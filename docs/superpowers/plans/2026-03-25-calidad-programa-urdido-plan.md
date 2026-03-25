# Feature Calidad en Programa Urdido - Plan de Implementación

**Goal:** Agregar evaluación de calidad (aprobado/rechazado/observaciones) a cada orden del programa urdido con indicador visual en tabla.

**Architecture:** Modal vanilla JS + Tailwind siguiendo patrón del modal "Editar Prioridad" existente. Datos via AJAX.

**Tech Stack:** Laravel 12, Blade, Vanilla JS, Tailwind, SQL Server

---

## File Structure

- Modify: `app/Models/Urdido/UrdProgramaUrdido.php` — agregar cast
- Modify: `app/Http/Controllers/Urdido/ProgramaUrdido/ProgramarUrdidoController.php` — nuevo método + actualizar getOrdenes
- Modify: `routes/modules/urdido.php` — nueva ruta POST
- Modify: `resources/views/modulos/urdido/programar-urdido.blade.php` — columna, botón, modal, JS

---

## Task 1: Actualizar Modelo

**Files:** `app/Models/Urdido/UrdProgramaUrdido.php`

- [ ] Step 1: Agregar cast `'calidad' => 'string'` en protected $casts

---

## Task 2: Agregar método actualizarCalidad

**Files:** `app/Http/Controllers/Urdido/ProgramaUrdido/ProgramarUrdidoController.php`

- [ ] Step 1: Agregar `use Illuminate\Validation\Rule;` al inicio del archivo
- [ ] Step 2: Agregar método actualizarCalidad después de guardarObservaciones (~línea 677)

```php
public function actualizarCalidad(Request $request): JsonResponse
{
    try {
        if (!$this->usuarioPuedeEditar()) {
            return response()->json(['success' => false, 'error' => 'No autorizado'], 403);
        }

        $request->validate([
            'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            'calidad' => ['required', 'string', Rule::in(['A', 'R', 'O'])],
            'calidadcomentario' => 'nullable|string|max:60',
        ]);

        $orden = UrdProgramaUrdido::findOrFail($request->id);
        $orden->calidad = $request->calidad;
        $orden->calidadcomentario = $request->calidadcomentario;
        $orden->save();

        return response()->json([
            'success' => true,
            'message' => 'Calidad actualizada correctamente',
            'calidad' => $orden->calidad,
            'calidadcomentario' => $orden->calidadcomentario,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}
```

---

## Task 3: Agregar Ruta

**Files:** `routes/modules/urdido.php`

- [ ] Step 1: Agregar después de guardar-observaciones (línea ~61):
```php
Route::post('/programar-urdido/actualizar-calidad', [ProgramarUrdidoController::class, 'actualizarCalidad'])->name('programar.urdido.actualizar.calidad');
```

---

## Task 4: Actualizar getOrdenes

**Files:** `app/Http/Controllers/Urdido/ProgramaUrdido/ProgramarUrdidoController.php`

- [ ] Step 1: Agregar 'calidad' y 'calidadcomentario' al select (líneas ~176 y ~199)
- [ ] Step 2: Agregar al array de respuesta (línea ~295):
```php
'calidad' => $orden->calidad ?? null,
'calidadcomentario' => $orden->calidadcomentario ?? null,
```

---

## Task 5: Columna Calidad en tabla

**Files:** `resources/views/modulos/urdido/programar-urdido.blade.php`

- [ ] Step 1: Agregar th "Calidad" después de Observaciones (línea ~68)
- [ ] Step 2: Cambiar colspan="9" a colspan="10" (líneas ~73 y ~286)

---

## Task 6: Celda Calidad en filas JS

**Files:** `resources/views/modulos/urdido/programar-urdido.blade.php`

