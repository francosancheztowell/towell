-- Crear tabla de auditoría para UrdProgramaUrdido y EngProgramaEngomado.
-- Ejecutar en la misma base de datos donde están UrdProgramaUrdido y EngProgramaEngomado (conexión sqlsrv).

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'AuditoriaUrdEng')
BEGIN
    CREATE TABLE dbo.AuditoriaUrdEng (
        Id            INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        Tabla         VARCHAR(50)  NOT NULL,   -- 'UrdProgramaUrdido' | 'EngProgramaEngomado'
        RegistroId    INT          NOT NULL,   -- Id del registro en la tabla afectada
        Folio         VARCHAR(50)  NULL,       -- Folio del registro (trazabilidad)
        Accion        VARCHAR(10)  NOT NULL,   -- 'create' | 'update'
        Campos        VARCHAR(2000) NULL,      -- Ej. Cuenta: 10 -> 20, Calibre: 2.5 -> 3
        UsuarioId     INT          NULL,
        UsuarioNombre VARCHAR(100) NULL,
        CreatedAt     DATETIME     NOT NULL DEFAULT GETDATE()
    );

    CREATE INDEX IX_AuditoriaUrdEng_Tabla    ON dbo.AuditoriaUrdEng (Tabla);
    CREATE INDEX IX_AuditoriaUrdEng_Folio    ON dbo.AuditoriaUrdEng (Folio);
    CREATE INDEX IX_AuditoriaUrdEng_CreatedAt ON dbo.AuditoriaUrdEng (CreatedAt);
END
GO
