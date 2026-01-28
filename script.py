import pandas as pd
import math
import re
import unicodedata

# ============================================================
# CONFIGURACIÓN
# ============================================================
excel_path = "ProduccionesUrdido.xlsx"   # <-- cambia tu archivo
sheet_name = 0                         # o "Hoja1"
db_name = "ProdTowel"w
schema_name = "dbo"
tabla_sql = f"[{schema_name}].[UrdProgramaUrdido]"
registros_por_archivo = 1000

# Tabla real (según tu HELP)
# Id = IDENTITY (NO se inserta)
# CreatedAt = NOT NULL DEFAULT(getdate()) (normalmente NO se inserta)

# ============================================================
# ENCABEZADOS ESPERADOS EN EXCEL (deben existir)
# (puedes ajustar nombres según tu Excel)
# ============================================================
expected_headers = [
    "Folio","NoTelarId","RizoPie","Cuenta","Calibre","FechaReq","Fibra","Metros","Kilos",
    "SalonTejidoId","MaquinaId","BomId","FechaProg","Status",
    "FolioConsumo","CreatedAt","TipoAtado","CveEmpl","NomEmpl",
    "BomFormula","LoteProveedor","ax","Observaciones","Prioridad"
]

# ============================================================
# OUTPUT SQL (SIN Id y SIN CreatedAt)
# y FolioConsumo lo haremos consecutivo CH00001...
# ============================================================
columnas_sql_output = [
    "Folio","NoTelarId","RizoPie","Cuenta",
    "Calibre","FechaReq","Fibra","Metros","Kilos",
    "SalonTejidoId","MaquinaId","BomId","FechaProg","Status",
    "FolioConsumo","TipoAtado","CveEmpl","NomEmpl","BomFormula","LoteProveedor",
    "ax","Observaciones","Prioridad"
]

# Tipos según tu HELP
DATE_FIELDS = {"FechaReq", "FechaProg"}       # (date)
REAL_FIELDS = {"Calibre", "Metros", "Kilos"}  # (real)
INT_FIELDS  = {"Prioridad"}                   # (int)
BIT_FIELDS  = {"ax"}                          # (bit)
VARCHAR_FIELDS = {"FolioConsumo", "Observaciones"}  # varchar en tabla

# ============================================================
# HELPERS
# ============================================================
def normalize_header(h) -> str:
    if h is None:
        h = ""
    h = str(h).strip()
    h = h.replace("\n", " ").replace("\r", " ").strip()
    h = unicodedata.normalize("NFKD", h)
    h = "".join(ch for ch in h if not unicodedata.combining(ch))
    h = h.lower()
    h = re.sub(r"[^a-z0-9]+", "", h)  # compacto
    return h

def parse_date_any(v):
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return None
    dt = pd.to_datetime(v, dayfirst=True, errors="coerce")
    return None if pd.isna(dt) else dt.date()

def parse_real_calibre(v):
    """
    Tu columna Calibre es REAL, pero el Excel trae cosas como '12/1', '20/2', '370/1'.
    Regla: si viene con '/', reemplazamos '/' por '.' -> 12.1, 20.2, 370.1
    Si viene numérico normal, lo parsea directo.
    """
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return None
    s = str(v).strip()
    if s == "" or s.lower() in {"na","n/a","null","nan","-"}:
        return None
    s = s.replace(",", ".")  # por si decimal con coma
    if "/" in s:
        s = s.replace("/", ".")
    try:
        return float(s)
    except:
        return None

def parse_real(v):
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return None
    s = str(v).strip()
    if s == "" or s.lower() in {"na","n/a","null","nan","-"}:
        return None
    s = s.replace(",", ".")
    try:
        return float(s)
    except:
        return None

def parse_int(v):
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return None
    s = str(v).strip()
    if s == "" or s.lower() in {"na","n/a","null","nan","-"}:
        return None
    try:
        return int(float(s))
    except:
        return None

def parse_bit(v):
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return None
    s = str(v).strip().lower()
    if s in {"", "na", "n/a", "null", "nan", "-"}:
        return None
    if s in {"1","true","si","sí","yes","y"}:
        return 1
    return 0

def sql_quote_text(v, nvarchar=True):
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return "NULL"
    s = str(v).strip()
    if s == "" or s.lower() in {"na","n/a","null","nan","-"}:
        return "NULL"
    s = s.replace("'", "''")
    return (f"N'{s}'" if nvarchar else f"'{s}'")

