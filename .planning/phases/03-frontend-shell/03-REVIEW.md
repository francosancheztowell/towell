---
phase: 03-frontend-shell
reviewed: 2026-09-15T18:00:00Z
depth: deep
files_reviewed: 18
files_reviewed_list:
  - resources/views/modulos/programa-tejido/req-programa-tejido.blade.php
  - resources/views/modulos/programa-tejido/scripts/state.blade.php
  - resources/views/modulos/programa-tejido/scripts/filters.blade.php
  - resources/views/modulos/programa-tejido/scripts/selection.blade.php
  - resources/views/modulos/programa-tejido/scripts/columns.blade.php
  - resources/views/modulos/programa-tejido/scripts/main.blade.php
  - resources/views/modulos/programa-tejido/scripts/inline-edit.blade.php
  - resources/views/modulos/programa-tejido/modal/_shared-helpers.blade.php
  - resources/views/modulos/programa-tejido/modal/_dividir.blade.php
  - resources/views/modulos/programa-tejido/modal/_duplicar-vincular.blade.php
  - resources/views/modulos/programa-tejido/modal/duplicar-dividir.blade.php
  - resources/views/modulos/programa-tejido/modal/act-calendarios.blade.php
  - resources/views/modulos/programa-tejido/modal/marbetes.blade.php
  - resources/views/modulos/programa-tejido/modal/redbooth.blade.php
  - resources/views/modulos/programa-tejido/modal/repaso.blade.php
  - resources/js/programa-tejido/filter-engine.js
  - resources/js/programa-tejido/modal-cache-bootstrap.js
  - resources/js/app.js
findings:
  critical: 4
  warning: 12
  info: 10
  total: 26
status: issues_found
---

# Phase 03: Code Review Report

**Reviewed:** 2026-09-15T18:00:00Z
**Depth:** deep
**Files Reviewed:** 18
**Status:** issues_found

## Summary

Programa Tejido frontend is vanilla JS/jQuery-era Blade, not Livewire and not React. There is no `useState`. State is a pile of `window.*` globals, lexical `let` in the same `<script>` tag, a write-only `PTStore` Map, and a parallel `PT_FILTER_INDEX` Map. The grid is fully SSR'd (~74 columns × N rows) and then mutated in place; there is no DataTables and no AJAX reload of the table body. `scripts/main.blade.php` is ~3,910 lines and inlines state/filters/columns/selection/inline-edit plus the duplicar/dividir modal into one script.

The implementation is not sound. Four defects will ship incorrect selection, wrong date-filter bounds in Mexico, stored/reflected XSS after inline edit or drag-drop, and a "reset vista" that does not actually clear filters. Filter + selection + inline-edit are three loosely coupled stores that desynchronize. There are zero JS tests for this module (Crudo already has `tests/Js/*.mjs`; `filter-engine.js` is a pure function file and still untested).

TypeScript migration of the 8k+ Blade JS lines is **not** justified as a first move. The documented direction is Livewire (`03-shell-livewire-PLAN.md`). The only TS-worthy slice already exists: `resources/js/programa-tejido/filter-engine.js`. First files if anything is extracted: `filter-engine.js` + tests, then `selection` (id-based, not index-based), then filters. Do not transpile `main.blade.php`.

## Architecture (how "useState" actually works)

| Store | Type | Canonical? | Notes |
|---|---|---|---|
| `window.allRows` | `HTMLTableRowElement[]` | Yes (DOM list) | Rebuilt by `refreshAllRows()` / `applyProgramaTejidoFilters()` |
| `window.selectedRowIndex` | number (`-1` empty) | Fragile | Index into `allRows`, **not** a record id |
| `window.inlineEditMode` | boolean | Yes | |
| `let filters` (state.blade.php:31) | array | Yes, lexical | Also copied to `window.filters` in some paths only |
| `let quickFilters` / `dateRangeFilters` / `lastFilterState` | objects | Lexical only | `resetAllView` writes `window.*` and misses these |
| `window.PTStore` | `Map<string, object>` | No | Written on save/delete; **zero subscribers** |
| `window.PT_FILTER_INDEX` | `Map<string, rowData>` | Partial | Custom filters prefer this over DOM; quick filters still hit DOM |
| `window.selectedRowsIds` + `selectedRowsOrder` | `Set` + array | Multi-select only | IDs are `data-id` strings |
| `hiddenColumns` / `pinnedColumns` + Sets | arrays | Yes | Persisted to localStorage + `POST /programa-tejido/columnas` |

