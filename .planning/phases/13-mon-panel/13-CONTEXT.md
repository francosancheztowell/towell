# Fase 13 — Monitoreo: panel `/admin` (Livewire)

**Track:** MON-C · **Ola:** 1→2 (arranca cuando esté mergeado el esquema de 11-01) · **IDs:** MON-21..28 · **Rama:** `claude/13-mon-panel`
**Depende de:** `../11-mon-servidor/11-CONTRACT.md` §2, §5, §6, §7 y tablas/modelos de 11-01 (solo lectura; cambios vía HANDOFF).
**Antes de ejecutar:** escribir `13-01-PLAN.md`.

## Objetivo
Panel para el **área Sistemas** en `/admin` que muestra en tiempo casi real los dispositivos conectados (IP, usuario, página, tiempo), el historial de sesiones y navegación, el rendimiento por pantalla, los errores agrupados y los accesos, con cierre remoto de sesión por dispositivo.

## Alcance
- Componentes Livewire en `app/Livewire/Admin/**`, vistas en `resources/views/livewire/admin/**`, host views en `resources/views/modulos/admin/**`.
- `/admin` **En línea** (MON-22): tarjetas/tabla de dispositivos en línea / inactivos / desconectados (definiciones del contrato §6), usuario, área, IP, tipo/modelo, SO/navegador, página actual, tiempo en sesión, última actividad, front desactualizado; acciones **Cerrar sesión** (usa `CierreRemotoService::solicitar`, confirmación con `notify.confirm`) y **Renombrar**. `wire:poll.visible` con `config('monitoreo.poll_seconds')` (≥ 10), patrón `config/program-board.php` + `resources/views/livewire/urd-eng/program-board.blade.php`.
- `/admin/sesiones` (MON-23): historial filtrable por usuario/dispositivo/fecha, duración, origen, motivo de fin.
- `/admin/navegacion` (MON-24): línea de tiempo por dispositivo o usuario con tiempo visible por página.
- `/admin/rendimiento` (MON-26): p50/p95 de `ServidorMs` y `CargaMs` por ruta, 7 días vs semana previa, rutas lentas resaltadas; `PERCENTILE_CONT` en SQL Server con fallback AVG/MAX en sqlite (tests). Link a `/admin/pulse`.
- `/admin/errores` + `/admin/errores/{id}` (MON-25): agrupados por huella, filtros por estado/origen, detalle con eventos (usuario, dispositivo, URL, versión), cambiar estado (visto/resuelto/ignorado) con nota.
- `/admin/accesos` (MON-27): logins, fallidos, bloqueos, logout remoto, filtros.
- Toda acción admin → `SYSMonAcceso` tipo `admin_accion` con `ActorId` (MON-28). Enlace "Admin" en `user-modal` solo con `@can('admin')` (MON-21; coordinar con MON-B, dueño de `user-modal`, vía HANDOFF si 12 ya lo tocó).
- Opcional: resumen diario 07:00 por Telegram (reusar `ErrorTelegramNotifier`).

## Reuso (ponytail)
`app/Livewire/Concerns/ConTabla.php` + `resources/views/components/tabla*.blade.php` para todas las tablas; `x-navbar.button-*`, `x-ui.*` existentes; `notify` para feedback; skill `dataviz` para gráficas (Chart.js ya está en `resources/js/charts.js`).

## Criterios de éxito
- Usuario de Sistemas ve su propia tablet en línea en ≤ 15 s; otra área → 403.
- Cierre remoto desde el panel saca solo esa tablet en ≤ 60 s.
- Consultas del panel < 200 ms con 90 días de datos (índices del contrato); la vista En línea no escanea `SYSMonVista`.
- Tests Livewire de cada componente (render, filtros, acciones, autorización).

## Skills
Plugin `frontend-design`, `dataviz`, `security-review`, `code-review`, `run`.
