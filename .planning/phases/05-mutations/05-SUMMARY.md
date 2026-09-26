---
phase: 05-mutations
plan: "05"
version: 2
branch: claude/pt-05-mutaciones
status: completo (salvo 05.5 canary, checkpoint humano, y B3)
fecha: 2026-09-26
ids: [PT-MUT-01, PT-DOM-01, PT-DOM-02, PT-ROL-01, PT-DUP-01, PT-DUP-02, PT-DUP-03, PT-DUP-04, PT-PERF-02]
---

# PT 05 — Mutaciones · SUMMARY

Plan: `05-mutations-PLAN.md` (v2, aprobado por el owner el 2026-09-26 con la excepción del modelo y la regla "'UL' se normaliza a '1'"). Matriz: `05-MUTATION-MATRIX.md`. Pedidos a otros: `HANDOFF.md`.

## 1. Qué se hizo

| Tarea | Commit | Resultado |
|---|---|---|
| 05.1 Matriz | `afb7b3e` | 24 handlers de escritura clasificados (simple/secuencia/grupo/balance/frontera/integración), dueño 05 u 06, 15 hallazgos de fallos silenciosos |
| 05.2 PT-DUP-01..04 | `47fa9d7` | Ver §2 |
| 05.3 PT-PERF-02 | `afb7b3e` | Posiciones del telar destino en una consulta (ver §3) |
| 05.4 Mutaciones v2 | `09801a4` | Edición inline, reprogramar y calendario masivo: FormRequest → DTO → Action, detrás de flag (ver §5) |
| Liberar Muestras R6/R7 | `f85f567` | 422 `capacidad: marbetes` antes de escribir mientras falte el DDL; `redirectUrl` por superficie. R1–R5, R8, R9 (prefijo "M") → PT-06 |
| HANDOFF B1 | `347f884` | `<script>` de `req-programa-tejido-line-table` (19 KB) → `resources/js/programa-tejido/lineas.js` |
| HANDOFF B3 | — | **No se hizo**: el modal de Redbooth lo incluyen también Trazabilidad y CatCodificación (HANDOFF B4) |
| 05.5 Canary | — | Checkpoint humano: runbook en §6 |

## 2. PT-DUP: una implementación cada uno

| ID | Antes | Después |
|---|---|---|
| DUP-01 | `ProgramaTejidoObserverHelper` ya no existía; quedaban copias inline (`get/unset/setEventDispatcher`, `observe()`) en 9 archivos PT. **Bug verificado:** `restoreObservers()` re-registraba el observer (1 → 2 → 3 listeners por request); `cambiarTelar`/`dividirTelar` llamaban `observe()` con el dispatcher nulo y dejaban el modelo **sin eventos**; los 422 tempranos de `dividirTelar` dejaban la transacción abierta | `restoreObservers()` idempotente (null = ya estaba apagado); todos los call sites PT usan `suppressObservers()/restoreObservers()`. Fuera de PT → HANDOFF B1 |
| DUP-02 | 14 derivaciones de FechaFinal, 6 idénticas (forma A) | `TejidoHelpers::resolverFechaFinal()` (forma A) y `finDesdeHoras()` (núcleo calendario/continuo que usan Balancear, DateHelpers y calendario masivo). `DateHelpers::calcularFechaFinalDesdeInicio` (pasamanos sin uso) borrado |
| DUP-03 | 4 variantes; solo Eliminar/Operaciones/health check contaban `'UL'` | `ReqProgramaTejido::esUltimo()` y `whereIn('Ultimo', VALORES_ULTIMO)` en las 12 lecturas. Mutator: `'UL'` se guarda como `'1'`. SQL de limpieza: `sql/pt_ultimo_normalizar.sql` (sin ejecutar) |
| DUP-04 | 30 filtros a mano en código PT | Eloquent con salón de fila/canónico → `->salon()->telar()` (Repaso, TejidoHelpers::recalcularPosicionesPorTelar, Duplicar, DragAndDrop, Balancear). **No** se tocaron `DB::table` (no admiten scope) ni los 3 sitios con salón crudo del request (ver hallazgo H1) |