Load strategy: one full page of HTML. Reloads: `location.reload()` in recálculo fechas, some modal helpers, act-calendarios. Mutations (inline edit, delete, drag-drop, vincular) patch cells via `innerHTML`. No Livewire. No React.

## Critical Issues

### CR-01: Click handlers close over row index; delete/reorder makes edit/delete/balance hit the wrong record

**File:** `resources/views/modulos/programa-tejido/scripts/main.blade.php:3517-3546` (init) and `:2213-2253` (drag-drop `disable()`)
**Issue:** `assignClickEvents` binds `selectRow(row, i)` where `i` is captured at bind time. After `eliminarRegistro` the DOM and `window.allRows` are rebuilt (`:1727-1756`) and `data-row-index` is rewritten, but handlers are **not** rebound. Left-click then sets `selectedRowIndex` to a stale index.

Concrete failure: rows `[A, B, C]` with handlers 0/1/2. Delete A. `allRows` is now `[B, C]`. Click C still calls `selectRow(C, 2)`. `allRows[2]` is `undefined`. Navbar Eliminar/Líneas/Balancear read `rows[window.selectedRowIndex]` (`:3282-3292`, `:3597-3604`) and no-op or operate on the wrong row. Click B calls `selectRow(B, 1)` — B paints selected, but `allRows[1]` is C, so Eliminar deletes C.

Context-menu path uses live `rows.indexOf(clickedRow)` (`:655-661`) so right-click is correct and left-click is not. `selectedRowIndex === id` string/number mismatches elsewhere in the app are the same class of bug; here the id is never the selection key at all.

**Fix:** Delegate one click on `tbody`, select by `data-id` (always `String`), store `window.selectedRowId`, derive index only when needed.

```javascript
tb.addEventListener('click', (e) => {
  const row = e.target.closest('.selectable-row');
  if (!row || window.dragDropMode || window.multiSelectMode) return;
  const id = String(row.dataset.id || '');
  const rows = Array.from(tb.querySelectorAll('.selectable-row'));
  window.selectRow(row, rows.indexOf(row));
  window.selectedRowId = id;
});
```

After delete/reorder, do not rebind per-row handlers. Toolbar actions: `tb.querySelector('tr.selectable-row[data-id="' + CSS.escape(id) + '"]')`.

### CR-02: Date-range filters are one day off in `America/Mexico_City`

**File:** `resources/views/modulos/programa-tejido/scripts/filters.blade.php:231-254`
**Issue:** Bounds come from `<input type="date">` as `'YYYY-MM-DD'`. `new Date('2026-01-15')` is UTC midnight. `setHours(0,0,0,0)` / `setHours(23,59,59,999)` then run in local time (UTC−6/−5), so:

- `desde` becomes **the previous local day** 00:00 (extra day included)
- `hasta` becomes **the previous local day** 23:59:59 (the selected end date is **excluded**)

Cell dates are parsed with `new Date(y, m-1, d)` (local) in `parseDate` (`:260-278`). Comparing local cell dates to UTC-shifted bounds is wrong. `filter-engine.js` already has `dateInRange()` doing string compare on `YYYY-MM-DD`, but `checkDateFilters` does not use it.

**Fix:**

```javascript
function checkDateFilters(row) {
  for (const [field, range] of Object.entries(dateRangeFilters)) {
    if (!range.desde && !range.hasta) continue;
    const columnName = field === 'fechaInicio' ? 'FechaInicio' : 'FechaFinal';
    const cell = row.querySelector(`[data-column="${columnName}"]`);
    const raw = (cell?.dataset.value || cell?.textContent || '').trim();
    const iso = (raw.match(/^(\d{4}-\d{2}-\d{2})/) || [])[1]
      || (() => { const d = parseDate(raw); return d ? d.toISOString().slice(0, 10) : ''; })();
    if (!window.PTFilterEngine.dateInRange(iso, range.desde, range.hasta)) return false;
  }
  return true;
}
```

