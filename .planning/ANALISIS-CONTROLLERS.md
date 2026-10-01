# Informe consolidado: refactor de `app/Http/Controllers`

Antes de aceptar los 8 hallazgos de mayor impacto abrí los archivos. 7 se confirman. Uno estaba mal interpretado (la inversión de CalibreTrama) y lo corregí en el punto 2.4.

## 1. Resumen ejecutivo

- **Tamaño:** 140 archivos y unas 48.6k líneas. jscpd encuentra 130 clones exactos (2.364 líneas), pero la duplicación real es mayor: hay pares Urd/Eng, Cortes/Marcas y Secuencia* que solo cambian nombres. Se pueden eliminar unas **3.000–3.500 líneas** sin crear abstracciones nuevas.
- **Estructura:** unas 8.4k líneas que no son controllers están dentro de Controllers: `ProgramaTejido/funciones/` (6.169) y `helper/` (2.240). Las importan 15 clases de `app/` y 20 tests. A eso se suman 6 controllers que otros usan como servicios.
- **Bugs latentes confirmados:** `TRY_CONVERT` (no existe en 2008 R2), una rama inalcanzable y un salón erróneo en `TelaresController`, un `?? true` que no hace nada en `MensajesController`, un JOIN de L.Mat que repite filas y redondeos a 2 frente a 4 decimales en las fórmulas de eficiencia.
- **Deuda:** PHPMD marca 343 violaciones y hay 84 métodos de más de 100 líneas (el peor es `DividirTejido::dividir`: 618 líneas, CC 217). El 73 % del baseline de PHPStan (2.304 de 3.143) está en controllers, y 1.870 de esos errores son `property.notFound`.
- **Seguridad y consistencia:** 185 respuestas devuelven `getMessage()` al cliente aunque `bootstrap/app.php` ya maneja los 5xx (SEC-04). Hay 57 `catch (ValidationException)` que rearman el 422 a mano y 26 accesos AX dentro de controllers.

## 2. Mapa de problemas por categoría

### 2.1 Estructura

| # | Hallazgo | Ubicación | Propuesta mínima |
|---|---|---|---|
| E1 | Clases estáticas de dominio dentro de Controllers. Reciben `Request` y devuelven `JsonResponse` | `Planeacion/ProgramaTejido/funciones/*` (7), `helper/*` (7) | Mover a `app/Services/Planeacion/ProgramaTejido/` y `app/Support/Planeacion/` cambiando solo el namespace. El plan ya existe: PT 05.1 (`.planning/phases/20-arq-sec/20-04-ESTRUCTURA-BACKEND.md:58`) |
| E2 | Una Action instancia un controller ✔ | `Actions/Planeacion/ProgramaTejido/CambiarCalendario.php:90` → `CalendarioController.php:245-259` (3 puentes) | Usar `FormulasCalendario` directamente y borrar los puentes |
| E3 | Un Observer importa un controller solo por una constante ✔ | `Observers/ReqProgramaTejidoObserver.php:7,385` | Usar `LiberarMarbetesCalculator::PESO_ROLLO_KG_KARL_MAYER` |
| E4 | Controllers usados como servicios | `LiberarOrdenesController:692` y `ReimprimirOrdenesController:63` → `OrdenDeCambioFelpaController::generarExcelDesdeBD`; `AtadoresController:366` → `AtaDevolucionesController:561-633`; `Livewire/Crudo/MachineDetail.php:240` → `AlineacionController:265`; `Imports/CatCodificadosImport.php:114,338` → `CatCodificacionController:670-689` | Mover cada método a un Service o Calculator que ya exista |
| E5 | Felpa: 1.942 líneas, casi todo es un generador de Excel | `OrdenDeCambio/Felpa/OrdenDeCambioFelpaController.php` | Pasarlo a `app/Exports/OrdenDeCambioFelpaExport.php` y dejar solo 2 endpoints |
| E6 | Traits y "Vista" sueltos dentro de Controllers | `Atadores/Catalogos/RespondeCatalogo.php`, `ProgramaUrdEng/Concerns/RespuestasErrorUrdEng.php`, `*Vista.php` | Absorberlos en `Support/Http/Concerns/HandlesApiErrors` |
| E7 | Nombres inconsistentes | `mecanicos/`, `funciones/`, `helper/`, `Catalago*`, `PDFController` en la raíz (solo lo usa Urd/Eng) | Renombrar en la misma fase que E1, no por separado |

### 2.2 PHP duplicado

