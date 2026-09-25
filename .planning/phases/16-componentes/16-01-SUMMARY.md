# 16-01 — Sistema de componentes · SUMMARY

**Rama:** `claude/16-componentes` (base `claude/friendly-hopper-506bg9` @ `c0fab09`) · **IDs:** DS-01..12 · **Fecha:** 2026-09-25
**Plan:** `16-01-PLAN.md`. Sin PR: este archivo sirve de cuerpo. Pedidos a otros dueños: `HANDOFF.md`.

## Qué se hizo

| ID | Entrega |
|---|---|
| DS-01 | Tokens en el `@theme` de `resources/css/app.css`: `text-caption` (12 px, mínimo), `touch` (44 px → `min-h-touch`/`size-touch`), colores semánticos (`primary`, `accent`, `success`, `warning`, `danger`, `info`, `surface(-muted)`, `line`, `ink(-muted)`) mapeados a la paleta actual, así que adoptarlos no cambia el aspecto. |
| DS-02 | `x-ui.modal-base` pasa a `<dialog>` con la misma API: `id` en el elemento, clase `hidden` como fuente de verdad, `onclose` en la ×, mismas props y `size`. Se agregan slot `footer`, `closeOnBackdrop`, `tone="danger"` y `aria-labelledby`. El runtime `resources/js/componentes/dialog.ts` sincroniza `open`, lleva el foco al primer control, atrapa el Tab, devuelve el foco al cerrar, cierra con Esc (ejecutando `onclose`) y agrega `data-ui-modal-open` / `data-ui-modal-close-target` y `window.uiModal`. **No modal** a propósito (sin `showModal()`): ver Decisiones. |
| DS-03 | `x-ui.table` (shell con variantes `primary`/`subtle`, encabezado fijo, cebra, `loading` → skeleton; respeta un `<tbody>` propio) y `x-ui.table-empty`. **`x-tabla` lo compone y da el mismo HTML** (captura idéntica píxel a píxel). |
| DS-04 | `x-ui.field`: label con `for`, obligatorio, `hint`, error de `$errors`/prop con `aria-describedby` + `aria-invalid`, control generado (`as=`) o propio (slot), alto de 44 px. |
| DS-05 | `x-ui.button`: variantes planas con la paleta de `x-navbar.button-*` (`create`, `edit`, `delete`, `report`, `neutral`, `ghost`), tamaños `touch`/`nav`/`icon`, `icon` de Font Awesome, `href` → `<a>`, `label` accesible. Las variantes con degradado (`primary…secondary`, `sm/md/lg`) quedan con las mismas clases. `x-navbar.button-*`: mismas clases; solo `aria-label` cuando no hay texto, iconos `aria-hidden` y `onclick` omitido si viene vacío. |
| DS-06 | `x-ui.badge` (7 tonos, icono o punto). |
| DS-07 | `x-ui.spinner`, `x-ui.skeleton` y un loader global único sin `<style>` ni `<script>` inline. `window.loader.show(ms)/hide()`. **Regresiones arregladas:** el `@keyframes spin` del global-loader pisaba el de Tailwind y desplazaba todo `animate-spin` con `translate(-50%,-50%)` (se ve en las capturas: el spinner del botón "Cargando" ahora está centrado), y se quitó el `.fa-spin`/`@keyframes fa-spin` redefinido en `layouts/app.blade.php` (FA 7 trae el suyo). |
| DS-08 | `x-empty.empty-state`: mismas props y aspecto (el uso de configuración no cambia), más icono FA y slot de acción. |
| DS-09 | `x-ui.flash` montado en `layouts/app.blade.php`: muestra `session(error|warning|success|info|status)` y `$errors`, así que "No tienes acceso a este módulo." de `UsuarioController` ya se ve. Éxito e info se cierran solos. **Anti-duplicado:** si la vista ya pinta el mismo mensaje completo (texto de un nodo o literal de JS/JSON, en cualquiera de las formas en que Blade lo escapa), no se repite; 25 vistas ya pintan su flash con Swal o alert. El HTML de la vista solo se junta cuando hay algo que mostrar. `x-ui.alert` se cierra **sin JS** (checkbox + `has-checked`), porque el login no carga `app.js`. |
| DS-10 | `x-ui.filter-bar`: modo cliente (`target`, filas `[data-filter-row]`, selects `data-ui-filter-column`) con `checkFilterMatch` de `resources/js/programa-tejido/filter-engine.ts` (import de solo lectura), conteo "N de M" y vacío; modo Livewire (`model`) sin JS. |
| DS-11 | Galería `/dev/ui-kit` (`routes/modules/dev.php` + 1 `require` en `routes/web.php`, dentro de `auth`), registrada solo con `app()->isLocal()`. Tiene todas las variantes, el código de ejemplo y los 3 parciales reales de modal de PT. Receta en `docs/cerebro-towell/Arquitectura/receta-componentes.md`: qué usar, reglas, cómo migrar un modal y una tabla, qué no hacer. |
| DS-12 | Piloto: Actividades, Comentarios y Máquinas pasan de 3 vistas (~1 250 líneas, ~80 % iguales) a `modulos/catalogos-atadores/index.blade.php` + `fila.blade.php`. La configuración vive en `App\Http\Controllers\Atadores\Catalogos\CatalogosAtadoresVista` y cada `index()` solo cambia su `return view(...)`. **Rutas, nombres, validaciones y JSON sin cambios.** `resources/js/catalogos/catalog-base.ts` (evolución de `CatalogBase.js`: URL base configurable en vez de `/planeacion/` fijo, `http`/`notify` inyectados, formularios en `<dialog>`, filas clonadas de un `<template>` sin `innerHTML`, mismos hooks) se carga desde `resources/js/modulos/catalogos-atadores/index.ts` (línea ancla en `vite.config.js`). Los otros 4 catálogos de `public/js/catalogs` siguen con la versión JS, que queda marcada como legado. |

