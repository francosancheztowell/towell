/*
    Catalogo de hilos para la captura de desarrolladores.  Base: ProdTowel.

    Calibre e Hilo eran texto libre en la tabla "Detalles de la Orden". Son las dos
    caras del mismo hilo -Calibre es el codigo (10.1 = 10/1) e Hilo el divisor que usa
    la formula de L.Mat (10.00)- y al capturarse por separado se desparejaban: en
    produccion el codigo 10.1 convive con diez divisores distintos, de 10 hasta 960.

    Sin GO: todo va en un solo lote, envuelto en un IF, asi que es idempotente.
    Selecciona la base ProdTowel en tu cliente antes de ejecutar.

    OJO - ocho divisores son calculados y NO tienen respaldo real:
      1/32T, 100/1, 150/3T, 167/1, 360/1, 68/1, 70/1, 75/1
    Son hilos de filamento, donde la division no aplica (600/1 divide entre 8.86, no
    entre 600). Confirmar con Planeacion, o marcarlos Vigente = 0 hasta entonces.
*/

IF OBJECT_ID(N'dbo.TejCatMatrizDesarrolladores', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.TejCatMatrizDesarrolladores (
        Id            INT IDENTITY(1,1) NOT NULL,
        -- ItemId de AX, tal cual: 10/1T. Solo para mostrar; NUNCA se guarda en CatCodificados.
        Codigo        NVARCHAR(20)  NOT NULL,
        -- Lo que se escribe en CalibreTrama / CalibreComb{N}.
        CodigoInterno NVARCHAR(20)  NOT NULL,
        -- Lo que se escribe en CalibreTrama2 / CalibreComb{N}2.
        Divisor       FLOAT         NOT NULL,
        Nombre        NVARCHAR(60)  NOT NULL,
        Vigente       BIT           NOT NULL CONSTRAINT DF_TejCatMatrizDesarrolladores_Vigente DEFAULT (1),
        created_at    DATETIME      NULL,
        updated_at    DATETIME      NULL,
        CONSTRAINT PK_TejCatMatrizDesarrolladores PRIMARY KEY CLUSTERED (Id)
    );

    CREATE UNIQUE INDEX UX_TejCatMatrizDesarrolladores_Codigo
        ON dbo.TejCatMatrizDesarrolladores (Codigo);

    -- Llave de lectura: con el par (calibre, hilo) que trae la orden se resuelve
    -- que hilo del catalogo hay que preseleccionar en el desplegable.
    CREATE INDEX IX_TejCatMatrizDesarrolladores_Par
        ON dbo.TejCatMatrizDesarrolladores (CodigoInterno, Divisor);

    INSERT INTO dbo.TejCatMatrizDesarrolladores
        (Codigo, CodigoInterno, Divisor, Nombre, Vigente, created_at, updated_at)
    VALUES
        (N'1/32T', N'1.32', 0.03, N'HILO 1/32 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'10/1', N'10.1', 10, N'HILO 10/1', 1, GETDATE(), GETDATE()),
        (N'10/1T', N'10.1', 10, N'HILO 10/1 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'10/4', N'10.4', 2.5, N'HILO 10/4', 1, GETDATE(), GETDATE()),
        (N'10/4T', N'10.4', 2.5, N'HILO 10/4 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'100/1', N'100.1', 100, N'HILO 100/1', 1, GETDATE(), GETDATE()),
        (N'12/1', N'12.1', 12, N'HILO 12/1', 1, GETDATE(), GETDATE()),
        (N'12/1T', N'12.1', 12, N'HILO 12/1 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'12/4', N'12.4', 3, N'HILO 12/4', 1, GETDATE(), GETDATE()),
        (N'12/4T', N'12.4', 3, N'HILO 12/4 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'13/1', N'13.1', 13, N'HILO 13/1', 1, GETDATE(), GETDATE()),
        (N'14/1', N'14.1', 14, N'HILO 14/1', 1, GETDATE(), GETDATE()),
        (N'14/1T', N'14.1', 14, N'HILO 14/1 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'14/2', N'14.2', 7, N'HILO 14/2', 1, GETDATE(), GETDATE()),
        (N'14/2T', N'14.2', 7, N'HILO 14/2 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'14/4', N'14.4', 3.5, N'HILO 14/4 TORCIDO', 1, GETDATE(), GETDATE()),
        (N'14/4T', N'14.4', 3.5, N'14/4T', 1, GETDATE(), GETDATE()),
        (N'150/3T', N'150.3', 50, N'150/3T', 1, GETDATE(), GETDATE()),
        (N'16/1', N'16.1', 16, N'HILO 16/1', 1, GETDATE(), GETDATE()),
        (N'16/1T', N'16.1', 16, N'HILO 16/1 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'16/2', N'16.2', 8, N'HILO 16/2', 1, GETDATE(), GETDATE()),
        (N'16/2T', N'16.2', 8, N'HILO 16/2 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'167/1', N'167.1', 167, N'HILO 167/1', 1, GETDATE(), GETDATE()),
        (N'18/1', N'18.1', 18, N'HILO 18/1', 1, GETDATE(), GETDATE()),
        (N'18/2', N'18.2', 9, N'HILO 18/2', 1, GETDATE(), GETDATE()),
        (N'18/2T', N'18.2', 9, N'HILO 18/2 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'20/1', N'20.1', 20, N'HILO 20/1', 1, GETDATE(), GETDATE()),
        (N'20/2', N'20.2', 10, N'HILO 20/2', 1, GETDATE(), GETDATE()),
        (N'20/2T', N'20.2', 10, N'HILO 20/2 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'24/2', N'24.2', 12, N'HILO 24/2', 1, GETDATE(), GETDATE()),
        (N'24/2T', N'24.2', 12, N'HILO 24/2 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'30/2', N'30.2', 15, N'HCOST 30/2', 1, GETDATE(), GETDATE()),
        (N'300/1', N'300.1', 11.82, N'HILO 300', 1, GETDATE(), GETDATE()),
        (N'300/1T', N'300.1', 11.82, N'HILO 300 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'360/1', N'360.1', 360, N'HILO 360', 1, GETDATE(), GETDATE()),
        (N'370/1', N'370.1', 11.81, N'HILO 370', 1, GETDATE(), GETDATE()),
        (N'4/2', N'4.2', 2, N'HILO 4/2', 1, GETDATE(), GETDATE()),
        (N'4/2T', N'4.2', 2, N'HILO 4/2 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'450/1', N'450.1', 11.81, N'HILO 450', 1, GETDATE(), GETDATE()),
        (N'450/1T', N'450.1', 11.81, N'HILO 450 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'600/1', N'600.1', 8.86, N'HILO 600', 1, GETDATE(), GETDATE()),
        (N'600/1T', N'600.1', 8.86, N'HILO 600 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'68/1', N'68.1', 68, N'HILO 68/1', 1, GETDATE(), GETDATE()),
        (N'70/1', N'70.1', 70, N'HILO 70/1', 1, GETDATE(), GETDATE()),
        (N'75/1', N'75.1', 75, N'HILO 75/1', 1, GETDATE(), GETDATE()),
        (N'8/1', N'8.1', 8, N'HILO 8/1', 1, GETDATE(), GETDATE()),
        (N'8/1T', N'8.1', 8, N'HILO 8/1 TEÑIDO', 1, GETDATE(), GETDATE()),
        (N'L10/1', N'10.1', 10, N'HILO LYCRA 10/1', 1, GETDATE(), GETDATE()),
        (N'O14/1', N'14.1', 14, N'HILO O14/1', 1, GETDATE(), GETDATE());
END;

SELECT COUNT(*) AS Hilos, SUM(CAST(Vigente AS INT)) AS Vigentes
  FROM dbo.TejCatMatrizDesarrolladores;
