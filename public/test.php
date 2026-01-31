<?php
$serverName = "192.168.2.24,1433"; // Tu IP
$connectionOptions = array(
    "Database" => "ProdTowel",
    "Uid" => "laragon",
    "PWD" => "laragon", // Tu contraseña
    "TrustServerCertificate" => true // Crucial para driver 18
);

// Intento de conexión pura sin Laravel
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn) {
    echo "<h1>¡ÉXITO! Conexión establecida correctamente.</h1>";
    echo "El usuario y contraseña funcionan. El problema es el caché de Laravel.";
} else {
    echo "<h1>FALLO: El servidor sigue rechazando al usuario.</h1>";
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
    echo "</pre>";
}
?>
