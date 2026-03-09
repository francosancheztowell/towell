-- Script para agregar columna Impresion (BIT NULL) a EngProduccionEngomado
-- Impresion=0 o NULL: no impreso en producción parcial
-- Impresion=1: ya impreso en producción parcial
-- Ejecutar en SQL Server Management Studio o cliente SQL

USE ProdTowel;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.EngProduccionEngomado') AND name = 'Impresion')
BEGIN
    ALTER TABLE dbo.EngProduccionEngomado
    ADD Impresion BIT NULL;
    PRINT 'Columna Impresion agregada exitosamente';
END
ELSE
BEGIN
    PRINT 'La columna Impresion ya existe';
END
GO
