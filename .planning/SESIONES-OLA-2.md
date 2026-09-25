# Sesiones de la Ola 2 — prompts de apertura

Abiertas el 2026-09-25 tras integrar la Ola 1 (fases 12, 13, 14, 15-01, PT 01.1 y 02) en `claude/friendly-hopper-506bg9`.

**Decisión del owner (2026-09-25):** abrir la Ola 2 **sin esperar G1** (≥ 7 días de monitoreo en producción) para lo que no depende de telemetría. Quedan para después: **17-01** (auditoría UX por uso real, necesita `SYSMonVista`) y **20-02 / 20-03** (errores JSON y AuthZ en modo auditar; se abren al integrar 20-01, orden del protocolo §4).

Cinco sesiones en paralelo, base `claude/friendly-hopper-506bg9`, tags `towell-refactor-2026`, `ola-2`. Plantilla en `PROTOCOLO-SESIONES.md` §7.

| Sesión | Rama | Contexto / plan |
|---|---|---|
| 15-02 — Librerías y Vite | `claude/15-02-librerias` | `phases/15-fe-fundacion/15-CONTEXT.md` (sección 15-02), `15-01-SUMMARY.md` |
| 16 — Sistema de componentes | `claude/16-componentes` | `phases/16-componentes/16-CONTEXT.md` |
| 20-01 — Movimientos y deduplicación | `claude/20-01-arq` | `phases/20-arq-sec/20-CONTEXT.md` (solo 20-01) |
| 18-01 — Perf infra + fix MON | `claude/18-01-perf-infra` | `phases/18-perf/18-CONTEXT.md` (18-01), `phases/12-mon-cliente/HANDOFF.md` §1, `phases/11-mon-servidor/11-CONTRACT.md` §4 |
| PT 04-perf — cortes 4–7 + HANDOFF B | `claude/pt-04-perf` | `phases/04-ux-grid/04-PERF-MEDIDO.md`, `phases/02-containment-read/HANDOFF.md` §B1–B3 |

**Orden de merge:** `20-01` → `18-01` → `pt-04-perf` → `15-02` → `16`.

## Propiedad de archivos en la Ola 2

Esta tabla **manda sobre** `PROTOCOLO-SESIONES.md` §5 durante la Ola 2. Cambios respecto al protocolo: el `global-loader` pasa de FE a DS; FE toca el bloque select2 de `redbooth.blade.php` (PT); ARQ toca solo líneas `use` en 3 archivos PT; PT toca 2 archivos del navbar (HANDOFF B1/B2).

| Sesión | Dueño de | Prohibido |
|---|---|---|
| 15-02 | `resources/js/utils/**`, `resources/js/types/**`, `tsconfig.json`, `resources/js/bootstrap.js`, `resources/js/app.js`, `resources/js/app-core.js`, `vite.config.js`, `package.json/lock`, `components/layout-scripts.blade.php`, `components/layout-styles.blade.php`, `resources/css/fontawesome-display.css`, `public/js/catalog-core.js`, `resources/js/lmat-lista/**`, `resources/js/catcodificacion/lmat-modal.js`, `resources/js/trazabilidad/{filter-selects,scroll-manager}.ts`, `tests/Js/utils-*`, `tests/Js/combobox-*`; **en vistas solo** las líneas de select2 / toastr / `bg-opacity-*` / `<script>` CDN (incluido el bloque select2/jQuery de `modulos/programa-tejido/modal/redbooth.blade.php`) | `resources/css/app.css`, `layouts/**`, `components/{ui,empty,layout,navbar}/**`, `modulos/catalogos-atadores/**`, `public/js/catalogs/**`, resto de PT, `resources/js/monitoreo/**`, `composer.*`, PHP |
| 16 | `components/ui/**`, `components/empty/**`, `components/tabla*.blade.php`, `components/layout/{page-*,global-loader}.blade.php`, `components/navbar/button-*.blade.php`, `layouts/app.blade.php` (flash, loader, `.fa-spin`), `resources/css/app.css`, `resources/js/catalogos/**`, `resources/js/modulos/catalogos-atadores/**`, `public/js/catalogs/**`, `modulos/catalogos-atadores/**`, `app/Http/Controllers/Atadores/Catalogos/**`, rutas de catálogos de atadores en `routes/modules/atadores.php`, `routes/modules/dev.php` (nuevo) + 1 `require` en `routes/web.php`, `resources/views/dev/**`, `docs/cerebro-towell/Arquitectura/receta-componentes.md`, tests nuevos de componentes. **Línea ancla:** 1 entrada en `input` de `vite.config.js` si el piloto la necesita antes de que 15-02 agregue el glob | `navbar.blade.php`, `navbar/sections/**`, `layout-head`, `layout-scripts`, `layout-styles`, `package.json`, resto de `vite.config.js`, PT (sus modales usan `x-ui.modal-base`: API compatible, sin tocar PT), vistas de otros módulos |
| 20-01 | `app/Http/Controllers/Tejedores/Desarrolladores/**`, `app/Services/Tejedores/**`, `app/Livewire/Desarrolladores/**` (solo `use`), `app/Helpers/FolioHelper.php`, `app/Helpers/TurnoHelper.php`, `app/Models/UrdEngomado/**`, `app/Models/urdengomado/**`; en otros PHP fuera de PT **solo** los métodos duplicados de folio/turno y sus `use`; en PT **solo** las líneas `use` de lo movido (`Utilerias/MoverOrdenesController.php`, `Utilerias/FinalizarOrdenesController.php`, `ProgramaTejido/funciones/EliminarTejido.php`); las 2 líneas `use` de `AppServiceProvider.php`; `phpstan-baseline.neon` (rutas movidas y entradas arregladas); `tests/Unit/*Desarrollador*`, `tests/Unit/Helpers/**`, tests de caracterización nuevos | Resto de PT (folio duplicado en PT → HANDOFF), `bootstrap/**`, `routes/**`, vistas, `resources/js/**`, `config/**`, resto de `AppServiceProvider` |
| 18-01 | `app/Providers/AppServiceProvider.php` (agregar en `boot`/`register`; no las 2 `use` de 20-01), `app/Helpers/permission-helpers.php`, `app/Http/Middleware/SetSqlContextInfo.php` (solo medición), `config/{session,cache,logging,monitoreo,pulse}.php`, `.env.example`, `docs/cerebro-towell/Runbooks/**`, `app/Http/Controllers/Monitoreo/**`, `app/Http/Requests/Monitoreo/**`, `app/Services/Monitoreo/**`, `resources/js/monitoreo/**`, `tests/Feature/Monitoreo/**`, `tests/Unit/Monitoreo/**`, `tests/Js/monitoreo-*`, tests nuevos de perf | `bootstrap/**`, `routes/**`, controllers de negocio, `resources/js/**` fuera de monitoreo, `package.json`, `vite.config.js`, `composer.*`, layouts, PT |
| PT 04-perf | Fila PT del protocolo + `components/navbar/sections/programa-tejido.blade.php` + **1 línea** (`$programaTejidoModulePermission`) de `components/navbar/navbar.blade.php` | `modal/redbooth.blade.php` (15-02 en esta ola), `components/ui/**` (16), `vite.config.js`, `package.json`, `resources/js/utils/**`, todo lo demás |

