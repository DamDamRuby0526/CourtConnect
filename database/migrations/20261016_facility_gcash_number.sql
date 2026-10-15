ALTER TABLE facilities
    ADD COLUMN IF NOT EXISTS gcash_number VARCHAR(20) NULL AFTER qr_img;
