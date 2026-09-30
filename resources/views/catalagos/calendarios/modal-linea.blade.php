{{-- Alta / edición de una línea de calendario (antes dos Swal: modal-agregar-linea y modal-editar-linea). --}}
<x-ui.modal-base id="modalLinea" title="Agregar Nueva Línea de Calendario" size="md" :close-on-backdrop="true">
    <form id="formLinea" class="space-y-3" novalidate>
        <input type="hidden" name="__id" value="">
        <x-ui.field as="input" id="linea-calendario" name="CalendarioId" label="No Calendario" placeholder="Ej: CAL011" autocomplete="off" required />
        <x-ui.field as="datetime-local" id="linea-inicio" name="FechaInicio" label="Inicio (Fecha Hora)" required />
        <x-ui.field as="datetime-local" id="linea-fin" name="FechaFin" label="Fin (Fecha Hora)" required />
        <x-ui.field as="number" id="linea-horas" name="HorasTurno" label="Horas" step="0.1" min="0" placeholder="8.0" required />
        <x-ui.field as="select" id="linea-turno" name="Turno" label="Turno" required>
            <option value="1">Turno 1</option>
            <option value="2">Turno 2</option>
            <option value="3">Turno 3</option>
        </x-ui.field>
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="modalLinea">Cancelar</x-ui.button>
        <x-ui.button variant="create" size="nav" type="submit" form="formLinea" icon="fa-floppy-disk" data-guardar-linea>Agregar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
