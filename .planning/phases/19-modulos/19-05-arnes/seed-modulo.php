<?php

// Semilla de Programa Urdido-Engomado (19-05): telares, TI_PRO simulado, BOM, catálogos y órdenes.
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

/** Tabla de TI_PRO (no tiene modelo): solo en main, que es el esquema por defecto de sqlsrv_ti. */
function ti(string $tabla, array $filas): void
{
    $db = DB::connection('sqlite');
    $cols = array_keys(array_merge(...$filas));
    $db->statement("CREATE TABLE IF NOT EXISTS \"$tabla\" (".implode(', ', array_map(fn ($c) => "\"$c\"", $cols)).')');
    foreach ($filas as $f) {
        $db->table($tabla)->insert($f);
    }
}

$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));
$manana = date('Y-m-d', strtotime('+1 day'));

/* ---------- Reservar y programar: telares activos (tej_inventario_telares) ---------- */
$telar = fn (int $id, string $no, string $tipo, array $extra = []) => array_merge([
    'id' => $id, 'no_telar' => $no, 'status' => 'Activo', 'tipo' => $tipo, 'cuenta' => $tipo === 'Rizo' ? '3040' : '2020',
    'calibre' => $tipo === 'Rizo' ? 12.5 : 10, 'fecha' => $hoy, 'turno' => 1, 'hilo' => $tipo === 'Rizo' ? 'A12' : 'P10',
    'metros' => 0, 'no_julio' => null, 'no_orden' => null, 'tipo_atado' => 'Normal', 'salon' => 'JACQUARD',
    'Reservado' => 0, 'Programado' => 0,
], $extra);
ins(App\Models\Tejido\TejInventarioTelares::class, [
    $telar(1, '201', 'Rizo'),
    $telar(2, '202', 'Rizo'),
    $telar(3, '203', 'Rizo', ['fecha' => $manana, 'turno' => 2]),
    $telar(4, '201', 'Pie', ['fecha' => $manana]),
    $telar(5, '204', 'Rizo', ['Reservado' => 1, 'no_julio' => 'J-501', 'metros' => 5200, 'localidad' => 'A-12']),
    $telar(6, '205', 'Pie', ['Programado' => 1, 'no_orden' => 'U00102', 'salon' => 'SMIT']),
    $telar(7, '206', 'Rizo', ['salon' => 'SMIT', 'tipo_atado' => 'Especial']),
    $telar(8, '401', 'Rizo', ['salon' => 'KARL MAYER', 'cuenta' => '1800', 'calibre' => 8]),
]);
ins(App\Models\Planeacion\ReqTelares::class, [
    ['Id' => 1, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'Nombre' => 'JAC 201', 'Grupo' => 'Jacquard Smit'],
    ['Id' => 2, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '202', 'Nombre' => 'JAC 202', 'Grupo' => 'Jacquard Smit'],
    ['Id' => 3, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '203', 'Nombre' => 'JAC 203', 'Grupo' => 'Jacquard Smit'],
    ['Id' => 4, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '206', 'Nombre' => 'SMIT 206', 'Grupo' => 'Smit'],
]);
ins(App\Models\Inventario\InvTelasReservadas::class, [
    ['Id' => 1, 'ItemId' => 'JU-ENG-RI-C', 'ConfigId' => 'A12', 'InventSizeId' => '3040', 'InventColorId' => 'BCO', 'InventLocationId' => 'A-JUL/TELA',
        'InventBatchId' => 'L-900', 'WMSLocationId' => 'A-12', 'InventSerialId' => 'J-501', 'Tipo' => 'Rizo', 'Metros' => 5200, 'InventQty' => 410,
        'ProdDate' => $ayer, 'NoTelarId' => '204', 'SalonTejidoId' => 'JACQUARD', 'Fecha' => $hoy, 'Turno' => 1, 'TejInventarioTelaresId' => 5,
        'Status' => 'Reservado', 'NumeroEmpleado' => '1001', 'NombreEmpl' => 'Usuario Prueba', 'created_at' => $hoy.' 07:00:00', 'updated_at' => $hoy.' 07:00:00'],
]);

