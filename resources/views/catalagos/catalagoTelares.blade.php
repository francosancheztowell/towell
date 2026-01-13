@extends('layouts.app')

@section('page-title', 'Catálogo de Telares')

@section('navbar-right')
<x-buttons.catalog-actions route="telares" :showFilters="true" />
@endsection

@section('content')
    @if ($noResults ?? false)
        <div class="alert alert-warning text-center">No se encontraron resultados con la información proporcionada.</div>
    @endif

    <div class="bg-white overflow-hidden w-full">
        <div class="overflow-y-auto h-[640px]  scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100 w-full">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-blue-500 border-b-2 text-white z-20">
                    <tr>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Salón</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Telar</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Nombre</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Grupo</th>
                    </tr>
                </thead>
                <tbody id="telares-body" class="bg-white text-black">
                    @foreach ($telares as $t)
                        @php $uid = $t->SalonTejidoId . '_' . $t->NoTelarId; @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="window.catalogManager?.selectRow(this, '{{ $uid }}', '{{ $uid }}')"
                            ondblclick="window.catalogManager?.deselectRow(this)"
                            data-uid="{{ $uid }}"
                            data-salon="{{ $t->SalonTejidoId }}"
                            data-telar="{{ $t->NoTelarId }}"
                            data-nombre="{{ $t->Nombre }}"
                            data-grupo="{{ $t->Grupo ?? '' }}"
                            data-id="{{ $uid }}">
                            <td class="py-2 px-4">{{ $t->SalonTejidoId }}</td>
                            <td class="py-2 px-4">{{ $t->NoTelarId }}</td>
                            <td class="py-2 px-4">{{ $t->Nombre }}</td>
                            <td class="py-2 px-4">{{ $t->Grupo ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
    <script src="{{ asset('js/catalogs/TelaresCatalog.js') }}"></script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        window.catalogManager = new TelaresCatalog({
            initialData: @json($telares)
        });

        // Funciones globales para el navbar
        window.agregarTelares = () => window.catalogManager.create();
        window.editarTelares = () => window.catalogManager.edit();
        window.eliminarTelares = () => window.catalogManager.delete();
        window.subirExcelTelares = () => window.catalogManager.uploadExcel();
        window.filtrarTelares = () => window.catalogManager.showFilters();
        window.limpiarFiltrosTelares = () => window.catalogManager.clearFilters();
    });
    </script>
@endsection
