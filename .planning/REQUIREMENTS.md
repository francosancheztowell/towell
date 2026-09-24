# Requirements: Towell — Refactor integral 2026

**Defined:** 2026-07-22 (track PT), ampliado 2026-09-24 (refactor integral)
**Core Value:** Planta y planeación trabajan más rápido y sin fricción, y Sistemas ve lo que pasa en producción, sin romper invariantes de dominio.

> Los requisitos `PT-*` pertenecen al track Programa Tejido (fases 01–07). Los demás a las fases 10–21. Trazabilidad fase ↔ ID en `ROADMAP.md`.

## v1 Requirements — Refactor integral 2026 (fases 10–21)

### Base (fase 10)

- [ ] **BASE-01**: CI corre `php artisan test` (sqlite) además de typecheck/test:js/build; los 41 fallos actuales se corrigen sin saltar tests (lo que dependa de SQL Server real va a una suite `SqlServer` local documentada).
- [ ] **BASE-02**: Larastan nivel 5 con baseline y Pint `--test` solo sobre archivos cambiados, en CI.
- [ ] **BASE-03**: `scripts/ratchet.mjs` + baseline: los conteos de deuda (`fetch(`, `Swal.fire`, `toastr.`, `onclick=`, `innerHTML=`, `X-CSRF-TOKEN`, `<script>` inline, `getMessage()` en JSON, `bg-opacity-`) no pueden subir.
- [ ] **BASE-04**: Línea base de rendimiento de 15 pantallas top (TTFB, queries, KB HTML/JS, chunks) con runbook repetible.
- [ ] **BASE-05**: Higiene: vistas/servicios muertos, rutas debug, certificados públicos y archivos sin referencia fuera.
- [ ] **BASE-06**: Página QR de usuarios funcional (librería por npm/Vite).
- [ ] **BASE-07**: Conexión de Ventas (`sqlsrv_Reportes_Towell`) restaurada por el flujo `scripts/db-config.ps1` + `.env.example`.
- [ ] **BASE-08**: Jobs fallidos persistidos (`database-uuids`) y log diario con rotación.
- [ ] **BASE-09**: Tabla `cache` solo si prod usa store `database` (decisión documentada).
- [ ] **BASE-10**: `tsconfig` incluye todo `resources/js/**/*.ts` (exclude temporal documentado).
- [ ] **BASE-11**: `CLAUDE.md` sin datos falsos (`modulos_v3`, `routes/ai.php`).
- [ ] **BASE-12**: SessionStart hook que instala dependencias para sesiones web.

### Monitoreo (fases 11–14)

