# Programa Urdido-Engomado

> Generado automáticamente — documentación detallada del módulo

## 1. Propósito del módulo

El módulo **Programa Urdido-Engomado** (también "Programa Urd / Eng") es el puente del flujo
productivo textil entre la **Planeación de tejido** y los departamentos de **Urdido** y **Engomado**.
Su función es preparar los julios de hilo (rizo y pie) que alimentan los telares: reservar el
inventario físico de julios disponible en el ERP (TI-PRO), proyectar los requerimientos de metros/kilos
por semana, y generar las órdenes de urdido y engomado con sus listas de materiales (BOM) y consumos.

Submódulos / pantallas que abarca:

1. **Reservar y Programar** (`reservar-programar`) — pantalla principal. Muestra el inventario de
   telares activos y el inventario disponible de julios en TI-PRO. Permite **reservar** una pieza de
   julio para un telar, **liberar** una reserva, y lanzar el flujo de programación.
2. **Programación de Requerimientos** (`programacion-requerimientos`) — recibe los telares
   seleccionados, valida que sean consistentes (mismo tipo/calibre/hilo) y muestra el **resumen de 5
   semanas** de metros y kilos requeridos. Permite editar cuenta/calibre/hilo/tamaño por telar.
3. **Creación de Órdenes** (`creacion-ordenes`) — agrupa los telares, busca el BOM de urdido/engomado,
   carga los materiales disponibles, calcula consumos y crea las órdenes (UrdProgramaUrdido +
   UrdConsumoHilo + UrdJuliosOrden + EngProgramaEngomado), marcando los telares como programados.
4. **Karl Mayer** (`karl-mayer`) — pantalla dedicada a la máquina de urdido seccional Karl Mayer.
   Crea **solo** órdenes de urdido (UrdProgramaUrdido + UrdConsumoHilo + UrdJuliosOrden), sin engomado.
5. **Núcleos Urd/Eng** (`UrdEngomado/UrdEngNucleosController`) — catálogo CRUD de núcleos (salón/nombre)
   usado como dato de engomado. Sus vistas viven bajo `modulos/engomado/urd-eng-nucleos/`.

Permiso de referencia en todo el módulo: **`Programa Urd / Eng`** (acceso, crear, modificar, eliminar).

Conexiones SQL Server utilizadas:
- **`sqlsrv`** (BD principal): tablas locales del módulo (telares, reservas, programas, auditoría, folios).
- **`sqlsrv_ti`** (`TI_PRO`): inventario físico de julios y materiales, catálogos BOM/AX (`InventSum`,
  `InventDim`, `InventSerial`, `BOM`, `BOMTABLE`, `BOMVersion`, `ConfigTable`, `InventSize`).
- `sqlsrv_tow_pro`: **no** se usa directamente en este ámbito.

---

## 2. Rutas

Archivo: `routes/modules/programa-urd-eng.php`. Todas las rutas se cargan bajo el grupo
`Route::middleware(['auth'])` definido en `routes/web.php:8`, por lo que **todas requieren el
middleware `auth`**. No hay middleware de permiso a nivel de ruta: los permisos se verifican dentro de
los controllers con `userCan(...)`.

| Método | URI | Controller@método | Middleware | Permiso (userCan) |
|---|---|---|---|---|
| GET | `/programaurdeng` | `ReservarProgramarController@index` | auth | — (vista calcula can*) |
| GET (301) | `/programa-urd-eng` → `/programaurdeng` | redirect | auth | — |
| GET (301) | `/programaurdeng/reservaryprogramar` → `/programaurdeng` | redirect | auth | — |
| GET | `/programa-urd-eng/reservar-programar` | `ReservarProgramarController@index` | auth | — |
| GET | `/programa-urd-eng/programacion-requerimientos` | `ReservarProgramarController@programacionRequerimientos` | auth | — |
| GET | `/programa-urd-eng/programacion-requerimientos/grupo-by-telar` | `ReservarProgramarController@getGrupoByTelar` | auth | — |
| GET | `/programa-urd-eng/creacion-ordenes` | `ReservarProgramarController@creacionOrdenes` | auth | — |
| GET | `/programa-urd-eng/karl-mayer` | `ReservarProgramarController@karlMayer` | auth | — |
| POST | `/programa-urd-eng/programacion-requerimientos/resumen-semanas` | `ResumenSemanasController@getResumenSemanas` | auth | — |
| GET | `/programa-urd-eng/inventario-telares` | `InventarioTelaresController@getInventarioTelares` | auth | — |
| GET | `/programa-urd-eng/inventario-disponible` | `InventarioDisponibleController@disponible` | auth | — |
| POST | `/programa-urd-eng/inventario-disponible` | `InventarioDisponibleController@disponible` | auth | — |
| POST | `/programa-urd-eng/programar-telar` | `ReservarProgramarController@programarTelar` | auth | **crear** |
| POST | `/programa-urd-eng/actualizar-telar` | `ReservarProgramarController@actualizarTelar` | auth | **modificar** |
| POST | `/programa-urd-eng/reservar-inventario` | `ReservaInventarioController@reservar` | auth | **modificar** |
| POST | `/programa-urd-eng/liberar-telar` | `ReservarProgramarController@liberarTelar` | auth | **eliminar** |
| GET | `/programa-urd-eng/reservas/{noTelar}` | `InventarioDisponibleController@porTelar` | auth | — |
| POST | `/programa-urd-eng/reservas/cancelar` | `ReservaInventarioController@cancelar` | auth | — |
| GET | `/programa-urd-eng/reservas/diagnostico` | `InventarioDisponibleController@diagnosticarReservas` | auth | — |
| GET | `/programa-urd-eng/buscar-bom-urdido` | `BomMaterialesController@buscarBomUrdido` | auth | — |
| GET | `/programa-urd-eng/buscar-bom-engomado` | `BomMaterialesController@buscarBomEngomado` | auth | — |
| GET | `/programa-urd-eng/materiales-urdido` | `BomMaterialesController@getMaterialesUrdido` | auth | — |
| GET | `/programa-urd-eng/materiales-urdido-completo` | `BomMaterialesController@getMaterialesUrdidoCompleto` | auth | — |
| GET | `/programa-urd-eng/materiales-engomado` | `BomMaterialesController@getMaterialesEngomado` | auth | — |
| GET | `/programa-urd-eng/anchos-balona` | `BomMaterialesController@getAnchosBalona` | auth | — |
| GET | `/programa-urd-eng/maquinas-engomado` | `BomMaterialesController@getMaquinasEngomado` | auth | — |
| GET | `/programa-urd-eng/nucleos` | `UrdEngNucleosController@getNucleos` | auth | — |
| POST | `/programa-urd-eng/crear-ordenes` | `ProgramarUrdEngController@crearOrdenes` | auth | — (valida datos) |
| POST | `/programa-urd-eng/crear-orden-karl-mayer` | `CrearOrdenKarlMayerController@store` | auth | — |
| GET | `/programa-urd-eng/hilos` | `BomMaterialesController@obtenerHilos` | auth | — |
| GET | `/programa-urd-eng/tamanos` | `BomMaterialesController@obtenerTamanos` | auth | — |
| GET | `/programa-urd-eng/bom-formula` | `BomMaterialesController@getBomFormula` | auth | — |

Nombres de ruta: prefijo `programa.urd.eng.` (p. ej. `programa.urd.eng.reservar.inventario`).
La ruta `index` se llama `programa.urd.eng.index`.

> El CRUD de **Núcleos** (`UrdEngNucleosController@index/store/update/destroy/create/edit`) y su nombre
> de ruta `urd-eng-nucleos.*` no está definido en `programa-urd-eng.php`; solo el endpoint API
> `getNucleos` (`/programa-urd-eng/nucleos`) pertenece a este archivo de rutas. El resto del CRUD se
> registra en el módulo de Engomado.

---

## 3. Controllers

### 3.1 `ReservarProgramarController`
`app/Http/Controllers/ProgramaUrdEng/ReservarProgramar/ReservarProgramarController.php`
Inyecta `InventarioTelaresService` y `ProgramasUrdidoEngomadoService`. Constante `STATUS_ACTIVO = 'Activo'`.

