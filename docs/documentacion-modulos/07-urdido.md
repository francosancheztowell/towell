# Urdido

> Generado automáticamente — documentación detallada del módulo

## 1. Propósito del módulo

El módulo **Urdido** gestiona la fase de urdido (preparación de los hilos de urdimbre sobre julios/balones) dentro del flujo productivo textil de Towell. El urdido es el paso previo al engomado (engoma de la urdimbre) y al tejido. Está construido sobre Laravel 12 con SQL Server (`sqlsrv`).

Submódulos que abarca:

- **Programar Urdido** (`ProgramarUrdidoController`): tablero de prioridades de órdenes por máquina (MC Coy 1–3 y Karl Mayer), gestión de status, observaciones, calidad, drag-and-drop de prioridades y reimpresión de PDF de órdenes finalizadas.
- **Módulo de Producción Urdido** (`ModuloProduccionUrdidoController` + `ProduccionTrait`): captura de producción por julio (Kg bruto/neto, tara, horas, oficiales, metros, roturas), sincronización con catálogo de julios y finalización de la orden.
- **Editar Órdenes Programadas** (`EditarOrdenesProgramadasController`): edición campo a campo de la orden (con sincronización a Engomado y auditoría) y de los julios/hilos asociados.
- **BPM Urdido** (`UrdBpmController` + `UrdBpmLineController`): checklist de Buenas Prácticas de Manufactura por folio, con flujo de estados Creado → Terminado → Autorizado (firmas de entrega/recibe/autoriza).
- **Configuración**: Catálogos de Julios y Máquinas (`CatalogosUrdidoController`) y Actividades BPM (`UrdActividadesBpmController`).
- **Reportes Urdido** (`ReportesUrdidoController`): 03-OEE URD-ENG, Kaizen urd-eng, Roturas x Millón, BPM Urdido y Resumen Semanal, con exportación a Excel y respaldo en ruta de red.

Rol en el flujo: Planeación genera la orden (`UrdProgramaUrdido` con su `Folio`). Urdido la programa por máquina, la pone *En Proceso*, captura producción y la *Finaliza*; muchos cambios se propagan al registro de Engomado correspondiente (`EngProgramaEngomado`, vinculado por `Folio`).

---

## 2. Rutas

Archivo: `routes/modules/urdido.php`. Todas las rutas están bajo el middleware `auth` (definido en el dispatcher `routes/web.php`). No hay verificación de permiso (`userCan`) declarada a nivel de ruta; los controles de edición se hacen dentro de los controladores (ver columna "Permiso/control"). El campo de permiso en `SYSRoles` mantiene el typo intencional `reigstrar`.

| Método | URI | Controller@método | Permiso/control interno |
|---|---|---|---|
| GET | `/urdido/{moduloPrincipal?}` | `UsuarioController@showSubModulos` | Menú de submódulos |
| GET | `/urdido/reportesurdido` | `ReportesUrdidoController@index` | — |
| GET | `/urdido/reportesurdido/03-oee-urd-eng` | `ReportesUrdidoController@reporte03Oee` | — |
| GET | `/urdido/reportesurdido/kaizen` | `ReportesUrdidoController@reporteKaizen` | — |
| GET | `/urdido/reportesurdido/kaizen/excel` | `ReportesUrdidoController@exportarKaizenExcel` | — |
| GET | `/urdido/reportesurdido/roturas-millon` | `ReportesUrdidoController@reporteRoturas` | — |
| GET | `/urdido/reportesurdido/roturas-millon/excel` | `ReportesUrdidoController@exportarRoturasExcel` | — |
| GET | `/urdido/reportesurdido/bpm-urdido` | `ReportesUrdidoController@reporteBpm` | — |
| GET | `/urdido/reportesurdido/bpm-urdido/excel` | `ReportesUrdidoController@exportarBpmExcel` | — |
| GET | `/urdido/reportesurdido/resumen` | `ReportesUrdidoController@reporteResumen` | — |
| GET | `/urdido/reportesurdido/resumen/excel` | `ReportesUrdidoController@exportarResumenExcel` | — |
| GET | `/urdido/reportesurdido/exportar-excel` | `ReportesUrdidoController@exportarExcel` | — |
| GET | `/urdido/configuracion/{moduloPadre?}` | `UsuarioController@showSubModulosNivel3` | Menú nivel 3 (default `304`) |
| GET | `/urdido/programaurdido` | `ProgramarUrdidoController@index` | `canEdit` = área "Supervisores" |
| (301) | `/urdido/programaurdido/produccionurdido` → `/urdido/modulo-produccion-urdido` | redirect | — |
| (301) | `/urdido/bpmbuenaspracticasmanufacturaurd` → `/urd-bpm` | redirect | — |
| (301) | `/urdido/bpm` → `/urd-bpm` | redirect | — |
| GET | `/urdido/configuracion/actividadesbpmurdido` | `UrdActividadesBpmController@index` | — |
| GET | `/urdido/configuracion/actividades-bpm` | `UrdActividadesBpmController@index` | — (alias legacy) |
| GET | `/urdido/configuracion/catalogosjulios` | `CatalogosUrdidoController@catalogosJulios` | — |
| GET | `/urdido/configuracion/catalogosmaquinas` | `CatalogosUrdidoController@catalogoMaquinas` | — |
| GET | `/urdido/programar-urdido` | `ProgramarUrdidoController@index` | `canEdit` = área "Supervisores" |
| GET | `/urdido/programar-urdido/ordenes` | `ProgramarUrdidoController@getOrdenes` | — (JSON) |
| GET | `/urdido/programar-urdido/todas-ordenes` | `ProgramarUrdidoController@getTodasOrdenes` | — (JSON) |
| GET | `/urdido/programar-urdido/verificar-en-proceso` | `ProgramarUrdidoController@verificarOrdenEnProceso` | — (JSON) |
| POST | `/urdido/programar-urdido/intercambiar-prioridad` | `ProgramarUrdidoController@intercambiarPrioridad` | Todos con acceso |
| POST | `/urdido/programar-urdido/actualizar-prioridades` | `ProgramarUrdidoController@actualizarPrioridades` | Todos con acceso |
| POST | `/urdido/programar-urdido/guardar-observaciones` | `ProgramarUrdidoController@guardarObservaciones` | área "Supervisores" (403 si no) |
| POST | `/urdido/programar-urdido/actualizar-calidad` | `ProgramarUrdidoController@actualizarCalidad` | — (registra `AutorizaCalidad`) |
| POST | `/urdido/programar-urdido/actualizar-status` | `ProgramarUrdidoController@actualizarStatus` | área "Supervisores" (403 si no) |
| GET | `/urdido/reimpresion-urdido` | `ProgramarUrdidoController@reimpresionFinalizadas` | — |
| GET | `/urdido/reimpresion-urdido/ventana-imprimir` | `ProgramarUrdidoController@reimpresionVentanaImprimir` | — |
| GET | `/urdido/editar-ordenes-programadas` | `EditarOrdenesProgramadasController@index` | `puedeEditar` = puesto contiene "supervisor" |
| POST | `/urdido/editar-ordenes-programadas/actualizar` | `EditarOrdenesProgramadasController@actualizar` | Bloqueos por status / AX |
| GET | `/urdido/editar-ordenes-programadas/obtener-orden` | `EditarOrdenesProgramadasController@obtenerOrden` | — (JSON) |
| POST | `/urdido/editar-ordenes-programadas/actualizar-julios` | `EditarOrdenesProgramadasController@actualizarJulios` | Bloqueos por status / AX |
| POST | `/urdido/editar-ordenes-programadas/actualizar-hilos-produccion` | `EditarOrdenesProgramadasController@actualizarHilosProduccion` | — |
| GET | `/urdido/catalogos-julios` | `CatalogosUrdidoController@catalogosJulios` | — |
| POST | `/urdido/catalogos-julios` | `CatalogosUrdidoController@storeJulio` | — (JSON) |
| PUT | `/urdido/catalogos-julios/{id}` | `CatalogosUrdidoController@updateJulio` | — (JSON) |
| DELETE | `/urdido/catalogos-julios/{id}` | `CatalogosUrdidoController@destroyJulio` | — (JSON) |
| GET | `/urdido/catalogo-maquinas` | `CatalogosUrdidoController@catalogoMaquinas` | — |
| POST | `/urdido/catalogo-maquinas` | `CatalogosUrdidoController@storeMaquina` | — (JSON) |
| PUT | `/urdido/catalogo-maquinas/{maquinaId}` | `CatalogosUrdidoController@updateMaquina` | — (JSON) |
| DELETE | `/urdido/catalogo-maquinas/{maquinaId}` | `CatalogosUrdidoController@destroyMaquina` | — (JSON) |
| GET | `/urdido/modulo-produccion-urdido` | `ModuloProduccionUrdidoController@index` | `canEdit` = `userCan('modificar','Producción Urdido')` |
| GET | `/urdido/modulo-produccion-urdido/catalogos-julios` | `ModuloProduccionUrdidoController@getCatalogosJulios` (trait) | — (JSON) |
| GET | `/urdido/modulo-produccion-urdido/usuarios-urdido` | `ModuloProduccionUrdidoController@getUsuariosUrdido` | — (JSON) |
| POST | `/urdido/modulo-produccion-urdido/guardar-oficial` | `…@guardarOficial` (trait) | `ensureUserCanEdit()` |
| POST | `/urdido/modulo-produccion-urdido/eliminar-oficial` | `…@eliminarOficial` (trait) | `ensureUserCanEdit()` |
| POST | `/urdido/modulo-produccion-urdido/actualizar-turno-oficial` | `…@actualizarTurnoOficial` (trait) | `ensureUserCanEdit()` |
| POST | `/urdido/modulo-produccion-urdido/actualizar-fecha` | `…@actualizarFecha` (trait) | `ensureUserCanEdit()` |
| POST | `/urdido/modulo-produccion-urdido/actualizar-julio-tara` | `…@actualizarJulioTara` (trait) | `ensureUserCanEdit()` |
| POST | `/urdido/modulo-produccion-urdido/actualizar-kg-bruto` | `…@actualizarKgBruto` (trait) | `ensureUserCanEdit()` |
| POST | `/urdido/modulo-produccion-urdido/actualizar-campos-produccion` | `ModuloProduccionUrdidoController@actualizarCamposProduccion` | — |
| POST | `/urdido/modulo-produccion-urdido/actualizar-horas` | `…@actualizarHoras` (trait) | `ensureUserCanEdit()` |
| POST | `/urdido/modulo-produccion-urdido/finalizar` | `ModuloProduccionUrdidoController@finalizar` | `ensureUserCanEdit()` |
| POST | `/urdido/modulo-produccion-urdido/marcar-listo` | `…@marcarListo` (trait) | `ensureUserCanEdit()` |
| GET | `/urdido/modulo-produccion-urdido/pdf` | `PDFController@generarPDFUrdidoEngomado` | — |
| resource | `urd-actividades-bpm` (`index/create/store/show/edit/update/destroy`) | `UrdActividadesBpmController` | — |
| resource | `urd-bpm` (`index/create/store/show/edit/update/destroy`) | `UrdBpmController` | — |
| GET | `urd-bpm-line/{folio}` | `UrdBpmLineController@index` | — |
| POST | `urd-bpm-line/{folio}/toggle` | `UrdBpmLineController@toggleActividad` | Solo si header `Status='Creado'` |
| PATCH | `urd-bpm-line/{folio}/terminar` | `UrdBpmLineController@terminar` | Supervisor → autoriza directo |
| PATCH | `urd-bpm-line/{folio}/autorizar` | `UrdBpmLineController@autorizar` | Solo Supervisor |
| PATCH | `urd-bpm-line/{folio}/rechazar` | `UrdBpmLineController@rechazar` | Solo Supervisor |

