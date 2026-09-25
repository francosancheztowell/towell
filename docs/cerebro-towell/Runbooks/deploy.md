# Runbook — despliegue en Laragon (192.168.2.15)

**Dueño:** owner (Sistemas). **Origen:** fase 18-01 (PERF-01..03, PERF-07). Un solo servidor Windows con
Laragon, sin Redis ni proxy; base de datos SQL Server 2008 R2.

---

## 1. Drivers (PERF-01, decisión del owner 2026-09-25)

| Variable | Valor en producción | Por qué |
|---|---|---|
| `CACHE_STORE` | `file` | Un solo servidor web. No hay migración de la tabla `cache`; el default del código también es `file` desde 18-01. |
| `SESSION_DRIVER` | `file` | Igual. Las sesiones viven en `storage/framework/sessions`. |
| `QUEUE_CONNECTION` | `database` | Desde 18-03 la usan los avisos de Telegram de atado terminado, montado de julio y solicitud de trama. Necesita la tabla `jobs` y el worker de §8. `sync` es la válvula mientras no haya worker (§8). |

Revisar esta decisión solo si se agrega un segundo servidor web (entonces cache y sesión compartidas: Redis o `database` con su migración).

Comprobar el `.env` real:

```bat
findstr /B "CACHE_STORE SESSION_DRIVER QUEUE_CONNECTION" .env
```

## 2. `php.ini` de Laragon (PERF-02) — una sola vez

Menú Laragon → PHP → `php.ini`. Valores:

```ini
[opcache]
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
; Despliegue con git pull en caliente: revisar timestamps cada 2 s.
opcache.validate_timestamps=1
opcache.revalidate_freq=2

realpath_cache_size=4096K
realpath_cache_ttl=600
```

- `validate_timestamps=0` es más rápido, pero **obliga a reiniciar Apache en cada despliegue**; un reinicio olvidado deja corriendo el código viejo. Con `git pull` en caliente, quedarse en `1` + `revalidate_freq=2`.
- Reiniciar Apache (Laragon → Stop / Start All) después de editar `php.ini`.

Verificar que el `php.ini` es el que carga Laragon:

```bat
php -i | findstr /I "Loaded opcache.memory_consumption opcache.max_accelerated_files realpath_cache_size"
```

La CLI no usa OPcache (`enable_cli=0`): el estado real se ve en el `phpinfo()` del servidor web (Laragon → *Web* → `phpinfo`), sección *Zend OPcache*: `Opcache Enabled: Up and Running`, memoria libre > 0 y `Out of memory restarts = 0` tras un día de uso. Si hay reinicios por memoria, subir `memory_consumption` a 384.

Antes/después: medir con el método de `.planning/phases/10-base/10-BASELINE.md` ("Cómo medir", 3 cargas, descartar la primera) las mismas 5 pantallas, y anotar `app;dur` del header Server-Timing y el p95 por ruta de `/admin/rendimiento` una semana antes y una después.

## 3. Cada despliegue (PERF-03)

```bat
cd C:\laragon\www\towell
git pull
composer install --no-dev -o          :: solo si cambió composer.lock
npm ci                                :: solo si cambió package-lock.json
php artisan migrate --force           :: solo si el SUMMARY de la fase lo pide
npm run update                        :: git pull (ya no trae nada) + optimize:clear + optimize + build
```

- `npm run update` ya corre `php artisan optimize:clear && php artisan optimize`. En Laravel 12 `optimize` cachea **config, events (`event:cache`), routes y views** (verificado en 18-01); no hace falta un `event:cache` aparte ni cambiar `package.json`.
- `optimize:clear` también vacía la cache de la aplicación (`cache:clear`): menú por usuario, `moduleNameForRoute`, límites de intentos y cierres remotos pendientes. Es esperado; un cierre remoto solicitado justo antes del deploy hay que volver a pedirlo.
- Después de cambiar `.env`: `php artisan config:clear && php artisan config:cache` (o `npm run update`). Con config cacheada, un cambio en `.env` no se ve.

## 4. Rollback

```bat
php artisan optimize:clear            :: SIEMPRE primero: sin esto quedan rutas/config/vistas cacheadas del código nuevo
git checkout <commit-o-tag-anterior>
composer install --no-dev -o
npm ci && npm run build
php artisan optimize
```

