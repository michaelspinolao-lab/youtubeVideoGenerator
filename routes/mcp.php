<?php

use App\Http\Controllers\McpController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST', 'DELETE'], '/mcp', [McpController::class, 'handle'])->middleware('throttle:60,1');
Route::get('/mcp/files/{video}', [McpController::class, 'download'])->middleware(['signed:relative', 'throttle:30,1'])->name('mcp.download');

Route::get('/.well-known/oauth-authorization-server', [\App\Http\Controllers\McpOAuthController::class, 'metadata']);
Route::get('/.well-known/oauth-protected-resource', [\App\Http\Controllers\McpOAuthController::class, 'resourceMetadata']);
Route::get('/.well-known/oauth-protected-resource/mcp', [\App\Http\Controllers\McpOAuthController::class, 'resourceMetadata']);
Route::post('/oauth/register', [\App\Http\Controllers\McpOAuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/oauth/token', [\App\Http\Controllers\McpOAuthController::class, 'token'])->middleware('throttle:30,1');
