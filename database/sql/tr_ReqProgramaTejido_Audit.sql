/*
  tr_ReqProgramaTejido_Audit

  Unico trigger que escribe en dbo.SYSAuditoria. Audita SOLO dbo.ReqProgramaTejido.

  Que registra
    UPDATE  una fila SOLO si cambio alguna de las 16 columnas relevantes.
            Si no cambio ninguna NO inserta nada (antes insertaba una fila vacia
            por cada UPDATE: 32,222 de 41,941 filas eran ruido).
    INSERT  snapshot del alta: Orden, Salon, Telar, TotalPedido, EnProceso.
    DELETE  snapshot de la baja, mismo formato.

  De donde sale el "por que"
    dbo.sp_SetAppContext escribe en CONTEXT_INFO 'acc=<CONTEXTO>;uid=..;ip=..;user=..'.
    El middleware SetSqlContextInfo lo llama en cada request y
    AuditoriaHelper::contexto('LIBERAR') lo re-sella antes de cada operacion de negocio.
    Si no viene contexto y APP_NAME() es Dynamics AX se marca Contexto=AX.

  Formato de salida
    PK      'Id=603 | Orden=TW-12345'
    Detalle 'LIBERAR | TotalPedido: 6113.00 -> 6106.00; NoTelarId: 12 -> 15'

  Reglas que este trigger NO puede romper
    1. Nunca abortar la escritura que audita. Por eso STR en los floats: un CAST a DECIMAL
       con un valor fuera de rango lanza overflow y revierte el UPDATE del negocio.
    2. Nunca perder un cambio. Por eso NVARCHAR(200) en texto (Prioridad es nvarchar(150))
       y comparacion NULL-aware explicita en vez de centinelas tipo ISNULL(x, '~').
    3. El servidor es SQL Server 2008 R2 (compat 100): nada de TRY_CAST, TRY_CONVERT,
       FORMAT, STRING_AGG ni THROW.

  Sin comentarios de linea (--) a proposito: si el editor colapsa los saltos
  de linea, un -- comentaria el resto del batch.
  Rollback: database/sql/tr_ReqProgramaTejido_Audit_rollback.sql
*/
ALTER TRIGGER dbo.tr_ReqProgramaTejido_Audit
ON dbo.ReqProgramaTejido
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @ctx VARCHAR(128);
    /* REPLACE CHAR(0): quita el padding del VARBINARY(128) fijo de CONTEXT_INFO */
    SET @ctx = REPLACE(CONVERT(VARCHAR(128), CONTEXT_INFO()), CHAR(0), '');

    DECLARE @Usuario VARCHAR(120);
    DECLARE @UsuarioId INT;
    DECLARE @IP VARCHAR(64);
    DECLARE @Contexto VARCHAR(40);
    DECLARE @p INT;
    DECLARE @s INT;
    DECLARE @tmp VARCHAR(50);
    DECLARE @Accion VARCHAR(10);

    SET @Usuario = NULL;
    SET @UsuarioId = NULL;
    SET @IP = NULL;
    SET @Contexto = NULL;

    SET @p = CHARINDEX('acc=', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX(';', @ctx, @p);
        SET @Contexto = SUBSTRING(@ctx, @p + 4, CASE WHEN @s > 0 THEN @s - (@p + 4) ELSE 40 END);
        IF LTRIM(RTRIM(@Contexto)) = '' SET @Contexto = NULL;
    END

    SET @p = CHARINDEX('uid=', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX(';', @ctx, @p);
        SET @tmp = SUBSTRING(@ctx, @p + 4, CASE WHEN @s > 0 THEN @s - (@p + 4) ELSE 20 END);
        IF ISNUMERIC(@tmp) = 1
            SET @UsuarioId = CAST(@tmp AS INT);
    END

    SET @p = CHARINDEX('user=', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX(';', @ctx, @p);
        SET @Usuario = SUBSTRING(@ctx, @p + 5, CASE WHEN @s > 0 THEN @s - (@p + 5) ELSE 120 END);
        IF LTRIM(RTRIM(@Usuario)) = '' SET @Usuario = NULL;
    END

    SET @p = CHARINDEX('ip=', @ctx);
    IF @p > 0
    BEGIN
        SET @s = CHARINDEX(';', @ctx, @p);
        SET @IP = SUBSTRING(@ctx, @p + 3, CASE WHEN @s > 0 THEN @s - (@p + 3) ELSE 64 END);
        IF LTRIM(RTRIM(@IP)) = '' SET @IP = NULL;
    END

    IF @Contexto IS NULL AND APP_NAME() LIKE '%Dynamics AX%'
        SET @Contexto = 'AX';
    IF @Contexto IS NULL
        SET @Contexto = 'SIN CONTEXTO';

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
        'Id=' + CONVERT(VARCHAR(50), COALESCE(i.Id, d.Id))
            + ' | Orden=' + ISNULL(NULLIF(LTRIM(RTRIM(CONVERT(NVARCHAR(50), COALESCE(i.NoProduccion, d.NoProduccion)))), ''), 'SIN LIBERAR'),
        @UsuarioId,
        COALESCE(@Usuario, SUSER_SNAME()),
        HOST_NAME(),
        APP_NAME(),
        @IP,
        @Contexto + ' | ' + dif.Detalle
    FROM inserted i
    FULL JOIN deleted d ON i.Id = d.Id
    CROSS APPLY (
        SELECT
            NoProduccion_ant = LTRIM(RTRIM(CONVERT(NVARCHAR(200), d.NoProduccion))),
            NoProduccion_new = LTRIM(RTRIM(CONVERT(NVARCHAR(200), i.NoProduccion))),
            TotalPedido_ant = LTRIM(STR(d.TotalPedido, 30, 2)),
            TotalPedido_new = LTRIM(STR(i.TotalPedido, 30, 2)),
            SaldoPedido_ant = LTRIM(STR(d.SaldoPedido, 30, 2)),
            SaldoPedido_new = LTRIM(STR(i.SaldoPedido, 30, 2)),
            Produccion_ant = LTRIM(STR(d.Produccion, 30, 2)),
            Produccion_new = LTRIM(STR(i.Produccion, 30, 2)),
            NoTelarId_ant = LTRIM(RTRIM(CONVERT(NVARCHAR(200), d.NoTelarId))),
            NoTelarId_new = LTRIM(RTRIM(CONVERT(NVARCHAR(200), i.NoTelarId))),
            SalonTejidoId_ant = LTRIM(RTRIM(CONVERT(NVARCHAR(200), d.SalonTejidoId))),
            SalonTejidoId_new = LTRIM(RTRIM(CONVERT(NVARCHAR(200), i.SalonTejidoId))),
            Posicion_ant = CONVERT(NVARCHAR(20), d.Posicion),
            Posicion_new = CONVERT(NVARCHAR(20), i.Posicion),
            FechaInicio_ant = CONVERT(NVARCHAR(16), d.FechaInicio, 120),
            FechaInicio_new = CONVERT(NVARCHAR(16), i.FechaInicio, 120),
            FechaFinal_ant = CONVERT(NVARCHAR(16), d.FechaFinal, 120),
            FechaFinal_new = CONVERT(NVARCHAR(16), i.FechaFinal, 120),
            EnProceso_ant = CONVERT(NVARCHAR(5), d.EnProceso),
            EnProceso_new = CONVERT(NVARCHAR(5), i.EnProceso),
            Programado_ant = CONVERT(NVARCHAR(10), d.Programado, 23),
            Programado_new = CONVERT(NVARCHAR(10), i.Programado, 23),
            TamanoClave_ant = LTRIM(RTRIM(CONVERT(NVARCHAR(200), d.TamanoClave))),
            TamanoClave_new = LTRIM(RTRIM(CONVERT(NVARCHAR(200), i.TamanoClave))),
            Prioridad_ant = LTRIM(RTRIM(CONVERT(NVARCHAR(200), d.Prioridad))),
            Prioridad_new = LTRIM(RTRIM(CONVERT(NVARCHAR(200), i.Prioridad))),
            CalendarioId_ant = LTRIM(RTRIM(CONVERT(NVARCHAR(200), d.CalendarioId))),
            CalendarioId_new = LTRIM(RTRIM(CONVERT(NVARCHAR(200), i.CalendarioId))),
            Reprogramar_ant = LTRIM(RTRIM(CONVERT(NVARCHAR(200), d.Reprogramar))),
            Reprogramar_new = LTRIM(RTRIM(CONVERT(NVARCHAR(200), i.Reprogramar))),
            OrdCompartida_ant = CONVERT(NVARCHAR(20), d.OrdCompartida),
            OrdCompartida_new = CONVERT(NVARCHAR(20), i.OrdCompartida)
    ) f
    CROSS APPLY (
        SELECT Detalle =
            CASE @Accion
                WHEN 'INSERT' THEN
                    'Orden=' + ISNULL(NULLIF(LTRIM(RTRIM(i.NoProduccion)), ''), 'SIN LIBERAR')
                   + ' | Salon=' + ISNULL(NULLIF(LTRIM(RTRIM(i.SalonTejidoId)), ''), 'N/A')
                   + ' | Telar=' + ISNULL(NULLIF(LTRIM(RTRIM(i.NoTelarId)), ''), 'N/A')
                   + ' | TotalPedido=' + ISNULL(LTRIM(STR(i.TotalPedido, 30, 2)), '0')
                   + ' | EnProceso=' + ISNULL(CONVERT(NVARCHAR(5), i.EnProceso), '0')
                WHEN 'DELETE' THEN
                    'Orden=' + ISNULL(NULLIF(LTRIM(RTRIM(d.NoProduccion)), ''), 'SIN LIBERAR')
                   + ' | Salon=' + ISNULL(NULLIF(LTRIM(RTRIM(d.SalonTejidoId)), ''), 'N/A')
                   + ' | Telar=' + ISNULL(NULLIF(LTRIM(RTRIM(d.NoTelarId)), ''), 'N/A')
                   + ' | TotalPedido=' + ISNULL(LTRIM(STR(d.TotalPedido, 30, 2)), '0')
                   + ' | EnProceso=' + ISNULL(CONVERT(NVARCHAR(5), d.EnProceso), '0')
                ELSE
                    NULLIF(STUFF(
                CASE WHEN (f.NoProduccion_new IS NULL AND f.NoProduccion_ant IS NOT NULL)
                          OR (f.NoProduccion_new IS NOT NULL AND f.NoProduccion_ant IS NULL)
                          OR f.NoProduccion_new <> f.NoProduccion_ant
                       THEN '; NoProduccion: ' + ISNULL(f.NoProduccion_ant, '(vacio)') + ' -> ' + ISNULL(f.NoProduccion_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.TotalPedido_new IS NULL AND f.TotalPedido_ant IS NOT NULL)
                          OR (f.TotalPedido_new IS NOT NULL AND f.TotalPedido_ant IS NULL)
                          OR f.TotalPedido_new <> f.TotalPedido_ant
                       THEN '; TotalPedido: ' + ISNULL(f.TotalPedido_ant, '(vacio)') + ' -> ' + ISNULL(f.TotalPedido_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.SaldoPedido_new IS NULL AND f.SaldoPedido_ant IS NOT NULL)
                          OR (f.SaldoPedido_new IS NOT NULL AND f.SaldoPedido_ant IS NULL)
                          OR f.SaldoPedido_new <> f.SaldoPedido_ant
                       THEN '; SaldoPedido: ' + ISNULL(f.SaldoPedido_ant, '(vacio)') + ' -> ' + ISNULL(f.SaldoPedido_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.Produccion_new IS NULL AND f.Produccion_ant IS NOT NULL)
                          OR (f.Produccion_new IS NOT NULL AND f.Produccion_ant IS NULL)
                          OR f.Produccion_new <> f.Produccion_ant
                       THEN '; Produccion: ' + ISNULL(f.Produccion_ant, '(vacio)') + ' -> ' + ISNULL(f.Produccion_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.NoTelarId_new IS NULL AND f.NoTelarId_ant IS NOT NULL)
                          OR (f.NoTelarId_new IS NOT NULL AND f.NoTelarId_ant IS NULL)
                          OR f.NoTelarId_new <> f.NoTelarId_ant
                       THEN '; NoTelarId: ' + ISNULL(f.NoTelarId_ant, '(vacio)') + ' -> ' + ISNULL(f.NoTelarId_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.SalonTejidoId_new IS NULL AND f.SalonTejidoId_ant IS NOT NULL)
                          OR (f.SalonTejidoId_new IS NOT NULL AND f.SalonTejidoId_ant IS NULL)
                          OR f.SalonTejidoId_new <> f.SalonTejidoId_ant
                       THEN '; SalonTejidoId: ' + ISNULL(f.SalonTejidoId_ant, '(vacio)') + ' -> ' + ISNULL(f.SalonTejidoId_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.Posicion_new IS NULL AND f.Posicion_ant IS NOT NULL)
                          OR (f.Posicion_new IS NOT NULL AND f.Posicion_ant IS NULL)
                          OR f.Posicion_new <> f.Posicion_ant
                       THEN '; Posicion: ' + ISNULL(f.Posicion_ant, '(vacio)') + ' -> ' + ISNULL(f.Posicion_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.FechaInicio_new IS NULL AND f.FechaInicio_ant IS NOT NULL)
                          OR (f.FechaInicio_new IS NOT NULL AND f.FechaInicio_ant IS NULL)
                          OR f.FechaInicio_new <> f.FechaInicio_ant
                       THEN '; FechaInicio: ' + ISNULL(f.FechaInicio_ant, '(vacio)') + ' -> ' + ISNULL(f.FechaInicio_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.FechaFinal_new IS NULL AND f.FechaFinal_ant IS NOT NULL)
                          OR (f.FechaFinal_new IS NOT NULL AND f.FechaFinal_ant IS NULL)
                          OR f.FechaFinal_new <> f.FechaFinal_ant
                       THEN '; FechaFinal: ' + ISNULL(f.FechaFinal_ant, '(vacio)') + ' -> ' + ISNULL(f.FechaFinal_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.EnProceso_new IS NULL AND f.EnProceso_ant IS NOT NULL)
                          OR (f.EnProceso_new IS NOT NULL AND f.EnProceso_ant IS NULL)
                          OR f.EnProceso_new <> f.EnProceso_ant
                       THEN '; EnProceso: ' + ISNULL(f.EnProceso_ant, '(vacio)') + ' -> ' + ISNULL(f.EnProceso_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.Programado_new IS NULL AND f.Programado_ant IS NOT NULL)
                          OR (f.Programado_new IS NOT NULL AND f.Programado_ant IS NULL)
                          OR f.Programado_new <> f.Programado_ant
                       THEN '; Programado: ' + ISNULL(f.Programado_ant, '(vacio)') + ' -> ' + ISNULL(f.Programado_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.TamanoClave_new IS NULL AND f.TamanoClave_ant IS NOT NULL)
                          OR (f.TamanoClave_new IS NOT NULL AND f.TamanoClave_ant IS NULL)
                          OR f.TamanoClave_new <> f.TamanoClave_ant
                       THEN '; TamanoClave: ' + ISNULL(f.TamanoClave_ant, '(vacio)') + ' -> ' + ISNULL(f.TamanoClave_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.Prioridad_new IS NULL AND f.Prioridad_ant IS NOT NULL)
                          OR (f.Prioridad_new IS NOT NULL AND f.Prioridad_ant IS NULL)
                          OR f.Prioridad_new <> f.Prioridad_ant
                       THEN '; Prioridad: ' + ISNULL(f.Prioridad_ant, '(vacio)') + ' -> ' + ISNULL(f.Prioridad_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.CalendarioId_new IS NULL AND f.CalendarioId_ant IS NOT NULL)
                          OR (f.CalendarioId_new IS NOT NULL AND f.CalendarioId_ant IS NULL)
                          OR f.CalendarioId_new <> f.CalendarioId_ant
                       THEN '; CalendarioId: ' + ISNULL(f.CalendarioId_ant, '(vacio)') + ' -> ' + ISNULL(f.CalendarioId_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.Reprogramar_new IS NULL AND f.Reprogramar_ant IS NOT NULL)
                          OR (f.Reprogramar_new IS NOT NULL AND f.Reprogramar_ant IS NULL)
                          OR f.Reprogramar_new <> f.Reprogramar_ant
                       THEN '; Reprogramar: ' + ISNULL(f.Reprogramar_ant, '(vacio)') + ' -> ' + ISNULL(f.Reprogramar_new, '(vacio)')
                       ELSE '' END
                +                  CASE WHEN (f.OrdCompartida_new IS NULL AND f.OrdCompartida_ant IS NOT NULL)
                          OR (f.OrdCompartida_new IS NOT NULL AND f.OrdCompartida_ant IS NULL)
                          OR f.OrdCompartida_new <> f.OrdCompartida_ant
                       THEN '; OrdCompartida: ' + ISNULL(f.OrdCompartida_ant, '(vacio)') + ' -> ' + ISNULL(f.OrdCompartida_new, '(vacio)')
                       ELSE '' END
                    , 1, 2, ''), '')
            END
    ) dif
    WHERE dif.Detalle IS NOT NULL;
END
