-- Run ONCE on an existing database created from the previous schema.sql.
USE barbershop;

CREATE TABLE branches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  address VARCHAR(200) NOT NULL,
  phone VARCHAR(30) NULL,
  hours VARCHAR(120) NOT NULL DEFAULT 'Daily 9:00 AM - 6:00 PM',
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO branches (name, address, phone) VALUES
 ('Boston Back Bay','428 Commonwealth Ave, Boston, MA','(617) 555-0142'),
 ('Cambridge Harvard Sq','12 Brattle St, Cambridge, MA','(617) 555-0177'),
 ('Seaport District','88 Seaport Blvd, Boston, MA','(617) 555-0199');

-- Existing barbers go to branch 1; the sample barbers 3 and 4 are moved to branches 2 and 3.
ALTER TABLE barbers ADD COLUMN branch_id INT UNSIGNED NULL AFTER name;
UPDATE barbers SET branch_id = 1;
UPDATE barbers SET branch_id = 2 WHERE id = 3;
UPDATE barbers SET branch_id = 3 WHERE id = 4;
ALTER TABLE barbers
  MODIFY branch_id INT UNSIGNED NOT NULL,
  DROP INDEX chair,
  ADD UNIQUE KEY uq_branch_chair (branch_id, chair),
  ADD CONSTRAINT fk_barber_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT;

-- Chair numbers are now per-branch (the old index forced them to be unique across all branches).
UPDATE barbers SET chair = 1 WHERE id IN (3, 4);

ALTER TABLE appointments ADD COLUMN booking_ref CHAR(8) NULL AFTER notes, ADD KEY idx_ref (booking_ref);
