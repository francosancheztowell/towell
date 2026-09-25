# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-09-25)

**Core value:** Planta y planeación trabajan más rápido y sin fricción, y Sistemas ve qué pasa en producción, sin romper invariantes de dominio.
**Current focus:** Ola 3 — primera tanda (17-02 UX global, 19-01 Urdido+Engomado, 19-03 Atadores, PT 05). Olas 0–2 completas y en `main`.
**Protocolo:** `.planning/PROTOCOLO-SESIONES.md` (propiedad de archivos, orden de merge, gates).

## Current Position

**`main` = `42837fa1` (2026-09-25):** contiene las Olas 0, 1 y 2 completas. El owner integró por su cuenta la Ola 2 (`c814d1a8`), 18-03 Telegram (`456f2257`), los arreglos post-merge (`53a5a89d`), scripts Karl Mayer (`93a7e786`), D-1 resuelto (`82966c96`: el observer lee `ReqPesosRolloTejido`, singular) y borró las ramas `claude/*`. La rama integradora se recreó avanzando (`--ff-only`) hasta `main`; `42837fa1` agrega el redirect de `/planeacion/catalogos/catalogoCodificacion` (404 del menú).
Validación sobre `main` + redirect: **1 623 tests PHP**, phpstan OK, build, ratchet OK.

Historial de sesiones (todas integradas):
- Ola 0: 10-base, 11-mon-servidor, 11-03 alertas, PT-01.
- Ola 1: 15-01 utils TS, 12 monitoreo cliente, 13-14 Pulse + panel, PT 01.1-02.
- Ola 2: 20-01, 18-01, PT 04-perf, 15-02, 16, 20-02/03 (22 rutas esperan idrol), 18-03 Telegram (cola `database`).

Gates: G1 ⏳ (monitoreo en prod ≥ 7 días: depende del despliegue); G2 ✅ salvo 17-01 (auditoría UX por uso real) e idrol de 22 rutas. Ola 3 se abre ya por decisión del owner (2026-09-25).

Ola 3 — primera tanda (abierta 2026-09-25; prompts y propiedad en `SESIONES-OLA-3.md`):
- (sesiones por crear)

Status: Ola 3 en curso
Last activity: 2026-09-25 — Olas 0–2 en `main`; redirect de Codificación; apertura de la Ola 3.

Progress: [██████░░░░] ~60% (fases 10–16, 18-01/03, 20 y PT 01–04-perf completas)

## Performance Metrics

**Velocity:**
- Total plans completed: 11 (10-01, 11-01, 11-02, 11-03, 12-01, 13-01, 14-01, 15-01, PT 01, 01.1, 02)
- Average duration: - min
- Total execution time: 0 hours

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| - | - | - | - |

## Accumulated Context

### Decisions

Ver PROJECT.md → Key Decisions. Recientes (2026-09-24, owner):

- Roadmap completo ejecutado con sesiones Claude paralelas; esta rama (`claude/friendly-hopper-506bg9`) es la integradora.
- Tiempo real del panel: `wire:poll.visible` (sin Reverb).
- Errores/rendimiento: Laravel Pulse (SQLite dedicado, porque Pulse no soporta sqlsrv) + tablas propias `SYSMon*`.
- Monitoreo: sesión + dispositivo, navegación con tiempos, rendimiento servidor/cliente, acciones admin (cierre remoto, fallidos).
- PT sí migra a Livewire **sin cambiar el diseño**, solo si mejora rendimiento medido. Orden PT: 01 → 01.1 → 02 → 04-perf → 03 → 05 → 04-ux → 06 → 07.
- Logout normal y remoto = solo ese dispositivo (`logoutCurrentDevice`).
- Panel solo área Sistemas, en `/admin`.

- 2026-09-24 (01.3): Muestras **sí se liberan**, con **"M"** en `CatCodificados.OrdenTejido` y en `MuestrasPrograma.NoProduccion` (formato exacto a confirmar al planear PT-02) → Marbetes A. Redbooth B, Producción A, Descarga TXT B, Finalización B, Longitudes A. Liberar Muestras exige `crear` del módulo Muestras (idrol 5).

- 2026-09-24: sin proxy delante de Laragon → quitar `trustProxies` (sesión 13-14); órdenes de Muestras `M12345`; prod usa `file` para cache y sesión; alertas de errores solo por correo a francost15@gmail.com.