Archivos compartidos: `scripts/ratchet-baseline.json` (cada sesión solo **baja** sus métricas con `npm run ratchet -- --update`; en conflicto gana el menor) y `phpstan-baseline.neon` (solo quitar entradas o actualizar rutas movidas). `AppServiceProvider.php` lo tocan 20-01 (2 `use`) y 18-01 (`boot`): conflicto trivial, lo resuelve el integrador.

---

## 1. Fase 15-02 — Librerías y Vite

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md y .planning/SESIONES-OLA-2.md (la propiedad de archivos de esta ola manda sobre el protocolo).

Fase 15-02 — Fundación frontend: librerías y Vite. IDs: FE-07..12.
Contexto: .planning/phases/15-fe-fundacion/15-CONTEXT.md (sección 15-02) y 15-01-SUMMARY.md (utils ya integrados: http/notify/format/dom en TS). Línea base de chunks: .planning/phases/10-base/10-BASELINE.md.
Escribe primero .planning/phases/15-fe-fundacion/15-02-PLAN.md (formato de .planning/phases/01-guardrails/01-guardrails-PLAN.md) y luego ejecútalo.
Rama: claude/15-02-librerias (base claude/friendly-hopper-506bg9).

Alcance:
- FE-07 Tom Select (npm tom-select) + wrapper resources/js/utils/combobox.ts (búsqueda, carga remota, templates, múltiple; en Livewire con wire:ignore; su CSS se importa desde el wrapper, NO desde app.css). Migrar todos los usos de select2: resources/js/lmat-lista/index.js (+ sus líneas en resources/views/catalagos/lmat-lista.blade.php), resources/js/catcodificacion/lmat-modal.js, resources/js/trazabilidad/filter-selects.ts, resources/js/trazabilidad/scroll-manager.ts y, por excepción aprobada en esta ola, el bloque select2/jQuery de resources/views/modulos/programa-tejido/modal/redbooth.blade.php (solo ese bloque, sin cambio visual; es el único código que usa window.jQuery).
- FE-08 toastr → notify en los call-sites fuera de PT (modulos/produccion-reenconado-cabezuela, urdido/produccion/_scripts, engomado/partials/modal-calificar-julios y -eng, engomado/modulo-produccion-engomado, resources/js/app-core.js). Adaptador temporal window.toastr → notify hasta la fase 21 (PT migra balancear.blade.php en su sesión).
- FE-09 quitar jquery, select2 y toastr de package.json, su CSS de app.js, el shim de bootstrap.js y sus tipos en types/global.d.ts. Antes, confirma con grep que cada `$(`/`$$(` restante tiene su `$` local (el integrador ya lo revisó: solo redbooth usaba window.jQuery).
- FE-10 vite.config.js sin manualChunks vendor; inputs actuales + glob resources/js/modulos/**/index.ts (así la Ola 3 no toca Vite; si 16-componentes dejó una línea ancla en input, consérvala). Medir el JS inicial por página antes/después (manifest + gzip) contra 10-BASELINE.
- FE-11 html2canvas y pdfjs-dist por npm con import() dinámico, reemplazando los <script> de CDN en modulos/mecanicos/reportes/{estado-maquina,ot-diarias}.blade.php y modulos/cortes-eficiencia/{cortes-eficiencia,visualizar-cortes-eficiencia}.blade.php (solo esas líneas y la llamada que las usa).
- FE-12 regresiones de Tailwind v4: bg-opacity-* → bg-black/50 (o equivalente) en las vistas fuera de PT y fuera de modulos/catalogos-atadores (ese trío es del piloto de 16); familia FA7 en resources/css/fontawesome-display.css; public/js/catalog-core.js deja de pisar window.showToast. El keyframes spin del global-loader y el .fa-spin del layout NO son tuyos (los hace 16-componentes).

