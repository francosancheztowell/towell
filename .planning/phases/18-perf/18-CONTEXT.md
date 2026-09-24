# Fase 18 — Performance backend

**Track:** PERF · **Ola:** 18-01 en Ola 2, 18-02 dentro de cada sesión 19-xx · **IDs:** PERF-01..12 · **Rama:** `claude/18-01-perf-infra`
**Regla:** ninguna optimización sin número antes/después (`../10-base/10-BASELINE.md`, `SYSMonVista`, Pulse, `../04-ux-grid/04-PERF-MEDIDO.md`).
**Antes de ejecutar:** escribir `18-01-PLAN.md`.

## 18-01 — Infraestructura (Ola 2)
- PERF-01 Pedir al owner el `.env` real de prod (192.168.2.15): `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`. `config/session.php` default `file`, `.env.example` dice `database` y **no existe migración de tabla `cache`**. Recomendación: sesión y cache `file` (un solo servidor Windows, sin Redis); documentar decisión y runbook.
- PERF-02 Checklist OPcache (`opcache.enable=1`, `memory_consumption=256`, `max_accelerated_files=20000`, `validate_timestamps` según deploy) y `realpath_cache_size=4096K` en el `php.ini` de Laragon → `docs/cerebro-towell/Runbooks/deploy.md`.
- PERF-03 Confirmar `php artisan optimize` en `npm run update` (ya está) y `optimize:clear` en rollback; `event:cache`.
- PERF-04 `moduleNameForRoute` (`app/Helpers/permission-helpers.php`) hace hasta 3 queries a `SYSRoles` (una con `LIKE '%x%'`) por request: memoizar por request + cache con prefijo `modulos_v3` y TTL del `ModuloService`.
- PERF-05 `Model::preventLazyLoading(! app()->isProduction())` con `handleLazyLoadingViolationUsing` → log (sin throw). PERF-06 `DB::whenQueryingForLongerThan(500, …)` → log/Pulse.
- PERF-07 `SetSqlContextInfo` ejecuta `EXEC dbo.sp_SetAppContext` en **cada** request web (incluidos polls de Livewire). **No cambiar su semántica** (alimenta los triggers de `SYSAuditoria`, y el comentario explica el pooling ODBC); solo medir su costo con Pulse/Server-Timing y excluir rutas de telemetría (ya lo hace MON-A). Proponer, con números, si conviene saltarlo en GET de solo lectura.

## 18-02 — Dentro de cada 19-xx (Ola 3)
- PERF-08 N+1: `FinalizarOrdenesController` (PT), `MovimientoDesarrolladorService`, `ReqProgramaTejidoUpdateImport` (PT), `CortesEficienciaController` (incluye PT-PERF-02 parte cortes), `AtadoresController` (`exists()` por ítem, `Model::all()` ×4), `DividirTejido`/`BalancearTejido` (PT).
- PERF-09 67 `where` con CAST/CONVERT/LTRIM/UPPER sobre columna y 19 `LIKE '%…'`: validar con plan de ejecución en Laragon; la collation CI hace innecesario `UPPER`.
- PERF-10 Índices vía `.sql` revisado por DBA (nunca migración a ciegas; verificar `sys.indexes`).
- PERF-11 `CodificacionController@getAll` (139 columnas, sin límite): proyección + paginación.
- PERF-12 Observer de `ReqProgramaTejido` (991 líneas, síncrono en cada `saved`): lo resuelve PT (fases 05/06).

## Criterios de éxito
- p95 por ruta en `/admin/rendimiento` baja o se mantiene en cada deploy; ninguna ruta top-15 empeora.
