# Planeación — Alineación y Utilerías

> Generado automáticamente — documentación detallada del módulo

---

## 1. Propósito del módulo

Este ámbito de **Planeación** agrupa dos submódulos auxiliares del flujo productivo de tejido textil que operan sobre el **programa de tejido** (`ReqProgramaTejido`), la cola de órdenes que cada telar debe producir en secuencia:

1. **Alineación** (`/planeacion/alineacion`): pantalla de **monitoreo de piso** en formato de tabla densa. Muestra todas las órdenes que están actualmente **en proceso** (`EnProceso = 1`) en cada telar, enriquecidas con datos de codificación del producto (`CatCodificados`): tolerancias de peso, tipo de rizo, calibres, fibras, cenefas, pesos mín/máx, días por ejecutar, etc. Es de **solo lectura**, con refresco automático cada 5 minutos, filtros y fijado de columnas en cliente, y alertas visuales para telares con paro activo en mantenimiento. Sirve para verificar de un vistazo que lo que se teje en el telar coincide con la especificación de la orden ("alinear" piso vs. especificación).

2. **Utilería** (`/planeacion/utileria`): página de aterrizaje con dos herramientas de mantenimiento manual del programa de tejido:
   - **Finalizar Órdenes**: permite cerrar/eliminar órdenes de un telar, transfiriendo saldos a la orden líder cuando son compartidas, sincronizando el catálogo `CatCodificados` (fecha de finalización, pedido/producción/saldos) y recalculando la cadena de fechas y posiciones del telar.
   - **Mover Órdenes**: interfaz drag-and-drop de dos paneles (origen/destino) para **reubicar y reordenar** órdenes entre telares. Ajusta telar, salón, posición, máquina, eficiencia/velocidad STD y cuentas según el salón destino, recalcula fechas y normaliza el estado "en proceso".

Un tercer flujo relacionado documentado aquí es el **Service `RevivirOrdenProgramaDesdeCatService`**, que "revive" una orden ya finalizada (existente en `CatCodificados`) reinsertándola en `ReqProgramaTejido`. Aunque su controlador (`CatCodificacionController@revivirProgramaDesdeCat`) pertenece al módulo de Codificación, el servicio es lógica del programa de tejido y se incluye por su acoplamiento con Utilería.

**Rol dentro del flujo productivo:** estos submódulos NO crean órdenes (eso lo hace Programa de Tejido / Liberar Órdenes); son herramientas de **ajuste operativo** de la cola ya programada y de **visibilidad** en piso. Sus operaciones tienen efectos colaterales fuertes: disparan el `ReqProgramaTejidoObserver` (regeneración de líneas diarias y fórmulas de eficiencia) y sincronizan el catálogo maestro `CatCodificados` y `ReqModelosCodificados`.

---

## 2. Rutas

Todas las rutas pertenecen al archivo `routes/modules/planeacion.php` y al middleware `auth` (heredado del agrupador de rutas autenticadas; ninguna ruta de este ámbito declara middleware adicional propio). **No** se declaran middlewares de permiso por ruta; los controladores tampoco invocan `userCan()` (ver sección 8, "Permisos").

### Alineación

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/alineacion` | `AlineacionController@index` | `auth` | Ninguno verificado en código |
| GET | `/planeacion/alineacion/api/data` | `AlineacionController@apiData` | `auth` | Ninguno verificado en código |

Nombres de ruta: `planeacion.alineacion.index`, `planeacion.alineacion.api.data` (`routes/modules/planeacion.php:94-95`).

### Utilería — Finalizar Órdenes

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/utileria` | Closure `view('planeacion.utileria.index')` | `auth` | Ninguno |
| GET | `/planeacion/utileria/finalizar/telares` | `FinalizarOrdenesController@getTelares` | `auth` | Ninguno verificado |
| GET | `/planeacion/utileria/finalizar/ordenes` | `FinalizarOrdenesController@getOrdenesByTelar` | `auth` | Ninguno verificado |
| POST | `/planeacion/utileria/finalizar/procesar` | `FinalizarOrdenesController@finalizarOrdenes` | `auth` | Ninguno verificado |

Nombres: `planeacion.utileria.index`, `planeacion.utileria.finalizar.telares`, `planeacion.utileria.finalizar.ordenes`, `planeacion.utileria.finalizar.procesar` (`routes/modules/planeacion.php:99-104`).

