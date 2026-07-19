<?php

namespace Tests\Unit\Rules\GraphSchema;

use App\Rules\GraphSchema\AgePropertyName;
use PHPUnit\Framework\TestCase;

class AgePropertyNameTest extends TestCase
{
    private AgePropertyName $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new AgePropertyName;
    }

    public function test_passes_with_valid_lowercase_alphanumeric_and_underscore(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', 'valid_property_123', $fail);

        $this->assertFalse($failCalled);
    }

    public function test_passes_with_all_lowercase_letters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', 'property', $fail);

        $this->assertFalse($failCalled);
    }

    public function test_passes_with_all_numbers(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', '12345', $fail);

        $this->assertFalse($failCalled);
    }

    public function test_passes_with_all_underscores(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', '___', $fail);

        $this->assertFalse($failCalled);
    }

    public function test_fails_with_uppercase_letters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', 'PropertyName', $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_with_special_characters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', 'property-name', $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_with_spaces(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', 'property name', $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_when_exceeds_64_characters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $longString = str_repeat('a', 65);
        $this->rule->validate('property_name', $longString, $fail);

        $this->assertTrue($failCalled);
    }

    public function test_passes_with_exactly_64_characters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $longString = str_repeat('a', 64);
        $this->rule->validate('property_name', $longString, $fail);

        $this->assertFalse($failCalled);
    }

    public function test_fails_with_non_string_value(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', 123, $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_with_array_value(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', ['property'], $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_with_null_value(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', null, $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_with_cypher_reserved_word(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('property_name', 'in', $fail);

        $this->assertTrue($failCalled);
    }
}
