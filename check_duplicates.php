<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sql = "
WITH CTE AS (
    SELECT 
        id, telar, tipo, Fecha, Reserva, no_julio, no_orden,
        ROW_NUMBER() OVER (
            PARTITION BY LTRIM(RTRIM(telar)), LOWER(LTRIM(RTRIM(tipo))), Fecha 
            ORDER BY 
                CASE WHEN Reserva = 1 THEN 1 ELSE 0 END DESC,
                CASE WHEN no_julio <> '0' AND no_julio <> '' THEN 1 ELSE 0 END DESC,
                id DESC
        ) as row_num
    FROM TejNotificaTejedor
)
SELECT * FROM CTE WHERE row_num > 1;
";

$duplicates = DB::connection('sqlsrv')->select($sql);

echo "Registros duplicados encontrados: " . count($duplicates) . "\n";
if (count($duplicates) > 0) {
    echo "Muestra de registros a ELIMINAR:\n";
    echo json_encode(array_slice($duplicates, 0, 5), JSON_PRETTY_PRINT);
}
