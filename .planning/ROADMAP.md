# Roadmap: Towell — Refactor integral 2026

## Overview

Llevar Towell a mejor rendimiento, estructura y UX sin romper planta: monitoreo real de dispositivos/sesiones/errores/rendimiento (panel `/admin`), fundación frontend en TypeScript sin librerías viejas, sistema de componentes unificado, migración del JS inline a módulos TS y a Livewire donde aplique, performance backend medida, arquitectura/seguridad y retiro de legado con evidencia.

El milestone previo **Programa Tejido (PT)** sigue vivo como un **track** de este roadmap (fases 01–07 + 01.1, sin renumerar). Las fases nuevas son 10–21.

Reglas transversales:
- **Ponytail:** nada nuevo si ya existe un helper/scope/componente que lo resuelve; los duplicados apuntan al original.
- **Medir antes de optimizar:** ninguna optimización sin número antes/después (fase 10 baseline + telemetría de fase 11–14 + `phases/04-ux-grid/04-PERF-MEDIDO.md`).
- **Livewire donde la guía dice sí** (`docs/cerebro-towell/Arquitectura/livewire-cuando-si-cuando-no.md`) **+ Programa Tejido** (decisión 2026-09-24: migra sin cambiar el diseño, solo si mejora rendimiento).
- Ejecución en **sesiones paralelas** según `.planning/PROTOCOLO-SESIONES.md` (propiedad de archivos, orden de merge, gates).

## Tracks

| Track | Fases | Qué resuelve |
|---|---|---|
| BASE | 10 | CI PHP+JS, ratchet de deuda, línea base medida, higiene |
| MON | 11, 12, 13, 14 | Monitoreo: dispositivos, sesiones, navegación, rendimiento, errores, accesos, cierre remoto, Pulse |
| FE | 15 | Utils TS, quitar jQuery/Select2/Toastr, Vite, regresiones Tailwind v4 |
| DS | 16 | Sistema de componentes Blade/Livewire |
| UX | 17 | Auditoría UX exhaustiva + correcciones globales |
| PERF | 18 | Drivers, OPcache, N+1, queries, índices |
| MIG | 19 | JS inline → TS por módulo (+ Livewire donde aplica) |
| ARQ/SEC | 20 | Services fuera de controllers, folios, errores JSON, AuthZ |
| ADOP | 21 | Retiro de legado con telemetría, docs |
| PT | 01–07, 01.1 | Programa Tejido (milestone previo) |

## Olas

| Ola | Sesiones paralelas | Gate de entrada |
|---|---|---|
| 0 | `10-base` · `pt-01-guardrails` · `11-mon-servidor` | — |
| 1 | `12-mon-cliente` · `14-mon-pulse` → `13-mon-panel` · `15-01-utils-ts` · PT `01.1` → `02` | G0 |
| 2 | `13-mon-panel` (si falta) · `15-02-librerias` · `16-componentes` · `20-01` → `20-02` → `20-03` · `18-01-perf-infra` · `17-01-auditoria-ux` · PT `04-perf` (cortes 4–7) → `03` | G1 |
| 3 | `19-01`…`19-10` (3–5 activas, priorizadas por telemetría) · `17-02-ux-global` · PT `05` → `04-ux` → `06` | G2 |
| 4 | `21-adopcion` · PT `07` | G3 + ≥ 30 días de telemetría |

Gates G0–G4: ver `PROTOCOLO-SESIONES.md` §8.

## Phases

### Track BASE

- [ ] **Phase 10: Base y guardarraíles** — CI con PHP, larastan, ratchet, línea base, higiene, SEC-01/02.
  **Requirements:** BASE-01..12, SEC-01, SEC-02 · **Plan:** `phases/10-base/10-01-PLAN.md`
  **Success:** CI PHP+JS verde y obligatorio sin tests saltados; ratchet commiteado; `10-BASELINE.md` con runbook; higiene aplicada.

### Track MON (pieza central)

- [x] **Phase 11: Monitoreo — esquema y captura servidor** — tablas `SYSMon*`, identidad de dispositivo, eventos de acceso, rate limit de login, errores PHP, endpoints de telemetría, cierre remoto, alertas Telegram, gate `admin` (área Sistemas).
  **Requirements:** MON-01..14 · **Contrato:** `phases/11-mon-servidor/11-CONTRACT.md` · **Plans:** `11-01-PLAN.md` (esquema + identidad + accesos), `11-02-PLAN.md` (errores + telemetría + cierre remoto + alertas)