### Utilería — Mover Órdenes

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/utileria/mover/telares` | `MoverOrdenesController@getTelares` | `auth` | Ninguno verificado |
| GET | `/planeacion/utileria/mover/registros` | `MoverOrdenesController@getRegistrosByTelar` | `auth` | Ninguno verificado |
| POST | `/planeacion/utileria/mover/procesar` | `MoverOrdenesController@moverOrdenes` | `auth` | Ninguno verificado |

Nombres: `planeacion.utileria.mover.telares`, `planeacion.utileria.mover.registros`, `planeacion.utileria.mover.procesar` (`routes/modules/planeacion.php:107-109`).

### Redirección relacionada

- `Route::redirect('/planeacion/utilera', '/planeacion/utileria', 301)` (`routes/modules/planeacion.php:36`) — corrige el typo histórico `utilera` → `utileria`.

### Ruta del Service "Revivir" (módulo Codificación, mismo ámbito lógico)

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| POST | `/planeacion/codificacion/api/revivir-programa` | `CatCodificacionController@revivirProgramaDesdeCat` → `RevivirOrdenProgramaDesdeCatService@ejecutar` | `auth` | Ninguno verificado |

Nombre: `planeacion.codificacion.revivir-programa` (`routes/modules/planeacion.php:83`).

---

## 3. Controllers

### 3.1 `AlineacionController`
Archivo: `app/Http/Controllers/Planeacion/Alineacion/AlineacionController.php`. Controlador de **solo lectura**; no escribe en BD.

#### `index(): View` — `AlineacionController.php:69`
- **Qué hace:** renderiza la vista principal de Alineación con los items en proceso.
- **Request:** ninguno (GET sin parámetros).
- **Respuesta:** `view('planeacion.alineacion.index', ['items' => $items])`.
- **Datos:** delega en `obtenerItemsAlineacion()`.

#### `apiData(): JsonResponse` — `AlineacionController.php:81`
- **Qué hace:** misma data que `index`, pero en JSON para el refresco automático cada 5 min del front.
- **Request:** ninguno.
- **Respuesta:** `{ "s": true, "items": [...] }`. Nota: la clave es `s` (no `success`); el JS de la vista valida exactamente `json.s`.

#### Funciones privadas relevantes
- **`obtenerItemsAlineacion(): array`** (`:93`): consulta `ReqProgramaTejido` con scopes `->enProceso(true)` (`EnProceso = 1`) y `->ordenado()` (orden por `SalonTejidoId`, `NoTelarId`, `Posicion`, `FechaInicio`). Cruza con `CatCodificados` por orden y con telares con paro activo, y mapea cada registro a un array de columnas.
- **`obtenerTelaresConParoActivo(): array`** (`:112`): consulta `ManFallasParos` (conexión `sqlsrv`, tabla `dbo.ManFallasParos`) `where('Estatus','Activo')`, devuelve los `MaquinaId` normalizados/únicos. Sirve para pintar la fila en amarillo y poner el icono de alerta.
- **`obtenerCatCodificadosPorOrden(Collection $registros): array`** (`:130`): recolecta los `NoProduccion` de los registros y consulta `CatCodificados` con `whereRaw("CAST([OrdenTejido] AS NVARCHAR(100)) IN (...)")` (placeholders parametrizados). Selecciona `Id, ItemId, OrdenTejido, FechaTejido, Tolerancia, Razurada, TipoRizo, DobladilloId, Obs5`, ordena `Id` desc y devuelve un mapa `OrdenTejido → CatCodificados` quedándose con el primero (más reciente) por orden.
- **`mapearProgramaTejidoAItem(ReqProgramaTejido $r, array $catCodPorOrden, array $telaresConParoActivo): array`** (`:170`): construye la fila final. Mapeos destacados:
  - `FechaCompromiso ← EntregaCte` (formateada `d M Y`).
  - `FechaCambio, Tolerancia, RazSN, TipoRizo, TipoPlano, Observaciones, PesoMin, PesoMax, MuestraMin, MuestraMax` provienen de `CatCodificados` (`FechaTejido, Tolerancia, Razurada, TipoRizo, DobladilloId, Obs5`).
  - `PasadasComb1..4` se concatenan como `Calibre/Fibra` de `ReqProgramaTejido` (campos `CalibreCombN`/`FibraCombN`).
  - `DiasPorEjecutar = round(SaldoPedido / ProdKgDia, 2)`; si `ProdKgDia` es nulo o ≤ 0 ⇒ `'ABIERTO'`.
  - `FechaTejido` se expone como `Y-m-d` para que el front calcule "Días de prod.".
  - `_tieneParoActivo`: `true` si el `NoTelarId` está en la lista de telares con paro activo.
- **`minMaxAlineacionToleranciaN(?CatCodificados $cat, mixed $base): array`** (`:268`): si `Tolerancia == 'N'` y `$base > 0`, calcula `[min, max]` a partir de `base/1.03`, `base/1.00`, `base/1.05` (redondeados a entero). En caso contrario devuelve `['','']`. Aplica a Peso y Muestra.
- **`concatCalibreFibra($calibre, $fibra): string`** (`:282`): une calibre y fibra como `c/f` (o solo uno si falta el otro).
- **`formatDateAlineacion($value, string $format): string`** (`:301`): formatea con Carbon locale `es` (`translatedFormat`); en error devuelve el valor crudo.

**Validaciones:** ninguna (solo lectura). **Folios/Turnos:** no usa `FolioHelper` ni `TurnoHelper`. **Conexiones:** todas las consultas usan la conexión por defecto `sqlsrv`.

---

### 3.2 `FinalizarOrdenesController`
Archivo: `app/Http/Controllers/Planeacion/Utilerias/FinalizarOrdenesController.php`. Usa el trait `HandlesApiErrors` (helper `apiErrorResponse`).

#### `getTelares(): JsonResponse` — `FinalizarOrdenesController.php:50`
- **Qué hace:** lista los telares que tienen al menos una orden con `NoProduccion` no nulo/no vacío. Normaliza salón y telar con `TelarSalonResolver`, deduplica por `salon|telar`, filtra los sin telar y ordena por salón + clave de telar.
- **Request:** ninguno.
- **Respuesta:** `{ success: true, telares: [{ salon, telar, label }] }`.
- **Tablas/queries:** `ReqProgramaTejido` (`sqlsrv`), `select SalonTejidoId, NoTelarId` con `whereNotNull/where != ''` sobre `NoProduccion` y `NoTelarId`.
- **Errores:** `apiErrorResponse`.

#### `getOrdenesByTelar(Request $request): JsonResponse` — `FinalizarOrdenesController.php:93`
- **Qué hace:** devuelve las órdenes (registros con `NoProduccion`) de un telar, cruzadas con `CatCodificados` para la fecha de cambio.
- **Request (query string):** `telar` (requerido), `salon` (requerido). Se normalizan; si falta alguno ⇒ 422 `{ success:false, message:'Salón y telar son requeridos' }`.
- **Respuesta:** `{ success:true, ordenes:[{ id, noOrden, fechaCambio, tamanoClave, modelo, enProceso(bool), saldoPedido, produccion, totalPedido }] }`.
- **Tablas/queries:** `ReqProgramaTejido` filtrado por telar (`TelarSalonResolver::applyTelarFilter`), `select Id, NoProduccion, TamanoClave, NombreProducto, SalonTejidoId, NoTelarId, Posicion, EnProceso, SaldoPedido, Produccion, TotalPedido`, orden por `Posicion` y `FechaInicio`. `CatCodificados`: `select OrdenTejido, FechaTejido` con `whereRaw CAST([OrdenTejido] AS NVARCHAR(100)) IN (...)`; `FechaTejido` se formatea `d/m/Y`. Todo en `sqlsrv`.
- **Validación:** manual (salón + telar requeridos). Sin `validate()` de Laravel.

#### `finalizarOrdenes(Request $request): JsonResponse` — `FinalizarOrdenesController.php:179`
- **Qué hace:** finaliza (elimina físicamente) los registros de programa indicados, con todo el manejo transaccional, de saldos compartidos, sincronización de catálogo, reasignación de "en proceso", recálculo de fechas y disparo de observer.
- **Request (JSON body):** validado con `$request->validate()`:
  - `ids`: `required|array|min:1`
  - `ids.*`: `required|integer`
- **Respuesta éxito:** `{ success:true, message:'Se finalizaron N orden(es) correctamente', finalizadas:N }`. Si no hay registros válidos ⇒ 422 `{ success:false, message:'No se encontraron órdenes con NoOrden válido...' }`. Error ⇒ `apiErrorResponse` (500).
- **Flujo interno (todo bajo `DB::beginTransaction()` con `lockForUpdate()`):**
  1. Carga los registros (`whereIn Id`, con `NoProduccion` válido). Si está vacío ⇒ rollback + 422.
  2. **Paso 1 por registro:**
     - 1a. **OrdCompartida:** si la orden es compartida, busca el líder (`OrdCompartidaLider = 1`, o el de menor `FechaInicio` promoviéndolo a líder) y le **transfiere el `SaldoPedido`** (`SaldoActual + SaldoTransferir`), guardando con `saveQuietly()` y sincronizando `ReqModelos` vía `MovimientoDesarrolladorService::actualizarReqModelosDesdePrograma()`.
     - 1b. Si el registro tenía `EnProceso = 1`, marca su telar para reasignar el "en proceso".
     - 1c. Asigna `FechaFinaliza = now()` y sincroniza fechas de arranque/finaliza al catálogo (`actualizarFechasArranqueFinaliza(..., 'now', preservarFechaArranqueCat: true)`).
     - 1d. Sincroniza pedido/producción/saldos a `CatCodificados` (`actualizarReqModelosDesdePrograma`).
     - Auditoría: `OrdenFinalizadaAuditoria::registrarUtileriaFinalizar(...)` (envuelta en try/catch; un fallo solo loguea `warning`).
     - 1e. `$registro->delete()` (eliminación física).
  3. 1f. Para cada `OrdCompartida` afectada: `VincularTejido::actualizarOrdPrincipalPorOrdCompartida()`.
  4. 1g. Telares que perdieron su "en proceso": pone `EnProceso = 0` a todo el telar y `EnProceso = 1` al primer restante por `Posicion`.
  5. **Paso 2 (sin dispatcher de eventos):** por cada telar afectado recalcula la cadena de fechas con `DateHelpers::recalcularFechasSecuencia()` anclando al primer registro con `FechaInicio`. Se **descarta `EnProceso`** de los updates (los estados ya se fijaron en Paso 1). Bump anti-colisión de `Posicion` (`ISNULL(Posicion,0) + 10000`) antes de aplicar las nuevas posiciones.
  6. **Paso 3:** restaura el dispatcher y `DB::commit()`.
  7. **Paso 4 (fuera de transacción):** `ReqProgramaTejido::regenerarLineas()` sobre los IDs afectados ⇒ dispara el observer para regenerar líneas diarias y fórmulas.
- **Conexiones:** `sqlsrv` (todas las tablas: `ReqProgramaTejido`, `CatCodificados`, `OrdenFinalizadaAuditoria`, `ReqModelosCodificados`).
- **Folios/Turnos:** no usa `FolioHelper` ni `TurnoHelper`.

---

### 3.3 `MoverOrdenesController`
Archivo: `app/Http/Controllers/Planeacion/Utilerias/MoverOrdenesController.php`. Usa `HandlesApiErrors` (`apiErrorResponse`, `apiClientErrorResponse`).

#### `getTelares(): JsonResponse` — `MoverOrdenesController.php:48`
- **Qué hace:** lista **todos** los telares del catálogo `ReqTelares` (no solo los que tienen órdenes; a diferencia de Finalizar), para que el destino pueda ser un telar vacío.
- **Request:** ninguno.
- **Respuesta:** `{ success:true, telares:[{ salon, telar, label }] }`. Normalizados, deduplicados y ordenados como en Finalizar.
- **Tablas:** `ReqTelares` (`sqlsrv`, `dbo.ReqTelares`), `select SalonTejidoId, NoTelarId`.

#### `getRegistrosByTelar(Request $request): JsonResponse` — `MoverOrdenesController.php:87`
- **Qué hace:** devuelve los registros con `NoProduccion` de un telar (origen o destino), con marca de repaso1 y estado en proceso.
- **Request (query):** `telar` (requerido), `salon` (requerido). Si falta alguno ⇒ 422 vía `apiClientErrorResponse`.
- **Respuesta:** `{ success:true, registros:[{ id, noOrden, tamanoClave, modelo, posicion, enProceso(bool), esRepaso1(bool), produccion, telar }] }`. `esRepaso1` se calcula si el modelo contiene "repaso1" (case-insensitive).
- **Tablas:** `ReqProgramaTejido` filtrado por telar; `select Id, NoProduccion, TamanoClave, NombreProducto, SalonTejidoId, NoTelarId, Posicion, EnProceso, Produccion`. `sqlsrv`.

#### `moverOrdenes(Request $request): JsonResponse` — `MoverOrdenesController.php:159`
- **Qué hace:** persiste el reordenamiento/reubicación de órdenes entre uno o dos telares, ajustando ubicación, STD y fechas, normalizando "en proceso" y disparando observer.
- **Request (JSON body), validado con `Validator::make()`:**
  - `ordenes_origen`: `nullable|array`; `ordenes_origen.*`: `integer`
  - `origen_salon`, `origen_telar`: `nullable|string`
  - `ordenes_destino`: `nullable|array`; `ordenes_destino.*`: `integer`
  - `destino_salon`, `destino_telar`: `nullable|string`
  - `solo_reorden_origen`: `nullable|boolean` (si `true`, solo reordena el telar origen, ignora el destino)
  - Si la validación falla ⇒ 422 vía `apiClientErrorResponse` con `errors`.
- **Respuesta éxito:** `{ success:true, message:'Se guardaron los cambios correctamente.' }`. Error ⇒ `apiErrorResponse` (500).
- **Flujo interno (transaccional con `lockForUpdate()`):**
  1. Arma la lista de telares a procesar (origen siempre; destino solo si no es `solo_reorden_origen`).
  2. `registrarInicioBaseTelar()` por cada telar (ancla de fecha del primer registro) y `capturarEstadoOriginal()` (salón/telar/enProceso originales de cada id, con bloqueo).
  3. **Paso 1** (`procesarMovimientoPorTelar`): por telar, bump anti-colisión de `Posicion` y reasignación de `Posicion = index+1`, `SalonTejidoId`, `NoTelarId`, `UpdatedAt`. Si cambia de ubicación llama a `aplicarCambioUbicacion`.
  4. **Paso 2** (`recalcularFechasPorTelar`, sin dispatcher): recalcula cadena de fechas con `DateHelpers::recalcularFechasSecuencia()`. Descarta `EnProceso` de los updates (se normaliza en 2.5). Bump anti-colisión.
  5. **Paso 2.5** (`normalizarEnProceso`): pone `EnProceso = 0` a todo el telar y `EnProceso = 1` al registro de `Posicion = 1`.
  6. **Paso 3:** restaura dispatcher y `DB::commit()`.
  7. **Paso 4** (`dispararObserver`, fuera de transacción): `regenerarLineas()` sobre todos los IDs afectados.
  8. **Paso 5** (`sincronizarCatCodificados`, fuera de transacción): solo para registros que cambiaron de **salón**, sincroniza `CatCodificados` (modelos y fechas) vía `MovimientoDesarrolladorService`.
- **Conexiones:** `sqlsrv`.

#### Funciones privadas relevantes
- **`normalizar(mixed): string`** (`:265`): `trim` seguro.
- **`registrarInicioBaseTelar(salon, telar, &$inicioBasePorTelar)`** (`:275`): guarda la `FechaInicio` del primer registro del telar (con lock) como ancla del recálculo.
- **`capturarEstadoOriginal($ids, &$estado, &$inicioBase)`** (`:305`): bloquea y captura salón/telar/enProceso original de cada id; registra anclas de los telares de origen.
- **`procesarMovimientoPorTelar(...)`** (`:335`): Paso 1 detallado arriba; controla colisión del índice único `(NoTelarId, Posicion)` con el truco `+10000`.
- **`aplicarCambioUbicacion(ReqProgramaTejido $registro, salon, telar, cambiaSalon, id, &$idsCambioSalon)`** (`:399`): pone `EnProceso = 0`, reconstruye `Maquina` (`TejidoHelpers::construirMaquinaConSalon`), y si hay cambio de salón ajusta `EficienciaSTD`/`VelocidadSTD` (`QueryHelpers::resolverStdSegunTelar` con el modelo destino `ReqModelosCodificados` por `TamanoClave`+`SalonTejidoId`) y `CuentaRizo`/`CuentaPie`. Marca el id en `idsCambioSalon` si cambió de salón.
- **`recalcularFechasPorTelar(...)`** (`:446`): Paso 2; el primer registro se trata como `EnProceso = 1` (usa `now()`, no actualiza `FechaInicio`); el resto se encadena.
- **`normalizarEnProceso(...)`** (`:516`): Paso 2.5; deja `EnProceso = 1` solo en `Posicion = 1` y acumula los IDs del telar en `idsAfectados`.
- **`dispararObserver(array $idsAfectados)`** (`:563`): Paso 4; aplana IDs y llama `regenerarLineas()`.
- **`sincronizarCatCodificados(array $idsCambioSalon)`** (`:579`): Paso 5; `actualizarReqModelosDesdePrograma()` + `actualizarFechasArranqueFinaliza()` por registro movido de salón.

**Permisos:** ninguno verificado con `userCan()`. **Folios/Turnos:** no usa `FolioHelper` ni `TurnoHelper`.

---

## 4. Services y Helpers del ámbito

### 4.1 `RevivirOrdenProgramaDesdeCatService`
Archivo: `app/Services/Planeacion/RevivirOrdenProgramaDesdeCatService.php`.

#### `ejecutar(int $catId, bool $colocarEnProceso = false): array` — `:20`
- **Qué hace:** reinserta en `ReqProgramaTejido` una orden previamente finalizada (existente en `CatCodificados`), limpiando su `FechaFinaliza` y opcionalmente dejándola como la orden en proceso del telar.
- **Parámetros:** `$catId` (Id del registro `CatCodificados`), `$colocarEnProceso`.
- **Respuesta:** `{ programa_id:int, cat_id:int }`.
- **Validaciones (lanzan `ValidationException`):**
  - `cat_id`: el registro debe existir.
  - `OrdenTejido`: no vacío.
  - `TelarId`: telar válido tras normalizar.
  - `Departamento`: no vacío (es el salón).
  - `NoProduccion`: no debe existir ya esa orden en el mismo salón+telar (evita duplicados).
  - `programa_id`: debe poder confirmarse el Id real tras insertar.
- **Flujo (dentro de `DB::transaction()` y con observers suprimidos vía `suppressObservers()` / `restoreObservers()`):**
  1. Bloquea (`lockForUpdate`) el `CatCodificados`; valida orden, telar y departamento (normalizados con `TelarSalonResolver`).
  2. Verifica que no exista la orden en programa para ese salón+telar.
  3. `cat->FechaFinaliza = null; cat->save()` (reabre el registro de catálogo).
  4. `siguientePosicionAlFinal()` ⇒ coloca al final del telar (no rellena huecos).
  5. `resolverFechasParaNuevoRegistro()` ⇒ `FechaInicio`/`FechaFinal`.
  6. `mapearCatAPrograma()` ⇒ mapea ~100 campos del catálogo al programa, filtrando por `getFillable()`.
  7. Inserta (`new ReqProgramaTejido(...); ->save()`), resuelve el Id real (`resolverIdRealPrograma`) y rehidrata el modelo.
  8. Si `colocarEnProceso`: `EnProceso = 0` a todo el telar y `EnProceso = 1` a la nueva orden.
  9. Calcula fórmulas de eficiencia (`TejidoHelpers::calcularFormulasEficienciaPorContexto(..., FORMULAS_CTX_PEDIDO_INHERIT)`) y persiste solo las fillable no nulas.
  10. `regenerarLineas([$programa])` ⇒ líneas diarias.
- **Conexión:** `sqlsrv`.

#### Funciones privadas
- **`resolverIdRealPrograma(orden, salon, telar, posicion): ?int`** (`:138`): obtiene el `Id` real recién insertado (filtrando por orden + posición, `orderByDesc('Id')`).
- **`siguientePosicionAlFinal(salon, telar): int`** (`:155`): `max(Posicion) + 1` (o 1 si vacío).
- **`resolverFechasParaNuevoRegistro(salon, telar): array`** (`:169`): inicio = `FechaFinal` del último registro; si no, `FechaInicio + 1 día`; si no, `now()`. Fin = inicio + `TejidoHelpers::DEFAULT_DURACION_DIAS`.
- **`mapearCatAPrograma(...)`** (`:195`): mapeo masivo `CatCodificados → ReqProgramaTejido` (combinaciones 1-5, calibres, fibras, colores, pedido/producción/saldos, BOM, densidad, OrdCompartida, etc.). Si hay `TamanoClave`, completa `AnchoToalla`/`LargoToalla`/`Nombre`/`VelocidadSTD` desde `TejidoHelpers::obtenerDatosModeloCodificadoArray()`. Filtra por `getFillable()`.
- **`parseFloat / parseEntero / parseEficiencia / parseBool`** (`:351-400`): coerciones tolerantes. `parseEficiencia` divide entre 100 si el valor es > 1 (normaliza porcentaje a fracción).

### 4.2 Helpers/Servicios de apoyo usados por el ámbito (definidos fuera, citados por completitud)
- **`TelarSalonResolver`** (`app/Support/Planeacion/TelarSalonResolver.php`): `normalizeSalon`, `normalizeTelar`, `applyTelarFilter` (filtra una query por salón+telar con alias), `telarSortKey`, `salonAliases`. Clave para resolver telares/salones con representaciones inconsistentes.
- **`DateHelpers::recalcularFechasSecuencia(Collection $registros, Carbon $inicio, bool $...)`**: recalcula `FechaInicio`/`FechaFinal`/`Posicion` encadenadas por telar; devuelve `[$updates]`. Tiende a fijar el primer registro como `EnProceso = 1` (por eso los controladores descartan `EnProceso` de los updates).
- **`MovimientoDesarrolladorService`** (`app/Http/Controllers/Tejedores/Desarrolladores/Funciones/`): `actualizarReqModelosDesdePrograma()` y `actualizarFechasArranqueFinaliza()` sincronizan `CatCodificados`/`ReqModelosCodificados`.
- **`VincularTejido::actualizarOrdPrincipalPorOrdCompartida(int)`**: recalcula `OrdPrincipal` de un grupo de órdenes compartidas.
- **`TejidoHelpers`**: `construirMaquinaConSalon`, `calcularFormulasEficienciaPorContexto`, `obtenerDatosModeloCodificadoArray`, constantes `DEFAULT_DURACION_DIAS`, `FORMULAS_CTX_PEDIDO_INHERIT`.
- **`QueryHelpers::resolverStdSegunTelar()`**: resuelve `EficienciaSTD`/`VelocidadSTD` según telar/salón.
- **`HandlesApiErrors`** (`app/Support/Http/Concerns/`): `apiErrorResponse`/`apiClientErrorResponse` (respuestas JSON de error con log).
- **`ReqProgramaTejido::regenerarLineas()` / `suppressObservers()` / `restoreObservers()`**: gestionan el `ReqProgramaTejidoObserver`.

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `App\Models\Planeacion\ReqProgramaTejido` | `ReqProgramaTejido` (override vía `config('planeacion.programa_tejido_table')`) | `sqlsrv` (default) | `Id` (BIGINT) | `NoProduccion`, `NoTelarId`, `SalonTejidoId`, `Posicion`, `EnProceso`, `FechaInicio`, `FechaFinal`, `FechaArranque`, `FechaFinaliza`, `SaldoPedido`, `Produccion`, `TotalPedido`, `OrdCompartida`, `OrdCompartidaLider`, `OrdPrincipal`, `EficienciaSTD`, `VelocidadSTD`, `TamanoClave`, `Maquina` |
| `App\Models\Planeacion\Catalogos\CatCodificados` | `CatCodificados` | `sqlsrv` (default) | `Id` | `OrdenTejido`, `TelarId`, `Departamento`, `FechaTejido`, `FechaFinaliza`, `Tolerancia`, `Razurada`, `TipoRizo`, `DobladilloId`, `Obs5`, `ItemId`, `ClaveModelo`, `Pedido`, `Produccion`, `Saldos`, `OrdCompartida` |
| `App\Models\Mantenimiento\ManFallasParos` | `dbo.ManFallasParos` | `sqlsrv` | `Id` | `MaquinaId`, `Estatus` (`'Activo'`) |
| `App\Models\Planeacion\ReqTelares` | `dbo.ReqTelares` | `sqlsrv` (default) | `Id` | `SalonTejidoId`, `NoTelarId` |
| `App\Models\Planeacion\ReqModelosCodificados` | `ReqModelosCodificados` | `sqlsrv` (default) | `Id` | `TamanoClave`, `SalonTejidoId`, `CuentaRizo`, `CuentaPie` |
| `App\Models\Planeacion\OrdenFinalizadaAuditoria` | `OrdenFinalizadaAuditoria` | `sqlsrv` (default) | `Id` | (auditoría de finalización: id registro, orden, salón, telar, metadatos) |

> Nota: los modelos que no declaran `protected $connection` usan la conexión por defecto del proyecto, que es `sqlsrv`. `ManFallasParos` la declara explícitamente.

---

## 6. Vistas Blade

### 6.1 `resources/views/planeacion/alineacion/index.blade.php`
- **Propósito:** tabla densa de solo lectura de órdenes en proceso, con doble encabezado (grupos `Crudo`, `Hilo`, `Cenefa Trama`, `Peso`, `Muestra`), filtros por columna, fijado de columnas (pin), selección de fila y refresco automático.
- **Secciones UI:** botón de navbar "Fijar columnas" (`#alineacionNavFijar`); tabla `#mainTable` con `thead` sticky de dos filas (grupos con `colspan` + subencabezados); `tbody#alineacion-body` llenado por JS; menú contextual de encabezado `#alineacionContextMenuHeader` (Filtrar / Fijar); estilos para filas seleccionadas, filas con paro activo (amarillas) y rangos peso/muestra. El JS es un IIFE con `state` (data, filtered, pinnedColumns, filters, selectedRowIndex) y `CONFIG` (columnas, labels, `apiUrl`).
- **Funciones JS inline (`<script>`):**
  - `escapeHtml(text)` (`:279`): escapa HTML vía `textContent`. Sin endpoint.
  - `applyFiltersToData()` (`:285`): aplica los filtros activos agrupados por columna sobre `state.data` → `state.filtered`.
  - `getColumnElements(index)` (`:304`): devuelve los `th`/`td` de la columna `.column-N`.
  - `updatePinnedPositions()` (`:308`): calcula los `left` acumulados y aplica/quita la clase `alineacion-pinned` (sticky) a las columnas fijadas.
  - `updateColumnHeaderIcons()` (`:337`): pinta en cada encabezado los iconos de filtro activo (quitar filtro) y pin (desfijar).
  - `renderTable()` (`:357`): renderiza el `tbody`. Formatea `AnchoToalla`/`PesoGRM2` (3 decimales), `DiasPorEjecutar` (2 decimales) y calcula **en cliente** "Días de prod." (`DiasEficiencia`) como `hoy - FechaTejido` (parsea ISO `YYYY-MM-DD` o `DD/MM/YYYY`); marca telar con paro activo con icono de alerta.
  - `setSelectedRow(rowIndex)` (`:438`): alterna la fila seleccionada y re-renderiza.
  - `refreshData()` (`:450`): **async**; `fetch(CONFIG.apiUrl)` → consume **GET `/planeacion/alineacion/api/data`**; valida `json.s` y `json.items`, refresca `state.data` y re-renderiza. Se ejecuta cada 5 min.
  - `hideContextMenu()` (`:470`): oculta el menú contextual.
  - `showContextMenu(e, columnIndex, columnField)` (`:479`): posiciona y muestra el menú contextual sobre el encabezado; ajusta a viewport.
  - `openFilterModal(columnIndex, columnField)` (`:494`): SweetAlert2 con checkboxes de valores únicos de la columna (con conteos y búsqueda); aplica los filtros seleccionados.
  - `getColumnLabel(idx)` (`:559`): etiqueta legible de una columna.
  - `openPanelFijar()` (`:564`): SweetAlert2 con checkboxes para elegir columnas a fijar; actualiza `state.pinnedColumns`.
  - `DOMContentLoaded` (`:597`): inicializa render, listeners (selección de fila, contextmenu en thead, clic en iconos de encabezado, botones del menú contextual) y `setInterval(refreshData, 5min)`.
