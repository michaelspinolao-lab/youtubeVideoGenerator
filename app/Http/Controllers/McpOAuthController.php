<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Private installation: authorization code + S256 PKCE, ChatGPT callbacks only. */
class McpOAuthController extends Controller
{
    public static function issuer(): string
    {
        return rtrim(config('app.url'), '/');
    }

    public static function resource(): string
    {
        return self::issuer().'/mcp';
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json($data, $status, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    private function error(string $error, int $status = 400)
    {
        return $this->json(['error' => $error], $status);
    }

    private function isOwner(?User $u): bool
    {
        return $u && config('video.allowed_email') && strcasecmp($u->email, config('video.allowed_email')) === 0;
    }

    public function metadata()
    {
        return $this->json([
            'issuer' => self::issuer(), 'authorization_endpoint' => self::issuer().'/oauth/authorize',
            'token_endpoint' => self::issuer().'/oauth/token', 'registration_endpoint' => self::issuer().'/oauth/register',
            'response_types_supported' => ['code'], 'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'], 'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => ['videogenius'], 'authorization_response_iss_parameter_supported' => true,
        ]);
    }

    public function resourceMetadata()
    {
        return $this->json(['resource' => self::resource(), 'authorization_servers' => [self::issuer()],
            'scopes_supported' => ['videogenius'], 'bearer_methods_supported' => ['header']]);
    }

    public function register(Request $r)
    {
        $v = Validator::make($r->all(), ['redirect_uris' => 'required|array|min:1|max:5',
            'redirect_uris.*' => 'required|string|max:512', 'token_endpoint_auth_method' => 'sometimes|in:none',
            'grant_types' => 'sometimes|array', 'grant_types.*' => 'in:authorization_code,refresh_token',
            'response_types' => 'sometimes|array', 'response_types.*' => 'in:code']);
        if ($v->fails()) {
            return $this->error('invalid_client_metadata');
        }
        // Exact stable callback, or the documented callback-ID-specific route. No arbitrary hosts, queries or fragments.
        foreach ($r->input('redirect_uris') as $uri) {
            if ($uri !== 'https://chatgpt.com/connector_platform_oauth_redirect'
                && ! preg_match('~\Ahttps://chatgpt\.com/connector/oauth/[A-Za-z0-9_-]{1,128}\z~D', $uri)) {
                return $this->error('invalid_redirect_uri');
            }
        }
        $id = bin2hex(random_bytes(24));
        DB::table('mcp_oauth_clients')->insert(['id' => $id, 'redirect_uris' => json_encode($r->input('redirect_uris')), 'created_at' => now()]);

        return $this->json(['client_id' => $id, 'client_id_issued_at' => time(), 'redirect_uris' => $r->input('redirect_uris'),
            'token_endpoint_auth_method' => 'none', 'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code']], 201);
    }

    private function validAuthorization(Request $r): bool
    {
        $v = Validator::make($r->all(), ['client_id' => 'required|string|max:64', 'redirect_uri' => 'required|string|max:512',
            'response_type' => 'required|in:code', 'code_challenge_method' => 'required|in:S256',
            'code_challenge' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{43}\z/D'],
            'state' => 'required|string|max:2048', 'resource' => 'required|string|max:512', 'scope' => 'sometimes|in:videogenius']);
        if ($v->fails() || $r->input('resource') !== self::resource()) {
            return false;
        }
        $c = DB::table('mcp_oauth_clients')->where('id', $r->input('client_id'))->first();

        return $c && in_array($r->input('redirect_uri'), json_decode($c->redirect_uris, true), true);
    }

