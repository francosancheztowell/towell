---
status: resolved
trigger: "después de las optimizaciones recientes, al hacer clic en una card de telar y abrir el modal, el usuario reporta que toda la aplicación se traba"
created: 2026-08-03T14:56:26-06:00
updated: 2026-08-03T15:06:14-06:00
---

## Current Focus

hypothesis: resolved — syncPendingDetail now uses an idempotent hidden-state helper
test: executable mutation regression, focused Crudo PHPUnit suite, production asset build, and bounded Chromium verification all passed
expecting: the observer callback terminates, the modal remains responsive, and existing Crudo behavior is preserved
next_action: none

## Symptoms

expected: clic en un telar debe mostrar respuesta inmediata y abrir el modal sin congelar UI; cierre igualmente fluido.
actual: al abrir el modal la aplicación se traba.
errors: no se reportó mensaje de error.
reproduction: abrir /Crudo y hacer clic en cualquier card de telar para abrir el detalle.
started: observado después de los cambios recientes de rendimiento/modal en el worktree actual.

## Eliminated

## Evidence

- timestamp: 2026-08-03T14:58:19-06:00
  checked: repository state and history
  found: the worktree contains extensive uncommitted edits across the Crudo component, provider, repository, modal Blade, card, TypeScript, CSS, and tests; HEAD cfa75d19 follows the modal extraction commit 64b08425
  implication: recent uncommitted performance/modal work is the primary change surface and must be preserved while isolating the regression

- timestamp: 2026-08-03T14:58:19-06:00
  checked: source search for requested hotspots
  found: MachineDetail::open exists in app/Livewire/Crudo/MachineDetail.php; dashboard.ts has both a global body MutationObserver and a separate audit observer plus a pending overlay
  implication: backend and browser-side competing hypotheses are both grounded in the current code and need separate measurements

- timestamp: 2026-08-03T15:02:41-06:00
  checked: complete dashboard.ts observer and pending-overlay path
  found: auditDefectObserver observes document.body for childList and hidden attributes; every callback calls syncPendingDetail(), which unconditionally assigns pending.hidden=true whenever a modal is present
  implication: the observer can observe the hidden write produced by its own callback and is a specific candidate for an infinite microtask loop beginning exactly when Livewire adds the modal

- timestamp: 2026-08-03T15:02:41-06:00
  checked: MachineDetail::open execution order
  found: open synchronously calls dashboard detail first and then Flog lookup before Livewire can return/render the modal
  implication: sequential SQL can increase response latency but cannot by itself explain a browser that remains frozen after DOM insertion; it must still be timed independently

- timestamp: 2026-08-03T15:12:24-06:00
  checked: isolated Chromium reproduction of the exact observer/write pattern, bounded by disconnecting after 1000 callbacks
  found: a single initial pending.hidden=true produced 1000 observer callbacks and 1000 hidden mutations in 2.8 ms before the safety disconnect
  implication: without the artificial disconnect the browser continuously drains microtasks and starves rendering, timers, input, and modal close; this directly reproduces the reported whole-application freeze

- timestamp: 2026-08-03T15:16:08-06:00
  checked: production SQL timing controls for telar 201 on 2026-08-03
  found: raw detail SQL completed in 417.79 ms (one 409.69 ms query, empty detail); uncached Flog lookup completed in 42.4 ms (one 36.95 ms query, 448-byte result)
  implication: MachineDetail's sequential lookups can add about 460 ms before response in this sample, but they terminate normally and are far below the unbounded browser loop

- timestamp: 2026-08-03T15:20:37-06:00
  checked: executable Node mutation-queue regression after the fix
  found: the first hide queued exactly one observer callback; the callback performed no second write and the queue terminated; 1 test passed in 275.715 ms
  implication: the fix directly breaks the self-observation cycle while preserving the initial overlay hide

- timestamp: 2026-08-03T15:06:14-06:00
  checked: bounded Chromium reproduction after adding the idempotence guard
  found: the same observer pattern terminated after 2 callbacks and 2 mutation records in 11 ms, instead of reaching the artificial 1000-callback cutoff in 2.8 ms
  implication: real browser mutation delivery is now finite and no longer starves rendering, input, timers, or modal close

- timestamp: 2026-08-03T15:06:14-06:00
  checked: focused Crudo PHPUnit suite and final production build
  found: 51 PHP tests passed with 287 assertions; the standalone Node regression passed 1 of 1; Vite built 105 modules successfully
  implication: the fix compiles into the production bundle and preserves the tested Crudo backend, Livewire, Flog, repository, layout, and status behavior

## Resolution

root_cause: resources/js/crudo/dashboard.ts observes hidden mutations across document.body, and once the modal exists every observer callback invokes syncPendingDetail(), which unconditionally reassigns pending.hidden=true. Chromium emits a mutation even when the boolean attribute is already present, so the callback observes its own write forever.
fix: added an idempotent hidePendingDetail helper and used it in syncPendingDetail so hidden is written only when transitioning from visible to hidden; added an executable regression that models browser mutation delivery and fails if the queue does not terminate
verification: before fix Chromium reached 1000 self-generated callbacks in 2.8 ms; after fix the browser reproduction terminated after 2 callbacks in 11 ms. Crudo PHPUnit passed 51 tests/287 assertions, Node regression passed 1/1, Vite production build passed with 105 modules, and git diff --check passed.
files_changed:
  - resources/js/crudo/dashboard.ts
  - resources/js/crudo/pending-detail.ts
  - tests/Js/crudo-pending-detail.test.mjs
