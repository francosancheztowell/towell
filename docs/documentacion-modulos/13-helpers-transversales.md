# Helpers, Servicios Transversales, PDF, Excel, Telegram

> Generado automáticamente — documentación detallada del módulo

## 1. Propósito del módulo

Este "módulo" no es un dominio de negocio en sí mismo, sino el **conjunto de piezas transversales** que dan soporte a todos los demás módulos productivos de Towell (Planeación, Tejido, Urdido, Engomado, Atadores, Tejedores, Mantenimiento). Agrupa:

- **Helpers de infraestructura**: folios secuenciales (`FolioHelper`), turnos y fecha de producción (`TurnoHelper`), truncado de strings al límite de columnas SQL Server (`StringTruncator`), auditoría vía stored procedure (`AuditoriaHelper`), optimización de imágenes con GD (`ImageOptimizer`), formato de números/fechas/foto de usuario (`format_helpers.php`) y detección de dispositivo por User-Agent (`device_helpers.php`).
- **Generación de PDF** de órdenes de Urdido/Engomado vía DomPDF (`PDFController` + vistas `resources/views/pdf/`).
- **Importación y exportación Excel** (maatwebsite/excel): 12 clases en `app/Imports/` y 28 clases en `app/Exports/` que alimentan catálogos, programas y reportes por módulo.
- **Notificaciones Telegram**: bot que envía mensajes a destinatarios suscritos por columna de módulo (`TelegramController`, modelo `SYSMensaje`).
- **Services transversales**: `ImportDataProcessor` (parser/normalizador de datos de Excel) y `PronosticosService` (consultas de pronósticos contra `TI_PRO`).
- **Comandos Artisan**: optimización masiva de imágenes y recálculo de fechas de Programa de Tejido.
- **Trait `ProduccionTrait`**: lógica compartida de captura de producción entre Urdido y Engomado.
- **MCP**: andamiaje de servidor MCP (sin tools definidos aún).

Su rol dentro del flujo productivo textil es **de soporte/cross-cutting**: cada vez que un módulo crea un registro (genera folio, audita, trunca campos), captura producción de urdido/engomado (turnos, fecha de producción, oficiales), imprime una orden, importa un catálogo desde Excel, exporta un reporte o avisa por Telegram, está usando estas piezas.

---

## 2. Rutas

Las únicas rutas HTTP propias de este ámbito son las de **Telegram** y las de **PDF** (estas últimas viven físicamente en los archivos de ruta de Urdido y Engomado, pero apuntan a `PDFController`).

| Método | URI | Controller@método | Middleware | Permiso requerido |
|--------|-----|-------------------|------------|-------------------|
| POST | `/telegram/send` | `Telegram\TelegramController@sendMessage` | `auth` (grupo en `web.php`) | No verifica `userCan` |
| GET | `/telegram/bot-info` | `Telegram\TelegramController@getBotInfo` | `auth` | No verifica `userCan` |
| GET | `/telegram/get-chat-id` | `Telegram\TelegramController@getChatId` | `auth` | No verifica `userCan` |
| GET | `/modulo-produccion-urdido/pdf` | `PDFController@generarPDFUrdidoEngomado` | `auth` | No verifica `userCan` (controla por `Status`) |
| GET | `/modulo-produccion-engomado/pdf` | `PDFController@generarPDFUrdidoEngomado` | `auth` | No verifica `userCan` (controla por `Status`) |

Notas de mapeo:
- `routes/modules/telegram.php` define el grupo `prefix('telegram')->name('telegram.')` con los tres endpoints; se incluye dentro del grupo `auth` de `routes/web.php:19`.
- `routes/modules/urdido.php:95` → `Route::get('/modulo-produccion-urdido/pdf', [PDFController::class, 'generarPDFUrdidoEngomado'])->name('modulo.produccion.urdido.pdf')`.
- `routes/modules/engomado.php:102` → `Route::get('/modulo-produccion-engomado/pdf', ...)->name('modulo.produccion.engomado.pdf')`.
- `routes/ai.php` está prácticamente vacío (registro MCP comentado).

---

## 3. Controllers

### 3.1 `app/Http/Controllers/PDFController.php`

Controlador único que genera la papeleta/orden PDF de Urdido y Engomado con DomPDF. Lee de los modelos `UrdProgramaUrdido`, `EngProgramaEngomado`, `UrdProduccionUrdido`, `EngProduccionEngomado`, `UrdJuliosOrden` (todos conexión `sqlsrv`).

#### `public function generarPDFUrdidoEngomado(Request $request)` — `PDFController.php:26`
- **Qué hace**: genera el PDF de una orden de urdido o engomado y lo devuelve inline (o como descarga en reimpresión).
- **Request (query string)**:
  - `orden_id` (obligatorio) — Id de la orden.
  - `tipo` = `urdido` | `engomado` (default `urdido`).
  - `parcial` = 1 (solo engomado) — imprime solo registros con `Impresion` NULL/0 y `Finalizar=1`, y tras generar el PDF los marca `Impresion=1`.
  - `reimpresion` = 1 — exige que la orden esté en `Status = 'Finalizado'`; devuelve el PDF como `attachment`.
- **Validaciones**:
  - Sin `orden_id` → JSON 422.
  - Orden inexistente → JSON 404.
  - `reimpresion=1` con `Status != 'Finalizado'` (urdido o engomado) → JSON 422.
  - Parcial sin registros pendientes → JSON 422 ("Todos los registros ya fueron impresos").