### Pending Todos (owner)

- **Cola de avisos (18-03), antes de desplegar `main`:** correr `database/sql/queue_jobs_tablas.sql` **y** crear la tarea programada del worker (`docs/cerebro-towell/Runbooks/deploy.md` §8). Las dos juntas o ninguna: con la tabla `jobs` y sin worker, los avisos de terminar atado, montado de julio y solicitud de trama se quedan atorados. Sin la tabla, la app manda en línea como antes. Alternativa: `QUEUE_CONNECTION=sync`.
- Confirmar el SAPI con `phpinfo()` → "Server API" (runbook §8 paso 1).
- `SELECT idrol, modulo, Ruta FROM dbo.SYSRoles WHERE Ruta LIKE '%odific%'` → corregir la `Ruta` del menú si apunta a `catalogoCodificacion` (el redirect ya cubre el 404).

- Correr en ProdTowel la consulta de solo lectura de `phases/20-arq-sec/20-03-MAPA-AUTHZ.md` §Pendientes y mandar el resultado (idrol de 22 rutas).
- Correr el SQL de despliegue (área de `/admin` confirmada: solo Sistemas; enviado 2026-09-25: `sysmon_tablas.sql` + registro en `dbo.migrations` + `failed_jobs` + barras de `main`), luego Pulse con `migrate --path` y `optimize`.


**Despliegue en Laragon (192.168.2.15) de todo lo integrado** (fases 10, 11, 12, 13, 14, 15-01, PT 01.1/02):
1. `git pull` de la rama; `composer install --no-dev -o` (nuevo: `laravel/pulse`); `npm ci && npm run build` (nuevo: `qrcode`).
2. Confirmar `pdo_sqlite` y `sqlite3` (`php -m | findstr sqlite`); `storage\pulse` con permiso de escritura. Sin `pdo_sqlite`: `PULSE_ENABLED=false` (el panel sigue con tablas propias). Ver `phases/14-mon-pulse/14-01-SUMMARY.md`.
3. **SQL (lo único obligatorio en SQL Server):** en SSMS contra ProdTowel correr `database/sql/sysmon_tablas.sql` completo y luego el `IF NOT EXISTS … INSERT INTO dbo.migrations` de su encabezado. **No** usar `php artisan migrate` a secas (`dbo.migrations` no coincide con live). Pulse va aparte en SQLite: `php artisan migrate --path=database/migrations/2026_09_24_000010_create_pulse_tables.php`. Hasta que existan las tablas, `/admin` da `Invalid object name 'SYSMonDispositivo'` (visto el 2026-09-25); el resto de la app sigue funcionando (las escrituras de monitoreo solo dejan `Log::warning`).
   Consulta de diagnóstico (solo lectura) antes y después: `OBJECT_ID('dbo.SYSMonDispositivo')`, `OBJECT_ID('dbo.failed_jobs')` (si es NULL: `migrate --path=…create_failed_jobs_table.php`), `COL_LENGTH('dbo.MuestrasPrograma','CuentaBarra1')` y `COL_LENGTH('dbo.ReqProgramaTejido','CalibreBarra12')` (si NULL: correr los scripts de `main` `alter_barras_muestras_programa.sql` / `alter_calibre_barra2_karl_mayer.sql`).
4. ~~Área de admin~~ → confirmado por el owner (2026-09-25): solo **Sistemas**. Es el default; no hace falta `MONITOREO_AREAS_ADMIN` en `.env`.
5. `.env`: `MAIL_*` de Resend ya existentes (alertas a francost15@gmail.com); si fija `LOG_STACK=single`, pasar a `daily` + `LOG_DAILY_DAYS=30`; `QUEUE_FAILED_DRIVER` no en `null`.
6. `php artisan optimize:clear && php artisan optimize && php artisan view:clear && php artisan route:clear`.
7. Probar: `/admin` con usuario de Sistemas (200) y de otra área (403); cierre remoto de una tablet de prueba.

