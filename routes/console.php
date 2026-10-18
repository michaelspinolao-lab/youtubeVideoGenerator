<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('video:recover-interrupted', function () {
    $count = app(\App\Services\InterruptedVideoRecovery::class)->recover();
    $this->info('Interrupted video jobs recovered: '.$count.'; quota unchanged.');
})->purpose('Personal container boot only: recover interrupted jobs before starting its sole worker');

Artisan::command('video:retry {video_id} {request_id}', function () {
    $id = (string) $this->argument('video_id');
    $key = (string) $this->argument('request_id');
    if (! ctype_digit($id) || ! preg_match('/^[a-zA-Z0-9_-]{8,100}$/', $key)) {
        $this->error('Invalid recovery arguments.');
        return 1;
    }
    $video = \App\Models\Video::with('user')->find($id);
    if (! $video || ! config('video.allowed_email') || strcasecmp($video->user->email, config('video.allowed_email')) !== 0) {
        $this->error('Video not found for the configured owner.');
        return 1;
    }
    try {
        app(\App\Services\VideoCreation::class)->retry($video->user, $video->id, $key);
        $this->info('Video recovery requested; quota unchanged. Video ID: '.$id);
        return 0;
    } catch (\Illuminate\Validation\ValidationException $e) {
        $this->error('Video is not eligible for this recovery request.');
        return 1;
    }
})->purpose('Retry one failed owner video once per request ID without spending another quota');
