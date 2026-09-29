---
phase: 03-frontend-shell
plan: "03"
version: 2
branch: claude/pt-03-shell-livewire
status: completo — GATE NO PASA (v2 queda apagado)
fecha: 2026-09-29
ids: [PT-UI-01, PT-ROL-01]
---

# PT 03 — Shell Livewire · SUMMARY

Plan: `03-shell-livewire-PLAN.md` (v2, aprobado por el owner el 2026-09-29). Pedidos a otros: `HANDOFF.md`.

## 0. Veredicto del GATE

**v2 no mejora ningún número; sale peor en TTFB (+56 a +62 ms, +22–24 %) y en bytes (+1,2 KB gzip por carga
y +77 KB gzip de `livewire.min.js` en la primera visita). Queda apagado (`PLANEACION_SHELL_V2=off`, el default)
y, según la regla de la fase, 04-ux no sigue sobre Livewire.** El diseño sí es idéntico (capturas iguales byte a byte).

Por qué era esperable: con el mismo diseño, la grilla la sigue pintando el mismo Blade y la sigue manejando el
mismo `programa-tejido/index.js`; Livewire no le quita bytes ni trabajo a la grilla y le suma su ciclo
(mount, snapshot con checksum, marcadores de morph en cada `@if`/`@foreach` de la grilla) y su runtime.
Los cortes que sí bajaban números ya se hicieron en Blade (04-perf: −59 % HTML, −37 % gzip).

## 1. Números v2 vs legacy

Método de `04-ux-grid/04-PERF-MEDIDO.md` / `04-perf-SUMMARY.md`, sobre la app real servida con el arnés
de 19-01 (`php -S`, 4 workers, todas las conexiones `sqlsrv*` en sqlite) + `03-frontend-shell/arnes/seed-pt.php`
(85 filas sintéticas por superficie, forma de la medición real; usuario con **59 columnas ocultas**).
Legacy y v2 corren el **mismo código y el mismo build**, en dos servidores que solo difieren en
`PLANEACION_SHELL_V2` (`off` / `on`). Chromium headless 1280×800, `medir-shell.mjs`, mediana de 20 recargas,
2 rondas alternadas. Crudos en `medicion/`.

### Programa Tejido

| Métrica | Legacy (r1 / r2) | v2 (r1 / r2) | Diferencia |
|---|---|---|---|
| TTFB servidor (curl, mediana de 30) | **253 ms** | 315 ms | **+62 ms (+24 %)** |
| TTFB navegador (`responseStart − requestStart`) | 284 / 251 ms | 318 / 363 ms | +34 / +112 ms |
| HTML plano | 1 069 KB | 1 078 KB | +9 KB |
| HTML gzip | **99,7 KB** | 100,9 KB | **+1,2 KB** |
| Cliente: `DOMContentLoaded − responseEnd` | 760 / 628 ms | 652 / 742 ms | sin diferencia medible (ruido > efecto) |
| Clic de fila → seleccionada (hasta el siguiente frame) | 2,0 / 1,6 ms | 2,5 / 2,8 ms | +0,5 / +1,2 ms |
| JS extra en la primera visita | — | `livewire.min.js` 233 KB / **77 KB gzip** (cache 1 año) + entrada v2 0,3 KB | +77 KB gzip |
| Errores de consola | 0 | 0 | — |

### Muestras

| Métrica | Legacy (r1 / r2) | v2 (r1 / r2) | Diferencia |
|---|---|---|---|
| TTFB servidor (curl, mediana de 30) | **251 ms** | 307 ms | **+56 ms (+22 %)** |
| TTFB navegador | 250 / 261 ms | 324 / 341 ms | +74 / +80 ms |
| HTML plano / gzip | 1 041 KB / **92,0 KB** | 1 050 KB / 93,2 KB | +9 KB / **+1,2 KB** |
| Cliente: `DOMContentLoaded − responseEnd` | 650 / 586 ms | 647 / 721 ms | sin diferencia medible |
| Clic de fila → seleccionada | 1,7 / 2,0 ms | 2,3 / 2,9 ms | +0,6 / +0,9 ms |
| Errores de consola | 0 | 0 | — |

