---
phase: 04-ux-grid
plan: "PT-TS-1"
branch: claude/pt-ts-1
base: claude/friendly-hopper-506bg9 (+ merge de origin/main)
status: completo
fecha: 2026-09-30
ids: [PT-TS-01, SEC-07, UX-18]
---

# PT-TS 1 — Programa Tejido: JS → TS (primera parte) · SUMMARY

Plan: `PT-TS-1-PLAN.md` (aprobado por el owner el 2026-09-30, con las decisiones de borrar
`programa-tejido-menu.js` y `modal-cache-bootstrap.js`). Pedidos a otros: `HANDOFF.md` (sección PT-TS 1).

## 1. Qué se hizo

Ninguna pantalla de PT fuera de la grilla tiene ya JavaScript: ni `<script>` inline ni `.js`. Queda
solo `programa-tejido/index.js` (PT-TS 2).

| Commit | Pantalla / pieza | Antes | Después |
|---|---|---|---|
| `94911ae` | Plan | — | `PT-TS-1-PLAN.md` |
| `afc42bf`, `fbde0b5` | Arnés | — | `arnes-pt-ts/` (semilla, `urls.txt`, `shoot.ts` con viewport) |
| `1568180` | `rutaSuperficie()` | regla solo en el parche de `fetch` de `index.js` | `programa-tejido/rutas.ts` + test de paridad contra ese parche |
| `79f2b35` | Modales calendarios, repaso, marbetes + recalcular fechas | 4 `.js` (615 líneas) | `.ts`; 3 `onclick` fuera de sus Blade |
| `02bd5de` | Tabla y modal de líneas diarias | `lineas.js` 426 | `lineas.ts` + `lineas-logica.ts` |
| `55a15a8` | Balanceo de órdenes compartidas | `balancear.js` 1 733 | `balancear.ts` + `balancear-logica.ts` |
| `779ca93` | Modal de Redbooth (PT, Trazabilidad, CatCodificación) | `modal.js` 319 + `modal.d.ts` + `<script type="application/json">` | `modal.ts` + `logica.ts`; boot en `data-redbooth-boot` |
| `833e33d` | Código muerto | `public/js/programa-tejido-menu.js` 175, `modal-cache-bootstrap.js` 7 | borrados (+ 3 líneas del layout, 1 de `app-core.js`) |
| `dcf7549` | Tests del bundle | 5 `.test.mjs` | `.test.ts` con tipos (compilan en `strict`) |
| `1176780` | **Liberar Órdenes** | Blade 2 688 líneas (~1 900 de `<script>`), 9 `on*=` | Blade 806, 0 `<script>`, 0 `on*=`; `modulos/programa-tejido/liberar-ordenes/**` |
| `6643b8e` | **Utilería** (Mover y Finalizar) | 3 Blade 1 219 líneas, 2 `<script>`, 12 `on*=` + los que armaba el JS por fila | 329 líneas, 0 / 0; `modulos/programa-tejido/utileria/**` |
| `1e144dd` | **Alineación** | `_script.blade.php` 422 (datos incrustados en JS) | borrado; `modulos/programa-tejido/alineacion/**` |
| `9f32016` | SEC-07 + guardián | 3 `getMessage()` al usuario en `LiberarOrdenesController` | `report()` + mensaje genérico con referencia; `ProgramaTejidoSinJsInlineTest` |
| `ed049d3` | Arreglos de la revisión de código | — | ver §3 |

Estructura final (receta 19-00): lógica pura en `logica.ts` / `*-logica.ts` con tests `node --test`;
`index.ts` solo cablea; datos del servidor en `data-*='@json($var)'`; `@vite` por `@push('scripts')`.

