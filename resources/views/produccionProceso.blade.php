@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title
        title="Producción en Proceso"
    />
@endsection

{{-- UX-01: los avisos de sesión ("No tienes acceso a este módulo.") los pinta x-ui.flash desde
     el layout; aquí no se repiten. --}}
@section('content')
    @if(collect($modulos)->reject(fn ($m) => ($m['nombre'] ?? null) === 'Configuración')->isEmpty())
        <x-empty.empty-state
            title="No tienes módulos asignados"
            message="Pide a Sistemas que te dé acceso a los módulos que necesitas."
            icon="fa-lock" />
    @else
        <x-layout.module-grid :modulos="$modulos" columns="xl:grid-cols-4" :filterConfig="true" />
    @endif
@endsection
