-- =====================================================
-- CLAVES FORÁNEAS PARA MEJORAR INTEGRIDAD REFERENCIAL
-- =====================================================
-- Ejecutar estos scripts directamente en SQL Server Management Studio
-- o mediante una herramienta de administración de base de datos
-- =====================================================

USE ProdTowel;
GO

-- =====================================================
-- 1. CREAR ÍNDICE ÚNICO COMPUESTO EN tej_inventario_telares
-- =====================================================
-- Este índice único permite crear una FK que referencie
-- la combinación de no_telar + tipo + fecha + turno + status
-- =====================================================

-- Primero, eliminar el índice anterior si existe (por si acaso)
IF EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status'
    AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
)
BEGIN
    DROP INDEX UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status
    ON dbo.tej_inventario_telares;
    PRINT 'Índice único anterior eliminado';
END
GO

-- Crear índice único compuesto (esto permite FK)
-- NOTA: Esto fallará si hay duplicados, así que primero verificamos
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status'
    AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
)
BEGIN
    -- Verificar si hay duplicados antes de crear el índice único
    IF NOT EXISTS (
        SELECT no_telar, tipo, fecha, turno, status, COUNT(*) as cnt
        FROM dbo.tej_inventario_telares
        WHERE status = 'Activo'
        GROUP BY no_telar, tipo, fecha, turno, status
        HAVING COUNT(*) > 1
    )
    BEGIN
        BEGIN TRY
            CREATE UNIQUE NONCLUSTERED INDEX UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status
            ON dbo.tej_inventario_telares (no_telar, tipo, fecha, turno, status)
            WHERE status = 'Activo'; -- Índice filtrado solo para registros activos
            
            PRINT 'Índice único UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status creado exitosamente';
        END TRY
        BEGIN CATCH
            PRINT 'Error al crear índice único: ' + ERROR_MESSAGE();
            PRINT 'NOTA: Puede haber registros duplicados. Verifica los datos primero.';
        END CATCH
    END
    ELSE
    BEGIN
        PRINT 'ADVERTENCIA: Hay registros duplicados. No se puede crear el índice único.';
        PRINT 'Ejecuta esta consulta para ver los duplicados:';
        PRINT 'SELECT no_telar, tipo, fecha, turno, status, COUNT(*) as cnt FROM dbo.tej_inventario_telares WHERE status = ''Activo'' GROUP BY no_telar, tipo, fecha, turno, status HAVING COUNT(*) > 1';
    END
END
ELSE
BEGIN
    PRINT 'El índice único UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status ya existe';
END
GO

-- =====================================================
-- 2. AGREGAR COLUMNAS DE REFERENCIA A InvTelasReservadas
-- =====================================================
-- Para crear una FK más precisa, necesitamos agregar campos
-- que referencien directamente al registro específico
-- =====================================================

-- Agregar columna fecha_reserva (opcional, para mejor referencia)
IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('dbo.InvTelasReservadas')
    AND name = 'FechaReserva'
)
BEGIN
    BEGIN TRY
        ALTER TABLE dbo.InvTelasReservadas
        ADD FechaReserva DATE NULL;
        
        -- Si ProdDate existe, copiar la fecha a FechaReserva
        UPDATE dbo.InvTelasReservadas
        SET FechaReserva = CAST(ProdDate AS DATE)
        WHERE ProdDate IS NOT NULL;
        
        PRINT 'Columna FechaReserva agregada exitosamente';
    END TRY
    BEGIN CATCH
        PRINT 'Error al agregar columna FechaReserva: ' + ERROR_MESSAGE();
    END CATCH
END
ELSE
BEGIN
    PRINT 'La columna FechaReserva ya existe';
END
GO

-- Agregar columna TurnoReserva (opcional, para mejor referencia)
IF NOT EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('dbo.InvTelasReservadas')
    AND name = 'TurnoReserva'
)
BEGIN
    BEGIN TRY
        ALTER TABLE dbo.InvTelasReservadas
        ADD TurnoReserva INT NULL;
        
        PRINT 'Columna TurnoReserva agregada exitosamente';
        PRINT 'NOTA: Deberás actualizar esta columna manualmente o desde la aplicación';
    END TRY
    BEGIN CATCH
        PRINT 'Error al agregar columna TurnoReserva: ' + ERROR_MESSAGE();
    END CATCH
END
ELSE
BEGIN
    PRINT 'La columna TurnoReserva ya existe';
END
GO

-- =====================================================
-- 3. CREAR CLAVE FORÁNEA SIMPLE (NoTelarId -> no_telar)
-- =====================================================
-- Esta FK básica asegura que NoTelarId siempre exista en tej_inventario_telares
-- =====================================================

-- Primero, crear un índice único en no_telar si no existe (requerido para FK)
-- NOTA: Esto solo funcionará si no hay duplicados en no_telar
-- Como puede haber múltiples registros con el mismo no_telar, 
-- esta FK simple puede no ser posible. Mejor usar la FK compuesta siguiente.

-- =====================================================
-- 4. CREAR CLAVE FORÁNEA COMPUESTA (RECOMENDADA)
-- =====================================================
-- Esta FK asegura que la combinación NoTelarId + Tipo + FechaReserva + TurnoReserva
-- siempre exista en tej_inventario_telares
-- =====================================================

-- Eliminar FK anterior si existe
IF EXISTS (
    SELECT 1 FROM sys.foreign_keys 
    WHERE name = 'FK_InvTelasReservadas_tej_inventario_telares'
    AND parent_object_id = OBJECT_ID('dbo.InvTelasReservadas')
)
BEGIN
    ALTER TABLE dbo.InvTelasReservadas
    DROP CONSTRAINT FK_InvTelasReservadas_tej_inventario_telares;
    
    PRINT 'Clave foránea anterior eliminada';