> Nota: las rutas `urd-actividades-bpm` y `urd-bpm` se declaran fuera del grupo `urdido.` (a nivel raíz), por lo que sus URIs no llevan el prefijo `/urdido`.

---

## 3. Controllers

### 3.1 `ReportesUrdidoController` (`app/Http/Controllers/Urdido/ReportesUrdidoController.php`)

Conexión: por defecto `sqlsrv` (modelos `UrdProduccionUrdido`, `UrdProgramaUrdido`, `EngProduccionEngomado`, `EngProgramaEngomado`, `UrdBpmModel`). No usa `sqlsrv_ti` ni `sqlsrv_tow_pro`.

**Públicas:**

- **`index()`** — Devuelve la vista `modulos.urdido.reportes-urdido-index` con un arreglo de 5 reportes disponibles (03-OEE, Kaizen, Roturas x Millón, BPM Urdido, Resumen).
- **`reporte03Oee(Request)`** — Reporte "03-OEE URD-ENG" en pantalla. Query params: `fecha_ini`, `fecha_fin`, `solo_finalizados` (default `'1'`). Sin rango retorna la vista vacía. Consulta `UrdProduccionUrdido JOIN UrdProgramaUrdido` (`buildReporte03UrdidoQuery`); agrupa por máquina (MC1/MC2/MC3/KM/Otros) con filas ORDEN/JULIO/P.NETO/METROS/OPE. Vista `modulos.urdido.reportes-urdido`.
- **`reporteKaizen(Request)`** — Reporte Kaizen (AX ENGOMADO + AX URDIDO). Mismos params. Llama a `obtenerDatosKaizen()`. Vista `modulos.urdido.reportes-kaizen`.
- **`exportarKaizenExcel(Request)`** — Genera `KaizenExport` y lo guarda en red + descarga (`guardarReporteEnRed`). Sin rango redirige con error.
- **`reporteRoturas(Request)`** — Reporte "Roturas x Millón". Llama a `obtenerDatosRoturas()`. Vista `modulos.urdido.reportes-roturas`.
- **`exportarRoturasExcel(Request)`** — `RoturasMillonExport` → red + descarga.
- **`reporteBpm(Request)`** — Reporte BPM Urdido. Consulta `UrdBPM as h LEFT JOIN UrdBPMLine as l` filtrando por `h.Fecha` entre rango; si `solo_finalizados` filtra `h.Status IN ('Terminado','Autorizado')`. Mapea `Valor` a texto, normaliza claves y marca inicio por folio. Vista `modulos.urdido.reportes-bpm-urdido`.
- **`exportarBpmExcel(Request)`** — `BpmUrdidoExport` → red + descarga.
- **`reporteResumen(Request)`** — Resumen semanal. Params `fecha_ini`/`fecha_fin`. Llama a `buildReporteSemanalDataUrdido()`. Vista `modulos.urdido.reportes-resumen-urdido`.
- **`exportarResumenExcel(Request)`** — `ReporteResumenSemanalUrdidoExport`; descarga directa (no guarda en red).
- **`exportarExcel(Request)`** — Exporta el reporte 03-OEE completo (Hoja por fecha con MC1–KM + WP2/WP3 de engomado + hoja de defectos). Construye `porFecha` (buckets por día), agrega producción de urdido y engomado y datos de defectos (`buildReporte03DefectosData`). `ReportesUrdidoExport` → red + descarga.

