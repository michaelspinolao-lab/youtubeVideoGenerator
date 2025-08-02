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
        Schema::create('video_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('job_id')->unique(); // For tracking background jobs
            $table->string('topic');
            $table->json('parameters')->nullable(); // Duration, style preferences, etc.
            $table->enum('status', [
                'queued',
                'processing',
                'completed',
                'failed'
            ])->default('queued');
            $table->text('error_message')->nullable();
            $table->json('progress_log')->nullable(); // Step-by-step progress
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('processing_time_seconds')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_generations');
    }
};
