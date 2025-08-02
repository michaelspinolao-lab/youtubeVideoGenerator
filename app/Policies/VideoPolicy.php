<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    /**
     * Determine whether the user can view any videos.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the video.
     */
    public function view(User $user, Video $video): bool
    {
        return $user->id === $video->user_id;
    }

    /**
     * Determine whether the user can create videos.
     */
    public function create(User $user): bool
    {
        return $user->canGenerateVideo();
    }

    /**
     * Determine whether the user can update the video.
     */
    public function update(User $user, Video $video): bool
    {
        return $user->id === $video->user_id && $video->status === 'draft';
    }

    /**
     * Determine whether the user can delete the video.
     */
    public function delete(User $user, Video $video): bool
    {
        return $user->id === $video->user_id;
    }

    /**
     * Determine whether the user can restore the video.
     */
    public function restore(User $user, Video $video): bool
    {
        return $user->id === $video->user_id;
    }

    /**
     * Determine whether the user can permanently delete the video.
     */
    public function forceDelete(User $user, Video $video): bool
    {
        return $user->id === $video->user_id;
    }
}
