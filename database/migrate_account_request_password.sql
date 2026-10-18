-- Run once if account_requests already existed before the preferred-password update.
-- New installations already include this column in schema.sql.
ALTER TABLE account_requests
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER contact_email;