@extends('layouts.app')

@section('page-title', 'Matriz de Hilos')

@section('navbar-right')
<x-buttons.catalog-actions route="matriz-hilos" :showFilters="false" />
@endsection

@section('content')
<div class="container-fluid ">
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto" style="max-height: calc(100vh - 70px); overflow-y: auto;">
            <table id="mainTable" class="border-collapse w-full">
                <thead class="sticky top-0 z-10">
                    <tr class="border border-gray-300 px-2 py-2 text-center font-light text-white text-sm bg-blue-500">
                        <th class="py-2 px-4 ">Hilo</th>
                        <th class="py-2 px-4 ">Calibre</th>
                        <th class="py-2 px-4 ">Calibre2</th>
                        <th class="py-2 px-4 ">CalibreAX</th>
                        <th class="py-2 px-4 ">Fibra</th>
                        <th class="py-2 px-4 ">CodColor</th>
                        <th class="py-2 px-4 ">NombreColor</th>
                        <th class="py-2 px-4 ">N1</th>
                        <th class="py-2 px-4 ">N2</th>
                    </tr>
                </thead>
                <tbody id="matriz-hilos-body" class="bg-white text-black">
                    @foreach ($matrizHilos as $item)
                        @php
                            // Obtener el ID usando getKey() que devuelve el valor de la clave primaria (Id)
                            $itemId = $item->getKey() ?? $item->Id ?? $item->id ?? '';
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="window.catalogManager?.selectRow(this, '{{ $itemId }}', '{{ $itemId }}')"
                            ondblclick="window.catalogManager?.deselectRow(this)"
                            data-id="{{ $itemId }}"
                            data-hilo="{{ $item->Hilo }}"
                            data-calibre="{{ $item->Calibre }}"
                            data-calibre2="{{ $item->Calibre2 }}"
                            data-calibreax="{{ $item->CalibreAX }}"
                            data-fibra="{{ $item->Fibra }}"
                            data-codcolor="{{ $item->CodColor }}"
                            data-nombrecolor="{{ $item->NombreColor }}"
                            data-n1="{{ $item->N1 }}"
                            data-n2="{{ $item->N2 }}"
                        >
                            <td class="py-2 px-4">{{ $item->Hilo }}</td>
                            <td class="py-2 px-4">{{ $item->Calibre ? number_format($item->Calibre, 2) : '' }}</td>
                            <td class="py-2 px-4">{{ $item->Calibre2 ? number_format($item->Calibre2, 2) : '' }}</td>
                            <td class="py-2 px-4">{{ $item->CalibreAX }}</td>
                            <td class="py-2 px-4">{{ $item->Fibra }}</td>
                            <td class="py-2 px-4">{{ $item->CodColor }}</td>
                            <td class="py-2 px-4">{{ $item->NombreColor }}</td>
                            <td class="py-2 px-4">{{ $item->N1 ? number_format($item->N1, 2) : '' }}</td>
                            <td class="py-2 px-4">{{ $item->N2 ? number_format($item->N2, 2) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/catalogs/CatalogBase.js') }}"></script>
<script src="{{ asset('js/catalogs/MatrizHilosCatalog.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    window.catalogManager = new MatrizHilosCatalog({
        initialData: @json($matrizHilos)
    });

    // Aliases globales para coincidir con los handlers generados por action-buttons
    window.agregarMatriz_hilos = () => {
        if (window.catalogManager) {
            window.catalogManager.create();
        }
    };
    window.editarMatriz_hilos = () => {
        if (window.catalogManager) {
            window.catalogManager.edit();
        }
    };
    window.eliminarMatriz_hilos = () => {
        if (window.catalogManager) {
            window.catalogManager.delete();
        }
    };

    // Aliases alternativos
    window.agregarMatrizHilos = () => {
        if (window.catalogManager) {
            window.catalogManager.create();
        }
    };
    window.editarMatrizHilos = () => {
        if (window.catalogManager) {
            window.catalogManager.edit();
        }
    };
    window.eliminarMatrizHilos = () => {
        if (window.catalogManager) {
            window.catalogManager.delete();
        }
    };
});
</script>
@endsection