/* ---------- TI_PRO: inventario de julios (A-JUL/*) y de hilo (A-MP) ---------- */
$dim = fn (string $id, string $cfg, string $size, string $loc, string $batch, string $wms, string $serial) => [
    'InventDimId' => $id, 'DATAAREAID' => 'PRO', 'ConfigId' => $cfg, 'InventSizeId' => $size, 'InventColorId' => 'BCO',
    'InventLocationId' => $loc, 'InventBatchId' => $batch, 'WMSLocationId' => $wms, 'InventSerialId' => $serial,
];
ti('InventDim', [
    $dim('D1', 'A12', '3040', 'A-JUL/TELA', 'L-900', 'A-12', 'J-501'),
    $dim('D2', 'A12', '3040', 'A-JUL/TELA', 'L-901', 'A-13', 'J-502'),
    $dim('D3', 'P10', '2020', 'A-JUL/TELA', 'L-902', 'B-01', 'J-503'),
    $dim('D4', 'A12', '3040', 'A-JUL/URD', '01269', 'C-02', 'J-504'),
    $dim('D5', 'A12', '3040', 'A-JUL/TELA', 'L-903', 'MC1', 'J-505'),
    $dim('M1', 'A12', 'ENTERO', 'A-MP', 'LP-777', 'MP-01', 'S-1001'),
    $dim('M2', 'A12', 'ENTERO', 'A-MP', 'LP-778', 'MP-02', 'S-1002'),
    $dim('M3', 'A12', 'ENTERO', 'A-MPBB', 'LP-779', 'MP-03', 'S-1003'),
]);
$sum = fn (string $item, string $dimId, float $qty) => ['ItemId' => $item, 'InventDimId' => $dimId, 'DATAAREAID' => 'PRO', 'PhysicalInvent' => $qty, 'ReservPhysical' => 0];
ti('InventSum', [
    $sum('JU-ENG-RI-C', 'D1', 410), $sum('JU-ENG-RI-C', 'D2', 395.5), $sum('JU-ENG-PI-C', 'D3', 280),
    $sum('JULIO-URDIDO', 'D4', 150), $sum('JU-ENG-RI-C', 'D5', 300),
    $sum('H-A12', 'M1', 820), $sum('H-A12', 'M2', 640), $sum('H-A12', 'M3', 510),
]);
$ser = fn (string $item, string $serial, float $mts, int $tiras = 0) => ['ItemId' => $item, 'InventSerialId' => $serial, 'DATAAREAID' => 'PRO',
    'TwMts' => $mts, 'ProdDate' => $ayer, 'TwTiras' => $tiras, 'TwCalidadFlog' => 'PROV-A', 'TwClienteFlog' => 'P-01'];
