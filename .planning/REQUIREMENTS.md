# Requirements: Towell — Refactor integral 2026

**Defined:** 2026-07-22 (track PT), ampliado 2026-09-24 (refactor integral)
**Core Value:** Planta y planeación trabajan más rápido y sin fricción, y Sistemas ve lo que pasa en producción, sin romper invariantes de dominio.

> Los requisitos `PT-*` pertenecen al track Programa Tejido (fases 01–07). Los demás a las fases 10–21. Trazabilidad fase ↔ ID en `ROADMAP.md`.

## v1 Requirements — Refactor integral 2026 (fases 10–21)

### Base (fase 10)

- [x] **BASE-01**: CI corre `php artisan test` (sqlite) además de typecheck/test:js/build; los 41 fallos actuales se corrigen sin saltar tests (lo que dependa de SQL Server real va a una suite `SqlServer` local documentada).
- [x] **BASE-02**: Larastan nivel 5 con baseline y Pint `--test` solo sobre archivos cambiados, en CI.
- [x] **BASE-03**: `scripts/ratchet.mjs` + baseline: los conteos de deuda (`fetch(`, `Swal.fire`, `toastr.`, `onclick=`, `innerHTML=`, `X-CSRF-TOKEN`, `<script>` inline, `getMessage()` en JSON, `bg-opacity-`) no pueden subir.
- [x] **BASE-04**: Línea base de rendimiento de 15 pantallas top (TTFB, queries, KB HTML/JS, chunks) con runbook repetible.
- [x] **BASE-05**: Higiene: vistas/servicios muertos, rutas debug, certificados públicos y archivos sin referencia fuera.
- [x] **BASE-06**: Página QR de usuarios funcional (librería por npm/Vite).
- [x] **BASE-07**: Conexión de Ventas (`sqlsrv_Reportes_Towell`) restaurada por el flujo `scripts/db-config.ps1` + `.env.example`. → resuelto en `main` (63781bf4: `sqlsrv_Reportes_Towell` en `config/database.php`), integrado el 2026-09-25; en prod solo faltan las llaves `DB_*_REPORTES_TOWELL` del `.env` si no están.
- [x] **BASE-08**: Jobs fallidos persistidos (`database-uuids`) y log diario con rotación.
- [x] **BASE-09**: Tabla `cache` solo si prod usa store `database` (decisión documentada). → Prod usa `file` (2026-09-24): no aplica.
- [x] **BASE-10**: `tsconfig` incluye todo `resources/js/**/*.ts` (exclude temporal documentado).
- [x] **BASE-11**: `CLAUDE.md` sin datos falsos (`modulos_v3`, `routes/ai.php`).
- [x] **BASE-12**: SessionStart hook que instala dependencias para sesiones web.

### Monitoreo (fases 11–14)

- [x] **MON-01**: Tablas `SYSMonDispositivo`, `SYSMonSesion`, `SYSMonVista`, `SYSMonError`, `SYSMonErrorEvento`, `SYSMonAcceso` (migración + `.sql` espejo) y modelos.
- [x] **MON-02**: Identidad estable de dispositivo por cookie UUID servidor; "touch" de actividad cacheado en cada request autenticado.
- [x] **MON-03**: Registro de login, restauración por remember, logout y logout de dispositivo como eventos de acceso + sesión.
- [x] **MON-04**: Login fallido registrado y rate limit de login con registro de bloqueo.
- [x] **MON-05**: Tiempo de servidor y consultas por request HTML (header `Server-Timing`).
- [x] **MON-06**: Excepciones PHP agrupadas por huella en conexión separada, sin recursión y sin lanzar nunca.
- [x] **MON-07**: Respuestas ≥ 500 que no pasan por el handler (catch que tragan) también quedan registradas.
- [x] **MON-08**: Endpoints de telemetría del contrato (`11-CONTRACT.md`) con kill switch.
- [x] **MON-09**: Cierre remoto de sesión por dispositivo (solo esa tablet) y logout normal por dispositivo.
- [x] **MON-10**: (2026-09-24, owner: destinatario fijo) Alertas de errores solo por **correo** a `francost15@gmail.com` (`config('monitoreo.errores.correo_alertas')`, env `MONITOREO_ALERTA_CORREO`); sin Telegram ni suscriptores de SYSMensajes.
- [x] **MON-11**: Alerta por correo (Resend) en error nuevo o regresión, con tope por hora, sin bloquear la respuesta.
- [x] **MON-12**: Retención/poda programada de los datos de monitoreo.
- [x] **MON-13**: Página 500 muestra un código de referencia rastreable.
- [x] **MON-14**: `config/monitoreo.php` con intervalos, umbrales, retención, kill switch y áreas admin; Gate `admin` por área (Sistemas).
- [x] **MON-15**: Latido del cliente (visibilidad, inactividad, página actual) y fin de vista con `sendBeacon`.
- [x] **MON-16**: Métricas de carga por vista (Navigation Timing + Server-Timing), incluida navegación suave de Livewire.
- [x] **MON-17**: Captura de errores JS, promesas rechazadas, errores HTTP y fallos de requests Livewire, deduplicados.
- [x] **MON-18**: Señal de conectividad (`towell:conexion`) para UX.
- [x] **MON-19**: Nombre de dispositivo persistido en servidor (migra el de localStorage).
- [x] **MON-20**: Tests JS del cliente y presupuesto ≤ 5 KB gz.
- [x] **MON-21**: Rutas `/admin/*` protegidas por Gate `admin`; enlace visible solo para Sistemas.
- [x] **MON-22**: Vista "En línea" en tiempo casi real con acciones de cierre remoto y renombrar.
- [x] **MON-23**: Historial de sesiones con duración.
- [x] **MON-24**: Navegación por dispositivo con tiempo por página.
- [x] **MON-25**: Errores agrupados con detalle, eventos y flujo de estado.
- [x] **MON-26**: Rendimiento p50/p95 por ruta (servidor y cliente) con comparación semanal.
- [x] **MON-27**: Accesos, fallidos y bloqueos.
- [x] **MON-28**: Auditoría de acciones admin (`ActorId`).
- [x] **MON-29**: Laravel Pulse instalado sobre conexión SQLite dedicada.
- [x] **MON-30**: Pulse en `/admin/pulse` con el mismo Gate y resolución de usuario Towell.
- [x] **MON-31**: Recorders de Pulse seleccionados (lentos, usuarios) sin duplicar errores.
- [x] **MON-32**: Fallback documentado si prod no tiene `pdo_sqlite`.

