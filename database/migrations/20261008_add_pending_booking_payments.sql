ALTER TABLE payments
    MODIFY COLUMN reference_number VARCHAR(50) NULL DEFAULT NULL,
    MODIFY COLUMN paid_at TIMESTAMP NULL DEFAULT NULL;

INSERT INTO payments (booking_id, payment_method, payment_status, reference_number, paid_at)
SELECT b.booking_id, 'Gcash', 'Pending', NULL, NULL
FROM bookings b
WHERE NOT EXISTS (
    SELECT 1
    FROM payments p
    WHERE p.booking_id = b.booking_id
);