# Fase 22 — Calidad (track CAL)

**Track:** CAL · **Ola:** 3 (22-01..06, sin chocar con las 19-xx activas) y 4 (22-07) · **IDs:** CAL-01..07 · **Ramas:** `claude/22-xx-<slug>`
**Origen:** análisis del owner (2026-09-29) con PHP Insights, PHPMD, detector de duplicación y cobertura, revisado a mano; verificado por el integrador sobre la rama integradora (`70d3dae7`).

## Principio

Ser estrictos **no** es corregir ~11 000 avisos de golpe (chocaría con las olas en curso). Es:
1. **Nada nuevo empeora:** gates en el CI, solo sobre archivos cambiados o como métrica del ratchet.
2. **La deuda vieja se paga archivo por archivo, con tests primero,** y la paga el dueño del archivo (la 19-xx o PT que lo tiene) salvo que no tenga dueño activo.
3. **Ponytail:** se reusa lo que ya existe (ratchet `scripts/ratchet.mjs`, baseline de phpstan, Pint sobre archivos cambiados) antes de sumar herramientas con baseline propio. Cada baseline nuevo es otro archivo que choca entre sesiones paralelas (ya pasó con `phpstan-baseline.neon`).

## Hallazgos verificados

| Hallazgo | Veredicto del owner | Verificación del integrador |
|---|---|---|
| 12 métodos privados sin usar (~500 líneas) | ✅ | Son **las 12 entradas `is unused` que ya están en `phpstan-baseline.neon`**: 10 en `app/Services/OeeAtadores/OeeAtadoresFileService.php` y 2 en `app/Imports/ReqModelosCodificadosImport.php` (más 5 constantes: `KaizenExport::MESES`, `ReporteOtDiariasExport::COL_NOMBRE`, `BalancearTejido::SMALL_GAP_SECONDS`, `TelBpmController::EST_AUTO/EST_TERM`). phpstan ya bloquea un método nuevo sin usar: borrarlos es **bajar el baseline**. |
| Métodos gigantes en `DividirTejido`, `UpdateTejido`, `DuplicarTejido` | ✅ deuda principal | El análisis se hizo sobre `main`. En la rama, PT 05 ya partió `UpdateTejido` (`actualizar` 697 líneas → `aplicarCambios` 461 + `reglas` / `recalcularDerivados` / `persistir`). Siguen `DividirTejido::dividir` (791) y `DuplicarTejido::duplicar` (638). **PT 05 ya está terminada**: la partición va en una PT 05.1/06. |
| Duplicación 2.15 % (imports de PT ~670 líneas; exports Urdido/Engomado idénticos) | ✅ baja | Sin cambio. |
| 29 catch vacíos | ⚠️ parcial | 29 en `app/` (31 archivos si se cuentan los que solo tienen un comentario). **Deliberados, no se tocan** (nunca deben lanzar; un `report()` se llamaría a sí mismo o haría ruido): `Services/Monitoreo/Monitoreo.php`, `Services/Monitoreo/ErrorRecorder.php`, `Http/Controllers/Monitoreo/TelemetriaController.php`, `Http/Middleware/EnsureModulePermission.php`, `Livewire/Admin/Navegacion.php`. El resto cae en archivos con dueño (Atadores 19-03, `InventarioTelaresService` 19-05, Tejedores 19-04, catálogos de Planeación 19-06, `funciones/`/`helper/` y `Observers` de PT, Configuración 19-09). |
| 30 usos de `@` | ❌ (`@ini_set` en exports pesados) | — |
| Puntajes de PHP Insights 47–60 | ❌ engañosos (~85 % estilo; 10 899 líneas largas) | Pint y phpstan ya cubren lo que importa de ahí. |
| "No conecta a la base" (doctor) | ❌ falso positivo | — |
| Archivos sin test: `CatLMatController`, `ProgramaTejidoOperacionesController`, `ReqModelosCodificadosImport`, `ReportesUrdidoController` | — | Buscar por nombre de clase no basta: los feature tests entran por la ruta. `CatLMatController` → `CatLMatGuardarPorcentajeTest` (en `main`). `ProgramaTejidoOperacionesController` → `Planeacion/ProgramaTejidoDedupTest` y `ProgramaTejidoDividirQueriesTest` (solo en la rama, PT 05). `ReportesUrdidoController` → `UrdEng/ReportesUrdEngTest` (solo en la rama, 19-01). **Hueco real: `ReqModelosCodificadosImport`** (import en cola `ShouldQueue`, 0 tests, 2 métodos muertos y un catch vacío). |

