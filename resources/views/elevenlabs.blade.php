<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vozes — VideoGenius</title>
<style>body{font:18px system-ui;background:#f3f4f6;color:#182033;margin:0}main{max-width:650px;margin:auto;padding:24px}input,select,button{box-sizing:border-box;width:100%;padding:14px;margin:8px 0 22px;font:inherit}button{background:#4338ca;color:white;border:0;border-radius:10px}a{color:#4338ca}label{display:block}aside{background:#fff;padding:18px;border-radius:12px}.error{color:#a11}</style>
<main><a href="/videos/create">← Criar vídeo</a><h1>Conectar ElevenLabs</h1>
<p>{{ $connected ? 'Chave salva com criptografia.' : 'Cole sua chave de API para conectar sua conta.' }}</p>
@if(session('status'))<aside role="status">{{ session('status') }}</aside>@endif
@if($error)<p class="error">{{ $error }}</p>@endif
@foreach($errors->all() as $message)<p class="error" role="alert">{{ $message }}</p>@endforeach
<form method="post" action="/integrations/elevenlabs">@csrf
<label>Chave de API {{ $connected ? '(deixe vazio para manter)' : '' }}<input type="password" name="api_key" autocomplete="new-password" spellcheck="false" maxlength="300" {{ $connected ? '' : 'required' }}></label>
@if(count($voices))<label>Voz para a narração<select name="voice_id" required><option value="">Escolha uma voz</option>@foreach($voices as $voice)<option value="{{ $voice['voice_id'] }}" @selected($voiceId === $voice['voice_id'])>{{ $voice['name'] }}</option>@endforeach</select></label>@endif
<button>Salvar conexão e voz</button></form>
<aside>Conectar e consultar vozes não gera áudio. Ao escolher ElevenLabs na criação de um vídeo, a narração usa os créditos da sua conta ElevenLabs. A cota de vídeos do VideoGenius é separada.</aside>
<p><a href="/media">Enviar minhas fotos e vídeos</a></p></main></html>
