# Auditoría UI: módulo programa-tejido (2026-04)

## Inventario

- **Vistas Blade** (`resources/views/modulos/programa-tejido/`): tabla principal `req-programa-tejido.blade.php`, balanceo `balancear.blade.php`, modales agrupados (`modal/duplicar-dividir.blade.php`, `_dividir.blade.php`, `_duplicar-vincular.blade.php`, `repaso.blade.php`, `act-calendarios.blade.php`), scripts por concern (`scripts/main.blade.php`, `columns.blade.php`, `filters.blade.php`, `state.blade.php`, `selection.blade.php`, `inline-edit.blade.php`, `draganddrop/drag-and-drop.blade.php`), `_shared-helpers.blade.php`.
- **Componentes**: `resources/views/components/programa-tejido/req-programa-tejido-line-table.blade.php`, `empty-state.blade.php`.
- **JS**: imports en `app.js` / `app-core.js` hacia `resources/js/programa-tejido/` (p. ej. `store.js`, `filter-engine.js`, `modal-cache-bootstrap.js`).

## Duplicación / oportunidades

1. **Modales duplicar/dividir**: `duplicar-dividir.blade.php` incluye parciales; mantener campos técnicos y totales en `_shared-helpers` o un único partial para no divergir del backend (`registros_datos`, `ord_compartida`).
2. **Scripts**: la carpeta `scripts/` reparte responsabilidades; cualquier nuevo modal debería reutilizar `state.blade.php` + patrones de respuesta JSON ya usados en operaciones (éxito con `registros_ids`).
3. **Tabla de líneas**: componente `req-programa-tejido-line-table` es el punto único para filas de `ReqProgramaTejidoLine`; evitar copiar markup en otras vistas.

## Recomendación

Refactors grandes de Blade/JS convienen **después** de estabilizar contratos JSON en PHP (ya alineados en parte con `TejidoHelpers::calcularFormulasEficienciaPorContexto` y helpers de secuencia). Prioridad siguiente: extraer fragmentos Blade repetidos entre `_dividir` y `_duplicar-vincular` si el equipo confirma mismo diseño UX.
