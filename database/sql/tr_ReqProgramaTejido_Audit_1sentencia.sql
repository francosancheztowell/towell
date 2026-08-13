EXEC sp_executesql N'ALTER TRIGGER dbo.tr_ReqProgramaTejido_Audit
ON dbo.ReqProgramaTejido
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @ctx VARCHAR(128);
    /* REPLACE CHAR(0): quita el padding del VARBINARY(128) fijo de CONTEXT_INFO */
    SET @ctx = REPLACE(CONVERT(VARCHAR(128), CONTEXT_INFO()), CHAR(0), '''');

    DECLARE @Usuario VARCHAR(120);
    DECLARE @UsuarioId INT;
    DECLARE @IP VARCHAR(64);
    DECLARE @p INT;
    DECLARE @s INT;
    DECLARE @tmp VARCHAR(50);
    DECLARE @Accion VARCHAR(10);

    SET @Usuario = NULL;
    SET @UsuarioId = NULL;
    SET @IP = NULL;

    SET @p = CHARINDEX(''uid='', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX('';'', @ctx, @p);
        SET @tmp = SUBSTRING(@ctx, @p + 4, CASE WHEN @s > 0 THEN @s - (@p + 4) ELSE 20 END);
        IF ISNUMERIC(@tmp) = 1
            SET @UsuarioId = CAST(@tmp AS INT);
    END

    SET @p = CHARINDEX(''user='', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX('';'', @ctx, @p);
        SET @Usuario = SUBSTRING(@ctx, @p + 5, CASE WHEN @s > 0 THEN @s - (@p + 5) ELSE 120 END);
        IF LTRIM(RTRIM(@Usuario)) = '''' SET @Usuario = NULL;
    END

    SET @p = CHARINDEX(''ip='', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX('';'', @ctx, @p);
        SET @IP = SUBSTRING(@ctx, @p + 3, CASE WHEN @s > 0 THEN @s - (@p + 3) ELSE 64 END);
        IF LTRIM(RTRIM(@IP)) = '''' SET @IP = NULL;
    END

    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
        SET @Accion = ''INSERT'';
    ELSE IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
        SET @Accion = ''DELETE'';
    ELSE
        SET @Accion = ''UPDATE'';

    INSERT INTO dbo.SYSAuditoria(Tabla, Accion, PK, UsuarioId, Usuario, HostName, AppName, IP, Detalle)
    SELECT
        ''ReqProgramaTejido'',
        @Accion,
        ''Id='' + CAST(COALESCE(i.Id, d.Id) AS VARCHAR(50)),
        @UsuarioId,
        COALESCE(@Usuario, SUSER_SNAME()),
        HOST_NAME(),
        APP_NAME(),
        @IP,
        CASE
            WHEN @Accion = ''INSERT'' THEN ''INSERT (auditoria por trigger).''
            WHEN @Accion = ''DELETE'' THEN ''DELETE (auditoria por trigger).''
            ELSE ISNULL(NULLIF(STUFF(
                CASE WHEN ISNULL(CAST(i.TotalPedido AS NVARCHAR(MAX)), '''') <> ISNULL(CAST(d.TotalPedido AS NVARCHAR(MAX)), '''')
                     THEN ''; TotalPedido: '' + ISNULL(CAST(d.TotalPedido AS NVARCHAR(MAX)), ''(null)'') + '' -> '' + ISNULL(CAST(i.TotalPedido AS NVARCHAR(MAX)), ''(null)'')
                     ELSE '''' END
              + CASE WHEN ISNULL(CAST(i.TamanoClave AS NVARCHAR(MAX)), '''') <> ISNULL(CAST(d.TamanoClave AS NVARCHAR(MAX)), '''')
                     THEN ''; TamanoClave: '' + ISNULL(CAST(d.TamanoClave AS NVARCHAR(MAX)), ''(null)'') + '' -> '' + ISNULL(CAST(i.TamanoClave AS NVARCHAR(MAX)), ''(null)'')
                     ELSE '''' END
              + CASE WHEN ISNULL(CAST(i.NoTelarId AS NVARCHAR(MAX)), '''') <> ISNULL(CAST(d.NoTelarId AS NVARCHAR(MAX)), '''')
                     THEN ''; NoTelarId: '' + ISNULL(CAST(d.NoTelarId AS NVARCHAR(MAX)), ''(null)'') + '' -> '' + ISNULL(CAST(i.NoTelarId AS NVARCHAR(MAX)), ''(null)'')
                     ELSE '''' END
            , 1, 2, ''''), ''''), ''UPDATE en columnas no auditadas.'')
        END
    FROM inserted i
    FULL JOIN deleted d ON i.Id = d.Id;
END';