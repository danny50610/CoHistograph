<?php

namespace Tests\Feature\GraphSchema;

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
