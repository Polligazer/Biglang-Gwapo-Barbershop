-- Run once on existing databases.
USE barbershop;

ALTER TABLE admins
  ADD COLUMN role ENUM('global_admin', 'branch_manager') NOT NULL DEFAULT 'global_admin' AFTER password_hash,
  ADD COLUMN branch_id INT UNSIGNED NULL AFTER role,
  ADD KEY idx_admin_branch (branch_id),
  ADD CONSTRAINT fk_admin_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL;

ALTER TABLE inventory
  ADD COLUMN branch_id INT UNSIGNED NULL AFTER id,
  ADD KEY idx_inventory_branch (branch_id),
  ADD CONSTRAINT fk_inventory_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT;

UPDATE inventory SET branch_id = (SELECT id FROM branches ORDER BY id LIMIT 1) WHERE branch_id IS NULL;
ALTER TABLE inventory MODIFY branch_id INT UNSIGNED NOT NULL;

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
  CONSTRAINT fk_unavailable_barber FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE CASCADE,
  CONSTRAINT fk_unavailable_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
  CONSTRAINT chk_unavailable_dates CHECK (end_date >= start_date),
  CONSTRAINT chk_unavailable_times CHECK (
    (start_time IS NULL AND end_time IS NULL) OR
    (start_time IS NOT NULL AND end_time IS NOT NULL AND end_time > start_time)
  )
) ENGINE=InnoDB;
