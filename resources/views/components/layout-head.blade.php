@props(['title' => 'TOWEL S.A DE C.V', 'simple' => false])

<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">

@if(!$simple)
    <!-- Head minimo para reducir solicitudes redundantes -->
    <link rel="icon" href="{{ url('/favicon.ico') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.08, minimum-scale=1.08, maximum-scale=1.08, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0f4c81">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Towell">
    <meta name="application-name" content="Towell">
    <meta name="msapplication-TileColor" content="#0f4c81">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/icon-180x180.png') }}">
@else
    <meta name="viewport" content="width=device-width, initial-scale=1.08, minimum-scale=1.08, maximum-scale=1.08, user-scalable=no">
@endif

<title>@yield('title', $title)</title>
