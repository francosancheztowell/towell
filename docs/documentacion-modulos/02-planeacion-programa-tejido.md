# Planeación — Programa de Tejido

> Generado automáticamente — documentación detallada del módulo

Este documento cubre el ámbito completo "Planeación → Programa de Tejido" del proyecto **Towell** (Laravel 12, SQL Server, industria textil). Incluye los 11 controllers raíz, las subcarpetas `funciones/` y `helper/`, el generador de Orden de Cambio (Felpa), los 17 blades, los 2 archivos JS dedicados, observers, middleware, service de prioridad, modelos y rutas.

> Convenciones del proyecto respetadas en este documento: el directorio de vistas de catálogos se llama `catalagos` (typo intencional) y el campo de permiso en `SYSRoles` es `reigstrar` (typo intencional). El módulo de Programa de Tejido **reutiliza por completo** sus controllers para el submódulo **Muestras** (mismo código, distintas tablas vía el middleware `ProgramaTejidoContext`).

---

## 1. Propósito del módulo

El **Programa de Tejido** es el corazón de la planeación productiva textil. Gestiona la cola de producción de cada **telar** (agrupados por **salón**: `JACQUARD` / `SMIT`), determinando qué modelo de toalla se teje, en qué orden (Posición), con qué fechas de inicio/fin (calculadas contra un **calendario/jornada**), y con qué cantidades (pedido, saldo, producción).

Resuelve:

- **Captura y secuenciación** de órdenes por telar (crear, editar, eliminar, reordenar por drag&drop o prioridad).
- **Cálculo automático de fechas** (FechaInicio/FechaFinal) en cascada al insertar/mover/eliminar, respetando el calendario (snap a turnos) y los registros `EnProceso`.
- **Operaciones de distribución de carga**: cambiar de telar, **duplicar**, **dividir** (un pedido entre varios telares), **vincular/desvincular** (agrupar registros bajo una `OrdCompartida`), y **balancear** (redistribuir cantidades dentro de un grupo compartido).
- **Liberación de órdenes**: asignar folio de producción (`NoProduccion`), BOM (L.Mat), código de dibujo, peso de rollo, y generar el **Excel de "Orden de Cambio de Modelo"** que va a piso, sincronizando con `CatCodificados`.
- **Repaso**: registro especial de cambio de hilo/ancho en un telar.
- **Generación de líneas diarias** (`ReqProgramaTejidoLine`): explosión de la producción día-a-día (kilos rizo, pie, trama, combinaciones) usada para el TXT de scheduling de piso.

Submódulos / vistas que abarca:

- **Programa Tejido** (`/planeacion/programa-tejido`) — tabla principal `ReqProgramaTejido` / `ReqProgramaTejidoLine`.
- **Muestras** (`/planeacion/muestras`) — mismas pantallas y controllers, pero contra tablas `MuestrasPrograma` / `MuestrasProgramaLine`.
- **Liberar Órdenes**, **Reimprimir Órdenes**, **Balancear**, **Orden de Cambio (Felpa)**.

Rol en el flujo productivo: Planeación captura/secuencia el programa → libera órdenes (genera folio + Excel de cambio de modelo) → el programa alimenta a **Tejido / Tejedores / Codificación** (vía `CatCodificados`) y la descarga TXT alimenta el scheduling de piso.

---

## 2. Rutas

Todas las rutas viven en `routes/modules/planeacion.php`, incluido desde `routes/web.php` dentro de `Route::middleware(['auth'])->group(...)`. Por tanto **todas requieren autenticación** (middleware `auth`). El middleware global `ProgramaTejidoContext` (registrado en `bootstrap/app.php:28`) intercepta las rutas `planeacion/muestras*`, `planeacion/muestras-line*` y `muestras*` para reconfigurar las tablas a `MuestrasPrograma`/`MuestrasProgramaLine`.

> **Permisos**: estas rutas **no** declaran `userCan()` a nivel de ruta ni middleware de permiso específico; el control de acceso al módulo se realiza vía el menú/permisos de `SYSUsuariosRoles` (helper `userCan('acceso','Planeacion'/'Tejido')`) y la UI oculta acciones. Los controllers de este ámbito **no invocan `userCan()` internamente** (ver nota en sección 8). La columna "Permiso" se marca como *(módulo Planeación, sin verificación explícita en controller)*.

### 2.1 Rutas de Programa de Tejido

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET (301) | `/planeacion/programatejido` | redirect → `/planeacion/programa-tejido` | auth | — |
| GET | `/planeacion/programa-tejido` | `ProgramaTejidoController@index` (name `catalogos.req-programa-tejido`) | auth | Planeación |
| GET | `/planeacion/programa-tejido/liberar-ordenes` | `LiberarOrdenesController@index` | auth | Planeación |
| POST | `/planeacion/programa-tejido/liberar-ordenes/procesar` | `LiberarOrdenesController@liberar` | auth | Planeación |
| GET | `/planeacion/programa-tejido/liberar-ordenes/bom-sugerencias` | `LiberarOrdenesController@obtenerBomYNombre` | auth | Planeación |
| GET | `/planeacion/programa-tejido/liberar-ordenes/tipo-hilo` | `LiberarOrdenesController@obtenerTipoHilo` | auth | Planeación |
| GET | `/planeacion/programa-tejido/liberar-ordenes/codigo-dibujo` | `LiberarOrdenesController@obtenerCodigoDibujo` | auth | Planeación |
| GET | `/planeacion/programa-tejido/liberar-ordenes/opciones-hilos` | `LiberarOrdenesController@obtenerOpcionesHilos` | auth | Planeación |
| POST | `/planeacion/programa-tejido/liberar-ordenes/guardar-campos` | `LiberarOrdenesController@guardarCamposEditables` | auth | Planeación |
| GET | `/planeacion/programa-tejido/reimprimir-ordenes/{id}` | `ReimprimirOrdenesController@reimprimir` | auth | Planeación |
| POST | `/planeacion/programa-tejido/descargar-programa` | `DescargarProgramaController@descargar` | auth | Planeación |
| POST | `/planeacion/programa-tejido/{id}/prioridad/mover` | `ProgramaTejidoOperacionesController@moveToPosition` | auth | Planeación |
| POST | `/planeacion/programa-tejido/{id}/verificar-cambio-telar` | `ProgramaTejidoOperacionesController@verificarCambioTelar` | auth | Planeación |
| POST | `/planeacion/programa-tejido/{id}/cambiar-telar` | `ProgramaTejidoOperacionesController@cambiarTelar` | auth | Planeación |
| POST | `/planeacion/programa-tejido/duplicar-telar` | `ProgramaTejidoOperacionesController@duplicarTelar` | auth | Planeación |
| POST | `/planeacion/programa-tejido/dividir-telar` | `ProgramaTejidoOperacionesController@dividirTelar` | auth | Planeación |
| POST | `/planeacion/programa-tejido/dividir-saldo` | `ProgramaTejidoOperacionesController@dividirSaldo` | auth | Planeación |
| POST | `/planeacion/programa-tejido/vincular-telar` | `ProgramaTejidoOperacionesController@vincularTelar` | auth | Planeación |
| POST | `/planeacion/programa-tejido/vincular-registros-existentes` | `ProgramaTejidoOperacionesController@vincularRegistrosExistentes` | auth | Planeación |
| POST | `/planeacion/programa-tejido/{id}/desvincular` | `ProgramaTejidoOperacionesController@desvincularRegistro` | auth | Planeación |
| GET | `/planeacion/programa-tejido/registros-ord-compartida/{ordCompartida}` | `ProgramaTejidoOperacionesController@getRegistrosPorOrdCompartida` | auth | Planeación |
| GET | `/planeacion/programa-tejido/balancear` | `ProgramaTejidoBalanceoController@balancear` | auth | Planeación |
| GET | `/planeacion/programa-tejido/{id}/detalles-balanceo` | `ProgramaTejidoBalanceoController@detallesBalanceo` | auth | Planeación |
| POST | `/planeacion/programa-tejido/preview-fechas-balanceo` | `ProgramaTejidoBalanceoController@previewFechasBalanceo` | auth | Planeación |
| POST | `/planeacion/programa-tejido/actualizar-pedidos-balanceo` | `ProgramaTejidoBalanceoController@actualizarPedidosBalanceo` | auth | Planeación |
| POST | `/planeacion/programa-tejido/balancear-automatico` | `ProgramaTejidoBalanceoController@balancearAutomatico` | auth | Planeación |
| GET | `/planeacion/programa-tejido/ver-detalles-grupo-balanceo/{ordCompartida}` | `ProgramaTejidoBalanceoController@verDetallesGrupoBalanceo` (name `verdetallesgrupobalanceo`) | auth | Planeación |
| PUT | `/planeacion/programa-tejido/{id}` | `ProgramaTejidoController@update` | auth | Planeación |
| DELETE | `/planeacion/programa-tejido/{id}` | `ProgramaTejidoController@destroy` | auth | Planeación |
| DELETE | `/planeacion/programa-tejido/{id}/en-proceso` | `ProgramaTejidoController@destroyEnProceso` | auth | Planeación |
| GET | `/planeacion/programa-tejido/all-registros-json` | `ProgramaTejidoCalendariosController@getAllRegistrosJson` | auth | Planeación |
| POST | `/planeacion/programa-tejido/actualizar-calendarios-masivo` | `ProgramaTejidoCalendariosController@actualizarCalendariosMasivo` | auth | Planeación |
| POST | `/planeacion/programa-tejido/{id}/reprogramar` | `ProgramaTejidoCalendariosController@actualizarReprogramar` | auth | Planeación |
| POST | `/planeacion/programa-tejido/crear-repaso` | `RepasoController@createrepaso` | auth | Planeación |
| POST | `/planeacion/programa-tejido/recalcular-fechas` | `ProgramaTejidoCalendariosController@recalcularFechas` | auth | Planeación |
| POST | `/planeacion/muestras/recalcular-fechas` | `ProgramaTejidoCalendariosController@recalcularFechas` (name `muestras.recalcular-fechas`) | auth | Planeación |
| GET | `/planeacion/req-programa-tejido-line` | `ReqProgramaTejidoLineController@index` | auth | Planeación |

### 2.2 Rutas de catálogos/AJAX (prefijo `/programa-tejido`)

