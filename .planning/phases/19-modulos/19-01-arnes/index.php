<?php
require __DIR__.'/boot.php';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && is_file(REPO.'/public'.$uri)) { return false; }
$_SERVER['SCRIPT_FILENAME'] = REPO.'/public/index.php';
$app = require REPO.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, fn () => harness_config());
$app->booted(function ($app) {
    harness_attach();
    Illuminate\Support\Facades\Route::middleware('web')->get('/__login/{id}', function ($id) {
        Illuminate\Support\Facades\Auth::loginUsingId((int) $id);
        return redirect(request('to', '/produccionProceso'));
    });
});
$app->handleRequest(Illuminate\Http\Request::capture());
