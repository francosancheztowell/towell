<?php

// Datos mínimos de los catálogos de Planeación (19-06b) para que las pantallas tengan filas.
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
ins(App\Models\Planeacion\ReqTelares::class, [
    ['Id' => 1, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'Nombre' => 'JAC 201', 'Grupo' => 'A', 'VelocidadSTD' => 180],
    ['Id' => 2, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '305', 'Nombre' => 'SMI 305', 'Grupo' => 'B', 'VelocidadSTD' => 220],
]);
ins(App\Models\Planeacion\ReqEficienciaStd::class, [
    ['Id' => 1, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FibraId' => 'ALGODON', 'Eficiencia' => 0.85, 'Densidad' => 'Normal'],
    ['Id' => 2, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '305', 'FibraId' => 'POLIESTER', 'Eficiencia' => 0.78, 'Densidad' => 'Alta'],
]);
ins(App\Models\Planeacion\ReqVelocidadStd::class, [
    ['Id' => 1, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FibraId' => 'ALGODON', 'Velocidad' => 180, 'Densidad' => 'Normal'],
    ['Id' => 2, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '305', 'FibraId' => 'POLIESTER', 'Velocidad' => 220, 'Densidad' => 'Alta'],
]);
ins(App\Models\Planeacion\ReqAplicaciones::class, [
    ['Id' => 1, 'AplicacionId' => 'BOR', 'Nombre' => 'Bordado', 'Factor' => 1.5],
    ['Id' => 2, 'AplicacionId' => 'EST', 'Nombre' => 'Estampado', 'Factor' => 2],
]);
ins(App\Models\Planeacion\ReqMatrizHilos::class, [
    ['Id' => 1, 'Hilo' => 'H-100', 'Calibre' => 12, 'Calibre2' => 12.5, 'CalibreAX' => '12/1', 'Fibra' => 'ALGODON', 'CodColor' => 'BL', 'NombreColor' => 'Blanco', 'N1' => 1, 'N2' => 2],
]);
ins(App\Models\Planeacion\Catalogos\CatMatrizCalibres::class, [
    ['Id' => 1, 'Tipo' => 'Pie', 'Calibre' => 12, 'FibraId' => 'ALGODON', 'Cuenta' => '3040', 'ItemId' => 'IT-1', 'ConfigId' => 'C1', 'InventSizeId' => 'S1', 'InventColorId' => 'BL'],
]);
ins(App\Models\Planeacion\Catalogos\ReqPesosRollosTejido::class, [
    ['Id' => 1, 'ItemId' => 'TOA-001', 'ItemName' => 'Toalla baño', 'InventSizeId' => 'GR', 'PesoRollo' => 25.5, 'FechaCreacion' => $hoy, 'HoraCreacion' => '08:00', 'UsuarioCrea' => 'Usuario Prueba'],
]);
ins(App\Models\Planeacion\ReqCalendarioTab::class, [
    ['CalendarioId' => 'Calendario Tej1', 'Nombre' => 'Tejido 3 turnos'],
    ['CalendarioId' => 'Calendario Tej2', 'Nombre' => 'Tejido 2 turnos'],
]);
$lineas = [];
for ($d = 0; $d < 3; $d++) {
    $f = date('Y-m-d', strtotime("+$d day"));
    $sig = date('Y-m-d', strtotime('+'.($d + 1).' day'));
    $lineas[] = ['Id' => 3 * $d + 1, 'CalendarioId' => 'Calendario Tej1', 'FechaInicio' => "$f 06:30:00", 'FechaFin' => "$f 14:29:00", 'HorasTurno' => 8, 'Turno' => 1];
    $lineas[] = ['Id' => 3 * $d + 2, 'CalendarioId' => 'Calendario Tej1', 'FechaInicio' => "$f 14:30:00", 'FechaFin' => "$f 22:29:00", 'HorasTurno' => 8, 'Turno' => 2];
    $lineas[] = ['Id' => 3 * $d + 3, 'CalendarioId' => 'Calendario Tej1', 'FechaInicio' => "$f 22:30:00", 'FechaFin' => "$sig 06:29:00", 'HorasTurno' => 8, 'Turno' => 3];
}
ins(App\Models\Planeacion\ReqCalendarioLine::class, $lineas);
