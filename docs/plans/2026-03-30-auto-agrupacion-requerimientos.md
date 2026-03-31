# Auto-Agrupación Requerimientos Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** (1) Eliminar la columna "Agrupar" y hardcodear `agrupar: true` para que `creacion-ordenes` agrupe automáticamente todos los telares por cuenta/calibre/tipo/urdido. (2) Eliminar los botones de filtro por columna (`req-filter-btn`) — el CSS, el estado JS y las 8 funciones de filtrado — ya que no son necesarios en esta vista.

**Architecture:** Cambio puro de frontend en un único archivo Blade. El campo `agrupar` sigue existiendo en el payload JSON que se pasa a `creacion-ordenes.js` — solo se elimina el checkbox manual y se hardcodea a `true`. La lógica de agrupación en `creacion-ordenes.js` (`agruparTelares`) permanece intacta.

**Tech Stack:** Blade (Laravel 12), JavaScript vanilla embebido, Tailwind CSS v4

---

### Contexto clave antes de tocar código

**Archivo único a modificar:**
`resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php`

**Cómo funciona el flujo:**
1. El usuario selecciona telares en `/programaurdeng` → llega a `/programa-urd-eng/programacion-requerimientos`
2. Esta vista muestra una tabla con los telares; el usuario llena metros, kilos, hilo, etc.
3. Al hacer clic en "Siguiente" (`btnSiguiente`), se recopila la data y se redirige a `/programa-urd-eng/creacion-ordenes?telares=...`
4. `creacion-ordenes.js` lee el campo `agrupar` de cada telar: si es `true`, agrupa los telares con mismo `cuenta|calibre|tipo|urdido|tipoAtado` en una sola fila; si es `false`, los muestra como filas individuales.

**El cambio:** Quitar el checkbox visible + siempre mandar `agrupar: true` → todos los telares se agrupan automáticamente en `creacion-ordenes`.

---

### Task 1: Quitar el `<th>` Agrupar del encabezado de la tabla

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php:88`

**Step 1: Eliminar el `<th>` Agrupar**

Buscar y eliminar esta línea (alrededor de la línea 88):
```html
<th class="px-2 py-3 text-left text-md font-semibold text-white w-24" data-column-field="agrupar" data-column-label="Agrupar">Agrupar</th>
```

**Step 2: Verificar visualmente**

Abrir `http://127.0.0.1:8000/programa-urd-eng/programacion-requerimientos?telares=[...]` y confirmar que la columna "Agrupar" ya no aparece en el encabezado.

---

### Task 2: Quitar `agrupar` de `REQUERIMIENTOS_COLUMN_META`

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php:166`

**Step 1: Eliminar la entrada `agrupar` del array de metadatos de columnas**

Buscar y eliminar esta línea (alrededor de la línea 166):
```js
{ field: 'agrupar', label: 'Agrupar' }
```

> Nota: La línea anterior termina en coma — asegúrate de que el array quede sintácticamente correcto (quitar la coma de la línea anterior si `agrupar` era la última entrada).

La línea `kilos` que queda como última debe verse así (sin coma final):
```js
{ field: 'kilos', label: 'Kilos' }
```

**Step 2: Verificar en consola del navegador**

Abrir DevTools → Console y verificar que no haya errores de JS al cargar la página.

---

### Task 3: Quitar el `<td>` con el checkbox de `crearFila()`

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php:478-480`

**Step 1: Eliminar el bloque `<td>` del checkbox**

Buscar y eliminar estas líneas (alrededor de la línea 478):
```js
<td class="px-2 py-3 w-24 text-center" data-column-field="agrupar">
    <input type="checkbox" class="w-4 h-4" ${telar.agrupar ? 'checked' : ''} data-field="agrupar">
</td>
```

**Step 2: Verificar visualmente**

Cargar la vista con telares seleccionados y confirmar que no aparece la columna checkbox en ninguna fila.

---

### Task 4: Eliminar las llamadas a funciones de agrupación en `renderTabla()`

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php`

**Step 1: Quitar las dos llamadas en `renderTabla()`**

Buscar y eliminar estas dos líneas (alrededor de la línea 537-541):
```js
// Aplicar agrupación automática
aplicarAgrupacionAutomatica(filtrados);

// Agregar event listeners a los checkboxes de agrupación
agregarValidacionAgrupacion();
```

> Quedan las otras llamadas que sí deben mantenerse:
> `reordenarColumnasTablaRequerimientos()`, `agregarEventListenersCamposEditables()`, etc.

**Step 2: Verificar en consola**

Recargar la página y confirmar que no hay errores `ReferenceError` en consola.

---

### Task 5: Eliminar la función `aplicarAgrupacionAutomatica`

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php:562-610`

