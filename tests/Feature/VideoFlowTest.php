<?php

namespace Tests\Feature;

use App\Jobs\GenerateVideo;
use App\Models\User;
use App\Models\Video;
use App\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'video.pixabay_key' => 'test', 'video.gemini_key' => 'test', 'video.gemini_model' => 'test']);
    }

    public function test_creation_queues_real_job_and_keeps_owner(): void
    {
        Queue::fake();
        $u = User::factory()->create();
        $this->actingAs($u)->post('/videos', ['topic' => 'Escavadeira', 'duration' => 60, 'custom_script' => 'Teste de narração'])->assertRedirect();
        $v = Video::firstOrFail();
        $this->assertSame($u->id, $v->user_id);
        $this->assertSame('generating_script', $v->status);
        Queue::assertPushed(GenerateVideo::class);
        $this->assertDatabaseHas('video_generations', ['video_id' => $v->id, 'status' => 'queued']);
        $this->assertSame(1, $u->fresh()->videos_generated_this_month);
    }

    public function test_foreign_channel_rejected_and_no_quota_spent(): void
    {
        Queue::fake();
        $u = User::factory()->create();
        $other = User::factory()->create();
        $c = YoutubeChannel::create(['user_id' => $other->id, 'youtube_channel_id' => 'other', 'channel_title' => 'Other']);
        $this->actingAs($u)->post('/videos', ['topic' => 'Teste', 'duration' => 60, 'youtube_channel_id' => $c->id])->assertSessionHasErrors('youtube_channel_id');
        Queue::assertNothingPushed();
        $this->assertSame(0, Video::count());
    }

    public function test_files_are_private_and_not_found_for_other_user(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $other = User::factory()->create();
        $v = Video::create(['user_id' => $u->id, 'title' => 'Test', 'topic' => 'Test', 'status' => 'ready', 'final_video_path' => 'videos/1/final.mp4']);
        Storage::disk('local')->put($v->final_video_path, 'test video');
        $this->actingAs($other)->get('/videos/'.$v->id.'/download')->assertNotFound();
        $this->actingAs($u)->get('/videos/'.$v->id.'/download')->assertDownload('video-'.$v->id.'.mp4');
    }

    public function test_tokens_are_encrypted_and_never_serialized(): void
    {
        $u = User::factory()->create(['google_access_token' => 'secret-token']);
        $this->assertSame('secret-token', $u->fresh()->google_access_token);
        $this->assertStringNotContainsString('secret-token', json_encode($u));
        $this->assertNotSame('secret-token', $u->getRawOriginal('google_access_token'));
    }

    public function test_provider_config_required_before_spending_quota(): void
    {
        Queue::fake();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'video.pixabay_key' => null]);
        $u = User::factory()->create();
        $this->actingAs($u)->post('/videos', ['topic' => 'Test', 'duration' => 60])->assertSessionHas('error');
        Queue::assertNothingPushed();
        $this->assertSame(0, Video::count());
    }

    public function test_dashboard_and_form_render(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/dashboard')->assertOk();
        $this->get('/videos/create')->assertOk();
    }

    public function test_legacy_api_not_exposed(): void
    {
        $this->post('/api/n8n/video-details')->assertNotFound();
    }

    public function test_generation_failure_does_not_leave_permanent_processing(): void
    {
        $u = User::factory()->create();
        $v = Video::create(['user_id' => $u->id, 'title' => 'Test', 'topic' => 'Test', 'status' => 'generating_script']);
        $g = \App\Models\VideoGeneration::create(['user_id' => $u->id, 'video_id' => $v->id, 'topic' => 'Test', 'job_id' => 'unique']);
        (new GenerateVideo($v->id, $g->id))->failed(new \RuntimeException('sensitive provider url'));
        $this->assertSame('failed', $v->fresh()->status);
        $this->assertStringNotContainsString('sensitive', $v->fresh()->failure_reason);
    }

    public function test_google_callback_creates_account_without_password_error(): void
    {
        $google = (new \Laravel\Socialite\Two\User)->map(['id' => 'google-test', 'name' => 'Owner', 'email' => 'owner@example.com', 'avatar' => null]);
        $google->user = ['email_verified' => true];
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver->user')->once()->andReturn($google);
        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['google_id' => 'google-test', 'email' => 'owner@example.com']);
    }

    public function test_production_refuses_other_google_account_and_demo(): void
    {
        $this->app->instance('env', 'production');
        config(['video.allowed_email' => 'owner@example.com']);
        $google = (new \Laravel\Socialite\Two\User)->map(['id' => 'other-google', 'name' => 'Other', 'email' => 'other@example.com', 'avatar' => null]);
        $google->user = ['email_verified' => true];
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver->user')->once()->andReturn($google);
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/demo-login')->assertNotFound();
        $this->assertSame(0, User::count());
    }
}
