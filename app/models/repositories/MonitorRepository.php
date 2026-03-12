<?php

/**
 * app/models/repositories/MonitorRepository.php
 *
 * Repository file for the DashMed-SAE project.
 *
 * Notes:
 * - This docblock is intentionally file-scoped.
 * - Detailed PHPDoc for classes/methods is maintained near declarations.
 *
 * @package DashMed\SAE
 */

namespace modules\models\repositories;

use modules\models\BaseRepository;
use PDO;

/**
 * Class MonitorRepository
 *
 * High-performance data access layer for physiological time-series data.
 * Optimized for real-time streaming, downsampling (LTTB), and historical analytics.
 *
 * Part of the DashMed Core Infrastructure.
 *
 * @package DashMed\Modules\Models\Repositories
 * @author  DashMed Team
 * @version 3.4.2
 */
class MonitorRepository extends BaseRepository
{
    /**
     * @var string The main table containing measurement samples (e.g., 'patient_data').
     */
    private string $table;

    /**
     * @var string Status: Normal
     */
    public const STATUS_NORMAL = 'normal';

    /**
     * @var string Status: Warning
     */
    public const STATUS_WARNING = 'warning';

    /**
     * @var string Status: Critical
     */
    public const STATUS_CRITICAL = 'critical';

    /**
     * @var string Status: Unknown
     */
    public const STATUS_UNKNOWN = 'unknown';