**Step 1: Eliminar el bloque completo de la función**

Buscar y eliminar desde el comentario hasta el cierre de la función (aprox. líneas 562-610):
```js
// Función para aplicar agrupación automática basada en Cuenta, Hilo, Calibre y Tipo
function aplicarAgrupacionAutomatica(telares) {
    if (!telares || telares.length === 0) return;

    // Agrupar telares por los criterios de agrupación
    const grupos = {};
    const tipoBase = String(telares[0]?.tipo || '').toUpperCase().trim();
    const esPie = tipoBase === 'PIE';

    telares.forEach((telar, index) => {
        ...
    });

    // Marcar checkboxes de agrupación para grupos con más de un telar
    Object.values(grupos).forEach(grupo => {
        ...
    });
}
```

---

### Task 6: Eliminar las funciones auxiliares de validación de agrupación manual

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php:612-689`

**Step 1: Eliminar `puedenAgruparse`**

Buscar y eliminar la función completa (aprox. líneas 612-650):
```js
// Función para validar si dos telares pueden agruparse
function puedenAgruparse(telar1, telar2) {
    ...
}
```

**Step 2: Eliminar `obtenerMensajeErrorAgrupacion`**

Buscar y eliminar (aprox. líneas 652-661):
```js
// Función para obtener mensaje de error de agrupación
function obtenerMensajeErrorAgrupacion(motivo) {
    ...
}
```

**Step 3: Eliminar `mostrarAlertaAgrupacion`**

Buscar y eliminar (aprox. líneas 663-689):
```js
// Función para mostrar alerta de agrupación con SweetAlert2 (toast)
function mostrarAlertaAgrupacion(mensaje) {
    ...
}
```

---

### Task 7: Eliminar la función `agregarValidacionAgrupacion`

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php:1292-1368`

**Step 1: Eliminar el bloque completo**

Buscar y eliminar desde el comentario hasta el cierre de la función (aprox. líneas 1292-1368):
```js
// Función para agregar validación a los checkboxes de agrupación
function agregarValidacionAgrupacion() {
    const checkboxes = document.querySelectorAll('#tablaRequerimientos input[data-field="agrupar"]');

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function(e) {
            ...
        });
    });
}
```

---

### Task 8: Hardcodear `agrupar: true` en `btnSiguiente`

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php:1834,1850`

**Step 1: Reemplazar la lectura del checkbox por `true` literal**

Buscar (aprox. línea 1834):
```js
const agrupar = fila.querySelector('input[data-field="agrupar"]')?.checked || false;
```

Reemplazar por:
```js
const agrupar = true;
```

**Step 2: Verificar que `agrupar: true` sigue en el objeto push**

La línea aprox. 1850 debe quedar igual:
```js
agrupar: agrupar
```

> No hay que cambiarla — ahora `agrupar` siempre es `true`.

---

### Task 9: Eliminar el CSS de los filtros de columna

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php:19-68`

**Step 1: Eliminar el bloque `<style>` completo**

Buscar y eliminar el bloque entero (aprox. líneas 19-68):
```html
<style>
    #tablaRequerimientos thead th {
        position: relative;
    }
    .req-header-shell { ... }
    .req-header-label { ... }
    .req-filter-btn { ... }
    .req-filter-btn:hover { ... }
    .req-filter-btn.active { ... }
    .req-filter-indicator { ... }
    .req-filter-indicator.hidden { ... }
</style>
```

> Solo hay un bloque `<style>` en la vista — eliminar todo su contenido junto con las etiquetas de apertura y cierre.

---

### Task 10: Eliminar el estado `requerimientosColumnFilters` y `REQUERIMIENTOS_COLUMN_LABELS`

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php`

**Step 1: Eliminar la variable de estado de filtros**

Buscar y eliminar (aprox. línea 170):
```js
const requerimientosColumnFilters = {};
```

**Step 2: Eliminar `REQUERIMIENTOS_COLUMN_LABELS`**

Buscar y eliminar (aprox. línea 169):
```js
const REQUERIMIENTOS_COLUMN_LABELS = Object.fromEntries(REQUERIMIENTOS_COLUMN_META.map(col => [col.field, col.label]));
```

---

### Task 11: Eliminar las 8 funciones de filtrado de columnas

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php:725-915`

Eliminar las siguientes funciones en orden (están contiguas):

