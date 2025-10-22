<?php

/**
 * Script para limpiar prefijos de telares en la base de datos
 * Convierte "JAC 201" → "201", "SMITH 202" → "202", etc.
 */

// Configuración de la base de datos (ajusta según tu configuración)
$host = 'localhost';
$database = 'towell';
$username = 'sa';
$password = '123456';

try {
    // Conectar a la base de datos SQL Server
    $pdo = new PDO(
        "sqlsrv:Server={$host};Database={$database}",
        $username,
        $password
    );

    echo "Conectado a la base de datos...\n";

    // Función para extraer solo el número del telar
    function extraerNumeroTelar($nombreTelar) {
        $telar = trim($nombreTelar);

        // Remover prefijos comunes de salón
        $prefijos = ['JAC', 'JACQUARD', 'ITEM', 'ITEMA', 'KARL', 'MAYER', 'SMITH'];

        foreach ($prefijos as $prefijo) {
            if (strtoupper(substr($telar, 0, strlen($prefijo))) === strtoupper($prefijo)) {
                $telar = trim(substr($telar, strlen($prefijo)));
                break;
            }
        }

        // Si queda solo números, devolverlos
        if (preg_match('/^\d+$/', $telar)) {
            return $telar;
        }

        // Si contiene números, extraer solo los números
        if (preg_match('/\d+/', $telar, $matches)) {
            return $matches[0];
        }

        return $telar; // Devolver tal como está si no se puede extraer número
    }

    // Obtener todos los registros que tienen prefijos
    $stmt = $pdo->prepare("SELECT Id, NoTelarId FROM req_eficiencia_std WHERE NoTelarId LIKE '% %'");
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Encontrados " . count($registros) . " registros con prefijos...\n";

    $actualizados = 0;
    $errores = 0;

    foreach ($registros as $registro) {
        $id = $registro['Id'];
        $telarOriginal = $registro['NoTelarId'];
        $telarLimpio = extraerNumeroTelar($telarOriginal);

        if ($telarLimpio !== $telarOriginal) {
            try {
                // Actualizar el registro
                $updateStmt = $pdo->prepare("UPDATE req_eficiencia_std SET NoTelarId = ? WHERE Id = ?");
                $updateStmt->execute([$telarLimpio, $id]);

                echo "ID {$id}: '{$telarOriginal}' → '{$telarLimpio}'\n";
                $actualizados++;
            } catch (Exception $e) {
                echo "Error actualizando ID {$id}: " . $e->getMessage() . "\n";
                $errores++;
            }
        }
    }

    echo "\n=== RESUMEN ===\n";
    echo "Registros procesados: " . count($registros) . "\n";
    echo "Registros actualizados: {$actualizados}\n";
    echo "Errores: {$errores}\n";
    echo "Script completado exitosamente.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