- **Tablas/queries (conexión `sqlsrv`)**:
  - `obtenerOrden()` → `UrdProgramaUrdido::find` / `EngProgramaEngomado::find`.
  - Si `tipo=urdido` y hay `Folio` → `EngProgramaEngomado::where('Folio', ...)` para el footer de engomado.
  - `obtenerRegistrosProduccion()` (ver privadas).
  - `UrdJuliosOrden::where('Folio')->whereNotNull('Julios')->orderBy('Julios')` para los julios.
  - En engomado, agrupa registros por `NoJulio` (1 papeleta por julio).
- **Efecto colateral**: en impresión parcial de engomado, `EngProduccionEngomado::whereIn('Id', $ids)->update(['Impresion' => 1])`.
- **Respuesta**: `response()` con `Content-Type: application/pdf` y `Content-Disposition` inline (normal) o attachment (reimpresión). Vista renderizada: `pdf.engomadopdf` (engomado) o `pdf.orden-urdido-engomado` (urdido). En error → JSON 500.
- **No usa** `userCan`, `FolioHelper` ni `TurnoHelper`; el control es por `Status`/`Impresion`.

#### Funciones protected relevantes
- `obtenerOrden(int|string $id, string $tipo)` — `PDFController.php:163`: devuelve el modelo de programa según tipo.
- `obtenerRegistrosProduccion(string $folio, string $tipo, bool $esParcial, bool $esReimpresion)` — `PDFController.php:182`: para **engomado** filtra `Finalizar=1` y `(Impresion IS NULL OR Impresion=0)`; si la columna `Impresion` no existe, cae a solo `Finalizar=1`. Para **urdido** devuelve todos los `UrdProduccionUrdido` del folio ordenados por `Id`.
- `cargarLogoBase64()` — `PDFController.php:214`: lee `public/images/fondosTowell/logo.png` y lo devuelve como data-URI base64 (sin GD); loguea warning si falta.
- `crearDompdf(string $html)` — `PDFController.php:250`: configura DomPDF (HTML5, `isRemoteEnabled`, fuente Arial, `chroot=public_path()`, papel `letter portrait`) y renderiza.
- `construirNombreArchivo($orden, string $tipo, bool $esParcial)` — `PDFController.php:279`: `ORDEN_ENGOMADO_{Folio}[_PARCIAL].pdf` o `ORDEN_URDIDO_ENGOMADO_{Folio}.pdf`.

### 3.2 `app/Http/Controllers/Telegram/TelegramController.php`

Envía notificaciones por Telegram usando la API HTTP del bot. Token y chat global desde `config('services.telegram.*')`.

#### `public function sendMessage(Request $request)` — `TelegramController.php:26`
- **Qué hace**: envía un mensaje de texto a todos los destinatarios suscritos a un módulo.
- **Request**: `mensaje` (texto, truncado a 4096 caracteres con `mb_substr`), `modulo` (columna del módulo en `SYSMensajes`, ej. `InvTrama`, `Desarrolladores`, `NotificarAtadoJulio`).
- **Lógica**:
  - Si falta `TELEGRAM_BOT_TOKEN` → JSON 500.
  - Si hay `modulo` → `SYSMensaje::getChatIdsPorModulo($modulo)` (registros con `Activo=1` y la columna del módulo `=1`).
  - Fallback: si no hay destinatarios, usa `config('services.telegram.chat_id')` (`.env` global).
  - Sin destinatarios → JSON 400 con mensaje guía.
  - Itera y hace `Http::post("https://api.telegram.org/bot{token}/sendMessage", ['chat_id','text'])` por cada chat; cuenta enviados/errores.
- **Respuesta**: JSON `{ success, message, enviados, total, errores[] }`. Error general → JSON 500.
- **Tablas**: `dbo.SYSMensajes` (`sqlsrv`) vía `SYSMensaje`.

#### `public function getBotInfo(Request $request)` — `TelegramController.php:113`
- **Qué hace**: consulta `getMe` de la API de Telegram para validar el bot.
- **Request**: ninguno relevante; respeta `wantsJson()`.
- **Respuesta**: JSON o vista `modulos.telegram.bot-info` con `{ success, data }`. Sin token o fallo → 500 / vista con error.

#### `public function getChatId(Request $request)` — `TelegramController.php:161`
- **Qué hace**: llama `getUpdates` y extrae los `chat_id` de quienes han escrito al bot, para facilitar la configuración de `TELEGRAM_CHAT_ID`.
- **Respuesta**: JSON o vista `modulos.telegram.get-chat-id` con `{ success, message, chat_ids[], instructions[] }`. Cada `chat_id` incluye `first_name`, `username`, `type`. Deduplica por `chat_id`.

---

## 4. Services y Helpers del ámbito

### 4.1 `app/Helpers/FolioHelper.php`
Genera folios secuenciales desde `dbo.SSYSFoliosSecuencias` (conexión `sqlsrv`, vía modelo `SSYSFoliosSecuencia` y `DB::table`).

- `obtenerSiguienteFolio(string $modulo, int $longitudConsecutivo = 5): string` — `FolioHelper.php:19`: delega en `SSYSFoliosSecuencia::nextFolio()`, que **incrementa** el consecutivo. **Usar solo al confirmar/guardar.** Devuelve p. ej. `TR00001`. Loguea y relanza en error.
- `obtenerFolioSugerido(string $modulo, int $longitudConsecutivo = 5): string` — `FolioHelper.php:41`: lee `prefijo`+`consecutivo` **sin incrementar**. **Usar para previsualización en UI.** Devuelve `''` si no hay secuencia.
- `obtenerInfoSecuencia(string $modulo): ?array` — `FolioHelper.php:67`: devuelve `['Id','Modulo','Prefijo','Consecutivo']` o null.
- `reiniciarConsecutivo(string $modulo, int $nuevoConsecutivo = 1): bool` — `FolioHelper.php:88`: actualiza `consecutivo` (uso de pruebas/reset).

