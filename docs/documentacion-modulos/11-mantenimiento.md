# Mantenimiento

> Generado automáticamente — documentación detallada del módulo

## 1. Propósito del módulo

El módulo **Mantenimiento** gestiona el reporte, seguimiento y cierre de **fallas y paros** de las máquinas/telares del piso productivo textil (Tejido, Urdido, Engomado, Atadores, Calidad, etc.), además de los catálogos que lo alimentan.

Resuelve el flujo completo de incidencias de mantenimiento:

- **Reportar un paro** (alta de incidencia): un operador/usuario reporta una falla en su área/máquina, con tipo de falla, falla concreta, descripción y orden de trabajo sugerida desde los programas en proceso.
- **Listar solicitudes/paros** (reporte de fallos y paros) con filtros por área, status, máquina y "solo mis solicitudes".
- **Finalizar un paro**: registra quién atendió, turno de atención, calificación de calidad (1–5) y observaciones de cierre.
- **Reportes con rango de fechas** y **exportación a Excel** del histórico de fallas/paros.
- **Catálogos de soporte**: catálogo de fallas (`CatParosFallas`) y catálogo de operadores de mantenimiento (`ManOperadoresMantenimiento`).

Submódulos que abarca (según rutas y vistas):

| Submódulo | Pantalla |
|---|---|
| Reportar Paro | `nuevo-paro` |
| Finalizar Paro | `finalizar-paro` |
| Solicitudes / Reporte de Fallos y Paros | `reporte-fallos-paros` |
| Reportes Mantenimiento (selector) | `reportes-mantenimiento-index` |
| Reporte Fallas y Paros (rango fechas + Excel) | `reportes-mantenimiento-fallas-paros` |
| Catálogo de Fallas (CRUD) | `catalogos-fallas` |
| Operadores de Mantenimiento (CRUD) | `operadores-mantenimiento` |

**Rol dentro del flujo productivo:** es transversal. Toma como fuentes de máquinas y órdenes de trabajo los catálogos y programas de otras áreas (`URDCatalogoMaquina`, `AtaMaquinasModel`, `TelTelaresOperador`, `ReqProgramaTejido`, `UrdProgramaUrdido`, `EngProgramaEngomado`) y notifica vía Telegram a los responsables (eléctrico, mecánico, tiempo muerto, calidad) cuando se reporta o se cierra un paro. Permite cuantificar tiempos muertos y la calidad de la atención de mantenimiento.

---

## 2. Rutas

Archivo: `routes/modules/mantenimiento.php`. Todas las rutas cargan bajo el grupo autenticado del dispatcher (`routes/web.php`), por lo que aplican el middleware `auth` (más los middleware globales del grupo: `SetSqlContextInfo`, `NoCacheHtmlResponses`, etc.). **No** hay verificación de permisos `userCan()` en los controladores del módulo; el control de acceso se hace a nivel de menú/módulo (los componentes de navbar reciben `module="Solicitudes"`). El campo de permiso en `SYSRoles` mantiene el typo intencional `reigstrar`.

| Método | URI | Controller@método | Middleware | Permiso requerido |
|---|---|---|---|---|
| GET | `/mantenimiento/reportes` | `ReportesMantenimientoController@index` | `auth` | — (módulo Mantenimiento) |
| GET | `/mantenimiento/reportes/fallas-paros` | `ReportesMantenimientoController@reporteFallasParos` | `auth` | — |
| GET | `/mantenimiento/reportes/fallas-paros/excel` | `ReportesMantenimientoController@exportarExcel` | `auth` | — |
| GET | `/mantenimiento/{moduloPrincipal?}` (default `mantenimiento`) | `UsuarioController@showSubModulos` | `auth` | — (listado de submódulos) |
| GET (view) | `/mantenimiento/solicitudes` | vista `modulos.mantenimiento.reporte-fallos-paros.index` | `auth` | — |
| GET | `/mantenimiento/nuevo-paro` | `MantenimientoParosController@nuevoParo` | `auth` | — |
| GET (view) | `/mantenimiento/finalizar-paro` | vista `modulos.mantenimiento.finalizar-paro.index` | `auth` | — |
| GET (view) | `/mantenimiento/reporte-fallos-paros` | vista `modulos.mantenimiento.reporte-fallos-paros.index` | `auth` | — |
| GET | `/mantenimiento/catalogodefallas` | `CatalogosFallasController@index` | `auth` | — |
| POST | `/mantenimiento/catalogodefallas` | `CatalogosFallasController@store` | `auth` | — |
| PUT | `/mantenimiento/catalogodefallas/{catalogosFalla}` | `CatalogosFallasController@update` | `auth` | — |
| DELETE | `/mantenimiento/catalogodefallas/{catalogosFalla}` | `CatalogosFallasController@destroy` | `auth` | — |
| GET | `/mantenimiento/operadores-mantenimiento` | `ManOperadoresMantenimientoController@index` | `auth` | — |
| POST | `/mantenimiento/operadores-mantenimiento` | `ManOperadoresMantenimientoController@store` | `auth` | — |
| PUT | `/mantenimiento/operadores-mantenimiento/{operador}` | `ManOperadoresMantenimientoController@update` | `auth` | — |
| DELETE | `/mantenimiento/operadores-mantenimiento/{operador}` | `ManOperadoresMantenimientoController@destroy` | `auth` | — |
| GET | `/api/mantenimiento/departamentos` | `MantenimientoParosController@departamentos` | `auth` | — |
| GET | `/api/mantenimiento/departamentos/catalogo-filtros` | `MantenimientoParosController@departamentosCatalogoFiltros` | `auth` | — |
| GET | `/api/mantenimiento/maquinas/{departamento}` | `MantenimientoParosController@maquinas` | `auth` | — |
| GET | `/api/mantenimiento/tipos-falla` | `MantenimientoParosController@tiposFalla` | `auth` | — |
| GET | `/api/mantenimiento/fallas/{departamento}/{tipoFallaId?}` | `MantenimientoParosController@fallas` | `auth` | — |
| GET | `/api/mantenimiento/orden-trabajo/{departamento}/{maquina}` | `MantenimientoParosController@ordenTrabajo` | `auth` | — |
| GET | `/api/mantenimiento/operadores` | `MantenimientoParosController@operadores` | `auth` | — |
| POST | `/api/mantenimiento/paros` | `MantenimientoParosController@store` | `auth` | — |
| GET | `/api/mantenimiento/paros` | `MantenimientoParosController@index` | `auth` | — |
| GET | `/api/mantenimiento/paros/validar-duplicado` | `MantenimientoParosController@validarDuplicadoParo` | `auth` | — |
| GET | `/api/mantenimiento/paros/{id}` | `MantenimientoParosController@show` | `auth` | — |
| PUT | `/api/mantenimiento/paros/{id}/finalizar` | `MantenimientoParosController@finalizar` | `auth` | — |

