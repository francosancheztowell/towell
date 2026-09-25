---
phase: 04-ux-grid
plan: "04-perf"
branch: claude/pt-04-perf
status: completo
fecha: 2026-09-25
---

# PT 04-perf — cortes 4–7 y HANDOFF B1–B3

## Qué se hizo

| # | Tarea | Resultado |
|---|---|---|
| Corte 4 | `Promise.all` en duplicar-dividir | **Ya estaba hecho** (commit `165dc37`): `duplicarTelar` lanza detalle y grupo en paralelo y ni siquiera espera la red si la fila no trae `OrdCompartida`. Solo verificado. |
| Corte 5 | JS inline de la grilla a módulos | El cuerpo de 527 KB ya estaba en `index.js` desde `165dc37`, pero seguían **10 `<script>` inline (144 KB)** en la página. Se movieron los 6 de PT: `balancear` (73 KB), `PT_BOOT` (9 KB, ahora JSON), `act-calendarios`, `repaso`, `marbetes` y recalcular fechas. Quedan 4 que no son de PT. |
| Corte 6 | Click delegado + `selectedRowIndex` | El click delegado ya estaba (`bindRowSelectionOnce`, `165dc37`). Faltaba el bug: `window.selectedRowIndex` seguía siendo un número congelado. Ahora es un accesor sobre la fila (`seleccion.ts`). |
| Corte 7 | No emitir columnas ocultas | **No se hace.** Justificación con números abajo. |
| B1 | "Descargar programa" | Se decide en el servidor con `ProgramaTejidoSurface::actual()->soporta('descarga')`; se borró el ocultamiento por JS de `index.js`. |
| B2 | `navbar.blade.php` | `$programaTejidoModulePermission = $isMuestras ? 'Muestras' : 'Programa Tejido'` (1 línea). |
| B3 | Menú contextual | `$moduloPT = $superficie->moduloPermiso()` (idrol 2 / 5). |
| toastr | `balancear` | `toastr.*` → `notify.*` (3 llamadas; y el respaldo `toastr` muerto de `repaso`). PT ya no depende del adaptador `window.toastr` de 15-02. |

## Medición antes / después

Mismo comando antes y después: `php .planning/phases/04-ux-grid/medir-grilla.php --sintetico`
(sqlite en memoria; 85 filas SMIT 50 / JACQUARD 28 / KARL MAYER 7 en 38 telares; ~28 % de celdas
vacías; usuario con 59 columnas ocultas). **Hoy `getTableColumns()` devuelve 116 columnas, no las 92**
de la medición de septiembre: el dataset usa las 116 porque es lo que la vista pinta.

### Programa (usuario con 59 ocultas)

| | Antes | Después |
|---|---|---|
| HTML | 1 184 KB plano / 125 KB gzip | **1 088 KB / 105 KB** (−8 % / **−16 %**) |
| JS inline en la página | 10 bloques, 144 KB (mayor: balancear 73 KB) | **4 bloques, 40 KB** (−72 %; ninguno de PT) |
| Bundle Vite `programa-tejido/index.js` (cacheable) | 270 KB / 64 KB gzip | 321 KB / 78 KB gzip (+51 KB: lo que salió del HTML, ahora con cache) |
| Render Blade (mejor de 15) | 189 ms | 200 ms (ruido: mover JS no toca el render) |
| Cliente: `DOMContentLoaded − responseEnd`, mediana de 20 recargas × 2 rondas (Chromium headless) | 940 / 770 ms | 754 / 689 ms |
| `<script>` inline de PT en la vista | 6 | **0** (solo `#pt-boot`, que es `application/json`) |

### Muestras (usuario con 59 ocultas)

| | Antes | Después |
|---|---|---|
| HTML | 1 155 KB / 119 KB gzip | **1 059 KB / 97 KB** (−8 % / **−18 %**) |
| JS inline | 9 bloques, 126 KB | **3 bloques, 22 KB** |
| Render Blade (mejor de 15) | 187 ms | 185 ms |
| Cliente (mismo método) | 727 / 733 ms | 755 / 724 ms (sin diferencia medible) |

Lectura honesta del lado cliente: en este contenedor (`php -S`, un solo hilo) la dispersión min–max
(≈ 600–1 400 ms) es mayor que la ganancia; Programa baja la mediana en las dos rondas, Muestras no se
mueve. **El número firme del corte 5 son los bytes**: −20 KB gzip por carga y 104 KB de JS que el
navegador ya no re-parsea en cada recarga (sale del bundle cacheado con bytecode).

Desglose de los 4 bloques inline que quedan (ninguno es de PT; ver HANDOFF):

