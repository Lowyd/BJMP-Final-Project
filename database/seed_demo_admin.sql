-- Demo administrator for capstone presentation.
USE jmris;

INSERT INTO users (username, full_name, password_hash, role, unit, is_active)
VALUES (
  'adminofficer@gmail.com',
  'System Administrator',
  CONVERT(UNHEX('2432792431302430304476563545554E634A616678766637346F674665444333664B30304B5A474B49564E4C306D49344D356C766833576C4944586D') USING utf8mb4),
  'admin',
  'Admin',
  1
)
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  unit = VALUES(unit),
  is_active = 1,
  failed_attempts = 0,
  locked_until = NULL;