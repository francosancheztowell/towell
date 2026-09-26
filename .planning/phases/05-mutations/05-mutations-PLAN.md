---
phase: 05-mutations
plan: "05"
version: 2
wave: ola-3
depends_on: [01-guardrails, 01.1, 02-containment-read, 04-perf]
autonomous: false
branch: claude/pt-05-mutaciones
requirements: [PT-MUT-01, PT-DOM-01, PT-DOM-02, PT-ROL-01, PT-DUP-01, PT-DUP-02, PT-DUP-03, PT-DUP-04, PT-PERF-02]
files_modified:
  - app/Http/Requests/Planeacion/ProgramaTejido/* (nuevos)
  - app/Data/Planeacion/ProgramaTejido/* (nuevos)
  - app/Actions/Planeacion/ProgramaTejido/* (nuevos)
  - app/Http/Controllers/Planeacion/ProgramaTejido/{ProgramaTejidoController,ProgramaTejidoCalendariosController,ProgramaTejidoOperacionesController,RepasoController,LiberarOrdenesController}.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/funciones/{UpdateTejido,DividirTejido,DuplicarTejido,EliminarTejido,BalancearTejido,DragAndDropTejido}.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/helper/{TejidoHelpers,DateHelpers}.php
  - app/Http/Controllers/Planeacion/Utilerias/{MoverOrdenesController,FinalizarOrdenesController}.php
  - app/Models/Planeacion/ReqProgramaTejido.php (EXCEPCIÓN pedida, ver §0)
  - config/planeacion.php (bloque mutaciones_v2)
  - tests/Feature/Planeacion/**, tests/Unit/Planeacion/**
  - .planning/phases/05-mutations/{05-MUTATION-MATRIX.md,05-SUMMARY.md,HANDOFF.md}
must_haves:
  truths:
    - "Cada mutación extraída valida con FormRequest, se tipa en un DTO y corre en un Action transaccional."
    - "Endpoints, payloads y respuestas legacy no cambian: el snapshot de rutas queda igual y los tests de caracterización de PT-01 siguen verdes sin tocarlos."
    - "Cada familia v2 tiene flag (global + usuarios canary), telemetría y rollback probado: flag apagado = handler legacy."
    - "Un fallo de derivados (líneas, CatCodificados, cascada, aplicación) en v2 revierte todo y responde error; nunca éxito."
    - "El observer no se reemplaza: se sigue llamando como adaptador (sincronizarCatCodificados, recalcularFormulasProduccion, regenerateLinesFor)."
    - "suppress/restore, fallback de FechaFinal, chequeo Ultimo y filtros Salón/Telar tienen UNA implementación cada uno en el código PT."
    - "Calculadores extraídos dan el mismo resultado que las copias legacy (shadow comparison en tests) o la divergencia queda documentada y no se corrige sin decisión."
    - "Sin cambios de UI (D-E)."
---

# PT 05 — Mutaciones (v2 del plan)

## Qué cambió respecto a la v1

La v1 (2026-07) se escribió antes de PT-01/02/04 y de la revisión `05-REVIEW.md`. Esta v2:

1. Suma **PT-DUP-01..04** y **PT-PERF-02** (REQUIREMENTS §Deduplicación y §Rendimiento).
2. Usa `ProgramaTejidoSurface` (PT-02) para las reglas por superficie en vez de "capabilities" genéricas.
3. Decide qué hacer con **liberar Muestras con "M"** (`02-MUESTRAS-LIBERAR.md`).
4. Corrige datos de la v1 que ya no son ciertos (ver "Lo que el código dice hoy").
5. Reemplaza la tarea 05.1 `MUTATION-MATRIX` en `.planning/planeacion-restructuring/` (carpeta que no existe) por `05-MUTATION-MATRIX.md` en esta fase.

## Lo que el código dice hoy (leído antes de planear)

| Tema | Estado real | Consecuencia para el plan |
|---|---|---|
| `ProgramaTejidoController::store()` (PT-PERF-02) | **Ya no existe.** El alta va por duplicar/dividir. `DuplicarTejido` ya precarga posiciones por telar en lote (paso 4 "PRE-BATCH") | El N+1 que queda en el camino de alta está en `DividirTejido` (:694, :1273) y en `dividirTelar` (:476): `obtenerSiguientePosicionDisponible()` (una query con UPDLOCK) por destino. PT-PERF-02 se cierra ahí, con número antes/después. La mitad `CortesEficienciaController` no es PT (módulo Tejido) → HANDOFF |
| `ProgramaTejidoObserverHelper` (PT-DUP-01) | Ya no existe | Quedan 2 implementaciones: el modelo (`suppressObservers/restoreObservers`) y copias inline (`getEventDispatcher/unsetEventDispatcher/setEventDispatcher/observe`) en Operaciones (cambiarTelar, dividirTelar), Repaso, Calendarios, DragAndDrop, Balancear, `DateHelpers::cascadeFechas`, Utilerias Mover/Finalizar |
| `restoreObservers()` | **Bug verificado:** re-registra el observer en cada llamada. Script: 1 listener → 2 → 3 tras dos suppress/restore. Después de cada operación masiva, el resto del request corre el observer N veces (regenera líneas N veces) | Hay que arreglarlo **antes** de mover más call sites a él (si no, el DUP-01 empeora el bug). Está en el modelo → §0 |
| `cambiarTelar`/`dividirTelar` | `unsetEventDispatcher()` y luego `observe()` con dispatcher nulo: `observe()` no hace nada y el modelo queda **sin eventos** el resto del request (WR-07) | Se corrige al pasar a suppress/restore |
| Fallback de FechaFinal (PT-DUP-02) | 14 derivaciones, no 6. **6 idénticas** (forma A: `h<=0 → +30 días; calendario → calcularFechaFinalDesdeInicio ?: +round(h·3600) s; sin calendario → +round(h·3600) s`) en UpdateTejido, DividirTejido ×4, DuplicarTejido (este con clamp fin≥inicio). Otras 3 formas (Balancear, DateHelpers secuencia/cascada, calendario masivo) difieren en `h<=0`, saldo<0, repaso 12 h, snap y clamp | `TejidoHelpers::resolverFechaFinal()` reemplaza las 6 idénticas y el núcleo "calendario o continuo" de las otras. Las políticas `h<=0`/saldo<0 **no se unifican** (cambiaría números en planta): quedan como divergencias documentadas |
| Chequeo "Ultimo" (PT-DUP-03) | 12 lecturas, 4 variantes. La única diferencia real es **`'UL'`**: Eliminar/Operaciones/health check lo cuentan como último; Dividir (`== 1`), Update y Balancear (`(int)`), Duplicar/Dividir en SQL (`where('Ultimo', 1)`) no. `'UL'` entra por el import de Excel | `esUltimo()` = `'1'`/`'UL'` (lo que ya usa el health check). En SQL, `whereIn('Ultimo', ['1','UL'])` — además quita la comparación texto vs int, que en SQL Server puede intentar convertir `'UL'` a int |
| Filtros Salón/Telar (PT-DUP-04) | 30 filtros a mano en código PT (9 lugares). 3 reciben el salón **crudo del request** (`KM`): cambiarTelar :341/:373, `obtenerSiguientePosicionDisponible` vía dividir. Ahí cambiar a scope **cambia qué filas toca** | Se pasan a scope los sitios Eloquent con valor de fila/canónico (sin cambio en datos limpios). Los `DB::table` no admiten scope. Los 3 con salón del request quedan como hallazgo (bug KM de cambiarTelar) para decisión, no se tocan en silencio |
| CR-04 (Maquina sin KM en cambiarTelar) | **Ya corregido** (usa `construirMaquinaConSalon`) | Fuera |
| Liberar Muestras "M" | `liberar` es operación de frontera (PT-OPS-01 / fase 06), no mutación simple. R1 tiene una pregunta abierta (secuencia de folios) | En 05 solo **R6** (guard 422 si Muestras no soporta `marbetes`, en vez del 500 actual) y **R7** (`redirectUrl` por superficie). R1–R5, R8, R9 → 06 |

## §0 Excepción de propiedad que pido al owner

`app/Models/Planeacion/ReqProgramaTejido.php` no está en la fila PT, pero REQUIREMENTS pone ahí PT-DUP-01 (`suppressObservers/restoreObservers`) y PT-DUP-03 (`esUltimo()`), y el bug de doble registro vive ahí. Pido tocar **solo**:

- `restoreObservers()`: si hay dispatcher guardado, solo `setEventDispatcher()` (el observer ya está registrado en él); `observe()` solo si no había dispatcher. ~5 líneas.
- `esUltimo(): bool` + constante `VALORES_ULTIMO = ['1', 'UL']`. ~10 líneas.

Si el owner no lo aprueba: va por HANDOFF, `esUltimo()` vive temporalmente en `TejidoHelpers`, y **no** se migran más call sites a `restoreObservers()` hasta que el modelo se arregle (DUP-01 queda parcial).

## Tareas

### 05.1 Matriz de mutaciones → `05-MUTATION-MATRIX.md`
Cada POST/PUT/PATCH/DELETE de PT (del snapshot de rutas): tablas leídas/escritas, observers on/off, transacción, derivados, locks, respuesta, idempotencia, rollback y categoría (simple · secuencia · grupo · balance · frontera · integración). **verify:** ninguna ruta de escritura del snapshot sin fila. **done:** se sabe qué se mueve en 05 y qué queda para 06.

### 05.2 PT-DUP (una implementación cada uno), commit aparte
- **DUP-01:** todo call site PT pasa a `ReqProgramaTejido::suppressObservers()` / `restoreObservers()` con `try/finally`. Test: después de N operaciones en el mismo request hay **1** listener de `saved` (hoy crece); cambiarTelar ya no deja el modelo sin dispatcher.
- **DUP-02:** `TejidoHelpers::resolverFechaFinal(Carbon $inicio, float $horas, ?string $calendarioId): Carbon` = forma A exacta. Reemplaza las 6 copias; Balancear/DateHelpers/calendario masivo lo usan solo para el núcleo "calendario o continuo". **Shadow comparison:** test unitario con copia literal de cada forma legacy vs la función nueva sobre una matriz (h ≤ 0, h fraccional, calendario nulo, calendario sin líneas, calendario que se agota, cruce de medianoche/gap). Divergencias entre formas → tabla en el SUMMARY, sin corregir.
- **DUP-03:** las 12 lecturas pasan a `esUltimo()` / `whereIn(VALORES_ULTIMO)`. **Decisión del owner (2026-09-26): `'UL'` se normaliza a `'1'`.** El valor canónico es `'1'`: un mutator del modelo convierte `'UL'` → `'1'` en toda escritura Eloquent (cubre los imports de Excel sin tocarlos); `esUltimo()` sigue leyendo `'UL'` como último mientras queden filas viejas; `database/sql/pt_ultimo_normalizar.sql` (sin ejecutar, 2008 R2, con conteo previo y rollback) limpia live en ambas superficies. Cambio de comportamiento documentado: filas `'UL'` se tratan como últimas también en Dividir, Update (no cascada), Balancear y Duplicar (se limpian).
- **DUP-04:** sitios Eloquent con salón de fila/canónico → `->salon()->telar()`. No se tocan `DB::table` ni los 3 con salón crudo del request (hallazgo + test que lo fija). Fuera de PT (imports, command, CalendarioController, Desarrolladores, Tejido) → HANDOFF.
- **verify:** suite completa + tests de caracterización PT-01 intactos + health check en sqlite.

### 05.3 PT-PERF-02: posiciones en lote en dividir
Contar queries con `DB::listen` en un feature test (dividir a 1, 3 y 6 destinos): antes/después. Precargar posiciones por telar destino con un solo SELECT … UPDLOCK por salón (mismo patrón que `DuplicarTejido` paso 4) y asignar en memoria. Mismo resultado de `Posicion` que hoy (test de equivalencia con huecos).

### 05.4 Mutaciones simples v2 (PT-MUT-01, PT-DOM-01/02, PT-ROL-01)
Tres familias, cada una **FormRequest → DTO → Action transaccional**:

| Familia | Ruta (sin cambios) | FormRequest / DTO / Action | Diferencias v2 vs legacy (detrás del flag) |
|---|---|---|---|
| `actualizar` (edición inline) | `PUT {programa-tejido,muestras}/{id}` | `ActualizarProgramaTejidoRequest` → `CambiosProgramaTejido` → `ActualizarProgramaTejido` | `lockForUpdate` de la fila dentro de la transacción (CR-05 servidor); `velocidad_std`/`eficiencia_std` recalculan duración y cascada (CR-03); `actualizarAplicacionEnLineas` ya no se traga el error (WR-08); 500 sin `getMessage()` al usuario |
| `reprogramar` | `POST …/{id}/reprogramar` | `ReprogramarRequest` → `Reprogramacion` → `ReprogramarProgramaTejido` | lock + transacción; misma respuesta |
| `calendarios` | `POST …/actualizar-calendarios-masivo` | `CambiarCalendarioRequest` → `CambioCalendario` → `CambiarCalendario` | todo o nada: una fila que falla revierte todo y responde error con los Id (CR-02.3); lock por telar (WR-03); líneas dentro de la transacción con `relanzar`. **No** cambia fórmulas (12 días / 4 decimales, WR-10) ni cascada a vecinos no seleccionados (CR-02.2): se documentan |

- **Sin duplicar lógica:** la aplicación de campos de `UpdateTejido` se extrae (movimiento puro) a un método que usan el legacy y el Action; el loop de calendarios se extrae con un modo `estricto` (legacy = no estricto). El legacy queda como rollback, no como copia.
- **Payload/respuesta:** mismos nombres snake_case y mismo JSON (`success`, `message`, `data` = `extractResumen`). La FormRequest normaliza vacíos a null (igual que hoy) y devuelve el mismo 422.
- **Superficie:** el Action trabaja sobre la tabla que fija `ProgramaTejidoContext`/`ProgramaTejidoSurface`; tests en Programa y Muestras (Muestras con `produccion` sin DDL: el observer ya filtra columnas).
- **Flags (PT-ROL-01):** `config('planeacion.mutaciones_v2')`: `actualizar`, `reprogramar`, `calendarios` (env, default **false**) + `usuarios_canary` (lista de Id). Telemetría: `Log` estructurado `programa_tejido.mutacion` (familia, versión, ms, ok, superficie) + `report()` en fallo. Rollback probado: test con flag apagado → mismo resultado que el legacy.
- **Fallas inyectadas:** por cada dependencia (líneas, CatCodificados, cascada, aplicación, calendario) → rollback + error observable + health check sin invariantes rotas.

### 05.5 Liberar Muestras (solo R6/R7 de `02-MUESTRAS-LIBERAR.md`)
`ProgramaTejidoSurface::actual()->exigir('marbetes')` al inicio de `liberar` (422 en vez de 500 mientras falte el DDL) y `redirectUrl` por superficie. Tests `test_liberar_muestra_sin_ddl_responde_422` y `test_liberar_muestra_redirige_a_muestras`. R1–R5, R8, R9 → 06 (pregunta abierta: ¿secuencia de folios propia para Muestras?).

### 05.6 HANDOFF PT B1 / B3 (si da tiempo, commits aparte, cero cambio visual)
`<script>` de `components/programa-tejido/req-programa-tejido-line-table.blade.php` (19 KB) y de `modal/redbooth.blade.php` (18 KB) → módulos en `resources/js/programa-tejido/`, datos por `application/json`. Ratchet baja. Verificación con la skill `run`: 0 errores de consola, capturas iguales.

### 05.7 Checkpoint humano: canary (no lo hace la sesión)
Runbook en el SUMMARY: encender una familia a la vez para usuarios canary, comparar logs/respuestas y health check antes/después, probar apagar el flag.

## Verificación
- `php artisan test` (base 1623 verdes) · phpstan · pint en tocados · typecheck/test:js/build · ratchet · health check sqlite · code-review y security-review.
- Snapshot de rutas sin cambios (`ProgramaTejidoRouteSurfaceTest`).
- Número antes/después de queries en dividir.

## Tamaño y cortes
~1 500 líneas de código + tests. Commits: (1) matriz + DUP, (2) PERF-02, (3) mutaciones v2 + flags, (4) liberar R6/R7, (5) B1, (6) B3. Si pasa de ~1 500 líneas sin movimientos puros, el SUMMARY marca `-p1` = 1–2 y `-p2` = 3–6 para el integrador.

## Rollback
Flags por familia (default apagado). DUP y PERF son refactors con tests de equivalencia; revertir su commit basta. Sin migraciones ni DDL.