> Nota de orden de rutas: `/api/mantenimiento/paros/validar-duplicado` se declara **antes** de `/api/mantenimiento/paros/{id}` para que `validar-duplicado` no sea capturado como `{id}`.

---

## 3. Controllers

### 3.1 `MantenimientoParosController`
`app/Http/Controllers/Mantenimiento/MantenimientoParosController.php`

Controlador central del módulo. Mezcla vista (HTML) y API (JSON). Usa `FolioHelper`, modelos de varias áreas y notificaciones Telegram.

#### Funciones públicas

**`nuevoParo()`** — (`:30`)
- **Qué hace:** muestra la vista del formulario de nuevo paro pre-seleccionando el área del usuario.
- **Respuesta:** vista `modulos.mantenimiento.nuevo-paro.index` con `$areaUsuario`.
- **Tablas:** lee `SYSUsuario` (conexión por defecto del modelo) por `idusuario` para obtener `area`.
- **Permisos:** ninguno explícito.

**`departamentos(): JsonResponse`** — (`:51`)
- **Qué hace:** devuelve la lista de departamentos para el combo de "nuevo paro".
- **Respuesta:** `{ success, data: string[] }`.
- **Regla de negocio:** si el `id` del usuario autenticado es **6**, solo devuelve `['Urdido', 'Engomado']`; en otro caso, todos.
- **Tablas:** `SysDepartamentos` (`SysDepartamento`, conexión `sqlsrv`), columna `Depto`.

**`departamentosCatalogoFiltros(): JsonResponse`** — (`:77`)
- **Qué hace:** devuelve **todos** los departamentos del catálogo, sin restricción por usuario (usado en el combo Área del reporte de paros).
- **Respuesta:** `{ success, data: string[] }`; en error, `{ success:false, error, data:[] }` con HTTP 500.
- **Tablas:** `SysDepartamentos.Depto` (trim, sin vacíos).

**`maquinas(string $departamento): JsonResponse`** — (`:115`)
- **Qué hace:** lista las máquinas/telares disponibles para el departamento.
- **Lógica por departamento** (mayúsculas, trim):
  - `URDIDO`/`ENGOMADO` → `URDCatalogoMaquina` filtrando `Departamento`.
  - `ATADORES` → `AtaMaquinasModel` (todas, `MaquinaId`).
  - `CALIDAD` → todos los telares distintos de `TelTelaresOperador` (`NoTelarId`), sin filtrar por usuario.
  - `TEJEDORES`/`TRAMA`/`DESARROLLADORES`/`SUPERVISORES` → telares del usuario (`numero_empleado`) sin filtrar por salón.
  - `ITEMA`/`JACQUARD`/`SMITH`/`KARLMAYER`/`KARL MAYER` → telares del usuario filtrados por `SalonTejidoId` (mapeo: ITEMA→Smith, JACQUARD→Jacquard, SMITH→Smith, KARLMAYER→`KARL MAYER`/`KarlMayer`).
- **Respuesta:** `{ success, data:[{MaquinaId, Nombre, Departamento}] }`. Si no hay `numero_empleado` → HTTP 401. Error → HTTP 500.
- **Tablas:** `URDCatalogoMaquina`, `AtaMaquinasModel`, `TelTelaresOperador` (todas en sus respectivas conexiones de modelo; típicamente `sqlsrv`).

**`tiposFalla(): JsonResponse`** — (`:248`)
- **Qué hace:** lista los tipos de falla del catálogo.
- **Respuesta:** `{ success, data: TipoFallaId[] }`.
- **Tablas:** `CatTipoFalla` (`sqlsrv`), `pluck('TipoFallaId')`.

**`fallas(string $departamento, ?string $tipoFallaId = null): JsonResponse`** — (`:269`)
- **Qué hace:** devuelve las fallas de `CatParosFallas` para el departamento (y opcionalmente filtrando por tipo).
- **Reglas:** los departamentos de tejido (`JACQUARD`, `ITEMA`, `KARL MAYER`/`KARLMAYER`, `SMITH`, `TEJEDORES`, `TRMA`/`TRAMA`, `DESARROLLADORES`, `SUPERVISORES`) consultan el catálogo `Tejido`. `CALIDAD` consulta `['Calidad', 'Tejido']` ordenando primero Calidad. Se eliminan duplicados por `Falla|Descripcion` (mayúsculas/trim).
- **Respuesta:** `{ success, data:[{Falla, Descripcion, Abreviado, Seccion, TipoFallaId, Departamento}] }`. Error → HTTP 500.
- **Tablas:** `CatParosFallas` (`sqlsrv`).

