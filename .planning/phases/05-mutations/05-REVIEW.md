---
phase: 05-mutations
reviewed: 2026-09-15T18:00:00Z
depth: deep
files_reviewed: 16
files_reviewed_list:
  - app/Http/Controllers/Planeacion/ProgramaTejido/funciones/draganddroptejido.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/funciones/UpdateTejido.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/funciones/eliminartejido.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/funciones/BalancearTejido.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoCalendariosController.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoBalanceoController.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoOperacionesController.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/helper/DateHelpers.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/helper/UpdateHelpers.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/helper/ProgramaTejidoSecuenciaHelper.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/helper/TejidoHelpers.php
  - resources/views/modulos/programa-tejido/scripts/inline-edit.blade.php
  - resources/views/modulos/programa-tejido/scripts/main.blade.php
  - resources/views/modulos/programa-tejido/scripts/filters.blade.php
  - resources/views/modulos/programa-tejido/modal/act-calendarios.blade.php
  - resources/views/modulos/programa-tejido/balancear.blade.php
findings:
  critical: 5
  warning: 12
  info: 8
  total: 25
status: issues_found
---

# Phase 05: Code Review Report — Programa Tejido (DnD / Inline Edit / Calendarios)

**Reviewed:** 2026-09-15T18:00:00Z
**Depth:** deep
**Files Reviewed:** 16
**Status:** issues_found

## Summary

Native HTML5 drag-and-drop (not SortableJS) reorders by 0-based collection index and then runs `DateHelpers::recalcularFechasSecuencia` on the whole telar. Inline edit is server-confirmed (not optimistic) via single-field PUTs. Calendarios masivo bulk-writes `CalendarioId` then walks a **selected subset** with a different formula engine than DnD/delete.

The implementation compiles and has some lock/`+10000` unique-index hygiene, but the date/position engines have drifted, EnProceso start timestamps are overwritten on every reorder, mass calendar commits partial failures, and the “edit whole row” path fires concurrent PUTs that lose updates.

---

## 1. DnD algorithm (how positions / telares are reassigned)

**Not SortableJS.** Programa tejido uses a custom HTML5 Drag and Drop module in `PT.dragdrop` (`resources/views/modulos/programa-tejido/scripts/main.blade.php` ~2083–2595). SortableJS in this repo is used by other boards / column sorts, not this grid.

### Same telar (priority reorder)

1. UI computes `new_position` as a **0-based index** (`calcTargetPosition`, main.blade.php:2126–2146):
   - Reads 1-based `data-posicion`.
   - Drop before target → `targetPos - 1`; drop after → `targetPos`.
   - If moving downward (`originPos < targetPos`) subtracts 1 (classic splice adjustment).
   - Clamps to `cache.rows.length` (cache **excludes** the dragged row).
2. POST `/planeacion/programa-tejido/{id}/prioridad/mover` with `{ new_position }` (`main.blade.php:2404–2410`).
3. `DragAndDropTejido::mover` → `moverAposicion` (`draganddroptejido.php:18–55, 62–176`):
   - Blocks `EnProceso === 1` (line 30–35).
   - `lockForUpdate()` all rows of same `SalonTejidoId` + `NoTelarId`, ordered by `Posicion`, `FechaInicio` (185–196).
   - Rejects `< 2` rows, missing first `FechaInicio`, drop before last EnProceso, or index out of range (198–259).
   - `Collection::splice` in memory (261–267).
   - `DateHelpers::recalcularFechasSecuencia($reordered, $inicioOriginal)` writes **Posicion = i+1**, EnProceso = first, Ultimo = last, dates, CambioHilo, formulas (DateHelpers.php:160–167).
   - Unique-index dodge: `Posicion + 10000` on affected IDs, then per-id update (draganddroptejido.php:111–135).
   - Regenerates lines **after** commit (152–163).

**Telares are not reassigned on this endpoint.** `new_position` is the only validated field.

### Cross telar

Frontend allows it (`main.blade.php:2379–2449`). It does **not** call `prioridad/mover`. It calls:

1. POST `.../verificar-cambio-telar`
2. POST `.../cambiar-telar` with `{ nuevo_salon, nuevo_telar, target_position }` (`main.blade.php:2535–2545`)

`ProgramaTejidoOperacionesController::cambiarTelar` (139–424):

