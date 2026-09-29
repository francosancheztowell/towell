{{--
  Modal "Consultar en rango" de los reportes de Urdido y Engomado (19-01). Reemplaza el
  diálogo SweetAlert con dos fechas que cada reporte tenía copiado.

  @param string      $ruta       URL del reporte (route(...)); la consulta navega a ruta?fecha_ini&fecha_fin[&solo_finalizados]
  @param string      $titulo     Título del modal (default 'Consultar en rango')
  @param string|null $checkbox   Etiqueta del filtro solo_finalizados ('Solo finalizados', 'Solo terminados/autorizados')
                                 o null si el reporte no lo tiene (Control Merma)
  Usa del padre: $fechaIni, $fechaFin, $soloFinalizados (default true).
  El botón que lo abre: data-ui-modal-open="modalReporteRango". Se abre solo si faltan fechas.
  JS: resources/js/modulos/urdido/comun/reporte-rango/index.ts
--}}
@php
    $hoy = now()->toDateString();
    $configRango = [
        'ruta' => $ruta,
        'conCheckbox' => filled($checkbox ?? null),
        'fechaIni' => (string) ($fechaIni ?? ''),
        'fechaFin' => (string) ($fechaFin ?? ''),
    ];
@endphp
<x-ui.modal-base id="modalReporteRango" :title="$titulo ?? 'Consultar en rango'" size="md">
    <form id="formReporteRango" method="GET" action="{{ $ruta }}" class="text-left space-y-4" novalidate
          data-reporte-rango='@json($configRango)'>
        <x-ui.field as="date" name="fecha_ini" id="rr_fecha_ini" label="Fecha inicial" required
                    :value="! empty($fechaIni) ? $fechaIni : $hoy" />
        <x-ui.field as="date" name="fecha_fin" id="rr_fecha_fin" label="Fecha final" required
                    :value="! empty($fechaFin) ? $fechaFin : $hoy" />
        @if (filled($checkbox ?? null))
            <div class="flex items-center gap-2">
                <input type="checkbox" id="rr_solo_finalizados" name="solo_finalizados" value="1"
                       @checked($soloFinalizados ?? true) class="rounded border-gray-300 text-blue-600">
                <label for="rr_solo_finalizados" class="text-sm text-gray-700">{{ $checkbox }}</label>
            </div>
        @endif
    </form>

    <x-slot:footer>
        <x-ui.button variant="neutral" data-ui-modal-close-target="modalReporteRango">Cancelar</x-ui.button>
        <x-ui.button variant="create" type="submit" form="formReporteRango" icon="fa-search">Consultar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>

@once
    @push('scripts')
        @vite('resources/js/modulos/urdido/comun/reporte-rango/index.ts')
    @endpush
@endonce
