@extends('layouts.app')
@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-report
    type="button"
    id="btn-open-filters"
    title="Filtros"
    icon="fa-filter"
    bg="bg-green-600"
    iconColor="text-white"
    text="Filtrar"
    class="text-white"
    module="Solicitudes"
    />
    <x-navbar.button-create
    type="button"
    id="btn-nuevo-paro"
    title="Nuevo Paro"
    module="Solicitudes"
    />

    <button type="button" id="btn-terminar-paro" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors whitespace-nowrap">
        Terminar Paro
    </button>

</div>
@endsection

@push('scripts')
    @vite('resources/js/modulos/mantenimiento/solicitudes/index.ts')
@endpush
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
<div id="pagina-solicitudes" class="w-full" data-pagina='@json($configPagina)'>
    <div class="bg-white">
        <div class="flex gap-4">
            <!-- Tabla -->
            <div class="flex-1 overflow-auto max-h-[70vh] rounded-lg border border-gray-300">
                <table class="w-full border-collapse text-sm min-w-full">
                    <thead>
                        <tr class="text-white text-center">
                            {{-- Columna de selección: el radio da foco, teclado y estado accesible a la fila --}}
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap w-12">
                                <span class="sr-only">Seleccionar paro</span>
                            </th>
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Folio</th>
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Status</th>
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Fecha</th>
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Hora</th>
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Área</th>
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Máquina</th>
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Tipo Falla</th>
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Falla</th>
                            <th scope="col" class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-paros" aria-busy="true">
                        <!-- Los datos se cargarán dinámicamente aquí -->
                        <tr>
                            <td colspan="10" class="border border-gray-300 px-2 py-2 text-center text-gray-700">
                                <span role="status">
                                    <i class="fa-solid fa-spinner fa-spin mr-2" aria-hidden="true"></i>Cargando datos...
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>


        </div>
    </div>
</div>

{{-- Filas que pinta resources/js/modulos/mantenimiento/solicitudes (textContent, sin innerHTML).
     Las clases que el JS alterna viven aquí: Tailwind escanea el Blade, no los .ts (ver HANDOFF 19-08). --}}
<template id="tpl-fila-paro"
          data-clase-seleccionada="bg-blue-700 text-white"
          data-clase-normal="hover:bg-gray-100 text-gray-900"
          data-clase-activo="bg-blue-100 text-blue-800"
          data-clase-terminado="bg-gray-100 text-gray-800">
    <tr class="row-paro cursor-pointer hover:bg-gray-100 transition-colors">
        <td class="px-2 py-2 text-center">
            <input type="radio" name="paro-seleccionado" class="h-5 w-5 align-middle cursor-pointer accent-blue-700">
        </td>
        <td data-col="Folio" class="px-2 py-2 text-gray-900 text-lg text-center"></td>
        <td class="px-2 py-2 text-lg text-center"><span data-col="Estatus" class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium"></span></td>
        <td data-col="Fecha" class="px-2 py-2 text-gray-900 text-lg text-center"></td>
        <td data-col="Hora" class="px-2 py-2 text-gray-900 text-lg text-center"></td>
        <td data-col="Depto" class="px-2 py-2 text-gray-900 text-lg text-center"></td>
        <td data-col="MaquinaId" class="px-2 py-2 text-gray-900 text-lg text-center"></td>
        <td data-col="TipoFallaId" class="px-2 py-2 text-gray-900 text-lg text-center"></td>
        <td data-col="Falla" class="px-2 py-2 text-gray-900 text-lg text-center"></td>
        <td data-col="NomEmpl" class="px-2 py-2 text-gray-900 text-lg text-center"></td>
    </tr>
</template>
<template id="tpl-fila-mensaje">
    <tr>
        <td colspan="10" class="border border-gray-300 px-2 py-2 text-center">
            <span role="status"><i class="fa-solid fa-spinner fa-spin mr-2 hidden" aria-hidden="true"></i></span>
        </td>
    </tr>
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
