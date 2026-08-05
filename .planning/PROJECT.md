# Towell — Programa Tejido (Planeación)

## What This Is

Towell es una app Laravel 12 de planeación y control de producción textil. Este proyecto GSD cubre específicamente la reestructuración de **Programa Tejido** (`ReqProgramaTejido` y superficies vinculadas: Muestras, Redbooth, liberación, finalización, balanceo, CatCodificados) dentro del módulo Planeación — usado a diario por el planeador de producción.

## Core Value

El planeador puede operar Programa Tejido más rápido y sin fricción (renderizado, filtros, acciones) sin romper ninguna invariante de dominio (posición, EnProceso, Ultimo, fechas, líneas, OrdCompartida) que otros módulos consumen hoy.

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] Ver .planning/REQUIREMENTS.md

### Out of Scope

- Reescritura completa de los 28 controladores de negocio (16,444 líneas) — se conservan como servicio invocado desde Livewire, no se reescriben salvo deduplicación puntual (ver PT-DUP-*)
- React/TanStack — descartado, Livewire cubre la necesidad de reactividad sin nueva dependencia mayor
- Migraciones de columnas/índices "aspiracionales" no confirmadas contra la BD física hasta que fase 01 las verifique

## Context

- Stack: Laravel 12, Blade, Livewire (ya en uso en `app/Livewire/Crudo/*` y `app/Livewire/UrdEng/ProgramBoard.php` — patrones de referencia establecidos), SQL Server (sqlsrv), Tailwind v4, Vite.
- Auditoría previa (2026-08-05): 28 controladores/helpers bajo `app/Http/Controllers/Planeacion/ProgramaTejido/`, 0% adopción de Livewire en este módulo, vistas Blade de hasta 497 líneas con `fetch` custom, filtros en `resources/js/programa-tejido/filter-engine.js`.
- Existe un paquete de planeación previo (2026-07-22) en `.planning/planeacion-restructuring/` con 7 fases ya diseñadas (guardrails → contexto/lectura → shell UI → UX/grid → mutaciones → límites operacionales → adopción/limpieza). Ese paquete decidió originalmente "Blade delgado + módulos ES/Vite" para la UI v2; **el 2026-08-05 se decidió reemplazar esa decisión por Livewire** (fases 03 y 04 deben replanearse; el resto del roadmap no cambia).
- Auditoría de duplicación (2026-08-05) encontró 8 piezas de lógica/validación repetidas en el backend (observer suppress/restore triplicado, cálculo de FechaFinal copiado 6×, chequeo de flag "Ultimo" con 3 variantes inconsistentes, scopes del modelo bypaseados en 20+ sitios, etc.) — casi todas resolubles apuntando a helpers/scopes ya existentes, sin abstracciones nuevas.

## Constraints

- **Tech stack**: Livewire para UI v2 (no React/TanStack); reusar patrones de `Crudo/MachineDetail.php` (computed properties para datasets grandes, no property pública) y `UrdEng/ProgramBoard.php` (drag/drop con SortableJS) en vez de inventar nuevos.
- **Convivencia**: legacy y v2 conviven con feature flag y rollback probado; no hay reemplazo masivo de una sola vez (decisión bloqueada en `planeacion-restructuring/00-CONTEXT.md`, sigue vigente salvo por el punto de UI).
- **Invariantes de dominio**: cero posiciones duplicadas por `(SalonTejidoId, NoTelarId, Posicion)`, máx. un registro `EnProceso` por telar, sin líneas huérfanas, separación total Programa/Muestras — no se pueden romper en ninguna fase.
- **DB**: SQL Server es la fuente física; no se infiere schema solo desde migrations (varias divergencias ya detectadas en fase 01).

## Key Decisions

| Fecha | Decisión | Razón |
|---|---|---|
| 2026-07-22 | Migración incremental con legacy/v2 conviviendo por feature flag, sin reescritura masiva | Programa Tejido es crítico y usado a diario; un big-bang es demasiado riesgoso |
| 2026-07-22 | Programa y Muestras son superficies explícitas con capacidades declaradas, no tablas equivalentes | Evitar bugs por asumir paridad física no verificada |
| 2026-08-05 | UI v2 usa **Livewire** (reemplaza la decisión previa de Blade delgado + módulos ES/Vite) | Pedido explícito del usuario; el codebase ya tiene convenciones Livewire establecidas (Crudo, UrdEng) que evitan inventar una arquitectura de estado JS nueva |
| 2026-08-05 | Los 28 controladores de negocio no se reescriben en la migración a Livewire; Livewire los invoca igual que hoy los invoca Blade+fetch | Están cubiertos por 22 tests existentes; reescribirlos es riesgo sin ganancia de UX |

## Working Agreements

- Formato: Español para toda la documentación del proyecto (roadmap, plans, decisiones).
- Modo ponytail activo: sin abstracciones nuevas si ya existe un scope/helper que resuelve lo mismo; priorizar apuntar duplicados al original sobre crear código nuevo.
