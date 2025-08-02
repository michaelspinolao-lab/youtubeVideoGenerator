<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'youtube_channel_id',
        'title',
        'description',
        'topic',
        'generated_script',
        'scenes',
        'pexels_videos',
        'voiceover_file_path',
        'background_music_path',
        'final_video_path',
        'thumbnail_path',
        'status',
        'failure_reason',
        'youtube_video_id',
        'youtube_url',
        'youtube_analytics',
        'is_autopilot',
        'duration_seconds',
        'processing_log',
    ];

    protected $casts = [
        'scenes' => 'array',
        'pexels_videos' => 'array',
        'youtube_analytics' => 'array',
        'processing_log' => 'array',
        'is_autopilot' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function youtubeChannel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class);
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, [
            'generating_script',
            'fetching_visuals',
            'generating_voiceover',
            'assembling_video',
            'uploading'
        ]);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isUploaded(): bool
    {
        return $this->status === 'uploaded';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}