ti('InventSerial', [
    $ser('JU-ENG-RI-C', 'J-501', 5200), $ser('JU-ENG-RI-C', 'J-502', 5000), $ser('JU-ENG-PI-C', 'J-503', 4800),
    $ser('JULIO-URDIDO', 'J-504', 3000), $ser('JU-ENG-RI-C', 'J-505', 4500),
    $ser('H-A12', 'S-1001', 0, 48), $ser('H-A12', 'S-1002', 0, 36), $ser('H-A12', 'S-1003', 0, 30),
]);
ti('ConfigTable', [
    ['ItemId' => 'JU-ENG-RI-C', 'ConfigId' => 'A12', 'DATAAREAID' => 'PRO', 'TwVigente' => 1],
    ['ItemId' => 'JU-ENG-RI-C', 'ConfigId' => 'A16', 'DATAAREAID' => 'PRO', 'TwVigente' => 1],
    ['ItemId' => 'JU-ENG-RI-C', 'ConfigId' => 'HILO', 'DATAAREAID' => 'PRO', 'TwVigente' => 1],
    ['ItemId' => 'JU-ENG-PI-C', 'ConfigId' => 'P10', 'DATAAREAID' => 'PRO', 'TwVigente' => 1],
    ['ItemId' => 'JULIO-URDIDO', 'ConfigId' => 'A12', 'DATAAREAID' => 'PRO', 'TwVigente' => 0],
]);
ti('InventSize', [
    // Formato real "cuenta-calibre/n": programación y Karl Mayer sacan cuenta y calibre de aquí.
    ['ItemId' => 'JU-ENG-RI-C', 'InventSizeId' => '3040-12.5/1', 'DATAAREAID' => 'PRO', 'TwVigente' => 1],
    ['ItemId' => 'JU-ENG-RI-C', 'InventSizeId' => '3240-12.5/1', 'DATAAREAID' => 'PRO', 'TwVigente' => 1],
    ['ItemId' => 'JU-ENG-PI-C', 'InventSizeId' => '2020-10/1', 'DATAAREAID' => 'PRO', 'TwVigente' => 1],
    ['ItemId' => 'JULIO-URDIDO', 'InventSizeId' => '1800-8/1', 'DATAAREAID' => 'PRO', 'TwVigente' => 0],
]);
ti('BOMTABLE', [
    ['BOMID' => 'URD 3040-A12', 'NAME' => 'Urdido 3040 A12', 'DATAAREAID' => 'PRO', 'ITEMGROUPID' => 'JUL-URD', 'Vigente' => 1],
    ['BOMID' => 'URD 1800-KM', 'NAME' => 'Urdido Karl Mayer 1800', 'DATAAREAID' => 'PRO', 'ITEMGROUPID' => 'JUL-URD', 'Vigente' => 1],
    ['BOMID' => 'ENG 3040-A12', 'NAME' => 'Engomado 3040 A12', 'DATAAREAID' => 'PRO', 'ITEMGROUPID' => 'JUL-ENG', 'Vigente' => 1],
]);
ti('BOM', [
    ['BOMID' => 'URD 3040-A12', 'ITEMID' => 'H-A12', 'BOMQTY' => 1.02, 'INVENTDIMID' => 'M1', 'DATAAREAID' => 'PRO'],
    ['BOMID' => 'URD 1800-KM', 'ITEMID' => 'H-A12', 'BOMQTY' => 1.01, 'INVENTDIMID' => 'M1', 'DATAAREAID' => 'PRO'],
    ['BOMID' => 'ENG 3040-A12', 'ITEMID' => 'TE-PD-ENF-100', 'BOMQTY' => 0.05, 'INVENTDIMID' => 'M1', 'DATAAREAID' => 'PRO'],
    ['BOMID' => 'ENG 3040-A12', 'ITEMID' => 'TE-PD-ENF-200', 'BOMQTY' => 0.04, 'INVENTDIMID' => 'M1', 'DATAAREAID' => 'PRO'],
]);
ti('BOMVersion', [['ItemId' => 'JU-ENG-RI-C', 'BomId' => 'ENG 3040-A12', 'DATAAREAID' => 'PRO']]);
ti('INVENTTABLE', [['ITEMID' => 'H-A12', 'ITEMNAME' => 'Hilo algodón 12/1', 'DATAAREAID' => 'PRO']]);

/* ---------- Catálogos locales ---------- */
// MaquinaId es el nombre largo (lo guardan las órdenes y los <select> de edición); KM1 es el Nombre de Karl Mayer.
ins(App\Models\Urdido\URDCatalogoMaquina::class, [
    ['MaquinaId' => 'Mc Coy 1', 'Nombre' => 'Mc Coy 1', 'Departamento' => 'Urdido'],
    ['MaquinaId' => 'Mc Coy 2', 'Nombre' => 'Mc Coy 2', 'Departamento' => 'Urdido'],
    ['MaquinaId' => 'Mc Coy 3', 'Nombre' => 'Mc Coy 3', 'Departamento' => 'Urdido'],
    ['MaquinaId' => 'Karl Mayer', 'Nombre' => 'KM1', 'Departamento' => 'Urdido'],
    ['MaquinaId' => 'West Point 2', 'Nombre' => 'West Point 2', 'Departamento' => 'Engomado'],
    ['MaquinaId' => 'West Point 3', 'Nombre' => 'West Point 3', 'Departamento' => 'Engomado'],
]);
ins(App\Models\UrdEngomado\UrdEngNucleos::class, [['Id' => 1, 'Salon' => 'JACQUARD', 'Nombre' => 'Jacquard'], ['Id' => 2, 'Salon' => 'SMIT', 'Nombre' => 'Smit']]);
ins(App\Models\Engomado\EngAnchoBalonaCuenta::class, [
    ['Id' => 1, 'Cuenta' => '3040', 'RizoPie' => 'Rizo', 'AnchoBalona' => 150],
    ['Id' => 2, 'Cuenta' => '3040', 'RizoPie' => 'Rizo', 'AnchoBalona' => 160],
    ['Id' => 3, 'Cuenta' => '2020', 'RizoPie' => 'Pie', 'AnchoBalona' => 120],
]);
ins(App\Models\Sistema\SSYSFoliosSecuencia::class, [
    ['Id' => 1, 'modulo' => 'CambioHilo', 'prefijo' => 'CH', 'consecutivo' => 40],
    ['Id' => 14, 'modulo' => 'URD/ENG', 'prefijo' => '', 'consecutivo' => 110],
]);