> Diferencia clave: `obtenerSiguienteFolio` **consume** un folio (auto-incrementa); `obtenerFolioSugerido` **solo lee** (preview). Llamar a `obtenerSiguienteFolio` sin guardar deja "huecos" en la secuencia.

### 4.2 `app/Helpers/TurnoHelper.php`
Determina turno y fecha de producción en `America/Mexico_City`.

- `getTurnoActual(): string` — `TurnoHelper.php:14`: devuelve `'1'|'2'|'3'` por minutos desde medianoche:
  - Turno 1: 6:30–14:30 (390–870 min).
  - Turno 2: 14:30–22:30 (870–1350 min).
  - Turno 3: 22:30–6:30 (resto, cruza medianoche).
- `getDescripcionTurno(string $turno): string` — `TurnoHelper.php:44`: rango horario textual (ej. `6:30 AM - 2:30 PM`).
- `getTurnoFormato(string $turno): string` — `TurnoHelper.php:64`: `Turno 1/2/3`.
- `getFechaProduccion(): string` (Y-m-d) — `TurnoHelper.php:86`: regla de **fecha de producción**:
  - Entre 00:00 y 06:30 (porción post-medianoche del Turno 3) → fecha de **ayer**.
  - **Cierre mensual**: día 1 antes de las 08:30 → último día del mes anterior.
  - Resto → hoy.
- `generarFolio(): string` — `TurnoHelper.php:110`: folio simple `TRAMA-YYYYMMDD-T` (no usa `SSYSFoliosSecuencias`).

### 4.3 `app/Helpers/StringTruncator.php`
Evita el error SQL Server `SQLSTATE[22001]` truncando strings al límite real de cada columna. Mantiene un mapa estático `$fieldLimits` (p. ej. `CuentaRizo=>10`, `Observaciones=>200`, `NombreProducto=>100`, `UsuarioCrea/UsuarioModifica=>50`, etc.).

- `getFieldLimits(): array` — `StringTruncator.php:75`.
- `truncate(string $fieldName, mixed $value): mixed` — `StringTruncator.php:80`: trunca con `mb_substr` si el campo está en el mapa y excede el límite; respeta null/''.
- `truncateArray(array $data): array` — `StringTruncator.php:100`: aplica `truncate` por clave.
- `truncateModelAttributes(object $model): void` — `StringTruncator.php:111`: trunca atributos de un modelo Eloquent in-place.
- `getLimit(string $fieldName): ?int` — `StringTruncator.php:123`.
- `exceedsLimit(string $fieldName, mixed $value): bool` — `StringTruncator.php:128`.

### 4.4 `app/Helpers/AuditoriaHelper.php`
Rellena campos de auditoría y registra eventos vía stored procedure `dbo.sp_LogEvento` (conexión por defecto `sqlsrv`).

- `aplicarCamposAuditoria(Model $modelo, bool $soloModificacion = false): void` — `AuditoriaHelper.php:21`: detecta columnas existentes con `Schema::getColumnListing` y setea, si existen y están vacías, `FechaCreacion`/`HoraCreacion`/`UsuarioCrea`/`CreatedAt`/`CreatedBy` (solo en creación) y siempre los de modificación (`FechaModificacion`, `HoraModificacion`, `UsuarioModifica`, `UpdatedAt`, `UpdatedBy`).
- `obtenerUsuarioActual(): string` — `AuditoriaHelper.php:70`: `Auth::user()->nombre` o `numero_empleado`; `'Sistema'` si no hay sesión.
- `logEvento(string $tabla, string $accion, string $detalle='', ?Request $request=null, ?int $usuarioId=null, ?string $usuarioNombre=null, ?string $ip=null): void` — `AuditoriaHelper.php:92`: ejecuta `EXEC dbo.sp_LogEvento ?,?,?,?,?,?,?` (tabla, acción, detalle, usuarioId, usuarioNombre[≤120], ip[≤64], timestamp). Si falla, solo `Log::warning` (no rompe el flujo).
- `logDragDrop(string $tabla, int $registroId, array $antes, array $despues, ?Request $request=null): void` — `AuditoriaHelper.php:153`: construye un detalle "De Campo=val -> Campo=val" y llama `logEvento` con acción `DRAGDROP`.
- `logCambioFechaInicio(string $tabla, int $registroId, ?string $fechaAnterior, ?string $fechaNueva, string $contexto='UPDATE', ?Request $request=null, ?bool $enProceso=null): void` — `AuditoriaHelper.php:188`: registra cambios de `FechaInicio` (formato `d/m/Y H:i`) solo si difieren; acción `UPDATE`.

### 4.5 `app/Helpers/ImageOptimizer.php`
Redimensiona y comprime imágenes con PHP **GD**. Constantes: `DEFAULT_MAX_SIZE=400`, `JPEG_QUALITY=82`, `PNG_COMPRESSION=6`.

- `optimizeAndSave(UploadedFile $file, string $destPath, int $maxSize=400): string` — `ImageOptimizer.php:34`: carga, redimensiona si excede `maxSize`, guarda según extensión y devuelve el `basename`. Lanza `Exception` si GD no está o la imagen no se procesa.
- `optimizeFile(string $filePath, int $maxSize=400): bool` — `ImageOptimizer.php:87`: optimiza un archivo ya en disco (sobrescribe). Devuelve bool.
- Privadas: `loadImage()` (jpeg/png/gif/webp), `saveImage()` (calidad por tipo; webp cae a png si no hay `imagewebp`), `calculateDimensions()` (mantiene proporción).

