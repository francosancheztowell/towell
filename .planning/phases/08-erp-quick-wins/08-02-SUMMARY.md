---
phase: 08-erp-quick-wins
plan: 02
subsystem: ui
tags: [tailwind-v4, vite, livewire, sqlsrv-2008, polling, csrf]

requires: []
provides:
  - "0 bg-opacity-* en vistas; fondos de modal translucidos con bg-X/NN (ERP-F0-04)"
  - "max-w-md en el modal de reporte-resumen-engomado (ERP-F0-04)"
  - "Tailwind se descarga una vez por pagina (ERP-F0-05)"
  - "Urdido-BPM-Line toggle con http.post + notify, sin 419 silencioso (ERP-F0-09)"
  - "VerificaMaquina pagina con PaginacionCompat, sin OFFSET (ERP-F0-10)"
  - "Polling de Programa Atadores pausado con document.hidden y a 15 s (ERP-F0-10, sin commit: viaja con el WIP del usuario)"
affects: [engomado, urdido, atadores, mecanicos, catalogos, layout]

tech-stack:
  added: []
  patterns:
    - "Guardas de vistas en tests/Unit/VistasTailwindV4Test.php (bg-opacity, max-md suelto, app.js sin app.css)"
    - "Guarda de SQL Server 2008 R2: DB::listen y ninguna consulta con 'offset'"

key-files:
  created:
    - tests/Unit/VistasTailwindV4Test.php
    - tests/Feature/VerificaMaquinaIndexPaginacionTest.php
  modified:
    - 17 vistas resources/views/... (ver lista abajo)
    - resources/js/app.js
    - resources/views/modulos/urdido/Urdido-BPM-Line/index.blade.php
    - app/Livewire/Mecanicos/VerificaMaquina/Index.php
    - app/Livewire/Concerns/ConTabla.php
    - resources/views/modulos/atadores/programaAtadores/index.blade.php (sin commit)

key-decisions:
  - "telar-requerimiento: se conserva bg-black/40 (lo que hoy renderiza) y solo se quita el bg-opacity-60 muerto"
  - "Los select() de VerificaMaquina se mueven al query antes de PaginacionCompat (paginar() no recibe columnas)"
  - "El polling de programaAtadores no se commitea: el archivo mezcla el WIP del usuario"

patterns-established:
  - "Paginacion Livewire en SQL Server 2008 R2: PaginacionCompat::paginar($query, N, $this->getPage())"

requirements-completed: [ERP-F0-04, ERP-F0-05, ERP-F0-09, ERP-F0-10]

duration: 60min
completed: 2026-09-23
---

# Phase 8 Plan 02: Bugs de front y perf rápida (Fase 0) Summary

**Fondos de modal translúcidos en 17 vistas (Tailwind v4 `bg-X/NN`), Tailwind servido una sola vez (el CSS del entry app.js baja de 207.61 kB a 57.33 kB), el toggle de Urdido-BPM-Line con `http.post` y aviso de 419, VerificaMaquina paginando sin OFFSET para SQL Server 2008 R2, y el polling de Atadores pausado con la pestaña oculta.**

## Performance

- **Duration:** ~60 min (incluye ~37 min de la suite completa)
- **Started:** 2026-09-23T00:10:00-06:00 (aprox.; preparación en paralelo con la suite de 08-06)
- **Completed:** 2026-09-23T01:32:38-06:00
- **Tasks:** 4/4
- **Files modified:** 25 (24 con commit + programaAtadores sin commit)

