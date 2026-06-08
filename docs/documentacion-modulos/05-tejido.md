# Tejido

> Generado automáticamente — documentación detallada del módulo

---

## 1. Propósito del módulo

El ámbito **Tejido** agrupa la operación del salón de telares de la planta textil. Da soporte a la captura, consulta y reportería de la producción de tejido y a la configuración de la secuencia de telares por proceso. Se apoya en el programa de producción de Planeación (`ReqProgramaTejido`) como fuente del "qué se está tejiendo" y produce información operativa para piso, supervisión y planeación.

Submódulos que abarca:

- **Inventario de Telas (telares)** — panel del estado actual de cada telar (orden en proceso, siguiente orden, julios montados) por familia de máquina: Jacquard (Smit/Sulzer), Itema (viejo/nuevo) y Karl Mayer. `InventarioTelas/TelaresController`.
- **Inventario de Trama (requerimientos de hilo)** — captura de requerimientos de consumo de trama por telar/calibre/fibra/color y su consulta/cambio de estado (incluye notificación a Telegram al "Solicitar"). `InventarioTrama/NuevoRequerimientoController`, `InventarioTrama/ConsultarRequerimientoController`.
- **Cortes de Eficiencia** — captura por turno y por horario (3 cortes) de RPM y eficiencia por telar, con finalización de folio, PDF/Excel y notificación a Telegram. `CortesEficiencia/CortesEficienciaController`.
- **Marcas Finales** — captura por turno de marcas, horas y paros (trama, pie, rizo, otros) por telar, con reporte por fecha, PDF/Excel y Telegram. `MarcasFinales/MarcasController`.
- **Producción de Reenconado (cabezuela)** — captura de producción de reenconado con cálculo de capacidad y eficiencia. `ProduccionReenconado/ProduccionReenconadoCabezuelaController`.
- **Reportes** — Inventario de Telas (5 días), Promedio de Paros y Eficiencia, Marcas Finales (eficiencias), Saldos 2026 y RPM Semanal. `Reportes/*`.
- **Configuración de secuencias** — orden de telares (drag & drop) para cada proceso: Inventario de Telas, Inventario de Trama, Corte de Eficiencia y Marcas Finales. `Configuracion/Secuencia*`.

Rol en el flujo productivo: Tejido consume el **programa de tejido** (qué orden va en cada telar, calibres, fibras, colores, fechas) y registra la **realidad de piso** (consumos de trama, RPM/eficiencia, marcas y paros). Esa realidad alimenta tableros y reportes que retroalimentan a planeación, urdido/engomado y atadores.

---

## 2. Rutas

Archivo: `routes/modules/tejido.php`. Todas las rutas se cargan bajo el grupo autenticado (middleware `auth`) del dispatcher `routes/web.php`. No hay verificaciones `userCan()` en los controllers del ámbito; el control de acceso es por el menú/módulo y el middleware de auth. Algunas acciones verifican el **puesto** del usuario (`supervisor`) en código (no es el sistema de permisos por módulo).

### 2.1 Vista índice y reportes

| Método | URI | Controller@método | Nombre |
|---|---|---|---|
| GET | `/tejido/{moduloPrincipal?}` | `UsuarioController@showSubModulos` | `tejido.index` |
| GET | `/tejido/reportes` | Closure → `view modulos.tejido.reportes.index` | `tejido.reportes.index` |
| GET | `/tejido/reportes/inv-telas` | `ReporteInvTelasController@index` | `tejido.reportes.inv-telas` |
| GET | `/tejido/reportes/inv-telas/excel` | `ReporteInvTelasController@exportarExcel` | `tejido.reportes.inv-telas.excel` |
| GET | `/tejido/reportes/inv-telas/pdf` | `ReporteInvTelasController@exportarPdf` | `tejido.reportes.inv-telas.pdf` |
| GET | `/tejido/reportes/promedio-paros-eficiencia` | `PromedioParosEficienciaController@index` | `tejido.reportes.promedio-paros-eficiencia` |
| GET | `/tejido/reportes/promedio-paros-eficiencia/excel` | `PromedioParosEficienciaController@exportarExcel` | `tejido.reportes.promedio-paros-eficiencia.excel` |
| GET | `/tejido/reportes/marcas-finales` | `ReporteMarcasFinalesController@index` | `tejido.reportes.marcas-finales` |
| GET | `/tejido/reportes/marcas-finales/excel` | `ReporteMarcasFinalesController@exportarExcel` | `tejido.reportes.marcas-finales.export` |
| GET | `/tejido/reportes/saldos-2026` | `SaldosController@index` | `tejido.reportes.saldos-2026` |
| GET | `/tejido/reportes/saldos-2026/excel` | `SaldosController@exportarExcel` | `tejido.reportes.saldos-2026.excel` |
| GET | `/tejido/reportes/rpm-semanal` | `ReporteRpmSemanalController@index` | `tejido.reportes.inv-trama` |
| GET | `/tejido/reportes/rpm-semanal/excel` | `ReporteRpmSemanalController@exportarExcel` | `tejido.reportes.inv-trama.excel` |

### 2.2 Navegación / redirecciones

| Método | URI | Destino | Nombre |
|---|---|---|---|
| GET | `/tejido/configurar/{serie?}` (205) | `UsuarioController@showSubModulosConfiguracion` | `tejido.configurar` |
| GET | `/tejido/marcasfinales/{moduloPadre?}` (202) | redirect → `/modulo-marcas/consultar` | `tejido.marcas.finales` |
| GET | `/tejido/invtrama` | `ConsultarRequerimientoController@index` | `tejido.inventario` |
| GET/redir | `/tejido/inventario` | redirect 301 → `/tejido/invtrama` | — |
| GET | `/tejido/cortesdeeficiencia/{moduloPadre?}` (206) | redirect → `/modulo-cortes-de-eficiencia/consultar` | `tejido.cortes.eficiencia` |
| redirect 301 | `/tejido/invtelas`, `/invtelas/jacquard`, `/invtelas/itema`, `/invtelas/karlmayer` | hacia `/tejido/inventario-telas/*` | — |
| redirect 301 | `/tejido/invtrama/nuevorequerimiento`, `/consultarrequerimiento` | hacia `/tejido/inventario/trama/*` | — |
| redirect 301 | `/tejido/produccionreenconadocabezuela` | `/tejido/produccion-reenconado` | — |
| redirect 301 | `/tejido/configurar/secuenciainvtelas`, `/secuenciainvtrama` | hacia `/tejido/secuencia-*` | — |

### 2.3 Configuración de secuencias

| Método | URI | Controller@método | Nombre |
|---|---|---|---|
| GET | `/tejido/configurar/secuenciamarcasfinales` | `SecuenciaMarcasFinalesController@index` | `tejido.secuencia-marcas-finales.index` |
| POST | `/tejido/configurar/secuenciamarcasfinales` | `@store` | `tejido.secuencia-marcas-finales.store` |
| POST | `/tejido/configurar/secuenciamarcasfinales/orden` | `@updateOrden` | `tejido.secuencia-marcas-finales.orden` |
| PUT | `/tejido/configurar/secuenciamarcasfinales/{id}` | `@update` | `tejido.secuencia-marcas-finales.update` |
| DELETE | `/tejido/configurar/secuenciamarcasfinales/{id}` | `@destroy` | `tejido.secuencia-marcas-finales.destroy` |
| GET/POST/POST/PUT/DELETE | `/tejido/configurar/secuenciacortedeeficiencia[...]` | `SecuenciaCorteEficienciaController@{index,store,updateOrden,update,destroy}` | `tejido.secuencia-corte-eficiencia.*` |
| GET/POST/POST/PUT/DELETE | `/tejido/secuencia-inv-telas[...]` | `SecuenciaInvTelasController@{index,store,updateOrden,update,destroy}` | `tejido.secuencia-inv-telas.*` |
| GET/POST/POST/PUT/DELETE | `/tejido/secuencia-inv-trama[...]` | `SecuenciaInvTramaController@{index,store,updateOrden,update,destroy}` | `tejido.secuencia-inv-trama.*` |

### 2.4 Inventario de Telas

