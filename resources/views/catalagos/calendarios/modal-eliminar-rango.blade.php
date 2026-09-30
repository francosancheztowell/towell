{{-- Borrado de líneas por rango de fechas y turnos del calendario seleccionado. --}}
<x-ui.modal-base id="modalRango" title="Eliminar Líneas por Rango" size="md" tone="danger" :close-on-backdrop="true">
    <form id="formRango" class="space-y-3" novalidate>
        <p class="rounded border-l-4 border-blue-400 bg-blue-50 p-3 text-sm font-semibold text-blue-800" data-rango-calendario></p>
        <x-ui.field as="date" id="rango-inicio" name="fechaInicio" label="Fecha Inicio" required />
        <x-ui.field as="date" id="rango-fin" name="fechaFin" label="Fecha Fin" required />
        <fieldset class="space-y-1 rounded-md border border-gray-300 bg-gray-50 p-3">
            <legend class="px-1 text-sm font-medium text-gray-700">Turnos a Eliminar</legend>
            @foreach ([1, 2, 3] as $turno)
                <label class="flex min-h-touch cursor-pointer items-center gap-2 rounded p-2 hover:bg-gray-100">
                    <input type="checkbox" name="turnos" value="{{ $turno }}" checked class="size-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                    <span class="text-sm font-medium text-gray-700">Turno {{ $turno }}</span>
                </label>
            @endforeach
        </fieldset>
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="modalRango">Cancelar</x-ui.button>
        <x-ui.button variant="delete" size="nav" type="submit" form="formRango" icon="fa-trash">Eliminar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
