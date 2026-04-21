-- Phase 3 hotfix (additive only)
-- Refreshes patient_data_latest payload columns from immutable history
-- in small patient-level chunks to avoid lock-table overflow.

DROP PROCEDURE IF EXISTS `sp_refresh_patient_data_latest_chunked`;

DELIMITER $$

CREATE PROCEDURE `sp_refresh_patient_data_latest_chunked`()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_patient_id INT UNSIGNED;

    DECLARE patient_cursor CURSOR FOR
        SELECT DISTINCT `id_patient`
        FROM `patient_data`
        ORDER BY `id_patient`;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN patient_cursor;

    patient_loop: LOOP
        FETCH patient_cursor INTO v_patient_id;
        IF done = 1 THEN
            LEAVE patient_loop;
        END IF;

        INSERT INTO `patient_data_latest` (
            `id_patient`,
            `parameter_id`,
            `seq`,
            `value`,
            `timestamp`,
            `alert_flag`,
            `archived`,
            `updated_at`
        )
        SELECT
            pd.`id_patient`,
            pd.`parameter_id`,
            pd.`seq`,
            pd.`value`,
            pd.`timestamp`,
            pd.`alert_flag`,
            pd.`archived`,
            CURRENT_TIMESTAMP
        FROM `patient_data` pd
        INNER JOIN (
            SELECT `parameter_id`, MAX(`seq`) AS max_seq
            FROM `patient_data`
            WHERE `id_patient` = v_patient_id
              AND `archived` = 0
            GROUP BY `parameter_id`
        ) latest
            ON latest.`parameter_id` = pd.`parameter_id`
           AND latest.`max_seq` = pd.`seq`
        WHERE pd.`id_patient` = v_patient_id
        ON DUPLICATE KEY UPDATE
            `seq` = VALUES(`seq`),
            `value` = VALUES(`value`),
            `timestamp` = VALUES(`timestamp`),
            `alert_flag` = VALUES(`alert_flag`),
            `archived` = VALUES(`archived`),
            `updated_at` = CURRENT_TIMESTAMP;
    END LOOP;

    CLOSE patient_cursor;
END$$

DELIMITER ;

CALL `sp_refresh_patient_data_latest_chunked`();
DROP PROCEDURE `sp_refresh_patient_data_latest_chunked`;
