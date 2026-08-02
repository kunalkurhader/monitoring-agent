<?php

use App\Http\Controllers\Api\AgentMetricsController;
use App\Http\Controllers\Api\BrowserEventController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/agent')->middleware(AuthenticateAgent::class)->group(function (): void {
    Route::get('/ping', [AgentMetricsController::class, 'ping']);
    Route::post('/metrics', [AgentMetricsController::class, 'store']);
    Route::post('/disks', [AgentMetricsController::class, 'storeDisks']);
});

Route::options('/v1/browser/events', [BrowserEventController::class, 'options']);
Route::post('/v1/browser/events', [BrowserEventController::class, 'store'])->middleware('throttle:120,1');
