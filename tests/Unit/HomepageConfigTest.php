<?php

namespace Tests\Unit;

use App\SystemConfig\HomepageConfig;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HomepageConfigTest extends TestCase
{
    public function test_defaults_use_expected_tagline(): void
    {
        $config = HomepageConfig::defaults();

        $this->assertSame(HomepageConfig::DEFAULT_TAGLINE, $config->tagline);
        $this->assertSame(['tagline' => HomepageConfig::DEFAULT_TAGLINE], $config->toArray());
    }

    public function test_from_validated_builds_typed_config(): void
    {
        $config = HomepageConfig::fromValidated([
            'tagline' => '測試文字',
        ]);

        $this->assertSame('測試文字', $config->tagline);
    }

    public function test_rules_reject_empty_tagline(): void
    {
        $validator = Validator::make(
            ['tagline' => ''],
            HomepageConfig::rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('tagline', $validator->errors()->toArray());
    }

    public function test_rules_accept_valid_tagline(): void
    {
        $validator = Validator::make(
            ['tagline' => '有效文字'],
            HomepageConfig::rules(),
        );

        $this->assertFalse($validator->fails());
        $this->assertSame(['tagline' => '有效文字'], $validator->validated());
    }
}
