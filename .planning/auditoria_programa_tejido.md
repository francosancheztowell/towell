# Auditoría — Pantalla Programa de Tejido

Fecha: 2026-08-18 · Alcance: `resources/views/modulos/programa-tejido/**` + `app/Http/Controllers/Planeacion/ProgramaTejido/**`

Todas las cifras están medidas contra ProdTowel con las 78 órdenes reales, no estimadas.

---

## 1. Lo que hay hoy

| Medida | Valor |
|---|---|
| Blade en `modulos/programa-tejido/` | **19,553 líneas** en 19 archivos |
| Controladores `Planeacion/ProgramaTejido/` | **16,668 líneas** en 29 archivos |
| JavaScript **dentro** de Blade | **8,627 líneas** |
| HTML de una carga de `/planeacion/programa-tejido` | **3,287 KB** |
| └─ `<script>` inline | **694 KB** en 19 bloques |
| └─ marcado de tabla | **2,037 KB** en 7,317 `<td>` (~285 B/celda) |
| Consultas SQL | 4 · **33 ms** |
| Render total | **436 ms** |

El archivo más grande es `scripts/main.blade.php`: **3,904 líneas**, casi todo JavaScript.

**La base de datos no es el cuello de botella.** 4 consultas y 33 ms. El problema es que se envían 3.2 MB por carga para pintar 78 filas.

---

## 2. Hallazgos, del corte más grande al más pequeño

### 🔴 `delete:` No hay ni una comprobación de permisos en el servidor
`grep userCan` en los 29 controladores de ProgramaTejido → **0 resultados**. Las rutas de escritura (`dividir-telar`, `duplicar-telar`, `balancear-automatico`, `crear-repaso`, `DELETE /{id}`…) solo llevan middleware `auth`.

Los permisos existen únicamente como atributo `disabled` en el botón. Cualquier usuario autenticado puede hacer `POST` a cualquier endpoint desde la consola del navegador, sin importar su rol.

Además, 4 de los 10 controles de `components/navbar/sections/programa-tejido.blade.php` son `<button>` HTML crudo sin ninguna comprobación, ni siquiera cosmética.

> Esto no es deuda de estilo: es que el control de acceso de la pantalla **no existe**. Va primero, antes que cualquier migración.

**Nota a favor:** los componentes `x-navbar.button-*` sí comprueban el permiso correcto cada uno (`crear`/`modificar`/`eliminar`/`registrar`). El diseño está bien; lo que falta es el lado servidor.

---

### 🔴 `shrink:` 694 KB de `<script>` inline por carga → ~0 KB cacheable
19 bloques `<script>` incrustados en Blade. Vite no los ve: **no se minifican, no se cachean, no se versionan**. Se retransmiten íntegros en cada F5 y en cada navegación.

Movidos a `.ts` compilados por Vite: se minifican, se parten por ruta, y el navegador los cachea con hash. La segunda carga baja a cero para ese tramo.

**Corte: −694 KB por carga.**

---

### 🟠 `shrink:` 2 MB de tabla para 78 filas
7,317 celdas a ~285 bytes cada una. Cada `<td>` repite la cadena completa de clases Tailwind, más `data-column` y `data-value` que **duplican el texto ya visible en la celda**:

```html
<td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap column-45 "
    data-column="PasadasComb1" data-value="30" >
  30
</td>
```

Y el servidor manda **las 74 columnas siempre**; `scripts/columns.blade.php` las esconde después con `el.style.display = 'none'`. Se paga el peso de columnas que el usuario nunca ve.

**Corte estimado: −60% del marcado** quitando `data-value` (derivable del `textContent`), sacando las clases repetidas a una regla CSS, y renderizando solo las columnas visibles.

---

### 🟠 `delete:` 9 funciones duplicadas literalmente entre dos archivos
`liberar-ordenes/index.blade.php` tiene copiadas de `scripts/columns.blade.php` y `scripts/filters.blade.php`:

`pinColumn` · `unpinColumn` · `showColumn` · `hideColumn` · `openPinColumnsModal` · `openHideColumnsModal` · `updatePinnedColumnsPositions` · `addCustomFilter` · `removeFilter`

Un solo módulo `columns.ts` importado por ambas. **Corte: ~600 líneas.**

---

### 🟠 `native:` 70 `fetch(` crudos con la infraestructura ya construida
El proyecto ya tiene `window.http` (axios, CSRF automático, errores normalizados) y `window.notify`, documentados en CLAUDE.md como el camino a seguir.

En programa-tejido: **70 `fetch(` contra 3 `window.http`**. Cada uno reimplementa a mano el CSRF, el `.then(r => r.json())` y el manejo de errores.

**Corte: ~350 líneas.** Sin dependencias nuevas: ya está instalado y es la convención del repo.

---

### 🟡 `stdlib:` `showToast` invocado a la defensiva 20+ veces
El patrón repetido por todo el módulo:

```js
if (typeof window.showToast === 'function') { ... }
```

Esa guarda existe porque nadie sabe si la función está definida en ese contexto. Hay además **47 `Swal.fire`** sueltos y 3 `toastr.`, tres mecanismos para lo mismo.

`window.notify` ya cubre los tres casos y escapa HTML (los `Swal.fire` con `html:` de datos de BD no lo hacen). **Corte: ~200 líneas y una clase de XSS.**

---

### 🟡 `yagni:` `duplicar-dividir.blade.php` son 3,381 líneas para 3 `@include`
El archivo abre con tres `@include` (`_shared-helpers`, `_duplicar-vincular`, `_dividir`) y luego trae 3,300 líneas propias. `_shared-helpers.blade.php` son otras 1,629, con 12 `location.reload()` dentro.

