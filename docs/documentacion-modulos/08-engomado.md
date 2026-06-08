# Engomado

> Generado automáticamente — documentación detallada del módulo

---

## 1. Propósito del módulo

**Engomado** es el módulo que gestiona el proceso textil de *engomado* (sizing), la etapa
posterior al **Urdido** en la que el hilo de urdimbre se impregna con goma para reforzarlo
antes de pasar a **Tejido**. El módulo cubre el ciclo completo de una orden de engomado:

- **Programación** (`ProgramaEngomado/`): priorización y secuenciación de órdenes por máquina
  (West Point 2 / West Point 3), cambio de status, edición de órdenes programadas y reimpresión.
- **Captura de Fórmulas** (`CapturaFormulas/`): registro de la formulación química (goma) por folio
  — kilos, litros, % sólidos, viscosidad, tiempo de cocinado y componentes (BOM) traídos de AX.
- **Producción** (`Produccion/`): captura de la producción real por julio (Kg Bruto/Neto, tara,
  metros, oficiales/turnos, canoas, humedad, ubicación, roturas), finalización de órdenes y
  calificación de julios con defectos.
- **BPM — Buenas Prácticas de Manufactura** (`BPMEngomado/`): checklist de actividades de turno con
  encabezado (entrega/recibe/autoriza) y líneas marcables (✓/✗), con flujo Creado → Terminado →
  Autorizado/Rechazado.
- **Configuración** (`Configuracion/`): catálogo de ubicaciones, catálogo de actividades BPM,
  catálogo de núcleos (compartido con Urdido) y catálogo de julios.
- **Reportes** (`ReportesEngomadoController`): BPM, Control de Merma, Resumen Semanal de Engomado, y
  enlaces a OEE URD-ENG / Kaizen (controlador de Urdido).

Dentro del flujo productivo, **Engomado consume el resultado de Urdido**: las órdenes
(`EngProgramaEngomado`) comparten el mismo `Folio` que las órdenes de urdido
(`UrdProgramaUrdido`), y una orden de engomado solo puede ponerse "En Proceso" cuando su orden de
urdido está en status `Finalizado`. Aguas abajo, el resultado del engomado alimenta a Tejido.

> El módulo comparte modelos/servicios con el ámbito **urdengomado** (catálogos de julios, núcleos,
> defectos `CatDefectosUrdEng`, auditoría `AuditoriaUrdEng` y el catálogo de máquinas
> `URDCatalogoMaquina`).

---

## 2. Rutas

Archivo: `routes/modules/engomado.php`. Todas las rutas se cargan bajo el middleware `auth`
(definido en el dispatcher `routes/web.php`). No hay verificaciones de permiso `userCan` declaradas
a nivel de ruta; los permisos se aplican dentro de los controladores (ver columna *Permiso*).

El módulo lógico de permisos relevante es **`Producción Engomado`** (verificado en producción y
calificación) y **`Captura de Formula`** (verificado solo en la vista Blade para la sección de
calidad). Los permisos estándar de los catálogos/BPM no usan `userCan`; usan validación por
**área/puesto** (`Supervisores`, `Engomado`, "supervisor").

### Submódulos / dispatcher

| Método | URI | Controller@método | Permiso |
|---|---|---|---|
| GET | `/engomado/{moduloPrincipal?}` | `UsuarioController@showSubModulos` (default `engomado`) | — |
| GET | `/engomado/configuracion/{moduloPadre?}` | `UsuarioController@showSubModulosNivel3` | — |

### Configuración

| Método | URI | Controller@método | Permiso |
|---|---|---|---|
| GET | `/engomado/configuracion/actividadesbpmengomado` | `EngActividadesBpmController@index` | — |
| GET | `/engomado/configuracion/actividades-bpm` (legacy) | `EngActividadesBpmController@index` | — |
| GET | `/engomado/configuracion/catalogodenucleos` | `UrdEngNucleosController@index` | — |
| GET | `/engomado/configuracion/catalogos-nucleos` (legacy) | `UrdEngNucleosController@index` | — |
| GET | `/engomado/configuracion/catalogojulioseng` | `CatalogosUrdidoController@catalogosJulios` | — |
| POST | `/engomado/configuracion/catalogojulioseng` | `CatalogosUrdidoController@storeJulio` | — |
| PUT | `/engomado/configuracion/catalogojulioseng/{id}` | `CatalogosUrdidoController@updateJulio` | — |
| DELETE | `/engomado/configuracion/catalogojulioseng/{id}` | `CatalogosUrdidoController@destroyJulio` | — |
| GET | `/engomado/configuracion/catalogo-ubicaciones` | `CatUbicacionesController@index` | — |
| POST | `/engomado/configuracion/catalogo-ubicaciones` | `CatUbicacionesController@store` | — |
| PUT | `/engomado/configuracion/catalogo-ubicaciones/{id}` | `CatUbicacionesController@update` | — |
| DELETE | `/engomado/configuracion/catalogo-ubicaciones/{id}` | `CatUbicacionesController@destroy` | — |

### Programa Engomado

| Método | URI | Controller@método | Permiso |
|---|---|---|---|
| GET | `/engomado/programaengomado` | `ProgramarEngomadoController@index` | — |
| 301 | `/engomado/programaengomado/produccionengomado` → `/engomado/modulo-produccion-engomado` | redirect | — |
| 301 | `/engomado/bpmbuenaspracticasmanufacturaeng` → `/eng-bpm` | redirect | — |
| 301 | `/engomado/bpm` → `/eng-bpm` | redirect | — |
| GET | `/engomado/capturadeformula` | `EngProduccionFormulacionController@index` | — |
| GET | `/engomado/programar-engomado` | `ProgramarEngomadoController@index` | — |
| GET | `/engomado/reimpresion-engomado` | `ProgramarEngomadoController@reimpresionFinalizadas` | — |
| GET | `/engomado/editar-ordenes-programadas` | `EditarOrdenesEngomadoController@index` | puesto contiene "supervisor" (UI) |
| POST | `/engomado/editar-ordenes-programadas/actualizar` | `EditarOrdenesEngomadoController@actualizar` | — |
| GET | `/engomado/editar-ordenes-programadas/obtener-orden` | `EditarOrdenesEngomadoController@obtenerOrden` | — |
| GET | `/engomado/reimpresion-engomado/ventana-imprimir` | `ProgramarEngomadoController@reimpresionVentanaImprimir` ⚠️ | — |
| GET | `/engomado/programar-engomado/ordenes` | `ProgramarEngomadoController@getOrdenes` | — |
| GET | `/engomado/programar-engomado/verificar-en-proceso` | `ProgramarEngomadoController@verificarOrdenEnProceso` | — |
| POST | `/engomado/programar-engomado/intercambiar-prioridad` | `ProgramarEngomadoController@intercambiarPrioridad` | — |
| POST | `/engomado/programar-engomado/guardar-observaciones` | `ProgramarEngomadoController@guardarObservaciones` | área = `Supervisores` |
| GET | `/engomado/programar-engomado/todas-ordenes` | `ProgramarEngomadoController@getTodasOrdenes` | — |
| POST | `/engomado/programar-engomado/actualizar-prioridades` | `ProgramarEngomadoController@actualizarPrioridades` | — |
| POST | `/engomado/programar-engomado/actualizar-status` | `ProgramarEngomadoController@actualizarStatus` | área = `Supervisores` |

> ⚠️ **Nota de exactitud**: `reimpresionVentanaImprimir` está referenciada apuntando a
> `ProgramarEngomadoController`, pero ese método **no existe** en esa clase (solo existe en
> `Urdido\ProgramaUrdido\ProgramarUrdidoController`). Esta ruta está rota tal como está escrita y
> produciría un error si se invoca.

### Producción Engomado