### 4.6 `app/Helpers/format_helpers.php` (funciones globales, autoload Composer)
- `decimales($valor)` — `format_helpers.php:4`: formatea a 2 decimales y elimina ceros/punto sobrantes (`12.50`→`12.5`, `12.00`→`12`). Devuelve el valor tal cual si no es numérico.
- `formatearFecha($fecha)` — `format_helpers.php:14`: `d-m-Y` si la hora es `00:00:00`, si no `d-m-Y H:i`. Devuelve el original ante error de parseo.
- `formatearFechaInputLocal($fecha)` — `format_helpers.php:33`: formato `Y-m-d\TH:i` para inputs `datetime-local`.
- `getFotoUsuarioUrl($foto)` — `format_helpers.php:47`: URL de la foto en `public/images/fotos_usuarios/`, prefiriendo la versión `.webp` si existe, con cache-busting `?v=filemtime`. Null si no existe.

### 4.7 `app/Helpers/device_helpers.php` (funciones globales)
Detección de dispositivo, navegador y SO por User-Agent (usado típicamente en login/auditoría de sesiones).
- `getClientIpv4(): string` — `device_helpers.php:9`: IPv4 real (considera `X-Forwarded-For`); fallback `0.0.0.0`.
- `getDeviceInfo(): array` — `device_helpers.php:34`: `['tipo','navegador','sistema','ip','user_agent']`.
- `detectDeviceType(string $userAgent): array` — `device_helpers.php:56`: distingue tablet/móvil/desktop con muchísimas heurísticas (Samsung SM-T/X/P, iPad, Lenovo TB-, Surface, Kindle…). Devuelve `['nombre','modelo','icono','tipo']`.
- `detectDeviceModel(string $userAgent): string` — `device_helpers.php:163`: intenta el modelo comercial (iPhone/iPad por versión iOS, Samsung por código `SM-`, Pixel, Xiaomi/Redmi/POCO, Lenovo, Huawei, Honor, OnePlus, etc.).
- `detectBrowser(string $userAgent): array` — `device_helpers.php:356`: Edge/Opera/Chrome/Safari/Firefox/IE con versión mayor e icono FA.
- `detectOS(string $userAgent): array` — `device_helpers.php:396`: Windows/macOS/iOS/iPadOS/Android/Ubuntu/Linux/Chrome OS con versión.
- `getSamsungTabName(string $modelCode): string` — `device_helpers.php:452`: traduce código `SM-` (sin prefijo) a nombre comercial de Galaxy Tab.
- `getLenovoModelName(string $modelCode): string` — `device_helpers.php:579`: traduce código `TB-`/`YT-` a nombre comercial Lenovo.
- `getDeviceIdentifier(): string` — `device_helpers.php:668`: huella corta (8 chars en mayúsculas) md5 de UA+IP+Accept-Language.

### 4.8 `app/Services/ImportDataProcessor.php`
Parser/normalizador centralizado de datos de Excel (usado por las clases de `app/Imports/`). No toca BD; solo conversión y matching de columnas.
- `normKey(string $s): string` — `ImportDataProcessor.php:17`: normaliza encabezados (minúsculas, sin tildes/signos, espacios colapsados) para matching robusto.
- `getString(...)` / `getStringExact(...)` — `:37` / `:49`: extraen string con trim y `maxLen`; `getStringExact` prioriza coincidencia exacta antes que fuzzy.
- `getInteger(...)` — `:82`: limpia no-dígitos y castea a int.
- `getFloat(...)` — `:95`: normaliza separadores (coma→punto) y castea a float.
- `getDate(...)` — `:107`: serial Excel (`ExcelDate::excelToDateTimeObject`) o parseo por formatos (`d-m-Y`, `d/m/Y`, `Y-m-d`, …) a Carbon.
- `getTotalValue(...)` — `:129`: extrae el "TOTAL" validando que sea > 0.
- Privadas: `pick()` (estrategia exacta → normalizada → "contiene" → posición), `isValidTotalValue()`, `convertToInt()`.

### 4.9 `app/Services/PronosticosService.php`
Obtiene pronósticos de demanda desde **`TI_PRO`** (conexión `sqlsrv_ti`). Ya **no persiste** en `ReqPronosticos` (líneas comentadas).
- `obtenerPronosticos($meses): array` — `PronosticosService.php:18`: recibe meses (`['2025-08',...]` o `"2025-08,2025-09"`), construye rangos y devuelve `[batas, otros]`.
- Privadas:
  - `construirRangos($meses)` — `:32`: meses `Y-m` → rangos inicio/fin de mes.
  - `obtenerOtros(array $rangos)` — `:51`: query sobre `dbo.TwPronosticosFlogs` + joins (`TwFlogsItemLine`, `TWFLOGSTABLE`, `TWFLOGSCUSTOMER`) para `TIPOPEDIDO=2` y `ITEMTYPEID` fuera de 10–19 (toallas/otros).
  - `obtenerBatas(array $rangos)` — `:115`: query sobre `TwPronosticosFlogs` con joins a `TwFlogsItemLine`, `TWFLOGBOMID`, `TWFLOGSTABLE` para `ITEMTYPEID` 10–19 (batas), multiplicando `INVENTQTY * BomQty`.
