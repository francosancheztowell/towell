# 14-01 — Resumen: Laravel Pulse en /admin/pulse (+ deuda de MON-A y SEC-02)

**Rama:** `claude/13-14-mon-pulse-panel` (base `claude/friendly-hopper-506bg9`) · **IDs:** MON-29, MON-30, MON-31, MON-32, SEC-02, deuda phpstan de MON-A (HANDOFF 10 §2)
**Plan:** `14-01-PLAN.md`. Sin cambios al contrato 11.

## Qué se hizo

| Pieza | Archivo(s) |
|---|---|
| Deuda phpstan (HANDOFF 10 §2): `@property` en MonDispositivo y MonError, logout con usuario null, offset `'function'`, `?->` del Gate. Salen 5 entradas del baseline | `app/Models/Sistema/Monitoreo/{MonDispositivo,MonError}.php`, `app/Listeners/Monitoreo/RegistrarLogout.php`, `app/Services/Monitoreo/{ErrorRecorder,AccesoAdmin}.php`, `phpstan-baseline.neon` |
| SEC-02: fuera `trustProxies(at: '*')`; el monitoreo registra `REMOTE_ADDR` con `Monitoreo::ip()` | `bootstrap/app.php`, `Monitoreo.php`, `AccesoService`, `DispositivoService`, `RegistrarLogin`, `TelemetriaController` |
| `laravel/pulse ^1.8`, que solo agrega `laravel/pulse`, `laravel/sentinel` y `doctrine/sql-formatter` | `composer.json`, `composer.lock` |
| Conexión SQLite `pulse` registrada en runtime (WAL, `busy_timeout` 5000, `synchronous` normal). Crea el archivo si falta | `app/Services/Monitoreo/PulseConexion.php`, `MonitoreoServiceProvider::register()` |
| Config: `path` = `admin/pulse`; middleware `web` + `auth` + `Authorize`; recorders; muestreo de Livewire | `config/pulse.php` |
| Gate `viewPulse` = `admin`; `Pulse::user()` muestra nombre, `#numero_empleado` y foto; `Pulse::filter` para el muestreo | `MonitoreoServiceProvider::configurarPulse()` |
| Migración que se salta con Pulse apagado | `database/migrations/2026_09_24_000010_create_pulse_tables.php` |
| Dashboard con solo las tarjetas encendidas, o un aviso si Pulse está apagado | `resources/views/vendor/pulse/dashboard.blade.php` |
| Helper de legado para ADOP-01 (Ola 2) | `app/Services/Monitoreo/Legado.php` |
| Carpeta del archivo SQLite, ignorada en git | `storage/pulse/.gitignore` |
| Tests | `tests/Feature/Monitoreo/{PulseTest,IpRealTest,LogoutSinUsuarioTest}.php` |

**Recorders.**
- Encendidos:
  - `SlowRequests`, a partir de 1000 ms.
  - `SlowQueries`, a partir de 500 ms, con ubicación y la consulta recortada a 2000 caracteres.
  - `SlowJobs` y `SlowOutgoingRequests`. Las URLs de Telegram se agrupan sin el token del bot.
  - `UserRequests`.
- Apagados:
  - `Exceptions`: la fuente única de errores es `SYSMonError`.
  - `Servers`: requiere el daemon `pulse:check`.
  - `CacheInteractions`, `Queues` y `UserJobs`.
- Excluidos de `SlowRequests` y `UserRequests`: `#^/admin/pulse#` y `#^/telemetria/#`.
- En `UserRequests`, las llamadas `livewire*/update` se muestrean: solo cuenta 1 de cada 10 (`PULSE_LIVEWIRE_SAMPLE_RATE`, por defecto 0.1). Las lentas no se muestrean.

## Decisiones

1. **La conexión vive en `PulseConexion`, no en `config/database.php`.** Ese archivo está en gitignore con skip-worktree. El archivo SQLite sale de `pulse.storage.database.sqlite` (`PULSE_DB_DATABASE`). Si no está definido, se usa `storage/pulse/pulse.sqlite`, o `:memory:` en tests.
2. **La migración se salta si `pulse.enabled` es false.**
   - Así, un servidor sin `pdo_sqlite` no truena en `migrate`.
   - Laravel no registra una migración saltada, así que corre en el primer `migrate` después de encender Pulse.
