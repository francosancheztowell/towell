# Producción Urdido/Engomado — Validación y Refactor

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Prevenir valores negativos, validar campos requeridos al marcar listo, validar reglas de oficiales, refactorizar blades para legibilidad, y crear tests.

**Architecture:** Validaciones compartidas en `ProduccionTrait.php`, específicas en cada controller. Blades monolíticos se dividen en partials con `@include`. Frontend reforzado con `min="0"` y validación JS pre-envío.

**Tech Stack:** Laravel 12, Blade, jQuery, SQL Server, PHPUnit

---

## Task 1: Validación backend — marcarListo() en ProduccionTrait

**Files:**
- Modify: `app/Traits/ProduccionTrait.php:476-534`

**Step 1: Add validation logic to marcarListo()**

En `ProduccionTrait.php`, modificar el método `marcarListo()` para validar campos requeridos antes de marcar como listo. Agregar después de la línea 498 (después del check de AX):

```php
// --- Validar campos requeridos para marcar como listo ---
if ($request->listo) {
    $camposFaltantes = [];

    if (empty($registro->HoraInicial)) {
        $camposFaltantes[] = 'Hora Inicial';
    }
    if (empty($registro->HoraFinal)) {
        $camposFaltantes[] = 'Hora Final';
    }
    if (empty($registro->NoJulio)) {
        $camposFaltantes[] = 'No. Julio';
    }
    if ($registro->KgBruto === null || (float) $registro->KgBruto < 0) {
        $camposFaltantes[] = 'Kg. Bruto (debe ser >= 0)';
    }
    if ($registro->KgNeto !== null && (float) $registro->KgNeto < 0) {
        $camposFaltantes[] = 'Kg. Neto (no puede ser negativo)';
    }

    if (!empty($camposFaltantes)) {
        return response()->json([
            'success' => false,
            'error' => 'No se puede marcar como listo. Campos faltantes o inválidos: ' . implode(', ', $camposFaltantes),
        ], 422);
    }
}
```

**Step 2: Run tests to verify nothing breaks**

Run: `php artisan test`

**Step 3: Commit**

```bash
git add app/Traits/ProduccionTrait.php
git commit -m "feat(produccion): add required field validation to marcarListo()"
```

---

## Task 2: Validación backend — oficiales en ProduccionTrait

**Files:**
- Modify: `app/Traits/ProduccionTrait.php:103-227` (guardarOficial)

**Step 1: Add sequential official validation**

En `guardarOficial()`, después de la validación de duplicado de CveEmpl (línea 163), agregar validación de secuencialidad y turno duplicado:

```php
// --- Validar secuencialidad: Oficial N requiere Oficial N-1 ---
if ($numeroOficial === 2 && empty($registro->NomEmpl1)) {
    return response()->json([
        'success' => false,
        'error' => 'No se puede agregar Oficial 2 sin tener Oficial 1 registrado.',
    ], 422);
}
if ($numeroOficial === 3 && empty($registro->NomEmpl2)) {
    return response()->json([
        'success' => false,
        'error' => 'No se puede agregar Oficial 3 sin tener Oficial 2 registrado.',
    ], 422);
}

// --- Validar que no se repita el turno dentro del mismo registro ---
$turnoNuevo = $request->input('turno');
if ($turnoNuevo !== null) {
    for ($i = 1; $i <= 3; $i++) {
        if ($i === $numeroOficial) {
            continue;
        }
        $turnoExistente = $registro->{"Turno{$i}"};
        if ($turnoExistente !== null && (int) $turnoExistente === (int) $turnoNuevo && !empty($registro->{"NomEmpl{$i}"})) {
            return response()->json([
                'success' => false,
                'error' => "El Turno {$turnoNuevo} ya está asignado al Oficial {$i}.",
            ], 422);
        }
    }
}
```

**Step 2: Run tests**

Run: `php artisan test`

**Step 3: Commit**

```bash
git add app/Traits/ProduccionTrait.php
git commit -m "feat(produccion): add sequential and turno validation for officials"
```

---

## Task 3: Validación backend — mermas en Engomado

**Files:**
- Modify: `app/Http/Controllers/Engomado/Produccion/ModuloProduccionEngomadoController.php:300-352`

**Step 1: Add min:0 validation for merma fields**

En `actualizarCampoOrden()`, después de obtener el valor numérico (línea 320), agregar:

```php
// --- Validar que mermas no sean negativas ---
if ($valor !== null && $valor < 0) {
    return response()->json([
        'success' => false,
        'error' => 'El valor de ' . str_replace('_', ' ', $request->campo) . ' no puede ser negativo.',
    ], 422);
}
```

**Step 2: Run tests**