#### `index()` — `:31`
- **Qué hace:** Renderiza la pantalla "Reservar y Programar". Carga hasta 1000 telares activos vía
  `telaresService->baseQuery()->limit(1000)->get()` y los normaliza.
- **Respuesta:** vista `modulos.programa_urd_eng.reservar-programar` con `inventarioTelares` (normalizado),
  `columnOptions` (definición de columnas de filtros, vía `columnOptionsData()`), y flags
  `canModificar` / `canCrear` / `canEliminar` calculados con `userCan(..., 'Programa Urd / Eng')`.
- **Tablas:** `tej_inventario_telares` (sqlsrv). En error registra log y devuelve la vista con colección vacía.

#### `programacionRequerimientos(Request $request)` — `:55`
- **Qué hace:** Pantalla de Programación de Requerimientos. Parsea el query `telares` (JSON) y lo
  **enriquece con el `id`** real de cada telar buscándolo en `tej_inventario_telares`
  (`enriquecerTelaresConId`). Carga las máquinas de urdido del catálogo (excluyendo `KM1`).
- **Request:** query `telares` = JSON de telares seleccionados.
- **Respuesta:** vista `modulos.programa_urd_eng.programacion-requerimientos` con `telaresSeleccionados`
  y `opcionesUrdido`.
- **Tablas:** `tej_inventario_telares` (sqlsrv), `URDCatalogoMaquinas` (Departamento='Urdido', Nombre!='KM1').

#### `getGrupoByTelar(Request $request): JsonResponse` — `:74`
- **Qué hace:** Devuelve el `Grupo` (destino) de `dbo.ReqTelares` por `NoTelarId` (opcionalmente acotado
  por `SalonTejidoId`).
- **Validación:** `notelarid` requerido (string, max 50); `salon_tejido_id` opcional (string, max 50).
- **Respuesta JSON:** `{success, grupo}` (grupo o null si no se encuentra).
- **Tablas:** `dbo.ReqTelares` (sqlsrv).

#### `creacionOrdenes(Request $request)` — `:107`
- **Qué hace:** Pantalla de Creación de Órdenes. Parsea el query `telares` (sin enriquecer id) y carga
  todas las máquinas de urdido (sin excluir KM1).
- **Respuesta:** vista `modulos.programa_urd_eng.creacion-ordenes` con `telaresSeleccionados` y `opcionesUrdido`.
- **Tablas:** `URDCatalogoMaquinas` (Departamento='Urdido').

#### `karlMayer()` — `:119`
- **Qué hace:** Renderiza la vista `modulos.programa_urd_eng.karl-mayer.crear-karl-mayer` (sin datos).

#### `programarTelar(Request $request): JsonResponse` — `:126`
- **Permiso:** `userCan('crear', 'Programa Urd / Eng')` → 403 si falla.
- **Validación:** `no_telar` requerido (string, max 50).
- **Qué hace:** Endpoint placeholder; **no persiste nada**, solo devuelve un mensaje de éxito
  "El telar X ha sido programado exitosamente". (La programación real se realiza en `crearOrdenes`.)
- **Respuesta JSON:** `{success, message, no_telar}`.

#### `actualizarTelar(Request $request): JsonResponse` — `:146`
- **Permiso:** `userCan('modificar', 'Programa Urd / Eng')` → 403 si falla.
- **Validación:** `no_telar` requerido; opcionales `tipo`, `metros`(numeric), `no_julio`, `no_orden`,
  `localidad`, `tipo_atado` (in: Normal,Especial), `hilo`, `cuenta`, `calibre`(numeric), `id`(int),
  `fecha`, `turno`, `folio`, `lote_proveedor`, `no_proveedor`, `solo_inventario`(bool).
- **Qué hace:** Separa los campos en dos grupos: **inventario** (`extraerCamposInventario`: metros,
  no_julio, no_orden→también LoteProveedor, localidad, tipo_atado, hilo, cuenta, calibre,
  lote_proveedor, no_proveedor) y **programas** (`extraerCamposProgramas`: hilo, cuenta, calibre, tipo).
  - Actualiza `tej_inventario_telares` (por `id`, o por no_telar+tipo+fecha+turno) vía
    `actualizarInventarioTelares()` (devuelve -1 → 404 "Telar no encontrado o no está activo").
  - Si hay campos de programas y `solo_inventario` es falso, toma el `no_orden` **desde la BD** del telar
    (no del request) y llama a `programasService->actualizar(...)` para actualizar UrdProgramaUrdido y
    EngProgramaEngomado **por Folio**. Desde "Programación de Requerimientos" se envía
    `solo_inventario=true` para no tocar programas.
- **Respuesta JSON:** `{success, message, detalle:{tej_inventario_telares, urd_programa_urdido, eng_programa_engomado}}`.
- **Tablas:** `tej_inventario_telares` (sqlsrv); indirectamente `UrdProgramaUrdido`/`EngProgramaEngomado`/`AuditoriaUrdEng` vía el servicio.

#### `liberarTelar(Request $request): JsonResponse` — `:227`
- **Permiso:** `userCan('eliminar', 'Programa Urd / Eng')` → 403 si falla.
- **Validación:** `id`(int opcional), `no_telar` requerido (max 50), `tipo` opcional.
- **Qué hace:** Libera la reserva de un telar:
  1. Busca el telar activo (`buscarTelarParaLiberar`, priorizando registros con `no_julio`+`no_orden`).
  2. Si no tiene `no_julio`/`no_orden` → 400 "no está reservado".
  3. **Elimina** las reservas activas de `InvTelasReservadas` que coincidan con
     `NoTelarId` + `Status='Reservado'` + `InventSerialId`(=no_julio) + `InventBatchId`(=no_orden)
     (+ `Tipo` si aplica), una por una.
  4. Resetea la notificación de tejedor coincidente (`TejNotificaTejedorModel`): `no_julio=null`,
     `no_orden=null`, `Reserva=0`.
  5. Limpia el telar: pone a null hilo, metros, no_julio, no_orden, ConfigId, InventSizeId, InventColorId,
     localidad, LoteProveedor, NoProveedor; y `Reservado=false`, `Programado=false`.
- **Respuesta JSON:** `{success, message, data (telar fresco), reservas_eliminadas}`.
- **Tablas:** `tej_inventario_telares`, `InvTelasReservadas`, `TejNotificaTejedorModel` (sqlsrv).

**Métodos privados relevantes:**
- `columnOptionsData()` `:324` — define las columnas de filtros para las tablas de telares e inventario
  (se envían con la vista para evitar una petición extra).
- `enriquecerTelaresConId(array)` `:366` — para cada telar sin `id` busca su registro en
  `tej_inventario_telares` por no_telar(+tipo+fecha+turno) y le asigna el `id`, `fecha`, `turno` reales.
- `parseTelaresFromQuery(?string)` `:417` — `json_decode` del query `telares` (sin doble urldecode).
- `obtenerTelarParaActualizar`, `extraerCamposInventario`, `extraerCamposProgramas`,
  `actualizarInventarioTelares` (devuelve count o -1), `buscarTelarParaLiberar`,
  `construirMensajeActualizacion`.

---

### 3.2 `BomMaterialesController`
`app/Http/Controllers/ProgramaUrdEng/ReservarProgramar/BomMaterialesController.php`
Inyecta `BomMaterialesService`. Todos los métodos devuelven JSON y delegan al servicio (conexión
`sqlsrv_ti`).