- Ambas atrapan errores y devuelven `[]`, logueando con `Log::error`.

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|--------|------------------|----------|----|--------------|
| `Sistema\SSYSFoliosSecuencia` | `dbo.SSYSFoliosSecuencias` | `sqlsrv` | `Id` | `Modulo`, `Prefijo`, `Consecutivo` (método `nextFolio()` incrementa) |
| `Sistema\SYSMensaje` | `dbo.SYSMensajes` | `sqlsrv` | `Id` | `Token` (chat_id), `Activo`, columnas-módulo: `InvTrama`, `Desarrolladores`, `DesarrolladoresPrue`, `NotificarAtadoJulio`, `CorteSEF`, `MarcasFinales`, `ReporteElectrico`, `ReporteMecanico`, `ReporteTiempoMuerto`, `Atadores`, `UrdidoCalidad`, `Calidad` |
| `Urdido\UrdProgramaUrdido` | `dbo.UrdProgramaUrdido`* | `sqlsrv` | `Id` | `Folio`, `Status` (usado en PDF) |
| `Engomado\EngProgramaEngomado` | `dbo.EngProgramaEngomado`* | `sqlsrv` | `Id` | `Folio`, `Status` |
| `Urdido\UrdProduccionUrdido` | `dbo.UrdProduccionUrdido`* | `sqlsrv` | `Id` | `Folio` (registros del PDF de urdido) |
| `Engomado\EngProduccionEngomado` | `dbo.EngProduccionEngomado`* | `sqlsrv` | `Id` | `Folio`, `Finalizar`, `Impresion`, `NoJulio` (filtro/agrupación del PDF de engomado) |
| `Urdido\UrdJuliosOrden` | `dbo.UrdJuliosOrden`* | `sqlsrv` | `Id` | `Folio`, `Julios` |
| `Urdido\UrdCatJulios` | `dbo.UrdCatJulios`* | `sqlsrv` | — | `NoJulio`, `Tara`, `Departamento` (catálogo usado por `ProduccionTrait::getCatalogosJulios`) |

\* Nombre exacto de tabla según definición del modelo respectivo; este documento se centra en el uso transversal.

**Modelo `SYSMensaje` — métodos relevantes** (`SYSMensaje.php`):
- `columnasModuloPermitidas(): array` — `:77`: whitelist de columnas-módulo válidas para Telegram.
- `getChatIdsPorModulo(string $columna): array` — `:102`: si la columna está en la whitelist, devuelve los `Token` únicos de registros con `Activo=1` y `columna=1` (no nulos/vacíos). Usado por `TelegramController::sendMessage`.

---

## 6. Vistas Blade

Las vistas del ámbito son las **plantillas PDF** (DomPDF), que **no llevan JavaScript** (son HTML+CSS renderizado en servidor). Las vistas Telegram (`modulos.telegram.bot-info`, `modulos.telegram.get-chat-id`) pertenecen al módulo de Configuración/Telegram y se documentan en su módulo correspondiente.

### 6.1 `resources/views/pdf/orden-urdido-engomado.blade.php`
- **Propósito**: papeleta/orden de **Urdido** (con bloque de datos de Engomado en el footer cuando existe el folio en engomado).
- **Secciones UI**: cabecera con logo (`$logoBase64`) y título "ORDEN DE URDIDO Y ENGOMADO" (`@page margin 10mm`, fuente Arial 10pt); bloque de datos de la orden (`$orden`); tabla de registros de producción (`$registrosProduccion`); listado de julios (`$julios`); bloque/footer de engomado (`$ordenEngomado`); marca de reimpresión (`$esReimpresion`).
- **Scripts JS inline**: ninguno (PDF estático).

### 6.2 `resources/views/pdf/engomadopdf.blade.php`
- **Propósito**: papeleta/orden de **Engomado**, 1 papeleta por julio (datos agrupados en `$registrosPorJulio` por `NoJulio`).
- **Secciones UI**: cabecera con logo y título de engomado; datos de la orden (`$orden`); por cada grupo de `NoJulio`, su papeleta con los registros (`$registrosProduccion`/`$registrosPorJulio`); marca de reimpresión.
- **Scripts JS inline**: ninguno (PDF estático).

---

## 7. JS dedicado

Este ámbito **no incluye archivos JavaScript dedicados** (los helpers globales JS — `window.http`, `window.notify` — se documentan en el módulo de Frontend/infraestructura). Las plantillas PDF son HTML/CSS puro renderizado por DomPDF en el servidor, sin `<script>`.

---

## 8. Panorama Excel: Imports y Exports

### 8.1 Imports (`app/Imports/` — 12 clases)
Todas usan maatwebsite/excel y, en general, `ImportDataProcessor` para parsear. Persisten en tablas `sqlsrv` vía sus modelos de Planeación.

