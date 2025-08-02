<?php

namespace App\Policies;

use App\Models\User;
use App\Models\YoutubeChannel;

class YoutubeChannelPolicy
{
    /**
     * Determine whether the user can view any YouTube channels.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the YouTube channel.
     */
    public function view(User $user, YoutubeChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }

    /**
     * Determine whether the user can create YouTube channels.
     */
    public function create(User $user): bool
    {
        return $user->hasValidGoogleToken();
    }

    /**
     * Determine whether the user can update the YouTube channel.
     */
    public function update(User $user, YoutubeChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }

    /**
     * Determine whether the user can delete the YouTube channel.
     */
    public function delete(User $user, YoutubeChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }
}
