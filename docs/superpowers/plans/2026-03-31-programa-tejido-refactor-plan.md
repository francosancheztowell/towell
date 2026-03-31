# Refactorización Programa Tejido - Plan Maestro

> **Para agentes:** REQUIRED usar superpowers:subagent-driven-development. Tasks usan checkbox (`- [ ]`) para tracking.

**Goal:** Eliminar duplicación de código,fix bugs, y consolidar lógica dispersa en el módulo programa-tejido.

**Architecture:** 
- Consolidar todas las funciones de búsqueda de modelo codificado en `TejidoHelpers::obtenerModeloPorTamanoClave()`
- Crear tests TDD riguroso ANTES de cada refactor
- Ejecutar múltiples agentes en paralelo para tareas independientes

**Tech Stack:** Laravel 12, PHPUnit, SQLite in-memory para tests

---

## FASE 1: Tests Canónicos (TDD Riguroso)

### Task 1.1: Crear test para búsqueda de modelo codificado canónica

**Archivo test:** `tests/Unit/ReqModeloCodificadoBusquedaTest.php`

**Criterios:**
- Test 1: búsqueda exacta encuentra registro
- Test 2: búsqueda por prefijo encuentra registro  
- Test 3: búsqueda por contains encuentra registro
- Test 4: búsqueda sin resultados retorna null
- Test 5: normalización de espacios funciona

```php
public function test_busqueda_exacta_encuentra_registro(): void
{
    // Given: un modelo con TamanoClave = "TEST  ABC"
    // When: buscar "TEST ABC" (sin doble espacio)
    // Then: encuentra el registro
}

public function test_busqueda_prefijo_encuentra_registro(): void
{
    // Given: un modelo con TamanoClave = "ABC123"
    // When: buscar "ABC"
    // Then: encuentra el registro
}

public function test_busqueda_contains_encuentra_registro(): void
{
    // Given: un modelo con TamanoClave = "MODELO-PRUEBA-VALOR"
    // When: buscar "PRUEBA"
    // Then: encuentra el registro
}

public function test_busqueda_sin_resultados_retorna_null(): void
{
    // Given: no existe modelo
    // When: buscar cualquier clave
    // Then: retorna null
}
```

**Pasos:**
- [ ] Escribir test `test_busqueda_exacta_encuentra_registro`
- [ ] Correr test - DEBE FALLAR (función no existe)
- [ ] Implementar `TejidoHelpers::obtenerModeloPorTamanoClave()` con búsqueda exacta
- [ ] Correr test - DEBE PASAR
- [ ] Repetir para cada caso

---

### Task 1.2: Crear test para aplicar datos de modelo codificado

**Archivo test:** `tests/Unit/ReqModeloCodificadoAplicarTest.php`

**Criterios:**
- Test 1: campos se mapean correctamente (57 campos)
- Test 2: CalibreTrama se invierte correctamente (Trama→Trama2, Trama2→Trama)
- Test 3: valores null no sobreescriben valores existentes

```php
public function test_campos_se_mapean_correctamente(): void
{
    // Given: un registro y datos de modelo
    // When: aplicar datos del modelo
    // Then: los 57 campos se mapean correctamente
}

public function test_calibre_trama_se_invierte_correctamente(): void
{
    // Given: modelo con CalibreTrama=10, CalibreTrama2=20
    // When: aplicar al registro
    // Then: registro.CalibreTrama=20, registro.CalibreTrama2=10
}

public function test_valores_null_no_sobreescriben(): void
{
    // Given: registro existente con campo = "ORIGINAL"
    // And: modelo tiene null para ese campo
    // When: aplicar datos del modelo
    // Then: registro mantiene "ORIGINAL"
}
```

---

### Task 1.3: Crear test para Bug CalibreTrama

**Archivo test:** `tests/Unit/DividirTejidoBugCalibreTramaTest.php`

**Criterios específicos del bug:**
```php
public function test_calibre_trama_no_se_duplica_en_dos_pasos(): void
{
    // Given: modelo con CalibreTrama=10, CalibreTrama2=20
    // When: aplicar modelo codificado (como hace DividirTejido)
    // Then: CalibreTrama=20, CalibreTrama2=10 (UNA SOLA VEZ, no dos)
    // BUG ACTUAL: El código hace la inversión DOS VECES, cancelándose
}
```

---

## FASE 2: Consolidación de Funciones

### Task 2.1: Consolidar búsqueda de modelo en TejidoHelpers

**Archivos a modificar:**
- `app/Http/Controllers/Planeacion/ProgramaTejido/helper/TejidoHelpers.php` (AGREGAR método)
- `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DuplicarTejido.php` (REEMPLAZAR línea 706-776)
- `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DividirTejido.php` (REEMPLAZAR línea 726-756)
- `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/UpdateTejido.php` (REEMPLAZAR línea 692-809)
- `app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoCatalogosController.php` (REEMPLAZAR método getDatosRelacionados)

