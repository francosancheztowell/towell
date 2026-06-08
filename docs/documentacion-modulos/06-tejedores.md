# Tejedores

> Generado automáticamente — documentación detallada del módulo

## 1. Propósito del módulo

El ámbito **Tejedores** agrupa todas las operaciones que ocurren en el piso de tejido textil relacionadas con los **operadores (tejedores)** y los **desarrolladores** de telar. Es un módulo transversal que conecta el programa de tejido (planeación) con la ejecución real en los telares: registra inventario de julios montados por telar, controla el checklist de Buenas Prácticas de Manufactura (BPM) en los cambios de turno, gestiona qué telares atiende cada operador, permite a los desarrolladores poner órdenes "en proceso" sobre los telares (incluyendo cambio de telar), y notifica eventos de producción (atado de julio, cortado de rollo / liberación de marbetes).

Submódulos que abarca:

| Submódulo | Carpeta de controllers | Función |
|-----------|------------------------|---------|
| **Inventario de Telares** | `Tejedores/InventarioTelaresController` | Registro de julios montados por telar/fecha/turno/tipo (Rizo/Pie), con sincronización a reservas de tela. |
| **BPM Tejedores** | `Tejedores/BPMTejedores/*` | Folios de checklist de actividades por cambio de turno con flujo Creado → Terminado → Autorizado. |
| **Configuración — Actividades BPM** | `Tejedores/TelActividadesBPMController` | Catálogo de actividades del checklist BPM. |
| **Configuración — Telares por Operador** | `Tejedores/Configuracion/TelaresOperador/*` | Asignación de telares a cada operador/supervisor. |
| **Configuración — Catálogo Desarrolladores** | `Tejedores/Configuracion/CatDesarrolladores/*` | Catálogo de desarrolladores. |
| **Desarrolladores** | `Tejedores/Desarrolladores/*` (+ 8 services) | Formulario que mueve una orden a "en proceso" en un telar, con cambio de telar opcional. |
| **Desarrolladores Muestras** | `Tejedores/Desarrolladores/TelDesarrolladoresMuestrasController` | Variante de Desarrolladores sobre el programa de Muestras. |
| **Notificar Atado de Julio** | `Tejedores/NotificarMontadoJulios/*` | Notificación (Telegram) de atado de julio por el tejedor. |
| **Notificar Cortado de Rollo** | `Tejedores/NotificarMontadoRollo/*` | Notificación de cortado de rollo + liberación de marbetes desde TI_PRO. |
| **Reportes** | `Tejedores/Reportes/*` | Reportes Excel de BPM Tejedores y de Desarrolladores. |

Rol dentro del flujo productivo: es el eslabón entre **Planeación (ReqProgramaTejido)** y la operación física. Lee/escribe `ReqProgramaTejido` para arrancar y mover órdenes, consume `AtaMontadoTelas` (Atadores) para los julios, sincroniza `InvTelasReservadas` (Inventario) y `UrdProgramaUrdido` (Urdido), y emite notificaciones a Telegram.

---

## 2. Rutas

Archivo: `routes/modules/tejedores.php`. Todas las rutas heredan el middleware `auth` (cargado por el dispatcher de `routes/web.php`). No hay verificaciones `userCan()` dentro de los controllers de este ámbito; el control de acceso es por menú/módulo. Los permisos indicados son los módulos lógicos asociados.

### 2.1 Navegación / submódulos

| Método | URI | Controller@método | Permiso lógico |
|--------|-----|-------------------|----------------|
| GET | `/tejedores/{moduloPrincipal?}` | `UsuarioController@showSubModulos` | acceso Tejedores |
| GET | `/tejedores/configurar` | `UsuarioController@showTejedoresConfiguracion` | acceso Configuración Tejedores |
| GET | `/tejedores/configurar/telaresxoperador` | `TelTelaresOperadorController@index` | TelaresPorOperador |
| GET | `/tejedores/configurar/catalogodesarrolladores` | `catDesarrolladoresController@index` | Catálogo Desarrolladores |
| GET | `/tejedores/configurar/actividadestejedores` | `TelActividadesBPMController@index` | Actividades BPM |
| GET | `/tejedores/bpmtejedores` (`tejedores.bpm`) | `TelBpmController@index` | BPM Tejedores |
| GET | `/tejedores/desarrolladores` (`tejedores.desarrolladores`) | `TelDesarrolladoresController@index` | Desarrolladores |
| GET | `/tejedores/desarrolladores-muestras` | `TelDesarrolladoresMuestrasController@index` | Desarrolladores Muestras |

### 2.2 Notificar Atado de Julio

| Método | URI | Controller@método |
|--------|-----|-------------------|
| GET | `/tejedores/atadodejulio` | `NotificarMontadoJulioController@index` |
| GET | `/tejedores/atadodejulio/telares` | `NotificarMontadoJulioController@telares`¹ |
| GET | `/tejedores/atadodejulio/detalle` | `NotificarMontadoJulioController@detalle`¹ |
| POST | `/tejedores/atadodejulio/notificar` | `NotificarMontadoJulioController@notificar` |

¹ Las rutas `telares` y `detalle` están declaradas pero el `NotificarMontadoJulioController` resuelve telares/detalle vía AJAX dentro de `index()` (parámetros `listado`, `no_telar`+`tipo`). Los métodos `telares`/`detalle` no existen en el controller de Julios (sí en el de Rollos); estas rutas son legacy.

### 2.3 Notificar Cortado de Rollo

| Método | URI | Controller@método |
|--------|-----|-------------------|
| GET | `/tejedores/cortadoderollo` | `NotificarMontRollosController@index` |
| GET | `/tejedores/cortadoderollo/telares` | `NotificarMontRollosController@telares` |
| GET | `/tejedores/cortadoderollo/detalle` | `NotificarMontRollosController@detalle` |
| POST | `/tejedores/cortadoderollo/notificar` | `NotificarMontRollosController@notificar` |
| GET | `/tejedores/cortadoderollo/telar/{telarId}/ordenes-en-proceso` | `NotificarMontRollosController@obtenerOrdenesEnProceso` |
| GET | `/tejedores/cortadoderollo/orden-produccion` | `NotificarMontRollosController@getOrdenProduccion` |
| GET | `/tejedores/cortadoderollo/datos-produccion` | `NotificarMontRollosController@getDatosProduccion` |
| POST | `/tejedores/cortadoderollo/insertar` | `NotificarMontRollosController@insertarMarbetes` |

Existen múltiples `Route::redirect(...301)` legacy hacia estas URIs (rutas antiguas `/notificarmontadodejulio*`, `/notificarcortadoderollo*`, `/notificar-montado-julios*`, `/notificar-mont-rollos*`).

### 2.4 Actividades BPM (resource) y Telares por Operador (resource)

| Método | URI | Controller@método |
|--------|-----|-------------------|
| GET | `/tel-bpm` | (redirect 301 → `/tejedores/bpmtejedores`) |
| resource | `/tel-actividades-bpm` (`{telActividadesBPM}`) | `TelActividadesBPMController` (index/create/store/show/edit/update/destroy) |
| GET | `/ActividadesBPM` | `TelActividadesBPMController@index` |
| resource | `/tel-telares-operador` (`{telTelaresOperador}`) | `TelTelaresOperadorController` |
| GET | `/tel-telares-operador/api/salones-y-telares` | `TelTelaresOperadorController@getSalonesYTelares` |
| GET | `/telaresPorOperador` | `TelTelaresOperadorController@index` |

### 2.5 BPM (resource + acciones de estado)

