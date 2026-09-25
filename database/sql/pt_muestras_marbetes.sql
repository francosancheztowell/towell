-- =============================================================================
-- PT-02 · decisión 01.3 — Muestras, capacidad "marbetes": alternativa A (paridad física aditiva)
-- Desbloquea Liberar en Muestras (hoy asigna NoMarbete/RollosProgramados y SQL Server responde
-- "Invalid column name") y el editor de marbetes. Ver .planning/phases/02-containment-read/02-MUESTRAS-LIBERAR.md.
--
-- NOTA PARA EL DBA
--   * NO es una migración de Laravel: el historial de migrations no coincide con live.
--     Se corre a mano en SSMS contra ProdTowel, primero en staging.
--   * Aditivo: solo ALTER TABLE ... ADD <col> NULL, sin default (no reescribe filas).
--     MuestrasPrograma tenía 0 filas en el research (re-verificar con RS6).
--   * Tipo, longitud y collation se COPIAN de dbo.ReqProgramaTejido vía sys.columns:
--     no hay tipos escritos a mano. Si la columna no existe en Programa, aborta sin cambios.
--   * Idempotente: el preflight IF COL_LENGTH(...) IS NULL salta lo que ya existe.
--   * Antes y después: correr .planning/phases/01-guardrails/sql/01-schema-fisico.sql
--     (RS1/RS2) y `php artisan planeacion:programa-tejido-health --json`; guardar ambas salidas.
--   * Backup previo de la base (o al menos de dbo.MuestrasPrograma).
--   * Al terminar: quitar las columnas agregadas de
--     config/planeacion.php → superficies.muestras.columnas_ausentes (lo hace la sesión PT).
-- =============================================================================
SET XACT_ABORT ON;
SET NOCOUNT ON;

IF OBJECT_ID('dbo.MuestrasPrograma') IS NULL OR OBJECT_ID('dbo.ReqProgramaTejido') IS NULL
    THROW 50000, N'Falta dbo.MuestrasPrograma o dbo.ReqProgramaTejido: base equivocada. Abortado.', 1;

BEGIN TRANSACTION;

DECLARE @tipo nvarchar(300), @sql nvarchar(max);
DECLARE @tipos TABLE (col sysname PRIMARY KEY, tipo nvarchar(300) NOT NULL);

INSERT INTO @tipos (col, tipo)
SELECT c.name,
        CASE
            WHEN t.name IN ('varchar', 'char', 'varbinary', 'binary')
                THEN t.name + '(' + CASE WHEN c.max_length = -1 THEN 'MAX' ELSE CAST(c.max_length AS varchar(10)) END + ')'
            WHEN t.name IN ('nvarchar', 'nchar')
                THEN t.name + '(' + CASE WHEN c.max_length = -1 THEN 'MAX' ELSE CAST(c.max_length / 2 AS varchar(10)) END + ')'
            WHEN t.name IN ('decimal', 'numeric')
                THEN t.name + '(' + CAST(c.precision AS varchar(3)) + ',' + CAST(c.scale AS varchar(3)) + ')'
            WHEN t.name IN ('datetime2', 'time', 'datetimeoffset')
                THEN t.name + '(' + CAST(c.scale AS varchar(3)) + ')'
            ELSE t.name
        END
        + CASE WHEN c.collation_name IS NOT NULL THEN ' COLLATE ' + c.collation_name ELSE '' END
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.ReqProgramaTejido')
  AND c.name IN (N'NoMarbete', N'RollosProgramados', N'ProduccionMarbetes');

IF COL_LENGTH('dbo.MuestrasPrograma', 'NoMarbete') IS NULL
BEGIN
    SET @tipo = (SELECT tipo FROM @tipos WHERE col = N'NoMarbete');
    IF @tipo IS NULL THROW 50001, N'ReqProgramaTejido.NoMarbete no existe: no se puede copiar su tipo. Abortado sin cambios.', 1;
    SET @sql = N'ALTER TABLE dbo.MuestrasPrograma ADD NoMarbete ' + @tipo + N' NULL;';
    EXEC sys.sp_executesql @sql;
    PRINT N'Agregada MuestrasPrograma.NoMarbete ' + @tipo + N' NULL';
