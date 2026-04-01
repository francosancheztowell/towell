# Plan de Refactorización: Deuda Técnica ProgramaTejido

> **Para agentes:** REQUIRED: Usar `superpowers:subagent-driven-development` para implementar. Cada tarea usa checkbox (`- [ ]`).

**Goal:** Reducir deuda técnica del módulo programa-tejido mediante refactorización conservadora de código duplicado, try-catch, y extracción JS.

**Architecture:** 
- Centralizar `calcularFormulasEficiencia` en `TejidoHelpers` como única fuente de verdad
- Eliminar wrappers redundantes, mantener solo DateHelpers que tiene lógica distinta para cascade
- Mejorar try-catch genéricos en puntos críticos
- Verificar que PTStore existe y se usa correctamente

**Tech Stack:** PHP 8.x, Laravel 12, JavaScript ES6+, Pest/PHPUnit

---

## Tareas por Prioridad

### PRIORIDAD 5: OrdCompartidaHelper — ✅ YA EXISTE (verificar uso)

**Archivos a verificar:**
- `app/Http/Controllers/Planeacion/ProgramaTejido/helper/OrdCompartidaHelper.php`
- `tests/Unit/OrdCompartidaHelperTest.php`

**Estado:** `OrdCompartidaHelper` ya existe y centraliza `obtenerNuevoOrdCompartidaDisponible()`. 
DuplicarTejido, VincularTejido y DividirTejido ya lo usan.

**Tarea:**
- [ ] **Verificar que no existe código duplicado residual**
  - Run: `grep -rn "max.*OrdCompartida" app/Http/Controllers/Planeacion/ProgramaTejido/`
  - Expected: Solo en OrdCompartidaHelper

---

### PRIORIDAD 6: Try-Catch Genéricos — Mejorar en puntos críticos

**Criterio conservador:** Solo mejorar donde `catch (\Throwable)` sin distinción puede ocultar errores de aplicación (no errores de parseo esperados).

**Archivos a modificar:**

#### 6.1: BalancearTejido.php

**Files:**
- Modify: `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/BalancearTejido.php:711-725`

**Código actual:**
```php
private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
{
    try {
        $m = TejidoHelpers::obtenerModeloParams($programa);
        return TejidoHelpers::calcularFormulasEficiencia($programa, $m, true, true, false);
    } catch (\Throwable $e) {
        Log::warning('BalancearTejido: Error al calcular formulas', [
            'error' => $e->getMessage(),
            'programa_id' => $programa->Id ?? null,
        ]);
    }
    return [];
}
```

**Refactorizar a:**
```php
private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
{
    try {
        $m = TejidoHelpers::obtenerModeloParams($programa);
        return TejidoHelpers::calcularFormulasEficiencia($programa, $m, true, true, false);
    } catch (\InvalidArgumentException $e) {
        // Datos inválidos en parámetros, no recuperable sin intervención
        Log::error('BalancearTejido: Parámetros inválidos para fórmulas', [
            'error' => $e->getMessage(),
            'programa_id' => $programa->Id ?? null,
        ]);
        return [];
    } catch (\Throwable $e) {
        // Error inesperado en cálculo, log y continue sin fórmulas
        Log::warning('BalancearTejido: Error al calcular fórmulas', [
            'error' => $e->getMessage(),
            'programa_id' => $programa->Id ?? null,
        ]);
        return [];
    }
}
```

**Test a crear:**
```php
// tests/Unit/BalancearTejidoCalcularFormulasTest.php
public function test_calculate_formulas_efficiency_returns_empty_on_invalid_params(): void
{
    $programaMock = Mockery::mock(ReqProgramaTejido::class);
    $programaMock->shouldReceive('getAttribute')->andReturn(null);
    $programaMock->Id = 999;
    
    // Invalid tamano clave should cause exception
    $result = BalancearTejido::calcularFormulasEfficiency($programaMock);
    
    $this->assertIsArray($result);
    $this->assertEmpty($result); // Graceful degradation
}
```

---

#### 6.2: TejidoHelpers.php (puntos de Carbon::parse)

**Files:**
- Modify: `app/Http/Controllers/Planeacion/ProgramaTejido/helper/TejidoHelpers.php:382-390`

