# Auditor├¡a cr├¡tica de arquitectura y l├│gica de negocio ÔÇö Towell

**Alcance:** Laravel 12, ERP textil. Lectura de c├│digo en `main` (2026-09-18).  
**M├®todo:** evidencia con ruta, s├¡mbolo o ruta HTTP. No se refactoriz├│ producci├│n.  
**Lo que no se ejecut├│:** suite PHPUnit (el entorno de auditor├¡a no garantiza `vendor/` + SQL Server). Los huecos de test se infieren del inventario de `tests/` y de la ausencia de archivos, no de cobertura Clover.

---

## Resumen ejecutivo

Towell **no es un sistema modular**. Las carpetas `app/Http/Controllers/{Planeacion,Urdido,Engomado,ÔÇª}` sugieren bounded contexts; el runtime es un **grafo ├║nico** centrado en `ReqProgramaTejido`, `CatCodificados` y AX (`sqlsrv_ti` / `TI_PRO`). Cualquier usuario autenticado puede, en la pr├íctica, pegarle a la mayor├¡a de APIs: las rutas solo montan `auth`. El men├║ (`SYSRoles` / `SYSUsuariosRoles`) **no es un control de acceso**.

Tres hechos que invalidan la confianza operativa actual:

1. **CRUD de m├│dulos sin login** en `routes/public.php` (`/modulos-sin-auth`). Quien alcance esa URL administra el men├║ y los permisos-plantilla.
2. **Liberar ├│rdenes reescribe `CreaProd = 1`** en `CatCodificados` aunque la fila ya exista. El flujo de reimpresi├│n en `OrdenDeCambioFelpaController` **evita** exactamente eso porque AX baja el bit a 0. Liberar otra vez **vuelve a encolar producci├│n en AX**.
3. **Engomado ÔÇ£En ProcesoÔÇØ no exige Urdido Finalizado** en el `POST` legacy de status. El tablero Livewire s├¡ lo exige. La UI por defecto es la legacy. Un dropdown salta la regla de negocio.

El resto del sistema es coherente **por accidente y por comentarios**, no por un contrato ├║nico: hay dos consultas L.Mat a AX que el c├│digo llama ÔÇ£la misma l├│gicaÔÇØ y **no lo son**; hay dos controladores `MecActividadesController`; hay un modelo `UrdEngNucleos` duplicado en dos directorios; `AGENTS.md` afirma reglas que el c├│digo ya no cumple (`validar-duplicado`, `ROUND` de marbetes, `FechaFinaliza` intacta al mover, cach├® `modulos_v2`).

**Inventario (conteo en disco):** 139 PHP bajo `app/Http/Controllers/`, 54 servicios, 97 modelos, 236 blades, 12 Livewire, 20 Form Requests, 31 exports, 12 imports, 143 tests (100 Unit / 43 Feature). Los cinco blades m├ís gordos suman ~15ÔÇ»800 l├¡neas. `LiberarOrdenesController` tiene **2ÔÇ»941** l├¡neas.

---

## Top 10 riesgos (P0ÔÇôP3)

