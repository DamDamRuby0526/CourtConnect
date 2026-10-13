ALTER TABLE payments
    ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER receipt_image;
