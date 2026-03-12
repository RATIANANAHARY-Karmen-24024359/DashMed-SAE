<?php

declare(strict_types=1);

namespace Tests\Models\Repositories;

use PHPUnit\Framework\TestCase;
use modules\models\repositories\AlertRepository;
use PDO;

class AlertRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AlertRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("CREATE TABLE parameter_reference (
            parameter_id TEXT PRIMARY KEY,
            display_name TEXT,
            unit TEXT,
            normal_min REAL,
            normal_max REAL,
            critical_min REAL,
            critical_max REAL
        )");

        $this->pdo->exec("CREATE TABLE patient_data (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_patient INTEGER,
            parameter_id TEXT,
            value REAL,
            timestamp TEXT,
            archived INTEGER DEFAULT 0,
            alert_flag INTEGER DEFAULT 0
        )");

        $this->pdo->exec("CREATE TABLE patient_alert_threshold (
            id_patient INTEGER,
            parameter_id TEXT,
            normal_min REAL,
            normal_max REAL,
            critical_min REAL,
            critical_max REAL,
            updated_by INTEGER,
            updated_at TEXT,
            PRIMARY KEY (id_patient, parameter_id)
        )");

        $this->pdo->exec("INSERT INTO parameter_reference VALUES ('bpm', 'Fréquence cardiaque', 'bpm', 60, 100, 40, 140)");
        $this->pdo->exec("INSERT INTO parameter_reference VALUES ('spo2', 'SpO2', '%', 95, 100, 90, NULL)");

        $this->repository = new AlertRepository($this->pdo);
    }

    public function testGetOutOfThresholdAlertsReturnsEmptyWhenNoData(): void
    {
        $alerts = $this->repository->getOutOfThresholdAlerts(1);
        $this->assertEmpty($alerts);
    }

    public function testGetOutOfThresholdAlertsReturnsEmptyWhenNormal(): void
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'bpm', 75, '2024-01-01 12:00:00', 0)");

        $alerts = $this->repository->getOutOfThresholdAlerts(1);
        $this->assertEmpty($alerts);
    }

    public function testGetOutOfThresholdAlertsDetectsHighValue(): void
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'bpm', 110, '2024-01-01 12:00:00', 0)");

        $alerts = $this->repository->getOutOfThresholdAlerts(1);
        $this->assertCount(1, $alerts);
        $this->assertEquals('bpm', $alerts[0]->parameterId);
        $this->assertTrue($alerts[0]->isAboveMax);
    }

    public function testGetOutOfThresholdAlertsDetectsLowValue(): void
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'spo2', 88, '2024-01-01 12:00:00', 0)");

        $alerts = $this->repository->getOutOfThresholdAlerts(1);
        $this->assertCount(1, $alerts);
        $this->assertEquals('spo2', $alerts[0]->parameterId);
        $this->assertTrue($alerts[0]->isBelowMin);
    }

    public function testGetOutOfThresholdAlertsIgnoresArchivedData(): void
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'bpm', 150, '2024-01-01 12:00:00', 1)");

        $alerts = $this->repository->getOutOfThresholdAlerts(1);
        $this->assertEmpty($alerts);
    }

    public function testGetOutOfThresholdAlertsUsesCustomThresholds(): void
    {
        $this->pdo->exec("INSERT INTO patient_alert_threshold VALUES (1, 'bpm', 50, 120, 30, 150, NULL, NULL)");
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'bpm', 110, '2024-01-01 12:00:00', 0)");

        $alerts = $this->repository->getOutOfThresholdAlerts(1);
        $this->assertEmpty($alerts); // 110 is within custom range 50-120
    }

    public function testHasAlertsReturnsFalseWhenNoAlerts(): void
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'bpm', 75, '2024-01-01 12:00:00', 0)");
        $this->assertFalse($this->repository->hasAlerts(1));
    }

    public function testHasAlertsReturnsTrueWhenAlertsExist(): void
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'bpm', 110, '2024-01-01 12:00:00', 0)");
        $this->assertTrue($this->repository->hasAlerts(1));
    }

    public function testGetOutOfThresholdAlertsDetectsCritical(): void
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'bpm', 35, '2024-01-01 12:00:00', 0)");

        $alerts = $this->repository->getOutOfThresholdAlerts(1);
        $this->assertCount(1, $alerts);
        $this->assertTrue($alerts[0]->isCritical);
    }

    public function testGetOutOfThresholdAlertsUsesLatestTimestamp(): void
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'bpm', 110, '2024-01-01 11:00:00', 0)");
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp, archived) VALUES (1, 'bpm', 75, '2024-01-01 12:00:00', 0)");

        $alerts = $this->repository->getOutOfThresholdAlerts(1);
        $this->assertEmpty($alerts); // Latest value (75) is normal
    }
}
