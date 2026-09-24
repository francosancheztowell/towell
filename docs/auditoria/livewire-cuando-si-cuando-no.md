# Livewire: cuándo sí / cuándo no (Towell ERP)

**Audiencia:** Franco / towellin.  
**Fecha:** 2026-09-18 ~11:05 CT (America/Mexico_City).  
**Evidencia:** auditoría `docs/auditoria` / PR #32 + checkout LA-GTECLAVE `C:\xampp\htdocs\Towell` + `main` GitHub `francosancheztowell/towell`.  
**Regla dura:** no inventar bugs; no reescribir `LiberarOrdenes` a Livewire como primer paso.


> **Actualización 2026-09-24 (decisión del owner):** **Programa Tejido sí migra a Livewire**, con el **mismo diseño visual**, y solo se da por buena si **mejora rendimiento/velocidad medidos** contra `.planning/phases/04-ux-grid/04-PERF-MEDIDO.md` (TTFB, KB de HTML, tiempo de interacción). Orden del track PT en `.planning/ROADMAP.md`: `01 → 01.1 AuthZ → 02 lectura → 04-perf → 03 shell Livewire → 05 mutaciones → 04-ux → 06 → 07` (services/tests antes que UI, como pide esta guía). **Liberar órdenes, Excel one-shot y reportes siguen en "No migrar ahora".** Roadmap global: `.planning/ROADMAP.md` (refactor integral 2026).

---

## Árbol de decisión (corto)

```
¿La mutación de negocio ya existe en un Service testeado?
  NO → Extraer service / unificar reglas / AuthZ primero. NO Livewire.
  SÍ ↓
¿Hay DOS rutas HTTP (Blade/fetch vs Livewire) con reglas distintas?
  SÍ → Congelar legacy / forzar Livewire (o delegar POST legacy al service). No “migrar UI” sola.
  NO ↓
¿Es tablero interactivo, piso en vivo, validación compartida, drag/status en tiempo real?
  SÍ → Migrar ya o Migrar parcial (solo el tablero).
  NO ↓
¿Es Excel one-shot, dump de reporte, CRUD delgado, Telegram/Redbooth send?
  SÍ → No migrar ahora.
  NO → Migrar parcial solo si el Blade es inmantenible Y hay service.
```

**Anti-patrón #1:** portar `LiberarOrdenesController` (~2537 LOC local / ~2941 en `main`) a un componente Livewire monolítico.  
**Orden correcto:** AuthZ en rutas → matar dual path Engomado → extraer servicios (L.Mat, CreaProd, marbetes) → Feature tests → UI después.

---

## Criterios explícitos

| SÍ (Livewire tiene sentido) | NO (no migrar ahora) |
|-----------------------------|----------------------|
| Tableros interactivos (status, prioridad, drag) | Excel import/export one-shot |
| Validación de dominio compartida server-side | Dumps de reporte / PhpSpreadsheet |
| Dual-path riesgo (misma planta, dos verdades) | CRUD delgado de catálogo estable |
| Piso / andón / máquina en tiempo casi-real | Telegram / Redbooth / notificaciones fire-and-forget |
| Ya hay `*ActionService` + tests | Fat controller sin service (extraer primero) |

**Conteo Livewire existente (12):**  
`Concerns/ConTabla`, Crudo×4, `Desarrolladores/Captura`, `Mantenimiento/CatalogoFallas`, `Mecanicos/VerificaMaquina/{Index,Show}`, `Tejedores/CatalogoCalibres`, `Trazabilidad/Index`, `UrdEng/ProgramBoard`.  
`wire:` ≈ **258** en blades (LA-GTECLAVE); `@livewire` ≈ **0** (usan tag `<livewire:…>` / directivas modernas).

**Blades por keyword (imperfécto, solo orientación):** tejido~25, urdido~24, programa~25, engomado~20, trazabilidad~15, mecanicos~14, crudo~11, atadores~8, mantto~8, tejedores~7, planeacion~7, config~7.

