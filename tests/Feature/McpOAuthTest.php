<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $callback = 'https://chatgpt.com/connector_platform_oauth_redirect';

    private string $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://aripuango.info', 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'video.allowed_email' => 'owner@example.com']);
        $this->owner = User::factory()->create(['email' => 'owner@example.com']);
        $this->verifier = str_repeat('a', 64);
    }

    private function params(): array
    {
        $client = $this->postJson('/oauth/register', ['redirect_uris' => [$this->callback], 'token_endpoint_auth_method' => 'none'])->assertCreated()->json('client_id');

        return ['client_id' => $client, 'redirect_uri' => $this->callback, 'response_type' => 'code', 'scope' => 'videogenius',
            'resource' => 'https://aripuango.info/mcp', 'state' => 'test-state', 'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '=')];
    }

    private function approvedCode(array $p): string
    {
        $r = $this->actingAs($this->owner)->get('/oauth/authorize?'.http_build_query($p))->assertOk();
        // Browsers enforce form-action on the redirect after consent as well.
        $r->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; form-action 'self' https://chatgpt.com");
        $nonce = $r->viewData('nonce');
        $r = $this->post('/oauth/authorize', ['nonce' => $nonce, 'decision' => 'allow'])->assertRedirect();
        parse_str(parse_url($r->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame($p['state'], $q['state']);
        $this->assertSame('https://aripuango.info', $q['iss']);
        $this->post('/oauth/authorize', ['nonce' => $nonce, 'decision' => 'allow'])->assertStatus(400);

        return $q['code'];
    }

    private function exchange(array $p, string $code, array $override = [])
    {
        return $this->postJson('/oauth/token', array_merge(['grant_type' => 'authorization_code', 'client_id' => $p['client_id'],
            'resource' => $p['resource'], 'redirect_uri' => $p['redirect_uri'], 'code' => $code, 'code_verifier' => $this->verifier], $override));
    }

    private function rpc(string $token)
    {
        return $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
    }

    public function test_discovery_and_untrusted_redirect_rejected(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->assertJsonPath('code_challenge_methods_supported.0', 'S256');
        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertJsonPath('resource', 'https://aripuango.info/mcp');
        $this->postJson('/mcp')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer resource_metadata="https://aripuango.info/.well-known/oauth-protected-resource", scope="videogenius"');
        foreach (['https://evil.example/callback', $this->callback.'?evil=1', 'https://chatgpt.com.evil.example/connector/oauth/id'] as $uri) {
            $this->postJson('/oauth/register', ['redirect_uris' => [$uri]])->assertStatus(400);
        }
    }

    public function test_login_owner_consent_and_parameter_checks(): void
    {
        $p = $this->params();
        $this->get('/oauth/authorize?'.http_build_query($p))->assertRedirect('/login');
        $this->assertStringContainsString('/oauth/authorize?', session('url.intended'));
        $this->actingAs(User::factory()->create())->get('/oauth/authorize?'.http_build_query($p))->assertForbidden();
        foreach (['redirect_uri' => 'https://evil.example', 'resource' => 'https://evil.example', 'code_challenge_method' => 'plain', 'scope' => 'admin'] as $field => $value) {
            $this->actingAs($this->owner)->get('/oauth/authorize?'.http_build_query(array_merge($p, [$field => $value])))->assertStatus(400);
        }
        $r = $this->get('/oauth/authorize?'.http_build_query($p))->assertOk();
        $deny = $this->post('/oauth/authorize', ['nonce' => $r->viewData('nonce'), 'decision' => 'deny'])->assertRedirect();
        $this->assertStringContainsString('error=access_denied', $deny->headers->get('Location'));
        $this->assertSame(0, DB::table('mcp_oauth_codes')->count());
    }

    public function test_code_pkce_single_use_and_access_expiry(): void
    {
        $p = $this->params();
        $code = $this->approvedCode($p);
        $this->exchange($p, $code, ['code_verifier' => str_repeat('b', 64)])->assertStatus(400);
        $this->exchange($p, $code, ['resource' => 'https://evil.example'])->assertStatus(400);
        $this->exchange($p, $code, ['redirect_uri' => 'https://evil.example'])->assertStatus(400);
        $tokens = $this->exchange($p, $code)->assertOk()->json();
        $this->exchange($p, $code)->assertStatus(400);
        $this->rpc($tokens['access_token'])->assertOk()->assertJsonCount(6, 'result.tools');
        $this->rpc($tokens['refresh_token'])->assertUnauthorized();
        $this->travel(61)->minutes();
        $this->rpc($tokens['access_token'])->assertUnauthorized();
    }

    public function test_refresh_rotates_and_replay_revokes_family(): void
    {
        $p = $this->params();
        $code = $this->approvedCode($p);
        $t = $this->exchange($p, $code)->assertOk()->json();
        $request = ['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $p['client_id'], 'resource' => $p['resource']];
        $new = $this->postJson('/oauth/token', $request)->assertOk()->json();
        $this->rpc($new['access_token'])->assertOk();
        $this->postJson('/oauth/token', $request)->assertStatus(400);
        $this->rpc($new['access_token'])->assertUnauthorized();
    }

    public function test_owner_revokes_oauth_and_expired_code_fails(): void
    {
        $p = $this->params();
        $code = $this->approvedCode($p);
        $t = $this->exchange($p, $code)->assertOk()->json();
        $this->get('/integrations/chatgpt')->assertOk()->assertSee('Revogar acesso');
        $this->post('/integrations/chatgpt/revoke')->assertRedirect();
        $this->rpc($t['access_token'])->assertUnauthorized();
        $code = $this->approvedCode($p);
        $this->travel(6)->minutes();
        $this->exchange($p, $code)->assertStatus(400);
    }

    public function test_consent_requires_csrf_in_production(): void
    {
        $p = $this->params();
        $r = $this->actingAs($this->owner)->get('/oauth/authorize?'.http_build_query($p))->assertOk();
        $this->app->instance('env', 'production');
        $this->post('/oauth/authorize', ['nonce' => $r->viewData('nonce'), 'decision' => 'allow'])->assertStatus(419);
        $this->assertSame(0, DB::table('mcp_oauth_codes')->count());
    }
}
