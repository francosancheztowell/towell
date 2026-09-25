---
phase: 08-erp-quick-wins
plan: 05
subsystem: routing
tags: [dead-code, routes, attack-surface, refactor]

requires:
  - phase: 08-03
    provides: "RouteNameContractTest (vigila que ningun route() literal apunte a una ruta borrada)"
provides:
  - "ERP-F0-08: 6 rutas editar-ordenes-programadas que daban 500 retiradas; index de Urdido y Engomado vivas"
  - "ERP-F0-08: /modulos-sin-auth, /test-404 (SystemController) y 5 metodos de ModulosController sin consumidor retirados; sincronizarPermisos vivo"
  - "ERP-F0-08: 8 rutas + 4 metodos de catalogo PT, 19 rutas + 11 metodos de NuevoRequerimiento y show/updateStatus de Consultar retirados"
  - "ERP-F0-08: 5 endpoints POST/PATCH sin consumidor (guardarTabla, recalcularMarbete, buscar, bulkSave, cambiarStatus) retirados"
affects: [configuracion, urdido, engomado, planeacion, programa-tejido, inventario-trama, tejedores, tejido]

tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - routes/modules/urdido.php
    - routes/modules/engomado.php
    - routes/modules/configuracion.php
    - routes/public.php
    - routes/modules/planeacion.php
    - routes/modules/tejido.php
    - routes/modules/tejedores.php
    - app/Http/Controllers/ModulosController.php
    - app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoCatalogosController.php
    - app/Http/Controllers/Tejido/InventarioTrama/NuevoRequerimientoController.php
    - app/Http/Controllers/Tejido/InventarioTrama/ConsultarRequerimientoController.php
    - app/Services/Tejido/InventarioTrama/NuevoRequerimientoService.php
    - app/Http/Controllers/Tejido/CortesEficiencia/CortesEficienciaController.php
    - app/Http/Controllers/Planeacion/CatCodificados/CatCodificacionController.php
    - app/Http/Controllers/Planeacion/CatalogoPlaneacion/ModelosCodificados/CodificacionController.php
    - app/Http/Controllers/Tejedores/BPMTejedores/TelBpmLineController.php
    - app/Http/Controllers/Tejido/ProduccionReenconado/ProduccionReenconadoCabezuelaController.php
    - tests/Feature/PublicSensitiveRoutesAuthTest.php
  deleted:
    - app/Http/Controllers/SystemController.php

key-decisions:
  - "08-01 no se ejecuto (excluido por el usuario); la dependencia se trato como satisfecha"
  - "Constructores, FormRequest y metodos de servicio que quedan sin uso se anotan, no se podan (regla del plan)"
  - "Imports que quedaron sin referencia por el borrado si se quitaron"
  - "PublicSensitiveRoutesAuthTest ahora fija que modulos.gestion.index ya no se registra"

requirements-completed: [ERP-F0-08]

duration: 25min (+ suite completa)
completed: 2026-09-23
---

# Phase 8 Plan 05: Rutas y endpoints sin consumidor (ERP-F0-08) Summary

**Se retiraron 50 rutas y 28 métodos de controlador (−1,009 líneas) en 3 commits: 6 rutas que daban 500, la superficie `/modulos-sin-auth` y `/test-404`, 5 métodos de gestión de módulos que nadie llamaba, los endpoints AJAX de Inventario Trama que la migración a Livewire dejó huérfanos, 4 catálogos de Programa Tejido sin consumidor y 5 endpoints POST/PATCH muertos. Antes de cada borrado se corrió el grep de nombre de ruta, URL y método.**

## Performance

- **Duration:** ~25 min de trabajo, más la suite completa
- **Completed:** 2026-09-23
- **Tasks:** 3/3
- **Files:** 19 (18 modificados + 1 borrado)

## Grep de 0 consumidores

Para cada ruta se hizo `grep -rnF` del nombre de ruta, del fragmento de URL y del método en `resources public/js app tests` (y `routes` para los registros). También se buscaron concatenaciones (`/buscar'`, `` `/...` ``, `apiBase + '/...'`). Solo aparecieron el registro y la definición. **No quedó ningún consumidor vivo y no se saltó ningún borrado.**

Homónimos revisados, que no son consumidores:
- `getCalibres`/`getFibras`/`getColores` de `produccion-reenconado-cabezuela.blade.php` llaman a `tejido.produccion.reenconado.*` (ProduccionReenconadoCabezuelaController), no a NuevoRequerimiento.
- `actualizarCantidad` de `nuevo-requerimiento.blade.php` es `wire:change` sobre el componente Livewire, no sobre el controlador.
- `flogs-id-from-twflogs` es un endpoint distinto y vivo (programa-tejido/index.js:287 y liberar-ordenes).
- `cambiarStatus` de `Livewire/InventarioTrama/ConsultarRequerimiento` es del componente, no del controlador de Reenconado.

