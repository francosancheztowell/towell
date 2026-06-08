# Planeación — Catálogos

> Generado automáticamente — documentación detallada del módulo

## 1. Propósito del módulo

El submódulo **Planeación → Catálogos** centraliza los datos maestros (catálogos) que alimentan los cálculos del **Programa de Tejido** y, por cascada, del resto del flujo productivo textil (urdido, engomado, atadores, tejedores). Es el origen de los parámetros estándar (eficiencia, velocidad), de la geometría/identidad de los telares, de los calendarios laborales que determinan fechas de producción, de las aplicaciones (factores de químico/aplicación), de la matriz de hilos (cálculo de metros de rizo) y de los pesos por rollo.

Su rol dentro del flujo: cada cambio en un catálogo no es estático, sino que **dispara recálculos en los programas de tejido vigentes** (`ReqProgramaTejido` y sus líneas `ReqProgramaTejidoLine`). Por eso varios controllers, además del CRUD, contienen lógica de propagación: al cambiar una eficiencia/velocidad se recalculan las fórmulas de producción de los programas afectados; al cambiar líneas de calendario se reprograman fechas; al cambiar el factor de una aplicación o los calibres de un hilo se reescriben columnas calculadas en las líneas diarias.

Submódulos que abarca (cada uno con su controller dedicado):

| Submódulo | Controller | Tabla principal |
|---|---|---|
| Telares | `CatTelares/CatalagoTelarController` | `dbo.ReqTelares` |
| Eficiencias estándar | `CatEficiencias/CatalagoEficienciaController` | `dbo.ReqEficienciaStd` |
| Velocidades estándar | `CatVelocidades/CatalagoVelocidadController` | `dbo.ReqVelocidadStd` |
| Calendarios (cabecera + líneas) | `CatCalendarios/CalendarioController` | `dbo.ReqCalendarioTab`, `dbo.ReqCalendarioLine` |
| Aplicaciones | `CatAplicaciones/AplicacionesController` | `dbo.ReqAplicaciones` |
| Matriz de hilos | `CatMatrizHilos/MatrizHilosController` | `ReqMatrizHilos` |
| Pesos por rollo | `CatPesosRollos/PesosRollosController` | `ReqPesosRolloTejido` |

> NOTA: `ModelosCodificados/CodificacionController` (codificación de modelos) **no** forma parte de este documento; está cubierto por otro.
>
> El directorio de vistas `resources/views/catalagos/` conserva el typo intencional del proyecto (debería ser `catalogos`). Se respeta tal cual.

---

## 2. Rutas

Todas las rutas viven en `routes/modules/planeacion.php` y se cargan dentro del grupo autenticado (middleware `auth`). El ámbito de catálogos está repartido en dos bloques:

- Bloque `Route::prefix('catalogos')->name('catalogos.')` (líneas ~39–78): solo los `index` y el CRUD de pesos-rollos y matriz-hilos parcialmente.
- Bloque plano dentro de `Route::prefix('planeacion')->name('planeacion.')` (líneas ~112–159): la mayoría del CRUD (telares, eficiencia, velocidad, calendarios, aplicaciones, matriz-hilos) y las cargas de Excel.

Existen además **redirects 301** desde las URIs antiguas hacia las nuevas (líneas 41–46).

### Telares

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/catalogos/telares` | `CatalagoTelarController@index` | auth | — (sin `userCan` en código) |
| GET | `/planeacion/telares` | `CatalagoTelarController@index` (`telares.index`) | auth | — |
| POST | `/planeacion/telares` | `CatalagoTelarController@store` | auth | — |
| PUT | `/planeacion/telares/{telar}` | `CatalagoTelarController@update` | auth | — |
| DELETE | `/planeacion/telares/{telar}` | `CatalagoTelarController@destroy` | auth | — |
| POST | `/planeacion/telares/excel` | `CatalagoTelarController@procesarExcel` (`telares.excel.upload`) | auth | — |

### Eficiencias

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/catalogos/eficiencia` | `CatalagoEficienciaController@index` (`catalogos.eficiencia`) | auth | — |
| GET | `/planeacion/eficiencia` | `CatalagoEficienciaController@index` (`eficiencia.index`) | auth | — |
| POST | `/planeacion/eficiencia` | `CatalagoEficienciaController@store` | auth | — |
| PUT | `/planeacion/eficiencia/{eficiencia}` | `CatalagoEficienciaController@update` (RMB) | auth | — |
| DELETE | `/planeacion/eficiencia/{eficiencia}` | `CatalagoEficienciaController@destroy` (RMB) | auth | — |
| POST | `/planeacion/eficiencia/excel` | `CatalagoEficienciaController@procesarExcel` | auth | — |

### Velocidades

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/catalogos/velocidad` | `CatalagoVelocidadController@index` (`catalogos.velocidad`) | auth | — |
| GET | `/planeacion/velocidad` | `CatalagoVelocidadController@index` (`velocidad.index`) | auth | — |
| POST | `/planeacion/velocidad` | `CatalagoVelocidadController@store` | auth | — |
| PUT | `/planeacion/velocidad/{velocidad}` | `CatalagoVelocidadController@update` (RMB) | auth | — |
| DELETE | `/planeacion/velocidad/{velocidad}` | `CatalagoVelocidadController@destroy` (RMB) | auth | — |
| POST | `/planeacion/velocidad/excel` | `CatalagoVelocidadController@procesarExcel` | auth | — |

### Calendarios

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/catalogos/calendarios` | `CalendarioController@index` (`catalogos.calendarios`) | auth | — |
| GET | `/planeacion/calendarios` | `CalendarioController@index` (`calendarios.index`) | auth | — |
| GET | `/planeacion/calendarios/json` | `CalendarioController@getCalendariosJson` | auth | — |
| GET | `/planeacion/calendarios/{calendario}/detalle` | `CalendarioController@getCalendarioDetalle` | auth | — |
| POST | `/planeacion/calendarios` | `CalendarioController@store` | auth | — |
| PUT | `/planeacion/calendarios/{calendario}` | `CalendarioController@update` | auth | — |
| PUT | `/planeacion/calendarios/{calendario}/masivo` | `CalendarioController@updateMasivo` | auth | — |
| DELETE | `/planeacion/calendarios/{calendario}` | `CalendarioController@destroy` | auth | — |
| POST | `/planeacion/calendarios/lineas` | `CalendarioController@storeLine` | auth | — |
| PUT | `/planeacion/calendarios/lineas/{linea}` | `CalendarioController@updateLine` | auth | — |
| DELETE | `/planeacion/calendarios/lineas/{linea}` | `CalendarioController@destroyLine` | auth | — |
| DELETE | `/planeacion/calendarios/{calendario}/lineas/rango` | `CalendarioController@destroyLineasPorRango` | auth | — |
| POST | `/planeacion/calendarios/{calendario}/recalcular-programas` | `CalendarioController@recalcularProgramas` | auth | — |
| POST | `/planeacion/calendarios/excel` | `CalendarioController@procesarExcel` | auth | — |

