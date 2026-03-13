<?php

namespace controllers\api;

use Exception;
use modules\controllers\api\SearchController;
use modules\models\repositories\SearchRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once __DIR__ . '/../../../app/controllers/api/SearchController.php';
require_once __DIR__ . '/../../../app/models/repositories/SearchRepository.php';

require_once __DIR__ . '/../../mocks/controllers/TestableSearchController.php';

/**
 * Class SearchControllerTest
 *
 * Unit tests for the search API.
 *
 * @package Tests\Controllers\Api
 * @author DashMed Team
 */
class SearchControllerTest extends TestCase
{
    private $searchModelMock;

    /**
     * Setup test session.
     */
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = [];
        $_GET = [];
    }

    /**
     * Teardown cleanup.
     */
    protected function tearDown(): void
    {
        $_GET = [];
        $_SESSION = [];
    }

    private function createController(): TestableSearchController
    {
        $this->searchModelMock = $this->createMock(SearchRepository::class);
        $controller = new TestableSearchController();
        $controller->setSearchRepository($this->searchModelMock);
        return $controller;
    }

    /**
     * Verify 401 unauthorized.
     */
    public function testGetReturns401IfNotLoggedIn(): void
    {
        $controller = $this->createController();
        $controller->get();

        $this->assertEquals(401, $controller->responseStatus);
        $this->assertEquals(['error' => 'Non autorisé'], $controller->responseData);
    }

    /**
     * Verify empty results for short query.
     */
    public function testGetReturnsEmptyIfQueryTooShort(): void
    {
        $_SESSION['email'] = 'user@example.com';
        $_GET['q'] = 'a';

        $controller = $this->createController();
        $controller->get();

        $this->assertEquals(200, $controller->responseStatus);
        $this->assertEquals(['results' => []], $controller->responseData);
    }

    /**
     * Verify empty results if no query.
     */
    public function testGetReturnsEmptyIfNoQuery(): void
    {
        $_SESSION['email'] = 'user@example.com';

        $controller = $this->createController();
        $controller->get();

        $this->assertEquals(200, $controller->responseStatus);
        $this->assertEquals(['results' => []], $controller->responseData);
    }

    /**
     * Verify successful search.
     */
    public function testGetPerformsSearchAndReturnsResults(): void
    {
        $_SESSION['email'] = 'user@example.com';
        $_GET['q'] = 'dupont';
        $_GET['patient_id'] = '10';

        $expectedResults = [
            'patients' => [['id_patient' => 1, 'first_name' => 'Jean', 'last_name' => 'Dupont']],
            'doctors' => [],
            'consultations' => []
        ];

        $this->searchModelMock = $this->createMock(SearchRepository::class);
        $this->searchModelMock->expects($this->once())
            ->method('searchGlobal')
            ->with('dupont', 5, 10)
            ->willReturn($expectedResults);

        $controller = new TestableSearchController();
        $controller->setSearchRepository($this->searchModelMock);
        $controller->get();

        $this->assertEquals(200, $controller->responseStatus);
        $this->assertEquals(['results' => $expectedResults], $controller->responseData);
    }

    /**
     * Verify search without patient ID.
     */
    public function testGetPerformsSearchWithoutPatientId(): void
    {
        $_SESSION['email'] = 'user@example.com';
        $_GET['q'] = 'martin';

        $expectedResults = [
            'patients' => [],
            'doctors' => [['id_user' => 5, 'first_name' => 'Marie', 'last_name' => 'Martin']],
            'consultations' => []
        ];

        $this->searchModelMock = $this->createMock(SearchRepository::class);
        $this->searchModelMock->expects($this->once())
            ->method('searchGlobal')
            ->with('martin', 5, null)
            ->willReturn($expectedResults);

        $controller = new TestableSearchController();
        $controller->setSearchRepository($this->searchModelMock);
        $controller->get();

        $this->assertEquals(200, $controller->responseStatus);
        $this->assertEquals(['results' => $expectedResults], $controller->responseData);
    }

    /**
     * Verify 500 error on exception.
     */
    public function testGetHandlesModelException(): void
    {
        $_SESSION['email'] = 'user@example.com';
        $_GET['q'] = 'crash';

        $this->searchModelMock = $this->createMock(SearchRepository::class);
        $this->searchModelMock->expects($this->once())
            ->method('searchGlobal')
            ->willThrowException(new Exception('DB failure'));

        $controller = new TestableSearchController();
        $controller->setSearchRepository($this->searchModelMock);
        $controller->get();

        $this->assertEquals(500, $controller->responseStatus);
        $this->assertEquals(['error' => 'Erreur Serveur'], $controller->responseData);
    }

    /**
     * Verify whitespace trimming.
     */
    public function testGetTrimsQueryWhitespace(): void
    {
        $_SESSION['email'] = 'user@example.com';
        $_GET['q'] = '   test   ';

        $expectedResults = ['patients' => [], 'doctors' => [], 'consultations' => []];

        $this->searchModelMock = $this->createMock(SearchRepository::class);
        $this->searchModelMock->expects($this->once())
            ->method('searchGlobal')
            ->with('test', 5, null)
            ->willReturn($expectedResults);

        $controller = new TestableSearchController();
        $controller->setSearchRepository($this->searchModelMock);
        $controller->get();

        $this->assertEquals(200, $controller->responseStatus);
    }

    /**
     * Verify invalid patient ID ignored.
     */
    public function testGetIgnoresNonNumericPatientId(): void
    {
        $_SESSION['email'] = 'user@example.com';
        $_GET['q'] = 'test';
        $_GET['patient_id'] = 'invalid';

        $expectedResults = ['patients' => [], 'doctors' => [], 'consultations' => []];

        $this->searchModelMock = $this->createMock(SearchRepository::class);
        $this->searchModelMock->expects($this->once())
            ->method('searchGlobal')
            ->with('test', 5, null)
            ->willReturn($expectedResults);

        $controller = new TestableSearchController();
        $controller->setSearchRepository($this->searchModelMock);
        $controller->get();

        $this->assertEquals(200, $controller->responseStatus);
    }
}