| Método | URI | Controller@método |
|--------|-----|-------------------|
| GET | `/tel-bpm/log-debug` | `TelBpmController@logDebug` |
| resource | `/tel-bpm` (`{folio}`) | `TelBpmController` (index/create/store/show/edit/update/destroy) |
| PATCH | `/tel-bpm/{folio}/terminar` | `TelBpmLineController@finish` |
| PATCH | `/tel-bpm/{folio}/autorizar` | `TelBpmLineController@authorizeDoc` |
| PATCH | `/tel-bpm/{folio}/rechazar` | `TelBpmLineController@reject` |
| GET | `/tel-bpm/{folio}/lineas` | `TelBpmLineController@index` |
| POST | `/tel-bpm/{folio}/lineas/toggle` | `TelBpmLineController@toggle` |
| POST | `/tel-bpm/{folio}/lineas/bulk-save` | `TelBpmLineController@bulkSave` |
| POST | `/tel-bpm/{folio}/lineas/comentarios` | `TelBpmLineController@updateComentarios` |

### 2.6 Inventario de Telares

| Método | URI | Controller@método |
|--------|-----|-------------------|
| GET | `/inventario-telares` | `InventarioTelaresController@index` |
| POST | `/inventario-telares/guardar` | `InventarioTelaresController@store` |
| GET | `/inventario-telares/verificar-estado` | `InventarioTelaresController@verificarEstado` |
| DELETE | `/inventario-telares/eliminar` | `InventarioTelaresController@destroy` |
| POST | `/inventario-telares/actualizar-fecha` | `InventarioTelaresController@updateFecha` |
| GET | `/inventario-telares/verificar-turnos-ocupados` | `InventarioTelaresController@verificarTurnosOcupados` |

### 2.7 Desarrolladores (endpoints AJAX + store)

| Método | URI | Controller@método |
|--------|-----|-------------------|
| GET | `/desarrolladores` | `TelDesarrolladoresController@index` |
| GET | `/desarrolladores/telar/{telarId}/producciones` | `@obtenerProducciones` |
| GET | `/desarrolladores/telar/{telarId}/producciones-html` | `@obtenerProduccionesHtml` |
| GET | `/desarrolladores/verificar-orden` | `@verificarOrden` |
| GET | `/desarrolladores/telar/{telarId}/orden-en-proceso` | `@obtenerOrdenEnProceso` |
| GET | `/desarrolladores/telar/{telarId}/julios` | `@obtenerJuliosPorTelar` |
| GET | `/desarrolladores/telar/{telarId}/produccion/{noProduccion}` | `@formularioDesarrollador` |
| GET | `/desarrolladores/orden/{noProduccion}/detalles` | `@obtenerDetallesOrden` |
| GET | `/desarrolladores/registro/{id}/detalles` | `@obtenerDetallesOrdenPorId` |
| GET | `/desarrolladores/modelo-codificado/{salonTejidoId}/{tamanoClave}` | `@obtenerCodigoDibujo` |
| GET | `/desarrolladores/catcodificados/{telarId}/{noProduccion}` | `@obtenerRegistroCatCodificado` |
| POST | `/desarrolladores` | `@store` |
| POST | `/desarrolladores/exportar-excel` | `@exportarExcel` |

### 2.8 Desarrolladores Muestras

| Método | URI | Controller@método |
|--------|-----|-------------------|
| GET | `/desarrolladores-muestras/telar/{telarId}/producciones` | `TelDesarrolladoresMuestrasController@obtenerProducciones` |
| GET | `/desarrolladores-muestras/orden/{noProduccion}/detalles` | `@obtenerDetallesOrden` |
| GET | `/desarrolladores-muestras/modelo-codificado/{salonTejidoId}/{tamanoClave}` | `@obtenerCodigoDibujo` |
| GET | `/desarrolladores-muestras/catcodificados/{telarId}/{noProduccion}` | `@obtenerRegistroCatCodificado` |
| POST | `/desarrolladores-muestras` | `@store` |

### 2.9 Catálogo Desarrolladores y Reportes

| Método | URI | Controller@método |
|--------|-----|-------------------|
| GET | `/catalogo-desarrolladores` | `catDesarrolladoresController@index` |
| POST | `/catalogo-desarrolladores` | `@store` |
| PUT | `/catalogo-desarrolladores/{cat_desarrolladore}` | `@update` |
| DELETE | `/catalogo-desarrolladores/{cat_desarrolladore}` | `@destroy` |
| GET | `/tejedores/reportes-desarrolladores` | `ReportesDesarrolladoresController@index` |
| GET | `/tejedores/reportes-desarrolladores/programa` | `@reportePrograma` |
| GET | `/tejedores/reportes-desarrolladores/programa/excel` | `@exportarExcel` |
| GET | `/tejedores/reportes-tejedores` | `ReportesTejedoresController@index` |
| GET | `/tejedores/reportes-tejedores/programa` | `@reportePrograma` |
| GET | `/tejedores/reportes-tejedores/programa/excel` | `@exportarExcel` |

---

## 3. Controllers

### 3.1 `InventarioTelaresController` (`app/Http/Controllers/Tejedores/InventarioTelaresController.php`)

Conexión por defecto `sqlsrv`. Tablas: `tej_inventario_telares` (modelo `TejInventarioTelares`), `InvTelasReservadas`, `UrdProgramaUrdido`. Todas las respuestas son JSON `{success, ...}`.

- **`index(): JsonResponse`** (L20) — Lista registros con `status='Activo'`, ordenados por fecha/no_telar/turno. Normaliza la columna `fecha` a `Y-m-d` (usando `getRawOriginal`) para evitar desfases de zona horaria en frontend. Sin parámetros.
- **`store(Request): JsonResponse`** (L74) — Crea o actualiza un registro. Valida `no_telar` (req, max:20), `tipo` (req, max:20), `cuenta` (req, max:20), `calibre` (nullable), `fecha` (req, date), `turno` (req, int 1-3), `salon` (req, max:50), `hilo` (nullable, max:50), `no_orden` (nullable, max:50). Clave única lógica = telar+tipo+fecha+turno+Activo: si existe actualiza, si no crea con `tipo_atado='Normal'`. **Fuerza `no_orden = null` siempre** (no se persiste el `no_orden` enviado). 422 en validación.
- **`verificarEstado(Request): JsonResponse`** (L156) — Determina si un registro/telar está reservado o programado antes de eliminar/editar. Acepta `no_telar`, `tipo`, y opcionalmente `fecha`+`turno` (body o query). Normaliza `tipo` (RIZO→Rizo, PIE→Pie). Si hay fecha+turno busca el registro exacto (con fallback `CONVERT(DATE, fecha)`); si no lo encuentra devuelve 404 con debug de registros similares. Reserva: prioridad 1 `InvTelasReservadas.TejInventarioTelaresId`, 2 `Fecha`+`Turno`, 3 `CONVERT(DATE, ProdDate)`. Regla crítica: si `Reservado=1` siempre está reservado; si `Reservado=0` sólo si hay reserva activa verificada. Si está programado consulta `UrdProgramaUrdido.Status` por `Folio` (= `no_orden`). Devuelve `{reservado, programado, registro_id, status_urdido, puede_eliminar:true}`.
- **`destroy(Request): JsonResponse`** (L448) — Elimina por telar+tipo+fecha+turno (todos requeridos, 422 si faltan). 404 si no existe. Antes de borrar elimina reservas asociadas en `InvTelasReservadas` con la misma prioridad de 3 niveles (Id → Fecha/Turno → ProdDate). Borra el registro y, si ya no quedan reservas activas del telar, pone `Reservado=false` en los demás registros activos del telar/tipo. Devuelve `reservas_eliminadas`.
- **`verificarTurnosOcupados(Request): JsonResponse`** (L630) — Para `no_telar`+`tipo`+`fecha` (req) devuelve `turnos_ocupados` y `turnos_disponibles` (de [1,2,3]). Permite excluir un `registro_id_excluir`.
- **`updateFecha(Request): JsonResponse`** (L703) — Mueve un registro a nueva fecha/turno. Requiere `no_telar`, `tipo`, `fecha_original`, `turno`, `fecha_nueva`; opcional `turno_nuevo`. Valida que el turno nuevo no esté ocupado (vía `verificarTurnosOcupadosInterno`, 400 si ocupado). Actualiza la fecha/turno del registro; si tiene `no_orden`, actualiza `UrdProgramaUrdido.FechaReq` **sólo si el programa tiene un único telar** en `NoTelarId` (no si es lista "211,214,210"). También sincroniza `ProdDate`/`Fecha`/`Turno` en `InvTelasReservadas`.
- **`verificarTurnosOcupadosInterno($noTelar,$tipo,$fecha,$registroIdExcluir=null): array`** (privado, L885) — Reutilizado por `updateFecha`. Devuelve el array de turnos ocupados.