### Aplicaciones

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/catalogos/aplicaciones` | `AplicacionesController@index` (`catalogos.aplicaciones`) | auth | — |
| GET | `/planeacion/aplicaciones` | `AplicacionesController@index` (`aplicaciones.index`) | auth | — |
| POST | `/planeacion/aplicaciones` | `AplicacionesController@store` | auth | — |
| PUT | `/planeacion/aplicaciones/{aplicacion}` | `AplicacionesController@update` | auth | — |
| DELETE | `/planeacion/aplicaciones/{aplicacion}` | `AplicacionesController@destroy` | auth | — |
| POST | `/planeacion/aplicaciones/excel` | `AplicacionesController@procesarExcel` | auth | — |

### Matriz de hilos

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/catalogos/matriz-hilos` | `MatrizHilosController@index` (`catalogos.matriz-hilos`) | auth | — |
| GET | `/planeacion/catalogos/matriz-hilos/list` | `MatrizHilosController@list` | auth | — |
| GET | `/planeacion/catalogos/matriz-hilos/{id}` | `MatrizHilosController@show` | auth | — |
| POST | `/planeacion/catalogos/matriz-hilos` | `MatrizHilosController@store` | auth | — |
| PUT | `/planeacion/catalogos/matriz-hilos/{id}` | `MatrizHilosController@update` | auth | — |
| DELETE | `/planeacion/catalogos/matriz-hilos/{id}` | `MatrizHilosController@destroy` | auth | — |

### Pesos por rollo

| Método | URI | Controller@método | Middleware | Permiso |
|---|---|---|---|---|
| GET | `/planeacion/catalogos/pesos-rollos` | `PesosRollosController@index` | auth | — |
| POST | `/planeacion/catalogos/pesos-rollos` | `PesosRollosController@store` | auth | — |
| PUT | `/planeacion/catalogos/pesos-rollos/{id}` | `PesosRollosController@update` | auth | — |
| DELETE | `/planeacion/catalogos/pesos-rollos/{id}` | `PesosRollosController@destroy` | auth | — |

> NOTA sobre permisos: ninguno de los controllers de este ámbito invoca `userCan()` ni `userPermissions()` en el cuerpo de sus métodos. El control de acceso es a nivel de **menú/visibilidad** (el módulo "Catálogos" está bajo `moduloPadre = 104`, según `showSubModulosNivel3`), pero las acciones de escritura no validan permisos granulares en el backend. Las vistas muestran/ocultan botones vía el componente `<x-buttons.catalog-actions>`; la columna de permiso en SYSRoles relevante usaría el typo intencional `reigstrar`.

---

## 3. Controllers

### 3.1 `CatTelares/CatalagoTelarController`

Archivo: `app/Http/Controllers/Planeacion/CatalogoPlaneacion/CatTelares/CatalagoTelarController.php`. Todas las operaciones usan el modelo `ReqTelares` (conexión por defecto `sqlsrv`, tabla `dbo.ReqTelares`). El registro se identifica externamente por un `uniqueId` compuesto `"Salon_Telar"`.

- **`index(Request $request)`** (L16). Lista telares con filtros opcionales por servidor: `salon`, `telar`, `nombre`, `grupo` (todos `LIKE %valor%`). Ordena por `SalonTejidoId`, `NoTelarId`. Devuelve la vista `catalagos.catalagoTelares` con `telares` y `noResults`. Ante excepción, registra en log y devuelve la vista con colección vacía y mensaje de error.
- **`procesarExcel(Request $request)`** (L38). Importa Excel de telares. Valida `archivo_excel` (`required|file|mimes:xlsx,xls|max:10240`). Dentro de transacción ejecuta `Excel::import(new ReqTelaresImport(), $file)`, hace commit y devuelve JSON con estadísticas (`processed_rows`, `created_rows`, `updated_rows`, `skipped_rows`) provenientes de `getStats()`. Rollback + 500 ante error.
- **`store(Request $request)`** (L68). Crea un telar. Valida `SalonTejidoId` (req, max 20), `NoTelarId` (req, max 10), `Nombre` (nullable, max 30), `Grupo` (nullable, max 30). Verifica duplicado por combinación salón+telar (422 si existe). Si no se envía nombre, lo genera con `makeName()`. Devuelve JSON `{success, message}`.
- **`update(Request $request, $uniqueId)`** (L103). Actualiza telar identificado por `uniqueId`. Parsea el id con `strrpos($uniqueId,'_')` (respeta salones con guiones bajos, partiendo por el **último** `_`). Mismas validaciones que `store`. Si cambia la combinación salón/telar valida duplicados (422). Regenera nombre si viene vacío. 404 si no se encuentra.
- **`destroy($uniqueId)`** (L153). Elimina por `uniqueId` (mismo parseo). 404 si no existe; devuelve JSON de éxito con el nombre eliminado.
- **`makeName($salon, $telar): string`** (privada, L175). Generador de nombre cuando el usuario no lo provee: prefijo `JAC` si el salón contiene "JACQUARD", `Smith` si contiene "SMITH", o las primeras 3 letras en mayúsculas; concatena con el número de telar.

### 3.2 `CatEficiencias/CatalagoEficienciaController`

Archivo: `.../CatEficiencias/CatalagoEficienciaController.php`. Modelo `ReqEficienciaStd` (`sqlsrv`, `dbo.ReqEficienciaStd`). RMB por `Id`.

- **`index(Request $request)`** (L18). Devuelve **todos** los registros ordenados por `SalonTejidoId`, `NoTelarId`, `FibraId` (los filtros se aplican en cliente con JS). Vista `catalagos.catalagoEficiencia`, `noResults` siempre `false`.
- **`procesarExcel(Request $request)`** (L37). `set_time_limit(300)`. Valida `archivo_excel` (xlsx/xls, 10MB). Importa con `ReqEficienciaStdImport` dentro de transacción; commit y JSON con estadísticas. Rollback + 500 ante error.
- **`store(Request $request)`** (L92). Crea eficiencia. Valida `NoTelarId` (req, max 10), `FibraId` (req, max 120), `Eficiencia` (req, numeric, 0–1), `Densidad` (nullable, max 10). Salón por defecto `JACQUARD` si no se envía; densidad por defecto `Normal`. Bloquea duplicados por salón+telar+fibra+densidad (422).
- **`update(Request $request, ReqEficienciaStd $eficiencia)`** (L143). Actualiza por RMB. Valida (telar max 20, fibra max 120, eficiencia 0–1, densidad max 10). Guarda valores originales (eficiencia/telar/fibra/densidad). Verifica duplicados excluyendo el `Id` actual (422). **Si cambió la eficiencia** (delta > 0.0001) o cambió telar/fibra/densidad, invoca `actualizarProgramasYRecalcular()` con los valores nuevos y/o antiguos para propagar el cambio a los programas de tejido.
- **`actualizarProgramasYRecalcular(string $telar, string $fibra, string $densidad, float $nuevaEficiencia)`** (privada, L219). Busca en `ReqProgramaTejido` (`sqlsrv`) los programas con `NoTelarId = $telar` y (`FibraRizo = $fibra` OR `FibraTrama = $fibra`). Calcula la densidad del programa (`Alta` si `CalibreTrama`/`CalibreTrama2` > 40, si no `Normal`). Si coincide la densidad y el valor difiere, asigna `EficienciaSTD`, fuerza `UpdatedAt = now()` y hace `save()` para que el **Observer** (`ReqProgramaTejidoObserver`) recalcule `StdDia`, `ProdKgDia`, `StdHrsEfect`, `ProdKgDia2`, `HorasProd`, `DiasJornada` y regenere líneas diarias. Excepciones silenciadas.
- **`destroy(ReqEficienciaStd $eficiencia)`** (L282). Antes de borrar verifica si la eficiencia está en uso por algún `ReqProgramaTejido` (misma lógica telar+fibra+densidad). Si está en uso devuelve 422; si no, elimina.

