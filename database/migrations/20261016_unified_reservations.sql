ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER schedule_id,
    ADD COLUMN IF NOT EXISTS end_time TIME NULL AFTER start_time,
    ADD COLUMN IF NOT EXISTS total_duration INT UNSIGNED NULL AFTER end_time;

UPDATE bookings b
INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
INNER JOIN court_details cd ON cd.court_id = b.court_id
SET b.start_time = COALESCE(b.start_time, cs.court_time),
    b.end_time = COALESCE(b.end_time, ADDTIME(cs.court_time, SEC_TO_TIME(cd.slot_duration * 60))),
    b.total_duration = COALESCE(b.total_duration, cd.slot_duration)
WHERE b.start_time IS NULL OR b.end_time IS NULL OR b.total_duration IS NULL;