- Parks mover at `Posicion = -Id`.
- Locks origin + dest telares.
- Clamps `target_position` into dest collection (does **not** include the moving row).
- Recalc origin remainder + dest including inserted row via the same `recalcularFechasSecuencia`.
- Dest unique dodge: `Posicion = Id + 1000000` (different constant than +10000).
- Syncs `CatCodificados.TelarId/Departamento` by `OrdenTejido` if `NoProduccion` is set (620–639).

After success, `updateTableAfterDragDrop` patches cells, re-sorts DOM by telar/salon/`data-posicion`, and `filterIndex.rebuild()` (`main.blade.php:363–548`).

---

## 2. Inline edit flow (fields, optimistic vs server, race with filters)

**Server-confirmed, not optimistic.** `saveInlineField` (`inline-edit.blade.php:645–736`) PUTs one snake_case field, then paints the cell. On error it restores `dataset.originalValue`.

Editable fields (`uiInlineEditableFields`, inline-edit.blade.php:89–226) mapped in `inlineFieldPayloadMap` (`state.blade.php:185–207`):

| UI column | Payload | Type |
|---|---|---|
| FibraRizo | hilo | select (hilos) |
| CalendarioId | calendario_id | select |
| TotalPedido | pedido | number |
| FlogsId | idflog | text |
| NombreProyecto | descripcion | text |
| AplicacionId | aplicacion_id | select |
| TamanoClave | tamano_clave | text |
| Rasurado | rasurado | text |
| ProgramarProd / EntregaProduc / EntregaPT | *_prod / entrega_* | date |
| EntregaCte | entrega_cte | datetime-local |
| NoTiras, Peine, LargoCrudo, Luchaje, PesoCrudo, Ancho, AnchoToalla, PTvsCte | snake_case | number |
| NoProduccion | no_produccion | text |

Not editable (commented as calculated): FechaInicio, FechaFinal, HorasProd, Std*, DiasEficiencia, ProdKgDia*.

Backend: `UpdateTejido::actualizar` (`UpdateTejido.php:23–701`). Flags `afectaCalendario` / `afectaDuracion` / `afectaFormulas` decide whether to snap, recompute `FechaFinal`, cascade (`DateHelpers::cascadeFechas` only if FechaFinal changed and `Ultimo !== 1`), and regenerate lines.

**Filters race:**

- Filters hide rows with `display:none` + `.filter-hidden`; they stay in the DOM (`filters.blade.php:178–186`). Custom filters prefer `PT_FILTER_INDEX` over the cell (`filters.blade.php:156–171`).
- After a successful inline save, `filterIndex.updateRow(row)` runs (`inline-edit.blade.php:704–706`). Filters are **not** re-applied, so a value that should hide the row stays visible until the next filter pass.
- After DnD, `filterIndex.rebuild()` **is** called (`main.blade.php:545`) — the two paths are inconsistent.
- `editarFilaSeleccionada` opens **every** editable cell at once (`main.blade.php:2056–2058` → `enableInlineEditForAllCellsInRow`). Each input blurs independently (`setTimeout 150ms`) and fires its own PUT. Last write wins on the same row; pedido/calendario recálculos interleave without locking.

Hilo change triggers a **second** PUT (`recalcularStdParaFila`, inline-edit.blade.php:594–641) for `velocidad_std` / `eficiencia_std`. That second PUT does not set `$afectaDuracion` (see CR-03).

---

## 3. Calendarios flow

UI: `resources/views/modulos/programa-tejido/modal/act-calendarios.blade.php`.

1. `GET /planeacion/calendarios/json` fills the select.
2. `GET /planeacion/programa-tejido/all-registros-json` loads **every** `ReqProgramaTejido` (`Id`, `NoTelarId`, `NombreProducto` only) — no current calendar, no EnProceso, no Posicion (`ProgramaTejidoCalendariosController.php:30–48`). Checkboxes default **checked** (act-calendarios.blade.php:169–177, 211).
3. `POST /planeacion/programa-tejido/actualizar-calendarios-masivo` with `{ calendario_id, registros_ids }`.

Server (`actualizarCalendariosMasivo`, 50–256):

