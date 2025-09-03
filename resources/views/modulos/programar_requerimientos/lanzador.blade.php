<!DOCTYPE html>
<html>

<head>
    <title>Redireccionando...</title>
</head>

<body>
    <p>Espere un momento...</p>

    <script>
        const folio = @json($folio);
        const urlNuevaPestana = "{{ route('folio.pantalla', ':folio') }}".replace(':folio', folio);

        const mainApp = "{{ url('/produccionProceso') }}";

        // Abrir en nueva pestaña
        window.open(urlNuevaPestana, '_blank');

        // Redirigir esta página
        window.location.href = mainApp;
    </script>
</body>

</html>
