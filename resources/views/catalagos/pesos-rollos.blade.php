@extends('layouts.app')

@section('page-title', 'Pesos por Rollos')

@section('navbar-right')
<x-buttons.catalog-actions route="pesos-rollos" :showFilters="true" />
@endsection

@php $catalogo = \App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatalogosPlaneacionVista::pesosRollos(); @endphp

@section('content')
    <div class="container-fluid" data-catalogo='@json($catalogo)'>
        <div class="bg-white overflow-hidden">
            <div class="overflow-y-auto max-h-[calc(100vh-70px)]">
                <table class="table w-full">
                    <thead class="sticky top-0 bg-blue-500 text-white z-10">
                        <tr>
                            <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Cod Artículo</th>
                            <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Nombre</th>
                            <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Tamaño</th>
                            <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Peso Rollo</th>
                        </tr>
                    </thead>
                    <tbody id="pesos-rollos-body" class="bg-white text-black" data-catalogo-filas>
                        @foreach ($pesosRollos as $item)
                            @php $valores = $item->only(['Id', 'ItemId', 'ItemName', 'InventSizeId', 'PesoRollo']); @endphp
                            <tr data-fila data-id="{{ $item->Id }}" data-valores='@json($valores)' tabindex="0" aria-selected="false"
                                class="text-center hover:bg-blue-50 transition cursor-pointer text-black aria-selected:bg-blue-500 aria-selected:text-white aria-selected:hover:bg-blue-500">
                                <td class="py-1 px-4">{{ $item->ItemId }}</td>
                                <td class="py-1 px-4">{{ $item->ItemName }}</td>
                                <td class="py-1 px-4">{{ $item->InventSizeId }}</td>
                                <td class="py-1 px-4 font-semibold">{{ number_format($item->PesoRollo, 2) }} kg</td>
                            </tr>
                        @endforeach
                        <tr data-catalogo-sin-coincidencias hidden>
                            <td colspan="4" class="text-center py-8 text-gray-500">
                                <i class="fas fa-search text-4xl mb-2" aria-hidden="true"></i><br>No se encontraron resultados
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('catalagos.comun.modales')
@endsection

@push('scripts')
    @vite('resources/js/modulos/catalogos-planeacion/pesos-rollos/index.ts')
@endpush
