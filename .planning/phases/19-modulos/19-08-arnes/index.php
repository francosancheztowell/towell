<?php

// Igual que 19-01-arnes/index.php, más INFORMATION_SCHEMA.COLUMNS para los folios
// (SSYSFoliosSecuencia::getColumnMap() pregunta los nombres de columna).
require __DIR__.'/../19-01-arnes/boot.php';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && is_file(REPO.'/public'.$uri)) {
    return false;
}
$_SERVER['SCRIPT_FILENAME'] = REPO.'/public/index.php';
$app = require REPO.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, fn () => harness_config());
$app->booted(function ($app) {
    harness_attach();
    $db = Illuminate\Support\Facades\DB::connection('sqlsrv');
    if (! in_array('INFORMATION_SCHEMA', array_column($db->select('PRAGMA database_list'), 'name'), true)) {
        $db->statement("ATTACH DATABASE '".HARNESS_DIR."/is.sqlite' AS INFORMATION_SCHEMA");
    }
    Illuminate\Support\Facades\Route::middleware('web')->get('/__login/{id}', function ($id) {
        Illuminate\Support\Facades\Auth::loginUsingId((int) $id);

        return redirect(request('to', '/produccionProceso'));
    });
});
$app->handleRequest(Illuminate\Http\Request::capture());
