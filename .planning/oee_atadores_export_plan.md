# Plan: Exportar reporte OEE Atadores a archivo existente

## Objetivo
Nuevo boton "Exportar a OEE" que abre `C:\Users\fsanchez\Desktop\OEE_ATADORES.xlsx`, actualiza la hoja DETALLE, crea/actualiza hojas SEMANA XX, y actualiza hojas CONCENTRADO XX.

## Arquitectura del Excel OEE_ATADORES.xlsx

### Hojas existentes
- `ATADORES 2025` - resumen anual OEE por atador
- `TOTAL ATADOS` - conteo total atados por semana, refs DETALLE footer rows
- `grafica` - grafica
- `DETALLE` - 52 secciones x 46 filas = datos OEE semanales (lo que genera el PHP Export)
- `SEMANA XX` (11,10,09,...01) - resumen OEE por atador por semana
- `CONCENTRADO ENERO/FEBRERO/MARZO` - consolidado mensual

### Cadena de referencias entre hojas
```
DETALLE (cols A-CJ = PHP Export, cols CK-CU = formulas OEE)
    | array formula: =DETALLE!CM418:CT418
    v
SEMANA XX (resumen OEE por atador, filas 4-8 = atadores, 16-22 = calculo OEE)
    | formula directa: ='SEMANA 02'!D17
    v
CONCENTRADO XX (mensual, 5 slots de semana, promedios)
    |
    v
ATADORES 2025 / TOTAL ATADOS (anual)
```

### Hoja DETALLE - Estructura por seccion (46 filas base)
- Fila 1 (relativa): vacia
- Fila 2: header con fechas por dia (DAY_DEFINITIONS)
- Fila 3: labels de columnas
- Filas 4-45: area de detalle (42 filas = 3 turnos x 2 bloques x 6 filas + capacitacion 6 filas)
- Fila 46: footer (B="SEMANA", C=weekNumber, conteos por dia)

Posicion de semana N: filas `(N-1)*46 + 1` a `N*46`

### Columnas CK-CU en DETALLE (cols 89-99) - Formulas OEE por atador
Las filas CK-CU estan en las primeras filas de datos de cada seccion (filas 4-10 relativas).
Cada fila corresponde a un bloque de atador (T1B1, T1B2, T2B1, T2B2, T3B1, T3B2, Capacitacion).

Formulas por fila de resumen (summaryRow = sectionTopRow + 3 + i):
```
CK = =C{blockRowStart}                    (clave atador del bloque)
CL = nombre atador (texto)
CM = =AVERAGE(CF{R},BT{R},BH{R},AV{R},AJ{R},X{R},L{R})   (avg calidad 7 dias)
CN = =AVERAGE(CA{R},BO{R},BC{R},AQ{R},AE{R},S{R},G{R})    (avg tiempo 7 dias)
CO = =CM{minRow}                           (estandar = MIN tiempo)
CP = =CN{row}-CO{row}                     (diferencia)
CQ = =CO{row}-CP{row}                     (tiempo ajustado)
CR = =CQ{row}*100/CO{row}                 (eficiencia %)
CS = =CM{row}*100/10                       (calidad %)
CT = =AVERAGE(CH{R},BV{R},BJ{R},AX{R},AL{R},Z{R},N{R})    (avg merma 7 dias)
CU = =CL{row}                             (nombre eco)
```

Donde R = row_start del bloque del atador en el area de detalle.

Columnas de avg por dia (usadas en CM/CN/CT):
- avg_calif: L, X, AJ, AV, BH, BT, CF (dias 0-6)
- avg_time:  G, S, AE, AQ, BC, BO, CA (dias 0-6)
- avg_merma: N, Z, AL, AX, BJ, BV, CH (dias 0-6)

Filas adicionales CK-CU (offsets desde primera fila resumen):
- +7: CN = AVERAGE(CN{first}:CN{last})  (avg tiempo todos atadores)
- +9: CL='MAX', CM=MAX(CN range), CQ='PROMEDIO GRAL.', CR=AVERAGE(CR range)
- +10: CL='MIN', CM=MIN(CN range)
- +13: CL='MAX', CM=CM{maxRow}
- +14: CL='MIN', CM=CM{minRow}
- +15: CL='EST', CM=CN{avgRow}

