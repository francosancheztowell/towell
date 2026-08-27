<?php

/**
 * Ensayo con 20 ordenes creadas en SQL Server REAL.
 *
 * Crea folios ZT01..ZT20 (prefijo que no existe en la base), les corre el ciclo
 * completo con los controladores de verdad, y los borra al final pase lo que
 * pase. Ninguna fila preexistente se toca: todo se filtra por Folio LIKE 'ZT%'.
 *
 * A diferencia de los tests en SQLite, esto si ejerce SQL Server: lockForUpdate,
 * el LIKE de MaquinaId, el orderByRaw de Hilos y las columnas `real`.
 */

use App\Http\Controllers\Urdido\Configuracion\ModuloProduccionUrdidoController;
use App\Http\Controllers\Urdido\ProgramaUrdido\EditarOrdenesProgramadasController;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

const PREFIJO = 'ZT';

$db = DB::connection('sqlsrv');

// ── seguridad: abortar si el prefijo ya existe ───────────────────────
$colision = $db->table('UrdProgramaUrdido')->where('Folio', 'like', PREFIJO.'%')->count()
    + $db->table('UrdProduccionUrdido')->where('Folio', 'like', PREFIJO.'%')->count()
    + $db->table('UrdJuliosOrden')->where('Folio', 'like', PREFIJO.'%')->count();

if ($colision > 0) {
    echo "ABORTADO: ya existen {$colision} filas con prefijo ".PREFIJO."\n";
    exit(1);
}

$limpiar = function () use ($db) {
    $p = PREFIJO.'%';
    $a = $db->table('UrdProduccionUrdido')->where('Folio', 'like', $p)->delete();
    $b = $db->table('UrdJuliosOrden')->where('Folio', 'like', $p)->delete();
    $c = $db->table('UrdProgramaUrdido')->where('Folio', 'like', $p)->delete();

    return [$a, $b, $c];
};

// ── los 20 planes: 1, 2 y 3 grupos, con y sin captura, con y sin AX ──
$catalogo = [];
for ($i = 1; $i <= 20; $i++) {
    $folio = sprintf(PREFIJO.'%02d', $i);
    $grupos = match ($i % 4) {
        0 => [[2, 486], [4, 484], [3, 480]],
        1 => [[6, 484]],
        2 => [[2, 486], [4, 484]],
        default => [[3, 490], [3, 488]],
    };
    $catalogo[] = [
        'folio' => $folio,
        'grupos' => $grupos,
        'capturar' => $i % 3,             // 0, 1 o 2 renglones capturados
        'ax' => $i % 5 === 0,             // 1 de cada 5 con una fila en AX
    ];
}

$controller = new ModuloProduccionUrdidoController;
$editor = app(EditarOrdenesProgramadasController::class);

$rgetJulios = new ReflectionMethod($controller, 'getJuliosForOrder');
$rgetJulios->setAccessible(true);
$rensure = new ReflectionMethod($controller, 'ensureProductionRecordsExist');
$rensure->setAccessible(true);

$entrar = function (UrdProgramaUrdido $orden) use ($controller, $rgetJulios, $rensure) {
    $julios = $rgetJulios->invoke($controller, $orden);
    $total = 0;
    foreach ($julios as $j) {
        $total += max(0, (int) $j->Julios);
    }
    $rensure->invoke($controller, $orden, $julios, $total);
};

$filas = fn ($folio) => $db->table('UrdProduccionUrdido')->where('Folio', $folio)->count();
$plan = fn ($folio) => (int) $db->table('UrdJuliosOrden')->where('Folio', $folio)->whereNotNull('Julios')->sum('Julios');
$capturadas = fn ($folio) => $db->table('UrdProduccionUrdido')->where('Folio', $folio)
    ->where(function ($q) {
        $q->where(function ($w) {
            $w->whereNotNull('NoJulio')->where('NoJulio', '!=', '');
        })->orWhere(function ($w) {
            $w->whereNotNull('KgBruto')->where('KgBruto', '!=', 0);
        });
    })->count();

