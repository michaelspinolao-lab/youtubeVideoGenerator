<?php

namespace Tests\Feature;

use App\Jobs\GenerateVideo;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoGeneration;
use App\Services\InterruptedVideoRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InterruptedVideoRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function job(User $user, string $state = 'processing'): array
    {
        $video = Video::create(['user_id' => $user->id, 'title' => 'Moda', 'topic' => 'Moda', 'status' => $state === 'completed' ? 'ready' : 'assembling_video']);
        $generation = VideoGeneration::create(['user_id' => $user->id, 'video_id' => $video->id, 'topic' => 'Moda', 'job_id' => (string) \Illuminate\Support\Str::uuid(), 'status' => $state]);
        $id = DB::table('jobs')->insertGetId(['queue' => 'default', 'payload' => json_encode(['data' => ['commandName' => GenerateVideo::class, 'command' => serialize(new GenerateVideo($video->id, $generation->id))]]), 'attempts' => 1, 'reserved_at' => now()->timestamp, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

        return [$id, $video];
    }

    public function test_boot_resumes_only_interrupted_owner_jobs_without_duplication_or_quota(): void
    {
        $owner = User::factory()->create(['videos_generated_this_month' => 1]);
        config(['queue.default' => 'database', 'video.allowed_email' => $owner->email]);
        [$interrupted] = $this->job($owner);
        [$foreign] = $this->job(User::factory()->create());
        [$failed] = $this->job($owner, 'failed');
        $unrelated = DB::table('jobs')->insertGetId(['queue' => 'default', 'payload' => json_encode(['data' => ['commandName' => 'UnrelatedJob']]), 'attempts' => 1, 'reserved_at' => now()->timestamp, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $this->assertSame(1, app(InterruptedVideoRecovery::class)->recover());
        $this->assertSame(0, app(InterruptedVideoRecovery::class)->recover());
        $this->assertDatabaseHas('jobs', ['id' => $interrupted, 'reserved_at' => null, 'attempts' => 0]);
        foreach ([$foreign, $failed, $unrelated] as $id) {
            $this->assertNotNull(DB::table('jobs')->where('id', $id)->value('reserved_at'));
        }
        $this->assertSame(4, DB::table('jobs')->count());
        $this->assertSame(1, $owner->fresh()->videos_generated_this_month);
    }

    public function test_completed_jobs_are_acknowledged_without_generating_again(): void
    {
        $owner = User::factory()->create();
        config(['queue.default' => 'database', 'video.allowed_email' => $owner->email]);
        [$id, $video] = $this->job($owner, 'completed');
        $this->assertSame(0, app(InterruptedVideoRecovery::class)->recover());
        $this->assertDatabaseMissing('jobs', ['id' => $id]);
        $this->assertSame('ready', $video->fresh()->status);
    }

    public function test_other_queue_backends_and_unconfigured_owner_are_untouched(): void
    {
        $owner = User::factory()->create();
        [$id] = $this->job($owner);
        config(['queue.default' => 'redis', 'video.allowed_email' => $owner->email]);
        $this->assertSame(0, app(InterruptedVideoRecovery::class)->recover());
        config(['queue.default' => 'database', 'video.allowed_email' => null]);
        $this->assertSame(0, app(InterruptedVideoRecovery::class)->recover());
        $this->assertNotNull(DB::table('jobs')->where('id', $id)->value('reserved_at'));
    }
}
