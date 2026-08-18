/*
  Purga total de SYSAuditoria antes de activar el trigger nuevo.

  Motivo: de 41,941 filas, 32,222 decian solo 'UPDATE (auditoria por trigger).'
  (cero informacion) y el resto tenia el Detalle corrupto por el bug de orden
  de parametros de sp_LogEvento. No hay nada rescatable.

  Ejecutar UNA sola vez, despues de respaldar si se quiere conservar evidencia.
*/
TRUNCATE TABLE dbo.SYSAuditoria;
