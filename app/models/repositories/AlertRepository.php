<?php

/**
 * app/models/repositories/AlertRepository.php
 *
 * Repository file for the DashMed-SAE project.
 *
 * Notes:
 * - This docblock is intentionally file-scoped.
 * - Detailed PHPDoc for classes/methods is maintained near declarations.
 *
 * @package DashMed\SAE
 */

declare(strict_types=1);

namespace modules\models\repositories;

use PDO;
use PDOException;
use modules\models\BaseRepository;
use modules\models\entities\AlertItem;

/**
 * Repository dedicated to alert extraction from immutable time-series data.
 *
 * Read strategy:
 * - Prefer `patient_data_latest` snapshot reads for low-latency hot paths.
 * - Fall back to legacy latest-by-timestamp SQL when snapshot rows are absent
 *   or unavailable (e.g. migration overlap, test SQLite fixtures).
 */
class AlertRepository extends BaseRepository
{
    /**
     * Returns all currently out-of-threshold metrics for one patient.
     *
     * The method first queries snapshot rows, then conditionally falls back to
     * legacy history SQL if the snapshot table has not been populated for that
     * patient. On hard SQL failure, it still attempts legacy fallback before
     * returning an empty list.
     *
     * @param int $patientId Target patient identifier.
     * @return AlertItem[]
     */
    public function getOutOfThresholdAlerts(int $patientId): array
    {
        try {
            $rows = $this->fetchAlertRowsSnapshot($patientId);
            if (empty($rows) && !$this->snapshotHasRowsForPatient($patientId)) {
                $rows = $this->fetchAlertRowsLegacy($patientId);
            }
            return $this->mapRowsToAlerts($rows);
        } catch (PDOException $e) {
            error_log('[AlertRepository] snapshot query failed, fallback to legacy: ' . $e->getMessage());
            try {
                return $this->mapRowsToAlerts($this->fetchAlertRowsLegacy($patientId));
            } catch (PDOException $legacyError) {
                error_log('[AlertRepository] legacy query failed: ' . $legacyError->getMessage());
                return [];
            }
        }
    }

    /**
     * Fast existence probe indicating whether any alert should currently be shown.
     *
     * This is optimized for polling endpoints: it prefers a single-row snapshot
     * query and only touches legacy history SQL when snapshot data is not yet
     * available for the patient.
     *
     * @param int $patientId Target patient identifier.
     * @return bool True when at least one active alert exists.
     */
    public function hasAlerts(int $patientId): bool
    {
        try {
            $stmt = $this->pdo->prepare($this->getHasAlertsSqlSnapshot());
            $stmt->execute([':patient_id' => $patientId]);
            $hasAlerts = $stmt->fetchColumn() !== false;

            if ($hasAlerts) {
                return true;
            }
            if ($this->snapshotHasRowsForPatient($patientId)) {
                return false;
            }

            $legacyStmt = $this->pdo->prepare($this->getHasAlertsSqlLegacy());
            $legacyStmt->execute([
                ':patient_id_inner' => $patientId,
                ':patient_id_outer' => $patientId,
            ]);
            return $legacyStmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log('[AlertRepository] hasAlerts snapshot failed, fallback to legacy: ' . $e->getMessage());
            try {
                $legacyStmt = $this->pdo->prepare($this->getHasAlertsSqlLegacy());
                $legacyStmt->execute([
                    ':patient_id_inner' => $patientId,
                    ':patient_id_outer' => $patientId,
                ]);
                return $legacyStmt->fetchColumn() !== false;
            } catch (PDOException $legacyError) {
                error_log('[AlertRepository] hasAlerts legacy failed: ' . $legacyError->getMessage());
                return false;
            }
        }
    }

    /**
     * Maps raw SQL rows into immutable alert DTOs.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return AlertItem[]
     */
    private function mapRowsToAlerts(array $rows): array
    {
        $alerts = [];
        foreach ($rows as $row) {
            $alerts[] = AlertItem::fromRow($row);
        }
        return $alerts;
    }