| Método | Línea | Request | Respuesta | Descripción |
|---|---|---|---|---|
| `buscarBomUrdido` | `:20` | query `q` | array de `{BOMID, NAME}` | Autocomplete de BOM de urdido (BOMTABLE, ITEMGROUPID='JUL-URD', BOMID LIKE 'URD %'). |
| `buscarBomEngomado` | `:32` | query `q` | array de `{BOMID, NAME}` | Autocomplete de BOM de engomado (ITEMGROUPID='JUL-ENG', BOMID LIKE 'ENG %'). |
| `getMaterialesUrdido` | `:44` | query `bomId` | array de materiales | Componentes del BOM de urdido (ItemId, BomQty agregado, ConfigId, ItemName). |
| `getMaterialesUrdidoCompleto` | `:59` | query `bomId`, `kilosTotal` | `{resumen, detalle}` | Para Karl Mayer: resumen (Articulo/Config/Consumo/Kilos) + detalle de inventario físico. |
| `getMaterialesEngomado` | `:72` | `itemIds[]`, `configIds[]` (query/body) | array de inventario | Inventario disponible de hilos para engomado (excluye ya consumidos). |
| `getAnchosBalona` | `:90` | query `cuenta`, `tipo` | `{success, data}` | Anchos de balona por cuenta/tipo (RizoPie); fallback a todos si no hay coincidencia. |
| `getMaquinasEngomado` | `:106` | — | `{success, data}` | Catálogo de máquinas de engomado (URDCatalogoMaquinas, Departamento='Engomado'). |
| `obtenerHilos` | `:117` | — | `{success, data}` | ConfigId distintos de `ConfigTable` (ItemId='JULIO-URDIDO'). |
| `obtenerTamanos` | `:128` | — | `{success, data}` | InventSizeId distintos de `InventSize` (ItemId='JULIO-URDIDO', PRO). |
| `getBomFormula` | `:139` | query `bomId` | `{success, bomFormulas[]}` | Fórmulas TE-PD-ENF% agregadas del programa de engomado. |

Validación explícita solo en `getAnchosBalona` (`cuenta`/`tipo` opcionales, string ≤50/≤20).
Todos capturan `\Throwable`, registran `Log::error` y devuelven 500.

---

### 3.3 `ResumenSemanasController`
`app/Http/Controllers/ProgramaUrdEng/ReservarProgramar/ResumenSemanasController.php`
Inyecta `ResumenSemanasService`.

#### `getResumenSemanas(Request $request): JsonResponse` — `:19`
- **Request:** `telares` (array JSON, vía body o query); `fallback_metros` (bool, default true).
- **Qué hace:** Parsea los telares (`parseTelares`, con `urldecode` si es string) y llama a
  `service->generar($telares, $usarFallbackMetros)`. El status HTTP es 200 si `success`, 400 si no.
- **Respuesta JSON:** `{success, data:{rizo[], pie[]}, semanas[]}` (o `message` en error). En excepción
  devuelve 500 con `semanas` construidas (5) y data vacía.
- **Tablas:** indirectas vía servicio (`ReqProgramaTejido`, `tej_inventario_telares`).

---

### 3.4 `InventarioTelaresController`
`app/Http/Controllers/ProgramaUrdEng/ReservarProgramar/InventarioTelaresController.php`
Inyecta `InventarioTelaresService`.

#### `getInventarioTelares(): JsonResponse` — `:21`
- **Qué hace:** Devuelve todo el inventario de telares activos (`baseQuery()`, ordenado por
  no_telar/fecha) normalizado.
- **Respuesta JSON:** `{success, data[], total}`.
- **Tablas:** `tej_inventario_telares` (sqlsrv).

---

### 3.5 `InventarioDisponibleController`
`app/Http/Controllers/ProgramaUrdEng/ReservarProgramar/InventarioDisponibleController.php`
Inyecta `InventarioReservasService`. Solo consultas GET.

#### `disponible(Request $request): JsonResponse` — `:27`
- **Request:** `filtros` (array `[{columna, valor}]`, body o query). Si hay filtros, valida que
  `columna` ∈ `InventarioReservasService::ALLOWED_FILTERS` y `valor` requerido.
- **Qué hace:** Normaliza filtros y llama `getDisponibleData()`: consulta el inventario físico de julios
  en **TI-PRO** y lo cruza con las reservas locales (marca `NoTelarId`/`ReservaId`/`SalonTejidoId`).
- **Respuesta JSON:** `{success, data[], total}`.
- **Tablas:** `InventSum`/`InventDim`/`InventSerial` (sqlsrv_ti); `InvTelasReservadas` (sqlsrv).

#### `porTelar(string $noTelar): JsonResponse` — `:59`
- **Qué hace:** Reservas activas (`Status='Reservado'`) de un telar, con su `dimKey` calculada.
- **Respuesta JSON:** `{success, data[], total}`.
- **Tablas:** `InvTelasReservadas` (sqlsrv).

#### `diagnosticarReservas(Request $request): JsonResponse` — `:70`
- **Request:** query `limit` (default 10), `noTelar` opcional.
- **Qué hace:** Herramienta de diagnóstico: lista las reservas activas más recientes con todas sus
  dimensiones y su `dimKey`.
- **Respuesta JSON:** `{success, data[], total}`.
- **Tablas:** `InvTelasReservadas` (sqlsrv).

---

### 3.6 `ReservaInventarioController`
`app/Http/Controllers/ProgramaUrdEng/ReservarProgramar/ReservaInventarioController.php`
Inyecta `InventarioReservasService`. Solo POST.

#### `reservar(Request $request): JsonResponse` — `:24`
- **Permiso:** `userCan('modificar', 'Programa Urd / Eng')` → 403 si falla.
- **Validación:** `NoTelarId` requerido (≤10), `ItemId` requerido (≤50), `tej_inventario_telares_id`
  requerido (int); dimensiones opcionales (`ConfigId`, `InventSizeId`, `InventColorId`,
  `InventLocationId`, `InventBatchId`, `WMSLocationId`, `InventSerialId`), `NoProveedor`, `Tipo`,
  `Metros`/`InventQty` (numeric), `ProdDate`/`fecha` (date), `turno` (int 1–3), `NumeroEmpleado`,
  `NombreEmpl`.
- **Qué hace:** Construye el registro de reserva: deriva `Fecha` (`parseFecha`, ignora 1900-01-01),
  `Turno`, `TejInventarioTelaresId`, `Status='Reservado'`, toma `NumeroEmpleado`/`NombreEmpl` del usuario
  autenticado, normaliza valores dimensionales y delega en `reservasService->ejecutarReserva()`.
- **Respuesta JSON:** `{success, created, message}`.
- **Tablas:** `InvTelasReservadas`, `tej_inventario_telares`, `TejNotificaTejedorModel` (sqlsrv).

#### `cancelar(Request $request): JsonResponse` — `:96`
- **Validación:** `Id` (int opcional); si no hay `Id`, `NoTelarId`+`ItemId` requeridos; dimensiones opcionales.
- **Qué hace:** Cancela reservas por `Id` o por la clave dimensional completa (cambia `Status` a
  'Cancelado'); libera la bandera `Reservado` del telar si ya no quedan reservas activas.
- **Respuesta JSON:** `{success, updated}`.
- **Tablas:** `InvTelasReservadas`, `tej_inventario_telares` (sqlsrv).

#### `parseFecha($fecha, $prodDate)` `:120` (privado) — normaliza fecha a `Y-m-d` ignorando la fecha
centinela `1900-01-01`, con fallback a `ProdDate`.

---

### 3.7 `ProgramarUrdEngController`
`app/Http/Controllers/ProgramaUrdEng/ReservarProgramar/ProgramarUrdEngController.php`
Constante `STATUS_ACTIVO = 'Activo'`. Orden de guardado documentado en el encabezado del archivo.

#### `crearOrdenes(Request $request): JsonResponse` — `:30`
- **Validación:** `grupo` (array requerido, con `salonTejidoId` opcional), `materialesEngomado` (array
  requerido), `construccionUrdido` (array requerido), `datosEngomado` (array requerido).
- **Validaciones de negocio adicionales:**
  - `grupo.salonTejidoId` (destino) no vacío → 422 "Debe seleccionar un destino".
  - `grupo.fibra`/`grupo.hilo` no vacío → 422 "La fibra/hilo es obligatoria".