| KB | Origen |
|---|---|
| 19 | `components/programa-tejido/req-programa-tejido-line-table.blade.php` (fuera del glob PT) |
| 18 | `modulos/programa-tejido/modal/redbooth.blade.php` (prohibido en esta ola: 15-02) — no se emite en Muestras |
| 2 | `mostrarModalDiasLiberar` en `components/navbar/navbar.blade.php` (solo 1 línea de ese archivo es de PT) |
| 0,3 | `DOMContentLoaded` del layout |

### Corte 7: por qué no

El runbook simula el corte (render quitando las columnas ocultas del usuario):

| Usuario con 59 ocultas | Después de 4–6 | Corte 7 simulado |
|---|---|---|
| HTML Programa | 1 088 KB / 105 KB gzip | 565 KB / 60 KB gzip |
| Render Blade Programa | ~190–200 ms | ~100 ms |
| Usuario sin columnas ocultas | — | **sin cambio** (no hay nada que dejar de emitir) |

El corte **sí** ahorraría ~45 KB gzip y ~100 ms de render, pero solo al usuario que oculta columnas.
No se hace porque:

1. **Criterio del documento** ("solo si 1–3 no alcanzan"): no hay un objetivo numérico incumplido. Ni
   ROADMAP ni REQUIREMENTS fijan un TTFB o KB meta para la grilla; tras 1–6 el HTML viaja en ~105 KB
   gzip por LAN y el render queda en ~190 ms sintético (257 ms con datos reales tras 1–3).
2. **Costo**: "mostrar columna" pasaría a necesitar recarga, y `column-N` es índice posicional con
   decenas de lectores en `index.js` (ocultar, fijar, filtros, totales, edición inline). Cambiarlo es
   reescribir el manejo de columnas del bundle, no "5 líneas".
3. **La migración a Livewire de las fases 03/04** (ROADMAP, línea 89) ya rehace la grilla y es el lugar
   natural para emitir solo columnas visibles; hacerlo antes en el Blade legado se tiraría.

**Decisión para el owner:** correr el runbook en Laragon con el usuario pesado (abajo). Si con datos
reales el "corte 7 simulado" ahorra más de ~150 ms de render o el TTFB medido en `/admin/rendimiento`
para `/planeacion/programa-tejido` sigue por encima de lo aceptable, se hace como fase propia.

## Runbook para el owner (Laragon, datos reales, solo lectura)

```bash
php .planning/phases/04-ux-grid/medir-grilla.php --usuario=74             # Programa
php .planning/phases/04-ux-grid/medir-grilla.php --usuario=74 --muestras  # Muestras
# --repeticiones=15 para más estabilidad en el render
```

Hace el mismo SELECT que la grilla, lee `OrdColProgramaTejido` del usuario, renderiza la vista (sin
middleware: el TTFB real es mayor) e imprime HTML plano/gzip, celdas, tbody y bytes en celdas ocultas,
JS inline por bloque, render (mejor de N), la simulación del corte 7 y el bundle de Vite. No escribe
nada. Requiere `npm run build` hecho (para el tamaño del bundle).

## Corte 6: el bug y el arreglo

`selectRow(fila, índice)` guardaba el índice en `window.selectedRowIndex`. Al insertar una fila antes
(duplicar/repaso sin recargar), borrar **otra** fila antes, o reordenar con arrastrar, `window.allRows`
cambiaba y el número pasaba a señalar otra fila; ~20 lectores (`rows[window.selectedRowIndex]`: Editar,
Eliminar, Ver líneas, Balancear, menú contextual…) actuaban sobre ella. `seleccion.ts` define
`window.selectedRowIndex` como accesor: guarda la **fila** y calcula el índice al leer (-1 si la fila
salió del DOM). Los lectores y escritores no cambian.

- Test: `tests/Js/programa-tejido-seleccion.test.mjs` (insertar antes, borrar antes, reordenar, fila
  desconectada, fuera de rango).
- En el navegador (skill `run`): seleccionar una fila, mover la última fila arriba y refrescar
  `allRows` → `window.allRows[window.selectedRowIndex]` sigue siendo la misma fila (`data-id` 2, índice
  4 → 5) en Programa y Muestras.

## Hallazgos que no se tocaron (cero cambio de comportamiento)

- **Referencias muertas desde que `index.js` es módulo (`165dc37`).** Los scripts inline preguntaban
  `typeof PT_BASE_PATH`, `typeof agregarRegistroSinRecargar` (repaso), `typeof columnsData` y
  `typeof formatearValorCelda` (balancear). Esos nombres viven en el scope del módulo, no en `window`,
  así que siempre caen al respaldo: repaso no inserta la fila sin recargar (el toast dice "Repaso creado"
  pero la fila aparece al recargar) y balancear formatea las celdas actualizadas con el formateo básico.
  Se movieron tal cual; restaurarlos es publicar esos 4 nombres en `window` y probarlo con backend real.
  `PT_BASE_PATH` es inocuo: el parche de `fetch` de `index.js` reescribe la URL a la superficie.
