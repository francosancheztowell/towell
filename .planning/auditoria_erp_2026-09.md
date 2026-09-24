# Auditoría ERP Towell — septiembre 2026

Fecha: 2026-09-22. Solo lectura. Síntesis de 7 auditores (dup-php, dup-front, dead, components, livewire, sql, perf), deduplicada y cruzada con `.planning/auditoria_limpieza_plan.md`, `auditoria_urdido.md`, `auditoria_desarrolladores.md` y `auditoria_programa_tejido.md`.

Leyenda: **[PREVIO-PENDIENTE Rx]** = ya estaba documentado en una auditoría anterior y sigue sin resolverse. **[NUEVO]** = no estaba documentado. Esfuerzo S/M/L. Las líneas eliminables son estimaciones.

Fuera de alcance por decisión previa (no se re-proponen): dedup de Programa Tejido fases 5/6, virtualizar la grilla PT, permisos N+1, rejilla 2x2 de Urdido, typos `catalagos`/`reigstrar`.

Re-verificado con grep en la síntesis (0 llamadores fuera de la definición): `obtenerPreview(`, `getInventarioPorMaterialesUrdido(`, `updateExistingRecord(`, `buildReporteResumenData` (2 definiciones, 0 llamadas), `OrdenKarlMayerService`, `$programaRoutes` (solo en las 2 vistas legacy), `guardarTabla` (solo ruta+método), `recalculatePriorities`, `addPersistentMiddleware` (0 en app), `Incorrecto` en app/Livewire (0). También se confirmó `EdicionOrden::guardarMetrosFila` con `find($registroId)` sin filtrar folio, `import '../css/app.css'` en app.js:2, `PATTERN_RIZO = '%JU-ENG-RI%'` y `button-edit.blade.php:61 $disabled = false`.

---

## 1. Resumen ejecutivo

| Bloque | Líneas eliminables (est.) | Naturaleza |
|---|---|---|
| Código muerto confirmado (vistas, métodos, rutas, controladores) | ~5,500 | Borrado directo, riesgo bajo (el tablero legacy exige portar antes "Cuenta/Calibre incorrecta") |
| Duplicación backend PHP/SQL | ~2,000 | Extracciones S/M |
| Duplicación front, componentes y migración de catálogos/secuencias a Livewire | ~8,500 | M, necesita migración |
| **Total** | **~16,000 L** (≈8 % de ~200k) | |
| Assets | ~27 MB de imágenes sin referencia en git, Tailwind descargado dos veces por página | |

No suma la migración pendiente de Urd/Eng F3/F5/F6 (~1,500 L de JS inline que se reescriben, no se borran).

### Top 10 acciones (orden recomendado)

1. **Seguridad Livewire** (S, alto): `Livewire::addPersistentMiddleware([EnsureModulePermission::class])` y corregir el IDOR de `EdicionOrden::guardarMetrosFila` (`find` sin filtrar folio). [NUEVO]
2. **Inventario disponible**: quitar el `%` inicial de los 3 patrones LIKE (`InventarioReservasService.php:42-47`). Scan de 8.9M filas; 2147 ms → 130 ms medido. 3 líneas. [NUEVO]
3. **Bug visual `bg-opacity-*`** (Tailwind v4 no lo genera): 31 reemplazos en 16-17 archivos por `bg-black/50`. Modales con fondo opaco. [NUEVO]
4. **Barrido de código muerto sin riesgo** (~1,000 L): 12 métodos privados muertos + `buildReporteResumenData` x2 + `mecanicos/MecActividadesController` + CRUD sin ruta de `ReqProgramaTejidoLineController` + create/edit sin vista + métodos públicos sin llamador. [NUEVO + PREVIO-PENDIENTE R2.4/R2.3]
5. **R1.1 + endpoints POST sin consumidor** (~1,000 L): 6 rutas `editar-ordenes-programadas` que hoy devuelven 500, rutas AJAX de trama, `guardarTabla`, `recalcularMarbete`, `buscar`, `bulkSave`, `cambiarStatus`. [PREVIO-PENDIENTE R1.1 + NUEVO]
6. **Tablero clásico Programar Urd/Eng** (~3,300 L): primero portar "marcar/liberar Cuenta/Calibre incorrecta" a ProgramBoard (hueco funcional: hoy nadie puede liberar `Incorrecto=1` y eso bloquea finalizar). Después borrar 2 vistas, 12 endpoints y sus rutas. Preguntar al usuario antes (revirtió dos veces en 48 h). [NUEVO]
7. **Perf rápida**: quitar el import doble de Tailwind (app.js:2), pasar la caché de líneas al snap de calendario (N→1 consultas), `document.hidden` en el polling de Atadores. [NUEVO]
8. **Folio 'Trama' con dos lógicas incompatibles** en el mismo servicio (riesgo de folio duplicado) + FolioHelper reinventado en 4 sitios. [NUEVO, relacionado con R2.5]
9. **`TelegramNotifier` único** (13 envíos crudos a api.telegram.org) + dedup CortesEficiencia/MarcasFinales (PDF + actualizarRegistro). ~390 L. [NUEVO]
10. **Secuencias de Tejido (4 pantallas) → un componente Livewire** (~2,200 L) y luego los catálogos CRUD a mano → ConTabla (~3,000 L). [amplía R2.5]

---

## 2. Hallazgos por dimensión

### 2.1 Código muerto