| Método | URI | Controller@método |
|---|---|---|
| GET | `/programa-tejido/salon-options` y `/salon-tejido-options` | `ProgramaTejidoCatalogosController@getSalonTejidoOptions` |
| GET | `/programa-tejido/tamano-clave-by-salon` | `@getTamanoClaveBySalon` |
| GET | `/programa-tejido/flogs-id-options` | `@getFlogsIdOptions` |
| GET | `/programa-tejido/flogs-id-from-twflogs` | `@getFlogsIdFromTwFlogsTable` |
| GET | `/programa-tejido/descripcion-by-idflog/{idflog}` | `@getDescripcionByIdFlog` |
| GET | `/programa-tejido/flog-by-item` | `@getFlogByItem` |
| GET | `/programa-tejido/flogs-by-tamano-clave` | `@getFlogsByTamanoClave` |
| GET | `/programa-tejido/calendario-id-options` | `@getCalendarioIdOptions` |
| GET | `/programa-tejido/calendario-lineas/{calendarioId}` | `@getCalendarioLineas` |
| GET | `/programa-tejido/aplicacion-id-options` | `@getAplicacionIdOptions` |
| GET/POST | `/programa-tejido/datos-relacionados` | `@getDatosRelacionados` |
| GET | `/programa-tejido/telares-by-salon` | `@getTelaresBySalon` |
| GET | `/programa-tejido/telares-all` | `@getTelaresAll` |
| GET | `/programa-tejido/ultima-fecha-final-telar` | `@getUltimaFechaFinalTelar` |
| GET | `/programa-tejido/hilos-options` | `@getHilosOptions` |
| GET | `/programa-tejido/eficiencia-std` | `@getEficienciaStd` |
| GET | `/programa-tejido/velocidad-std` | `@getVelocidadStd` |
| GET | `/programa-tejido/eficiencia-velocidad-std` | `@getEficienciaVelocidadStd` |
| POST | `/programa-tejido/calcular-totales-dividir` | `funciones\DividirTejido@calcularTotalesDividir` |
| GET | `/programa-tejido/columnas` | `ColumnasProgramaTejidoController@index` |
| GET | `/programa-tejido/columnas/visibles` | `ColumnasProgramaTejidoController@getColumnasVisibles` |
| POST | `/programa-tejido/columnas` | `ColumnasProgramaTejidoController@store` |
| GET | `/planeacion/codificacion/orden-cambio-excel` | `OrdenDeCambio\Felpa\OrdenDeCambioFelpaController@generarExcel` (name `codificacion.orden-cambio-excel`) |

> **Nota**: la única ruta vigente para la Orden de Cambio (Felpa) es `codificacion.orden-cambio-excel` → `generarExcel`. No existe ruta PDF; el controller solo implementa `generarExcelDesdeBD` y `generarExcel`.

### 2.3 Rutas de Muestras (reusan los mismos controllers)

Existe un **espejo completo** de todas las rutas anteriores bajo el prefijo `/planeacion/muestras` y `/muestras` (con nombres `muestras.*`), apuntando a los **mismos** controllers. La diferencia funcional la introduce `ProgramaTejidoContext`, que redirige las tablas a `MuestrasPrograma`/`MuestrasProgramaLine`. Ejemplos: `muestras.index`, `muestras.liberar-ordenes`, `muestras.balancear`, `muestras.update`, `muestras.destroy`, `muestras.crear-repaso`, `planeacion.muestras-line`, `/muestras/salon-options`, `/muestras/columnas`, etc. (mismas firmas que las tablas 2.1 y 2.2).

---

## 3. Controllers

### 3.1 `ProgramaTejidoController` (`app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php`)

Controlador principal. Detecta si la request es Muestras (`request()->is('planeacion/muestras')`) para definir `basePath`/`apiPath`/`linePath`/`pageTitle`.

- **`index()`** — Carga todos los registros de `ReqProgramaTejido` con un `select` explícito de ~100 columnas, ordenados con scope `ordenado()` (Salón, Telar, Posición, FechaInicio). Obtiene columnas de tabla con `UtilityHelpers::getTableColumns()`. Devuelve la vista `modulos.programa-tejido.req-programa-tejido` con `registros`, `columns`, `basePath`, `apiPath`, `linePath`, `pageTitle`. En error captura `Throwable`, loguea y devuelve la misma vista con colección vacía + mensaje de error. Conexión: `sqlsrv` (default).
- **`edit(int $id)`** — Delega en `funciones\EditTejido::editar($id)`. Devuelve vista de formulario.
- **`update(Request $request, int $id)`** — Delega en `funciones\UpdateTejido::actualizar($request, $id)`. Respuesta JSON.
- **`store(Request $request)`** — Crea uno o varios registros (uno por telar). Valida: `salon_tejido_id` (required string), `tamano_clave`/`hilo`/`idflog`/`calendario_id`/`aplicacion_id` (nullable string), `telares` (required array min:1) y por fila `telares.*.no_telar_id` (required), `cantidad` (numeric), `fecha_inicio/fecha_final/compromiso_tejido/fecha_cliente/fecha_entrega` (date). Flujo (transacción `sqlsrv`): bulk `Ultimo=0` para los telares afectados, marca `CambioHilo` masivo (`UtilityHelpers::marcarCambioHiloBulk`), y por cada fila crea un `ReqProgramaTejido` aplicando `UpdateHelpers::aplicarCamposFormulario/aplicarAliasesEnNuevo/aplicarFallbackModeloCodificado`, trunca strings (`StringTruncator`), resuelve `TipoPedido` desde el flog (`UtilityHelpers::resolveTipoPedidoFromFlog`), asigna posición (`TejidoHelpers::obtenerSiguientePosicionDisponible`) y guarda. Respuesta JSON `{success, message, data}`. Tabla: `dbo.ReqProgramaTejido` (`sqlsrv`).
- **`destroy(int $id)`** — Delega en `funciones\EliminarTejido::eliminar($id)`.
- **`destroyEnProceso(int $id)`** — Delega en `funciones\EliminarTejido::eliminarEnProceso($id)`.

### 3.2 `ProgramaTejidoCatalogosController`

Endpoints de catálogos/opciones para los selects de la UI. Todas devuelven JSON.

- **`getSalonTejidoOptions()`** — Distinct de `SalonTejidoId` uniendo `ReqProgramaTejido` + `ReqModelosCodificados` (`sqlsrv`).
- **`getTamanoClaveBySalon(Request)`** — `TamanoClave` distintas de `ReqModelosCodificados` filtradas por `salon_tejido_id` y `search` (LIKE, límite 50).
- **`getFlogsIdOptions()`** — `FlogsId` distintos uniendo programa + modelos.
- **`getFlogsIdFromTwFlogsTable()`** — `IDFLOG` de `dbo.TwFlogsTable` (**conexión `sqlsrv_ti`**, TI_PRO) con `EstadoFlog ∈ {3,4,5,21}`.
- **`getDescripcionByIdFlog($idflog)`** — `NAMEPROYECT`/`CUSTNAME` de `dbo.TwFlogsTable` por IDFLOG (`sqlsrv_ti`). Devuelve `{nombreProyecto, custName}`.
- **`getFlogByItem(Request)`** — Resuelve el Flog a partir de `item_id`+`invent_size_id` o de `tamano_clave`+`salon_tejido_id`. Une `dbo.TwFlogsItemLine` + `dbo.TwFlogsTable` (`sqlsrv_ti`), `ESTADOFLOG ∈ {3,4,5,21}`, prioriza el IDFLOG con sufijo numérico mayor. Valida que existan ambos parámetros (400 si no).
- **`getFlogsByTamanoClave(Request)`** — Valida `tamano_clave` required. Busca modelos por clave (match exacto normalizado o LIKE), arma pares Item/Size y consulta `TwFlogsItemLine`/`TwFlogsTable` (`sqlsrv_ti`) para listar flogs candidatos.
- **`getCalendarioIdOptions()`** — `CalendarioId` distintos no vacíos de `ReqCalendarioTab` (`QueryHelpers::pluckDistinctNonEmpty`).
- **`getCalendarioLineas($calendarioId)`** — Líneas de `ReqCalendarioLine` (Id, FechaInicio, FechaFin, HorasTurno, Turno) ordenadas por FechaInicio.
- **`getAplicacionIdOptions()`** — `AplicacionId` distintos de `ReqAplicaciones`.
- **`getDatosRelacionados(Request)`** — Dado `salon_tejido_id` (required) y opcional `tamano_clave`, obtiene el modelo codificado (`TejidoHelpers::obtenerModeloPorTamanoClave`) y devuelve un objeto **mapeado** de ~70 campos técnicos (rizo, pie, trama, combinaciones, calibres, colores, ancho, peso, etc.) usado para autollenar el formulario. Loguea el resultado. Tabla: `ReqModelosCodificados` (`sqlsrv`).
- **`getEficienciaStd(Request)`** / **`getVelocidadStd(Request)`** — Delegan en `QueryHelpers::getStdValue` sobre `ReqEficienciaStd` / `ReqVelocidadStd`.
- **`getEficienciaVelocidadStd(Request)`** — Requiere `fibra_id`, `no_telar_id`, `calibre_trama` (400 si falta). Devuelve `{eficiencia, velocidad}` vía `QueryHelpers::getEficienciaVelocidadStd`.
- **`getTelaresBySalon(Request)`** — `NoTelarId` distintos de `ReqProgramaTejido` por salón.
- **`getTelaresAll()`** — Pares `SalonTejidoId|NoTelarId` distintos (value/label) de `ReqProgramaTejido`.
- **`getUltimaFechaFinalTelar(Request)`** — Última `FechaFinal` (+ hilo, máquina, ancho) del telar (salón+telar requeridos).
- **`getHilosOptions()`** — `Hilo` distintos de `ReqMatrizHilos`.

### 3.3 `ProgramaTejidoOperacionesController`

Operaciones de cambio de telar, reordenamiento y vinculación. Regla clave: `OrdCompartida` agrupa registros vinculados; `OrdCompartidaLider=1` marca el líder del grupo.

- **`verificarCambioTelar(Request, int $id)`** — Valida `nuevo_salon`/`nuevo_telar` (required). Pre-chequeo del cambio de telar: si mismo telar → no requiere confirmación. Verifica que la clave modelo exista en el destino (`QueryHelpers::findModeloDestino`); si no, `puede_mover=false`. Calcula los **cambios** que se aplicarían (salón, telar, último, cambio hilo, ancho, eficiencia/velocidad STD, calibres rizo/pie, recálculo de fechas) y los devuelve para mostrar en un diálogo de confirmación. No persiste. Tablas: `ReqProgramaTejido`, `ReqModelosCodificados`, catálogos STD.
- **`cambiarTelar(Request, int $id)`** — Valida `nuevo_salon`/`nuevo_telar`/`target_position` (required, min:0). Mueve un registro a otro telar/posición. Rechaza si `EnProceso=1` (422), si mismo telar (422) o si el modelo no existe en destino (422). En transacción con observers desactivados: recalcula secuencia de fechas del telar origen y destino (`DateHelpers::recalcularFechasSecuencia`), resuelve STD del telar destino (`QueryHelpers::resolverStdSegunTelar`), reconstruye `Maquina` (`construirMaquinaSegunSalon`, privado), respeta el mínimo de posición tras registros `EnProceso`, aplica updates con offsets temporales para evitar colisión del índice único (telar+posición), regenera líneas (`ReqProgramaTejido::regenerarLineas`) y registra auditoría (`AuditoriaHelper::logDragDrop`). JSON con `registros_afectados`, `detalles`, `updates`.
- **`moveToPosition(Request, int $id)`** — Delega en `funciones\DragAndDropTejido::mover`.
- **`duplicarTelar(DuplicarTejidoRequest)`** — Delega en `funciones\DuplicarTejido::duplicar`.
- **`dividirTelar(DividirTelarRequest)`** — **Divide físicamente** los registros de un telar en dos a partir de `posicion_division`: los registros desde esa posición se mueven a `nuevo_telar`/`nuevo_salon`, recalculando fechas en ambos telares y regenerando líneas. Requiere ≥2 registros (422) y posición válida (422). (Distinto de `dividirSaldo`.)
- **`dividirSaldo(DividirSaldoRequest)`** — Delega en `funciones\DividirTejido::dividir` (división de cantidad/pedido entre telares con `OrdCompartida`).
- **`vincularTelar(Request)`** — Hace `merge(['vincular'=>true])` y delega en `funciones\DuplicarTejido::duplicar` (crea duplicados vinculados bajo una `OrdCompartida`).
- **`vincularRegistrosExistentes(Request)`** — Delega en `funciones\VincularTejido::vincularRegistrosExistentes`.
- **`desvincularRegistro(Request, $id)`** — Delega en `funciones\VincularTejido::desvincularRegistro`.
- **`getRegistrosPorOrdCompartida($ordCompartida)`** — Normaliza el id (privado `normalizeOrdCompartida`) y delega en `funciones\BalancearTejido::getRegistrosPorOrdCompartida`.
- *(privados)* `normalizeOrdCompartida($value)`: castea a int o null; `construirMaquinaSegunSalon($base,$salon,$telar)`: deriva prefijo SMI/JAC y arma `"PREFIJO telar"`.