| # | Hallazgo | Ubicación | Líneas | Propuesta |
|---|---|---|---|---|
| D1 | 4 controllers Secuencia* clonados | `Tejido/Configuracion/Secuencia{CorteEficiencia,InvTelas,InvTrama,MarcasFinales}` | ~450 | Un controller con `{tipo}` y un mapa tipo→modelo/vista, como `EstandarCatalogoController` |
| D2 | BPM Urd/Eng (controller y Line) | `EngBpm*` frente a `UrdBpm*`; tercera copia en `TelBpmLineController:290` | ~350 | Un trait parametrizado por modelo y columna (`NomEmplAutoriza` frente a `NombreEmplAutoriza`) |
| D3 | Reportes BPM y semanal Urd/Eng | `ReportesEngomadoController:142-256,304-384` = `ReportesUrdidoController:795-908,1509-1571,29-45` | ~250 | Un servicio parametrizado, como el `ControlMermaReportService` que ya existe |
| D4 | Programar/ModuloProduccion Urd/Eng | ~466 líneas iguales; `usuarioPuedeEditar` está 4 veces con 2 semánticas | ~400 | Subir lo común a `ProduccionTrait` / `ProgramBoardActionService`. **Antes hay que decidir la migración a Livewire** (ver la sección 4) |
| D5 | Telegram armado a mano 6 veces | `AtadoresController:1051`, `CortesEficiencia:1241,1409`, `Marcas:807`, `NotificarMontadoJulio:318`, `TelegramController:39-80` | ~250 | Un método `TelegramEnvio::aModulo($modulo, $texto)`; el servicio ya existe |
| D6 | "¿Es supervisor?" con 4 criterios en 10 sitios | `EngBpm:144`, `UrdBpm:135`, `ProgramarEng:37`, `ProgramarUrd:35`, `CortesEf:69,560`, `Marcas:61,438,477`, `TelBpmLine:103,303`, `TelBpm:571` | ~120 | `Usuario::esSupervisor()` con un solo criterio. Hay que validar el criterio con negocio |
| D7 | Logo en base64 copiado 6 veces | `PDFController:196,311`, `AlineacionController:234`, `ReporteInvTelas:666`, `MecReportes:246,321` | ~70 | `TowellLogo::base64()` |
| D8 | `ConfiguracionController::procesarExcel` y `procesarExcelUpdate` | `:36` / `:218` | ~130 | Un método privado con un flag |
| D9 | `extractMcCoyNumber` copiado 3 veces | `ModuloProduccionUrdido:67`, `ProgramarUrdido:136`, `ReportesUrdido:47` | ~45 | Corregir el accessor `UrdProgramaUrdido::getMcCoyNumberAttribute` (no considera KM→4) y borrar las copias |
| D10 | `importProgress` implementado 2 veces sobre la misma clave | `CatCodificacionController:694`, `CodificacionController:1153` | ~30 | Dejar una sola implementación |
| D11 | `verificarTurnosOcupados` público repite al privado | `Tejedores/InventarioTelaresController:646-686` frente a `:899-939` | ~40 | Que el público llame al privado |
| D12 | `function_exists('userCan')` 16 veces; los envoltorios `calcularFormulasEficiencia` y `construirMaquina` | varios | ~60 | Borrar; Composer siempre carga el helper |

### 2.3 SQL duplicado o crudo

