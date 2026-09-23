---
phase: 08-erp-quick-wins
plan: 03
subsystem: routing
tags: [routes, contract-test, sqlsrv-ti, like, sargable]

requires: []
provides:
  - "0 route() literales a nombres inexistentes en app/ y resources/views, con RouteNameContractTest (ERP-F0-11)"
  - "Pagina /configuracion/utileria/cargarcatalogos retirada (ruta, variable idrol 68 y vista) (ERP-F0-11)"
  - "Duplicar modelo codificado inexistente y borrar usuario inexistente vuelven a su listado (ERP-F0-11)"
  - "Patrones LIKE de InventarioReservasService sin comodin inicial y whereIn sin LTRIM/RTRIM (ERP-F0-03, SIN COMMIT: pendiente de aprobacion)"
affects: [configuracion, planeacion-catalogos, programa-urd-eng]

tech-stack:
  added: []
  patterns:
    - "Contrato de nombres de ruta: regex sobre el codigo + Route::has() (tests/Feature/RouteNameContractTest.php)"

key-files:
  created:
    - tests/Feature/RouteNameContractTest.php
    - tests/Feature/UsuarioDestroyRedirectTest.php
    - tests/Unit/InventarioReservasPatronesTest.php (sin commit)
  modified:
    - routes/modules/configuracion.php
    - app/Http/Controllers/Planeacion/CatalogoPlaneacion/ModelosCodificados/CodificacionController.php
    - app/Http/Controllers/UsuarioController.php
    - app/Services/ProgramaUrdEng/InventarioReservasService.php (sin commit)
  deleted:
    - resources/views/modulos/cargar-catalogos.blade.php

key-decisions:
  - "cargar-catalogos: el usuario respondio \"retirar\""
  - "ERP-F0-03 no se commitea: sqlsrv_ti no responde desde esta maquina y las consultas A/B las tiene que correr el usuario en SSMS"

requirements-completed: [ERP-F0-11]

duration: 15min
completed: 2026-09-23
---

# Phase 8 Plan 03: Rutas rotas y LIKE sargable Summary

**Las 3 `route()` a nombres inexistentes quedan corregidas o retiradas, y un test de contrato impide que vuelvan. La página de cargar catálogos, que nunca funcionó, se retira. Los patrones LIKE del inventario disponible de julios ya no llevan comodín inicial, pero ese cambio queda sin commit hasta que el usuario lo apruebe.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-09-23
- **Tasks:** 3/4 (Tarea 4, el checkpoint de A/B, queda abierta: la corre el usuario)
- **Files:** 6 con commit (1 borrado) + 2 sin commit

## Tarea 1: decisión de cargar-catalogos (checkpoint)

Respuesta del usuario (vía orquestador), literal: **"retirar"**

## Accomplishments

- **ERP-F0-11, retiro:** en `routes/modules/configuracion.php` se borran `$cargarCatalogos = 68;`, la variable en las dos listas `use (...)` y el `Route::view('/cargarcatalogos', ...)`. También se borra `resources/views/modulos/cargar-catalogos.blade.php` (166 líneas). Antes de borrar, el grep de 0 llamadores (`cargar-catalogos|cargarcatalogos|$cargarCatalogos` en app, resources, routes y tests) solo encontró esas líneas.
- **ERP-F0-11, nombres corregidos:**
  - `CodificacionController::create`, en la rama "duplicate no encontrado": `codificacion.index` → `planeacion.catalogos.codificacion-modelos`.
  - `UsuarioController::destroy`, en la rama "usuario no encontrado": `usuarios.select` → `configuracion.usuarios.select`. Detalle: la `RouteNotFoundException` la atrapaba el `catch (\Exception)` genérico, así que el usuario veía "Verifica que no tenga registros relacionados", un mensaje falso. El test lo fija con el flash exacto `'Usuario no encontrado'`.
- **RouteNameContractTest:** recorre `app/` y `resources/views` (*.php) con la regex del plan y reporta `archivo => nombre` por cada nombre sin `Route::has()`. En RED encontró exactamente las 3 entradas previstas y ninguna adicional.
- **ERP-F0-03 (SIN COMMIT):** `PATTERN_RIZO = 'JU-ENG-RI%'`, `PATTERN_PIE = 'JU-ENG-PI%'` y `PATTERN_URDIDO = 'JULIO-URDIDO%'`. El join a InventDim pasa a `->whereIn('d.InventLocationId', self::ALMACENES)`, y el docblock cita §2.6 (2147 ms → 130 ms). No se tocan los LTRIM/RTRIM de columnas proyectadas ni el de WMSLocationId.

## Task Commits