- [ ] **MON-01**: Tablas `SYSMonDispositivo`, `SYSMonSesion`, `SYSMonVista`, `SYSMonError`, `SYSMonErrorEvento`, `SYSMonAcceso` (migración + `.sql` espejo) y modelos.
- [ ] **MON-02**: Identidad estable de dispositivo por cookie UUID servidor; "touch" de actividad cacheado en cada request autenticado.
- [ ] **MON-03**: Registro de login, restauración por remember, logout y logout de dispositivo como eventos de acceso + sesión.
- [ ] **MON-04**: Login fallido registrado y rate limit de login con registro de bloqueo.
- [ ] **MON-05**: Tiempo de servidor y consultas por request HTML (header `Server-Timing`).
- [ ] **MON-06**: Excepciones PHP agrupadas por huella en conexión separada, sin recursión y sin lanzar nunca.
- [ ] **MON-07**: Respuestas ≥ 500 que no pasan por el handler (catch que tragan) también quedan registradas.
- [ ] **MON-08**: Endpoints de telemetría del contrato (`11-CONTRACT.md`) con kill switch.
- [ ] **MON-09**: Cierre remoto de sesión por dispositivo (solo esa tablet) y logout normal por dispositivo.
- [ ] **MON-10**: (2026-09-24, owner: destinatario fijo) Alertas de errores solo por **correo** a `francost15@gmail.com` (`config('monitoreo.errores.correo_alertas')`, env `MONITOREO_ALERTA_CORREO`); sin Telegram ni suscriptores de SYSMensajes.
- [ ] **MON-11**: Alerta por correo (Resend) en error nuevo o regresión, con tope por hora, sin bloquear la respuesta.
- [ ] **MON-12**: Retención/poda programada de los datos de monitoreo.
- [ ] **MON-13**: Página 500 muestra un código de referencia rastreable.
- [ ] **MON-14**: `config/monitoreo.php` con intervalos, umbrales, retención, kill switch y áreas admin; Gate `admin` por área (Sistemas).
- [ ] **MON-15**: Latido del cliente (visibilidad, inactividad, página actual) y fin de vista con `sendBeacon`.
- [ ] **MON-16**: Métricas de carga por vista (Navigation Timing + Server-Timing), incluida navegación suave de Livewire.
- [ ] **MON-17**: Captura de errores JS, promesas rechazadas, errores HTTP y fallos de requests Livewire, deduplicados.
- [ ] **MON-18**: Señal de conectividad (`towell:conexion`) para UX.
- [ ] **MON-19**: Nombre de dispositivo persistido en servidor (migra el de localStorage).
- [ ] **MON-20**: Tests JS del cliente y presupuesto ≤ 5 KB gz.
- [ ] **MON-21**: Rutas `/admin/*` protegidas por Gate `admin`; enlace visible solo para Sistemas.
- [ ] **MON-22**: Vista "En línea" en tiempo casi real con acciones de cierre remoto y renombrar.
- [ ] **MON-23**: Historial de sesiones con duración.
- [ ] **MON-24**: Navegación por dispositivo con tiempo por página.
- [ ] **MON-25**: Errores agrupados con detalle, eventos y flujo de estado.
- [ ] **MON-26**: Rendimiento p50/p95 por ruta (servidor y cliente) con comparación semanal.
- [ ] **MON-27**: Accesos, fallidos y bloqueos.
- [ ] **MON-28**: Auditoría de acciones admin (`ActorId`).
- [ ] **MON-29**: Laravel Pulse instalado sobre conexión SQLite dedicada.
- [ ] **MON-30**: Pulse en `/admin/pulse` con el mismo Gate y resolución de usuario Towell.
- [ ] **MON-31**: Recorders de Pulse seleccionados (lentos, usuarios) sin duplicar errores.
- [ ] **MON-32**: Fallback documentado si prod no tiene `pdo_sqlite`.

### Frontend (fase 15)

- [ ] **FE-01**: `utils/http.ts` con Accept JSON, manejo único de 419 y evento `towell:http-error`.
- [ ] **FE-02**: `utils/notifications.ts` con toast nativo accesible; Swal solo para modales.
- [ ] **FE-03**: `utils/format.ts` único (`escapeHtml`, `debounce`, formateadores es-MX).
- [ ] **FE-04**: `utils/dom.ts` (`qs`, `qsa`, `delegate`).
- [ ] **FE-05**: Tipos globales (`window.http`, `notify`, `Swal`, `Livewire`).
- [ ] **FE-06**: `tsconfig` con `erasableSyntaxOnly` + `verbatimModuleSyntax`; `tejido/inventario-telas.ts` tipado.
- [ ] **FE-07**: Tom Select reemplaza Select2 (wrapper `utils/combobox.ts`).
- [ ] **FE-08**: Toastr reemplazado por `notify`.
- [ ] **FE-09**: jQuery, Select2, Toastr y el shim de `bootstrap.js` eliminados.
- [ ] **FE-10**: Vite sin chunk vendor global; entradas por glob para módulos.
- [ ] **FE-11**: Librerías CDN (html2canvas, pdf.js) por npm con import dinámico.
- [ ] **FE-12**: Regresiones Tailwind v4 corregidas (`bg-opacity-*`, keyframes `spin`, fuente FA7, `showToast` pisado).

### Sistema de componentes (fase 16)

- [ ] **DS-01**: Tokens de diseño en `@theme` (texto ≥ 12 px, targets ≥ 44 px).
- [ ] **DS-02**: Modal único sobre `<dialog>` nativo.
- [ ] **DS-03**: Tabla base (`x-ui.table`) compuesta por `x-tabla`.
- [ ] **DS-04**: Campo de formulario con label/error/hint.
- [ ] **DS-05**: Botón unificado con `x-navbar.button-*`.
- [ ] **DS-06**: Badge.
- [ ] **DS-07**: Spinner/skeleton y loader global único.
- [ ] **DS-08**: Empty state adoptado.
- [ ] **DS-09**: Flash messages.
- [ ] **DS-10**: Barra de filtros reutilizable.
- [ ] **DS-11**: Galería `/dev/ui-kit` + receta de componentes.
- [ ] **DS-12**: Piloto: catálogos de atadores consolidados + `CatalogBase` en TS.

