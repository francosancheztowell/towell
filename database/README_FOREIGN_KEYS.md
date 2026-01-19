# Claves Foráneas para Integridad Referencial

## 📋 Resumen

Este script crea claves foráneas (Foreign Keys) para mejorar la integridad referencial entre las tablas `InvTelasReservadas` y `tej_inventario_telares`.

## 🎯 Objetivo

Asegurar que:
1. Cada reserva en `InvTelasReservadas` referencie un registro válido en `tej_inventario_telares`
2. Al eliminar un registro de telar, se eliminen automáticamente sus reservas relacionadas (CASCADE)
3. Al actualizar un telar, se actualicen automáticamente las referencias (CASCADE)

## ⚠️ Consideraciones Importantes

### Problema Principal
- `tej_inventario_telares` puede tener **múltiples registros** con el mismo `no_telar` y `tipo` pero diferentes `fecha` y `turno`
- Una FK simple (`NoTelarId` → `no_telar`) no es suficiente porque no identifica el registro específico
- Necesitamos una **FK compuesta** que incluya `fecha` y `turno`

### Solución Propuesta

1. **Índice Único Compuesto** en `tej_inventario_telares`:
   - `(no_telar, tipo, fecha, turno, status)` - Solo para registros activos
   - Esto permite identificar de manera única cada registro

2. **Columnas Adicionales** en `InvTelasReservadas`:
   - `FechaReserva` (DATE) - Para almacenar la fecha específica del registro reservado
   - `TurnoReserva` (INT) - Para almacenar el turno específico del registro reservado

3. **Clave Foránea Compuesta**:
   - `(NoTelarId, Tipo, FechaReserva, TurnoReserva)` → `(no_telar, tipo, fecha, turno)`
   - Con `ON DELETE CASCADE` y `ON UPDATE CASCADE`

## 📊 Estructura de la FK

```
InvTelasReservadas                    tej_inventario_telares
├── NoTelarId      ────────────────>  ├── no_telar
├── Tipo           ────────────────>  ├── tipo
├── FechaReserva   ────────────────>  ├── fecha
└── TurnoReserva   ────────────────>  └── turno
```

## 🚀 Cómo Ejecutar

### Opción 1: SQL Server Management Studio (SSMS)
1. Abre SSMS y conéctate a tu servidor SQL Server
2. Selecciona la base de datos `ProdTowel`
3. Abre el archivo `database/foreign_keys_inventario_telares.sql`
4. Ejecuta el script completo (F5)

### Opción 2: Línea de comandos (sqlcmd)
```bash
sqlcmd -S tu_servidor -d ProdTowel -i database/foreign_keys_inventario_telares.sql
```

## ⚙️ Pasos del Script

### 1. Crear Índice Único Compuesto
- Crea un índice único en `tej_inventario_telares` con `(no_telar, tipo, fecha, turno, status)`
- Solo para registros con `status = 'Activo'` (índice filtrado)
- **IMPORTANTE**: Si hay registros duplicados, el script mostrará un error

### 2. Agregar Columnas a InvTelasReservadas
- Agrega `FechaReserva DATE NULL`
- Agrega `TurnoReserva INT NULL`
- Copia `ProdDate` a `FechaReserva` si existe

### 3. Crear Clave Foránea
- La FK está **comentada** por defecto
- Descomenta después de asegurar que todos los registros tienen `FechaReserva` y `TurnoReserva`

## 📝 Actualizar Datos Existentes

Antes de crear la FK, necesitas actualizar los registros existentes:

```sql
-- Actualizar FechaReserva desde ProdDate
UPDATE dbo.InvTelasReservadas
SET FechaReserva = CAST(ProdDate AS DATE)
WHERE ProdDate IS NOT NULL AND FechaReserva IS NULL;

-- Actualizar TurnoReserva desde tej_inventario_telares
-- (Esto requiere hacer un JOIN basado en NoTelarId, Tipo y FechaReserva)
UPDATE r
SET r.TurnoReserva = t.turno
FROM dbo.InvTelasReservadas r
INNER JOIN dbo.tej_inventario_telares t
    ON r.NoTelarId = t.no_telar
    AND r.Tipo = t.tipo
    AND CAST(r.ProdDate AS DATE) = t.fecha
WHERE r.TurnoReserva IS NULL
    AND r.ProdDate IS NOT NULL
    AND t.status = 'Activo';
```

## ✅ Verificación

Después de ejecutar el script, verifica que todo esté correcto:

```sql
-- Ver índices únicos creados
SELECT 
    i.name AS IndexName,
    i.type_desc AS IndexType,
    i.is_unique AS IsUnique,
    STRING_AGG(c.name, ', ') WITHIN GROUP (ORDER BY ic.key_ordinal) AS IndexColumns
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE i.object_id = OBJECT_ID('dbo.tej_inventario_telares')
    AND i.is_unique = 1
GROUP BY i.name, i.type_desc, i.is_unique
ORDER BY i.name;

-- Ver claves foráneas creadas
SELECT 
    fk.name AS ForeignKeyName,
    OBJECT_NAME(fk.parent_object_id) AS ParentTable,
    OBJECT_NAME(fk.referenced_object_id) AS ReferencedTable,
    fk.delete_referential_action_desc AS OnDelete,
    fk.update_referential_action_desc AS OnUpdate
FROM sys.foreign_keys fk
WHERE fk.parent_object_id = OBJECT_ID('dbo.InvTelasReservadas')
   OR fk.referenced_object_id = OBJECT_ID('dbo.tej_inventario_telares');
```

## 🔄 Modificar Código de la Aplicación

Después de crear las columnas y la FK, necesitas actualizar el código:

### En `InvTelasReservadasController::reservar()`:
```php
// Agregar FechaReserva y TurnoReserva al crear la reserva
$data['FechaReserva'] = $data['fecha'] ?? null;
$data['TurnoReserva'] = $data['turno'] ?? null;
```

### En el Modelo `InvTelasReservadas`:
```php
protected $fillable = [
    // ... campos existentes ...
    'FechaReserva',
    'TurnoReserva',
];
```

## ⚠️ Advertencias

1. **Datos Existentes**: Si ya hay reservas sin `FechaReserva` o `TurnoReserva`, la FK fallará
2. **Rendimiento**: Las FKs agregan validación en cada INSERT/UPDATE, lo que puede afectar el rendimiento
3. **CASCADE**: `ON DELETE CASCADE` eliminará automáticamente las reservas al eliminar un telar
4. **Duplicados**: El índice único fallará si hay registros duplicados con la misma combinación

## 🔄 Rollback (Si es necesario)

Si necesitas eliminar las FKs:

```sql
-- Eliminar FK compuesta
IF EXISTS (
    SELECT 1 FROM sys.foreign_keys 
    WHERE name = 'FK_InvTelasReservadas_tej_inventario_telares'
)
BEGIN
    ALTER TABLE dbo.InvTelasReservadas
    DROP CONSTRAINT FK_InvTelasReservadas_tej_inventario_telares;
END

-- Eliminar columnas (opcional)
ALTER TABLE dbo.InvTelasReservadas
DROP COLUMN FechaReserva, TurnoReserva;

-- Eliminar índice único (opcional)
DROP INDEX UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status
ON dbo.tej_inventario_telares;
```

## 📈 Beneficios

1. **Integridad Referencial**: Garantiza que cada reserva referencie un registro válido
2. **Eliminación Automática**: Al eliminar un telar, se eliminan sus reservas automáticamente
3. **Prevención de Errores**: Evita crear reservas para telares que no existen
4. **Mejor Rendimiento**: Las FKs pueden mejorar el rendimiento de JOINs