No usa FolioHelper ni TurnoHelper.

### 3.2 `TelActividadesBPMController` (`app/Http/Controllers/Tejedores/TelActividadesBPMController.php`)

CRUD del catálogo de actividades del checklist BPM. Modelo `TelActividadesBPM` (PK `Orden`). Devuelve vistas/redirects.

- **`index(Request)`** (L16) — Lista actividades, búsqueda `q` por `Actividad` (like), orden por `Orden`. Vista `modulos.tel-actividades-bpm.index`.
- **`create()`** (L31) — Vista `modulos.tel-actividades-bpm.create`.
- **`store(Request)`** (L39) — Valida `Actividad` (req, max:100). Crea y redirige.
- **`edit(TelActividadesBPM)`** (L55) — Vista `edit` (binding por `Orden`).
- **`update(Request, TelActividadesBPM)`** (L66) — Valida `Actividad`; actualiza y redirige.
- **`destroy(TelActividadesBPM)`** (L82) — Elimina y redirige.

### 3.3 `TelBpmController` (`app/Http/Controllers/Tejedores/BPMTejedores/TelBpmController.php`)

Encabezado de los folios BPM. Modelos `TelBpmModel` (`TelBPM`), `TelTelaresOperador`, `SYSUsuario`, `TelActividadesBPM`, `SSYSFoliosSecuencia`. Usa `TurnoHelper::getTurnoActual()`. Estados: `Creado`, `Terminado`, `Autorizado`. Folio prefijo `BT`, clave `BPMTEjido` en `dbo.SSYSFoliosSecuencias`.

- **`index(Request)`** (L29) — Listado con búsqueda `q` (Folio/Cve/Nombre de recibe y entrega) y filtro `status`; orden por Fecha/Folio desc; `simplePaginate` (50–1000, def 300). Prefills para el modal de creación: turno y fecha actuales (`America/Mexico_City`), datos del operador autenticado y lista de operadores de entrega (vía `obtenerDatosOperador`), y `esSupervisor`. Vista `modulos.bpm-tejedores.tel-bpm.index`.
- **`show(string $folio)`** (L104) — Redirige a `tel-bpm-line.index` (checklist).
- **`logDebug(Request)`** (L110) — Devuelve `204` vacío (endpoint de no-op para el cliente).
- **`store(Request)`** (L116) — Crea el encabezado. Valida recibe (nullable: `CveEmplRec`, `NombreEmplRec`, `TurnoRecibe`) y entrega (req: `CveEmplEnt`, `NombreEmplEnt`, `TurnoEntrega`). Rellena recibe con datos del usuario autenticado; resuelve turnos vía `resolverTurnoEmpleado`. Valida que entrega ≠ recibe. Genera folio (`generarFolio`), crea el registro en estado `Creado` e inicializa las líneas del checklist (`inicializarLineasChecklist`). Redirige a `tel-bpm-line.index`.
- **`update(Request, string $folio)`** (L195) — Sólo en estado `Creado`. Valida entrega (req). Actualiza encabezado.
- **`destroy(string $folio)`** (L214) — Sólo `Creado`. Elimina (cascade a `TelBPMLine`). Redirige a `tejedores.bpm`.

Privados relevantes: **`generarFolio()`** (L229) — Dentro de transacción, `lockForUpdate` sobre `dbo.SSYSFoliosSecuencias` (modulo `BPMTEjido`); si no existe la secuencia la crea sembrando con el máximo `BT%` de `TelBPM`; reconcilia consecutivo con el máximo real; llama `SSYSFoliosSecuencia::nextFolio()` (fallback `nextFolioByPrefijo`) con padding 5; guard anti-duplicado de 5 intentos. **`obtenerDatosOperador($user)`** (L278) — Busca al operador en `TelTelaresOperador` por código/nombre, completa turno y arma la lista de operadores de entrega de los mismos telares (excluyendo supervisores), enriquecida con `SYSUsuario`. **`inicializarLineasChecklist($folio,$cveEmplRec,$turnoRecibe)`** (L360) — Inserta en `TelBPMLine` una fila por cada (Actividad × Telar asignado al recibe) que no exista; en chunks de 200. **`operadorQuery`/`completarTurnoOperador`/`resolverTurnoEmpleado`** (L419/439/454) — Resolución robusta del turno del empleado consultando `SYSUsuario` y `TelTelaresOperador` (exacto, numérico TRY_CONVERT). **`esSupervisor($user)`** (L566) — `true` si área/puesto = "Supervisor" o `TelTelaresOperador.Supervisor=1`. **`logTurnoDebug`** (L557) — log warning de depuración de turnos.

### 3.4 `TelBpmLineController` (`app/Http/Controllers/Tejedores/BPMTejedores/TelBpmLineController.php`)

Edición del checklist (grid de actividades × telares) y transiciones de estado. Modelos `TelBpmModel`, `TelBpmLineModel`, `TelActividadesBPM`, `TelTelaresOperador`, `SYSUsuario`.

- **`index(string $folio)`** (L21) — Carga encabezado con `lines`, catálogo de actividades, líneas existentes; resuelve los telares visibles desde `TelTelaresOperador` del operador que **recibe** (fallback a los presentes en líneas). Si faltan celdas (`actividades × telares`), las inserta on-demand en `TelBPMLine` (chunks de 50). Detecta si el usuario es supervisor (puesto en `SYSUsuario`). Comentarios viven en `TelBPM.Comentarios`. Vista `modulos.bpm-tejedores.tel-bpm-line.index`.
- **`toggle(Request, string $folio)`** (L120) — Sólo en estado `Creado` (422 si no). Valida `Orden` (req int), `NoTelarId` (req), `SalonTejidoId`/`TurnoRecibe`/`Actividad` (nullable). Lee el `Valor` actual de la celda y aplica el ciclo `nextValor` (NULL→OK→X→M→NULL); `updateOrInsert` en `TelBPMLine`. Devuelve `{ok, valor}`.
- **`bulkSave(Request, string $folio)`** (L168) — Sólo `Creado`. Valida `rows[]` con `Orden`, `NoTelarId`, opcionales `Actividad`/`Salon`/`Turno`/`Valor`. Normaliza valores (`1`→OK, `-1`→X, `M`/Mantenimiento→M, otros→null); `updateOrInsert` por fila dentro de transacción.
- **`updateComentarios(Request, string $folio)`** (L222) — Sólo `Creado`. Valida `Comentarios` (nullable, max:150). Guarda en `TelBPM.Comentarios` (trim → null si vacío).
- **`finish(string $folio)`** (L258) — `Creado` → `Terminado`. Redirige a `tejedores.bpm`.
- **`authorizeDoc(string $folio)`** (L272) — `Terminado` → `Autorizado`. Exige supervisor (`getSupervisorInfo`), guarda `CveEmplAutoriza`/`NomEmplAutoriza`.
- **`reject(string $folio)`** (L297) — `Terminado` → `Creado` (limpia autorizador). Exige supervisor.

Privados: **`nextValor($curr)`** (L323) — ciclo de estados de celda. **`getSupervisorInfo($accion)`** (L332) — verifica que el usuario autenticado tenga puesto "supervisor" en `SYSUsuario`; lanza `RuntimeException` si no; devuelve `[code, name]`.

