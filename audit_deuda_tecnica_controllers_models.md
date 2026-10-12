# Auditoría Técnica Exhaustiva: Deuda Técnica en Controladores y Modelos

**Proyecto:** Towell (Sistema Integral de Planeación y Manufactura Textil)  
**Framework:** Laravel 12 / PHP 8.2+ / Base de Datos: Microsoft SQL Server (`sqlsrv`) y ERP AX (`sqlsrv_ti`)  
**Fecha de Emisión:** 2026-10-09  
**Estado:** Final / Aprobado  
**Alcance de la Auditoría:** 100% de Controladores (`app/Http/Controllers`), 100% de Modelos Eloquent (`app/Models`), Suite de Pruebas Automatizadas (`tests/`), y Mapeo de Rutas (`routes/`)

---

## 1. Resumen Ejecutivo y Métricas Globales Cuantitativas

El presente informe consolida los resultados de la auditoría técnica integral realizada sobre la arquitectura backend del sistema **Towell**. El análisis abarca la totalidad del código fuente de controladores y modelos Eloquent, evaluando la presencia de código muerto, la segregación de responsabilidades arquitectónicas (Controller Bloat) y la cobertura efectiva de la suite de pruebas automatizadas.

### 1.1 Indicadores Globales del Sistema

| Dimensión / Indicador | Métrica Observada | Porcentaje Relativo | Impacto / Estado |
|---|---|---|---|
| **Total Elementos Físicos en `app/Http/Controllers`** | **142 elementos** | 100.0% | 141 archivos PHP + 1 plantilla binaria Excel (`ordfelpa.xlsx`) |
| — Archivos de Código PHP | 141 archivos | 99.3% | Clases de controller, motores de dominio, helpers y plantillas |
| — Controladores de Aplicación Estándar | 125 clases | 88.0% | Puntos de entrada HTTP y orquestación de rutas |
| — Motores de Dominio Camuflados (`funciones/`) | 5 clases | 3.5% | Lógica matemática/algorítmica alojada en controllers |
| — Adaptadores y Helpers Temporales (`helper/`) | 5 clases | 3.5% | Clases marcadas `@deprecated` no retiradas |
| — Clases de Configuración de UI / ViewModels | 2 clases | 1.4% | Definiciones de schemas visuales en namespace HTTP |
| — Traits de Soporte HTTP | 3 traits | 2.1% | Lógica compartida de respuestas y autorización |
| — Controlador Base | 1 clase | 0.7% | `App\Http\Controllers\Controller` |
| — Archivos Binarios / Plantillas fuera de lugar | 1 archivo | 0.7% | `ordfelpa.xlsx` ubicado físicamente en controllers |
| **Total Modelos Eloquent en `app/Models`** | **103 modelos** | 100.0% | Distribuidos en 16 namespaces de módulo |
| **Volumen de Código en Controladores** | **~37,500 LoC** | 100.0% | Densidad media: 266 LoC por archivo |
| — Código en Controladores Críticos (Top 39) | **27,679 LoC** | **~73.8%** | Alta concentración de lógica en el 27.6% de los archivos |
| **Total Archivos de Pruebas Automatizadas** | **343 archivos** | 100.0% | Suite ejecutada sobre emulación SQLite en memoria |
| — Feature Tests (`tests/Feature/`) | 196 archivos | 57.1% | Pruebas de integración HTTP y flujos de usuario |
| — Unit Tests (`tests/Unit/`) | 147 archivos | 42.9% | Pruebas de servicios, exportadores y componentes aislados |
| **Cobertura de Controladores** | **98 probados / 27 sin cobertura** | **78.4% cubierto / 21.6% ciego** | 27 controladores sin ninguna prueba automatizada |
| **Cobertura de Modelos Eloquent** | **85 probados / 18 sin cobertura** | **82.5% cubierto / 17.5% ciego** | 18 modelos sin aserciones directas en la suite |

---

### 1.2 Diagnóstico de Diseño y Antipatrones Arquitectónicos

El análisis de diseño sobre los 125 controladores de aplicación reveló una desviación crítica respecto a las directrices de arquitectura limpia establecidas en `AGENTS.md` ("Keep controllers thin; move business logic to Services/Repositories; use Form Requests for validation"):

```
+---------------------------------------------------------------------------------------------------+
| DISTRIBUCIÓN DE MECANISMOS DE VALIDACIÓN EN CONTROLADORES TOWELL (125 DE APLICACIÓN)              |
+---------------------------------------------------------------------------------------------------+
| [X] Uso de Form Requests dedicados (40 clases):   18 controladores (14.4%) [BAJA ADOPCIÓN]         |
| [X] Validación inline ($request->validate):       73 controladores (58.4%)                         |
| [X] Validación manual (Validator::make):           6 controladores ( 4.8%)                         |
| [X] Sin validación formal / Input crudo:          28 controladores (22.4%)                         |
| [X] Inyección de dependencias en constructor:     50 controladores (40.0%)                         |
| [X] Acoplamiento directo a modelos/DB:            75 controladores (60.0%)                         |
| [X] Transacciones manuales en controller:         14 controladores (11.2%)                         |
| [X] Consultas directas a ERP AX (sqlsrv_ti):      21 controladores (16.8%) [RIESGO DE LATENCIA]    |
| [X] Generación de binarios (PDF/Excel) en HTTP:    7 controladores ( 5.6%)                         |
+---------------------------------------------------------------------------------------------------+
```

#### Hallazgos Principales:
1. **Baja Adopción de Form Requests (14.4%):** Existen 40 clases `FormRequest` en `app/Http/Requests`, pero únicamente 18 controladores de aplicación (~14.4%) las consumen. Los 107 controladores restantes (~85.6%) delegan en validación inline `$request->validate()` (73 controladores, 58.4%), validación manual procedural `Validator::make()` (6 controladores, 4.8%) o consumen directamente `$request->all()` o `$request->input()` sin validación formal (28 controladores, 22.4%), dispersando y duplicando reglas de validación en la capa HTTP.
2. **Inversión de Capas y Falsos Controladores:** El directorio `Planeacion/ProgramaTejido/funciones/` almacena 2,615 LoC de algoritmos puros de secuenciación y balanceo que operan bajo firmas estáticas aceptando objetos `Request` y retornando `JsonResponse`, eludiendo el ciclo de vida de servicios de dominio.
3. **Acoplamiento Crítico con ERP AX (`sqlsrv_ti`):** 21 controladores ejecutan consultas directas contra la conexión secundaria de SQL Server (`sqlsrv_ti`), leyendo tablas externas como `WMSLocation`, `InventTable`, `ConfigTable` y `BOMTABLE` sin capas de repositorio o resiliencia ante caídas de red.
4. **Archivos Binarios en el Código Fuente:** Se identificó la presencia física de la plantilla de cálculo `ordfelpa.xlsx` dentro del namespace `app/Http/Controllers/Planeacion/ProgramaTejido/OrdenDeCambio/Felpa/`, violando los principios de empaquetado de código y control de versiones.

---

## 2. R1. Inventario Exhaustivo de Código Muerto y Redundante (Dead Code & Waste Detection)

La auditoría estricta de rutas (`routes/`), referencias en código fuente (`app/`, `resources/views/`) y suite de pruebas (`tests/`) identificó múltiples categorías de desperdicio técnico y código inalcanzable.

### 2.1 Categoría A: Controladores 100% Huérfanos y Muertos

Se confirmaron **3 controladores de aplicación completamente inalcanzables** (126 LoC en total), sin rutas asignadas, sin llamadas programáticas y sustituidos funcionalmente por refactorizaciones previas:

| Controlador | Archivo y Líneas | LoC | Causa de Orfandad y Sustitución Verificada |
|---|---|---|---|
| `TwHistPedidosController` | `app/Http/Controllers/Ventas/TwHistPedidosController.php` (Líneas 1-42) | 42 | **0 rutas en `routes/`**. Clase huérfana de pedidos históricos. Sustituida completamente por los endpoints activos `compara()` y `comparaAnios()` en `VentasDatosController.php` mediante el servicio `PvVsOcPayloadBuilder` y repositorio `PvVsOcReportRepository`. |
| `TwHistPronosController` | `app/Http/Controllers/Ventas/TwHistPronosController.php` (Líneas 1-42) | 42 | **0 rutas en `routes/`**. Clase huérfana de pronósticos históricos. Sustituida completamente por el endpoint activo `compara()` en `VentasDatosController.php` mediante `PvVsOcPayloadBuilder`. |
| `TwHistVtasController` | `app/Http/Controllers/Ventas/TwHistVtasController.php` (Líneas 1-42) | 42 | **0 rutas en `routes/`**. Clase huérfana de ventas reales. Sustituida completamente por el endpoint activo `historico()` en `VentasDatosController.php` mediante `VentasHistoricasPayloadBuilder`. |

---

### 2.2 Categoría B: Métodos Stub Dummy en Controladores

En `app/Http/Controllers/UrdEngomado/UrdEngNucleosController.php` (Líneas 121-129), se identificaron métodos vacíos que solo ejecutan redirecciones no operativas:

```php
// Verbatim app/Http/Controllers/UrdEngomado/UrdEngNucleosController.php:121-129
public function create()
{
    return redirect()->route('urd-eng-nucleos.index');
}

public function edit(UrdEngNucleos $urdEngNucleo)
{
    return redirect()->route('urd-eng-nucleos.index');
}
```

*Causa Raíz:* En `routes/modules/engomado.php:118`, la ruta fue registrada como `Route::resource('urd-eng-nucleos', UrdEngNucleosController::class)` sin excluir las rutas innecesarias. Para cumplir el contrato de recurso REST sin implementar vistas dedicadas de creación/edición, se introdujeron stubs artificiales.  
*Solución:* Reemplazar en la ruta por `Route::resource('urd-eng-nucleos', ...)->except(['create', 'edit'])` y eliminar ambos métodos del controlador.

---

### 2.3 Categoría C: Modelos Huérfanos o Duplicados

Se identificaron **4 modelos Eloquent huérfanos o redundantes** que generan ambigüedad estructural:

