# Phase 3: Shell Livewire - Research

**Researched:** 2026-08-05
**Domain:** Laravel 12 + Livewire 3 component shell for an existing high-risk Blade/jQuery/fetch module (Programa Tejido), with per-user canary rollout and instant rollback
**Confidence:** HIGH (architecture/patterns — verified directly against this repo's own code), MEDIUM (canary/feature-flag mechanism — no working precedent exists in-repo, see Open Questions)

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-------------------|
| PT-UI-01 | UI v2 es Livewire — componente(s) siguiendo el patrón `Crudo/MachineDetail.php` (datasets grandes como `#[Computed]`) y `UrdEng/ProgramBoard.php` (reorder/drag-drop) | See Architecture Patterns 1-4 and Don't Hand-Roll table — both reference components' exact mechanisms documented with code, plus Phase 4 handoff note on reorder |
| PT-ROL-01 | Cada corte tiene feature flag, telemetría, gate y rollback probado | See Pattern 5, Pitfalls 2-3, and Open Question 1 — no working precedent exists in-repo for the toggle itself, flagged as the phase's primary open design decision |
</phase_requirements>

## Summary

Phase 3 replaces the hand-rolled `fetch()` + DOM-mutation frontend of Programa Tejido/Muestras (`req-programa-tejido.blade.php`, 339 lines + `req-programa-tejido-line-table.blade.php`, 497 lines + `filter-engine.js`) with a Livewire 3 shell, without touching any of the 28 business controllers under `app/Http/Controllers/Planeacion/ProgramaTejido/`. The codebase already has two mature Livewire reference implementations to copy structurally: `App\Livewire\Crudo\MachineDetail` (modal/detail component, `#[Computed]` for large per-request data, DI via `boot()`, `#[Url]` filter state, cache fallback) and `App\Livewire\UrdEng\ProgramBoard` (board/list component consuming a dedicated read-service + action-service pair, drag/drop via SortableJS, `dispatch()`-based toast/modal events consumed by a small TS module). Both patterns are directly transferable to Programa Tejido's shell.

The single hardest technical trap in this phase is **not** UI architecture — it's that `ReqProgramaTejido::getTable()` resolves Programa vs. Muestras via `config('planeacion.programa_tejido_table')`, which today is set per-request by `App\Http\Middleware\ProgramaTejidoContext` based on `$request->is('planeacion/muestras*')`. Livewire component actions and polling requests all funnel through the single shared `POST /livewire/update` endpoint — **not** through `/planeacion/muestras*` — so this middleware's path-sniffing will silently misresolve the table on every Livewire action/poll for a Muestras page unless the component captures and re-applies its surface context on every request (`boot()`/`mount()` time, not relying on the incoming request path). This must be solved for real by Phase 2's `ProgramaTejidoSurface`/`ProgramaTejidoContextResolver` (per PROJECT.md) — Phase 3 planning needs to confirm Phase 2 actually lands a request-path-independent way for the Livewire component to declare/resolve its surface, or build that resolution explicitly inside the component's `mount()`.

The second material finding: **there is no working per-user canary/feature-flag precedent anywhere in this codebase yet.** A structurally similar Livewire migration already exists for Urdido/Engomado (`App\Livewire\UrdEng\ProgramBoard`, thin wrapper blade at `resources/views/modulos/urdido/programar-urdido-livewire.blade.php`), but it is **not wired to any route** — `ProgramarUrdidoController::index()` still unconditionally calls `legacy()` and serves the old Blade view. The only routing convention that exists is a static `/legacy` suffix route (`programar.urdido.legacy`) that always serves old Blade, with no toggle logic feeding into the default `index()` route. No `laravel/pennant`, GrowthBook, or other flag package is installed. Phase 3's canary mechanism (PT-ROL-01: "activable por usuario con rollback inmediato") has to be designed from scratch, most naturally as a config/env-driven allowlist (employee number or role) checked in the controller `index()` method that conditionally renders the Livewire wrapper view vs. the legacy view — consistent with the project's existing `userCan()` / `Usuario->puesto` permission-check idioms, not a new abstraction.

**Primary recommendation:** Build one parent Livewire component (`App\Livewire\Planeacion\ProgramaTejidoBoard` or similar, namespaced under a new `App\Livewire\Planeacion\` directory) following `UrdEng\ProgramBoard`'s shape (constructor-less, DI via `boot()`, `#[Url]` for filter/search/page state, action methods per mutation calling existing controllers/services, `dispatch()` events for toasts/modals), with large row data exposed via a `#[Computed]` method per `Crudo\MachineDetail`'s documented rationale (keeps bulky Programa Tejido rows out of the Livewire snapshot payload). Reuse `UrdEng\ProgramBoard`'s reorder pattern (SortableJS + `moveToPosition`-style action) directly for Phase 4, but Phase 3 only needs the shell/read path — no drag/drop yet.

## Standard Stack

### Core (already installed, do not add anything new)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `livewire/livewire` | v3 (installed) | Component-driven UI, replaces custom fetch/store JS | Already the established pattern for Crudo and UrdEng modules in this exact codebase |
| Laravel 12 | installed | Framework, routing, Blade | Project baseline |
| Tailwind v4 (`@tailwindcss/vite`) | installed | Styling | Project baseline; `UrdEng\ProgramBoard` ships a dedicated isolated CSS file (`resources/css/urd-eng/program-board.css`) — same pattern to follow |
| SweetAlert2 (`window.Swal`) | installed | Toast/alert notifications | `UrdEng\ProgramBoard`'s `dispatch('program-board-notify', ...)` is consumed by `resources/js/urd-eng/feedback.ts`, which prefers `Swal.fire({toast:true,...})` and falls back to `window.notify` |
| SortableJS | installed (used by `resources/js/urd-eng/sortable-board.ts`) | Drag/drop reorder | Reference for Phase 4, not required in Phase 3's shell-only scope |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `window.notify` (`resources/js/utils/notifications.js`) | installed | Fallback toast client if `Swal` absent | `feedback.ts`-style listener already documents this fallback order — reuse verbatim for the new module's JS glue, don't invent a third notifier |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Livewire | Blade delgado + Vite ES modules (the original, now-superseded plan) | Rejected 2026-08-05 by explicit user decision; would require inventing a bespoke JS store/routing layer this codebase doesn't have, duplicating what Livewire already provides |
| Livewire | React/TanStack | Explicitly out of scope per PROJECT.md and REQUIREMENTS.md — no new major frontend dependency |

**Installation:** None — all required packages are already in `composer.json`/`package.json`.

## Architecture Patterns

### Recommended Project Structure
```
app/Livewire/Planeacion/
├── ProgramaTejidoBoard.php     # parent shell component (this phase) — Programa AND Muestras via constructor/mount param, mirrors UrdEng\ProgramBoard's `module` prop pattern
└── (Phase 4+) ProgramaTejidoLineTable.php, filters/toolbar child components — communicate via #[On] events, per Crudo's Dashboard -> MachineFloor -> MachineDetail chain

resources/views/livewire/planeacion/
└── programa-tejido-board.blade.php   # no inline <script>, no @script — enforced by ProgramBoardStructureTest-style test

resources/views/modulos/programa-tejido/
├── req-programa-tejido.blade.php         # UNCHANGED legacy view (kept intact, rollback target)
└── req-programa-tejido-livewire.blade.php  # NEW thin wrapper (<40 lines), mirrors programar-urdido-livewire.blade.php exactly:
                                             #   @extends('layouts.app', ['ocultarBotones' => true])
                                             #   <livewire:planeacion.programa-tejido-board surface="programa|muestras" />
                                             #   @push('scripts') @vite(...) @endpush

resources/js/planeacion/ (new, or reuse resources/js/urd-eng/ patterns)
├── feedback.ts     # program-board-notify / program-board-modal style listeners, Swal-first fallback to window.notify
└── program-board.ts # single Vite entry, code-split heavy features per superseded plan's intent (not required to be perfect in Phase 3, just not regressed)

resources/css/planeacion/
└── programa-tejido.css   # isolated namespace, no !important, no global selectors — mirrors program-board.css intent from the superseded plan
```

### Pattern 1: Parent shell component with DI via boot(), not mount()
**What:** Constructor-free Livewire component; all services injected in `boot(TypeA $a, TypeB $b)`, stored as private properties; `mount()` only normalizes incoming public/`#[Url]` props.
**When to use:** Every new top-level Livewire component in this codebase (both reference components do this).
**Example (from `App\Livewire\UrdEng\ProgramBoard`):**
```php
private ProgramBoardReadService $readService;
private ProgramBoardActionService $actionService;

public function boot(
    ProgramBoardReadService $readService,
    ProgramBoardActionService $actionService,
): void {
    $this->authorizeAccess();
    $this->readService = $readService;
    $this->actionService = $actionService;
}

public function mount(string $module): void
{
    $resolved = ProgramaModulo::resolve($module);
    $this->module = $resolved->value;
    ...
}
```
Programa Tejido's `boot()` should authorize + resolve surface (Programa vs Muestras) the same way, and must NOT rely on `request()->is(...)` for surface detection on subsequent Livewire AJAX calls (see Common Pitfalls).

### Pattern 2: Large per-request data as `#[Computed]`, never a public property
**What:** Any dataset too large/expensive to serialize in Livewire's snapshot (Programa Tejido rows, potentially up to hundreds of rows × ~140 columns) must be a `#[Computed]` method, memoized per request, not a public property.
**When to use:** The row/grid dataset for the board — this directly satisfies PT-UI-01's explicit requirement ("datasets grandes como `#[Computed]`, no propiedad pública").
**Example (from `App\Livewire\Crudo\MachineDetail::detail()`):**
```php
#[Computed]
public function detail(): ?array
{
    if ($this->selectedTelar === null || ... || ! $this->detailLoaded) {
        return null;
    }
    try {
        $detail = $this->provider->detail(...);
        Cache::put($fallbackKey, $detail, now()->addSeconds(...));
        return $detail;
    } catch (Throwable $exception) {
        report($exception);
        $this->detailError = '...';
        $cached = Cache::get($fallbackKey);
        return is_array($cached) ? $cached : null;
    }
}
```
For Programa Tejido, the equivalent computed property should call Phase 2's `ProgramaTejidoReadService` (per `02-containment-read-PLAN.md`), not query `ReqProgramaTejido` directly from the component — keeps read logic in one seam.

### Pattern 3: `#[Url]` for all filter/pagination state, normalized in mount/updated hooks
**What:** Search/status/selected-id/page all live in `#[Url]` typed properties so browser back/forward and reload work without extra state management (this is literally PT-UI-02/Phase 4 concern but the shell in Phase 3 must expose the hooks).
**Example (from `ProgramBoard`):**
```php
#[Url(as: 'q', except: '')]
public string $search = '';

#[Url(except: 'todos')]
public string $status = 'todos';

public function updatedSearch(): void { $this->search = $this->normalizeSearch($this->search); $this->selectedOrderId = null; }
```

### Pattern 4: Events out to a small TS glue module, not framework-level JS state
**What:** Livewire component `dispatch()`s browser events (`program-board-notify`, `program-board-modal`) consumed by a plain `window.addEventListener` in a small `.ts` file; no store, no global mutable state.
**Example (from `resources/js/urd-eng/feedback.ts`):** Swal-first with `window.notify` fallback — reuse this exact fallback order for Programa Tejido's toasts so the CLAUDE.md-documented `window.notify` utility stays the single blessed non-Livewire notification path.

### Pattern 5: Thin route-level canary switch (structural precedent exists, wiring does not)
**What:** `ProgramarUrdidoController` already has separate `index()` and `legacy()` methods and a `/legacy`-suffixed route registered side-by-side with the primary route name — the routing shape for "always-available legacy fallback" exists. What's missing everywhere in this codebase is the actual **conditional** that flips `index()` between the two views per user. Phase 3 must add that conditional — see Open Questions for the concrete mechanism to decide.
**Anti-pattern already flagged by the superseded plan and still valid:** "no se parchea `window.fetch`", "no se construyen rutas reemplazando texto" (i.e., don't do string-replace tricks like the current `ProgramaTejidoContext` middleware's path-based table swap — that pattern is exactly what causes the Muestras Livewire pitfall below, don't replicate it in new code).

### Anti-Patterns to Avoid
- **Path-based surface detection inside a Livewire component or its dependencies:** `ProgramaTejidoContext`'s `$request->is('planeacion/muestras*')` check only works for the initial full-page load; Livewire's own action/polling requests hit `/livewire/update`, not the surface's URL. Any code that repeats this path-sniffing pattern to decide Programa vs Muestras inside a Livewire lifecycle will silently read/write the wrong table on the second interaction.
- **Querying `ReqProgramaTejido` directly from the Livewire component:** Phase 2 built a `ProgramaTejidoReadService`/Resource specifically so the component doesn't reimplement scope logic — use it.
- **New global JS state/store:** the original Blade+Vite plan wanted a hand-rolled `store.js` reducer; Livewire's own component state replaces that need entirely — do not build a parallel JS store.
- **A second notification system:** `window.notify` (project-wide, CLAUDE.md-documented) and the `Swal`-first `feedback.ts` fallback already coexist; adding SweetAlert-only or notify-only code that ignores the fallback order duplicates decisions already made by `UrdEng\ProgramBoard`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Large dataset in Livewire state | New pagination/lazy-load abstraction | `#[Computed]` method (Crudo pattern) + Phase 2's `ProgramaTejidoReadService` pagination | Already solved, already tested pattern in this codebase |
| Reorder/drag-drop (deferred to Phase 4, but shell must not block it) | Custom drag/drop JS | SortableJS via `resources/js/urd-eng/sortable-board.ts` adapted for Programa Tejido, invoking `ProgramaTejidoOperacionesController::moveToPosition` the same way legacy Blade+fetch does today | Explicit instruction from the task: "closest existing analog, should be reused not reinvented" |
| Toast/flash messaging | New toast component | `dispatch()` + `feedback.ts`-style listener, Swal-first / `window.notify` fallback | Matches `UrdEng\ProgramBoard` exactly and the project-wide `window.notify` convention from CLAUDE.md |
| Canary/feature-flag gating | A generic "feature flag service" abstraction, or a flag package | A single config-driven allowlist check (env var list of employee numbers, or a role/puesto check like `usuarioPuedeEditar()`) inside the controller `index()` method | Ponytail/ponytail mode is active; no flag package is installed and none is needed for a single per-module canary toggle — matches existing `userCan()`/`puesto` check idioms |
| Surface (Programa vs Muestras) resolution inside Livewire | Ad hoc `request()->is(...)` checks | Phase 2's `ProgramaTejidoSurface`/`ProgramaTejidoContextResolver` (per `02-containment-read-PLAN.md`), passed explicitly into `mount(string $surface)` the way `ProgramBoard::mount(string $module)` receives its module | Avoids the path-sniffing trap described above; also the exact pattern `UrdEng\ProgramBoard` already uses for its two modules (Urdido/Engomado) |

**Key insight:** Everything Phase 3 needs architecturally already has a working, tested twin in this codebase (`Crudo\MachineDetail` for computed-property/DI/cache-fallback shape, `UrdEng\ProgramBoard` for board/list-with-actions shape). The actual novel work in Phase 3 is (a) wiring Phase 2's surface resolver into a Livewire-safe form, and (b) inventing the canary toggle, because neither has a working precedent yet.

## Common Pitfalls

### Pitfall 1: Muestras surface silently resolves to the wrong table after mount
**What goes wrong:** Initial page load renders correctly (`request()->is('planeacion/muestras*')` matches, middleware swaps `config('planeacion.programa_tejido_table')` to `MuestrasPrograma`), but the first Livewire component action (any `wire:click`, `wire:model` update, or poll) hits `POST /livewire/update`, which does not match `planeacion/muestras*`, so the middleware never swaps the config, and the component's own model queries land on `ReqProgramaTejido` (Programa's table) instead of `MuestrasPrograma`.
**Why it happens:** `ProgramaTejidoContext` middleware makes a per-request decision based on URL path, but Livewire's request lifecycle after the initial mount is a single shared endpoint, decoupled from the page's original URL.
**How to avoid:** The Livewire component must receive its surface explicitly at `mount(string $surface)` time (passed from the Blade wrapper, which does know its own route/URL) and re-apply the correct `config(['planeacion.programa_tejido_table' => ...])` (or, better, call into Phase 2's context resolver directly) at the start of every action method / in `boot()`, not depend on middleware picking it up from the ambient request path.
**Warning signs:** Muestras UI showing Programa rows (or vice versa) only after the first click/interaction, never on first load; `Id` lookups in action methods returning 404/"not found" intermittently for Muestras.

### Pitfall 2: Canary flag has no rollback story if it's just a URL query param
**What goes wrong:** If PT-ROL-01's canary is implemented as `?ui=v2`, any shared/bookmarked link or SSO redirect strips or ignores the query string, and the user silently falls back to legacy without knowing, or worse, a URL is shared to someone not authorized for v2.
**Why it happens:** Query-param flags are stateless and not tied to identity; the superseded plan's own task 03.5 explicitly called for rollback "sin limpiar cache del browser ni revertir BD" (without depending on browser state).
**How to avoid:** Gate on `Auth::user()` identity (employee number allowlist in config, or a `puesto`/role check consistent with `usuarioPuedeEditar()`), decided server-side in the controller before rendering, not a query string the client controls.
**Warning signs:** QA reports "it works when I add `?ui=v2`, but not when I just click the menu link" — the two paths aren't the same code.

### Pitfall 3: Feature flag "off" still loading v2 assets
**What goes wrong:** Success Criteria #2 explicitly requires "Feature flag apagado = cero requests/assets v2 cargados." If the legacy Blade view unconditionally includes the new `@vite('resources/css/planeacion/...')`/`@vite('resources/js/planeacion/...')` tags (e.g., via a shared layout partial), the browser fetches v2 assets even when the user is served the legacy HTML.
**Why it happens:** Easy to accidentally hoist `@push('styles')`/`@push('scripts')` into a layout section that renders regardless of which view (legacy vs livewire wrapper) was dispatched.
**How to avoid:** Keep the `@vite(...)` calls only inside the new thin wrapper view (`req-programa-tejido-livewire.blade.php`), never in the legacy view or a shared layout section; verify with a build/asset-manifest check or a Playwright/HTTP test asserting the legacy response body contains none of the v2 asset paths.
**Warning signs:** Network tab shows `program-board.css`/`.ts`-equivalent chunks loading even when `index()` returned the legacy Blade view.

### Pitfall 4: `#[Computed]` re-runs on every Livewire request unless guarded
**What goes wrong:** Unlike a cached-forever value, `#[Computed]` is memoized only per-request (per Crudo's own code comment: "evaluada al renderizar (memoizada por petición)"). If the read query behind it is expensive (Programa Tejido's up-to-140-column, potentially large row count), every single Livewire action (even unrelated ones like opening a modal) triggers `render()` and therefore a full re-query unless there's an explicit "loaded" boolean gate (`detailLoaded` in `MachineDetail`) preventing it from running until intentionally triggered.
**How to avoid:** Mirror `MachineDetail`'s `detailLoaded` boolean + `loadDetail()` method gate, or ensure the computed method only executes its expensive branch when filter/pagination state actually changed, consistent with how `ProgramBoard::render()` calls `$this->readService->board(...)` fresh every render (acceptable there because the read is cheap/paginated — confirm Programa Tejido's read cost profile from Phase 2 before assuming the same is safe).
**Warning signs:** Slow response times on every unrelated Livewire interaction (opening a modal, toggling a checkbox) on the Programa Tejido board, not just on filter changes.

### Pitfall 5: Legacy view's 497-line line-table partial and jQuery/SweetAlert-driven context menu are not simple to "leave alone"
**What goes wrong:** The legacy `req-programa-tejido.blade.php` view has DOM-ID-driven JS (`contextMenu`, `contextMenuEditar`, etc.) and inline `@php` HTML-building helpers (`$formatValue`) tightly coupled to the surrounding `modulos.programa-tejido.scripts.main` script bundle. If Phase 3 tries to reuse fragments of this Blade (e.g., column formatting logic) inside the new Livewire view without full extraction, it risks re-coupling the new component to the old jQuery-driven DOM contract, defeating the "single state source" success criterion.
**How to avoid:** Treat the legacy view as read-only/frozen for this phase (per Phase 1/2's "rutas y mutaciones legacy se conservan" decision) and build the Livewire markup fresh, porting only the pure-PHP formatting logic (`$formatValue`'s per-field rules) into a small formatter class/trait, not by including the legacy Blade partial.

## Code Examples

### Reorder/position mutation the new component should call, not reimplement (Phase 4, but shell must route to it correctly in Phase 3)
```php
// routes/modules/planeacion.php:214
Route::post('/planeacion/programa-tejido/{id}/prioridad/mover', [ProgramaTejidoOperacionesController::class, 'moveToPosition'])
    ->name('programa-tejido.prioridad.mover');
// Muestras equivalent: routes/modules/planeacion.php:279, name 'muestras.prioridad.mover'
```
Both Programa and Muestras have their own named route for the same controller action — the Livewire component's action method must pick the correct route name based on resolved surface (mirrors `UrdEng\ProgramBoard`'s `moduleEnum()->productionRouteName()` indirection), never hardcode one.

### Existing table-detection risk, verbatim (the thing Phase 3 must not repeat verbatim for Livewire)
```php
// app/Http/Middleware/ProgramaTejidoContext.php
public function handle(Request $request, Closure $next)
{
    if ($this->isMuestrasRequest($request)) {
        config([
            'planeacion.programa_tejido_table' => 'MuestrasPrograma',
            'planeacion.programa_tejido_line_table' => 'MuestrasProgramaLine',
        ]);
    }
    return $next($request);
}

private function isMuestrasRequest(Request $request): bool
{
    return $request->is('planeacion/muestras*')
        || $request->is('planeacion/muestras-line*')
        || $request->is('muestras*');
}
```
```php
// app/Models/Planeacion/ReqProgramaTejido.php
public function getTable()
{
    $override = config('planeacion.programa_tejido_table');
    if (is_string($override) && $override !== '') {
        return $override;
    }
    return $this->table;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| Blade delgado + módulos ES vía Vite, `store.js` reducer, `api.js` fetch wrapper | Livewire component, server-owned state, `dispatch()` events + small TS glue | Decided 2026-08-05 (PROJECT.md Key Decisions) | Phase 3/4 plans fully replan; Phase 03's old `.superseded` file's *tasks* (bootstrap contract, accessible shell, single state source, isolated CSS, canary) still describe the right functional surface, just implemented via Livewire component props/`#[Computed]`/`dispatch()` instead of `routes.js`/`store.js`/`api.js` |
| UrdEng ProgramBoard fully "migrated" | UrdEng ProgramBoard shell exists and is tested at the unit level, but is **not routed** — `index()` still serves legacy Blade unconditionally | Observed directly in current codebase (2026-08-05 read) | Do not assume UrdEng's rollout mechanism is proven in production; only its component *shape* is proven, the canary wiring for Programa Tejido has no working precedent to copy 1:1 |

**Deprecated/outdated:** The `.superseded` plan's `vite.config.js`, `routes.js`, `store.js`, `columns.js`, `formatters.js`, `api.js` file list is entirely obsolete — none of these files should be created. Its accessibility/CSS-isolation/canary *goals* remain valid requirements, just satisfied through Livewire's own component/view/asset structure instead.

## Open Questions

1. **What is the concrete canary mechanism (allowlist source, storage, and toggle UX)?**
   - What we know: No flag package installed; existing precedent is a static `puesto`-based check (`usuarioPuedeEditar()`) and a `/legacy` route suffix that is not wired into any conditional; PROJECT.md's `01-CONTEXT.md` defers exact canary user/role selection to a "checkpoint del dueño" (owner decision point), unresolved as of this research.
   - What's unclear: Whether the canary should be an env-var employee-number allowlist, a new `SYSUsuariosRoles`-driven permission flag (e.g., a new capability under the existing per-module permission system), or a `config/planeacion.php` boolean scoped by role/puesto.
   - Recommendation: Plan should treat this as a Wave-0/first-task decision, not defer it — propose the simplest option consistent with ponytail mode (env var list of employee numbers checked in the controller, e.g., `config('planeacion.programa_tejido_v2_canary_users')`), and flag it explicitly for a human checkpoint given PROJECT.md's own prior deferral of this exact decision.

2. **Has Phase 2 (`02-containment-read`) actually executed and produced `ProgramaTejidoSurface`/`ProgramaTejidoContextResolver`/`config/planeacion.php` by the time Phase 3 executes?**
   - What we know: STATE.md shows Phase 1 as "Ready to execute" and Phase 2 status as "Planned" (not yet executed); `config/planeacion.php` does not exist yet in the current tree (only referenced in the Phase 2 plan's `files_modified`).
   - What's unclear: Phase 3's plan cannot assume these Phase 2 artifacts exist at planning time — it must either declare a hard dependency gate (don't start Phase 3 execution until Phase 2's `ProgramaTejidoContextResolver` lands) or, if Phase 3 is genuinely planned before Phase 2 executes, the Phase 3 plan's tasks must explicitly reference Phase 2's *planned* interface (from `02-containment-read-PLAN.md`) as a contract to build against.
   - Recommendation: Treat Phase 2's context resolver as a hard prerequisite for solving Pitfall 1 correctly; do not have Phase 3 reinvent surface resolution independently.

3. **Does the Livewire shell need to serve Programa and Muestras from one component instance (via a `surface` prop) or two distinct components?**
   - What we know: `UrdEng\ProgramBoard` uses one component with a `#[Locked] public string $module` prop resolved via an enum (`ProgramaModulo::resolve($module)`), reused for both Urdido and Engomado — directly analogous to Programa/Muestras.
   - What's unclear: Whether Programa Tejido's declared-capability differences between Programa and Muestras (6 missing columns, 11 length divergences, per PROJECT.md) are large enough that a single component with conditional capability checks becomes unwieldy versus two thin subclasses/components sharing a trait.
   - Recommendation: Default to the `ProgramBoard` single-component-with-enum-prop pattern for consistency; only split into two components if Phase 1's approved capability-matrix decision (a blocking checkpoint per ROADMAP.md Phase 1) reveals Muestras needs materially different rendering, not just different data.

## Sources

### Primary (HIGH confidence — direct repo inspection)
- `C:\xampp\htdocs\Towell\app\Livewire\Crudo\MachineDetail.php` — `#[Computed]`, `boot()` DI, cache-fallback pattern
- `C:\xampp\htdocs\Towell\app\Livewire\UrdEng\ProgramBoard.php` — board/list shape, `#[Url]`, `dispatch()` events, module-enum pattern
- `C:\xampp\htdocs\Towell\resources\js\urd-eng\feedback.ts` — notification fallback convention
- `C:\xampp\htdocs\Towell\app\Http\Middleware\ProgramaTejidoContext.php` and `app\Models\Planeacion\ReqProgramaTejido.php::getTable()` — surface-detection risk, verified directly
- `C:\xampp\htdocs\Towell\app\Http\Controllers\Urdido\ProgramaUrdido\ProgramarUrdidoController.php` and `routes\modules\urdido.php` — confirms canary routing is unwired (`index()` → `legacy()`)
- `C:\xampp\htdocs\Towell\tests\Unit\Programas\ProgramBoardStructureTest.php` — confirms current test coverage only asserts structural/static properties of the not-yet-routed Livewire shell
- `C:\xampp\htdocs\Towell\.planning\phases\02-containment-read\02-containment-read-PLAN.md` — Phase 2's planned interfaces Phase 3 must depend on
- `C:\xampp\htdocs\Towell\.planning\phases\03-frontend-shell\03-frontend-shell-PLAN.md.superseded` — functional surface to preserve (bootstrap contract, accessible shell, single state source, isolated CSS, canary), implementation approach discarded
- `C:\xampp\htdocs\Towell\resources\views\modulos\programa-tejido\req-programa-tejido.blade.php` and `resources\js\programa-tejido\filter-engine.js` — legacy surface being replaced

### Secondary (MEDIUM confidence)
- None — all findings for this phase were verifiable directly against the repository; no external library research was needed since Livewire is already installed and its usage is fully established in-repo.

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — nothing new to install, all patterns directly observed in working code
- Architecture: HIGH — two working reference components in the same codebase, directly transferable
- Pitfalls: HIGH for the surface-detection issue (verified by reading the actual middleware/model code); MEDIUM for the canary mechanism (no working precedent exists, so the recommendation is a best-fit inference from adjacent code, not a proven pattern)

**Research date:** 2026-08-05
**Valid until:** Effectively pinned to this repo's state, not calendar time — re-verify once Phase 2 actually executes and lands `ProgramaTejidoSurface`/`ProgramaTejidoContextResolver`/`config/planeacion.php`, since Phase 3 planning depends on their real shape, not just the Phase 2 plan's stated intent.
