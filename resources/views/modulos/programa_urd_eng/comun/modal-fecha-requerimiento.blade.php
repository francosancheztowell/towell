{{--
    Modal "¿Cuándo se requiere el material?" de Programa Urd-Eng (19-05; antes un SweetAlert con html).
    Lo abre resources/js/modulos/programa-urd-eng/comun/modal-fecha-requerimiento.ts → pedirFechaRequerimiento(id).
    @param string $id  id del <dialog> (default 'modalFechaRequerimiento')
--}}
@php($idModal = $id ?? 'modalFechaRequerimiento')
<x-ui.modal-base :id="$idModal" title="¿Cuándo se requiere el material?" size="md" data-fecha-requerimiento>
    <p class="text-sm text-gray-500 mb-3">Selecciona la fecha y hora en que se necesita el material.</p>
    <label for="{{ $idModal }}-valor" class="mb-1 block text-sm font-medium text-gray-700">
        Fecha y hora de requerimiento <span class="text-red-500" aria-hidden="true">*</span>
    </label>
    <input type="datetime-local" id="{{ $idModal }}-valor" data-fecha-requerimiento-valor required
           aria-describedby="{{ $idModal }}-error"
           class="w-full min-h-touch rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <p id="{{ $idModal }}-error" class="mt-2 text-sm text-red-600" role="alert" data-fecha-requerimiento-error hidden></p>

    <x-slot:footer>
        <x-ui.button variant="neutral" data-ui-modal-close-target="{{ $idModal }}">Cancelar</x-ui.button>
        <x-ui.button variant="edit" data-fecha-requerimiento-confirmar>Confirmar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
