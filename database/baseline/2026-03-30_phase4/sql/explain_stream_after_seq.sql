EXPLAIN FORMAT=JSON
SELECT seq, parameter_id, value, `timestamp`, alert_flag
FROM patient_data
WHERE id_patient = 8
  AND archived = 0
  AND seq > 0
ORDER BY seq ASC
LIMIT 5000;
