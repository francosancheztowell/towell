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
-- Captura por renglón: turno, fecha y comentarios de la intervención
-- =============================================================================
-- El turno y la fecha viven en el renglón y no en la cabecera: una orden puede
-- cruzar turnos y días, y cada intervención la captura el mecánico que estaba
-- en piso. Turno 4 es el comodín que cubre descansos (ver App\Helpers\TurnoHelper).

IF COL_LENGTH('dbo.MecOrdenTrabajoLine', 'Turno') IS NULL
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoLine ADD Turno INT NULL;
END
GO

IF COL_LENGTH('dbo.MecOrdenTrabajoLine', 'Fecha') IS NULL
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoLine ADD Fecha DATE NULL;
END
GO

IF COL_LENGTH('dbo.MecOrdenTrabajoLine', 'comentarios') IS NULL
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoLine ADD comentarios VARCHAR(500) NULL;
END
GO

-- =============================================================================
-- Calificación del renglón: escala 1-5 alineada con el paro de origen
-- =============================================================================
-- La orden nace de un paro (MecOrdenTrabajoTable.FolioParo -> ManFallasParos.Folio)
-- y ese paro ya se califica al cerrarlo con estrellas 1-5 (ManFallasParos.Calidad).
-- Al finalizar la orden esa nota se hereda a los renglones sin calificar, así que
-- ambas columnas tienen que medir en la misma escala. Antes el renglón aceptaba
-- 1-10 (solo por validación de Laravel: la columna nunca tuvo CHECK).
--
-- Al momento de este cambio existían 2 renglones calificados, ambos fuera de la
-- nueva escala (un 6 y un 10). Se reescalan dividiendo entre 2 y redondeando
-- hacia arriba, que es la conversión inversa de 1-5 -> 1-10.
--
-- Revisa el SELECT antes de correr el UPDATE.

SELECT Id, Folio, Calificacion
FROM dbo.MecOrdenTrabajoLine
WHERE Calificacion > 5;
GO

UPDATE dbo.MecOrdenTrabajoLine
SET Calificacion = CASE
        WHEN Calificacion > 10 THEN 5
        ELSE (Calificacion + 1) / 2
    END
WHERE Calificacion > 5;
GO

-- Opcional pero recomendado: la escala pasa a estar garantizada por la base y no
-- solo por la validación de PHP. Ejecutar SOLO después del UPDATE de arriba.
IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = N'CK_MecOrdenTrabajoLine_Calificacion'
)
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoLine
        ADD CONSTRAINT CK_MecOrdenTrabajoLine_Calificacion
        CHECK (Calificacion IS NULL OR Calificacion BETWEEN 1 AND 5);
END
GO
