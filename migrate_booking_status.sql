-- Run once on databases that already contain booking_ref.
USE barbershop;

ALTER TABLE appointments
  ADD COLUMN booking_token CHAR(64) NULL AFTER booking_ref,
  ADD KEY idx_booking_token (booking_token);