| Modelo | Archivo y LoC | Tabla Mapeada | Evidencia de Inactividad / Colisión |
|---|---|---|---|
| `Modelos` | `app/Models/Planeacion/Catalogos/Modelos.php` (121 LoC) | `modelos` | **0 referencias** en todo el proyecto (`app/`, `routes/`, `resources/views/`). Toda la lógica de modelos de planeación utiliza el modelo canónico `App\Models\Planeacion\ReqModelosCodificados.php` (tabla `ReqModelosCodificados`). `Modelos.php` mapea la tabla legacy `modelos` y se encuentra completamente inactivo. |
| `Requerimiento` | `app/Models/Planeacion/Requerimiento.php` (41 LoC) | `requerimiento` | **0 referencias** en controladores, servicios, repositorios o vistas. Contiene atributos comentados en el `$fillable`. Archivo abandonado. |
| `User` | `app/Models/Sistema/User.php` (54 LoC) | `dbo.SYSUsuario` | **Duplicado redundante**. `config/auth.php` establece explícitamente a `App\Models\Sistema\Usuario::class` como el modelo de autenticación principal. `User.php` no es consumido por código de producción y solo persiste por mocks en tests unitarios. |
| `SysDepartamentos` | `app/Models/Sistema/SysDepartamentos.php` (30 LoC) | `dbo.SysDepartamentos` | **Colisión de clave primaria en el mismo namespace**. Mapea `$primaryKey = 'Depto'` (string). Solo es invocado en `UsuarioController.php` (líneas 7, 54, 168). Colisiona con el modelo canónico `App\Models\Sistema\SysDepartamento.php` (`$primaryKey = 'id'`, entero), residiendo ambos en `App\Models\Sistema\`; este último es consumido por los módulos restantes de la aplicación. |

---

### 2.4 Categoría D: Scopes Locales no Invocados en Modelos (13 Scopes)

Se identificaron **13 métodos `scope*`** definidos en modelos Eloquent que tienen cero llamadas en el código de producción, debido a que los controladores duplican las cláusulas SQL manualmente:

| # | Modelo y Archivo | Línea | Nombre del Scope | Comportamiento y Causa de No Uso |
|---|---|---|---|---|
| 1 | `app/Models/Urdido/UrdConsumoHilo.php` | 55 | `scopeRegistrados` | Filtra consumos activos. Solo era invocado por una relación huérfana. Cero llamadas en la app. |
| 2 | `app/Models/Engomado/EngFormulacionLineModel.php` | 53 | `scopeByFolio` | Filtra por número de folio. Los controladores escriben `->where('Folio', $folio)` en línea. |
| 3 | `app/Models/Sistema/SSYSFoliosSecuencia.php` | 79 | `scopeModulo` | Filtra por nombre de módulo. Los controladores escriben `->where('modulo', ...)` en línea. |
| 4 | `app/Models/Sistema/SYSRoles.php` | 233 | `scopeModulosPrincipales` | Filtra roles de nivel 1. Los servicios duplican `->where('Nivel', 1)->whereNull('Dependencia')`. |
| 5 | `app/Models/Sistema/SYSRoles.php` | 241 | `scopeSubmodulosDe` | Filtra submódulos jerárquicos. Reemplazado por consultas manuales. |
| 6 | `app/Models/Sistema/SYSRoles.php` | 249 | `scopeConAcceso` | Filtra acceso activo. Bypasseado en helpers de permisos. |
| 7 | `app/Models/Sistema/SYSRoles.php` | 257 | `scopeConPermisosUsuario` | Filtra permisos de usuario. Bypasseado en queries del repositorio. |
| 8 | `app/Models/Sistema/SYSUsuariosRoles.php` | 61 | `scopeConAcceso` | Filtra `acceso = 1`. Controladores y middleware escriben `->where('acceso', 1)` en línea. |
| 9 | `app/Models/Sistema/SYSUsuariosRoles.php` | 77 | `scopePorRol` | Filtra por ID de rol. Sin uso en el codebase. |
| 10 | `app/Models/Tejedores/TelMarbeteLiberadoModel.php` | 38 | `scopeByBarcode` | Filtra por código de barras. Cero llamadas. |
| 11 | `app/Models/Tejedores/TelMarbeteLiberadoModel.php` | 43 | `scopeByTelar` | Filtra por número de telar. Cero llamadas. |
| 12 | `app/Models/Tejedores/TelMarbeteLiberadoModel.php` | 48 | `scopeByItem` | Filtra por código de artículo. Cero llamadas. |
| 13 | `app/Models/Planeacion/ReqProgramaTejido.php` | 296 | `scopeProgramadas` | Filtra órdenes con fechas programadas asignadas (`whereNotNull('FechaInicio')->whereNotNull('FechaFinal')`). Los controladores duplican estas cláusulas `whereNotNull` manualmente o filtran por fecha en línea. |

---

### 2.5 Categoría E: Relaciones Eloquent Huérfanas (13 Relaciones)

Se detectaron **13 relaciones Eloquent** declaradas en modelos que no son utilizadas mediante eager loading (`with`), lazy loading o `whereHas` en ningún punto del sistema:

| # | Modelo y Archivo | Línea | Método de Relación | Tipo y Destino | Diagnóstico |
|---|---|---|---|---|---|
| 1 | `app/Models/Urdido/UrdProgramaUrdido.php` | 137 | `consumosRegistrados()` | HasMany `UrdConsumoHilo` | Cero consumidores en controladores o vistas. |
| 2 | `app/Models/Planeacion/Muestras.php` | 19 | `lineas()` | HasMany `MuestrasLine` | Todo el flujo de muestras usa consultas directas `DB::table()`. Cero llamadas a `lineas()`. |
| 3 | `app/Models/Planeacion/MuestrasLine.php` | 20 | `programa()` | BelongsTo `Muestras` | Inversa de relación huérfana anterior. Cero llamadas a `programa()`. |
| 4 | `app/Models/Atadores/AtaDevolucionesModel.php` | 59 | `montadoTela()` | BelongsTo `AtaMontadoTelasModel` | Cero llamadas; controladores consultan por ID manual. |
| 5 | `app/Models/Engomado/EngProduccionFormulacionModel.php` | 71 | `linesByFolio()` | HasMany `EngFormulacionLineModel` | Duplicado redundante de `lines()`. Usa clave de negocio `Folio`. |
| 6 | `app/Models/Engomado/EngFormulacionLineModel.php` | 47 | `headerByFolio()` | BelongsTo `EngProduccionFormulacionModel` | Duplicado redundante de `header()`. Usa clave de negocio `Folio`. |
| 7 | `app/Models/Mantenimiento/CatTipoFalla.php` | 28 | `parosFallas()` | HasMany `CatParosFallas` | Cero llamadas. |
| 8 | `app/Models/Mantenimiento/CatTipoFalla.php` | 36 | `fallasParos()` | HasMany `ManFallasParos` | Cero llamadas. |
| 9 | `app/Models/Mantenimiento/CatParosFallas.php` | 46 | `tipoFalla()` | BelongsTo `CatTipoFalla` | Cero llamadas. |
| 10 | `app/Models/Mantenimiento/ManFallasParos.php` | 77 | `tipoFalla()` | BelongsTo `CatTipoFalla` | Cero llamadas; consultas usan joins manuales. |
| 11 | `app/Models/Mecanicos/MecVerificaMaquinaLineModel.php` | 34 | `verificacion()` | BelongsTo `MecVerificaMaquinaModel` | Cero llamadas. |
| 12 | `app/Models/Sistema/SYSRoles.php` | 150 | `usuariosRoles()` | HasMany `SYSUsuariosRoles` | Duplicado exacto de `permisosUsuario()` (líneas 158-164). |
| 13 | `app/Models/Sistema/SYSRoles.php` | 217, 225 | `moduloPadre()`, `submódulos()` | Self-referential HasMany/BelongsTo | Sin uso. `submódulos()` contiene carácter no ASCII con tilde en nombre de método. |

---

### 2.6 Categoría F: Accessors y Mutators Obsoletos / Redundantes (6 Métodos)

1. `app/Models/Urdido/UrdProgramaUrdido.php:96` — `getMcCoyNumberAttribute()` (`mc_coy_number`): 0 llamadas en frontend y backend.
2. `app/Models/Planeacion/ReqEficienciaStd.php:97, 103, 111` — `getSalonAttribute()`, `getTelarAttribute()`, `getFibraAttribute()`: Passthroughs triviales 1:1 a las columnas nativas (`SalonTejidoId`, `NoTelarId`, `FibraId`). El código consume directamente los nombres de columna reales de SQL Server.
3. `app/Models/Tejido/TejInventarioTelares.php:69, 87` — `getLoteProveedorAttribute()`, `getNoProveedorAttribute()`: Mappings obsoletos para nombres de columna legacy con espacios que ya no existen en la base de datos.

---

### 2.7 Categoría G: Antipatrones de Consulta y Bypass Sistemático de Scopes

1. **Bypass Sistemático de Scopes de Dominio:** A pesar de contar con scopes definidos en modelos como `SYSRoles` y `SYSUsuariosRoles`, los desarrolladores reescriben manualmente cláusulas `where` idénticas en decenas de archivos (`where('Nivel', 1)->whereNull('Dependencia')`, `where('acceso', 1)`), generando divergencias cuando una regla de negocio cambia.
2. **Colisión de Modelos sobre la Misma Tabla Física:** La tabla `dbo.SysDepartamentos` posee dos modelos compitiendo en el codebase dentro del mismo namespace `App\Models\Sistema\`: `SysDepartamentos` (PK string `Depto`) y `SysDepartamento` (PK integer `id`). Un solo controlador (`UsuarioController`) utiliza el primero, mientras el resto del sistema consume el segundo canónico.
3. **Rutas Relacionales Duplicadas por Clave Primaria y Clave de Negocio:** En `EngProduccionFormulacionModel` conviven relaciones gemelas que resuelven las mismas líneas a través de su ID autoincremental (`id`) y a través del folio de negocio (`Folio`), multiplicando el esfuerzo de mantenimiento.

---

## 3. R2. Diagnóstico de Segmentación y Arquitectura (Controller Bloat & Technical Debt)

### 3.1 Metodología de Clasificación por Semáforo

Los 141 componentes del namespace de controladores fueron categorizados bajo los siguientes umbrales arquitectónicos:

- 🔴 **CRÍTICO (Rojo - 39 componentes, 27,679 LoC, ~73.8% del volumen):** Componentes con >300-500 LoC, responsabilidades múltiples (violación SRP), mezcla de lógica de negocio o transaccional directa (`DB::beginTransaction`), motores matemáticos camuflados, generación procedural de hojas de cálculo o llamadas directas a ERP externo.
- 🟡 **MEDIO (Amarillo - 27 componentes, 5,610 LoC, 15.0% del volumen):** Componentes entre 150 y 300 LoC, o que aun delegando a servicios retienen lógica pesada de formateo de UI, árboles de navegación o validación inline extensa.
- 🟢 **ACEPTABLE (Verde - 72 componentes, 3,624 LoC, ~9.7% del volumen):** Componentes delgados (<150 LoC), controladores de responsabilidad única (`__invoke`), delegación limpia a Services/Repositories o traits de soporte. *(Nota: Los 3 controladores restantes son huérfanos inactivos clasificados estrictamente en Categoría A con 126 LoC, ~0.3%)*.

```
+----------------------------------------------------------------------------------------------------+
| DISTRIBUCIÓN DEL VOLUMEN DE CÓDIGO POR SEMÁFORO DE DEUDA TÉCNICA                                  |
+----------------------------------------------------------------------------------------------------+
| [========================================================================] 73.8% CRÍTICO (27,679 LoC)|
| [==============] 15.0% MEDIO (5,610 LoC)                                                           |
| [==========] 10.0% ACEPTABLE / CÓDIGO MUERTO (3,750 LoC: 72 aceptables + 3 huérfanos)             |
+----------------------------------------------------------------------------------------------------+
```

---

### 3.2 Tabla Completa de los 39 Controladores Críticos (Rojo)

| # | Archivo del Controlador | LoC | Métodos (Pub/Priv) | Dependencias Inyectadas | Mecanismo de Validación | Smells y Desviaciones Arquitectónicas |
|---|---|---|---|---|---|---|
| 1 | `Planeacion/ProgramaTejido/OrdenDeCambio/Felpa/OrdenDeCambioFelpaController.php` | 1,942 | 2 / 18 | 0 | Ninguna | Generador procedural masivo de Excel celda por celda; manipula estilos, fórmulas y celdas directamente; plantilla binaria `ordfelpa.xlsx` en el directorio. |
| 2 | `Planeacion/CatalogoPlaneacion/ModelosCodificados/CodificacionController.php` | 1,647 | 18 / 12 | 0 | `Validator::make` inline | 6 responsabilidades: CRUD, parseo Excel en memoria, cálculo de similitud de modelos, estadísticas, consulta AX TI, topología de telares. |
| 3 | `Tejido/CortesEficiencia/CortesEficienciaController.php` | 1,538 | 20 / 14 | 0 | Inline `$request->validate` | Inserts masivos en lotes (95 filas x 21 cols), `DB::beginTransaction`, generación de PDF (Dompdf), Excel, notificaciones Telegram. |
| 4 | `Urdido/ReportesUrdidoController.php` | 1,500 | 13 / 11 | 1 (en método) | Inline | 7 reportes no relacionados en un solo archivo (03-OEE, Kaizen, Roturas, BPM, Resumen, Panel KM, Excel). Métodos de 150+ líneas. |
| 5 | `Planeacion/ProgramaTejido/funciones/BalancearTejido.php` | 1,165 | 7 stat / 17 priv | 0 | Inline | Motor algorítmico de balanceo de tejido; métodos estáticos que aceptan `$request` y retornan `JsonResponse`. Ubicado en `funciones/`. |
| 6 | `Atadores/ProgramaAtadores/AtadoresController.php` | 1,129 | 6 / 8 | 1 (en método) | Inline | Método `save()` de 530 líneas (líneas 484 a 1014). Modifica 10 modelos distintos (`AtaMontadoTelas`, `AtaActividades`, etc.), transacciones, Telegram Jobs. |
| 7 | `Planeacion/ProgramaTejido/LiberarOrdenesController.php` | 1,071 | 11 / 9 | 9 | Inline | Método `liberar()` de 506 líneas. `@SuppressWarnings(PHPMD)`. `DB::beginTransaction`, mutaciones directas en `ReqProgramaTejido`. |
| 8 | `Atadores/ProgramaAtadores/AtaDevolucionesController.php` | 990 | 8 / 6 | 0 | Inline | Conexión directa a `sqlsrv_ti` (`WMSLocation`), cálculo de reservas de julios, lógica específica de Karl Mayer, transacciones. |
| 9 | `Tejido/MarcasFinales/MarcasController.php` | 939 | 9 / 10 | 0 | Inline | `DB::beginTransaction`, batch insert en `TejMarcasLine`, Dompdf, Excel, envío de alertas por Telegram, cálculo de turnos. |
| 10 | `Tejido/Reportes/ReporteInvTelasController.php` | 937 | 7 / 8 | 0 | Inline | Topologías y telares cableados en constantes, Dompdf, Excel, cruce manual de 4 modelos de diferentes módulos. |
| 11 | `Urdido/Configuracion/ModuloProduccionUrdidoController.php` | 921 | 14 / 8 | 0 | Inline | Captura pesada de producción de urdido; duplica lógica de `ProduccionTrait`; validaciones complejas de tara y kilos netos (tope 700 kg). |
| 12 | `Planeacion/CatCodificados/CatCodificacionController.php` | 871 | 11 / 7 | 2 | StoreCatCodificadosExcelRequest | Duplica el dominio de `CodificacionController`; lectura y encolamiento de Excel con PhpSpreadsheet; cálculo de similitud. |
| 13 | `Tejedores/InventarioTelaresController.php` | 869 | 11 / 6 | 0 | Inline | CRUD de inventario de telares, manipulación y normalización de fechas Carbon en cada fila, sincronización de julios. |
| 14 | `Engomado/CapturaFormulas/EngProduccionFormulacionController.php` | 785 | 10 / 8 | 1 | Inline | `DB::beginTransaction`, inserción por lotes en `EngFormulacionLineModel`, resolución de BOM en AX TI, filtros de alta latencia. |
| 15 | `Planeacion/CatLMat/CatLMatController.php` | 738 | 12 / 6 | 2 | Inline | Cálculos de curvas de peso, porcentajes (suma 100%), replace-all en BD, conexión `sqlsrv_ti`, validación manual compleja. |
| 16 | `Planeacion/ProgramaTejido/funciones/EliminarTejido.php` | 676 | 1 stat / 5 priv | 0 | Ninguna | Motor de reprogramación y eliminación de colas de telares. `DB::beginTransaction`, recálculo de secuencias de fechas. Ubicado en `funciones/`. |
| 17 | `Engomado/Produccion/ModuloProduccionEngomadoController.php` | 638 | 12 / 6 | 0 | Inline | Captura de producción de engomado; duplica `ProduccionTrait`; cálculos de kilos brutos (tope 2000 kg) y mermas. |
| 18 | `Tejido/InventarioTelas/TelaresController.php` | 620 | 5 / 7 | 0 | Ninguna | Consultas complejas crudas a base de datos para inventario de telares Jacquard, Itema, Smit y Karl Mayer. 0% tests. |
| 19 | `Planeacion/Utilerias/MoverOrdenesController.php` | 599 | 6 / 5 | 0 | `Validator::make` inline | `DB::beginTransaction`, reasignación y bump de posiciones de cola de órdenes entre telares, recálculo de secuencias. |
| 20 | `Atadores/Reportes/ReportesAtadoresController.php` | 503 | 7 / 6 | 0 | Inline | Múltiples reportes (OEE Atadores, Programa, Karl Mayer), jobs de actualización (`ActualizarOeeAtadoresJob`), exportaciones Excel. |
| 21 | `Urdido/ProgramaUrdido/ProgramarUrdidoController.php` | 498 | 8 / 6 | 2 | `Validator::make` inline | Tablero de programación de urdido; reordenamiento de prioridades en lote, validaciones de supervisor, jobs de calidad. |
| 22 | `Planeacion/ProgramaTejido/ProgramaTejidoOperacionesController.php` | 488 | 10 / 4 | 0 | Inline | Orquestador de operaciones (cambio de telar, duplicar, dividir, vincular); delega directamente a clases en `funciones/`. |
| 23 | `ModulosController.php` | 464 | 9 / 3 | 1 | `Validator::make` inline | Gestión de módulos del sistema, validación manual masiva, carga y optimización de imágenes (`ImageOptimizer`), árbol de roles. |
| 24 | `Tejedores/NotificarMontadoRollo/NotificarMontRollosController.php` | 447 | 6 / 5 | 0 | Inline | Notificación de rollos montados, consultas multimodelo (`TelTelaresOperador`, `TejInventarioTelares`, `TelMarbeteLiberadoModel`). |
| 25 | `Engomado/ProgramaEngomado/ProgramarEngomadoController.php` | 436 | 7 / 5 | 2 | Inline | Duplica estructura de `ProgramarUrdidoController`; actualización de prioridades en lote (hasta 2000 filas). |
| 26 | `Planeacion/ProgramaTejido/DescargarProgramaController.php` | 434 | 1 / 4 | 0 | Inline | Generación manual y formateo de archivo de texto plano `ProgramaTejido.txt` con decenas de columnas numéricas formateadas. |
| 27 | `Tejido/ProduccionReenconado/ProduccionReenconadoCabezuelaController.php` | 422 | 7 / 4 | 0 | `Validator::make` inline | `DB::beginTransaction`, consultas directas a `sqlsrv_ti` (`InventTable`), manejo de mermas y cabezuelas. |
| 28 | `Planeacion/Utilerias/FinalizarOrdenesController.php` | 416 | 4 / 3 | 0 | Inline | `DB::beginTransaction`, finalización de órdenes en lote, validación de producción > 0, normalización de estado `EnProceso`. 0% tests. |
| 29 | `Planeacion/ProgramaTejido/RedboothProgramaTejidoController.php` | 399 | 6 / 3 | 2 | SaveRedboothProgramaTejidoRequest | Integración directa con API externa de Redbooth, mapeo y sincronización con `ReqProgramaTejido` y `CatCodificados`. |
| 30 | `Planeacion/ProgramaTejido/funciones/VincularTejido.php` | 398 | 2 stat / 3 priv | 0 | Inline | Lógica de vinculación y desvinculación de órdenes compartidas (`OrdCompartidaLider`). Ubicado en `funciones/`. |
| 31 | `Mantenimiento/ParosController.php` | 393 | 5 / 3 | 1 | Inline | Alta y reporte de paros, validación de duplicados, notificaciones Telegram (`ParoTelegramNotifier`). |
| 32 | `PDFController.php` | 391 | 6 / 4 | 0 | Inline | Generación de PDFs (Dompdf) y Excels (PhpSpreadsheet) para órdenes de Urdido y Engomado construidos procedimentalmente. 0% tests. |
| 33 | `Engomado/ReportesEngomadoController.php` | 386 | 6 / 3 | 0 | Inline | Reportes múltiples de engomado (03-OEE, Kaizen, BPM, Control Merma, Resumen), llamadas directas a Maatwebsite Excel. |
| 34 | `Configuracion/ConfiguracionController.php` | 381 | 4 / 2 | 0 | `Validator::make` inline | `set_time_limit(600)`, importación pesada de Excel con transacciones, llamadas directas a `Artisan::call('cache:clear')`. 0% tests. |
| 35 | `Tejedores/NotificarMontadoJulios/NotificarMontadoJulioController.php` | 368 | 4 / 4 | 0 | Inline | Notificación de montado de julios por operador, resolución Karl Mayer, despacho de Telegram jobs (`EnviarMensajeTelegram`). |
| 36 | `Tejedores/Configuracion/TelaresOperador/TelTelaresOperadorController.php` | 346 | 6 / 3 | 0 | Inline | Asignación masiva de telares a operadores, transacción `DB::transaction`, consultas directas agrupadas. 0% tests. |
| 37 | `ProgramaUrdEng/ReservarProgramar/ReservarProgramarController.php` | 321 | 5 / 3 | 2 | Inline | Coordinación del tablero de reservar/programar; consultas de 1,000 telares sin paginación, manipulación de queries complejas. |
| 38 | `Tejido/Reportes/ReporteMarcasFinalesController.php` | 308 | 3 / 4 | 0 | Inline | Agrupación por turnos y fechas para marcas finales, exportación Excel en controller. |
| 39 | `Configuracion/MensajesController.php` | 305 | 6 / 3 | 0 | Inline | Llamadas HTTP directas con `Http::get` a la API de Telegram (`https://api.telegram.org/bot...`) dentro de métodos de acción. |

