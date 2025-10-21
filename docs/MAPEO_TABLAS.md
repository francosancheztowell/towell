# Mapeo entre Tablas: TEJIDO_SCHEDULING vs ReqProgramaTejido

## Comparación de Campos

### ✅ Campos Coincidentes (con nombres diferentes)

| TEJIDO_SCHEDULING (Planeacion) | ReqProgramaTejido | Tipo | Notas |
|-------------------------------|-------------------|------|-------|
| `Cuenta` | `CuentaRizo` | NVARCHAR/String | Cuenta del rizo |
| `Salon` | `SalonTejidoId` | NVARCHAR/String | Salón de tejido |
| `Telar` | `NoTelarId` | NVARCHAR/String | Número de telar |
| `Ultimo` | `Ultimo` | BIT/Boolean | Último registro |
| `Cambios_Hilo` | `CambioHilo` | BIT/Boolean | Cambio de hilo |
| `Maquina` | `Maquina` | NVARCHAR/String | Máquina |
| `Ancho` | `Ancho` | FLOAT | Ancho |
| `Eficiencia_Std` | `EficienciaSTD` | FLOAT | Eficiencia estándar |
| `Velocidad_STD` | `VelocidadSTD` | INT | Velocidad estándar |
| `Hilo` | `FibraRizo` | NVARCHAR/String | Fibra/Hilo de rizo |
| `Calibre_Pie` | `CalibrePie` | FLOAT | Calibre del pie |
| `Calendario` | `CalendarioId` | NVARCHAR/String | Calendario |
| `Clave_Estilo` | `TamanoClave` | NVARCHAR/String | Tamaño/Clave |
| `Nombre_Producto` | `NombreProducto` | NVARCHAR/String | Nombre del producto |
| `Saldos` | `SaldoPedido` | FLOAT | Saldo del pedido |
| `Orden_Prod` | `NoProduccion` | NVARCHAR/String | Número de producción |
| `Descrip` | `NombreProyecto` | NVARCHAR/String | Descripción/Proyecto |
| `Aplic` | `AplicacionId` | NVARCHAR/String | Aplicación |
| `Obs` | `Observaciones` | NVARCHAR/String | Observaciones |
| `Tipo_Ped` | `TipoPedido` | NVARCHAR/String | Tipo de pedido |
| `Tiras` | `NoTiras` | INT | Número de tiras |
| `Peine` | `Peine` | INT | Peine |
| `Luchaje` | `Luchaje` | INT | Luchaje |
| `Peso_Crudo` | `PesoCrudo` | INT | Peso crudo |
| `CALIBRE_TRA` | `CalibreTrama` | FLOAT | Calibre trama |
| `Dobladillo` | `DobladilloId` | NVARCHAR/String | Dobladillo |
| `PASADAS_TRAMA` | `PasadasTrama` | INT | Pasadas trama |
| `PASADAS_C1` | `PasadasComb1` | INT | Pasadas combinación 1 |
| `PASADAS_C2` | `PasadasComb2` | INT | Pasadas combinación 2 |
| `PASADAS_C3` | `PasadasComb3` | INT | Pasadas combinación 3 |
| `PASADAS_C4` | `PasadasComb4` | INT | Pasadas combinación 4 |
| `PASADAS_C5` | `PasadasComb5` | INT | Pasadas combinación 5 |
| `ancho_por_toalla` | `AnchoToalla` | INT/FLOAT | Ancho por toalla |
| `COLOR_TRAMA` | `ColorTrama` | NVARCHAR/String | Color trama |
| `CALIBRE_C1` | `CalibreComb12` | FLOAT | Calibre combinación 1 |
| `Clave_Color_C1` | `CodColorComb1` | NVARCHAR/String | Código color comb. 1 |
| `COLOR_C1` | `NombreCC1` | NVARCHAR/String | Nombre color comb. 1 |
| `CALIBRE_C2` | `CalibreComb22` | FLOAT | Calibre combinación 2 |
| `Clave_Color_C2` | `CodColorComb2` | NVARCHAR/String | Código color comb. 2 |
| `COLOR_C2` | `NombreCC2` | NVARCHAR/String | Nombre color comb. 2 |
| `CALIBRE_C3` | `CalibreComb32` | FLOAT | Calibre combinación 3 |
| `Clave_Color_C3` | `CodColorComb3` | NVARCHAR/String | Código color comb. 3 |
| `COLOR_C3` | `NombreCC3` | NVARCHAR/String | Nombre color comb. 3 |
| `CALIBRE_C4` | `CalibreComb42` | FLOAT | Calibre combinación 4 |
| `Clave_Color_C4` | `CodColorComb4` | NVARCHAR/String | Código color comb. 4 |
| `COLOR_C4` | `NombreCC4` | NVARCHAR/String | Nombre color comb. 4 |
| `CALIBRE_C5` | `CalibreComb52` | FLOAT | Calibre combinación 5 |
| `Clave_Color_C5` | `CodColorComb5` | NVARCHAR/String | Código color comb. 5 |
| `COLOR_C5` | `NombreCC5` | NVARCHAR/String | Nombre color comb. 5 |
| `Plano` | `MedidaPlano` | INT | Medida plano |
| `Cuenta_Pie` | `CuentaPie` | NVARCHAR/String | Cuenta pie |
| `Clave_Color_Pie` | `CodColorCtaPie` | NVARCHAR/String | Código color pie |
| `Color_Pie` | `NombreCPie` | NVARCHAR/String | Nombre color pie |
| `Peso_gr_m2` | `PesoGRM2` | INT | Peso gr/m2 |
| `Dias_Ef` | `DiasEficiencia` | FLOAT | Días de eficiencia |
| `Prod_Kg_Dia` | `ProdKgDia` | FLOAT | Producción kg/día |
| `Std_Dia` | `StdDia` | FLOAT | Estándar día |
| `Prod_Kg_Dia1` | `ProdKgDia2` | FLOAT | Producción kg/día 2 |
| `Std_Toa_Hr_100` | `StdToaHra` | FLOAT | Estándar toalla/hora |
| `Dias_jornada_completa` | `DiasJornada` | FLOAT | Días jornada |
| `Horas` | `HorasProd` | FLOAT | Horas producción |
| `Std_Hr_efectivo` | `StdHrsEfect` | FLOAT | Std horas efectivas |
| `Inicio_Tejido` | `FechaInicio` | DATE | Fecha inicio |
| `Calc4` | `Calc4` | FLOAT | Cálculo 4 |
| `Calc5` | `Calc5` | FLOAT | Cálculo 5 |
| `Calc6` | `Calc6` | FLOAT | Cálculo 6 |
| `Fin_Tejido` | `FechaFinal` | DATE | Fecha final |
| `en_proceso` | `EnProceso` | BIT/Boolean | En proceso |
| `Calibre_Rizo` | `CalibreRizo` | FLOAT | Calibre del rizo |
| `rasurado` | `Rasurado` | NVARCHAR/String | Rasurado |