- `set_time_limit(300)`, unset observers.
- **Bulk** `update(['CalendarioId' => $calendarioId])` for all IDs (77–78) **before** any date math.
- Reloads selected rows ordered by salon/telar/Posicion/FechaInicio/Id.
- Walks that **subset** chaining `prevFin` only across selected rows of the same telar (107–204).
- EnProceso: uses `Carbon::now()` as start but **does not persist FechaInicio** (128–129, 183–185) — different from `DateHelpers::recalcularFechasSecuencia`.
- Fin via `BalancearTejido::calcularFechaFinalDesdeInicio`; formulas via `CalendarioController::calcularFormulasDependientesDeFechas` (hardcoded `addDays(12)`, 4-decimal rounding — not `TejidoHelpers::resolverDiasEntrega`).
- Per-row `try/catch`: failures increment `$errores` and **continue**; transaction still **commits** (205–214).
- Regenerates lines **inside** the loop per row.

`actualizarReprogramar` (258–306): only if `EnProceso == 1`; values `1|2|null`.

`recalcularFechas` (308–320): `Artisan::call('programa-tejido:recalcular-fechas-produccion', ['--all' => true])` with no extra time limit.

App timezone is `America/Mexico_City` (`config/app.php:68`). PHP `Carbon::now()` / `Carbon::parse` on naive SQL Server datetimes are consistent **if** the DB stores local Mexico time. The JS `EntregaCte` formatter uses `new Date(v)` (browser-local / ISO-Z shift), not `parseSqlDateTimeLocal`.

---

## 4. Duplicated date / position logic

Four independent engines compute the same “snap + consume hours on calendar lines + formulas” story:

| Engine | Used by | Start for EnProceso | Persist FechaInicio EnProceso | EntregaCte | Posicion |
|---|---|---|---|---|---|
| `DateHelpers::recalcularFechasSecuencia` | DnD, delete, cambiarTelar, dividirTelar | `now()` | **YES** (overwrites) | `resolverDiasEntrega` via DateHelpers copy | `i+1` always |
| `DateHelpers::cascadeFechas` | UpdateTejido if FechaFinal changed | n/a (does not touch current) | n/a | same copy | does **not** rewrite Posicion |
| `BalancearTejido::resolverInicioFin` / `calcularInicioFinExactos` | preview + save balanceo + auto | `now()` for calc | **YES** on save (`$registro->FechaInicio = $inicio`) | `FORMULAS_CTX_BALANCEAR` | not rewritten |
| `ProgramaTejidoCalendariosController` + `CalendarioController` | mass calendar | `now()` for calc | **NO** | **hardcoded +12 days**, 4 dp | not rewritten |

Position compactors also duplicated:

- DnD / cambiarTelar: in-memory reorder + `Posicion = i+1` + unique bump.
- `TejidoHelpers::recalcularPosicionesPorTelar` (75–113): sequential `saveQuietly`, **no** +10000 bump. Used by delete.
- `TejidoHelpers::obtenerSiguientePosicionDisponible` (40–69): first hole, used by `dividirTelar` in a loop.
- `ProgramaTejidoSecuenciaHelper::aplicarUpdatesDesdeRecalculo` (16–21): raw `where Id update` **without** unique bump.

Formula clones:

- `DateHelpers::calcularMetricasBase` / `calcularFormulasEficiencia` duplicate `TejidoHelpers::calcularHorasProdFromParams` / `calcularFormulasEficienciaPorContexto`.
- `CalendarioController::calcularHorasProd` (1068–1083) wraps the helper then `calcularFormulasDependientesDeFechas` diverges (12 days, 4 decimals, no EntregaPT/EntregaProduc).
- `construirMaquinaConSalon` (TejidoHelpers:146–178, has KM) vs `construirMaquinaSegunSalon` (OperacionesController:589–613, **no KM**).

`respetarInicioPrimerRegistro` exists only on `recalcularFechasSecuencia` and is **never** passed as `true` (dead parameter).

---

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: Every same-telar DnD/delete/cambiarTelar overwrites EnProceso `FechaInicio` with `now()`

**File:** `app/Http/Controllers/Planeacion/ProgramaTejido/helper/DateHelpers.php:64-167`
**Also:** `funciones/draganddroptejido.php:91-135`, `ProgramaTejidoOperacionesController.php:267-300`

**Issue:** `recalcularFechasSecuencia` sets `$nuevoInicio = $esEnProceso ? Carbon::now() : $cursor` and then **persists** that as `FechaInicio`. DnD always recalculates the **entire** telar, including the immovable EnProceso row. Dragging any later row therefore:

- Destroys the real start of the order in process.
- Recalculates its `FechaFinal` from now (remaining-hours semantics mixed with historical start).
- Cascades every subsequent start/finish.