### 3.5 `TelTelaresOperadorController` (`app/Http/Controllers/Tejedores/Configuracion/TelaresOperador/TelTelaresOperadorController.php`)

CRUD de asignación telar↔operador. Modelos `TelTelaresOperador` (`sqlsrv`), `SYSUsuario`, `ReqProgramaTejido`, `ReqTelares`. Soporta respuestas JSON (AJAX) y redirects.

- **`getSalonesYTelares()`** (L20) — JSON con `salones` (distintos `SalonTejidoId` de `ReqProgramaTejido`) y `telares` (pares salón/telar distintos). Fuente: `ReqProgramaTejido`.
- **`index(Request)`** (L55) — Listado con búsqueda `q` (numero_empleado/nombreEmpl/NoTelarId). Carga telares (de `ReqProgramaTejido`, fallback `ReqTelares::obtenerTodos()`) y usuarios (`SYSUsuario`). Orden numérico de empleado. Vista `modulos.tel-telares-operador.index`.
- **`create()`** (L101) — Vista `create` con telares y usuarios.
- **`store(Request)`** (L115) — Asigna **múltiples telares** a un operador. Valida `numero_empleado` (req, exists `SYSUsuario`), `nombreEmpl`, `Turno`, `SalonTejidoId`, `telares` (array req), `telares.*`, `Supervisor` (bool nullable). Crea un registro por telar dentro de transacción evitando duplicados (mismo empleado+telar). Devuelve creados/duplicados (JSON) o redirect.
- **`edit(TelTelaresOperador)`** (L218) — Vista `edit` (binding por `Id`).
- **`update(Request, TelTelaresOperador)`** (L235) — Sincroniza el conjunto de telares del operador: valida que los telares existan en `ReqTelares`; borra los no seleccionados y `updateOrCreate` los seleccionados; usa nombre/turno de `SYSUsuario` y salón de `ReqTelares`. JSON o redirect.
- **`destroy(Request, TelTelaresOperador)`** (L328) — Elimina **todos** los registros del `numero_empleado` del operador. JSON o redirect.

### 3.6 `catDesarrolladoresController` (`app/Http/Controllers/Tejedores/Configuracion/CatDesarrolladores/catDesarrolladoresController.php`)

CRUD del catálogo de desarrolladores. Modelo `catDesarrolladoresModel` (`cat_desarrolladores`), `Usuario`.

- **`index(Request)`** (L12) — Lista catálogo; carga usuarios del área "Desarrolladores" (`Usuario::porArea`) que aún no están en el catálogo. Vista `modulos.desarrolladores.catalogo-desarrolladores`.
- **`store(Request)`** (L23) — Valida `clave_empleado` (req, max:50). Busca el usuario del área Desarrolladores (`firstOrFail`) y crea el registro con su nombre/turno. Redirige.
- **`update(Request, $id)`** (L40) — Valida `clave_empleado`, `nombre`, `Turno`. Actualiza por id.
- **`destroy($id)`** (L54) — Elimina por id.

### 3.7 `TelDesarrolladoresController` (`app/Http/Controllers/Tejedores/Desarrolladores/TelDesarrolladoresController.php`)

Orquestador del módulo Desarrolladores. Inyecta `ConsultasDesarrolladorService` y `ProcesarDesarrolladorService`. Modelo `ReqProgramaTejido`.

- **`__construct(...)`** (L16) — Inyección de servicios.
- **`index()`** (L27) — Datos vía `ConsultasDesarrolladorService::obtenerDatosIndex()`. Vista `modulos.desarrolladores.desarrolladores`.
- **`exportarExcel(Request)`** (L36) — Requiere `fecha`. Descarga `DesarrolladoresExport($fecha)` (`.xlsx`).
- **`formularioDesarrollador(Request, $telarId, $noProduccion)`** (L56) — Vista `partials.form` con datos de `ReqProgramaTejido` (por telar+producción).
- **`obtenerProduccionesHtml(Request, $telarId)`** (L68) — Renderiza el partial `filas-producciones` con las producciones del telar y telares destino. Devuelve HTML.
- **`obtenerProducciones(Request, $telarId)`** (L92) — JSON con producciones pendientes (delegado al service).
- **`verificarOrden(Request)`** (L102) — JSON `{exists}` si `NoProduccion` existe en `ReqProgramaTejido`.
- **`obtenerOrdenEnProceso($telarId)`** (L115) — JSON con la orden `EnProceso=1` del telar (NoProduccion, NombreProducto, FechaInicio formateada).
- **`obtenerJuliosPorTelar($telarId)`** (L135) — JSON julios Rizo/Pie del telar (delegado).
- **`obtenerDetallesOrden($noProduccion)`** (L145) — JSON detalles de trama/combinaciones (delegado).
- **`obtenerDetallesOrdenPorId($id)`** (L155) — Igual pero buscando por Id (fila sin orden).
- **`obtenerCodigoDibujo($salonTejidoId, $tamanoClave)`** (L165) — JSON con `CodigoDibujo` (404 si no hay).
- **`obtenerRegistroCatCodificado($telarId, $noProduccion)`** (L175) — JSON con valores previos de `CatCodificados` para precargar (404 si no hay).
- **`store(Request)`** (L186) — Delega todo a `ProcesarDesarrolladorService::store($request)`.

### 3.8 `TelDesarrolladoresMuestrasController` (`app/Http/Controllers/Tejedores/Desarrolladores/TelDesarrolladoresMuestrasController.php`)

Variante de Desarrolladores sobre el programa de **Muestras**. Inyecta `ConsultasMuestrasDesarrolladorService` y `ProcesarMuestrasDesarrolladorService`. Métodos análogos a 3.7 pero contra `Muestras` (MuestrasPrograma):

- **`__construct`** (L15), **`index()`** (L23, vista `desarrolladores-muestras`), **`obtenerProducciones(Request,$telarId)`** (L29), **`obtenerDetallesOrden($noProduccion)`** (L36), **`obtenerCodigoDibujo($salonTejidoId,$tamanoClave)`** (L43), **`obtenerRegistroCatCodificado($telarId,$noProduccion)`** (L50), **`store(Request)`** (L57, delega a `ProcesarMuestrasDesarrolladorService`).

### 3.9 `NotificarMontadoJulioController` (`app/Http/Controllers/Tejedores/NotificarMontadoJulios/NotificarMontadoJulioController.php`)

Notificación de atado de julio por el tejedor. Modelos `TelTelaresOperador`, `TejNotificaTejedorModel` (`TejNotificaTejedor`), `TejInventarioTelares`, `ReqProgramaTejido`, `SYSMensaje`. Telegram vía `Http`.

- **`index(Request)`** (L19) — Sólo telares asignados al usuario (`TelTelaresOperador`). En AJAX: con `listado` devuelve `{telares}`; con `no_telar`+`tipo` busca (1) registro completo en `tej_inventario_telares` con `no_julio` y `no_orden`, (2) registro parcial, (3) sin registro pero telar asignado — completando cuenta/calibre desde `ReqProgramaTejido` (`getCuentaCalibreDePrograma`) y marcando `registroCompleto`. Sin AJAX: vista `modulos.notificar-montado-julios.index`.
- **`notificar(Request)`** (L123) — Registra la notificación. Si el registro es completo (tiene no_julio+no_orden): fija `horaParo`, guarda en `TejNotificaTejedor` (Reserva=1) evitando duplicados y envía Telegram. Si es incompleto/sin registro: actualiza `horaParo` si existe registro parcial, registra (Reserva=0) y notifica con datos disponibles. Devuelve `{success, horaParo}`.