### CR-03: Unescaped `innerHTML` of user/server text after inline edit and drag-drop (XSS)

**File:** `resources/views/modulos/programa-tejido/scripts/inline-edit.blade.php:245-252`, `:689-692`, `:763-765`; `scripts/main.blade.php:248-256`, `:303`
**Issue:** Initial SSR was fixed: `req-programa-tejido.blade.php:140-143` uses `e($value)` for free text. Mutations undo that.

- `setCellValue` / `ddSetCellValue` assign `cell.innerHTML = display` with `NombreProyecto`, `FlogsId`, `TamanoClave`, `Rasurado`, etc.
- After save, `displayValue` is the raw input string (`:689-692`).
- `closeInlineEditForRow` fallback does `cell.innerHTML = currentValue` (`:765`).
- Drag-drop `ddFormatCell` default branch is `String(raw)` then `innerHTML` (`:303`).

`escapeHtmlPtModal` exists in `_shared-helpers.blade.php:249-257` and is used when rebuilding rows after vincular (`main.blade.php:2976-2977`). Inline-edit and D&D skip it. A value like `<img src=x onerror=alert(1)>` in Descripción executes in the current session and in any client that receives the field back through D&D/vincular without the helper.

**Fix:** Never use `innerHTML` for text cells. Prefer `textContent`, or:

```javascript
function setCellValue(cell, display, rawValue) {
  cell.replaceChildren();
  cell.textContent = display ?? '';
  if (rawValue == null) delete cell.dataset.value;
  else cell.dataset.value = String(rawValue);
}
```

HTML-only cells (`EnProceso` checkbox, `ULTIMO`) should be built with `createElement`, not string HTML.

### CR-04: "Reset vista" does not clear the real filter store (dual `filters` state)

**File:** `resources/views/modulos/programa-tejido/scripts/main.blade.php:3225-3258`; `scripts/filters.blade.php:4-19`, `:677-710`; `scripts/state.blade.php:31-38`
**Issue:** `state.blade.php` already documents that a lexical binding plus `window.*` created two stores for selection. The same bug was left in place for filters.

- Canonical: `let filters`, `let quickFilters`, `let dateRangeFilters`, `let lastFilterState` (not on `window`).
- `applyColumnFilterManual` (`main.blade.php:1211-1277`) copies into `window.filters`.
- `resetAllView` (bound to `#btnResetColumns`, toast: "Vista restablecida (filtros y columnas)") writes `window.filters = []` / `window.quickFilters = …` / `window.lastFilterState = null`. Those `window.*` keys are usually `undefined`, so the assignments no-op **or** clear the copy while the lexical arrays stay populated.
- Rows are un-hidden in the DOM, but `updateFilterUI()` still reads lexical `filters` → badge stays on. Opening the filter modal still shows the old chips. Next `toggleQuickFilter` applies **old custom filters + new quick filter**. `lastFilterState` (lexical) is untouched, so `applyProgramaTejidoFilters` can also no-op (`filters.blade.php:103-106`).

`resetAllFilters()` does clear the lexical store but does **not** call `updateTotales()` and is not what the navbar reset button runs. The modal also has no Reset control (see WR-06).

**Fix:** One filter store on `window.PTFilters` (or always the lexical one). `resetAllView` must call `resetAllFilters()` / `resetAllFiltersInModal`, set `lastFilterState = null`, then `updateTotales()`. Delete `window.filters`.

## Warnings

### WR-01: REPASO rows cannot be toggle-deselected

**File:** `resources/views/modulos/programa-tejido/scripts/selection.blade.php:5-8`, `:56-57`
**Issue:** Selected REPASO uses `bg-blue-400`; toggle-off requires `bg-blue-700`. Clicking the selected REPASO row does nothing (stays selected).
**Fix:** Toggle on `row.classList.contains('row-selected')` or `window.selectedRowIndex === rowIndex`, not on `bg-blue-700`.

### WR-02: Filtering does not clear a now-hidden selection