Mass calendar **explicitly avoids** writing EnProceso `FechaInicio` (`ProgramaTejidoCalendariosController.php:183-185`). UpdateTejido comments the same rule (`UpdateTejido.php:516-517`). The sequence helper violates it.

**Fix:** Compute EnProceso finish from `now()`, but do not write `FechaInicio` unless the row is *becoming* EnProceso (delete-en-proceso / first remaining). Mirror calendarios:

```php
$nuevoInicio = $esEnProceso ? Carbon::now() : $cursor->copy();
// ...
$baseUpdate = [
    'FechaFinal' => $tmp->FechaFinal,
    'EnProceso' => $i === 0 ? 1 : 0,
    'Ultimo' => $i === ($n - 1) ? '1' : '0',
    'CambioHilo' => $cambioHilo,
    'Posicion' => $i + 1,
    'UpdatedAt' => $now,
];
if (! $esEnProceso) {
    $baseUpdate['FechaInicio'] = $tmp->FechaInicio;
}
```

---

### CR-02: Mass calendar commits partial failures and does not cascade unselected neighbors

**File:** `app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoCalendariosController.php:77-214`
**UI:** `resources/views/modulos/programa-tejido/modal/act-calendarios.blade.php:169-177, 211, 268-277`

**Issue:**

1. `CalendarioId` is bulk-updated for **all** selected IDs first (line 77-78).
2. Date walk is only over the selected subset. Unchecked rows of the same telar keep old starts/ends → overlaps/gaps vs the new calendar.
3. Per-row exceptions increment `$errores` and `continue`; the transaction still `commit()`s (205-214). Result: calendar id changed, dates/lines stale on failed rows, siblings already rewritten.
4. Modal defaults to select-all of the entire table (`getAllRegistrosJson` has no salon/EnProceso filter). Easy to stamp every loom in the plant.

**Fix:** Lock each telar (`lockForUpdate`), load **all** rows of every affected telar (not just selected ids), run `DateHelpers::recalcularFechasSecuencia` (or one shared engine), fail the transaction on any row error, and do not default-check the whole plant. Preserve EnProceso `FechaInicio` as calendarios already tries to do.

---

### CR-03: Inline hilo / VelocidadSTD / EficienciaSTD never mark `$afectaDuracion`

**File:** `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/UpdateTejido.php:80-90`
**Also:** `resources/views/modulos/programa-tejido/scripts/inline-edit.blade.php:592-641`

**Issue:** Changing `hilo` only writes `FibraRizo`. The follow-up PUT writes `velocidad_std` / `eficiencia_std` without setting `$afectaDuracion` / `$afectaFormulas`. HorasProd, FechaFinal, and downstream cascade stay stale until some later sequence recalc (DnD/delete). Neighbors’ `CambioHilo` is also not recomputed.

**Fix:** Treat `hilo`, `velocidad_std`, and `eficiencia_std` as duration inputs:

```php
if (array_key_exists('velocidad_std', $data)) {
    $registro->VelocidadSTD = $data['velocidad_std'] !== null ? (float) $data['velocidad_std'] : null;
    $afectaDuracion = true;
    $afectaFormulas = true;
}
```

Same for `eficiencia_std` and `hilo`. Prefer one PUT that includes hilo+std.

---

### CR-04: `cambiarTelar` builds `Maquina` without Karl Mayer prefix

**File:** `app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoOperacionesController.php:242, 589-613`

**Issue:** Duplicate of `TejidoHelpers::construirMaquinaConSalon` (which **does** map KM). Cross-telar drag to Karl Mayer keeps the previous prefix (`SMI 401`). STD resolution that keys off `Maquina` then looks up SMITH speeds. Workspace fact already documented this bug in TejidoHelpers; the DnD path never calls that helper.

**Fix:** Replace `construirMaquinaSegunSalon` with `TejidoHelpers::construirMaquinaConSalon($registro->Maquina, $nuevoSalon, $nuevoTelar)` and delete the local copy.

---

### CR-05: “Editar fila” opens all cells → N concurrent PUTs, lost updates

**File:** `resources/views/modulos/programa-tejido/scripts/main.blade.php:2056-2058`
**Also:** `inline-edit.blade.php:520-537, 645-675`

**Issue:** `enableInlineEditForAllCellsInRow` mounts ~20 inputs. Blur on each fires `saveInlineField` (150ms) as an independent `PUT` of a **single** field. `UpdateTejido` does `findOrFail` + `saveQuietly` with **no** `lockForUpdate` and no `UpdatedAt` check. Concurrent requests:

