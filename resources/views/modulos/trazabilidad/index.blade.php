@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="Trazabilidad" />
@endsection

@section('navbar-right')
    {{-- El contenedor se oculta y no el botón: flux:button trae inline-flex. --}}
    <div data-redbooth @class(['hidden' => ! $hayFlog])>
        <flux:button id="btn-redbooth" variant="primary" color="red" icon="chat-bubble-left-right" class="min-h-touch">
            Redbooth
        </flux:button>
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

    <div class="trazabilidad-page mx-auto w-full max-w-[1600px] space-y-4 px-2 py-3 md:px-4">
        <livewire:trazabilidad.index />

        <section id="resultado-detalle" class="hidden space-y-4" aria-labelledby="detalle-titulo">
            <header class="flex flex-wrap items-center gap-3">
                <flux:button data-volver-resumen variant="primary" color="blue" icon="arrow-left" class="min-h-touch">
                    Volver al resumen
                </flux:button>
                <flux:heading id="detalle-titulo" size="xl" level="2" tabindex="-1" data-detalle-titulo class="outline-none"></flux:heading>
            </header>

            <div data-detalle-cargando class="hidden space-y-3" role="status">
                <span class="sr-only">Cargando detalle…</span>
                <flux:skeleton class="h-14 w-full rounded-xl" />
                <flux:skeleton class="h-64 w-full rounded-xl" />
            </div>

            <div data-detalle-error class="hidden" role="alert">
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.heading data-detalle-error-texto></flux:callout.heading>
                    <x-slot name="actions">
                        <flux:button size="sm" icon="arrow-path" data-detalle-reintentar class="min-h-touch">Reintentar</flux:button>
                    </x-slot>
                </flux:callout>
            </div>

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
