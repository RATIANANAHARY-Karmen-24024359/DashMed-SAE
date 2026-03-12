<?php

declare(strict_types=1);

namespace Tests\Entities;

use PHPUnit\Framework\TestCase;
use modules\models\entities\Indicator;

class IndicatorTest extends TestCase
{
    private function makeIndicator(): Indicator
    {
        return new Indicator(
            parameterId: 'bpm',
            value: 80.0,
            timestamp: '2024-01-01 12:00:00',
            alertFlag: 0,
            displayName: 'Heart Rate',
            category: 'vital',
            unit: 'bpm',
            description: 'Heart rate measurement',
            normalMin: 60.0,
            normalMax: 100.0,
            criticalMin: 40.0,
            criticalMax: 140.0,
            displayMin: 0.0,
            displayMax: 200.0,
            defaultChart: 'line',
            allowedCharts: ['line', 'bar', 'area'],
            status: 'normal'
        );
    }

    public function testConstructorAndGetters(): void
    {
        $m = $this->makeIndicator();

        $this->assertEquals('bpm', $m->getId());
        $this->assertEquals(80.0, $m->getValue());
        $this->assertEquals('2024-01-01 12:00:00', $m->getTimestamp());
        $this->assertEquals(0, $m->getAlertFlag());
        $this->assertEquals('Heart Rate', $m->getDisplayName());
        $this->assertEquals('vital', $m->getCategory());
        $this->assertEquals('bpm', $m->getUnit());
        $this->assertEquals('Heart rate measurement', $m->getDescription());
        $this->assertEquals(60.0, $m->getNormalMin());
        $this->assertEquals(100.0, $m->getNormalMax());
        $this->assertEquals(40.0, $m->getCriticalMin());
        $this->assertEquals(140.0, $m->getCriticalMax());
        $this->assertEquals(0.0, $m->getDisplayMin());
        $this->assertEquals(200.0, $m->getDisplayMax());
        $this->assertEquals('line', $m->getDefaultChart());
        $this->assertCount(3, $m->getAllowedCharts());
        $this->assertEquals('normal', $m->getStatus());
    }

    public function testSetters(): void
    {
        $m = $this->makeIndicator();

        $m->setValue(90.0);
        $this->assertEquals(90.0, $m->getValue());

        $m->setTimestamp('2024-06-01 00:00:00');
        $this->assertEquals('2024-06-01 00:00:00', $m->getTimestamp());

        $m->setAlertFlag(1);
        $this->assertEquals(1, $m->getAlertFlag());

        $m->setStatus('critical');
        $this->assertEquals('critical', $m->getStatus());

        $m->setChartType('bar');
        $this->assertEquals('bar', $m->getChartType());

        $m->setModalChartType('scatter');
        $this->assertEquals('scatter', $m->getModalChartType());

        $m->setDisplayOrder(5);
        $this->assertEquals(5, $m->getDisplayOrder());

        $m->setPriority(2);
        $this->assertEquals(2, $m->getPriority());

        $m->setForceShown(true);
        $this->assertTrue($m->isForceShown());

        $m->setAllowedCharts(['line']);
        $this->assertCount(1, $m->getAllowedCharts());
    }

    public function testViewData(): void
    {
        $m = $this->makeIndicator();
        $m->setViewData(['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $m->getViewData());
    }

    public function testHistory(): void
    {
        $m = $this->makeIndicator();
        $history = [
            ['timestamp' => '2024-01-01 12:00:00', 'value' => 75, 'alert_flag' => 0],
            ['timestamp' => '2024-01-01 12:01:00', 'value' => 80, 'alert_flag' => 0],
        ];
        $m->setHistory($history);
        $this->assertCount(2, $m->getHistory());
    }

    public function testDefaultValues(): void
    {
        $m = $this->makeIndicator();
        $this->assertEquals(0, $m->getPriority());
        $this->assertEquals(9999, $m->getDisplayOrder());
        $this->assertFalse($m->isForceShown());
        $this->assertEquals('line', $m->getChartType());
        $this->assertEquals('line', $m->getModalChartType());
        $this->assertEmpty($m->getViewData());
        $this->assertEmpty($m->getHistory());
    }

    public function testToArray(): void
    {
        $m = $this->makeIndicator();
        $arr = $m->toArray();

        $this->assertEquals('bpm', $arr['parameter_id']);
        $this->assertEquals(80.0, $arr['value']);
        $this->assertEquals('Heart Rate', $arr['display_name']);
        $this->assertEquals('normal', $arr['status']);
        $this->assertArrayHasKey('history', $arr);
        $this->assertArrayHasKey('view_data', $arr);
        $this->assertArrayHasKey('priority', $arr);
        $this->assertArrayHasKey('chart_type', $arr);
        $this->assertArrayHasKey('modal_chart_type', $arr);
    }

    public function testNullableValues(): void
    {
        $m = new Indicator(
            parameterId: 'test',
            value: null,
            timestamp: null,
            alertFlag: 0,
            displayName: 'Test',
            category: 'test',
            unit: '',
            description: null,
            normalMin: null,
            normalMax: null,
            criticalMin: null,
            criticalMax: null,
            displayMin: null,
            displayMax: null,
            defaultChart: 'line',
            allowedCharts: [],
            status: 'unknown'
        );

        $this->assertNull($m->getValue());
        $this->assertNull($m->getTimestamp());
        $this->assertNull($m->getDescription());
        $this->assertNull($m->getNormalMin());
        $this->assertNull($m->getCriticalMax());
    }
}