| Método | URI | Controller@método | Permiso |
|---|---|---|---|
| GET | `/engomado/modulo-produccion-engomado` | `ModuloProduccionEngomadoController@index` | — (UI usa `userCan('modificar','Producción Engomado')`) |
| GET | `/engomado/modulo-produccion-engomado/catalogos-julios` | `ModuloProduccionEngomadoController@getCatalogosJulios` (trait) | — |
| GET | `/engomado/modulo-produccion-engomado/usuarios-engomado` | `ModuloProduccionEngomadoController@getUsuariosEngomado` | — |
| POST | `/engomado/modulo-produccion-engomado/guardar-oficial` | `@guardarOficial` (trait) | `modificar` / `Producción Engomado` |
| POST | `/engomado/modulo-produccion-engomado/eliminar-oficial` | `@eliminarOficial` (trait) | `modificar` / `Producción Engomado` |
| POST | `/engomado/modulo-produccion-engomado/actualizar-turno-oficial` | `@actualizarTurnoOficial` (trait) | `modificar` / `Producción Engomado` |
| POST | `/engomado/modulo-produccion-engomado/actualizar-fecha` | `@actualizarFecha` (trait) | `modificar` / `Producción Engomado` |
| POST | `/engomado/modulo-produccion-engomado/actualizar-julio-tara` | `@actualizarJulioTara` (trait) | `modificar` / `Producción Engomado` |
| POST | `/engomado/modulo-produccion-engomado/actualizar-kg-bruto` | `@actualizarKgBruto` (trait) | `modificar` / `Producción Engomado` |
| POST | `/engomado/modulo-produccion-engomado/actualizar-campos-produccion` | `@actualizarCamposProduccion` | `modificar` / `Producción Engomado` |
| POST | `/engomado/modulo-produccion-engomado/actualizar-campo-orden` | `@actualizarCampoOrden` | — |
| POST | `/engomado/modulo-produccion-engomado/actualizar-horas` | `@actualizarHoras` (trait) | `modificar` / `Producción Engomado` |
| GET | `/engomado/modulo-produccion-engomado/verificar-formulaciones` | `@verificarFormulaciones` | — |
| POST | `/engomado/modulo-produccion-engomado/finalizar` | `@finalizar` | `modificar` / `Producción Engomado` |
| POST | `/engomado/modulo-produccion-engomado/marcar-listo` | `@marcarListo` (trait) | `modificar` / `Producción Engomado` |
| GET | `/engomado/modulo-produccion-engomado/calificar-julios` | `CalificarJuliosController@getJulios` | — |
| POST | `/engomado/modulo-produccion-engomado/calificar-julios/calificar` | `CalificarJuliosController@calificar` | `modificar` / `Producción Engomado` |
| GET | `/engomado/modulo-produccion-engomado/calificar-julios-eng` | `CalificarJuliosController@getJuliosEng` | — |
| POST | `/engomado/modulo-produccion-engomado/calificar-julios-eng/calificar` | `CalificarJuliosController@calificarEng` | `modificar` / `Producción Engomado` |
| GET | `/engomado/modulo-produccion-engomado/pdf` | `PDFController@generarPDFUrdidoEngomado` | — |

### Reportes

| Método | URI | Controller@método | Permiso |
|---|---|---|---|
| GET | `/engomado/reportesengomado` | `ReportesEngomadoController@index` | — |
| GET | `/engomado/reportesengomado/bpm-engomado` | `ReportesEngomadoController@reporteBpm` | — |
| GET | `/engomado/reportesengomado/bpm-engomado/excel` | `ReportesEngomadoController@exportarBpmExcel` | — |
| GET | `/engomado/reportesengomado/control-merma` | `ReportesEngomadoController@reporteControlMerma` | — |
| GET | `/engomado/reportesengomado/control-merma/excel` | `ReportesEngomadoController@exportarControlMermaExcel` | — |
| GET | `/engomado/reportesengomado/resumen-engomado` | `ReportesEngomadoController@reporteResumenEngomado` | — |
| GET | `/engomado/reportesengomado/resumen-engomado/excel` | `ReportesEngomadoController@exportarResumenEngomadoExcel` | — |

### Recursos (fuera del grupo `engomado.`)

| Método | URI | Controller@método |
|---|---|---|
| resource | `eng-actividades-bpm` | `EngActividadesBpmController` (index/store/update/destroy/create/edit) |
| resource | `eng-bpm` | `EngBpmController` (index/store/update/destroy) |
| GET | `eng-bpm-line/{folio}` | `EngBpmLineController@index` |
| POST | `eng-bpm-line/{folio}/toggle` | `EngBpmLineController@toggleActividad` |
| PATCH | `eng-bpm-line/{folio}/terminar` | `EngBpmLineController@terminar` |
| PATCH | `eng-bpm-line/{folio}/autorizar` | `EngBpmLineController@autorizar` |
| PATCH | `eng-bpm-line/{folio}/rechazar` | `EngBpmLineController@rechazar` |
| GET | `eng-formulacion/validar-folio` | `EngProduccionFormulacionController@validarFolio` |
| GET | `eng-formulacion/by-id` | `EngProduccionFormulacionController@getFormulacionById` |
| GET | `eng-formulacion/componentes/formula` | `@getComponentesFormula` |
| GET | `eng-formulacion/componentes/formulacion` | `@getComponentesFormulacion` |
| GET | `eng-formulacion/calibres-formula` | `@getCalibresFormula` |
| GET | `eng-formulacion/fibras-formula` | `@getFibrasFormula` |
| GET | `eng-formulacion/colores-formula` | `@getColoresFormula` |
| GET | `eng-formulacion/formulas-disponibles` | `@getFormulasDisponibles` |
| resource | `eng-formulacion` | `EngProduccionFormulacionController` (index/store/update/destroy) |
| resource | `urd-eng-nucleos` | `UrdEngNucleosController` |

---

## 3. Controllers

### 3.1 `EngActividadesBpmController`
`app/Http/Controllers/Engomado/Configuracion/ActividadesBPMEngomado/EngActividadesBpmController.php`
CRUD del catálogo de actividades del checklist BPM. Vista única con modales (no usa create/edit dedicados).

- **`index(Request)`** (línea 15): listado paginado de `EngActividadesBPM`. Lee `q` (búsqueda
  `like` sobre `Actividad`/`Orden`) y `per_page` (default 15). Ordena por `Orden`, `Id`. Devuelve
  `modulos.engomado.eng-actividades-bpm.index`. Tabla: `EngActividadesBPM` (sqlsrv).
- **`store(Request)`** (línea 37): valida `Orden` (`nullable|integer|min:1`) y `Actividad`
  (`required|string|max:100`). Crea registro y redirige a `eng-actividades-bpm.index` con flash
  `success`.
- **`update(Request, EngActividadesBpmModel)`** (línea 56): mismas validaciones; actualiza por
  binding de modelo; redirige con flash.
- **`destroy(EngActividadesBpmModel)`** (línea 75): elimina el registro; redirige con flash.
- **`create()` / `edit()`** (líneas 86, 91): no implementan formulario — solo redirigen a
  `eng-actividades-bpm.index` (la UI usa modales).

### 3.2 `CatUbicacionesController`
`app/Http/Controllers/Engomado/Configuracion/CatUbicacionesController.php` — CRUD JSON del catálogo
de ubicaciones (códigos cortos usados en la captura de producción). Tabla: `CatUbicaciones` (sqlsrv).

- **`index(Request)`** (línea 16): filtro opcional `codigo` (`like`), ordena por `Codigo`. Devuelve
  vista `modulos.engomado.configuracion.catalogo-ubicaciones` con `ubicaciones` y `noResults`.
  Captura excepciones y registra en `Log::error`.
- **`store(Request): JsonResponse`** (línea 46): valida `Codigo`
  (`required|string|max:10|unique:CatUbicaciones,Codigo`). Guarda en mayúsculas (`strtoupper(trim)`).
  Devuelve JSON `{success,message}`; 422 en validación, 500 en error.
- **`update(Request,$id): JsonResponse`** (línea 81): `findOrFail`; valida `Codigo` (`max:10`,
  `unique` solo si cambió). Guarda en mayúsculas. JSON con 422/500.
- **`destroy($id): JsonResponse`** (línea 125): `findOrFail` + `delete`. 404 si no existe, 500 en error.

### 3.3 `EngBpmController`
`app/Http/Controllers/Engomado/BPMEngomado/EngBpmController.php` — encabezado de los registros BPM.
Tablas: `EngBPM` (sqlsrv), `SYSUsuario` (`area='Engomado'`), `URDCatalogoMaquina`
(`Departamento='Engomado'`). Usa **`FolioHelper`**.

- **`index()`** (línea 17): carga `items` (`EngBPM` desc), `usuarios` (área Engomado), `maquinas`
  (departamento Engomado), `folioSugerido` vía `FolioHelper::obtenerFolioSugerido('Engomado BPM', 3)`
  (preview, no incrementa). Determina `esSupervisorBpm` con `currentUserIsSupervisor()`. Devuelve
  `modulos.engomado.BPM-Engomado.index`.
- **`store(Request)`** (línea 41): valida `Fecha` (req date), `NombreEmplRec` (req),
  `Status` (`in:Creado,Terminado,Autorizado`), `MaquinaId` (req), `Departamento` (nullable) y varios
  campos de entrega/recibe/autoriza. Combina la fecha elegida con la hora actual del servidor.
  Genera el folio con **`FolioHelper::obtenerSiguienteFolio('Engomado BPM', 3)`** (incrementa).
  Crea el encabezado, guarda `MaquinaId`/`Departamento` en sesión (`bpm_eng_maquina_id`,
  `bpm_eng_departamento`) para las líneas, y redirige a `eng-bpm-line.index`.
- **`update(Request,$id)`** (línea 94): valida `Folio`, `Fecha`, `Status` y campos; actualiza por
  `findOrFail`; redirige con flash.
- **`destroy($id)`** (línea 119): `findOrFail` + `delete`; redirige.
- *(privado)* **`currentUserIsSupervisor(): bool`** (línea 130): resuelve `SYSUsuario` por
  `numero_empleado`/`cve`/`idusuario` y retorna true si `puesto` o `area` contienen "supervisor".

### 3.4 `EngBpmLineController`
`app/Http/Controllers/Engomado/BPMEngomado/EngBpmLineController.php` — líneas del checklist BPM y su
flujo de status. Tablas: `EngBPM`, `EngBPMLine`, `EngActividadesBPM`, `URDCatalogoMaquina`, `SYSUsuario`.

