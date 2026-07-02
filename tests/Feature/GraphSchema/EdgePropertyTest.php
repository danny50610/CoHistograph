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
                'age_property_name' => $edgeProperty->age_property_name,
                'age_property_type' => PropertyType::Integer->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $updatedProperty = EdgeProperty::find($edgeProperty->id);
        $this->assertNotNull($updatedProperty);
        $this->assertEquals('Updated Name', $updatedProperty->name);
        $this->assertEquals(PropertyType::Integer, $updatedProperty->age_property_type);
    }

    public function test_update_fail_when_age_property_name_not_unique_within_edge_type()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create(['age_property_name' => 'taken_prop']);
        $edgeProperty = EdgeProperty::factory()->for($edgeType)->create(['age_property_name' => 'original_prop']);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}/edge-property/{$edgeProperty->id}", [
                'name' => $edgeProperty->name,
                'description' => '',
                'age_property_name' => 'taken_prop',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_name']);

        $this->assertEquals('original_prop', $edgeProperty->fresh()->age_property_name);
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
                'age_property_name' => $edgeProperty->age_property_name,
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
}