## Tarea 1 (commit `3ab7debc`, −201 / +7)

| Qué | Dónde | −L |
|---|---|---|
| 4 rutas `editar-ordenes-programadas/{actualizar,obtener-orden,actualizar-julios,actualizar-hilos-produccion}` (el controlador solo tiene `index` y hoy daban 500) | routes/modules/urdido.php | 4 |
| 2 rutas `editar-ordenes-programadas/{actualizar,obtener-orden}` (ídem) | routes/modules/engomado.php | 2 |
| `toggle-acceso`, `toggle-permiso`, `/{modulo}/duplicar` y el grupo `api/modulos/{nivel,submodulos}` | routes/modules/configuracion.php | 13 |
| getModulosPorNivel, getSubmodulos, duplicar, toggleAcceso, togglePermiso, más la rama `routeIs('modulos.gestion.*')` de getModulosIndexRoute | app/Http/Controllers/ModulosController.php | 146 |
| Grupo `modulos-sin-auth` (`modulos.gestion.*`), `/test-404`, y los `use` SystemController y ModulosController | routes/public.php | 13 |
| Archivo entero (solo tenía `test404`) | app/Http/Controllers/SystemController.php | 11 |
| Sin los 4 casos `/modulos-sin-auth` ni los 4 `assertRouteHasAuth('modulos.gestion.*')`. Ahora afirma que `modulos.gestion.index` ya no se registra | tests/Feature/PublicSensitiveRoutesAuthTest.php | 12 (+7) |

Siguen vivas: `urdido.editar.ordenes.programadas`, `engomado.editar.ordenes.programadas` y `sincronizarPermisos` (gestion-modulos/index.blade.php:525).

## Tarea 2 (commit `dea658c2`, −423)

| Qué | Dónde | −L |
|---|---|---|
| 8 rutas (`/programa-tejido/*` y `/muestras/*`): flogs-id-options, flogs-by-tamano-clave, ultima-fecha-final-telar, eficiencia-std | routes/modules/planeacion.php | 8 |
| getFlogsIdOptions, getFlogsByTamanoClave, getEficienciaStd, getUltimaFechaFinalTelar. Ninguno tenía llamada interna (`$this->`) | ProgramaTejidoCatalogosController.php | 154 |
| 19 rutas AJAX de NuevoRequerimiento (7 `tejido.inventario.trama.nuevo.requerimiento.*` y 12 `modulo.nuevo.requerimiento.*`), más `show`/`status` de Consultar | routes/modules/tejido.php | 21 |
| guardarRequerimientos, getTurnoInfo, enProcesoInfo, actualizarCantidad, getCalibres, getFibras, getColores, buscarNombresColor, buscarArticulos, buscarFibras, buscarCodigosColor, más 7 `use` sin referencia | NuevoRequerimientoController.php | 171 |
| show, updateStatus, más 3 `use` sin referencia | ConsultarRequerimientoController.php | 67 |
| Claves `actualizarCantidadUrl` y `guardarUrl` del VM (0 lecturas en resources/ y app/Livewire) | NuevoRequerimientoService.php | 2 |

Siguen vivas: `tejido.inventario.trama.nuevo.requerimiento`, `modulo.nuevo.requerimiento` (index), `*.consultar.requerimiento` (index), `*.consultar.requerimiento.resumen`, `salon-options` y `calendario-lineas`.

**Diferencia con R1.1:** R1.1 hablaba de 7 × 2 rutas de catálogo PT. El plan verificó 4 × 2 = 8 con 0 consumidores, y solo esas se borraron. No se buscaron más.

## Tarea 3 (commit `af603d5b`, −385)

| Endpoint | Método (líneas HEAD) | −L |
|---|---|---|
| `cortes.eficiencia.guardar.tabla` (tejido.php) | CortesEficienciaController::guardarTabla (1553-1668, con docblock), más `use ValidationException` | 119 |
| `codificacion.recalcular-marbetes` (planeacion.php) | CatCodificacionController::recalcularMarbete (225-307) | 86 |
| `codificacion.buscar` (planeacion.php) | CodificacionController::buscar (1160-1236), sobre la versión de 08-03 | 79 |
| `tel-bpm-line.bulk` (tejedores.php) | TelBpmLineController::bulkSave (170-231) | 64 |
| `produccion.reenconado.cambiar-status` (tejido.php, un PATCH **sin** module.permission) | ProduccionReenconadoCabezuelaController::cambiarStatus (410-443) | 37 |

`git diff --stat HEAD~3 -- routes/modules/telegram.php app/Http/Controllers/Telegram` sale vacío. `/orden-produccion`, el tablero clásico Urd/Eng, salon-options y calendario-lineas siguen intactos.

## Lines deleted

