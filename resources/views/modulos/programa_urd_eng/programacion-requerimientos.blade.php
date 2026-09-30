@extends('layouts.app')

@section('page-title', 'Programación de Requerimientos')

@php
    // 19-05: config de la página para resources/js/modulos/programa-urd-eng/programacion-requerimientos/index.ts
    $configPagina = [
        'rutas' => [
            'resumen' => route('programa.urd.eng.programacion.resumen.semanas'),
            'actualizarTelar' => route('programa.urd.eng.actualizar.telar'),
            'hilos' => route('programa.urd.eng.hilos'),
            'tamanos' => route('programa.urd.eng.tamanos'),
            'creacionOrdenes' => route('programa.urd.eng.creacion.ordenes'),
        ],
        'telares' => $telaresSeleccionados ?? [],
        'opcionesUrdido' => $opcionesUrdido ?? [],
    ];
@endphp

@section('navbar-right')
<div class="flex items-center gap-3">
    <!-- Botón único -->
    <button id="btnSiguiente" type="button" title="Siguiente" aria-label="Siguiente: crear órdenes" disabled
        class="px-6 py-2.5 bg-blue-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 flex items-center gap-2 group disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none">
        <i class="fa-solid fa-arrow-right w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" aria-hidden="true"></i>
    </button>
</div>
@endsection

@section('content')


<div class="w-full" id="pagina-programacion-requerimientos" data-pagina='@json($configPagina)'>

    {{-- =================== Tabla de requerimientos =================== --}}
    <div class="bg-white overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table id="tablaRequerimientos" class="w-full">
                <thead>
                    <tr class="bg-blue-500">
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-20">Telar</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-28">Fecha</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-24">Tamaño</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-20">Cuenta</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-20">Calibre</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-24">Hilo</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-28">Urdido</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-20">Tipo</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-28">Tipo Atado</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-24">Metros</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-24">Kilos</th>
                    </tr>
                </thead>
                <tbody id="tbodyRequerimientos" class="bg-white">
                    {{-- filas dinámicas --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- =================== Resumen por semana =================== --}}
    <div class="bg-white overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tablaResumen" class="w-full">
                <thead id="theadResumen">
                    <tr class="bg-slate-100">
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Telar</th>
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Cuenta</th>
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Hilo</th>
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Calibre</th>
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Modelo</th>
                        <th class="px-2 py-1.5 text-center text-[12px] font-semibold text-blue-700 bg-blue-50/50" colspan="5">Metros</th>
                        <th class="px-2 py-1.5 text-right text-[12px] font-semibold text-blue-700 bg-blue-50" rowspan="2">Total (mts)</th>
                        <th class="px-2 py-1.5 text-center text-[12px] font-semibold text-green-700 bg-green-50/50" colspan="5">Kilos</th>
                        <th class="px-2 py-1.5 text-right text-[12px] font-semibold text-green-700 bg-green-50" rowspan="2">Total (kg)</th>
                    </tr>
                    <tr>
                        @for ($semana = 0; $semana < 5; $semana++)
                            <th class="px-2 py-1 text-right text-[12px] font-semibold text-blue-600 bg-blue-50 semana-header" data-semana="{{ $semana }}">Semana {{ $semana + 1 }}<span class="hidden" data-rango-semana><br><span class="text-gray-500 font-normal text-caption" data-rango-texto></span></span></th>
                        @endfor
                        @for ($semana = 0; $semana < 5; $semana++)
                            <th class="px-2 py-1 text-right text-[12px] font-semibold text-green-600 bg-green-50 semana-header" data-semana="{{ $semana }}">Semana {{ $semana + 1 }}<span class="hidden" data-rango-semana><br><span class="text-gray-500 font-normal text-caption" data-rango-texto></span></span></th>
                        @endfor
                    </tr>
                </thead>
                <tbody id="tbodyResumen" class="bg-white">
                    {{-- filas dinámicas --}}
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- =================== Plantillas (las llena index.ts; sin datos en el HTML) =================== --}}
<template id="tpl-fila-requerimiento">
    <tr class=" hover:bg-gray-50">
        <td class="px-2 py-3 w-20">
            <input type="text" class="w-full px-2 py-1.5 text-md bg-transparent border-0" data-field="telar" aria-label="Telar" disabled>
        </td>
        <td class="px-2 py-3 w-28">
            <input type="date" class="w-full px-2 py-1.5 text-md bg-transparent border-0" data-field="fecha_req" aria-label="Fecha requerida" disabled>
        </td>
        <td class="px-2 py-3 w-24">
            <div class="relative tamano-wrapper">
                <input type="text" class="w-full px-2 py-1.5 text-md border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" data-field="tamano" aria-label="Tamaño" required placeholder="Buscar..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false">
                <div class="tamano-dropdown hidden fixed z-[9999] bg-white border border-gray-300 rounded shadow-lg overflow-y-auto text-sm" style="max-height:200px;" role="listbox"></div>
            </div>
        </td>
        <td class="px-2 py-3 w-20">
            <input type="text" class="w-full px-2 py-1.5 text-md border border-gray-300 rounded-md bg-gray-100 focus:outline-none" data-field="cuenta" aria-label="Cuenta" readonly>
        </td>
        <td class="px-2 py-3 w-20">
            <input type="text" class="w-full px-2 py-1.5 text-md border border-gray-300 rounded-md bg-gray-100 focus:outline-none" data-field="calibre" aria-label="Calibre" readonly>
        </td>
        <td class="px-2 py-3 w-24">
            <select class="w-full px-2 py-1.5 text-md border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" data-field="hilo" aria-label="Hilo" required>
                <option value="">Seleccione...</option>
            </select>
        </td>
        <td class="px-2 py-3 w-28">
            <select class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" data-field="urdido" aria-label="Urdido" required></select>
        </td>
        <td class="px-2 py-3 w-20">
            <select class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" data-field="tipo" aria-label="Tipo" required>
                <option value="Rizo">Rizo</option>
                <option value="Pie">Pie</option>
            </select>
        </td>
        <td class="px-2 py-3 w-28">
            <select class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" data-field="tipo_atado" aria-label="Tipo de atado" required>
                <option value="Normal">Normal</option>
                <option value="Especial">Especial</option>
            </select>
        </td>
        <td class="px-2 py-3 w-24">
            <input type="text" placeholder="Metros (requerido)" inputmode="decimal"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-right"
                   data-field="metros" aria-label="Metros" required>
        </td>
        <td class="px-2 py-3 w-24">
            <input type="text" placeholder="Kilos (requerido)" inputmode="decimal"
                   class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-md text-right focus:outline-none focus:ring-2 focus:ring-blue-500"
                   data-field="kilos" aria-label="Kilos" required>
        </td>
    </tr>
