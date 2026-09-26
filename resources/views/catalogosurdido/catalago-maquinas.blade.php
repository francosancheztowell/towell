@extends('layouts.app')

@section('page-title', 'Máquinas Urdido')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
        data-accion="crear"
        title="Nueva Máquina"
        module="Catalogos Maquinas"
        />
        <x-navbar.button-edit
        id="btnEdit"
        data-accion="editar"
        title="Editar Máquina"
        :disabled="true"
        module="Catalogos Maquinas"
        />
        <x-navbar.button-delete
        id="btnDelete"
        data-accion="eliminar"
        title="Eliminar Máquina"
        :disabled="true"
        hoverBg="hover:bg-red-200"
        module="Catalogos Maquinas"
        />
        {{-- Antes x-buttons.catalog-actions route="maquinas": 'maquinas' no está en su mapa de permisos,
             así que solo pintaba Filtrar/Restablecer. Mismo markup, sin handlers inline. --}}
        <div class="flex items-center gap-1">
            <button type="button" id="btn-filtrar" data-accion="filtrar"
               class="relative p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
               title="Filtrar" aria-label="Filtrar">
                <i class="fas fa-filter text-lg" aria-hidden="true"></i>
            </button>
            <button type="button" id="btn-restablecer-maquinas" data-accion="restablecer"
               class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors"
               title="Restablecer" aria-label="Restablecer">
                <i class="fas fa-redo text-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>
@endsection

@section('content')
    @php
        $configMaquinas = [
            "rutas" => [
                "guardar" => route("urdido.catalogo.maquinas.store"),
                "actualizar" => route("urdido.catalogo.maquinas.update", ["maquinaId" => "__ID__"]),
                "eliminar" => route("urdido.catalogo.maquinas.destroy", ["maquinaId" => "__ID__"]),
                "listado" => url()->current(),
            ],
        ];
    @endphp
    <div class="container" id="catalogo-maquinas" data-pagina='@json($configMaquinas)'>
    @if (! empty($error))
        <x-ui.alert type="error" :message="$error" />
    @endif
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
                    @forelse ($maquinas as $maquina)
                        @php $uid = $maquina->MaquinaId ?? uniqid(); @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            data-fila
                            data-uid="{{ $uid }}"
                            data-maquina-id="{{ $maquina->MaquinaId }}"
                            data-nombre="{{ $maquina->Nombre ?? '' }}"
                            data-departamento="{{ $maquina->Departamento ?? '' }}"
                            data-id="{{ $maquina->MaquinaId }}">
                            <td class="py-2 px-4 ">{{ $maquina->MaquinaId }}</td>
                            <td class="py-2 px-4 ">{{ $maquina->Nombre ?? 'N/A' }}</td>
                            <td class="py-2 px-4 ">{{ $maquina->Departamento ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 px-4 text-center text-gray-500">No hay máquinas registradas</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>

    <!-- Modal Crear/Editar Máquina -->
    <div id="formModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
            <div class="flex justify-between items-center border-b p-4 bg-blue-500 rounded-t-lg">
                <h2 class="text-xl font-bold text-white" id="formModalTitle">Nueva Máquina</h2>
                <button type="button" data-accion="cerrar-modal" data-modal="formModal" class="text-white hover:text-gray-200" aria-label="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="maquinaForm" novalidate>
                <div class="p-6">
                    <input type="hidden" id="original_maquinaid" name="original_maquinaid">

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label for="MaquinaId" class="block text-sm font-medium text-gray-700 mb-1">
                                Máquina ID <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="MaquinaId" name="MaquinaId" required maxlength="50"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200"
                                placeholder="Ej: MC Coy 1, MC Coy 2">
                        </div>
                        <div>
                            <label for="Nombre" class="block text-sm font-medium text-gray-700 mb-1">
                                Nombre
                            </label>
                            <input type="text" id="Nombre" name="Nombre" maxlength="100"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200"
                                placeholder="Ej: MC Coy 1">
                        </div>
                        <div>
                            <label for="Departamento" class="block text-sm font-medium text-gray-700 mb-1">
                                Departamento
                            </label>
                            <input type="text" id="Departamento" name="Departamento" maxlength="50"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200"
                                placeholder="Ej: Urdido">
                        </div>
                    </div>
                </div>

                <div class="border-t p-4 flex justify-end gap-2 bg-gray-50 rounded-b-lg">
                    <button type="button" data-accion="cerrar-modal" data-modal="formModal"
                        class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmar Eliminación -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="flex justify-between items-center border-b p-4 bg-red-500 rounded-t-lg">
                <h2 class="text-xl font-bold text-white">Confirmar Eliminación</h2>
                <button type="button" data-accion="cerrar-modal" data-modal="deleteModal" class="text-white hover:text-gray-200" aria-label="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="shrink-0">
                        <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-700 font-medium">¿Está seguro que desea eliminar esta máquina?</p>
                        <p class="text-gray-500 text-sm mt-1">Esta acción no se puede deshacer.</p>
                    </div>
                </div>
            </div>
            <div class="border-t p-4 flex justify-end gap-2 bg-gray-50 rounded-b-lg">
                <button type="button" data-accion="cerrar-modal" data-modal="deleteModal"
                    class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                    Cancelar
                </button>
                <button type="button" data-accion="confirmar-eliminar"
                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    {{-- Filtro (antes un Swal con formulario). --}}
    <x-ui.modal-base id="filtroMaquinasModal" title="Filtrar Máquinas" size="sm" :close-on-backdrop="true">
        <form id="filtroMaquinasForm" class="grid grid-cols-1 gap-3 text-left text-sm">
            <x-ui.field as="input" name="maquina_id" id="filtro-maquina-id" label="Máquina ID"
                        placeholder="Buscar por Máquina ID" :value="request('maquina_id')" autocomplete="off" />
            <x-ui.field as="input" name="nombre" id="filtro-nombre" label="Nombre"
                        placeholder="Buscar por Nombre" :value="request('nombre')" autocomplete="off" />
            <x-ui.field as="input" name="departamento" id="filtro-departamento" label="Departamento"
                        placeholder="Buscar por Departamento" :value="request('departamento')" autocomplete="off" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="filtroMaquinasModal">Cancelar</x-ui.button>
            <x-ui.button variant="create" size="nav" type="submit" form="filtroMaquinasForm" icon="fa-filter">Filtrar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal-base>

<style>
  .scrollbar-thin { scrollbar-width: thin; }
  .scrollbar-thin::-webkit-scrollbar { width: 8px; }
  .scrollbar-thumb-gray-400::-webkit-scrollbar-thumb { background-color: #9ca3af; border-radius: 4px; }
  .scrollbar-track-gray-100::-webkit-scrollbar-track { background-color: #f3f4f6; }
  .scrollbar-thin::-webkit-scrollbar-thumb:hover { background-color: #6b7280; }
</style>
@endsection

@push('scripts')
    @vite('resources/js/modulos/urdido/catalogo-maquinas/index.ts')
@endpush
