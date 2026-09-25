{{--
  Balanceo de órdenes compartidas.

  El JS (73 KB) vivía aquí en un <script> inline que se reenviaba y recompilaba en cada
  carga de la grilla. Ahora está en resources/js/programa-tejido/balancear.js, dentro del
  bundle de Vite de la grilla (04-perf, corte 5). La vista queda vacía porque la ruta GET
  programa-tejido.balancear / muestras.balancear la sigue devolviendo.
--}}