### 3.3 `CatVelocidades/CatalagoVelocidadController`

Archivo: `.../CatVelocidades/CatalagoVelocidadController.php`. Modelo `ReqVelocidadStd` (`sqlsrv`, `dbo.ReqVelocidadStd`). RMB por `Id`. Estructura paralela a Eficiencias.

- **`index(Request $request)`** (L20). Todos los registros ordenados por salón/telar/fibra; filtros en cliente. Vista `catalagos.catalagoVelocidad`.
- **`procesarExcel(Request $request)`** (L39). Valida archivo; importa con `ReqVelocidadStdImport` en transacción; JSON con estadísticas.
- **`store(Request $request)`** (L104). Valida `SalonTejidoId` (nullable, max 20), `NoTelarId` (req, max 10), `FibraId` (req, max 60), `Velocidad` (req, **integer**, min 0), `Densidad` (nullable, max 10). Duplicado por telar+fibra+densidad (422). Salón por defecto `Ninguno`, densidad `Normal`.
- **`update(Request $request, ReqVelocidadStd $velocidad)`** (L159). Mismas validaciones. Duplicado excluyendo `Id` actual. Guarda valores originales. Detecta cambio en velocidad/telar/fibra/densidad y, si lo hay, llama `actualizarProgramasYRecalcular()`. Salón por defecto al actualizar: `JACQUARD`.
- **`actualizarProgramasYRecalcular(oldTelar, oldFibra, oldDensidad, newTelar, newFibra, newDensidad, int $nuevaVelocidad)`** (privada, L246). Busca programas que usaban la velocidad **original** (`NoTelarId = oldTelar` y fibra antigua en rizo/trama). Si la densidad del programa coincide con `oldDensidad`, sincroniza estado (`syncOriginal()`), asigna `NoTelarId`, `FibraRizo`, `FibraTrama` y `VelocidadSTD` nuevos, fuerza `UpdatedAt` y guarda para que el Observer recalcule `StdToaHra` (depende de `VelocidadSTD`), `StdDia`, `DiasJornada`. Excepciones silenciadas.
- **`destroy(ReqVelocidadStd $velocidad)`** (L314). Bloquea eliminación (422) si la velocidad está en uso por algún programa (telar+fibra+densidad); si no, elimina.

### 3.4 `CatCalendarios/CalendarioController`

Archivo: `.../CatCalendarios/CalendarioController.php`. Es el controller más complejo del ámbito. Modelos: `ReqCalendarioTab` (cabecera, PK string `CalendarioId`), `ReqCalendarioLine` (líneas, PK `Id`), y `ReqProgramaTejido`/`ReqModelosCodificados` para el recálculo (todo `sqlsrv`). Usa helpers `BalancearTejido` y `TejidoHelpers` del subdominio Programa de Tejido. Constantes: `RECALC_TIMEOUT_SECONDS = 900`, `RECALC_LOG_EVERY = 25`.

