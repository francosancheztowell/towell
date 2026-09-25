# 18-01 — Performance backend: infraestructura (SUMMARY)

**Rama:** `claude/18-01-perf-infra` (base `claude/friendly-hopper-506bg9`). **IDs:** PERF-01..07 y HANDOFF 12 §1.
**Plan:** `18-01-PLAN.md` (aprobado por el owner 2026-09-25). **HANDOFF:** `HANDOFF.md` (1 línea en `layout-head`, 1 opcional en `ModuloService`).

## Qué se hizo

| ID | Cambio | Número antes → después |
|---|---|---|
| PERF-01 | `config/cache.php` default `database` → `file` (sesión ya era `file`); `.env.example` documenta la decisión. Test: sin `CACHE_STORE`/`SESSION_DRIVER` ambos son `file`. | Un `.env` sin `CACHE_STORE` caía en `database`, que no tiene tabla → ahora `file`. |
| PERF-02 | `docs/cerebro-towell/Runbooks/deploy.md` §2: OPcache (`enable=1`, `memory_consumption=256`, `interned_strings_buffer=16`, `max_accelerated_files=20000`, `validate_timestamps=1` + `revalidate_freq=2`) y `realpath_cache_size=4096K`/`ttl=600`, cómo verificarlo y cómo medir antes/después (método de 10-BASELINE). | Lo mide el owner en Laragon (runbook). |
| PERF-03 | Runbook §3/§4: `npm run update` ya corre `optimize:clear && optimize`; en Laravel 12.53 `optimize` cachea config, **events**, routes y views (verificado: salida del comando). Rollback con `optimize:clear` primero. `package.json` no cambia → sin HANDOFF. Verificado que `route:cache` conserva el alias `legacy-livewire-endpoint` y `/livewire/update`. | — |
| PERF-04 | `moduleNameForRoute()`: memo por request (contenedor, como `userPermissions`) + `Cache::remember` con llave `modulos_v3_<env>_modulo_ruta_<versión>_<sha1>` y TTL 3600; guarda también el "no encontrado". La versión la rotan `SYSRoles::saved/deleted` (así escribe `ModulosController`). Las 3 consultas originales quedan idénticas en `buscarModuloPorRuta()`. | Queries a `SYSRoles` por llamada: **3 → 0** (peor caso, tras la primera; test `ModuleNameForRouteTest`). Tiempo por llamada en sqlite local: **461.7 µs → 57.6 µs** (cache `file`). En SQL Server cada consulta suma su viaje de red. Único consumidor hoy: `MecReportesController:438` (OT diarias). |
| PERF-05 | `Model::preventLazyLoading(! isProduction())` + `handleLazyLoadingViolationUsing` → `Log::warning('Rendimiento: relación cargada en lazy…')`, una vez por modelo+relación por request, sin lanzar. | Suite completa (1381 tests) con la prevención activa: **0** avisos en el log. |
| PERF-06 | `whenQueryingForLongerThan(500)` en **cada** conexión (listener de `ConnectionEstablished`: sqlsrv, TI_PRO, TOW_PRO…), una vez por conexión y request aunque reconecte → `Log::warning('Rendimiento: la request lleva más de 500 ms en consultas.')` con conexión, ms, ruta y SQL. Es el **acumulado** por request; la consulta individual lenta ya es de Pulse (SlowQueries): no se duplica. | — (observabilidad) |
| PERF-07 | `SetSqlContextInfo` cronometra el `EXEC dbo.sp_SetAppContext` (sin cambiar cuándo ni cómo se sella) y `App\Services\Monitoreo\ContextoSql` lo publica: `ctx;dur=<ms>` anexado al `Server-Timing` y `Pulse::record('contexto_sql', GET\|POST\|livewire, µs)->avg()->max()->count()`. Respeta `MONITOREO_ENABLED` y `PULSE_ENABLED`. | Costo de la medición (local, 5 000 iteraciones): **5.8 µs/request** sin Pulse, **17.2 µs** con Pulse. El costo del EXEC en producción: runbook §6. |
| MON (12 §1) | `POST /telemetria/error` lee `ruta` (y `version`, que ya leía). `SYSMonError.Ruta` = ruta del cliente → si falta, `Ruta` de la `SYSMonVista` del uuid `vista` **del mismo usuario** → si no, `desconocida`. Nunca `telemetria.error`; las filas viejas con `telemetria.error` se corrigen en su siguiente ocurrencia. Cliente: `ruta: meta('towell-ruta') \|\| undefined` en `reportar()`. | Cliente: **3149 → 3154 B gzip** (≤ 5 KB). |

