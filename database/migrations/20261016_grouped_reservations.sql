ALTER TABLE bookings
    ADD COLUMN booking_group_id INT(11) NULL,
    ADD KEY idx_bookings_group (booking_group_id);
