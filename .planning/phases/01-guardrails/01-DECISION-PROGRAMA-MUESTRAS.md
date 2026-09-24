# 01.3 — Decisión Programa / Muestras (checkpoint BLOQUEANTE)

**Estado:** APROBADO por el owner el 2026-09-24 (check-in del integrador). Se aplica en la fase PT-02; nada se ejecutó en la fase 01.
**Fecha:** 2026-09-24 · **Track:** PT · **IDs:** PT-DOM-01, PT-DOM-02, PT-ROL-01
**Cómo responder:** marcar A o B en la tabla de la §5 (una casilla por capacidad) y devolver el archivo o contestar en la sesión. Se puede elegir distinto para cada capacidad.

## 1. Punto de partida (evidencia)

| Dato | Fuente | Estado |
|---|---|---|
| `ReqProgramaTejido` 141 columnas, `MuestrasPrograma` 135 | research live 2026-07-22 | verificado entonces; re-verificar con `sql/01-schema-fisico.sql` (RS1/RS2) |
| Faltan en Muestras: `NoMarbete`, `RollosProgramados`, `ProdId`, `ProduccionMarbetes`, `IdRedbooth`, `NombreRedbooth` | research live | ídem |
| 11 columnas más cortas en Muestras: CalendarioId, FlogsId, NombreProyecto, CustName, AplicacionId, Observaciones, ColorTrama, Prioridad, CombinaTram, BomId, BomName | research live | **longitudes exactas pendientes** (RS2). En `config/planeacion.php` están como `null` |
| `MuestrasPrograma` y `MuestrasProgramaLine` con 0 filas | research live | re-verificar con RS6 / `planeacion:programa-tejido-health` |
| Índice único / FK de Programa existen en live pero no en migrations; Muestras no los tiene | research | re-verificar con RS3/RS4. En `database/migrations` **no hay** unique `(SalonTejidoId, NoTelarId, Posicion)` ni FK real (solo índices de performance y la FK de `ProgramaId` comentada) |
| `StringTruncator` aplica **un solo mapa de límites (los de Programa)** a ambas superficies | `app/Helpers/StringTruncator.php:15-77` | verificado en código y congelado en `ProgramaTejidoSchemaCapabilityTest` |

Mecánica que hace que Muestras "herede" todo: `ProgramaTejidoContext` cambia la tabla de `ReqProgramaTejido` y `ReqProgramaTejidoLine` para URIs `planeacion/muestras*` y `muestras*` (congelado en `ProgramaTejidoIsolationTest`), y el front reescribe cualquier `fetch` a `/planeacion/programa-tejido` hacia `/planeacion/muestras` (`resources/js/programa-tejido/index.js:30-45`). Una acción sin ruta en Muestras acaba en 404; una con ruta se ejecuta contra `MuestrasPrograma` con las reglas de Programa.

## 2. Estado actual por capacidad

| Capacidad | ¿Alcanzable desde Muestras hoy? | Qué pasa hoy | Evidencia |
|---|---|---|---|
| **Redbooth** | No: solo existen rutas `planeacion/programa-tejido/redbooth*`; la URL reescrita da 404 | Botón visible, falla con 404. Si se agregara la ruta: 500 por columna inexistente (sin guard) | `RedboothProgramaTejidoController.php:148-163,345-359`; `routes/modules/planeacion.php:244-256`; snapshot de rutas (gap `solo_programa`) |
| **Marbetes** (editor `programa-tejido/marbetes`) | No (404) | El editor escribe `NoMarbete`/`RollosProgramados` (`LiberarOrdenesController.php:935-941`) | snapshot de rutas |
| **Marbetes al liberar** (`muestras/liberar-ordenes/procesar`) | **Sí** | `liberar()` asigna `NoMarbete`/`RollosProgramados` (`:487-488,526`) y `save()` (`:619`) → SQL Server "Invalid column name" → rollback y 500. **Liberar en Muestras siempre falla.** Además la ruta de Muestras exige `crear,2` (permiso de Programa) y redirige a `catalogos.req-programa-tejido` (`:684,702`) | snapshot de rutas (gap de permiso) |
| **Producción** (fórmulas Repeticiones → PzasRollo → MtsRollo → TotalRollos → TotalPzas) | **Sí** (update, dividir, balancear, observer) | `recalcularFormulasProduccion` hace un solo UPDATE que incluye `RollosProgramados` (`ReqProgramaTejidoObserver.php:221-231`) → excepción tragada por el `catch (Throwable)` (`:288-295`): **ninguna** de las 5 fórmulas se persiste en Muestras y el save responde éxito. `ProdId`: solo lo escribe `NotificarMontRollosController` contra `ReqProgramaTejido` fijo | `ProgramaTejidoInvariantTest::test_en_muestras_el_recalculo_de_produccion_falla_en_silencio` |
| **Descarga UNC** (`muestras/descargar-programa`) | **Sí** | Lee `MuestrasProgramaLine` (correcto) pero escribe el mismo archivo fijo `\\192.168.2.11\txts\ProgramaTejido.txt`: **una descarga de Muestras pisa el TXT de Programa** | `DescargarProgramaController.php:58` |
| **Finalización** (`planeacion/utileria/finalizar/*`) | No: la URI no cae en el contexto Muestras; opera siempre sobre `ReqProgramaTejido` | Muestras no tiene ciclo de finalización. El "fin de vida" real de una muestra es `ProcesarMuestrasDesarrolladorService`, que **borra** la fila procesada (`Muestras` directo, `saveQuietly`, sin truncado) | snapshot de rutas (`programa`/`finalizacion`) |
| Longitudes (transversal) | Sí, en todo save de Muestras | El truncado usa los límites de Programa; un valor que cabe en Programa y no en Muestras da SQLSTATE 22001 (500) | `ProgramaTejidoSchemaCapabilityTest` |