- **`index(string $folio)`** (línea 15): obtiene el encabezado (`firstOrFail`), las actividades
  (`EngActividadesBPM` ordenadas). **Si no existen líneas para el folio, las crea todas con
  `Valor=0`** copiando `TurnoRecibe`/`MaquinaId`/`Departamento` (de la sesión guardada en store o de
  la primera línea). Resuelve `nombreMaquina` vía `URDCatalogoMaquina`. Devuelve
  `modulos.engomado.Engomado-BPM-Line.index` con `header`, `actividades`, `lineas` (Valor por
  Actividad), `nombreMaquina`, `esSupervisor`.
- **`toggleActividad(Request,$folio)`** (línea 61): recibe `actividad` y `valor` (0/1/2). Solo
  permite cambios si `Status==='Creado'` (403 en otro caso). Actualiza `EngBPMLine.Valor`. JSON
  `{success,affected}`.
- **`terminar($folio)`** (línea 84): solo si `Status==='Creado'`. **Si el usuario es supervisor**,
  termina y autoriza en un paso (`Status='Autorizado'` + clave/nombre del supervisor); si no, marca
  `Status='Terminado'` y limpia autorizador. Redirige a `eng-bpm.index`.
- **`autorizar($folio)`** (línea 113): solo si `Status==='Terminado'`. Requiere supervisor
  (`getSupervisorInfo`, lanza error si no). Pone `Status='Autorizado'` con clave/nombre.
- **`rechazar($folio)`** (línea 136): solo si `Status==='Terminado'`. Requiere supervisor.
  Regresa a `Status='Creado'` y limpia autorizador.
- *(privados)* **`currentUserIsSupervisor()`** (159) y **`getSupervisorInfo(string $accion): array`**
  (170): resuelven `SYSUsuario`, validan que `puesto`/`area` contengan "supervisor" (si no, lanzan
  `RuntimeException`) y devuelven `[clave, nombre]`.

### 3.5 `CalificarJuliosController`
`app/Http/Controllers/Engomado/Produccion/CalificarJuliosController.php` — califica julios con
defectos (penalización). Maneja **dos fuentes**: julios de Urdido (`UrdProduccionUrdido`) y de
Engomado (`EngProduccionEngomado`). Catálogo: `CatDefectosUrdEng` (`Activo=1`).

- *(privado)* **`ensureCanEdit()`** (línea 15): `abort(403)` si no `userCan('modificar','Producción
  Engomado')`.
- **`getJulios(Request): JsonResponse`** (línea 22): valida `folio`. Devuelve julios de
  `UrdProduccionUrdido` (orden numérico por `NoJulio`) + defectos activos. Sin verificación de
  permiso (solo lectura).
- **`calificar(Request): JsonResponse`** (línea 55): `ensureCanEdit`. Valida `julio_id` (req int) y
  `defecto_id` (nullable). Si `defecto_id` vacío, limpia `ClaveDefecto`/`Penalizacion`/
  `OperadorDefecto`/`NoEmplDefecto`/`FechaDefecto`; si no, asigna `ClaveDefecto = defecto->Id`,
  `Penalizacion`, operador/nombre del usuario autenticado y `FechaDefecto = now()`. Guarda en
  `UrdProduccionUrdido`. JSON con el registro.
- **`getJuliosEng(Request): JsonResponse`** (línea 104): igual que `getJulios` pero sobre
  `EngProduccionEngomado` (orden por `Id`).
- **`calificarEng(Request): JsonResponse`** (línea 136): igual que `calificar` pero sobre
  `EngProduccionEngomado`.

### 3.6 `ModuloProduccionEngomadoController`
`app/Http/Controllers/Engomado/Produccion/ModuloProduccionEngomadoController.php` — controlador
central de producción. Usa el trait **`ProduccionTrait`** (provee endpoints compartidos
`getCatalogosJulios`, `guardarOficial`, `eliminarOficial`, `actualizarTurnoOficial`,
`actualizarFecha`, `actualizarJulioTara`, `actualizarKgBruto`, `actualizarHoras`, `marcarListo` y
helpers `traitRefrescarFechaEnRegistrosVacios`, `traitAutollenarOficial1...`,
`traitHasNegativeKgNetoByFolio`, `ensureUserCanEdit`, `validarHorasRegistros`,
`updateProduccionFechaByFolio`, `resolveMonthlyClosureDateContext`).

Configuración del trait (métodos protected): `getProduccionModelClass()=EngProduccionEngomado`,
`getProgramaModelClass()=EngProgramaEngomado`, `getDepartamento()='Engomado'`,
`shouldRoundKgBruto()=true`, `maxKgNetoAllowed()=null` (sin tope), `maxKgBrutoAllowed()=2000.0`,
`getModuleNameForPermissions()='Producción Engomado'`. **`onRegistroDesmarcado()`** resetea
`Impresion=NULL` al desmarcar Finalizar. **`usuarioPuedeEditar()`** = `userCan('modificar',
'Producción Engomado')`. **`resolveFechaFinalizaFromProduccion($folio)`** toma la última `Fecha` de
producción del folio.

- **`index(Request)`** (línea 103): vista de producción.
  - Modo `check_only=true&orden_id=X`: devuelve JSON `{puedeCrear,tieneRegistros,usuarioArea}`.
  - Sin `orden_id`: muestra la vista vacía con `foliosPrograma` (órdenes no finalizadas).
  - Con `orden_id`: carga la orden (`EngProgramaEngomado`). **Verifica que la orden de urdido
    (`UrdProgramaUrdido`) esté `Finalizado`** (si no, redirige con error). Si la orden está
    `Programado`, la pasa a `En Proceso`. Carga julios (`UrdJuliosOrden`), obtiene `Solidos` de la
    última formulación. **Sincroniza registros de `EngProduccionEngomado` con `NoTelas`** (crea
    faltantes con datos del usuario/turno via `TurnoHelper::getTurnoActual()` y metros de la orden, o
    elimina sobrantes priorizando `NoJulio` nulo, luego `KgNeto` nulo, luego más antiguos). Refresca
    fechas y autollena Oficial 1. Calcula `tieneRegistrosParciales` (Finalizar=1 e Impresion
    NULL/0). Devuelve `modulos.engomado.modulo-produccion-engomado` con la orden, julios,
    registros, ubicaciones, `canEdit`, `maxKgBruto`, etc.
- **`getUsuariosEngomado(): JsonResponse`** (línea 350): usuarios de `SYSUsuario` con
  `area='Engomado'` y `numero_empleado` no nulo. JSON `{success,data:[{id,numero_empleado,nombre,turno}]}`.
- **`actualizarCamposProduccion(Request): JsonResponse`** (línea 371): `ensureUserCanEdit`. Valida
  `registro_id`, `campo` (`in:Solidos,Canoa1,Canoa2,Canoa3,Humedad,Ubicacion,Roturas`) y `valor`.
  `Ubicacion` se guarda como string; los demás como numéricos (`Solidos` redondeado a 2 decimales,
  `Roturas` a int). Valida que el campo esté en `fillable`. Guarda en `EngProduccionEngomado`.
- **`actualizarCampoOrden(Request): JsonResponse`** (línea 437): valida `orden_id`, `campo`
  (`in:merma_con_goma,merma_sin_goma`), `valor` (numérico ≥ 0). Mapea a `MermaGoma`/`Merma` en
  `EngProgramaEngomado`. Maneja `QueryException` por columnas inexistentes. *(No verifica permiso.)*
- **`verificarFormulaciones(Request): JsonResponse`** (línea 500): valida `folio`. Devuelve
  `{tieneFormulaciones,cantidad}` contando `EngProduccionFormulacion` por folio.
- **`finalizar(Request): JsonResponse`** (línea 514): `ensureUserCanEdit`. Valida `orden_id`
  (`exists:EngProgramaEngomado,Id`). Reglas: status debe ser `En Proceso` o `Parcial`; no debe haber
  Kg Neto negativo (`traitHasNegativeKgNetoByFolio`); valida horas (`validarHorasRegistros`); **debe
  existir al menos 1 formulación** para el folio. Resuelve contexto de cierre mensual
  (`resolveMonthlyClosureDateContext`). En **transacción (`sqlsrv`)**: marca todos los registros de
  producción `Finalizar=1`, ajusta fechas si aplica cierre mensual, pone la orden en `Finalizado` con
  `FechaFinaliza`, y marca las formulaciones (`EngProduccionFormulacion`) como `Status='Finalizado'`.

### 3.7 `ProgramarEngomadoController`
`app/Http/Controllers/Engomado/ProgramaEngomado/ProgramarEngomadoController.php` — programación y
priorización de órdenes. Inyecta **`ProgramaPrioridadService`**. Usa `ProgramaConfig`
(`ACTIVE_STATUSES`, `STATUS_OPTIONS`, `OBSERVACIONES_MAX_LENGTH`) y `ProgramaRouteHelper::engomado()`.

- *(privado)* **`usuarioPuedeEditar()`** (línea 25): true solo si `area === 'Supervisores'`.
- **`index()`** (línea 40): devuelve `modulos.engomado.programar-engomado` con `canEdit`,
  `programaRoutes`, `observacionesMaxLength`.
- **`reimpresionFinalizadas(Request)`** (línea 52): listado filtrable de `EngProgramaEngomado`
  (filtros `q`, `folio`, `maquina`, `tipo`, `status`). Ordena por `FechaProg`/`Id` desc. Devuelve
  `modulos.engomado.reimpresion-engomado`.
