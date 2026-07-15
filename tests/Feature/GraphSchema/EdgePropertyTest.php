<?php

namespace Tests\Feature\GraphSchema;

use App\Enums\PropertyType;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\User;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Enums\Direction;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EdgePropertyTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->addRole('admin');
    }

    public function test_store_success()
    {
        $edgeType = EdgeType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/edge-type/{$edgeType->id}/edge-property", [
                'name' => 'Start Date',
                'description' => 'When the relationship started',
                'age_property_name' => 'start_date',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $property = EdgeProperty::where('edge_type_id', $edgeType->id)
            ->where('name', 'Start Date')
            ->first();
        $this->assertNotNull($property);
        $this->assertEquals('start_date', $property->age_property_name);
        $this->assertEquals(PropertyType::String, $property->age_property_type);
    }

    public function test_store_fail_when_name_not_unique_within_edge_type()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create(['name' => 'Start Date']);

        $this->actingAs($this->user)
            ->post("/graph-schema/edge-type/{$edgeType->id}/edge-property", [
                'name' => 'Start Date',
                'description' => '',
                'age_property_name' => 'another_prop',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertCount(1, EdgeProperty::where('edge_type_id', $edgeType->id)->get());
    }

    public function test_store_fail_when_age_property_name_not_unique_within_edge_type()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create(['age_property_name' => 'start_date']);

        $this->actingAs($this->user)
            ->post("/graph-schema/edge-type/{$edgeType->id}/edge-property", [
                'name' => 'Another Name',
                'description' => '',
                'age_property_name' => 'start_date',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['resolved_age_property_name']);

        $this->assertCount(1, EdgeProperty::where('edge_type_id', $edgeType->id)->get());
    }

    public function test_store_success_with_localized_property()
    {
        $edgeType = EdgeType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/edge-type/{$edgeType->id}/edge-property", [
                'name' => '角色說明',
                'description' => '',
                'locale' => 'zh_tw',
                'base_age_property_name' => 'role',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $property = EdgeProperty::where('edge_type_id', $edgeType->id)
            ->where('age_property_name', 'role_zh_tw')
            ->first();
        $this->assertNotNull($property);
        $this->assertEquals('zh_tw', $property->locale);
        $this->assertEquals('role_zh_tw', $property->age_property_name);
    }

    public function test_store_fail_when_localized_conflicts_with_existing_non_localized_property()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create([
            'age_property_name' => 'role',
            'locale' => null,
        ]);

        $this->actingAs($this->user)
            ->post("/graph-schema/edge-type/{$edgeType->id}/edge-property", [
                'name' => '角色說明',
                'description' => '',
                'locale' => 'zh_tw',
                'base_age_property_name' => 'role',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['base_age_property_name']);

        $this->assertCount(1, EdgeProperty::where('edge_type_id', $edgeType->id)->get());
    }

    public function test_store_fail_when_non_localized_conflicts_with_existing_localized_property()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create([
            'age_property_name' => 'role_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->post("/graph-schema/edge-type/{$edgeType->id}/edge-property", [
                'name' => 'Role',
                'description' => '',
                'age_property_name' => 'role',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_name']);

        $this->assertCount(1, EdgeProperty::where('edge_type_id', $edgeType->id)->get());
    }

    public function test_store_fail_when_age_property_name_invalid()
    {
        $edgeType = EdgeType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/edge-type/{$edgeType->id}/edge-property", [
                'name' => 'Start Date',
                'description' => '',
                'age_property_name' => 'Invalid-Name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_name']);
    }

    public function test_store_fail_when_age_property_type_invalid()
    {
        $edgeType = EdgeType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/edge-type/{$edgeType->id}/edge-property", [
                'name' => 'Start Date',
                'description' => '',
                'age_property_name' => 'start_date',
                'age_property_type' => 'not_a_type',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_type']);
    }

    public function test_update_success()
    {
        $edgeType = EdgeType::factory()->create();
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create();

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}", [
                'name' => 'Updated Name',
                'description' => 'Updated description',
                'age_property_type' => PropertyType::Integer->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $updatedProperty = EdgeProperty::find($edgeProperty->id);
        $this->assertNotNull($updatedProperty);
        $this->assertEquals('Updated Name', $updatedProperty->name);
        $this->assertEquals(PropertyType::Integer, $updatedProperty->age_property_type);
    }

    public function test_update_success_when_age_property_name_is_cypher_reserved_word(): void
    {
        $edgeType = EdgeType::factory()->create();
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create([
            'age_property_name' => 'in',
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}", [
                'name' => 'Updated Name',
                'description' => 'Updated description',
                'age_property_type' => PropertyType::Integer->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated Name', $edgeProperty->fresh()->name);
    }

    public function test_store_fail_when_age_property_name_is_cypher_reserved_word(): void
    {
        $edgeType = EdgeType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/edge-type/{$edgeType->id}/edge-property", [
                'name' => 'Reserved',
                'description' => '',
                'age_property_name' => 'in',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_name']);
    }

    public function test_update_does_not_change_age_property_name_or_locale()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create([
            'name' => 'Other Property',
            'age_property_name' => 'taken_prop',
        ]);
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create([
            'name' => 'Localized Role',
            'age_property_name' => 'role_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}", [
                'name' => 'Updated Localized Role',
                'description' => 'Updated description',
                'base_age_property_name' => 'role',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $edgeProperty->refresh();
        $this->assertEquals('Updated Localized Role', $edgeProperty->name);
        $this->assertEquals('role_zh_tw', $edgeProperty->age_property_name);
        $this->assertEquals('zh_tw', $edgeProperty->locale);
    }

    public function test_update_can_rename_age_property_name_when_no_graph_data(): void
    {
        $edgeType = EdgeType::factory()->create();
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create([
            'name' => 'Start Date',
            'age_property_name' => 'start_date',
            'locale' => null,
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}", [
                'name' => 'Start Date',
                'description' => '',
                'age_property_name' => 'began_at',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertSame('began_at', $edgeProperty->fresh()->age_property_name);
    }

    public function test_update_rejects_locale_field(): void
    {
        $edgeType = EdgeType::factory()->create();
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create([
            'name' => 'Localized Role',
            'age_property_name' => 'role_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}", [
                'name' => $edgeProperty->name,
                'description' => '',
                'base_age_property_name' => 'role',
                'locale' => 'en_us',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['locale']);

        $this->assertSame('zh_tw', $edgeProperty->fresh()->locale);
    }

    public function test_update_rejects_age_property_name_when_graph_data_exists(): void
    {
        $startVertex = VertexType::factory()->create(['age_label_name' => 'lock_edge_prop_start']);
        $endVertex = VertexType::factory()->create(['age_label_name' => 'lock_edge_prop_end']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'lock_edge_prop_et',
            'start_vertex_id' => $startVertex->id,
            'end_vertex_id' => $endVertex->id,
        ]);
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create([
            'name' => 'Start Date',
            'age_property_name' => 'start_date',
            'locale' => null,
        ]);

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType, $edgeProperty) {
                return $builder->createNode('a', $edgeType->startVertex->age_label_name)
                    ->withCreateEdge(Direction::RIGHT, 'e', $edgeType->age_label_name, [
                        $edgeProperty->age_property_name => 'in_use',
                    ])
                    ->withCreateNode('b', $edgeType->endVertex->age_label_name)
                    ->setAs(['e']);
            })->get();

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}", [
                'name' => $edgeProperty->name,
                'description' => '',
                'age_property_name' => 'began_at',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_name']);

        $this->assertSame('start_date', $edgeProperty->fresh()->age_property_name);
    }

    public function test_update_fail_when_name_not_unique_within_edge_type()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create(['name' => 'Taken Name']);
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create(['name' => 'Original Name']);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}", [
                'name' => 'Taken Name',
                'description' => '',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertEquals('Original Name', $edgeProperty->fresh()->name);
    }

    public function test_destroy_success()
    {
        $edgeType = EdgeType::factory()->create();
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create();

        $this->actingAs($this->user)
            ->delete("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}")
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($edgeProperty);
    }

    public function test_destroy_fail_when_property_used_in_graph_data()
    {
        $startVertex = VertexType::factory()->create(['age_label_name' => 'destroy_edge_prop_start_vt']);
        $endVertex = VertexType::factory()->create(['age_label_name' => 'destroy_edge_prop_end_vt']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'destroy_edge_prop_et',
            'start_vertex_id' => $startVertex->id,
            'end_vertex_id' => $endVertex->id,
        ]);
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create();

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType, $edgeProperty) {
                return $builder->createNode('a', $edgeType->startVertex->age_label_name)
                    ->withCreateEdge(Direction::RIGHT, 'e', $edgeType->age_label_name, [
                        $edgeProperty->age_property_name => 'in_use',
                    ])
                    ->withCreateNode('b', $edgeType->endVertex->age_label_name)
                    ->setAs(['e']);
            })->get();

        $this->actingAs($this->user)
            ->delete("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}")
            ->assertStatus(302)
            ->assertSessionHas('warning');

        $this->assertModelExists($edgeProperty);
    }

    public function test_create_form_shows_locale_selector(): void
    {
        $edgeType = EdgeType::factory()->create();

        $this->actingAs($this->user)
            ->get("/graph-schema/edge-type/{$edgeType->id}/edge-property/create")
            ->assertOk()
            ->assertSee('語言版本')
            ->assertSee('非多語系')
            ->assertSee('繁體中文（zh_tw）')
            ->assertSee('id="age_property_type"', false)
            ->assertSee('form-select', false)
            ->assertSee('>INTEGER<', false)
            ->assertSee('>STRING<', false)
            ->assertDontSee('type="text" name="age_property_type"', false);
    }

    public function test_edit_form_allows_editing_property_name_when_no_graph_data(): void
    {
        $edgeType = EdgeType::factory()->create();
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create([
            'age_property_name' => 'role_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->get("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}/edit")
            ->assertOk()
            ->assertSee('語言版本')
            ->assertSee('繁體中文')
            ->assertSee('(zh_tw)')
            ->assertSee('id="base_age_property_name"', false)
            ->assertDontSee('id="locale"', false)
            ->assertDontSee('圖資料庫中已有此屬性的資料，無法變更 Property 名稱');
    }

    public function test_edit_form_shows_readonly_property_name_when_graph_data_exists(): void
    {
        $startVertex = VertexType::factory()->create(['age_label_name' => 'readonly_edge_prop_start']);
        $endVertex = VertexType::factory()->create(['age_label_name' => 'readonly_edge_prop_end']);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'readonly_edge_prop_et',
            'start_vertex_id' => $startVertex->id,
            'end_vertex_id' => $endVertex->id,
        ]);
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create([
            'age_property_name' => 'role_zh_tw',
            'locale' => 'zh_tw',
        ]);

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($edgeType, $edgeProperty) {
                return $builder->createNode('a', $edgeType->startVertex->age_label_name)
                    ->withCreateEdge(Direction::RIGHT, 'e', $edgeType->age_label_name, [
                        $edgeProperty->age_property_name => 'in_use',
                    ])
                    ->withCreateNode('b', $edgeType->endVertex->age_label_name)
                    ->setAs(['e']);
            })->get();

        $this->actingAs($this->user)
            ->get("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}/edit")
            ->assertOk()
            ->assertSee('語言版本')
            ->assertSee('繁體中文')
            ->assertSee('role_zh_tw')
            ->assertSee('圖資料庫中已有此屬性的資料，無法變更 Property 名稱')
            ->assertDontSee('id="locale"', false)
            ->assertDontSee('id="base_age_property_name"', false);
    }

    public function test_show_displays_locale_for_localized_property(): void
    {
        $edgeType = EdgeType::factory()->create();
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create([
            'name' => '角色說明',
            'age_property_name' => 'role_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->get("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}")
            ->assertOk()
            ->assertSee('語言版本')
            ->assertSee('繁體中文')
            ->assertSee('(zh_tw)')
            ->assertSee('role_zh_tw');
    }
}
