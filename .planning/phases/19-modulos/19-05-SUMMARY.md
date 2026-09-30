# 19-05 — Programa Urdido-Engomado: JS inline y public/js → TS · SUMMARY

**Rama:** `claude/19-05-programa-urd-eng` (base `claude/friendly-hopper-506bg9`, con los gates CAL de la fase 22 integrados) · **Fecha:** 2026-09-30 · **PR:** no abierto (lo pide el owner).
**IDs:** MIG-PUE-01..04, PERF-08..11 (del módulo), SEC-07 (del módulo), UX-18. SEC-06 **no**: AuthZ sigue en modo auditar.
**Plan:** [`19-05-PLAN.md`](19-05-PLAN.md) · **Receta:** [`19-00-RECETA.md`](19-00-RECETA.md) · **HANDOFF:** [`HANDOFF.md`](HANDOFF.md) (sección "De 19-05") · **Arnés:** [`19-05-arnes/`](19-05-arnes/README.md) · **Capturas:** [`19-05-evidencia/`](19-05-evidencia/)

## Resultado

Las 5 vistas de `modulos/programa_urd_eng` y las 7 de tableros/edición (`livewire/urd-eng/*`, `programar-*-livewire`, `editar-orden-*`) quedan **sin `<script>` inline, sin `on*=`, sin `csrf_token()` y sin `asset('js/…')`** (guardián `ProgramaUrdEng/VistasSinJsInlineTest`: 12 vistas + que el `.js` de `public/js` no vuelva). `public/js/modulos/programa_urd_eng/creacion-ordenes.js` ya no existe (BUG-033): todo el módulo pasa por Vite.

| Pantalla | Antes | Después |
|---|---|---|
| Programación de requerimientos | 1 360 líneas inline (vista 1 444) | 0 · vista 196 · bundle `programacion-requerimientos/` (7 archivos) |
| Karl Mayer | 823 inline (vista 1 076) | 0 · vista 269 · bundle `karl-mayer/` (4) |
| Creación de órdenes | 36 inline + 1 460 en `public/js` | 0 · bundle `creacion-ordenes/` (8) |
| Reservar y programar | isla JSON + `reservar-programar.ts` 2 478 líneas en un archivo | 0 · `index.ts` 159 líneas de cableado + 8 archivos |
| Tableros / edición (Livewire) | 4 `fetch`, 4 Swal, 4 `window.alert` + 3 `$this->js('alert')` | 0 · `urd-eng/logica.ts` |
| Compartido | — | `comun/` (contrato del flujo, materiales, fecha de requerimiento + modal) |

TS del módulo: 7 078 líneas tipadas (estricto, sin `@ts-nocheck`, sin `any` sin comentar). Lógica pura en `logica.ts` por pantalla con 68 tests node (`tests/Js/programa-urd-eng-*.test.mjs`); los payloads de crear orden (normal y Karl Mayer) están **caracterizados contra el JS viejo** (mismo JSON y mismo orden de claves).

**Ratchet (fijado con `--update`, solo bajas):**

| Métrica | Antes | Después |
|---|---|---|
| `fetch(` | 202 | **189** |
| `Swal.fire` | 485 | **438** |
| `onclick=` | 239 | **231** |
| `innerHTML =` | 329 | **275** |
| `X-CSRF-TOKEN` | 109 | **103** |
| `<script>` inline en blade | 99 | **95** |
| `getMessage()` en `response()->json` | 192 | **148** |
| catch vacío | 28 | **27** |
| duplicación % | 6.93 | **6.85** |
| SQL crudo con `$interpolado` | 8 | 8 (no sube) |

## Commits (p1 / p2 por rangos, como 19-01)

- **p1 — reservar → programar → crear orden:** `41dbb093` (movimiento puro), `0df7d4e0` (programación de requerimientos), `de0d0e62` (creación de órdenes, BUG-033), `0b4fa73d` (reservar y programar + accionesTactiles), `91a03019` / `42ba3aaf` (backend de ese flujo).
- **p2 — Karl Mayer, tableros, backend:** `0b0a39a8` + `6b979322` (Karl Mayer), `111475ae` (tableros, U1, U7, SEC-07, 20-03), `1d8111d0` (SEC-07 y N+1 de alta de órdenes/BOM).
- **Cierre:** `88188dec` (code-review), arnés `ed923720` `f809d7f5` `a31fc991` `0665e512`, ratchet `88865d07`.

