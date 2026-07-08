<?php

namespace Tests\Unit\Support;

use App\Enums\PropertyType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\VertexDisplayNameResolver;
use Tests\TestCase;

class VertexDisplayNameResolverTest extends TestCase
{
    private VertexDisplayNameResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new VertexDisplayNameResolver;
    }

    public function test_returns_empty_string_when_show_property_name_is_null(): void
    {
        $this->assertSame('', $this->resolver->resolve(null, [], collect()));
    }

    public function test_resolves_non_localized_property(): void
    {
        $definitions = collect([
            $this->makeDefinition('birth_year', null, '出生年份'),
        ]);

        $this->assertSame(
            '701',
            $this->resolver->resolve('birth_year', ['birth_year' => 701], $definitions),
        );
    }

    public function test_resolves_localized_base_name_using_display_locale(): void
    {
        $definitions = collect([
            $this->makeDefinition('name_zh_tw', 'zh_tw', '姓名'),
            $this->makeDefinition('name_en_us', 'en_us', 'Name'),
        ]);

        $this->assertSame(
            '李白',
            $this->resolver->resolve('name', [
                'name_zh_tw' => '李白',
                'name_en_us' => 'Li Bai',
            ], $definitions),
        );
    }

    public function test_falls_back_when_primary_locale_value_is_empty(): void
    {
        $definitions = collect([
            $this->makeDefinition('name_zh_tw', 'zh_tw', '姓名'),
            $this->makeDefinition('name_en_us', 'en_us', 'Name'),
        ]);

        $this->assertSame(
            'Li Bai',
            $this->resolver->resolve('name', [
                'name_zh_tw' => '',
                'name_en_us' => 'Li Bai',
            ], $definitions),
        );
    }

    public function test_supports_legacy_suffixed_show_property_name(): void
    {
        $definitions = collect([
            $this->makeDefinition('name_zh_tw', 'zh_tw', '姓名'),
        ]);

        $this->assertSame(
            '李白',
            $this->resolver->resolve('name_zh_tw', ['name_zh_tw' => '李白'], $definitions),
        );
    }

    public function test_prefers_non_localized_exact_match_over_localized_base_name(): void
    {
        $definitions = collect([
            $this->makeDefinition('name', null, '名稱'),
            $this->makeDefinition('name_zh_tw', 'zh_tw', '姓名'),
        ]);

        $this->assertSame(
            '單一語言名稱',
            $this->resolver->resolve('name', [
                'name' => '單一語言名稱',
                'name_zh_tw' => '李白',
            ], $definitions),
        );
    }

    public function test_returns_empty_string_when_base_name_has_no_matching_localized_properties(): void
    {
        $definitions = collect([
            $this->makeDefinition('title', null, '標題'),
        ]);

        $this->assertSame('', $this->resolver->resolve('name', ['title' => 'ignored'], $definitions));
    }

    public function test_uses_explicit_locale_parameter_over_config(): void
    {
        $definitions = collect([
            $this->makeDefinition('name_zh_tw', 'zh_tw', '姓名'),
            $this->makeDefinition('name_en_us', 'en_us', 'Name'),
        ]);

        $this->assertSame(
            'Li Bai',
            $this->resolver->resolve('name', [
                'name_zh_tw' => '李白',
                'name_en_us' => 'Li Bai',
            ], $definitions, 'en_us'),
        );
    }

    private function makeDefinition(string $agePropertyName, ?string $locale, string $name): VertexProperty
    {
        $vertexType = VertexType::factory()->make();

        return VertexProperty::factory()->for($vertexType)->make([
            'name' => $name,
            'age_property_name' => $agePropertyName,
            'locale' => $locale,
            'age_property_type' => PropertyType::String,
        ]);
    }
}
