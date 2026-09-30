-- 19-03 (HANDOFF 16 C3): llave numérica para el catálogo de Comentarios de Atadores.
--
-- Hoy la llave de la ruta es Nota1 (texto libre, PK): una nota con "/" da 404 al editar o
-- eliminar. Con esta columna la pantalla pasa sola a /atadores/catalogos/comentarios/id/{Id};
-- las URLs por Nota1 siguen funcionando. Compatible con SQL Server 2008 R2. Idempotente.
--
-- Correr en ProdTowel (SSMS o sqlcmd: usa GO) y después: php artisan cache:clear (la app cachea 1 h si la columna existe).

IF COL_LENGTH('dbo.AtaComentarios', 'Id') IS NULL
BEGIN
    IF EXISTS (SELECT 1 FROM sys.identity_columns WHERE object_id = OBJECT_ID('dbo.AtaComentarios'))
        -- Una tabla solo puede tener una columna IDENTITY: si ya hay otra, avisar y no tocar nada.
        RAISERROR('dbo.AtaComentarios ya tiene una columna IDENTITY con otro nombre; revisar antes de agregar Id.', 16, 1);
    ELSE
        ALTER TABLE dbo.AtaComentarios ADD Id INT IDENTITY(1,1) NOT NULL;
END;
GO

-- EXEC: el CREATE INDEX solo se compila si la columna existe (si el RAISERROR de arriba saltó, no).
IF COL_LENGTH('dbo.AtaComentarios', 'Id') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_AtaComentarios_Id' AND object_id = OBJECT_ID('dbo.AtaComentarios'))
    EXEC('CREATE UNIQUE INDEX UX_AtaComentarios_Id ON dbo.AtaComentarios (Id);');
GO

-- Verificación (lote aparte: si la columna no existía al compilar, fallaría todo el lote)
SELECT TOP 5 Id, Nota1, Nota2 FROM dbo.AtaComentarios ORDER BY Id;
