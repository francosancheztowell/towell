-- Liberar Órdenes: el flag "asignar flogs" vive en CatCodificados (0 = la orden NO lleva
-- flog, 1 = sí lo lleva). La regla es que TODA orden lleva flog: el default es 1 y sólo
-- los renglones que empatan con el catálogo TwArticulosFelpas (item+talla, de cualquier
-- tipo de artículo) permiten al usuario bajarlo a 0 desde la pantalla.
-- ReqProgramaTejido sólo conserva FlogsId.

IF COL_LENGTH('dbo.CatCodificados', 'AsignarFlogs') IS NULL
    ALTER TABLE dbo.CatCodificados
        ADD AsignarFlogs BIT NOT NULL CONSTRAINT DF_CatCodificados_AsignarFlogs DEFAULT ((1));
GO

-- Si la columna ya existía con DEFAULT (0), se recrea el constraint con (1). Idempotente:
-- el nombre real se busca en el catálogo por si difiere de DF_CatCodificados_AsignarFlogs.
DECLARE @ConstraintName NVARCHAR(200);

SELECT @ConstraintName = dc.name
FROM sys.default_constraints dc
JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
WHERE dc.parent_object_id = OBJECT_ID('dbo.CatCodificados')
  AND c.name = 'AsignarFlogs';

IF @ConstraintName IS NOT NULL
    EXEC('ALTER TABLE dbo.CatCodificados DROP CONSTRAINT ' + QUOTENAME(@ConstraintName));

ALTER TABLE dbo.CatCodificados
    ADD CONSTRAINT DF_CatCodificados_AsignarFlogs DEFAULT ((1)) FOR AsignarFlogs;
GO

-- El DEFAULT sólo aplica a INSERT: las filas creadas cuando el default era 0 conservan 0.
-- Ejecutar una sola vez si se quiere alinear el catálogo histórico a la nueva regla:
-- UPDATE dbo.CatCodificados SET AsignarFlogs = 1 WHERE AsignarFlogs = 0;
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
