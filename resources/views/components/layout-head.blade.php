@props(['title' => 'TOWELL S.A DE C.V', 'simple' => false])

<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">

@if(!$simple)
    <!-- Favicon - Debe ir ANTES del manifest para máxima compatibilidad -->
    <link rel="icon" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}" type="image/png">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">

    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0f4c81">
    <meta name="msapplication-TileColor" content="#0f4c81">
    <meta name="msapplication-TileImage" content="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <meta name="msapplication-config" content="{{ asset('browserconfig.xml') }}">

    <!-- PWA - Mejor soporte para tablets -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Towell">
    <meta name="msapplication-starturl" content="/">
    <meta name="msapplication-tap-highlight" content="no">

    <!-- iOS: hace que al "Añadir a pantalla de inicio" abra como app -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Towell">
    <link rel="apple-touch-icon" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="apple-touch-icon" sizes="60x60" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">
    <link rel="apple-touch-icon" sizes="57x57" href="{{ asset('images/fotosTowell/TOWELLIN.png') }}">

    <!-- Android/Chrome: Ocultar barra de navegación -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Towell">


    <!-- Prevenir zoom y mostrar en pantalla completa - Optimizado para tablets y móviles -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover, shrink-to-fit=no">

    <!-- PWA - Mejor experiencia en tablets -->
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-touch-fullscreen" content="yes">

    <!-- Prefetch - Recursos que pueden necesitarse después (no críticos para carga inicial) -->
    <link rel="prefetch" as="image" href="{{ asset('images/fondosTowell/logo.png') }}">
    <link rel="prefetch" as="image" href="{{ asset('images/fondosTowell/TOWELLIN.png') }}">
    @if(file_exists(public_path('images/fotos_usuarios/TOWELLIN.png')))
        <link rel="prefetch" as="image" href="{{ asset('images/fotos_usuarios/TOWELLIN.png') }}">
    @endif
@else
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
@endif

<title>@yield('title', $title)</title>

