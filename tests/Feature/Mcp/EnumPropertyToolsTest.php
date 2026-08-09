<?php

namespace Tests\Feature\Mcp;

use App\Enums\PropertyType;
use App\Enums\RevisionStatus;
use App\Mcp\Servers\CoHistographServer;
use App\Mcp\Tools\Revision\AddRevisionActionTool;
use App\Mcp\Tools\Schema\SearchEdgeTypesTool;
use App\Mcp\Tools\Schema\SearchVertexTypesTool;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class EnumPropertyToolsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_search_vertex_types_includes_enum_options_for_enum_properties(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne([
            'name' => 'ENUM 人物',
            'age_label_name' => 'person_enum_'.uniqid(),
        ]);
        VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => false],
        ])->create([
            'name' => '曲風',
            'age_property_name' => 'genres',
            'min_selections' => 1,
            'max_selections' => 2,
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name',
            'age_property_type' => PropertyType::String,
        ]);

        $response = CoHistographServer::actingAs($user)->tool(SearchVertexTypesTool::class, [
            'query' => 'ENUM 人物',
            'include_properties' => true,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($vertexType) {
            $json->where('total', 1)
                ->where('vertex_types.0.id', $vertexType->id)
                ->where('vertex_types.0.properties.0.age_property_name', 'genres')
                ->where('vertex_types.0.properties.0.age_property_type', PropertyType::Enum->value)
                ->where('vertex_types.0.properties.0.enum_options.0.value', 'rock')
                ->where('vertex_types.0.properties.0.enum_options.0.label', '搖滾')
                ->where('vertex_types.0.properties.0.enum_options.0.active', true)
                ->where('vertex_types.0.properties.0.enum_options.1.active', false)
                ->where('vertex_types.0.properties.0.min_selections', 1)
                ->where('vertex_types.0.properties.0.max_selections', 2)
                ->where('vertex_types.0.properties.1.age_property_name', 'name')
                ->where('vertex_types.0.properties.1.enum_options', null)
                ->where('vertex_types.0.properties.1.min_selections', null)
                ->where('vertex_types.0.properties.1.max_selections', null)
                ->etc();

            return true;
        });
    }

    public function test_search_edge_types_includes_enum_options_for_enum_properties(): void
    {
        $user = User::factory()->createOne();
        $person = VertexType::factory()->createOne(['age_label_name' => 'person_'.uniqid()]);
        $event = VertexType::factory()->createOne(['age_label_name' => 'event_'.uniqid()]);
        $edgeType = EdgeType::factory()->createOne([
            'name' => 'ENUM 參與',
            'age_label_name' => 'participated_'.uniqid(),
            'start_vertex_id' => $person->id,
            'end_vertex_id' => $event->id,
        ]);
        EdgeProperty::factory()->for($edgeType)->enum([
            ['value' => 'lead', 'label' => '主唱', 'active' => true],
        ])->create([
            'name' => '角色',
            'age_property_name' => 'roles',
            'min_selections' => 1,
            'max_selections' => null,
        ]);

        $response = CoHistographServer::actingAs($user)->tool(SearchEdgeTypesTool::class, [
            'query' => 'ENUM 參與',
            'include_properties' => true,
            'include_vertices' => false,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) use ($edgeType) {
            $json->where('total', 1)
                ->where('edge_types.0.id', $edgeType->id)
                ->where('edge_types.0.properties.0.age_property_name', 'roles')
                ->where('edge_types.0.properties.0.age_property_type', PropertyType::Enum->value)
                ->where('edge_types.0.properties.0.enum_options.0.value', 'lead')
                ->where('edge_types.0.properties.0.min_selections', 1)
                ->where('edge_types.0.properties.0.max_selections', null)
                ->etc();

            return true;
        });
    }

    public function test_add_revision_action_accepts_enum_array_value_and_validates(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => 'person_enum_'.uniqid()]);
        $property = VertexProperty::factory()->for($vertexType)->enum()->create([
            'age_property_name' => 'genres',
        ]);
        $revision = Revision::factory()->createOne([
            'user_id' => $user->id,
            'status' => RevisionStatus::Draft,
            'title' => 'MCP ENUM',
        ]);

        CoHistographServer::actingAs($user)->tool(AddRevisionActionTool::class, [
            'revision_id' => $revision->id,
            'order' => 0,
            'action' => [
                'action' => 'create_vertex',
                'vertex_type_label' => $vertexType->age_label_name,
            ],
        ])->assertOk();

        $response = CoHistographServer::actingAs($user)->tool(AddRevisionActionTool::class, [
            'revision_id' => $revision->id,
            'order' => 1,
            'action' => [
                'action' => 'create_vertex_property',
                'target_ref_order' => 0,
                'age_property_name' => $property->age_property_name,
                'value' => ['jazz', 'rock'],
            ],
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('validation.is_valid', true)
                ->where('actions.1.action', 'create_vertex_property')
                ->where('actions.1.value', ['rock', 'jazz'])
                ->etc();

            return true;
        });

        $this->assertSame(
            ['rock', 'jazz'],
            $revision->fresh()->actions()->orderBy('order')->get()[1]->value,
        );
    }

    public function test_add_revision_action_rejects_enum_string_scalar_via_validation(): void
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne(['age_label_name' => 'person_enum_'.uniqid()]);
        $property = VertexProperty::factory()->for($vertexType)->enum()->create([
            'age_property_name' => 'genres',
        ]);
        $revision = Revision::factory()->createOne([
            'user_id' => $user->id,
            'status' => RevisionStatus::Draft,
            'title' => 'MCP ENUM scalar',
        ]);

        CoHistographServer::actingAs($user)->tool(AddRevisionActionTool::class, [
            'revision_id' => $revision->id,
            'order' => 0,
            'action' => [
                'action' => 'create_vertex',
                'vertex_type_label' => $vertexType->age_label_name,
            ],
        ])->assertOk();

        $response = CoHistographServer::actingAs($user)->tool(AddRevisionActionTool::class, [
            'revision_id' => $revision->id,
            'order' => 1,
            'action' => [
                'action' => 'create_vertex_property',
                'target_ref_order' => 0,
                'age_property_name' => $property->age_property_name,
                'value' => 'rock',
            ],
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function (AssertableJson $json) {
            $json->where('validation.is_valid', false)
                ->etc();

            return true;
        });
    }
}
