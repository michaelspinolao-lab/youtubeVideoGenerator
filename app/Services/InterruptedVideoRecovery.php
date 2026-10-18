<?php

namespace App\Services;

use App\Jobs\GenerateVideo;
use App\Models\Video;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\DB;

class InterruptedVideoRecovery
{
    /** Run only before the worker starts, with exclusive access to the personal disk. */
    public function recover(): int
    {
        if (config('queue.default') !== 'database' || ! config('video.allowed_email')) {
            return 0;
        }

        $connection = DB::connection(config('queue.connections.database.connection'));
        $table = config('queue.connections.database.table', 'jobs');
        $recovered = 0;
        foreach ($connection->table($table)->whereNotNull('reserved_at')->get() as $row) {
            $payload = json_decode($row->payload, true);
            if (($payload['data']['commandName'] ?? null) !== GenerateVideo::class) {
                continue;
            }
            $job = @unserialize($payload['data']['command'] ?? '', ['allowed_classes' => [GenerateVideo::class]]);
            if (! $job instanceof GenerateVideo || ! isset($job->videoId, $job->generationId)) {
                continue;
            }
            $video = Video::with('user')->find($job->videoId);
            $generation = VideoGeneration::whereKey($job->generationId)->where('video_id', $job->videoId)->first();
            if (! $video || ! $generation || $generation->user_id !== $video->user_id ||
                strcasecmp($video->user->email, config('video.allowed_email')) !== 0) {
                continue;
            }
            if ($video->status === 'ready' && $generation->status === 'completed') {
                // The previous worker may have finished just before acknowledging the queue job.
                $connection->table($table)->where('id', $row->id)->delete();
                continue;
            }
            if ($generation->status !== 'processing' || ! in_array($video->status, ['generating_script', 'fetching_visuals', 'generating_voiceover', 'assembling_video'], true)) {
                continue;
            }
            // Reuse the existing queue row. Do not create a video or increment its quota.
            $connection->table($table)->where('id', $row->id)->update([
                'reserved_at' => null, 'attempts' => 0, 'available_at' => now()->timestamp,
            ]);
            $recovered++;
        }

        return $recovered;
    }
}
