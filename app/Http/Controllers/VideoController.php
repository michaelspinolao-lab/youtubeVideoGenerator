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
        return Inertia::render('Videos/Create', ['youtubeChannels' => Auth::user()->youtubeChannels()->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['photo_id' => 'nullable|uuid', 'topic' => 'required|string|max:200', 'duration' => 'required|integer|min:30|max:600', 'custom_script' => 'nullable|string|max:8000', 'youtube_channel_id' => ['nullable', Rule::exists('youtube_channels', 'id')->where('user_id', $r->user()->id)]]);
        if ((empty($data['photo_id']) && ! config('video.pixabay_key')) || (empty($data['custom_script']) && (! config('video.gemini_key') || ! config('video.gemini_model')))) {
            return back()->with('error', 'Configure Pixabay e Gemini antes de gerar. Com roteiro próprio, Gemini não é necessário.');
        }
        $v = app(\App\Services\VideoCreation::class)->create($r->user(), $data);

        return redirect()->route('videos.show', $v);
    }

    public function show(Video $video)
    {
        $this->own($video);

        return Inertia::render('Videos/Show', ['video' => $video->load('youtubeChannel')]);
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
