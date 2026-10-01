-- Índices para consultas lentas de Pulse (30-sep-2026).
--
-- Las tablas son chicas (cientos a miles de filas); lo lento eran esperas por bloqueo.
-- Sin índice, un WHERE Folio = ? recorre la tabla entera y se topa con cualquier fila
-- bloqueada por otra transacción, aunque no sea la que busca. Con índice solo toca su rango.
--   - TejEficiencia (heap): CortesEficienciaController lee y actualiza por Folio + Turno.
--   - TejMarcas (heap): MarcasController filtra por Folio, Status = 'En Proceso' y Date/Turno.
--   - ReqProgramaTejidoLine: el observer de ReqProgramaTejido borra por ProgramaId.
--   - AtaMontadoTelas (heap): ProgramaAtadoresListado agrupa por NoJulio, NoProduccion y
--     se une por Id; INCLUDE Estatus para no volver a la tabla.
--   - EngProgramaEngomado tiene tres índices sobre Folio (dos UNIQUE + uno normal): se deja
--     un UNIQUE y se borran los otros dos. Ninguna FK los referencia (verificado en ProdTowel).
--     Los UNIQUE tienen nombre autogenerado, por eso se buscan por columnas.
--
-- Standard Edition: sin ONLINE = ON. Con estas tamaños cada índice tarda menos de un segundo.
-- Idempotente: se puede correr más de una vez.

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_TejEficiencia_Folio_Turno' AND object_id = OBJECT_ID(N'dbo.TejEficiencia'))
    CREATE NONCLUSTERED INDEX IX_TejEficiencia_Folio_Turno ON dbo.TejEficiencia (Folio, Turno);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_TejMarcas_Folio' AND object_id = OBJECT_ID(N'dbo.TejMarcas'))
    CREATE NONCLUSTERED INDEX IX_TejMarcas_Folio ON dbo.TejMarcas (Folio);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_TejMarcas_Status_Date' AND object_id = OBJECT_ID(N'dbo.TejMarcas'))
    CREATE NONCLUSTERED INDEX IX_TejMarcas_Status_Date ON dbo.TejMarcas (Status, Date) INCLUDE (Turno, Folio);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ReqProgramaTejidoLine_ProgramaId' AND object_id = OBJECT_ID(N'dbo.ReqProgramaTejidoLine'))
    CREATE NONCLUSTERED INDEX IX_ReqProgramaTejidoLine_ProgramaId ON dbo.ReqProgramaTejidoLine (ProgramaId);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_AtaMontadoTelas_NoJulio_NoProduccion' AND object_id = OBJECT_ID(N'dbo.AtaMontadoTelas'))
    CREATE NONCLUSTERED INDEX IX_AtaMontadoTelas_NoJulio_NoProduccion ON dbo.AtaMontadoTelas (NoJulio, NoProduccion) INCLUDE (Id, Estatus);
GO

-- EngProgramaEngomado: índices duplicados sobre Folio.
DECLARE @sql nvarchar(max) = N'';

-- Índices (no PK) cuya única columna llave es Folio y que no tienen INCLUDE.
;WITH folio AS (
    SELECT i.name, i.is_unique_constraint, i.is_unique
    FROM sys.indexes i
    WHERE i.object_id = OBJECT_ID(N'dbo.EngProgramaEngomado')
      AND i.is_primary_key = 0
      AND i.index_id > 0
      AND (SELECT COUNT(*) FROM sys.index_columns ic WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id) = 1
      AND EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
                  WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND c.name = N'Folio')
),
conservar AS (
    -- El UNIQUE de menor nombre se queda (garantiza que el folio no se repita).
    SELECT TOP 1 name FROM folio WHERE is_unique = 1 ORDER BY name
)
SELECT @sql = @sql +
    CASE WHEN f.is_unique_constraint = 1
         THEN N'ALTER TABLE dbo.EngProgramaEngomado DROP CONSTRAINT ' + QUOTENAME(f.name) + N';'
         ELSE N'DROP INDEX ' + QUOTENAME(f.name) + N' ON dbo.EngProgramaEngomado;'
    END + CHAR(10)
FROM folio f
WHERE EXISTS (SELECT 1 FROM conservar)              -- sin UNIQUE no se borra nada
  AND f.name NOT IN (SELECT name FROM conservar)
  AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys fk  -- por si una FK depende de él
                  JOIN sys.indexes ri ON ri.object_id = fk.referenced_object_id AND ri.index_id = fk.key_index_id
                  WHERE fk.referenced_object_id = OBJECT_ID(N'dbo.EngProgramaEngomado') AND ri.name = f.name);

PRINT @sql;
EXEC sp_executesql @sql;
GO