### ❌ Campos SOLO en TEJIDO_SCHEDULING (Planeacion)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `Clave_AX` | String | Clave AX |
| `Tamano_AX` | String | Tamaño AX |
| `Estilo_Alternativo` | String | Estilo alternativo |
| `Fecha_Captura` | DateTime | Fecha de captura |
| `Fecha_Liberacion` | DateTime | Fecha de liberación |
| `Id_Flog` | String | ID de Flog |
| `Largo_Crudo` | Float | Largo crudo |
| `Fecha_Compromiso` | Date | Fecha de compromiso |
| `Fecha_Compromiso1` | Date | Fecha de compromiso 1 |
| `Entrega` | Date | Entrega |
| `Dif_vs_Compromiso` | Float | Diferencia vs compromiso |
| `cantidad` | Float | Cantidad |

### ➕ Campos SOLO en ReqProgramaTejido (NUEVOS)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `NoExisteBase` | String | No existe en base |
| `ItemId` | String | ID del artículo |
| `InventSizeId` | String | ID de tamaño inventario |
| `TotalPedido` | Float | Total del pedido |
| `Produccion` | Float | Producción |
| `SaldoMarbete` | Int | Saldo marbete |
| `ProgramarProd` | Date | Programar producción |
| `Programado` | Date | Programado |
| `FlogsId` | String | ID de Flogs |
| `CustName` | String | Nombre del cliente |
| `FibraTrama` | String | Fibra trama |
| `CodColorTrama` | String | Código color trama |
| `FibraComb1` | String | Fibra combinación 1 |
| `FibraComb2` | String | Fibra combinación 2 |
| `FibraComb3` | String | Fibra combinación 3 |
| `FibraComb4` | String | Fibra combinación 4 |
| `FibraComb5` | String | Fibra combinación 5 |
| `EntregaProduc` | Date | Entrega producción |
| `EntregaPT` | Date | Entrega PT |
| `EntregaCte` | Date | Entrega cliente |
| `PTvsCte` | Int | PT vs Cliente |

## Conclusiones

### 📋 Resumen:
- **Campos coincidentes**: ~68 campos (con nombres diferentes)
- **Campos solo en TEJIDO_SCHEDULING**: 12 campos
- **Campos solo en ReqProgramaTejido**: 21 campos nuevos

### 🔄 Recomendaciones:

1. **Si usas ReqProgramaTejido**:
   - Necesitarás crear un nuevo `ExcelImportReqPrograma.php` 
   - O modificar el actual para mapear correctamente los campos

2. **Diferencias clave**:
   - ReqProgramaTejido tiene más campos relacionados con cliente y fechas de entrega
   - ReqProgramaTejido separa las fibras de las combinaciones (más detallado)
   - TEJIDO_SCHEDULING tiene campos de fechas de compromiso que no están en ReqProgramaTejido

3. **Mapeo de Excel**:
   - El Excel actual (69 columnas) parece estar diseñado para TEJIDO_SCHEDULING
   - Para ReqProgramaTejido necesitarás un Excel diferente o adaptar el mapeo




















