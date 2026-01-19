-- =====================================================
-- CLAVE FORÁNEA SIMPLE PARA INTEGRIDAD REFERENCIAL
-- Versión simplificada - Ejecutar directamente
-- =====================================================

USE ProdTowel;
GO

-- =====================================================
-- PASO 1: Verificar y crear índice único compuesto
-- =====================================================
-- Este índice permite identificar registros únicos por:
-- no_telar + tipo + fecha + turno + status
-- =====================================================

-- Verificar si hay duplicados antes de crear el índice único
PRINT 'Verificando duplicados...';
IF EXISTS (
    SELECT no_telar, tipo, fecha, turno, status, COUNT(*) as cnt
    FROM dbo.tej_inventario_telares
    WHERE status = 'Activo'
    GROUP BY no_telar, tipo, fecha, turno, status
    HAVING COUNT(*) > 1
)
BEGIN
    PRINT 'ADVERTENCIA: Hay registros duplicados. Ejecuta esta consulta para verlos:';
    PRINT 'SELECT no_telar, tipo, fecha, turno, status, COUNT(*) as cnt FROM dbo.tej_inventario_telares WHERE status = ''Activo'' GROUP BY no_telar, tipo, fecha, turno, status HAVING COUNT(*) > 1';
    PRINT 'Debes eliminar los duplicados antes de continuar.';
END
ELSE
BEGIN
    PRINT 'No hay duplicados. Procediendo a crear índice único...';
    
    -- Crear índice único compuesto
    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes 
        WHERE name = 'UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status'
        AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
    )
    BEGIN
        CREATE UNIQUE NONCLUSTERED INDEX UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status
        ON dbo.tej_inventario_telares (no_telar, tipo, fecha, turno, status)
        WHERE status = 'Activo';
        
        PRINT '✓ Índice único creado exitosamente';
    END
    ELSE
    BEGIN
        PRINT 'El índice único ya existe';
    END
END
GO

-- =====================================================
-- PASO 2: Agregar columnas de referencia a InvTelasReservadas
-- =====================================================

-- Agregar FechaReserva
IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('dbo.InvTelasReservadas')
    AND name = 'FechaReserva'
)
BEGIN
    ALTER TABLE dbo.InvTelasReservadas
    ADD FechaReserva DATE NULL;
    
    PRINT '✓ Columna FechaReserva agregada';
    
    -- Copiar fecha desde ProdDate si existe
    UPDATE dbo.InvTelasReservadas
    SET FechaReserva = CAST(ProdDate AS DATE)
    WHERE ProdDate IS NOT NULL AND FechaReserva IS NULL;
    
    PRINT '✓ FechaReserva actualizada desde ProdDate';
END
ELSE
BEGIN
    PRINT 'Columna FechaReserva ya existe';
END
GO

-- Agregar TurnoReserva
IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('dbo.InvTelasReservadas')
    AND name = 'TurnoReserva'
)
BEGIN
    ALTER TABLE dbo.InvTelasReservadas
    ADD TurnoReserva INT NULL;
    
    PRINT '✓ Columna TurnoReserva agregada';
    PRINT 'NOTA: Debes actualizar TurnoReserva manualmente o desde la aplicación';
END
ELSE
BEGIN
    PRINT 'Columna TurnoReserva ya existe';
END
GO

-- =====================================================
-- PASO 3: Actualizar TurnoReserva desde tej_inventario_telares
-- =====================================================
-- Esto intenta hacer match entre reservas y telares usando:
-- NoTelarId + Tipo + FechaReserva (o ProdDate)
-- =====================================================

PRINT 'Actualizando TurnoReserva desde tej_inventario_telares...';

UPDATE r
SET r.TurnoReserva = t.turno
FROM dbo.InvTelasReservadas r
INNER JOIN dbo.tej_inventario_telares t
    ON r.NoTelarId = t.no_telar
    AND r.Tipo = t.tipo
    AND (
        (r.FechaReserva IS NOT NULL AND CAST(r.FechaReserva AS DATE) = CAST(t.fecha AS DATE))
        OR
        (r.FechaReserva IS NULL AND r.ProdDate IS NOT NULL AND CAST(r.ProdDate AS DATE) = CAST(t.fecha AS DATE))
    )
WHERE r.TurnoReserva IS NULL
    AND t.status = 'Activo'
    AND r.Status = 'Reservado';

DECLARE @updated INT = @@ROWCOUNT;
PRINT CONCAT('✓ ', @updated, ' registros actualizados con TurnoReserva');
GO

-- =====================================================
-- PASO 4: Verificar que todos los registros tienen FechaReserva y TurnoReserva
-- =====================================================

PRINT '';
PRINT 'Verificando completitud de datos...';

SELECT 
    COUNT(*) AS TotalReservas,
    SUM(CASE WHEN FechaReserva IS NULL THEN 1 ELSE 0 END) AS SinFechaReserva,
    SUM(CASE WHEN TurnoReserva IS NULL THEN 1 ELSE 0 END) AS SinTurnoReserva,
    SUM(CASE WHEN FechaReserva IS NOT NULL AND TurnoReserva IS NOT NULL THEN 1 ELSE 0 END) AS Completos
