/*
 * Carga de dbo.SYSDepartamentosAsignacion por SYSUsuario.area. Solo agrega pares que faltan.
 *   Urdido / Engomado → su departamento
 *   Supervisores, Desarrolladores, Trama → Jacquard, Smith, Itema, Karl Mayer
 *   Tejedores → el departamento de cada telar suyo (TelTelaresOperador → URDCatalogoMaquinas);
 *               sin telares, los 4 salones
 *   Mantenimiento, Atadores, Calidad y el resto → sin asignación (a propósito)
 * Supervisor: el que ya tenga el usuario; si no tiene filas, 1 cuando puesto = 'Supervisor'.
 * SQL Server 2008 R2.
 */

-- 1) VISTA PREVIA (solo lectura)
WITH Salones AS (
    SELECT * FROM (VALUES (N'Jacquard'), (N'Smith'), (N'Itema'), (N'Karl Mayer')) s(Departamento)
),
Objetivo AS (
    SELECT u.idusuario, CAST(u.area AS NVARCHAR(60)) AS Departamento
    FROM dbo.SYSUsuario u WHERE u.area IN ('Urdido', 'Engomado')
    UNION
    SELECT u.idusuario, s.Departamento
    FROM dbo.SYSUsuario u CROSS JOIN Salones s
    WHERE u.area IN ('Supervisores', 'Desarrolladores', 'Trama')
    UNION
    SELECT u.idusuario, m.Departamento
    FROM dbo.SYSUsuario u
    JOIN dbo.TelTelaresOperador o ON o.numero_empleado = u.numero_empleado
    JOIN dbo.URDCatalogoMaquinas m ON m.MaquinaId = CAST(o.NoTelarId AS NVARCHAR(30))
    WHERE u.area = 'Tejedores'
    UNION
    SELECT u.idusuario, s.Departamento
    FROM dbo.SYSUsuario u CROSS JOIN Salones s
    WHERE u.area = 'Tejedores'
      AND NOT EXISTS (SELECT 1 FROM dbo.TelTelaresOperador o WHERE o.numero_empleado = u.numero_empleado)
),
Nuevos AS (
    SELECT o.idusuario, o.Departamento,
           COALESCE(
               (SELECT MAX(CAST(a.Supervisor AS INT)) FROM dbo.SYSDepartamentosAsignacion a WHERE a.idusuario = o.idusuario),
               CASE WHEN u.puesto = 'Supervisor' THEN 1 ELSE 0 END
           ) AS Supervisor
    FROM Objetivo o
    JOIN dbo.SYSUsuario u ON u.idusuario = o.idusuario
    WHERE o.Departamento IN (SELECT Departamento FROM dbo.URDCatalogoMaquinas)
      AND NOT EXISTS (SELECT 1 FROM dbo.SYSDepartamentosAsignacion a
                      WHERE a.idusuario = o.idusuario AND a.Departamento = o.Departamento)
),
Final AS (
    SELECT idusuario, Departamento, CAST(Supervisor AS INT) AS Supervisor, CAST('YA ESTABA' AS VARCHAR(20)) AS Estado
    FROM dbo.SYSDepartamentosAsignacion
    UNION ALL
    SELECT idusuario, Departamento, Supervisor, 'NUEVO' FROM Nuevos
)
SELECT u.idusuario, u.nombre, u.area, u.puesto, f.Departamento, f.Supervisor,
       ISNULL(f.Estado, 'SIN ASIGNACION') AS Estado
FROM dbo.SYSUsuario u
LEFT JOIN Final f ON f.idusuario = u.idusuario
ORDER BY u.area, u.idusuario, f.Departamento;


-- 2) INSERT (deja la transacción abierta: revisa el OUTPUT y luego COMMIT o ROLLBACK)
SET XACT_ABORT ON;
BEGIN TRAN;

WITH Salones AS (
    SELECT * FROM (VALUES (N'Jacquard'), (N'Smith'), (N'Itema'), (N'Karl Mayer')) s(Departamento)
),
Objetivo AS (
    SELECT u.idusuario, CAST(u.area AS NVARCHAR(60)) AS Departamento
    FROM dbo.SYSUsuario u WHERE u.area IN ('Urdido', 'Engomado')
    UNION
    SELECT u.idusuario, s.Departamento
    FROM dbo.SYSUsuario u CROSS JOIN Salones s
    WHERE u.area IN ('Supervisores', 'Desarrolladores', 'Trama')
    UNION
    SELECT u.idusuario, m.Departamento
    FROM dbo.SYSUsuario u
    JOIN dbo.TelTelaresOperador o ON o.numero_empleado = u.numero_empleado
    JOIN dbo.URDCatalogoMaquinas m ON m.MaquinaId = CAST(o.NoTelarId AS NVARCHAR(30))
    WHERE u.area = 'Tejedores'
    UNION
    SELECT u.idusuario, s.Departamento
    FROM dbo.SYSUsuario u CROSS JOIN Salones s
    WHERE u.area = 'Tejedores'
      AND NOT EXISTS (SELECT 1 FROM dbo.TelTelaresOperador o WHERE o.numero_empleado = u.numero_empleado)
)
INSERT INTO dbo.SYSDepartamentosAsignacion (idusuario, Departamento, Supervisor)
OUTPUT inserted.idusuario, inserted.Departamento, inserted.Supervisor
SELECT o.idusuario, o.Departamento,
       COALESCE(
           (SELECT MAX(CAST(a.Supervisor AS INT)) FROM dbo.SYSDepartamentosAsignacion a WHERE a.idusuario = o.idusuario),
           CASE WHEN u.puesto = 'Supervisor' THEN 1 ELSE 0 END
       )
FROM Objetivo o
JOIN dbo.SYSUsuario u ON u.idusuario = o.idusuario
WHERE o.Departamento IN (SELECT Departamento FROM dbo.URDCatalogoMaquinas)
  AND NOT EXISTS (SELECT 1 FROM dbo.SYSDepartamentosAsignacion a
                  WHERE a.idusuario = o.idusuario AND a.Departamento = o.Departamento);

-- COMMIT;    -- si el OUTPUT está bien
-- ROLLBACK;  -- si no
