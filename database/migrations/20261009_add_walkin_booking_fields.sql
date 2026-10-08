ALTER TABLE bookings MODIFY COLUMN user_id INT(11) NULL;

SET @customer_name_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND COLUMN_NAME = 'customer_name'
);
SET @customer_name_ddl = IF(
    @customer_name_exists = 0,
    'ALTER TABLE bookings ADD COLUMN customer_name VARCHAR(200) NULL AFTER user_id',
    'SELECT 1'
);
PREPARE customer_name_stmt FROM @customer_name_ddl;
EXECUTE customer_name_stmt;
DEALLOCATE PREPARE customer_name_stmt;

SET @customer_phone_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND COLUMN_NAME = 'customer_phone'
);
SET @customer_phone_ddl = IF(
    @customer_phone_exists = 0,
    'ALTER TABLE bookings ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_name',
    'SELECT 1'
);
PREPARE customer_phone_stmt FROM @customer_phone_ddl;
EXECUTE customer_phone_stmt;
DEALLOCATE PREPARE customer_phone_stmt;

ALTER TABLE payments
    MODIFY COLUMN payment_method ENUM('Gcash', 'Cash') NOT NULL DEFAULT 'Gcash';ALTER TABLE bookings MODIFY COLUMN user_id INT(11) NULL;

SET @customer_name_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND COLUMN_NAME = 'customer_name'
);
SET @customer_name_ddl = IF(
    @customer_name_exists = 0,
    'ALTER TABLE bookings ADD COLUMN customer_name VARCHAR(200) NULL AFTER user_id',
    'SELECT 1'
);
PREPARE customer_name_stmt FROM @customer_name_ddl;
EXECUTE customer_name_stmt;
DEALLOCATE PREPARE customer_name_stmt;

SET @customer_phone_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND COLUMN_NAME = 'customer_phone'
);
SET @customer_phone_ddl = IF(
    @customer_phone_exists = 0,
    'ALTER TABLE bookings ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_name',
    'SELECT 1'
);
PREPARE customer_phone_stmt FROM @customer_phone_ddl;
EXECUTE customer_phone_stmt;
DEALLOCATE PREPARE customer_phone_stmt;

ALTER TABLE payments
    MODIFY COLUMN payment_method ENUM('Gcash', 'Cash') NOT NULL DEFAULT 'Gcash';