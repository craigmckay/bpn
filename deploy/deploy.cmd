@echo off
REM Launcher for deploy.ps1 - so the script can be double-clicked or run
REM without changing your PowerShell execution policy.
REM
REM   deploy\deploy.bat            deploy, prompts first
REM   deploy\deploy.bat -WhatIf    list what would be sent, upload nothing

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy.ps1" %*
if errorlevel 1 (
  echo.
  pause
  exit /b 1
)
echo.
pause
exit /b 0
