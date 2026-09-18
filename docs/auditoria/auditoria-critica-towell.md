# Auditoría crítica de arquitectura y lógica de negocio — Towell

**Alcance:** Laravel 12, ERP textil. Lectura de código en `main` (2026-09-18).  
**Método:** evidencia con ruta, símbolo o ruta HTTP. No se refactorizó producción.  
**Lo que no se ejecutó:** suite PHPUnit (el entorno de auditoría no garantiza `vendor/` + SQL Server). Los huecos de test se infieren del inventario de `tests/` y de la ausencia de archivos, no de cobertura Clover.

---

## Resumen ejecutivo

Towell **no es un sistema modular**. Las carpetas `app/Http/Controllers/{Planeacion,Urdido,Engomado,…}` sugieren bounded contexts; el runtime es un **grafo único** centrado en `ReqProgramaTejido`, `CatCodificados` y AX (`sqlsrv_ti` / `TI_PRO`). Cualquier usuario autenticado puede, en la práctica, pegarle a la mayoría de APIs: las rutas solo montan `auth`. El menú (`SYSRoles` / `SYSUsuariosRoles`) **no es un control de acceso**.

Tres hechos que invalidan la confianza operativa actual:

1. **CRUD de módulos sin login** en `routes/public.php` (`/modulos-sin-auth`). Quien alcance esa URL administra el menú y los permisos-plantilla.
2. **Liberar órdenes reescribe `CreaProd = 1`** en `CatCodificados` aunque la fila ya exista. El flujo de reimpresión en `OrdenDeCambioFelpaController` **evita** exactamente eso porque AX baja el bit a 0. Liberar otra vez **vuelve a encolar producción en AX**.
3. **Engomado “En Proceso” no exige Urdido Finalizado** en el `POST` legacy de status. El tablero Livewire sí lo exige. La UI por defecto es la legacy. Un dropdown salta la regla de negocio.

El resto del sistema es coherente **por accidente y por comentarios**, no por un contrato único: hay dos consultas L.Mat a AX que el código llama “la misma lógica” y **no lo son**; hay dos controladores `MecActividadesController`; hay un modelo `UrdEngNucleos` duplicado en dos directorios; `AGENTS.md` afirma reglas que el código ya no cumple (`validar-duplicado`, `ROUND` de marbetes, `FechaFinaliza` intacta al mover, caché `modulos_v2`).

**Inventario (conteo en disco):** 139 PHP bajo `app/Http/Controllers/`, 54 servicios, 97 modelos, 236 blades, 12 Livewire, 20 Form Requests, 31 exports, 12 imports, 143 tests (100 Unit / 43 Feature). Los cinco blades más gordos suman ~15 800 líneas. `LiberarOrdenesController` tiene **2 941** líneas.

---

## Top 10 riesgos (P0–P3)

