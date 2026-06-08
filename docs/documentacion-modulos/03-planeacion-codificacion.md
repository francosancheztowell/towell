# Planeación — Codificación y Modelos

> Generado automáticamente — documentación detallada del módulo

---

## 1. Propósito del módulo

Este ámbito cubre la **codificación de modelos textiles** dentro de Planeación. Es el catálogo maestro de las "recetas" técnicas de cada toalla/modelo (peine, ancho, largo, peso crudo, calibres de rizo/pie/trama, combinaciones de color, pasadas, fórmulas de marbetes, etc.). Existen **dos catálogos paralelos**, gestionados por dos controllers distintos:

1. **Codificación de Modelos (`ReqModelosCodificados`)** — Controlador `CodificacionController`. Es el catálogo "requisición de modelos" usado por el flujo de **Programa de Tejido**: las claves de modelo aquí codificadas (`TamanoClave` / `Clave`) se consumen luego en `ReqProgramaTejido`. Permite alta manual (formulario), duplicado, importación masiva por Excel (formato de 2 filas de encabezado compuesto), y un flujo de "duplicar / importar desde CatCodificados".

2. **Catálogo de Codificación (`CatCodificados`)** — Controlador `CatCodificacionController`. Es un catálogo más amplio (≈130 columnas, incluyendo producción, saldos, segundas, órdenes compartidas, BOM/LMAT, peso de muestra, auditoría). Sirve como **fuente de verdad** de la orden de tejido finalizada/histórica. Desde aquí se puede: recalcular No. de marbetes con la fórmula de Liberar Órdenes, "revivir" una orden hacia Programa de Tejido, gestionar peso de muestra + lista de materiales (LMAT/BOM) sincronizándola entre tablas, balancear órdenes compartidas, y reimprimir órdenes de cambio.

Submódulos / vistas que abarca:
- **Codificación (CatCodificados)** — vista principal tabla (`catcodificacion/index.blade.php`) + partial de subida de Excel.
- **Codificación de Modelos (ReqModelosCodificados)** — usa vistas `catalagos.catalogoCodificacion` y `catalagos.codificacion-form`.

> **Nota:** las antiguas vistas `modulos/modelos/create.blade.php` y `edit.blade.php` (formularios CRUD del modelo legacy `modelos`) fueron **eliminadas** — eran un feature huérfano sin controller que las renderizara y referenciaban rutas `modelos.store` / `modelos.update` inexistentes. Ver "Hallazgos resueltos (2026-06-04)".

Rol en el flujo productivo: la codificación es el paso previo a la **programación de tejido**. Sin la receta codificada no puede liberarse ni programarse una orden. El recálculo de marbetes y el peso de muestra alimentan directamente la logística de corte/marbeteo y el cálculo de saldos de producción.

---

## 2. Rutas

Todas las rutas se cargan dentro de `routes/web.php` bajo el grupo `Route::middleware(['auth'])` → `require routes/modules/planeacion.php`. **No existe middleware de permiso por ruta**; el control de permisos (`userCan`) se realiza dentro de las vistas/menús. Por tanto el middleware efectivo es siempre `auth`.

### 2.1 Codificación de Modelos — `CodificacionController` (prefijo `planeacion/catalogos`)

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/catalogos/codificacion-modelos` | `CodificacionController@index` | auth | Módulo Codificación (vista) |
| GET | `/planeacion/catalogos/codificacion-modelos/create` | `CodificacionController@create` | auth | crear |
| GET | `/planeacion/catalogos/codificacion-modelos/get-all` | `CodificacionController@getAll` | auth | acceso |
| GET | `/planeacion/catalogos/codificacion-modelos/api/all-fast` | `CodificacionController@getAllFast` | auth | acceso |
| GET | `/planeacion/catalogos/codificacion-modelos/estadisticas` | `CodificacionController@estadisticas` | auth | acceso |
| GET | `/planeacion/catalogos/codificacion-modelos/salones-telares` | `CodificacionController@getSalonesYTelares` | auth | acceso |
| GET | `/planeacion/catalogos/codificacion-modelos/flogs-data` | `CodificacionController@getFlogsData` | auth | acceso |
| GET | `/planeacion/catalogos/codificacion-modelos/catcodificados-orden` | `CodificacionController@getCatCodificadosByOrden` | auth | acceso |
| POST | `/planeacion/catalogos/codificacion-modelos/duplicar-importar` | `CodificacionController@duplicarImportar` | auth | crear |
| GET | `/planeacion/catalogos/codificacion-modelos/{id}/edit` | `CodificacionController@edit` | auth | modificar |
| POST | `/planeacion/catalogos/codificacion-modelos/{id}/duplicate` | `CodificacionController@duplicate` | auth | crear |
| GET | `/planeacion/catalogos/codificacion-modelos/{id}` | `CodificacionController@show` | auth | acceso |
| POST | `/planeacion/catalogos/codificacion-modelos` | `CodificacionController@store` | auth | crear |
| PUT | `/planeacion/catalogos/codificacion-modelos/{id}` | `CodificacionController@update` | auth | modificar |
| DELETE | `/planeacion/catalogos/codificacion-modelos/{id}` | `CodificacionController@destroy` | auth | eliminar |
| POST | `/planeacion/catalogos/codificacion-modelos/excel` | `CodificacionController@procesarExcel` | auth | registrar |
| GET | `/planeacion/catalogos/codificacion-modelos/excel-progress/{id}` | `CodificacionController@importProgress` | auth | acceso |
| POST | `/planeacion/catalogos/codificacion-modelos/buscar` | `CodificacionController@buscar` | auth | acceso |
| (redirect 301) | `/planeacion/catalogos/codificacionmodelos` → `/planeacion/catalogos/codificacion-modelos` | — | auth | — |

Nombres de ruta: `planeacion.catalogos.codificacion-modelos`, `planeacion.catalogos.codificacion.create/get-all/all-fast/estadisticas/salones-telares/flogs-data/catcodificados-orden/duplicar-importar/edit/duplicate/show/store/update/destroy/excel/excel.progress/buscar`.

### 2.2 Catálogo de Codificación — `CatCodificacionController` (prefijo `planeacion`)

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/codificacion` | `CatCodificacionController@index` | auth | Módulo Codificación (vista) |
| GET | `/planeacion/codificacion/api/all-fast` | `CatCodificacionController@getAllFast` | auth | acceso |
| GET | `/planeacion/codificacion/api/ordenes-en-proceso` | `CatCodificacionController@ordenesEnProceso` | auth | acceso |
| POST | `/planeacion/codificacion/api/revivir-programa` | `CatCodificacionController@revivirProgramaDesdeCat` | auth | registrar |
| POST | `/planeacion/codificacion/api/recalcular-marbetes` | `CatCodificacionController@recalcularMarbete` | auth | modificar |
| GET | `/planeacion/codificacion/api/catcodificados-por-orden/{ordenTejido}` | `CatCodificacionController@getCatCodificadosPorOrden` | auth | acceso |
| POST | `/planeacion/codificacion/api/actualizar-peso-muestra-lmat` | `CatCodificacionController@actualizarPesoMuestraLmat` | auth | modificar |
| GET | `/planeacion/codificacion/api/registros-ord-compartida/{ordCompartida}` | `CatCodificacionController@registrosOrdCompartida` | auth | acceso |
| POST | `/planeacion/codificacion/excel` | `CatCodificacionController@procesarExcel` | auth | registrar |
| GET | `/planeacion/codificacion/excel-progress/{id}` | `CatCodificacionController@importProgress` | auth | acceso |
| POST | `/planeacion/codificacion/excel-cancel/{id}` | `CatCodificacionController@cancelImport` | auth | registrar |
| GET | `/planeacion/codificacion/orden-cambio-pdf` | `OrdenDeCambioFelpaController@generarPDF` (fuera de scope) | auth | — |
| GET | `/planeacion/codificacion/orden-cambio-excel` | `OrdenDeCambioFelpaController@generarExcel` (fuera de scope) | auth | — |
| GET | `/modulo-codificación` | `CatCodificacionController@index` (alias) | auth | — |

