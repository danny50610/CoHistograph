<?php

namespace Tests\Feature\GraphSchema;

use App\Models\EdgeType;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    public function test_create()
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

    public function test_update()
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
}
