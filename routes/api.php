<?php

use App\Http\Controllers\Api\V1\ArtifactController;
use App\Http\Controllers\Api\V1\HeartbeatController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SoulController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\ThreadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['agent.auth', 'throttle:agent-api'])->group(function (): void {
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::post('/tasks/{task}/claim', [TaskController::class, 'claim']);

    Route::post('/messages', [MessageController::class, 'store']);
    Route::get('/messages', [MessageController::class, 'index']);

    Route::get('/threads', [ThreadController::class, 'index']);
    Route::patch('/threads/{thread}', [ThreadController::class, 'update']);

    Route::post('/tasks/{task}/artifacts', [ArtifactController::class, 'store']);
    Route::get('/tasks/{task}/artifacts', [ArtifactController::class, 'index']);
    Route::post('/artifacts/{artifact}/confirm', [ArtifactController::class, 'confirm']);
    Route::post('/artifacts/{artifact}/upload', [ArtifactController::class, 'upload']);

    Route::post('/heartbeat', [HeartbeatController::class, 'store'])
        ->middleware('throttle:heartbeat');

    Route::get('/soul', [SoulController::class, 'show']);

    Route::get('/projects', [ProjectController::class, 'index']);
});
