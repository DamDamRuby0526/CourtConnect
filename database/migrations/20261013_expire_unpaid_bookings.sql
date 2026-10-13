ALTER TABLE bookings
    MODIFY status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'pending_payment', 'Expired') NOT NULL DEFAULT 'confirmed',
    ADD COLUMN hold_expires_at DATETIME NULL AFTER created_at;

UPDATE bookings
SET hold_expires_at = DATE_ADD(created_at, INTERVAL 10 MINUTE)
WHERE status = 'pending_payment' AND hold_expires_at IS NULL;

CREATE TABLE IF NOT EXISTS booking_expiry_notifications (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    court_no INT(11) NOT NULL,
    court_date DATE NOT NULL,
    court_time TIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    consumed_at DATETIME NULL,
    PRIMARY KEY (notification_id),
    UNIQUE KEY booking_expiry_notifications_booking_id (booking_id),
    KEY booking_expiry_notifications_user_pending (user_id, consumed_at, notification_id),
    CONSTRAINT booking_expiry_notifications_user_fk
        FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
