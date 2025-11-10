@extends('layouts.app')

@section('page-title', 'Matriz de Hilos')

@section('navbar-right')
<x-buttons.catalog-actions route="matriz-hilos" :showFilters="false" />
@endsection

@section('content')
<div class="container-fluid px-4 py-6 -mt-6">
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto h-[600px]">
            <table id="mainTable" class="border-collapse w-full">
                <thead>
                    <tr class="border border-gray-300 px-2 py-2 text-center font-light text-white text-sm bg-blue-500">
                        <th class="py-2 px-4">Hilo</th>
                        <th class="py-2 px-4">Calibre</th>
                        <th class="py-2 px-4">Calibre2</th>
                        <th class="py-2 px-4">CalibreAX</th>
                        <th class="py-2 px-4">Fibra</th>
                        <th class="py-2 px-4">CodColor</th>
                        <th class="py-2 px-4">NombreColor</th>
                    </tr>
                </thead>
                <tbody id="matriz-hilos-body" class="bg-white text-black">
                    @foreach ($matrizHilos as $item)
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer border-b"
                            onclick="window.catalogManager?.selectRow(this, '{{ $item->id }}', '{{ $item->id }}')"
                            ondblclick="window.catalogManager?.deselectRow(this)"
                            data-id="{{ $item->id }}"
                            data-hilo="{{ $item->Hilo }}"
                            data-calibre="{{ $item->Calibre }}"
                            data-calibre2="{{ $item->Calibre2 }}"
                            data-calibreax="{{ $item->CalibreAX }}"
                            data-fibra="{{ $item->Fibra }}"
                            data-codcolor="{{ $item->CodColor }}"
                            data-nombrecolor="{{ $item->NombreColor }}"
                        >
                            <td class="py-2 px-4 border-b">{{ $item->Hilo }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->Calibre ? number_format($item->Calibre, 4) : '' }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->Calibre2 ? number_format($item->Calibre2, 4) : '' }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->CalibreAX }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->Fibra }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->CodColor }}</td>
                            <td class="py-2 px-4 border-b">{{ $item->NombreColor }}</td>
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
    window.agregarMatriz_hilos = () => window.catalogManager.create();
    window.editarMatriz_hilos = () => window.catalogManager.edit();
    window.eliminarMatriz_hilos = () => window.catalogManager.delete();

    // Aliases alternativos
    window.agregarMatrizHilos = () => window.catalogManager.create();
    window.editarMatrizHilos = () => window.catalogManager.edit();
    window.eliminarMatrizHilos = () => window.catalogManager.delete();
});
</script>
@endsection
