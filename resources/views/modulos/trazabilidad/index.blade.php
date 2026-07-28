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
                'redbooth' => route('trazabilidad.redbooth'),
                'detalles' => [
                    'trazabilidad' => route('trazabilidad.details.matrix'),
                    'produccion' => route('trazabilidad.details.production'),
                    'flogs' => route('trazabilidad.details.flog'),
                ],
            ],
        ];
    @endphp

    <div class="w-full min-h-full px-1.5 md:px-2 py-3 trazabilidad-page">
        <livewire:trazabilidad.index />

        <section id="resultado-detalle" class="hidden space-y-4" aria-live="polite">
            <header class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-600">Detalle</p>
                    <h2 class="text-xl font-bold text-slate-800" data-detalle-titulo></h2>
                </div>
                <button type="button" data-volver-resumen
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2 text-sm font-bold text-white hover:bg-blue-600">
                    <i class="fa-solid fa-arrow-left"></i>
                    Volver al resumen
                </button>
            </header>

            <div data-detalle-cargando class="hidden rounded-2xl border border-blue-100 bg-white px-6 py-16 text-center shadow-sm">
                <i class="fa-solid fa-circle-notch fa-spin text-2xl text-blue-500"></i>
                <p class="mt-3 text-sm font-semibold text-slate-600">Cargando detalle…</p>
            </div>
            <div data-detalle-error class="hidden rounded-2xl border border-red-200 bg-white px-6 py-12 text-center text-red-600"></div>
            <div data-detalle-contenido></div>
        </section>

        @include('modulos.trazabilidad._modal_rollos_maquina')
        @include('modulos.trazabilidad._modal_flog_imagen')
        @include('modulos.programa-tejido.modal.redbooth')
    </div>

    <script type="application/json" id="trazabilidad-config">@json($trazabilidadConfig)</script>
@endsection

@push('scripts')
    @vite('resources/js/trazabilidad/index.ts')
@endpush
