<?php

declare(strict_types=1);

namespace modules\services;

/**
 * Class SecurityService
 *
 * OWASP Top 10 2025 compliant security service.
 *
 * Provides security hardening for the entire application:
 *
 * - A01:2025 - Broken Access Control → Session validation, CSRF protection
 * - A02:2025 - Security Misconfiguration → Secure headers, session config
 * - A04:2025 - Cryptographic Failures → Secure session cookies, HTTPS enforcement
 * - A05:2025 - Injection → Input sanitization helpers
 * - A06:2025 - Insecure Design → Rate limiting, account lockout
 * - A07:2025 - Authentication Failures → Session fixation prevention, secure cookies
 * - A08:2025 - Software or Data Integrity Failures → CSP headers, SRI
 * - A09:2025 - Security Logging and Alerting Failures → Security event logging
 * - A10:2025 - Mishandling of Exceptional Conditions → Graceful error handling
 *
 * @package DashMed\Modules\Services
 * @author  DashMed Team
 * @license Proprietary
 */
class SecurityService
{
    /**
     * @var int Maximum login attempts before lockout
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * @var int Lockout duration in seconds (15 minutes)
     */
    private const LOCKOUT_DURATION = 900;

    /**
     * @var int Session timeout in seconds (30 minutes)
     */
    private const SESSION_TIMEOUT = 1800;

    /**
     * Configures session with secure settings.
     *
     * Must be called BEFORE session_start().
     * Addresses: A02 (Security Misconfiguration), A04 (Cryptographic Failures), A07 (Authentication Failures)
     *
     * @return void
     */
    public static function configureSecureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $serverPort = $_SERVER['SERVER_PORT'] ?? null;
        $serverPort = is_numeric($serverPort) ? (int) $serverPort : 0;
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $serverPort === 443;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', (string) self::SESSION_TIMEOUT);

        if ($isHttps) {
            ini_set('session.cookie_secure', '1');
        }

