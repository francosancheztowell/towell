-- Alta de máquinas de otras áreas para Órdenes de Trabajo de Mecánicos.
-- Catálogo: dbo.URDCatalogoMaquinas (MaquinaId nvarchar(20) PK, Nombre nvarchar(60), Departamento nvarchar(60)).
-- El select de "Nueva OT" agrupa por Departamento; Urdido/Engomado/BPM filtran por su
-- propio Departamento, así que estas filas no aparecen en esos módulos.
--
-- Idempotente: solo inserta los MaquinaId que todavía no existen.
-- Compatible con SQL Server 2008 R2 (constructor de filas VALUES).
--
-- Notas sobre la lista original:
--   * BOMCIST venía dos veces (BOMBA DE CISTERNA / BOMBA CISTERNA, ambas Prod Term):
--     MaquinaId es PK, se da de alta una sola vez.
--   * OTROS no se da de alta: el select ya tiene la opción "Otros (escribir máquina)".
--   * Se normalizaron espacios dobles/finales ("OVER LOOK  31", "OVER FLOW 1 ") y el
--     salto de línea de "PLANTA TRATADORA DE AGUA".

SET NOCOUNT ON;

BEGIN TRANSACTION;

INSERT INTO dbo.URDCatalogoMaquinas (MaquinaId, Nombre, Departamento)
SELECT v.MaquinaId, v.Nombre, v.Departamento
FROM (VALUES
    -- Corte Bata
    (N'OVERCO1',   N'CB MAQ OVER COLLARETE 1', N'Corte Bata'),
    (N'OVERCO2',   N'CB MAQ OVER COLLARETE 2', N'Corte Bata'),
    (N'OVERCO3',   N'CB MAQ OVER COLLARETE 3', N'Corte Bata'),
    (N'OVERCO4',   N'CB MAQ OVER COLLARETE 4', N'Corte Bata'),
    (N'OVERJ38',   N'CB MAQ OVER JACK 38', N'Corte Bata'),
    (N'OVERJ39',   N'CB MAQ OVER JACK 39', N'Corte Bata'),
    (N'OVERJ40',   N'CB MAQ OVER JACK 40', N'Corte Bata'),
    (N'OVERJ41',   N'CB MAQ OVER JACK 41', N'Corte Bata'),
    (N'OVERJ42',   N'CB MAQ OVER JACK 42', N'Corte Bata'),
    (N'RECT1 CB',  N'CB MAQ RECTA 1', N'Corte Bata'),
    (N'RECT2 CB',  N'CB MAQ RECTA 2', N'Corte Bata'),
    (N'RECT3 CB',  N'CB MAQ RECTA 3', N'Corte Bata'),
    (N'RECT4 CB',  N'CB MAQ RECTA 4', N'Corte Bata'),
    (N'RECT5 CB',  N'CB MAQ RECTA 5', N'Corte Bata'),
    (N'RECT6 CB',  N'CB MAQ RECTA 6', N'Corte Bata'),
    (N'RECT7 CB',  N'CB MAQ RECTA 7', N'Corte Bata'),
    (N'RECT8 CB',  N'CB MAQ RECTA 8', N'Corte Bata'),
    -- Costura
    (N'CORF',      N'ENRROLLADORA Y CORTADORA DE FELPA', N'Costura'),
    (N'CORT',      N'TEXPA CORTADORA', N'Costura'),
    (N'CVERT1',    N'CORT VERT 1', N'Costura'),
    (N'CVERT2',    N'CORT VERT 2', N'Costura'),
    (N'CVERT3',    N'CORT VERT 3', N'Costura'),
    (N'CVERT4',    N'CORT VERT 4', N'Costura'),
    (N'CVERT5',    N'CORT VERT 5', N'Costura'),
    (N'ELEV COST', N'ELEVADOR COSTURA', N'Costura'),
    (N'LONG1',     N'TEXPA LONGITUDINAL 1', N'Costura'),
    (N'LONG2',     N'TEXPA LONGITUDINAL 2', N'Costura'),
    (N'LONG3',     N'TEXPA LONGITUDINAL 3', N'Costura'),
    (N'OVERL31',   N'OVER LOOK 31', N'Costura'),
    (N'OVERL32',   N'OVER LOOK 32', N'Costura'),
    (N'OVERL33',   N'OVER LOOK 33', N'Costura'),
    (N'OVERL34',   N'OVER LOOK 34', N'Costura'),
    (N'PLAN1',     N'PLANCHA ESTAMPADORA 1', N'Costura'),
    (N'RECT1',     N'MAQ RECTA 1', N'Costura'),
    (N'RECT2',     N'MAQ RECTA 2', N'Costura'),
    (N'RECT3',     N'MAQ RECTA 3', N'Costura'),
    (N'RECT4',     N'MAQ RECTA 4', N'Costura'),
    (N'RECT5',     N'MAQ RECTA 5', N'Costura'),
    (N'RECT6',     N'MAQ RECTA 6', N'Costura'),
    (N'RECT7',     N'MAQ RECTA 7', N'Costura'),
    (N'RECT8',     N'MAQ RECTA 8', N'Costura'),
    (N'RECT9',     N'MAQ RECTA 9', N'Costura'),
    (N'RECT10',    N'MAQ RECTA 10', N'Costura'),
    (N'RECT11',    N'MAQ RECTA 11', N'Costura'),
    (N'RECT12',    N'MAQ RECTA 12', N'Costura'),
    (N'RECT13',    N'MAQ RECTA 13', N'Costura'),
    (N'RECT14',    N'MAQ RECTA 14', N'Costura'),
    (N'RECT15',    N'MAQ RECTA 15', N'Costura'),
    (N'RECT16',    N'MAQ RECTA 16', N'Costura'),
    (N'SELL1',     N'SELLADORA 1', N'Costura'),
    (N'SELL2',     N'SELLADORA 2', N'Costura'),
    (N'SELL3',     N'SELLADORA 3', N'Costura'),
    (N'SUBL',      N'SUBLIMADORA/CALANDRA', N'Costura'),
    -- Crudo
    (N'COMP30HP',  N'COMPRESOR 30 HP', N'Crudo'),
    (N'COMP50HP',  N'COMPRESOR 50 HP', N'Crudo'),
    (N'OVERL36',   N'MAQUINA DE COSER OVER LOOK 36', N'Crudo'),
    (N'REVS1',     N'REVISADORA 1', N'Crudo'),
    (N'REVS2',     N'REVISADORA 2', N'Crudo'),
    -- Mat Prim
    (N'MONISSAN',  N'MONTACARGAS NISSAN', N'Mat Prim'),
    -- Prod Term
    (N'BOMCIST',   N'BOMBA DE CISTERNA', N'Prod Term'),
    (N'COM15HP',   N'COMPRESOR CHICO (15 HP)', N'Prod Term'),
    (N'DETMET1',   N'DETECTOR DE METALES', N'Prod Term'),
    (N'SELL1 PT',  N'SELLADORA PT 1', N'Prod Term'),
    (N'SELL2 PT',  N'SELLADORA PT 2', N'Prod Term'),
    (N'SELL3 PT',  N'SELLADORA PT 3', N'Prod Term'),
    (N'SELL4 PT',  N'SELLADORA PT 4', N'Prod Term'),
    (N'SELL5 PT',  N'SELLADORA PT 5', N'Prod Term'),
    -- Rasurado
    (N'ELEV RASU', N'ELEVADOR RASURADO', N'Rasurado'),
    (N'LAFERT',    N'RASURADORA LAFER', N'Rasurado'),
    -- Secado
    (N'CAMP1',     N'SECADORA CAMPO 1', N'Secado'),
    (N'CAMP2',     N'SECADORA CAMPO 2', N'Secado'),
    (N'CAMP3',     N'SECADORA CAMPO 3', N'Secado'),
    (N'FOUL',      N'SECADORA FOULARD', N'Secado'),
    (N'PR',        N'SECADORA PR', N'Secado'),
    (N'TSPL',      N'SECADORA TSE PLUS', N'Secado'),
    -- Tejido
    (N'GENK',      N'MONTACARGAS GENKINGER', N'Tejido'),
    (N'POLJAC1',   N'POLIPASTOS JACQUARD 1', N'Tejido'),
    (N'POLJAC2',   N'POLIPASTOS JACQUARD 2', N'Tejido'),
    -- Tintoreria
    (N'ABRI',      N'ABRIDORA CORINO', N'Tintoreria'),
    (N'CALD1',     N'CALDERA CLEAVER 1 300CC', N'Tintoreria'),
    (N'CALD2',     N'CALDERA CLEAVER 2 300CC', N'Tintoreria'),
    (N'CENLAV',    N'CENTRO DE LAVADO', N'Tintoreria'),
    (N'CLAY',      N'CALDERA CLAYTON', N'Tintoreria'),
    (N'OVEL35',    N'MAQUINA DE COSER OVER LOOK 35', N'Tintoreria'),
    (N'OVEL37',    N'MAQUINA DE COSER OVER LOOK 37', N'Tintoreria'),
    (N'OVERF1',    N'OVER FLOW 1', N'Tintoreria'),
    (N'OVERF2',    N'OVER FLOW 2', N'Tintoreria'),
    (N'OVERF3',    N'OVER FLOW 3', N'Tintoreria'),
    (N'OVERF4',    N'OVER FLOW 4', N'Tintoreria'),
    (N'OVERF5',    N'OVER FLOW 5', N'Tintoreria'),
    (N'PLAT',      N'PLANTA TRATADORA DE AGUA', N'Tintoreria'),
    (N'POZO',      N'BOMBA DE AGUA DEL POZO', N'Tintoreria')
) AS v (MaquinaId, Nombre, Departamento)
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.URDCatalogoMaquinas m WHERE m.MaquinaId = v.MaquinaId
);

SELECT @@ROWCOUNT AS MaquinasInsertadas; -- 92 en la primera ejecución, 0 si se vuelve a correr.

COMMIT TRANSACTION;

-- Verificación
SELECT Departamento, COUNT(*) AS Maquinas
FROM dbo.URDCatalogoMaquinas
GROUP BY Departamento
ORDER BY Departamento;