| Método | URI | Controller@método | Nombre |
|---|---|---|---|
| GET (view) | `/tejido/inventario-telas` | `view modulos/tejido/inventario-telas` | `tejido.inventario.telas` |
| GET | `/tejido/inventario-telas/jacquard` | `TelaresController@inventarioJacquard` | `tejido.inventario.jacquard` |
| GET | `/tejido/inventario-telas/itema` | `TelaresController@inventarioItema` | `tejido.inventario.itema` |
| GET | `/tejido/inventario-telas/karl-mayer` | `TelaresController@inventarioKarlMayer` | `tejido.inventario.karl_mayer` |
| GET | `/tejido/jacquard-sulzer/{telar}` | `TelaresController@mostrarTelarSulzer` | `tejido.mostrarTelarSulzer` |
| GET | `/ordenes-programadas-dinamica/{telar}` | `TelaresController@obtenerOrdenesProgramadas` | `ordenes.programadas` |
| GET | `/api/telares/proceso-actual/{telarId}` | `TelaresController@procesoActual` | — |
| GET | `/api/telares/siguiente-orden/{telarId}` | `TelaresController@siguienteOrden` | — |

### 2.5 Inventario de Trama (requerimientos)

Existen dos juegos de rutas equivalentes que apuntan a los mismos controllers (prefijo `/tejido/inventario/trama/*` y prefijo plano `/modulo-nuevo-requerimiento/*`, `/modulo-consultar-requerimiento/*`).

| Método | URI (representativa) | Controller@método | Nombre |
|---|---|---|---|
| GET | `/tejido/inventario/trama/nuevo-requerimiento` | `NuevoRequerimientoController@index` | `tejido.inventario.trama.nuevo.requerimiento` |
| POST | `/tejido/inventario/trama/nuevo-requerimiento` | `@guardarRequerimientos` | `...store` |
| GET | `/tejido/inventario/trama/nuevo-requerimiento/turno-info` | `@getTurnoInfo` | `...turno.info` |
| GET | `/tejido/inventario/trama/nuevo-requerimiento/en-proceso` | `@enProcesoInfo` | `...enproceso` |
| POST | `/tejido/inventario/trama/nuevo-requerimiento/actualizar-cantidad` | `@actualizarCantidad` | `...actualizar.cantidad` |
| GET | `.../buscar-articulos`, `.../buscar-fibras`, `.../buscar-codigos-color`, `.../buscar-nombres-color` | `@buscarArticulos`, `@buscarFibras`, `@buscarCodigosColor`, `@buscarNombresColor` | `...buscar.*` |
| GET | `/modulo-nuevo-requerimiento/calibres`, `/fibras`, `/colores` | `@getCalibres`, `@getFibras`, `@getColores` | `modulo.nuevo.requerimiento.*` |
| GET | `/tejido/inventario/trama/consultar-requerimiento` | `ConsultarRequerimientoController@index` | `tejido.inventario.trama.consultar.requerimiento` |
| GET | `/tejido/inventario/trama/consultar-requerimiento/{folio}/resumen` | `@resumen` | `...resumen` |
| GET | `/modulo-consultar-requerimiento/{folio}` | `@show` | `modulo.consultar.requerimiento.show` |
| POST | `/modulo-consultar-requerimiento/{folio}/status` | `@updateStatus` | `modulo.consultar.requerimiento.status` |

### 2.6 Cortes de Eficiencia (`/modulo-cortes-de-eficiencia/*`)

| Método | URI | Controller@método | Nombre |
|---|---|---|---|
| GET | `/modulo-cortes-de-eficiencia` | `CortesEficienciaController@index` | `cortes.eficiencia` |
| GET | `/modulo-cortes-de-eficiencia/consultar` | `@consultar` | `cortes.eficiencia.consultar` |
| GET | `.../turno-info` | `@getTurnoInfo` | `cortes.eficiencia.turno.info` |
| GET | `.../datos-programa-tejido` | `@getDatosProgramaTejido` | `cortes.eficiencia.datos.programa.tejido` |
| GET | `.../datos-telares` | `@getDatosTelares` | `cortes.eficiencia.datos.telares` |
| GET | `.../fallas` | `@getFallasCe` | `cortes.eficiencia.fallas` |
| GET | `.../generar-folio` | `@generarFolio` | `cortes.eficiencia.generar.folio` |
| POST | `.../guardar-hora` | `@guardarHora` | `cortes.eficiencia.guardar.hora` |
| POST | `.../guardar-tabla` | `@guardarTabla` | `cortes.eficiencia.guardar.tabla` |
| POST | `/modulo-cortes-de-eficiencia` | `@store` | `cortes.eficiencia.store` |
| GET | `.../{id}/pdf` | `@pdf` | `cortes.eficiencia.pdf` |
| PUT | `.../{id}/actualizar-registro` | `@actualizarRegistro` | `cortes.eficiencia.actualizar.registro` |
| GET | `.../{id}` | `@show` | `cortes.eficiencia.show` |
| PUT | `.../{id}` | `@update` | `cortes.eficiencia.update` |
| POST | `.../{id}/finalizar` | `@finalizar` | `cortes.eficiencia.finalizar` |
| GET | `.../visualizar/{folio}` | `@visualizar` | `cortes.eficiencia.visualizar` |
| GET | `.../visualizar-folio/{folio}` | `@visualizarFolio` | `cortes.eficiencia.visualizar.folio` |
| POST | `.../visualizar/exportar-excel` | `@exportarVisualizacionExcel` | `cortes.eficiencia.visualizar.excel` |
| POST | `.../visualizar/descargar-pdf` | `@descargarVisualizacionPDF` | `cortes.eficiencia.visualizar.pdf` |
| POST | `.../visualizar/notificar-telegram` | `@notificarTelegram` | `cortes.eficiencia.visualizar.telegram` |
| POST | `.../visualizar/notificar-telegram-imagen` | `@notificarTelegramImagen` | `cortes.eficiencia.visualizar.telegram.imagen` |

### 2.7 Marcas Finales (`/modulo-marcas/*`)

| Método | URI | Controller@método | Nombre |
|---|---|---|---|
| GET | `/modulo-marcas` | `MarcasController@index` | `marcas.nuevo` |
| GET | `/modulo-marcas/consultar` | `@consultar` | `marcas.consultar` |
| POST | `/modulo-marcas/generar-folio` | `@generarFolio` | `marcas.generar.folio` |
| GET | `/modulo-marcas/obtener-datos-std` | `@obtenerDatosSTD` | `marcas.datos.std` |
| POST | `/modulo-marcas/store` | `@store` | `marcas.store` |
| GET | `/modulo-marcas/visualizar/{folio}` | `@visualizarFolio` | `marcas.visualizar` |
| GET | `/modulo-marcas/reporte` | `@reporte` | `marcas.reporte` |
| POST | `/modulo-marcas/reporte/exportar-excel` | `@exportarExcel` | `marcas.reporte.excel` |
| POST | `/modulo-marcas/reporte/descargar-pdf` | `@descargarPDF` | `marcas.reporte.pdf` |
| POST | `/modulo-marcas/reporte/notificar-telegram` | `@notificarTelegram` | `marcas.reporte.telegram` |
| GET | `/modulo-marcas/{folio}` (excluye `reporte`) | `@show` | `marcas.show` |
| PUT | `/modulo-marcas/{folio}/actualizar-registro` | `@actualizarRegistro` | `marcas.actualizar.registro` |
| PUT | `/modulo-marcas/{folio}` | `@update` | `marcas.update` |
| POST | `/modulo-marcas/{folio}/finalizar` | `@finalizar` | `marcas.finalizar` |
| POST | `/modulo-marcas/{folio}/reabrir` | `@reabrirFolio` | `marcas.reabrir` |

> Nota: la ruta `marcas.visualizar` apunta a `@visualizarFolio` (abre la vista de captura en solo lectura), mientras que `@visualizar` (tabla de los 3 turnos) no tiene ruta directa registrada en este archivo.

### 2.8 Producción de Reenconado

| Método | URI | Controller@método | Nombre |
|---|---|---|---|
| GET | `/tejido/produccion-reenconado` | `ProduccionReenconadoCabezuelaController@index` | `tejido.produccion.reenconado` |
| POST | `/tejido/produccion-reenconado` | `@store` | `tejido.produccion.reenconado.store` |
| POST | `/tejido/produccion-reenconado/generar-folio` | `@generarFolio` | `tejido.produccion.reenconado.generar-folio` |
| GET | `/tejido/produccion-reenconado/calibres` `/fibras` `/colores` | `@getCalibres` `@getFibras` `@getColores` | `tejido.produccion.reenconado.{calibres,fibras,colores}` |
| PUT | `/tejido/produccion-reenconado/{folio}` | `@update` | `tejido.produccion.reenconado.update` |
| DELETE | `/tejido/produccion-reenconado/{folio}` | `@destroy` | `tejido.produccion.reenconado.destroy` |
| PATCH | `/tejido/produccion-reenconado/{folio}/cambiar-status` | `@cambiarStatus` | `tejido.produccion.reenconado.cambiar-status` |
| GET | `/produccion/reenconado-cabezuela` | `@index` | `produccion.reenconado_cabezuela` |
| POST | `/produccion/reenconado-cabezuela` | `@store` | `produccion.reenconado_cabezuela.store` |

