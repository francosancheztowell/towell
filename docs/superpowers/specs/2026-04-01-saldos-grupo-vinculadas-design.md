# Spec: Agrupación de Órdenes Vinculadas — Saldos 2026

## Problema

En el reporte `saldos-2026`, cuando varias órdenes están vinculadas (`OrdCompartida` = mismo valor), cada registro tiene sus propios valores de `TotalPedido`, `SaldoPedido`, `Produccion`, `TotalRollos`. El usuario necesita ver la **sumatoria** en el registro líder y los demás registros marcados como **"ABIERTO"**.

Adicionalmente, al filtrar u ordenar, los registros de una misma grupo nunca deben separarse.

---

## Comportamiento Esperado

### Agrupación

- Llave de grupo: `OrdCompartida`
- Registros con el mismo `OrdCompartida` (no vacío, no null) pertenecen a una grupo
- Dentro de cada grupo: el registro donde `OrdCompartidaLider = 1` es el **líder**
- Registros sin `OrdCompartida` (null o vacío) son grupos de un solo registro (sin cambios)

### Sumatoria en el Líder

Los siguientes campos se **suman** para todos los registros de la grupo y se muestran solo en el registro líder:

| Campo | Qué se suma |
|-------|-------------|
| `TotalPedido` | Cantidad a Producir sumada |
| `SaldoPedido` | SaldoPedido sumada |
| `Produccion` | Produccion sumada |
| `TotalRollos` | Rollos programados sumados |

El líder muestra `OrdenLider` (el `NoProduccion` del registro líder) en la columna "Orden Jefe Líder".

### Registros No-Líder ("ABIERTO")

- En las columnas `Cant. a Producir`, `SALDO`, `Toallas Tejidas`, `Rollos prog.` → mostrar **"ABIERTO"** como texto
- El campo `Orden Jefe Líder` del no-líder muestra el `OrdenLider` (mismo valor que el líder)
- `data-search` del no-líder incluye la cadena `"abierto"` para que sea encontrable al filtrar

### Persistencia de Grupo

- **Al filtrar:** si cualquier registro de la grupo coincide con el filtro, **todos** los registros de esa grupo se muestran. Si ninguno coincide, **todos** se ocultan.
- **Al ordenar:** los registros de una grupo siempre permanecen adyacentes, sin importar la columna por la que se ordene.
- El `sort` nunca rompe la grupo — la grupo se mueve como bloque según el valor del líder.

### Marco visual de grupo

- Fondo distintivo para los rows de una grupo: tono suave diferente del gris default
- Primera fila de cada grupo (el líder) tiene un borde izquierdo verde `3px solid #16a34a`
- No-líders tienen ligero indent visual o badge "ABIERTO" en la columna de prioridad para facilitar identificación
- Hover en cualquier row de la grupo resalta toda la grupo

---

## Implementación

### 1. Controller (`SaldosController.php`)

Modificar `query()` para que ya no traiga `TotalPedido`, `SaldoPedido`, `Produccion`, `TotalRollos` directamente — en su lugar, el PHP preprocesará los registros para calcular las sumas por grupo.

**Paso 1 — Obtener todos los registros ordenados:**
```php
$registros = ReqProgramaTejido::query()
    ->whereNotNull('NoProduccion')
    ->where('NoProduccion', '!=', '')
    ->leftJoin(DB::raw('(
        SELECT TamanoClave, Tolerancia, CodigoDibujo, FlogsId, Clave, Obs,
               TipoRizo, AlturaRizo,
               Comb1, Obs1, Comb2, Obs2, Comb3, Obs3, Comb4, Obs4,
               MedidaCenefa, MedIniRizoCenefa
        FROM (
            SELECT ..., ROW_NUMBER() OVER (PARTITION BY TamanoClave ORDER BY Id DESC) AS rn
            FROM dbo.ReqModelosCodificados
        ) AS ranked WHERE rn = 1
    ) AS rmc'), 'rmc.TamanoClave', '=', 'ReqProgramaTejido.TamanoClave')
    ->orderBy('SalonTejidoId')
    ->orderBy('NoTelarId')
    ->orderBy('Posicion')
    ->select([...todos los campos existentes...])
    ->get();
```

**Paso 2 — Preprocesar grupos en PHP:**