- **`boostRuntimeLimits(): void`** (privada, L26). Sube `max_execution_time`/`set_time_limit` a 900s, `memory_limit` a 1024M y desactiva el query log para evitar fugas de memoria en bucles grandes. Se llama al inicio de las operaciones pesadas.
- **`index(Request $request)`** (L36). Carga todos los calendarios (orden por `CalendarioId`) y todas las líneas (orden por `CalendarioId`, `FechaInicio`). Vista `catalagos.calendarios.index` con `calendarioTab` y `calendarioLine`.
- **`getCalendariosJson(Request $request)`** (L47). JSON con `CalendarioId` y `Nombre` de todos los calendarios (para selects). 500 ante error.
- **`store(Request $request)`** (L65). Crea calendario. Valida `CalendarioId` (req, max 20, único en `ReqCalendarioTab`) y `Nombre` (req, max 255). Si se envía `Turnos` (array), valida adicionalmente `FechaInicial`/`FechaFinal` (date) y genera líneas con `crearLineasDesdeTurnos()`. Todo en transacción. Devuelve `{success, data, lineas_creadas}`. `InvalidArgumentException` → 422 (p. ej. fechas inválidas o suma de horas > 24).
- **`update(Request $request, $id)`** (L146). Actualiza **solo** el `Nombre` del calendario (busca por `CalendarioId`). 404 si no existe. Valida `Nombre` (req, max 255).
- **`getCalendarioDetalle(string $calendarioId)`** (L185). Reconstruye la matriz turnos×días a partir de las líneas existentes (mapa `dayOfWeek` → nombre de día; turnos 1,2,3). Para cada turno/día devuelve `{horas, inicio, fin, activo}` tomando la **primera** ocurrencia. Calcula `fechaInicial`/`fechaFinal` del rango cubierto. JSON `{calendarioId, nombre, fechaInicial, fechaFinal, turnos}`. Alimenta el modal de edición masiva.
- **`updateMasivo(Request $request, string $calendarioId)`** (L286). Edición masiva por rango. Valida `Nombre`, `FechaInicial`, `FechaFinal`, `Turnos` (array). Actualiza el nombre, **elimina solo las líneas que se solapan** con el rango (líneas que empiezan dentro, terminan dentro o contienen el rango) y regenera con `crearLineasDesdeTurnos()`. Transaccional. Devuelve líneas creadas y el rango actualizado.
- **`destroy($id)`** (L386). Elimina calendario. Bloquea (422) si hay `ReqProgramaTejido` usándolo (`CalendarioId = $id`, `count > 0`). Si no, borra primero sus líneas y luego la cabecera (transacción).
- **`storeLine(Request $request)`** (L426). Crea una línea de calendario. Valida `CalendarioId`, `FechaInicio`, `FechaFin` (date), `HorasTurno` (numeric ≥ 0), `Turno` (int ≥ 1). Verifica que el calendario exista (422). Tras crear, ejecuta `recalcularProgramasPorCalendario()` acotado al rango de la línea, **sin regenerar líneas** (solo fechas) para evitar timeout. Devuelve la línea y el bloque `recalculo` con estadísticas.
- **`updateLine(Request $request, $id)`** (L487). Actualiza una línea. Valida fechas/horas/turno. Calcula el rango unión entre las fechas viejas y nuevas y recalcula programas de ese rango (solo fechas).
- **`destroyLine($id)`** (L557). Elimina una línea y recalcula programas del rango que cubría (solo fechas).
- **`destroyLineasPorRango(Request $request, string $calendarioId)`** (L598). Borra líneas masivamente por `fechaInicio`/`fechaFin` (`after_or_equal`) y opcionalmente `turnos` (in 1,2,3). Dentro de una `DB::transaction`, borra coincidentes y, si eliminó algo, recalcula programas del rango. 404 si no eliminó nada.
- **`procesarExcel(Request $request)`** (L682). Importa Excel de calendarios. Parámetro `tipo` (`'calendarios'` por defecto o `'lineas'`). **Borra/trunca** la tabla destino antes de importar (solo líneas, o líneas + cabeceras). Usa `ReqCalendarioLineImport` o `ReqCalendarioTabImport`. Estadísticas en formato `{procesados, creados, actualizados, errores}`.
- **`recalcularProgramas(Request $request, $calendarioId)`** (L764). Endpoint de recálculo manual. 404 si el calendario no existe (lista los disponibles). Si es POST, lee `regenerar_lineas` (bool) y llama `recalcularProgramasPorCalendario($calendarioId, null, null, 'manual', $regenerarLineas)`. Devuelve nombre del calendario y estadísticas.
- **`recalcularProgramasPorCalendario(string $calendarioId, ?Carbon $rangoIni, ?Carbon $rangoFin, string $motivo='', bool $regenerarLineas=false): array`** (pública, L810). **Núcleo del recálculo en cascada.** Desactiva el event dispatcher de `ReqProgramaTejido` (evita disparar el Observer durante el barrido). Detecta telares afectados (programas con ese `CalendarioId` y `FechaInicio` no nula; si hay rango, filtra solapamientos vía `whereRaw` con `DATEADD`). Para cada telar trae sus filas ordenadas por `FechaInicio`, `Id` y las procesa en cascada: el **primer registro mantiene su fecha original**; los siguientes se ajustan al fin del anterior y se "encajan" al calendario con `snapInicioAlCalendario()`. Recalcula `HorasProd` solo si viene 0 (`calcularHorasProd()`), obtiene `FechaFinal` con `BalancearTejido::calcularFechaFinalDesdeInicio()`, recalcula campos dependientes de fechas (`calcularFormulasDependientesDeFechas()`) y persiste con `saveQuietly()`. Si `regenerarLineas`, llama `ReqProgramaTejido::regenerarLineas([$p])`. Restaura el dispatcher en `finally`. Devuelve `{procesados, actualizados, errores, segundos}`.
- **`snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon`** (pública, L1004). Delega en `TejidoHelpers::snapInicioAlCalendario()`: ajusta una fecha de inicio al primer slot hábil del calendario.
- **`calcularHorasProd(ReqProgramaTejido $p): float`** (pública, L1009). Calcula horas de producción a partir de `VelocidadSTD`, `EficienciaSTD`, cantidad (`SaldoPedido`/`Produccion`/`TotalPedido`) y parámetros del modelo (`no_tiras`, `total`, `luchaje`, `repeticiones`), vía `TejidoHelpers::calcularHorasProdFromParams()`.
- **`getModeloParams(?string $tamanoClave, ReqProgramaTejido $p): array`** (privada, L1026). Obtiene parámetros del modelo desde `ReqModelosCodificados` (por `TamanoClave`) con caché estático; prioriza los valores del propio programa (`NoTiras`, `Luchaje`, `Repeticiones`) si son > 0.
- **`sanitizeNumber($value): float`** (privada, L1069). Delega en `TejidoHelpers::sanitizeNumber()`.
- **`normalizarHoraHms(string $hora): string`** (privada, L1074). Normaliza horas `HH:MM` → `HH:MM:SS`.
- **`crearLineasDesdeTurnos(string $calendarioId, string $fechaInicial, string $fechaFinal, array $turnos): int`** (privada, L1083). Genera líneas para cada día del rango según la matriz de turnos. Lanza `InvalidArgumentException` si la fecha final es menor a la inicial o si la suma de horas de un día supera 24. Inserta en lotes de 400 (`insert()`). Retorna el número de líneas creadas.
- **`calcularFormulasDependientesDeFechas(ReqProgramaTejido $p, Carbon $inicio, Carbon $fin, float $horasProd): array`** (pública, L1174). Recalcula **solo** campos dependientes de fechas (no toca `StdToaHra`/`StdDia`/`HorasProd` para que no se "pase por tiempo"): `DiasEficiencia`, `StdHrsEfect`, `ProdKgDia2`, `DiasJornada`, `EntregaCte` (= fin + 12 días) y `PTvsCte` (diferencia en días con `EntregaPT`). Ver fórmulas en §8.

### 3.5 `CatAplicaciones/AplicacionesController`

Archivo: `.../CatAplicaciones/AplicacionesController.php`. Modelo `ReqAplicaciones` (`sqlsrv`, `dbo.ReqAplicaciones`, PK numérica `Id`).

- **`index(Request $request)`** (L22). Lista aplicaciones (`AplicacionId`, `Nombre`, `Factor`) ordenadas por `AplicacionId`, `Nombre`. Vista `catalagos.aplicaciones`.
- **`procesarExcel(Request $request)`** (L38). Valida archivo; importa con `ReqAplicacionesImport` en transacción; JSON con estadísticas.
- **`store(Request $request)`** (L107). Crea aplicación. Valida `AplicacionId` (req, max 50, único), `Nombre` (req, max 100), `Factor` (nullable, numeric). 422 ante validación.
- **`update(Request $request, $id)`** (L153). Busca por `Id` numérico o por `AplicacionId`. Valida con `Rule::unique(...)->ignore($aplicacion->Id, 'Id')`. **Si cambia el `Factor`** (delta > 0.0001), invoca `actualizarLineasPorCambioFactor()` para recalcular la columna `Aplicacion` en las líneas de programa.
- **`actualizarLineasPorCambioFactor(string $aplicacionId, ?float $nuevoFactor)`** (privada, L227). Busca `ReqProgramaTejido` con ese `AplicacionId`, toma sus `ReqProgramaTejidoLine` con `Kilos > 0` y actualiza cada línea con `Aplicacion = round(Factor * Kilos, 6)` (misma precisión que el Observer). Relanza excepción si falla.
- **`destroy($id)`** (L273). Busca por `Id` o `AplicacionId`. Bloquea (422) si está en uso en `ReqProgramaTejido` (`AplicacionId`) **o** en `ReqProgramaTejidoLine` (`Aplicacion`). Si no, elimina.

### 3.6 `CatMatrizHilos/MatrizHilosController`

Archivo: `.../CatMatrizHilos/MatrizHilosController.php`. Modelo `ReqMatrizHilos` (**conexión por defecto `sqlsrv`, tabla `ReqMatrizHilos` SIN prefijo `dbo.`**, PK `Id`).

