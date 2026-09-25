/*
 * Barras Karl Mayer: julios/ordenes 2-4 en AtaMontadoTelas y julio/orden principal
 * del telar en InvTelasReservadas. Se agregaron a mano en pruebas (.28) y faltaban
 * en produccion (.24). Tipos copiados de .28. Idempotente y compatible con 2008 R2.
 *
 * Usan estas columnas: AtadoresController (iniciar atado), AtaDevolucionesController,
 * InventarioReservasService::conJulioPrincipal.
 */
SET NOCOUNT ON;

IF COL_LENGTH('dbo.AtaMontadoTelas', 'no_julio2') IS NULL ALTER TABLE dbo.AtaMontadoTelas ADD no_julio2 NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.AtaMontadoTelas', 'no_julio3') IS NULL ALTER TABLE dbo.AtaMontadoTelas ADD no_julio3 NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.AtaMontadoTelas', 'no_julio4') IS NULL ALTER TABLE dbo.AtaMontadoTelas ADD no_julio4 NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.AtaMontadoTelas', 'no_orden2') IS NULL ALTER TABLE dbo.AtaMontadoTelas ADD no_orden2 NVARCHAR(50) NULL;
IF COL_LENGTH('dbo.AtaMontadoTelas', 'no_orden3') IS NULL ALTER TABLE dbo.AtaMontadoTelas ADD no_orden3 NVARCHAR(50) NULL;
IF COL_LENGTH('dbo.AtaMontadoTelas', 'no_orden4') IS NULL ALTER TABLE dbo.AtaMontadoTelas ADD no_orden4 NVARCHAR(50) NULL;

IF COL_LENGTH('dbo.InvTelasReservadas', 'JulioPrincipal') IS NULL ALTER TABLE dbo.InvTelasReservadas ADD JulioPrincipal NVARCHAR(20) NULL;
IF COL_LENGTH('dbo.InvTelasReservadas', 'OrdenPrincipal') IS NULL ALTER TABLE dbo.InvTelasReservadas ADD OrdenPrincipal NVARCHAR(20) NULL;
