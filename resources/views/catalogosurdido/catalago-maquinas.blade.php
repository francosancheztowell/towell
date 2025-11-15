@extends('layouts.app')

@section('page-title', 'Catálogo de Máquinas')

@section('navbar-right')
<x-buttons.catalog-actions route="maquinas" :showFilters="true" />
@endsection

@section('content')
    <div class="container">
    @if ($noResults ?? false)
        <div class="alert alert-warning text-center">No se encontraron resultados con la información proporcionada.</div>
        @endif

    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-blue-500 border-b-2 text-white z-20">
                    <tr>
                        <th class="py-1 px-2 font-bold tracking-wider text-center">Máquina ID</th>
                        <th class="py-1 px-2 font-bold tracking-wider text-center">Nombre</th>
                        <th class="py-1 px-2 font-bold tracking-wider text-center">Departamento</th>
                    </tr>
                </thead>
                <tbody id="maquinas-body" class="bg-white text-black">
                    @foreach ($maquinas as $maquina)
                        @php $uid = $maquina->MaquinaId ?? uniqid(); @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="window.catalogManager?.selectRow(this, '{{ $uid }}', '{{ $maquina->MaquinaId }}')"
                            ondblclick="window.catalogManager?.deselectRow(this)"
                            data-uid="{{ $uid }}"
                            data-maquina-id="{{ $maquina->MaquinaId }}"
                            data-nombre="{{ $maquina->Nombre ?? '' }}"
                            data-departamento="{{ $maquina->Departamento ?? '' }}"
                            data-id="{{ $maquina->MaquinaId }}">
                            <td class="py-2 px-4 border-b">{{ $maquina->MaquinaId }}</td>
                            <td class="py-2 px-4 border-b">{{ $maquina->Nombre ?? 'N/A' }}</td>
                            <td class="py-2 px-4 border-b">{{ $maquina->Departamento ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    </div>


<style>
  .scrollbar-thin { scrollbar-width: thin; }
  .scrollbar-thin::-webkit-scrollbar { width: 8px; }
  .scrollbar-thumb-gray-400::-webkit-scrollbar-thumb { background-color: #9ca3af; border-radius: 4px; }
  .scrollbar-track-gray-100::-webkit-scrollbar-track { background-color: #f3f4f6; }
  .scrollbar-thin::-webkit-scrollbar-thumb:hover { background-color: #6b7280; }
  .swal2-input { width: 100% !important; }
</style>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/catalogs/CatalogBase.js') }}"></script>
    <script src="{{ asset('js/catalogs/MaquinasUrdidoCatalog.js') }}"></script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        window.catalogManager = new MaquinasUrdidoCatalog({
            initialData: @json($maquinas)
        });

        // Funciones globales para el navbar
        window.agregarMaquinas = () => window.catalogManager.create();
        window.editarMaquinas = () => window.catalogManager.edit();
        window.eliminarMaquinas = () => window.catalogManager.delete();
        window.subirExcelMaquinas = () => window.catalogManager.uploadExcel();
        window.filtrarMaquinas = () => window.catalogManager.showFilters();
        window.limpiarFiltrosMaquinas = () => window.catalogManager.clearFilters();
    });
    </script>
@endsection