## 3. Alternativas

- **A — Paridad física aditiva.** `ALTER TABLE MuestrasPrograma ADD <col> NULL` con el tipo exacto de Programa (y `ALTER COLUMN` para ensanchar longitudes), vía `.sql` versionado con preflight, sin tocar migrations (el historial no coincide con live). Todo nullable, sin default que reescriba filas. Muestras tiene 0 filas: no hay backfill.
- **B — Capacidad exclusiva de Programa.** Muestras declara la capacidad como no soportada (`config/planeacion.php` → `capacidades.<x> = false`), la acción se oculta en la UI de Muestras y el backend responde 422/403 explícito en vez de 500/404/éxito aparente. No hay cambio de esquema.

## 4. Análisis por capacidad

### 4.1 Redbooth (`IdRedbooth`, `NombreRedbooth`)
| | A — paridad | B — exclusiva |
|---|---|---|
| Impacto | 2 columnas nullable. Exige además rutas `muestras/redbooth*`, decidir si Trazabilidad/`TrazabilidadRedboothService` debe mirar Muestras, y a qué proyecto Redbooth se vinculan las muestras | Ocultar el botón en Muestras; guard en `RedboothProgramaTejidoController` si la superficie es Muestras |
| Rollback | `ALTER TABLE ... DROP COLUMN` (0 filas con datos al inicio) | quitar el guard |
| Datos de Muestras | 0 filas → nada que migrar | ninguno |
| Owner técnico | PT (+ Trazabilidad solo lectura) | PT |
| Aceptación | vincular/desvincular una muestra en staging; Trazabilidad sin cambios para Programa | 0 llamadas Redbooth desde Muestras; respuesta 422 con mensaje; test de ruta |
| **Recomendación** | | **B**: Redbooth es seguimiento de producción comercial; no hay caso de negocio documentado para muestras |

### 4.2 Marbetes (`NoMarbete`, `RollosProgramados`, `ProduccionMarbetes`)
| | A — paridad | B — exclusiva |
|---|---|---|
| Impacto | 3 columnas nullable. Desbloquea **Liberar en Muestras** tal como está hoy y el editor de marbetes. Hay que confirmar si el proceso externo de impresión de marbetes debe leer Muestras | Liberar en Muestras tiene que dejar de asignar marbetes (rama por superficie en `LiberarOrdenesController::liberar`) o Liberar se bloquea en Muestras |
| Rollback | DROP de las 3 columnas | quitar la rama |
| Datos | 0 filas | ninguno |
| Owner | PT + dueño del proceso externo de marbetes | PT |
| Aceptación | liberar una muestra en staging sin 500; marbetes visibles | liberar una muestra sin tocar columnas de marbete; test que lo congele |
| **Recomendación** | **A** si Muestras debe liberarse (hoy la ruta existe y siempre falla); **B** si Muestras nunca se libera, y entonces también se retiran las rutas `muestras/liberar-ordenes*` | |
| Pregunta al owner | **¿Las muestras se liberan a producción (generan orden, CatCodificados, marbetes)?** | |

