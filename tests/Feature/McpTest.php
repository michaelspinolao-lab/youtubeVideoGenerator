<?php

namespace Tests\Feature;

use App\Jobs\GenerateVideo;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class McpTest extends TestCase
{
    use RefreshDatabase;

    private string $key = 'vg_test_private_key';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'video.allowed_email' => 'owner@example.com', 'video.pixabay_key' => 'test']);
        $this->owner = User::factory()->create(['email' => 'owner@example.com', 'monthly_video_quota' => 10]);
        DB::table('mcp_tokens')->insert(['user_id' => $this->owner->id, 'token_hash' => hash('sha256', $this->key), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function rpc($method, $params = [])
    {
        return $this->withToken($this->key)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    }

    private function tool($name, $args = [])
    {
        return $this->rpc('tools/call', ['name' => $name, 'arguments' => $args]);
    }

    public function test_auth_is_required_even_with_web_session_and_expired_keys_fail(): void
    {
        $this->actingAs($this->owner)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
        DB::table('mcp_tokens')->update(['expires_at' => now()->subSecond()]);
        $this->rpc('tools/list')->assertUnauthorized();
    }

    public function test_protocol_discovery_and_bad_origin(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '2025-06-18'])->assertOk()->assertJsonPath('result.serverInfo.name', 'videogenius');
        $this->rpc('tools/list')->assertJsonCount(5, 'result.tools');
        $this->rpc('bogus')->assertJsonPath('error.code', -32601);
        $this->withHeader('Origin', 'https://evil.example')->rpc('tools/list')->assertForbidden();
    }

    public function test_creation_idempotency_validation_and_quota(): void
    {
        Queue::fake();
        $a = ['topic' => 'Trator', 'duration' => 30, 'custom_script' => 'Um trator trabalhando no campo.', 'request_id' => 'request_123456'];
        $this->tool('create_video', $a)->assertJsonPath('result.isError', false);
        $this->tool('create_video', $a)->assertJsonPath('result.isError', false);
        $this->assertSame(1, Video::count());
        $this->assertSame(1, $this->owner->fresh()->videos_generated_this_month);
        Queue::assertPushed(GenerateVideo::class, 1);
        $this->tool('create_video', array_merge($a, ['topic' => 'Outro']))->assertJsonPath('result.isError', true);
        $this->tool('create_video', array_merge($a, ['request_id' => 'request_bad', 'duration' => 999]))->assertJsonPath('result.isError', true);
        $this->owner->update(['monthly_video_quota' => 1]);
        $this->tool('create_video', array_merge($a, ['request_id' => 'request_new']))->assertJsonPath('result.isError', true);
        $this->assertSame(1, Video::count());
    }

    public function test_owner_isolation_and_signed_download_expiration(): void
    {
        Storage::fake('local');
        $v = Video::create(['user_id' => $this->owner->id, 'title' => 'Mine', 'topic' => 'Mine', 'status' => 'ready', 'final_video_path' => 'videos/test/final.mp4']);
        Storage::disk('local')->put($v->final_video_path, 'video bytes');
        $other = Video::create(['user_id' => User::factory()->create()->id, 'title' => 'Private', 'topic' => 'Private', 'status' => 'ready']);
        $this->tool('get_video', ['video_id' => $other->id])->assertJsonPath('result.isError', true);
        $this->tool('get_video_download', ['video_id' => $other->id])->assertJsonPath('result.isError', true);
        $result = $this->tool('get_video_download', ['video_id' => $v->id])->assertJsonPath('result.isError', false)->json('result.content.0.text');
        $url = json_decode($result, true)['download_url'];
        $this->get('/mcp/files/'.$v->id)->assertForbidden();
        $this->get($url)->assertDownload('video-'.$v->id.'.mp4');
        $this->travel(31)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_connection_page_requires_owner_and_rotates_and_revokes(): void
    {
        $this->get('/integrations/chatgpt')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post('/integrations/chatgpt')->assertForbidden();
        $this->actingAs($this->owner)->post('/integrations/chatgpt')->assertOk()->assertSee('Sua chave de conexão');
        $this->rpc('tools/list')->assertUnauthorized();
        $this->assertSame(1, DB::table('mcp_tokens')->count());
        $this->actingAs($this->owner)->post('/integrations/chatgpt/revoke')->assertRedirect('/integrations/chatgpt');
        $this->assertSame(0,DB::table('mcp_tokens')->count());
    }
}
