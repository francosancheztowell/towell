-- Query para obtener componentes de la fórmula con JOIN
-- Reemplaza 'TU_FOLIO_AQUI' con un folio real de EngProgramaEngomado para probar

SELECT 
    B.BomId,
    B.ItemId,
    IT.ItemName,
    ID.ConfigId,
    B.BomQty as ConsumoUnitario,
    B.UnitId as Unidad,
    ID.InventLocationId as Almacen,
    B.InventDimId
FROM BOMVersion as BV
INNER JOIN Bom as B ON B.BomId = BV.BomId
INNER JOIN InventTable as IT ON IT.ItemId = B.ItemId
INNER JOIN InventDim as ID ON B.InventDimId = ID.InventDimId
WHERE BV.ItemId = 'TE-PD-ENF-0022'  -- Reemplaza con un folio válido
  AND IT.DATAAREAID = 'PRO'
  AND B.DATAAREAID = 'PRO'
  AND ID.DATAAREAID = 'PRO'
ORDER BY B.LineNum;

-- Nota: Este query se ejecuta en la base de datos TOW_PRO (AX)
-- El folio debe coincidir con un ItemId válido en la tabla BOMVersion
