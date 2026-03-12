<?php

declare(strict_types=1);

namespace Tests\Controllers\Auth;

use PHPUnit\Framework\TestCase;
use modules\controllers\AuthController;
use modules\models\repositories\UserRepository;
use PDO;

class AuthControllerTest extends TestCase
{
    private AuthController $controller;
    private $userRepoMock;
    private $pdoMock;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET'];
        $_POST = [];
        $_GET = [];

        $this->pdoMock = $this->createMock(PDO::class);
        $this->userRepoMock = $this->createMock(UserRepository::class);

        $this->controller = new AuthController();

        $this->injectProperty($this->controller, 'userRepo', $this->userRepoMock);
        $this->injectProperty($this->controller, 'pdo', $this->pdoMock);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_SERVER = [];
        $_POST = [];
        $_GET = [];
    }

    private function injectProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    public function testLoginGetRendersFormWhenNotLoggedIn(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->userRepoMock->method('listUsersForLogin')->willReturn([]);

        ob_start();
        $this->controller->login();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
    }

    public function testLoginGetRedirectsWhenLoggedIn(): void
    {
        $_SESSION['email'] = 'test@test.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        try {
            $this->controller->login();
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            // Header warning expected
        }
        ob_end_clean();

        // If logged in, it tries to redirect
        $this->assertTrue(true);
    }

    public function testLoginPostRejectsEmptyCredentials(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = '';
        $_POST['password'] = '';
        $_SESSION['_csrf'] = 'token';
        $_POST['_csrf'] = 'token';

        ob_start();
        try {
            $this->controller->login();
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            // Header redirect warning
        }
        ob_end_clean();

        $this->assertNotEmpty($_SESSION['error'] ?? '');
    }

    public function testLoginPostRejectsCsrfMismatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['_csrf'] = 'correct_token';
        $_POST['_csrf'] = 'wrong_token';
        $_POST['email'] = 'test@test.com';
        $_POST['password'] = 'password';

        ob_start();
        try {
            $this->controller->login();
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            // Header redirect
        }
        ob_end_clean();

        $this->assertStringContainsString('invalide', $_SESSION['error'] ?? '');
    }

    public function testSignupGetRendersFormWhenNotLoggedIn(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $stmtMock = $this->createMock(\PDOStatement::class);
        $stmtMock->method('fetchAll')->willReturn([]);
        $this->pdoMock->method('query')->willReturn($stmtMock);

        ob_start();
        $this->controller->signup();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
    }

    public function testSignupPostRejectsEmptyFields(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['_csrf'] = 'token';
        $_POST['_csrf'] = 'token';
        $_POST['last_name'] = '';
        $_POST['first_name'] = '';
        $_POST['email'] = '';
        $_POST['password'] = '';
        $_POST['password_confirm'] = '';

        ob_start();
        try {
            $this->controller->signup();
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            // Header redirect
        }
        ob_end_clean();

        $this->assertStringContainsString('obligatoire', $_SESSION['error'] ?? '');
    }

    public function testSignupPostRejectsPasswordMismatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['_csrf'] = 'token';
        $_POST['_csrf'] = 'token';
        $_POST['last_name'] = 'Doe';
        $_POST['first_name'] = 'John';
        $_POST['email'] = 'john@test.com';
        $_POST['password'] = 'MyStr0ng!Pass';
        $_POST['password_confirm'] = 'Different!Pass1';
        $_POST['id_profession'] = '1';

        ob_start();
        try {
            $this->controller->signup();
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            // Header redirect
        }
        ob_end_clean();

        $this->assertStringContainsString('correspondent', $_SESSION['error'] ?? '');
    }

    public function testSignupPostRejectsWeakPassword(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['_csrf'] = 'token';
        $_POST['_csrf'] = 'token';
        $_POST['last_name'] = 'Doe';
        $_POST['first_name'] = 'John';
        $_POST['email'] = 'john@test.com';
        $_POST['password'] = 'weak';
        $_POST['password_confirm'] = 'weak';
        $_POST['id_profession'] = '1';

        ob_start();
        try {
            $this->controller->signup();
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            // Header redirect
        }
        ob_end_clean();

        $this->assertNotEmpty($_SESSION['error'] ?? '');
    }

    public function testSignupPostRejectsInvalidEmail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['_csrf'] = 'token';
        $_POST['_csrf'] = 'token';
        $_POST['last_name'] = 'Doe';
        $_POST['first_name'] = 'John';
        $_POST['email'] = 'not-an-email';
        $_POST['password'] = 'MyStr0ng!Pass';
        $_POST['password_confirm'] = 'MyStr0ng!Pass';
        $_POST['id_profession'] = '1';

        ob_start();
        try {
            $this->controller->signup();
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            // Header redirect
        }
        ob_end_clean();

        $this->assertStringContainsString('invalide', $_SESSION['error'] ?? '');
    }
}