Nombres de ruta: `planeacion.codificacion.index/all-fast/ordenes-en-proceso/revivir-programa/recalcular-marbetes/catcodificados-por-orden/actualizar-peso-muestra-lmat/registros-ord-compartida/excel/excel.progress/excel.cancel/orden-cambio-pdf/orden-cambio-excel` y `modulo.codificacion`.

> **Nota de colisión de nombres:** ambos controllers registran nombres `codificacion.excel`, `codificacion.all-fast`, `codificacion.excel.progress` etc., pero bajo distinto prefijo de grupo (`planeacion.catalogos.` vs `planeacion.`), por lo que los nombres completos resultan distintos.

---

## 3. Controllers

### 3.1 `CodificacionController`
`app/Http/Controllers/Planeacion/CatalogoPlaneacion/ModelosCodificados/CodificacionController.php`

Trabaja sobre el modelo `ReqModelosCodificados` (conexión por defecto `sqlsrv`, tabla `ReqModelosCodificados`). Define dos constantes internas: `COLUMNS` (encabezados visibles) y `CAMPOS_MODELO` (mapa campo→tipo `date`/`zero`/`null`), `DATE_FIELDS` y `REQUIRED_FIELDS = ['TamanoClave','OrdenTejido']`.

#### Funciones públicas

- **`index()`** (`:390`) — Vista principal. Calcula `total` cacheado 5 min (`codificacion_total`) con `ReqModelosCodificados::count()`. Devuelve la vista `catalagos.catalogoCodificacion` con `columnas`, `camposModelo`, `columnasConfig`, `totalRegistros` y `apiUrl` (`/planeacion/catalogos/codificacion-modelos/api/all-fast`). En excepción re-renderiza la misma vista con `total=0` y mensaje de error. **Respuesta:** vista.

- **`create(Request $request)`** (`:416`) — Muestra el formulario `catalagos.codificacion-form`. Si la query trae `?duplicate={id}`, carga el `ReqModelosCodificados` original, copia todos sus atributos (vía `getAttributes()` + `setRawAttributes()`) **menos** `Id`, `created_at`, `updated_at`, marca la instancia como nueva (`exists=false`). Si no existe el original redirige a `codificacion.index` con error. **Respuesta:** vista o redirect.

- **`edit($id)`** (`:454`) — `findOrFail($id)` y devuelve la vista `catalagos.codificacion-form` con el registro. **Respuesta:** vista (404 si no existe).

- **`getAll(): JsonResponse`** (`:461`) — Devuelve **todos** los registros seleccionando `Id` + claves de `CAMPOS_MODELO`, ordenados `Id` desc, vía `toBase()` (sin hidratar Eloquent). Header `Cache-Control: private, max-age=60`. **Respuesta:** `{success, data[], total}`.

- **`getAllFast(Request $request): JsonResponse`** (`:486`) — Carga rápida/compacta. Parámetros: `id` (filtro opcional por Id) y `nocache` (bool). Usa cache (`codificacion_fast_all` o `codificacion_fast_id_{id}`, TTL 60s) salvo `nocache`. Selecciona columnas entre corchetes (`selectRaw`), ordena `Id` desc. Para el listado completo decide entre `fetchWithGet` (<=1000) o `fetchWithCursor` (>1000) según `codificacion_estimated_count` (cache 300s). **Respuesta compacta:** `{s, d:[[...valores]], t, c:[...columnas]}`. Headers `X-Cache: HIT|MISS`.

- **`show($id): JsonResponse`** (`:589`) — `find($id)`; devuelve `{success, data}` o 404 `{success:false, message:'No encontrado'}`.

- **`store(Request $request): JsonResponse`** (`:617`) — Crea. **Validación** vía `getValidationRules(true)`: cada campo de `CAMPOS_MODELO` es `sometimes|nullable` (los de `DATE_FIELDS` además `date`), y `TamanoClave`+`OrdenTejido` son `required`. Crea con `request->only(claves CAMPOS_MODELO)`, limpia caché (`clearCodificacionCache`). **Respuesta:** 201 `{success, message:'Registro creado', data}` o 422 con errores.

- **`update(Request $request, $id): JsonResponse`** (`:640`) — Igual a store pero reglas con `isCreate=false` (sin requeridos). 404 si no existe. Actualiza, limpia caché. **Respuesta:** `{success, message:'Registro actualizado', data}` / 422 / 404.

- **`destroy($id): JsonResponse`** (`:667`) — Elimina. **Regla de negocio:** antes de borrar, si el registro tiene `Clave` (clave mod), verifica en `ReqProgramaTejido` (conexión `sqlsrv`) que no exista ninguna fila con `TamanoClave = Clave`; si está en uso devuelve 422 e impide el borrado. Tras borrar limpia caché. **Respuesta:** `{success, message}` / 422 / 404.

- **`duplicate($id): JsonResponse`** (`:697`) — Replica un registro: toma `getAttributes()`, quita `Id`, hace `create($attributes)`. Limpia caché. **Respuesta:** 201 `{success, data}` / 404 / 500.

