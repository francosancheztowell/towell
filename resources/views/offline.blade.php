<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin conexión</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            padding: 2rem;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 3rem;
            border-radius: 1rem;
            max-width: 400px;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        button {
            margin-top: 2rem;
            padding: 0.75rem 2rem;
            font-size: 1rem;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
        }
        button:hover {
            transform: scale(1.05);
            transition: transform 0.2s;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1> Estás sin conexión</h1>
        <p>No hay conexión a internet disponible.</p>
        <p>Por favor, verifica tu conexión e intenta de nuevo.</p>
        <button onclick="window.location.reload()">Reintentar</button>
    </div>
</body>
</html>

