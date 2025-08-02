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
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->index();
            $table->text('google_access_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->timestamp('google_token_expires_at')->nullable();
            $table->json('google_scopes')->nullable();
            $table->string('avatar_url')->nullable();
            $table->enum('subscription_plan', ['free', 'basic', 'pro', 'enterprise'])->default('free');
            $table->integer('monthly_video_quota')->default(5);
            $table->integer('videos_generated_this_month')->default(0);
            $table->timestamp('quota_reset_at')->nullable();
            $table->boolean('is_admin')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_id',
                'google_access_token',
                'google_refresh_token',
                'google_token_expires_at',
                'google_scopes',
                'avatar_url',
                'subscription_plan',
                'monthly_video_quota',
                'videos_generated_this_month',
                'quota_reset_at',
                'is_admin'
            ]);
        });
    }
};
