-- Limpia filas duplicadas en TejEficienciaLine (mismo Folio+Telar+Turno+Fecha guardado
-- dos veces cuando el mismo folio se edita desde dos sesiones distintas, p. ej. el
-- operador que llena los datos y el revisor que corrige comentarios despues) y agrega
-- un indice unico para que no se puedan volver a crear duplicados.
--
-- Equivalente a la migracion Laravel:
--   database/migrations/2026_07_03_123445_dedupe_and_constrain_tej_eficiencia_line.php
--
-- Es idempotente: se puede ejecutar mas de una vez sin causar dano (si ya no hay
-- duplicados, el UPDATE/DELETE no afectan filas, y el indice solo se crea si falta).
--
-- Ejecutar en la base donde esta TejEficienciaLine.

SET NOCOUNT ON;

BEGIN TRAN;

-- 1) Fusionar datos: para cada grupo duplicado, la fila mas antigua (por created_at)
--    se queda como "titular" y recibe el ultimo valor no nulo de cada columna entre
--    todas las filas del grupo (para no perder ningun comentario/lectura).
;WITH Grupos AS (
    SELECT
        Folio, [Date], Turno, NoTelarId, created_at,
        ROW_NUMBER() OVER (PARTITION BY Folio, [Date], Turno, NoTelarId ORDER BY created_at ASC) AS rn,
        COUNT(*)      OVER (PARTITION BY Folio, [Date], Turno, NoTelarId) AS total
    FROM dbo.TejEficienciaLine
)
UPDATE t
SET
    SalonTejidoId = (SELECT TOP 1 x.SalonTejidoId FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.SalonTejidoId IS NOT NULL AND x.SalonTejidoId <> '' ORDER BY x.created_at DESC),
    RpmStd        = (SELECT TOP 1 x.RpmStd        FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.RpmStd IS NOT NULL ORDER BY x.created_at DESC),
    EficienciaSTD = (SELECT TOP 1 x.EficienciaSTD FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.EficienciaSTD IS NOT NULL ORDER BY x.created_at DESC),
    RpmR1         = (SELECT TOP 1 x.RpmR1         FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.RpmR1 IS NOT NULL ORDER BY x.created_at DESC),
    EficienciaR1  = (SELECT TOP 1 x.EficienciaR1  FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.EficienciaR1 IS NOT NULL ORDER BY x.created_at DESC),
    ObsR1         = (SELECT TOP 1 x.ObsR1         FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.ObsR1 IS NOT NULL AND x.ObsR1 <> '' ORDER BY x.created_at DESC),
    StatusOB1     = (SELECT TOP 1 x.StatusOB1     FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.StatusOB1 IS NOT NULL ORDER BY x.created_at DESC),
    RpmR2         = (SELECT TOP 1 x.RpmR2         FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.RpmR2 IS NOT NULL ORDER BY x.created_at DESC),
    EficienciaR2  = (SELECT TOP 1 x.EficienciaR2  FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.EficienciaR2 IS NOT NULL ORDER BY x.created_at DESC),
    ObsR2         = (SELECT TOP 1 x.ObsR2         FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.ObsR2 IS NOT NULL AND x.ObsR2 <> '' ORDER BY x.created_at DESC),
    StatusOB2     = (SELECT TOP 1 x.StatusOB2     FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.StatusOB2 IS NOT NULL ORDER BY x.created_at DESC),
    RpmR3         = (SELECT TOP 1 x.RpmR3         FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.RpmR3 IS NOT NULL ORDER BY x.created_at DESC),
    EficienciaR3  = (SELECT TOP 1 x.EficienciaR3  FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.EficienciaR3 IS NOT NULL ORDER BY x.created_at DESC),
    ObsR3         = (SELECT TOP 1 x.ObsR3         FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.ObsR3 IS NOT NULL AND x.ObsR3 <> '' ORDER BY x.created_at DESC),
    StatusOB3     = (SELECT TOP 1 x.StatusOB3     FROM dbo.TejEficienciaLine x WHERE x.Folio = t.Folio AND x.[Date] = t.[Date] AND x.Turno = t.Turno AND x.NoTelarId = t.NoTelarId AND x.StatusOB3 IS NOT NULL ORDER BY x.created_at DESC)
FROM dbo.TejEficienciaLine t
JOIN Grupos g
    ON g.Folio = t.Folio AND g.[Date] = t.[Date] AND g.Turno = t.Turno
   AND g.NoTelarId = t.NoTelarId AND g.created_at = t.created_at
WHERE g.rn = 1 AND g.total > 1;

PRINT CONCAT('Filas fusionadas (titulares actualizadas): ', @@ROWCOUNT);

-- 2) Borrar las filas duplicadas sobrantes (todas menos la mas antigua de cada grupo).
;WITH Grupos2 AS (
    SELECT
        Folio, [Date], Turno, NoTelarId, created_at,
        ROW_NUMBER() OVER (PARTITION BY Folio, [Date], Turno, NoTelarId ORDER BY created_at ASC) AS rn
    FROM dbo.TejEficienciaLine
)
DELETE t
FROM dbo.TejEficienciaLine t
JOIN Grupos2 g
    ON g.Folio = t.Folio AND g.[Date] = t.[Date] AND g.Turno = t.Turno
   AND g.NoTelarId = t.NoTelarId AND g.created_at = t.created_at
WHERE g.rn > 1;

PRINT CONCAT('Filas duplicadas eliminadas: ', @@ROWCOUNT);

-- 3) Indice unico para impedir que se vuelvan a crear duplicados a futuro.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes i
    JOIN sys.objects o ON i.object_id = o.object_id
    WHERE o.name = 'TejEficienciaLine' AND i.name = 'UX_TejEficienciaLine_Folio_Telar_Turno_Fecha'
)
BEGIN
    CREATE UNIQUE INDEX UX_TejEficienciaLine_Folio_Telar_Turno_Fecha
        ON dbo.TejEficienciaLine (Folio, NoTelarId, Turno, [Date]);
    PRINT 'Indice unico UX_TejEficienciaLine_Folio_Telar_Turno_Fecha creado.';
END
ELSE
    PRINT 'Indice unico ya existia, no se volvio a crear.';

COMMIT TRAN;
GO
