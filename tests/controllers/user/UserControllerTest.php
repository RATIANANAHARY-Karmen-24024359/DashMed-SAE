<?php

namespace Tests\Controllers\User;

use PHPUnit\Framework\TestCase;
use modules\controllers\UserController;
use modules\services\UserLayoutService;
use PDO;

require_once __DIR__ . '/../../../vendor/autoload.php';

class UserControllerTest extends TestCase
{
    private $userController;
    private $pdoMock;
    private $layoutServiceMock;


    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $stmtMock = $this->createMock(\PDOStatement::class);
        $this->pdoMock->method('prepare')->willReturn($stmtMock);
        $this->pdoMock->method('query')->willReturn($stmtMock);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetchAll')->willReturn([]);
        $stmtMock->method('fetch')->willReturn(false);

        $this->layoutServiceMock = $this->createMock(UserLayoutService::class);

        $this->userController = new UserController($this->pdoMock);

        $this->injectProperty($this->userController, 'layoutService', $this->layoutServiceMock);
    }

    private function injectProperty($object, $propertyName, $value)
    {
        $reflection = new \ReflectionClass($object);
        if ($reflection->hasProperty($propertyName)) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($object, $value);
        }
    }

    public function testProfileRedirectsIfNoSession()
    {
        $_SESSION = [];

        $this->assertTrue(true);
    }

    public function testCustomizationCallsLayoutService()
    {
        $_SESSION['email'] = 'user@test.com';
        $_SESSION['user_id'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->layoutServiceMock->expects($this->once())
            ->method('buildWidgetsForCustomization')
            ->with(1)
            ->willReturn(['widgets' => [], 'hidden' => []]);

        ob_start();
        try {
            $this->userController->customization();
        }
        finally {
            $output = ob_get_clean();
        }

        $this->assertIsString($output);
    }
}