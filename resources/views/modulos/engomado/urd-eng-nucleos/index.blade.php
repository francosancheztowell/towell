@extends('layouts.app')

@section('title', 'Catálogo de Núcleos')
@section('page-title')
Catálogo de Núcleos
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">

        <!-- Botón Agregar -->
        <x-navbar.button-create
        data-accion="crear"
        title="Nuevo Núcleo"
        module="Catálogo de Núcleos"
        />

        <!-- Botón Editar -->
        <x-navbar.button-edit
        id="btn-top-edit"
        data-accion="editar"
        module="Catálogo de Núcleos"
        />

        <!-- Botón Eliminar -->
        <x-navbar.button-delete
        id="btn-top-delete"
        data-accion="eliminar"
        module="Catálogo de Núcleos"
        />
    </div>
@endsection

@section('content')
@php
    $configNucleos = [
        "rutas" => [
            "guardar" => route("urd-eng-nucleos.store"),
            "actualizar" => route("urd-eng-nucleos.update", ["urdEngNucleo" => "__ID__"]),
            "eliminar" => route("urd-eng-nucleos.destroy", ["urdEngNucleo" => "__ID__"]),
        ],
    ];
@endphp
<div class="w-full px-4 py-6" id="catalogo-nucleos" data-pagina='@json($configNucleos)'>
    {{-- Los avisos de sesión (success/error) y los errores de validación los pinta x-ui.flash del layout
         (antes: tres scripts inline con SweetAlert). --}}

    <!-- Tabla de Núcleos -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden w-full">
        <div class="overflow-x-auto w-full">
            <table class="w-full text-sm">
                <thead class=" bg-blue-500 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Salón</th>
                        <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                            data-key="{{ $item->Id }}"
                            data-salon="{{ e($item->Salon) }}"
                            data-nombre="{{ e($item->Nombre) }}"
                            data-fila
                            aria-selected="false">
                            <td class="px-4 py-3 align-middle font-medium text-gray-700 select-row-text">
                                {{ $item->Salon }}
                            </td>
                            <td class="px-4 py-3 align-middle text-gray-800 select-row-text">
                                {{ $item->Nombre }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-8 text-center text-gray-500">
                                <i class="fa-solid fa-inbox text-4xl mb-2 text-gray-300"></i>
                                <p class="text-lg">No se encontraron núcleos</p>
                                @if($q)
                                    <p class="text-sm mt-2">Intenta con otro término de búsqueda</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        @if($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                {{ $items->links() }}
            </div>
        @endif
    </div>

    <!-- Formulario oculto para eliminación -->
    <form id="globalDeleteForm" action="#" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>

{{-- Formulario Crear/Editar (antes un Swal que copiaba los valores a dos <form> ocultos). --}}
<x-ui.modal-base id="nucleoModal" title="Nuevo Núcleo" size="sm" :close-on-backdrop="true">
    <form id="nucleoForm" method="POST" action="{{ route('urd-eng-nucleos.store') }}" class="grid grid-cols-1 gap-3 text-left text-sm" novalidate>
        @csrf
        <input type="hidden" name="_method" value="PUT" disabled data-metodo-put>
        <x-ui.field as="select" name="Salon" id="nucleo-salon" label="Salón" required>
            <option value="">-- Seleccione un salón --</option>
            @foreach (['JACQUARD', 'SMIT', 'KARL MAYER'] as $salon)
                <option value="{{ $salon }}">{{ $salon }}</option>
            @endforeach
        </x-ui.field>
        <x-ui.field as="input" name="Nombre" id="nucleo-nombre" label="Nombre" required
                    placeholder="Nombre del núcleo" maxlength="120" autocomplete="off" />
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" size="nav" icon="fa-times" data-ui-modal-close-target="nucleoModal">Cancelar</x-ui.button>
        <x-ui.button variant="create" size="nav" type="submit" form="nucleoForm" icon="fa-check" data-nucleo-guardar><span data-texto-guardar>Guardar</span></x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>

<style>
    /* Estilos para filas seleccionadas */
    tbody tr {
        transition: all 0.15s ease;
    }

    tbody tr:hover {
        background-color: #eff6ff !important;
    }

    tbody tr[aria-selected="true"] {
        background-color: #3b82f6 !important;
    }

    tbody tr[aria-selected="true"] .select-row-text {
        color: white !important;
    }


</style>
@endsection

@push('scripts')
    @vite('resources/js/modulos/engomado/catalogo-nucleos/index.ts')
@endpush
