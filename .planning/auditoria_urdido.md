# Auditoría — Módulo Urdido

Fecha: 2026-09-02 · Alcance: `app/Http/Controllers/Urdido/**`, `app/Models/Urdido/**`, `resources/views/modulos/urdido/**`, `resources/views/catalogosurdido/**`, `routes/modules/urdido.php`

---

## 1. Lo que hay hoy

| Medida | Valor |
|---|---|
| Controladores | **5,041 líneas** en 8 archivos |
| Modelos | **617 líneas** en 10 archivos |
| Blade | **11,060 líneas** en 24 archivos |
| Rutas declaradas | **58** |
| Rutas de escritura (POST/PUT/PATCH/DELETE) | **22** |
| └─ con comprobación de permisos en servidor | **11** |
| └─ **sin ninguna comprobación** | **11** |
| `fetch(` inline en Blade | **29** |
| `window.http` / `window.notify` | **0** |
| Mecanismos de autorización distintos coexistiendo | **3** |

Archivos más grandes: `ReportesUrdidoController.php` (1,652), `produccion/_scripts.blade.php` (2,265), `editar-orden-programada.blade.php` (1,900), `programar-urdido.blade.php` (1,523).

---

## 2. Checklist, del corte más grande al más pequeño

### 🔴 `fix:` 11 endpoints de escritura sin control de acceso en servidor

| Controlador | Endpoints sin guard |
|---|---|
| [CatalogosUrdidoController.php](app/Http/Controllers/Urdido/Configuracion/CatalogosJulios/CatalogosUrdidoController.php) | `storeJulio`, `updateJulio`, `destroyJulio`, `storeMaquina`, `updateMaquina`, `destroyMaquina` |
| [EditarOrdenesProgramadasController.php](app/Http/Controllers/Urdido/ProgramaUrdido/EditarOrdenesProgramadasController.php) | `actualizar`, `actualizarJulios`, `actualizarHilosProduccion` |
| [ProgramarUrdidoController.php](app/Http/Controllers/Urdido/ProgramaUrdido/ProgramarUrdidoController.php) | `guardarObservaciones`, `actualizarCalidad`, `actualizarStatus` |
| [UrdBpmLineController.php:68](app/Http/Controllers/Urdido/BPMUrdido/UrdBpmLineController.php:68) | `toggleActividad` |
| [UrdActividadesBpmController.php](app/Http/Controllers/Urdido/Configuracion/ActividadesBPMUrdido/UrdActividadesBpmController.php) / [UrdBpmController.php](app/Http/Controllers/Urdido/BPMUrdido/UrdBpmController.php) | `store`, `update`, `destroy` (resource) |

Sólo llevan middleware `auth`. Cualquier usuario logueado puede borrar el catálogo de julios o cambiar el status de una orden desde la consola del navegador.

Los dos casos más finos:

- `EditarOrdenesProgramadasController::usuarioPuedeEditar()` **existe** pero sólo se llama en la línea 253, dentro de `index()`, para decidir si el botón se pinta habilitado. Los tres `POST` que hay debajo no lo llaman.
- `UrdBpmLineController::toggleActividad()` además escribe `$request->input('valor')` sin `validate()`: acepta cualquier valor, no sólo 0/1/2, y cualquier `actividad`.

**Corte:** un `ensureUserCanEdit()` al inicio de cada método, igual al que ya existe y funciona en [ProduccionTrait.php:142](app/Traits/ProduccionTrait.php:142).

---

### 🔴 `unify:` tres mecanismos de autorización distintos en un mismo módulo

1. **Permisos del sistema** (`userCan`) — [ProduccionTrait.php:144](app/Traits/ProduccionTrait.php:144), [ProgramarUrdidoController.php:48](app/Http/Controllers/Urdido/ProgramaUrdido/ProgramarUrdidoController.php:48)
2. **`stripos($usuario->puesto, 'supervisor')`** — [EditarOrdenesProgramadasController.php:62](app/Http/Controllers/Urdido/ProgramaUrdido/EditarOrdenesProgramadasController.php:62), [ProgramarUrdidoController.php:31](app/Http/Controllers/Urdido/ProgramaUrdido/ProgramarUrdidoController.php:31)
3. **`SYSUsuario` + `str_contains(puesto|area, 'supervisor')`** — [UrdBpmLineController.php:181](app/Http/Controllers/Urdido/BPMUrdido/UrdBpmLineController.php:181)

Los dos últimos ignoran `SYSUsuariosRoles` por completo: dar o quitar permisos desde la pantalla de roles no cambia nada en esas pantallas. Y cualquiera cuyo puesto contenga la cadena "supervisor" (incluido "Auxiliar de Supervisor") pasa el filtro.

**Corte:** una sola regla, `userCan(..., 'Programa Urdido' | 'Producción Urdido' | 'BPM Urdido')`. Borra ~60 líneas de los tres helpers ad-hoc.

---

