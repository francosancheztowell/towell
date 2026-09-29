<?php
// Datos mínimos de Urdido/Engomado para que las pantallas tengan filas.
use Illuminate\Support\Facades\DB;
function ins(string $modelo, array $filas): void {
    $m = new $modelo; $t = $m->getTable(); $db = DB::connection('sqlite');
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
ins(App\Models\Urdido\UrdProgramaUrdido::class, [
  ['Id'=>1,'Folio'=>'U00101','NoTelarId'=>'201','Cuenta'=>'3040','Calibre'=>12.5,'FechaReq'=>$hoy,'Fibra'=>'ALGODON','Metros'=>12000,'Kilos'=>850,'SalonTejidoId'=>'JACQUARD','MaquinaId'=>'Mc Coy 1','Status'=>'Finalizado','TipoAtado'=>'Normal','CveEmpl'=>'1001','NomEmpl'=>'Usuario Prueba','LoteProveedor'=>'L-77','Prioridad'=>1,'FechaProg'=>$hoy,'CreatedAt'=>$hoy.' 08:00:00'],
  ['Id'=>2,'Folio'=>'U00102','NoTelarId'=>'201','Cuenta'=>'3040','Calibre'=>12.5,'FechaReq'=>$hoy,'Fibra'=>'ALGODON','Metros'=>12000,'Kilos'=>850,'SalonTejidoId'=>'JACQUARD','MaquinaId'=>'Mc Coy 1','Status'=>'En Proceso','TipoAtado'=>'Normal','CveEmpl'=>'1001','NomEmpl'=>'Usuario Prueba','LoteProveedor'=>'L-77','Prioridad'=>1,'FechaProg'=>$hoy,'CreatedAt'=>$hoy.' 08:00:00'],
]);
ins(App\Models\Urdido\UrdJuliosOrden::class, [
  ['Id'=>1,'Folio'=>'U00101','Julios'=>3,'Hilos'=>640,'Obs'=>''],
  ['Id'=>2,'Folio'=>'U00102','Julios'=>3,'Hilos'=>640,'Obs'=>''],
]);
ins(App\Models\Engomado\EngProgramaEngomado::class, [
  ['Id'=>1,'Folio'=>'U00101','NoTelarId'=>'201','Cuenta'=>'3040','Calibre'=>12.5,'FechaReq'=>$hoy,'Fibra'=>'ALGODON','Metros'=>12000,'Kilos'=>850,'SalonTejidoId'=>'JACQUARD','MaquinaUrd'=>'Mc Coy 1','MaquinaEng'=>'West Point 2','Status'=>'En Proceso','Nucleo'=>'Jacquard','NoTelas'=>2,'AnchoBalonas'=>150,'MetrajeTelas'=>6000,'Cuentados'=>3000,'TipoAtado'=>'Normal','CveEmpl'=>'1001','NomEmpl'=>'Usuario Prueba','LoteProveedor'=>'L-77','Prioridad'=>1,'FechaProg'=>$hoy,'BomFormula'=>'F-100'],
]);
ins(App\Models\Urdido\UrdCatJulios::class, [
  ['Id'=>1,'NoJulio'=>'11','Tara'=>120.5,'Departamento'=>'Urdido'],
  ['Id'=>2,'NoJulio'=>'12','Tara'=>118,'Departamento'=>'Urdido'],
  ['Id'=>3,'NoJulio'=>'21','Tara'=>210,'Departamento'=>'Engomado'],
]);
ins(App\Models\Urdido\URDCatalogoMaquina::class, [
  ['Id'=>1,'MaquinaId'=>'Mc Coy 1','Nombre'=>'Mc Coy 1','Departamento'=>'Urdido'],
  ['Id'=>2,'MaquinaId'=>'West Point 2','Nombre'=>'West Point 2','Departamento'=>'Engomado'],
]);
ins(App\Models\Engomado\CatUbicaciones::class, [['Id'=>1,'Codigo'=>'A-01'],['Id'=>2,'Codigo'=>'B-07']]);
ins(App\Models\UrdEngomado\UrdEngNucleos::class, [['Id'=>1,'Salon'=>'JACQUARD','Nombre'=>'Jacquard'],['Id'=>2,'Salon'=>'SMIT','Nombre'=>'Smit']]);
ins(App\Models\Urdido\UrdActividadesBpmModel::class, [['Id'=>1,'Orden'=>1,'Actividad'=>'Limpieza de fileta','Maquina'=>'MC'],['Id'=>2,'Orden'=>2,'Actividad'=>'Revisión de tensores','Maquina'=>'KM']]);
ins(App\Models\Engomado\EngActividadesBpmModel::class, [['Id'=>1,'Orden'=>1,'Actividad'=>'Limpieza de tina'],['Id'=>2,'Orden'=>2,'Actividad'=>'Revisión de rodillos']]);
ins(App\Models\Urdido\UrdBpmModel::class, [['Id'=>1,'Folio'=>'UB0001','Status'=>'Creado','Fecha'=>$hoy.' 07:00:00','CveEmplRec'=>'1001','NombreEmplRec'=>'Usuario Prueba','TurnoRecibe'=>1,'CveEmplEnt'=>'1002','NombreEmplEnt'=>'Otro','TurnoEntrega'=>3,'MaquinaId'=>'Mc Coy 1']]);
ins(App\Models\Engomado\EngBpmModel::class, [['Id'=>1,'Folio'=>'EB0001','Status'=>'Creado','Fecha'=>$hoy.' 07:00:00','CveEmplRec'=>'1001','NombreEmplRec'=>'Usuario Prueba','TurnoRecibe'=>1,'CveEmplEnt'=>'1002','NombreEmplEnt'=>'Otro','TurnoEntrega'=>3,'MaquinaId'=>'West Point 2']]);
ins(App\Models\Engomado\EngProduccionFormulacionModel::class, [['Id'=>1,'Folio'=>'U00101','Formula'=>'F-100','Status'=>'Creado','fecha'=>$hoy,'Hora'=>'08:00','MaquinaId'=>'West Point 2','Cuenta'=>'3040','Calibre'=>12.5,'Tipo'=>'ALGODON','NomEmpl'=>'Usuario Prueba','CveEmpl'=>'1001','Kilos'=>50,'Litros'=>200,'Solidos'=>10,'Viscocidad'=>7,'Olla'=>'1','Turno'=>1]]);
ins(App\Models\Engomado\CatDefectosUrdEng::class, [
  ['Id'=>7,'Clave'=>'RHC','Defecto'=>'Rotura de hilo con cuenta','Penalizacion'=>3,'Activo'=>1],
  ['Id'=>8,'Clave'=>'MI','Defecto'=>'Mezcla de hilo','Penalizacion'=>1,'Activo'=>1],
]);
ins(App\Models\Urdido\UrdProduccionUrdido::class, [
  ['Id'=>101,'Folio'=>'U00101','NoJulio'=>'11','Fecha'=>$hoy,'Metros1'=>4000,'NomEmpl1'=>'Ana López','KgBruto'=>300,'Tara'=>120.5,'KgNeto'=>179.5,'Turno'=>1],
  ['Id'=>102,'Folio'=>'U00101','NoJulio'=>'12','Fecha'=>$hoy,'Metros1'=>4000,'NomEmpl1'=>'Beto Ruiz','KgBruto'=>310,'Tara'=>118,'KgNeto'=>192,'Turno'=>1,'ClaveDefecto'=>7],
]);
