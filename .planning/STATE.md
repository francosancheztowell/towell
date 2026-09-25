# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-09-25)

**Core value:** Planta y planeación trabajan más rápido y sin fricción, y Sistemas ve qué pasa en producción, sin romper invariantes de dominio.
**Current focus:** Ola 2 — librerías (15-02), componentes (16), movimientos/folios (20-01), perf infra + fix MON (18-01), PT 04-perf.
**Protocolo:** `.planning/PROTOCOLO-SESIONES.md` (propiedad de archivos, orden de merge, gates).

## Current Position

Ola: 1 de 4 — **terminada e integrada** en `claude/friendly-hopper-506bg9` (2026-09-25)
Ola 0 — terminada e integrada:
- `claude/10-base` — session_017VenBHhK6jcvQXfSynxW4b — merge `080d764`
- `claude/11-mon-servidor` — session_01J7vgKbD5zVc6quHX9zzWw9 — merge `76f0e59`
- `claude/11-03-alertas-correo` — session_01VtFtyJvrs2cRtAomxTz4DM — merge `95069ff`
- `claude/pt-01-guardrails` — session_01DY3pXuDNb3Zk9eV7TXBoxg — merge `870a586` (runbook de Laragon pendiente del owner)

Ola 1 — terminada e integrada (orden 15-01 → 12 → 13-14 → PT):
- `claude/15-01-utils-ts` — session_01Bv6QphZHsjRbXrHuFfDsoi — merge `1e3c525`
- `claude/12-mon-cliente` — session_01LejJmkBirENzZcEke8uQ2c — merge `8c2ce54`
- `claude/13-14-mon-pulse-panel` — session_016rh12MpSHLTuXoSDd2cpxv — merge `8290129`
- `claude/pt-01.1-02` — session_01TD1ADfUv5GXAjt5eJ7q9Yb — merge `0e55e84`
Validación de la rama integrada: **1 365 tests PHP verdes** (19 811 aserciones), phpstan `[OK] No errors`, typecheck OK, 95/95 tests JS, build OK, ratchet OK.

G1 (entrada a Ola 2): utils TS mergeados ✅; monitoreo en producción ≥ 7 días ⏳ (falta desplegar); cierre remoto probado en prod ⏳; overhead p95 < 5 ms ✅ en local (1.4 ms), falta medir en prod.

**`main` integrado (2026-09-25):** a pedido del owner, las Olas 0 y 1 se llevaron a `main`. Antes se trajeron a esta rama los 39 commits propios de `main` (fase 08 ERP quick wins, Ventas históricas, fixes de Engomado/Atadores/Crudo, conexión de Ventas). Resolución: vistas `programar-{urdido,engomado}` siguen borradas (muertas; solo tenían el cambio mecánico de 08-02); `NuevoRequerimientoLivewireTest` toma el mock de `main`; ROADMAP conserva el nuestro + fase 8. Arreglados 2 tests que ya fallaban en `main` (guard de SQL Server sin la conexión de Ventas; Crudo con el % de 2das) y 3 snapshots de PT por las rutas que 08-05 quitó a propósito. Baselines: phpstan regenerado con la deuda de `main` (3 441), ratchet con `innerHTML` 394→401 por `resources/js/ventas/ventas-historicas.js` (→ 19-10). Pint aplicado a 9 PHP que el CI revisa.
**Ojo Ola 2:** las 5 sesiones partieron de la base anterior; `main` ya dejó 0 `bg-opacity-*` (parte de FE-12) y borró código muerto que 15-02/16 podrían tocar → esperar conflictos al integrarlas.

Ola 2 — abierta 2026-09-25 sin esperar G1 (decisión del owner). Prompts y propiedad en `SESIONES-OLA-2.md`; orden de merge 20-01 → 18-01 → PT 04-perf → 15-02 → 16:
- `claude/15-02-librerias` — session_01TQbwRo1Nnv926TpbrZsQuU
- `claude/16-componentes` — session_01BXY25bxEvFRvn9zFQDDuXp
- `claude/20-01-arq` — session_012Qzr95nR9m7DXJx4mMpnwT
- `claude/18-01-perf-infra` — session_01UhtrkSte6Z8jWPKmZEners
- `claude/pt-04-perf` — session_01TLqGpEASNx9XneZUVaF3JE
Después: 20-02 / 20-03 al integrar 20-01; 17-01 cuando haya ≥ 7 días de telemetría.

Status: Ola 2 en curso
Last activity: 2026-09-25 — Integración de la Ola 1 (4 ramas) + docs del integrador (CLAUDE.md, contrato §4, SQL Server 2008 R2).