| Clase | Propósito | Módulo / modelo destino |
|-------|-----------|--------------------------|
| `CatCodificadosImport` | Importa catálogo de productos codificados por chunks con progreso (`excel_import_progress:` en caché); mapea filas con `CatCodificadosExcelRowMapper`. `ToCollection`, `WithChunkReading`, `WithEvents`. | Planeación / `CatCodificados` |
| `QueuedCatCodificadosImport` | Variante en **cola** (`ShouldQueue`) de `CatCodificadosImport` para archivos grandes. | Planeación / `CatCodificados` |
| `ReqAplicacionesImport` | Importa aplicaciones (`WithHeadingRow`, `WithBatchInserts`). | Planeación / `ReqAplicaciones` |
| `ReqCalendarioImport` | Importa calendarios (cabecera + líneas); orquesta `ReqCalendarioTab`/`ReqCalendarioLine`. `WithEvents`. | Planeación / `ReqCalendarioTab`, `ReqCalendarioLine` |
| `ReqCalendarioLineImport` | Importa **líneas** de calendario (fechas inicio/fin por `No Calendario`). | Planeación / `ReqCalendarioLine` |
| `ReqCalendarioTabImport` | Importa **cabeceras** de calendario (`updateOrCreate` por `CalendarioId`). | Planeación / `ReqCalendarioTab` |
| `ReqEficienciaStdImport` | Importa eficiencia estándar por Salón/Telar/Fibra (`ToCollection`). Columnas: Salon, No Telar, Fibra, Eficiencia, Densidad. | Planeación / `ReqEficienciaStd` |
| `ReqModelosCodificadosImport` | Importa modelos codificados (`updateOrCreate` por `TamanoClave`); con progreso en caché. | Planeación / `ReqModelosCodificados` |
| `ReqProgramaTejidoSimpleImport` | Importa programa de tejido (alta simple por modelo `ReqProgramaTejido`); consulta `TwFlogsCustomer`. | Planeación-Tejido / `ReqProgramaTejido` |
| `ReqProgramaTejidoUpdateImport` | **Actualiza** registros de programa de tejido desde Excel (`ToCollection`); consulta `TwFlogsCustomer`. | Planeación-Tejido / `ReqProgramaTejido` |
| `ReqTelaresImport` | Importa catálogo de telares. | Planeación / `ReqTelares` |
| `ReqVelocidadStdImport` | Importa velocidad estándar por telar/fibra. | Planeación / `ReqVelocidadStd` |

### 8.2 Exports (`app/Exports/` — 28 clases)
Generan reportes descargables por módulo. Las terminadas en `Sheet` son hojas individuales agregadas por un export "multiple sheets".

| Clase | Propósito | Módulo / hoja (title) |
|-------|-----------|------------------------|
| `AtaMontadoActividadesSheet` | Hoja de actividades de atado montado. | Atadores / `Actividades` |
| `AtaMontadoMaquinasSheet` | Hoja de máquinas de atado montado. | Atadores / `Máquinas` |
| `AtaMontadoTelasSheet` | Hoja de telas de atado montado. | Atadores / `Atadores` |
| `ProgramaAtadoresExport` | Export multi-hoja del programa de atadores (`WithMultipleSheets`, agrupa las 3 hojas `AtaMontado*`). | Atadores |
| `Reporte00EAtadoresExport` | Reporte 00E de atadores (un día/turno). `FromArray`, `WithEvents`. | Atadores / `00E Atadores` |
| `Reporte00EAtadoresRangoExport` | Reporte 00E de atadores por **rango** de fechas. | Atadores |
| `BpmEngomadoExport` | Reporte BPM de Engomado. | Engomado / `BPM Engomado` |
| `BpmUrdidoExport` | Reporte BPM de Urdido. | Urdido / `BPM Urdido` |
| `ControlMermaExport` | Control de merma de engomado (usa `ControlMermaReportService`). | Engomado / `Control Merma` |
| `CortesEficienciaExport` | Reporte de cortes y eficiencia (con formato de columnas). | Tejido / hoja de cortes-eficiencia |
| `DesarrolladoresExport` | Reporte diario de desarrolladores (tejedores). | Tejedores / `Desarrolladores {fecha}` |
| `DesarrolladoresReporteExport` | Reporte consolidado de desarrolladores. | Tejedores / `Desarrolladores` |
| `KaizenExport` | Reporte Kaizen. | Mejora continua / `Kaizen` |
| `MarcasFinalesExport` | Reporte de marcas finales (con formato). | Tejido / Marcas Finales |
| `ReporteMarcasFinalesDiaSheet` | Hoja diaria de marcas finales. | Tejido (hoja) |
| `ReporteMarcasFinalesExport` | Export multi-hoja de marcas finales (`WithMultipleSheets`). | Tejido |
| `PromedioParosEficienciaExport` | Promedio de paros vs eficiencia con **gráfica** (`WithCharts`). | Tejido / `SEMANA` |
| `ReporteInvTelasExport` | Reporte de inventario de telas. | Inventario / `Reporte Inv Telas` |
| `ReporteMantenimientoExport` | Reporte de mantenimiento. | Mantenimiento / `Reporte Mantenimiento` |
| `ReporteResumenEngomadoExport` | Resumen de engomado. | Engomado / `Resumen Engomado` |
| `ReporteResumenSemanalEngomadoExport` | Resumen **semanal** de engomado con gráficas. | Engomado / `Resumen Semanal Engomado` |
| `ReporteResumenSemanalUrdidoExport` | Resumen **semanal** de urdido con gráficas. | Urdido / `Resumen Semanal Urdido` |
| `ReporteRpmSemanalExport` | RPM semanal con gráfica. | Tejido/Urdido / `RPM semanal` |
| `ReportesUrdidoExport` | Reportes de urdido. | Urdido / `Reporte Urdido` |
| `RoturasMillonExport` | Roturas por millón (calidad de tejido). | Tejido / `Roturas x Millón` |
| `Saldos2026Export` | Reporte de saldos 2026 (clase `final`, lógica propia sin interfaces estándar). | Planeación/Tejido |
| `TejedoresReporteExport` | Reporte BPM de tejedores. | Tejedores / `BPM Tejedores` |