- **`index()`** (L17). Lista hilos ordenados por `Hilo`; mapea cada registro para exponer también `id` minúscula (compatibilidad con la vista). Vista `catalagos.matriz-hilos`.
- **`list()`** (L42). JSON con `Hilo` y `Fibra` distintos (para selects). 500 ante error.
- **`store(Request $request)`** (L65). Crea registro. Valida `Hilo` (req, max 30), `Calibre`/`Calibre2`/`N1`/`N2` (nullable numeric), `CalibreAX` (max 20), `Fibra` (max 30), `CodColor` (max 10), `NombreColor` (max 60). 422 ante `ValidationException`.
- **`show($id)`** (L105). Devuelve un registro por `Id` (con fallback `where('Id',$id)`). 404 si no existe.
- **`update(Request $request, $id)`** (L131). Actualiza registro. Mismas validaciones que `store`. **Regla de negocio:** si el hilo está en uso en `ReqProgramaTejido` (campo `FibraRizo`) y se intenta cambiar el **nombre** del hilo, bloquea (422). Si se modifican campos de cálculo (`N1`, `N2`, `Calibre`, `Calibre2`) y el hilo está en uso, tras guardar invoca `recalcularMtsRizoEnLineas()`.
- **`recalcularMtsRizoEnLineas(string $hilo)`** (privada, L232). Recalcula `MtsRizo` en todas las `ReqProgramaTejidoLine` que usan el hilo. Constantes: `1000`, `0.59`, `1.0162`. Obtiene `N1`/`N2` (con fallback a `Calibre`/`Calibre2`); si ambos > 0, para cada programa con `FibraRizo = $hilo` recorre sus líneas con `Rizo > 0` y aplica la fórmula de metros de rizo (ver §8). Errores logueados.
- **`destroy($id)`** (L315). Elimina por `Id`. Bloquea (422) si el hilo está en uso en `ReqProgramaTejido.FibraRizo`. Devuelve `deleted_id` en éxito.

### 3.7 `CatPesosRollos/PesosRollosController`

Archivo: `.../CatPesosRollos/PesosRollosController.php`. Modelo `ReqPesosRollosTejido` (**conexión por defecto `sqlsrv`, tabla `ReqPesosRolloTejido` SIN `dbo.`**, PK `Id`). Sin propagación a programas.

- **`index(Request $request)`** (L18). Lista pesos ordenados por `ItemId`, `InventSizeId`. Vista `catalagos.pesos-rollos`.
- **`store(Request $request)`** (L30). Crea registro. Valida `ItemId` (req, max 20), `ItemName` (req, max 60), `InventSizeId` (req, max 10), `PesoRollo` (req, numeric ≥ 0). Bloquea duplicado por `ItemId`+`InventSizeId` (422). Auditoría manual: `FechaCreacion`/`HoraCreacion` (Carbon::now) y `UsuarioCrea` = `Auth::user()->nombre` o `Sistema`.
- **`update(Request $request, $id)`** (L89). `findOrFail($id)`. Mismas validaciones; duplicado excluyendo `Id` actual. Escribe `FechaModificacion`/`HoraModificacion`/`UsuarioModifica`.
- **`destroy($id)`** (L151). `findOrFail($id)` y elimina. 500 ante error.

---

## 4. Services y Helpers del ámbito

Este ámbito no define services propios; reutiliza helpers del subdominio **Programa de Tejido** (invocados desde `CalendarioController`):

- **`TejidoHelpers`** (`App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers`):
  - `snapInicioAlCalendario(calendarioId, fechaInicio)`: ajusta la fecha de inicio al primer slot hábil del calendario.
  - `calcularHorasProdFromParams(vel, efic, cantidad, noTiras, total, luchaje, repeticiones)`: fórmula de horas de producción.
  - `sanitizeNumber(value)`: normaliza valores numéricos (limpia separadores, nulos).
- **`BalancearTejido`** (`...\ProgramaTejido\funciones\BalancearTejido`):
  - `calcularFechaFinalDesdeInicio(calendarioId, inicio, horas)`: calcula la `FechaFinal` recorriendo los slots del calendario a partir del inicio y las horas de producción.
- **`ReqProgramaTejido::regenerarLineas([$p])`** (método estático del modelo): regenera las líneas diarias de un programa **saltándose** el guard `shouldRegenerateLines()` del Observer; usado en el recálculo con `regenerar_lineas = true`.

No se usa `FolioHelper` ni `TurnoHelper` en este ámbito. `PesosRollosController` realiza su propia auditoría con `Carbon` + `Auth` (no `AuditoriaHelper`).

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `App\Models\Planeacion\ReqTelares` | `dbo.ReqTelares` | sqlsrv | `Id` (RMB) | `SalonTejidoId`, `NoTelarId`, `Nombre`, `Grupo`, `VelocidadSTD` |
| `App\Models\Planeacion\ReqEficienciaStd` | `dbo.ReqEficienciaStd` | sqlsrv | `Id` | `SalonTejidoId`, `NoTelarId`, `FibraId`, `Eficiencia` (float), `Densidad` |
| `App\Models\Planeacion\ReqVelocidadStd` | `dbo.ReqVelocidadStd` | sqlsrv | `Id` | `SalonTejidoId`, `NoTelarId`, `FibraId`, `Velocidad` (cast float), `Densidad` |
| `App\Models\Planeacion\ReqCalendarioTab` | `dbo.ReqCalendarioTab` | sqlsrv | `CalendarioId` (string, no incrementing) | `CalendarioId`, `Nombre`; relación `lineas()` (hasMany) |
| `App\Models\Planeacion\ReqCalendarioLine` | `dbo.ReqCalendarioLine` (overridable vía `config('planeacion.req_calendario_line_table')`) | sqlsrv | `Id` | `CalendarioId`, `FechaInicio`, `FechaFin`, `HorasTurno` (float), `Turno` (int); relación `calendario()` |
| `App\Models\Planeacion\ReqAplicaciones` | `dbo.ReqAplicaciones` | sqlsrv | `Id` (int, incrementing) | `AplicacionId`, `Nombre`, `Factor` (float) |
| `App\Models\Planeacion\ReqMatrizHilos` | `ReqMatrizHilos` (sin `dbo.`) | sqlsrv | `Id` (int) | `Hilo`, `Calibre`/`Calibre2` (decimal:4), `CalibreAX`, `Fibra`, `CodColor`, `NombreColor`, `N1`/`N2` (decimal:4) |
| `App\Models\Planeacion\Catalogos\ReqPesosRollosTejido` | `ReqPesosRolloTejido` (sin `dbo.`; nótese singular "Rollo") | sqlsrv | `Id` (int) | `ItemId`, `ItemName`, `InventSizeId`, `PesoRollo`, campos de auditoría (`FechaCreacion`, `HoraCreacion`, `UsuarioCrea`, `FechaModificacion`, `HoraModificacion`, `UsuarioModifica`) |

Tablas tocadas por la lógica de propagación (no exclusivas de este ámbito): `dbo.ReqProgramaTejido`, `dbo.ReqProgramaTejidoLine`, `ReqModelosCodificados` (lectura de parámetros de modelo). Todas en `sqlsrv`. Ningún controller del ámbito usa `sqlsrv_ti` ni `sqlsrv_tow_pro`.

Todos los modelos tienen `$timestamps = false`.

---

## 6. Vistas Blade

Directorio `resources/views/catalagos/` (typo intencional). Los catálogos siguen **dos patrones de UI**:

- **Patrón clase JS** (Telares, Aplicaciones, Matriz de Hilos): el blade solo renderiza la tabla y delega toda la lógica a una clase que extiende `CatalogBase` (ver §7). El bloque `<script>` solo instancia la clase y registra aliases globales.
- **Patrón funciones inline** (Eficiencia, Velocidad, Pesos-rollos, Calendarios y sus modales): toda la lógica vive en funciones `function` dentro del `<script>` del blade.

### 6.1 `catalagoTelares.blade.php` (patrón clase)
Propósito: listado de telares. Secciones: navbar con `<x-buttons.catalog-actions route="telares" :showFilters>`, tabla scrollable (Salón, Telar, Nombre, Grupo), cada fila con `onclick`/`ondblclick` que invoca `window.catalogManager`. Carga `CatalogBase.js` + `TelaresCatalog.js`. Script: instancia `new TelaresCatalog({initialData})` y expone aliases `agregarTelares`, `editarTelares`, `eliminarTelares`, `subirExcelTelares`, `filtrarTelares`, `limpiarFiltrosTelares` (cada uno delega en el método homónimo del manager). Endpoints (vía la clase): `/planeacion/telares` (POST/PUT/DELETE), `/planeacion/telares/excel`.

### 6.2 `aplicaciones.blade.php` (patrón clase)
Propósito: listado de aplicaciones (AplicacionId, Nombre, Factor). Carga `CatalogBase.js` + `AplicacionesCatalog.js`. Script: `new AplicacionesCatalog({initialData})` y aliases `agregarAplicaciones`, `editarAplicaciones`, `eliminarAplicaciones`, `filtrarAplicaciones`, `limpiarFiltrosAplicaciones`, `subirExcelAplicaciones`. Endpoints: `/planeacion/aplicaciones` (POST/PUT/DELETE), `/planeacion/aplicaciones/excel`.

### 6.3 `matriz-hilos.blade.php` (patrón clase)
Propósito: matriz de hilos. Carga `CatalogBase.js` + `MatrizHilosCatalog.js`. Script: `new MatrizHilosCatalog({initialData})` con doble juego de aliases (`agregarMatriz_hilos`/`agregarMatrizHilos`, etc.) para cubrir ambas convenciones de nombre del componente de botones. Endpoints: `/planeacion/catalogos/matriz-hilos` (POST/PUT/DELETE), `/planeacion/catalogos/matriz-hilos/{id}` (show).

### 6.4 `catalagoEficiencia.blade.php` (patrón funciones)
Propósito: catálogo de eficiencias con filtros en cliente. Funciones del `<script>`:
- `crearToast(icon, msg, ms)` (L82): muestra un toast (SweetAlert) ligero.
- `repoblarSelect(selectEl, opciones, selectedValue)` (L90): rellena un `<select>` (p. ej. telares) preservando selección.
- `selectRow(row, uniqueId, eficienciaId)` (L115) / `deselectRow(row)` (L132): selección/deselección de fila y habilitación de botones.
- `actualizarTelaresCreate()` (L149): refresca la lista de telares en el formulario de alta.
- `actualizarEficiencia(valor)` (L153): sincroniza el valor de eficiencia en el formulario.
- `agregarEficienciaLocal()` (L156): abre el modal de alta y hace `POST /planeacion/eficiencia`.
- `editarEficiencia()` (L242): abre modal de edición y hace `PUT /planeacion/eficiencia/{id}`.
- `eliminarEficiencia()` (L347): confirma y hace `DELETE /planeacion/eficiencia/{id}`.
- `mostrarFiltros()` (L386): abre el modal de filtros.
- `aplicarFiltros(f)` (L491): filtra la tabla en memoria por los criterios.
- `limpiarFiltros()` (L534): reinicia filtros.
- `actualizarTablaOptimizada(datos)` (L546): re-renderiza el `<tbody>` con el dataset filtrado.
- `actualizarContador()` (L586): actualiza el contador de registros visibles.

### 6.5 `catalagoVelocidad.blade.php` (patrón funciones)
Propósito: catálogo de velocidades (estructura idéntica a eficiencia). Funciones: `crearToast` (L99), `repoblarSelect` (L107), `selectRow`/`deselectRow` (L132/L149), `actualizarTelaresCreate` (L166), `agregarVelocidadLocal` (L170, `POST /planeacion/velocidad`), `editarVelocidad` (L251, `PUT /planeacion/velocidad/{id}`), `eliminarVelocidad` (L351, `DELETE /planeacion/velocidad/{id}`), `mostrarFiltros` (L390), `aplicarFiltros(f)` (L468), `actualizarTablaOptimizada(datos)` (L513), `actualizarContador` (L553), `limpiarFiltros` (L561).

### 6.6 `pesos-rollos.blade.php` (patrón funciones)
Propósito: catálogo de pesos por rollo. Funciones: `crearToast` (L64), `selectRow`/`deselectRow` (L92/L109), `agregarPesoRollo` (L125, `POST /planeacion/catalogos/pesos-rollos`), `editarPesoRollo` (L198, `PUT /planeacion/catalogos/pesos-rollos/{id}`), `eliminarPesoRollo` (L281, `DELETE /planeacion/catalogos/pesos-rollos/{id}`), `mostrarFiltros` (L320), `aplicarFiltros` (L372), `limpiarFiltros` (L391), `renderizarTabla` (L398, re-render del cuerpo de la tabla).

### 6.7 `calendarios/index.blade.php` (patrón funciones)
Propósito: pantalla maestra-detalle de calendarios (tabla de calendarios + tabla de líneas). Funciones:
- `resetRowStyles` (L105): limpia estilos de selección.
- `selectRowTab(row, calendarioId)` (L120) / `deselectRowTab(row)` (L136): selección de calendario.
- `selectRowLine(row, calendarioLineKey)` (L159) / `deselectRowLine(row)` (L173): selección de línea.
- `filtrarLineasPorCalendario(calendarioId)` (L188): muestra solo las líneas del calendario seleccionado.
- `agregarCalendario` (L228): abre el modal de creación (`modal-calendario`).
- `editarCalendario` (L245): carga detalle (`GET /planeacion/calendarios/{id}/detalle`) y abre el modal de edición masiva.
- `convertirFechaParaInput(fechaTexto)` (L294): formatea fechas para inputs `date`.
- `updateFilterColumns` (L353), `removeFilter(index, tabla)` (L373), `updateFilterModal` (L385), `applyFilters` (L389), `updateFilterCount` (L442), `restablecerFiltros` (L455): sistema de filtros por columna sobre ambas tablas.
- `window.subirExcelCalendarios` (L474): abre el modal de Excel de calendarios maestro.
- `enableButtons` (L486) / `disableButtons` (L505): habilita/inhabilita botones según selección.

### 6.8 `calendarios/modal-calendario.blade.php`
Propósito: modal de alta de calendario con matriz turnos×días (checkbox por día, horas/inicio/fin). Función inline: `toggleDiaInputs(checkbox)` (L456): habilita/inhabilita los inputs de horas/inicio/fin del día según el checkbox de activo. Envía a `POST /planeacion/calendarios` (con `Turnos`).

