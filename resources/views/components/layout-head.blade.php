@props(['title' => 'TOWEL S.A DE C.V', 'simple' => false])

<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">

@if(!$simple)
    <!-- Head minimo para reducir solicitudes redundantes -->
    <link rel="icon" href="{{ url('/favicon.ico') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
@else
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
@endif

<title>@yield('title', $title)</title>
