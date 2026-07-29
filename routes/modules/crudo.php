<?php

declare(strict_types=1);

use App\Http\Controllers\Crudo\CrudoController;
use Illuminate\Support\Facades\Route;

Route::get('/Crudo', [CrudoController::class, 'index'])->name('crudo.index');