---

### 3.3 Análisis en Profundidad del Top 10 Controladores con Mayor Sobrecarga

1. **`OrdenDeCambioFelpaController` (1,942 LoC):** No es un controlador web en el sentido estricto. Carece de acciones CRUD y contiene 18 métodos privados dedicados a la manipulación celda por celda de PhpSpreadsheet (bordes, fuentes, fórmulas matemáticas de repeticiones y anchos). Contiene la plantilla `.xlsx` en su propia carpeta.
2. **`CodificacionController` (1,647 LoC):** Monolito que absorbe el catálogo completo de modelos codificados de tejido, procesamiento de archivos Excel de 10,000 filas en memoria, cálculo heurístico de similitud de modelos, sincronización con el ERP AX y estadísticas de telares.
3. **`CortesEficienciaController` (1,538 LoC):** Dios-controlador de planta. Maneja la captura del turno de tejedores, ejecuta validaciones manuales de 95 campos, fragmenta inserts masivos para eludir el límite de 2,100 parámetros de SQL Server, orquesta transacciones `DB::beginTransaction`, genera el informe en PDF con Dompdf, compila el Excel y envía resúmenes por bot de Telegram.
4. **`ReportesUrdidoController` (1,500 LoC):** Archivo procedural que amalgama 7 reportes analíticos completamente independientes (OEE, Kaizen, Roturas, BPM, Resumen de producción, Panel de control Karl Mayer y exportación tabular). Cada método excede las 150 líneas de consultas y formateo manual.
5. **`BalancearTejido` (1,165 LoC en `funciones/`):** Motor de cálculo que balancea las órdenes de tejido en la línea de tiempo de los telares, resolviendo dependencias de calendario laboral, paros programados y velocidades ideales. Expone métodos estáticos que reciben `Request` HTTP y devuelven `JsonResponse`.
6. **`AtadoresController` (1,129 LoC):** El método `save()` tiene 530 líneas continuas (líneas 484 a 1014) de lógica imperativa. Modifica simultáneamente 10 modelos distintos (`AtaMontadoTelas`, `AtaActividades`, `AtaMaquinas`, etc.), abre transacciones manuales, gestiona excepciones y despacha notificaciones asíncronas de Telegram.
7. **`LiberarOrdenesController` (1,071 LoC):** Su método `liberar()` abarca 506 líneas de código no interrumpido, suprimiendo formalmente las alertas de complejidad ciclomática (`@SuppressWarnings(PHPMD)`). Contiene reglas críticas de negocio para cálculo de marbetes, asignación de BOMs crudos y saldos.
8. **`AtaDevolucionesController` (990 LoC):** Controla la devolución de julios en el área de atado. Establece conexiones crudas contra `sqlsrv_ti` (`WMSLocation`), calcula reservas de urdido y engomado y bifurca lógica según se trate de telares estándar o urdidoras Karl Mayer.
9. **`MarcasController` (939 LoC):** Gestiona la inspección de marcas finales de tejido, turnos de supervisión, inserciones en bloque en `TejMarcasLine`, exportaciones Dompdf y Excel, y alertas de calidad vía Telegram.
10. **`ReporteInvTelasController` (937 LoC):** Generador de reportes de inventario de telares que mantiene listas de telares e identificadores de máquina cableados en constantes PHP, procesa 4 modelos de diferentes áreas y renderiza salidas PDF y Excel en el mismo controlador.

---

### 3.4 Inventario de Controladores Medios (27 Componentes) y Aceptables (75 Componentes)

#### Controladores Medios (Amarillo - 27 Componentes, 5,610 LoC)
1. `UsuarioController.php` (487 LoC) — Inyecta servicios pero retiene 200+ líneas de transformación recursiva del árbol de roles y permisos.
2. `mecanicos/MecReportesController.php` (450 LoC) — Consolida 2 reportes y generación binaria en controller.
3. `Tejido/Reportes/SaldosController.php` (287 LoC) — Agrupación manual de órdenes finalizadas compartidas.
4. `Planeacion/ProgramaTejido/funciones/DragAndDropTejido.php` (269 LoC) — Reordenamiento de posiciones con transacciones.
5. `Planeacion/ProgramaTejido/RepasoController.php` (263 LoC) — Alta de repaso con transacciones manuales.
6. `Planeacion/ProgramaTejido/ProgramaTejidoCalendariosController.php` (260 LoC) — Asignación de calendarios.
7. `ProgramaUrdEng/ReservarProgramar/CrearOrdenKarlMayerController.php` (250 LoC) — Alta KM con validación inline masiva.
8. `Monitoreo/TelemetriaController.php` (250 LoC) — Ingesta de métricas de telemetría.
9. `Telegram/TelegramController.php` (250 LoC) — Webhooks y endpoints de Telegram.
10. `Planeacion/CatalogoPlaneacion/CatCalendarios/CalendarioController.php` (244 LoC) — CRUD múltiple en un archivo.
11. `Planeacion/CatalogoPlaneacion/CatalogosPlaneacionVista.php` (239 LoC) — Definición de schema UI en controllers.
12. `Mantenimiento/ParosCatalogoController.php` (230 LoC) — Catálogos en cascada con joins múltiples.
13. `ProgramaUrdEng/ReservarProgramar/ReservaInventarioController.php` (210 LoC) — Transacciones de reserva.
14. `Planeacion/Alineacion/AlineacionController.php` (203 LoC) — Renderizado PDF/Excel en controller.
15. `mecanicos/OrdenesTrabajo/VistaOrdenesTrabajoController.php` (200 LoC) — Vistas y consultas OT.
16. `Planeacion/ProgramaTejido/helper/TejidoHelpers.php` (199 LoC) — Adaptador temporal obsoleto en controllers.
17. `ProgramaUrdEng/ReservarProgramar/BomMaterialesController.php` (190 LoC) — Resolución de listas de materiales.
18. `mecanicos/OrdenesTrabajo/ConsultaOrdenesTrabajoController.php` (180 LoC) — Filtros y consultas de órdenes mecánicas.
19. `Tejido/Configuracion/SecuenciaInvTelas/SecuenciaInvTelasController.php` (180 LoC) — Secuencia de inventario de telas.
20. `Mantenimiento/ParosCierreController.php` (170 LoC) — Cierre y validación de paros.
21. `Planeacion/CatalogoPlaneacion/CatTelares/CatalagoTelarController.php` (170 LoC) — CRUD de catálogo de telares.
22. `Planeacion/ProgramaTejido/ColumnasProgramaTejidoController.php` (170 LoC) — Configuración de columnas de grilla.
23. `Planeacion/ProgramaTejido/ProgramaTejidoController.php` (170 LoC) — Shell principal de planeación.
24. `Tejido/Configuracion/SecuenciaCorteEficiencia/SecuenciaCorteEficienciaController.php` (165 LoC) — Secuencia de cortes.
25. `Tejido/Configuracion/SecuenciaMarcasFinales/SecuenciaMarcasFinalesController.php` (165 LoC) — Secuencia de marcas.
26. `Integraciones/RedboothController.php` (160 LoC) — Integración base OAuth Redbooth.
27. `UrdEngomado/UrdEngNucleosController.php` (160 LoC) — Gestión de núcleos urdido/engomado.

#### Componentes Aceptables (Verde - 72 Componentes, 3,624 LoC)
Incluye controladores con responsabilidad única, controladores de acción (`__invoke`), traits y clases base:
`AuthController`, `Controller`, `RespondeCatalogo`, `PuedeEditarProgramaUrdEng`, `RespuestasErrorUrdEng`, `AtaActividadesController`, `CatalogosAtadoresVista`, `AtaComentariosController`, `AtaMaquinasController`, `BaseDeDatosController`, `DepartamentosController`, `ModuloPermisosController`, `SecuenciaFoliosController`, `CrudoAuditController`, `CrudoController`, `CrudoReporteDiaController`, `ExternalRedboothController`, `CatUbicacionesController`, `CalificarJuliosController`, `EditarOrdenesEngomadoController`, `CatalogosFallasController`, `ManOperadoresMantenimientoController`, `ReportesMantenimientoController`, `AuditoriaProgramaTejidoController`, `AplicacionesController`, `CatalagoEficienciaController`, `MatrizCalibresController`, `MatrizHilosController`, `PesosRollosController`, `CatalagoVelocidadController`, `EstandarCatalogoController`, `ProgramaTejidoBalanceoController`, `ProgramaTejidoCatalogosController`, `ProgramaTejidoReadController`, `ReimprimirOrdenesController`, `ReqProgramaTejidoLineController`, `UpdateTejido`, `DateHelpers`, `ProgramaTejidoSecuenciaHelper`, `QueryHelpers`, `UtilityHelpers`, `TiemposPreparacionController`, `InventarioDisponibleController`, `InventarioTelaresController`, `ProgramarUrdEngController`, `ResumenSemanasController`, `CatalogoCalibresController`, `TelDesarrolladoresController`, `TelDesarrolladoresMuestrasController`, `ReportesDesarrolladoresController`, `ReportesTejedoresController`, `SecuenciaInvTramaController`, `ConsultarRequerimientoController`, `NuevoRequerimientoController`, `PromedioParosEficienciaController`, `ReporteRpmSemanalController`, `TrazabilidadController`, `TrazabilidadDetailController`, `CatalogosUrdidoController`, `ListaMaterialesController`, `EditarOrdenesProgramadasController`, `VentasDatosController` (métodos activos `compara`, `comparaAnios`, `historico`), `MecActividadesController`, `MecVerificaMaquinaController`, `ActualizarOrdenTrabajoController`, `AutorizarOrdenTrabajoController`, `CrearOrdenTrabajoController`, `EliminarOrdenTrabajoController`, `FinalizarOrdenTrabajoController`, `ActualizarLineaController`, `CrearLineaController`, `EliminarLineaController`.

