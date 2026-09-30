{{-- Carga de Excel de calendarios o de líneas (antes dos modales iguales). REEMPLAZA todo lo cargado. --}}
<x-ui.modal-base id="modalExcelCalendario" title="Subir Excel de Calendarios" size="md" :close-on-backdrop="true">
    <form id="formExcelCalendario" class="space-y-3" novalidate>
        <input type="hidden" name="tipo" value="calendarios">
        <p class="text-sm text-gray-600" data-excel-ayuda></p>
        <label for="excel-calendario-archivo" class="block cursor-pointer rounded-lg border-2 border-dashed border-gray-300 p-6 text-center hover:border-green-500">
            <i class="fas fa-file-excel mb-2 text-4xl text-green-600" aria-hidden="true"></i>
            <span class="block text-sm font-medium text-gray-900">Arrastra o haz click para seleccionar</span>
            <input type="file" id="excel-calendario-archivo" name="archivo_excel" accept=".xlsx,.xls" class="sr-only" required>
        </label>
        <p class="text-sm font-medium text-gray-900" data-excel-nombre hidden></p>
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="modalExcelCalendario">Cancelar</x-ui.button>
        <x-ui.button variant="create" size="nav" type="submit" form="formExcelCalendario" icon="fa-upload">Procesar Excel</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