```php
$grupos = $registros->groupBy(function($r) {
    return $r->OrdCompartida ?? ('__solo__' . $r->Id);
});

$processed = collect();

foreach ($grupos as $key => $grupo) {
    $esGrupoVinculado = !str_starts_with($key, '__solo__');
    
    if ($esGrupoVinculado) {
        $lider = $grupo->firstWhere('OrdCompartidaLider', 1) ?? $grupo->first();
        $noLiderOrden = $lider->NoProduccion;

        // Sumas
        $sumTotalPedido  = $grupo->sum('TotalPedido');
        $sumSaldoPedido  = $grupo->sum('SaldoPedido');
        $sumProduccion   = $grupo->sum('Produccion');
        $sumTotalRollos  = $grupo->sum('TotalRollos');

        foreach ($grupo as $r) {
            $r->_esLider       = ($r->OrdCompartidaLider == 1);
            $r->_esGrupoVinculado = true;
            $r->_sumTotalPedido  = $r->_esLider ? $sumTotalPedido : null;
            $r->_sumSaldoPedido  = $r->_esLider ? $sumSaldoPedido : null;
            $r->_sumProduccion   = $r->_esLider ? $sumProduccion : null;
            $r->_sumTotalRollos  = $r->_esLider ? $sumTotalRollos : null;
            $r->_ordenLider      = $noLiderOrden;
        }
    } else {
        foreach ($grupo as $r) {
            $r->_esLider       = true; // único en su grupo
            $r->_esGrupoVinculado = false;
            $r->_sumTotalPedido  = $r->TotalPedido;
            $r->_sumSaldoPedido  = $r->SaldoPedido;
            $r->_sumProduccion   = $r->Produccion;
            $r->_sumTotalRollos  = $r->TotalRollos;
            $r->_ordenLider      = null;
        }
    }

    $processed = $processed->merge($grupo);
}

$registros = $processed;
```

### 2. Vista (`saldos-2026.blade.php`)

**Row attributes:**
```blade
@php
    $esGrupoVinculado = $r->_esGrupoVinculado ?? false;
    $esLider          = $r->_esLider          ?? true;
    $grupoBg          = $esGrupoVinculado ? 'background:#f0fdf4;' : '';
    $marcaLider       = $esLider && $esGrupoVinculado ? 'border-left:3px solid #16a34a;' : '';
@endphp
<tr class="saldos-row ...
    {{ $esGrupoVinculado ? 'saldos-row-grupo' : '' }}
    {{ $esLider ? 'saldos-row-lider' : 'saldos-row-abierto' }}"
    style="{{ $grupoBg }}{{ $marcaLider }}"
    data-lider="{{ $esLider ? '1' : '0' }}"
    data-es-grupo="{{ $esGrupoVinculado ? '1' : '0' }}"
    data-search="{{ strtolower(($r->NoTelarId ?? '') . ' ' . ($r->NoProduccion ?? '') . ' ' . ($r->NombreProducto ?? '') . ' ' . ($r->ItemId ?? '') . ' ' . ($r->TamanoClave ?? '') . ($esGrupoVinculado && !$esLider ? ' abierto' : '')) }}"
>
```

**Campos сумados en el render (PHP en la fila):**
```blade
{{-- Líder: muestra la suma. No-líder: muestra "ABIERTO" --}}
@if ($esLider)
    {{ number_format((float) ($r->_sumTotalPedido ?? $r->TotalPedido), 0) }}
@else
    <span class="saldos-abierto-badge">ABIERTO</span>
@endif
```
(Igual para `SaldoPedido`, `Produccion`, `TotalRollos`)

**CSS adicional:**
```css
.saldos-row-grupo td { background-color: #f0fdf4; }
.saldos-row-lider { font-weight: 600; }
.saldos-row-abierto td { opacity: 0.85; }
.saldos-row-abierto:hover td { opacity: 1; }

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

tr.saldos-row-grupo:hover td { background-color: #dcfce7 !important; }
```

### 3. JavaScript — Filtro que mantiene grupos

