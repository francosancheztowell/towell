# Ventas Safari compatibility

## Objective
Make the Ventas dashboard build and layout compatible with Safari 15.4 and newer.

## Problem / Why
The user asked to make the Ventas module compatible with Safari. The dashboard uses Safari 15.4-era CSS features (`:has()` and dynamic viewport units), while the production build target is implicit. Pin an explicit browser target and retain a static viewport fallback.

## Scope and constraints
- Branch: `fix/ventas-safari-compatibility`.
- Scope: Vite build target and Ventas dashboard viewport CSS only.
- Supported minimum: Safari 15.4 (first Safari release supporting `:has()` and dynamic viewport units; source: WebKit Safari 15.4 release notes).
- Do not change sales behavior/data or broaden compatibility claims to older Safari.
- TDD: off (project convention from adjacent Ventas work); runner: `npm run test:js`.
- Delivery strategy: ask-on-risk; forecast: under 100 authored lines.

## Tasks
- [ ] T1 Set explicit Safari 15.4 Vite target and add `100vh` fallbacks before dynamic `100dvh` sizing in the Ventas dashboard. Route: delegated direct (two non-trivial source files; writer trigger).

## Acceptance criteria
- Compiled Ventas entry targets Safari 15.4 or newer.
- Older WebKit parsing viewport sizing can use `100vh`; capable browsers override it with `100dvh`.
- Existing sales JS tests, typecheck, build, and ratchet pass.

## Checks
- `npm run test:js`
- `npm run typecheck`
- `npm run build`
- `npm run ratchet`
- Safari/WebKit runtime visual check if a Safari runner is available.

## Progress
- Explored the Ventas dashboard; found `:has()` and `100dvh` in `resources/css/ventas/dashboard.css`; Vite currently has no explicit browser target.
- T1 pending.

## Next step
Implement T1, run checks, inspect built output for Safari target, and record commit evidence.

## Relevant files
- `vite.config.js` — Vite production build options.
- `resources/css/ventas/dashboard.css` — Ventas dashboard sizing and styles.
