<?php

declare(strict_types=1);

namespace Tests\Models\Repositories;

use PHPUnit\Framework\TestCase;
use modules\models\repositories\AlertThresholdRepository;
use PDO;

class AlertThresholdRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AlertThresholdRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("CREATE TABLE parameter_reference (
            parameter_id TEXT PRIMARY KEY,
            display_name TEXT,
            category TEXT,
            unit TEXT,
            normal_min REAL,
            normal_max REAL,
            critical_min REAL,
            critical_max REAL
        )");

        $this->pdo->exec("CREATE TABLE patient_alert_threshold (
            id_patient INTEGER,
            parameter_id TEXT,
            normal_min REAL,
            normal_max REAL,
            critical_min REAL,
            critical_max REAL,
            updated_by INTEGER,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_patient, parameter_id)
        )");

        $this->pdo->exec("INSERT INTO parameter_reference VALUES ('bpm', 'Fréquence cardiaque', 'vital', 'bpm', 60, 100, 40, 140)");
        $this->pdo->exec("INSERT INTO parameter_reference VALUES ('spo2', 'SpO2', 'vital', '%', 95, 100, 90, NULL)");

        $this->repository = new AlertThresholdRepository($this->pdo);
    }

    public function testGetThresholdsForPatientReturnsDefaults(): void
    {
        $thresholds = $this->repository->getThresholdsForPatient(1);
        $this->assertCount(2, $thresholds);

        $bpm = $thresholds[0];
        $this->assertEquals('bpm', $bpm['parameter_id']);
        $this->assertEquals(60, $bpm['effective_normal_min']);
        $this->assertEquals(100, $bpm['effective_normal_max']);
        $this->assertNull($bpm['custom_normal_min']);
    }

    public function testGetThresholdsForPatientReturnsCustomOverrides(): void
    {
        $this->pdo->exec("INSERT INTO patient_alert_threshold (id_patient, parameter_id, normal_min, normal_max, critical_min, critical_max) VALUES (1, 'bpm', 50, 120, 30, 150)");

        $thresholds = $this->repository->getThresholdsForPatient(1);
        $bpm = array_filter($thresholds, fn($t) => $t['parameter_id'] === 'bpm');
        $bpm = array_values($bpm)[0];

        $this->assertEquals(50, $bpm['effective_normal_min']);
        $this->assertEquals(120, $bpm['effective_normal_max']);
        $this->assertEquals(50, $bpm['custom_normal_min']);
    }

    public function testSaveThresholdInsertsNew(): void
    {
        $result = $this->repository->saveThreshold(1, 'bpm', 50.0, 120.0, 30.0, 150.0, 1);
        $this->assertTrue($result);

        $effective = $this->repository->getEffectiveThreshold(1, 'bpm');
        $this->assertNotNull($effective);
        $this->assertEquals(50, $effective['normal_min']);
        $this->assertEquals(120, $effective['normal_max']);
    }

    public function testResetThresholdDeletesCustom(): void
    {
        $this->repository->saveThreshold(1, 'bpm', 50.0, 120.0, null, null);
        $result = $this->repository->resetThreshold(1, 'bpm');
        $this->assertTrue($result);

        $effective = $this->repository->getEffectiveThreshold(1, 'bpm');
        $this->assertEquals(60, $effective['normal_min']); // Back to default
    }

    public function testResetAllThresholds(): void
    {
        $this->repository->saveThreshold(1, 'bpm', 50.0, 120.0, null, null);
        $this->repository->saveThreshold(1, 'spo2', 90.0, 100.0, null, null);

        $result = $this->repository->resetAllThresholds(1);
        $this->assertTrue($result);

        $bpmEff = $this->repository->getEffectiveThreshold(1, 'bpm');
        $this->assertEquals(60, $bpmEff['normal_min']); // Back to default
    }

    public function testGetEffectiveThresholdReturnsNullForUnknownParam(): void
    {
        $result = $this->repository->getEffectiveThreshold(1, 'unknown_param');
        $this->assertNull($result);
    }

    public function testGetEffectiveThresholdReturnsDefaultsWhenNoCustom(): void
    {
        $result = $this->repository->getEffectiveThreshold(1, 'bpm');
        $this->assertNotNull($result);
        $this->assertEquals(60, $result['normal_min']);
        $this->assertEquals(100, $result['normal_max']);
        $this->assertEquals(40, $result['critical_min']);
        $this->assertEquals(140, $result['critical_max']);
    }

    public function testSaveThresholdWithNullValues(): void
    {
        $result = $this->repository->saveThreshold(1, 'bpm', null, null, null, null);
        $this->assertTrue($result);

        $effective = $this->repository->getEffectiveThreshold(1, 'bpm');
        // Should fall back to defaults since custom is null
        $this->assertEquals(60, $effective['normal_min']);
    }
}
