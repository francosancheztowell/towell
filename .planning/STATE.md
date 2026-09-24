# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-09-24)

**Core value:** Planta y planeación trabajan más rápido y sin fricción, y Sistemas ve qué pasa en producción, sin romper invariantes de dominio.
**Current focus:** Cerrar Ola 0 (falta fase 10) y preparar Ola 1.
**Protocolo:** `.planning/PROTOCOLO-SESIONES.md` (propiedad de archivos, orden de merge, gates).

## Current Position

Ola: 0 de 4 (cerrando)
Sesiones:
- `claude/10-base` — session_017VenBHhK6jcvQXfSynxW4b — **en curso**
- `claude/11-mon-servidor` — session_01J7vgKbD5zVc6quHX9zzWw9 — **terminada**, integrada (merge `76f0e59`)
- `claude/pt-01-guardrails` — session_01DY3pXuDNb3Zk9eV7TXBoxg — **terminada** (parte sqlite), integrada (merge `870a586`); runbook de Laragon pendiente del owner
Status: Esperando fase 10 para cerrar G0 y abrir Ola 1
Last activity: 2026-09-24 — Check-in del integrador: fases 11 y PT-01 integradas en la rama integradora; decisión 01.3 (Programa vs Muestras) aprobada por el owner y registrada en `phases/01-guardrails/01-DECISION-PROGRAMA-MUESTRAS.md` §5.

Progress: [██░░░░░░░░] ~12% (2 de 16 fases con entregables; fase 10 en curso)

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

### Pending Todos (owner)

- Confirmar en prod (192.168.2.15): `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `pdo_sqlite` habilitado, si hay proxy inverso, si la LAN sirve HTTPS.
- `SELECT DISTINCT area FROM SYSUsuario` para confirmar el valor exacto de "Sistemas".
- Aviso de privacidad del monitoreo (propuesta: leyenda discreta en login).
- Worker de colas en Windows (propuesta: `queue:work --stop-when-empty --max-time=50` desde `scheduler.bat`).
- Desplegar fase 11 en Laragon: `php artisan migrate` (o `database/sql/sysmon_tablas.sql` + `sysmon_sysmensajes.sql` por DBA), `SELECT area, COUNT(*) FROM dbo.SYSUsuario GROUP BY area` y ajustar `MONITOREO_AREAS_ADMIN`, marcar "Errores del sistema" en Configuración › Mensajes, `php artisan optimize:clear && php artisan optimize`. Ver `phases/11-mon-servidor/11-01-SUMMARY.md` y `11-02-SUMMARY.md`.
- Correr `phases/01-guardrails/RUNBOOK-LARAGON.md` (tests Planeacion, `planeacion:programa-tejido-health --json`, `sql/01-schema-fisico.sql` → llenar las 11 longitudes de Muestras).
- Confirmar el formato exacto de la "M" en órdenes de Muestras (antes de PT-02).

### Blockers/Concerns

- Las sesiones en la nube no alcanzan SQL Server: lo que requiera datos reales (schema físico, baseline de tiempos, valores de `area`) vuelve como script/runbook para correr en Laragon.
- PT fase 4-ux sigue con PLAN `.superseded`; se replanea cuando toque (Ola 3).
- Índices `IX_ReqProgramaTejido_*` citados en el modelo no existen en migraciones — verificar en PT 01.2.

## Session Continuity

Last session: 2026-09-24
Stopped at: 11 y PT-01 integradas; falta fase 10 (CI) para G0. Siguiente: integrar 10, validar CI verde y abrir Ola 1 (12, 14→13, 15-01, PT 01.1→02).
Resume file: None
