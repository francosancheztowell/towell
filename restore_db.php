<?php
/**
 * Script para restaurar la base de datos ProdTowel desde un archivo SQL
 */

$serverName = "192.168.2.28";
$connectionOptions = [
    "Database" => "ProdTowel",
    "Uid" => "laravel",
    "PWD" => "Francost15",
    "TrustServerCertificate" => true,
    "Encrypt" => false,
];

$sqlFile = "C:\\Users\\fsanchez\\Desktop\\ProdTowel.sql";

if (!file_exists($sqlFile)) {
    die("ERROR: Archivo no encontrado: $sqlFile\n");
}

echo "Leyendo archivo SQL...\n";
$sqlContent = file_get_contents($sqlFile);
$fileSize = filesize($sqlFile);
echo "Tamaño del archivo: " . number_format($fileSize / 1024 / 1024, 2) . " MB\n\n";

echo "Conectando a SQL Server...\n";
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    $errors = sqlsrv_errors();
    die("ERROR de conexión: " . print_r($errors, true) . "\n");
}

echo "Conexión exitosa!\n\n";

// Dividir en statements individuales (por ;)
$statements = array_filter(array_map('trim', explode(';', $sqlContent)));
$total = count($statements);
$current = 0;
$errors = [];

echo "Ejecutando $total statements...\n\n";

foreach ($statements as $statement) {
    $current++;
    
    // Saltar statements vacíos o solo comentarios
    if (empty($statement) || preg_match('/^--/', $statement) || preg_match('/^\/\*/', $statement)) {
        continue;
    }
    
    // Mostrar progreso cada 100 statements
    if ($current % 100 === 0 || $current === $total) {
        echo "Progreso: $current / $total (" . round($current / $total * 100) . "%)\n";
    }
    
    $result = sqlsrv_query($conn, $statement);
    
    if (!$result) {
        $err = sqlsrv_errors();
        $errMsg = isset($err[0]['message']) ? $err[0]['message'] : 'Unknown error';
        
        // Ignorar errores de "already exists" o "cannot find"
        if (stripos($errMsg, 'already exists') !== false || 
            stripos($errMsg, 'cannot find') !== false ||
            stripos($errMsg, 'does not exist') !== false) {
            continue;
        }
        
        $errors[] = [
            'statement' => substr($statement, 0, 100) . '...',
            'error' => $errMsg
        ];
        
        // Mostrar primeros errores
        if (count($errors) <= 5) {
            echo "  [!] Error en statement $current: " . substr($errMsg, 0, 100) . "\n";
        }
    }
    
    if ($result !== false && $result !== null) {
        @sqlsrv_free_stmt($result);
    }
}

sqlsrv_close($conn);

echo "\n======================================\n";
echo "RESTAURACIÓN COMPLETADA\n";
echo "======================================\n";
echo "Statements ejecutados: $current\n";
echo "Errores encontrados: " . count($errors) . "\n";

if (count($errors) > 0) {
    echo "\nPrimeros errores:\n";
    foreach (array_slice($errors, 0, 10) as $e) {
        echo "- " . substr($e['error'], 0, 150) . "\n";
    }
}