**File:** `resources/views/modulos/programa-tejido/scripts/filters.blade.php:154-189`
**Issue:** Hidden rows stay in `window.allRows` and `selectedRowIndex` is unchanged. Navbar Eliminar/Editar/Líneas still target the invisible row. Same class of bug as finalizar-órdenes (number vs string Set), except here the key is a stale index into a list that includes `display:none` rows.
**Fix:** After applying filters, if the selected row has `filter-hidden`, call `deselectRow()`. Prefer `selectedRowId`.

### WR-03: `lastFilterState` skip + filter index makes post-edit re-apply a no-op

**File:** `filters.blade.php:103-106`, `:156-175`; `inline-edit.blade.php:703-706`; `main.blade.php:539-545`
**Issue:** Custom filters read `PT_FILTER_INDEX` (updated on save). Visibility is only recomputed when filter JSON changes. Editing `NombreProyecto` while a contains-filter is active leaves the row visible; calling `applyProgramaTejidoFilters()` again returns immediately. Drag-drop already had to `filterIndex.rebuild()` for this reason (`main.blade.php:539-545`); inline-edit only `updateRow`s and never reapplies.
**Fix:** After `updateRow`, set `lastFilterState = null` and re-run apply, or drop the memo entirely (N ≈ 80 rows).

### WR-04: Three copies of the filter matcher; the extracted engine is unused for dates

**File:** `resources/js/programa-tejido/filter-engine.js` (67 lines); `filters.blade.php:122-150`; `main.blade.php:1292-1361`
**Issue:** `checkFilterMatch` / `groupFiltersByColumn` are inlined twice as fallbacks. `rowMatchesCustomFilters` and `dateInRange` are never called from Blade. Header-column filters use `operator: 'equals'`; the modal uses `'contains'`; both land in the same `filters` array and OR within a column, so mixing them is surprising. Header path's fallback (`applyColumnFilterManual` else branch) ignores quick filters and dates.
**Fix:** One call to `window.PTFilterEngine`. Delete the inlined copies and the unused fallback in `applyColumnFilterManual` (the real function is always in the same script tag).

### WR-05: `resetAllFilters` leaves footer totals stale

**File:** `filters.blade.php:677-710`
**Issue:** Un-hides rows and updates the badge, never `updateTotales()`. `applyProgramaTejidoFilters` does.
**Fix:** `resetAllFilters()` should call `applyProgramaTejidoFilters()` (after `lastFilterState = null`) instead of hand-rolling display.

### WR-06: Filter modal has no reset/close controls (dead handlers)

**File:** `filters.blade.php:366-452`, `:486-496`, `:625-668`
**Issue:** `didOpen` binds `[data-action="reset"]` and `[data-action="close"]`. The HTML never renders those buttons (`showConfirmButton: false`). `resetAllFiltersInModal` / `applyAndCloseProgramaTejidoFilterModal` are unreachable from UI. Users can only remove chips one by one.
**Fix:** Add a "Limpiar" button (`data-action="reset"`) in the modal footer, matching the Liberar órdenes / paros pattern.

### WR-07: `EntregaCte` display drops the time; inputFormatter uses `new Date(string)`

**File:** `scripts/inline-edit.blade.php:189-204` vs `scripts/state.blade.php:126-132`, `:79-86`
**Issue:** `uiInlineEditableFields.EntregaCte.displayFormatter` calls `formatDateDisplay` (date only). After save the cell loses `HH:mm`. `toPayload` sends `YYYY-MM-DDTHH:mm` (Carbon will parse it); `state.blade.php`'s unused `createDateTimeFieldConfig` already converts via `datetimeLocalToSql`. `inputFormatter` uses `new Date(v)` which is implementation-defined for `'YYYY-MM-DD HH:mm:ss'` (Safari).
**Fix:** Use `formatDateTimeDisplay` + `datetimeLocalToSql` from `state.blade.php`. Delete the duplicate config.

### WR-08: `applyTableFilters` detaches non-matching rows from the DOM forever

**File:** `scripts/main.blade.php:3374-3400`
**Issue:** `tb.innerHTML = ''` then appends only matches. Hidden rows are garbage-collected. Empty `catch`. No callers today (`grep` only finds the definition) — a layout leftover that will data-loss if anything calls `window.applyTableFilters`.
**Fix:** Delete it. Filtering must set `display`/`filter-hidden`, not unmount rows.

### WR-09: Global `window.fetch` rewrite (anti-pattern, Muestras footgun)

