<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una barra de Karl Mayer se alimenta de cuatro julios; un rizo o un pie, de uno.
 * La fila de la barra guarda los cuatro para poder leerlos sin cruzar con
 * InvTelasReservadas, que sigue siendo la tabla 1:N y la fuente de verdad.
 *
 * Mismo tipo que no_julio (nvarchar(20)) para que los joins contra
 * AtaMontadoTelas.NoJulio sigan comparando igual.
 */
return new class extends Migration
{
    private const COLUMNAS = ['no_julio2', 'no_julio3', 'no_julio4'];

    public function up(): void
    {
        $faltantes = array_values(array_filter(
            self::COLUMNAS,
            fn ($col) => ! Schema::hasColumn('tej_inventario_telares', $col)
        ));

        if ($faltantes === []) {
            return;
        }

        DB::statement(
            'ALTER TABLE dbo.tej_inventario_telares ADD '
            .implode(', ', array_map(fn ($col) => "{$col} NVARCHAR(20) NULL", $faltantes)).';'
        );
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(
            self::COLUMNAS,
            fn ($col) => Schema::hasColumn('tej_inventario_telares', $col)
        ));

        if ($existentes === []) {
            return;
        }

        DB::statement(
            'ALTER TABLE dbo.tej_inventario_telares DROP COLUMN '.implode(', ', $existentes).';'
        );
    }
};
