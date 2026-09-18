# Deuda JS / frontend — Towell

**Fecha:** 2026-09-18 ~11:05 CT.  
**Fuente preferida de conteos:** checkout LA-GTECLAVE `C:\xampp\htdocs\Towell` (Franco).  
**Complemento:** `main` GitHub (LOC de archivos JS) + auditoría crítica PR #32.

---

## Conteos LA-GTECLAVE (usar estos)

| API / señal | Ocurrencias |
|-------------|------------:|
| `fetch(` | **339** |
| `axios` | **30** |
| `window.http` | **9** |
| `$.ajax` | **0** |
| `Swal.` | **1136** |
| `wire:` | **258** |
| `@livewire` en blades | **≈0** |

**Lectura:** la migración documentada en `AGENTS.md` hacia `window.http` / `window.notify` **casi no ocurrió** (9 vs 339 fetch). jQuery ajax ya no; SweetAlert domina notificaciones. Livewire está presente vía `wire:` / tags `<livewire:…>`, no vía `@livewire` clásico.

Auditoría previa (solo `resources/views`, órdenes de magnitud): fetch~250, http.*~37, Swal~976, notify~29 — **subcuenta** vs LA-GTECLAVE (que incluye más superficie JS+blade). Preferir **339 / 1136 / 9**.

---

## Tres generaciones de UI (conviven)

1. **Blade monolítico + SweetAlert + `fetch`** — urdido/engomado default, atadores, liberar, catálogos, producción.  
2. **Vite entrypoints de dominio** — `programa-tejido/`, `catcodificacion/`, `urd-eng/*.ts`, `trazabilidad/*.ts`, `crudo/*.ts`.  
3. **Livewire 4** — Crudo, Trazabilidad shell, ProgramBoard, Captura, CatalogoFallas, VerificaMaquina, CatalogoCalibres.

No hay design system único. `components/ui/*` coexiste con HTML ad hoc. Carpetas: `modulos/`, `catalagos/` (typo congelado), `catcodificacion/`, `planeacion/`, `catalogosurdido/`.

---

## Archivos JS/TS más grandes (`main` GitHub, wc -l)

| LOC | Archivo |
|----:|---------|
| **13218** | `resources/js/programa-tejido/index.js` |
| **2455** | `resources/js/catcodificacion/lmat-modal.js` |
| **1988** | `resources/js/catcodificacion/index.js` |
| **1557** | `resources/js/crudo/dashboard.ts` |
| **1460** | `public/js/modulos/programa_urd_eng/creacion-ordenes.js` |
| 781 | `public/js/catalogs/CatalogBase.js` |
| 490 | `public/js/app-pwa.js` |
| 370 | `resources/js/app-core.js` |
| ~320 | `resources/js/trazabilidad/flog-image-viewer.ts` |
| 99 | `resources/js/urd-eng/sortable-board.ts` |

`programa-tejido/index.js` es el verdadero controller del programa de tejido. `lmat-modal.js` impone %100 solo en browser (BUG-008).

Utilidades canónicas (poco usadas): `resources/js/utils/http.js`, `resources/js/utils/notifications.js`.

---

## Dual stacks UI (riesgo)

| Dominio | Stack A (default) | Stack B | Riesgo |
|---------|-------------------|---------|--------|
| Engomado programar | Blade + fetch status | Livewire en ruta **nombrada** `.legacy` | Reglas negocio distintas (BUG-005/011/016) |
| Urdido programar | Blade via `index→legacy()` | `/programar-urdido/livewire` | Misma planta, URL decide reglas |
| Trazabilidad | Livewire Index | TS detail/flog/matrix | Scroll/overflow; OK si service único |
| Crudo | Livewire floor/dashboard | TS audit/dashboard | Mantener; no abrir Blade |
| Codificación | `catalagos/` legacy | `catcodificacion/` + lmat-modal | Dual tablas/rutas (BUG-012) |
| Programa Urd-Eng crear | Blade + creacion-ordenes.js | (no Livewire ProgramBoard) | Naming confuso con ProgramBoard status |

---

## Fat controllers (LOC local LA-GTECLAVE — preferir)

| LOC | Controller |
|----:|------------|
| 2537 | LiberarOrdenesController *(main audit citó 2941 — local puede ir atrasado de PR)* |
| 1696 | OrdenDeCambioFelpaController |
| 1480 | CortesEficienciaController |
| 1435 | ReportesUrdidoController |
| 1347 | OrdenesTrabajoMecaController |
| 1337 | CodificacionController |
| 1269 | DividirTejido |
| 1199 | BalancearTejido |
| 1112 | Calendario |
| 1043 | AtadoresController |
| 892 | CatCodificacionController |
| 845 | MantenimientoParosController |

Blades “aplicación” (auditoría): captura-formula eng ~3589, produccion eng ~3389, telar-requerimiento ~3298, catalogoCodificacion ~2834, liberar-ordenes index ~2682.

---

## CSRF / XSS / errores

- `notify` escapa HTML; **Swal con HTML crudo no** → mensaje de servidor → modal es vector XSS plausible (no PoC en esta pasada).  
- Migrar módulo a módulo a `window.http` + `notify` (Calendarios = piloto citado en auditoría).  
- Secuencias tejido: mismo esqueleto fetch ×4 — un fix CSRF hay que pegarlo cuatro veces.

---

## Sugerencias nuevas BUG-029+ (parent merge → inventario)

| ID | Sev | Área | Título | Evidencia | Impacto | Estado |
|----|-----|------|--------|-----------|---------|--------|
| BUG-029 | P2 | Frontend | Migración `window.http` estancada (9 vs 339 `fetch(`) | Conteos LA-GTECLAVE 2026-09-18 | CSRF/errores inconsistentes; AGENTS.md miente en la práctica | confirmado (conteo) |
| BUG-030 | P2 | Frontend | SweetAlert domina (1136) vs notify | LA-GTECLAVE `Swal.`=1136 | XSS via HTML en modal; a11y foco/ESC irregular | confirmado (conteo); PoC XSS = hipótesis |
| BUG-031 | P1 | Engomado / UX rutas | Ruta `.legacy` sirve Livewire; default `index` sirve Blade débil | `ProgramarEngomadoController::index` → `programar-engomado`; `legacy()` → `programar-engomado-livewire` + comentario “Livewire en .legacy” | Operador/docs van a “legacy” y obtienen el stack moderno; default sigue con reglas rotas | confirmado |
| BUG-032 | P2 | Frontend | `programa-tejido/index.js` 13 218 LOC | `wc` en `main` GitHub | Controller JS intocable; regresiones UI sin test | confirmado |
| BUG-033 | P2 | Programa Urd-Eng | `creacion-ordenes.js` 1460 LOC fuera de Vite domain TS | `public/js/modulos/programa_urd_eng/creacion-ordenes.js` | Assets públicos sin pipeline; divergencia con `resources/js/urd-eng` | confirmado |
| BUG-034 | P3 | Livewire / blades | `wire:`=258 pero `@livewire`≈0 | LA-GTECLAVE | Docs/onboarding que busquen `@livewire` no encuentran uso real | confirmado (conteo) |

**No inventados aquí:** jobs AX, PoC XSS, cobertura Clover — siguen en gaps del inventario.

---

## Qué hacer (orden)

1. No reescribir liberar en Livewire.  
2. Engomado/Urdido: una ruta de mutación (service) — ver matriz Livewire.  
3. Piloto `http`+`notify` en un módulo de alto Swal (producción o atadores) con checklist CSRF.  
4. Partir `programa-tejido/index.js` por feature **después** de tests de humo de filtros — no como “migración Livewire”.
