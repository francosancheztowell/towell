-- Script para agregar columnas MermaGoma y Merma a la tabla EngProgramaEngomado
-- Ejecutar este script en SQL Server Management Studio o en tu cliente SQL

USE ProdTowel;
GO

-- Verificar si las columnas ya existen antes de agregarlas
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.EngProgramaEngomado') AND name = 'MermaGoma')
BEGIN
    ALTER TABLE dbo.EngProgramaEngomado
    ADD MermaGoma FLOAT NULL;
    PRINT 'Columna MermaGoma agregada exitosamente';
END
ELSE
BEGIN
    PRINT 'La columna MermaGoma ya existe';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.EngProgramaEngomado') AND name = 'Merma')
BEGIN
    ALTER TABLE dbo.EngProgramaEngomado
    ADD Merma FLOAT NULL;
    PRINT 'Columna Merma agregada exitosamente';
END
ELSE
BEGIN
    PRINT 'La columna Merma ya existe';
END
GO

-- Verificar que las columnas fueron agregadas
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'EngProgramaEngomado'
    AND COLUMN_NAME IN ('MermaGoma', 'Merma')
ORDER BY COLUMN_NAME;
GO