---

## 3. Controllers

### 3.1 `Configuracion/Secuencia*` — controladores de secuencia (4 archivos, patrón CRUD idéntico)

Los cuatro controllers gestionan el **orden de telares** por proceso mediante una tabla de secuencia. Estructura común: `index` (vista con registros ordenados), `store`, `update`, `destroy` (todos JSON), `updateOrden` (persiste el reordenamiento drag & drop). Todos envuelven en `try/catch`, registran con `Log::error` y devuelven `{success, message[, errors]}`. No usan `userCan`, folios ni turnos.

**`SecuenciaInvTelasController`** (`app/Http/Controllers/Tejido/Configuracion/SecuenciaInvTelas/SecuenciaInvTelasController.php`) — modelo `InvSecuenciaTelares` (conexión `sqlsrv`, tabla `dbo.InvSecuenciaTelares`).
- `index()` → vista `modulos.tejido.secuencia.inv-telas`, ordena por `Secuencia`.
- `store(Request)` → valida `NoTelar` (int, req), `TipoTelar` (string ≤50, req), `Secuencia` (int, nullable), `Observaciones` (string ≤500, nullable). Si `Secuencia ≤ 0` calcula `max(Secuencia)+1`. Setea `Created_At=now()`.
- `update(Request,$id)` → `findOrFail`, mismas reglas (Secuencia requerido), setea `Updated_At=now()`.
- `destroy($id)` → `findOrFail` + delete.
- `updateOrden(Request)` → body `orden[*].{Id, Secuencia}`; recorre y actualiza `Secuencia` + `Updated_At`.

**`SecuenciaInvTramaController`** (`.../SecuenciaInvTrama/SecuenciaInvTramaController.php`) — modelo `InvSecuenciaTrama` (`sqlsrv`, `dbo.InvSecuenciaTrama`). Igual al anterior pero sin columnas `Observaciones` ni `Created_At/Updated_At`; reglas: `NoTelar`, `TipoTelar`, `Secuencia` (todas requeridas). Vista `modulos.tejido.secuencia.inv-trama`. `updateOrden` por `{Id, Secuencia}`.

**`SecuenciaCorteEficienciaController`** (`.../SecuenciaCorteEficiencia/SecuenciaCorteEficienciaController.php`) — modelo `InvSecuenciaCorteEf` (`sqlsrv`, `dbo.InvSecuenciaCorteEf`, PK `NoTelarId`). Reglas: `NoTelarId` (int), `SalonTejidoId` (string ≤100), `Orden` (int). En `store` calcula `Orden=max(Orden)+1` si viene nulo/≤0 y **valida unicidad** de `NoTelarId` (lanza `ValidationException` si ya existe). `update` valida que no exista otro registro con el mismo `NoTelarId`. Vista `modulos.tejido.secuencia.corte-eficiencia`. `updateOrden` por `{NoTelarId, Orden}`.

**`SecuenciaMarcasFinalesController`** (`.../SecuenciaMarcasFinales/SecuenciaMarcasFinalesController.php`) — modelo `InvSecuenciaMarcas` (`sqlsrv`, `dbo.InvSecuenciaMarcas`, PK `NoTelarId`). Idéntico a Corte de Eficiencia (validación de unicidad de telar incluida). Vista `modulos.tejido.secuencia.marcas-finales`. `updateOrden` por `{NoTelarId, Orden}`.

---

### 3.2 `InventarioTelas/TelaresController`

Archivo: `app/Http/Controllers/Tejido/InventarioTelas/TelaresController.php`. Todas las consultas usan la conexión por defecto **`sqlsrv`** (Query Builder sobre `ReqProgramaTejido`, `InvSecuenciaTelares`, `AtaMontadoTelas`). No usa folios/turnos ni `userCan`.

**Públicas:**

- `mostrarTelarSulzer($telar)` (l.15) — Vista individual de un telar. Determina salón, resuelve candidatos (ITEMA 3XX↔1XX), busca el telar **en proceso** (`fetchTelarEnProceso`) y la **siguiente orden** (`fetchSiguienteOrden` o fallback `fetchPrimeraOrdenDisponible`). Devuelve `view('modulos/tejido/telares/telar-informacion-individual', compact('telar','datos','ordenSig','tipo'))`.
- `obtenerOrdenesProgramadas($telar)` (l.60) — Lista todas las órdenes (en proceso y programadas) de un telar desde `ReqProgramaTejido` ordenadas por `EnProceso desc, FechaInicio asc`. Devuelve `view('modulos/tejido/telares/ordenes-programadas')`.
- `inventarioJacquard()` (l.90) — Construye el tablero del salón Jacquard: toma la secuencia (`getSecuenciaTelares(['JACQUARD'])`), y por telar arma `{telarData, ordenSig}` más los últimos julios rizo/pie. Vista `modulos/tejido/inventario-telas/inventario-telas` con `tipoInventario='jacquard'`.
- `inventarioItema()` (l.145) — Igual para ITEMA/SMIT; usa candidatos 3XX/1XX y, si el "en proceso" se encontró como 1XX, fuerza mostrar el número 3XX. `tipoInventario='itema'`.
- `inventarioKarlMayer()` (l.217) — Igual para `KARL MAYER`. `tipoInventario='karl-mayer'`.
- `procesoActual($telarId)` (l.267) — **API JSON**. Devuelve cuenta/calibre/fibra de rizo y pie del registro `EnProceso=1`. 400 si el telar no es reconocido.
- `siguienteOrden($telarId)` (l.295) — **API JSON**. Localiza el registro en proceso y devuelve la siguiente orden (cuenta/calibre/fibra rizo y pie). 404 si no hay registro en proceso.

**Privadas relevantes:**
- `determinarTipoSalon($telar,$strict=false)` — mapea rango de telar a salón (200-215 JACQUARD, 299-320 ITEMA, 303-306 KARL MAYER). En modo `strict` devuelve `null` si no encaja.
- `resolverCandidatosTelar(int $telar, string $tipoSalon)` — para ITEMA/SMIT con telar 3XX añade el candidato `100 + telar%100` (ej. 318→118).
- `selectColsProceso()` — lista completa de columnas/alias del registro en proceso (orden, flog, cliente, cuentas, calibres, fibras, combinaciones C1-C5, fechas, etc.).
- `fetchTelarEnProceso($salones,$candidatos)` — registro `EnProceso=1` priorizando el primer candidato, luego `FechaInicio desc, Id desc`.
- `fetchSiguienteOrden(...)` / `fetchSiguienteOrdenConCandidatos(...)` — siguiente orden por `Posicion` mayor a la actual (tratando `EnProceso` NULL u 0 como disponible); si no, primera disponible priorizando las que tienen `Posicion`.
- `getSecuenciaTelares(array $tipos)` — `pluck('NoTelar')` de `InvSecuenciaTelares` ordenado por `Secuencia`.
- `fetchPrimeraOrdenDisponible(...)` / `...ConCandidatos(...)` — cascada: por `Posicion`, luego por `FechaInicio`, luego sin restricción.
- `ultimoJulioRizoPiePorTelar($telarOrCandidatos)` — último `NoJulio` Rizo y Pie desde `AtaMontadoTelas` (orden `CAST(Fecha AS DATE) desc, Id desc`).
- `objTelarVacio($numeroTelar)` — objeto stub cuando no hay nada en proceso.

---

### 3.3 `InventarioTrama/NuevoRequerimientoController`

Archivo: `.../InventarioTrama/NuevoRequerimientoController.php`. Modelos `TejTrama` (cabecera) y `TejTramaConsumos` (detalle). Usa `TurnoHelper`, `FolioHelper` (serie `Trama`, ancho 5) y `AuditoriaHelper`. Consulta de catálogos contra la conexión **`sqlsrv_ti`** (AX: `InventTable`, `ConfigTable`, `InventSum`, `InventDim`, `InventColor`).

**Públicas:**

