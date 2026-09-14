/*
  Alta de catalogos STD (velocidad + eficiencia) para KARL MAYER, telares 401 y 402.

  Copia las filas de un telar plantilla y las agrega con SalonTejidoId = 'KARL MAYER'.
  TelarSalonResolver::salonAliases() resuelve 'KM' -> 'KARL MAYER', asi que el programa
  las encuentra escribiendo el salon de cualquiera de las dos formas.

  Es idempotente (NOT EXISTS): se puede correr dos veces sin duplicar.
  Revisa el bloque 3: esos valores son una COPIA del plantilla, no medidas reales de KM.
*/
USE ProdTowel;
GO
SET NOCOUNT ON;

DECLARE @SalonOrigen nvarchar(50) = 'SMITH';   -- salon del telar plantilla
DECLARE @TelarOrigen nvarchar(50) = '309';     -- telar con las 38 filas (19 fibras x 2 densidades)
DECLARE @SalonKM     nvarchar(50) = 'KARL MAYER';

DECLARE @Telares TABLE (NoTelarId nvarchar(50));
INSERT INTO @Telares (NoTelarId) VALUES ('401'), ('402');

-- Fibras que SI usa Karl Mayer hoy (ReqProgramaTejido.FibraRizo / ReqModelosCodificados).
-- El programa busca el catalogo por FibraRizo exacto, y estos textos no existen en el
-- catalogo actual, asi que sin este bloque el match nunca ocurre.
DECLARE @FibrasKM TABLE (FibraId nvarchar(100), Densidad nvarchar(20), Velocidad real, Eficiencia real);
INSERT INTO @FibrasKM (FibraId, Densidad, Velocidad, Eficiencia) VALUES
    ('FIL. 370 VOLUMINIZADO', 'Normal', 330, 0.80),
    ('FIL. 370 VOLUMINIZADO', 'Alta',   300, 0.78),
    ('ANILLO',                'Normal', 330, 0.80),
    ('ANILLO',                'Alta',   300, 0.78);

BEGIN TRANSACTION;

-- 1) Velocidades copiadas del telar plantilla
INSERT INTO dbo.ReqVelocidadStd (SalonTejidoId, NoTelarId, FibraId, Velocidad, Densidad)
SELECT @SalonKM, t.NoTelarId, v.FibraId, v.Velocidad, v.Densidad
FROM dbo.ReqVelocidadStd v
CROSS JOIN @Telares t
WHERE v.SalonTejidoId = @SalonOrigen
  AND v.NoTelarId     = @TelarOrigen
  AND NOT EXISTS (
        SELECT 1 FROM dbo.ReqVelocidadStd x
        WHERE x.SalonTejidoId = @SalonKM
          AND x.NoTelarId     = t.NoTelarId
          AND x.FibraId       = v.FibraId
          AND ISNULL(x.Densidad, '') = ISNULL(v.Densidad, ''));

-- 2) Eficiencias copiadas del mismo telar plantilla
INSERT INTO dbo.ReqEficienciaStd (SalonTejidoId, NoTelarId, FibraId, Eficiencia, Densidad)
SELECT @SalonKM, t.NoTelarId, e.FibraId, e.Eficiencia, e.Densidad
FROM dbo.ReqEficienciaStd e
CROSS JOIN @Telares t
WHERE e.SalonTejidoId = @SalonOrigen
  AND e.NoTelarId     = @TelarOrigen
  AND NOT EXISTS (
        SELECT 1 FROM dbo.ReqEficienciaStd x
        WHERE x.SalonTejidoId = @SalonKM
          AND x.NoTelarId     = t.NoTelarId
          AND x.FibraId       = e.FibraId
          AND ISNULL(x.Densidad, '') = ISNULL(e.Densidad, ''));

-- 3) Fibras propias de Karl Mayer  <-- AJUSTA Velocidad/Eficiencia con los valores reales
INSERT INTO dbo.ReqVelocidadStd (SalonTejidoId, NoTelarId, FibraId, Velocidad, Densidad)
SELECT @SalonKM, t.NoTelarId, f.FibraId, f.Velocidad, f.Densidad
FROM @FibrasKM f
CROSS JOIN @Telares t
WHERE NOT EXISTS (
        SELECT 1 FROM dbo.ReqVelocidadStd x
        WHERE x.SalonTejidoId = @SalonKM
          AND x.NoTelarId     = t.NoTelarId
          AND x.FibraId       = f.FibraId
          AND ISNULL(x.Densidad, '') = f.Densidad);

INSERT INTO dbo.ReqEficienciaStd (SalonTejidoId, NoTelarId, FibraId, Eficiencia, Densidad)
SELECT @SalonKM, t.NoTelarId, f.FibraId, f.Eficiencia, f.Densidad
FROM @FibrasKM f
CROSS JOIN @Telares t
WHERE NOT EXISTS (
        SELECT 1 FROM dbo.ReqEficienciaStd x
        WHERE x.SalonTejidoId = @SalonKM
          AND x.NoTelarId     = t.NoTelarId
          AND x.FibraId       = f.FibraId
          AND ISNULL(x.Densidad, '') = f.Densidad);

COMMIT TRANSACTION;

-- Verificacion: debe dar 42 filas por telar (38 copiadas + 4 propias) en cada tabla
SELECT 'Velocidad' AS Tabla, NoTelarId, COUNT(*) AS Filas
FROM dbo.ReqVelocidadStd WHERE SalonTejidoId = 'KARL MAYER' GROUP BY NoTelarId
UNION ALL
SELECT 'Eficiencia', NoTelarId, COUNT(*)
FROM dbo.ReqEficienciaStd WHERE SalonTejidoId = 'KARL MAYER' GROUP BY NoTelarId
ORDER BY Tabla, NoTelarId;

-- Prueba puntual: lo que buscara el programa para las ordenes KM de hoy
SELECT v.NoTelarId, v.FibraId, v.Densidad, v.Velocidad, e.Eficiencia
FROM dbo.ReqVelocidadStd v
LEFT JOIN dbo.ReqEficienciaStd e
       ON e.SalonTejidoId = v.SalonTejidoId AND e.NoTelarId = v.NoTelarId
      AND e.FibraId = v.FibraId AND ISNULL(e.Densidad,'') = ISNULL(v.Densidad,'')
WHERE v.SalonTejidoId = 'KARL MAYER' AND v.FibraId = 'FIL. 370 VOLUMINIZADO'
ORDER BY v.NoTelarId, v.Densidad;
