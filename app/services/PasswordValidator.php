<?php

declare(strict_types=1);

namespace modules\services;

/**
 * Class PasswordValidator
 *
 * Centralized password validation service compliant with OWASP Top 10 2025.
 *
 * Enforces strong password policies:
 * - Minimum 12 characters (A07:2025 - Authentication Failures)
 * - At least 1 uppercase letter
 * - At least 1 digit
 * - At least 1 special character
 * - Breached password check against common passwords (A04:2025 - Cryptographic Failures)
 *
 * @package DashMed\Modules\Services
 * @author  DashMed Team
 * @license Proprietary
 */
class PasswordValidator
{
    /**
     * @var int Minimum password length
     */
    private const MIN_LENGTH = 12;

    /**
     * @var array<int, string> List of commonly breached passwords to reject
     */
    private const BREACHED_PASSWORDS = [
        'password1234',
        'motdepasse12',
        'qwertyuiop12',
        'azertyuiop12',
        '123456789012',
        'password1234!',
        'Password1234',
        'Admin1234567',
        'Welcome12345',
        'Changeme1234',
        'Passw0rd1234',
        'P@ssword1234',
        'Password123!',
        'Qwerty123456',
        'Azerty123456',
        'Abc123456789',
        'Test12345678',
        'Admin12345678',
        'Root12345678',
        'User12345678',
    ];

    /**
     * Validates a password against all security rules.
     *
     * Returns an array of error messages. Empty array means the password is valid.
     *
     * @param  string $password The password to validate
     * @return array<int, string> List of validation error messages (empty if valid)
     */
    public static function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = "Le mot de passe doit contenir au moins " . self::MIN_LENGTH . " caractères.";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une lettre majuscule.";
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une lettre minuscule.";
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un chiffre.";
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un caractère spécial (!@#\$%^&*...).";
        }

        if (self::isBreached($password)) {
            $errors[] = "Ce mot de passe est trop courant et a été compromis. Choisissez-en un autre.";
        }

        return $errors;
    }

    /**
     * Checks whether a password is valid (no errors).
     *
     * @param  string $password The password to check
     * @return bool True if valid, false otherwise
     */
    public static function isValid(string $password): bool
    {
        return empty(self::validate($password));
    }

    /**
     * Returns a formatted error message string from validation errors.
     *
     * @param  array<int, string> $errors Validation errors
     * @return string Formatted error message
     */
    public static function formatErrors(array $errors): string
    {
        if (empty($errors)) {
            return '';
        }

        if (count($errors) === 1) {
            return $errors[0];
        }

        return "Le mot de passe ne respecte pas les critères de sécurité : " . implode(' ', $errors);
    }

    /**
     * Checks if the password is in the list of commonly breached passwords.
     *
     * Uses case-insensitive comparison to catch variations.
     *
     * @param  string $password The password to check
     * @return bool True if the password is breached
     */
    private static function isBreached(string $password): bool
    {
        $lowerPassword = strtolower($password);
        foreach (self::BREACHED_PASSWORDS as $breached) {
            if (strtolower($breached) === $lowerPassword) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the password policy description for display in forms.
     *
     * @return string Human-readable password policy
     */
    public static function getPolicyDescription(): string
    {
        return "Le mot de passe doit contenir au moins " . self::MIN_LENGTH
            . " caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.";
    }
}
