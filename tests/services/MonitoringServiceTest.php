<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use modules\services\MonitoringService;
use modules\models\entities\Indicator;
use modules\models\repositories\MonitorRepository;

class MonitoringServiceTest extends TestCase
{
    private MonitoringService $service;

    protected function setUp(): void
    {
        $this->service = new MonitoringService();
    }

    private function makeIndicator(
        string $id = 'bpm',
        ?float $value = 80.0,
        string $status = 'normal',
        ?float $nmin = 60.0,
        ?float $nmax = 100.0,
        ?float $cmin = 40.0,
        ?float $cmax = 140.0
    ): Indicator {
        return new Indicator(
            parameterId: $id,
            value: $value,
            timestamp: '2024-01-01 12:00:00',
            alertFlag: 0,
            displayName: 'Heart Rate',
            category: 'vital',
            unit: 'bpm',
            description: 'Heart rate',
            normalMin: $nmin,
            normalMax: $nmax,
            criticalMin: $cmin,
            criticalMax: $cmax,
            displayMin: 0.0,
            displayMax: 200.0,
            defaultChart: 'line',
            allowedCharts: ['line', 'bar'],
            status: $status
        );
    }

    public function testCalculatePriorityCritical(): void
    {
        $m = $this->makeIndicator(status: MonitorRepository::STATUS_CRITICAL);
        $this->assertEquals(2, $this->service->calculatePriority($m));
    }

    public function testCalculatePriorityWarning(): void
    {
        $m = $this->makeIndicator(status: MonitorRepository::STATUS_WARNING);
        $this->assertEquals(1, $this->service->calculatePriority($m));
    }

    public function testCalculatePriorityNormal(): void
    {
        $m = $this->makeIndicator(status: 'normal');
        $this->assertEquals(0, $this->service->calculatePriority($m));
    }

    public function testProcessMetricsAppliesHistory(): void
    {
        $m = $this->makeIndicator(value: null);
        $history = [
            ['parameter_id' => 'bpm', 'timestamp' => '2024-01-01 12:00:00', 'value' => 75, 'alert_flag' => 0],
            ['parameter_id' => 'bpm', 'timestamp' => '2024-01-01 12:01:00', 'value' => 80, 'alert_flag' => 0],
        ];

        $result = $this->service->processMetrics([$m], $history, []);

        $this->assertCount(1, $result);
        $this->assertEquals(80.0, $result[0]->getValue());
    }

    public function testProcessMetricsSetsStatusFromAlertFlag(): void
    {
        $m = $this->makeIndicator(value: 80.0, status: 'normal');
        $history = [
            ['parameter_id' => 'bpm', 'timestamp' => '2024-01-01 12:00:00', 'value' => 80, 'alert_flag' => 1],
        ];

        $result = $this->service->processMetrics([$m], $history, []);

        $this->assertEquals(MonitorRepository::STATUS_CRITICAL, $result[0]->getStatus());
    }

    public function testProcessMetricsSetsWarningFromThresholds(): void
    {
        $m = $this->makeIndicator(value: 105.0, status: 'normal');
        $result = $this->service->processMetrics([$m], [], []);

        $this->assertEquals(MonitorRepository::STATUS_WARNING, $result[0]->getStatus());
    }

    public function testProcessMetricsSetsCriticalFromThresholds(): void
    {
        $m = $this->makeIndicator(value: 35.0, status: 'normal');
        $result = $this->service->processMetrics([$m], [], []);

        $this->assertEquals(MonitorRepository::STATUS_CRITICAL, $result[0]->getStatus());
    }

    public function testProcessMetricsAppliesChartPreferences(): void
    {
        $m = $this->makeIndicator();
        $prefs = [
            'charts' => ['bpm' => ['chart_type' => 'bar', 'modal_chart_type' => 'scatter']],
            'orders' => [],
        ];

        $result = $this->service->processMetrics([$m], [], $prefs);

        $this->assertEquals('bar', $result[0]->getChartType());
        $this->assertEquals('scatter', $result[0]->getModalChartType());
    }

    public function testProcessMetricsSortsByDisplayOrder(): void
    {
        $m1 = $this->makeIndicator(id: 'spo2', value: 98.0);
        $m2 = $this->makeIndicator(id: 'bpm', value: 80.0);

        $prefs = [
            'orders' => [
                'bpm' => ['display_order' => 1],
                'spo2' => ['display_order' => 2],
            ],
        ];

        $result = $this->service->processMetrics([$m1, $m2], [], $prefs);

        $this->assertEquals('bpm', $result[0]->getId());
        $this->assertEquals('spo2', $result[1]->getId());
    }

    public function testPrepareViewDataGeneratesExpectedKeys(): void
    {
        $m = $this->makeIndicator();
        $viewData = $this->service->prepareViewData($m);

        $this->assertArrayHasKey('parameter_id', $viewData);
        $this->assertArrayHasKey('display_name', $viewData);
        $this->assertArrayHasKey('value', $viewData);
        $this->assertArrayHasKey('unit', $viewData);
        $this->assertArrayHasKey('thresholds', $viewData);
        $this->assertArrayHasKey('chart_type', $viewData);
        $this->assertArrayHasKey('state_label', $viewData);
        $this->assertArrayHasKey('card_class', $viewData);
        $this->assertArrayHasKey('chart_config', $viewData);
        $this->assertArrayHasKey('history_html_data', $viewData);
    }

    public function testPrepareViewDataNullValue(): void
    {
        $m = $this->makeIndicator(value: null);
        $viewData = $this->service->prepareViewData($m);

        $this->assertEquals('—', $viewData['value']);
        $this->assertEquals('', $viewData['unit']);
    }

    public function testProcessMetricsForceShowsHiddenCriticalItems(): void
    {
        $m = $this->makeIndicator(value: 35.0, status: 'normal');
        $prefs = [
            'orders' => ['bpm' => ['is_hidden' => true, 'display_order' => 1]],
        ];

        $result = $this->service->processMetrics([$m], [], $prefs, false);

        $this->assertTrue($result[0]->isForceShown());
    }
}