*(Nota: Se excluyen explícitamente de esta lista `TwHistPedidosController`, `TwHistPronosController` y `TwHistVtasController`, ya que pertenecen estrictamente a la Sección 2.1 como Código Muerto 100% Huérfano Categoría A y no a controladores aceptables en producción).*

---

### 3.5 Ocho Recetas Arquitectónicas Concretas de Extracción

#### Receta 1: Desacoplamiento de `OrdenDeCambioFelpaController` (1,942 LoC -> 40 LoC)
- **Problema:** Generación masiva de Excel celda por celda dentro de la capa HTTP; archivo binario `.xlsx` en el repositorio de controladores.
- **Acciones Concretas:**
  1. Mover `app/Http/Controllers/Planeacion/ProgramaTejido/OrdenDeCambio/Felpa/ordfelpa.xlsx` a `storage/templates/excel/ordfelpa.xlsx`.
  2. Crear servicio `App\Services\Planeacion\OrdenDeCambio\Felpa\OrdenDeCambioSpreadsheetGenerator`: encapsula la apertura de la plantilla, el mapeo de registros en la hoja `REGISTRO`, la inyección de fórmulas matemáticas y el formateo de celdas.
  3. Crear servicio `App\Services\Planeacion\OrdenDeCambio\Felpa\OrdenDeCambioCatCodificadosSync`: aísla la sincronización de atributos de tejido en `CatCodificados`.
  4. Reducir el controlador a 40 LoC despachando la descarga mediante `response()->download()` o `BinaryFileResponse`.

#### Receta 2: Unificación y Extracción de `CodificacionController` y `CatCodificacionController` (2,518 LoC combinadas -> 120 LoC combinadas)
- **Problema:** Dos controladores paralelos que duplican la importación masiva de modelos codificados, el parseo de Excel y la búsqueda de similitud.
- **Acciones Concretas:**
  1. Crear `App\Services\Planeacion\Codificacion\ModeloSimilarScoringService`: encapsula el algoritmo heurístico de cálculo de similitud y ponderación de campos.
  2. Crear `App\Services\Planeacion\Codificacion\ImportacionModelosExcelService`: encapsula la lectura por bloques (chunks) de PhpSpreadsheet y el mapeo de columnas.
  3. Crear repositorio `App\Repositories\Planeacion\ModeloCodificadoRepository`: aísla las consultas de alta velocidad (`getAllFast`, estadísticas y listados).
  4. Crear servicio de integración `App\Services\Integraciones\AxFlogsIntegrationService`: aísla las consultas a `sqlsrv_ti`.
  5. Crear Form Requests: `StoreModeloCodificadoRequest`, `UpdateModeloCodificadoRequest`, `ImportarExcelCodificacionRequest`.

#### Receta 3: Desacoplamiento de `CortesEficienciaController` (1,538 LoC -> 110 LoC)
- **Problema:** Dios-controlador que combina captura de turno, inserciones masivas en lotes SQL Server, Dompdf, Excel y Telegram.
- **Acciones Concretas:**
  1. Crear `App\Services\Tejido\CortesEficiencia\CorteEficienciaBatchWriter`: encapsula los inserts masivos en `TejEficienciaLine` respetando el límite de 2,100 parámetros de SQL Server dentro de una transacción gestionada.
  2. Crear `App\Services\Tejido\CortesEficiencia\CorteEficienciaDocumentService`: aísla la generación de PDF (Dompdf) y Excel (Maatwebsite).
  3. Crear `App\Services\Tejido\CortesEficiencia\CorteEficienciaTelegramService`: formatea y despacha notificaciones al bot de Telegram.
  4. Crear Form Requests: `StoreCorteEficienciaRequest`, `UpdateCorteEficienciaRequest`.

#### Receta 4: Modularización de `ReportesUrdidoController` (1,500 LoC -> 5 Controladores de 35 LoC c/u)
- **Problema:** 7 reportes analíticos consolidados en un solo archivo procedural.
- **Acciones Concretas:**
  1. Dividir en controladores dedicados por reporte:
     - `ReporteOeeUrdidoController` (35 LoC)
     - `ReporteKaizenUrdidoController` (35 LoC)
     - `ReporteRoturasUrdidoController` (35 LoC)
     - `ReporteBpmUrdidoController` (35 LoC)
     - `ReporteResumenUrdidoController` (35 LoC)
  2. Extraer servicios de cálculo analítico a `App\Services\Urdido\Reportes\`:
     - `KaizenMetricsAggregator`
     - `RoturasMetricsCalculator`
     - `OeeCalculationService`

#### Receta 5: Extracción de Acción en `LiberarOrdenesController` (1,071 LoC -> 85 LoC)
- **Problema:** Método `liberar()` tiene 506 líneas continuas con transacciones manuales y múltiples mutaciones de modelos.
- **Acciones Concretas:**
  1. Crear la clase de acción `App\Actions\Planeacion\ProgramaTejido\LiberarOrdenAction`: encapsula el flujo transaccional de 500 líneas, orquestando los 9 servicios inyectados (`LiberarMarbetesCalculator`, `LiberarBomCrudoResolver`, `LiberarCatCodificadosWriter`, etc.).
  2. Crear `App\Http\Requests\Planeacion\LiberarOrdenRequest`: valida obligatoriedad de `bomId`, `bomName`, prioridades y saldos antes de entrar al controller.
  3. Reducir el método `liberar()` a 15 líneas delegando a `LiberarOrdenAction::execute()`.

#### Receta 6: Saneamiento de `AtadoresController` y `AtaDevolucionesController` (2,119 LoC combinadas -> 140 LoC combinadas)
- **Problema:** Método `save()` de 530 líneas (líneas 484 a 1014) en Atadores modificando 10 tablas; `AtaDevolucionesController` consulta `sqlsrv_ti` directamente.
- **Acciones Concretas:**
  1. Crear `App\Services\Atadores\AtadoExecutionService`: encapsula el flujo transaccional de guardado de atado, checklist y actualización de estado en `AtaMontadoTelasModel`.
  2. Crear `App\Services\Atadores\DevolucionJulioService`: encapsula el registro de devoluciones, validación contra `InvTelasReservadas` y manejo de barras Karl Mayer.
  3. Crear repositorio `App\Repositories\Atadores\WmsLocationRepository`: aísla la consulta contra `WMSLocation` en `sqlsrv_ti`.
  4. Crear Form Requests: `SaveAtadoRequest`, `StoreDevolucionRequest`.

#### Receta 7: Desacoplamiento de Producción Urdido y Engomado (1,559 LoC combinadas -> 120 LoC combinadas)
- **Problema:** Duplicación de lógica de captura de planta entre Urdido y Engomado a través de `ProduccionTrait`.
- **Acciones Concretas:**
  1. Reemplazar `ProduccionTrait` por un servicio desacoplado: `App\Services\Produccion\CapturaProduccionService`.
  2. Implementar patrón Strategy para las reglas de validación específicas de departamento:
     - `UrdidoProduccionPolicy` (tope de 700 kg neto).
     - `EngomadoProduccionPolicy` (tope de 2000 kg bruto, mermas con y sin goma).
  3. Desacoplar el cálculo de turnos y operadores a servicios reutilizables.

#### Receta 8: Reubicación de Motores en `Planeacion\ProgramaTejido\funciones\` (2,615 LoC)
- **Problema:** Clases de dominio puras (`BalancearTejido` 1,165 LoC, `EliminarTejido` 676 LoC, `VincularTejido` 398 LoC, `DragAndDropTejido` 269 LoC) alojadas en la capa HTTP.
- **Acciones Concretas:**
  1. Mover y reestructurar a `App\Services\Planeacion\ProgramaTejido\`:
     - `App\Services\Planeacion\ProgramaTejido\Balanceo\BalancearTejidoEngine`
     - `App\Services\Planeacion\ProgramaTejido\Acciones\EliminarTejidoAction`
     - `App\Services\Planeacion\ProgramaTejido\Acciones\VincularTejidoAction`
     - `App\Services\Planeacion\ProgramaTejido\Acciones\MoverPosicionAction`
  2. Eliminar la carpeta `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/`.
  3. Eliminar los adaptadores obsoletos en `app/Http/Controllers/Planeacion/ProgramaTejido/helper/` (`TejidoHelpers`, `DateHelpers`, etc.), redirigiendo a los consumidores hacia las clases de dominio reales.

---

## 4. R3. Auditoría de la Suite de Pruebas y Brechas de Cobertura (Test Suite & Coverage Gap Analysis)

### 4.1 Arquitectura del Test Runner y Guardián de Conexión

La suite de pruebas automatizadas de Towell cuenta con **343 archivos de prueba** (196 Feature + 147 Unit). La ejecución está diseñada bajo una estrategia estricta de aislamiento de base de datos para proteger los servidores de producción:

1. **Configuración en `phpunit.xml`:**
   - La suite corre bajo SQLite en memoria (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
   - Se excluyen por defecto los tests que requieren SQL Server real mediante `<exclude><group>sqlserver</group></exclude>` (líneas 18-20).
2. **Interceptor Dinámico de Seguridad en `tests/TestCase.php` (Líneas 26-42):**
   ```php
   // tests/TestCase.php: Bloqueo activo contra conexiones reales a SQL Server
   $sqlServer = array_keys(array_filter(
       config('database.connections'),
       fn (array $config) => ($config['driver'] ?? null) === 'sqlsrv'
   ));
   foreach ($sqlServer as $conexion) {
       DB::extend($conexion, function (array $config, string $name) {
           $connection = $this->app['db.factory']->make($config, $name);
           if (($config['driver'] ?? null) !== 'sqlite') {
               $bloqueo = fn () => throw new \RuntimeException(
                   "El test intento conectar al SQL Server real '{$name}'. Mockea el servicio o reapunta la conexion a sqlite."
               );
               $connection->setPdo($bloqueo)->setReadPdo($bloqueo);
           }
           return $connection;
       });
   }
   ```
   *Efecto:* Cualquier prueba que intente ejecutar una consulta sobre las conexiones `sqlsrv`, `sqlsrv_ti`, `sqlsrv_tow_tow` o `sqlsrv_Reportes_Towell` sin reapuntar el driver a SQLite mediante el trait `UsesSqlsrvSqlite` es abortada inmediatamente con una excepción en tiempo de ejecución.
3. **Pruebas con `#[Group('sqlserver')]`:** Existen pruebas específicas que se omiten automáticamente en entornos locales si no se dispone de una instancia de SQL Server con los triggers de auditoría compilados (`Feature/AuditoriaProgramaTejidoTest.php`, `Feature/ProgramaTejidoIndexSmokeTest.php`, `Feature/Tejido/SaldosQuerySqlServerTest.php`).

---

### 4.2 Tabla Completa de Correlación de los 125 Controladores (98 Probados, 27 sin Cobertura)

A continuación se detalla el estado de cobertura automatizada para la totalidad de los 125 controladores de aplicación:

