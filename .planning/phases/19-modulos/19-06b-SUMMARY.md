# 19-06b — Catálogos de Planeación (SUMMARY)

**Rama:** `claude/19-06b-catalogos-planeacion` (base `claude/friendly-hopper-506bg9`). **IDs:** MIG-CAT-01..04 + PERF, SEC-07, UX-18 y ORM del módulo. **Plan:** `19-06b-PLAN.md` (p1 catálogos simples, p2 calendarios; misma rama).
**Fuera:** Codificación / CatCodificados / L.Mat (19-06a), Programa Tejido, rutas (`routes/modules/planeacion.php` es de PT: no se tocó).

## 1. Qué se hizo

| Pantalla | JS inline antes → después | Ahora |
|---|---|---|
| `catalagoTelares` | 15 + `public/js/catalogs/TelaresCatalog.js` (371) → 0 | `modulos/catalogos-planeacion/telares` |
| `aplicaciones` | 17 + `AplicacionesCatalog.js` (298) → 0 | `…/aplicaciones` |
| `matriz-hilos` | 41 + `MatrizHilosCatalog.js` (335) → 0 | `…/matriz-hilos` |
| `matriz-calibres` | 7 + `MatrizCalibresCatalog.js` (362) + 3 `onclick` → 0 | `…/matriz-calibres` |
| `pesos-rollos` | 390 → 0 | `…/pesos-rollos` |
| `catalagoEficiencia` + `catalagoVelocidad` | 645 + 604 (casi iguales) → 0 | **una** vista `catalagos/comun/estandar` + `…/estandar` |
| `calendarios/index` + 9 modales | 453 + 1 214 + `public/js/catalog-core.js` (235) → 0 | `…/calendarios/{index,modal-calendario,logica}.ts`, 5 modales `x-ui.modal-base` |
| `components/buttons/catalog-actions` | 300 + 10 `onclick` → 0 | `resources/js/catalogos/catalog-actions.ts` (entrada `…/acciones/index.ts`) |

- Base común: `resources/js/catalogos/catalog-base.ts` (piloto de atadores) ganó ganchos (`valoresParaEditar`, `despuesDeGuardar`, `alAbrirFormulario`, `botonesExternos`, plantilla opcional) sin romper atadores; `modulos/catalogos-planeacion/comun/{catalogo,logica}.ts` agrega formulario/borrado/filtros comunes; modales en `catalagos/comun/modales.blade.php` con la config de `CatalogosPlaneacionVista` (PHP).
- `catalog-actions`: botones con `data-accion-catalogo`; la pantalla registra sus handlers (`registrarAccionesCatalogo`) y, si no, se llama al `window.<accion><Ruta>()` de antes. **PUENTE 19-06b** para Codificación (sigue en JS, 19-06a): `window.agregarCodificacion`, `editarCodificacion`, `eliminarCodificacion`, `filtrarCodificacion`, `limpiarFiltrosCodificacion`. Editar/Eliminar se ven deshabilitados por `disabled:` (sin juego de clases en JS). Excel genérico en `<dialog>` + `http.upload`.
- Borrados: `public/js/catalogs/*.js` (5 + README), `public/js/catalog-core.js`, 6 modales de calendario.
- Tests JS migrados a TS: `catalog-base.test.mjs` → `catalogos-base.test.ts`, `matriz-calibres-catalog.test.cjs` → dentro de `catalogos-planeacion.test.ts`; nuevos `catalogos-acciones.test.ts`, `catalogos-calendarios.test.ts` (37 casos del área).

### Backend

| Controller | Líneas antes → después |
|---|---|
| `CalendarioController` | 1 301 → 274 |
| `CatalagoEficienciaController` / `CatalagoVelocidadController` | 335 / 365 → 22 / 22 (+ `EstandarCatalogoController` 89) |
| `AplicacionesController` | 324 → 93 |
| `MatrizHilosController` | 375 → 106 |
| `MatrizCalibresController` | 296 → 133 |
| `CatalagoTelarController` | 203 → 109 |
| `PesosRollosController` | 173 → 92 |

