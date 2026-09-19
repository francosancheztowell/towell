# Inventario vivo — bugs / arquitectura crítica (Towell ERP)

**Propósito:** backlog priorizado, honestidad sobre evidencia. No es un changelog ni un plan de sprint.
**Fuente base:** `docs/auditoria/auditoria-critica-towell.md` (PR [#32](https://github.com/francosancheztowell/towell/pull/32)) + verificación puntual en `main` / rama de auditoría (2026-09-18).
**Última actualización:** 2026-09-19 (BUG-006/008 mitigados: FechaFinaliza al mover + L.Mat % = 100 en backend).
**Reglas:** no inventar bugs sin path/símbolo. `confirmado` = leído en código; `hipótesis` = plausible pero no cerrado en esta pasada.

---

## Resumen rápido

| Sev | Cantidad (esta nota) |
|-----|---------------------:|
| P0  | 3 |
| P1  | 12 |
| P2  | 9 |
| P3  | 4 |
| **Total** | **28** |

---

## Tabla maestra

| ID | Sev | Área | Título | Evidencia | Impacto | Estado | Notas |
|----|-----|------|--------|-----------|---------|--------|-------|
| BUG-001 | P0 | Auth / Config | CRUD módulos sin autenticación | `routes/public.php` grupo `auth` (URI histórica `/modulos-sin-auth`, names `modulos.gestion.*`) → `ModulosController::{index,store,update,destroy}`. Guest test: `tests/Feature/PublicSensitiveRoutesAuthTest.php` | Quien alcance la URL administra menú y plantillas de permiso | mitigado | 2026-09-18: exige sesión. Nombres `modulos.sin.auth.*` eliminados. No hay middleware de módulo en el router (`userCan` no se inventó). URI conservada para clientes logueados. PR [#33](https://github.com/francosancheztowell/towell/pull/33). |
| BUG-002 | P0 | Auth | `GET /obtener-empleados/{area}` sin auth | `routes/public.php` grupo `auth` → `UsuarioController::obtenerEmpleados` (`usuarios.obtener-empleados`). Guest test: `tests/Feature/PublicSensitiveRoutesAuthTest.php` | Filtración de nómina/empleados por área sin sesión | mitigado | 2026-09-18: `GET /obtener-empleados/{area}` exige sesión (302 login / 401 JSON). PR [#33](https://github.com/francosancheztowell/towell/pull/33). |
| BUG-003 | P0 | AuthZ | APIs solo con `auth`; menú ≠ autorización | Esqueleto Planeación: `EnsureModulePermission` (`module.permission`) en POST liberar (programa-tejido + muestras), `planeacion.lmat.guardar`, mover/finalizar utilería. Tests: `tests/Feature/PlaneacionMutationAuthorizationTest.php`. Paros/Mantto: AuthZ `userCan` **revertida** (PR #37); Engomado/Urdido y GETs planeación siguen solo `auth`. | Usuario autenticado sin módulo ya no pega a liberar/L.Mat/mover; paros y resto ERP siguen abiertos | mitigado (esqueleto Planeación) | 2026-09-18: módulos SYSRoles **Programa Tejido** (`crear`), **Codificación** (`modificar`), **Utilería** (`modificar`, con acento — verificado en LA-GTECLAVE). AuthZ ERP completa abierta. No Livewire Liberar ni P1. PR [#36](https://github.com/francosancheztowell/towell/pull/36). |
| BUG-004 | P1 | Planeación / AX | Liberar fuerza `CreaProd = 1` (re-encola AX) | `LiberarOrdenesController` L613 y payload L1381; contraste `OrdenDeCambioFelpaController` omite `CreaProd` en update | Re-liberar vuelve a encolar producción en AX | confirmado | No tocar `CreaProd` si el row ya existe. Test de no-regresión. |
| BUG-005 | P1 | Engomado | `actualizarStatus` → En Proceso sin exigir Urdido Finalizado | Mitigado: `ProgramarEngomadoController::actualizarStatus` delega a `ProgramBoardActionService::changeStatus`. Guardia en `productionBlockReasonForOrder` (Urdido `Status === Finalizado`). Feature: `tests/Feature/ProgramBoardStatusGuardsTest.php` | POST legacy ya no salta la regla | mitigado | 2026-09-18. `verificar-en-proceso` sigue sin usarse en vistas (BUG-017). |
| BUG-006 | P1 | Utilería | Mover puede anular `FechaFinaliza` | Mitigado: `MoverOrdenesController::sincronizarCatCodificados` pasa `actualizarFechaFinaliza: false`. Feature: `tests/Feature/MoverOrdenesFechaFinalizaTest.php` | Ya no se anula al sincronizar CatCodificados por cambio de salón | mitigado | 2026-09-19. `FechaFinaliza` solo se sella al finalizar (utilería / tejedores). |
| BUG-007 | P1 | L.Mat / AX | Dos SQLs incompatibles L.Mat CRUDO → AX | `LiberarOrdenesController::bomCrudoQuery` L2295–2319 (`whereExists` + filtro salón) vs `CatCodificacionController::queryLmatDesdeTi` L537–562 (`JOIN BOMVERSION`, `limit(50)`, sin salón) | Mismo ítem/size puede listar BOMs distintos; select truncado | confirmado | Unificar en `LmatCrudoQueryService`. Quitar `limit(50)`. |
| BUG-008 | P1 | L.Mat | % total = 100 solo en frontend | Mitigado: `CatLMatController::validarSumaPorcentajes` exige suma === 100 antes de AX. Feature: `tests/Feature/CatLMatGuardarPorcentajeTest.php` | POST directo con % ≠ 100 ahora 422 | mitigado | 2026-09-19. El modal ya bloqueaba; el API ahora también. |
| BUG-009 | P1 | Arquitectura | Fat controller LiberarOrdenes ~2941 LOC | `app/Http/Controllers/Planeacion/ProgramaTejido/LiberarOrdenesController.php` (wc = 2941) | Imposible testear/revisar; mezcla AX, Cat, BOM, marbetes | confirmado | Nota: se citaba ~2537; en `main` actual es **2941**. Extraer servicios. |
| BUG-010 | P1 | Arquitectura | ~139 controllers vs ~54 services | Conteo árbol `app/Http/Controllers/*.php` = 139; `app/Services/*.php` = 54 | Dominio vive en controllers; services son excepción (~24% importan Services) | confirmado | Deuda estructural, no bug puntual. |
| BUG-011 | P1 | Urd/Eng | Fork legacy Blade vs Livewire (reglas distintas) | Default UI = Livewire `ProgramBoard` (`index()` → `programar-*-livewire`). POST `actualizar-status` / prioridad / observaciones delegan a `ProgramBoardActionService`. Blade queda en `/legacy` con las mismas reglas. Tests: `ProgramBoardStructureTest`, `ProgramBoardStatusGuardsTest`, `ProgramBoardRouteContractTest` | Una verdad de mutación; Blade solo fallback | mitigado | 2026-09-18. `actualizarCalidad` / `marcarIncorrecto` / bulk prioridades siguen en controller (sin equivalente de status en el service). |
| BUG-012 | P1 | Codificación | Dual `ReqModelosCodificados` vs `CatCodificados` | `CodificacionController` + `catalagos/catalogoCodificacion.blade.php` vs `CatCodificacionController` + `catcodificacion/` | Dos superficies HTTP, Excel duplicado, rutas parecidas | confirmado | Decinir canónico; congelar legacy. |
| BUG-013 | P1 | Docs / Paros | `AGENTS.md` inventa ruta `validar-duplicado` | `AGENTS.md` + docs módulo 11; `routes/modules/mantenimiento.php` **no** declara GET `validar-duplicado`; controller **no** tiene `validarDuplicado` | Front/docs mienten; `store` sí valida via `hayActivoEnMaquina` (L507) | confirmado | Corregir AGENTS/docs o añadir ruta. Vista `nuevo-paro` no llama esa URL. |
| BUG-014 | P1 | Integraciones | Hotspots `sqlsrv_ti` sin anti-corrupción | Liberar / CatCodificacion / CatLMat / Flogs / BOM (auditoría §1.4: ~21 archivos) | Timeout TI_PRO rompe liberar+L.Mat+flogs; resto “verde” | confirmado | Capa puerto + circuit breaker; no silenciar catch BomName L607–609. |
| BUG-015 | P1 | Paros / Mecánicos | `RefaccionesParoService` usa `sqlsrv_tow_tow` | `RefaccionesParoService::CONEXION = 'sqlsrv_tow_tow'`; `config/database.php` L140–152 **sí** declara la conexión (env `DB_*_TOW_TOW`) | Si `.env` carece de vars → fallos en runtime al listar refacciones | hipótesis | Conexión ya no “falta” en config; falta verificar `.env` / `.env.example` en cada entorno. |
| BUG-016 | P2 | Urd/Eng | Engomado legacy sin límite 2× En Proceso | Mitigado: `ProgramBoardActionService::productionBlockReasonForOrder` aplica tope 2× En Proceso por lane también a Engomado (`MaquinaEng` / West Point). POST `actualizar-status` delega al service. Feature: `ProgramBoardStatusGuardsTest::test_engomado_respects_max_two_en_proceso_per_machine` | Misma regla Urdido/Engomado/Livewire/legacy | mitigado | 2026-09-18. GET `verificar-en-proceso` de Engomado sigue fail-open (BUG-017); ya no es el camino de mutación. |
| BUG-017 | P2 | Engomado | `verificarOrdenEnProceso` falla abierto + no se usa | Controller L298–336; auditoría: ninguna vista engomado llama la ruta | Falsa sensación de guardrail | confirmado | Eliminar o cablear; no fallar abierto. |
| BUG-018 | P2 | UI / HTTP | Migración `fetch`→`window.http` incompleta | Auditoría en blades: `fetch(` ~250, `http.*` ~37, `Swal.` ~976, `notify.` ~29. User citaba ~323/~69 (posible conteo más amplio JS+blade) | CSRF/errores/XSS via Swal HTML crudo se arreglan N veces | confirmado (órdenes de magnitud) | Preferir `window.http`/`notify` per AGENTS. Recontar en máquina local si hace falta precisión. |
| BUG-019 | P2 | Menú | Cache key `modulos_v3` vs docs `modulos_v2` | `ModuloService` `CACHE_PREFIX = 'modulos_v3'`; CLAUDE.md / docs módulo 12 aún dicen `modulos_v2` | Docs mienten; riesgo de stale mental model al limpiar caché | confirmado | Actualizar docs; siempre `limpiarCacheUsuario()` post-permisos. |
| BUG-020 | P2 | FS / Vistas | Typo congelado `resources/views/catalagos/` | Árbol git: `resources/views/catalagos/**` | Rutas Blade y links frágiles; rename rompe | confirmado | Deuda; no renombrar sin plan. |
| BUG-021 | P2 | Mecánicos | Dos `MecActividadesController` | `app/Http/Controllers/mecanicos/MecActividadesController.php` y `.../Catalogos/MecActividadesController.php` | Ambigüedad de mantenimiento / imports | confirmado | Unificar o renombrar. |
| BUG-022 | P2 | Modelos | `UrdEngNucleos` duplicado (case paths) | `app/Models/UrdEngomado/UrdEngNucleos.php` vs `app/Models/urdengomado/UrdEngNucleos.php` | En Linux/CI el autoload puede divergir de Windows | confirmado | Un path canónico. |
| BUG-023 | P2 | Tests | Mutaciones críticas sin Feature tests | Ya hay Feature de `MoverOrdenesController` (FechaFinaliza) y `CatLMatController::guardarLmat` (% = 100). Siguen faltando `FinalizarOrdenesController` y happy-path de paros | Cobertura parcial; finalizar/paros aún sin Feature de negocio | confirmado | 2026-09-19: mover + guardarLmat cubiertos. Falta finalizar. |
| BUG-024 | P2 | Atadores | `OeeAtadoresFileService` ~3097 LOC | `app/Services/OeeAtadores/OeeAtadoresFileService.php` | Segundo ERP embebido; riesgo de cambio | confirmado | Partir por bounded use-case. |
| BUG-025 | P3 | Paros | UserId mágico `=== 6` en departamentos | `MantenimientoParosController::departamentos` L61 | Permiso hardcodeado a un empleado | confirmado | Config/rol, no ID. |
| BUG-026 | P3 | Auth | `Auth::login(..., true)` remember-me siempre | `AuthController` (auditoría Top10 #10) | Sesiones eternas en PCs compartidas | hipótesis | Verificar política de planta. |
| BUG-027 | P3 | Docs | `AGENTS.md` dice marbetes `ROUND`; código hace `ceil` | `LiberarOrdenesController::saldoMarbeteDesdeFormula` L2084–2086; AGENTS menciona REDONDEAR | Desarrolladores “arreglan” el lado equivocado | confirmado | Alinear docs o código + Observer. |
| BUG-028 | P3 | Docs | `routes/ai.php` citado en docs, no existe | Auditoría §1.1; bootstrap solo `web`+`api` | Onboarding confunde agentes | confirmado | Borrar mención o crear stub intencional. |

---

## Slice Mantto de BUG-003 (revertido 2026-09-18)

Franco pidió abrir paros a cualquier sesión autenticada. Se revirtió el gate de PR [#34](https://github.com/francosancheztowell/towell/pull/34):

- `POST /api/mantenimiento/paros` y `PUT /api/mantenimiento/paros/{id}/finalizar` ya no llaman `userCan`.
- Guest sigue 401/302 (`auth` en la ruta). El menú puede ocultar la UI; el API no bloquea por módulo **Solicitudes**.
- Slice Planea (liberar / L.Mat / mover) no se toca. Fuera de este slice: `userId === 6` en `departamentos()` (BUG-025).

---

## Hallazgos nuevos de esta pasada (además de la lista conocida)

1. **Liberar LOC real = 2941** (no ~2537) — BUG-009.
2. **`sqlsrv_tow_tow` ya está en `config/database.php`** — el hallazgo “missing connection” baja a hipótesis de `.env` (BUG-015).
3. **`store` de paros SÍ valida duplicado**; falta solo la ruta GET documentada (BUG-013 matizado).
4. **Docs menú aún en `modulos_v2`** mientras código usa `v3` (BUG-019).
5. **Dual `MecActividadesController`** y **dual path `UrdEngNucleos`** confirmados en árbol (BUG-021/022).
6. **BUG-003 esqueleto Planeación (2026-09-18):** `EnsureModulePermission` cubre liberar / L.Mat / mover / finalizar. Paros, Engomado/Urdido y el resto de mutaciones autenticadas siguen abiertas. AuthZ ERP completa **sigue abierta**.

---

## Cómo mantener vivo este inventario

1. Al confirmar/fijar un item: cambiar `Estado` → `confirmado` / `mitigado` / `wontfix` y fecha en Notas.
2. Cada PR de auditoría: añadir filas nuevas **solo** con evidencia (path + símbolo).
3. No mezclar wishlist de features aquí — solo bugs/riesgos/deuda que rompe planta o seguridad.
4. Re-verificar en la máquina local (`C:\xampp\htdocs\Towell`) cuando el conteo `fetch`/`axios` deba ser exacto.

---

## Gaps aún sin analizar (próximas pasadas)

- Middleware / policies por módulo (mapa completo de endpoints sin `userCan`). Esqueleto Planeación (liberar / L.Mat / mover) ya no está en cero; falta el resto del ERP.
- Jobs/queues AX: retries, idempotencia, dead letters.
- Livewire `Captura` desarrolladores vs writers Excel/liberar (calibres FLOAT).
- Producto terminado / Telegram / Redbooth (solo `require` en web.php).
- Seguridad XSS vía `Swal` + mensajes servidor (PoC no hecha).
- Cobertura PHPUnit real (no se corrió suite).
- `.env.example` vs conexiones `sqlsrv_*` (secrets no leídos a propósito).
- Drift vault Obsidian ↔ `docs/auditoria/` en git (esta nota vive en vault).

---

## Ampliacion 2026-09-18 (JS / rutas Livewire) — merge desde js-y-frontend-deuda

| ID | Sev | Area | Titulo | Evidencia | Impacto | Estado | Notas |
|----|-----|------|--------|-----------|---------|--------|-------|
| BUG-029 | P2 | Frontend | Migracion window.http estancada (9 vs 339 fetch) | Conteos LA-GTECLAVE | CSRF/errores inconsistentes | confirmado | Ver [[js-y-frontend-deuda]] |
| BUG-030 | P2 | Frontend | SweetAlert domina (1136) vs notify | Swal.=1136 | XSS via HTML en modal plausible | confirmado | PoC XSS = hipotesis |
| BUG-031 | P1 | Engomado | Ruta .legacy sirve Livewire; default index = Blade debil | Mitigado: `ProgramarEngomadoController::index()` → `programar-engomado-livewire`; `legacy()` → Blade `programar-engomado`. Urdido igual (`index` Livewire, `/legacy` Blade, `/livewire` 301 → default). `tests/Unit/Programas/ProgramBoardStructureTest.php` | Default planta = Livewire; “legacy” = Blade | mitigado | 2026-09-18. |
| BUG-032 | P2 | Frontend | programa-tejido/index.js ~13218 LOC | resources/js/programa-tejido/index.js | UI intocable | confirmado | No migrar a Livewire primero |
| BUG-033 | P2 | Programa Urd-Eng | creacion-ordenes.js 1460 LOC fuera de Vite | public/js/modulos/programa_urd_eng/ | Assets sin pipeline | confirmado | |
| BUG-034 | P3 | Livewire | wire:=258 pero @livewire~0 | LA-GTECLAVE | Docs buscan sintaxis vieja | confirmado | |

Resumen actualizado: P0=3, P1=13 (+BUG-031; **005/011/031 mitigados**), P2=13 (+029/030/032/033; **016 mitigado**), P3=5 (+034). Total **34**.

---

## Slice tablero Urd/Eng (BUG-005 / 011 / 016 / 031) — 2026-09-18

Una verdad de mutación de status del tablero:

- UI default: `GET /engomado/programar-engomado` y `GET /urdido/programar-urdido` → Livewire `app/Livewire/UrdEng/ProgramBoard.php` (wrappers `programar-*-livewire.blade.php`). Sin rediseño.
- `GET .../legacy` = Blade clásico. `GET /urdido/programar-urdido/livewire` redirige 301 al default.
- POST `actualizar-status`, `intercambiar-prioridad`, `guardar-observaciones` delegan a `ProgramBoardActionService` (Urdido Finalizado + tope 2× En Proceso por máquina + AX).
- Evidencia tests: `tests/Feature/ProgramBoardStatusGuardsTest.php`, `tests/Feature/ProgramBoardRouteContractTest.php`, `tests/Unit/Programas/ProgramBoardActionServiceTest.php`, `tests/Unit/Programas/ProgramBoardStructureTest.php`.
