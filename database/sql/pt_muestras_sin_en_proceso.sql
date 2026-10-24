-- =============================================================================
-- Muestras no tiene orden "en proceso" (decisión del owner, 23-oct-2026)
--
-- La muestra se consume al guardarla en Desarrolladores Muestras; lo que teje el telar es la
-- orden de Programa. Desde este cambio Planeación y "Recalcular fechas" ya no marcan muestras en
-- proceso (ProgramaTejidoSurface::tieneEnProceso). Este script limpia las que quedaron marcadas.
--
-- Qué cambia al correrlo, solo en MuestrasPrograma:
--   * La primera muestra de cada telar deja de arrancar en "ahora" en cada recálculo: conserva
--     la FechaInicio que tiene hoy.
--   * Se puede eliminar y mover como cualquier otra muestra.
-- No toca ReqProgramaTejido ni ninguna otra tabla. ProdTowel, SQL Server 2008 R2.
-- Alternativa sin SQL: "Recalcular fechas" en Muestras también las limpia (y recalcula fechas).
-- =============================================================================
SET NOCOUNT ON;

SELECT COUNT(*) AS muestras_en_proceso FROM dbo.MuestrasPrograma WHERE EnProceso = 1;  -- 23-oct: 38

UPDATE dbo.MuestrasPrograma
SET EnProceso = 0, UpdatedAt = GETDATE()
WHERE EnProceso = 1;
PRINT N'Muestras que dejan de estar en proceso: ' + CAST(@@ROWCOUNT AS nvarchar(10));

SELECT COUNT(*) AS muestras_en_proceso FROM dbo.MuestrasPrograma WHERE EnProceso = 1;  -- esperado 0