- `index()` (l.27) — Construye el ViewModel `$vm` para `modulos.inventario-trama.nuevo-requerimiento`. Modo **edición** si llega `?folio=` (carga cabecera + consumos por telar vía `cargarEdicionPorFolio` y completa con `ReqProgramaTejido` en proceso); modo **nuevo** si no (recorre telares de `InvSecuenciaTrama` JACQUARD+ITEMA, arma filas TRA + C1..C5 desde el programa en proceso y la "siguiente orden"). Folio inicial: el editado, o el "En Proceso", o `FolioHelper::obtenerFolioSugerido('Trama',5)`.
- `guardarRequerimientos(Request)` (l.151) — **Persistencia principal** (JSON). En transacción: resuelve folio (`resolverFolio`), por cada `consumos[]` ejecuta `upsertConsumoNormalizado`; si es nuevo y sin filas inserta consumo mínimo. Registra auditoría (`AuditoriaHelper::logEvento('TejTrama', INSERT|UPDATE, ...)`). Devuelve `{success, folio, turno, consumos}`.
- `getTurnoInfo()` (l.222) — JSON `{turno, descripcion, folio sugerido}`.
- `enProcesoInfo()` (l.232) — JSON `{exists, folio}` del primer `TejTrama` en `Status='En Proceso'`.
- `actualizarCantidad(Request)` (l.239) — valida `id` (int) y `cantidad` (numeric ≥0). `UPDATE` SQL directo sobre `TejTramaConsumos` (compatibilidad SQL Server 2008). Audita `TejTramaConsumos UPDATE`.
- `getCalibres()` (l.286) — `sqlsrv_ti.InventTable` con `ItemGroupId='HILO DIREC'`, `DATAAREAID='PRO'`, distinct `ItemId`.
- `getFibras(Request)` (l.306) — `sqlsrv_ti` join `InventSum`+`InventDim` por `ItemId` con `PhysicalInvent>0`, devuelve `ConfigId`.
- `getColores(Request)` (l.336) — `sqlsrv_ti` join `InventSum`+`InventDim`+`InventColor` con `PhysicalInvent>0`, devuelve `{InventColorId, Name}`.
- `buscarArticulos`/`buscarFibras`/`buscarCodigosColor` (l.371-385) — **DEPRECATED**, delegan a `getCalibres`/`getFibras`/`getColores`.
- `buscarNombresColor(Request)` (l.389) — autocompletado de `ColorTrama` desde `TejTramaConsumos` (LIKE, limit 50).

**Privadas relevantes:** `cargarEdicionPorFolio` (cabecera+detalle agrupado por telar); `fetchProgramaEnProceso` (programa `EnProceso=1`, maneja ITEMA con SMIT y fallback 3XX→1XX); `mapTelarData` (normaliza el programa a array que pinta el blade); `buildRowsFromTelarData` (genera filas TRA + C1..C5 evitando calibres duplicados); `buscarOrdenSiguiente` (siguiente orden por `FechaInicio`); `determinarTipoSalon` (299-320 → ITEMA, resto JACQUARD); `resolverFolio` (reusa folio provisto/en proceso con `lockForUpdate`, o crea nuevo con `generarFolioSecuencial`); `upsertConsumoNormalizado` (trunca campos, busca duplicado por clave compuesta con tolerancia `ABS(CalibreTrama-?)<0.01`, update o insert); `asegurarConsumoMinimo`; `generarFolioSecuencial`/`generarFolioSugerido` (FolioHelper serie `Trama`).

---

### 3.4 `InventarioTrama/ConsultarRequerimientoController`

Archivo: `.../InventarioTrama/ConsultarRequerimientoController.php`. Modelos `TejTrama`, `TejTramaConsumos`, `SYSMensaje`.

- `index(Request)` (l.18) — Lista de requerimientos con filtros opcionales `folio` (LIKE), `fecha_inicio`/`fecha_fin` (whereDate sobre `Fecha`), `status`, `turno`. Adjunta los consumos a cada requerimiento. Vista `modulos.inventario-trama.consultar-requerimiento`.
- `show($folio)` (l.79) — **AJAX JSON** con el requerimiento y sus consumos; 404 si no existe.
- `updateStatus(Request,$folio)` (l.102) — valida `status in:En Proceso,Solicitado,Surtido,Cancelado,Creado`. Aplica una **máquina de estados** de transiciones permitidas (ver §8). Al pasar a `Solicitado` dispara `enviarTelegram`. JSON `{success, message}`.
- `resumen($folio)` (l.182) — Vista `modulos.inventario-trama.resumen-articulos` con consumos agrupados por `SalonTejidoId`.
- *(privada)* `enviarTelegram(TejTrama $req)` — envía mensaje a destinatarios `SYSMensaje::getChatIdsPorModulo('InvTrama')` vía `api.telegram.org/sendMessage` (Markdown).

---

### 3.5 `CortesEficiencia/CortesEficienciaController`

Archivo: `.../CortesEficiencia/CortesEficienciaController.php` (1705 líneas). Modelos `TejEficiencia` (cabecera, PK `Folio`), `TejEficienciaLine` (líneas por telar/turno/fecha), `InvSecuenciaCorteEf`, `ReqProgramaTejido`, `TejeFallasCeModel`, `SYSMensaje`. Folios vía `FolioHelper::obtenerSiguienteFolio('CorteEficiencia',4)`. Turno vía `TurnoHelper`. Notificaciones por Telegram (módulo `CorteSEF`). PDF con Dompdf (a4 landscape).

**Públicas:**

- `index(Request)` (l.30) — Vista de captura `modulos.cortes-eficiencia.cortes-eficiencia` con los telares ordenados por `InvSecuenciaCorteEf`. Si existe un folio **no finalizado** y no se está editando uno explícito, redirige a `consultar` con aviso.
- `consultar()` (l.54) — Vista `consultar-cortes-eficiencia` con todos los cortes (eager load `usuario`, `lineas`), `esSupervisor` según puesto. Headers `no-store/no-cache`.
- `getTurnoInfo()` (l.95) — JSON `{turno, descripcion}` (TurnoHelper).
- `getDatosTelares()` (l.122) — JSON por telar con la **última RPM real ≠0** y **última eficiencia real ≠0** (prioridad Horario 3>2>1 sobre los 20 registros más recientes), con fallback a `RpmStd`/`EficienciaSTD`.
- `generarFolio(Request)` (l.198) — Genera folio y **crea inmediatamente** el registro `TejEficiencia` en `Status='En Proceso'` para reservarlo. Reglas de negocio: no permite dos folios para la misma fecha+turno, ni dos folios "en proceso" simultáneos. Usa `DB::transaction` + `lockForUpdate`.
- `store(Request)` (l.303) — valida `folio, fecha, turno, status, usuario, noEmpleado, datos_telares[] (req array), horario1..3`. Verifica conflictos (otro en proceso / mismo turno). `updateOrCreate` de la cabecera y por cada telar `update`/`create` de `TejEficienciaLine` (clave Folio+NoTelarId+Turno+Date). Asigna `SalonTejidoId` desde la secuencia. JSON.
- `update(Request,$id)` (l.468) — valida campos pero **no persiste** (stub que sólo responde éxito).
- `actualizarRegistro(Request,$folio)` (l.500) — **solo supervisores** (chequea `puesto==='supervisor'`, 403 si no). Edita `Date/Turno/Status/numero_empleado/nombreEmpl` de la cabecera y, si cambia Date/Turno, sincroniza `TejEficienciaLine`.
- `finalizar(Request,$id)` (l.592) — pone `Status='Finalizado'`. La notificación automática a Telegram al finalizar está **comentada/desactivada**. Devuelve `pdf_url`.
- `show($id)` (l.666) — JSON de la cabecera + sus líneas (mapeadas con NoTelar, RPM/Eficiencia R1-R3, observaciones, status OB1-3, horarios formateados).
- `pdf($id)` (l.733) — Genera y descarga el PDF del folio (`modulos.cortes-eficiencia.pdf`).
- `getDatosProgramaTejido()` (l.804) — JSON con `VelocidadSTD`/`EficienciaSTD` por telar desde `ReqProgramaTejido` (`EnProceso=1`, fallback al último registro por telar).
- `getFallasCe()` (l.872) — JSON catálogo de fallas (`TejeFallasCe`: `Clave`, `Descripcion`).
- `guardarHora(Request)` (l.896) — valida `folio, turno, horario(1-3), hora, fecha`; setea `Horario1|2|3` en `TejEficiencia` (update o insert por Folio+Turno), SQL Builder directo.
- `visualizar($folio)` (l.957) — Vista `visualizar-cortes-eficiencia` con los 3 turnos de la fecha del folio (`obtenerDatosVisualizacionPorFecha`).
- `visualizarFolio($folio)` (l.983) — Reabre la vista de captura `cortes-eficiencia` en `soloLectura=true`.
- `exportarVisualizacionExcel(Request)` (l.1008) — Excel por `fecha` única o por rango `fecha_inicio/fecha_fin` (`CortesEficienciaExport`). 404 si sin datos.
- `descargarVisualizacionPDF(Request)` (l.1046) — PDF por fecha o rango (vistas `visualizar-cortes-eficiencia-pdf` / `...-rango-pdf`).
- `notificarTelegram(Request)` (l.1329) — Genera el PDF de la fecha (o del folio) y lo envía a Telegram (`enviarReporteTelegramInternal`).
- `notificarTelegramImagen(Request)` (l.1385) — valida imagen (`mimes:jpg,jpeg,png max:10240`), la envía por `sendPhoto` (`enviarReporteCortesImagenTelegram`).

