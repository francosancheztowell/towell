# Ejemplo de Excel para Codificación de Modelos

## Estructura mínima requerida

Tu archivo Excel debe tener estos encabezados en la primera fila:

### Encabezados básicos (requeridos):
- `tamano_clave` o `tamaño_clave` o `tamano clave` o `tamaño clave`
- `orden_tejido` o `orden tejido` o `orden`
- `salon_tejido_id` o `salon_tejido` o `departamento` o `salón_tejido`
- `no_telar_id` o `no_telar` o `telar_actual` o `no telar`
- `prioridad`
- `nombre` o `modelo`
- `clave_modelo` o `clave_mod` o `clave modelo`
- `item_id` o `clave_ax` o `item id`
- `invent_size_id` o `tamano` o `tamaño`
- `tolerancia`
- `codigo_dibujo` o `código_dibujo`
- `flogs_id` o `id_flog`
- `nombre_proyecto` o `nombre de formato logístico`
- `clave`

### Ejemplo de datos:

| tamano_clave | orden_tejido | salon_tejido_id | no_telar_id | prioridad | nombre | clave_modelo | item_id | invent_size_id | tolerancia | codigo_dibujo | flogs_id | nombre_proyecto | clave |
|--------------|--------------|-----------------|-------------|-----------|--------|--------------|---------|----------------|------------|---------------|----------|-----------------|-------|
| CH           | 12345        | A               | 1           | ALTA      | MODELO A | CM001        | AX001   | CH             | 2          | CD001          | FL001    | PROYECTO A      | C     |
| MD           | 12346        | B               | 2           | MEDIA     | MODELO B | CM002        | AX002   | MD             | 1          | CD002          | FL002    | PROYECTO B      | M     |

## Campos opcionales (se pueden agregar):

### Fechas:
- `fecha_tejido` o `fecha_orden`
- `fecha_cumplimiento`
- `fecha_compromiso`

### Producción:
- `pedido` o `cantidad_a_producir`
- `peine`
- `ancho_toalla` o `ancho`
- `largo_toalla` o `largo`
- `peso_crudo` o `p_crudo`
- `luchaje`
- `calibre_trama` o `tra`
- `calibre_trama2` o `hilo`

### Y muchos más campos opcionales...

## Notas importantes:

1. **Primera fila**: Debe contener los encabezados
2. **Nombres flexibles**: El sistema acepta múltiples variaciones de nombres
3. **Campos requeridos**: Los 14 campos básicos son obligatorios
4. **Formato de fechas**: DD/MM/YYYY o YYYY-MM-DD
5. **Números**: Usar punto como separador decimal
6. **Tamaño máximo**: 10MB
7. **Formatos**: .xlsx, .xls

## Si tienes errores:

El sistema te mostrará exactamente qué campos faltan y en qué fila. Por ejemplo:
- "Campos requeridos faltantes: Tamaño Clave, Orden Tejido" en fila 2
- Esto significa que en la fila 2 del Excel, faltan esos campos o están vacíos

