<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class MediaImport
{
    public function fromAttachment(User $owner, array $file): array
    {
        $url = $file['download_url'];
        $p = parse_url($url);
        $host = strtolower($p['host'] ?? '');
        $trusted = $host === 'files.oaiusercontent.com'
            || preg_match('/\A[a-z0-9-]+\.oaiusercontent\.com\z/', $host)
            || preg_match('/\Aoai[a-z0-9-]+\.blob\.core\.windows\.net\z/', $host)
            || preg_match('/\Aoai[a-z0-9-]+\.s3(?:\.[a-z0-9-]+)?\.amazonaws\.com\z/', $host);
        if (($p['scheme'] ?? '') !== 'https' || ! $trusted || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && $p['port'] !== 443)) {
            throw ValidationException::withMessages(['file' => 'Use um anexo real fornecido pelo ChatGPT.']);
        }
        $temp = tmpfile();
        $body = null;
        try {
            try {
                $response = \Illuminate\Support\Facades\Http::connectTimeout(10)->timeout(90)->withoutRedirecting()->withOptions(['stream' => true, 'read_timeout' => 15])->get($url);
                if ($response->status() !== 200 || (int) $response->header('Content-Length') > 104857600) {
                    throw new \RuntimeException('download');
                }
                $body = $response->toPsrResponse()->getBody();
                $size = 0;
                $deadline = microtime(true) + 90;
                while (! $body->eof()) {
                    $chunk = $body->read(65536);
                    $size += strlen($chunk);
                    if ($size > 104857600 || microtime(true) > $deadline) {
                        throw new \RuntimeException('limit');
                    }
                    fwrite($temp, $chunk);
                }
                fflush($temp);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['file' => 'Não foi possível receber o anexo. Reenvie ou use /media. Limite: 100 MB.']);
            }

            return $this->store($owner, stream_get_meta_data($temp)['uri'], $file['file_name'] ?? 'Arquivo enviado');
        } finally {
            $body?->close();
            fclose($temp);
        }
    }

    public function store(User $owner, string $source, string $name): array
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('media-tmp');
        $normalized = $disk->path('media-tmp/'.Str::uuid().'.jpg');
        try {
            $process = new Process([config('video.python'), base_path('scripts/inspect_media.py'), $source, $normalized]);
            $process->setTimeout(45);
            $process->run();
            $info = json_decode($process->getOutput(), true);
            if (! $process->isSuccessful() || ! isset($info['kind'])) {
                throw ValidationException::withMessages(['file' => 'Arquivo inválido. Use JPG, PNG, HEIC ou WebP até 20 MB; MP4, MOV ou WebM até 100 MB, 10 minutos e 4K.']);
            }
            $path = $info['kind'] === 'photo' ? $normalized : $source;
            $hash = hash_file('sha256', $path);
            $size = filesize($path);

            return DB::transaction(function () use ($owner, $path, $hash, $size, $info, $name, $disk) {
                User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
                $existing = DB::table('video_media')->where('user_id', $owner->id)->where('sha256', $hash)->first();
                if ($existing && $disk->exists($existing->path)) {
                    return ['media_id' => $existing->id, 'already_uploaded' => true, 'quota_used' => 0];
                }
                if (DB::table('video_media')->where('user_id', $owner->id)->sum('size_bytes') + $size > 2 * 1024 * 1024 * 1024) {
                    throw ValidationException::withMessages(['file' => 'Sua biblioteca atingiu o limite de 2 GB.']);
                }
                $id = $existing?->id ?? (string) Str::uuid();
                $ext = ['image/jpeg' => 'jpg', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'][$info['mime_type']];
                $destination = 'media/'.$owner->id.'/'.$id.'.'.$ext;
                $stream = fopen($path, 'rb');
                try {
                    $saved = $disk->put($destination, $stream);
                } finally {
                    fclose($stream);
                }
                if (! $saved) {
                    throw new \RuntimeException('media_storage_failed');
                }
                try {
                    DB::table('video_media')->updateOrInsert(['id' => $id], array_merge($info, [
                        'user_id' => $owner->id, 'path' => $destination, 'name' => mb_substr(basename($name), 0, 200),
                        'sha256' => $hash, 'size_bytes' => $size, 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now(),
                    ]));
                } catch (\Throwable $e) {
                    $disk->delete($destination);
                    throw $e;
                }

                return ['media_id' => $id, 'kind' => $info['kind'], 'already_uploaded' => false, 'quota_used' => 0];
            });
        } finally {
            if (is_file($normalized)) {
                unlink($normalized);
            }
        }
    }

    public function resolve(User $owner, array $ids): array
    {
        $rows = [];
        foreach ($ids as $id) {
            $row = DB::table('video_media')->where('id', $id)->where('user_id', $owner->id)->first();
            if (! $row || ! Storage::disk('local')->exists($row->path)) {
                throw ValidationException::withMessages(['media_ids' => 'Arquivo não encontrado nesta conta. Envie novamente em /media.']);
            }
            $rows[] = ['id' => $row->id, 'path' => $row->path, 'kind' => $row->kind, 'has_audio' => (bool) $row->has_audio];
        }

        return $rows;
    }
}