### 6.9 `calendarios/modal-agregar-linea.blade.php`
Propósito: modal de alta de una línea. `agregarLineaCalendario()` (L3): valida y hace `POST /planeacion/calendarios/lineas`, muestra el resumen de recálculo devuelto.

### 6.10 `calendarios/modal-editar-linea.blade.php`
Propósito: modal de edición de línea. `editarLineaCalendario()` (L3): `PUT /planeacion/calendarios/lineas/{id}`.

### 6.11 `calendarios/modal-eliminar.blade.php`
Propósito: confirmación de borrado de calendario. `eliminarCalendario()` (async, L3): `DELETE /planeacion/calendarios/{id}` (maneja el 422 de "en uso").

### 6.12 `calendarios/modal-eliminar-rango.blade.php`
Propósito: borrado de líneas por rango/turnos. `eliminarLineasPorRango()` (L3): `DELETE /planeacion/calendarios/{id}/lineas/rango` con `fechaInicio`, `fechaFin`, `turnos[]`.

### 6.13 `calendarios/modal-recalcular.blade.php`
Propósito: lanzar recálculo manual. `recalcularProgramasCalendarioNavbar()` (L3): toma el calendario seleccionado y llama a `recalcularProgramasCalendario`. `recalcularProgramasCalendario(calendarioId, calendarioNombre)` (L28): `POST /planeacion/calendarios/{id}/recalcular-programas` (puede enviar `regenerar_lineas`) y muestra estadísticas.

### 6.14 `calendarios/modal-filtrar.blade.php`
Propósito: modal de filtro por columna. `filtrarPorColumna()` (L3): aplica el filtro seleccionado sobre la tabla en cliente.

### 6.15 `calendarios/modal-excel-calendarios.blade.php`
Propósito: subida de Excel de calendarios maestro. `window.subirExcelCalendariosMaestro` (L3): `POST /planeacion/calendarios/excel` con `tipo=calendarios`; listener `change` (L46) para previsualizar el archivo.

### 6.16 `calendarios/modal-excel-lineas.blade.php`
Propósito: subida de Excel de líneas. `window.subirExcelLineas` (L3): `POST /planeacion/calendarios/excel` con `tipo=lineas`; listener `change` (L47).

### 6.17 Vistas de formulario individual / Excel (legacy)

- **`telaresCreate.blade.php`**: formulario clásico de alta de telar (`<form>` a `route('planeacion.telares.store')`, campos salón/telar/nombre/cuenta/piel/ancho). Sin `<script>`. Aparenta ser legacy: el `store` actual solo usa salón/telar/nombre/grupo. No tiene ruta GET propia mapeada en el ámbito.
- **`create-telar.blade.php`**: `generarNombre(salon, telar)` (L138), `updatePreview()` (L163), `confirmarCreacion()` (L180) — alta con preview de nombre.
- **`edit-telar.blade.php`**: `generarNombre(salon, telar)` (L164), `updatePreview()` (L189), `confirmarActualizacion()` (L206) — edición con preview.
- **`eficienciaCreate.blade.php`**, **`Eficiencia-edit.blade.php`**, **`velocidadCreate.blade.php`**: formularios individuales de alta/edición (sin funciones `function` inline detectadas; usan `<form>` o submit directo). Parecen legacy frente al flujo modal de los catálogos `catalagoEficiencia`/`catalagoVelocidad`.

> La subida de Excel de telares se realiza vía `POST /planeacion/telares/excel` (name `telares.excel.upload` → `CatalagoTelarController@procesarExcel`), invocada por la clase `TelaresCatalog.js` desde el catálogo principal.

> Las vistas `codificacion-form.blade.php`, `catalogoCodificacion.blade.php` y `modal/_duplicar-importar-codificacion.blade.php` pertenecen al submódulo **Codificación de Modelos** (otro documento) aunque residan en `catalagos/`; no se detallan aquí.

---

## 7. JS dedicado

Archivos en `public/js/catalogs/`. Todas las clases de catálogo extienden `CatalogBase`.

### 7.1 `CatalogBase.js` — clase base genérica de catálogo
Métodos relevantes (todos pensados para ser sobreescritos por las subclases): `constructor(config)`, `init()`, `getCreateFormHTML()`, `getEditFormHTML(data)`, `validateCreateData(data)`, `validateEditData(data)`, `processData(data, action)`, `renderRow(item)`, `getRowId(row)`, `getRowData(row)`, `selectRow(row, uniqueId, id)`, `deselectRow(row)`, `onRowSelected/onRowDeselected`, `enableButtons()`/`disableButtons()`, `showToast(message, type, duration)`, `async create()`, `async edit()`, `async delete()`, `getDeleteConfirmMessage(rowData)`, `async performCreate(data)`, `async performEdit(id, data)`, `async performDelete(id)`, `extractFormData(action)`, `getCSRFToken()`, `onCreateFormOpen()`/`onEditFormOpen(rowData)`, `async showFilters()`, `getFiltersFormHTML()`, `extractFiltersData()`, `applyFilters(filters)`, `matchesFilters(item, filters)`, `clearFilters()`, `renderTable()`, `async uploadExcel()`, `async performExcelUpload(file)`, `setupEventListeners()`, `bindGlobalFunctions()`, `capitalize(str)`. Implementa el ciclo SweetAlert: abrir modal → validar → `fetch` con CSRF → toast/refresco de tabla.

### 7.2 `TelaresCatalog.js` (extends CatalogBase)
- `nameFrom(salon, telar)`: equivalente JS de `makeName` (prefijo JAC/Smith/3 letras).
- `getCreateFormHTML()`/`getEditFormHTML(data)`: formularios de alta/edición.
- `onCreateFormOpen()`/`onEditFormOpen(rowData)`: enlazan el preview de nombre.
- `validateCreateData`/`validateEditData`: validan salón y telar requeridos.
- `renderRow(item)`, `getRowId(row)`, `getRowData(row)`, `getDeleteConfirmMessage(rowData)`, `extractFormData(action)`, `getFiltersFormHTML()`, `extractFiltersData()`, `matchesFilters(item, filters)`.
- `async performCreate(data)` → `POST /planeacion/telares`; `async performEdit(id, data)` → `PUT /planeacion/telares/{uniqueId}`; `async performDelete(id)` → `DELETE /planeacion/telares/{uniqueId}`.

### 7.3 `AplicacionesCatalog.js` (extends CatalogBase)
- `getCreateFormHTML`/`getEditFormHTML`, `validateCreateData`/`validateEditData`, `processData`, `renderRow`, `getRowData`, `getFiltersFormHTML`, `extractFiltersData`, `matchesFilters`, `getDeleteConfirmMessage`.
- `async performCreate(data)` → `POST /planeacion/aplicaciones`; `async performEdit(id, data)` → `PUT /planeacion/aplicaciones/{id}`.