- *(privado)* **`extractTablaNumber(?string)`** (línea 112): convierte `MaquinaEng` a número de tabla
  1/2 (WestPoint 2 → tabla 1, WestPoint 3 → tabla 2; también "Tabla X", "Izquierda"→1,
  "Derecha"→2, números).
- *(privados)* **`activeOrdersQuery()`** (169) y **`fechaProgFallback(object)`** (177): query de
  órdenes activas con `MaquinaEng` no vacía, y timestamp de `FechaProg` para ordenar.
- **`getOrdenes(): JsonResponse`** (línea 192): carga órdenes activas con prioridad opcional
  (`prioridadService->loadRecordsWithOptionalPriority`), cruza con `UrdProgramaUrdido` para marcar
  `urdido_finalizado`, ordena por prioridad/fecha y **agrupa por tabla 1/2**. JSON
  `{success,data:{1:[],2:[]}}`.
- **`verificarOrdenEnProceso(Request): JsonResponse`** (línea 283): recibe `excluir_id`,
  `maquina_eng`, `folio`. **Bloquea** si la orden de urdido no está `Finalizado`
  (`urdidoNoFinalizado:true`). El antiguo límite de 2 órdenes en proceso por tabla **fue removido**
  (siempre devuelve `tieneOrdenEnProceso:false`), pero reporta `cantidad` informativa.
- **`intercambiarPrioridad(Request): JsonResponse`** (línea 368): valida `source_id`/`target_id`
  (`exists`). Intercambia prioridades vía `prioridadService->swapPriorities`. Habilitado para todos.
- **`guardarObservaciones(Request): JsonResponse`** (línea 404): requiere `usuarioPuedeEditar`
  (Supervisores). Valida `id` y `observaciones` (max `OBSERVACIONES_MAX_LENGTH`). Guarda en la orden.
- *(privado)* **`recalcularPrioridadesEngomado()`** (línea 443): recalcula prioridades consecutivas
  de órdenes activas.
- **`actualizarStatus(Request): JsonResponse`** (línea 459): requiere Supervisores. Valida `id` y
  `status` (`in:STATUS_OPTIONS`). En transacción: al pasar a `Cancelado` limpia `Prioridad` y
  **elimina** la producción (`EngProduccionEngomado` por folio) + recalcula prioridades; al salir de
  `Cancelado` hacia activo, asigna `nextPriority`.
- **`getTodasOrdenes(): JsonResponse`** (línea 535): lista plana (sin agrupar por tabla) de órdenes
  activas con prioridad de display, para el modal de reordenamiento.
- **`actualizarPrioridades(Request): JsonResponse`** (línea 595): valida array `prioridades.*.{id,
  prioridad}` y aplica `bulkUpdatePriorities`. Habilitado para todos.

### 3.8 `EditarOrdenesEngomadoController`
`app/Http/Controllers/Engomado/ProgramaEngomado/EditarOrdenesEngomadoController.php` — edición
campo-a-campo de una orden programada con sincronización a producción y **auditoría**
(`AuditoriaUrdEng`). Constantes de acción de metros: `solo_campo`, `actualizar_produccion_toda`,
`actualizar_produccion_sin_hora_inicio`.

- *(privado)* **`usuarioPuedeEditar()`** (29): true si `puesto` contiene "supervisor".
- *(privados)* **`accionesMetrosPermitidasPorStatus()`** (42), **`sincronizarMetrosProduccion()`**
  (62, actualiza `Metros1`/`Metros2`/`Metros3` en `EngProduccionEngomado`),
  **`crearRegistrosProduccionDesdeNoTelas()`** (81, crea N registros con datos de usuario/turno/
  metros/sólidos), **`eliminarRegistrosProduccionPorNoTelas()`** (136, elimina N priorizando los que
  no tienen `HoraInicial`).
- **`index(Request): View|RedirectResponse`** (línea 163): requiere `orden_id`. Carga la orden y
  máquinas de engomado, calcula flags de edición por status (`En Proceso`/`Programado`/`Parcial`),
  carga producción si aplica y ubicaciones. Devuelve `modulos.engomado.editar-orden-engomado`.
  Soporta `from=reimpresion` para ajustar la ruta de regreso.
- **`actualizar(Request): JsonResponse`** (línea 231): valida `orden_id` (`exists`), `campo`
  (whitelist: `RizoPie,Cuenta,Calibre,Metros,Fibra,InventSizeId,SalonTejidoId,MaquinaEng,BomEng,
  BomFormula,TipoAtado,LoteProveedor,NoTelarId,NoTelas,Observaciones`), `valor` y `accion_metros`.
  Reglas de negocio: bloquea `Folio`; varios campos solo editables en `En Proceso/Programado/Parcial`;
  valida longitud de `InventSizeId` contra `INFORMATION_SCHEMA`. En **transacción (`sqlsrv`)**:
  guarda el cambio, **registra auditoría** (`AuditoriaUrdEng::registrar(..ENGOMADO..)`), sincroniza
  metros a producción según `accion_metros`, y al cambiar `NoTelas` (no en Programado) crea/elimina
  registros de producción según el delta. JSON con detalle de registros creados/eliminados.
- **`obtenerOrden(Request): JsonResponse`** (línea 417): devuelve los datos de la orden por
  `orden_id` para precargar el formulario de edición.

### 3.9 `EngProduccionFormulacionController`
`app/Http/Controllers/Engomado/CapturaFormulas/EngProduccionFormulacionController.php` — captura de
fórmulas químicas y sus componentes. Inyecta **`BomMaterialesService`**. Tablas: `EngProduccionFormulacion`,
`EngFormulacionLine` (sqlsrv), `EngProgramaEngomado`, `SYSUsuario`, `URDCatalogoMaquina`, y **AX vía
`sqlsrv_ti`** (`BOMVersion`, `Bom`, `InventTable`, `InventDim`, `ConfigTable`, `InventColor`).

- **`index(Request)`** (línea 23): listado de formulaciones (opcional `folio`), resuelve `Folio` para
  cada item (`resolveFormulacionFolio`), adjunta el `Status` del programa, carga usuarios, máquinas
  de engomado, folios de programa no finalizados, y genera `folioSugerido` con patrón
  `ENG-FORM-{año}-####`. Devuelve `modulos.engomado.captura-formula.index`. También resuelve
  `ordenIdProduccion` para el enlace "Volver a producción".
- **`store(Request)`** (línea 101): valida `FolioProg` (req) y campos de la fórmula (`Kilos`,
  `Litros`, `TiempoCocinado`, `Solidos`, `Viscocidad` requeridos > 0; `componentes` string JSON).
  Verifica que el folio exista en `EngProgramaEngomado` y **no esté finalizado** (`isFinalStatus`).
  En **transacción**: crea el encabezado (`Status='Creado'`, `MaquinaId`/`CveEmpl` desde el
  programa, `Solidos` redondeado), sincroniza `BomFormula` en `EngProgramaEngomado` si hay `Formula`,
  e inserta los componentes en `EngFormulacionLine` (truncando campos con `truncateString`,
  vinculados por `EngProduccionFormulacionId`).
- **`validarFolio(Request)`** (línea 205): query `folio`; devuelve datos del programa
  (`Cuenta`,`Calibre`,`Tipo=RizoPie`) y operador actual. JSON.
- **`getFormulacionById(Request)`** (línea 249): query `id`; devuelve la formulación + sus
  componentes **filtrados estrictamente por `EngProduccionFormulacionId`** (no por Folio). Redondea
  `Solidos` a 2 decimales.
- *(privados)* **`resolveFormulacionFolio()`** (342) y **`normalizePotentialFolio()`** (359):
  resuelven el folio efectivo entre `Folio`, contexto y `ProdId`.
- **`getComponentesFormulacion(Request)`** (línea 368): componentes guardados por `id`
  (`byFormulacionId`) o, como fallback, por `folio`. JSON.
- **`getComponentesFormula(Request)`** (línea 427): componentes de la fórmula **desde AX**
  (`sqlsrv_ti`): JOIN `BOMVersion→Bom→InventTable→InventDim` filtrando `BV.ItemId=formula` y
  `DATAAREAID='PRO'`. JSON con BomId, ItemId, ItemName, ConfigId, ConsumoUnitario, Unidad, Almacen.
- **`update(Request,$folio)`** (línea 476): valida campos (todos nullable) más `Status`
  (`in:Creado,En Proceso,Terminado`) y los flags `ok_tiempo`/`ok_viscocidad`/`ok_solidos`
  (`in:0,1`). Localiza el registro por `formulacion_id` o `folio`. En **transacción**: actualiza solo
  los campos enviados (mapea `ok_*` → `OkTiempo`/`OkViscosidad`/`OkSolidos` con null=vacío), redondea
  `Solidos`, sincroniza `BomFormula` solo si es el primer registro del folio, y reemplaza los
  componentes (`delete byFormulacionId` + recrea). Responde JSON o redirect según `expectsJson`.
- **`destroy(Request,$folio)`** (línea 609): localiza por `formulacion_id` o `folio`. **Bloquea si
  `AX==1`**. En transacción elimina líneas (`byFormulacionId`) y el encabezado.
- **`getCalibresFormula()`** (línea 650): desde `sqlsrv_ti.InventTable` los ItemId
  `AE-014/AE-021/AE-022` (`DATAAREAID='PRO'`). JSON.
