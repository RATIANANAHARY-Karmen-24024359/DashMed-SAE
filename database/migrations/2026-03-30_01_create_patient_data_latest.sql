-- Phase 3 (additive only)
-- Create snapshot table to serve latest-by-parameter reads without scanning historical patient_data.
-- Safe to run with current schema (requires patient_data.seq unique or primary key).

CREATE TABLE IF NOT EXISTS `patient_data_latest` (
    `id_patient` INT UNSIGNED NOT NULL,
    `parameter_id` VARCHAR(50) NOT NULL,
    `seq` BIGINT UNSIGNED NOT NULL,
    `value` DECIMAL(15,2) DEFAULT NULL,
    `timestamp` DATETIME NOT NULL,
    `alert_flag` TINYINT(1) DEFAULT 0,
    `archived` TINYINT(1) DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id_patient`, `parameter_id`),
    UNIQUE KEY `uq_patient_data_latest_seq` (`seq`),
    INDEX `ix_patient_data_latest_patient_seq` (`id_patient`, `seq`),

    CONSTRAINT `fk_patient_data_latest_patient`
        FOREIGN KEY (`id_patient`) REFERENCES `patients` (`id_patient`)
            ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_patient_data_latest_parameter`
        FOREIGN KEY (`parameter_id`) REFERENCES `parameter_reference` (`parameter_id`)
            ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_patient_data_latest_seq`
        FOREIGN KEY (`seq`) REFERENCES `patient_data` (`seq`)
            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