### Hoja SEMANA XX - Estructura
```
Fila 2: B2 = "SEMANA {N}"
Fila 3: Headers (CLAVE ATADOR, ATADOR, CALIDAD, TIEMPO DE ATADO, ESTANDAR, ..., EFICIENCIA, CALIDAD, MERMA, ATADOR, 5S-SEGURIDAD)
Filas 4-8: Un atador por fila
  B = clave (int), C = nombre (str)
  D:K = array formula =DETALLE!CM{detalleRow}:CT{detalleRow} (o formulas individuales por celda)
  L = =C{row}, M = 5S score (manual, default 100)
Fila 11: K = =AVERAGE(K4:K8) (avg merma)
Fila 13: C='PROMEDIO GRAL.', D=formula
Filas 16-22: Tabla OEE
  16: D-H = nombres atadores (=C4..=C8)
  17: C='EFIC. ATADOR', D-H = =I4..=I8
  18: C='EFIC. X AUXILIAR', formulas especificas
  19: C='CALIDAD/5S EGURIDAD', D-H = =AVERAGE(J{row},M{row})
  20: B=MIN merma, C='MERMA (PROMEDIO)', D-H = =K4..=K8
  21: C='% X MERMA', D-H = =$B$20*100/D20..
  22: C='OEE', D-H = =D21*D19*D17/1000000..
Filas 26-51: Seccion 5S y Uster (DATOS MANUALES - preservar si hoja existe)
```

Array formula en D4:K4 equivale a: D4==DETALLE!CM{r}, E4==DETALLE!CN{r}, F4==DETALLE!CO{r}, G4==DETALLE!CP{r}, H4==DETALLE!CQ{r}, I4==DETALLE!CR{r}, J4==DETALLE!CS{r}, K4==DETALLE!CT{r}

### Hoja CONCENTRADO XX - Estructura
5 slots de semana (filas 5,10,15,20,25), cada uno 5 filas:
```
Slot N (row = 5 + (N-1)*5):
  A = ISO week number
  B = 'EFICIENCIA', C-H = ='SEMANA XX'!D17..H17
  B = 'CALIDAD/5S', C-H = ='SEMANA XX'!D19..H19
  B = 'MERMA KG',   C-H = ='SEMANA XX'!D20..H20
  B = 'MERMA %',    C-H = ='SEMANA XX'!D21..H21
  B = 'OEE',        C-H = ='SEMANA XX'!D22..H22
```
Filas 32-37: Promedios mensuales (AVERAGE de los 5 slots)
Fila 39: PROMEDIO OEE

### Hoja TOTAL ATADOS - Referencias a DETALLE
Cada semana referencia la fila footer de DETALLE:
- Semana 1: =DETALLE!I46 (footer_count cols por dia)
- Semana 2: =DETALLE!I92, U92, AG92, AS92, BE92, BQ92, CC92
- Pattern: =DETALLE!{footer_count_col}{weekNumber * 46}
- YA tiene #REF! errors desde semana 12+

## Impacto de expansion de filas

Cuando se insertan filas en DETALLE para semana W:
- PhpSpreadsheet `insertNewRowBefore()` actualiza automaticamente TODAS las referencias cross-sheet
- SEMANA XX sheets: array formulas se actualizan automaticamente
- TOTAL ATADOS: formulas se actualizan automaticamente
- CONCENTRADO: referencia SEMANA sheets por nombre, NO afectado
- CK-CU formulas en DETALLE mismo: se actualizan automaticamente

## Decisiones de implementacion

1. **SEMANA existente**: PRESERVAR datos manuales (filas 26-51). Solo actualizar formulas/OEE (filas 2-22).
2. **SEMANA nueva**: Clonar ultima SEMANA existente, actualizar formulas, limpiar seccion manual.
3. **CK-CU**: Regenerar cada vez basandose en layout real de bloques.
4. **Expansion**: Usar insertNewRowBefore, confiar en PhpSpreadsheet para actualizar refs.
5. **Archivo**: Desktop `C:\Users\fsanchez\Desktop\OEE_ATADORES.xlsx` (configurable).
6. **Confirmacion**: Verificar si semana tiene datos, preguntar al usuario via AJAX/SweetAlert.

## Plan de implementacion

