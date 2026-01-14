<?php

use App\Http\Controllers\Telegram\TelegramController;
use Illuminate\Support\Facades\Route;

Route::prefix('telegram')->name('telegram.')->group(function () {
    Route::post('/send', [TelegramController::class, 'sendMessage'])->name('send');
    Route::get('/bot-info', [TelegramController::class, 'getBotInfo'])->name('bot-info');
    Route::get('/get-chat-id', [TelegramController::class, 'getChatId'])->name('get-chat-id');
});
