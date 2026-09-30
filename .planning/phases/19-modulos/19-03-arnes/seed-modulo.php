<?php

// Datos mínimos de Atadores (19-03): tablero con todos los estatus, un atado Jacquard en proceso
// con su checklist y una barra Karl Mayer en proceso con un atado anterior para la devolución.
use Illuminate\Support\Facades\DB;

function ins(string $tabla, array $filas): void
{
    $db = DB::connection('sqlite');
    [$s, $n] = str_contains($tabla, '.') ? ['dbo', explode('.', $tabla, 2)[1]] : ['main', $tabla];
    $cols = array_column($db->select("PRAGMA $s.table_info(\"$n\")"), 'name');
    foreach ($filas as $f) {
        $f = array_intersect_key($f, array_flip($cols));
        $db->table($tabla)->insert($f);
        $db->table($s === 'dbo' ? $n : 'dbo.'.$n)->insert($f);
    }
}
$db = DB::connection('sqlite');
foreach (['SYSUsuario', 'dbo.SYSUsuario'] as $t) {
    $db->table($t)->where('idusuario', 1)->update(['puesto' => 'Supervisor', 'area' => 'Tejido']);
}
foreach (['main', 'dbo'] as $esq) {
    $db->statement('CREATE TABLE IF NOT EXISTS '.$esq.'."TejHistorialInventarioTelares" ("Id" INTEGER PRIMARY KEY AUTOINCREMENT, "NoTelarId" TEXT, "Status" TEXT, "Tipo" TEXT, "Cuenta" TEXT, "Calibre" TEXT, "Turno" TEXT, "Fibra" TEXT, "Metros" REAL, "NoJulio" TEXT, "NoProduccion" TEXT, "TipoAtado" TEXT, "Localidad" TEXT, "LoteProveedor" TEXT, "NoProveedor" TEXT, "FechaAtado" TEXT, "FechaRequerimiento" TEXT, "HoraParo" TEXT)');
}
$db->statement('CREATE TABLE IF NOT EXISTS INFORMATION_SCHEMA."COLUMNS" ("TABLE_NAME" TEXT, "COLUMN_NAME" TEXT, "CHARACTER_MAXIMUM_LENGTH" INTEGER)');
$db->table('INFORMATION_SCHEMA.COLUMNS')->insert(['TABLE_NAME' => 'AtaMontadoTelas', 'COLUMN_NAME' => 'FolioParo', 'CHARACTER_MAXIMUM_LENGTH' => 50]);
$db->statement('CREATE TABLE IF NOT EXISTS "WMSLocation" ("wMSLocationId" TEXT, "InventLocationId" TEXT, "dataAreaId" TEXT)');
foreach (['KM1', 'KM2', 'A-01', 'A-02'] as $ubi) {
    $db->table('WMSLocation')->insert(['wMSLocationId' => $ubi, 'InventLocationId' => 'A-JUL/TELA', 'dataAreaId' => 'PRO']);
}
$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));

ins('AtaMaquinas', [['MaquinaId' => 'Atadora STAUBLI'], ['MaquinaId' => 'Atadora KNOTTER'], ['MaquinaId' => 'Pinza']]);
ins('AtaActividades', [
    ['Id' => 1, 'ActividadId' => 'Tendido y Atado', 'Porcentaje' => 30],
    ['Id' => 2, 'ActividadId' => 'Peinado', 'Porcentaje' => 20],
    ['Id' => 3, 'ActividadId' => 'Pasado de hilos', 'Porcentaje' => 25],
    ['Id' => 4, 'ActividadId' => 'Tensión', 'Porcentaje' => 15],
    ['Id' => 5, 'ActividadId' => 'Limpieza', 'Porcentaje' => 10],
]);
ins('AtaComentarios', [
    ['Nota1' => 'Revisar julio 1/2 antes de atar', 'Nota2' => 'Avisar al supervisor'],
    ['Nota1' => "Peine d'Ana <sucio>", 'Nota2' => null],
]);
ins('ReqTelares', array_map(fn ($t) => ['NoTelarId' => (string) $t], [201, 202, 203, 300, 401, 402]));

