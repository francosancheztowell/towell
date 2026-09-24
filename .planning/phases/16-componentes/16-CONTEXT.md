# Fase 16 — Sistema de componentes

**Track:** DS · **Ola:** 2 · **IDs:** DS-01..12 · **Rama:** `claude/16-componentes`
**Depende de:** 15-01 (notify/format). **Antes de ejecutar:** escribir `16-01-PLAN.md`.

## Objetivo
Un solo juego de componentes Blade (y Livewire donde aplique) para modales, tablas, campos, botones, badges, loaders, estados vacíos, flash y filtros — **evolucionando lo existente**, no creando un paralelo.

## Estado actual
- 33 componentes anónimos en `resources/views/components/`; bien adoptados: `x-navbar.button-*` (create 56, edit 42, report 35, delete 28), `x-buttons.catalog-actions`. Poco usados: `x-ui.modal-base` (3), `x-ui.button`/`x-ui.alert` (2 archivos), `x-tabla` (solo Livewire), `x-empty.empty-state` (1). No hay class components.
- 77 overlays de modal a mano en 43 archivos (15+ variantes de clases, solo 16 con `role="dialog"`); 184 tablas con 8+ variantes de `thead`; `applyFilters` definido 15×; 5 tipos de loader; 66 empty states a mano; `bg-blue-600 hover:bg-blue-700` a mano 57×.
- Pantallas casi duplicadas: `catalogos-atadores/{actividades,maquinas,comentarios}` (~80 % iguales), `{urd,eng,tel}-actividades-bpm`, `BPM-Urdido/BPM-Engomado/tel-bpm`, `Urdido-BPM-Line` vs `Engomado-BPM-Line`, `modal-calificar-julios` vs `-eng`, `tejido/secuencia/*` ×4.

## Alcance
- DS-01 tokens en `resources/css/app.css` `@theme` (texto mínimo 12 px, targets táctiles ≥ 44 px, colores semánticos).
- DS-02 `x-ui.modal-base` → `<dialog>` nativo (foco, Esc, `::backdrop`, `aria-labelledby`) con API compatible con sus 3 usos.
- DS-03 `x-ui.table` (shell: sticky header, zebra, vacío, cargando) y `x-tabla` lo compone. DS-04 `x-ui.field` (label ligado, error, hint). DS-05 `x-ui.button` unificado con los estilos de `x-navbar.button-*`. DS-06 `x-ui.badge`. DS-07 `x-ui.spinner`/skeleton + loader global único. DS-08 adoptar `x-empty.empty-state`. DS-09 `x-ui.flash` (session flash + errores). DS-10 `x-ui.filter-bar` (reusa `resources/js/programa-tejido/filter-engine.ts` solo lectura; se reubica en ADOP).
- DS-11 galería `/dev/ui-kit` (solo `app()->isLocal()`) + receta `docs/cerebro-towell/Arquitectura/receta-componentes.md` (cuándo usar qué, ejemplos, migración de un modal/tabla existente).
- DS-12 piloto: consolidar el trío `catalogos-atadores` en una vista parametrizada y mover `public/js/catalogs/CatalogBase.js` a `resources/js/catalogos/catalog-base.ts`.
- Los demás duplicados (BPM, calificar-julios, secuencias) los consolida la sesión 19-xx del módulo dueño usando estos componentes.

## Criterios de éxito
- Galería con todos los componentes en claro/tablet; piloto sin regresión visual (capturas antes/después).
- Receta publicada; ratchet sin subir.

## Skills
Plugin `frontend-design`, `.impeccable/` critique, agente `code-simplifier`, `run`.