## Puentes `window`

- **Quitado:** `window.abrirModalCalificarJuliosEng` (HANDOFF 19-01 U1). `calificar-julios/index.ts` exporta `abrirCalificarJulios(variante, folio?)` y `urd-eng/edicion-ordenes.ts` lo importa.
- **Quitados sin llamadores externos:** `window.initCreacionOrdenes`, `window.crearOrdenes`.
- **Nuevos:** ninguno.

## Bugs encontrados y corregidos (además de mover el código)

- **SQL Server 2008 R2:** el filtro `hilo` de `InventarioTelaresService` usaba `TRIM()` (llegó en 2017): filtrar por hilo daba 500 en producción → `LTRIM(RTRIM())`, con test.
- **Límite de 2 100 parámetros:** `tiposPorFolioUrdido` con > 1 050 lotes de Karl Mayer lo rebasaba → bloques de 1 000 (con test del orden "el último gana").
- **Rutas:** `reservas/diagnostico` nunca se ejecutaba (la tapaba `reservas/{noTelar}`).
- **Errores:** `actualizar-telar` devolvía el SQL de una `QueryException` en un 404; 3 endpoints convertían su 422 de validación en 500.
- **Creación de órdenes:** con julios > 15 avisaba pero creaba igual sin esa fila; un 422 salía como "Error desconocido".
- **Karl Mayer:** fecha de requerimiento en UTC (mínimo 6 h adelantado); el aviso "Seleccione al menos un material" nunca salía; Tab bloqueado en Lote Proveedor.
- **Programación de requerimientos:** si se elegía el hilo antes que el tamaño, "Siguiente" quedaba deshabilitado; al cambiar Rizo ↔ Pie seguía la lista de hilos del tipo anterior; el hilo se guardaba con el tipo de telar de la carga inicial; se pedía el grupo de cada telar (una petición por telar) y el resultado nunca se usaba; dropdown de tamaño descolocado con scroll; colspan de filas vacías.
- **Reservar y programar:** ordenar por Estado no hacía nada; el tipo de atado no revertía al fallar; programar varios telares mandaba siempre `tipo_atado = Normal`.
- **Edición de órdenes:** un aviso de Livewire abría hasta 3 avisos a la vez; `success:false` con HTTP 200 se mostraba como "Actualizado"; autocompletado descolocado con scroll.
- **Del code-review (`high`), corregidos en `88188dec`:** Karl Mayer se saltaba la validación nativa (`min=1` en julios/hilos); `no_telar` numérico se ligaba como `int` contra una columna varchar; telares con distinta capitalización no se marcaban programados; `InsercionEnBloques` podía pasar de 1 000 filas por INSERT (error 10738); la falla del query conjunto de BOM perdía las fórmulas de los hermanos; tras reservar, un fallo de la recarga se reportaba como reserva fallida; tres helpers de error copiados en los dos controllers de tableros → trait `ProgramaUrdEng/Concerns/RespuestasErrorUrdEng`.

## Backend del módulo

**SEC-07:** 44 `getMessage()` en respuestas JSON → `HandlesApiErrors` vía `RespuestasErrorUrdEng` (`message` + `error` + `trace_id`; `error` se conserva porque es el contrato que leen las pantallas). En `EdicionOrden` (Livewire), `report()` + "ocurrió un error en el servidor (ref: …)". Quedan a propósito los `DomainException`/`RuntimeException` con mensajes escritos por el propio código. El catch vacío de `parseDateFlexible` pasa a `rescue()`, no a `report()`: probar formatos de fecha es flujo normal y cada intento llenaría `SYSMonError` (excepción anotada para CAL-03).

**U7:** `reimpresion-urdido/ventana-imprimir` sin `orden_id` → 422 (JSON si se pide JSON; si no, la página de error), sin `<script>alert`.

**PERF-08..11:** todas las cifras salen de tests en sqlite con el código viejo y el nuevo, y verifican que el resultado en BD es el mismo.

