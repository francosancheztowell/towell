# 19-06b — Catálogos de Planeación (PLAN)

**Rama:** `claude/19-06b-catalogos-planeacion` (base `claude/friendly-hopper-506bg9`). **Receta:** `19-00-RECETA.md` · **Checklist:** `../17-ux/17-02-CHECKLIST.md` · **Estructura:** `../20-arq-sec/20-04-ESTRUCTURA-BACKEND.md`.

## Contexto

Encargo §13 de `.planning/SESIONES-OLA-3.md` (tercera tanda, rama `claude/19-06b-catalogos-planeacion`, base `claude/friendly-hopper-506bg9`). IDs MIG-CAT-01..04 (+ PERF, SEC-07, UX-18, ORM del módulo). Reglas: solo TypeScript, ORM primero, código largo partido con tests primero, estructura 20-04 (controllers delgados, FormRequest, sin `DB::`), AuthZ solo auditar, mismo diseño. Sin PR.

**Medido en la base (43e0f09):**
- JS inline en vistas `catalagos/**` (sin Codificación/L.Mat): Eficiencia 645, Velocidad 604 (casi idénticas), calendarios index 453 + 9 modales 1 214, pesos-rollos 390, matriz-hilos 41, aplicaciones 17, telares 15, matriz-calibres 7 ≈ **3 400 líneas**; `catalog-actions.blade.php` 300 líneas inline + 10 `onclick`.
- `public/js/catalogs/*.js` 2 152 + `public/js/catalog-core.js` 235 (fuera de Vite). `resources/js/catalogos/catalog-base.ts` (piloto atadores) ya existe.
- Backend: `CalendarioController` 1 301 (22 `DB::`, 17 `getMessage`, 10 catch vacíos; `recalcularProgramasPorCalendario` 212 líneas sin tests); Eficiencia/Velocidad 335/365 (duplicados); Aplicaciones 324, MatrizHilos 375, MatrizCalibres 296, Telares 203, PesosRollos 173. `getMessage()` en `response()->json` del módulo: 37.
- Fuera de alcance (19-06a): `catalogoCodificacion`, `codificacion-form`, `modal/_duplicar-importar-codificacion`, `lmat-lista`, `CodificacionController`. Rutas en `routes/modules/planeacion.php` (PT): **no se tocan** (se leen con `route()`).

## Enfoque

Dos partes (p1, p2) en la misma rama, commits por pantalla o par deduplicado.

### p1 — Catálogos simples + Eficiencia/Velocidad + catalog-actions

1. **`catalog-actions.blade.php` sin onclick ni `<script>`**: botones con `data-accion-catalogo="subir-excel|agregar|editar|eliminar|filtrar|restablecer|recalcular|…"` y un `data-catalogo='@json($cfg)'` (ruta, `routeJs`, URL de Excel resuelta con `route('<ruta>.excel.upload')` cuando existe). Runtime en `resources/js/catalogos/catalog-actions.ts` (cargado por el componente con `@vite`, idempotente): despacha al `window.<accion><RouteJs>()` que cada pantalla expone (PUENTE para Codificación de 19-06a mientras siga en JS) o al handler registrado por el bundle TS de la pantalla (`registrarAccionesCatalogo(route, handlers)`). Subida de Excel en `<dialog>` (`x-ui.modal-base`) + `http.upload` + `notify`; `actualizarBotonesAccion/actualizarContadorFiltros/animarRestablecer` como funciones exportadas + puentes `window` mientras Codificación los llame. Verificar y corregir la URL de Excel (hoy `/planeacion/catalogos/{route}-modelos/excel`, que no coincide con las rutas `*.excel.upload`).
2. **Base TS compartida**: extender `resources/js/catalogos/catalog-base.ts` (sin romper atadores) con lo que usan Telares/Aplicaciones/Matrices (filtros, selección, validar/procesar) portando `public/js/catalogs/CatalogBase.js`; cada catálogo en `resources/js/modulos/catalogos-planeacion/<pantalla>/{index,logica}.ts`. Borrar `public/js/catalogs/*.js` y `catalog-core.js` cuando ningún Blade los cargue.
3. **Pantallas**: telares, aplicaciones, matriz-hilos, matriz-calibres, pesos-rollos → TS (datos por `data-*='@json($var)'`, `delegate()`, `http`, `notify`, `x-ui.modal-base`, `accionesTactiles` si hay clic derecho).
4. **Eficiencia ↔ Velocidad dedupe**: una vista `catalagos/comun/estandar.blade.php` + `resources/js/modulos/catalogos-planeacion/estandar/**` parametrizados por `variante` (mapa de columnas/etiquetas/rutas); las vistas originales quedan como `@include(..., ['variante' => ...])`. Backend: una implementación parametrizada (`app/Services/Planeacion/Catalogos/EstandarCatalogoService` + `app/Actions/Planeacion/Catalogos/*` para store/update/destroy/recalcular programas), FormRequests en `app/Http/Requests/Planeacion/Catalogos/`, controllers delgados.
5. **Backend p1**: SEC-07 (`HandlesApiErrors::apiErrorResponse`, catch vacíos → `report($e)`), `$request->validate` → FormRequest, `DB::` → Eloquent/Service, métodos > 100 / complejidad ≥ 10 partidos. Tests de caracterización **antes** por controller (Feature con `UsesSqlsrvSqlite`, patrón `tests/Feature/CatalogosAtadoresTest.php`), con conteo de consultas antes/después donde cambie la consulta. Imports (Velocidades, Eficiencias, Telares, Aplicaciones) con tests del import real (xlsx generado en el test con PhpSpreadsheet).