### 3.4 `ProgramaTejidoBalanceoController`

- **`balancear()`** — Carga registros con `OrdCompartida` no nula, agrupados por `OrdCompartida` (cast int), ordenados por FechaInicio/Telar. Devuelve la vista `modulos.programa-tejido.balancear` con `gruposCompartidos`. Tabla `ReqProgramaTejido` (`sqlsrv`).
- **`detallesBalanceo($id)`** — Devuelve JSON con el registro `fresh()` o 404 si no existe.
- **`previewFechasBalanceo(Request)`** — Delega en `BalancearTejido::previewFechas` (simulación sin persistir).
- **`actualizarPedidosBalanceo(Request)`** — Delega en `BalancearTejido::actualizarPedidos` (persiste cambios de pedido + cascada).
- **`balancearAutomatico(Request)`** — Delega en `BalancearTejido::balancearAutomatico`.
- **`verDetallesGrupoBalanceo($ordCompartida)`** — Normaliza (privado `normalizeOrdCompartidaRouteParam`) y delega en `BalancearTejido::getRegistrosPorOrdCompartida` (ruta histórica equivalente a `getRegistrosPorOrdCompartida`).

### 3.5 `ProgramaTejidoCalendariosController`

- **`getAllRegistrosJson()`** — JSON con `Id`, `NoTelarId`, `NombreProducto` de todos los registros (ordenados por telar/Id).
- **`actualizarCalendariosMasivo(Request)`** — `set_time_limit(300)`. Valida `calendario_id` (required) y `registros_ids` (array, cada uno `exists` en la tabla). Asigna el calendario a todos los registros y **recalcula fechas en secuencia por telar** respetando `EnProceso` (arranca desde `now()`), hace **snap al calendario** (`CalendarioController::snapInicioAlCalendario`), calcula `HorasProd` y `FechaFinal` (`BalancearTejido::calcularFechaFinalDesdeInicio`), aplica fórmulas dependientes (`CalendarioController::calcularFormulasDependientesDeFechas`), guarda con `saveQuietly()`, regenera líneas y audita cambios de fecha inicio (`AuditoriaHelper::logCambioFechaInicio`). Maneja el dispatcher de eventos manualmente. JSON con conteos (`actualizados`, `procesados`, `errores`, `tiempo_segundos`). Tabla `ReqProgramaTejido`.
- **`actualizarReprogramar(Request, int $id)`** — Valida `reprogramar` (nullable in:1,2). Solo permite actualizar si `EnProceso=1` (422 en caso contrario). Persiste `Reprogramar` (1 = pasar al siguiente, 2 = pasar al último; ver EliminarTejido). 404 si no existe.
- **`recalcularFechas(Request): JsonResponse`** — Ejecuta el comando artisan `programa-tejido:recalcular-fechas-produccion --all` y devuelve su salida. (Usado por el botón "Recalcular fechas".)

### 3.6 `ColumnasProgramaTejidoController`

Persistencia por usuario del estado de columnas (ocultas/visibles, pin) en `OrdColProgramaTejido`.

- **`index(Request)`** — Devuelve `{Columna: bool}` (true=oculta) por `usuario_id` (o `Auth::id()`). Usa índice `UX_OrdColProgramaTejido_Usuario_Columna`. 400 si no hay usuario.
- **`getColumnasVisibles(Request)`** — Lista de columnas con `Estado=1` (visibles) por usuario (índice filtrado `IX_OrdColProgTej_Usuario_Estado1`).
- **`store(Request)`** — Valida `columnas` (array). Hace `upsert` de `{UsuarioId, Columna, Estado}` por (UsuarioId, Columna). JSON de confirmación.

### 3.7 `ReqProgramaTejidoLineController`

CRUD JSON de las líneas diarias `ReqProgramaTejidoLine`.

- *(privado)* `rules(bool $isUpdate)` — Reglas: `Fecha` (date), 13 columnas numéricas nullable (Cantidad, Kilos, Aplicacion, Trama, Combina1-5, Pie, Rizo, MtsRizo, MtsPie), `ProgramaId` (`exists` en tabla programa; required en create, sometimes en update).
- *(privado)* `sanitize(array)` — Convierte cadenas vacías a null en columnas numéricas.
- **`index(Request): JsonResponse`** — Lista paginada con filtros `programa_id` (scope `programa`), `fecha` (scope `onDate`), rango `desde`/`hasta` (scope `between`). Orden whitelist (`Fecha`/`Id`/`ProgramaId`). `per_page` por defecto 1000 si solo se filtra por programa, máx 5000.
- **`store(Request): JsonResponse`** — Crea una línea (201).
- **`show(int $id): JsonResponse`** — Devuelve una línea (`findOrFail`).
- **`update(Request, int $id): JsonResponse`** — Actualiza una línea.
- **`destroy(int $id): JsonResponse`** — Elimina una línea.

### 3.8 `DescargarProgramaController`

- **`descargar(Request)`** — Valida `fecha_inicial` (required date). Lee `ReqProgramaTejidoLine` con relación `programa` (`Fecha >= fecha_inicial`), genera un **TXT pipe-delimitado** (encabezados mapeados + columnas del programa + columnas de línea), con un Id consecutivo que agrupa por `TamanoClave + NoProduccion`, formatea fechas/decimales (`formatearValor`, privado; constantes `COLUMNAS_FECHA_HORA`, `COLUMNAS_FECHA`, `COLUMNAS_DECIMALES`) y **escribe el archivo en la ruta de red** `\\192.168.2.11\txts\ProgramaTejido.txt`. Devuelve JSON con conteo o error si la ruta de red no es accesible. *(privados)* `getMapeoColumnas()`, `getColumnasOrdenadas()`, `formatearValor()`.

### 3.9 `LiberarOrdenesController`

Pantalla y proceso de liberación de órdenes de producción. Tablas tocadas: `ReqProgramaTejido` (`sqlsrv`), `CatCodificados` (`sqlsrv`), `ReqModelosCodificados`, `ReqPesosRollosTejido`, y AX vía **`sqlsrv_ti`** (`BOMTABLE`, `BOMVERSION`, `INVENTTABLE`, `TwTipoHilo`). Constantes: `BOM_CRUDO_TW_SALONES=['SMIT','JACQUARD']`, `PESO_ROLLO_KG_FELPA=90.0`.

- **`index(Request)`** — Lista los registros **sin** `NoProduccion`. Aplica la fórmula **INN** (`=SI(FechaInicio ≤ HOY()+dias, HOY(), "")`) con `dias` (parámetro o sesión `liberar_ordenes_dias`, default 10.999, rango 0–999.999). Filtra los que cumplen INN y no tienen `NoExisteBase`, y calcula la "PrioridadAnterior" (CHECAR + producto del registro previo del mismo telar). Devuelve la vista `modulos.programa-tejido.liberar-ordenes.index`.
- **`liberar(Request)`** — `set_time_limit(0)`. Valida `registros` (array) y por fila `id` (exists), `bomId`/`bomName` (required), `prioridad`, `hiloAX`, `pesoRollo`, `repeticiones`, `noTiras`, `saldoMarbete`, `mtsRollo`, `pzasRollo`, `totalRollos`, `totalPzas`, `densidad`, `observaciones`, `cambioRepaso` (in SI/NO), `combinaTram`, `noProduccion` (max 15), `codigoDibujo`. En transacción: por cada registro genera/usa **folio** (`FolioHelper::obtenerSiguienteFolio('Planeacion', 5)` o manual), valida unicidad de la orden (`validarOrdenTejidoUnicoParaLiberacion`, privado), calcula fecha programada, peso de rollo (con lógica especial felpa/FEL: 90 kg, ajustes de saldo/mts/pzas), repeticiones y saldo de marbete (`repeticionesDesdePesoRollo`, `saldoMarbeteDesdeFormula`), valida métricas de producción (`validarMetricasProduccionParaLiberar`), resuelve BOM crudo (`resolverBomCrudoExacto/Opciones`), aplica auditoría y guarda. Luego sincroniza a **`CatCodificados`** (`actualizarCatCodificados`) y `ReqModelosCodificados` (`actualizarReqModelosCodificados`), y **genera el Excel de Orden de Cambio** (`OrdenDeCambioFelpaController::generarExcelDesdeBD`) devolviéndolo en base64 (`fileData`) junto a `redirectUrl`. Respuestas 422 ante folios duplicados/ inválidos.
- **`obtenerBomYNombre(Request)`** — Busca L.Mat (BOM) y Nombre en AX (`BOMTABLE`+`BOMVERSION`, `sqlsrv_ti`), filtrando `ITEMGROUPID='CRUDO'` y `TwSalon ∈ {SMIT,JACQUARD}`. Soporta: `freeMode` (libre), `combinations` (múltiples `item::size`, devuelve mapa `item|size→[{bomId,bomName}]`), o individual `itemId`+`inventSizeId` (con `term`/`fallback`). *(privado)* `queryBomFallback`.
- **`obtenerTipoHilo(Request)`** — Dado `itemIds` (CSV), devuelve mapa `itemId→TwTipoHiloId` desde `INVENTTABLE` (`sqlsrv_ti`).
- **`guardarCamposEditables(Request)`** — Valida `id` (exists), `field` (in: MtsRollo, PzasRollo, TotalRollos, TotalPzas, Repeticiones, SaldoMarbete, Densidad, CombinaTrama), `value`. Actualiza el campo en `ReqProgramaTejido` (y sincroniza a `CatCodificados`).
- **`obtenerCodigoDibujo(Request)`** — Dado `combinations` (`item::size::departamento`), devuelve mapa `cacheKey→CodigoDibujo` resolviendo desde `CatCodificados` (privado `resolverCodigoDibujoCatCodificados`: prioridad Item+Departamento → Item+Size+Departamento → Item+Size, último `Id` con código no vacío).
- **`obtenerOpcionesHilos()`** — `TipoHilo` distintos de `TwTipoHilo` (`sqlsrv_ti`).
- *(privados relevantes)*: `validarOrdenTejidoUnicoParaLiberacion`, `actualizarCatCodificados(...)` y `actualizarCatCodificadosCampo(...)` (sincronización a CatCodificados), `actualizarReqModelosCodificados` (graba `OrdPrincipal`/`PesoMuestra`), `obtenerPesoRollo`/`obtenerPesoRolloPorInventSizeId`, `resolverBomCrudoExacto`/`resolverBomCrudoOpciones`/`normalizarSalonBomCrudo`, helpers de felpa (`esTamanoFelpa`, `esInventSizeFel`, `aplicarAjusteFel*`), `calcularFechaProgramada`.

### 3.10 `ReimprimirOrdenesController`