Privados: **`registrarNotificacionTejedor(array $payload, bool $esCompleto)`** (L227) — Inserta o actualiza en `TejNotificaTejedor` el registro más reciente de hoy para ese telar/tipo, protegiendo julio/orden ya buenos. **`getCuentaCalibreDePrograma($noTelar,$tipo)`** (L284) — Devuelve `[cuenta, calibre]` desde `ReqProgramaTejido` (EnProceso=1) según rizo/pie. **`enviarNotificacionTelegram($registro,$usuario)`** (L320) — Envía a los chat IDs de `SYSMensaje::getChatIdsPorModulo('NotificarAtadoJulio')` un mensaje con telar/tipo/orden/julio/metros/horaParo/operador.

### 3.10 `NotificarMontRollosController` (`app/Http/Controllers/Tejedores/NotificarMontadoRollo/NotificarMontRollosController.php`)

Notificación de cortado de rollo y liberación de marbetes. Usa **conexión `sqlsrv_ti`** (TI_PRO) para `TWMarbetes`, `ProdTable`, `InventDim`. Modelos `TelTelaresOperador`, `TejInventarioTelares`, `TelMarbeteLiberadoModel` (`TelMarbeteLiberado`), `ReqProgramaTejido`.

- **`index(Request)`** (L18) — Telares del operador. AJAX `listado`: telares + detalle (filtro `tipo` rizo/pie). AJAX `no_telar`+`tipo`: detalle (incluye `no_rollo`). Sin AJAX: vista `modulos.tejedores.notificar-mont-rollos.index`.
- **`telares(Request)`** (L91) — JSON con telares (no_telar/tipo) del operador.
- **`detalle(Request)`** (L113) — JSON detalle de un `no_telar` (400/404 si falta/no encontrado).
- **`notificar(Request)`** (L138) — Fija `horaParo` del registro por `id` (404 si no existe). Devuelve `{success, horaParo}`.
- **`obtenerOrdenesEnProceso($telarId)`** (L166) — Valida que el telar sea del operador (403 si no). Devuelve **todas** las órdenes con `NoProduccion` del telar (`ReqProgramaTejido`), priorizando las `EnProceso`.
- **`getOrdenProduccion(Request)`** (L219) — Para `no_telar` devuelve la orden `EnProceso=1` (`ReqProgramaTejido`); incluye prueba de conexión a `sqlsrv_ti`. 404 si no hay.
- **`getDatosProduccion(Request)`** (L270) — Núcleo de liberación: con `no_produccion`+`no_telar` busca `ProdId` local; consulta primero `TWMarbetes` (`sqlsrv_ti`, `StatusMarebete=0`) y como fallback `ProdTable`⋈`InventDim` (`impreso='SI'`, `ProdStatus=0`, `DATAAREAID='PRO'`). Excluye los `PurchBarCode` ya en `TelMarbeteLiberado`. Devuelve marbetes formateados, total y fuente.
- **`insertarMarbetes(Request)`** (L398) — Inserta los marbetes seleccionados en `TelMarbeteLiberado` evitando duplicados (por `PurchBarCode`). Devuelve insertados/yaExistían/errores.

### 3.11 `ReportesTejedoresController` (`app/Http/Controllers/Tejedores/Reportes/ReportesTejedoresController.php`)

- **`index()`** (L16) — Vista `modulos.tejedores.reportes.index` con el catálogo de reportes (Reporte BPM Tejedores).
- **`reportePrograma(Request)`** (L33) — Vista `modulos.tejedores.reportes.programa`; si hay `fecha_ini`+`fecha_fin` las normaliza a `Y-m-d`.
- **`exportarExcel(Request)`** (L58) — Valida rango de fechas (inicio ≤ fin). Descarga `TejedoresReporteExport($ini,$fin)` con nombre `tejedores_bpm_dd-mm-YYYY_a_dd-mm-YYYY.xlsx`.

### 3.12 `ReportesDesarrolladoresController` (`app/Http/Controllers/Tejedores/Reportes/ReportesDesarrolladoresController.php`)

Idéntico patrón a 3.11 pero para Desarrolladores: **`index()`** (L16, vista `modulos.desarrolladores.reportes.index`), **`reportePrograma(Request)`** (L33, vista `...reportes.programa`), **`exportarExcel(Request)`** (L58, descarga `DesarrolladoresReporteExport`, nombre `desarrolladores_dd-mm-YYYY_a_dd-mm-YYYY.xlsx`).

---

## 4. Services y Helpers del ámbito

Carpeta `app/Http/Controllers/Tejedores/Desarrolladores/Funciones/` (8 services) + `app/Helpers/TelDesarrolladoresHelper.php`.

### 4.1 `ConsultasDesarrolladorService`

Lecturas para la vista Desarrolladores. Modelos: `TelTelaresOperador`, `Usuario`, `AtaMontadoTelasModel`, `ReqProgramaTejido`, `ReqModelosCodificados`, `CatCodificados` (vía `CatCodificadosDesarrolladorService`). Usa `TelDesarrolladoresHelper::mapDetalleFila`.

- **`obtenerDatosIndex(): array`** (L29) — telares, telaresDestino, juliosRizo, juliosPie, desarrolladores, desarrolladorActual.
- **`obtenerTelaresDestino(): Collection`** (L56) — pares `salon|telar` desde `ReqProgramaTejido`.
- **`obtenerJuliosPorTelar(string $telarId): array`** (L105) — julios Rizo/Pie del telar desde `AtaMontadoTelas`.
- **`obtenerProducciones(string $telarId): array`** (L148) — producciones `EnProceso=0` con `NoProduccion` del telar.
- **`obtenerDetallesOrdenPorId(int $id)`** (L176) / **`obtenerDetallesOrden($noProduccion)`** (L196) — detalles trama+5 combinaciones (filtra filas "cero-ish").
- **`obtenerCodigoDibujo($salonTejidoId,$tamanoClave): array`** (L216) — `CodigoDibujo` de `ReqModelosCodificados`.
- **`obtenerRegistroCatCodificado($telarId,$noProduccion): array`** (L252) — valores previos (`JulioRizo`, `JulioPie`, `EfiInicial`, `EfiFinal`, `DesperdicioTrama`).
- Privados: `obtenerTelares` (L44), `obtenerJuliosPorTipo` (L82), `obtenerDesarrolladores` (L124), `buildDetallesFromOrdenData` (L283).

### 4.2 `ConsultasMuestrasDesarrolladorService` (extiende 4.1)

Override sobre el programa de **Muestras** (`Muestras`/MuestrasPrograma). **`obtenerTelaresDestinoMuestras()`** (privado, L15), **`obtenerDatosIndex()`** (L41, reemplaza telaresDestino), **`obtenerProducciones($telarId, bool $soloConOrden=false)`** (L54, sólo con `NoProduccion`), **`obtenerDetallesOrden($noProduccion)`** (L82, detalles desde Muestras).

### 4.3 `CatCodificadosDesarrolladorService`

Resolución de la tabla `CatCodificados` con esquema variable. **`getColumns()`** (L11, columnas reales vía `Schema`), **`buildOrderQuery($noProduccion,$columns?)`** (L18, filtra por `OrdenTejido`/`NumOrden`/`NoProduccion` según exista), **`resolveForRead($noProduccion,$telarId?)`** (L35, mejor match por telar), **`resolveCodigoDibujo(...)`** (L58), **`resolveCanonical($noProduccion)`** (L70).

### 4.4 `ProcesarDesarrolladorService`

Núcleo transaccional del POST de Desarrolladores. Inyecta `MovimientoDesarrolladorService`, `NotificacionTelegramDesarrolladorService`, `CatCodificadosDesarrolladorService`.

