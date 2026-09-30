@extends('layouts.app')

@section('page-title', 'Matriz de Hilos')

@section('navbar-right')
<x-buttons.catalog-actions route="matriz-hilos" :showFilters="false" />
@endsection

@php
    $catalogo = \App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatalogosPlaneacionVista::matrizHilos();
    $dosDecimales = fn ($v) => $v ? number_format($v, 2) : '';
@endphp

@section('content')
<div class="container-fluid" data-catalogo='@json($catalogo)'>
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto overflow-y-auto max-h-[calc(100vh-70px)]">
            <table id="mainTable" class="border-collapse w-full">
                <thead class="sticky top-0 z-10">
                    <tr class="border border-gray-300 px-2 py-2 text-center font-light text-white text-sm bg-blue-500">
                        @foreach (['Hilo', 'Calibre', 'Calibre2', 'CalibreAX', 'Fibra', 'CodColor', 'NombreColor', 'N1', 'N2'] as $titulo)
                            <th scope="col" class="py-2 px-4">{{ $titulo }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="matriz-hilos-body" class="bg-white text-black" data-catalogo-filas>
                    @foreach ($matrizHilos as $item)
                        @php
                            $itemId = $item->getKey() ?? '';
                            $valores = $item->only(['Hilo', 'Calibre', 'Calibre2', 'CalibreAX', 'Fibra', 'CodColor', 'NombreColor', 'N1', 'N2']) + ['Id' => $itemId];
                        @endphp
                        <tr data-fila data-id="{{ $itemId }}" data-valores='@json($valores)' tabindex="0" aria-selected="false"
                            class="text-center hover:bg-blue-50 transition cursor-pointer aria-selected:bg-blue-500 aria-selected:text-white aria-selected:hover:bg-blue-500">
                            <td class="py-2 px-4">{{ $item->Hilo }}</td>
                            <td class="py-2 px-4">{{ $dosDecimales($item->Calibre) }}</td>
                            <td class="py-2 px-4">{{ $dosDecimales($item->Calibre2) }}</td>
                            <td class="py-2 px-4">{{ $item->CalibreAX }}</td>
                            <td class="py-2 px-4">{{ $item->Fibra }}</td>
                            <td class="py-2 px-4">{{ $item->CodColor }}</td>
                            <td class="py-2 px-4">{{ $item->NombreColor }}</td>
                            <td class="py-2 px-4">{{ $dosDecimales($item->N1) }}</td>
                            <td class="py-2 px-4">{{ $dosDecimales($item->N2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('catalagos.comun.modales')
@endsection

@push('scripts')
    @vite('resources/js/modulos/catalogos-planeacion/matriz-hilos/index.ts')
@endpush
