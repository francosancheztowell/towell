# Fase 14 — Monitoreo: Laravel Pulse

**Track:** MON-D · **Ola:** 1 · **IDs:** MON-29..32 · **Rama:** `claude/14-mon-pulse`
**Depende de:** 11-01 (Gate `admin`, `MonitoreoServiceProvider`). Antes que la fase 13 termine (el panel enlaza a Pulse).
**Antes de ejecutar:** escribir `14-01-PLAN.md`.

## Objetivo
Métricas agregadas de servidor (requests lentas, queries lentas con ubicación, jobs lentos, requests salientes lentas, uso por usuario) en `/admin/pulse`, sin tocar SQL Server.

## Hecho verificado
Pulse (`DatabaseStorage::upsert*` y su migración) solo soporta `mysql`/`mariadb`/`pgsql`/`sqlite`; con `sqlsrv` lanza `Unsupported database driver`. → **Conexión `pulse` SQLite dedicada.**

## Alcance
- `composer require laravel/pulse` (archivo caliente `composer.json/lock`: dueño MON-D en Ola 1). `livewire/livewire ^4` es compatible.
- Conexión `pulse` registrada en runtime (en `MonitoreoServiceProvider`, vía HANDOFF a MON-A si 11 no está mergeada, o en un provider propio `PulseServiceProvider`): `driver sqlite`, `database storage_path('pulse/pulse.sqlite')`, `journal_mode=wal`, `busy_timeout=5000`, `synchronous=normal`; `PULSE_DB_CONNECTION=pulse`. Crear el archivo/directorio en el deploy (documentar) y migraciones de Pulse sobre esa conexión. **No** tocar `config/database.php`.
- `config/pulse.php`: `path` = `admin/pulse`; gate `viewPulse` → `Gate::allows('admin')`; `Pulse::user(fn ($u) => ['name' => $u->nombre, 'extra' => '#'.$u->numero_empleado, 'avatar' => foto])`.
- Recorders activos: SlowRequests (1000 ms), SlowQueries (500 ms, con ubicación), SlowJobs, SlowOutgoingRequests, UserRequests. Apagados: Exceptions (fuente única = `SYSMonError`), Servers (requiere daemon `pulse:check`), CacheInteractions. Ignorar `#^/telemetria/#`, `#^/admin/pulse#`; muestrear `livewire/update` a 0.1.
- Recorder de legado (ADOP-01, se usa desde Ola 2): `Pulse::record('legado', $uri)->count()` en los redirects 301 — solo dejar el helper listo.
- Fallback (MON-32): si prod no tiene `pdo_sqlite` o SQLite no aguanta la concurrencia → `PULSE_ENABLED=false`; el panel sigue con tablas propias. Documentar cómo verificar en Laragon (`php -m | findstr sqlite`).

## Criterios de éxito
- `/admin/pulse` abre para Sistemas, 403 para otros.
- `phpunit.xml` ya trae `PULSE_ENABLED=false`: la suite sigue verde.
- Overhead medido (contra `../10-base/10-BASELINE.md`) < 2 ms p95.

## Skills
`laravel-specialist`, `code-review`, `run`.
