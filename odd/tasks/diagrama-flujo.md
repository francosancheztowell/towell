# Interactive flow diagram (SVG)

## Objective
Reusable, interactive SVG flow diagram (nodes + edges with kg labels), modeled on the Kardex
"Flujo de almacenes" diagram (`Kardex_Materia_Prima_y_Produccion_en_Proceso_Agosto_2026.html`, `flow()`),
so any module can render process flows (warehouse → warehouse) and drill down.

## Why
The Kardex HTML builds the SVG as hand-placed string concatenation with no interactivity on the diagram.
We want the same look, but declarative (nodes/edges as config), interactive, and TS-only.

## Scope
- Branch: `feat/diagrama-flujo`.
- Renderer: `resources/js/componentes/diagrama-flujo/` (types, pure geometry/layout helpers, SVG render, interactions).
- Demo: `/dev/ui-kit` (local only) with the Kardex August 2026 example as a static fixture.
- Out of scope: backend/data source (pending: SQL Server vs Excel), choosing the final module, auto-layout (elkjs).

## Constraints
- TypeScript only, no new dependencies, no inline `<script>` JS in Blade (page bundle under `resources/js/modulos/**/index.ts` or the ui-kit's existing pattern).
- Use `notify.html` / `escapeHtml` / `formatNumber` from utils; no raw `fetch`, no `onclick=`.
- Light/dark aware via Tailwind tokens / CSS variables; keyboard accessible (nodes focusable, Enter opens detail).
- Ratchet must not go up.
- TDD: off (no project setting); ordinary checks. Runner: `node --test` via `npm run test:js`.

## Tasks
- [x] T1 Diagram model + pure geometry (edge path, label position, net/gross) with node tests — delegated writer
- [x] T2 SVG renderer + interactions (hover highlight path, click node/edge → callback, tooltip, keyboard) — same writer
- [x] T3 ui-kit demo with Kardex fixture and drill-down via `notify.html` — same writer

## Acceptance criteria
- Diagram renders the Kardex layout (MP/MPBB → Urdido → Julios → Tejido → Crudo, trama, goma) from config only.
- Hover on a node dims unrelated nodes/edges; hover on edge highlights it and its ends.
- Click (or Enter) on node/edge fires a callback with its data; demo shows a detail dialog.
- Labels always on top with halo; scrolls horizontally on narrow screens.

## Checks
`npm run typecheck`, `npm run test:js`, `npm run build`, `npm run ratchet`.

## Progress
- Created 2026-10-09. Route: delegated direct (writer trigger: 3+ new non-trivial TS files).

## Verification (commit 4e529c86)
- npm run typecheck: ok; npm run test:js: 496 pass / 0 fail (16 new in diagrama-flujo.test.ts); npm run build: ok; npm run ratchet: ok (no metric went up).
- Dark mode: CSS variables in diagrama-flujo.css, switched under `.dark` / `[data-theme="dark"]` (app has no dark mode today).
- ui-kit loads its bundle via `@push('scripts')` + `@vite(resources/js/modulos/dev/ui-kit/index.ts)`.
- Not verified in a browser (visual check pending).

## Next step
Visual check at /dev/ui-kit#flujo; then choose data source and module.
