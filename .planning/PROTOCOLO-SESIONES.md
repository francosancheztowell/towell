# Protocolo multi-sesión — Refactor integral 2026

**Aprobado:** 2026-09-24 (owner). **Integrador:** la sesión que mantiene `ROADMAP.md` / `STATE.md` / `REQUIREMENTS.md` (hoy `claude/friendly-hopper-506bg9`).

Este documento es la regla de convivencia para que varias sesiones Claude trabajen en paralelo sin pisarse. Si una sesión necesita algo fuera de lo que le toca, **no lo edita**: lo pide en `HANDOFF.md` de su fase.

---

## 1. Ramas y entregas

| Regla | Detalle |
|---|---|
| Rama | `claude/<fase>-<slug>` (ej. `claude/10-base`, `claude/11-mon-servidor`, `claude/19-01-urdeng-ts`, `claude/pt-02-lectura`). |
| Base | `claude/friendly-hopper-506bg9` mientras los docs GSD no estén en `main`; después, `main`. |
| Tamaño | Un plan = una rama, < 1 500 líneas cambiadas sin contar movimientos puros. Si se pasa: `-p1`, `-p2`. |
| PR | **Solo cuando el owner lo pida.** La sesión deja la rama pusheada y el `SUMMARY.md` listo para el cuerpo del PR. |
| Commits | Español, formato `<area>: <cambio>` (ver `AGENTS.md`). |
| Integración | El integrador rebasa/mergea por ola en el orden de §4 y actualiza `STATE.md`. |

## 2. Qué entrega cada sesión

1. Código + tests en su rama.
2. `.planning/phases/<NN-*>/<NN-XX>-SUMMARY.md`: qué se hizo, IDs cubiertos, archivos tocados, evidencia (salida de tests/build), decisiones tomadas, pendientes, cómo desplegar (migraciones/`.sql`, variables `.env`, `optimize`).
3. `HANDOFF.md` en su fase si necesita cambios en archivos de otro dueño (qué archivo, qué cambio, por qué).
4. Si la fase solo tiene `CONTEXT.md`: primero escribe `<NN-XX>-PLAN.md` (formato de `phases/01-guardrails/01-guardrails-PLAN.md`), luego ejecuta.

**No editar nunca:** `.planning/ROADMAP.md`, `.planning/STATE.md`, `.planning/REQUIREMENTS.md`, `.planning/PROJECT.md`, este archivo. Solo el integrador.

## 3. Checks obligatorios antes de push

```bash
composer install && npm ci          # si no hay vendor/ o node_modules/
php artisan test                    # sqlite en memoria (phpunit.xml)
npm run typecheck && npm run test:js && npm run build
vendor/bin/phpstan analyse          # cuando exista phpstan.neon (fase 10)
node scripts/ratchet.mjs            # cuando exista (fase 10): ningún conteo sube
vendor/bin/pint --test $(git diff --name-only --diff-filter=AM origin/<base>...HEAD -- '*.php')
```

Además: skill `code-review` sobre el diff; `security-review` en fases 11, 13, 20 y SEC-01.

**SQL:** producción es **SQL Server 2008 R2**: todo SQL crudo debe ser compatible (sin `PERCENTILE_CONT`, `OFFSET/FETCH`, `STRING_AGG`, `TRY_CONVERT`, `IIF`, `CONCAT`, `FORMAT`, `THROW`); paginar con `app/Support/PaginacionCompat.php`. sqlite de tests no lo detecta: revisarlo a mano.

**Nunca:** saltar, desactivar o marcar como incompleto un test para ponerlo en verde; formatear el repo completo con Pint; editar `public/build`; tocar `config/database.php` (gitignored + skip-worktree, se administra con `scripts/db-config.ps1`).

## 4. Orden de merge por ola

- **Ola 0:** `10-base` (CI) → `11-mon-servidor` p1 (esquema) → `11` p2 → PT-01 cuando quiera (no comparte archivos).
- **Ola 1:** `15-01-utils-ts` → `12-mon-cliente` → `14-mon-pulse` → `13-mon-panel`.
- **Ola 2:** `20-01` movimientos puros → `18-01` → PT (usos select2) → `15-02` → `16` → `20-02` / `20-03`.
- **Ola 3:** sesiones `19-xx` en cualquier orden (propiedad vertical); `17-02` antes que las 19-xx que toquen layout (ninguna debería).

## 5. Propiedad de archivos

Lo no listado es **solo lectura** para el track. Los globs de un track **no** se editan desde otro.

