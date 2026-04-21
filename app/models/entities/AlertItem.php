<?php

/**
 * app/models/entities/AlertItem.php
 *
 * Entity file for the DashMed-SAE project.
 *
 * Notes:
 * - This docblock is intentionally file-scoped.
 * - Detailed PHPDoc for classes/methods is maintained near declarations.
 *
 * @package DashMed\SAE
 */

declare(strict_types=1);

namespace modules\models\entities;

/**
 * Immutable data-transfer object describing one alert candidate.
 *
 * This value object is built from repository SQL rows and then consumed by
 * the alert presentation service. It intentionally keeps both threshold-based
 * booleans (`isBelowMin`, `isAboveMax`) and the explicit `alert_flag` outcome
 * collapsed into `isCritical`, so callers can render a clinically conservative
 * message without recomputing threshold logic.
 */
final class AlertItem
{
    /**
     * @param string      $parameterId  Stable parameter identifier (for example `HR`, `SpO2`).
     * @param string      $displayName  Human-readable parameter label used in UI.
     * @param string      $unit         Measurement unit shown to clinicians.
     * @param float       $value        Latest measured value for the parameter.
     * @param float|null  $minThreshold Effective lower normal threshold.
     * @param float|null  $maxThreshold Effective upper normal threshold.
     * @param float|null  $criticalMin  Effective lower critical threshold.
     * @param float|null  $criticalMax  Effective upper critical threshold.
     * @param string      $timestamp    Source timestamp (`Y-m-d H:i:s` expected).
     * @param bool        $isBelowMin   True when value is below/equal normal minimum.
     * @param bool        $isAboveMax   True when value is above/equal normal maximum.
     * @param bool        $isCritical   True when value is critical by threshold or explicit alert flag.
     */
    public function __construct(
        public string $parameterId,
        public string $displayName,
        public string $unit,
        public float $value,
        public ?float $minThreshold,
        public ?float $maxThreshold,
        public ?float $criticalMin,
        public ?float $criticalMax,
        public string $timestamp,
        public bool $isBelowMin,
        public bool $isAboveMax,
        public bool $isCritical
    ) {
    }

    /**
     * Builds an immutable alert DTO from one SQL row.
     *
     * Expected keys are compatible with both legacy latest-history queries and
     * snapshot-backed queries:
     * - `parameter_id`, `display_name`, `unit`, `value`, `timestamp`
     * - `normal_min`, `normal_max`, `critical_min`, `critical_max`
     * - optional `alert_flag` (when present and equal to `1`, critical is forced)
     *
     * @param array<string, mixed> $row Raw repository row.
     */
    public static function fromRow(array $row): self
    {
        $value = self::toFloat($row['value'] ?? 0);
        $minThreshold = self::toFloatOrNull($row['normal_min'] ?? null);
        $maxThreshold = self::toFloatOrNull($row['normal_max'] ?? null);
        $criticalMin = self::toFloatOrNull($row['critical_min'] ?? null);
        $criticalMax = self::toFloatOrNull($row['critical_max'] ?? null);
        $criticalFlag = isset($row['alert_flag']) && is_numeric($row['alert_flag']) && (int) $row['alert_flag'] === 1;

        $isBelowMin = $minThreshold !== null && $value <= $minThreshold;
        $isAboveMax = $maxThreshold !== null && $value >= $maxThreshold;
        $isCritical = $criticalFlag
            || ($criticalMin !== null && $value <= $criticalMin)
            || ($criticalMax !== null && $value >= $criticalMax);

        $timestamp = self::toString($row['timestamp'] ?? '');

        return new self(
            parameterId: self::toString($row['parameter_id'] ?? ''),
            displayName: self::toString($row['display_name'] ?? ''),
            unit: self::toString($row['unit'] ?? ''),
            value: $value,
            minThreshold: $minThreshold,
            maxThreshold: $maxThreshold,
            criticalMin: $criticalMin,
            criticalMax: $criticalMax,
            timestamp: $timestamp !== '' ? $timestamp : date('Y-m-d H:i:s'),
            isBelowMin: $isBelowMin,
            isAboveMax: $isAboveMax,
            isCritical: $isCritical
        );
    }

    /**
     * Exports this DTO into a predictable associative payload for serializers.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'parameterId' => $this->parameterId,
            'displayName' => $this->displayName,
            'unit' => $this->unit,
            'value' => $this->value,
            'minThreshold' => $this->minThreshold,
            'maxThreshold' => $this->maxThreshold,
            'criticalMin' => $this->criticalMin,
            'criticalMax' => $this->criticalMax,
            'timestamp' => $this->timestamp,
            'isBelowMin' => $this->isBelowMin,
            'isAboveMax' => $this->isAboveMax,
            'isCritical' => $this->isCritical,
        ];
    }

    /**
     * Converts scalar numeric input to float; falls back to `0.0`.
     */
    private static function toFloat(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    /**
     * Converts scalar numeric input to float; returns `null` when not numeric.
     */
    private static function toFloatOrNull(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Converts scalar values to string while avoiding notices on complex types.
     */
    private static function toString(mixed $v): string
    {
        return is_string($v) || is_numeric($v) ? (string) $v : '';
    }
}
