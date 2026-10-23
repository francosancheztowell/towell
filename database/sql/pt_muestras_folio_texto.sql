-- =============================================================================
-- Muestras libera con su propio folio (SSYSFoliosSecuencias.Modulo = 'Muestras', prefijo M:
-- M3175, M3176…) y ese folio llega a CatCodificados. Decisiones del owner (23-oct-2026):
--   * Folio: la secuencia 'Muestras' tal cual está (Consecutivo 3174 → el siguiente es M3175).
--   * OrdCompartida y OrdPrincipal pasan de INT a texto en las 4 tablas, porque el número de
--     grupo y la orden principal salen del NoProduccion del líder, que en Muestras lleva "M".
--   * Los 16 grupos copiados de Programa a Muestras vuelven a número temporal (no tienen
--     NoProduccion): al liberar su primera orden adoptan el folio de Muestras.
--
-- NOTA PARA QUIEN LO CORRA
--   * SQL Server 2008 R2, contra ProdTowel. Backup previo de MuestrasPrograma,
--     ReqProgramaTejido, CatCodificados y ReqModelosCodificados.
--   * Un solo lote (sin GO) y una sola transacción: si algo falla, no queda nada a medias.
--   * Idempotente: cada paso revisa el estado y salta lo que ya está hecho.
--   * Compatible con el código actual: mientras los valores sean números, el código que hoy
--     compara contra INT sigue funcionando (SQL Server convierte). Los folios "M" solo aparecen
--     cuando se despliegue el código nuevo, que se hace después de correr esto.
--   * El PASO 3 cambia el tipo de columnas en CatCodificados y ReqProgramaTejido: toma un
--     bloqueo de esquema breve; correrlo cuando nadie esté liberando ni editando el programa.
--   * NO quitar NoMarbete/RollosProgramados/ProduccionMarbetes/ProdId de
--     config/planeacion.php → superficies.muestras.columnas_ausentes hasta desplegar el código
--     del folio de Muestras: antes, Liberar en Muestras consumiría folios de 'Planeacion'.
--   * ProdTowel @ 192.168.2.28: los PASOS 1 y 2 ya se aplicaron el 23-oct-2026 14:26. Correr el
--     script completo es seguro: esos pasos avisan que ya están hechos y solo corre el PASO 3.
--
-- Qué debe imprimir (base sin aplicar): 4 × "Agregada MuestrasPrograma.<col>", "Filas de
-- Muestras pasadas a grupo temporal: N", 7 × "Cambiada <tabla>.<col> a nvarchar(15)" y "Listo".
-- Después revisar los 4 resultados de VERIFICACIÓN del final.
-- =============================================================================
SET XACT_ABORT ON;
SET NOCOUNT ON;

DECLARE @col sysname, @tabla sysname, @tipo nvarchar(300), @sql nvarchar(max), @n int;
DECLARE @faltan TABLE (col sysname PRIMARY KEY);
DECLARE @cambios TABLE (n int IDENTITY PRIMARY KEY, tabla sysname, col sysname);

BEGIN TRANSACTION;

-- =============================================================================
-- PASO 1. MuestrasPrograma: columnas de marbetes y producción que faltan para Liberar.
-- Mismo método que pt_muestras_marbetes.sql / pt_muestras_produccion.sql (que este paso
-- reemplaza): el tipo se copia de dbo.ReqProgramaTejido, no se escribe a mano.
-- =============================================================================
INSERT INTO @faltan (col) VALUES (N'NoMarbete'), (N'RollosProgramados'), (N'ProduccionMarbetes'), (N'ProdId');