| # | Hallazgo | Ubicación | Propuesta |
|---|---|---|---|
| S1 | L.Mat con JOIN BOMVERSION que repite filas ✔. El servicio ya lo corrigió con `whereExists` (`LiberarBomCrudoResolver.php:31,43`) | `CatCodificacionController.php:453-498` (`:464`) | `app(LiberarBomCrudoResolver::class)->query(...)`; ~45 líneas menos |
| S2 | Flog vigente con 3 criterios de orden. `orderByDesc` sobre texto deja FL-999 arriba de FL-1000 | `CodificacionController:1355-1363`, `ProgramaTejidoCatalogosController:173-190`, `LiberarFlogSugeridoService:208` | Usar `LiberarFlogSugeridoService::sugerir()` y `ESTADOS_VIGENTES` |
| S3 | Modelo codificado por TamanoClave: 4 copias con 3 criterios; `whereRaw LTRIM(RTRIM(SalonTejidoId))` 5 veces | `TejidoHelpers:808-860,1000-1035`, `ProgramaTejidoCatalogosController:138-155`, `DividirTejido:836-866` | `ReqModelosCodificados::scopeSalon()` con `TelarSalonResolver::salonAliases` y una sola búsqueda |
| S4 | "Posicion + 10000" seguido de update fila por fila, 6 copias | `FinalizarOrdenes:365`, `MoverOrdenes:356,500`, `DragAndDropTejido:114`, `ProgramaTejidoOperaciones:282,342`, `Imports/ReqProgramaTejidoUpdateImport:564` | Agregar el desplazamiento a `ProgramaTejidoSecuenciaHelper::aplicarUpdatesDesdeRecalculo`; ~80 líneas menos |
| S5 | AX (`sqlsrv_ti`) en 10 controllers; calibres, configs y colores clonados | `ProduccionReenconadoCabezuela:30-98`, `CatLMat:508-770`, `EngProduccionFormulacion:422-447,636-680`, `NotificarMontRollos:277`, `AtaDevoluciones:45`, `LiberarOrdenes:181` | Reusar `CatalogoTramaService`, `CatalogosMaterialesLMatService`, `BomMaterialesService` y `LiberarHilosCatalogo`. **No crear** una capa de Repositories nueva; ~150 líneas menos |
| S6 | "Orden en proceso por telar" con 7 variantes, algunas sin salón ni `orderBy` | `TelaresController:241,282,440,463`, `CortesEf:859`, `ReporteInvTelas:580`, `MantenimientoParos:395`, `NotificarMontRollos:229` | Usar los scopes `salon()->telar()->enProceso()`, que ya existen (`ReqProgramaTejido.php:228-247`) |
| S7 | `DB::table` sobre `AtaMontadoTelas`, que tiene observer y así no se dispara | `AtadoresController:749,774,828,860,984` | Confirmar si es intencional; si no, usar el modelo |
| S8 | `INFORMATION_SCHEMA` reimplementado 6 veces | `CodificacionController:245,269,287`, `AtadoresController:815`, 2 Imports, `SSYSFoliosSecuencia:61` | `Schema::getColumns()` cacheado dentro de `StringTruncator` |
| S9 | Folios con MAX+1 a mano, o realineando `FolioHelper` | `Marcas:162-171`, `EngProduccionFormulacion:75-95`, `TelBpm:238-275` | `FolioHelper::obtenerFolioSugerido` / `obtenerSiguienteFolio` |
| S10 | Escrituras múltiples sin transacción y con `catch` vacíos | `Tejedores/InventarioTelaresController::destroy` (:449-600), `updateFecha` (:704-870; `$updateData` nunca se usa) | `DB::transaction` y `delete()`/`update()` en bloque |

### 2.4 Riesgos de SQL Server 2008 R2 y de comportamiento

| # | Hallazgo | Ubicación | Propuesta |
|---|---|---|---|
| R1 | **`TRY_CONVERT`** ✔. Cuando no hay match exacto, el guardado de BPM truena con 500 | `Tejedores/BPMTejedores/TelBpmController.php:502,537` | `CASE WHEN ISNUMERIC(...)=1 THEN CAST(... AS INT) END = ?`, como en `TelTelaresOperadorController:67`. Quitar `logTurnoDebug` (:563) |
| R2 | `whereIn` sin trocear: se pasa del límite de 2.100 parámetros con rangos de fecha amplios | `ReportesUrdido:827`, `ReportesEngomado:174`, `DuplicarTejido:204` | `array_chunk(…, 2000)`, como en `UrdBpmLineController:178` |
| R3 | Salón mal calculado ✔: devuelve `'ITEMA'` en lugar de `'SMIT'`, la rama 303-306 es inalcanzable y por defecto devuelve `'JACQUARD'` | `Tejido/InventarioTelas/TelaresController.php:319-335` | Borrar el método y usar `TelarSalonResolver::salonDesdeTelar()` |
| R4 | Redondeo distinto ✔: `TejidoHelpers:523,548` y `DateHelpers:429,445` redondean a 2 decimales, `FormulasCalendario:84-98` a 4 | — | Confirmar si los 4 decimales del calendario son intencionales (`CambiarCalendario.php:27` cita WR-10). Después, dejar `DateHelpers` llamando a `TejidoHelpers` (~150 líneas menos) |
| R5 | Inversión de CalibreTrama. **Corrección al informe de duplicado:** la inversión es deliberada en `UpdateTejido.php:860-861` y en `DuplicarTejido.php:741-742` (comentada como "Invertido"). La que no invierte es `ProgramaTejidoCatalogosController.php:369-370` | — | Validar con negocio qué ruta está mal antes de unificar el mapeo con una constante `COLUMNAS_PROGRAMA` |
| R6 | Dividir no copia `Repeticiones` ✔. El comentario dice que la columna no existe, pero sí existe (`ReqProgramaTejido.php:76,187`) | `DividirTejido.php:932-935` | Unificar con la tabla de mapeo de Duplicar (`:712`); ~100 líneas menos |
| R7 | `boolean() ?? x` no hace nada ✔: `Activo` queda en `false` si no se envía | `Configuracion/MensajesController.php:117-131,185-199` | Leer las banderas desde una constante `FLAGS` con un bucle y usar `boolean('Activo', true)`; ~80 líneas menos |
| R8 | El `elseif` de conversión a entero es inalcanzable | `DuplicarTejido.php:~832-837` | Decidir si `Peine` y `Pasadas*` se guardan como int |