---

## Matriz por módulo

### Planeación

| Campo | Valor |
|-------|--------|
| **Estado actual** | Blade + jQuery/`fetch` + Vite (`resources/js/programa-tejido/index.js` **13 218** LOC en `main`). Controllers gordos: Liberar **2537** local, OrdenDeCambioFelpa **1696**, DividirTejido **1269**, BalancearTejido **1199**, Calendario **1112**, CatCodificacion **892**. Sin Livewire de programa. |
| **Recomendación** | **No migrar ahora** (programa/liberar/utilería). Catálogos delgados: **No migrar ahora**. |
| **Criterios** | NO: Excel-heavy, liberar es one-shot multi-paso AX, JS ya es el “controller”. SÍ solo si se extrae service y se necesita UI reactiva de filas — no es el caso hoy. |
| **Riesgo si se migra mal** | Re-encolar AX (`CreaProd`), marbetes `ceil` vs docs, L.Mat divergente, planta sin liberar. |
| **Dependencias** | AuthZ middleware; no tocar `CreaProd` en update; `LmatCrudoQueryService`; validar %100 en backend; Feature tests liberar/mover/finalizar/L.Mat **antes** de cualquier UI nueva. |

### Tejido

| Campo | Valor |
|-------|--------|
| **Estado actual** | Blade + `fetch` (secuencias corte/marcas/inv-telas/inv-trama clonadas). CortesEficiencia **1480** LOC local. Reportes Excel. |
| **Recomendación** | **No migrar ahora**. |
| **Criterios** | NO: report dumps + esqueletos fetch repetidos; migrar Livewire no arregla el copy-paste. |
| **Riesgo si se migra mal** | Cuatro pantallas divergentes; CSRF/error handling ×4. |
| **Dependencias** | Extraer helper HTTP/`window.http`; unificar secuencias en un service de lectura si duele. |

### Urdido

| Campo | Valor |
|-------|--------|
| **Estado actual** | **Híbrido dual.** Default `ProgramarUrdidoController::index()` → `legacy()` → Blade `programar-urdido`. Ruta explícita `/programar-urdido/livewire` → Blade livewire con `<livewire:urd-eng.program-board module="urdido">`. Mutaciones legacy vía POST `actualizar-status` etc. Service canónico: `ProgramBoardActionService`. |
| **Recomendación** | **Congelar legacy / forzar Livewire** (tablero de status). Producción/BPM/reportes: **No migrar ahora**. |
| **Criterios** | SÍ: dual-path + board interactivo + service ya testeado. NO: captura producción 2k+ LOC Blade. |
| **Riesgo si se migra mal** | Dejar POST legacy vivos con reglas distintas; operadores en URL vieja. |
| **Dependencias** | Redirect default → Livewire; legacy POST debe delegar a `ProgramBoardActionService` o 410. |

### Engomado

| Campo | Valor |
|-------|--------|
| **Estado actual** | **Híbrido invertido (peligroso).** `index()` sirve Blade legacy `programar-engomado` (UI default). Ruta **nombrada** `programar.engomado.legacy` sirve **Livewire** (`programar-engomado-livewire` + `ProgramBoard module="engomado"`). **No** hay ruta `*.livewire` en `engomado.php`. Legacy `actualizarStatus` **no** exige Urdido Finalizado ni límite 2× En Proceso (BUG-005/016). Livewire service **sí**. |
| **Recomendación** | **Congelar legacy / forzar Livewire** — **prioridad P1 sobre “migrar más UI”**. |
| **Criterios** | SÍ: dual-path risk máximo del ERP. NO: captura-fórmula 3589 LOC Blade, módulo producción, Excel. |
| **Riesgo si se migra mal** | Engomar sin urdido finalizado; sobrecarga máquina; confusión `.legacy` = Livewire. |
| **Dependencias** | Delegar `actualizarStatus` al service **antes** de apagar Blade; renombrar rutas (legacy≠Livewire); cablear o matar `verificar-en-proceso`. |

