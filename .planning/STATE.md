# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-09-24)

**Core value:** Planta y planeación trabajan más rápido y sin fricción, y Sistemas ve qué pasa en producción, sin romper invariantes de dominio.
**Current focus:** Ola 0 — fases 10 (base), 11 (monitoreo servidor) y PT 01 (guardrails), en sesiones paralelas.
**Protocolo:** `.planning/PROTOCOLO-SESIONES.md` (propiedad de archivos, orden de merge, gates).

## Current Position

Ola: 0 de 4
Sesiones activas: `claude/10-base`, `claude/11-mon-servidor`, `claude/pt-01-guardrails`
Status: En ejecución
Last activity: 2026-09-24 — Proyecto ampliado a "Refactor integral 2026". Roadmap con tracks BASE/MON/FE/DS/UX/PERF/MIG/ARQ-SEC/ADOP + track PT. Fases 10–21 creadas (10 y 11 con PLAN; 12–21 con CONTEXT). Contrato de monitoreo en `phases/11-mon-servidor/11-CONTRACT.md`.

Progress: [░░░░░░░░░░] 0%

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

### Pending Todos (owner)

- Confirmar en prod (192.168.2.15): `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `pdo_sqlite` habilitado, si hay proxy inverso, si la LAN sirve HTTPS.
- `SELECT DISTINCT area FROM SYSUsuario` para confirmar el valor exacto de "Sistemas".
- Aviso de privacidad del monitoreo (propuesta: leyenda discreta en login).
- Worker de colas en Windows (propuesta: `queue:work --stop-when-empty --max-time=50` desde `scheduler.bat`).
- PT 01 tiene el checkpoint bloqueante 01.3 (decisión Programa vs Muestras).

### Blockers/Concerns

- Las sesiones en la nube no alcanzan SQL Server: lo que requiera datos reales (schema físico, baseline de tiempos, valores de `area`) vuelve como script/runbook para correr en Laragon.
- PT fase 4-ux sigue con PLAN `.superseded`; se replanea cuando toque (Ola 3).
- Índices `IX_ReqProgramaTejido_*` citados en el modelo no existen en migraciones — verificar en PT 01.2.

## Session Continuity

Last session: 2026-09-24
Stopped at: Docs GSD escritos; abriendo sesiones de Ola 0.
Resume file: None
