-- =============================================================================
-- PT-02 · decisión 01.3 §4.6 — Muestras, capacidad "longitudes": alternativa A
-- Ensancha las 11 columnas de dbo.MuestrasPrograma que son más cortas que en
-- dbo.ReqProgramaTejido, para que StringTruncator (que usa los límites de Programa)
-- deje de producir SQLSTATE 22001 en Muestras.
--
-- NOTA PARA EL DBA
--   * NO es una migración de Laravel. Se corre a mano en SSMS, primero en staging.
--   * Solo ENSANCHA: ALTER COLUMN al tipo/longitud de ReqProgramaTejido (leído de
--     sys.columns) cuando la de Muestras es más corta y ambas son texto del mismo tipo.
--     Conserva la nulabilidad y la collation actuales de Muestras. Nunca acorta.
--   * Si una columna está en un índice o constraint, ALTER COLUMN falla y, con
--     XACT_ABORT, se revierte TODO el script: revisar RS3 de 01-schema-fisico.sql y
--     avisar a la sesión PT.
--   * Antes de correrlo: guardar el result set "ANTES" (es el insumo del rollback) y
--     la salida de 01-schema-fisico.sql RS2; después, llenar config/planeacion.php →
--     superficies.muestras.longitudes con las longitudes nuevas (lo hace la sesión PT).
--   * Backup previo de dbo.MuestrasPrograma.
-- =============================================================================
SET XACT_ABORT ON;
SET NOCOUNT ON;

IF OBJECT_ID('dbo.MuestrasPrograma') IS NULL OR OBJECT_ID('dbo.ReqProgramaTejido') IS NULL
    THROW 50000, N'Falta dbo.MuestrasPrograma o dbo.ReqProgramaTejido: base equivocada. Abortado.', 1;

DECLARE @columnas TABLE (col sysname PRIMARY KEY);
INSERT INTO @columnas (col) VALUES (N'CalendarioId'), (N'FlogsId'), (N'NombreProyecto'), (N'CustName'), (N'AplicacionId'), (N'Observaciones'), (N'ColorTrama'), (N'Prioridad'), (N'CombinaTram'), (N'BomId'), (N'BomName');

-- ANTES: guardar este result set (insumo del rollback).
SELECT N'ANTES' AS momento, m.name AS columna, tm.name AS tipo_muestras, m.max_length AS bytes_muestras,
       tp.name AS tipo_programa, p.max_length AS bytes_programa, m.is_nullable, m.collation_name
FROM @columnas x
LEFT JOIN sys.columns m ON m.object_id = OBJECT_ID('dbo.MuestrasPrograma') AND m.name = x.col
LEFT JOIN sys.types tm ON tm.user_type_id = m.user_type_id
LEFT JOIN sys.columns p ON p.object_id = OBJECT_ID('dbo.ReqProgramaTejido') AND p.name = x.col
LEFT JOIN sys.types tp ON tp.user_type_id = p.user_type_id
ORDER BY x.col;

BEGIN TRANSACTION;

DECLARE @col sysname, @sql nvarchar(max);
DECLARE cur CURSOR LOCAL FAST_FORWARD FOR
    SELECT
        m.name,
        N'ALTER TABLE dbo.MuestrasPrograma ALTER COLUMN ' + QUOTENAME(m.name) + N' '
        + tp.name + N'(' + CASE
            WHEN p.max_length = -1 THEN N'MAX'
            WHEN tp.name IN ('nvarchar', 'nchar') THEN CAST(p.max_length / 2 AS nvarchar(10))
            ELSE CAST(p.max_length AS nvarchar(10))
          END + N')'
        + CASE WHEN m.collation_name IS NOT NULL THEN N' COLLATE ' + m.collation_name ELSE N'' END
        + CASE WHEN m.is_nullable = 1 THEN N' NULL' ELSE N' NOT NULL' END + N';'
    FROM @columnas x
    JOIN sys.columns m ON m.object_id = OBJECT_ID('dbo.MuestrasPrograma') AND m.name = x.col
    JOIN sys.types tm ON tm.user_type_id = m.user_type_id
    JOIN sys.columns p ON p.object_id = OBJECT_ID('dbo.ReqProgramaTejido') AND p.name = x.col
    JOIN sys.types tp ON tp.user_type_id = p.user_type_id
    WHERE tm.name = tp.name                                   -- mismo tipo de texto
      AND tp.name IN ('varchar', 'nvarchar', 'char', 'nchar')
      AND m.max_length <> -1                                  -- Muestras ya es MAX: nada que ensanchar
      AND (p.max_length = -1 OR p.max_length > m.max_length); -- solo ensancha

OPEN cur;
FETCH NEXT FROM cur INTO @col, @sql;
WHILE @@FETCH_STATUS = 0
BEGIN
    EXEC sys.sp_executesql @sql;
    PRINT @sql;
    FETCH NEXT FROM cur INTO @col, @sql;
END
CLOSE cur;
DEALLOCATE cur;

COMMIT TRANSACTION;

-- DESPUÉS: las 11 deben quedar con bytes_muestras = bytes_programa, o listadas aquí
-- con el motivo (tipo distinto → decidir con la sesión PT, no ensanchar a ciegas).
SELECT N'DESPUES' AS momento, x.col AS columna, tm.name AS tipo_muestras, m.max_length AS bytes_muestras,
       tp.name AS tipo_programa, p.max_length AS bytes_programa,
       CASE
           WHEN m.name IS NULL THEN N'FALTA en Muestras'
           WHEN tm.name <> tp.name THEN N'TIPO DISTINTO: revisar'
           WHEN m.max_length = p.max_length THEN N'ok'
           ELSE N'DIFIERE'
       END AS estado
FROM @columnas x
LEFT JOIN sys.columns m ON m.object_id = OBJECT_ID('dbo.MuestrasPrograma') AND m.name = x.col
LEFT JOIN sys.types tm ON tm.user_type_id = m.user_type_id
LEFT JOIN sys.columns p ON p.object_id = OBJECT_ID('dbo.ReqProgramaTejido') AND p.name = x.col
LEFT JOIN sys.types tp ON tp.user_type_id = p.user_type_id
ORDER BY x.col;

-- =============================================================================
-- ROLLBACK (no se ejecuta). Acortar puede truncar datos: solo si ninguna fila excede
-- la longitud original. Plantilla por columna, con los valores del result set ANTES:
--
--   IF NOT EXISTS (SELECT 1 FROM dbo.MuestrasPrograma WHERE LEN(<col>) > <longitud_antes>)
--       ALTER TABLE dbo.MuestrasPrograma ALTER COLUMN <col> <tipo_antes>(<longitud_antes>)
--           COLLATE <collation_antes> <NULL|NOT NULL>;
--
-- Columnas: CalendarioId, FlogsId, NombreProyecto, CustName, AplicacionId, Observaciones, ColorTrama, Prioridad, CombinaTram, BomId, BomName
-- =============================================================================
