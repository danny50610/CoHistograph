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
