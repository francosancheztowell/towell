# 16 — HANDOFF (cambios pedidos a otros dueños)

Fase 16 (rama `claude/16-componentes`). Nada de esto bloquea el merge de 16: cada punto funciona hoy y el pedido lo mejora o lo limpia.

## A. 15-02 (librerías y Vite) — `resources/js/app-core.js`, `app.js`, `vite.config.js`, `utils/notifications.ts`

| # | Archivo | Cambio | Por qué |
|---|---|---|---|
| A1 | `resources/js/app.js` | **Conservar** la línea ancla `import './componentes/index.ts';` (PROTOCOLO §5, "un import en app.js"). | Es el runtime de los componentes: sin él, `x-ui.modal-base` sigue abriendo y cerrando con la clase `hidden`, pero pierde foco, Esc, Tab y los `data-ui-modal-open`. También expone `window.loader`, que usa el piloto. |
| A2 | `vite.config.js` | Cuando entre el glob `resources/js/modulos/**/index.ts`, **quitar** la línea ancla `'resources/js/modulos/catalogos-atadores/index.ts', // fase 16…`. El glob ya la cubre. | Sin duplicar la entrada. |
| A3 | `resources/js/app-core.js` (`initNavigation`) | Cambiar `mostrarLoader`/`ocultarLoader` por `window.loader.show(LOADER_DELAY)` / `window.loader.hide()` (`resources/js/componentes/loader.ts`). | Hoy hay dos temporizadores sobre `#globalLoader`: un `window.loader.hide()` de una vista no cancela el `show` diferido de la navegación, y viceversa (hallazgo del code-review). |
| A4 | `resources/js/utils/notifications.ts` | Bajar la pila de toasts bajo el navbar: `top: calc(var(--pt-navbar-height, 64px) + .75rem)` en `.towell-toasts`. | Arriba a la derecha los toasts tapan los botones Crear/Editar/Eliminar del navbar durante 2.5–6 s. Se vio en el recorrido CRUD del piloto: Playwright no podía hacer clic en "Eliminar" con un toast encima. `x-ui.flash` ya usa ese mismo lugar. |
| A5 | `utils/notifications.ts` + SweetAlert2 (ADOP) | Cuando se quiera `showModal()` (top layer) en `x-ui.modal-base`, los toasts y Swal deben vivir también en el top layer (`popover="manual"` + `showPopover()`). | Por eso el `<dialog>` de 16 es **no modal**: con `showModal()`, SweetAlert2 y los toasts quedarían debajo e inertes. PT abre Swal/notify con sus modales abiertos. |

## B. BASE / integrador — `CLAUDE.md`

Agregar a la sección *Frontend*:
- Componentes: `resources/views/components/ui/*` (modal-base `<dialog>`, button, table, table-empty, field, badge, spinner, skeleton, alert, flash, filter-bar) + `x-empty.empty-state`. Runtime `resources/js/componentes/` (lo importa `app.js`). Receta: `docs/cerebro-towell/Arquitectura/receta-componentes.md`. Galería `/dev/ui-kit` (solo `APP_ENV=local`).
- Tokens en el `@theme` de `app.css`: `text-caption` (mínimo 12 px), `min-h-touch`/`size-touch` (44 px), `bg-primary`, `bg-accent`, `bg-danger`, `text-ink-muted`…
- El layout monta `x-ui.flash`: `redirect()->with('error'|'success'|…)` se ve sin código en la vista.

## C. Sesiones 19-xx

| # | Dueño | Pedido |
|---|---|---|
| C1 | 19-xx Planeación (catálogos `catalagos/{aplicaciones,catalagoTelares,matriz-calibres,matriz-hilos}`) | Migrar a `resources/js/catalogos/catalog-base.ts` (formularios en `x-ui.modal-base`, filas desde `<template>`), como el piloto de atadores, y **borrar** `public/js/catalogs/*.js`. `CatalogBase.js` ya tiene la cabecera de legado. |
| C2 | 19-xx de cada módulo | Duplicados que 16 no tocó a propósito: `{urd,eng,tel}-actividades-bpm`, `BPM-Urdido/BPM-Engomado/tel-bpm`, `Urdido-BPM-Line` vs `Engomado-BPM-Line`, `modal-calificar-julios` vs `-eng`, `tejido/secuencia/*` ×4. Receta §3–4: modal a mano → `x-ui.modal-base`, `thead` a mano → `x-ui.table`. Los 77 overlays con `bg-opacity-*` salen con **fondo negro sólido** en Tailwind v4, así que migrarlos también arregla eso. |
| C3 | 19-xx Atadores | Comentarios usa `Nota1` (texto libre) como llave de ruta: un `/` en la nota da 404 al editar o eliminar, porque `%2F` no llega a `{nota1}`. Ya pasaba antes del piloto. Arreglo de fondo: llave por `Id`. |

## D. PT (información; no requiere cambios)

Los modales `repaso`, `act-calendarios` y `marbetes` ahora son `<dialog>`, con la misma API (id, clase `hidden`, `onclose`). Se verificaron en `/planeacion/programa-tejido` antes y después; las capturas están en `evidencia/`. Opcional cuando PT toque esos archivos: quitar sus `keydown Escape` propios, porque el componente ya cierra con Esc ejecutando el mismo `onclose`.