- A sets `pedido` and cascades dates.
- B loaded the pre-A row, sets `hilo`, saves, **reverts** A’s pedido/dates.

No queue, no debounce, no in-flight map per `rowId`.

**Fix:** Serialize saves per row (a mutex/queue in JS), send one payload with all dirty fields, or keep single-cell edit only. On the server, `lockForUpdate()` the row (and telar if cascading).

---

## Warnings

### WR-01: `new_position` is a collection index, but the UI sends `data-posicion`

**File:** `resources/views/modulos/programa-tejido/scripts/main.blade.php:2126-2146`
**Also:** `draganddroptejido.php:254-258`

If Posicion has gaps (delete with `Ultimo=1` skips compact — `eliminartejido.php:118-121`; `obtenerSiguientePosicionDisponible` fills holes on insert), UI may send `new_position=4` for a 3-row telar → `validarRangoPosicion` 422, or land on the wrong index.

**Fix:** Always send 0-based index among locked rows (or send `target_id` + `before|after`) and ignore numeric Posicion for splice. Compact on every delete, including Ultimo.

---

### WR-02: Drop targeting includes filter-hidden rows

**File:** `main.blade.php:2148-2158, 2113-2119`

`findClosestRow` / `buildTelarCache` query all `.selectable-row`. Filtered rows are `display:none` (`getBoundingClientRect` → zeros). A drop near the top of the viewport can snap to a hidden row and use its `data-posicion`.

**Fix:** Skip `.filter-hidden` for hit-testing; keep them in the **index** used to compute backend position, or disable DnD while filters hide any row of that telar.

---

### WR-03: Balanceo and mass calendar do not `lockForUpdate` the telar

**File:** `funciones/BalancearTejido.php:268-399`
**Also:** `ProgramaTejidoCalendariosController.php:80-105`

DnD serializes with `lockForUpdate`. Balance save and calendar mass update do not. Interleaving with a drag on the same telar produces crossed dates/positions.

**Fix:** Same lock as DnD (`salon` + `telar` + `orderBy Posicion`) before writing.

---

### WR-04: `empty()` on `'0'` in delete / shared-order / hours fallbacks

**File:** `funciones/eliminartejido.php:59, 65`
**Also:** `DateHelpers.php:70, 79, 93`; `BalancearTejido.php:99, 529`

`empty('0')` is true. `Reprogramar = '0'` skips the move-instead-of-delete path and **deletes**. `OrdCompartida = '0'` skips transfer. `empty($r->HorasProd)` is OK for numeric 0, but string `"0"` vs `"0.00"` behaves differently (`empty("0")` true, `empty("0.00")` false).

This module does not use `empty()` on Prioridad (that bug lives in Liberar Órdenes), but the same footgun is here.

**Fix:** Compare with `=== null || $x === ''` (or a typed cast), never `empty()` on flags that can be `'0'`.

---

### WR-05: EntregaCte input uses `new Date(v)` (TZ / ISO-Z)

**File:** `inline-edit.blade.php:191-200`

`parseSqlDateTimeLocal` exists specifically to treat SQL datetimes as local Mexico time. EntregaCte ignores it. A value with `Z` or a space-datetime that the engine treats as UTC shifts by 6 hours in the widget and then round-trips a wrong payload.

**Fix:** Reuse `parseSqlDateTimeLocal` in `inputFormatter` / `toPayload`.

---

### WR-06: Drag mode and inline mode can both be on

**File:** `main.blade.php:2169-2196` vs `inline-edit.blade.php:846-875`

Enabling DnD does not disable inline (and vice versa). Dragging a row that has open inputs, or capturing click vs dragstart, produces cancelled/partial saves.

**Fix:** Mutually exclusive modes; on DnD enable, `closeInlineEditForRow` for all rows.

---

### WR-07: `cambiarTelar` restores observers with `observe()` after `unsetEventDispatcher()`

**File:** `ProgramaTejidoOperacionesController.php:177, 224, 395, 412`

DnD/Balancear stash the dispatcher and call `restoreObservers`. `cambiarTelar` / `dividirTelar` call `observe(ReqProgramaTejidoObserver::class)` on a null dispatcher (new dispatcher, other listeners dropped). Early 422 at line 223-224 does the same.

**Fix:** `$dispatcher = ReqProgramaTejido::suppressObservers();` … `ReqProgramaTejido::restoreObservers($dispatcher);` in every exit.