### UX (fase 17)

- [ ] **UX-01**: Flash visible en el layout. **UX-02**: `<title>` por página. **UX-03**: un solo `h1`.
- [ ] **UX-04**: Pinch-zoom habilitado (salvo andón). **UX-05**: `user-select:none` solo en chrome.
- [ ] **UX-06**: Acciones de clic derecho también por long-press/botón "⋮". **UX-07**: texto ≥ 12 px.
- [ ] **UX-08**: `lang/es` + locale `es`. **UX-09**: páginas de error estilizadas con código de referencia.
- [ ] **UX-10**: Regla de contraseña única. **UX-11**: 419 unificado. **UX-12**: banner sin conexión.
- [ ] **UX-13**: Duraciones de notificación consistentes. **UX-14**: `aria-label` en botones de ícono. **UX-15**: foco visible.
- [ ] **UX-16**: Sin mojibake en mensajes al usuario.
- [ ] **UX-17**: Auditoría UX exhaustiva de las 25 pantallas más usadas (por telemetría).
- [ ] **UX-18**: Checklist UX por pantalla aplicado en cada sesión 19-xx.

### Performance (fase 18)

- [ ] **PERF-01**: Drivers de sesión/cache decididos con el `.env` real de prod.
- [ ] **PERF-02**: OPcache y realpath cache verificados. **PERF-03**: `optimize` en cada deploy.
- [ ] **PERF-04**: `moduleNameForRoute` memoizado/cacheado.
- [ ] **PERF-05**: Lazy loading detectado fuera de prod (log). **PERF-06**: queries lentas registradas.
- [ ] **PERF-07**: `SetSqlContextInfo` medido y excluido de telemetría sin cambiar su semántica.
- [ ] **PERF-08**: N+1 fuera de PT eliminados. **PERF-09**: predicados no-sargables y LIKE con comodín inicial corregidos con plan de ejecución.
- [ ] **PERF-10**: Índices por `.sql` revisado. **PERF-11**: `CodificacionController@getAll` proyectado/paginado.
- [ ] **PERF-12**: Costo del observer de `ReqProgramaTejido` atendido en PT 05/06.

### Módulos (fase 19)

- [ ] **MIG-<MOD>-01**: JS inline de las vistas del módulo movido a `resources/js/modulos/<mod>/**` en TS (inline < 50 líneas por vista).
- [ ] **MIG-<MOD>-02**: Adopción de `http`/`notify`/`format` y componentes DS; ratchet baja.
- [ ] **MIG-<MOD>-03**: Duplicados del módulo consolidados (BPM, secuencias, calificar-julios, catálogos).
- [ ] **MIG-<MOD>-04**: Livewire solo donde la guía lo indica.

### Arquitectura y seguridad (fases 10, 20)

- [ ] **SEC-01**: SQL interpolado parametrizado. **SEC-02**: `trustProxies` acotado a la topología real.
- [ ] **ARQ-01**: Services fuera de `app/Http/Controllers`. **ARQ-02**: folios solo por `FolioHelper`.
- [ ] **ARQ-03**: turnos solo por `TurnoHelper`. **ARQ-04**: modelo `UrdEngNucleos` único. **ARQ-05**: Form Requests en mutaciones tocadas.
- [ ] **SEC-04**: Respuesta JSON 5xx central (mensaje genérico + `trace_id`).
- [ ] **SEC-05**: `module.permission` con modo `auditar`. **SEC-06**: enforce por módulo con tests.
- [ ] **SEC-07**: Sin `getMessage()` crudo hacia el usuario.

### Adopción (fase 21)

- [ ] **ADOP-01**: Hits de redirects legados medidos. **ADOP-02**: rutas/vistas con 0 hits en 30 días retiradas.
- [ ] **ADOP-03**: Dependencias y adaptadores temporales retirados. **ADOP-04**: `filter-engine.ts` en utils.
- [ ] **ADOP-05**: `CLAUDE.md`/`AGENTS.md`/`docs/cerebro-towell` al día. **ADOP-06**: `inventario-bugs.md` actualizado.