| Hallazgo | Ubicaciones | Impacto | Esf. | Riesgo | Recomendación |
|---|---|---|---|---|---|
| Tablero clásico Programar Urd/Eng: 2 vistas huérfanas + 12 endpoints JSON sin consumidor [NUEVO] | `ProgramarUrdidoController.php:89`, `ProgramarEngomadoController.php:48` (sirven `-livewire`); `programar-urdido.blade.php` (1,465 L, `@json($programaRoutes)` :273, variable que nadie pasa); `programar-engomado.blade.php` (1,051 L, :145); métodos `ProgramarUrdidoController.php:46-86,128-433,482-576`, `ProgramarEngomadoController.php:63-315,364-455`; rutas `urdido.php:60-66`, `engomado.php:85-89`; `ProgramBoard.php:479` (`legacyUrl` sin uso); `tests/Unit/Programas/ProgramBoardStructureTest.php:30-32` | Alto, ~3,300 L | M | Medio | **Bloqueante**: `marcarIncorrecto` (`programar-urdido.blade.php:952`) no existe en ProgramBoard ni en ProgramBoardActionService, pero `ModuloProduccionUrdidoController.php:671` impide finalizar si `Incorrecto=1`. Portar esa acción (o retirarla con negocio) y revisar órdenes con `Incorrecto=1` en prod. Después borrar vistas, endpoints, helpers privados (extractMcCoyNumber, createdAtFallback, puntosCalidad, …), 12 rutas y el assertFileExists. Conservar `actualizarStatus`, `index`, `reimpresion*`, redirects `/legacy`. Actualizar `auditoria_urdido.md:63`. Preguntar al usuario antes. |
| Métodos privados sin llamadas (varios `@deprecated`) [NUEVO] | `ReqProgramaTejidoUpdateImport.php:580-679` (updateExistingRecord, copia sin transacción); `BomMaterialesService.php:334-414`; `ReporteMarcasFinalesController.php:84-157` (clon de obtenerPreviewPorDia) y `:277-320`; `ReqModelosCodificadosImport.php:600-620, 891-923`; `ReqVelocidadStdImport.php:191-202`; `Reporte00EAtadoresExport.php:702-710`; `OrdenesTrabajoMecaController.php:1456-1459`; `MoverOrdenesController.php:275-278` | Medio, ~372 L | S | Bajo | Borrar en un commit. |
| `buildReporteResumenData` muerto con N+1 dentro **[PREVIO-PENDIENTE R2.4 paso 1]** | `ReportesEngomadoController.php:370-443`, `ReportesUrdidoController.php:1575-1651` | Bajo, 151 L | S | Bajo | Borrar antes de extraer BpmReportService. |
| Endpoints POST/PATCH sin consumidor [NUEVO] | `CortesEficienciaController.php:1556-1668` + `tejido.php:225`; `CatCodificacionController.php:230-307` + `planeacion.php:105`; `CodificacionController.php:1161-1236` + `planeacion.php:97`; `TelBpmLineController.php:171-231` + `tejedores.php:118`; `ProduccionReenconadoCabezuelaController.php:410-443` + `tejido.php:148`; `TelegramController.php:25-104` + `telegram.php:7` | Medio, ~440 L | S | Bajo | Borrar los 5 primeros (~360 L). Telegram: revisar access log de 192.168.2.15 antes (documentado en CLAUDE.md). Desplegar con cache-bust. |
| R1.1 sin aplicar: 6 rutas a métodos inexistentes (500), AJAX de trama, toggles de Modulos, `/modulos-sin-auth`, `/test-404` **[PREVIO-PENDIENTE R1.1]** | `urdido.php:72-75`, `engomado.php:76-77`, `EditarOrdenesProgramadasController.php:19`, `tejido.php:171-180, 254-255`, `configuracion.php:77-80`, `public.php:19, 32-39`, `planeacion.php:325-330` | Medio, ~560 L | S | Bajo | Ejecutar R1.1 tal como está. Empezar por las rutas que dan 500. |
| `OrdenKarlMayerService` huérfano (el componente que lo usaba se borró en 11740c77) [NUEVO] | `app/Services/ProgramaUrdEng/OrdenKarlMayerService.php:25`; lógica duplicada inline en `CrearOrdenKarlMayerController.php:73-155` | Medio, 216 L | S | Bajo | Preferible: que el controlador delegue en el servicio (comparar campo por campo antes) y borrar ~130 L del controlador. Si no, borrar el servicio. |
| Controlador duplicado sin rutas [NUEVO] | `app/Http/Controllers/mecanicos/MecActividadesController.php` (132 L); la ruta usa `mecanicos/Catalogos/MecActividadesController` (`mecanicos.php:3`) | Bajo, 132 L | S | Bajo | Borrar archivo. |
| `ReqProgramaTejidoLineController`: store/show/update/destroy + rules/sanitize sin ruta [NUEVO] | `ReqProgramaTejidoLineController.php:17-56, 93-134`; rutas solo GET `planeacion.php:321, 401` | Bajo, ~80 L | S | Bajo | Borrar. |
| create()/edit() excluidos por `->only()` y con vistas inexistentes [NUEVO] | `TelTelaresOperadorController.php:101-109, 218-230`; `TelActividadesBPMController.php:31-34, 55-61`; `tejedores.php:81, 89` | Bajo, ~30 L | S | Bajo | Borrar. |
| Métodos públicos / helpers sin llamador [NUEVO; getByArea/getPermisosUsuario **PREVIO-PENDIENTE R2.3**] | `FolioHelper.php:71-111`; `ProgramaPrioridadService.php:147-158` (recalculatePriorities, además save() por fila); `InventarioTelaresService.php:133-136`; `ProgramaConfig.php:42-49`; `ReporteEstadoMaquinaService.php:151-154`; `ReqModelosCodificadosImport.php:864-885`; `format_helpers.php:36-50`; `UsuarioRepository.php:122`; `PermissionService.php:60` | Bajo, ~130 L | S | Bajo | Borrar en bloque. (getDeviceInfo NO está muerto: `function_exists` en user-modal.) |
| NuevoRequerimiento: irATelar, listaTelares, `__construct` vacío, guardar()/guardando sin UI; `eliminarFila` sin botón [NUEVO] | `NuevoRequerimiento.php:31, 60, 88-91, 230-258`; vista `nuevo-requerimiento.blade.php:104-117, 141` | Bajo, ~45 L | S | Bajo | Decidir con negocio `eliminarFila` (poner botón o borrar con su test). Borrar el resto. Añadir `wire:key` en los 5 bucles. |
| Alias `Engomado\EdicionOrdenes` y `DashboardPvVsOc` sin estado con datos mock [NUEVO] | `app/Livewire/Engomado/EdicionOrdenes.php:7-8`, `reimpresion-engomado.blade.php:14`; `DashboardPvVsOc.php:15-28`, `PvVsOcMockPayload.php`, `ventas.php:15` (mojibake en :19) | Bajo, ~40 L | S | Bajo | Usar `<livewire:urd-eng.edicion-ordenes module="engomado">`. Ventas: Route::view mientras sea mock; decidir si /ventas debe mostrar datos inventados. |
| ~27 MB de imágenes sin referencia en git [NUEVO] | `public/images/fotosTowell/1-14.png` (idénticas a `fondosTowell/1-14.png`), `logo_towell*.png`, `bluetowel.jpg`, `robot_for_chatbot.png`, `fondoLog.jpg` | Bajo (deploy/repo) | S | Bajo | Borrar; conservar `fotosTowell/TOWELLIN.png` (browserconfig.xml). Revisar access log antes. Confianza media. |
| `sqlsrv_tow_pro` sin uso; `getOrdenProduccion` hace `SELECT @@VERSION` y lo devuelve en JSON [NUEVO] | `config/database.php:128-139`; `NotificarMontRollosController.php:219-265`; `tejedores.php:52, 61, 66` | Medio (fuga de info) | S | Bajo | Quitar el probe y el bloque debug ya. Si no hay cliente externo, borrar método y rutas. Actualizar CLAUDE.md sobre la conexión. |

