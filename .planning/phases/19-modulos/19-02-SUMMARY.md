# 19-02 — Tejido: JS inline → TS · SUMMARY

**Rama:** `claude/19-02-tejido` (base `claude/friendly-hopper-506bg9` @ `b0d181a`, con `origin/main` mergeado) · **Fecha:** 2026-09-29 · **PR:** no abierto (lo pide el owner).
**IDs:** MIG-TEJ-01..04, PERF-08..11 (del módulo), SEC-07 (del módulo), UX-18 ([checklist 17-02](../17-ux/17-02-CHECKLIST.md)). SEC-06 **no**: AuthZ sigue en modo auditar.
**Plan:** [`19-02-PLAN.md`](19-02-PLAN.md) · **Receta:** [`19-00-RECETA.md`](19-00-RECETA.md) · **HANDOFF:** [`HANDOFF.md`](HANDOFF.md) §"De 19-02" · **Arnés:** [`19-02-arnes/`](19-02-arnes/README.md) · **Capturas:** [`19-02-evidencia/`](19-02-evidencia/)

## Resultado

Las vistas de `modulos/{tejido,cortes-eficiencia,inventario-trama,marcas-finales}`, `produccion-reenconado-cabezuela` y `livewire/inventario-trama` quedan **sin `<script>` inline, sin `on*=`, sin `csrf_token()`, sin `<h1>` propio y sin texto < 12 px en clases Tailwind** en el fuente. Lo vigila un test guardián (`tests/Feature/Tejido/VistasSinJsInlineTest.php`, 58 casos). El JS vive en `resources/js/modulos/tejido/**` (TS estricto, sin `@ts-nocheck`).

| | Antes | Después |
|---|---|---|
| Líneas de JS inline en el módulo | ~6 850 (17 vistas) | 0 |
| Vistas Blade | — | 770 + / 7 122 − |
| TS nuevo en `resources/js/modulos/tejido` | — | 4 767 líneas (con `logica.ts` puros probados en node) |
| Tests nuevos | — | PHP: 140 en `tests/Feature/Tejido` · JS: 6 archivos `tests/Js/tejido-*.test.mjs` |

**Ratchet (fijado con `--update`, solo bajas):**

| Métrica | Antes | Después |
|---|---|---|
| `fetch(` | 246 | **202** |
| `Swal.fire` | 659 | **485** |
| `onclick=` | 265 | **240** |
| `innerHTML =` | 349 | **329** |
| `X-CSRF-TOKEN` | 150 | **109** |
| `<script>` inline en blade | 116 | **99** |
| `getMessage()` en `response()->json` | 229 | **192** |

## Pantallas (commit → qué)

División -p1/-p2 por rangos de commits (el diff pasa de 1 500 líneas, casi todo movimiento Blade → TS): **p1** = `7e3fcd7`, `a7f7e01`, `330f711`; **p2** = `f240c68`, `822b039`.

| Commit | Pantalla(s) | Notas |
|---|---|---|
| `7e3fcd7` | Secuencias ×4 (inv-telas, inv-trama, corte-eficiencia, marcas-finales) | 1 vista `secuencia/comun.blade.php` + 1 bundle, variante por mapa PHP; las 4 vistas quedan como `@include` (controllers sin cambio). Formulario de Swal con HTML → `x-ui.modal-base` |
| `a7f7e01` | Marcas finales (consultar, nuevo/editar/visualizar, reporte) | sin puentes (`window.MarcasManager` eliminado) |
| `330f711` | Inventario de telas, reenconado, inventario de trama | trama es Livewire: solo texto y SEC-07 |
| `f240c68` | Reportes (saldos-2026, rpm-semanal, inv-telas, marcas-finales, promedio-paros, index) | modal de fechas común `reportes/partials/rango-fechas` |
| `822b039` | Cortes de eficiencia (captura, consultar, visualizar) | pdf.js por `librerias.pdfjs()` en ESM; captura partida en `estado/tabla/horarios/guardado/observaciones/acciones.ts` |
| `67cd88e`, `f3b4aee`, `0a318c6` | Guardián, ratchet, correcciones del code-review | — |

**Puentes `window` que quedan (para ADOP):**
- `window.volverAlIndice` (`reportes/inv-telas`) — lo llama `resources/js/app-core.js` (`#btn-back`).
- Funciones de `resources/js/tejido/inventario-telas.ts` que llaman los 11 `onclick` de `components/telares/telar-requerimiento.blade.php` (HANDOFF T3). Todas marcadas `// PUENTE 19-02`.

## Bugs encontrados y corregidos (además de mover el código)

