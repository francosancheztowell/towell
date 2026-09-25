---
phase: 04-ux-grid
plan: "04-perf"
wave: 2
depends_on: [02-containment-read]
autonomous: false
branch: claude/pt-04-perf
files_modified:
  - resources/js/programa-tejido/index.js
  - resources/js/programa-tejido/{balancear,modales,recalcular,seleccion}.* (nuevos)
  - resources/views/modulos/programa-tejido/{req-programa-tejido,balancear}.blade.php
  - resources/views/modulos/programa-tejido/scripts/main.blade.php
  - resources/views/modulos/programa-tejido/modal/{act-calendarios,repaso,marbetes}.blade.php
  - resources/views/components/navbar/sections/programa-tejido.blade.php
  - resources/views/components/navbar/navbar.blade.php (1 línea)
  - tests/Feature/Planeacion/ProgramaTejidoPermisosUiTest.php (nuevo)
  - tests/Js/programa-tejido-*.test.mjs
  - scripts/ratchet-baseline.json (solo bajar)
  - .planning/phases/04-ux-grid/{medir-grilla.php,04-perf-SUMMARY.md}
must_haves:
  truths:
    - "Cada corte tiene número antes/después medido con el mismo comando (medir-grilla.php)."
    - "Cero cambio de comportamiento y mismo diseño visual (D-E) en Programa y Muestras."
    - "En Muestras, navbar y menú contextual siguen los permisos del módulo Muestras (idrol 5)."
    - "El ratchet no sube; '<script> inline en blade', 'toastr.', 'fetch(' y 'X-CSRF-TOKEN' bajan."
---

<objective>
Terminar los cortes de rendimiento de la grilla que quedaban en 04-PERF-MEDIDO.md, con
números, y cerrar los pedidos B1–B3 del HANDOFF de PT-02.
</objective>

## Lo que el código dice hoy (leído antes de planear)

04-PERF-MEDIDO.md dice "pendientes los cortes 4–7", pero el commit `165dc37` ya aplicó
tres de ellos sin actualizar el documento:

| Corte | Estado real | Evidencia |
|---|---|---|
| 4 · `Promise.all` en duplicar-dividir | **Hecho** | `index.js` `duplicarTelar`: `Promise.all([detallePendiente, resolverGrupoOrdCompartida()])` y el modal ni espera la red si la fila no trae `OrdCompartida` |
| 5 · sacar el bloque de 539 KB | **Hecho a medias** | El cuerpo vive en `resources/js/programa-tejido/index.js` (bundle Vite, 270 KB / 64 KB gzip). Pero siguen **10 `<script>` inline, 144 KB** en la página: `balancear` 73 KB (el nuevo "bloque grande"), `PT_BOOT` 9 KB, 3 modales PT 21 KB, recalcular 1 KB, + 3 que no son de PT (líneas 19 KB, redbooth 18 KB, liberar 2 KB) |
| 6 · click delegado | **Hecho a medias** | `bindRowSelectionOnce()` delega en tbody y no hay `assignClickEvents`. Pero `window.selectedRowIndex` sigue siendo un **índice congelado**: tras insertar, borrar otra fila o arrastrar, apunta a otra fila y Editar/Eliminar/Ver líneas actúan sobre ella |
| 7 · no emitir ocultas | Pendiente | decisión con números abajo |

## Medición ANTES (sqlite sintético, `medir-grilla.php --sintetico`)

85 filas (SMIT 50 / JACQUARD 28 / KARL MAYER 7, 38 telares), **116** columnas (hoy
`getTableColumns()` devuelve 116, no las 92 de la medición de septiembre), usuario con 59 ocultas.

| Medida | Programa |
|---|---|
| HTML | 1 184 KB plano / 125 KB gzip |
| tbody | 940 KB, de ellos 493 KB (52 %) en celdas ocultas |
| JS inline | 10 bloques, 144 KB (mayor: balancear 73 KB) |
| Render Blade (mejor de 5) | ~185 ms |

## Tareas

1. **Runbook de medición** — `.planning/phases/04-ux-grid/medir-grilla.php`: `--sintetico` (sqlite en
   memoria) o `--usuario=N [--muestras]` (Laragon, solo lectura). Desglosa HTML/gzip, tbody, bytes en
   celdas ocultas, JS inline por bloque, render y bundle Vite. Además `--sin-ocultas` simula el corte 7
   (render quitando las columnas ocultas) para decidirlo con números.
2. **Corte 5 (resto)** — mover a módulos importados desde `index.js` (sin tocar `vite.config.js`):
   `balancear` → `balancear.js` (y toastr → `notify`, fetch → `http`), recalcular fechas →
   `recalcular.js` (fetch → `http`), scripts de `modal/{act-calendarios,repaso,marbetes}` → `modales/*.js`.
   Lo que dependía de Blade (rutas de marbetes/recalcular) pasa a `PT_BOOT.routes`. `PT_BOOT` sale de
   `<script>` ejecutable a `<script type="application/json" id="pt-boot">`. `balancear.blade.php` queda
   como vista vacía documentada (la ruta GET `balancear` sigue devolviendo 200; su contenido era solo
   ese `<script>` suelto). `modal/redbooth.blade.php` **no se toca** (15-02).
   Orden de ejecución: los inline corrían antes que el bundle; los módulos importados se evalúan antes
   que el cuerpo de `index.js`, así que las funciones `window.*` que publican siguen existiendo cuando
   `index.js` las busca. Test: `programa-tejido-bundle.test.mjs` verifica que siguen publicadas.
3. **Corte 6 (resto)** — `window.selectedRowIndex` pasa a ser un accesor sobre la **fila** seleccionada
   (`seleccion.ts`): lee `allRows.indexOf(fila)` al momento, -1 si la fila ya no está en el DOM. Los ~20
   consumidores no cambian. Test JS: seleccionar, insertar antes / borrar antes / reordenar → el índice
   sigue apuntando a la misma fila.
4. **Corte 4** — solo verificación y nota en el SUMMARY (ya hecho).
5. **Corte 7** — no se hace si los números no lo justifican: criterio del documento ("solo si no
   alcanzan") medido con `--sin-ocultas` en gzip y ms de render; la justificación va en el SUMMARY.
6. **HANDOFF B1** — `@if(ProgramaTejidoSurface::actual()->soporta('descarga'))` alrededor de
   "Descargar programa" en `navbar/sections/programa-tejido.blade.php`; borrar el ocultamiento por JS
   en `index.js`. **B2** — `navbar.blade.php:9`: `$isMuestras ? 'Muestras' : 'Programa Tejido'`.
   **B3** — `$moduloPT = $superficie->moduloPermiso()` en el menú contextual.
   Test feature: en Muestras, con permisos solo de Muestras, el navbar y el menú contextual muestran
   Crear/Editar/Eliminar; con permisos solo de Programa, no; y Descargar no aparece en Muestras.
7. **Medición DESPUÉS**, capturas antes/después (skill `run`, Programa y Muestras), checks del
   protocolo, `code-review`, `04-perf-SUMMARY.md` y actualización de 04-PERF-MEDIDO.md (estado de
   los cortes). Push a `claude/pt-04-perf`, sin PR.

## Fuera de alcance

`modal/redbooth.blade.php` (15-02), `components/ui/**` (16), `components/programa-tejido/req-programa-tejido-line-table.blade.php`
(no está en la fila PT: su `<script>` de 19 KB queda como pendiente en el SUMMARY), el `mostrarModalDiasLiberar` inline de
`navbar.blade.php` (solo 1 línea de ese archivo es de PT), `vite.config.js`, `package.json`, `resources/js/utils/**`.