- **`store(Request)`** (L38) — Flujo completo (ver §8): valida/normaliza, calcula minutos de cambio y fecha programada, abre transacción, resuelve contexto origen/destino, normaliza código de dibujo, actualiza `CatCodificados`/`ReqModelosCodificados`/programas relacionados, ejecuta el movimiento "en proceso" (con o sin cambio de telar), registra auditoría (`OrdenFinalizadaAuditoria::registrarDesarrolladorFinalizar` si acción=finalizar) y notifica por Telegram. Devuelve JSON (AJAX) o redirect.
- Privados clave: **`validarYNormalizarEntrada`** (L233, ver validaciones en §8), `normalizarEntero` (L278), `resolverContextoOrigen` (L295, busca/asigna orden a fila sin `NoProduccion` por `registroId`), `resolverContextoDestino` (L333, parsea `salon|telar` de `TelarDestino`), `actualizarCatCodificados` (L398), `buildFechasArranqueFinalizaPayload`/`combinarFechaYHora` (L534/561), `resolverModeloDestinoYCopiaSiAplica` (L580), `actualizarProgramaAntesDeMovimiento` (L644), `actualizarModeloDestinoSiCorresponde` (L685), `actualizarProgramasRelacionados` (L737), **`ejecutarMovimientoYPonerEnProceso`** (L825, mapea acción → Reprogramar `1`/`2` y delega en `MovimientoDesarrolladorService`), `enviarNotificacion` (L865), `construirFechaInicioProgramada` (L889), `normalizarLongitudLucha` (L905), `buildPasadasPayload` (L912), `normalizeCodigoDibujo`/`resolverSufijoCodigoPorTelar` (L943/960), `buildDetallePayloadFromOrden` (L969), `calcularMinutosCambio` (L1001).

### 4.5 `ProcesarMuestrasDesarrolladorService`

Equivalente a 4.4 para Muestras. **`store(Request)`** (L33) procesa la muestra y la **elimina** del programa (`eliminarRegistroMuestra`, L202). Mismos helpers privados de normalización/payload (L226–L796) y notifica vía `NotificacionTelegramMuestrasService`.

### 4.6 `MovimientoDesarrolladorService`

Mueve órdenes a `EnProceso=1` y reordena la cola del telar. Modelos `ReqProgramaTejido`, `CatCodificados`; helpers de Planeación (`DateHelpers`, `VincularTejido`).

- **`moverRegistroEnProceso(ReqProgramaTejido $registro, bool $throwOnError=false)`** (L26) — Pone el registro en proceso; los registros previos en proceso con `Reprogramar` 1/2 se reubican (actualiza fechas y `ReqModelos`), recalcula la secuencia del telar y dispara observers.
- **`moverRegistroConCambioTelarEnProceso(ReqProgramaTejido, string $salonDestino, string $telarDestino, ?string $reprogramarValor=null)`** (L216) — Mueve el registro a otro salón/telar; si origen=destino delega en el método anterior.
- **`actualizarFechasArranqueFinaliza(ReqProgramaTejido, $fechaArranque=null, $fechaFinaliza=null, bool $actualizarFechaFinaliza=true, bool $preservarFechaArranqueCat=false)`** (L509).
- **`actualizarReqModelosDesdePrograma(ReqProgramaTejido $programa)`** (L595).
- Privados: `encolarEnDestino` (L311), `recalcularSecuenciaTelar` (L384), `dispararObserverPorIds` (L431), `moverRegistroConReprogramar` (L443).

### 4.7 `NotificacionTelegramDesarrolladorService`

**`enviarProcesoCompletado(array $validated, ReqProgramaTejido $programa, string $codigoDibujo)`** (L17) — Envía a `SYSMensaje::getChatIdsPorModulo('Desarrolladores')` un mensaje Markdown con telar, producción, cambio de telar/código si aplica, julios, horas, eficiencias y fechas. **`construirMensajeProcesoCompletado`** (privado, L47).

### 4.8 `NotificacionTelegramMuestrasService`

Igual a 4.7 pero módulo `DesarrolladoresPrue`. **`enviarProcesoCompletado`** (L13), **`construirMensajeProcesoCompletado`** (L43). Mensaje indica "Muestra procesada y eliminada del programa".

### 4.9 Helper `TelDesarrolladoresHelper` (`app/Helpers/TelDesarrolladoresHelper.php`)

- **`mapDetalleFila($ordenData, $calibreKey, $hiloKey, $fibraKey, $colorKey, $nombreColorKey, $pasadasKey): array`** (estático, L19) — Mapea campos de una orden a `{Calibre, Hilo, Fibra, CodColor, NombreColor, Pasadas, pasadasField}`. Para claves `NombreCC*` nulas usa el alterno `NomColorC{i}`.

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|--------|------------------|----------|----|--------------|
| `TelBpmModel` | `TelBPM` | default (`sqlsrv`) | `Folio` (string, no incrementing) | Fecha, CveEmplRec/NombreEmplRec/TurnoRecibe, CveEmplEnt/NombreEmplEnt/TurnoEntrega, CveEmplAutoriza/NomEmplAutoriza, Status, Comentarios. Relación `lines()`. |
| `TelBpmLineModel` | `TelBPMLine` | default | `Id` (int) | Folio, TurnoRecibe, NoTelarId, SalonTejidoId, Orden, Actividad, Valor. Relación `header()`, scope `byFolio`. |
| `TelActividadesBPM` | `TelActividadesBPM` | default | `Orden` (IDENTITY) | Actividad. |
| `TelTelaresOperador` | `TelTelaresOperador` | `sqlsrv` (explícita) | `Id` (IDENTITY) | numero_empleado, nombreEmpl, NoTelarId, Turno, SalonTejidoId, Supervisor (bool). |
| `catDesarrolladoresModel` | `cat_desarrolladores` | default | `id` (default) | clave_empleado, nombre, Turno. |
| `TelDesarrolladoresModel` | `TelDesarrolladores` | default | `id` (default) | (modelo mínimo, sin fillable). |
| `TelMarbeteLiberadoModel` | `TelMarbeteLiberado` | default | `Id` (int) | PurchBarCode, ItemId, InventSizeId, InventBatchId, WMSLocationId, QtySched, Salon, CUANTAS. Scopes `byBarcode`/`byTelar`/`byItem`. |
| `TejNotificaTejedorModel` | `TejNotificaTejedor` | default | `id` (default) | telar, tipo, hora, NomEmpleado, NoEmpleado, Reserva (bool), no_julio, no_orden, Fecha. |
| `NotificarMontRollosModel` | (sin tabla definida) | — | — | Modelo placeholder vacío. |

Tablas externas tocadas por los controllers (no son modelos del ámbito): `tej_inventario_telares` (`TejInventarioTelares`, Tejido), `InvTelasReservadas` (Inventario), `UrdProgramaUrdido` (Urdido), `ReqProgramaTejido`/`ReqModelosCodificados`/`Muestras`/`CatCodificados`/`ReqTelares`/`OrdenFinalizadaAuditoria` (Planeación), `AtaMontadoTelas` (Atadores), `SYSUsuario`/`SYSMensajes`/`SSYSFoliosSecuencias`/`Usuario` (Sistema), y en `sqlsrv_ti` (TI_PRO): `TWMarbetes`, `ProdTable`, `InventDim`.

---

## 6. Vistas Blade

### 6.1 `modulos/tel-actividades-bpm/index.blade.php`
Catálogo de actividades BPM con tabla y modales crear/editar/eliminar. Funciones JS: `updateTopButtonsState()` habilita botones por selección; `clearSelection()`/`selectRow(row)` gestión de fila activa; `handleTopEdit()`/`handleTopDelete()` abren modales; `openTelModal(modalId)`/`closeTelModal(modalId)`; `openEditModal(key, actividad)` precarga; `deleteActivity(key)` confirma y envía el form de borrado. Endpoints: rutas resource `tel-actividades-bpm.*`.