- **`getFibrasFormula(Request)`** (línea 673): `sqlsrv_ti.ConfigTable.ConfigId` por `itemId`. JSON.
- **`getColoresFormula(Request)`** (línea 701): `sqlsrv_ti.InventColor` por `itemId`. JSON.
- **`getFormulasDisponibles(Request)`** (línea 729): fórmulas disponibles desde AX vía
  `bomMaterialesService->getBomFormulasWithFallback(bomId, formula)`. JSON.
- *(privados)* **`truncateString($value,$max)`** (759, trunca + log warning) e **`isFinalStatus()`**
  (781, true para `FINALIZADO`/`TERMINADO`).

### 3.10 `ReportesEngomadoController`
`app/Http/Controllers/Engomado/ReportesEngomadoController.php` — reportes y exportaciones Excel.

- **`index()`** (línea 23): muestra el selector de reportes (OEE URD-ENG y Kaizen apuntan a rutas de
  Urdido; BPM, Control Merma y Resumen Engomado son propios). Vista
  `modulos.engomado.reportes-engomado-index`.
- **`reporteControlMerma(Request, ControlMermaReportService)`** (línea 61): si hay `fecha_ini`/
  `fecha_fin`, llama `service->build(...)`; devuelve `modulos.engomado.reportes-control-merma`.
- **`exportarControlMermaExcel(Request, ControlMermaReportService)`** (línea 83): exporta vía
  `Excel::download(new ControlMermaExport($filas), 'control-merma-...xlsx')`.
- **`reporteBpm(Request)`** (línea 102): join `EngBPM h` ⋈ `EngBPMLine l` por `Folio`, filtra por
  rango `h.Fecha` y opcionalmente `Status in (Terminado,Autorizado)` (`solo_finalizados`). Mapea
  `Valor`→texto (`mapearValorBpm`), normaliza claves y marca inicio por folio. Vista
  `modulos.engomado.reportes-bpm-engomado`.
- **`exportarBpmExcel(Request)`** (línea 164): misma consulta; `Excel::download(new
  BpmEngomadoExport($filas))`.
- *(privados)* **`mapearValorBpm(int)`** (218: 1→CORRECTO, 2→INCORRECTO, otro→S/N),
  **`normalizarClaveNumero(mixed)`** (230), **`marcarInicioPorFolio(Collection)`** (248: marca `•`).
- **`reporteResumenEngomado(Request)`** (línea 263): si hay fechas, construye datos semanales
  (`buildReporteSemanalData`). Vista `modulos.engomado.reporte-resumen-engomado`.
- **`exportarResumenEngomadoExcel(Request)`** (línea 285): `Excel::download(new
  ReporteResumenSemanalEngomadoExport(...))`.
- *(privado)* **`buildReporteSemanalData($ini,$fin): array`** (304): agrupa `EngProduccionEngomado`
  (con relación `programa`) por semana ISO (`W-o`), sumando órdenes únicas, julios, Kg neto, metros
  (M1+M2+M3) y cuenta; calcula promedios `peso_promedio`/`metros_promedio`/`cuenta_promedio`.
- *(privado)* **`buildReporteResumenData($ini,$fin): array`** (373): variante por fecha y máquina
  WP2/WP3 (no usada por las rutas actuales; helper auxiliar).
- *(privado)* **`parseReportDate(string): Carbon`** (448): parsea `Y-m-d` o `d/m/Y`.

---

## 4. Services y Helpers del ámbito

### `ControlMermaReportService`
`app/Services/Engomado/ControlMermaReportService.php` — construye el reporte de Control de Merma
cruzando programa, producción de urdido y producción de engomado. Conexión: usa los modelos
(`sqlsrv`).

- **`build(?string $fechaIni, ?string $fechaFin): Collection`** (línea 17): resuelve la columna de
  fecha, carga `EngProgramaEngomado` con `Status='Finalizado'` (con la relación `programaUrdido`
  seleccionando `Cuenta`/`Calibre`/`MaquinaId`) en el rango, carga la producción agrupada por folio y
  delega en `mapProgramas`.
- **`mapProgramas(Collection, ?dateColumn, ?urdProd, ?engProd): Collection`** (línea 41): ordena
  (fecha → máquina → folio), lleva un contador por máquina (WP) y construye por cada programa:
  `maquina_label` (WP2/WP3), `maquina_urdido_label` (KARL MAYER/MCx), `maquina_full_label`,
  `maquina_display`, `folio`, `cuenta`/`hilo` (primer valor lleno entre programa y urdido),
  `merma_sin_goma` (`Merma`), `merma_con_goma` (`MermaGoma`) y los **slots de oficiales** de urdido y
  engomado (`buildOfficialSlots`).
- *(privado)* **`loadProductionGroupedByFolio(Collection): array`** (93): carga `UrdProduccionUrdido`
  y `EngProduccionEngomado` (columnas de oficiales/metros) agrupadas por folio normalizado.
- *(privado)* **`compareProgramas(...)`** (149): ordenamiento por fecha → orden de máquina → folio.
- *(privado)* **`buildOfficialSlots(Collection, string $source): array`** (168): determina el oficial
  responsable por registro (`extractResponsibleOfficial`), agrupa por etiqueta contando ítems únicos
  (julio en urdido, fila en engomado), ordena por conteo desc y deja **máximo 3 slots** (el resto se
  agrupa como "OTROS"); rellena con vacíos hasta 3.
- *(privados)* **`extractResponsibleOfficial(object)`** (240, elige el oficial con más metros),
  **`resolveProductionItemKey(object,$source)`** (277, `julio:` o `row:`),
  **`normalizeOperatorLabel(name,code)`** (294), **`extractEngomadoWP(?string)`** (309: regex →
  WP2/WP3/OTRO), **`machineSortOrder(?string)`** (328), **`extractUrdidoMachineLabel(...)`** (337:
  KARL MAYER / MCx / OTRO), **`normalizeDate`** (360), **`firstFilledValue(...)`** (378),
  **`normalizeNumeric`** (395), **`normalizeText`** (404), **`resolveDateColumn()`** (411: detecta
  `FechaFinaliza`/`FechaProg`/`FechaReq` vía `Schema::hasColumn`, con warning si usa alternativa).

### Helpers del proyecto usados
- **`FolioHelper`** (`obtenerFolioSugerido`/`obtenerSiguienteFolio` con módulo `'Engomado BPM'`) en
  `EngBpmController`.
- **`TurnoHelper::getTurnoActual()`** al crear registros de producción en
  `ModuloProduccionEngomadoController` y `EditarOrdenesEngomadoController`.
- **`AuditoriaUrdEng`** (registro de cambios) en `EditarOrdenesEngomadoController`.
- **`ProgramaPrioridadService`**, **`ProgramaConfig`**, **`ProgramaRouteHelper`** en
  `ProgramarEngomadoController`.
- **`BomMaterialesService`** (`getBomFormulasWithFallback`) en `EngProduccionFormulacionController`.
- **`ProduccionTrait`** en `ModuloProduccionEngomadoController` (endpoints de oficiales/julios/kg/horas).

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `EngProgramaEngomado` | `dbo.EngProgramaEngomado` | sqlsrv | `Id` | `Folio`, `RizoPie`, `Cuenta`, `Calibre`, `Metros`, `MaquinaEng`, `MaquinaUrd`, `BomEng`, `BomFormula`, `Status`, `NoTelas`, `Prioridad`, `MermaGoma`, `Merma`, `FechaProg`, `FechaFinaliza`. Rel `programaUrdido()` → `UrdProgramaUrdido` por `Folio` |
| `EngProduccionEngomado` | `dbo.EngProduccionEngomado` | sqlsrv | `Id` | `Folio`, `Fecha`, `HoraInicial/Final`, `NoJulio`, `KgBruto`, `Tara`, `KgNeto`, `Canoa1-4`, `Humedad`, `Ubicacion`, `Roturas`, `CveEmpl1-3`/`NomEmpl1-3`/`Metros1-3`/`Turno1-3`, `Solidos`, `Finalizar`, `AX`, `Impresion`, `ClaveDefecto`/`Penalizacion`/`FechaDefecto`. Rel `programa()` → `EngProgramaEngomado` por `Folio` |
| `EngProduccionFormulacionModel` | `dbo.EngProduccionFormulacion` | (default, sqlsrv) | `Id` | `Folio`, `fecha`, `Hora`, `MaquinaId`, `Formula`, `Kilos`, `Litros`, `Solidos`, `Viscocidad`, `TiempoCocinado`, `Status`, `OkTiempo/OkViscosidad/OkSolidos`, `AX`. Rel `lines()` / `linesByFolio()` |
| `EngFormulacionLineModel` | `dbo.EngFormulacionLine` | (default, sqlsrv) | `Id` | `Folio`, `EngProduccionFormulacionId` (FK), `ItemId`, `ItemName`, `ConfigId`, `ConsumoUnit`, `ConsumoTotal`, `Unidad`, `InventLocation`. Scopes `byFolio`, `byFormulacionId` |
| `EngBpmModel` | `dbo.EngBPM` | (default, sqlsrv) | `Id` | `Folio`, `Fecha`, `CveEmplRec/NombreEmplRec/TurnoRecibe`, `CveEmplEnt/...`, `CveEmplAutoriza/NomEmplAutoriza`, `Status`. Rel `lines()`, scope `status()` |
| `EngBpmLineModel` | `dbo.EngBPMLine` | (default, sqlsrv) | `Id` | `Folio`, `TurnoRecibe`, `MaquinaId`, `Departamento`, `Orden`, `Actividad`, `Valor` (0/1/2). Rel `header()`, scope `byFolio()` |
| `EngActividadesBpmModel` | `dbo.EngActividadesBPM` | (default, sqlsrv) | `Id` | `Orden`, `Actividad` |
| `CatUbicaciones` | `dbo.CatUbicaciones` | sqlsrv | `Id` | `Codigo` (único, mayúsculas) |
| `CatDefectosUrdEng` | `dbo.CatDefectosUrdEng` | sqlsrv | `Id` | `Clave`, `Penalizacion`, `Defecto`, `Activo` (usado por calificación de julios) |
| `EngAnchoBalonaCuenta` | `dbo.EngAnchoBalonaCuenta` | sqlsrv | `Id` | `Cuenta`, `RizoPie`, `AnchoBalona` (catálogo auxiliar, no referenciado por estos controllers) |