- **Endpoints consumidos:** GET `route('planeacion.alineacion.api.data')` (vía `refreshData`). Carga inicial: datos inyectados server-side (`@json($items)`).

### 6.2 `resources/views/planeacion/utileria/index.blade.php`
- **Propósito:** página de aterrizaje de Utilería con dos tarjetas-botón: "Finalizar Órdenes" (`onclick="abrirModalFinalizar()"`) y "Mover Órdenes" (`onclick="abrirModalMover()"`).
- **Secciones UI:** grid de dos tarjetas; `@include('planeacion.utileria.finalizar-ordenes')` y `@include('planeacion.utileria.mover-ordenes')`.
- **Funciones JS inline:** ninguna (no tiene bloque `<script>` propio; las funciones provienen de los partials incluidos).

### 6.3 `resources/views/planeacion/utileria/finalizar-ordenes.blade.php`
- **Propósito:** modal para finalizar órdenes de un telar.
- **Secciones UI:** modal `#modalFinalizar` con header (contador `#finalizarContador`), select de telar `#finalizarSelectTelar`, loader, tabla `#finalizarTbody` (No. Orden, Fecha Cambio, Clave, Modelo, Pedido, Producción, Saldos + checkbox), estado vacío `#finalizarEmpty`, footer con `#finalizarSeleccionados` y botón `#btnFinalizarConfirm`. JS en IIFE bajo `@push('scripts')`, con `finalizarState` y `ROUTES`.
- **Funciones JS inline (`<script>`):**
  - `abrirModalFinalizar()` (global, `:126`): muestra el modal, resetea estado y carga telares.
  - `cerrarModalFinalizar()` (global, `:132`): oculta el modal y resetea.
  - `resetFinalizarState()` (`:137`): limpia estado, select, tabla, footer y botón.
  - `cargarTelaresFinalizar()` (`:155`): **async**; `fetch(ROUTES.telares)` → **GET `/planeacion/utileria/finalizar/telares`**; llena el select con `t.telar`.
  - `cargarOrdenesFinalizar()` (global, `:177`): **async**; `fetch(ROUTES.ordenes + ?salon=&telar=)` → **GET `/planeacion/utileria/finalizar/ordenes`**; renderiza tabla o estado vacío y actualiza contador/footer.
  - `renderTablaFinalizar()` (`:224`): construye filas; resalta `enProceso` (ámbar + badge), `REPASO1` (badge rojo), saldo negativo (badge rojo); formatea números `es-MX`.
  - `toggleFinalizarRow(id, tr)` (global, `:256`): alterna selección desde la fila y sincroniza el checkbox.
  - `toggleFinalizarCheck(id)` (global, `:265`): alterna el id en `selectedIds`, sincroniza checkboxes y footer.
  - `syncCheckboxesFinalizar()` (`:277`): refleja `selectedIds` en los checkboxes del DOM.
  - `tieneSeleccionSinProduccion()` (`:284`): `true` si alguna orden seleccionada no tiene producción o es 0 (regla de negocio que bloquea finalizar).
  - `updateFinalizarFooter()` (`:295`): actualiza texto de seleccionados y habilita/deshabilita el botón (se deshabilita si hay selección sin producción).
  - `confirmarFinalizar()` (global, `:310`): confirma con SweetAlert2 y, si procede, ejecuta.
  - `ejecutarFinalizar()` (`:331`): **async**; `fetch(ROUTES.procesar, POST)` con `X-CSRF-TOKEN` y `{ ids }` → **POST `/planeacion/utileria/finalizar/procesar`**; muestra éxito/error y recarga las órdenes del telar.
  - `escHtml(text)` (`:382`): escapa HTML.
  - Listener de clic en backdrop (`:389`): cierra el modal al hacer clic fuera.
