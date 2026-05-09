<?php

namespace Tests\Feature\GraphSchema;

use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
}