> Modelos externos consumidos: `UrdProgramaUrdido`, `UrdProduccionUrdido`, `UrdJuliosOrden`,
> `URDCatalogoMaquina`, `SYSUsuario`, `AuditoriaUrdEng` (todos `sqlsrv`); y tablas AX en `sqlsrv_ti`
> (`BOMVersion`, `Bom`, `InventTable`, `InventDim`, `ConfigTable`, `InventColor`).

---

## 6. Vistas Blade

Directorio: `resources/views/modulos/engomado/`. Para cada blade se documentan el propósito, las
secciones UI principales y **cada función JS inline** con su(s) endpoint(s).

### 6.1 `reportes-engomado-index.blade.php`
Selector de reportes (lista de enlaces a OEE/Kaizen/BPM/Control Merma/Resumen). Sin JS inline.

### 6.2 `eng-actividades-bpm/index.blade.php`
CRUD del catálogo de actividades BPM (tabla + modales). UI: tabla seleccionable, botones superiores
Editar/Eliminar, modales Crear/Editar. Funciones JS:
- `updateTopButtonsState()` (273): habilita/deshabilita botones según selección.
- `clearSelection()` (291) / `selectRow(row)` (301): manejo de fila seleccionada.
- `handleTopEdit()` (318) / `handleTopDelete()` (326): disparan edición/eliminación de la fila.
- `openActividadModal(modalId)` (332) / `closeActividadModal(modalId)` (338): apertura/cierre modal.
- `openEditModal(key,orden,actividad)` (344) / `openEditModalDirect(...)` (352): precargan el form de
  edición (POST a `eng-actividades-bpm.update`).
- `deleteActivity(key)` (357): confirma con SweetAlert y envía form a `eng-actividades-bpm.destroy`.
- `window.onclick` (382): cierra modales al hacer click fuera.

### 6.3 `configuracion/catalogo-ubicaciones.blade.php`
CRUD JSON del catálogo de ubicaciones. UI: tabla + modal único reutilizable (create/edit). Funciones:
- `getSelectedUbicacionData()` (93): lee datos de la fila seleccionada.
- `selectRow(row)` (105) / `enableButtons()` (135) / `disableButtons()` (154): selección/estado.
- `showSelectionWarning()` (172): alerta si no hay fila seleccionada.
- `editSelected()` (181) / `deleteSelected()` (192): acciones desde botones superiores.
- `openCreateModal()` (203) / `openEditModal(id)` (208) / `openDeleteModal(id)` (224): aperturas.
- `openUbicacionModal(mode,data)` (234): configura el modal según modo.
- `saveUbicacion(data,id)` (285): crea (`POST configuracion.catalogo.ubicaciones.store`) o actualiza
  (`PUT .../{id}`). Usa fetch + CSRF, maneja 422.
- `confirmDeleteUbicacion(id)` (342) / `deleteUbicacion(id)` (358): confirma y hace
  `DELETE configuracion.catalogo.ubicaciones.destroy`.

### 6.4 `BPM-Engomado/index.blade.php`
Listado de registros BPM (encabezados) con filtros, creación (modal) y acciones. UI: tabla
seleccionable, panel de filtros, modal Crear, modal Editar. Funciones:
- `qs/qsa/open/close` (372-383): utilidades DOM/modales.
- `applyFilters()` (408) / `updateFilterButtons()` (488): filtrado en cliente de la tabla.
- `selectRow(row,folio,id)` (563) / `enableButtons()` (578): selección de fila.
- `openChecklist()` (588): navega a `eng-bpm-line.index` del folio (abrir checklist).
- `openEditModal()` (601): precarga y abre el modal de edición (`PUT eng-bpm.update`).
- `confirmDelete()` (627): confirma y envía form a `eng-bpm.destroy`.
- `fillRecibe/fillMaquina/fillEntrega/fillRecibeEdit(select)` (655-680): autocompletan
  clave/turno/departamento al elegir empleado/máquina en los selects.

### 6.5 `Engomado-BPM-Line/index.blade.php`
Checklist de actividades de un folio BPM. UI: grilla de actividades con botones cíclicos, botón
Terminar. Funciones:
- `window.volverAlIndice()` (160): `window.location.replace` a `eng-bpm.index` (evita apilar
  historial; ligado a `popstate`/`pageshow`).
- `validarYTerminar()` (177): verifica que todas las actividades estén marcadas antes de enviar el
  form `form-terminar` (PATCH `eng-bpm-line.terminar`).
- `toggleActividad(btn)` (197): cicla el valor 0→1→2→0 y hace `POST eng-bpm-line.toggle` con
  `{actividad,valor}`, actualizando el icono (○/✓/✗) según respuesta.

### 6.6 `captura-formula/index.blade.php` (≈3589 líneas)
Vista principal de **Captura de Fórmulas**: tabla de formulaciones + modales Crear/Editar/Ver,
sub-modal de componentes, modal de observaciones de calidad, y filtros por columna (menú contextual).
La sección de calidad usa `userCan('registrar','Captura de Formula')` (typo `registrar` correcto en
la columna de `SYSUsuariosRoles`). Funciones JS destacadas (selección no exhaustiva por volumen):

Gestión de fórmula / folio / status:
- `getCreateFormulaHidden()` (795), `setCreateFormulaValorReal(val)` (799),
  `buildFormulasDisponiblesUrl(bomEng,formula)` (818),
  `poblarOpcionesFormulaCreateDesdeAx(...)` (836) → consume `eng-formulacion.formulas-disponibles`.
- `normalizarStatus`/`statusEsFinalizado`/`formulacionTieneAX1`/`obtenerStatusSeleccionado`/
  `obtenerStatusProgramaSeleccionado` (871-888): lógica de estado.
- `actualizarPresentacionFolioCreate` (894), `setCreateFolioValue(folio)` (904),
  `actualizarDisponibilidadRegistroPorStatusPrograma(...)` (944),
  `mostrarBloqueoFinalizado` (970) / `mostrarBloqueoAX1` (979): bloqueos de UI.
- `setButtonEnabled` (988), `actualizarEstadoBotonesAccion` (996),
  `obtenerFormulacionSeleccionadaValida` (1005), `selectRow(row,folio,id)` (1030).

Modales y tabla:
- `openCreateModal(readOnly)` (1042), `cerrarModalCreate` (1131), `disableButtons`/`enableButtons`
  (1138/1145), `obtenerFechaRow` (1149), `ordenarPorFecha(asc)` (1164), `toggleOrdenFecha` (1189).
- `openEditModal()` (1198): abre el modal de edición; carga datos por `eng-formulacion.by-id`.
- `obtenerSnapshotFormulacionCreate` (1410) / `haCambiadoFormulacionCreate` (1434) /
  `actualizarBotonGuardarEdicion` (1440): detección de cambios.
- `openViewModal()` (1452): modo solo lectura; `cargarComponentesVista()` (1790) consume
  `eng-formulacion.componentes.formulacion`; `renderizarTablaComponentesVista` (1822),
  `mostrarErrorComponentesVista` (1856).
- `confirmDelete()` (1865): confirma y hace submit/`DELETE eng-formulacion.destroy`.
- `cargarDatosPrograma(select)` (1904, async): consume `eng-formulacion.validar-folio` para
  precargar Cuenta/Calibre/Tipo/operador.
- `fillEmpleadoEdit(select)` (1986).
- `setEditModalReadOnly`/`setCreateModalReadOnly`/`actualizarEstadoFormulaSelect`/
  `fillEditModalFromRow` (1618-1729): estado de modales.

Componentes (BOM):
- `esComponenteAe021`/`esComponenteAgua`/`obtenerLimiteConsumoInfo`/`mostrarAlertaLimiteConsumo`/
  `aplicarMaxConsumoTotal` (2004-2057): reglas de límite de consumo (AE-021 y agua).
- `abrirModalComponentes(kilos)` (2062) / `cerrarModalComponentes` (2118),
  `renderizarTablaComponentes` (2157), `seleccionarComponente` (2205),
  `calcularConsumoTotal(unit)` (2228), `nuevoComponente` (2232), `editarComponente` (2290),
  `eliminarComponenteSeleccionado` (2352).