- **`procesarExcel(Request $request): JsonResponse`** (`:739`) — Import masivo de `ReqModelosCodificados`. **Validación:** `archivo_excel = required|file|mimes:xlsx,xls|max:10240`. Intenta calcular `totalRows` (vía `XlsReader`/`XlsxReader::listWorksheetInfo`, restando 2 por los encabezados). Encola con `Excel::queueImport(new ReqModelosCodificadosImport($importId, $totalRows), ...)`. **Respuesta:** 202 `{success, data:{import_id (uuid), total_rows, poll_url}}` / 422 / 500.

- **`importProgress($id): JsonResponse`** (`:779`) — Lee `Cache::get('excel_import_progress:'.$id)`. Calcula `percent` (processed/total*100). Mapea hasta los errores almacenados (fila + error truncado a 150). **Respuesta:** `{success, data:state, percent, errors, has_errors}` / 404.

- **`buscar(Request $request): JsonResponse`** (`:805`) — Búsqueda filtrada optimizada por índices. Filtros: `tamano_clave` (LIKE), `salon_tejido` (=), `fecha_desde`/`fecha_hasta` (sobre `FechaTejido`), `orden_tejido` (LIKE), `nombre` (LIKE), `no_telar` (=). Prioriza combinaciones que usan índices compuestos (`IX_RMC_Tamano_Salon`, `IX_RMC_Salon_FechaTejido`). Selecciona `Id`+`CAMPOS_MODELO`. **Respuesta:** `{success, data[], total, mensaje}`.

- **`getSalonesYTelares(): JsonResponse`** (`:883`) — Lee `InvSecuenciaTelares` (conexión `sqlsrv`), columnas `TipoTelar` (=salón) y `NoTelar`, distinct, ordenado. Agrupa telares por salón. **Regla especial:** `ITEMA` y `SMIT` comparten el mismo pool de telares (los telares de ambos se unifican y se asignan a las dos llaves `SMIT` e `ITEMA`). **Respuesta:** `{success, data:{salones[], telaresPorSalon{}}}`.

- **`estadisticas(): JsonResponse`** (`:1000`) — Estadísticas cacheadas 5 min (`codificacion_estadisticas`): `total_registros`, `por_salon` (groupBy `SalonTejidoId` sobre `ReqModelosCodificados`), `por_prioridad` (groupBy `Prioridad`). **Respuesta:** `{success, data}`.

- **`getFlogsData(Request $request): JsonResponse`** (`:1029`) — Consulta **Flogs** en la BD de producción `sqlsrv_ti`: join `dbo.TwFlogsItemLine` × `dbo.TwFlogsTable` por `IDFLOG`, filtrando por `ITEMID` (=`item_id`) e `INVENTSIZEID` (=`invent_size_id`) con TRIM, y `ESTADOFLOG IN (3,4,5,21)`, último IDFLOG. **Parámetros requeridos:** `item_id`, `invent_size_id`. **Respuesta:** `{success, data:{idflog, nombre (NAMEPROYECT), custname}}` / 400 / 404 / 500.

- **`getCatCodificadosByOrden(Request $request): JsonResponse`** (`:1083`) — Busca en `CatCodificados` por la orden de trabajo (`orden_trabajo`/`orden`) usando el helper privado `findCatCodificadoByOrden` (busca por `OrdenTejido`, luego por `NoOrden`). Devuelve datos para precargar un nuevo registro: `orden_trabajo, salon (Departamento), clave_mod (ClaveModelo o Clave), clave_ax (ItemId), tamano (InventSizeId), nombre`. **Respuesta:** `{success, data}` / 422 / 404.

- **`duplicarImportar(Request $request): JsonResponse`** (`:1122`) — Crea N registros de codificación en una transacción. **Validación:** `registro_id_original` (required|integer|exists), `modo` (required|in:duplicar,importar), `datos` (array) con por elemento `orden_trabajo` (required_if importar), `salon`, `clave_mod`, `clave_ax`, `nombre`, `tamano` (todos required string). Para cada `dato`:
  - **modo `importar`:** busca CatCodificados por la orden (`findCatCodificadoByOrden`); si no existe, hace rollback y devuelve 404. Mapea con `mapCatCodificadosToReq`, sobreescribe los campos del formulario, opcionalmente `FlogsId` (idflog) y `NombreProyecto`/`CustName` (custname, si la columna existe), normaliza+trunca (`normalizeDataForTable`) y guarda con `forceFill`.
  - **modo `duplicar`:** `replicate()` del original, actualiza los 5 campos del formulario (truncados) + opcional FlogsId/NombreProyecto/CustName, guarda.
  - Al final `DB::commit()`, limpia caché. **Respuesta:** `{success, message:'N registro(s) creado(s)', registros_ids[]}` / 422 / 404 / 500.

#### Funciones privadas / estáticas relevantes
- `clearCodificacionCache(?int $id)` (`:158`) — olvida `codificacion_total`, `codificacion_fast_all`, `codificacion_estimated_count` y opcional `codificacion_fast_id_{id}`.
- `getColumnMaxLengths($table)` / `getTableColumns($table)` (`:168`,`:191`) — leen `INFORMATION_SCHEMA.COLUMNS` (cache 3600s) para truncado y validación de columnas existentes.
- `normalizeDataForTable()` / `truncateValueForColumn()` (`:209`,`:358`) — filtran a columnas reales y truncan strings a su longitud SQL.
- `findCatCodificadoByOrden($orden)` (`:221`) — resuelve un CatCodificados por `OrdenTejido` o `NoOrden`.
- `mapCatCodificadosToReq(CatCodificados $cat)` (`:236`) — mapeo extenso (~100 campos) de columnas CatCodificados → campos ReqModelosCodificados.
- `getColumnasConfig()` / `getCamposModelo()` (estáticas, `:378`,`:384`) — exponen config de columnas/campos a las vistas/JS.
- `getValidationRules(bool $isCreate)` (`:598`) — genera reglas dinámicas.
- `fetchWithGet()` / `fetchWithCursor()` (`:565`,`:577`) — estrategias de lectura.

### 3.2 `CatCodificacionController`
`app/Http/Controllers/Planeacion/CatCodificados/CatCodificacionController.php`

Trabaja sobre `CatCodificados` (conexión por defecto `sqlsrv`, tabla `CatCodificados`), `ReqProgramaTejido` y `ReqModelosCodificados`. Inyecta por constructor `CatCodificadosExcelHeaderMapper` y `SaldoMarbeteCodificacionService`. Constante `INLINE_IMPORT_ROW_THRESHOLD = 20`.

