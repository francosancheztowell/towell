{{--
  Bootstrap del JS de Programa Tejido.

  El cuerpo vivia aqui inline (527 KB que el navegador re-descargaba y
  re-compilaba en cada recarga, el 48 % de la pagina). Ahora esta en
  resources/js/programa-tejido/index.js y lo sirve Vite con hash: sale de
  cache y V8 reusa el bytecode compilado.

  Lo unico que sigue inline son los valores que solo conoce el servidor.
--}}
<script>
  window.PT_BOOT = {
    basePath: @json($basePath ?? '/planeacion/programa-tejido'),
    apiPath: @json($apiPath ?? '/programa-tejido'),
    linePath: @json($linePath ?? '/planeacion/req-programa-tejido-line'),
    columns: @json($columns ?? []),
    hiddenFields: @json($hiddenFields ?? []),
    routes: {
      codificacion: @json(route('planeacion.codificacion.index')),
      codificacionModelos: @json(route('planeacion.catalogos.codificacion-modelos')),
      vincularRegistros: @json(route('programa-tejido.vincular-registros-existentes')),
    },
  };
</script>
@vite('resources/js/programa-tejido/index.js')
