# Atadores

> Generado automáticamente — documentación detallada del módulo

---

## 1. Propósito del módulo

El módulo **Atadores** gestiona la operación de **atado de julios** en el flujo productivo textil de Towell. Cuando un telar termina (o detiene) un julio de urdimbre, el área de atadores realiza el "atado" (unión de hilos del julio nuevo con los del telar) y registra el proceso de calidad/limpieza, máquinas usadas, actividades realizadas, merma generada y los tiempos de paro/arranque. El módulo cubre todo el ciclo de vida del atado mediante un flujo de estados:

```
Activo  →  En Proceso  →  Terminado  →  Calificado  →  Autorizado
```

Submódulos que abarca:

- **Programa Atadores** (`ProgramaAtadores/AtadoresController`): lista los julios pendientes/en proceso provenientes del inventario de telares (`tej_inventario_telares`, conexión MySQL/`mysql` por defecto del modelo `TejInventarioTelares`), permite **iniciar atado** y **calificar** el atado capturando máquinas, actividades, merma, observaciones y folio de paro. Visibilidad filtrada por rol (Tejedor, Atador, Supervisor).
- **Calificar Atadores**: vista de detalle de un atado en proceso donde se marcan máquinas y actividades, se captura merma/observaciones/folio de paro, se termina el atado, lo califica el tejedor y lo autoriza el supervisor.
- **Reportes Atadores** (`Reportes/ReportesAtadoresController`): selector de reportes; reporte de **Programa Atadores** por rango de fechas (exportable a Excel) y reporte **OEE Atadores** (eficiencia OEE semanal que se escribe/actualiza sobre el archivo anual `OEE_ATADORES.xlsx` en un share de red, vía job en cola).
- **Catálogos de Atadores** (`catalogos-atadores/`): tres catálogos CRUD — **Actividades** (con porcentaje), **Comentarios/Notas** y **Máquinas**.
- **Configuración** (nivel 3, vista vacía): punto de entrada estructural a submódulos de configuración.

Rol en el flujo productivo: es el eslabón entre **Tejido** (que genera el inventario de telares y detiene el telar registrando hora de paro) y el registro histórico de telares (`TejHistorialInventarioTelares`). Al autorizar un atado, el registro original del inventario MySQL se elimina y se inserta su histórico en SQL Server, cerrando el ciclo del julio.

---

## 2. Rutas

Archivo: `routes/modules/atadores.php`. Todas las rutas de archivos de módulo se cargan dentro del grupo con middleware `auth` (ver `routes/web.php`). No se observan llamadas a `userCan()` como middleware de ruta; los permisos se verifican dentro del controlador `index` (ver sección 3) y por la construcción del menú (`SYSRoles`/`SYSUsuariosRoles`).

| Método | URI | Controller@método | Middleware | Permiso requerido |
|---|---|---|---|---|
| GET | `/atadores/{moduloPrincipal?}` | `UsuarioController@showSubModulos` (default `atadores`) | auth | acceso al módulo "Atadores" (menú) |
| GET | `/atadores/configuracion/{moduloPadre?}` (default 502) | `UsuarioController@showSubModulosNivel3` | auth | submódulos nivel 3 padre 502 |
| GET | `/atadores/catalogos/{moduloPadre?}` (default 503) | `UsuarioController@showSubModulosNivel3` | auth | submódulos nivel 3 padre 503 |
| GET | `/atadores/programaatadores` | `AtadoresController@index` | auth | `acceso`+`crear` "Programa Atadores" (afecta visibilidad/filtros) |
| POST | `/atadores/programaatadores/exportar-excel` | `AtadoresController@exportarExcel` | auth | — |
| 301 | `/atadores/programa` → `/atadores/programaatadores` | redirect | auth | — |
| GET | `/atadores/iniciar` | `AtadoresController@iniciarAtado` | auth | — |
| GET | `/atadores/calificar` | `AtadoresController@calificarAtadores` | auth | — |
| GET | `/atadores/julios-atados` | `AtadoresController@cargarDatosUrdEngAtador` | auth | (método no implementado en el controlador) |
| POST | `/atadores/save` | `AtadoresController@save` | auth | autenticado (valida `Auth::user()`) |
| GET | `/atadores/show` | `AtadoresController@show` | auth | (método no implementado en el controlador) |
| GET | `/produccionProceso/atadores` | `AtadoresController@index` | auth | igual que `index` |
| POST | `/tejedores/validar` | `AtadoresController@validarTejedor` | auth | (método no implementado en el controlador) |
| GET | `/atadores/catalogos/actividades` | `AtaActividadesController@index` | auth | — |
| POST | `/atadores/catalogos/actividades` | `AtaActividadesController@store` | auth | — |
| GET | `/atadores/catalogos/actividades/{id}` | `AtaActividadesController@show` | auth | — |
| PUT | `/atadores/catalogos/actividades/{id}` | `AtaActividadesController@update` | auth | — |
| DELETE | `/atadores/catalogos/actividades/{id}` | `AtaActividadesController@destroy` | auth | — |
| GET | `/atadores/catalogos/comentarios` | `AtaComentariosController@index` | auth | — |
| POST | `/atadores/catalogos/comentarios` | `AtaComentariosController@store` | auth | — |
| GET | `/atadores/catalogos/comentarios/{nota1}` | `AtaComentariosController@show` | auth | — |
| PUT | `/atadores/catalogos/comentarios/{nota1}` | `AtaComentariosController@update` | auth | — |
| DELETE | `/atadores/catalogos/comentarios/{nota1}` | `AtaComentariosController@destroy` | auth | — |
| GET | `/atadores/catalogos/maquinas` | `AtaMaquinasController@index` | auth | — |
| POST | `/atadores/catalogos/maquinas` | `AtaMaquinasController@store` | auth | — |
| GET | `/atadores/catalogos/maquinas/{maquinaId}` | `AtaMaquinasController@show` | auth | — |
| PUT | `/atadores/catalogos/maquinas/{maquinaId}` | `AtaMaquinasController@update` | auth | — |
| DELETE | `/atadores/catalogos/maquinas/{maquinaId}` | `AtaMaquinasController@destroy` | auth | — |
| GET | `/atadores/reportes-atadores/` | `ReportesAtadoresController@index` | auth | — |
| GET | `/atadores/reportes-atadores/programa` | `ReportesAtadoresController@reportePrograma` | auth | — |
| GET | `/atadores/reportes-atadores/programa/excel` | `ReportesAtadoresController@exportarExcel` | auth | — |
| GET | `/atadores/reportes-atadores/atadores` | `ReportesAtadoresController@reporteAtadores` | auth | — |
| GET | `/atadores/reportes-atadores/atadores/descargar` | `ReportesAtadoresController@descargarExcelRango` | auth | — |
| GET | `/atadores/reportes-atadores/oee/verificar` | `ReportesAtadoresController@verificarOeeAtadores` | auth | — |
| POST | `/atadores/reportes-atadores/oee/despachar` | `ReportesAtadoresController@despacharOeeAtadores` | auth | — |
| GET | `/atadores/reportes-atadores/oee/estado/{token}` | `ReportesAtadoresController@estadoOeeAtadores` | auth | — |

