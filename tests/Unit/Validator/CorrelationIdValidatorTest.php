<?php

declare(strict_types=1);

namespace MdavidDev\SymfonyCorrelationIdBundle\Tests\Unit\Validator;

use MdavidDev\SymfonyCorrelationIdBundle\Validator\CorrelationIdValidator;
use PHPUnit\Framework\TestCase;

class CorrelationIdValidatorTest extends TestCase
{
    public function testValidationDisabled(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: false,
            maxLength: 255,
            pattern: null
        );

        $this->assertTrue($validator->isValid('anything'));
        $this->assertTrue($validator->isValid('very-long-' . str_repeat('x', 1000)));
        $this->assertTrue($validator->isValid('with-special-chars-!@#$%'));
    }

    public function testNullIsInvalid(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 255,
            pattern: null
        );

        $this->assertFalse($validator->isValid(null));
    }

    public function testEmptyStringIsInvalid(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 255,
            pattern: null
        );

        $this->assertFalse($validator->isValid(''));
    }

    public function testMaxLengthValidation(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 10,
            pattern: null
        );

        $this->assertTrue($validator->isValid('123456789'));
        $this->assertTrue($validator->isValid('1234567890'));
        $this->assertFalse($validator->isValid('12345678901'));
    }

    public function testPatternValidation(): void
    {
        // Pattern: lowercase letters and dashes only
        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 255,
            pattern: '/^[a-z-]+$/'
        );

        $this->assertTrue($validator->isValid('valid-id'));
        $this->assertTrue($validator->isValid('another-valid-one'));
        $this->assertFalse($validator->isValid('Invalid-ID'));
        $this->assertFalse($validator->isValid('invalid_id'));
        $this->assertFalse($validator->isValid('invalid123'));
    }

    public function testUuidV4Pattern(): void
    {
        // UUID v4 pattern
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 255,
            pattern: $pattern
        );

        $this->assertTrue($validator->isValid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertTrue($validator->isValid('f47ac10b-58cc-4372-a567-0e02b2c3d479'));

        $this->assertFalse($validator->isValid('not-a-uuid'));
        $this->assertFalse($validator->isValid('550e8400-e29b-11d4-a716-446655440000')); // UUID v1
    }

    public function testCombinedValidation(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 20,
            pattern: '/^[A-Z0-9-]+$/'
        );

        $this->assertTrue($validator->isValid('VALID-ID-123'));
        $this->assertFalse($validator->isValid('TOO-LONG-ID-123456789'));
        $this->assertFalse($validator->isValid('invalid-lowercase'));
    }

    public function testSanitizeValidId(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 255,
            pattern: null
        );

        $this->assertSame('valid-id', $validator->sanitize('  valid-id  '));
        $this->assertSame('another-id', $validator->sanitize('another-id'));
    }

    public function testSanitizeInvalidId(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 10,
            pattern: null
        );

        $this->assertNull($validator->sanitize('this-is-too-long'));

        $this->assertNull($validator->sanitize(null));

        $this->assertNull($validator->sanitize(''));
    }

    public function testSanitizeWithPattern(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 255,
            pattern: '/^[a-z0-9-]+$/'
        );

        $this->assertSame('valid-id-123', $validator->sanitize('  valid-id-123  '));
        $this->assertNull($validator->sanitize('Invalid-ID'));
    }

    public function testSanitizeWhenValidationDisabled(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: false,
            maxLength: 5,
            pattern: '/^[a-z]+$/'
        );

        $this->assertSame('ANYTHING-GOES-123', $validator->sanitize('  ANYTHING-GOES-123  '));
    }

    public function testMultibyteStringLength(): void
    {
        $validator = new CorrelationIdValidator(
            enabled: true,
            maxLength: 10,
            pattern: null
        );

        $this->assertTrue($validator->isValid('émoji🎉'));
        $this->assertFalse($validator->isValid('émoji🎉test123'));
    }
}
