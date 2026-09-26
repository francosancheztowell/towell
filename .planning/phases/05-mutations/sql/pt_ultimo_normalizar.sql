/*
  PT-05 · PT-DUP-03 — Normalizar Ultimo = 'UL' a '1' (decisión del owner, 2026-09-26).

  NO lo ejecuta la sesión. Lo corre el DBA en SSMS, primero en staging y luego en live.
  Compatible con SQL Server 2008 R2 (sin THROW/IIF/CONCAT).
  Desde PT-05 el modelo ya convierte 'UL' → '1' al guardar; este script limpia lo que ya existe.
  Mientras no se corra, ReqProgramaTejido::esUltimo() y las consultas siguen aceptando 'UL'.

  Antes y después: php artisan planeacion:programa-tejido-health --json
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

-- 1) Conteo previo (anotar el resultado en el ticket)
SELECT 'ReqProgramaTejido' AS tabla, Ultimo, COUNT(*) AS filas
FROM dbo.ReqProgramaTejido
GROUP BY Ultimo
UNION ALL
SELECT 'MuestrasPrograma', Ultimo, COUNT(*)
FROM dbo.MuestrasPrograma
GROUP BY Ultimo;

-- Ids que se van a tocar (guardar esta salida: es el rollback)
SELECT 'ReqProgramaTejido' AS tabla, Id, Ultimo FROM dbo.ReqProgramaTejido WHERE UPPER(LTRIM(RTRIM(Ultimo))) = 'UL'
UNION ALL
SELECT 'MuestrasPrograma', Id, Ultimo FROM dbo.MuestrasPrograma WHERE UPPER(LTRIM(RTRIM(Ultimo))) = 'UL';

-- 2) Normalización
BEGIN TRANSACTION;

UPDATE dbo.ReqProgramaTejido SET Ultimo = '1' WHERE UPPER(LTRIM(RTRIM(Ultimo))) = 'UL';
PRINT 'ReqProgramaTejido: ' + CAST(@@ROWCOUNT AS varchar(20)) + ' filas';

UPDATE dbo.MuestrasPrograma SET Ultimo = '1' WHERE UPPER(LTRIM(RTRIM(Ultimo))) = 'UL';
PRINT 'MuestrasPrograma: ' + CAST(@@ROWCOUNT AS varchar(20)) + ' filas';

-- Revisar los conteos y confirmar a mano:
-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;

/*
  Rollback después del COMMIT: con la lista de Ids del paso 1,
  UPDATE dbo.ReqProgramaTejido SET Ultimo = 'UL' WHERE Id IN (...);
  UPDATE dbo.MuestrasPrograma  SET Ultimo = 'UL' WHERE Id IN (...);
  (No hace falta: '1' y 'UL' significan lo mismo para la aplicación.)
*/