Lectura honesta:
- **TTFB** es el número firme: +56–62 ms en curl con 30 muestras alternadas (p90 también sube: 363 → 415 ms
  Programa, 344 → 406 ms Muestras). No se perfiló a nivel de función; el costo es el ciclo del componente
  Livewire sobre el mismo render de la grilla. El entorno corre con `app.debug=true` (como 04-perf).
- **Bytes**: los +9 KB planos son los marcadores de morph de Livewire (`<!--[if BLOCK]>…`, dos por fila),
  el snapshot (`superficie` + las 59 columnas ocultas) y `wire:*`. El dataset **no** viaja en el snapshot.
- **Cliente**: la dispersión de `DOMContentLoaded − responseEnd` en este contenedor (≈ ±100 ms entre rondas)
  es mayor que cualquier efecto; el clic de fila es el mismo código (`index.js`) y cuesta < 3 ms en ambos.
- En producción el runtime de Livewire sería `livewire.min.js` (77 KB gzip), no el `livewire.js` sin
  minificar (115 KB gzip) que sirve el arnés en debug.

### Mismo diseño

Capturas lado a lado en `capturas/` (`legacy-*` / `v2-*`), Programa y Muestras a 1280×800 y 768×1024: los
cuatro pares de PNG son **idénticos byte a byte** (`cmp`).

## 2. Qué se hizo

| Tarea | Commit | Resultado |
|---|---|---|
| Plan v2 + arnés | `3d7f1a2`* | `03-shell-livewire-PLAN.md` v2 (tabla "Cambios v1 → v2", v1 como historial); `arnes/seed-pt.php` + `arnes/medir-shell.mjs` |
| 03.1–03.4 Shell | ver `git log` | `App\Livewire\Planeacion\ProgramaTejidoBoard`, `ShellV2`, partials, wrapper, entrada v2, tests |
| 03.5 GATE | — | §0–§1: **no pasa** |
| HANDOFF 17-02 B1/B2 | ver `git log` | Liberar órdenes por `data-accion`; menús por `accionesTactiles` + "⋮" |
| HANDOFF PT-05 B4 | ver `git log` | Redbooth a `resources/js/modulos/redbooth/` |

\* Los hashes cambian al integrar; usar los mensajes `programa tejido: …` de la rama.

### Shell v2 (PT-UI-01, PT-ROL-01)

- **Canary** `ShellV2::activo()`: `PLANEACION_SHELL_V2 = off | canary | on` + `PLANEACION_SHELL_V2_CANARY=<Id>,<Id>`,
  con la misma semántica que las mutaciones v2 (`MutacionesV2::modoActivo()`, extraído y compartido; PT 05 no
  cambia). `ProgramaTejidoController::index()` bifurca antes de consultar; rutas y nombres sin cambios.
- **Con el canary apagado la respuesta es la legacy byte a byte:** la grilla (`partials/grilla.blade.php`) y
  los modales/menús (`partials/complementos.blade.php`) se extrajeron de `req-programa-tejido.blade.php` y el
  HTML servido se comparó contra el capturado antes del cambio (CSRF normalizado, mismo build): `cmp` idéntico
  en Programa y Muestras. Test: sin `programa-tejido-v2`, `data-pt-shell`, `wire:` ni `livewire` en la página.
- **Superficie explícita (Muestras):** `mount(string $superficie)` + `#[Locked]`; la consulta usa
  `from($superficie->tabla())` (en `/livewire/update` no corre `ProgramaTejidoContext`). Test: tras `$refresh`
  con la config y el request apuntando a Programa, Muestras sigue leyendo `MuestrasPrograma`; cambiar la
  superficie desde el cliente lanza `CannotUpdateLockedPropertyException`.
- **Dataset nunca público:** filas solo en `#[Computed] registros()`; el snapshot tiene `superficie`, `ocultas`
  y `error` (test).