- **Qué hace (en transacción `DB::beginTransaction()`):**
  1. Genera **Folio de consumo** `SSYSFoliosSecuencia::nextFolio('CambioHilo', 5)`.
  2. Genera **Folio URD/ENG** (`obtenerFolioUrdEng`: `nextFolio('URD/ENG',5)`, fallback `nextFolioById(14,5)`).
  3. Resuelve `bomFormula` (desde `datosEngomado.bomFormula` o vía `obtenerBomFormula` consultando `BOM`
     en sqlsrv_ti, ITEMID LIKE 'TE-PD-ENF%'), `loteProveedor` (primer `inventBatchId`),
     `fechaReq` (mínima fecha de los telares activos vía `obtenerFechaReq`).
  4. Crea **`UrdProgramaUrdido`** con todos los datos del grupo + empleado autenticado. Registra
     auditoría `AuditoriaUrdEng::registrar(TABLA_URDIDO, ..., ACCION_CREATE)`.
  5. Crea un **`UrdConsumoHilo`** por cada material de engomado (ItemId, dimensiones, kilos→InventQty,
     ProdDate, Conos, LoteProv/NoProv, FechaRegistro, FechaRequerimiento).
  6. Crea **`UrdJuliosOrden`** por cada fila de construcción con `julios` o `hilos`.
  7. Crea **`EngProgramaEngomado`** con datos del grupo + `datosEngomado` (Nucleo, NoTelas,
     AnchoBalonas, MetrajeTelas, Cuentados, MaquinaEng, BomEng, Obs, BomFormula, TipoAtado). Auditoría
     `ACCION_CREATE`.
  8. `marcarTelaresProgramados()`: por cada telar (split de `telaresStr`) pone `no_orden=folio`,
     `Programado=true` en `tej_inventario_telares`.
  9. `DB::commit()`.
- **Respuesta JSON:** `{success, message, data:{folio, folioConsumo, telares_actualizados}}`. En error
  `DB::rollBack()` y 422 (validación) / 500.
- **Tablas:** `UrdProgramaUrdido`, `UrdConsumoHilo`, `UrdJuliosOrden`, `EngProgramaEngomado`,
  `AuditoriaUrdEng`, `tej_inventario_telares`, `SSYSFoliosSecuencias` (sqlsrv); `BOM` (sqlsrv_ti).
- **Folios:** sí (FolioHelper vía `SSYSFoliosSecuencia::nextFolio`, secuencias 'CambioHilo' y 'URD/ENG').

**Métodos privados relevantes:** `obtenerFolioUrdEng`, `obtenerBomFormula`, `obtenerLoteProveedor`,
`obtenerFechaReq`, `marcarTelaresProgramados`, `normalizeTipo` (RIZO→Rizo/PIE→Pie), `parseProdDate`,
`camposCreateParaAuditoria` (formato "Campo: (vacío) -> valor").

---

### 3.8 `CrearOrdenKarlMayerController`
`app/Http/Controllers/ProgramaUrdEng/ReservarProgramar/CrearOrdenKarlMayerController.php`
Crea **solo** órdenes de urdido para Karl Mayer (NO crea engomado).

#### `store(Request $request): JsonResponse` — `:26`
- **Validación extensa:** `no_telar` requerido (≤50), `barras` requerido (≤20), `fibra` requerido (≤100),
  `tamano` requerido (≤50), `cuenta`/`calibre` opcionales, `metros` requerido (numeric ≥0),
  `fecha_programada` requerido (date), `tipo_atado` requerido (in: Normal,Especial), `bom_id` requerido
  (≤50), `lote_proveedor`/`observaciones` opcionales; `julios[]`, `hilos[]` requeridos; `obs[]` opcional;
  `materiales[]` requerido con subcampos por material (itemId, configId, dimensiones, kilos, conos,
  loteProv, noProv, prodDate).
- **Qué hace (en transacción):**
  1. Folio de consumo `nextFolio('CambioHilo',5)` y Folio `obtenerFolioUrdEng()`.
  2. Crea **`UrdProgramaUrdido`** con `SalonTejidoId='Karl Mayer'`, `MaquinaId='Karl Mayer'`,
     `Status='Programado'`, `Kilos=null`, `RizoPie`=barras. Auditoría `ACCION_CREATE`.
  3. Crea **`UrdConsumoHilo`** por material; deriva `InventBatchId` del prefijo de `InventSerialId`
     (`derivarInventBatchId`, p. ej. `00061-744`→`00061`); `Status='Programado'`.
  4. Crea **`UrdJuliosOrden`** emparejando `julios[i]`/`hilos[i]`/`obs[i]` (filas con julios o hilos).
  5. `DB::commit()`.
- **Respuesta JSON:** `{success, message, data:{folio, folio_consumo}}`; 422/500 en error.
- **Tablas:** `UrdProgramaUrdido`, `UrdConsumoHilo`, `UrdJuliosOrden`, `AuditoriaUrdEng`,
  `SSYSFoliosSecuencias` (sqlsrv).
- **Folios:** sí ('CambioHilo' y 'URD/ENG').

**Métodos privados:** `obtenerFolioUrdEng`, `emptyToNull`, `parseFloatOrNull`, `derivarInventBatchId`,
`parseProdDate`, `camposCreateParaAuditoria`.

---

### 3.9 `UrdEngNucleosController`
`app/Http/Controllers/UrdEngomado/UrdEngNucleosController.php` — CRUD del catálogo de núcleos
(`UrdEngNucleos` en sqlsrv).

| Método | Línea | Request | Respuesta | Descripción |
|---|---|---|---|---|
| `index` | `:14` | query `q`, `per_page` (def 15) | vista `modulos.engomado.urd-eng-nucleos.index` | Lista paginada con búsqueda por Salon/Nombre. |
| `store` | `:35` | `Salon` (req ≤50), `Nombre` (req ≤120) | redirect back con success/error | Crea núcleo; valida duplicado (Salon+Nombre). |
| `update` | `:67` | `Salon`, `Nombre` (mismas reglas) | redirect back | Actualiza núcleo; valida duplicado excluyendo el actual. |
| `destroy` | `:100` | `UrdEngNucleos` (route model) | redirect back | Elimina núcleo. |
| `create` | `:113` | — | redirect a `urd-eng-nucleos.index` | Redirección (no usa vista propia). |
| `edit` | `:118` | `UrdEngNucleos` | redirect a `urd-eng-nucleos.index` | Redirección. |
| `getNucleos` | `:126` | — | `{success, data[]}` | API para selects: `{id, salon, nombre, value, text}`. |

- **Tablas:** `UrdEngNucleos` (sqlsrv).
- **Único endpoint registrado en este ámbito:** `getNucleos` (`/programa-urd-eng/nucleos`).

---

## 4. Services y Helpers del ámbito

### 4.1 `InventarioTelaresService`
`app/Services/ProgramaUrdEng/InventarioTelaresService.php` — consulta y normalización del inventario de
telares. Const `STATUS_ACTIVO='Activo'`, `COLS_TELARES` (lista de columnas).
- `baseQuery()` `:21` — query builder sobre `tej_inventario_telares` filtrando `status='Activo'`,
  seleccionando las columnas de telares + `fecha_ymd` (CONVERT a `YYYY-MM-DD`; soporta sqlsrv y mysql).
- `normalizeTelares($rows)` `:41` — mapea cada fila a un array normalizado (id, no_telar, tipo, cuenta,
  calibre, fecha, turno, hilo, metros, no_julio, no_orden, tipo_atado, salon, reservado, programado).
- `validarYActualizarNoOrden($telares)` `:69` — **no-op** intencional (ya no toca `InvTelasReservadas`).
- `applyFiltros($query, $filtros)` `:74` — aplica filtros `{columna, valor}`: `fecha` (whereDate),
  `hilo` (igualdad case-insensitive), resto LIKE.
- `normalizeTipo($tipo)` `:97` — RIZO→'Rizo', PIE→'Pie', otro→null.
- Helpers privados: `normalizeTelar`, `str`, `num`, `normalizeDateFromRow`, `parseDateFlexible`.

