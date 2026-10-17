<?php

use App\Http\Controllers\McpController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST', 'DELETE'], '/mcp', [McpController::class, 'handle'])->middleware('throttle:60,1');
Route::get('/mcp/files/{video}', [McpController::class, 'download'])->middleware(['signed:relative', 'throttle:30,1'])->name('mcp.download');