Run: `php artisan test`

**Step 3: Commit**

```bash
git add app/Http/Controllers/Engomado/Produccion/ModuloProduccionEngomadoController.php
git commit -m "feat(engomado): add min:0 validation for merma fields"
```

---

## Task 4: Refactor blade Urdido — dividir en partials

**Files:**
- Modify: `resources/views/modulos/urdido/modulo-produccion-urdido.blade.php` (2,745 líneas → index con @includes)
- Create: `resources/views/modulos/urdido/produccion/_header-orden.blade.php`
- Create: `resources/views/modulos/urdido/produccion/_tabla-registros.blade.php`
- Create: `resources/views/modulos/urdido/produccion/_modal-oficial.blade.php`
- Create: `resources/views/modulos/urdido/produccion/_modal-fecha.blade.php`
- Create: `resources/views/modulos/urdido/produccion/_scripts.blade.php`

**Step 1: Read the full blade and identify section boundaries**

Read `modulo-produccion-urdido.blade.php` completely. Identify:
- Header section (orden info, folio, cuenta, metros, etc.)
- Table section (dynamic rows with production data)
- Modal oficial (official management dialog)
- Modal fecha (date picker dialog)
- `<script>` section (all JavaScript)

**Step 2: Create directory and extract partials**

Create `resources/views/modulos/urdido/produccion/` directory.

Extract each section into its own partial file with a descriptive comment header:
```php
{{-- ============================================================
     _header-orden.blade.php
     Muestra la información de la orden: Folio, Cuenta, Metros,
     Proveedor, Destino, Hilo, Tipo Atado, Observaciones.
     Variables requeridas: $orden, $metros, $destino, $hilo,
     $tipoAtado, $observaciones, $loteProveedor, $isKarlMayer
     ============================================================ --}}
```

**Step 3: Replace original blade with @includes**

Replace `modulo-produccion-urdido.blade.php` content with:
```php
{{-- ============================================================
     Producción Urdido — Vista principal
     Divide la UI en partials para legibilidad.
     ============================================================ --}}
@extends('layouts.app')

@section('content')
    @include('modulos.urdido.produccion._header-orden')
    @include('modulos.urdido.produccion._tabla-registros')
    @include('modulos.urdido.produccion._modal-oficial')
    @include('modulos.urdido.produccion._modal-fecha')
@endsection

@push('scripts')
    @include('modulos.urdido.produccion._scripts')
@endpush
```

**Step 4: Add `min="0"` to numeric inputs in _tabla-registros**

In the extracted `_tabla-registros.blade.php`, add `min="0"` to:
- `input[data-field="kg_bruto"]`
- `input[data-field="vueltas"]`
- `input[data-field="diametro"]`

**Step 5: Verify the page renders identically**

Open the page in browser and verify nothing changed visually. Check browser console for JS errors.

**Step 6: Commit**

```bash
git add resources/views/modulos/urdido/
git commit -m "refactor(urdido): split production blade into partials for readability"
```

---

## Task 5: Refactor blade Engomado — dividir en partials

**Files:**
- Modify: `resources/views/modulos/engomado/modulo-produccion-engomado.blade.php` (3,147 líneas → index con @includes)
- Create: `resources/views/modulos/engomado/produccion/_header-orden.blade.php`
- Create: `resources/views/modulos/engomado/produccion/_tabla-registros.blade.php`
- Create: `resources/views/modulos/engomado/produccion/_modal-oficial.blade.php`
- Create: `resources/views/modulos/engomado/produccion/_modal-fecha.blade.php`
- Create: `resources/views/modulos/engomado/produccion/_scripts.blade.php`

**Step 1: Read the full blade and identify section boundaries**

Same approach as Task 4. Read `modulo-produccion-engomado.blade.php` completely.

**Step 2: Create directory and extract partials**

Create `resources/views/modulos/engomado/produccion/` directory.

Each partial gets a descriptive comment header listing required variables.

**Step 3: Replace original blade with @includes**

Same pattern as Urdido but with Engomado-specific variables (mermaGoma, merma, ubicaciones, foliosPrograma, etc.).

**Step 4: Add `min="0"` to numeric inputs in _tabla-registros**

In the extracted `_tabla-registros.blade.php`, add `min="0"` to:
- `input[data-field="kg_bruto"]`
- Merma con goma input in `_header-orden.blade.php`
- Merma sin goma input in `_header-orden.blade.php`

**Step 5: Verify the page renders identically**

Open the page in browser and verify nothing changed visually.

**Step 6: Commit**

```bash
git add resources/views/modulos/engomado/
git commit -m "refactor(engomado): split production blade into partials for readability"
```

---

## Task 6: Frontend JS validation — marcarListo pre-envío

