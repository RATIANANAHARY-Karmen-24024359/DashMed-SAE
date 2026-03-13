<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use modules\services\SecurityService;

class SecurityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_SESSION = [];
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_SERVER = [];
    }

    public function testGenerateCsrfTokenReturnsEmptyWhenNoSession(): void
    {
        $token = SecurityService::generateCsrfToken();
        $this->assertEquals('', $token);
    }

    public function testValidateCsrfTokenReturnsFalseForEmptyToken(): void
    {
        $this->assertFalse(SecurityService::validateCsrfToken(''));
    }

    public function testValidateCsrfTokenReturnsFalseWhenNoSessionToken(): void
    {
        $_SESSION['_csrf'] = '';
        $this->assertFalse(SecurityService::validateCsrfToken('some-token'));
    }

    public function testValidateCsrfTokenReturnsTrueForMatchingToken(): void
    {
        $_SESSION['_csrf'] = 'abc123';
        $this->assertTrue(SecurityService::validateCsrfToken('abc123'));
    }

    public function testValidateCsrfTokenReturnsFalseForMismatch(): void
    {
        $_SESSION['_csrf'] = 'abc123';
        $this->assertFalse(SecurityService::validateCsrfToken('wrong'));
    }

    public function testCheckSessionTimeoutInitializesLastActivity(): void
    {
        $result = SecurityService::checkSessionTimeout();
        $this->assertTrue($result);
        $this->assertArrayHasKey('_last_activity', $_SESSION);
    }

    public function testCheckSessionTimeoutReturnsTrueForRecentActivity(): void
    {
        $_SESSION['_last_activity'] = time() - 10;
        $this->assertTrue(SecurityService::checkSessionTimeout());
    }

    public function testCheckLoginRateLimitAllowsWhenNotLocked(): void
    {
        $this->assertTrue(SecurityService::checkLoginRateLimit('test@test.com'));
    }

    public function testCheckLoginRateLimitBlocksWhenLocked(): void
    {
        $key = 'login_lockout_' . md5('test@test.com');
        $_SESSION[$key] = time() + 600;
        $this->assertFalse(SecurityService::checkLoginRateLimit('test@test.com'));
    }

    public function testCheckLoginRateLimitUnlocksAfterExpiry(): void
    {
        $key = 'login_lockout_' . md5('test@test.com');
        $_SESSION[$key] = time() - 1;
        $this->assertTrue(SecurityService::checkLoginRateLimit('test@test.com'));
    }

    public function testRecordFailedLoginDecrementsAttempts(): void
    {
        $remaining = SecurityService::recordFailedLogin('test@test.com');
        $this->assertEquals(4, $remaining);
    }

    public function testRecordFailedLoginLocksAfterMaxAttempts(): void
    {
        for ($i = 0; $i < 4; $i++) {
            SecurityService::recordFailedLogin('lock@test.com');
        }
        $remaining = SecurityService::recordFailedLogin('lock@test.com');
        $this->assertEquals(0, $remaining);
    }

    public function testResetLoginAttemptsClearsState(): void
    {
        SecurityService::recordFailedLogin('reset@test.com');
        SecurityService::resetLoginAttempts('reset@test.com');

        $key = 'login_attempts_' . md5('reset@test.com');
        $this->assertArrayNotHasKey($key, $_SESSION);
    }

    public function testGetLockoutRemainingReturnsZeroWhenNotLocked(): void
    {
        $this->assertEquals(0, SecurityService::getLockoutRemaining('nobody@test.com'));
    }

    public function testGetLockoutRemainingReturnsPositiveWhenLocked(): void
    {
        $key = 'login_lockout_' . md5('locked@test.com');
        $_SESSION[$key] = time() + 300;
        $remaining = SecurityService::getLockoutRemaining('locked@test.com');
        $this->assertGreaterThan(0, $remaining);
    }

    public function testEscapeHtmlEscapesSpecialChars(): void
    {
        $this->assertEquals('&lt;script&gt;', SecurityService::escapeHtml('<script>'));
    }

    public function testEscapeHtmlHandlesNonScalar(): void
    {
        $this->assertEquals('', SecurityService::escapeHtml(null));
        $this->assertEquals('', SecurityService::escapeHtml([]));
    }

    public function testGetClientIpReturnsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $this->assertEquals('192.168.1.1', SecurityService::getClientIp());
    }

    public function testGetClientIpReturnsFallback(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $this->assertEquals('0.0.0.0', SecurityService::getClientIp());
    }

    public function testValidateHttpMethodAcceptsAllowed(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(SecurityService::validateHttpMethod(['GET', 'POST']));
    }

    public function testValidateHttpMethodRejectsDisallowed(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->assertFalse(SecurityService::validateHttpMethod(['GET', 'POST']));
    }

    public function testDestroySessionClearsData(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['email'] = 'test@test.com';
        SecurityService::destroySession();
        $this->assertEmpty($_SESSION);
    }
}