| # | Sev. | Riesgo | Evidencia | Siguiente movimiento |
|---|------|--------|-----------|----------------------|
| 1 | **P0** | Administraci├│n de m├│dulos **sin autenticaci├│n** | `routes/public.php` L25ÔÇô30 ÔåÆ `ModulosController::{index,store,update,destroy}`. `ModulosController` no tiene middleware de auth en constructor. | Eliminar el grupo o meterlo bajo `auth` + `userCan`. Tratar cualquier hit hist├│rico como incidente. |
| 2 | **P0** | Autorizaci├│n = men├║. APIs abiertas a cualquier sesi├│n | `routes/web.php` L8ÔÇô25: ├║nico middleware de grupo `auth`. `rg userCan\|can:` en `routes/` = 0. Utiler├¡a, L.Mat, paros, liberar, mover: sin `abort(403)` sistem├ítico. | Middleware por m├│dulo (`userCan('acceso', ÔÇª)`) en cada `routes/modules/*.php`. Denegar por defecto. |
| 3 | **P1** | Re-encolar AX: `CreaProd` forzado a 1 al liberar | `LiberarOrdenesController` L613 y payload L1381; foreach L1402ÔÇô1422 escribe todas las columnas. Contraste: `OrdenDeCambioFelpaController::crearOActualizarModeloCodificado` L1574ÔÇô1578 **omite** `CreaProd` en update. | En `actualizarCatCodificados`, no tocar `CreaProd` si el row de `CatCodificados` ya existe. Test de no-regresi├│n. |
| 4 | **P1** | Engomado En Proceso **sin** Urdido Finalizado (stack default) | `ProgramarEngomadoController::actualizarStatus` L516ÔÇô540: solo AX lock. `ProgramBoardActionService::productionBlockReasonForOrder` L207ÔÇô219 **s├¡** exige `Finalizado`. Default UI: Blade legacy (`programar-engomado.blade.php`), no Livewire. | Delegar `actualizarStatus` al service. El endpoint `verificar-en-proceso` (`engomado.php` L76) **no se llama** desde las vistas engomado. |
| 5 | **P1** | Mover ├│rdenes **puede anular `FechaFinaliza`** | `MoverOrdenesController::sincronizarCatCodificados` L605 llama `actualizarFechasArranqueFinaliza($regMovido, null, null)` con default `actualizarFechaFinaliza = true` (`MovimientoDesarrolladorService` L549). `null` en finaliza = ÔÇ£ningunaÔÇØ (comentario L558). Existe el flag `false` y **no se usa**. | Pasar `actualizarFechaFinaliza: false`. AGENTS.md est├í **desactualizado**. |
| 6 | **P1** | L.Mat CRUDO: dos SQLs incompatibles hacia AX | `LiberarOrdenesController::bomCrudoQuery` L2295ÔÇô2319: `whereExists` + `TelarSalonResolver::salonAliasesAx($salon)` o `todosLosAliasesAx()`. `CatCodificacionController::queryLmatDesdeTi` L537ÔÇô562: **JOIN** `BOMVERSION`, **`limit(50)`**, siempre `todosLosAliasesAx()`, **sin filtro de sal├│n de telar**. El comentario L532 miente. | Un `LmatCrudoQueryService`. Contrato de test: mismas filas para el mismo `(item, size, salon)`. Quitar el `limit(50)`. |
| 7 | **P1** | % L.Mat = 100 **solo en el navegador** | `resources/js/catcodificacion/lmat-modal.js` ~L2124 bloquea guardar. `CatLMatController::guardarLmat` L98ÔÇô160: valida filas y AX, **cero** aserci├│n de suma 100. | Validar suma en backend (misma regla que el modal). Feature test con POST directo. |
| 8 | **P2** | Doble tablero Urd/Eng: reglas distintas seg├║n URL | Legacy: `ProgramarUrdidoController` (830 LOC) / `ProgramarEngomadoController` (661). Moderno: `ProgramBoardActionService` + Livewire. Urdido legacy **s├¡** limita 2├ù En Proceso (L636ÔÇô653) y **cancela engomado en cascada** (L676ÔÇô687). Engomado legacy **no** limita concurrencia en `actualizarStatus`. Livewire **s├¡** limita (service L247ÔÇô249) y **s├¡** cascada (L252ÔÇô273). | Una sola ruta de mutaci├│n. Apagar o redirigir el Blade. |
| 9 | **P2** | Tests no cubren las mutaciones que rompen planta | 143 tests. Hay `LiberarOrdenesLiberarTest`, AX status unitarios, program board. **No hay** tests de `MoverOrdenesController`, `FinalizarOrdenesController`, `CatLMatController::guardarLmat`, `MantenimientoParosController::store`. Varios ÔÇ£unitÔÇØ leen el fuente como string. | Feature tests HTTP de esos cuatro flujos **antes** de extraer servicios. |
| 10 | **P3** | Deuda de superficie: CSS cacheado, typos, ID de usuario m├ígico, remember-me global | `req-programa-tejido.blade.php` L337ÔÇô342 (CSS con `?v=filemtime` porque `.htaccess` cachea 1 a├▒o). `reigstrar` vs `registrar`. `MantenimientoParosController::departamentos` L61: `if ($userId === 6)`. `AuthController` L56ÔÇô60: `Auth::login(..., true)` siempre. Modelo duplicado `app/Models/UrdEngomado/UrdEngNucleos.php` vs `app/Models/urdengomado/UrdEngNucleos.php`. | Inventario de ÔÇ£no tocar nombresÔÇØ vs bugs reales. El ID `6` y el CRUD p├║blico son bugs; `catalagos/` es deuda congelada. |

---

## 1. Estructura de carpetas vs acoplamiento real

### 1.1 El mapa nominal es cosm├®tica

| Capa | Cantidad | D├│nde |
|------|---------:|-------|
| Controladores | 139 | `app/Http/Controllers/` |
| Servicios | 54 | `app/Services/` |
| Modelos | 97 | `app/Models/` |
| Vistas Blade | 236 | `resources/views/` |
| Livewire | 12 | `app/Livewire/` |
| Form Requests | 20 | `app/Http/Requests/` |
| Rutas de m├│dulo | 16 | `routes/modules/` |

`routes/web.php` no es un router de dominio: es un `require` de fragmentos. Laravel solo registra `web.php` + `api.php` (`bootstrap/app.php` L12ÔÇô16). **No existe** `routes/ai.php` pese a `AGENTS.md` / `CLAUDE.md`.

Los controladores **no** coinciden con las vistas:

| Dominio pretendido | Controllers | Vistas | Rutas |
|--------------------|-------------|--------|-------|
| Planeaci├│n / programa | `Planeacion/` + `funciones/` + `helper/` | `modulos/programa-tejido/`, `planeacion/`, `catalagos/`, `catcodificacion/` | `routes/modules/planeacion.php` |
| Urdido / Engomado | ├írboles separados | `modulos/urdido/`, `modulos/engomado/` | `urdido.php`, `engomado.php` |
| Programa Urd+Eng | `ProgramaUrdEng/` | `modulos/programa_urd_eng/` (guion bajo) | `programa-urd-eng.php` |
| Mec├ínicos | `app/Http/Controllers/mecanicos/` (**min├║sculas**) | `modulos/mecanicos/` | `mecanicos.php` |

