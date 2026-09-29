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
    @if ($noResults ?? false)
        <div class="alert alert-warning text-center">No se encontraron resultados con la información proporcionada.</div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto max-h-[70vh] relative scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="min-w-full text-sm text-center table-sticky">
                <thead class="bg-blue-500 text-white">
                    <tr>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">ID</th>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">Código</th>
                    </tr>
                </thead>
                <tbody id="ubicaciones-body" class="bg-white">
                    @forelse ($ubicaciones as $ubicacion)
                        @php
                            $uid = $ubicacion->Id ?? uniqid();
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer text-black"
                            data-fila
                            data-uid="{{ $uid }}"
                            data-codigo="{{ $ubicacion->Codigo }}"
                            data-id="{{ $ubicacion->Id }}">
                            <td class="py-2 px-4">{{ $ubicacion->Id }}</td>
                            <td class="py-2 px-4 font-semibold">{{ $ubicacion->Codigo }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="py-4 px-4 text-center text-gray-500">No hay ubicaciones registradas</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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
@endsection

@push('scripts')
    @vite('resources/js/modulos/engomado/catalogo-ubicaciones/index.ts')
@endpush
