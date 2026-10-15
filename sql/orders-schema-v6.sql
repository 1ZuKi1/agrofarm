-- Orders schema v6 — named staff accounts and an activity log for /admin/ (2026-10).
-- Run once via phpMyAdmin's SQL tab, AFTER sql/orders-schema-v5.sql and
-- BEFORE deploying the matching admin/ pages. Until it is run, /admin/login
-- says so; the public site does not use these tables.
--
-- admin_users: one row per person. role 'admin' (Админ) can also manage
--   people, the delivery fee and closed days; 'staff' (Ажилтан) handles
--   orders, quote requests, products and news. must_change = log in with a
--   temporary password and choose a new one first. failed_logins/locked_until
--   = 5 wrong passwords lock that email for 15 minutes.
--   The table starts empty: the first visit to /admin/login asks for the old
--   shared password once, to create the first Админ.
-- admin_log: who did what and when (delivered, cancelled, quote answered,
--   product saved, news published, settings and people changed). user_name
--   is copied in, so the history still reads right if an account is removed.
-- inquiries.staff_note: a short note staff keep on a quote request.

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  password_hash VARCHAR(255) NOT NULL,
  must_change TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  failed_logins INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  user_name VARCHAR(120) NOT NULL,
  action VARCHAR(40) NOT NULL,
  target_type VARCHAR(20) NOT NULL,
  target_id INT UNSIGNED NULL,
  detail VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_log_target (target_type, target_id),
  INDEX idx_admin_log_created (created_at),
  CONSTRAINT fk_admin_log_user FOREIGN KEY (user_id)
    REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE inquiries ADD COLUMN staff_note TEXT NULL AFTER message;