### 1.2 Acoplamiento: Tejedores vive dentro de Planeaci├│n

Importaciones `use App\Models\ÔÇª` cruzadas (muestra, no exhaustiva):

- `Tejedores/Desarrolladores/Funciones/ProcesarDesarrolladorService.php` ÔåÆ `CatCodificados`, `ReqModelosCodificados`, `ReqProgramaTejido`.
- `Tejido/Reportes/ReporteInvTelasController.php` ÔåÆ `EngProgramaEngomado`, `UrdProgramaUrdido`, `ReqProgramaTejido`.
- `Urdido/Configuracion/ModuloProduccionUrdidoController.php` ÔåÆ `EngProgramaEngomado`.
- `Mantenimiento/MantenimientoParosController.php` ÔåÆ `AtaMaquinasModel`, `TelTelaresOperador`, `URDCatalogoMaquina`.
- `mecanicos/OrdenesTrabajoMecaController.php` ÔåÆ `ManFallasParos`, `ReqTelares`, `TelTelaresOperador`, `URDCatalogoMaquina`.

**Programa Tejido es un monolito disfrazado de carpeta.** Bajo `Controllers/Planeacion/ProgramaTejido/funciones/` hay clases de 1ÔÇ»400+ LOC (`BalancearTejido`, `DividirTejido`) que no extienden `Controller` y no viven en `Services/`. PSR-4 en Linux: `funciones/draganddroptejido.php` declara `class DragAndDropTejido` ÔÇö el autoload de Composer (`App\\` ÔåÆ `app/`) **no** resolver├í ese archivo por nombre de clase. Si algo funciona, es porque otro archivo lo `require`/`use` de forma fr├ígil.

### 1.3 Typos y duplicados de filesystem (trampas reales)

| Problema | Ruta |
|----------|------|
| `catalagos` (deber├¡a catalogos) | `resources/views/catalagos/` (21 blades). Modelos `CatalagoEficiencia` / `CatalagoVelocidad` ÔåÆ tablas `catalago_*`. |
| Dos copias del mismo modelo | `app/Models/UrdEngomado/UrdEngNucleos.php` y `app/Models/urdengomado/UrdEngNucleos.php`, **mismo namespace**. En Linux PSR-4 carga **solo** `UrdEngomado/`. Editar la copia min├║scula es editar un muerto. |
| Dos `MecActividadesController` | `mecanicos/MecActividadesController.php` (**sin** `userCan`) vs `mecanicos/Catalogos/MecActividadesController.php` (**con** `userCan('crear', 'Actividades Mecanicos')`). Las rutas usan el de `Catalogos`. El otro es un backdoor si alguien lo vuelve a enlazar. |
| `sqlsrv_tow_pro` | Configurado en `config/database.php`. **Cero** usos en `app/`. Refacciones usa `sqlsrv_tow_tow` (`RefaccionesParoService`). Docs mienten. |
| Cach├® de men├║ | `ModuloService::CACHE_PREFIX = 'modulos_v3'`. `docs/documentacion-modulos/README.md` sigue hablando de `modulos_v2`. |

### 1.4 AX no es un puerto: son 21 archivos peg├índole a `sqlsrv_ti`

Hotspots: `LiberarOrdenesController`, `CatCodificacionController`, `CatLMatController`, `TrazabilidadFlogsService`, `BomMaterialesService`, `EngProduccionFormulacionController`. Un timeout de login en `TI_PRO` no tumba el ERP entero: **rompe liberar, L.Mat, flogs, f├│rmulas** y deja el resto ÔÇ£verdeÔÇØ. No hay capa anti-corrupci├│n. Los `catch (\Exception $e) { /* silenciar */ }` de BomName en `LiberarOrdenesController` L607ÔÇô609 **tragan** el fallo de AX y siguen.

---

## 2. Coherencia de flujos de negocio

Cadena que el negocio cree que existe:

`Planeaci├│n (ReqProgramaTejido) ÔåÆ Liberar ÔåÆ CatCodificados / AX ÔåÆ Programa Urd/Eng ÔåÆ Producci├│n Urdido ÔåÆ Producci├│n Engomado ÔåÆ Atadores ÔåÆ Tejido/Tejedores ÔåÆ Crudo/Trazabilidad`  
y en paralelo `Mantenimiento/Mec├ínicos` sobre las mismas m├íquinas.

### 2.1 Liberar ├│rdenes ÔåÆ CatCodificados (n├║cleo podrido)

Rutas: `programa-tejido.liberar-ordenes` y el espejo `muestras.liberar-ordenes` (`routes/modules/planeacion.php` L204ÔÇô211 y L272ÔÇô279). Mismo controller, tablas distintas v├¡a `ProgramaTejidoContext` (`planeacion/muestras*` ÔåÆ `MuestrasPrograma`).

**Verificado en c├│digo:**

