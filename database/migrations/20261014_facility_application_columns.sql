ALTER TABLE admins
    MODIFY COLUMN rejection_reason VARCHAR(500) NULL;

ALTER TABLE admins
    ADD COLUMN rejected_documents VARCHAR(255) NULL AFTER rejection_reason;
