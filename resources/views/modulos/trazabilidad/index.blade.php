@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="Trazabilidad" />
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button type="button" id="btn-redbooth"
            @class([
                'items-center gap-2 px-2 py-2 text-md font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors',
                'flex' => $hayFlog,
                'hidden' => ! $hayFlog,
            ])>
            <i class="fas fa-comments"></i>
            Redbooth
        </button>
        <button type="button" id="btn-restablecer"
                class="flex items-center gap-2 px-2 py-2 text-md font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
            <i class="fas fa-rotate-left"></i>
            Restablecer
        </button>
    </div>
@endsection

@push('styles')
    @vite('resources/css/trazabilidad/index.css')
@endpush

@section('content')
    @php
        $trazabilidadConfig = [
            'rutas' => [
                'index' => route('trazabilidad.index'),
                'redbooth' => route('trazabilidad.redbooth'),
            ],
            'mesesDisponibles' => $mesesDisponibles,
            'hayFiltro' => $hayFiltro,
            'hayFlog' => $hayFlog,
            'produccionCargando' => (bool) ($produccionCargando ?? false),
            'flogsCargando' => (bool) ($flogsCargando ?? false),
            'conteosIniciales' => [
                'articulo' => $opcionesArticulo->count(),
                'tamano' => $opcionesTamano->count(),
                'color' => $opcionesColor->count(),
            ],
        ];
    @endphp

    <div class="w-full min-h-full px-1.5 md:px-2 py-3 trazabilidad-page">
        @include('modulos.trazabilidad._filters')

        <div id="resultado">
            @include('modulos.trazabilidad._resultado')
        </div>

        @include('modulos.trazabilidad._modal_rollos_maquina')
        @include('modulos.trazabilidad._modal_flog_imagen')
        @include('modulos.programa-tejido.modal.redbooth')
    </div>

    <script type="application/json" id="trazabilidad-config">@json($trazabilidadConfig)</script>
@endsection

@push('scripts')
    @vite('resources/js/trazabilidad/index.js')
@endpush