- `cargarComponentesCreate(formula)` (2386): consume `eng-formulacion.componentes` (AX).
- `cargarComponentesFormulacion(folio,id)` (2424): consume `eng-formulacion.componentes.formulacion`.
- `setComponenteSelectOptions`/`ensureComponenteOption` (2522/2548),
  `initComponenteSelectorsForRow` (2559) / `initComponenteCalibreForNewRow` (2643): pueblan selects de
  calibre/fibra/color consumiendo `eng-formulacion.calibres`/`.fibras`/`.colores`.
- `renderizarTablaComponentesCreate` (2697), `agregarFilaComponenteCreate` (2849),
  `eliminarFilaComponenteCreate(index)` (2876), `obtenerComponentesCreateDesdeTabla` (2884),
  `mostrarErrorComponentesCreate` (2918).

Filtros por columna y calidad:
- `initCtxMenuFiltering` (2934), `getColumnUniqueValues` (2948), `ctxEscape` (2964),
  `showColumnCtxMenu` (2968), `clearAllCtxFilters` (3102), `filterCtxRows` (3109),
  `updateCtxFilterInfo` (3126), `updateCtxIndicators` (3139): menú contextual de filtros por columna.
- `verObsCalidad(event,texto)` (3350), `abrirModalObsCalidad(btnCalidad)` (3362): modal de
  observaciones de calidad (ciclo de checks OkTiempo/OkViscosidad/OkSolidos).
- `guardarObsCalidad(folio,observaciones,btnCalidad,checks)` (3474, async): hace
  `PUT eng-formulacion.update` con `obs_calidad` y los flags `ok_*`.
- `abrirModalObservaciones(checkbox)` (3546).

### 6.7 `captura-formula-line/index.blade.php`
Variante de checklist (líneas de captura de fórmula). Funciones:
- `window.volverAlIndice()` (170), `esStatusEditableChecklist()` (178),
  `validarYTerminar()` (195), `toggleActividad(btn)` (225): mismo patrón de checklist que BPM-Line
  (toggle con POST y validación antes de terminar).

### 6.8 `modulo-produccion-engomado.blade.php` (≈3381 líneas)
Vista de **captura de producción**: cabecera de la orden, tabla de registros por julio, modal de
oficiales, modal de formulación, modal de calificación. Funciones JS destacadas:

Utilidades/permiso:
- `requireCanEdit()` (822): bloquea acciones si no hay permiso de edición.
- `mostrarToast`/`mostrarAlerta` (831/837): notificaciones.

Formulación:
- `window.abrirModalFormulacion()` (856) / `cerrarModalFormulacion()` (866): modal para registrar
  formulación desde producción.
- `cargarDatosPrograma(select)` (872): precarga datos del programa.

Cálculo y edición de registros:
- `calcularNeto(row)` (906): calcula Kg Neto (= KgBruto − Tara) en la fila.
- `toggleQuantityEdit(element)` (939) / `closeAllQuantityEditors()` (1006): edición inline.
- `actualizarFecha(id,fecha)` (1058, async): `POST modulo.produccion.engomado.actualizar.fecha`.
- `actualizarTurnoOficial(id,numeroOficial,turno)` (1087): `POST ...actualizar.turno.oficial`.
- `actualizarKgBruto(id,kgBruto)` (1152): `POST ...actualizar.kg.bruto`.
- `actualizarJulioTara(id,noJulio,tara,kgNeto)` (1204): `POST ...actualizar.julio.tara`.
- `actualizarHora(id,campo,valor)` (1269): `POST ...actualizar.horas`.
- `actualizarCampoProduccion(id,campo,valor)` (1316): `POST ...actualizar.campos.produccion`
  (Solidos/Canoa1-3/Humedad/Ubicacion/Roturas).
- `actualizarCampoOrden(ordenId,campo,valor)` (2781): `POST ...actualizar.campo.orden`
  (merma_con_goma/merma_sin_goma).

Catálogos/oficiales:
- `cargarCatalogosJulios()` (1372, async): `GET modulo.produccion.engomado.catalogos.julios`.
- `cargarUsuariosEngomado()` (1456): `GET modulo.produccion.engomado.usuarios.engomado`.
- `poblarSelectUsuarios(...)` (1473), `obtenerClavesOficialesEnModal` (1514),
  `obtenerClavesRepetidasEnModal` (1527), `marcarEstadoDuplicadosOficiales` (1545),
  `validarNoOperadorDuplicadoEnModal` (1575), `obtenerTurnosRepetidosEnModal` (1601): validaciones de
  oficiales del modal.
- `renderizarOficialesExistentes(id)` (1631), `abrirModalOficial(id)` (1720),
  `mostrarAlertaErrorModal` (1736), `cerrarModalOficial` (1748),
  `actualizarOficialesEnTabla(...)` (1755), `propagarOficialesHaciaAbajo(...)` (1829),
  `actualizarDisplayOficialEnModal(numero)` (2562): manejan el guardado de oficiales (consumen
  `guardar-oficial`/`eliminar-oficial`).

Bloqueo/validación/finalización:
- `bloquearFila`/`desbloquearFila` (1906/1919), `verificarOficialSeleccionado` (1029),
  `mostrarAlertaOficialRequerido` (1038), `esFilaBloqueada` (1043), `verificarFilaNoFinalizada` (1049).
- `marcarRegistroListo(id,listo,checkbox)` (2011): `POST modulo.produccion.engomado.marcar.listo`.
- `actualizarBotonImprimirParcial()` (2053), `removerErrorAlCambiar(e)` (2066).
- `marcarCampoError`/`limpiarErroresVisuales`/`validarCamposFila`/`validarFilaParaFinalizar`/
  `validarRegistrosCompletos` (2927-3024): validación previa a finalizar.
- `window.imprimirProduccionParcial()` (3111, async): genera/imprime parcial (PDF
  `modulo.produccion.engomado.pdf`).
- `window.finalizar()` (3170, async): `POST modulo.produccion.engomado.finalizar` tras validar.

### 6.9 `editar-orden-engomado.blade.php` (≈820 líneas)
Edición campo-a-campo de la orden programada con sincronización a producción. Funciones JS:
- `showToast`/`showError`/`escapeHtml` (294-304): utilidades de UI seguras.
- `normalizarOficiales(of)` (306) / `renderizarInfoEmpleadosFila(id,oficiales)` (327): muestran
  oficiales por registro.
- `formatearMetrosTabla(v)` (528): formatea metros.
- `sincronizarMetrosTablaPantalla(accion,metros)` (530), `eliminarRegistrosProduccionDeTabla(ids)`
  (542), `agregarRegistrosProduccionATabla(registros)` (562): reflejan en la tabla los cambios
  devueltos por el backend al editar Metros/NoTelas.
- `bindProduccionInput(input)` (645) con `enviar()` (649): cada cambio de campo hace
  `POST editar.ordenes.programadas.actualizar` (campo + valor + accion_metros).

### 6.10 `programar-engomado.blade.php` (≈1074 líneas)
Tablero de programación con dos tablas (WP2/WP3), drag&drop de prioridad, select de status y modal de
reordenamiento. Funciones JS:
- `showToast`/`showError`/`setButtonsEnabled` (163-193): utilidades.
- `renderTipoBadge(tipo)` (198), `renderStatusSelect(orden)` (224): render de celdas.
- `renderTable(tabla)` (263), `renderAllTables()` (358): pintan las tablas con datos de
  `programar.engomado.ordenes`.
- `handleRowClick(row)` (367), `setupRowClickDelegates()` (379): selección de fila → enlaza a poner en
  proceso / verificar (`programar.engomado.verificar.en.proceso`).
- `setupDragAndDrop(tabla)` (400): drag&drop → `intercambiar-prioridad`.
- `cerrarModalEditarPrioridad()` (836), `renderModalPrioridadTable()` (882, usa
  `programar.engomado.todas.ordenes`), `setupModalDragAndDrop()` (932): modal de reordenamiento global
  que guarda vía `actualizar-prioridades`. El cambio de status del select consume
  `programar.engomado.actualizar.status`; observaciones → `guardar.observaciones`.

### 6.11 `reimpresion-engomado.blade.php`
Listado filtrable de órdenes para reimpresión / edición / calificación. Funciones:
- `window.seleccionarFila(row)` (214): selecciona fila.
- `window.calificarJuliosSeleccionado()` (248): abre el modal de calificación (parcial).
- `window.editarOrdenSeleccionada()` (253): navega a `editar.ordenes.programadas?from=reimpresion`.
- `window.imprimirOrdenSeleccionada()` (264, async): imprime/reimprime (PDF).
- `ordenarTabla(columna,orden)` (322), `actualizarContador()` (374), `initFilters()` (402) con
  `applyFilters()` (416): orden y filtros en cliente.

### 6.12 `reportes-bpm-engomado.blade.php`
Reporte BPM (tabla + botón consultar por rango). Función:
- `mostrarModalConsultarBpmEngomado()` (113): pide rango de fechas y navega/exporta a
  `reportes.bpm` / `reportes.bpm.excel`.

### 6.13 `reportes-control-merma.blade.php`
Reporte Control de Merma. Función:
- `mostrarModalConsultarControlMerma()` (89): pide rango y dispara `reportes.control-merma` /
  `.excel`.