- **Misma lectura:** el SELECT de la grilla pasó a `ProgramaTejidoReadService::COLUMNAS_GRILLA` +
  `registrosGrilla()`, y las columnas ocultas a `columnasOcultas()` (movimientos puros); los usan legacy y v2.
- Error de lectura → `report()` + mismo estado de error que la legacy + evento `programa-tejido-error` →
  `notify.error` (entrada `resources/js/modulos/programa-tejido-v2/index.ts`, por glob; `vite.config.js` intacto).
- El componente responde 404 si el canary está apagado (ni un snapshot guardado lo revive) o la superficie
  es inválida.

### HANDOFF 17-02 B1/B2 (UX-06)

- **B1:** el botón "Liberar órdenes" del navbar de PT pasa de `onclick="mostrarModalDiasLiberar()"` a
  `data-accion="dias-liberar"`; `programa-tejido/acciones.ts` lo enlaza con `delegate()` e importa
  `componentes/dias-liberar.ts` (import dinámico: el bundle se evalúa en node sin SweetAlert, test del bundle).
- **B2:** menú de fila y de encabezado de PT, y de encabezado de liberar-órdenes, con `accionesTactiles`
  (clic derecho + mantener presionado; clase `towell-acciones-zona`). `botonAcciones`: **un solo "⋮" en el
  navbar** que abre el menú de la fila seleccionada (sin selección: aviso). No se pone uno por fila porque
  `index.js` lee `textContent` de las celdas (filtros, totales, edición inline). Los encabezados no llevan "⋮":
  sus acciones (filtrar/fijar/ocultar) ya están en los botones de columnas del navbar.
- Verificado en Chromium (768×1024, touch): clic derecho, long-press de 650 ms, "⋮" con y sin selección,
  menú de encabezado y modal de días, en Programa y Muestras; liberar-órdenes con una tabla inyectada
  (el sqlite no tiene órdenes por liberar). 0 errores de consola.

### HANDOFF PT-05 B4 (Redbooth)

El `<script>` inline de `modal/redbooth.blade.php` (315 líneas) pasa **tal cual** a
`resources/js/modulos/redbooth/modal.js` (+ `index.ts` de entrada por glob y `logica.ts` que valida el boot);
las rutas y el contexto viajan en `<script type="application/json" id="redbooth-boot">` y el propio modal
carga `@vite('resources/js/modulos/redbooth/index.ts')`. Verificado en PT, Trazabilidad y CatCodificación:
`window.abrirModalRedboothProgramaTejido` existe y abre el modal; en CatCodificación el GET lleva
`source=catcodificados`. Muestras sigue sin el modal.

## 3. Hallazgos (no se corrigieron)

| # | Hallazgo | Evidencia |
|---|---|---|
| H1 | A 768 px el navbar de PT ya se desbordaba (con todos los permisos: el logo tapa el título y el botón de filtros queda cortado); el "⋮" (44 px) lo empuja un botón más | `nav-sin-768` vs `nav-768` (skill run); HANDOFF C1 |
| H2 | El ratchet cuenta `<script type="application/json">` como "script inline": mover JS a un bundle con boot JSON no baja la métrica (Redbooth: −1 +1) | `scripts/ratchet.mjs`; HANDOFF A1 |
| H3 | Livewire compila marcadores de morph en toda vista que se renderiza dentro de un componente, incluidos los partials compartidos; son comentarios, sin efecto visual, pero cuestan ~5 KB en la grilla | test de paridad normaliza `<!--[if BLOCK]>` |

## 4. Evidencia

```
php artisan test                        (ver §4.1)
php artisan test tests/Feature/Planeacion tests/Unit/Planeacion   403 passed antes de B1/B2/B4
ProgramaTejidoShellV2Test               9 passed · ProgramaTejidoShellV2EstructuraTest 4 passed
tests/Js programa-tejido-{v2,acciones}, redbooth-boot   6 passed (bundle de PT: 17 passed)
npm run ratchet                         ok; onclick= 265 → 264 (fijado)
```

### 4.1 Checks antes del push

