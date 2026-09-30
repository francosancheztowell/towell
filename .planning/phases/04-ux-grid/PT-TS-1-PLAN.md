---
phase: 04-ux-grid
plan: "PT-TS-1"
wave: 3 (tercera tanda)
branch: claude/pt-ts-1
base: claude/friendly-hopper-506bg9
depends_on: [04-perf, 05, 03]
autonomous: false
requirements: [PT-TS-01, SEC-07, UX-18]
---

# PT-TS 1 — Programa Tejido: JS → TS (primera parte), mismo diseño

## Objetivo

Que ninguna pantalla de PT que no sea la grilla (`index.js`, PT-TS 2) tenga JavaScript: ni `<script>` inline en
Blade ni archivos `.js`. Sin cambiar comportamiento ni diseño. Los tests PHP de caracterización de Liberar, Mover
y Finalizar no cambian.

## Inventario medido (rama base `43e0f09`)

| Archivo | Líneas | `<script>` | `on*=` | `fetch(` | `Swal.fire` | `innerHTML =` |
|---|---|---|---|---|---|---|
| `modulos/programa-tejido/liberar-ordenes/index.blade.php` | 2 688 (≈1 900 de JS inline) | 1 | 9 | 4 | 18 | 6 |
| `planeacion/utileria/mover-ordenes.blade.php` | 745 (≈610 JS) | 1 | 5 | 3 | 8 | 13 |
| `planeacion/utileria/finalizar-ordenes.blade.php` | 394 (≈287 JS) | 1 | 5 (+2 en HTML armado por JS) | 3 | 5 | 3 |
| `planeacion/utileria/index.blade.php` | 80 | 0 | 2 | 0 | 0 | 0 |
| `planeacion/alineacion/_script.blade.php` | 422 | 1 | 0 | 1 | 3 | 3 |
| `resources/js/programa-tejido/balancear.js` | 1 733 | — | 4 | 6 | 7 | 6 |
| `resources/js/programa-tejido/lineas.js` | 426 | — | 0 | 2 | 1 | 9 |
| `resources/js/programa-tejido/recalcular-fechas.js` | 34 | — | 0 | 0 | 4 | 0 |
| `resources/js/programa-tejido/modal-cache-bootstrap.js` | 7 | — | — | — | — | — |
| `resources/js/programa-tejido/modales/{act-calendarios,repaso,marbetes}.js` | 288 / 185 / 108 | — | 0 | 3 / 3 / 0 | 0 | 4 / 2 / 0 |
| `resources/js/modulos/redbooth/modal.js` (+ `modal.d.ts`) | 319 | — | 0 | 3 | 0 | 6 |
| `public/js/programa-tejido-menu.js` | 175 | — | 0 | 0 | 1 | 0 |
| `tests/Js/programa-tejido-*.test.mjs` (6) + `redbooth-boot.test.mjs` | 371 | | | | | |

Hallazgos que cambian el alcance:

1. **`public/js/programa-tejido-menu.js` es código muerto.** Busca `#layoutBtnAddMenu`/`#layoutAddMenu`, que no
   existen en ninguna vista, y sale en la primera línea; su "alta de pronósticos" apunta a
   `/planeacion/programa-tejido/alta-pronosticos`, ruta que no existe. Lo carga `layouts/app.blade.php` (3 líneas
   `@if … <script src> … @endif`) solo en Programa/Muestras. Migrarlo a TS sería mover código muerto: se **borra**
   y se quitan esas 3 líneas del layout (decisión 1).
2. **`modal-cache-bootstrap.js` está duplicado.** Solo hace `window.__PT_DEBUG = false` si no es `true`;
   `index.js:254` ya hace lo mismo antes de leerlo, y nadie más lo lee. Lo importa `app-core.js` (dueño TS-base)
   en todas las páginas. Se **borra** junto con esa línea de import (decisión 1).
3. **El parche de `window.fetch` de `index.js` reescribe las URL a la superficie (Muestras).** `balancear.js` y
   los modales dependen de él (sus URL dicen `/programa-tejido/...`). `window.http` usa axios (XHR), que el
   parche no ve: al pasar a `http` hay que reescribir la URL explícitamente. Se crea
   `resources/js/programa-tejido/rutas.ts` con `rutaSuperficie(url, boot)` (misma lógica que el parche, con
   test que compara ambas); el parche de `index.js` no se toca (PT-TS 2 lo podrá importar de ahí).
4. **Redbooth ya tiene su entrada Vite** (`modulos/redbooth/index.ts`, 15-02/PT-05 B4); solo falta `modal.js` →
   `modal.ts` (y borrar `modal.d.ts`).