Entre los cuatro archivos: **6,590 líneas para dos modales.**

---

### 🟡 `shrink:` 16 recargas completas de página
`location.reload()` / `location.href =` repartidos, 12 de ellos solo en `_shared-helpers.blade.php`. Cada uno vuelve a pagar los 3.2 MB.

Este es exactamente el *"que no vuelva a recargar"* del encargo, y es consecuencia directa de no tener un componente con estado.

---

## 3. Lo que ya está resuelto y hay que copiar, no inventar

El repo **ya tiene** todo lo que pide el encargo, funcionando en otros módulos:

| Pieza | Dónde | Qué aporta |
|---|---|---|
| **TypeScript** | `tsconfig.json`, 23 archivos `.ts` | `strict`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes` |
| **Livewire 4.3** | 10 componentes | `Crudo/`, `Trazabilidad/`, `UrdEng/ProgramBoard` |
| **`ConTabla`** | `app/Livewire/Concerns/ConTabla.php` | búsqueda, orden, paginación y selección, con `#[Url]` |
| **`<x-tabla>`** | `resources/views/components/tabla.blade.php` | la tabla ya pintada |
| **Islas TS** | `resources/js/urd-eng/*.ts` (38–99 líneas c/u) | sortable, fullscreen, feedback |
| **Servicios** | `ProgramBoardReadService` / `ActionService` | el componente Livewire queda en 429 líneas |

`UrdEng/ProgramBoard` es una pantalla de tablero con drag & drop, filtros y acciones — **el mismo problema, ya resuelto en 429 líneas de componente + tres islas TS de menos de 100 líneas**.

El `tsconfig.json` usa lista blanca por módulo. Entrar es añadir una línea:

```json
"include": [ ..., "resources/js/programa-tejido/**/*.ts" ]
```

---

## 4. Plan por fases

Cada fase entrega valor sola y es reversible. **Ninguna cambia el diseño visual.**

### Fase 0 — Red de seguridad (antes de tocar nada)
Sin esto, una migración de 19,553 líneas es a ciegas.

- Test de humo por ruta: las ~25 rutas de programa-tejido responden 200/302.
- Test de contrato de payload: la respuesta JSON de `dividir`, `duplicar`, `balancear` y `update` mantiene su forma.
- Snapshot del HTML de la tabla para comparar antes/después.

### Fase 1 — Permisos en el servidor 🔴
La única fase que arregla un agujero real. **Independiente del resto: se puede hacer hoy.**

- Un `FormRequest` (o middleware `puede:{permiso},{modulo}`) por ruta de escritura.
- `crear` → duplicar/dividir/repaso · `modificar` → update/balancear/calendarios/drag&drop · `eliminar` → destroy
- Los 4 `<button>` crudos de la navbar pasan a `x-navbar.button-*` con su `module`.
- Test: usuario sin `eliminar` recibe **403** al hacer `DELETE`, aunque el botón esté deshabilitado en pantalla.

### Fase 2 — Sacar el JS de Blade a TS tipado
Sin Livewire todavía. Puro movimiento de código, verificable con los snapshots de la Fase 0.

```
resources/js/programa-tejido/
  types.ts        ← forma de ReqProgramaTejido (los campos que usa el front)
  columns.ts      ← mata la duplicación de las 9 funciones
  filters.ts
  inline-edit.ts
  selection.ts
  state.ts
  api.ts          ← window.http, adiós a los 70 fetch
```

Añadir la ruta al `include` del `tsconfig.json`. Los `Swal.fire` y `showToast` pasan a `notify`.

**Corte esperado: −694 KB por carga, −1,100 líneas.**

### Fase 3 — Adelgazar el marcado
- Quitar `data-value` (usar `textContent`).
- Clases repetidas de celda → una regla CSS.
- Renderizar solo las columnas visibles.

**Corte esperado: −60% de los 2 MB de tabla.**

### Fase 4 — Livewire, copiando `ProgramBoard`
- `App\Livewire\Planeacion\ProgramaTejido` + `use ConTabla`.
- La lógica de negocio a `ProgramaTejidoReadService` / `ActionService`; los controladores actuales quedan como fachada mientras dure la transición.
- Los 16 `location.reload()` → `$this->dispatch()` / re-render parcial.
- El drag & drop sigue siendo una isla TS (`sortable-board.ts` ya existe en urd-eng).
- **El Blade de la tabla se conserva tal cual**: mismo diseño, mismas clases.

### Fase 5 — Retirar lo viejo
Borrar `scripts/*.blade.php` y los controladores que quedaron sin usar, una vez que la Fase 0 confirme paridad.

---

## 5. Balance

```
net: -8,600 líneas de JS fuera de Blade (a TS tipado)
     -1,700 líneas de duplicación y fetch/toast a mano
     -694 KB por carga (script inline → bundle cacheado)
     -1,200 KB por carga (marcado de tabla)
     -16 recargas completas de página
      0 dependencias nuevas
```

**Riesgo principal:** son 36,000 líneas entre vistas y controladores. La Fase 0 no es burocracia, es lo que hace que las demás sean reversibles.

**Si solo se hace una cosa:** la Fase 1. Las demás son calidad y rendimiento; esa es la que cierra un agujero de seguridad que hoy está abierto.

---

## 6. Dos decisiones que necesito de ti

1. **Fase 1 sola y ya, o el plan completo por fases.** Se puede cerrar el agujero de permisos esta semana sin tocar nada más.
2. **Alcance de la Fase 4.** Solo la tabla principal, o también `liberar-ordenes` y `balancear` (que suman otras 4,200 líneas y tienen su propia copia del código de columnas).