| Track | Dueño de | Prohibido |
|---|---|---|
| **PT** | `resources/js/programa-tejido/**`, `app/Http/Controllers/Planeacion/ProgramaTejido/**`, `app/Http/Controllers/Planeacion/Utilerias/**`, `resources/views/modulos/programa-tejido/**` (incl. liberar-ordenes, balancear), `app/Services/Planeacion/ProgramaTejido/**`, `app/Actions/Planeacion/**`, `app/Observers/ReqProgramaTejidoObserver.php`, `app/Http/Middleware/ProgramaTejidoContext.php`, `routes/modules/planeacion.php`, `config/planeacion.php`, `public/js/programa-tejido-*.js`, `public/css/programa-tejido/**`, `tests/Feature/Planeacion/**`, `tests/Unit/Planeacion/**`, `.planning/phases/0*/**` | Todo lo demás |
| **BASE (10)** | `.github/workflows/frontend-checks.yml`, `.gitignore`, `phpunit.xml`, `phpstan.neon` (+ baseline), `scripts/ratchet.mjs` (+ `scripts/ratchet-baseline.json`), tests que hoy fallan en sqlite **fuera de PT**, vistas/servicios muertos listados en su plan, `routes/public.php`, la ruta debug de `routes/modules/tejedores.php`, `public/towell-*`, `public/audio/click-sound.js`, `public/pwa-check.mjs`, `resources/views/modulos/usuarios/qr.blade.php`, `config/queue.php`, `config/logging.php` (solo default `daily`), `.env.example`, `tsconfig.json` (Ola 0), `CLAUDE.md` (datos obsoletos), `.claude/settings.json` (hook), SQL interpolado de SEC-01 (líneas puntuales) | Lógica de controllers de negocio |
| **MON-A (11)** | `app/Services/Monitoreo/**`, `app/Models/Sistema/Monitoreo/**`, `app/Http/Middleware/Monitoreo/**`, `app/Http/Controllers/Monitoreo/**`, `app/Http/Requests/Monitoreo/**`, `app/Listeners/Monitoreo/**`, `routes/console.php` (solo agregar el schedule de poda), `resources/views/modulos/admin/index.blade.php` (placeholder), `app/Support/Http/Concerns/HandlesApiErrors.php` (solo `report()`), `app/Providers/MonitoreoServiceProvider.php`, `bootstrap/providers.php`, `config/monitoreo.php`, `routes/modules/telemetria.php`, `routes/modules/admin.php`, `routes/web.php` (solo líneas `require`), `bootstrap/app.php` (Ola 0–1), migraciones `*_sysmon_*`, `database/sql/sysmon_*.sql`, `app/Http/Controllers/AuthController.php`, `app/Models/Sistema/SYSMensaje.php`, `MensajesController`, `mensajes.blade.php`, `resources/views/errors/500.blade.php` (solo código de referencia), `components/layout-head.blade.php` (solo 2 `<meta>`), `tests/Feature/Monitoreo/**`, `tests/Unit/Monitoreo/**` | `resources/js/**` |
| **MON-B (12)** | `resources/js/monitoreo/**`, `tests/Js/monitoreo-*`, `resources/views/components/navbar/sections/user-modal.blade.php`, 1 línea de import en `resources/js/app.js` | `resources/js/utils/**` (solo consume) |
| **MON-C/D (13–14)** | `app/Livewire/Admin/**`, `resources/views/livewire/admin/**`, `resources/views/modulos/admin/**`, `config/pulse.php`, `resources/views/vendor/pulse/**`, migración de Pulse, `composer.json/lock` (Ola 1, solo `laravel/pulse`) | Tablas MON-A (solo lectura; cambios vía HANDOFF) |
| **FE (15)** | Ola 1: `resources/js/utils/**`, `resources/js/types/**`, `tsconfig.json`, `tests/Js/utils-*`, `resources/js/tejido/**` (solo tipos). Ola 2: + `resources/js/bootstrap.js`, `resources/js/app.js`, `vite.config.js`, `package.json/lock`, `components/layout-scripts.blade.php`, `components/layout-styles.blade.php`, `components/layout/global-loader.blade.php`, `resources/css/fontawesome-display.css`, `public/js/catalog-core.js`, call-sites de select2/toastr **fuera de PT** | Resto de vistas de módulos |
| **DS (16)** | `resources/views/components/ui/**`, `resources/views/components/empty/**`, `resources/views/components/tabla*.blade.php`, `resources/views/components/layout/page-*`, `resources/css/app.css` (bloque `@theme`), `resources/js/catalogos/**`, `public/js/catalogs/**`, `resources/views/modulos/catalogos-atadores/**` (piloto), `docs/cerebro-towell/Arquitectura/receta-componentes.md` | Layouts |
| **UX-global (17-02)** | `resources/views/layouts/app.blade.php`, `components/layout-head.blade.php`, `resources/views/errors/**`, `lang/**`, `config/app.php` (locale), `resources/views/login.blade.php`, `resources/views/components/auth/**` | Vistas de módulos |
| **PERF-infra (18-01)** | `app/Providers/AppServiceProvider.php`, `app/Helpers/permission-helpers.php`, `app/Http/Middleware/SetSqlContextInfo.php`, `config/session.php`, `config/cache.php`, `docs/cerebro-towell/Runbooks/deploy.md` | Controllers |
| **ARQ/SEC (20)** | Ola 2: `bootstrap/app.php`, `app/Http/Middleware/EnsureModulePermission.php`, `app/Helpers/FolioHelper.php`, `app/Helpers/TurnoHelper.php`, `app/Support/Http/Concerns/HandlesApiErrors.php`, movimientos `app/Http/Controllers/Tejedores/Desarrolladores/Funciones/*` → `app/Services/Tejedores/**` | `Planeacion/ProgramaTejido/{funciones,helper}` (PT) |
| **19-xx módulo** | `routes/modules/<mod>.php`, `app/Http/Controllers/<Mod>/**`, `app/Services/<Mod>/**`, `app/Livewire/<Mod>/**`, `resources/views/modulos/<mod>/**`, `resources/js/modulos/<mod>/**`, `tests/**/<Mod>*` | Otros módulos, utils, componentes (HANDOFF) |

