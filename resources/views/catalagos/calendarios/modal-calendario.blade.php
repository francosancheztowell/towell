{{--
    Alta / edición masiva de calendario con plantilla semanal de turnos (antes un Swal armado en JS).
    La tabla la pinta Blade; el comportamiento (horarios, topes de 24 h, checks) está en
    resources/js/modulos/catalogos-planeacion/calendarios/modal-calendario.ts.
--}}
@php
    $diasCalendario = ['lunes' => 'Lunes', 'martes' => 'Martes', 'miercoles' => 'Miercoles', 'jueves' => 'Jueves', 'viernes' => 'Viernes', 'sabado' => 'Sabado', 'domingo' => 'Domingo'];
    $diasGris = ['martes', 'jueves', 'sabado'];
    $celda = 'border border-gray-300 px-1 py-1 text-center';
@endphp
<x-ui.modal-base id="modalCalendario" title="Agregar calendario" size="xl" :close-on-backdrop="false" class="[&_.ui-modal-panel]:max-w-[98vw]">
    <form id="formCalendario" class="space-y-3 text-caption" novalidate>
        <div class="grid grid-cols-2 gap-2 justify-items-center">
            <label class="text-center">
                <span class="block font-semibold text-gray-700">Fecha Inicial</span>
                <input type="date" name="FechaInicial" required class="min-h-touch w-40 border-b border-gray-400 bg-transparent text-center focus:outline-none">
            </label>
            <label class="text-center">
                <span class="block font-semibold text-gray-700">Fecha Final</span>
                <input type="date" name="FechaFinal" required class="min-h-touch w-40 border-b border-gray-400 bg-transparent text-center focus:outline-none">
            </label>
        </div>
        <div class="overflow-x-auto max-h-[60vh] border border-gray-300">
            <table class="min-w-full border-collapse">
                <thead class="sticky top-0 bg-white">
                    <tr>
                        <th class="{{ $celda }} w-8"></th>
                        <th class="{{ $celda }} w-8"></th>
                        @foreach ($diasCalendario as $clave => $dia)
                            <th class="{{ $celda }} @if (in_array($clave, $diasGris)) bg-gray-100 @endif" colspan="3">
                                <input type="checkbox" class="size-4" data-check-dia="{{ $clave }}" aria-label="Activar {{ $dia }}" checked>
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="{{ $celda }}"></th>
                        <th class="{{ $celda }}"></th>
                        @foreach ($diasCalendario as $clave => $dia)
                            <th class="{{ $celda }} text-sm font-semibold @if (in_array($clave, $diasGris)) bg-gray-100 @endif" colspan="3">{{ $dia }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="{{ $celda }}"></th>
                        <th class="{{ $celda }}"></th>
                        @foreach ($diasCalendario as $clave => $dia)
                            @foreach (['Horas', 'Inicio', 'Fin'] as $sub)
                                <th class="{{ $celda }} w-16 font-semibold @if (in_array($clave, $diasGris)) bg-gray-100 @endif">{{ $sub }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @foreach ([1, 2, 3] as $turno)
                        <tr>
                            <td class="{{ $celda }}">
                                <input type="checkbox" class="size-4" data-check-turno="{{ $turno }}" aria-label="Activar turno {{ $turno }}" checked>
                            </td>
                            <td class="{{ $celda }} font-semibold text-base whitespace-nowrap">T{{ $turno }}</td>
                            @foreach ($diasCalendario as $clave => $dia)
                                @php $gris = in_array($clave, $diasGris) ? 'bg-gray-100' : ''; @endphp
                                <td class="{{ $celda }} w-16 {{ $gris }}">
                                    <input type="checkbox" class="sr-only" data-check-celda data-turno="{{ $turno }}" data-dia="{{ $clave }}" checked>
                                    <input type="number" step="0.1" min="0" max="24" data-horas data-turno="{{ $turno }}" data-dia="{{ $clave }}"
                                           class="w-full bg-transparent text-center focus:outline-none disabled:opacity-50" placeholder="Hr"
                                           aria-label="Horas turno {{ $turno }} {{ $dia }}">
                                </td>
                                <td class="{{ $celda }} w-16 {{ $gris }}" data-inicio data-turno="{{ $turno }}" data-dia="{{ $clave }}"></td>
                                <td class="{{ $celda }} w-16 {{ $gris }}" data-fin data-turno="{{ $turno }}" data-dia="{{ $clave }}"></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" size="nav" data-ui-modal-close-target="modalCalendario">Cancelar</x-ui.button>
        <x-ui.button variant="create" size="nav" type="submit" form="formCalendario" icon="fa-floppy-disk" data-guardar-calendario><span data-texto-guardar>Agregar</span></x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