## Accomplishments
- **ERP-F0-04:** 32 `bg-opacity-*` en 17 vistas → `bg-black/50` x15, `bg-gray-900/50` x9, `bg-gray-600/50` x5, `bg-gray-900/10` x1, `bg-gray-400/10` x1, y `bg-black/40 bg-opacity-60` → `bg-black/40` x1. `max-md` → `max-w-md` en reporte-resumen-engomado:92. `npm run build` verde y las 6 clases nuevas más `max-w-md` están en `public/build/assets/app-*.css`.
- **ERP-F0-05:** `resources/js/app.js` ya no importa `../css/app.css`. Salida de vite build:
  - Antes: entry `app.js` → `app-CTMhRpEB.css` **207.61 kB** (gzip 38.13 kB) = Tailwind + CSS de librerías; entry `app.css` → `app-Didsjf1c.css` 150.07 kB (gzip 22.04 kB). Cada página bajaba Tailwind dos veces (~357 kB de CSS).
  - Después: entry `app.js` → `app-CN9kqvY_.css` **57.33 kB** (gzip 16.17 kB), solo librerías (Font Awesome, etc.); `app.css` sin cambio (150.07 kB). ~150 kB menos por página (−22 kB gzip).
  - El JS de app no cambia (2.52 kB, gzip 1.28 kB). El manifest conserva `resources/css/app.css`. Cascada intacta: `<x-layout-styles/>` (Tailwind) va antes que `<x-layout-scripts/>` (CSS de librerías), el mismo orden que dentro del bundle viejo.
- **ERP-F0-09:** `toggleActividad` de Urdido-BPM-Line es igual a la de Engomado: `http.post(route('urd-bpm-line.toggle'))`, `notify.error` si `!data.success`, 419 con mensaje de sesión expirada, y el comentario ponytail copiado. Los `@csrf` que quedan en la vista (líneas 9, 26, 41) son campos de los formularios terminar/autorizar/rechazar, no del toggle. Los `Swal.fire` restantes pertenecen a otras funciones de la vista (fuera de alcance: unificación de gemelas es Fase 2).
- **ERP-F0-10:** VerificaMaquina `render()` usa `PaginacionCompat::paginar($query->select([8 columnas]), 15, $this->getPage())`, con el mismo filtro y orden. El docblock de `ConTabla` usa `$this->paginar(...)`. `! grep -rq -e "->paginate(" app/Livewire` OK. Los otros 4 `paginate()` de §2.5 (controladores) quedan para Fase 1.
- **ERP-F0-10 polling:** `setInterval(refreshStatus, 15000)` y `if (document.hidden || refrescoEnVuelo) return;`.

## Task Commits

1. **Tarea 1: bg-opacity-* → /NN y max-md → max-w-md (ERP-F0-04)** - `6de22b1b` (fix): 17 vistas + test, 18 archivos
2. **Tarea 2: Tailwind una sola vez por página (ERP-F0-05)** - `287ff6a5` (perf)
3. **Tarea 3: Urdido-BPM-Line con http.post (ERP-F0-09)** - `7f0e73db` (fix)
4. **Tarea 4: VerificaMaquina sin OFFSET; polling Atadores (ERP-F0-10)** - `fd5d7ee6` (fix): solo Index.php, ConTabla.php y el test

## Files Created/Modified
- `tests/Unit/VistasTailwindV4Test.php` - 0 `bg-opacity-` en vistas; 0 `max-md` como token suelto (no confunde `max-md:` ni `max-w-md`); app.js sin `css/app.css`
- `tests/Feature/VerificaMaquinaIndexPaginacionTest.php` - 20 filas, `gotoPage(2)` → 5 elementos, total 20, primer folio VM00005; ninguna consulta con `offset`
- Vistas ERP-F0-04: `catalogosurdido/catalago-maquinas`, `components/telares/telar-requerimiento`, `modulos/catalogos-atadores/{actividades,comentarios,maquinas}/index`, `modulos/engomado/{BPM-Engomado/index,captura-formula/index,eng-actividades-bpm/index,modulo-produccion-engomado,programar-engomado,reporte-resumen-engomado}`, `modulos/gestion-modulos/index`, `modulos/urdido/{BPM-Urdido/index,produccion/_modal-oficial,programar-urdido,reportes-resumen-urdido}`, `modulos/usuarios/select`
- `resources/js/app.js` - sin import de app.css
- `resources/views/modulos/urdido/Urdido-BPM-Line/index.blade.php` - toggle con http.post
- `app/Livewire/Mecanicos/VerificaMaquina/Index.php` - PaginacionCompat
- `app/Livewire/Concerns/ConTabla.php` - docblock
- `resources/views/modulos/atadores/programaAtadores/index.blade.php` - **SIN COMMIT** (ver abajo)

