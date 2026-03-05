# Diseño: Validación y Refactor de Producción Urdido/Engomado

**Fecha:** 2026-03-04
**Módulos:** Urdido, Engomado (producción)
**Objetivo:** Prevenir valores negativos, requerir fechas/horas para finalizar registros, validar oficiales, refactorizar blades para legibilidad, agregar tests.

## Contexto

Ambos módulos comparten `ProduccionTrait.php` (545 líneas) y tienen blades monolíticos:
- Urdido: 2,745 líneas
- Engomado: 3,147 líneas

## Requisitos

### R1: Validación de Negativos
- **KgBruto**: min:0 en frontend (HTML `min="0"`) y backend (ya existe `min:0`)
- **KgNeto**: readonly, calculado. Bloquear `marcarListo` si < 0
- **MermaGoma** (Engomado): min:0 en frontend y backend (NO existe actualmente)
- **Merma** (Engomado): min:0 en frontend y backend (NO existe actualmente)
- **Vueltas** (Urdido): min:0 en frontend y backend
- **Diámetro** (Urdido): min:0 en frontend y backend
- Otros campos numéricos (Canoa, Solidos, Roturas, Humedad): libres

### R2: Validación para Marcar Listo (registro individual)
Para marcar un registro como "listo" (`Finalizar = 1`), se requiere:
- `HoraInicial` no vacío
- `HoraFinal` no vacío
- `NoJulio` no vacío
- `KgBruto` >= 0 y no nulo
- `KgNeto` >= 0

### R3: Reglas de Oficiales (ambos módulos, en ProduccionTrait)
- No duplicar `CveEmpl` en posiciones 1, 2, 3 del mismo registro
- No duplicar turno en el mismo registro
- Oficial 2 requiere que Oficial 1 exista
- Oficial 3 requiere que Oficial 2 exista

### R4: Refactor de Blades (solo legibilidad, sin cambios de diseño)
**Urdido:**
```
resources/views/modulos/urdido/produccion/
  index.blade.php              → layout principal con @includes
  _header-orden.blade.php      → información de la orden
  _tabla-registros.blade.php   → tabla de registros de producción
  _modal-oficial.blade.php     → modal de gestión de oficiales
  _modal-fecha.blade.php       → modal de selección de fecha
  _scripts.blade.php           → todo el JavaScript con comentarios
```

**Engomado:**
```
resources/views/modulos/engomado/produccion/
  index.blade.php              → layout principal con @includes
  _header-orden.blade.php      → información de la orden + campos merma
  _tabla-registros.blade.php   → tabla de registros de producción
  _modal-oficial.blade.php     → modal de gestión de oficiales
  _modal-fecha.blade.php       → modal de selección de fecha
  _scripts.blade.php           → todo el JavaScript con comentarios
```

Cada partial tendrá un comentario header describiendo su propósito.

### R5: Performance
- Revisar y optimizar queries N+1 en `index()`
- Eager loading de relaciones donde aplique

### R6: Tests
- Feature tests para endpoints de validación (negativos, fechas, oficiales)
- Test de flujo de finalización parcial y total

## Enfoque Técnico

### Backend
- Validaciones comunes se agregan/refuerzan en `ProduccionTrait.php`
- Validaciones específicas de Engomado (mermas) en su controller
- Validaciones específicas de Urdido (vueltas/diámetro) en su controller
- `marcarListo()` se modifica para validar campos requeridos antes de marcar

### Frontend
- Agregar atributos `min="0"` en inputs HTML de campos que no deben ser negativos
- Agregar validación JS pre-envío en `marcarListo` para campos requeridos
- Validación JS en cambio de valor para campos negativos (feedback visual inmediato)

### Oficiales (ProduccionTrait::guardarOficial)
- Verificar secuencialidad: posición N requiere posición N-1 llena
- Verificar unicidad: CveEmpl no repetido en el mismo registro
- Verificar turno: no repetir turno en el mismo registro

## Archivos Afectados

1. `app/Traits/ProduccionTrait.php` — validaciones de marcarListo, oficiales
2. `app/Http/Controllers/Urdido/Configuracion/ModuloProduccionUrdidoController.php` — validaciones específicas
3. `app/Http/Controllers/Engomado/Produccion/ModuloProduccionEngomadoController.php` — validaciones merma
4. `resources/views/modulos/urdido/modulo-produccion-urdido.blade.php` → split en 6 partials
5. `resources/views/modulos/engomado/modulo-produccion-engomado.blade.php` → split en 6 partials
6. `tests/Feature/` — nuevos tests de validación
