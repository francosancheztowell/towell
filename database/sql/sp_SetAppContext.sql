/*
  sp_SetAppContext

  Sella CONTEXT_INFO para que tr_ReqProgramaTejido_Audit sepa QUIEN y POR QUE.
  Lo llama App\Http\Middleware\SetSqlContextInfo (una vez por request, sin @Accion)
  y App\Helpers\AuditoriaHelper::contexto('LIBERAR') antes de cada operacion de negocio.

  CONTEXT_INFO son 128 bytes fijos, por eso el orden importa: 'acc' va primero
  para que nunca se pierda al truncar, y 'user' va al final por ser lo prescindible.
  Presupuesto: acc 30 + uid 10 + ip 15 + user 50 = 124 chars.

  Sin comentarios de linea (--) a proposito, igual que el trigger.
*/
ALTER PROCEDURE dbo.sp_SetAppContext
    @UsuarioId INT = NULL,
    @Usuario   VARCHAR(120) = NULL,
    @IP        VARCHAR(64) = NULL,
    @Accion    VARCHAR(40) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @s VARCHAR(128);
    SET @s = 'acc=' + LEFT(ISNULL(@Accion, ''), 30)
           + ';uid=' + LEFT(ISNULL(CAST(@UsuarioId AS VARCHAR(20)), ''), 10)
           + ';ip=' + LEFT(ISNULL(@IP, ''), 15)
           + ';user=' + LEFT(ISNULL(@Usuario, ''), 50);

    DECLARE @b VARBINARY(128);
    SET @b = CONVERT(VARBINARY(128), LEFT(@s, 128));

    SET CONTEXT_INFO @b;
END