## ERP-F0-10 polling: 2 líneas sin commit

ERP-F0-10 polling: 2 líneas sin commit, que viajan con el WIP del usuario en `resources/views/modulos/atadores/programaAtadores/index.blade.php`. El archivo tenía cambios sin commit del usuario (+115/−270). Se editó encima con Edit, sin `checkout`/`restore`/`stash`, y **no se hizo `git add`**. `git diff --stat` del archivo después: +116/−271 (el WIP original más las 2 líneas). `git show --stat fd5d7ee6` no lo incluye. Cuando el usuario commitee su WIP de Atadores, el cambio del polling entra con él.

## Verification
- `php artisan test --filter=VistasTailwindV4Test`: RED (2 fallos) antes del reemplazo; GREEN 3 passed después de las Tareas 1 y 2.
- `php artisan test --filter=VerificaMaquinaIndexPaginacionTest`: RED (consulta con `limit 15 offset 0`; los datos ya pasaban porque sqlite acepta OFFSET) → GREEN (1 passed, 8 assertions).
- `npm run build` verde en las Tareas 1 y 2; `! grep -rq "bg-opacity-" resources/views`; `! grep -q "css/app.css" resources/js/app.js`; `grep -q "resources/css/app.css" public/build/manifest.json`.
- Grep de Tarea 3: `http.post("{{ route('urd-bpm-line.toggle'` presente y `fetch("{{ route('urd-bpm-line.toggle'` ausente.
- Pint `--test` sobre los PHP tocados: pass.
- **Suite completa** (`php artisan test`, una vez, 2228 s): **1157 passed, 49 skipped, 11 failed**. Son los mismos 11 fallos de la corrida de 08-06 (1153 passed ahí; +4 = los tests nuevos de este plan). Ninguno toca archivos de este plan:
  - `Tests\Unit\Crudo\CrudoDashboardServiceTest` (4), `CrudoMachineDetailTest` (1): ajenos.
  - `Tests\Unit\Programas\ProgramBoardLivewireTest` (2), `ProgramBoardStructureTest` (1): cruzan con el WIP sin commit del usuario en `program-board.blade.php` / `reservar-programar.ts` / `program-board.css`.
  - `Tests\Feature\NuevoRequerimientoLivewireTest` (3): timeout TCP real contra `sqlsrv_ti` (192.168.2.28).

## Decisions Made
- `telar-requerimiento.blade.php:118`: `bg-black/40 bg-opacity-60` → `bg-black/40`, sin cambio visual, como pedía el plan.
- VerificaMaquina: las 8 columnas pasan de argumento de `paginate()` a `->select()` sobre el query, porque `PaginacionCompat::paginar` no recibe columnas.
- El comentario ponytail de VerificaMaquina dice "paginate() de Laravel" en vez de `->paginate(` para no romper la guarda `! grep -rq -e "->paginate(" app/Livewire`.

## Deviations from Plan

None - plan executed exactly as written. (La preparación de las Tareas 3 y 4 se hizo mientras corría la suite de 08-06, y `npm run build` se aplazó hasta que terminó para no vaciar `public/build` bajo tests que renderizan `@vite`. Los commits salieron en el orden 1-4.)

## TDD Gate Compliance

Las Tareas 1 y 4 (tdd="true") ejecutaron RED y luego GREEN, pero test e implementación van en el mismo commit por la regla de un commit por requisito. No hay commits `test(...)` separados.

## Known Stubs
None.

## Issues Encountered
- La suite completa tarda ~37 min, en buena parte por los timeouts de red de `NuevoRequerimientoLivewireTest` contra TI_PRO.

## User Setup Required
- Commitear el WIP de `programaAtadores/index.blade.php` cuando esté listo; lleva dentro el cambio del polling (ERP-F0-10).
- Desplegar con `npm run build` para que producción deje de servir el CSS duplicado.

## Self-Check: PASSED
- FOUND: tests/Unit/VistasTailwindV4Test.php
- FOUND: tests/Feature/VerificaMaquinaIndexPaginacionTest.php
- FOUND: commits 6de22b1b, 287ff6a5, 7f0e73db, fd5d7ee6
