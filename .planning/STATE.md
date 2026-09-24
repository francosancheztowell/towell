# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-09-24)

**Core value:** Planta y planeación trabajan más rápido y sin fricción, y Sistemas ve qué pasa en producción, sin romper invariantes de dominio.
**Current focus:** Ola 1 — monitoreo cliente, Pulse + panel /admin, utils TS, PT AuthZ + lectura.
**Protocolo:** `.planning/PROTOCOLO-SESIONES.md` (propiedad de archivos, orden de merge, gates).

## Current Position

Ola: 1 de 4 (abierta 2026-09-24 17:10 UTC; G0 cumplido salvo marcar checks required en `main`)
Ola 0 — terminada e integrada en `claude/friendly-hopper-506bg9`:
- `claude/10-base` — session_017VenBHhK6jcvQXfSynxW4b — merge `080d764`
- `claude/11-mon-servidor` — session_01J7vgKbD5zVc6quHX9zzWw9 — merge `76f0e59`
- `claude/11-03-alertas-correo` — session_01VtFtyJvrs2cRtAomxTz4DM — merge `95069ff`
- `claude/pt-01-guardrails` — session_01DY3pXuDNb3Zk9eV7TXBoxg — merge `870a586` (runbook de Laragon pendiente del owner)
Validación de la rama integrada: 1 254 tests PHP verdes, phpstan OK, typecheck OK, 37/37 tests JS, ratchet OK.

Ola 1 — sesiones abiertas (modo plan: esperan aprobación del owner en la web). Prompts y propiedad en `SESIONES-OLA-1.md`:
- `claude/12-mon-cliente` — session_01LejJmkBirENzZcEke8uQ2c
- `claude/13-14-mon-pulse-panel` — session_016rh12MpSHLTuXoSDd2cpxv
- `claude/15-01-utils-ts` — session_01Bv6QphZHsjRbXrHuFfDsoi
- `claude/pt-01.1-02` — session_01TD1ADfUv5GXAjt5eJ7q9Yb

Status: Ola 1 en curso
Last activity: 2026-09-24 — Cierre de Ola 0 (10 y 11-03 integradas, CI verde) y apertura de Ola 1 (4 sesiones).

Progress: [███░░░░░░░] ~20% (fases 10 y 11 completas, PT-01 completa en sqlite; Ola 1 en curso)

## Performance Metrics

**Velocity:**
- Total plans completed: 0
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

- Aprobar en claude.ai/code los planes de las 4 sesiones de la Ola 1.
- Marcar como *required* en la protección de `main` los checks `checks` y `php` del workflow *Frontend checks*.
- Desplegar fase 11 en Laragon: `php artisan migrate` (o `database/sql/sysmon_tablas.sql` por DBA), `SELECT area, COUNT(*) FROM dbo.SYSUsuario GROUP BY area` y ajustar `MONITOREO_AREAS_ADMIN` si no es "Sistemas", `php artisan optimize:clear && php artisan optimize`. Alertas: requieren los `MAIL_*` de Resend (ya usados por Crudo). Ver `phases/11-mon-servidor/11-0{1,2,3}-SUMMARY.md`.
- Desplegar fase 10: `npm ci && npm run build` (nueva dependencia `qrcode`), `optimize`; si el `.env` de prod fija `LOG_STACK=single`, cambiar a `daily` + `LOG_DAILY_DAYS=30`; `QUEUE_FAILED_DRIVER` no en `null`.
- Ventas: agregar la conexión `sqlsrv_Reportes_Towell` con `scripts/db-config.ps1` (pasos en `phases/10-base/10-01-SUMMARY.md` §Decisiones 1) y las 5 llaves `DB_*_REPORTES_TOWELL` en `.env`.
- Certificado de tablets: `public/towell-ca.crt` ya no se sirve; si las tablets lo bajaban de ahí, distribuirlo por otro medio.
- Correr `phases/01-guardrails/RUNBOOK-LARAGON.md` (tests Planeacion, `planeacion:programa-tejido-health --json`, `sql/01-schema-fisico.sql` → llenar las 11 longitudes de Muestras).
- Confirmar `pdo_sqlite` habilitado en el PHP de Laragon (Pulse, fase 14) y si la LAN sirve HTTPS.
- Aviso de privacidad del monitoreo (propuesta: leyenda discreta en login).
- Worker de colas en Windows (propuesta: `queue:work --stop-when-empty --max-time=50` desde `scheduler.bat`).

### Blockers/Concerns

- Las sesiones en la nube no alcanzan SQL Server: lo que requiera datos reales (schema físico, baseline de tiempos, valores de `area`) vuelve como script/runbook para correr en Laragon.
- PT fase 4-ux sigue con PLAN `.superseded`; se replanea cuando toque (Ola 3).
- Índices `IX_ReqProgramaTejido_*` citados en el modelo no existen en migraciones — verificar en PT 01.2.

## Session Continuity

Last session: 2026-09-24
Stopped at: Ola 1 abierta. Siguiente: revisar SUMMARY/HANDOFF de 12, 13-14, 15-01 y PT 01.1-02, integrar en orden (15-01 → 12 → 13-14; PT cuando quiera) y evaluar G1.
Resume file: None
