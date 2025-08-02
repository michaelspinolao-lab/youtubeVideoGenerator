<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Load user's videos with their status
        $videos = $user->videos()
            ->with('youtubeChannel')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Load user's YouTube channels
        $youtubeChannels = $user->youtubeChannels()
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate user stats
        $stats = [
            'total_videos' => $user->videos()->count(),
            'uploaded_videos' => $user->videos()->where('status', 'uploaded')->count(),
            'processing_videos' => $user->videos()->whereIn('status', [
                'generating_script', 'fetching_visuals', 'generating_voiceover',
                'assembling_video', 'uploading'
            ])->count(),
            'failed_videos' => $user->videos()->where('status', 'failed')->count(),
            'videos_remaining_this_month' => max(0, $user->monthly_video_quota - $user->videos_generated_this_month),
            'channels_connected' => $youtubeChannels->count(),
            'autopilot_channels' => $youtubeChannels->where('autopilot_enabled', true)->count(),
        ];

        return Inertia::render('Dashboard', [
            'videos' => $videos,
            'youtubeChannels' => $youtubeChannels,
            'stats' => $stats,
            'user' => $user->only([
                'name', 'email', 'avatar_url', 'subscription_plan',
                'monthly_video_quota', 'videos_generated_this_month'
            ]),
        ]);
    }
}
