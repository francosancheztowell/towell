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

# Clear all caches (often needed after config or module changes)
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

## Architecture

### Database
- Primary DB: SQL Server (`sqlsrv`) via `pdo_sqlsrv` / `sqlsrv` PHP extensions
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

Module data is cached for 1 hour via `ModuloService` (cache prefix `modulos_v2`). Always call `ModuloService::limpiarCacheUsuario()` after permission changes.

### Module Hierarchy
- **Level 1**: Main modules (`Dependencia = NULL`) — e.g., Planeación, Tejido, Urdido
- **Level 2**: Submodules referencing a Level 1 `orden` in `Dependencia`
- **Level 3**: Submodules referencing a Level 2 `orden` in `Dependencia`

Routes are split into `routes/web.php` (dispatcher) and individual files in `routes/modules/` per business domain. All authenticated routes use the `auth` middleware.

### Model Organization
Models are in `app/Models/` organized by subdirectory:
- `Sistema/` — `Usuario`, `SYSRoles`, `SYSUsuariosRoles`, `SSYSFoliosSecuencia`, etc.
- `Planeacion/`, `Tejido/`, `Urdido/`, `Engomado/`, `Atadores/`, `Tejedores/`, `Inventario/`, `Mantenimiento/`

Controllers follow the same subdirectory pattern under `app/Http/Controllers/`.

### Key Services & Helpers
- `ModuloService` — module lookup and user-specific module cache
- `PermissionService` — save/retrieve user permissions
- `FolioHelper` — generate/retrieve sequential folios from `dbo.SSYSFoliosSecuencias`; use `obtenerSiguienteFolio()` only when committing (it auto-increments), use `obtenerFolioSugerido()` for UI preview
- `TurnoHelper` — determine current production shift (Turno 1: 6:30–14:30, Turno 2: 14:30–22:30, Turno 3: 22:30–6:30, America/Mexico_City timezone)
- `StringTruncator` — truncate string fields to their SQL Server column limits before insert/update
- Global helpers auto-loaded via Composer: `format_helpers.php` (`decimales()`, `formatearFecha()`), `permission-helpers.php`, `device_helpers.php`

### Frontend
- Tailwind CSS v4 via `@tailwindcss/vite` plugin
- jQuery v4, Select2, SweetAlert2, Toastr, Chart.js, SortableJS, Font Awesome
- Three JS entry points: `app.js` (main), `app-core.js`, `app-filters.js`
- Blade layouts in `resources/views/layouts/`: `app.blade.php` (main), `simple.blade.php`, `globalLoader.blade.php`
- Module images stored in `public/images/fotos_modulos/`; user photos in `public/images/fotos_usuarios/` (WebP preferred)

### Excel Import
Uses `maatwebsite/excel` (v3.1). Import classes are in `app/Imports/`.

### PDF Generation
Uses `dompdf/dompdf` (v3.1). PDF controllers/views are in `app/Http/Controllers/PDFController.php` and `resources/views/pdf/`.

## Important Conventions

- The permission field in `SYSRoles` has a **typo**: it is `reigstrar` (not `registrar`). The corresponding column in `SYSUsuariosRoles` is correctly named `registrar`. Be careful when referencing both.
- When creating new modules via `ModulosController`, permissions are automatically propagated to all existing users and caches are cleared.
- The `SetSqlContextInfo` middleware sets SQL Server session context for auditing.
- Cache prefix includes `APP_ENV` to prevent local/production cache collisions. If the menu doesn't appear in production, run `cache:clear` and `config:clear`.