### 4.2 `ProgramasUrdidoEngomadoService`
`app/Services/ProgramaUrdEng/ProgramasUrdidoEngomadoService.php` — actualiza UrdProgramaUrdido y
EngProgramaEngomado **solo por Folio**.
- `actualizar(string $noTelar, ?string $tipo, array $update, ?string $folio)` `:21` — mapea campos
  (`mapearCampos`: cuenta→Cuenta, calibre→Calibre, hilo→Fibra, tipo→RizoPie), y si hay `folio` no vacío
  busca el **primer** registro por Folio en `UrdProgramaUrdido` y `EngProgramaEngomado`, los actualiza
  (uno cada uno, no mass update) y registra auditoría `ACCION_UPDATE` con antes/después. Devuelve
  `{urdido, engomado}` (0/1 cada uno). Si no hay folio, registra warning y no actualiza.
- `buildCamposAntesDespues` (privado) — genera la cadena de auditoría comparando valor anterior/nuevo.
- `mapearCampos` (privado) — mapeo descrito arriba; usa `InventarioTelaresService::normalizeTipo`.
- **Regla clave:** nunca actualiza por `NoTelarId`/`no_telar` (puede repetirse); solo por `Folio`, que
  proviene del campo `no_orden` del telar en BD.

### 4.3 `InventarioReservasService`
`app/Services/ProgramaUrdEng/InventarioReservasService.php` — gestiona inventario disponible y reservas;
conecta a `sqlsrv_ti` (`TI_CONN`). Constantes: `DATAAREA='PRO'`, `LOC_TELA='A-JUL/TELA'`,
`LIMIT_TI=2000`, patrones `PATTERN_RIZO='%JU-ENG-RI%'`, `PATTERN_PIE='%JU-ENG-PI%'`. `ALLOWED_FILTERS` y
`FILTER_SQL` mapean campos del frontend a columnas de TI-PRO.
- `normalizeFilters($raw)` `:61` — fuerza formato `[{columna, valor}]`.
- `normalizeDimValue($v)` `:83` — null/'null' → '' (trim).
- `dimKey($obj)` `:98` — clave única de 8 dimensiones unidas por `|`; **es el cruce** entre el inventario
  físico de TI-PRO y las reservas locales.
- `getDisponibleData($filtros)` `:121` — separa el filtro local `NoTelarId` del resto; carga reservas
  activas en un mapa por `dimKey`; consulta TI-PRO (`queryDisponibleFromTiPro`); por cada fila marca
  `NoTelarId`/`ReservaId`/`SalonTejidoId` si está reservada; filtra por `NoTelarId` (soporta
  "disponible/null/vacío" para ver solo no reservados). Regla: si no está en TI-PRO, no se muestra.
- `getReservasPorTelar($noTelar)` `:211`, `getDiagnosticoReservas($noTelar, $limit)` `:226`.
- `ejecutarReserva($data)` `:244` — deriva `InventBatchId` del prefijo del serial; aplica la regla de
  notificación de tejedor (`aplicarReglaNotificaTejedorAntesDeReservar`); crea `InvTelasReservadas`
  (ignora duplicados por índice único, códigos SQL 2601/2627); actualiza el estado del telar
  (`actualizarEstadoTelarTrasReserva`: `Reservado=true` + dimensiones + LoteProveedor/NoProveedor).
- `aplicarReglaNotificaTejedorAntesDeReservar($data)` `:329` — si el tejedor reportó paro en el telar/tipo
  con `Reserva` pendiente: transfiere `hora`→`horaParo` del telar, adjunta no_julio/no_orden a la
  notificación y la marca `Reserva=1`.
- `ejecutarCancelar($input)` `:464` — cancela por `Id` o por clave dimensional (Status→'Cancelado');
  libera el telar si no quedan reservas activas (`liberarTelarSiNoHayReservasActivas`).
- `queryDisponibleFromTiPro($filtros, $limit)` `:529` — consulta a TI-PRO con
  **READ UNCOMMITTED + NOLOCK** (join InventSum/InventDim/InventSerial), `PhysicalInvent>0`, ItemId LIKE
  Rizo/Pie; aplica filtros dinámicos; restaura READ COMMITTED en `finally`.
- Helpers privados: `shouldExcludeByTelarFilter`, `actualizarEstadoTelarTrasReserva`,
  `obtenerTelarObjetivoParaNotificacion`, `resolverTipoReserva`, `normalizeTipoReserva`.

### 4.4 `ResumenSemanasService`
`app/Services/ProgramaUrdEng/ResumenSemanasService.php` — calcula el resumen de 5 semanas de
metros/kilos por telar. Const `STATUS_ACTIVO='Activo'`, `FALLBACK_METROS_DEFAULT=true`.
- `generar(array $telares, bool $usarFallbackMetros)` `:25` — construye 5 semanas; valida que los
  telares sean consistentes (`validarTelaresConsistentes`); obtiene hilos reales por telar; carga
  `ReqProgramaTejido` con sus líneas (rango de fechas); filtra programas por tipo (RIZO/PIE), hilo,
  calibre; procesa el resumen (`procesarResumenPorTipo`). Devuelve `{success, data:{rizo[], pie[]}, semanas[]}`.
- `construirSemanas(int $n=5)` `:328` — genera n semanas desde el lunes de la semana actual (etiqueta
  "Sem Actual", "Sem Actual +1"...).
- `validarTelaresConsistentes($telares)` `:366` — exige mismo tipo, mismo calibre (tolerancia 0.01) y,
  para RIZO, mismo hilo; devuelve error con mensaje o los valores de referencia.
- Helpers internos: `obtenerHiloPorTelar`, `cargarProgramas`, `filtrarProgramasRizo`,
  `procesarResumenPorTipo` (acumula metros/kilos por semana y clave telar|cuenta|hilo|modelo),
  `resolverLineas`, `formatearResumen`, `parseToCarbon`, `semanaIndex`, `agregarTotalesSemana`,
  `matchHilo` (case-insensitive; vacío = no filtra), `matchCalibre` (tolerancia 0.11; vacío exige nulo).
- **Tablas:** `ReqProgramaTejido` + relación `lineas` (MtsRizo/MtsPie/Rizo/Pie), `tej_inventario_telares` (sqlsrv).

### 4.5 `BomMaterialesService`
`app/Services/ProgramaUrdEng/BomMaterialesService.php` — todas las consultas BOM/inventario a
`sqlsrv_ti` (`CONN`), `DATAAREA='PRO'`.
- `buscarBomUrdido($q)` `:19` / `buscarBomEngomado($q)` `:37` — autocomplete sobre `BOMTABLE`.
- `getMaterialesUrdido($bomId)` `:55` — componentes del BOM (join BOM/BOMTABLE/INVENTDIM/INVENTTABLE,
  SUM(BOMQTY) por ITEMID+CONFIGID).
- `getMaterialesUrdidoCompleto($bomId, $kilosTotal)` `:91` — resumen (consumo×kilosTotal) + detalle de
  inventario físico (`getInventarioPorMaterialesUrdidoRaw`).
- `getInventarioPorMaterialesUrdidoRaw($itemIds, $configIds)` `:124` (privado) — inventario disponible
  (InventSum/InventDim/InventSerial, localidades A-MP/A-MPBB) excluyendo materiales ya consumidos.
- `getInventarioPorMaterialesUrdido(...)` `:202` (privado, **@deprecated**).
- `getMaterialesEngomado($itemIds, $configIds)` `:279` — inventario de hilos para engomado, excluyendo
  consumidos en `UrdConsumoHilo`.
- `obtenerMaterialesConsumidosKeys($itemIds)` `:334` / `excluirMaterialesConsumidos(...)` `:373`
  (privados) — clave `ItemId|InventSerialId`; filtra `Registrado=1` si la columna existe.
