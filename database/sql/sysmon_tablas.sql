/*
 * Monitoreo (fase 11) — espejo para el DBA de
 * database/migrations/2026_09_24_000001_create_sysmon_tables.php
 *
 * Contrato: .planning/phases/11-mon-servidor/11-CONTRACT.md §2.
 *
 * Dos formas de desplegar (usar UNA):
 *   a) php artisan migrate  y después solo la sección "ÍNDICES EXTRA" de abajo.
 *   b) Correr este script completo en lugar de la migración y registrar la migración
 *      como ejecutada:
 *        INSERT INTO dbo.migrations (migration, batch)
 *        VALUES ('2026_09_24_000001_create_sysmon_tables', (SELECT ISNULL(MAX(batch),0)+1 FROM dbo.migrations));
 *
 * Idempotente: cada objeto se crea solo si no existe.
 * Fechas en hora local de planta (America/Mexico_City), igual que el resto del ERP.
 * Sin llaves foráneas a propósito: las escrituras de monitoreo nunca deben fallar
 * por integridad referencial ni bloquear borrados en tablas de negocio.
 */

SET NOCOUNT ON;
GO

/* ───────────── SYSMonDispositivo ───────────── */
IF OBJECT_ID(N'dbo.SYSMonDispositivo', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SYSMonDispositivo (
        Id                  bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSMonDispositivo PRIMARY KEY,
        Uuid                char(36)       NOT NULL,              -- cookie towell_disp
        Nombre              nvarchar(80)   NULL,
        Tipo                nvarchar(20)   NOT NULL CONSTRAINT DF_SYSMonDispositivo_Tipo DEFAULT (N'desconocido'),
        Modelo              nvarchar(80)   NULL,
        SO                  nvarchar(60)   NULL,
        Navegador           nvarchar(60)   NULL,
        UaHash              char(40)       NOT NULL,              -- sha1(user agent)
        UltimaIp            nvarchar(45)   NOT NULL,
        UltimoUsuarioId     int            NULL,                  -- SYSUsuario.idusuario
        UltimaSesionId      bigint         NULL,
        PrimeraVez          datetime2(3)   NOT NULL,
        UltimaActividad     datetime2(3)   NOT NULL,
        UltimaRuta          nvarchar(150)  NULL,
        Visible             bit            NOT NULL CONSTRAINT DF_SYSMonDispositivo_Visible DEFAULT (1),
        InactivoSeg         int            NOT NULL CONSTRAINT DF_SYSMonDispositivo_InactivoSeg DEFAULT (0),
        VersionFront        nvarchar(40)   NULL,
        Pantalla            nvarchar(20)   NULL,
        CierreSolicitadoEn  datetime2(3)   NULL,
        CierreSolicitadoPor int            NULL
    );
    CREATE UNIQUE INDEX UX_SYSMonDispositivo_Uuid ON dbo.SYSMonDispositivo (Uuid);
    CREATE INDEX IX_SYSMonDispositivo_UltimaActividad ON dbo.SYSMonDispositivo (UltimaActividad);
END
GO

/* ───────────── SYSMonSesion ───────────── */
IF OBJECT_ID(N'dbo.SYSMonSesion', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SYSMonSesion (
        Id              bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSMonSesion PRIMARY KEY,
        DispositivoId   bigint         NOT NULL,
        UsuarioId       int            NOT NULL,
        Origen          nvarchar(12)   NOT NULL,   -- login | recordarme
        Ip              nvarchar(45)   NOT NULL,
        Inicio          datetime2(3)   NOT NULL,
        UltimaActividad datetime2(3)   NOT NULL,
        Fin             datetime2(3)   NULL,
        MotivoFin       nvarchar(12)   NULL        -- logout | remoto | expirada | reemplazada
    );
    CREATE INDEX IX_SYSMonSesion_Usuario_Inicio ON dbo.SYSMonSesion (UsuarioId, Inicio);
    CREATE INDEX IX_SYSMonSesion_Dispositivo_Inicio ON dbo.SYSMonSesion (DispositivoId, Inicio);
END
GO

/* ───────────── SYSMonVista ───────────── */
IF OBJECT_ID(N'dbo.SYSMonVista', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SYSMonVista (
        Id            bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSMonVista PRIMARY KEY,
        Uuid          char(36)       NOT NULL,
        SesionId      bigint         NULL,
        DispositivoId bigint         NOT NULL,
        UsuarioId     int            NOT NULL,
        Ruta          nvarchar(150)  NOT NULL,   -- nombre de ruta (o URI con placeholders)
        Url           nvarchar(300)  NOT NULL,   -- solo path, sin query string
        Tipo          nvarchar(6)    NOT NULL,   -- carga | suave
        Inicio        datetime2(3)   NOT NULL,
        Fin           datetime2(3)   NULL,
        VisibleMs     int NULL,
        ServidorMs    int NULL,
        ConsultasN    int NULL,
        ConsultasMs   int NULL,
        TtfbMs        int NULL,
        DomMs         int NULL,
        CargaMs       int NULL,
        Kb            int NULL
    );
    CREATE UNIQUE INDEX UX_SYSMonVista_Uuid ON dbo.SYSMonVista (Uuid);
    CREATE INDEX IX_SYSMonVista_Dispositivo_Inicio ON dbo.SYSMonVista (DispositivoId, Inicio);
END
GO

/* ───────────── SYSMonError ───────────── */
IF OBJECT_ID(N'dbo.SYSMonError', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SYSMonError (
        Id          bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSMonError PRIMARY KEY,
        Huella      char(40)        NOT NULL,   -- sha1(origen|clase|archivo|linea|mensaje normalizado)
        Origen      nvarchar(10)    NOT NULL,   -- php | js | livewire | red | http5xx
        Clase       nvarchar(200)   NOT NULL,
        Mensaje     nvarchar(1000)  NOT NULL,   -- saneado, sin bindings
        Archivo     nvarchar(300)   NULL,
        Linea       int             NULL,
        Ruta        nvarchar(150)   NULL,
        Estado      nvarchar(10)    NOT NULL CONSTRAINT DF_SYSMonError_Estado DEFAULT (N'nuevo'),
        Ocurrencias int             NOT NULL CONSTRAINT DF_SYSMonError_Ocurrencias DEFAULT (1),
        PrimeraVez  datetime2(3)    NOT NULL,
        UltimaVez   datetime2(3)    NOT NULL,
        ResueltoPor int             NULL,
        ResueltoEn  datetime2(3)    NULL,
        Nota        nvarchar(500)   NULL,
        AlertadoEn  datetime2(3)    NULL
    );
    CREATE UNIQUE INDEX UX_SYSMonError_Huella ON dbo.SYSMonError (Huella);
    CREATE INDEX IX_SYSMonError_Estado_UltimaVez ON dbo.SYSMonError (Estado, UltimaVez);
END
GO

/* ───────────── SYSMonErrorEvento ───────────── */
IF OBJECT_ID(N'dbo.SYSMonErrorEvento', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SYSMonErrorEvento (
        Id            bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSMonErrorEvento PRIMARY KEY,
        ErrorId       bigint         NOT NULL,
        Fecha         datetime2(3)   NOT NULL,
        UsuarioId     int            NULL,
        DispositivoId bigint         NULL,
        SesionId      bigint         NULL,
        Url           nvarchar(300)  NULL,
        Metodo        nvarchar(8)    NULL,
        Status        smallint       NULL,
        VersionFront  nvarchar(40)   NULL,
        Traza         nvarchar(max)  NULL        -- <= 8 KB: stack + NOMBRES de llaves del input
    );
    CREATE INDEX IX_SYSMonErrorEvento_Error_Fecha ON dbo.SYSMonErrorEvento (ErrorId, Fecha);
END
GO

/* ───────────── SYSMonAcceso ───────────── */
IF OBJECT_ID(N'dbo.SYSMonAcceso', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SYSMonAcceso (
        Id             bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSMonAcceso PRIMARY KEY,
        Fecha          datetime2(3)   NOT NULL,
        Tipo           nvarchar(20)   NOT NULL,  -- login | login_fallido | logout | logout_remoto | recordarme | bloqueo | authz_denegaria | admin_accion
        NumeroEmpleado nvarchar(20)   NULL,      -- lo tecleado
        UsuarioId      int            NULL,
        DispositivoId  bigint         NULL,
        Ip             nvarchar(45)   NOT NULL,
        Motivo         nvarchar(200)  NULL,
        ActorId        int            NULL       -- admin que ejecutó la acción
    );
    CREATE INDEX IX_SYSMonAcceso_Fecha ON dbo.SYSMonAcceso (Fecha);
    CREATE INDEX IX_SYSMonAcceso_Tipo_Fecha ON dbo.SYSMonAcceso (Tipo, Fecha);
    CREATE INDEX IX_SYSMonAcceso_Usuario_Fecha ON dbo.SYSMonAcceso (UsuarioId, Fecha);
END
GO

/* ═════════════ ÍNDICES EXTRA (solo SQL Server) ═════════════
 * La migración no los puede expresar de forma portable. Correr también si se
 * desplegó con `php artisan migrate`.
 */

-- Sesiones abiertas: el panel "en línea" y el cierre de expiradas filtran por Fin IS NULL.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_SYSMonSesion_Abiertas' AND object_id = OBJECT_ID(N'dbo.SYSMonSesion'))
    CREATE INDEX IX_SYSMonSesion_Abiertas ON dbo.SYSMonSesion (DispositivoId, UltimaActividad)
        INCLUDE (UsuarioId, Inicio)
        WHERE Fin IS NULL;
GO

-- Rendimiento por pantalla: reemplaza el índice simple de la migración por uno con INCLUDE.
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_SYSMonVista_Ruta_Inicio' AND object_id = OBJECT_ID(N'dbo.SYSMonVista'))
   AND NOT EXISTS (
       SELECT 1 FROM sys.index_columns ic
       JOIN sys.indexes i ON i.object_id = ic.object_id AND i.index_id = ic.index_id
       WHERE i.name = N'IX_SYSMonVista_Ruta_Inicio' AND i.object_id = OBJECT_ID(N'dbo.SYSMonVista') AND ic.is_included_column = 1
   )
    DROP INDEX IX_SYSMonVista_Ruta_Inicio ON dbo.SYSMonVista;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_SYSMonVista_Ruta_Inicio' AND object_id = OBJECT_ID(N'dbo.SYSMonVista'))
    CREATE INDEX IX_SYSMonVista_Ruta_Inicio ON dbo.SYSMonVista (Ruta, Inicio)
        INCLUDE (CargaMs, ServidorMs);
GO