### 7.4 `MatrizHilosCatalog.js` (extends CatalogBase)
- `getCreateFormHTML`/`getEditFormHTML` (formulario con Hilo, Calibre, Calibre2, CalibreAX, Fibra, CodColor, NombreColor, N1, N2), `validateCreateData`/`validateEditData`, `processData`, `renderRow`, `getRowId`, `getRowData`, `getDeleteConfirmMessage`, `extractFormData`.
- `async performCreate(data)` → `POST /planeacion/catalogos/matriz-hilos`; `async performEdit(id, data)` → `PUT /planeacion/catalogos/matriz-hilos/{id}`.

> `FormBuilder.js`, `JuliosCatalog.js`, `MaquinasUrdidoCatalog.js` existen en el mismo directorio pero pertenecen a otros catálogos (urdido), fuera de este ámbito.

---

## 8. Lógica de negocio y reglas

### 8.1 Cálculos y fórmulas

**Eficiencia/Velocidad → densidad del programa.** En los tres controllers que propagan a programas, la densidad efectiva del programa se deduce de `CalibreTrama` (o `CalibreTrama2`): `> 40 ⇒ 'Alta'`, en caso contrario `'Normal'`. La propagación solo aplica si esa densidad coincide con la del registro de catálogo.

**Aplicaciones (`actualizarLineasPorCambioFactor`).** Por cada línea de programa con `Kilos > 0`:
`Aplicacion = round(Factor × Kilos, 6)` (6 decimales, igual que el Observer).

**Matriz de hilos (`recalcularMtsRizoEnLineas`).** Constantes `c1=1000`, `c2=0.59`, `c3=1.0162`. Con `N1`/`N2` (fallback a `Calibre`/`Calibre2`), `Rizo` de la línea y `CuentaRizo` del programa:
```
ValorRizo1 = ((N1 × (Rizo × 1000)) / 0.59) / 2
ValorRizo2 = ((N2 × (Rizo × 1000)) / 0.59) / 2
MtsRizo    = ((ValorRizo1 + ValorRizo2) / CuentaRizo) × 1.0162
```
Solo se persiste si `MtsRizo > 0`.

**Calendarios — `calcularFormulasDependientesDeFechas`** (no toca `StdToaHra`/`StdDia`/`HorasProd`):
```
diffDias    = |fin - inicio| / 86400
DiasEficiencia = round(diffDias, 4)
cantidad    = SaldoPedido ?? Produccion ?? TotalPedido
StdHrsEfect = (cantidad / diffDias) / 24
ProdKgDia2  = ((PesoCrudo × StdHrsEfect) × 24) / 1000     (si PesoCrudo > 0)
DiasJornada = HorasProd / 24
EntregaCte  = fin + 12 días
PTvsCte     = round(diff(EntregaCte, EntregaPT) en días, 2)
```

**Horas de producción** (`calcularHorasProd` → `TejidoHelpers::calcularHorasProdFromParams`) y **fecha final** (`BalancearTejido::calcularFechaFinalDesdeInicio`) se delegan al subdominio Programa de Tejido y dependen del calendario laboral (slots de turnos).

### 8.2 Restricciones / validaciones de negocio

- **Telares**: combinación `SalonTejidoId`+`NoTelarId` única; el `uniqueId` se parsea por el **último** `_` (soporta salones con guiones bajos).
- **Eficiencia/Velocidad**: unicidad por salón/telar/fibra/densidad. Velocidad debe ser **entero** ≥ 0; eficiencia es float en [0,1].
- **Aplicaciones**: `AplicacionId` único; **no se puede eliminar** si está usada en `ReqProgramaTejido.AplicacionId` o `ReqProgramaTejidoLine.Aplicacion`.
- **Matriz de hilos**: **no se puede renombrar** un hilo en uso (`ReqProgramaTejido.FibraRizo`); **no se puede eliminar** un hilo en uso.
- **Calendarios**: `CalendarioId` único; **no se puede eliminar** un calendario usado por programas; la suma de horas por día no puede exceder 24 (lanza `InvalidArgumentException` → 422); fecha final ≥ fecha inicial.
- **Pesos por rollo**: unicidad por `ItemId`+`InventSizeId`; auditoría obligatoria de usuario/fecha/hora.

### 8.3 Flujos completos (qué pasa al guardar/recalcular)

1. **Guardar eficiencia/velocidad** → detecta cambio → busca programas afectados → asigna el nuevo valor + `UpdatedAt = now()` → `save()` dispara el **Observer** (`ReqProgramaTejidoObserver`) que recalcula fórmulas y regenera líneas diarias.
2. **Guardar factor de aplicación** → recalcula `Aplicacion` en líneas (sin pasar por Observer; update directo de columna).
3. **Guardar calibres/N1/N2 de hilo** → recalcula `MtsRizo` en líneas (update directo).
4. **Alta/edición/borrado de línea de calendario** → `recalcularProgramasPorCalendario()` acotado al rango afectado, **solo fechas** (`saveQuietly`, Observer desactivado durante el barrido) para evitar timeouts; el primer registro de cada telar conserva su fecha; los siguientes se encadenan y se "encajan" (`snap`) al calendario.
5. **Recálculo manual** (modal recalcular) → `recalcularProgramas` con opción `regenerar_lineas`; si está activa, además de fechas regenera las líneas diarias de cada programa.
6. **Importación Excel de calendarios** → **trunca/borra** la tabla destino antes de importar (operación destructiva).

### 8.4 Efectos colaterales entre módulos

Todos los catálogos de este ámbito son **upstream** del Programa de Tejido: sus cambios reescriben `ReqProgramaTejido` y `ReqProgramaTejidoLine`, que a su vez alimentan urdido/engomado/atadores. El recálculo de calendarios desactiva temporalmente el dispatcher del modelo para no provocar recálculos en cadena no deseados, y restaura el dispatcher en `finally`. El recálculo eleva límites de tiempo/memoria (`boostRuntimeLimits`, 900s/1024M) por el volumen de filas.

### 8.5 Integraciones

- **Excel (maatwebsite/excel)**: imports `ReqTelaresImport`, `ReqEficienciaStdImport`, `ReqVelocidadStdImport`, `ReqAplicacionesImport`, `ReqCalendarioTabImport`, `ReqCalendarioLineImport` (en `app/Imports/`). Implementan `ToModel`, `WithHeadingRow`, `WithBatchInserts`, `WithChunkReading` y exponen `getStats()` con contadores (`processed_rows`/`created_rows`/`updated_rows`/`skipped_rows`/`errores`, o `procesados`/`creados`/`actualizados`/`errores` en los de calendario). No hay clases de **export** ni **PDF** en este ámbito.
- **Telegram**: no hay notificaciones Telegram en este ámbito.
- **Folios/Turnos**: no se usan `FolioHelper` ni `TurnoHelper` aquí.

### 8.6 Hallazgos / inconsistencias detectadas

- **Sin permisos granulares en backend**: ningún método de escritura valida `userCan()`; el control es solo de visibilidad de menú/botones.
- **Excepciones silenciadas**: `actualizarProgramasYRecalcular` (eficiencia y velocidad) capturan y descartan excepciones (catch vacío), lo que puede ocultar fallos de propagación.
- **Tablas sin prefijo `dbo.`**: `ReqMatrizHilos` y `ReqPesosRolloTejido` se declaran sin prefijo de esquema (a diferencia del resto del ámbito).
