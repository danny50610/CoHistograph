<?php

namespace Tests\Unit\Rules\GraphSchema;

use App\Rules\GraphSchema\AgeLabelName;
use PHPUnit\Framework\TestCase;

class AgeLabelNameTest extends TestCase
{
    private AgeLabelName $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new AgeLabelName;
    }

    public function test_passes_with_valid_lowercase_alphanumeric_and_underscore(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', 'valid_label_123', $fail);

        $this->assertFalse($failCalled);
    }

    public function test_passes_with_all_lowercase_letters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', 'label', $fail);

        $this->assertFalse($failCalled);
    }

    public function test_passes_with_all_numbers(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', '12345', $fail);

        $this->assertFalse($failCalled);
    }

    public function test_passes_with_all_underscores(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', '___', $fail);

        $this->assertFalse($failCalled);
    }

    public function test_fails_with_uppercase_letters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', 'LabelName', $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_with_special_characters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', 'label-name', $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_with_spaces(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', 'label name', $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_when_exceeds_32_characters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $longString = str_repeat('a', 33);
        $this->rule->validate('label_name', $longString, $fail);

        $this->assertTrue($failCalled);
    }

    public function test_passes_with_exactly_32_characters(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $longString = str_repeat('a', 32);
        $this->rule->validate('label_name', $longString, $fail);

        $this->assertFalse($failCalled);
    }

    public function test_fails_with_non_string_value(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', 123, $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_with_array_value(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', ['label'], $fail);

        $this->assertTrue($failCalled);
    }

    public function test_fails_with_null_value(): void
    {
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        $this->rule->validate('label_name', null, $fail);

        $this->assertTrue($failCalled);
    }
}
