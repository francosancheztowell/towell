<?php
use Illuminate\Support\Facades\DB;
$db = DB::connection('sqlite');
$db->table('dbo.SYSUsuario')->insert(['idusuario'=>1,'numero_empleado'=>'1001','nombre'=>'Usuario Prueba','area'=>'Sistemas','contrasenia'=>bcrypt('x')]);
$db->table('SYSUsuario')->insert(['idusuario'=>1,'numero_empleado'=>'1001','nombre'=>'Usuario Prueba','area'=>'Sistemas','contrasenia'=>bcrypt('x')]);
$nombres = array_filter(array_map('trim', file(ARNES.'/modulos.txt')));
$roles = [];
for ($i = 1; $i <= 400; $i++) { $roles[$i] = 'rol '.$i; }
$i = 1000; foreach ($nombres as $n) { $roles[$i++] = $n; }
foreach ($roles as $id => $n) {
    foreach (['SYSRoles','dbo.SYSRoles'] as $t) $db->table($t)->insert(['idrol'=>$id,'orden'=>(string)$id,'modulo'=>$n,'acceso'=>1,'crear'=>1,'modificar'=>1,'eliminar'=>1,'reigstrar'=>1,'Nivel'=>1]);
    foreach (['SYSUsuariosRoles','dbo.SYSUsuariosRoles'] as $t) $db->table($t)->insert(['idusuario'=>1,'idrol'=>$id,'acceso'=>1,'crear'=>1,'modificar'=>1,'eliminar'=>1,'registrar'=>1]);
}
if (is_file(ARNES.'/seed-modulo.php')) require ARNES.'/seed-modulo.php';