### 2.5 Complejidad, errores y código muerto

| # | Hallazgo | Propuesta |
|---|---|---|
| C1 | `Ventas/TwHist{Vtas,Pedidos,Pronos}Controller.php` ✔: sin ruta ni referencias (`routes/modules/ventas.php:19` solo usa `VentasDatosController`) | Borrar los 3 (123 líneas) |
| C2 | `ReporteInvTelasController::obtenerEstadosOrdenesActivas` (:700) y `crearFilaTelarVacia` (:394) sin llamadas, y 33 variables sin uso (PHPMD). Algunas parecen lógica perdida: `DuplicarTejido:49 $flog`, `:279 $hayCambioClaveModelo` | Borrar las triviales y revisar una por una las de Duplicar y Dividir |
| C3 | 185 `catch` devuelven `getMessage()`; 57 `catch (ValidationException)` rearman el 422 | Borrar los `catch (\Throwable)` que solo loguean y responden 500, y los de `ValidationException`. Antes, confirmar con grep que ningún JS lee `success===false` en un 422 |
| C4 | Methods gigantes: `DividirTejido::dividir` (618/CC 217), `DuplicarTejido::duplicar` (457/120), `AtadoresController::save` (385/72, un despachador de 11 `$action`), `CatLMatController::guardarLmat` (335/61), `ProgramaTejidoOperacionesController::cambiarTelar` (252/41), `LiberarOrdenesController::liberar` (507, con 9 `rollBack`) | Partirlos con tests de caracterización primero (fase 4) |
| C5 | 1.870 `property.notFound` en el baseline: `ReqProgramaTejido` 837, `CatCodificados` 222, `ReqModelosCodificados` 135, `AtaMontadoTelasModel` 104 | Docblocks `@property` en esos 4 modelos, antes de mover código |
| C6 | `DividirTejido.php`: 33 líneas mojibake (`├│`) | Arreglarlas cuando se toque el archivo |

## 3. Plan de refactor por fases

**Fase 0. Bugs, con test por cada uno** (riesgo bajo, alto valor)
- Alcance: R1 (`TRY_CONVERT`), R3 (salón), R7 (flags de Mensajes), S1 (L.Mat a `LiberarBomCrudoResolver`), R2 (troceo de `whereIn`).
- Líneas: unas −120.
- Tests:
  - Feature de BPM Tejedores con un empleado "0123" frente a "123". Ojo: sqlite no reproduce el error de `TRY_CONVERT`, así que hay que añadir un test de grupo `sqlserver` o un guard que busque `TRY_CONVERT` en `app/`, como `SinPaginateNativoTest`.
  - Unit de salón por telar.
  - Feature de Mensajes sin `Activo`.

**Fase 1. Quick wins de borrado y reuso** (riesgo bajo)
- C1 (TwHist), C2 (código muerto y variables triviales), D12, E2 (puentes de Calendario), E3 (constante del Observer), D7 (logo), S9 (folios), D10, D11 y los clones internos.
- Líneas: unas −450.
- Tests: la suite actual más `phpstan`. Los borrados se validan con grep de referencias y `route:list`.

**Fase 2. Usar helpers que ya existen** (riesgo medio-bajo)
- D5 (`TelegramEnvio::aModulo`), S5 (AX a los servicios existentes), S6 (scopes `enProceso`), S8 (`Schema::getColumns`), S2 (flog único) y C3 (quitar los `catch` redundantes, archivo por archivo).
- Líneas: unas −700 a −900.
- Tests:
  - Telegram: un Feature con `Http::fake` que verifique destinatarios por módulo.
  - C3: un test de que un 500 responde con `trace_id` y sin mensaje interno.

**Fase 3. Fusionar clones de bajo riesgo** (riesgo medio)
- D1 (Secuencias), D8 (Configuracion), D9 (McCoy), D6 (supervisor, después de fijar el criterio con negocio) y S4 (desplazamiento de Posicion).
- Líneas: unas −750.
- Tests: Feature por cada tipo de Secuencia antes de fusionar (hoy no hay); test de sqlite para `ConfiguracionController` (hoy solo hay `sqlserver`).