### 2.2 Duplicación de código PHP

| Hallazgo | Ubicaciones | Impacto | Esf. | Riesgo | Recomendación |
|---|---|---|---|---|---|
| BPM captura Engomado/Urdido: 4 controladores ~90 % idénticos con 3 divergencias sin documentar [NUEVO; distinto de R2.4, que es de reportes] | `EngBpmLineController.php:1-216`, `UrdBpmLineController.php:1-226`, `EngBpmController.php:1-159` (setTimeFrom :67-78), `UrdBpmController.php:1-154` (filtro KM/MC en UrdBpmLine :20-24) | Alto, ~330 L | M | Medio | Trait `BpmCapturaTrait` con métodos abstractos (headerModel, lineModel, columnaAutoriza, sessionPrefix, viewPrefix, indexRoute). Antes decidir si las divergencias son intencionales. |
| Envío a Telegram reimplementado 13 veces (timeouts 3/5/20/30/ninguno, Markdown inconsistente) [NUEVO] | `AtadoresController.php:1142`, `NotificarMontadoJulioController.php:301`, `CortesEficienciaController.php:1239, 1439`, `MarcasController.php:861`, `ParoTelegramNotifier.php:120`, `ReporteEstadoMaquinaTelegramNotifier.php:67`, `RequerimientoStatusService.php:99`, `SendUrdidoQualityNotification.php:46`, `NotificacionTelegramDesarrolladorService.php:43`, `TelegramController.php:64`, `MensajesController.php:29` | Alto, ~200 L | M | Bajo | `App\Services\TelegramNotifier::toModulo / sendDocumentToModulo / sendPhotoToModulo` con timeout por defecto y log uniforme. Migrar uno por uno. |
| CortesEficiencia ↔ MarcasFinales: actualizarRegistro, PDF y Telegram copiados entre sí y dentro de cada uno [NUEVO] | `CortesEficienciaController.php:496-585, 1038-1093, 1187-1280, 1489-1543`; `MarcasController.php:473-562, 671-736, 737-811, 812-900` | Medio, ~190 L | S | Bajo | Trait `actualizarRegistroFolio(header, line, …)`, un `generarPdf()` privado por controlador y envío por TelegramNotifier. |
| Folio 'Trama' con dos lógicas incompatibles en el mismo servicio + FolioHelper reinventado en 4 sitios [NUEVO; relacionado con R2.5] | `NuevoRequerimientoService.php:39-50` (usa consecutivo actual y luego incrementa) vs `:505` (`obtenerSiguienteFolio` → current+1); `SSYSFoliosSecuencia.php:89-124`; `FolioHelper.php:19-54`; `OrdenesTrabajoMecaController.php:1075-1100` y `VerificaMaquina/Index.php:138-164` (asegurarSecuencia byte a byte); `TelBpmController.php:234-281` (reintentos x5) | Alto (posible folio duplicado), ~125 L | M | Medio | Test de folios consecutivos alternando las dos rutas **antes**. `construirVm` usa `obtenerSiguienteFolio('Trama')`. Añadir `FolioHelper::asegurarSecuencia(...)` para Meca/VerificaMaquina. TelBpm → nextFolio y resincronización como migración de datos. Hacerlo junto con R2.5. Confianza media. |
| ConfiguracionController: procesarExcel / procesarExcelUpdate ~180 L cada uno [NUEVO; distinto de R3 que trata los Imports] | `ConfiguracionController.php:36-216, 218-385` | Medio, ~150 L | S | Medio | Privado `importarProgramaTejido($import, ?callable $antes, callable $stats)`. |
| OrdenDeCambioFelpa copia a mano `LiberarCatCodificadosWriter::actualizarReqModelos` [NUEVO] | `OrdenDeCambioFelpaController.php:1612, 1629-1703`; `LiberarCatCodificadosWriter.php:365-440` | Medio, ~70 L | S | Bajo | Inyectar el Writer y borrar la copia (la copia no tiene la corrección QW9). |
| Regla "CalibreTrama > 40 ⇒ Alta" x7 y consulta "programas que usan este STD" x4 [NUEVO] | `CatalagoEficienciaController.php:219-240, 282-316`; `CatalagoVelocidadController.php:250-270, 318-346`; `QueryHelpers.php:25 (sin null-check), 85, 114` | Medio, ~60 L | S | Bajo | `QueryHelpers::densidadPorCalibre()` y `programasQueUsanStd()`. |
| InventarioTelaresController: endpoint reimplementa el helper interno y normalización RIZO/PIE x6 [NUEVO] | `InventarioTelaresController.php:631-700 vs 897-941`; `:184, 404, 484, 650, 751, 903`; `InventarioTelaresService.php:191` | Bajo, ~90 L | S | Bajo | Endpoint llama al helper; `normalizarTipo()` de una línea. |
| CalificarJuliosController: dos pares de métodos idénticos [NUEVO] | `CalificarJuliosController.php:22-100, 104-181`; `engomado.php:109-112` | Bajo, ~70 L | S | Bajo | Dos privados parametrizados por modelo. |
| ModuloProduccionEngomado: segunda consulta de "filas vacías" redundante (devuelve 0 por construcción); filtro copiado 5 veces con divergencia NULL vs '' [NUEVO] | `ModuloProduccionEngomadoController.php:579-624`; `ModuloProduccionUrdidoController.php:394-410, 458-472`; `EdicionOrden.php:622-629`; `ProduccionTrait.php:177-230` | Bajo, ~55 L | S | Medio | Borrar 603-624; `ProduccionTrait::filasVaciasEditables()` con un único criterio. |
| Dompdf Options+render repetido 10 veces [NUEVO] | `MecReportesController.php:244-276, 319-351`; `CortesEficienciaController.php:756-770`; `MarcasController.php:700-712`; `AlineacionController.php:208-220`; `ReporteInvTelasController.php:205-216`; `PDFController.php:262-273` | Bajo, ~90 L | S | Bajo | Un `renderHtml(view, data, paper, orient): string`. |
| MensajesController: reglas copiadas store/update [NUEVO] | `MensajesController.php:85-148, 151-216` | Bajo, ~40 L | S | Bajo | `rules()` privado o FormRequest. |
| Secuencias de Tejido: 4 controladores clonados, no 2 **[PREVIO-PENDIENTE R2.5, ampliado]** | `SecuenciaInvTelasController.php`, `SecuenciaInvTramaController.php`, `SecuenciaCorteEficienciaController.php`, `SecuenciaMarcasFinalesController.php` | Ver 2.5 | — | — | Incluir los 4 en R2.5 o resolver con el componente Livewire (2.5). |

