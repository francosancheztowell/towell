-- Indices para Cortes de Eficiencia (TejEficiencia / TejEficienciaLine).
--
-- Ambas tablas son HEAP sin ningun indice: cada guardado del corte (~80 consultas por
-- Folio) recorre la tabla completa y los guardados encimados se bloquean entre si.
-- Pulse: POST /modulo-cortes-de-eficiencia 645 veces/semana, hasta 2.5 s.
--
-- Es idempotente: cada indice solo se crea si falta. Si hay duplicados que impiden un
-- indice unico, NO borra nada: avisa con PRINT y se salta ese indice.
--
-- Standard Edition no crea indices ONLINE: la tabla queda bloqueada mientras se crea
-- (segundos con ~25 mil filas). Ejecutar fuera de captura de cortes.
--
-- Ejecutar en la base donde estan TejEficiencia y TejEficienciaLine.

SET NOCOUNT ON;

-- 0) Diagnostico (solo lectura) ------------------------------------------------------
SELECT o.name AS tabla, i.name AS indice, i.type_desc
FROM sys.indexes i JOIN sys.objects o ON o.object_id = i.object_id
WHERE o.name IN ('TejEficiencia', 'TejEficienciaLine')
ORDER BY o.name, i.index_id;

SELECT 'TejEficiencia: folios duplicados' AS revision, COUNT(*) AS grupos
FROM (SELECT Folio FROM dbo.TejEficiencia GROUP BY Folio HAVING COUNT(*) > 1) d
UNION ALL
SELECT 'TejEficienciaLine: Folio+Telar+Turno+Fecha duplicados', COUNT(*)
FROM (SELECT Folio FROM dbo.TejEficienciaLine
      GROUP BY Folio, NoTelarId, Turno, [Date] HAVING COUNT(*) > 1) d
UNION ALL
-- El dedupe distingue filas por created_at: debe dar 0 o no podra limpiar esos grupos.
SELECT 'TejEficienciaLine: duplicados que el dedupe no separa (created_at nulo/igual)', COUNT(*)
FROM (SELECT Folio FROM dbo.TejEficienciaLine
      GROUP BY Folio, NoTelarId, Turno, [Date]
      HAVING COUNT(*) > 1
         AND (COUNT(*) <> COUNT(DISTINCT created_at) OR SUM(CASE WHEN created_at IS NULL THEN 1 ELSE 0 END) > 0)) d;
GO

-- 1) TejEficiencia: Folio unico (el modelo ya lo trata como llave primaria) ----------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TejEficiencia') AND name = 'UX_TejEficiencia_Folio')
    PRINT 'UX_TejEficiencia_Folio ya existia.';
ELSE IF EXISTS (SELECT Folio FROM dbo.TejEficiencia GROUP BY Folio HAVING COUNT(*) > 1)
    PRINT 'OMITIDO UX_TejEficiencia_Folio: hay folios duplicados en TejEficiencia. Revisarlos a mano.';
ELSE IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TejEficiencia') AND type = 1)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_TejEficiencia_Folio ON dbo.TejEficiencia (Folio);
    PRINT 'UX_TejEficiencia_Folio creado (nonclustered: la tabla ya tenia clustered).';
END
ELSE
BEGIN
    CREATE UNIQUE CLUSTERED INDEX UX_TejEficiencia_Folio ON dbo.TejEficiencia (Folio);
    PRINT 'UX_TejEficiencia_Folio creado (clustered).';
END
GO

-- 2) TejEficiencia: validacion "ya existe folio para esa fecha y turno" y el listado --
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TejEficiencia') AND name = 'IX_TejEficiencia_Date_Turno')
BEGIN
    CREATE NONCLUSTERED INDEX IX_TejEficiencia_Date_Turno ON dbo.TejEficiencia ([Date], Turno);
    PRINT 'IX_TejEficiencia_Date_Turno creado.';
END
ELSE
    PRINT 'IX_TejEficiencia_Date_Turno ya existia.';
GO

-- 3) TejEficienciaLine: llave del updateOrCreate del guardado ------------------------
--    Mismo nombre que database/sql/dedupe_and_constrain_tej_eficiencia_line.sql.
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TejEficienciaLine') AND name = 'UX_TejEficienciaLine_Folio_Telar_Turno_Fecha')
    PRINT 'UX_TejEficienciaLine_Folio_Telar_Turno_Fecha ya existia.';
ELSE IF EXISTS (SELECT Folio FROM dbo.TejEficienciaLine GROUP BY Folio, NoTelarId, Turno, [Date] HAVING COUNT(*) > 1)
    PRINT 'OMITIDO UX_TejEficienciaLine_Folio_Telar_Turno_Fecha: hay duplicados. Ejecutar primero dedupe_and_constrain_tej_eficiencia_line.sql (fusiona y crea el indice).';
ELSE IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TejEficienciaLine') AND type = 1)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_TejEficienciaLine_Folio_Telar_Turno_Fecha
        ON dbo.TejEficienciaLine (Folio, NoTelarId, Turno, [Date]);
    PRINT 'UX_TejEficienciaLine_Folio_Telar_Turno_Fecha creado (nonclustered).';
END
ELSE
BEGIN
    CREATE UNIQUE CLUSTERED INDEX UX_TejEficienciaLine_Folio_Telar_Turno_Fecha
        ON dbo.TejEficienciaLine (Folio, NoTelarId, Turno, [Date]);
    PRINT 'UX_TejEficienciaLine_Folio_Telar_Turno_Fecha creado (clustered).';
END
GO

-- 4) TejEficienciaLine: lectura por rango de fechas (tablero de Crudo) ---------------
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TejEficienciaLine') AND name = 'IX_TejEficienciaLine_Date_Turno')
BEGIN
    CREATE NONCLUSTERED INDEX IX_TejEficienciaLine_Date_Turno ON dbo.TejEficienciaLine ([Date], Turno);
    PRINT 'IX_TejEficienciaLine_Date_Turno creado.';
END
ELSE
    PRINT 'IX_TejEficienciaLine_Date_Turno ya existia.';
GO