| # | Controlador | Módulo / Ruta | Estado | Pruebas Correlacionadas en `tests/` | Notas de Cobertura |
|---|---|---|---|---|---|
| 1 | `AuthController` | Raíz | **Probado** | `Feature/PwaSessionDuplicationTest`, `Feature/Monitoreo/AccesosTest` | Flujo de login/logout y PWA cubierto |
| 2 | `ModulosController` | Raíz | **Probado** | `Feature/ModulosRutaTest`, `Feature/NavegacionModulosTest` | Navegación de módulos cubierta |
| 3 | `PDFController` | Raíz | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | **Riesgo Crítico**: Generación de PDFs de producción de urdido/engomado sin tests |
| 4 | `UsuarioController` | Raíz | **Probado** | `Feature/UsuarioDestroyRedirectTest`, `Feature/UsuarioDuplicarTest` | CRUD de usuarios cubierto |
| 5 | `ExternalRedboothController` | `Api/V1` | **Probado** | `Feature/ExternalRedboothApiTest` | Webhooks de API externa cubiertos |
| 6 | `AtaActividadesController` | `Atadores/Catalogos` | **Probado** | `Feature/CatalogosAtadoresTest` | CRUD completo cubierto |
| 7 | `AtaComentariosController` | `Atadores/Catalogos` | **Probado** | `Feature/CatalogosAtadoresTest`, `Feature/Atadores/CatalogoComentariosLlaveTest` | CRUD cubierto |
| 8 | `AtaMaquinasController` | `Atadores/Catalogos` | **Probado** | `Feature/CatalogosAtadoresTest` | CRUD completo cubierto |
| 9 | `AtaDevolucionesController` | `Atadores/Programa` | **Probado** | `Feature/CalificarAtadoKarlMayerTest` | API de devoluciones cubierta |
| 10 | `AtadoresController` | `Atadores/Programa` | **Probado** | `Feature/ProgramaAtadoresListadoTest`, `Feature/Atadores/AtadoresChecklistConsultasTest` | Tablero de atadores ampliamente cubierto |
| 11 | `ReportesAtadoresController` | `Atadores/Reportes` | **Probado** | `Feature/ReporteAtadoresKmTest` | Reporte Karl Mayer cubierto |
| 12 | `BaseDeDatosController` | `Configuracion` | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | Herramientas de base de datos sin tests |
| 13 | `ConfiguracionController` | `Configuracion` | 🔴 **SIN COBERTURA (0%)** | Solo middleware verificado en `Feature/ConfiguracionRoutePermissionTest` | Importación Excel y caché sin tests de ejecución |
| 14 | `DepartamentosController` | `Configuracion` | 🔴 **SIN COBERTURA (0%)** | Solo middleware verificado en `Feature/ConfiguracionRoutePermissionTest` | CRUD de departamentos sin tests |
| 15 | `MensajesController` | `Configuracion` | 🔴 **SIN COBERTURA (0%)** | Solo middleware verificado; modelo en `Unit/SYSMensajeTest` | Difusión de mensajes sin tests HTTP |
| 16 | `ModuloPermisosController` | `Configuracion` | **Probado** | `Feature/ModuloPermisosTest` | Asignación masiva de permisos cubierta |
| 17 | `SecuenciaFoliosController` | `Configuracion` | 🔴 **SIN COBERTURA (0%)** | Solo middleware verificado; helper en `Unit/Helpers/FoliosCaracterizacionTest` | Configuración de secuencias sin tests HTTP |
| 18 | `CrudoController` | `Crudo` | 🟡 **SUPERFICIAL (0% Exec)** | `Feature/CrudoRouteContractTest` | Solo contrato de ruta verificado; 0 llamadas HTTP |
| 19 | `CrudoAuditController` | `Crudo` | 🟡 **SUPERFICIAL (0% Exec)** | `Unit/Crudo/CrudoAuditServiceTest` | Servicio probado; endpoint HTTP sin tests |
| 20 | `CrudoReporteDiaController` | `Crudo` | 🟡 **SUPERFICIAL (0% Exec)** | `Unit/Crudo/CrudoReporteDiaExportTest` | Export probado; endpoint HTTP sin tests |
| 21 | `EngProduccionFormulacionController` | `Engomado/Formulas` | **Probado** | `Unit/EngProduccionFormulacionControllerTest`, `Feature/UrdEng/CapturaFormulaTest` | Captura de fórmulas cubierta |
| 22 | `CatUbicacionesController` | `Engomado/Config` | **Probado** | `Feature/UrdEng/CatalogoUbicacionesNucleosTest` | Ubicación de núcleos cubierta |
| 23 | `CalificarJuliosController` | `Engomado/Prod` | **Probado** | `Feature/UrdEng/CalificarJuliosTest` | Calificación de julios cubierta |
| 24 | `ModuloProduccionEngomadoController` | `Engomado/Prod` | **Probado** | `Unit/ModuloProduccionEngomadoControllerTest`, `Feature/UrdEng/ProduccionEngomadoTest` | Captura de producción cubierta |
| 25 | `EditarOrdenesEngomadoController` | `Engomado/Prog` | **Probado** | `Feature/EngomadoEdicionOrdenesTest` | Edición de orden cubierta |
| 26 | `ProgramarEngomadoController` | `Engomado/Prog` | **Probado** | `Unit/ProgramarEngomadoActualizarStatusAxTest`, `Feature/ProgramBoardRouteContractTest` | Tablero de engomado cubierto |
| 27 | `ReportesEngomadoController` | `Engomado/Reportes` | **Probado** | `Feature/EngomadoControlMermaReportTest`, `Unit/ControlMermaReportServiceTest` | Reportes de merma cubiertos |
| 28 | `RedboothController` | `Integraciones` | **Probado** | `Feature/RedboothIntegrationTest` | Integración OAuth cubierta |
| 29 | `TelemetriaController` | `Monitoreo` | **Probado** | `Feature/Monitoreo/TelemetriaTest`, `Feature/Monitoreo/PanelRendimientoErroresTest` | Telemetría cubierta |
| 30 | `CatalogosFallasController` | `Mantenimiento` | **Probado** | `Feature/CatalogoFallasLivewireTest` | Wrapper de componente Livewire probado |
| 31 | `ManOperadoresMantenimientoController` | `Mantenimiento` | **Probado** | `Feature/Mantenimiento/CatalogoOperadoresLivewireTest` | Wrapper de componente Livewire probado |
| 32 | `ParosCatalogoController` | `Mantenimiento` | **Probado** | `Feature/Mantenimiento/MantenimientoParosApiTest` | Catálogos y filtros cubiertos |
| 33 | `ParosCierreController` | `Mantenimiento` | **Probado** | `Feature/Mantenimiento/MantenimientoParosApiTest` | Cierre de paros cubierto |
| 34 | `ParosController` | `Mantenimiento` | **Probado** | `Feature/Mantenimiento/MantenimientoParosApiTest`, `Unit/MantenimientoParosControllerTest` | Alta de paros cubierta |
| 35 | `ReportesMantenimientoController` | `Mantenimiento` | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | Reportes de paros sin tests |
| 36 | `AlineacionController` | `Planeacion/Alinea` | **Probado** | `Unit/AlineacionControllerTest`, `Unit/AlineacionExportTest` | Alineación ampliamente cubierta |
| 37 | `AuditoriaProgramaTejidoController` | `Planeacion/Audit` | **Probado** | `Feature/AuditoriaProgramaTejidoPantallaTest` | Auditoría de tejido cubierta |
| 38 | `CatCodificacionController` | `Planeacion/Codif` | **Probado** | `Feature/CodificacionFormularioTest`, `Unit/SaldoMarbeteCodificacionServiceTest` | Modales y fórmulas cubiertos |
| 39 | `CatLMatController` | `Planeacion/LMat` | **Probado** | `Feature/CatLMatGuardarPorcentajeTest` | Porcentajes y guardado cubiertos |
| 40 | `EstandarCatalogoController` | `Planeacion/Cat` | **Probado** | `Feature/CatalogosPlaneacion/EstandarCatalogoTest` | Catálogos base cubiertos |
| 41 | `AplicacionesController` | `Planeacion/Cat` | **Probado** | `Feature/CatalogosPlaneacion/CatalogosSimplesTest` | CRUD completo cubierto |
| 42 | `CalendarioController` | `Planeacion/Cat` | **Probado** | `Feature/CatalogosPlaneacion/CalendariosTest` | Calendarios y turnos cubiertos |
| 43 | `CatalagoEficienciaController` | `Planeacion/Cat` | **Probado** | `Feature/CatalogosPlaneacion/EstandarCatalogoTest` | Eficiencias estándar cubiertas |
| 44 | `MatrizCalibresController` | `Planeacion/Cat` | **Probado** | `Unit/MatrizCalibresServiceTest`, `Feature/CatalogosPlaneacion/ConsultasCatalogosTest` | Matriz de calibres cubierta |
| 45 | `MatrizHilosController` | `Planeacion/Cat` | **Probado** | `Feature/CatalogosPlaneacion/CatalogosSimplesTest` | Matriz de hilos cubierta |
| 46 | `PesosRollosController` | `Planeacion/Cat` | **Probado** | `Feature/CatalogosPlaneacion/CatalogosSimplesTest` | Pesos de rollos cubiertos |
| 47 | `CatalagoTelarController` | `Planeacion/Cat` | **Probado** | `Feature/CatalogosPlaneacion/CatalogoTelaresTest` | Catálogo de telares cubierto |
| 48 | `CatalagoVelocidadController` | `Planeacion/Cat` | **Probado** | `Feature/CatalogosPlaneacion/EstandarCatalogoTest` | Velocidades cubiertas |
| 49 | `CodificacionController` | `Planeacion/ModCod` | **Probado** | `Feature/CatCodificadosExcelImportTest`, `Unit/Calidad/ReqModelosCodificadosImportTest` | Importación Excel probada |
| 50 | `ColumnasProgramaTejidoController` | `Planeacion/ProgTej` | **Probado** | `Unit/Planeacion/ColumnasGrillaProgramaTejidoTest` | Configuración de grilla cubierta |
| 51 | `DescargarProgramaController` | `Planeacion/ProgTej` | **Probado** | `Feature/Planeacion/ProgramaTejidoCapacidadesMuestrasTest` | Descarga de programa cubierta |
| 52 | `LiberarOrdenesController` | `Planeacion/ProgTej` | **Probado** | `Feature/LiberarOrdenesLiberarTest`, `Feature/LiberarBomYNombreTest` | Flujo crítico ampliamente testeado |
| 53 | `ProgramaTejidoBalanceoController` | `Planeacion/ProgTej` | **Probado** | `Feature/ProgramaTejidoBalanceoIntegrationTest` | Balanceo cubierto |
| 54 | `ProgramaTejidoCalendariosController` | `Planeacion/ProgTej` | **Probado** | `Feature/CatalogosPlaneacion/CalendariosTest` | Calendarios cubiertos |
| 55 | `ProgramaTejidoCatalogosController` | `Planeacion/ProgTej` | **Probado** | `Feature/Planeacion/ProgramaTejidoReadV2Test` | Lectura de catálogos cubierta |
| 56 | `ProgramaTejidoController` | `Planeacion/ProgTej` | **Probado** | `Feature/Planeacion/ProgramaTejidoRouteSurfaceTest` | Shell de programa cubierto |
| 57 | `ProgramaTejidoOperacionesController` | `Planeacion/ProgTej` | **Probado** | `Feature/ProgramaTejidoOperacionesTest`, `Feature/ProgramaTejidoUpdateTest` | Mutaciones del programa cubiertas |
| 58 | `ProgramaTejidoReadController` | `Planeacion/ProgTej` | **Probado** | `Feature/Planeacion/ProgramaTejidoReadV2Test` | Consultas de grilla cubiertas |
| 59 | `RedboothProgramaTejidoController` | `Planeacion/ProgTej` | **Probado** | `Feature/RedboothIntegrationTest` | Tareas Redbooth cubiertas |
| 60 | `ReimprimirOrdenesController` | `Planeacion/ProgTej` | 🔴 **SIN COBERTURA (0%)** | Solo regex de URI en `ProgramaTejidoRouteSurfaceTest` | Reimpresión sin tests de ejecución |
| 61 | `RepasoController` | `Planeacion/ProgTej` | 🔴 **SIN COBERTURA (0%)** | Solo regex de URI en `ProgramaTejidoRouteSurfaceTest` | Alta de repaso sin tests de ejecución |
| 62 | `ReqProgramaTejidoLineController` | `Planeacion/ProgTej` | **Probado** | `Feature/Planeacion/ProgramaTejidoIsolationTest` | Sublíneas de programa cubiertas |
| 63 | `OrdenDeCambioFelpaController` | `Planeacion/OrdCamb` | 🟡 **SUPERFICIAL / FRÁGIL** | `Unit/OrdenDeCambioTipoFormatoTest` (solo 1 método privado por reflexión) | Monolito de 1,942 LoC sin tests HTTP |
| 64 | `FinalizarOrdenesController` | `Planeacion/Utiler` | 🔴 **SIN COBERTURA (0%)** | Solo contrato en `PlaneacionUtileriaRouteContractTest` | **Riesgo Crítico**: Finalización de órdenes sin tests HTTP |
| 65 | `MoverOrdenesController` | `Planeacion/Utiler` | 🟡 **SUPERFICIAL / FRÁGIL** | `Feature/MoverOrdenesFechaFinalizaTest` (método privado por reflexión) | Mover órdenes sin tests HTTP de integración |
| 66 | `TiemposPreparacionController` | `ProductoTerminado` | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | Tiempos de preparación sin tests |
| 67 | `BomMaterialesController` | `ProgramaUrdEng` | **Probado** | `Feature/ProgramaUrdEng/BomMaterialesFormulasQueriesTest` | Fórmulas BOM cubiertas |
| 68 | `CrearOrdenKarlMayerController` | `ProgramaUrdEng` | **Probado** | `Feature/ProgramaUrdEng/KarlMayerCrearOrdenQueriesTest` | Órdenes Karl Mayer cubiertas |
| 69 | `InventarioDisponibleController` | `ProgramaUrdEng` | **Probado** | `Feature/ProgramaUrdEng/InventarioReservasServiceTest` | Inventario disponible cubierto |
| 70 | `InventarioTelaresController` | `ProgramaUrdEng` | **Probado** | `Feature/ProgramaUrdEng/InventarioTelaresServiceTest` | Inventario de telares cubierto |
| 71 | `ProgramarUrdEngController` | `ProgramaUrdEng` | **Probado** | `Unit/ProgramarUrdEngControllerTest`, `Feature/ProgramaUrdEng/ProgramaBoardEndpointsTest` | Tablero de programación cubierto |
| 72 | `ReservaInventarioController` | `ProgramaUrdEng` | **Probado** | `Feature/ReservarConTelarTransaccionTest` | Transacciones de reserva cubiertas |
| 73 | `ReservarProgramarController` | `ProgramaUrdEng` | **Probado** | `Feature/ProgramaUrdEng/ReservarProgramarActionQueriesTest` | Tablero general cubierto |
| 74 | `ResumenSemanasController` | `ProgramaUrdEng` | **Probado** | `Feature/ProgramaUrdEng/ResumenSemanasQueriesTest` | Resumen semanal cubierto |
| 75 | `InventarioTelaresController` | `Tejedores` | **Probado** | `Feature/Tejedores/InventarioTelaresEndpointsTest` | Inventario en piso cubierto |
| 76 | `CatalogoCalibresController` | `Tejedores/Config` | **Probado** | `Feature/CatalogoCalibresLivewireTest` | Wrapper de componente Livewire probado |
| 77 | `TelTelaresOperadorController` | `Tejedores/Config` | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | **Riesgo Crítico**: Asignación de telares a operadores sin tests |
| 78 | `TelDesarrolladoresController` | `Tejedores/Desarr` | **Probado** | `Feature/DesarrolladoresCapturaLivewireTest` | Captura de desarrolladores cubierta |
| 79 | `TelDesarrolladoresMuestrasController` | `Tejedores/Desarr` | **Probado** | `Feature/DesarrolladoresCapturaLivewireTest` | Captura de muestras cubierta |
| 80 | `NotificarMontadoJulioController` | `Tejedores/Notif` | **Probado** | `Feature/AtadoDeJulioReservadoTest` | Montado de julios cubierto |
| 81 | `NotificarMontRollosController` | `Tejedores/Notif` | **Probado** | `Feature/NotificarCortadoRolloOrdenProduccionTest` | Montado de rollos cubierto |
| 82 | `ReportesDesarrolladoresController` | `Tejedores/Reportes` | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | Reportes de desarrolladores sin tests |
| 83 | `ReportesTejedoresController` | `Tejedores/Reportes` | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | Reportes de tejedores sin tests |
| 84 | `SecuenciaCorteEficienciaController` | `Tejido/Config` | **Probado** | `Feature/Tejido/SecuenciasTest` | Secuencias cubiertas |
| 85 | `SecuenciaInvTelasController` | `Tejido/Config` | **Probado** | `Feature/Tejido/SecuenciaInvTelasTest` | Secuencias cubiertas |
| 86 | `SecuenciaInvTramaController` | `Tejido/Config` | **Probado** | `Feature/Tejido/SecuenciasTest` | Secuencias cubiertas |
| 87 | `SecuenciaMarcasFinalesController` | `Tejido/Config` | **Probado** | `Feature/Tejido/SecuenciasTest` | Secuencias cubiertas |
| 88 | `CortesEficienciaController` | `Tejido/Cortes` | **Probado** | `Feature/Tejido/CortesEficienciaTest` | Captura masiva ampliamente probada |
| 89 | `TelaresController` | `Tejido/InvTelas` | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | **Riesgo Crítico**: Telas Jacquard/Itema/KM sin tests |
| 90 | `ConsultarRequerimientoController` | `Tejido/InvTrama` | **Probado** | `Feature/ConsultarRequerimientoLivewireTest` | Wrapper de componente Livewire probado |
| 91 | `NuevoRequerimientoController` | `Tejido/InvTrama` | **Probado** | `Feature/NuevoRequerimientoLivewireTest` | Wrapper de componente Livewire probado |
| 92 | `MarcasController` | `Tejido/Marcas` | **Probado** | `Feature/Tejido/MarcasFinalesTest` | Marcas finales cubiertas |
| 93 | `ProduccionReenconadoCabezuelaController` | `Tejido/Reencon` | **Probado** | `Feature/Tejido/ReenconadoTest` | Reenconado cubierto |
| 94 | `PromedioParosEficienciaController` | `Tejido/Reportes` | **Probado** | `Feature/TejidoPromedioParosEficienciaReportTest` | Reporte y exportación probados |
| 95 | `ReporteInvTelasController` | `Tejido/Reportes` | **Probado** | `Unit/ReporteInvTelasControllerTest` | Reporte de telas cubierto |
| 96 | `ReporteMarcasFinalesController` | `Tejido/Reportes` | **Probado** | `Feature/Tejido/ReporteMarcasFinalesVelocidadTest` | Reporte de marcas cubierto |
| 97 | `ReporteRpmSemanalController` | `Tejido/Reportes` | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | Reporte semanal de RPM sin tests |
| 98 | `SaldosController` | `Tejido/Reportes` | **Probado** | `Feature/Tejido/SaldosQuerySqlServerTest`, `Unit/Saldos2026ExportTest` | Saldos 2026 cubiertos |
| 99 | `TelegramController` | `Telegram` | 🔴 **SIN COBERTURA (0%)** | Servicio en `Unit/Telegram/EnviarMensajeTelegramTest`; HTTP sin tests | Endpoints de Telegram sin tests |
| 100 | `TrazabilidadController` | `Trazabilidad` | 🟡 **SUPERFICIAL (0% Exec)** | Livewire y servicios probados; controller sin HTTP test | Shell de trazabilidad sin tests HTTP |
| 101 | `TrazabilidadDetailController` | `Trazabilidad` | 🟡 **SUPERFICIAL (0% Exec)** | `Feature/TrazabilidadDetailRoutesTest` (solo mapeo de rutas) | Endpoints JSON de detalle sin tests |
| 102 | `UrdEngNucleosController` | `UrdEngomado` | **Probado** | `Feature/UrdEng/CatalogoUbicacionesNucleosTest` | CRUD de núcleos cubierto |
| 103 | `ListaMaterialesController` | `Urdido` | **Probado** | `Feature/Urdido/ListaMaterialesTest` | Reporte BOM cubierto |
| 104 | `ReportesUrdidoController` | `Urdido` | **Probado** | `Feature/Reporte03OeeFechaFinalizaTest`, `Feature/UrdEng/ReportesUrdEngTest` | Reportes OEE cubiertos |
| 105 | `ModuloProduccionUrdidoController` | `Urdido/Config` | **Probado** | `Unit/ModuloProduccionUrdidoControllerTest` | Producción de urdido cubierta |
| 106 | `CatalogosUrdidoController` | `Urdido/Config` | **Probado** | `Feature/Urdido/CatalogoJuliosTest`, `Feature/Urdido/CatalogoMaquinasTest` | Catálogos cubiertos |
| 107 | `EditarOrdenesProgramadasController` | `Urdido/Prog` | **Probado** | `Feature/EditarOrdenPermisosTest` | Edición de orden cubierta |
| 108 | `ProgramarUrdidoController` | `Urdido/Prog` | **Probado** | `Unit/ProgramarUrdidoActualizarStatusAxTest` | Tablero de urdido cubierto |
| 109 | `VentasDatosController` | `Ventas` | **Probado** | `Feature/VentasComparaPorAnioTest` | Comparación ventas/plan cubierta |
| 110 | `TwHistPedidosController` | `Ventas` | 🔴 **HUÉRFANO / 0% TESTS** | *Ninguna* | Código muerto sin rutas |
| 111 | `TwHistPronosController` | `Ventas` | 🔴 **HUÉRFANO / 0% TESTS** | *Ninguna* | Código muerto sin rutas |
| 112 | `TwHistVtasController` | `Ventas` | 🔴 **HUÉRFANO / 0% TESTS** | *Ninguna* | Código muerto sin rutas |
| 113 | `MecReportesController` | `mecanicos` | 🔴 **SIN COBERTURA (0%)** | Servicios probados; controller HTTP sin tests | Reportes mecánicos sin tests HTTP |
| 114 | `MecVerificaMaquinaController` | `mecanicos` | **Probado** | `Feature/VerificaMaquinaIndexPaginacionTest` | Paginación cubierta |
| 115 | `MecActividadesController` | `mecanicos/Cat` | 🔴 **SIN COBERTURA (0%)** | *Ninguna* | Catálogo de actividades mecánicas sin tests |
| 116 | `ActualizarOrdenTrabajoController` | `mecanicos/OT` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Actualización OT cubierta |
| 117 | `AutorizarOrdenTrabajoController` | `mecanicos/OT` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Autorización supervisor cubierta |
| 118 | `ConsultaOrdenesTrabajoController` | `mecanicos/OT` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Consultas OT cubiertas |
| 119 | `CrearOrdenTrabajoController` | `mecanicos/OT` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Creación OT cubierta |
| 120 | `EliminarOrdenTrabajoController` | `mecanicos/OT` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Eliminación OT cubierta |
| 121 | `FinalizarOrdenTrabajoController` | `mecanicos/OT` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Finalización OT cubierta |
| 122 | `VistaOrdenesTrabajoController` | `mecanicos/OT` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Shell OT cubierto |
| 123 | `ActualizarLineaController` | `mecanicos/OT/Line` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Líneas OT cubiertas |
| 124 | `CrearLineaController` | `mecanicos/OT/Line` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Creación de línea OT cubierta |
| 125 | `EliminarLineaController` | `mecanicos/OT/Line` | **Probado** | `Feature/Mecanicos/OrdenesTrabajoHttpTest` | Eliminación de línea OT cubierta |

