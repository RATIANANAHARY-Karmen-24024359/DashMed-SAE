DROP PROCEDURE IF EXISTS `sp_check_latest_parity_chunked`;

DELIMITER $$

CREATE PROCEDURE `sp_check_latest_parity_chunked`()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_patient_id INT UNSIGNED;

    DECLARE patient_cursor CURSOR FOR
        SELECT DISTINCT `id_patient`
        FROM `patient_data`
        ORDER BY `id_patient`;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_parity_stats;
    CREATE TEMPORARY TABLE tmp_parity_stats (
        patient_id INT UNSIGNED NOT NULL,
        series_count INT NOT NULL,
        missing_snapshot INT NOT NULL,
        behind_snapshot INT NOT NULL
    );

    OPEN patient_cursor;

    patient_loop: LOOP
        FETCH patient_cursor INTO v_patient_id;
        IF done = 1 THEN
            LEAVE patient_loop;
        END IF;

        INSERT INTO tmp_parity_stats (patient_id, series_count, missing_snapshot, behind_snapshot)
        SELECT
            v_patient_id,
            COUNT(*) AS series_count,
            SUM(CASE WHEN l.id_patient IS NULL THEN 1 ELSE 0 END) AS missing_snapshot,
            SUM(CASE WHEN l.id_patient IS NOT NULL AND l.seq < h.max_seq THEN 1 ELSE 0 END) AS behind_snapshot
        FROM (
            SELECT parameter_id, MAX(seq) AS max_seq
            FROM patient_data
            WHERE id_patient = v_patient_id
              AND archived = 0
            GROUP BY parameter_id
        ) h
        LEFT JOIN patient_data_latest l
            ON l.id_patient = v_patient_id
           AND l.parameter_id = h.parameter_id;
    END LOOP;

    CLOSE patient_cursor;

    SELECT 'snapshot_rows' AS metric, COUNT(*) AS value
    FROM patient_data_latest;

    SELECT 'patients_checked' AS metric, COUNT(*) AS value
    FROM tmp_parity_stats;

    SELECT 'series_checked' AS metric, COALESCE(SUM(series_count), 0) AS value
    FROM tmp_parity_stats;

    SELECT 'missing_snapshot_rows' AS metric, COALESCE(SUM(missing_snapshot), 0) AS value
    FROM tmp_parity_stats;

    SELECT 'snapshot_behind_history' AS metric, COALESCE(SUM(behind_snapshot), 0) AS value
    FROM tmp_parity_stats;

    SELECT 'snapshot_missing_source_seq' AS metric, COUNT(*) AS value
    FROM patient_data_latest l
    LEFT JOIN patient_data pd ON pd.seq = l.seq
    WHERE pd.seq IS NULL;
END$$

DELIMITER ;

CALL `sp_check_latest_parity_chunked`();
DROP PROCEDURE `sp_check_latest_parity_chunked`;
