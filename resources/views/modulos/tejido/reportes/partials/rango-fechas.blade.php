{{--
  Modal de fechas de los reportes de Tejido (19-02). Reemplaza el Swal con inputs que tenían
  copiado inv-telas, promedio paros, marcas finales y RPM semanal.

  @param string      $ruta         URL del reporte (route(...)); consultar navega a ruta?fecha_ini&fecha_fin o ruta?semana
  @param string      $modo         'rango' (dos fechas, default) | 'semana' (una fecha de la semana)
  @param string      $titulo       Título del modal
  @param string      $descripcion  Texto bajo el título
  @param int|null    $maxDias      Máximo de días del rango (inv-telas: 5) o null
  @param bool        $abrirAlCargar  true cuando la página llega sin datos: se abre solo
  @param string|null $fechaIni, $fechaFin (modo rango) / $semana (modo semana): valores con que llegó la página
  El botón que lo abre: data-ui-modal-open="modalRangoTejido".
  JS: resources/js/modulos/tejido/reportes/comun/rango.ts (lo importa el index.ts de cada reporte).
--}}
@php
    $modo ??= 'rango';
    $hoy = now()->toDateString();
    $configRango = [
        'modal' => 'modalRangoTejido',
        'modo' => $modo,
        'ruta' => $ruta,
        'maxDias' => $maxDias ?? null,
        'abrirAlCargar' => (bool) ($abrirAlCargar ?? false),
    ];
@endphp
<x-ui.modal-base id="modalRangoTejido" :title="$titulo" size="md">
    <form id="formRangoTejido" method="GET" action="{{ $ruta }}" class="text-left space-y-4" novalidate
          data-rango-tejido='@json($configRango)'>
        <p class="text-sm text-gray-600">{{ $descripcion }}</p>
        @if ($modo === 'semana')
            <x-ui.field as="date" name="semana" id="rt_semana" label="Fecha de referencia" required
                        :value="! empty($semana) ? $semana : $hoy" />
            <p class="text-sm text-gray-500" data-semana-texto aria-live="polite"></p>
        @else
            <x-ui.field as="date" name="fecha_ini" id="rt_fecha_ini" label="Fecha inicial" required
                        :value="! empty($fechaIni) ? $fechaIni : $hoy" />
            <x-ui.field as="date" name="fecha_fin" id="rt_fecha_fin" label="Fecha final" required
                        :value="! empty($fechaFin) ? $fechaFin : $hoy" />
        @endif
    </form>

    <x-slot:footer>
        <x-ui.button variant="neutral" data-ui-modal-close-target="modalRangoTejido">Cancelar</x-ui.button>
        <x-ui.button variant="create" type="submit" form="formRangoTejido" icon="fa-search">Consultar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
