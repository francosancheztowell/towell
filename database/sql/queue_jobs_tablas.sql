/*
 * Cola `database` (18-03, PERF-13) — espejo para el DBA de
 *   database/migrations/2025_10_28_201715_create_jobs_table.php
 *   database/migrations/2025_10_28_222749_create_failed_jobs_table.php
 *
 * Los avisos de Telegram de atado terminado, montado de julio y solicitud de trama
 * salen por la cola. Si `dbo.jobs` no existe, la app los manda en línea (como antes)
 * y deja "no se pudo encolar" en el log: nada se pierde, pero la tablet vuelve a esperar.
 *
 * Idempotente: cada objeto se crea solo si no existe. Compatible con SQL Server 2008 R2.
 * Después de correrlo, registrar las migraciones como ejecutadas (bloque final) para que
 * un `php artisan migrate` futuro no intente crearlas otra vez.
 */

SET NOCOUNT ON;
GO

/* ───────────── jobs ───────────── */
IF OBJECT_ID(N'dbo.jobs', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.jobs (
        id              bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_jobs PRIMARY KEY,
        queue           nvarchar(255)        NOT NULL,
        payload         nvarchar(max)        NOT NULL,
        attempts        tinyint              NOT NULL,
        reserved_at     int                  NULL,
        available_at    int                  NOT NULL,
        created_at      int                  NOT NULL
    );
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'jobs_queue_index' AND object_id = OBJECT_ID(N'dbo.jobs'))
    CREATE INDEX jobs_queue_index ON dbo.jobs (queue);
GO

/* ───────────── failed_jobs (QUEUE_FAILED_DRIVER=database-uuids) ───────────── */
IF OBJECT_ID(N'dbo.failed_jobs', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.failed_jobs (
        id              bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_failed_jobs PRIMARY KEY,
        uuid            nvarchar(255)        NOT NULL,
        connection      nvarchar(max)        NOT NULL,
        queue           nvarchar(max)        NOT NULL,
        payload         nvarchar(max)        NOT NULL,
        exception       nvarchar(max)        NOT NULL,
        failed_at       datetime             NOT NULL CONSTRAINT DF_failed_jobs_failed_at DEFAULT (GETDATE())
    );
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'failed_jobs_uuid_unique' AND object_id = OBJECT_ID(N'dbo.failed_jobs'))
    CREATE UNIQUE INDEX failed_jobs_uuid_unique ON dbo.failed_jobs (uuid);
GO

/* ───────────── registrar las migraciones ───────────── */
IF OBJECT_ID(N'dbo.migrations', N'U') IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM dbo.migrations WHERE migration = '2025_10_28_201715_create_jobs_table')
        INSERT INTO dbo.migrations (migration, batch)
        SELECT '2025_10_28_201715_create_jobs_table', ISNULL(MAX(batch), 0) + 1 FROM dbo.migrations;

    IF NOT EXISTS (SELECT 1 FROM dbo.migrations WHERE migration = '2025_10_28_222749_create_failed_jobs_table')
        INSERT INTO dbo.migrations (migration, batch)
        SELECT '2025_10_28_222749_create_failed_jobs_table', ISNULL(MAX(batch), 0) + 1 FROM dbo.migrations;
END
GO

/* Comprobación */
SELECT name, create_date FROM sys.tables WHERE name IN (N'jobs', N'failed_jobs');
GO
