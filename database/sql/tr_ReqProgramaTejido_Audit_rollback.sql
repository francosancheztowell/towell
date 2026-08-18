-- Definición previa (revertir con esto).

ALTER TRIGGER dbo.tr_ReqProgramaTejido_Audit
ON dbo.ReqProgramaTejido
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @ctx VARCHAR(128);
    SET @ctx = CONVERT(VARCHAR(128), CONTEXT_INFO());

    DECLARE @Usuario VARCHAR(120);
    DECLARE @UsuarioId INT;
    DECLARE @IP VARCHAR(64);

    SET @Usuario = NULL;
    SET @UsuarioId = NULL;
    SET @IP = NULL;

    -- Parse uid
    DECLARE @p INT; 
    DECLARE @s INT;
    DECLARE @tmp VARCHAR(50);

    SET @p = CHARINDEX('uid=', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX(';', @ctx, @p);
        SET @tmp = SUBSTRING(@ctx, @p + 4, CASE WHEN @s > 0 THEN @s - (@p + 4) ELSE 20 END);

        IF ISNUMERIC(@tmp) = 1
            SET @UsuarioId = CAST(@tmp AS INT);
    END

    -- Parse user
    SET @p = CHARINDEX('user=', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX(';', @ctx, @p);
        SET @Usuario = SUBSTRING(@ctx, @p + 5, CASE WHEN @s > 0 THEN @s - (@p + 5) ELSE 120 END);
        IF LTRIM(RTRIM(@Usuario)) = '' SET @Usuario = NULL;
    END

    -- Parse ip
    SET @p = CHARINDEX('ip=', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX(';', @ctx, @p);
        SET @IP = SUBSTRING(@ctx, @p + 3, CASE WHEN @s > 0 THEN @s - (@p + 3) ELSE 64 END);
        IF LTRIM(RTRIM(@IP)) = '' SET @IP = NULL;
    END

    DECLARE @Accion VARCHAR(10);

    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
        SET @Accion = 'INSERT';
    ELSE IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
        SET @Accion = 'DELETE';
    ELSE
        SET @Accion = 'UPDATE';

    INSERT INTO dbo.SYSAuditoria(Tabla, Accion, PK, UsuarioId, Usuario, HostName, AppName, IP, Detalle)
    SELECT
        'ReqProgramaTejido',
        @Accion,
        'Id=' + CAST(COALESCE(i.Id, d.Id) AS VARCHAR(50)),
        @UsuarioId,
        COALESCE(@Usuario, SUSER_SNAME()),
        HOST_NAME(),
        APP_NAME(),
        @IP,
        CASE 
            WHEN @Accion='UPDATE' THEN 'UPDATE (auditoría por trigger).'
            WHEN @Accion='INSERT' THEN 'INSERT (auditoría por trigger).'
            ELSE 'DELETE (auditoría por trigger).'
        END
    FROM inserted i
    FULL JOIN deleted d ON i.Id = d.Id;
END


/*
  OJO: sp_SetAppContext vuelve a 3 parametros, pero AuditoriaHelper::contexto() lo llama
  con 4. La llamada fallara y quedara en el log de Laravel sin tumbar la operacion, pero
  para revertir de verdad hay que revertir tambien el codigo PHP.
*/
/* sp_SetAppContext: definicion previa (sin @Accion). */
ALTER PROCEDURE dbo.sp_SetAppContext
    @UsuarioId INT = NULL,
    @Usuario   VARCHAR(120) = NULL,
    @IP        VARCHAR(64) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @s VARCHAR(128);
    SET @s = 'uid=' + ISNULL(CAST(@UsuarioId AS VARCHAR(20)), '')
           + ';user=' + ISNULL(@Usuario, '')
           + ';ip=' + ISNULL(@IP, '');

    DECLARE @b VARBINARY(128);
    SET @b = CONVERT(VARBINARY(128), LEFT(@s, 128));

    SET CONTEXT_INFO @b;
END
