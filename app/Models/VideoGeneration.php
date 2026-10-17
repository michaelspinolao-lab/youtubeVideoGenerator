<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoGeneration extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['parameters' => 'array', 'progress_log' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
}
