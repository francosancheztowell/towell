---
phase: 08-erp-quick-wins
plan: 06
subsystem: api
tags: [security, information-disclosure, tejedores, sqlsrv]

requires: []
provides:
  - "getOrdenProduccion sin SELECT @@VERSION ni clave debug (ERP-F0-06)"
  - "Test de contrato 200/404/400 del endpoint orden-produccion"
affects: [tejedores, cortado-de-rollo]

tech-stack:
  added: []
  patterns:
    - "Guarda de 'no se abrio la conexion X': assertArrayNotHasKey('sqlsrv_ti', DB::getConnections()), porque DB::listen no registra consultas que fallan"

key-files:
  created:
    - tests/Feature/NotificarCortadoRolloOrdenProduccionTest.php
  modified:
    - app/Http/Controllers/Tejedores/NotificarMontadoRollo/NotificarMontRollosController.php

key-decisions:
  - "El test apunta sqlsrv_ti a sqlite en memoria para que el RED no salga a la red, y comprueba que la conexion ni se abre"
  - "Metodo y ruta /orden-produccion se conservan (clientes externos por confirmar, excluido por CONTEXT)"

patterns-established:
  - "Probar ausencia de un probe de conexion con DB::getConnections(), no solo con DB::listen"

requirements-completed: [ERP-F0-06]

duration: 45min
completed: 2026-09-23
---

# Phase 8 Plan 06: getOrdenProduccion sin probe de version Summary

**`GET /tejedores/cortadoderollo/orden-produccion` ya no ejecuta `SELECT @@VERSION` contra TI_PRO en cada llamada ni devuelve la versión del SQL Server en la clave `debug`; el contrato 200/404/400 queda cubierto por test.**

## Performance

- **Duration:** ~45 min (la mayor parte es la suite completa: 2334 s)
- **Started:** 2026-09-23T00:00:00-06:00 (aprox.)
- **Completed:** 2026-09-23T00:50:44-06:00
- **Tasks:** 1/1
- **Files modified:** 2

## Accomplishments
- Borrado el try/catch del probe `DB::connection('sqlsrv_ti')->select('SELECT @@VERSION ...')` y la variable `$conexionTowPro`.
- Quitada la clave `debug` (versión del servidor y telar buscado) de las respuestas 404 y 200. Ningún archivo de `resources/` la leía.
- Una consulta menos a TI_PRO por llamada desde las tablets (T-08-18).

## Task Commits

1. **Tarea 1: Quitar SELECT @@VERSION y bloque debug de getOrdenProduccion (ERP-F0-06)** - `b3c13073` (fix)

## Files Created/Modified
- `app/Http/Controllers/Tejedores/NotificarMontadoRollo/NotificarMontRollosController.php` - getOrdenProduccion sin probe ni debug (-15 líneas)
- `tests/Feature/NotificarCortadoRolloOrdenProduccionTest.php` - 200 con success+orden sin debug; 404 con error sin debug; 400 sin no_telar; ninguna consulta con `@@VERSION` y la conexión `sqlsrv_ti` nunca se abre

## Verification
- `php artisan test --filter=NotificarCortadoRolloOrdenProduccionTest`: RED antes del cambio (2 fallos por `debug` presente), GREEN después (3 passed, 17 assertions).
- `! grep -qE "@@VERSION|conexion_tow_pro"` sobre el controlador: OK.
- Pint `--test` sobre los 2 archivos: pass.
- Suite completa (`php artisan test`, una vez, 2334 s): **1153 passed, 49 skipped, 11 failed**. Ninguno de los 11 toca archivos de este plan:
  - `Tests\Unit\Crudo\CrudoDashboardServiceTest` (4) y `CrudoMachineDetailTest` (1): aserciones de dashboard Crudo; ajenos.
  - `Tests\Unit\Programas\ProgramBoardLivewireTest` (2) y `ProgramBoardStructureTest` (1): la vista/TS del tablero Urd/Eng tienen cambios sin commit del usuario (`program-board.blade.php`, `reservar-programar.ts`, `program-board.css`); ajenos.
  - `Tests\Feature\NuevoRequerimientoLivewireTest` (3): `QueryException` por timeout TCP real a `sqlsrv_ti` (192.168.2.28, TI_PRO): el test sale a la red. Explica también buena parte de la duración de la suite. Ajeno (ERP-F0-01 / 08-01 no se ejecuta).

## Decisions Made
- El test redirige `sqlsrv_ti` a sqlite en memoria: sin eso, el RED intentaría conectar al SQL Server real.
- Además de `DB::listen` (que no ve consultas fallidas), se afirma que `sqlsrv_ti` no aparece en `DB::getConnections()`.

## Deviations from Plan

None - plan executed exactly as written.

## TDD Gate Compliance

RED se ejecutó y falló antes del cambio, pero test e implementación van en un solo commit (`b3c13073`) porque el plan y el orquestador piden un commit por requisito. No hay commit `test(...)` separado.

## Issues Encountered
None.

## User Setup Required
None.

## Next Phase Readiness
Sin bloqueos. Si algún cliente externo usaba `debug.conexion_tow_pro` para monitorear la conexión, dejará de verla (no se encontró ninguno en el repo).

## Self-Check: PASSED
- FOUND: tests/Feature/NotificarCortadoRolloOrdenProduccionTest.php
- FOUND: commit b3c13073