- Migraciones: las de monitoreo/PT son aditivas; no correr `migrate:rollback` sin revisar con el DBA qué tablas borra.

## 5. Despliegue acumulado pendiente (fases 10–15, PT 01.1/02, 18-01)

Tomado de `.planning/STATE.md` ("Pending Todos", 2026-09-25) más lo de 18-01. Hacerlo una vez, en orden:

1. `php.ini` según §2 y reiniciar Apache.
2. `git pull` de la rama integrada; `composer install --no-dev -o` (nuevo: `laravel/pulse`); `npm ci && npm run build` (nuevo: `qrcode`).
3. Confirmar `pdo_sqlite` y `sqlite3` (`php -m | findstr sqlite`) y permiso de escritura en `storage\pulse`. Sin `pdo_sqlite`: `PULSE_ENABLED=false` (el panel `/admin` sigue con sus tablas; se pierde la medición de §6). Ver `phases/14-mon-pulse/14-01-SUMMARY.md`.
4. `php artisan migrate --force` (o que el DBA corra `database/sql/sysmon_tablas.sql`).
5. `SELECT area, COUNT(*) FROM dbo.SYSUsuario GROUP BY area` → ajustar `MONITOREO_AREAS_ADMIN` si el valor no es "Sistemas".
6. `.env`: `CACHE_STORE=file`, `SESSION_DRIVER=file` (§1); `MAIL_*` de Resend (alertas a francost15@gmail.com); si `LOG_STACK=single`, pasar a `daily` + `LOG_DAILY_DAYS=30`; `QUEUE_FAILED_DRIVER` no en `null`.
7. PT: DBA corre `database/sql/pt_muestras_{marbetes,produccion,longitudes}.sql` (aditivos); avisar a Muestras que liberar exige `crear` del módulo Muestras (idrol 5); D-1 (`OBJECT_ID` de `ReqPesosRollosTejido` vs `ReqPesosRolloTejido`); canary 02.5 (`PLANEACION_READ_V2_SHADOW_SAMPLE=0.05` una semana); `phases/01-guardrails/RUNBOOK-LARAGON.md`.
8. `php artisan optimize:clear && php artisan optimize`.
9. Probar: `/admin` con usuario de Sistemas (200) y de otra área (403); cierre remoto de una tablet de prueba; en DevTools → Network, el header `Server-Timing` de una pantalla trae `app`, `db` y `ctx` (§6).
10. 18-01: provocar un error de JS en una pantalla (consola: `setTimeout(() => { throw new Error('prueba ruta') })`) y verificar en `/admin` → Errores que la ruta es la de esa pantalla, no `telemetria.error`. Los errores de navegador viejos con `telemetria.error` se corrigen solos en su siguiente ocurrencia.

11. 18-03: tabla `jobs` y worker de la cola según §8 (antes de este paso, `QUEUE_CONNECTION=sync`).

## 6. Medir `SetSqlContextInfo` (PERF-07) — una semana después del despliegue

`SetSqlContextInfo` ejecuta `EXEC dbo.sp_SetAppContext` en cada request web (incluidos los polls de Livewire) para que los triggers de `SYSAuditoria` sepan quién escribió. No se cambia sin estos números.

**En una pantalla:** DevTools → Network → la request → *Timing* → Server Timing: `ctx` (ms del EXEC, incluye abrir la conexión si es la primera consulta), `db` (todas las consultas, `ctx` incluido) y `app` (total).

**Agregado (Pulse, sqlite `storage\pulse\pulse.sqlite`):** tipo `contexto_sql`, valores en **µs**, llave `GET` / `POST` / `livewire`.

```sql
-- Promedio y máximo por tipo de request, últimos 7 días (sqlite3 storage\pulse\pulse.sqlite)
SELECT key,
       COUNT(*)                         AS requests,
       ROUND(AVG(value) / 1000.0, 1)    AS prom_ms,
       ROUND(MAX(value) / 1000.0, 1)    AS max_ms
FROM pulse_entries
WHERE type = 'contexto_sql' AND timestamp > strftime('%s', 'now', '-7 days')
GROUP BY key;

-- p95 por tipo (funciones de ventana: sqlite >= 3.25)
SELECT key, ROUND(value / 1000.0, 1) AS p95_ms FROM (
  SELECT key, value,
         ROW_NUMBER() OVER (PARTITION BY key ORDER BY value) AS n,
         COUNT(*)     OVER (PARTITION BY key)                AS total
  FROM pulse_entries WHERE type = 'contexto_sql'
) WHERE n = CAST(total * 0.95 AS INTEGER);
```