Nota: la ruta `atadores.reportes.atadores.descargar` apunta a `descargarExcelRango`. El método público `exportarReporteAtadoresExcel` existe en el controlador pero **no está ruteado** en `atadores.php` (probablemente legacy/enlazado en otra parte).

---

## 3. Controllers

### 3.1 `AtadoresController` — `app/Http/Controllers/Atadores/ProgramaAtadores/AtadoresController.php`

#### `public function index(Request $request)` (línea 26)
Renderiza la vista del Programa de Atadores con el listado de julios.
- **Request**: `filtro` (opcional: `autorizados`, `todos`, u otro), `vista` (lista de filtros cliente separada por coma).
- **Query base** (`TejInventarioTelares`, modelo apuntando a `tej_inventario_telares`, conexión por defecto MySQL): selecciona campos del inventario y hace `leftJoin('AtaMontadoTelas', ...)` por `no_julio = NoJulio` y `no_orden = NoProduccion`, calculando `status_proceso` con un CASE sobre `AtaMontadoTelas.Estatus`. Filtra `no_julio` no nulo y no vacío.
- **Lógica de visibilidad por rol** (a partir de `user->area` y `user->puesto`):
  - **Tejedor** (`area` ∈ TEJEDORES/TEJEDOR): solo sus telares (de `TelTelaresOperador` por `numero_empleado`) y status `Terminado`. Sin telares asignados → no muestra nada (`whereRaw('0=1')`).
  - **Atador** (`puesto`/`area` atador, con `userCan('acceso','Programa Atadores')` y `userCan('crear','Programa Atadores')`): solo `Estatus` NULL o `En Proceso`.
  - **Supervisor** (`puesto` = supervisor): `Estatus` NULL, `En Proceso` o `Calificado`.
  - Otros roles → no muestra registros.
  - Filtro `autorizados` → delega en `getAutorizadosParaVista()`.
  - Cualquier otro filtro personalizado → trae todos (el frontend filtra).
- **Permisos verificados**: `userCan('acceso', 'Programa Atadores')` y `userCan('crear', 'Programa Atadores')` (combinados en `$tienePermisosAtadores`).
- **Respuesta**: vista `modulos.atadores.programaAtadores.index` con `inventarioTelares`, `filtroAplicado`, `telaresUsuario`, `esTejedor`, `esSupervisor`, `vista`, `filtroGlobalActivo`.
- **Tablas**: `tej_inventario_telares` (MySQL), `AtaMontadoTelas` (join), `TelTelaresOperador`.

#### `protected function getAutorizadosParaVista()` (línea 174)
Devuelve todos los registros `AtaMontadoTelasModel` con `Estatus='Autorizado'` (sqlsrv) y los combina con `TejInventarioTelares` cuando hay coincidencia (NoJulio+NoProduccion); si no existe inventario, construye un objeto sintético con los datos del montado. Permite ver autorizados aunque su fila de inventario ya se haya eliminado.

#### `public function exportarExcel(Request $request)` (línea 219)
Exporta a Excel los atadores de un rango de fechas.
- **Request**: `fecha_inicio`, `fecha_fin` (requeridos; si falta alguno o inicio > fin, redirige con error).
- **Respuesta**: descarga `Maatwebsite\Excel` con `App\Exports\ProgramaAtadoresExport($inicio, $fin)`, archivo `atadores_dd-mm-YYYY_a_dd-mm-YYYY.xlsx`.

#### `public function iniciarAtado(Request $request)` (línea 243)
Inicia el proceso de atado de un julio.
- **Request**: `no_julio`, `no_orden`, `id` (de `tej_inventario_telares`).
- **Lógica**:
  - Si llegan `no_julio`+`no_orden` y ya existe `AtaMontadoTelas` en estado `En Proceso/Terminado/Calificado/Autorizado` → redirige a `atadores.calificar` (continuar, o solo lectura si Autorizado).
  - Valida que exista `id`, que el `TejInventarioTelares` exista y que coincidan `no_julio`/`no_orden`; valida que el registro tenga datos necesarios.
  - Si no hay atado previo: crea `AtaMontadoTelasModel` con `Estatus='En Proceso'`, copiando datos del inventario, asigna `CveTejedor`/`NomTejedor` = usuario en sesión, `HrInicio` = hora actual. Actualiza `tej_inventario_telares.status='En Proceso'`.
  - Crea registros base en `AtaMontadoMaquinas` (uno por cada `AtaMaquinas` del catálogo, `Estado=0`) y en `AtaMontadoActividades` (uno por cada `AtaActividades` con su `Porcentaje`, `Estado=0`, `Turno` del inventario).
