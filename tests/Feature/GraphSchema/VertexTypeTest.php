<?php

namespace Tests\Feature\GraphSchema;

use App\Models\EdgeType;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VertexTypeTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->addRole('admin');
    }

    public function test_create_success()
    {
        $this->actingAs($this->user)
            ->post('/graph-schema/vertex-type', [
                'name' => 'Person',
                'age_label_name' => 'person',
                'description' => 'A person vertex type',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $vertexTypes = VertexType::where('name', 'Person')->get();
        $this->assertCount(1, $vertexTypes);

        $vertexType = $vertexTypes->first();
        $this->assertNotNull($vertexType);
        $this->assertEquals('person', $vertexType->age_label_name);
        $this->assertEquals('A person vertex type', $vertexType->description);
    }

    public function test_create_fail_when_name_or_age_label_name_not_unique()
    {
        VertexType::create([
            'name' => 'Person',
            'age_label_name' => 'person',
            'description' => 'A person vertex type',
        ]);

        $this->actingAs($this->user)
            ->post('/graph-schema/vertex-type', [
                'name' => 'Person',
                'age_label_name' => 'individual',
                'description' => 'Another person vertex type',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name' => 'The name has already been taken.']);

        $this->actingAs($this->user)
            ->post('/graph-schema/vertex-type', [
                'name' => 'Individual',
                'age_label_name' => 'person',
                'description' => 'Another person vertex type',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name' => 'The age label name has already been taken.']);
    }

    public function test_create_success_when_name_matches_edge_type_display_name()
    {
        EdgeType::factory()->create(['name' => 'participated_in']);

        $this->actingAs($this->user)
            ->post('/graph-schema/vertex-type', [
                'name' => 'participated_in',
                'age_label_name' => 'participated_in_vt',
                'description' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertNotNull(VertexType::where('name', 'participated_in')->first());
    }

    public function test_create_fail_when_age_label_name_clashes_with_edge_type()
    {
        EdgeType::factory()->create(['age_label_name' => 'participated_in']);

        $this->actingAs($this->user)
            ->post('/graph-schema/vertex-type', [
                'name' => 'SomeNewVertex',
                'age_label_name' => 'participated_in',
                'description' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name']);
    }

    public function test_update_success()
    {
        $vertexType = VertexType::create([
            'name' => 'Person',
            'age_label_name' => 'person',
            'description' => 'A person vertex type',
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}", [
                'name' => 'Individual',
                'age_label_name' => 'individual',
                'description' => 'An individual vertex type',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $updatedVertexType = VertexType::find($vertexType->id);
        $this->assertNotNull($updatedVertexType);
        $this->assertEquals('Individual', $updatedVertexType->name);
        $this->assertEquals('individual', $updatedVertexType->age_label_name);
        $this->assertEquals('An individual vertex type', $updatedVertexType->description);
    }

    public function test_update_fail_when_name_or_age_label_name_not_unique()
    {
        VertexType::create([
            'name' => 'Person',
            'age_label_name' => 'person',
            'description' => 'A person vertex type',
        ]);

        $vertexType = VertexType::create([
            'name' => 'Other',
            'age_label_name' => 'other',
            'description' => 'other vertex type',
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}", [
                'name' => 'Person',
                'age_label_name' => $vertexType->age_label_name,
                'description' => $vertexType->description,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name' => 'The name has already been taken.']);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}", [
                'name' => $vertexType->name,
                'age_label_name' => 'person',
                'description' => $vertexType->description,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name' => 'The age label name has already been taken.']);
    }

    public function test_update_success_when_name_matches_edge_type_display_name()
    {
        $vertexType = VertexType::factory()->create();
        EdgeType::factory()->create(['name' => 'participated_in']);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}", [
                'name' => 'participated_in',
                'age_label_name' => $vertexType->age_label_name,
                'description' => $vertexType->description,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertEquals('participated_in', $vertexType->fresh()->name);
    }

    public function test_update_fail_when_age_label_name_clashes_with_edge_type()
    {
        $vertexType = VertexType::factory()->create();
        EdgeType::factory()->create(['age_label_name' => 'participated_in']);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}", [
                'name' => $vertexType->name,
                'age_label_name' => 'participated_in',
                'description' => $vertexType->description,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name']);
    }

    public function test_destroy_success()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->delete("/graph-schema/vertex-type/{$vertexType->id}")
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($vertexType);
    }

    public function test_destroy_fail_when_has_properties()
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create();

        $this->actingAs($this->user)
            ->delete("/graph-schema/vertex-type/{$vertexType->id}")
            ->assertStatus(302)
            ->assertSessionHas('warning');

        $this->assertModelExists($vertexType);
    }

    public function test_destroy_fail_when_has_edge_types()
    {
        $vertexType = VertexType::factory()->create();
        EdgeType::factory()->create(['start_vertex_id' => $vertexType->id]);

        $this->actingAs($this->user)
            ->delete("/graph-schema/vertex-type/{$vertexType->id}")
            ->assertStatus(302)
            ->assertSessionHas('warning');

        $this->assertModelExists($vertexType);
    }

    public function test_destroy_fail_when_used_as_end_vertex_by_edge_type()
    {
        $vertexType = VertexType::factory()->create();
        EdgeType::factory()->create(['end_vertex_id' => $vertexType->id]);

        $this->actingAs($this->user)
            ->delete("/graph-schema/vertex-type/{$vertexType->id}")
            ->assertStatus(302)
            ->assertSessionHas('warning');

        $this->assertModelExists($vertexType);
    }

    public function test_destroy_fail_when_has_graph_vertices_data()
    {
        $vertexType = VertexType::factory()->create(['age_label_name' => 'destroy_vertex_vt']);

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($vertexType) {
                return $builder->createNode(null, $vertexType->age_label_name, [
                    'name' => 'in_use_vertex',
                ])->setAs(['v']);
            })->get();

        $this->actingAs($this->user)
            ->delete("/graph-schema/vertex-type/{$vertexType->id}")
            ->assertStatus(302)
            ->assertSessionHas('warning');

        $this->assertModelExists($vertexType);
    }
}