**Files:**
- Modify: `resources/views/modulos/urdido/produccion/_scripts.blade.php`
- Modify: `resources/views/modulos/engomado/produccion/_scripts.blade.php`

**Step 1: Add JS validation before marcarListo AJAX call**

In both `_scripts.blade.php` files, find the function that handles "marcar listo" checkbox and add validation before the AJAX POST:

```javascript
// --- Validar campos requeridos antes de marcar como listo ---
function validarRegistroParaListo(row) {
    const errores = [];
    const horaInicial = row.querySelector('[data-field="hora_inicial"]');
    const horaFinal = row.querySelector('[data-field="hora_final"]');
    const noJulio = row.querySelector('[data-field="no_julio"]');
    const kgBruto = row.querySelector('[data-field="kg_bruto"]');
    const kgNeto = row.querySelector('[data-field="kg_neto"]');

    if (!horaInicial || !horaInicial.value) errores.push('Hora Inicial');
    if (!horaFinal || !horaFinal.value) errores.push('Hora Final');
    if (!noJulio || !noJulio.value) errores.push('No. Julio');

    const brutoVal = kgBruto ? parseFloat(kgBruto.value) : NaN;
    if (isNaN(brutoVal) || brutoVal < 0) errores.push('Kg. Bruto (debe ser >= 0)');

    const netoVal = kgNeto ? parseFloat(kgNeto.value) : NaN;
    if (!isNaN(netoVal) && netoVal < 0) errores.push('Kg. Neto (no puede ser negativo)');

    return errores;
}
```

Before the AJAX call for marcarListo, add:
```javascript
if (checked) {
    const errores = validarRegistroParaListo(row);
    if (errores.length > 0) {
        checkbox.checked = false;
        toastr.error('Campos faltantes: ' + errores.join(', '));
        return;
    }
}
```

**Step 2: Verify in browser**

Test marking a record as ready with missing fields — should show toast error.

**Step 3: Commit**

```bash
git add resources/views/modulos/urdido/produccion/_scripts.blade.php
git add resources/views/modulos/engomado/produccion/_scripts.blade.php
git commit -m "feat(produccion): add frontend validation before marcarListo"
```

---

## Task 7: Feature tests — Validación de negativos y marcarListo

**Files:**
- Create: `tests/Feature/ProduccionUrdidoValidationTest.php`
- Create: `tests/Feature/ProduccionEngomadoValidationTest.php`

**Step 1: Write test for Urdido marcarListo validation**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;

