# Programa Tejido - Plan de Mejora Cauteloso

> **For agentic workers:** REQUIRED: Usar superpowers:subagent-driven-development o superpowers:executing-plans para implementar este plan.

**Goal:** Mejorar rendimiento, diseño, usabilidad, consistencia, buenas prácticas y legibilidad del módulo req-programa-tejido SIN romper funcionalidad existente.

**Architecture:** Plan incremental en 5 fases, cada una verificable con tests antes de avanzar. Cambios backwards-compatible. Prioridad: tests > legibilidad > rendimiento > consistencia > usabilidad.

**Tech Stack:** Laravel 12, Blade, JavaScript vanilla, Tailwind CSS, PHPUnit

---

## ESTRATEGIA: "Nunca romper lo que funciona"

### Reglas de oro:
1. **Tests PRIMERO** - Siempre escribir/modificar tests antes de tocar código
2. **Un cambio a la vez** - No hacer 10 cambios simultáneos
3. **Verify en cada paso** - Ejecutar tests después de cada cambio
4. **Si algo falla, revert inmediato** - No dejar código roto
5. **Commit atómico** - Cada mejora = su propio commit

---

## FASE 0: Baseline - Verificar que todo funciona

### Objetivo: Crear punto de partida verificado

**Criterio de éxito:** `php artisan test --filter=ProgramaTejido` pasa al 100%

---

### Tarea 0.1: Verificar tests existentes

**Files:** Tests existentes

- [ ] **Step 1: Ejecutar tests de ProgramaTejido**

```bash
cd /c/xampp/htdocs/Towell && php artisan test --filter=ProgramaTejido 2>&1
```

Expected: Todos los tests pasan (6 test files)

- [ ] **Step 2: Si fallan, INVESTIGAR antes de continuar**

Si algún test falla, no continuar hasta entender por qué. El baseline debe estar verde.

---

### Tarea 0.2: Crear test de smoke para la vista principal

**Files:**
- Create: `tests/Feature/ProgramaTejidoSmokeTest.php`

- [ ] **Step 1: Crear test de smoke**

```php
<?php
namespace Tests\Feature;

use Tests\TestCase;

class ProgramaTejidoSmokeTest extends TestCase
{
    /**
     * Verifica que la ruta principal de programa-tejido responde correctamente.
     * Este test existe para garantizar que cambios en vistas/JS no rompan el flujo básico.
     */
    public function test_programa_tejido_index_route_returns_view(): void
    {
        $response = $this->get(route('catalogos.req-programa-tejido'));
        
        $response->assertStatus(200);
    }
    
    public function test_balancear_route_returns_view(): void
    {
        $response = $this->get(route('programa-tejido.balancear'));
        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Ejecutar y verificar que pasa**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
```

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/ProgramaTejidoSmokeTest.php
git commit -m "test: add smoke tests for programa-tejido routes"
```

---

## FASE 1: Legibilidad y Buenas Prácticas (menor riesgo)

### Objetivo: Código más legible sin cambiar comportamiento

---

### Tarea 1.1: Extraer CSS inline a archivo dedicado

**Files:**
- Modify: `resources/views/modulos/programa-tejido/req-programa-tejido.blade.php` (remover `<style>`)
- Create: `resources/css/programa-tejido/main.css`

- [ ] **Step 1: Identificar y extraer los estilos de `<style>` en req-programa-tejido.blade.php**

Del archivo `req-programa-tejido.blade.php` líneas 312-644, extraer a `resources/css/programa-tejido/main.css`:
- Estilos de tabla (.pt-page, .pt-table-wrapper, etc.)
- Estilos de columnas fijadas (pinned-column)
- Estilos de filas seleccionadas
- Estilos de edición inline
- Estilos de context menu
- Estilos de reprogramar

**NO cambiar selectores, solo mover de ubicación.**

- [ ] **Step 2: En req-programa-tejido.blade.php, reemplazar `<style>` por:**

```blade
@push('styles')
<link rel="stylesheet" href="{{ asset('css/programa-tejido/main.css') }}">
@endpush
```

- [ ] **Step 3: Ejecutar tests**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
```

