# Towell — Refactor integral 2026

## What This Is

Towell es una app Laravel 12 de planeación y control de producción textil (SQL Server, Livewire 4, Tailwind v4, Vite/TS), usada a diario en planta: tablets compartidas en piso, pantallas andón sin atender, PCs de planeación y mantenimiento.

Este proyecto GSD cubre el **refactor integral 2026**: rendimiento y velocidad, estructura, componentes reutilizables unificados, migración de JS a TypeScript y a Livewire donde aplica, retiro de librerías viejas, análisis exhaustivo de UX y un **panel de administración con monitoreo** (dispositivos conectados con IP, tiempo, navegación, rendimiento, errores, accesos, cierre remoto).

El milestone anterior, **Programa Tejido (PT)** (`ReqProgramaTejido` y superficies vinculadas: Muestras, Redbooth, liberación, finalización, balanceo, CatCodificados), queda como un **track** dentro de este proyecto, con su Core Value y sus fases 01–07 intactas.

## Core Value

El personal de planta y planeación trabaja más rápido y sin fricción, y el área de Sistemas **ve** qué pasa en producción (quién está conectado, desde dónde, qué pantallas son lentas, qué falla) — sin romper ninguna invariante de dominio que hoy consumen los módulos.

Core Value del track PT (sin cambios): el planeador opera Programa Tejido más rápido y sin fricción sin romper invariantes (posición, EnProceso, Ultimo, fechas, líneas, OrdCompartida).

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] Ver `.planning/REQUIREMENTS.md`

### Out of Scope

- Reescritura completa de controllers de negocio: se extraen servicios y se deduplica por caso de uso, no se reescribe.
- React/Vue/Inertia/TanStack: Livewire + TS cubre la reactividad sin dependencia mayor nueva.
- WebSockets/Reverb: el tiempo real del panel es `wire:poll.visible` (servidor Windows/Laragon sin daemon extra).
- SaaS externo de errores (Sentry): los datos no salen de planta.
- Renombrar `catalagos/` o la columna `reigstrar` sin plan de migración propio.
- Migrar a Livewire Liberar órdenes, Excel one-shot o dumps de reportes (guía Livewire).
- Retirar legado sin telemetría de cero uso.

## Context

- Stack: Laravel 12.53, Livewire 4.3.3 (17 componentes: Crudo, Trazabilidad, UrdEng/ProgramBoard, Desarrolladores/Captura, Mantenimiento/CatalogoFallas, Mecánicos/VerificaMaquina, Tejedores/CatalogoCalibres, `Concerns/ConTabla`), Tailwind v4 (CSS-first), Vite 6, TypeScript 7 (`tsconfig` estricto, 31 `.ts`), SQL Server (`sqlsrv`, `sqlsrv_ti` = AX/TI_PRO, `sqlsrv_tow_tow`), producción en Windows/Laragon.
- Auditorías vivas: `docs/cerebro-towell/Auditoria/*` (BUG-001..034, AuthZ gaps, deuda JS), guía `docs/cerebro-towell/Arquitectura/livewire-cuando-si-cuando-no.md`, medición `phases/04-ux-grid/04-PERF-MEDIDO.md`.
- Foto de la deuda (2026-09-24, verificada en código):
  - Frontend: ~49k líneas de JS inline en 135 blades; 211 `fetch` crudos vs 34 `http.*`; 707 `Swal.fire`; ~7 implementaciones de `showToast`; `escapeHtml` ×13; 77 modales hechos a mano; jQuery + Select2 (RC 2021) + Toastr (2018) en el chunk global para solo 5 archivos; `programa-tejido/index.js` 13 218 líneas; regresiones Tailwind v4 (`bg-opacity-*`).
  - Backend: 142 controllers (54.6k líneas, 11 > 1 000); 22 archivos tipo service dentro de Controllers; ~401 `catch` que devuelven `getMessage()`; ~106 rutas de escritura sin `module.permission`; N+1 identificados; `failed.driver = null`; log sin rotación; `SetSqlContextInfo` ejecuta un SP en cada request.
  - Monitoreo: **no existe** (ni sesiones, ni dispositivos, ni errores, ni `window.onerror`, ni registro de login). `app/Helpers/device_helpers.php` solo pinta info en `user-modal`.
  - Tests: 183 archivos (1 098 métodos) en sqlite en memoria; 41 fallan por tablas faltantes; CI solo corre frontend.
- **Laravel Pulse no soporta SQL Server** como almacenamiento → conexión SQLite dedicada; el detalle de monitoreo va en tablas propias `SYSMon*` en SQL Server.

## Constraints

- **Planta primero:** ningún cambio rompe invariantes de dominio ni flujos de piso; convivencia legacy/v2 con rollback donde aplique.
- **SQL Server es la fuente física:** no inferir schema solo desde migrations; lo que requiera datos reales se entrega como script para correr en Laragon.
- **Producción = SQL Server 2008 R2** (descubierto en fase 13): nada de funciones 2012+ (`PERCENTILE_CONT`, `OFFSET/FETCH`, `STRING_AGG`, `TRY_CONVERT`, `IIF`, `CONCAT`, `FORMAT`, `THROW`); paginación con `app/Support/PaginacionCompat.php` / `ROW_NUMBER()`.
- **Windows/Laragon:** sin Redis ni daemons extra obligatorios; colas vía `afterResponse` o scheduler.
- **Tablets compartidas + remember-me siempre activo** (decisión de negocio para andón).
- **Sesiones paralelas:** propiedad de archivos y orden de merge en `.planning/PROTOCOLO-SESIONES.md`.

