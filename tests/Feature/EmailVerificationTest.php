<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\HomeController;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_verification_notice_page_is_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('信箱驗證', false)
            ->assertSee('重新寄送驗證信', false)
            ->assertSee(route('verification.resend'), false)
            ->assertSee(route('overview'), false);
    }

    public function test_unverified_user_sees_navbar_reminder(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertSee('信箱尚未驗證', false)
            ->assertSee('text-danger', false)
            ->assertSee(route('verification.notice'), false);
    }

    public function test_unverified_user_cannot_access_auth_required_pages(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('revisions.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_does_not_see_navbar_reminder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertDontSee('信箱尚未驗證', false);
    }

    public function test_register_sends_verification_email_and_redirects_to_notice(): void
    {
        Notification::fake();

        $email = fake()->unique()->userName().'@gmail.com';

        $this->post(route('register'), [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', $email)->first();

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect(url(HomeController::AUTHENTICATED_REDIRECT))
            ->assertSessionHas('global', '信箱驗證完成');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_resend_verification_response_uses_global_flash(): void
    {
        $controller = new class extends VerificationController
        {
            public function callResend(Request $request)
            {
                return $this->resend($request);
            }
        };

        $user = User::factory()->unverified()->create();
        $request = Request::create(route('verification.resend'), 'POST');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession($this->app['session.store']);

        Notification::fake();

        $response = $controller->callResend($request);

        $this->assertTrue($response->isRedirect());
        $this->assertEquals('驗證信已重新寄出', session('global'));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verification_notice_page_shows_global_flash_via_page_alert(): void
    {
        $user = User::factory()->unverified()->create();
        $message = '驗證信已重新寄出';

        $this->actingAs($user)
            ->withSession(['global' => $message])
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee($message, false)
            ->assertSee('fa-info-circle', false);
    }
}
