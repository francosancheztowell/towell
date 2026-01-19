-- Script para agregar la columna TejInventarioTelaresId a InvTelasReservadas
-- Esta columna almacena el ID del registro específico de tej_inventario_telares
-- para identificar de manera única qué registro fue reservado

USE ProdTowel;
GO

-- Verificar si la columna ya existe antes de agregarla
IF COL_LENGTH('dbo.InvTelasReservadas', 'TejInventarioTelaresId') IS NULL
BEGIN
    ALTER TABLE dbo.InvTelasReservadas
    ADD TejInventarioTelaresId INT NULL;
    
    PRINT 'Columna TejInventarioTelaresId agregada exitosamente a InvTelasReservadas';
END
ELSE
BEGIN
    PRINT 'La columna TejInventarioTelaresId ya existe en InvTelasReservadas';
END
GO

-- Opcional: Crear un índice no agrupado para mejorar el rendimiento de las consultas
-- que busquen reservas por ID del registro de telar
IF NOT EXISTS (
    SELECT 1 
    FROM sys.indexes 
    WHERE name = 'IX_InvTelasReservadas_TejInventarioTelaresId' 
    AND object_id = OBJECT_ID('dbo.InvTelasReservadas')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_InvTelasReservadas_TejInventarioTelaresId
    ON dbo.InvTelasReservadas (TejInventarioTelaresId)
    WHERE TejInventarioTelaresId IS NOT NULL;
    
    PRINT 'Índice IX_InvTelasReservadas_TejInventarioTelaresId creado exitosamente';
END
ELSE
BEGIN
    PRINT 'El índice IX_InvTelasReservadas_TejInventarioTelaresId ya existe';
END
GO
