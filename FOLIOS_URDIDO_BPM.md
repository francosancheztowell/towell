# Implementación de Generación Automática de Folios para Urdido BPM

## ✅ Configuración Actual

El módulo **"Urdido BPM"** ya existe en la tabla `SSYSFoliosSecuencias`:

| Id  | Módulo     | Prefijo | Consecutivo |
| --- | ---------- | ------- | ----------- |
| 11  | Urdido BPM | BU      | 1           |

## Cambios Realizados

### 1. **Controlador actualizado** (`UrdBpmController.php`)

-   ✅ Usa el módulo correcto: `'Urdido BPM'` (no URD_BPM)
-   ✅ Padding de 3 dígitos (001, 002, 003...)
-   ✅ Genera folio automáticamente: `FolioHelper::obtenerSiguienteFolio('Urdido BPM', 3)`
-   ✅ Muestra folio sugerido en la vista antes de crear

### 2. **Vista mejorada** (`index.blade.php`)

-   ✅ Muestra el folio en un cuadro destacado azul
-   ✅ Formato grande y visible con el folio sugerido
-   ✅ Mensaje claro: "Este folio se asignará automáticamente al crear el registro"

## Formato del Folio

**Prefijo** + **Consecutivo con padding de 3 dígitos**

Ejemplos:

-   Primer registro: `BU001`
-   Segundo registro: `BU002`
-   Registro 10: `BU010`
-   Registro 100: `BU100`

## Cómo Funciona

1. **Usuario abre el modal** → Se muestra `BU001` (folio sugerido)
2. **Usuario completa el formulario** → Llena los datos de empleados
3. **Usuario hace clic en "Guardar"** → El sistema genera automáticamente el folio
4. **Sistema guarda el registro** → Con folio `BU001`
5. **Sistema actualiza consecutivo** → De 1 a 2
6. **Próximo folio sugerido** → Será `BU002`

## Verificación

Para verificar que todo funciona correctamente:

```sql
-- Ver la configuración actual
SELECT * FROM SSYSFoliosSecuencias WHERE Modulo = 'Urdido BPM'

-- Ver los registros creados
SELECT Folio, Status, Fecha, NombreEmplEnt, NombreEmplRec
FROM UrdBPM
ORDER BY Id DESC
```

## Beneficios

✅ **Sin duplicados** - Cada folio es único y secuencial  
✅ **Automático** - El usuario no escribe el folio  
✅ **Visible** - Se muestra claramente antes de guardar  
✅ **Transaccional** - Si falla el guardado, no se incrementa el consecutivo  
✅ **Thread-safe** - Usa bloqueos para evitar condiciones de carrera

## Troubleshooting

### ❌ Error: "No se encontró secuencia para el módulo: Urdido BPM"

**Causa**: El registro no existe en la tabla  
**Solución**: Ejecuta este SQL:

```sql
INSERT INTO SSYSFoliosSecuencias (Modulo, Prefijo, Consecutivo)
VALUES ('Urdido BPM', 'BU', 1)
```

### ❌ El folio no se muestra en el modal

**Causa**: El controlador no está pasando la variable `$folioSugerido`  
**Solución**: Verificar que el método `index()` incluye:

```php
$folioSugerido = FolioHelper::obtenerFolioSugerido('Urdido BPM', 3);
return view("...", compact("items", "usuarios", "folioSugerido"));
```

### ❌ El consecutivo no se incrementa

**Causa**: Permisos de escritura en la tabla  
**Solución**: Verificar permisos UPDATE en `SSYSFoliosSecuencias`

## Cambiar Configuración

### Cambiar el prefijo de "BU" a otro

```sql
UPDATE SSYSFoliosSecuencias
SET Prefijo = 'UR'
WHERE Modulo = 'Urdido BPM'
```

### Reiniciar el consecutivo a 1

```sql
UPDATE SSYSFoliosSecuencias
SET Consecutivo = 1
WHERE Modulo = 'Urdido BPM'
```

O usando el helper en código:

```php
FolioHelper::reiniciarConsecutivo('Urdido BPM', 1);
```