        session_set_cookie_params(
            [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
            ]
        );
    }

    /**
     * Sends security HTTP headers.
     *
     * Addresses: A02 (Security Misconfiguration), A08 (Software/Data Integrity)
     *
     * @return void
     */
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: DENY');

        header('X-Content-Type-Options: nosniff');

        header('X-XSS-Protection: 1; mode=block');

        header('Referrer-Policy: strict-origin-when-cross-origin');

        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

        header(
            "Content-Security-Policy: "
            . "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "img-src 'self' data:; "
            . "connect-src 'self' https://cdn.jsdelivr.net; "
            . "frame-ancestors 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self';"
        );

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /**
     * Validates and regenerates session to prevent fixation attacks.
     *
     * Addresses: A07 (Authentication Failures)
     *
     * @return void
     */
    public static function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_last_regeneration'] = time();
        }
    }

    /**
     * Checks if the session has expired due to inactivity.
     *
     * Addresses: A07 (Authentication Failures)
     *
     * @return bool True if session is still valid
     */
    public static function checkSessionTimeout(): bool
    {
        if (!isset($_SESSION['_last_activity'])) {
            $_SESSION['_last_activity'] = time();
            return true;
        }

        $lastActivityRaw = $_SESSION['_last_activity'];
        $lastActivity = is_numeric($lastActivityRaw) ? (int) $lastActivityRaw : 0;
        if ((time() - $lastActivity) > self::SESSION_TIMEOUT) {
            self::destroySession();
            return false;
        }

        $_SESSION['_last_activity'] = time();
        return true;
    }

    /**
     * Safely destroys the session.
     *
     * @return void
     */
    public static function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $cookieName = session_name();
            if ($cookieName === false) {
                $cookieName = 'PHPSESSID';
            }
            $domain = $params['domain'];
            $path = $params['path'];
            $secure = $params['secure'];
            $httpOnly = $params['httponly'];
            setcookie(
                $cookieName,
                '',
                time() - 42000,
                $path,
                $domain,
                $secure,
                $httpOnly
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Generates a CSRF token and stores it in the session.
     *
     * Addresses: A01 (Broken Access Control)
     *
     * @return string The CSRF token
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    /**
     * Validates a CSRF token against the session.
     *
     * Addresses: A01 (Broken Access Control)
     *
     * @param  string $token Token from the form
     * @return bool True if the token is valid
     */
    public static function validateCsrfToken(string $token): bool
    {
        $sessionToken = $_SESSION['_csrf'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '' || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Checks if a login attempt is rate-limited (account lockout).
     *
     * Addresses: A07 (Authentication Failures), A06 (Insecure Design)
     *
     * @param  string $identifier Email or IP address
     * @return bool True if the attempt is allowed, false if locked out
     */
    public static function checkLoginRateLimit(string $identifier): bool
    {
        $key = 'login_attempts_' . md5($identifier);
        $lockKey = 'login_lockout_' . md5($identifier);

        if (isset($_SESSION[$lockKey])) {
            $lockoutRaw = $_SESSION[$lockKey] ?? null;
            $lockoutTime = is_numeric($lockoutRaw) ? (int) $lockoutRaw : 0;
            if (time() < $lockoutTime) {
                $remaining = $lockoutTime - time();
                self::logSecurityEvent(
                    'LOGIN_LOCKED',
                    "Account locked for {$identifier}. {$remaining}s remaining."
                );
                return false;
            }
            unset($_SESSION[$lockKey], $_SESSION[$key]);
        }

        return true;
    }

    /**
     * Records a failed login attempt and checks for lockout.
     *
     * Addresses: A07 (Authentication Failures), A09 (Logging)
     *
     * @param  string $identifier Email or IP address
     * @return int Remaining attempts before lockout
     */
    public static function recordFailedLogin(string $identifier): int
    {
        $key = 'login_attempts_' . md5($identifier);
        $lockKey = 'login_lockout_' . md5($identifier);

        $attemptsRaw = $_SESSION[$key] ?? null;
        $attempts = is_numeric($attemptsRaw) ? (int) $attemptsRaw : 0;
        $attempts++;
        $_SESSION[$key] = $attempts;

        self::logSecurityEvent(
            'LOGIN_FAILED',
            "Failed login attempt #{$attempts} for {$identifier} from IP " . self::getClientIp()
        );

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$lockKey] = time() + self::LOCKOUT_DURATION;
            self::logSecurityEvent(
                'LOGIN_LOCKOUT',
                "Account locked for {$identifier} after {$attempts} failed attempts."
            );
            return 0;
        }

        return self::MAX_LOGIN_ATTEMPTS - $attempts;
    }

    /**
     * Resets the login attempt counter after a successful login.
     *
     * @param  string $identifier Email or IP address
     * @return void
     */
    public static function resetLoginAttempts(string $identifier): void
    {
        $key = 'login_attempts_' . md5($identifier);
        $lockKey = 'login_lockout_' . md5($identifier);
        unset($_SESSION[$key], $_SESSION[$lockKey]);
    }

    /**
     * Returns the remaining lockout time in seconds.
     *
     * @param  string $identifier Email or IP address
     * @return int Seconds remaining, 0 if not locked
     */
    public static function getLockoutRemaining(string $identifier): int
    {
        $lockKey = 'login_lockout_' . md5($identifier);
        if (!isset($_SESSION[$lockKey])) {
            return 0;
        }

        $lockoutRaw = $_SESSION[$lockKey] ?? null;
        $lockoutTime = is_numeric($lockoutRaw) ? (int) $lockoutRaw : 0;
        $remaining = $lockoutTime - time();
        return max(0, $remaining);
    }

    /**
     * Logs a security event.
     *
     * Addresses: A09 (Security Logging and Alerting Failures)
     *
     * @param  string $event   Event type (e.g., LOGIN_FAILED, CSRF_MISMATCH)
     * @param  string $details Additional context
     * @return void
     */
    public static function logSecurityEvent(string $event, string $details = ''): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $ip = self::getClientIp();
        $userId = $_SESSION['user_id'] ?? 'anonymous';
        $userIdStr = is_scalar($userId) ? (string) $userId : 'anonymous';

        $logMessage = "[SECURITY][{$timestamp}][{$event}] "
            . "IP={$ip} User={$userIdStr} {$details}";

        error_log($logMessage);
    }

    /**
     * Gets the client IP address safely.
     *
     * @return string Client IP address
     */
    public static function getClientIp(): string
    {
        // Do NOT trust X-Forwarded-For blindly (A02: Security Misconfiguration)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($ip) ? $ip : '0.0.0.0';
    }

    /**
     * Sanitizes output for safe HTML display.
     *
     * Addresses: A05 (Injection)
     *
     * @param  mixed $value Value to sanitize
     * @return string Sanitized string
     */
    public static function escapeHtml(mixed $value): string
    {
        return htmlspecialchars(
            is_scalar($value) ? (string) $value : '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * Validates that a request is from an allowed HTTP method.
     *
     * Addresses: A01 (Broken Access Control)
     *
     * @param  array<int, string> $allowedMethods Allowed HTTP methods
     * @return bool True if the method is allowed
     */
    public static function validateHttpMethod(array $allowedMethods): bool
    {
        $methodRaw = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method = strtoupper(is_string($methodRaw) ? $methodRaw : 'GET');
        return in_array($method, $allowedMethods, true);
    }
}
