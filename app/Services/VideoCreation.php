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
        $v = DB::transaction(function () use ($owner, $data) {
            $user = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            if (! $user->canGenerateVideo()) {
                throw ValidationException::withMessages(['topic' => 'Limite mensal atingido.']);
            }
            $v = $user->videos()->create(['title' => $data['topic'], 'topic' => $data['topic'], 'youtube_channel_id' => $data['youtube_channel_id'] ?? null, 'generated_script' => $data['custom_script'] ?? null, 'status' => 'generating_script']);
            $g = VideoGeneration::create(['user_id' => $user->id, 'video_id' => $v->id, 'job_id' => (string) Str::uuid(), 'topic' => $data['topic'], 'parameters' => ['duration' => $data['duration']], 'status' => 'queued']);
            $user->increment('videos_generated_this_month');
            GenerateVideo::dispatch($v->id, $g->id)->afterCommit();

            return $v;
        });

        return $v;
    }
}