**Shadow comparison (DUP-02):** `tests/Unit/Planeacion/ResolverFechaFinalShadowTest.php` compara copias literales del legacy contra las funciones nuevas sobre 120 combinaciones (calendario nulo/vacío/con líneas/que se agota/inexistente × 3 inicios × 8 horas, incl. ≤ 0 y fraccionales): **idénticas**. Divergencias que siguen entre formas (no se unificaron, cambiarían números en planta):

| Forma | horas ≤ 0 | saldo < 0 | snap | clamp fin ≥ inicio |
|---|---|---|---|---|
| A (Update, Dividir ×4, Duplicar) | +30 días | — | solo Update al cambiar calendario | solo Duplicar |
| B (Balancear) | repaso +12 h, resto +30 días | EnProceso → now; resto → fin del día de FechaInicio | sí, salvo EnProceso | no |
| C1 (`recalcularFechasSecuencia`) | EnProceso: HorasProd guardadas; resto: conserva duración previa; si no, 12 h/30 d | fin del día de la **FechaInicio vieja** | sí, salvo EnProceso | no |
| C2 (`cascadeFechas`) | conserva duración previa; si no, 12 h/30 d | fin del día del **nuevo** inicio | siempre | no |
| D (calendario masivo) | la fila se salta (`errores++`) | — | sí, salvo la primera del telar | sí |

## 3. PT-PERF-02 con número antes/después

`ProgramaTejidoController::store()` ya no existe (el alta va por duplicar, que ya precargaba posiciones, y dividir). El N+1 que quedaba: `obtenerSiguientePosicionDisponible()` (SELECT con UPDLOCK) por destino en `DividirTejido` y por fila movida en `dividirTelar`. `TejidoHelpers::reservadorDePosiciones()` hace una consulta por salón (mismo filtro exacto y lock) y asigna el primer hueco en memoria.

| Medición (sqlite, `ProgramaTejidoDividirQueriesTest`) | Antes | Después |
|---|---|---|
| `dividir-saldo`, 1 destino | 25 consultas | 25 |
| `dividir-saldo`, 6 destinos | 70 | **65** (9 → 8 por destino) |
| `dividir-telar`, consultas de posiciones | 1 por fila movida | **1** |

Mismas posiciones que antes (test de caracterización con huecos y destinos repetidos). Diferencia solo con datos inválidos: con `Posicion` duplicadas o ≤ 0 el legacy podía devolver una posición ocupada; el reservador no. Las otras 8 consultas por destino (último del telar, update de Ultimo, modelo, STD, insert, líneas, fórmulas) quedan para PT-06.

## 4. Hallazgos nuevos (no se corrigieron: fuera del alcance de 05)

| # | Hallazgo | Evidencia |
|---|---|---|
| H1 | `cambiarTelar` recibe `nuevo_salon` crudo ('KM'): carga el destino con el scope (alias) pero el bump `Id+1000000` y los updates filtran exacto, y escribe 'KM' en `SalonTejidoId`. Lo mismo en `obtenerSiguientePosicionDisponible` vía dividir por destino | Inventario DUP-04 (agente), `ProgramaTejidoOperacionesController` ~:341/:373 |
| H2 | `dividirTelar` a un telar con filas: `recalcularFechasSecuencia` renumera **solo** las filas movidas (1..n) y pisa posiciones del destino; en SQL Server el índice único lo rechazaría (500) | `ProgramaTejidoDividirQueriesTest::test_dividir_telar_consulta_las_posiciones_del_destino_una_sola_vez` |
| H3 | Editar pedido/no_tiras de una fila sin `PesoCrudo` responde **500** en el legacy (y en v2): `recalcularFormulasProduccion()` devuelve false y `UpdateTejido` lo convierte en error (desde PT-02) | Descubierto al armar el fixture de v2 |
| H4 | Calendario masivo cuenta `actualizados` dos veces (update masivo + filas con fechas cambiadas) | code-review; se movió igual al Action |
| H5 | `restoreObservers`/`observe()` sin dispatcher en código de otros dueños deja el modelo sin eventos | HANDOFF B1 |

