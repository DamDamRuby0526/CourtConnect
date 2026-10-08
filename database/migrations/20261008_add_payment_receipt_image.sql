SET @receipt_image_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'receipt_image'
);
SET @receipt_image_ddl = IF(
    @receipt_image_exists = 0,
    'ALTER TABLE payments ADD COLUMN receipt_image VARCHAR(255) NULL AFTER reference_number',
    'SELECT 1'
);
PREPARE receipt_image_stmt FROM @receipt_image_ddl;
EXECUTE receipt_image_stmt;
DEALLOCATE PREPARE receipt_image_stmt;