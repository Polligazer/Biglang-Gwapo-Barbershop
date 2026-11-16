-- Run once on an existing barbershop database.
USE barbershop;

CREATE TABLE reorder_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventory_id INT UNSIGNED NOT NULL,
  requested_qty INT UNSIGNED NOT NULL,
  status ENUM('open','ordered','received','cancelled') NOT NULL DEFAULT 'open',
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_reorder_status (status, created_at),
  CONSTRAINT fk_reorder_inventory FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
  CONSTRAINT fk_reorder_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
