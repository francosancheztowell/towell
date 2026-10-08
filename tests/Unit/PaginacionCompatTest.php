<?php

namespace Tests\Unit;

use App\Support\PaginacionCompat;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaginacionCompatTest extends TestCase
{
    // La auditoria de Programa Tejido le pasa un DB::table(), que no tiene toBase().
    public function test_pagina_un_query_builder_sin_modelo(): void
    {
        DB::statement('CREATE TABLE pag_compat (id INTEGER)');
        DB::table('pag_compat')->insert(array_map(fn ($i) => ['id' => $i], range(1, 7)));

        $p = PaginacionCompat::paginar(DB::table('pag_compat')->orderBy('id'), 3, 2);

        $this->assertSame(7, $p->total());
        $this->assertSame([4, 5, 6], $p->getCollection()->pluck('id')->map(fn ($v) => (int) $v)->all());
    }
}