- **Respuesta**: redirect a `atadores.calificar?no_julio=&no_orden=` con flash success/info/error.
- **Tablas**: `tej_inventario_telares` (MySQL), `AtaMontadoTelas`, `AtaMontadoMaquinas`, `AtaMontadoActividades`, `AtaMaquinas`, `AtaActividades` (sqlsrv).

#### `public function calificarAtadores(Request $request)` (línea 372)
Renderiza la vista de calificación/detalle del atado.
- **Request**: `no_julio`, `no_orden` (query, opcionales). Si se pasan, filtra el `AtaMontadoTelas` específico; si no, trae todos los activos/autorizados.
- **Lógica**: carga catálogos (`AtaMaquinas`, `AtaActividades`, `AtaComentarios`), y los estados de máquinas/actividades del proceso actual (`AtaMontadoMaquinas`/`AtaMontadoActividades` keyed por Id). Si faltan filas base de actividades para el folio, las crea (compatibilidad con datos históricos). Valida coincidencia NoJulio/NoProduccion.
- **Respuesta**: vista `modulos.atadores.calificar-atadores.index` con `montadoTelas`, `maquinasCatalogo`, `maquinasMontado`, `actividadesCatalogo`, `actividadesMontado`, `comentarios`.

#### `public function save(Request $request)` (línea 469)
Endpoint **multi-acción** (JSON) que procesa todas las mutaciones del detalle de atado. Requiere `Auth::user()` (401 si no). Requiere `no_julio` + `no_orden` (422 si faltan); localiza el `AtaMontadoTelas` activo (`En Proceso/Terminado/Calificado`). Discrimina por `action`:

- **`operador`**: asigna `CveTejedor`/`NomTejedor` = usuario actual.
- **`supervisor`**: requiere `Estatus='Calificado'`. Dentro de una **transacción sqlsrv**: marca `Estatus='Autorizado'`, registra supervisor (`CveSupervisor`, `NomSupervisor`, `FechaSupervisor`), guarda `comments_sup`; inserta en `TejHistorialInventarioTelares` (query builder, con Cuenta/Calibre/Fibra/TipoAtado/Localidad tomados del `TejInventarioTelares` original, `Status='Completado'`, `FechaAtado=now`); asegura filas base de máquinas/actividades. Tras commit, elimina el `TejInventarioTelares` original (MySQL, fuera de la transacción). Conserva las tablas de montado como histórico. Devuelve `redirect` a `atadores.programa`. Rollback en excepción.
- **`calificacion`**: requiere `Estatus='Terminado'`. Valida `calidad` (int 1-10), `limpieza` (int 5-10), `comments_tej` (nullable, max 500). Actualiza `AtaMontadoTelas` (Calidad, Limpieza, comments_tej, CveTejedor/NomTejedor, `Estatus='Calificado'`) y `tej_inventario_telares.status='Calificado'`.
- **`observaciones`**: bloqueado si Terminado/Calificado/Autorizado. Guarda `Obs`.
- **`merga`**: bloqueado si terminado+. Valida numérico; guarda `MergaKg` (null si vacío).
- **`folio_paro`**: bloqueado si terminado+. Verifica que exista la columna `FolioParo` en `AtaMontadoTelas` (Schema sqlsrv) y respeta su longitud (consulta `INFORMATION_SCHEMA.COLUMNS`); guarda `FolioParo`.
- **`maquina_estado`**: bloqueado si terminado+. Valida `maquinaId` (string max 50), `estado` (boolean). `updateOrInsert` en `AtaMontadoMaquinas` con `Estado` 1/0.
- **`terminar`**: bloqueado si ya terminado+. Requiere `MergaKg` capturada. Registra `HoraArranque` (hora actual), `FechaArranque` (vía `resolverFechaArranque`), calcula `TiempoParo` = diferencia HH:MM:SS entre `HrInicio` y arranque (suma 1 día si cruza medianoche), `Estatus='Terminado'`, `comments_ata`. Actualiza `tej_inventario_telares.status='Terminado'` y dispara notificación Telegram (`enviarNotificacionTelegramAtadoTerminado`).
- **`actividad_estado`**: bloqueado si terminado+. `updateOrInsert` en `AtaMontadoActividades`: `Estado` 1/0; al activar asigna operador = usuario actual (`CveEmpl`/`NomEmpl`), al desactivar los limpia; copia `Porcentaje` del catálogo y `Turno`. Devuelve el operador para reflejarlo en la UI.
- Por defecto: `{ ok:false, message:'Acción no válida' }` (422).
- **Tablas tocadas (sqlsrv)**: `AtaMontadoTelas`, `AtaMontadoMaquinas`, `AtaMontadoActividades`, `AtaMaquinas`, `AtaActividades`, `TejHistorialInventarioTelares`; **MySQL**: `tej_inventario_telares` (vía `TejInventarioTelares`).

#### `private function resolverFechaArranque(Carbon $momento): string` (línea 992)
Regla de fecha para turno 3: si la hora está entre 00:00:00 y 06:30:00 se considera fecha del día anterior; en otro caso, el mismo día.

#### `private function enviarNotificacionTelegramAtadoTerminado(AtaMontadoTelasModel $montado, $usuario, ?string $horaArranque = null): void` (línea 1006)
Envía notificación Telegram al terminar un atado. Toma destinatarios de `SYSMensaje::getChatIdsPorModulo('Atadores')`; si no hay, usa `TELEGRAM_CHAT_ID` global. Construye un mensaje con Telar/Tipo/Julio/Orden/Metros/Merma/HoraParo/HoraArranque/Fecha/Operador y lo envía a cada chat vía `https://api.telegram.org/bot{token}/sendMessage` (`Http::timeout(20)->retry(2,200)`). Loguea errores; no rompe el flujo (try/catch en el caller).

