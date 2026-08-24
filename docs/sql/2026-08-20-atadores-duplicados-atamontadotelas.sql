/* =====================================================================================
   Corrección de duplicados en dbo.AtaMontadoTelas  (SQL Server 2008 R2 - ProdTowel)
   Fecha: 2026-08-20
   Contexto: el alta del atado (GET /atadores/iniciar) no era atómica y el JS disparaba
             la navegación 3 veces, por lo que dos peticiones simultáneas insertaban dos
             filas gemelas para el mismo NoJulio + NoProduccion. Después, cada acción
             (terminar / calificar / autorizar) elegía una fila u otra de forma no
             determinista y quedaban con Estatus distinto y datos incompletos.

   El código ya fue corregido. Este script:
     PASO 0 - diagnóstico
     PASO 1 - respaldo
     PASO 2 - reapuntar AtaDevoluciones al registro superviviente
     PASO 3 - eliminar las filas sobrantes de AtaMontadoTelas
     PASO 4 - depurar duplicados en AtaMontadoMaquinas / AtaMontadoActividades
     PASO 5 - índice único que impide que el problema se repita

   EJECUTAR PASO A PASO, revisando el resultado de cada SELECT antes de continuar.
   ===================================================================================== */

USE ProdTowel;
GO

/* -------------------------------------------------------------------------------------
   PASO 0. Diagnóstico: ver los grupos duplicados y qué fila sobrevivirá.
   Regla de supervivencia: el estatus más avanzado
   (Autorizado > Calificado > Terminado > En Proceso) y, en empate, el Id mayor.
   ------------------------------------------------------------------------------------- */
WITH Dup AS (
    SELECT NoJulio, NoProduccion
    FROM dbo.AtaMontadoTelas
    GROUP BY NoJulio, NoProduccion
    HAVING COUNT(*) > 1
),
Rank AS (
    SELECT a.Id, a.NoJulio, a.NoProduccion, a.Estatus, a.Fecha, a.Turno,
           a.Calidad, a.Limpieza, a.CveSupervisor, a.HoraArranque,
           ROW_NUMBER() OVER (
               PARTITION BY a.NoJulio, a.NoProduccion
               ORDER BY CASE a.Estatus
                            WHEN 'Autorizado'  THEN 4
                            WHEN 'Calificado'  THEN 3
                            WHEN 'Terminado'   THEN 2
                            WHEN 'En Proceso'  THEN 1
                            ELSE 0
                        END DESC,
                        a.Id DESC
           ) AS rn
    FROM dbo.AtaMontadoTelas a
    INNER JOIN Dup d ON d.NoJulio = a.NoJulio AND d.NoProduccion = a.NoProduccion
)
SELECT Id, NoJulio, NoProduccion, Estatus, Fecha, Turno, Calidad, Limpieza,
       CveSupervisor, HoraArranque,
       CASE WHEN rn = 1 THEN 'CONSERVAR' ELSE 'ELIMINAR' END AS Accion
FROM Rank
ORDER BY NoJulio, NoProduccion, rn;
GO


/* -------------------------------------------------------------------------------------
   PASO 1. Respaldo completo de las filas involucradas (obligatorio antes de borrar).
   ------------------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.AtaMontadoTelas_BkpDup_20260820', 'U') IS NOT NULL
    DROP TABLE dbo.AtaMontadoTelas_BkpDup_20260820;
GO

SELECT a.*
INTO dbo.AtaMontadoTelas_BkpDup_20260820
FROM dbo.AtaMontadoTelas a
INNER JOIN (
    SELECT NoJulio, NoProduccion
    FROM dbo.AtaMontadoTelas
    GROUP BY NoJulio, NoProduccion
    HAVING COUNT(*) > 1
) d ON d.NoJulio = a.NoJulio AND d.NoProduccion = a.NoProduccion;
GO

SELECT COUNT(*) AS FilasRespaldadas FROM dbo.AtaMontadoTelas_BkpDup_20260820;
GO


/* -------------------------------------------------------------------------------------
   PASO 2 y 3. Reapuntar devoluciones y eliminar las filas sobrantes.
   Se ejecutan juntos en una transacción para no dejar devoluciones huérfanas.
   ------------------------------------------------------------------------------------- */