- [ ] Step 1: En renderTable (~línea 351), después del td de observaciones, agregar:
```javascript
const calidadCell = orden.calidad
    ? (orden.calidad === 'A' ? '<span class="text-green-600 font-bold text-lg" title="'+(orden.calidadcomentario||'')+'">✓</span>'
        : orden.calidad === 'R' ? '<span class="text-red-600 font-bold text-lg" title="'+(orden.calidadcomentario||'')+'">✗</span>'
        : '<span class="text-yellow-500 font-bold text-lg" title="'+(orden.calidadcomentario||'')+'">!</span>')
    : '<span class="text-gray-300 text-lg">—</span>';
```
- [ ] Step 2: Agregar TD en el return del TR:
```html
<td class="${baseTd} text-center">${calidadCell}</td>
```

---

## Task 7: Botón "Calidad"

**Files:** `resources/views/modulos/urdido/programar-urdido.blade.php`

- [ ] Step 1: Agregar en @section('navbar-right') después del botón Reimpresion (~línea 37):
```blade
<x-navbar.button-report onclick="abrirModalCalidad()" title="Evaluación de Calidad" icon="fa-clipboard-check" text="Calidad" bg="bg-amber-500" iconColor="text-white" hoverBg="hover:bg-amber-600" module="Programa Urdido" />
```

---

## Task 8: Modal de Calidad

**Files:** `resources/views/modulos/urdido/programar-urdido.blade.php`

- [ ] Step 1: Agregar modal HTML después del modalEditarPrioridad (~línea 137) con:
  - Header con título "Evaluación de Calidad" y botón cerrar
  - Body con: Folio seleccionado, 3 radio buttons (✓ verde A, ✗ rojo R, ! amarillo O), textarea observaciones
  - Footer con botones Cancelar y Guardar

---

## Task 9: JavaScript funciones

**Files:** `resources/views/modulos/urdido/programar-urdido.blade.php`

- [ ] Step 1: Agregar al final del bloque @javascript:
```javascript
let ordenCalidadId = null;

function abrirModalCalidad() {
    const orden = state.ordenSeleccionada;
    if (!orden) { alert('Seleccione un registro'); return; }
    ordenCalidadId = orden.id;
    document.getElementById('modalCalidadFolio').textContent = orden.folio || '';
    document.querySelectorAll('input[name="calidad"]').forEach(r => { r.checked = r.value === orden.calidad; });
    document.getElementById('calidadcomentario').value = orden.calidadcomentario || '';
    document.getElementById('modalCalidad').style.display = 'flex';
}

function cerrarModalCalidad() {
    document.getElementById('modalCalidad').style.display = 'none';
    ordenCalidadId = null;
}

function guardarCalidad() {
    const calidad = document.querySelector('input[name="calidad"]:checked')?.value;
    const calidadcomentario = document.getElementById('calidadcomentario').value;
    if (!calidad) { alert('Seleccione un estado de calidad'); return; }
    fetch('/urdido/programar-urdido/actualizar-calidad', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ id: ordenCalidadId, calidad, calidadcomentario })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            for (let mccoy = 1; mccoy <= 4; mccoy++) {
                const ordenIdx = (state.ordenes[mccoy] || []).findIndex(o => o.id === ordenCalidadId);
                if (ordenIdx !== -1) {
                    state.ordenes[mccoy][ordenIdx].calidad = data.calidad;
                    state.ordenes[mccoy][ordenIdx].calidadcomentario = data.calidadcomentario;
                    break;
                }
            }
            cerrarModalCalidad();
            renderAllTables();
        } else { alert('Error: ' + (data.error || 'Error')); }
    })
    .catch(err => { alert('Error de conexión: ' + err.message); });
}
```

---

## Task 10: Verificación

- [ ] Probar columna Calidad en tabla
- [ ] Seleccionar registro y clic en botón Calidad
- [ ] Modal abre con Folio correcto
- [ ] Seleccionar opción y guardar
- [ ] Indicador aparece en la tabla
- [ ] Hover muestra observaciones
- [ ] Recargar página verifica persistencia
