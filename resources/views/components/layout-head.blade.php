@props(['title' => 'TOWELL S.A DE C.V', 'simple' => false])

<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">

@if(!$simple)
    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#0f4c81">

    <!-- iOS: hace que al "Añadir a pantalla de inicio" abra como app -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Producción">
    <link rel="apple-touch-icon" href="{{ asset('images/fotos_usuarios/TOWELLIN.png') }}">

    <!-- Android/Chrome: Ocultar barra de navegación -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Producción">

    <!-- Prevenir zoom y mostrar en pantalla completa -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

    <!-- Preload -->
    <link rel="preload" as="image" href="{{ asset('images/fondosTowell/logo.png') }}">
    @if(file_exists(public_path('images/fotos_usuarios/TOWELLIN.png')))
        <link rel="preload" as="image" href="{{ asset('images/fotos_usuarios/TOWELLIN.png') }}">
    @endif
@else
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
@endif

<title>@yield('title', $title)</title>

