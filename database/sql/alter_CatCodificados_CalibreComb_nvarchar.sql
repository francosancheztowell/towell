-- CatCodificados.CalibreComb1..5 están en FLOAT, pero el programa / desarrolladores
-- guardan designaciones de hilo como "600/1T". Eso provoca:
--   Error converting data type nvarchar to float
-- ReqProgramaTejido y ReqModelosCodificados ya usan texto en esos campos.
-- Ejecutar en ProdTowel cuando se quiera persistir el calibre textual.

ALTER TABLE dbo.CatCodificados ALTER COLUMN CalibreComb1 NVARCHAR(50) NULL;
ALTER TABLE dbo.CatCodificados ALTER COLUMN CalibreComb2 NVARCHAR(50) NULL;
ALTER TABLE dbo.CatCodificados ALTER COLUMN CalibreComb3 NVARCHAR(50) NULL;
ALTER TABLE dbo.CatCodificados ALTER COLUMN CalibreComb4 NVARCHAR(50) NULL;
ALTER TABLE dbo.CatCodificados ALTER COLUMN CalibreComb5 NVARCHAR(50) NULL;