*Nota sobre Delegados de Dominio (`funciones/`):* `BalancearTejido`, `DragAndDropTejido`, `EliminarTejido`, `UpdateTejido` y `VincularTejido` cuentan con pruebas en `tests/Unit/BalancearTejidoTest.php` y `tests/Feature/Planeacion/ProgramaTejidoMoverVincularTest.php`.

---

### 4.3 Tabla de Correlación de los 103 Modelos Eloquent (85 Probados, 18 sin Cobertura)

| Namespace | Modelo Eloquent | Estado | Pruebas Correlacionadas en `tests/` | Observaciones |
|---|---|---|---|---|
| **Atadores** (9) | `AtaActividadesModel` | 🔴 **Sin tests** | *Ninguna* | Accedido mediante consultas directas `DB::table()` |
| | `AtaComentariosModel` | **Probado** | `Feature/Atadores/CatalogoComentariosLlaveTest` | Clave primaria y persistencia probadas |
| | `AtaDevolucionesModel` | 🔴 **Sin tests** | *Ninguna* | Tabla probada vía DB; modelo sin tests directos |
| | `AtaKmEnhebradoModel` | 🔴 **Sin tests** | *Ninguna* | Modelo no instanciado en tests |
| | `AtaKmMontadoModel` | 🔴 **Sin tests** | *Ninguna* | Modelo no instanciado en tests |
| | `AtaMaquinasModel` | 🔴 **Sin tests** | *Ninguna* | Modelo no instanciado en tests |
| | `AtaMontadoActividadesModel` | 🔴 **Sin tests** | *Ninguna* | Modelo no instanciado en tests |
| | `AtaMontadoMaquinasModel` | 🔴 **Sin tests** | *Ninguna* | Modelo no instanciado en tests |
| | `AtaMontadoTelasModel` | **Probado** | `Unit/ConsultasDesarrolladorServiceTest` | Montado de telas cubierto |
| **Costos** (3) | `CosCuota` | **Probado** | `Feature/CostosCuotasLivewireTest` | Modelo base probado vía subclases |
| | `CosCuotasReal` | **Probado** | `Feature/CostosCuotasLivewireTest` | Cuotas reales cubiertas |
| | `CosCuotasSTD` | **Probado** | `Feature/CostosCuotasLivewireTest` | Cuotas estándar cubiertas |
| **Crudo** (1) | `CrudoAuditoria` | **Probado** | `Unit/Crudo/CrudoAuditServiceTest` | Auditoría de crudo cubierta |
| **Engomado** (10) | `CatDefectosUrdEng` | **Probado** | `Feature/Reporte03OeeFechaFinalizaTest` | Catálogo de defectos cubierto |
| | `CatUbicaciones` | **Probado** | `Feature/UrdEng/CatalogoUbicacionesNucleosTest` | Ubicación de núcleos cubierta |
| | `EngActividadesBpmModel` | **Probado** | `Feature/Bpm/BpmTest` | BPM cubierto |
| | `EngAnchoBalonaCuenta` | 🔴 **Sin tests** | *Ninguna* | Modelo auxiliar sin tests |
| | `EngBpmLineModel` | **Probado** | `Feature/Bpm/BpmTest` | BPM cubierto |
| | `EngBpmModel` | **Probado** | `Feature/Bpm/BpmTest` | BPM cubierto |
| | `EngFormulacionLineModel` | **Probado** | `Unit/EngProduccionFormulacionControllerTest` | Líneas de formulación cubiertas |
| | `EngProduccionEngomado` | **Probado** | `Unit/ModuloProduccionEngomadoControllerTest` | Producción de engomado cubierta |
| | `EngProduccionFormulacionModel` | **Probado** | `Unit/EngProduccionFormulacionControllerTest` | Encabezados de formulación cubiertos |
| | `EngProgramaEngomado` | **Probado** | `Unit/ProgramarEngomadoActualizarStatusAxTest` | Programa de engomado cubierto |
| **Integraciones** (1) | `RedboothCredential` | **Probado** | `Feature/ExternalRedboothApiTest` | Credenciales de integración cubiertas |
| **Inventario** (5) | `InvKardexMPConsol` | 🔴 **Sin tests** | *Ninguna* | Modelo de kardex de materia prima sin tests |
| | `InvSecuenciaCorteEf` | **Probado** | `Feature/Tejido/SecuenciasTest` | Secuencias de corte cubiertas |
| | `InvSecuenciaMarcas` | **Probado** | `Feature/Tejido/SecuenciasTest` | Secuencias de marcas cubiertas |
| | `InvSecuenciaTrama` | **Probado** | `Feature/Tejido/SecuenciasTest` | Secuencias de trama cubiertas |
| | `InvTelasReservadas` | **Probado** | `Feature/ReservarConTelarTransaccionTest` | Reservas de telares cubiertas |
| **Mantenimiento** (4) | `CatParosFallas` | **Probado** | `Feature/CatalogoFallasLivewireTest` | Catálogo de fallas cubierto |
| | `CatTipoFalla` | **Probado** | `Feature/CatalogoFallasLivewireTest` | Tipos de falla cubiertos |
| | `ManFallasParos` | **Probado** | `Feature/Mantenimiento/MantenimientoParosApiTest` | Paros de mantenimiento cubiertos |
| | `ManOperadoresMantenimiento` | **Probado** | `Feature/Mantenimiento/CatalogoOperadoresLivewireTest` | Operadores de mantenimiento cubiertos |
| **Mecanicos** (5) | `MecActividadesModel` | 🔴 **Sin tests** | *Ninguna* | Modelo de actividades mecánicas sin tests |
| | `MecOrdenTrabajoLineModel` | **Probado** | `Unit/Mecanicos/OrdenesTrabajoTest` | Líneas de OT mecánicas cubiertas |
| | `MecOrdenTrabajoModel` | **Probado** | `Unit/Mecanicos/OrdenesTrabajoTest` | Encabezados de OT mecánicas cubiertos |
| | `MecVerificaMaquinaLineModel` | 🔴 **Sin tests** | *Ninguna* | Verificación de máquinas sin tests |
| | `MecVerificaMaquinaModel` | 🔴 **Sin tests** | *Ninguna* | Verificación de máquinas sin tests |
| **Planeacion** (11) | `Muestras` | **Probado** | `Unit/ProcesarMuestrasStoreTest` | Órdenes de muestra cubiertas |
| | `MuestrasLine` | 🔴 **Sin tests** | *Ninguna* | Líneas de muestra sin tests |
| | `OrdColProgramaTejido` | 🔴 **Sin tests** | *Ninguna* | Modelo de columnas compartidas sin tests |
| | `ReqAplicaciones` | **Probado** | `Feature/CatalogosPlaneacion/CatalogosSimplesTest` | Aplicaciones cubiertas |
| | `ReqCalendarioLine` | **Probado** | `Feature/CatalogosPlaneacion/CalendariosTest` | Líneas de calendario cubiertas |
| | `ReqCalendarioTab` | **Probado** | `Feature/CatalogosPlaneacion/CalendariosTest` | Encabezados de calendario cubiertos |
| | `ReqEficienciaStd` | **Probado** | `Feature/CatalogosPlaneacion/EstandarCatalogoTest` | Eficiencias estándar cubiertas |
| | `ReqMatrizHilos` | **Probado** | `Feature/CatalogosPlaneacion/CatalogosSimplesTest` | Matriz de hilos cubierta |
| | `ReqModelosCodificados` | **Probado** | `Feature/CatCodificadosExcelImportTest` | Modelos codificados cubiertos |
| | `ReqProgramaTejido` | **Probado** | Probado en más de 80 archivos de prueba | Columna vertebral de planeación cubierta |
| | `ReqProgramaTejidoLine` | **Probado** | `Feature/Planeacion/ProgramaTejidoIsolationTest` | Sublíneas cubiertas |
| | `ReqVelocidadStd` | **Probado** | `Feature/CatalogosPlaneacion/EstandarCatalogoTest` | Velocidades cubiertas |
| | `Requerimiento` | 🔴 **Sin tests** | *Ninguna* | **Modelo huérfano** (0 referencias) |
| **Planeacion/Cat** (5) | `CatCodificados` | **Probado** | Probado en más de 50 archivos de prueba | Fichas técnicas de tejido cubiertas |
| | `CatLMat` | **Probado** | `Feature/CatLMatGuardarPorcentajeTest` | BOM de tejido cubierto |
| | `CatMatrizCalibres` | **Probado** | `Unit/MatrizCalibresServiceTest` | Matriz de calibres cubierta |
| | `Modelos` | 🔴 **Sin tests** | *Ninguna* | **Modelo huérfano** (0 referencias) |
| | `ReqPesosRollosTejido` | **Probado** | `Feature/CatalogosPlaneacion/CatalogosSimplesTest` | Pesos de rollos cubiertos |
| **Sistema** (9) | `SSYSFoliosSecuencia` | **Probado** | `Unit/Helpers/FoliosCaracterizacionTest` | Secuencias de folios cubiertas |
| | `SYSMensaje` | **Probado** | `Unit/SYSMensajeTest` | Mensajería del sistema cubierta |
| | `SYSRoles` | **Probado** | `Feature/ModuloPermisosTest` | Módulos y roles cubiertos |
| | `SYSUsuario` | **Probado** | Probado en más de 50 archivos de prueba | Identidad de usuario cubierta |
| | `SYSUsuariosRoles` | **Probado** | `Feature/ModuloPermisosTest` | Permisos cubiertos |
| | `SysDepartamento` | **Probado** | `Feature/Mantenimiento/MantenimientoParosApiTest` | Modelo de departamento canónico cubierto |
| | `SysDepartamentos` | **Probado** | `Feature/Mantenimiento/MantenimientoParosApiTest` | Modelo redundante cubierto |
| | `User` | **Probado** | Probado en mocks de tests | Modelo duplicado de autenticación |
| | `Usuario` | **Probado** | Probado en más de 70 archivos de prueba | Modelo auténtico de login cubierto |
| **Monitoreo** (6) | `MonAcceso` | **Probado** | `Feature/Monitoreo/AccesosTest` | Auditoría de accesos cubierta |
| | `MonDispositivo` | **Probado** | `Feature/Monitoreo/IdentificarDispositivoTest` | Rastreo de dispositivos cubierto |
| | `MonError` | **Probado** | `Feature/Monitoreo/ErroresTest` | Registro de excepciones cubierto |
| | `MonErrorEvento` | **Probado** | `Feature/Monitoreo/ErroresTest` | Eventos de error cubiertos |
| | `MonSesion` | **Probado** | `Feature/Monitoreo/CierreRemotoTest` | Sesiones remotas cubiertas |
| | `MonVista` | **Probado** | `Feature/Monitoreo/TelemetriaTest` | Telemetría de vistas cubierta |
| **Tejedores** (7) | `TejCatMatrizDesarrolladores` | **Probado** | `Unit/ProcesarDesarrolladorStoreTest` | Matriz de desarrollo cubierta |
| | `TejNotificaTejedorModel` | 🔴 **Sin tests** | *Ninguna* | Bitácora de notificaciones sin tests |
| | `TelActividadesBPM` | **Probado** | `Feature/Bpm/BpmTest` | BPM tejedores cubierto |
| | `TelBpmLineModel` | **Probado** | `Feature/Bpm/BpmTest` | BPM tejedores cubierto |
| | `TelBpmModel` | **Probado** | `Feature/Bpm/BpmTest` | BPM tejedores cubierto |
| | `TelMarbeteLiberadoModel` | 🔴 **Sin tests** | *Ninguna* | Marbetes liberados sin tests |
| | `TelTelaresOperador` | **Probado** | `Feature/Bpm/BpmTest` | Asignación tejedores-telares cubierta |
| **Tejido** (10) | `TejEficiencia` | **Probado** | `Feature/Tejido/CortesEficienciaTest` | Encabezados de eficiencia cubiertos |
| | `TejEficienciaLine` | **Probado** | `Feature/Tejido/CortesEficienciaTest` | Muestras de eficiencia cubiertas |
| | `TejInventarioTelares` | **Probado** | `Unit/ReporteInvTelasControllerTest` | Inventario en telares cubierto |
| | `TejMarcas` | **Probado** | `Feature/Tejido/MarcasFinalesTest` | Marcas finales cubiertas |
| | `TejMarcasLine` | **Probado** | `Feature/Tejido/MarcasFinalesTest` | Líneas de inspección cubiertas |
| | `TejProduccionReenconado` | **Probado** | `Feature/Tejido/ReenconadoTest` | Reenconado cubierto |
| | `TejTrama` | **Probado** | `Feature/NuevoRequerimientoLivewireTest` | Requerimientos de trama cubiertos |
| | `TejTramaConsumos` | **Probado** | `Feature/NuevoRequerimientoLivewireTest` | Consumo de trama cubierto |
| | `TejeFallasCeModel` | **Probado** | `Feature/Tejido/CortesEficienciaTest` | Códigos de paros cubiertos |
| **Trazabilidad** (1) | `TrazaProduccion` | **Probado** | `Feature/RedboothIntegrationTest` | Trazabilidad de producción cubierta |
| **UrdEngomado** (1) | `UrdEngNucleos` | **Probado** | `Feature/UrdEng/CatalogoUbicacionesNucleosTest` | Núcleos de urdido/engomado cubiertos |
| **Urdido** (11) | `AuditoriaUrdEng` | **Probado** | `Feature/ProgramaUrdEng/Concerns/ModuloProgramaUrdEng` | Auditoría cubierta |
| | `URDCatalogoMaquina` | **Probado** | `Feature/Urdido/CatalogoMaquinasTest` | Máquinas de urdido cubiertas |
| | `UrdActividadesBpmModel` | **Probado** | `Feature/Bpm/BpmTest` | BPM urdido cubierto |
| | `UrdBpmLineModel` | **Probado** | `Feature/Bpm/BpmTest` | BPM urdido cubierto |
| | `UrdBpmModel` | **Probado** | `Feature/Bpm/BpmTest` | BPM urdido cubierto |
| | `UrdCatJulios` | **Probado** | `Feature/Urdido/CatalogoJuliosTest` | Catálogo de julios cubierto |
| | `UrdConsumoHilo` | **Probado** | `Feature/ProgramaUrdEng/Concerns/ModuloProgramaUrdEng` | Consumo de hilo cubierto |
| | `UrdJuliosOrden` | **Probado** | `Feature/ProgramaUrdEng/Concerns/ModuloProgramaUrdEng` | Julios de orden cubiertos |
| | `UrdProduccionUrdido` | **Probado** | `Unit/ProduccionUrdidoCicloJuliosTest` | Producción de urdido cubierta |
| | `UrdProgramaUrdido` | **Probado** | Probado en más de 30 archivos de prueba | Programa de urdido cubierto |
| | `Urdbom` | **Probado** | `Feature/Urdido/ListaMaterialesTest` | Fórmulas de urdido cubiertas |
| **Ventas** (3) | `TwHistPedidosModel` | **Probado** | `Feature/VentasComparaPorAnioTest` | Pedidos históricos cubiertos vía VentasDatos |
| | `TwHistPronosModel` | **Probado** | `Feature/VentasComparaPorAnioTest` | Pronósticos históricos cubiertos vía VentasDatos |
| | `TwHistVtasModel` | **Probado** | `Feature/VentasComparaPorAnioTest` | Ventas históricas cubiertas vía VentasDatos |

