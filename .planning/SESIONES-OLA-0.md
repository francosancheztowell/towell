# Sesiones de la Ola 0 — prompts de apertura

Tres sesiones en paralelo, todas con base `claude/friendly-hopper-506bg9`, modo de permisos **auto** y tag `towell-refactor-2026`. Plantilla en `PROTOCOLO-SESIONES.md` §7.

| Sesión | Rama de salida | Plan |
|---|---|---|
| Fase 10 — Base y guardarraíles | `claude/10-base` | `phases/10-base/10-01-PLAN.md` |
| Fase 11 — Monitoreo servidor | `claude/11-mon-servidor` | `phases/11-mon-servidor/11-01-PLAN.md` → `11-02-PLAN.md` |
| PT 01 — Guardrails Programa Tejido | `claude/pt-01-guardrails` | `phases/01-guardrails/01-guardrails-PLAN.md` |

---

## 1. Fase 10 — Base y guardarraíles

```
Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026. Protocolo en .planning/PROTOCOLO-SESIONES.md: léelo primero y respétalo.

Fase 10 — Base y guardarraíles. IDs: BASE-01..12, SEC-01, SEC-02.
Plan: .planning/phases/10-base/10-01-PLAN.md (ejecútalo tarea por tarea).
Contrato: ninguno. Rama: claude/10-base (base claude/friendly-hopper-506bg9).

ERES DUEÑO DE: .github/workflows/frontend-checks.yml, .gitignore, phpunit.xml, phpstan.neon (+ baseline), scripts/ratchet.mjs (+ baseline), tests que hoy fallan en sqlite FUERA de tests/*/Planeacion/**, vistas/servicios muertos listados en el plan, routes/public.php, la ruta tel-bpm/log-debug de routes/modules/tejedores.php, public/towell-*, public/audio/click-sound.js, public/pwa-check.mjs, resources/views/modulos/usuarios/qr.blade.php (+ resources/js/usuarios/**), config/queue.php, config/logging.php (solo defaults), .env.example, tsconfig.json, CLAUDE.md (solo datos obsoletos), .claude/settings.json (hook), package.json/lock y vite.config.js (solo script ratchet, dependencia qrcode y su input).
SOLO LECTURA: todo lo demás.
PROHIBIDO: bootstrap/app.php, bootstrap/providers.php, routes/web.php, app/Http/Controllers/AuthController.php y todo lo de monitoreo (lo hace en paralelo la sesión claude/11-mon-servidor); todo el track Programa Tejido (resources/js/programa-tejido/**, app/Http/Controllers/Planeacion/{ProgramaTejido,Utilerias}/**, resources/views/modulos/programa-tejido/**, routes/modules/planeacion.php, tests/*/Planeacion/**, .planning/phases/0*), que lo hace claude/pt-01-guardrails. Lo que necesites ahí va en .planning/phases/10-base/HANDOFF.md.

Acuerdos: docs y commits en español ("<area>: <cambio>"); modo ponytail (reusar Tests\Concerns\UsesSqlsrvSqlite, app/Console/Commands/DbProfileCommand.php y el método de .planning/phases/04-ux-grid/04-PERF-MEDIDO.md antes de crear nada); NUNCA saltar, desactivar ni marcar incompleto un test para ponerlo en verde; Pint solo en archivos tocados; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md. Aquí no hay SQL Server: lo que requiera datos reales (tiempos de prod, .env de prod) va como runbook para el owner, nunca inventado.

Antes de cada push: composer install / npm ci si faltan; php artisan test; npm run typecheck && npm run test:js && npm run build; phpstan y ratchet cuando existan; skill code-review sobre el diff (security-review para SEC-01).
Entregables: código + tests + .planning/phases/10-base/10-01-SUMMARY.md (conteos antes/después, borrados con evidencia de grep, decisiones pendientes del owner) y HANDOFF.md si aplica. Push a claude/10-base. NO abras PR.
```

## 2. Fase 11 — Monitoreo: esquema y captura servidor

