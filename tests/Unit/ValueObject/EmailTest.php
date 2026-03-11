<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\ValueObject;

use Cortex\ValueObject\Email;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cortex\ValueObject\Email
 */
class EmailTest extends TestCase
{
    // =======================================================================
    // VALID EMAIL TESTS
    // =======================================================================

    /** @dataProvider validEmailsProvider */
    public function testValidEmails(string $email): void
    {
        $emailObject = new Email($email);

        $this->assertEquals($email, $emailObject->value);
    }

    public static function validEmailsProvider(): array
    {
        return [
            'simple' => ['test@example.com'],
            'with subdomain' => ['user@mail.example.com'],
            'with plus' => ['user+tag@example.com'],
            'with dot in local' => ['first.last@example.com'],
            'with numbers' => ['user123@example123.com'],
            'with hyphen domain' => ['user@my-domain.com'],
            'short domain' => ['a@b.co'],
            'long local' => ['verylongemailaddresspart@example.com'],
            'mixed case' => ['User@Example.COM'],
        ];
    }

    // =======================================================================
    // INVALID EMAIL TESTS
    // =======================================================================

    /** @dataProvider invalidEmailsProvider */
    public function testInvalidEmailsThrow(string $email): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        new Email($email);
    }

    public static function invalidEmailsProvider(): array
    {
        return [
            'no at sign' => ['invalid-email'],
            'no domain' => ['user@'],
            'no local part' => ['@example.com'],
            'spaces' => ['user @example.com'],
            'double at' => ['user@@example.com'],
            'empty string' => [''],
            'just domain' => ['example.com'],
            'missing tld' => ['user@domain'],
        ];
    }

    // =======================================================================
    // VALUE OBJECT BEHAVIOR TESTS
    // =======================================================================

    public function testToString(): void
    {
        $email = new Email('test@example.com');

        $this->assertEquals('test@example.com', (string) $email);
    }

    public function testEquals(): void
    {
        $email1 = new Email('test@example.com');
        $email2 = new Email('test@example.com');
        $email3 = new Email('other@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }

    public function testInvoke(): void
    {
        $email = new Email('test@example.com');

        $this->assertEquals('test@example.com', $email());
    }

    public function testValueIsReadOnly(): void
    {
        $email = new Email('test@example.com');

        $reflection = new \ReflectionProperty($email, 'value');
        $this->assertTrue($reflection->isReadOnly());
    }

    // =======================================================================
    // EDGE CASES
    // =======================================================================

    public function testEmailWithUnicode(): void
    {
        // Unicode in domain is typically not valid for FILTER_VALIDATE_EMAIL
        $this->expectException(\InvalidArgumentException::class);

        new Email('user@例え.jp');
    }

    public function testEmailPreservesCase(): void
    {
        $email = new Email('User@Example.COM');

        $this->assertEquals('User@Example.COM', $email->value);
    }
}
