<?php

namespace Tests\Unit\Rules\GraphSchema;

use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Rules\GraphSchema\LocaleMutualExclusion;
use App\Rules\GraphSchema\UniqueResolvedAgePropertyName;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LocaleMutualExclusionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_passes_when_no_conflicting_properties_exist(): void
    {
        $vertexType = VertexType::factory()->create();

        $this->assertValidationPasses(
            new LocaleMutualExclusion(VertexProperty::class, 'vertex_type_id', $vertexType->id, null),
            'name',
        );

        $this->assertValidationPasses(
            new LocaleMutualExclusion(VertexProperty::class, 'vertex_type_id', $vertexType->id, 'zh_tw'),
            'name',
        );
    }

    public function test_fails_when_non_localized_conflicts_with_existing_localized_property(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->assertValidationFails(
            new LocaleMutualExclusion(VertexProperty::class, 'vertex_type_id', $vertexType->id, null),
            'name',
            '已存在多語系版本的同名屬性，無法建立非多語系版本',
        );
    }

    public function test_fails_when_localized_conflicts_with_existing_non_localized_property(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $this->assertValidationFails(
            new LocaleMutualExclusion(VertexProperty::class, 'vertex_type_id', $vertexType->id, 'zh_tw'),
            'name',
            '已存在非多語系版本的同名屬性，無法建立多語系版本',
        );
    }

    public function test_passes_when_localized_property_uses_different_base_name(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $this->assertValidationPasses(
            new LocaleMutualExclusion(VertexProperty::class, 'vertex_type_id', $vertexType->id, 'zh_tw'),
            'title',
        );
    }

    public function test_unique_resolved_age_property_name_fails_when_name_exists(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->assertValidationFails(
            new UniqueResolvedAgePropertyName(VertexProperty::class, 'vertex_type_id', $vertexType->id),
            'name_zh_tw',
            'name_zh_tw 已被使用',
        );
    }

    public function test_unique_resolved_age_property_name_passes_when_name_is_available(): void
    {
        $vertexType = VertexType::factory()->create();

        $this->assertValidationPasses(
            new UniqueResolvedAgePropertyName(VertexProperty::class, 'vertex_type_id', $vertexType->id),
            'name_zh_tw',
        );
    }

    private function assertValidationPasses(object $rule, string $value): void
    {
        $failed = false;
        $rule->validate('attribute', $value, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    private function assertValidationFails(object $rule, string $value, string $expectedMessage): void
    {
        $message = null;
        $rule->validate('attribute', $value, function (string $msg) use (&$message) {
            $message = $msg;
        });

        $this->assertSame($expectedMessage, $message);
    }
}
