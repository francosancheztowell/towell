# Auditoría del owner (2026-09-29) cruzada con el estado real

Fuente: auditoría de solo lectura del owner sobre `main` @ `436d88f7` (29-sep-2026), 920 archivos, sin `resources/views` ni `resources/js`. Cruce hecho por el integrador el 2026-09-30 contra la rama integradora (`claude/friendly-hopper-506bg9`, que ya contiene `main` y toda la Ola 3). Donde el informe y el código de la rama difieren, manda la rama.

## Top 10 → estado

| # | Hallazgo | Estado en la rama | Dónde / quién |
|---|---|---|---|
| 1 | Dependencias con CVE + `composer audit` en CI | ✅ **Hecho**: `composer audit` 56 → 0 (phpspreadsheet 1.30.7, Laravel 12.69.3, Livewire 4.4.7, maatwebsite 3.1.70, guzzle, commonmark, symfony, dompdf); `npm audit` 0; `composer audit` en el CI (bloquea si cambia el lock) | CAL 22-01 + CAL-deps (`04a8442d`) |
| 2 | Salida de SQL Server 2008 R2 → 2019/2022; luego PHP 8.4 y Laravel 13 (L12 seguridad hasta 24-feb-2027; PHP 8.2 hasta 31-dic-2026) | ⏳ **Decisión de infraestructura del owner** | Owner (fecha y presupuesto). Nota: la CLI de producción ya es PHP 8.3.28; el lock tiene piso `platform.php = 8.2.0` |
| 3 | AuthZ en acciones Livewire (`NuevoRequerimiento::$folioActual` sin `#[Locked]`, `resolverFolio` sin validar Status/dueño, `ConsultarRequerimiento::cambiarStatus` sin `userCan`, `/tejido/invtrama` sin `module.permission`, `addPersistentMiddleware`) | ❌ **Pendiente** (19-02 no lo tocó) | Sesión **20-04 AuthZ** (tanda 4, primera) |
| 4 | Gates que envuelvan `userCan()`; quitar AuthZ por puesto "supervisor" (18 usos); `userId === 6`; auditar → enforce | Parcial: `userId === 6` ✅ quitado (19-08). Gates/puesto/enforce ❌ | 20-04 + SEC-06 con datos de `SYSMonAcceso` en producción |
| 5 | 5 `paginate()`/`simplePaginate()` que rompen en 2008 R2 | ✅ **Hecho** 2026-09-30 (`b7114948`) + guardián `SinPaginateNativoTest` | Integrador |
| 6 | `getMessage()` al cliente | En curso: ratchet 270 → **120** en `response()->json`; cada 19-xx baja su módulo | 19-xx (SEC-07) |
| 7 | Paros: `finalizar` con transacción + `lockForUpdate`; auditar store/finalizar | Revisar lo que dejó 19-08 (HANDOFF M4) | 20-04 |
| 8 | `updatePermiso` sin lista blanca; rama de contraseña en texto plano; remember-me siempre; headers de seguridad | `updatePermiso` ❌ → 20-04 (o 19-09). **Contraseñas y login: el owner decidió no tocarlos (2026-09-25)**: la rama de texto plano y el remember-me quedan como están salvo que el owner lo reabra. Headers ❌ → 20-04 (CSP en Report-Only) | 20-04 / owner |
| 9 | Tests: CI con SQL Server real, Pest 4, factories, arch tests, feature tests de Finalizar/paros/InventarioTrama, mutation testing | Parcial: cobertura medida (46 %), guardianes de texto (JS inline, paginate), Infection previsto para PT 05.1. CI con SQL Server ❌, factories ❌, Pest ❌ (pide PHP ^8.3) | CAL 22-08 (SQL Server en CI + phpat) y cada sesión con sus feature tests |
| 10 | Dominio/ORM: enums de status, capa anticorrupción de AX, WHERE sargables, ide-helper, Rector, unificar `actualizarStatus`, BUG-007 | ❌ Pendiente. Política ORM nueva (ver PROJECT 2026-09-30) | 22-09 ide-helper + enums; AX en cada sesión que toque AX; `actualizarStatus` → 19-05 siguiente |

## Números del informe que ya cambiaron

| Métrica | Informe (main 29-sep) | Rama (30-sep) |
|---|---|---|
| Avisos de seguridad | 56 | **0** |
| `paginate()` nativos | 5 | **0** (+ guardián) |
| `getMessage()` en `response()->json` | 270 | **120** |
| `<script>` inline en Blade (ratchet) | 161 | **79** |
| `onclick=` | 372 | **201** |
| `Swal.fire` | 800 | **339** |
| Baseline de phpstan | 3 441 | ~2 220 |
| Livewire | 4.3.3 (CVE) | **4.4.7** |
| Tests PHP | 1 521 métodos | **2 469** tests |

## Inventario del backend (integrador, 2026-09-30, rama)

- **Métodos > 100 líneas: 119** (9 de más de 300). **Clases > 1 000 líneas: 17.**
- Los más largos:
  - `DividirTejido::dividir` 791 · `DuplicarTejido::duplicar` 638 · `AtadoresController::save` 530 · `LiberarOrdenesController::liberar` 506 · `UpdateTejido::aplicarCambios` 461 → **PT 05.1** (salvo `save` de Atadores → 22-06).
  - `DividirTejido::redistribuirGrupoExistente` 396.
  - `CatLMatController::guardarLmat` 381 → **19-06a**.
  - `ReqProgramaTejidoSimpleImport::model` 361 · `ReqProgramaTejidoUpdateImport::procesarFilas` 321 → **PT 05.1**.
  - `ReqModelosCodificadosImport::collection` 291 → **19-06a**.
  - `InventarioTelaresController::verificarEstado` 288 → **19-04**.
- Clases > 1 000: `OrdenDeCambioFelpaController` 1 942 (PT), `CodificacionController` 1 692 (19-06a), `ReportesUrdidoController` 1 572 (22-06), `OrdenesTrabajoMecaController` 1 561 (19-07), `CortesEficienciaController` 1 537 (22-06), `BalancearTejido` 1 411, `DividirTejido` 1 393 (PT), `CalendarioController` 1 301 (19-06b), `OeeAtadoresFileService` 1 293 (ya bajó de 3 097), `Livewire/Desarrolladores/Captura` 1 164 (19-04), `AtadoresController` 1 129 (22-06), `ReqProgramaTejidoUpdateImport` 1 127 (PT), `ReqProgramaTejidoObserver` 1 073 (PT), `Reporte00EAtadoresExport` 1 061 (22-06), `LiberarOrdenesController` 1 056 (PT), `TejidoHelpers` 1 048 (PT), `ReqProgramaTejidoSimpleImport` 1 035 (PT).
- **SQL fuera del ORM:**
  - Totales: 145 `*Raw()`, 70 `DB::raw`, 10 `DB::select/statement`, 113 `DB::table` (query builder sin modelo), 45 conexiones directas a AX (`sqlsrv_ti`).
  - Por módulo, los que más tienen: Services/ProgramaUrdEng (37 `*Raw`, 13 `DB::raw`), Controllers/Planeacion (25 `*Raw`, 20 `DB::table`, 16 AX), Controllers/Tejido (26 `DB::raw`), Services/Trazabilidad (13), Controllers/Tejedores (14), Services/Planeacion (14 AX).