### 2.3 Duplicación de consultas SQL

| Hallazgo | Ubicaciones | Impacto | Esf. | Riesgo | Recomendación |
|---|---|---|---|---|---|
| Flogs vigentes AX (ESTADOFLOG 3/4/5/21) copiada 7 veces con distinto criterio de "más reciente" (numérico vs alfabético) [NUEVO; el whereIn por pares ya está en R2.5] | `CodificacionController.php:1415-1423`; `ProgramaTejidoCatalogosController.php:97-105, 192-209, 288-296`; `LiberarFlogSugeridoService.php:23, 117-123, 208-222`; `CrudoFlogService.php:19` | Alto (resultado de negocio distinto), ~70 L | M | Medio | `AxFlogsRepository` con una constante `ESTADOS_VIGENTES`; Codificación usa `LiberarFlogSugeridoService::sugerir()`. |
| L.Mat CRUDO copiada 5 veces; la de CatCodificacion usa JOIN sin DISTINCT (duplicados, bug ya corregido en el resolver) [NUEVO] | `CatCodificacionController.php:537-581`; `LiberarBomCrudoResolver.php:28-60, 171-186, 278-288, 333-341` | Medio, ~35 L | S | Bajo | `queryLmatDesdeTi` → `LiberarBomCrudoResolver::query(...)->limit(50)`; `baseCrudo()` privado. |
| Catálogos AX ConfigTable/InventSize/InventColor en 11 sitios; Engomado y Reenconado sin `TwVigente=1` [NUEVO; fusiona dup-php + sql] | `CatLMatController.php:520-600, 688-710`; `CatalogosMaterialesLMatService.php:44-94`; `EngProduccionFormulacionController.php:672-718`; `ProduccionReenconadoCabezuelaController.php:47-90` | Medio, ~80 L | S | Bajo | `configs/tamanos/colores(itemIds, soloVigentes)` en el servicio de catálogos. Confirmar con negocio el filtro TwVigente. |
| Introspección de esquema en ~15 sitios, 4 estrategias de caché; `SSYSFoliosSecuencia::getColumnMap` consulta INFORMATION_SCHEMA en cada folio, dentro de la transacción [NUEVO] | `SSYSFoliosSecuencia.php:52-77, 92`; `AuditoriaHelper.php:25`; `ReqProgramaTejidoSimpleImport.php:832-852`; `ReqProgramaTejidoUpdateImport.php:979-994`; `CodificacionController.php:236-291`; `ReqProgramaTejidoObserver.php:475`; `LiberarValidacionesService.php:214`; `LiberarCatCodificadosWriter.php:36`; `VincularTejido.php:375-379`; `MovimientoDesarrolladorService.php:585, 637` | Medio, ~90 L | M | Bajo | `SchemaCache::columns/stringLimits`. En folios, constantes en lugar de getColumnMap. |
| Patrón "Posicion+10000 y UPDATE por fila" en 6 sitios con NULL tratado distinto [NUEVO] | `DragAndDropTejido.php:114-136`; `ProgramaTejidoOperacionesController.php:278-294`; `MoverOrdenesController.php:368-369, 513`; `FinalizarOrdenesController.php:370`; `ReqProgramaTejidoUpdateImport.php:553-578` | Medio, ~60 L | M | Medio | `ProgramaTejidoPosiciones::aplicar()` con ISNULL + applyTelarFilter. Nota: toca PT; es corrección de criterio, no el dedup descartado de fases 5/6. |
| Filtro base de Trazabilidad x4, mes con whereRaw concatenado y regla de color divergente [NUEVO] | `TrazabilidadMatrixService.php:66-74`; `TrazabilidadProduccionService.php:576-584`; `TrazabilidadResumenService.php:86-95`; `TrazabilidadFilterOptionsService.php:151-173` | Medio, ~25 L | S | Bajo | `TrazaProduccion::scopeFiltros()`. Sin inyección (intval). |
| BomMaterialesService: consulta InventSum⋈InventDim⋈InventSerial x3 [NUEVO; el método muerto está en 2.1] | `BomMaterialesService.php:261-290, 416-445` | Bajo | S | Bajo | `inventarioMpQuery()` privado. |
| Reportes BPM Urd/Eng: filasBpm + 3 helpers **[PREVIO-PENDIENTE R2.4]** | `ReportesEngomadoController.php:154-258`; `ReportesUrdidoController.php:807-910` | Bajo, ~100 L | S | Bajo | Seguir R2.4 (BpmReportService). |

Seguridad SQL: todos los `whereRaw/selectRaw/DB::raw` con variables se revisaron; no hay inyección explotable (constantes, whitelists o intval).

### 2.4 Duplicación front-end y reutilización de componentes