/* ---------- Programa de tejido (resumen de 5 semanas) ---------- */
ins(App\Models\Planeacion\ReqProgramaTejido::class, [
    ['Id' => 1, 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'CuentaRizo' => '3040', 'CalibreRizo' => 12.5, 'FibraRizo' => 'A12', 'ItemId' => 'TOA-100', 'NombreProducto' => 'Toalla baño 100', 'CuentaPie' => '2020', 'CalibrePie' => 10, 'FibraPie' => 'P10'],
    ['Id' => 2, 'NoTelarId' => '202', 'SalonTejidoId' => 'JACQUARD', 'CuentaRizo' => '3040', 'CalibreRizo' => 12.5, 'FibraRizo' => 'A12', 'ItemId' => 'TOA-200', 'NombreProducto' => 'Toalla mano 200', 'CuentaPie' => '2020', 'CalibrePie' => 10, 'FibraPie' => 'P10'],
]);
$lineas = [];
$lid = 1;
foreach ([1, 2] as $prog) {
    for ($d = 0; $d < 28; $d += 3) {
        $lineas[] = ['Id' => $lid++, 'ProgramaId' => $prog, 'Fecha' => date('Y-m-d', strtotime("+$d day")), 'Rizo' => 40 + $d, 'Pie' => 20 + $d, 'MtsRizo' => 500 + 10 * $d, 'MtsPie' => 300 + 5 * $d];
    }
}
ins(App\Models\Planeacion\ReqProgramaTejidoLine::class, $lineas);