---

### 4.4 Análisis de Controladores Críticos de Alto Riesgo sin Pruebas Automatizadas

1. **`PDFController` (16.5 KB / 391 LoC):** Controla la emisión de los boletos de ruta e instrucciones viajeras de planta para las áreas de urdido y engomado. Un error tipográfico en Blade o un fallo en el motor DomPDF detendría la expedición física en planta sin ser detectado por CI/CD.
2. **`TelaresController` (Tejido, 23.2 KB / 620 LoC):** Orquesta los inventarios de rollos en telares Jacquard, Itema, Smit y Karl Mayer. Contiene consultas complejas y transformaciones manuales que carecen de cualquier red de seguridad en tests.
3. **`FinalizarOrdenesController` (17.6 KB / 416 LoC):** Aplica el cierre administrativo y técnico de las órdenes de fabricación, sellando la fecha de término (`FechaFinaliza`) y sincronizando el estado con AX. Solo posee un test de contrato de ruta que verifica la URL pero no ejecuta la acción.
4. **`TelTelaresOperadorController` (13.6 KB / 346 LoC):** Configura la matriz de asignación de turnos y máquinas asignadas por número de empleado tejedores. Cero pruebas automatizadas.
5. **`ConfiguracionController` (15.5 KB / 381 LoC):** Modifica configuraciones globales y ejecuta vaciados masivos de caché (`Artisan::call('cache:clear')`) e importaciones masivas con `set_time_limit(600)` sin pruebas de integración.
6. **`MecReportesController` (15.5 KB / 450 LoC):** Consolida reportes de averías y mantenimiento mecánico con generación binaria de Dompdf y PhpSpreadsheet completamente ciegos a la suite de pruebas.

---

### 4.5 Identificación de Pruebas Superficiales o Frágiles

Se identificaron dos patrones de pruebas que proporcionan una **falsa sensación de cobertura**:

#### Patrón 1: Pruebas por Reflexión sobre Métodos Privados
- **`OrdenDeCambioFelpaController`:** Es uno de los controladores más voluminosos del sistema (1,942 LoC). Su único test correlacionado es `Unit/OrdenDeCambioTipoFormatoTest.php` (64 líneas), el cual utiliza reflexión PHP (`ReflectionMethod`) exclusivamente para invocar el método privado `determinarTipoFormatoDesdeBD`. Las restantes 1,880 líneas de generación de hojas de cálculo, escritura en base de datos y respuesta HTTP carecen de pruebas de integración.

#### Patrón 2: Pruebas de Contrato de Ruta sin Ejecución HTTP
Pruebas que únicamente verifican la existencia del nombre de la ruta o sus middlewares en el Router de Laravel, sin enviar solicitudes HTTP (`$this->get()`, `$this->postJson()`), por lo que un error 500 fatal o una excepción no capturada dentro del controlador seguiría pasando el test en verde:
- `Feature/CrudoRouteContractTest.php` (25 líneas): Solo valida `Route::getRoutes()->getByName('crudo.index')`.
- `Feature/PlaneacionUtileriaRouteContractTest.php` (54 líneas): Solo valida que existan las rutas `finalizar.procesar` y `mover.procesar`.
- `Feature/TrazabilidadDetailRoutesTest.php` (34 líneas): Solo afirma el registro de rutas sin invocar los endpoints de matriz, producción o flogs.
- `Feature/EngomadoReportesRouteContractTest.php` (47 líneas): Solo verifica la definición de `control-merma.excel`.

---

## 5. R4. Plan de Saneamiento Priorizado y Accionable (Actionable Remediation Plan)

Se define una hoja de ruta estructurada en tres fases continuas, dimensionadas por criticidad de negocio y riesgo operativo, asegurando la no regresión de las operaciones de planta.

### 5.1 Fase P1: Inmediato (Semanas 1 y 2) — Blindaje Operativo y Limpieza de Residuos

**Objetivo:** Eliminar código muerto inofensivo, retirar archivos binarios de la capa HTTP y establecer redes de seguridad mínimas (Characterization Tests) sobre los flujos más peligrosos antes de refactorizar.

| # | Acción Específica | Componentes Afectados | Esfuerzo Estimado | Criterios de Aceptación Verificables |
|---|---|---|---|---|
| **P1.1** | **Eliminar Controladores Huérfanos** | `TwHistPedidosController.php`, `TwHistPronosController.php`, `TwHistVtasController.php` | 2 horas | Archivos eliminados del disco; `composer dump-autoload` limpio; cero regresiones en rutas de ventas. |
| **P1.2** | **Reubicar Plantilla Binaria** | `app/Http/Controllers/.../ordfelpa.xlsx` -> `storage/templates/excel/ordfelpa.xlsx` | 1 hora | Directorio de controladores libre de archivos binarios; ruta actualizada en `OrdenDeCambioFelpaController`. |
| **P1.3** | **Eliminar Stubs Dummy de Recurso** | `UrdEngNucleosController.php`, `routes/modules/engomado.php` | 1 hora | Métodos `create()` y `edit()` eliminados; ruta declarada con `->except(['create', 'edit'])`. |
| **P1.4** | **Tests de Caracterización para PDFController** | `tests/Feature/UrdEng/PDFProduccionTest.php` (Nuevo) | 6 horas | Test genera PDF de urdido y engomado; assert status 200, Content-Type `application/pdf` y cabecera `%PDF-`. |
| **P1.5** | **Tests de Caracterización para FinalizarOrdenes** | `tests/Feature/Planeacion/FinalizarOrdenesHttpTest.php` (Nuevo) | 6 horas | Test ejecuta `$this->postJson()` para finalizar orden; valida validación de producción > 0 y timestamp `FechaFinaliza`. |
| **P1.6** | **Tests de Caracterización para TelaresOperador** | `tests/Feature/Tejedores/TelTelaresOperadorTest.php` (Nuevo) | 6 horas | Test cubre el guardado masivo de asignación operador-telar y validación de turnos. |
| **P1.7** | **Eliminar Modelos Huérfanos Inactivos** | `Modelos.php`, `Requerimiento.php` | 2 horas | Modelos eliminados; verificación de cero dependencias en código de producción. |

---