$estatus = [null, 'En Proceso', 'Terminado', 'Calificado', 'Autorizado', null, 'En Proceso', null];
$inv = [];
$ata = [];
foreach ($estatus as $i => $e) {
    $n = $i + 1;
    $inv[] = ['id' => $n, 'fecha' => $ayer, 'turno' => 1 + $i % 3, 'no_telar' => (string) (200 + $n), 'tipo' => $i % 2 ? 'Pie' : 'Rizo',
        'no_julio' => "J-10$n", 'no_orden' => "500$n", 'metros' => 1200 + $n * 10, 'cuenta' => '2139', 'calibre' => 12.5, 'hilo' => 'FIL. 370 VOLUMINIZADO',
        'localidad' => 'KM1', 'tipo_atado' => 'Normal', 'LoteProveedor' => "L$n", 'NoProveedor' => "P$n", 'horaParo' => $n === 6 ? null : '08:1'.$n, 'status' => $e ?? 'Activo'];
    if ($e) {
        $ata[] = ['Estatus' => $e, 'Fecha' => $ayer, 'Turno' => '1', 'NoJulio' => "J-10$n", 'NoProduccion' => "500$n", 'Tipo' => $i % 2 ? 'Pie' : 'Rizo',
            'NoTelarId' => (string) (200 + $n), 'Metros' => 1200, 'HrInicio' => '08:30', 'HoraParo' => '08:1'.$n, 'LoteProveedor' => "L$n", 'NoProveedor' => "P$n",
            'MergaKg' => $e === 'En Proceso' ? null : 1.5, 'Calidad' => in_array($e, ['Calificado', 'Autorizado']) ? 9 : null,
            'Limpieza' => in_array($e, ['Calificado', 'Autorizado']) ? 8 : null, 'Obs' => $n === 2 ? "Observación con 'comillas' y <b>" : null];
    }
}
// Barra Karl Mayer 401: atado anterior autorizado + el actual en proceso.
$inv[] = ['id' => 20, 'fecha' => $hoy, 'turno' => 2, 'no_telar' => '401', 'tipo' => '3', 'no_julio' => 'K12', 'no_julio2' => 'K38', 'no_julio3' => 'K34', 'no_julio4' => 'K14',
    'no_orden' => '4072', 'no_orden2' => '4072', 'no_orden3' => '4072', 'no_orden4' => '4072', 'cuenta' => '2139', 'calibre' => 75, 'hilo' => 'FIL. 370 VOLUMINIZADO',
    'metros' => 5000, 'horaParo' => '10:18', 'status' => 'En Proceso'];
$ata[] = ['Estatus' => 'Autorizado', 'Fecha' => date('Y-m-d', strtotime('-3 day')), 'Turno' => '1', 'NoJulio' => '00667-63', 'NoProduccion' => '00667', 'Tipo' => '3', 'NoTelarId' => '401'];
$ata[] = ['Estatus' => 'En Proceso', 'Fecha' => $hoy, 'Turno' => '2', 'NoJulio' => 'K12', 'NoProduccion' => '4072', 'Tipo' => '3', 'NoTelarId' => '401',
    'no_julio2' => 'K38', 'no_julio3' => 'K34', 'no_julio4' => 'K14', 'HrInicio' => '10:30', 'HoraParo' => '10:18'];
ins('tej_inventario_telares', $inv);
ins('AtaMontadoTelas', $ata);
ins('TejHistorialInventarioTelares', [['NoTelarId' => '401', 'Tipo' => '3', 'NoJulio' => '00667-63', 'NoProduccion' => '00667', 'Cuenta' => '2139', 'Calibre' => '75', 'Fibra' => 'FIL. 370 VOLUMINIZADO', 'Metros' => 4800, 'Status' => 'Completado', 'FechaAtado' => date('Y-m-d H:i:s', strtotime('-3 day'))]]);

// Checklist del Jacquard en proceso (J-102 / 5002): la mitad marcada.
foreach (['Atadora STAUBLI' => 1, 'Atadora KNOTTER' => 0, 'Pinza' => 0] as $maq => $estado) {
    ins('AtaMontadoMaquinas', [['NoJulio' => 'J-102', 'NoProduccion' => '5002', 'MaquinaId' => $maq, 'Estado' => $estado]]);
}
foreach ([1 => 'Tendido y Atado', 2 => 'Peinado', 3 => 'Pasado de hilos', 4 => 'Tensión', 5 => 'Limpieza'] as $id => $act) {
    ins('AtaMontadoActividades', [['NoJulio' => 'J-102', 'NoProduccion' => '5002', 'ActividadId' => $act, 'Porcentaje' => 20, 'Estado' => $id <= 2 ? 1 : 0,
        'CveEmpl' => $id <= 2 ? '1001' : null, 'NomEmpl' => $id <= 2 ? 'Usuario Prueba' : null, 'Turno' => '1']]);
}
