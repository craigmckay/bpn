-- Removes what migration 001 replaced.
--
-- Run this ONLY AFTER the new code is deployed and working. Until then the
-- live pages still read `empty.contents`, and dropping it early takes the
-- site down.
--
--     mysql -u <user> -p southesk_bpn < db/migrations/002-drop-old-contents.sql
--
-- Check first that nothing was missed by 001's backfill. This should return
-- no rows:
--
--     SELECT e.empty_id, e.contents, e.contents_id
--       FROM empty e JOIN status s ON s.status_id = e.contents_id
--      WHERE s.name <> e.contents;

ALTER TABLE `empty` DROP COLUMN `contents`;

DROP TABLE `contents`;
