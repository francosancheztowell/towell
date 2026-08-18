/*
================================================================================
 Revisión de índices para el andón de Crudo — TI_PRO (AX)
================================================================================
 Contexto: el módulo Crudo SOLO LEE de esta base. Las tres consultas de abajo son
 las únicas que le pega, y son las que cuestan 210–230 ms cada una (medido desde
 la app, LAN, ~750k filas en TWCRUDOTABLE).

 Este script es de DIAGNÓSTICO y NO MODIFICA NADA:
   - No hay DROP, DELETE, UPDATE, INSERT, TRUNCATE ni ALTER en ninguna línea.
   - El único DDL del archivo (CREATE INDEX, paso 6) vive DENTRO de un bloque
     de comentario: aunque se ejecute el archivo completo, no se aplica.
   - Solo lee catálogos del sistema (sys.*), DMVs y las mismas tablas que el
     andón ya consulta cada pocos minutos.

 Cómo usarlo: correr los pasos 1–4, luego el 5 con el plan de ejecución real
 activado (Ctrl+M en SSMS), y comparar contra la propuesta del paso 6.
================================================================================
*/

USE TI_PRO;
GO

/*
 Cinturón de seguridad de la sesión. Lo importante es que este diagnóstico no
 estorbe a AX: lee sin tomar bloqueos, se rinde si encuentra algo bloqueado y,
 ante un deadlock, la víctima es esta sesión y nunca la captura de producción.
*/
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
SET LOCK_TIMEOUT 5000;
SET DEADLOCK_PRIORITY LOW;
GO

-- Parámetros de la prueba: mismo rango y mismo telar que usa el tablero.
DECLARE @dataAreaId NVARCHAR(4)  = N'pro';
DECLARE @desde      DATETIME     = CAST(CAST(GETDATE() AS DATE) AS DATETIME);
DECLARE @hasta      DATETIME     = DATEADD(DAY, 1, CAST(CAST(GETDATE() AS DATE) AS DATETIME));
DECLARE @telar      NVARCHAR(20) = N'201';


/*------------------------------------------------------------------------------
 1. Qué índices existen hoy en las dos tablas
------------------------------------------------------------------------------*/
SELECT
    OBJECT_NAME(i.object_id)      AS tabla,
    i.name                        AS indice,
    i.type_desc                   AS tipo,
    i.is_unique,
    i.filter_definition,
    STUFF((SELECT ', ' + c.name
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
           FOR XML PATH('')), 1, 2, '') AS columnas_incluidas,
    p.rows                        AS filas
FROM sys.indexes i
JOIN sys.partitions p ON p.object_id = i.object_id AND p.index_id = i.index_id
WHERE i.object_id IN (OBJECT_ID('dbo.TWCRUDOTABLE'), OBJECT_ID('dbo.TWCRUDOLINE'))
  AND p.partition_number = 1
ORDER BY tabla, i.index_id;


/*------------------------------------------------------------------------------
 2. ¿Se usan? Scans altos contra seeks bajos = falta el índice adecuado.
    (Los contadores se reinician al reiniciar la instancia.)
------------------------------------------------------------------------------*/
SELECT
    OBJECT_NAME(s.object_id) AS tabla,
    i.name                   AS indice,
    s.user_seeks,
    s.user_scans,
    s.user_lookups,
    s.user_updates,
    s.last_user_seek,
    s.last_user_scan
FROM sys.dm_db_index_usage_stats s
JOIN sys.indexes i ON i.object_id = s.object_id AND i.index_id = s.index_id
WHERE s.database_id = DB_ID()
  AND s.object_id IN (OBJECT_ID('dbo.TWCRUDOTABLE'), OBJECT_ID('dbo.TWCRUDOLINE'))
ORDER BY tabla, s.user_scans DESC;


/*------------------------------------------------------------------------------
 3. Qué índice pide SQL Server por su cuenta (DMV de índices faltantes).
    Ojo: son sugerencias crudas, suelen incluir de más en INCLUDE. Sirven como
    segunda opinión, no como receta.
------------------------------------------------------------------------------*/
SELECT
    OBJECT_NAME(d.object_id)                        AS tabla,
    d.equality_columns,
    d.inequality_columns,
    d.included_columns,
    s.user_seeks,
    s.avg_total_user_cost,
    s.avg_user_impact,
    CAST(s.avg_total_user_cost * s.avg_user_impact * (s.user_seeks + s.user_scans) AS DECIMAL(18,2)) AS beneficio_estimado
FROM sys.dm_db_missing_index_details d
JOIN sys.dm_db_missing_index_groups g   ON g.index_handle = d.index_handle
JOIN sys.dm_db_missing_index_group_stats s ON s.group_handle = g.index_group_handle
WHERE d.database_id = DB_ID()
  AND d.object_id IN (OBJECT_ID('dbo.TWCRUDOTABLE'), OBJECT_ID('dbo.TWCRUDOLINE'))
ORDER BY beneficio_estimado DESC;