## Key Decisions

| Fecha | Decisión | Razón |
|---|---|---|
| 2026-07-22 | PT: migración incremental con legacy/v2 por feature flag, sin big-bang | Programa Tejido es crítico y diario |
| 2026-07-22 | PT: Programa y Muestras son superficies con capacidades declaradas | Evitar paridad física supuesta |
| 2026-08-05 | PT: UI v2 en Livewire | Convenciones Livewire ya establecidas (Crudo, UrdEng) |
| 2026-08-05 | PT: los 28 controllers de negocio no se reescriben | Cubiertos por tests; riesgo sin ganancia |
| 2026-09-24 | Proyecto ampliado a "Refactor integral 2026"; PT pasa a track | Pedido del owner: roadmap completo |
| 2026-09-24 | Ejecución en sesiones Claude paralelas con propiedad de archivos | Pedido del owner; evita conflictos |
| 2026-09-24 | Tiempo real del panel con `wire:poll.visible` (sin Reverb) | Cero infraestructura nueva en Windows |
| 2026-09-24 | Errores/rendimiento: Laravel Pulse (SQLite) + tablas propias `SYSMon*` | Pulse no soporta sqlsrv; detalle por dispositivo necesita tablas propias |
| 2026-09-24 | Monitoreo registra sesión+dispositivo, navegación con tiempos, rendimiento servidor/cliente, acciones admin | Pedido del owner |
| 2026-09-24 | PT sí migra a Livewire **sin cambiar el diseño**, solo si mejora rendimiento medido | Pedido del owner; resuelve contradicción con la guía Livewire |
| 2026-09-24 | Logout normal y remoto = solo ese dispositivo (`logoutCurrentDevice`) | Hoy un logout invalida el remember de todas las tablets del usuario |
| 2026-09-24 | Panel de monitoreo solo para **área Sistemas**, en `/admin` (Gate por `area`) | Pedido del owner; datos sensibles (IPs, actividad) |
| 2026-09-24 | PT 01.3: Muestras se liberan con **"M"** en `OrdenTejido`/`NoProduccion`; capacidades Muestras: Redbooth B, Marbetes A, Producción A, Descarga TXT B, Finalización B, Longitudes A | Decisión del owner sobre `phases/01-guardrails/01-DECISION-PROGRAMA-MUESTRAS.md` |
| 2026-09-24 | Liberar Muestras exige permiso del módulo Muestras (idrol 5), no el de Programa (idrol 2) | Decisión del owner |
| 2026-09-24 | Alertas de errores del sistema **solo por correo a un destinatario fijo** (`francost15@gmail.com`), no por Telegram | Decisión del owner; se reusa el mailer Resend ya configurado |
| 2026-09-24 | No hay proxy delante de Laragon → se quita `trustProxies(at: '*')` (SEC-02) | Confirmado por el owner; la IP del monitoreo y del rate limit debe ser la real |
| 2026-09-24 | Formato de órdenes de Muestras: **`M` + número** (ej. `M12345`) en `CatCodificados.OrdenTejido` y `MuestrasPrograma.NoProduccion` | Confirmado por el owner |
| 2026-09-24 | Producción usa `file` para cache y sesión → no se crea tabla `cache` (BASE-09 cerrado) | Confirmado por el owner |
| 2026-09-25 | Ola 2 se abre **sin esperar G1** para lo que no depende de telemetría (15-02, 16, 20-01, 18-01 + fix MON, PT 04-perf); 17-01 y 20-02/20-03 esperan | Decisión del owner; el monitoreo aún no está desplegado |
| 2026-09-25 | Contraseñas: **no se tocan** (UX-10 fuera de 17-02) | Decisión del owner |
| 2026-09-25 | Codificación **no es duplicado** (BUG-012 reclasificado): catálogos = `ReqModelosCodificados` (modelos), codificación = `CatCodificados` (órdenes); se quedan las dos | Aclaración del owner |
| 2026-09-25 | Ola 3 se abre ya con 4 sesiones (17-02, 19-01, 19-03, PT 05); AuthZ sigue en auditar hasta tener datos de prod | Decisión del owner |
| 2026-09-25 | Avisos de Telegram secundarios por cola `database` + worker cada minuto (no `defer()`: sin PHP-FPM en Windows no libera la respuesta) | 18-03, medido |

## Working Agreements

- Español para toda la documentación del proyecto (roadmap, plans, decisiones, commits).
- **Modo ponytail:** sin abstracciones nuevas si ya existe un scope/helper/componente que resuelve lo mismo; priorizar apuntar duplicados al original.
- Medir antes y después de toda optimización.
- Nunca saltar ni desactivar tests para llegar a verde.
- PRs solo cuando el owner los pida.
