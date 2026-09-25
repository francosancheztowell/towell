@props([
    'title' => 'Producción Towell',
    'description' => 'Sistema de gestión de producción y planeación empresarial Towell.',
    'simple' => false
])

@php
    // UX-02: <title> propio por página. Orden: @section('title') › texto de @section('page-title')
    // (lo que el navbar muestra) › módulo de SYSRoles de la ruta › default. Las secciones ya
    // vienen escapadas por Blade: se decodifican para no escapar dos veces.
    $textoPlano = fn (string $html): string => trim((string) preg_replace('/\s+/u', ' ',
        html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $tituloPagina = $textoPlano($__env->yieldContent('title'));
    if ($tituloPagina === '') {
        $tituloPagina = $textoPlano($__env->yieldContent('page-title'));
    }
    if ($tituloPagina === '' && ! $simple && auth()->check()) {
        $tituloPagina = (string) rescue(fn () => moduleNameForRoute(), '', false);
    }
    $tituloCompleto = $tituloPagina !== '' && $tituloPagina !== $title ? $tituloPagina.' · Towell' : $title;

    // UX-04: pinch-zoom habilitado. Solo las pantallas andón (se quedan abiertas todo el turno
    // en una TV o tablet fija) lo bloquean: por nombre de ruta aquí, o con @section('viewport-fijo').
    $viewportFijo = request()->routeIs('crudo.index') || $__env->hasSection('viewport-fijo');
    $viewport = 'width=device-width, initial-scale=1.0'
        .($viewportFijo ? ', minimum-scale=1.0, maximum-scale=1.0, user-scalable=no' : '');
@endphp

<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="@yield('meta_description', $description)">
{{-- Monitoreo (fase 11, contrato §4): señales para el cliente de telemetría. --}}
{{-- Sin nombre de ruta, la plantilla de la URI (tejido/{id}), nunca IDs (contrato 11 §3). --}}
<meta name="towell-ruta" content="{{ request()->route()?->getName() ?? request()->route()?->uri() ?? '' }}">
<meta name="towell-version" content="{{ \App\Services\Monitoreo\Monitoreo::versionFront() }}">
@if(\App\Services\Monitoreo\Monitoreo::activo() && auth()->check())
<meta name="towell-telemetria" content="1">
@endif

@if(!$simple)
    @if(config('app.pwa_enabled', true) && !config('app.service_worker_cleanup', false))
        <link rel="manifest" href="{{ asset('manifest.json') }}">
    @endif
    <meta name="viewport" content="{{ $viewport }}, viewport-fit=cover">
    <meta name="theme-color" content="#0f4c81">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/icon-180x180.png') }}">
    <meta name="apple-mobile-web-app-title" content="Producción Towell">
    <meta name="application-name" content="Producción Towell">
    <meta name="msapplication-TileColor" content="#0f4c81">
@else
    <meta name="viewport" content="{{ $viewport }}">
@endif

<title>{{ $tituloCompleto }}</title>
