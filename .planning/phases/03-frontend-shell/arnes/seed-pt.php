<?php

/**
 * Semilla de Programa Tejido para el arnés de 19-01 (PT 03). Correr DESPUÉS de su setup.php:
 *
 *   export ARNES_DATOS=/tmp/towell-arnes
 *   php .planning/phases/19-modulos/19-01-arnes/setup.php
 *   php .planning/phases/03-frontend-shell/arnes/seed-pt.php
 *
 * Programa y Muestras con las 85 filas sintéticas de 04-perf (sembrar-sintetico.php) y el
 * usuario 1 con 59 columnas ocultas (la forma del usuario 74 de la medición).
 */

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$raiz = dirname(__DIR__, 4);
require $raiz.'/.planning/phases/19-modulos/19-01-arnes/boot.php';
$app = require REPO.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, fn () => harness_config());
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
harness_attach();
require $raiz.'/.planning/phases/04-ux-grid/sembrar-sintetico.php';

$columns = UtilityHelpers::getTableColumns();
foreach (['ReqProgramaTejido', 'MuestrasPrograma', 'OrdColProgramaTejido'] as $tabla) {
    Schema::connection('sqlsrv')->dropIfExists($tabla);
}
sembrarSintetico($columns, 'ReqProgramaTejido');
sembrarSintetico($columns, 'MuestrasPrograma', false);

$campos = array_column(array_slice($columns, 6), 'field');
$ocultas = array_slice(array_merge(
    array_values(array_filter($campos, fn ($i) => $i % 2 === 0, ARRAY_FILTER_USE_KEY)),
    array_values(array_filter($campos, fn ($i) => $i % 2 === 1, ARRAY_FILTER_USE_KEY)),
), 0, 59);
foreach ($ocultas as $c) {
    DB::connection('sqlsrv')->table('OrdColProgramaTejido')->insert(['UsuarioId' => 1, 'Columna' => $c, 'Estado' => 1]);
}
// Los botones del navbar piden permisos por NOMBRE de módulo: idrol 2 y 5 con su nombre real.
foreach (['SYSRoles', 'dbo.SYSRoles'] as $t) {
    DB::connection('sqlite')->table($t)->where('idrol', 2)->update(['modulo' => 'Programa Tejido']);
    DB::connection('sqlite')->table($t)->where('idrol', 5)->update(['modulo' => 'Muestras']);
}
echo 'PT: 85 filas × 2 superficies, '.count($ocultas)." columnas ocultas para el usuario 1\n";
