@extends('layouts.app')
@section('navbar-right')
<div class="flex items-center gap-2">
    {{-- Abre el <dialog> de filtros: Área y "terminados" cambian lo que se pide a la API, por eso
         no son filtros de columna. --}}
    <flux:button id="btn-open-filters" icon="funnel" class="min-h-touch">Filtrar</flux:button>
    <x-navbar.button-create
    type="button"
    id="btn-nuevo-paro"
    title="Nuevo Paro"
    module="Solicitudes"
    />

    <flux:button id="btn-terminar-paro" variant="primary" color="zinc" icon="stop-circle" class="min-h-touch">Terminar Paro</flux:button>

</div>
@endsection
@section('page-title', 'Reporte de Fallos y Paros')
@section('content')
@php
    $usuarioSesion = Auth::user();
    $configPagina = [
        'rutas' => [
            'paros' => route('api.mantenimiento.paros.index'),
            'departamentos' => route('api.mantenimiento.departamentos.catalogo-filtros'),
            'nuevoParo' => route('mantenimiento.nuevo-paro'),
            'finalizar' => route('mantenimiento.finalizar-paro'),
        ],
        // Área = SYSUsuario.area; debe coincidir con ManFallasParos.Depto para el filtro por defecto.
        'usuario' => [
            'nombre' => (string) ($usuarioSesion->nombre ?? ''),
            'numeroEmpleado' => (string) ($usuarioSesion->numero_empleado ?? ''),
            'area' => trim((string) ($usuarioSesion->area ?? '')),
        ],
    ];
@endphp
<div id="pagina-solicitudes" class="pantalla-completa p-2" data-pagina='@json($configPagina)'>
            {{-- flux:table a pantalla completa + .tabla-cebra / .tabla-seleccionable (app.css). Filas: <template> de abajo. --}}
            <div class="tabla-pantalla bg-white rounded-lg shadow-sm overflow-hidden">
                <flux:table class="tabla-cebra tabla-seleccionable">
                    <flux:table.columns sticky class="bg-white">
                        {{-- Columna de selección: el radio da foco, teclado y estado accesible a la fila --}}
                        <flux:table.column class="w-12"><span class="sr-only">Seleccionar paro</span></flux:table.column>
                        @foreach (['Folio', 'Status', 'Fecha', 'Hora', 'Área', 'Máquina', 'Tipo Falla', 'Falla', 'Usuario'] as $titulo)
                            <flux:table.column align="center">{{ $titulo }}</flux:table.column>
                        @endforeach
                    </flux:table.columns>
                    <flux:table.rows id="tbody-paros" aria-busy="true">
                        <flux:table.row>
                            <flux:table.cell colspan="10" class="text-center">
                                <span role="status">
                                    <i class="fa-solid fa-spinner fa-spin mr-2" aria-hidden="true"></i>Cargando datos...
                                </span>
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>
</div>

{{-- Filas que pinta resources/js/modulos/mantenimiento/solicitudes (textContent, sin innerHTML).
     Selección: el TS pone aria-selected y marca el radio; el color sale de .tabla-seleccionable.
     Status: el TS clona el flux:badge de <template data-badge-paro> (activo / terminado). --}}
<template id="tpl-fila-paro">
    <flux:table.row class="row-paro" aria-selected="false">
        <flux:table.cell align="center">
            <input type="radio" name="paro-seleccionado" class="size-5 align-middle cursor-pointer accent-blue-700">
        </flux:table.cell>
        <flux:table.cell variant="strong" align="center" data-col="Folio"></flux:table.cell>
        <flux:table.cell align="center" data-col="Estatus"></flux:table.cell>
        @foreach (['Fecha', 'Hora', 'Depto', 'MaquinaId', 'TipoFallaId', 'Falla', 'NomEmpl'] as $col)
            <flux:table.cell align="center" data-col="{{ $col }}"></flux:table.cell>
        @endforeach
    </flux:table.row>
