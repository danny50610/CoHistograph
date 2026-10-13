<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_is_redirected_from_profile(): void
    {
        $this->get(route('profile'))
            ->assertRedirect(route('login'));
    }

    public function test_verified_user_can_view_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile User',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('個人資料', false)
            ->assertSee('Profile User', false)
            ->assertSee($user->email, false);
    }

    public function test_unverified_user_cannot_view_profile(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_user_can_update_own_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'New Name',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('global', '個人資料已更新')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_user_cannot_update_name_to_taken_name(): void
    {
        User::factory()->create([
            'name' => 'Taken Name',
        ]);

        $user = User::factory()->create([
            'name' => 'Old Name',
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->patch(route('profile.update'), [
                'name' => 'Taken Name',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Old Name',
        ]);
    }

    public function test_user_can_keep_same_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Same Name',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Same Name',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasNoErrors();
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('global', '密碼已更新')
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_update_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->put(route('profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors(['current_password']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_password_update_fails_when_confirmation_mismatches(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->put(route('profile.password'), [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors(['password']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
