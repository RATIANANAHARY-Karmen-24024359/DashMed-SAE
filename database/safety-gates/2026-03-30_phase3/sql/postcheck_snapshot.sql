SHOW TABLES LIKE 'patient_data_latest';

SELECT COUNT(*) AS snapshot_rows, MIN(seq) AS min_snapshot_seq, MAX(seq) AS max_snapshot_seq
FROM patient_data_latest;

SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME = 'trg_patient_data_latest_after_insert';