## 5. Mutaciones v2 (PT-MUT-01, PT-ROL-01, PT-DOM-01/02)

| Familia | Ruta (sin cambios) | FormRequest → DTO → Action | Qué cambia en v2 |
|---|---|---|---|
| `actualizar` | `PUT {programa-tejido,muestras}/{id}` | `ActualizarProgramaTejidoRequest` → `CambiosProgramaTejido` → `ActualizarProgramaTejido` | `lockForUpdate` de la fila dentro de la transacción (CR-05 servidor); `velocidad_std`/`eficiencia_std` recalculan duración, FechaFinal y cascada (CR-03); fallo al actualizar aplicación en líneas revierte (WR-08); 500 sin `getMessage()`; no escribe el log `UpdateTejido: campos actualizados` (queda la telemetría) |
| `reprogramar` | `POST …/{id}/reprogramar` | `ReprogramarProgramaTejidoRequest` → `Reprogramacion` → `ReprogramarProgramaTejido` | lock + transacción; valor inválido → 422 (legacy: 500) |
| `calendarios` | `POST …/actualizar-calendarios-masivo` | `CambiarCalendarioRequest` → `CambioCalendario` → `CambiarCalendario` | todo o nada con `registro_id` de la fila que falló (CR-02.3); lock de las filas elegidas (WR-03); líneas dentro de la transacción y relanzando. **Igual que legacy:** fórmulas de `CalendarioController` (12 días / 4 decimales, WR-10) y encadenado solo de las filas elegidas (CR-02.2) |

- **Una sola implementación:** `UpdateTejido` se partió (movimiento puro) en `reglas()`, `aplicarCambios()`, `recalcularDerivados()` y `persistir($estricto)`, que usan el legacy y el Action; el loop de calendarios se movió a `CambiarCalendario` con modo `estricto` y el legacy lo llama con `false`. El observer no se reemplazó: se sigue usando como adaptador.
- **Flags:** `config('planeacion.mutaciones_v2')` = `PLANEACION_MUT_V2_{ACTUALIZAR,REPROGRAMAR,CALENDARIOS}` ∈ `off` (default) | `canary` | `on`, y `PLANEACION_MUT_V2_CANARY=74,12`. Telemetría: `Log::info('programa_tejido.mutacion', {familia, version, superficie, ms, status, excepcion, usuario})` en legacy y v2.
- **Tests (`ProgramaTejidoMutacionesV2Test`, 13):** paridad legacy/v2 (misma base completa —cabeceras, líneas, CatCodificados de las dos superficies— y mismo JSON) en 5 payloads de edición, 4 de reprogramar y el calendario masivo; CR-03; fallas inyectadas (líneas, aplicación, fila de calendario inválida) → v2 revierte y responde error, legacy confirmaba; 422 de negocio/validación iguales; Muestras solo toca Muestras; canary por usuario; health check sano tras v2.

## 6. Runbook del canary (05.5, owner en Laragon)

1. `php artisan planeacion:programa-tejido-health --json` → guardar.
2. `.env`: `PLANEACION_MUT_V2_ACTUALIZAR=canary`, `PLANEACION_MUT_V2_CANARY=<Id de 1–2 planeadores>`; `php artisan config:clear`.
3. Un turno de uso. Revisar `storage/logs/laravel-*.log`: `programa_tejido.mutacion` con `version=v2` (status, ms) contra `legacy`; buscar `status: 500`.
4. Health check de nuevo: mismas invariantes.
5. Probar rollback: `PLANEACION_MUT_V2_ACTUALIZAR=off` + `config:clear` → las líneas vuelven a `version=legacy`.
6. Repetir con `REPROGRAMAR` y luego `CALENDARIOS` (una familia a la vez). Tras el ciclo sin incidentes: `on`.