ERES DUEÑO DE: resources/js/utils/**, resources/js/types/**, tsconfig.json, resources/js/bootstrap.js, resources/js/app.js, resources/js/app-core.js, vite.config.js, package.json y package-lock.json, resources/views/components/layout-scripts.blade.php, resources/views/components/layout-styles.blade.php, resources/css/fontawesome-display.css, public/js/catalog-core.js, resources/js/lmat-lista/**, resources/js/catcodificacion/lmat-modal.js, resources/js/trazabilidad/filter-selects.ts, resources/js/trazabilidad/scroll-manager.ts, tests/Js/utils-*, tests/Js/combobox-*; y en vistas SOLO las líneas de select2/toastr/bg-opacity-*/CDN listadas arriba.
SOLO LECTURA: todo lo demás.
PROHIBIDO: resources/css/app.css, resources/views/layouts/**, resources/views/components/{ui,empty,layout,navbar}/** (sesión claude/16-componentes), resources/views/modulos/catalogos-atadores/**, public/js/catalogs/**, el resto del track Programa Tejido (sesión claude/pt-04-perf), resources/js/monitoreo/** (sesión claude/18-01-perf-infra), composer.json, PHP. Si necesitas algo ahí: .planning/phases/15-fe-fundacion/HANDOFF.md.

Acuerdos: docs y commits en español ("frontend: <cambio>"); modo ponytail (reusa utils/http, notify, format, dom); NUNCA saltar/desactivar tests; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube (toastr. debe bajar).
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); npm run typecheck && npm run test:js && npm run build; npm run ratchet; php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; skill code-review; con la skill run abre las pantallas tocadas (lmat-lista, trazabilidad, redbooth en Programa Tejido, cortes de eficiencia, un reporte de mecánicos, una vista con toastr migrado) y verifica 0 errores de consola y el combobox funcionando (capturas antes/después).
Entregables: código + tests + 15-02-SUMMARY.md (con KB de JS inicial antes/después) (+ HANDOFF.md si aplica). Push a claude/15-02-librerias. NO abras PR.
```

## 2. Fase 16 — Sistema de componentes

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md y .planning/SESIONES-OLA-2.md (la propiedad de archivos de esta ola manda sobre el protocolo).

Fase 16 — Sistema de componentes. IDs: DS-01..12.
Contexto: .planning/phases/16-componentes/16-CONTEXT.md. Ya integrado: utils TS (15-01-SUMMARY.md: notify con toasts nativos, format, dom). Guía: docs/cerebro-towell/Arquitectura/livewire-cuando-si-cuando-no.md.
Escribe primero .planning/phases/16-componentes/16-01-PLAN.md (si pasa de ~1 500 líneas cambiadas, divide en -p1/-p2 con commits separados) y luego ejecútalo.
Rama: claude/16-componentes (base claude/friendly-hopper-506bg9).

Reglas:
- Evolucionar lo existente (x-ui.modal-base, x-ui.button, x-ui.alert, x-tabla, x-empty.empty-state, x-navbar.button-*) con API compatible. Los usos actuales NO cambian de aspecto: capturas antes/después de cada uno.
- x-ui.modal-base → <dialog> nativo (DS-02): sus 3 usos están en Programa Tejido (modal/repaso, modal/act-calendarios, modal/marbetes) y los abre/cierra el JS de PT. No puedes tocar PT: la API (props, slots, ids, clases que el JS alterna) debe seguir funcionando igual; verifícalo abriendo esos modales en Programa Tejido con la skill run. Si no es posible sin tocar PT, deja la compatibilidad dentro del componente y pide el cambio en HANDOFF.md.
- Tokens en el bloque @theme de resources/css/app.css (texto mínimo 12 px, targets ≥ 44 px, colores semánticos).
- DS-07 loader global único: incluye las regresiones que en 15-CONTEXT eran de FE pero viven en tus archivos: keyframes `spin` de components/layout/global-loader.blade.php que pisa animate-spin y el `.fa-spin` redefinido en layouts/app.blade.php.
- DS-09 x-ui.flash se monta en layouts/app.blade.php (hoy "No tienes acceso" se pierde).
- DS-11 galería /dev/ui-kit solo con app()->isLocal(), en routes/modules/dev.php nuevo (+ una línea require en routes/web.php) y vistas en resources/views/dev/**; receta en docs/cerebro-towell/Arquitectura/receta-componentes.md.
- DS-12 piloto: trío modulos/catalogos-atadores en una vista parametrizada + public/js/catalogs/CatalogBase.js → resources/js/catalogos/catalog-base.ts cargado desde resources/js/modulos/catalogos-atadores/index.ts. La sesión 15-02 agrega en paralelo el glob resources/js/modulos/**/index.ts a vite.config.js; mientras tanto puedes agregar UNA línea ancla en `input` de vite.config.js con esa entrada (nada más de ese archivo). Los otros catálogos de public/js/catalogs siguen funcionando (no los rompas).

