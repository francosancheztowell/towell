{{--
    Catálogos de atadores: Actividades, Comentarios y Máquinas (piloto DS-12).
    Una sola vista; lo que cambia viene en $catalogo
    (App\Http\Controllers\Atadores\Catalogos\CatalogosAtadoresVista) y el comportamiento en
    resources/js/modulos/catalogos-atadores/index.ts (CRUD sobre resources/js/catalogos/catalog-base.ts).
    Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md
--}}
@extends('layouts.app')

@section('page-title', $catalogo['titulo'])

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create id="btnCreate" :title="$catalogo['textos']['nuevo']" :module="$catalogo['modulo']" />
        <x-navbar.button-edit id="btnEdit" :title="$catalogo['textos']['editar']" :module="$catalogo['modulo']" :disabled="true" />
        <x-navbar.button-delete id="btnDelete" :title="$catalogo['textos']['eliminar']" :module="$catalogo['modulo']" :disabled="true" />
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6" data-catalogo='@json($catalogo)'>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <x-ui.table variant="subtle" :sticky="false" id="{{ $catalogo['clave'] }}Table">
                <x-slot:head>
                    <tr>
                        @foreach ($catalogo['columnas'] as $columna)
                            <th scope="col">{{ $columna['titulo'] }}</th>
                        @endforeach
                    </tr>
                </x-slot:head>

                <tbody data-catalogo-filas>
                    @foreach ($filas as $fila)
                        @include('modulos.catalogos-atadores.fila', ['valores' => $fila])
                    @endforeach
                    <x-ui.table-empty :colspan="count($catalogo['columnas'])" :message="$catalogo['textos']['vacio']"
                                      data-catalogo-vacio :hidden="$filas->isNotEmpty()" />
                </tbody>
            </x-ui.table>
        </div>
    </div>

    {{-- La fila que pinta el JS al crear o editar sale de aquí: mismas clases que las del servidor. --}}
    <template data-catalogo-plantilla>
        @include('modulos.catalogos-atadores.fila', ['valores' => null])
    </template>
</div>

<x-ui.modal-base id="formModal" :title="$catalogo['textos']['nuevo']" size="lg" :close-on-backdrop="true">
    <form id="catalogoForm" class="space-y-4">
        <input type="hidden" name="__original" value="">
        @foreach ($catalogo['campos'] as $campo)
            <x-ui.field :as="$campo['tipo']" :name="$campo['nombre']" :label="$campo['etiqueta']"
                        :required="$campo['requerido'] ?? false" :placeholder="$campo['placeholder'] ?? null"
                        :min="$campo['min'] ?? null" :max="$campo['max'] ?? null" :step="$campo['step'] ?? null"
                        :rows="$campo['filas'] ?? null" autocomplete="off" />
        @endforeach
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="formModal">Cancelar</x-ui.button>
        <x-ui.button variant="create" size="nav" type="submit" form="catalogoForm" icon="fa-floppy-disk" data-catalogo-guardar>Guardar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>

<x-ui.modal-base id="deleteModal" title="Confirmar eliminación" size="sm" tone="danger" :close-on-backdrop="true">
    <div class="flex items-center gap-4">
        <i class="fas fa-exclamation-triangle text-red-500 text-4xl" aria-hidden="true"></i>
        <div>
            <p class="text-gray-700">{{ $catalogo['textos']['confirmar'] }}</p>
            <p class="text-gray-500 text-sm mt-1">Esta acción no se puede deshacer.</p>
        </div>
    </div>
    <x-slot:footer>
        <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="deleteModal">Cancelar</x-ui.button>
        <x-ui.button variant="delete" size="nav" icon="fa-trash" data-catalogo-confirmar-eliminar>Eliminar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
@endsection

@push('scripts')
    @vite('resources/js/modulos/catalogos-atadores/index.ts')
@endpush