## Estructura (receta 19-00 §1)

```
resources/js/programa-tejido/
  rutas.ts                      nuevo: rutaSuperficie() (puro, con test)
  balancear.ts                  ← balancear.js (tipado; lógica pura a balancear-logica.ts)
  balancear-logica.ts           parseFechaBackendALocal, repartos de pedidos, totales (tests)
  lineas.ts                     ← lineas.js
  recalcular-fechas.ts          ← recalcular-fechas.js
  modales/{act-calendarios,repaso,marbetes}.ts
resources/js/modulos/redbooth/modal.ts   ← modal.js
resources/js/modulos/programa-tejido/     (entradas Vite por glob; no se toca vite.config)
  liberar-ordenes/{index.ts, columnas.ts, calculos.ts, autocompletar.ts, liberar.ts, logica.ts}
  utileria/{index.ts, mover.ts, finalizar.ts, logica-mover.ts, logica-finalizar.ts}
  alineacion/{index.ts, logica.ts}
tests/Js/programa-tejido-*.test.ts         (los 6 actuales convertidos + nuevos por pantalla)
tests/Js/redbooth-*.test.ts
```

- `index.js` solo cambia sus 6 líneas `import './x.js'` → `'./x.ts'` (y el import de `rutas.ts` si hiciera
  falta). Su cuerpo va en PT-TS 2.
- Vistas: datos del servidor en `data-*='@json($variable)'` sobre el nodo raíz (receta §2: rutas con `route()`,
  `hilosOptions`, `columns`), `@push('scripts') @vite('resources/js/modulos/programa-tejido/<pantalla>/index.ts')`.
- `onclick=` → `data-accion` + un `delegate()` por tipo de evento en la raíz (receta §3). Las filas que hoy arma
  el JS con `onclick="toggleFinalizarRow(...)"` pasan a `data-accion` + `data-id`.
- Clic derecho de encabezados de Liberar → `accionesTactiles()` + botón (UX-18 2.1).
- `fetch` → `http` (+ `rutaSuperficie` donde aplicaba el parche); toasts y alertas `Swal.fire` → `notify.*`;
  confirmaciones → `notify.confirm`; `innerHTML` con datos → `textContent`/`replaceChildren`/`escapeHtml`.
  Se conserva `Swal.fire` solo donde es un modal con formulario/Gantt propio que cambiaría el diseño (el modal
  de balanceo y el de rango/confirmación con inputs de Liberar si los hay); cada uno se lista en el SUMMARY.
- Puentes `window.fn` solo para lo que llama otro archivo (hoy: `schedulePreview`, `loadReqProgramaTejidoLines`,
  `abrirModalActCalendarios`, `abrirModalRepaso`, `abrirModalMarbetes`, `abrirModalRedboothProgramaTejido`, que
  llaman `index.js` y Blade de la grilla), marcados `// PUENTE PT-TS 1: <quién>` y declarados en `globals.d.ts`.
- Pantallas grandes: se permite `// @ts-nocheck` solo dentro de un commit intermedio; al final todo tipado
  (`npm run typecheck` sin `@ts-nocheck` en la rama).

## Tareas (un commit por pantalla o grupo)

