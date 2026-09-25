{{--
  Bootstrap del JS de Programa Tejido.

  El código vive en resources/js/programa-tejido/ (bundle de Vite con hash: sale de
  cache y V8 reusa el bytecode). Aquí solo quedan los valores que conoce el servidor,
  como datos: resources/js/programa-tejido/boot.ts los lee de #pt-boot. `capacidades`
  (config planeacion.superficies): false = acción B oculta.
--}}
@php
  $ptBoot = [
    'basePath' => $basePath ?? '/planeacion/programa-tejido',
    'apiPath' => $apiPath ?? '/programa-tejido',
    'linePath' => $linePath ?? '/planeacion/req-programa-tejido-line',
    'columns' => $columns ?? [],
    'hiddenFields' => $hiddenFields ?? [],
    'capacidades' => (object) ($capacidades ?? []),
    'routes' => [
        'codificacion' => route('planeacion.codificacion.index'),
        'codificacionModelos' => route('planeacion.catalogos.codificacion-modelos'),
        'vincularRegistros' => route('programa-tejido.vincular-registros-existentes'),
        'marbetes' => route('programa-tejido.marbetes'),
        'marbetesGuardar' => route('programa-tejido.marbetes.guardar'),
        'recalcularFechas' => ($isMuestras ?? false)
            ? route('muestras.recalcular-fechas')
            : route('programa-tejido.recalcular-fechas'),
    ],
];
@endphp
<script type="application/json" id="pt-boot">@json($ptBoot)</script>
@vite('resources/js/programa-tejido/index.js')