### Archivos a crear
1. `app/Services/OeeAtadores/OeeAtadoresFileService.php` - Orquestador principal (~400 lineas)
2. Modificar `app/Exports/Reporte00EAtadoresExport.php` - Agregar getLayout() y getPreparedRecords()

### Archivos a modificar
3. `app/Http/Controllers/Atadores/Reportes/ReportesAtadoresController.php` - 2 metodos nuevos
4. `routes/modules/atadores.php` - 2 rutas nuevas
5. `resources/views/modulos/atadores/reportes/atadores.blade.php` - Boton nuevo + JS

### Detalle del servicio OeeAtadoresFileService

```php
class OeeAtadoresFileService
{
    public function verificarSemanasConDatos(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    // Abre archivo, escanea secciones, retorna array de semanas con datos

    public function actualizarArchivo(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): string
    // Flujo principal:
    // 1. Abrir OEE_ATADORES.xlsx
    // 2. Para cada semana:
    //    a. Encontrar posicion seccion en DETALLE (escanear footers)
    //    b. Pre-limpiar celdas A-CJ de la seccion
    //    c. Usar Reporte00EAtadoresExport::renderIntoSheet() para escribir datos
    //    d. Escribir formulas CK-CU
    //    e. Crear/actualizar hoja SEMANA XX
    // 3. Actualizar hojas CONCENTRADO
    // 4. Guardar archivo

    private function buildSectionMap(Worksheet $detalle): array
    // Escanea filas footer (B="SEMANA") para construir mapa de posiciones

    private function clearSectionData(Worksheet $sheet, int $startRow, int $endRow): void
    // Limpia celdas A-CJ en el rango de filas

    private function writeCkCuFormulas(Worksheet $sheet, array $layout, int $sectionTopRow): void
    // Escribe formulas CK-CU basandose en el layout de bloques

    private function updateSemanaSheet(Spreadsheet $book, int $weekNumber, array $sectionMap, array $atadorList): void
    // Crea o actualiza hoja SEMANA XX

    private function updateConcentradoSheet(Spreadsheet $book, int $weekNumber, CarbonImmutable $weekDate): void
    // Actualiza hoja CONCENTRADO del mes correspondiente
}
```

### Detalle del controlador (metodos nuevos)

```php
// GET /atadores/reportes-atadores/atadores/oee/verificar?fecha_ini=...&fecha_fin=...
public function verificarOeeAtadores(Request $request): JsonResponse
// Retorna JSON: { semanas_con_datos: [10, 11], semanas_rango: [10, 11, 12] }

// POST /atadores/reportes-atadores/atadores/oee/exportar
public function exportarOeeAtadores(Request $request): RedirectResponse
// Ejecuta la actualizacion, redirect con success/error
```

### Detalle del blade (boton nuevo)
Boton "Exportar a OEE" junto al boton existente "Guardar Excel Anual".
Al clickear:
1. AJAX GET a verificar → obtiene semanas con datos
2. SweetAlert muestra: "Se actualizaran semanas X-Y. Semanas con datos existentes: A, B. Continuar?"
3. Si confirma → POST a exportar
4. Redirect con mensaje success/error

### Rutas nuevas
```php
Route::get('/atadores/oee/verificar', [..., 'verificarOeeAtadores'])->name('atadores.oee.verificar');
Route::post('/atadores/oee/exportar', [..., 'exportarOeeAtadores'])->name('atadores.oee.exportar');
```

## Notas tecnicas importantes

- La plantilla `resources/templates/Reporte_00E_Atadores.xlsx` existe y es usada por renderIntoSheet()
- `clearTemplateContent()` es private y carga el template para cachear coordenadas. Pre-limpiar toda la seccion antes de llamar renderIntoSheet() hace que clearTemplateContent() sea no-op.
- Para SEMANA sheets: usar formulas individuales por celda en vez de array formulas (mas simple con PhpSpreadsheet)
- MAX_COLUMN_INDEX = 88 (CJ). CK-CU son cols 89-99.
- DAY_DEFINITIONS tiene 7 dias (0=lunes, 6=domingo) con mapeo de columnas completo.
- CONCENTRADO: mes se determina por el jueves de la semana ISO. Meses en espanol: ENERO, FEBRERO, etc.
- El archivo ya tiene #REF! en TOTAL ATADOS desde semana 12+ (preexistente, no causado por nosotros).