// Una fila en AX tampoco se puede borrar, aunque este vacia: la regla es no
// tocar lo ya procesado en AX. El invariante son las filas INTOCABLES.
$intocables = fn ($folio) => $db->table('UrdProduccionUrdido')->where('Folio', $folio)
    ->where(function ($q) {
        $q->where('AX', 1)
            ->orWhere(function ($w) {
                $w->whereNotNull('NoJulio')->where('NoJulio', '!=', '');
            })
            ->orWhere(function ($w) {
                $w->whereNotNull('KgBruto')->where('KgBruto', '!=', 0);
            });
    })->count();

$fallos = [];
$lineas = [];

$check = function ($folio, $etiqueta) use (&$fallos, $filas, $plan, $intocables) {
    $esperado = max($plan($folio), $intocables($folio));
    $real = $filas($folio);
    if ($real !== $esperado) {
        $fallos[] = "{$folio} [{$etiqueta}]: {$real} renglones, esperado {$esperado}";
    }

    return [$real, $esperado];
};

try {
    // ── alta de las 20 ordenes ───────────────────────────────────────
    foreach ($catalogo as $c) {
        $db->table('UrdProgramaUrdido')->insert([
            'Folio' => $c['folio'],
            'Status' => 'En Proceso',
            'MaquinaId' => 'Mc Coy 2',
            'NoTelarId' => 'ZT',
            'RizoPie' => 'Pie',
            'Cuenta' => '0000',
            'InventSizeId' => 'ZT',
            'Incorrecto' => 0,
            'Metros' => 6000,
            'CreatedAt' => now(),
        ]);
        foreach ($c['grupos'] as $g) {
            $db->table('UrdJuliosOrden')->insert([
                'Folio' => $c['folio'], 'Julios' => $g[0], 'Hilos' => $g[1],
            ]);
        }
    }
    echo "creadas 20 ordenes ZT01..ZT20\n\n";

    // poblar y capturar
    foreach ($catalogo as $c) {
        $orden = UrdProgramaUrdido::where('Folio', $c['folio'])->first();
        $entrar($orden);

        $ids = $db->table('UrdProduccionUrdido')->where('Folio', $c['folio'])->orderBy('Id')->pluck('Id')->all();
        for ($k = 0; $k < min($c['capturar'], count($ids)); $k++) {
            $db->table('UrdProduccionUrdido')->where('Id', $ids[$k])->update([
                'HoraInicial' => '06:00', 'HoraFinal' => '07:00',
                'NoJulio' => 'Z'.$k, 'KgBruto' => 287.4, 'Tara' => 45.6, 'KgNeto' => 241.8,
            ]);
        }
        if ($c['ax'] && count($ids) > 0) {
            $db->table('UrdProduccionUrdido')->where('Id', $ids[0])->update(['AX' => 1]);
        }
    }

    // ── escenario 1: abrir 25 veces ──────────────────────────────────
    echo "--- 1) abrir 25 veces sin tocar nada ---\n";
    foreach ($catalogo as $c) {
        $orden = UrdProgramaUrdido::where('Folio', $c['folio'])->first();
        $antes = $filas($c['folio']);
        for ($n = 0; $n < 25; $n++) {
            $entrar($orden);
        }
        [$real, $esp] = $check($c['folio'], 'abrir x25');
        printf("  %-5s grupos=%d plan=%-2d antes=%-2d despues=%-2d %s\n",
            $c['folio'], count($c['grupos']), $plan($c['folio']), $antes, $real, $real === $esp ? 'OK' : '<<< FALLA');
    }

    // ── escenario 2: sumar julios ────────────────────────────────────
    echo "\n--- 2) sumar 3 julios al primer grupo ---\n";
    foreach ($catalogo as $c) {
        $orden = UrdProgramaUrdido::where('Folio', $c['folio'])->first();
        $g = UrdJuliosOrden::where('Folio', $c['folio'])->orderBy('Id')->first();
        $editor->actualizarJulios(Request::create('/x', 'POST', [
            'orden_id' => $orden->Id, 'id' => $g->Id, 'no_julio' => (int) $g->Julios + 3, 'hilos' => (int) $g->Hilos,
        ]));
        $entrar($orden);
        $entrar($orden);
        [$real, $esp] = $check($c['folio'], 'sumar 3');
        printf("  %-5s plan=%-2d filas=%-2d esperado=%-2d %s\n", $c['folio'], $plan($c['folio']), $real, $esp, $real === $esp ? 'OK' : '<<< FALLA');
    }

    // ── escenario 3: restar julios ───────────────────────────────────
    echo "\n--- 3) restar 2 julios al primer grupo ---\n";
    foreach ($catalogo as $c) {
        $orden = UrdProgramaUrdido::where('Folio', $c['folio'])->first();
        $g = UrdJuliosOrden::where('Folio', $c['folio'])->orderBy('Id')->first();
        $editor->actualizarJulios(Request::create('/x', 'POST', [
            'orden_id' => $orden->Id, 'id' => $g->Id, 'no_julio' => max(0, (int) $g->Julios - 2), 'hilos' => (int) $g->Hilos,
        ]));
        $entrar($orden);
        $entrar($orden);
        [$real, $esp] = $check($c['folio'], 'restar 2');
        printf("  %-5s plan=%-2d filas=%-2d esperado=%-2d %s\n", $c['folio'], $plan($c['folio']), $real, $esp, $real === $esp ? 'OK' : '<<< FALLA');
    }

    // ── escenario 4: cambiar el Hilos de cada grupo ──────────────────
    echo "\n--- 4) cambiar el Hilos de CADA grupo ---\n";
    foreach ($catalogo as $c) {
        $orden = UrdProgramaUrdido::where('Folio', $c['folio'])->first();
        $base = 900;
        foreach (UrdJuliosOrden::where('Folio', $c['folio'])->orderBy('Id')->get() as $g) {
            $editor->actualizarJulios(Request::create('/x', 'POST', [
                'orden_id' => $orden->Id, 'id' => $g->Id, 'no_julio' => (int) $g->Julios, 'hilos' => $base++,
            ]));
            $entrar($orden);
        }
        $entrar($orden);
        $entrar($orden);
        [$real, $esp] = $check($c['folio'], 'cambiar hilos');
        printf("  %-5s grupos=%d plan=%-2d cap=%-2d filas=%-2d esperado=%-2d %s\n",
            $c['folio'], count($c['grupos']), $plan($c['folio']), $capturadas($c['folio']), $real, $esp, $real === $esp ? 'OK' : '<<< FALLA');
    }

    // ── escenario 5: vaciar el plan ──────────────────────────────────
    echo "\n--- 5) vaciar el plan grupo por grupo ---\n";
    foreach ($catalogo as $c) {
        $orden = UrdProgramaUrdido::where('Folio', $c['folio'])->first();
        foreach (UrdJuliosOrden::where('Folio', $c['folio'])->orderBy('Id')->get() as $g) {
            $editor->actualizarJulios(Request::create('/x', 'POST', [
                'orden_id' => $orden->Id, 'id' => $g->Id, 'no_julio' => '', 'hilos' => '',
            ]));
            $entrar($orden);
        }
        $entrar($orden);
        [$real, $esp] = $check($c['folio'], 'vaciar plan');
        printf("  %-5s plan=%-2d cap=%-2d filas=%-2d esperado=%-2d %s\n",
            $c['folio'], $plan($c['folio']), $capturadas($c['folio']), $real, $esp, $real === $esp ? 'OK' : '<<< FALLA');
    }

    echo "\n════════ RESULTADO ════════\n";
    echo count($fallos) === 0
        ? "SIN FALLOS: 20 ordenes x 5 escenarios = 100 comprobaciones limpias\n"
        : ('FALLOS ('.count($fallos)."):\n  ".implode("\n  ", $fallos)."\n");
} catch (Throwable $e) {
    echo "\nEXCEPCION: ".$e->getMessage()."\n";
} finally {
    [$a, $b, $c] = $limpiar();
    echo "\nlimpieza: {$a} produccion, {$b} julios, {$c} programa\n";
    $resto = $db->table('UrdProgramaUrdido')->where('Folio', 'like', PREFIJO.'%')->count()
        + $db->table('UrdProduccionUrdido')->where('Folio', 'like', PREFIJO.'%')->count()
        + $db->table('UrdJuliosOrden')->where('Folio', 'like', PREFIJO.'%')->count();
    echo $resto === 0 ? "residuo: NINGUNO\n" : "ATENCION: quedaron {$resto} filas ZT\n";
}
