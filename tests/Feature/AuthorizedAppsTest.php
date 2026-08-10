<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordMcpTokenUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthorizedAppsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_cannot_view_authorized_apps(): void
    {
        $this->get(route('settings.authorized-apps.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authorized_apps_are_aggregated_by_client(): void
    {
        $user = User::factory()->createOne();
        $client = $this->createMcpClient('Cursor');

        $this->createAccessToken($user, $client, [
            'created_at' => now()->subDays(2),
            'last_used_at' => now()->subHour(),
            'last_used_ip' => '203.0.113.10',
            'last_used_user_agent' => 'Cursor/1.0',
        ]);
        $this->createAccessToken($user, $client, [
            'created_at' => now()->subDay(),
            'last_used_at' => now()->subMinutes(5),
            'last_used_ip' => '203.0.113.20',
            'last_used_user_agent' => 'Cursor/1.1',
        ]);

        $otherClient = $this->createMcpClient('Claude');
        $this->createAccessToken($user, $otherClient, [
            'last_used_at' => now()->subDays(3),
            'last_used_ip' => '198.51.100.1',
            'last_used_user_agent' => 'Claude-User',
        ]);

        $response = $this->actingAs($user)
            ->get(route('settings.authorized-apps.index'));

        $response->assertOk()
            ->assertSee('已授權應用', false)
            ->assertSee('Cursor', false)
            ->assertSee('Claude', false)
            ->assertSee('2 組有效權杖', false)
            ->assertSee('203.0.113.20', false)
            ->assertSee('Cursor/1.1', false)
            ->assertSee('198.51.100.1', false);
    }

    public function test_revoking_authorized_app_revokes_tokens_and_refresh_tokens(): void
    {
        $user = User::factory()->createOne();
        $otherUser = User::factory()->createOne();
        $client = $this->createMcpClient('Cursor');

        $token = $this->createAccessToken($user, $client);
        $refresh = $this->createRefreshToken($token->id);
        $otherToken = $this->createAccessToken($otherUser, $client);

        $this->actingAs($user)
            ->delete(route('settings.authorized-apps.destroy', $client))
            ->assertRedirect(route('settings.authorized-apps.index'))
            ->assertSessionHas('global');

        $this->assertTrue($token->fresh()->revoked);
        $this->assertTrue($refresh->fresh()->revoked);
        $this->assertFalse($otherToken->fresh()->revoked);

        $this->actingAs($user)
            ->get(route('settings.authorized-apps.index'))
            ->assertOk()
            ->assertSee('目前沒有已授權的應用程式', false)
            ->assertViewHas('apps', fn ($apps): bool => $apps->isEmpty());
    }

    public function test_user_cannot_revoke_client_without_own_tokens(): void
    {
        $user = User::factory()->createOne();
        $otherUser = User::factory()->createOne();
        $client = $this->createMcpClient('Cursor');
        $this->createAccessToken($otherUser, $client);

        $this->actingAs($user)
            ->delete(route('settings.authorized-apps.destroy', $client))
            ->assertNotFound();
    }

    public function test_mcp_request_records_last_used_metadata(): void
    {
        $user = User::factory()->createOne();
        $client = $this->createMcpClient('Cursor');
        $token = $this->createAccessToken($user, $client);

        $user->withAccessToken(new AccessToken([
            'oauth_access_token_id' => $token->id,
            'oauth_client_id' => $client->getKey(),
            'oauth_user_id' => $user->getAuthIdentifier(),
            'oauth_scopes' => ['mcp:use'],
        ]));

        $request = Request::create('/mcp', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.50',
            'HTTP_USER_AGENT' => 'Cursor MCP Client/2.0',
        ]);
        $request->setUserResolver(fn () => $user);

        $middleware = new RecordMcpTokenUsage;
        $middleware->handle($request, fn () => new Response('ok'));

        $token->refresh();
        $this->assertNotNull($token->last_used_at);
        $this->assertSame('203.0.113.50', $token->last_used_ip);
        $this->assertSame('Cursor MCP Client/2.0', $token->last_used_user_agent);
    }

    public function test_mcp_usage_recording_is_throttled_for_same_fingerprint(): void
    {
        $user = User::factory()->createOne();
        $client = $this->createMcpClient('Cursor');
        $originalUsedAt = now()->subSeconds(10);
        $token = $this->createAccessToken($user, $client, [
            'last_used_at' => $originalUsedAt,
            'last_used_ip' => '203.0.113.50',
            'last_used_user_agent' => 'Cursor MCP Client/2.0',
        ]);

        $user->withAccessToken(new AccessToken([
            'oauth_access_token_id' => $token->id,
            'oauth_client_id' => $client->getKey(),
            'oauth_user_id' => $user->getAuthIdentifier(),
            'oauth_scopes' => ['mcp:use'],
        ]));

        $request = Request::create('/mcp', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.50',
            'HTTP_USER_AGENT' => 'Cursor MCP Client/2.0',
        ]);
        $request->setUserResolver(fn () => $user);

        (new RecordMcpTokenUsage)->handle($request, fn () => new Response('ok'));

        $token->refresh();
        $lastUsedAt = $token->last_used_at;
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $lastUsedAt);
        $this->assertEqualsWithDelta(
            $originalUsedAt->getTimestamp(),
            $lastUsedAt->getTimestamp(),
            1,
            'Expected last_used_at to remain unchanged within the throttle window.',
        );
        $this->assertSame('203.0.113.50', $token->last_used_ip);
    }

    private function createMcpClient(string $name): Client
    {
        return Client::factory()->asPublic()->create([
            'name' => $name,
            'redirect_uris' => ['cursor://anysphere.cursor-mcp/oauth/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAccessToken(User $user, Client $client, array $overrides = []): \Laravel\Passport\Token
    {
        $token = Passport::token()->forceFill([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'client_id' => $client->getKey(),
            'name' => null,
            'scopes' => ['mcp:use'],
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addYear(),
            ...$overrides,
        ]);
        $token->save();

        return $token;
    }

    private function createRefreshToken(string $accessTokenId): RefreshToken
    {
        $refresh = Passport::refreshToken()->forceFill([
            'id' => Str::random(40),
            'access_token_id' => $accessTokenId,
            'revoked' => false,
            'expires_at' => now()->addYear(),
        ]);
        $refresh->save();

        return $refresh;
    }
}