## 7. Evidencia

```
php artisan test                         1920 passed (21570 assertions)   [base 1623 antes del merge de 17-02]
php artisan test tests/Feature/Planeacion tests/Unit/Planeacion   388 passed
vendor/bin/phpstan analyse --memory-limit=2G   [OK] No errors  (baseline −113 entradas)
npm run typecheck / test:js / build      ok / 148 pass, 0 fail / built
npm run ratchet                          ok; <script> inline en blade 159 → 158 (fijado)
vendor/bin/pint --test (archivos tocados)   pass
planeacion:programa-tejido-health        en sqlite vía ProgramaTejidoHealthCheckTest: 6 passed; suelto
                                         "No se pudo consultar" (no hay database/sqlite), igual que PT-02/04
code-review (medium)                     0 hallazgos de correctitud (2 menores: ruta del .sql en un
                                         comentario —corregido— y H4)
security-review                          0 hallazgos (authz sin cambios: mismas rutas y middleware;
                                         flag solo por env; consultas con bindings; v2 sin getMessage)
```

Tests que fallan con el código viejo (verificado): los 3 de observers de `ProgramaTejidoDedupTest`, el de consultas de `dividirTelar` y el de globals del bundle.

## 8. Archivos

- Nuevos: `app/Actions/Planeacion/ProgramaTejido/{ActualizarProgramaTejido,ReprogramarProgramaTejido,CambiarCalendario,MutacionRechazada,FalloEnRegistro}.php`, `app/Data/Planeacion/ProgramaTejido/{CambiosProgramaTejido,Reprogramacion,CambioCalendario}.php`, `app/Http/Requests/Planeacion/ProgramaTejido/{ActualizarProgramaTejido,ReprogramarProgramaTejido,CambiarCalendario}Request.php`, `app/Services/Planeacion/ProgramaTejido/MutacionesV2.php`, `resources/js/programa-tejido/lineas.js`, tests `tests/Feature/Planeacion/{ProgramaTejidoDedup,ProgramaTejidoDividirQueries,ProgramaTejidoMutacionesV2,LiberarMuestras}Test.php`, `tests/Unit/Planeacion/ResolverFechaFinalShadowTest.php`, docs de la fase.
- Modificados: controllers y `funciones/`/`helper/` de ProgramaTejido, Utilerias Mover/Finalizar, `config/planeacion.php`, `ReqProgramaTejido.php` (excepción aprobada), `resources/js/programa-tejido/index.js`, la vista de la tabla de líneas, `tests/Js/programa-tejido-bundle.test.mjs`, baselines (solo bajan).
- Snapshot de rutas y tests de caracterización de PT-01: **sin cambios**.
- Tamaño: +1 592 / −906 en `app/config/resources`, de los cuales ~620 son movimientos puros (partición de `UpdateTejido`, loop de calendarios, `lineas.js` de 420 líneas); +736 en tests. Si el integrador lo quiere partir: `-p1` = `47fa9d7` + `afb7b3e`, `-p2` = el resto.

## 9. Cómo desplegar

1. Código: `npm run build` y `php artisan optimize:clear && php artisan optimize`.
2. Sin migraciones. `.env` opcional (todo apagado por default): `PLANEACION_MUT_V2_*`, `PLANEACION_MUT_V2_CANARY`.
3. DBA (opcional, recomendado): `sql/pt_ultimo_normalizar.sql`.
4. Efectos visibles con los flags apagados: el observer ya no corre N veces tras una operación masiva; filas `'UL'` cuentan como últimas en dividir/duplicar/update/balancear; liberar en Muestras sin DDL responde 422 en vez de 500; al liberar se vuelve a la grilla de la superficie.
