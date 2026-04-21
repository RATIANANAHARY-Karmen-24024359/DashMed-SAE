-- Phase 3 hotfix (additive only)
-- Fixes ON DUPLICATE assignment order so snapshot payload columns
-- (`value`, `timestamp`, `alert_flag`) update correctly when seq increases.
-- Snapshot payload refresh is handled by 2026-03-30_05_refresh_patient_data_latest_chunked.sql
-- to avoid lock-table pressure on very large datasets.

DROP TRIGGER IF EXISTS `trg_patient_data_latest_after_insert`;

DELIMITER $$

CREATE TRIGGER `trg_patient_data_latest_after_insert`
    AFTER INSERT ON `patient_data`
    FOR EACH ROW
BEGIN
    IF NEW.archived = 0 THEN
        INSERT INTO `patient_data_latest` (
            `id_patient`, `parameter_id`, `seq`, `value`, `timestamp`, `alert_flag`, `archived`, `updated_at`
        ) VALUES (
            NEW.id_patient, NEW.parameter_id, NEW.seq, NEW.value, NEW.timestamp, NEW.alert_flag, NEW.archived, CURRENT_TIMESTAMP
        )
        ON DUPLICATE KEY UPDATE
            `value` = IF(VALUES(`seq`) > `patient_data_latest`.`seq`, VALUES(`value`), `patient_data_latest`.`value`),
            `timestamp` = IF(VALUES(`seq`) > `patient_data_latest`.`seq`, VALUES(`timestamp`), `patient_data_latest`.`timestamp`),
            `alert_flag` = IF(VALUES(`seq`) > `patient_data_latest`.`seq`, VALUES(`alert_flag`), `patient_data_latest`.`alert_flag`),
            `archived` = IF(VALUES(`seq`) > `patient_data_latest`.`seq`, VALUES(`archived`), `patient_data_latest`.`archived`),
            `updated_at` = IF(VALUES(`seq`) > `patient_data_latest`.`seq`, CURRENT_TIMESTAMP, `patient_data_latest`.`updated_at`),
            `seq` = IF(VALUES(`seq`) > `patient_data_latest`.`seq`, VALUES(`seq`), `patient_data_latest`.`seq`);
    END IF;
END$$

DELIMITER ;