```
php artisan test                        2131 passed (22750 assertions), tras merge de origin/main
vendor/bin/phpstan analyse             [OK] No errors
npm run typecheck / test:js / build     ok / 240 pass, 0 fail / built
npm run ratchet                         ok (onclick= 265 → 264)
vendor/bin/pint --test (PHP cambiados)  pass
planeacion:programa-tejido-health       ProgramaTejidoHealthCheckTest 6 passed en sqlite; suelto "No se pudo consultar"
                                        (no hay database/sqlite), igual que PT-02/04/05
code-review (medium)                    0 hallazgos (1 inconsistencia menor del fallback del ⋮, corregida)
security-review                         0 hallazgos (componente con gate auth + canary en boot(); superficie #[Locked] y enum; tabla solo de config; @json con JSON_HEX_*; modal.js idéntico al inline salvo el boot). Brecha previa, no nueva: index() solo exige auth, sin acceso por módulo
```

## 5. Runbook para el owner (Laragon, datos reales)

El GATE se decidió con datos sintéticos. Para confirmarlo con datos reales **sin exponer a nadie**:

1. `.env`: `PLANEACION_SHELL_V2=canary`, `PLANEACION_SHELL_V2_CANARY=<tu Id>`; `php artisan config:clear`.
2. Abrir `/planeacion/programa-tejido` y `/planeacion/muestras` 20 veces cada una con tu usuario y 20 con
   otro usuario fuera de la lista (o quitarte de la lista). En `/admin/rendimiento` comparar p50/p95 de
   `catalogos.req-programa-tejido` y `muestras.index` entre las dos tandas.
3. Verificar que Muestras muestra filas de Muestras tras recargar y que el diseño es el mismo.
4. Rollback: `PLANEACION_SHELL_V2=off` + `config:clear` (sin tocar BD ni caché del navegador).

Si con datos reales v2 saliera mejor (no esperado), reabrir la decisión con esos números.

## 6. Cómo desplegar

`npm run build` y `php artisan optimize:clear && php artisan optimize`. Sin migraciones. `.env` opcional
(todo apagado por default): `PLANEACION_SHELL_V2`, `PLANEACION_SHELL_V2_CANARY`. Efectos visibles con el
flag apagado: botón "⋮" en el navbar de PT/Muestras, menús con long-press, Redbooth cargado como módulo.

## 7. Archivos

- Nuevos: `app/Livewire/Planeacion/ProgramaTejidoBoard.php`, `app/Services/Planeacion/ProgramaTejido/ShellV2.php`,
  `resources/views/livewire/planeacion/programa-tejido-board.blade.php`,
  `resources/views/modulos/programa-tejido/{req-programa-tejido-v2,partials/grilla,partials/complementos}.blade.php`,
  `resources/js/modulos/programa-tejido-v2/{index,logica}.ts`, `resources/js/programa-tejido/acciones.ts`,
  `resources/js/modulos/redbooth/{index.ts,logica.ts,modal.js,modal.d.ts}`,
  tests `tests/Feature/Planeacion/ProgramaTejidoShellV2Test.php`, `tests/Unit/Planeacion/ProgramaTejidoShellV2EstructuraTest.php`,
  `tests/Js/{programa-tejido-v2,programa-tejido-acciones,redbooth-boot}.test.mjs`, docs/arnés/capturas/medición de la fase.
- Modificados: `ProgramaTejidoController.php`, `ProgramaTejidoReadService.php`, `MutacionesV2.php` (helper
  compartido), `config/planeacion.php`, `req-programa-tejido.blade.php` (solo extracción),
  `resources/js/programa-tejido/index.js`, `components/navbar/sections/programa-tejido.blade.php`,
  `liberar-ordenes/index.blade.php`, `modal/redbooth.blade.php`, `scripts/ratchet-baseline.json` (baja),
  `tests/Unit/TrazabilidadStructureTest.php` (1 línea, HANDOFF B1).
- No se tocó: `vite.config.js`, `package.json`, `resources/js/{utils,componentes}/**`, `bootstrap/**`,
  `config/database.php`, módulos 19-xx.
