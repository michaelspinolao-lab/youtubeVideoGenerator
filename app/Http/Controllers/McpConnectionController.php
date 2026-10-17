<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class McpConnectionController extends Controller
{
    private function owner(Request $r): void
    {
        abort_unless($r->user() && config('video.allowed_email') && strcasecmp($r->user()->email, config('video.allowed_email')) === 0, 403);
    }

    public function index(Request $r)
    {
        $this->owner($r);

        return response()->view('mcp-connection', ['token' => null, 'oauthActive' => DB::table('mcp_oauth_grants')->where('user_id', $r->user()->id)->whereNull('revoked_at')->where('expires_at', '>', now())->exists(), 'active' => DB::table('mcp_tokens')->where('user_id', $r->user()->id)->where('expires_at', '>', now())->exists()])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function create(Request $r)
    {
        $this->owner($r);
        $token = 'vg_'.bin2hex(random_bytes(32));
        DB::transaction(function () use ($r, $token) {
            DB::table('mcp_tokens')->where('user_id', $r->user()->id)->delete();
            DB::table('mcp_tokens')->insert(['user_id' => $r->user()->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(90), 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->view('mcp-connection', ['token' => $token, 'active' => true])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function revoke(Request $r)
    {
        $this->owner($r);
        DB::table('mcp_tokens')->where('user_id', $r->user()->id)->delete();
        DB::table('mcp_oauth_grants')->where('user_id', $r->user()->id)->update(['revoked_at' => now()]);
        DB::table('mcp_oauth_codes')->where('user_id', $r->user()->id)->delete();

        return redirect('/integrations/chatgpt');
    }
}