    /**
     * Reads alert candidates from the snapshot table (`patient_data_latest`).
     *
     * @param int $patientId Target patient identifier.
     * @return array<int, array<string, mixed>>
     */
    private function fetchAlertRowsSnapshot(int $patientId): array
    {
        $stmt = $this->pdo->prepare($this->getAlertsSqlSnapshot());
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reads alert candidates using the legacy latest-by-timestamp strategy.
     *
     * @param int $patientId Target patient identifier.
     * @return array<int, array<string, mixed>>
     */
    private function fetchAlertRowsLegacy(int $patientId): array
    {
        $stmt = $this->pdo->prepare($this->getAlertsSqlLegacy());
        $stmt->execute([
            ':patient_id_inner' => $patientId,
            ':patient_id_outer' => $patientId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Checks whether snapshot rows exist for a patient.
     *
     * This gate avoids false negatives when snapshot migration/backfill is not
     * fully applied for the current dataset.
     *
     * @param int $patientId Target patient identifier.
     * @return bool True when at least one snapshot row is present.
     */
    private function snapshotHasRowsForPatient(int $patientId): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM patient_data_latest WHERE id_patient = :patient_id LIMIT 1');
            $stmt->execute([':patient_id' => $patientId]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Builds the snapshot SQL used by `hasAlerts()`.
     *
     * @return string SQL statement with `:patient_id` placeholder.
     */
    private function getHasAlertsSqlSnapshot(): string
    {
        return "
            SELECT 1
            FROM patient_data_latest pdl
            JOIN parameter_reference pr ON pr.parameter_id = pdl.parameter_id
            LEFT JOIN patient_alert_threshold pat
              ON pat.parameter_id = pdl.parameter_id
             AND pat.id_patient = pdl.id_patient
            WHERE pdl.id_patient = :patient_id
              AND pdl.archived = 0
              AND pdl.value IS NOT NULL
              AND (
                    pdl.alert_flag = 1
                 OR (COALESCE(pat.normal_min, pr.normal_min) IS NOT NULL AND pdl.value <= COALESCE(pat.normal_min, pr.normal_min))
                 OR (COALESCE(pat.normal_max, pr.normal_max) IS NOT NULL AND pdl.value >= COALESCE(pat.normal_max, pr.normal_max))
              )
            LIMIT 1
        ";
    }

    /**
     * Builds the legacy latest-history SQL used by `hasAlerts()` fallback.
     *
     * @return string SQL statement with `:patient_id_inner` and `:patient_id_outer`.
     */
    private function getHasAlertsSqlLegacy(): string
    {
        return "
            SELECT 1
            FROM (
                SELECT pd.parameter_id, pd.value, pd.timestamp, pd.id_patient, pd.alert_flag
                FROM patient_data pd
                INNER JOIN (
                    SELECT parameter_id, MAX(`timestamp`) AS ts
                    FROM patient_data
                    WHERE id_patient = :patient_id_inner
                      AND archived = 0
                    GROUP BY parameter_id
                ) last
                  ON last.parameter_id = pd.parameter_id
                 AND last.ts = pd.`timestamp`
                WHERE pd.id_patient = :patient_id_outer
                  AND pd.archived = 0
                  AND pd.value IS NOT NULL
            ) m
            JOIN parameter_reference r ON r.parameter_id = m.parameter_id
            LEFT JOIN patient_alert_threshold pat
              ON pat.parameter_id = m.parameter_id
             AND pat.id_patient = m.id_patient
            WHERE (
                    m.alert_flag = 1
                 OR (COALESCE(pat.normal_min, r.normal_min) IS NOT NULL AND m.value <= COALESCE(pat.normal_min, r.normal_min))
                 OR (COALESCE(pat.normal_max, r.normal_max) IS NOT NULL AND m.value >= COALESCE(pat.normal_max, r.normal_max))
            )
            LIMIT 1
        ";
    }

    /**
     * Builds snapshot-backed SQL returning complete alert rows.
     *
     * Returned columns are designed to match `AlertItem::fromRow()`.
     *
     * @return string SQL statement with `:patient_id` placeholder.
     */
    private function getAlertsSqlSnapshot(): string
    {
        return "
            SELECT pdl.parameter_id, pdl.value, pdl.timestamp, pdl.alert_flag,
                   r.display_name, r.unit,
                   COALESCE(pat.normal_min,   r.normal_min)   AS normal_min,
                   COALESCE(pat.normal_max,   r.normal_max)   AS normal_max,
                   COALESCE(pat.critical_min, r.critical_min) AS critical_min,
                   COALESCE(pat.critical_max, r.critical_max) AS critical_max
            FROM patient_data_latest pdl
            JOIN parameter_reference r ON r.parameter_id = pdl.parameter_id
            LEFT JOIN patient_alert_threshold pat
                ON pat.parameter_id = pdl.parameter_id AND pat.id_patient = pdl.id_patient
            WHERE pdl.id_patient = :patient_id
              AND pdl.archived = 0
              AND pdl.value IS NOT NULL
              AND (
                    pdl.alert_flag = 1
                 OR
                    (COALESCE(pat.normal_min, r.normal_min) IS NOT NULL AND pdl.value <= COALESCE(pat.normal_min, r.normal_min))
                 OR (COALESCE(pat.normal_max, r.normal_max) IS NOT NULL AND pdl.value >= COALESCE(pat.normal_max, r.normal_max))
              )
            ORDER BY
                CASE WHEN pdl.alert_flag = 1
                       OR (COALESCE(pat.critical_min, r.critical_min) IS NOT NULL AND pdl.value <= COALESCE(pat.critical_min, r.critical_min))
                       OR (COALESCE(pat.critical_max, r.critical_max) IS NOT NULL AND pdl.value >= COALESCE(pat.critical_max, r.critical_max)) THEN 0 ELSE 1 END,
                pdl.timestamp DESC
        ";
    }

    /**
     * Builds legacy latest-history SQL returning complete alert rows.
     *
     * Returned columns are aligned with snapshot query output for consistent
     * downstream mapping into `AlertItem`.
     *
     * @return string SQL statement with `:patient_id_inner` and `:patient_id_outer`.
     */
    private function getAlertsSqlLegacy(): string
    {
        return "
            SELECT m.parameter_id, m.value, m.timestamp, m.alert_flag,
                   r.display_name, r.unit,
                   COALESCE(pat.normal_min,   r.normal_min)   AS normal_min,
                   COALESCE(pat.normal_max,   r.normal_max)   AS normal_max,
                   COALESCE(pat.critical_min, r.critical_min) AS critical_min,
                   COALESCE(pat.critical_max, r.critical_max) AS critical_max
            FROM (
                SELECT pd.parameter_id, pd.value, pd.timestamp, pd.id_patient, pd.alert_flag
                FROM patient_data pd
                INNER JOIN (
                    SELECT parameter_id, MAX(`timestamp`) AS ts
                    FROM patient_data
                    WHERE id_patient = :patient_id_inner
                      AND archived = 0
                    GROUP BY parameter_id
                ) last
                  ON last.parameter_id = pd.parameter_id
                 AND last.ts = pd.`timestamp`
                WHERE pd.id_patient = :patient_id_outer
                  AND pd.archived = 0
                  AND pd.value IS NOT NULL
            ) m
            JOIN parameter_reference r ON r.parameter_id = m.parameter_id
            LEFT JOIN patient_alert_threshold pat
              ON pat.parameter_id = m.parameter_id
             AND pat.id_patient = m.id_patient
            WHERE (
                    m.alert_flag = 1
                 OR (COALESCE(pat.normal_min, r.normal_min) IS NOT NULL AND m.value <= COALESCE(pat.normal_min, r.normal_min))
                 OR (COALESCE(pat.normal_max, r.normal_max) IS NOT NULL AND m.value >= COALESCE(pat.normal_max, r.normal_max))
            )
            ORDER BY
                CASE WHEN m.alert_flag = 1
                       OR (COALESCE(pat.critical_min, r.critical_min) IS NOT NULL AND m.value <= COALESCE(pat.critical_min, r.critical_min))
                       OR (COALESCE(pat.critical_max, r.critical_max) IS NOT NULL AND m.value >= COALESCE(pat.critical_max, r.critical_max)) THEN 0 ELSE 1 END,
                m.timestamp DESC
        ";
    }
}
