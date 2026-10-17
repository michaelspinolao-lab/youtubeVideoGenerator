<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhotoImport
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function fromAttachment(User $owner, array $file): array
    {
        $url = $file['download_url'];
        $p = parse_url($url);
        $host = strtolower($p['host'] ?? '');
        // Only host-provided attachment stores; never arbitrary URLs or redirects.
        $trusted = $host === 'files.oaiusercontent.com'
            || preg_match('/\A[a-z0-9-]+\.oaiusercontent\.com\z/', $host)
            || preg_match('/\Aoai[a-z0-9-]+\.blob\.core\.windows\.net\z/', $host)
            || preg_match('/\Aoai[a-z0-9-]+\.s3(?:\.[a-z0-9-]+)?\.amazonaws\.com\z/', $host);
        if (($p['scheme'] ?? '') !== 'https' || ! $trusted || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && $p['port'] !== 443)) {
            throw ValidationException::withMessages(['file' => 'Use uma foto anexada nesta conversa; a URL do arquivo deve ser fornecida pelo ChatGPT.']);
        }
        $body = null;
        try {
            $response = Http::timeout(30)->connectTimeout(10)->withoutRedirecting()
                ->withOptions(['stream' => true, 'read_timeout' => 15])->get($url);
            if ($response->status() !== 200 || (int) $response->header('Content-Length') > self::MAX_BYTES) {
                throw new \RuntimeException('download_rejected');
            }
            $body = $response->toPsrResponse()->getBody();
            $bytes = '';
            $deadline = microtime(true) + 30;
            while (! $body->eof()) {
                $bytes .= $body->read(65536);
                if (strlen($bytes) > self::MAX_BYTES || microtime(true) > $deadline) {
                    throw new \RuntimeException('download_limit');
                }
            }
        } catch (\Throwable $e) {
            // Signed URLs and tokens must never appear in errors or logs.
            throw ValidationException::withMessages(['file' => 'Não foi possível receber a foto. Reenvie o anexo JPG/PNG de até 5 MB e tente novamente.']);
        } finally {
            $body?->close();
        }

        return $this->store($owner, $bytes);
    }

    public function store(User $owner, string $bytes): array
    {
        $info = strlen($bytes) <= self::MAX_BYTES ? @getimagesizefromstring($bytes) : false;
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
            || $info[0] < 100 || $info[1] < 100 || $info[0] > 8000 || $info[1] > 8000
            || $info[0] * $info[1] > 32000000) {
            throw ValidationException::withMessages(['file' => 'Envie uma imagem JPG ou PNG válida, até 5 MB, entre 100 e 8000 pixels e no máximo 32 megapixels.']);
        }
        $hash = hash('sha256', $bytes);

        return DB::transaction(function () use ($owner, $bytes, $hash, $info) {
            User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('video_photos')->where('user_id', $owner->id)->where('sha256', $hash)->first();
            if ($existing && Storage::disk('local')->exists($existing->path)) {
                return ['photo_id' => $existing->id, 'already_uploaded' => true, 'quota_used' => 0];
            }
            $id = $existing?->id ?? (string) Str::uuid();
            $path = 'photos/'.$owner->id.'/'.$id.($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
            if (! Storage::disk('local')->put($path, $bytes)) {
                throw ValidationException::withMessages(['file' => 'Não foi possível salvar a foto. Tente novamente.']);
            }
            try {
                DB::table('video_photos')->updateOrInsert(['id' => $id], [
                    'user_id' => $owner->id, 'path' => $path, 'sha256' => $hash,
                    'created_at' => $existing?->created_at ?? now(), 'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e;
            }

            return ['photo_id' => $id, 'already_uploaded' => false, 'quota_used' => 0];
        });
    }
}