- `bomId` / `bomName` **required** (`LiberarOrdenesController` L253ÔÇô254, mensajes L275ÔÇô276).
- `CodigoDibujo` opcional; `actualizarCatCodificados` no pisa si no hay valor resuelto.
- `ReqProgramaTejido` castea `Prioridad` a `string` (L188). El Blade de liberar **no** usa `empty()` sobre prioridad (comentario L98).
- Repeticiones: `(int) $v` = trunc hacia cero (`repeticionesDesdePesoRollo`).
- **Marbetes: el c├│digo hace `ceil`, no `ROUND`.** `saldoMarbeteDesdeFormula` L2084ÔÇô2086. `AGENTS.md` dice `REDONDEAR`. El comentario del propio m├®todo (L2068ÔÇô2070) admite que Observer + `SaldoMarbeteCodificacionService` **tienen que techar igual** o se pisan. Tres sitios, una f├│rmula fr├ígil, documentaci├│n contradictoria.

### 2.2 Programa Urdido / Engomado: una regla, tres implementaciones

Contrato can├│nico: `App\Support\Programas\ProgramaConfig::STATUS_BLOQUEADOS_CON_AX_PRODUCCION` = `Cancelado`, `Programado`, `En Proceso`. `Parcial` queda fuera. Tests: `ProgramarUrdidoActualizarStatusAxTest`, `ProgramarEngomadoActualizarStatusAxTest`, `ProgramBoardActionServiceTest`.

| Regla | Urdido legacy `actualizarStatus` | Engomado legacy `actualizarStatus` | Livewire `ProgramBoardActionService` |
|-------|----------------------------------|------------------------------------|--------------------------------------|
| Bloqueo AX=1 | S├¡ (`jsonSiAxBloqueaEstatus`) | S├¡ (copia) | S├¡ L97ÔÇô105 |
| M├íx. 2 En Proceso por carril | S├¡ L636ÔÇô653 | **No** en el POST de status | S├¡ L247ÔÇô249 |
| Urdido Finalizado antes de Engomado En Proceso | N/A | **No** | S├¡ L207ÔÇô219 |
| Cancelar urdido cancela engomado + borra prod | S├¡ L676ÔÇô687 | Solo borra prod engomado | S├¡ L252ÔÇô273 |

La vista default de engomado (`resources/views/modulos/engomado/programar-engomado.blade.php` ~L751) bloquea ÔÇ£Ir a producci├│nÔÇØ si `urdido_finalizado !== true`. **El combo de status no pasa por ese JS.** `verificarOrdenEnProceso` (controller L298) adem├ís **falla abierto**: sin `maquina_eng` o sin n├║mero de tabla, responde ÔÇ£se permite cargarÔÇØ (L323ÔÇô328, L336). Y **ninguna vista engomado llama esa ruta**.

### 2.3 Mover / Finalizar / Desarrolladores