---

### WR-08: UpdateTejido swallows line regeneration errors then commits

**File:** `UpdateTejido.php:664-678`

`regenerarLineas` and `actualizarAplicacionEnLineas` are try/caught, logged, and the outer transaction still commits. Header dates/pedido persist; daily lines do not match.

**Fix:** Let those exceptions hit the outer catch (already rolls back) or compensate.

---

### WR-09: `dividirTelar` splits by `FechaInicio` index, not `Posicion`

**File:** `ProgramaTejidoOperacionesController.php:448-470`

`orderBy('FechaInicio')` then `take($posicionDivision)`. If Posicion and FechaInicio diverge (the state this module spends hundreds of lines repairing), the cut is wrong. Loop uses `obtenerSiguientePosicionDisponible` without the unique bump.

**Fix:** Order by Posicion; assign dest positions from a compact 1..N after the move.

---

### WR-10: Mass calendar formula engine disagrees with DnD/balanceo

**File:** `CalendarioController.php:1257-1293` vs `DateHelpers.php:421-541`

Hardcoded `addDays(12)` vs `TejidoHelpers::resolverDiasEntrega` (12 vs 16). `DiasEficiencia` rounded to 4 decimals vs 2. No `EntregaPT` / `EntregaProduc` / `HorasProd` write.

**Fix:** Delete `calcularFormulasDependientesDeFechas`; call `TejidoHelpers::calcularFormulasEficienciaPorContexto`.

---

### WR-11: Nested transaction in `cascadeFechas` while UpdateTejido already holds one

**File:** `DateHelpers.php:200-321` called from `UpdateTejido.php:655-658`

Inner `beginTransaction` is a savepoint. Inner `commit` then `regenerarLineas`. Dispatcher restore uses `setEventDispatcher` not `restoreObservers`. If something saves after restore in the same request, observer duplication / missed listeners depends on call path.

**Fix:** `cascadeFechas` should assume the caller owns the transaction (no inner begin/commit) or detect `transactionLevel()`.

---

### WR-12: Operaciones “move” test uses the wrong payload key and never hits the algorithm

**File:** `tests/Feature/ProgramaTejidoOperacionesTest.php:11-13`

Posts `nueva_posicion` but the API validates `new_position`. Combined with auth-only assertions, DnD has **zero** behavioral coverage.

**Fix:** Authenticated feature tests with three rows on one telar: move down, move up, reject EnProceso, reject drop before EnProceso, assert Posicion 1..N and dates chained.

---

## Info

### IN-01: No SortableJS in this module

Custom HTML5 DnD (~500 lines in `main.blade.php`) plus a parallel cross-telar protocol. High surface, no shared primitive with other boards.

### IN-02: `ProgramaTejidoSecuenciaHelper` is a 32-line passthrough

DnD does not use it (needs unique bump). Delete does (no bump). Ponytail wrapper that hid the inconsistency.

### IN-03: `+10000` / `Id + 1000000` unique-index dodge

Three different parking schemes (negative Id, +10000, Id+1e6). A leftover from a killed connection would make the next bump collide. Prefer deferrable constraint or assign from a temp map in one statement.

### IN-04: `restoreObservers` always `observe()`s again

`ReqProgramaTejido.php:333-338`. After `setEventDispatcher($dispatcher)` the observer is already on that dispatcher; `observe()` can double-register for the rest of the request.

### IN-05: Inline edit uses raw `fetch`, not `window.http`

Project convention in `Claude.md`. Also no `notify.*`. Same in act-calendarios and DnD.

### IN-06: `UpdateTejido` logs full dirty payloads

`UpdateTejido.php:603-616`. Noisy; includes business fields on every inline keystroke-save.

### IN-07: Dead / duplicated UI helpers

`formatDateDisplay` alias, yellowTimeout leftover, `respetarInicioPrimerRegistro` unused, CambioHilo precompute in `cambiarTelar` overwritten by `recalcularFechasSecuencia`.

### IN-08: Static calendar-line cache on `BalancearTejido`

Documented Octane staleness (`BalancearTejido.php:21-24`). Mass calendar / DnD call `calcularFechaFinalDesdeInicio` which reads that cache.

---

## 5. Bugs (position gaps, concurrent moves, calendar TZ, empty('0'))

