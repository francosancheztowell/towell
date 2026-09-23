# Phase 8: ERP quick wins — Context

**Source:** `.planning/auditoria_erp_2026-09.md` §3 Fase 0 + §2.7 (rutas rotas) + §2.9 (tests de edición). La auditoría ES la investigación: cada ítem tiene archivo:línea verificado el 2026-09-22. Re-verificado ese día: los 10 ítems de Fase 0 siguen sin aplicar.

## Phase Boundary

Solo Fase 0 (esfuerzo S, riesgo bajo) + 3 rutas rotas. Nada de Fases 1-3 de la auditoría. No toca Programa Tejido fases 1-7.

## Implementation Decisions

### Seguridad
- ERP-F0-01: `Livewire::addPersistentMiddleware([EnsureModulePermission::class])` en `AppServiceProvider`. Gate `module.permission` o `abort_unless(userCan(...))` en `NuevoRequerimiento`, `ConsultarRequerimiento`, `EdicionOrden` (`puedeEditar=true` fijo, :112-125), `EdicionOrdenes`. Resolver módulo por **idrol**, no por nombre (5 nombres duplicados en SYSRoles).
- ERP-F0-02: `EdicionOrden::guardarMetrosFila` (:152-180) → `$this->produccionEditable($this->orden())->whereKey($id)->first()`; rechazar no numéricos. Test Livewire copiando `tests/Feature/EditarOrdenPermisosTest.php`.
- ERP-F0-06: quitar `SELECT @@VERSION` y bloque debug en `NotificarMontRollosController.php:219-265`.

### Bugs
- ERP-F0-04: `bg-opacity-*` en 17 vistas → sintaxis `/NN` de Tailwind v4. `reporte-resumen-engomado.blade.php:92` `max-md` → `max-w-md`.
- ERP-F0-09: `Urdido-BPM-Line/index.blade.php:208` `fetch` → `http.post` igual que Engomado :207.
- ERP-F0-11: `cargar-catalogos.blade.php:32` (no hay endpoint de subida: la página da 500 al renderizar → **checkpoint humano**: retirar la ruta `configuracion.php:94-95` o implementar subida; default = retirar la vista/ruta y su módulo del menú queda para el usuario); `CodificacionController.php:628` `codificacion.index` → nombre real; `UsuarioController.php:210` → `configuracion.usuarios.select`. Test de contrato: grep de `route('...')` literales en `app/` y `resources/views` vs `Route::has()`.

### SQL / perf
- ERP-F0-03: `InventarioReservasService.php:42-47` quitar `%` inicial de PATTERN_RIZO/PIE/URDIDO; no aplicar LTRIM/RTRIM a la columna (:694). Riesgo: si algún ItemId tiene prefijo antes de `JU-ENG-`, cambia el resultado → task de verificación comparando salida antes/después contra `sqlsrv_ti` (manual, checkpoint).
- ERP-F0-05: borrar `import '../css/app.css'` de `resources/js/app.js:2`; `npm run build` verde.
- ERP-F0-10: `programaAtadores/index.blade.php:734` `setInterval` → saltar si `document.hidden`, 15 s. (Archivo con cambios sin commit del usuario: editar encima, no revertir.) `Mecanicos/VerificaMaquina/Index.php:108` `paginate` → `PaginacionCompat`; corregir docblock `ConTabla.php:23`.

### Código muerto (ERP-F0-07, ERP-F0-08)
- Lista exacta en auditoría §2.1 filas 2-5, 7-10 y §3 Fase 0 ítems 7-8 (R1.1 en `auditoria_limpieza_plan.md`). Antes de borrar cada símbolo: `grep` de 0 llamadores (incluir `resources/`, `routes/`, `tests/`). Excluir Telegram (`TelegramController`) y `/orden-produccion` (clientes externos por confirmar) y el tablero clásico Urd/Eng (Fase 1, decisión de negocio).

### Claude's Discretion
- Orden de waves y agrupación de commits. Un commit por requisito.

## Canonical References
- `.planning/auditoria_erp_2026-09.md`, `.planning/auditoria_limpieza_plan.md` (R1.1)
- Tests: `tests/Concerns/UsesSqlsrvSqlite.php`, `tests/Feature/EditarOrdenPermisosTest.php`, `tests/Unit/EnsureModulePermissionTest.php`
- `CLAUDE.md` (userCan, typos `catalagos`/`reigstrar`)

## Specific Ideas
- Regla: cada método de edición tocado deja su test en el mismo commit (auditoría §2.9).

## Deferred Ideas
- Fases 1-3 de la auditoría; `wire:navigate`, breadcrumbs, validación de `SYSRoles.Ruta` (§2.7); orden JS→TS (§2.8); tests de FolioHelper/actualizarRegistro/BPM/catálogos (§2.9) — van con sus refactors de Fase 1.
