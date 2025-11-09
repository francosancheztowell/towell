# Archivos No Utilizados - Towell

Este documento lista los archivos que han sido identificados como no utilizados en el proyecto. Se recomienda revisar cada uno antes de eliminarlos para confirmar que no son necesarios.

## Controladores No Utilizados

### 1. `app/Http/Controllers/UrdidoController.php`
- **Estado**: Archivo no existe
- **Problema**: Está importado en `routes/web.php` (línea 17) pero el archivo físico no existe
- **Acción recomendada**: Eliminar la importación de `routes/web.php` línea 17

### 2. `app/Http/Controllers/ExcelUploadController.php`
- **Estado**: Existe pero no está registrado en rutas
- **Descripción**: Controlador con métodos para procesar archivos Excel pero no hay rutas que lo utilicen
- **Acción recomendada**: Si no se planea usar, eliminar el archivo. Si se planea usar, agregar las rutas correspondientes en `routes/web.php`

### 3. `app/Http/Controllers/FallasController.php`
- **Estado**: Existe pero está vacío (solo tiene la clase base)
- **Problema**: No está registrado en rutas y no tiene funcionalidad
- **Acción recomendada**: Eliminar el archivo

### 4. `app/Http/Controllers/ModelosController.php`
- **Estado**: Tiene código pero no está registrado en rutas
- **Problema**: Existen vistas en `resources/views/modulos/modelos/` pero no hay rutas que conecten el controlador con las vistas
- **Vistas relacionadas**:
  - `resources/views/modulos/modelos/index.blade.php`
  - `resources/views/modulos/modelos/create.blade.php`
  - `resources/views/modulos/modelos/edit.blade.php`
- **Acción recomendada**: 
  - Si se planea usar: Agregar rutas en `routes/web.php` para conectar el controlador con las vistas
  - Si no se planea usar: Eliminar el controlador y las vistas relacionadas

### 5. `app/Http/Controllers/ReportesController.php`
- **Estado**: Tiene código pero la vista referenciada no existe
- **Problema**: El controlador referencia la vista `TEJIDO-SCHEDULING.reportes.consumo` (línea 196) que no existe en el proyecto
- **Acción recomendada**: 
  - Si se planea usar: Crear la vista faltante o corregir la ruta de la vista
  - Si no se planea usar: Eliminar el archivo

## Modelos

### 6. `app/Models/User.php`
- **Estado**: Importado pero no utilizado
- **Problema**: Está importado en `app/Http/Controllers/AuthController.php` (línea 8) pero realmente se usa el modelo `Usuario`
- **Nota**: El sistema de autenticación está configurado para usar `App\Models\Usuario` (ver `config/auth.php` línea 65)
- **Acción recomendada**: Eliminar la importación de `AuthController.php` línea 8. El archivo `User.php` puede mantenerse si se planea usar en el futuro, pero actualmente no es necesario.

## Vistas

### 7. Vistas de Modelos (si el controlador no se va a usar)
- **Ruta**: `resources/views/modulos/modelos/`
- **Archivos**:
  - `index.blade.php`
  - `create.blade.php`
  - `edit.blade.php`
- **Acción recomendada**: Eliminar solo si se confirma que `ModelosController` no se va a utilizar

### 8. Vista de Reportes (referenciada pero no existe)
- **Ruta esperada**: `resources/views/TEJIDO-SCHEDULING/reportes/consumo.blade.php`
- **Problema**: Referenciada en `ReportesController.php` pero el archivo no existe
- **Acción recomendada**: Si se planea usar reportes, crear la vista. Si no, puede ignorarse junto con el controlador.

## Scripts de Base de Datos

### 9. `database/scripts/`
- **Estado**: Scripts de mantenimiento/utilitarios
- **Archivos**:
  - `crear_plantilla_excel.php`
  - `crear_plantilla_limpia.php`
  - `fix_column_types_direct.sql`
  - `limpiar_prefijos_telares.php`
  - `probar_correcciones.php`
  - `reorganizar_modulos.php`
- **Acción recomendada**: Revisar si estos scripts son necesarios para mantenimiento futuro. Si son scripts de una sola ejecución ya completados, pueden eliminarse.

## Resumen de Acciones

### Alta Prioridad (Eliminar importaciones no válidas) - COMPLETADO
1. ~~Eliminar importación de `UrdidoController` en `routes/web.php` línea 17~~ - COMPLETADO
2. ~~Eliminar importación de `User` en `app/Http/Controllers/AuthController.php` línea 8~~ - COMPLETADO

### Media Prioridad (Archivos sin uso confirmado)
1. `app/Http/Controllers/FallasController.php` - Eliminar (vacío)
2. `app/Http/Controllers/ExcelUploadController.php` - Revisar y eliminar si no se usa
3. `app/Http/Controllers/ReportesController.php` - Revisar y eliminar si no se usa, o crear la vista faltante

### Baja Prioridad (Revisar antes de eliminar)
1. `app/Http/Controllers/ModelosController.php` y vistas relacionadas - Decidir si se implementa o se elimina
2. Scripts en `database/scripts/` - Revisar si son necesarios para mantenimiento

## Notas Importantes

- Antes de eliminar cualquier archivo, asegúrese de hacer un backup del proyecto
- Revise las dependencias en el código antes de eliminar archivos
- Algunos archivos pueden ser necesarios para funcionalidades futuras planeadas
- Los scripts de base de datos pueden ser útiles para mantenimiento o migraciones

## Fecha de Revisión

Este documento fue generado el: $(Get-Date -Format "yyyy-MM-dd")

