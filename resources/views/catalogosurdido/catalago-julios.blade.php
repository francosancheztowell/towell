@extends('layouts.app')

@section('page-title', 'Catálogo de Julios')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
        data-accion="crear"
        title="Nuevo Julio"
        module="Catalogo Julios Eng"
        />
        <x-navbar.button-edit
        id="btnEdit"
        data-accion="editar"
        title="Editar Julio"
        module="Catalogo Julios Eng"
        />
        <x-navbar.button-delete
        id="btnDelete"
        data-accion="eliminar"
        title="Eliminar Julio"
        module="Catalogo Julios Eng"/>
        {{-- Antes x-buttons.catalog-actions route="julios": 'julios' no está en su mapa de permisos,
             así que solo pintaba Filtrar/Restablecer (con onclick). Mismo markup, sin handlers inline. --}}
        <div class="flex items-center gap-1">
            <button type="button" id="btn-filtrar" data-accion="filtrar"
               class="relative p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
               title="Filtrar" aria-label="Filtrar">
                <i class="fas fa-filter text-lg" aria-hidden="true"></i>
            </button>
            <button type="button" id="btn-restablecer-julios" data-accion="restablecer"
               class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors"
               title="Restablecer" aria-label="Restablecer">
                <i class="fas fa-redo text-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>
@endsection

@section('content')
    @php
        $rutaJulios = $departamentoFiltro === 'Engomado' ? 'engomado.configuracion.catalogos.julios' : 'urdido.catalogos.julios';
        $configJulios = [
            "departamento" => $departamentoFiltro,
            "rutas" => [
                "guardar" => route($rutaJulios.".store"),
                "actualizar" => route($rutaJulios.".update", ["id" => "__ID__"]),
                "eliminar" => route($rutaJulios.".destroy", ["id" => "__ID__"]),
                "listado" => url()->current(),
            ],
        ];
    @endphp
    <div class="w-full" id="catalogo-julios" data-pagina='@json($configJulios)'>
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
                        <th class="py-2 px-2 font-bold tracking-wider text-center">No. Julio</th>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">Tara</th>
                        <th class="py-2 px-2 font-bold tracking-wider text-center">Departamento</th>
                    </tr>
                </thead>
                <tbody id="julios-body" class="bg-white">
                    @forelse ($julios as $julio)
                        @php
                            $uid = $julio->Id ?? uniqid();
                            $dep = strtoupper($julio->Departamento ?? '');
                            $depBadge = $dep === 'URDIDO'
                                ? 'bg-indigo-100 text-indigo-700'
                                : ($dep === 'ENGOMADO'
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-gray-100 text-gray-700');
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer text-black"
                            data-fila
                            data-uid="{{ $uid }}"
                            data-no-julio="{{ $julio->NoJulio }}"
                            data-tara="{{ $julio->Tara ?? 0 }}"
                            data-departamento="{{ $julio->Departamento ?? '' }}"
                            data-id="{{ $julio->Id ?? $julio->NoJulio }}">
                            <td class="py-2 px-4">{{ $julio->NoJulio }}</td>
                            <td class="py-2 px-4">{{ number_format($julio->Tara ?? 0, 2) }}</td>
                            <td class="py-2 px-4">
                                <span class="px-2 py-1 rounded text-xs font-semibold {{ $depBadge }}">
                                    {{ $julio->Departamento ?? 'N/A' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 px-4 text-center text-gray-500">No hay julios registrados</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>

    {{-- Formulario Crear/Editar (antes un Swal con HTML armado en JS). --}}
    <x-ui.modal-base id="julioModal" title="Nuevo Julio" size="sm" :close-on-backdrop="true">
        <form id="julioForm" class="grid grid-cols-1 gap-3 text-left text-sm" novalidate>
            <x-ui.field as="input" name="NoJulio" id="julio-no" label="No. Julio" required
                        placeholder="Ej: J001, J002" maxlength="50" autocomplete="off" />
            <x-ui.field as="number" name="Tara" id="julio-tara" label="Tara" step="0.01" min="0" placeholder="Ej: 10.50" />
            <x-ui.field as="select" name="Departamento" id="julio-departamento" label="Departamento" disabled>
                <option value="{{ $departamentoFiltro }}" selected>{{ $departamentoFiltro }}</option>
            </x-ui.field>
        </form>
        <x-slot:footer>
            <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="julioModal">Cancelar</x-ui.button>
            <x-ui.button variant="create" size="nav" type="submit" form="julioForm" data-julio-guardar><span data-texto-guardar>Guardar</span></x-ui.button>
        </x-slot:footer>
    </x-ui.modal-base>

    {{-- Filtro (antes un Swal): el departamento ya lo fija la ruta. --}}
    <x-ui.modal-base id="filtroJuliosModal" title="Filtrar Julios" size="sm" :close-on-backdrop="true">
        <form id="filtroJuliosForm" class="grid grid-cols-1 gap-3 text-left text-sm">
            <x-ui.field as="input" name="no_julio" id="filtro-no-julio" label="No. Julio"
                        placeholder="Buscar por No. Julio" :value="request('no_julio')" autocomplete="off" />
            <x-ui.field as="select" name="departamento_ruta" id="filtro-departamento" label="Departamento" disabled>
                <option value="{{ $departamentoFiltro }}" selected>{{ $departamentoFiltro }}</option>
            </x-ui.field>
        </form>
        <x-slot:footer>
            <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="filtroJuliosModal">Cancelar</x-ui.button>
            <x-ui.button variant="create" size="nav" type="submit" form="filtroJuliosForm" icon="fa-filter">Filtrar</x-ui.button>
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
    @vite('resources/js/modulos/urdido/catalogo-julios/index.ts')
@endpush
