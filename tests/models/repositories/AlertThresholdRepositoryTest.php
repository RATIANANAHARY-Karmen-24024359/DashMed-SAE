<?php

namespace tests\models\repositories;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use modules\models\repositories\AlertThresholdRepository;

class AlertThresholdRepositoryTest extends TestCase
{
    private PDO $pdoMock;
    private PDOStatement $stmtMock;
    private AlertThresholdRepository $repo;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
        $this->repo = new AlertThresholdRepository($this->pdoMock);
    }

    public function testGetThresholdsForPatientReturnsArray(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([
            ['parameter_id' => 'FC', 'effective_normal_min' => 60]
        ]);

        $result = $this->repo->getThresholdsForPatient(1);
        $this->assertCount(1, $result);
        $this->assertEquals('FC', $result[0]['parameter_id']);
    }

    public function testSaveThresholdReturnsTrueOnSuccess(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([
                ':patient_id' => 1,
                ':parameter_id' => 'FC',
                ':normal_min' => 60.0,
                ':normal_max' => 100.0,
                ':critical_min' => 50.0,
                ':critical_max' => 120.0,
                ':updated_by' => 2
            ])
            ->willReturn(true);

        $result = $this->repo->saveThreshold(1, 'FC', 60.0, 100.0, 50.0, 120.0, 2);
        $this->assertTrue($result);
    }

    public function testResetThresholdReturnsTrueOnSuccess(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([':pid' => 1, ':param' => 'FC'])
            ->willReturn(true);

        $result = $this->repo->resetThreshold(1, 'FC');
        $this->assertTrue($result);
    }

    public function testGetEffectiveThresholdReturnsArray(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetch')->willReturn([
            'normal_min' => 60,
            'normal_max' => 100,
            'critical_min' => 45,
            'critical_max' => 125
        ]);

        $result = $this->repo->getEffectiveThreshold(1, 'FC');
        $this->assertIsArray($result);
        $this->assertEquals(60, $result['normal_min']);
    }
}
