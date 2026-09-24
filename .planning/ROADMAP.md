# Roadmap: Programa Tejido — Migración Livewire + Refactor

## Overview

Reestructurar Programa Tejido (`ReqProgramaTejido` y superficies vinculadas: Muestras, Redbooth, liberación, balanceo) de forma incremental: primero congelar contratos e invariantes, luego separar lectura, después construir la UI v2 en Livewire, migrar mutaciones por caso de uso, tratar operaciones de alto riesgo con sus propios gates, y finalmente retirar el legacy con evidencia de adopción. Los 28 controladores de negocio (16,444 líneas) no se reescriben — Livewire los invoca igual que hoy lo hace Blade+fetch, salvo la deduplicación puntual de PT-DUP-*.

## Phases

- [ ] **Phase 1: Guardrails** — Congelar contratos, esquema, capacidades e invariantes antes de tocar código.
- [ ] **Phase 2: Contexto + lectura** — Contener P0 y crear contexto/read seam sin cambiar mutaciones.
- [ ] **Phase 3: Shell Livewire** — UI v2 canary en Livewire (reemplaza la decisión previa de Blade+Vite modules).
- [ ] **Phase 4: UX/grid** — Tabla usable, paginada, accesible, una sola fuente de estado (Livewire).
- [ ] **Phase 5: Mutaciones** — Extraer mutaciones en slices por caso de uso, incluye deduplicación PT-DUP-01..04.
- [ ] **Phase 6: Límites operacionales** — Secuencia, grupos, balanceo e integraciones con gates propios.
- [ ] **Phase 7: Adopción y limpieza** — Adopción, retiro legacy, limpieza de rutas/assets muertos.
- [ ] **Phase 8: ERP quick wins** — Fase 0 de `auditoria_erp_2026-09.md` (seguridad Livewire, bugs, SQL, perf, muertos) con tests de edición. Independiente de 1-7.

## Phase Details

### Phase 1: Guardrails
**Goal**: Ningún refactor parte de "Programa y Muestras son iguales" sin evidencia. Rutas, schema, derivados, invariantes tienen tests automatizados y la decisión de capacidades Programa/Muestras está aprobada.
**Depends on**: Nothing (first phase)
**Requirements**: PT-CON-01, PT-CON-02, PT-DOM-01, PT-DOM-02, PT-ROL-01
**Success Criteria**:
  1. Suite de caracterización (rutas, schema, aislamiento, invariantes, fórmulas) pasa en verde.
  2. Command read-only de salud reporta posición/EnProceso/líneas/grupos/CatCodificados.
  3. Decisión aprobada sobre las 6 columnas y 11 longitudes divergentes de Muestras.
**Plans**: 1 plan (ya escrito)

Plans:
- [ ] 01-guardrails: Snapshot de rutas, matriz física de schema, checkpoint de decisión Programa/Muestras, fixtures de aislamiento, caracterización de invariantes.

### Phase 2: Contexto + lectura
**Goal**: Existe un read-seam (Request/ReadService/Resource) paginado y proyectado sin tocar mutaciones legacy.
**Depends on**: Phase 1
**Requirements**: PT-CON-01, PT-READ-01
**Success Criteria**:
  1. Lectura v2 usa Request/ReadService/Resource con paginación real (no todo en memoria).
  2. Mutaciones legacy siguen intactas — cero regresión funcional.
**Plans**: 1 plan (ya escrito)

Plans:
- [ ] 02-containment-read: Contexto Programa/Muestras explícito + read seam.

### Phase 3: Shell Livewire
**Goal**: UI v2 en Livewire, activable por usuario con rollback inmediato a Blade legacy. Reemplaza la decisión original de "Blade delgado + módulos ES/Vite" (superada 2026-08-05).
**Depends on**: Phase 2
**Requirements**: PT-UI-01, PT-ROL-01
**Success Criteria**:
  1. Componente Livewire principal (patrón `Crudo/MachineDetail.php`: dataset grande como `#[Computed]`, no propiedad pública) reemplaza el fetch/store JS a mano.
  2. Feature flag apagado = cero requests/assets v2 cargados.
  3. Programa y Muestras abren sin monkey-patch ni reemplazo de texto en rutas.
**Plans**: 1 plan (replaneado 2026-08-05 para Livewire, ver `.superseded` para contexto histórico)

Plans:
- [ ] 03-shell-livewire-PLAN.md — Shell `ProgramaTejidoBoard` (Livewire) + canary allowlist por `numero_empleado` + wrappers delgados Programa/Muestras con rollback inmediato.

### Phase 4: UX/grid
**Goal**: Tabla usable, paginada, accesible, con presets/filtros claros y una sola fuente de estado — en Livewire.
**Depends on**: Phase 3
**Requirements**: PT-UI-02, PT-PERF-01
**Success Criteria**:
  1. Tabla pagina server-side (aprovecha índices de PT-PERF-01), no carga todo el dataset al DOM.
  2. Reorder/drag-drop de posición reusa el patrón `UrdEng/ProgramBoard.php` (SortableJS) en vez de inventar uno nuevo.
  3. Estados loading/error/empty explícitos y accesibles (landmarks, foco, labels).
**Plans**: TBD — replanear con `/gsd:plan-phase 4` (el plan anterior asumía Blade+Vite modules, ver `.superseded`)

> ⚠️ El criterio de éxito 1 (paginar server-side) se midió y **no es el cuello de botella**:
> son 85 filas / 7 820 celdas, y el 79 % del HTML se va en atributos repetidos y en las
> 59 columnas que el usuario pesado oculta. Ver `04-PERF-MEDIDO.md` antes de planear.

