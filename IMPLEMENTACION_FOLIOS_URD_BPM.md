# Implementación de Generación Automática de Folios para URD_BPM

## Cambios Realizados

### 1. **Controlador actualizado** (`UrdBpmController.php`)

-   ✅ Importado `FolioHelper`
-   ✅ Se obtiene el folio sugerido para mostrar en la vista
-   ✅ Al crear un registro, el folio se genera automáticamente usando `FolioHelper::obtenerSiguienteFolio('URD_BPM', 5)`
-   ✅ El folio ya NO se valida en el request, se genera automáticamente
-   ✅ Se actualiza automáticamente el consecutivo en la tabla `SSYSFoliosSecuencias`

### 2. **Vista actualizada** (`index.blade.php`)

-   ✅ Eliminado el checkbox problemático de la tabla
-   ✅ Campo de folio ahora es solo lectura y muestra el folio sugerido
-   ✅ Se indica al usuario que "El folio se asignará al guardar"
-   ✅ Ajustado colspan de 12 a 11 en el mensaje de "No hay registros"

### 3. **Script SQL creado** (`add_urd_bpm_folio_secuencia.sql`)

-   ✅ Script para crear el módulo `URD_BPM` en la tabla `SSYSFoliosSecuencias`
-   ✅ Prefijo: **UB**
-   ✅ Consecutivo inicial: **1**
-   ✅ Verifica si ya existe antes de insertar

## Cómo Funciona

### Formato del Folio Generado:

**Prefijo** + **Padding de ceros** + **Consecutivo**

Ejemplo: `UB00001`, `UB00002`, `UB00003`, etc.

### Proceso:

1. Usuario abre el modal de crear
2. Se muestra el folio sugerido (ej: `UB00001`)
3. Usuario llena los demás campos y guarda
4. El sistema:
    - Genera el folio usando `FolioHelper::obtenerSiguienteFolio()`
    - Inserta el registro con el folio generado
    - Incrementa automáticamente el consecutivo en `SSYSFoliosSecuencias` (de 1 a 2)
5. El siguiente folio sugerido será `UB00002`

## Instrucciones de Instalación

### Paso 1: Ejecutar el Script SQL

Ejecuta el archivo `database/scripts/add_urd_bpm_folio_secuencia.sql` en tu base de datos SQL Server:

```sql
-- Esto creará el módulo URD_BPM en SSYSFoliosSecuencias
-- con prefijo 'UB' y consecutivo inicial 1
```

### Paso 2: Verificar

Verifica que el módulo se creó correctamente:

```sql
SELECT * FROM SSYSFoliosSecuencias WHERE Modulo = 'URD_BPM'
```

Deberías ver:
| Id | Modulo | Prefijo | Consecutivo |
|----|---------|---------|-------------|
| X | URD_BPM | UB | 1 |

### Paso 3: Probar

1. Abre la vista de BPM-Urdido
2. Crea un nuevo registro
3. Verifica que el folio se generó automáticamente (ej: `UB00001`)
4. Crea otro registro
5. Verifica que el nuevo folio es `UB00002`

## Beneficios

✅ **No hay duplicados**: El sistema garantiza que cada folio es único
✅ **Automático**: El usuario no tiene que escribir el folio manualmente
✅ **Secuencial**: Los folios siguen un orden consecutivo
✅ **Bloqueado**: Usa `lockForUpdate()` para evitar condiciones de carrera
✅ **Transaccional**: Si algo falla, el consecutivo no se incrementa

## Troubleshooting

### Error: "No se encontró secuencia para el módulo: URD_BPM"

-   **Solución**: Ejecuta el script SQL `add_urd_bpm_folio_secuencia.sql`

### El folio no se incrementa

-   **Solución**: Verifica que la tabla `SSYSFoliosSecuencias` tiene permisos de UPDATE

### Quiero cambiar el prefijo

-   **Solución**: Actualiza el prefijo en la tabla:
    ```sql
    UPDATE SSYSFoliosSecuencias
    SET Prefijo = 'NUEVO_PREFIJO'
    WHERE Modulo = 'URD_BPM'
    ```

### Quiero reiniciar el consecutivo

-   **Solución**:

    ```sql
    UPDATE SSYSFoliosSecuencias
    SET Consecutivo = 1
    WHERE Modulo = 'URD_BPM'
    ```

    O usa el helper:

    ```php
    FolioHelper::reiniciarConsecutivo('URD_BPM', 1);
    ```