| Hallazgo | Ubicaciones | Impacto | Esf. | Riesgo | Recomendación |
|---|---|---|---|---|---|
| Bug: 31 fondos de modal con `bg-opacity-*` (Tailwind v3), que v4 no genera → fondo sólido [NUEVO] | `catalogos-atadores/maquinas/index.blade.php:42, 67, 106`; `eng-actividades-bpm/index.blade.php:112`; `usuarios/select.blade.php:166`; `gestion-modulos/index.blade.php:175`; … (17 archivos) | Alto (UX) | S | Bajo | Reemplazo mecánico `bg-black bg-opacity-50` → `bg-black/50`. |
| `button-edit` fuerza `$disabled = false` cuando hay `module`: `:disabled` no funciona; 4 botones navbar casi idénticos con clases divergentes [NUEVO] | `components/navbar/button-edit.blade.php:59-61`; `button-create.blade.php:45-104`; `button-delete.blade.php:45-121`; `button-report.blade.php:47-108`; `livewire/mantenimiento/catalogo-fallas.blade.php:16-17`; `livewire/tejedores/catalogo-calibres.blade.php:17` | Medio, ~300 L | S | Medio | `navbar/action-button` común; los 4 como envoltorios; quitar el `$disabled = false`. |
| Modal Swal "Consultar en rango" copiado en 13 vistas de reportes [NUEVO] | `atadores/reportes/programa.blade.php:70-100`, `desarrolladores/reportes/programa.blade.php:75`, `tejedores/reportes/programa.blade.php:74`, `atadores/reportes/atadores.blade.php:198`, `reportes-bpm-engomado:124`, `reportes-control-merma:99`, `inv-telas:179`, `promedio-paros-eficiencia:72`, `reporte-marcas-finales:206`, `reportes-bpm-urdido:124`, `reportes-kaizen:180`, `reportes-roturas:102`, `reportes-urdido:108` | Medio, ~400 L | S | Bajo | `notify.rangoFechas()` en `utils/notifications.js`. |
| Vistas de reporte gemelas (programa Atad/Desarr/Tejed; BPM y Resumen Eng/Urd); `max-md` inválido en engomado [NUEVO] | `atadores|tejedores|desarrolladores/reportes/programa.blade.php:3`; `reportes-bpm-engomado.blade.php:11` vs `reportes-bpm-urdido.blade.php:11`; `reporte-resumen-engomado.blade.php:92` vs `reportes-resumen-urdido.blade.php:92` | Medio, ~600 L | S | Bajo | Una vista por familia parametrizada; Eng/Urd junto con R2.4. Corregir `max-md` → `max-w-md` ya. |
| Vistas BPM gemelas Eng/Urd derivando: el arreglo del 419 (`http.post`) solo está en Engomado [NUEVO; fusiona con 2.2 BPM] | `Engomado-BPM-Line/index.blade.php:207` vs `Urdido-BPM-Line/index.blade.php:208`; `BPM-Engomado/index.blade.php:45` vs `BPM-Urdido/index.blade.php:43`; `eng|urd|tel-actividades-bpm/index.blade.php` | Medio, ~600 L | M | Medio | Portar ya `http.post` a Urdido-BPM-Line. Después vista compartida junto con el trait de 2.2. |
| Componentes con 1 uso y flash duplicado en 23 vistas; 4 `alert alert-warning` de Bootstrap sin estilo [NUEVO] | `components/layout/page-header.blade.php` (1 uso), `ui/button.blade.php` (1), `ui/alert.blade.php:118-171` (líneas vacías); `catalagoTelares.blade.php:11`, `catalago-julios.blade.php:30`, `catalago-maquinas.blade.php:34`, `catalogo-ubicaciones.blade.php:30` | Medio, ~400 L | S | Bajo | `<x-ui.flash/>` en layouts/app que use `notify`; `x-ui.alert` en los 4 avisos; inline page-header. |
| Armazón de modal Livewire y bloque label+input+@error copiados; `<x-campo>` **[PREVIO-PENDIENTE D-04 auditoria_desarrolladores]** | `livewire/mantenimiento/catalogo-fallas.blade.php:54-138`; `livewire/tejedores/catalogo-calibres.blade.php:54-70, 161-192`; `livewire/desarrolladores/captura.blade.php` (20 `@error`) | Medio, ~350 L | S | Bajo | `<x-ui.dialog>`, `<x-ui.confirmar>`, `<x-campo>` **antes** de migrar catálogos. |
| Modales a mano: 64 overlays en 36 vistas; `x-ui.modal-base` (3 usos) acoplado a `--pt-navbar-height` [NUEVO] | `components/ui/modal-base.blade.php:8, 20-25` | Bajo, ~300 L | L | Bajo | Generalizar modal-base; adoptar solo en vistas que se toquen por otros hallazgos. |
| escapeHtml reimplementado 19 veces, con implementaciones distintas (algunas no escapan `'`) [NUEVO] | `utils/notifications.js:17` (no exportado); `nuevo-paro/index.blade.php:800`; `reporte-fallos-paros:173`; `redbooth.blade.php:163`; `catcodificacion/index.js:998, 1824`; … | Bajo, ~70 L | S | Bajo | Exportar desde `utils/html.js` y `window.escapeHtml`. |
| Restos de toasts: `internalToast` muerto, guardas `typeof showToast` con else muerto, 75 Swal toast inline [NUEVO] | `bootstrap.js:75`; `catcodificacion/index.js:134-169`; `act-calendarios.blade.php:117`; `cortes-eficiencia.blade.php:200` | Bajo, ~150 L | S | Bajo | Borrar internalToast y ramas else ya; Swal→notify al migrar cada módulo. |
| Catálogos Atadores: `openViewModal` nunca se llama; `deleteModal` duplica `notify.confirm` [NUEVO] | `catalogos-atadores/maquinas/index.blade.php:41-62, 105-134, 256`; `comentarios/index.blade.php:45`; `actividades/index.blade.php:44` | Bajo, ~210 L | S | Bajo | Si no se migran ya, borrar viewModal y usar notify.confirm. Si se migran (2.5), desaparece. |
| `catalog-actions` resuelve permisos con mapa ruta→nombre: julios/máquinas quedan fuera y se ocultan botones en silencio; 300 L de script siempre [NUEVO] | `components/buttons/catalog-actions.blade.php:19-39, 43`; `catalago-julios.blade.php:23`; `catalago-maquinas.blade.php:27` | Bajo | S | Bajo | Prop `moduloId` (resolver por idrol, por los nombres duplicados de SYSRoles); sacar el script a resources/js. |
| `x-layout.page-title` emite `<h1>` dentro del `<h1>` del navbar e ignora 4 de 5 props [NUEVO] | `components/layout/page-title.blade.php:1-11`; `navbar.blade.php:49-53`; 9 usos | Bajo, ~40 L | S | Bajo | `@section('page-title')` y borrar componente. |
| Mapa de colores de estado de orden x3; componente sin `$attributes` [NUEVO] | `components/urd-eng/estado-orden.blade.php:1-11`; `editar-orden-engomado.blade.php:6-17`; `editar-orden-programada.blade.php:6-17`; `EdicionOrdenes.php:66` | Bajo, ~35 L | S | Bajo | `$attributes->merge` y usar en las 2 vistas y en EdicionOrdenes. |
| Empty-state copiado; páginas 403/404/500 cada una con su `<html>` [NUEVO] | `submodulos.blade.php:12-18`; `components/empty/empty-state.blade.php`; `errors/403|404|500.blade.php` | Bajo, ~110 L | S | Bajo | Usar el componente; `errors/layout.blade.php`. |
| fetch crudo: 287 llamadas en 68 archivos vs 21 migrados **[PREVIO-PENDIENTE R3]** | `resources/js/utils/http.js`; `.planning/auditoria_limpieza_plan.md:168` | Medio | L | Medio | Sin cambios a R3, salvo portar ya las gemelas donde una ya migró (BPM-Line). |

