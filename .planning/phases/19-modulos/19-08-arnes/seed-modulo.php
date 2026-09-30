<?php

// Datos de Mantenimiento para ver nuevo paro, Solicitudes, finalizar, reportes y catálogos.
use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlite');
// El usuario de prueba es de Urdido: nuevo paro le preselecciona su área.
foreach (['SYSUsuario', 'dbo.SYSUsuario'] as $t) {
    $db->table($t)->where('idusuario', 1)->update(['area' => 'Urdido']);
}
// "Solicitudes" y "Reportes" son los nombres que usan los botones del navbar del módulo.
foreach (['Solicitudes' => 1100, 'Reportes' => 1101] as $nombre => $id) {
    foreach (['SYSRoles', 'dbo.SYSRoles'] as $t) {
        $db->table($t)->insert(['idrol' => $id, 'orden' => (string) $id, 'modulo' => $nombre, 'acceso' => 1, 'crear' => 1, 'modificar' => 1, 'eliminar' => 1, 'reigstrar' => 1, 'Nivel' => 1]);
    }
    foreach (['SYSUsuariosRoles', 'dbo.SYSUsuariosRoles'] as $t) {
        $db->table($t)->insert(['idusuario' => 1, 'idrol' => $id, 'acceso' => 1, 'crear' => 1, 'modificar' => 1, 'eliminar' => 1, 'registrar' => 1]);
    }
}
$hoy = date('Y-m-d');
ins(App\Models\Sistema\SysDepartamento::class, array_map(fn ($d) => ['Depto' => $d], ['Calidad', 'Engomado', 'Tejedores', 'Urdido']));
ins(App\Models\Mantenimiento\CatTipoFalla::class, [['TipoFallaId' => 'ELECTRICO'], ['TipoFallaId' => 'MECANICO']]);
ins(App\Models\Mantenimiento\CatParosFallas::class, [
    ['Id' => 1, 'TipoFallaId' => 'MECANICO', 'Departamento' => 'Urdido', 'Falla' => 'Rotura de hilo', 'Descripcion' => 'Hilo roto en fileta', 'Abreviado' => 'RH', 'Seccion' => 'Fileta'],
    ['Id' => 2, 'TipoFallaId' => 'MECANICO', 'Departamento' => 'Urdido', 'Falla' => 'Freno', 'Descripcion' => 'Freno de julio', 'Abreviado' => 'FR', 'Seccion' => 'Cabezal'],
    ['Id' => 3, 'TipoFallaId' => 'ELECTRICO', 'Departamento' => 'Urdido', 'Falla' => 'Sensor', 'Descripcion' => 'Sensor de paro', 'Abreviado' => 'SE', 'Seccion' => 'Tablero'],
    ['Id' => 4, 'TipoFallaId' => 'MECANICO', 'Departamento' => 'Engomado', 'Falla' => 'Rodillo', 'Descripcion' => 'Rodillo exprimidor', 'Abreviado' => 'RO', 'Seccion' => 'Tina'],
]);
ins(App\Models\Mantenimiento\ManFallasParos::class, [
    ['Id' => 1, 'Folio' => 'PF00041', 'Estatus' => 'Activo', 'Fecha' => $hoy, 'Hora' => '07:40:00', 'Depto' => 'Urdido', 'MaquinaId' => 'Mc Coy 1', 'TipoFallaId' => 'ELECTRICO', 'Falla' => 'Sensor', 'Descripcion' => 'Sensor de paro', 'CveEmpl' => '1001', 'NomEmpl' => 'Usuario Prueba', 'Turno' => 1, 'OrdenTrabajo' => 'U00102', 'Enviado' => 1],
    ['Id' => 2, 'Folio' => 'PF00040', 'Estatus' => 'Terminado', 'Fecha' => $hoy, 'Hora' => '06:55:00', 'Depto' => 'Urdido', 'MaquinaId' => 'Mc Coy 1', 'TipoFallaId' => 'MECANICO', 'Falla' => 'Freno', 'Descripcion' => 'Freno de julio', 'CveEmpl' => '1002', 'NomEmpl' => 'Otro Operador', 'Turno' => 1, 'HoraFin' => '07:20:00', 'FechaFin' => $hoy, 'NomAtendio' => 'Mecánico Uno', 'Calidad' => 4, 'Enviado' => 1],
]);
ins(App\Models\Mantenimiento\ManOperadoresMantenimiento::class, [
    ['Id' => 1, 'CveEmpl' => '3001', 'NomEmpl' => 'Mecánico Uno', 'Turno' => 1, 'Depto' => 'Mantenimiento', 'Telefono' => '4441112233'],
    ['Id' => 2, 'CveEmpl' => '3002', 'NomEmpl' => 'Eléctrico Dos', 'Turno' => 2, 'Depto' => 'Mantenimiento', 'Telefono' => null],
]);
ins(App\Models\Sistema\SSYSFoliosSecuencia::class, [['Id' => 1, 'modulo' => 'ParosFallas', 'prefijo' => 'PF', 'consecutivo' => 41]]);
@unlink(HARNESS_DIR.'/is.sqlite');
$is = new PDO('sqlite:'.HARNESS_DIR.'/is.sqlite');
$is->exec('CREATE TABLE COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
foreach (['Id', 'modulo', 'prefijo', 'consecutivo'] as $c) {
    $is->exec("INSERT INTO COLUMNS VALUES ('dbo', 'SSYSFoliosSecuencias', '$c')");
}
