-- InventBatchId con la misma longitud que InventSerialId (campos similares).
-- Ejecutar en la base donde está InvTelasReservadas.

IF EXISTS (SELECT * FROM sys.tables WHERE name = 'InvTelasReservadas')
BEGIN
    ALTER TABLE dbo.InvTelasReservadas
    ALTER COLUMN InventBatchId NVARCHAR(30) NULL;
    PRINT 'InventBatchId alterado a NVARCHAR(30) (igual que InventSerialId).';
END
ELSE
    PRINT 'Tabla InvTelasReservadas no encontrada.';
GO