FROM dbo.InvTelasReservadas
WHERE Status = 'Reservado';

DECLARE @sinFecha INT, @sinTurno INT;
SELECT 
    @sinFecha = SUM(CASE WHEN FechaReserva IS NULL THEN 1 ELSE 0 END),
    @sinTurno = SUM(CASE WHEN TurnoReserva IS NULL THEN 1 ELSE 0 END)
FROM dbo.InvTelasReservadas
WHERE Status = 'Reservado';

IF @sinFecha > 0 OR @sinTurno > 0
BEGIN
    PRINT '';
    PRINT 'ADVERTENCIA: Hay reservas sin FechaReserva o TurnoReserva.';
    PRINT 'Debes completar estos datos antes de crear la clave foránea.';
    PRINT '';
    PRINT 'Consulta para ver los registros incompletos:';
    PRINT 'SELECT Id, NoTelarId, Tipo, ProdDate, FechaReserva, TurnoReserva FROM dbo.InvTelasReservadas WHERE Status = ''Reservado'' AND (FechaReserva IS NULL OR TurnoReserva IS NULL)';
END
ELSE
BEGIN
    PRINT '✓ Todos los registros tienen FechaReserva y TurnoReserva';
    PRINT '';
    PRINT 'Puedes proceder a crear la clave foránea descomentando el siguiente bloque:';
END
GO

-- =====================================================
-- PASO 5: Crear clave foránea compuesta
-- =====================================================
-- DESCOMENTA ESTE BLOQUE DESPUÉS DE VERIFICAR QUE TODOS LOS REGISTROS
-- TIENEN FechaReserva Y TurnoReserva
-- =====================================================

/*
-- Crear índice en InvTelasReservadas para la FK
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'IX_InvTelasReservadas_NoTelarId_Tipo_FechaReserva_TurnoReserva'
    AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_NoTelarId_Tipo_FechaReserva_TurnoReserva
    ON dbo.InvTelasReservadas (NoTelarId, Tipo, FechaReserva, TurnoReserva);
    
    PRINT '✓ Índice en InvTelasReservadas creado';
END
GO

-- Crear la clave foránea compuesta
IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys 
    WHERE name = 'FK_InvTelasReservadas_tej_inventario_telares'
    AND parent_object_id = OBJECT_ID('dbo.InvTelasReservadas')
)
BEGIN
    ALTER TABLE dbo.InvTelasReservadas
    ADD CONSTRAINT FK_InvTelasReservadas_tej_inventario_telares
    FOREIGN KEY (NoTelarId, Tipo, FechaReserva, TurnoReserva)
    REFERENCES dbo.tej_inventario_telares (no_telar, tipo, fecha, turno)
    ON DELETE CASCADE   -- Si se elimina el telar, eliminar las reservas
    ON UPDATE CASCADE;  -- Si se actualiza el telar, actualizar las reservas
    
    PRINT '✓ Clave foránea FK_InvTelasReservadas_tej_inventario_telares creada exitosamente';
END
ELSE
BEGIN
    PRINT 'La clave foránea ya existe';
END
GO
*/

-- =====================================================
-- VERIFICACIÓN FINAL
-- =====================================================

PRINT '';
PRINT '=====================================================';
PRINT 'VERIFICACIÓN FINAL';
PRINT '=====================================================';

-- Ver índices únicos
SELECT 
    i.name AS IndexName,
    i.is_unique AS IsUnique,
    STRING_AGG(c.name, ', ') WITHIN GROUP (ORDER BY ic.key_ordinal) AS IndexColumns
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE i.object_id = OBJECT_ID('dbo.tej_inventario_telares')
    AND i.is_unique = 1
GROUP BY i.name, i.is_unique
ORDER BY i.name;

-- Ver columnas agregadas
SELECT 
    c.name AS ColumnName,
    t.name AS DataType,
    c.is_nullable AS IsNullable
FROM sys.columns c
INNER JOIN sys.types t ON c.user_type_id = t.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.InvTelasReservadas')
    AND c.name IN ('FechaReserva', 'TurnoReserva')
ORDER BY c.name;

-- Ver claves foráneas (si se crearon)
SELECT 
    fk.name AS ForeignKeyName,
    OBJECT_NAME(fk.parent_object_id) AS ParentTable,
    OBJECT_NAME(fk.referenced_object_id) AS ReferencedTable,
    fk.delete_referential_action_desc AS OnDelete,
    fk.update_referential_action_desc AS OnUpdate
FROM sys.foreign_keys fk
WHERE fk.parent_object_id = OBJECT_ID('dbo.InvTelasReservadas')
   OR fk.referenced_object_id = OBJECT_ID('dbo.tej_inventario_telares');

PRINT '';
PRINT '=====================================================';
PRINT 'PROCESO COMPLETADO';
PRINT '=====================================================';