| Dónde | Antes → después (queries) |
|---|---|
| `CrearOrdenesService::crear` (30 materiales, 9 julios) | 57 → 14 |
| … con 250 materiales | 277 → 16 |
| `CrearOrdenKarlMayerController::store` (25 materiales, 7 julios) | 42 → 12 |
| Fórmulas de BOM hermanos (4 BOM) | 6 → 3 |
| Abrir programación de requerimientos (5 telares sin id) | 5 → 1 |
| `ejecutarReserva` con aviso al tejedor / sin aviso | 8 → 5 / 6 → 4 |
| Reservar desde la pantalla (`reservarConTelar`) | 10 → 6 |
| `actualizarTelar` por id / sin id con 4 registros | 5 → 4 / 5 → 2 |
| Liberar barra con 4 julios | 10 → 5 |
| Tablero: `saveUrdidoSalons` (30 órdenes) | 30 → 3 |
| Tablero: recálculo de prioridades (24 activas) | 30 → 7 |
| Ya óptimos (fijados en test) | `ResumenSemanasService` 3, `ejecutarCancelar` 4, `getDisponibleData` 2, listado de telares 1 |

- INSERT en bloques con `InsercionEnBloques`: ≤ 2 099 parámetros **y** ≤ 1 000 filas por sentencia.
- Sin índices nuevos.
- **Cobertura de `InventarioReservasService`** (27.8 % en 22-01): tests de caracterización **antes** de optimizar. Cubiertos todos sus métodos públicos y privados, salvo el `statement`/`get` de `queryDisponibleFromTiPro`, que exige SQL Server real (el SQL se revisa con `toSql()` y se sustituye en el test). No hay pcov/xdebug en el entorno para dar el porcentaje nuevo.

**AuthZ (20-03, modo auditar):**
- `engomado/programar-engomado/actualizar-prioridades` ("habilitado para todos") valida la entrada: 1–2 000 filas, ids y prioridades enteros distintos, prioridad ≤ 2 000 e ids existentes (una sola consulta) → 422.
- **No se agregó chequeo de permiso** (el code-review lo marcó): hacerlo sería enforce, y la ola sigue en auditar hasta SEC-06.
- Las rutas `programar-urdido/*` y `programar-engomado/*` viven en `routes/modules/{urdido,engomado}.php` (no son de 19-05) y siguen pendientes del idrol que manda el owner: ver HANDOFF.

## Checklist UX-18 por pantalla

| Pantalla | 1.1 title | 1.2 un h1 | 2.1 táctil | 2.5 ≥ 12 px | 3.1 aria-label | 4.2 notify | 4.3 419/401 | Notas |
|---|---|---|---|---|---|---|---|---|
| Reservar y programar | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | long-press en encabezados + botón ⋮ por fila (solo con `modificar`); loader propio → `window.loader` |
| Programación de requerimientos | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | resumen con "Reintentar" (5.3); teclado decimal en tablet |
| Creación de órdenes | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | modal de fecha `x-ui.modal-base` |
| Karl Mayer | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | mismo modal común |
| Programar Urdido / Engomado | ✅ | ✅ | n/a | ❌ | ✅ | ✅ | ✅ | texto < 12 px en `resources/css/urd-eng/program-board.css` → HANDOFF |
| Edición de orden Urd / Eng | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | formulario de empleados `x-ui.modal-base` |

## Evidencia

