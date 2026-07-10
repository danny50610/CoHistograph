<?php

namespace Tests\Unit\Rules\GraphSchema;

use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Rules\GraphSchema\ValidShowPropertyName;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidShowPropertyNameTest extends TestCase
{
    use DatabaseTransactions;

    public function test_allows_null_value(): void
    {
        $vertexType = $this->makeVertexTypeWithProperties();

        $validator = Validator::make(
            ['show_property_name' => null],
            ['show_property_name' => ['nullable', 'string', ValidShowPropertyName::forVertexType($vertexType)]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_allows_non_localized_property_name(): void
    {
        $vertexType = $this->makeVertexTypeWithProperties();

        $validator = Validator::make(
            ['show_property_name' => 'birth_year'],
            ['show_property_name' => ['nullable', 'string', ValidShowPropertyName::forVertexType($vertexType)]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_allows_localized_base_name(): void
    {
        $vertexType = $this->makeVertexTypeWithProperties();

        $validator = Validator::make(
            ['show_property_name' => 'name'],
            ['show_property_name' => ['nullable', 'string', ValidShowPropertyName::forVertexType($vertexType)]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_allows_legacy_suffixed_property_name(): void
    {
        $vertexType = $this->makeVertexTypeWithProperties();

        $validator = Validator::make(
            ['show_property_name' => 'name_zh_tw'],
            ['show_property_name' => ['nullable', 'string', ValidShowPropertyName::forVertexType($vertexType)]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_rejects_unknown_property_name(): void
    {
        $vertexType = $this->makeVertexTypeWithProperties();

        $validator = Validator::make(
            ['show_property_name' => 'unknown'],
            ['show_property_name' => ['nullable', 'string', ValidShowPropertyName::forVertexType($vertexType)]],
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('show_property_name', $validator->errors()->toArray());
    }

    private function makeVertexTypeWithProperties(): VertexType
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_en_us',
            'locale' => 'en_us',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'birth_year',
            'locale' => null,
        ]);

        return $vertexType->fresh('properties');
    }
}