**Privadas relevantes:** `formatearHora` (HH:MM); `obtenerDatosVisualizacionPorRango`/`obtenerDatosVisualizacionPorFecha` (construyen estructura por telar×turno + folios y horarios por turno, usando el más reciente por `updated_at`); `enviarReporteCortesPdfTelegram` (sendDocument, módulo `CorteSEF`, límite 50 MB); `enviarReporteCortesImagenTelegram` (sendPhoto, límite 10 MB); `enviarReporteTelegramInternal` (renderiza PDF y delega el envío); `normalizarFecha`. `guardarTabla(Request)` (l.1592, **pública**) — valida `datos_telares.*` (RPM R1-R3 int, eficiencias numeric, Obs string, StatusOB in:0,1) y hace upsert directo en `TejEficienciaLine` por Folio+NoTelarId+Turno+Date.

---

### 3.6 `MarcasFinales/MarcasController`

Archivo: `.../MarcasFinales/MarcasController.php`. Modelos `TejMarcas` (cabecera, PK `Folio`), `TejMarcasLine` (líneas), `ReqProgramaTejido`, `SYSMensaje`. Folios generados a mano con prefijo `FM####`. PDF Dompdf (a4 landscape). Telegram módulo `MarcasFinales`.

**Públicas:**

- `index(Request)` (l.23) — Vista de captura `modulos.marcas-finales.nuevo-marcas`. Si no llega `?folio=` y hay un folio en `Status='En Proceso'`, redirige a editarlo. Carga la secuencia de telares.
- `consultar()` (l.47) — Vista `marcasFinales` con todos los folios ordenados (En Proceso primero) y `esSupervisor`.
- `generarFolio(Request)` (l.71) — JSON. Requiere `fecha`+`turno`. En `DB::transaction` con `lockForUpdate`: prohíbe folio en proceso, prohíbe duplicado de fecha+turno, calcula `FM` + consecutivo. Devuelve folio/turno/fecha/usuario.
- `obtenerDatosSTD()` (l.171) — JSON con `porcentaje_efi` (=`EficienciaSTD*100`) y salón por telar desde `ReqProgramaTejido`.
- `store(Request)` (l.219) — **Persistencia principal**. Valida folio/fecha/turno; evita duplicado fecha+turno con otro folio. `firstOrNew` cabecera, borra y reinserta `TejMarcasLine` con `Eficiencia` (entero 0-100, prioriza `PorcentajeEfi` capturado, si no STD×100), `Marcas/Horas/Trama/Pie/Rizo/Otros`. Transaccional.
- `show($folio)` (l.371) — JSON cabecera + líneas.
- `update(Request,$folio)` (l.400) — delega a `store`.
- `reabrirFolio($folio)` (l.409) — **solo supervisores**; de `Finalizado`→`En Proceso`.
- `actualizarRegistro(Request,$folio)` (l.449) — **solo supervisores**; edita Date/Turno/Status/empleado y sincroniza `TejMarcasLine`.
- `finalizar($folio)` (l.538) — `Status='Finalizado'` (sin bloqueo por vacíos; la confirmación se hace en frontend).
- `visualizarFolio($folio)` (l.576) — abre `nuevo-marcas` en `soloLectura=true`.
- `visualizar($folio)` (l.600) — Vista `visualizar-marcas` con los 3 turnos de la fecha del folio.
- `reporte(Request)` (l.661) — Vista `reporte-marcas` con hasta 3 tablas (turnos 1-3) por `?fecha=`.
- `exportarExcel(Request)` (l.682) — Excel `MarcasFinalesExport`.
- `descargarPDF(Request)` (l.701) — PDF `reporte-marcas-pdf`.
- `notificarTelegram(Request)` (l.766) — Genera el PDF y lo envía por Telegram (`enviarReporteMarcasPdfTelegram`).

**Privadas relevantes:** `enviarReporteMarcasPdfTelegram` (sendDocument, módulo `MarcasFinales`, límite 50 MB); `obtenerDatosReporte($fechaNorm)` (para cada turno 1-3 toma el folio existente y sus líneas indexadas por telar); `obtenerSecuenciaTelares` (de `InvSecuenciaMarcas` por `Orden`, fallback a `InvSecuenciaTelares`).

---

### 3.7 `ProduccionReenconado/ProduccionReenconadoCabezuelaController`

Archivo: `.../ProduccionReenconado/ProduccionReenconadoCabezuelaController.php`. Modelo `TejProduccionReenconado` (tabla `dbo.TejProduccionReenconado`, PK `Folio`). Catálogos AX en **`sqlsrv_ti`**. Folios serie `Reenconado` ancho 4.

**Públicas:**

- `index()` (l.17) — Vista `modulos.produccion-reenconado-cabezuela` con los últimos 300 registros.
- `getCalibres()` (l.26) — `sqlsrv_ti.InventTable` (HILO DIREC, PRO) distinct `ItemId`.
- `getFibras(Request)` (l.45) — `sqlsrv_ti.ConfigTable` por `ItemId` → `ConfigId`.
- `getColores(Request)` (l.69) — `sqlsrv_ti.InventColor` por `ItemId` → `{InventColorId, Name}`.
- `store(Request)` (l.92) — Dos modos: **modal** (un registro; genera folio consumiendo secuencia, calcula `capacidad=Horas*9.3` y `Eficiencia=Cantidad/capacidad`, status `Creado`); **masivo** (`rows[]` con validación por fila e `insert` en bloque). JSON o redirect según `expectsJson`.
- `generarFolio(Request)` (l.270) — JSON `{folio sugerido, turno, usuario, numero_empleado, fecha}` (no consume secuencia). Fallback `CE0001`/`TEMP-…`.
- `update(Request,$folio)` (l.311) — `findOrFail`, recalcula capacidad/eficiencia, actualiza. JSON.
- `destroy($folio)` (l.381) — elimina. JSON.
- `cambiarStatus(Request,$folio)` (l.393) — **ciclo de estados** `Creado → En Proceso → Terminado → Creado`. JSON.

---

### 3.8 Reportes

**`PromedioParosEficienciaController`** (`Reportes/PromedioParosEficienciaController.php`)
- `index(Request)` (l.16) — Vista `modulos.tejido.reportes.promedio-paros-eficiencia` (sin rango muestra formulario). Resuelve rango con `resolveDateRange`.
- `exportarExcel(Request, PromedioParosEficienciaReportService)` (l.29) — requiere rango (`requireRange:true`); `set_time_limit(300)`; arma el reporte vía el service y descarga `PromedioParosEficienciaExport`.
- *(privada)* `resolveDateRange` — normaliza/valida `fecha_ini`/`fecha_fin` (inicial ≤ final), redirige con error si inválidas.

**`ReporteRpmSemanalController`** (`Reportes/ReporteRpmSemanalController.php`) — inyecta `ReporteRpmSemanalService`.
- `index(Request)` (l.21) — A partir de `?semana=` calcula lunes-domingo (America/Mexico_City) y construye secciones/filas/total. Vista `modulos.tejido.reportes.rpm-semanal`.
- `exportarExcel(Request)` (l.59) — Excel `ReporteRpmSemanalExport` para la semana.