| # | Sev. | Riesgo | Evidencia | Siguiente movimiento |
|---|------|--------|-----------|----------------------|
| 1 | **P0** | Administración de módulos **sin autenticación** | `routes/public.php` L25–30 → `ModulosController::{index,store,update,destroy}`. `ModulosController` no tiene middleware de auth en constructor. | Eliminar el grupo o meterlo bajo `auth` + `userCan`. Tratar cualquier hit histórico como incidente. |
| 2 | **P0** | Autorización = menú. APIs abiertas a cualquier sesión | `routes/web.php` L8–25: único middleware de grupo `auth`. `rg userCan\|can:` en `routes/` = 0. Utilería, L.Mat, paros, liberar, mover: sin `abort(403)` sistemático. | Middleware por módulo (`userCan('acceso', …)`) en cada `routes/modules/*.php`. Denegar por defecto. |
| 3 | **P1** | Re-encolar AX: `CreaProd` forzado a 1 al liberar | `LiberarOrdenesController` L613 y payload L1381; foreach L1402–1422 escribe todas las columnas. Contraste: `OrdenDeCambioFelpaController::crearOActualizarModeloCodificado` L1574–1578 **omite** `CreaProd` en update. | En `actualizarCatCodificados`, no tocar `CreaProd` si el row de `CatCodificados` ya existe. Test de no-regresión. |
| 4 | **P1** | Engomado En Proceso **sin** Urdido Finalizado (stack default) | `ProgramarEngomadoController::actualizarStatus` L516–540: solo AX lock. `ProgramBoardActionService::productionBlockReasonForOrder` L207–219 **sí** exige `Finalizado`. Default UI: Blade legacy (`programar-engomado.blade.php`), no Livewire. | Delegar `actualizarStatus` al service. El endpoint `verificar-en-proceso` (`engomado.php` L76) **no se llama** desde las vistas engomado. |
| 5 | **P1** | Mover órdenes **puede anular `FechaFinaliza`** | `MoverOrdenesController::sincronizarCatCodificados` L605 llama `actualizarFechasArranqueFinaliza($regMovido, null, null)` con default `actualizarFechaFinaliza = true` (`MovimientoDesarrolladorService` L549). `null` en finaliza = “ninguna” (comentario L558). Existe el flag `false` y **no se usa**. | Pasar `actualizarFechaFinaliza: false`. AGENTS.md está **desactualizado**. |
| 6 | **P1** | L.Mat CRUDO: dos SQLs incompatibles hacia AX | `LiberarOrdenesController::bomCrudoQuery` L2295–2319: `whereExists` + `TelarSalonResolver::salonAliasesAx($salon)` o `todosLosAliasesAx()`. `CatCodificacionController::queryLmatDesdeTi` L537–562: **JOIN** `BOMVERSION`, **`limit(50)`**, siempre `todosLosAliasesAx()`, **sin filtro de salón de telar**. El comentario L532 miente. | Un `LmatCrudoQueryService`. Contrato de test: mismas filas para el mismo `(item, size, salon)`. Quitar el `limit(50)`. |
| 7 | **P1** | % L.Mat = 100 **solo en el navegador** | `resources/js/catcodificacion/lmat-modal.js` ~L2124 bloquea guardar. `CatLMatController::guardarLmat` L98–160: valida filas y AX, **cero** aserción de suma 100. | Validar suma en backend (misma regla que el modal). Feature test con POST directo. |
| 8 | **P2** | Doble tablero Urd/Eng: reglas distintas según URL | Legacy: `ProgramarUrdidoController` (830 LOC) / `ProgramarEngomadoController` (661). Moderno: `ProgramBoardActionService` + Livewire. Urdido legacy **sí** limita 2× En Proceso (L636–653) y **cancela engomado en cascada** (L676–687). Engomado legacy **no** limita concurrencia en `actualizarStatus`. Livewire **sí** limita (service L247–249) y **sí** cascada (L252–273). | Una sola ruta de mutación. Apagar o redirigir el Blade. |
| 9 | **P2** | Tests no cubren las mutaciones que rompen planta | 143 tests. Hay `LiberarOrdenesLiberarTest`, AX status unitarios, program board. **No hay** tests de `MoverOrdenesController`, `FinalizarOrdenesController`, `CatLMatController::guardarLmat`, `MantenimientoParosController::store`. Varios “unit” leen el fuente como string. | Feature tests HTTP de esos cuatro flujos **antes** de extraer servicios. |
| 10 | **P3** | Deuda de superficie: CSS cacheado, typos, ID de usuario mágico, remember-me global | `req-programa-tejido.blade.php` L337–342 (CSS con `?v=filemtime` porque `.htaccess` cachea 1 año). `reigstrar` vs `registrar`. `MantenimientoParosController::departamentos` L61: `if ($userId === 6)`. `AuthController` L56–60: `Auth::login(..., true)` siempre. Modelo duplicado `app/Models/UrdEngomado/UrdEngNucleos.php` vs `app/Models/urdengomado/UrdEngNucleos.php`. | Inventario de “no tocar nombres” vs bugs reales. El ID `6` y el CRUD público son bugs; `catalagos/` es deuda congelada. |

---

## 1. Estructura de carpetas vs acoplamiento real

### 1.1 El mapa nominal es cosmética

| Capa | Cantidad | Dónde |
|------|---------:|-------|
| Controladores | 139 | `app/Http/Controllers/` |
| Servicios | 54 | `app/Services/` |
| Modelos | 97 | `app/Models/` |
| Vistas Blade | 236 | `resources/views/` |
| Livewire | 12 | `app/Livewire/` |
| Form Requests | 20 | `app/Http/Requests/` |
| Rutas de módulo | 16 | `routes/modules/` |

`routes/web.php` no es un router de dominio: es un `require` de fragmentos. Laravel solo registra `web.php` + `api.php` (`bootstrap/app.php` L12–16). **No existe** `routes/ai.php` pese a `AGENTS.md` / `CLAUDE.md`.

