-- Karl Mayer: montado y enhebrado del atado. Tablas aparte de AtaMaquinas / AtaActividades.
-- Correr en ProdTowel antes de usar la pantalla de atado de barra.

IF OBJECT_ID('dbo.AtaKmMontado', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.AtaKmMontado (
        Id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_AtaKmMontado PRIMARY KEY,
        NoJulio NVARCHAR(50) NOT NULL,
        NoProduccion NVARCHAR(50) NOT NULL,
        CveEmpl1 NVARCHAR(30) NULL,
        NomEmpl1 NVARCHAR(150) NULL,
        CveEmpl2 NVARCHAR(30) NULL,
        NomEmpl2 NVARCHAR(150) NULL,
        CveEmpl3 NVARCHAR(30) NULL,
        NomEmpl3 NVARCHAR(150) NULL,
        FechaInicio DATETIME NULL,
        FechaFin DATETIME NULL,
        CONSTRAINT UQ_AtaKmMontado_JulioOrden UNIQUE (NoJulio, NoProduccion)
    );
END;

IF OBJECT_ID('dbo.AtaKmEnhebrado', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.AtaKmEnhebrado (
        Id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_AtaKmEnhebrado PRIMARY KEY,
        NoJulio NVARCHAR(50) NOT NULL,
        NoProduccion NVARCHAR(50) NOT NULL,
        CveEmpl1 NVARCHAR(30) NULL,
        NomEmpl1 NVARCHAR(150) NULL,
        CveEmpl2 NVARCHAR(30) NULL,
        NomEmpl2 NVARCHAR(150) NULL,
        CveEmpl3 NVARCHAR(30) NULL,
        NomEmpl3 NVARCHAR(150) NULL,
        FechaInicio DATETIME NULL,
        FechaFin DATETIME NULL,
        CONSTRAINT UQ_AtaKmEnhebrado_JulioOrden UNIQUE (NoJulio, NoProduccion)
    );
END;
