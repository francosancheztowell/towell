<?php

use App\Http\Controllers\Api\V1\ExternalRedboothController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/redbooth')
    ->name('api.v1.redbooth.')
    ->middleware(['redbooth.api-key', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/me', [ExternalRedboothController::class, 'me'])->name('me');
        Route::get('/activities', [ExternalRedboothController::class, 'activities'])->name('activities');
        Route::get('/tasks', [ExternalRedboothController::class, 'tasks'])->name('tasks');
        Route::get('/comments', [ExternalRedboothController::class, 'comments'])->name('comments');
        Route::get('/files', [ExternalRedboothController::class, 'files'])->name('files');
        Route::get('/images', [ExternalRedboothController::class, 'images'])->name('images');
        Route::get('/files/{fileId}/download', [ExternalRedboothController::class, 'download'])
            ->whereNumber('fileId')
            ->name('files.download');
    });