</template>

<template id="tpl-requerimientos-vacio">
    <tr>
        <td colspan="11" class="px-4 py-8 text-center text-gray-500">
            <i class="fa-solid fa-circle-info text-gray-400 mb-2" aria-hidden="true"></i>
            <p>No hay telares seleccionados.</p>
        </td>
    </tr>
</template>

<template id="tpl-requerimientos-error">
    <tr>
        <td colspan="11" class="px-4 py-8 text-center text-red-500" role="alert">
            <i class="fa-solid fa-triangle-exclamation text-red-400 mb-2" aria-hidden="true"></i>
            <p class="font-semibold">Error de validación</p>
            <p class="text-sm mt-2" data-slot="mensaje"></p>
            <p class="text-md mt-2 text-gray-500">Todos los telares deben tener el mismo tipo y calibre.</p>
        </td>
    </tr>
</template>

<template id="tpl-resumen-mensaje">
    <tr>
        <td colspan="17" class="px-2 py-4 text-center text-gray-500 text-caption">
            <i class="fa-solid fa-circle-info text-gray-400 mb-1" aria-hidden="true"></i>
            <p class="whitespace-pre-line" data-slot="mensaje"></p>
            <button type="button" data-accion="reintentar-resumen" hidden
                class="mt-2 min-h-touch px-4 py-2 rounded-lg bg-blue-500 text-white text-xs font-semibold hover:bg-blue-600">
                <i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Reintentar
            </button>
        </td>
    </tr>
</template>

<template id="tpl-resumen-cargando">
    <tr>
        <td colspan="17" class="px-2 py-4 text-center text-gray-500 text-caption" role="status">
            <div class="flex items-center justify-center gap-2">
                <div class="animate-spin rounded-full h-4 w-4 border-2 border-gray-300 border-t-blue-500" aria-hidden="true"></div>
                <span>Cargando datos...</span>
            </div>
        </td>
    </tr>
</template>

@endsection

@push('scripts')
    @vite('resources/js/modulos/programa-urd-eng/programacion-requerimientos/index.ts')
@endpush
