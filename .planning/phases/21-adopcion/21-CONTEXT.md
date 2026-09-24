# Fase 21 — Adopción, limpieza y documentación

**Track:** ADOP · **Ola:** 4 · **IDs:** ADOP-01..06 · **Rama:** `claude/21-adopcion`
**Depende de:** G3 + ≥ 30 días de telemetría. **Antes de ejecutar:** escribir `21-01-PLAN.md`.

## Alcance
- ADOP-01 Hits de los 54 redirects 301 legados (medidos desde Ola 2 con `Pulse::record('legado', $uri)->count()`, fallback log) y de rutas/vistas candidatas.
- ADOP-02 Borrar rutas/vistas/assets con **0 hits en 30 días** (`SYSMonVista` + Pulse), con lista y evidencia en SUMMARY.
- ADOP-03 Quitar `predis/predis`, `laravel/sail` (verificar con `composer-unused`), adaptador `window.toastr`, puentes `window.*` temporales de 19-xx sin llamadores, `getDeviceIdentifier()` de `device_helpers.php` (reemplazado por la cookie `towell_disp`).
- ADOP-04 Mover `resources/js/programa-tejido/filter-engine.ts` a `resources/js/utils/`.
- ADOP-05 `CLAUDE.md`, `AGENTS.md` (skill `claude-md-management:claude-md-improver`), `docs/cerebro-towell/**`, `docs/documentacion-*` al día (http/notify/componentes/monitoreo/protocolo).
- ADOP-06 `docs/cerebro-towell/Auditoria/inventario-bugs.md` con estados finales.

## Criterios de éxito
- Ratchet en su mínimo histórico; `composer-unused` limpio; docs sin afirmaciones falsas.