- **`reimprimir($id)`** — Recibe el Id de un `CatCodificados`. Valida que el registro exista, tenga `UsuarioCrea` (403 si no) y `OrdenTejido` (400 si no). Busca el `ReqProgramaTejido` por `NoProduccion = OrdenTejido` y reusa `OrdenDeCambioFelpaController::generarExcelDesdeBD([$registro])` para reimprimir el Excel de la orden de cambio. Devuelve el `StreamedResponse` o JSON de error.

### 3.11 `RepasoController`

- **`createrepaso(Request)`** — Valida `telar` (required), `ancho` (numeric ≥0), `hilo` (string max:50), `calibre` (nullable). Crea un registro especial **REPASO1** en el telar (deriva salón con `derivarSalon`: telar ≥299 → SMIT, si no JACQUARD), marcando `Ultimo=1`, heredando calendario/cuenta/calibre del registro anterior, calculando `CambioHilo`, eficiencia/velocidad (`TejidoHelpers::buscarStdEficiencia/Velocidad`, defaults 0.78 / 280), `FechaInicio` = FechaFinal del anterior (snap a calendario) y `FechaFinal` = +12 h. Aplica fórmulas (`DuplicarTejido::calcularFormulasEficiencia`), invoca el observer manualmente para generar líneas, y devuelve JSON con el registro creado. Maneja el dispatcher manualmente y resuelve el registro post-commit (`resolverRegistroRepasoTrasCommit`, por visibilidad SQL Server). *(privados)*: `derivarSalon`, `obtenerRegistroAnterior`, `crearRegistroRepaso`, `obtenerEficienciaVelocidad`, `aplicarFormulas`.

### 3.12 `OrdenDeCambio\Felpa\OrdenDeCambioFelpaController`

Generador del **Excel de "Orden de Cambio de Modelo"** (PhpSpreadsheet) a partir de una plantilla fija `ordfelpa.xlsx` (junto al controller). Soporta 3 formatos: `felpa`, `smit`, `jacquard`. Constante `PRINT_AREA_FORMATO='A1:P105'`; cache estática `$modeloCodificadoCache`.

- **`generarExcelDesdeBD(iterable $registros)`** — Genera el Excel directamente desde modelos `ReqProgramaTejido` (usado por `LiberarOrdenesController` y `ReimprimirOrdenesController`). Carga plantilla, crea/llena la hoja `REGISTRO` (mapeo BD→fila en `mapearDatosBDaRegistro`), calcula Repeticiones (respeta `$registro->Repeticiones`; fallback fórmula `floor((41.5/PesoCrudo)/NoTiras × 1000)`), No. Marbetes (`SaldoMarbete`), mts_rollo y toallas_rollo. Crea/actualiza `CatCodificados` (`crearOActualizarModeloCodificado`), copia la hoja plantilla por registro, llena fórmulas que referencian `REGISTRO!` (`llenarFormatoEnHoja`, protected), y descarga (`streamDownload`).
- **`generarExcel(Request)`** — Variante que **lee** la hoja `REGISTRO` ya poblada del Excel subido (`plantilla`) y genera un talón por fila (no toca BD). Misma maquinaria de hojas/fórmulas.
- *(protected/privados relevantes)*: `llenarFormatoEnHoja`, `establecerCeldasAuxiliares`, `cargarPlantillaExcel`, `obtenerTodosLosRegistros`, `determinarTipoFormato`/`determinarTipoFormatoDesdeBD`, `generarNombreHoja`, `leerDatosDesdeHojaRegistro` (mapea ~90 columnas A..CT), `generarTablaRegistro` (encabezados de `REGISTRO`), `establecerFormulaCelda`, `establecerPlanoDobladilloFormulas`, `establecerHilosFormulas`, `establecerCenefaTramaFormulas`, `configurarAreaImpresion`/`escalarAlturaFilas`, `formatearFecha`, `obtenerHoraActual`, `limpiarTextoParaExcel`/`setCellValueSeguro`.

> **`generarPDF`**: referenciado en rutas pero **no implementado** en este controller (ver nota en §2.2).

---

## 4. Services, funciones/ y helper/ del ámbito

### 4.1 `funciones/` (lógica de operaciones)

**`EditTejido`** — `editar(int $id)`: `findOrFail` del registro + modelo codificado por `TamanoClave`; devuelve vista `modulos.programa-tejido.programatejidoform.edit`.

**`UpdateTejido`** — `actualizar(Request, int $id)`: actualización inline. Normaliza strings vacías a null, valida ~22 campos (hilo, calendario_id, tamano_clave, no_produccion max:80, rasurado, pedido, programar_prod/entrega_*/fecha_final como date, etc.). Aplica cambios con flags `$afectaCalendario/$afectaDuracion/$afectaFormulas/$afectaAplicacion`. Si cambia `tamano_clave` y el salón es Jacquard/Smit, vuelve a leer datos del modelo codificado (`obtenerDatosModeloCodificado`, busca en otros salones con `buscarClaveModeloEnOtrosSalones`). Recalcula fechas/fórmulas (`recalcularSoloDiffDias`, `calcularFormulasEficiencia`) y actualiza líneas (`actualizarAplicacionEnLineas`). Respuesta JSON.

**`EliminarTejido`** — Borrado con reglas de negocio:
- `eliminar(int $id)`: rechaza `EnProceso=1` (422). Si `Reprogramar ∈ {1,2}` → `moverEnLugarDeEliminar` (1 = al siguiente, 2 = al último). Si tiene `OrdCompartida` → `eliminarConOrdCompartida` (transfiere `TotalPedido` al líder/receptor y recalcula). Si nada de eso, sella `FechaFinaliza` cuando hay `NoProduccion` (sincroniza con `MovimientoDesarrolladorService`), audita (`AuditoriaHelper::logEvento DELETE`), borra (líneas por `ON DELETE CASCADE`), recalcula posiciones (`TejidoHelpers::recalcularPosicionesPorTelar`) y fechas (`DateHelpers::recalcularFechasSecuencia`).
- `eliminarEnProceso(int $id)`: borra el registro `EnProceso=1`; el siguiente del telar pasa a EnProceso, se sella `FechaFinaliza`, se registra auditoría de utilería (`OrdenFinalizadaAuditoria::registrarUtileriaFinalizar`) y se recalcula la secuencia desde `now()`.
- *(privados)*: `moverEnLugarDeEliminar`, `eliminarConOrdCompartida`, `recalcularFechasYFormulas`, `registrarAuditoriaDeletePrograma`.

**`DragAndDropTejido`** — `mover(Request, int $id)`: valida `new_position`. Rechaza `EnProceso=1` (422). Llama `moverAposicion` (transacción, reordena la colección del telar en memoria, recalcula fechas, aplica posiciones con offset +10000 para no chocar el índice único, regenera líneas) y registra auditoría drag&drop. Helpers privados de validación: `obtenerRegistrosBloqueadosPorTelar`, `validarCantidadRegistros`, `obtenerInicioOriginal`, `obtenerIndiceRegistro`, `validarPosicionPermitida` (no antes de un EnProceso), `validarRangoPosicion`, `reordenarColeccion`.

**`DuplicarTejido`** — `duplicar(Request)`: duplica el registro origen a N destinos (modo duplicar o **vincular** si `vincular=true`). Usa `$request->validated()` (`DuplicarTejidoRequest`). Pre-batch para evitar N+1 (últimos por telar, flags `Ultimo`, posiciones, modelos codificados, `TwFlogsCustomer` en `sqlsrv_ti`). Por destino: replica el registro, aplica datos del modelo si cambia `tamano_clave` (`aplicarDatosModeloCodificado`), resuelve AplicacionId/FibraRizo/FlogsId/CustName/CategoriaCalidad, calcula TotalPedido/SaldoPedido (`%` segundas), STD desde catálogos, FechaInicio = FechaFinal del último del destino, FechaFinal por horas (`TejidoHelpers::calcularHorasProd` + `BalancearTejido::calcularFechaFinalDesdeInicio`), fórmulas (`calcularFormulasEficiencia`), posición consecutiva. Si vincula, asigna `OrdCompartida` y recalcula líder (`OrdCompartidaHelper`). Regenera líneas dentro de la transacción. Métodos públicos: `duplicar`, `calcularFormulasEficiencia`. Privados: `obtenerUltimoRegistroTelar`, `calcularSiguientePosicion`, `aplicarDatosModeloCodificado`.

**`DividirTejido`** — `dividir(Request)`: divide el **pedido** del registro origen entre varios telares (todos comparten `OrdCompartida` = NoProduccion del origen). Ajusta el `TotalPedido` del original restando los nuevos, crea registros nuevos con datos del modelo por destino (incluye decenas de campos técnicos por fila), asigna líder por FechaInicio más antigua (`VincularTejido::actualizarOrdPrincipalPorOrdCompartida`). Soporta **redistribución** de un grupo existente (`redistribuirGrupoExistente`, privado) cuando llega `ord_compartida_existente`. `calcularTotalesDividir(Request)`: endpoint público (ruta `/programa-tejido/calcular-totales-dividir`) que calcula totales/saldos para previsualización del modal. Privados: `calcularFormulasEficiencia`, `obtenerModeloCodificadoPorSalon`, `aplicarModeloCodificadoPorSalon`, `construirMaquina`, `calcularHorasProd`.

**`VincularTejido`** — Agrupación bajo `OrdCompartida`:
- `vincularRegistrosExistentes(Request)`: valida `registros_ids` (array min:2, cada uno exists). El primero es el líder propuesto y **debe tener `NoProduccion`** (422 si no). Asigna `OrdCompartida` (=NoProduccion del líder) a todos, recalcula líder por fecha más antigua, actualiza `OrdPrincipal` y sincroniza a `CatCodificados`.
- `desvincularRegistro(Request, $id)`: si hay 2 registros, ambos quedan sin `OrdCompartida`; si hay más, solo el seleccionado se desvincula y se re-elige líder entre los restantes. Limpia/actualiza `CatCodificados`.
- `actualizarOrdPrincipalPorOrdCompartida(int $ordCompartida)` *(pública estática)*: pone `OrdPrincipal` = ItemId del líder en todo el grupo (programa y `CatCodificados`).
- Privados: `buildCatCodificadosQueryOrdenTelar` (resuelve columnas dinámicas de CatCodificados vía `Schema::getColumnListing`), `actualizarOrdCompartidaEnCatCodificados`, `limpiarOrdCompartidaEnCatCodificados`.

**`BalancearTejido`** — Motor de balanceo y de cálculo de fechas contra calendario:
- `previewFechas(Request)`: valida `cambios[]` (id, total_pedido, modo saldo/total) + `ord_compartida`. Simula en memoria el guardado (PASO1 + cascada) y devuelve fechas/saldos previstos **sin persistir** (`previewSimularGuardadoBalanceo`, privado).
- `actualizarPedidos(Request)`: igual validación, **persiste**: ajusta TotalPedido/SaldoPedido (`resolverTotalSaldo`), recalcula fechas exactas y propaga cascada por telar; valida membresía al grupo (`validarCambiosPertenecenOrdCompartida`).
- `balancearAutomatico(Request): JsonResponse`: reparte cantidades para alcanzar una fecha objetivo (clasifica registros, computa nuevos pedidos por horas disponibles, `calcularPedidoParaFechaObjetivo`, `calcularHorasDisponiblesHastaFecha`).
- `getRegistrosPorOrdCompartida(int $ordCompartida)`: JSON con los registros del grupo.
- `resolverInicioFin(Carbon, ReqProgramaTejido, bool)`, `recalcularRegistroPorProduccion(ReqProgramaTejido): bool`, `iterarLineasActivas(...)`, `calcularFechaFinalDesdeInicio(string $calendarioId, Carbon, float $horas): ?Carbon`, `clearCalendarioLinesCache()`: utilidades públicas del motor de calendario (snap a turnos, cálculo de fin a partir de horas efectivas). Cachés de calendario warm-eados (`warmCachesFromProgramas`, `getCalendarioLines`).