**Consumidores conocidos** (controllers que instancian exports): `Urdido\ReportesUrdidoController`, `Tejido\Reportes\PromedioParosEficienciaController`, `Tejido\MarcasFinales\MarcasController`, `Tejido\CortesEficiencia\CortesEficienciaController`, `Atadores\Reportes\ReportesAtadoresController`, `Engomado\ReportesEngomadoController`.

---

## 9. Comandos Artisan, Trait y MCP

### 9.1 `app/Console/Commands/OptimizeModuleImagesCommand.php`
- **Signature**: `images:optimize-modules {--folder=fotos_modulos} {--max=400} {--dry-run}`.
- **Qué hace**: recorre `public/images/{folder}` y optimiza jpg/jpeg/png/gif/webp con `ImageOptimizer::optimizeFile`. Aborta si no hay GD; `--dry-run` solo lista. Muestra barra de progreso y resumen `{ok, fail}`.

### 9.2 `app/Console/Commands/RecalcularFechasProduccionCommand.php`
- **Signature**: `programa-tejido:recalcular-fechas-produccion {--all} {--hours=2}`.
- **Qué hace**: recalcula `FechaInicio` de `ReqProgramaTejido` cuando `Produccion`/`SaldoPedido` se actualizaron por SQL externo. Sin `--all`, procesa solo `EnProceso=1` o `UpdatedAt` dentro de `--hours`.
- **Lógica**: `asignarEnProcesoSiFalta()` garantiza que el **primer** registro (por `Posicion`/`FechaInicio`) de cada telar tenga `EnProceso=1` (y limpia el resto). Luego desactiva el observer (`unsetEventDispatcher`), llama `BalancearTejido::recalcularRegistroPorProduccion()` por registro, y restaura el observer (`ReqProgramaTejidoObserver`) en `finally`. `EnProceso=1` usa `now()` como inicio.

### 9.3 `app/Traits/ProduccionTrait.php`
Trait compartido por `ModuloProduccionUrdidoController` y `ModuloProduccionEngomadoController`. Cada controller debe implementar: `getProduccionModelClass()`, `getProgramaModelClass()`, `getDepartamento()`, `shouldRoundKgBruto()`, `getModuleNameForPermissions()`. Modelos involucrados: producción/programa de Urdido o Engomado y `UrdCatJulios` (todos `sqlsrv`).

**Endpoints públicos (JSON) compartidos**:
- `getCatalogosJulios(): JsonResponse` — `:213`: lista `UrdCatJulios` (`NoJulio`, `Tara`, `Departamento`) filtrando por `getDepartamento()`.
- `guardarOficial(Request)` — `:242`: guarda oficial 1/2/3 de un registro. Valida `registro_id`, `numero_oficial in:1,2,3`, `cve_empl≤30`, `nom_empl≤150`, `metros≥0`, `turno in:1,2,3`. Reglas: requiere clave **o** nombre; no repite No. Operador en el mismo registro; **secuencialidad** (Oficial 2 exige Oficial 1; Oficial 3 exige Oficial 2); máximo 3 oficiales; **advierte** (no bloquea) turno repetido. Si es el primer Oficial 1 del folio, **propaga** a registros del mismo folio sin `HoraInicial`. Verifica `userCan('modificar', módulo)` vía `ensureUserCanEdit()`.
- `eliminarOficial(Request)` — `:401`: limpia `CveEmpl/NomEmpl/Metros/Turno` del oficial N.
- `actualizarTurnoOficial(Request)` — `:432`: actualiza `Turno{N}` (requiere oficial registrado en esa posición).
- `actualizarFecha(Request)` — `:476`: actualiza `Fecha` de un registro.
- `actualizarJulioTara(Request)` — `:509`: setea `NoJulio` (≤10) y `Tara` (≥0); recalcula `KgNeto = KgBruto - Tara`; rechaza si excede `maxKgNetoAllowed()`.
- `actualizarKgBruto(Request)` — `:570`: setea `KgBruto` (≥0; redondeo a 2 si `shouldRoundKgBruto()`); recalcula `KgNeto`; valida límites `maxKgBruto`/`maxKgNeto`.
- `actualizarHoras(Request)` — `:643`: setea `HoraInicial`/`HoraFinal` (regex `HH:MM` 24h).
- `marcarListo(Request)` — `:681`: marca/desmarca `Finalizar`. Bloquea si `AX=1` (ya enviado a AX). Al marcar, exige `HoraInicial`, `HoraFinal`, `NoJulio`, `KgBruto≥0` (y `KgNeto≥0`) y respeta límites. Al desmarcar, llama `onRegistroDesmarcado()` (hook; Engomado resetea `Impresion`). Actualiza `Status` del programa (`En Proceso`↔`Parcial`).

**Helpers protected del trait** (resumen): `maxKgNetoAllowed()`/`maxKgBrutoAllowed()` (límites por módulo), `jsonIfKgNetoExceedsLimit()`/`jsonIfKgBrutoExceedsLimit()` (422 si excede), `ensureUserCanEdit()` (403 si no `userCan('modificar', módulo)`), `traitHasNegativeKgNetoByFolio()`, `traitHasHoraInicialCaptured()`, `traitRefrescarFechaEnRegistrosVacios()` (refresca `Fecha`=`TurnoHelper::getFechaProduccion()` y `Turno1`=turno del usuario en registros aún vacíos y sin `HoraInicial`), `traitAutollenarOficial1EnRegistrosSinHoraInicial()`, `resolveMonthlyClosureDateContext()` (cierre mensual día 1 antes de 08:30), `updateProduccionFechaByFolio()`, `onRegistroDesmarcado()` (hook), `validarHorasRegistros()` (deshabilitado, retorna null).