- **Endpoints consumidos:** GET finalizar.telares, GET finalizar.ordenes, POST finalizar.procesar.

### 6.4 `resources/views/planeacion/utileria/mover-ordenes.blade.php`
- **Propósito:** modal de dos paneles (origen/destino) con drag-and-drop para mover y reordenar órdenes entre telares.
- **Secciones UI:** modal `#modalMover`; panel origen (`#panelOrigenContainer`, select `#moverSelectOrigen`, tabla `#moverOrigenTbody`, dropzone) y panel destino análogo; footer con resumen `#moverResumen`, botón Revertir `#btnMoverRevertir` y Guardar `#btnMoverConfirm`. Handlers de drag en los contenedores (`handleDragOverContainer`, etc.). JS en IIFE bajo `@push('scripts')` con `moverState` y `MOVER_ROUTES`.
- **Funciones JS inline (`<script>`):**
  - `tipoSalonDisplay(salon)` (`:158`): normaliza el nombre de salón para mostrar (JACQUARD/SMIT/KARL MAYER).
  - `mismoTelar(a, b)` (`:167`): compara dos telares por salón+telar.
  - `syncTelarSelects()` (`:172`): deshabilita en cada select el telar elegido en el otro (evita origen == destino).
  - `escHtml(text)` (`:194`): escapa HTML.
  - `abrirModalMover()` (global, `:200`): **async**; muestra modal, resetea y carga telares.
  - `cerrarModalMover()` (global, `:206`): cierra el modal; si hay cambios sin guardar, confirma con SweetAlert2.
  - `revertirCambios()` (global, `:227`): **async**; descarta cambios y recarga registros de origen/destino desde el servidor.
  - `resetMoverState()` (`:236`): limpia estado, selects, tablas, badges y botones.
  - `cargarTelaresMover()` (`:268`): **async**; `fetch(MOVER_ROUTES.telares)` → **GET `/planeacion/utileria/mover/telares`**; llena ambos selects y sincroniza.
  - `checkIfChanged()` (`:292`): compara el orden actual vs. el original (origen y destino) para fijar `hasChanges` y actualizar botones.
  - `cargarRegistrosOrigen()` (global, `:329`): **async**; valida cambios pendientes; fija telar origen y llama `fetchRegistros('origen')`.
  - `cargarRegistrosDestino()` (global, `:364`): **async**; análogo para destino.
  - `fetchRegistros(panel)` (`:398`): **async**; `fetch(MOVER_ROUTES.registros + ?salon=&telar=)` → **GET `/planeacion/utileria/mover/registros`**; carga registros del panel y los renderiza.
  - `renderPanel(panel)` (`:441`): renderiza un panel; calcula badges ("A mover", "Modificado", "En proceso", "Repaso"), draggable, indicador de posición y producción formateada.
  - `handleDragStart(event, panel, id)` (global, `:526`): inicia el arrastre; guarda `draggedItemInfo`.
  - `handleDragEnd(event)` (global, `:536`): limpia estilos de arrastre.
  - `handleRowDragOver(event)` (global, `:544`): resalta borde superior de la fila destino durante el drag.
  - `handleRowDragLeave(event)` (global, `:557`): quita el resaltado de la fila.
  - `handleRowDrop(event, targetPanel, targetIndex)` (global, `:562`): suelta sobre una fila concreta; reordena/mueve el item al índice destino.
  - `handleDragOverContainer(event, targetPanel)` (global, `:596`): resalta el panel contenedor al arrastrar sobre él.
  - `handleDragLeaveContainer(event, targetPanel)` (global, `:605`): quita el resaltado del panel.
  - `handleDropContainer(event, targetPanel)` (global, `:615`): suelta al final del panel (no sobre fila); mueve el item.
  - `updateMoverButtons()` (`:643`): habilita Guardar/Revertir y pinta el resumen según `hasChanges`.
  - `confirmarMover()` (global, `:661`): confirma con SweetAlert2 y ejecuta.
  - `ejecutarMover()` (`:680`): **async**; arma `payload` (ordenes_origen/destino + salón/telar) y `fetch(MOVER_ROUTES.procesar, POST)` con `X-CSRF-TOKEN` → **POST `/planeacion/utileria/mover/procesar`**; muestra éxito/error y recarga registros.
  - Listener de clic en backdrop (`:740`): cierra el modal.