## Archivos
- **Nuevos:** `components/ui/{badge,field,filter-bar,flash,skeleton,spinner,table,table-empty}.blade.php`, `resources/js/componentes/{index,dialog,loader,dismiss,filter-bar}.ts`, `resources/js/catalogos/catalog-base.ts`, `resources/js/modulos/catalogos-atadores/index.ts`, `app/Http/Controllers/Atadores/Catalogos/CatalogosAtadoresVista.php`, `modulos/catalogos-atadores/{index,fila}.blade.php`, `routes/modules/dev.php`, `resources/views/dev/ui-kit{,/seccion}.blade.php`, `docs/cerebro-towell/Arquitectura/receta-componentes.md`, tests (abajo), `evidencia/*.png`.
- **Modificados:** `resources/css/app.css`, `components/ui/{modal-base,button,alert}`, `components/empty/empty-state`, `components/tabla`, `components/layout/global-loader`, `components/navbar/button-{create,edit,delete,report}`, `layouts/app.blade.php` (flash, loader, `.fa-spin`), los 3 controllers del trío (solo `index()`), `public/js/catalogs/CatalogBase.js` (solo cabecera de legado), `scripts/ratchet-baseline.json`.
- **Líneas ancla:** `resources/js/app.js` (1 import, PROTOCOLO §5), `vite.config.js` (1 entrada en `input`), `routes/web.php` (1 `require`). En `routes/web.php` se corrió Pint porque el CI lo aplica a los archivos cambiados; solo toca espacios en las concatenaciones.
- **Borrados:** `modulos/catalogos-atadores/{actividades,comentarios,maquinas}/index.blade.php`.
- **Tests nuevos:** `tests/Feature/Componentes/{ComponentesUiTest,FlashTest,UiKitRouteTest}.php`, `tests/Feature/CatalogosAtadoresTest.php`, `tests/Js/{componentes-runtime,catalog-base}.test.mjs`.

