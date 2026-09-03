CREATE TABLE dbo.MecVerificaMaquinaTable (
    Folio       VARCHAR(20) NOT NULL,
    Fecha       DATE NULL,
    TelarId     VARCHAR(10) NULL,
    FolioParo   VARCHAR(30) NULL,
    Falla       VARCHAR(150) NULL,
    FechaParo   DATE NULL,
    HoraParo    TIME(0) NULL,
    Estatus     VARCHAR(15) NULL,
    Orden       VARCHAR(20) NULL,
    Turno       INT NULL,

    CONSTRAINT PK_MecVerificaMaquinaTable
        PRIMARY KEY CLUSTERED (Folio)
);
GO

CREATE TABLE dbo.MecVerificaMaquinaLine (
    Id            INT IDENTITY(1,1) NOT NULL,
    Folio         VARCHAR(20) NOT NULL,
    CveOperador   VARCHAR(30) NULL,
    NomOperador   VARCHAR(150) NULL,
    Ajusto        BIT NOT NULL
        CONSTRAINT DF_MecVerificaMaquinaLine_Ajusto DEFAULT (0),
    Reparo        BIT NOT NULL
        CONSTRAINT DF_MecVerificaMaquinaLine_Reparo DEFAULT (0),
    Cambio        BIT NOT NULL
        CONSTRAINT DF_MecVerificaMaquinaLine_Cambio DEFAULT (0),
    Lubrico       BIT NOT NULL
        CONSTRAINT DF_MecVerificaMaquinaLine_Lubrico DEFAULT (0),
    FaltaRefacc   BIT NOT NULL
        CONSTRAINT DF_MecVerificaMaquinaLine_FaltaRefacc DEFAULT (0),
    HoraInicial   TIME(0) NULL,
    HoraFinal     TIME(0) NULL,
    TotalMinutos  INT NULL,
    Calificacion  INT NULL,
    CveTejedor    VARCHAR(30) NULL,
    NomTejedor    VARCHAR(150) NULL,

    CONSTRAINT PK_MecVerificaMaquinaLine
        PRIMARY KEY CLUSTERED (Id),

    CONSTRAINT FK_MecVerificaMaquinaLine_Folio
        FOREIGN KEY (Folio)
        REFERENCES dbo.MecVerificaMaquinaTable(Folio)
        ON DELETE CASCADE
);
GO

CREATE INDEX IX_MecVerificaMaquinaLine_Folio
    ON dbo.MecVerificaMaquinaLine(Folio);
GO

CREATE OR ALTER TRIGGER dbo.TR_MecVerificaMaquinaTable_CrearLinea
ON dbo.MecVerificaMaquinaTable
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.MecVerificaMaquinaLine (Folio)
    SELECT Folio
    FROM inserted;
END;
GO





SET XACT_ABORT ON;

BEGIN TRANSACTION;

EXEC sys.sp_rename
    N'dbo.MecVerificaMaquinaLine',
    N'MecOrdenTrabajoLine',
    N'OBJECT';

EXEC sys.sp_rename
    N'dbo.MecVerificaMaquinaTable',
    N'MecOrdenTrabajoTable',
    N'OBJECT';

-- Eliminar el trigger anterior, si existe
IF OBJECT_ID(N'dbo.TR_MecVerificaMaquinaTable_CrearLinea', N'TR') IS NOT NULL
BEGIN
    EXEC(N'DROP TRIGGER dbo.TR_MecVerificaMaquinaTable_CrearLinea;');
END;

-- EXEC crea un lote independiente, requerido para CREATE TRIGGER
EXEC(N'
CREATE TRIGGER dbo.TR_MecOrdenTrabajoTable_CrearLinea
ON dbo.MecOrdenTrabajoTable
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.MecOrdenTrabajoLine (Folio)
    SELECT Folio
    FROM inserted;
END;
');

COMMIT TRANSACTION;




-- =============================================================================
-- Captura por renglón: turno y comentarios de la intervención
-- =============================================================================
-- El turno vive en el renglón y no en la cabecera: una orden puede cruzar
-- turnos y cada intervención la captura el mecánico que estaba en piso.
-- Turno 4 es el comodín que cubre descansos (ver App\Helpers\TurnoHelper).

IF COL_LENGTH('dbo.MecOrdenTrabajoLine', 'Turno') IS NULL
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoLine ADD Turno INT NULL;
END
GO

IF COL_LENGTH('dbo.MecOrdenTrabajoLine', 'comentarios') IS NULL
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoLine ADD comentarios VARCHAR(500) NULL;
END
GO