### Frontend (fase 15)

- [x] **FE-01**: `utils/http.ts` con Accept JSON, manejo único de 419 y evento `towell:http-error`.
- [x] **FE-02**: `utils/notifications.ts` con toast nativo accesible; Swal solo para modales.
- [x] **FE-03**: `utils/format.ts` único (`escapeHtml`, `debounce`, formateadores es-MX).
- [x] **FE-04**: `utils/dom.ts` (`qs`, `qsa`, `delegate`).
- [x] **FE-05**: Tipos globales (`window.http`, `notify`, `Swal`, `Livewire`).
- [x] **FE-06**: `tsconfig` con `erasableSyntaxOnly` + `verbatimModuleSyntax`; `tejido/inventario-telas.ts` tipado.
- [x] **FE-07**: Tom Select reemplaza Select2 (wrapper `utils/combobox.ts`).
- [x] **FE-08**: Toastr reemplazado por `notify`.
- [x] **FE-09**: jQuery, Select2, Toastr y el shim de `bootstrap.js` eliminados.
- [x] **FE-10**: Vite sin chunk vendor global; entradas por glob para módulos.
- [x] **FE-11**: Librerías CDN (html2canvas, pdf.js) por npm con import dinámico.
- [x] **FE-12**: Regresiones Tailwind v4 corregidas (`bg-opacity-*`, keyframes `spin`, fuente FA7, `showToast` pisado).

### Sistema de componentes (fase 16)

- [x] **DS-01**: Tokens de diseño en `@theme` (texto ≥ 12 px, targets ≥ 44 px).
- [x] **DS-02**: Modal único sobre `<dialog>` nativo.
- [x] **DS-03**: Tabla base (`x-ui.table`) compuesta por `x-tabla`.
- [x] **DS-04**: Campo de formulario con label/error/hint.
- [x] **DS-05**: Botón unificado con `x-navbar.button-*`.
- [x] **DS-06**: Badge.
- [x] **DS-07**: Spinner/skeleton y loader global único.
- [x] **DS-08**: Empty state adoptado.
- [x] **DS-09**: Flash messages.
- [x] **DS-10**: Barra de filtros reutilizable.
- [x] **DS-11**: Galería `/dev/ui-kit` + receta de componentes.
- [x] **DS-12**: Piloto: catálogos de atadores consolidados + `CatalogBase` en TS.

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

- [x] **PERF-01**: Drivers de sesión/cache decididos con el `.env` real de prod.
- [x] **PERF-02**: OPcache y realpath cache verificados. **PERF-03**: `optimize` en cada deploy.
- [x] **PERF-04**: `moduleNameForRoute` memoizado/cacheado.
- [x] **PERF-05**: Lazy loading detectado fuera de prod (log). **PERF-06**: queries lentas registradas.
- [ ] **PERF-07**: `SetSqlContextInfo` medido y excluido de telemetría sin cambiar su semántica. → medición lista (Server-Timing `ctx` + Pulse `contexto_sql`, 18-01); la decisión espera 1 semana de datos de prod (runbook `deploy.md` §6).
- [ ] **PERF-08**: N+1 fuera de PT eliminados. **PERF-09**: predicados no-sargables y LIKE con comodín inicial corregidos con plan de ejecución.
- [ ] **PERF-10**: Índices por `.sql` revisado. **PERF-11**: `CodificacionController@getAll` proyectado/paginado.
- [ ] **PERF-12**: Costo del observer de `ReqProgramaTejido` atendido en PT 05/06.

### Módulos (fase 19)