- **Endpoints consumidos:** GET mover.telares, GET mover.registros, POST mover.procesar.

---

## 7. JS dedicado

Este ámbito **no tiene archivos `.js` dedicados** en `resources/js/`. Toda la lógica JavaScript vive **inline** en los `<script>` de los blades descritos en la sección 6 (IIFEs que exponen algunas funciones en `window.*` para los manejadores `onclick`/`onchange` del HTML). El ámbito no migró aún a `window.http` / `window.notify`: usa `fetch(...).then(r => r.json())` crudo y SweetAlert2 directamente.

Funciones expuestas globalmente (en `window`), por blade:
- **Alineación:** ninguna (todo encapsulado en el IIFE).
- **Finalizar:** `abrirModalFinalizar`, `cerrarModalFinalizar`, `cargarOrdenesFinalizar`, `toggleFinalizarRow`, `toggleFinalizarCheck`, `confirmarFinalizar`.
- **Mover:** `abrirModalMover`, `cerrarModalMover`, `revertirCambios`, `cargarRegistrosOrigen`, `cargarRegistrosDestino`, `handleDragStart`, `handleDragEnd`, `handleRowDragOver`, `handleRowDragLeave`, `handleRowDrop`, `handleDragOverContainer`, `handleDragLeaveContainer`, `handleDropContainer`, `confirmarMover`.

