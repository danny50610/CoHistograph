<?php

namespace Tests\Feature\GraphSchema;

use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\User;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EdgeTypeTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->addRole('admin');
    }

    public function test_create()
    {
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'participated_in',
                'reverse_name' => 'had_participant',
                'age_label_name' => 'participated_in',
                'description' => 'A participation edge',
                'start_vertex_id' => $startVertex->id,
                'end_vertex_id' => $endVertex->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $edgeType = EdgeType::where('name', 'participated_in')->first();
        $this->assertNotNull($edgeType);
        $this->assertEquals('participated_in', $edgeType->age_label_name);
        $this->assertEquals($startVertex->id, $edgeType->start_vertex_id);
        $this->assertEquals($endVertex->id, $edgeType->end_vertex_id);
    }

    public function test_create_fail_when_name_clashes_with_vertex_type()
    {
        VertexType::factory()->create(['name' => 'Person']);
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'Person',
                'age_label_name' => 'person_edge',
                'description' => '',
                'start_vertex_id' => $startVertex->id,
                'end_vertex_id' => $endVertex->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);
    }

    public function test_create_fail_when_age_label_name_clashes_with_vertex_type()
    {
        VertexType::factory()->create(['age_label_name' => 'person']);
        $startVertex = VertexType::factory()->create();
        $endVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post('/graph-schema/edge-type', [
                'name' => 'SomeEdge',
                'age_label_name' => 'person',
                'description' => '',
                'start_vertex_id' => $startVertex->id,
                'end_vertex_id' => $endVertex->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name']);
    }

    public function test_update()
    {
        $edgeType = EdgeType::factory()->create();
        $newStartVertex = VertexType::factory()->create();
        $newEndVertex = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => 'updated_edge',
                'reverse_name' => 'reversed',
                'age_label_name' => 'updated_edge',
                'description' => 'Updated description',
                'start_vertex_id' => $newStartVertex->id,
                'end_vertex_id' => $newEndVertex->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $updatedEdgeType = EdgeType::find($edgeType->id);
        $this->assertNotNull($updatedEdgeType);
        $this->assertEquals('updated_edge', $updatedEdgeType->name);
        $this->assertEquals('updated_edge', $updatedEdgeType->age_label_name);
        $this->assertEquals($newStartVertex->id, $updatedEdgeType->start_vertex_id);
        $this->assertEquals($newEndVertex->id, $updatedEdgeType->end_vertex_id);
    }

    public function test_update_fail_when_name_clashes_with_vertex_type()
    {
        $edgeType = EdgeType::factory()->create();
        VertexType::factory()->create(['name' => 'Person']);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => 'Person',
                'age_label_name' => $edgeType->age_label_name,
                'description' => $edgeType->description,
                'start_vertex_id' => $edgeType->start_vertex_id,
                'end_vertex_id' => $edgeType->end_vertex_id,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);
    }

    public function test_update_fail_when_age_label_name_clashes_with_vertex_type()
    {
        $edgeType = EdgeType::factory()->create();
        VertexType::factory()->create(['age_label_name' => 'person']);

        $this->actingAs($this->user)
            ->put("/graph-schema/edge-type/{$edgeType->id}", [
                'name' => $edgeType->name,
                'age_label_name' => 'person',
                'description' => $edgeType->description,
                'start_vertex_id' => $edgeType->start_vertex_id,
                'end_vertex_id' => $edgeType->end_vertex_id,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_label_name']);
    }

    public function test_destroy_success()
    {
        $edgeType = EdgeType::factory()->create();

        $this->actingAs($this->user)
            ->delete("/graph-schema/edge-type/{$edgeType->id}")
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($edgeType);
    }

    public function test_destroy_fail_when_has_properties()
    {
        $edgeType = EdgeType::factory()->create();
        EdgeProperty::factory()->for($edgeType)->create();

        $this->actingAs($this->user)
            ->delete("/graph-schema/edge-type/{$edgeType->id}")
            ->assertStatus(302)
            ->assertSessionHas('warning');

        $this->assertModelExists($edgeType);
    }
}
