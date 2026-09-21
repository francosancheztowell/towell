<?php

namespace Tests;

use App\Observers\ReqProgramaTejidoObserver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Los caches del observer son static y PHPUnit corre en un solo proceso: lo que
        // cachea un test se lo queda el siguiente. Un test que crea su propia tabla
        // 'CatCodificados' con menos columnas dejaba a otro sin sincronizar 'Pedido',
        // en silencio y solo dentro de la suite completa.
        ReqProgramaTejidoObserver::flushCaches();
    }
}
