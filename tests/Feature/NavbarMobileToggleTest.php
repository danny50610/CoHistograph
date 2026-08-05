<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NavbarMobileToggleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_navbar_uses_bootstrap_5_collapse_toggle_attributes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertSee('data-bs-toggle="collapse"', false)
            ->assertSee('data-bs-target="#navbarResponsive"', false)
            ->assertSee('id="navbarResponsive"', false)
            ->assertDontSee('data-toggle="collapse"', false);
    }
}
