-- Calificación de julios montados (Tejedores > Calificar Julios, idrol 212).
-- Cada renglón es un defecto que el oficial encontró en un julio montado (AtaMontadoTelas).
-- Un julio puede tener varios defectos. El catálogo y la penalización son los de
-- Urdido/Engomado (CatDefectosUrdEng). La penalización se copia al calificar, como en
-- engomado, para que un cambio posterior en el catálogo no reescriba lo ya calificado.
-- Compatible con SQL Server 2008 R2.

USE ProdTowel;
GO

IF OBJECT_ID('dbo.TejCalificacionJulioMontados', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.TejCalificacionJulioMontados (
        Id                int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_TejCalificacionJulioMontados PRIMARY KEY,
        AtaMontadoTelasId int            NOT NULL
            CONSTRAINT FK_TejCalJulMont_AtaMontadoTelas REFERENCES dbo.AtaMontadoTelas (Id),
        DefectoId         int            NOT NULL
            CONSTRAINT FK_TejCalJulMont_CatDefectosUrdEng REFERENCES dbo.CatDefectosUrdEng (Id),
        Penalizacion      decimal(10,2)  NOT NULL,
        Fecha             datetime       NOT NULL
            CONSTRAINT DF_TejCalJulMont_Fecha DEFAULT (GETDATE()),
        NoEmpleado        nvarchar(20)   NOT NULL,   -- oficial que calificó (usuario en sesión)
        NomEmpleado       nvarchar(120)  NULL
    );

    -- Historial: calificaciones de los julios de un telar/tipo en los últimos 7 días.
    CREATE INDEX IX_TejCalJulMont_Montado_Fecha
        ON dbo.TejCalificacionJulioMontados (AtaMontadoTelasId, Fecha);

    PRINT 'Tabla TejCalificacionJulioMontados creada';
END
ELSE
    PRINT 'La tabla TejCalificacionJulioMontados ya existe';
GO