1. **Tarea 1: decisión** - sin commit (checkpoint, respuesta "retirar")
2. **Tarea 2: rutas rotas y contrato (ERP-F0-11)** - `c3bf47c1` (fix)
3. **Tarea 3: LIKE sin % inicial (ERP-F0-03)** - **sin commit**
4. **Tarea 4: comparación A/B** - **pendiente de aprobación del usuario (consultas A/B en SSMS)**

## ERP-F0-03: pendiente de aprobación del usuario (consultas A/B en SSMS)

`sqlsrv_ti` (192.168.2.28) da timeout desde esta máquina (el orquestador lo confirmó hoy), así que las consultas no se pudieron correr aquí. Los dos archivos quedan **modificados y sin stage** en el árbol:

- `app/Services/ProgramaUrdEng/InventarioReservasService.php`
- `tests/Unit/InventarioReservasPatronesTest.php` (nuevo, sin seguimiento)

Para aprobar, correr en SSMS contra TI_PRO. Las dos consultas deben devolver 0 filas:

```sql
-- A: ItemIds que el patron viejo encuentra y el nuevo no
SELECT DISTINCT s.ItemId FROM InventSum s WITH (NOLOCK)
WHERE s.DATAAREAID = 'PRO'
  AND (s.ItemId LIKE '%JU-ENG-RI%' OR s.ItemId LIKE '%JU-ENG-PI%' OR s.ItemId LIKE '%JULIO-URDIDO%')
  AND NOT (s.ItemId LIKE 'JU-ENG-RI%' OR s.ItemId LIKE 'JU-ENG-PI%' OR s.ItemId LIKE 'JULIO-URDIDO%');

-- B: almacenes que solo casan con LTRIM/RTRIM
SELECT DISTINCT d.InventLocationId FROM InventDim d WITH (NOLOCK)
WHERE d.DATAAREAID = 'PRO'
  AND LTRIM(RTRIM(d.InventLocationId)) IN ('A-JUL/TELA', 'A-JUL/URD')
  AND d.InventLocationId NOT IN ('A-JUL/TELA', 'A-JUL/URD');
```

- Si A y B dan 0 filas: `git add` de los dos archivos y commit `perf(08-03): ERP-F0-03 LIKE sin comodin inicial en inventario disponible`.
- Si aparecen filas: no commitear, y conservar el `%` inicial en el patrón afectado (o el LTRIM/RTRIM si falla B).

## Verification

- `php artisan test --filter="RouteNameContractTest|UsuarioDestroyRedirectTest"`: RED antes del cambio (3 rutas rotas, y el flash era el del catch genérico). GREEN después: junto con `ConfiguracionRoutePermissionTest`, 5 passed y 51 assertions.
- `php artisan test --filter="InventarioReservasPatronesTest|ReservarConTelarTransaccionTest|AtadoDeJulioReservadoTest"`: RED (4 fallos) antes del cambio y GREEN después (19 passed, 69 assertions).
- `grep -c "'%JU-ENG\|'%JULIO-URDIDO"` = 0; `grep -c "LTRIM(RTRIM(d.InventLocationId))'), self::ALMACENES"` = 0.
- Pint `--test` sobre todos los PHP tocados: pass.
- La suite completa se corre una sola vez, al cierre de 08-05 (instrucción del orquestador). El resultado está en 08-05-SUMMARY.

## Lines deleted

- ERP-F0-11 (commit `c3bf47c1`): −173 / +119. De eso, la vista es −166, la ruta y la variable −5 (+3 por reescribir las listas `use`) y cada controlador −1/+1. Los tests nuevos suman +115.
- ERP-F0-03 (sin commit): cambio en sitio, 3 constantes y 1 whereIn.

## Deviations from Plan

- **Suite completa:** el plan la corría al cierre de la Tarea 4. Por instrucción del orquestador se corre una sola vez al final de 08-05.
- **Test de patrones:** usa `#[DataProvider]` (PHPUnit 11) en vez del docblock `@dataProvider`, que está deprecado.

## TDD Gate Compliance

RED y luego GREEN en las Tareas 2 y 3. Test e implementación van en un solo commit por requisito, así que no hay commits `test(...)` separados.

## Known Stubs
None.

## User Setup Required

- **SYSRoles idrol 68 (Cargar Catálogos) sigue en el menú; desactivarlo en Gestión de Módulos (pendiente del usuario).** Su ruta ya no existe, así que la entrada lleva a un 404.
- Correr las consultas A/B en SSMS y aprobar o rechazar ERP-F0-03 (ver arriba).

## Self-Check: PASSED

- FOUND: tests/Feature/RouteNameContractTest.php
- FOUND: tests/Feature/UsuarioDestroyRedirectTest.php
- FOUND: tests/Unit/InventarioReservasPatronesTest.php (sin commit, a propósito)
- FOUND: commit c3bf47c1
- MISSING (a propósito): resources/views/modulos/cargar-catalogos.blade.php