class ProduccionUrdidoValidationTest extends TestCase
{
    /**
     * Verifica que marcarListo rechaza registros sin HoraInicial.
     */
    public function test_marcar_listo_requires_hora_inicial(): void
    {
        $user = SYSUsuario::first();
        $this->actingAs($user);

        $registro = UrdProduccionUrdido::whereNull('Finalizar')
            ->orWhere('Finalizar', 0)
            ->first();

        if (!$registro) {
            $this->markTestSkipped('No hay registros de producción disponibles para test');
        }

        // Guardar valores originales para restaurar después
        $originalHoraInicial = $registro->HoraInicial;
        $registro->HoraInicial = null;
        $registro->save();

        $response = $this->postJson('/urdido/modulo-produccion-urdido/marcar-listo', [
            'registro_id' => $registro->Id,
            'listo' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        // Restaurar
        $registro->HoraInicial = $originalHoraInicial;
        $registro->save();
    }

    /**
     * Verifica que KgBruto no acepta valores negativos.
     */
    public function test_kg_bruto_rejects_negative_values(): void
    {
        $user = SYSUsuario::first();
        $this->actingAs($user);

        $registro = UrdProduccionUrdido::first();

        if (!$registro) {
            $this->markTestSkipped('No hay registros de producción disponibles');
        }

        $response = $this->postJson('/urdido/modulo-produccion-urdido/actualizar-kg-bruto', [
            'registro_id' => $registro->Id,
            'kg_bruto' => -5.0,
        ]);

        $response->assertStatus(422);
    }

    /**
     * Verifica que guardarOficial rechaza Oficial 2 sin Oficial 1.
     */
    public function test_oficial2_requires_oficial1(): void
    {
        $user = SYSUsuario::first();
        $this->actingAs($user);

        $registro = UrdProduccionUrdido::first();

        if (!$registro) {
            $this->markTestSkipped('No hay registros de producción disponibles');
        }

        // Limpiar oficial 1
        $originalNom = $registro->NomEmpl1;
        $originalCve = $registro->CveEmpl1;
        $registro->NomEmpl1 = null;
        $registro->CveEmpl1 = null;
        $registro->save();

        $response = $this->postJson('/urdido/modulo-produccion-urdido/guardar-oficial', [
            'registro_id' => $registro->Id,
            'numero_oficial' => 2,
            'cve_empl' => '999',
            'nom_empl' => 'Test Oficial 2',
            'turno' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        // Restaurar
        $registro->NomEmpl1 = $originalNom;
        $registro->CveEmpl1 = $originalCve;
        $registro->save();
    }

    /**
     * Verifica que no se repita turno en el mismo registro.
     */
    public function test_turno_no_duplicado_en_registro(): void
    {
        $user = SYSUsuario::first();
        $this->actingAs($user);

        $registro = UrdProduccionUrdido::whereNotNull('NomEmpl1')
            ->whereNotNull('Turno1')
            ->first();

        if (!$registro) {
            $this->markTestSkipped('No hay registros con oficial 1 y turno');
        }

        $turnoExistente = $registro->Turno1;

        $response = $this->postJson('/urdido/modulo-produccion-urdido/guardar-oficial', [
            'registro_id' => $registro->Id,
            'numero_oficial' => 2,
            'cve_empl' => '998',
            'nom_empl' => 'Test Oficial 2 Turno Dup',
            'turno' => $turnoExistente,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }
}
```

**Step 2: Write test for Engomado merma validation**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Sistema\SYSUsuario;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;

class ProduccionEngomadoValidationTest extends TestCase
{
    /**
     * Verifica que merma con goma no acepta valores negativos.
     */
    public function test_merma_con_goma_rejects_negative(): void
    {
        $user = SYSUsuario::first();
        $this->actingAs($user);

        $orden = EngProgramaEngomado::whereIn('Status', ['En Proceso', 'Parcial'])->first();

        if (!$orden) {
            $this->markTestSkipped('No hay órdenes en proceso disponibles');
        }

        $response = $this->postJson('/engomado/modulo-produccion-engomado/actualizar-campo-orden', [
            'orden_id' => $orden->Id,
            'campo' => 'merma_con_goma',
            'valor' => -10.5,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    /**
     * Verifica que merma sin goma no acepta valores negativos.
     */
    public function test_merma_sin_goma_rejects_negative(): void
    {
        $user = SYSUsuario::first();
        $this->actingAs($user);

        $orden = EngProgramaEngomado::whereIn('Status', ['En Proceso', 'Parcial'])->first();

        if (!$orden) {
            $this->markTestSkipped('No hay órdenes en proceso disponibles');
        }

        $response = $this->postJson('/engomado/modulo-produccion-engomado/actualizar-campo-orden', [
            'orden_id' => $orden->Id,
            'campo' => 'merma_sin_goma',
            'valor' => -3.2,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    /**
     * Verifica que marcarListo en Engomado requiere campos obligatorios.
     */
    public function test_marcar_listo_requires_fields(): void
    {
        $user = SYSUsuario::first();
        $this->actingAs($user);

        $registro = EngProduccionEngomado::whereNull('Finalizar')
            ->orWhere('Finalizar', 0)
            ->first();

        if (!$registro) {
            $this->markTestSkipped('No hay registros de producción disponibles');
        }

        $originalHoraInicial = $registro->HoraInicial;
        $registro->HoraInicial = null;
        $registro->save();

        $response = $this->postJson('/engomado/modulo-produccion-engomado/marcar-listo', [
            'registro_id' => $registro->Id,
            'listo' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $registro->HoraInicial = $originalHoraInicial;
        $registro->save();
    }
}
```

**Step 3: Run tests**

Run: `php artisan test tests/Feature/ProduccionUrdidoValidationTest.php tests/Feature/ProduccionEngomadoValidationTest.php`

**Step 4: Commit**

```bash
git add tests/Feature/ProduccionUrdidoValidationTest.php tests/Feature/ProduccionEngomadoValidationTest.php
git commit -m "test(produccion): add validation tests for urdido and engomado production"
```

---

## Task Summary

| Task | Scope | Parallel? |
|------|-------|-----------|
| 1. marcarListo validation | ProduccionTrait.php | Independent |
| 2. Official rules | ProduccionTrait.php | Depends on 1 (same file) |
| 3. Merma validation | Engomado controller | Independent |
| 4. Blade refactor Urdido | Blade files | Independent |
| 5. Blade refactor Engomado | Blade files | Independent |
| 6. Frontend JS validation | Blade scripts | Depends on 4,5 |
| 7. Feature tests | Test files | Depends on 1,2,3 |

**Parallel waves:**
- Wave 1: Tasks 1+3+4+5 (independent)
- Wave 2: Tasks 2+6 (depends on wave 1)
- Wave 3: Task 7 (depends on wave 2)