Covered above: WR-01 gaps, WR-03 concurrent moves, WR-05 / config timezone, WR-04 `empty('0')`, CR-01 EnProceso start, CR-02 partial calendar commit.

Additional concurrency note: DnD `lockForUpdate` is correct for same-telar; `regenerarLineas` runs **after** commit (draganddroptejido.php:152-163), so a second request can lock and rewrite dates before lines catch up (brief inconsistency, not a lost update of header rows).

---

## 6. Over-engineering / ponytail findings

- Four date engines plus two machine-prefix builders plus three position compactors — the “canonical” `TejidoHelpers` is not actually canonical.
- Unique-index parking (`+10000`, `Id+1000000`, `-Id`) instead of one ordered write.
- `ProgramaTejidoSecuenciaHelper` extracted “to DRY” the path that is unsafe for reorder.
- `updateTableAfterDragDrop` (~190 lines) with a HorasProd-specific `setTimeout(0)` visibility hack (`main.blade.php:504-527`).
- `getAllRegistrosJson` + select-all modal instead of “apply calendar to current salon / selected rows in the main grid”.
- Comments already say “ponytail” in UpdateTejido (tamano_clave salon filter) and main.blade.php (filterIndex rebuild).

---

## 7. Missing tests

Present (partial):

- `tests/Unit/BalancearTejidoCalendarioTest.php`, `BalancearTejidoTest.php`, `ProgramaTejidoBalanceoIntegrationTest.php` — calendar walk / auto-balance, **not** DnD.
- `tests/Feature/ProgramaTejidoUpdateTest.php` — pedido → rollos; several cases never call `UpdateTejido::actualizar`.
- `tests/Feature/ProgramaTejidoOperacionesTest.php` — unauth + route name only; **wrong key** `nueva_posicion`.
- `tests/Feature/ProgramaTejidoEliminar*.php` — not-found + FechaFinaliza seal.

Absent (must-have for this surface):

- `DragAndDropTejido::mover`: up, down, noop, EnProceso reject, drop-before-EnProceso, Posicion compact 1..N, EnProceso FechaInicio **unchanged**.
- `cambiarTelar`: insert index, origin compact, KM `Maquina` prefix, CatCodificados sync.
- `actualizarCalendariosMasivo`: full telar cascade, EnProceso FechaInicio preserved, rollback on row failure, subset does not leave overlaps.
- `UpdateTejido`: hilo/std triggers duration; concurrent single-field PUTs; cascade rollback with header.
- `DateHelpers::recalcularFechasSecuencia` vs `cascadeFechas` vs `BalancearTejido::resolverInicioFin` golden dates on the same fixture.
- `empty('0')` on Reprogramar / OrdCompartida.
- JS: `calcTargetPosition` splice math; filter-hidden hit testing (no coverage today).

---

## 8. LOC estimates (physical lines)

| Area | File | Lines |
|---|---|---|
| DnD PHP | `funciones/draganddroptejido.php` | ~222 |
| Cross-telar / split | `ProgramaTejidoOperacionesController.php` | ~568 (cambiarTelar ~285) |
| Inline backend | `funciones/UpdateTejido.php` | ~802 |
| Delete + reprogram | `funciones/eliminartejido.php` | ~553 |
| Date engine | `helper/DateHelpers.php` | ~485 |
| Canonical helpers | `helper/TejidoHelpers.php` | ~746 |
| Sequence wrapper | `helper/ProgramaTejidoSecuenciaHelper.php` | ~32 |
| Flog helper | `helper/UpdateHelpers.php` | ~17 |
| Calendarios controller | `ProgramaTejidoCalendariosController.php` | ~273 |
| Balanceo controller | `ProgramaTejidoBalanceoController.php` | ~124 |
| Balance engine | `funciones/BalancearTejido.php` | ~1199 |
| DnD + table patch JS | `scripts/main.blade.php` | ~3427 (DnD ~520, updateTableAfterDragDrop ~190) |
| Inline JS | `scripts/inline-edit.blade.php` | ~764 |
| Filters JS | `scripts/filters.blade.php` | ~669 |
| Calendarios modal | `modal/act-calendarios.blade.php` | ~300 |
| Balanceo view | `balancear.blade.php` | ~1501 |

Rough totals for this audit surface: **~3.3k PHP** (engines + controllers) and **~6.7k Blade/JS** (grid + modal + balanceo). Date/position logic alone is ~2.5k PHP across four copies.

---

_Reviewed: 2026-09-15T18:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: deep_