END
GO

-- Crear FK compuesta (solo si las columnas existen y hay índice único)
IF EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'UQ_tej_inventario_telares_no_telar_tipo_fecha_turno_status'
    AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
)
AND EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('dbo.InvTelasReservadas')
    AND name = 'FechaReserva'
)
AND EXISTS (
    SELECT 1 FROM sys.columns 
    WHERE object_id = OBJECT_ID('dbo.InvTelasReservadas')
    AND name = 'TurnoReserva'
)
BEGIN
    BEGIN TRY
        -- Crear FK usando el índice único
        -- NOTA: SQL Server requiere que las columnas de la FK coincidan exactamente
        -- con las columnas del índice único en el mismo orden
        
        -- Primero, crear un índice en InvTelasReservadas para la FK
        IF NOT EXISTS (
            SELECT 1 FROM sys.indexes 
            WHERE name = 'IX_InvTelasReservadas_NoTelarId_Tipo_FechaReserva_TurnoReserva'
            AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
        )
        BEGIN
            CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_NoTelarId_Tipo_FechaReserva_TurnoReserva
            ON dbo.InvTelasReservadas (NoTelarId, Tipo, FechaReserva, TurnoReserva);
            
            PRINT 'Índice en InvTelasReservadas creado para FK';
        END
        
        -- Crear la FK (comentada porque requiere que todas las reservas tengan FechaReserva y TurnoReserva)
        -- Descomenta esto después de asegurar que todos los registros tienen estos valores
        
        /*
        ALTER TABLE dbo.InvTelasReservadas
        ADD CONSTRAINT FK_InvTelasReservadas_tej_inventario_telares
        FOREIGN KEY (NoTelarId, Tipo, FechaReserva, TurnoReserva)
        REFERENCES dbo.tej_inventario_telares (no_telar, tipo, fecha, turno)
        ON DELETE CASCADE  -- Si se elimina el telar, eliminar las reservas
        ON UPDATE CASCADE; -- Si se actualiza el telar, actualizar las reservas
        
        PRINT 'Clave foránea FK_InvTelasReservadas_tej_inventario_telares creada exitosamente';
        */
        
        PRINT 'NOTA: La FK está comentada. Descomenta después de asegurar que todos los registros tienen FechaReserva y TurnoReserva.';
        
    END TRY
    BEGIN CATCH
        PRINT 'Error al crear clave foránea: ' + ERROR_MESSAGE();
    END CATCH
END
ELSE
BEGIN
    PRINT 'No se puede crear la FK: faltan requisitos (índice único o columnas FechaReserva/TurnoReserva)';
END
GO

-- =====================================================
-- 5. FK ALTERNATIVA SIMPLE (Solo NoTelarId)
-- =====================================================
-- Esta FK simple asegura que NoTelarId exista en tej_inventario_telares
-- pero NO garantiza el registro específico (puede haber múltiples)
-- =====================================================

-- Crear índice único en no_telar (solo si no hay duplicados)
-- NOTA: Esto puede fallar si hay múltiples registros con el mismo no_telar
-- Por eso está comentado - solo úsalo si estás seguro de que no hay duplicados

/*
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'UQ_tej_inventario_telares_no_telar'
    AND object_id = OBJECT_ID('dbo.tej_inventario_telares')
)
BEGIN
    -- Verificar duplicados primero
    IF NOT EXISTS (
        SELECT no_telar, COUNT(*) as cnt
        FROM dbo.tej_inventario_telares
        WHERE status = 'Activo'
        GROUP BY no_telar
        HAVING COUNT(*) > 1
    )
    BEGIN
        CREATE UNIQUE NONCLUSTERED INDEX UQ_tej_inventario_telares_no_telar
        ON dbo.tej_inventario_telares (no_telar)
        WHERE status = 'Activo';
        
        -- Crear FK simple
        ALTER TABLE dbo.InvTelasReservadas
        ADD CONSTRAINT FK_InvTelasReservadas_NoTelarId
        FOREIGN KEY (NoTelarId)
        REFERENCES dbo.tej_inventario_telares (no_telar)
        ON DELETE CASCADE
        ON UPDATE CASCADE;
        
        PRINT 'FK simple creada exitosamente';
    END
    ELSE
    BEGIN
        PRINT 'No se puede crear FK simple: hay múltiples registros con el mismo no_telar';
    END
END
*/
GO

-- =====================================================
-- VERIFICACIÓN DE CLAVES FORÁNEAS CREADAS
-- =====================================================

PRINT '';
PRINT '=====================================================';
PRINT 'VERIFICACIÓN DE CLAVES FORÁNEAS';
PRINT '=====================================================';

SELECT 
    fk.name AS ForeignKeyName,
    OBJECT_NAME(fk.parent_object_id) AS ParentTable,
    OBJECT_NAME(fk.referenced_object_id) AS ReferencedTable,
    COL_NAME(fc.parent_object_id, fc.parent_column_id) AS ParentColumn,
    COL_NAME(fc.referenced_object_id, fc.referenced_column_id) AS ReferencedColumn,
    fk.delete_referential_action_desc AS OnDelete,
    fk.update_referential_action_desc AS OnUpdate
FROM sys.foreign_keys fk
INNER JOIN sys.foreign_key_columns fc ON fk.object_id = fc.constraint_object_id
WHERE fk.parent_object_id = OBJECT_ID('dbo.InvTelasReservadas')
   OR fk.referenced_object_id = OBJECT_ID('dbo.tej_inventario_telares')
ORDER BY fk.name;

PRINT '';
PRINT '=====================================================';
PRINT 'PROCESO COMPLETADO';
PRINT '=====================================================';
