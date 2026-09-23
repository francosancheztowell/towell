---
phase: 08-erp-quick-wins
plan: 04
subsystem: cleanup
tags: [dead-code, refactor]

requires:
  - phase: 08-02
    provides: "vistas catalogos-atadores ya con bg-X/NN"
provides:
  - "ERP-F0-07: 12 metodos privados muertos (10 + buildReporteResumenData x2) borrados"
  - "ERP-F0-07: MecActividadesController duplicado, CRUD sin ruta de ReqProgramaTejidoLine, create/edit sin ruta de Tejedores y 13 publicos sin llamador borrados"
  - "ERP-F0-07: internalToast de catcodificacion y el modal de vista muerto de 3 catalogos de Atadores borrados"
affects: [planeacion, tejedores, mecanicos, urdido, engomado, atadores, catcodificacion]

tech-stack:
  added: []
  patterns:
    - "Borrado de metodos con token_get_all (docblock + modificadores + llaves balanceadas), no con regex"

key-files:
  created: []
  modified:
    - app/Imports/ReqProgramaTejidoUpdateImport.php
    - app/Services/ProgramaUrdEng/BomMaterialesService.php
    - app/Http/Controllers/Tejido/Reportes/ReporteMarcasFinalesController.php
    - app/Imports/ReqModelosCodificadosImport.php
    - app/Imports/ReqVelocidadStdImport.php
    - app/Exports/Reporte00EAtadoresExport.php
    - app/Http/Controllers/mecanicos/OrdenesTrabajoMecaController.php
    - app/Http/Controllers/Planeacion/Utilerias/MoverOrdenesController.php
    - app/Http/Controllers/Engomado/ReportesEngomadoController.php
    - app/Http/Controllers/Urdido/ReportesUrdidoController.php
    - app/Http/Controllers/Planeacion/ProgramaTejido/ReqProgramaTejidoLineController.php
    - app/Http/Controllers/Tejedores/Configuracion/TelaresOperador/TelTelaresOperadorController.php
    - app/Http/Controllers/Tejedores/TelActividadesBPMController.php
    - app/Helpers/FolioHelper.php
    - app/Helpers/format_helpers.php
    - app/Services/Programas/ProgramaPrioridadService.php
    - app/Services/ProgramaUrdEng/InventarioTelaresService.php
    - app/Support/Programas/ProgramaConfig.php
    - app/Services/Mecanicos/ReporteEstadoMaquinaService.php
    - app/Repositories/UsuarioRepository.php
    - app/Services/PermissionService.php
    - resources/js/catcodificacion/index.js
    - resources/views/modulos/catalogos-atadores/{maquinas,comentarios,actividades}/index.blade.php
  deleted:
    - app/Http/Controllers/mecanicos/MecActividadesController.php

key-decisions:
  - "08-01 no se ejecuto (excluido por el usuario); la dependencia se trato como satisfecha y no se tocaron fixtures"
  - "Imports que quedaron sin referencia por el borrado se quitaron (EngProgramaEngomado, ReqProgramaTejido, Rule); los que ya estaban sin uso en HEAD no"
  - "Comentarios de seccion huerfanos de ReqProgramaTejidoLineController (Reglas/Store/Show/Update/Destroy) tambien se quitaron"

requirements-completed: [ERP-F0-07]

duration: 25min
completed: 2026-09-23
---

# Phase 8 Plan 04: Código muerto confirmado (ERP-F0-07) Summary

**Se borraron 1,189 líneas de código muerto en 3 commits bisectables: 12 métodos privados sin llamada, un controlador duplicado, un CRUD sin ruta, create/edit que ninguna ruta usa, 13 públicos sin llamador, un toast de respaldo que nunca se ejecutaba y el modal "Ver" de tres catálogos de Atadores que nadie abría. Antes de cada borrado se corrió el grep de 0 llamadores.**

## Performance

- **Duration:** ~25 min
- **Completed:** 2026-09-23
- **Tasks:** 3/3
- **Files:** 26 (25 modificados + 1 borrado)

## Grep de 0 llamadores

