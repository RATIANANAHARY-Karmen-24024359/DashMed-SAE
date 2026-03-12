<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use modules\services\UserLayoutService;
use modules\models\repositories\MonitorPreferenceRepository;

class UserLayoutServiceTest extends TestCase
{
    private UserLayoutService $service;
    private MonitorPreferenceRepository $prefModelMock;

    protected function setUp(): void
    {
        $this->prefModelMock = $this->createMock(MonitorPreferenceRepository::class);
        $this->service = new UserLayoutService($this->prefModelMock);
    }

    public function testValidateAndParseLayoutDataEmpty(): void
    {
        $result = $this->service->validateAndParseLayoutData('');
        $this->assertEmpty($result);
    }

    public function testValidateAndParseLayoutDataInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->validateAndParseLayoutData('not json');
    }

    public function testValidateAndParseLayoutDataValidItems(): void
    {
        $json = json_encode([
            ['id' => 'bpm', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 3, 'visible' => true],
            ['id' => 'spo2', 'x' => 4, 'y' => 0, 'w' => 4, 'h' => 3],
        ]);

        $result = $this->service->validateAndParseLayoutData($json);
        $this->assertCount(2, $result);
        $this->assertEquals('bpm', $result[0]['id']);
        $this->assertEquals('spo2', $result[1]['id']);
        $this->assertTrue($result[0]['visible']);
        $this->assertTrue($result[1]['visible']);
    }

    public function testValidateAndParseLayoutDataSkipsInvalidItems(): void
    {
        $json = json_encode([
            ['id' => 'bpm', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 3],
            ['no_id' => true, 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 3],
            'not_an_array',
        ]);

        $result = $this->service->validateAndParseLayoutData($json);
        $this->assertCount(1, $result);
    }

    public function testValidateAndParseLayoutDataDeduplicates(): void
    {
        $json = json_encode([
            ['id' => 'bpm', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 3],
            ['id' => 'bpm', 'x' => 4, 'y' => 0, 'w' => 4, 'h' => 3],
        ]);

        $result = $this->service->validateAndParseLayoutData($json);
        $this->assertCount(1, $result);
    }

    public function testValidateAndParseLayoutDataClampsValues(): void
    {
        $json = json_encode([
            ['id' => 'bpm', 'x' => -5, 'y' => -3, 'w' => 1, 'h' => 1],
        ]);

        $result = $this->service->validateAndParseLayoutData($json);
        $this->assertEquals(0, $result[0]['x']);
        $this->assertEquals(0, $result[0]['y']);
        $this->assertEquals(4, $result[0]['w']); // MIN_WIDTH
        $this->assertEquals(3, $result[0]['h']); // MIN_HEIGHT
    }

    public function testValidateAndParseLayoutDataClampsMaxValues(): void
    {
        $json = json_encode([
            ['id' => 'bpm', 'x' => 20, 'y' => 0, 'w' => 20, 'h' => 20],
        ]);

        $result = $this->service->validateAndParseLayoutData($json);
        $this->assertEquals(11, $result[0]['x']); // max GRID_COLUMNS - 1
        $this->assertEquals(12, $result[0]['w']); // max GRID_COLUMNS
        $this->assertEquals(10, $result[0]['h']); // MAX_HEIGHT
    }

    public function testBuildWidgetsForCustomization(): void
    {
        $this->prefModelMock->method('getAllParameters')->willReturn([
            ['parameter_id' => 'bpm', 'display_name' => 'Heart Rate', 'category' => 'vital'],
            ['parameter_id' => 'spo2', 'display_name' => 'SpO2', 'category' => 'vital'],
        ]);

        $this->prefModelMock->method('getUserLayoutSimple')->willReturn([
            ['parameter_id' => 'bpm', 'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 6, 'grid_h' => 4, 'is_hidden' => 0, 'display_order' => 1],
        ]);

        $result = $this->service->buildWidgetsForCustomization(1);

        $this->assertArrayHasKey('widgets', $result);
        $this->assertArrayHasKey('hidden', $result);
        $this->assertCount(2, $result['widgets']);
        $this->assertCount(0, $result['hidden']);
    }

    public function testBuildWidgetsForCustomizationHiddenItems(): void
    {
        $this->prefModelMock->method('getAllParameters')->willReturn([
            ['parameter_id' => 'bpm', 'display_name' => 'Heart Rate', 'category' => 'vital'],
        ]);

        $this->prefModelMock->method('getUserLayoutSimple')->willReturn([
            ['parameter_id' => 'bpm', 'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 3, 'is_hidden' => 1, 'display_order' => 1],
        ]);

        $result = $this->service->buildWidgetsForCustomization(1);

        $this->assertCount(0, $result['widgets']);
        $this->assertCount(1, $result['hidden']);
    }

    public function testSaveLayoutDelegatesToPrefModel(): void
    {
        $items = [['id' => 'bpm', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 3, 'visible' => true]];

        $this->prefModelMock->expects($this->once())
            ->method('saveUserLayoutSimple')
            ->with(1, $items);

        $this->service->saveLayout(1, $items);
    }

    public function testResetLayoutDelegatesToPrefModel(): void
    {
        $this->prefModelMock->expects($this->once())
            ->method('resetUserLayoutSimple')
            ->with(1);

        $this->service->resetLayout(1);
    }

    public function testGetLayoutMapForDashboard(): void
    {
        $this->prefModelMock->method('getUserLayoutSimple')->willReturn([
            ['parameter_id' => 'bpm', 'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 3],
        ]);

        $map = $this->service->getLayoutMapForDashboard(1);
        $this->assertArrayHasKey('bpm', $map);
        $this->assertEquals(0, $map['bpm']['grid_x']);
    }
}
