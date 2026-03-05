{{-- ============================================================
     modulo-produccion-urdido.blade.php (índice)
     Vista principal de producción de urdido. Compuesta por
     partials ubicados en produccion/.
     ============================================================ --}}

@extends('layouts.app')

@section('page-title', 'Producción de Urdido')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
            onclick="finalizar()"
            title="Finalizar"
            icon="fa-check-circle"
            iconColor="text-white"
            hoverBg="hover:bg-blue-600"
            text="Finalizar"
            bg="bg-blue-500"
        />
    </div>
@endsection

@php
    // Checkbox Fin visible para todos los usuarios (sin validación de permiso registrar)
    $hasFinalizarPermission = true;
@endphp

@section('content')

    {{-- Información de la orden: Folio, Cuenta, Metros, etc. --}}
    @include('modulos.urdido.produccion._header-orden')

    {{-- Tabla principal de registros de producción --}}
    @include('modulos.urdido.produccion._tabla-registros')

    {{-- Modal para gestionar oficiales --}}
    @include('modulos.urdido.produccion._modal-oficial')

    {{-- Selección de fecha (inline en tabla, punto de extensión) --}}
    @include('modulos.urdido.produccion._modal-fecha')

    {{-- JavaScript: cálculos, AJAX, validaciones, finalización --}}
    @include('modulos.urdido.produccion._scripts')

@endsection