Expected: PASS (el CSS es idéntico, solo mudoubicación)

- [ ] **Step 4: Verificar visual en navegador que nada se movió**

- [ ] **Step 5: Commit**

```bash
git add resources/views/modulos/programa-tejido/req-programa-tejido.blade.php resources/css/programa-tejido/main.css
git commit -m "refactor(programa-tejido): extract inline styles to dedicated CSS file"
```

---

### Tarea 1.2: Agregar JSDoc a funciones principales de main.blade.php

**Files:**
- Modify: `resources/views/modulos/programa-tejido/scripts/main.blade.php`

- [ ] **Step 1: Agregar JSDoc a las funciones documentadas existentes**

Ejemplo del patrón a seguir:

```javascript
/**
 * Obtiene metadatos de una fila de la tabla.
 * @param {HTMLTableRowElement} row - Fila de la tabla
 * @returns {{telar: string, salon: string, cambioHilo: string, enProceso: boolean, posicion: number|null}}
 */
function rowMeta(row) {
    // ... código existente sin cambios
}

/**
 * Refresca el cache de filas y retorna array de todas las filas.
 * @returns {HTMLTableRowElement[]}
 */
function refreshAllRows() {
    // ... código existente sin cambios
}
```

**Funciones a documentar:**
- `rowMeta()`
- `refreshAllRows()`
- `ddReorderRows()`
- `ddFormatNumber()`
- `ddFormatDateTime()`
- `ddFormatDateOnly()`
- `applyColumnFilterManual()`
- `openFilterModal()`

- [ ] **Step 2: Ejecutar tests**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
```

Expected: PASS (solo comentarios, sin cambio de lógica)

- [ ] **Step 3: Commit**

```bash
git add resources/views/modulos/programa-tejido/scripts/main.blade.php
git commit -m "docs(programa-tejido): add JSDoc to main script functions"
```

---

### Tarea 1.3: Deduplicar lógica de formateo

**Files:**
- Modify: `resources/views/modulos/programa-tejido/req-programa-tejido.blade.php` (líneas 57-141)
- Create: `resources/js/programa-tejido/formatters.js`

- [ ] **Step 1: Identificar funciones de formateo duplicadas**

En `req-programa-tejido.blade.php` hay `$formatValue` (PHP) y en `main.blade.php` hay `ddFormatCell` (JS). Ambos formatean:
- Fechas
- Números
- Checkboxes (EnProceso, Ultimo)
- EficienciaSTD

**Acción: Crear un módulo JS `formatters.js` con funciones reutilizables**

```javascript
// resources/js/programa-tejido/formatters.js

/**
 * @param {number} n
 * @returns {string}
 */
export function formatEficiencia(n) {
    if (!Number.isFinite(n)) return String(n ?? '');
    return `${Math.round(n * 100)}%`;
}

/**
 * @param {string|Date} raw
 * @returns {string}
 */
