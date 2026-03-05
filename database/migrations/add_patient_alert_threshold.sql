-- =============================================
-- Table patient_alert_threshold
-- Seuils d'alerte personnalisés par patient
-- =============================================
-- Cette table permet de surcharger les seuils globaux
-- de parameter_reference pour un patient spécifique.
-- Si un seuil est NULL ici, le seuil global s'applique.

CREATE TABLE IF NOT EXISTS `patient_alert_threshold` (
    `id_patient`    INT UNSIGNED NOT NULL,
    `parameter_id`  VARCHAR(50) NOT NULL,
    `normal_min`    DECIMAL(15,2) DEFAULT NULL COMMENT 'Seuil min normal personnalisé',
    `normal_max`    DECIMAL(15,2) DEFAULT NULL COMMENT 'Seuil max normal personnalisé',
    `critical_min`  DECIMAL(15,2) DEFAULT NULL COMMENT 'Seuil min critique personnalisé',
    `critical_max`  DECIMAL(15,2) DEFAULT NULL COMMENT 'Seuil max critique personnalisé',
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`    INT UNSIGNED DEFAULT NULL COMMENT 'Utilisateur ayant modifié les seuils',

    PRIMARY KEY (`id_patient`, `parameter_id`),

    CONSTRAINT `fk_pat_threshold_patient`
        FOREIGN KEY (`id_patient`) REFERENCES `patients` (`id_patient`)
            ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_pat_threshold_parameter`
        FOREIGN KEY (`parameter_id`) REFERENCES `parameter_reference` (`parameter_id`)
            ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_pat_threshold_user`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id_user`)
            ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