/* ---------- Órdenes para los tableros y la edición ---------- */
$urd = fn (int $id, string $folio, string $maq, string $status, int $prio, array $extra = []) => array_merge([
    'Id' => $id, 'Folio' => $folio, 'NoTelarId' => '201', 'RizoPie' => 'Rizo', 'Cuenta' => '3040', 'Calibre' => 12.5, 'FechaReq' => $manana,
    'Fibra' => 'A12', 'InventSizeId' => '3040-12.5/1', 'Metros' => 12000, 'Kilos' => 850, 'SalonTejidoId' => 'JACQUARD', 'MaquinaId' => $maq,
    'BomId' => 'URD 3040-A12', 'FechaProg' => $hoy, 'Status' => $status, 'FolioConsumo' => 'CH00039', 'BomFormula' => 'TE-PD-ENF-100',
    'TipoAtado' => 'Normal', 'CveEmpl' => '1001', 'NomEmpl' => 'Usuario Prueba', 'LoteProveedor' => 'LP-777', 'Prioridad' => $prio,
    'CreatedAt' => $hoy.' 08:00:00', 'Observaciones' => null,
], $extra);
ins(App\Models\Urdido\UrdProgramaUrdido::class, [
    $urd(1, '00101', 'Mc Coy 1', 'En Proceso', 1),
    $urd(2, '00102', 'Mc Coy 1', 'Programado', 2, ['NoTelarId' => '205', 'RizoPie' => 'Pie', 'Cuenta' => '2020', 'Calibre' => 10, 'Fibra' => 'P10', 'InventSizeId' => '2020-10/1']),
    $urd(3, '00103', 'Mc Coy 2', 'Programado', 1, ['NoTelarId' => '202,203', 'Metros' => 9500, 'Observaciones' => 'Urgente para Jacquard']),
    $urd(4, '00104', 'Mc Coy 2', 'Parcial', 2, ['Metros' => 7000]),
    $urd(5, '00105', 'Mc Coy 3', 'Programado', 1, ['NoTelarId' => '206', 'SalonTejidoId' => 'SMIT']),
    $urd(6, '00106', 'Karl Mayer', 'Programado', 1, ['NoTelarId' => '401', 'RizoPie' => '1', 'Cuenta' => '1800', 'Calibre' => 8, 'BomId' => 'URD 1800-KM']),
    $urd(7, '00107', 'Mc Coy 3', 'Finalizado', 0),
    $urd(8, '00108', 'Mc Coy 1', 'Cancelado', 0),
]);
ins(App\Models\Urdido\UrdJuliosOrden::class, [
    ['Id' => 1, 'Folio' => '00101', 'Julios' => 3, 'Hilos' => 640, 'Obs' => ''],
    ['Id' => 2, 'Folio' => '00102', 'Julios' => 2, 'Hilos' => 520, 'Obs' => ''],
    ['Id' => 3, 'Folio' => '00103', 'Julios' => 3, 'Hilos' => 640, 'Obs' => 'Revisar tensión'],
    ['Id' => 4, 'Folio' => '00103', 'Julios' => 1, 'Hilos' => 600, 'Obs' => ''],
]);
ins(App\Models\Urdido\UrdConsumoHilo::class, [
    ['Id' => 1, 'Folio' => '00101', 'FolioConsumo' => 'CH00039', 'ItemId' => 'H-A12', 'ConfigId' => 'A12', 'InventSizeId' => 'ENTERO', 'InventColorId' => 'BCO',
        'InventLocationId' => 'A-MP', 'InventBatchId' => 'LP-777', 'WMSLocationId' => 'MP-09', 'InventSerialId' => 'S-0999', 'InventQty' => 400, 'Conos' => 24,
        'Status' => 'Programado', 'NumeroEmpleado' => '1001', 'NombreEmpl' => 'Usuario Prueba', 'FechaRegistro' => $hoy],
]);
$eng = fn (int $id, string $folio, string $maq, string $status, int $prio, array $extra = []) => array_merge([
    'Id' => $id, 'Folio' => $folio, 'NoTelarId' => '201', 'RizoPie' => 'Rizo', 'Cuenta' => '3040', 'Calibre' => 12.5, 'FechaReq' => $manana,
    'Fibra' => 'A12', 'InventSizeId' => '3040-12.5/1', 'Metros' => 12000, 'Kilos' => 850, 'SalonTejidoId' => 'JACQUARD', 'MaquinaUrd' => 'Mc Coy 1',
    'BomUrd' => 'URD 3040-A12', 'FechaProg' => $hoy, 'Status' => $status, 'Nucleo' => 'Jacquard', 'NoTelas' => 2, 'AnchoBalonas' => 150,
    'MetrajeTelas' => 6000, 'Cuentados' => 3000, 'MaquinaEng' => $maq, 'BomEng' => 'ENG 3040-A12', 'Obs' => '', 'BomFormula' => 'TE-PD-ENF-100',
    'TipoAtado' => 'Normal', 'CveEmpl' => '1001', 'NomEmpl' => 'Usuario Prueba', 'LoteProveedor' => 'LP-777', 'Prioridad' => $prio,
], $extra);
ins(App\Models\Engomado\EngProgramaEngomado::class, [
    $eng(1, '00101', 'West Point 2', 'En Proceso', 1),
    $eng(2, '00102', 'West Point 2', 'Programado', 2, ['NoTelarId' => '205', 'RizoPie' => 'Pie', 'Cuenta' => '2020', 'Calibre' => 10, 'Fibra' => 'P10']),
    $eng(3, '00103', 'West Point 3', 'Programado', 1, ['NoTelarId' => '202,203', 'Metros' => 9500, 'MaquinaUrd' => 'Mc Coy 2', 'Observaciones' => 'Urgente']),
    $eng(4, '00104', 'West Point 3', 'Parcial', 2, ['MaquinaUrd' => 'Mc Coy 2']),
    $eng(5, '00105', 'West Point 2', 'Programado', 3, ['MaquinaUrd' => 'Mc Coy 3', 'NoTelarId' => '206']),
    $eng(6, '00107', 'West Point 3', 'Finalizado', 0, ['MaquinaUrd' => 'Mc Coy 3']),
]);
ins(App\Models\Urdido\UrdCatJulios::class, [
    ['Id' => 1, 'NoJulio' => '11', 'Tara' => 120.5, 'Departamento' => 'Urdido'],
    ['Id' => 2, 'NoJulio' => '21', 'Tara' => 210, 'Departamento' => 'Engomado'],
]);
