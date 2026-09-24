/*
  Columnas de barras KARL MAYER en MuestrasPrograma.

  alter_barras_karl_mayer.sql y alter_calibre_barra2_karl_mayer.sql solo alteraron
  ReqProgramaTejido, ReqModelosCodificados y CatCodificados. Pero /planeacion/muestras reusa
  ProgramaTejidoController: el middleware ProgramaTejidoContext solo cambia el nombre de la
  tabla a MuestrasPrograma, y el SELECT del index nombra CuentaBarra1..PasadasBarra4.
  Resultado desde el deploy del 22-sep: "Invalid column name 'CuentaBarra1'" y Muestras
  carga la tabla vacia.

  Mismas 28 columnas y mismos tipos que ReqProgramaTejido (24 de barra + CalibreBarraN2 de
  formula). MuestrasProgramaLine no se toca: ReqProgramaTejidoLine tampoco las tiene.

  Con guard: se puede correr dos veces.
*/
USE ProdTowel;
GO

IF COL_LENGTH('dbo.MuestrasPrograma', 'CuentaBarra1') IS NULL
    ALTER TABLE dbo.MuestrasPrograma ADD
        CuentaBarra1 NVARCHAR(10) NULL,
        CalibreBarra1 NVARCHAR(50) NULL,
        CodColorBarra1 NVARCHAR(10) NULL,
        ColorBarra1 NVARCHAR(60) NULL,
        FibraBarra1 NVARCHAR(50) NULL,
        PasadasBarra1 INT NULL,
        CuentaBarra2 NVARCHAR(10) NULL,
        CalibreBarra2 NVARCHAR(50) NULL,
        CodColorBarra2 NVARCHAR(10) NULL,
        ColorBarra2 NVARCHAR(60) NULL,
        FibraBarra2 NVARCHAR(50) NULL,
        PasadasBarra2 INT NULL,
        CuentaBarra3 NVARCHAR(10) NULL,
        CalibreBarra3 NVARCHAR(50) NULL,
        CodColorBarra3 NVARCHAR(10) NULL,
        ColorBarra3 NVARCHAR(60) NULL,
        FibraBarra3 NVARCHAR(50) NULL,
        PasadasBarra3 INT NULL,
        CuentaBarra4 NVARCHAR(10) NULL,
        CalibreBarra4 NVARCHAR(50) NULL,
        CodColorBarra4 NVARCHAR(10) NULL,
        ColorBarra4 NVARCHAR(60) NULL,
        FibraBarra4 NVARCHAR(50) NULL,
        PasadasBarra4 INT NULL;
GO

IF COL_LENGTH('dbo.MuestrasPrograma', 'CalibreBarra12') IS NULL
    ALTER TABLE dbo.MuestrasPrograma ADD
        CalibreBarra12 FLOAT NULL, CalibreBarra22 FLOAT NULL, CalibreBarra32 FLOAT NULL, CalibreBarra42 FLOAT NULL;
GO

-- Verificacion: las dos tablas deben dar 28.
SELECT t.name AS Tabla, COUNT(*) AS ColumnasBarra
FROM sys.columns c
JOIN sys.tables t ON t.object_id = c.object_id
WHERE t.name IN ('ReqProgramaTejido', 'MuestrasPrograma')
  AND c.name LIKE '%Barra[1-4]%'
GROUP BY t.name
ORDER BY t.name;
GO
