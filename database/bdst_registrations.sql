-- ============================================================
-- BDS Tanzania ??? Member Registrations table
-- Import via cPanel > phpMyAdmin (select the site database first)
-- ============================================================

CREATE TABLE IF NOT EXISTS `bds_registrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(30) NOT NULL DEFAULT '',
  `full_name` varchar(150) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `age_group` varchar(10) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `email` varchar(150) NOT NULL,
  `region` varchar(60) NOT NULL,
  `district` varchar(80) NOT NULL,
  `professional_status` varchar(100) NOT NULL,
  `firm_name` varchar(150) DEFAULT NULL,
  `experience_years` varchar(20) NOT NULL,
  `education` varchar(100) NOT NULL,
  `service_areas` text NOT NULL,
  `industries` text NOT NULL,
  `client_segments` text NOT NULL,
  `clients_served` varchar(10) NOT NULL,
  `service_nature` text NOT NULL,
  `has_certification` tinyint(1) NOT NULL DEFAULT 0,
  `certifications` text DEFAULT NULL,
  `zones` text NOT NULL,
  `declaration` tinyint(1) NOT NULL DEFAULT 1,
  `cv_path` varchar(255) NOT NULL,
  `cv_original_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','reviewed','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bds_reg_reference` (`reference_no`),
  KEY `idx_bds_reg_status` (`status`,`submitted_at`),
  UNIQUE KEY `uq_bds_reg_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Email log (audit trail of every notification the system sends)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bds_email_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id INT UNSIGNED NOT NULL,
  reference_no VARCHAR(30) NOT NULL DEFAULT '',
  email_type VARCHAR(40) NOT NULL,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL,
  error_message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