**Privadas relevantes:**

- `parseReportDate(string)` — Parsea fecha aceptando `Y-m-d` o `d/m/Y`.
- `extractMcCoyNumber(?string)` — De `MaquinaId` extrae 1–3 (Mc Coy) o 4 (Karl Mayer).
- `maquinaLabel(int)` — 4 → "KM"; resto → "MC{n}".
- `extractEngomadoWP(?string)` — De `MaquinaEng` deriva "WP2"/"WP3" (West Point 2/3, tabla 1/2, izquierda/derecha).
- `calcularTiempoProduccionMin(?string,?string)` — Minutos entre `HoraInicial` y `HoraFinal`; suma 1440 si cruza medianoche (Turno 3).
- `obtenerDatosKaizen(...)` — Construye filas de Engomado (`EngProduccionEngomado JOIN EngProgramaEngomado`) y Urdido (`UrdProduccionUrdido LEFT JOIN UrdProgramaUrdido`), deduplicando por Id y descartando metros ≤ 0.
- `obtenerOficialesKaizen(object)` — Concatena nombres y números de los 3 oficiales (`NomEmpl1..3`, `CveEmpl1..3`).
- `guardarReporteEnRed($export,$red,$download,$nombre)` — Guarda el xlsx temporalmente en disco `local`, lo copia a la ruta de red (`config('filesystems.disks.reports_urdido.root')`, fallback UNC `\\192.168.2.11\...EFIC-CA UR-ENG 2026`) y devuelve `Excel::download`.
- `obtenerDatosRoturas(...)` — Agrupa producción por Folio; calcula metros/julio, hilos/julio, metros orden, millón de metros = `(metros_orden * hilos_julio) / 1.000.000`, y suma roturas (Hilatura/Maquina/Operac/Transf).
- `mapearValorBpm(int)` — 1→"CORRECTO", 2→"INCORRECTO", otro→"S/N".
- `normalizarClaveNumero(mixed)` — Convierte a int si es numérico, sino null.
- `marcarInicioPorFolio(Collection)` — Marca `InicioFolio='•'` en la primera fila de cada folio.
- `obtenerOperadorDisplayCompleto/obtenerOperadorDisplay` — Devuelve nombre o clave del operador.
- `extraerOperadoresConMetros(object)` — Extrae los operadores 1–3 con metros > 0.
- `operadorUrdidoMayorMetros(object)` — Operador con mayor Metros1–3.
- `buildReporte03UrdidoQuery / buildReporte03EngomadoQuery` — Builders base; urdido filtra `Finalizar=1` y engomado `Status='Finalizado'` cuando `solo_finalizados`.
- `normalizeReporte03DateKey / ensureReporte03DateBucket / buildReporte03DateBuckets` — Normalización y creación de buckets por fecha.
- `buildReporte03DefectosData / fetchReporte03DefectosRegistros / mapReporte03DefectoRecord / buildReporte03SeguridadRows / normalizeReporte03DefectOperator` — Construyen las hojas de Calidad/Seguridad/5S a partir de `ClaveDefecto` + `CatDefectosUrdEng` (LEFT JOIN) para urdido y engomado.
- `buildReporteSemanalDataUrdido(...)` — Agrupa producción por semana ISO (`W-o`): órdenes, julios, kg, metros, cuenta y promedios.
- `buildReporteResumenData(...)` — (variante diaria, no expuesta directamente por las rutas actuales).

### 3.2 `ProgramarUrdidoController` (`app/Http/Controllers/Urdido/ProgramaUrdido/ProgramarUrdidoController.php`)

Inyecta `ProgramaPrioridadService`. Conexión `sqlsrv`.

**Públicas:**

- **`index()`** — Vista `modulos.urdido.programar-urdido`. Pasa `canEdit` (`usuarioPuedeEditar`), `programaRoutes` (`ProgramaRouteHelper::urdido()`) y límites de longitud (`ProgramaConfig`).
- **`reimpresionFinalizadas(Request)`** — Lista de órdenes para reimpresión. Filtros por `q`, `folio`, `maquina`, `tipo`, `status`. Selecciona `UrdProgramaUrdido` ordenado por `FechaProg desc, Id desc`. Vista `modulos.urdido.reimpresion-urdido`.
- **`reimpresionVentanaImprimir(Request)`** — Recibe `orden_id`; arma la URL del PDF (`urdido.modulo.produccion.urdido.pdf` con `tipo=urdido`, `reimpresion=1`) y muestra `reimpresion-urdido-popup` (que abre el diálogo de impresión).
- **`getOrdenes(): JsonResponse`** — Órdenes activas (status en `ProgramaConfig::ACTIVE_STATUSES`) con `MaquinaId` no nulo, agrupadas por MC Coy 1–4. Usa `prioridadService->loadRecordsWithOptionalPriority` y `sortRecords`; devuelve por orden: id, folio, tipo, cuenta_calibre (`InventSizeId`), configuracion (`Fibra`), metros, mccoy, status, observaciones, prioridad, calidad/comentario/autoriza/fecha.
- **`subirPrioridad(Request)`** — Valida `id` existe. Intercambia `CreatedAt` con la orden anterior del mismo MC Coy (sube prioridad). Transaccional. (No mapeada en rutas actuales pero pública.)
- **`verificarOrdenEnProceso(Request)`** — Query `excluir_id`, `maquina_id`. Cuenta órdenes "En Proceso" del mismo MC Coy; bloquea si ≥ 2 (`limitePorMaquina=2`). Devuelve `tieneOrdenEnProceso`, `cantidad`, `limite`, `maquina`, `mensaje`.
- **`bajarPrioridad(Request)`** — Inverso de `subirPrioridad` (intercambia con la posterior). Transaccional. (No mapeada en rutas actuales.)
- **`intercambiarPrioridad(Request)`** — Valida `source_id`, `target_id`. Llama `prioridadService->swapPriorities`. Usado por drag-and-drop.
- **`guardarObservaciones(Request)`** — Requiere `usuarioPuedeEditar` (403 si no). Valida `id` y `observaciones` (max `OBSERVACIONES_MAX_LENGTH`). Guarda `Observaciones`.
- **`actualizarCalidad(Request)`** — Valida `id`, `calidad` (A/R/O), `calidadcomentario`. Guarda `Calidad`, `CalidadComentario`, `AutorizaCalidad` (= nombre del usuario) y `FechaCalidad`. **Envía notificación a Telegram** al módulo `UrdidoCalidad` (`SYSMensaje::getChatIdsPorModulo`) con el estado (Aprobado/Rechazado/Observaciones).
- **`actualizarStatus(Request)`** — Requiere `usuarioPuedeEditar` (403). Valida `id` y `status` (`ProgramaConfig::STATUS_OPTIONS`). Al pasar a *En Proceso* respeta el límite de 2 por máquina. Al **Cancelar**: pone `Prioridad=null`, borra registros `UrdProduccionUrdido` del folio, cancela también la orden de Engomado (`EngProgramaEngomado`) y borra su producción (`EngProduccionEngomado`), y recalcula prioridades. Al **reactivar** desde cancelado asigna `nextPriority`. Transaccional.
- **`getTodasOrdenes(): JsonResponse`** — Todas las órdenes activas sin agrupar, con prioridad calculada. Para vistas globales.
- **`actualizarPrioridades(Request)`** — Valida arreglo `prioridades[].id/.prioridad`. `prioridadService->bulkUpdatePriorities`.

**Privadas relevantes:** `usuarioPuedeEditar()` (área = "Supervisores"), `extractMcCoyNumber()`, `activeOrdersQuery()`, `createdAtFallback()`, `recalcularPrioridades()`.