- `getAnchosBalona($cuenta, $tipo)` `:392` — `EngAnchoBalonaCuenta` por Cuenta/RizoPie; fallback a todos.
- `getMaquinasEngomado()` `:416` — `URDCatalogoMaquinas` Departamento='Engomado'.
- `obtenerHilos()` `:425` — `ConfigTable` (ItemId='JULIO-URDIDO'). `obtenerTamanos()` `:437` — `InventSize`.
- **BomFormula (AX):** `resolveBomIdFromFormulaItem` `:453`, `getBomFormulasWithFallback` `:486`,
  `getBomFormulasAggregatedForEngProgram` `:510` (une fórmulas de todos los BOM ENG que comparten el
  mismo ItemId padre en `BOMVersion`), `resolveParentItemIdsForEngBom`, `resolveEngBomIdsForParentItems`,
  `getBomFormulas` `:606` (ITEMID LIKE 'TE-PD-ENF%'), `getBomFormula` `:638` (primera fórmula).

> No hay uso de `FolioHelper`/`TurnoHelper` en los services; los folios se generan directamente con
> `SSYSFoliosSecuencia::nextFolio()` en los controllers de creación de órdenes.

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `UrdEngomado\UrdEngNucleos` | `UrdEngNucleos` | sqlsrv | `Id` | Salon, Nombre |
| `Tejido\TejInventarioTelares` | `tej_inventario_telares` | sqlsrv (default) | `id` | no_telar, tipo, status, hilo, metros, no_julio, no_orden, Reservado, Programado, ConfigId, InventSizeId, InventColorId, LoteProveedor, NoProveedor, horaParo |
| `Inventario\InvTelasReservadas` | `InvTelasReservadas` | sqlsrv (default) | `Id` | NoTelarId, ItemId, dimensiones (ConfigId/InventSizeId/InventColorId/InventLocationId/InventBatchId/WMSLocationId/InventSerialId), Tipo, Status, Metros, InventQty, SalonTejidoId, TejInventarioTelaresId |
| `Urdido\UrdProgramaUrdido` | `UrdProgramaUrdido` | sqlsrv | `Id` | Folio, FolioConsumo, NoTelarId, RizoPie, Cuenta, Calibre, Fibra, InventSizeId, Metros, Kilos, SalonTejidoId, MaquinaId, BomId, Status, BomFormula, TipoAtado |
| `Urdido\UrdConsumoHilo` | `UrdConsumoHilo` | sqlsrv | `Id` | Folio, FolioConsumo, ItemId, dimensiones, InventQty, ProdDate, Status, Conos, LoteProv, NoProv, FechaRequerimiento, Registrado |
| `Urdido\UrdJuliosOrden` | `UrdJuliosOrden` | sqlsrv | `Id` | Folio, Julios, Hilos, Obs |
| `Engomado\EngProgramaEngomado` | `EngProgramaEngomado` | sqlsrv | `Id` | Folio, NoTelarId, RizoPie, Cuenta, Calibre, Fibra, Metros, Kilos, SalonTejidoId, MaquinaUrd, BomUrd, Nucleo, NoTelas, AnchoBalonas, MetrajeTelas, Cuentados, MaquinaEng, BomEng, BomFormula, TipoAtado |
| `Urdido\AuditoriaUrdEng` | `AuditoriaUrdEng` | sqlsrv | `id` | TABLA_URDIDO/TABLA_ENGOMADO, ACCION_CREATE/UPDATE, formatoCampo(), registrar() |
| `Engomado\EngAnchoBalonaCuenta` | `EngAnchoBalonaCuenta` | sqlsrv | `Id` | AnchoBalona, Cuenta, RizoPie |
| `Urdido\URDCatalogoMaquina` | `URDCatalogoMaquinas` | sqlsrv | `MaquinaId` | Nombre, Departamento (Urdido/Engomado) |
| `Planeacion\ReqTelares` | `dbo.ReqTelares` | sqlsrv (default) | `Id` | NoTelarId, SalonTejidoId, Grupo (=destino) |
| `Sistema\SSYSFoliosSecuencia` | `dbo.SSYSFoliosSecuencias` | sqlsrv | — | nextFolio($clave,$len), nextFolioById($id,$len) |
| `Tejedores\TejNotificaTejedorModel` | (notificaciones de tejedor) | sqlsrv | — | telar, tipo, hora, no_julio, no_orden, Reserva |
| `Planeacion\ReqProgramaTejido` | (programa de tejido) | sqlsrv | `Id` | NoTelarId, CuentaRizo/Pie, CalibreRizo/Pie, FibraRizo/Pie + relación `lineas` |

Tablas externas en **`sqlsrv_ti`** (TI_PRO) consultadas vía query builder (no modelos):
`InventSum`, `InventDim`, `InventSerial`, `BOM`, `BOMTABLE`, `BOMVersion`, `ConfigTable`, `InventSize`.

---

## 6. Vistas Blade

### 6.1 `reservar-programar.blade.php` (≈2262 líneas)
Pantalla principal. Botones en navbar: **Karl Mayer** (link), **Reservar** (`#btnReservar`),
**Liberar** (`#btnLiberarTelar`), **Programar** (`#btnProgramar`). Dos tablas: **telares**
(`#telaresTable`, datos pre-cargados desde el servidor) e **inventario disponible** (`#inventarioTable`).
Soporta ordenamiento por columnas, filtros por columna (modal SweetAlert) y menú contextual.

`API` (`:395`) mapea las rutas: `inventarioTelares`, `inventarioDisponible`(.get), `programarTelar`,
`actualizarTelar`, `reservarInventario`, `liberarTelar`. Flags `CAN_CREAR/CAN_MODIFICAR/CAN_ELIMINAR`.

Funciones JS inline destacadas (el script está organizado en objetos `Render`/`Filters`/`Sorting`):
- `getCsrfToken()` `:411` — lee el token CSRF del meta.
- `disable(el, v)` `:420`, `toast(icon, title, text, timer)` `:423` — helpers UI (SweetAlert toast).
- `http` (objeto, `:437`) — wrapper de `fetch` con manejo de 419/errores; expone `http.get`/`http.post`.
- `deriveInventBatchFromSerial(serialId, batchId)` `:505` — prefijo del serial (00061-744→00061).
- `matchLote(...)` `:513`, `matchCuenta(...)` `:521`, `sameGroup(a,b)` `:538`, `normalizeTipo(t)` `:543`,
  `isReservado(telar)` `:551`, `isProgramado(telar)` `:556` — utilidades de comparación/estado.
- Objeto **`Render`**: `salonBadge`/`tipoBadge`/`num`/`date`, `telares(rows)` `:661` (pinta la tabla de
  telares con checkbox por fila), `inventario(rows)` `:847` (pinta el inventario disponible aplicando
  el filtro por telar/cuenta/lote), `clearVisualRow`, `clearVisualInventario`, `clear`,
  `updateFiltroButton`, `toggleTelarCheckbox(row, checked)` `:1076` (selección individual vs múltiple),
  `applyTelar(row)`/`applyInventario(row)`, `validateButtons()` `:1263`, `updateBadge`, `filterLocal`.
- Objeto **filtros/modal**: `openModal()` `:1379` (modal SweetAlert de filtros, consume
  `API.inventarioDisponibleGet`), `applyColumnFilter`/`clearColumnFilter`/`clearTable`/`reset`.
- Objeto **Sorting**: `sorted(data, sorts)`, `toggle(table, col, additive)`, `updateSortIcons`, `bind`.
- Acciones principales:
  - `programar()` `:1616` — valida permiso crear y estado; arma el JSON de telares seleccionados y
    redirige a **Programación de Requerimientos** (`programa.urd.eng.programacion.requerimientos?telares=`).
  - `liberarTelar()` `:1703` — valida permiso eliminar; confirma; `http.post(API.liberarTelar)`;
    recarga inventario disponible y telares.
  - `reservar()` `:1766` — valida permiso modificar + telar + inventario seleccionado; arma el payload y
    `http.post(API.reservarInventario)`; recarga inventario/telares.
  - Edición inline de celdas: `http.post(API.actualizarTelar)` (`:1825`, `:1973`, `:2179`).
- `closeContextMenu`/`openContextMenu` `:2051` — menú contextual de columnas.

