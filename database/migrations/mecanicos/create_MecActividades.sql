-- Catálogo de actividades del módulo Mecánicos
IF OBJECT_ID(N'dbo.MecActividades', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.MecActividades (
        Id        INT IDENTITY(1,1) NOT NULL,
        Orden     INT NOT NULL,
        Actividad VARCHAR(100) NOT NULL,

        CONSTRAINT PK_MecActividades
            PRIMARY KEY CLUSTERED (Id)
    );

    CREATE INDEX IX_MecActividades_Orden
        ON dbo.MecActividades(Orden);
END
GO
