-- Liberar Órdenes: el flag "asignar flogs" vive en CatCodificados (0 = ignorar flogs,
-- 1 = asignar/considerar flogs). ReqProgramaTejido sólo conserva FlogsId.

IF COL_LENGTH('dbo.CatCodificados', 'AsignarFlogs') IS NULL
    ALTER TABLE dbo.CatCodificados
        ADD AsignarFlogs BIT NOT NULL CONSTRAINT DF_CatCodificados_AsignarFlogs DEFAULT (0);
GO

-- Columnas obsoletas en ReqProgramaTejido (el flag ya no se guarda aquí).
DECLARE @sql NVARCHAR(MAX) = N'';

SELECT @sql = @sql + N'ALTER TABLE dbo.ReqProgramaTejido DROP CONSTRAINT ' + QUOTENAME(dc.name) + N';'
FROM sys.default_constraints dc
JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
WHERE dc.parent_object_id = OBJECT_ID('dbo.ReqProgramaTejido')
  AND c.name IN ('AplicaFlogs', 'AsignarFlags');

IF COL_LENGTH('dbo.ReqProgramaTejido', 'AplicaFlogs') IS NOT NULL
    SET @sql = @sql + N'ALTER TABLE dbo.ReqProgramaTejido DROP COLUMN AplicaFlogs;';

IF COL_LENGTH('dbo.ReqProgramaTejido', 'AsignarFlags') IS NOT NULL
    SET @sql = @sql + N'ALTER TABLE dbo.ReqProgramaTejido DROP COLUMN AsignarFlags;';

IF @sql <> N'' EXEC sp_executesql @sql;