---

### 3.2 `ReportesAtadoresController` — `app/Http/Controllers/Atadores/Reportes/ReportesAtadoresController.php`

Constante `OEE_QUEUE = 'oee-atadores'`.

#### `public function index()` (línea 28)
Devuelve la vista `modulos.atadores.reportes.index` con un arreglo de reportes disponibles (Programa Atadores y OEE Atadores) con sus URLs.

#### `public function reportePrograma(Request $request)` (línea 51)
Vista `modulos.atadores.reportes.programa`. Si no llegan `fecha_ini`/`fecha_fin`, devuelve la vista con datos vacíos (la vista abre el modal de rango). Si llegan, formatea fechas Y-m-d y las pasa a la vista.

#### `public function exportarExcel(Request $request)` (línea 76)
Exporta el Programa Atadores. Toma `fecha_inicio`/`fecha_ini` y `fecha_fin`; valida presencia y orden; descarga `ProgramaAtadoresExport($inicio, $fin)` como `atadores_dd-mm-YYYY_a_dd-mm-YYYY.xlsx`.

#### `public function reporteAtadores(Request $request)` (línea 100)
Vista `modulos.atadores.reportes.atadores` para el OEE. Resuelve el rango (vía `resolverRangoFechasAtadoresDesdeRequest`) y pasa `fechaIni`, `fechaFin`, `lunesIni` (inicio de semana) y `domingoFin`.

#### `public function exportarReporteAtadoresExcel(Request $request)` (línea 113) — *no ruteada*
Genera/guarda el archivo **anual** del OEE Atadores en la ruta de red configurada. Sube `set_time_limit(300)`. Exige rango válido y dentro del mismo año (si difiere el año, redirige con error). Calcula el rango anual completo (`resolverRangoAnualAtadores`) y llama `guardarReporteAtadoresEnRuta(new Reporte00EAtadoresRangoExport(...), "OEE Atadores {year}.xlsx")`. Redirige con success/error.

#### `public function verificarOeeAtadores(Request $request): JsonResponse` (línea 169)
GET `/oee/verificar`. Resuelve el rango de semanas; si inválido → 422. Construye `OeeAtadoresFileService($filePath)` y llama `verificarSemanasConDatos($lunesInicio, $lunesFin)`, devolviendo qué semanas ya tienen datos en `OEE_ATADORES.xlsx`. 500 en excepción.

#### `public function descargarExcelRango(Request $request)` (línea 193)
GET `/atadores/descargar`. Descarga directamente un Excel del rango (no toca el archivo OEE de red) con `Reporte00EAtadoresRangoExport($lunesInicio, $lunesFin)`. Nombre `OEE_Atadores_dd-mm-YYYY_al_dd-mm-YYYY.xlsx`.

#### `public function despacharOeeAtadores(Request $request): JsonResponse` (línea 217)
POST `/oee/despachar`. Resuelve el rango (422 si inválido). Verifica que exista el archivo OEE (422 si no). Genera un `token` aleatorio, guarda estado `{estado:'despachado'}` en cache de archivo (`oee_job_{token}`, TTL 600s), despacha `ActualizarOeeAtadoresJob` en la cola `oee-atadores`, arranca el worker (`bootOeeQueueWorker`) y devuelve `{ token }`.

#### `public function estadoOeeAtadores(Request $request, string $token): JsonResponse` (línea 249)
GET `/oee/estado/{token}`. Lee el estado de la cache (`oee_job_{token}`). Si el estado referencia un `status_file` JSON existente, lo fusiona con el estado. Devuelve `{estado:'desconocido'}` si no hay nada.

#### Privadas relevantes
- `oeeAtadoresFilePath()` (277): resuelve la ruta del `OEE_ATADORES.xlsx` (prioridad `env OEE_ATADORES_FILE_PATH` > `config('filesystems.disks.reports_atadores.root')` > `\\192.168.2.11\ti-system`).
- `resolverRangoFechasAtadoresDesdeRequest()` (289): devuelve `[fechaInicio, fechaFin, lunesInicio, lunesFin]`; si no hay fechas, cae a rango por semanas ISO.
- `resolverRangoSemanasDesdeRequest()` (321): admite `semana`, `semana_ini`, `semana_fin` (formato `YYYY-Www`).
- `resolverFecha()` (346) / `resolverSemanaIso()` (360): parseo robusto de fecha y de semana ISO (valida `^(\d{4})-W(\d{2})$`, semana 1-53).
- `resolverRangoAnualAtadores()` (383): rango anual de lunes a lunes.
- `guardarReporteAtadoresEnRuta()` (392): escribe el Excel en la ruta de red (normaliza separadores, crea directorio, usa `Excel::raw(..., XLSX)`, `file_put_contents`).
- `bootOeeQueueWorker()` (430): si la cola no es `sync`, lanza en segundo plano `php artisan queue:work {conn} --queue=oee-atadores --once --stop-when-empty` (Windows `start /B`, otros `exec ... &`).

---

### 3.3 `AtaActividadesController` — `app/Http/Controllers/Atadores/Catalogos/Actividades/AtaActividadesController.php`

CRUD del catálogo de actividades (respuestas JSON `{success, message|data}`).
- `index()` (14): vista `modulos.catalogos-atadores.actividades.index` con todas las actividades.
- `store()` (23): valida `ActividadId` (required, string max 255, unique en `AtaActividades`) y `Porcentaje` (required, numeric 0-100); crea.
- `update($id)` (51): `firstOrFail` por `ActividadId`; valida unique excluyendo el actual; actualiza.
- `destroy($id)` (81): `firstOrFail` y elimina.
- `show($id)` (102): devuelve la actividad o 404.

### 3.4 `AtaComentariosController` — `app/Http/Controllers/Atadores/Catalogos/Comentarios/AtaComentariosController.php`