    public function authorize(Request $r)
    {
        if (! $this->validAuthorization($r)) {
            return $this->error('invalid_request');
        }
        if (! $r->user()) {
            $r->session()->put('url.intended', self::issuer().'/oauth/authorize?'.http_build_query($r->query()));

            return redirect('/login');
        }
        abort_unless($this->isOwner($r->user()), 403);
        // Store a separate approval request per browser tab; never trust hidden form OAuth parameters.
        $nonce = bin2hex(random_bytes(24));
        $r->session()->put('mcp_oauth.'.$nonce, ['params' => $r->only(['client_id', 'redirect_uri', 'state', 'code_challenge', 'resource']), 'until' => time() + 600]);

        return response()->view('mcp-oauth-consent', ['nonce' => $nonce])->withHeaders([
            'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "frame-ancestors 'none'; form-action 'self' https://chatgpt.com"]);
    }

    public function approve(Request $r)
    {
        abort_unless($this->isOwner($r->user()), 403);
        $r->validate(['nonce' => ['required', 'regex:/\A[a-f0-9]{48}\z/D'], 'decision' => 'required|in:allow,deny']);
        $pending = $r->session()->pull('mcp_oauth.'.$r->input('nonce'));
        if (! $pending || $pending['until'] < time()) {
            return $this->error('invalid_request');
        }
        $p = $pending['params'];
        $out = ['state' => $p['state'], 'iss' => self::issuer()];
        if ($r->input('decision') === 'deny') {
            $out['error'] = 'access_denied';
        } else {
            $code = bin2hex(random_bytes(32));
            DB::table('mcp_oauth_codes')->insert(['code_hash' => hash('sha256', $code), 'client_id' => $p['client_id'],
                'user_id' => $r->user()->id, 'redirect_uri' => $p['redirect_uri'], 'challenge' => $p['code_challenge'],
                'resource' => $p['resource'], 'expires_at' => now()->addMinutes(5)]);
            $out['code'] = $code;
        }

        return redirect()->away($p['redirect_uri'].'?'.http_build_query($out))->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    private function issue(int $grantId)
    {
        $access = 'vgo_'.bin2hex(random_bytes(32));
        $refresh = 'vgr_'.bin2hex(random_bytes(32));
        $grant = DB::table('mcp_oauth_grants')->find($grantId);
        DB::table('mcp_oauth_tokens')->insert([
            ['token_hash' => hash('sha256', $access), 'grant_id' => $grantId, 'type' => 'access', 'expires_at' => now()->addHour(), 'used_at' => null],
            ['token_hash' => hash('sha256', $refresh), 'grant_id' => $grantId, 'type' => 'refresh', 'expires_at' => $grant->expires_at, 'used_at' => null],
        ]);

        return $this->json(['access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => 3600,
            'refresh_token' => $refresh, 'scope' => 'videogenius']);
    }

    public function token(Request $r)
    {
        $v = Validator::make($r->all(), ['grant_type' => 'required|in:authorization_code,refresh_token',
            'client_id' => 'required|string|max:64', 'resource' => 'required|string|max:512']);
        if ($v->fails()) {
            return $this->error('invalid_request');
        }
        if ($r->input('resource') !== self::resource()) {
            return $this->error('invalid_target');
        }
        if (! DB::table('mcp_oauth_clients')->where('id', $r->input('client_id'))->exists()) {
            return $this->error('invalid_client');
        }

        return DB::transaction(function () use ($r) {
            if ($r->input('grant_type') === 'authorization_code') {
                $v = Validator::make($r->all(), ['code' => 'required|string|max:128', 'redirect_uri' => 'required|string|max:512',
                    'code_verifier' => ['required', 'string', 'regex:/\A[A-Za-z0-9._~-]{43,128}\z/D']]);
                if ($v->fails()) {
                    return $this->error('invalid_grant');
                }
                $hash = hash('sha256', $r->input('code'));
                $code = DB::table('mcp_oauth_codes')->where('code_hash', $hash)->lockForUpdate()->first();
                $challenge = rtrim(strtr(base64_encode(hash('sha256', $r->input('code_verifier'), true)), '+/', '-_'), '=');
                if (! $code || $code->expires_at <= now() || $code->client_id !== $r->input('client_id')
                    || $code->redirect_uri !== $r->input('redirect_uri') || $code->resource !== self::resource()
                    || ! hash_equals($code->challenge, $challenge) || ! $this->isOwner(User::find($code->user_id))) {
                    return $this->error('invalid_grant');
                }
                if (DB::table('mcp_oauth_codes')->where('code_hash', $hash)->delete() !== 1) {
                    return $this->error('invalid_grant');
                }
                $id = DB::table('mcp_oauth_grants')->insertGetId(['client_id' => $code->client_id, 'user_id' => $code->user_id,
                    'resource' => self::resource(), 'scope' => 'videogenius', 'expires_at' => now()->addDays(90)]);

                return $this->issue($id);
            }
            if (! is_string($r->input('refresh_token')) || strlen($r->input('refresh_token')) > 128) {
                return $this->error('invalid_grant');
            }
            if ($r->has('scope') && $r->input('scope') !== 'videogenius') {
                return $this->error('invalid_scope');
            }
            $hash = hash('sha256', $r->input('refresh_token'));
            $token = DB::table('mcp_oauth_tokens')->where('token_hash', $hash)->where('type', 'refresh')->lockForUpdate()->first();
            $g = $token ? DB::table('mcp_oauth_grants')->where('id', $token->grant_id)->lockForUpdate()->first() : null;
            if (! $g || $g->client_id !== $r->input('client_id') || $g->resource !== self::resource() || $g->revoked_at
                || $g->expires_at <= now() || $token->expires_at <= now() || ! $this->isOwner(User::find($g->user_id))) {
                return $this->error('invalid_grant');
            }
            if ($token->used_at) {
                DB::table('mcp_oauth_grants')->where('id', $g->id)->update(['revoked_at' => now()]);

                return $this->error('invalid_grant');
            }
            if (DB::table('mcp_oauth_tokens')->where('token_hash', $hash)->whereNull('used_at')->update(['used_at' => now()]) !== 1) {
                return $this->error('invalid_grant');
            }

            return $this->issue($g->id);
        });
    }

    public static function tokenOwner(string $bearer): ?User
    {
        $t = DB::table('mcp_oauth_tokens as t')->join('mcp_oauth_grants as g', 'g.id', '=', 't.grant_id')
            ->where('t.token_hash', hash('sha256', $bearer))->where('t.type', 'access')->where('t.expires_at', '>', now())
            ->whereNull('g.revoked_at')->where('g.expires_at', '>', now())->where('g.resource', self::resource())
            ->where('g.scope', 'videogenius')->select('g.user_id')->first();

        return $t ? User::find($t->user_id) : null;
    }
}
