@extends('layouts.app', ['ocultarBotones' => true])

@php
    $titulosInventario = [
        'jacquard' => 'Inventario Jacquard',
        'itema' => 'Inventario Itema',
        'karl-mayer' => 'Inventario Karl Mayer',
    ];
    $nombresSalon = [
        'jacquard' => 'Jacquard',
        'itema' => 'Itema',
        'karl-mayer' => 'Karl Mayer',
    ];
@endphp
@section('page-title', $titulosInventario[$tipoInventario] ?? 'Inventario de Telas')

@push('styles')
    @vite('resources/css/tejido/inventario-telas.css')
@endpush

@push('scripts')
    @vite('resources/js/tejido/inventario-telas.ts')
    @vite('resources/js/modulos/tejido/inventario-telas/index.ts')
@endpush

@section('navbar-right')
    @if(count($telares ?? []) > 0)
    <div class="relative">
        <!-- Dropdown de Telares -->
        <button
            type="button"
            id="btnDropdownTelares"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="menuDropdownTelares"
            class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-none"
        >
            <span class="font-medium">Telares</span>
            <i class="fas fa-chevron-down text-sm transition-transform duration-200 ease-out rotate-0" id="iconDropdown" aria-hidden="true"></i>
        </button>

        <!-- Menú Dropdown -->
        <div
            id="menuDropdownTelares"
            class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg border border-gray-200 max-h-96 overflow-y-auto z-50 shadow-none"
        >
            <div class="py-2">
                <button
                    type="button"
                    data-accion="ir-telar"
                    data-telar=""
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                >
                    <span class="font-medium">Todos los telares</span>
                </button>
                <div class="border-t border-gray-200 my-1"></div>
                @foreach(collect($telares)->sortBy(fn($v) => (float) $v)->values() as $t)
                    <button
                        type="button"
                        data-accion="ir-telar"
                        data-telar="{{ $t }}"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                    >
                        Telar <span class="font-semibold">{{ $t }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
    @endif
@endsection

@section('content')
<div class="inventario-telas-page">
    @if(count($telares) > 0)
        <div class="inventario-telas-list">
            @foreach ($telares as $telar)
                @php
                    $telarData = $datosTelaresCompletos[$telar]['telarData'] ?? (object) [
                        'Telar' => $telar,
                        'en_proceso' => false
                    ];
                    $ordenSig = $datosTelaresCompletos[$telar]['ordenSig'] ?? null;
                @endphp

                <div id="telar-{{ $telar }}">
                    <x-telares.telar-section
                        :telar="$telarData"
                        :ordenSig="$ordenSig"
                        :tipo="$tipoInventario"
                        :showRequerimiento="true"
                        :showSiguienteOrden="true"
                    />
                </div>
            @endforeach
        </div>
    @else
        <!-- Estado vacío -->
        <div class="flex flex-col items-center justify-center py-12 px-4">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-gray-100 mb-4">
                    <i class="fas fa-industry text-4xl text-gray-400" aria-hidden="true"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    No hay telares {{ $nombresSalon[$tipoInventario] ?? '' }} en proceso
                </h3>
                <p class="text-gray-500 mb-4">
                    Actualmente no hay telares {{ $nombresSalon[$tipoInventario] ?? '' }} con producción activa.
                </p>
                <p class="text-sm text-gray-400">
                    Los telares aparecerán aquí cuando tengan órdenes con <span class="font-semibold">EnProceso = 1</span>
                </p>
            </div>
        </div>
    @endif
</div>

@endsection
