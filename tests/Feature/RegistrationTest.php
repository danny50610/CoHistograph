<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registration_form_does_not_ask_for_name(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('id="name"', false)
            ->assertSee('id="email"', false);
    }

    public function test_register_auto_generates_unique_name_from_email(): void
    {
        Notification::fake();

        $localPart = fake()->unique()->userName();
        $email = $localPart.'@gmail.com';

        $this->post(route('register'), [
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => $localPart,
        ]);

        Notification::assertSentTo(User::where('email', $email)->first(), VerifyEmail::class);
    }

    public function test_register_avoids_duplicate_auto_generated_names(): void
    {
        Notification::fake();

        User::factory()->create([
            'name' => 'sharedname',
        ]);

        $email = 'sharedname@gmail.com';

        $this->post(route('register'), [
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'sharedname-2',
        ]);
    }

    public function test_register_ignores_submitted_name_field(): void
    {
        Notification::fake();

        $localPart = fake()->unique()->userName();
        $email = $localPart.'@gmail.com';

        $this->post(route('register'), [
            'name' => 'Forced Display Name',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => $localPart,
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => $email,
            'name' => 'Forced Display Name',
        ]);
    }
}
