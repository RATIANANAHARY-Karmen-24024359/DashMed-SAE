<?php

declare(strict_types=1);

namespace modules\models\repositories;

use PDO;
use PDOException;

/**
 * Repository for managing per-patient alert thresholds.
 *
 * Handles CRUD for the patient_alert_threshold table.
 * When a patient has no custom threshold, the global parameter_reference values apply.
 *
 * @package DashMed\Modules\Models\Repositories
 * @author DashMed Team
 * @license Proprietary
 */
class AlertThresholdRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieves all parameters with their effective thresholds for a given patient.
     *
     * Returns the custom threshold if set, otherwise the global default.
     *
     * @param int $patientId
     * @return array<int, array<string, mixed>>
     */
    public function getThresholdsForPatient(int $patientId): array
    {
        try {
            $sql = "
                SELECT
                    pr.parameter_id,
                    pr.display_name,
                    pr.category,
                    pr.unit,
                    pr.normal_min   AS default_normal_min,
                    pr.normal_max   AS default_normal_max,
                    pr.critical_min AS default_critical_min,
                    pr.critical_max AS default_critical_max,
                    pat.normal_min   AS custom_normal_min,
                    pat.normal_max   AS custom_normal_max,
                    pat.critical_min AS custom_critical_min,
                    pat.critical_max AS custom_critical_max,
                    pat.updated_at   AS threshold_updated_at,
                    COALESCE(pat.normal_min,   pr.normal_min)   AS effective_normal_min,
                    COALESCE(pat.normal_max,   pr.normal_max)   AS effective_normal_max,
                    COALESCE(pat.critical_min, pr.critical_min) AS effective_critical_min,
                    COALESCE(pat.critical_max, pr.critical_max) AS effective_critical_max
                FROM parameter_reference pr
                LEFT JOIN patient_alert_threshold pat
                    ON pat.parameter_id = pr.parameter_id
                    AND pat.id_patient = :patient_id
                ORDER BY pr.category, pr.display_name
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':patient_id' => $patientId]);
            /** @var array<int, array<string, mixed>> */
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[AlertThresholdRepository] getThresholdsForPatient: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Saves (upserts) custom thresholds for a patient on a specific parameter.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert.
     *
     * @param int $patientId
     * @param string $parameterId
     * @param float|null $normalMin
     * @param float|null $normalMax
     * @param float|null $criticalMin
     * @param float|null $criticalMax
     * @param int|null $updatedBy User ID of the user making the change
     * @return bool
     */
    public function saveThreshold(
        int $patientId,
        string $parameterId,
        ?float $normalMin,
        ?float $normalMax,
        ?float $criticalMin,
        ?float $criticalMax,
        ?int $updatedBy = null
    ): bool {
        try {
            $sql = "
                INSERT INTO patient_alert_threshold
                    (id_patient, parameter_id, normal_min, normal_max, critical_min, critical_max, updated_by)
                VALUES
                    (:patient_id, :parameter_id, :normal_min, :normal_max, :critical_min, :critical_max, :updated_by)
                ON DUPLICATE KEY UPDATE
                    normal_min   = VALUES(normal_min),
                    normal_max   = VALUES(normal_max),
                    critical_min = VALUES(critical_min),
                    critical_max = VALUES(critical_max),
                    updated_by   = VALUES(updated_by)
            ";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':patient_id'   => $patientId,
                ':parameter_id' => $parameterId,
                ':normal_min'   => $normalMin,
                ':normal_max'   => $normalMax,
                ':critical_min' => $criticalMin,
                ':critical_max' => $criticalMax,
                ':updated_by'   => $updatedBy,
            ]);
        } catch (PDOException $e) {
            error_log('[AlertThresholdRepository] saveThreshold: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resets custom thresholds for a patient on a specific parameter (reverts to global).
     *
     * @param int $patientId
     * @param string $parameterId
     * @return bool
     */
    public function resetThreshold(int $patientId, string $parameterId): bool
    {
        try {
            $sql = "DELETE FROM patient_alert_threshold WHERE id_patient = :pid AND parameter_id = :param";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':pid' => $patientId, ':param' => $parameterId]);
        } catch (PDOException $e) {
            error_log('[AlertThresholdRepository] resetThreshold: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resets all custom thresholds for a patient.
     *
     * @param int $patientId
     * @return bool
     */
    public function resetAllThresholds(int $patientId): bool
    {
        try {
            $sql = "DELETE FROM patient_alert_threshold WHERE id_patient = :pid";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':pid' => $patientId]);
        } catch (PDOException $e) {
            error_log('[AlertThresholdRepository] resetAllThresholds: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns the effective thresholds for a specific parameter and patient.
     * Falls back to global defaults if no custom threshold is set.
     *
     * @param int $patientId
     * @param string $parameterId
     * @return array<string, mixed>|null
     */
    public function getEffectiveThreshold(int $patientId, string $parameterId): ?array
    {
        try {
            $sql = "
                SELECT
                    COALESCE(pat.normal_min,   pr.normal_min)   AS normal_min,
                    COALESCE(pat.normal_max,   pr.normal_max)   AS normal_max,
                    COALESCE(pat.critical_min, pr.critical_min) AS critical_min,
                    COALESCE(pat.critical_max, pr.critical_max) AS critical_max
                FROM parameter_reference pr
                LEFT JOIN patient_alert_threshold pat
                    ON pat.parameter_id = pr.parameter_id
                    AND pat.id_patient = :patient_id
                WHERE pr.parameter_id = :parameter_id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':patient_id' => $patientId, ':parameter_id' => $parameterId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            /** @var array<string, mixed>|false $row */
            return is_array($row) ? $row : null;
        } catch (PDOException $e) {
            error_log('[AlertThresholdRepository] getEffectiveThreshold: ' . $e->getMessage());
            return null;
        }
    }
}