### 3.3 `ModuloProduccionUrdidoController` (`app/Http/Controllers/Urdido/Configuracion/ModuloProduccionUrdidoController.php`)

Usa `ProduccionTrait` (ver sección 4). Define la configuración del trait:
- `getProduccionModelClass()` → `UrdProduccionUrdido`
- `getProgramaModelClass()` → `UrdProgramaUrdido`
- `getDepartamento()` → `'Urdido'`
- `shouldRoundKgBruto()` → `false`
- `maxKgNetoAllowed()` → `700.0`
- `getModuleNameForPermissions()` → `'Producción Urdido'`

**Públicas (propias):**

- **`index(Request)`** — Punto de entrada de la pantalla de captura. Si `check_only=true` + `orden_id`, devuelve JSON (`handleCheckOnlyRequest`). Sin `orden_id` muestra vista vacía. Con `orden_id`: carga la orden; si está *Programado* la transiciona a *En Proceso* (`transitionToEnProceso`, respeta el límite de 2 por máquina); obtiene los julios (`UrdJuliosOrden`), calcula total de registros, **garantiza la existencia de registros de producción** (`ensureProductionRecordsExist`), refresca fecha y autocompleta oficial 1 (métodos del trait), y renderiza `modulos.urdido.modulo-produccion-urdido`.
- **`actualizarCamposProduccion(Request)`** — Valida `registro_id`, `campo` ∈ {Hilos, Hilatura, Maquina, Operac, Transf, Vueltas, Diametro}, `valor` numérico 0–99999. Vueltas/Diametro como float, resto int. Actualiza el campo en `UrdProduccionUrdido`.
- **`getUsuariosUrdido(): JsonResponse`** — Lista usuarios `SYSUsuario` de área "Urdido" (más `idusuario=22`, oficial especial), con `numero_empleado` no nulo.
- **`finalizar(Request)`** — `ensureUserCanEdit()`. Valida `orden_id`. Solo finaliza órdenes *En Proceso*/*Parcial*. Rechaza si hay Kg Neto negativo (`traitHasNegativeKgNetoByFolio`), si fallan validaciones de horas (`validarHorasRegistros`), o si es Karl Mayer y faltan Vueltas/Diámetro. Resuelve fecha de cierre mensual (`resolveMonthlyClosureDateContext`). En transacción `sqlsrv`: borra registros sin HoraInicial/HoraFinal, marca `Finalizar=1`, ajusta fecha de cierre, y pone la orden `Status='Finalizado'` + `FechaFinaliza`.

**Privadas relevantes:** `usuarioPuedeEditar()` (`userCan('modificar','Producción Urdido')`), `extractMcCoyNumber()`, `handleCheckOnlyRequest()`, `getEmptyViewData()`, `transitionToEnProceso()`, `getJuliosForOrder()`, `calculateTotalRegistros()`, `ensureProductionRecordsExist()` (sincroniza registros por `Hilos`: crea faltantes con datos del usuario/turno/metros y elimina sobrantes priorizando NoJulio vacío, luego KgNeto vacío, luego Id más antiguo), `prepareViewData()` (incluye engomado, destino, `isKarlMayer`, hilo, tipoAtado, loteProveedor).

### 3.4 `EditarOrdenesProgramadasController` (`app/Http/Controllers/Urdido/ProgramaUrdido/EditarOrdenesProgramadasController.php`)

Conexión `sqlsrv`. Sincroniza con `EngProgramaEngomado` y audita con `AuditoriaUrdEng`.

**Públicas:**

- **`index(Request)`** — Vista de edición `modulos.urdido.editar-orden-programada`. Query `orden_id` y `from` (`reimpresion` cambia el botón "volver"). Carga julios, calcula `AX` (bloqueo si `ax=1`), máquinas de área Urdido, registros de producción (si Finalizado/En Proceso), mapa julios→hilos, detecta Karl Mayer y obtiene la fecha de requerimiento de hilo (`UrdConsumoHilo`). `puedeEditar` = puesto contiene "supervisor" (no redirige; solo restringe).
- **`actualizar(Request): JsonResponse`** — Edita **un** campo de la orden. Valida `orden_id`, `campo` (lista blanca: RizoPie, Cuenta, Calibre, Metros, Fibra, InventSizeId, SalonTejidoId, MaquinaId, BomId, FechaProg, TipoAtado, LoteProveedor, FolioConsumo, NoTelarId, Observaciones), `valor`, `accion_metros`. Reglas: Folio no editable; si no es Karl Mayer exige Engomado relacionado; bloqueos por `AX=1` (no edita campos de Urdido); InventSizeId no en estado *Parcial* y validación de longitud contra `INFORMATION_SCHEMA`; RizoPie/Cuenta/Calibre/Fibra/MaquinaId/BomId solo en *En Proceso*/*Programado*. Sincroniza el campo a Engomado (incluyendo `MaquinaId→MaquinaUrd`, `BomId→BomUrd`), audita ambos cambios y, para `Metros`, sincroniza producción según `accion_metros` (solo_campo / actualizar_produccion_toda / actualizar_produccion_sin_hora_inicio). Transaccional.
- **`obtenerOrden(Request): JsonResponse`** — Devuelve datos de la orden (id, folio, rizo_pie, cuenta, calibre, metros, kilos, fibra, salon, maquina, fecha_prog, tipo_atado, lote_proveedor, observaciones, status).
- **`actualizarHilosProduccion(Request): JsonResponse`** — Actualiza `Hilos` en `UrdJuliosOrden` (por Folio + NoJulio) y propaga a `UrdProduccionUrdido` (si no está *Programado*).
- **`actualizarJulios(Request): JsonResponse`** — Alta/edición/eliminación de un registro de `UrdJuliosOrden`. Reglas por status y AX. En *En Proceso* crea/elimina registros de producción proporcionalmente al cambio de cantidad de julios. En *Finalizado* solo permite actualizar Hilos.

**Privadas relevantes:** `usuarioPuedeEditar()`, `obtenerEngomadoPorOrden()`, `accionesMetrosPermitidasPorStatus()`, `sincronizarMetrosProduccion()`, `sincronizarHilosProduccionPorFolio()`, `crearRegistrosProduccionDesdeJulio()`, `eliminarRegistrosProduccionPorEliminacionJulio()` (elimina priorizando registros sin HoraInicial).

### 3.5 `CatalogosUrdidoController` (`app/Http/Controllers/Urdido/Configuracion/CatalogosJulios/CatalogosUrdidoController.php`)

Comparte vistas con Engomado (detecta departamento por nombre de ruta / path). Conexión `sqlsrv`.

**Públicas:**

- **`catalogosJulios(Request)`** — Lista `UrdCatJulios` filtrando `Departamento` ("Urdido" o "Engomado" según ruta) y opcional `no_julio`. Vista `catalogosurdido.catalago-julios`.
- **`catalogoMaquinas(Request)`** — Lista `URDCatalogoMaquina` con filtros `maquina_id`, `nombre`, `departamento`. Vista `catalogosurdido.catalago-maquinas`.
- **`storeMaquina(Request)`** — JSON. Valida `MaquinaId` único (`URDCatalogoMaquinas`), `Nombre`, `Departamento`. Crea máquina.
- **`updateMaquina(Request,$maquinaId)`** — JSON. Si cambia `MaquinaId` valida unicidad y recrea (borra + crea, por ser PK string); sino actualiza Nombre/Departamento.
- **`destroyMaquina($maquinaId)`** — JSON. Elimina por `MaquinaId` (404 si no existe).
- **`storeJulio(Request)`** — JSON. Departamento según ruta/path. Valida `NoJulio` único, `Tara` numérica. Crea julio.
- **`updateJulio(Request,$id)`** — JSON. Busca por `Id` o `NoJulio`. Valida unicidad si cambia `NoJulio`. Actualiza NoJulio/Tara/Departamento.
- **`destroyJulio($id)`** — JSON. Elimina por `Id` o `NoJulio`.