```
Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026. Protocolo en .planning/PROTOCOLO-SESIONES.md: léelo primero y respétalo.

Fase 11 — Monitoreo: esquema y captura servidor. IDs: MON-01..14 (+ ruta /admin placeholder de MON-21).
Planes: .planning/phases/11-mon-servidor/11-01-PLAN.md y después 11-02-PLAN.md.
Contrato (fuente de verdad de tablas, columnas, rutas, config y cierre remoto): .planning/phases/11-mon-servidor/11-CONTRACT.md. Otras sesiones (cliente TS, panel /admin, Pulse) trabajarán contra él: si necesitas cambiarlo, hazlo explícito en tu SUMMARY.
Rama: claude/11-mon-servidor (base claude/friendly-hopper-506bg9). Haz primero el commit de 11-01 (esquema, identidad, accesos, cierre remoto, Gate admin) y luego 11-02.

Decisiones del owner que aplican: panel solo para usuarios del área "Sistemas" en /admin (Gate por Usuario.area normalizada, config monitoreo.areas_admin); logout normal y remoto = Auth::logoutCurrentDevice() (no sacar a las demás tablets del mismo usuario); remember-me siempre activo se conserva; Pulse NO va en esta fase (no soporta SQL Server; lo hace la fase 14).

ERES DUEÑO DE: app/Services/Monitoreo/**, app/Models/Sistema/Monitoreo/**, app/Http/Middleware/Monitoreo/**, app/Http/Controllers/Monitoreo/**, app/Http/Requests/Monitoreo/**, app/Listeners/Monitoreo/**, app/Providers/MonitoreoServiceProvider.php, bootstrap/providers.php, bootstrap/app.php, routes/web.php (solo líneas require), routes/modules/telemetria.php, routes/modules/admin.php, routes/console.php (solo agregar el schedule de poda), config/monitoreo.php, migraciones *_sysmon_* y la de SYSMensajes.ErroresSistema, database/sql/sysmon_*.sql, app/Http/Controllers/AuthController.php, app/Models/Sistema/SYSMensaje.php, MensajesController y mensajes.blade.php, resources/views/errors/500.blade.php (solo código de referencia), resources/views/components/layout-head.blade.php (solo 3 <meta>), resources/views/modulos/admin/index.blade.php (placeholder), app/Support/Http/Concerns/HandlesApiErrors.php (solo agregar report()), tests/Feature/Monitoreo/**, tests/Unit/Monitoreo/**.
SOLO LECTURA: todo lo demás (reusa app/Helpers/device_helpers.php, app/Services/Mantenimiento/ParoTelegramNotifier.php, app/Http/Middleware/EnsureModulePermission.php, tests/Concerns/UsesSqlsrvSqlite.php, la migración 2026_08_24_000001_create_catalogo_calibres_module.php como estilo).
PROHIBIDO: resources/js/**, package.json, vite.config.js, phpunit.xml, .github/**, tsconfig.json (son de claude/10-base o de fases posteriores), config/database.php (gitignored + skip-worktree: la conexión sqlsrv_monitoreo se registra en runtime), y todo el track Programa Tejido. Lo que necesites ahí va en .planning/phases/11-mon-servidor/HANDOFF.md.

Acuerdos: docs y commits en español ("monitoreo: <cambio>"); modo ponytail; NUNCA saltar/desactivar tests; Pint solo en archivos tocados; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md. Aquí no hay SQL Server: migraciones portables (Schema builder) + .sql espejo con índices filtrados para el DBA; tests en sqlite; el valor real de "area" se verifica con un SELECT que dejas documentado para el owner.

Antes de cada push: composer install si falta; php artisan test (suite completa, sin regresiones) y php artisan test --filter=Monitoreo; npm run build; skills security-review y code-review sobre el diff.
Entregables: código + tests + 11-01-SUMMARY.md y 11-02-SUMMARY.md (qué se hizo, cómo desplegar: migración o .sql, variables .env como MONITOREO_ENABLED y MONITOREO_AREAS_ADMIN, pasos para el owner) + HANDOFF.md si aplica. Push a claude/11-mon-servidor. NO abras PR.
```

