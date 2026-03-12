<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use modules\services\PatientContextService;
use modules\models\repositories\PatientRepository;

class PatientContextServiceTest extends TestCase
{
    private PatientContextService $service;
    private PatientRepository $patientRepoMock;

    protected function setUp(): void
    {
        $this->patientRepoMock = $this->createMock(PatientRepository::class);
        $this->service = new PatientContextService($this->patientRepoMock);
        $_COOKIE = [];
        $_GET = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        $_GET = [];
        $_REQUEST = [];
    }

    public function testGetCurrentRoomIdReturnsNullWhenNoCookie(): void
    {
        $this->assertNull($this->service->getCurrentRoomId());
    }

    public function testGetCurrentRoomIdReturnsCookieValue(): void
    {
        $_COOKIE['room_id'] = '5';
        $this->assertEquals(5, $this->service->getCurrentRoomId());
    }

    public function testGetCurrentRoomIdReturnsNullForNonNumeric(): void
    {
        $_COOKIE['room_id'] = 'abc';
        $this->assertNull($this->service->getCurrentRoomId());
    }

    public function testGetCurrentPatientIdFromRequest(): void
    {
        $_REQUEST['id_patient'] = '42';
        $patientId = $this->service->getCurrentPatientId();
        $this->assertEquals(42, $patientId);
    }

    public function testGetCurrentPatientIdFromRoom(): void
    {
        $_COOKIE['room_id'] = '3';

        $this->patientRepoMock->expects($this->once())
            ->method('getPatientIdByRoom')
            ->with(3)
            ->willReturn(99);

        $this->assertEquals(99, $this->service->getCurrentPatientId());
    }

    public function testGetCurrentPatientIdDefaultsToOne(): void
    {
        $this->patientRepoMock->method('getPatientIdByRoom')->willReturn(null);
        $this->assertEquals(1, $this->service->getCurrentPatientId());
    }

    public function testGetCurrentPatientIdIgnoresNonDigitRequest(): void
    {
        $_REQUEST['id_patient'] = 'abc';
        $_COOKIE['room_id'] = '2';

        $this->patientRepoMock->expects($this->once())
            ->method('getPatientIdByRoom')
            ->with(2)
            ->willReturn(10);

        $this->assertEquals(10, $this->service->getCurrentPatientId());
    }
}