### Archivos calientes (un solo dueño por ola)

| Archivo | Ola 0 | Ola 1 | Ola 2 | Ola 3 | Ola 4 |
|---|---|---|---|---|---|
| `composer.json/lock` | BASE | MON-D (`laravel/pulse`) | — | — | ADOP |
| `package.json/lock`, `vite.config.js` | BASE (`qrcode`) | — | FE (inputs por glob) | congelado | ADOP |
| `tsconfig.json` | BASE | FE | FE | — | — |
| `resources/js/bootstrap.js`, `app.js` | — | MON-B (1 línea en `app.js`) | FE | — | — |
| `bootstrap/app.php`, `bootstrap/providers.php`, `routes/web.php` | MON-A | MON-A | ARQ | — | — |
| `AppServiceProvider.php` | — | — | PERF-infra (después de ARQ-01) | — | — |
| `layouts/app.blade.php`, `layout-head` | MON-A (2 `<meta>`) | — | DS (flash) | UX-global | — |
| `config/logging.php` | BASE (default) | PT-02 | PERF | — | — |

"Líneas ancla" permitidas a cualquiera con conflicto trivial: un `require` en `routes/web.php`, un import en `app.js`.

## 6. Entorno de las sesiones en la nube

- **No hay SQL Server** (IPs privadas 192.168.2.x). Los tests corren en sqlite (`phpunit.xml`, `Tests\Concerns\UsesSqlsrvSqlite`).
- Todo lo que requiera datos reales (schema físico, línea base de tiempos, `SELECT DISTINCT area`) se entrega como **script/runbook para que el owner lo corra en Laragon**, nunca se inventa.
- PHP 8.4 + `pdo_sqlite`, Node 22 disponibles. `vendor/` y `node_modules/` no vienen: `composer install` y `npm ci` (la fase 10 agrega un SessionStart hook).

## 7. Plantilla del prompt de apertura

```
Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026, protocolo en .planning/PROTOCOLO-SESIONES.md (léelo primero).

Fase <NN> — <nombre>. IDs: <...>.
Plan: .planning/phases/<NN-*>/<NN-XX>-PLAN.md  (si solo hay CONTEXT.md, escribe el PLAN primero).
Contrato(s): <ruta o "ninguno">.
Rama: claude/<NN>-<slug> (base claude/friendly-hopper-506bg9).

ERES DUEÑO DE: <globs>.
SOLO LECTURA: <globs>.
PROHIBIDO: <globs + archivos calientes de otros>.

Acuerdos: docs en español; modo ponytail (reusar <helpers citados> antes de crear nada nuevo);
nunca saltar/desactivar tests; Pint solo en archivos tocados; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT;
el ratchet no sube; no hay SQL Server en este entorno: lo que requiera datos reales va como script/runbook para el owner.

Antes de push: php artisan test · npm run typecheck && npm run test:js && npm run build · phpstan/ratchet si existen · code-review.
Entregables: código + tests + <NN-XX>-SUMMARY.md (+ HANDOFF.md si pediste cambios ajenos). Push a tu rama; NO abras PR.
```

## 8. Gates

| Gate | Condición |
|---|---|
| G0 | CI PHP + JS verde y obligatorio; ratchet y baseline commiteados; higiene mergeada; esquema de monitoreo mergeado |
| G1 | Monitor en prod ≥ 7 días; dispositivos en línea, errores con Telegram y cierre remoto probados; overhead p95 < 5 ms; utils TS mergeados |
| G2 | Sin jQuery/Select2/Toastr; vendor fuera del bundle global; galería DS; AuthZ en modo auditar; drivers y OPcache decididos; auditoría UX publicada |
| G3 (por módulo) | JS inline < 50 líneas por vista; http/notify/componentes adoptados; AuthZ enforce con tests; checklist UX; 7 días sin subida de errores ni p95 en `/admin` |
| G4 | Legado retirado con evidencia de 0 hits en 30 días; docs al día |
