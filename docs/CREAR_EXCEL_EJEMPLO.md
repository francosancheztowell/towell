# Crear Excel de Ejemplo

## Pasos para crear un archivo Excel de prueba:

### 1. Abre Excel y crea una nueva hoja

### 2. En la primera fila (A1), escribe estos encabezados:
```
tamano_clave	orden_tejido	salon_tejido_id	no_telar_id	prioridad	nombre	clave_modelo	item_id	invent_size_id	tolerancia	codigo_dibujo	flogs_id	nombre_proyecto	clave
```

### 3. En la segunda fila (A2), escribe datos de ejemplo:
```
CH	12345	A	1	ALTA	MODELO A	CM001	AX001	CH	2	CD001	FL001	PROYECTO A	C
```

### 4. En la tercera fila (A3), escribe más datos:
```
MD	12346	B	2	MEDIA	MODELO B	CM002	AX002	MD	1	CD002	FL002	PROYECTO B	M
```

### 5. Guarda el archivo como "Codificacion modelos.xlsx"

## Estructura del archivo:

| Columna | Encabezado | Ejemplo |
|---------|------------|---------|
| A | tamano_clave | CH |
| B | orden_tejido | 12345 |
| C | salon_tejido_id | A |
| D | no_telar_id | 1 |
| E | prioridad | ALTA |
| F | nombre | MODELO A |
| G | clave_modelo | CM001 |
| H | item_id | AX001 |
| I | invent_size_id | CH |
| J | tolerancia | 2 |
| K | codigo_dibujo | CD001 |
| L | flogs_id | FL001 |
| M | nombre_proyecto | PROYECTO A |
| N | clave | C |

## Notas importantes:
- **Primera fila**: Debe contener exactamente estos encabezados
- **Sin espacios extra**: No agregues espacios al inicio o final
- **Sin caracteres especiales**: Evita acentos en los encabezados
- **Datos de ejemplo**: Puedes usar cualquier texto para los datos

## Si tienes problemas:
1. Verifica que los encabezados estén exactamente como se muestran
2. Asegúrate de que no haya filas vacías entre el encabezado y los datos
3. Guarda como .xlsx (no .xls)

