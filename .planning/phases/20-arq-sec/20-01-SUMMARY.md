# 20-01 — Arquitectura: movimientos y deduplicación (SUMMARY)

**Rama:** `claude/20-01-arq` (base `claude/friendly-hopper-506bg9`) · **IDs:** ARQ-01, ARQ-02, ARQ-03, ARQ-04 · **Plan:** `20-01-PLAN.md`. El owner lo aprobó con D1 = A (numeración idéntica) y D2 = sí (incluir `asegurarSecuencia`).

## Commits (en orden de revisión)

| Commit | Qué |
|---|---|
| `arquitectura: plan 20-01` | Plan. |
| `arquitectura: mover servicios de Desarrolladores a app/Services` | **ARQ-01, movimiento puro.** 8 renames (similitud 94–99 %), namespace `App\Services\Tejedores\Desarrolladores`, líneas `use` de los consumidores y rutas/FQCN en `phpstan-baseline.neon`. En PT, Captura y AppServiceProvider solo cambian líneas `use`, reordenadas alfabéticamente para que pase Pint. En los tests, Pint además quita los `()` de `new X()`. |
| `arquitectura: caracterizar folios y turnos antes de deduplicar` | Tests escritos y en verde **sobre el código original**. |
| `arquitectura: folios de SSYSFoliosSecuencias solo por FolioHelper` | ARQ-02. |
| `arquitectura: getTurnoInfo de Cortes y Trama desde TurnoHelper::info()` | ARQ-03 y borrado de `TurnoHelper::generarFolio()`, que no tenía usos. |
| `arquitectura: guardia contra rutas de app/ que solo difieren en mayusculas` | ARQ-04. |
| `arquitectura: Trama conserva el prefijo TR cuando la secuencia lo trae NULL` | Hallazgo de code-review. |

## ARQ-02 — folios

Inventario con grep de `SSYSFoliosSecuencias`, y qué quedó en cada sitio:

| Sitio | Antes | Ahora |
|---|---|---|
| `CrearOrdenesService` y `CrearOrdenKarlMayerController` | `obtenerFolioUrdEng()` privado y duplicado: `nextFolio('URD/ENG')`, con respaldo `nextFolioById(14)` ante `\Exception`; `CambioHilo` llamaba directo a `nextFolio` | `FolioHelper::obtenerSiguienteFolio('URD/ENG', 5, idRespaldo: 14)` y `FolioHelper::obtenerSiguienteFolio('CambioHilo', 5)`. Los dos métodos privados se borraron. |
| `NuevoRequerimientoService::construirVm()` | Reimplementación: lock, luego `prefijo+pad(consecutivo)` y luego `increment` | `FolioHelper::consumirFolioSugerido('Trama', 5, prefijoSiNulo: 'TR')`. Consume exactamente el folio que muestra `obtenerFolioSugerido()`. Sin fila de secuencia se conserva el folio `TR` aleatorio de antes. |
| `TelBpmController::generarFolio()` | Crear la secuencia sembrada con el máximo `BT` + alinear + `nextFolio` con respaldo `nextFolioByPrefijo` + guardia | La creación pasa a `FolioHelper::asegurarSecuencia()` y el consumo a `FolioHelper::obtenerSiguienteFolio()`. **Se conservan** la alineación contra `TelBPM`, el respaldo `nextFolioByPrefijo` y la guardia de folio ocupado, que son propios de BPM. |
| `OrdenesTrabajoMecaController`, `VerificaMaquina\Index` | `asegurarSecuenciaFolios()` copiado | Queda como una línea que delega en `FolioHelper::asegurarSecuencia(...)`. |
| `CortesEficienciaController`, `NuevoRequerimientoController` | Ya usaban FolioHelper | Sin cambio. |
| `SecuenciaFoliosController` | CRUD del catálogo | No se tocó (no es una reimplementación). |

**Semántica.** Cada sitio produce el mismo texto de folio y deja el mismo consecutivo. El bloqueo tampoco cambia: `lockForUpdate` corre dentro de la transacción del llamador, y la transacción propia del helper es un savepoint anidado, así que el lock dura hasta el commit exterior.

Diferencias menores, a propósito:
- Cuando falla la generación de un folio sin respaldo (por ejemplo `CambioHilo`, o el primer intento de BPM), ahora se escribe una línea `Log::error` antes de relanzar la excepción.
- BPM hace un `SELECT ... WITH (UPDLOCK)` más, porque `asegurarSecuencia` bloquea la fila por su cuenta.

