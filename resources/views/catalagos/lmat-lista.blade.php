@extends('layouts.app')

@section('page-title', 'Listas de Materiales')

@section('navbar-right')
    <button id="btn-ver-lista" type="button"
        class="w-28 h-9 flex items-center justify-center p-4 bg-black text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        disabled
        title="Ver lista de materiales" aria-label="Ver lista de materiales">
        Ver Lista
    </button>
@endsection

@section('content')
    <div class="container-fluid p-4">
        <div class="bg-white rounded-lg shadow-sm">


            @php
                $opciones = fn ($valores) => $valores->filter()->unique()->sort()->values();
                $ordenes = $opciones($grupos->keys());
                $nombres = $opciones($grupos->map(fn ($l) => $l->first()->Nombre));
                $claves = $opciones($grupos->map(fn ($l) => $l->first()->ItemIdCrudo));
                $tamanos = $opciones($grupos->map(fn ($l) => $l->first()->InventSizeCrudo));
                $salones = $opciones($grupos->map(fn ($l) => $l->first()->Salon));
            @endphp

            <div id="lmat-filtros" class="px-4 py-3 border-b border-gray-200 grid grid-cols-2 sm:grid-cols-5 gap-2">
                <select id="f-orden" class="lmat-filtro-select w-full" data-placeholder="Orden">
                    <option></option>
                    @foreach ($ordenes as $v)
                        <option value="{{ $v }}">{{ $v }}</option>
                    @endforeach
                </select>
                <select id="f-nombre" class="lmat-filtro-select w-full" data-placeholder="Nombre">
                    <option></option>
                    @foreach ($nombres as $v)
                        <option value="{{ $v }}">{{ $v }}</option>
                    @endforeach
                </select>
                <select id="f-clave" class="lmat-filtro-select w-full" data-placeholder="Clave">
                    <option></option>
                    @foreach ($claves as $v)
                        <option value="{{ $v }}">{{ $v }}</option>
                    @endforeach
                </select>
                <select id="f-tamano" class="lmat-filtro-select w-full" data-placeholder="Tamaño">
                    <option></option>
                    @foreach ($tamanos as $v)
                        <option value="{{ $v }}">{{ $v }}</option>
                    @endforeach
                </select>
                <select id="f-salon" class="lmat-filtro-select w-full" data-placeholder="Salón">
                    <option></option>
                    @foreach ($salones as $v)
                        <option value="{{ $v }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div class="overflow-x-auto" style="max-height: calc(100vh - 160px);">
                <table class="w-full text-sm">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Orden</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Nombre</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Descripción</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Clave</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Tamaño</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Salón</th>
                            <th class="px-3 py-2 text-center whitespace-nowrap">Líneas</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Fecha Registro</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grupos as $orden => $lineas)
                            @php $primera = $lineas->first(); @endphp
                            <tr class="lmat-row cursor-pointer border-b border-gray-100 hover:bg-blue-50 transition-colors {{ $loop->even ? 'bg-gray-50' : 'bg-white' }}"
                                data-orden="{{ $orden }}"
                                data-f-orden="{{ mb_strtolower($orden) }}"
                                data-f-nombre="{{ mb_strtolower((string) $primera->Nombre) }}"
                                data-f-clave="{{ mb_strtolower((string) $primera->ItemIdCrudo) }}"
                                data-f-tamano="{{ mb_strtolower((string) $primera->InventSizeCrudo) }}"
                                data-f-salon="{{ mb_strtolower((string) $primera->Salon) }}">
                                <td class="px-3 py-2 font-semibold text-blue-700 whitespace-nowrap">{{ $orden }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->Nombre }}</td>
                                <td class="px-3 py-2">{{ $primera->Descrip }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->ItemIdCrudo }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->InventSizeCrudo }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->Salon }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-bold">{{ $lineas->count() }}</span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->FechaRegistro?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->UsuarioRegistro }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-gray-300 text-3xl block mb-2"></i>
                                    No hay listas de materiales registradas
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        tr.lmat-row-selected,
        tr.lmat-row-selected td {
            background-color: #3b82f6 !important;
            color: #fff !important;
        }

        /* === Estilo de los selects (select2), igual que en Trazabilidad === */
        #lmat-filtros .select2-container--default .select2-selection--single {
            height: 34px;
            display: flex;
            align-items: center;
            border: 1px solid #cbd5e1;
            border-radius: 0.6rem;
            background-color: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        #lmat-filtros .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 32px;
            padding-left: 0.7rem;
            padding-right: 1.6rem;
            color: #334155;
            font-size: 0.8125rem;
        }
        #lmat-filtros .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8;
        }
        #lmat-filtros .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 32px;
            right: 8px;
        }
        #lmat-filtros .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 26px;
            padding: 0 4px;
            color: #94a3b8;
            font-weight: 700;
            cursor: pointer;
        }
        #lmat-filtros .select2-container--default .select2-selection--single .select2-selection__clear:hover {
            color: #ef4444;
        }
        #lmat-filtros .select2-container--default.select2-container--focus .select2-selection--single,
        #lmat-filtros .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .25);
            outline: none;
        }
        .lmat-select2-dd.select2-dropdown {
            border: 1px solid #3b82f6;
            border-radius: 0.6rem;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .12);
            margin-top: 4px;
        }
        .lmat-select2-dd .select2-search__field {
            border: 1px solid #cbd5e1;
            border-radius: 0.45rem;
            padding: 0.35rem 0.5rem;
        }
        .lmat-select2-dd .select2-results__option--highlighted[aria-selected] {
            background-color: #3b82f6;
        }
        .lmat-select2-dd .select2-results__option[aria-selected="true"] {
            background-color: #dbeafe;
            color: #1e40af;
        }
    </style>

    @vite('resources/js/lmat-lista/index.js')
@endsection