---

## 8. Lógica de negocio y reglas

### Cálculos y fórmulas
- **Días por ejecutar** (Alineación, server): `DiasPorEjecutar = round(SaldoPedido / ProdKgDia, 2)`; si `ProdKgDia ≤ 0` o nulo ⇒ `'ABIERTO'` (`AlineacionController.php:246-251`).
- **Días de prod.** (`DiasEficiencia`, Alineación, **cliente**): `(hoy - FechaTejido)` en días con 1 decimal (negativo ⇒ `0`) (`index.blade.php:397-422`).
- **Rango Peso/Muestra por tolerancia "N"**: base/1.03, base/1.00, base/1.05 ⇒ `[min, max]` enteros; solo aplica si `Tolerancia == 'N'` y base > 0 (`AlineacionController.php:268-277`).
- **Normalización de eficiencia** (Revivir): si el valor > 1 se divide entre 100 (porcentaje → fracción) (`RevivirOrdenProgramaDesdeCatService.php:376`).
- **Posiciones**: `Posicion` es secuencial **por telar** (1, 2, 3…). Para evitar violar el índice único `(NoTelarId, Posicion)` al reasignar, se usa el truco de bump temporal `ISNULL(Posicion,0) + 10000` antes de aplicar las nuevas posiciones (Finalizar y Mover).
- **Fechas encadenadas**: `DateHelpers::recalcularFechasSecuencia()` recalcula `FechaInicio`/`FechaFinal` en cadena por telar, anclando al primer registro con fecha.

