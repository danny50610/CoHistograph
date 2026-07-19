<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\ForgotPasswordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetViewTest extends TestCase
{
    public function test_forgot_password_page_is_rendered(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('忘記密碼', false)
            ->assertSee('寄送重設密碼連結', false)
            ->assertSee('name="email"', false)
            ->assertSee(route('password.email'), false)
            ->assertSee(route('login'), false);
    }

    public function test_reset_password_page_is_rendered(): void
    {
        $email = 'user@example.com';
        $token = 'sample-reset-token';

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $email,
        ]))
            ->assertOk()
            ->assertSee('重設密碼', false)
            ->assertSee('name="token"', false)
            ->assertSee($token, false)
            ->assertSee('name="email"', false)
            ->assertSee($email, false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee(route('password.update'), false)
            ->assertSee(route('login'), false);
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), false);
    }

    public function test_send_reset_link_response_uses_global_flash(): void
    {
        $controller = new class extends ForgotPasswordController
        {
            public function callSendResetLinkResponse(Request $request, string $response)
            {
                return $this->sendResetLinkResponse($request, $response);
            }
        };

        $request = Request::create(route('password.email'), 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $response = $controller->callSendResetLinkResponse($request, Password::RESET_LINK_SENT);

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(trans(Password::RESET_LINK_SENT), session('global'));
        $this->assertNull(session('status'));
    }

    public function test_forgot_password_page_shows_global_flash_via_page_alert(): void
    {
        $message = trans(Password::RESET_LINK_SENT);

        $this->withSession(['global' => $message])
            ->get(route('password.request'))
            ->assertOk()
            ->assertSee($message, false)
            ->assertSee('fa-info-circle', false);
    }
}