CRUD del catálogo de notas/comentarios (clave de negocio: `Nota1`).
- `index()` (14): vista `modulos.catalogos-atadores.comentarios.index`.
- `store()` (23): valida `Nota1` (required, string max 500), `Nota2` (nullable, max 500); crea.
- `update($nota1)` (51): `firstOrFail` por `Nota1`; valida unique (excluyendo actual); actualiza.
- `destroy($nota1)` (81): elimina por `Nota1`.
- `show($nota1)` (102): devuelve o 404.

### 3.5 `AtaMaquinasController` — `app/Http/Controllers/Atadores/Catalogos/Maquinas/AtaMaquinasController.php`

CRUD del catálogo de máquinas (clave `MaquinaId`).
- `index()` (14): vista `modulos.catalogos-atadores.maquinas.index`.
- `store()` (23): valida `MaquinaId` (required, string max 255, unique); crea.
- `update($maquinaId)` (49): `firstOrFail`; valida unique excluyendo actual; actualiza.
- `destroy($maquinaId)` (77): elimina.
- `show($maquinaId)` (98): devuelve o 404.

---

## 4. Services y Helpers del ámbito

### 4.1 `OeeAtadoresFileService` — `app/Services/OeeAtadores/OeeAtadoresFileService.php`

Servicio que lee y reescribe el archivo Excel anual `OEE_ATADORES.xlsx` con PhpSpreadsheet. Construido con `__construct(private string $filePath)`. Contiene gran cantidad de constantes de layout (columnas de promedios `AVG_TIME_COLS`/`AVG_CALIF_COLS`/`AVG_MERMA_COLS`, marcadores de sección `SEMANA`, filas de footer/`ATADOS`, plantillas de hojas SEMANA/CONCENTRADO/TOTAL ATADOS/ANNUAL, meses, etc.).

Métodos **públicos**:
- `verificarSemanasConDatos(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array` (171): verifica el workbook, obtiene las semanas del rango, valida que sean del mismo año ISO, parsea la hoja `DETALLE` y construye un diagnóstico por semana (sección encontrada, filas, atadores visibles, `tiene_datos`). Devuelve `anio_iso`, `semanas_rango`, `semanas_con_datos`, `diagnostico`.
- `actualizarArchivo(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): string` (198): reescribe el archivo OEE en disco. Sube límites de memoria/tiempo; copia el archivo a temporal local (rendimiento de red); carga el workbook (sin charts por bug de corrupción), parsea secciones de `DETALLE`; por cada semana decide entre **reconstruir desde prototipo**, **reemplazar** o **insertar** la sección semanal (usando `Reporte00EAtadoresExport` para renderizar datos). **Modo detalle-only**: solo toca la hoja DETALLE y preserva el resto de hojas (SEMANA/CONCENTRADO/TOTAL/gráficas/ANNUAL). Guarda a un archivo temporal y lo renombra/copia sobre el destino. Devuelve la ruta final. Loguea cada etapa con tiempos.

Métodos privados destacados (lógica interna de manipulación de Excel): `parseDetalleSections` (detecta secciones por marcadores `SEMANA`/`ATADOS`), `generateWeeklySection`, `rebuildDetalleSectionFromPrototype`, `replaceDetalleSection`, `insertDetalleSection`, `resizeDetalleSection`, `copySectionRange` (copia estilos/valores/fórmulas desplazando referencias con `ReferenceHelper`), `normalizeDetalleFooterWeeks`/`normalizeDetalleVisualWeeks`, `syncSemanaSheets`/`rebuildConcentradoSheets` (sincronización de hojas SEMANA/CONCENTRADO, no usadas en modo detalle-only), helpers de carga de nombres de atadores y construcción de fórmulas (`writeCkCuFormulas`, `extractAtadorList`, `loadAtadorNamesForWeek`).

> Nota: el servicio es muy extenso (~3098 líneas). La documentación cubre la API pública y la mecánica general; el detalle fila-a-fila de cada constante de layout queda fuera de alcance.

### 4.2 `OeeAtadoresPythonExportService` — `app/Services/OeeAtadores/OeeAtadoresPythonExportService.php`

Alternativa basada en script Python para generar el OEE.
- `run(string $filePath, CarbonImmutable $weekStart, CarbonImmutable $weekEnd, string $token, string $statusFilePath): void` (16): ejecuta `scripts/oee_export.py` (ruta en `config('oee.script_path')`). Resuelve la conexión de BD (`config('oee.database_connection')`, default `sqlsrv`) y pasa host/database/username/password/port como argumentos al script, junto con week-start/end, token, file-path y status-file. Usa `Process::timeout(config('oee.python_timeout', 900))`. Lanza `RuntimeException` con la salida de error si el proceso falla.
- `pythonBinaryParts(): array` (privado, 71): resuelve el binario de Python desde `config('oee.python_binary', 'python')`, soportando rutas con argumentos.

### 4.3 Job relacionado
`App\Jobs\ActualizarOeeAtadoresJob` (`app/Jobs/ActualizarOeeAtadoresJob.php`) — despachado por `despacharOeeAtadores` en la cola `oee-atadores`; orquesta la actualización del archivo OEE (vía uno de los servicios anteriores) y actualiza el estado en cache para el polling de la UI.

