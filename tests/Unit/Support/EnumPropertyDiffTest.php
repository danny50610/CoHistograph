<?php

namespace Tests\Unit\Support;

use App\Enums\PropertyType;
use App\Enums\RevisionActionType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\RevisionAction;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Services\Revision\AgeGraphStateManager;
use App\Support\EnumPropertyDiff;
use App\Support\PropertyValueCaster;
use Mockery;
use Tests\TestCase;

class EnumPropertyDiffTest extends TestCase
{
    private AgeGraphStateManager $graphManager;

    private EnumPropertyDiff $diff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphManager = Mockery::mock(AgeGraphStateManager::class);
        $this->diff = new EnumPropertyDiff($this->graphManager, new PropertyValueCaster);
    }

    public function test_for_action_returns_null_for_non_property_actions(): void
    {
        $action = new RevisionAction([
            'action' => RevisionActionType::CreateVertex,
            'vertex_type_label' => 'person',
        ]);

        $this->assertNull($this->diff->forAction($action, collect(), [], []));
    }

    public function test_for_action_returns_null_when_property_is_not_enum(): void
    {
        [$vertexType, $actions] = $this->vertexCreateRefSetup(
            PropertyType::String,
            null,
            ['rock'],
        );

        $propertyAction = $actions->last();

        $this->assertNull($this->diff->forAction($propertyAction, $actions, [$vertexType], []));
    }

    public function test_for_action_create_ref_has_empty_before_and_added_labels(): void
    {
        [$vertexType, $actions] = $this->vertexCreateRefSetup(
            PropertyType::Enum,
            [
                ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                ['value' => 'jazz', 'label' => '爵士', 'active' => true],
            ],
            ['jazz', 'rock'],
        );

        $result = $this->diff->forAction($actions->last(), $actions, [$vertexType], []);

        $this->assertNotNull($result);
        $this->assertTrue($result['is_enum']);
        $this->assertTrue($result['is_create_ref_target']);
        $this->assertSame('（無）', $result['before_labels']);
        $this->assertSame('搖滾、爵士', $result['added_labels']);
        $this->assertSame('', $result['removed_labels']);
        $this->assertSame('搖滾、爵士', $result['value_labels']);
    }

    public function test_for_action_update_computes_added_and_removed_from_age(): void
    {
        $vertexType = new VertexType(['age_label_name' => 'person']);
        $property = new VertexProperty([
            'age_property_name' => 'genres',
            'age_property_type' => PropertyType::Enum,
            'enum_options' => [
                ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                ['value' => 'jazz', 'label' => '爵士', 'active' => true],
                ['value' => 'pop', 'label' => '流行', 'active' => true],
            ],
        ]);
        $vertexType->setRelation('properties', collect([$property]));

        $action = new RevisionAction([
            'action' => RevisionActionType::UpdateVertexProperty,
            'target_age_id' => '42',
            'age_property_name' => 'genres',
            'value' => ['rock', 'pop'],
        ]);

        $this->graphManager->shouldReceive('loadAgeVertexState')
            ->with(42)
            ->andReturn([
                'type_label' => 'person',
                'property_values' => [
                    'genres' => ['rock', 'jazz'],
                ],
            ]);

        $result = $this->diff->forAction($action, collect([$action]), [$vertexType], []);

        $this->assertNotNull($result);
        $this->assertFalse($result['is_create_ref_target']);
        $this->assertSame('搖滾、爵士', $result['before_labels']);
        $this->assertSame('流行', $result['added_labels']);
        $this->assertSame('爵士', $result['removed_labels']);
        $this->assertSame('搖滾、流行', $result['value_labels']);
    }

    public function test_for_action_delete_treats_after_as_empty(): void
    {
        $vertexType = new VertexType(['age_label_name' => 'person']);
        $property = new VertexProperty([
            'age_property_name' => 'genres',
            'age_property_type' => PropertyType::Enum,
            'enum_options' => [
                ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ],
        ]);
        $vertexType->setRelation('properties', collect([$property]));

        $action = new RevisionAction([
            'action' => RevisionActionType::DeleteVertexProperty,
            'target_age_id' => '7',
            'age_property_name' => 'genres',
            'value' => ['rock'],
        ]);

        $this->graphManager->shouldReceive('loadAgeVertexState')
            ->with(7)
            ->andReturn([
                'type_label' => 'person',
                'property_values' => [
                    'genres' => ['rock'],
                ],
            ]);

        $result = $this->diff->forAction($action, collect([$action]), [$vertexType], []);

        $this->assertNotNull($result);
        $this->assertSame('搖滾', $result['before_labels']);
        $this->assertSame('', $result['added_labels']);
        $this->assertSame('搖滾', $result['removed_labels']);
        $this->assertSame('—', $result['value_labels']);
    }

    public function test_for_action_resolves_edge_property_via_create_ref(): void
    {
        $edgeType = new EdgeType(['age_label_name' => 'performs']);
        $property = new EdgeProperty([
            'age_property_name' => 'roles',
            'age_property_type' => PropertyType::Enum,
            'enum_options' => [
                ['value' => 'lead', 'label' => '主唱', 'active' => true],
                ['value' => 'guest', 'label' => '客串', 'active' => true],
            ],
        ]);
        $edgeType->setRelation('properties', collect([$property]));

        $actions = collect([
            new RevisionAction([
                'order' => 0,
                'action' => RevisionActionType::CreateEdge,
                'edge_type_label' => 'performs',
            ]),
            new RevisionAction([
                'order' => 1,
                'action' => RevisionActionType::CreateEdgeProperty,
                'target_ref_order' => 0,
                'age_property_name' => 'roles',
                'value' => ['guest'],
            ]),
        ]);

        $result = $this->diff->forAction($actions->last(), $actions, [], [$edgeType]);

        $this->assertNotNull($result);
        $this->assertTrue($result['is_create_ref_target']);
        $this->assertSame('客串', $result['added_labels']);
        $this->assertSame('客串', $result['value_labels']);
    }

    public function test_format_action_value_uses_enum_labels(): void
    {
        $property = new VertexProperty([
            'age_property_type' => PropertyType::Enum,
            'enum_options' => [
                ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                ['value' => 'jazz', 'label' => '爵士', 'active' => true],
            ],
        ]);
        $action = new RevisionAction(['value' => ['jazz', 'rock']]);

        $this->assertSame('搖滾、爵士', $this->diff->formatActionValue($action, $property));
    }

    public function test_format_action_value_falls_back_for_non_enum(): void
    {
        $property = new VertexProperty(['age_property_type' => PropertyType::String]);

        $this->assertSame('hello', $this->diff->formatActionValue(new RevisionAction(['value' => 'hello']), $property));
        $this->assertSame('true', $this->diff->formatActionValue(new RevisionAction(['value' => true]), $property));
        $this->assertSame('a, b', $this->diff->formatActionValue(new RevisionAction(['value' => ['a', 'b']]), $property));
        $this->assertSame('—', $this->diff->formatActionValue(new RevisionAction(['value' => null]), $property));
        $this->assertSame('x', $this->diff->formatActionValue(new RevisionAction(['value' => 'x']), null));
    }

    /**
     * @param  list<array{value: string, label: string, active: bool}>|null  $enumOptions
     * @param  list<string>|string|null  $value
     * @return array{0: VertexType, 1: \Illuminate\Support\Collection<int, RevisionAction>}
     */
    private function vertexCreateRefSetup(PropertyType $type, ?array $enumOptions, mixed $value): array
    {
        $vertexType = new VertexType(['age_label_name' => 'person']);
        $property = new VertexProperty([
            'age_property_name' => 'genres',
            'age_property_type' => $type,
            'enum_options' => $enumOptions,
        ]);
        $vertexType->setRelation('properties', collect([$property]));

        $actions = collect([
            new RevisionAction([
                'order' => 0,
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => 'person',
            ]),
            new RevisionAction([
                'order' => 1,
                'action' => RevisionActionType::CreateVertexProperty,
                'target_ref_order' => 0,
                'age_property_name' => 'genres',
                'value' => $value,
            ]),
        ]);

        return [$vertexType, $actions];
    }
}
