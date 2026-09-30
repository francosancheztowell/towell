# 22-01 — Calidad, primera tanda (SUMMARY)

**Rama:** `claude/22-cal-gates` (base `claude/friendly-hopper-506bg9`, con `origin/main` mergeado al final) · **IDs:** CAL-01, CAL-02 (parte libre), CAL-04, CAL-05 · **Plan:** `22-01-PLAN.md` (aprobado por el owner) · **Sin PR.**

## Qué quedó

### 22-01 Gates (CAL-01) — sin baselines globales nuevos

| Gate | Dónde | Cómo |
|---|---|---|
| `catch vacío` | `scripts/ratchet.mjs` (métrica nueva) | `catch (…) { }` sin cuerpo ni comentario en `app/**/*.php`. Techo 29 → **28** (el del import pasó a `report()`). Las excepciones deliberadas del CONTEXT cuentan igual. |
| `duplicación %` | `scripts/ratchet.mjs` + `.jscpd.json` + `jscpd` (devDependency) | jscpd 5 (motor Rust con binario por plataforma, incluido Windows) sobre `app`, `resources`, `routes`, `config`, `database`, `public/js`, `scripts`, `tests`; php (blade incluido: jscpd lo tokeniza como php), ts, js; fuera `vendor`, `node_modules`, `public/build`, `tests/fixtures`, `storage`, `*.min.js`. Techo = lo medido en la rama: 7.11 % → **6.93 %**. `node scripts/ratchet.mjs --top 'duplicación %'` lista los pares. |
| PHPMD | `phpmd.xml`, `scripts/calidad.mjs phpmd`, paso nuevo del CI | Solo PHP cambiados. **Falla solo por violaciones nuevas** respecto a la versión base de cada archivo (clave regla + clase + método; en unusedcode también la descripción). Detecta renombres (`git diff -M`) para que mover un archivo no cuente su deuda. Umbrales abajo. |
| `composer audit` | `scripts/calidad.mjs audit`, paso nuevo del CI | Si el cambio toca `composer.lock`, bloquea los advisories que la base no tenía; si no, `::warning::`. Una falla del audit (sin red) no pasa por "cero advisories": bloquea si cambió el lock, avisa si no. |
| `composer quality` | `composer.json` | `pint --test` (cambiados) → `phpstan` → `phpmd` (cambiados) → `npm run ratchet`. En CLAUDE.md §Commands y §CI. |

Umbrales de `phpmd.xml` (los defaults de PHPMD): CyclomaticComplexity ≥ 10, NPath ≥ 200, método > 100 líneas y clase > 1 000 (sin blancos), > 10 parámetros, complejidad de clase > 50; UnusedPrivateField/Method y UnusedLocalVariable (se permiten variables de `foreach`). Fuera a propósito: UnusedFormalParameter, TooMany*/ExcessivePublicCount (Laravel los dispara por diseño).

**Base de "archivos cambiados", una sola lógica** (antes vivía en bash dentro del paso de Pint): PR → su rama base; push → `github.event.before` (rama nueva o force-push → `HEAD~1`); local → el upstream de la rama (o `origin/main`) más el árbol de trabajo y los archivos sin `git add`; `CAMBIADOS_BASE=<ref>` la fuerza. Está en Node y no en bash para que `composer quality` corra igual en Windows/Laragon (el PLAN hablaba de `php-cambiados.sh` + `phpmd-cambiados.mjs`: quedaron juntos en `scripts/calidad.mjs`).

PHPMD corre **un archivo por invocación**: en lote pdepend aborta con las colisiones de traits de `app/Http/Controllers/Engomado/Produccion`. En PHP 8.4 corre limpio (sin deprecations) sobre los 504 archivos de `app/`.

