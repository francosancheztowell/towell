# Saldos 2026 — Agrupación de Órdenes Vinculadas

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Agrupar registros con misma `OrdCompartida`, mostrar sumas en el líder, "ABIERTO" en no-líders, y mantener grupos intactos al filtrar/ordenar.

**Architecture:** Preprocesamiento en PHP en el controller (calcula sumas por grupo), atributos `_esLider`, `_esGrupoVinculado`, `_sum*` inyectados en cada modelo. Vista usa esos atributos para render. JS modifica filter/sort para mantener bloques.

**Tech Stack:** Laravel 12, Blade, PHP 8, JavaScript vanilla (no frameworks).

---

## File Inventory

| Archivo | Responsabilidad |
|---------|-----------------|
| `app/Http/Controllers/Tejido/Reportes/SaldosController.php` | Preprocesar grupos, calcular sumas |
| `resources/views/modulos/tejido/reportessaldos-2026.blade.php` | Render con valores precalculados, badge "ABIERTO", estilos grupo |
| `app/Exports/Saldos2026Export.php` | Adaptar export para usar mismos valores (revisar) |

---

## Task 1: Modificar SaldosController — Preprocesamiento de grupos

**Files:**
- Modify: `app/Http/Controllers/Tejido/Reportes/SaldosController.php`

- [ ] **Step 1: Reemplazar método index()**

Reemplazar todo el método `index()` existente con:

```php
public function index()
{
    $registros = $this->query()->get();
    $registros = $this->preprocesarGrupos($registros);

    return view('modulos.tejido.reportes.saldos-2026', compact('registros'));
}
```

- [ ] **Step 2: Reemplazar método exportarExcel()**

```php
public function exportarExcel()
{
    $registros = $this->query()->get();
    $registros = $this->preprocesarGrupos($registros);

    return Excel::download(new Saldos2026Export($registros), 'saldos-2026.xlsx');
}
```

- [ ] **Step 3: Agregar método preprocesarGrupos() después de query()**

```php
private function preprocesarGrupos($registros)
{
    $grupos = $registros->groupBy(function ($r) {
        $clave = trim($r->OrdCompartida ?? '');
        return $clave !== '' ? $clave : '__solo__' . $r->Id;
    });

    $processed = collect();

    foreach ($grupos as $key => $grupo) {
        $esGrupoVinculado = !str_starts_with($key, '__solo__');

        if ($esGrupoVinculado) {
            // Líder: OrdCompartidaLider=1, o el primero si none tiene ese flag
            $lider = $grupo->firstWhere('OrdCompartidaLider', 1) ?? $grupo->first();
            $noLiderOrden = $lider->NoProduccion;

            $sumTotalPedido = $grupo->sum('TotalPedido');
            $sumSaldoPedido = $grupo->sum('SaldoPedido');
            $sumProduccion  = $grupo->sum('Produccion');
            $sumTotalRollos = $grupo->sum('TotalRollos');

            foreach ($grupo as $r) {
                $r->_esLider           = ($r->OrdCompartidaLider == 1);
                $r->_esGrupoVinculado  = true;
                $r->_ordenLider        = $noLiderOrden;
                if ($r->_esLider) {
                    $r->_sumTotalPedido = $sumTotalPedido;
                    $r->_sumSaldoPedido = $sumSaldoPedido;
                    $r->_sumProduccion  = $sumProduccion;
                    $r->_sumTotalRollos = $sumTotalRollos;
                } else {
                    $r->_sumTotalPedido = null;
                    $r->_sumSaldoPedido = null;
                    $r->_sumProduccion  = null;
                    $r->_sumTotalRollos = null;
                }
            }
        } else {
            // Grupo de un solo registro (no vinculado)
            foreach ($grupo as $r) {
                $r->_esLider          = true;
                $r->_esGrupoVinculado = false;
                $r->_ordenLider       = null;
                $r->_sumTotalPedido   = $r->TotalPedido;
                $r->_sumSaldoPedido   = $r->SaldoPedido;
                $r->_sumProduccion    = $r->Produccion;
                $r->_sumTotalRollos   = $r->TotalRollos;
            }
        }

        $processed = $processed->merge($grupo);
    }

    return $processed;
}
```

- [ ] **Step 4: Verificar que query() no cambia**