**File:** `scripts/main.blade.php:6-38`
**Issue:** Patches `window.fetch` for the page lifetime. `url.replace('/programa-tejido', PT_API_PATH)` is first-match, no boundary. Phase 3 research already forbids this (`03-RESEARCH.md` anti-pattern: "no se parchea `window.fetch`"). Livewire `/livewire/update` is safe only because the string is absent; any other asset/API whose path contains that substring is not.
**Fix:** Call helpers with `PT_BASE_PATH` / `PT_API_PATH` concatenated, or `window.http` with explicit URLs. Remove the patch.

### WR-10: JACQUARD quick filter misses AX spelling `JACUARD`

**File:** `scripts/filters.blade.php:63-72`
**Issue:** `val.includes('JACQUARD')`. Workspace fact: AX uses `JACUARD` as well. Those rows never match the salón chip.
**Fix:** `return /JACQ?UARD/.test(val)`.

### WR-11: Navbar "Editar" is a no-op; context "Editar fila" is the real path

**File:** `scripts/main.blade.php:3272-3278` vs `:677-680`, `:2032-2078`
**Issue:** `#btn-editar-programa` only `console.warn`s. Selection enables the button (`selection.blade.php:88-92`). Users click Editar and nothing happens. Context menu calls `editarFilaSeleccionada()`.
**Fix:** Bind the navbar button to `editarFilaSeleccionada()`.

### WR-12: Enter on an inline input can fire two PUTs

**File:** `scripts/inline-edit.blade.php:510-537`, `:645-675`
**Issue:** Enter calls `saveInlineField`. Replacing `cell.innerHTML` detaches the input → `blur` → 150ms later a second `saveInlineField` with the detached node. Two concurrent `PUT /planeacion/programa-tejido/{id}`.
**Fix:** Guard with `cell.dataset.saving = '1'` or abort if `!input.isConnected`.

## Info

### IN-01: `PTStore` is over-engineering (ponytail)

**File:** `scripts/state.blade.php:1-28`; `main.blade.php:3454-3468`
**Issue:** Map + `subscribe`/`notify`, populated 100ms after load. No `subscribe()` callers. Every mutation still walks the DOM. Dead mirror of `PT_FILTER_INDEX`.
**Fix:** Delete `PTStore` or make it the single source and render from it. Do not keep both.

### IN-02: `inlineEditableFields` in `state.blade.php` is dead

**File:** `scripts/state.blade.php:154-176`
**Issue:** Runtime uses `uiInlineEditableFields` in `inline-edit.blade.php:89-226`. Two catalogs, two date stacks, two payload maps. Encoding mojibake in comments (`segÃºn`).
**Fix:** Keep one config object.

### IN-03: `$` / `$$` / `tbodyEl` declared three times

**File:** `state.blade.php:210-212`; `inline-edit.blade.php:234-236`; `main.blade.php:54-56`
**Issue:** `const $` in the shared `<script>` shadows jQuery for the rest of that tag. Modals included **before** the declaration (`main.blade.php:42`) close over the later `$` (querySelector, not jQuery). Current modal code uses `window.jQuery || window.$`, so it survives; any `$(...)` added to duplicar-dividir later will break.
**Fix:** Name it `qs` everywhere (main already does). Do not bind `$`.

### IN-04: Zero JS tests for this module; `filter-engine.js` is the easy win

**File:** `resources/js/programa-tejido/filter-engine.js`; `tests/Js/` (Crudo only)
**Issue:** PHPUnit covers controllers/routes/Blade string assertions (`ProgramaTejidoModalDuplicarDividirBladeTest`). No Playwright. No node:test for `checkFilterMatch`, `dateInRange`, `groupFiltersByColumn`. Crudo already uses `tests/Js/*.mjs` + `node:test`.
**Fix:** Add `tests/Js/programa-tejido-filter-engine.test.mjs` first (equals/contains/empty, dateInRange UTC-safe, OR-within-column). Then a selection helper once CR-01 is extracted.

### IN-05: TS migration — what is justified

Not justified: converting `main.blade.php` (~3,910 lines) or the 3,011-line duplicar-dividir modal to `.ts` while they still live in Blade and mutate DOM. That is a format change, not a design change, and Phase 3 is Livewire.

