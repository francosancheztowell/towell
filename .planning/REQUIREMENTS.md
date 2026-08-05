# Requirements: Programa Tejido — Migración Livewire + Refactor

**Defined:** 2026-07-22 (base), actualizado 2026-08-05
**Core Value:** El planeador opera Programa Tejido más rápido, sin fricción y sin romper invariantes de dominio.

## v1 Requirements

### Contexto y contratos

- [ ] **PT-CON-01**: Programa y Muestras tienen contexto, tablas, rutas, preferencias y capacidades explícitas.
- [ ] **PT-CON-02**: Rutas/payloads/respuestas legacy están caracterizados antes de refactorizar.

### Dominio

- [ ] **PT-DOM-01**: Posición, `EnProceso`, `Ultimo`, fechas, líneas y grupos conservan sus invariantes.
- [ ] **PT-DOM-02**: Fórmulas y sincronización CatCodificados conservan semántica y son observables ante fallo.

### Lectura

- [ ] **PT-READ-01**: La lectura v2 usa Request, ReadService y Resource con paginación/proyección.

### UI (Livewire)

- [ ] **PT-UI-01**: UI v2 es Livewire — componente(s) siguiendo el patrón `Crudo/MachineDetail.php` (datasets grandes como `#[Computed]`, no propiedad pública) y `UrdEng/ProgramBoard.php` (reorder/drag-drop).
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
