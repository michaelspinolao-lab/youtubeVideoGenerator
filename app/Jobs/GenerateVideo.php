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
    private const ERRORS = [
        'photo_missing' => 'A foto original não foi encontrada. Envie novamente em /photos.',
        'no_stock_footage' => 'O Pixabay não encontrou clipes para este tema. Use poucas palavras ou envie uma foto.',
        'script_provider_failed' => 'Falha ao criar o roteiro com Gemini. Envie um roteiro próprio.',
        'stock_provider_failed' => 'Falha ao buscar ou baixar os clipes do Pixabay. Verifique a chave e a disponibilidade do provedor.',
        'voice_provider_failed' => 'O serviço de narração não respondeu ou recusou a solicitação.',
        'render_failed' => 'Falha na montagem do MP4. Verifique a imagem e os recursos do servidor.',
        'invalid_audio' => 'A duração da narração é inválida.',
        'invalid_output' => 'O vídeo final não passou na verificação de duração.',
        'worker_failed' => 'O processo de geração não iniciou corretamente. Verifique as dependências do servidor.',
    ];

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
        $process->setInput(json_encode(['topic' => $v->topic, 'script' => $v->generated_script, 'duration' => $g->parameters['duration'], 'directory' => Storage::disk('local')->path($dir), 'photo' => empty($g->parameters['photo_path']) ? null : Storage::disk('local')->path($g->parameters['photo_path'])]));
        $process->setTimeout(1700);
        $buffer = '';
        $errorCode = 'worker_failed';
        $process->run(function ($type, $chunk) use ($v, &$buffer, &$errorCode) {
            if ($type !== Process::OUT) {
                return;
            }
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $event = json_decode($line, true);
                if (isset(self::ERRORS[$event['error_code'] ?? ''])) {
                    $errorCode = $event['error_code'];
                }
                if (in_array($event['status'] ?? '', ['generating_script', 'fetching_visuals', 'generating_voiceover', 'assembling_video'])) {
                    $v->update(['status' => $event['status']]);
                }
            }
        });
        if (! $process->isSuccessful()) {
            throw new \RuntimeException($errorCode);
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
        $code = isset(self::ERRORS[$e?->getMessage() ?? '']) ? $e->getMessage() : 'worker_failed';
        Video::whereKey($this->videoId)->update(['status' => 'failed', 'failure_reason' => self::ERRORS[$code]]);
        VideoGeneration::whereKey($this->generationId)->update(['status' => 'failed', 'completed_at' => now(), 'error_message' => $code]);
        \Illuminate\Support\Facades\Log::warning('Video generation failed', ['video_id' => $this->videoId, 'error_code' => $code]);
    }
}
