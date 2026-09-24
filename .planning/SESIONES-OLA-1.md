# Sesiones de la Ola 1 — prompts de apertura

Abiertas el 2026-09-24 tras integrar la Ola 0 (fases 10, 11, 11-03 y PT-01) en `claude/friendly-hopper-506bg9`. Cuatro sesiones en paralelo, todas con esa base y el tag `towell-refactor-2026`. Plantilla en `PROTOCOLO-SESIONES.md` §7.

| Sesión | Rama | Contexto / plan |
|---|---|---|
| 12 — Monitoreo cliente (TS) | `claude/12-mon-cliente` | `phases/12-mon-cliente/12-CONTEXT.md` + contrato `phases/11-mon-servidor/11-CONTRACT.md` |
| 14→13 — Pulse + panel `/admin` | `claude/13-14-mon-pulse-panel` | `phases/14-mon-pulse/14-CONTEXT.md`, `phases/13-mon-panel/13-CONTEXT.md`, HANDOFF §2 y §6 de `phases/10-base/HANDOFF.md` |
| 15-01 — Utils TS | `claude/15-01-utils-ts` | `phases/15-fe-fundacion/15-CONTEXT.md` (sección 15-01) |
| PT 01.1→02 — AuthZ + lectura Programa Tejido | `claude/pt-01.1-02` | `phases/01.1-autorizaci-n-en-servidor-programa-tejido/` (plan por escribir), `phases/02-containment-read/02-containment-read-PLAN.md`, decisión `phases/01-guardrails/01-DECISION-PROGRAMA-MUESTRAS.md` §5 |

## Propiedad de archivos en la Ola 1

