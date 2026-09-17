/*
  Alta de columnas Barra1..Barra4 para KARL MAYER (telares 401 y 402).

  Karl Mayer no teje con rizo/pie/C1-C5: su construccion son cuatro barras. Hoy esos datos
  se capturan a la fuerza en las ranuras de rizo y pie (los 7 registros de 401/402 tienen
  cuenta/calibre de barra metidos en CuentaRizo/CuentaPie) y las otras dos barras no tienen
  donde ir. Estas columnas les dan lugar propio.

  Van en las tres tablas porque la cadena de copia es
  CatCodificados -> ReqModelosCodificados -> ReqProgramaTejido:
  si falta un eslabon, el dato no llega al programa.

  Columnas dedicadas (no reusar los slots CombN) a proposito: son contrato de integracion.
  Un consumidor externo lee 'CuentaBarra2', no 'CalibreComb2 que en KM significa otra cosa'.
  Ese disfraz es justo lo que hace UrdProgramaUrdido.RizoPie hoy, y lo que dejo muerto el
  flujo de desarrolladores para KM.

  Son 4 barras, no 5: confirmado contra UrdProgramaUrdido, donde RizoPie para Karl Mayer
  toma los valores '1'..'4' (69 / 36 / 61 / 61 filas).

  ponytail: CalibreBarraN es texto y la formula de consumo lo castea, igual que ya hace
  calcularPie() con CuentaPie. No se agrega el gemelo numerico CalibreBarraN2 porque las 227
  filas de barra tienen calibre numerico (0 textuales). Techo conocido: si algun dia se captura
  una designacion tipo '10/1T' en una barra, el cast la leeria como 10 y habria que agregar
  CalibreBarraN2 FLOAT, como ya existe para pie y para las combinaciones.

  Sin guards IF NOT EXISTS: se verifico que ninguna de las 72 columnas existe. Si el script se
  corre dos veces, el segundo intento falla con "Column names in each table must be unique",
  que es un error seguro: no modifica nada.

  Tamanos espejo de la columna equivalente del patron existente:
    Cuenta   <- CuentaRizo / CuentaPie   NVARCHAR(10)
    Calibre  <- CalibreComb1..5          NVARCHAR(50)
    CodColor <- CodColorComb1            NVARCHAR(10)
    Color    <- NombreCC1 / NomColorC1   NVARCHAR(60)
    Fibra    <- FibraComb1               NVARCHAR(50)
    Pasadas  <- PasadasComb1             INT

  EJECUTAR ANTES DE TOCAR LOS MODELOS: CatCodificados::COLUMNS arma los SELECT con su lista
  de columnas, asi que si el modelo las nombra antes de que existan, el catalogo truena.
*/
USE ProdTowel;
GO

ALTER TABLE dbo.ReqProgramaTejido ADD
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

ALTER TABLE dbo.ReqModelosCodificados ADD
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

ALTER TABLE dbo.CatCodificados ADD
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

-- Verificacion: deben salir 24 columnas por tabla.
SELECT  t.name   AS Tabla,
        COUNT(*) AS ColumnasBarra
FROM sys.columns c
JOIN sys.tables  t ON t.object_id = c.object_id
WHERE t.name IN ('ReqProgramaTejido', 'ReqModelosCodificados', 'CatCodificados')
  AND c.name LIKE '%Barra[1-4]'
GROUP BY t.name
ORDER BY t.name;
GO
