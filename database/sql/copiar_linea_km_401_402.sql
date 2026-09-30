-- Copia una linea del programa de tejido de Karl Mayer al final del telar 401 y del 402.
-- Hace lo mismo que el boton Duplicar: la copia queda como Ultimo, sin EnProceso, sin
-- orden de produccion ni marbetes, y con la duracion de KM = 600 kg/dia sin eficiencia.
-- Compatible con SQL Server 2008 R2.
--
-- Limites (el SQL no pasa por la app):
--   * FechaFinal = FechaInicio + HorasProd corrido, NO respeta huecos del calendario.
--   * No genera las lineas diarias (ReqProgramaTejidoLine) ni EntregaCte/EntregaPT.
--   => Despues de correrlo, abrir cada linea nueva en Programa Tejido y guardarla (o balancear)
--      para que la app ajuste la fecha al calendario y genere las lineas diarias.

DECLARE @IdOrigen bigint      = 0;             -- <== Id de la linea KM a copiar
DECLARE @Usuario  varchar(50) = 'SQL';         -- quien queda como UsuarioCrea
DECLARE @KgDia    float       = 600;           -- meta KM por telar (config crudo.fixed_daily_kilos)

SET XACT_ABORT ON;

IF NOT EXISTS (
    SELECT 1 FROM dbo.ReqProgramaTejido
    WHERE Id = @IdOrigen AND SalonTejidoId IN ('KARL MAYER', 'KM') AND PesoCrudo > 0
)
BEGIN
    RAISERROR('El Id no existe, no es Karl Mayer o no tiene PesoCrudo.', 16, 1);
    RETURN;
END

BEGIN TRAN;

-- La copia sera la nueva ultima de cada telar.
UPDATE dbo.ReqProgramaTejido
SET Ultimo = '0'
WHERE SalonTejidoId IN ('KARL MAYER', 'KM') AND NoTelarId IN ('401', '402') AND Ultimo = '1';

