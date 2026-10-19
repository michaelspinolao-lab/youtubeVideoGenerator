<?php

namespace App\Services;

use App\Jobs\GenerateVideo;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VideoCreation
{
    public function create(User $owner, array $data): Video
    {
        $media = app(MediaImport::class)->resolve($owner, $data['media_ids'] ?? []);
        $narration = (bool) ($data['narration'] ?? true);
        if (! $narration && ! $media) {
            throw ValidationException::withMessages(['media_ids' => 'Envie seus arquivos para gerar sem narração.']);
        }
        $provider = $data['voice_provider'] ?? 'standard';
        if ($narration && $provider === 'elevenlabs' && (! $owner->elevenlabs_api_key || ! $owner->elevenlabs_voice_id)) {
            throw ValidationException::withMessages(['voice_provider' => 'Conecte a ElevenLabs e escolha uma voz em /integrations/elevenlabs.']);
        }
        $data['media'] = $media;
        $data['voice_id'] = $provider === 'elevenlabs' ? $owner->elevenlabs_voice_id : null;
        $photo = null;
        if (! empty($data['photo_id'])) {
            $photo = DB::table('video_photos')->where('id', $data['photo_id'])->where('user_id', $owner->id)->first();
            if (! $photo || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($photo->path)) {
                throw ValidationException::withMessages(['photo_id' => 'Foto não encontrada nesta conta. Envie em /photos.']);
            }
        }
        $data['photo_path'] = $photo?->path;
        $v = DB::transaction(function () use ($owner, $data) {
            $user = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            if (! $user->canGenerateVideo()) {
                throw ValidationException::withMessages(['topic' => 'Limite mensal atingido.']);
            }
            $v = $user->videos()->create(['title' => $data['topic'], 'topic' => $data['topic'], 'youtube_channel_id' => $data['youtube_channel_id'] ?? null, 'generated_script' => $data['custom_script'] ?? null, 'status' => 'generating_script']);
            $g = VideoGeneration::create(['user_id' => $user->id, 'video_id' => $v->id, 'job_id' => (string) Str::uuid(), 'topic' => $data['topic'], 'parameters' => ['duration' => $data['duration'], 'photo_path' => $data['photo_path'], 'photo_id' => $data['photo_id'] ?? null, 'media' => $data['media'], 'narration' => (bool) ($data['narration'] ?? true), 'original_audio' => (bool) ($data['original_audio'] ?? true), 'aspect_ratio' => $data['aspect_ratio'] ?? 'vertical', 'voice_provider' => $data['voice_provider'] ?? 'standard', 'voice_id' => $data['voice_id']], 'status' => 'queued']);
            $user->increment('videos_generated_this_month');
            GenerateVideo::dispatch($v->id, $g->id)->afterCommit();

            return $v;
        });

        return $v;
    }

    public function retry(User $owner, int $id, string $requestId): Video
    {
        return DB::transaction(function () use ($owner, $id, $requestId) {
            $user = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $v = $user->videos()->whereKey($id)->lockForUpdate()->firstOrFail();
            $key = 'retry:'.hash('sha256', $requestId);
            $hash = hash('sha256', (string) $id);
            $existing = DB::table('mcp_requests')->where('user_id', $user->id)->where('request_id', $key)->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['request_id' => 'Já utilizado com outro vídeo.']);
                }

                return $v;
            }
            if ($v->status !== 'failed') {
                throw ValidationException::withMessages(['video_id' => 'Somente vídeos com falha podem ser repetidos. Consulte o andamento.']);
            }
            $g = VideoGeneration::where('user_id', $user->id)->where('video_id', $id)->latest('id')->lockForUpdate()->firstOrFail();
            $v->update(['status' => 'generating_script', 'failure_reason' => null]);
            $g->update(['status' => 'queued', 'completed_at' => null, 'started_at' => null, 'error_message' => null]);
            DB::table('mcp_requests')->insert(['user_id' => $user->id, 'request_id' => $key, 'payload_hash' => $hash, 'video_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            GenerateVideo::dispatch($v->id, $g->id)->afterCommit();

            return $v;
        });
    }
}