Plans:
- [ ] 04-XX: TBD
- [ ] 04-perf: cortes 1–3 de `04-PERF-MEDIDO.md` (selección en CSS, ocultas antes del paint, clases utilitarias). Independientes de Livewire.

### Phase 5: Mutaciones
**Goal**: Mutaciones extraídas verticalmente a FormRequests + servicios por caso de uso; deduplicación de backend resuelta.
**Depends on**: Phase 4
**Requirements**: PT-MUT-01, PT-DUP-01, PT-DUP-02, PT-DUP-03, PT-DUP-04, PT-PERF-02
**Success Criteria**:
  1. Cada mutación simple tiene FormRequest + servicio propio, sin lógica duplicada entre controladores.
  2. Observer suppress/restore, cálculo de FechaFinal, chequeo "Ultimo" y scopes Salon/Telar usan una sola implementación cada uno.
  3. N+1 confirmados (`store()`, `obtenerDatosVisualizacionPorFecha()`) eliminados.
**Plans**: 1 plan existente (mutaciones) + ampliar con tareas de deduplicación

Plans:
- [ ] 05-mutations: Extracción de mutaciones simples a slices (plan ya escrito, ampliar con PT-DUP-*).

### Phase 6: Límites operacionales
**Goal**: Secuencia, grupos (OrdCompartida), balanceo e integraciones (Redbooth, imports) migrados como planes independientes con gates propios — muy alto riesgo, nunca en un solo PR.
**Depends on**: Phase 5
**Requirements**: PT-OPS-01, PT-DOM-01, PT-DOM-02
**Success Criteria**:
  1. Cada subfamilia (secuencia / grupos / balanceo / liberar-finalizar-imports-integraciones) es su propio PR y gate.
  2. Invariantes de dominio (posición única, EnProceso único, OrdCompartida) verificadas antes/después de cada corte.
**Plans**: 1 plan (ya escrito)

Plans:
- [ ] 06-operational-boundaries: Secuencia, grupos, balanceo, integraciones.

### Phase 7: Adopción y limpieza
**Goal**: Legacy se retira solo con telemetría de cero uso; sin fetch global parcheado ni estado duplicado en v2.
**Depends on**: Phase 6
**Requirements**: PT-ROL-01
**Success Criteria**:
  1. UI v2 estable durante el ciclo operativo acordado.
  2. Retiro de legacy respaldado por evidencia de adopción, no por calendario.
**Plans**: 1 plan (ya escrito)

Plans:
- [ ] 07-adoption-cleanup: Adopción, retiro legacy, limpieza.

### Phase 8: ERP quick wins
**Goal**: Cerrar la Fase 0 de `.planning/auditoria_erp_2026-09.md` (S, riesgo bajo) dejando un test por cada ruta de edición tocada.
**Depends on**: Nada (fuera de Programa Tejido; no toca fases 1-7)
**Requirements**: ERP-F0-01..11
**Success Criteria**:
  1. Acciones Livewire re-ejecutan `module.permission`; `guardarMetrosFila` rechaza registros de otro folio (test).
  2. Inventario disponible sin `%` inicial y con la misma salida que antes.
  3. 0 `bg-opacity-*` en vistas; Tailwind se descarga una vez por página.
  4. Código muerto confirmado borrado; `php artisan test` y `npm run build` verdes.
  5. 0 `route()` a nombres inexistentes, con test de contrato.
**Plans**: 6 plans (wave 1: 08-01, 08-02, 08-03, 08-06; wave 2: 08-04, 08-05)

Plans:
- [ ] 08-01-PLAN.md — Seguridad Livewire: middleware persistente que aborta en X-Livewire + gates por idrol (sin bloqueo) en 4 pantallas; IDOR guardarMetrosFila (ERP-F0-01, 02)
- [x] 08-02-PLAN.md — Front/perf: bg-opacity → /NN, Tailwind una vez, BPM-Line http.post, polling Atadores, VerificaMaquina PaginacionCompat (ERP-F0-04, 05, 09, 10)
- [~] 08-03-PLAN.md — Rutas rotas + contrato de nombres (checkpoint cargar-catalogos) y LIKE sargable en InventarioReservas (checkpoint sqlsrv_ti) (ERP-F0-11, 03)
- [x] 08-04-PLAN.md — Código muerto confirmado: privados, duplicados, públicos sin llamador, internalToast, openViewModal (ERP-F0-07)
- [x] 08-05-PLAN.md — R1.1 + 5 endpoints POST sin consumidor (ERP-F0-08)
- [x] 08-06-PLAN.md — getOrdenProduccion sin SELECT @@VERSION ni debug (ERP-F0-06)

## Progress

**Execution Order:**
Fases ejecutan en orden numérico: 1 → 2 → 3 → 4 → 5 → 6 → 7

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Guardrails | 0/1 | Planned, ready to execute | - |
| 2. Contexto + lectura | 0/1 | Planned | - |
| 3. Shell Livewire | 0/1 | Planned | - |
| 4. UX/grid | 0/TBD | Needs replanning (Livewire) | - |
| 5. Mutaciones | 0/1 | Planned (ampliar con PT-DUP-*) | - |
| 6. Límites operacionales | 0/1 | Planned | - |
| 7. Adopción y limpieza | 0/1 | Planned | - |
| 8. ERP quick wins | 4/6 | 08-01 excluido por el usuario; 08-03 F0-03 pendiente de consultas A/B |  - |

## Gate global para avanzar

Una wave termina únicamente si: sus tests de contrato y dominio pasan; los invariantes SQL se mantienen; el build pasa cuando aplica; el rollback del flag fue probado; Programa y Muestras fueron evaluados explícitamente; no se tocaron consumidores fuera del alcance documentado; existe evidencia de UAT para la superficie afectada.
