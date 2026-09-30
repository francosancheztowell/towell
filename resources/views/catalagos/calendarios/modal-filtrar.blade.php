{{-- Filtros por columna de las dos tablas (se acumulan; cada uno se puede quitar). --}}
<x-ui.modal-base id="modalFiltros" title="Filtrar por Columna" size="md" :close-on-backdrop="true">
    <div class="mb-4 rounded-lg bg-gray-50 p-3" data-filtros-activos hidden>
        <h4 class="mb-2 text-sm font-medium text-gray-700">Filtros Activos:</h4>
        <ul class="space-y-1" data-filtros-lista></ul>
    </div>
    <template data-filtro-plantilla>
        <li class="flex items-center justify-between rounded border bg-white p-2">
            <span class="text-caption" data-filtro-texto></span>
            <button type="button" data-quitar-filtro class="min-h-touch min-w-touch text-red-500 hover:text-red-700" aria-label="Quitar filtro">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </li>
    </template>
    <form id="formFiltros" class="space-y-3" novalidate>
        <x-ui.field as="select" id="filtro-tabla" name="tabla" label="Tabla">
            <option value="tab">Calendarios (ReqCalendarioTab)</option>
            <option value="line">Líneas de Calendario (ReqCalendarioLine)</option>
        </x-ui.field>
        <x-ui.field as="select" id="filtro-columna" name="columna" label="Columna"></x-ui.field>
        <x-ui.field as="input" id="filtro-valor" name="valor" label="Valor a buscar" placeholder="Ingresa el valor a buscar" autocomplete="off" />
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="modalFiltros">Cerrar</x-ui.button>
        <x-ui.button variant="create" size="nav" type="submit" form="formFiltros" icon="fa-filter">Agregar Filtro</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