**Evidencia:**
- `tests/Unit/Helpers/FoliosCaracterizacionTest.php` tiene 22 tests: URD/ENG en ambos orígenes (normal, respaldo Id 14, sin fila), Trama (normal, prefijo NULL, sin secuencia), BPM (crea, crea vacío, alinea, no retrocede), Mecánicos ×2 (crea sembrada, crea en 0, no toca) y FolioHelper directo.
- Las expectativas no cambiaron después de deduplicar. En URD/ENG solo cambió la invocación, porque el método privado dejó de existir; en su lugar el test verifica que cada llamador contiene la llamada al helper.
- El test de prefijo NULL, agregado después, también pasa sobre el servicio original (lo comprobé restaurando el archivo previo).
- Técnica: `nextFolio()` lee `INFORMATION_SCHEMA.COLUMNS`; en sqlite se adjunta una base con ese nombre.

## ARQ-03 — turnos

- `TurnoHelper::info()` devuelve `{turno, horario, formato}`. Cortes responde `descripcion = horario` y Trama responde `descripcion = formato`, igual que antes.
- `tests/Unit/Helpers/TurnoHelperTest.php` tiene 19 tests:
  - Límites 00:00, 06:29:59, 06:30, 14:29:59, 14:30, 22:29:59, 22:30 y 23:59, con el reloj fijado en UTC para probar la conversión a America/Mexico_City, más un caso en julio que confirma que no hay horario de verano.
  - `getFechaProduccion()` (madrugada y cierre del día 1 a las 08:30).
  - El JSON exacto de los dos endpoints `/turno-info`.
- No hay otras reimplementaciones del cálculo de turno en `app/`: un grep de 390/870/1350 solo encuentra TurnoHelper.

## ARQ-04 — BUG-022

- El duplicado `app/Models/urdengomado/UrdEngNucleos.php` ya se había borrado en `160613b`. Queda un solo FQCN, `App\Models\UrdEngomado\UrdEngNucleos`.
- La guardia `RutasSinColisionDeMayusculasTest` falla si entra una ruta de `app/` que solo difiere en mayúsculas. Lo comprobé recreando el duplicado: el test falla con las dos colisiones y vuelve a verde al borrarlo.
- `composer dump-autoload`: **0** clases ambiguas en `App\` propias. La única advertencia con `App\` es `vendor/laravel/pint/app/Providers/AppServiceProvider.php`, que pertenece al propio paquete Pint y ya existía; las de flysystem también son de vendor.

## Checks

| Check | Resultado |
|---|---|
| `php artisan test` | **1408 passed** (19892 assertions); en la base eran 1365 (+43 tests nuevos en `tests/Unit/Helpers`) |
| `vendor/bin/phpstan analyse --memory-limit=2G` | `[OK] No errors` |
| `composer dump-autoload` | Sin clases `App\` ambiguas de `app/` |
| `npm run build` / `npm run ratchet` | OK / "ninguna subió" |
| `vendor/bin/pint --test` sobre los PHP cambiados desde la base | `pass` |
| Skill code-review | 1 hallazgo (prefijo NULL de Trama), corregido |
| Skill security-review | Sin hallazgos; la concurrencia de folios queda igual que antes |

SQL crudo nuevo: ninguno; todo va por query builder y es compatible con 2008 R2.

## Despliegue

- `composer dump-autoload -o` para que el classmap tome el namespace nuevo de Desarrolladores, y después `php artisan optimize:clear` / `optimize`.
- Sin migraciones, variables `.env` nuevas ni `.sql`.

## Pendientes / HANDOFF

- `HANDOFF.md`: actualizar `CLAUDE.md` (rutas nuevas y `UrdEngomado/`) y marcar BUG-022 como resuelto en el inventario de bugs.
- Posible siguiente paso, fuera de alcance: dos semánticas de consecutivo conviven en `SSYSFoliosSecuencias`. En Trama el consecutivo es "el siguiente a usar"; en los demás módulos es "el último usado". Por eso `obtenerFolioSugerido()` muestra el último folio ya usado en los módulos que no son Trama, y en Trama `resolverFolio()` salta un número cuando crea un folio nuevo. Unificarlo cambia la numeración: decisión del owner.