El método `query()` queda exactamente igual. Solo index() y exportarExcel() llaman a `preprocesarGrupos()` después de `query()->get()`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Tejido/Reportes/SaldosController.php
git commit -m "tejido: preprocesar grupos de ordenes vinculadas en SaldosController"
```

**Verify:** `php artisan tinker --execute="echo App\Models\Planeacion\ReqProgramaTejido::count();"` (verifica que el modelo sigue funcionando)

---

## Task 2: Modificar Blade — Atributos de fila y data-search

**Files:**
- Modify: `resources/views/modulos/tejido/reportessaldos-2026.blade.php:106-230`

- [ ] **Step 1: Modificar el opening `<tr>` del loop `@foreach ($registros as $i => $r)`**

Buscar (línea ~118):
```blade
<tr class="saldos-row {{ $esRepaso ? 'saldos-row-repaso' : ($i % 2 === 0 ? 'saldos-row-even' : 'saldos-row-odd') }}"
    data-search="{{ strtolower(($r->NoTelarId ?? '') . ' ' . ($r->NoProduccion ?? '') . ' ' . ($r->NombreProducto ?? '') . ' ' . ($r->ItemId ?? '') . ' ' . ($r->TamanoClave ?? '')) }}">
```

Reemplazar con:
```blade
@php
    $esRepaso          = !empty($r->NoExisteBase);
    $esGrupoVinculado  = $r->_esGrupoVinculado ?? false;
    $esLider           = $r->_esLider ?? true;
    $rowClass          = $esRepaso ? 'saldos-row-repaso' : ($i % 2 === 0 ? 'saldos-row-even' : 'saldos-row-odd');
    $grupoClass        = $esGrupoVinculado ? ' saldos-row-grupo' : '';
    $liderClass        = $esLider ? ' saldos-row-lider' : ' saldos-row-abierto';
    $searchBase        = strtolower(($r->NoTelarId ?? '') . ' ' . ($r->NoProduccion ?? '') . ' ' . ($r->NombreProducto ?? '') . ' ' . ($r->ItemId ?? '') . ' ' . ($r->TamanoClave ?? ''));
    $searchFull        = $esGrupoVinculado && !$esLider ? $searchBase . ' abierto' : $searchBase;
@endphp
<tr class="saldos-row {{ $rowClass }}{{ $grupoClass }}{{ $liderClass }}"
    style="{{ $esGrupoVinculado ? 'background:#f0fdf4;' : '' }}{{ $esLider && $esGrupoVinculado ? 'border-left:3px solid #16a34a;' : '' }}"
    data-search="{{ $searchFull }}"
    data-es-grupo="{{ $esGrupoVinculado ? '1' : '0' }}"
    data-lider="{{ $esLider ? '1' : '0' }}">
```

- [ ] **Step 2: Modificar celdas de campos sumados**

**Cant. a Producir (buscar ~línea 181):**
```blade
<td class="saldos-td saldos-td-solsaldo text-right tabular-nums font-semibold">{{ $r->TotalPedido !== null ? number_format((float)$r->TotalPedido, 0) : '—' }}</td>
```
Reemplazar con:
```blade
<td class="saldos-td saldos-td-solsaldo text-right tabular-nums font-semibold">
    @if ($esLider)
        {{ number_format((float) ($r->_sumTotalPedido ?? $r->TotalPedido ?? 0), 0) }}
    @else
        <span class="saldos-abierto-badge">ABIERTO</span>
    @endif
</td>
```

**Toallas Tejidas (buscar ~línea 219):**
```blade
<td class="saldos-td text-right tabular-nums font-medium text-gray-800">{{ $r->Produccion !== null ? number_format((float)$r->Produccion, 0) : '—' }}</td>
```
Reemplazar con:
```blade
<td class="saldos-td text-right tabular-nums font-medium text-gray-800">
    @if ($esLider)
        {{ number_format((float) ($r->_sumProduccion ?? $r->Produccion ?? 0), 0) }}
    @else
        <span class="saldos-abierto-badge">ABIERTO</span>
    @endif
</td>
```

**SALDO (buscar ~línea 220-221):**
```blade
<td class="saldos-td saldos-td-solsaldo text-right tabular-nums font-semibold {{ ($r->SaldoPedido ?? 0) > 0 ? 'text-indigo-700' : 'text-gray-400' }}">
    {{ $r->SaldoPedido !== null ? number_format((float)$r->SaldoPedido, 0) : '—' }}
</td>
```
Reemplazar con:
```blade
<td class="saldos-td saldos-td-solsaldo text-right tabular-nums font-semibold {{ ($r->_sumSaldoPedido ?? 0) > 0 ? 'text-indigo-700' : 'text-gray-400' }}">
    @if ($esLider)
        {{ number_format((float) ($r->_sumSaldoPedido ?? $r->SaldoPedido ?? 0), 0) }}
    @else
        <span class="saldos-abierto-badge">ABIERTO</span>
    @endif
</td>
```

**Rollos prog. (buscar ~línea 218):**
```blade
<td class="saldos-td text-right tabular-nums" style="background:#dcfce7;border-color:#86efac;">{{ $r->TotalRollos !== null ? number_format((float)$r->TotalRollos, 0) : '—' }}</td>
```
Reemplazar con:
```blade
<td class="saldos-td text-right tabular-nums" style="background:#dcfce7;border-color:#86efac;">
    @if ($esLider)
        {{ number_format((float) ($r->_sumTotalRollos ?? $r->TotalRollos ?? 0), 0) }}
    @else
        <span class="saldos-abierto-badge">ABIERTO</span>
    @endif
