-- JMRIS upgrade for databases created before the password/RBAC update.
-- Run this once in phpMyAdmin with the jmris database selected.
USE jmris;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER full_name;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS unit VARCHAR(100) NOT NULL DEFAULT 'Admin' AFTER role;

-- Create or refresh the capstone administrator account.
INSERT INTO users
(username, full_name, password_hash, role, unit, is_active)
VALUES
(
  'adminofficer@gmail.com',
  'System Administrator',
  '$2y$10$00DvV5EUNcJafxvf74ogFeDC3fK00KZGKIVNL0mI4M5lvh3WlIDXm',
  'admin',
  'Admin',
  1
)
ON DUPLICATE KEY UPDATE
  password_hash = VALUES(password_hash),
  role = 'admin',
  unit = 'Admin',
  is_active = 1;

ALTER TABLE account_requests
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER contact_email;