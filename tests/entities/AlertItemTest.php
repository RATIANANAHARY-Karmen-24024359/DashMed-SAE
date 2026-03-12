<?php

declare(strict_types=1);

namespace Tests\Entities;

use PHPUnit\Framework\TestCase;
use modules\models\entities\AlertItem;

class AlertItemTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $alert = new AlertItem(
            parameterId: 'bpm',
            displayName: 'Heart Rate',
            unit: 'bpm',
            value: 110.0,
            minThreshold: 60.0,
            maxThreshold: 100.0,
            criticalMin: 40.0,
            criticalMax: 140.0,
            timestamp: '2024-01-01 12:00:00',
            isBelowMin: false,
            isAboveMax: true,
            isCritical: false
        );

        $this->assertEquals('bpm', $alert->parameterId);
        $this->assertEquals('Heart Rate', $alert->displayName);
        $this->assertEquals(110.0, $alert->value);
        $this->assertTrue($alert->isAboveMax);
        $this->assertFalse($alert->isBelowMin);
        $this->assertFalse($alert->isCritical);
    }

    public function testFromRowNormalValue(): void
    {
        $row = [
            'parameter_id' => 'bpm',
            'display_name' => 'Heart Rate',
            'unit' => 'bpm',
            'value' => '75',
            'normal_min' => '60',
            'normal_max' => '100',
            'critical_min' => '40',
            'critical_max' => '140',
            'timestamp' => '2024-01-01 12:00:00',
        ];

        $alert = AlertItem::fromRow($row);

        $this->assertEquals('bpm', $alert->parameterId);
        $this->assertEquals(75.0, $alert->value);
        $this->assertFalse($alert->isBelowMin);
        $this->assertFalse($alert->isAboveMax);
        $this->assertFalse($alert->isCritical);
    }

    public function testFromRowHighValue(): void
    {
        $row = [
            'parameter_id' => 'bpm',
            'display_name' => 'Heart Rate',
            'unit' => 'bpm',
            'value' => '110',
            'normal_min' => '60',
            'normal_max' => '100',
            'critical_min' => '40',
            'critical_max' => '140',
            'timestamp' => '2024-01-01 12:00:00',
        ];

        $alert = AlertItem::fromRow($row);

        $this->assertTrue($alert->isAboveMax);
        $this->assertFalse($alert->isCritical);
    }

    public function testFromRowCriticalValue(): void
    {
        $row = [
            'parameter_id' => 'bpm',
            'display_name' => 'Heart Rate',
            'unit' => 'bpm',
            'value' => '35',
            'normal_min' => '60',
            'normal_max' => '100',
            'critical_min' => '40',
            'critical_max' => '140',
            'timestamp' => '2024-01-01 12:00:00',
        ];

        $alert = AlertItem::fromRow($row);

        $this->assertTrue($alert->isBelowMin);
        $this->assertTrue($alert->isCritical);
    }

    public function testFromRowWithNullThresholds(): void
    {
        $row = [
            'parameter_id' => 'custom',
            'display_name' => 'Custom',
            'unit' => 'u',
            'value' => '50',
            'normal_min' => null,
            'normal_max' => null,
            'critical_min' => null,
            'critical_max' => null,
            'timestamp' => '2024-01-01 12:00:00',
        ];

        $alert = AlertItem::fromRow($row);

        $this->assertNull($alert->minThreshold);
        $this->assertNull($alert->maxThreshold);
        $this->assertFalse($alert->isBelowMin);
        $this->assertFalse($alert->isAboveMax);
        $this->assertFalse($alert->isCritical);
    }

    public function testFromRowWithMissingFields(): void
    {
        $alert = AlertItem::fromRow([]);
        $this->assertEquals('', $alert->parameterId);
        $this->assertEquals(0.0, $alert->value);
    }

    public function testToArray(): void
    {
        $alert = new AlertItem(
            parameterId: 'spo2',
            displayName: 'SpO2',
            unit: '%',
            value: 95.0,
            minThreshold: 95.0,
            maxThreshold: 100.0,
            criticalMin: 90.0,
            criticalMax: null,
            timestamp: '2024-01-01 12:00:00',
            isBelowMin: true,
            isAboveMax: false,
            isCritical: false
        );

        $arr = $alert->toArray();

        $this->assertEquals('spo2', $arr['parameterId']);
        $this->assertEquals(95.0, $arr['value']);
        $this->assertTrue($arr['isBelowMin']);
        $this->assertNull($arr['criticalMax']);
    }

    public function testFromRowTimestampFallback(): void
    {
        $row = [
            'parameter_id' => 'bpm',
            'display_name' => 'HR',
            'unit' => 'bpm',
            'value' => '80',
            'timestamp' => '',
        ];

        $alert = AlertItem::fromRow($row);
        $this->assertNotEmpty($alert->timestamp);
    }
}
