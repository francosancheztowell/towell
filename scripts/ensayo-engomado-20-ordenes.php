<?php

/**
 * Ensayo con 20 ordenes de Engomado creadas en SQL Server REAL.
 *
 * Gemelo de ensayo-urdido-20-ordenes.php. Crea folios ZE01..ZE20, les corre el
 * ciclo con los controladores de verdad y los borra al final pase lo que pase.
 * Todo se filtra por Folio LIKE 'ZE%': ninguna fila preexistente se toca.
 *
 * Uso: php artisan tinker --execute="require 'scripts/ensayo-engomado-20-ordenes.php';"
 */

use App\Http\Controllers\Engomado\Produccion\ModuloProduccionEngomadoController;
use App\Models\Engomado\EngProgramaEngomado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

const PREFIJO_ENG = 'ZE';

$db = DB::connection('sqlsrv');
$p = PREFIJO_ENG.'%';

$colision = $db->table('EngProgramaEngomado')->where('Folio', 'like', $p)->count()
    + $db->table('EngProduccionEngomado')->where('Folio', 'like', $p)->count()
    + $db->table('EngProduccionFormulacion')->where('Folio', 'like', $p)->count();

if ($colision > 0) {
    echo 'ABORTADO: ya existen '.$colision.' filas con prefijo '.PREFIJO_ENG."\n";
    exit(1);
}

$limpiar = function () use ($db, $p) {
    $a = $db->table('EngProduccionEngomado')->where('Folio', 'like', $p)->delete();
    $b = $db->table('EngProgramaEngomado')->where('Folio', 'like', $p)->delete();
    $db->table('EngProduccionFormulacion')->where('Folio', 'like', $p)->delete();

    return [$a, $b];
};

// Un controlador que puentea SOLO el permiso: el resto es codigo real.
$controller = new class extends ModuloProduccionEngomadoController
{
    protected function ensureUserCanEdit(): void {}
};

$filas = fn ($folio) => $db->table('EngProduccionEngomado')->where('Folio', $folio)->count();
$noTelas = fn ($folio) => (int) $db->table('EngProgramaEngomado')->where('Folio', $folio)->value('NoTelas');

// Intocables: capturadas o en AX. Son el unico motivo legitimo para exceder el plan.
$intocables = fn ($folio) => $db->table('EngProduccionEngomado')->where('Folio', $folio)
    ->where(function ($q) {
        $q->where('AX', 1)
            ->orWhere(function ($w) {
                $w->whereNotNull('NoJulio')->where('NoJulio', '!=', '');
            })
            ->orWhere(function ($w) {
                $w->whereNotNull('KgBruto')->where('KgBruto', '!=', 0);
            });
    })->count();

// "Entrar": la sincronizacion que index() hace al abrir la pantalla.
$rsync = new ReflectionMethod($controller, 'sincronizarRenglonesConNoTelas');
$rsync->setAccessible(true);
$entrar = function ($folio) use ($rsync, $controller) {
    $orden = EngProgramaEngomado::where('Folio', $folio)->first();
    $rsync->invoke($controller, $orden, (int) $orden->NoTelas, null);
};

$fallos = [];
$check = function ($folio, $etiqueta) use (&$fallos, $filas, $noTelas, $intocables) {
    $esperado = max($noTelas($folio), $intocables($folio));
    $real = $filas($folio);
    if ($real !== $esperado) {
        $fallos[] = "{$folio} [{$etiqueta}]: {$real} renglones, esperado {$esperado}";
    }

    return [$real, $esperado];
};

