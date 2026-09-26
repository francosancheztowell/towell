# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Towell** is a Laravel 12 web application for production and business planning management in the textile industry. It manages modules for planning (planeación), weaving (tejido), warping (urdido), sizing (engomado), tying (atadores), weavers (tejedores), maintenance, and configuration — with a granular role-based permission system per module.

## Commands

```bash
# Full development environment (PHP server + queue worker + pail logs + Vite)
composer dev

# Individual processes
php artisan serve
npm run dev

# Production build
npm run build

# Code style (Laravel Pint)
./vendor/bin/pint

# Run tests
php artisan test
# Run a single test file
php artisan test tests/Feature/ExampleTest.php
# Tests that need real SQL Server (group `sqlserver`, excluded by default)
# see tests/README-sqlserver.md

# Static analysis (larastan level 5; existing debt lives in phpstan-baseline.neon)
vendor/bin/phpstan analyse --memory-limit=2G

# Debt ratchet: fails if any counted pattern (fetch(, Swal.fire, onclick=, inline <script>...) goes up
npm run ratchet            # node scripts/ratchet.mjs --update to lock in a decrease

# Clear all caches (often needed after config or module changes)
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

### CI
`.github/workflows/frontend-checks.yml` (the only versioned workflow) runs on PRs and on pushes to `main` and `claude/**`:
- `checks`: `npm run typecheck`, `npm run test:js`, `npm run build`, `npm run ratchet`.
- `php`: `php artisan test` (sqlite in memory, needs `npm run build` first for the Vite manifest), `phpstan analyse`, and Pint `--test` only on changed PHP files (never format the whole repo).

Cloud sessions get `vendor/`, `node_modules/`, `.env` and `public/build` from the SessionStart hook `scripts/session-start.sh`.

## Architecture

### Database
- Primary DB: SQL Server (`sqlsrv`) via `pdo_sqlsrv` / `sqlsrv` PHP extensions. **Production runs SQL Server 2008 R2**: no `PERCENTILE_CONT`, `OFFSET/FETCH`, `STRING_AGG`, `TRY_CONVERT`, `IIF`, `CONCAT`, `FORMAT` or `THROW` (all 2012+). Use `ROW_NUMBER()` and the existing `PaginacionCompat` for paging.
- Two additional SQL Server connections configured:
  - `sqlsrv_ti` → `TI_PRO` database (production data source)
  - `sqlsrv_tow_pro` → `TOW_PRO` database (production data source)
- Tables use the `dbo.` schema prefix (e.g., `dbo.SSYSFoliosSecuencias`)

### Authentication
- Custom auth using the `Usuario` model (employee number + password)
- Supports legacy plain-text passwords with auto-migration to bcrypt on successful login
- QR code login also supported
- After login, users are redirected to `/produccionProceso`

### Permission System
Permissions are managed per-module (stored in `SYSUsuariosRoles`) with five permission types: `acceso`, `crear`, `modificar`, `eliminar`, `registrar`. Module definitions are in `SYSRoles`.

Use the global helper functions from `app/Helpers/permission-helpers.php`:
```php
userCan('crear', 'NombreModulo')      // Check single permission
userPermissions('NombreModulo')        // Get all permissions for a module
```

Module data is cached for 1 hour via `ModuloService` (cache prefix `modulos_v3`). Always call `ModuloService::limpiarCacheUsuario()` after permission changes.

### Module Hierarchy
- **Level 1**: Main modules (`Dependencia = NULL`) — e.g., Planeación, Tejido, Urdido
- **Level 2**: Submodules referencing a Level 1 `orden` in `Dependencia`
- **Level 3**: Submodules referencing a Level 2 `orden` in `Dependencia`

Routes are split into `routes/web.php` (dispatcher) and individual files in `routes/modules/` per business domain. All authenticated routes use the `auth` middleware. Additional route files: `routes/public.php` (unauthenticated), `routes/modules/telegram.php`. `routes/ai.php` (AI/MCP) is local-only: it is gitignored and does not exist in a fresh clone.

### Model Organization
Models are in `app/Models/` organized by subdirectory:
- `Sistema/` — `Usuario`, `SYSRoles`, `SYSUsuariosRoles`, `SSYSFoliosSecuencia`, etc.
- `Planeacion/` (with `Catalogos/`), `Tejido/`, `Urdido/`, `Engomado/`, `Atadores/`, `Tejedores/`, `Inventario/`, `Mantenimiento/`, `UrdEngomado/`

Controllers follow the same subdirectory pattern under `app/Http/Controllers/`.

### Key Services & Helpers
- `ModuloService` — module lookup and user-specific module cache
- `PermissionService` — save/retrieve user permissions
- `FolioHelper` — generate/retrieve sequential folios from `dbo.SSYSFoliosSecuencias`; use `obtenerSiguienteFolio()` only when committing (it auto-increments), use `obtenerFolioSugerido()` for UI preview
- `TurnoHelper` — determine current production shift (Turno 1: 6:30–14:30, Turno 2: 14:30–22:30, Turno 3: 22:30–6:30, America/Mexico_City timezone)
- `StringTruncator` — truncate string fields to their SQL Server column limits before insert/update
- `UsuarioService` — user data operations
- `ProgramaUrdEng/` — 5 services for warping-sizing scheduling (InventarioTelaresService, ProgramasUrdidoEngomadoService, ResumenSemanasService, InventarioReservasService, BomMaterialesService)
- `Engomado/ControlMermaReportService` — sizing waste control reports
- `AuditoriaHelper` — audit trail logging
- `ImageOptimizer` — image optimization (WebP conversion)
- `TelDesarrolladoresHelper` — weaver developer operations
- Weaver-developer services (`ProcesarDesarrolladorService`, `MovimientoDesarrolladorService`, …) live in `app/Services/Tejedores/Desarrolladores/` (moved out of `Controllers/.../Funciones` in phase 20-01)
- Global helpers auto-loaded via Composer: `format_helpers.php` (`decimales()`, `formatearFecha()`), `permission-helpers.php`, `device_helpers.php`

### Frontend
- Tailwind CSS v4 via `@tailwindcss/vite` plugin
- No jQuery, Select2 or Toastr (removed in phase 15-02). Searchable selects: Tom Select through `resources/js/utils/combobox.ts` (`combobox(select, opts)`; in inline Blade `await window.combobox(select, opts)`). SweetAlert2 (modals only), Chart.js, SortableJS, Font Awesome 7. `html2canvas-pro` and `pdfjs-dist` load on demand via `window.librerias.html2canvas()` / `.pdfjs()`. `window.toastr` is a temporary adapter to `notify` (removed in phase 21).
- Vite entries: the fixed list in `vite.config.js` plus every `resources/js/modulos/**/index.ts` (glob) — new module bundles don't need to touch Vite.
- Blade components: `resources/views/components/ui/*` (modal-base as `<dialog>`, button, table, table-empty, field, badge, spinner, skeleton, alert, flash, filter-bar) and `x-empty.empty-state`; runtime in `resources/js/componentes/` (imported by `app.js`). Tokens in the `@theme` block of `resources/css/app.css` (`text-caption` ≥ 12 px, `min-h-touch`/`size-touch` 44 px, `bg-primary`, `bg-danger`…). The layout mounts `x-ui.flash`, so `redirect()->with('error'|'success', …)` shows without view code. Recipe: `docs/cerebro-towell/Arquitectura/receta-componentes.md`; gallery `/dev/ui-kit` (local only).
- Two JS entry points: `app.js` (main) y `app-core.js`. El componente `<x-layout-scripts>` carga `app.js` (que importa `bootstrap.js`); `app.blade.php` además carga `app-core.js`. `app-filters.js` ya no existe: se desconectó a propósito porque `@vite` emite `<script type="module">` y los `onclick` inline no veían sus funciones (ver comentario en `app.blade.php`).
- Blade layouts in `resources/views/layouts/`: `app.blade.php` (main), `simple.blade.php`, `globalLoader.blade.php`
- Module images stored in `public/images/fotos_modulos/`; user photos in `public/images/fotos_usuarios/` (WebP preferred)

#### HTTP, notificaciones y utilidades (preferir sobre `fetch` crudo)
`bootstrap.js` expone utilidades globales (también importables como ESM desde `resources/js/utils/*.ts`), disponibles en cualquier `<script>` de Blade:
- **`window.http`** (`resources/js/utils/http.ts`) — cliente HTTP único sobre axios. Firmas: `http.get(url, config?)`, `http.delete(url, config?)` (el body de un DELETE va en `config.data`), `http.post/put/patch(url, data?, config?)`, `http.upload(url, formData)`. Devuelve el JSON (`response.data`), manda siempre `Accept: application/json` y el CSRF, y **lanza** `HttpError` (`err.status`, `err.data`, `err.errors` en 422). En todo fallo emite `towell:http-error` en `window` (`{status, url, method}`). Sesión expirada (419/401): un aviso y recarga sola. No escribir `fetch(...).then(r => r.json())` nuevo.
- **`window.notify`** (`resources/js/utils/notifications.ts`) — `notify.success/error/warning/info(msg)` son **toasts nativos** accesibles (`aria-live`, máx. 4, sin Toastr); `notify.confirm({...}) → Promise<boolean>`, `notify.validation(err.errors)`, `notify.loading()/close()` siguen con SweetAlert2. `window.showToast(msg, tipo)` apunta aquí. Escapa HTML. Todos los toasts duran 5 s y salen debajo del navbar.
- **`resources/js/utils/format.ts`** — `escapeHtml`, `debounce`, `formatNumber`, `formatDate`, `formatDateTime` (es-MX, America/Mexico_City). No redefinirlos en vistas.
- **`resources/js/utils/dom.ts`** — `qs`, `qsa`, `delegate`, `onReady`.
- **`resources/js/utils/sesion.ts`** — `sesionExpirada()`: aviso único y recarga ante 419/401, compartido por `window.http` y por Livewire. Un `fetch` crudo no lo hace.
- **`window.accionesTactiles`** (`resources/js/utils/acciones-tactiles.ts`) — `accionesTactiles(root, selector, abrir)` (clic derecho + long-press) y `botonAcciones(el, abrir)` (botón "⋮"); reemplaza los `contextmenu` sueltos, que no funcionan en tablet.
- Banner "sin conexión" global (`resources/js/componentes/conexion.ts`, evento `towell:conexion` + `online`/`offline`): no pintar avisos propios de red.

Páginas: `<title>` sale de `@section('title')` o del texto de `@section('page-title')` (con "· Towell"); el navbar pone el único `<h1>` (los títulos del contenido van en `<h2>`). Pinch-zoom habilitado salvo andón (`@section('viewport-fijo', '1')`). Idioma `es` (`lang/es/**`, `APP_LOCALE=es`). Páginas `errors/*` con layout propio. Checklist por pantalla para cada migración: `.planning/phases/17-ux/17-02-CHECKLIST.md`.

Migración en curso (`.planning/ROADMAP.md`, fases 15/16/19): los `fetch` inline, `showToast()` duplicados y `onclick=` se reemplazan módulo por módulo; el ratchet (`npm run ratchet`) impide que crezcan.

### Monitoreo y panel `/admin`
- Tablas `dbo.SYSMon*` (dispositivos, sesiones, vistas, errores, accesos). Contrato en `.planning/phases/11-mon-servidor/11-CONTRACT.md`; kill switch `MONITOREO_ENABLED`.
- Panel Livewire en `/admin` (solo área Sistemas, Gate `admin`, `MONITOREO_AREAS_ADMIN`) y Laravel Pulse en `/admin/pulse` sobre una conexión **SQLite** propia (Pulse no soporta SQL Server).
- Errores PHP/JS agrupados por huella en `SYSMonError`; alertas por correo a `MONITOREO_ALERTA_CORREO` (default francost15@gmail.com).
- Cliente de telemetría: `resources/js/monitoreo/*.ts` (no parchear `window.fetch`).

### Excel Import/Export
Uses `maatwebsite/excel` (v3.1). Import classes are in `app/Imports/` (11 files). Export classes in `app/Exports/` (17 files) for generating downloadable reports per module.

### PDF Generation
Uses `dompdf/dompdf` (v3.1). PDF controllers/views are in `app/Http/Controllers/PDFController.php` and `resources/views/pdf/`.

### Telegram Notifications
- Bot config in `config/services.php` via `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` env vars
- Controller: `Telegram/TelegramController.php` — sends module-specific notifications
- Recipients per module stored in `SYSMensajes` table, queried via `SYSMensaje::getChatIdsPorModulo($modulo)`
- Routes in `routes/modules/telegram.php`: `POST /telegram/send`, `GET /telegram/bot-info`, `GET /telegram/get-chat-id`

### Additional Patterns
- **Traits**: `ProduccionTrait` in `app/Traits/`
- **Observers**: `ReqProgramaTejidoObserver` and `AtaMontadoTelasObserver` (both registered in AppServiceProvider)
- **Artisan Commands**: `OptimizeModuleImagesCommand`, `RecalcularFechasProduccionCommand`
- **Middleware**: `NoCacheHtmlResponses`, `ProgramaTejidoContext` (in addition to `SetSqlContextInfo`). HTTPS is the web server's job, not the app's.
- **Redis**: `predis/predis` v3.3 configured as cache/queue driver

## Important Conventions

- The permission field in `SYSRoles` has a **typo**: it is `reigstrar` (not `registrar`). The corresponding column in `SYSUsuariosRoles` is correctly named `registrar`. Be careful when referencing both.
- When creating new modules via `ModulosController`, permissions are automatically propagated to all existing users and caches are cleared.
- The `SetSqlContextInfo` middleware sets SQL Server session context for auditing.
- Cache prefix includes `APP_ENV` to prevent local/production cache collisions. If the menu doesn't appear in production, run `cache:clear` and `config:clear`.
- The views directory `resources/views/catalagos/` has a **typo** (should be `catalogos`). Preserve this when referencing existing views.
