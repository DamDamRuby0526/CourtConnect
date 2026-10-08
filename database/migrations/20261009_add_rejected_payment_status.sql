ALTER TABLE payments
    MODIFY COLUMN payment_status ENUM('Pending', 'Rejected', 'Paid') NOT NULL;