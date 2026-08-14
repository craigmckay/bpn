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

| File | Purpose |
|---|---|
| `bpn_waste_report.php` | Public landing page for a "this bin needs emptying" QR scan |
| `bpn_empty.php` | Volunteer landing page for an "I emptied this" QR scan |
| `bpn_status.php` | Which bins probably need emptying, and which have seen no activity for a week |
| `bpn_stats.php` | Charts of empties by person and by bin, filterable by period |
| `bpn_stats_bin.php` | Monthly breakdown for a single bin |
| `bpn_util.php` | Shared table-rendering functions used by the status and empty pages |
| `bpn_db.php` | Database connection and input-validation helpers |
| `bpn_test.php` | Near-copy of `bpn_empty.php` that mails a single address instead of the group |
| `Chart.html` | Standalone Chart.js prototype with hardcoded numbers; not part of the app |
| `bpn.png` | Map of the path network, shown on the report page |

## Database

Five tables, defined in `data/schema.sql`.

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
names, email addresses and tokens. `data/schema.sql` is structure only, and
`data/seed.sample.sql` has anonymised rows so the pages have something to render.

## Local setup

Requires PHP 8.1+ and MySQL/MariaDB. Production runs PHP 8.1 and MariaDB 10.3;
locally this is served from XAMPP at `C:\xampp\htdocs\bpn`.

1. **Create the database and load the schema**

   ```
   mysql -u root -p -e "CREATE DATABASE southesk_bpn"
   mysql -u root -p southesk_bpn < data/schema.sql
   mysql -u root -p southesk_bpn < data/seed.sample.sql
   ```

2. **Add your credentials**

   ```
   cp config.sample.php config.php
   ```

   Then edit `config.php` with your database host, name, user and password.
   `config.php` is gitignored and must never be committed.

3. Browse to `http://localhost/bpn/bpn_status.php`.

No `php.ini` changes are needed. The pages use `<?php` throughout, and the `<?=` short
echo tags they rely on are available unconditionally in PHP 5.4 and later.

Note that the "emailing" steps use PHP's `mail()`, which generally won't do anything
useful on a local XAMPP install. The pages still work; the mail just goes nowhere.

## Configuration

Two things are currently hardcoded rather than configured:

- The notification address, `brechinpathnetwork@googlegroups.com`, appears in
  `bpn_empty.php` and `bpn_waste_report.php`.
- The Google Analytics tag `G-MPXXSQYB9E` is inlined in the `<head>` of most pages.

