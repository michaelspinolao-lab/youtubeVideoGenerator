<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YoutubeChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'youtube_channel_id',
        'channel_title',
        'channel_description',
        'thumbnail_url',
        'subscriber_count',
        'video_count',
        'daily_upload_quota',
        'uploads_today',
        'quota_reset_date',
        'autopilot_enabled',
        'autopilot_settings',
        'last_video_uploaded_at',
    ];

    protected $casts = [
        'autopilot_settings' => 'array',
        'autopilot_enabled' => 'boolean',
        'quota_reset_date' => 'date',
        'last_video_uploaded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function canUploadToday(): bool
    {
        // Reset quota if it's a new day
        if ($this->quota_reset_date->isToday() === false) {
            $this->update([
                'uploads_today' => 0,
                'quota_reset_date' => now()->toDateString(),
            ]);
        }

        return $this->uploads_today < $this->daily_upload_quota;
    }

    public function incrementUploadsToday(): void
    {
        $this->increment('uploads_today');
    }
}
