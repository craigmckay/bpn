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

Records a row in `report` and emails the group. Repeat scans of the same bin within
5 minutes are ignored, so a bin that several people walk past doesn't send a flurry
of mail.

**Logging an empty** — volunteers only, identified by a token in the URL:

```
bpn_empty.php?person=AB12CD34
```

The token is matched against `person.person_key`. The volunteer picks a bin and how
full it was (Empty / Some / Full / Overflowing), which writes a row to `empty` and
emails the group. The same person emptying the same bin twice within 15 minutes is
recorded once.

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
| `bpn_util.php` | Shared table-rendering functions |
| `bpn_db.php` | Database connection and input-validation helpers |
| `config.php` | Database credentials. Gitignored |
| `.htaccess` | `DirectoryIndex`, the `/status` rewrite, and denial of direct access to config and `.sql` files |
| `Chart.html` | Standalone Chart.js prototype with hardcoded numbers; not part of the app |
| `bpn.png` | Map of the path network, shown on the report page |

**In `db/`** — setup scripts only, never read by the application at runtime:

| File | Purpose |
|---|---|
| `schema.sql` | Table structure |
| `seed.sample.sql` | Anonymised rows for local work |
| `.htaccess` | Denies all HTTP access. The folder sits in the Joomla document root, so without it these would be downloadable |

## Database

Five tables, defined in `db/schema.sql`.

| Table | Holds |
|---|---|
| `bin` | Bin number, name, whether it's still active, and the volunteer usually responsible |
| `person` | Volunteers: name, initials, `person_key` token, email |
| `empty` | One row each time a bin is emptied — bin, volunteer, timestamp, how full it was |
| `report` | One row each time the public reports a bin — bin, timestamp, IP, user agent |
| `contents` | Lookup: Empty, Some, Full, Overflowing |

`person.person_key` is the value that appears in a volunteer's QR URL. `person.initials`
is what the stats charts group and filter by.

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
| `notify_email` | **yes** | Where bin reports and empty notifications go. The page fails with a clear message if it's missing, rather than silently mailing nobody |
| `analytics_id` | no | Google Analytics measurement ID. Leave empty and no analytics markup is emitted at all — which is what you want locally |

`bpn_db.php` reads these once and exposes `BPN_NOTIFY_EMAIL` and `BPN_ANALYTICS_ID`.
The analytics markup is emitted by `bpn_analytics_tag()`, called inside `<head>` on each
page, so the tag exists in exactly one place rather than being pasted into five files.