**`ProgramaPrioridadService`** (`app/Services/Programas/ProgramaPrioridadService.php`) — Servicio genérico de prioridades (usado por programa/muestras vía la columna `Prioridad`):
- `loadRecordsWithOptionalPriority($modelClass, $baseColumns, $scope)`: intenta cargar con columna `Prioridad`; si falla, sin ella (`has_priority`).
- `sortRecords(Collection, callable)`: ordena por prioridad y fallback.
- `displayPriority(object, int)`, `swapPriorities($modelClass, $sourceId, $targetId)`, `bulkUpdatePriorities($modelClass, $priorities)`, `nextPriority(Builder)`, `recalculatePriorities(Builder, callable)`. Privados: `hasPriority`, `normalizePriority`.

### 4.2 `helper/`

**`TejidoHelpers`** — Núcleo de cálculo. Constantes: `DEFAULT_DURACION_DIAS=30`, `DEFAULT_DURACION_REPASO_HORAS=12`, `FORMULAS_CTX_BALANCEAR`, `FORMULAS_CTX_PEDIDO_INHERIT`. Métodos: `obtenerSiguientePosicionDisponible($salon,$telar)`, `recalcularPosicionesPorTelar($salon,$telar)`, `sanitizeNumber`/`sanitizeNullableNumber`, `construirMaquinaConSalon`/`construirMaquinaConBase`, `calcularHorasProd`/`calcularHorasProdFromParams`, `esRepaso`, `resolverDiasEntrega`, `calcularFormulasEficiencia`/`calcularFormulasEficienciaPorContexto`, `snapInicioAlCalendario`, `aplicarStdDesdeCatalogos`, `resolverTipoTelarStd` (SMIT/JACQUARD), `resolverDensidadStd`, `buscarStdVelocidad`/`buscarStdEficiencia` (catálogos `ReqVelocidadStd`/`ReqEficienciaStd`), `obtenerModeloPorTamanoClave`, `obtenerModeloParams`, `obtenerDatosModeloCodificadoArray`.

**`DateHelpers`** — `setSafeDate`, `recalcularFechasSecuencia(Collection, Carbon)`: **función central** que recalcula FechaInicio/FechaFinal en cadena para todos los registros de un telar (devuelve `[updates, detalles]`), `cascadeFechas(ReqProgramaTejido)`, `snapInicioAlCalendario`, `calcularFechaFinalDesdeInicio`. Privados: `calcularMetricasBase`, `calcularFormulasEficiencia`, `obtenerTotalModelo`, `sanitizeNumber`.

**`UpdateHelpers`** — Aplicación de campos al modelo: `applyInlineFieldUpdates`, `applyCantidad`, `applyCalculados`, `applyEficienciaVelocidad`, `applyColoresYCalibres`, `applyFlogYTipoPedido` (deriva `TipoPedido` de las 2 letras del flog), `aplicarCamposFormulario`, `aplicarAliasesEnNuevo`, `aplicarFallbackModeloCodificado`.

**`QueryHelpers`** — `getStdValue($tabla,$campo,$key,Request)`, `pluckDistinctNonEmpty($table,$column,$conn)`, `findModeloDestino($salon, ReqProgramaTejido)`, `resolverStdSegunTelar(...)`, `getEficienciaVelocidadStd($fibraId,$telar,$calibreTrama)`.

**`OrdCompartidaHelper`** — `obtenerOrdCompartidaDesdeRegistro($lider): ?int` (= NoProduccion como int), `seleccionarLider(Collection)`, `recalcularLiderYOrdPrincipalPorOrdCompartida(int)`. Privados: `normalizarFechaInicioDia`, `combinarFechaCreacion`, `compararPorFechaInicio`, `compararPorPedidoDesc`.

**`ProgramaTejidoSecuenciaHelper`** — `aplicarUpdatesDesdeRecalculo(array $updates)` (persiste los updates de `recalcularFechasSecuencia`), `regenerarLineasDesdeDetalles(array $detalles)`.

**`ProgramaTejidoObserverHelper`** — `withoutObserver(callable $fn): mixed` (ejecuta el callback con el observer suprimido).

**`UtilityHelpers`** — `getTableColumns(): array` (definición de columnas/labels de la tabla principal), `extractResumen(ReqProgramaTejido)`, `resolveTipoPedidoFromFlog(?string)`, `resolverAliases(Request)`, `marcarCambioHiloAnterior($salon,$telar,$hilo)`, `marcarCambioHiloBulk($salon,$telaresIds,$hilo)`.

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `App\Models\Planeacion\ReqProgramaTejido` | `dbo.ReqProgramaTejido` (override a `dbo.MuestrasPrograma` para Muestras vía config `planeacion.programa_tejido_table`) | `sqlsrv` | `Id` (BIGINT, autoincrement) | `SalonTejidoId`, `NoTelarId`, `Posicion`, `Ultimo`, `EnProceso`, `TamanoClave`, `ItemId`/`InventSizeId`, `FlogsId`, `CalendarioId`, `TotalPedido`/`SaldoPedido`/`Produccion`/`SaldoMarbete`, `FechaInicio`/`FechaFinal`, `NoProduccion`, `OrdCompartida`/`OrdCompartidaLider`/`OrdPrincipal`, `Reprogramar`, `EficienciaSTD`/`VelocidadSTD`, `CambioHilo`, `Prioridad` |
| `App\Models\Planeacion\ReqProgramaTejidoLine` | `dbo.ReqProgramaTejidoLine` (override a `dbo.MuestrasProgramaLine`) | `sqlsrv` | `Id` | `ProgramaId` (FK→ReqProgramaTejido.Id, ON DELETE CASCADE), `Fecha`, `Cantidad`, `Kilos`, `Aplicacion`, `Trama`, `Combina1..5`, `Pie`, `Rizo`, `MtsRizo`, `MtsPie` |
| `App\Models\Planeacion\OrdColProgramaTejido` | `dbo.OrdColProgramaTejido` | `sqlsrv` | `Id`/(UsuarioId,Columna) | `UsuarioId`, `Columna`, `Estado` (índices `UX_OrdColProgramaTejido_Usuario_Columna`, `IX_OrdColProgTej_Usuario_Estado1`) |
| `App\Models\Planeacion\ReqModelosCodificados` | `dbo.ReqModelosCodificados` | `sqlsrv` | `Id` | `TamanoClave`/`ClaveModelo`, `SalonTejidoId`, `ItemId`/`InventSizeId`, `FlogsId`, datos técnicos del modelo |
| `App\Models\Planeacion\Catalogos\CatCodificados` | `dbo.CatCodificados` | `sqlsrv` | `Id` | `OrdenTejido`/`NoProduccion`, `ItemId`/`InventSizeId`, `Departamento`, `CodigoDibujo`, `OrdCompartida`/`OrdCompartidaLider`/`OrdPrincipal`, `UsuarioCrea` |
| `App\Models\Planeacion\ReqCalendarioLine` | `dbo.ReqCalendarioLine` | `sqlsrv` | `Id` | `CalendarioId`, `FechaInicio`, `FechaFin`, `HorasTurno`, `Turno` |
| `App\Models\Planeacion\Catalogos\ReqPesosRollosTejido` | `dbo.ReqPesosRollosTejido` | `sqlsrv` | `Id` | peso de rollo por `InventSizeId`/DEF |
| Catálogos STD (`ReqEficienciaStd`, `ReqVelocidadStd`, `ReqMatrizHilos`, `ReqAplicaciones`) | `dbo.*` | `sqlsrv` | `Id` | estándares por telar/fibra/densidad |
| Tablas AX (lecturas) `TwFlogsTable`, `TwFlogsItemLine`, `TwFlogsCustomer`, `BOMTABLE`, `BOMVERSION`, `INVENTTABLE`, `TwTipoHilo` | `dbo.*` | **`sqlsrv_ti`** (TI_PRO) | — | Flogs, BOM crudo, tipo de hilo |

**`ReqProgramaTejido` — detalles del modelo**: `timestamps=false` (usa `CreatedAt`/`UpdatedAt` manuales). `getTable()` lee el override `planeacion.programa_tejido_table` (clave del soporte Muestras). Métodos estáticos relevantes: `tableName()`, `suppressObservers(): ?object` / `restoreObservers(?object)` (manejo del dispatcher en operaciones masivas), `regenerarLineas(iterable)` (invoca el observer directamente, **bypassa** el guard `shouldRegenerateLines`), `getTelaresPorSalon`/`getTelarEnProceso`/`getSiguienteOrden`/`getOrdenesProgramadas`. Scopes: `salon($salon)`, `telar($noTelar)`, `enProceso(bool)`, `ordenado()` (Salón→Telar→Posicion→FechaInicio), `programadas()`. Casts: `EnProceso`→bool, `Ultimo`/`CambioHilo`→string (puede ser 'UL'/'1'/'0'), fechas a date/datetime, numéricos a float/integer. Relación `lineas()` (hasMany a `ReqProgramaTejidoLine`).

**`ReqProgramaTejidoLine` — detalles del modelo**: `timestamps=false`; PK `Id` (int, autoincrement). `$fillable` = las 15 columnas (`ProgramaId`, `Fecha`, `Cantidad`, `Kilos`, `Aplicacion`, `Trama`, `Combina1..5`, `Pie`, `Rizo`, `MtsRizo`, `MtsPie`). Casts: `Fecha`→date, todos los numéricos→float (REAL en SQL Server), `Aplicacion`→string (VARCHAR(50)). Métodos propios:
- **`getTable(): string`** — Devuelve el nombre de tabla, respetando el override `config('planeacion.programa_tejido_line_table')` (vale `dbo.MuestrasProgramaLine` en el flujo de Muestras; ver §8.7). Si no hay override, retorna `$this->table` (`ReqProgramaTejidoLine`).
- **`tableName(): string`** *(estática)* — Misma resolución de nombre de tabla que `getTable()`, pero invocable sin instancia (la usa el observer y los controllers para `DB::table(...)`).
- **`programa(): BelongsTo`** — Relación inversa hacia `ReqProgramaTejido` (`ProgramaId` → `Id`). Es la usada por `DescargarProgramaController` para componer el TXT (`with('programa')`).
- **`scopePrograma(Builder $q, int $programaId): Builder`** — Filtra `ProgramaId = $programaId`. Consumido por `ReqProgramaTejidoLineController@index` con el filtro `programa_id` (§3.7).
- **`scopeOnDate(Builder $q, string $date): Builder`** — Filtra por día exacto con `whereDate('Fecha', $date)`. Consumido por el filtro `fecha` del listado de líneas.
- **`scopeBetween(Builder $q, string $from, string $to): Builder`** — Rango inclusivo por fecha usando `whereDate('Fecha','>=',$from)` + `whereDate('Fecha','<=',$to)` (evita que el tipo DATE de SQL Server excluya el último día, problema que tendría `whereBetween`). Consumido por los filtros `desde`/`hasta` del listado.
- **`setAttribute($key, $value)`** *(override)* — Normaliza valores numéricos antes de persistir: si `$key` es una de las 12 columnas numéricas (`Cantidad`, `Kilos`, `Trama`, `Combina1..5`, `Pie`, `Rizo`, `MtsRizo`, `MtsPie`) y el valor entrante es cadena vacía `''` o la cadena literal `'null'`, lo convierte a `null` real antes de delegar en `parent::setAttribute()`. `Aplicacion` queda excluido por ser VARCHAR(50). Esto blinda inserts/updates (desde formularios o el `sanitize()` del controller) contra fallos de conversión de tipo en columnas REAL.

