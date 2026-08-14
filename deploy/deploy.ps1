<#
    Deploy the BPN bin site to the Joomla document root over FTP/FTPS.

    Uses curl.exe, which ships with Windows 10 1803+ and Windows 11.
    No third-party FTP client required.

    Sends:       bpn_empty.php, bpn_waste_report.php, bpnbins\ (recursive)
    Never sends: bpnbins\config.php, bpnbins\config.sample.php, Chart.html,
                 .git\, db\, deploy\, README.md, .gitignore, .gitattributes

    SAFETY: uploads only. Nothing on the server is ever deleted. The target is
    a live Joomla installation - a mirroring delete would wipe it.

    Usage:
        .\deploy.cmd -Test         connect and list the remote path, upload nothing
        .\deploy.cmd -WhatIf       list what would be sent, connect to nothing
        .\deploy.cmd               deploy, prompts first
        .\deploy.cmd -Force        deploy without the prompt
#>

[CmdletBinding()]
param(
    [switch]$WhatIf,
    [switch]$Force,
    [switch]$Test
)

$ErrorActionPreference = 'Stop'

$Project = Split-Path -Parent $PSScriptRoot
$IniPath = Join-Path $PSScriptRoot 'deploy.ini'

# ---------------------------------------------------------------- settings --

if (-not (Test-Path $IniPath)) {
    Write-Host "ERROR: deploy\deploy.ini not found." -ForegroundColor Red
    Write-Host "       copy deploy\deploy.sample.ini deploy\deploy.ini and fill it in."
    exit 1
}

$cfg = @{}
foreach ($line in Get-Content $IniPath) {
    $t = $line.Trim()
    if ($t -eq '' -or $t.StartsWith(';') -or $t.StartsWith('#')) { continue }
    $i = $t.IndexOf('=')
    if ($i -lt 1) { continue }
    $cfg[$t.Substring(0, $i).Trim()] = $t.Substring($i + 1).Trim()
}

foreach ($k in 'HOST', 'USER', 'PASS', 'PROTOCOL', 'REMOTE') {
    if (-not $cfg.ContainsKey($k) -or $cfg[$k] -eq '') {
        Write-Host "ERROR: $k is not set in deploy\deploy.ini" -ForegroundColor Red
        exit 1
    }
}

$remote = '/' + $cfg['REMOTE'].Trim('/')

switch ($cfg['PROTOCOL'].ToLower()) {
    'ftp'  { $scheme = 'ftp://';  $tls = @() }
    'ftps' { $scheme = 'ftp://';  $tls = @('--ssl-reqd') }
    'sftp' { $scheme = 'sftp://'; $tls = @() }
    default {
        Write-Host "ERROR: PROTOCOL must be ftp, ftps or sftp." -ForegroundColor Red
        exit 1
    }
}

if ($cfg['INSECURE'] -eq '1') { $tls += '--insecure' }

$curl = (Get-Command curl.exe -ErrorAction SilentlyContinue).Source
if (-not $curl) {
    Write-Host "ERROR: curl.exe not found. It ships with Windows 10 1803+." -ForegroundColor Red
    Write-Host "       On older Windows, install curl or use FileZilla manually."
    exit 1
}

# ------------------------------------------------------------- file list ----

