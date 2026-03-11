-- Script para agregar columna AX (BIT NULL) a EngProduccionFormulacion
-- AX=1: no se permite editar la formulación
-- AX=0 o NULL: se permite editar
-- Ejecutar en SQL Server Management Studio o cliente SQL si la migración falla

USE ProdTowel;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.EngProduccionFormulacion') AND name = 'AX')
BEGIN
    ALTER TABLE dbo.EngProduccionFormulacion
    ADD AX BIT NULL;
    PRINT 'Columna AX agregada exitosamente';
END
ELSE
BEGIN
    PRINT 'La columna AX ya existe';
END
GO
