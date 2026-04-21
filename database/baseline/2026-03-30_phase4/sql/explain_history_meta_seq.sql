EXPLAIN FORMAT=JSON
SELECT MAX(`timestamp`) AS max_ts, MAX(seq) AS max_seq
FROM patient_data
WHERE id_patient = 8
  AND parameter_id = 'FR_m'
  AND archived = 0;
