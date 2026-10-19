-- Carga del calendario de Urdido, 1-feb a 31-dic-2026, para KM1, Mc Coy 1, Mc Coy 2 y Mc Coy 3.
-- 3 horarios por día (06:30, 14:30, 22:30) de 480 min; el turno (cuadrilla 1..4) sale de la rotación
-- oficial (UrdEngCalMaquina::ROTACION_URDIDO):
--             Lu  Ma  Mi  Ju  Vi  Sá  Do
--   06:30     1   1   1   1   1   1   3
--   14:30     3   3   2   2   2   2   2
--   22:30     4   4   3   3   4   4   4
-- Las máquinas se buscan por MaquinaId (el Id cambia entre bases). Se puede correr más de una vez:
-- no inserta un turno que se encime con uno que ya exista en esa máquina. Compatible con SQL Server 2008 R2.
-- Esperado en una tabla vacía: 334 días × 3 horarios × 4 máquinas = 4,008 filas.

SET NOCOUNT ON;

IF (SELECT COUNT(*) FROM dbo.URDCatalogoMaquinas
    WHERE MaquinaId IN (N'KM1', N'Mc Coy 1', N'Mc Coy 2', N'Mc Coy 3')) <> 4
BEGIN
    RAISERROR(N'No están las 4 máquinas (KM1, Mc Coy 1, Mc Coy 2, Mc Coy 3) en URDCatalogoMaquinas. No se cargó nada.', 16, 1);
    RETURN;
END;

BEGIN TRANSACTION;

;WITH Numeros AS (
    SELECT TOP (334) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) - 1 AS n
    FROM sys.all_columns
),
Dias AS (
    SELECT DATEADD(day, n, CAST('20260201' AS datetime)) AS Dia,
           DATEDIFF(day, '19000101', DATEADD(day, n, CAST('20260201' AS datetime))) % 7 AS Dow  -- 0 = lunes … 6 = domingo
    FROM Numeros
),
Horarios AS (
    SELECT 1 AS Ventana, 390 AS MinutoInicio      -- 06:30
    UNION ALL SELECT 2, 870                       -- 14:30
    UNION ALL SELECT 3, 1350                      -- 22:30
),
Turnos AS (
    SELECT m.Id AS Maquina,
           DATEADD(minute, h.MinutoInicio, d.Dia) AS FechaInicio,
           480 AS Minutos,
           CASE h.Ventana
               WHEN 1 THEN CASE WHEN d.Dow = 6 THEN 3 ELSE 1 END          -- domingo en la mañana: 3
               WHEN 2 THEN CASE WHEN d.Dow IN (0, 1) THEN 3 ELSE 2 END    -- lunes y martes en la tarde: 3
               ELSE        CASE WHEN d.Dow IN (2, 3) THEN 3 ELSE 4 END    -- miércoles y jueves en la noche: 3
           END AS Turno
    FROM Dias d
    CROSS JOIN Horarios h
    CROSS JOIN dbo.URDCatalogoMaquinas m
    WHERE m.MaquinaId IN (N'KM1', N'Mc Coy 1', N'Mc Coy 2', N'Mc Coy 3')
)
INSERT INTO dbo.UrdEngCalMaquinas (Maquina, FechaInicio, Minutos, Turno)
SELECT t.Maquina, t.FechaInicio, t.Minutos, t.Turno
FROM Turnos t
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.UrdEngCalMaquinas e
    WHERE e.Maquina = t.Maquina
      AND e.FechaInicio < DATEADD(minute, t.Minutos, t.FechaInicio)
      AND DATEADD(minute, e.Minutos, e.FechaInicio) > t.FechaInicio
);

PRINT CAST(@@ROWCOUNT AS varchar(10)) + ' turnos insertados.';

COMMIT TRANSACTION;

-- Revisión: turnos por máquina y por turno en 2026.
SELECT m.MaquinaId, c.Turno, COUNT(*) AS Turnos, SUM(c.Minutos) AS Minutos
FROM dbo.UrdEngCalMaquinas c
JOIN dbo.URDCatalogoMaquinas m ON m.Id = c.Maquina
WHERE c.FechaInicio >= '20260201' AND c.FechaInicio < '20270101'
GROUP BY m.MaquinaId, c.Turno
ORDER BY m.MaquinaId, c.Turno;
