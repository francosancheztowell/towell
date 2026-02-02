USE ProdTowel;
GO

/* =========================================================
   1) TABLA PADRE: EngProduccionFormulacion
   ========================================================= */

-- Agrega Id autoincremental si no existe
IF COL_LENGTH('dbo.EngProduccionFormulacion', 'Id') IS NULL
BEGIN
    ALTER TABLE dbo.EngProduccionFormulacion
    ADD Id INT IDENTITY(1,1) NOT NULL;

    -- Si la tabla no tenía PK, la ponemos sobre Id
    IF NOT EXISTS (
        SELECT 1
        FROM sys.key_constraints kc
        WHERE kc.[type] = 'PK'
          AND kc.parent_object_id = OBJECT_ID('dbo.EngProduccionFormulacion')
    )
    BEGIN
        ALTER TABLE dbo.EngProduccionFormulacion
        ADD CONSTRAINT PK_EngProduccionFormulacion PRIMARY KEY CLUSTERED (Id);
    END
END
GO


/* =========================================================
   2) TABLA HIJA: EngFormulacionLine
   ========================================================= */

-- Agrega Id autoincremental si no existe
IF COL_LENGTH('dbo.EngFormulacionLine', 'Id') IS NULL
BEGIN
    ALTER TABLE dbo.EngFormulacionLine
    ADD Id INT IDENTITY(1,1) NOT NULL;

    IF NOT EXISTS (
        SELECT 1
        FROM sys.key_constraints kc
        WHERE kc.[type] = 'PK'
          AND kc.parent_object_id = OBJECT_ID('dbo.EngFormulacionLine')
    )
    BEGIN
        ALTER TABLE dbo.EngFormulacionLine
        ADD CONSTRAINT PK_EngFormulacionLine PRIMARY KEY CLUSTERED (Id);
    END
END
GO

-- Agrega la columna FK hacia la tabla padre si no existe
IF COL_LENGTH('dbo.EngFormulacionLine', 'EngProduccionFormulacionId') IS NULL
BEGIN
    ALTER TABLE dbo.EngFormulacionLine
    ADD EngProduccionFormulacionId INT NULL;
END
GO

-- Poblar EngProduccionFormulacionId con los Ids correspondientes basados en Folio
-- Esto es necesario para los registros existentes
UPDATE EFL
SET EFL.EngProduccionFormulacionId = EPF.Id
FROM dbo.EngFormulacionLine EFL
INNER JOIN dbo.EngProduccionFormulacion EPF ON EFL.Folio = EPF.Folio
WHERE EFL.EngProduccionFormulacionId IS NULL;
GO

-- Crea la FK si no existe
IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys fk
    WHERE fk.name = 'FK_EngFormulacionLine_EngProduccionFormulacion'
      AND fk.parent_object_id = OBJECT_ID('dbo.EngFormulacionLine')
)
BEGIN
    ALTER TABLE dbo.EngFormulacionLine WITH CHECK
    ADD CONSTRAINT FK_EngFormulacionLine_EngProduccionFormulacion
    FOREIGN KEY (EngProduccionFormulacionId)
    REFERENCES dbo.EngProduccionFormulacion(Id);

    ALTER TABLE dbo.EngFormulacionLine CHECK CONSTRAINT FK_EngFormulacionLine_EngProduccionFormulacion;
END
GO


/* =========================================================
   3) Índices recomendados
   ========================================================= */

-- Índice para búsquedas por EngProduccionFormulacionId en detalle
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_EngFormulacionLine_EngProduccionFormulacionId'
      AND object_id = OBJECT_ID('dbo.EngFormulacionLine')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_EngFormulacionLine_EngProduccionFormulacionId
    ON dbo.EngFormulacionLine (EngProduccionFormulacionId);
END
GO

-- Índice para búsquedas por Folio en EngFormulacionLine (mantener compatibilidad)
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_EngFormulacionLine_Folio'
      AND object_id = OBJECT_ID('dbo.EngFormulacionLine')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_EngFormulacionLine_Folio
    ON dbo.EngFormulacionLine (Folio);
END
GO

-- Índice para búsquedas por Folio en EngProduccionFormulacion
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_EngProduccionFormulacion_Folio'
      AND object_id = OBJECT_ID('dbo.EngProduccionFormulacion')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_EngProduccionFormulacion_Folio
    ON dbo.EngProduccionFormulacion (Folio);
END
GO

PRINT 'Script ejecutado exitosamente. Estructura mejorada con IDs y Foreign Keys.';
GO
