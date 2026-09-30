SET @court_img_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'court_details'
      AND COLUMN_NAME = 'court_img'
);
SET @court_img_ddl = IF(
    @court_img_exists = 0,
    'ALTER TABLE court_details ADD COLUMN court_img VARCHAR(255) NULL AFTER description',
    'SELECT 1'
);
PREPARE court_img_stmt FROM @court_img_ddl;
EXECUTE court_img_stmt;
DEALLOCATE PREPARE court_img_stmt;