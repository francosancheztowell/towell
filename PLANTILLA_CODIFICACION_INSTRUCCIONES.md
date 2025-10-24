# 📋 Plantilla de Codificación - Instrucciones de Uso

## ✅ Plantillas Creadas

He creado **dos plantillas** para ti:

1. **`plantilla_codificacion_ejemplo.xlsx`** - Con datos de ejemplo para probar
2. **`PLANTILLA_CODIFICACION_LIMPIA.xlsx`** - Plantilla vacía para tus datos reales

## 🧪 Prueba Realizada

✅ **La plantilla fue probada exitosamente:**
- Se importaron 2 registros de ejemplo
- 0 errores durante la importación
- Los datos se guardaron correctamente en la base de datos
- El sistema maneja correctamente valores como "ABIERTO" en el campo "Cantidad a Producir"

## 📝 Cómo Usar la Plantilla

### Paso 1: Abrir la Plantilla
- Abre el archivo `PLANTILLA_CODIFICACION_LIMPIA.xlsx`
- Verás 2 filas de encabezados (filas 1 y 2) y 1 fila de ejemplo (fila 3)

### Paso 2: Completar con Tus Datos
- **Reemplaza la fila 3** con tus datos reales
- **Agrega más filas** según necesites (fila 4, 5, 6, etc.)
- **Mantén los encabezados** en las filas 1 y 2

### Paso 3: Campos Importantes

#### 🔑 Campos Obligatorios
- **Clave mod.**: Identificador único del modelo (ej: "MOD001")
- **Orden**: Número de orden de producción (ej: "ORD-2024-001")

#### 📊 Campos Flexibles
- **Cantidad a Producir**: Puede ser número (1000) o texto ("ABIERTO")
- **Tra**: Campo de calibre trama - acepta texto o números
- **Tipo plano**: Acepta texto literal (ej: "PLANO A")

#### 📅 Campos de Fecha
- **Fecha Orden**: Formato YYYY-MM-DD (ej: "2024-01-15")
- **Fecha Cumplimiento**: Formato YYYY-MM-DD
- **Fecha Compromiso**: Formato YYYY-MM-DD

#### 🔢 Campos Numéricos
- **Peine, Ancho, Largo, P_crudo, Luchaje**: Números enteros
- **Rizo, Pie, Total, KGDia, Densidad**: Números decimales
- **Veloc. Mínima**: Número entero

### Paso 4: Subir a la Aplicación
1. Guarda tu archivo Excel
2. Ve a la sección de Codificación en la aplicación web
3. Usa el botón "Subir Excel" para cargar tu archivo
4. El sistema procesará automáticamente todos los registros

## 🛠️ Características Técnicas

### ✅ Problemas Resueltos
- **Error de casting decimal**: Solucionado con accessors seguros
- **Valores "ABIERTO"**: Se manejan como texto literal
- **Campos vacíos**: Se procesan correctamente como NULL
- **Fechas**: Se convierten automáticamente al formato correcto

### 🔍 Validaciones Automáticas
- El sistema detecta duplicados por (Clave mod. + Orden)
- Si existe un registro con la misma clave y orden, lo actualiza
- Si no existe, crea un nuevo registro

### 📈 Rendimiento
- Procesamiento por lotes de 300 registros
- Manejo de memoria optimizado (1GB)
- Sin límite de tiempo de ejecución

## 🚨 Notas Importantes

1. **NO modifiques los encabezados** de las filas 1 y 2
2. **Mantén el formato** de las columnas como están
3. **Usa la fila 3 como ejemplo** para el formato de datos
4. **Guarda como .xlsx** (formato Excel 2007+)

## 📞 Soporte

Si encuentras algún problema:
1. Verifica que los encabezados estén correctos
2. Asegúrate de que las fechas estén en formato YYYY-MM-DD
3. Revisa que los campos obligatorios (Clave mod. y Orden) estén llenos

¡La plantilla está lista para usar! 🎉
