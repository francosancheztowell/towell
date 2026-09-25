/*
  Calibre de fórmula por barra para KARL MAYER: CalibreBarra12..CalibreBarra42 (FLOAT).

  Es el "techo conocido" que anticipaba alter_barras_karl_mayer.sql. El calibre de catálogo
  (CalibreBarraN, NVARCHAR) no sirve para la fórmula de la L.Mat: en poliéster el artículo
  AX es 70/1 pero la hoja de producción calcula con 75.99. Mismo patrón que
  CalibreComb{n} (catálogo) / CalibreComb{n}2 (fórmula).

  L.Mat KM: %barra = (PasadasBarraN / CalibreBarraN2) / Σ(PasadasBarra / CalibreBarra2).
  Validado contra TEJ FEL 612 KM III-K de AX (0.267 / 0.026 / 0.180 / 0.233).

  Tres tablas por la cadena de copia CatCodificados -> ReqModelosCodificados -> ReqProgramaTejido.
  Con guard: se puede correr dos veces.

  EJECUTAR ANTES DE TOCAR LOS MODELOS (CatCodificados::COLUMNS arma los SELECT).
*/
USE ProdTowel;
GO

IF COL_LENGTH('dbo.ReqProgramaTejido', 'CalibreBarra12') IS NULL
    ALTER TABLE dbo.ReqProgramaTejido ADD
        CalibreBarra12 FLOAT NULL, CalibreBarra22 FLOAT NULL, CalibreBarra32 FLOAT NULL, CalibreBarra42 FLOAT NULL;
GO

IF COL_LENGTH('dbo.ReqModelosCodificados', 'CalibreBarra12') IS NULL
    ALTER TABLE dbo.ReqModelosCodificados ADD
        CalibreBarra12 FLOAT NULL, CalibreBarra22 FLOAT NULL, CalibreBarra32 FLOAT NULL, CalibreBarra42 FLOAT NULL;
GO

IF COL_LENGTH('dbo.CatCodificados', 'CalibreBarra12') IS NULL
    ALTER TABLE dbo.CatCodificados ADD
        CalibreBarra12 FLOAT NULL, CalibreBarra22 FLOAT NULL, CalibreBarra32 FLOAT NULL, CalibreBarra42 FLOAT NULL;
GO