### 4.4 Helpers globales usados
- `userCan('acceso'|'crear', 'Programa Atadores')` (en `index`): control de visibilidad/filtros por permiso de módulo.
- `SYSMensaje::getChatIdsPorModulo('Atadores')`: destinatarios Telegram.
- `FolioHelper`/`TurnoHelper`: **no se usan** en este módulo. La fecha de arranque/turno 3 se resuelve con la regla ad-hoc `resolverFechaArranque` (corte 06:30) en lugar de `TurnoHelper`.

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `Atadores\AtaMontadoTelasModel` | `dbo.AtaMontadoTelas` | sqlsrv | `Id` (int, auto) | Estatus, Fecha, Turno, NoJulio, NoProduccion, Tipo, Metros, NoTelarId, MergaKg, HoraParo, HoraArranque, HrInicio, FechaArranque, TiempoParo, Calidad, Limpieza, CveTejedor/NomTejedor, CveSupervisor/NomSupervisor, FechaSupervisor, Obs, FolioParo, comments_sup/comments_ata/comments_tej, ConfigId, InventSizeId, InventColorId, LoteProveedor, NoProveedor |
| `Atadores\AtaMontadoMaquinasModel` | `dbo.AtaMontadoMaquinas` | sqlsrv | `Id` (int, auto) | NoJulio, NoProduccion, MaquinaId, Estado, CveEmpl, NomEmpleado (NomEmpl comentado en fillable) |
| `Atadores\AtaMontadoActividadesModel` | `dbo.AtaMontadoActividades` | sqlsrv | `Id` (int, auto) | NoJulio, NoProduccion, ActividadId, Porcentaje, Estado, CveEmpl, NomEmpl, Turno |
| `Atadores\AtaMaquinasModel` | `dbo.AtaMaquinas` | sqlsrv | `MaquinaId` (string, no incrementing) | MaquinaId |
| `Atadores\AtaActividadesModel` | `dbo.AtaActividades` | sqlsrv | `Id` (int, auto) | ActividadId, Porcentaje |
| `Atadores\AtaComentariosModel` | `dbo.AtaComentarios` | sqlsrv | `Nota1` (string, no incrementing) | Nota1, Nota2 |

Todos con `$timestamps = false`. Tablas externas tocadas por el módulo: `tej_inventario_telares` (modelo `Tejido\TejInventarioTelares`, **MySQL**), `TejHistorialInventarioTelares` (sqlsrv, vía query builder), `TelTelaresOperador` (`Tejedores\TelTelaresOperador`), `SYSMensajes` (`Sistema\SYSMensaje`).

---

## 6. Vistas Blade

### 6.1 `modulos/atadores/programaAtadores/index.blade.php` (1057 líneas)
**Propósito**: tabla del programa de atadores con filtros por estado/columna, ordenamiento, auto-refresh de estatus y botón para iniciar atado.
**Secciones UI**: tabla `#atadoresTableHead`/`#tb-body` con filas `data-*` (status, telar, no-julio, no-orden, hora-paro...), modal de filtros `#modalFiltros`, menú contextual `#tableContextMenu`, badges de filtro, botón `#btnIniciarAtado`.

Funciones JS inline (bloque `<script>`):
- `getDataKey(col)` (318): mapea clave de columna a su `data-*` attribute.
- `updateSortIcons()` (325): pinta el icono ▲/▼ en la columna ordenada.
- `mostrarModalFiltros()` / `cerrarModalFiltros()` (338/346): abren/cierran el modal de filtros.
- `aplicarFiltro(tipo)` (357): aplica/quita un filtro. `autorizados` y `todos` navegan al servidor (`?filtro=`), otros filtros operan en cliente o piden `?filtro=todos&vista=`. Endpoint: `route('atadores.programa')`.
- `statusMatchesFilter(status, noTelar, filterKey)` (405): determina si un status coincide con la clave de filtro (creados/activo/en-proceso/calificados/terminados/autorizados); para tejedores restringe `terminados` a sus telares.
- `applyFilters()` (433): muestra/oculta filas según `filterState.filtros`; gestiona el mensaje "sin resultados" y el badge.
- `updateFilterButtons()` (487): actualiza el estilo visual de los botones de filtro activos.
- `sortTable()` (559): ordena las filas por la columna/dirección activas (numérico para metros/calibre/julio/orden).
- listener `.th-sortable` click (591): alterna columna/dirección y reordena.
- listener `#modalFiltros` click (606): cierra el modal al clicar fuera.
- `closeContextMenu()` / `openContextMenu(e, column, label)` (619/623): menú contextual de filtro por columna (clic derecho en encabezado).
- listener `#atadoresTableHead` contextmenu (648) y `document` click (657): abren/cierran el menú contextual.
- listener `#tableContextMenu` click (664): acciones `filter-column` (Swal input), `clear-column-filter`, `clear-all-filters`.
- `applyColumnFilters()` (736): filtra filas por valores de columna combinándolo con los filtros de status.
- `updateColumnFilterBadge()` (793): resalta encabezados con filtro de columna activo.
- listener `DOMContentLoaded` (819): inicializa botones/orden.
- `refreshStatus()` (832): cada 5s hace `fetch` a `route('atadores.programa')`, parsea el HTML y actualiza in-place los `data-status`/`data-telar` de las filas, preservando la selección. Endpoint: `atadores.programa`.
- `selectRow(row, id)` (907): selecciona/deselecciona una fila; exige `hora-paro` y `no_julio`/`no_orden`.
- `enableIniciarButton()` / `disableIniciarButton()` (972/980): habilitan/deshabilitan el botón iniciar.
- `iniciarAtado()` (988): valida selección y navega a `route('atadores.iniciar')?id=&no_julio=&no_orden=` (forzando la navegación).

### 6.2 `modulos/atadores/calificar-atadores/index.blade.php` (1157 líneas)
**Propósito**: detalle del atado en proceso: marcar máquinas/actividades, capturar merma/observaciones/folio de paro, terminar, calificar (tejedor) y autorizar (supervisor).
**Secciones UI**: panel de datos del julio, tabla de máquinas (checkboxes `toggleMaquina`), tabla de actividades (checkboxes `toggleActividad`, celda operador), campos `#mergaKg`, `#observaciones`, `#folioParo`, indicadores de auto-guardado, notas rápidas, botones `#btnTerminar`, `#btnCalificar`, `#btnAutorizar`.
Constantes JS desde Blade: `currentNoJulio`/`currentNoOrden`/`esSoloLectura` (Autorizado), `actividadesData` (id+estado), `MERGA_MAX=5`.

