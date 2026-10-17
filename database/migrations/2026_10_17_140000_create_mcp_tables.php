<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        Schema::create('mcp_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('request_id', 100);
            $t->string('payload_hash', 64);
            $t->foreignId('video_id')->nullable()->constrained()->nullOnDelete();
            $t->unique(['user_id', 'request_id']);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_requests');
        Schema::dropIfExists('mcp_tokens');
    }
};