### 6.2 `programacion-requerimientos.blade.php` (≈1445 líneas)
Tabla de requerimientos (`#tablaRequerimientos`, editable) + tabla de resumen por 5 semanas
(`#tablaResumen`, metros y kilos). Botón navbar **Siguiente** (`#btnSiguiente`, deshabilitado hasta
validar). Rutas JS: `RUTA_RESUMEN` (resumen-semanas), `RUTA_ACTUALIZAR_TELAR`, `RUTA_GRUPO_BY_TELAR`,
`RUTA_HILOS`, `RUTA_TAMANOS`.

Funciones JS inline:
- `normalizarDestino(destino)` `:116`, `mapearSalonADestino(salon)` `:131`, `setDestinoEnFila(row, grupo)`
  `:141` — mapeo de salón→destino para cada fila.
- `cargarDestinoDesdeReqTelares()` `:153` — por cada telar consulta `RUTA_GRUPO_BY_TELAR` y fija el destino.
- `todayISO()` `:192`, `escapeHtml(value)` `:197`, `normalizarTipo(tipo)` `:207`, `normalizeInput(arr)`
  `:215`, `formatNumberInput`/`parseNumberInput` `:224`/`:232` — utilidades.
- `validarGrupo(telares)` `:239` — valida consistencia (mismo tipo/calibre/hilo) en cliente.
- `cargarHilos()` `:264` (consume `RUTA_HILOS`), `cargarTamanos()` `:288` (consume `RUTA_TAMANOS`) —
  llenan los datalists/selects de hilo y tamaño.
- `agruparPorCuenta(telares)` `:312`, `crearFila(grupo, index)` `:324`, `renderTabla()` `:415`,
  `reordenarColumnasTablaRequerimientos()` `:479` — render de la tabla editable.
- `rellenarCuentaYCalibreDesdeTamano(fila, {guardar})` `:513` — al elegir tamaño, autocompleta
  cuenta/calibre y, si `guardar`, persiste vía `guardarCampoTelar`.
- `agregarEventListenersCamposEditables()` `:610` — enlaza inputs editables (hilo, cuenta, calibre,
  tamaño, tipo, tipo_atado, urdido) y dispara guardado.
- `guardarCampoTelar(campo, valor, telarId, tipo, fila, options)` `:778` — `fetch(RUTA_ACTUALIZAR_TELAR)`
  con `solo_inventario` para actualizar solo `tej_inventario_telares`; luego refresca el resumen.
- `renderResumenMensaje(msg)` `:888`, `cargarResumenDesdeServidor(validacion)` `:899`
  (`fetch(RUTA_RESUMEN)` POST con los telares), `renderResumen(data, validacion, semanas)` `:943`
  (pinta filas rizo/pie y totales por semana).
- `fmtNum(n)` `:1256`, `actualizarEstadoBotonSiguiente()` `:1262`, `validarCamposRequeridos()` `:1298` —
  habilitan el botón Siguiente.
- Click en **Siguiente** `:1426` — redirige a **Creación de Órdenes**
  (`programa.urd.eng.creacion.ordenes?telares=`).

### 6.3 `creacion-ordenes.blade.php` (≈317 líneas)
5 tablas: requerimientos agrupados (`#tablaOrdenes`), materiales urdido (`#tablaMaterialesUrdido`),
materiales engomado (`#tablaMaterialesEngomado`, con checkboxes y totales), construcción urdido
(`#tablaConstruccionUrdido`, 4 filas de julios/hilos/obs) y datos de engomado (`#tablaDatosEngomado`:
núcleo, no. telas, ancho balonas, metraje, cuendeados, máquina, L Mat Engomado, Bom Formula, obs).
Botón navbar **Crear Órdenes** (`onclick="crearOrdenes()"`).

JS inline en la vista (`:282`): limita en tiempo real "No. Julios" a 15 e invoca
`window.initCreacionOrdenes({telaresData, destinoOptions, routes})` con todas las rutas del módulo. El
grueso de la lógica está en el JS dedicado (sección 7). Carga el script
`public/js/modulos/programa_urd_eng/creacion-ordenes.js` con cache-busting por `filemtime`.

### 6.4 `karl-mayer/crear-karl-mayer.blade.php` (≈1075 líneas)
Formulario `#form-karl-mayer` (POST a `programa.urd.eng.crear.orden.karl.mayer`) con: No. Telar (401/402),
Barras, Fibra, Tamaño, Cuenta, Calibre, Metros, Fecha programada, Tipo Atado, BOM (L.Mat Urdido), Lote
proveedor, Observaciones; tabla de 4 filas de julios/hilos/obs; tabla resumen de materiales y tabla
detalle de inventario (con checkboxes y totales). Botón navbar **Crear Orden** (submit). `ROUTES`
(`:255`): `buscarBomUrdido`, `materialesCompleto`, `hilos`, `tamanos`, `crearOrdenKarlMayer`,
`indexProgramaUrdEng`.

Funciones JS inline:
- Utils: `debounce` `:291`, `toFloat`/`toInt`/`toNumber` `:299`/`:304`/`:309`, `escapeHtml` `:315`,
  `formatKilos` `:321`, `formatFecha` `:326`, `notifyWarning` `:344`, `setTableMessage` `:353`.
- `resetTotales()` `:357`, `actualizarTotalesSeleccionados()` `:365` — totales de conos/kilos seleccionados.
- `renderResumen(rows)` `:397` — pinta la tabla resumen (Articulo/Config/Consumo/Kilos).
- `getDetalleCellClass(index)` `:413`, `ordenarMateriales(materiales, column, direction)` `:415`,
  `actualizarIconosOrdenamiento(column, direction)` `:445`, `initOrdenamientoTabla()` `:453` —
  ordenamiento de la tabla detalle.
- `getSelectedIds()` `:510`, `renderDetalle(materiales, preserveSelection)` `:515` — render detalle inventario.
- `buildUrl(baseUrl, params)` `:575`, `fetchJson(url)` `:585` — HTTP.
- `cargarHilosYTamanos()` `:593` — consume `ROUTES.hilos` y `ROUTES.tamanos`.
- `rellenarCuentaYCalibreDesdeTamano()` `:617` — autocompleta cuenta/calibre desde el tamaño.
- `cargarMaterialesLmat()` `:634` — consume `ROUTES.materialesCompleto` (resumen + detalle) por BOM.
- `getMaterialesSeleccionados()` `:697` — recoge los materiales con checkbox marcado.
- `validarFormulario()` `:743` — valida campos requeridos antes de enviar.
- `actualizarEstadoBotonCrear()` `:763`, `bindEvents()` `:768` — eventos del formulario, dropdown de
  tamaño (`renderTamanoOpciones`/`seleccionarTamano`/`cerrarTamanoDropdown`), bloqueo de lote, y el
  submit que hace `fetch(ROUTES.crearOrdenKarlMayer)` (`:997`) con la fecha de requerimiento (SweetAlert
  `datetime-local`) y al éxito redirige a `ROUTES.indexProgramaUrdEng`.

---

## 7. JS dedicado

### `public/js/modulos/programa_urd_eng/creacion-ordenes.js` (≈1504 líneas, IIFE)
Expone globalmente **`window.initCreacionOrdenes(cfg)`** y **`window.crearOrdenes()`**.

Funciones internas principales:
- Utils: `qs`/`qsa`, `isNil`/`isBlank`, `toNumber`, `fmtNumber`, `fmtDate`, `normalizarTipo`, `safeJSON`
  (localStorage), `fetchJSON`, `debounce`, `positionDropdown`.
- Destino: `getDestinoOptions`, `normalizeDestinoValue`, `grupoRequiereDestinoManual`,
  `destinoPorTelar(noTelar)` (mapea rangos de telar→destino: 207-211 Jacquard Sulzer; 201-206/213-215
  Jacquard Smit; 305-316 Smit; 303/304/317/318 Itema Viejo; 299-302/319/320 Itema Nuevo),
  `getInitialDestino`, `buildDestinoOptionsHtml`, `syncDestinoSelectState`.
- LS helpers (`getMateriales`/`setMateriales`/`wipeMateriales`/`getSelecciones`/`setSelecciones`).
- `normalizeInput(arr)` — normaliza los telares de entrada. `agruparTelares(telares)` — agrupa por
  `cuenta|tipo|urdido|tipoAtado` (solo los marcados `agrupar`; el resto van como singles); suma
  metros/kilos.
