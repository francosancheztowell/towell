<script>
  // Drag & Drop unificado en scripts/main.blade.php.
  // Este archivo queda como shim para evitar doble logica.
  if (!window.toggleDragDropMode && window.PT?.dragdrop) {
    window.toggleDragDropMode = function () {
      window.PT.dragdrop.toggle();
    };
  }
</script>
