{{-- ============================================================
     modulo-produccion-urdido.blade.php (índice)
     Vista principal de producción de urdido. Compuesta por
     partials ubicados en produccion/.
     ============================================================ --}}

@extends('layouts.app')

@section('page-title', 'Producción de Urdido')

@section('navbar-right')
    @if($canEdit ?? false)
    <div class="flex items-center gap-2">
        @if($ordenIncorrecta ?? false)
            <span class="px-3 py-2 rounded bg-red-100 text-red-700 text-sm font-semibold">
                <i class="fas fa-ban mr-1"></i>Cuenta/Calibre incorrecta — un supervisor debe liberarla
            </span>
        @else
            <x-navbar.button-create
                onclick="finalizar()"
                title="Finalizar"
                icon="fa-check-circle"
                iconColor="text-white"
                hoverBg="hover:bg-blue-600"
                text="Finalizar"
                bg="bg-blue-500"
            />
        @endif
    </div>
    @endif
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
