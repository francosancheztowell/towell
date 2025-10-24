# Formato de Excel para Codificación de Modelos

## Estructura del archivo Excel

El archivo Excel debe tener la siguiente estructura en la primera fila (encabezados):

### Datos Básicos
- `tamano_clave` - Tamaño Clave
- `orden_tejido` - Orden Tejido  
- `fecha_tejido` - Fecha Tejido
- `fecha_cumplimiento` - Fecha Cumplimiento
- `salon_tejido_id` - Salón Tejido
- `no_telar_id` - No. Telar
- `prioridad` - Prioridad
- `nombre` - Nombre
- `clave_modelo` - Clave Modelo
- `item_id` - Item ID
- `invent_size_id` - Invent Size ID
- `tolerancia` - Tolerancia
- `codigo_dibujo` - Código Dibujo
- `fecha_compromiso` - Fecha Compromiso
- `flogs_id` - Flogs ID
- `nombre_proyecto` - Nombre Proyecto
- `clave` - Clave

### Producción & Medidas
- `pedido` - Pedido
- `peine` - Peine
- `ancho_toalla` - Ancho Toalla
- `largo_toalla` - Largo Toalla
- `peso_crudo` - Peso Crudo
- `luchaje` - Luchaje
- `calibre_trama` - Calibre Trama
- `calibre_trama2` - Calibre Trama 2
- `velocidad_std` - Velocidad STD
- `total_marbetes` - Total Marbetes
- `repeticiones` - Repeticiones
- `no_tiras` - No. Tiras

### Trama & Rizo
- `cod_color_trama` - Código Color Trama
- `color_trama` - Color Trama
- `fibra_id` - Fibra ID
- `dobladillo_id` - Tipo plano
- `medida_plano` - Medida plano
- `tipo_rizo` - Tipo de rizo
- `altura_rizo` - Altura de rizo
- `calibre_rizo` - Calibre Rizo
- `calibre_rizo2` - Calibre Rizo 2
- `cuenta_rizo` - Cuenta Rizo
- `fibra_rizo` - Fibra Rizo
- `calibre_pie` - Calibre Pie
- `calibre_pie2` - Calibre Pie 2
- `cuenta_pie` - Cuenta Pie
- `fibra_pie` - Fibra Pie
- `obs` - Observaciones

### Combinaciones
- `comb1` - C1 (Comb1)
- `obs1` - Obs C1
- `comb2` - C2 (Comb2)
- `obs2` - Obs C2
- `comb3` - C3 (Comb3)
- `obs3` - Obs C3
- `comb4` - C4 (Comb4)
- `obs4` - Obs C4

### Cenefa & Calidad
- `medida_cenefa` - Med. de Cenefa
- `med_ini_rizo_cenefa` - Med. de inicio de rizo a cenefa
- `rasurado` - Rasurada (Sí/No)
- `cambio_repaso` - Cambio de repaso
- `vendedor` - Vendedor
- `cat_calidad` - No. Orden (Cat. Calidad)
- `obs5` - Observaciones (Obs5)

### Trama & Lucha
- `ancho_peine_trama` - TRAMA (Ancho Peine)
- `log_lucha_total` - LOG. de Lucha Total

### Fondo C1
- `cal_trama_fondo_c1` - C1 trama de Fondo
- `cal_trama_fondo_c12` - Hilo Fondo C1
- `fibra_trama_fondo_c1` - OBS Fondo C1
- `pasadas_trama_fondo_c1` - Pasadas Fondo C1

### Detalle Comb1
- `calibre_comb1` - C1
- `calibre_comb12` - Hilo C1
- `fibra_comb1` - OBS C1
- `cod_color_c1` - Cod Color C1
- `nom_color_c1` - Nombre Color C1
- `pasadas_comb1` - Pasadas C1

### Detalle Comb2
- `calibre_comb2` - C2
- `calibre_comb22` - Hilo C2
- `fibra_comb2` - OBS C2
- `cod_color_c2` - Cod Color C2
- `nom_color_c2` - Nombre Color C2
- `pasadas_comb2` - Pasadas C2

### Detalle Comb3
- `calibre_comb3` - C3
- `calibre_comb32` - Hilo C3
- `fibra_comb3` - OBS C3
- `cod_color_c3` - Cod Color C3
- `nom_color_c3` - Nombre Color C3
- `pasadas_comb3` - Pasadas C3

### Detalle Comb4
- `calibre_comb4` - C4
- `calibre_comb42` - Hilo C4
- `fibra_comb4` - OBS C4
- `cod_color_c4` - Cod Color C4
- `nom_color_c4` - Nombre Color C4
- `pasadas_comb4` - Pasadas C4

### Detalle Comb5
- `calibre_comb5` - C5
- `calibre_comb52` - Hilo C5
- `fibra_comb5` - OBS C5
- `cod_color_c5` - Cod Color C5
- `nom_color_c5` - Nombre Color C5
- `pasadas_comb5` - Pasadas C5

### Totales & Métricas
- `total` - Total
- `pasadas_dibujo` - Pasadas Dibujo
- `contraccion` - Contracción
- `tramas_cm_tejido` - Tramas cm/Tejido
- `contrac_rizo` - Contrac. Rizo
- `clasificacion_kg` - Clasificación (KG)
- `kg_dia` - KG/Día
- `densidad` - Densidad
- `pzas_dia_pasadas` - Pzas/Día/pasadas
- `pzas_dia_formula` - Pzas/Día/fórmula
- `dif` - DIF
- `efic` - EFIC
- `rev` - Rev
- `tiras` - TIRAS
- `pasadas` - PASADAS
- `columct` - ColumCT
- `columcu` - ColumCU
- `columcv` - ColumCV
- `comprobar_mod_dup` - COMPROBAR modelos duplicados

## Notas importantes:

1. **Primera fila**: Debe contener los encabezados exactos (pueden estar en minúsculas o con guiones bajos)
2. **Fechas**: Formato DD/MM/YYYY o YYYY-MM-DD
3. **Números decimales**: Usar punto como separador decimal
4. **Campos requeridos**: Los campos marcados con * son obligatorios
5. **Tamaño máximo**: 10MB
6. **Formatos soportados**: .xlsx, .xls

## Ejemplo de mapeo:

Si tu Excel tiene encabezados como:
- "Tamaño Clave" → se mapea a `tamano_clave`
- "No. Telar" → se mapea a `no_telar_id`
- "Fecha Orden" → se mapea a `fecha_tejido`

El sistema es flexible y puede manejar variaciones en los nombres de los encabezados.