#### Funciones públicas

- **`index()`** (`:38`) — Vista principal `catcodificacion.index`. Pasa `columnas = CatCodificados::COLUMNS`, `totalRegistros` (cache `catcodificacion_total`, 300s), `apiUrl = /planeacion/codificacion/api/all-fast`. En excepción re-renderiza con total 0 y error. **Respuesta:** vista.

- **`procesarExcel(StoreCatCodificadosExcelRequest $request): JsonResponse`** (`:70`) — Import de CatCodificados con validación previa de encabezados. Lee la fila de encabezado (`readHeaderRow`), la mapea con `headerMapper->map()`; si hay `errors` de plantilla devuelve 422 con `errors.headers` (col, letra, esperado, actual). Calcula `totalRows` (`resolveTotalRows`, contando filas con datos significativos). **Decisión inline vs cola:** si `totalRows <= 20` → `CatCodificadosImport` ejecutado con `Excel::import` (síncrono); si no → `QueuedCatCodificadosImport` con `Excel::queueImport`. **Respuesta:** 202 `{success, message, data:{import_id, total_rows, completed, queued, summary, poll_url, cancel_url}}` / 422 / 500.

- **`ordenesEnProceso(Request $request): JsonResponse`** (`:153`) — Lista órdenes "en proceso" de `ReqProgramaTejido` (scope `ordenado()`), proyectando `Id, NoProduccion, NoTelarId, SalonTejidoId, ItemId, NombreProducto`; filtra los que tienen `NoProduccion`. Alimenta el select de Orden Tejido del modal Peso Muestra. **Respuesta:** `{s:true, d:[...]}`.

- **`revivirProgramaDesdeCat(Request $request): JsonResponse`** (`:189`) — **Validación:** `cat_id` (required|integer|min:1), `en_proceso` (nullable|boolean). Delega en `RevivirOrdenProgramaDesdeCatService::ejecutar(cat_id, en_proceso)` (servicio fuera del scope estricto): pone `FechaFinaliza = null` en CatCodificados y crea la orden en `ReqProgramaTejido` (al final de la cola o en proceso). **Respuesta:** `{s:true, d:resultado}` / 422 / 500.

- **`recalcularMarbete(Request $request)`** (`:229`) — **Validación:** `ids` (required|array|min:1), `ids.*` (integer|min:1). Para cada CatCodificados encontrado, calcula `NoMarbete` con `SaldoMarbeteCodificacionService::calcularParaCatCodificados()`; redondea a 0 decimales, guarda `cat->NoMarbete`. Acumula resultados (`anterior`/`nuevo`). Si ninguno fue exitoso devuelve 422 con el primer error. Limpia caché estática (`clearCache`). **Respuesta:** `{s:true, d:{resultados}, message:'N actualizado(s)'}` / 422 / 500.

- **`getCatCodificadosPorOrden(string $ordenTejido): JsonResponse`** (`:312`) — Para el modal Peso Muestra. Busca CatCodificados por `OrdenTejido` (proyecta `OrdenTejido, TelarId, ItemId, InventSizeId, Nombre, ClaveModelo, ActualizaLmat, PesoMuestra, BomId, BomName`). Normaliza booleano `ActualizaLmat`, peso, BomId/BomName. Consulta la lista LMAT en `sqlsrv_ti` (`queryLmatDesdeTi`) y, si tenía `BomId` sin `BomName`, lo completa desde TI. **Respuesta:** `{s:true, d:{...incluye listaLmat[]}}` (o `d:null` si no hay registro) / 400 / 500.

- **`actualizarPesoMuestraLmat(Request $request): JsonResponse`** (`:386`) — **Validación:** `ordenTejido` (required string), `pesoMuestra` (nullable|numeric|min:0), `actualizaLmat` (required boolean), `bomId` (nullable|string|max:20). Si `actualizaLmat` está desactivado fuerza `bomId` y `bomName` a null. Si hay `bomId`, obtiene `bomName` desde `sqlsrv_ti` (vía `queryLmatDesdeTi`). **Propaga la actualización a 3 tablas (`sqlsrv`):**
  1. `CatCodificados` (por `OrdenTejido`): PesoMuestra, ActualizaLmat, BomId, BomName.
  2. `ReqProgramaTejido` (por `NoProduccion = ordenTejido`): mismos campos.
  3. `ReqModelosCodificados` (por `OrdenTejido`): solo PesoMuestra.
  Limpia `catcodificacion_fast_all`. **Respuesta:** `{s:true, message, actualizados[]}` / 422 / 500.

- **`getAllFast(Request $request): JsonResponse`** (`:563`) — Carga rápida de la tabla. Parámetros `id` y `nocache`. Cache (`catcodificacion_fast_all`/`catcodificacion_fast_id_{id}`, TTL 60s). Selecciona `CatCodificados::COLUMNS` ordenado por `Id` desc; transforma cada registro en arreglo posicional. Headers `no-store`/`X-Cache`. **Respuesta:** `{s, d:[[...]], t, c:[columnas]}` / 500.

- **`registrosOrdCompartida(string $ordCompartida): JsonResponse`** (`:665`) — Para el modal Balancear. Devuelve todos los CatCodificados con la misma `OrdCompartida` (busca como int si es numérico). Ordena líderes primero (`OrdCompartidaLider` desc, `Id` asc). Proyecta `OrdenTejido, TelarId, Nombre, ClaveModelo, Cantidad, Produccion, Saldos, TotalSegundas, OrdCompartida, OrdCompartidaLider`. Calcula `tieneLideres`. **Respuesta:** `{success, message, registros[], tieneLideres}` / 400 / 500.

- **`importProgress(string $id): JsonResponse`** (`:771`) — Lee `excel_import_progress:{id}`. Calcula `percent`, formatea errores (fila + error a 150). **Respuesta:** `{success, data:state, percent, errors, has_errors}` / 404.

- **`cancelImport(string $id): JsonResponse`** (`:809`) — Cancela un import encolado: marca `excel_import_cancelled:{id}=true` (1h), actualiza el estado a `cancelled`, y borra jobs pendientes de la cola (`deletePendingQueuedImportJobs`). Si ya estaba `done` devuelve 409. **Respuesta:** `{success, message, data:{import_id, status, deleted_jobs}}` / 404 / 409.

