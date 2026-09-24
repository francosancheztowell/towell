/* =====================================================================
   PT-01 · 01.2 — Matriz física de schema Programa / Muestras (READ-ONLY)
   ---------------------------------------------------------------------
   Solo SELECT sobre vistas de catálogo (sys.*). No crea, altera ni borra
   nada. Correr en SSMS / sqlcmd contra la BD de Towell (ProdTowel o la
   copia de Laragon) con un usuario de solo lectura si es posible.

   Salida esperada: 6 result sets. Guardar cada uno como CSV (o copiar a
   Excel) y adjuntarlos al HANDOFF / pegar los huecos en config/planeacion.php
   según .planning/phases/01-guardrails/RUNBOOK-LARAGON.md.

   Tablas: ReqProgramaTejido, MuestrasPrograma,
           ReqProgramaTejidoLine, MuestrasProgramaLine
   ===================================================================== */

SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED; -- catálogo: no bloquea a nadie

DECLARE @tablas TABLE (tabla sysname PRIMARY KEY, superficie varchar(10), rol varchar(10));
INSERT INTO @tablas VALUES
    ('ReqProgramaTejido',     'programa', 'cabecera'),
    ('MuestrasPrograma',      'muestras', 'cabecera'),
    ('ReqProgramaTejidoLine', 'programa', 'lineas'),
    ('MuestrasProgramaLine',  'muestras', 'lineas');

/* ---------------------------------------------------------------------
   RS1. Columnas: tipo, longitud en caracteres, nulabilidad, default,
        identity y computada.
   --------------------------------------------------------------------- */
SELECT
    t.superficie,
    t.rol,
    o.name                                   AS tabla,
    c.column_id,
    c.name                                   AS columna,
    ty.name                                  AS tipo,
    CASE
        WHEN c.max_length = -1 THEN 'MAX'
        WHEN ty.name IN ('nchar', 'nvarchar') THEN CAST(c.max_length / 2 AS varchar(10))
        WHEN ty.name IN ('char', 'varchar', 'binary', 'varbinary') THEN CAST(c.max_length AS varchar(10))
        ELSE NULL
    END                                      AS longitud_chars,
    c.precision,
    c.scale,
    c.is_nullable,
    dc.definition                            AS default_def,
    c.is_identity,
    c.is_computed
FROM @tablas t
JOIN sys.objects o  ON o.name = t.tabla AND o.type = 'U' AND SCHEMA_NAME(o.schema_id) = 'dbo'
JOIN sys.columns c  ON c.object_id = o.object_id
JOIN sys.types ty   ON ty.user_type_id = c.user_type_id
LEFT JOIN sys.default_constraints dc ON dc.object_id = c.default_object_id
ORDER BY t.rol, t.superficie, c.column_id;

/* ---------------------------------------------------------------------
   RS2. Diferencias de columnas entre superficies (mismo rol).
        tipo_diferencia: SOLO_PROGRAMA | SOLO_MUESTRAS | TIPO | LONGITUD | NULL
        Research 2026-07-22 esperaba: 6 SOLO_PROGRAMA en cabecera
        (NoMarbete, RollosProgramados, ProdId, ProduccionMarbetes,
        IdRedbooth, NombreRedbooth) y 11 LONGITUD. Cualquier otra fila es
        divergencia nueva y va al SUMMARY.
   --------------------------------------------------------------------- */
;WITH cols AS (
    SELECT t.superficie, t.rol, c.name AS columna, ty.name AS tipo,
           CASE WHEN c.max_length = -1 THEN -1
                WHEN ty.name IN ('nchar', 'nvarchar') THEN c.max_length / 2
                ELSE c.max_length END AS longitud,
           c.precision, c.scale, c.is_nullable
    FROM @tablas t
    JOIN sys.objects o ON o.name = t.tabla AND o.type = 'U' AND SCHEMA_NAME(o.schema_id) = 'dbo'
    JOIN sys.columns c ON c.object_id = o.object_id
    JOIN sys.types ty ON ty.user_type_id = c.user_type_id
),
p AS (SELECT * FROM cols WHERE superficie = 'programa'),
m AS (SELECT * FROM cols WHERE superficie = 'muestras')
SELECT
    COALESCE(p.rol, m.rol)              AS rol,
    COALESCE(p.columna, m.columna)      AS columna,
    CASE
        WHEN m.columna IS NULL THEN 'SOLO_PROGRAMA'
        WHEN p.columna IS NULL THEN 'SOLO_MUESTRAS'
        WHEN p.tipo <> m.tipo THEN 'TIPO'
        WHEN p.longitud <> m.longitud OR p.precision <> m.precision OR p.scale <> m.scale THEN 'LONGITUD'
        WHEN p.is_nullable <> m.is_nullable THEN 'NULL'
    END                                 AS tipo_diferencia,
    p.tipo AS tipo_programa, p.longitud AS longitud_programa, p.is_nullable AS null_programa,
    m.tipo AS tipo_muestras, m.longitud AS longitud_muestras, m.is_nullable AS null_muestras