### 3.6 `UrdActividadesBpmController` (`app/Http/Controllers/Urdido/Configuracion/ActividadesBPMUrdido/UrdActividadesBpmController.php`)

Resource controller (`UrdActividadesBpmModel`, conexión por defecto). Vistas por modales.

**Públicas:**

- **`index(Request)`** — Lista actividades; filtro `q` (busca en `Actividad`/`Orden`), ordena máquinas "MC" primero. Vista `modulos.urdido.urd-actividades-bpm.index`.
- **`store(Request)`** — Valida `Orden` (int), `Actividad` (req, max 100), `Maquina` ∈ {MC,KM}. Crea. `back()->with('success')`.
- **`update(Request,$urdActividadesBpm)`** — Igual validación; actualiza.
- **`destroy($urdActividadesBpm)`** — Elimina.
- **`create()` / `edit()`** — Redirigen a `urd-actividades-bpm.index` (la UI usa modales).

### 3.7 `UrdBpmController` (`app/Http/Controllers/Urdido/BPMUrdido/UrdBpmController.php`)

Encabezado de checklist BPM (`UrdBpmModel`, conexión `sqlsrv`). Usa `FolioHelper`.

**Públicas:**

- **`index()`** — Carga registros BPM (`Id desc`), usuarios de área "Urdido" (`SYSUsuario`), máquinas de Urdido (excluye Karl Mayer/Karl Mayer en nombre, incluye `MaquinaId` 401/402) y `folioSugerido` = `FolioHelper::obtenerFolioSugerido('Urdido BPM', 3)`. Marca `esSupervisorBpm`. Vista `modulos.urdido.BPM-Urdido.index`.
- **`store(Request)`** — Valida Fecha, datos de empleados (Rec/Ent/Autoriza), `Status` ∈ {Creado,Terminado,Autorizado}, `MaquinaId` (req), `Departamento`. Genera folio definitivo con `FolioHelper::obtenerSiguienteFolio('Urdido BPM', 3)`, crea el header, guarda `MaquinaId`/`Departamento` en sesión (para las líneas) y redirige a `urd-bpm-line.index`.
- **`update(Request,$id)`** — Actualiza header (Folio, Fecha, empleados, Status).
- **`destroy($id)`** — Elimina header.

**Privada:** `currentUserIsSupervisor()` — true si el `puesto` o `area` del `SYSUsuario` contiene "supervisor".

### 3.8 `UrdBpmLineController` (`app/Http/Controllers/Urdido/BPMUrdido/UrdBpmLineController.php`)

Líneas del checklist BPM (`UrdBpmLineModel`). La conexión está comentada en el modelo (usa la conexión por defecto del proyecto, `sqlsrv`).

**Públicas:**

- **`index(string $folio)`** — Carga el header. Determina actividades según `MaquinaId` (KM1 → actividades Id 11–20; otro → 1–10) de `UrdActividadesBpmModel`. Si no existen líneas para el folio, las crea todas con `Valor=0` y limpia la sesión `bpm_*`. Obtiene nombre de máquina, valores actuales (`Valor` por `Actividad`) y `esSupervisor`. Vista `modulos.urdido.Urdido-BPM-Line.index`.
- **`toggleActividad(Request,$folio)`** — Cambia `Valor` (0 vacío / 1 palomita / 2 tache) de una actividad. Solo si header `Status='Creado'` (403 si no). JSON.
- **`terminar($folio)`** — Solo desde *Creado*. Si el usuario es supervisor pasa directo a *Autorizado* (registra `CveEmplAutoriza`/`NombreEmplAutoriza`); sino lo deja *Terminado*.
- **`autorizar($folio)`** — Solo desde *Terminado*, solo supervisor; pasa a *Autorizado*.
- **`rechazar($folio)`** — Solo desde *Terminado*, solo supervisor; regresa a *Creado* y limpia campos de autorización.

**Privadas:** `currentUserIsSupervisor()`, `getSupervisorInfo(string $accion)` (resuelve `SYSUsuario` y lanza `RuntimeException` si no es supervisor; retorna `[code, name]`).

---

## 4. Services y Helpers del ámbito

### 4.1 `ProduccionTrait` (`app/Traits/ProduccionTrait.php`)

Trait compartido Urdido/Engomado, usado por `ModuloProduccionUrdidoController`. Endpoints públicos montados por las rutas de producción urdido:

- **`getCatalogosJulios(): JsonResponse`** — Devuelve `UrdCatJulios` (julio, tara, departamento) del `getDepartamento()` actual.
- **`guardarOficial(Request)`** — `ensureUserCanEdit()`. Valida `registro_id`, `numero_oficial` ∈ {1,2,3}, `cve_empl`, `nom_empl`, `metros`, `turno`. Reglas: requiere clave o nombre; no repetir No. Operador en el mismo registro; secuencialidad (Oficial 2 requiere 1, 3 requiere 2); máximo 3 oficiales; advierte (no bloquea) turno repetido. Propaga el Oficial 1 a registros del mismo folio sin HoraInicial cuando es el primero.
- **`eliminarOficial(Request)`** — `ensureUserCanEdit()`. Limpia Cve/Nom/Metros/Turno del oficial N.
- **`actualizarTurnoOficial(Request)`** — `ensureUserCanEdit()`. Cambia `Turno{n}` (requiere oficial existente).
- **`actualizarFecha(Request)`** — `ensureUserCanEdit()`. Cambia `Fecha` del registro.
- **`actualizarJulioTara(Request)`** — `ensureUserCanEdit()`. Setea `NoJulio` y `Tara`; recalcula `KgNeto = KgBruto - Tara`; valida límite de Kg Neto.
- **`actualizarKgBruto(Request)`** — `ensureUserCanEdit()`. Setea `KgBruto` (Urdido no redondea), recalcula `KgNeto`; valida límites de Kg Bruto y Kg Neto (≤ 700 para Urdido).
- **`actualizarHoras(Request)`** — `ensureUserCanEdit()`. Setea `HoraInicial`/`HoraFinal` (regex `HH:MM`).
- **`marcarListo(Request)`** — `ensureUserCanEdit()`. Marca el registro listo validando campos requeridos (HoraInicial, HoraFinal, NoJulio, KgBruto/KgNeto en rango). Bloquea si `AX=1`.

Métodos auxiliares del trait usados por el controller de urdido: `ensureUserCanEdit()`, `validarHorasRegistros()`, `resolveMonthlyClosureDateContext()`, `updateProduccionFechaByFolio()`, `traitHasNegativeKgNetoByFolio()`, `traitRefrescarFechaEnRegistrosVacios()`, `traitAutollenarOficial1EnRegistrosSinHoraInicial()`, `jsonIfKgNetoExceedsLimit()`, `jsonIfKgBrutoExceedsLimit()`, `maxKgBrutoAllowed()`.

### 4.2 `ProgramaPrioridadService` (`app/Services/Programas/ProgramaPrioridadService.php`)

Servicio genérico de prioridades reutilizado por Programar Urdido:

- `loadRecordsWithOptionalPriority($modelClass, $baseColumns, $scope): array` — Carga registros aplicando un scope; agrega `Prioridad` si la columna existe.
- `sortRecords(Collection, callable $fallback): Collection` — Ordena por Prioridad, con fallback (aquí `CreatedAt`).
- `displayPriority(object, int $position): int` — Prioridad a mostrar (la del registro o la posición+1).
- `swapPriorities($modelClass, int $source, int $target)` — Intercambia el campo `Prioridad` entre dos registros.
- `bulkUpdatePriorities($modelClass, array)` — Actualiza prioridades en lote.
- `nextPriority(Builder): int` — Siguiente prioridad libre.
- `recalculatePriorities(Builder, callable $fallback)` — Reasigna prioridades consecutivas.

Soporte: `ProgramaConfig` (constantes `ACTIVE_STATUSES`, `STATUS_OPTIONS`, `OBSERVACIONES_MAX_LENGTH`, `CALIDAD_COMENTARIO_MAX_LENGTH`) y `ProgramaRouteHelper::urdido()` (mapa de rutas para la vista).

### 4.3 Helpers globales usados

- **`FolioHelper`** — `obtenerFolioSugerido('Urdido BPM', 3)` (preview) y `obtenerSiguienteFolio('Urdido BPM', 3)` (al crear, auto-incrementa) en `UrdBpmController`.
- **`TurnoHelper`** — `getTurnoActual()` en `ensureProductionRecordsExist` (turno por defecto al crear registros).
- **`userCan('modificar','Producción Urdido')`** — control de edición en `ModuloProduccionUrdidoController`.

---

## 5. Modelos y tablas

Directorio: `app/Models/Urdido/`. Todos sin timestamps Eloquent.

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `UrdProgramaUrdido` | `dbo.UrdProgramaUrdido` | sqlsrv | `Id` (int) | Folio, RizoPie, Cuenta, Calibre, Fibra, InventSizeId, Metros, MaquinaId, SalonTejidoId, BomId, Status, TipoAtado, LoteProveedor, Prioridad, CreatedAt, FechaProg, FechaFinaliza, Calidad, CalidadComentario, AutorizaCalidad, FechaCalidad. Relaciones: `engomado()` 1:1, `julios()` 1:N, `consumos()`/`consumosRegistrados()` 1:N. Accessor `mc_coy_number`. |
| `UrdProduccionUrdido` | `dbo.UrdProduccionUrdido` | sqlsrv | `Id` (int) | Folio, Fecha, HoraInicial, HoraFinal, NoJulio, Hilos, KgBruto, Tara, KgNeto, Hilatura, Maquina, Operac, Transf, Metros1–3, CveEmpl1–3/NomEmpl1–3/Turno1–3, Finalizar, AX, Vueltas, Diametro, Penalizacion, ClaveDefecto, FechaDefecto. Relación `programa()` N:1. |
| `UrdJuliosOrden` | `dbo.UrdJuliosOrden` | sqlsrv | `Id` (int) | Folio, Julios (cantidad), Hilos, Obs. Relación `programaUrdido()`. |
| `UrdConsumoHilo` | `dbo.UrdConsumoHilo` | sqlsrv | `Id` (int) | Folio, FolioConsumo, ItemId, InventSizeId, InventBatchId, InventQty, Conos, LoteProv, Registrado, FechaRegistro, FechaRequerimiento. Scope `registrados()` (`Registrado=1`). |
| `AuditoriaUrdEng` | `dbo.AuditoriaUrdEng` | sqlsrv | `id` | Tabla, RegistroId, Folio, Accion, Campos, UsuarioId, UsuarioNombre, CreatedAt. Constantes TABLA_URDIDO/TABLA_ENGOMADO/ACCION_*; `registrar()` y `formatoCampo()`. |
| `UrdCatJulios` | `dbo.UrdCatJulios` | sqlsrv | `Id` (int) | NoJulio, Tara, Departamento. |
| `URDCatalogoMaquina` | `dbo.URDCatalogoMaquinas` | sqlsrv | `MaquinaId` (string, no autoincremental) | Nombre, Departamento. |
| `UrdBpmModel` | `dbo.UrdBPM` | sqlsrv | `Id` (int) | Folio, Fecha, CveEmplRec/Ent/Autoriza, NombreEmpl*, TurnoRecibe/Entrega, Status. Relación `lines()` (por Folio), `maquina()`, scope `status()`. |
| `UrdBpmLineModel` | `dbo.UrdBPMLine` | (conexión por defecto) | `Id` (int) | Folio, TurnoRecibe, MaquinaId, Departamento, Orden, Actividad, Valor. Relación `header()`, scope `byFolio()`. |
| `UrdActividadesBpmModel` | `dbo.UrdActividadesBPM` | (conexión por defecto) | `Id` (int) | Orden, Actividad, Maquina (MC/KM). |

Tablas externas leídas (no modelos del ámbito): `dbo.EngProgramaEngomado`, `dbo.EngProduccionEngomado` (sincronización/reportes), `dbo.CatDefectosUrdEng` (reportes), `dbo.SYSUsuarios` (`SYSUsuario`), `dbo.SYSMensajes` (Telegram), `dbo.SSYSFoliosSecuencias` (folios BPM vía `FolioHelper`).

---

## 6. Vistas Blade

Directorio principal: `resources/views/modulos/urdido/` (más `resources/views/catalogosurdido/`). El directorio de catálogos `catalagos` mantiene el typo intencional del proyecto en otros módulos; aquí las vistas de catálogos viven en `catalogosurdido`.

### 6.1 `programar-urdido.blade.php`
Tablero de prioridades por máquina (MC Coy 1–3 + Karl Mayer) con drag-and-drop, selección de orden, edición inline de status/observaciones y modal de calidad. Consume `programar-urdido/ordenes` (GET), `actualizar-status`, `guardar-observaciones`, `intercambiar-prioridad`, `actualizar-calidad`, `verificar-en-proceso`.
- `renderTable(mccoy)` / `renderAllTables()` — Renderizan las filas por máquina a partir de `state.ordenes`.
- `handleRowClick(row)` / `setupRowClickDelegates()` — Selección de orden (habilita botones de cargar/editar).
- `setupDragAndDrop(mccoy)` (event listeners dragstart/dragover/drop) — Reordena prioridad y llama a `intercambiar-prioridad`.
- `obtenerOrdenCalidadVisual(ordenId,mccoy)` — Localiza la orden en el estado.
- `abrirModalCalidad / abrirModalCalidadPorOrden / abrirModalVerCalidad / cerrarModalVerCalidad / cerrarModalCalidad` — Apertura/cierre de modales de calidad.
- `cyclicCalidad()` — Cicla el valor A→R→O. `actualizarDisplayCalidad()` — Refresca el ícono mostrado.
- `guardarCalidad()` — POST a `actualizar-calidad` con calidad y comentario.
- `guardarObservaciones(event, id)` (referenciada en onblur) — POST a `guardar-observaciones`.

### 6.2 `modulo-produccion-urdido.blade.php`
Pantalla de captura de producción. Compone partials: `_header-orden`, `_tabla-registros`, `_modal-oficial`, `_modal-fecha`, `_scripts`. Botón "crear" en navbar. Variables: orden, julios, engomado, metros, destino, isKarlMayer, registrosProduccion, canEdit, maxKgNeto.

### 6.3 `produccion/_header-orden.blade.php`
Encabezado con datos de la orden (folio, fibra, cuenta/calibre, máquina, destino, metros, tipo atado, lote). Sin lógica JS propia.

### 6.4 `produccion/_tabla-registros.blade.php`
Tabla de registros de producción por julio (No. Julio, Tara, Kg Bruto/Neto, horas, oficiales, metros, roturas, listo). Inputs editables que disparan funciones de `_scripts`.