#### Funciones estáticas / privadas relevantes
- `clearCache(?int $id)` (`:748`) — olvida `catcodificacion_fast_id_{id}`, `catcodificacion_fast_all`, `catcodificacion_estimated_count`, `catcodificacion_total`.
- `progressCacheKey($id)` / `cancellationCacheKey($id)` (`:758`,`:763`) — claves de cache `excel_import_progress:{id}` y `excel_import_cancelled:{id}`.
- `queryLmatDesdeTi(string $itemId, ?string $inventSizeId)` (`:512`) — consulta LMAT en `sqlsrv_ti`: join `BOMTABLE` × `BOMVERSION` por `BOMID`, filtra `BV.ITEMID = itemId.'-1'`, `BT.ITEMGROUPID='CRUDO'`, `BT.TwSalon IN ('SMIT','JACQUARD')` y opcional `BT.TWINVENTSIZEID`. Devuelve `[{bomId, bomName}]` (máx 50). Misma lógica que LiberarOrdenesController.
- `readHeaderRow` / `resolveTotalRows` / `makeSpreadsheetReader` / `shouldProcessInline` / `buildImportSummary` / `deletePendingQueuedImportJobs` / `rowHasMeaningfulData` / `isNullLikeSpreadsheetValue` — utilidades del flujo de import (lectura de encabezados, conteo de filas con datos, decisión inline, resumen, limpieza de jobs).
- `fetchWithGet` / `fetchWithCursor` (`:633`,`:649`) — utilidades declaradas (no usadas en el `getAllFast` actual).

---

## 4. Services y Helpers del ámbito

### 4.1 `SaldoMarbeteCodificacionService`
`app/Services/Planeacion/SaldoMarbeteCodificacionService.php`. Replica la fórmula de **Liberar Órdenes** para No. de marbetes sobre `CatCodificados`. Constantes: `PESO_ROLLO_KG_FELPA = 90.0`, `PESO_ROLLO_FALLBACK_KG = 41.5`. Depende del modelo `ReqPesosRollosTejido` (catálogo de pesos de rollo por `InventSizeId`).

- **`calcularParaCatCodificados(CatCodificados $c): array`** (público) — Devuelve `{ok, valor, message}`. **No persiste.** Toma `Pedido ?? Cantidad`, `NoTiras`, `P_crudo`; valida que todos sean numéricos > 0 (si no, `ok=false` con mensaje y referencia corta). Obtiene peso de rollo (`obtenerPesoRollo`), calcula repeticiones y luego el saldo de marbetes; aplica ajuste FEL/Felpa. Si el resultado es ≤ 0 → `ok=false`.

Privadas (lógica de negocio, ver sección 8 para fórmulas):
- `referenciaCorta()` — arma la referencia "(Cat Id N — Nombre / ItemId)" para mensajes.
- `esTamanoFelpa()` — true si `InventSizeId`, `Nombre` o `ClaveModelo` contienen "FELPA".
- `esInventSizeFel()` — true si `InventSizeId` contiene "FEL".
- `debeAplicarAjusteFormatoFelRollo()` — true si es Felpa o InventSize FEL.
- `obtenerPesoRollo()` — 90.0 si es Felpa; si no, busca por `InventSizeId` exacto, luego por `'FEL'`, luego por `'DEF'`, y por último `41.5`.
- `obtenerPesoRolloPorInventSizeId()` — lee `ReqPesosRollosTejido` (último por FechaModificacion/FechaCreacion/Id).
- `repeticionesDesdePesoRollo()` — `TRUNCAR((pesoRollo / P_crudo) / tiras * 1000)`.
- `saldoMarbeteDesdeFormula()` — `REDONDEAR((cantidad / tiras) / repeticiones, 0)`.
- `aplicarAjusteFelSaldoMarbete()` — duplica el resultado (`× 2`) si aplica FEL/Felpa.

### 4.2 `CatCodificadosExcelHeaderMapper`
`app/Services/Planeacion/CatCodificados/Excel/CatCodificadosExcelHeaderMapper.php`. Define la **plantilla esperada** del Excel de CatCodificados (≈94 columnas con header literal y campo destino; algunas columnas son `optional` o `field:null` —se ignoran al mapear—). Genera la letra de columna con `Coordinate::stringFromColumnIndex`.

- **`template()`** (privado) — arreglo de definiciones `{header, field, column, optional?}`.
- **`expectedHeaders(): array`** — lista de encabezados esperados.
- **`expectedColumns(): array`** — lista de letras de columna (A, B, …) usada para el conteo de filas.
- **`map(array $headers): array`** — compara los encabezados reales contra la plantilla; devuelve `{columnMap: [indice→campo], errors: [{column, column_letter, expected, actual}]}`. Solo genera `columnMap` para definiciones con `field` no nulo.
- **`matches()` / `normalize()`** (privados) — normalizan a ASCII minúsculas sin signos (`Str::ascii`) para comparar headers de forma tolerante; una celda vacía coincide solo si la definición es `optional`.

### 4.3 `CatCodificadosExcelRowMapper`
`app/Services/Planeacion/CatCodificados/Excel/CatCodificadosExcelRowMapper.php`. Convierte cada fila del Excel (según `columnMap`) en un payload tipado para `CatCodificados`. `MAX_TEXT_LENGTH = 2040`.

- **`map(array $row, array $columnMap): array`** (público) — recorre el `columnMap`, limpia cada valor según el tipo del campo. Si el payload queda vacío devuelve `[]`. **Derivaciones automáticas:** si hay `FibraId` sin `ColorTrama`, copia; idem `FibraComb1→NomColorC1` … `FibraComb5→NomColorC5`.

Privadas: `cleanValue` (despacha por tipo), `parseDate` (Excel serial o múltiples formatos d/m/Y, etc.), `parseTime` (H:i:s), `parseBool` (si/sí/yes/true/1 → 1), `parseInt`, `parseFloat`/`parseFloatForField` (precisión por campo: `CalibreRizo`,`CalibrePie2` → 1 decimal), `cleanText` (colapsa espacios, trunca), `isNullLike` (`'', na, n/a, null, -, nan`), `normalizeNumericString` (quita comas), `stringifyNumber`. Conjuntos de campos: `INT_FIELDS`, `FLOAT_FIELDS`, `DATE_FIELDS`, `TIME_FIELDS`, `BOOL_FIELDS`.

### 4.4 Requests
- **`StoreCatCodificadosExcelRequest`** (`app/Http/Requests/Planeacion/StoreCatCodificadosExcelRequest.php`) — `authorize() = true`; regla única `archivo_excel = required|file|mimes:xlsx,xls|max:10240` con mensajes en español.

