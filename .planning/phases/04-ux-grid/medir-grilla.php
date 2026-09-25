<?php

/**
 * Medición de la grilla de Programa Tejido (fase 04-perf). Mismo método que
 * "Cómo repetir la medición" de 04-PERF-MEDIDO.md, empaquetado para correrlo igual
 * aquí (sqlite sintético) y en Laragon (datos reales).
 *
 *   php .planning/phases/04-ux-grid/medir-grilla.php --sintetico
 *   php .planning/phases/04-ux-grid/medir-grilla.php --usuario=74            (Laragon, Programa)
 *   php .planning/phases/04-ux-grid/medir-grilla.php --usuario=74 --muestras (Laragon, Muestras)
 *
 * Solo lee: en modo real hace el mismo SELECT que la grilla y lee OrdColProgramaTejido
 * del usuario. En modo sintético todo vive en un sqlite :memory: que muere con el proceso.
 *
 * Con usuario (o --sintetico) imprime además "corte 7 simulado": el render si no se
 * emitieran sus columnas ocultas, para decidir ese corte con números.
 *
 * El render es el de la vista (sin middleware ni sesión), como en la medición original:
 * el TTFB real es mayor. "JS inline" cuenta los <script> sin src que ejecutan código
 * (no cuenta type="application/json").
 */

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Models\Planeacion\OrdColProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/sembrar-sintetico.php';

$opts = getopt('', ['sintetico', 'usuario::', 'muestras', 'repeticiones::']);
$sintetico = array_key_exists('sintetico', $opts);
$muestras = array_key_exists('muestras', $opts);
$repeticiones = max(1, (int) ($opts['repeticiones'] ?? 5));

// La superficie se decide por el path del request, igual que en la app.
$request = Request::create($muestras ? '/planeacion/muestras' : '/planeacion/programa-tejido');
$app->instance('request', $request);
$superficie = ProgramaTejidoSurface::actual();
config()->set('planeacion.programa_tejido_table', $superficie->tabla());
config()->set('planeacion.programa_tejido_line_table', $superficie->tablaLineas());

$columns = UtilityHelpers::getTableColumns();