### 6.5 `produccion/_modal-oficial.blade.php`
Modal para gestionar hasta 3 oficiales por registro (clave/nombre/turno/metros).

### 6.6 `produccion/_modal-fecha.blade.php`
Modal para editar la fecha de un registro.

### 6.7 `produccion/_scripts.blade.php` (bloque JS principal de producción)
Funciones inline (todas consumen los endpoints de producción urdido):
- `requireCanEdit()` — Verifica permiso de edición antes de operar.
- `mostrarToast / mostrarAlerta` — Notificaciones (SweetAlert/Toast).
- `calcularNeto(row)` — Calcula Kg Neto = Bruto − Tara en la fila.
- `netoFueraDeRango(neto)` — Valida el límite (700 kg).
- `marcarCampoError / limpiarErroresVisuales` — Resaltado de campos inválidos.
- `closeAllQuantityEditors()` — Cierra editores inline abiertos.
- `bloquearFila / desbloquearFila / esFilaBloqueada(registroId)` — Bloqueo de filas finalizadas/AX.
- `marcarRegistroListo(registroId, listo, checkbox)` — POST `marcar-listo`.
- `verificarFilaNoFinalizada / verificarOficialSeleccionado / mostrarAlertaOficialRequerido` — Validaciones previas.
- `actualizarFecha(registroId, fecha)` — POST `actualizar-fecha`.
- `actualizarTurnoOficial(registroId, numeroOficial, turno)` — POST `actualizar-turno-oficial`.
- `actualizarKgBruto(registroId, kgBruto)` — POST `actualizar-kg-bruto`.
- `actualizarJulioTara(registroId, noJulio, tara)` — POST `actualizar-julio-tara`.
- `actualizarHora(registroId, campo, valor)` — POST `actualizar-horas`.
- `obtenerJuliosSeleccionados / actualizarSelectJulio / actualizarTodosLosSelectsJulios` — Gestión de selects de No. Julio sin duplicar.
- `cargarCatalogosJulios()` — GET `modulo-produccion-urdido/catalogos-julios`.
- `actualizarCampoProduccion(registroId, campo, valor)` — POST `actualizar-campos-produccion` (Hilos, roturas, Vueltas, Diametro).
- `cargarUsuariosUrdido()` — GET `usuarios-urdido`.
- `poblarSelectUsuarios / obtenerClavesOficialesEnModal / obtenerClavesRepetidasEnModal / validarNoOperadorDuplicadoEnModal / obtenerTurnosRepetidosEnModal / marcarEstadoDuplicadosOficiales` — Lógica del modal de oficiales.
- `renderizarOficialesExistentes(registroId)` — Pinta oficiales guardados.
- `abrirModalOficial / cerrarModalOficial / mostrarAlertaErrorModal` — Control del modal.
- `actualizarOficialesEnTabla(registroId, oficiales, opciones)` — Refresca la tabla tras guardar.
- `propagarOficialesHaciaAbajo(registroIdActual, oficiales)` — Replica oficiales a filas siguientes (POST `guardar-oficial`).
- `validarCamposFila / validarFilaParaFinalizar / validarRegistrosCompletos` — Validaciones de finalización.
- `abrirPDFParaImprimir(url)` — Abre el PDF de la orden (`modulo-produccion-urdido/pdf`).

### 6.8 `editar-orden-programada.blade.php`
Edición campo a campo de la orden y de los julios/hilos. Consume `editar-ordenes-programadas/actualizar`, `actualizar-julios`, `actualizar-hilos-produccion`, `obtener-orden`, y endpoints de producción para horas/campos.
- `actualizarHoraProduccion(registroId, campo, valor)` — POST `actualizar-horas`.
- `actualizarCampoProduccion(registroId, campo, valor)` — POST `actualizar-campos-produccion`.
(El resto de la edición de campos de orden/julios se hace mediante handlers inline que llaman a `actualizar`, `actualizar-julios` y `actualizar-hilos-produccion`.)

### 6.9 `reimpresion-urdido.blade.php`
Tabla buscable/filtrable de órdenes para reimpresión.
- `ordenarTabla(columna, orden)` — Ordena la tabla por columna.
- `actualizarContador()` — Cuenta filas visibles.
- `initFilters()` / `applyFilters()` — Filtrado por folio/máquina/tipo/status. Cada fila abre la ventana de impresión (`reimpresion-urdido/ventana-imprimir`).

### 6.10 `reimpresion-urdido-popup.blade.php`
Ventana emergente que carga el PDF y fuerza el diálogo de impresión.
- `abrirImpresion()` — Lanza `window.print()` sobre el iframe del PDF (`pdfUrl`).

### 6.11 `reportes-urdido-index.blade.php`
Selector de los 5 reportes con su acción (pedir rango de fechas). Sin lógica de negocio JS.

### 6.12 `reportes-urdido.blade.php` (reporte 03-OEE en pantalla)
- `mostrarModalConsultarReportesUrdido()` — Abre el modal de rango de fechas y navega con `fecha_ini`/`fecha_fin`/`solo_finalizados`.

### 6.13 `reportes-kaizen.blade.php`
- `mostrarModalConsultarKaizen()` — Modal de rango y navegación al reporte Kaizen (y export Excel).

### 6.14 `reportes-roturas.blade.php`
- `mostrarModalConsultarRoturas()` — Modal de rango para Roturas x Millón.

### 6.15 `reportes-bpm-urdido.blade.php`
- `mostrarModalConsultarBpmUrdido()` — Modal de rango para BPM Urdido.

### 6.16 `reportes-resumen-urdido.blade.php`
- `mostrarModalConsultarResumenUrdido()` / `cerrarModalConsultarResumenUrdido()` — Modal de rango para el resumen semanal.

### 6.17 `reportes-urdido-placeholder.blade.php`
Vista placeholder para reportes aún no implementados.

### 6.18 `BPM-Urdido/index.blade.php`
Grid de encabezados BPM con modales de crear/editar/eliminar y filtros por status.
- `applyFilters() / updateFilterButtons()` — Filtrado por status.
- `fillRecibe / fillEntrega / fillEditRecibe / fillAutoriza(select)` — Autollenan clave a partir del nombre seleccionado.
- `fillMaquina(select)` — Setea la máquina seleccionada (define qué actividades cargará la línea).
- `selectRow(row, id) / enableButtons() / disableButtons()` — Selección de fila y habilitación de botones.
- `openCreateModal / closeCreateModal` — Modal de alta (POST resource `urd-bpm`).
- `openChecklist()` — Navega a `urd-bpm-line.index` del folio.
- `openEditModal / closeEditModal` — Edición del header (PUT `urd-bpm`).
- `openDeleteModal / closeDeleteModal` — Eliminación (DELETE `urd-bpm`).

### 6.19 `Urdido-BPM-Line/index.blade.php`
Checklist de actividades por folio con botones de tri-estado y flujo de autorización.
- `toggleActividad(btn)` — Cicla el valor de la actividad (0/1/2) y hace POST a `urd-bpm-line.toggle`.
- `validarYTerminar()` — Valida y dispara `terminar` (PATCH). Autorizar/Rechazar se invocan vía formularios PATCH (`urd-bpm-line.autorizar` / `.rechazar`).

### 6.20 `urd-actividades-bpm/index.blade.php`
Catálogo de actividades BPM con modales.
- `updateTopButtonsState / clearSelection / selectRow(row)` — Selección de fila.
- `handleTopEdit / handleTopDelete` — Acciones de barra superior.
- `openUrdModal / closeUrdModal(modalId)` — Apertura/cierre de modales.
- `openEditModal / openEditModalDirect(key, orden, actividad, maquina)` — Carga datos al modal de edición (PUT resource).
- `deleteActivity(key)` — Confirma y elimina (DELETE resource, SweetAlert).