BEGIN TRANSACTION;

-- Tabla temporal con el mapa perdedor -> superviviente
IF OBJECT_ID('tempdb..#MapaDup') IS NOT NULL DROP TABLE #MapaDup;

WITH Dup AS (
    SELECT NoJulio, NoProduccion
    FROM dbo.AtaMontadoTelas
    GROUP BY NoJulio, NoProduccion
    HAVING COUNT(*) > 1
),
Rank AS (
    SELECT a.Id, a.NoJulio, a.NoProduccion,
           ROW_NUMBER() OVER (
               PARTITION BY a.NoJulio, a.NoProduccion
               ORDER BY CASE a.Estatus
                            WHEN 'Autorizado'  THEN 4
                            WHEN 'Calificado'  THEN 3
                            WHEN 'Terminado'   THEN 2
                            WHEN 'En Proceso'  THEN 1
                            ELSE 0
                        END DESC,
                        a.Id DESC
           ) AS rn
    FROM dbo.AtaMontadoTelas a
    INNER JOIN Dup d ON d.NoJulio = a.NoJulio AND d.NoProduccion = a.NoProduccion
)
SELECT p.Id AS IdEliminar, g.Id AS IdConservar
INTO #MapaDup
FROM Rank p
INNER JOIN Rank g
        ON g.NoJulio = p.NoJulio
       AND g.NoProduccion = p.NoProduccion
       AND g.rn = 1
WHERE p.rn > 1;

SELECT COUNT(*) AS FilasAEliminar FROM #MapaDup;

-- PASO 2: mover devoluciones ligadas al registro que se va a eliminar
UPDATE d
   SET d.RefId = m.IdConservar
  FROM dbo.AtaDevoluciones d
 INNER JOIN #MapaDup m ON d.RefId = m.IdEliminar;

SELECT @@ROWCOUNT AS DevolucionesReapuntadas;

-- PASO 3: eliminar los duplicados
DELETE a
  FROM dbo.AtaMontadoTelas a
 INNER JOIN #MapaDup m ON a.Id = m.IdEliminar;

SELECT @@ROWCOUNT AS FilasEliminadas;

-- Verificación: debe devolver 0 filas
SELECT NoJulio, NoProduccion, COUNT(*) AS Veces
  FROM dbo.AtaMontadoTelas
 GROUP BY NoJulio, NoProduccion
HAVING COUNT(*) > 1;

-- Si todo lo anterior es correcto:
COMMIT TRANSACTION;
-- En caso contrario:
-- ROLLBACK TRANSACTION;
GO


/* -------------------------------------------------------------------------------------
   PASO 4. Depurar duplicados en las tablas hijas.
   El alta duplicada también sembró dos veces el catálogo de máquinas y actividades.
   Se conserva la fila de mayor Id (la última modificada por la interfaz).
   ------------------------------------------------------------------------------------- */

-- 4.a Diagnóstico
SELECT 'AtaMontadoMaquinas' AS Tabla, COUNT(*) AS GruposDuplicados FROM (
    SELECT NoJulio, NoProduccion, MaquinaId
      FROM dbo.AtaMontadoMaquinas
     GROUP BY NoJulio, NoProduccion, MaquinaId
    HAVING COUNT(*) > 1
) x
UNION ALL
SELECT 'AtaMontadoActividades', COUNT(*) FROM (
    SELECT NoJulio, NoProduccion, ActividadId
      FROM dbo.AtaMontadoActividades
     GROUP BY NoJulio, NoProduccion, ActividadId
    HAVING COUNT(*) > 1
) y;
GO

-- 4.b Respaldo
IF OBJECT_ID('dbo.AtaMontadoMaquinas_Bkp_20260820', 'U') IS NOT NULL
    DROP TABLE dbo.AtaMontadoMaquinas_Bkp_20260820;
SELECT * INTO dbo.AtaMontadoMaquinas_Bkp_20260820 FROM dbo.AtaMontadoMaquinas;