### 4.5 Imports (Maatwebsite Excel)

- **`ReqModelosCodificadosImport`** (`app/Imports/ReqModelosCodificadosImport.php`) — Import **encolado** (`ShouldQueue`) para `ReqModelosCodificados`. Implementa `ToCollection, WithStartRow(1), WithChunkReading(1000), WithBatchInserts(1000), SkipsEmptyRows, WithEvents`. Maneja un **encabezado compuesto de 2 filas** (combina fila 1 y 2 con `|`) y un **mapa de posiciones de respaldo** (`$pos`, 1-based). Para cada fila desde la 3:
  - Extrae valores con getters tolerantes: `S` (string), `SExact` (clave exacta o normalizada), `SF` (fracciones tipo `5/3` → decimal ×10), `I` (entero), `F` (float), `D` (fecha Carbon).
  - `cleanExcelFormula()` descarta fórmulas Excel (`=…`, funciones, referencias de celda, terminados en `*`) y palabras "sin valor" (TERMO, NORMAL, NO APLICA, etc.).
  - Valida mínimo (`isRowValid`): `TamanoClave` y `OrdenTejido` no vacíos.
  - **Upsert por (`TamanoClave`,`OrdenTejido`)**: update si existe, create si no.
  - Reporta progreso en cache `excel_import_progress:{importId}` (status pending/processing/done, processed/created/updated/errors). Sube `memory_limit` a 1024M en `BeforeImport`.

- **`CatCodificadosImport`** (`app/Imports/CatCodificadosImport.php`) — Import para `CatCodificados`. Implementa `ToCollection, WithStartRow(2), WithChunkReading(500), SkipsEmptyRows, WithEvents`. Usa `CatCodificadosExcelRowMapper` + el `columnMap` recibido. En `BeforeImport`: desactiva el event dispatcher del modelo, sube memoria, `SET NOCOUNT ON` (sqlsrv). Por chunk:
  - Mapea filas a payload; **upsert por `OrdenTejido`** (carga ids existentes con `loadExistingIds`, update por `Id`; los nuevos van a `insert` por lotes con fallback fila a fila).
  - **Soporta cancelación:** `throwIfCancelled()` lanza `ImportCancelledException` si `excel_import_cancelled:{id}` está activo; `failed()` marca status `cancelled`.
  - En `AfterImport`: `SET NOCOUNT OFF`, escribe estado final en cache y llama `CatCodificacionController::clearCache()`.

- **`QueuedCatCodificadosImport`** (`app/Imports/QueuedCatCodificadosImport.php`) — Subclase de `CatCodificadosImport` que añade `ShouldQueue` (variante encolada para archivos grandes).

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `App\Models\Planeacion\ReqModelosCodificados` | `dbo.ReqModelosCodificados` | `sqlsrv` (default) | `Id` (autoincrement) | `TamanoClave`, `OrdenTejido`, `SalonTejidoId`, `NoTelarId`, `Clave`, `ItemId`, `InventSizeId`, `FlogsId`, `Pedido`, `PesoMuestra`, `OrdPrincipal` |
| `App\Models\Planeacion\Catalogos\CatCodificados` | `dbo.CatCodificados` | `sqlsrv` (default) | `Id` (autoincrement) | `OrdenTejido`, `NoOrden`, `TelarId`, `Departamento`, `ItemId`, `InventSizeId`, `ClaveModelo`, `Clave`, `Cantidad`, `NoMarbete`, `Produccion`, `Saldos`, `TotalSegundas`, `OrdCompartida`, `OrdCompartidaLider`, `BomId`, `BomName`, `PesoMuestra`, `ActualizaLmat`, `FechaArranque`, `FechaFinaliza` |
| `App\Models\Planeacion\Catalogos\Modelos` | `modelos` (sin prefijo `dbo.` explícito; conexión default) | `sqlsrv` (default) | (no declarada → `id` por defecto) | `CONCATENA`, `RASEMA`, `CLAVE_MODELO`, `CLAVE_AX`, `Tamanio_AX`, `No_Orden`, `No_De_Marbetes`, `TOTAL`, `COMPROBAR_modelos_duplicados` |

Notas:
- Los tres modelos tienen `public $timestamps = false`.
- `CatCodificados` expone la constante `COLUMNS` (≈140 nombres) que el controller usa para proyectar/serializar.
- `ReqModelosCodificados` define `$casts` (INT, FLOAT y fechas) y `CatCodificados` también (booleans `CreaProd`/`ActualizaLmat`, reales `MtsRollo`/`PzasRollo`/`Densidad`, etc.).
- Tablas adicionales tocadas por los controllers (no son modelos del scope): `ReqProgramaTejido`, `InvSecuenciaTelares`, `jobs` (cola), todas en `sqlsrv`; `dbo.TwFlogsItemLine`, `dbo.TwFlogsTable`, `BOMTABLE`, `BOMVERSION` en `sqlsrv_ti`; `ReqPesosRollosTejido` (vía el service) en `sqlsrv`.

---

## 6. Vistas Blade

### 6.1 `resources/views/catcodificacion/index.blade.php`
**Propósito:** vista principal del catálogo CatCodificados — tabla virtualizada con paginación, columnas fijables, filtros AND por columna, y barra de acciones.

**Secciones UI principales:**
- `@section('navbar-right')`: botones **Subir Excel** (`subirExcelCatCodificacion`), **Peso M** (`mostrarAlertaNavbar`), **Filtrar** (`filtrarCodificacion`), **Balancear** (`abrirModalBalancear`, deshabilitado por defecto), **Reimprimir Orden** (`reimprimirOrdenSeleccionada`), **Revivir a programa** (`revivirOrdenAlPrograma`), **Recalc. marbetes** (`recalcularMarbetesCodificacion`).
- `@section('content')`: overlay de carga, contenedor de tabla con scroll, definición PHP de etiquetas de columna (`$columnLabels`) y orden deseado (`$ordenDeseado`), modales de filtros y menú contextual.
- Incluye el partial `catcodificacion/partials/excel-upload.blade.php`.