Se usó `grep -rnw "<símbolo>" app resources routes tests config database public/js`. Para los privados de nombre genérico (`F`, `parseInteger`, `normalizar`), el grep se hizo en su propio archivo, buscando `->x(`, `::x(`, `'x'` y `[$this, 'x']`, porque en otras clases hay homónimos vivos. En todos los casos solo apareció la definición. **Ningún símbolo resultó vivo y no se saltó ningún borrado.**

### Tarea 1: privados (commit `e64b6098`, −554)

| Archivo | Símbolo | Líneas (HEAD) | −L |
|---|---|---|---|
| app/Imports/ReqProgramaTejidoUpdateImport.php | updateExistingRecord (updateExistingRecordFields intacto) | 581-679 | 100 |
| app/Services/ProgramaUrdEng/BomMaterialesService.php | getInventarioPorMaterialesUrdido (...UrdidoRaw intacto) | 334-414 | 82 |
| app/Http/Controllers/Tejido/Reportes/ReporteMarcasFinalesController.php | obtenerPreview (obtenerPreviewPorDia intacto) | 84-157 | 75 |
| ídem | calcularPromediosPorTelar | 277-320 | 45 |
| app/Imports/ReqModelosCodificadosImport.php | F | 600-620 | 22 |
| ídem | getTotalValue | 888-923 | 37 |
| app/Imports/ReqVelocidadStdImport.php | parseInteger | 191-202 | 13 |
| app/Exports/Reporte00EAtadoresExport.php | copyRowStyles (copyRowStylesToRange intacto) | 702-710 | 10 |
| app/Http/Controllers/mecanicos/OrdenesTrabajoMecaController.php | debeUsarRutaSoloCalificacion | 1453-1459 | 8 |
| app/Http/Controllers/Planeacion/Utilerias/MoverOrdenesController.php | normalizar (normalizarEnProceso intacto) | 272-278 | 8 |
| app/Http/Controllers/Engomado/ReportesEngomadoController.php | buildReporteResumenData + `use EngProgramaEngomado` | 370-443, :12 | 76 |
| app/Http/Controllers/Urdido/ReportesUrdidoController.php | buildReporteResumenData | 1575-1651 | 78 |

### Tarea 2: controladores y públicos (commit `48625670`, −412)

| Archivo | Símbolo | −L |
|---|---|---|
| app/Http/Controllers/mecanicos/MecActividadesController.php | archivo entero. La ruta usa `mecanicos\Catalogos\MecActividadesController` (mecanicos.php:3). El grep del FQCN solo encontró el propio archivo | 133 |
| ReqProgramaTejidoLineController.php | store, show, update, destroy, rules, sanitize, más 5 comentarios de sección huérfanos y los `use` ReqProgramaTejido y Rule. Sigue `index` (planeacion.php:321 y :401) | 86 |
| TelTelaresOperadorController.php | create, edit (fuera por `->only(['index','store','update','destroy'])`, tejedores.php:85) | 31 |
| TelActividadesBPMController.php | create, edit (ídem, tejedores.php:77) | 19 |
| app/Helpers/FolioHelper.php | obtenerInfoSecuencia, reiniciarConsecutivo | 43 |
| app/Services/Programas/ProgramaPrioridadService.php | recalculatePriorities | 13 |
| app/Services/ProgramaUrdEng/InventarioTelaresService.php | validarYActualizarNoOrden | 9 |
| app/Support/Programas/ProgramaConfig.php | accionesMetrosPermitidas | 14 |
| app/Services/Mecanicos/ReporteEstadoMaquinaService.php | colorCelda (colorSalon intacto) | 5 |
| app/Imports/ReqModelosCodificadosImport.php | getRowCount, getCreatedCount, getUpdatedCount, getErrors. Ninguna interfaz implementada (ShouldQueue, SkipsEmptyRows, ToCollection, WithBatchInserts, WithChunkReading, WithEvents, WithStartRow) los exige | 24 |
| app/Helpers/format_helpers.php | formatearFechaInputLocal con su `if (! function_exists(...))`. getDeviceInfo intacto | 17 |
| app/Repositories/UsuarioRepository.php | getByArea | 8 |
| app/Services/PermissionService.php | getPermisosUsuario | 10 |

### Tarea 3: front (commit `4488f93f`, −223 / +5)