Documentación desfasada: CLAUDE.md menciona `layouts/simple.blade.php` y `globalLoader.blade.php`, que no existen (el loader está en `components/layout/global-loader.blade.php`). La memoria `project_urdeng_migracion_livewire` dice que Urdido/Engomado sirven el Blade clásico; desde 18440e4b (20-sep) sirven `-livewire`.

### 2.5 Migración a Livewire

| Hallazgo | Ubicaciones | Impacto | Esf. | Riesgo | Recomendación |
|---|---|---|---|---|---|
| `module.permission` no protege las acciones Livewire (`/livewire/update` no re-ejecuta ese middleware); 4 componentes sin chequeo propio [NUEVO; EditarOrdenPermisosTest **PREVIO-PENDIENTE R3**] | `vendor/livewire/.../PersistentMiddleware.php:16-25`; `AppServiceProvider.php:85-87`; `NuevoRequerimiento.php:62-66`; `ConsultarRequerimiento.php:89-108`; `EdicionOrden.php:112-125` (`puedeEditar=true` fijo); `EdicionOrdenes.php:43-46`; rutas `tejido.php:170, 173`, `urdido.php:71` | Alto (seguridad) | S | Medio | `Livewire::addPersistentMiddleware([EnsureModulePermission::class])` y module.permission / `abort_unless` en las 4 páginas. Verificar idrol contra SYSUsuariosRoles antes. |
| IDOR: `EdicionOrden::guardarMetrosFila` hace `find($registroId)` sin comprobar el folio (clave controlada por el cliente) [NUEVO] | `EdicionOrden.php:152-180, 213-216`; `edicion-orden.blade.php:410` | Alto (integridad) | S | Bajo | `$this->produccionEditable($this->orden())->whereKey($id)->first()` + test Livewire. |
| `VerificaMaquina\Index` usa `->paginate()` (OFFSET/FETCH) → falla desde página 2 en SQL Server 2008 R2 [NUEVO] | `VerificaMaquina/Index.php:108`; `PaginacionCompat.php:14-20`; docblock `ConTabla.php:23`; también `UrdEngNucleosController:26`, `EngActividadesBpmController:26`, `ReqProgramaTejidoLineController:89`, `AuditoriaProgramaTejidoController:57` | Medio | S | Bajo | `PaginacionCompat::paginar(...)`; corregir docblock; probar página 2 en prod. Confianza media. |
| Estado migración Urd/Eng: F1-F2 hechas; F3 revertida (creacion-ordenes.js 1,460 L fuera de Vite, BUG-033 abierto); F4 en TS, no Livewire; F5-F7 pendientes; doc de arquitectura contradice el código | `~/.claude/plans/dazzling-waddling-falcon.md`; `creacion-ordenes.blade.php:286`; `public/js/modulos/programa_urd_eng/creacion-ordenes.js`; `reservar-programar.ts`; `programacion-requerimientos.blade.php` (1,444 L, 1,360 de script); `karl-mayer/crear-karl-mayer.blade.php` (1,076 L); `docs/cerebro-towell/Arquitectura/livewire-cuando-si-cuando-no.md` | Medio, ~1,500 L reescribibles | L | Medio | Registrar la desviación F4→TS; F7 (docs) ya; luego F5 (Livewire sobre ResumenSemanasService o TS), rehacer F3 antes de F6. Actualizar la memoria. |
| Secuencias de Tejido: 4 vistas + 4 controladores + 20 rutas = la misma pantalla (drag&drop propio, `!selectedId` falla con id 0, URLs sin codificar) **[amplía PREVIO-PENDIENTE R2.5]** | `tejido/secuencia/corte-eficiencia.blade.php` (491 L, :239), `marcas-finales.blade.php` (483, :237, :302), `inv-telas.blade.php` (472, :321), `inv-trama.blade.php` (513, :260); `routes/modules/tejido.php:117-163`; 4 controladores | Alto, ~2,200 L | M | Bajo | `App\Livewire\Tejido\Secuencia(tipo)` con mapa tipo→[modelo, columnas, idrol], ConTabla, SortableJS, `abort_unless(userCan)` y `updateOrden` en transacción. |
| Catálogos CRUD a mano (tercer sistema en paralelo con ConTabla y CatalogBase.js): Atadores x3, *-actividades-bpm x3, Eficiencia/Velocidad (comparten 398/567 L), Pesos rollos, Julios, Operadores mantenimiento, urd-eng-nucleos, ubicaciones, máquinas [NUEVO] | `catalogos-atadores/{maquinas,comentarios,actividades}/index.blade.php` (403/430/421 L); `tel|urd|eng-actividades-bpm` (405/453/399); `catalagoEficiencia.blade.php` (703/645 script); `catalagoVelocidad.blade.php` (679); `pesos-rollos` (439); `catalago-julios` (522); `operadores-mantenimiento` (682); `public/js/catalogs/CatalogBase.js` (781, fuera de Vite) | Alto, ~3,000 L | M | Medio | ConTabla + `<x-tabla>` como único estándar; referencia CatalogoFallas (203+140 L). Orden: primero `<x-ui.dialog>/<x-campo>`, luego Atadores x3 y BPM x3, luego Eficiencia+Velocidad juntos. Congelar CatalogBase.js. Confianza media en el ahorro. |
| No migrar (confirmado con números): capturas de producción, codificación, liberar, balancear, calificar-atadores | `captura-formula` (3,589 L), `modulo-produccion-engomado` (3,389), `urdido/produccion/_scripts` (2,268), `catalogoCodificacion` (2,834), `liberar-ordenes` (2,682), `balancear` (1,730), `calificar-atadores` (2,269) | — | — | — | Si se tocan, patrón reservar-programar.ts (TS bajo Vite + tests/Js). |

### 2.6 Optimización / rendimiento

