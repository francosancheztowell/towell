<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    DB::connection('sqlsrv')->beginTransaction();
    
    // Consulta para eliminar duplicados manteniendo el mejor registro
    $sqlDelete = "
    WITH CTE AS (
        SELECT 
            id,
            ROW_NUMBER() OVER (
                PARTITION BY LTRIM(RTRIM(telar)), LOWER(LTRIM(RTRIM(tipo))), Fecha 
                ORDER BY 
                    CASE WHEN Reserva = 1 THEN 1 ELSE 0 END DESC,
                    CASE WHEN no_julio <> '0' AND no_julio <> '' THEN 1 ELSE 0 END DESC,
                    id DESC
            ) as row_num
        FROM TejNotificaTejedor
    )
    DELETE FROM TejNotificaTejedor 
    WHERE id IN (SELECT id FROM CTE WHERE row_num > 1);
    ";
    
    $affected = DB::connection('sqlsrv')->delete($sqlDelete);
    
    DB::connection('sqlsrv')->commit();
    echo "Éxito: Se eliminaron {$affected} registros duplicados.\n";
    
} catch (\Exception $e) {
    DB::connection('sqlsrv')->rollBack();
    echo "ERROR: No se pudo realizar la limpieza: " . $e->getMessage() . "\n";
}
