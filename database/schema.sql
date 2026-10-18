-- JMRIS 1.0 / MySQL 8.0+ / MariaDB 10.4+ (XAMPP)
-- Import in phpMyAdmin after creating the database, or use the command line.
CREATE DATABASE IF NOT EXISTS jmris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jmris;

CREATE TABLE IF NOT EXISTS inmates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inmate_no VARCHAR(40) NOT NULL UNIQUE,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NOT NULL,
  sex ENUM('Male','Female','Other','Undisclosed') NOT NULL DEFAULT 'Undisclosed',
  birth_date DATE NULL,
  admission_date DATE NOT NULL,
  status ENUM('Active','Released','Transferred') NOT NULL DEFAULT 'Active',
  status_date DATE NULL,
  housing_unit VARCHAR(100) NULL,
  case_reference VARCHAR(100) NULL,
  remarks TEXT NULL,
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_inmate_name (last_name, first_name),
  INDEX idx_inmate_status (status),
  INDEX idx_inmate_admission (admission_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS personnel (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_no VARCHAR(40) NOT NULL UNIQUE,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  rank_title VARCHAR(100) NOT NULL,
  position VARCHAR(150) NOT NULL,
  contact_number VARCHAR(40) NULL,
  status ENUM('Active','Inactive','On Leave','Separated') NOT NULL DEFAULT 'Active',
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_personnel_name (last_name, first_name),
  INDEX idx_personnel_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS visitors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_no VARCHAR(40) NOT NULL UNIQUE,
  full_name VARCHAR(200) NOT NULL,
  contact_number VARCHAR(40) NULL,
  remarks TEXT NULL,
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_visitor_name (full_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_no VARCHAR(40) NOT NULL UNIQUE,
  visitor_id BIGINT UNSIGNED NOT NULL,
  inmate_id BIGINT UNSIGNED NOT NULL,
  relationship VARCHAR(120) NOT NULL,
  visit_at DATETIME NOT NULL,
  purpose VARCHAR(255) NOT NULL,
  status ENUM('Pending','Approved','Completed','Cancelled','Denied') NOT NULL DEFAULT 'Pending',
  remarks TEXT NULL,
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_visit_date (visit_at),
  INDEX idx_visit_status (status),
  CONSTRAINT fk_visit_visitor FOREIGN KEY (visitor_id) REFERENCES visitors(id),
  CONSTRAINT fk_visit_inmate FOREIGN KEY (inmate_id) REFERENCES inmates(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_no VARCHAR(40) NOT NULL UNIQUE,
  inmate_id BIGINT UNSIGNED NOT NULL,
  incident_type VARCHAR(120) NOT NULL,
  incident_date DATETIME NOT NULL,
  description TEXT NOT NULL,
  action_taken TEXT NULL,
  status ENUM('Open','Under Review','Resolved','Closed') NOT NULL DEFAULT 'Open',
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_incident_date (incident_date),
  INDEX idx_incident_status (status),
  CONSTRAINT fk_incident_inmate FOREIGN KEY (inmate_id) REFERENCES inmates(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_no VARCHAR(40) NOT NULL UNIQUE,
  inmate_id BIGINT UNSIGNED NOT NULL,
  case_title VARCHAR(255) NOT NULL,
  court VARCHAR(200) NULL,
  offense VARCHAR(255) NOT NULL,
  filing_date DATE NULL,
  status ENUM('Pending','Active','On Trial','Decided','Dismissed','Archived') NOT NULL DEFAULT 'Pending',
  next_hearing_date DATE NULL,
  remarks TEXT NULL,
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_case_inmate (inmate_id), INDEX idx_case_status (status), INDEX idx_case_hearing (next_hearing_date),
  CONSTRAINT fk_case_inmate FOREIGN KEY (inmate_id) REFERENCES inmates(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS custody_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_no VARCHAR(40) NOT NULL UNIQUE,
  inmate_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('Admission','Housing Assignment','Transfer','Release','Status Update') NOT NULL,
  event_at DATETIME NOT NULL,
  from_unit VARCHAR(100) NULL, to_unit VARCHAR(100) NULL,
  reason VARCHAR(255) NOT NULL, authorized_by BIGINT UNSIGNED NULL, remarks TEXT NULL,
  archived_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_custody_inmate (inmate_id,event_at), INDEX idx_custody_type (event_type),
  CONSTRAINT fk_custody_inmate FOREIGN KEY (inmate_id) REFERENCES inmates(id),
  CONSTRAINT fk_custody_personnel FOREIGN KEY (authorized_by) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_no VARCHAR(40) NOT NULL UNIQUE,
  document_type VARCHAR(120) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  classification ENUM('Public','Internal','Confidential','Restricted') NOT NULL DEFAULT 'Internal',
  document_date DATE NOT NULL,
  remarks TEXT NULL,
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_document_date (document_date),
  INDEX idx_document_class (classification)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS routing_sheets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  routing_no VARCHAR(40) NOT NULL UNIQUE,
  document_id BIGINT UNSIGNED NOT NULL,
  origin_unit VARCHAR(150) NOT NULL,
  destination_unit VARCHAR(150) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  status ENUM('Draft','In Transit','Received','Completed') NOT NULL DEFAULT 'Draft',
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_routing_status (status),
  CONSTRAINT fk_routing_document FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','records_officer','viewer') NOT NULL DEFAULT 'viewer',
  unit VARCHAR(100) NOT NULL DEFAULT 'Admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Capstone demo administrator. Change or remove this account before real deployment.
INSERT IGNORE INTO users (username, full_name, password_hash, role, unit, is_active)
VALUES ('adminofficer@gmail.com', 'System Administrator', CONVERT(UNHEX('2432792431302430304476563545554E634A616678766637346F674665444333664B30304B5A474B49564E4C306D49344D356C766833576C4944586D') USING utf8mb4), 'admin', 'Admin', 1);

CREATE TABLE IF NOT EXISTS routing_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  routing_sheet_id BIGINT UNSIGNED NOT NULL,
  action ENUM('Created','Forwarded','Received','Completed','Note') NOT NULL,
  from_unit VARCHAR(150) NULL,
  to_unit VARCHAR(150) NULL,
  note TEXT NULL,
  actor_id BIGINT UNSIGNED NULL,
  acted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_routing_history_sheet FOREIGN KEY (routing_sheet_id) REFERENCES routing_sheets(id),
  CONSTRAINT fk_routing_history_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_routing_history_sheet (routing_sheet_id, acted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(30) NOT NULL,
  module VARCHAR(40) NOT NULL,
  record_id BIGINT UNSIGNED NULL,
  message VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_log_created (created_at),
  INDEX idx_log_module (module, record_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  employee_no VARCHAR(60) NOT NULL,
  department VARCHAR(150) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  requested_role ENUM('records_officer','viewer') NOT NULL DEFAULT 'viewer',
  justification VARCHAR(1000) NOT NULL,
  desired_username VARCHAR(60) NULL,
  status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_note VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_request_status (status, created_at),
  CONSTRAINT fk_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