### 6.21 `catalogosurdido/catalago-julios.blade.php`
Catálogo de julios (CRUD vía JSON contra `catalogos-julios`).
- `getSelectedJulioData()` — Lee datos de la fila seleccionada.
- `selectRow(row) / enableButtons() / disableButtons() / showSelectionWarning()` — Selección.
- `editSelected / deleteSelected / editarJulios / eliminarJulios / agregarJulios` — Disparadores de acciones.
- `openCreateModal / openEditModal(julioId) / openDeleteModal(julioId) / openJulioModal(mode,data)` — Modales.
- `saveJulio(data, julioId)` — POST/PUT a `catalogos-julios`.
- `confirmDeleteJulio / deleteJulio(julioId)` — DELETE a `catalogos-julios/{id}`.
- `filtrarJulios() / limpiarFiltrosJulios()` — Filtro por No. Julio en cliente.

### 6.22 `catalogosurdido/catalago-maquinas.blade.php`
Catálogo de máquinas (CRUD vía JSON contra `catalogo-maquinas`).
- `selectRow(row, maquinaId) / enableButtons() / disableButtons()` — Selección.
- `editSelected / deleteSelected` — Acciones.
- `openCreateModal / openEditModal(maquinaId) / closeFormModal / openDeleteModal / closeDeleteModal` — Modales.
- `handleSubmit(event)` — POST/PUT a `catalogo-maquinas`.
- `deleteMaquina()` — DELETE a `catalogo-maquinas/{maquinaId}`.
- `filtrarMaquinas() / limpiarFiltrosMaquinas()` — Filtro en cliente.

---

## 7. JS dedicado

No existen archivos `.js` dedicados al ámbito Urdido en `resources/js/`. Toda la lógica de cliente vive en bloques `<script>` inline de los blades (sección 6). El proyecto provee utilidades globales (`window.http`, `window.notify`) desde `bootstrap.js`; las vistas de Urdido aún usan mayormente `fetch(...)` directo y SweetAlert/Toast (candidatos a migración según el plan de auditoría descrito en CLAUDE.md).

---

## 8. Lógica de negocio y reglas

### 8.1 Identificación de máquina (MC Coy / Karl Mayer)
El campo `MaquinaId` es texto. La función `extractMcCoyNumber()` (replicada en varios controladores) deriva: "Mc Coy 1/2/3" → 1/2/3 y "Karl Mayer" → 4. La etiqueta de reporte es "MC1/MC2/MC3/KM". **Karl Mayer no tiene fase de engomado**: en edición/finalización se omite la sincronización con Engomado, y exige `Vueltas` y `Diametro` por registro para poder finalizar.

### 8.2 Prioridades de programación
- Las órdenes activas (`ACTIVE_STATUSES`: Programado, En Proceso, Parcial) se ordenan por `Prioridad` (o `CreatedAt` como fallback).
- `subir/bajarPrioridad` intercambian `CreatedAt` dentro del mismo MC Coy; el drag-and-drop usa `intercambiarPrioridad` (swap del campo `Prioridad` global).
- Al cancelar una orden se anula su `Prioridad` y se recalculan las del resto (`recalcularPrioridades`).
- **Límite de 2 órdenes "En Proceso" por máquina** (MC Coy/Karl Mayer): aplicado en `verificarOrdenEnProceso`, `actualizarStatus` y `transitionToEnProceso`.

### 8.3 Flujo de status de la orden
Programado → (al abrir producción) En Proceso → (captura) → Finalizado. *Parcial* es un estado intermedio que también permite finalizar. **Cancelar** una orden de urdido tiene efectos colaterales: borra `UrdProduccionUrdido` del folio, cancela la orden de Engomado homóloga (mismo Folio) y borra su `EngProduccionEngomado`.

### 8.4 Sincronización de registros de producción
- `ensureProductionRecordsExist` crea un registro `UrdProduccionUrdido` por cada julio esperado (`UrdJuliosOrden.Julios`), agrupando por `Hilos`. Crea faltantes (con clave/nombre/turno del usuario, metros de la orden y fecha de hoy) y elimina sobrantes priorizando: sin `NoJulio`, luego sin `KgNeto`, luego más antiguos.
- Al editar julios/hilos en *En Proceso* se crean/eliminan registros de producción proporcionalmente; al cambiar `Hilos` se propaga a producción por Folio.

### 8.5 Cálculos
- **Kg Neto** = `KgBruto − Tara` (recalculado en `actualizarKgBruto` y `actualizarJulioTara`). Límite Kg Neto Urdido = **700 kg**.
- **Reporte Roturas x Millón**: `metros_julio = Σmetros / total_julios`; `hilos_julio = Σhilos / total_julios`; `metros_orden = metros_julio × total_julios`; `millon_metros = (metros_orden × hilos_julio) / 1.000.000`; `total_roturas = hilatura + maquina + operacion + transferencia`.
- **Tiempo de producción (Kaizen)**: minutos entre HoraInicial y HoraFinal, sumando 1440 si cruza medianoche (Turno 3).
- **Resumen semanal**: agrupación por semana ISO (`W-o`) con promedios de peso, metros y cuenta por julio.

### 8.6 Validaciones de finalización
La orden no se finaliza si: el status no es En Proceso/Parcial, existen Kg Neto negativos, fallan las horas (`validarHorasRegistros`), o (Karl Mayer) faltan Vueltas/Diámetro. Al finalizar se eliminan registros sin horas, se marca `Finalizar=1`, se aplica la fecha de cierre mensual y se setea `FechaFinaliza`.

### 8.7 Permisos y control de edición
- **Programar Urdido**: `actualizarStatus` y `guardarObservaciones` exigen área "Supervisores" (403 si no). El intercambio/actualización de prioridades está abierto a cualquier usuario con acceso.
- **Editar Órdenes**: `puedeEditar` = el `puesto` del usuario contiene "supervisor" (no redirige; solo bloquea acciones).
- **Producción**: `userCan('modificar','Producción Urdido')` vía `ensureUserCanEdit()` del trait.
- **BPM**: las acciones de autorización/rechazo requieren que el `SYSUsuario` tenga "supervisor" en `puesto` o `area`.
- El campo de permiso de `SYSRoles` conserva el typo intencional **`reigstrar`**.

### 8.8 Auditoría
Cada edición de campo de orden (y su sincronización a Engomado) se registra en `AuditoriaUrdEng` con formato `Campo: valorAnterior -> valorNuevo`, usuario y fecha (`AuditoriaUrdEng::registrar`).

### 8.9 Integraciones
- **Telegram**: `actualizarCalidad` notifica al módulo `UrdidoCalidad` (chat ids vía `SYSMensaje::getChatIdsPorModulo`) el resultado de calidad (Aprobado/Rechazado/Con observaciones).
- **Excel (maatwebsite/excel)**: exports `KaizenExport`, `RoturasMillonExport`, `BpmUrdidoExport`, `ReportesUrdidoExport`, `ReporteResumenSemanalUrdidoExport`. Los 4 primeros además **se respaldan en una ruta de red** (`reports_urdido`, UNC a `192.168.2.11`) vía `guardarReporteEnRed`.
- **PDF (dompdf)**: `PDFController@generarPDFUrdidoEngomado` genera el PDF de la orden de urdido/engomado, usado por la pantalla de producción y por la reimpresión.
- **Folios**: el folio BPM se genera con `FolioHelper` (módulo "Urdido BPM", longitud 3) — preview en `index`, definitivo en `store`.