## 3. PT 01 — Guardrails de Programa Tejido

```
Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026; este es el track Programa Tejido (PT). Protocolo en .planning/PROTOCOLO-SESIONES.md: léelo primero y respétalo. Contexto del track: .planning/PROJECT.md, .planning/phases/01-guardrails/01-CONTEXT.md, 01-RESEARCH.md y 01-VALIDATION.md.

Fase PT 01 — Guardrails. IDs: PT-CON-01, PT-CON-02, PT-DOM-01, PT-DOM-02, PT-ROL-01.
Plan: .planning/phases/01-guardrails/01-guardrails-PLAN.md (tareas 01.1 a 01.5).
Rama: claude/pt-01-guardrails (base claude/friendly-hopper-506bg9).

IMPORTANTE:
- En este entorno NO hay SQL Server (IPs privadas de planta). Lo que el plan pide consultar en vivo (01.2: sys.columns/sys.indexes/sys.foreign_keys; baseline 69/853/0/0 de 01.5; el command de salud contra datos reales) se entrega como script SQL / comando artisan read-only + runbook para que el owner lo corra en Laragon. Nunca inventes esquema ni números: donde falte el dato real, deja el hueco marcado.
- 01.3 es un checkpoint de decisión BLOQUEANTE: prepara las alternativas A (paridad física aditiva) y B (capacidades exclusivas) por capacidad (Redbooth, marbetes, producción, descarga, finalización) con impacto y rollback, escríbelas en .planning/phases/01-guardrails/01-DECISION-PROGRAMA-MUESTRAS.md y NO ejecutes nada que dependa de esa decisión. Continúa con 01.1, 01.4 y la parte de 01.5 que se pueda hacer en sqlite.
- Decisión del owner 2026-09-24 que enmarca el track: Programa Tejido sí migrará a Livewire más adelante (fase 03) SIN cambiar el diseño, solo si mejora rendimiento medido. Esta fase no toca UI.

ERES DUEÑO DE (fila PT del protocolo): resources/js/programa-tejido/**, app/Http/Controllers/Planeacion/ProgramaTejido/**, app/Http/Controllers/Planeacion/Utilerias/**, resources/views/modulos/programa-tejido/**, app/Services/Planeacion/ProgramaTejido/**, app/Actions/Planeacion/**, app/Observers/ReqProgramaTejidoObserver.php, app/Http/Middleware/ProgramaTejidoContext.php, routes/modules/planeacion.php, config/planeacion.php, tests/Feature/Planeacion/**, tests/Unit/Planeacion/**, tests/Fixtures/Planeacion/**, app/Console/Commands/PlaneacionProgramaTejidoHealthCheck.php, .planning/phases/0*/**.
SOLO LECTURA: todo lo demás (Tests\Concerns\UsesSqlsrvSqlite incluido: si necesitas cambiarlo, pídelo en HANDOFF).
PROHIBIDO: .github/**, phpunit.xml, package.json, vite.config.js, tsconfig.json, bootstrap/**, routes/web.php, AuthController y monitoreo (son de claude/10-base y claude/11-mon-servidor, que corren en paralelo).

Acuerdos: docs y commits en español ("programa tejido: <cambio>"); modo ponytail (sin abstracciones nuevas si ya existe scope/helper); NUNCA saltar/desactivar tests; no ejecutar migraciones; Pint solo en archivos tocados; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md.

Antes de cada push: composer install si falta; php artisan test (sin regresiones); skill code-review sobre el diff.
Entregables: tests de caracterización + command read-only + scripts SQL/runbook + 01-DECISION-PROGRAMA-MUESTRAS.md + .planning/phases/01-guardrails/01-SUMMARY.md (qué quedó verificado en sqlite, qué necesita correr el owner en Laragon, qué decisión se espera). Push a claude/pt-01-guardrails. NO abras PR.
```