**Fase 4. Mover clases fuera de Controllers** (riesgo bajo si es solo namespace)
- C5 (`@property` primero) y después E1 (`funciones/`, `helper/`), E5 (Felpa a `Exports`), E4, E6 y E7.
- Líneas: unas 0 movidas y −1.300 del baseline de PHPStan.
- Tests: suite completa y phpstan. Hacer el cambio con `git mv` más la actualización de imports, en un commit aparte.

**Fase 5. Unificar la lógica de ProgramaTejido** (riesgo alto)
- R4 (fórmulas), R5, R6 y R8 (mapeo de modelo codificado), S3 (búsqueda por TamanoClave).
- Líneas: unas −450.
- Tests: caracterización de Dividir, Duplicar, Update y Catalogos con los mismos datos de entrada, comparando las columnas resultantes. Antes, decisión de negocio sobre CalibreTrama y los decimales.

**Fase 6. Partir god methods con tests primero** (riesgo alto)
Cada uno lleva un plan propio:
- `CortesEficiencia::store`: el más barato, ya tiene 15 casos.
- `AtadoresController::save`: una ruta y un método por `$action`.
- `LiberarOrdenesController::liberar`: con `DB::transaction`.
- `cambiarTelar`.
- `guardarLmat`.
- `DividirTejido::dividir` y `duplicar` (PT 05.1).
- S10 (transacciones en InventarioTelares).

Tests: caracterización obligatoria. Hoy no tienen ninguno `AtaDevoluciones`, `CatLMat`, `InventarioTelares`, `ProgramaTejidoOperaciones` ni `ReportesUrdido`.

## 4. Qué NO refactorizar

- **Pares Urd/Eng (D2–D4):** no fusionarlos hasta decidir el orden respecto a la migración de Urd/Eng a Livewire (7 fases, en memoria del proyecto). Fusionar algo que se va a reemplazar es trabajo tirado.
- **PDO crudo en `CatCodificacionController.php:533-551`:** está justificado por rendimiento y comentado.
- **Migración masiva a FormRequest (152 validaciones inline):** solo pasar las reglas que se repiten o que son largas, como `CodificacionController::getValidationRules` o un `RangoFechasRequest` para reportes.
- **Helper nuevo de respuestas JSON:** no crear uno. Se fija `{success, message, data?}` de `HandlesApiErrors` y se migra al tocar cada archivo.
- **Capa nueva `app/Repositories/Ax`:** AX ya vive en `Services/`; se reusa eso.
- **Aplanar las 46 carpetas de un solo archivo:** es ruido de diff sin beneficio. Solo hacerlo junto con la fase 4 si se toca el módulo.
- **Métodos abstractos o hooks que parecen sin uso** (`get*ModelClass`, `maxKgNetoAllowed`, `Catalago*`): implementan `ProduccionTrait` o `EstandarCatalogoController`.
- **`AuditoriaProgramaTejidoController` y `TejHistorialInventarioTelares` con QueryBuilder:** no tienen modelo; es aceptable según la convención.
- **`whereRaw('1 = 0')` y `DB::connection('sqlsrv')` explícito:** es ruido menor; solo cambiarlo cuando se toque el archivo.

---
Archivos verificados para este informe:
- `C:\xampp\htdocs\Towell\app\Http\Controllers\Tejedores\BPMTejedores\TelBpmController.php`
- `C:\xampp\htdocs\Towell\app\Http\Controllers\Tejido\InventarioTelas\TelaresController.php`
- `C:\xampp\htdocs\Towell\app\Http\Controllers\Planeacion\ProgramaTejido\funciones\{UpdateTejido,DuplicarTejido,DividirTejido}.php`
- `C:\xampp\htdocs\Towell\app\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoCatalogosController.php`
- `C:\xampp\htdocs\Towell\app\Http\Controllers\Planeacion\ProgramaTejido\helper\{TejidoHelpers,DateHelpers}.php`
- `C:\xampp\htdocs\Towell\app\Http\Controllers\Configuracion\MensajesController.php`
- `C:\xampp\htdocs\Towell\app\Http\Controllers\Planeacion\CatCodificados\CatCodificacionController.php`
- `C:\xampp\htdocs\Towell\app\Services\Planeacion\Liberar\LiberarBomCrudoResolver.php`
- `C:\xampp\htdocs\Towell\app\Services\Planeacion\Calendarios\FormulasCalendario.php`
- `C:\xampp\htdocs\Towell\app\Observers\ReqProgramaTejidoObserver.php`
- `C:\xampp\htdocs\Towell\app\Actions\Planeacion\ProgramaTejido\CambiarCalendario.php`
- `C:\xampp\htdocs\Towell\routes\modules\ventas.php`