try {
    for ($i = 1; $i <= 20; $i++) {
        $folio = sprintf(PREFIJO_ENG.'%02d', $i);
        $db->table('EngProgramaEngomado')->insert([
            'Folio' => $folio,
            'Status' => 'En Proceso',
            'NoTelas' => ($i % 4) + 1,          // 1..4 telas
            'SalonTejidoId' => 'ZE',
            'MetrajeTelas' => 6000,
        ]);
        // finalizar() exige al menos una formulacion por folio
        $db->table('EngProduccionFormulacion')->insert([
            'Folio' => $folio, 'Status' => 'Creado', 'Solidos' => 7.1,
            'OkTiempo' => 0, 'OkViscosidad' => 0, 'OkSolidos' => 0,
        ]);
    }
    echo "creadas 20 ordenes ZE01..ZE20\n\n";

    echo "--- 1) abrir 25 veces sin tocar nada ---\n";
    for ($i = 1; $i <= 20; $i++) {
        $folio = sprintf(PREFIJO_ENG.'%02d', $i);
        $entrar($folio);

        // capturar algunas y marcar una en AX
        $ids = $db->table('EngProduccionEngomado')->where('Folio', $folio)->orderBy('Id')->pluck('Id')->all();
        for ($k = 0; $k < min($i % 3, count($ids)); $k++) {
            $db->table('EngProduccionEngomado')->where('Id', $ids[$k])->update([
                'HoraInicial' => '06:00', 'HoraFinal' => '07:00',
                'NoJulio' => 'E'.$k, 'KgBruto' => 850.5, 'KgNeto' => 800.5,
            ]);
        }
        if ($i % 5 === 0 && count($ids) > 0) {
            $db->table('EngProduccionEngomado')->where('Id', $ids[0])->update(['AX' => 1]);
        }

        $antes = $filas($folio);
        for ($n = 0; $n < 25; $n++) {
            $entrar($folio);
        }
        [$real, $esp] = $check($folio, 'abrir x25');
        printf("  %-5s NoTelas=%-2d antes=%-2d despues=%-2d %s\n",
            $folio, $noTelas($folio), $antes, $real, $real === $esp ? 'OK' : '<<< FALLA');
    }

    echo "\n--- 2) subir y bajar NoTelas ---\n";
    for ($i = 1; $i <= 20; $i++) {
        $folio = sprintf(PREFIJO_ENG.'%02d', $i);
        foreach ([6, 2, 8, 1, 4] as $n) {
            $db->table('EngProgramaEngomado')->where('Folio', $folio)->update(['NoTelas' => $n]);
            $entrar($folio);
        }
        $entrar($folio);
        [$real, $esp] = $check($folio, 'NoTelas 6-2-8-1-4');
        printf("  %-5s NoTelas=%-2d intocables=%-2d filas=%-2d esperado=%-2d %s\n",
            $folio, $noTelas($folio), $intocables($folio), $real, $esp, $real === $esp ? 'OK' : '<<< FALLA');
    }

    echo "\n--- 3) finalizar: no debe quedar esqueleto marcado ---\n";
    for ($i = 1; $i <= 20; $i++) {
        $folio = sprintf(PREFIJO_ENG.'%02d', $i);
        $orden = EngProgramaEngomado::where('Folio', $folio)->first();

        $resp = $controller->finalizar(Request::create('/f', 'POST', ['orden_id' => $orden->Id]))->getData(true);
        if (empty($resp['success'])) {
            $fallos[] = "{$folio} [finalizar]: no cerro -> ".($resp['error'] ?? '?');
        }

        $basura = $db->table('EngProduccionEngomado')->where('Folio', $folio)
            ->whereNull('HoraInicial')
            ->where(function ($q) {
                $q->whereNull('NoJulio')->orWhere('NoJulio', '');
            })
            ->where(function ($q) {
                $q->whereNull('KgBruto')->orWhere('KgBruto', 0);
            })
            ->where('Finalizar', 1)->count();

        if ($basura > 0) {
            $fallos[] = "{$folio} [finalizar]: {$basura} esqueletos marcados como terminados";
        }
        printf("  %-5s filas=%-2d esqueletos marcados=%-2d %s\n",
            $folio, $filas($folio), $basura, $basura === 0 ? 'OK' : '<<< FALLA');
    }

    echo "\n════════ RESULTADO ════════\n";
    echo count($fallos) === 0
        ? "SIN FALLOS: 20 ordenes x 3 escenarios = 60 comprobaciones limpias\n"
        : ('FALLOS ('.count($fallos)."):\n  ".implode("\n  ", $fallos)."\n");
} catch (Throwable $e) {
    echo "\nEXCEPCION: ".$e->getMessage()."\n  en ".$e->getFile().':'.$e->getLine()."\n";
} finally {
    [$a, $b] = $limpiar();
    echo "\nlimpieza: {$a} produccion, {$b} programa\n";
    $resto = $db->table('EngProgramaEngomado')->where('Folio', 'like', $p)->count()
        + $db->table('EngProduccionEngomado')->where('Folio', 'like', $p)->count();
    echo $resto === 0 ? "residuo: NINGUNO\n" : "ATENCION: quedaron {$resto} filas ZE\n";
}