</template>
<template data-badge-paro="activo"><flux:badge size="sm" inset="top bottom" color="blue"></flux:badge></template>
<template data-badge-paro="terminado"><flux:badge size="sm" inset="top bottom" color="zinc"></flux:badge></template>
<template id="tpl-fila-mensaje">
    <flux:table.row>
        <flux:table.cell colspan="10" class="text-center">
            <span role="status"><i class="fa-solid fa-spinner fa-spin mr-2 hidden" aria-hidden="true"></i></span>
        </flux:table.cell>
    </flux:table.row>
</template>

{{-- Modal Filtros (estilo BPM). <dialog> nativo: aporta role="dialog", aria-modal,
     foco atrapado, cierre con Escape y devolución del foco al botón que lo abrió. --}}
<style>
    /* El velo y el centrado van en CSS propio, no en utilidades de Tailwind:
       `backdrop:bg-black/50` no es una variante válida en Tailwind v4 (no se genera
       en el bundle), y el preflight aplica `*{margin:0}`, que le gana al
       `dialog{margin:auto}` del navegador y pegaría el diálogo a la esquina.
       Así la pantalla no depende de que alguien haya corrido `npm run build`. */
    #modal-filters {
        margin: auto;
    }

    #modal-filters::backdrop {
        background-color: rgb(0 0 0 / 0.5);
    }
</style>
<dialog id="modal-filters" aria-labelledby="modal-filters-title" class="w-full max-w-2xl bg-transparent p-0">
    <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4">
        <div class="flex items-center justify-between mb-4">
            <h2 id="modal-filters-title" class="text-lg font-semibold text-gray-800">
                <i class="fa-solid fa-filter text-purple-600 mr-2"></i>Filtros
            </h2>
            <button type="button" id="btn-close-modal-filters" aria-label="Cerrar filtros" class="text-slate-500 hover:text-slate-700 text-3xl leading-none">&times;</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                <label for="filter-depto" class="block text-xs text-gray-600 mb-2">
                    <i class="fa-solid fa-door-open mr-1"></i>Área
                </label>
                <select id="filter-depto" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                <label for="filter-status" class="block text-xs text-gray-600 mb-2">
                    <i class="fa-solid fa-circle-info mr-1"></i>Status
                </label>
                <select id="filter-status" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                <label for="filter-maquina" class="block text-xs text-gray-600 mb-2">
                    <i class="fa-solid fa-gear mr-1"></i>Máquina
                </label>
                <select id="filter-maquina" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos</option>
                </select>
            </div>
        </div>
        <div class="mb-4 space-y-3">
            <label class="flex items-center gap-2 p-4 rounded-lg border-2 border-gray-300 bg-gray-50 cursor-pointer hover:bg-gray-100 transition" title="En sistema los paros cerrados tienen estatus Terminado">
                <input type="checkbox" id="filter-incluir-terminados" class="w-4 h-4 text-purple-600 rounded focus:ring-2 focus:ring-purple-500">
                <span class="text-sm font-medium text-gray-700">
                    <i class="fa-solid fa-flag-checkered mr-1"></i>Incluir paros terminados (últimos 30 días)
                </span>
            </label>
            <label class="flex items-center gap-2 p-4 rounded-lg border-2 border-gray-300 bg-gray-50 cursor-pointer hover:bg-gray-100 transition">
                <input type="checkbox" id="filter-solo-mis" class="w-4 h-4 text-purple-600 rounded focus:ring-2 focus:ring-purple-500">
                <span class="text-sm font-medium text-gray-700">
                    <i class="fa-solid fa-user mr-1"></i>Solo mis solicitudes
                </span>
            </label>
        </div>
        <div class="flex gap-2">
            <button type="button" id="btn-clear-filter" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 bg-blue-500 text-white hover:bg-blue-600 transition text-sm">
                <i class="fa-solid fa-eraser mr-1"></i>Limpiar
            </button>
        </div>
    </div>
</dialog>

@endsection

@push('scripts')
    @vite('resources/js/modulos/mantenimiento/solicitudes/index.ts')
@endpush
