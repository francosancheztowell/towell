# Roadmap ejecutable

## Cómo usar este paquete

Este directorio es un paquete GSD-compatible acotado a Planeación/Programa Tejido. El repositorio no tiene todavía `ROADMAP.md`, `REQUIREMENTS.md` y `STATE.md` globales de GSD; por eso no se inicializó ni alteró un proyecto GSD global sin autorización.

Cada plan incluye frontmatter, dependencias, must-haves, tareas, verificación y criterio de rollback. La ejecución debe hacerse fase por fase, con PRs pequeñas y sin combinar familias de riesgo.

## Requirements

| ID | Requisito |
|---|---|
| PT-CON-01 | Programa y Muestras tienen contexto, tablas, rutas, preferencias y capacidades explícitas. |
| PT-CON-02 | Rutas/payloads/respuestas legacy están caracterizados antes de refactorizar. |
| PT-DOM-01 | Posición, `EnProceso`, `Ultimo`, fechas, líneas y grupos conservan sus invariantes. |
| PT-DOM-02 | Fórmulas y sincronización CatCodificados conservan semántica y son observables ante fallo. |
| PT-READ-01 | La lectura v2 usa Request, ReadService y Resource con paginación/proyección. |
| PT-UI-01 | Blade v2 es shell/composición y JavaScript se divide por dominio/feature. |
| PT-UI-02 | La tabla ofrece presets, filtros claros, acciones accesibles y estados explícitos. |
| PT-MUT-01 | Mutaciones se extraen verticalmente a FormRequests y servicios por caso de uso. |
| PT-OPS-01 | Liberar/finalizar/imports/integraciones se migran como planes independientes. |
| PT-ROL-01 | Cada corte tiene feature flag, telemetría, gate y rollback probado. |

## Waves y dependencias

| Wave | Plan | Depende de | Objetivo | Riesgo |
|---:|---|---|---|---|
| 0 | [01-guardrails-PLAN.md](01-guardrails-PLAN.md) | — | Congelar contratos, esquema, capacidades e invariantes. | Muy alto si se omite |
| 1 | [02-containment-read-PLAN.md](02-containment-read-PLAN.md) | 01 | Contener P0 y crear contexto/read seam sin cambiar mutaciones. | Medio |
| 2 | [03-frontend-shell-PLAN.md](03-frontend-shell-PLAN.md) | 02 | UI v2 canary, Blade delgado, Vite modules y rollback. | Medio |
| 3 | [04-ux-grid-PLAN.md](04-ux-grid-PLAN.md) | 03 | Tabla usable, paginada, accesible y con una fuente de estado. | Medio |
| 4 | [05-mutations-PLAN.md](05-mutations-PLAN.md) | 04 | Extraer mutaciones en slices, empezando por las simples. | Alto |
| 5 | [06-operational-boundaries-PLAN.md](06-operational-boundaries-PLAN.md) | 05 | Tratar secuencia, grupos, balanceo e integraciones con gates propios. | Muy alto |
| 6 | [07-adoption-cleanup-PLAN.md](07-adoption-cleanup-PLAN.md) | 06 | Adopción, retiro legacy y limpieza de rutas/assets demostrablemente muertos. | Medio/irreversible |

## Orden de ejecución recomendado

```text
01 Guardrails
  -> 02 Contexto + lectura
    -> 03 Shell v2
      -> 04 UX/grid
        -> 05 Mutaciones simples
          -> 06A Secuencia
            -> 06B Grupos
              -> 06C Balanceo
                -> 06D Liberar/Finalizar/Codificación/Imports/Integraciones
                  -> 07 Adopción y limpieza
```

Dentro del plan 06, cada subfamilia es un PR y un gate independiente; no constituye autorización para ejecutar todas juntas.

## Gate global para avanzar

Una wave termina únicamente si:

1. sus tests de contrato y dominio pasan;
2. los invariantes SQL se mantienen;
3. el build Vite pasa cuando aplica;
4. el rollback del flag fue probado;
5. Programa y Muestras fueron evaluados explícitamente, incluso cuando una capacidad se marque “no soportada”;
6. no se tocaron consumidores fuera del alcance documentado;
7. existe evidencia de UAT para la superficie afectada.

## Criterio de finalización del programa

- UI v2 estable durante el ciclo operativo acordado.
- Todas las operaciones que se decida migrar tienen contratos y servicios explícitos.
- No existe patch global de `fetch` ni estado duplicado en módulos v2.
- Las preferencias, rutas y consumidores downstream conservan compatibilidad.
- Código legacy se retira solo con telemetría de cero uso y aprobación explícita.
- Los riesgos diferidos quedan documentados; no se ocultan como “limpieza pendiente”.

