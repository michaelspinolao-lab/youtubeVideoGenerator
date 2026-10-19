<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('elevenlabs_api_key')->nullable();
            $t->string('elevenlabs_voice_id', 100)->nullable();
        });
        Schema::create('video_media', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('name');
            $t->string('kind', 10);
            $t->string('mime_type', 80);
            $t->string('sha256', 64);
            $t->unsignedBigInteger('size_bytes');
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->float('duration')->nullable();
            $t->boolean('has_audio')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_media');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['elevenlabs_api_key', 'elevenlabs_voice_id']));
    }
};