### 6.2 `modulos/tel-telares-operador/index.blade.php`
Gestión de telares por operador con tabla, filtros y modales crear/editar. Funciones JS principales: `updateTopButtonsState()`, `clearSelection()`, `selectRow(row)`, `handleTopEdit()`, `handleTopDelete()` (async, borra operador); `openModal/closeModal`; `openEditModal(...)`/`openEditModalFromBtn(btn)`; `deleteOperator(key)`; `openFiltersModal/closeFiltersModal/applyFilters()`; `cargarCatalogos()` (async, consume `/tel-telares-operador/api/salones-y-telares`); `populateSalonesSelect()`, `getTelaresAsignadosPorEmpleado()`, `renderEditTelares()/renderEditTelaresInternal()`, `cargarTelaresPorSalon(salon)`, `actualizarEstadoGuardar()`, `wireEmpleado()`, `createTableRow(item)`, `escapeHtml()`, `updateEmptyMessage()`, `updateTableRow()`. Los `submit` de `createForm`/`editForm` envían a `tel-telares-operador.store`/`.update` (AJAX JSON).

### 6.3 `modulos/bpm-tejedores/tel-bpm/index.blade.php`
Listado de folios BPM con filtros y modal de creación. Funciones JS: `logStep(step,msg)` (debug → `/tel-bpm/log-debug`); `applyFilters()` filtra el listado en cliente; `getEmptyMessage()`; `updateFilterButtons()`/`toggleBtn(...)`; `setDisabled(btn,val)`; `updateActions()` habilita acciones por selección; `clearSelection()`. El form de creación envía a `tel-bpm.store`.

### 6.4 `modulos/bpm-tejedores/tel-bpm-line/index.blade.php`
Grid del checklist (actividades × telares) con celdas tri-estado y comentarios. Funciones JS: `nextValor(curr)` ciclo NULL→OK→X→M→NULL; `toast(icon,title)`; `postJson(url,payload)` (async, helper fetch CSRF); `setCellState(btn,next)` pinta la celda; `confirmAndSubmit(btnId,formId,title)` confirma terminar/autorizar/rechazar; `setComentariosStatus(text,isError)`; `saveComentarios()` (async → `tel-bpm-line.comentarios`). Toggle de celdas → `tel-bpm-line.toggle`; guardado masivo → `tel-bpm-line.bulk`; transiciones → `tel-bpm.finish/authorize/reject`.

### 6.5 `modulos/desarrolladores/desarrolladores.blade.php`
Vista principal de Desarrolladores: incluye los partials `select-telar`, `tabla-producciones`, `form-desarrollador`, `modales`. Inyecta `window.__TELARES_LISTA__`. La lógica JS está en `partials/scripts.php` (ver §7). Partials: `select-telar`, `tabla-producciones`, `filas-producciones` (renderizado server-side de filas por telar), `form-desarrollador`, `modales`.

### 6.6 `modulos/desarrolladores/desarrolladores-muestras.blade.php`
Variante para Muestras; usa `partials/scripts-muestras.blade.php` (ver §7).

### 6.7 `modulos/desarrolladores/catalogo-desarrolladores.blade.php`
Catálogo de desarrolladores con tabla y modales. Funciones JS: `updateTopButtonsState()`, `clearSelection()`, `selectRow(row)`, `handleTopEdit()`, `handleTopDelete()`, `openModal/closeModal(modalId)`, `openEditModal(key,clave,nombre,turno)`. Endpoints: `cat-desarrolladores.store/update/destroy`.

### 6.8 `modulos/tejedores/configuracion/catalogo-desarrolladores.blade.php`
Vista alternativa del catálogo de desarrolladores (configuración). Misma finalidad de CRUD que 6.7.

### 6.9 `modulos/notificar-montado-julios/index.blade.php`
Modal de notificación de atado de julio para el tejedor (filtro Rizo/Pie). Funciones JS: `cerrarModal()` oculta el modal; `aplicarFiltro(tipo)` recarga con query `tipo`. (Buena parte del bloque está en comentario Blade; la lógica AJAX activa de telares/detalle/notificar se sirve desde el controller.)

### 6.10 `modulos/tejedores/notificar-mont-rollos/index.blade.php`
Cortado de rollo y liberación de marbetes. Funciones JS: `habilitarBtnNotificar()/deshabilitarBtnNotificar()`; `mostrarMensajeCortado(mensaje,tipo)/ocultarMensajeCortado()`; `cargarOrdenesEnProceso(telarId)` (consume `cortadoderollo/telar/{id}/ordenes-en-proceso`); `cargarMarbetesCortado(noProduccion,noTelar,salon)` (consume `cortadoderollo/datos-produccion`); `renderizarTablaCortado(datos)` con `formatearPiezas(valor)` interno; el click del botón notificar (async) envía a `cortadoderollo/insertar`.

### 6.11 Reportes
- `modulos/tejedores/reportes/index.blade.php` y `programa.blade.php` — selector y vista del reporte BPM Tejedores (modal de rango de fechas, exporta a `tejedores.reportes-tejedores.programa.excel`).
- `modulos/desarrolladores/reportes/index.blade.php` y `programa.blade.php` — `mostrarModalConsultarReportesDesarrolladores()` abre el modal de fechas; exporta a `tejedores.reportes-desarrolladores.programa.excel`.
- `modulos/tejedores/Reportes-bpm/index.blade.php` — vista legacy (redirigida a reportes-tejedores).

---

## 7. JS dedicado (partials de Desarrolladores)

`resources/views/modulos/desarrolladores/partials/scripts.php` — Lógica del formulario de Desarrolladores. Funciones (selección representativa, todas inline/IIFE):
- `showSelectionRequiredMessage()`, `setActionSelectLocked(isLocked)`, `setupTelarDestinoListeners()` — UI de selección de telar/acción y cambio de telar.
- `spinnerHtml/emptyRowHtml(colspan,mensaje)`, `parseDestinoValue(value)` (`salon|telar`), `formatHiloValue/normalizeHiloInputValue/attachHiloInputListener`, `normalizeIntegerValue`, `escapeHtml`, `flattenValidationMessages/showValidationAlert` — utilidades de render y validación.
- `validarAntesDeEnviar()` — valida el formulario antes del POST.
- `setSelectValue`, `resetJulioSelect/populateJulioSelect`, `checkFormValidity()` — selects de julio Rizo/Pie.
- `fetchJson(url,params)` — helper GET JSON.
- Módulo de sliders/código de dibujo: `build/init/resetAll/setById/setBlankState/refreshBlankStates/getActiveTelar/getSuffix/updateSuffix/updateHiddenValue/updateNoDataMessage/setFromCodigoDibujo/clear/initListeners`.
- Selects dependientes calibre/fibra/color: `getCalibres/getFibras/getColores/setOptions/ensureOption/getRowEls/updateColorFromCod/loadDependents/initForRow`.
- Pasadas: `getInputs/calcularSuma/sincronizar/adjuntarListeners/reset`.
- `crearFilaDetalle(...)`, `actualizarResumen(data)`, `prefillDesde(data)` (con `updateJulioBadge`), `resetFormularioCompleto()`.
- Carga AJAX: `cargarProducciones(telarId)` (→ `desarrolladores/telar/{id}/producciones-html`), `cargarJuliosPorTelar(telarId)` (→ `.../julios`), `cargarDetallesOrden(noProduccion)` (→ `orden/{n}/detalles`), `cargarResumenCatCodificados(telarId,noProduccion)` (→ `catcodificados/{telar}/{n}`), `buscarYActualizarCodigoDibujo(salon,telar,tamano)` (→ `modelo-codificado/{salon}/{tamano}`).
- `enviarFormularioDetallado()` y `enviarFormulario()` (async) — POST a `desarrolladores.store`.

`resources/views/modulos/desarrolladores/partials/scripts-muestras.blade.php` — Variante para Muestras con el mismo conjunto de funciones (`spinnerHtml`, `parseDestinoValue`, `setSelectValue`, `checkFormValidity`, `fetchJson`, módulo de código de dibujo, selects dependientes, pasadas, `crearFilaDetalle`, `actualizarEstadoCambioTelar()`, `actualizarResumen`, `prefillDesde`, `resetFormularioCompleto`, `cargarProducciones`, `cargarDetallesOrden`, `cargarResumenCatCodificados`, `buscarYActualizarCodigoDibujo`, `validarCambioTelar()`, `enviarFormulario()`, `calcularLongitudLucha()`) apuntando a las rutas `desarrolladores-muestras/*` y `desarrolladores-muestras.store`.