**Prueba de que muerde** (commits locales, no subidos):
- `KaizenExport` + un `private function metodoSinUso()` → `UnusedPrivateMethod … 'metodoSinUso'`, exit 1 (con base local y simulando el CI de una rama nueva).
- Tocar `ReqModelosCodificadosImport` (con 12 violaciones legadas) sin agregar nada → `phpmd ok`.
- El gate se atrapó a sí mismo dos veces en esta rama: mi primer arreglo del import subió el NPath de `collection()` a 200 y luego la complejidad de `D()` a 10; los dos se partieron (`resolverEncabezados()`, `fechaDeTexto()`).
- Un `catch (\Throwable $e) {}` nuevo en `app/` → `SUBIO catch vacío: 29 -> 30`.

### 22-02 Código muerto sin dueño (CAL-02, parte libre)

Fuera `ReqModelosCodificadosImport::convertToInt()` / `isValidTotalValue()`, `KaizenExport::MESES`, `ReporteOtDiariasExport::COL_NOMBRE` y sus 4 entradas del baseline. No se tocaron `OeeAtadoresFileService` (19-03), `BalancearTejido` (PT) ni `TelBpmController` (19-04).

### 22-04 Tests de hotspots (CAL-04)

`tests/Unit/Calidad/ReqModelosCodificadosImportTest.php` (10 tests, primero sobre el código sin tocar): filas válidas con conversiones (fracción `5/3` → 16.7, fecha serial, enteros), upsert por (TamanoClave, OrdenTejido), claves vacías, fórmulas/"N/A"/texto sin dígitos descartados, falla al guardar, progreso en caché, y el camino real `Excel::queueImport` con la cola en `sync` (incluye chunks).

**Los tests destaparon 3 bugs del import, arreglados con su test** (cada test falla sobre el código anterior):
1. **Totales y errores en cola.** Cada chunk en cola corre en su propia copia deserializada del import, y `AfterImport` corre en otra: pisaba `created`/`updated` con 0 y nunca dejaba los errores. En producción el aviso final decía siempre "Nuevos: 0, Actualizados: 0". Ahora la caché acumula todo y `AfterImport` solo marca `done`. `errors` en caché es siempre la lista (≤ 20; el total va en `total_errors`): de paso `CodificacionController::importProgress` deja de recibir un entero en `errors` (su `array_map` lanzaba `TypeError` mientras el import estaba en proceso).
2. **Archivos de más de 1 000 filas.** Con `WithChunkReading` cada chunk tomaba sus 2 primeras filas de datos como encabezado (las perdía y mapeaba el resto por "contiene"/posición). Los encabezados del primer chunk quedan en caché (`RemembersChunkOffset`) y los siguientes los reusan; la fila de Excel del error ahora es la real.
3. **Fechas de texto.** `Carbon::createFromFormat` lanza en vez de devolver `false`: solo `d-m-Y` funcionaba y `dd-mm-aa` daba el año 25. Ahora `dd-mm-aaaa`, `dd-mm-aa` (20xx), con o sin hora, y `aaaa-mm-dd`; lo demás → null (nada de `Carbon::parse` con día primero, que lo leería mes/día). Quita 2 entradas del baseline (`notIdentical.alwaysTrue`, `deadCode.unreachable`).

El catch vacío de `AfterImport` pasó a `report($e)`.

### 22-05 Duplicación (CAL-05)

`BpmProcesoExport` y `ReporteResumenSemanalProcesoExport` (abstractas) llevan el código; `BpmUrdidoExport`, `BpmEngomadoExport`, `ReporteResumenSemanal{Urdido,Engomado}Export` quedan como subclases de 11 líneas que solo dicen su proceso. **Llamadores sin cambios** (`ReportesUrdidoController`, `ReportesEngomadoController`: mismo `use`/`new`).
Red: `tests/Unit/Calidad/ExportsUrdidoEngomadoSnapshotTest.php` + `tests/fixtures/calidad/*.json`, generados y commiteados **antes** del refactor (commit `b32c1ec`) y verdes después sin regenerar: valor, formato numérico, fuente (negrita/tamaño/color), relleno, alineación, borde de cada celda; anchos; combinadas; tabla Excel; panel congelado; título; gráficas (nombre, título, posición, series).

