<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $cols = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'EngProduccionFormulacion' ORDER BY ORDINAL_POSITION");
    foreach($cols as $c) {
        echo $c->COLUMN_NAME . ' | ' . $c->DATA_TYPE . ' | ' . $c->IS_NULLABLE . PHP_EOL;
    }
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