- [ ] **Phase 12: Monitoreo — captura cliente (TS)** — latido, vistas con Navigation Timing, errores JS/Livewire/red, nombre de dispositivo.
  **Requirements:** MON-15..20 · **Context:** `phases/12-mon-cliente/12-CONTEXT.md`
- [ ] **Phase 13: Monitoreo — panel `/admin`** — En línea (wire:poll), sesiones, navegación, rendimiento, errores, accesos, cierre remoto.
  **Requirements:** MON-21..28 · **Context:** `phases/13-mon-panel/13-CONTEXT.md`
- [ ] **Phase 14: Monitoreo — Pulse** — Pulse en conexión SQLite dedicada bajo `/admin/pulse`.
  **Requirements:** MON-29..32 · **Context:** `phases/14-mon-pulse/14-CONTEXT.md`

**Success F1 (11–14):** overhead p95 < 5 ms/request; kill switch probado; error PHP → `SYSMonError` + Telegram; error JS → fila; cierre remoto saca solo esa tablet; logins fallidos y bloqueos visibles; usuario fuera de Sistemas → 403 en `/admin/*`.

### Track FE / DS / UX

- [ ] **Phase 15: Fundación frontend** — 15-01 utils TS (http, notifications con toast nativo, format, dom, tipos globales); 15-02 Tom Select en lugar de Select2, quitar jQuery/Toastr, Vite sin vendor global + inputs por glob, CDN → npm, regresiones Tailwind v4.
  **Requirements:** FE-01..12 · **Context:** `phases/15-fe-fundacion/15-CONTEXT.md`
- [ ] **Phase 16: Sistema de componentes** — evolucionar `components/ui/*`: `<dialog>`, tabla, field, botón, badge, loader, empty, flash, filter-bar; galería `/dev/ui-kit`; piloto catálogos atadores.
  **Requirements:** DS-01..12 · **Context:** `phases/16-componentes/16-CONTEXT.md`
- [ ] **Phase 17: UX** — 17-01 auditoría exhaustiva (top 25 pantallas por telemetría); 17-02 correcciones globales (flash, títulos, h1, zoom, clic derecho, lang/es, páginas de error, contraseña, 419, offline, a11y).
  **Requirements:** UX-01..18 · **Context:** `phases/17-ux/17-CONTEXT.md`

### Track PERF / MIG / ARQ-SEC

- [ ] **Phase 18: Performance backend** — 18-01 drivers, OPcache, `moduleNameForRoute`, lazy loading, queries lentas; 18-02 N+1/no-sargables/índices dentro de cada 19-xx.
  **Requirements:** PERF-01..12 · **Context:** `phases/18-perf/18-CONTEXT.md`
- [ ] **Phase 19: Módulos — JS inline → TS** — receta única, 10 sesiones verticales (19-01 Urd/Eng … 19-10 Trazabilidad/Crudo/Ventas).
  **Requirements:** MIG-<MOD>-* · **Context:** `phases/19-modulos/19-CONTEXT.md`
- [ ] **Phase 20: Arquitectura y seguridad** — movimientos de services, folios, turnos, JSON 5xx central, AuthZ auditar → enforce, quitar `getMessage()`.
  **Requirements:** ARQ-01..05, SEC-04..07 · **Context:** `phases/20-arq-sec/20-CONTEXT.md`

### Track ADOP

- [ ] **Phase 21: Adopción y limpieza** — retiro de legado con 0 hits, dependencias muertas, docs.
  **Requirements:** ADOP-01..06 · **Context:** `phases/21-adopcion/21-CONTEXT.md`

### Track PT — Programa Tejido (milestone previo, sin renumerar)

**Orden aprobado 2026-09-24 (decisión D-E):** `01 → 01.1 → 02 → 04-perf (cortes 4–7) → 03 → 05 → 04-ux → 06 → 07`.
PT **sí** migra a Livewire, **sin cambiar el diseño visual**; la migración (03/04) solo se da por buena si **mejora** TTFB, KB de HTML y tiempo de interacción contra `phases/04-ux-grid/04-PERF-MEDIDO.md`, con paridad visual (capturas lado a lado). La telemetría de PT-ROL-01 la aporta `SYSMonVista` (fase 11).

- [x] **Phase 1: Guardrails** (sqlite; live pendiente) — Congelar contratos, esquema, capacidades e invariantes antes de tocar código.
  **Requirements:** PT-CON-01, PT-CON-02, PT-DOM-01, PT-DOM-02, PT-ROL-01 · **Plan:** `phases/01-guardrails/01-guardrails-PLAN.md`
  **Success:** suite de caracterización verde; command read-only de salud; decisión aprobada sobre las 6 columnas y 11 longitudes divergentes de Muestras (checkpoint 01.3 bloqueante).
