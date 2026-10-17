<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_photos', function (Blueprint $t) {
            $t->string('sha256', 64)->nullable();
            $t->unique(['user_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::table('video_photos', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'sha256']);
            $t->dropColumn('sha256');
        });
    }
};