export function formatDate(raw) {
    if (!raw) return '';
    try {
        const dt = raw instanceof Date ? raw : new Date(raw);
        if (dt.getFullYear() <= 1970) return '';
        return dt.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch {
        return '';
    }
}
```

- [ ] **Step 2: Ejecutar tests**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
```

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add resources/js/programa-tejido/formatters.js
git commit -m "refactor(programa-tejido): extract formatters to dedicated JS module"
```

---

## FASE 2: Rendimiento

### Objetivo: Mejorar velocidad sin cambiar UX

---

### Tarea 2.1: Implementar throttling en refreshAllRows

**Files:**
- Modify: `resources/views/modulos/programa-tejido/scripts/main.blade.php`

- [ ] **Step 1: Crear wrapper con throttle (úsase solo donde se llama repetidamente)**

```javascript
// Agregar al inicio del script en main.blade.php
const throttle = (fn, delay) => {
    let lastCall = 0;
    return function(...args) {
        const now = Date.now();
        if (now - lastCall >= delay) {
            lastCall = now;
            return fn.apply(this, args);
        }
    };
};
```

- [ ] **Step 2: Envolver refreshAllRows en llamadas que lo invocan múltiples veces**

Buscar en el código:
- `refreshAllRows()` se llama en `updateTableAfterDragDrop`
- `clearRowCache()` también se llama mucho

**No cambiar la función `refreshAllRows` directamente**, solo las llamadas repetitivas.

- [ ] **Step 3: Tests**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
```

- [ ] **Step 4: Commit**

```bash
git commit -m "perf(programa-tejido): add throttling to expensive DOM operations"
```

---

### Tarea 2.2: Limitar crecimiento de caches en memoria

**Files:**
- Modify: `resources/views/modulos/programa-tejido/modal/duplicar-dividir.blade.php` (líneas 26-45)

- [ ] **Step 1: Agregar evict policy a caches existentes**

```javascript
// Reemplazar cache Map simple por uno con límite
class LimitedCache {
    constructor(maxSize = 100) {
        this.cache = new Map();
        this.maxSize = maxSize;
    }
    
    set(key, value) {
        if (this.cache.size >= this.maxSize) {
            // Eliminar el más antiguo (primero en orden de inserción)
            const firstKey = this.cache.keys().next().value;
            this.cache.delete(firstKey);
        }
        this.cache.set(key, value);
    }
    
    get(key) { return this.cache.get(key); }
    has(key) { return this.cache.has(key); }
}

const detallesBalanceoCache = new LimitedCache(50);
const descripcionFlogCache = new LimitedCache(100);
```

- [ ] **Step 2: Tests**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
```

- [ ] **Step 3: Commit**

```bash
git commit -m "perf(programa-tejido): limit cache growth with eviction policy"
```

---

### Tarea 2.3: Eliminar setTimeout dispersos

**Files:**
- Modify: `resources/views/modulos/programa-tejido/scripts/main.blade.php`
- Modify: `resources/views/modulos/programa-tejido/modal/duplicar-dividir.blade.php`

- [ ] **Step 1: Encontrar todos los setTimeout**

```bash
grep -n "setTimeout" /c/xampp/htdocs/Towell/resources/views/modulos/programa-tejido/scripts/main.blade.php
grep -n "setTimeout" /c/xampp/htdocs/Towell/resources/views/modulos/programa-tejido/modal/duplicar-dividir.blade.php
```

- [ ] **Step 2: Analizar cada uno**

Los setTimeout con delays pequeños (0-100ms) son típicamente "hack" para ejecutar después del render. Reemplazarlos por `requestAnimationFrame` o mejor aún, por callbacks de eventos.

Ejemplo de problema:
```javascript
// MAL: setTimeout(fn, 100) para asegurar que el DOM está listo
// BIEN: usar MutationObserver o simplemente no usar timeout
```

**Nota: Solo modificar si el delay es < 200ms y hay alternativa clara. Si no, dejar como está para no arriesgar.**

- [ ] **Step 3: Tests y commit**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
git commit -m "perf(programa-tejido): replace setTimeout with requestAnimationFrame where possible"
```

---

## FASE 2B: Estado Local / Optimistic UI Updates ⭐

### Objetivo: Cambios inmediatos en UI sin recargas

**Problema actual:** Cuando duplicas, eliminas, editas, balanceas o drag & drop, la página a veces recarga o parpadea. El usuario ve un "loading" o espera.

**Solución:** Patrón "Optimistic UI" - el cambio aparece **inmediatamente** en la tabla, y luego se sincroniza con el servidor en background. Si el servidor falla, se revierte.

---

### Tarea 2B.1: Crear store centralizado de estado

**Files:**
- Create: `resources/js/programa-tejido/store.js`

- [ ] **Step 1: Crear el store básico**

```javascript
// resources/js/programa-tejido/store.js

/**
 * Store centralizado para el estado de la tabla de programa-tejido.
 * Permite actualizaciones optimistas (cambio inmediato en UI, sync con servidor en background).
 */
class PTStore {
    constructor() {
        this.registros = new Map(); // id -> registro data
        this.listeners = new Set();
    }

    /** Obtener todos los registros como array */
    getAll() {
        return Array.from(this.registros.values());
    }

    /** Obtener un registro por ID */
    get(id) {
        return this.registros.get(String(id));
    }

    /** Agregar o actualizar un registro */
    set(id, data) {
        this.registros.set(String(id), { ...this.registros.get(String(id)), ...data });
        this.notify();
    }

    /** Agregar nuevo registro (duplicar, crear repaso) */
    add(data) {
        const id = data.Id || data.id;
        if (id) {
            this.registros.set(String(id), data);
            this.notify();
            return id;
        }
        return null;
    }

    /** Eliminar registro (eliminar, eliminar en proceso) */
    remove(id) {
        this.registros.delete(String(id));
        this.notify();
    }

    /** Suscribirse a cambios */
    subscribe(fn) {
        this.listeners.add(fn);
        return () => this.listeners.delete(fn);
    }

    /** Notificar a todos los listeners */
    notify() {
        this.listeners.forEach(fn => fn(this.getAll()));
    }

    /** Cargar datos iniciales desde el servidor */
    loadFromServer(data) {
        this.registros.clear();
        data.forEach(r => {
            const id = r.Id || r.id;
            if (id) this.registros.set(String(id), r);
        });
        this.notify();
    }
}

// Instancia global única
window.PTStore = new PTStore();
```

- [ ] **Step 2: Tests del store**

Crear test file `tests/Unit/PTStoreTest.php` (o JS si se prefiere):

```javascript
// tests/js/PTStore.test.js (si existe sistema de tests JS)
// Por ahora, probar manualmente con consola:
/*
const store = new PTStore();
console.assert(store.getAll().length === 0, 'Store vacío inicial');

// Agregar
store.add({ Id: '1', NoTelarId: '01', TotalPedido: 100 });
console.assert(store.get('1').NoTelarId === '01', 'Registro agregado');

// Actualizar
store.set('1', { TotalPedido: 200 });
console.assert(store.get('1').TotalPedido === 200, 'Registro actualizado');

// Eliminar
store.remove('1');
console.assert(store.get('1') === undefined, 'Registro eliminado');
*/
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/programa-tejido/store.js
git commit -m "feat(programa-tejido): add central state store for optimistic UI"
```

---

### Tarea 2B.2: Hook del store a la tabla (LECTURA)

**Files:**
- Modify: `resources/views/modulos/programa-tejido/scripts/main.blade.php`

- [ ] **Step 1: Modificar `refreshAllRows` para que cargue al store**

Al cargar la página, el store debe poblarse con los datos de la tabla:

```javascript
// Al final del script en main.blade.php, agregar:

/**
 * Inicializa el store con los datos actuales de la tabla.
 * Se llama una vez al cargar la página.
 */
function initStoreFromTable() {
    const rows = document.querySelectorAll('#mainTable tbody .selectable-row');
    const datos = [];
    rows.forEach(row => {
        const id = row.getAttribute('data-id');
        if (!id) return;
        
        // Capturar todos los datos de la fila
        const registro = { Id: id };
        row.querySelectorAll('td').forEach(td => {
            const col = td.getAttribute('data-column');
            if (col) {
                registro[col] = td.dataset.value || td.textContent.trim();
            }
        });
        datos.push(registro);
    });
    
    if (window.PTStore && datos.length > 0) {
        window.PTStore.loadFromServer(datos);
    }
}

// Llamar al inicializar
document.addEventListener('DOMContentLoaded', () => {
    // Esperar a que la tabla esté lista
    setTimeout(initStoreFromTable, 100);
});
```

- [ ] **Step 2: Verificar que no rompe nada**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
```

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(programa-tejido): connect table to central state store"
```

---

### Tarea 2B.3: Actualización optimista para ELIMINAR

**Files:**
- Modify: `resources/views/modulos/programa-tejido/scripts/main.blade.php`

- [ ] **Step 1: Encontrar función `eliminarRegistro` y modificarla**

Buscar en `main.blade.php` la función `eliminarRegistro` y aplicar el patrón:

```javascript
// ANTES (típicamente recargaba o llamaba al servidor sin actualizar UI primero):
async function eliminarRegistro(id) {
    showLoading();
    const response = await fetch(...);
    if (response.ok) {
        // Algo con la respuesta, quizás reload
    }
}

// DESPUÉS (optimista):
async function eliminarRegistro(id) {
    // 1. UI PRIMERO: remover del DOM inmediatamente
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (row) {
        row.style.transition = 'opacity 0.3s';
        row.style.opacity = '0';
        setTimeout(() => row.remove(), 300);
    }
    
    // 2. También del store
    if (window.PTStore) {
        window.PTStore.remove(id);
    }
    
    // 3. Luego sync con servidor en background
    showLoading();
    try {
        const response = await fetch('/planeacion/programa-tejido/' + id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        hideLoading();
        
        if (!response.ok || !data.success) {
            // 4. ROLLBACK si falla: mostrar error y dejar que el usuario decida
            showToast(data.message || 'Error al eliminar', 'error');
            // Nota: No se puede hacer rollback fácil aquí porque ya se quitó del DOM
            // Mejor approach: no hacer rollback sino mostrar toast de error
            // y dejar que el usuario recargue si quiere
        }
    } catch (error) {
        hideLoading();
        showToast('Error de conexión', 'error');
    }
}
```

- [ ] **Step 2: Tests**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
```

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(programa-tejido): optimistic delete - UI updates instantly"
```

---

### Tarea 2B.4: Actualización optimista para DUPLICAR

**Files:**
- Modify: `resources/views/modulos/programa-tejido/modal/duplicar-dividir.blade.php`

- [ ] **Step 1: Modificar después del `fetch` exitoso**

En la función `duplicarTelar`, después de que el servidor responde OK:

```javascript
// En la respuesta exitosa del fetch en duplicarTelar():
if (data.success) {
    // 1. UI: agregar la nueva fila INMEDIATAMENTE si tenemos los datos
    if (data.registro && window.PTStore) {
        window.PTStore.add(data.registro);
        
        // También agregar al DOM directamente si es posible
        // (requiere construir el HTML de la fila - ver tarea 2B.5)
    }
    
    showToast(data.message || 'Telar duplicado correctamente', 'success');
    // Ya NO reload - el cambio ya está en UI
}
```

- [ ] **Step 2: Tests y commit**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
git commit -m "feat(programa-tejido): optimistic duplicate - no page reload"
```

---

### Tarea 2B.5: Helper para agregar filas al DOM directamente

**Files:**
- Modify: `resources/views/modulos/programa-tejido/scripts/main.blade.php`

- [ ] **Step 1: Crear función para insertar fila desde datos**

```javascript
/**
 * Inserta una nueva fila en la tabla desde datos del registro.
 * @param {Object} datos - Datos del registro
 * @param {string} insertPosition - 'append'|'prepend' (default: 'append')
 */
window.insertarFilaDesdeDatos = function(datos, insertPosition = 'append') {
    const tbody = document.querySelector('#mainTable tbody');
    if (!tbody || !datos) return null;
    
    // Construir HTML de la fila
    // (Este código debe adaptarse al formato exacto de las filas existentes)
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-blue-50 cursor-pointer selectable-row';
    tr.setAttribute('data-id', datos.Id || datos.id);
    tr.setAttribute('data-posicion', datos.Posicion || '');
    
    // Aquí iría el código para construir las celdas...
    // Por brevedad, usar innerHTML con los datos disponibles
    
    // Insertar
    if (insertPosition === 'prepend') {
        tbody.prepend(tr);
    } else {
        tbody.appendChild(tr);
    }
    
    // Animación de entrada
    tr.style.opacity = '0';
    tr.style.transform = 'translateY(-10px)';
    requestAnimationFrame(() => {
        tr.style.transition = 'opacity 0.3s, transform 0.3s';
        tr.style.opacity = '1';
        tr.style.transform = 'translateY(0)';
    });
    
    return tr;
};
```

- [ ] **Step 2: Tests y commit**

```bash
git commit -m "feat(programa-tejido): add helper to insert rows directly into DOM"
```

---

### Tarea 2B.6: Actualización optimista para DRAG & DROP

**Files:**
- Modify: `resources/views/modulos/programa-tejido/scripts/main.blade.php`

- [ ] **Step 1: Modificar `updateTableAfterDragDrop`**

La función `updateTableAfterDragDrop` ya hace actualizaciones del DOM, pero podemos optimizarla:

```javascript
// La función actualiza el DOM directamente, que es correcto.
// Lo que podemos hacer es también actualizar el store
window.updateTableAfterDragDrop = function(detalles, registroId, updates = {}) {
    // ... código existente ...
    
    // AGREGAR: actualizar store
    if (window.PTStore && updates) {
        Object.entries(updates).forEach(([id, data]) => {
            window.PTStore.set(id, data);
        });
    }
    
    // ... resto del código existente ...
};
```

- [ ] **Step 2: Tests y commit**

```bash
git commit -m "feat(programa-tejido): sync store on drag-drop operations"
```

---

### Tarea 2B.7: Actualización optimista para EDITAR (inline edit)

**Files:**
- Modify: `resources/views/modulos/programa-tejido/scripts/inline-edit.blade.php`

- [ ] **Step 1: Después de guardar edición inline, actualizar store**

```javascript
// En el código de inline-edit, después de que el servidor responde OK:
if (data.success && window.PTStore && data.registro) {
    window.PTStore.set(registroId, data.registro);
}
```

- [ ] **Step 2: Tests y commit**

```bash
git commit -m "feat(programa-tejido): optimistic inline edit - no reload on save"
```

---

### Tarea 2B.8: Actualización optimista para BALANCEO

**Files:**
- Modify: `resources/views/modulos/programa-tejido/balancear.blade.php`

- [ ] **Step 1: Después de balancear, actualizar store con los nuevos datos**

El balanceo es más complejo porque afecta múltiples registros. Después de la operación:

```javascript
// En la respuesta exitosa del balanceo:
if (result.success && window.PTStore) {
    // Los datos del balanceo vienen en result.data.registros o similar
    const registrosActualizados = result.data?.registros || [];
    registrosActualizados.forEach(reg => {
        window.PTStore.set(reg.Id || reg.id, reg);
    });
    
    // También actualizar el DOM si ya existe updateTableAfterDragDrop
    if (typeof window.updateTableAfterDragDrop === 'function') {
        window.updateTableAfterDragDrop(registrosActualizados, null, {});
    }
}
```

- [ ] **Step 2: Tests y commit**

```bash
git commit -m "feat(programa-tejido): optimistic balanceo - instant UI update"
```

---

### Resumen FASE 2B

| Operación | Antes | Después |
|-----------|-------|---------|
| **Eliminar** | Recarga/flash | Fade out + remove inmediato |
| **Duplicar** | Recarga página | Fila aparece instantáneamente |
| **Editar** | Recarga/flash | Valor cambia sin reload |
| **Drag & Drop** | Reordena + reload | Reordena instantáneamente |
| **Balanceo** | Recarga completa | Tabla se actualiza con datos nuevos |

**Archivos modificados en FASE 2B:**
- `resources/js/programa-tejido/store.js` (nuevo)
- `resources/views/modulos/programa-tejido/scripts/main.blade.php`
- `resources/views/modulos/programa-tejido/scripts/inline-edit.blade.php`
- `resources/views/modulos/programa-tejido/modal/duplicar-dividir.blade.php`
- `resources/views/modulos/programa-tejido/balancear.blade.php`

---

## FASE 3: Consistencia de Modales

### Objetivo: Sistema de modales unificado

---

### Tarea 3.1: Crear componente modal base reutilizable

**Files:**
- Create: `resources/views/components/ui/modal-base.blade.php`

- [ ] **Step 1: Crear modal base**

```blade
{{-- resources/views/components/ui/modal-base.blade.php --}}
{{--
  @param {string} id - ID único del modal
  @param {string} title - Título del modal
  @param {string} size - 'sm'|'md'|'lg'|'xl' (default: 'md')
--}}
@props(['id', 'title', 'size' => 'md'])

<div id="{{ $id }}" 
     x-data="{ open: false }"
     x-show="open"
     x-on:open-modal.window="{{ $id }}=$event.detail"
     class="hidden fixed inset-0 z-50 overflow-hidden"
     style="display: none;">
    <div class="absolute inset-0 overflow-auto">
        <div class="relative mx-auto p-4"
             style="max-width: {{ $size === 'sm' ? '400px' : ($size === 'md' ? '600px' : ($size === 'lg' ? '900px' : '1200px')) }};">
            <div class="bg-white rounded-lg shadow-xl">
                {{ $header ?? '' }}
                <div class="p-4">
                    {{ $slot }}
                </div>
                {{ $footer ?? '' }}
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Refactorizar modal de repaso para usar el base**

El modal de repaso (`repaso.blade.php`) es el más simple. Empezar por él.

- [ ] **Step 3: Tests y commit**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
git commit -m "refactor(programa-tejido): extract base modal component"
```

---

### Tarea 3.2: Unificar estilos de botones de modales

**Files:**
- Create: `resources/css/programa-tejido/modals.css`

- [ ] **Step 1: Definir tokens de modal**

```css
/* Botones primarios de modal */
.modal-btn-primary {
    @apply px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 shadow-md hover:shadow-lg transition-all font-medium;
}

/* Botones secundarios de modal */
.modal-btn-secondary {
    @apply px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400 shadow-md hover:shadow-lg transition-all font-medium;
}
```

- [ ] **Step 2: Aplicar a modales existentes**

Revisar cada modal y usar las clases unificadas.

- [ ] **Step 3: Tests y commit**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
git commit -m "style(programa-tejido): unify modal button styles"
```

---

## FASE 4: Usabilidad

### Objetivo: Mejorar UX sin cambiar funcionalidad

---

### Tarea 4.1: Agregar atajos de teclado básicos

**Files:**
- Modify: `resources/views/modulos/programa-tejido/scripts/main.blade.php`

- [ ] **Step 1: Agregar listener de atajos**

```javascript
// Agregar al final del script en main.blade.php
document.addEventListener('keydown', (e) => {
    // Escape: cerrar modales/abrir menús
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.swal2-container:not(.hidden)');
        if (modals.length) {
            Swal.close();
            return;
        }
    }
    
    // Ctrl+F: abrir filtro de columna (solo si hay una columna seleccionada)
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const selectedColumn = window.PT?.selectedColumn;
        if (selectedColumn) {
            openFilterModal(selectedColumn.index, selectedColumn.field);
        }
    }
});
```

- [ ] **Step 2: Tests y commit**

```bash
php artisan test --filter=ProgramaTejidoSmokeTest
git commit -m "feat(programa-tejido): add keyboard shortcuts"
```

---

### Tarea 4.2: Mejorar feedback visual de selección de filas

**Files:**
- Modify: `resources/css/programa-tejido/main.css`

- [ ] **Step 1: Reforzar estilos de selección**

Los estados actuales `bg-blue-700` y `bg-blue-400` pueden ser confusos. Agregar borde más visible:

```css
.selectable-row.row-selected {
    outline: 3px solid #3b82f6;
    outline-offset: -3px;
}
```

- [ ] **Step 2: Tests visuales**

No hay test automático para esto. Verificar manualmente.

- [ ] **Step 3: Commit**

```bash
git commit -m "style(programa-tejido): improve row selection visual feedback"
```

---

## RESUMEN DE FASES Y CRITERIOS DE ÉXITO

| Fase | Descripción | Tests | Criterio de éxito |
|------|-------------|-------|-------------------|
| 0 | Baseline | `php artisan test` pasa | Verde 100% |
| 1 | Legibilidad | Smoke tests pasan | Código más limpio, misma funcionalidad |
| 2 | Rendimiento clásico | Smoke tests pasan | Menos timeouts, caches limitados |
| **2B** | **Estado Local / Optimistic UI** ⭐ | Smoke tests pasan | **Cambios instantáneos sin reload** |
| 3 | Consistencia | Smoke tests pasan | Modales con estilos unificados |
| 4 | Usabilidad | Smoke tests pasan | Atajos funcionan, mejor feedback |

---

## ORDEN DE EJECUCIÓN RECOMENDADO

```
1. FASE 0 → Ejecutar tests baseline (obligatorio antes de todo)
2. FASE 1 → Legibilidad (menor riesgo, buena práctica)
3. FASE 2 → Rendimiento clásico (cambios pequeños pero impactantes)
4. FASE 2B → Estado Local / Optimistic UI ⭐ (MEJORA PRINCIPAL que pediste)
5. FASE 3 → Consistencia (requiere más cuidado)
6. FASE 4 → Usabilidad (cambios visuales, bajo riesgo)
```

**Nota sobre FASE 2B:** Esta es la fase que responde a tu request principal. Cada operación (duplicar, eliminar, editar, balancear, drag-drop) tendrá actualización instantánea de UI. Esta fase tiene 8 tareas - ejecutar una por una, verificando que no se rompe nada.

**Nota:** Si en cualquier fase los tests fallan, DETENERSE y hacer commit del estado anterior antes de investigar el fallo.

---

## ARCHIVOS A CREAR/MODIFICAR

### Archivos a CREAR:
- `tests/Feature/ProgramaTejidoSmokeTest.php`
- `resources/css/programa-tejido/main.css`
- `resources/css/programa-tejido/modals.css`
- `resources/js/programa-tejido/formatters.js`
- `resources/js/programa-tejido/store.js` ⭐ (NUEVO - estado centralizado)
- `resources/views/components/ui/modal-base.blade.php`

### Archivos a MODIFICAR:
- `resources/views/modulos/programa-tejido/req-programa-tejido.blade.php` (solo extraer CSS)
- `resources/views/modulos/programa-tejido/scripts/main.blade.php` (store hook, drag-drop sync)
- `resources/views/modulos/programa-tejido/scripts/inline-edit.blade.php` (optimistic edit)
- `resources/views/modulos/programa-tejido/scripts/columns.blade.php`
- `resources/views/modulos/programa-tejido/modal/duplicar-dividir.blade.php` (optimistic duplicate)
- `resources/views/modulos/programa-tejido/modal/repaso.blade.php` (usar modal base)
- `resources/views/modulos/programa-tejido/balancear.blade.php` (optimistic balanceo)

### Archivos a NO TOCAR (sin pasar por fase de testing exhaustiva):
- `app/Http/Controllers/Planeacion/ProgramaTejido/*` (lógica backend)
- `routes/modules/planeacion.php` (rutas)
- `app/Models/Planeacion/ReqProgramaTejido.php` (modelo)