def sql_format(v, col):
    if col in DATE_FIELDS:
        d = parse_date_any(v)
        return "NULL" if d is None else f"'{d.isoformat()}'"

    if col == "Calibre":
        x = parse_real_calibre(v)
        return "NULL" if x is None else str(x)

    if col in REAL_FIELDS:
        x = parse_real(v)
        return "NULL" if x is None else str(x)

    if col in INT_FIELDS:
        x = parse_int(v)
        return "NULL" if x is None else str(x)

    if col in BIT_FIELDS:
        x = parse_bit(v)
        return "NULL" if x is None else str(x)

    # texto
    # FolioConsumo y Observaciones son varchar en la tabla, el resto nvarchar
    if col in VARCHAR_FIELDS:
        return sql_quote_text(v, nvarchar=False)
    return sql_quote_text(v, nvarchar=True)

# ============================================================
# LEER EXCEL
# ============================================================
df_raw = pd.read_excel(excel_path, sheet_name=sheet_name)

# Mapear columnas por nombre (tolerante a acentos/espacios)
actual_cols = list(df_raw.columns)
actual_norm = {normalize_header(c): c for c in actual_cols}

missing = []
picked = {}
for h in expected_headers:
    key = normalize_header(h)
    if key in actual_norm:
        picked[h] = actual_norm[key]
    else:
        missing.append(h)

if missing:
    raise ValueError(
        "Faltan columnas en el Excel:\n- " + "\n- ".join(missing) +
        "\n\nColumnas leídas:\n" + "\n".join([str(c) for c in actual_cols])
    )

df = df_raw[[picked[h] for h in expected_headers]].copy()
df.columns = expected_headers
df = df.where(pd.notnull(df), None)

# ============================================================
# GENERAR FolioConsumo CONSECUTIVO (CH00001...)
# Ignora lo que venga en el Excel y lo crea nuevo por orden de Prioridad
# ============================================================
# Si no hay Prioridad, la generamos 1..N
if df["Prioridad"].isnull().all():
    df["Prioridad"] = list(range(1, len(df) + 1))

# Orden estable por prioridad
df = df.sort_values(by=["Prioridad", "Folio", "NoTelarId"], kind="mergesort").reset_index(drop=True)

# Creamos CH consecutivo desde 1 (si quieres que arranque desde el máximo de la BD, eso lo hacemos en SQL)
df["FolioConsumo"] = [f"CH{str(i).zfill(5)}" for i in range(1, len(df) + 1)]

# ============================================================
# ARMAR SQL (SIN Id y SIN CreatedAt)
# ============================================================
total_filas = len(df)
num_archivos = math.ceil(total_filas / registros_por_archivo)
cols_sql = ", ".join([f"[{c}]" for c in columnas_sql_output])

print(f"Filas totales: {total_filas}")
print(f"Generando {num_archivos} archivos .sql...")

for i in range(num_archivos):
    ini = i * registros_por_archivo
    fin = min(total_filas, ini + registros_por_archivo)
    chunk = df.iloc[ini:fin]

    filas_sql = []
    for _, row in chunk.iterrows():
        valores = [sql_format(row[col], col) for col in columnas_sql_output]
        filas_sql.append("(" + ", ".join(valores) + ")")

    contenido = (
        f"USE [{db_name}];\nGO\n"
        f"SET DATEFORMAT dmy;\nGO\n"
        f"INSERT INTO {tabla_sql} ({cols_sql}) VALUES\n"
        + ",\n".join(filas_sql)
        + ";\nGO\n"
    )

    nombre_archivo = f"UrdProgramaUrdido_{i+1}.sql"
    with open(nombre_archivo, "w", encoding="utf-8") as f:
        f.write(contenido)

    print(f"Archivo generado: {nombre_archivo}")

# ============================================================
# DEBUG ROW-BY-ROW (para detectar fila exacta que truena)
# ============================================================
debug_sql = []
debug_sql.append(f"USE [{db_name}];\nGO\nSET NOCOUNT ON;\nGO\nSET DATEFORMAT dmy;\nGO\n")

for idx, row in df.iterrows():
    vals = ", ".join([sql_format(row[c], c) for c in columnas_sql_output])
    debug_sql.append(
        "BEGIN TRY\n"
        f"  INSERT INTO {tabla_sql} ({cols_sql}) VALUES ({vals});\n"
        "END TRY\n"
        "BEGIN CATCH\n"
        f"  PRINT 'FILA FALLÓ (df index): {idx}';\n"
        "  PRINT ERROR_MESSAGE();\n"
        "  THROW;\n"
        "END CATCH\n"
        "GO\n"
    )

with open("UrdProgramaUrdido_debug_rowbyrow.sql", "w", encoding="utf-8") as f:
    f.write("\n".join(debug_sql))

print("Archivo generado: UrdProgramaUrdido_debug_rowbyrow.sql")
print("Proceso completado.")