**`ReporteInvTelasController`** (`Reportes/ReporteInvTelasController.php`) — reporte de inventario por telar a 5 días máx. Modelos `TejInventarioTelares`, `ReqProgramaTejido`, `UrdProgramaUrdido`, `EngProgramaEngomado`.
- `index(Request)` (l.72) — valida rango ≤ `MAX_DIAS=5`; arma secciones por familia (JACQUARD SULZER/SMIT, SMIT, ITEMA VIEJO/NUEVO) con celdas coloreadas. Vista `modulos.tejido.reportes.inv-telas` con leyenda de colores.
- `exportarExcel(Request)` (l.117) — `ReporteInvTelasExport`.
- `exportarPdf(Request)` (l.161) — PDF `inv-telas-pdf` (letter portrait) con resumen por sección y logo en base64.
- *(protegidas)* `obtenerDatosReporte`, `construirDias`, `crearFilaTelarBase/Vacia`, `finalizarFilaTelar`, `formatearCalibre`, `aMayusculas`, `construirResumenPdf`, `normalizarCuentas`, `obtenerNoTelaresOrdenados`, `obtenerFallbackReqPorTelar` (cuentas/fibra/calibre desde el programa en proceso), `aplicarFallbackReqPrograma`, `cargarLogoBase64`, `obtenerLeyendaColores`, `resolverColorCeldaInventario` (azul=reservado/julio, naranja=programado, amarillo=pendiente), `obtenerEstadosOrdenesActivas` (cruza urdido/engomado), `obtenerColorEstadoPorStatusOrden`, `desglosarTelaresOrden`, `obtenerAmarilloPorTelarFecha` (pendientes según días de liberación de sesión), `obtenerDiasLiberacion` (`DEFAULT_LIBERAR_DIAS=10.999`), `aplicarColorAmarilloPendiente`, `priorizarColor` (azul>naranja>amarillo), `normalizarTipo`.

**`ReporteMarcasFinalesController`** (`Reportes/ReporteMarcasFinalesController.php`) — reporte "EFICIENCIAS" desde `TejMarcasLine`.
- `index(Request)` (l.16) — preview por día seleccionable (`?fecha_ini/fecha_fin[/dia]`), agrupado por turno→máquina→telar con sumas de marcas/horas/paros. Velocidades por telar desde `ReqTelares`. Vista `modulos.tejido.reportes.reporte-marcas-finales`.
- `exportarExcel(Request)` (l.321) — Excel `ReporteMarcasFinalesExport` (datos por día); archivo `EFICIENCIAS_*.xlsx`.
- *(privadas)* `obtenerVelocidadesPorTelar`, `obtenerPreview`, `obtenerDiasConDatos`, `obtenerPreviewPorDia`, `resolverMaquinaPorTelar` (rangos de telar → Jacquard Sulzer/Smith, Smith Liso, Karl Mayer), `ordenMaquina`, `calcularPromediosPorTelar`, `obtenerDatosPorDia`.

**`SaldosController`** (`Reportes/SaldosController.php`) — "Saldos 2026" desde `ReqProgramaTejido` + `ReqModelosCodificados` + `CatCodificados`.
- `index()` (l.14) — `query()` + finalizados de grupos compartidos + preprocesamiento de grupos. Vista `modulos.tejido.reportes.saldos-2026`.
- `exportarExcel()` (l.23) — `Saldos2026Export`.
- *(privadas)* `fetchFinalizadosEnGrupos` (trae de `CatCodificados` las órdenes ya finalizadas que comparten `OrdCompartida` con activos, marcadas `_finalizado`), `preprocesarGrupos` (agrupa por `OrdCompartida`, marca líder, suma pedidos/saldos/producción/rollos), `query` (join lateral con la última fila por `TamanoClave` de `ReqModelosCodificados`, trae combinaciones C1-C4, cenefas, etc.).

---

## 4. Services y Helpers del ámbito

### 4.1 `PromedioParosEficienciaReportService`
Archivo: `app/Services/Tejido/PromedioParosEficienciaReportService.php`. Construye el reporte cruzando **marcas** (`TejMarcas`/`TejMarcasLine`) y **cortes** (`TejEficiencia`/`TejEficienciaLine`).

- `build($fechaIni,$fechaFin)` — devuelve `{days, metrics, summaries}`.
- *(privadas)* `buildDays` (códigos LU..DO con etiquetas 1T/2T/3T por día); `selectLatestMarcasHeaders` (sólo `Status='Finalizado'`) / `selectLatestCortesHeaders` (toman el folio más reciente por fecha+turno); `indexLatestHeaders`; `fetchMarcasMetrics` (eficiencia y paros trama/urdimbre/rizo/otros + marcas por telar); `fetchCortesMetrics` (RPM = promedio de RpmR1/R2/R3 ≠0); `flattenHeaderMeta`; `mergeMetrics`; `buildSummaries` (promedios por telar de los grupos JACQ/JACQ-SULZ/SMIT/ITEMA); `averageRpm`/`averageCapturedValues` (promedia sólo valores ≠0); `calculateSummaryEfficiency` (**fórmula** `Marcas*100000 / (RPM*60*8)`); `averageSummaryValues`; normalizadores varios; `summaryTelarLookup`; `resolveDayCode`.

### 4.2 `ReporteRpmSemanalService`
Archivo: `app/Services/Tejido/ReporteRpmSemanalService.php`.

- `build($lunesYmd,$domingoYmd)` — lee `TejEficienciaLine` join `TejEficiencia` por rango; calcula RPM real promedio semanal por telar y lo compara contra un **RPM ideal fijo** por telar (constante `RPM_IDEAL_FIJO_POR_TELAR`). Devuelve `{secciones, filas_orden_telar, total_general, lunes, domingo}`.
- `filasOrdenadasPorNumeroTelar(array $secciones)` (pública) — aplana y ordena por número de telar (para tabla y gráfico).
- *(privadas)* `totalGeneralDesdeFilas` (suma de RPM real e ideal), `rpmIdealFijoParaTelar` (lanza excepción si el telar no tiene ideal), `aggregateRpmRealPorTelar`, `normalizeTelar`, `averageRpmCapturado` (promedio de RpmR1/R2/R3 ≠0).

### 4.3 Helpers usados (de `app/Helpers/`)
- **`FolioHelper`** — `obtenerSiguienteFolio(serie,ancho)` (consume secuencia: series `Trama`, `Reenconado`, `CorteEficiencia`) y `obtenerFolioSugerido(serie,ancho)` (lectura para UI).
- **`TurnoHelper`** — `getTurnoActual()`, `getTurnoFormato()`, `getDescripcionTurno()`.
- **`AuditoriaHelper`** — `logEvento($tabla,$accion,$detalle,$request)` (Inventario de Trama).

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `Tejido\TejInventarioTelares` | `tej_inventario_telares` | sqlsrv (default) | `id` | no_telar, tipo, cuenta, calibre, hilo, fecha, turno, no_julio, no_orden, Reservado, Programado, status |
| `Tejido\TejHistorialInventarioTelaresModel` | `TejHistorialInventarioTelares` | sqlsrv | autoinc | histórico de inventario |
| `Tejido\TejTrama` | `TejTrama` | sqlsrv | `Folio` (string, no inc) | Fecha, Turno, Status, numero_empleado, nombreEmpl (sin timestamps) |
| `Tejido\TejTramaConsumos` | `TejTramaConsumos` | sqlsrv | (autoinc `Id`) | Folio, NoTelarId, SalonTejidoId, NoProduccion, CalibreTrama, FibraTrama, CodColorTrama, ColorTrama, Cantidad (sin timestamps) |
| `Tejido\TejEficiencia` | `TejEficiencia` | sqlsrv | `Folio` (string) | Date, Turno, Status, Horario1-3, numero_empleado, nombreEmpl; rel `lineas`, `usuario` |
| `Tejido\TejEficienciaLine` | `TejEficienciaLine` | sqlsrv | — | Folio, Date, Turno, NoTelarId, SalonTejidoId, RpmStd, EficienciaSTD, RpmR1-3, EficienciaR1-3, ObsR1-3, StatusOB1-3 |
| `Tejido\TejeFallasCeModel` | `TejeFallasCe` | sqlsrv | `Id` (inc) | Clave, Descripcion |
| `Tejido\TejMarcas` | `TejMarcas` | sqlsrv | `Folio` (string) | Date, Turno, Status, numero_empleado, nombreEmpl (sin timestamps) |
| `Tejido\TejMarcasLine` | `TejMarcasLine` | sqlsrv | — | Folio, Date, Turno, NoTelarId, SalonTejidoId, Eficiencia, Marcas, Horas, Trama, Pie, Rizo, Otros (sin timestamps) |
| `Tejido\TejProduccionReenconado` | `dbo.TejProduccionReenconado` | sqlsrv | `Folio` (string) | Date, Turno, Calibre, FibraTrama, CodColor, Color, Cantidad, Cabezuela, Conos, Horas, Eficiencia, capacidad, status (sin timestamps) |
| `Inventario\InvSecuenciaTelares` | `dbo.InvSecuenciaTelares` | sqlsrv | `Id` | NoTelar, TipoTelar, Secuencia, Observaciones (sin timestamps) |
| `Inventario\InvSecuenciaTrama` | `dbo.InvSecuenciaTrama` | sqlsrv | `Id` | NoTelar, TipoTelar, Secuencia (sin timestamps) |
| `Inventario\InvSecuenciaCorteEf` | `dbo.InvSecuenciaCorteEf` | sqlsrv | `NoTelarId` (no inc) | SalonTejidoId, Orden (sin timestamps) |
| `Inventario\InvSecuenciaMarcas` | `dbo.InvSecuenciaMarcas` | sqlsrv | `NoTelarId` (no inc) | SalonTejidoId, Orden (sin timestamps) |
| `Inventario\InvTelasReservadas` | `InvTelasReservadas` | sqlsrv | `Id` (inc) | reservas (con timestamps) |
| `Inventario\invSecuenciaTelar` | `InvSecuenciaTelares` | sqlsrv | `Id` | (modelo legacy alterno) |