Los controladores **no** coinciden con las vistas:

| Dominio pretendido | Controllers | Vistas | Rutas |
|--------------------|-------------|--------|-------|
| Planeación / programa | `Planeacion/` + `funciones/` + `helper/` | `modulos/programa-tejido/`, `planeacion/`, `catalagos/`, `catcodificacion/` | `routes/modules/planeacion.php` |
| Urdido / Engomado | árboles separados | `modulos/urdido/`, `modulos/engomado/` | `urdido.php`, `engomado.php` |
| Programa Urd+Eng | `ProgramaUrdEng/` | `modulos/programa_urd_eng/` (guion bajo) | `programa-urd-eng.php` |
| Mecánicos | `app/Http/Controllers/mecanicos/` (**minúsculas**) | `modulos/mecanicos/` | `mecanicos.php` |

### 1.2 Acoplamiento: Tejedores vive dentro de Planeación

Importaciones `use App\Models\…` cruzadas (muestra, no exhaustiva):

- `Tejedores/Desarrolladores/Funciones/ProcesarDesarrolladorService.php` → `CatCodificados`, `ReqModelosCodificados`, `ReqProgramaTejido`.
- `Tejido/Reportes/ReporteInvTelasController.php` → `EngProgramaEngomado`, `UrdProgramaUrdido`, `ReqProgramaTejido`.
- `Urdido/Configuracion/ModuloProduccionUrdidoController.php` → `EngProgramaEngomado`.
- `Mantenimiento/MantenimientoParosController.php` → `AtaMaquinasModel`, `TelTelaresOperador`, `URDCatalogoMaquina`.
- `mecanicos/OrdenesTrabajoMecaController.php` → `ManFallasParos`, `ReqTelares`, `TelTelaresOperador`, `URDCatalogoMaquina`.

**Programa Tejido es un monolito disfrazado de carpeta.** Bajo `Controllers/Planeacion/ProgramaTejido/funciones/` hay clases de 1 400+ LOC (`BalancearTejido`, `DividirTejido`) que no extienden `Controller` y no viven en `Services/`. PSR-4 en Linux: `funciones/draganddroptejido.php` declara `class DragAndDropTejido` — el autoload de Composer (`App\\` → `app/`) **no** resolverá ese archivo por nombre de clase. Si algo funciona, es porque otro archivo lo `require`/`use` de forma frágil.

### 1.3 Typos y duplicados de filesystem (trampas reales)

| Problema | Ruta |
|----------|------|
| `catalagos` (debería catalogos) | `resources/views/catalagos/` (21 blades). Modelos `CatalagoEficiencia` / `CatalagoVelocidad` → tablas `catalago_*`. |
| Dos copias del mismo modelo | `app/Models/UrdEngomado/UrdEngNucleos.php` y `app/Models/urdengomado/UrdEngNucleos.php`, **mismo namespace**. En Linux PSR-4 carga **solo** `UrdEngomado/`. Editar la copia minúscula es editar un muerto. |
| Dos `MecActividadesController` | `mecanicos/MecActividadesController.php` (**sin** `userCan`) vs `mecanicos/Catalogos/MecActividadesController.php` (**con** `userCan('crear', 'Actividades Mecanicos')`). Las rutas usan el de `Catalogos`. El otro es un backdoor si alguien lo vuelve a enlazar. |
| `sqlsrv_tow_pro` | Configurado en `config/database.php`. **Cero** usos en `app/`. Refacciones usa `sqlsrv_tow_tow` (`RefaccionesParoService`). Docs mienten. |
| Caché de menú | `ModuloService::CACHE_PREFIX = 'modulos_v3'`. `docs/documentacion-modulos/README.md` sigue hablando de `modulos_v2`. |

### 1.4 AX no es un puerto: son 21 archivos pegándole a `sqlsrv_ti`

Hotspots: `LiberarOrdenesController`, `CatCodificacionController`, `CatLMatController`, `TrazabilidadFlogsService`, `BomMaterialesService`, `EngProduccionFormulacionController`. Un timeout de login en `TI_PRO` no tumba el ERP entero: **rompe liberar, L.Mat, flogs, fórmulas** y deja el resto “verde”. No hay capa anti-corrupción. Los `catch (\Exception $e) { /* silenciar */ }` de BomName en `LiberarOrdenesController` L607–609 **tragan** el fallo de AX y siguen.

---

## 2. Coherencia de flujos de negocio

