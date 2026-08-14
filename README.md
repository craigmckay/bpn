# Brechin Path Network — Waste Bins

Waste bin tracking for the [Brechin Path Network](https://southesk.com/bpn). Each bin
on the path network carries QR codes. Members of the public scan one to report that a
bin needs emptying; volunteers scan a different one to log that they have emptied it.
Both actions email the volunteer group, and the site charts activity by bin and by
person.

Plain PHP and MySQL/MariaDB, no framework and no build step. Written in 2020 and
still in service.

## How it works

There are two QR flows.

**Reporting a full bin** — public, no identification needed:

```
bpn_waste_report.php?bin_no=7
```

Records a row in `report` with status `Full` — a bare scan means "needs emptying" — and
emails the group. Repeat scans of the same bin within 5 minutes don't log again or send
another email, so a bin several people walk past doesn't cause a flurry of mail.

The page then offers an optional follow-up: a status (`Full`, `Overflowing`, `Damaged`,
`Missing`) and a free-text comment. Sending it updates the report row that was just
logged and sends a second, more prominent email with the status in the subject line.
Nobody has to use it — the scan alone is a complete report.

Because the form posts a `report_id` in a hidden field, the update is constrained to a
report for the same bin logged within the last hour. Otherwise the field could be edited
to rewrite any report on any bin.

**Logging an empty** — volunteers only, identified by a token in the URL:

```
bpn_empty.php?person=AB12CD34
```

The token is matched against `person.person_key`. The volunteer picks a bin, how full it
was (Empty / Some / Full / Overflowing), what condition it's in (OK / Damaged / Missing)
and optionally leaves a comment. That writes a row to `empty` and emails the group; a
condition other than OK is called out in the subject line, and comments appear in the
body. The same person emptying the same bin twice within 15 minutes is recorded once.

Contents defaults to Full and condition to OK, so the common case stays "pick a bin,
press the button".

The bin list is read from `bin` where `active=1`, so retiring a bin is a data change
rather than an edit to the page.

## Pages

The site is deployed into the document root of southesk.com, which is a **Joomla
install**. Only the two files whose URLs are fixed by printed QR codes sit at the root;
everything else lives in `bpnbins/` to stay out of Joomla's way.

Joomla owns `/index.php` and the root `.htaccess`. Neither is touched by this project.
`/bpn` is a Joomla menu redirect and is unrelated to the `bpnbins/` folder.

**At the web root** — these two cannot be renamed or moved, the QR codes point at them:

| File | Purpose |
|---|---|
| `bpn_waste_report.php` | Public landing page for a "this bin needs emptying" QR scan |
| `bpn_empty.php` | Volunteer landing page for an "I emptied this" QR scan |

**In `bpnbins/`**:

| File | Purpose |
|---|---|
| `index.php` | Charts of empties by person and by bin, filterable by period. Served at `/bpnbins/` |
| `bpn_status.php` | Which bins probably need emptying, and which have seen no activity for a week. Served at `/bpnbins/status` |
| `bpn_stats_bin.php` | Monthly breakdown for a single bin |
| `bpn_util.php` | Page head/foot, shared tables, and the multipart mail helper |
| `bpn_db.php` | Database connection, config constants and input-validation helpers |
| `bpn.css` | The only stylesheet. Every page links it; no page has its own `<style>` |
| `config.php` | Database credentials. Gitignored |
| `.htaccess` | `DirectoryIndex`, the `/status` rewrite, and denial of direct access to config and `.sql` files |
| `Chart.html` | Standalone Chart.js prototype with hardcoded numbers; not part of the app |
| `bpn.png` | Map of the path network, shown on the report page |

**In `db/`** — setup scripts only, never read by the application at runtime:

| File | Purpose |
|---|---|
| `schema.sql` | Table structure |
| `seed.sample.sql` | Anonymised rows for local work |
| `migrations/` | Numbered `ALTER` scripts. **Run these against the live database before deploying the code that needs them** |
| `.htaccess` | Denies all HTTP access. The folder sits in the Joomla document root, so without it these would be downloadable |

**In `deploy/`** — never uploaded:

| File | Purpose |
|---|---|
| `deploy.cmd` | Launcher. `deploy\deploy.cmd` to deploy, `-WhatIf` to list files, `-Test` to check the connection |
| `deploy.ps1` | Uploads over FTPS using `curl.exe`, which ships with Windows 10/11 |
| `deploy.sample.ini` | Template for `deploy.ini`, which holds the FTP password and is gitignored |

The deploy **only uploads and never deletes**. The target is the Joomla document root,
so a mirroring sync would remove the entire Joomla installation. It also excludes
`config.php`, so the server keeps its own settings.

## Database

Five tables, defined in `db/schema.sql`.

| Table | Holds |
|---|---|
| `bin` | Bin number, name, whether it's still active, and the volunteer usually responsible |
| `person` | Volunteers: name, initials, `person_key` token, email |
| `empty` | One row each time a bin is emptied — bin, volunteer, timestamp, how full it was |
| `report` | One row each time the public reports a bin — bin, timestamp, IP, user agent, status, comments |
| `status` | Lookup for both `empty` and `report` — see below |

`person.person_key` is the value that appears in a volunteer's QR URL. `person.initials`
is what the stats charts group and filter by.

### The `status` lookup

Two domains in one table, separated by `type_id`, because they are not the same thing:

| type_id | Domain | Values |
|---|---|---|
| 1 | **Contents** — how full the bin is | Empty, Some, Full, Overflowing |
| 2 | **Condition** — what state the bin is in | OK, Damaged, Missing |

A volunteer emptying a bin picks one of each: `empty.contents_id` and
`empty.condition_id`, defaulting to Full and OK so the common case is still one button
press.

A member of the public gets a single flat list — every row with `report_show = 1`, which
is Full, Overflowing, Damaged and Missing. They won't fill in two fields for a bin
they're walking past. `report.status_id` defaults to Full, since that is what a bare
scan means.

Two constraints on adding values:

- Contents names must be **single words**. The chart pages build a JavaScript variable
  name from the lowercased value (`var overflowing = [...]`), so a space breaks the page.
- `status_id` 3 (Full) and 5 (OK) are referenced by column DEFAULTs on `empty` and
  `report`. Don't renumber them.

The production dump is deliberately **not** in this repository — it contains volunteer
names, email addresses and tokens. `db/schema.sql` is structure only, and
`db/seed.sample.sql` has anonymised rows so the pages have something to render.

## Local setup

Requires PHP 8.1+ and MySQL/MariaDB. Production runs PHP 8.1 and MariaDB 10.3;
locally this is served from XAMPP at `C:\xampp\htdocs\bpn`.

1. **Create the database and load the schema**

   ```
   mysql -u root -p -e "CREATE DATABASE southesk_bpn"
   mysql -u root -p southesk_bpn < db/schema.sql
   mysql -u root -p southesk_bpn < db/seed.sample.sql
   ```

2. **Add your credentials**

   ```
   cp bpnbins/config.sample.php bpnbins/config.php
   ```

   Then edit `bpnbins/config.php` with your database host, name, user and password.
   It is gitignored and must never be committed.

3. Browse to `http://localhost/bpn/bpnbins/`.

   Locally the project sits in a `bpn/` folder under `htdocs`, so paths are one level
   deeper than production. The absolute links in the pages (`/bpnbins/...`) assume the
   project is at the document root, so they will not resolve under XAMPP unless you
   point a virtual host at the project folder.

No `php.ini` changes are needed. The pages use `<?php` throughout, and the `<?=` short
echo tags they rely on are available unconditionally in PHP 5.4 and later.

Note that the "emailing" steps use PHP's `mail()`, which generally won't do anything
useful on a local XAMPP install. The pages still work; the mail just goes nowhere.

## Configuration

Everything environment-specific lives in `bpnbins/config.php`, which is gitignored.
`bpnbins/config.sample.php` is the committed template.

| Key | Required | Notes |
|---|---|---|
| `host`, `database`, `username`, `password` | yes | Database connection |
| `charset` | no | Defaults to `utf8mb4` |
| `base_path` | no | URL prefix the site is served under. Empty on production (document root); `/bpn` on local XAMPP. Get it wrong and the stylesheet 404s, so every page renders unstyled with no other clue |
| `notify_email` | **yes** | Where bin reports and empty notifications go. The page fails with a clear message if it's missing, rather than silently mailing nobody |
| `analytics_id` | no | Google Analytics measurement ID. Leave empty and no analytics markup is emitted at all — which is what you want locally |
| `from_email` | no | `From:` address on outgoing mail. Left unset, PHP's default is used, which is the long-standing behaviour. Setting it usually helps deliverability |

`bpn_db.php` reads these once and exposes `BPN_NOTIFY_EMAIL`, `BPN_ANALYTICS_ID` and
`BPN_FROM_EMAIL`.

## Markup and styling

Every page opens with `bpn_head($title, $wide)` and closes with `bpn_foot()`, both in
`bpn_util.php`. That guarantees each one gets a doctype, `charset`, `lang`, and — most
importantly — a `viewport` meta tag.

The absence of that viewport tag was why the original pages used font sizes like `4vw`.
Without it a phone lays out at a virtual ~980px and zooms out, so text had to be sized
against that rather than the real screen. With the tag present, `vw` units would be
enormous, so the type scale now uses `clamp()`: it still grows with the screen but stops
at a sensible maximum, which is what makes the pages usable on a desktop as well as a
phone.

The stylesheet is linked as `bpn.css?v=<mtime>`. Phones cache aggressively, and a
half-applied stale stylesheet is genuinely hard to diagnose — the page still looks
styled, so caching isn't the first thing you suspect. Tying the query string to the
file's modification time means an edit always produces a new URL.

Two layout conventions worth knowing before editing the CSS:

- **Never put `display: grid` on a `<fieldset>`.** Browsers before roughly 2020 ignore
  it and fall back to one column. Every option group therefore wraps its choices in a
  plain `<div>` inside the fieldset, and the fieldset carries `.choices-group` purely to
  strip its default border.
- The bin picker uses `.choices--bins`, a fixed two-column grid (three above ~45rem)
  with compact rows, so all ~28 active bins fit one phone screen. Short option sets use
  `.choices--inline`, which wraps.

