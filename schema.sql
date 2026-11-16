CREATE DATABASE IF NOT EXISTS barbershop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE barbershop;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE branches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  address VARCHAR(200) NOT NULL,
  phone VARCHAR(30) NULL,
  hours VARCHAR(120) NOT NULL DEFAULT 'Daily 9:00 AM - 6:00 PM',
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE barbers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  chair TINYINT UNSIGNED NOT NULL,
  title VARCHAR(60) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_branch_chair (branch_id, chair),
  CONSTRAINT fk_barber_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(1000) NULL,
  duration_min SMALLINT UNSIGNED NOT NULL,
  price DECIMAL(8,2) NOT NULL,
  category VARCHAR(40) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT chk_dur CHECK (duration_min BETWEEN 15 AND 240),
  CONSTRAINT chk_price CHECK (price >= 0)
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
  status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  notes VARCHAR(1000) NULL,
  booking_ref CHAR(8) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_slot (barber_id, appt_date, start_time),
  KEY idx_day (appt_date, status),
  KEY idx_ref (booking_ref),
  CONSTRAINT chk_times CHECK (end_time > start_time),
  CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  CONSTRAINT fk_appt_barber FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(40) NOT NULL,
  price DECIMAL(8,2) NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  min_threshold INT UNSIGNED NOT NULL DEFAULT 5
) ENGINE=InnoDB;

CREATE TABLE stock_ledger (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventory_id INT UNSIGNED NOT NULL,
  type ENUM('receive','waste','correction','transfer') NOT NULL,
  qty_change INT NOT NULL,
  batch_ref VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ledger_inv FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample data (admin user is created with: php seed_admin.php <user> <password> "<Name>")
-- Sample branches: replace the addresses with your real ones.
INSERT INTO branches (name, address, phone) VALUES
 ('Boston Back Bay','428 Commonwealth Ave, Boston, MA','(617) 555-0142'),
 ('Cambridge Harvard Sq','12 Brattle St, Cambridge, MA','(617) 555-0177'),
 ('Seaport District','88 Seaport Blvd, Boston, MA','(617) 555-0199');

INSERT INTO barbers (name, branch_id, chair, title) VALUES
 ('Marcus Volkov',1,1,'Master Barber'),('Julian Kessler',1,2,'Senior Stylist'),
 ('Derrick Park',2,1,'Barber Craft'),('Elena Rossi',3,1,'Texture Specialist');

INSERT INTO services (name, description, duration_min, price, category) VALUES
 ('Royal Signature Cut','Consultation, precision cut and finish.',45,55.00,'Haircut'),
 ('Hot Towel Razor Shave','Straight-razor shave with hot towel and balm.',30,48.00,'Beard & Shave'),
 ('Beard Architecture','Sculpted beard trim with conditioning oil.',30,40.00,'Beard & Shave'),
 ('Precision Scissor Styling','Scissor-only cut with wash and style.',60,75.00,'Haircut'),
 ('Atelier Restorative Facial','Steam, exfoliation and mask treatment.',45,65.00,'Facial & Care'),
 ('Father & Son Heritage Session','Two cuts back-to-back with tonic finish.',90,110.00,'Packages');

INSERT INTO inventory (sku, name, category, price, stock, min_threshold) VALUES
 ('SKU-AP-0104','Sovereign Matte Clay Pomade (100ml)','Styling',28.00,3,12),
 ('SKU-AP-0107','Sandalwood Shave Cream','Shave',22.00,2,8),
 ('SKU-AP-0110','Japanese Carbon Steel Blades (100pk)','Tools',22.00,41,10),
 ('SKU-AP-0112','Cedar Beard Oil (30ml)','Beard',26.00,24,10);

INSERT INTO appointments (customer_name, customer_email, service_id, barber_id, appt_date, start_time, end_time, status) VALUES
 ('Samuel Zhang','s.zhang@example.com',1,3,CURDATE(),'09:30:00','10:15:00','completed'),
 ('Arthur Pendelton','arthur@example.com',1,1,CURDATE(),'09:00:00','09:45:00','completed'),
 ('Gregory Moss','g.moss@example.com',3,3,CURDATE(),'11:15:00','11:45:00','confirmed'),
 ('David Mercer','d.mercer@example.com',4,4,CURDATE(),'12:00:00','13:00:00','confirmed'),
 ('B. Vance','b.vance@example.com',2,2,CURDATE(),'12:30:00','13:00:00','pending'),
 ('Liam Ward','l.ward@example.com',1,1,DATE_ADD(CURDATE(), INTERVAL 1 DAY),'10:00:00','10:45:00','pending');

INSERT INTO admins (username, name, password_hash)
VALUES (
  'admin',
  'Administrator',
  'REPLACE_WITH_BCRYPT_HASH'
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password_hash = VALUES(password_hash);