WHILE EXISTS (SELECT 1 FROM @faltan)
BEGIN
    SELECT TOP 1 @col = col FROM @faltan ORDER BY col;
    DELETE FROM @faltan WHERE col = @col;

    IF COL_LENGTH('dbo.MuestrasPrograma', @col) IS NOT NULL
    BEGIN
        PRINT N'MuestrasPrograma.' + @col + N' ya existe: sin cambios';
        CONTINUE;
    END

    SET @tipo = NULL;
    SELECT @tipo =
        CASE
            WHEN t.name IN ('varchar', 'char') THEN t.name + '(' + CASE WHEN c.max_length = -1 THEN 'MAX' ELSE CAST(c.max_length AS varchar(10)) END + ')'
            WHEN t.name IN ('nvarchar', 'nchar') THEN t.name + '(' + CASE WHEN c.max_length = -1 THEN 'MAX' ELSE CAST(c.max_length / 2 AS varchar(10)) END + ')'
            WHEN t.name IN ('decimal', 'numeric') THEN t.name + '(' + CAST(c.precision AS varchar(3)) + ',' + CAST(c.scale AS varchar(3)) + ')'
            ELSE t.name
        END
        + CASE WHEN c.collation_name IS NOT NULL THEN ' COLLATE ' + c.collation_name ELSE '' END
    FROM sys.columns c
    JOIN sys.types t ON t.user_type_id = c.user_type_id
    WHERE c.object_id = OBJECT_ID('dbo.ReqProgramaTejido') AND c.name = @col;

    IF @tipo IS NULL
    BEGIN
        RAISERROR(N'ReqProgramaTejido.%s no existe: no se puede copiar su tipo. Abortado sin cambios.', 16, 1, @col);
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        RETURN;
    END

    SET @sql = N'ALTER TABLE dbo.MuestrasPrograma ADD ' + QUOTENAME(@col) + N' ' + @tipo + N' NULL;';
    EXEC sys.sp_executesql @sql;
    PRINT N'Agregada MuestrasPrograma.' + @col + N' ' + @tipo + N' NULL';
END