`git diff --shortstat 53cbdf3e af603d5b`: **19 files changed, 7 insertions(+), 1009 deletions(-)** (201 + 423 + 385).

## Quedan sin uso (anotados, NO podados, por regla del plan)

- `App\Http\Requests\Tejido\InventarioTrama\GuardarRequerimientoRequest`: su único uso era `guardarRequerimientos`.
- `CatalogoTramaService::nombresColor`: 0 llamadores (lo llamaba `buscarNombresColor`). `calibres`, `fibras` y `colores` siguen vivos desde `Livewire/InventarioTrama/NuevoRequerimiento`.
- Dependencias de constructor sin uso: `NuevoRequerimientoController::$requerimientos` y `$catalogo`, y `ConsultarRequerimientoController::$statusService`. Los controladores solo sirven la página contenedora de Livewire.
- No quedó ningún método privado huérfano en los controladores de 08-05 (se comparó cada `private|protected function` contra su versión anterior).

## Verification

- Tarea 1: el grep del verify dio OK. Filtro RouteNameContractTest|PublicSensitiveRoutesAuthTest|ConfiguracionRoutePermissionTest|RutasDestructivasPermisoTest|EngomadoEdicionOrdenesTest|ModulosRutaTest: **33 passed (117 assertions)**.
- Tarea 2: el grep del verify dio OK. Filtro RouteNameContractTest|NuevoRequerimientoLivewireTest|ConsultarRequerimientoLivewireTest|ProgramaTejidoCatalogosTest: 11 passed y 3 failed. Los 3 son los de la línea base de NuevoRequerimientoLivewireTest (`QueryException` por timeout TCP de 15 s a sqlsrv_ti, 192.168.2.28).
- Tarea 3: el grep del verify dio 0 registros. Filtro CatCodificadosExcelImportTest|RouteNameContractTest|RutasDestructivasPermisoTest|PlaneacionMutationAuthorizationTest: **33 passed (107 assertions)**. Como extra, `--filter="Cortes|TelBpm|BpmTejedores|Reenconado|Codificacion"`: 24 passed.
- Pint `--test` sobre todos los PHP tocados: pass.
- `npm run build` verde (4.22 s). `npm run typecheck` (`tsc --noEmit`) salió con exit 0. Ambos se corrieron **antes** de lanzar la suite completa.
- **Suite completa** (`php artisan test`, una sola vez para 08-03, 08-04 y 08-05, 2218 s): **1159 passed, 49 skipped, 11 failed (18231 assertions)**. Los 11 fallos son **exactamente los de la línea base**. No hay fallos nuevos:
  - `Tests\Unit\Crudo\CrudoDashboardServiceTest` (4) y `CrudoMachineDetailTest` (1): ajenos.
  - `Tests\Unit\Programas\ProgramBoardLivewireTest` (2) y `ProgramBoardStructureTest` (1): cruzan con el WIP sin commit del usuario en program-board.
  - `Tests\Feature\NuevoRequerimientoLivewireTest` (3): `QueryException` por timeout a sqlsrv_ti (TI_PRO).
  - Cuadre contra 08-02 (1157 passed): +1 RouteNameContractTest, +1 UsuarioDestroyRedirectTest y +4 InventarioReservasPatronesTest (ERP-F0-03, sin commit pero presente en el árbol), −4 casos `/modulos-sin-auth` del data provider de PublicSensitiveRoutesAuthTest. Resultado: 1159.
  - La suite corrió con los cambios sin commit de ERP-F0-03 (InventarioReservasService) y el WIP del usuario presentes en el árbol.

## Deviations from Plan

- **[Rule 1 - Limpieza derivada] Imports sin referencia:** se quitaron los `use` que dejaron de usarse por el borrado. Fueron `ModulosController` y `SystemController` en public.php, 7 en NuevoRequerimientoController, 3 en ConsultarRequerimientoController y `ValidationException` en CortesEficienciaController. Constructores, FormRequest y servicios no se tocaron.
- **PublicSensitiveRoutesAuthTest:** además de quitar los casos, se añadió una afirmación de que `modulos.gestion.index` ya no se registra, para que no regrese.
- **Dependencia 08-01:** no se ejecutó (el usuario lo excluyó) y se trató como satisfecha.

## Known Stubs
None.

## Threat Flags
None. Solo se reduce superficie; no se añadió ninguna ruta.

## User Setup Required

- **Deploy:** hacer cache-bust de assets (`npm run build`, ya verde). Una tablet con JS viejo que pegue a `/modulo-nuevo-requerimiento/guardar` (o a cualquier ruta retirada) recibirá **404**; la pantalla actual es Livewire y no las usa.
- `php artisan route:clear` (o `route:cache`) en producción, si las rutas están cacheadas.

## Self-Check: PASSED
- FOUND: commits 3ab7debc, dea658c2, af603d5b
- MISSING (a propósito): app/Http/Controllers/SystemController.php