**`ordenTrabajo(string $departamento, string $maquina): JsonResponse`** — (`:322`)
- **Qué hace:** sugiere órdenes de trabajo (máx. 5) según el programa en proceso de cada área.
- **Reglas por departamento:**
  - `URDIDO` → `UrdProgramaUrdido` (`MaquinaId`, `Status = 'En Proceso'`), devuelve `Folio as Orden_Prod`, `FechaProg as Fecha`, `MaquinaId`.
  - `ENGOMADO` → `EngProgramaEngomado` (`MaquinaEng`, `Status = 'En Proceso'`), `Folio as Orden_Prod`, `FechaProg`, `MaquinaEng`, `SalonTejidoId`.
  - Resto (tejido/calidad) → `ReqProgramaTejido` (`NoTelarId`, `EnProceso = 1`); para no-Calidad además filtra `SalonTejidoId` (mapeo: ITEMA/SMITH→`SMIT`, JACQUARD→`JACQUARD`, KARLMAYER→`KARL MAYER`/`KARLMAYER`). Devuelve `NoProduccion as Orden_Prod`, `NombreProducto`, `FechaInicio`, `SalonTejidoId`, `NoTelarId`.
- **Conexión:** usa `DB::table(...)` (conexión por defecto, `sqlsrv`).
- **Respuesta:** `{ success, data }`. Error → HTTP 500.

**`validarDuplicadoParo(Request $request): JsonResponse`** — (`:400`)
- **Qué hace:** valida si ya existe un paro **Activo** para la misma máquina y tipo de falla.
- **Request validado:** `maquina` (required|string|max:50), `tipo_falla` (required|string|max:20).
- **Respuesta:** `{ success, duplicado: bool, message }`. Validación → HTTP 422. Error → HTTP 500.
- **Tablas:** `ManFallasParos` (`sqlsrv`) vía `existeParoActivoDuplicado()`.

**`store(Request $request)`** — (`:452`)
- **Qué hace:** crea un nuevo paro/falla.
- **Auth:** si no hay usuario → HTTP 401.
- **Request validado:** `fecha` (required|date), `hora` (required), `depto` (required|string|max:30), `maquina` (required|string|max:50), `tipo_falla` (required|string|max:20), `falla` (required|string|max:20), `descrip` (nullable|string|max:100), `orden_trabajo` (nullable|string|max:50), `obs` (nullable|string).
- **Reglas:** rechaza duplicado activo (HTTP 422). Genera **folio** con `FolioHelper::obtenerSiguienteFolio('ParosFallas', 5)` (auto-incrementa). Si el folio es vacío → HTTP 500.
- **Datos guardados:** `Folio`, `Estatus='Activo'`, fecha/hora, `Depto`, `MaquinaId`, `TipoFallaId`, `Falla`, `Descripcion`, `OrdenTrabajo`, `Obs`, `CveEmpl`=numero_empleado, `NomEmpl`=nombre, `Turno`=(int)usuario->turno (default 1), `Enviado`=checkbox notificar, campos de cierre en null.
- **Efectos:** si `notificar_supervisor` es true → `enviarNotificacionTelegram()`.
- **Respuesta:** `{ success, message, data:{folio, id, notificacion_enviada} }`. Validación → 422; error → 500.
- **Tablas:** `ManFallasParos` (`sqlsrv`, create), `dbo.SSYSFoliosSecuencias` (vía `FolioHelper`).

**`index(Request $request): JsonResponse`** — (`:864`)
- **Qué hace:** lista paros para el reporte de solicitudes, ordenados por Fecha/Hora desc.
- **Query params:** `alcance` (`todos`), `depto` (nombre exacto, debe existir en `SysDepartamentos`), `incluir_finalizados` (bool; por defecto solo `Estatus='Activo'`).
- **Reglas de filtrado:**
  - `alcance=todos` → todos los departamentos.
  - `depto={nombre}` válido → ese departamento; inválido → `1=0` (vacío).
  - sin parámetros → solo el `area` del usuario; si no tiene área → vacío.
  - Usuarios de área **Tejedores**: se acota a sus telares (`TelTelaresOperador`) según `aplicarRestriccionTelaresOperadorSiCorresponde()`.
- **Respuesta:** `{ success, data:[Id, Folio, Estatus, Fecha, Hora, Depto, MaquinaId, TipoFallaId, Falla, HoraFin, NomAtendio, NomEmpl, CveEmpl] }`. Error → 500.
- **Tablas:** `ManFallasParos`, `SysDepartamentos`, `TelTelaresOperador`, `SYSUsuario` (`sqlsrv`).

**`show(int $id): JsonResponse`** — (`:937`)
- **Qué hace:** devuelve un paro por `Id`. 404 si no existe.
- **Respuesta:** `{ success, data: ManFallasParos }`. Error → 500.
- **Tablas:** `ManFallasParos`.

**`finalizar(Request $request, int $id): JsonResponse`** — (`:969`)
- **Qué hace:** cierra un paro (cambia a `Terminado` con datos de cierre).
- **404** si el paro no existe.
- **Request validado:** `atendio` (nullable|string|max:100), `turno` (nullable|integer|in:1,2,3), `calidad` (nullable|integer|min:1|max:10), `obs_cierre` (nullable|string|max:255), `enviar_telegram` (nullable|boolean).
- **Datos actualizados:** `Estatus='Terminado'`, `HoraFin=now('H:i:s')`, `FechaFin=now('Y-m-d')`; condicionalmente `NomAtendio`+`CveAtendio` (numero_empleado del que cierra), `TurnoAtendio`, `Calidad`, `ObsCierre`.
- **Efectos:** si `enviar_telegram` → `enviarNotificacionTelegramCierre()`.
- **Respuesta:** `{ success, message, data:{id, folio, notificacion_enviada} }`. Validación → 422; error → 500.
- **Tablas:** `ManFallasParos` (update).