- **Puentes `window`** (todos marcados `// PUENTE PT-TS 1`): `abrirModal{ActCalendarios,Repaso,Marbetes}`
  y `cerrarModal*` (index.js y el `onclose` de `x-ui.modal-base`), `loadReqProgramaTejidoLines`,
  `openLinesModal`, `verDetallesGrupoBalanceo`, `abrirBalancearDesdeSeleccion` (onclick del navbar de la
  grilla), `abrirModalRedboothProgramaTejido`. Solo depuración: `aplicarBalanceoAutomatico`,
  `recargarGanttOrdCompartida`. Dejan de ser globales: `guardarMarbetesEnviar`, `crearRepasoEnviar`,
  `guardarCalendariosSeleccionados`, los ~40 de Liberar/Utilería/Alineación.
- **SweetAlert2 que se queda** (modales con formulario, tabla o Gantt propios; mismo diseño):
  balanceo (modal + carga + guardado + error con color), "Detalle del Telar", aviso de recalcular fechas,
  aviso de vínculo eliminado de Redbooth, fijar/ocultar/filtros/filtro Excel y confirmación de Liberar,
  filtro y fijar de Alineación, y un helper `aviso()` en Utilería (colores por pantalla). Toasts,
  alertas simples y confirmaciones → `notify.*`.

## 2. Números

**Ratchet** (`scripts/ratchet-baseline.json`, fijado con `--update`):

| Métrica | Antes | Después |
|---|---|---|
| `fetch(` | 154 | **126** |
| `Swal.fire` | 339 | **308** |
| `onclick=` | 201 | **179** |
| `innerHTML =` | 228 | **176** |
| `X-CSRF-TOKEN` | 84 | **69** |
| `<script> inline en blade` | 79 | **74** |
| `getMessage() en response()->json` | 120 | **118** |
| duplicación % | 6.82 | **6.80** |

**Tests:** `php artisan test` 2 482 ✓ (incluye `LiberarOrdenesLiberarTest`, `Liberar*Test`,
`MoverOrdenesFechaFinalizaTest`, `PlaneacionUtileriaRouteContractTest`, `Alineacion*Test` **sin cambios**,
más `LiberarOrdenesSec07Test` y `ProgramaTejidoSinJsInlineTest` nuevos); `npm run test:js` 445 ✓ (+8
archivos de PT: rutas, líneas, balanceo, Liberar, Utilería, Alineación, Redbooth); `npm run typecheck`,
`npm run build`, `phpstan` (sin errores), `composer quality` (pint, phpstan, phpmd sin violaciones nuevas,
ratchet) en verde. Los tests de PT también compilan con `strict` si el `tsconfig` incluye `tests/`
(verificado con un tsconfig temporal).

**Liberar:** el payload del POST de liberar es idéntico antes/después (capturado en el arnés).

## 3. Hallazgos

| # | Hallazgo | Qué se hizo |
|---|---|---|
| **H1** | **Liberar Órdenes en Muestras postea a las rutas de Programa** (`programa-tejido.liberar-ordenes.procesar`, `.bom`, `.tipo-hilo`…): la superficie sale del path, así que desde Muestras se libera contra la tabla de Programa. Con las rutas de Muestras el guard R6 de PT 05 respondería 422 (falta el DDL de marbetes) | **No se cambió** (cambia a qué tabla se escribe). Decisión del owner (HANDOFF B5) |
| H2 | Balanceo: con la producción del último telar por encima de lo que le toca del total, `calcularTotalesYFechas` se llamaba a sí misma sin fin (`Maximum call stack size exceeded` al salir de un pedido). Reproducido también en el "antes" | Corregido: si el último telar ya está en su mínimo, no se reintenta y se marca el descuadre |
| H3 | Redbooth: al desenvolver una etiqueta no permitida (`<h2>`, `<b>`) junto a una imagen, la limpieza volvía a procesar la imagen ya reescrita y la borraba (también en `modal.js`) | Corregido (cada nodo se limpia una vez); verificado en el navegador |
| H4 | Mover: con cambios sin guardar, el select se quedaba en el telar nuevo mientras el panel y "Guardar" usaban el anterior | Corregido: el select vuelve al telar en pantalla |
| H5 | Liberar: el formateador de celdas devolvía el texto libre (producto, descripción, Clave AX…) crudo a `{!! !!}` | Se escapa |
| H6 | Liberar: el menú contextual del encabezado medía su tamaño antes de mostrarse, así que nunca se volteaba en el borde | Se mide ya visible |
| H7 | Repaso: desde 04-perf `agregarRegistroSinRecargar` no es alcanzable (scope de `index.js`); el repaso se crea sin insertarse en la grilla | Igual que antes; HANDOFF B2 (PT-TS 2) |
| H8 | `programa-tejido-menu.js` muerto y `modal-cache-bootstrap.js` duplicado | Borrados (decisión 1 del plan) |
| H9 | Alineación: el menú del encabezado era solo de clic derecho (no abría en tablet) | `accionesTactiles` |
| H10 | 4 funciones de Liberar que nadie llamaba (`convertirHiloAXaSelect`, `syncBomOnSelect`, `applyBomOption`, `applyFilters`) | Borradas |

