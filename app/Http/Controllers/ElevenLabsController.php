<?php

namespace App\Http\Controllers;

use App\Services\ElevenLabs;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ElevenLabsController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        $voices = [];
        $error = null;
        if ($user->elevenlabs_api_key) {
            try {
                $voices = app(ElevenLabs::class)->voices($user->elevenlabs_api_key);
            } catch (ValidationException $e) {
                $error = $e->errors()['api_key'][0];
            }
        }

        return view('elevenlabs', ['connected' => (bool) $user->elevenlabs_api_key, 'voices' => $voices, 'voiceId' => $user->elevenlabs_voice_id, 'error' => $error]);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['api_key' => 'nullable|string|min:20|max:300|regex:/\A[a-zA-Z0-9_-]+\z/', 'voice_id' => 'nullable|string|max:100|regex:/\A[a-zA-Z0-9_-]+\z/']);
        $user = $r->user();
        $key = $data['api_key'] ?? $user->elevenlabs_api_key;
        if (! $key) {
            throw ValidationException::withMessages(['api_key' => 'Cole sua chave da ElevenLabs.']);
        }
        $voices = app(ElevenLabs::class)->voices($key);
        $voice = $data['voice_id'] ?? null;
        if ($voice && ! in_array($voice, array_column($voices, 'voice_id'), true)) {
            throw ValidationException::withMessages(['voice_id' => 'Escolha uma voz disponível nesta conta.']);
        }
        $user->elevenlabs_api_key = $key;
        $user->elevenlabs_voice_id = $voice ?: (in_array($user->elevenlabs_voice_id, array_column($voices, 'voice_id'), true) ? $user->elevenlabs_voice_id : null);
        $user->save();

        return redirect('/integrations/elevenlabs')->with('status', 'Conexão salva. Escolha uma voz e salve. Nenhum crédito de geração foi usado.');
    }
}