**Código actual:**
```php
try {
    $fechaFinal = Carbon::parse($programa->FechaFinal);
    $entregaCteCalculada = $fechaFinal->copy()->addDays($diasEntrega);
    // ...
} catch (\Throwable $e) {
    // Si hay error al parsear, no establecer EntregaCte
}
```

**Refactorizar a:**
```php
try {
    $fechaFinal = Carbon::parse($programa->FechaFinal);
    $entregaCteCalculada = $fechaFinal->copy()->addDays($diasEntrega);
    // ...
} catch (\Carbon\Exceptions\InvalidFormatException $e) {
    // FechaFinal inválida o null, no se puede calcular entrega
} catch (\Throwable $e) {
    Log::warning('TejidoHelpers: Error al calcular entrega cliente', [
        'error' => $e->getMessage(),
        'programa_id' => $programa->Id ?? null,
    ]);
}
```

---

#### 6.3: DateHelpers.php (múltiples puntos)

**Files:**
- Modify: `app/Http/Controllers/Planeacion/ProgramaTejido/helper/DateHelpers.php:23,125,265,333,485,494,514`

**Mejora:** Para los try-catch en parseos de fecha, usar `\Carbon\Exceptions\InvalidFormatException` explícitamente.

```php
try {
    // ...
} catch (\Carbon\Exceptions\InvalidFormatException $e) {
    // Formato de fecha inválido, continuar sin ese cálculo
} catch (\Throwable $e) {
    Log::warning('DateHelpers: Error', ['msg' => $e->getMessage()]);
}
```

---

### PRIORIDAD 8: State Management PTStore — Verificar uso

**Files:**
- `resources/js/programa-tejido/store.js` (71 líneas)
- `resources/views/modulos/programa-tejido/scripts/state.blade.php`

**Estado actual:** PTStore existe con:
- `getAll()`, `get(id)`, `set(id, data)`, `add(data)`, `remove(id)`
- `subscribe(fn)`, `notify()`
- `loadFromServer(data)`

**Tareas:**
- [ ] **Verificar que main.blade.php incluye y usa PTStore**
  - Run: `grep -n "PTStore\|window.PTStore" resources/views/modulos/programa-tejido/scripts/main.blade.php`
  
- [ ] **Verificar que state.blade.php carga PTStore**
  - Run: `cat resources/views/modulos/programa-tejido/scripts/state.blade.php`

- [ ] **Si no se usa, documentar en un comentario por qué está ahí**

- [ ] **Commit:** `refactor(programa-tejido): verificar PTStore estado actual`

---

### PRIORIDAD 1: Extraer JS de Blade

**Estado:** `main.blade.php` (3879 líneas) contiene JS inline. Ya existen utilitários externos.

**Files:**
- `resources/views/modulos/programa-tejido/scripts/main.blade.php`
- `public/js/programa-tejido/utils.js`
- `resources/js/programa-tejido/store.js`

**Análisis:**
El JS en main.blade.php incluye:
1. Fetch patch para path rewriting
2. Modal duplicar/dividir include
3. State store initialization
4. Filters, columns, selection, inline-edit scripts
5. PT object con loader, rowCache, helpers
6. Drag & drop handlers
7. Tabla update functions
8. Event handlers (drag, selection, context menu)

**Tareas conservador (solo mover, no reescribir):**

- [ ] **1.1: Identificar funciones independencees que pueden extraerse**
  
  Funciones candidatas para extraer a `public/js/programa-tejido/`:
  - `throttle(fn, delay)` → `utils.js` (ya existe como `debounce`)
  - `rowMeta(row)` → `utils.js`
  - `normalizeTelarValue(value)` → `utils.js`
  - `isSameTelar(a, b)` → `utils.js`
  - Formatters `ddFormatDateTime`, `ddFormatDateOnly`, `ddFormatNumber` → `formatters.js` (crear)
  - `ddFormatCell` → `formatters.js`
  - `ddSetCellValue` → `formatters.js`

