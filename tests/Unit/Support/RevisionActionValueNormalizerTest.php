<?php

namespace Tests\Unit\Support;

use App\Enums\RevisionActionType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\RevisionActionValueNormalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RevisionActionValueNormalizerTest extends TestCase
{
    use DatabaseTransactions;

    private RevisionActionValueNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = app(RevisionActionValueNormalizer::class);
    }

    public function test_normalize_returns_null_when_value_missing_or_null(): void
    {
        $this->assertNull($this->normalizer->normalize(['action' => RevisionActionType::CreateVertex->value]));
        $this->assertNull($this->normalizer->normalize([
            'action' => RevisionActionType::CreateVertexProperty->value,
            'age_property_name' => 'genres',
            'value' => null,
        ]));
    }

    public function test_normalize_sorts_enum_values_by_definition_order(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => true],
            ['value' => 'pop', 'label' => '流行', 'active' => true],
        ])->create([
            'age_property_name' => 'genres',
        ]);

        $normalized = $this->normalizer->normalize([
            'action' => RevisionActionType::CreateVertexProperty->value,
            'age_property_name' => 'genres',
            'value' => ['pop', 'rock'],
        ]);

        $this->assertSame(['rock', 'pop'], $normalized);
    }

    public function test_normalize_sorts_edge_enum_values_by_definition_order(): void
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->enum([
            ['value' => 'lead', 'label' => '主唱', 'active' => true],
            ['value' => 'guest', 'label' => '客串', 'active' => true],
        ])->create([
            'age_property_name' => 'roles',
        ]);

        $normalized = $this->normalizer->normalize([
            'action' => RevisionActionType::CreateEdgeProperty->value,
            'age_property_name' => 'roles',
            'value' => ['guest', 'lead'],
        ]);

        $this->assertSame(['lead', 'guest'], $normalized);
    }

    public function test_normalize_leaves_invalid_enum_shape_unchanged(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->enum()->create([
            'age_property_name' => 'genres',
        ]);

        $this->assertSame([], $this->normalizer->normalize([
            'action' => RevisionActionType::CreateVertexProperty->value,
            'age_property_name' => 'genres',
            'value' => [],
        ]));
        $this->assertSame('rock', $this->normalizer->normalize([
            'action' => RevisionActionType::CreateVertexProperty->value,
            'age_property_name' => 'genres',
            'value' => 'rock',
        ]));
    }

    public function test_normalize_casts_integer_property_values(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'year',
            'age_property_type' => \App\Enums\PropertyType::Integer,
        ]);

        $this->assertSame(1999, $this->normalizer->normalize([
            'action' => RevisionActionType::CreateVertexProperty,
            'age_property_name' => 'year',
            'value' => '1999',
        ]));
    }

    public function test_normalize_returns_raw_value_when_property_unknown(): void
    {
        $this->assertSame(['x'], $this->normalizer->normalize([
            'action' => RevisionActionType::CreateVertexProperty->value,
            'age_property_name' => 'missing_prop_'.uniqid(),
            'value' => ['x'],
        ]));
    }
}
