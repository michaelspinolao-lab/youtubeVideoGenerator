<?php

namespace App\Http\Controllers;

use App\Services\MediaImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VideoMediaController extends Controller
{
    public function index(Request $r)
    {
        return view('media', ['media' => DB::table('video_media')->where('user_id', $r->user()->id)->latest()->limit(100)->get(['id', 'kind', 'name', 'size_bytes']),
            'elevenlabsReady' => (bool) ($r->user()->elevenlabs_api_key && $r->user()->elevenlabs_voice_id)]);
    }

    public function store(Request $r)
    {
        $r->validate(['file' => 'required|file|max:102400']);

        return response()->json(app(MediaImport::class)->store($r->user(), $r->file('file')->getRealPath(), $r->file('file')->getClientOriginalName()));
    }

    public function show(Request $r, string $media)
    {
        $row = DB::table('video_media')->where('id', $media)->where('user_id', $r->user()->id)->first();
        abort_unless($row && Storage::disk('local')->exists($row->path), 404);

        return response()->file(Storage::disk('local')->path($row->path), ['Content-Type' => $row->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
