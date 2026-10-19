<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VideoController extends Controller
{
    private function own(Video $video): void
    {
        abort_unless($video->user_id === Auth::id(), 404);
    }

    public function index(Request $r)
    {
        return Inertia::render('Videos/Index', ['videos' => $r->user()->videos()->latest()->paginate(15)]);
    }

    public function create()
    {
        return Inertia::render('Videos/Create', [
            'canGenerateScript' => (bool) (config('video.gemini_key') && config('video.gemini_model')),
            'photos' => \Illuminate\Support\Facades\DB::table('video_photos')->where('user_id', Auth::id())->latest()->limit(30)->get(['id']),
        ]);
    }

    public function store(Request $r)
    {
        $scriptRequired = $r->boolean('narration', true) && ($r->input('voice_provider') === 'elevenlabs' || ! (config('video.gemini_key') && config('video.gemini_model')));
        $data = $r->validate([
            'media_ids' => 'nullable|array|max:12|prohibits:photo,photo_id',
            'media_ids.*' => 'required|uuid|distinct',
            'narration' => 'sometimes|boolean', 'original_audio' => 'sometimes|boolean',
            'aspect_ratio' => 'sometimes|in:vertical,horizontal',
            'voice_provider' => 'sometimes|in:standard,elevenlabs',
            'photo_id' => 'nullable|uuid|prohibits:photo',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120|dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000',
            'topic' => 'required|string|max:200', 'duration' => 'required|integer|min:10|max:600',
            'custom_script' => [Rule::requiredIf($scriptRequired), 'nullable', 'string', 'max:8000'],
            'youtube_channel_id' => ['nullable', Rule::exists('youtube_channels', 'id')->where('user_id', $r->user()->id)],
        ], ['custom_script.required' => 'Escreva ou cole o texto da narração. A criação automática de roteiro ainda não está configurada.']);
        if (empty($data['media_ids']) && empty($data['photo_id']) && ! $r->hasFile('photo') && ! config('video.pixabay_key')) {
            return back()->withErrors(['photo' => 'Envie uma foto. A busca de clipes ainda não está configurada.']);
        }
        if ($r->hasFile('photo')) {
            $photo = app(\App\Services\PhotoImport::class)->store($r->user(), file_get_contents($r->file('photo')->getRealPath()));
            $data['photo_id'] = $photo['photo_id'];
        }
        $v = app(\App\Services\VideoCreation::class)->create($r->user(), $data);

        return redirect()->route('videos.show', $v);
    }

    public function show(Video $video)
    {
        $this->own($video);

        return Inertia::render('Videos/Show', ['video' => $video->load('youtubeChannel'), 'retryRequestId' => (string) \Illuminate\Support\Str::uuid()]);
    }

    public function retry(Request $r, Video $video)
    {
        $this->own($video);
        $data = $r->validate(['request_id' => 'required|string|min:8|max:100|regex:/^[a-zA-Z0-9_-]+$/']);
        app(\App\Services\VideoCreation::class)->retry($r->user(), $video->id, $data['request_id']);

        return redirect()->route('videos.show', $video)->with('success', 'Nova tentativa iniciada, sem descontar outra cota.');
    }

    public function edit(Video $video)
    {
        $this->own($video);

        return redirect()->route('videos.show', $video);
    }

    public function update(Request $r, Video $video)
    {
        $this->own($video);
        abort_if($video->isProcessing(), 409);
        $video->update($r->validate(['title' => 'required|string|max:100', 'description' => 'nullable|string|max:2000', 'youtube_channel_id' => ['nullable', Rule::exists('youtube_channels', 'id')->where('user_id', $r->user()->id)]]));

        return back();
    }

    public function download(Request $r, Video $video)
    {
        $this->own($video);
        abort_unless(in_array($video->status, ['ready', 'uploaded']) && $video->final_video_path && Storage::disk('local')->exists($video->final_video_path), 404);
        $path = Storage::disk('local')->path($video->final_video_path);

        return $r->boolean('preview') ? response()->file($path, ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store']) : response()->download($path, 'video-'.$video->id.'.mp4');
    }

    public function destroy(Video $video)
    {
        $this->own($video);
        abort_if($video->isProcessing(), 409);
        Storage::disk('local')->deleteDirectory('videos/'.$video->id);
        $video->delete();

        return redirect()->route('videos.index');
    }

    public function upload(Request $r, Video $video)
    {
        $this->own($video);

        return back()->with('error', 'Envio automático ainda não está habilitado. Baixe o MP4 e envie pelo YouTube Studio.');
    }
}
