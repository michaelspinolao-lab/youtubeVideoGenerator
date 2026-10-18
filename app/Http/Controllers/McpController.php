<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use App\Services\VideoCreation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class McpController extends Controller
{
    private function owner(Request $r): ?User
    {
        $bearer = $r->bearerToken();
        if (! $bearer || strlen($bearer) > 200) {
            return null;
        }
        $token = DB::table('mcp_tokens')->where('token_hash', hash('sha256', $bearer))->where('expires_at', '>', now())->first();
        $u = $token ? User::find($token->user_id) : McpOAuthController::tokenOwner($bearer);

        return $u && config('video.allowed_email') && strcasecmp($u->email, config('video.allowed_email')) === 0 ? $u : null;
    }

    public function handle(Request $r)
    {
        // Reject browser origins: clients use server-to-server HTTPS and bearer authentication.
        if ($r->header('Origin') && $r->header('Origin') !== rtrim(config('app.url'), '/')) {
            return response()->json(['error' => 'Forbidden origin'], 403);
        }
        $u = $this->owner($r);
        if (! $u) {
            return response()->json(['error' => 'Uma chave VideoGenius válida é necessária. Crie em /integrations/chatgpt.'], 401, ['WWW-Authenticate' => 'Bearer resource_metadata="'.McpOAuthController::issuer().'/.well-known/oauth-protected-resource", scope="videogenius"', 'Cache-Control' => 'no-store']);
        }
        if (! $r->isMethod('post')) {
            return response('', 405, ['Allow' => 'POST']);
        }
        if (strlen($r->getContent()) > 32768) {
            return response()->json(['error' => 'Request too large'], 413);
        }
        $m = json_decode($r->getContent(), true);
        if (! is_array($m) || array_is_list($m) || ($m['jsonrpc'] ?? null) !== '2.0' || ! is_string($m['method'] ?? null)) {
            return $this->error(null, -32600, 'Invalid request');
        }
        $id = $m['id'] ?? null;
        if (! array_key_exists('id', $m)) {
            return response('', 202);
        }
        if (! is_string($id) && ! is_int($id) && $id !== null) {
            return $this->error(null, -32600, 'Invalid id');
        }
        $params = $m['params'] ?? [];
        if (! is_array($params)) {
            return $this->error($id, -32602, 'Invalid params');
        }
        if ($m['method'] === 'initialize') {
            $version = in_array($params['protocolVersion'] ?? '', ['2024-11-05', '2025-03-26', '2025-06-18', '2025-11-25']) ? $params['protocolVersion'] : '2025-06-18';

            return $this->result($id, ['protocolVersion' => $version, 'capabilities' => ['tools' => ['listChanged' => false]], 'serverInfo' => ['name' => 'videogenius', 'version' => '1.2.1'], 'instructions' => 'Para fotos anexadas ao chat, use upload_photo e passe o photo_id retornado em create_video. Escreva a narração em custom_script; isso dispensa Gemini. prepare_video confere o pedido sem consumir cota. Gere apenas quando solicitado. Preserve request_id nas repetições. retry_video tenta novamente um vídeo falho sem nova cota. Não repita falhas automaticamente. Fotos recebem zoom, não animação corporal. Esta integração gera MP4 e não publica em redes sociais.']);
        }
        if ($m['method'] === 'ping') {
            return $this->result($id, (object) []);
        }
        if ($m['method'] === 'tools/list') {
            return $this->result($id, ['tools' => $this->tools()]);
        }
        if ($m['method'] !== 'tools/call') {
            return $this->error($id, -32601, 'Method not found');
        }
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];
        if (! is_string($name) || ! is_array($args)) {
            return $this->error($id, -32602, 'Invalid tool arguments');
        }
        if (! in_array($name, array_column($this->tools(), 'name'), true)) {
            return $this->error($id, -32602, 'Unknown tool');
        }
        try {
            $data = $this->call($u, $name, $args);

            return $this->result($id, ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]], 'isError' => false]);
        } catch (ValidationException $e) {
            return $this->result($id, ['content' => [['type' => 'text', 'text' => json_encode($e->errors(), JSON_UNESCAPED_UNICODE)]], 'isError' => true]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->result($id, ['content' => [['type' => 'text', 'text' => 'Vídeo não encontrado nesta conta.']], 'isError' => true]);
        } catch (\Throwable $e) {
            report($e);

            return $this->result($id, ['content' => [['type' => 'text', 'text' => 'Não foi possível concluir. Consulte o andamento antes de tentar criar novamente com o mesmo request_id.']], 'isError' => true]);
        }
    }

    private function result($id, $result)
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], 200, ['Cache-Control' => 'no-store']);
    }

    private function error($id, $code, $message)
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => compact('code', 'message')], 200, ['Cache-Control' => 'no-store']);
    }

    private function tools(): array
    {
        $object = fn ($p = [], $required = []) => ['type' => 'object', 'properties' => $p ?: new \stdClass, 'required' => $required, 'additionalProperties' => false];
        $video = $object(['video_id' => ['type' => 'integer', 'minimum' => 1]], ['video_id']);
        $read = ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false];

        $file = $object(['download_url' => ['type' => 'string'], 'file_id' => ['type' => 'string'], 'mime_type' => ['type' => 'string'], 'file_name' => ['type' => 'string']], ['download_url', 'file_id']);
        $plan = $object(['topic' => ['type' => 'string', 'maxLength' => 200], 'duration' => ['type' => 'integer', 'minimum' => 10, 'maximum' => 600], 'custom_script' => ['type' => 'string', 'maxLength' => 8000], 'photo_id' => ['type' => 'string', 'format' => 'uuid']], ['topic', 'duration', 'custom_script']);

        return [
            ['name' => 'upload_photo', 'description' => 'Recebe uma foto JPG/PNG anexada pelo usuário nesta conversa e salva privadamente no VideoGenius. Até 5 MB. Não consome cota de vídeos. Retorna photo_id para create_video. Repetir a mesma foto não duplica o arquivo. Use o anexo real, nunca invente uma URL.', 'inputSchema' => $object(['file' => $file], ['file']), '_meta' => ['openai/fileParams' => ['file']], 'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true]],
            ['name' => 'prepare_video', 'description' => 'Confere foto, roteiro, configuração e cota antes de gerar; retorna o plano e o custo em cota. Não gera vídeo, não reserva cota. Escreva a narração em custom_script, sem instruções de câmera.', 'inputSchema' => $plan, 'annotations' => $read],
            ['name' => 'retry_video', 'description' => 'Repete um vídeo falho da conta com a mesma foto e roteiro, sem consumir nova cota. Use somente se o usuário solicitar ou autorizar nova tentativa, depois de avaliar o erro. request_id deve ser preservado para evitar repetição duplicada.', 'inputSchema' => $object(['video_id' => ['type' => 'integer', 'minimum' => 1], 'request_id' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 100]], ['video_id', 'request_id']), 'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true]],
            ['name' => 'list_photos', 'description' => 'Lista as fotos privadas enviadas pelo usuário em /photos. Use o ID em create_video. O vídeo aplica zoom na foto, sem animar a pessoa.', 'inputSchema' => $object(), 'annotations' => $read],
            ['name' => 'account_status', 'description' => 'Consulta a cota mensal e se os provedores estão configurados, sem revelar chaves.', 'inputSchema' => $object(), 'annotations' => $read],
            ['name' => 'list_videos', 'description' => 'Lista até 20 vídeos da conta, com paginação.', 'inputSchema' => $object(['page' => ['type' => 'integer', 'minimum' => 1]]), 'annotations' => $read],
            ['name' => 'get_video', 'description' => 'Consulta estado e resultado de um vídeo da conta.', 'inputSchema' => $video, 'annotations' => $read],
            ['name' => 'get_video_download', 'description' => 'Cria um link de download do MP4 pronto válido por 30 minutos. Qualquer pessoa com o link pode baixá-lo até expirar.', 'inputSchema' => $video, 'annotations' => $read],
            ['name' => 'create_video', 'description' => 'Enfileira um vídeo narrado em português, usando foto própria (photo_id) ou clipes do Pixabay. Para fotos anexadas use upload_photo; para fotos já salvas use list_photos. A foto recebe zoom suave, sem animação da pessoa. Consome a cota mensal. Roteiro próprio obrigatório dispensa Gemini. Use o mesmo request_id numa repetição da mesma solicitação. Não gera filmagens novas com IA nem publica no YouTube.', 'inputSchema' => $object(['photo_id' => ['type' => 'string', 'format' => 'uuid', 'description' => 'Opcional: ID retornado por list_photos.'], 'topic' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200], 'duration' => ['type' => 'integer', 'minimum' => 10, 'maximum' => 600], 'custom_script' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 8000], 'request_id' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 100]], ['topic', 'duration', 'custom_script', 'request_id']), 'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true]],
        ];
    }

    private function details(Video $v): array
    {
        return ['id' => $v->id, 'title' => $v->title, 'topic' => $v->topic, 'status' => $v->status, 'custom_script' => $v->generated_script, 'duration_seconds' => $v->duration_seconds, 'failure_reason' => $v->failure_reason, 'page_url' => rtrim(config('app.url'), '/').'/videos/'.$v->id, 'created_at' => $v->created_at?->toIso8601String()];
    }

    private function call(User $u, string $name, array $a): array
    {
        if ($name === 'upload_photo') {
            $d = Validator::make($a, ['file' => 'required|array', 'file.download_url' => 'required|string|max:16000', 'file.file_id' => 'required|string|max:255'])->validate();

            return app(\App\Services\PhotoImport::class)->fromAttachment($u, $d['file']);
        }
        if ($name === 'prepare_video') {
            $d = Validator::make($a, ['topic' => 'required|string|max:200', 'duration' => 'required|integer|min:10|max:600', 'custom_script' => 'required|string|max:8000', 'photo_id' => 'nullable|uuid'])->validate();
            if (! empty($d['photo_id'])) {
                $photo = DB::table('video_photos')->where('user_id', $u->id)->where('id', $d['photo_id'])->first();
                if (! $photo || ! Storage::disk('local')->exists($photo->path)) {
                    throw ValidationException::withMessages(['photo_id' => 'Foto não encontrada nesta conta. Use upload_photo.']);
                }
            } elseif (! config('video.pixabay_key')) {
                throw ValidationException::withMessages(['configuration' => 'Configure Pixabay ou envie uma foto.']);
            }
            $can = $u->canGenerateVideo();

            return ['plan' => $d, 'mode' => empty($d['photo_id']) ? 'stock_footage' : 'photo_zoom_vertical', 'can_generate' => $can, 'quota_cost' => 1, 'quota_consumed_now' => 0, 'remaining' => max(0, $u->monthly_video_quota - $u->videos_generated_this_month), 'duration_note' => 'A duração final acompanha a narração. Fotos recebem zoom, sem animar a pessoa.'];
        }
        if ($name === 'retry_video') {
            $d = Validator::make($a, ['video_id' => 'required|integer|min:1', 'request_id' => 'required|string|min:8|max:100|regex:/^[a-zA-Z0-9_-]+$/'])->validate();

            return $this->details(app(VideoCreation::class)->retry($u, $d['video_id'], $d['request_id']));
        }
        if ($name === 'list_photos') {
            return ['upload_url' => rtrim(config('app.url'), '/').'/photos', 'photos' => DB::table('video_photos')->where('user_id', $u->id)->latest()->limit(30)->get(['id', 'created_at'])->all()];
        }
        if ($name === 'account_status') {
            $u->canGenerateVideo();

            return ['monthly_quota' => $u->monthly_video_quota, 'used' => $u->videos_generated_this_month, 'remaining' => max(0, $u->monthly_video_quota - $u->videos_generated_this_month), 'pixabay_configured' => (bool) config('video.pixabay_key'), 'gemini_configured' => (bool) (config('video.gemini_key') && config('video.gemini_model')), 'custom_script_supported' => true, 'photo_video_supported' => true, 'attachment_upload_supported' => true, 'server_version' => '1.2.1', 'photo_upload_url' => rtrim(config('app.url'), '/').'/photos'];
        }
        if ($name === 'list_videos') {
            $d = Validator::make($a, ['page' => 'sometimes|integer|min:1|max:100000'])->validate();
            $page = $d['page'] ?? 1;
            $vs = $u->videos()->latest('id')->skip(($page - 1) * 20)->take(21)->get();

            return ['videos' => $vs->take(20)->map(fn ($v) => $this->details($v))->values()->all(), 'next_page' => $vs->count() > 20 ? $page + 1 : null];
        }
        if ($name === 'create_video') {
            $d = Validator::make($a, ['photo_id' => 'sometimes|nullable|uuid', 'topic' => 'required|string|max:200', 'duration' => 'required|integer|min:10|max:600', 'custom_script' => 'required|string|max:8000', 'request_id' => 'required|string|min:8|max:100|regex:/^[a-zA-Z0-9_-]+$/'])->validate();
            if (empty($d['photo_id']) && ! config('video.pixabay_key')) {
                throw ValidationException::withMessages(['configuration' => 'Configure a chave Pixabay no Render.']);
            }
            $payload = [$d['topic'], (int) $d['duration'], $d['custom_script']];
            if (! empty($d['photo_id'])) {
                $payload[] = $d['photo_id'];
            }
            $hash = hash('sha256', json_encode($payload));

            return DB::transaction(function () use ($u, $d, $hash) {
                $owner = User::whereKey($u->id)->lockForUpdate()->firstOrFail();
                $existing = DB::table('mcp_requests')->where('user_id', $owner->id)->where('request_id', $d['request_id'])->first();
                if ($existing) {
                    if (! hash_equals($existing->payload_hash, $hash)) {
                        throw ValidationException::withMessages(['request_id' => 'Já utilizado com outro conteúdo.']);
                    }

                    return $this->details($owner->videos()->findOrFail($existing->video_id));
                }
                $v = app(VideoCreation::class)->create($owner, $d);
                DB::table('mcp_requests')->insert(['user_id' => $owner->id, 'request_id' => $d['request_id'], 'payload_hash' => $hash, 'video_id' => $v->id, 'created_at' => now(), 'updated_at' => now()]);

                return $this->details($v);
            });
        }
        $d = Validator::make($a, ['video_id' => 'required|integer|min:1'])->validate();
        $v = $u->videos()->findOrFail($d['video_id']);
        if ($name === 'get_video') {
            return $this->details($v);
        }
        if (! in_array($v->status, ['ready', 'uploaded']) || ! $v->final_video_path || ! Storage::disk('local')->exists($v->final_video_path)) {
            throw ValidationException::withMessages(['video' => 'O MP4 ainda não está disponível.']);
        }
        $expires = now()->addMinutes(30);

        return ['video_id' => $v->id, 'download_url' => rtrim(config('app.url'), '/').URL::temporarySignedRoute('mcp.download', $expires, ['video' => $v->id], absolute: false), 'expires_at' => $expires->toIso8601String()];
    }

    public function download(Request $r, Video $video)
    {
        abort_unless(in_array($video->status, ['ready', 'uploaded']) && $video->final_video_path && Storage::disk('local')->exists($video->final_video_path), 404);

        return response()->download(Storage::disk('local')->path($video->final_video_path), 'video-'.$video->id.'.mp4', ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
