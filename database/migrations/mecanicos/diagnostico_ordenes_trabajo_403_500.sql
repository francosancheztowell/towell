-- =============================================================================
-- Diagnostico y reparacion: 403 al agregar renglones + 500 en storeLinea
-- Modulo: Mecanicos > Ordenes de Trabajo  (SYSRoles.orden = 1101)
-- =============================================================================
-- Ejecutar en el servidor SQL que usa la aplicacion de 192.168.2.15.
-- Los bloques 1 a 4 son SOLO LECTURA. El bloque 5 modifica el esquema y es
-- idempotente. Revisa la salida de 1-4 antes de correr el 5.
-- =============================================================================

PRINT '===== Servidor y base en uso =====';
SELECT @@SERVERNAME AS servidor, DB_NAME() AS base;
GO


-- =============================================================================
-- 1. El nombre del modulo tiene que coincidir EXACTO con la constante de PHP
-- =============================================================================
-- OrdenesTrabajoMecaController::MODULO_PERMISO = 'Ordenes de Trabajo' (sin acento).
-- userPermissions() hace un match por nombre en minusculas, sin trim y sin
-- normalizar acentos: 'Ordenes de Trabajo ' u 'Ordenes de Trabajo' con tilde NO
-- coinciden y userCan() devuelve false para todas las acciones -> 403.

PRINT '===== 1. Modulos parecidos a "Ordenes de Trabajo" =====';
SELECT
    idrol,
    orden,
    modulo,
    '[' + modulo + ']'                                  AS con_delimitadores,
    LEN(modulo)                                         AS largo,
    DATALENGTH(modulo)                                  AS bytes,
    CASE WHEN modulo = 'Ordenes de Trabajo'
         THEN 'COINCIDE' ELSE 'NO COINCIDE' END         AS match_exacto_con_php,
    Nivel,
    Dependencia,
    Ruta
FROM dbo.SYSRoles
WHERE modulo LIKE '%rdenes%Trabajo%'
ORDER BY idrol;
GO


-- =============================================================================
-- 2. El usuario y sus permisos reales sobre ese modulo
-- =============================================================================
-- Cambia 3517 si estas revisando a otra persona.

PRINT '===== 2. Usuario 3517: existencia, area y permisos =====';
DECLARE @empleado VARCHAR(30) = '3517';

SELECT
    u.idusuario,
    '[' + u.numero_empleado + ']'   AS numero_empleado,
    u.nombre,
    '[' + ISNULL(u.area, '') + ']'  AS area,
    '[' + ISNULL(u.puesto, '') + ']' AS puesto,
    u.turno
FROM dbo.SYSUsuario u
WHERE LTRIM(RTRIM(u.numero_empleado)) = @empleado;

-- Permisos por modulo. Si no sale fila para 'Ordenes de Trabajo', el usuario
-- nunca paso por PermissionService::guardarPermisos() -> userCan() da false.
SELECT
    ro.idrol,
    ro.orden,
    ro.modulo,
    r.acceso,
    r.crear,
    r.modificar,
    r.eliminar,
    r.registrar
FROM dbo.SYSUsuario u
JOIN dbo.SYSUsuariosRoles r ON r.idusuario = u.idusuario
JOIN dbo.SYSRoles ro        ON ro.idrol = r.idrol
WHERE LTRIM(RTRIM(u.numero_empleado)) = @empleado
  AND ro.modulo LIKE '%rdenes%Trabajo%'
ORDER BY ro.idrol;
GO

-- Lectura del resultado del bloque 2:
--   * Sin fila del usuario            -> el 3517 no existe en ESTA base.
--   * Sin fila de permisos            -> abrir el modulo de Usuarios y guardar
--                                        permisos (crea las filas de todos los
--                                        modulos de una vez).
--   * crear = 0                       -> marcar "crear" en el modulo 1101.
--   * area = 'Tejedores'              -> ademas del permiso, aplica el guardia
--                                        de tejedor del controlador.


-- =============================================================================
-- 3. Cuidado: el 3517 puede ser solo un operador del catalogo, no un usuario
-- =============================================================================
-- ManOperadoresMantenimiento alimenta el select "Mecanico (capturando)".
-- Estar ahi NO da cuenta ni permisos: son tablas independientes.

PRINT '===== 3. El 3517 en el catalogo de operadores =====';
SELECT Id, CveEmpl, NomEmpl, Turno, Depto
FROM dbo.ManOperadoresMantenimiento
WHERE LTRIM(RTRIM(CveEmpl)) = '3517';
GO


-- =============================================================================
-- 4. Columnas que el codigo escribe y que la migracion manual pudo no aplicar
-- =============================================================================
-- storeLinea() inserta Turno, Fecha y comentarios. Si alguna falta en esta base,
-- el INSERT revienta con "Invalid column name" y el controlador responde 500.
-- Esa es la causa mas probable del 500 que aparece junto a los 403.

PRINT '===== 4. Esquema real de MecOrdenTrabajoLine =====';
SELECT
    c.name                          AS columna,
    t.name                          AS tipo,
    c.max_length,
    c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.MecOrdenTrabajoLine')
ORDER BY c.column_id;

PRINT '----- Columnas requeridas que faltan -----';
SELECT requerida
FROM (VALUES ('Turno'), ('Fecha'), ('comentarios'), ('Calificacion'),
             ('CveTejedor'), ('NomTejedor'), ('TotalMinutos')) AS v(requerida)
WHERE COL_LENGTH('dbo.MecOrdenTrabajoLine', requerida) IS NULL;
GO


-- =============================================================================
-- 5. REPARACION del esquema (idempotente) -- correr solo si el bloque 4 lista faltantes
-- =============================================================================

IF COL_LENGTH('dbo.MecOrdenTrabajoLine', 'Turno') IS NULL
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoLine ADD Turno INT NULL;
    PRINT 'Agregada MecOrdenTrabajoLine.Turno';
END
GO

IF COL_LENGTH('dbo.MecOrdenTrabajoLine', 'Fecha') IS NULL
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoLine ADD Fecha DATE NULL;
    PRINT 'Agregada MecOrdenTrabajoLine.Fecha';
END
GO

IF COL_LENGTH('dbo.MecOrdenTrabajoLine', 'comentarios') IS NULL
BEGIN
    ALTER TABLE dbo.MecOrdenTrabajoLine ADD comentarios VARCHAR(500) NULL;
    PRINT 'Agregada MecOrdenTrabajoLine.comentarios';
END
GO

PRINT '===== Listo. Repetir el bloque 4 para confirmar que no falta ninguna. =====';
GO
