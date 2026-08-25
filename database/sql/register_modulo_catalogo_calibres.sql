/*
    Alta del modulo "Catalogo Calibres" en el menu.  Base: ProdTowel.

    Cuelga de Tejedores > Configurar (idrol 142, orden 604) y apunta a la pantalla
    /tejedores/configurar/catalogo-calibres, que mantiene TejCatMatrizDesarrolladores:
    hasta ahora ese catalogo solo se poblaba por script, asi que corregir un divisor
    equivocado o dar de baja un hilo exigia entrar a SQL Server. Un divisor mal puesto
    no falla ni avisa: L.Mat calcula peso 0 para esa trama y el rizo absorbe la
    diferencia, de modo que el dato tiene que poder mantenerlo Planeacion.

    Los permisos se copian de "Configurar": quien ya administra la configuracion de
    tejedores mantiene este catalogo. Al resto se le crea el renglon en ceros -igual
    que hace ModulosController al crear un modulo- para que aparezca en Gestion de
    Modulos y se pueda asignar sin volver aqui.

    'eliminar' queda en 0 a proposito: la pantalla no borra renglones. Dar de baja es
    poner Vigente = 0, que lo saca de los desplegables sin romper las ordenes viejas
    que siguen apuntando a ese codigo. Esa accion va con 'modificar'.

    Sin GO: todo va en un solo lote, envuelto en un IF, asi que es idempotente.
    Selecciona la base ProdTowel en tu cliente antes de ejecutar.

    DESPUES DE EJECUTAR, limpiar el cache de modulos o el menu no cambia:
        php artisan cache:clear && php artisan config:clear
*/

IF NOT EXISTS (SELECT 1 FROM dbo.SYSRoles WHERE Ruta = N'/tejedores/configurar/catalogo-calibres')
BEGIN
    DECLARE @idRol INT;

    -- idrol es IDENTITY: se deja que SQL Server lo asigne y se lee de vuelta.
    INSERT INTO dbo.SYSRoles (orden, modulo, Dependencia, Nivel, Ruta, acceso, crear, modificar, eliminar, reigstrar)
    VALUES (N'604-4', N'Catalogo Calibres', N'604', N'3', N'/tejedores/configurar/catalogo-calibres', 1, 1, 1, 0, 0);

    SET @idRol = SCOPE_IDENTITY();

    -- Un renglon por usuario, como al crear un modulo desde Gestion de Modulos.
    -- Hereda de "Configurar" (idrol 142) quien ya lo tenia con acceso.
    INSERT INTO dbo.SYSUsuariosRoles (idusuario, idrol, acceso, crear, modificar, eliminar, registrar, assigned_at)
    SELECT
        u.idusuario,
        @idRol,
        CASE WHEN p.acceso = 1 THEN 1 ELSE 0 END,
        CASE WHEN p.acceso = 1 THEN 1 ELSE 0 END,
        CASE WHEN p.acceso = 1 THEN 1 ELSE 0 END,
        0,
        0,
        GETDATE()
    FROM dbo.SYSUsuario u
    LEFT JOIN dbo.SYSUsuariosRoles p
        ON p.idusuario = u.idusuario AND p.idrol = 142
    WHERE NOT EXISTS (
        SELECT 1 FROM dbo.SYSUsuariosRoles x
        WHERE x.idusuario = u.idusuario AND x.idrol = @idRol
    );
END
GO

/*  Rollback:

    DECLARE @idRol INT = (SELECT idrol FROM dbo.SYSRoles WHERE Ruta = N'/tejedores/configurar/catalogo-calibres');
    DELETE FROM dbo.SYSUsuariosRoles WHERE idrol = @idRol;
    DELETE FROM dbo.SYSRoles         WHERE idrol = @idRol;
*/
