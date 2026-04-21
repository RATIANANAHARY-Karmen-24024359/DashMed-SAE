SELECT
    h.parameter_id,
    h.max_seq AS history_max_seq,
    l.seq AS snapshot_seq,
    (l.seq - h.max_seq) AS seq_delta
FROM (
    SELECT parameter_id, MAX(seq) AS max_seq
    FROM patient_data
    WHERE id_patient = 8 AND archived = 0
    GROUP BY parameter_id
) h
LEFT JOIN patient_data_latest l
    ON l.id_patient = 8
   AND l.parameter_id = h.parameter_id
ORDER BY h.max_seq DESC
LIMIT 15;