- [ ] **MIG-<MOD>-01**: JS inline de las vistas del módulo movido a `resources/js/modulos/<mod>/**` en TS (inline < 50 líneas por vista).
- [ ] **MIG-<MOD>-02**: Adopción de `http`/`notify`/`format` y componentes DS; ratchet baja.
- [ ] **MIG-<MOD>-03**: Duplicados del módulo consolidados (BPM, secuencias, calificar-julios, catálogos).
- [ ] **MIG-<MOD>-04**: Livewire solo donde la guía lo indica.

### Arquitectura y seguridad (fases 10, 20)

- [x] **SEC-01**: SQL interpolado parametrizado (auditado: 0 interpolaciones de input; ratchet vigila 8 expresiones internas). **SEC-02**: `trustProxies` acotado a la topología real → sin proxy: se quita (sesión 14→13).
- [x] **ARQ-01**: Services fuera de `app/Http/Controllers`. **ARQ-02**: folios solo por `FolioHelper`.
- [ ] **ARQ-03**: turnos solo por `TurnoHelper`. **ARQ-04**: modelo `UrdEngNucleos` único. **ARQ-05**: Form Requests en mutaciones tocadas. → ARQ-03 y ARQ-04 hechas (20-01); ARQ-05 va en cada 19-xx.
- [ ] **SEC-04**: Respuesta JSON 5xx central (mensaje genérico + `trace_id`).
- [ ] **SEC-05**: `module.permission` con modo `auditar`. **SEC-06**: enforce por módulo con tests.
- [ ] **SEC-07**: Sin `getMessage()` crudo hacia el usuario.

### Adopción (fase 21)

- [ ] **ADOP-01**: Hits de redirects legados medidos. **ADOP-02**: rutas/vistas con 0 hits en 30 días retiradas.
- [ ] **ADOP-03**: Dependencias y adaptadores temporales retirados. **ADOP-04**: `filter-engine.ts` en utils.
- [ ] **ADOP-05**: `CLAUDE.md`/`AGENTS.md`/`docs/cerebro-towell` al día. **ADOP-06**: `inventario-bugs.md` actualizado.

## v1 Requirements — Track PT (Programa Tejido)

### Contexto y contratos

- [x] **PT-CON-01**: Programa y Muestras tienen contexto, tablas, rutas, preferencias y capacidades explícitas. → `ProgramaTejidoSurface` + capacidades 01.3 en `config/planeacion.php` (PT 02).
- [x] **PT-CON-02**: Rutas/payloads/respuestas legacy están caracterizados antes de refactorizar. → tests de caracterización de PT-01 (sqlite; runbook Laragon pendiente).

### Dominio

- [ ] **PT-DOM-01**: Posición, `EnProceso`, `Ultimo`, fechas, líneas y grupos conservan sus invariantes.
- [ ] **PT-DOM-02**: Fórmulas y sincronización CatCodificados conservan semántica y son observables ante fallo. → catches silenciosos 1/4/5/6 contenidos y observables (PT 02); resto en PT-05.

### Lectura

- [ ] **PT-READ-01**: La lectura v2 usa Request, ReadService y Resource con paginación/proyección. → lectura v2 detrás de flag + comparación shadow (PT 02); falta canary 02.5 en prod.

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

### ERP quick wins (auditoría 2026-09-22, Fase 0)

- [ ] **ERP-F0-01**: Acciones Livewire (`/livewire/update`) re-ejecutan `EnsureModulePermission`; las 4 páginas Livewire sin chequeo propio lo tienen.
- [ ] **ERP-F0-02**: `EdicionOrden::guardarMetrosFila` solo acepta registros del folio abierto (fix IDOR + test).
- [ ] **ERP-F0-03**: Patrones LIKE de `InventarioReservasService` sin `%` inicial; salida idéntica a la actual.
- [x] **ERP-F0-04**: 0 `bg-opacity-*` en vistas (Tailwind v4); `max-md` → `max-w-md`.
- [x] **ERP-F0-05**: Tailwind cargado una sola vez por página (quitar import de `app.js`).
- [x] **ERP-F0-06**: `getOrdenProduccion` no expone `SELECT @@VERSION` ni bloque debug.
- [x] **ERP-F0-07**: Barrido de código muerto confirmado (métodos privados, `buildReporteResumenData` x2, `MecActividadesController` duplicado, CRUD sin ruta, `internalToast`).
- [x] **ERP-F0-08**: R1.1 aplicado: rutas a métodos inexistentes (500) y 5 endpoints POST sin consumidor retirados.
- [x] **ERP-F0-09**: Urdido-BPM-Line usa `http.post` (fix 419, igual que Engomado).
- [x] **ERP-F0-10**: Polling de Programa Atadores se pausa con `document.hidden`; VerificaMaquina pagina con `PaginacionCompat` (los otros 4 `paginate()` de §2.5 quedan para Fase 1).
- [x] **ERP-F0-11**: 0 `route()` a nombres inexistentes (3 hoy) y un test de contrato que lo impide.

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
