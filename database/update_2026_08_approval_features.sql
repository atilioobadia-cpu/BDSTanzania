-- ============================================================================
-- BDS Tanzania — one-time database patch for approval-pack features
-- Run ONCE in cPanel → phpMyAdmin → select the site database → SQL tab.
-- Safe to re-run on MariaDB (cPanel default): uses IF NOT EXISTS guards.
--
-- Adds:
--   1. bds_email_log table          (audit trail of every email sent)
--   2. rejection_reason column      (reason shown to rejected applicants)
--   3. unique index on email        (blocks duplicate registrations)
-- ============================================================================

-- 1. Email log ---------------------------------------------------------------
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

-- 2. Rejection reason ---------------------------------------------------------
ALTER TABLE bds_registrations
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER admin_notes;

-- 3. Unique email index -------------------------------------------------------
-- IMPORTANT (MySQL-only hosts): if this statement errors with
-- "Duplicate entry", find duplicates first with:
--     SELECT email, COUNT(*) c FROM bds_registrations GROUP BY email HAVING c > 1;
-- resolve them, then run this statement again.
ALTER TABLE bds_registrations
  ADD UNIQUE KEY IF NOT EXISTS uq_bds_reg_email (email);