### 9.4 `app/Mcp/Servers/WeatherServer.php` y `routes/ai.php`
- `WeatherServer` (`laravel/mcp`): servidor de andamiaje con atributos `#[Name('Weather Server')]`, `#[Version('0.0.1')]`, `#[Instructions(...)]`. Arrays `$tools`, `$resources`, `$prompts` **vacíos** — sin funcionalidad activa.
- `routes/ai.php`: solo `use Laravel\Mcp\Facades\Mcp;` y un registro web **comentado** (`// Mcp::web('/mcp/demo', ...)`). No expone endpoints MCP activos.

---

## 10. Lógica de negocio y reglas

### Folios
- **Dos modos**: preview (`obtenerFolioSugerido`, no incrementa) vs commit (`obtenerSiguienteFolio`, incrementa en `dbo.SSYSFoliosSecuencias`). Llamar al commit sin guardar el registro genera huecos en la secuencia → usar siempre el sugerido en la UI y el siguiente solo al persistir.
- `TurnoHelper::generarFolio()` es un folio "ligero" `TRAMA-YYYYMMDD-T` independiente de la tabla de secuencias.

### Turnos y fecha de producción
- Turnos por minutos desde medianoche (T1 390–870, T2 870–1350, T3 resto).
- **Fecha de producción** (`getFechaProduccion`): la madrugada (00:00–06:30, Turno 3) se imputa al **día anterior**; el **día 1 antes de las 08:30** se imputa al **último día del mes anterior** (cierre mensual). Esta misma regla de cierre se reimplementa en `ProduccionTrait::resolveMonthlyClosureDateContext()`.

### Captura de producción (Urdido/Engomado)
- **Oficiales** secuenciales (1→2→3), máximo 3, sin repetir No. Operador en el registro; el primer Oficial 1 del folio se propaga a registros sin `HoraInicial`.
- **Kg**: `KgNeto = KgBruto - Tara`; Engomado redondea a 2 decimales (`shouldRoundKgBruto`) y aplica límites (p. ej. Urdido `maxKgNeto≈700`, Engomado `maxKgBruto≈2000`).
- **Marcar listo** exige todos los campos clave y respeta el bloqueo `AX=1` (registro ya enviado a AX no editable). El `Status` del programa transita `En Proceso` ↔ `Parcial` según registros `Finalizar=1`.

### PDF (DomPDF)
- Urdido imprime **todos** los registros del folio; Engomado imprime **solo** `Finalizar=1` no impresos, agrupados **1 papeleta por `NoJulio`**.
- **Impresión parcial** (engomado): tras generar el PDF marca `Impresion=1`; al **desmarcar** Finalizar, `onRegistroDesmarcado` resetea `Impresion` (vuelve a ser imprimible).
- **Reimpresión** exige `Status='Finalizado'` y descarga como attachment. El logo se incrusta en base64 sin GD.

### Excel
- Imports usan `ImportDataProcessor` para matching robusto de encabezados (exacto → normalizado → "contiene" → posición) y conversión de tipos (incl. seriales de fecha Excel). Algunos reportan progreso en caché (`excel_import_progress:*`) y existe variante encolada (`QueuedCatCodificadosImport`).
- Exports producen reportes por módulo; varios incluyen gráficas (`WithCharts`) y multi-hoja (`WithMultipleSheets`: programa de atadores y marcas finales).

### Auditoría y truncado (efectos colaterales globales)
- `AuditoriaHelper::aplicarCamposAuditoria` rellena automáticamente columnas de auditoría según existan en la tabla; `logEvento` registra en `dbo.sp_LogEvento` **sin romper** el flujo si falla.
- `StringTruncator` previene `SQLSTATE[22001]` recortando strings al límite real de columna antes de insert/update — clave porque SQL Server con `dbo.` impone longitudes estrictas.

### Telegram (integración cross-módulo)
- Suscripción **por columna de módulo** en `dbo.SYSMensajes` (`Activo=1` + columna=1). El módulo de origen llama `POST /telegram/send` con `{mensaje, modulo}`; el controller resuelve destinatarios vía `SYSMensaje::getChatIdsPorModulo` y, en su defecto, el `TELEGRAM_CHAT_ID` global de `.env`. Límite de 4096 caracteres por mensaje. Config en `config/services.php` (`bot_token`, `chat_id`).

### Pronósticos
- `PronosticosService` lee de **`TI_PRO`** (`sqlsrv_ti`), distinguiendo **batas** (ITEMTYPEID 10–19, cantidad ponderada por BOM) y **otros** (resto), sin persistir resultados (capa de solo lectura para planeación).

---

## Notas / limitaciones

- Los rangos del trait `maxKgNeto/maxKgBruto` (≈700 / ≈2000) provienen de las subclases concretas (controllers de Urdido/Engomado), no del trait; se citan como referencia aproximada.
- El conteo real de exports es **28** (no 17): el alcance original subestimaba el número; se documentaron todas. Imports reales: **12** (no 11) incluyendo la variante encolada.
- Las vistas Telegram (`modulos.telegram.bot-info`, `get-chat-id`) y los JS globales (`window.http`, `window.notify`) pertenecen a otros módulos y solo se referencian aquí.
- El detalle interno de cada clase Export/Import (encabezados, estilos, queries completas) se resumió en 2–4 líneas por clase, no exhaustivo a nivel de método, dado el volumen (40 clases).
- MCP no tiene funcionalidad activa (servidor de andamiaje, ruta comentada).
- No se documentó cada `<script>` de blade porque las únicas vistas del ámbito (PDF) no contienen JavaScript.