### Decisión: `Ruta` NO entra en la huella de errores de cliente

- El mismo defecto en un bundle compartido (`app.js`, utils) aparece en N páginas: con la ruta en la huella serían N errores y N alertas por correo.
- Las huellas ya registradas en producción no cambian: el deploy no parte grupos ni dispara alertas de "nuevo".
- La página de cada ocurrencia ya queda en `SYSMonErrorEvento.Url`; `SYSMonError.Ruta` es la de la primera vez (o la corregida si era `telemetria.error`). Documentado en el docblock de `ErrorRecorder::capturarCliente()`.

### Navegación suave de Livewire

`wire:navigate` (`livewire.esm.js::mergeNewHead`) quita los `<meta>` del head viejo y agrega los del nuevo **antes** de `livewire:navigated`. `reportar()` lee el meta en el momento del error, así que tras navegar manda la ruta nueva. Test JS lo cubre y se verificó en navegador (abajo).

## PERF-07 — propuesta con números

**Lo que se sabe hoy:**
- El `EXEC` corre en cada request web, incluidos los polls de Livewire (`/telemetria/*` ya lo excluye, MON-A).
- `ctx` incluye abrir la conexión física cuando el `EXEC` es la primera consulta de la request. Saltarlo **no** ahorra esa conexión si la request hace otra consulta después, y casi todas lo hacen (`auth` lee `SYSUsuario`). El ahorro real es el tiempo del `EXEC` ya conectado.
- Lo que cuesta medirlo es despreciable: 5.8–17.2 µs por request.
- En la nube no hay SQL Server: el costo real del `EXEC` sale del runbook `deploy.md` §6 (Server-Timing `ctx` + consultas a `pulse_entries` por GET/POST/livewire, una semana).

**Criterio de decisión (con los números del runbook):**

| Resultado en producción | Decisión |
|---|---|
| p95 de `ctx` < 2 ms **o** < 3 % del p95 de `app` en todos los tipos | **No tocar.** Cerrar PERF-07. |
| p95 de `ctx` ≥ 5 ms **y** ≥ 5 % de `app` en `GET` o `livewire` | Plan en 18-02: **sellar en forma perezosa** (`DB::beforeExecuting`) antes de la primera sentencia que escribe (`INSERT/UPDATE/DELETE/MERGE/EXEC`) en esa conexión, en lugar de al inicio de cada request. Mantiene la garantía (los triggers de `SYSAuditoria` solo corren en escrituras, y toda escritura pasa por el sellado) y se ahorra el `EXEC` en las requests de solo lectura. Necesita tests de que ninguna escritura sale sin sello, incluidos `DB::unprepared` y los SP que escriben. |
| Entre los dos | Revisar solo `livewire` (polls de andón), que es donde se multiplica. |

**No se propone** saltarlo por método (`GET`): hay GET que escriben (logs, auditoría de lectura). Con la conexión reutilizada, la escritura quedaría con el autor de la request anterior, que es justo lo que el comentario del middleware prohíbe.

## Archivos