### 5.2 Fase P2: Mediano Plazo (Semanas 3 a 6) — Desacoplamiento Arquitectónico del Core

**Objetivo:** Ejecutar las recetas de extracción arquitectónica sobre los controladores con mayor sobrecarga (Top 8 Bloated), introduciendo Services, Repositories y Form Requests tipados.

| # | Acción Específica | Componentes Afectados | Esfuerzo Estimado | Criterios de Aceptación Verificables |
|---|---|---|---|---|
| **P2.1** | **Extracción de OrdenDeCambioFelpa** | `OrdenDeCambioFelpaController`, `OrdenDeCambioSpreadsheetGenerator` | 16 horas | Controlador reducido a <50 LoC; generación de Excel encapsulada en servicio dedicado; tests de integración en verde. |
| **P2.2** | **Reubicación de Motores en `funciones/`** | `BalancearTejido`, `EliminarTejido`, `VincularTejido`, `DragAndDropTejido` | 20 horas | Clases migradas a `App\Services\Planeacion\ProgramaTejido\`; eliminación de la carpeta `funciones/`; adaptadores obsoletos retirados. |
| **P2.3** | **Extracción de Acción en LiberarOrdenes** | `LiberarOrdenesController`, `LiberarOrdenAction`, `LiberarOrdenRequest` | 14 horas | Método `liberar()` reducido de 506 a <20 LoC; reglas de validación en Form Request; tests unitarios y de integración pasando. |
| **P2.4** | **Desacoplamiento de CortesEficiencia** | `CortesEficienciaController`, `CorteEficienciaBatchWriter`, `CorteEficienciaDocumentService` | 18 horas | Transacciones e inserciones masivas aisladas en servicio; generación Dompdf/Excel desacoplada; Form Requests creados. |
| **P2.5** | **Modularización de ReportesUrdido** | `ReportesUrdidoController`, 5 nuevos controllers, 3 servicios analíticos | 16 horas | Archivo de 1,500 LoC reemplazado por 5 controladores delgados (<50 LoC) y servicios de cálculo aislados. |
| **P2.6** | **Unificación de Modelos Codificados** | `CodificacionController`, `CatCodificacionController`, `ModeloSimilarScoringService` | 20 horas | Lógica de similitud y lectura de Excel compartida en servicios; eliminación de código redundante entre ambos controllers. |
| **P2.7** | **Encapsulamiento de Consultas AX (`sqlsrv_ti`)** | 21 controladores que acceden a `sqlsrv_ti`, Repositorios de Integración | 18 horas | Consultas a `WMSLocation`, `InventTable`, `ConfigTable` y `BOMTABLE` encapsuladas en repositorios con manejo de excepciones y timeouts. |
| **P2.8** | **Actualización de Tests Superficiales** | Tests de contrato en Crudo, Trazabilidad, Engomado | 12 horas | Contratos convertidos a pruebas reales de ejecución HTTP validando payload JSON y respuestas de vista. |

---

### 5.3 Fase P3: Mejora Continua (Semanas 7 a 12) — Madurez, Cobertura Integral y Tipado Estricto

**Objetivo:** Consolidar modelos duplicados, adoptar scopes de dominio en toda la aplicación, extender la cobertura de pruebas al 90%+ y aplicar estándares estrictos de análisis estático.

| # | Acción Específica | Componentes Afectados | Esfuerzo Estimado | Criterios de Aceptación Verificables |
|---|---|---|---|---|
| **P3.1** | **Consolidación de Modelos Duplicados** | `SysDepartamentos` vs `SysDepartamento` (ambos en `App\Models\Sistema\`), `User` vs `Usuario` | 10 horas | `UsuarioController` migrado al modelo canónico `SysDepartamento`; suite de tests migrada a `Usuario`; eliminación de clases duplicadas. |
| **P3.2** | **Adopción Sistemática de Scopes de Dominio** | 13 Scopes locales no invocados, controladores del sistema | 16 horas | Reemplazo de cláusulas SQL manuales repetidas por llamadas a scopes del modelo (`scopeConAcceso`, `scopeModulosPrincipales`, etc.). |
| **P3.3** | **Cobertura de Controladores Restantes** | 15 controladores no testeados de baja y media prioridad | 24 horas | Cobertura global de controladores elevada del 78.4% a >95.0%. |
| **P3.4** | **Generalización de Form Requests** | 73 controladores con `$request->validate()` inline + 6 con `Validator::make()` | 30 horas | Migración progresiva de arrays de validación en controllers hacia clases `FormRequest` tipadas (elevando adopción del 14.4% al 90%+). |
| **P3.5** | **Análisis Estático con PHPStan / Pint** | Base de código completa | 16 horas | Ejecución de `vendor/bin/pint` y configuración de PHPStan en nivel incremental (nivel 5 a 8) en pipeline CI. |

---

### 5.4 Estrategia de Gestión de Riesgo Durante la Refactorización

Para garantizar que el saneamiento no impacte la operación productiva en planta:

1. **Protocolo "Test-First" de Caracterización:** Antes de modificar cualquier línea en controladores críticos (`OrdenDeCambioFelpaController`, `LiberarOrdenesController`, `CortesEficienciaController`), se deben crear pruebas de integración que congelen el comportamiento actual de entrada y salida (Characterization Tests).
2. **Preservación Estricta de Contratos Frontend:** Los endpoints consumidos por componentes JavaScript (`catalog-base.ts`, DataTables, Livewire, SweetAlert2) deben mantener exactamente la misma estructura de respuestas JSON, nombres de claves y códigos de estado HTTP.
3. **Migración en Pasos Atómicos:** Ningún refactor debe combinar cambios estructurales con nuevas características funcionales ("no while I'm here refactoring").
4. **Verificación de Conexiones Duales:** Validar exhaustivamente las pruebas que interactúan con emulación de `sqlsrv` y `sqlsrv_ti` utilizando el trait `UsesSqlsrvSqlite` para evitar falsos positivos o bloqueos del interceptor de seguridad.

---

## 6. Métodos de Verificación y Auditoría Independiente

Todos los hallazgos, conteos métricos y referencias incluidos en este informe son deterministas y pueden ser verificados de manera independiente mediante los siguientes procedimientos en PowerShell:

1. **Verificación de Elementos Físicos Totales en Controladores (142 elementos: 141 PHP + 1 XLSX):**
   ```powershell
   # Total de elementos físicos en app/Http/Controllers:
   (Get-ChildItem -Path "c:\xampp\htdocs\Towell\app\Http\Controllers" -Recurse -File).Count
   # Retorna: 142 elementos físicos

   # Desglose por extensión (141 archivos .php + 1 plantilla .xlsx):
   Get-ChildItem -Path "c:\xampp\htdocs\Towell\app\Http\Controllers" -Recurse -File | Group-Object Extension | Select-Object Count, Name
   # Retorna:
   # Count Name
   # ----- ----
   #   141 .php
   #     1 .xlsx
   ```

2. **Verificación de Archivo Binario en Controllers (`ordfelpa.xlsx`):**
   ```powershell
   Get-ChildItem -Path "c:\xampp\htdocs\Towell\app\Http\Controllers" -Filter "*.xlsx" -Recurse | Select-Object FullName
   # Retorna: app\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\ordfelpa.xlsx
   ```

3. **Verificación de Controladores Huérfanos (0 Rutas en `routes/`):**
   ```powershell
   Select-String -Path "routes\*.php", "routes\modules\*.php" -Pattern "TwHistPedidosController|TwHistPronosController|TwHistVtasController"
   # Retorna: 0 coincidencias
   ```

4. **Verificación de Clases FormRequest y Mecanismos de Validación en Controladores:**
   ```powershell
   # Total de clases FormRequest en app/Http/Requests (40 clases):
   (Get-ChildItem -Path "c:\xampp\htdocs\Towell\app\Http\Requests" -Filter "*.php" -Recurse).Count
   # Retorna: 40 clases FormRequest

   # Controladores que consumen Form Requests mediante namespace o type-hint (18 controladores, ~14.4% de 125):
   (Get-ChildItem -Path "c:\xampp\htdocs\Towell\app\Http\Controllers" -Filter "*.php" -Recurse | Where-Object {
       Select-String -Path $_.FullName -Pattern "App\\Http\\Requests\\" -Quiet
   }).Count
   # Retorna: 18 controladores de aplicación

   # Controladores que dependen de validación inline $request->validate() (73 controladores, 58.4%):
   (Get-ChildItem -Path "c:\xampp\htdocs\Towell\app\Http\Controllers" -Filter "*.php" -Recurse | Where-Object {
       (Select-String -Path $_.FullName -Pattern "\$request->validate\(" -Quiet) -and
       -not (Select-String -Path $_.FullName -Pattern "App\\Http\\Requests\\" -Quiet)
   }).Count
   # Retorna: 73 controladores

   # Controladores con validación manual Validator::make() (6 controladores, 4.8%):
   (Get-ChildItem -Path "c:\xampp\htdocs\Towell\app\Http\Controllers" -Filter "*.php" -Recurse | Where-Object {
       (Select-String -Path $_.FullName -Pattern "Validator::make\(" -Quiet) -and
       -not (Select-String -Path $_.FullName -Pattern "\$request->validate\(|App\\Http\\Requests\\" -Quiet)
   }).Count
   # Retorna: 6 controladores

   # Controladores sin validación formal / input crudo (28 controladores, 22.4%):
   # Total de controladores de aplicación sin Form Requests: 107 controladores (~85.6%)
   ```

5. **Verificación de Volumen de Código en Controladores Críticos (27,679 LoC):**
   ```powershell
   # Lista de los 39 controladores críticos clasificados en la Sección 3.2:
   $criticos = @(
       "Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController.php",
       "Planeacion\CatalogoPlaneacion\ModelosCodificados\CodificacionController.php",
       "Tejido\CortesEficiencia\CortesEficienciaController.php",
       "Urdido\ReportesUrdidoController.php",
       "Planeacion\ProgramaTejido\funciones\BalancearTejido.php",
       "Atadores\ProgramaAtadores\AtadoresController.php",
       "Planeacion\ProgramaTejido\LiberarOrdenesController.php",
       "Atadores\ProgramaAtadores\AtaDevolucionesController.php",
       "Tejido\MarcasFinales\MarcasController.php",
       "Tejido\Reportes\ReporteInvTelasController.php",
       "Urdido\Configuracion\ModuloProduccionUrdidoController.php",
       "Planeacion\CatCodificados\CatCodificacionController.php",
       "Tejedores\InventarioTelaresController.php",
       "Engomado\CapturaFormulas\EngProduccionFormulacionController.php",
       "Planeacion\CatLMat\CatLMatController.php",
       "Planeacion\ProgramaTejido\funciones\EliminarTejido.php",
       "Engomado\Produccion\ModuloProduccionEngomadoController.php",
       "Tejido\InventarioTelas\TelaresController.php",
       "Planeacion\Utilerias\MoverOrdenesController.php",
       "Atadores\Reportes\ReportesAtadoresController.php",
       "Urdido\ProgramaUrdido\ProgramarUrdidoController.php",
       "Planeacion\ProgramaTejido\ProgramaTejidoOperacionesController.php",
       "ModulosController.php",
       "Tejedores\NotificarMontadoRollo\NotificarMontRollosController.php",
       "Engomado\ProgramaEngomado\ProgramarEngomadoController.php",
       "Planeacion\ProgramaTejido\DescargarProgramaController.php",
       "Tejido\ProduccionReenconado\ProduccionReenconadoCabezuelaController.php",
       "Planeacion\Utilerias\FinalizarOrdenesController.php",
       "Planeacion\ProgramaTejido\RedboothProgramaTejidoController.php",
       "Planeacion\ProgramaTejido\funciones\VincularTejido.php",
       "Mantenimiento\ParosController.php",
       "PDFController.php",
       "Engomado\ReportesEngomadoController.php",
       "Configuracion\ConfiguracionController.php",
       "Tejedores\NotificarMontadoJulios\NotificarMontadoJulioController.php",
       "Tejedores\Configuracion\TelaresOperador\TelTelaresOperadorController.php",
       "ProgramaUrdEng\ReservarProgramar\ReservarProgramarController.php",
       "Tejido\Reportes\ReporteMarcasFinalesController.php",
       "Configuracion\MensajesController.php"
   )
   $locCriticos = 0
   foreach ($f in $criticos) {
       $locCriticos += (Get-Content (Join-Path "c:\xampp\htdocs\Towell\app\Http\Controllers" $f)).Count
   }
   Write-Host "LoC Total Controladores Críticos (Top 39): $locCriticos LoC"
   # Retorna: 27,679 LoC (~73.8% del volumen total de controladores)
   ```

6. **Verificación de Longitud del Método `AtadoresController::save()` (530 Líneas):**
   ```powershell
   # Rango del método save() en app/Http/Controllers/Atadores/ProgramaAtadores/AtadoresController.php:
   # Firma en línea 484 (public function save), retorno final en línea 1014 (return response()->json(..., 422))
   $lineasSave = 1014 - 484 + 1
   Write-Host "Longitud de save(): $lineasSave líneas"
   # Retorna: 530 líneas de código imperativo continuo
   ```

7. **Verificación de Llamadas Directas a ERP AX (`sqlsrv_ti` en 21 Controladores):**
   ```powershell
   (Select-String -Path "app\Http\Controllers\*.php", "app\Http\Controllers\**\*.php" -Pattern "sqlsrv_ti" | Select-Object -Unique Path).Count
   # Retorna: 21 archivos
   ```

8. **Verificación del Total de Archivos de Prueba (343 archivos):**
   ```powershell
   $feature = (Get-ChildItem -Path "tests\Feature" -Filter "*Test.php" -Recurse).Count
   $unit = (Get-ChildItem -Path "tests\Unit" -Filter "*Test.php" -Recurse).Count
   Write-Host "Feature: $feature, Unit: $unit, Total: $($feature + $unit)"
   # Retorna: Feature: 196, Unit: 147, Total: 343
   ```

9. **Verificación de Guardián de Conexión SQL Server en Tests:**
   Inspeccionar `tests/TestCase.php` líneas 26 a 42 y `phpunit.xml` líneas 18 a 20.