- `renderTabla()` — pinta la tabla principal con `<select>` de destino (multi-telar) e input de BOM por fila.
- `seleccionarFila(filaId)` — al seleccionar, carga materiales de urdido (`cargarMaterialesUrdido`),
  anchos de balona y metraje.
- Autocompletes: `setupAutocomplete`, `initAutocompleteBOMUrdido` (consume `routes.buscarBomUrdido`),
  `initAutocompleteBOMEngomado` (consume `routes.buscarBomEngomado`).
- `cargarBomFormula(bomId)` — consume `routes.bomFormula`; acumula fórmulas en un `Set` y llena
  `#inputBomFormula`.
- `cargarMaterialesUrdido(bomId, kilos, forzar)` / `renderTablaMaterialesUrdido` /
  `renderTablaMaterialesUrdidoDesdeStorage` — consume `routes.materialesUrdido`; calcula consumo×kilos.
- `cargarMaterialesEngomado(itemIds, configIds, bomId, forzar)` / `renderTablaMaterialesEngomado` —
  consume `routes.materialesEngomado`; checkboxes con persistencia en localStorage.
- Ordenamiento: `ordenarMateriales`, `actualizarIconosOrdenamiento`, `initOrdenamientoTabla`.
- Checkboxes/totales: `agregarEventListenersCheckboxes`, `guardarSeleccionesCheckboxes`,
  `actualizarTotalesMaterialesEngomado`, `restaurarSelecciones`, `actualizarEstadoBotonCrear`.
- Selects: `cargarAnchosBalona` (`routes.anchosBalona`), `cargarMaquinasEngomado`
  (`routes.maquinasEngomado`), `cargarNucleos` (`routes.nucleos`), `limpiarSelectAnchosBalona`.
- `actualizarMetrajeTelas()` — calcula metraje = metros / no. telas.
- `initCreacionOrdenes(cfg)` — bootstrap (datos de telares desde cfg o query `telares`).
- `crearOrdenes()` — valida fila/BOM/destino/fibra/materiales/construcción/datos de engomado; pide la
  fecha de requerimiento (SweetAlert `datetime-local`); `fetch(routes.crearOrdenes)` POST con el payload
  `{grupo, materialesEngomado, construccionUrdido, datosEngomado, fechaRequerimiento}`; limpia
  localStorage y redirige a `/programa-urd-eng/reservar-programar`.

---

## 8. Lógica de negocio y reglas

### Flujo completo (de inicio a fin)
1. **Reservar y Programar** (`index`): el planeador ve los telares activos y el inventario de julios
   disponible (TI-PRO). Selecciona un telar + una pieza de inventario y la **reserva**
   (`ReservaInventarioController@reservar`): se crea `InvTelasReservadas` (Status='Reservado') y el telar
   queda `Reservado=true` con sus dimensiones (ConfigId, InventSizeId, InventColorId, LoteProveedor).
2. Marca varios telares y pulsa **Programar** → redirige a **Programación de Requerimientos** pasando los
   telares por query.
3. **Programación de Requerimientos**: valida que los telares sean consistentes y muestra el **resumen de
   5 semanas** (`ResumenSemanasController@getResumenSemanas` → `ResumenSemanasService`). El usuario puede
   ajustar cuenta/calibre/hilo/tamaño, que se guardan en `tej_inventario_telares` con
   `solo_inventario=true` (no toca programas). Pulsa **Siguiente** → **Creación de Órdenes**.
4. **Creación de Órdenes**: agrupa los telares, busca el BOM de urdido, carga sus materiales y consumos,
   carga el inventario de engomado disponible (excluyendo el ya consumido), pide núcleo/máquina/etc., y al
   **Crear Órdenes** (`ProgramarUrdEngController@crearOrdenes`) genera, en una sola transacción:
   `UrdProgramaUrdido` + N `UrdConsumoHilo` + N `UrdJuliosOrden` + `EngProgramaEngomado`, con folios
   `URD/ENG` y `CambioHilo`, y marca los telares como `Programado=true` con `no_orden=folio`.
5. **Karl Mayer** es un flujo paralelo simplificado: crea solo la parte de urdido
   (`CrearOrdenKarlMayerController@store`), con `SalonTejidoId='Karl Mayer'` y `Status='Programado'`.

### Cálculos y fórmulas
- **Consumo de material (urdido):** `kilos = kilosProgramados × BomQty` (BomQty = SUM(BOMQTY) del BOM).
- **Metraje de telas (engomado):** `metraje = metros / no_telas`.
- **dimKey:** concatenación de 8 dimensiones (`ItemId|ConfigId|InventSizeId|InventColorId|
  InventLocationId|InventBatchId|WMSLocationId|InventSerialId`) — clave de cruce TI-PRO ↔ reservas.
- **InventBatchId derivado:** prefijo de `InventSerialId` antes del primer `-` (p. ej. 00061-744→00061).
- **Resumen por semana:** 5 semanas desde el lunes actual; suma `MtsRizo`/`MtsPie` (con fallback al otro
  si es 0) y kilos (`Rizo`/`Pie`) por semana; tolerancias: calibre 0.01 (validación de grupo) / 0.11
  (match de resumen).
- **FechaReq de la orden:** mínima fecha de los telares activos involucrados.

### Restricciones y validaciones de negocio
- Permisos por acción: programar/crear-orden = **crear**; reservar/actualizar = **modificar**;
  liberar = **eliminar** (módulo `Programa Urd / Eng`).
- Crear órdenes exige **destino** (salonTejidoId) y **fibra/hilo** no vacíos (422 si faltan).
- Telares de un grupo deben tener **mismo tipo, calibre y (para rizo) hilo**.
- "No. Julios" máximo **15** por fila de construcción (validado en cliente y en input).
- Reservas idempotentes: violación de índice único (SQL 2601/2627) se trata como "ya reservada".
- Un telar no se puede reservar/programar si ya tiene checkbox deshabilitado (reservado/programado/orden).
- Actualización de programas **solo por Folio** (nunca por NoTelarId, que puede repetirse), tomando el
  Folio del campo `no_orden` del telar en BD.
- Consultas a TI-PRO en **READ UNCOMMITTED + NOLOCK** para no bloquear el ERP de producción.

### Efectos colaterales entre módulos
- **Tejido:** `tej_inventario_telares` es la tabla central compartida; reservar/programar/liberar la
  modifican (banderas Reservado/Programado, no_julio, no_orden, dimensiones).
- **Tejedores:** al reservar, si hay una notificación de paro pendiente del tejedor para ese telar/tipo,
  se le transfiere la `hora` al telar (`horaParo`), se le adjuntan no_julio/no_orden y se marca
  `Reserva=1`. Al liberar, la notificación se resetea.
- **Planeación:** `ReqTelares` provee el grupo/destino; `ReqProgramaTejido` (+líneas) alimenta el resumen
  de semanas; los programas de urdido/engomado generados son consumidos aguas abajo por Urdido y Engomado.
- **Auditoría:** toda creación/actualización de `UrdProgramaUrdido`/`EngProgramaEngomado` queda registrada
  en `AuditoriaUrdEng` (acciones create/update con detalle de campos antes/después).

### Integraciones
- **Folios:** `SSYSFoliosSecuencia::nextFolio('URD/ENG',5)` (fallback `nextFolioById(14,5)`) y
  `nextFolio('CambioHilo',5)`. Se generan dentro de la transacción al crear órdenes (incrementan).
- **ERP TI-PRO (`sqlsrv_ti`):** inventario físico de julios/hilos, catálogos BOM y fórmulas (AX).
- **Telegram / Excel / PDF:** **no** hay integración directa en este ámbito (no se invocan
  TelegramController, clases de `app/Imports`/`app/Exports` ni `PDFController` desde estos controllers).
- **SweetAlert2:** usado en todas las vistas para confirmaciones, toasts y captura de fecha de
  requerimiento; persistencia de selecciones en `localStorage` (creacion-ordenes).
