<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('youtube_channel_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('topic'); // User input topic
            $table->text('generated_script')->nullable();
            $table->json('scenes')->nullable(); // Array of scenes with keywords
            $table->json('pexels_videos')->nullable(); // Pexels video URLs/data
            $table->string('voiceover_file_path')->nullable();
            $table->string('background_music_path')->nullable();
            $table->string('final_video_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->enum('status', [
                'draft',
                'generating_script',
                'fetching_visuals',
                'generating_voiceover',
                'assembling_video',
                'ready',
                'uploading',
                'uploaded',
                'failed'
            ])->default('draft');
            $table->text('failure_reason')->nullable();
            $table->string('youtube_video_id')->nullable();
            $table->string('youtube_url')->nullable();
            $table->json('youtube_analytics')->nullable(); // Views, likes, etc.
            $table->boolean('is_autopilot')->default(false);
            $table->integer('duration_seconds')->nullable();
            $table->json('processing_log')->nullable(); // For debugging
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['youtube_channel_id', 'status']);
            $table->index('is_autopilot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
