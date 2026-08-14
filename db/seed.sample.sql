-- Brechin Path Network - sample seed data
--
-- Anonymised rows, enough to run the app locally. The real dataset is never
-- committed: it contains volunteer names, email addresses and person_key
-- access tokens.
--
-- The `status` lookup rows are NOT here - they are reference data the
-- application requires, so they live in schema.sql.
--
-- Usage:
--   mysql -u <user> -p <database> < db/schema.sql
--   mysql -u <user> -p <database> < db/seed.sample.sql

--
-- Sample volunteers. person_key is the token used to identify someone when
-- they scan a bin, so treat real values as secrets.
--
INSERT INTO `person` (`person_id`, `person_name`, `person_key`, `initials`, `email`) VALUES
(1, 'Ada Example', 'AE000001', 'AE', 'ada@example.com'),
(2, 'Bob Example', 'BE000002', 'BE', 'bob@example.com'),
(3, 'Cara Example', 'CE000003', 'CE', 'cara@example.com');

--
-- Sample bins
--
INSERT INTO `bin` (`bin_no`, `bin_name`, `active`, `person_id`) VALUES
(1, 'Riverside Path', 1, 1),
(2, 'Old Bridge', 1, 1),
(3, 'Station Junction', 1, 2),
(4, 'Mill Wynd', 1, 3),
(5, 'Retired Bin', 0, 3);

--
-- A few empties, so the stats pages have something to plot.
-- contents_id: 1 Empty, 2 Some, 3 Full, 4 Overflowing
-- condition_id: 5 OK, 6 Damaged, 7 Missing
--
INSERT INTO `empty` (`empty_id`, `bin_no`, `emptied_date`, `person_id`, `contents_id`, `condition_id`, `comments`) VALUES
(1, 1, '2024-01-06 09:14:00', 1, 3, 5, NULL),
(2, 2, '2024-01-06 09:41:00', 1, 2, 5, NULL),
(3, 1, '2024-01-13 10:02:00', 2, 2, 5, NULL),
(4, 3, '2024-01-13 10:35:00', 2, 4, 5, NULL),
(5, 4, '2024-01-20 11:20:00', 3, 1, 6, 'Lid hinge broken'),
(6, 1, '2024-01-27 09:05:00', 1, 3, 5, NULL);

--
-- Public reports. status_id 3 is Full - what a bare scan means.
--
INSERT INTO `report` (`report_id`, `bin_no`, `reported_date`, `remote_addr`, `http_user_agent`, `status_id`, `comments`) VALUES
(1, 1, '2024-01-12 16:22:00', '192.0.2.10', 'Mozilla/5.0 (sample)', 3, NULL),
(2, 3, '2024-01-13 08:50:00', '192.0.2.11', 'Mozilla/5.0 (sample)', 4, 'Rubbish blowing about on the path'),
(3, 4, '2024-01-14 12:05:00', '192.0.2.12', 'Mozilla/5.0 (sample)', 6, 'Lid has come off');
