-- Módulo Estado de Máquina (verificación de telares por mecánicos)

IF OBJECT_ID(N'dbo.MecVerificaMaquinaTable', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MecVerificaMaquinaTable (
        Folio         VARCHAR(20)  NOT NULL,
        Fecha         DATE         NULL,
        TurnoRecibe   INT          NULL,
        CveOperador   VARCHAR(30)  NULL,
        NomOperador   VARCHAR(150) NULL,
        Estatus       VARCHAR(15)  NULL,

        CONSTRAINT PK_MecVerificaMaquinaTable_Folio
            PRIMARY KEY CLUSTERED (Folio)
    );
END
GO

-- Hora de inicio (captura automática al crear el folio) y hora de fin
-- (captura automática al finalizar la verificación).
IF COL_LENGTH('dbo.MecVerificaMaquinaTable', 'HoraInicio') IS NULL
BEGIN
    ALTER TABLE dbo.MecVerificaMaquinaTable ADD HoraInicio TIME NULL;
END
GO

IF COL_LENGTH('dbo.MecVerificaMaquinaTable', 'HoraFin') IS NULL
BEGIN
    ALTER TABLE dbo.MecVerificaMaquinaTable ADD HoraFin TIME NULL;
END
GO

IF OBJECT_ID(N'dbo.MecVerificaMaquinaLine', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MecVerificaMaquinaLine (
        Id          INT          IDENTITY(1,1) NOT NULL,
        Folio       VARCHAR(20)  NOT NULL,
        NoTelarId   VARCHAR(10)  NULL,
        Orden       INT          NULL,
        Actividad   VARCHAR(150) NULL,
        Valor       VARCHAR(100) NULL,

        CONSTRAINT PK_MecVerificaMaquinaLine_Id
            PRIMARY KEY CLUSTERED (Id),

        CONSTRAINT FK_MecVerificaMaquinaLine_MecVerificaMaquinaTable_Folio
            FOREIGN KEY (Folio)
            REFERENCES dbo.MecVerificaMaquinaTable(Folio)
            ON DELETE CASCADE
    );
END
GO

-- Secuencia de folios (prefijo VM) usada por App\Livewire\Mecanicos\VerificaMaquina\Index.
-- El propio código la crea sola en el primer uso si falta, pero se deja aquí para
-- mantener consistencia con el resto de módulos que sí la registran explícitamente.
IF NOT EXISTS (SELECT 1 FROM dbo.SSYSFoliosSecuencias WHERE modulo = 'MecVerificaMaquina')
BEGIN
    INSERT INTO dbo.SSYSFoliosSecuencias (modulo, prefijo, consecutivo)
    VALUES ('MecVerificaMaquina', 'VM', 0);
END
GO

-- Catálogo de actividades: ver create_MecActividades.sql (tabla dbo.MecActividades).
-- El catálogo de telares reutiliza dbo.ReqTelares (App\Models\Planeacion\ReqTelares),
-- no se crea tabla adicional para telares.