---

## 6. Vistas Blade

Vista raíz: **`req-programa-tejido.blade.php`** (extiende `layouts.app`). Compone toda la pantalla. El bloque `@push('scripts')` renderiza `modulos.programa-tejido.scripts.main`, que a su vez incluye `modal.duplicar-dividir` y renderiza los parciales `scripts.state`, `scripts.filters`, `scripts.columns`, `scripts.selection`, `scripts.inline-edit`. La vista raíz incluye además `modal.act-calendarios`, `modal.repaso`, `balancear`, `components.programa-tejido.*` y `components.ui.toast-notification`. `modal.duplicar-dividir` incluye `modal._shared-helpers`.

### 6.1 `req-programa-tejido.blade.php`
**Propósito**: tabla principal del programa (una fila por registro, columnas dinámicas). **Secciones**: cabecera con botones (nuevo, descargar, recalcular fechas, columnas, filtros, drag&drop, balancear), tabla `#mainTable`, empty-state, tablas/ modales incluidos. **Lógica Blade**: closures `$getRegistroId`, `$formatValue` (renderiza checkboxes de `Reprogramar`/`EnProceso`/`Ultimo` y formatea valores por columna). **JS inline**: handler `DOMContentLoaded` que enlaza el botón `#btn-recalcular-fechas` → `POST` a `programa-tejido.recalcular-fechas` (o `muestras.recalcular-fechas`) con SweetAlert de resultado.

### 6.2 `scripts/main.blade.php` (≈3900 líneas — núcleo JS)
**Propósito**: orquesta toda la interacción de la tabla. Define el objeto global `PT` (cache de filas, acciones, dragdrop). **Funciones JS (concisas)**:
- `rewriteUrl(url)` / override de `window.fetch` — reescribe URLs base `/programa-tejido`↔`/muestras` según contexto.
- `qs/qsa/tbodyEl/setNavbarHeightVar/throttle/toast` — utilidades DOM.
- `findDragDropButton()/setDragDropButtonGray(active)` — UI del botón drag&drop.
- `rowMeta(row)/clearRowCache()/normalizeTelarValue/isSameTelar/refreshAllRows()` — caché y metadatos de filas.
- `ddFormatDateTime/ddFormatDateOnly/ddFormatNumber/ddSetCellValue/ddFormatCell(column,raw)` — formateo de celdas tras drag&drop.
- `ddApplyUpdatesToRow(row,updates)/ddReorderRows()/ddSelectRowById(id)` — aplica updates devueltos por el backend.
- `window.updateTableAfterDragDrop(detalles, registroId, updates)` — refresca la tabla tras mover/recalcular (consume `cambiar-telar`/`prioridad/mover`).
- Menú contextual de fila: `hide()/show(e,row)`.
- Menú contextual de columna + filtros: `escapeCSSValue`, `hide/show`, `openFilterModal(columnIndex,columnField)`, `applyColumnFilterManual(field,values)`, `checkFilterMatch`, `updateColumnFilterIcons()`, `updateColumnPinIcons()`, `window.updatePinnedColumnsPositions()`.
- `PT.actions.descargarPrograma()` — abre modal de fecha y `POST` a `programa-tejido.descargar-programa`.
- `PT.actions.abrirNuevo()` — abre el formulario de alta.
- `PT.actions.eliminarRegistro(id)` — confirma y `DELETE` a `/planeacion/programa-tejido/{id}` (con loader global, manejo de fallo); helpers `applyDeleteSuccessDom/performDeleteFetch/handleDeleteFailure/doDeleteWithGlobalLoader`.
- `window.eliminarEnProcesoRegistro(id)` — `DELETE` a `/{id}/en-proceso`.
- `window.desvincularRegistro(id)` — `POST` a `/{id}/desvincular`.
- `window.editarFilaSeleccionada()` — abre edición de la fila seleccionada.
- Drag&drop (objeto interno): `resetTelarCache/buildTelarCache/calcTargetPosition/findClosestRow/setRowDraggable/enable/disable/toggle/isEnabled/decideTargetTelarFromDOM/clearVisualRows/onDragStart/onDragOver/onDrop/onDragEnd` y `procesarMovimientoOtroTelar(registroId,nuevoTelar,targetPosition,nuevoSalon)` (consume `verificar-cambio-telar` + `cambiar-telar`).
- `window.toggleDragDropMode()` — activa/desactiva el modo.
- `updateBalanceBtnState()`, multi-selección: `toggleMultiSelectMode/toggleRowSelection/updateSelectedRowsVisual/updateVincularButtonState`.
- `window.vincularRegistrosExistentes()` + `actualizarRegistrosVinculados(ids,ord)` + `doVincular(ids)` — consume `vincular-registros-existentes` y refresca filas (helpers `getDateType/formatearValor/fetchDetalle`).
- `window.updateTotales()` — recalcula totales del pie de tabla.
- `resetAllView/bindLayoutButtons/restoreSelectionAfterReload/showSavedToastIfAny/window.applyTableFilters/initStoreFromTable` — estado y layout.
- Reprogramar: `procesarSeleccionReprogramar(id,valor,checkbox,texto)` (`POST` a `/{id}/reprogramar`) e `initReprogramarListeners()`.
- Índice de filtros: `buildPTFilterIndex/updatePTFilterIndexRow/removePTFilterIndexRow`.

### 6.3 `scripts/state.blade.php`
**Propósito**: estado de UI (modo edición inline, fila seleccionada). Define la clase `PTStore` (expuesta `window.PTStore`) y expone `window.selectedRowIndex` / `window.inlineEditMode`.

### 6.4 `scripts/filters.blade.php`
**Propósito**: filtros rápidos, por columna y por rango de fechas. **Funciones**: `applyProgramaTejidoFilters()` (aplica todos los filtros a las filas), `checkDateFilters(row)`, `parseDate(str)`, `toggleQuickFilter(key)`, `renderQuickFilterButtons()`, `updateQuickFilterButton/InModal`, `openProgramaTejidoFilterModal()`, `saveDateRangeFilters()`, `closeProgramaTejidoFilterModal()`, `renderActiveFilters()`, `addCustomFilter()`, `removeFilter(index)`, `resetAllFiltersInModal(modalEl)`, `applyAndCloseProgramaTejidoFilterModal()`, `resetAllFilters()`, `updateFilterUI()`, `updateFilterCount()`. Todas expuestas en `window.*`.

### 6.5 `scripts/columns.blade.php`
**Propósito**: visibilidad/anclado (pin) de columnas, agrupadas, persistidas por usuario. **Funciones (concisas)**: `buildFieldToGroupMap/ensureColumnCache/getAllColumnIndices/syncColumnStateSets/getColumnElements/forEachColumnElement/ensureColumnsReady/buildGroupedColumns`; caché `getCachedHiddenFields/setCachedHiddenFields/getCachedPinnedFields/setCachedPinnedFields`; persistencia `loadPersistedHiddenColumns()` (GET `/programa-tejido/columnas/visibles`), `scheduleSaveHiddenColumns()/saveHiddenColumns()` (POST `/programa-tejido/columnas`); `getColumnIndexByField/isRequiredPinnedField/isRequiredPinnedIndex/getColumnGroup/getGroupColumns/tryApplyHiddenFields/applyInitialColumnState/isGroupVisible/toggleGroupVisibility/toggleGroupPin`; modal `getModalStateSet/buildColumnsModalHtml/bindColumnsModalEvents/openColumnsModal/openPinColumnsModal/openHideColumnsModal/updateGroupCheckboxState`; datos `getColumnsData/getPinnedColumns/getHiddenColumns`; acciones `pinColumn/unpinColumn/resetColumnVisibility/initializeColumnVisibility/showColumn/hideColumn/togglePinColumn/clearPinnedStyles/applyPinnedStyles/updatePinnedColumnsPositions/applyDefaultPinsOnce/pinDefaultColumns/promptSavePreset/promptLoadPreset`. Expone varias en `window.*`.

### 6.6 `scripts/selection.blade.php`
**Propósito**: selección visual de filas y estado de botones. **Funciones**: `updateRowSelectionStyles(row,isSelected,inlineActive)`, `setButtonDisabled(id,disabled)`, `updateSelectionButtons({canEdit,canDelete,canViewLines})`, `clearSelectionStyles()`, `selectRow(rowElement,rowIndex)`, `deselectRow()`. Expone `window.selectRow`/`window.deselectRow`.

### 6.7 `scripts/inline-edit.blade.php`
**Propósito**: edición inline de celdas y formateo. **Funciones**: `parseSqlDateTimeLocal(raw)`, `formatDateOnlyDisplay(raw)`, `formatDateTimeDisplay(raw)`, `formatNumber2(raw)`, `formatDateDisplay(isoOrDate)`, `toInputDate(val)` (más la lógica de edición/guardado que consume `programa-tejido.update`).

### 6.8 `scripts/draganddrop/drag-and-drop.blade.php`
Shim de 9 líneas: si `window.toggleDragDropMode` no existe pero `window.PT.dragdrop` sí, lo expone (la lógica real vive en `main.blade.php`).

### 6.9 `modal/duplicar-dividir.blade.php` (≈3300 líneas)
**Propósito**: modal único para **duplicar** y **dividir** un telar (switch de modo). **Secciones**: tabla de destinos editable (clave modelo, flog, telar, pedido, saldo, aplicación), autocompletados, validaciones. **Funciones (concisas)**: `buildTelarValue/parseTelarValue`, `obtenerDetalleBalanceo(id)` (GET `/{id}/detalles-balanceo`), `duplicarTelar(row)` (abre modal), `generarHTMLModalDuplicar({...})`, `initModalDuplicar(...)`, `agregarListenersCalculoAutomatico/recomputeState/aplicarVisibilidadColumnas/calcularSaldoDuplicar`, `buildBaseInfoCells(...)`, `reconstruirTablaSegunModo(esDuplicar)`, `actualizarEstiloSwitch`, autocomplete de clave modelo `buscarClaveModelo/mostrarSugerenciasClaveModelo/seleccionarClaveModelo/cargarDatosRelacionados` (GET `/programa-tejido/datos-relacionados`), `esSalonJacquardOSmit/normalizarClaveModelo/existeClaveEnSalon/actualizarHiddenSalonPorTelar/actualizarTelaresPorClaveModelo`, flogs `cargarOpcionesFlog/mostrarSugerenciasFlog/mostrarSugerenciasFlogConDescripcion/cargarDescripcionPorFlog` (GET `/flog-by-item`, `/descripcion-by-idflog`), por fila `window.setupRowAutocompletadores/cargarDatosRelacionadosRow/cargarEficienciaVelocidadRow` (GET `/eficiencia-velocidad-std`)/`construirMaquinaRow/cargarDescripcionPorFlogRow`, alertas `mostrarAlertaClaveModelo/ocultarAlertaClaveModelo/validarClaveModeloEnSalon`, `bindClaveModeloEditableInput/bindFlogEditableInput`, y `validarYCapturarDatosDuplicar()` (arma el payload y `POST` a `duplicar-telar`/`vincular-telar`/`dividir-saldo`). Incluye `modal._shared-helpers`.

