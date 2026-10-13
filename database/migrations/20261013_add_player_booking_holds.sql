ALTER TABLE bookings
    MODIFY status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'pending_payment') NOT NULL DEFAULT 'confirmed';

ALTER TABLE court_schedule
    MODIFY schedule_status ENUM('Available', 'Held', 'Booked') NOT NULL DEFAULT 'Available',
    ADD COLUMN hold_expires_at DATETIME NULL AFTER schedule_status;
