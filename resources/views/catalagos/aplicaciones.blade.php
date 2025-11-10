@extends('layouts.app')

@section('page-title', 'Catálogo de Aplicaciones')

@section('navbar-right')
<x-buttons.catalog-actions route="aplicaciones" :showFilters="true" />
@endsection

@section('content')
<div class="w-full">
    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto max-h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="table table-bordered table-sm w-full">
                <thead class="sticky top-0 bg-blue-500 text-white z-10">
                    <tr>
                        <th class="py-1 px-2 font-bold tracking-wider text-center">Clave</th>
                        <th class="py-1 px-2 font-bold tracking-wider text-center">Nombre</th>
                        <th class="py-1 px-2 font-bold tracking-wider text-center">Factor</th>
                    </tr>
                </thead>
                <tbody id="aplicaciones-body" class="bg-white text-black">
                    @foreach ($aplicaciones as $item)
                        @php
                            $uniqueId = $item->AplicacionId;
                            $recordId = $item->Id ?? $item->id ?? null;
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="window.catalogManager?.selectRow(this, '{{ $uniqueId }}', '{{ $recordId ?? $uniqueId }}')"
                            ondblclick="window.catalogManager?.deselectRow(this)"
                            data-aplicacion="{{ $uniqueId }}"
                            data-aplicacion-id="{{ $uniqueId }}"
                            data-clave="{{ $item->AplicacionId }}"
                            data-nombre="{{ $item->Nombre }}"
                            data-factor="{{ $item->Factor }}"
                            data-id="{{ $recordId ?? $uniqueId }}"
                        >
                            <td class="py-1 px-4 border-b">{{ $item->AplicacionId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->Nombre }}</td>
                            <td class="py-1 px-4 border-b font-semibold">{{ $item->Factor }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="{{ asset('js/catalogs/CatalogBase.js') }}"></script>
<script src="{{ asset('js/catalogs/AplicacionesCatalog.js') }}"></script>

<style>
    .scrollbar-thin { scrollbar-width: thin; }
    .scrollbar-thin::-webkit-scrollbar { width: 8px; }
    .scrollbar-thumb-gray-400::-webkit-scrollbar-thumb { background-color: #9ca3af; border-radius: 4px; }
    .scrollbar-track-gray-100::-webkit-scrollbar-track { background-color: #f3f4f6; }
    .scrollbar-thin::-webkit-scrollbar-thumb:hover { background-color: #6b7280; }
    .swal2-input { width: 100% !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar el catálogo
    window.catalogManager = new AplicacionesCatalog({
        initialData: @json($aplicaciones)
    });

    // Funciones globales esperadas por catalog-actions component
    // El componente genera: agregarAplicaciones, editarAplicaciones, eliminarAplicaciones, filtrarAplicaciones, limpiarFiltrosAplicaciones, subirExcelAplicaciones
    window.agregarAplicaciones = () => window.catalogManager.create();
    window.editarAplicaciones = () => window.catalogManager.edit();
    window.eliminarAplicaciones = () => window.catalogManager.delete();
    window.filtrarAplicaciones = () => window.catalogManager.showFilters();
    window.limpiarFiltrosAplicaciones = () => window.catalogManager.clearFilters();
    window.subirExcelAplicaciones = () => window.catalogManager.uploadExcel();
});
</script>
@endsection