- **Parche global de `window.fetch`** (`index.js`, arriba): reescribe `/planeacion/programa-tejido` a la
  superficie actual. Por eso balancear, repaso y act-calendarios **siguen usando `fetch`**: pasarlos a
  `http` (axios) los sacaría de esa reescritura y en Muestras escribirían en Programa. Migrarlos exige
  construir la URL con `PT_BOOT.basePath` explícitamente.
- **Ruta GET `programa-tejido.balancear` / `muestras.balancear`**: devolvía solo el `<script>` suelto
  (nadie la consume). Ahora devuelve la vista vacía documentada; sigue en 200 para los tests de contrato.
- **`resources/js/programa-tejido/globals.d.ts` nunca aplicaba**: sin `export {}` el `declare global` no es
  un módulo y `skipLibCheck` callaba el error. Se agregó `export {}`.

## Archivos

- Nuevos: `resources/js/programa-tejido/{boot.ts,seleccion.ts,balancear.js,recalcular-fechas.js}`,
  `resources/js/programa-tejido/modales/{act-calendarios,repaso,marbetes}.js`,
  `tests/Feature/Planeacion/ProgramaTejidoPermisosUiTest.php`, `tests/Js/programa-tejido-seleccion.test.mjs`,
  `.planning/phases/04-ux-grid/{04-perf-PLAN.md,04-perf-SUMMARY.md,HANDOFF.md,medir-grilla.php,sembrar-sintetico.php,capturas/}`.
- Modificados: `resources/js/programa-tejido/{index.js,globals.d.ts}`,
  `resources/views/modulos/programa-tejido/{req-programa-tejido,balancear}.blade.php`,
  `resources/views/modulos/programa-tejido/scripts/main.blade.php`,
  `resources/views/modulos/programa-tejido/modal/{act-calendarios,repaso,marbetes}.blade.php`,
  `resources/views/components/navbar/sections/programa-tejido.blade.php`,
  `resources/views/components/navbar/navbar.blade.php` (1 línea), `tests/Js/programa-tejido-bundle.test.mjs`,
  `scripts/ratchet-baseline.json` (solo baja), `tests/Feature/ProgramaTejidoJsSyntaxTest.php` (1 aserción, ver HANDOFF).
- No se tocó: `modal/redbooth.blade.php`, `components/ui/**`, `vite.config.js`, `package.json`,
  `resources/js/utils/**`, `config/database.php`.

## Evidencia

```
php artisan test                         1370 passed (1 fallo intermedio: ProgramaTejidoJsSyntaxTest fijaba
                                         'window.PT_BOOT'; aserción actualizada al contrato #pt-boot)
ProgramaTejidoPermisosUiTest             5 passed; 2 fallan si se revierten B2/B3 (verificado)
vendor/bin/phpstan analyse               [OK] No errors
npm run typecheck                        ok
npm run test:js                          101 passed
npm run build                            ok
npm run ratchet                          fetch( 300→299 · toastr. 25→22 · X-CSRF-TOKEN 193→192 ·
                                         <script> inline en blade 169→165 (baseline actualizado)
planeacion:programa-tejido-health        sobre el fixture PT en sqlite (ProgramaTejidoHealthCheckTest):
                                         6 passed. Suelto no hay database/sqlite ("No se pudo consultar"),
                                         igual que en PT-02.
code-review (medium)                     sin hallazgos
```

### Skill `run` — mismo diseño visual (D-E)

App real con `php -S`, `sqlsrv` apuntando a un sqlite sembrado con `sembrar-sintetico.php` (solo en el
scratchpad; `config/database.php` intacto), usuario 74 con 59 columnas ocultas, Chromium headless
1280×800. Capturas en `capturas/` (`antes-*` / `despues-*`), comparadas píxel a píxel:

| Captura | Resultado |
|---|---|
| programa, programa-menu, muestras, muestras-menu | **idénticas** |
| modal act-calendarios, repaso, marbetes, balancear | **idénticas** |
| recalcular fechas | solo cambia la hora dentro del mensaje de error del servidor (sqlite sin `UpdatedAt`); captura no incluida |

- 0 errores de consola al cargar, en ambas superficies y en ambos estados. Los 500 al abrir modales
  (calendarios, recalcular) son tablas que el sqlite sintético no tiene, y salen igual antes y después.
- Menú contextual y navbar: mismos botones antes y después (con todos los permisos). Descargar no
  aparece en Muestras en ninguno de los dos estados; antes lo quitaba el JS, ahora no se emite.

## Cómo desplegar

Sin migraciones, sin `.env` nuevo. `npm run build` y `php artisan optimize:clear && php artisan optimize`
(vistas cambiadas). Después, recarga forzada no hace falta: el bundle cambia de hash.