**PT:**
- Avisar a los usuarios de Muestras: liberar ahora exige `crear` del módulo Muestras (idrol 5), no el de Programa.
- `database/sql/pt_muestras_{marbetes,produccion,longitudes}.sql`: **no urgente** (habilitan capacidades A de Muestras para PT-05/06). Corregidos el 2026-09-25: usaban `THROW` (2012+) y en 2008 R2 fallaban sin hacer nada. Correr primero en staging.
- ~~D-1 tabla de pesos~~ → resuelto por el owner en `82966c96` (singular `ReqPesosRolloTejido`).
- Aprobar canary 02.5: `PLANEACION_READ_V2_SHADOW_SAMPLE=0.05` una semana, luego revisar diferencias en el log.
- Correr `phases/01-guardrails/RUNBOOK-LARAGON.md`.

**Otros:**
- Marcar como *required* en la protección de `main` los checks `checks` y `php` del workflow *Frontend checks*.
- Ventas: la conexión `sqlsrv_Reportes_Towell` ya está en `config/database.php` (commit de `main`); confirmar las 5 llaves `DB_*_REPORTES_TOWELL` en el `.env` de prod.
- Certificado de tablets: `public/towell-ca.crt` ya no se sirve; distribuirlo por otro medio si las tablets lo bajaban de ahí.
- Aviso de privacidad del monitoreo (propuesta: leyenda discreta en login).
- Worker de colas en Windows (propuesta: `queue:work --stop-when-empty --max-time=50` desde `scheduler.bat`).

### HANDOFFs ruteados (Ola 2)

- 20-02/03 #4 parser de `RutasDestructivasPermisoTest` → aceptado. #5 código de referencia de `errors/500.blade.php` por excepción → 17-02. Huecos (supervisor en `atadores/save`, `actualizar-campo-orden`, `actualizar-prioridades`, `telegram/send` sin llamadores, stub de cortes, reenconado legacy) → 19-xx.

- 18-01 #1 `towell-ruta` vacío en rutas sin nombre (1 línea en `layout-head`) → 17-02 UX-global.
- 16 A3 loader de `app-core.js` → `window.loader`, A4 toasts bajo el navbar, A5 top layer → FE (próxima sesión que toque utils/app-core) / 21. C1 catálogos de Planeación a `catalog-base.ts`, C2 duplicados BPM/julios/secuencias, C3 llave `Nota1` de Comentarios → 19-xx.
- 15-02 #6 CSS select2 muerto en Trazabilidad, #7 comentario jQuery, #8 `<br>` en calendarios → 19-xx.
- PT B1 `req-programa-tejido-line-table.blade.php` a la fila PT, B2 `mostrarModalDiasLiberar` del navbar → 17-02, B3 `redbooth.blade.php` vuelve a PT (mover su `<script>` al bundle) → próxima sesión PT.
- 20-01: sin pendientes (CLAUDE.md y BUG-022 hechos).

### HANDOFFs ruteados (Ola 1)

- 12 §1/§3 → mini-fix MON en Ola 2: aceptar `ruta` (y `version`) del cliente en `/telemetria/error` para que `SYSMonError.Ruta` no quede `telemetria.error`. Contrato §4 ya actualizado.
- 15-01 §1 → hecho por el integrador (`CLAUDE.md`). §2 comentarios obsoletos en vistas → 21 (ADOP) / cada 19-xx. §4 bug `tejido/inventario-telas.ts` (`err.response?.data?.message` con el nuevo `HttpError`) → 19-02 Tejido.
- PT A: 5 tests tocados fuera de PT → aceptado. B1/B2 botones de navbar (Descargar, permiso Muestras) → 17-02 UX global. B3 → PT 04. B4 → DBA (arriba). B5/D-1 → owner (arriba).
- 10 §1 `tel-bpm/log-debug` → 19-04 Tejedores. `getClientIpv4()` con fallback a `X-Forwarded-For` fuera de monitoreo → 20 (SEC).

### Blockers/Concerns

- Las sesiones en la nube no alcanzan SQL Server: lo que requiera datos reales (schema físico, baseline de tiempos, valores de `area`) vuelve como script/runbook para correr en Laragon.
- PT fase 4-ux sigue con PLAN `.superseded`; se replanea cuando toque (Ola 3).
- Índices `IX_ReqProgramaTejido_*` citados en el modelo no existen en migraciones — verificar en PT 01.2.

## Session Continuity

Last session: 2026-09-25
Stopped at: Ola 3 abriéndose (17-02, 19-01, 19-03, PT 05). Integrar cada rama al terminar; no push a `main` sin pedido del owner.
Resume file: None