IF OBJECT_ID('dbo.AtaMontadoActividades_Bkp_20260820', 'U') IS NOT NULL
    DROP TABLE dbo.AtaMontadoActividades_Bkp_20260820;
SELECT * INTO dbo.AtaMontadoActividades_Bkp_20260820 FROM dbo.AtaMontadoActividades;
GO

-- 4.c Limpieza. Conserva la fila con Estado = 1 si existe; si no, la de mayor Id.
--     Ajustar el nombre de la columna Id si difiere en estas tablas.
BEGIN TRANSACTION;

WITH R AS (
    SELECT Id,
           ROW_NUMBER() OVER (
               PARTITION BY NoJulio, NoProduccion, MaquinaId
               ORDER BY CASE WHEN Estado = 1 THEN 0 ELSE 1 END, Id DESC
           ) AS rn
      FROM dbo.AtaMontadoMaquinas
)
DELETE FROM R WHERE rn > 1;
SELECT @@ROWCOUNT AS MaquinasEliminadas;

WITH R AS (
    SELECT Id,
           ROW_NUMBER() OVER (
               PARTITION BY NoJulio, NoProduccion, ActividadId
               ORDER BY CASE WHEN Estado = 1 THEN 0 ELSE 1 END, Id DESC
           ) AS rn
      FROM dbo.AtaMontadoActividades
)
DELETE FROM R WHERE rn > 1;
SELECT @@ROWCOUNT AS ActividadesEliminadas;

COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
GO


/* -------------------------------------------------------------------------------------
   PASO 5. Barrera definitiva a nivel de motor.

   5.a Índice único filtrado: impide que existan DOS atados vivos del mismo
       NoJulio + NoProduccion. No afecta a los históricos ya autorizados, por lo que
       permite re-atar el mismo julio/orden más adelante si el proceso lo requiere.
       Requiere que el PASO 3 haya dejado 0 duplicados en estatus activos.
   ------------------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE object_id = OBJECT_ID('dbo.AtaMontadoTelas')
                  AND name = 'UX_AtaMontadoTelas_Folio_Activo')
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_AtaMontadoTelas_Folio_Activo
        ON dbo.AtaMontadoTelas (NoJulio, NoProduccion)
     WHERE Estatus = 'En Proceso' OR Estatus = 'Terminado' OR Estatus = 'Calificado';
END
GO

/* 5.b Índices de apoyo para las búsquedas por folio (hoy la tabla sólo tiene la PK,
       así que toda consulta por NoJulio/NoProduccion hace scan). */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE object_id = OBJECT_ID('dbo.AtaMontadoTelas')
                  AND name = 'IX_AtaMontadoTelas_Folio')
BEGIN
    CREATE NONCLUSTERED INDEX IX_AtaMontadoTelas_Folio
        ON dbo.AtaMontadoTelas (NoJulio, NoProduccion) INCLUDE (Estatus);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE object_id = OBJECT_ID('dbo.AtaMontadoMaquinas')
                  AND name = 'IX_AtaMontadoMaquinas_Folio')
BEGIN
    CREATE NONCLUSTERED INDEX IX_AtaMontadoMaquinas_Folio
        ON dbo.AtaMontadoMaquinas (NoJulio, NoProduccion, MaquinaId);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE object_id = OBJECT_ID('dbo.AtaMontadoActividades')
                  AND name = 'IX_AtaMontadoActividades_Folio')
BEGIN
    CREATE NONCLUSTERED INDEX IX_AtaMontadoActividades_Folio
        ON dbo.AtaMontadoActividades (NoJulio, NoProduccion, ActividadId);
END
GO


/* -------------------------------------------------------------------------------------
   PASO 6 (opcional, tras confirmar que la operación quedó correcta).
   Eliminar los respaldos.
   ------------------------------------------------------------------------------------- */
-- DROP TABLE dbo.AtaMontadoTelas_BkpDup_20260820;
-- DROP TABLE dbo.AtaMontadoMaquinas_Bkp_20260820;
-- DROP TABLE dbo.AtaMontadoActividades_Bkp_20260820;