**Método a crear en TejidoHelpers:**
```php
public static function obtenerModeloPorTamanoClave(
    string $tamanoClave, 
    ?string $salonTejidoId = null,
    array $selectCols = ['*']
): ?ReqModelosCodificados
```

**Pasos:**
- [ ] Implementar método canónico en TejidoHelpers
- [ ] Actualizar DuplicarTejido para usar TejidoHelpers
- [ ] Actualizar DividirTejido para usar TejidoHelpers
- [ ] Actualizar UpdateTejido para usar TejidoHelpers
- [ ] Actualizar ProgramaTejidoCatalogosController para usar TejidoHelpers
- [ ] CORRER TODOS LOS TESTS - deben pasar

---

### Task 2.2: Consolidar aplicar datos de modelo

**Archivos a modificar:**
- `app/Http/Controllers/Planeacion/ProgramaTejido/helper/TejidoHelpers.php` (AGREGAR método)
- `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DuplicarTejido.php` (REEMPLAZAR aplicarDatosModeloCodificado)
- `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DividirTejido.php` (REEMPLAZAR aplicarModeloCodificadoPorSalon)

**Método a crear en TejidoHelpers:**
```php
public static function aplicarDatosModeloCodificado(
    ReqProgramaTejido $registro,
    ?ReqModelosCodificados $modelo
): void
```

**Nota:** Este método ya existe parcialmente en DuplicarTejido. Consolidar y corregir el bug de CalibreTrama.

---

### Task 2.3: Eliminar cadena circular UpdateTejido→DuplicarTejido

**Archivo a modificar:** `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/UpdateTejido.php`

**Cambio específico:**
- Línea 845: `return DuplicarTejido::calcularFormulasEficiencia($programa);`
- Cambiar a: `return TejidoHelpers::calcularFormulasEficiencia($programa, ...);`

**Pasos:**
- [ ] Modificar UpdateTejido línea 845
- [ ] Verificar que el método en DuplicarTejido ya llama a TejidoHelpers
- [ ] Tests pasan sin cambios adicionales

---

### Task 2.4: Unificar sanitizeNumber y construirMaquina en DividirTejido

**Archivo a modificar:** `app/Http/Controllers/Planeacion/ProgramaTejido/funciones/DividirTejido.php`

**Cambios:**
- Línea 130: `str_replace(',', '', $rawPedido)` → `TejidoHelpers::sanitizeNumber()`
- Líneas 273-277: lógica inline → `TejidoHelpers::construirMaquinaConBase()`

---

## FASE 3: Tests de Integración

### Task 3.1: Tests de integración DuplicarTejido

**Archivo test:** `tests/Feature/ProgramaTejidoDuplicarTest.php`

**Casos a probar:**
1. Duplicar telar simple (sin vincular)
2. Duplicar telar con vinculación (OrdCompartida)
3. Duplicar con cambio de clave modelo
4. Duplicar múltiples destinos

### Task 3.2: Tests de integración DividirTejido

**Archivo test:** `tests/Feature/ProgramaTejidoDividirTest.php`

**Casos a probar:**
1. Dividir registro simple
2. Dividir con redistribution de grupo existente
3. Verificar que CalibreTrama se aplica correctamente

### Task 3.3: Tests de integración UpdateTejido

**Archivo test:** `tests/Feature/ProgramaTejidoUpdateTest.php`

**Casos a probar:**
1. Update simple de pedido
2. Update que cambia clave modelo
3. Update que cambia calendario

---

## Dependencias de Tareas

```
TASK 1.1 (tests búsqueda) ─────┐
TASK 1.2 (tests aplicar)  ────┼──→ TASK 2.1 (consolidar)
TASK 1.3 (test bug)      ─────┘

TASK 2.1 ─────────────────────────────→ TASK 2.2
                                          │
TASK 2.3 ←───────────────────────────────┘
TASK 2.4 (puede correr en paralelo)

TASK 2.1 + 2.2 + 2.3 + 2.4 ────────────→ TASK 3.1
                                                   │
                                                   ├──→ TASK 3.2 ───→ FINAL VALIDATION
                                                   │
                                                   └──→ TASK 3.3 ───→
```

---

## Validación Final

**Comando de validación:**
```bash
php artisan test --filter=Tejido
```

**Criterios:**
- Todos los tests pasan (0 failures)
- No hay warnings de deprecated
- Coverage mínimo: 80% para funciones modificadas

---

## Agents Allocation

| Agent | Tasks | Responsabilidad |
|-------|-------|----------------|
| Agent-1 | 1.1, 2.1 | Tests canónicos y consolidación de búsqueda |
| Agent-2 | 1.2, 1.3, 2.2 | Tests de aplicación y bug fix |
| Agent-3 | 2.3, 2.4, 3.1 | Circulación, unificación, tests duplicar |
| Agent-4 | 3.2, 3.3 | Tests dividir y update |

**Importante:** Cada agent debe verificar que sus tests pasan ANTES de reportar completación.