### Restricciones / validaciones de negocio
- **Finalizar:** `ids` requerido (array de enteros ≥ 1). Solo se procesan registros con `NoProduccion` válido. En el **front**, no se permite finalizar si alguna orden seleccionada tiene producción nula o 0 (`tieneSeleccionSinProduccion`); el botón se deshabilita y el footer avisa. (Esta regla es solo client-side; el backend no la replica.)
- **Mover:** payload validado con `Validator`. `solo_reorden_origen` permite reordenar un único telar. El front impide seleccionar el mismo telar como origen y destino (`syncTelarSelects`) y bloquea cambiar de telar con cambios sin guardar.
- **Revivir:** orden, telar y departamento obligatorios; no se puede revivir si ya existe la orden en el mismo salón+telar (anti-duplicado).
- **EnProceso** invariante por telar: tras cualquier operación, **solo un registro** por telar queda `EnProceso = 1` (el de `Posicion = 1` en Mover/Revivir; el primer restante en Finalizar).

### Flujos completos y efectos colaterales
- **Al finalizar una orden:** transferencia de saldo al líder si es `OrdCompartida` → `FechaFinaliza = now()` → sincronización a `CatCodificados` (fechas, pedido/producción/saldos) → auditoría en `OrdenFinalizadaAuditoria` → **eliminación física** del registro → reasignación de "en proceso" del telar → recálculo de fechas/posiciones → `regenerarLineas()` (observer).
- **Al mover órdenes:** reasignación de telar/salón/posición → si cambia de salón, ajuste de `Maquina`, `EficienciaSTD`, `VelocidadSTD`, `CuentaRizo`, `CuentaPie` desde el modelo destino → recálculo de fechas → normalización de `EnProceso` → `regenerarLineas()` (observer) → sincronización de `CatCodificados` solo para los que cambiaron de salón.
- **Al revivir:** se limpia `FechaFinaliza` del catálogo, se inserta al final del telar, se calculan fórmulas de eficiencia y se regeneran líneas. Los observers se **suprimen durante la transacción** y se restauran al final.
- **Disparo del observer `ReqProgramaTejidoObserver`:** todas las operaciones de escritura llaman a `ReqProgramaTejido::regenerarLineas()` **fuera de la transacción** (excepto Revivir, que lo hace dentro tras suprimir observers temporalmente). Esto regenera las líneas diarias de producción y recalcula fórmulas; es el principal efecto colateral inter-módulo (Tejido / líneas / OEE).

### Integraciones
- **Telegram:** ninguna en este ámbito.
- **Excel import/export:** ninguno en este ámbito.
- **PDF:** ninguno en este ámbito.
- **Sincronización de catálogo:** vía `MovimientoDesarrolladorService` (`CatCodificados` / `ReqModelosCodificados`) y `VincularTejido` (OrdPrincipal de órdenes compartidas).

### Permisos (`userCan`)
**Ningún controller de este ámbito invoca `userCan()` ni `userPermissions()`**, y las rutas no declaran middleware de permiso por módulo (solo `auth`). El control de acceso efectivo es la autenticación y la visibilidad del menú (módulos en `SYSRoles`/`SYSUsuariosRoles`, campo con typo intencional `reigstrar`). Si se requiere control granular crear/modificar/eliminar para Utilería o Alineación, debería añadirse explícitamente.

### Folios y Turnos
Este ámbito **no usa `FolioHelper`** (no genera folios secuenciales) ni **`TurnoHelper`** (no depende del turno productivo). Las fechas se manejan con `Carbon::now()` y `DateHelpers`.