Push-Location $Project
try {
    $files = @('bpn_empty.php', 'bpn_waste_report.php')

    # config.php      - the server keeps its own
    # config.sample.php - a template, only useful in the repo
    # Chart.html      - dead prototype, not part of the app
    $excluded = @('config.php', 'config.sample.php', 'Chart.html')
    $files += Get-ChildItem -Path 'bpnbins' -Recurse -File -Force |
        Where-Object { $excluded -notcontains $_.Name } |
        ForEach-Object { $_.FullName.Substring($Project.Length + 1) }

    $missing = $files | Where-Object { -not (Test-Path (Join-Path $Project $_)) }
    if ($missing) {
        Write-Host "ERROR: expected files are missing:" -ForegroundColor Red
        $missing | ForEach-Object { Write-Host "       $_" }
        exit 1
    }

    # ------------------------------------------------------------- report --

    Write-Host ''
    Write-Host "  Project : $Project"
    Write-Host "  Host    : $($cfg['HOST'])$remote  ($($cfg['PROTOCOL']))"
    Write-Host "  User    : $($cfg['USER'])"
    Write-Host "  Files   : $($files.Count)"
    Write-Host "  Deletes : none"
    Write-Host ''
    $files | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
    Write-Host ''

    if ($WhatIf) {
        Write-Host "-WhatIf: nothing uploaded." -ForegroundColor Yellow
        exit 0
    }

    # Credentials go in a temp config file rather than the command line, so
    # they never appear in the process list.
    function New-CurlConfig {
        $path = [System.IO.Path]::GetTempFileName()
        $u = $cfg['USER'] -replace '"', '\"'
        $p = $cfg['PASS'] -replace '"', '\"'
        Set-Content -Path $path -Value "user = `"$u`:$p`"" -Encoding ASCII
        return $path
    }

    if ($Test) {
        Write-Host "Listing $remote to confirm it is the web root..." -ForegroundColor Cyan
        Write-Host ''
        $curlCfg = New-CurlConfig
        try {
            $args = @(
                '--config', $curlCfg
                '--silent', '--show-error', '--fail'
                '--connect-timeout', '20'
                '--list-only'
            ) + $tls + @("$scheme$($cfg['HOST'])$remote/")

            $listing = & $curl @args 2>&1
            if ($LASTEXITCODE -ne 0) {
                Write-Host "Connection failed: $listing" -ForegroundColor Red
                if ($LASTEXITCODE -eq 60 -or "$listing" -match 'WRONG_PRINCIPAL|certificate') {
                    Write-Host ''
                    Write-Host 'That is a certificate NAME MISMATCH, not a connection failure.'  -ForegroundColor Yellow
                    Write-Host 'The server presented a certificate for a different hostname -'   -ForegroundColor Yellow
                    Write-Host 'usual on shared hosting. Options, best first:'                   -ForegroundColor Yellow
                    Write-Host '  1. Try HOST=southesk.com instead of ftp.southesk.com'
                    Write-Host '  2. Set INSECURE=1 in deploy.ini - still encrypted, but no'
                    Write-Host '     longer verifies the server identity'
                    Write-Host '  Do NOT set PROTOCOL=ftp - that sends your password in clear.'  -ForegroundColor Yellow
                }
                exit 1
            }
            $listing | ForEach-Object { Write-Host "    $_" }
            Write-Host ''
            $names = @($listing)
            if ($names -contains 'public_html' -or $names -contains 'www') {
                Write-Host 'WARNING: this looks like your home directory, not the web root.' -ForegroundColor Yellow
                Write-Host "         A 'public_html' entry is visible, so REMOTE should"    -ForegroundColor Yellow
                Write-Host "         probably be /public_html rather than $remote."          -ForegroundColor Yellow
            } elseif ($names -contains 'index.php' -or $names -contains 'configuration.php') {
                Write-Host 'Looks right - Joomla files are present at this path.' -ForegroundColor Green
            } else {
                Write-Host 'Could not tell. Check the listing above looks like your web root.' -ForegroundColor Yellow
            }
        }
        finally {
            Remove-Item $curlCfg -Force -ErrorAction SilentlyContinue
        }
        exit 0
    }

    if (-not $Force) {
        $ok = Read-Host 'Deploy to the LIVE site? [y/N]'
        if ($ok -ne 'y' -and $ok -ne 'Y') { Write-Host 'Cancelled.'; exit 0 }
    }

    # ------------------------------------------------------------- upload --

    $curlCfg = New-CurlConfig

    $failed = @()
    $done = 0

    try {
        foreach ($rel in $files) {
            $local = Join-Path $Project $rel
            $dir = Split-Path $rel -Parent
            $remoteDir = if ($dir) { "$remote/$($dir -replace '\\','/')/" } else { "$remote/" }
            $url = "$scheme$($cfg['HOST'])$remoteDir"

            $args = @(
                '--config', $curlCfg
                '--upload-file', $local
                '--ftp-create-dirs'
                '--silent', '--show-error'
                '--fail'
                '--connect-timeout', '20'
            ) + $tls + @($url)

            $out = & $curl @args 2>&1
            if ($LASTEXITCODE -eq 0) {
                $done++
                Write-Host "  ok    $rel" -ForegroundColor Green
            } else {
                $failed += $rel
                Write-Host "  FAIL  $rel  $out" -ForegroundColor Red
            }
        }
    }
    finally {
        Remove-Item $curlCfg -Force -ErrorAction SilentlyContinue
    }

    Write-Host ''
    if ($failed.Count) {
        Write-Host "$done uploaded, $($failed.Count) FAILED." -ForegroundColor Red
        Write-Host 'The site may be in a half-deployed state. Re-run once the cause is fixed.'
        exit 1
    }

    Write-Host "$done files uploaded." -ForegroundColor Green
    Write-Host ''
    Write-Host 'Now check:'
    Write-Host '  - the stats page at /bpnbins/'
    Write-Host '  - a bin report at /bpn_waste_report.php?bin_no=1'
    Write-Host '  - the Joomla site still loads'
    exit 0
}
finally {
    Pop-Location
}