### p2 — Calendarios

1. **Tests primero** de `CalendarioController`: CRUD de calendario y líneas, `updateMasivo`, `destroyLineasPorRango`, `procesarExcel` (imports `ReqCalendarioTab/LineImport` reales) y, sobre todo, `recalcularProgramasPorCalendario` (cascada por telar, primer registro intacto, snap al calendario, stats, observers restaurados).
2. **Partir**: `app/Services/Planeacion/Calendarios/RecalcularProgramasCalendario` (lectura por telar + cálculo puro), `CrearLineasDesdeTurnos`, Actions de mutación; `ReqProgramaTejido::suppressObservers()/restoreObservers()` en `finally` (HANDOFF PT-05 B1) en vez de `unsetEventDispatcher`. `CalendarioController` queda delgado (meta ≤ 300 líneas) **conservando** `calcularHorasProd`, `calcularFormulasDependientesDeFechas` y `snapInicioAlCalendario` como delegados públicos porque `app/Actions/Planeacion/ProgramaTejido/CambiarCalendario.php` (PT) hace `new CalendarioController` → HANDOFF a PT para usar el servicio.
3. **Front**: `calendarios/index` + 9 modales → `resources/js/modulos/catalogos-planeacion/calendarios/**` (index, tabla, un archivo por modal, `logica.ts` puro); modales como `x-ui.modal-base`; Recalcular/Rango/Excel vía catalog-actions.

### Tests JS
`tests/Js/matriz-calibres-catalog.test.cjs` y `catalog-base.test.mjs` → `tests/Js/catalogos-*.test.ts`; nuevos `catalogos-<pantalla>.test.ts` sobre cada `logica.ts`. Guardián PHP por vista: 0 `<script>` inline y 0 `onclick=` en el HTML renderizado.

## Límites

- No tocar: Codificación/L.Mat, PT (salvo llamar al modelo como hoy), `routes/modules/planeacion.php`, utils/componentes/layouts (salvo catalog-actions), `vite.config.*`, `package.json`, `scripts/ratchet-baseline.json` (es de TS-base: reporto el ratchet antes → después en el SUMMARY y el integrador fija la baja).
- Si algo lo necesita, HANDOFF en `.planning/phases/19-modulos/HANDOFF.md` (sección 19-06b).

## Verificación

- Hook SessionStart; `php artisan test`; `vendor/bin/phpstan analyse --memory-limit=2G`; `npm run typecheck && npm run test:js && npm run build`; `npm run ratchet` (debe bajar: fetch/Swal/onclick/innerHTML/X-CSRF/`<script>`/getMessage/catch vacío); `COMPOSER_ALLOW_SUPERUSER=1 composer quality`; `vendor/bin/pint --test` en todos los PHP cambiados.
- Arnés `19-01-arnes` (+ semilla de catálogos): capturas antes/después 768×1024 y 1280×800 de las 8 pantallas y 0 errores de consola; skill `run` con el flujo principal (alta/edición/borrado, Excel, recalcular calendario).
- Skill `code-review` sobre el diff.
- Antes del último push: `git fetch origin && git merge origin/claude/friendly-hopper-506bg9 && git merge origin/main`, repetir todo, `git push -u origin claude/19-06b-catalogos-planeacion`.

## Entregables
Código + tests + `19-06b-PLAN.md` + `19-06b-SUMMARY.md` (checklist UX-18 por pantalla, líneas inline antes → después, puentes `window`, bugs, números PERF, ratchet) + HANDOFF.
