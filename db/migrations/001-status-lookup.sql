-- One lookup table for both domains, replacing the free-text `empty.contents`
-- and the old `contents` table.
--
--   type_id 1 = Contents   how full the bin is        (Empty/Some/Full/Overflowing)
--   type_id 2 = Condition  what state the bin is in   (OK/Damaged/Missing)
--
-- report_show marks the values a member of the public can choose when they
-- scan. They get one flat list drawn from both types, because they will not
-- fill in two fields.
--
-- Run BEFORE deploying the code that uses it:
--     mysql -u <user> -p southesk_bpn < db/migrations/001-status-lookup.sql
--
-- This migration only ADDS. `empty.contents` and the `contents` table are
-- left in place so the currently-deployed code keeps working right up to the
-- moment the new code lands. Migration 002 removes them afterwards.

-- --------------------------------------------------------------- status ---

CREATE TABLE IF NOT EXISTS `status` (
  `status_id`   int(11)     NOT NULL AUTO_INCREMENT,
  `type_id`     int(11)     NOT NULL COMMENT '1=Contents, 2=Condition',
  `name`        varchar(20) NOT NULL,
  `report_show` tinyint(1)  NOT NULL DEFAULT 0 COMMENT 'Offer to the public on a scan',
  `sort_order`  int(11)     NOT NULL DEFAULT 0,
  PRIMARY KEY (`status_id`),
  UNIQUE KEY `type_name` (`type_id`, `name`),
  KEY `type_sort` (`type_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- IDs are set explicitly, not left to AUTO_INCREMENT, because the DEFAULT
-- values on `empty` and `report` below refer to them by number.
INSERT INTO `status` (`status_id`, `type_id`, `name`, `report_show`, `sort_order`) VALUES
  (1, 1, 'Empty',       0, 1),
  (2, 1, 'Some',        0, 2),
  (3, 1, 'Full',        1, 3),
  (4, 1, 'Overflowing', 1, 4),
  (5, 2, 'OK',          0, 1),
  (6, 2, 'Damaged',     1, 2),
  (7, 2, 'Missing',     1, 3);

-- ---------------------------------------------------------------- empty ---
-- Two separate values: contents defaults to Full, condition to OK, so a
-- volunteer emptying a perfectly good bin still just presses the button.

ALTER TABLE `empty`
  ADD COLUMN `contents_id`  int(11)       NOT NULL DEFAULT 3 AFTER `contents`,
  ADD COLUMN `condition_id` int(11)       NOT NULL DEFAULT 5 AFTER `contents_id`,
  ADD COLUMN `comments`     varchar(1000) DEFAULT NULL       AFTER `condition_id`;

-- Backfill from the old free text. Anything NULL or unrecognised keeps the
-- default of Full, which is exactly what IFNULL(e.contents,'Full') did in
-- the old queries.
UPDATE `empty` e
  JOIN `status` s ON s.`type_id` = 1 AND s.`name` = e.`contents`
  SET e.`contents_id` = s.`status_id`;

ALTER TABLE `empty`
  ADD KEY `contents_id` (`contents_id`),
  ADD KEY `condition_id` (`condition_id`),
  ADD CONSTRAINT `empty_contents`  FOREIGN KEY (`contents_id`)  REFERENCES `status` (`status_id`),
  ADD CONSTRAINT `empty_condition` FOREIGN KEY (`condition_id`) REFERENCES `status` (`status_id`);

-- --------------------------------------------------------------- report ---
-- A scan means "needs emptying", which is Full. The reporter can change it to
-- Overflowing, Damaged or Missing, and add a comment.

ALTER TABLE `report`
  ADD COLUMN `status_id`    int(11)       NOT NULL DEFAULT 3 AFTER `http_user_agent`,
  ADD COLUMN `comments`     varchar(1000) DEFAULT NULL       AFTER `status_id`,
  ADD COLUMN `updated_date` datetime      DEFAULT NULL       AFTER `comments`,
  ADD KEY `status_id` (`status_id`),
  ADD KEY `bin_reported` (`bin_no`, `reported_date`),
  ADD CONSTRAINT `report_status` FOREIGN KEY (`status_id`) REFERENCES `status` (`status_id`);