| Archivo | Cambio |
|---|---|
| `config/cache.php`, `.env.example` | PERF-01 |
| `docs/cerebro-towell/Runbooks/deploy.md` (nuevo), `00-Indice-runbooks.md` | PERF-01..03, 07, avisos de log, despliegue acumulado 10–15 + PT + 18-01 |
| `app/Helpers/permission-helpers.php` | PERF-04: `moduleNameForRoute` memo+cache, `buscarModuloPorRuta`, `olvidarModulosPorRuta`, `moduleNameForRouteCachePrefix` |
| `app/Providers/AppServiceProvider.php` | Solo en `boot` + métodos privados nuevos: eventos de `SYSRoles`, PERF-05, PERF-06. Las 2 `use` de Desarrolladores no se tocaron (conflicto trivial con 20-01 en el bloque de `use`). |
| `app/Http/Middleware/SetSqlContextInfo.php` | PERF-07: solo medición |
| `app/Services/Monitoreo/ContextoSql.php` (nuevo) | PERF-07: Server-Timing `ctx` + Pulse |
| `app/Http/Controllers/Monitoreo/TelemetriaController.php`, `app/Services/Monitoreo/ErrorRecorder.php` | Fix MON |
| `resources/js/monitoreo/cliente.ts` | `ruta` en `reportar()` |
| `tests/Feature/Perf/{DriversProduccion,ModuleNameForRoute,ObservabilidadConsultas,SetSqlContextInfo}Test.php` (nuevos) | 13 tests |
| `tests/Feature/Monitoreo/TelemetriaTest.php`, `tests/Js/monitoreo-cliente.test.mjs` | 3 tests PHP + 2 JS del fix MON |

## Evidencia

```
php artisan test                         → Tests: 1381 passed (19880 assertions)  [+ reconexión PERF-06: tests/Feature/Perf 13 passed]
vendor/bin/phpstan analyse               → [OK] No errors
vendor/bin/pint --test (archivos tocados) → pass
npm run typecheck                        → ok
npm run test:js                          → pass 97, fail 0   (# monitoreo: 3154 B gzip)
npm run build                            → ✓ built
npm run ratchet                          → ratchet ok (10 metricas, ninguna subio)
php artisan optimize / optimize:clear    → config, events, routes, views ✓
```

Cada test nuevo se corrió también contra el código anterior y falló (drivers, reconexión de PERF-06, ruta de errores).

**Navegador (skill run: Chromium + `php -S` con workers, `sqlsrv` apuntado a sqlite de scratch sin tocar `config/database.php`, usuario de área Sistemas):**

| Acción | `SYSMonError` |
|---|---|
| `/admin` (carga completa) → `setTimeout(() => { throw new Error(…) })` en consola | `Origen js · Ruta admin.index` (evento `Url /admin`) |
| `/admin/rendimiento` (carga completa) → throw | `Ruta admin.rendimiento` |
| `Livewire.navigate('/admin/sesiones')` (navegación suave; el meta pasó a `admin.sesiones`, 1 solo meta) → throw | `Ruta admin.sesiones` (evento `Url /admin/sesiones`) |

Cuerpo que mandó el cliente: `{"origen":"js",…,"url":"/admin/sesiones","ruta":"admin.sesiones","vista":"97a67f54-…","version":"147f9d60fbc3"}`. El panel `/admin/errores` muestra las 3 filas con su ruta.

**Code review** (skill `code-review`, medium, sobre `origin/claude/friendly-hopper-506bg9...HEAD`): 1 hallazgo, el umbral de PERF-06 se volvía a registrar en cada `DB::reconnect()` y el aviso salía N veces en un worker de larga vida. Corregido (`WeakMap` por conexión) con test que falla sin el fix. Anotado sin corregir: `SYSRoles` escrito por SQL directo no invalida la cache de `moduleNameForRoute` (lo cubre `optimize:clear` en cada deploy; ver HANDOFF §2).

## Cómo desplegar

- **Sin migraciones ni `.sql`.**
- `.env`: confirmar `CACHE_STORE=file` y `SESSION_DRIVER=file` (runbook §1). Nada nuevo.
- `php.ini`: runbook §2 (OPcache/realpath) y reiniciar Apache.
- `npm run build` (cambió el cliente de monitoreo) y `php artisan optimize:clear && php artisan optimize` (o `npm run update`).
- Una semana después: runbook §6 (costo de `SetSqlContextInfo`) → decidir con la tabla de PERF-07.
- Todo el despliegue acumulado pendiente (fases 10–15, PT, 18-01) quedó en orden en `deploy.md` §5.

## Pendientes

- PERF-07: números de producción (owner, runbook §6) y decisión según la tabla.
- HANDOFF §1: `towell-ruta` con la URI cuando la ruta no tiene nombre.
- PERF-05 solo avisa fuera de producción: revisar `Rendimiento:` en el log del Laragon de desarrollo al trabajar cada módulo en 18-02 (PERF-08, N+1).
