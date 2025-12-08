@extends('layouts.app')

@section('page-title', 'Catálogo de Julios')

@section('navbar-right')
<x-buttons.catalog-actions route="julios" :showFilters="true" />
@endsection

@section('content')
    <div class="w-full">
    @if ($noResults ?? false)
        <div class="alert alert-warning text-center">No se encontraron resultados con la información proporcionada.</div>
        @endif

    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto max-h-[70vh] relative scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="min-w-full text-sm text-center table-sticky">
                <thead class="bg-blue-500 text-white">
                    <tr>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">No. Julio</th>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">Tara</th>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">Departamento</th>
                    </tr>
                </thead>
                <tbody id="julios-body" class="bg-white text-black">
                    @foreach ($julios as $julio)
                        @php
                            $uid = $julio->Id ?? uniqid();
                            $dep = strtoupper($julio->Departamento ?? '');
                            $depBadge = $dep === 'URDIDO'
                                ? 'bg-indigo-100 text-indigo-700'
                                : ($dep === 'ENGOMADO'
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-gray-100 text-gray-700');
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="window.catalogManager?.selectRow(this, '{{ $uid }}', '{{ $julio->NoJulio }}')"
                            ondblclick="window.catalogManager?.deselectRow(this)"
                            data-uid="{{ $uid }}"
                            data-no-julio="{{ $julio->NoJulio }}"
                            data-tara="{{ $julio->Tara ?? 0 }}"
                            data-departamento="{{ $julio->Departamento ?? '' }}"
                            data-id="{{ $julio->Id ?? $uid }}">
                            <td class="py-2 px-4">{{ $julio->NoJulio }}</td>
                            <td class="py-2 px-4">{{ number_format($julio->Tara ?? 0, 2) }}</td>
                            <td class="py-2 px-4">
                                <span class="px-2 py-1 rounded text-xs font-semibold {{ $depBadge }}">
                                    {{ $julio->Departamento ?? 'N/A' }}
                                </span>
                            </td>
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
  /* Encabezado fijo en scroll interno */
  .table-sticky { border-collapse: separate; border-spacing: 0; }
  .table-sticky thead {
    position: sticky;
    top: 0;
    z-index: 45;

  }
  .table-sticky thead th {
    position: sticky;
    top: 0;
    z-index: 50;
    background-clip: padding-box;
  }
</style>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/catalogs/CatalogBase.js') }}"></script>
    <script src="{{ asset('js/catalogs/JuliosCatalog.js') }}"></script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        window.catalogManager = new JuliosCatalog({
            initialData: @json($julios)
        });

        // Funciones globales para el navbar
        window.agregarJulios = () => window.catalogManager.create();
        window.editarJulios = () => window.catalogManager.edit();
        window.eliminarJulios = () => window.catalogManager.delete();
        window.subirExcelJulios = () => window.catalogManager.uploadExcel();
        window.filtrarJulios = () => window.catalogManager.showFilters();
        window.limpiarFiltrosJulios = () => window.catalogManager.clearFilters();
    });
    </script>
@endsection