**`operadores(): JsonResponse`** — (`:1054`)
- **Qué hace:** lista operadores de mantenimiento para el select "Atendió".
- **Respuesta:** `{ success, data:[Id, CveEmpl, NomEmpl, Turno, Depto] }`. Error → 500.
- **Tablas:** `dbo.ManOperadoresMantenimiento` (`sqlsrv`).

#### Funciones privadas relevantes
- **`existeParoActivoDuplicado(string $maquinaId, string $tipoFallaId): bool`** (`:437`) — `ManFallasParos` con `Estatus='Activo'` y comparación normalizada (LTRIM/RTRIM/CAST) de `MaquinaId` y `TipoFallaId`.
- **`moduloTelegramPorTipoFalla(?string $tipoFallaId): ?string`** (`:555`) — mapea el tipo de falla a la columna de `SYSMensajes`: `ReporteElectrico`, `ReporteMecanico`, `ReporteTiempoMuerto`, `Calidad`, o `null`.
- **`enviarNotificacionTelegram($paro, $usuario): void`** (`:588`) — envía mensaje Markdown de alta de paro a los `chat_id` de `SYSMensaje::getChatIdsPorModulo($modulo)` vía `Http::post` a la API de Telegram (token desde `config('services.telegram.bot_token')`).
- **`enviarNotificacionTelegramCierre($paro, $usuario): void`** (`:669`) — equivalente para el cierre; incluye atendió, turno, calidad y observaciones de cierre.
- **`areaUsuarioAutenticado(): string`** (`:750`) — área del usuario en sesión (de `Auth::user()->area` o de `SYSUsuario`).
- **`usuarioEsAreaTejedores(): bool`** (`:774`) — compara el área con `Tejedores`.
- **`idsTelaresAsignadosOperadorActual(): array`** (`:784`) — `NoTelarId` distintos del usuario en `TelTelaresOperador`.
- **`aplicarRestriccionTelaresOperadorSiCorresponde($query, $alcance, $deptoReq): void`** (`:808`) — para usuarios Tejedores acota `MaquinaId` a sus telares (lógica distinta según `alcance=todos` o filtro por depto).

### 3.2 `CatalogosFallasController`
`app/Http/Controllers/Mantenimiento/CatalogosFallasController.php`

CRUD del catálogo de fallas (`CatParosFallas`). Responde HTML o JSON según `expectsJson()/ajax()`.

**`index(Request $request)`** — (`:17`)
- **Qué hace:** vista del catálogo con filtros y listado.
- **Query params:** `q` (búsqueda en `Falla`/`Descripcion`/`Abreviado`), `tipo_falla` (igualdad `TipoFallaId`), `departamento` (igualdad `Departamento`).
- **Respuesta:** vista `modulos.mantenimiento.catalogos-fallas.index` con `items`, filtros, `tiposFalla`, `departamentos`.
- **Tablas:** `CatParosFallas` (con relación `tipoFalla`), `CatTipoFalla` (`sqlsrv`).

**`store(Request $request)`** — (`:64`)
- **Request validado:** `TipoFallaId` (required|max:50), `Departamento` (required|max:50), `Falla` (required|max:100), `Descripcion` (nullable|max:255), `Abreviado` (nullable|max:50), `Seccion` (nullable|max:50).
- **Respuesta:** JSON `{success, message}` o redirect `back()->with('success')`. Validación → 422 (JSON) o `withErrors`. Error → 500/error flash.
- **Tablas:** `CatParosFallas::create`.

**`update(Request $request, CatParosFallas $catalogosFalla)`** — (`:105`)
- **Request validado:** igual que `store`.
- **Respuesta:** JSON/redirect de éxito; 422/500 en errores.
- **Tablas:** `CatParosFallas::update` (binding por ruta `{catalogosFalla}`).

**`destroy(Request $request, CatParosFallas $catalogosFalla)`** — (`:147`)
- **Qué hace:** elimina la falla.
- **Respuesta:** JSON `{success, message}` o redirect; error → 500/flash.
- **Tablas:** `CatParosFallas::delete`.

### 3.3 `ManOperadoresMantenimientoController`
`app/Http/Controllers/Mantenimiento/ManOperadoresMantenimientoController.php`

CRUD de operadores de mantenimiento (`ManOperadoresMantenimiento`). Mismo patrón JSON/redirect.

**`index(Request $request)`** — (`:15`)
- **Query params:** `q` (busca en `CveEmpl`/`NomEmpl`/`Depto`/`Telefono`), `turno` (igualdad), `depto` (like).
- **Respuesta:** vista `modulos.mantenimiento.operadores-mantenimiento.index` con `items`, filtros, `turnos`, `departamentos` (distintos).
- **Tablas:** `dbo.ManOperadoresMantenimiento` (`sqlsrv`).

**`store(Request $request)`** — (`:66`)
- **Request validado:** `CveEmpl` (required|max:50), `NomEmpl` (required|max:255), `Turno` (required|integer|1–3), `Depto` (required|max:100), `Telefono` (nullable|max:50).
- **Respuesta:** JSON/redirect de éxito; 422/500 en errores.
- **Tablas:** `ManOperadoresMantenimiento::create`.

**`update(Request $request, ManOperadoresMantenimiento $operador)`** — (`:106`)
- **Request validado:** igual que `store`.
- **Tablas:** `ManOperadoresMantenimiento::update`.

**`destroy(Request $request, ManOperadoresMantenimiento $operador)`** — (`:147`)
- **Tablas:** `ManOperadoresMantenimiento::delete`.

