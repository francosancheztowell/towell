-- =====================================================
-- ÍNDICES PARA MEJORAR IDENTIFICACIÓN DE REGISTROS
-- =====================================================
-- Ejecutar estos scripts directamente en SQL Server Management Studio
-- o mediante una herramienta de administración de base de datos
-- =====================================================

USE ProdTowel;
GO

-- =====================================================
-- 1. ÍNDICE COMPUESTO PARA tej_inventario_telares
-- =====================================================
-- Este índice permite búsquedas rápidas de registros específicos
-- Identifica un registro por: no_telar + tipo + fecha + turno + status
-- INCLUDE agrega campos frecuentemente consultados para evitar lookups adicionales
-- =====================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status'
    AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
)
BEGIN
    -- Intentar crear con INCLUDE (SQL Server 2005+)
    BEGIN TRY
        CREATE NONCLUSTERED INDEX IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status
        ON dbo.tej_inventario_telares (no_telar, tipo, fecha, turno, status)
        INCLUDE (Reservado, Programado, no_julio, no_orden, metros);
        
        PRINT 'Índice IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status creado exitosamente con INCLUDE';
    END TRY
    BEGIN CATCH
        -- Si falla con INCLUDE, crear sin INCLUDE (versiones antiguas o problemas de permisos)
        BEGIN TRY
            CREATE NONCLUSTERED INDEX IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status
            ON dbo.tej_inventario_telares (no_telar, tipo, fecha, turno, status);
            
            PRINT 'Índice IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status creado exitosamente (sin INCLUDE)';
        END TRY
        BEGIN CATCH
            PRINT 'Error al crear índice IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status: ' + ERROR_MESSAGE();
        END CATCH
    END CATCH
END
ELSE
BEGIN
    PRINT 'El índice IX_tej_inventario_telares_no_telar_tipo_fecha_turno_status ya existe';
END
GO

-- =====================================================
-- 2. ÍNDICE COMPUESTO PARA InvTelasReservadas (búsqueda por fecha)
-- =====================================================
-- Permite búsquedas rápidas de reservas activas por:
-- NoTelarId + Tipo + Status + ProdDate (fecha de producción)
-- Esto ayuda a identificar qué reserva corresponde a qué registro específico
-- =====================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'IX_InvTelasReservadas_NoTelarId_Tipo_Status_ProdDate'
    AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
)
BEGIN
    BEGIN TRY
        CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_NoTelarId_Tipo_Status_ProdDate
        ON dbo.InvTelasReservadas (NoTelarId, Tipo, Status, ProdDate);
        
        PRINT 'Índice IX_InvTelasReservadas_NoTelarId_Tipo_Status_ProdDate creado exitosamente';
    END TRY
    BEGIN CATCH
        PRINT 'Error al crear índice IX_InvTelasReservadas_NoTelarId_Tipo_Status_ProdDate: ' + ERROR_MESSAGE();
    END CATCH
END
ELSE
BEGIN
    PRINT 'El índice IX_InvTelasReservadas_NoTelarId_Tipo_Status_ProdDate ya existe';
END
GO

-- =====================================================
-- 3. ÍNDICE COMPUESTO PARA InvTelasReservadas (búsqueda general)
-- =====================================================
-- Permite búsquedas rápidas de reservas activas por:
-- NoTelarId + Status (sin fecha)
-- Útil para verificar si un telar tiene reservas activas en general
-- =====================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'IX_InvTelasReservadas_NoTelarId_Status'
    AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
)
BEGIN
    BEGIN TRY
        -- Intentar crear con INCLUDE
        BEGIN TRY
            CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_NoTelarId_Status
            ON dbo.InvTelasReservadas (NoTelarId, Status)
            INCLUDE (Tipo, ProdDate);
            
            PRINT 'Índice IX_InvTelasReservadas_NoTelarId_Status creado exitosamente con INCLUDE';
        END TRY
        BEGIN CATCH
            -- Si falla, crear sin INCLUDE
            CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_NoTelarId_Status
            ON dbo.InvTelasReservadas (NoTelarId, Status);
            
            PRINT 'Índice IX_InvTelasReservadas_NoTelarId_Status creado exitosamente (sin INCLUDE)';
        END CATCH
    END TRY
    BEGIN CATCH
        PRINT 'Error al crear índice IX_InvTelasReservadas_NoTelarId_Status: ' + ERROR_MESSAGE();
    END CATCH