Justified, in order:
1. `filter-engine.js` → keep/rename `.ts`, add tests (already ESM, already imported from `app.js:5-6`).
2. New `selection.ts`: `selectedId: string`, no indexes.
3. New `filters.ts` that only calls the engine + sets `hidden` classes.
4. Stop there. Do not move columns/D&D/inline-edit until the Livewire shell exists.

`app.js` currently loads `PTFilterEngine` on **every** page. Move the import behind the programa-tejido entry (or a `programa-tejido.ts` Vite input) as part of step 1.

### IN-06: `window.http` unused; 18 raw `fetch` in scripts alone

**File:** `scripts/main.blade.php`, `inline-edit.blade.php`, `columns.blade.php`; `req-programa-tejido.blade.php:19-24`
**Issue:** CLAUDE.md: prefer `window.http`. This module: 0 uses. Recálculo fechas on the page view still hand-rolls CSRF + `r.json()`.
**Fix:** When touching a mutation path, switch that call. Do not mass-rewrite.

### IN-07: Dead functions / flags

- `PT.actions.abrirNuevo` (`main.blade.php:1602-1605`)
- `applyAndCloseProgramaTejidoFilterModal` (`filters.blade.php:671-674`)
- `window.applyTableFilters` (WR-08)
- `columnGroups.*.defaultVisible: true` for all groups (`columns.blade.php:1-28`) → `initializeColumnVisibility` is a no-op hide path
- `promptSavePreset` / `promptLoadPreset` are just save/load hidden columns, not named views (`columns.blade.php:967-978`)
- `modal-cache-bootstrap.js` is only `window.__PT_DEBUG = false`

### IN-08: `PTvsCte` rounding disagrees with the product rule

**File:** `req-programa-tejido.blade.php:104-123`
**Issue:** Learned rule is round half away from zero when decimal ≥ 0.5. Code uses `> 0.50`, so `n.5` truncates.
**Fix:** `if ($parteDecimal >= 0.5)`.

### IN-09: Header-filter unique values skip empties; engine supports `empty`/`notEmpty` with no UI

**File:** `main.blade.php:1049`; `filter-engine.js:22-23`
**Issue:** Cannot filter "sin fecha" / blank salón from the column modal.
**Fix:** Include a `(vacío)` checkbox that pushes `{ operator: 'empty' }`.

### IN-10: Client permissions are cosmetic

**File:** `req-programa-tejido.blade.php:251-289`
**Issue:** Context menu items are gated with `userCan`. `window.eliminarRegistro` / `eliminarEnProcesoRegistro` / `desvincularRegistro` are always defined. Server-side `userCan` in ProgramaTejido controllers is a known auditoria finding (out of this frontend slice, but the JS does not even hide the functions).
**Fix:** Frontend cannot fix auth; do not treat `disabled` as a control.

## Load / duplication / over-engineering (ranked)

1. **SSR whole table + hide columns in JS** (`columns.blade.php` `display:none` after shipping every `<td>`). Not a v1 performance finding, but it is why the page is 3 MB and why column state is so complex.
2. **One 3.9k-line script tag** concatenating 6 partials + a 3k-line modal. No modules, no CSP-friendly hashes, no Vite cache.
3. **Filter pipeline duplicated** (engine + Blade fallback + header-filter fallback + `applyTableFilters`).
4. **PTStore + PT_FILTER_INDEX + DOM `data-value` + `textContent`** — four representations of one cell.
5. **Fetch monkey-patch** instead of passing `$apiPath` into functions.
6. **`lastFilterState` JSON memo**, `columnElementsCache`, `rowCache` WeakMap, `telarCache` — micro-optimizations around 80 rows (the comments even say "ponytail").

## Suggested first repair order

1. CR-01 selection by `data-id` (delegation). This unblocks filter/edit/delete.
2. CR-04 single filter store + make reset real.
3. CR-02 use `dateInRange`.
4. CR-03 `textContent` in `setCellValue` / `ddSetCellValue`.
5. Tests for `filter-engine.js`.
6. Do **not** start a TS rewrite of `main.blade.php`.

---

_Reviewed: 2026-09-15T18:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: deep_
