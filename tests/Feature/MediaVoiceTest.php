<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\MediaImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaVoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Queue::fake();
        config(['video.python' => 'python3', 'video.pixabay_key' => null, 'video.gemini_key' => null]);
    }

    private function fixture(string $kind = 'photo'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vgtest');
        $command = $kind === 'photo' ? ['python3', '-c', "from PIL import Image; import sys; Image.new('RGB',(200,300),'red').save(sys.argv[1],'PNG')", $path]
            : ['ffmpeg', '-y', '-f', 'lavfi', '-i', 'color=c=red:s=160x120:d=1', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1', '-c:v', 'libx264', '-threads', '1', '-c:a', 'aac', '-f', 'mov', $path];
        (new \Symfony\Component\Process\Process($command))->mustRun();

        return $path;
    }

    public function test_media_upload_private_deduplicated_and_without_quota(): void
    {
        $u = User::factory()->create();
        $path = $this->fixture();
        try {
            $response = $this->actingAs($u)->postJson('/media', ['file' => new UploadedFile($path, 'my-photo.png', null, null, true)])->assertOk();
            $id = $response->json('media_id');
            $this->postJson('/media', ['file' => new UploadedFile($path, 'my-photo.png', null, null, true)])->assertJsonPath('media_id', $id)->assertJsonPath('already_uploaded', true);
            $this->get('/media')->assertOk()->assertSee('my-photo.png');
            $this->get('/media/'.$id)->assertOk();
            $this->actingAs(User::factory()->create())->get('/media/'.$id)->assertNotFound();
            $this->assertSame(0, $u->fresh()->videos_generated_this_month);
            $this->assertDatabaseCount('video_media', 1);
        } finally {
            unlink($path);
        }
    }

    public function test_mov_and_no_narration_creation_and_foreign_media_rejected(): void
    {
        $u = User::factory()->create();
        $path = $this->fixture('video');
        try {
            $id = app(MediaImport::class)->store($u, $path, 'iphone.mov')['media_id'];
            $this->assertDatabaseHas('video_media', ['id' => $id, 'kind' => 'video', 'has_audio' => 1, 'mime_type' => 'video/quicktime']);
            $data = ['topic' => 'Meus vídeos', 'duration' => 10, 'media_ids' => [$id], 'narration' => false, 'original_audio' => true];
            $this->actingAs(User::factory()->create())->post('/videos', $data)->assertSessionHasErrors('media_ids');
            $this->actingAs($u)->post('/videos', $data)->assertRedirect();
            $g = VideoGeneration::firstOrFail();
            $this->assertFalse($g->parameters['narration']);
            $this->assertTrue($g->parameters['original_audio']);
            $this->assertSame($id, $g->parameters['media'][0]['id']);
            $this->assertSame(1, $u->fresh()->videos_generated_this_month);
        } finally {
            unlink($path);
        }
    }

    public function test_invalid_file_and_attachment_host_are_rejected(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/media', ['file' => UploadedFile::fake()->createWithContent('fake.mp4', 'not video')])->assertUnprocessable();
        Http::fake();
        try {
            app(MediaImport::class)->fromAttachment($u, ['download_url' => 'https://localhost/secret', 'file_id' => 'test']);
            $this->fail('accepted host');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Http::assertNothingSent();
        }
    }

    public function test_elevenlabs_key_encrypted_not_echoed_and_setup_only_reads(): void
    {
        $u = User::factory()->create();
        $secret = 'test_key_never_expose_123456789';
        Http::fake(['api.elevenlabs.io/v2/voices*' => Http::response(['voices' => [['voice_id' => 'voice123', 'name' => 'Voz teste']]])]);
        $this->actingAs($u)->post('/integrations/elevenlabs', ['api_key' => $secret, 'voice_id' => 'voice123'])->assertRedirect();
        $fresh = $u->fresh();
        $this->assertSame($secret, $fresh->elevenlabs_api_key);
        $this->assertStringNotContainsString($secret, $fresh->getRawOriginal('elevenlabs_api_key'));
        $this->assertStringNotContainsString($secret, $fresh->toJson());
        $this->get('/integrations/elevenlabs')->assertOk()->assertDontSee($secret)->assertSee('Voz teste');
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('xi-api-key', $secret));
        config(['video.pixabay_key' => 'test']);
        $this->post('/videos', ['topic' => 'Teste', 'duration' => 30, 'custom_script' => 'Olá!', 'voice_provider' => 'elevenlabs'])->assertRedirect();
        $this->assertSame('voice123', VideoGeneration::firstOrFail()->parameters['voice_id']);
        $this->assertStringNotContainsString($secret, VideoGeneration::firstOrFail()->toJson());
    }

    public function test_failed_key_never_flashes_or_replaces_saved_key(): void
    {
        $u = User::factory()->create();
        $u->elevenlabs_api_key = 'previous_key';
        $u->save();
        Http::fake(['*' => Http::response([], 401)]);
        $this->actingAs($u)->from('/integrations/elevenlabs')->post('/integrations/elevenlabs', ['api_key' => 'invalid_key_123456789012345'])->assertSessionHasErrors('api_key')->assertSessionMissing('_old_input.api_key');
        $this->assertSame('previous_key', $u->fresh()->elevenlabs_api_key);
    }

    public function test_photo_id_validation_regression(): void
    {
        $u = User::factory()->create();
        $path = $this->fixture();
        try {
            $id = app(\App\Services\PhotoImport::class)->store($u, file_get_contents($path))['photo_id'];
            $this->actingAs($u)->post('/videos', ['topic' => 'Foto', 'duration' => 30, 'custom_script' => 'Olá.', 'photo_id' => $id])->assertRedirect();
            $this->assertSame(1, $u->fresh()->videos_generated_this_month);
        } finally {
            unlink($path);
        }
    }

    public function test_job_renders_own_media_without_external_voice_or_stock(): void
    {
        $u = User::factory()->create();
        $path = $this->fixture();
        try {
            $id = app(MediaImport::class)->store($u, $path, 'photo.png')['media_id'];
            $video = app(\App\Services\VideoCreation::class)->create($u, ['topic' => 'Foto', 'duration' => 10, 'media_ids' => [$id], 'narration' => false]);
            $g = VideoGeneration::firstOrFail();
            (new \App\Jobs\GenerateVideo($video->id, $g->id))->handle();
            $this->assertSame('ready', $video->fresh()->status);
            $this->assertTrue(Storage::disk('local')->exists($video->fresh()->final_video_path));
            $this->assertGreaterThanOrEqual(10, $video->fresh()->duration_seconds);
        } finally {
            unlink($path);
        }
    }
}