### 3.4 `ReportesMantenimientoController`
`app/Http/Controllers/Mantenimiento/ReportesMantenimientoController.php`

**`index()`** — (`:17`)
- **Qué hace:** selector de reportes (estilo Urdido). Devuelve un único reporte disponible "Fallas y Paros".
- **Respuesta:** vista `modulos.mantenimiento.reportes-mantenimiento-index` con `$reportes`.

**`reporteFallasParos(Request $request)`** — (`:37`)
- **Query params:** `fecha_ini`, `fecha_fin`. Si ambos presentes, filtra `whereBetween('Fecha', ...)`.
- **Respuesta:** vista `modulos.mantenimiento.reportes-mantenimiento-fallas-paros` con `registros`, `fechaIni`, `fechaFin`.
- **Tablas:** `ManFallasParos` (`sqlsrv`), orden por Fecha/Hora.

**`exportarExcel(Request $request)`** — (`:63`)
- **Query params:** `fecha_ini`, `fecha_fin` (mismo filtro que el reporte).
- **Respuesta:** descarga `Excel::download(new ReporteMantenimientoExport($registros), 'Reporte_Mantenimiento_{timestamp}.xlsx')`.
- **Tablas:** `ManFallasParos`.

---

## 4. Services y Helpers del ámbito

El módulo no define services propios. Reutiliza helpers globales:

- **`FolioHelper::obtenerSiguienteFolio('ParosFallas', 5)`** — usado en `MantenimientoParosController@store` para generar el folio secuencial del paro (longitud 5). Auto-incrementa la secuencia en `dbo.SSYSFoliosSecuencias`.
- **`SYSMensaje::getChatIdsPorModulo($modulo)`** — obtiene los `chat_id` de Telegram activos para el módulo derivado del tipo de falla.
- **Notificaciones Telegram** — `config('services.telegram.bot_token')` + `Http::post` a `https://api.telegram.org/bot{token}/sendMessage` con `parse_mode=Markdown`.

### `ReporteMantenimientoExport`
`app/Exports/ReporteMantenimientoExport.php` — clase de exportación (`FromArray`, `WithEvents`, `WithTitle`) que construye el Excel a partir de una **plantilla** `ReporteMantenimiento.xlsx`.

- **`__construct(Collection $registros)`** — recibe los paros.
- **`array()`** / **`title()`** — base (hoja "Reporte Mantenimiento"); el contenido real se inyecta en eventos.
- **`registerEvents()`** — en `AfterSheet` carga la plantilla, reemplaza la hoja, escribe encabezados, llena datos, sincroniza el formato de tabla y aplica estilos.
- **`loadTemplateBook()`** — busca la plantilla en `resources/templates/`, `storage/app/templates/` o `storage/app/`; si no existe lanza `RuntimeException`.
- **`setHeaders()`** — 19 columnas (Folio, Estatus, Fecha, Fecha Fin, Hora Inicio, HoraFin, **Diferencia**, Departamento, Maquina, TipoFalla, Falla, ClaveEmpleado, NombreEmpleado, Turno, Obs, CveAtendio, NomAtendio, ObsCierre, OrdenTrabajo).
- **`fillData()`** — vuelca cada registro; resalta la columna "Diferencia" si está finalizado; aplica badges de status y tipo de falla.
- **`buildDifferenceText()`** — calcula la duración del paro (`Xd Yh Zm`) entre Fecha+Hora inicio y FechaFin+HoraFin; negativo si el fin es anterior.
- **`resolveFallaDescripcion()`** — usa `Descripcion`, o `Falla` si está vacía.
- **`applyStatusBadge()` / `applyTipoFallaBadge()`** — colores: finalizado=verde, activo=azul; tipo eléctrico=azul, mecánico=rojo, tiempo muerto=gris.
- **`formatDateValue()` / `formatTimeValue()` / `buildDateTime()` / `normalizeText()` / `isFinalizado()` / `isActivo()`** — utilidades de formato.

---

## 5. Modelos y tablas

`app/Models/Mantenimiento/`

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `ManFallasParos` | `dbo.ManFallasParos` | `sqlsrv` | `Id` (int, auto) | `Folio`, `Estatus`, `Fecha`, `Hora`, `Depto`, `MaquinaId`, `TipoFallaId`, `Falla`, `Descripcion`, `HoraFin`, `FechaFin`, `CveEmpl`, `NomEmpl`, `Turno`, `CveAtendio`, `NomAtendio`, `TurnoAtendio`, `Obs`, `ObsCierre`, `OrdenTrabajo`, `Enviado`, `Calidad` |
| `CatParosFallas` | `CatParosFallas` | `sqlsrv` | `Id` (int, auto) | `TipoFallaId`, `Departamento`, `Falla`, `Descripcion`, `Abreviado`, `Seccion` |
| `CatTipoFalla` | `CatTipoFalla` | `sqlsrv` | `TipoFallaId` (string, no auto) | `TipoFallaId` |
| `ManOperadoresMantenimiento` | `dbo.ManOperadoresMantenimiento` | `sqlsrv` | `Id` (int, auto) | `CveEmpl`, `NomEmpl`, `Turno`, `Depto`, `Telefono` |

**Relaciones:**
- `ManFallasParos::tipoFalla()` → `belongsTo(CatTipoFalla, TipoFallaId)`.
- `CatParosFallas::tipoFalla()` → `belongsTo(CatTipoFalla, TipoFallaId)`.
- `CatTipoFalla::parosFallas()` → `hasMany(CatParosFallas)`; `CatTipoFalla::fallasParos()` → `hasMany(ManFallasParos)`.

