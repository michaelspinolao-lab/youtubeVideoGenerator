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
        Schema::create('youtube_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('youtube_channel_id')->unique();
            $table->string('channel_title');
            $table->text('channel_description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('subscriber_count')->default(0);
            $table->integer('video_count')->default(0);
            $table->integer('daily_upload_quota')->default(6);
            $table->integer('uploads_today')->default(0);
            $table->date('quota_reset_date')->default(now()->toDateString());
            $table->boolean('autopilot_enabled')->default(false);
            $table->json('autopilot_settings')->nullable(); // topic, frequency, etc.
            $table->timestamp('last_video_uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'autopilot_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_channels');
    }
};