Progress: [█████░░░░░] ~35% (fases 10–14 completas, 15-01, PT 01, 01.1 y 02 completas)

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

- Aprobar en claude.ai/code los planes de las 5 sesiones de la Ola 2 (arrancan en modo plan).

**Despliegue en Laragon (192.168.2.15) de todo lo integrado** (fases 10, 11, 12, 13, 14, 15-01, PT 01.1/02):
1. `git pull` de la rama; `composer install --no-dev -o` (nuevo: `laravel/pulse`); `npm ci && npm run build` (nuevo: `qrcode`).
2. Confirmar `pdo_sqlite` y `sqlite3` (`php -m | findstr sqlite`); `storage\pulse` con permiso de escritura. Sin `pdo_sqlite`: `PULSE_ENABLED=false` (el panel sigue con tablas propias). Ver `phases/14-mon-pulse/14-01-SUMMARY.md`.
3. **SQL (lo único obligatorio en SQL Server):** en SSMS contra ProdTowel correr `database/sql/sysmon_tablas.sql` completo y luego el `IF NOT EXISTS … INSERT INTO dbo.migrations` de su encabezado. **No** usar `php artisan migrate` a secas (`dbo.migrations` no coincide con live). Pulse va aparte en SQLite: `php artisan migrate --path=database/migrations/2026_09_24_000010_create_pulse_tables.php`. Hasta que existan las tablas, `/admin` da `Invalid object name 'SYSMonDispositivo'` (visto el 2026-09-25); el resto de la app sigue funcionando (las escrituras de monitoreo solo dejan `Log::warning`).
   Consulta de diagnóstico (solo lectura) antes y después: `OBJECT_ID('dbo.SYSMonDispositivo')`, `OBJECT_ID('dbo.failed_jobs')` (si es NULL: `migrate --path=…create_failed_jobs_table.php`), `COL_LENGTH('dbo.MuestrasPrograma','CuentaBarra1')` y `COL_LENGTH('dbo.ReqProgramaTejido','CalibreBarra12')` (si NULL: correr los scripts de `main` `alter_barras_muestras_programa.sql` / `alter_calibre_barra2_karl_mayer.sql`).
4. `SELECT area, COUNT(*) FROM dbo.SYSUsuario GROUP BY area` → ajustar `MONITOREO_AREAS_ADMIN` si el valor no es "Sistemas".
5. `.env`: `MAIL_*` de Resend ya existentes (alertas a francost15@gmail.com); si fija `LOG_STACK=single`, pasar a `daily` + `LOG_DAILY_DAYS=30`; `QUEUE_FAILED_DRIVER` no en `null`.
6. `php artisan optimize:clear && php artisan optimize && php artisan view:clear && php artisan route:clear`.
7. Probar: `/admin` con usuario de Sistemas (200) y de otra área (403); cierre remoto de una tablet de prueba.

**PT:**
- Avisar a los usuarios de Muestras: liberar ahora exige `crear` del módulo Muestras (idrol 5), no el de Programa.
- `database/sql/pt_muestras_{marbetes,produccion,longitudes}.sql`: **no urgente** (habilitan capacidades A de Muestras para PT-05/06). Corregidos el 2026-09-25: usaban `THROW` (2012+) y en 2008 R2 fallaban sin hacer nada. Correr primero en staging.
- D-1: `SELECT OBJECT_ID('dbo.ReqPesosRollosTejido') AS plural, OBJECT_ID('dbo.ReqPesosRolloTejido') AS singular;` y decir cuál existe.
- Aprobar canary 02.5: `PLANEACION_READ_V2_SHADOW_SAMPLE=0.05` una semana, luego revisar diferencias en el log.
- Correr `phases/01-guardrails/RUNBOOK-LARAGON.md`.

**Otros:**
- Marcar como *required* en la protección de `main` los checks `checks` y `php` del workflow *Frontend checks*.
- Ventas: la conexión `sqlsrv_Reportes_Towell` ya está en `config/database.php` (commit de `main`); confirmar las 5 llaves `DB_*_REPORTES_TOWELL` en el `.env` de prod.
- Certificado de tablets: `public/towell-ca.crt` ya no se sirve; distribuirlo por otro medio si las tablets lo bajaban de ahí.
- Aviso de privacidad del monitoreo (propuesta: leyenda discreta en login).
- Worker de colas en Windows (propuesta: `queue:work --stop-when-empty --max-time=50` desde `scheduler.bat`).

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
Stopped at: Ola 2 abierta (5 sesiones). Siguiente: revisar SUMMARY/HANDOFF de cada rama e integrar en el orden de `SESIONES-OLA-2.md`.
Resume file: None