No existen archivos `.js` en `resources/js/` específicos de este ámbito; toda la lógica vive inline en los blades/partials.

---

## 8. Lógica de negocio y reglas

### 8.1 Inventario de Telares (julios montados)
- **Identidad única** de un registro = `no_telar + tipo + fecha + turno` con `status='Activo'`. `tipo` se normaliza siempre a `Rizo`/`Pie`.
- `store()` **nunca persiste `no_orden`** (lo fuerza a `null`); el `no_orden`/`no_julio`/`metros` se asignan por otros flujos (reservas de inventario).
- **Reserva como fuente de verdad**: `Reservado=1` ⇒ reservado siempre; `Reservado=0` ⇒ sólo si hay reserva activa en `InvTelasReservadas` (búsqueda por `TejInventarioTelaresId` → `Fecha/Turno` → `CONVERT(DATE, ProdDate)`). Esto evita falsos positivos cuando un mismo telar/tipo tiene varias fechas/turnos.
- Al **eliminar** se borran también las reservas asociadas; al **mover fecha** se sincronizan `ProdDate/Fecha/Turno` en reservas y `FechaReq` en `UrdProgramaUrdido` **sólo si el folio de urdido apunta a un único telar** (no se toca si `NoTelarId` es una lista separada por comas, para no afectar a otros telares del grupo).

### 8.2 BPM Tejedores (checklist de cambio de turno)
- **Folio** prefijo `BT`, secuencia `BPMTEjido` en `dbo.SSYSFoliosSecuencias`, padding 5 (`BT00001`), generado con `lockForUpdate` + reconciliación contra el máximo real + guard anti-duplicado.
- **Checklist** = producto cartesiano `Actividades (TelActividadesBPM) × Telares del operador que recibe (TelTelaresOperador)`, materializado en `TelBPMLine`. Se inicializa al crear el folio y se completa on-demand al abrir el grid si faltan celdas.
- **Estado de celda** tri-valor: `NULL → OK → X → M (Mantenimiento) → NULL`. La edición (toggle/bulk/comentarios) **sólo se permite en estado `Creado`**.
- **Flujo de estados**: `Creado` → (finish) `Terminado` → (authorize) `Autorizado`, con (reject) `Terminado` → `Creado`. Autorizar/rechazar **exigen puesto "supervisor"** (verificado en `SYSUsuario`); se registran `CveEmplAutoriza`/`NomEmplAutoriza`. Eliminar/editar encabezado sólo en `Creado` (borrado en cascada de líneas).
- **Resolución de turno** del empleado: cadena de fallbacks `SYSUsuario` exacto → `SYSUsuario` numérico (`TRY_CONVERT`) → `TelTelaresOperador` exacto/numérico. Validación: entrega ≠ recibe.

### 8.3 Desarrolladores (poner orden en proceso)
Flujo de `ProcesarDesarrolladorService::store()` (transaccional):
1. **Validación** (`validarYNormalizarEntrada`): `NoTelarId` (req), `NoProduccion` (req, max:80), `accion` (`finalizar`|`reprogramar_siguiente`|`reprogramar_final`), `NumeroJulioRizo` (req), `NumeroJulioPie`, `TotalPasadasDibujo` (req, ≥1), `HoraInicio`/`HoraFinal` (`H:i`), `EficienciaInicio`/`EficienciaFinal` (0–100), `Desarrollador`, `TramaAnchoPeine`, `DesperdicioTrama`, `LongitudLuchaTot`, `CodificacionModelo` (req, max:100), `pasadas[]`, `CambioTelarActivo` (bool), `TelarDestino`. Si `CambioTelarActivo` exige `TelarDestino` con formato `salon|telar`.
2. **Cálculos**: `minutosCambio` (diferencia HoraInicio↔HoraFinal), `fechaInicioProgramada` (hoy a la HoraFinal), `longitudLuchaTot` normalizado.
3. **Contexto origen**: localiza el `ReqProgramaTejido` por `NoProduccion`+`NoTelarId` (`lockForUpdate`); si la fila no tiene orden, la asigna por `registroId`.
4. **Contexto destino**: mismo telar o, si hay cambio, parsea `salon|telar`. Bloqueo liviano del telar destino.
5. **Persistencia**: arma `detallePayload`/`pasadasPayload` desde la orden, resuelve/copía el modelo destino, actualiza `CatCodificados`, `ReqModelosCodificados` (cuando no es cambio de telar) y los programas relacionados (fechas).
6. **Movimiento**: `ejecutarMovimientoYPonerEnProceso` mapea acción → `Reprogramar` (`1` siguiente, `2` final) y delega en `MovimientoDesarrolladorService` (`moverRegistroEnProceso` o `moverRegistroConCambioTelarEnProceso`), recalculando la secuencia/fechas del telar y disparando observers.
7. **Auditoría**: si `accion='finalizar'`, `OrdenFinalizadaAuditoria::registrarDesarrolladorFinalizar(...)` (best-effort).
8. **Notificación Telegram**: `NotificacionTelegramDesarrolladorService` al módulo `Desarrolladores` (incluye cambio de código de dibujo si hubo cambio de telar).

**Muestras** sigue el mismo flujo pero al finalizar **elimina** la muestra del programa y notifica al módulo `DesarrolladoresPrue`.

### 8.4 Notificaciones de producción
- **Atado de Julio**: el tejedor sólo ve sus telares (`TelTelaresOperador`). Notificar fija `horaParo`, registra en `TejNotificaTejedor` (sin duplicar el registro del día, protegiendo julio/orden ya buenos) y avisa a Telegram (módulo `NotificarAtadoJulio` en `SYSMensajes`). Distingue registro completo (Reserva=1) de parcial/sin orden (Reserva=0), completando cuenta/calibre desde `ReqProgramaTejido`.
- **Cortado de Rollo / Marbetes**: la liberación lee de **TI_PRO (`sqlsrv_ti`)** priorizando `TWMarbetes` (`StatusMarebete=0`) y, como fallback, `ProdTable⋈InventDim` (`impreso='SI'`, `ProdStatus=0`, `DATAAREAID='PRO'`). Excluye marbetes ya presentes en `TelMarbeteLiberado` y los inserta sin duplicar por `PurchBarCode`. El acceso a un telar se valida contra los telares del operador (403 si no le corresponde).

### 8.5 Integraciones
- **Telegram**: tres módulos de `SYSMensajes` — `NotificarAtadoJulio`, `Desarrolladores`, `DesarrolladoresPrue` — vía `SYSMensaje::getChatIdsPorModulo()` y `Http::post` a la API de bot. Token en `services.telegram.bot_token`.
- **Excel**: exports `TejedoresReporteExport`, `DesarrolladoresReporteExport`, `DesarrolladoresExport` (`maatwebsite/excel`).
- **Folios**: BPM usa `SSYSFoliosSecuencia::nextFolio()/nextFolioByPrefijo()` (clave `BPMTEjido`, prefijo `BT`).
- **Turnos**: BPM usa `TurnoHelper::getTurnoActual()` como prefill; Inventario no usa TurnoHelper (turno lo captura el usuario).
- **Conexiones SQL Server**: la mayoría usa la default `sqlsrv`; `NotificarMontRollosController` usa `sqlsrv_ti` (TI_PRO) para marbetes; no se observa uso de `sqlsrv_tow_pro` en este ámbito (aunque una traza de `getOrdenProduccion` lo menciona en comentarios, la conexión real consultada es `sqlsrv_ti`).
