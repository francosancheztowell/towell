/*
  Purga el ruido historico de dbo.SYSAuditoria.

  Las 29,974 filas 'UPDATE en columnas no auditadas.' las dejo el trigger viejo:
  se disparaba en CADA update aunque no cambiara ninguna columna auditada.
  Cero informacion, 32% de la tabla.

  El trigger nuevo ya no las genera (WHERE dif.Detalle IS NOT NULL), asi que
  esto se corre UNA vez, despues de aplicar tr_ReqProgramaTejido_Audit.sql.

  NO borra los INSERT/DELETE sin snapshot: dicen poco pero dicen que algo paso.

  ponytail: un solo DELETE. La tabla son 31 MB; no vale la pena batchear.
*/
DELETE FROM dbo.SYSAuditoria
WHERE Detalle = 'UPDATE en columnas no auditadas.';
