# 📅 Guía: Subir Líneas de Calendarios Excel

## ✅ Formato Correcto de Columnas

El archivo Excel debe tener EXACTAMENTE estas 5 columnas:

| Columna | Nombre | Tipo | Ejemplo |
|---------|--------|------|---------|
| A | No Calendario | Texto | `Tej1` |
| B | Inicio (Fecha Hora) | Fecha + Hora | `01/01/2025 06:00` |
| C | Fin (Fecha Hora) | Fecha + Hora | `01/01/2025 14:00` |
| D | Horas | Número | `8` |
| E | Turno | Número | `1` |

## 🕐 Formatos de Fecha y Hora Soportados

El sistema acepta múltiples formatos de fecha/hora:

### ✅ Formatos VÁLIDOS:

```
d/m/Y H:i:s    →  01/01/2025 06:30:45
d/m/Y H:i      →  01/01/2025 06:30
Y-m-d H:i:s    →  2025-01-01 06:30:45
Y-m-d H:i      →  2025-01-01 06:30
d-m-Y H:i:s    →  01-01-2025 06:30:45
d-m-Y H:i      →  01-01-2025 06:30
d.m.Y H:i:s    →  01.01.2025 06:30:45
d.m.Y H:i      →  01.01.2025 06:30
Solo Fecha     →  01/01/2025 (asume 00:00:00)
```

## 🎯 Instrucciones en Excel

### En Excel (recomendado):

1. **Crea las columnas** con los encabezados:
   - A1: `No Calendario`
   - B1: `Inicio (Fecha Hora)`
   - C1: `Fin (Fecha Hora)`
   - D1: `Horas`
   - E1: `Turno`

2. **Formatea las columnas de fecha**:
   - Selecciona columnas B y C
   - Clic derecho → "Formato de celdas"
   - Categoría: **Fecha** 
   - Formato: `14/03/2012 13:30:55` (o similar con horas)
   - ✅ O déjalo como **Texto** y escribe las fechas manualmente

3. **Llena los datos**:
   ```
   Tej1    01/01/2025 06:00    01/01/2025 14:00    8    1
   Tej1    01/01/2025 14:00    01/01/2025 22:00    8    2
   Tej1    01/01/2025 22:00    02/01/2025 06:00    8    3
   ```

## ⚠️ PROBLEMAS COMUNES

### Problema: "Fechas inválidas"
**Solución:**
- Verifica que las fechas incluyan la HORA
- Formato correcto: `01/01/2025 06:00`
- ❌ Incorrecto: `01/01/2025` (sin hora)

### Problema: Horas mal interpretadas
**Solución:**
- Si exportas de otra aplicación, verifica que incluya horas:minutes
- El sistema espera: `HH:MM` o `HH:MM:SS`
- Ejemplo correcto: `14:30` o `14:30:45`
- ❌ Incorrecto: `2:30 PM` (formato 12h no soportado)

### Problema: Formato de celda erróneo
**Solución:**
- Clic derecho en celda → Formato de celdas
- Cambia a **Fecha y Hora** o **Texto**
- ✅ Si está en Texto, escribe: `01/01/2025 14:30`

## 📊 Ejemplo Completo de Excel

```
No Calendario | Inicio (Fecha Hora)    | Fin (Fecha Hora)       | Horas | Turno
Tej1          | 01/01/2025 06:00:00    | 01/01/2025 14:00:00    | 8     | 1
Tej1          | 01/01/2025 14:00:00    | 01/01/2025 22:00:00    | 8     | 2
Tej1          | 01/01/2025 22:00:00    | 02/01/2025 06:00:00    | 8     | 3
Tej2          | 02/01/2025 06:00:00    | 02/01/2025 14:00:00    | 8     | 1
Tej2          | 02/01/2025 14:00:00    | 02/01/2025 22:00:00    | 8     | 2
```

## 🔍 Revisar los Logs

Si el import falla, revisa el archivo de logs:
```
storage/logs/laravel.log
```

Busca líneas que digan:
- ✅ "✓ Fecha Excel parseada" = Éxito
- ✅ "✓ Línea guardada" = Registro creado
- ❌ "✗ No se pudo parsear fecha" = Error en formato de fecha
- ❌ "Fechas no válidas" = Verificar datos de entrada

## 💡 Tips

- **Siempre incluye la HORA** en las fechas
- **Usa formatos consistentes** en todo el Excel
- **Verifica los datos de ejemplo** antes de hacer bulk
- **Descarga un plantilla** si está disponible en el sistema






