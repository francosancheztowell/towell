<?php

/**
 * Semilla de PT-TS 1 para el arnés de 19-01. Correr DESPUÉS de setup.php (19-01) y seed-pt.php (03):
 *
 *   export ARNES_DATOS=/tmp/towell-arnes
 *   php .planning/phases/19-modulos/19-01-arnes/setup.php
 *   php .planning/phases/03-frontend-shell/arnes/seed-pt.php
 *   php .planning/phases/04-ux-grid/arnes-pt-ts/seed.php
 *
 * Deja 8 filas de Programa sin orden de producción con inicio hoy (Liberar Órdenes) y las
 * tablas de AX que lee Liberar (INVENTTABLE, BOM, flogs) con datos mínimos.
 */

use Illuminate\Support\Facades\DB;

$raiz = dirname(__DIR__, 4);
require $raiz.'/.planning/phases/19-modulos/19-01-arnes/boot.php';
$app = require REPO.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, fn () => harness_config());
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
harness_attach();

$ti = DB::connection('sqlsrv_ti');
foreach (['main', 'dbo'] as $s) {
    $ti->statement("CREATE TABLE IF NOT EXISTS $s.\"INVENTTABLE\" (\"ITEMID\" TEXT, \"TwTipoHiloId\" TEXT)");
    $ti->statement("CREATE TABLE IF NOT EXISTS $s.\"BOMTABLE\" (\"BOMID\" TEXT, \"NAME\" TEXT, \"ITEMGROUPID\" TEXT, \"TWINVENTSIZEID\" TEXT, \"TWSALON\" TEXT, \"Vigente\" INTEGER DEFAULT 1)");
    $ti->statement("CREATE TABLE IF NOT EXISTS $s.\"BOMVERSION\" (\"BOMID\" TEXT, \"ITEMID\" TEXT)");
    $ti->statement("CREATE TABLE IF NOT EXISTS $s.\"TwArticulosFelpas\" (\"ITEMID\" TEXT, \"INVENTSIZEID\" TEXT, \"ITEMNAME\" TEXT)");
    $ti->statement("CREATE TABLE IF NOT EXISTS $s.\"TwFlogsTable\" (\"IDFLOG\" TEXT, \"NAMEPROYECT\" TEXT, \"CUSTNAME\" TEXT, \"ESTADOFLOG\" INTEGER)");
    $ti->statement("CREATE TABLE IF NOT EXISTS $s.\"TwFlogsItemLine\" (\"IDFLOG\" TEXT, \"ITEMID\" TEXT, \"INVENTSIZEID\" TEXT)");
    $ti->statement("CREATE TABLE IF NOT EXISTS $s.\"TwFlogsCustomer\" (\"IdFlog\" TEXT, \"CustName\" TEXT, \"CategoriaCalidad\" TEXT)");
}
foreach (['ALGODON', 'POLIESTER', 'MIXTO'] as $h) {
    $ti->table('INVENTTABLE')->insert(['ITEMID' => 'X-'.$h, 'TwTipoHiloId' => $h]);
}

$ids = DB::connection('sqlsrv')->table('ReqProgramaTejido')->orderBy('Id')->limit(8)->pluck('Id');
$hoy = now()->startOfDay();
foreach ($ids as $i => $id) {
    DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->update([
        'NoProduccion' => null,
        'FechaInicio' => $hoy->copy()->addHours($i)->format('Y-m-d H:i:s'),
    ]);
}
// Líneas diarias de la primera fila (tabla y modal "Detalle del Telar").
$primera = DB::connection('sqlsrv')->table('ReqProgramaTejido')->orderBy('Id')->value('Id');
foreach (range(0, 4) as $d) {
    DB::connection('sqlsrv')->table('ReqProgramaTejidoLine')->insert([
        'ProgramaId' => $primera, 'Fecha' => $hoy->copy()->addDays($d)->format('Y-m-d'),
        'Cantidad' => 1234.5 + $d, 'Kilos' => 88.4, 'Aplicacion' => 0, 'Trama' => 12, 'Rizo' => 7.5,
    ]);
}

// Grupo de orden compartida sano (900) para el balanceo: pedido > producción, con fechas.
foreach (DB::connection('sqlsrv')->table('ReqProgramaTejido')->orderBy('Id')->skip(20)->limit(2)->pluck('Id') as $i => $id) {
    DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->update([
        'OrdCompartida' => 900,
        'TotalPedido' => $i === 0 ? 1000 : 2000, 'Produccion' => 0, 'StdDia' => 500,
        'FechaInicio' => $hoy->copy()->addHours(6)->format('Y-m-d H:i:s'),
        'FechaFinal' => $hoy->copy()->addDays(3 + 2 * $i)->format('Y-m-d H:i:s'),
    ]);
}

echo 'PT-TS 1: '.count($ids)." filas para liberar\n";