END
ELSE
    PRINT N'MuestrasPrograma.NoMarbete ya existe: sin cambios';

IF COL_LENGTH('dbo.MuestrasPrograma', 'RollosProgramados') IS NULL
BEGIN
    SET @tipo = (SELECT tipo FROM @tipos WHERE col = N'RollosProgramados');
    IF @tipo IS NULL THROW 50001, N'ReqProgramaTejido.RollosProgramados no existe: no se puede copiar su tipo. Abortado sin cambios.', 1;
    SET @sql = N'ALTER TABLE dbo.MuestrasPrograma ADD RollosProgramados ' + @tipo + N' NULL;';
    EXEC sys.sp_executesql @sql;
    PRINT N'Agregada MuestrasPrograma.RollosProgramados ' + @tipo + N' NULL';
END
ELSE
    PRINT N'MuestrasPrograma.RollosProgramados ya existe: sin cambios';

IF COL_LENGTH('dbo.MuestrasPrograma', 'ProduccionMarbetes') IS NULL
BEGIN
    SET @tipo = (SELECT tipo FROM @tipos WHERE col = N'ProduccionMarbetes');
    IF @tipo IS NULL THROW 50001, N'ReqProgramaTejido.ProduccionMarbetes no existe: no se puede copiar su tipo. Abortado sin cambios.', 1;
    SET @sql = N'ALTER TABLE dbo.MuestrasPrograma ADD ProduccionMarbetes ' + @tipo + N' NULL;';
    EXEC sys.sp_executesql @sql;
    PRINT N'Agregada MuestrasPrograma.ProduccionMarbetes ' + @tipo + N' NULL';
END
ELSE
    PRINT N'MuestrasPrograma.ProduccionMarbetes ya existe: sin cambios';

COMMIT TRANSACTION;

-- Verificación: las columnas deben existir con el mismo tipo en ambas tablas.
SELECT OBJECT_NAME(c.object_id) AS tabla, c.name AS columna, t.name AS tipo, c.max_length, c.precision, c.scale, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id IN (OBJECT_ID('dbo.ReqProgramaTejido'), OBJECT_ID('dbo.MuestrasPrograma'))
  AND c.name IN (N'NoMarbete', N'RollosProgramados', N'ProduccionMarbetes')
ORDER BY c.name, tabla;

-- =============================================================================
-- ROLLBACK (no se ejecuta: descomentar y correr aparte si hay que revertir)
-- Solo quita una columna si sigue vacía en todas las filas; si ya tiene datos, se
-- detiene esa columna y hay que decidir con el owner antes de perderlos.
-- =============================================================================
/*
SET XACT_ABORT ON;
BEGIN TRANSACTION;
IF COL_LENGTH('dbo.MuestrasPrograma', 'NoMarbete') IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM dbo.MuestrasPrograma WHERE NoMarbete IS NOT NULL)
    ALTER TABLE dbo.MuestrasPrograma DROP COLUMN NoMarbete;
IF COL_LENGTH('dbo.MuestrasPrograma', 'RollosProgramados') IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM dbo.MuestrasPrograma WHERE RollosProgramados IS NOT NULL)
    ALTER TABLE dbo.MuestrasPrograma DROP COLUMN RollosProgramados;
IF COL_LENGTH('dbo.MuestrasPrograma', 'ProduccionMarbetes') IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM dbo.MuestrasPrograma WHERE ProduccionMarbetes IS NOT NULL)
    ALTER TABLE dbo.MuestrasPrograma DROP COLUMN ProduccionMarbetes;
COMMIT TRANSACTION;
*/
