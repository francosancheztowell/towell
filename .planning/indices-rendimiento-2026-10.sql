-- Índices para captura de fórmula y BPM Urdido/Engomado (análisis de rendimiento, 2026-10-07).
-- Compatible con SQL Server 2008 R2. Revisarlo con el DBA y correrlo fuera de turno.
-- TelBPM y CatCodificados.OrdenTejido ya tienen índice en ProdTowel (.28).

-- Captura de fórmula: filtro Folio OR ProdId y búsqueda del folio sugerido (LIKE 'ENG-FORM-AAAA-%').
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_EngProduccionFormulacion_Folio')
    CREATE NONCLUSTERED INDEX IX_EngProduccionFormulacion_Folio ON dbo.EngProduccionFormulacion (Folio);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_EngProduccionFormulacion_ProdId')
    CREATE NONCLUSTERED INDEX IX_EngProduccionFormulacion_ProdId ON dbo.EngProduccionFormulacion (ProdId);

-- BPM: el índice ahora filtra Status <> 'Autorizado' OR Fecha >= hace 30 días.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_UrdBPM_Status_Fecha')
    CREATE NONCLUSTERED INDEX IX_UrdBPM_Status_Fecha ON dbo.UrdBPM (Status, Fecha);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_EngBPM_Status_Fecha')
    CREATE NONCLUSTERED INDEX IX_EngBPM_Status_Fecha ON dbo.EngBPM (Status, Fecha);
