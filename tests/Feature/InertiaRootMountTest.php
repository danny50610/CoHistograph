<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InertiaRootMountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_blade_pages_do_not_render_inertia_root(): void
    {
        $this->get(route('index'))
            ->assertOk()
            ->assertDontSee('data-page="app"', false)
            ->assertDontSee('<div id="app"></div>', false);
    }

    public function test_inertia_pages_render_inertia_root(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('graph-schema.visualization'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('GraphSchema/Visualization')
            )
            ->assertSee('data-page="app"', false)
            ->assertSee('<div id="app"></div>', false);
    }
}