if ($sintetico) {
    config()->set('database.connections.sqlsrv', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('database.default', 'sqlsrv');
    DB::purge('sqlsrv');
    sembrarSintetico($columns, $superficie->tabla());
    // 59 de las columnas ocultas, deterministas (la forma del usuario 74 de la medición).
    // Pares desde la 6ª y luego impares: deja visibles las primeras (Estado, Telar...).
    $campos = array_column(array_slice($columns, 6), 'field');
    $ocultas = array_merge(
        array_values(array_filter($campos, fn ($i) => $i % 2 === 0, ARRAY_FILTER_USE_KEY)),
        array_values(array_filter($campos, fn ($i) => $i % 2 === 1, ARRAY_FILTER_USE_KEY)),
    );
    $ocultas = array_slice($ocultas, 0, 59);
} else {
    $usuario = (int) ($opts['usuario'] ?? 0);
    $ocultas = $usuario
        ? OrdColProgramaTejido::query()->where('UsuarioId', $usuario)->where('Estado', 1)->pluck('Columna')->all()
        : [];
}

// El navbar necesita un usuario en sesión. Real: el usuario pedido, con sus permisos de BD.
// Sintético: uno en memoria con todos los permisos de Programa (2) y Muestras (5).
if ($sintetico || ! empty($usuario)) {
    $u = $sintetico ? null : \App\Models\Sistema\Usuario::find($usuario);
    if (! $u) {
        $u = new \App\Models\Sistema\Usuario(['nombre' => 'Medición']);
        $u->idusuario = 999100;
        app()->instance('permisos.roles', collect([
            'programa tejido' => (object) ['idrol' => 2, 'modulo' => 'Programa Tejido'],
            'muestras' => (object) ['idrol' => 5, 'modulo' => 'Muestras'],
        ]));
        $todo = (object) ['acceso' => 1, 'crear' => 1, 'modificar' => 1, 'eliminar' => 1, 'registrar' => 1];
        app()->instance('permisos.usuario.999100', collect([2 => $todo, 5 => $todo]));
    }
    \Illuminate\Support\Facades\Auth::setUser($u);
}

$t0 = hrtime(true);
$registros = ReqProgramaTejido::query()->ordenado()->get();
$msQuery = (hrtime(true) - $t0) / 1e6;

$datos = [
    'superficie' => $superficie,
    'isMuestras' => $superficie->esMuestras(),
    'capacidades' => $superficie->capacidades(),
    'basePath' => $superficie->basePath(),
    'apiPath' => $superficie->apiPath(),
    'linePath' => $superficie->linePath(),
    'pageTitle' => $superficie->titulo(),
    'registros' => $registros,
    'columns' => $columns,
];

$medir = function (array $hidden) use (&$datos, $repeticiones): array {
    $mejor = INF;
    $html = '';
    for ($i = 0; $i < $repeticiones; $i++) {
        app('view')->flushState();
        $t = hrtime(true);
        $html = view('modulos.programa-tejido.req-programa-tejido', [...$datos, 'hiddenFields' => $hidden])->render();
        $mejor = min($mejor, (hrtime(true) - $t) / 1e6);
    }

    return [$html, $mejor];
};

$kb = fn (int $bytes) => number_format($bytes / 1024, 0, ',', ' ').' KB';

$reporte = function (string $titulo, string $html, float $ms, int $nOcultas) use ($kb, $registros, $columns) {
    preg_match('#<tbody.*?</tbody>#s', $html, $tb);
    $tbody = $tb[0] ?? '';
    preg_match_all('#<script\b(?![^>]*\bsrc\s*=)(?![^>]*type="application/json")[^>]*>(.*?)</script>#si', $html, $js);
    $tamanos = array_map('strlen', $js[1]);
    rsort($tamanos);
    preg_match_all('#<td[^>]*style="display:none"[^>]*>.*?</td>#s', $tbody, $oc);
    $bytesOcultas = array_sum(array_map('strlen', $oc[0]));

    echo "== {$titulo} ==".PHP_EOL;
    printf("  filas / columnas / ocultas     %d / %d / %d\n", $registros->count(), count($columns), $nOcultas);
    printf("  HTML                           %s plano / %s gzip\n", $kb(strlen($html)), $kb(strlen(gzencode($html, 6))));
    printf("  celdas <td                     %d\n", substr_count($html, '<td'));
    printf("  tbody                          %s (%s en celdas ocultas = %d %%)\n", $kb(strlen($tbody)), $kb($bytesOcultas), strlen($tbody) ? round(100 * $bytesOcultas / strlen($tbody)) : 0);
    printf("  JS inline                      %d bloques, %s (mayor %s)\n", count($tamanos), $kb(array_sum($tamanos)), $kb($tamanos[0] ?? 0));
    printf("  render Blade (mejor de N)      %.0f ms\n", $ms);
    foreach ($js[1] as $bloque) {
        $firma = preg_replace('/\s+/', ' ', trim(substr(trim($bloque), 0, 70)));
        printf("    %7s  %s\n", $kb(strlen($bloque)), $firma);
    }
};

echo 'Superficie: '.$superficie->value.($sintetico ? ' (sintético, sqlite)' : ' (datos reales)').PHP_EOL;
printf("Query + hydrate: %.0f ms\n\n", $msQuery);

[$html, $ms] = $medir([]);
$reporte('sin columnas ocultas', $html, $ms, 0);
[$html, $ms] = $medir($ocultas);
$reporte('usuario con columnas ocultas', $html, $ms, count($ocultas));

// Corte 7 simulado: el mismo usuario si el servidor NO emitiera sus columnas ocultas.
// Solo para decidir con números; no es la implementación (column-N dejaría de ser posicional).
if ($ocultas) {
    $datos['columns'] = array_values(array_filter($columns, fn ($c) => ! in_array($c['field'], $ocultas, true)));
    [$html, $ms] = $medir([]);
    $reporte('corte 7 simulado (sin emitir ocultas)', $html, $ms, 0);
    $datos['columns'] = $columns;
}

// Bundle de Vite (lo que el navegador cachea): entrada + chunks importados.
$manifest = $root.'/public/build/manifest.json';
if (is_file($manifest)) {
    $m = json_decode((string) file_get_contents($manifest), true);
    $visitados = [];
    $pila = ['resources/js/programa-tejido/index.js'];
    $plano = $gzip = 0;
    while ($pila) {
        $k = array_pop($pila);
        if (isset($visitados[$k]) || ! isset($m[$k])) {
            continue;
        }
        $visitados[$k] = true;
        $contenido = (string) file_get_contents($root.'/public/build/'.$m[$k]['file']);
        $plano += strlen($contenido);
        $gzip += strlen(gzencode($contenido, 6));
        array_push($pila, ...($m[$k]['imports'] ?? []));
    }
    printf("\nBundle programa-tejido/index.js  %s plano / %s gzip (%d archivos, cacheable)\n", $kb($plano), $kb($gzip), count($visitados));
}