    /**
     * Constructor
     *
     * @param PDO|null $pdo   Database connection (optional)
     * @param string   $table Table name
     */
    public function __construct(?PDO $pdo = null, string $table = 'patient_data')
    {
        parent::__construct($pdo);
        $this->table = $table;
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves the latest metrics for a patient.
     *
     * Returns an empty array on SQL error to prevent blocking display.
     *
     * @param  int $patientId Patient ID
     * @return array<int, \modules\models\entities\Indicator> List of metrics or empty array
     */
    public function getLatestMetrics(int $patientId): array
    {
        try {
            $sql = "
        SELECT
            pr.parameter_id,
            pd.value,
            pd.`timestamp`,
            pd.alert_flag,

            pr.display_name,
            pr.category,
            pr.unit,
            pr.description,
            COALESCE(pat.normal_min, pr.normal_min) as normal_min,
            COALESCE(pat.normal_max, pr.normal_max) as normal_max,
            COALESCE(pat.critical_min, pr.critical_min) as critical_min,
            COALESCE(pat.critical_max, pr.critical_max) as critical_max,
            pr.display_min,
            pr.display_max,

            pr.default_chart,

            (
                SELECT GROUP_CONCAT(chart_type ORDER BY chart_type)
                FROM parameter_chart_allowed
                WHERE parameter_id = pr.parameter_id
            ) AS allowed_charts_str,

            -- Status calculation (Logic preserved for View)
            CASE
                WHEN pd.value IS NULL THEN 'unknown'
                WHEN (
                    pd.alert_flag = 1
                    OR (COALESCE(pat.critical_min, pr.critical_min) IS NOT NULL AND pd.value <= COALESCE(pat.critical_min, pr.critical_min))
                    OR (COALESCE(pat.critical_max, pr.critical_max) IS NOT NULL AND pd.value >= COALESCE(pat.critical_max, pr.critical_max))
                ) THEN '" . self::STATUS_CRITICAL . "'
                WHEN (
                    (COALESCE(pat.normal_min, pr.normal_min) IS NOT NULL AND pd.value <= COALESCE(pat.normal_min, pr.normal_min))
                    OR (COALESCE(pat.normal_max, pr.normal_max) IS NOT NULL AND pd.value >= COALESCE(pat.normal_max, pr.normal_max))
                ) THEN '" . self::STATUS_WARNING . "'
                ELSE '" . self::STATUS_NORMAL . "'
                END AS status

        FROM parameter_reference pr

        LEFT JOIN patient_alert_threshold pat
          ON pat.parameter_id = pr.parameter_id
         AND pat.id_patient = :id_pat_threshold

        -- Last measurement for patient
        LEFT JOIN (
            SELECT pd1.*
            FROM {$this->table} pd1
            INNER JOIN (
                SELECT parameter_id, MAX(`timestamp`) AS ts
                FROM {$this->table}
                WHERE id_patient = :id_pat_inner AND archived = 0
                GROUP BY parameter_id
            ) last
              ON last.parameter_id = pd1.parameter_id
             AND last.ts = pd1.`timestamp`
            WHERE pd1.id_patient = :id_pat_outer AND pd1.archived = 0
        ) pd
          ON pd.parameter_id = pr.parameter_id

        ORDER BY
            pr.display_name ASC
        ";

            $st = $this->pdo->prepare($sql);
            $st->execute(
                [
                ':id_pat_threshold' => $patientId,
                ':id_pat_inner' => $patientId,
                ':id_pat_outer' => $patientId,
                ]
            );

            $rows = $st->fetchAll();
            $indicators = [];

            foreach ($rows as $row) {
                $allowedCharts = [];
                if (!empty($row['allowed_charts_str'])) {
                    $allowedCharts = explode(',', $row['allowed_charts_str']);
                } else {
                    $allowedCharts = ['line'];
                }

                $indicators[] = new \modules\models\entities\Indicator(
                    (string) ($row['parameter_id'] ?? ''),
                    isset($row['value']) ? (float) $row['value'] : null,
                    isset($row['timestamp']) ? (string) $row['timestamp'] : null,
                    isset($row['alert_flag']) ? (int) $row['alert_flag'] : 0,
                    (string) ($row['display_name'] ?? ''),
                    (string) ($row['category'] ?? ''),
                    (string) ($row['unit'] ?? ''),
                    isset($row['description']) ? (string) $row['description'] : null,
                    isset($row['normal_min']) ? (float) $row['normal_min'] : null,
                    isset($row['normal_max']) ? (float) $row['normal_max'] : null,
                    isset($row['critical_min']) ? (float) $row['critical_min'] : null,
                    isset($row['critical_max']) ? (float) $row['critical_max'] : null,
                    isset($row['display_min']) ? (float) $row['display_min'] : null,
                    isset($row['display_max']) ? (float) $row['display_max'] : null,
                    (string) ($row['default_chart'] ?? 'line'),
                    $allowedCharts,
                    (string) ($row['status'] ?? self::STATUS_UNKNOWN)
                );
            }

            return $indicators;
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Retrieves the latest metrics for a patient, restricted to specific parameters.
     *
     * @param int $patientId Patient ID
     * @param array<int, string> $parameterIds Parameter IDs to include
     * @return array<int, \modules\models\entities\Indicator>
     */
    public function getLatestMetricsForParameters(int $patientId, array $parameterIds): array
    {
        $parameterIds = array_values(array_unique(array_filter(array_map('strval', $parameterIds))));
        if (empty($parameterIds)) {
            return [];
        }

        // If the list is huge, fallback to the full query (prevents building giant IN clauses).
        if (count($parameterIds) > 200) {
            return $this->getLatestMetrics($patientId);
        }

        try {
            $placeholders = implode(',', array_fill(0, count($parameterIds), '?'));

            $sql = "
        SELECT
            pr.parameter_id,
            pd.value,
            pd.`timestamp`,
            pd.alert_flag,

            pr.display_name,
            pr.category,
            pr.unit,
            pr.description,
            COALESCE(pat.normal_min, pr.normal_min) as normal_min,
            COALESCE(pat.normal_max, pr.normal_max) as normal_max,
            COALESCE(pat.critical_min, pr.critical_min) as critical_min,
            COALESCE(pat.critical_max, pr.critical_max) as critical_max,
            pr.display_min,
            pr.display_max,

            pr.default_chart,

            (
                SELECT GROUP_CONCAT(chart_type ORDER BY chart_type)
                FROM parameter_chart_allowed
                WHERE parameter_id = pr.parameter_id
            ) AS allowed_charts_str,

            CASE
                WHEN pd.value IS NULL THEN 'unknown'
                WHEN (
                    pd.alert_flag = 1
                    OR (COALESCE(pat.critical_min, pr.critical_min) IS NOT NULL AND pd.value <= COALESCE(pat.critical_min, pr.critical_min))
                    OR (COALESCE(pat.critical_max, pr.critical_max) IS NOT NULL AND pd.value >= COALESCE(pat.critical_max, pr.critical_max))
                ) THEN '" . self::STATUS_CRITICAL . "'
                WHEN (
                    (COALESCE(pat.normal_min, pr.normal_min) IS NOT NULL AND pd.value <= COALESCE(pat.normal_min, pr.normal_min))
                    OR (COALESCE(pat.normal_max, pr.normal_max) IS NOT NULL AND pd.value >= COALESCE(pat.normal_max, pr.normal_max))
                ) THEN '" . self::STATUS_WARNING . "'
                ELSE '" . self::STATUS_NORMAL . "'
                END AS status

        FROM parameter_reference pr

        LEFT JOIN patient_alert_threshold pat
          ON pat.parameter_id = pr.parameter_id
         AND pat.id_patient = ?

        LEFT JOIN (
            SELECT pd1.*
            FROM {$this->table} pd1
            INNER JOIN (
                SELECT parameter_id, MAX(`timestamp`) AS ts
                FROM {$this->table}
                WHERE id_patient = ? AND archived = 0
                  AND parameter_id IN ($placeholders)
                GROUP BY parameter_id
            ) last
              ON last.parameter_id = pd1.parameter_id
             AND last.ts = pd1.`timestamp`
            WHERE pd1.id_patient = ? AND pd1.archived = 0
              AND pd1.parameter_id IN ($placeholders)
        ) pd
          ON pd.parameter_id = pr.parameter_id

        WHERE pr.parameter_id IN ($placeholders)

        ORDER BY pr.display_name ASC
        ";

            $st = $this->pdo->prepare($sql);

            // Bind values in the exact order of placeholders used above.
            $bind = [];
            $bind[] = $patientId; // pat.id_patient
            $bind[] = $patientId; // inner id_patient
            foreach ($parameterIds as $pid) {
                $bind[] = $pid;
            }
            $bind[] = $patientId; // outer id_patient
            foreach ($parameterIds as $pid) {
                $bind[] = $pid;
            }
            foreach ($parameterIds as $pid) {
                $bind[] = $pid;
            }

            $st->execute($bind);

            $rows = $st->fetchAll();
            $indicators = [];

            foreach ($rows as $row) {
                $allowedCharts = [];
                if (!empty($row['allowed_charts_str'])) {
                    $allowedCharts = explode(',', $row['allowed_charts_str']);
                } else {
                    $allowedCharts = ['line'];
                }

                $indicators[] = new \modules\models\entities\Indicator(
                    (string) ($row['parameter_id'] ?? ''),
                    isset($row['value']) ? (float) $row['value'] : null,
                    isset($row['timestamp']) ? (string) $row['timestamp'] : null,
                    isset($row['alert_flag']) ? (int) $row['alert_flag'] : 0,
                    (string) ($row['display_name'] ?? ''),
                    (string) ($row['category'] ?? ''),
                    (string) ($row['unit'] ?? ''),
                    isset($row['description']) ? (string) $row['description'] : null,
                    isset($row['normal_min']) ? (float) $row['normal_min'] : null,
                    isset($row['normal_max']) ? (float) $row['normal_max'] : null,
                    isset($row['critical_min']) ? (float) $row['critical_min'] : null,
                    isset($row['critical_max']) ? (float) $row['critical_max'] : null,
                    isset($row['display_min']) ? (float) $row['display_min'] : null,
                    isset($row['display_max']) ? (float) $row['display_max'] : null,
                    (string) ($row['default_chart'] ?? 'line'),
                    $allowedCharts,
                    (string) ($row['status'] ?? self::STATUS_UNKNOWN)
                );
            }

            return $indicators;
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Retrieves a standard buffered collection of historical data points.
     *
     * Standard implementation for small to medium ranges. For large scale analysis,
     * use streamRawHistoryByParameter() instead to maintain flat memory usage.
     *
     * @param  int         $patientId      The unique patient identifier.
     * @param  int         $limit          Maximum number of records to return (0 for unlimited).
     * @param  string|null $sinceTimestamp Optional starting point (YYYY-MM-DD HH:MM:SS).
     * @return array<int, array{parameter_id: string, value: float|null, timestamp: string, alert_flag: int}>
     * @throws \PDOException If retrieval fails.
     */
    public function getRawHistory(int $patientId, int $limit = 0, ?string $sinceTimestamp = null): array
    {
        try {
            $sinceCondition = '';
            if ($sinceTimestamp !== null) {
                $sinceCondition = ' AND `timestamp` > :since ';
            }

            $limitClause = '';
            if ($limit > 0) {
                // Fetch the LATEST $limit records (DESC) then wrap to return them ASC for the app
                $sql = "
                    SELECT * FROM (
                        SELECT parameter_id, value, `timestamp`, alert_flag
                        FROM {$this->table}
                        WHERE id_patient = :id AND archived = 0 $sinceCondition
                        ORDER BY `timestamp` DESC
                        LIMIT :limit
                    ) sub
                    ORDER BY `timestamp` ASC
                ";
            } else {
                $sql = "
                    SELECT parameter_id, value, `timestamp`, alert_flag
                    FROM {$this->table}
                    WHERE id_patient = :id AND archived = 0 $sinceCondition
                    ORDER BY `timestamp` ASC
                ";
            }

            $st = $this->pdo->prepare($sql);
            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            if ($sinceTimestamp !== null) {
                $st->bindValue(':since', $sinceTimestamp, \PDO::PARAM_STR);
            }
            if ($limit > 0) {
                $st->bindValue(':limit', $limit, \PDO::PARAM_INT);
            }

            $st->execute();
            return $st->fetchAll();
        } catch (\PDOException $e) {
            error_log("MonitorRepository::getRawHistory Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Efficiently retrieves the latest history points for SPECIFIC parameters of a patient.
     *
     * @param  int                $patientId     Patient ID
     * @param  array<int, string> $parameterIds  List of parameter IDs
     * @param  int                $limitPerParam Max points per parameter (default 1000)
     * @return array<int, array{parameter_id: string, value: float|null, timestamp: string, alert_flag: int}>
     */
    public function getLatestHistoryForSpecificParameters(int $patientId, array $parameterIds, int $limitPerParam = 1000): array
    {
        if (empty($parameterIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($parameterIds), '?'));

            $sql = "
                SELECT parameter_id, value, `timestamp`, alert_flag
                FROM (
                    SELECT 
                        parameter_id, value, `timestamp`, alert_flag,
                        ROW_NUMBER() OVER(PARTITION BY parameter_id ORDER BY `timestamp` DESC) as rn
                    FROM {$this->table}
                    WHERE id_patient = ? AND archived = 0 AND parameter_id IN ($placeholders)
                ) ranked
                WHERE rn <= ?
                ORDER BY parameter_id, `timestamp` ASC
            ";

            $st = $this->pdo->prepare($sql);
            $params = array_merge([$patientId], $parameterIds, [$limitPerParam]);
            $st->execute($params);

            return $st->fetchAll();
        } catch (\PDOException $e) {
            error_log("MonitorRepository::getLatestHistoryForSpecificParameters Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLatestHistoryForAllParameters(int $patientId, int $limitPerParam = 1000): array
    {
        try {
            /**
             * Optimized query using a ROW_NUMBER() window function.
             * This ensures we only pull exactly what's needed for sparklines (e.g. 1000 pts * 8 params = 8000 rows),
             * regardless of how many millions of rows exist in the patient_data table.
             */
            $sql = "
                SELECT parameter_id, value, `timestamp`, alert_flag
                FROM (
                    SELECT
                        parameter_id, value, `timestamp`, alert_flag,
                        ROW_NUMBER() OVER(PARTITION BY parameter_id ORDER BY `timestamp` DESC) as rn
                    FROM {$this->table}
                    WHERE id_patient = :id AND archived = 0
                ) ranked
                WHERE rn <= :limit
                ORDER BY parameter_id, `timestamp` ASC
            ";

            $st = $this->pdo->prepare($sql);
            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            $st->bindValue(':limit', $limitPerParam, \PDO::PARAM_INT);
            $st->execute();

            return $st->fetchAll();
        } catch (\PDOException $e) {
            /**
             * Fallback for older MySQL versions (< 8.0) that don't support Window Functions.
             * Note: In a real production env, we might want a more complex join here,
             * but for the current stack, Window Functions are the standard.
             */
            error_log("MonitorRepository::getLatestHistoryForAllParameters - Window functions failed, falling back to simple history: " . $e->getMessage());
            return $this->getRawHistory($patientId, 5000); // generic fallback
        }
    }

    /**
     * Retrieves raw buffered history for a specific patient and parameter.
     *
     * This method fetches historical data into memory. It is suitable for small
     * datasets but may cause memory exhaustion on massive time ranges.
     *
     * @param int         $patientId   The unique ID of the patient.
     * @param string      $parameterId The target medical parameter (e.g., 'FC', 'SpO2').
     * @param string|null $targetDate  Optional target date (YYYY-MM-DD or ISO 8601), limits data up to this exact date/time.
     * @param int         $limit       Maximum number of records to return. 0 disables the limit but is risky for large sets.
     *
     * @return array<int, array{parameter_id: string, value: float|string|null, timestamp: string, alert_flag: string|int}> Ordered chronologically ASC.
     */
    public function getRawHistoryByParameter(
        int $patientId,
        string $parameterId,
        ?string $targetDate = null,
        int $limit = 5000
    ): array {
        try {
            $dateCondition = '';
            $isDateTime = false;
            if ($targetDate !== null) {
                if (
                    preg_match(
                        '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',
                        $targetDate
                    ) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)
                ) {
                    $dateCondition = 'AND `timestamp` <= :targetDateEnd';
                    $isDateTime = true;
                }
            }

            $sql = "
            SELECT
                parameter_id,
                value,
                `timestamp`,
                alert_flag
            FROM {$this->table}
            WHERE id_patient = :id
              AND parameter_id = :paramId
              AND archived = 0
              $dateCondition
            ORDER BY `timestamp` ASC
        ";
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            $st->bindValue(':paramId', $parameterId, \PDO::PARAM_STR);
            if ($isDateTime && $targetDate !== null) {
                $formattedDate = str_replace('T', ' ', $targetDate);
                if (strlen($formattedDate) === 10) {
                    $formattedDate .= ' 23:59:59';
                } elseif (strlen($formattedDate) === 16) {
                    $formattedDate .= ':59';
                }
                $st->bindValue(':targetDateEnd', $formattedDate, \PDO::PARAM_STR);
            }

            $st->execute();
            return $st->fetchAll();
        } catch (\PDOException $e) {
            error_log("MonitorRepository::getRawHistoryByParameter Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Streams raw history using an unbuffered MySQL query.
     *
     * This method utilizes PDO unbuffered queries to yield rows one by one.
     * Ensures O(1) memory footprint regardless of the number of points.
     *
     * @important MUST restore PDO::MYSQL_ATTR_USE_BUFFERED_QUERY after use.
     *
     * @param int $patientId The unique ID of the patient.
     * @param string $parameterId The target medical parameter (e.g., 'FC', 'SpO2').
     * @param string|null $targetDate Optional target date (YYYY-MM-DD or ISO 8601), limits data up to this exact date/time.
     * @param int $limit Maximum number of records to stream. 0 means unlimited.
     *
     * @return \Generator<int, array{parameter_id: string, value: string|null, timestamp: string, alert_flag: string|int}> Ordered chronologically ASC.
     */
    /**
     * @return \Generator<int, array{parameter_id: string, value: string|null, timestamp: string, alert_flag: int|string}>
     */
    public function streamRawHistoryByParameter(
        int $patientId,
        string $parameterId,
        ?string $targetDate = null,
        int $limit = 0
    ): \Generator {
        try {
            $dateCondition = '';
            $isDateTime = false;
            if ($targetDate !== null) {
                if (
                    preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $targetDate)
                    || preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)
                ) {
                    $dateCondition = 'AND `timestamp` <= :targetDateEnd';
                    $isDateTime = true;
                }
            }

            $sql = "
            SELECT parameter_id, value, `timestamp`, alert_flag
            FROM {$this->table}
            WHERE id_patient = :id AND parameter_id = :paramId AND archived = 0 $dateCondition
            ORDER BY `timestamp` ASC
            ";

            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $st = $this->pdo->prepare($sql);

            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            $st->bindValue(':paramId', $parameterId, \PDO::PARAM_STR);

            if ($isDateTime && $targetDate !== null) {
                $formattedDate = str_replace('T', ' ', $targetDate);
                if (strlen($formattedDate) === 10) {
                    $formattedDate .= ' 23:59:59';
                } elseif (strlen($formattedDate) === 16) {
                    $formattedDate .= ':59';
                }
                $st->bindValue(':targetDateEnd', $formattedDate, \PDO::PARAM_STR);
            }
            if ($limit > 0) {
                $st->bindValue(':limit', $limit, \PDO::PARAM_INT);
            }

            $st->execute();

            while (true) {
                $row = $st->fetch(\PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    break;
                }
                /**
 * @var array{parameter_id: string, value: string|null, timestamp: string, alert_flag: int|string} $row
*/
                yield $row;
            }
        } catch (\PDOException $e) {
            error_log("MonitorRepository::streamRawHistoryByParameter Error: " . $e->getMessage());
        } finally {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    }

    /**
     * Streams pre-aggregated history by grouping records into time buckets.
     *
     * This is an optimization for extremely large datasets (e.g. > 50,000 rows).
     * It performs a FIRST pass of reduction in SQL using AVG() and GROUP BY,
     * so PHP only receives a manageable amount of points (e.g. 5,000-10,000).
     *
     * @param  int         $patientId       Patient ID.
     * @param  string      $parameterId     Parameter identifier.
     * @param  int         $intervalSeconds Time bucket size in seconds.
     * @param  string|null $targetDate      Optional date filter.
     * @return \Generator
     */
    public function streamPreAggregatedHistoryByParameter(
        int $patientId,
        string $parameterId,
        int $intervalSeconds,
        ?string $targetDate = null
    ): \Generator {
        try {
            $dateCondition = '';
            if ($targetDate !== null) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $targetDate) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
                    $dateCondition = 'AND `timestamp` <= :targetDateEnd';
                }
            }

            $sql = "
            SELECT
                parameter_id,
                AVG(value) as value,
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(`timestamp`) / :interval) * :interval) as `timestamp`,
                MAX(alert_flag) as alert_flag
            FROM {$this->table}
            WHERE id_patient = :id AND parameter_id = :paramId AND archived = 0 $dateCondition
            GROUP BY FLOOR(UNIX_TIMESTAMP(`timestamp`) / :interval)
            ORDER BY `timestamp` ASC
            ";

            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $st = $this->pdo->prepare($sql);

            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            $st->bindValue(':paramId', $parameterId, \PDO::PARAM_STR);
            $st->bindValue(':interval', $intervalSeconds, \PDO::PARAM_INT);

            if ($dateCondition && $targetDate !== null) {
                $formattedDate = str_replace('T', ' ', $targetDate);
                if (strlen($formattedDate) === 10) {
                    $formattedDate .= ' 23:59:59';
                } elseif (strlen($formattedDate) === 16) {
                    $formattedDate .= ':59';
                }
                $st->bindValue(':targetDateEnd', $formattedDate, \PDO::PARAM_STR);
            }

            $st->execute();

            while (true) {
                $row = $st->fetch(\PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    break;
                }
                yield $row;
            }
        } catch (\PDOException $e) {
            error_log("MonitorRepository::streamPreAggregatedHistoryByParameter Error: " . $e->getMessage());
        } finally {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    }

    /**
     * Counts the total number of records available for a specific patient and parameter.
     *
     * This count is crucial for feeding the mathematically accurate LTTB downsampling
     * algorithm before an unbuffered stream begins, as streams cannot be counted mid-flight.
     *
     * @param int         $patientId   The unique ID of the patient.
     * @param string      $parameterId The target medical parameter.
     * @param string|null $targetDate  Optional target date (YYYY-MM-DD or ISO 8601), limits up to this date/time.
     *
     * @return int The total chronological row count.
     */
    public function countRawHistoryByParameter(
        int $patientId,
        string $parameterId,
        ?string $targetDate = null
    ): int {
        try {
            $dateCondition = '';
            $isDateTime = false;
            if ($targetDate !== null) {
                if (
                    preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $targetDate)
                    || preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)
                ) {
                    $dateCondition = 'AND `timestamp` <= :targetDateEnd';
                    $isDateTime = true;
                }
            }

            $sql = "
            SELECT count(*) as total
            FROM {$this->table}
            WHERE id_patient = :id
              AND parameter_id = :paramId
              AND archived = 0
              $dateCondition
            ";

            $st = $this->pdo->prepare($sql);
            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            $st->bindValue(':paramId', $parameterId, \PDO::PARAM_STR);

            if ($isDateTime && $targetDate !== null) {
                $formattedDate = str_replace('T', ' ', $targetDate);
                if (strlen($formattedDate) === 10) {
                    $formattedDate .= ' 23:59:59';
                } elseif (strlen($formattedDate) === 16) {
                    $formattedDate .= ':59';
                }
                $st->bindValue(':targetDateEnd', $formattedDate, \PDO::PARAM_STR);
            }

            $st->execute();
            $res = $st->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($res)) {
                return 0;
            }
            if (!isset($res['total'])) {
                return 0;
            }
            $total = $res['total'];
            return is_numeric($total) ? (int) $total : 0;
        } catch (\PDOException $e) {
            error_log("MonitorRepository::countRawHistoryByParameter Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Retrieves the complete list of available chart types.
     *
     * @return array<string, string> Associative array (type => label)
     */
    /**
     * Fetches the latest N samples for a single parameter (tail) in ascending timestamp order.
     *
     * Implementation details:
     * - Uses an index-friendly `ORDER BY timestamp DESC LIMIT N` subquery.
     * - Wraps the result to return points in chronological order (ASC) for charting.
     *
     * @param int         $patientId   The patient identifier.
     * @param string      $parameterId The parameter identifier (e.g. "FC_m", "HCO3").
     * @param int         $limit       Max number of points to return (hard-capped to 5000).
     * @param string|null $targetDate  Optional upper bound (YYYY-MM-DD or YYYY-MM-DDTHH:MM).
     *
     * @return array<int, array{
     *   parameter_id: string,
     *   value: float|string|null,
     *   timestamp: string,
     *   alert_flag: string|int
     * }>
     */
    public function getTailHistoryByParameter(
        int $patientId,
        string $parameterId,
        int $limit = 250,
        ?string $targetDate = null
    ): array {
        $limit = max(1, min(5000, $limit));

        try {
            $dateCondition = '';
            $isDateTime = false;
            if ($targetDate !== null) {
                if (
                    preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $targetDate)
                    || preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)
                ) {
                    $dateCondition = 'AND `timestamp` <= :targetDateEnd';
                    $isDateTime = true;
                }
            }

            $sql = "
                SELECT * FROM (
                    SELECT parameter_id, value, `timestamp`, alert_flag
                    FROM {$this->table}
                    WHERE id_patient = :id
                      AND parameter_id = :paramId
                      AND archived = 0
                      $dateCondition
                    ORDER BY `timestamp` DESC
                    LIMIT :limit
                ) sub
                ORDER BY `timestamp` ASC
            ";

            $st = $this->pdo->prepare($sql);
            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            $st->bindValue(':paramId', $parameterId, \PDO::PARAM_STR);
            $st->bindValue(':limit', $limit, \PDO::PARAM_INT);

            if ($isDateTime && $targetDate !== null) {
                $formattedDate = str_replace('T', ' ', $targetDate);
                if (strlen($formattedDate) === 10) {
                    $formattedDate .= ' 23:59:59';
                } elseif (strlen($formattedDate) === 16) {
                    $formattedDate .= ':59';
                }
                $st->bindValue(':targetDateEnd', $formattedDate, \PDO::PARAM_STR);
            }

            $st->execute();
            return $st->fetchAll();
        } catch (\PDOException $e) {
            error_log('MonitorRepository::getTailHistoryByParameter Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetches an exact, forward-only chunk after a given timestamp cursor.
     *
     * Notes:
     * - This cursor is weaker than the monotonic `seq` cursor. Prefer {@see getHistoryChunkAfterSeq()}.
     * - Kept for backwards-compatibility and tooling that only knows timestamps.
     *
     * @param int         $patientId      The patient identifier.
     * @param string      $parameterId    The parameter identifier.
     * @param string|null $afterTimestamp Exclusive cursor in SQL DATETIME (YYYY-MM-DD HH:MM:SS) or null to start.
     * @param int         $limit          Max number of points to return (hard-capped to 20000).
     *
     * @return array<int, array{
     *   parameter_id: string,
     *   value: float|string|null,
     *   timestamp: string,
     *   alert_flag: string|int
     * }>
     */
    public function getHistoryChunkAfter(
        int $patientId,
        string $parameterId,
        ?string $afterTimestamp,
        int $limit = 5000
    ): array {
        $limit = max(1, min(20000, $limit));

        try {
            $afterCondition = '';
            if ($afterTimestamp !== null && $afterTimestamp !== '') {
                // accept ISO too
                $afterTimestamp = str_replace('T', ' ', $afterTimestamp);
                if (str_ends_with($afterTimestamp, 'Z')) {
                    $afterTimestamp = rtrim($afterTimestamp, 'Z');
                }
                $afterCondition = 'AND `timestamp` > :afterTs';
            }

            $sql = "
                SELECT parameter_id, value, `timestamp`, alert_flag
                FROM {$this->table}
                WHERE id_patient = :id
                  AND parameter_id = :paramId
                  AND archived = 0
                  $afterCondition
                ORDER BY `timestamp` ASC
                LIMIT :limit
            ";

            $st = $this->pdo->prepare($sql);
            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            $st->bindValue(':paramId', $parameterId, \PDO::PARAM_STR);
            if ($afterCondition !== '') {
                $st->bindValue(':afterTs', $afterTimestamp, \PDO::PARAM_STR);
            }
            $st->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll();
        } catch (\PDOException $e) {
            error_log('MonitorRepository::getHistoryChunkAfter Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetches an exact, forward-only chunk using the monotonic `seq` cursor.
     *
     * Why `seq`:
     * - Guarantees strict ordering and safe pagination even when multiple rows share the same timestamp.
     * - Allows resumable background sync without missing or duplicating points.
     *
     * @param int      $patientId   The patient identifier.
     * @param string   $parameterId The parameter identifier.
     * @param int|null $afterSeq    Exclusive cursor (seq). Use null to start from the beginning.
     * @param int      $limit       Max number of points to return (hard-capped to 20000).
     *
     * @return array<int, array{
     *   seq: int|string,
     *   parameter_id: string,
     *   value: float|string|null,
     *   timestamp: string,
     *   alert_flag: string|int
     * }>
     */
    public function getHistoryChunkAfterSeq(
        int $patientId,
        string $parameterId,
        ?int $afterSeq,
        int $limit = 5000
    ): array {
        $limit = max(1, min(20000, $limit));

        try {
            $afterCondition = '';
            if ($afterSeq !== null && $afterSeq > 0) {
                $afterCondition = 'AND seq > :afterSeq';
            }

            $sql = "
                SELECT seq, parameter_id, value, `timestamp`, alert_flag
                FROM {$this->table}
                WHERE id_patient = :id
                  AND parameter_id = :paramId
                  AND archived = 0
                  $afterCondition
                ORDER BY seq ASC
                LIMIT :limit
            ";

            $st = $this->pdo->prepare($sql);
            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            $st->bindValue(':paramId', $parameterId, \PDO::PARAM_STR);
            if ($afterCondition !== '') {
                $st->bindValue(':afterSeq', $afterSeq, \PDO::PARAM_INT);
            }
            $st->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll();
        } catch (\PDOException $e) {
            error_log('MonitorRepository::getHistoryChunkAfterSeq Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Lightweight metadata for sync/validation.
     *
     * This is intentionally cheap to compute compared to serving large histories.
     * The `max_seq` watermark enables a client to determine when it is fully synced.
     *
     * @param int    $patientId   The patient identifier.
     * @param string $parameterId The parameter identifier.
     *
     * @return array{max_ts: string|null, max_seq: int|null, count: int|null}
     */
    public function getHistoryMeta(int $patientId, string $parameterId): array
    {
        try {
            $sql = "
                SELECT MAX(`timestamp`) AS max_ts, COUNT(*) AS cnt, MAX(seq) AS max_seq
                FROM {$this->table}
                WHERE id_patient = :id
                  AND parameter_id = :paramId
                  AND archived = 0
            ";
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':id', $patientId, \PDO::PARAM_INT);
            $st->bindValue(':paramId', $parameterId, \PDO::PARAM_STR);
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return ['max_ts' => null, 'max_seq' => null, 'count' => null];
            }

            // max_seq may not exist on older schema; keep null-safe.
            $maxTs = $row['max_ts'] ?? null;
            $cnt = $row['cnt'] ?? null;
            $maxSeq = $row['max_seq'] ?? null;

            return [
                'max_ts' => is_scalar($maxTs) ? (string) $maxTs : null,
                'count' => is_numeric($cnt) ? (int) $cnt : null,
                'max_seq' => is_numeric($maxSeq) ? (int) $maxSeq : null,
            ];
        } catch (\PDOException $e) {
            error_log('MonitorRepository::getHistoryMeta Error: ' . $e->getMessage());
            return ['max_ts' => null, 'max_seq' => null, 'count' => null];
        }
    }

    /**
     * Retrieves the complete list of available chart types.
     *
     * @return array<string, string> Associative array (type => label)
     */
    public function getAllChartTypes(): array
    {
        try {
            $sql = "SELECT chart_type, label FROM chart_types ORDER BY label ASC";
            $st = $this->pdo->prepare($sql);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\PDOException $e) {
            error_log("MonitorRepository::getAllChartTypes Error: " . $e->getMessage());
            return [];
        }
    }
}
