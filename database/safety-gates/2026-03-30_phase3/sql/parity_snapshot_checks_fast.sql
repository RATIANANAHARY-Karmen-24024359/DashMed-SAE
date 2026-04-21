SET @cutoff_seq := (SELECT MAX(seq) FROM patient_data);

DROP TEMPORARY TABLE IF EXISTS tmp_latest_history;

CREATE TEMPORARY TABLE tmp_latest_history AS
SELECT id_patient, parameter_id, MAX(seq) AS max_seq
FROM patient_data
WHERE archived = 0
  AND seq <= @cutoff_seq
GROUP BY id_patient, parameter_id;

SELECT 'cutoff_seq' AS metric, @cutoff_seq AS value;

SELECT 'snapshot_rows' AS metric, COUNT(*) AS value
FROM patient_data_latest;

SELECT 'history_series_at_cutoff' AS metric, COUNT(*) AS value
FROM tmp_latest_history;

SELECT 'missing_snapshot_rows' AS metric, COUNT(*) AS value
FROM tmp_latest_history h
LEFT JOIN patient_data_latest l
    ON l.id_patient = h.id_patient
   AND l.parameter_id = h.parameter_id
WHERE l.id_patient IS NULL;

SELECT 'snapshot_behind_history' AS metric, COUNT(*) AS value
FROM tmp_latest_history h
INNER JOIN patient_data_latest l
    ON l.id_patient = h.id_patient
   AND l.parameter_id = h.parameter_id
WHERE l.seq < h.max_seq;

SELECT 'snapshot_missing_source_seq' AS metric, COUNT(*) AS value
FROM patient_data_latest l
LEFT JOIN patient_data pd ON pd.seq = l.seq
WHERE pd.seq IS NULL;