**Funciones JS inline (bloque `<script>`, IIFE):**
- `formatDateOnly(val, columnName)` (`:543`) — formatea valores de fecha a solo-fecha para celdas.
- `setLoading(isLoading, message, count)` (`:580`) — muestra/oculta el overlay de carga.
- `internalToast(message, type)` (`:603`) — toast de respaldo si no existe `window.showToast`.
- `mostrarAlertaNavbar()` (`:643`) — abre el **modal Peso Muestra** (SweetAlert2). Carga el registro seleccionado, permite usar la orden de la fila o elegir una orden en proceso, y al guardar persiste. Endpoints que consume (a través de sus helpers anidados):
  - `actualizarEstadoListaMat()` (`:775`) — habilita/inhabilita el campo BomId según el checkbox Act Lmat.
  - `actualizarBotonGuardar()` (`:789`) — valida que haya BomId si Act Lmat está activo.
  - `actualizarDatalistLmat(opciones, mostrarMensaje)` (`:811`) — rellena el datalist de BOM.
  - `buscarBomLibre(term, callback)` (`:837`) — `GET /planeacion/programa-tejido/liberar-ordenes/bom-sugerencias?freeMode=1&term=…`.
  - `cargarCatCodificadosPorOrden(orden)` (`:879`) — `GET /planeacion/codificacion/api/catcodificados-por-orden/{orden}` (precarga telar, artículo, peso, BomId y lista LMAT).
  - `onOrdenTejidoSelectChange()` (`:915`) — al cambiar la orden recarga datos.
  - `poblarSelectOrdenesEnProceso()` (`:929`) — `GET /planeacion/codificacion/api/ordenes-en-proceso`.
  - `aplicarModoOrdenTejido(usarFila)` (`:954`) — alterna entre select de órdenes en proceso y orden de la fila.
  - Guardado final: `POST /planeacion/codificacion/api/actualizar-peso-muestra-lmat` (`:1043`).
- `loadData(forceRefresh)` (`:1085`) — `GET` al `apiUrl` (`/planeacion/codificacion/api/all-fast`, con `?nocache=1` si forceRefresh) y rellena la tabla.
- `renderPage()` (`:1176`) — pinta la página actual de la tabla virtualizada.
- `updatePagination()` (`:1291`) — recalcula controles de paginación.
- `getColumnElements(index)` (`:1322`) — obtiene celdas de una columna.
- `updatePinnedPositions()` (`:1326`) — recalcula posiciones de columnas fijadas (sticky).
- `updateColumnHeaderIcons()` (`:1370`) — actualiza iconos de filtro en encabezados.
- `escapeHtml(text)` (`:1390`) — escapa HTML (anti-XSS) en celdas.
- `aplicarFiltrosAND()` (`:1399`) — aplica todos los filtros activos (lógica AND) sobre `state.data`.
- `updateFilterCount()` (`:1455`) — actualiza el badge contador de filtros.
- `aplicarAccionRapidaOrdCompartida()` (`:1471`) — acción rápida de filtro por OrdCompartida.
- `filtrarCodificacion()` (`:1502`) — abre el modal de filtros.
- `addFilterFromModal()` / `renderModalFilters()` / `removeFilterPorColumna(i)` / `removeFilterFromModal(i)` / `limpiarFiltrosCodificacion()` (`:1622`–`:1725`) — gestión de filtros del modal.
- `hideContextMenu()` / `showContextMenu(e, idx, field)` / `openFilterModal(idx, field)` (`:1744`–`:1768`) — menú contextual por columna.
- `initPaginationEvents()` (`:1839`) — registra eventos de paginación y resize.
- `actualizarEstadoBotonReimprimir()` (`:1951`) — habilita el botón Reimprimir según fila seleccionada (requiere `UsuarioCrea`).
- `actualizarEstadoBotonBalancear()` (`:1976`) — habilita Balancear si la fila tiene `OrdCompartida`.
- `actualizarEstadoBotonRevivir()` (`:1997`) — habilita Revivir.
- `actualizarEstadoBotonRecalcMarbete()` (`:2019`) — habilita Recalc. marbetes.
- `revivirOrdenAlPrograma()` (`:2033`) — `POST /planeacion/codificacion/api/revivir-programa` con `{cat_id, en_proceso}`.
- `recalcularMarbetesCodificacion()` (`:2109`) — `POST /planeacion/codificacion/api/recalcular-marbetes` con `{ids:[…]}`.
- `abrirModalBalancear()` (`:2187`) — `GET /planeacion/codificacion/api/registros-ord-compartida/{ordCompartida}` y muestra los registros compartidos.
- `reimprimirOrdenSeleccionada()` (`:2289`) — valida selección y delega en `reimprimirOrden`.
- `reimprimirOrden(id)` (`:2316`) — `GET /planeacion/programa-tejido/reimprimir-ordenes/{id}` y descarga el blob resultante (orden de cambio).

**Globales expuestas** (`window.*`, `:2373`+): `mostrarAlertaNavbar`, `filtrarCodificacion`, `limpiarFiltrosCodificacion`, `removeFilterFromModal`, `removeFilterPorColumna`, `loadData`, `reimprimirOrden`, `revivirOrdenAlPrograma`, `recalcularMarbetesCodificacion`, `reimprimirOrdenSeleccionada`, `abrirModalBalancear`.

### 6.2 `resources/views/catcodificacion/partials/excel-upload.blade.php`
**Propósito:** flujo completo de subida de Excel de CatCodificados con barra de progreso, manejo de errores de plantilla y cancelación. Todo dentro de una IIFE con estado `importFlowState`.

**Funciones JS:**
- Helpers internos: `getCsrfToken`, `clearPollTimeout`, `clearControllers`, `abortActiveRequests`, `resetImportFlow`, `finishImportFlow`, `scheduleNextPoll(url, attempts)`, `notify(message, type)`, `cancelImportFlow()` (`POST` al `cancel_url` si la importación fue encolada).
- `renderHeaderErrors(errors)` — tabla SweetAlert con Col/Letra/Esperado/Actual de los encabezados inválidos.
- `renderImportSummary(created, updated, errors)` — HTML del resumen de import.
- `showImportFinished(summary)` — muestra el resumen final y recarga datos (`window.loadData(true)` o reload).
- `window.subirExcelCatCodificacion()` — abre el SweetAlert de selección de archivo (valida tamaño ≤ 10 MB).
- `window.procesarExcel(file)` — `POST {{ route('planeacion.codificacion.excel') }}` con `FormData`; si `completed` muestra resumen, si `poll_url` inicia el polling; maneja errores de plantilla (`errors.headers`).
- `window.pollImportProgress(url, attempts=0)` — hace polling (`GET poll_url`, máx 600 intentos / 1s) actualizando la barra; al `done` muestra el resumen, al `cancelled` notifica.