ERES DUEÑO DE: resources/views/components/ui/**, resources/views/components/empty/**, resources/views/components/tabla*.blade.php, resources/views/components/layout/page-*.blade.php, resources/views/components/layout/global-loader.blade.php, resources/views/components/navbar/button-*.blade.php, resources/views/layouts/app.blade.php (flash, loader, .fa-spin), resources/css/app.css, resources/js/catalogos/**, resources/js/modulos/catalogos-atadores/**, public/js/catalogs/**, resources/views/modulos/catalogos-atadores/**, app/Http/Controllers/Atadores/Catalogos/**, las rutas de catálogos de atadores en routes/modules/atadores.php, routes/modules/dev.php (nuevo) + 1 require en routes/web.php, resources/views/dev/**, docs/cerebro-towell/Arquitectura/receta-componentes.md, tests nuevos de componentes.
SOLO LECTURA: todo lo demás (incluido resources/js/programa-tejido/filter-engine.ts para DS-10).
PROHIBIDO: components/navbar/navbar.blade.php y components/navbar/sections/** (PT toca ahí en esta ola), components/layout-head, layout-scripts, layout-styles, package.json, el resto de vite.config.js (sesión claude/15-02-librerias), todo el track Programa Tejido (sesión claude/pt-04-perf), vistas de otros módulos (los duplicados BPM, calificar-julios y secuencias los consolida cada 19-xx en la Ola 3). Si necesitas algo ahí: .planning/phases/16-componentes/HANDOFF.md.

Acuerdos: docs y commits en español ("componentes: <cambio>"); modo ponytail (evolucionar, no crear paralelos); NUNCA saltar/desactivar tests; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; npm run typecheck && npm run test:js && npm run build; npm run ratchet; vendor/bin/phpstan analyse --memory-limit=2G; skills code-review y frontend-design (o el agente code-simplifier); capturas con la skill run a 1280×800 y 768×1024 de la galería, del piloto antes/después y de los 3 modales de PT.
Entregables: código + tests + 16-01-SUMMARY.md (+ HANDOFF.md si aplica). Push a claude/16-componentes. NO abras PR.
```

## 3. Fase 20-01 — Movimientos y deduplicación

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md y .planning/SESIONES-OLA-2.md (la propiedad de archivos de esta ola manda sobre el protocolo).

Fase 20-01 — Arquitectura: movimientos y deduplicación. IDs: ARQ-01..04.
Contexto: .planning/phases/20-arq-sec/20-CONTEXT.md (solo la sección 20-01; 20-02 y 20-03 se abren cuando esta rama esté integrada). Auditoría: docs/cerebro-towell/Auditoria/ (BUG-022).
Escribe primero .planning/phases/20-arq-sec/20-01-PLAN.md y luego ejecútalo.
Rama: claude/20-01-arq (base claude/friendly-hopper-506bg9).

Alcance:
- ARQ-01 commit de movimientos puros (git mv + namespace + imports, cero cambio de lógica): app/Http/Controllers/Tejedores/Desarrolladores/Funciones/* → app/Services/Tejedores/Desarrolladores/. Actualiza todos los consumidores (grep "Desarrolladores\\Funciones": Livewire/Desarrolladores/Captura.php, AppServiceProvider (2 use), 3 archivos PT solo en su línea use, 11 tests en tests/Unit) y las rutas de archivo en phpstan-baseline.neon. Commit separado ("arquitectura: mover servicios de Desarrolladores a app/Services") para revisarlo como movimiento.
- ARQ-02 folios: reemplazar reimplementaciones por app/Helpers/FolioHelper.php (obtenerSiguienteFolio() al confirmar, obtenerFolioSugerido() para UI). Candidatos (confirma con grep de SSYSFoliosSecuencias): obtenerFolioUrdEng() duplicado en app/Services/ProgramaUrdEng/CrearOrdenesService.php y app/Http/Controllers/ProgramaUrdEng/ReservarProgramar/CrearOrdenKarlMayerController.php, Tejido/InventarioTrama/NuevoRequerimiento{Controller,Service}, Tejido/CortesEficiencia/CortesEficienciaController, Tejedores/BPMTejedores/TelBpmController, TurnoHelper. Semántica idéntica (mismo bloqueo/transacción, mismo formato y prefijo de folio): escribe tests de caracterización ANTES de cambiar cada uno.
- ARQ-03 getTurnoInfo() duplicado (CortesEficienciaController, NuevoRequerimientoController) → app/Helpers/TurnoHelper.php, con tests de los límites de turno (6:30, 14:30, 22:30, America/Mexico_City).
- ARQ-04 UrdEngNucleos en app/Models/UrdEngomado/ y app/Models/urdengomado/ (BUG-022): deja uno y actualiza consumidores. Ojo: Windows no distingue mayúsculas en rutas y Linux sí; verifica el autoload de composer y que no queden dos clases con el mismo FQCN.

ERES DUEÑO DE: app/Http/Controllers/Tejedores/Desarrolladores/**, app/Services/Tejedores/**, app/Livewire/Desarrolladores/** (solo use), app/Helpers/FolioHelper.php, app/Helpers/TurnoHelper.php, app/Models/UrdEngomado/**, app/Models/urdengomado/**; en otros PHP fuera de PT SOLO los métodos duplicados de folio/turno y sus use; en PT SOLO las líneas use de lo movido (Planeacion/Utilerias/MoverOrdenesController.php, Planeacion/Utilerias/FinalizarOrdenesController.php, Planeacion/ProgramaTejido/funciones/EliminarTejido.php); las 2 líneas use de app/Providers/AppServiceProvider.php; phpstan-baseline.neon (rutas movidas y entradas que arregles); tests/Unit/*Desarrollador*, tests/Unit/Helpers/** y tests de caracterización nuevos.
SOLO LECTURA: todo lo demás.
PROHIBIDO: el resto de Programa Tejido (si encuentras un folio duplicado en PT → HANDOFF para PT), bootstrap/**, routes/**, vistas, resources/js/**, config/**, el resto de AppServiceProvider (sesión claude/18-01-perf-infra). Si necesitas algo ahí: .planning/phases/20-arq-sec/HANDOFF.md.

Acuerdos: docs y commits en español ("arquitectura: <cambio>"); modo ponytail (FolioHelper y TurnoHelper ya existen: apuntar duplicados al original); NUNCA saltar/desactivar tests; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube; SQL compatible con SQL Server 2008 R2.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; composer dump-autoload sin advertencias de clases ambiguas; npm run build && npm run ratchet; vendor/bin/pint --test en archivos tocados; skills code-review y security-review (folios = concurrencia).
Entregables: código + tests + 20-01-SUMMARY.md (+ HANDOFF.md si aplica). Push a claude/20-01-arq. NO abras PR.
```

## 4. Fase 18-01 — Perf infra + fix de monitoreo

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md y .planning/SESIONES-OLA-2.md (la propiedad de archivos de esta ola manda sobre el protocolo).

Fase 18-01 — Performance backend: infraestructura. IDs: PERF-01..07. Más un fix de monitoreo (HANDOFF 12 §1).
Contexto: .planning/phases/18-perf/18-CONTEXT.md (sección 18-01); línea base .planning/phases/10-base/10-BASELINE.md; monitoreo ya integrado (Server-Timing, Pulse en /admin/pulse, SYSMonVista, 11/13/14-SUMMARY.md): úsalo para medir.
Decisiones ya tomadas por el owner: producción usa `file` para cache y sesión (PERF-01: documentar y alinear defaults y .env.example, sin tabla cache); no hay Redis ni proxy; producción es SQL Server 2008 R2.
Escribe primero .planning/phases/18-perf/18-01-PLAN.md y luego ejecútalo.
Rama: claude/18-01-perf-infra (base claude/friendly-hopper-506bg9).

Alcance:
- PERF-01 decisión documentada. PERF-02 checklist de OPcache y realpath_cache en docs/cerebro-towell/Runbooks/deploy.md (créalo si no existe), incluyendo el despliegue acumulado de las fases 10–15 y PT (lista en .planning/STATE.md, "Pending Todos"). PERF-03 optimize + event:cache en el deploy y optimize:clear en rollback (el script "update" de package.json es de 15-02 en esta ola: si hay que cambiarlo, HANDOFF; documéntalo en el runbook).
- PERF-04 memoizar moduleNameForRoute (app/Helpers/permission-helpers.php): por request + cache con el prefijo modulos_v3 y el TTL de ModuloService; test que cuente queries antes/después.
- PERF-05 Model::preventLazyLoading fuera de producción con handleLazyLoadingViolationUsing → log (sin throw). PERF-06 DB::whenQueryingForLongerThan(500) → log (Pulse ya tiene SlowQueries: no dupliques).
- PERF-07 medir SetSqlContextInfo con Server-Timing sin cambiar su semántica (alimenta los triggers de SYSAuditoria); propuesta con números en el SUMMARY.
- Fix MON (HANDOFF .planning/phases/12-mon-cliente/HANDOFF.md §1): POST /telemetria/error acepta `ruta` y `version` (contrato 11-CONTRACT.md §4 ya actualizado). SYSMonError.Ruta de errores de cliente = ruta de la página (si falta: la Ruta de la SYSMonVista del uuid `vista`; si no, 'desconocida'), nunca 'telemetria.error'. Cliente: `ruta: meta('towell-ruta')` en reportar() de resources/js/monitoreo/cliente.ts, cuidando la navegación suave de Livewire (el meta debe estar actualizado tras livewire:navigated). Decide y documenta si Ruta entra en la huella de errores de cliente. Tests PHP y JS; el cliente sigue ≤ 5 KB gzip.

ERES DUEÑO DE: app/Providers/AppServiceProvider.php (solo agregar en boot/register; las 2 líneas use de servicios de Desarrolladores las cambia claude/20-01-arq), app/Helpers/permission-helpers.php, app/Http/Middleware/SetSqlContextInfo.php (solo medición), config/session.php, config/cache.php, config/logging.php, config/monitoreo.php, config/pulse.php, .env.example, docs/cerebro-towell/Runbooks/**, app/Http/Controllers/Monitoreo/**, app/Http/Requests/Monitoreo/**, app/Services/Monitoreo/**, resources/js/monitoreo/**, tests/Feature/Monitoreo/**, tests/Unit/Monitoreo/**, tests/Js/monitoreo-*, tests nuevos de perf.
SOLO LECTURA: todo lo demás.
PROHIBIDO: bootstrap/**, routes/**, controllers de negocio, resources/js/** fuera de monitoreo (sesión claude/15-02-librerias), package.json, vite.config.js, composer.*, layouts (sesión claude/16-componentes), config/database.php, Programa Tejido. Si necesitas algo ahí: .planning/phases/18-perf/HANDOFF.md.

Acuerdos: docs y commits en español ("rendimiento: <cambio>", "monitoreo: <cambio>"); modo ponytail; ninguna optimización sin número antes/después; NUNCA saltar/desactivar tests; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube; lo que requiera datos de producción va como runbook para el owner.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; skill code-review; con la skill run: throw en consola en una página → SYSMonError.Ruta = ruta de esa página (en sqlite local).
Entregables: código + tests + 18-01-SUMMARY.md (+ HANDOFF.md si aplica). Push a claude/18-01-perf-infra. NO abras PR.
```

## 5. PT 04-perf — cortes 4–7 + HANDOFF B

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server). Refactor integral 2026, track Programa Tejido (PT). Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-2.md, .planning/PROJECT.md (decisión D-E: mismo diseño visual) y los SUMMARY de PT 01, 01.1 y 02.

Fase PT 04-perf — cortes 4–7 de .planning/phases/04-ux-grid/04-PERF-MEDIDO.md, más los pedidos B1–B3 de .planning/phases/02-containment-read/HANDOFF.md.
Escribe primero .planning/phases/04-ux-grid/04-perf-PLAN.md y luego ejecútalo.
Rama: claude/pt-04-perf (base claude/friendly-hopper-506bg9).

Alcance:
- Medir ANTES con el método de 04-PERF-MEDIDO.md ("Cómo repetir la medición") sobre un dataset sintético en sqlite con la forma real (85 filas, 92 columnas, un usuario con 59 columnas ocultas) y dejar el mismo comando como runbook para que el owner mida en Laragon con datos reales. Medir DESPUÉS igual.
- Corte 4: Promise.all en duplicar-dividir (los 2 await en serie antes del Swal.fire).
- Corte 5: sacar el bloque JS inline grande de la grilla a módulos importados desde resources/js/programa-tejido/index.js (ya es entrada de Vite: NO edites vite.config.js). Solo las constantes (PT_BASE_PATH…) quedan en la vista, como <script type="application/json"> o data-*. Cero cambio de comportamiento; baja el ratchet de <script> inline.
- Corte 6: click delegado en tbody (borrar los 3 assignClickEvents con setTimeout) y corregir el cierre sobre `i` que arrastra selectedRowIndex, con test.
- Corte 7 (no emitir columnas ocultas): solo si 4–6 medidos no alcanzan, según el criterio del documento; si no se hace, justifícalo con números en el SUMMARY.
- HANDOFF B1: envolver "Descargar programa" en components/navbar/sections/programa-tejido.blade.php con ProgramaTejidoSurface::actual()->soporta('descarga') y quitar el ocultamiento por JS de index.js. B2: en components/navbar/navbar.blade.php, $programaTejidoModulePermission = 'Muestras' cuando $isMuestras (solo esa línea). B3: $moduloPT del menú contextual → $superficie->moduloPermiso(). Tests de que en Muestras los botones siguen los permisos del módulo Muestras.
- toastr → notify en modulos/programa-tejido/balancear.blade.php (15-02 deja un adaptador window.toastr temporal; PT no debe depender de él).
- Mismo diseño visual: capturas antes/después de Programa y Muestras con la skill run.

ERES DUEÑO DE: la fila PT de .planning/PROTOCOLO-SESIONES.md (resources/js/programa-tejido/**, app/Http/Controllers/Planeacion/{ProgramaTejido,Utilerias}/**, resources/views/modulos/programa-tejido/**, app/Services/Planeacion/ProgramaTejido/**, app/Actions/Planeacion/**, app/Observers/ReqProgramaTejidoObserver.php, app/Http/Middleware/ProgramaTejidoContext.php, routes/modules/planeacion.php, config/planeacion.php, public/js/programa-tejido-*.js, public/css/programa-tejido/**, tests/Feature/Planeacion/**, tests/Unit/Planeacion/**, tests/fixtures/planeacion/**, .planning/phases/0*/**), más resources/views/components/navbar/sections/programa-tejido.blade.php y UNA línea de resources/views/components/navbar/navbar.blade.php.
SOLO LECTURA: todo lo demás.
PROHIBIDO: resources/views/modulos/programa-tejido/modal/redbooth.blade.php (en esta ola lo toca claude/15-02-librerias para quitar select2/jQuery), resources/views/components/ui/** (claude/16-componentes: x-ui.modal-base pasa a <dialog> con API compatible; si tus modales repaso/act-calendarios/marbetes necesitan algo, HANDOFF), las líneas use de servicios de Desarrolladores en Utilerias/{Mover,Finalizar}OrdenesController.php y funciones/EliminarTejido.php (las cambia claude/20-01-arq), vite.config.js, package.json, resources/js/utils/**, bootstrap/**, config/database.php, monitoreo.

Acuerdos: docs y commits en español ("programa tejido: <cambio>"); modo ponytail; ninguna optimización sin número antes/después; NUNCA saltar/desactivar tests; no ejecutar migraciones; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube; aquí no hay SQL Server: lo que requiera datos reales va como runbook para el owner.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; php artisan planeacion:programa-tejido-health en sqlite; skill code-review.
Entregables: código + tests + 04-perf-SUMMARY.md (tabla antes/después como la de 04-PERF-MEDIDO.md) (+ HANDOFF.md si aplica). Push a claude/pt-04-perf. NO abras PR.
```

---

## Segunda tanda (abierta 2026-09-25, tras integrar la primera en `claude/friendly-hopper-506bg9`)

| Sesión | Rama | Contexto |
|---|---|---|
| 20-02 → 20-03 — Errores JSON y AuthZ en modo auditar | `claude/20-02-03-errores-authz` | `phases/20-arq-sec/20-CONTEXT.md` (20-02 y 20-03), `20-01-SUMMARY.md` |

Única sesión activa: es dueña de `bootstrap/app.php` (archivo caliente de ARQ en la Ola 2) y de las rutas de módulos fuera de Planeación.

## 6. Fases 20-02 → 20-03 — Errores JSON y AuthZ en modo auditar

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS, SQL Server 2008 R2 en producción). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-2.md (sección "Segunda tanda") y CLAUDE.md.

Fases 20-02 y 20-03 — Arquitectura y seguridad. IDs: SEC-04, SEC-05 (SEC-06/SEC-07/ARQ-05 NO: van en cada 19-xx).
Contexto: .planning/phases/20-arq-sec/20-CONTEXT.md (secciones 20-02 y 20-03) y 20-01-SUMMARY.md. Monitoreo ya integrado: ErrorRecorder (fase 11) y App\Services\Monitoreo\AccesoService::registrar('authz_denegaria', …) — consúmelos, no los cambies (si hace falta, HANDOFF).
Escribe primero .planning/phases/20-arq-sec/20-02-PLAN.md y 20-03-PLAN.md, y ejecútalos en ese orden con commits separados.
Rama: claude/20-02-03-errores-authz (base claude/friendly-hopper-506bg9).

20-02 (SEC-04): render central en bootstrap/app.php para respuestas JSON 5xx (requests que esperan JSON, incluidas las de window.http y Livewire): mensaje genérico en español + trace_id = Id del evento de SYSMonErrorEvento que expone ErrorRecorder (si no hay evento, un id de referencia igual al de la página 500). Nunca exponer getMessage(), clase, archivo ni SQL con APP_DEBUG=false; con APP_DEBUG=true se conserva el detalle actual. Reusar app/Support/Http/Concerns/HandlesApiErrors.php. No cambies los catch de los controllers (eso es SEC-07 en cada 19-xx). Tests: 500 en JSON sin detalle y con trace_id; 422/403/404/419 intactos; HTML 500 intacto.

20-03 (SEC-05): EnsureModulePermission (module.permission:<accion>,<idrol>) acepta un tercer parámetro `auditar` (module.permission:crear,123,auditar): si el usuario NO tendría permiso, registra SYSMonAcceso tipo authz_denegaria (ruta, acción, idrol, usuario) y DEJA PASAR; con permiso no registra nada. Sin el parámetro, el comportamiento actual no cambia. Aplicar el modo auditar a las rutas de escritura (POST/PUT/PATCH/DELETE) SIN module.permission de routes/modules/*.php excepto planeacion.php (Planeación ya la cerró PT 01.1). Mapa acción ↔ idrol por módulo documentado en .planning/phases/20-arq-sec/20-03-MAPA-AUTHZ.md, con la fuente de cada idrol (SYSRoles / vistas que ya usan userCan). Excepción aprobada por el owner: alta de paros de mantenimiento queda abierta a cualquier usuario autenticado (sin auditar). Test que falle si aparece una ruta de escritura nueva fuera de Planeación sin module.permission (ni enforce ni auditar), al estilo de tests/Feature/Planeacion/PlaneacionEscrituraAutorizacionTest.php. Deduplicar el registro: una fila por usuario+ruta+acción por hora como máximo (cache), para no llenar SYSMonAcceso.

ERES DUEÑO DE: bootstrap/app.php, app/Http/Middleware/EnsureModulePermission.php, app/Support/Http/Concerns/HandlesApiErrors.php, routes/modules/*.php EXCEPTO routes/modules/planeacion.php (solo agregar middleware; no mover ni renombrar rutas), tests nuevos en tests/Feature/Seguridad/**, .planning/phases/20-arq-sec/**.
SOLO LECTURA: app/Services/Monitoreo/** (consumir ErrorRecorder y AccesoService), app/Helpers/permission-helpers.php, controllers.
PROHIBIDO: routes/modules/planeacion.php y todo Programa Tejido, controllers de negocio (los catch con getMessage() son SEC-07 de cada 19-xx), resources/js/**, vistas, composer.*, package.json, config/database.php. Si necesitas algo ahí: .planning/phases/20-arq-sec/HANDOFF.md.

Acuerdos: docs y commits en español ("seguridad: <cambio>"); modo ponytail; NUNCA saltar/desactivar tests; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube; SQL compatible con 2008 R2 (hay test que lo vigila en database/sql); el snapshot de rutas de PT no debe cambiar.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run build && npm run ratchet; vendor/bin/pint --test en archivos tocados; skills security-review y code-review (obligatorios).
Entregables: código + tests + 20-02-SUMMARY.md, 20-03-SUMMARY.md y 20-03-MAPA-AUTHZ.md (+ HANDOFF.md si aplica). Push a claude/20-02-03-errores-authz. NO abras PR.
```

## 7. Telegram sin bloquear + avisos de modelo (aprobada por el owner 2026-09-25)

| Sesión | Rama | Contexto |
|---|---|---|
| Telegram sin bloquear | `claude/telegram-no-bloquear` | Este bloque; patrón de `app/Services/Mantenimiento/ParoTelegramNotifier.php` |

Corre en paralelo con 20-02 → 20-03: no comparten archivos (esa sesión toca rutas, `bootstrap/app.php` y middleware; esta, 6 archivos de envío a Telegram + `boot` de `AppServiceProvider`).

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción: Windows/Laragon, SQL Server 2008 R2, sin Redis, cache/sesión file). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-2.md (bloque 7) y CLAUDE.md.

Tarea — "Telegram sin bloquear + avisos de modelo". Sin ID previo: regístralo como PERF-13 (Telegram) y PERF-14 (avisos de modelo) en tu SUMMARY.
Escribe primero .planning/phases/18-perf/18-03-PLAN.md y luego ejecútalo.
Rama: claude/telegram-no-bloquear (base claude/friendly-hopper-506bg9).

Problema medido en código: 6 acciones mandan Telegram DENTRO de la petición, secuencialmente a cada chat_id, con timeouts de 20–30 s; el resultado solo va al log. Peor caso: N destinatarios × 30 s con la tablet en el loader.
- AtadoresController::enviarNotificacionTelegramAtadoTerminado (al terminar atado, 20 s)
- NotificarMontadoJulioController::enviarNotificacionTelegram (20 s)
- RequerimientoStatusService::enviarTelegram (sin timeout propio = 30 s por defecto)
- CortesEficienciaController: envío al finalizar corte (línea ~621, PDF) y los botones notificarTelegram / notificarTelegramImagen (30 s)
- MarcasController::notificarTelegram → enviarReporteMarcasPdfTelegram (30 s)

Alcance:
1. En todos: connectTimeout 3 s, timeout 8 s para texto y 15 s para PDF/imagen; envío EN PARALELO a todos los chat_id con Http::pool; mismo contenido, mismos destinatarios (SYSMensaje::getChatIdsPorModulo), mismos logs. Ponytail: reusa el patrón de app/Services/Mantenimiento/ParoTelegramNotifier.php; si varias rutas repiten "mandar texto/documento a N chats", extrae UN helper pequeño y úsalo en las 6 (no un framework).
2. Donde Telegram es efecto secundario (terminar atado, montado de julio, requerimiento de trama, finalizar corte): enviar después de responder con defer(), como ya hacen Mantenimiento paros y Crudo. OJO: producción es Windows (no hay PHP-FPM ni fastcgi_finish_request); mide si defer() libera la respuesta antes en Apache/mod_php y php-cgi. Si no la libera, usa la cola `database` (migración de jobs ya existe) con un job y documenta en docs/cerebro-towell/Runbooks/deploy.md el worker cada minuto desde el Programador de tareas (`php artisan queue:work --stop-when-empty --max-time=50`) y el SQL de la tabla jobs si falta (compatible 2008 R2, idempotente, en database/sql/). Deja la decisión con evidencia en el SUMMARY y un paso de verificación para el owner (phpinfo → "Server API").
3. Botones explícitos "Enviar a Telegram" (Cortes PDF/imagen, Marcas): siguen síncronos para poder confirmar, con el punto 1, y ahora responden al usuario el resultado real ("enviado a N de M" / "no se pudo enviar") en el JSON que ya devuelven, sin romper el contrato del front (mismos campos + mensaje).
4. Avisos de modelo en app/Providers/AppServiceProvider.php (solo en boot, fuera de producción, solo log, sin throw), como el de lazy loading de 18-01: Model::preventSilentlyDiscardingAttributes + handleDiscardedAttributeViolationUsing y Model::preventAccessingMissingAttributes + handleMissingAttributeViolationUsing → Log::warning una vez por modelo+atributo por request. Verifica que la suite completa siga verde y reporta cuántos avisos salen al correrla.
5. Tests con Http::fake: paralelo (todos los chats reciben), timeouts, que la respuesta de las acciones diferidas no espera al envío, que los botones explícitos reportan éxito/fallo; y medición antes/después (tiempo de respuesta con Telegram lento simulado).

ERES DUEÑO DE: los 6 métodos/archivos listados (solo el código de envío a Telegram y la respuesta de los botones explícitos), app/Services/Mantenimiento/ParoTelegramNotifier.php (solo si extraes el helper común), un helper nuevo pequeño si hace falta (app/Services/Telegram/**), app/Jobs/** nuevos si usas cola, app/Providers/AppServiceProvider.php (solo boot, avisos de modelo), database/sql/ (solo tabla jobs si hace falta), docs/cerebro-towell/Runbooks/deploy.md (sección del worker), tests nuevos en tests/Feature/Telegram/** y tests/Unit/Telegram/**, .planning/phases/18-perf/18-03-*.
SOLO LECTURA: todo lo demás.
PROHIBIDO: routes/**, bootstrap/**, app/Http/Middleware/** (sesión claude/20-02-03-errores-authz en paralelo), vistas y resources/js/** (salvo que el mensaje de resultado de los botones necesite 1 línea en su vista: entonces HANDOFF), Programa Tejido, composer.*, package.json, config/database.php.

Acuerdos: docs y commits en español ("rendimiento: <cambio>"); modo ponytail; ninguna optimización sin número antes/después; NUNCA saltar/desactivar tests; no editar .planning/ROADMAP.md, STATE.md, REQUIREMENTS.md ni PROJECT.md; el ratchet no sube; SQL compatible con 2008 R2 (hay test que lo vigila).
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run build && npm run ratchet; vendor/bin/pint --test en archivos tocados; skill code-review.
Entregables: código + tests + 18-03-SUMMARY.md (+ HANDOFF.md si aplica). Push a claude/telegram-no-bloquear. NO abras PR.
```