### 6.10 `modal/_shared-helpers.blade.php` (≈1570 líneas)
**Propósito**: helpers compartidos por los modales de duplicar/dividir/vincular y por la actualización de filas sin recargar. **Funciones (concisas)**: formateo de miles `formatMiles/limpiarFormatoMiles/parseNumeroConMiles/a2Decimales/aplicarFormatoMilesInput/aplicarFormatoMilesEnContenedor`; contexto `getModoActual/estaVincularActivado/getRowCellText/getRowTelar/getRowSalon/getCsrfToken/ptDebugLog`; caché telares `obtenerTelaresPorSalonCached`; `ensureFlogsListaLoaded`; util `escapeHtmlPtModal/normalizeSqlDateValue/formatDateCellValue/updateDateCell/getRowCellValue/buildTelarKey`; refresco DOM `actualizarRegistrosPorIds(ids)`, `actualizarTelaresAfectadosDespuesDividir({...})`, `actualizarRegistroOriginalDividir(data,tbody)`; errores de calendario `buildCalendarErrorHtml/buildCalendarWarningHtml`; `redirectToRegistro(data)`; `agregarRegistroSinRecargar(data,{preventReload})` (inserta la nueva fila en la tabla); `construirFilaRegistro(registro,columns)`, `formatearValorCelda(registro,field,value,dateType)`; loaders `showLoading/hideLoading`; `getRowInputs/getProduccionInputFromRow`.

### 6.11 `modal/_dividir.blade.php` (≈1300 líneas)
**Propósito**: lógica específica del modo **dividir** (reparto de saldos entre telares). **Funciones**: `calcularSaldoTotal(row)`, `scheduleCalcularSaldoTotalDebounced/flushCalcularSaldoTotalDebounced`, `sincronizarPedidoTempoYTotal(row,desdeTotal)`, `redistribuirPedidoTotalEntreTelares()`, `actualizarResumenCantidades()`, `obtenerTelaresPorSalonCache(salon)`, `actualizarSelectTelaresParaFila/actualizarSelectSalonesParaFila/actualizarTelaresPorSalonEnFila`, `guardarValoresOriginales(fila)/guardarValoresOriginalesFilaPrincipal/restaurarValoresOriginales`, `calcularSaldoTotalDisponibleOriginal()`, `redistribuirSaldosProporcionalmente(cambio)`, `validarYRedistribuirNuevoRegistro(fila)`, `agregarFilaDividir()`, `cargarRegistrosOrdCompartida(ord)` (GET `/registros-ord-compartida/{ord}`).

### 6.12 `modal/_duplicar-vincular.blade.php` (≈277 líneas)
**Propósito**: lógica específica del modo **duplicar/vincular**. **Funciones**: `obtenerTelaresPorSalonCacheDuplicar(salon)`, `actualizarSelectsTelares(preseleccionarPrimero)`, `cargarTelaresPorSalon(salon,preseleccionarTelar)` (GET `/telares-by-salon`), `agregarFilaDuplicar()`.

### 6.13 `modal/act-calendarios.blade.php` (≈351 líneas)
**Propósito**: modal de **actualización masiva de calendarios**. **Funciones**: `window.abrirModalActCalendarios()` (GET `/all-registros-json`), `window.cerrarModalActCalendarios()`, `cargarCalendariosEnSelect()` (GET `/calendario-id-options`), `cargarRegistrosEnTabla()`, `window.guardarCalendariosSeleccionados()` (POST `/actualizar-calendarios-masivo`).

### 6.14 `modal/repaso.blade.php` (≈243 líneas)
**Propósito**: modal de **alta de repaso**. **Funciones**: `getCsrf()`, `notifyRepaso(msg,type)`, `cargarHilos()` (GET `/hilos-options`), `cargarTelares()` (GET `/telares-all`), `window.abrirModalRepaso(row)`, `window.cerrarModalRepaso()`, `window.crearRepasoEnviar()` (POST `/crear-repaso`).

### 6.15 `balancear.blade.php` (≈1730 líneas)
**Propósito**: modal/panel de **balanceo** con Gantt y edición de pedidos por grupo `OrdCompartida`. **Secciones**: tabla de registros del grupo, inputs de pedido editables, badge de líder, grid Gantt. **Funciones (concisas)**: `setBalanceoPreviewLoading`, fechas `parseFechaBackendALocal/toDateInputValueLocal/formatearFecha/parseNumber/parseSQLDateToMs/parseDateOnlyTimeToMs`, orden `sortRegistrosPorFechaTelar/comparePedidoDesc`, util `getRowById/getInputById`, líder `resolveBalanceoLeader/renderBalanceoLeaderBadge`, total bloqueado `getLockedTotalBalanceo/setLockedTotalBalanceo/getCurrentInputsPayload/hasPedidoChanges/pedidoEfectivoParaCalculo/isBalanceoTotalsBalanced/updateBalanceoTotalVisualState/syncBalanceoGuardarButtonState`, datos `fetchRegistrosOrdCompartida` (GET `/registros-ord-compartida/{ord}`)/`fetchLineasPrograma` (GET `req-programa-tejido-line`)/`prefetchLineas`, Gantt `parseDateISO/formatShort/normalizeToLocalMidnight/getDateKeyLocal/buildDateRange/getCurrentInputsMap/capByDayFromHorasPorDia/mapWithScaledTimeline/renderGanttGrid/renderGanttOrd/updateGanttPreview`, `previewFechasExactas(ord,options)` (POST `/preview-fechas-balanceo`), `window.schedulePreview/calcularTotalesYFechas/actualizarPedidosDesdeTotal`, `window.aplicarBalanceoAutomatico(ord)` (POST `/balancear-automatico`), `actualizarRegistrosBalanceo(ids)`, `guardarCambiosPedido(ord)` (POST `/actualizar-pedidos-balanceo`), `window.recargarGanttOrdCompartida(ord)`, `window.verDetallesGrupoBalanceo(ord)`, `updateBalancearButton(row)`, `window.abrirBalancearDesdeSeleccion()`.

### 6.16 `liberar-ordenes/index.blade.php` (≈2490 líneas)
**Propósito**: pantalla de **liberación de órdenes** (grilla editable: L.Mat, hilo AX, peso rollo, código dibujo, marbetes, etc.). **Secciones**: tabla de registros candidatos (INN), columnas pin/oculta, filtros, autollenado masivo, botón liberar. **Funciones (concisas)**: autollenado `autoFillAllHiloAX/actualizarHiloAXSelect/autoFillAllBomFields/autoFillAllCodigoDibujo/convertirHiloAXaSelect`, BOM `setupBomSelects/setupBomAutocomplete/debounce/fetchBomOptions` (GET `/liberar-ordenes/bom-sugerencias`)/`updateBomDatalists/syncBomFromInput/syncBomOnSelect/applyBomOption`, selección `toggleSeleccionarTodo/updateSelectAllCheckbox/setupRowReferenceClick/updateLiberarHeaderBadges`, menú/columnas `initLiberarContextMenuHeader/openPinColumnsModal/pinColumn/unpinColumn/updatePinnedColumnsPositions/openHideColumnsModal/hideColumn/showColumn`, filtros `toggleFilters/openFiltersModal/addCustomFilter/removeFilter/clearAllFilters/getCellValueForColumn/escapeHtmlExcel/openFilterExcelModal/applyFiltersSilent/applyFilters`, proceso `obtenerRegistrosSeleccionados/validarMetricasProduccionParaLiberar/descargarExcelBase64(data,fileName)/liberarOrdenes()` (POST `/liberar-ordenes/procesar`, descarga Excel base64), numéricos `parseNumeroGrid/bloquearNegativoEnNumero/normalizarNumeroNoNegativo/actualizarSpanNumerico`, felpa `esFilaInventSizeFel/esFilaAjusteFelRollo`, recálculo `recalcularPorPesoRollo(pesoInput)/actualizarTotalRollosYTotalPzas/leerPzasRolloDesdeFila/calcularTotalPzas`. También consume `/liberar-ordenes/tipo-hilo`, `/codigo-dibujo`, `/opciones-hilos`, `/guardar-campos`.

### 6.17 `reimprimir-ordenes/index.blade.php` (≈334 líneas)
**Propósito**: pantalla para **reimprimir** órdenes ya liberadas. **Funciones**: `buscarOrdenes()`, `renderOrdenes()`, `actualizarEstado()`, `seleccionarTodas()`, `deseleccionarTodas()`, `reimprimirOrdenes()` (GET `/reimprimir-ordenes/{id}` → descarga Excel), `base64ToBlob(base64,mime)`.

---

## 7. JS dedicado (`resources/js/programa-tejido/`)

Importados desde `app.js`/`bootstrap.js` y expuestos globalmente.

**`filter-engine.js`** (motor de filtrado puro, sin DOM; `window.PTFilterEngine`):
- `checkFilterMatch(cellValue, filter)` — evalúa un valor de celda contra un operador (`equals/starts/ends/not/empty/notEmpty/contains`).
- `groupFiltersByColumn(filters)` — agrupa filtros por columna (OR intra-columna / AND inter-columna).
- `rowMatchesCustomFilters(rowData, filtersByColumn)` — true si una fila pasa todos los grupos de filtros.
- `dateInRange(dateStr, desde, hasta)` — true si la fecha cae en el rango (solo parte fecha).

**`store.js`** — clase `PTStore` (instancia global `window.PTStore`) para estado optimista de la tabla:
- `getAll()`, `get(id)`, `set(id,data)` (merge), `add(data)` (nuevo registro), `remove(id)`, `subscribe(fn)` (devuelve unsubscribe), `notify()`, `loadFromServer(data)`.
> Nota: `state.blade.php` define **otra** clase `PTStore` inline para estado de UI; conviven en `window.PTStore` según orden de carga.

**`modal-cache-bootstrap.js`** — inicializa el flag de depuración `window.__PT_DEBUG=false` (ponerlo `true` en consola antes de abrir el modal habilita `ptDebugLog`).

---

## 8. Lógica de negocio y reglas

### 8.1 Permisos
- Los controllers de este ámbito **no llaman `userCan()`** internamente; el control de acceso se realiza vía menú/permisos del módulo Planeación/Tejido (`SYSUsuariosRoles`, recordar el typo `reigstrar` en `SYSRoles`). La UI oculta o deshabilita acciones (crear/editar/eliminar) según permisos resueltos por el layout.

### 8.2 Secuenciación, posiciones y fechas
- Cada **telar** tiene su propia secuencia de `Posicion` (1,2,3…). El scope `ordenado()` ordena Salón→Telar→Posicion→FechaInicio.
- Insertar/mover/eliminar **recalcula en cadena** las fechas del telar (`DateHelpers::recalcularFechasSecuencia`): `FechaInicio` del primero se conserva; cada siguiente arranca en la `FechaFinal` del anterior, con **snap al calendario** (turnos) y `FechaFinal` calculada por horas efectivas (`BalancearTejido::calcularFechaFinalDesdeInicio`). Default si no hay horas: +30 días (`DEFAULT_DURACION_DIAS`); repaso: +12 h.
- Para evitar colisiones del **índice único (telar, posición)** durante reordenamientos, se aplican offsets temporales (`Posicion + 10000`, `Id + 1000000`) antes de los valores definitivos.
- **EnProceso**: un registro `EnProceso=1` no se puede mover/eliminar normalmente (422); no se puede colocar otro antes de él. Para borrarlo se usa `destroyEnProceso` (el siguiente pasa a EnProceso, secuencia recalculada desde `now()`).
- **Reprogramar** (solo editable si `EnProceso=1`): valor `1` = al eliminar, mueve el registro al siguiente; valor `2` = al último (en vez de borrarlo).