### 6.3 Vistas legacy de modelos (ELIMINADAS)
Las vistas `resources/views/modulos/modelos/create.blade.php` y `edit.blade.php` (formularios CRUD del modelo legacy `modelos`), junto con su carpeta `modulos/modelos/`, **fueron eliminadas el 2026-06-04**. Eran un feature huérfano: ningún controller las renderizaba y referenciaban las rutas `modelos.store` / `modelos.update`, inexistentes en el árbol `routes/`. Ver "Hallazgos resueltos (2026-06-04)".

---

## 7. JS dedicado

No existen archivos `.js` dedicados (en `resources/js/`) específicos de este ámbito. Toda la lógica de cliente es inline en los blades (sección 6). Las funciones globales relevantes ya están listadas: las expuestas por `catcodificacion/index.blade.php` (`window.mostrarAlertaNavbar`, `window.loadData`, `window.filtrarCodificacion`, `window.revivirOrdenAlPrograma`, `window.recalcularMarbetesCodificacion`, `window.abrirModalBalancear`, `window.reimprimirOrden`, etc.) y las del partial de Excel (`window.subirExcelCatCodificacion`, `window.procesarExcel`, `window.pollImportProgress`). Estas vistas todavía usan `fetch` crudo y `window.showToast`/SweetAlert (no migradas aún a `window.http`/`window.notify`).

---

## 8. Lógica de negocio y reglas

### 8.1 Fórmula de No. de Marbetes (Recalcular)
Implementada en `SaldoMarbeteCodificacionService` y replicando la de Liberar Órdenes:
1. **Peso de rollo:** 90.0 kg si el modelo es "Felpa"; en caso contrario se busca en `ReqPesosRollosTejido` por `InventSizeId` exacto → por `'FEL'` → por `'DEF'` → fallback 41.5 kg.
2. **Repeticiones:** `repeticiones = TRUNCAR((pesoRollo / P_crudo) / NoTiras × 1000)`.
3. **Saldo de marbetes:** `marbetes = REDONDEAR((Pedido|Cantidad / NoTiras) / repeticiones, 0)`.
4. **Ajuste FEL/Felpa:** si `InventSizeId` contiene "FEL" o el modelo es Felpa, el valor se **duplica** (`× 2`).
Requiere `Pedido`/`Cantidad`, `NoTiras` y `P_crudo` numéricos > 0; cualquier resultado ≤ 0 se rechaza con mensaje. El controller redondea a 0 decimales y persiste en `CatCodificados.NoMarbete`.

### 8.2 Restricciones / validaciones de negocio
- **Borrado bloqueado:** no se puede eliminar un `ReqModelosCodificados` cuya `Clave` (clave mod) esté en uso en `ReqProgramaTejido.TamanoClave` (HTTP 422).
- **Claves obligatorias en import:** `TamanoClave` + `OrdenTejido` (ReqModelosCodificados) y `OrdenTejido` (CatCodificados) no pueden estar vacíos.
- **Validación de plantilla Excel (CatCodificados):** si los encabezados no coinciden con `CatCodificadosExcelHeaderMapper`, el import se rechaza (422) listando columna/letra/esperado/actual. (El import de ReqModelosCodificados NO valida plantilla; es tolerante por posición/nombre.)
- **Truncado:** todos los valores string se truncan a la longitud real de la columna SQL antes de insertar (`getColumnMaxLengths`/`truncateValueForColumn`, `MAX_TEXT_LENGTH=2040` en el row mapper).
- **Lista de materiales (LMAT/BOM):** si "Act Lmat" se desactiva, `BomId` y `BomName` se fuerzan a null en las tres tablas.

### 8.3 Flujos completos
- **Guardar Peso Muestra / LMAT** (`actualizarPesoMuestraLmat`): obtiene `BomName` desde `sqlsrv_ti` y propaga `PesoMuestra`/`ActualizaLmat`/`BomId`/`BomName` a `CatCodificados`, `ReqProgramaTejido` (por `NoProduccion`) y `PesoMuestra` a `ReqModelosCodificados` (por `OrdenTejido`). Limpia `catcodificacion_fast_all`.
- **Revivir orden a programa** (`revivirProgramaDesdeCat` → `RevivirOrdenProgramaDesdeCatService`): limpia `FechaFinaliza` en CatCodificados y recrea la orden en `ReqProgramaTejido` (al final de cola o en proceso según `en_proceso`).
- **Duplicar/Importar a codificación** (`duplicarImportar`): en transacción, crea N registros nuevos en `ReqModelosCodificados` ya sea replicando un original (duplicar) o mapeando desde `CatCodificados` (importar), sobreescribiendo salón/clave mod/clave AX/nombre/tamaño y opcionalmente FlogsId/CustName.
- **Import Excel encolado con progreso:** ambos controllers escriben estado en cache `excel_import_progress:{uuid}`; el cliente hace polling a `…/excel-progress/{id}`. CatCodificados decide inline (≤20 filas) vs cola; permite cancelar (`…/excel-cancel/{id}`) marcando `excel_import_cancelled:{id}` y borrando jobs pendientes.

### 8.4 Efectos colaterales y caches
- Cualquier alta/edición/borrado/duplicado de ReqModelosCodificados limpia: `codificacion_total`, `codificacion_fast_all`, `codificacion_estimated_count`, `codificacion_fast_id_{id}`.
- Operaciones sobre CatCodificados (incl. fin de import) limpian: `catcodificacion_total`, `catcodificacion_fast_all`, `catcodificacion_estimated_count`, `catcodificacion_fast_id_{id}`.
- Los TTL de las respuestas `getAllFast` son de 60s; las estadísticas y totales 300s.

### 8.5 Integraciones entre bases de datos
- **`sqlsrv` (default):** todas las tablas del catálogo, `ReqProgramaTejido`, `InvSecuenciaTelares`, `ReqPesosRollosTejido`, cola `jobs`.
- **`sqlsrv_ti` (TI_PRO):** datos de Flogs (`getFlogsData` → `TwFlogsItemLine`/`TwFlogsTable`) y LMAT/BOM (`queryLmatDesdeTi` → `BOMTABLE`/`BOMVERSION`).
- **Excel:** import (Maatwebsite, encolado/inline) — clases `ReqModelosCodificadosImport`, `CatCodificadosImport`, `QueuedCatCodificadosImport`. La exportación / impresión de orden de cambio se delega a `OrdenDeCambioFelpaController` (PDF/Excel, fuera de este scope).
- **Telegram / PDF:** este ámbito no envía notificaciones Telegram directamente; la reimpresión de orden produce un archivo descargable vía la ruta de Reimprimir Órdenes (otro módulo).