**Casts notables (`ManFallasParos`):** `Fecha`/`FechaFin` → date, `Turno`/`TurnoAtendio`/`Calidad` → integer, `Enviado` → boolean. `timestamps = false` en todos.

**Tablas externas tocadas por el módulo (no tienen modelo en este ámbito, se consultan vía `DB::table` o modelos de otras áreas):** `SysDepartamentos`, `URDCatalogoMaquina`, `AtaMaquinas` (`AtaMaquinasModel`), `TelTelaresOperador`, `ReqProgramaTejido`, `UrdProgramaUrdido`, `EngProgramaEngomado`, `SYSUsuario`, `SYSMensajes` (`SYSMensaje`), `dbo.SSYSFoliosSecuencias`.

---

## 6. Vistas Blade

`resources/views/modulos/mantenimiento/`

### 6.1 `nuevo-paro/index.blade.php`
**Propósito:** formulario para reportar un paro. **Secciones UI:** formulario con Fecha/Hora (deshabilitadas, valor actual), selects encadenados Departamento → Máquina → Tipo Falla → Falla/Descripción, Orden de Trabajo, Observaciones, checkbox "Notificar a Supervisor" (forzado a marcado), botones Ir a Solicitudes / Cancelar / Reportar.

Funciones JS inline (dentro de `DOMContentLoaded`):
- **`cargarTiposFalla()`** (`:203`) — carga tipos de falla en el select. Endpoint: `GET api.mantenimiento.tipos-falla`.
- **`cargarDepartamentos()`** (`:228`) — carga departamentos y auto-selecciona el área del usuario, disparando carga de máquinas. Endpoint: `GET api.mantenimiento.departamentos`.
- **`cargarFallas(departamento, tipoFallaId=null)`** (`:276`) — puebla los selects Falla y Descripción (sincronizados por `data-*`). Endpoint: `GET /api/mantenimiento/fallas/{dep}/{tipo?}`.
- **`cargarMaquinas(departamento)`** (`:429`) — puebla el select de máquinas. Endpoint: `GET /api/mantenimiento/maquinas/{dep}`.
- **`cargarOrdenTrabajo(departamento, maquina)`** (`:470`) — sugiere la primera orden de trabajo en proceso. Endpoint: `GET /api/mantenimiento/orden-trabajo/{dep}/{maquina}`.
- **`restaurarBotonReportar()`** (`:588`, anidada en submit) — re-habilita el botón Reportar tras error.
- **Submit del formulario** (`:577`) — valida duplicado (`GET api.mantenimiento.paros.validar-duplicado`) y luego envía (`POST api.mantenimiento.paros.store`); muestra SweetAlert con el folio y redirige.

### 6.2 `finalizar-paro/index.blade.php`
**Propósito:** formulario de cierre de un paro existente. **Secciones UI:** campos de solo lectura del paro (Fecha/Hora cierre, Depto, Máquina, Tipo Falla, Falla, Descripción, Orden de Trabajo), select "Atendió" (operadores), sistema de **estrellas 1–5** para Calidad (input hidden), Turno oculto (autollenado por operador), Observaciones de cierre, checkbox "Enviar a Telegram" (forzado), botones Cancelar / Finalizar. El `id` del paro llega por query `?id=` o por `localStorage('selectedParoId')`.

Funciones JS inline:
- **`cargarOperadores()`** (`:247`) — llena el select "Atendió" y guarda el `Turno` en `data-turno`; al cambiar operador rellena el Turno oculto. Endpoint: `GET api.mantenimiento.operadores`.
- **`inicializarEstrellas()`** (`:291`) — crea 5 estrellas clickeables con hover y fija `calidad`.
  - **`actualizarEstrellas(valor)`** (`:296`, anidada) — pinta de amarillo las estrellas hasta `valor`.
- **`cargarDatosParo(paroId)`** (`:338`) — carga los datos del paro y prellena los campos (Fecha/Hora cierre = ahora; resto desde el paro, incluido calidad/atendió previos). Endpoint: `GET /api/mantenimiento/paros/{id}`.
- **Submit del formulario** (`:422`) — envía cierre con `PUT /api/mantenimiento/paros/{id}/finalizar` (JSON: atendio, turno, calidad, obs_cierre, enviar_telegram); en éxito redirige a `produccion.index`.

### 6.3 `reporte-fallos-paros/index.blade.php`
**Propósito:** tabla/listado de solicitudes (paros) con modal de filtros. Es la vista de `/mantenimiento/solicitudes`. **Secciones UI:** navbar con botones Filtrar / Nuevo Paro / Terminar Paro; tabla (Folio, Status, Fecha, Hora, Área, Máquina, Tipo Falla, Falla, Usuario); modal de filtros con Área, Status, Máquina, checkboxes "Incluir terminados" y "Solo mis solicitudes". La selección de fila guarda `window.paroSeleccionadoId` y, en "Terminar Paro", se persiste en `localStorage` y se navega a finalizar.

Funciones JS inline:
- **`obtenerDepartamentosCatalogo()`** (`:152`) — obtiene el catálogo de áreas para el combo. Endpoint: `GET api.mantenimiento.departamentos.catalogo-filtros`.
- **`poblarSelectDepartamentos(deptos, valorForzado)`** (`:166`) — puebla el combo Área, fijando el valor por defecto del área del usuario o el forzado.
- **`applyFilters()`** (`:180`) — filtra las filas ya cargadas en cliente por área, status, máquina y "solo mis solicitudes".
- **`aplicarValorStatusTrasCarga(filterStatusEl, prevStatus, opts)`** (`:216`) — mantiene coherente el combo Status tras recargar (solo Activos salvo que se incluyan terminados).
- **`cargarParos(apiModo, valorDeptoCombo, opts)`** (`:244`) — recarga la tabla desde la API según alcance (default/todos/depto) e incluir terminados, repuebla combos y enlaza el click de fila. Endpoint: `GET api.mantenimiento.paros.index`.
- **`openFiltersModal()` / `closeFiltersModal()`** (`:396` / `:401`) — abren/cierran el modal de filtros.
- Listeners: cambio de Área dispara `cargarParos('todos'|'depto')`; "Incluir terminados" recarga; Status/Máquina aplican filtro cliente; "Solo mis" filtra; "Limpiar" resetea; "Nuevo Paro" navega a `mantenimiento.nuevo-paro`; "Terminar Paro" navega a `mantenimiento.finalizar-paro` con el paro seleccionado.

