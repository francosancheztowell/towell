@extends('layouts.app')

@section('page-title', 'Catálogo de Calendarios')

@section('navbar-right')
    <x-buttons.catalog-actions route="calendarios" :showFilters="true" />
@endsection

@php
    // Rutas resueltas en PHP; las que llevan llave usan el marcador __ID__ (19-00-RECETA §2).
    $id = '__ID__';
    $configCalendarios = [
        'rutas' => [
            'json' => route('planeacion.calendarios.json', absolute: false),
            'detalle' => route('planeacion.calendarios.detalle', ['calendario' => $id], absolute: false),
            'crear' => route('planeacion.calendarios.store', absolute: false),
            'masivo' => route('planeacion.calendarios.update.masivo', ['calendario' => $id], absolute: false),
            'eliminar' => route('planeacion.calendarios.destroy', ['calendario' => $id], absolute: false),
            'crearLinea' => route('planeacion.calendarios.lineas.store', absolute: false),
            'linea' => route('planeacion.calendarios.lineas.update', ['linea' => $id], absolute: false),
            'rango' => route('planeacion.calendarios.lineas.destroy.rango', ['calendario' => $id], absolute: false),
            'excel' => route('planeacion.calendarios.excel.upload', absolute: false),
            'recalcular' => route('planeacion.calendarios.recalcular.programas', ['calendario' => $id], absolute: false),
        ],
    ];
    $fila = 'text-center transition cursor-pointer border-b border-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500';
@endphp

@section('content')
    <div class="w-full space-y-6" id="pagina-calendarios" data-pagina='@json($configCalendarios)'>

        <!-- Tabla 1: ReqCalendarioTab -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="relative max-h-[300px] overflow-y-auto">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-blue-500 text-white">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-center font-semibold">No Calendario</th>
                            <th scope="col" class="px-4 py-2 text-center font-semibold">Nombre</th>
                        </tr>
                    </thead>
                    <tbody id="calendario-tab-body" class="bg-white text-black" data-tabla="tab">
                        @foreach ($calendarioTab as $item)
                            <tr data-fila-tab data-id="{{ $item->CalendarioId }}" data-nombre="{{ $item->Nombre }}" tabindex="0" aria-selected="false"
                                class="{{ $fila }} hover:bg-blue-50 aria-selected:bg-blue-500 aria-selected:text-white aria-selected:hover:bg-blue-500">
                                <td class="px-4 py-1 font-medium">{{ $item->CalendarioId }}</td>
                                <td class="px-4 py-1">{{ $item->Nombre }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla 2: ReqCalendarioLine -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="relative h-[600px] overflow-y-auto" id="contenedor-scroll-line">
                <table class="min-w-full text-sm" id="tabla-calendario-line">
                    <thead class="sticky top-0 z-10 bg-blue-500 text-white">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-center font-semibold w-[15%]">No Calendario</th>
                            <th scope="col" class="px-4 py-2 text-center font-semibold w-[25%]">Fecha Inicio</th>
                            <th scope="col" class="px-4 py-2 text-center font-semibold w-[25%]">Fecha Fin</th>
                            <th scope="col" class="px-4 py-2 text-center font-semibold w-[15%]">Horas</th>
                            <th scope="col" class="px-4 py-2 text-center font-semibold w-[20%]">Turno</th>
                        </tr>
                    </thead>
                    <tbody id="calendario-line-body" class="bg-white text-black" data-tabla="line">
                        @foreach ($calendarioLine as $item)
                            @php
                                $valoresLinea = [
                                    'Id' => $item->Id,
                                    'CalendarioId' => $item->CalendarioId,
                                    'FechaInicio' => $item->FechaInicio?->format('Y-m-d\TH:i'),
                                    'FechaFin' => $item->FechaFin?->format('Y-m-d\TH:i'),
                                    'HorasTurno' => $item->HorasTurno,
                                    'Turno' => $item->Turno,
                                ];
                            @endphp
                            <tr data-fila-linea data-id="{{ $item->Id }}" data-calendario="{{ $item->CalendarioId }}" data-valores='@json($valoresLinea)'
                                tabindex="0" aria-selected="false"
                                class="{{ $fila }} hover:bg-green-50 aria-selected:bg-green-500 aria-selected:text-white aria-selected:hover:bg-green-500">
                                <td class="px-4 py-1 font-medium">{{ $item->CalendarioId }}</td>
                                <td class="px-4 py-1">{{ $item->FechaInicio?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-1">{{ $item->FechaFin?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-1 font-semibold">{{ $item->HorasTurno }}</td>
                                <td class="px-4 py-1 font-semibold">{{ $item->Turno }}</td>
                            </tr>
                        @endforeach
                        <tr data-lineas-vacio hidden>
                            <td colspan="5" class="text-center py-4 text-gray-500">No hay líneas para este calendario</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('catalagos.calendarios.modal-calendario')
    @include('catalagos.calendarios.modal-linea')
    @include('catalagos.calendarios.modal-eliminar-rango')
    @include('catalagos.calendarios.modal-filtrar')
    @include('catalagos.calendarios.modal-excel')
@endsection

@push('scripts')
    @vite('resources/js/modulos/catalogos-planeacion/calendarios/index.ts')
@endpush