- **Secuencias:** Observaciones iba sin escapar al HTML del Swal de edición (XSS). Inv-trama leía `$item->id` (minúsculas): con la columna `Id` editar/eliminar/reordenar mandaban un id vacío; ahora `Id` con respaldo. `update`/`destroy` de un id inexistente daban 500 con "No query results…" → 404.
- **Inventario de telas:** HANDOFF 15-01 #4 — el mensaje del servidor nunca se mostraba (`err.response?.data?.message` con `window.http`); scroll al telar contaba dos veces el desplazamiento.
- **Reenconado:** el modal mostraba el folio anterior al que se guardaba; la eficiencia del modal usaba otra fórmula que la guardada; filas nuevas con `innerHTML` sin escapar; `querySelector('1')` lanzaba SyntaxError al cerrar por el fondo; listeners duplicados tras cada alta; flashes duplicados con `x-ui.flash`; fecha por defecto en UTC.
- **Marcas finales:** % Efi guardado en fracción (folios viejos) salía en 0 y se perdía al guardar; el guardado en `beforeunload` casi siempre se cancelaba → `sendBeacon`; cancelar el 2.º modal de fecha dejaba la pantalla sin folio; doble alerta; mensaje del servidor sin escapar en Swal; `PUT {folio}` ignoraba el folio de la URL; INSERT de líneas de una sola sentencia pasaba de 2 100 parámetros con > 150 telares.
- **Cortes:** fecha por defecto en UTC (después de las 18:00 proponía mañana); validaciones que salían como 500; el aviso "Guardado automáticamente" quedaba invisible encima de la página y tapaba clics; *code-review:* un folio **finalizado** seguía autoguardándose y `store()` creaba un folio nuevo (ya pasaba antes) → ya no se guarda y la captura pasa a solo lectura; error al cargar `?folio=` era silencioso.
- **Reportes:** "Aplicar" del filtro de saldos no cerraba el modal (`checkAll` inexistente); la fila de filtros por columna nunca se mostraba; no se podía deseleccionar fila; separadores dobles al filtrar; fechas por defecto en UTC; la gráfica de rpm-semanal era código muerto (no había `<canvas>`): se quita junto con `charts.js` en esa vista.

## Backend del módulo

**SEC-07:** 0 `getMessage()` hacia el usuario en controllers/services/Livewire del módulo (quedan solo en `Log::`). JSON por `HandlesApiErrors` (`message` + `trace_id`), flashes con `(ref: …)`, `ModelNotFoundException` → 404.
- Secuencias ×4: 12 · Marcas: 5 fugas + 7 catch sin reporte · Reenconado: 11 · `NuevoRequerimiento` (Livewire): 1 · Cortes: 14. Reportes: 0 (sus flashes ya eran fijos).
- Contrato: se conservan las claves que el JS consume (`success`, `message`, `data`; Excel/PDF de cortes siguen mandando `error` además).

**PERF-08..11** (todas las cifras en tests con `contarQueries()` sobre sqlite, mismo escenario antes y después):

| Dónde | Antes → después |
|---|---|
| Orden de secuencias (30 filas) | 30 UPDATE → **1** `UPDATE … CASE` (`app/Services/Tejido/OrdenSecuencia.php`, bloques de 600 filas; llave repetida: gana la última, como antes) |
| `CortesEficiencia::getDatosTelares` (30 telares) | 31 → **2** (`ROW_NUMBER() OVER (PARTITION BY NoTelarId …)`, existe desde 2005) |
| `getDatosProgramaTejido`, respaldo sin EnProceso | 32 → **3** (`MAX(Id) … GROUP BY`) |
| `CortesEficiencia::store` (autoguardado, 30 líneas) | 60 queries de `updateOrCreate` → INSERT en bloques de 95 filas + UPDATE solo de filas cambiadas (10 el primer guardado, 7 un cambio); choque con el índice único → reintento fila por fila como antes |
| Marcas `store` | 1 INSERT de 2 800 parámetros (falla en SQL Server) → bloques de 142 filas; las demás consultas no crecen con los telares |
| `obtenerDatosVisualizacionPorFecha` | **sin N+1**: 3 consultas fijas por llamada (el encargo decía "por fecha del rango": no hay rango). El test fija el 3. `whereDate` no se tocó |

Sin índices nuevos ni `.sql`. SQL revisado a mano para 2008 R2 (sin `OFFSET/FETCH`, `CONCAT`, `IIF`…). Límite de parámetros: < 2 100 **incluyendo** los del RPC (el code-review detectó que 100×21 y 150×14 daban justo 2 100).

**AuthZ (20-03, modo auditar):**
- `PUT /modulo-cortes-de-eficiencia/{id}` (stub sin llamadores que respondía 200 sin escribir) → **410** con mensaje; la ruta sigue en `modificar,105,auditar`.
- `POST /produccion/reenconado-cabezuela` (duplicado legacy): misma acción, middleware y validación que la ruta nueva (test).
- Rutas en enforce que ya existían (finalizar/reabrir marcas, finalizar cortes, eliminar reenconado): no se tocaron → HANDOFF T7.

