EXPLAIN FORMAT=JSON
SELECT parameter_id, value, `timestamp`, alert_flag
FROM patient_data
WHERE id_patient = 8
  AND archived = 0
  AND `timestamp` > '2026-03-30 01:00:00'
ORDER BY `timestamp` ASC;
