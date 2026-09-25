<?php

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 85 filas con la forma de la medición real: SMIT 50 / JACQUARD 28 / KARL MAYER 7 en
 * 38 telares, ~28 % de celdas vacías. Valores por tipo del cast del modelo.
 */
function sembrarSintetico(array $columns, string $tabla, bool $conOrdCol = true): void
{
    $casts = (new ReqProgramaTejido)->getCasts();
    $campos = array_unique(array_merge(['Id', 'Posicion', 'OrdCompartida', 'NoExisteBase'], array_column($columns, 'field')));

    Schema::connection('sqlsrv')->create($tabla, function (Blueprint $t) use ($campos) {
        foreach ($campos as $c) {
            $c === 'Id' ? $t->integer('Id')->primary() : $t->text($c)->nullable();
        }
    });
    if ($conOrdCol) {
        Schema::connection('sqlsrv')->create('OrdColProgramaTejido', function (Blueprint $t) {
            $t->increments('Id');
            $t->integer('UsuarioId');
            $t->string('Columna');
            $t->boolean('Estado');
        });
    }

    mt_srand(2026);
    $salones = array_merge(array_fill(0, 50, 'SMIT'), array_fill(0, 28, 'JACQUARD'), array_fill(0, 7, 'KARL MAYER'));
    $posiciones = [];
    foreach ($salones as $i => $salon) {
        $telar = (string) (match ($salon) {
            'SMIT' => 201, 'JACQUARD' => 301, default => 401
        } + ($i % 38 % 13));
        $posiciones[$telar] = ($posiciones[$telar] ?? 0) + 1;
        $fila = ['Id' => $i + 1];
        foreach ($campos as $c) {
            if ($c === 'Id') {
                continue;
            }
            $tipo = $casts[$c] ?? null;
            $fila[$c] = mt_rand(1, 100) <= 28 ? null : match (true) {
                str_contains((string) $tipo, 'date') => date('Y-m-d H:i:s', 1788220800 + mt_rand(0, 60) * 86400 + mt_rand(0, 86399)),
                in_array($tipo, ['float', 'double', 'decimal', 'real'], true) || str_starts_with((string) $tipo, 'decimal') => round(mt_rand(0, 500000) / 100, 2),
                in_array($tipo, ['int', 'integer'], true) => mt_rand(0, 5000),
                in_array($tipo, ['bool', 'boolean'], true) => mt_rand(0, 1),
                default => 'VAL '.mt_rand(100, 99999),
            };
        }
        $fila['SalonTejidoId'] = $salon;
        $fila['NoTelarId'] = $telar;
        $fila['Posicion'] = $posiciones[$telar];
        $fila['EnProceso'] = $posiciones[$telar] === 1 ? 1 : 0;
        $fila['NombreProducto'] = $i % 17 === 0 ? 'REPASO '.$i : 'TOALLA '.mt_rand(1000, 9999);
        $fila['OrdCompartida'] = $i % 9 === 0 ? (string) (100 + intdiv($i, 18)) : null;
        $fila['NoExisteBase'] = null;
        DB::connection('sqlsrv')->table($tabla)->insert($fila);
    }
}