### 4.3 Producción (fórmulas + `RollosProgramados`, `ProdId`)
| | A — paridad | B — exclusiva |
|---|---|---|
| Impacto | Con `RollosProgramados` (y `ProdId`) físicos, el UPDATE del observer deja de fallar y Muestras obtiene Repeticiones/PzasRollo/MtsRollo/TotalRollos/TotalPzas | El observer omite `RollosProgramados` cuando la superficie no lo tiene (o no recalcula en Muestras) y lo registra explícitamente; los 5 campos seguirían vacíos o se calcularían sin `RollosProgramados` |
| Rollback | DROP | revertir el cambio del observer |
| Datos | 0 filas | ninguno |
| Owner | PT | PT |
| Aceptación | `test_en_muestras_el_recalculo_de_produccion_falla_en_silencio` se invierte: Repeticiones persistido en Muestras | ídem con la fórmula persistida sin `RollosProgramados`; ningún `catch` silencioso |
| **Recomendación** | **A**: es la opción de menor código (dos columnas nullable) y elimina un fallo silencioso que hoy ya afecta a update/dividir/balancear en Muestras. La contención del catch silencioso va a fase 02 en cualquier caso | |

### 4.4 Descarga UNC
| | A — archivo propio | B — exclusiva |
|---|---|---|
| Impacto | Sin cambio de esquema. Nombre de archivo por superficie (p. ej. `MuestrasPrograma.txt`); confirmar con el consumidor del TXT (lectura en planta) | Bloquear `muestras.descargar-programa` y ocultar el botón |
| Rollback | revertir el nombre | quitar el bloqueo |
| Datos | ninguno | ninguno |
| Owner | PT + consumidor del TXT | PT |
| Aceptación | descargar Muestras no modifica `ProgramaTejido.txt` | 422 en Muestras; Programa sin cambios |
| **Recomendación** | | **B inmediato** (hoy una descarga de Muestras pisa el archivo de Programa); A solo si alguien en planta consume un TXT de muestras. Es la decisión 4 de `01-CONTEXT.md` |

### 4.5 Finalización
| | A — paridad | B — exclusiva |
|---|---|---|
| Impacto | Crear un ciclo de finalización para Muestras (rutas utilería por superficie, `FechaFinaliza`, CatCodificados). No hay requerimiento | Documentar que Muestras termina vía `ProcesarMuestrasDesarrolladorService` (borra la fila) y que Utilería opera solo sobre Programa. Sin cambio de código |
| Rollback | eliminar rutas nuevas | n/a |
| Datos | 0 filas | ninguno |
| Owner | PT + Tejedores/Desarrolladores | PT |
| Aceptación | finalizar una muestra en staging | snapshot de rutas congela `utileria/*` como `programa` |
| **Recomendación** | | **B** (es el estado actual; solo se hace explícito) |

### 4.6 Longitudes (transversal, afecta a todas)
Independiente de las 5 capacidades. Opciones: **A** ensanchar las 11 columnas de Muestras a la longitud de Programa (`ALTER COLUMN`, 0 filas, sin riesgo de datos) o **B** que `StringTruncator` reciba la superficie. Recomendación: **A** (una sola vez, y deja de existir la divergencia); requiere primero RS2 para conocer longitudes y nulabilidad exactas.

## 5. Respuesta del owner

| Capacidad | A | B | Nota del owner |
|---|---|---|---|
| Redbooth | ☐ | ☑ | Aprobada la recomendación. |
| Marbetes (y ¿Muestras se libera?) | ☑ | ☐ | **Sí se liberan**, con una **"M"** en `CatCodificados.OrdenTejido` y en `MuestrasPrograma.NoProduccion` para distinguirlas de las órdenes de Programa. El formato exacto (p. ej. prefijo `M` + número de orden) se confirma con el owner al planear PT-02. |
| Producción | ☑ | ☐ | Aprobada la recomendación. |
| Descarga UNC | ☐ | ☑ | Aprobada la recomendación (bloquear; Muestras no pisa el TXT de Programa). |
| Finalización | ☐ | ☑ | Aprobada la recomendación (queda como hoy, explícito). |
| Longitudes (transversal) | ☑ | ☐ | Aprobada la recomendación (igualar las 11 columnas tras RS2). |

**Permiso de liberar Muestras:** la ruta `muestras.liberar-ordenes.procesar` debe exigir **`crear` del módulo Muestras (idrol 5)**, no el de Programa (idrol 2). Aprobado por el owner.

## 6. Qué pasa después de la respuesta (fase 02, no esta)

1. Actualizar `config/planeacion.php` → `superficies.muestras.capacidades` (`true` = A, `false` = B). El test de capacidades obliga a clasificar cualquier combinación nueva.
2. Para cada A: `.sql` aditivo en `database/sql/` (vía HANDOFF al dueño de esa carpeta) con preflight `IF COL_LENGTH(...) IS NULL`, backup previo y script de rollback; correr `sql/01-schema-fisico.sql` antes y después.
3. Para cada B: guard en backend (422 explícito), ocultar la acción en la UI de Muestras y test de ruta.
4. Correr `php artisan planeacion:programa-tejido-health --json` antes y después de cualquier cambio de esquema.
