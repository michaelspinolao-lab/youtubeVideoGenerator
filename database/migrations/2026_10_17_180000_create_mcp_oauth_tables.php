<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_oauth_clients', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->text('redirect_uris');
            $t->timestamp('created_at');
        });
        Schema::create('mcp_oauth_codes', function (Blueprint $t) {
            $t->string('code_hash', 64)->primary();
            $t->string('client_id', 64);
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('redirect_uri');
            $t->string('challenge', 43);
            $t->text('resource');
            $t->timestamp('expires_at');
        });
        Schema::create('mcp_oauth_grants', function (Blueprint $t) {
            $t->id();
            $t->string('client_id', 64);
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('resource');
            $t->string('scope');
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
        });
        Schema::create('mcp_oauth_tokens', function (Blueprint $t) {
            $t->string('token_hash', 64)->primary();
            $t->foreignId('grant_id')->constrained('mcp_oauth_grants')->cascadeOnDelete();
            $t->string('type', 10);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_tokens');
        Schema::dropIfExists('mcp_oauth_grants');
        Schema::dropIfExists('mcp_oauth_codes');
        Schema::dropIfExists('mcp_oauth_clients');
    }
};