END
ELSE
BEGIN
    PRINT 'El índice IX_InvTelasReservadas_NoTelarId_Status ya existe';
END
GO

-- =====================================================
-- 4. ÍNDICE ADICIONAL PARA tej_inventario_telares (búsqueda por telar y tipo)
-- =====================================================
-- Útil para búsquedas que no requieren fecha/turno específicos
-- Por ejemplo: "¿Qué registros tiene este telar de tipo Rizo?"
-- =====================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'IX_tej_inventario_telares_no_telar_tipo_status'
    AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
)
BEGIN
    BEGIN TRY
        CREATE NONCLUSTERED INDEX IX_tej_inventario_telares_no_telar_tipo_status
        ON dbo.tej_inventario_telares (no_telar, tipo, status)
        INCLUDE (fecha, turno, Reservado, Programado);
        
        PRINT 'Índice IX_tej_inventario_telares_no_telar_tipo_status creado exitosamente';
    END TRY
    BEGIN CATCH
        BEGIN TRY
            CREATE NONCLUSTERED INDEX IX_tej_inventario_telares_no_telar_tipo_status
            ON dbo.tej_inventario_telares (no_telar, tipo, status);
            
            PRINT 'Índice IX_tej_inventario_telares_no_telar_tipo_status creado exitosamente (sin INCLUDE)';
        END TRY
        BEGIN CATCH
            PRINT 'Error al crear índice IX_tej_inventario_telares_no_telar_tipo_status: ' + ERROR_MESSAGE();
        END CATCH
    END CATCH
END
ELSE
BEGIN
    PRINT 'El índice IX_tej_inventario_telares_no_telar_tipo_status ya existe';
END
GO

-- =====================================================
-- VERIFICACIÓN DE ÍNDICES CREADOS
-- =====================================================

PRINT '';
PRINT '=====================================================';
PRINT 'VERIFICACIÓN DE ÍNDICES CREADOS';
PRINT '=====================================================';

-- Verificar índices en tej_inventario_telares
SELECT 
    i.name AS IndexName,
    i.type_desc AS IndexType,
    STRING_AGG(c.name, ', ') WITHIN GROUP (ORDER BY ic.key_ordinal) AS IndexColumns,
    CASE 
        WHEN i.has_filter = 1 THEN 'Sí'
        ELSE 'No'
    END AS HasFilter
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE i.object_id = OBJECT_ID('dbo.tej_inventario_telares')
    AND i.name LIKE 'IX_tej_inventario_telares%'
GROUP BY i.name, i.type_desc, i.has_filter
ORDER BY i.name;

-- Verificar índices en InvTelasReservadas
SELECT 
    i.name AS IndexName,
    i.type_desc AS IndexType,
    STRING_AGG(c.name, ', ') WITHIN GROUP (ORDER BY ic.key_ordinal) AS IndexColumns,
    CASE 
        WHEN i.has_filter = 1 THEN 'Sí'
        ELSE 'No'
    END AS HasFilter
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE i.object_id = OBJECT_ID('dbo.InvTelasReservadas')
    AND i.name LIKE 'IX_InvTelasReservadas%'
GROUP BY i.name, i.type_desc, i.has_filter
ORDER BY i.name;

PRINT '';
PRINT '=====================================================';
PRINT 'PROCESO COMPLETADO';
PRINT '=====================================================';