| Hallazgo | Ubicaciones | Impacto | Esf. | Riesgo | Recomendación |
|---|---|---|---|---|---|
| LIKE `'%JU-ENG-RI%'` sobre InventSum (8.9M filas): mediana 1.2 s, pico 14.7 s; sin `%` inicial: 2147 ms → 130 ms mismo resultado [NUEVO] | `InventarioReservasService.php:42, 44, 47, 694, 708-712, 724-725` | Alto | S | Bajo | Quitar `%` inicial de las 3 constantes; evitar LTRIM/RTRIM en la columna (:694). Comparar salidas antes de mergear. |
| Tailwind descargado dos veces por página (~149 KB / ~22 KB gzip) [NUEVO] | `resources/js/app.js:2`; `components/layout-styles.blade.php:4`; `layouts/app.blade.php:5-6` | Medio | S | Bajo | Borrar `import '../css/app.css'` de app.js; `npm run build`. |
| `snapInicioAlCalendario` consulta por registro y no usa la caché de BalancearTejido; ReqCalendarioLine es HEAP sin índices [NUEVO] | `TejidoHelpers.php:519-522`; `DateHelpers.php:70, 240, 358-361`; `CalendarioController.php:966, 1063-1066`; `BalancearTejido.php:843-848, 955-967` | Medio | S | Bajo | Pasar `$lines` desde la caché; índice `(CalendarioId, FechaInicio) INCLUDE (FechaFin)`. **Aviso negocio**: las líneas de los 4 calendarios terminan feb-jul 2026; hoy se usa el cálculo continuo de respaldo. Confirmar con Planeación. |
| Catálogo de Calendarios pinta 5,237 filas en el DOM y filtra con JS [NUEVO] | `CalendarioController.php:40-49`; `catalagos/calendarios/index.blade.php:55-67, 185-200`; ya existe `ProgramaTejidoCatalogosController.php:333-349` | Medio | M | Bajo | Cargar líneas por calendario con `http.get` bajo demanda. |
| HEAP sin índice en TejMarcasLine/TejEficienciaLine/AtaMontado*; guardar cortes hace 2 scans por telar (~76 consultas) **[PREVIO-PENDIENTE R2.1, ampliado]** | `MarcasController.php:402, 924`; `ReporteMarcasFinalesController.php:86`; `CortesEficienciaController.php:137-142, 1587-1632`; `AtadoresController.php:287-290, 488-495` | Medio | M | Medio | R2.1 + índices TejMarcasLine(Folio),(Date,Turno) y AtaMontado*(NoJulio,NoProduccion); cargar líneas existentes una vez con keyBy. Confianza media. |
| Programa Atadores sondea cada 5 s con pestaña oculta y recalcula el listado completo [NUEVO] | `programaAtadores/index.blade.php:734-769`; `AtadoresController.php:65-73`; `ProgramaAtadoresListado.php:25-41` (archivos con cambios sin commit) | Medio | S | Bajo | `if (document.hidden) return;`, 15 s, seleccionar solo id+status. |
| EdicionOrden/EdicionOrdenes: catálogos y consultas en cada render; `ax()` relee la fila [NUEVO] | `EdicionOrden.php:228-251, 780-789`; `EdicionOrdenes.php:66, 143-196` | Medio | S | Bajo | Cache::remember / `#[Computed(persist)]`; usar `$orden->AX`; badge con componente. |
| Fotos de usuario sin WebP y sin borrar la anterior (45 archivos para 7 referencias, 5.3 MB) [NUEVO; pendiente en memoria] | `UsuarioService.php:25, 55, 97-102`; `ModulosController.php:176` | Bajo | S | Bajo | `ImageOptimizer::optimizeAndSave` + borrar foto anterior. |
| Propagación de permisos: 2 consultas por usuario **[PREVIO-PENDIENTE R2.3]** | `ModulosController.php:545-580` | Bajo | S | Medio | UPDATE masivo + INSERT…SELECT. |
| Atadores: exists()+create() por elemento del catálogo (23 consultas en /iniciar) [NUEVO] | `AtadoresController.php:284-320, 661-690` | Bajo | S | Bajo | pluck + un insert(). |
| BPM Urd/Eng index sin límite (TelBPM sí limita) [NUEVO] | `UrdBpmController.php:19`; `EngBpmController.php:20`; `TelBpmController.php:37-39` | Bajo | S | Bajo | `limit(300)` como TelBpm. |

### 2.7 Navegación [NUEVO, 2026-09-22 tarde]

862 rutas (789 con nombre). `wire:navigate` en 2 enlaces. 0 breadcrumbs. El botón atrás es `ModuloService::rutaPadreDe()` (`ModuloService.php:139`).

| Hallazgo | Ubicaciones | Impacto | Esf. | Recomendación |
|---|---|---|---|---|
| 3 `route()` a nombres inexistentes (verificado con `route:list`) → `RouteNotFoundException` | `cargar-catalogos.blade.php:32` (la página da 500 al cargar; no existe endpoint de subida); `CodificacionController.php:628` (`codificacion.index`); `UsuarioController.php:210` (`usuarios.select` → `configuracion.usuarios.select`) | Alto | S | Corregir + test de contrato que cruce `route('x')` del código contra `route:list` |
| 186 `location.href/reload/replace` en 87 archivos (89 `reload()`), 18 con URL fija | vistas, `resources/js`, `public/js` | Alto (recarga completa, pierde filtros) | M | Refrescar componente/tabla en vez de recargar, al migrar cada módulo |
| SPA casi sin usar; el layout ya tiene `data-navigate-once` | `module-grid.blade.php:81`, navbar, logo | Medio-alto | M | `wire:navigate` en module-grid/navbar tras revisar re-ejecución de 135 vistas con script inline |
| 104 `fetch('/...')` con URL fija | `programa-tejido/index.js` (22), `captura-formula` (5), `CatalogBase.js` (4) | Medio | M | `data-url`/mapa de `route()` |
| 54 `Route::redirect` legacy; 26 con nombres de relleno (`tejido.` x14…) | `routes/modules/{tejido,planeacion,urdido,engomado}.php` | Medio | S | Apuntar `SYSRoles.Ruta` a las reales y borrar alias |
| `SYSRoles.Ruta` es texto libre sin validar | `ModuloService.php:197`, `module-grid.blade.php:20` | Medio | M | Comando que valide cada Ruta contra `route:list` |
| Sin breadcrumb en 3 niveles; fallback a `/produccionProceso` | `ModuloService.php:155-167` | Medio | S | `<x-layout.breadcrumb>` desde `arbolRutas()` |

### 2.8 JS → TS [NUEVO]

TS ya configurado (`tsconfig` estricto, `npm run typecheck`, 9,524 L en `.ts`, 8 tests en `tests/Js`). Queda: `resources/js` 18,433 L (13,218 son `programa-tejido/index.js`), `public/js` fuera de Vite 4,532 L, script inline en 135 vistas (~49k L). `tsconfig.include` apunta a `utils/**/*.ts` pero `utils` solo tiene `.js`.