| Sesión | Dueño de | Prohibido |
|---|---|---|
| 12 | `resources/js/monitoreo/**`, `tests/Js/monitoreo-*`, `resources/views/components/navbar/sections/user-modal.blade.php` (incluye el enlace "Admin" con `@can('admin')` → `route('admin.index')` o `/admin`), **1 línea** de import en `resources/js/app.js` | `resources/js/utils/**`, `tsconfig.json`, `bootstrap.js`, PHP de monitoreo |
| 14→13 | `app/Services/Monitoreo/**`, `app/Models/Sistema/Monitoreo/**`, `app/Http/Middleware/Monitoreo/**`, `app/Http/Controllers/Monitoreo/**`, `app/Listeners/Monitoreo/**`, `app/Mail/Monitoreo/**`, `app/Providers/MonitoreoServiceProvider.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `config/monitoreo.php`, `config/pulse.php`, `composer.json/lock` (solo `laravel/pulse`), migraciones de Pulse, `app/Livewire/Admin/**`, `resources/views/livewire/admin/**`, `resources/views/modulos/admin/**`, `resources/views/vendor/pulse/**`, `routes/modules/admin.php`, `tests/Feature/Monitoreo/**`, `tests/Unit/Monitoreo/**`, `phpstan-baseline.neon` (solo quitar entradas que arregle) | `resources/js/**`, `user-modal.blade.php`, PT |
| 15-01 | `resources/js/utils/**`, `resources/js/types/**`, `tsconfig.json`, `resources/js/tejido/**` (solo tipos), `tests/Js/utils-*`, `resources/js/bootstrap.js` (solo para seguir exponiendo las mismas globals desde los `.ts`) | `resources/js/monitoreo/**`, `app.js`, `vite.config.js`, `package.json`, vistas |
| PT 01.1→02 | Fila PT de `PROTOCOLO-SESIONES.md` + `database/sql/pt_*.sql` | Todo lo demás |

Archivos compartidos: `scripts/ratchet-baseline.json` (cada sesión solo puede **bajar** sus métricas con `npm run ratchet -- --update`; en conflicto gana el menor) y `phpstan-baseline.neon` (solo quitar entradas).

---

## 1. Fase 12 — Monitoreo cliente (TS)

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md y .planning/SESIONES-OLA-1.md (propiedad de archivos de esta ola).

Fase 12 — Monitoreo: captura cliente (TS). IDs: MON-15..20 (+ enlace "Admin" de MON-21).
Contexto: .planning/phases/12-mon-cliente/12-CONTEXT.md. Contrato (fuente de verdad de endpoints, metas y eventos): .planning/phases/11-mon-servidor/11-CONTRACT.md §3–§6. El servidor YA está integrado en la base (fase 11): prueba contra los endpoints reales /telemetria/* además de los tests con fetch mockeado.
Escribe primero .planning/phases/12-mon-cliente/12-01-PLAN.md (formato de .planning/phases/01-guardrails/01-guardrails-PLAN.md) y luego ejecútalo.
Rama: claude/12-mon-cliente (base claude/friendly-hopper-506bg9).

ERES DUEÑO DE: resources/js/monitoreo/**, tests/Js/monitoreo-*, resources/views/components/navbar/sections/user-modal.blade.php (migrar el nombre de dispositivo a POST /telemetria/dispositivo/nombre y agregar el enlace "Admin" visible solo con @can('admin')), y UNA línea de import en resources/js/app.js.
SOLO LECTURA: todo lo demás.
PROHIBIDO: resources/js/utils/**, resources/js/types/**, tsconfig.json, resources/js/bootstrap.js (son de la sesión claude/15-01-utils-ts, en paralelo), vite.config.js, package.json, todo el PHP de monitoreo (sesión claude/13-14-mon-pulse-panel) y el track Programa Tejido. Si necesitas algo ahí: .planning/phases/12-mon-cliente/HANDOFF.md.

Reglas técnicas: no parchear window.fetch (ya lo hacen programa-tejido/index.js y crudo/dashboard.ts); escuchar el evento towell:http-error (lo emitirá utils/http en 15-01) y Livewire.hook('request') para fallos; UUID con crypto.randomUUID() o fallback getRandomValues (la LAN no sirve HTTPS); TS solo con sintaxis borrable (los tests node importan .ts); presupuesto ≤ 5 KB gzip; con el meta towell-telemetria ausente no se hace ninguna request.

Acuerdos: docs y commits en español ("monitoreo: <cambio>"); modo ponytail; NUNCA saltar/desactivar tests; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube.
Antes de push: el hook SessionStart instala dependencias (si no, bash scripts/session-start.sh con CLAUDE_CODE_REMOTE=true); php artisan test; npm run typecheck && npm run test:js && npm run build; npm run ratchet; vendor/bin/phpstan analyse; skill code-review; verifica en navegador con la skill run (login → navegar → filas en SYSMonVista en sqlite local, throw en consola → SYSMonError).
Entregables: código + tests + 12-01-SUMMARY.md (+ HANDOFF.md si aplica). Push a claude/12-mon-cliente. NO abras PR.
```

## 2. Fases 14→13 — Pulse + panel `/admin`

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md y .planning/SESIONES-OLA-1.md (propiedad de archivos de esta ola).

Fases 14 y 13 — Monitoreo: Laravel Pulse y panel /admin. IDs: MON-21..32 (+ SEC-02 y deuda phpstan de MON-A).
Contexto: .planning/phases/14-mon-pulse/14-CONTEXT.md y .planning/phases/13-mon-panel/13-CONTEXT.md. Contrato: .planning/phases/11-mon-servidor/11-CONTRACT.md (§2 tablas, §5 cierre remoto, §6 definiciones de en línea/inactivo/lenta, §7 rutas admin). Resúmenes de lo ya hecho: 11-01/11-02/11-03-SUMMARY.md.
Escribe primero .planning/phases/14-mon-pulse/14-01-PLAN.md y .planning/phases/13-mon-panel/13-01-PLAN.md; ejecuta en este orden:
  0) Deuda previa: .planning/phases/10-base/HANDOFF.md §2 (phpstan de MON-A: @property en MonDispositivo, RegistrarLogout con $event->user null, ErrorRecorder offset 'function', AccesoAdmin ?->) quitando esas entradas de phpstan-baseline.neon; y §6 SEC-02: el owner confirmó que NO hay proxy delante de Laragon → quitar trustProxies(at: '*') de bootstrap/app.php (la IP registrada debe ser la real; test que lo cubra).
  1) Fase 14: Pulse en conexión SQLite dedicada (Pulse no soporta sqlsrv), en /admin/pulse con el Gate admin, recorders según el contexto, fallback PULSE_ENABLED=false documentado.
  2) Fase 13: panel /admin en Livewire (En línea con wire:poll.visible, sesiones, navegación, rendimiento p50/p95, errores con flujo de estado, accesos), cierre remoto con CierreRemotoService::solicitar(), acciones admin auditadas. Reusa app/Livewire/Concerns/ConTabla + x-tabla, x-ui.*, notify. Solo área Sistemas (Gate admin ya existe).
Rama: claude/13-14-mon-pulse-panel (base claude/friendly-hopper-506bg9). Commits separados por paso.

ERES DUEÑO DE: app/Services/Monitoreo/**, app/Models/Sistema/Monitoreo/**, app/Http/Middleware/Monitoreo/**, app/Http/Controllers/Monitoreo/**, app/Listeners/Monitoreo/**, app/Mail/Monitoreo/**, app/Providers/MonitoreoServiceProvider.php, bootstrap/app.php, bootstrap/providers.php, config/monitoreo.php, config/pulse.php, composer.json/composer.lock (solo laravel/pulse), migraciones de Pulse, app/Livewire/Admin/**, resources/views/livewire/admin/**, resources/views/modulos/admin/**, resources/views/vendor/pulse/**, routes/modules/admin.php, tests/Feature/Monitoreo/**, tests/Unit/Monitoreo/**, phpstan-baseline.neon (solo quitar entradas que arregles).
SOLO LECTURA: todo lo demás.
PROHIBIDO: resources/js/** y user-modal.blade.php (sesión claude/12-mon-cliente, que agrega el enlace "Admin"), tsconfig.json, package.json, vite.config.js, config/database.php (gitignored + skip-worktree: la conexión pulse se registra en runtime), y el track Programa Tejido. Si necesitas algo ahí: HANDOFF.md en tu fase.

Acuerdos: docs y commits en español ("monitoreo: <cambio>"); modo ponytail; NUNCA saltar/desactivar tests; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube.
Antes de push: hook SessionStart (o bash scripts/session-start.sh con CLAUDE_CODE_REMOTE=true); php artisan test; vendor/bin/phpstan analyse; npm run build && npm run ratchet; skills security-review y code-review; capturas del panel con la skill run (usuario area=Sistemas → 200, otra área → 403).
Entregables: código + tests + 14-01-SUMMARY.md y 13-01-SUMMARY.md (despliegue: pdo_sqlite en Laragon, archivo storage/pulse/pulse.sqlite, migraciones, optimize). Push a claude/13-14-mon-pulse-panel. NO abras PR.
```

## 3. Fase 15-01 — Utils TS

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md y .planning/SESIONES-OLA-1.md (propiedad de archivos de esta ola).

Fase 15-01 — Fundación frontend: utils en TypeScript. IDs: FE-01..06.
Contexto: .planning/phases/15-fe-fundacion/15-CONTEXT.md (sección 15-01; la 15-02 NO es de esta sesión). Contrato del evento towell:http-error: .planning/phases/11-mon-servidor/11-CONTRACT.md §4.
Escribe primero .planning/phases/15-fe-fundacion/15-01-PLAN.md y luego ejecútalo.
Rama: claude/15-01-utils-ts (base claude/friendly-hopper-506bg9).

Alcance: utils/http.ts (API compatible con http.js, Accept JSON, CSRF, 419 único, emite towell:http-error), utils/notifications.ts (API compatible con notify.*, toasts nativos accesibles aria-live, Swal solo para confirm/loading/validation), utils/format.ts (un solo escapeHtml/debounce/formateadores es-MX), utils/dom.ts (qs/qsa/delegate/onReady), types/global.d.ts, tsconfig con erasableSyntaxOnly + verbatimModuleSyntax, tipar resources/js/tejido/inventario-telas.ts y quitar su exclude. bootstrap.js debe seguir exponiendo exactamente las mismas globals (window.http, window.notify, window.showToast, …) para no romper ninguna vista. NO migres call-sites de vistas en esta fase.

ERES DUEÑO DE: resources/js/utils/**, resources/js/types/**, tsconfig.json, resources/js/tejido/** (solo tipos, sin cambiar comportamiento), tests/Js/utils-*, resources/js/bootstrap.js (solo para importar los .ts y re-exponer las mismas globals).
SOLO LECTURA: todo lo demás.
PROHIBIDO: resources/js/monitoreo/** y resources/js/app.js (sesión claude/12-mon-cliente), vite.config.js, package.json, vistas Blade, PHP, y el track Programa Tejido. Si necesitas algo ahí: .planning/phases/15-fe-fundacion/HANDOFF.md.

Acuerdos: docs y commits en español ("frontend: <cambio>"); modo ponytail; NUNCA saltar/desactivar tests; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube.
Antes de push: hook SessionStart (o bash scripts/session-start.sh con CLAUDE_CODE_REMOTE=true); npm run typecheck && npm run test:js && npm run build; npm run ratchet; php artisan test (sin regresiones en vistas); skill code-review; con la skill run abre 3 pantallas que usen http/notify (p. ej. catálogo de calendarios) y verifica 0 errores de consola.
Entregables: código + tests + 15-01-SUMMARY.md (+ HANDOFF.md si aplica). Push a claude/15-01-utils-ts. NO abras PR.
```

## 4. PT 01.1 → 02 — AuthZ y lectura de Programa Tejido

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026, track Programa Tejido (PT). Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-1.md, .planning/PROJECT.md y .planning/phases/01-guardrails/01-SUMMARY.md.

Fases PT 01.1 (autorización en servidor) y PT 02 (contexto + lectura). IDs: PT-CON-01, PT-READ-01 (+ AuthZ de Planeación de BUG-003).
Rama: claude/pt-01.1-02 (base claude/friendly-hopper-506bg9).

Paso 01.1: escribe .planning/phases/01.1-autorizaci-n-en-servidor-programa-tejido/01.1-PLAN.md y ejecútalo: poner module.permission (middleware EnsureModulePermission, alias module.permission:<accion>,<idrol>) en las rutas de escritura de routes/modules/planeacion.php que todavía no lo tienen (usa el snapshot tests/fixtures/planeacion/programa-tejido/rutas.json y el gap de permisos que detectó PT-01). Decisión del owner: liberar Muestras (muestras.liberar-ordenes.procesar) exige crear del módulo Muestras (idrol 5), no el de Programa (idrol 2). Tests al estilo tests/Feature/PlaneacionMutationAuthorizationTest.php (usuario sin permiso → 403; con permiso → pasa). El snapshot de rutas se actualiza con PT_ROUTES_SNAPSHOT=update y el diff se revisa.

Paso 02: ejecuta .planning/phases/02-containment-read/02-containment-read-PLAN.md incorporando la decisión 01.3 ya aprobada (.planning/phases/01-guardrails/01-DECISION-PROGRAMA-MUESTRAS.md §5):
- capacidades de Muestras en config/planeacion.php (Redbooth B, Marbetes A, Producción A, Descarga TXT B, Finalización B, Longitudes A);
- para cada A: .sql aditivo en database/sql/pt_*.sql con preflight IF COL_LENGTH(...) IS NULL, rollback y nota para el DBA (NO correr migraciones);
- para cada B: guard 422 explícito + ocultar la acción en la UI de Muestras + test de ruta;
- contener los catches silenciosos 1, 4, 5 y 6 del 01-SUMMARY (los tests "…_en_silencio"/"…_reporta_exito" se INVIERTEN, no se borran);
- regla del owner para liberar Muestras: el número de orden lleva prefijo "M" (formato M12345) en CatCodificados.OrdenTejido y en MuestrasPrograma.NoProduccion. Si esa mutación de liberar no cabe en el alcance de 02 (lectura), déjala especificada como requisito con tests pendientes de implementación en .planning/phases/02-containment-read/02-MUESTRAS-LIBERAR.md para PT-05/06, sin tocar la lógica de liberar.
- No cambies el diseño visual (decisión D-E). No toques UI salvo ocultar acciones B en Muestras.

ERES DUEÑO DE: la fila PT de .planning/PROTOCOLO-SESIONES.md (resources/js/programa-tejido/**, app/Http/Controllers/Planeacion/{ProgramaTejido,Utilerias}/**, resources/views/modulos/programa-tejido/**, app/Services/Planeacion/ProgramaTejido/**, app/Actions/Planeacion/**, app/Observers/ReqProgramaTejidoObserver.php, app/Http/Middleware/ProgramaTejidoContext.php, routes/modules/planeacion.php, config/planeacion.php, tests/Feature/Planeacion/**, tests/Unit/Planeacion/**, tests/fixtures/planeacion/**, app/Console/Commands/PlaneacionProgramaTejidoHealthCheck.php, .planning/phases/0*/**) y database/sql/pt_*.sql.
SOLO LECTURA: todo lo demás (EnsureModulePermission incluido: si necesitas cambiarlo, HANDOFF).
PROHIBIDO: bootstrap/**, routes/web.php, resources/js/utils/**, tsconfig.json, package.json, vite.config.js, config/database.php, monitoreo.

Acuerdos: docs y commits en español ("programa tejido: <cambio>"); modo ponytail; NUNCA saltar/desactivar tests; no ejecutar migraciones; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube. Aquí no hay SQL Server: lo que requiera datos reales va como script/runbook para el owner.
Antes de push: hook SessionStart (o bash scripts/session-start.sh con CLAUDE_CODE_REMOTE=true); php artisan test; vendor/bin/phpstan analyse; npm run build && npm run ratchet; php artisan planeacion:programa-tejido-health en sqlite; skills code-review y security-review (AuthZ).
Entregables: código + tests + 01.1-SUMMARY.md y 02-SUMMARY.md (+ HANDOFF.md si aplica). Push a claude/pt-01.1-02. NO abras PR.
```