- **Finalizar** (`FinalizarOrdenesController`): escribe `FechaFinaliza` y sincroniza Cat. Preferencia de producto: no confirmar si producci├│n es 0 ÔÇö vive sobre todo en JS de `planeacion/utileria/finalizar-ordenes.blade.php`, no hay test de controller.
- **Mover** (`MoverOrdenesController` L595ÔÇô606): al cambiar sal├│n sincroniza Cat y **puede poner `FechaFinaliza` a null** (ver riesgo #5).
- **Desarrolladores:** l├│gica en `Tejedores/Desarrolladores/Funciones/*Service.php` ÔÇö servicios **dentro de Controllers**. `CatCodificadosDesarrolladorService::applyPayload` L29ÔÇô48 omite texto no num├®rico en columnas FLOAT (p. ej. `600/1T`). `CatCodificados` **no castea** `CalibreComb1`ÔÇô`5`; el guardado depende de que **ese** service filtre. Cualquier otro writer (liberar, Excel, Livewire `Captura`) puede mandar el nvarchar al FLOAT y SQLSTATE.

### 2.4 Atadores

`AtadoresController` (~1ÔÇ»200 LOC) + `OeeAtadoresFileService` (**3ÔÇ»097** LOC, el PHP m├ís largo del repo). Ciclo ActivoÔåÆÔÇªÔåÆAutorizado sobre `TejInventarioTelares` / montado. Aislado de AX en el slice revisado. El OEE es un segundo ERP embebido en un ÔÇ£serviceÔÇØ.

### 2.5 Crudo / Trazabilidad

Mejor capa de servicios del repo (`app/Services/Trazabilidad/*`, `app/Services/Crudo/*`) + Livewire. Aun as├¡: filtro Color **solo** rollos te├▒ido (`TrazabilidadProduccionService`); Flogs pega a `sqlsrv_ti`; el scroll real est├í en `main.app-main` (layout), no en `body` ÔÇö ya biti├│ bugs de `overflow:hidden`. Dos stacks UI (Blade mega + Livewire + TS en `resources/js/trazabilidad/`).

### 2.6 Mantenimiento / paros

`GET /api/mantenimiento/paros` (`MantenimientoParosController::index` L696+):

- Sin query: `Depto` = ├írea del usuario; ├írea vac├¡a ÔåÆ `whereRaw('1 = 0')`.
- Default `Estatus = Activo`; `incluir_finalizados=1` abre ventana de d├¡as (default 30, m├íx. 365) **m├ís** los Activo.
- `alcance=todos` / `depto=` con cat├ílogo `SysDepartamentos` (si el depto no existe ÔåÆ vac├¡o).
- Tejedores: `aplicarRestriccionTelaresOperadorSiCorresponde` recorta `MaquinaId`.

`store` valida duplicado **dentro** de la transacci├│n (`hayActivoEnMaquina`, L507). **No existe** `GET api/mantenimiento/paros/validar-duplicado`. `AGENTS.md` y preferencias aprendidas est├ín **mal**. `nuevo-paro` no referencia esa ruta.

**ID m├ígico:** `departamentos()` L61 `if ($userId === 6)` restringe a Urdido/Engomado. No es configuraci├│n: es un empleado hardcodeado.

`Route::view` para solicitudes y reporte (`mantenimiento.php` L19, L23): **la misma Blade, dos URLs, cero controller**, cero permiso.

---

## 3. Duplicaci├│n

### 3.1 Urdido vs Engomado (copy-paste industrial)

- Boards: ~58ÔÇô74% de similitud controller/vista (conteo de auditor├¡a previa). `jsonSiAxBloqueaEstatus` **copiado**.
- Exports `BpmUrdidoExport` vs `BpmEngomadoExport` Ôëê 99% id├®nticos. Igual `ReporteResumenSemanalUrdidoExport` / `Engomado`.
- `ReportesUrdidoController` 1ÔÇ»652 LOC vs `ReportesEngomadoController` 462: mismo patr├│n, urdido acumul├│ basura.
- Producci├│n: `ProduccionTrait` (902 LOC) comparte tope; urdido override `maxKgNetoAllowed() = 700` (`ModuloProduccionUrdidoController` L41ÔÇô44); engomado override `maxKgBrutoAllowed() = 2000`. Defaults del trait = `null` (sin tope). Un tercer m├│dulo que use el trait **nace sin l├¡mite**.
- Calificar julios: engomado tiene controller + **dos** partials (`modal-calificar-julios` y `ÔÇª-julios-eng`). Urdido embebe julios en `produccion/_scripts.blade.php` (2ÔÇ»268 LOC).

### 3.2 Dos codificaciones y un L.Mat

| Stack | Controller | Vista | Tabla |
|-------|------------|-------|-------|
| ÔÇ£ModelosÔÇØ legacy | `CodificacionController` (1ÔÇ»279) | `catalagos/catalogoCodificacion.blade.php` (2ÔÇ»834) | `ReqModelosCodificados` |
| ÔÇ£CatÔÇØ actual | `CatCodificacionController` (1ÔÇ»014) | `catcodificacion/` | `CatCodificados` |

Nombres de ruta **parecidos, no colisionan** (Laravel s├¡ prefixea): `planeacion.catalogos.codificacion.all-fast` vs `planeacion.codificacion.all-fast`. Quien escriba `route('codificacion.all-fast')` se equivoca. Excel duplicado (`codificacion.excel` en ambos grupos).

Muestras: 11 pares de rutas programa Ôåö muestras (`PlaneacionProgramaMuestrasRouteParityTest`). No es DRY: es **duplicar superficie HTTP** para no parametrizar el prefijo.

### 3.3 Reportes

31 clases en `app/Exports/`. Ceremonia PhpSpreadsheet (`WithEvents` / `AfterSheet`) copiada. `ReportesUrdidoController` es un god-object de reportes. El patr├│n ÔÇ£controller gordo + export casi clonÔÇØ se repite en tejido (RPM semanal **nombrado** `tejido.reportes.inv-trama` ÔÇö el nombre de ruta es de otro reporte).

### 3.4 Secuencias de tejido

`corte-eficiencia` / `marcas-finales` / `inv-telas` / `inv-trama` bajo `modulos/tejido/secuencia/` son el mismo esqueleto `fetch`-pesado. Cualquier fix de CSRF o error handling hay que pegarlo cuatro veces.

---

## 4. Fat controllers, l├│gica en Blade/JS, permisos

### 4.1 D├│nde est├í la verdad (spoiler: no en Services)

~24% de los controllers importan `App\Services\`. El resto **es** la capa de dominio.

**Controllers / ÔÇ£funcionesÔÇØ > 1ÔÇ»000 LOC (muestra):**

| LOC | Archivo |
|----:|---------|
| 3097 | `app/Services/OeeAtadores/OeeAtadoresFileService.php` |
| 2941 | `LiberarOrdenesController.php` |
| 1933 | `OrdenDeCambioFelpaController.php` |
| 1737 | `CortesEficienciaController.php` |
| 1652 | `ReportesUrdidoController.php` |
| 1580 | `OrdenesTrabajoMecaController.php` |
| 1431 | `funciones/DividirTejido.php` |
| 1419 | `funciones/BalancearTejido.php` |
| 1279 | `CodificacionController.php` |
| 1039 | `app/Livewire/Desarrolladores/Captura.php` |

**Blades que son aplicaciones:**

| LOC | Archivo |
|----:|---------|
| 3589 | `modulos/engomado/captura-formula/index.blade.php` |
| 3389 | `modulos/engomado/modulo-produccion-engomado.blade.php` |
| 3298 | `components/telares/telar-requerimiento.blade.php` |
| 2834 | `catalagos/catalogoCodificacion.blade.php` |
| 2682 | `modulos/programa-tejido/liberar-ordenes/index.blade.php` |

`@php` aparece en ~124 blades. 20 Form Requests para 139 controllers: la validaci├│n est├í **inline** o en el navegador.

### 4.2 HTTP y notificaciones: la migraci├│n no ocurri├│

En `resources/views`:

| API | Usos aprox. |
|-----|------------:|
| `fetch(` | **250** |
| `http.get/post/ÔÇª` | **37** |
| `Swal.` | **976** |
| `notify.` | **29** |

`AGENTS.md` dice preferir `window.http` / `window.notify` (`resources/js/utils/http.js`, `notifications.js`). Calendarios es el piloto. Producci├│n urdido/engomado, atadores, liberar, cortes, inventario trama siguen en `fetch` + SweetAlert. `notify` escapa HTML; `Swal` con HTML crudo **no**. XSS de mensaje de servidor ÔåÆ modal es el camino probable, no te├│rico.

`resources/js/programa-tejido/index.js` supera las 7ÔÇ»000 l├¡neas. Es el verdadero controller del programa de tejido.

### 4.3 Permisos: cerebro partido + agujero p├║blico

```
SYSRoles.reigstrar     (typo, plantilla del m├│dulo)
SYSUsuariosRoles.registrar  (permiso real por usuario)
userCan('registrar', $modulo)  ÔåÆ lee la columna correcta
```

Puente: `ModulosController::actualizarPermisosNuevoModulo` L545. `togglePermiso` L468 solo acepta `reigstrar` sobre **SYSRoles**, no sobre el usuario.

Cach├®: `modulos_v3` + `APP_ENV`. Si no llaman `ModuloService::limpiarCacheUsuario()` el men├║ miente. El men├║ **no autoriza** las APIs.

Grupo p├║blico:

```25:30:routes/public.php
Route::prefix('modulos-sin-auth')->name('modulos.sin.auth.')->group(function () {
    Route::get('/', [ModulosController::class, 'index'])->name('index');
    Route::post('/', [ModulosController::class, 'store'])->name('store');
    ÔÇª
});
```

Tambi├®n p├║blico: `GET /obtener-empleados/{area}` (`public.php` L19ÔÇô20) ÔÇö enumeraci├│n de plantilla. CSRF del grupo `web` aplica a POST, pero el GET entrega el formulario de gesti├│n de m├│dulos a un an├│nimo.

`ForceHttps` est├í **comentado** en `bootstrap/app.php` L23ÔÇô24.

Login: `AuthController` L44ÔÇô46 compara plaintext con `hash_equals` y rehash a bcrypt. Correcto como migraci├│n; las contrase├▒as legacy **siguen en claro en SQL Server** hasta el pr├│ximo login de cada usuario. `Auth::login($empleado, true)` L60: remember-me **siempre** (kioscos / and├│n). Robo de cookie = sesi├│n larga.

---

## 5. Footguns verificados (no confiar en AGENTS.md)

| Afirmaci├│n (AGENTS / ÔÇ£learnedÔÇØ) | C├│digo real |
|---------------------------------|-------------|
| `GET ÔÇª/validar-duplicado` antes del POST de paros | **No est├í en** `routes/modules/mantenimiento.php`. Solo `store` + `hayActivoEnMaquina`. |
| Saldo marbete = `REDONDEAR` | `saldoMarbeteDesdeFormula` usa **`ceil`**. |
| `FechaFinaliza` no se toca al mover | Se puede **poner null** (flag default `true`). |
| CreaProd seguro en reimprimir | Felpa s├¡; **liberar no**. |
| Engomado exige urdido finalizado | Solo bot├│n ÔÇ£Ir a producci├│nÔÇØ + Livewire. **No** el POST de status. |
| Cach├® men├║ `modulos_v2` | C├│digo: **`modulos_v3`**. |
| `routes/ai.php` | **No existe**. |
| `% L.Mat exactamente 100` en front y back | Front s├¡ (`lmat-modal.js` L2124). **Backend no.** |
| `empty()` en Prioridad | Arreglado en liberar. Sigue siendo trampa en cualquier Blade nuevo. |
| Calibres float vs `600/1T` | Filtrado en `CatCodificadosDesarrolladorService`. Otros writers no. |
| AX=1 bloquea Cancelado/Programado/En Proceso; Parcial OK | **Verificado** y testeado. Esta es de las pocas reglas consistentes. |
| L.Mat `ITEMGROUPID=CRUDO`, salones SMIT/JACQUARD(+JACUARD) | S├¡, v├¡a `TelarSalonResolver`. El **c├│mo** se consulta **diverge**. |

Otros:

- `LiberarOrdenesController` L597 usa `empty($registro->BomName)` ÔÇö mismo bug de `"0"` si alg├║n d├¡a un BomName fuera `"0"`. Menor.
- `queryLmatDesdeTi` `limit(50)`: un art├¡culo con muchas versiones de BOM **corta la lista** que el select de Peso muestra ense├▒a.
- `ProgramaTejidoContext` es middleware **global** web (`bootstrap/app.php` L37). Cualquier request `planeacion/muestras*` cambia `config('planeacion.programa_tejido_table')` **para ese request**. Un helper que cachee el nombre de tabla a nivel proceso est├í un bug de lejos.
- Observaciones truncadas en `ReqProgramaTejido` `saving` (`StringTruncator`) ÔÇö evita SQLSTATE 22001 **perdiendo texto en silencio**.

---

## 6. Deuda de UI / dise├▒o

### 6.1 Tres generaciones de frontend conviven

1. **jQuery + Blade monol├¡tico + SweetAlert + `fetch`** (urdido, engomado, atadores, liberar, cat├ílogos).
2. **Vite entrypoints de dominio** (`resources/js/programa-tejido/index.js`, `catcodificacion/`, `urd-eng/*.ts`, `trazabilidad/*.ts`, `crudo/*.ts`).
3. **Livewire 4** (Crudo, Trazabilidad, ProgramBoard, Captura desarrolladores, cat├ílogo fallas). Solo ~6 blades referencian Livewire.

No hay design system. Hay `components/ui/{button,modal-base,alert}` y a la vez HTML crudo + Tailwind ad hoc en cada m├│dulo. Carpetas de vistas: `modulos/`, `catalagos/`, `catcodificacion/`, `planeacion/`, `catalogosurdido/`.

### 6.2 Rutas legacy y nombres mentirosos

- Redirects 301 en `planeacion.php` (`programatejido`, `utilera`, `codificacionmodelos`, ÔÇª) y `tejido.php`.
- `/urdido/programaurdido` y `/urdido/programar-urdido` ÔåÆ mismo index; extra `/programar-urdido/legacy` y Livewire en otra URI.
- RPM semanal: URI `/tejido/reportes/rpm-semanal`, **name** `tejido.reportes.inv-trama`.
- Programa tejido index: URI `/planeacion/programa-tejido`, **name** `catalogos.req-programa-tejido`.
- Configuraci├│n: endpoint duplicado `GET /configuracion/utileria/api/modulos/nivel/{nivel}` (`configuracion.utileria.api.modulos.nivel`) y `GET /api/modulos/nivel/{nivel}` (`api.modulos.nivel`) ÔÇö mismo m├®todo, dos URLs. No es colisi├│n de `route()`, es superficie doble.

### 6.3 CSS / Vite / cach├®

Comentario en producci├│n, no en un ticket:

```337:342:resources/views/modulos/programa-tejido/req-programa-tejido.blade.php
{{-- ?v=filemtime obligatorio: .htaccess le pone un ano de expiracion al CSS y
     estos dos no pasan por Vite, asi que sin esto el navegador sigue sirviendo el
     de antes. ÔÇª un main.css cacheado deja la tabla sin tamano ni padding
     y la pagina se ve "con zoom". --}}
```

`NoCacheHtmlResponses` pone `Cache-Control` en HTML, **no** en `/css/programa-tejido/*.css`. Laragon sirviendo `public/build` sin `npm run build` deja JS de `resources/js` viejo (documentado en AGENTS.md). Resultado: ÔÇ£arregl├® el modal y en planta no se veÔÇØ.

Paros: CSS inline de `<dialog>` porque el variant de Tailwind v4 **no compilaba** (comentario en `reporte-fallos-paros/index.blade.php`). Workarounds apilados.

### 6.4 Accesibilidad / UX (lo visible en vistas)

- 976 `Swal.` vs dialogos nativos: foco, ESC, etiquetas ARIA inconsistentes. El reporte de paros **s├¡** documenta `<dialog>` + `aria-labelledby` ÔÇö excepci├│n, no patr├│n.
- Botones de men├║ contextual en programa tejido (`req-programa-tejido.blade.php` L321ÔÇô332) sin `type="button"` en varios: en un form heredado disparan submit.
- Permisos en UI: `userCan` en ~10ÔÇô14 blades. El resto muestra acciones que el API a veces ni siquiera 403-ea.
- Tablas de miles de filas pintadas en el DOM (comentario expl├¡cito en `index` de paros L709ÔÇô711). Incluir finalizados = riesgo de congelar el navegador.
- Mezcla de selects nativos, Select2 y combos a mano. Calendarios migr├│ a `http`; el operador cruza m├│dulos y el comportamiento de error cambia.

---

## 7. Huecos de test

`phpunit.xml` fuerza `DB_CONNECTION=sqlite` / `:memory:` **a prop├│sito**: sin eso la suite pega a SQL Server real **sin** `RefreshDatabase` (comentario L25ÔÇô30). `UsesSqlsrvSqlite` rebind├®a `sqlsrv` y crea esquemas m├¡nimos. Eso es inteligente y **peligroso**: tipos FLOAT/NVARCHAR, collations y `lockForUpdate` de SQL Server **no** se ejercitan.

| Flujo cr├¡tico | ┬┐Hay test que mute estado? |
|---------------|----------------------------|
| Liberar ├│rdenes | S├¡: `tests/Feature/LiberarOrdenesLiberarTest.php` (+ units de f├│rmulas). No cubre `CreaProd` en Cat existente. |
| AX status urd/eng | S├¡ (unit, controllers + service). |
| Program board Livewire | S├¡: `ProgramBoardActionServiceTest`, `ProgramBoardLivewireTest`. |
| Mover ├│rdenes | **No** el controller (608 LOC). Solo `MovimientoOrdenBloqueoTest` (orden de locks) y contrato de rutas. |
| Finalizar ├│rdenes | **No** comportamiento (415 LOC). Ruta en contract test. |
| Guardar L.Mat | **No** `CatLMatController`. `MatrizCalibresServiceTest` menciona `guardarLmat` de pasada. |
| Paros `store` / duplicado | `MantenimientoParosControllerTest`: m├íquinas / filtro calidad. **No store.** |
| Trazabilidad | Varios unit de estructura/layout/Livewire. Poco HTTP+TI. |
| Captura f├│rmula 3ÔÇ»589 LOC | `EngProduccionFormulacionControllerTest` del controller; Blade JS = 0. |
| Utiler├¡a / alineaci├│n | `AlineacionControllerTest` (rangos). Utiler├¡a = rutas. |

Muchos tests `*StructureTest` / `*MarkupTest` / `*JsGlobalsTest` **leen archivos como texto**. ├Ütiles como humo, in├║tiles como regla de negocio.

143 tests para ~59k LOC de controllers + ~94k de vistas es **teatro de cobertura**. Lo que est├í testeado coincide con dolor reciente (liberar, board AX, crudo, trazabilidad grouping), no con el radio de explosi├│n.

---

## 8. Mapa de ÔÇ£una regla, N copiasÔÇØ (para no olvidar)

```
Liberar.bomCrudoQuery  ÔöÇÔöÇÔöÉ
                         Ôö£ÔöÇÔöÇÔû║ BOMTABLE+BOMVERSION CRUDO  (resultados distintos)
CatCodificacion.queryLmatDesdeTi ÔöÇÔöÿ

ProgramaConfig.estatusBloqueadoPorAx
  Ôö£ÔöÇ ProgramarUrdidoController.jsonSiAxBloqueaEstatus
  Ôö£ÔöÇ ProgramarEngomadoController.jsonSiAxBloqueaEstatus
  ÔööÔöÇ ProgramBoardActionService.changeStatus

CreaProd
  Ôö£ÔöÇ LiberarOrdenesController: siempre 1 (programa + Cat)
  Ôö£ÔöÇ OrdenDeCambioFelpaController: solo alta
  ÔööÔöÇ ProcesarDesarrolladorService: copia el del programa

FechaFinaliza
  Ôö£ÔöÇ FinalizarOrdenesController / ProcesarDesarrollador (accion=finalizar): set
  ÔööÔöÇ MoverOrdenesController: puede null

% L.Mat = 100
  Ôö£ÔöÇ lmat-modal.js: bloquea
  ÔööÔöÇ CatLMatController: no
```

---

## 9. Qu├® no hacer ahora

No extraer `LiberarOrdenesService` ÔÇ£porque SOLIDÔÇØ. No unificar Urdido/Engomado en un mega-refactor. No renombrar `catalagos` ni `reigstrar` sin migraci├│n de BD y de cada Blade. Esas movidas **rompen planta** y esta auditor├¡a no las pide.

S├¡ hay cambios **peque├▒os y de alto valor** (por prioridad del top 10): cerrar `modulos-sin-auth`, poner middleware de permiso en rutas, dejar de escribir `CreaProd` en update, unificar el POST de status de engomado con el service, no tocar `FechaFinaliza` al mover, una query L.Mat, validar 100% en backend. Cada uno cabe en un PR con un feature test. Eso es el trabajo; el resto es arqueolog├¡a.

---

## 10. Ap├®ndice: archivos ancla

| Tema | Ancla |
|------|--------|
| Dispatcher | `routes/web.php`, `routes/public.php`, `bootstrap/app.php` |
| Liberar | `app/Http/Controllers/Planeacion/ProgramaTejido/LiberarOrdenesController.php` |
| Mover / Finalizar | `app/Http/Controllers/Planeacion/Utilerias/MoverOrdenesController.php`, `FinalizarOrdenesController.php` |
| Fechas Cat | `app/Http/Controllers/Tejedores/Desarrolladores/Funciones/MovimientoDesarrolladorService.php` |
| Board moderno | `app/Services/Programas/ProgramBoardActionService.php`, `app/Support/Programas/ProgramaConfig.php` |
| Board legacy | `ProgramarUrdidoController.php`, `ProgramarEngomadoController.php` |
| L.Mat Cat | `CatCodificacionController::queryLmatDesdeTi`, `CatLMatController::guardarLmat`, `resources/js/catcodificacion/lmat-modal.js` |
| Permisos | `app/Helpers/permission-helpers.php`, `app/Services/ModuloService.php`, `app/Models/Sistema/SYSRoles.php` |
| Paros | `MantenimientoParosController.php`, `routes/modules/mantenimiento.php` |
| CSS cache | `resources/views/modulos/programa-tejido/req-programa-tejido.blade.php` |
| Tests | `phpunit.xml`, `tests/Unit/`, `tests/Feature/` |

---

*Auditor├¡a de lectura. Cero cambios de comportamiento en esta entrega.*
