<?php

namespace Tests\Unit\Support;

use App\Enums\RevisionActionType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\RevisionAction;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\LocalizedPropertyLabelResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LocalizedPropertyLabelResolverTest extends TestCase
{
    use DatabaseTransactions;

    private LocalizedPropertyLabelResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new LocalizedPropertyLabelResolver;
    }

    public function test_formats_localized_age_property_name_with_locale_label(): void
    {
        $vertexType = VertexType::factory()->create();
        $property = VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $formatted = $this->resolver->formatAgePropertyName('name_zh_tw', [$property]);

        $this->assertSame('name_zh_tw（繁體中文）', $formatted);
    }

    public function test_formats_action_using_referenced_vertex_type_label(): void
    {
        $vertexType = VertexType::factory()->create(['age_label_name' => 'person']);
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $createVertexAction = new RevisionAction([
            'order' => 0,
            'action' => RevisionActionType::CreateVertex,
            'vertex_type_label' => 'person',
        ]);

        $propertyAction = new RevisionAction([
            'order' => 1,
            'action' => RevisionActionType::CreateVertexProperty,
            'target_ref_order' => 0,
            'age_property_name' => 'name_zh_tw',
        ]);

        $formatted = $this->resolver->formatForAction(
            $propertyAction,
            collect([$createVertexAction, $propertyAction]),
            collect([$vertexType->load('properties')]),
            collect(),
        );

        $this->assertSame('name_zh_tw（繁體中文）', $formatted);
    }

    public function test_formats_action_using_referenced_edge_type_label(): void
    {
        $edgeType = EdgeType::factory()->create(['age_label_name' => 'performs']);
        EdgeProperty::factory()->for($edgeType)->create([
            'age_property_name' => 'role_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $createEdgeAction = new RevisionAction([
            'order' => 0,
            'action' => RevisionActionType::CreateEdge,
            'edge_type_label' => 'performs',
        ]);

        $propertyAction = new RevisionAction([
            'order' => 1,
            'action' => RevisionActionType::CreateEdgeProperty,
            'target_ref_order' => 0,
            'age_property_name' => 'role_zh_tw',
        ]);

        $formatted = $this->resolver->formatForAction(
            $propertyAction,
            collect([$createEdgeAction, $propertyAction]),
            collect(),
            collect([$edgeType->load('properties')]),
        );

        $this->assertSame('role_zh_tw（繁體中文）', $formatted);
    }
}