### 6.14 `reporte-resumen-engomado.blade.php`
Resumen semanal con gráfica (Chart.js). Funciones:
- `mostrarModalConsultarResumenEngomado()` (134) / `cerrarModalConsultarResumenEngomado()` (138):
  modal de rango → `reportes.resumen-engomado` / `.excel`.
- callback de formato de eje en la configuración de Chart.js (235).

### 6.15 `urd-eng-nucleos/index.blade.php`
CRUD del catálogo de núcleos (compartido con Urdido; recurso `urd-eng-nucleos`). Funciones:
`updateTopButtonsState` (182), `clearSelection` (200), `selectRow` (210), `openCreateModal` (227),
`openEditModal` (291), `handleTopEdit` (357), `handleTopDelete` (365), `deleteNucleo(key)` (371) →
operan contra el recurso `urd-eng-nucleos`.

### 6.16 `partials/modal-calificar-julios.blade.php`
Modal de calificación de julios de **Urdido**. Funciones:
- `fechaHoyCalificacionYmd()` (72), `aplicarColor(selectEl)` (105), `parseFecha(val)` (114),
  `resolveOperadorJulioUrd(j)` (120), `buildInfoJulioUrd(j)` (144), `buildSelect(julio)` (159),
  `renderTabla(julios)` (173).
- `window.abrirModalCalificarJulios()` (199, async): consume `modulo.produccion.engomado.calificar.
  julios.get`.
- `window.cerrarModalCalificarJulios()` (226).
- `window.__calificarJulioChange(julioId,selectEl)` (232, async): `POST
  modulo.produccion.engomado.calificar.julios.save`.

### 6.17 `partials/modal-calificar-julios-eng.blade.php`
Modal de calificación de julios de **Engomado** (variante `*Eng`). Funciones equivalentes:
`fechaHoyCalificacionYmdEng` (69), `aplicarColorEng` (90), `parseFechaEng` (99),
`resolveOperadorJulioEng` (104), `buildInfoJulioEng` (128), `buildSelectEng` (143),
`renderTablaEng` (157), `window.abrirModalCalificarJuliosEng(folio)` (183, consume
`...calificar.julios.eng.get`), `window.cerrarModalCalificarJuliosEng` (210),
`window.__calificarJulioEngChange(julioId,selectEl)` (216, `POST ...calificar.julios.eng.save`).

---

## 7. JS dedicado

El módulo Engomado **no tiene archivos `.js` dedicados** en `resources/js/` propios del ámbito. Toda
la lógica de cliente vive inline en los `<script>` de las vistas Blade (documentados en la sección 6).
Las utilidades globales del proyecto (`window.http`, `window.notify` de `bootstrap.js`) están
disponibles, aunque varias vistas de Engomado aún usan `fetch(...)` crudo + SweetAlert directamente
(pendientes de migración según el plan de auditoría del proyecto).

---

## 8. Lógica de negocio y reglas

### 8.1 Cálculos y fórmulas
- **Kg Neto** = `KgBruto − Tara` (calculado en cliente `calcularNeto` y validado en backend; no se
  permite Kg Neto negativo al finalizar).
- **Tope de Kg Bruto** = 2000 kg (`maxKgBrutoAllowed`). **No hay tope de Kg Neto** en Engomado
  (a diferencia de Urdido, que limita a 700 kg).
- **Metros por registro** = `Metros1 + Metros2 + Metros3` (sumados en reportes y al crear registros).
- **Resumen semanal**: agrupa por semana ISO (`W-o`); calcula `peso_promedio = total_kg/total_julios`,
  `metros_promedio = total_metros/total_julios`, `cuenta_promedio = total_cuenta/total_julios`.
- **% Sólidos** se redondea a 2 decimales en captura de fórmula y producción.
- **Control de Merma**: por cada programa finalizado, calcula la máquina engomado (WP2/WP3) y la de
  urdido (KARL MAYER/MCx), `merma_sin_goma`=`Merma`, `merma_con_goma`=`MermaGoma`, y los 3 oficiales
  responsables (por más metros) tanto de urdido como de engomado, agrupando el sobrante como "OTROS".
- **Límites de consumo de componentes** (captura de fórmula): reglas especiales para AE-021 y agua
  (`obtenerLimiteConsumoInfo`/`aplicarMaxConsumoTotal`) que topan `ConsumoTotal` respecto a los litros.

### 8.2 Restricciones / validaciones de negocio
- Una orden de engomado **solo entra "En Proceso" si su orden de urdido está `Finalizado`**
  (verificado en `index` de producción y en `verificarOrdenEnProceso`).
- **Finalizar** una orden requiere: status `En Proceso`/`Parcial`, sin Kg Neto negativo, horas
  válidas, y **≥ 1 formulación** registrada para el folio.
- En **captura de fórmula** no se puede `store` ni editar fórmulas de un folio finalizado/terminado
  (`isFinalStatus`); no se puede eliminar una formulación con `AX==1`.
- **BPM**: las actividades solo se editan en status `Creado`; el flujo es Creado → Terminado →
  Autorizado, con Rechazado que regresa a Creado. La autorización/rechazo requiere **supervisor**
  (validación por `puesto`/`area`); un supervisor puede terminar+autorizar en un solo paso.
- **Programación**: cambio de status y guardar observaciones requieren `area = 'Supervisores'`;
  intercambio/actualización de prioridades está habilitado para todos.
- **Editar órdenes programadas**: solo `puesto` con "supervisor" (a nivel UI); ciertos campos
  (`RizoPie`, `Cuenta`, `Calibre`, `Fibra`, `MaquinaEng`, `BomEng`, `BomFormula`, `NoTelas`) solo
  editables en `En Proceso/Programado/Parcial`; `Folio` es de solo lectura.
- **Calificación de julios**: lectura libre; calificar requiere `userCan('modificar','Producción
  Engomado')`.
- **Producción** (`actualizarCamposProduccion`, `guardarOficial`, `finalizar`, etc.): requieren
  `userCan('modificar','Producción Engomado')` vía `ensureUserCanEdit`/`ensureCanEdit`.

### 8.3 Flujos completos
- **Crear BPM** → `EngBpmController@store` genera folio (`FolioHelper`), guarda encabezado y deja
  `MaquinaId`/`Departamento` en sesión → redirige a `EngBpmLineController@index`, que **crea
  automáticamente todas las líneas** del checklist con Valor=0 → el operador marca (✓/✗) →
  Terminar/Autorizar.
- **Captura de fórmula** → seleccionar folio de programa → `store` crea encabezado + líneas
  (componentes) y sincroniza `BomFormula` en `EngProgramaEngomado` → editable hasta finalizar.
- **Poner orden en proceso** → `ModuloProduccionEngomadoController@index` valida urdido finalizado,
  pasa orden a `En Proceso` y **sincroniza los registros de producción con `NoTelas`** (crea/borra) →
  captura de julios → `finalizar` marca todo `Finalizar=1`, pone orden y formulaciones en
  `Finalizado`.
- **Editar orden programada** → cada cambio se guarda individualmente y, según el campo, **sincroniza
  la producción** (Metros: actualiza M1 y limpia M2/M3; NoTelas: crea/elimina registros por el delta).
- **Cancelar orden** (`actualizarStatus` → `Cancelado`) → limpia prioridad, **elimina la producción
  del folio** y recalcula prioridades del resto.

### 8.4 Efectos colaterales entre módulos
- Engomado depende fuertemente de **Urdido**: comparte `Folio`, lee `UrdProgramaUrdido` (status),
  `UrdProduccionUrdido` (oficiales/julios para Control de Merma y calificación), `UrdJuliosOrden`,
  `URDCatalogoMaquina` y `CatDefectosUrdEng`. La auditoría usa `AuditoriaUrdEng` (tabla compartida
  URD-ENG).
- La sincronización de producción por `NoTelas` / `Metros` modifica directamente
  `EngProduccionEngomado`, afectando reportes (Resumen, Control de Merma) y el cierre de la orden.
- Cancelar/editar órdenes borra/crea registros de producción, lo que impacta los reportes históricos.

### 8.5 Integraciones
- **Excel** (maatwebsite/excel): exportaciones `BpmEngomadoExport`, `ControlMermaExport`,
  `ReporteResumenSemanalEngomadoExport` (en `app/Exports/`).
- **PDF** (dompdf): impresión de producción/orden vía `PDFController@generarPDFUrdidoEngomado`
  (ruta `modulo.produccion.engomado.pdf`) usada por impresión parcial y reimpresión.
- **AX (ERP) vía `sqlsrv_ti`**: la captura de fórmula consulta el BOM y catálogos de AX
  (`BOMVersion`, `Bom`, `InventTable`, `InventDim`, `ConfigTable`, `InventColor`) para poblar
  componentes, calibres, fibras, colores y fórmulas disponibles.
- **Telegram**: no se observan notificaciones de Telegram directamente en los controladores de este
  ámbito.
- **Folios**: `FolioHelper` para los folios de BPM (`'Engomado BPM'`); los folios de fórmula se
  generan con patrón propio `ENG-FORM-{año}-####` (sin `SSYSFoliosSecuencias`).
- **Turnos**: `TurnoHelper::getTurnoActual()` al autocrear registros de producción.