### 8.3 Cambio de hilo, máquina y STD
- `CambioHilo=1` cuando el `FibraRizo` difiere del registro anterior del telar.
- `Maquina` = prefijo del salón (`SMI`/`JAC`) + número de telar.
- Eficiencia/velocidad **STD** se resuelven por tipo de telar (SMIT/JACQUARD), fibra, calibre de trama y densidad desde `ReqEficienciaStd`/`ReqVelocidadStd` (`TejidoHelpers::aplicarStdDesdeCatalogos`).

### 8.4 OrdCompartida (vinculación / división / balanceo)
- `OrdCompartida` agrupa registros relacionados; vale el `NoProduccion` del líder natural. `OrdCompartidaLider=1` marca el líder (regla: **fecha de inicio más antigua**). `OrdPrincipal` = ItemId (Clave AX) del líder, propagado a todo el grupo y a `CatCodificados`.
- **Vincular**: requiere que el primer registro (líder) tenga `NoProduccion`. **Desvincular**: con 2 registros, ambos quedan libres; con más, se re-elige líder.
- **Dividir** reparte el `TotalPedido` (con `% segundas` para el `SaldoPedido`); valida que la suma no exceda el total original (ajuste proporcional si excede).
- **Balanceo**: redistribuye cantidades dentro de un grupo. `previewFechas` simula sin persistir; `actualizarPedidos` persiste con cascada; `balancearAutomatico` calcula pedidos para alcanzar una fecha objetivo según horas disponibles del calendario.

### 8.5 Liberación de órdenes y fórmulas
- **INN** (filtro de candidatos): se libera lo que cumple `FechaInicio ≤ HOY()+dias` (sesión `liberar_ordenes_dias`, default 10.999) y no tiene `NoExisteBase`.
- **Folio**: `FolioHelper::obtenerSiguienteFolio('Planeacion', 5)` (auto-incrementa al committear) o manual; se valida unicidad de `NoProduccion`/orden de tejido.
- **Fórmulas de producción** (también en el observer): `Repeticiones = TRUNC((PesoRollo/PesoCrudo)/NoTiras × 1000)`; `PzasRollo = Repeticiones × NoTiras` (÷2 si tamaño FEL/felpa); `SaldoMarbete` desde cantidad a producir / tiras / repeticiones; peso de rollo maestro desde `ReqPesosRollosTejido` (90 kg para felpa, 41.5 fallback histórico).
- **BOM crudo**: se resuelve en AX (`sqlsrv_ti`) con `ITEMGROUPID='CRUDO'`, `TwSalon ∈ {SMIT,JACQUARD}`, `ItemId` con sufijo `-1`.

### 8.6 Líneas diarias (ReqProgramaTejidoLine) y observer
- El **observer `ReqProgramaTejidoObserver`** (registrado en `AppServiceProvider`) hook `saved()`: si cambian campos relevantes (`CAMPOS_RELEVANTES`) regenera las **líneas diarias** (`generarLineasDiarias`, explosión día-a-día por calendario: kilos rizo/pie/trama/combinaciones, `MtsRizo`/`MtsPie`), recalcula fórmulas de producción, y **sincroniza** campos clave hacia `CatCodificados` (`CAMPOS_SYNC_CAT_CODIFICADOS`: TamanoClave→ClaveModelo, TotalPedido→Pedido, SaldoPedido→Saldos, PesoCrudo→P_crudo, etc.) cuando existe la fila por `OrdenTejido=NoProduccion`.
- En operaciones masivas el observer se suprime (`suppressObservers`/`restoreObservers`) y las líneas se regeneran explícitamente con `ReqProgramaTejido::regenerarLineas()` (que **bypassa** el guard `shouldRegenerateLines`, necesario porque los modelos refetcheados no tienen `isDirty()`).

**Métodos públicos del observer** (todos reciben `ReqProgramaTejido $programa` y son invocables manualmente desde flujos que usan `saveQuietly()` —que no dispara observers— o desde scripts de mantenimiento masivo):

- **`recalcularFormulasProduccion($programa): void`** — Recalcula y persiste la **cadena completa de fórmulas de producción**. Dentro de `saved()` se dispara solo cuando cambió alguno de los inputs `CAMPOS_RECALC_FORMULA` (`TamanoClave`, `InventSizeId`, `PesoCrudo`, `NoTiras`, `LargoCrudo`, `SaldoPedido`), detectado por `debeRecalcularFormulas()` (privado, vía `wasChanged()`/`isDirty()`). Determina si es felpa por `InventSizeId` (contiene "FEL", `esFelpaInventSize`), obtiene el `PesoRollo` maestro (`obtenerPesoRolloMaestro`: 90 kg fijo si felpa; si no, busca el último `PesoRollo` por `InventSizeId` en `ReqPesosRollosTejido`, fallback "DEF", último fallback 41.5) y calcula: `Repeticiones = TRUNC((PesoRollo/PesoCrudo)/NoTiras × 1000)`, `PzasRollo = round(Repeticiones × NoTiras)`, `MtsRollo = (LargoCrudo × Repeticiones)/100`, aplicando el **ajuste FEL** (`÷2` en `PzasRollo` y `MtsRollo`); luego `TotalRollos = CEIL(SaldoPedido / PzasRollo)`, `TotalPzas = round(TotalRollos × PzasRollo)`, y `SaldoMarbete = NoMarbete = RollosProgramados = TotalRollos`. **Persiste con `UPDATE` directo vía query builder** sobre `ReqProgramaTejido` (filtrando por `Id`, evitando recursión del observer) y propaga las mismas fórmulas a **`CatCodificados`** (filtra por `OrdenTejido = NoProduccion`, con `FechaModificacion`/`HoraModificacion`). Si `PesoCrudo`/`NoTiras` son ≤0 o `Repeticiones`≤0 hace skip (loguea y retorna). Todo el cuerpo está envuelto en `try/catch` (errores se loguean como `warning`, no propagan). No valida permisos.
- **`sincronizarCatCodificados($programa): void`** — Sincroniza hacia **`CatCodificados`** solo los campos del mapeo `CAMPOS_SYNC_CAT_CODIFICADOS` (`TamanoClave`→`ClaveModelo`, `ItemId`→`ItemId`, `TotalPedido`→`Pedido`, `SaldoPedido`→`Saldos`, `FlogsId`→`FlogsId`, `NombreProyecto`→`NombreProyecto`, `PesoCrudo`→`P_crudo`) que **efectivamente cambiaron** en el save (`wasChanged()`). Retorna temprano si `NoProduccion` está vacío o si no hubo cambios. Añade auditoría (`FechaModificacion`, `HoraModificacion`, y `UsuarioModifica` vía `AuditoriaHelper::obtenerUsuarioActual()` si está disponible), **filtra** los cambios contra las columnas reales de la tabla (`Schema::getColumnListing`, defensa ante renombrados) y omite el update si tras el filtro solo quedan campos de auditoría. Hace **update masivo** a *todas* las filas con el mismo `OrdenTejido` (cubre múltiples telares/órdenes del mismo número de producción, p. ej. Balanceo). Envuelto en `try/catch` (warning en error). Es el método que respalda la sincronización descrita arriba en `saved()`; se expone público para flujos `saveQuietly()` (UpdateTejido, importaciones).
- **`regenerateLinesFor($programa): void`** — Atajo público que llama directamente a `generarLineasDiarias($programa)` **saltándose** el guard `shouldRegenerateLines()`. Se usa cuando el caller ya decidió que quiere regenerar las líneas pero `wasChanged()`/`isDirty()` no reflejan el cambio real (p. ej. tras un bulk update por query builder o tras un refetch desde BD). Para los `save()` normales de Eloquent el dispatcher sigue llamando a `saved()` con el guard intacto.
- En SQL Server, un **trigger** de `SYSAuditoria` hace que el driver devuelva un Id de auditoría tras el INSERT; por eso DuplicarTejido/DividirTejido re-resuelven el `Id` real por (salón, telar, orderByDesc Id), y RepasoController re-resuelve el registro tras commit.
- `SimulacionProgramaTejidoObserver`: observer análogo para la tabla de simulación (fuera del flujo de producción real, pero registrado para el modelo de simulación).

### 8.7 Soporte Muestras (middleware)
- `ProgramaTejidoContext` reescribe en runtime `config('planeacion.programa_tejido_table')` y `..._line_table` a `MuestrasPrograma`/`MuestrasProgramaLine` cuando la URI es de muestras. `ReqProgramaTejido::getTable()`/`ReqProgramaTejidoLine::getTable()` leen ese override, de modo que **el mismo código** opera sobre tablas distintas. El frontend reescribe URLs `/programa-tejido`↔`/muestras` (override de `window.fetch` en `main.blade.php`).

### 8.8 Integraciones
- **Excel (PhpSpreadsheet)**: "Orden de Cambio de Modelo" generado desde plantilla `ordfelpa.xlsx` con hoja `REGISTRO` + un talón por orden (formatos felpa/smit/jacquard). Devuelto en streaming o base64 (liberar).
- **CatCodificados**: sincronización bidireccional de campos, OrdCompartida/OrdCompartidaLider/OrdPrincipal y código de dibujo; alimenta a Codificación/Tejido.
- **TXT de scheduling**: `DescargarProgramaController` escribe `\\192.168.2.11\txts\ProgramaTejido.txt` (pipe-delimitado) con las líneas diarias.
- **Comando artisan** `programa-tejido:recalcular-fechas-produccion --all` (invocado por `recalcularFechas`).
- **Auditoría** (`AuditoriaHelper`): drag&drop, cambios de fecha inicio, eliminaciones; `OrdenFinalizadaAuditoria` para finalización en proceso.
- **AX (`sqlsrv_ti`)**: lecturas de Flogs (`TwFlogsTable`/`TwFlogsItemLine`/`TwFlogsCustomer`), BOM (`BOMTABLE`/`BOMVERSION`), items/hilo (`INVENTTABLE`/`TwTipoHilo`).

---

## 9. Notas y limitaciones de la documentación

- Ruta `codificacion.orden-cambio-pdf` → `OrdenDeCambioFelpaController@generarPDF`: **método inexistente** (solo Excel implementado).
- Las clases `funciones/DividirTejido` (1442 líneas), `LiberarOrdenesController@liberar` (~370 líneas) y el generador `OrdenDeCambioFelpaController` (1921 líneas) son muy extensas; se documentaron sus métodos públicos y la mecánica esencial, sin desglosar cada rama interna línea a línea.
- Los blades gigantes (`main` ~3900, `duplicar-dividir` ~3300, `liberar-ordenes` ~2490, `balancear` ~1730, `_shared-helpers` ~1570, `_dividir` ~1300, `columns` ~983, `inline-edit` ~817) se documentan a nivel de propósito/secciones + inventario conciso de cada función JS (1–2 líneas y endpoints consumidos), conforme a lo solicitado.