### 6.4 `reportes-mantenimiento-index.blade.php`
**Propósito:** selector de reportes (lista de tarjetas) con modal de rango de fechas. **Secciones UI:** lista de reportes (`$reportes`), modal con Fecha inicial/final.

Funciones JS inline (en `@push('scripts')`):
- **`hoy()`** (`:75`) — devuelve la fecha de hoy `YYYY-MM-DD`.
- **`abrirModal(url)`** (`:79`) — abre el modal guardando la URL destino del reporte.
- **`cerrarModal()`** (`:88`) — cierra el modal.
- **`confirmar()`** (`:93`) — valida el rango y navega a `urlDestino?fecha_ini&fecha_fin` (destino: `mantenimiento.reportes.fallas-paros`).

### 6.5 `reportes-mantenimiento-fallas-paros.blade.php`
**Propósito:** tabla del reporte de fallas/paros por rango de fechas, con botón de descarga a Excel. **Secciones UI:** navbar con Consultar y (si hay rango) Descargar Excel (`mantenimiento.reportes.fallas-paros.excel`); cabecera con el rango; tabla de 18 columnas renderizada en servidor desde `$registros`; modal de rango de fechas (se abre automáticamente si no hay fechas).

Funciones JS inline (en `@push('scripts')`):
- **`mostrarModalFechas()`** (`:122`, global) — abre el modal y precarga hoy si los inputs están vacíos.
- **`cerrarModal()`** (`:140`, anidada en `DOMContentLoaded`) — cierra el modal.
- **`confirmar()`** (`:145`) — valida el rango y navega a `mantenimiento.reportes.fallas-paros?fecha_ini&fecha_fin`.

### 6.6 `catalogos-fallas/index.blade.php`
**Propósito:** CRUD del catálogo de fallas con tabla seleccionable y modales SweetAlert. **Secciones UI:** navbar con Filtrar / Crear / Editar / Eliminar (componentes `x-navbar.button-*`); tabla (Tipo Falla, Departamento, Falla, Descripción, Abreviado, Sección) con selección por fila; form oculto de delete; bloques `Swal.fire` para flashes de sesión/errores. Opciones de departamentos/tipos de falla inyectadas vía `@json`.

Funciones JS inline:
- **`updateTopButtonsState()`** (`:198`) — habilita/deshabilita los botones Editar/Eliminar según haya selección.
- **`clearSelection()`** (`:210`) — limpia la fila seleccionada.
- **`selectRow(row)`** (`:220`) — selecciona/deselecciona una fila (toggle) y actualiza botones.
- **`openCreateModal()`** (`:237`) — abre modal SweetAlert para crear; `preConfirm` valida y llama a `submitCreateForm`.
- **`submitCreateForm(data)`** (`:350`) — `POST mantenimiento.catalogos-fallas.store`; en éxito recarga.
- **`handleTopEdit()`** (`:404`) — toma los `data-*` de la fila y abre `openEditModal`.
- **`openEditModal(key, tipoFalla, departamento, falla, descripcion, abreviado, seccion)`** (`:416`) — modal de edición pre-cargado; `preConfirm` valida y llama a `submitUpdateForm`.
- **`submitUpdateForm(key, data)`** (`:530`) — `POST` con `_method=PUT` a `mantenimiento.catalogos-fallas.update`; en éxito recarga.
- **`handleTopDelete()`** (`:586`) — confirma y llama a `deleteFalla`.
- **`window.openFilterModal()`** (`:592`) — modal SweetAlert de filtros (Tipo Falla, Departamento); aplica o limpia navegando a `mantenimiento.catalogos-fallas.index` con query.
- **`deleteFalla(key)`** (`:689`) — confirma y hace `POST` con `_method=DELETE` a `mantenimiento.catalogos-fallas.destroy`; en éxito recarga.

### 6.7 `operadores-mantenimiento/index.blade.php`
**Propósito:** CRUD de operadores de mantenimiento; mismo patrón visual/JS que `catalogos-fallas`. **Secciones UI:** navbar Filtrar/Crear/Editar/Eliminar; tabla (CveEmpl, NomEmpl, Turno, Depto, Telefono) seleccionable; modales SweetAlert.

Funciones JS inline:
- **`updateTopButtonsState()`** (`:165`) — habilita/deshabilita Editar/Eliminar según selección.
- **`clearSelection()`** (`:177`) — limpia selección.
- **`selectRow(row)`** (`:187`) — selecciona/deselecciona fila.
- **`openCreateModal()`** (`:204`) — modal de alta de operador (CveEmpl, NomEmpl, Turno 1–3, Depto, Telefono).
- **`submitCreateForm(data)`** (`:307`) — `POST mantenimiento.operadores-mantenimiento.store`; en éxito recarga.
- **`handleTopEdit()`** (`:361`) — abre `openEditModal` con los `data-*` de la fila.
- **`openEditModal(key, cveEmpl, nomEmpl, turno, depto, telefono)`** (`:372`) — modal de edición pre-cargado.
- **`submitUpdateForm(key, data)`** (`:477`) — `POST` `_method=PUT` a `mantenimiento.operadores-mantenimiento.update`; recarga.
- **`handleTopDelete()`** (`:533`) — confirma y llama a `deleteOperador`.
- **`deleteOperador(key)`** (`:539`) — `POST` `_method=DELETE` a `mantenimiento.operadores-mantenimiento.destroy`; recarga.
- **`window.openFilterModal()`** (`:609`) — modal de filtros (Turno 1–3, Departamento); navega a `mantenimiento.operadores-mantenimiento.index` con query (conserva `q`).

