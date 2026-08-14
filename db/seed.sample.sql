-- Brechin Path Network - sample seed data
--
-- Reference data plus a handful of anonymised rows, enough to run the app
-- locally. The real dataset is never committed: it contains volunteer names,
-- email addresses and person_key access tokens.
--
-- Usage:
--   mysql -u <user> -p <database> < data/schema.sql
--   mysql -u <user> -p <database> < data/seed.sample.sql

--
-- Lookup values for `contents` (required - the app reads these directly)
--
INSERT INTO `contents` (`contents`) VALUES
('Empty'),
('Some'),
('Full'),
('Overflowing');

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
-- A few empties, so the stats pages have something to plot
--
INSERT INTO `empty` (`empty_id`, `bin_no`, `emptied_date`, `person_id`, `contents`) VALUES
(1, 1, '2024-01-06 09:14:00', 1, 'Full'),
(2, 2, '2024-01-06 09:41:00', 1, 'Some'),
(3, 1, '2024-01-13 10:02:00', 2, 'Some'),
(4, 3, '2024-01-13 10:35:00', 2, 'Overflowing'),
(5, 4, '2024-01-20 11:20:00', 3, 'Empty'),
(6, 1, '2024-01-27 09:05:00', 1, 'Full');

--
-- A couple of public overflow reports
--
INSERT INTO `report` (`report_id`, `bin_no`, `reported_date`, `remote_addr`, `http_user_agent`) VALUES
(1, 1, '2024-01-12 16:22:00', '192.0.2.10', 'Mozilla/5.0 (sample)'),
(2, 3, '2024-01-13 08:50:00', '192.0.2.11', 'Mozilla/5.0 (sample)');
