# ✅ SOLUCIÓN IMPLEMENTADA - CREATE ahora genera ReqProgramaTejidoLine

## El Problema
- **CREATE** guardaba el programa pero **NO generaba líneas diarias** en `ReqProgramaTejidoLine`
- **UPDATE** sí generaba/regeneraba las líneas
- El frontend CREATE no mostraba la tabla de líneas

## Root Cause Identificado
El modelo `ReqProgramaTejido` tenía:
```php
public $incrementing = false;  // ❌ INCORRECTO
```

Esto hacía que Eloquent NO asignara automáticamente el `Id` después de `.save()`, resultando en:
1. El `Id` era `NULL` en el Observer
2. El Observer no podía crear las líneas porque `ProgramaId = NULL`
3. Las líneas no se guardaban

## Solución
Se cambió en `app/Models/ReqProgramaTejido.php`:
```php
public $incrementing = true;  // ✅ CORRECTO
```

La BD tiene `$table->id('Id')` que genera auto-increment, así que Eloquent debe estar configurado para usar eso.

## Cambios Realizados

### 1. Modelo - `app/Models/ReqProgramaTejido.php`
```diff
- public $incrementing = false;
+ public $incrementing = true;  // ✅ El ID es auto-increment
```

### 2. Observer - `app/Observers/ReqProgramaTejidoObserver.php`
- Agregado log de debug en `saved()` para verificar ID
- Agregado check en `generarLineasDiarias()` para validar que `$programa->Id` exista

### 3. Controlador - `app/Http/Controllers/ProgramaTejidoController.php`
- Sin cambios (ya estaba correcto)

### 4. Frontend - `create.blade.php`
- ✅ Ya tiene el contenedor para la tabla (`contenedor-lineas-diarias`)
- ✅ Ya carga la tabla después de crear (`loadReqProgramaTejidoLines`)

## Verificación

✅ **TEST 1: CREATE genera líneas**
```
Programa ID 169 creado
- 4 líneas diarias generadas
- Cada línea con Fecha, Cantidad, Kilos, Trama, Rizo calculados
```

✅ **TEST 2: UPDATE regenera líneas**
```
Programa ID 169 actualizado
- Líneas regeneradas (de 4 a 9 líneas con nueva fecha)
- Nueva cantidad distribuida correctamente
```

## Comportamiento Final

### CREATE (Nuevo programa)
```
1. Usuario rellena formulario y hace clic "Guardar"
2. store() crea registro en ReqProgramaTejido
3. Eloquent asigna ID automáticamente
4. Observer.saved() se ejecuta
5. Observer crea líneas diarias en ReqProgramaTejidoLine
6. Frontend muestra tabla con líneas (después de 2 segundos redirecciona)
```

### UPDATE (Editar programa)
```
1. Usuario cambia cantidad/fechas y guarda
2. update() modifica registro en ReqProgramaTejido
3. Observer.saved() se ejecuta
4. Observer elimina líneas viejas y crea nuevas
5. Frontend muestra tabla actualizada
```

### Tabla Visible
- ✅ CREATE: Tabla visible después de crear (hidden → visible)
- ✅ UPDATE: Tabla siempre visible en página de editar

## Test Scripts Creados

- `test_create_lines.php` - Verifica que CREATE genera 4 líneas
- `test_update_lines.php` - Verifica que UPDATE regenera líneas (4 → 9)

Ambos tests PASAN ✅

## Próximos Pasos

1. Probar en el navegador:
   - http://127.0.0.1:8000/planeacion/programa-tejido/nuevo
   - Crear programa
   - Verificar que se muestra tabla de líneas

2. Verificar en BD:
   - ReqProgramaTejido tiene ID correcto
   - ReqProgramaTejidoLine tiene líneas con ProgramaId correcto

3. Verificar CambioHilo:
   - Crear dos programas con diferentes hilos
   - Verificar que anterior tiene CambioHilo=1