- **Estructura (20-04):** 0 `DB::` y 0 `$request->validate([...])` en los controllers del módulo; FormRequests en `app/Http/Requests/Planeacion/Catalogos/` (base `CatalogoRequest` conserva `{success:false, message, errors}`; las consultas de Matriz de Calibres que usa L.Mat conservan el 422 estándar). Servicios en `app/Services/Planeacion/Catalogos/` (`EstandarCatalogoService`, `AplicacionesService`, `MatrizHilosService`, `ImportarExcelCatalogo`, `ResultadoCatalogo`) y `app/Services/Planeacion/Calendarios/` (`CalendarioService`, `TurnosCalendario`, `RecalcularProgramasCalendario`, `FormulasCalendario`).
- **Enums:** `App\Enums\Planeacion\VarianteEstandar` (Eficiencia/Velocidad: modelo, columna, columna del programa, mensajes) y `Densidad` (Normal/Alta + `deCalibreTrama()`). **Sin cast** en los modelos: los imports aceptan texto libre de 10 caracteres y no hay `SELECT DISTINCT Densidad` de producción; un valor fuera del enum rompería la lectura (runbook abajo).
- **Código largo:** `recalcularProgramasPorCalendario` (212 líneas, sin tests) partido en `RecalcularProgramasCalendario` con tests de caracterización escritos antes (`CalendariosTest`, commit `c718b42`). PHPMD sin violaciones nuevas en los 61 PHP cambiados.
- **Observers:** el recálculo usa `ReqProgramaTejido::suppressObservers()/restoreObservers()` en `finally` (HANDOFF PT-05 B1) en vez de `unsetEventDispatcher()`.
- **SQL:** el filtro por rango del recálculo ya no usa `DATEADD`/`ISNULL` crudos: se evalúa en PHP sobre 5 columnas (portable, probado en sqlite). Único SQL crudo nuevo: `COALESCE(CalibreTrama, CalibreTrama2)` en `EstandarCatalogoService::programasQueUsan` (documentado, parámetro enlazado, válido en 2008 R2).
- **PUENTE PHP:** `CalendarioController::{snapInicioAlCalendario, calcularHorasProd, calcularFormulasDependientesDeFechas}` siguen públicos porque `CambiarCalendario` (PT) hace `new CalendarioController` (HANDOFF C1).

## 2. PERF (request completa en sqlite, `tests/Feature/CatalogosPlaneacion/ConsultasCatalogosTest.php`)

| Operación | Antes (43e0f09) | Después |
|---|---:|---:|
| Editar Eficiencia (10 programas del telar, 5 aplican) | 21 | 11 |
| Editar Velocidad (mismo escenario) | 21 | 16 |
| Cambiar Factor de Aplicación (50 líneas) | 57 | 8 |
| Cambiar N1 de un hilo en uso (10 programas, 50 líneas) | 67 | 8 |
| Recalcular calendario (20 programas, 2 telares) | 46 | 28 |

## 3. Bugs encontrados y corregidos

- **BUG-19-06b-1:** editar una Velocidad reescribía `NoTelarId`, `FibraRizo` y `FibraTrama` de los programas que la usaban (un programa con rizo H y trama PAP quedaba con trama H). Ahora solo cambia `VelocidadSTD` (como Eficiencia) y, si cambia la llave, se aplica también a los programas de la llave nueva. Test `test_editar_velocidad_no_reescribe_fibras_ni_telar_del_programa`.
- **BUG-19-06b-2:** el import de Aplicaciones exigía Salón y Telar (estructura vieja, ya no son columnas) y no leía Factor: el Excel vigente (Clave, Nombre, Factor) no cargaba nada. Test con xlsx real.
- El botón de Excel de `catalog-actions` posteaba a `/planeacion/catalogos/{ruta}-modelos/excel` (inexistente) en Pesos por Rollos y Matriz de Hilos: sin ruta de carga ya no se muestra.
- Validaciones que salían como 500 con `getMessage()` (Telares, Eficiencia, Matriz de Hilos) ahora son 422; registro inexistente en Pesos por Rollos → 404 (antes 500).
- El 404 de "recalcular" listaba todos los calendarios existentes en el mensaje.
- `<button>` vacío en el navbar de Calendarios.

