<?php

namespace Tests\Feature\GraphSchema;

use App\Enums\PropertyType;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VertexPropertyTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->addRole('admin');
    }

    public function test_store()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Full Name',
                'description' => 'The full name of the person',
                'age_property_name' => 'full_name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $property = VertexProperty::where('vertex_type_id', $vertexType->id)
            ->where('name', 'Full Name')
            ->first();
        $this->assertNotNull($property);
        $this->assertEquals('full_name', $property->age_property_name);
        $this->assertEquals(PropertyType::String, $property->age_property_type);
    }

    public function test_store_fail_when_name_not_unique_within_vertex_type()
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create(['name' => 'Full Name']);

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Full Name',
                'description' => '',
                'age_property_name' => 'another_prop',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertCount(1, VertexProperty::where('vertex_type_id', $vertexType->id)->get());
    }

    public function test_update()
    {
        $vertexType = VertexType::factory()->create();
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create();

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}", [
                'name' => 'Updated Name',
                'description' => 'Updated description',
                'age_property_name' => $vertexProperty->age_property_name,
                'age_property_type' => PropertyType::Integer->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $updatedProperty = VertexProperty::find($vertexProperty->id);
        $this->assertNotNull($updatedProperty);
        $this->assertEquals('Updated Name', $updatedProperty->name);
        $this->assertEquals(PropertyType::Integer, $updatedProperty->age_property_type);
    }

    public function test_update_fail_when_name_not_unique_within_vertex_type()
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create(['name' => 'Taken Name']);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create(['name' => 'Original Name']);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}", [
                'name' => 'Taken Name',
                'description' => '',
                'age_property_name' => $vertexProperty->age_property_name,
                'age_property_type' => $vertexProperty->age_property_type->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertEquals('Original Name', $vertexProperty->fresh()->name);
    }

    public function test_destroy_success()
    {
        $vertexType = VertexType::factory()->create();
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create();

        $this->actingAs($this->user)
            ->delete("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}")
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($vertexProperty);
    }
}
