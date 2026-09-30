-- Paros activos por máquina (dbo.ManFallasParos).
--
-- Todas las consultas "¿hay paro Activo en esta máquina?" filtran por Estatus + MaquinaId:
--   - ModuloProduccionUrdidoController::finalizar (bloquea finalizar con paro activo; sin filtro por Depto)
--   - ManFallasParos::hayActivoEnMaquina (alta de paro; usa WITH (updlock, holdlock))
--   - listado de activos de Mantenimiento y paros activos de Crudo
-- Sin índice cada una recorre la tabla completa, y el holdlock de hayActivoEnMaquina
-- bloquea todo lo leído hasta el fin de la transacción. Con el índice solo bloquea el rango.
--
-- Índice normal, no filtrado (WHERE Estatus = 'Activo'): uno filtrado obliga a que todo
-- el que escriba en la tabla tenga QUOTED_IDENTIFIER/ANSI_NULLS ON, y no sabemos si
-- escribe algo más que Laravel.
-- Standard Edition: sin ONLINE = ON. Con ~10k filas se crea en menos de un segundo.

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ManFallasParos_Estatus_MaquinaId' AND object_id = OBJECT_ID(N'dbo.ManFallasParos'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_ManFallasParos_Estatus_MaquinaId
        ON dbo.ManFallasParos (Estatus, MaquinaId)
        INCLUDE (Depto, TipoFallaId, Folio);
END
GO
