<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\VideoGeneration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class GenerateVideo implements ShouldQueue
{
    use Queueable;

    public $timeout = 1800;

    public $tries = 1;

    public $failOnTimeout = true;

    public function __construct(public int $videoId, public int $generationId) {}

    public function handle(): void
    {
        $v = Video::findOrFail($this->videoId);
        $g = VideoGeneration::findOrFail($this->generationId);
        $g->update(['status' => 'processing', 'started_at' => now()]);
        $dir = 'videos/'.$v->id;
        Storage::disk('local')->makeDirectory($dir);
        $process = new Process([config('video.python'), base_path('scripts/generate.py')], base_path(), [
            'GEMINI_API_KEY' => config('video.gemini_key') ?? '',
            'GEMINI_MODEL' => config('video.gemini_model') ?? '',
            'PIXABAY_API_KEY' => config('video.pixabay_key') ?? '',
        ]);
        $process->setInput(json_encode(['topic' => $v->topic, 'script' => $v->generated_script, 'duration' => $g->parameters['duration'], 'directory' => Storage::disk('local')->path($dir)]));
        $process->setTimeout(1700);
        $buffer = '';
        $process->run(function ($type, $chunk) use ($v, &$buffer) {
            if ($type !== Process::OUT) {
                return;
            }
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $event = json_decode($line, true);
                if (in_array($event['status'] ?? '', ['generating_script', 'fetching_visuals', 'generating_voiceover', 'assembling_video'])) {
                    $v->update(['status' => $event['status']]);
                }
            }
        });
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Falha na geração. Confira as chaves e a disponibilidade dos provedores.');
        }
        $result = json_decode(file_get_contents(Storage::disk('local')->path($dir.'/result.json')), true, flags: JSON_THROW_ON_ERROR);
        if (! Storage::disk('local')->exists($dir.'/final.mp4')) {
            throw new \RuntimeException('O vídeo não foi gerado.');
        }
        $v->update(['status' => 'ready', 'final_video_path' => $dir.'/final.mp4', 'generated_script' => $result['script'], 'duration_seconds' => (int) ceil($result['duration']), 'failure_reason' => null]);
        $g->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function failed(?Throwable $e): void
    {
        Video::whereKey($this->videoId)->update(['status' => 'failed', 'failure_reason' => 'Não foi possível concluir. Verifique a configuração dos provedores e tente criar um novo vídeo.']);
        VideoGeneration::whereKey($this->generationId)->update(['status' => 'failed', 'completed_at' => now(), 'error_message' => 'Generation failed']);
    }
}
