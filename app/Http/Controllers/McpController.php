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

            return $this->result($id, ['protocolVersion' => $version, 'capabilities' => ['tools' => ['listChanged' => false]], 'serverInfo' => ['name' => 'videogenius', 'version' => '1.0.0'], 'instructions' => 'Crie vídeos apenas quando solicitado. Envie um roteiro próprio para dispensar Gemini. Preserve request_id nas tentativas. Não publique no YouTube: esta integração gera MP4.']);
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

        return [
            ['name' => 'account_status', 'description' => 'Consulta a cota mensal e se os provedores estão configurados, sem revelar chaves.', 'inputSchema' => $object(), 'annotations' => $read],
            ['name' => 'list_videos', 'description' => 'Lista até 20 vídeos da conta, com paginação.', 'inputSchema' => $object(['page' => ['type' => 'integer', 'minimum' => 1]]), 'annotations' => $read],
            ['name' => 'get_video', 'description' => 'Consulta estado e resultado de um vídeo da conta.', 'inputSchema' => $video, 'annotations' => $read],
            ['name' => 'get_video_download', 'description' => 'Cria um link de download do MP4 pronto válido por 30 minutos. Qualquer pessoa com o link pode baixá-lo até expirar.', 'inputSchema' => $video, 'annotations' => $read],
            ['name' => 'create_video', 'description' => 'Enfileira um vídeo narrado em português, usando clipes do Pixabay. Consome a cota mensal. Roteiro próprio obrigatório dispensa Gemini. Use o mesmo request_id numa repetição da mesma solicitação. Não gera filmagens novas com IA nem publica no YouTube.', 'inputSchema' => $object(['topic' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200], 'duration' => ['type' => 'integer', 'minimum' => 30, 'maximum' => 600], 'custom_script' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 8000], 'request_id' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 100]], ['topic', 'duration', 'custom_script', 'request_id']), 'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true]],
        ];
    }

    private function details(Video $v): array
    {
        return ['id' => $v->id, 'title' => $v->title, 'topic' => $v->topic, 'status' => $v->status, 'duration_seconds' => $v->duration_seconds, 'failure_reason' => $v->failure_reason, 'page_url' => rtrim(config('app.url'), '/').'/videos/'.$v->id, 'created_at' => $v->created_at?->toIso8601String()];
    }

    private function call(User $u, string $name, array $a): array
    {
        if ($name === 'account_status') {
            $u->canGenerateVideo();

            return ['monthly_quota' => $u->monthly_video_quota, 'used' => $u->videos_generated_this_month, 'remaining' => max(0, $u->monthly_video_quota - $u->videos_generated_this_month), 'pixabay_configured' => (bool) config('video.pixabay_key'), 'gemini_configured' => (bool) (config('video.gemini_key') && config('video.gemini_model')), 'custom_script_supported' => true];
        }
        if ($name === 'list_videos') {
            $d = Validator::make($a, ['page' => 'sometimes|integer|min:1|max:100000'])->validate();
            $page = $d['page'] ?? 1;
            $vs = $u->videos()->latest('id')->skip(($page - 1) * 20)->take(21)->get();

            return ['videos' => $vs->take(20)->map(fn ($v) => $this->details($v))->values()->all(), 'next_page' => $vs->count() > 20 ? $page + 1 : null];
        }
        if ($name === 'create_video') {
            $d = Validator::make($a, ['topic' => 'required|string|max:200', 'duration' => 'required|integer|min:30|max:600', 'custom_script' => 'required|string|max:8000', 'request_id' => 'required|string|min:8|max:100|regex:/^[a-zA-Z0-9_-]+$/'])->validate();
            if (! config('video.pixabay_key')) {
                throw ValidationException::withMessages(['configuration' => 'Configure a chave Pixabay no Render.']);
            }
            $hash = hash('sha256', json_encode([$d['topic'], (int) $d['duration'], $d['custom_script']]));

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