Cadena que el negocio cree que existe:

`Planeación (ReqProgramaTejido) → Liberar → CatCodificados / AX → Programa Urd/Eng → Producción Urdido → Producción Engomado → Atadores → Tejido/Tejedores → Crudo/Trazabilidad`  
y en paralelo `Mantenimiento/Mecánicos` sobre las mismas máquinas.

### 2.1 Liberar órdenes → CatCodificados (núcleo podrido)

Rutas: `programa-tejido.liberar-ordenes` y el espejo `muestras.liberar-ordenes` (`routes/modules/planeacion.php` L204–211 y L272–279). Mismo controller, tablas distintas vía `ProgramaTejidoContext` (`planeacion/muestras*` → `MuestrasPrograma`).

**Verificado en código:**

- `bomId` / `bomName` **required** (`LiberarOrdenesController` L253–254, mensajes L275–276).
- `CodigoDibujo` opcional; `actualizarCatCodificados` no pisa si no hay valor resuelto.
- `ReqProgramaTejido` castea `Prioridad` a `string` (L188). El Blade de liberar **no** usa `empty()` sobre prioridad (comentario L98).
- Repeticiones: `(int) $v` = trunc hacia cero (`repeticionesDesdePesoRollo`).
- **Marbetes: el código hace `ceil`, no `ROUND`.** `saldoMarbeteDesdeFormula` L2084–2086. `AGENTS.md` dice `REDONDEAR`. El comentario del propio método (L2068–2070) admite que Observer + `SaldoMarbeteCodificacionService` **tienen que techar igual** o se pisan. Tres sitios, una fórmula frágil, documentación contradictoria.

### 2.2 Programa Urdido / Engomado: una regla, tres implementaciones

Contrato canónico: `App\Support\Programas\ProgramaConfig::STATUS_BLOQUEADOS_CON_AX_PRODUCCION` = `Cancelado`, `Programado`, `En Proceso`. `Parcial` queda fuera. Tests: `ProgramarUrdidoActualizarStatusAxTest`, `ProgramarEngomadoActualizarStatusAxTest`, `ProgramBoardActionServiceTest`.

| Regla | Urdido legacy `actualizarStatus` | Engomado legacy `actualizarStatus` | Livewire `ProgramBoardActionService` |
|-------|----------------------------------|------------------------------------|--------------------------------------|
| Bloqueo AX=1 | Sí (`jsonSiAxBloqueaEstatus`) | Sí (copia) | Sí L97–105 |
| Máx. 2 En Proceso por carril | Sí L636–653 | **No** en el POST de status | Sí L247–249 |
| Urdido Finalizado antes de Engomado En Proceso | N/A | **No** | Sí L207–219 |
| Cancelar urdido cancela engomado + borra prod | Sí L676–687 | Solo borra prod engomado | Sí L252–273 |

La vista default de engomado (`resources/views/modulos/engomado/programar-engomado.blade.php` ~L751) bloquea “Ir a producción” si `urdido_finalizado !== true`. **El combo de status no pasa por ese JS.** `verificarOrdenEnProceso` (controller L298) además **falla abierto**: sin `maquina_eng` o sin número de tabla, responde “se permite cargar” (L323–328, L336). Y **ninguna vista engomado llama esa ruta**.

### 2.3 Mover / Finalizar / Desarrolladores