**Dónde medir:** la cobertura y los baselines se generan **en la rama integradora** (o en `main` después de subir la Ola 3). En `main` faltan ~550 tests de la Ola 3 y ~300 archivos cambiados: un baseline hecho ahí falla en cuanto llega la Ola 3.

## Subfases

| Plan | Qué | Dueño / cuándo | Riesgo |
|---|---|---|---|
| **22-01 Gates** (CAL-01) | (a) Ratchet: métricas nuevas `catch vacío` (29) y `duplicación %` con `jscpd` (npm; `phpcpd` está archivado), techo = valor medido en la rama. (b) PHPMD solo con `unusedcode` + `codesize` (complejidad ciclomática, NPath, largo de método), **sobre archivos cambiados** como Pint, sin baseline global; `phpmd.xml` en la raíz. (c) `composer audit`: bloquea solo si cambia `composer.lock`; si no, aviso. (d) `composer quality` = `pint --test` (cambiados) + phpstan + phpmd (cambiados) + `npm run ratchet`. **PHP Insights no es gate** (a lo sumo reporte local). | Sesión CAL, Ola 3, base = rama integradora. Dueña de `composer.json/lock` (solo dev), `.github/workflows/frontend-checks.yml`, `scripts/ratchet.mjs` + baseline, `phpmd.xml`. Ninguna 19-xx activa toca esos archivos. | Nulo |
| **22-02 Código muerto** (CAL-02) | Borrar lo que phpstan ya marca y regenerar su baseline: los 2 métodos de `ReqModelosCodificadosImport` y las 5 constantes. Variables locales sin usar (PHPMD) solo en archivos sin dueño activo. Los 10 métodos de `OeeAtadoresFileService` → **anexo de 19-03**. | CAL (lo libre) + 19-03 | Bajo |
| **22-03 Errores tragados** (CAL-03) | Sin barrido global. Métrica del ratchet (22-01) + lista de excepciones deliberadas (arriba). Cada 19-xx/PT convierte los suyos a `report($e)` junto con su SEC-07 (una línea más en sus prompts). | Repartido | Bajo |
| **22-04 Tests de hotspots** (CAL-04) | Tests de `ReqModelosCodificadosImport` (filas válidas, vacías, totales, cola). Cobertura por archivo medida en la rama (pcov local; el CI sigue con `coverage: none`). Infection **puntual** (no gate) con `--filter` sobre `funciones/DividirTejido` y `DuplicarTejido` antes de partirlas; con sqlite el score engaña en lo que es casi todo SQL. | CAL; Infection lo corre la sesión PT que parta esos archivos | Nulo |
| **22-05 Duplicación** (CAL-05) | Exports idénticos de Urdido y Engomado (~590 líneas) en `app/Exports/**`: 19-01 ya terminó y nadie es dueño de esos exports. Los imports de PT (~670) van con PT. | CAL, Ola 3 | Medio |
| **22-06 Complejidad** (CAL-06) | `ReportesUrdidoController` (libre, 19-01 cerrada). `AtadoresController` y `OeeAtadores*` → anexo de 19-03. `CatLMat` → cuando el owner deje de moverlo en `main` (19-06). | Por dueño | Medio |
| **PT 05.1** | Partir `DividirTejido::dividir` y `DuplicarTejido::duplicar` con el patrón de PT 05 (FormRequest → DTO → Action) y deduplicar los imports de PT; requiere 22-04 (tests + Infection) y usa `ResolverFechaFinalShadowTest` como red. | PT | Alto |
| **22-07 Listón** (CAL-07) | phpstan **por carpeta**: config aparte a nivel 8 para el código nuevo (`app/Actions`, `app/Data`, `app/Livewire`, `app/Services/Monitoreo`, `app/Support`); el legado sigue en 5 con su baseline y sube cuando se toca. Nivel 6 global no: multiplica el baseline con tipos de arreglos del legado. | Ola 4 | Bajo |

## Criterios

- CI rechaza: catch vacío nuevo, duplicación por encima del techo, violación PHPMD (unusedcode/codesize) en archivo cambiado, advisory de seguridad al cambiar `composer.lock`.
- `phpstan-baseline.neon` baja (sin entradas `is unused` fuera de OeeAtadores hasta que 19-03 lo cierre).
- `ReqModelosCodificadosImport` con tests; cobertura por archivo publicada en `22-04-SUMMARY.md` (medida en la rama).
- Ningún gate nuevo con baseline global compartido.