- [ ] **1.2: Crear formatters.js**
  
  Create: `public/js/programa-tejido/formatters.js`
  ```javascript
  /**
   * Formateadores para celdas de la tabla programa-tejido
   */
  window.PTFormatters = {
      formatDateTime(raw) { /* ... */ },
      formatDateOnly(raw) { /* ... */ },
      formatNumber(raw) { /* ... */ },
      formatCell(column, raw) { /* ... */ },
      setCellValue(cell, display, rawValue) { /* ... */ }
  };
  ```

- [ ] **1.3: Extraer utilities a utils.js**

  Add a `public/js/programa-tejido/utils.js`:
  - `throttle(fn, delay)` (ya existe como `debounce` - renombrar o crear alias)
  - `rowMeta(row)` 
  - `normalizeTelarValue(value)`
  - `isSameTelar(a, b)`

- [ ] **1.4: Incluir archivos JS en main.blade.php**

  En `<head>` o antes del include de main:
  ```blade
  <script src="{{ asset('js/programa-tejido/utils.js') }}"></script>
  <script src="{{ asset('js/programa-tejido/formatters.js') }}"></script>
  ```

- [ ] **1.5: Reemplazar definiciones locales con referencias a archivos**

  En main.blade.php, después de incluir los archivos:
  ```javascript
  // Remover definiciones duplicadas si ya existen en archivos externos
  // Usar window.PTFormatters.formatCell en lugar de ddFormatCell local
  ```

**Test:**
- [ ] **Verificar que las funciones extraídas funcionan igual**
  - Run: `npm run build` o verificar en dev que no hay errores de consola

- [ ] **Commit:** `refactor(programa-tejido): extraer utilidades JS a archivos externos`

---

### PRIORIDAD 3: Centralizar calcularFormulasEficiencia

**Análisis actual:**
| Ubicación | Implementación | Parámetros |
|-----------|---------------|------------|
| `TejidoHelpers::calcularFormulasEficiencia` | CANÓNICA | (programa, modeloParams, includeEntregaCte, includePTvsCte, fallbackEntregaCteFromProgram) |
| `DuplicarTejido::calcularFormulasEficiencia` | Wrapper | (programa, m, true, true, true) |
| `BalancearTejido::calcularFormulasEficiencia` | Wrapper | (programa, m, true, true, false) |
| `DividirTejido::calcularFormulasEficiencia` | Wrapper con callback | (programa, modeloParams, true, true, true) |
| `DateHelpers::calcularFormulasEficiencia` | **IMPLEMENTACIÓN DISTINTA** | (programa, metricasBase) |
| `UpdateTejido::calcularFormulasEficiencia` | Delega a DuplicarTejido | - |

**Problema:** `DateHelpers` tiene su propia implementación que NO incluye:
- `EntregaCte`, `EntregaPT`, `EntregaProduc`, `PTvsCte`
- Lógica de `fallbackEntregaCteFromProgram`

**Decisión conservador:** `DateHelpers` se usa para cascade de fechas donde estas fórmulas adicionales NO son necesarias (es para recalcular fechas, no para display). La lógica es distinta por diseño.

**Mejora:** Los wrappers de DuplicarTejido, BalancearTejido, DividirTejido son aceptables si usan params diferentes intencionalmente.

**Tarea de documentación:**

- [ ] **3.1: Documentar por qué los parámetros difieren**

  Crear comentario en cada wrapper:
  ```php
  /**
   * Calculate efficiency formulas for DuplicarTejido operations.
   * Uses fallbackEntregaCte=true because duplicados inherit client delivery dates.
   * 
   * @param ReqProgramaTejido $programa
   * @return array
   */
  private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
  ```

- [ ] **3.2: Verificar que UpdateTejido delega correctamente**

  Run: `grep -A5 "calcularFormulasEficiencia" app/Http/Controllers/Planeacion/ProgramaTejido/funciones/UpdateTejido.php`

- [ ] **3.3: Commit:** `docs(programa-tejido): documentar diferencias en parámetros de calcularFormulasEficiencia`

---

### PRIORIDAD 4: Bulk Queries en store()

**Files:**
- Modify: `app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php:206-298`

**Código actual (problema):**
```php
foreach ($request->input('telares', []) as $fila) {
    // Query 1: marcar Ultimo anterior
    ReqProgramaTejido::where('SalonTejidoId', $salon)
        ->where('NoTelarId', $noTelarId)
        ->where('Ultimo', '1')
        ->update(['Ultimo' => 0]); // N queries si N telares
    
    // Query 2: crear nuevo registro
    $nuevo->save(); // N queries
}
```

