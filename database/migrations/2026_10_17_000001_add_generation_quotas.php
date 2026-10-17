<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('subscription_plan')->default('personal');
            $t->unsignedInteger('monthly_video_quota')->default(10);
            $t->unsignedInteger('videos_generated_this_month')->default(0);
            $t->timestamp('quota_reset_at')->nullable();
            $t->boolean('is_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['subscription_plan', 'monthly_video_quota', 'videos_generated_this_month', 'quota_reset_at', 'is_admin']));
    }
};
