<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use modules\models\repositories\ConsultationRepository;
use modules\models\entities\Consultation;
use PDO;

/**
 * Class ConsultationRepositoryTest
 *
 * Tests for consultation management CRUD.
 *
 * @package Tests\Models
 * @author DashMed Team
 */
class ConsultationRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ConsultationRepository $consultationModel;

    /**
     * Setup.
     */
    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            call_user_func([$this->pdo, 'sqliteCreateFunction'], 'NOW', function () {
                return date('Y-m-d H:i:s');
            });
            call_user_func([$this->pdo, 'sqliteCreateFunction'], 'CURDATE', function () {
                return date('Y-m-d');
            });
        }


        $this->pdo->exec("CREATE TABLE consultations (
            id_consultations INTEGER PRIMARY KEY AUTOINCREMENT,
            id_patient INTEGER,
            id_user INTEGER,
            date TEXT,
            type TEXT,
            note TEXT,
            title TEXT,
            updated_at TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE view_consultations (
            id_consultations INTEGER,
            id_user INTEGER,
            id_patient INTEGER,
            last_name TEXT,
            date TEXT,
            title TEXT,
            type TEXT,
            note TEXT
        )");

        $this->consultationModel = new ConsultationRepository($this->pdo);
    }

    /**
     * Test creating a consultation.
     */
    public function testCreateConsultation(): void
    {
        $result = $this->consultationModel->createConsultation(
            1,
            2,
            '2023-10-10 10:00:00',
            'Checkup',
            'Tout va bien',
            'Consultation 1'
        );
        $this->assertTrue($result);


        $stmt = $this->pdo->prepare("SELECT * FROM consultations WHERE id_patient = 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $this->assertEquals('Consultation 1', $row['title'] ?? '');
            $this->assertEquals('Checkup', $row['type'] ?? '');
        } else {
            $this->fail('No row found in consultations table');
        }
    }

    /**
     * Test retrieving consultations by patient.
     */
    public function testGetConsultationsByPatientId(): void
    {
        $this->pdo->exec(
            "INSERT INTO view_consultations (
                                id_consultations,
                                id_user,
                                id_patient,
                                last_name,
                                date,
                                title,
                                type,
                                note
                                )
            VALUES (10, 5, 1, 'Dr. Strange', '2023-10-10 10:00:00', 'Magic check', 'Magic', 'Strange things')"
        );

        $consultations = $this->consultationModel->getConsultationsByPatientId(1);
        $this->assertCount(1, $consultations);
        $this->assertInstanceOf(Consultation::class , $consultations[0]);
        $this->assertEquals('Dr. Strange', $consultations[0]->getDoctor());
        $this->assertEquals('Magic check', $consultations[0]->getTitle());
    }

    /**
     * Test updating a consultation.
     */
    public function testUpdateConsultation(): void
    {
        $this->pdo->exec(
            "INSERT INTO consultations (id_consultations, id_patient, id_user, date, type, note, title)
            VALUES (1, 1, 2, '2023-01-01', 'Old', 'Note', 'Title')
            "
        );

        $result = $this->consultationModel->updateConsultation(
            1,
            3,
            '2023-02-02',
            'New',
            'New Note',
            'New Title'
        );
        $this->assertTrue($result);

        $stmt = $this->pdo->prepare("SELECT * FROM consultations WHERE id_consultationS = 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $this->assertEquals('New Title', $row['title'] ?? '');
            $this->assertEquals(3, $row['id_user'] ?? 0);
        } else {
            $this->fail('No row found in consultations table');
        }
    }

    /**
     * Test deleting a consultation.
     */
    public function testDeleteConsultation(): void
    {
        $this->pdo->exec("INSERT INTO consultations (id_consultations, id_patient, id_user) VALUES (1, 1, 2)");
        $this->assertTrue($this->consultationModel->deleteConsultation(1));

        $stmt = $this->pdo->prepare("SELECT count(*) FROM consultations WHERE id_consultations = 1");
        $stmt->execute();
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}