Orden: 1) `utils/http.js` + `notifications.js` (S, contratos tipados); 2) `CatalogBase.js` + 4 subclases a Vite/TS (o desaparecen con ConTabla, 2.5); 3) `creacion-ordenes.js` a Vite/TS (junto con rehacer F3 Urd/Eng); 4) catcodificacion; 5) `programa-tejido/index.js` solo por módulos puros extraídos con test (no entero). No migrar: `app-pwa.js`, `bootstrap.js`, `app.js`, `charts.js`, `modal-cache-bootstrap.js`. Script inline: primero a archivos Vite, tipar después.

### 2.9 Tests en rutas de edición [NUEVO]

Tests usan sqlite en memoria + `tests/Concerns/UsesSqlsrvSqlite.php`. De 73 clases con update/guardar/destroy/toggle, ~50 sin test que las nombre (heurística).

| Ruta sin test | Ubicación | Patrón a copiar |
|---|---|---|
| `guardarMetrosFila` (IDOR, `(float)'abc'` → 0) | `EdicionOrden.php:152` | `EditarOrdenPermisosTest.php` |
| `FolioHelper` (15 llamadores) | `FolioHelper.php:19,92` | Unit + `UsesSqlsrvSqlite` |
| `actualizarRegistro` Cortes/Marcas (gate por string `puesto`) | `CortesEficienciaController.php:496`, `MarcasController.php:473` | `OrdenesTrabajoMecaControllerTest.php` |
| editar-ordenes-programadas `actualizar` | `EditarOrdenesProgramadasController.php`, `EditarOrdenesEngomadoController.php` | `ProgramarUrdidoActualizarStatusAxTest.php` |
| Líneas Programa Tejido | `ReqProgramaTejidoLineController.php` | `ProgramaTejidoUpdateTest.php` |
| BPM update/toggle | `TelBpmLineController.php:123,234`, `Eng/UrdBpmLineController` | `MantenimientoParosControllerTest.php` |
| Catálogos Planeación update (un test parametrizado) | `CatalogoPlaneacion/*` | `CatalogoCalibresLivewireTest.php` |
| Usuarios update/destroy | `UsuarioController.php` | `ConfiguracionRoutePermissionTest.php` |

Regla propuesta: todo cambio que toque un método de edición deja su test (patrón de la tabla) en el mismo commit.

---

## 3. Plan por fases

### Fase 0 — Quick wins (S, riesgo bajo, alto impacto) · ~1 semana · ~2,100 L

1. `Livewire::addPersistentMiddleware([EnsureModulePermission::class])` + gate en las 4 páginas Livewire sin chequeo. (Verificar idrol.)
2. IDOR `guardarMetrosFila` + test.
3. `InventarioReservasService`: quitar `%` inicial (3 líneas).
4. `bg-opacity-*` → `/50` (31 reemplazos). `max-md` → `max-w-md`.
5. Borrar `import '../css/app.css'` de app.js.
6. Quitar `SELECT @@VERSION` y bloque debug de `getOrdenProduccion`.
7. Barrido de muertos: 12 privados, `buildReporteResumenData` x2 **[R2.4]**, `mecanicos/MecActividadesController`, CRUD de `ReqProgramaTejidoLineController`, create/edit, públicos sin llamador **[R2.3]**, `internalToast` y else muertos, `openViewModal` de Atadores (~1,100 L).
8. **R1.1** completo + 5 endpoints POST sin consumidor (~920 L). **[PREVIO-PENDIENTE]**
9. Portar `http.post` a Urdido-BPM-Line (fix 419).
10. `document.hidden` en polling de Atadores; `paginate` → `PaginacionCompat` en VerificaMaquina.
11. 3 rutas rotas (2.7) + test de contrato de nombres de ruta.

### Fase 1 — S/M con algo más de riesgo o coordinación · 2-3 semanas · ~2,300 L

1. Portar "Cuenta/Calibre incorrecta" a ProgramBoard (o retirarla con negocio) → borrar tablero clásico Urd/Eng (~3,300 L, contado aquí). Preguntar al usuario.
2. Test de folios + unificar folio Trama y `FolioHelper::asegurarSecuencia` (junto con **R2.5**).
3. `TelegramNotifier` + dedup CortesEficiencia/MarcasFinales + helper Dompdf.
4. Snap de calendario con caché + índice ReqCalendarioLine; confirmar calendarios vencidos con Planeación.
5. Dedups PHP pequeños: ConfiguracionController, Felpa→Writer, densidad>40, InventarioTelares, CalificarJulios, filas vacías Eng/Urd, Mensajes, L.Mat CRUDO, Trazabilidad scope, BomMateriales query.
6. `OrdenKarlMayerService`: delegar desde el controlador.
7. Front: `notify.rangoFechas`, vistas de reporte gemelas (+ **R2.4** backend), `escapeHtml` exportado, `x-ui.flash`, `navbar/action-button` (arregla `:disabled`), page-title, estado-orden, empty-state/errores.
8. `<x-ui.dialog>`, `<x-ui.confirmar>`, `<x-campo>` **[PREVIO-PENDIENTE D-04]**.

### Fase 2 — M · 1-2 meses · ~6,000 L

1. Secuencias de Tejido → un componente Livewire (~2,200 L) **[amplía R2.5]**.
2. BPM captura Eng/Urd: trait + vistas compartidas (~930 L) tras decidir divergencias.
3. Catálogos CRUD → ConTabla: Atadores x3, *-actividades-bpm x3, Eficiencia+Velocidad, Pesos rollos, Julios, Operadores (~3,000 L).
4. `AxFlogsRepository` (unificar criterio de "flog más reciente"), catálogos AX con decisión TwVigente, `SchemaCache`, `ProgramaTejidoPosiciones`.
5. Índices HEAP **[R2.1]** + guardado de cortes en memoria; calendarios bajo demanda.

### Fase 3 — L

1. Migración Urd/Eng F7 (docs) ya, luego F5, rehacer F3, F6.
2. fetch → `http` y Swal toast → `notify` módulo por módulo **[PREVIO-PENDIENTE R3]**.
3. Generalizar `x-ui.modal-base` y adoptarlo al tocar cada vista.
4. Limpieza de imágenes (~27 MB) tras revisar access log.

### Pendientes de decisión de negocio

- Función "Cuenta/Calibre incorrecta": portar o retirar.
- `eliminarFila` en NuevoRequerimiento: botón o borrar.
- Filtro `TwVigente` en Engomado/Reenconado.
- Divergencias BPM Eng/Urd (hora del servidor, filtro KM/MC, redirects).
- /ventas con datos mock en ruta viva.
- Calendarios de capacidad vencidos (feb-jul 2026).
- Clientes externos de `/telegram/*` y `/orden-produccion`.
