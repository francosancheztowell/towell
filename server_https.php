<?php
/**
 * Servidor HTTPS simple para desarrollo Laravel
 * Ejecutar con: php server_https.php
 */

// Verificar si OpenSSL está disponible
if (!extension_loaded('openssl')) {
    die("Error: Extensión OpenSSL no está disponible. Instala OpenSSL o usa XAMPP con SSL habilitado.\n");
}

// Configuración
$host = '0.0.0.0';
$port = 8000;
$context_options = [
    'ssl' => [
        'local_cert' => __DIR__ . '/ssl/localhost.crt',
        'local_pk' => __DIR__ . '/ssl/localhost.key',
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
    ]
];

// Verificar que los certificados existan
if (!file_exists($context_options['ssl']['local_cert'])) {
    echo "Error: Certificado SSL no encontrado en " . $context_options['ssl']['local_cert'] . "\n";
    echo "Ejecuta ssl/generate_cert.bat para generar los certificados.\n";
    exit(1);
}

if (!file_exists($context_options['ssl']['local_pk'])) {
    echo "Error: Clave privada SSL no encontrada en " . $context_options['ssl']['local_pk'] . "\n";
    echo "Ejecuta ssl/generate_cert.bat para generar los certificados.\n";
    exit(1);
}

// Crear contexto SSL
$context = stream_context_create($context_options);

echo "🚀 Iniciando servidor HTTPS Laravel...\n";
echo "📡 URL: https://{$host}:{$port}\n";
echo "🔒 Certificado SSL: " . $context_options['ssl']['local_cert'] . "\n";
echo "🔑 Clave privada: " . $context_options['ssl']['local_pk'] . "\n";
echo "⚠️  NOTA: Tu navegador mostrará una advertencia de seguridad. Acepta el certificado para continuar.\n";
echo "📝 Para instalar el certificado: Doble click en ssl/localhost.crt y sigue las instrucciones.\n";
echo "🛑 Presiona Ctrl+C para detener el servidor.\n\n";

// Iniciar servidor
$server = stream_socket_server("ssl://{$host}:{$port}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

if (!$server) {
    die("Error al crear servidor SSL: $errstr ($errno)\n");
}

echo "✅ Servidor HTTPS iniciado correctamente en https://{$host}:{$port}\n\n";

// Procesar conexiones
while (true) {
    $client = stream_socket_accept($server);

    if ($client) {
        $request = fread($client, 1024);

        // Parsear la solicitud HTTP
        $lines = explode("\n", $request);
        $request_line = $lines[0];

        // Responder con redirección a HTTPS o contenido
        if (strpos($request_line, 'GET') === 0) {
            $response = "HTTP/1.1 200 OK\r\n";
            $response .= "Content-Type: text/html; charset=UTF-8\r\n";
            $response .= "Connection: close\r\n\r\n";
            $response .= "<html><head><title>Laravel HTTPS Server</title></head><body>";
            $response .= "<h1>🔒 Servidor HTTPS Laravel Funcionando</h1>";
            $response .= "<p>El servidor HTTPS está funcionando correctamente.</p>";
            $response .= "<p>Para usar Laravel con HTTPS, configura tu aplicación para usar este servidor.</p>";
            $response .= "<p><strong>URL:</strong> https://localhost:{$port}</p>";
            $response .= "<p><strong>Estado:</strong> ✅ SSL Activo</p>";
            $response .= "</body></html>";

            fwrite($client, $response);
        }

        fclose($client);
    }
}

fclose($server);
?>