**Tablas externas consultadas vía Query Builder** (no como modelos del ámbito):
- `sqlsrv` (default): `ReqProgramaTejido` (programa de tejido — modelo `Planeacion\ReqProgramaTejido`), `ReqModelosCodificados`, `CatCodificados`, `ReqTelares`/`ReqTelares` (`VelocidadSTD`), `AtaMontadoTelas` (julios), `UrdProgramaUrdido`, `EngProgramaEngomado`.
- `sqlsrv_ti` (TI_PRO — AX): `InventTable`, `ConfigTable`, `InventSum`, `InventDim`, `InventColor` (catálogos de calibre/fibra/color).

---

## 6. Vistas Blade

> Las vistas grandes de captura usan **`fetch` inline** (no la utilidad `window.http`) y `Swal`/toasts propios. Las observaciones del CLAUDE.md sobre migración a `window.http`/`window.notify` aplican a futuro.

### Inventario de Telas

- `modulos/tejido/inventario-telas.blade.php` (32 l.) — Hub estático con accesos a Jacquard / Itema / Karl Mayer. Sin JS de lógica.
- `modulos/tejido/inventario-telas/inventario-telas.blade.php` (338 l.) — Tablero del salón: tarjetas por telar con la orden en proceso, siguiente orden y julios. Recibe `datosTelaresCompletos` del controller. JS principalmente de presentación (impresión/scroll).
- `modulos/tejido/telares/telar-informacion-individual.blade.php` y `telares/ordenes-programadas.blade.php` — vistas de detalle individual (renderizadas por `mostrarTelarSulzer`/`obtenerOrdenesProgramadas`).

### Inventario de Trama

- `modulos/inventario-trama/nuevo-requerimiento.blade.php` (999 l.) — Captura del requerimiento por telar. **Funciones JS inline:** `getScrollable(node)` (busca contenedor scrollable); `window.irATelar(noTelar)` (ancla a un telar); `toggleQuantityEdit(element)` / `closeAllQuantityEditors()` (edición inline de cantidad); `agregarNuevoRequerimiento(btn)`, `cerrarModal()`; `debounce(func,wait)`; `setSelectOptions/ensureOption` (helpers de `<select>`); `transformarCalibre(calibre)` (formato de calibre); `initModalAutocomplete()` con `getCalibres`→`/modulo-nuevo-requerimiento/calibres`, `getFibras(itemId)`→`/fibras`, `getColores(itemId)`→`/colores`, `resetDependents/loadDependents/initMaterialSelectors`; `agregarCampo()`, `agregarFilaATabla(datos)`; `buildConsumosPayload()`; `autoGuardarRequerimientos(...)` / `scheduleGuardarRequerimientos()` / `guardarRequerimientos()` → POST `.../nuevo-requerimiento` (store); `actualizarIdsEnFilas(consumos)` (mapea data-consumo-id); `actualizarCantidadEnBD(consumoId,cantidad)` → POST `.../actualizar-cantidad`.
- `modulos/inventario-trama/consultar-requerimiento.blade.php` (569 l.) — Listado con filtros y panel de detalle. **Funciones JS:** `bindFoliosTable()`, `bindAcciones()`, `autoSelectFromQueryOrFirst()`, `selectFolio(folio,rowEl,forzarRecarga)`, `setSelectedFolio(folio,status)`, `renderDetalles(consumos,folio)`, `actualizarBotonesPorEstado()`, `fetchDetalles(folio,...)` → GET `/modulo-consultar-requerimiento/{folio}`, `postStatus(folio,nuevoStatus)` → POST `.../status`, `verificarEstadoInicial()`, `verificarYCrearNuevo()`; globales `window.accionStatus`, `window.verResumenSeleccionado`, `window.cambiarStatus(folio,status)`, `window.verResumen(folio)` → `.../resumen`, `window.editarFolioSeleccionado()` → `nuevo-requerimiento?folio=`.
- `modulos/inventario-trama/resumen-articulos.blade.php` (183 l.) — Resumen de consumos agrupados por salón (sólo presentación / impresión).

### Cortes de Eficiencia

- `modulos/cortes-eficiencia/cortes-eficiencia.blade.php` (1134 l.) — **Captura principal**. Helpers: `csrf()`, `fetchJSON(url,opts)`, `baseHeaders()`, `debounce()`, `showToast()`, `parsePct()`, `pad2()`, `horaActualStr()`, `emojiHorario(h)`. Lógica: `horarioTomado(h)`, `requireHorario(h)`, `leerHorarios()`; `asegurarTurno()` → GET `.../turno-info`; `actualizarBadgeFolio()`, `actualizarEstadoBotonesHeader()`, `bindEvents()`, `aplicarModoSoloLectura()`; `cargarTurnoActual()`; `cargarDatosTelaresStd()` → GET `.../datos-telares`; `completarStdDesdeHistorial()`; `generarNuevoFolio()` → GET `.../generar-folio`; `cargarCorteExistente(folio)` → GET `.../{folio}`; `setDisplay/setObs/obtenerValorHorarioAnterior`; `actualizarYGuardarHoraHorario(h)` → POST `.../guardar-hora`; `guardarEnServidor(...)` → POST `.../guardar-tabla`; `recopilarDatosTelares()`; `accionNotificarTelegram()` → POST `.../visualizar/notificar-telegram`; `accionFinalizarFolio()` → POST `.../{folio}/finalizar`; `validarParaFinalizar()`, `procesarFinalizado()`; `syncObsTitle`, `preloadFallas()` → GET `.../fallas`, `abrirModalObservaciones(checkbox)`; `cargarPdfJs()`, `accionCapturarImagen()` → POST `.../visualizar/notificar-telegram-imagen`; `floatingBadge(text,isError)`.
- `modulos/cortes-eficiencia/consultar-cortes-eficiencia.blade.php` (933 l.) — Listado/consulta. **Funciones JS:** `generarFolioConDatos(fecha,turno)` → GET `.../generar-folio`; `cerrarModalNuevoCorte()`, `confirmarNuevoCorte()`; `exportarExcelVisualizacion(fecha)` → POST `.../visualizar/exportar-excel`; `descargarPDFVisualizacion(fecha)` → POST `.../visualizar/descargar-pdf`.
- `modulos/cortes-eficiencia/visualizar-cortes-eficiencia.blade.php` (472 l.) — Tabla de los 3 turnos por fecha; botones de exportar/notificar.
- `modulos/cortes-eficiencia/pdf.blade.php`, `visualizar-cortes-eficiencia-pdf.blade.php`, `visualizar-cortes-eficiencia-rango-pdf.blade.php` — plantillas Dompdf (sin JS).

### Marcas Finales