---

## 7. JS dedicado

El módulo **no tiene archivos `.js` dedicados** en `resources/js`; toda la lógica de cliente está inline en los `<script>` de los blades (documentados en la sección 6). Las únicas funciones expuestas globalmente son `window.openFilterModal` (en ambos CRUD) y `mostrarModalFechas` (en el reporte de fallas/paros). Las vistas usan `fetch` crudo y SweetAlert/`alert` (no migradas aún a `window.http`/`window.notify`).

---

## 8. Lógica de negocio y reglas

### Cálculos y fórmulas
- **Folio del paro:** `FolioHelper::obtenerSiguienteFolio('ParosFallas', 5)` — secuencia de 5 dígitos que **auto-incrementa** al confirmar el alta.
- **Turno del reporte:** se toma de `Auth::user()->turno` (default 1) al crear; el turno de atención (`TurnoAtendio`) se llena al seleccionar el operador en el cierre.
- **Duración del paro (Excel):** `buildDifferenceText()` → diferencia `Xd Yh Zm` entre inicio (Fecha+Hora) y fin (FechaFin+HoraFin); se prefija `-` si el fin es anterior al inicio.
- **Calidad de atención:** escala 1–5 (estrellas en UI). El backend de `finalizar` valida `1..10` aunque la UI limita a 5.

### Restricciones / validaciones de negocio
- **No duplicar paro activo:** no se permite crear un paro `Activo` con la misma `MaquinaId` + `TipoFallaId` que otro ya activo (validado en cliente vía `validar-duplicado` y reforzado en `store`, HTTP 422). Se debe finalizar el actual antes de reportar otro igual.
- **Filtrado por área:** sin parámetros, el listado solo muestra paros del `area` del usuario; sin área → vacío.
- **Restricción Tejedores:** usuarios cuya área es `Tejedores` solo ven paros de sus telares asignados en `TelTelaresOperador` (salvo que filtren explícitamente otro departamento).
- **Usuario id 6:** en "nuevo paro" solo puede elegir `Urdido` y `Engomado`.
- **Catálogo compartido Tejido:** los salones de tejido (Jacquard, Itema, Karl Mayer, Smith, Tejedores, Trama, Desarrolladores, Supervisores) reutilizan las fallas del departamento `Tejido`; `Calidad` ve `Calidad` + `Tejido`.
- **Estatus:** alta crea `Estatus='Activo'`; finalizar lo pone en `Terminado` con `FechaFin`/`HoraFin`.

### Flujos completos
1. **Reportar paro:** la vista carga tipos de falla y departamentos (auto-selecciona el área del usuario) → al elegir departamento se cargan máquinas → al elegir máquina se habilita Tipo Falla y se sugiere Orden de Trabajo (programa en proceso) → al elegir tipo se cargan Falla/Descripción → submit valida duplicado y hace `POST /api/mantenimiento/paros` → genera folio, guarda en `ManFallasParos` → si "Notificar a Supervisor" está marcado, envía Telegram → SweetAlert con folio y vuelta a Solicitudes.
2. **Listar y seleccionar:** `/mantenimiento/solicitudes` carga paros (por área o filtros) → se selecciona una fila → "Terminar Paro" guarda el `id` en `localStorage` y navega a finalizar.
3. **Finalizar paro:** carga datos del paro (`GET /api/mantenimiento/paros/{id}`), operadores y estrellas → submit `PUT /api/mantenimiento/paros/{id}/finalizar` actualiza a `Terminado` con atendió/turno/calidad/observaciones → si "Enviar a Telegram", notifica el cierre → redirige a producción.
4. **Reporte por fechas + Excel:** selector → modal de rango → tabla server-side; si hay rango, botón "Descargar Excel" genera el `.xlsx` desde la plantilla `ReporteMantenimiento.xlsx`.

### Efectos colaterales entre módulos
- Las **máquinas** y **órdenes de trabajo** provienen de otras áreas: `URDCatalogoMaquina`, `AtaMaquinas`, `TelTelaresOperador` y los programas `ReqProgramaTejido` / `UrdProgramaUrdido` / `EngProgramaEngomado`. Cambios en esos catálogos/programas afectan lo que muestra Mantenimiento.
- Los **departamentos** se validan contra `SysDepartamentos`.

### Integraciones
- **Telegram:** notificaciones de alta y cierre de paro a los destinatarios de `SYSMensajes` según la columna mapeada por tipo de falla (`ReporteElectrico`, `ReporteMecanico`, `ReporteTiempoMuerto`, `Calidad`). Token desde `config('services.telegram.bot_token')`.
- **Excel:** exportación con `maatwebsite/excel` vía `ReporteMantenimientoExport` (usa plantilla `ReporteMantenimiento.xlsx`; lanza error si falta).
- **PDF:** no aplica en este módulo.

---

### Notas de cobertura
- Todas las funciones públicas de los 4 controllers están documentadas (`MantenimientoParosController`: 11 públicas; `CatalogosFallasController`: 4; `ManOperadoresMantenimientoController`: 4; `ReportesMantenimientoController`: 3 → 22 funciones públicas).
- Se documentan las 8 funciones privadas relevantes de `MantenimientoParosController`.
