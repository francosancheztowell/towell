<?php

namespace Tests\Feature;

use App\Http\Middleware\MarcarDescarga;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MarcarDescargaTest extends TestCase
{
    public function test_marca_las_descargas_y_no_las_paginas(): void
    {
        Route::middleware('web')->get('/_prueba/descarga', fn () => response()->streamDownload(fn () => print ('x'), 'r.xlsx'));
        Route::middleware('web')->get('/_prueba/pagina', fn () => response('<p>hola</p>'));

        $descarga = $this->get('/_prueba/descarga');
        $descarga->assertOk();
        $cookie = collect($descarga->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === MarcarDescarga::COOKIE);
        $this->assertNotNull($cookie);
        $this->assertFalse($cookie->isHttpOnly(), 'el loader la lee desde JS');

        $this->get('/_prueba/pagina')->assertCookieMissing(MarcarDescarga::COOKIE);
    }
}
