-- Biglang Gwapo Barbershop complete database schema.
-- Fresh install:
--   mysql -u root -p < schema.sql
-- Then create the first administrator:
--   php seed_admin.php admin "a-long-password" "Administrator"

CREATE DATABASE IF NOT EXISTS barbershop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE barbershop;

-- Branches must exist before staff and inventory can be assigned to them.
CREATE TABLE branches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  address VARCHAR(200) NOT NULL,
  phone VARCHAR(30) NULL,
  hours VARCHAR(120) NOT NULL DEFAULT 'Daily 9:00 AM - 6:00 PM',
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('global_admin', 'branch_manager') NOT NULL DEFAULT 'global_admin',
  branch_id INT UNSIGNED NULL,
  KEY idx_admin_branch (branch_id),
  CONSTRAINT fk_admin_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  resource VARCHAR(40) NOT NULL,
  record_id INT UNSIGNED NULL,
  details JSON NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_created (created_at),
  KEY idx_audit_resource (resource, action),
  CONSTRAINT fk_audit_admin
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE barbers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  chair TINYINT UNSIGNED NOT NULL,
  title VARCHAR(60) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_branch_chair (branch_id, chair),
  CONSTRAINT fk_barber_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(1000) NULL,
  duration_min SMALLINT UNSIGNED NOT NULL,
  price DECIMAL(8,2) NOT NULL,
  category VARCHAR(40) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT chk_service_duration CHECK (duration_min BETWEEN 15 AND 240),
  CONSTRAINT chk_service_price CHECK (price >= 0)
) ENGINE=InnoDB;

CREATE TABLE appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(120) NOT NULL,
  customer_email VARCHAR(150) NULL,
  customer_phone VARCHAR(30) NULL,
  service_id INT UNSIGNED NOT NULL,
  barber_id INT UNSIGNED NOT NULL,
  appt_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  notes VARCHAR(1000) NULL,
  booking_ref CHAR(8) NULL,
  booking_token CHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_appointment_slot (barber_id, appt_date, start_time),
  KEY idx_appointment_day (appt_date, status),
  KEY idx_appointment_ref (booking_ref),
  KEY idx_appointment_token (booking_token),
  CONSTRAINT chk_appointment_times CHECK (end_time > start_time),
  CONSTRAINT fk_appointment_service
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  CONSTRAINT fk_appointment_barber
    FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE barber_unavailability (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barber_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  reason VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_unavailable_barber_dates (barber_id, start_date, end_date),
  CONSTRAINT fk_unavailable_barber
    FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE CASCADE,
  CONSTRAINT fk_unavailable_admin
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
  CONSTRAINT chk_unavailable_dates CHECK (end_date >= start_date),
  CONSTRAINT chk_unavailable_times CHECK (
    (start_time IS NULL AND end_time IS NULL)
    OR (start_time IS NOT NULL AND end_time IS NOT NULL AND end_time > start_time)
  )
) ENGINE=InnoDB;

CREATE TABLE inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id INT UNSIGNED NOT NULL,
  sku VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(40) NOT NULL,
  price DECIMAL(8,2) NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  min_threshold INT UNSIGNED NOT NULL DEFAULT 5,
  KEY idx_inventory_branch (branch_id),
  CONSTRAINT chk_inventory_price CHECK (price >= 0),
  CONSTRAINT fk_inventory_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE stock_ledger (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventory_id INT UNSIGNED NOT NULL,
  type ENUM('receive', 'waste', 'correction', 'transfer') NOT NULL,
  qty_change INT NOT NULL,
  batch_ref VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_stock_ledger_inventory (inventory_id, created_at),
  CONSTRAINT fk_ledger_inventory
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reorder_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventory_id INT UNSIGNED NOT NULL,
  requested_qty INT UNSIGNED NOT NULL,
  status ENUM('open', 'ordered', 'received', 'cancelled') NOT NULL DEFAULT 'open',
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_reorder_status (status, created_at),
  CONSTRAINT fk_reorder_inventory
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
  CONSTRAINT fk_reorder_admin
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Catalog data from phpMyAdmin screenshots
INSERT INTO branches (name, address, phone, hours, active) VALUES
  ('Bocaue, Bulacan', '428 Igulot, Bocaue, Bulacan', '(+63) 960-564-2554', 'Daily 9:00 AM - 6:00 PM', 1),
  ('Balagtas, Bulacan', '012 Wakas, Balagtas, Bulacan', '(+63) 961-336-3143', 'Daily 9:00 AM - 6:00 PM', 1),
  ('Marilao, Bulacan', '088 Ibayo, Marilao, Bulacan', '(+63) 973-531-8469', 'Daily 9:00 AM - 6:00 PM', 1);

INSERT INTO barbers (name, branch_id, chair, title, active) VALUES
  ('Jun Reyes', 1, 1, 'Master Barber', 1),
  ('Rico Bautista', 1, 2, 'Senior Stylist', 1),
  ('Nathan Flores', 2, 1, 'Barber Craft', 1),
  ('Marco Navarro', 3, 1, 'Texture Specialist', 1);

INSERT INTO services (name, description, duration_min, price, category, active) VALUES
  ('Royal Signature Cut', 'Consultation, precision cut and finish.', 45, 350.00, 'Haircut', 1),
  ('Hot Towel Razor Shave', 'Straight-razor shave with hot towel and balm.', 30, 299.00, 'Beard & Shave', 1),
  ('Beard Architecture', 'Sculpted beard trim with conditioning oil.', 30, 240.00, 'Beard & Shave', 1),
  ('Precision Scissor Styling', 'Scissor-only cut with wash and style.', 60, 350.00, 'Haircut', 1),
  ('Atelier Restorative Facial', 'Steam, exfoliation and mask treatment.', 45, 450.00, 'Facial & Care', 1),
  ('Father & Son Heritage Session', 'Two cuts back-to-back with tonic finish.', 90, 650.00, 'Packages', 1);

INSERT INTO inventory (branch_id, sku, name, category, price, stock, min_threshold) VALUES
  (1, 'SKU-AP-0104', 'Sovereign Matte Clay Pomade (100ml)', 'Styling', 220.00, 3, 12),
  (1, 'SKU-AP-0107', 'Sandalwood Shave Cream', 'Shave', 170.00, 2, 8),
  (1, 'SKU-AP-0110', 'Japanese Carbon Steel Blades (100pk)', 'Tools', 150.00, 41, 10),
  (1, 'SKU-AP-0112', 'Cedar Beard Oil (30ml)', 'Beard', 250.00, 24, 10);

INSERT INTO appointments
  (customer_name, customer_email, service_id, barber_id, appt_date, start_time, end_time, status)
VALUES
  ('Samuel Zhang', 's.zhang@example.com', 1, 3, CURDATE(), '09:30:00', '10:15:00', 'completed'),
  ('Arthur Pendelton', 'arthur@example.com', 1, 1, CURDATE(), '09:00:00', '09:45:00', 'completed'),
  ('Gregory Moss', 'g.moss@example.com', 3, 3, CURDATE(), '11:15:00', '11:45:00', 'confirmed'),
  ('B. Vance', 'b.vance@example.com', 2, 2, CURDATE(), '12:30:00', '13:00:00', 'pending'),
  ('Liam Ward', 'l.ward@example.com', 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', '10:45:00', 'pending');