- [ ] **Phase 1.1: Autorización en servidor (Programa Tejido)** — `module.permission` en las ~10 rutas de escritura de Planeación que faltan. Carpeta `phases/01.1-autorizaci-n-en-servidor-programa-tejido/` (plan por escribir).
- [ ] **Phase 2: Contexto + lectura** — read-seam (Request/ReadService/Resource) paginado y proyectado sin tocar mutaciones. **Plan:** `phases/02-containment-read/02-containment-read-PLAN.md`
- [ ] **Phase 4-perf: cortes medidos** — cortes 4–7 de `04-PERF-MEDIDO.md` (1–3 ya aplicados). Independientes de Livewire.
- [ ] **Phase 3: Shell Livewire** — UI v2 en Livewire con el **mismo diseño**, canary por `numero_empleado`, rollback inmediato. Patrón `Crudo/MachineDetail.php` (`#[Computed]`). **Plan:** `phases/03-frontend-shell/03-shell-livewire-PLAN.md`
- [ ] **Phase 5: Mutaciones** — FormRequests + servicios por caso de uso; PT-DUP-01..04; N+1 de `store()`. **Plan:** `phases/05-mutations/05-mutations-PLAN.md`
- [ ] **Phase 4-ux: UX/grid** — tabla accesible con estados explícitos, reorder con patrón `UrdEng/ProgramBoard.php`; **sin rediseño**. Plan por replanear (`.superseded` asumía Blade+Vite).
- [ ] **Phase 6: Límites operacionales** — secuencia, grupos, balanceo, integraciones, cada uno su PR y gate. **Plan:** `phases/06-operational-boundaries/06-operational-boundaries-PLAN.md`
- [ ] **Phase 7: Adopción y limpieza PT** — retiro de legacy con telemetría de cero uso. **Plan:** `phases/07-adoption-cleanup/07-adoption-cleanup-PLAN.md`

Gate PT (sin cambios): tests de contrato y dominio pasan; invariantes SQL se mantienen; build pasa; rollback del flag probado; Programa y Muestras evaluados explícitamente; sin tocar consumidores fuera del alcance; evidencia de UAT.

## Progress

| Phase | Track | Ola | Plans | Status | Completed |
|---|---|---|---|---|---|
| 10. Base | BASE | 0 | 0/1 | En ejecución (sesión `claude/10-base`) | - |
| 11. Mon servidor | MON | 0 | 2/2 | Completa, integrada (despliegue pendiente del owner) | 2026-09-24 |
| 12. Mon cliente | MON | 1 | 0/TBD | Context listo | - |
| 13. Mon panel | MON | 1–2 | 0/TBD | Context listo | - |
| 14. Mon Pulse | MON | 1 | 0/TBD | Context listo | - |
| 15. FE fundación | FE | 1–2 | 0/2 | Context listo | - |
| 16. Componentes | DS | 2 | 0/TBD | Context listo | - |
| 17. UX | UX | 2–3 | 0/2 | Context listo | - |
| 18. Perf | PERF | 2–3 | 0/2 | Context listo | - |
| 19. Módulos TS | MIG | 3 | 0/10 | Context listo | - |
| 20. Arq/Sec | ARQ/SEC | 2–3 | 0/3 | Context listo | - |
| 21. Adopción | ADOP | 4 | 0/1 | Context listo | - |
| PT 1. Guardrails | PT | 0 | 1/1 | Completa en sqlite, integrada; runbook Laragon pendiente; decisión 01.3 aprobada | 2026-09-24 |
| PT 1.1 AuthZ | PT | 1 | 0/TBD | Por planear | - |
| PT 2. Lectura | PT | 1 | 0/1 | Planned | - |
| PT 4-perf | PT | 2 | 0/1 | Medido, cortes 4–7 pendientes | - |
| PT 3. Shell Livewire | PT | 2 | 0/1 | Planned (mismo diseño) | - |
| PT 5. Mutaciones | PT | 3 | 0/1 | Planned (ampliar con PT-DUP-*) | - |
| PT 4-ux | PT | 3 | 0/TBD | Por replanear | - |
| PT 6. Límites | PT | 3 | 0/1 | Planned | - |
| PT 7. Adopción PT | PT | 4 | 0/1 | Planned | - |