## v1 Requirements — Track PT (Programa Tejido)

### Contexto y contratos

- [ ] **PT-CON-01**: Programa y Muestras tienen contexto, tablas, rutas, preferencias y capacidades explícitas.
- [ ] **PT-CON-02**: Rutas/payloads/respuestas legacy están caracterizados antes de refactorizar.

### Dominio

- [ ] **PT-DOM-01**: Posición, `EnProceso`, `Ultimo`, fechas, líneas y grupos conservan sus invariantes.
- [ ] **PT-DOM-02**: Fórmulas y sincronización CatCodificados conservan semántica y son observables ante fallo.

### Lectura

- [ ] **PT-READ-01**: La lectura v2 usa Request, ReadService y Resource con paginación/proyección.

### UI (Livewire)

- [ ] **PT-UI-01**: (2026-09-24: **mismo diseño visual** y solo si mejora TTFB/KB/interacción vs `04-PERF-MEDIDO.md`) UI v2 es Livewire — componente(s) siguiendo el patrón `Crudo/MachineDetail.php` (datasets grandes como `#[Computed]`, no propiedad pública) y `UrdEng/ProgramBoard.php` (reorder/drag-drop).
- [ ] **PT-UI-02**: La tabla ofrece presets, filtros claros, acciones accesibles y estados explícitos (loading/error/empty).

### Mutaciones

- [ ] **PT-MUT-01**: Mutaciones se extraen verticalmente a FormRequests y servicios por caso de uso.

### Operaciones

- [ ] **PT-OPS-01**: Liberar/finalizar/imports/integraciones se migran como planes independientes.

### Rollout

- [ ] **PT-ROL-01**: Cada corte tiene feature flag, telemetría, gate y rollback probado.

### Deduplicación de backend (hallazgo de auditoría 2026-08-05)

- [ ] **PT-DUP-01**: Eliminar las 3 implementaciones competidoras del patrón suppress/restore de observers (modelo, `ProgramaTejidoObserverHelper`, copias inline) — todos los call sites usan `ReqProgramaTejido::suppressObservers()/restoreObservers()`.
- [ ] **PT-DUP-02**: Centralizar el árbol de fallback de `FechaFinal` (duplicado 6×) en `TejidoHelpers::resolverFechaFinal()`.
- [ ] **PT-DUP-03**: Unificar el chequeo de flag "Ultimo" (3 variantes inconsistentes, una de ellas más débil = bug latente) en `ReqProgramaTejido::esUltimo()`.
- [ ] **PT-DUP-04**: Reemplazar los 20+ sitios que rearman `where('SalonTejidoId',...)->where('NoTelarId',...)` a mano por los scopes `scopeSalon()`/`scopeTelar()` ya existentes en el modelo.

### Rendimiento

- [ ] **PT-PERF-01**: Índices faltantes creados — `ReqProgramaTejidoLine` no tiene ningún índice sobre `ProgramaId`/`Fecha`; `ReqProgramaTejido` sin índice directo sobre `(NoTelarId, Posicion)`.
- [ ] **PT-PERF-02**: Eliminar N+1 confirmados en `ProgramaTejidoController::store()` (query de posición por telar en loop) y `CortesEficienciaController::obtenerDatosVisualizacionPorFecha()` (3 queries por fecha en rango).

## v2 Requirements

Deferido — no en el roadmap actual.

- **PT-DUP-05**: Unificar los dos algoritmos de "siguiente posición disponible" (DB-backed vs in-memory en `DuplicarTejido`).
- **PT-DUP-06**: Extraer `classifyDensidad()` centralizado en `QueryHelpers` (3 copias inline).

## Out of Scope

| Feature | Reason |
|---------|--------|
| Reescritura de los 28 controladores de negocio | Cubiertos por 22 tests; el riesgo de reescribir supera la ganancia de UX |
| React/TanStack en UI v2 | Livewire cubre la necesidad sin dependencia nueva mayor |
| Retiro del código legacy sin telemetría de cero uso | Ver fase 07 — solo se retira con evidencia y aprobación explícita |