Pulse recorta `pulse_entries` a 7 días; para más historia usar `pulse_aggregates` (`aggregate IN ('avg','max','count')`, `period = 10080`).

Mandar la salida de las dos consultas y el p95 de `app;dur` por tipo (`/admin/rendimiento`) a la sesión de rendimiento; el criterio de decisión está en `.planning/phases/18-perf/18-01-SUMMARY.md` (PERF-07).

## 7. Avisos de rendimiento en el log (PERF-05/06)

`storage\logs\laravel-*.log`, buscar `Rendimiento:`:

- `relación cargada en lazy (posible N+1)` — solo fuera de producción (local/Laragon de desarrollo). Una línea por modelo y relación por request.
- `la request lleva más de 500 ms en consultas` — también en producción: una línea por conexión y request cuando la suma de sus consultas pasa de 500 ms, con la ruta. La consulta individual lenta está en Pulse (`/admin/pulse`, *Slow Queries*).

```bat
findstr /C:"Rendimiento:" storage\logs\laravel-*.log
```

## 8. Worker de la cola (18-03, PERF-13)

**Por qué:** los avisos de Telegram que acompañan una acción (terminar atado, notificar montado de julio,
solicitar trama) ya no se mandan dentro de la petición. `defer()` no sirve en este servidor: sin PHP-FPM no
existe `fastcgi_finish_request` y el navegador espera a que termine el callback diferido (medición en
`.planning/phases/18-perf/18-03-SUMMARY.md`). Van a la cola `database` y un worker los manda.

**1. Confirmar el SAPI** (una vez): crear `public\info-tmp.php` con `<?php phpinfo();`, abrirlo, buscar
**Server API** y **borrar el archivo**. `Apache 2.0 Handler` (mod_php) o `CGI/FastCGI` (php-cgi) confirman
que `defer()` no libera la respuesta. Si dijera `FPM/FastCGI`, avisar a la sesión de rendimiento.

**2. Tabla `jobs`** (una vez): el DBA corre `database/sql/queue_jobs_tablas.sql` (idempotente, crea `jobs` y
`failed_jobs` si faltan y registra sus migraciones). Sin la tabla la app no se cae: manda el aviso en línea y
deja `Telegram: no se pudo encolar el aviso` en el log.

**3. Tarea programada** (una vez), en una consola como administrador, con las mismas rutas que `scheduler.bat`:

```bat
schtasks /Create /TN "Towell cola" /SC MINUTE /MO 1 /RU SYSTEM /F ^
  /TR "\"C:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\php.exe\" C:\laragon\www\towell\artisan queue:work --stop-when-empty --max-time=50"
```

Cada minuto arranca un worker que procesa lo pendiente y sale solo (`--stop-when-empty`) o a los 50 s
(`--max-time=50`), así nunca hay dos a la vez por mucho tiempo ni un proceso colgado días. Tras cada
`git pull` no hace falta reiniciarlo: el siguiente minuto arranca con el código nuevo.

**4. Verificar:**

```bat
REM la tarea corre cada minuto y termina con 0
schtasks /Query /TN "Towell cola" /V /FO LIST | findstr /C:"Último resultado" /C:"Last Result"
```

```sql
SELECT COUNT(*) AS pendientes, MIN(DATEADD(s, created_at, '19700101')) AS mas_viejo_utc FROM dbo.jobs;  -- ~0 en operación normal
SELECT TOP 20 failed_at, queue, LEFT(exception, 300) AS error FROM dbo.failed_jobs ORDER BY id DESC;
```

Prueba de punta a punta: terminar un atado de prueba → la tablet responde al instante → el mensaje llega al
grupo de Atadores en menos de un minuto. Fallos de envío: `findstr /C:"Telegram" storage\logs\laravel-*.log`.

**Válvula:** si el worker no está listo, `QUEUE_CONNECTION=sync` en `.env` + `php artisan config:cache`. Los
avisos vuelven a salir en línea (como antes de 18-03, pero en paralelo y con timeouts de 3/8 s: peor caso
~16 s, un envío y un reintento, en vez de N × 20 s).
