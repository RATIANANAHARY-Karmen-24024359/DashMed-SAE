SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ;
START TRANSACTION WITH CONSISTENT SNAPSHOT;

SELECT 'snapshot_rows' AS metric, COUNT(*) AS value
FROM patient_data_latest;

SELECT 'active_series_rows' AS metric, COUNT(*) AS value
FROM (
    SELECT id_patient, parameter_id
    FROM patient_data
    WHERE archived = 0
    GROUP BY id_patient, parameter_id
) s;

SELECT 'mismatch_latest_seq' AS metric, COUNT(*) AS value
FROM (
    SELECT l.id_patient, l.parameter_id
    FROM patient_data_latest l
    INNER JOIN (
        SELECT id_patient, parameter_id, MAX(seq) AS max_seq
        FROM patient_data
        WHERE archived = 0
        GROUP BY id_patient, parameter_id
    ) h
        ON h.id_patient = l.id_patient
       AND h.parameter_id = l.parameter_id
    WHERE l.seq <> h.max_seq
) d;

SELECT 'snapshot_missing_source_seq' AS metric, COUNT(*) AS value
FROM patient_data_latest l
LEFT JOIN patient_data pd ON pd.seq = l.seq
WHERE pd.seq IS NULL;

SELECT 'source_missing_snapshot' AS metric, COUNT(*) AS value
FROM (
    SELECT h.id_patient, h.parameter_id
    FROM (
        SELECT id_patient, parameter_id, MAX(seq) AS max_seq
        FROM patient_data
        WHERE archived = 0
        GROUP BY id_patient, parameter_id
    ) h
    LEFT JOIN patient_data_latest l
        ON l.id_patient = h.id_patient
       AND l.parameter_id = h.parameter_id
    WHERE l.id_patient IS NULL
) z;

COMMIT;