| Archivo | Símbolo | −L |
|---|---|---|
| resources/js/catcodificacion/index.js | `internalToast` y el bloque `window.showToast \|\| internalToast` pasan a `const showToast = window.showToast;` (bootstrap.js:75 siempre lo define). 2 llamadas `internalToast(` pasan a `showToast(`, y `fallbackToast: internalToast` pasa a `showToast`. lmat-modal.js no se tocó | 42 (+5) |
| catalogos-atadores/maquinas/index.blade.php | `#viewModal`, openViewModal, closeViewModal y la rama `viewModal` del `window.onclick` | 57 |
| catalogos-atadores/comentarios/index.blade.php | ídem | 62 |
| catalogos-atadores/actividades/index.blade.php | ídem | 62 |

`openViewModal(` solo aparecía en su definición en las 3 vistas. `engomado/captura-formula` (openViewModal vivo en :46) y todos los `deleteModal` siguen intactos. Ningún `view_*` id quedó huérfano.

## Lines deleted

**Total: −1,189 / +5** (554 + 412 + 223).

## Excluido por CONTEXT (intacto)

TelegramController, getOrdenProduccion (`/orden-produccion`), el tablero clásico Urd/Eng, OrdenKarlMayerService, NuevoRequerimiento (§2.1 fila 11), `resources/js/programa-tejido/` y `resources/views/modulos/programa-tejido/`.

## Verification

- Tarea 1: `php -l` pasa en los 10 archivos. El grep de los 8 símbolos con `\b...\(` da 0, y el de `F`/`parseInteger`/`normalizar` en sus archivos también da 0. Filtro ProgramaTejidoImportParseFloatTest|BomMaterialesServiceTest|Reporte00EAtadoresExportTest|OrdenesTrabajoMecaControllerTest|MoverOrdenesFechaFinalizaTest|Reporte03OeeFechaFinalizaTest: **34 passed (121 assertions)**.
- Tarea 2: `composer dump-autoload`. El archivo duplicado ya no existe. El grep de los 13 públicos da 0. `route:list --path=req-programa-tejido-line` sigue listando `planeacion.req-programa-tejido-line`. Filtro LiberarCamposEditablesTest|LiberarOrdenesLiberarTest|InventarioTelaresFiltrosTest|ProgramarUrdEngControllerTest|ProgramBoardActionServiceTest|ProgramarUrdidoActualizarStatusAxTest|ReporteEstadoMaquinaServiceTest: **49 passed (238 assertions)**.
- Tarea 3: `npm run build` verde (4.22 s). `! grep -rqE "internalToast|openViewModal|viewModal"` en los 4 archivos da OK, y captura-formula conserva openViewModal.
- Pint `--test` en los PHP tocados: solo UsuarioRepository.php y PermissionService.php fallan, con los **mismos fixers que ya fallaban en HEAD** (concat_space, single_blank_line_at_eof y otros). Son preexistentes y quedan fuera de alcance, así que no se reformatearon.
- **Suite completa:** por instrucción del orquestador se corre una sola vez al cierre de 08-05. El resultado está en 08-05-SUMMARY.

## Deviations from Plan

- **[Rule 1 - Limpieza derivada] Comentarios e imports huérfanos:** al quitar el CRUD de ReqProgramaTejidoLineController quedaron 5 comentarios de sección sin su código, y 2 `use` (ReqProgramaTejido, Rule) dejaron de usarse. Se quitaron en el mismo commit. Lo mismo pasó con `use EngProgramaEngomado` en ReportesEngomadoController (Tarea 1). El plan pedía quitar los `use` sobrantes; los comentarios se quitaron por la misma razón.
- **Suite completa:** se aplaza al final de 08-05 (orquestador).
- **Dependencia 08-01:** 08-01 no se ejecutó (el usuario lo excluyó). La dependencia se trató como satisfecha y no se hizo ningún cambio de 08-01.

## Known Stubs
None.

## Issues Encountered
- En Git Bash, `sed -i '/^use ...\\...;\r\?$/d'` no borraba las líneas `use`, así que se usó Edit. Sin impacto.

## User Setup Required
None.

## Self-Check: PASSED
- FOUND: commits e64b6098, 48625670, 4488f93f
- MISSING (a propósito): app/Http/Controllers/mecanicos/MecActividadesController.php
