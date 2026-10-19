<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ElevenLabs
{
    // Only read requests during connection setup. Never generate a charged preview.
    public function voices(string $key): array
    {
        try {
            $response = Http::withHeaders(['xi-api-key' => $key])->acceptJson()
                ->connectTimeout(10)->timeout(25)->withoutRedirecting()
                ->get('https://api.elevenlabs.io/v2/voices', ['page_size' => 100]);
            if (! $response->successful()) {
                throw new \RuntimeException('provider_rejected');
            }

            return collect($response->json('voices', []))->map(fn ($v) => [
                'voice_id' => $v['voice_id'], 'name' => $v['name'],
            ])->filter(fn ($v) => preg_match('/\A[a-zA-Z0-9_-]{1,100}\z/', $v['voice_id']))->values()->all();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['api_key' => 'Não foi possível consultar as vozes. Confira a chave e a permissão Voices: Read na ElevenLabs. Nenhum áudio foi gerado.']);
        }
    }
}