- **PHP:** `php artisan test` (sqlite): **2 403 passed**, 0 fallos. Nuevos: 21 archivos en `tests/Feature/ProgramaUrdEng/`. Siguen verdes `ProgramBoard*`, `EngomadoEdicionOrdenesTest`, `ProgramaUrdEngAuthorizationTest`, `ReservarConTelarTransaccionTest` y `UrdEng/CalificarJuliosTest`.
- **JS:** `npm run typecheck`: 0 errores. `npm run test:js`: **363 pass**. `npm run build`: ok.
- **Calidad:** `composer quality` con `CAMBIADOS_BASE=origin/claude/friendly-hopper-506bg9`: Pint pass en los 48 PHP cambiados, phpstan 0 errores (baseline intacto), PHPMD sin violaciones nuevas, ratchet ok.
- **Code-review** (skill, `high`): 10 hallazgos; 9 corregidos en `88188dec` (con tests de regresión que fallan sin el arreglo) y 1 que se deja a propósito (permiso en `actualizar-prioridades`: auditar, no enforce).
- **Navegador** (skill `run` + arnés `19-05-arnes/`, sqlite + Playwright):
  - 16 pantallas × 2 tamaños (768×1024 y 1280×800), antes y después: **todas 200, 0 errores de consola, 0 respuestas ≥ 400**.
  - Flujo completo con el código final: 18 pasos ok, 0 errores de consola.
    - Reservar J-502 a telar 203 (fila en `InvTelasReservadas`).
    - Programar 201+202 → requerimientos → creación → crear orden **00111**, verificada en `UrdProgramaUrdido`, `EngProgramaEngomado`, `UrdJuliosOrden`, `UrdConsumoHilo` y telares con `no_orden`/Programado.
    - Táctil a 768 px: long-press en encabezado, botón ⋮ (44×44) y long-press en celda.
    - Karl Mayer → folio **00112** verificado en BD.
  - Comparativas lado a lado en `19-05-evidencia/`. Mismo diseño salvo lo intencional:
    - Filas de reservar-programar más altas por el botón ⋮ de 44 px (caben 5 filas en vez de 7 a 1280).
    - Rangos de semana del resumen en 2 líneas (texto ≥ 12 px).
    - Modales `x-ui` en lugar de SweetAlert con formulario.

## Decisiones

- **Reparto:** siete agentes trabajaron en paralelo, cada uno sobre archivos distintos. El integrador commiteó por pantalla, deduplicó el modal de fecha (Karl Mayer y Creación nacieron con uno cada uno) y unificó los helpers de error.
- **`resources/js/programa-urd-eng/reservar-programar.ts` queda como stub vacío:** `vite.config.js` (congelado en la Ola 3) lo lista como entrada fija. HANDOFF R1.
- **Karl Mayer y Creación no comparten el armado del payload:** divergen en respaldos (`|| materialId`, `|| serialId`) y campos. Comparten `comun/inventario-materiales.ts` (orden, mapeo de material, clave) y la fecha de requerimiento.
- **`formatFecha` de Karl Mayer no usa `utils/format.formatDate`:** el JS viejo tomaba la fecha del ISO tal cual; `formatDate` la pasaría a hora de CDMX y daría el día anterior.
- **Arnés:** el "antes" con `vendor` en symlink cargaba `App\` del árbol de trabajo (composer sigue el symlink), así que en realidad no era el "antes". `19-05-arnes/README.md` explica cómo hacerlo bien (copia con hardlinks + `dump-autoload`). Aviso para el README de 19-01 en HANDOFF.

## Pendientes y decisiones para el owner

1. **Botón ⋮ en reservar-programar:** cumple la checklist (44 px, tablet), pero las filas son más altas. ¿Se acepta, o prefieres el botón más compacto con solo el long-press como objetivo táctil?
2. **Bugs previos que siguen igual (reproducidos en el "antes"):**
   - En creación de órdenes, tras elegir el BOM de la lista, el panel de urdido dice "No hay materiales de urdido disponibles" mientras engomado sí carga (parece una carrera blur/clic; abriendo por URL sí aparecen).
   - La lista de engomado muestra 2 de 3 materiales (falta el lote LP-779/A-MPBB).
   - Karl Mayer ofrece de nuevo un serial ya consumido si no está `Registrado`.

   Requieren criterio de negocio: no se tocaron.
3. **Programación de requerimientos:** una fila que agrupa varios telares con la misma cuenta guarda sus ediciones solo en el primer telar (creación recibe todos). Se conservó. ¿Es lo esperado?
4. **`ProgramarEngomadoController::actualizarPrioridades` sigue sin permiso** (solo validación): se cierra con SEC-06.
5. **`tests/Js/agruparTelares.test.cjs`** prueba una copia desactualizada de `agruparTelares` del `.js` borrado; no prueba código de producción (lo cubre `programa-urd-eng-creacion-ordenes.test.mjs`). No se borró: HANDOFF.

## Cómo desplegar

Sin migraciones, `.sql` ni variables `.env` nuevas.
- `npm run build` (bundles nuevos por glob en `resources/js/modulos/programa-urd-eng/**/index.ts`).
- Borrar en el servidor `public/js/modulos/programa_urd_eng/creacion-ordenes.js`, si el despliegue no sincroniza borrados.
- `php artisan optimize:clear` (cambian vistas y rutas: se reordenó `reservas/diagnostico`).
