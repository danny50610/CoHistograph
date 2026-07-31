<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OAuthAuthorizePageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authorization_page_uses_bootstrap_styles(): void
    {
        $user = User::factory()->createOne();

        $register = $this->postJson('/oauth/register', [
            'client_name' => 'Cursor',
            'redirect_uris' => [
                'http://localhost:8787/callback',
            ],
        ])->assertOk();

        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', 'test-verifier', true)), '+/', '-_'), '=');

        $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $register->json('client_id'),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'redirect_uri' => 'http://localhost:8787/callback',
            'scope' => 'mcp:use',
            'state' => 'test-state',
        ]));

        $response->assertOk()
            ->assertSee('navbar', false)
            ->assertSee('footer', false)
            ->assertSee('btn btn-primary', false)
            ->assertSee('授權應用程式', false)
            ->assertSee('Cursor', false)
            ->assertSee($user->email, false)
            ->assertDontSee('bg-background', false)
            ->assertDontSee('text-card-foreground', false);
    }
}
