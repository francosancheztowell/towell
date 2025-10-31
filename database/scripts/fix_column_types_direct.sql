-- Script para cambiar tipos de columnas de NUMERIC/DECIMAL a VARCHAR(50)
-- en la tabla ReqModelosCodificados para aceptar valores como "5/3"

USE [towell];
GO

-- Desactivar constraint checks temporalmente
ALTER TABLE [ReqModelosCodificados] NOCHECK CONSTRAINT ALL;
GO

-- Lista de columnas a cambiar de numeric/decimal a varchar(50)
-- Basado en los errores reportados
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [Pedido] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [CalibreTrama] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [CalibreTrama2] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [Obs] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [CalibreRizo] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [CalibreRizo2] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [CalibrePie] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [CalibrePie2] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [Comb3] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [Obs3] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [Comb4] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [Obs4] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [CalTramaFondoC1] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [CalibreComb1] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [Total] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [KGDia] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [Densidad] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [PzasDiaPasadas] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [PzasDiaFormula] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [DIF] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [EFIC] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [Rev] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [TIRAS] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [PASADAS] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [ColumCT] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [ColumCU] VARCHAR(50);
ALTER TABLE [ReqModelosCodificados] ALTER COLUMN [ColumCV] VARCHAR(50);

-- Reactivar constraint checks
ALTER TABLE [ReqModelosCodificados] CHECK CONSTRAINT ALL;
GO

-- Verificar que los cambios se aplicaron
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'ReqModelosCodificados'
AND COLUMN_NAME IN (
    'Pedido', 'CalibreTrama', 'CalibreTrama2', 'Obs', 'CalibreRizo', 'CalibreRizo2',
    'CalibrePie', 'CalibrePie2', 'Comb3', 'Obs3', 'Comb4', 'Obs4', 'CalTramaFondoC1',
    'CalibreComb1', 'Total', 'KGDia', 'Densidad', 'PzasDiaPasadas', 'PzasDiaFormula',
    'DIF', 'EFIC', 'Rev', 'TIRAS', 'PASADAS', 'ColumCT', 'ColumCU', 'ColumCV'
)
ORDER BY COLUMN_NAME;
GO
