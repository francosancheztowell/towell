<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir orden urdido {{ $ordenId ?? '' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <iframe
        id="pdf-frame"
        src="{{ $pdfUrl }}"
        title="PDF orden urdido"
    ></iframe>
    <script>
        (function() {
            var iframe = document.getElementById('pdf-frame');
            function abrirImpresion() {
                try {
                    window.print();
                } catch (e) {
                    console.warn('Print:', e);
                }
            }
            iframe.addEventListener('load', function() {
                setTimeout(abrirImpresion, 600);
            });
        })();
    </script>
</body>
</html>