### Programa Urd-Eng

| Campo | Valor |
|-------|--------|
| **Estado actual** | Blade + JS gordo `public/js/modulos/programa_urd_eng/creacion-ordenes.js` (**1460** LOC). Controllers `ReservarProgramar*` / BOM / Karl Mayer. **No** usa `Livewire\UrdEng\ProgramBoard` (ese es el board de status urd/eng). Existe también `app/Services/ProgramaUrdEng/ProgramBoardActionService.php` (nombre confuso vs `Programas\ProgramBoardActionService`). |
| **Recomendación** | **No migrar ahora** (reservar/crear órdenes). |
| **Criterios** | NO: flujo multi-paso Excel/BOM/reserva; no es el mismo board. Aclarar naming de services. |
| **Riesgo si se migra mal** | Mezclar “crear órdenes UrdEng” con “status board” en un solo Livewire. |
| **Dependencias** | Renombrar/documentar services duplicados; AuthZ en POST crear/reservar. |

### Atadores

| Campo | Valor |
|-------|--------|
| **Estado actual** | Blade + fetch. `AtadoresController` **1043** LOC local. `OeeAtadoresFileService` ~3097 LOC (segundo ERP). |
| **Recomendación** | **No migrar ahora**. |
| **Criterios** | NO: OEE file-heavy; sin dual-path Livewire hoy. |
| **Riesgo si se migra mal** | Romper ciclo Activo→Autorizado + OEE embebido. |
| **Dependencias** | Partir OEE por use-case si se toca; no Livewire primero. |

### Tejedores

| Campo | Valor |
|-------|--------|
| **Estado actual** | Híbrido: Livewire `CatalogoCalibres` + `Desarrolladores/Captura` (~1039 LOC componente). Resto Blade/controllers bajo Planeación/Tejedores. |
| **Recomendación** | **Migrar parcial** — mantener Captura/Calibres; no portar todo Desarrolladores. |
| **Criterios** | SÍ donde ya hay Livewire+tests. NO reescribir writers Excel/liberar a Livewire. |
| **Riesgo si se migra mal** | Calibres FLOAT (`600/1T`) si Captura/otros writers no filtran igual. |
| **Dependencias** | Un solo writer de calibres (service); tests Captura ya existen. |

### Mantenimiento

| Campo | Valor |
|-------|--------|
| **Estado actual** | Blade + APIs fetch. Livewire solo `CatalogoFallas`. Paros: `MantenimientoParosController` **845** LOC; `Route::view` sin controller en solicitudes/reporte. |
| **Recomendación** | **Migrar parcial** (catálogos tipo fallas). Paros UI: **No migrar ahora** hasta AuthZ + quitar userId mágico. |
| **Criterios** | SÍ: ConTabla/catálogo. NO: listados de miles de filas + dialogos ya nativos en reporte. |
| **Riesgo si se migra mal** | Duplicar validación `hayActivoEnMaquina`; docs `validar-duplicado` fantasma. |
| **Dependencias** | Middleware módulo; Feature test `store`; quitar `userId === 6`. |

### Mecánicos

| Campo | Valor |
|-------|--------|
| **Estado actual** | Híbrido: Livewire `VerificaMaquina/{Index,Show}`. OT: `OrdenesTrabajoMecaController` **1347** LOC. Dual `MecActividadesController` (BUG-021). |
| **Recomendación** | **Migrar parcial** — VerificaMaquina ok; OT/catálogos **No migrar ahora**. |
| **Criterios** | SÍ: verificación interactiva por máquina. NO: OT gordo sin service claro. |
| **Riesgo si se migra mal** | Enlazar el MecActividades “sin userCan”. |
| **Dependencias** | Unificar controllers duplicados; AuthZ en APIs OT. |