FROM p
FULL OUTER JOIN m ON m.rol = p.rol AND m.columna = p.columna
WHERE m.columna IS NULL
   OR p.columna IS NULL
   OR p.tipo <> m.tipo
   OR p.longitud <> m.longitud
   OR p.precision <> m.precision
   OR p.scale <> m.scale
   OR p.is_nullable <> m.is_nullable
ORDER BY rol, tipo_diferencia, columna;

/* ---------------------------------------------------------------------
   RS3. Índices (incluye PK/UNIQUE, filtrados e INCLUDE).
   --------------------------------------------------------------------- */
SELECT
    t.superficie,
    o.name                  AS tabla,
    i.name                  AS indice,
    i.type_desc,
    i.is_primary_key,
    i.is_unique,
    i.is_unique_constraint,
    i.has_filter,
    i.filter_definition,
    STUFF((SELECT ', ' + c.name + CASE WHEN ic.is_descending_key = 1 THEN ' DESC' ELSE '' END
           FROM sys.index_columns ic
           JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
           WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.is_included_column = 0
           ORDER BY ic.key_ordinal
           FOR XML PATH('')), 1, 2, '') AS columnas_clave,
    STUFF((SELECT ', ' + c.name
           FROM sys.index_columns ic
           JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
           WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.is_included_column = 1
           ORDER BY c.name
           FOR XML PATH('')), 1, 2, '') AS columnas_include
FROM @tablas t
JOIN sys.objects o ON o.name = t.tabla AND o.type = 'U' AND SCHEMA_NAME(o.schema_id) = 'dbo'
JOIN sys.indexes i ON i.object_id = o.object_id AND i.index_id > 0
ORDER BY t.rol, t.superficie, i.name;

/* ---------------------------------------------------------------------
   RS4. Foreign keys donde cualquiera de las 4 tablas es padre o hija.
   --------------------------------------------------------------------- */
SELECT
    fk.name                                     AS fk,
    OBJECT_NAME(fk.parent_object_id)            AS tabla_hija,
    STUFF((SELECT ', ' + COL_NAME(fkc.parent_object_id, fkc.parent_column_id)
           FROM sys.foreign_key_columns fkc WHERE fkc.constraint_object_id = fk.object_id
           ORDER BY fkc.constraint_column_id FOR XML PATH('')), 1, 2, '') AS columnas_hija,
    OBJECT_NAME(fk.referenced_object_id)        AS tabla_padre,
    STUFF((SELECT ', ' + COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id)
           FROM sys.foreign_key_columns fkc WHERE fkc.constraint_object_id = fk.object_id
           ORDER BY fkc.constraint_column_id FOR XML PATH('')), 1, 2, '') AS columnas_padre,
    fk.delete_referential_action_desc           AS on_delete,
    fk.update_referential_action_desc           AS on_update,
    fk.is_disabled,
    fk.is_not_trusted
FROM sys.foreign_keys fk
WHERE OBJECT_NAME(fk.parent_object_id) IN (SELECT tabla FROM @tablas)
   OR OBJECT_NAME(fk.referenced_object_id) IN (SELECT tabla FROM @tablas)
ORDER BY tabla_padre, tabla_hija, fk;

/* ---------------------------------------------------------------------
   RS5. Triggers y check constraints (afectan filas afectadas / @@ROWCOUNT;
        el observer ya desconfía del conteo por triggers).
   --------------------------------------------------------------------- */
SELECT 'TRIGGER' AS tipo, OBJECT_NAME(tr.parent_id) AS tabla, tr.name AS nombre,
       tr.is_disabled, CAST(NULL AS nvarchar(max)) AS definicion
FROM sys.triggers tr
WHERE OBJECT_NAME(tr.parent_id) IN (SELECT tabla FROM @tablas)
UNION ALL
SELECT 'CHECK', OBJECT_NAME(cc.parent_object_id), cc.name, cc.is_disabled, cc.definition
FROM sys.check_constraints cc
WHERE OBJECT_NAME(cc.parent_object_id) IN (SELECT tabla FROM @tablas)
ORDER BY tabla, tipo, nombre;

/* ---------------------------------------------------------------------
   RS6. Conteos (debe cuadrar con la línea base 69 / 853 / 0 / 0 o
        explicar la diferencia con la fecha de corte).
   --------------------------------------------------------------------- */
SELECT 'ReqProgramaTejido'     AS tabla, COUNT_BIG(*) AS filas FROM dbo.ReqProgramaTejido
UNION ALL SELECT 'ReqProgramaTejidoLine', COUNT_BIG(*) FROM dbo.ReqProgramaTejidoLine
UNION ALL SELECT 'MuestrasPrograma',      COUNT_BIG(*) FROM dbo.MuestrasPrograma
UNION ALL SELECT 'MuestrasProgramaLine',  COUNT_BIG(*) FROM dbo.MuestrasProgramaLine
UNION ALL SELECT 'CatCodificados',        COUNT_BIG(*) FROM dbo.CatCodificados;