3. **`viewPulse` se define en `booted()`.** Pulse define el suyo (solo en `local`) al resolverse el Gate. En `booted()` ya arrancaron todos los providers, así que la definición de Towell gana sin depender del orden.
4. **SEC-02.** Además de quitar `trustProxies`, el monitoreo dejó de usar `getClientIpv4()` (`app/Helpers/device_helpers.php`, que no es de este track). Ese helper cae al `X-Forwarded-For` crudo cuando `REMOTE_ADDR` no es IPv4, por ejemplo `::1`.
   - `getClientIpv4()` se sigue usando fuera del monitoreo y ahí conserva ese fallback. Se deja como aviso para ARQ (fase 20), sin HANDOFF, porque no bloquea nada.
   - Si algún día se pone un proxy delante, hay que usar `trustProxies(at: ['<IP del proxy>'])` en `bootstrap/app.php`. El comentario ya lo dice.

## Evidencia

```
php artisan test tests/Feature/Monitoreo
  Tests:    98 passed (715 assertions)
php artisan test                         # suite completa
  Tests:    1296 passed (19579 assertions)
vendor/bin/phpstan analyse --memory-limit=2G      [OK] No errors (5 entradas menos en el baseline)
npm run build                                     ✓ built
npm run ratchet                                   ratchet ok (10 metricas, ninguna subio)
vendor/bin/pint --test <archivos PHP tocados>     pass
```

- `IpRealTest` falla (4 de 4) si se vuelve a poner `trustProxies(at: '*')`: se comprobó revirtiendo la línea.
- **Pulse en vivo** (skill `run`, Pulse encendido sobre un archivo SQLite): `/admin/pulse` responde 200 a Sistemas, con «Application Usage → Franco Sistemas #9001» y requests reales registradas. Responde 403 a Tejido. Captura en `../13-mon-panel/capturas/pulse.png`.
- **Overhead.** Microbenchmark local de la ingesta a SQLite WAL, con 500 iteraciones:
  - Request típica (1 `user_request`): p50 0.97 ms, p95 1.38 ms.
  - Con una request lenta además: p95 2.27 ms.
  - La ingesta corre en `terminate()`, después de enviar la respuesta con FPM, así que no se suma al tiempo que ve el usuario.
  - Falta medir en Laragon contra `../10-base/10-BASELINE.md` (runbook abajo).
- **`security-review`:** sin hallazgos de confianza alta o media.
- **`code-review` (medium):** un hallazgo en el panel, ya corregido (ver `13-01-SUMMARY.md`).

## Cómo desplegar (Laragon)

1. **`pdo_sqlite`.** Revisar con `php -m | findstr sqlite`: deben salir `pdo_sqlite` y `sqlite3`.
   - Si no salen, descomentar `extension=pdo_sqlite` y `extension=sqlite3` en el `php.ini` de Laragon y reiniciar Apache o Nginx.
   - Si no se puede, ir al Fallback.
2. `composer install --no-dev` para traer `laravel/pulse`.
3. **Archivo SQLite.** Crear `storage\pulse\pulse.sqlite`. La app lo crea sola si falta, pero el usuario del servicio web necesita permiso de escritura en `storage\pulse` (también para los archivos `-wal` y `-shm`).
4. `php artisan migrate`. Crea `pulse_*` **en el archivo SQLite**, no en SQL Server. En `dbo.migrations` solo queda la fila de la migración.
5. **`.env`** (opcional; los valores por defecto sirven):
   ```
   PULSE_ENABLED=true
   # PULSE_DB_DATABASE=C:\laragon\www\towell\storage\pulse\pulse.sqlite
   # PULSE_LIVEWIRE_SAMPLE_RATE=0.1
   ```
6. `php artisan optimize:clear && php artisan optimize`.
7. **Retención.** Pulse limpia solo lo que tenga más de 7 días (`PULSE_STORAGE_KEEP`), con una lotería de 1 en 1000 al ingerir. No requiere scheduler.

### Fallback (MON-32)
Si prod no tiene `pdo_sqlite`, o SQLite no aguanta la concurrencia (`database is locked` en el log):
1. `PULSE_ENABLED=false` en `.env`.
2. `php artisan optimize`.

Con eso no se graba nada, la migración se salta y `/admin/pulse` muestra «Pulse está apagado». El enlace «Pulse» desaparece del panel. El panel `/admin` sigue completo con las tablas `SYSMon*`.

### Runbook: medir el overhead en Laragon
1. Con `PULSE_ENABLED=false`, abrir 20 veces una pantalla de la línea base de `10-BASELINE.md` y anotar el p95 del header `Server-Timing: app` (DevTools → Network → Timing).
2. Repetir con `PULSE_ENABLED=true`.
3. El criterio es p95 con Pulse − p95 sin Pulse < 2 ms. El `terminate()` no aparece en `Server-Timing`: si hace falta medirlo, usar `SYSMonVista.CargaMs` de ambas corridas.

## Pendientes

- Medir el overhead en prod (runbook arriba).
- ADOP-01 (Ola 2) llama `Legado::registrar($uri)` en los redirects 301. Una tarjeta de Pulse para `legado` queda para esa fase.