**Step 1: Eliminar `getRequerimientosDataRows()`** (aprox. líneas 725-728)
```js
function getRequerimientosDataRows() {
    return Array.from(document.querySelectorAll('#tablaRequerimientos tbody tr'))
        .filter(row => row.querySelector('[data-field="telar"]'));
}
```

**Step 2: Eliminar `getRequerimientosCellValue()`** (aprox. líneas 730-749)
```js
function getRequerimientosCellValue(row, field) { ... }
```

**Step 3: Eliminar `rowMatchesRequerimientosFilters()`** (aprox. líneas 751-757)
```js
function rowMatchesRequerimientosFilters(row, excludeField = null) { ... }
```

**Step 4: Eliminar `getAvailableValuesForRequerimientosColumn()`** (aprox. líneas 759-766)
```js
function getAvailableValuesForRequerimientosColumn(field) { ... }
```

**Step 5: Eliminar `updateRequerimientosFilterIndicators()`** (aprox. líneas 768-776)
```js
function updateRequerimientosFilterIndicators() { ... }
```

**Step 6: Eliminar `inicializarFiltrosColumnasRequerimientos()`** (aprox. líneas 778-807)
```js
function inicializarFiltrosColumnasRequerimientos() { ... }
```

**Step 7: Eliminar `applyRequerimientosColumnFilters()`** (aprox. líneas 809-814)
```js
function applyRequerimientosColumnFilters() { ... }
```

**Step 8: Eliminar `openRequerimientosColumnFilter()`** (aprox. líneas 816-916)
```js
function openRequerimientosColumnFilter(field) { ... }
```

---

### Task 12: Quitar las llamadas a funciones de filtro en `renderTabla()`

**Files:**
- Modify: `resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php`

**Step 1: Eliminar las dos llamadas en `renderTabla()`**

Buscar y eliminar (aprox. líneas 544-547):
```js
inicializarFiltrosColumnasRequerimientos();
applyRequerimientosColumnFilters();
```

> Mantener las demás llamadas: `reordenarColumnasTablaRequerimientos()`, `agregarEventListenersCamposEditables()`, etc.

**Step 2: Verificar en consola del navegador**

No debe haber `ReferenceError` de ninguna de las funciones eliminadas.

---

### Task 13: Verificación funcional end-to-end

**Step 1: Cargar la vista con telares de la misma cuenta**

Navegar a:
```
http://127.0.0.1:8000/programa-urd-eng/programacion-requerimientos?telares=[{"id":"1322","no_telar":"203","tipo":"Pie","cuenta":"3524","salon":"Jacquard","calibre":"12","hilo":"","fecha":"2026-03-27","turno":"1","tipo_atado":"Normal"}]
```

Confirmar:
- [ ] No aparece columna "Agrupar" en la tabla
- [ ] No hay errores en consola del navegador

**Step 2: Llenar metros y kilos, hacer clic en "Siguiente"**

- Llenar un valor de metros y kilos en la fila
- Seleccionar hilo
- Hacer clic en el botón "Siguiente" (flecha derecha)

Confirmar:
- [ ] Redirige a `creacion-ordenes`
- [ ] En `creacion-ordenes`, los telares con la misma cuenta/calibre/tipo aparecen agrupados en una sola fila (no como filas individuales)

**Step 3: Verificar con telares de diferentes cuentas**

Seleccionar telares con cuentas distintas en la primera pantalla.

Confirmar:
- [ ] Cada cuenta diferente aparece como fila separada en `creacion-ordenes`

---

### Task 14: Commit

**Step 1: Verificar cambios**

```bash
git diff resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php
```

Confirmar que solo hay:
- Eliminación del `<th>` Agrupar y `<td>` checkbox
- Eliminación de `agrupar` en `REQUERIMIENTOS_COLUMN_META`
- Eliminación de 5 funciones de agrupación manual y sus llamadas
- `agrupar` hardcodeado a `true` en `btnSiguiente`
- Eliminación del bloque `<style>` de filtros
- Eliminación de `requerimientosColumnFilters` y `REQUERIMIENTOS_COLUMN_LABELS`
- Eliminación de las 8 funciones de filtrado de columnas y sus llamadas

**Step 2: Commit**

```bash
git add resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php
git commit -m "feat: agrupacion automatica por cuenta y quitar filtros de columna

- Elimina columna Agrupar: todos los telares se envian con agrupar:true
  para que creacion-ordenes los agrupe automaticamente
- Elimina botones de filtro por columna y todo su CSS/JS (~200 lineas)"
```
