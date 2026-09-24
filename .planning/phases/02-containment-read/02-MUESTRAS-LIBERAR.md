# PT-02 → PT-05/06 — Liberar Muestras con prefijo "M" (requisito, no implementado)

**Estado:** especificado. **No se implementó en PT-02**, porque es una mutación y la fase 02 es de contención y lectura. La lógica de `LiberarOrdenesController::liberar` no se tocó.
**Decisiones del owner (2026-09-24):**
- las muestras **sí se liberan** (Marbetes A);
- la orden de una muestra lleva **prefijo `M` + número** (`M12345`) tanto en `CatCodificados.OrdenTejido` como en `MuestrasPrograma.NoProduccion`;
- para liberar hace falta `crear` del módulo Muestras (idrol 5). Esto ya está aplicado en PT-01.1.

## 1. Estado actual (verificado en código)

| Punto | Hoy | Evidencia |
|---|---|---|
| Folio | `FolioHelper::obtenerSiguienteFolio('Planeacion', 5)`, o el que escribe el usuario (`noProduccion`), **sin prefijo** y con la misma secuencia que Programa | `LiberarOrdenesController.php:339` |
| Asignación | `$registro->NoProduccion = $folio` sobre la tabla de la superficie (el contexto pone `MuestrasPrograma`) | `:376` |
| CatCodificados | `LiberarCatCodificadosWriter` busca y escribe por `OrdenTejido = NoProduccion` | `app/Services/Planeacion/Liberar/LiberarCatCodificadosWriter.php:63,158` |
| Unicidad | `LiberarValidacionesService::validarOrdenTejidoUnicoParaLiberacion` compara contra `ReqProgramaTejido` (vía `getTable()`, o sea la tabla del contexto) y `CatCodificados` | `LiberarValidacionesService.php:31-60` |
| Marbetes | asigna `NoMarbete`/`RollosProgramados`: **en Muestras falla siempre** ("Invalid column name") hasta aplicar `database/sql/pt_muestras_marbetes.sql` | `LiberarOrdenesController.php:487-488,526,619` |
| Redirect | `redirectUrl` fijo a `route('catalogos.req-programa-tejido')`, también desde Muestras | `:684,702` |

## 2. Requisitos para PT-05/06

| # | Requisito |
|---|---|
| R1 | En la superficie Muestras (`ProgramaTejidoSurface::Muestras`), el folio que se asigna es `'M'.$numero`. `$numero` sale de la secuencia de folios (definir con el owner si se reusa `Planeacion`/5 o se crea una secuencia propia de Muestras en `SSYSFoliosSecuencias`; **pregunta abierta**). |
| R2 | El mismo valor `M…` se escribe en `MuestrasPrograma.NoProduccion` y en `CatCodificados.OrdenTejido`, así el `OrdenTejido = NoProduccion` que usan el writer, el observer y el health check sigue cuadrando. |
| R3 | Si el usuario escribe el número a mano en Muestras: se acepta `M12345` o `12345`, y se normaliza a `M12345` (una sola `M`, en mayúscula, sin espacios). En Programa, una orden con prefijo `M` → 422 ("prefijo reservado para Muestras"). |
| R4 | La unicidad se valida contra **las dos** superficies y contra CatCodificados: un `M12345` no puede repetirse, y un número de Programa nunca empieza con `M`. |
| R5 | Programa no cambia: mismo folio sin prefijo y mismos marbetes. |
| R6 | Precondición: `pt_muestras_marbetes.sql` aplicado en live y `columnas_ausentes` actualizado. Mientras tanto, `ProgramaTejidoSurface::Muestras->soporta('marbetes')` es `false` y liberar en Muestras debe responder **422 explícito** en vez de 500. Ese guard puede adelantarse en PT-05 aunque R1–R4 esperen. |
| R7 | `redirectUrl` por superficie: `muestras.index` en Muestras. |
| R8 | Revisar el ancho de `NoProduccion` en Muestras y de `OrdenTejido` en CatCodificados (RS2 de `01-schema-fisico.sql`): el prefijo suma 1 carácter. |
| R9 | Consumidores de `OrdenTejido` que asumen que es numérico (casts `(int)`, `ORDER BY` numérico, `whereNumber`): inventariarlos con grep antes de liberar la primera muestra. |

## 3. Tests pendientes de implementación (especificación)

No se crearon como tests saltados ni incompletos, porque el protocolo lo prohíbe. Se escriben en PT-05/06 junto con el código, en `tests/Feature/Planeacion/LiberarMuestrasTest.php`, con el fixture `ProgramaTejidoFixtures` + `ConPermisosPlaneacion`:

| Test | Arrange | Assert |
|---|---|---|
| `test_liberar_muestra_asigna_orden_con_prefijo_m` | Muestras con el DDL de marbetes aplicado (fixture sin `columnas_ausentes` de marbetes); usuario con `crear,5`; secuencia de folio en 12345 | `MuestrasPrograma.NoProduccion === 'M12345'` y `CatCodificados.OrdenTejido === 'M12345'` |
| `test_liberar_programa_no_lleva_prefijo` | Programa, mismo folio | `ReqProgramaTejido.NoProduccion === '12345'` |
| `test_numero_manual_en_muestras_se_normaliza` | `noProduccion` = ` m12345 ` y `12345` | ambos guardan `M12345` |
| `test_programa_rechaza_prefijo_m` | Programa, `noProduccion` = `M1` | 422 |
| `test_orden_m_repetida_se_rechaza_entre_superficies` | `M12345` ya en CatCodificados | 422 y sin escrituras (foto de `fotoSuperficies()` sin cambios) |
| `test_liberar_muestra_sin_ddl_responde_422` | fixture actual (columnas ausentes) | 422 con `capacidad = marbetes`; sin 500 ni escrituras |
| `test_liberar_muestra_redirige_a_muestras` | Muestras | `redirectUrl === route('muestras.index')` |
| `test_liberar_muestra_exige_crear_de_muestras` | ya cubierto por `PlaneacionEscrituraAutorizacionTest` (PT-01.1) | — |
