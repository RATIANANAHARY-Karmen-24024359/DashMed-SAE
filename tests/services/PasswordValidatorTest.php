<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use modules\services\PasswordValidator;

class PasswordValidatorTest extends TestCase
{
    public function testValidPasswordReturnsNoErrors(): void
    {
        $errors = PasswordValidator::validate('MyStr0ng!Pass');
        $this->assertEmpty($errors);
    }

    public function testIsValidReturnsTrueForValidPassword(): void
    {
        $this->assertTrue(PasswordValidator::isValid('MyStr0ng!Pass'));
    }

    public function testIsValidReturnsFalseForInvalidPassword(): void
    {
        $this->assertFalse(PasswordValidator::isValid('short'));
    }

    public function testTooShortPassword(): void
    {
        $errors = PasswordValidator::validate('Ab1!');
        $this->assertNotEmpty($errors);
        $this->assertTrue($this->containsSubstring($errors, '12 caractères'));
    }

    public function testMissingUppercase(): void
    {
        $errors = PasswordValidator::validate('mystrongpass1!');
        $this->assertNotEmpty($errors);
        $this->assertTrue($this->containsSubstring($errors, 'majuscule'));
    }

    public function testMissingLowercase(): void
    {
        $errors = PasswordValidator::validate('MYSTRONGPASS1!');
        $this->assertNotEmpty($errors);
        $this->assertTrue($this->containsSubstring($errors, 'minuscule'));
    }

    public function testMissingDigit(): void
    {
        $errors = PasswordValidator::validate('MyStrongPass!!');
        $this->assertNotEmpty($errors);
        $this->assertTrue($this->containsSubstring($errors, 'chiffre'));
    }

    public function testMissingSpecialCharacter(): void
    {
        $errors = PasswordValidator::validate('MyStrongPass12');
        $this->assertNotEmpty($errors);
        $this->assertTrue($this->containsSubstring($errors, 'spécial'));
    }

    public function testBreachedPasswordIsRejected(): void
    {
        $errors = PasswordValidator::validate('Password1234');
        $this->assertNotEmpty($errors);
        $this->assertTrue($this->containsSubstring($errors, 'compromis'));
    }

    public function testBreachedPasswordCaseInsensitive(): void
    {
        $errors = PasswordValidator::validate('password1234');
        $this->assertTrue($this->containsSubstring($errors, 'compromis'));
    }

    public function testFormatErrorsSingleError(): void
    {
        $result = PasswordValidator::formatErrors(['Erreur unique.']);
        $this->assertEquals('Erreur unique.', $result);
    }

    public function testFormatErrorsMultipleErrors(): void
    {
        $result = PasswordValidator::formatErrors(['Erreur 1.', 'Erreur 2.']);
        $this->assertStringContainsString('critères de sécurité', $result);
        $this->assertStringContainsString('Erreur 1.', $result);
        $this->assertStringContainsString('Erreur 2.', $result);
    }

    public function testFormatErrorsEmpty(): void
    {
        $this->assertEquals('', PasswordValidator::formatErrors([]));
    }

    public function testGetPolicyDescription(): void
    {
        $desc = PasswordValidator::getPolicyDescription();
        $this->assertStringContainsString('12', $desc);
        $this->assertStringContainsString('majuscule', $desc);
    }

    private function containsSubstring(array $errors, string $needle): bool
    {
        foreach ($errors as $e) {
            if (str_contains($e, $needle)) {
                return true;
            }
        }
        return false;
    }
}