## Números antes → después

| Métrica | Antes | Después |
|---|---:|---:|
| `catch vacío` en `app/` | 29 | **28** |
| Duplicación (jscpd, % de líneas) | 7.11 % | **6.93 %** |
| Par `BpmUrdido/BpmEngomado` duplicado | 285 líneas | 0 |
| Par `ResumenSemanal Urdido/Engomado` duplicado | 214 líneas | 0 |
| Líneas de los 4 exports | 992 | 556 (2 bases + 4 subclases) |
| Entradas de `phpstan-baseline.neon` | 2 240 | **2 234** |
| … de ellas `is unused` | 17 | **13** (todas de OeeAtadores / BalancearTejido / TelBpmController) |
| Tests de `ReqModelosCodificadosImport` | 0 | 10 (cobertura 91 %) |

## Cobertura por archivo (22-04)

Medida **en esta rama** con pcov 1.0.12 (compilado local; el CI sigue con `coverage: none`) sobre la suite completa: 2 285 tests, **46,0 %** de sentencias de `app/` (26 246 / 57 024, 504 archivos).
Riesgo = ncloc × complejidad ciclomática (suma de clases, clover) × fracción sin cubrir.

| # | Archivo | ncloc | Complejidad | Cobertura | Riesgo | Dueño |
|---|---|---:|---:|---:|---:|---|
| 1 | `app/Services/OeeAtadores/OeeAtadoresFileService.php` | 3042 | 449 | 31.4 % | 0.94 M | 19-03 |
| 2 | `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DividirTejido.php` | 1216 | 316 | 38.6 % | 0.24 M | PT |
| 3 | `app/Imports/ReqProgramaTejidoUpdateImport.php` | 1022 | 226 | 2.3 % | 0.23 M | PT |
| 4 | `app/Http/Controllers/mecanicos/OrdenesTrabajoMecaController.php` | 1396 | 204 | 23.7 % | 0.22 M | sin track (lo mueve `main`) |
| 5 | `app/Http/Controllers/Planeacion/CatalogoPlaneacion/CatCalendarios/CalendarioController.php` | 1267 | 165 | 5.5 % | 0.20 M | 19-06 |
| 6 | `app/Http/Controllers/Urdido/ReportesUrdidoController.php` | 1486 | 212 | 42.0 % | 0.18 M | libre → 22-06 |
| 7 | `app/Imports/ReqProgramaTejidoSimpleImport.php` | 883 | 193 | 2.8 % | 0.17 M | PT |
| 8 | `app/Http/Controllers/Planeacion/CatalogoPlaneacion/ModelosCodificados/CodificacionController.php` | 1575 | 205 | 51.5 % | 0.16 M | 19-06 |
| 9 | `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/UpdateTejido.php` | 870 | 299 | 45.1 % | 0.14 M | PT |
| 10 | `app/Http/Controllers/Planeacion/ProgramaTejido/OrdenDeCambio/Felpa/OrdenDeCambioFelpaController.php` | 1684 | 231 | 68.3 % | 0.12 M | PT |
| 11 | `app/Http/Controllers/Tejedores/InventarioTelaresController.php` | 800 | 125 | 0.0 % | 0.10 M | 19-04 |
| 12 | `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DuplicarTejido.php` | 728 | 137 | 0.0 % | 0.10 M | PT |
| 13 | `app/Http/Controllers/Planeacion/CatCodificados/CatCodificacionController.php` | 844 | 144 | 25.0 % | 0.09 M | 19-06 |
| 14 | `app/Traits/ProduccionTrait.php` | 809 | 159 | 31.4 % | 0.09 M | sin track (Urdido/Engomado) |
| 15 | `app/Http/Controllers/Planeacion/ProgramaTejido/helper/TejidoHelpers.php` | 928 | 208 | 56.7 % | 0.08 M | PT |
| 16 | `app/Http/Controllers/Tejido/CortesEficiencia/CortesEficienciaController.php` | 1353 | 161 | 65.0 % | 0.08 M | 19-02 |
| 17 | `app/Http/Controllers/Tejido/Reportes/ReporteInvTelasController.php` | 919 | 172 | 52.7 % | 0.07 M | 19-02 |
| 18 | `app/Http/Controllers/Atadores/ProgramaAtadores/AtadoresController.php` | 1059 | 156 | 57.0 % | 0.07 M | 19-03 |
| 19 | `app/Http/Controllers/Mantenimiento/MantenimientoParosController.php` | 818 | 79 | 17.8 % | 0.05 M | 19-08 |
| 20 | `app/Services/ProgramaUrdEng/InventarioReservasService.php` | 659 | 110 | 27.8 % | 0.05 M | 19-05 |

