-- Órdenes de Trabajo (Mecánicos): tipo de falla capturado a mano en la cabecera.
-- La descripción de la falla (Falla) pasa a ser opcional; ya es NULL en la tabla.
-- Idempotente y compatible con SQL Server 2008 R2.
--
-- Correr ANTES de subir el código que guarda TipoFalla.

IF COL_LENGTH('dbo.MecOrdenTrabajoTable', 'TipoFalla') IS NULL
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoTable ADD TipoFalla VARCHAR(100) NULL;
END;

-- Verificación
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'MecOrdenTrabajoTable'
  AND COLUMN_NAME IN ('Falla', 'TipoFalla');
