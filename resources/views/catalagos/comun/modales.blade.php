{{--
    Modales de los catálogos de Planeación (19-06b): formulario de alta/edición, confirmación de
    borrado y filtros. Lo que cambia por catálogo llega en $catalogo
    (App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatalogosPlaneacionVista). El comportamiento
    está en resources/js/modulos/catalogos-planeacion/comun/catalogo.ts.
--}}
<x-ui.modal-base id="formModal" :title="$catalogo['textos']['nuevo']" size="lg" :close-on-backdrop="true">
    <form id="catalogoForm" class="grid grid-cols-1 gap-3 sm:grid-cols-2" novalidate>
        <input type="hidden" name="__original" value="">
        @foreach ($catalogo['campos'] as $campo)
            @php
                $idCampo = 'campo-'.$campo['nombre'];
                $ancho = ($campo['ancho'] ?? null) === 'completo' ? 'sm:col-span-2' : '';
            @endphp
            <div class="{{ $ancho }}">
                @if ($campo['tipo'] === 'select')
                    <x-ui.field as="select" :id="$idCampo" :name="$campo['nombre']" :label="$campo['etiqueta']"
                                :required="$campo['requerido'] ?? false" :data-depende="$campo['depende'] ?? null"
                                :data-por-defecto="$campo['porDefecto'] ?? null">
                        @unless (isset($campo['porDefecto']))
                            <option value="">Seleccionar</option>
                        @endunless
                        @foreach ($campo['opciones'] ?? [] as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </x-ui.field>
                @elseif ($campo['tipo'] === 'range')
                    <x-ui.field :id="$idCampo" :name="$campo['nombre']" :label="$campo['etiqueta']" :required="$campo['requerido'] ?? false">
                        <div class="flex items-center gap-3">
                            <input id="{{ $idCampo }}" name="{{ $campo['nombre'] }}" type="range"
                                   min="{{ $campo['min'] ?? 0 }}" max="{{ $campo['max'] ?? 100 }}" step="{{ $campo['step'] ?? 1 }}"
                                   value="{{ $campo['porDefecto'] ?? 0 }}" data-por-defecto="{{ $campo['porDefecto'] ?? 0 }}"
                                   class="min-h-touch flex-1 cursor-pointer accent-blue-500">
                            <output for="{{ $idCampo }}" data-salida-rango="{{ $campo['nombre'] }}" data-sufijo="{{ $campo['sufijo'] ?? '' }}"
                                    class="w-14 text-right font-bold text-blue-600">{{ $campo['porDefecto'] ?? 0 }}{{ $campo['sufijo'] ?? '' }}</output>
                        </div>
                    </x-ui.field>
                @else
                    <x-ui.field :as="$campo['tipo']" :id="$idCampo" :name="$campo['nombre']" :label="$campo['etiqueta']"
                                :required="$campo['requerido'] ?? false" :placeholder="$campo['placeholder'] ?? null"
                                :maxlength="$campo['maxlength'] ?? null" :min="$campo['min'] ?? null" :max="$campo['max'] ?? null"
                                :step="$campo['step'] ?? null" autocomplete="off" />
                @endif
            </div>
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
            <p class="text-gray-500 text-sm mt-1" data-catalogo-resumen-eliminar></p>
            <p class="text-gray-500 text-sm mt-1">Esta acción no se puede deshacer.</p>
        </div>
    </div>
    <x-slot:footer>
        <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="deleteModal">Cancelar</x-ui.button>
        <x-ui.button variant="delete" size="nav" icon="fa-trash" data-catalogo-confirmar-eliminar>Eliminar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>

@if (! empty($catalogo['filtros']))
    <x-ui.modal-base id="filtrosModal" :title="$catalogo['textos']['filtrar']" size="lg" :close-on-backdrop="true">
        <form id="catalogoFiltrosForm" class="grid grid-cols-1 gap-3 sm:grid-cols-2" novalidate>
            @foreach ($catalogo['filtros'] as $filtro)
                @php $idFiltro = 'filtro-'.$filtro['nombre']; @endphp
                @if (($filtro['control'] ?? 'text') === 'select')
                    <x-ui.field as="select" :id="$idFiltro" :name="$filtro['nombre']" :label="$filtro['etiqueta']"
                                :data-depende="$filtro['depende'] ?? null">
                        <option value="">Todos</option>
                        @foreach ($filtro['opciones'] ?? [] as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </x-ui.field>
                @else
                    <x-ui.field :as="$filtro['control'] ?? 'text'" :id="$idFiltro" :name="$filtro['nombre']" :label="$filtro['etiqueta']"
                                :placeholder="$filtro['placeholder'] ?? null" :min="($filtro['control'] ?? '') === 'number' ? 0 : null"
                                :step="($filtro['control'] ?? '') === 'number' ? 'any' : null" autocomplete="off" />
                @endif
            @endforeach
            <p class="sm:col-span-2 rounded bg-blue-50 p-2 text-caption text-gray-600">
                <i class="fas fa-info-circle mr-1" aria-hidden="true"></i>Deja campos vacíos para no aplicar filtro.
            </p>
        </form>
        <x-slot:footer>
            <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="filtrosModal">Cancelar</x-ui.button>
            <x-ui.button variant="create" size="nav" type="submit" form="catalogoFiltrosForm" icon="fa-filter">Filtrar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal-base>
@endif
