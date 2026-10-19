-- Calendario de máquinas de Urdido y Engomado: minutos disponibles por máquina y turno.
-- Es el tiempo total del OEE (.planning/oee_urdido_plan.md):
--   oportunidad = Minutos − producción (UrdProduccionUrdido) − paros (ManFallasParos),
-- todo recortado a la ventana [FechaInicio, FechaInicio + Minutos).
--
-- - Cada máquina tiene su propio horario y un turno puede empezar a cualquier hora,
--   por eso FechaInicio lleva fecha y hora reales (un turno 3 del 19 empieza 2026-10-19 22:30).
-- - Sin fila = turno no programado. No se guardan ceros.
-- - Turno solo 1..3: el turno 4 cubre descansos y no es una ventana de tiempo.
-- - No hay FechaFin a propósito: se calcula, y así no se desfasa como en ReqCalendarioLine.
-- - Que dos turnos de la misma máquina no se encimen lo valida la aplicación.

IF OBJECT_ID(N'dbo.UrdEngCalMaquinas', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.UrdEngCalMaquinas (
        Id          INT      IDENTITY(1,1) NOT NULL,
        Maquina     INT      NOT NULL,  -- URDCatalogoMaquinas.Id (134 KM1, 135-137 Mc Coy, 60-61 WestPoint)
        FechaInicio DATETIME NOT NULL,
        Minutos     INT      NOT NULL,
        Turno       TINYINT  NOT NULL,

        CONSTRAINT PK_UrdEngCalMaquinas_Id
            PRIMARY KEY CLUSTERED (Id),

        CONSTRAINT FK_UrdEngCalMaquinas_URDCatalogoMaquinas_Id
            FOREIGN KEY (Maquina)
            REFERENCES dbo.URDCatalogoMaquinas(Id),

        CONSTRAINT CK_UrdEngCalMaquinas_Minutos CHECK (Minutos > 0 AND Minutos <= 1440),
        CONSTRAINT CK_UrdEngCalMaquinas_Turno   CHECK (Turno BETWEEN 1 AND 3)
    );
END
GO

-- Una máquina no puede tener dos turnos que empiecen al mismo tiempo; además es el
-- índice de la consulta del OEE (máquina + rango de fechas).
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.UrdEngCalMaquinas')
      AND name = 'UX_UrdEngCalMaquinas_Maquina_FechaInicio'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_UrdEngCalMaquinas_Maquina_FechaInicio
        ON dbo.UrdEngCalMaquinas (Maquina, FechaInicio)
        INCLUDE (Minutos, Turno);
END
GO
