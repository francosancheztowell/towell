@extends('layouts.app')

@section('page-title', 'Matriz de Calibres')

@section('navbar-right')
@php $accionesCalibres = ['ruta' => 'matriz-calibres', 'routeJs' => 'Matriz_calibres', 'excel' => null]; @endphp
{{-- Sin x-buttons.catalog-actions (sin permisos por nombre, como antes); mismo runtime de acciones. --}}
<div class="flex items-center gap-1" data-catalogo-acciones='@json($accionesCalibres)'>
    <button type="button" id="btn-agregar" data-accion-catalogo="agregar"
        class="p-2 min-h-touch text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
        title="Añadir" aria-label="Añadir">
        <i class="fas fa-plus text-lg" aria-hidden="true"></i>
    </button>
    <button type="button" id="btn-editar" data-accion-catalogo="editar" disabled
        class="p-2 min-h-touch text-blue-600 hover:text-blue-800 rounded-md transition-colors disabled:text-gray-400 disabled:cursor-not-allowed"
        title="Editar" aria-label="Editar">
        <i class="fas fa-edit text-lg" aria-hidden="true"></i>
    </button>
    <button type="button" id="btn-eliminar" data-accion-catalogo="eliminar" disabled
        class="p-2 min-h-touch text-red-600 hover:text-red-800 rounded-md transition-colors disabled:text-gray-400 disabled:cursor-not-allowed"
        title="Eliminar" aria-label="Eliminar">
        <i class="fas fa-trash text-lg" aria-hidden="true"></i>
    </button>
</div>
@endsection

@php $catalogo = \App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatalogosPlaneacionVista::matrizCalibres(); @endphp

@section('content')
<div class="w-full px-3 sm:px-4 pb-4" data-catalogo='@json($catalogo)'>
    <div class="bg-white/95 backdrop-blur rounded-xl shadow-lg border border-white/60 overflow-hidden">
        {{-- Toolbar --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-sky-50">
            <div class="flex items-center gap-3 min-w-0">
                <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-lg bg-sky-600 text-white shadow-sm">
                    <i class="fas fa-ruler-combined" aria-hidden="true"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-base sm:text-lg font-semibold text-slate-800 truncate">Matriz de Calibres</h2>
                    <p class="text-xs text-slate-500">
                        Mostrando <span id="matriz-calibres-count" class="font-semibold text-sky-700">{{ $registros->count() }}</span>
                        de <span id="matriz-calibres-total" class="font-semibold">{{ $registros->count() }}</span> registros
                    </p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:w-72">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
                    <input
                        id="matriz-calibres-search"
                        type="search"
                        placeholder="Buscar tipo, calibre, item..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                        autocomplete="off"
                    >
                </div>

                <select
                    id="matriz-calibres-tipo"
                    class="w-full sm:w-44 px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                >
                    <option value="">Todos los tipos</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo }}">{{ $tipo }}</option>
                    @endforeach
                </select>

                <button
                    type="button"
                    id="matriz-calibres-clear"
                    class="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm text-slate-600 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 transition-colors"
                    title="Limpiar filtros" aria-label="Limpiar filtros"
                >
                    <i class="fas fa-redo" aria-hidden="true"></i>
                    <span class="sm:hidden">Limpiar</span>
                </button>
            </div>
        </div>

        {{-- Tabla --}}
        <div class="overflow-auto" style="max-height: calc(100vh - 160px);">
            <table class="w-full border-collapse min-w-[900px]">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-sky-600 text-white text-xs sm:text-sm uppercase tracking-wide">
                        <th class="py-2.5 px-3 font-semibold text-center">Id</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Tipo</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Calibre</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Fibra</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Cuenta</th>
                        <th class="py-2.5 px-3 font-semibold text-center">ItemId</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Config</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Tamaño</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Color</th>
                    </tr>
                </thead>
                <tbody id="matriz-calibres-body" class="bg-white text-slate-800 text-sm" data-catalogo-filas>
                    @forelse ($registros as $item)
                        @php
                            $itemId = $item->Id;
                            $calibre = $item->Calibre === null
                                ? ''
                                : rtrim(rtrim(number_format((float) $item->Calibre, 4, '.', ''), '0'), '.');
                            $valores = $item->only(['Id', 'Tipo', 'Calibre', 'FibraId', 'Cuenta', 'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId']);
                        @endphp
                        <tr data-fila data-id="{{ $itemId }}" data-valores='@json($valores)' tabindex="0" aria-selected="false"
                            class="text-center hover:bg-sky-50 transition cursor-pointer border-b border-slate-100 aria-selected:bg-blue-500 aria-selected:text-white aria-selected:hover:bg-blue-500">
                            <td class="py-2.5 px-3 text-slate-500 font-mono text-xs">{{ $itemId }}</td>
                            <td class="py-2.5 px-3">
                                @if (filled($item->Tipo))
                                    <span class="inline-flex items-center rounded-full bg-sky-100 text-sky-800 px-2.5 py-0.5 text-xs font-semibold">{{ $item->Tipo }}</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 font-semibold tabular-nums text-slate-800">
                                {{ $calibre !== '' ? $calibre : '—' }}
                            </td>
                            <td class="py-2.5 px-3">{{ $item->FibraId ?: '—' }}</td>
                            <td class="py-2.5 px-3">{{ $item->Cuenta ?: '—' }}</td>
                            <td class="py-2.5 px-3 font-mono text-xs">{{ $item->ItemId ?: '—' }}</td>
                            <td class="py-2.5 px-3">{{ $item->ConfigId ?: '—' }}</td>
                            <td class="py-2.5 px-3">{{ $item->InventSizeId ?: '—' }}</td>
                            <td class="py-2.5 px-3">{{ $item->InventColorId ?: '—' }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>

            <div id="matriz-calibres-empty" class="{{ $registros->isEmpty() ? '' : 'hidden' }} px-6 py-16 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <i class="fas fa-inbox text-2xl" aria-hidden="true"></i>
                </div>
                <p class="text-slate-700 font-medium">No hay registros</p>
                <p class="text-sm text-slate-500 mt-1">Usa el botón + para agregar el primer calibre.</p>
            </div>
        </div>
    </div>
</div>

@include('catalagos.comun.modales')
@endsection

@push('scripts')
    @vite('resources/js/modulos/catalogos-planeacion/acciones/index.ts')
    @vite('resources/js/modulos/catalogos-planeacion/matriz-calibres/index.ts')
@endpush