Lectura: `DuplicarTejido` (0 %) y los dos imports de PT (2–3 %) son lo que PT 05.1 debe cubrir antes de partir; `OeeAtadoresFileService` es el mayor riesgo absoluto (19-03, junto con sus 10 métodos muertos); lo libre para la próxima tanda CAL es `ReportesUrdidoController` (22-06), `ProduccionTrait` y `OrdenesTrabajoMecaController` (si el owner deja de moverlo en `main`).

Para repetir: `php -d extension=pcov.so -d pcov.enabled=1 -d pcov.directory=app vendor/bin/phpunit --coverage-clover clover.xml` (~4,5 min) y cruzar `ncloc`/`coveredstatements` del `<file>` con la `complexity` de sus `<class>`.

## Hallazgos que no se tocaron (ver HANDOFF.md)

- **56 advisories de seguridad** en `composer.lock` (16 paquetes; `phpoffice/phpspreadsheet` con uno crítico; `guzzlehttp/guzzle`, `league/commonmark`, `laravel/framework`, `maatwebsite/excel`, `symfony/*` con altos). Preexistentes: el gate no los bloquea salvo que un cambio agregue uno. `npm audit`: 1 alto (`nanoid`), también preexistente.
- `ReqModelosCodificadosImport::cleanExcelFormula` descarta **todo texto sin dígitos de más de 2 caracteres** (un `Modelo` "TOALLA" queda en null; el comentario de `Pedido` dice admitir "ABIERTO" y no lo admite) y las fechas de texto con `/`. Es de negocio: los tests lo fijan tal cual.
- Con la fila 2 de encabezados **vacía**, `SkipsEmptyRows` la salta y la primera fila de datos se usa como encabezado (la plantilla real trae subencabezados, así que hoy no pasa).

## Evidencia (después de `git merge origin/main`)

- `php artisan test`: `Tests: 2285 passed (23699 assertions)`.
- `vendor/bin/phpstan analyse --memory-limit=2G`: `[OK] No errors`.
- `npm run typecheck && npm run test:js && npm run build`: verdes (295 tests JS).
- `npm run ratchet`: `ratchet ok (12 metricas, ninguna subio)`.
- `composer quality`: verde. `vendor/bin/pint --test` sobre todos los PHP cambiados: pass.
- `code-review` sobre el diff: 2 hallazgos (fecha con hora leída mes/día; `composer audit` que falla contaba como cero) → arreglados en `8671f1d`.
- CI de la rama: ver al pie.

## Tamaño

~1 230 líneas nuevas sin contar movimiento (≈ 600 de docs/PLAN/SUMMARY y ≈ 450 de tests), más el movimiento de los exports (512 líneas pasan a las bases) y −960 de duplicado; locks y fixtures JSON aparte. Por debajo del corte de 1 500: no se partió en -p1/-p2.

## Despliegue

Nada que migrar ni variables nuevas. `composer install` (dev: `phpmd/phpmd`) y `npm ci` (dev: `jscpd`) solo afectan a desarrollo/CI. El import cambia su contrato de caché (`errors` lista, `total_errors`): el único lector es `CodificacionController::importProgress`, que ya esperaba lista.