### Trazabilidad

| Campo | Valor |
|-------|--------|
| **Estado actual** | Híbrido fuerte: Livewire `Index` + TS `resources/js/trazabilidad/*` + services `app/Services/Trazabilidad/*`. Flogs → `sqlsrv_ti`. |
| **Recomendación** | **Migrar parcial** — Livewire ya es shell; no reescribir TS a Livewire puro. |
| **Criterios** | SÍ: filtros/detalle interactivo. NO: dump Excel/Redbooth send. |
| **Riesgo si se migra mal** | Scroll `main.app-main` vs body; Color solo teñido. |
| **Dependencias** | Puerto AX/Flogs; no silenciar timeouts. |

### Crudo

| Campo | Valor |
|-------|--------|
| **Estado actual** | Livewire-first: Dashboard, MachineDetail, MachineFlogSummary, MachineFloor + TS `resources/js/crudo/*` (dashboard.ts **1557** LOC). Mejor capa servicios del repo. |
| **Recomendación** | **Migrar ya** está hecho — **mantener Livewire**; no abrir Blade paralelo. |
| **Criterios** | SÍ: piso/andón real-time. Congelar cualquier Blade legacy si aparece. |
| **Riesgo si se migra mal** | Segundo stack Blade para “hotfix” → dual path otra vez. |
| **Dependencias** | Seguir services; tests CrudoLivewire. |

### Configuración

| Campo | Valor |
|-------|--------|
| **Estado actual** | Blade + menú `ModuloService`/`SYSRoles`. CRUD módulos también en `public.php` **sin auth** (P0). |
| **Recomendación** | **No migrar ahora**. Primero seguridad. |
| **Criterios** | NO: thin CRUD + riesgo P0; Livewire no cierra el agujero público. |
| **Riesgo si se migra mal** | UI bonita sobre `/modulos-sin-auth` intacto. |
| **Dependencias** | Eliminar grupo público; middleware `userCan`; caché `modulos_v3` documentada. |

### Integraciones

| Campo | Valor |
|-------|--------|
| **Estado actual** | Módulos `telegram.php`, `redbooth.php`, `producto-terminado.php` bajo `auth` only. Fire-and-forget / bridges. |
| **Recomendación** | **No migrar ahora**. |
| **Criterios** | NO: sends one-shot; no board interactivo. |
| **Riesgo si se migra mal** | Doble envío / secrets en componente. |
| **Dependencias** | AuthZ + jobs/idempotencia si duelen; no Livewire. |

---

## Resumen ejecutivo de recomendaciones

| Módulo | Decisión |
|--------|----------|
| Planeación | **No migrar ahora** (excepto Programa Tejido: migra a Livewire sin cambiar diseño, ver actualización 2026-09-24) |
| Tejido | **No migrar ahora** |
| Urdido (tablero) | **Congelar legacy / forzar Livewire** |
| Engomado (tablero) | **Congelar legacy / forzar Livewire** (P1) |
| Programa Urd-Eng | **No migrar ahora** |
| Atadores | **No migrar ahora** |
| Tejedores | **Migrar parcial** |
| Mantenimiento | **Migrar parcial** (catálogos) |
| Mecánicos | **Migrar parcial** |
| Trazabilidad | **Migrar parcial** |
| Crudo | **Migrar ya** (mantener; no dualizar) |
| Configuración | **No migrar ahora** |
| Integraciones | **No migrar ahora** |

**Top 3 decisiones Livewire (para Franco):**

1. **Engomado:** default Blade salta reglas; forzar mutaciones por `ProgramBoardActionService` y dejar de llamar “legacy” al Livewire.  
2. **Urdido:** mismo patrón — default aún Blade; ruta `/livewire` existe pero no es default.  
3. **Planeación/Liberar:** **prohibido** como primer Livewire; extraer services + AuthZ + tests.

