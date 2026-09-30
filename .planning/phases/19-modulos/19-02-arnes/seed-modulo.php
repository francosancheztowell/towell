<?php

// Datos mínimos de Tejido para que las pantallas del arnés tengan filas.
use Illuminate\Support\Facades\DB;

function ins(string $modelo, array $filas): void
{
    $m = new $modelo;
    $t = $m->getTable();
    $db = DB::connection('sqlite');
    [$s, $n] = str_contains($t, '.') ? ['dbo', explode('.', $t, 2)[1]] : ['main', $t];
    $cols = array_column($db->select("PRAGMA $s.table_info(\"$n\")"), 'name');
    foreach ($filas as $f) {
        $f = array_intersect_key($f, array_flip($cols));
        $db->table($t)->insert($f);
        $espejo = $s === 'dbo' ? $n : 'dbo.'.$n;
        $db->table($espejo)->insert($f);
    }
}
$hoy = date('Y-m-d');
$ahora = date('Y-m-d H:i:s');
ins(App\Models\Inventario\InvSecuenciaTelares::class, [
    ['Id' => 1, 'NoTelar' => 201, 'TipoTelar' => 'JACQUARD', 'Secuencia' => 1, 'Observaciones' => "Telar d'Ana <b>"],
    ['Id' => 2, 'NoTelar' => 202, 'TipoTelar' => 'JACQUARD', 'Secuencia' => 2, 'Observaciones' => ''],
    ['Id' => 3, 'NoTelar' => 301, 'TipoTelar' => 'ITEMA', 'Secuencia' => 3, 'Observaciones' => ''],
]);
ins(App\Models\Inventario\InvSecuenciaTrama::class, [
    ['Id' => 1, 'NoTelar' => 201, 'TipoTelar' => 'JACQUARD', 'Secuencia' => 1],
    ['Id' => 2, 'NoTelar' => 301, 'TipoTelar' => 'ITEMA', 'Secuencia' => 2],
]);
foreach ([App\Models\Inventario\InvSecuenciaCorteEf::class, App\Models\Inventario\InvSecuenciaMarcas::class] as $modelo) {
    ins($modelo, [
        ['Id' => 1, 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Orden' => 1],
        ['Id' => 2, 'NoTelarId' => '202', 'SalonTejidoId' => 'JACQUARD', 'Orden' => 2],
        ['Id' => 3, 'NoTelarId' => '301', 'SalonTejidoId' => 'ITEMA', 'Orden' => 3],
    ]);
}
ins(App\Models\Tejido\TejEficiencia::class, [
    ['Id' => 1, 'Folio' => 'CE0001', 'Date' => $hoy, 'Turno' => 1, 'Status' => 'Finalizado', 'numero_empleado' => '1001', 'nombreEmpl' => 'Usuario Prueba', 'Horario1' => '07:00', 'Horario2' => '09:00', 'Horario3' => '11:00', 'created_at' => $ahora, 'updated_at' => $ahora],
    ['Id' => 2, 'Folio' => 'CE0002', 'Date' => $hoy, 'Turno' => 2, 'Status' => 'En Proceso', 'numero_empleado' => '1001', 'nombreEmpl' => 'Usuario Prueba', 'Horario1' => '15:00', 'created_at' => $ahora, 'updated_at' => $ahora],
]);
ins(App\Models\Tejido\TejEficienciaLine::class, [
    ['Id' => 1, 'Folio' => 'CE0001', 'Date' => $hoy, 'Turno' => 1, 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'RpmStd' => 400, 'EficienciaSTD' => 0.85, 'RpmR1' => 390, 'EficienciaR1' => 0.8, 'ObsR1' => 'ok', 'created_at' => $ahora, 'updated_at' => $ahora],
    ['Id' => 2, 'Folio' => 'CE0001', 'Date' => $hoy, 'Turno' => 1, 'NoTelarId' => '202', 'SalonTejidoId' => 'JACQUARD', 'RpmStd' => 400, 'EficienciaSTD' => 0.85, 'RpmR1' => 380, 'EficienciaR1' => 0.75, 'created_at' => $ahora, 'updated_at' => $ahora],
    ['Id' => 3, 'Folio' => 'CE0002', 'Date' => $hoy, 'Turno' => 2, 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'RpmStd' => 400, 'EficienciaSTD' => 0.85, 'RpmR1' => 395, 'EficienciaR1' => 0.82, 'created_at' => $ahora, 'updated_at' => $ahora],
]);
ins(App\Models\Tejido\TejMarcas::class, [
    ['Id' => 1, 'Folio' => 'MF0001', 'Date' => $hoy, 'Turno' => 1, 'Status' => 'Finalizado', 'numero_empleado' => '1001', 'nombreEmpl' => 'Usuario Prueba', 'created_at' => $ahora, 'updated_at' => $ahora],
    ['Id' => 2, 'Folio' => 'MF0002', 'Date' => $hoy, 'Turno' => 2, 'Status' => 'En Proceso', 'numero_empleado' => '1001', 'nombreEmpl' => 'Usuario Prueba', 'created_at' => $ahora, 'updated_at' => $ahora],
]);
ins(App\Models\Tejido\TejMarcasLine::class, [
    ['Id' => 1, 'Folio' => 'MF0001', 'Date' => $hoy, 'Turno' => 1, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'Eficiencia' => 0.8, 'Marcas' => 120, 'Trama' => 3, 'Pie' => 1, 'Rizo' => 2, 'Otros' => 0, 'created_at' => $ahora, 'updated_at' => $ahora],
    ['Id' => 2, 'Folio' => 'MF0002', 'Date' => $hoy, 'Turno' => 2, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'Eficiencia' => 0.82, 'Marcas' => 110, 'Trama' => 1, 'Pie' => 0, 'Rizo' => 1, 'Otros' => 0, 'created_at' => $ahora, 'updated_at' => $ahora],
]);
ins(App\Models\Tejido\TejProduccionReenconado::class, [
    ['Folio' => 'RC0001', 'Date' => $hoy, 'Turno' => 1, 'numero_empleado' => '1001', 'nombreEmpl' => 'Usuario Prueba', 'Calibre' => '20/1', 'FibraTrama' => 'ALGODON', 'CodColor' => 'B01', 'Color' => 'BLANCO', 'Cantidad' => 50, 'Cabezuela' => 2, 'Conos' => 10, 'Horas' => 8, 'Eficiencia' => 0.9, 'Obs' => "d'Ana", 'status' => 'Creado', 'capacidad' => 60],
]);
// reenconado (19-02-a): catálogos de TI_PRO para calibre/fibra/color y la secuencia del folio.
(function (): void {
    $db = DB::connection('sqlite');
    $db->statement('CREATE TABLE IF NOT EXISTS InventTable (ItemId TEXT, ItemGroupId TEXT, DATAAREAID TEXT)');
    $db->statement('CREATE TABLE IF NOT EXISTS ConfigTable (ItemId TEXT, ConfigId TEXT, DATAAREAID TEXT)');
    $db->statement('CREATE TABLE IF NOT EXISTS InventColor (ItemId TEXT, InventColorId TEXT, Name TEXT, DATAAREAID TEXT)');
    $db->table('InventTable')->insert([
        ['ItemId' => '20/1', 'ItemGroupId' => 'HILO DIREC', 'DATAAREAID' => 'PRO'],
        ['ItemId' => '30/1', 'ItemGroupId' => 'HILO DIREC', 'DATAAREAID' => 'PRO'],
    ]);
    $db->table('ConfigTable')->insert([
        ['ItemId' => '20/1', 'ConfigId' => 'ALGODON', 'DATAAREAID' => 'PRO'],
        ['ItemId' => '30/1', 'ConfigId' => 'POLIESTER', 'DATAAREAID' => 'PRO'],
    ]);
    $db->table('InventColor')->insert([
        ['ItemId' => '20/1', 'InventColorId' => 'B01', 'Name' => 'BLANCO', 'DATAAREAID' => 'PRO'],
        ['ItemId' => '30/1', 'InventColorId' => 'N01', 'Name' => "NEGRO d'Ana", 'DATAAREAID' => 'PRO'],
    ]);
})();
ins(App\Models\Sistema\SSYSFoliosSecuencia::class, [
    ['modulo' => 'Reenconado', 'prefijo' => 'RC', 'consecutivo' => 1],
]);
