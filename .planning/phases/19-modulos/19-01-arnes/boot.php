<?php
// Arnés local: Laravel con todas las conexiones sqlsrv* apuntando a sqlite en archivo.
// REPO: el árbol a servir (ARNES_REPO permite apuntar a un worktree del "antes").
// ARNES_DATOS: dónde quedan los sqlite y las capturas (fuera del repo).
define('ARNES', __DIR__);
define('REPO', getenv('ARNES_REPO') ?: dirname(__DIR__, 4));
define('HARNESS_DIR', getenv('ARNES_DATOS') ?: sys_get_temp_dir().'/towell-arnes');
if (! is_dir(HARNESS_DIR)) { mkdir(HARNESS_DIR, 0777, true); }
require REPO.'/vendor/autoload.php';

function harness_config(): void {
    $main = HARNESS_DIR.'/main.sqlite';
    foreach (['sqlsrv','sqlsrv_ti','sqlsrv_tow_pro','sqlsrv_tow_tow','sqlsrv_Reportes_Towell','sqlite'] as $c) {
        config()->set("database.connections.$c", ['driver'=>'sqlite','database'=>$main,'prefix'=>'','foreign_key_constraints'=>false]);
    }
    config()->set('database.default', 'sqlite');
    config()->set('cache.default', 'array');
    config()->set('session.driver', 'file');
    config()->set('queue.default', 'sync');
    config()->set('monitoreo.enabled', false);
    config()->set('app.debug', true);
    config()->set('debugbar.enabled', false);
}

function harness_attach(): void {
    foreach (['sqlsrv','sqlsrv_ti','sqlsrv_tow_pro','sqlsrv_tow_tow','sqlsrv_Reportes_Towell','sqlite'] as $c) {
        $db = Illuminate\Support\Facades\DB::connection($c);
        $names = array_column($db->select('PRAGMA database_list'), 'name');
        if (! in_array('dbo', $names, true)) {
            $db->statement("ATTACH DATABASE '".HARNESS_DIR."/dbo.sqlite' AS dbo");
        }
    }
}
