@props([
    'title' => 'Produccion Towell',
    'description' => 'Sistema de gestion de produccion y planeacion empresarial Towell.',
    'simple' => false
])

<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="@yield('meta_description', $description)">

@if(!$simple)
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#0f4c81">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Produccion Towell">
    <meta name="application-name" content="Produccion Towell">
    <meta name="msapplication-TileColor" content="#0f4c81">
@else
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5.0, user-scalable=yes">
@endif

<title>@yield('title', $title)</title>