## Evidencia
- `php artisan test` → **1399 passed** (20029 assertions); los 34 nuevos de PHP pasan.
- `npm run typecheck` → sin errores. `npm run test:js` → **111/111** (16 nuevos). `npm run build` → ok.
- `vendor/bin/phpstan analyse --memory-limit=2G` → **No errors**. `pint --test` sobre todos los PHP cambiados → pass.
- `npm run ratchet` → ninguna métrica sube; la baseline bajó y quedó fijada: `onclick=` 413 → **376**, `Swal.fire` 818 → **800**, `bg-opacity-` 28 → **19**, `<script>` inline 169 → **165**.
- `code-review` (high): 10 hallazgos. Se corrigieron 9 en `ba66358`: fila repintada con lo que guardó el servidor, mensaje de un `success:false`, foco tras eliminar, Tab con Swal encima y controles ocultos, costo del flash, anti-duplicado más estricto, clic en el fondo del piloto, `<tbody>` tras comentarios, `onReady`. El que queda (dos temporizadores del loader) vive en `app-core.js` → HANDOFF A3. Después pasó el agente `code-simplifier` (`f3d1bd3`).
- **Navegador** (skill `run`: Chromium headless contra `php -S` + sqlite en archivo, usuario inyectado como en los tests; el "antes" se sirvió desde un worktree de `c0fab09` con su propio build):
  - **Modales de PT en `/planeacion/programa-tejido` real** (repaso, act-calendarios, marbetes, a 1280×800 y 768×1024): abren con las funciones de PT, cierran con ×, Esc y `cerrarModal*()`, restauran `body.overflow` y no hay `pageerror`. Contra el antes: repaso idéntico píxel a píxel; en los otros dos la única diferencia es el anillo de foco en el primer control. Con un SweetAlert abierto encima del modal, sus botones funcionan y el modal sigue abierto. El Tab queda dentro del modal.
  - **Usos existentes:** `x-tabla` (catálogo de fallas) idéntico píxel a píxel en ambos tamaños; `x-ui.button`, `x-ui.alert`, `x-empty` y botones del navbar iguales salvo el spinner "Cargando", que ya no se desplaza.
  - **Piloto:** tablas de los 3 catálogos idénticas píxel a píxel antes y después. Recorrido CRUD real: crear, editar (cambiando la llave; la fila sigue seleccionada), error de duplicado con el mensaje del servidor, eliminar, y tras recargar el servidor coincide con el DOM. Comentarios funciona con acentos y espacios en la llave.
  - **Galería** a 1280×800 y 768×1024: abrir el modal enfoca el primer campo y Esc lo cierra; el filtro muestra "1 de 4" y el vacío. 0 errores de consola.
  - Capturas en `evidencia/`: `antes-despues-*` (izquierda antes, derecha después) y `galeria-*`.

## Decisiones
- **`<dialog>` no modal.** Con `showModal()` el resto de la página queda inerte y fuera del top layer: los SweetAlert2 y toasts que PT abre con el modal abierto quedarían debajo y sin poder usarse. El overlay lo pinta el propio `<dialog>` bajo el navbar, igual que el `<div>` anterior, y la clase `hidden` basta para mostrarlo aunque el runtime no haya cargado. Pasar a `showModal()` cuando notify/Swal vivan en el top layer → HANDOFF A5.
- **Runtime importado desde `app.js`** con una línea ancla (PROTOCOLO §5), en `resources/js/componentes/` (directorio nuevo del DS). Sin él, los modales no tendrían foco ni Esc.
- **Piloto:**
  - La tabla, los botones del navbar y el layout conservan su aspecto (`x-ui.table variant="subtle"`).
  - Los modales del trío adoptan el estilo de `x-ui.modal-base`. **Antes salían con fondo negro sólido**, porque `bg-opacity-50` no existe en Tailwind v4; ahora el fondo es el `rgb(0 0 0 / .4)` de siempre y no tapa el navbar.
  - La selección de fila se unifica: la de Comentarios era azul sólido con texto blanco; ahora es la de Actividades/Máquinas, sin el corrimiento de 4 px.
  - Se quitó el modal "Ver": `openViewModal` no tenía llamadores.
  - Tras guardar ya no se recarga la página: se relee el registro guardado, se pinta la fila y se avisa con `notify`.
  - Se conservan la validación nativa (`required`, `min`/`max`) y el cierre con clic en el fondo.
- **`catalog-base.ts` recibe `http` y `notify` inyectados**, así se prueba en node sin axios ni SweetAlert. `index.ts` le pasa `window.http` / `window.notify`.
- **x-ui.alert sin JS** (checkbox + `has-checked:hidden`): el login usa `x-ui.alert` y no carga `app.js`.

## Pendientes / deuda conocida
- HANDOFF A1–A5 (15-02), B (`CLAUDE.md`), C1–C3 (19-xx).
- La adopción de los componentes en el resto de las vistas es de las sesiones 19-xx (Ola 3). Quedan 77 overlays a mano, 184 tablas y 66 estados vacíos.
- `filter-engine.ts` se importa desde `resources/js/programa-tejido/`; se reubica en ADOP.
- En tableta (768 px), con 3 botones en el navbar, el título de la página se parte sobre el logo. Es del navbar (`navbar.blade.php`, PT en esta ola) y ya pasaba antes.
- Tamaño: ~2 900 líneas añadidas / 1 500 borradas fuera de `.planning`, en commits separados por parte: p1 `c683a07` (componentes, +1 348/−215), p2 `d881470` (galería y receta, +542), p3 `0012380` (piloto, +959/−1 290; −1 250 son las 3 vistas borradas). Encima van `ba66358` (code-review) y `f3d1bd3` (simplificaciones). El integrador puede partir la rama por esos commits.

## Despliegue
Solo frontend + vistas: `npm ci && npm run build`, `php artisan view:clear` (y `optimize` si se usa `route:cache`: `/dev/ui-kit` no se registra fuera de `local`). Sin migraciones ni `.env`.