/*------------------------------------------------------------------------------
 4. Estadísticas: si están viejas, el plan puede ser malo aunque el índice exista.
------------------------------------------------------------------------------*/
SELECT
    OBJECT_NAME(st.object_id) AS tabla,
    st.name                   AS estadistica,
    sp.last_updated,
    sp.rows,
    sp.rows_sampled,
    sp.modification_counter
FROM sys.stats st
CROSS APPLY sys.dm_db_stats_properties(st.object_id, st.stats_id) sp
WHERE st.object_id IN (OBJECT_ID('dbo.TWCRUDOTABLE'), OBJECT_ID('dbo.TWCRUDOLINE'))
ORDER BY sp.last_updated;


/*------------------------------------------------------------------------------
 5. Las tres consultas reales del andón, tal cual las manda la aplicación.
    Correr con "Include Actual Execution Plan" y mirar:
      - Clustered Index Scan sobre TWCRUDOTABLE  -> falta índice
      - Estimated vs Actual rows muy separados   -> estadísticas viejas
      - Lecturas lógicas en miles                -> está barriendo la tabla
------------------------------------------------------------------------------*/
SET STATISTICS IO, TIME ON;

-- 5a. Agregación del tablero: una fila por telar. Es la que corre en cada
--     reconstrucción del snapshot (cada 3 min por caché de la app).
SELECT
    TELAR,
    COUNT(*)                       AS captureCount,
    SUM(COALESCE(PIEZASTOTAL, 0))  AS pieces,
    SUM(COALESCE(SEGUNDASTOTAL, 0)) AS seconds,
    SUM(COALESCE(PESO, 0))         AS kilos
FROM dbo.TWCRUDOTABLE
WHERE DATAAREAID = @dataAreaId
  AND TRANSDATE >= @desde
  AND TRANSDATE <  @hasta
GROUP BY TELAR
ORDER BY TELAR;

-- 5b. Detalle de un telar: se dispara al abrir el modal de una máquina.
SELECT
    RECID, PRODID, PURCHBARCODE, ORDENTEJIDO, ORDENURDIDO, TRANSDATE, TELAR,
    PESO, PIEZAST1, PIEZAST2, PIEZAST3, PIEZAST4, PIEZASTOTAL, SEGUNDASTOTAL,
    EMPLID, NAMEEMPLE, OBSERVACIONES, MODIFIEDDATE, MODIFIEDTIME
FROM dbo.TWCRUDOTABLE
WHERE DATAAREAID = @dataAreaId
  AND TRANSDATE >= @desde
  AND TRANSDATE <  @hasta
  AND TELAR = @telar
ORDER BY TELAR, RECID;

-- 5c. Defectos (segundas) del periodo: join de líneas contra encabezados.
SELECT
    h.TELAR,
    l.CODDEFECTOID,
    l.DESCRIP,
    SUM(COALESCE(l.CANTIDAD, 0)) AS quantity
FROM dbo.TWCRUDOLINE  l
JOIN dbo.TWCRUDOTABLE h ON h.RECID = l.REFRECID
WHERE l.DATAAREAID = @dataAreaId
  AND h.DATAAREAID = @dataAreaId
  AND h.TRANSDATE >= @desde
  AND h.TRANSDATE <  @hasta
GROUP BY h.TELAR, l.CODDEFECTOID, l.DESCRIP;

SET STATISTICS IO, TIME OFF;


/*------------------------------------------------------------------------------
 6. PROPUESTA — NO EJECUTAR SIN VALIDAR. Decide el DBA de TI_PRO.

 Las tres consultas filtran igual: DATAAREAID + rango de TRANSDATE, y la 5b
 además por TELAR. Un solo índice las cubre:

     CREATE NONCLUSTERED INDEX IX_TWCRUDOTABLE_DataArea_TransDate_Telar
         ON dbo.TWCRUDOTABLE (DATAAREAID, TRANSDATE, TELAR)
         INCLUDE (RECID, PESO, PIEZASTOTAL, SEGUNDASTOTAL)
         WITH (ONLINE = ON, FILLFACTOR = 90);   -- ONLINE solo en Enterprise

 Notas para quien lo evalúe:
   - Orden de las claves: DATAAREAID primero (igualdad), TRANSDATE después
     (rango) y TELAR al final. Invertirlo mata el seek del rango.
   - El INCLUDE cubre por completo 5a y 5c; 5b sigue yendo al clustered por las
     columnas de texto, pero ya solo por las filas del telar y del día.
   - Es AX: verificar que el índice no choque con los que crea/recrea el propio
     AX en sincronización de la base (Data Dictionary), y que no exista ya uno
     equivalente con otro nombre (paso 1).
   - Costo: escritura extra en cada captura de crudo y espacio adicional. La
     tabla se escribe poco y se lee mucho, así que debería salir a favor.

 Antes/después: correr el paso 5 con STATISTICS IO y comparar lecturas lógicas.
 Si el índice sirve, deben caer de miles a decenas.
------------------------------------------------------------------------------*/
