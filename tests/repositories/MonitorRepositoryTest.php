<?php

namespace Tests\Models\Monitoring;

use PHPUnit\Framework\TestCase;
use modules\models\repositories\MonitorRepository;
use PDO;

/**
 * Class MonitorRepositoryTest
 *
 * Tests for raw monitoring data retrieval.
 *
 * @package Tests\Models\Monitoring
 * @author DashMed Team
 */
class MonitorRepositoryTest extends TestCase
{
    private PDO $pdo;
    private MonitorRepository $monitorModel;

    /**
     * Setup.
     */
    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE patient_data (
            seq INTEGER PRIMARY KEY AUTOINCREMENT,
            id_patient INTEGER,
            parameter_id TEXT,
            value REAL,
            timestamp TEXT,
            alert_flag INTEGER DEFAULT 0,
            archived INTEGER DEFAULT 0
        )");

        $this->pdo->exec("CREATE TABLE parameter_reference (
            parameter_id INTEGER PRIMARY KEY,
            display_name TEXT,
            category TEXT,
            unit TEXT,
            description TEXT,
            normal_min REAL,
            normal_max REAL,
            critical_min REAL,
            critical_max REAL,
            display_min REAL,
            display_max REAL,
            default_chart TEXT
        )");

        $this->pdo->exec("CREATE TABLE parameter_chart_allowed (
            parameter_id INTEGER,
            chart_type TEXT
        )");

        $this->pdo->exec("CREATE TABLE chart_types (
            chart_type TEXT PRIMARY KEY,
            label TEXT
        )");

        $this->monitorModel = new MonitorRepository($this->pdo, 'patient_data');

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            call_user_func([$this->pdo, 'sqliteCreateFunction'], 'UNIX_TIMESTAMP', function ($timestamp) {
                return strtotime($timestamp);
            }, 1);
            call_user_func([$this->pdo, 'sqliteCreateFunction'], 'FROM_UNIXTIME', function ($unix) {
                return date('Y-m-d H:i:s', (int)$unix);
            }, 1);
        }

        $this->pdo->exec(
            "INSERT INTO parameter_reference (parameter_id, display_name, category, default_chart)
            VALUES (1, 'BPM', 'Vitals', 'line')"
        );

        $this->pdo->exec("INSERT INTO parameter_chart_allowed (parameter_id, chart_type) VALUES (1, 'line')");
        $this->pdo->exec("INSERT INTO parameter_chart_allowed (parameter_id, chart_type) VALUES (1, 'bar')");
    }

    /**
     * Test retrieval of raw history data.
     */
    public function testGetRawHistory()
    {
        $this->pdo->exec(
            "INSERT INTO patient_data (id_patient, parameter_id, value, timestamp)
            VALUES (1, 1, 80, '2023-01-01 10:00:00')"
        );
        $this->pdo->exec(
            "INSERT INTO patient_data (id_patient, parameter_id, value, timestamp)
            VALUES (1, 1, 85, '2023-01-01 10:05:00')"
        );

        $history = $this->monitorModel->getRawHistory(1);
        $this->assertCount(2, $history);
        $this->assertEquals(80.0, (float)$history[0]['value']);
        $this->assertEquals(85.0, (float)$history[1]['value']);

        $historyLimit = $this->monitorModel->getRawHistory(1, 1);
        $this->assertCount(1, $historyLimit);
        $this->assertEquals(85.0, (float)$historyLimit[0]['value']);
    }

    public function testGetLatestHistoryForAllParameters()
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'BPM', 70, '2023-01-01 09:00:00')");
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'BPM', 75, '2023-01-01 09:10:00')");
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'BPM', 80, '2023-01-01 09:20:00')");

        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'SpO2', 98, '2023-01-01 09:00:00')");
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'SpO2', 99, '2023-01-01 09:10:00')");

        $history = $this->monitorModel->getLatestHistoryForAllParameters(1, 2);
        $this->assertCount(4, $history);

        $bpm = array_filter($history, fn($h) => $h['parameter_id'] === 'BPM');
        $this->assertCount(2, $bpm);
        $this->assertEquals(75.0, (float)reset($bpm)['value']);
        $this->assertEquals(80.0, (float)end($bpm)['value']);
    }

    /**
     * Test retrieval of available chart types.
     */
    public function testGetTailHistoryByParameter()
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'BPM', 70, '2023-01-01 09:00:00')");
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'BPM', 75, '2023-01-01 09:10:00')");
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'BPM', 80, '2023-01-01 09:20:00')");

        $tail = $this->monitorModel->getTailHistoryByParameter(1, 'BPM', 2);
        $this->assertCount(2, $tail);
        $this->assertEquals(75.0, (float)$tail[0]['value']);
        $this->assertEquals(80.0, (float)$tail[1]['value']);
    }

    public function testGetHistoryChunkAfter()
    {
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'BPM', 70, '2023-01-01 09:00:00')");
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'BPM', 75, '2023-01-01 09:10:00')");
        $this->pdo->exec("INSERT INTO patient_data (id_patient, parameter_id, value, timestamp) VALUES (1, 'BPM', 80, '2023-01-01 09:20:00')");

        $chunk = $this->monitorModel->getHistoryChunkAfter(1, 'BPM', '2023-01-01 09:00:00', 10);
        $this->assertCount(2, $chunk);
        $this->assertEquals(75.0, (float)$chunk[0]['value']);
        $this->assertEquals(80.0, (float)$chunk[1]['value']);

        $chunk2 = $this->monitorModel->getHistoryChunkAfterSeq(1, 'BPM', 1, 10);
        $this->assertCount(2, $chunk2);
        $this->assertEquals(75.0, (float)$chunk2[0]['value']);
        $this->assertEquals(80.0, (float)$chunk2[1]['value']);
    }

    public function testGetAllChartTypes()
    {
        $this->pdo->exec("INSERT INTO chart_types (chart_type, label) VALUES ('line', 'Ligne')");
        $this->pdo->exec("INSERT INTO chart_types (chart_type, label) VALUES ('bar', 'Barre')");

        $types = $this->monitorModel->getAllChartTypes();
        $this->assertCount(2, $types);
        $this->assertArrayHasKey('line', $types);
        $this->assertEquals('Ligne', $types['line']);
    }

    /**
     * Test SQL pre-aggregation logic.
     * Skipped on SQLite: uses MySQL-specific FLOOR/UNIX_TIMESTAMP/FROM_UNIXTIME.
     */
    public function testStreamPreAggregatedHistory()
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->markTestSkipped('streamPreAggregatedHistory uses MySQL-specific SQL (FLOOR, UNIX_TIMESTAMP).');
        }

        for ($i = 0; $i < 6; $i++) {
            $seconds = str_pad((string)($i * 10), 2, '0', STR_PAD_LEFT);
            $time = "2023-01-01 10:00:$seconds";
            $this->pdo->exec(
                "INSERT INTO patient_data (id_patient, parameter_id, value, timestamp)
                VALUES (1, 'BPM', 100, '$time')"
            );
        }

        $generator = $this->monitorModel->streamPreAggregatedHistoryByParameter(1, 'BPM', 30);
        $results = iterator_to_array($generator);

        $this->assertCount(2, $results);
        $this->assertEquals(100.0, (float)$results[0]['value']);
        $this->assertEquals(100.0, (float)$results[1]['value']);
    }
}