- `modulos/marcas-finales/nuevo-marcas.blade.php` (760 l.) — **Captura principal**. Helpers `q/qa`. Funciones: `initElements()`, `actualizarBadgeFolio()`, `mostrarAlerta(mensaje,tipo)`, `guardarAutomatico()`, `guardarDatosTabla()` → POST `/modulo-marcas/store`; `generarNuevoFolio(fecha,turno)` → POST `/modulo-marcas/generar-folio`; `abrirModalFechaTurno()` (con `cerrar/abrir/onCancel/onOk/onBackdrop/limpiar`); `cargarDatosSTD(soloVacios)` → GET `/modulo-marcas/obtener-datos-std`; `cargarMarcaExistente(folio)` → GET `/modulo-marcas/{folio}`.
- `modulos/marcas-finales/marcasFinales.blade.php` (809 l.) — Consulta/listado implementado como objeto con métodos (no funciones sueltas): `accionNuevo`, `accionEditar`, `accionFinalizar` → POST `.../finalizar`, `accionVisualizar`, `accionEditarSupervisor`, `abrirModalFechas`/`generarReporteFecha` → `/modulo-marcas/reporte`, `guardarRegistro` → PUT `.../actualizar-registro`. Botones supervisor: reabrir (`.../reabrir`).
- `modulos/marcas-finales/reporte-marcas.blade.php` (226 l.) — Reporte por fecha (3 turnos) con botones Excel/PDF/Telegram.
- `modulos/marcas-finales/reporte-marcas-pdf.blade.php` — plantilla Dompdf.
- `modulos/marcas-finales/consultar-marcas-finales.blade.php` (609 l.) — vista de consulta alterna.

### Producción de Reenconado

- `modulos/produccion-reenconado-cabezuela.blade.php` (1024 l.) — Captura + tabla. **Funciones JS de filtro:** `obtenerOperadoresUnicos()`, `obtenerCalibresUnicos()`, `poblarFiltros()`, `applyFilters()`. Consume `.../generar-folio`, `.../calibres|fibras|colores`, `store`, `update`, `destroy`, `cambiar-status`.

### Reportes y Secuencias

- `modulos/tejido/reportes/index.blade.php` (34 l.) — Menú de reportes (definido en la closure de rutas).
- `modulos/tejido/reportes/inv-telas.blade.php` (227 l.) — Modal de rango (máx 5 días), tabla por sección con celdas coloreadas, botones Excel/PDF.
- `modulos/tejido/reportes/inv-telas-pdf.blade.php` (360 l.) — plantilla Dompdf con resumen y logo.
- `modulos/tejido/reportes/promedio-paros-eficiencia.blade.php` (118 l.) — Modal de rango y descarga Excel.
- `modulos/tejido/reportes/reporte-marcas-finales.blade.php` (252 l.) — Preview por día (selector) + Excel.
- `modulos/tejido/reportes/rpm-semanal.blade.php` (222 l.) — Selección de semana, tabla y gráfico (Chart.js) RPM real vs ideal + Excel.
- `modulos/tejido/reportes/saldos-2026.blade.php` (1715 l.) — Tabla de saldos con agrupación por orden compartida (líder/miembros), punto rojo para finalizados; Excel.
- `modulos/tejido/secuencia/{inv-telas,inv-trama,corte-eficiencia,marcas-finales}.blade.php` (~470-513 l. c/u) — CRUD con tabla **drag & drop** (SortableJS): alta/edición/borrado por AJAX y `updateOrden` al soltar; consumen las rutas `tejido.secuencia-*.{store,update,destroy,orden}`.

---

## 7. JS dedicado

No existen archivos `.js` dedicados para el ámbito Tejido en `resources/js/`. Toda la lógica de cliente vive **inline** en los `<script>` de cada Blade (documentadas en §6). Las utilidades globales disponibles son las del proyecto (`window.http`, `window.notify` desde `bootstrap.js`), aunque estas vistas todavía usan `fetch`/`Swal` propios.

---

## 8. Lógica de negocio y reglas

### Cálculos y fórmulas
- **Producción de Reenconado:** `capacidad = Horas * 9.3`; `Eficiencia = Cantidad / capacidad` (sólo si `capacidad > 0`). Recalculadas en `store` y `update`.
- **Eficiencia de resumen (Promedio Paros):** `Eficiencia = Marcas * 100000 / (RPM * 60 * 8)` (RPM debe ser ≠0).
- **RPM real (cortes):** promedio de `RpmR1/RpmR2/RpmR3` ignorando los valores 0 o vacíos. Para `getDatosTelares` se toma la última RPM/eficiencia real ≠0 priorizando Horario 3>2>1.
- **RPM Semanal:** RPM real promedio semanal por telar vs **RPM ideal fijo** por telar (tabla constante en el service). Total = suma de reales y suma de ideales.
- **Marcas Finales – Eficiencia almacenada:** entero 0-100; prioriza el `PorcentajeEfi` capturado, si no `EficienciaSTD*100` del programa, acotado a [0,100].
- **Reporte Inv Telas – color de celda:** azul (reservado / con julio) > naranja (programado) > amarillo (pendiente / sin reservar ni programar). El amarillo se inyecta para órdenes del programa sin producción dentro de la ventana de "días de liberación" (`liberar_ordenes_dias`, default 10.999).

### Restricciones / validaciones de negocio
- **Secuencias Corte Eficiencia y Marcas Finales:** un solo registro por `NoTelarId` (unicidad validada en store/update).
- **Cortes de Eficiencia:** no puede existir más de un folio "en proceso" simultáneamente, ni dos folios para la misma fecha+turno. `generarFolio` reserva el folio creando ya la cabecera `En Proceso` (transacción + `lockForUpdate`).
- **Marcas Finales:** no puede haber dos folios para la misma fecha+turno; sólo un folio "En Proceso"; `generarFolio` con `lockForUpdate`.
- **Acciones de supervisor (chequeo `puesto==='supervisor'`, no `userCan`):** reabrir folio de marcas, `actualizarRegistro` de marcas y de cortes. 403 en caso contrario.
- **Trama – máquina de estados (`updateStatus`):** `Creado → {En Proceso, Cancelado}`, `En Proceso → {Solicitado, Cancelado}`, `Solicitado → {Surtido, Cancelado}`, `Surtido`/`Cancelado` finales. Sólo se reusa un único folio "En Proceso".
- **Producción Reenconado – ciclo de status:** `Creado → En Proceso → Terminado → Creado`.

### Flujos completos
- **Captura Cortes de Eficiencia:** abrir vista → si hay folio en proceso, redirige a consultar → `generarFolio` (reserva cabecera) → `cargarDatosTelaresStd`/`getDatosProgramaTejido` precargan STD → captura por horario, `guardarHora` por corte, `guardarTabla` autosalva las líneas → `finalizar` cierra el folio y ofrece PDF → opcional `notificarTelegram`/imagen.
- **Captura Marcas Finales:** `generarFolio` (fecha+turno) → captura marcas/horas/paros → `store` (borra y reinserta líneas) → `finalizar` → `reporte`/Excel/PDF/Telegram. Supervisores pueden `reabrir`/`actualizarRegistro`.
- **Requerimiento de Trama:** `index` arma filas TRA + C1..C5 desde el programa en proceso → captura cantidades → `guardarRequerimientos` (upsert con dedupe por calibre/fibra/color con tolerancia 0.01) → en consulta, cambio de status; al pasar a "Solicitado" se notifica a Telegram (módulo `InvTrama`).
- **Reporte Inv Telas:** rango ≤5 días → se agregan registros de `tej_inventario_telares` por telar/día/turno, se completa con el programa en proceso (fallback de cuentas/fibra/calibre) y se cruza con urdido/engomado para colores → vista / Excel / PDF.
- **Saldos 2026:** órdenes activas de `ReqProgramaTejido` + datos de `ReqModelosCodificados` (combinaciones, cenefas) + finalizados del mismo grupo `OrdCompartida` desde `CatCodificados`; agrupa por orden compartida marcando líder y sumando totales.

### Efectos colaterales e integraciones
- **Telegram:** destinatarios por módulo desde `SYSMensaje::getChatIdsPorModulo()` — `InvTrama` (mensaje de solicitud), `CorteSEF` (PDF e imagen), `MarcasFinales` (PDF). Límites: 50 MB documento, 10 MB foto. Token desde `services.telegram.bot_token`.
- **Excel:** `maatwebsite/excel` con exports `ReporteInvTelasExport`, `PromedioParosEficienciaExport`, `ReporteRpmSemanalExport`, `ReporteMarcasFinalesExport`, `MarcasFinalesExport`, `CortesEficienciaExport`, `Saldos2026Export`.
- **PDF:** `dompdf/dompdf` (cortes a4 landscape, marcas a4 landscape, inv-telas letter portrait), con `isRemoteEnabled` y logo embebido en base64.
- **Auditoría:** `AuditoriaHelper::logEvento` en alta/edición de `TejTrama` y `TejTramaConsumos`.
- **Conexiones cruzadas:** catálogos de hilo/fibra/color contra AX (`sqlsrv_ti`); el resto de la operación contra `sqlsrv` (incluido el programa de tejido de Planeación).
