<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Tests\TestCase;

class OAuthRegisterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unauthenticated_mcp_http_endpoint_returns_401(): void
    {
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertUnauthorized();
    }

    public function test_dynamic_client_registration_accepts_cursor_redirect_uri(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Cursor',
            'redirect_uris' => [
                'cursor://anysphere.cursor-mcp/oauth/callback',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('scope', 'mcp:use')
            ->assertJsonPath('redirect_uris.0', 'cursor://anysphere.cursor-mcp/oauth/callback');

        $this->assertNotEmpty($response->json('client_id'));
        $this->assertTrue(Client::query()->whereKey($response->json('client_id'))->exists());
    }

    public function test_dynamic_client_registration_survives_ag_catalog_leading_search_path(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires pgsql with Apache AGE schemas');
        }

        // Reproduce apache-age-driver's HTTP ConnectionEstablished side effect
        // (skipped while runningInConsole, so PHPUnit must set it manually).
        DB::statement('SET SESSION search_path = ag_catalog, public');

        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Cursor',
            'redirect_uris' => [
                'cursor://anysphere.cursor-mcp/oauth/callback',
                'https://www.cursor.com/agents/mcp/oauth/callback',
                'http://localhost:8787/callback',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('redirect_uris.0', 'cursor://anysphere.cursor-mcp/oauth/callback');
    }

    public function test_dynamic_client_registration_accepts_localhost_loopback_redirect_uri(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Cursor',
            'redirect_uris' => [
                'http://localhost:8787/callback',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('redirect_uris.0', 'http://localhost:8787/callback');
    }

    public function test_dynamic_client_registration_rejects_unknown_custom_scheme(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Unknown Client',
            'redirect_uris' => [
                'notallowed://oauth/callback',
            ],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri')
            ->assertJsonPath('error_description', 'redirect_uris.0 is not a valid URL.');
    }
}