```javascript
// applyFilters ahora considera grupos
function applyFilters() {
    var rows = Array.from(tbody.querySelectorAll('tr.saldos-row'));
    var shown = 0;

    // Primera pasada: marcar ocultos/visibles individuales
    rows.forEach(function(row) {
        var show = !globalQ || (row.dataset.search || '').includes(globalQ);
        if (show) {
            for (var idx in colFilters) {
                var v = colFilters[idx];
                if (v && !getCellText(row, parseInt(idx)).includes(v)) { show = false; break; }
            }
        }
        row._miShow = show;
    });

    // Segunda pasada: propagar visibilidad a toda la grupo
    rows.forEach(function(row) {
        if (row.dataset.esGrupo !== '1') return;
        var grupoRows = obtenerGrupoRows(row);
        var algunaVisible = grupoRows.some(function(r) { return r._miShow; });
        grupoRows.forEach(function(r) { r._miShow = algunaVisible; });
    });

    rows.forEach(function(row) {
        row.classList.toggle('saldos-hidden', !row._miShow);
        if (row._miShow) shown++;
    });

    if (counter) counter.textContent = shown;
    if (visibleEl) visibleEl.textContent = shown;
}

function obtenerGrupoRows(rowLider) {
    // Encuentra todas las filas adyacentes con data-es-grupo="1"
    // que pertencen a la misma grupo (misma OrdenLider)
    var ordenLider = rowLider.querySelector('td:nth-child(3)').textContent.trim();
    var grupo = [];
    var allRows = Array.from(tbody.querySelectorAll('tr.saldos-row'));
    var found = false;
    for (var r of allRows) {
        if (r === rowLider) { found = true; }
        if (found) {
            var esGrupo = r.dataset.esGrupo === '1';
            var esLider = r.dataset.lider === '1';
            if (!esGrupo || (esGrupo && esLider && r !== rowLider)) break;
            grupo.push(r);
        }
    }
    return grupo;
}
```

### 4. JavaScript — Sort que mantiene grupos

```javascript
function sortByCol(colIdx, dir) {
    if (!tbody || colIdx === null) return;

    // Extraer grupos como bloques
    var bloques = [];
    var bloqueActual = [];
    var allRows = Array.from(tbody.querySelectorAll('tr.saldos-row'));

    allRows.forEach(function(row) {
        if (row.dataset.esGrupo === '1' && row.dataset.lider !== '1') {
            // No-líder: agregar al bloque actual
            bloqueActual.push(row);
        } else {
            // Líder o no-grupo: cerrar bloque anterior si existe
            if (bloqueActual.length > 0) bloques.push(bloqueActual);
            bloqueActual = [];
            // Crear bloque para este líder o registro solo
            bloqueActual.push(row);
        }
    });
    if (bloqueActual.length > 0) bloques.push(bloqueActual);

    // Ordenar bloques por el valor de la columna del líder (índice 0 del bloque)
    bloques.sort(function(a, b) {
        var liderA = a[0], liderB = b[0];
        var ta = getCellText(liderA, colIdx);
        var tb = getCellText(liderB, colIdx);
        var na = parseFloat(ta.replace(/,/g,'')), nb = parseFloat(tb.replace(/,/g,''));
        var cmp = (!isNaN(na) && !isNaN(nb)) ? (na - nb) : ta.localeCompare(tb, 'es', {sensitivity:'base'});
        return dir === 'asc' ? cmp : -cmp;
    });

    // Reconstruir tbody
    bloqueActual.forEach(function(bloque) {
        bloque.forEach(function(row) { tbody.appendChild(row); });
    });
}
```

---

## Archivos a Modificar

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Tejido/Reportes/SaldosController.php` | Preprocesar grupos, calcular sumas, agregar atributos `_esLider`, `_esGrupoVinculado`, `_sum*` |
| `resources/views/modulos/tejido/reportes/saldos-2026.blade.php` | Usar valores precalculados, badge "ABIERTO", estilos de grupo, `data-search` con "abierto" |
| `app/Exports/Saldos2026Export.php` | Adaptar si exporta a reflectar la misma lógica de sumas (revisar) |

---

## Verificación

1. Abrir `http://127.0.0.1:8000/tejido/reportes/saldos-2026`
2. Identificar una grupo de órdenes vinculadas (misma `OrdCompartida`, ver `Orden Vinculada` columna)
3. Confirmar que el registro líder muestra la **suma** en `Cant. a Producir`, `SALDO`, `Toallas Tejidas`, `Rollos prog.`
4. Confirmar que los no-líders muestran **"ABIERTO"** en esos mismos campos
5. Confirmar que todos los registros de la grupo están **juntos** visualmente (mismo fondo, borde verde en líder)
6. Filtrar por cualquier valor que coincida con un no-líder → la grupo completa se muestra
7. Ordenar por cualquier columna → la grupo no se rompe
8. Badge "ABIERTO" visible en los no-líders al hacer hover

---

## Dependencias

Ninguna nueva. Solo modificaciones en el controller existente, vista existente y JavaScript existente.