Cambios visibles (todos pequeños y buscados): texto mínimo 12 px en los modales migrados (antes 9–11 px),
toasts con la duración y posición únicas de `notify`, `aria-label` en botones de ícono, color del botón
"Sí, cerrar" de Mover (el de `notify.confirm`). El resto de las capturas antes/después son iguales píxel a
píxel salvo el antialias del título del navbar.

## 4. UX-18 por pantalla

| Pantalla | 1.1 title | 1.2 h1 | 2.1 táctil | 2.5 ≥12 px | 3.1 aria-label | 4.2 notify | 4.3 419 | Notas |
|---|---|---|---|---|---|---|---|---|
| Liberar Órdenes | ✅ | ✅ | ✅ (encabezado: `accionesTactiles`) | ✅ modales; celdas sin cambio | ✅ | ✅ | ✅ `http` | Badge "Decide flog" 10 → 12 px |
| Utilería (Mover/Finalizar) | ✅ | ✅ | n/a (arrastrar ya funciona con mouse; táctil pendiente de SortableJS en ADOP) | ✅ | ✅ | ✅ | ✅ | |
| Alineación | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | |
| Modales de la grilla (líneas, balanceo, repaso, marbetes, calendarios, Redbooth) | n/a | n/a | n/a | ✅ salvo celdas del Gantt (9–10 px en CSS, van con PT-TS 2) | ✅ | ✅ | ✅ | |

## 5. Evidencia

- Arnés: `.planning/phases/04-ux-grid/arnes-pt-ts/` (README). Comparación controlada: base reiniciada
  antes de cada corrida, "antes" desde un worktree de `43e0f09` con su propio build.
- Capturas en `capturas/pt-ts-1/{antes,despues}-{768x1024,1280x800}/` (16 pantallas/modales cada una; PNG reducidos a 128 colores para el repo; la comparación píxel a píxel se hizo sobre los originales) y
  `reporte.json` (status y errores de consola: 0 errores de JS; los 4xx/5xx que aparecen vienen del
  arnés sqlite y se ven igual en "antes").
- Flujos probados en el navegador: Liberar (fijar, ocultar, filtros de texto y Excel, peso de rollo,
  seleccionar todo, validaciones, POST con 422 y con éxito); Mover (arrastrar entre telares, reordenar,
  guardar, cerrar con cambios, cambiar de telar con cambios); Finalizar (telar, selección, confirmar);
  Alineación (fila, fijar, filtrar); balanceo (editar pedido, total, automático, guardar); líneas, repaso,
  marbetes, calendarios, Redbooth vinculado (HTML hostil incluido) en Programa y Muestras.

## 6. Cómo desplegar

Solo front + un controller: `npm run build` y `php artisan view:clear`. Sin migraciones ni `.env`.