| # | Commit | Contenido |
|---|---|---|
| 0 | `pt-ts: plan` | Este plan. |
| 1 | `pt-ts: arnés y capturas antes` | Arnés sobre `03-frontend-shell/arnes/seed-pt.php` + 19-01 (sqlite en archivo, `php -S`, Playwright) extendido con semillas para Liberar, Utilería y Alineación. Capturas 1280×800 y 768×1024 de: grilla Programa y Muestras (modales balancear, líneas, repaso, marbetes, calendarios, Redbooth), Liberar, Utilería (modales Mover y Finalizar), Alineación. |
| 2 | `pt-ts: rutas de superficie` | `rutas.ts` + test de paridad contra el parche de `index.js`. |
| 3 | `pt-ts: modales y recalcular-fechas a TS` | `recalcular-fechas`, `act-calendarios`, `repaso`, `marbetes` → `.ts`; `http`/`notify`/`rutaSuperficie`. |
| 4 | `pt-ts: lineas a TS` | `lineas.js` → `lineas.ts`. |
| 5 | `pt-ts: balancear a TS` | `balancear.js` → `balancear.ts` + `balancear-logica.ts` con tests. |
| 6 | `pt-ts: redbooth a TS` | `modal.js` → `modal.ts`; borrar `modal.d.ts`; ajustar la aserción de ruta de `tests/Unit/TrazabilidadStructureTest.php` (1 línea, HANDOFF A). |
| 7 | `pt-ts: borrar menú y bootstrap muertos` | Hallazgos 1 y 2 (según decisión 1). |
| 8 | `pt-ts: tests del bundle a TS` | `tests/Js/programa-tejido-*.test.mjs` y `redbooth-boot.test.mjs` → `.test.ts` (mismas aserciones). |
| 9 | `pt-ts: liberar órdenes a TS` | Vista sin `<script>` ni `on*=`; `modulos/programa-tejido/liberar-ordenes/**`; tests de `logica.ts` (prioridad heredada, peso/rollos/piezas/marbetes, Karl Mayer y felpa, filtros de columnas, armado del payload de liberar). Tests PHP de Liberar intactos. |
| 10 | `pt-ts: utilería (mover y finalizar) a TS` | Idem para `utileria/**`. `FinalizarOrdenesController` no se toca salvo que el front lo exija. |
| 11 | `pt-ts: alineación a TS` | `_script.blade.php` desaparece; `alineacion/**`. |
| 12 | `pt-ts: SEC-07 de Liberar y Alineación` | Solo lo mínimo en las pantallas tocadas: los 3 `getMessage()` que llegan al usuario en `LiberarOrdenesController` (:196 vista, :671 JSON, :973 JSON) → `HandlesApiErrors` / `report()` + mensaje genérico con `trace_id`; los mensajes de negocio que el propio código escribe (p. ej. validaciones de L.Mat) se conservan. Test de que el 500 no expone el detalle. Mover/Finalizar/Alineación no mandan `getMessage()` al usuario (medido). |
| 13 | `pt-ts: guardianes` | Test PHP que renderiza Liberar, Utilería y Alineación y verifica 0 `<script>` sin `src`/no-JSON y 0 `on*=`; ratchet `--update` para fijar la baja. |
| 14 | `pt-ts: summary` | Capturas después, `PT-TS-1-SUMMARY.md`, `HANDOFF.md`, fila UX-18 por pantalla. |

Backend: fuera de lo del paso 12 no se toca (ni FormRequests ni partición de `liberar`/`finalizarOrdenes`: PT 05.1).
ORM/código largo no aplica a este plan porque no se tocan consultas ni métodos PHP largos (si el paso 12 toca
`liberar()` —506 líneas— es solo el `catch`, sin moverlo; PHPMD mide solo violaciones nuevas).

## Verificación

- `php artisan test` (incluye `LiberarOrdenesLiberarTest`, `Liberar*Test`, `MoverOrdenesFechaFinalizaTest`,
  `PlaneacionUtileriaRouteContractTest`, `ProgramaTejidoJsSyntaxTest`, `Alineacion*Test` sin cambios);
  `phpstan`; `npm run typecheck && npm run test:js && npm run build`; `npm run ratchet` (baja);
  `COMPOSER_ALLOW_SUPERUSER=1 composer quality`; `pint --test` en los PHP cambiados; skill `code-review`.
- Skill `run` con el arnés: cada pantalla abre con 0 errores de consola y su flujo principal funciona
  (Liberar: editar peso/rollos y liberar; Mover: elegir telar, reordenar, confirmar; Finalizar: marcar y
  confirmar; Alineación: carga/refresco; grilla: balancear, líneas, repaso, marbetes, calendarios, Redbooth,
  recalcular fechas) en Programa y en Muestras.
- Capturas antes/después idénticas salvo lo que cambie por UX-18 (aria-label, botón de acciones táctiles).

## Riesgos

| Riesgo | Mitigación |
|---|---|
| Rutas de Muestras al dejar el parche de `fetch` | `rutaSuperficie` con test de paridad; flujo en Muestras con la skill `run`. |
| Orden de evaluación del bundle (los módulos publican `window.*` antes que `index.js`) | Imports en el mismo orden; `programa-tejido-bundle.test.ts` evalúa el bundle completo. |
| Tamaño (≈ 5 000 líneas movidas/reescritas, > 1 500 del protocolo) | Un commit por pantalla, revisable por separado; casi todo es movimiento con tipos. Si el owner lo prefiere, p1 = pasos 2–8 (bundle de la grilla) y p2 = 9–13 (pantallas). |
| Choques con TS-base (`app-core.js`, layout, `tests/Js`) | Solo las líneas de la decisión 1; se avisa en HANDOFF. Merge de `origin/main` y de la base antes del último push. |

## Entregables

Código + tests, `PT-TS-1-PLAN.md`, `PT-TS-1-SUMMARY.md`, `HANDOFF.md` (sección PT-TS 1), capturas en
`.planning/phases/04-ux-grid/capturas/pt-ts-1/`. Push a `claude/pt-ts-1`. Sin PR.