## 4. SEC-07 y ratchet

`getMessage()` fuera de las respuestas del módulo (JSON → `HandlesApiErrors`; errores por fila de los imports → "no se pudo procesar la fila" + `report()`); catch vacíos del módulo → `report()`.

Ratchet (no se fijó el baseline: `scripts/ratchet-baseline.json` es de TS-base en esta tanda; el integrador puede hacer `--update`):

| Métrica | Base | Rama |
|---|---:|---:|
| `fetch(` | 154 | 130 |
| `Swal.fire` | 339 | 213 |
| `onclick=` | 201 | 165 |
| `innerHTML =` | 228 | 208 |
| `X-CSRF-TOKEN` | 84 | 66 |
| `<script>` inline en Blade | 79 | 61 |
| `getMessage()` en `response()->json` | 120 | 90 |
| catch vacío | 26 | 13 |
| duplicación % | 6.82 | 6.41 |

## 5. Checklist UX-18

| Pantalla | 1.1 | 1.2 | 2.1 | 2.5 | 3.1 | 4.2 | 4.3 | Notas |
|---|---|---|---|---|---|---|---|---|
| Telares | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | selección con `aria-selected`, filas con teclado |
| Aplicaciones | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | |
| Matriz de Hilos | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | |
| Matriz de Calibres | ✅ | ✅ (el `<h1>` del contenido pasó a `<h2>`) | n/a | ✅ | ✅ | ✅ | ✅ | "Limpiar filtros" con `aria-label` |
| Pesos por Rollos | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | |
| Eficiencia / Velocidad | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | |
| Calendarios | ✅ | ✅ | n/a | ✅ (`text-[11px]` → `text-caption`) | ✅ | ✅ | ✅ | checks de la plantilla con `aria-label` |

2.1: ninguna pantalla del módulo tenía acciones solo por clic derecho. Botones del navbar con `min-h-touch` (el ancho se dejó igual: a 768 px el título ya tapa el logo y crecía el solape; ver U5/T6).
Capturas antes/después 768×1024 y 1280×800 en `19-06b-evidencia/` (arnés `19-06b-arnes/`), 0 errores de consola en las 9 URLs (`reporte-*.json`); flujos probados con Playwright: alta/edición/borrado, filtros, restablecer, Excel (xlsx real), plantilla de turnos, líneas, rango, recalcular.

## 6. Evidencia

- `php artisan test`: ver §8 (sqlite). Tests nuevos en `tests/Feature/CatalogosPlaneacion/` (57): caracterización de CRUD, recálculo, Excel real, consultas y guardián de JS inline.
- `npm run typecheck`, `npm run test:js` (424), `npm run build`, `npm run ratchet`: OK.
- `phpstan` sin errores nuevos; PHPMD sin violaciones nuevas; Pint en todos los PHP cambiados.
- Skill `code-review` (medium) sin hallazgos; su observación sobre el Salón "Ninguno" de Velocidad se corrigió (el select conserva el valor guardado).

## 7. Despliegue

Sin migraciones ni `.env`. `npm run build` y `php artisan optimize:clear` (vistas y rutas). Runbook opcional para decidir el cast de `Densidad`:

```sql
SELECT Densidad, COUNT(*) FROM dbo.ReqEficienciaStd GROUP BY Densidad;
SELECT Densidad, COUNT(*) FROM dbo.ReqVelocidadStd GROUP BY Densidad;
```

Si solo salen `Normal`/`Alta` (o NULL), se puede agregar `'Densidad' => Densidad::class` a los dos modelos.

## 8. Pendientes / HANDOFF

Ver `HANDOFF.md` §"De 19-06b". Tamaño: la rama pasa las 1 500 líneas del protocolo (p1 + p2 juntos, pedido del prompt); la mayor parte son borrados (~10 000).
