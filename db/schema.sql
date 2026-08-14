-- Brechin Path Network - Waste Bins
-- Database structure, plus the `status` lookup rows the application needs.
--
--   mysql -u <user> -p southesk_bpn < db/schema.sql
--   mysql -u <user> -p southesk_bpn < db/seed.sample.sql   (optional test data)
--
-- No real data lives here. See db/seed.sample.sql for anonymised rows.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
--
-- `status` - one lookup for two domains, separated by type_id.
--
--   type_id 1 = Contents   how full the bin is
--   type_id 2 = Condition  what state the bin is in
--
-- report_show marks the values offered to the public when they scan. They
-- see a single list drawn from both types, because someone walking past a
-- bin will not fill in two fields.
--
-- NOTE: `name` is lowercased to build a JavaScript variable name on the
-- stats charts, so Contents values must stay single words.
--

CREATE TABLE `status` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL COMMENT '1=Contents, 2=Condition',
  `name` varchar(20) NOT NULL,
  `report_show` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Offer to the public on a scan',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`status_id`),
  UNIQUE KEY `type_name` (`type_id`,`name`),
  KEY `type_sort` (`type_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reference data, not sample data: the application requires these rows, and
-- the IDs are referenced by the DEFAULT values on `empty` and `report`.
INSERT INTO `status` (`status_id`, `type_id`, `name`, `report_show`, `sort_order`) VALUES
  (1, 1, 'Empty',       0, 1),
  (2, 1, 'Some',        0, 2),
  (3, 1, 'Full',        1, 3),
  (4, 1, 'Overflowing', 1, 4),
  (5, 2, 'OK',          0, 1),
  (6, 2, 'Damaged',     1, 2),
  (7, 2, 'Missing',     1, 3);

-- --------------------------------------------------------
--
-- `bin` - the physical bins on the path network.
--

CREATE TABLE `bin` (
  `bin_no` int(11) NOT NULL COMMENT 'Bin Number',
  `bin_name` varchar(200) DEFAULT NULL COMMENT 'Bin Name',
  `active` int(11) NOT NULL DEFAULT 1 COMMENT 'Active?',
  `person_id` int(11) DEFAULT NULL COMMENT 'Default Person Responsible',
  PRIMARY KEY (`bin_no`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
--
-- `person` - the volunteers. person_key is the token in their QR URL.
--

CREATE TABLE `person` (
  `person_id` int(11) NOT NULL AUTO_INCREMENT,
  `person_name` varchar(50) NOT NULL,
  `person_key` varchar(8) NOT NULL,
  `initials` varchar(4) NOT NULL,
  `email` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`person_id`),
  UNIQUE KEY `Person_Key` (`person_key`) USING BTREE,
  UNIQUE KEY `initials` (`initials`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- `empty` - one row each time a volunteer empties a bin.
-- Contents defaults to Full and condition to OK, so the common case is a
-- single button press.
--

CREATE TABLE `empty` (
  `empty_id` int(11) NOT NULL AUTO_INCREMENT,
  `bin_no` int(11) NOT NULL,
  `emptied_date` datetime NOT NULL DEFAULT current_timestamp(),
  `person_id` int(11) NOT NULL,
  `contents_id` int(11) NOT NULL DEFAULT 3 COMMENT 'status.status_id, type 1',
  `condition_id` int(11) NOT NULL DEFAULT 5 COMMENT 'status.status_id, type 2',
  `comments` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`empty_id`),
  KEY `contents_id` (`contents_id`),
  KEY `condition_id` (`condition_id`),
  CONSTRAINT `empty_contents`  FOREIGN KEY (`contents_id`)  REFERENCES `status` (`status_id`),
  CONSTRAINT `empty_condition` FOREIGN KEY (`condition_id`) REFERENCES `status` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------
--
-- `report` - one row each time a member of the public scans a bin.
-- status_id defaults to Full: a bare scan means "needs emptying". The
-- reporter can change it and add a comment on the page that follows.
--

CREATE TABLE `report` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `bin_no` int(11) NOT NULL,
  `reported_date` datetime NOT NULL DEFAULT current_timestamp(),
  `remote_addr` varchar(50) NOT NULL,
  `http_user_agent` varchar(1000) NOT NULL,
  `status_id` int(11) NOT NULL DEFAULT 3 COMMENT 'status.status_id',
  `comments` varchar(1000) DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  KEY `status_id` (`status_id`),
  KEY `bin_reported` (`bin_no`,`reported_date`),
  CONSTRAINT `report_status` FOREIGN KEY (`status_id`) REFERENCES `status` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
