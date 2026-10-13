UPDATE bookings b
INNER JOIN users u ON u.user_id = b.user_id
SET b.customer_name = COALESCE(
        NULLIF(b.customer_name, ''),
        CONCAT_WS(' ', NULLIF(u.first_name, ''), NULLIF(u.last_name, ''))
    ),
    b.customer_phone = COALESCE(NULLIF(b.customer_phone, ''), u.phone_number)
WHERE b.booking_type = 'online'
    AND (b.customer_name IS NULL OR b.customer_name = ''
        OR b.customer_phone IS NULL OR b.customer_phone = '');