;WITH telares AS (
    SELECT '401' AS Telar UNION ALL SELECT '402'
),
base AS (
    SELECT o.*,
        t.Telar AS TelarDestino,
        ISNULL((SELECT MAX(x.FechaFinal) FROM dbo.ReqProgramaTejido x
                WHERE x.SalonTejidoId IN ('KARL MAYER', 'KM') AND x.NoTelarId = t.Telar), GETDATE()) AS IniNuevo,
        ISNULL((SELECT MAX(x.Posicion) FROM dbo.ReqProgramaTejido x
                WHERE x.SalonTejidoId IN ('KARL MAYER', 'KM') AND x.NoTelarId = t.Telar), 0) + 1 AS PosNueva,
        (SELECT TOP 1 x.FibraRizo FROM dbo.ReqProgramaTejido x
         WHERE x.SalonTejidoId IN ('KARL MAYER', 'KM') AND x.NoTelarId = t.Telar
         ORDER BY x.FechaFinal DESC) AS FibraAnterior,
        o.TotalPedido * (1 + ISNULL(o.PorcentajeSegundos, 0) / 100.0) AS SaldoNuevo
    FROM dbo.ReqProgramaTejido o
    CROSS JOIN telares t
    WHERE o.Id = @IdOrigen
),
calc AS (
    SELECT b.*,
        (@KgDia * 1000.0 / b.PesoCrudo) / 24.0 AS StdKm,                 -- piezas/hora
        b.SaldoNuevo * b.PesoCrudo * 24.0 / (@KgDia * 1000.0) AS Horas   -- kg totales / kg por hora
    FROM base b
)
INSERT INTO dbo.ReqProgramaTejido (
    EnProceso, CuentaRizo, CalibreRizo, SalonTejidoId, NoTelarId, Ultimo, CambioHilo, Maquina, Ancho,
    EficienciaSTD, VelocidadSTD, FibraRizo, CalibrePie, CalendarioId, TamanoClave, NoExisteBase, ItemId,
    InventSizeId, Rasurado, NombreProducto, TotalPedido, Produccion, SaldoPedido, SaldoMarbete, ProgramarProd,
    NoProduccion, Programado, FlogsId, NombreProyecto, CustName, AplicacionId, Observaciones, TipoPedido,
    NoTiras, Peine, Luchaje, PesoCrudo, CalibreTrama, FibraTrama, DobladilloId, PasadasTrama,
    PasadasComb1, PasadasComb2, PasadasComb3, PasadasComb4, PasadasComb5, AnchoToalla, CodColorTrama, ColorTrama,
    CalibreComb12, FibraComb1, CodColorComb1, NombreCC1, CalibreComb22, FibraComb2, CodColorComb2, NombreCC2,
    CalibreComb32, FibraComb3, CodColorComb3, NombreCC3, CalibreComb42, FibraComb4, CodColorComb4, NombreCC4,
    CalibreComb52, FibraComb5, CodColorComb5, NombreCC5, MedidaPlano, CuentaPie, CodColorCtaPie, NombreCPie,
    PesoGRM2, DiasEficiencia, ProdKgDia, StdDia, ProdKgDia2, StdToaHra, DiasJornada, HorasProd, StdHrsEfect,
    Calc4, Calc5, Calc6, EntregaProduc, EntregaPT, EntregaCte, PTvsCte, CreatedAt, UpdatedAt, FibraPie,
    FechaInicio, FechaFinal, CalibreRizo2, CalibrePie2, CalibreTrama2,
    CalibreComb1, CalibreComb2, CalibreComb3, CalibreComb4, CalibreComb5, Prioridad, LargoCrudo,
    OrdCompartida, CategoriaCalidad, PorcentajeSegundos, PedidoTempo, OrdCompartidaLider, Reprogramar,
    MtsRollo, PzasRollo, TotalRollos, TotalPzas, Repeticiones, CombinaTram, BomId, BomName, CreaProd, Densidad,
    HiloAX, ActualizaLmat, FechaCreacion, HoraCreacion, UsuarioCrea, FechaModificacion, HoraModificacion,
    UsuarioModifica, Posicion, OrdPrincipal, PesoMuestra, TotalSegundas, FechaArranque, FechaFinaliza,
    NoMarbete, RollosProgramados, ProdId, ProduccionMarbetes, IdRedbooth, NombreRedbooth, PesoRollo,
    CuentaBarra1, CalibreBarra1, CodColorBarra1, ColorBarra1, FibraBarra1, PasadasBarra1,
    CuentaBarra2, CalibreBarra2, CodColorBarra2, ColorBarra2, FibraBarra2, PasadasBarra2,
    CuentaBarra3, CalibreBarra3, CodColorBarra3, ColorBarra3, FibraBarra3, PasadasBarra3,
    CuentaBarra4, CalibreBarra4, CodColorBarra4, ColorBarra4, FibraBarra4, PasadasBarra4,
    CalibreBarra12, CalibreBarra22, CalibreBarra32, CalibreBarra42
)
SELECT
    0, CuentaRizo, CalibreRizo, 'KARL MAYER', TelarDestino, '1',
    CASE WHEN ISNULL(FibraAnterior, '') = ISNULL(FibraRizo, '') THEN '0' ELSE '1' END,
    'KM ' + TelarDestino, Ancho,
    EficienciaSTD, VelocidadSTD, FibraRizo, CalibrePie, CalendarioId, TamanoClave, NoExisteBase, ItemId,
    InventSizeId, Rasurado, NombreProducto, TotalPedido, NULL, SaldoNuevo, NULL, NULL,
    NULL, NULL, FlogsId, NombreProyecto, CustName, AplicacionId, Observaciones, TipoPedido,
    NoTiras, Peine, Luchaje, PesoCrudo, CalibreTrama, FibraTrama, DobladilloId, PasadasTrama,
    PasadasComb1, PasadasComb2, PasadasComb3, PasadasComb4, PasadasComb5, AnchoToalla, CodColorTrama, ColorTrama,
    CalibreComb12, FibraComb1, CodColorComb1, NombreCC1, CalibreComb22, FibraComb2, CodColorComb2, NombreCC2,
    CalibreComb32, FibraComb3, CodColorComb3, NombreCC3, CalibreComb42, FibraComb4, CodColorComb4, NombreCC4,
    CalibreComb52, FibraComb5, CodColorComb5, NombreCC5, MedidaPlano, CuentaPie, CodColorCtaPie, NombreCPie,
    PesoGRM2,
    ROUND(Horas / 24.0, 2),            -- DiasEficiencia (sin huecos de calendario)
    @KgDia,                            -- ProdKgDia
    ROUND(StdKm * 24.0, 2),            -- StdDia
    @KgDia,                            -- ProdKgDia2
    ROUND(StdKm, 2),                   -- StdToaHra
    ROUND(Horas / 24.0, 2),            -- DiasJornada
    ROUND(Horas, 2),                   -- HorasProd
    ROUND(StdKm, 2),                   -- StdHrsEfect
    Calc4, Calc5, Calc6, NULL, NULL, NULL, NULL, GETDATE(), GETDATE(), FibraPie,
    IniNuevo, DATEADD(second, CAST(ROUND(Horas * 3600.0, 0) AS int), IniNuevo),
    CalibreRizo2, CalibrePie2, CalibreTrama2,
    CalibreComb1, CalibreComb2, CalibreComb3, CalibreComb4, CalibreComb5, Prioridad, LargoCrudo,
    NULL, CategoriaCalidad, PorcentajeSegundos, PedidoTempo, NULL, NULL,
    MtsRollo, PzasRollo, TotalRollos, TotalPzas, Repeticiones, CombinaTram, BomId, BomName, NULL, Densidad,
    HiloAX, ActualizaLmat, CAST(GETDATE() AS date), CAST(GETDATE() AS time), @Usuario,
    CAST(GETDATE() AS date), CAST(GETDATE() AS time), @Usuario,
    PosNueva, OrdPrincipal, PesoMuestra, TotalSegundas, NULL, NULL,
    NULL, RollosProgramados, NULL, NULL, IdRedbooth, NombreRedbooth, PesoRollo,
    CuentaBarra1, CalibreBarra1, CodColorBarra1, ColorBarra1, FibraBarra1, PasadasBarra1,
    CuentaBarra2, CalibreBarra2, CodColorBarra2, ColorBarra2, FibraBarra2, PasadasBarra2,
    CuentaBarra3, CalibreBarra3, CodColorBarra3, ColorBarra3, FibraBarra3, PasadasBarra3,
    CuentaBarra4, CalibreBarra4, CodColorBarra4, ColorBarra4, FibraBarra4, PasadasBarra4,
    CalibreBarra12, CalibreBarra22, CalibreBarra32, CalibreBarra42
FROM calc;

-- Revisar antes de confirmar: deben salir 2 lineas nuevas (401 y 402).
SELECT Id, NoTelarId, NombreProducto, SaldoPedido, PesoCrudo, ProdKgDia, HorasProd, DiasJornada,
       FechaInicio, FechaFinal, Posicion, Ultimo
FROM dbo.ReqProgramaTejido
WHERE SalonTejidoId = 'KARL MAYER' AND NoTelarId IN ('401', '402') AND Ultimo = '1';

-- Si esta bien: COMMIT;   Si no: ROLLBACK;