-- =============================================================================
-- PASO 2. Grupos copiados de Programa → número temporal (900000000 + Id del primero).
-- Regla de la app (OrdCompartida.php): un grupo sin ningún NoProduccion lleva número
-- temporal y no tiene líder ni OrdPrincipal hasta liberar su primera orden.
-- Solo corre mientras OrdCompartida siga siendo INT (antes del PASO 3).
-- =============================================================================
IF (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'MuestrasPrograma' AND COLUMN_NAME = 'OrdCompartida') = 'int'
BEGIN
    EXEC sys.sp_executesql N'
        UPDATE m
        SET m.OrdCompartida = g.temporal,
            m.OrdCompartidaLider = NULL,
            m.OrdPrincipal = NULL,
            m.UpdatedAt = GETDATE()
        FROM dbo.MuestrasPrograma m
        JOIN (
            SELECT OrdCompartida, 900000000 + MIN(Id) AS temporal
            FROM dbo.MuestrasPrograma
            WHERE OrdCompartida IS NOT NULL AND OrdCompartida < 900000000
            GROUP BY OrdCompartida
            HAVING SUM(CASE WHEN NoProduccion IS NOT NULL AND NoProduccion <> N'''' THEN 1 ELSE 0 END) = 0
        ) g ON g.OrdCompartida = m.OrdCompartida;
        PRINT N''Filas de Muestras pasadas a grupo temporal: '' + CAST(@@ROWCOUNT AS nvarchar(10));';
END
ELSE
    PRINT N'PASO 2 omitido: MuestrasPrograma.OrdCompartida ya no es INT (el PASO 3 ya corrió).';

-- =============================================================================
-- PASO 3. OrdCompartida y OrdPrincipal: INT → nvarchar(15), igual que NoProduccion (de donde
-- salen). nvarchar y no varchar: pdo_sqlsrv manda los textos como NVARCHAR y así los índices
-- se siguen usando (ver nota NVARCHAR vs VARCHAR en CLAUDE.md).
-- Para poder cambiar el tipo se quitan los 2 índices de ReqProgramaTejido y las estadísticas
-- automáticas de esas columnas; los índices se recrean idénticos y las estadísticas las vuelve
-- a crear SQL Server solo.
-- =============================================================================
INSERT INTO @cambios (tabla, col) VALUES
    (N'ReqProgramaTejido', N'OrdCompartida'),
    (N'ReqProgramaTejido', N'OrdPrincipal'),
    (N'MuestrasPrograma', N'OrdCompartida'),
    (N'MuestrasPrograma', N'OrdPrincipal'),
    (N'CatCodificados', N'OrdCompartida'),
    (N'CatCodificados', N'OrdPrincipal'),
    (N'ReqModelosCodificados', N'OrdPrincipal');

-- Índices que llevan OrdCompartida (se recrean abajo con la misma definición).
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.ReqProgramaTejido') AND name = 'IX_ReqProgramaTejido_OrdCompartida')
    EXEC sys.sp_executesql N'DROP INDEX IX_ReqProgramaTejido_OrdCompartida ON dbo.ReqProgramaTejido;';
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.ReqProgramaTejido') AND name = 'IX_ReqProgramaTejido_OrdCompartida_Telar_Pos')
    EXEC sys.sp_executesql N'DROP INDEX IX_ReqProgramaTejido_OrdCompartida_Telar_Pos ON dbo.ReqProgramaTejido;';

-- Estadísticas que no son de índice sobre las columnas a cambiar (bloquean ALTER COLUMN).
SET @sql = N'';
SELECT @sql = @sql + N'DROP STATISTICS dbo.' + QUOTENAME(OBJECT_NAME(s.object_id)) + N'.' + QUOTENAME(s.name) + N';'
FROM sys.stats s
JOIN sys.stats_columns sc ON sc.object_id = s.object_id AND sc.stats_id = s.stats_id
JOIN sys.columns c ON c.object_id = sc.object_id AND c.column_id = sc.column_id
JOIN @cambios x ON s.object_id = OBJECT_ID(N'dbo.' + x.tabla) AND c.name = x.col
LEFT JOIN sys.indexes i ON i.object_id = s.object_id AND i.index_id = s.stats_id
WHERE i.index_id IS NULL;
IF @sql <> N'' EXEC sys.sp_executesql @sql;

-- Cambio de tipo (solo donde todavía es INT).
SET @n = 1;
WHILE @n <= (SELECT MAX(n) FROM @cambios)
BEGIN
    SELECT @tabla = tabla, @col = col FROM @cambios WHERE n = @n;
    IF (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = @tabla AND COLUMN_NAME = @col) = 'int'
    BEGIN
        SET @sql = N'ALTER TABLE dbo.' + QUOTENAME(@tabla) + N' ALTER COLUMN ' + QUOTENAME(@col)
                 + N' nvarchar(15) COLLATE SQL_Latin1_General_CP1_CI_AS NULL;';
        EXEC sys.sp_executesql @sql;
        PRINT N'Cambiada ' + @tabla + N'.' + @col + N' a nvarchar(15)';
    END
    ELSE
        PRINT @tabla + N'.' + @col + N' ya no es INT: sin cambios';
    SET @n = @n + 1;
END

-- Recrear los índices con la definición original.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.ReqProgramaTejido') AND name = 'IX_ReqProgramaTejido_OrdCompartida')
    EXEC sys.sp_executesql N'CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_OrdCompartida
        ON dbo.ReqProgramaTejido (OrdCompartida, NoTelarId);';
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.ReqProgramaTejido') AND name = 'IX_ReqProgramaTejido_OrdCompartida_Telar_Pos')
    EXEC sys.sp_executesql N'CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_OrdCompartida_Telar_Pos
        ON dbo.ReqProgramaTejido (OrdCompartida, SalonTejidoId, NoTelarId, Posicion)
        INCLUDE (EnProceso, FechaInicio, FechaFinal);';

COMMIT TRANSACTION;
PRINT N'Listo';

-- =============================================================================
-- VERIFICACIÓN (con dynamic SQL para que se compile después de los cambios de tipo)
-- =============================================================================
-- 1) Tipos: las 4 columnas de Muestras existen y las 7 de grupo son nvarchar(15).
EXEC sys.sp_executesql N'
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = ''dbo''
  AND (COLUMN_NAME IN (''OrdCompartida'', ''OrdPrincipal'')
       OR (TABLE_NAME IN (''ReqProgramaTejido'', ''MuestrasPrograma'')
           AND COLUMN_NAME IN (''NoMarbete'', ''RollosProgramados'', ''ProduccionMarbetes'', ''ProdId'')))
ORDER BY COLUMN_NAME, TABLE_NAME;';

-- 2) Índices recreados (se esperan 2).
EXEC sys.sp_executesql N'
SELECT name FROM sys.indexes
WHERE object_id = OBJECT_ID(''dbo.ReqProgramaTejido'') AND name LIKE ''IX_ReqProgramaTejido_OrdCompartida%'';';

-- 3) Grupos de Muestras: todos temporales, sin líder ni OrdPrincipal (se esperan 16 grupos).
EXEC sys.sp_executesql N'
SELECT OrdCompartida, COUNT(*) AS filas,
       SUM(CASE WHEN OrdCompartidaLider = 1 THEN 1 ELSE 0 END) AS lideres,
       SUM(CASE WHEN OrdPrincipal IS NOT NULL THEN 1 ELSE 0 END) AS conOrdPrincipal
FROM dbo.MuestrasPrograma
WHERE OrdCompartida IS NOT NULL
GROUP BY OrdCompartida
ORDER BY OrdCompartida;';

-- 4) Secuencia de Muestras sin tocar (el siguiente folio será M + Consecutivo + 1).
SELECT Modulo, Prefijo, Consecutivo FROM dbo.SSYSFoliosSecuencias WHERE Modulo IN ('Muestras', 'Planeacion');

-- =============================================================================
-- REVERSA (no se ejecuta: descomentar y correr aparte). El PASO 3 solo se puede revertir
-- mientras todos los valores sigan siendo números (antes del primer folio "M" en un grupo).
-- El PASO 2 no tiene reversa automática: los números originales eran los de Programa
-- (mismos Id en ReqProgramaTejido) y se pueden volver a copiar de ahí.
-- =============================================================================
/*
SET XACT_ABORT ON;
BEGIN TRANSACTION;
DROP INDEX IX_ReqProgramaTejido_OrdCompartida ON dbo.ReqProgramaTejido;
DROP INDEX IX_ReqProgramaTejido_OrdCompartida_Telar_Pos ON dbo.ReqProgramaTejido;
ALTER TABLE dbo.ReqProgramaTejido ALTER COLUMN OrdCompartida int NULL;
ALTER TABLE dbo.ReqProgramaTejido ALTER COLUMN OrdPrincipal int NULL;
ALTER TABLE dbo.MuestrasPrograma ALTER COLUMN OrdCompartida int NULL;
ALTER TABLE dbo.MuestrasPrograma ALTER COLUMN OrdPrincipal int NULL;
ALTER TABLE dbo.CatCodificados ALTER COLUMN OrdCompartida int NULL;
ALTER TABLE dbo.CatCodificados ALTER COLUMN OrdPrincipal int NULL;
ALTER TABLE dbo.ReqModelosCodificados ALTER COLUMN OrdPrincipal int NULL;
CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_OrdCompartida ON dbo.ReqProgramaTejido (OrdCompartida, NoTelarId);
CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_OrdCompartida_Telar_Pos ON dbo.ReqProgramaTejido (OrdCompartida, SalonTejidoId, NoTelarId, Posicion) INCLUDE (EnProceso, FechaInicio, FechaFinal);
COMMIT TRANSACTION;
*/
