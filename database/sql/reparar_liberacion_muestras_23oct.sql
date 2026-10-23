-- =============================================================================
-- Reparación: "Liberar órdenes" de Muestras re-liberó órdenes de PROGRAMA (23-oct-2026)
--
-- Qué pasó: la pantalla /planeacion/muestras/liberar-ordenes posteaba a la ruta de Programa.
-- MuestrasPrograma comparte los Id con ReqProgramaTejido, así que al liberar las muestras 913 y
-- 949 se liberaron OTRA VEZ las órdenes de Programa 913 y 949 (ya liberadas el 16-oct como 37040 y
-- 37041) con datos de la muestra (pedido 300 → 1 rollo):
--   * 14:51  913: 37040 → 37069   949: 37041 → 37070   (CatCodificados 23901, 23902)
--   * 15:48  913: 37069 → 37071                         (CatCodificados 23903)
--   * Marbetes pisados: 913 24 rollos/8640 pzas → 1/360; 949 33 rollos/20592 pzas → 1/624.
--   * Las 3 filas nuevas de CatCodificados quedaron con CreaProd = 1: AX crea la orden de
--     producción y lo baja a 0. Las originales (37040, 37041) ya están en 0.
-- El código ya está corregido (rutas de la superficie + Liberar no re-libera un renglón con
-- NoProduccion). Esto solo deja los datos como estaban.
--
-- CÓMO CORRERLO (SQL Server 2008 R2, ProdTowel)
--   1. PASO 1 YA (seleccionarlo y ejecutarlo solo): que AX no cree 37069/37070/37071.
--   2. Revisar en AX si ya creó alguna de esas órdenes. Si las creó, cancelarlas en AX y NO
--      correr el PASO 3 (dejar las filas en CatCodificados como rastro).
--   3. PASO 2 y PASO 3 juntos (una transacción). Cada paso revisa el valor actual (no pisa nada
--      que alguien haya cambiado después) e imprime cuántas filas tocó; lo esperado está en el PRINT.
--   No se toca la secuencia de folios: 37069–37071 quedan como hueco (reusarlos chocaría si AX
--   ya los creó).
--   El Excel de orden de cambio que salió al liberar 37069/37070/37071 no vale: descartarlo.
-- =============================================================================
SET XACT_ABORT ON;
SET NOCOUNT ON;

-- Antes: cómo están hoy (para comparar).
SELECT Id, OrdenTejido, CreaProd, NoMarbete, TotalRollos, TotalPzas FROM dbo.CatCodificados WHERE Id IN (23872, 23873, 23901, 23902, 23903) ORDER BY Id;
SELECT Id, NoProduccion, Programado, NoMarbete, TotalRollos, TotalPzas, SaldoMarbete, RollosProgramados FROM dbo.ReqProgramaTejido WHERE Id IN (913, 949);

-- =============================================================================
-- PASO 1 (urgente, solo). Que AX no cree las órdenes 37069, 37070 y 37071. Esperado: 3 filas.
-- =============================================================================
UPDATE dbo.CatCodificados
SET CreaProd = 0
WHERE Id IN (23901, 23902, 23903)
  AND OrdenTejido IN ('37069', '37070', '37071')
  AND CreaProd = 1;
PRINT N'PASO 1 - CatCodificados con CreaProd apagado: ' + CAST(@@ROWCOUNT AS nvarchar(10)) + N' (esperado 3)';

BEGIN TRANSACTION;

-- =============================================================================
-- PASO 2. Las órdenes de Programa vuelven a su folio y sus marbetes del 16-oct (los de sus
-- filas de CatCodificados 23872/23873, que no se tocaron). Sin producción, así que
-- SaldoMarbete = TotalRollos. Programado = 2026-10-16, como la 37042 liberada ese día.
-- Esperado: 1 + 1 filas.
-- =============================================================================
UPDATE dbo.ReqProgramaTejido
SET NoProduccion = N'37040', Programado = '2026-10-16',
    NoMarbete = 24, TotalRollos = 24, TotalPzas = 8640, SaldoMarbete = 24, RollosProgramados = 24,
    UpdatedAt = GETDATE()
WHERE Id = 913 AND NoProduccion = N'37071' AND TamanoClave = N'PULLMAN7028';
PRINT N'PASO 2 - Programa 913 vuelve a 37040: ' + CAST(@@ROWCOUNT AS nvarchar(10)) + N' (esperado 1)';

UPDATE dbo.ReqProgramaTejido
SET NoProduccion = N'37041', Programado = '2026-10-16',
    NoMarbete = 33, TotalRollos = 33, TotalPzas = 20592, SaldoMarbete = 33, RollosProgramados = 33,
    UpdatedAt = GETDATE()
WHERE Id = 949 AND NoProduccion = N'37070' AND TamanoClave = N'TRAPO6988';
PRINT N'PASO 2 - Programa 949 vuelve a 37041: ' + CAST(@@ROWCOUNT AS nvarchar(10)) + N' (esperado 1)';

-- =============================================================================
-- PASO 3. Quitar de CatCodificados las 3 órdenes que no existen. SOLO si AX no las creó
-- (si las creó, cancelarlas allá primero). Esperado: 3 filas.
-- =============================================================================
DELETE FROM dbo.CatCodificados
WHERE Id IN (23901, 23902, 23903)
  AND OrdenTejido IN ('37069', '37070', '37071')
  AND CreaProd = 0;
PRINT N'PASO 3 - CatCodificados borrados: ' + CAST(@@ROWCOUNT AS nvarchar(10)) + N' (esperado 3)';

COMMIT TRANSACTION;

-- Después: 913 y 949 con 37040/37041 y sus marbetes; 23901–23903 ya no existen.
SELECT Id, NoProduccion, Programado, NoMarbete, TotalRollos, TotalPzas, SaldoMarbete, RollosProgramados FROM dbo.ReqProgramaTejido WHERE Id IN (913, 949);
SELECT Id, OrdenTejido, CreaProd FROM dbo.CatCodificados WHERE Id IN (23872, 23873, 23901, 23902, 23903) ORDER BY Id;