**Refactorizar a:**
```php
// Paso 1: Recolectar todos los telares únicos
$telaresData = collect($request->input('telares', []));
$telaresUnicos = $telaresData->pluck('no_telar_id')->unique();

// Paso 2: Bulk update - UNA query
ReqProgramaTejido::where('SalonTejidoId', $salon)
    ->whereIn('NoTelarId', $telaresUnicos->values()->all())
    ->where('Ultimo', '1')
    ->update(['Ultimo' => 0]);

// Paso 3: Bulk insert - UNA query (si el modelo lo soporta)
// Por ahora mantener loop individual ya que cada registro es diferente
// pero-envolver en transaction
```

**Tareas:**

- [ ] **4.1: Implementar bulk update para Ultimo**
  
  Modify: `ProgramaTejidoController.php:213-218`
  
  ```php
  // Recolectar telares únicos del request
  $telaresIds = collect($request->input('telares', []))
      ->pluck('no_telar_id')
      ->unique()
      ->values()
      ->all();
  
  // Bulk update - UNA query
  if (!empty($telaresIds)) {
      ReqProgramaTejido::where('SalonTejidoId', $salon)
          ->whereIn('NoTelarId', $telaresIds)
          ->where('Ultimo', '1')
          ->update(['Ultimo' => 0]);
  }
  ```

- [ ] **4.2: Crear test para store con múltiples telares**

  Create: `tests/Feature/ProgramaTejidoStoreBulkTest.php`
  ```php
  public function test_store_creates_multiple_registers_in_single_transaction(): void
  {
      $data = [
          'salon_tejido_id' => 'JACQUARD',
          'telares' => [
              ['no_telar_id' => 'T01', 'cantidad' => 100],
              ['no_telar_id' => 'T02', 'cantidad' => 200],
              ['no_telar_id' => 'T03', 'cantidad' => 300],
          ],
          // ... otros campos
      ];
      
      $response = $this->postJson(route('programa-tejido.store'), $data);
      
      $response->assertStatus(201);
      $this->assertDatabaseCount('ReqProgramaTejido', 3);
      
      // Verificar que cada telar tiene Ultimo=1
      foreach (['T01', 'T02', 'T03'] as $telar) {
          $this->assertDatabaseHas('ReqProgramaTejido', [
              'NoTelarId' => $telar,
              'Ultimo' => '1',
          ]);
      }
  }
  ```

- [ ] **4.3: Commit:** `perf(programa-tejido): bulk update para marcar ultimo en store`

---

## Resumen de Tasks

| # | Task | Files | Type |
|---|------|-------|------|
| 5 | Verificar OrdCompartidaHelper (ya existe) | OrdCompartidaHelper.php | verification |
| 6.1 | Mejorar try-catch BalancearTejido | BalancearTejido.php | refactor |
| 6.2 | Mejorar try-catch TejidoHelpers | TejidoHelpers.php | refactor |
| 6.3 | Mejorar try-catch DateHelpers | DateHelpers.php | refactor |
| 8.1 | Verificar PTStore uso | main.blade.php, state.blade.php | verification |
| 1.1 | Identificar funciones JS extraíbles | main.blade.php | analysis |
| 1.2 | Crear formatters.js | public/js/programa-tejido/formatters.js | create |
| 1.3 | Extraer utilities a utils.js | public/js/programa-tejido/utils.js | refactor |
| 1.4 | Incluir JS externos en Blade | main.blade.php | refactor |
| 3.1 | Documentar diferencias calcularFormulasEficiencia | DuplicarTejido, BalancearTejido, DividirTejido | docs |
| 4.1 | Bulk update Ultimo en store | ProgramaTejidoController.php | perf |
| 4.2 | Crear test store bulk | tests/Feature/ProgramaTejidoStoreBulkTest.php | test |

---

## Siguiente Paso

Ejecutar con: `/gsd-execute-phase` o crear worktree con `/gsd-use-worktree feature/programa-tejido-5-mejoras`
