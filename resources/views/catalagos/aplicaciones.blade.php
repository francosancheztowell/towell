@extends('layouts.app')

@section('page-title', 'Catálogo de Aplicaciones')

@section('navbar-right')
<x-buttons.catalog-actions route="aplicaciones" :showFilters="true" />
@endsection

@php $catalogo = \App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatalogosPlaneacionVista::aplicaciones(); @endphp

@section('content')
<div class="w-full" data-catalogo='@json($catalogo)'>
    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto max-h-[640px] [scrollbar-width:thin]">
            <table class="table table-bordered table-sm w-full">
                <thead class="sticky top-0 bg-blue-500 text-white z-10">
                    <tr>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Clave</th>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Nombre</th>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Factor</th>
                    </tr>
                </thead>
                <tbody id="aplicaciones-body" class="bg-white text-black" data-catalogo-filas>
                    @foreach ($aplicaciones as $item)
                        @php
                            $recordId = $item->Id ?? $item->AplicacionId;
                            $valores = ['Id' => $recordId, 'AplicacionId' => $item->AplicacionId, 'Nombre' => $item->Nombre, 'Factor' => $item->Factor];
                        @endphp
                        <tr data-fila data-id="{{ $recordId }}" data-valores='@json($valores)' tabindex="0" aria-selected="false"
                            class="text-center hover:bg-blue-50 transition cursor-pointer aria-selected:bg-blue-500 aria-selected:text-white aria-selected:hover:bg-blue-500">
                            <td class="py-1 px-4">{{ $item->AplicacionId }}</td>
                            <td class="py-1 px-4">{{ $item->Nombre }}</td>
                            <td class="py-1 px-4 font-semibold">{{ $item->Factor }}</td>
                        </tr>
                    @endforeach
                    <tr data-catalogo-sin-coincidencias hidden>
                        <td colspan="3" class="text-center py-8 text-gray-500">
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
    @vite('resources/js/modulos/catalogos-planeacion/aplicaciones/index.ts')
@endpush
