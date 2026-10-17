<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoPhotoController extends Controller
{
    public function index(Request $r)
    {
        return view('video-photos', ['photos' => DB::table('video_photos')->where('user_id', $r->user()->id)->latest()->limit(30)->get()]);
    }

    public function store(Request $r)
    {
        $r->validate(['photo' => 'required|file|mimes:jpg,jpeg,png|max:5120|dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000']);
        $file = $r->file('photo');
        $id = (string) Str::uuid();
        $path = $file->storeAs('photos/'.$r->user()->id, $id.'.'.$file->extension(), 'local');
        try {
            DB::table('video_photos')->insert(['id' => $id, 'user_id' => $r->user()->id, 'path' => $path, 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('photo_id', $id);
    }

    public function show(Request $r, string $photo)
    {
        $p = DB::table('video_photos')->where('user_id', $r->user()->id)->where('id', $photo)->first();
        abort_unless($p && Storage::disk('local')->exists($p->path), 404);

        return response()->file(Storage::disk('local')->path($p->path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