## Checklist UX-18

| Pantalla | 1.1 title | 1.2 un h1 | 2.1 sin solo-clic-derecho | 2.5 ≥ 12 px | 3.1 aria-label | 4.2 toasts notify | 4.3 http | Notas |
|---|---|---|---|---|---|---|---|---|
| Secuencias ×4 | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | modal `x-ui.modal-base` |
| Inventario telas (menú) | ✅ | ✅ | n/a | ✅ | n/a | n/a | n/a | |
| Inventario telas por salón | ✅ | ✅ | n/a | ❌ | ✅ | ✅ | ✅ | CSS propio baja a ~10 px (HANDOFF T5) |
| Trama nuevo / consultar | ✅ | ✅ | n/a | ✅ | n/a | ✅ | ✅ | Livewire |
| Reenconado | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | |
| Marcas consultar / nuevo / reporte | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | h3 del vacío → h2 |
| Cortes captura / consultar / visualizar | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | |
| Reportes index, inv-telas, promedio, marcas, rpm | ✅ | ✅ (C2) | n/a | ✅ | ✅ | n/a | n/a | |
| Saldos-2026 | ✅ | ✅ | ✅ (C1) | ⚠️ | ✅ | n/a | n/a | clic derecho + pulsación larga + ⋮ en el navbar; el `<style>` tipo Excel conserva 0.55–0.72rem |

## Evidencia

- **PHP:** `php artisan test` (sqlite): **2 257 passed**, 0 fallos (antes del último fix; `tests/Feature/Tejido` después: 140 passed).
- **JS:** `npm run typecheck` 0 errores · `npm run test:js` **283 pass** · `npm run build` ok.
- **Estático:** `phpstan analyse --memory-limit=2G`: 0 errores (baseline intacto) · `pint --test` en los PHP cambiados: pass (arnés incluido).
- **Ratchet:** ok, tabla de arriba.
- **Code-review** (skill, `high`): 10 hallazgos; corregidos en `0a318c6` los 2 de límite de parámetros, folio finalizado autoguardado, error silencioso de `?folio=`, UTC en reenconado, `CASE` con llave repetida y log duplicado. Quedan como deuda (no cambian comportamiento): 3 formas de abrir `x-ui.modal-base` en el módulo, `esVacioOCero`/finalizar duplicado entre marcas y cortes, descarga de blobs duplicada en el reporte de marcas.
- **Navegador** (skill `run` con el arnés `19-02-arnes`, sqlite + Playwright): 22 pantallas a 768×1024 y 1280×800, antes y después, **200 y 0 errores de consola** salvo `saldos-2026` (su SQL usa `TOP`: 500 en sqlite antes y después; verificada con la vista renderizada con 24 filas falsas, idéntica). Cada agente ejercitó además las acciones principales: crear/editar/eliminar/arrastrar en secuencias; alta, edición y filtros de reenconado; nuevo folio, captura, finalizar y reporte PDF de marcas; captura con guardado verificado en BD, observaciones, imagen JPEG por pdf.js, PDF y Excel de cortes; modales de fechas, clic derecho, pulsación larga, filtros y orden de saldos. Comparativas lado a lado en `19-02-evidencia/`.
- Cambios visuales intencionales: formularios que eran SweetAlert → `x-ui.modal-base` (secuencias, observaciones y modales de consultar cortes, fechas de reportes); texto de 10–11 px → 12 px; ⋮ en el navbar de saldos.

## Pendientes y decisiones para el owner

1. **Eficiencia de reenconado:** el servidor guarda `Cantidad/round(Horas×9.3,2)` (fracción) y la etiqueta dice "Eficiencia (%)". El modal ahora muestra lo que se guarda. ¿Fórmula correcta?
2. **Guardado masivo de reenconado** (`store` sin `modal`): acepta el `Folio` del cliente sin consumir la secuencia. ¿Se retira?
3. **Marcas:** `generarFolio` devuelve `creado_por_otro: true` en todo 400, así que "¿Desea continuar editando ese folio?" nunca aparece; guardar un folio Finalizado lo reabre. Sin cambiar.
4. **Rutas en enforce** (HANDOFF T7): ¿se alinean a auditar hasta SEC-06?
5. **Saldos-2026:** ⋮ en el navbar (el menú es por columna) y tamaños del CSS tipo Excel (< 12 px): ¿se suben?
6. **RPM semanal:** si se quiere la gráfica, hay que agregar el `<canvas>` (cambio de diseño).

## Cómo desplegar

Sin migraciones, `.sql` ni variables `.env` nuevas.
- `npm run build` (bundles nuevos por glob en `resources/js/modulos/tejido/**/index.ts`).
- `php artisan optimize:clear` (o `view:clear`): cambian muchas vistas.