Funciones JS inline:
- `normalizarMergaNumero(valor)` / `normalizarMergaTexto(valor)` (433/437): redondean la merma a 2 decimales.
- `normalizarMergaInput()` (441): normaliza el input de merma al perder foco.
- `handleObservacionesChange()` (458): debounce 2s y dispara auto-guardado de observaciones.
- `handleMergaChange(valor)` (481): valida tope 5kg, debounce 1.5s y guarda merma.
- `handleFolioParoChange(valor)` (517): debounce 1.2s y guarda folio de paro.
- `guardarObservacionesAuto()` (534): `fetch` POST `action:'observaciones'`. Endpoint `atadores.save`.
- `terminarAtado()` (581): valida merma, pide comentarios (Swal) y `fetch` POST `action:'terminar'`; deshabilita controles y habilita Calificar. Endpoint `atadores.save`.
- `calificarTejedor()` (680): Swal con calidad (1-10)/limpieza (5-10)/comentarios; `fetch` POST `action:'calificacion'`; actualiza UI y habilita Autorizar. Endpoint `atadores.save`.
- `autorizaSupervisor()` (780): Swal con comentarios; `fetch` POST `action:'supervisor'`; redirige al programa al completar. Endpoint `atadores.save`.
- `guardarObservaciones(event)` (850): guardado manual de observaciones (bloquea si solo lectura). Endpoint `atadores.save`.
- `guardarMerga(valor)` (895): valida número/tope y `fetch` POST `action:'merga'`. Endpoint `atadores.save`.
- `guardarFolioParo(valor)` (980): `fetch` POST `action:'folio_paro'`. Endpoint `atadores.save`.
- `agregarNota(texto)` (1020): inserta una nota del catálogo en el textarea de observaciones.
- `toggleMaquina(maquinaId, checked)` (1029): `fetch` POST `action:'maquina_estado'`; revierte el checkbox si falla. Endpoint `atadores.save`.
- `toggleActividad(actividadId, checked)` (1064): valida que solo el operador que marcó pueda desmarcar; `fetch` POST `action:'actividad_estado'`; actualiza la celda operador. Endpoint `atadores.save`.

### 6.3 `modulos/atadores/reportes/index.blade.php` (33 líneas)
**Propósito**: lista de reportes disponibles (Programa Atadores, OEE Atadores) como enlaces. Sin JS propio.

### 6.4 `modulos/atadores/reportes/programa.blade.php` (118 líneas)
**Propósito**: reporte de Programa Atadores por rango de fechas; abre modal de rango si no hay fechas.
Funciones JS:
- `window.volverAlIndice()` (61): navega a `atadores.reportes.index`.
- `mostrarModalConsultarReportesAtadores()` (65): Swal con fecha inicial/final; navega a `route('atadores.reportes.programa')?fecha_ini=&fecha_fin=`.
- listener `DOMContentLoaded` (112): abre el modal automáticamente si faltan fechas.

### 6.5 `modulos/atadores/reportes/atadores.blade.php` (237 líneas)
**Propósito**: reporte/exportación OEE Atadores con verificación de semanas y job en cola.
Funciones JS:
- `formatearFechaInput(fecha)` (89) / `obtenerFechaActualInput()` (96): formateo de fecha a `YYYY-MM-DD`.
- `exportarAOeeAtadores()` (100): (1) `fetch` GET `atadores.reportes.oee.verificar` para detectar semanas con datos; (2) Swal de confirmación advirtiendo sobrescrituras; (3) `fetch` POST `atadores.reportes.oee.despachar` para obtener token; (4) polling cada 3s (máx 10 min) a `atadores/reportes-atadores/oee/estado/{token}` hasta `completado`/`error`. Endpoints: verificar/despachar/estado OEE.
- `mostrarModalRangoFechasAtadores()` (189): Swal de rango de fechas (agrupa lunes-domingo por `FechaArranque`); navega a `route('atadores.reportes.atadores')?fecha_ini=&fecha_fin=`.

### 6.6 `modulos/atadores/configuracion/index.blade.php` (vacío)
Vista placeholder sin contenido (0 líneas); el ruteo de configuración usa `UsuarioController@showSubModulosNivel3`.

### 6.7 Catálogos `modulos/catalogos-atadores/{actividades,comentarios,maquinas}/index.blade.php`
Los tres comparten un patrón CRUD idéntico (tabla + modales crear/editar/ver/eliminar, consumo vía `axios`):
- `selectRow(row, key)`: selecciona una fila de la tabla y habilita los botones.
- `enableButtons()` / `disableButtons()`: habilitan/deshabilitan editar/eliminar.
- `editSelected()` / `deleteSelected()`: abren edición/eliminación de la fila seleccionada.
- `openCreateModal()`: abre el modal de alta (reset del form).
- `openEditModal(key)`: `axios.get` al endpoint show del catálogo y rellena el form.
- `openViewModal(key)`: `axios.get` y muestra los datos en modo lectura.
- `closeFormModal()` / `closeViewModal()` / `closeDeleteModal()`: cierran modales.
- `confirmDelete(key)`: abre el modal de confirmación.
- `delete{Actividad|Comentario|Maquina}()`: `axios.delete` al endpoint destroy; quita la fila y muestra Swal.
- `handleSubmit(event)`: `axios.post`/`axios.put` al endpoint store/update según sea alta o edición; recarga la página al éxito.

Endpoints consumidos por catálogo:
- Actividades: `/atadores/catalogos/actividades[/{id}]` (GET/POST/PUT/DELETE).
- Comentarios: `/atadores/catalogos/comentarios[/{nota1}]`.
- Máquinas: `/atadores/catalogos/maquinas[/{maquinaId}]`.

---

## 7. JS dedicado

