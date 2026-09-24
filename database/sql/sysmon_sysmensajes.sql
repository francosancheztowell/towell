/*
 * Monitoreo (fase 11) — espejo de
 * database/migrations/2026_09_24_000002_add_errores_sistema_to_sysmensajes.php
 *
 * Canal Telegram "ErroresSistema" (alertas de errores nuevos / regresiones).
 * Idempotente. Si se corre en lugar de la migración, registrarla:
 *   INSERT INTO dbo.migrations (migration, batch)
 *   VALUES ('2026_09_24_000002_add_errores_sistema_to_sysmensajes', (SELECT ISNULL(MAX(batch),0)+1 FROM dbo.migrations));
 *
 * Después, en Configuración > Mensajes marcar "Errores del sistema" a los
 * destinatarios de Sistemas (o directo: UPDATE dbo.SYSMensajes SET ErroresSistema = 1 WHERE Id IN (...)).
 */
IF COL_LENGTH(N'dbo.SYSMensajes', N'ErroresSistema') IS NULL
    ALTER TABLE dbo.SYSMensajes
        ADD ErroresSistema bit NOT NULL
            CONSTRAINT DF_SYSMensajes_ErroresSistema DEFAULT (0);
GO