### 🟠 `decide:` dos implementaciones vivas de "Programar Urdido"

- `resources/views/modulos/urdido/programar-urdido.blade.php` — **1,523 líneas**, blade clásico, servido por `/urdido/programar-urdido` y por `/urdido/programaurdido` (ambas rutas → `index()` → `legacy()`).
- `app/Livewire/UrdEng/ProgramBoard.php` + `resources/views/livewire/urd-eng/program-board.blade.php` (**507 líneas**), servido por `/urdido/programar-urdido/livewire` con `Route::view` suelto.

Antes de tocar nada aquí hay que decidir cuál es la buena. Si el board Livewire es el destino, la ruta `.livewire` debería ser la principal y el blade de 1,523 líneas sale; si no, el board sale. Mantener las dos duplica cada cambio de negocio.

---

### 🟠 `shrink:` `ReportesUrdidoController` es 1,652 líneas / 7 reportes en un archivo

42 métodos, de los cuales 30 son helpers privados de un solo reporte (`buildReporte03*` ×8, `*Kaizen` ×3, `*Bpm` ×5, `*Roturas` ×2). El patrón `reporteX` + `exportarXExcel` se repite 5 veces casi idéntico, y `guardarReporteEnRed()` está incrustado ahí en medio.

`PanelControlKmService` ya demuestra el corte correcto: el reporte de panel de control ocupa **20 líneas** en el controlador porque su lógica vive en un service. Los otros seis no siguieron.

**Corte:** un service por reporte, siguiendo `PanelControlKmService`. El controlador baja a ~250 líneas. No urge, pero cualquier cambio en un reporte hoy obliga a navegar el archivo entero.

---

### 🟠 `migrate:` 29 `fetch(` inline, 0 uso de `window.http` / `window.notify`

Concentrados en [_scripts.blade.php](resources/views/modulos/urdido/produccion/_scripts.blade.php) (15), [editar-orden-programada.blade.php](resources/views/modulos/urdido/editar-orden-programada.blade.php) (12) y dos más. Cada uno repite CSRF a mano, `.then(r => r.json())` y su propio manejo de error; hay además 68 `Swal.fire` sueltos por 12 archivos.

El módulo va contra lo que dice `CLAUDE.md`. `catalagos/calendarios/` es el piloto ya migrado a `http`/`notify`.

**Corte:** ~400 líneas de plomería repetida, y los 422 de validación pasan a mostrarse solos vía `notify.validation(err.errors)`.

---

### 🟡 `delete:` rutas duplicadas y alias legacy

En [routes/modules/urdido.php](routes/modules/urdido.php), 58 rutas para ~14 pantallas:

- `programa.urdido` y `programar.urdido` → el mismo `index()`
- `configuracion.actividades-bpm` y `configuracion.actividades-bpm.legacy` → el mismo `index()`
- `configuracion.catalogos-julios` y `catalogos.julios` → `catalogosJulios()`
- `configuracion.catalogos-maquinas` y `catalogo.maquinas` → `catalogoMaquinas()`
- 3 `Route::redirect` 301 de URLs viejas
- `programar.urdido.legacy` apunta al mismo método que `index()` llama internamente

**Corte:** −10 rutas. Un `grep` de cada `route('urdido.…')` en Blade dice cuál nombre sobrevive; los alias sin uso salen.

---

### 🟡 `shrink:` `_scripts.blade.php` — 2,265 líneas de JS dentro de Blade

Vite no lo ve: no se minifica, no se cachea, no se versiona. Se retransmite entero en cada carga de Producción Urdido. Es el mismo hallazgo que en el audit de Programa Tejido.

Sólo vale la pena moverlo a `.ts` si ya se va a tocar; si no, es trabajo sin cambio funcional.

---

### 🟢 Lo que está bien y no hay que tocar

- **`ProduccionTrait`**: `ensureUserCanEdit()` en los 8 endpoints de escritura, transacciones explícitas, y `ModuloProduccionUrdidoController` comparte código real con Engomado. Es el mejor pedazo del módulo.
- **Modelos**: 617 líneas en 10 archivos, delgados y sin lógica de negocio embebida. Nada que recortar.
- **Cero `DB::raw` / `DB::statement`** en todo el módulo: no hay superficie de inyección SQL.
- **Transacciones** correctamente puestas en `finalizar`, `actualizarJulios` y `ensureProductionRecordsExist`.

---

## 3. Orden sugerido si vas a hacer cambios

1. **Los 11 endpoints sin guard** — es lo único que no es deuda de estilo, y son ~11 líneas de código.
2. **Decidir blade vs. Livewire en Programar Urdido** — bloquea cualquier trabajo en esa pantalla; hacerlo antes evita escribir dos veces.
3. **Unificar autorización a `userCan`** — mientras existan los tres mecanismos, la pantalla de roles miente.
4. El resto (reportes, `fetch`, rutas duplicadas, `_scripts`) sólo cuando toques ese archivo por otra razón.
