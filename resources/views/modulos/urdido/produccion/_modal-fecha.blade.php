{{-- ============================================================
     _modal-fecha.blade.php
     Sección de selección de fecha para registros de producción
     de urdido. La selección de fecha se maneja de forma inline
     dentro de cada fila de la tabla de producción (ver
     _tabla-registros.blade.php), utilizando un input[type=date]
     oculto que se activa al hacer clic en el botón de fecha.
     La lógica JavaScript correspondiente se encuentra en
     _scripts.blade.php (sección: Manejo de fecha inline).

     Este archivo existe como punto de extensión en caso de que
     se necesite agregar un modal o selector de fecha dedicado
     en el futuro.
     Variables requeridas: ninguna
     ============================================================ --}}

{{-- La selección de fecha está integrada inline en _tabla-registros.blade.php --}}