- **Finalizar** (`FinalizarOrdenesController`): escribe `FechaFinaliza` y sincroniza Cat. Preferencia de producto: no confirmar si producción es 0 — vive sobre todo en JS de `planeacion/utileria/finalizar-ordenes.blade.php`, no hay test de controller.
- **Mover** (`MoverOrdenesController` L595–606): al cambiar salón sincroniza Cat y **puede poner `FechaFinaliza` a null** (ver riesgo #5).
- **Desarrolladores:** lógica en `Tejedores/Desarrolladores/Funciones/*Service.php` — servicios **dentro de Controllers**. `CatCodificadosDesarrolladorService::applyPayload` L29–48 omite texto no numérico en columnas FLOAT (p. ej. `600/1T`). `CatCodificados` **no castea** `CalibreComb1`–`5`; el guardado depende de que **ese** service filtre. Cualquier otro writer (liberar, Excel, Livewire `Captura`) puede mandar el nvarchar al FLOAT y SQLSTATE.

### 2.4 Atadores

`AtadoresController` (~1 200 LOC) + `OeeAtadoresFileService` (**3 097** LOC, el PHP más largo del repo). Ciclo Activo→…→Autorizado sobre `TejInventarioTelares` / montado. Aislado de AX en el slice revisado. El OEE es un segundo ERP embebido en un “service”.

### 2.5 Crudo / Trazabilidad

Mejor capa de servicios del repo (`app/Services/Trazabilidad/*`, `app/Services/Crudo/*`) + Livewire. Aun así: filtro Color **solo** rollos teñido (`TrazabilidadProduccionService`); Flogs pega a `sqlsrv_ti`; el scroll real está en `main.app-main` (layout), no en `body` — ya bitió bugs de `overflow:hidden`. Dos stacks UI (Blade mega + Livewire + TS en `resources/js/trazabilidad/`).

### 2.6 Mantenimiento / paros

`GET /api/mantenimiento/paros` (`MantenimientoParosController::index` L696+):

- Sin query: `Depto` = área del usuario; área vacía → `whereRaw('1 = 0')`.
- Default `Estatus = Activo`; `incluir_finalizados=1` abre ventana de días (default 30, máx. 365) **más** los Activo.
- `alcance=todos` / `depto=` con catálogo `SysDepartamentos` (si el depto no existe → vacío).
- Tejedores: `aplicarRestriccionTelaresOperadorSiCorresponde` recorta `MaquinaId`.

`store` valida duplicado **dentro** de la transacción (`hayActivoEnMaquina`, L507). **No existe** `GET api/mantenimiento/paros/validar-duplicado`. `AGENTS.md` y preferencias aprendidas están **mal**. `nuevo-paro` no referencia esa ruta.

**ID mágico:** `departamentos()` L61 `if ($userId === 6)` restringe a Urdido/Engomado. No es configuración: es un empleado hardcodeado.

`Route::view` para solicitudes y reporte (`mantenimiento.php` L19, L23): **la misma Blade, dos URLs, cero controller**, cero permiso.

---

## 3. Duplicación

### 3.1 Urdido vs Engomado (copy-paste industrial)

- Boards: ~58–74% de similitud controller/vista (conteo de auditoría previa). `jsonSiAxBloqueaEstatus` **copiado**.
- Exports `BpmUrdidoExport` vs `BpmEngomadoExport` ≈ 99% idénticos. Igual `ReporteResumenSemanalUrdidoExport` / `Engomado`.
- `ReportesUrdidoController` 1 652 LOC vs `ReportesEngomadoController` 462: mismo patrón, urdido acumuló basura.
- Producción: `ProduccionTrait` (902 LOC) comparte tope; urdido override `maxKgNetoAllowed() = 700` (`ModuloProduccionUrdidoController` L41–44); engomado override `maxKgBrutoAllowed() = 2000`. Defaults del trait = `null` (sin tope). Un tercer módulo que use el trait **nace sin límite**.
- Calificar julios: engomado tiene controller + **dos** partials (`modal-calificar-julios` y `…-julios-eng`). Urdido embebe julios en `produccion/_scripts.blade.php` (2 268 LOC).

### 3.2 Dos codificaciones y un L.Mat

| Stack | Controller | Vista | Tabla |
|-------|------------|-------|-------|
| “Modelos” legacy | `CodificacionController` (1 279) | `catalagos/catalogoCodificacion.blade.php` (2 834) | `ReqModelosCodificados` |
| “Cat” actual | `CatCodificacionController` (1 014) | `catcodificacion/` | `CatCodificados` |

Nombres de ruta **parecidos, no colisionan** (Laravel sí prefixea): `planeacion.catalogos.codificacion.all-fast` vs `planeacion.codificacion.all-fast`. Quien escriba `route('codificacion.all-fast')` se equivoca. Excel duplicado (`codificacion.excel` en ambos grupos).

Muestras: 11 pares de rutas programa ↔ muestras (`PlaneacionProgramaMuestrasRouteParityTest`). No es DRY: es **duplicar superficie HTTP** para no parametrizar el prefijo.

### 3.3 Reportes

31 clases en `app/Exports/`. Ceremonia PhpSpreadsheet (`WithEvents` / `AfterSheet`) copiada. `ReportesUrdidoController` es un god-object de reportes. El patrón “controller gordo + export casi clon” se repite en tejido (RPM semanal **nombrado** `tejido.reportes.inv-trama` — el nombre de ruta es de otro reporte).

### 3.4 Secuencias de tejido

`corte-eficiencia` / `marcas-finales` / `inv-telas` / `inv-trama` bajo `modulos/tejido/secuencia/` son el mismo esqueleto `fetch`-pesado. Cualquier fix de CSRF o error handling hay que pegarlo cuatro veces.

---

## 4. Fat controllers, lógica en Blade/JS, permisos

### 4.1 Dónde está la verdad (spoiler: no en Services)

~24% de los controllers importan `App\Services\`. El resto **es** la capa de dominio.

**Controllers / “funciones” > 1 000 LOC (muestra):**

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

`@php` aparece en ~124 blades. 20 Form Requests para 139 controllers: la validación está **inline** o en el navegador.

### 4.2 HTTP y notificaciones: la migración no ocurrió

En `resources/views`:

| API | Usos aprox. |
|-----|------------:|
| `fetch(` | **250** |
| `http.get/post/…` | **37** |
| `Swal.` | **976** |
| `notify.` | **29** |

`AGENTS.md` dice preferir `window.http` / `window.notify` (`resources/js/utils/http.js`, `notifications.js`). Calendarios es el piloto. Producción urdido/engomado, atadores, liberar, cortes, inventario trama siguen en `fetch` + SweetAlert. `notify` escapa HTML; `Swal` con HTML crudo **no**. XSS de mensaje de servidor → modal es el camino probable, no teórico.

`resources/js/programa-tejido/index.js` supera las 7 000 líneas. Es el verdadero controller del programa de tejido.

### 4.3 Permisos: cerebro partido + agujero público

```
SYSRoles.reigstrar     (typo, plantilla del módulo)
SYSUsuariosRoles.registrar  (permiso real por usuario)
userCan('registrar', $modulo)  → lee la columna correcta
```

Puente: `ModulosController::actualizarPermisosNuevoModulo` L545. `togglePermiso` L468 solo acepta `reigstrar` sobre **SYSRoles**, no sobre el usuario.

Caché: `modulos_v3` + `APP_ENV`. Si no llaman `ModuloService::limpiarCacheUsuario()` el menú miente. El menú **no autoriza** las APIs.

Grupo público:

```25:30:routes/public.php
Route::prefix('modulos-sin-auth')->name('modulos.sin.auth.')->group(function () {
    Route::get('/', [ModulosController::class, 'index'])->name('index');
    Route::post('/', [ModulosController::class, 'store'])->name('store');
    …
});
```

También público: `GET /obtener-empleados/{area}` (`public.php` L19–20) — enumeración de plantilla. CSRF del grupo `web` aplica a POST, pero el GET entrega el formulario de gestión de módulos a un anónimo.

`ForceHttps` está **comentado** en `bootstrap/app.php` L23–24.

Login: `AuthController` L44–46 compara plaintext con `hash_equals` y rehash a bcrypt. Correcto como migración; las contraseñas legacy **siguen en claro en SQL Server** hasta el próximo login de cada usuario. `Auth::login($empleado, true)` L60: remember-me **siempre** (kioscos / andón). Robo de cookie = sesión larga.

---

## 5. Footguns verificados (no confiar en AGENTS.md)

| Afirmación (AGENTS / “learned”) | Código real |
|---------------------------------|-------------|
| `GET …/validar-duplicado` antes del POST de paros | **No está en** `routes/modules/mantenimiento.php`. Solo `store` + `hayActivoEnMaquina`. |
| Saldo marbete = `REDONDEAR` | `saldoMarbeteDesdeFormula` usa **`ceil`**. |
| `FechaFinaliza` no se toca al mover | Se puede **poner null** (flag default `true`). |
| CreaProd seguro en reimprimir | Felpa sí; **liberar no**. |
| Engomado exige urdido finalizado | Solo botón “Ir a producción” + Livewire. **No** el POST de status. |
| Caché menú `modulos_v2` | Código: **`modulos_v3`**. |
| `routes/ai.php` | **No existe**. |
| `% L.Mat exactamente 100` en front y back | Front sí (`lmat-modal.js` L2124). **Backend no.** |
| `empty()` en Prioridad | Arreglado en liberar. Sigue siendo trampa en cualquier Blade nuevo. |
| Calibres float vs `600/1T` | Filtrado en `CatCodificadosDesarrolladorService`. Otros writers no. |
| AX=1 bloquea Cancelado/Programado/En Proceso; Parcial OK | **Verificado** y testeado. Esta es de las pocas reglas consistentes. |
| L.Mat `ITEMGROUPID=CRUDO`, salones SMIT/JACQUARD(+JACUARD) | Sí, vía `TelarSalonResolver`. El **cómo** se consulta **diverge**. |

Otros:

- `LiberarOrdenesController` L597 usa `empty($registro->BomName)` — mismo bug de `"0"` si algún día un BomName fuera `"0"`. Menor.
- `queryLmatDesdeTi` `limit(50)`: un artículo con muchas versiones de BOM **corta la lista** que el select de Peso muestra enseña.
- `ProgramaTejidoContext` es middleware **global** web (`bootstrap/app.php` L37). Cualquier request `planeacion/muestras*` cambia `config('planeacion.programa_tejido_table')` **para ese request**. Un helper que cachee el nombre de tabla a nivel proceso está un bug de lejos.
- Observaciones truncadas en `ReqProgramaTejido` `saving` (`StringTruncator`) — evita SQLSTATE 22001 **perdiendo texto en silencio**.

---

## 6. Deuda de UI / diseño

### 6.1 Tres generaciones de frontend conviven

1. **jQuery + Blade monolítico + SweetAlert + `fetch`** (urdido, engomado, atadores, liberar, catálogos).
2. **Vite entrypoints de dominio** (`resources/js/programa-tejido/index.js`, `catcodificacion/`, `urd-eng/*.ts`, `trazabilidad/*.ts`, `crudo/*.ts`).
3. **Livewire 4** (Crudo, Trazabilidad, ProgramBoard, Captura desarrolladores, catálogo fallas). Solo ~6 blades referencian Livewire.

No hay design system. Hay `components/ui/{button,modal-base,alert}` y a la vez HTML crudo + Tailwind ad hoc en cada módulo. Carpetas de vistas: `modulos/`, `catalagos/`, `catcodificacion/`, `planeacion/`, `catalogosurdido/`.

### 6.2 Rutas legacy y nombres mentirosos

- Redirects 301 en `planeacion.php` (`programatejido`, `utilera`, `codificacionmodelos`, …) y `tejido.php`.
- `/urdido/programaurdido` y `/urdido/programar-urdido` → mismo index; extra `/programar-urdido/legacy` y Livewire en otra URI.
- RPM semanal: URI `/tejido/reportes/rpm-semanal`, **name** `tejido.reportes.inv-trama`.
- Programa tejido index: URI `/planeacion/programa-tejido`, **name** `catalogos.req-programa-tejido`.
- Configuración: endpoint duplicado `GET /configuracion/utileria/api/modulos/nivel/{nivel}` (`configuracion.utileria.api.modulos.nivel`) y `GET /api/modulos/nivel/{nivel}` (`api.modulos.nivel`) — mismo método, dos URLs. No es colisión de `route()`, es superficie doble.

### 6.3 CSS / Vite / caché

Comentario en producción, no en un ticket:

```337:342:resources/views/modulos/programa-tejido/req-programa-tejido.blade.php
{{-- ?v=filemtime obligatorio: .htaccess le pone un ano de expiracion al CSS y
     estos dos no pasan por Vite, asi que sin esto el navegador sigue sirviendo el
     de antes. … un main.css cacheado deja la tabla sin tamano ni padding
     y la pagina se ve "con zoom". --}}
```

`NoCacheHtmlResponses` pone `Cache-Control` en HTML, **no** en `/css/programa-tejido/*.css`. Laragon sirviendo `public/build` sin `npm run build` deja JS de `resources/js` viejo (documentado en AGENTS.md). Resultado: “arreglé el modal y en planta no se ve”.

Paros: CSS inline de `<dialog>` porque el variant de Tailwind v4 **no compilaba** (comentario en `reporte-fallos-paros/index.blade.php`). Workarounds apilados.

### 6.4 Accesibilidad / UX (lo visible en vistas)

- 976 `Swal.` vs dialogos nativos: foco, ESC, etiquetas ARIA inconsistentes. El reporte de paros **sí** documenta `<dialog>` + `aria-labelledby` — excepción, no patrón.
- Botones de menú contextual en programa tejido (`req-programa-tejido.blade.php` L321–332) sin `type="button"` en varios: en un form heredado disparan submit.
- Permisos en UI: `userCan` en ~10–14 blades. El resto muestra acciones que el API a veces ni siquiera 403-ea.
- Tablas de miles de filas pintadas en el DOM (comentario explícito en `index` de paros L709–711). Incluir finalizados = riesgo de congelar el navegador.
- Mezcla de selects nativos, Select2 y combos a mano. Calendarios migró a `http`; el operador cruza módulos y el comportamiento de error cambia.

---

## 7. Huecos de test

`phpunit.xml` fuerza `DB_CONNECTION=sqlite` / `:memory:` **a propósito**: sin eso la suite pega a SQL Server real **sin** `RefreshDatabase` (comentario L25–30). `UsesSqlsrvSqlite` rebindéa `sqlsrv` y crea esquemas mínimos. Eso es inteligente y **peligroso**: tipos FLOAT/NVARCHAR, collations y `lockForUpdate` de SQL Server **no** se ejercitan.

| Flujo crítico | ¿Hay test que mute estado? |
|---------------|----------------------------|
| Liberar órdenes | Sí: `tests/Feature/LiberarOrdenesLiberarTest.php` (+ units de fórmulas). No cubre `CreaProd` en Cat existente. |
| AX status urd/eng | Sí (unit, controllers + service). |
| Program board Livewire | Sí: `ProgramBoardActionServiceTest`, `ProgramBoardLivewireTest`. |
| Mover órdenes | **No** el controller (608 LOC). Solo `MovimientoOrdenBloqueoTest` (orden de locks) y contrato de rutas. |
| Finalizar órdenes | **No** comportamiento (415 LOC). Ruta en contract test. |
| Guardar L.Mat | **No** `CatLMatController`. `MatrizCalibresServiceTest` menciona `guardarLmat` de pasada. |
| Paros `store` / duplicado | `MantenimientoParosControllerTest`: máquinas / filtro calidad. **No store.** |
| Trazabilidad | Varios unit de estructura/layout/Livewire. Poco HTTP+TI. |
| Captura fórmula 3 589 LOC | `EngProduccionFormulacionControllerTest` del controller; Blade JS = 0. |
| Utilería / alineación | `AlineacionControllerTest` (rangos). Utilería = rutas. |

Muchos tests `*StructureTest` / `*MarkupTest` / `*JsGlobalsTest` **leen archivos como texto**. Útiles como humo, inútiles como regla de negocio.

143 tests para ~59k LOC de controllers + ~94k de vistas es **teatro de cobertura**. Lo que está testeado coincide con dolor reciente (liberar, board AX, crudo, trazabilidad grouping), no con el radio de explosión.

---

## 8. Mapa de “una regla, N copias” (para no olvidar)

```
Liberar.bomCrudoQuery  ──┐
                         ├──► BOMTABLE+BOMVERSION CRUDO  (resultados distintos)
CatCodificacion.queryLmatDesdeTi ─┘

ProgramaConfig.estatusBloqueadoPorAx
  ├─ ProgramarUrdidoController.jsonSiAxBloqueaEstatus
  ├─ ProgramarEngomadoController.jsonSiAxBloqueaEstatus
  └─ ProgramBoardActionService.changeStatus

CreaProd
  ├─ LiberarOrdenesController: siempre 1 (programa + Cat)
  ├─ OrdenDeCambioFelpaController: solo alta
  └─ ProcesarDesarrolladorService: copia el del programa

FechaFinaliza
  ├─ FinalizarOrdenesController / ProcesarDesarrollador (accion=finalizar): set
  └─ MoverOrdenesController: puede null

% L.Mat = 100
  ├─ lmat-modal.js: bloquea
  └─ CatLMatController: no
```

---

## 9. Qué no hacer ahora

No extraer `LiberarOrdenesService` “porque SOLID”. No unificar Urdido/Engomado en un mega-refactor. No renombrar `catalagos` ni `reigstrar` sin migración de BD y de cada Blade. Esas movidas **rompen planta** y esta auditoría no las pide.

Sí hay cambios **pequeños y de alto valor** (por prioridad del top 10): cerrar `modulos-sin-auth`, poner middleware de permiso en rutas, dejar de escribir `CreaProd` en update, unificar el POST de status de engomado con el service, no tocar `FechaFinaliza` al mover, una query L.Mat, validar 100% en backend. Cada uno cabe en un PR con un feature test. Eso es el trabajo; el resto es arqueología.

---

## 10. Apéndice: archivos ancla

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

*Auditoría de lectura. Cero cambios de comportamiento en esta entrega.*
