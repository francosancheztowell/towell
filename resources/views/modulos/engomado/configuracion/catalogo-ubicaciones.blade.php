@extends('layouts.app')

@section('page-title', 'Catálogo de Ubicaciones')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
        data-accion="crear"
        title="Nueva Ubicación"
        module="Catalogo Ubicaciones"
        />
        <x-navbar.button-edit
        id="btnEdit"
        data-accion="editar"
        title="Editar Ubicación"
        module="Catalogo Ubicaciones"
        />
        <x-navbar.button-delete
        id="btnDelete"
        data-accion="eliminar"
        title="Eliminar Ubicación"
        module="Catalogo Ubicaciones"
        />
    </div>
@endsection

@section('content')
    @php
        $configUbicaciones = [
            "rutas" => [
                "guardar" => route("engomado.configuracion.catalogo.ubicaciones.store"),
                "actualizar" => route("engomado.configuracion.catalogo.ubicaciones.update", ["id" => "__ID__"]),
                "eliminar" => route("engomado.configuracion.catalogo.ubicaciones.destroy", ["id" => "__ID__"]),
            ],
        ];
    @endphp
    <div class="w-full" id="catalogo-ubicaciones" data-pagina='@json($configUbicaciones)'>
    @if (! empty($error))
        <x-ui.alert type="error" :message="$error" />
    @endif

    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto max-h-[70vh] relative scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <th scope="col" class="text-center">ID</th>
                        <th scope="col" class="text-center">Código</th>
                    </tr>
                </x-slot:head>
                <tbody id="ubicaciones-body">
                    @forelse ($ubicaciones as $ubicacion)
                        @php
                            $uid = $ubicacion->Id ?? uniqid();
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer text-black"
                            data-fila
                            data-uid="{{ $uid }}"
                            data-codigo="{{ $ubicacion->Codigo }}"
                            data-id="{{ $ubicacion->Id }}">
                            <td class="py-2 px-4 text-inherit">{{ $ubicacion->Id }}</td>
                            <td class="py-2 px-4 font-semibold text-inherit">{{ $ubicacion->Codigo }}</td>
                        </tr>
                    @empty
                        <x-ui.table-empty :colspan="2" message="No hay ubicaciones registradas" icon="fa-location-dot" />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>
    </div>
    </div>

    {{-- Formulario Crear/Editar (antes un Swal con HTML armado en JS). --}}
    <x-ui.modal-base id="ubicacionModal" title="Nueva Ubicación" size="sm" :close-on-backdrop="true">
        <form id="ubicacionForm" class="grid grid-cols-1 gap-3 text-left text-sm" novalidate>
            <x-ui.field as="input" name="Codigo" id="ubicacion-codigo" label="Código" required
                        placeholder="Ej: A1, B1, C1" maxlength="10" autocomplete="off" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="ubicacionModal">Cancelar</x-ui.button>
            <x-ui.button variant="create" size="nav" type="submit" form="ubicacionForm" data-ubicacion-guardar><span data-texto-guardar>Guardar</span></x-ui.button>
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
    @vite('resources/js/modulos/engomado/catalogo-ubicaciones/index.ts')
@endpush