No existen archivos `.js` dedicados al ámbito Atadores en `resources/js/`. Toda la lógica de cliente vive **inline** en los bloques `<script>`/`@push('scripts')` de las vistas Blade (documentadas en la sección 6). Las vistas usan las librerías globales del proyecto: **SweetAlert2** (`Swal`), **axios** (en catálogos), `fetch` nativo (en programa/calificar/reportes) y el token CSRF (`meta[name="csrf-token"]` / `csrf_token()`).

> Observación de migración: las vistas de Atadores aún usan `fetch(...).then(r => r.json())` y `Swal.fire(...)` directamente; no han sido migradas a `window.http` / `window.notify` (clientes globales recomendados en CLAUDE.md).

---

## 8. Lógica de negocio y reglas

### Flujo de estados del atado
1. **Activo**: julio presente en `tej_inventario_telares` con `no_julio` lleno y sin fila en `AtaMontadoTelas`.
2. **En Proceso**: `iniciarAtado` crea la fila `AtaMontadoTelas` (`HrInicio`=hora actual, operador=usuario) y siembra filas base de máquinas/actividades desde los catálogos. El inventario pasa a `status='En Proceso'`. Se permiten múltiples procesos simultáneos.
3. **Terminado** (`save action='terminar'`): exige merma capturada; registra `HoraArranque`, `FechaArranque` (regla turno 3) y calcula `TiempoParo` (diff HH:MM:SS, +1 día si cruza medianoche). Dispara Telegram. Inventario `status='Terminado'`.
4. **Calificado** (`save action='calificacion'`): exige estado Terminado; guarda Calidad (1-10) y Limpieza (5-10) + comentarios del tejedor. Inventario `status='Calificado'`.
5. **Autorizado** (`save action='supervisor'`): exige estado Calificado; en transacción sqlsrv inserta el histórico en `TejHistorialInventarioTelares` (`Status='Completado'`), marca supervisor; tras commit **elimina** la fila MySQL de `tej_inventario_telares`. Las tablas de montado se conservan como histórico.

### Cálculos y fórmulas
- **TiempoParo** = diferencia entre `HrInicio` y `HoraArranque` formateada como `HH:MM:SS`; si el arranque es menor que el inicio se asume cruce de medianoche (+1 día).
- **FechaArranque** (turno 3): si la hora de cierre cae entre 00:00:00 y 06:30:00, se asigna la fecha del día anterior.
- **Merma (MergaKg)**: tope **5 kg** validado en cliente y normalizada a 2 decimales; requerida antes de terminar.
- **Calidad** 1-10, **Limpieza** 5-10 (validación servidor y cliente).
- **OEE Atadores**: el agrupamiento semanal es de **lunes a domingo** usando `FechaArranque`; las semanas se identifican por número ISO; el archivo anual `OEE_ATADORES.xlsx` se reescribe por sección semanal sobre la hoja `DETALLE`.

### Restricciones/validaciones de negocio
- No se puede iniciar atado si el telar no tiene **hora de paro** (validación cliente en `selectRow`/`iniciarAtado`).
- Observaciones, merma, folio de paro, máquinas y actividades **solo se editan en estado En Proceso** (bloqueado en Terminado/Calificado/Autorizado).
- Un usuario **no puede desmarcar una actividad** realizada por otro (validación cliente comparando el operador con `currentUser`).
- Registros **Autorizado** son de solo lectura (`esSoloLectura`).
- `FolioParo` respeta la longitud real de su columna (consulta `INFORMATION_SCHEMA.COLUMNS`) para evitar truncamiento.

### Flujos completos / efectos colaterales entre módulos
- **Tejido → Atadores**: el inventario `tej_inventario_telares` (MySQL) es la fuente; el atado lo marca con `status` (`En Proceso`/`Terminado`/`Calificado`) y finalmente lo elimina al autorizar.
- **Atadores → Histórico de telares**: al autorizar se inserta en `TejHistorialInventarioTelares` (sqlsrv), cerrando el ciclo del julio (combina datos del montado con Cuenta/Calibre/Fibra/TipoAtado/Localidad del inventario original).
- **Visibilidad por rol** (Tejedor / Atador / Supervisor) controlada en `index` mediante `area`/`puesto`/`userCan`, con `TelTelaresOperador` para los telares del tejedor.

### Integraciones
- **Telegram**: notificación "ATADO TERMINADO" a destinatarios de `SYSMensaje::getChatIdsPorModulo('Atadores')` (fallback `TELEGRAM_CHAT_ID`), tolerante a fallos.
- **Excel (maatwebsite/excel)**: exports `ProgramaAtadoresExport`, `Reporte00EAtadoresRangoExport`, `Reporte00EAtadoresExport` (este último usado internamente por el servicio OEE para renderizar secciones).
- **PhpSpreadsheet**: `OeeAtadoresFileService` manipula directamente el `.xlsx` anual en un share de red; las gráficas se omiten al guardar (bug conocido de corrupción con `setIncludeCharts`).
- **Cola/Job**: `ActualizarOeeAtadoresJob` en cola `oee-atadores`; la UI hace polling de estado (cache de archivo por token). `bootOeeQueueWorker` auto-lanza un worker `--once` cuando la cola no es `sync`.
- **Python (opcional)**: `OeeAtadoresPythonExportService` ejecuta `scripts/oee_export.py` como alternativa, pasando credenciales de BD desde `config/database`.

### Anotaciones / typos del proyecto
- Las rutas de catálogos usan el directorio de vistas correcto `modulos/catalogos-atadores/` (no comparten el typo histórico `catalagos`).
- El nombre de columna/campo Telegram `Atadores` en `SYSMensajes` se consulta por módulo (`Activo=1`).
- Métodos ruteados pero no implementados en `AtadoresController`: `cargarDatosUrdEngAtador`, `show`, `validarTejedor` (rutas existen en `atadores.php` pero no hay método correspondiente en el controlador leído).