</td>
```

**Faltan (buscar ~línea 223):** También necesita actualizarse para usar sumas:
```blade
{{ $solicitado > 0 ? number_format($faltan, 0) : '—' }}
```
Cambiar a:
```blade
{{ $esLider && $esGrupoVinculado ? number_format(((float)($r->_sumTotalPedido ?? 0)) - ((float)($r->_sumSaldoPedido ?? 0)), 0) : ($solicitado > 0 ? number_format($faltan, 0) : '—') }}
```

- [ ] **Step 3: Agregar CSS para ABIERTO badge y grupo**

Buscar en `@push('styles')` (después de la línea ~503, antes de `</style>`):

```css
/* ABIERTO badge */
.saldos-abierto-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 1px 8px;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
    border-radius: 9999px;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.05em;
}

/* Grupo vinculada */
.saldos-row-grupo td { background-color: #f0fdf4; }
.saldos-row-lider { font-weight: 600; }
.saldos-row-abierto td { opacity: 0.85; }
.saldos-row-abierto:hover td { opacity: 1; }
tr.saldos-row-grupo:hover td { background-color: #dcfce7 !important; }
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/modulos/tejido/reportessaldos-2026.blade.php
git commit -m "tejido: render grupo vinculadas con sumas en lider y ABIERTO en no-liders"
```

**Verify:** `php artisan route:list --name=saldos-2026` y abrir en navegador para verificar visualmente.

---

## Task 3: Modificar JavaScript — Filtro y Sort mantienen grupos

**Files:**
- Modify: `resources/views/modulos/tejido/reportessaldos-2026.blade.php` (solo la sección `@push('scripts')`)

- [ ] **Step 1: Reemplazar applyFilters()**

Buscar la función `applyFilters` completa (líneas ~1024-1059) y reemplazarla con:

```javascript
function applyFilters() {
    var rows = Array.from(tbody.querySelectorAll('tr.saldos-row'));
    var shown = 0;

    // Primera pasada: evaluar cada fila individualmente
    rows.forEach(function(row) {
        var show = !globalQ || (row.dataset.search || '').includes(globalQ);
        if (show) {
            for (var idx in colFilters) {
                var v = colFilters[idx];
                if (v && !getCellText(row, parseInt(idx)).includes(v)) { show = false; break; }
            }
        }
        if (show && Object.keys(columnFilters).length > 0) {
            for (var colIdx in columnFilters) {
                var allowed = columnFilters[colIdx];
                if (!allowed || allowed.length === 0) continue;
                var cellVal = getCellTextForCol(row, parseInt(colIdx));
                var key = cellVal === '' ? '(vacío)' : cellVal;
                if (!allowed.includes(key)) { show = false; break; }
            }
        }
        row._miShow = show;
    });

    // Segunda pasada: si cualquier row de un grupo está visible, mostrar todos
    rows.forEach(function(row) {
        if (row.dataset.esGrupo !== '1') return;
        var grupoRows = getGrupoBloque(row);
        var algunaVisible = grupoRows.some(function(r) { return r._miShow; });
        grupoRows.forEach(function(r) { r._miShow = algunaVisible; });
    });

    rows.forEach(function(row) {
        row.classList.toggle('saldos-hidden', !row._miShow);
        if (row._miShow) shown++;
    });

    if (counter) counter.textContent = shown;
    if (visibleEl) visibleEl.textContent = shown;

    if (checkAll) {
        var vis = tbody.querySelectorAll('tr.saldos-row:not(.saldos-hidden) .saldos-row-check');
        var chk = tbody.querySelectorAll('tr.saldos-row:not(.saldos-hidden) .saldos-row-check:checked');
        checkAll.indeterminate = chk.length > 0 && chk.length < vis.length;
        checkAll.checked = vis.length > 0 && chk.length === vis.length;
    }
}
```

- [ ] **Step 2: Agregar función getGrupoBloque()**

Agregar después de `getCellTextForCol()` (~línea 855):

```javascript
function getGrupoBloque(rowLider) {
    // rowLider es un <tr> que es líder de grupo (data-lider="1", data-es-grupo="1")
    // Retorna array con el líder + todos los no-líders adyacentes de la misma grupo
    var bloque = [];
    var allRows = Array.from(tbody.querySelectorAll('tr.saldos-row'));
    var liderIdx = allRows.indexOf(rowLider);
    if (liderIdx === -1) return [rowLider];

    // Recopilar no-líders después del líder
    for (var i = liderIdx + 1; i < allRows.length; i++) {
        var r = allRows[i];
        if (r.dataset.esGrupo !== '1') break; // fin del grupo
        if (r.dataset.lider === '1') break;   // otro líder = fin del grupo
        bloque.push(r);
    }

    // Devolver [lider] + [no-líders]
    bloque.unshift(rowLider);
    return bloque;
}
```

- [ ] **Step 3: Reemplazar sortByCol()**

Buscar la función `sortByCol()` (líneas ~1064-1081) y reemplazarla con:

```javascript
function sortByCol(colIdx, dir) {
    if (!tbody || colIdx === null) return;

    // Extraer bloques [líder + no-líders adyacentes]
    var bloques = [];
    var bloqueActual = [];
    var allRows = Array.from(tbody.querySelectorAll('tr.saldos-row'));

    allRows.forEach(function(row) {
        if (row.dataset.esGrupo === '1' && row.dataset.lider !== '1') {
            // No-líder: agregar al bloque actual
            bloqueActual.push(row);
        } else {
            // Líder o no-grupo: cerrar bloque anterior
            if (bloqueActual.length > 0) bloques.push(bloqueActual);
            bloqueActual = [row];
        }
    });
    if (bloqueActual.length > 0) bloques.push(bloqueActual);

    // Ordenar bloques por el valor del líder (índice 0 del bloque)
    bloques.sort(function(a, b) {
        var ta = getCellText(a[0], colIdx);
        var tb = getCellText(b[0], colIdx);
        var na = parseFloat(ta.replace(/,/g, '')), nb = parseFloat(tb.replace(/,/g, ''));
        var cmp = (!isNaN(na) && !isNaN(nb)) ? (na - nb) : ta.localeCompare(tb, 'es', { sensitivity: 'base' });
        return dir === 'asc' ? cmp : -cmp;
    });

    // Reconstruir tbody: vaciar y re-agregar bloques en orden
    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
    bloques.forEach(function(bloque) {
        bloque.forEach(function(row) { tbody.appendChild(row); });
    });

    // Actualizar sort indicator en header
    table.querySelectorAll('[data-sort-dir]').forEach(function(el) { el.removeAttribute('data-sort-dir'); });
    var hdrCells = colCells[colIdx] || [];
    var hdrCell = hdrCells.find(function(c) { return c.closest('thead') && c.tagName === 'TH'; });
    if (hdrCell) hdrCell.setAttribute('data-sort-dir', dir);
}
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/modulos/tejido/reportessaldos-2026.blade.php
git commit -m "tejido: filtro y sort mantienen bloques de grupos vinculadas"
```

**Verify:** Abrir en navegador, identificar grupo, filtrar por valor del no-líder, confirmar grupo completa visible. Ordenar por columna y confirmar grupo no se rompe.

---

## Task 4: Revisar Saldos2026Export

**Files:**
- Modify: `app/Exports/Saldos2026Export.php`

- [ ] **Step 1: Leer el archivo export para entender cómo usa los datos**

```bash
cat app/Exports/Saldos2026Export.php
```

- [ ] **Step 2: Si el export accede directamente a TotalPedido/SaldoPedido/etc., modificar para usar _sum* del líder**

El export recibe `$registros` ya preprocesados (del controller). Si un registro tiene `_esLider = false`, debería mostrar "ABIERTO" en las celdas sumadas, similar a la vista Blade.

- [ ] **Step 3: Commit**

```bash
git add app/Exports/Saldos2026Export.php
git commit -m "tejido: exportar excel con misma logica de grupo vinculado"
```

---

## Verification Checklist

Después de implementar, verificar en `http://127.0.0.1:8000/tejido/reportes/saldos-2026`:

1. [ ] Grupo con `OrdCompartida` visible tiene líder con borde verde a la izquierda
2. [ ] Líder muestra sumas (valores grandes, > que cualquier registro individual)
3. [ ] No-líders muestran "ABIERTO" en Cant. a Producir, SALDO, Toallas Tejidas, Rollos prog.
4. [ ] Badge "ABIERTO" en color ámbar/amarillo visible
5. [ ] Filtrar por texto que solo existe en no-líder → grupo completa se muestra
6. [ ] Ordenar por cualquier columna → líder y no-líders se mueven juntos
7. [ ] Hover en cualquier row de grupo → toda la grupo cambia a verde más oscuro
8. [ ] Registros sin grupo (no vinculados) funcionan igual que antes
9. [ ] Exportar Excel descarga correctamente

---

## Notes

- El campo `data-es-grupo="1"` identifica rows que son parte de una grupo vinculada
- El campo `data-lider="1"` identifica si el row es líder de su grupo
- La grupo se detecta buscando rows adyacentes con `data-es-grupo="1"` hasta encontrar un líder nuevo o fin de grupo
- No se usan collapsible/expandable — todos los rows existen en el DOM siempre
