#!/usr/bin/env python3
"""
OEE Atadores Excel Export — Python replacement for the PHP PhpSpreadsheet job.

Worker (misma máquina que `php artisan queue:work` en cola oee-atadores):
  pip install -r scripts/requirements.txt
  ODBC Driver 17 or 18 for SQL Server (pyodbc).

Config Laravel: config/oee.php — OEE_PYTHON_BINARY, OEE_PYTHON_TIMEOUT, OEE_EXPORT_DB_CONNECTION.

Usage:
    python oee_export.py \
        --week-start 2026-03-23 \
        --week-end   2026-03-23 \
        --token      abc123 \
        --file-path  "\\\\192.168.2.11\\ti-system\\OEE_ATADORES.xlsx" \
        --status-file "C:\\xampp\\htdocs\\Towell\\storage\\app\\oee_python_abc123.json" \
        --db-host HOST --db-database DB --db-username USER --db-password PASS
"""

from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import sys
import tempfile
import time
import traceback
from copy import copy
from datetime import date, datetime, timedelta, timezone
from pathlib import Path
from typing import Any

# ---------------------------------------------------------------------------
# Optional dotenv support (graceful if not installed)
# ---------------------------------------------------------------------------
try:
    from dotenv import load_dotenv as _load_dotenv

    def _try_load_dotenv() -> None:
        # Walk up from script location looking for .env
        current = Path(__file__).resolve().parent
        for _ in range(5):
            env_file = current / ".env"
            if env_file.is_file():
                _load_dotenv(env_file)
                return
            current = current.parent
except ImportError:
    def _try_load_dotenv() -> None:
        pass


# ---------------------------------------------------------------------------
# Constants (mirror of Reporte00EAtadoresExport.php)
# ---------------------------------------------------------------------------
DETAIL_START_ROW = 4       # 1-indexed, relative to section top
BASE_DETAIL_ROWS = 42
FOOTER_BASE_ROW = 46
DEFAULT_BLOCK_HEIGHT = 6
MIN_BLOCKS_PER_TURN = 2
CAPACITACION_HEIGHT = 6
MAX_COLUMN_INDEX = 88      # CJ

# Turn definitions (relative prototype rows, 1-indexed within section)
TURN_DEFINITIONS = {
    1: {"label": "1 ER TURNO", "first_prototype": [4, 9],  "next_prototype": [10, 15]},
    2: {"label": "2 DO TURNO", "first_prototype": [16, 21], "next_prototype": [22, 27]},
    3: {"label": "3 ER TURNO", "first_prototype": [28, 33], "next_prototype": [34, 39]},
}

# Column definitions per day (0 = Monday)
DAY_DEFINITIONS = {
    0: {"header": "H2",  "start": "D",  "end": "E",  "duration": "F",  "avg_time": "G",  "atado": "H",  "telar": "I",  "calif": "J",  "five_s": "K",  "avg_calif": "L",  "merma": "M",  "avg_merma": "N",  "footer_count": "I"},
    1: {"header": "T2",  "start": "P",  "end": "Q",  "duration": "R",  "avg_time": "S",  "atado": "T",  "telar": "U",  "calif": "V",  "five_s": "W",  "avg_calif": "X",  "merma": "Y",  "avg_merma": "Z",  "footer_count": "U"},
    2: {"header": "AF2", "start": "AB", "end": "AC", "duration": "AD", "avg_time": "AE", "atado": "AF", "telar": "AG", "calif": "AH", "five_s": "AI", "avg_calif": "AJ", "merma": "AK", "avg_merma": "AL", "footer_count": "AG"},
    3: {"header": "AR2", "start": "AN", "end": "AO", "duration": "AP", "avg_time": "AQ", "atado": "AR", "telar": "AS", "calif": "AT", "five_s": "AU", "avg_calif": "AV", "merma": "AW", "avg_merma": "AX", "footer_count": "AS"},
    4: {"header": "BD2", "start": "AZ", "end": "BA", "duration": "BB", "avg_time": "BC", "atado": "BD", "telar": "BE", "calif": "BF", "five_s": "BG", "avg_calif": "BH", "merma": "BI", "avg_merma": "BJ", "footer_count": "BE"},
    5: {"header": "BP2", "start": "BL", "end": "BM", "duration": "BN", "avg_time": "BO", "atado": "BP", "telar": "BQ", "calif": "BR", "five_s": "BS", "avg_calif": "BT", "merma": "BU", "avg_merma": "BV", "footer_count": "BQ"},
    6: {"header": "CB2", "start": "BX", "end": "BY", "duration": "BZ", "avg_time": "CA", "atado": "CB", "telar": "CC", "calif": "CD", "five_s": "CE", "avg_calif": "CF", "merma": "CG", "avg_merma": "CH", "footer_count": "CC"},
}

# Summary columns (CK..CU) mirrors writeCkCuFormulas
AVG_CALIF_COLS = ["L", "X", "AJ", "AV", "BH", "BT", "CF"]
AVG_TIME_COLS  = ["G", "S", "AE", "AQ", "BC", "BO", "CA"]
AVG_MERMA_COLS = ["N", "Z", "AL", "AX", "BJ", "BV", "CH"]

# ---------------------------------------------------------------------------
# Status file helpers
# ---------------------------------------------------------------------------

# Última fase del export (para mensajes de error cuando la excepción no trae texto).
_RUN_PHASE = "inicio"


def _set_run_phase(phase: str) -> None:
    global _RUN_PHASE  # noqa: PLW0603
    _RUN_PHASE = phase


def _format_exception_detail(exc: BaseException) -> str:
    """Texto útil cuando str(exc) es vacío o solo tuplas (p. ej. algunos errores ODBC)."""
    parts: list[str] = [f"{type(exc).__name__}"]
    s = str(exc).strip()
    if s:
        parts.append(s)
    if getattr(exc, "args", None):
        dec: list[Any] = []
        for a in exc.args:
            if isinstance(a, (bytes, bytearray)):
                dec.append(bytes(a).decode("utf-8", "replace"))
            else:
                dec.append(a)
        parts.append(f"args={dec!r}")
    if isinstance(exc, OSError):
        for name in ("winerror", "errno", "strerror", "filename", "filename2"):
            v = getattr(exc, name, None)
            if v is not None:
                parts.append(f"{name}={v!r}")
    return " | ".join(parts)[:2000]


def write_status(status_file: str, estado: str, mensaje: str = "", elapsed_s: float = 0) -> None:
    """Write job status JSON atomically. Never raises."""
    try:
        payload = {
            "estado": estado,
            "mensaje": mensaje,
            "elapsed_s": round(elapsed_s, 1),
            "updated_at": datetime.now(timezone.utc).isoformat(),
        }
        tmp = status_file + ".tmp"
        with open(tmp, "w", encoding="utf-8") as f:
            json.dump(payload, f)
        os.replace(tmp, status_file)
    except Exception as exc:  # noqa: BLE001
        print(f"CRITICAL: cannot write status file {status_file}: {exc}", file=sys.stderr)


# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------

def parse_args() -> argparse.Namespace:
    _try_load_dotenv()

    p = argparse.ArgumentParser(description="OEE Atadores Excel Export")
    p.add_argument("--week-start",   required=True)
    p.add_argument("--week-end",     required=True)
    p.add_argument("--token",        required=True)
    p.add_argument("--file-path",    required=True)
    p.add_argument("--status-file",  required=True)
    p.add_argument("--db-host",      default=os.environ.get("DB_HOST", "localhost"))
    p.add_argument("--db-database",  default=os.environ.get("DB_DATABASE", ""))
    p.add_argument("--db-username",  default=os.environ.get("DB_USERNAME", ""))
    p.add_argument("--db-password",  default=os.environ.get("DB_PASSWORD", ""))
    p.add_argument("--db-port",      default=int(os.environ.get("DB_PORT", "1433")), type=int)
    p.add_argument("--log-file",     default=None)
    return p.parse_args()


# ---------------------------------------------------------------------------
# Database
# ---------------------------------------------------------------------------

def _odbc_server_clause(host: str, port: int) -> str:
    """
    Instancia con nombre (host\\INSTANCIA): no añadir ,puerto — ODBC falla si se mezcla.
    Solo host o IP: usar host,puerto como siempre.
    """
    h = (host or "").strip()
    if not h:
        return f"localhost,{port}"
    if "\\" in h:
        return h
    return f"{h},{port}"


def get_db_connection(args: argparse.Namespace):
    import pyodbc  # noqa: PLC0415

    drivers = [d for d in pyodbc.drivers() if "SQL Server" in d]
    if not drivers:
        raise RuntimeError(
            "No ODBC SQL Server driver found. Install 'ODBC Driver 17 for SQL Server' from Microsoft."
        )
    driver = next((d for d in drivers if "17" in d or "18" in d), drivers[0])

    server = _odbc_server_clause(args.db_host, args.db_port)
    conn_str = (
        f"DRIVER={{{driver}}};"
        f"SERVER={server};"
        f"DATABASE={args.db_database};"
        f"UID={args.db_username};"
        f"PWD={args.db_password};"
        "Encrypt=no;TrustServerCertificate=yes;"
        "MARS_Connection=yes;"
    )
    try:
        return pyodbc.connect(conn_str, timeout=30)
    except Exception as e:  # noqa: BLE001
        raise RuntimeError(
            "No se pudo conectar a SQL Server por ODBC. "
            "Revisa DB_HOST (si usas instancia con \\\\ no pongas puerto en .env), usuario, contraseña y firewall. "
            f"Detalle: {e!r}"
        ) from e


def query_records(conn, week_start_str: str, week_end_str: str) -> list[dict]:
    """Mirror of PHP preloadRecordsByWeek — fetches all records in range."""
    # Range end = last day of the last requested week (sunday)
    week_start = date.fromisoformat(week_start_str)
    week_end   = date.fromisoformat(week_end_str)
    range_end  = week_end + timedelta(days=6)

    sql = """
        SELECT Id, FechaArranque, Turno, CveTejedor, NomTejedor, Tipo,
               NoTelarId, HrInicio, HoraArranque, Calidad, Limpieza, MergaKg
        FROM dbo.AtaMontadoTelas
        WHERE Estatus = 'Autorizado'
          AND FechaArranque IS NOT NULL
          AND FechaArranque >= ? AND FechaArranque <= ?
        ORDER BY FechaArranque, Turno, CveTejedor, NomTejedor, HrInicio, HoraArranque, Id
    """
    cursor = conn.cursor()
    try:
        cursor.execute(sql, week_start_str, str(range_end))
    except Exception as e:  # noqa: BLE001
        raise RuntimeError(
            "Fallo al ejecutar consulta en dbo.AtaMontadoTelas "
            f"(FechaArranque entre {week_start_str!r} y {str(range_end)!r}): "
            f"{_format_exception_detail(e)}"
        ) from e
    cols = [col[0] for col in cursor.description]
    rows = []
    for row in cursor.fetchall():
        rows.append(dict(zip(cols, row)))
    cursor.close()
    return rows


# ---------------------------------------------------------------------------
# Record preparation (mirrors PHP prepareRecords)
# ---------------------------------------------------------------------------

def _monday_of(d: date) -> date:
    return d - timedelta(days=d.weekday())


def normalize_turn(value) -> int | None:
    if value is None:
        return None
    try:
        t = int(str(value).strip())
        return t if t in (1, 2, 3) else None
    except (ValueError, TypeError):
        return None


def resolve_atador_key(row: dict) -> str:
    cve = str(row.get("CveTejedor") or "").strip()
    if cve:
        return cve
    return str(row.get("NomTejedor") or "").strip()


def resolve_atado_label(tipo) -> str:
    t = str(tipo or "").strip().lower()
    if t == "rizo":
        return "R"
    if t == "pie":
        return "P"
    return t[0].upper() if t else ""


def normalize_time(value) -> str | None:
    if value is None:
        return None
    raw = str(value).strip()
    if not raw:
        return None
    m = re.match(r"^(\d{1,2}:\d{2}:\d{2})", raw)
    if m:
        return m.group(1)
    m = re.match(r"^(\d{1,2}:\d{2})$", raw)
    if m:
        return m.group(1) + ":00"
    # try datetime parse
    for fmt in ("%H:%M:%S", "%H:%M", "%Y-%m-%d %H:%M:%S", "%Y-%m-%dT%H:%M:%S"):
        try:
            return datetime.strptime(raw, fmt).strftime("%H:%M:%S")
        except ValueError:
            continue
    return None


def normalize_number(value):
    if value is None or value == "":
        return None
    try:
        return float(value)
    except (ValueError, TypeError):
        return None


def prepare_records(raw_rows: list[dict], week_monday: date) -> list[dict]:
    """Mirror of PHP prepareRecords."""
    out = []
    for row in raw_rows:
        fa = row.get("FechaArranque")
        if fa is None:
            continue
        if isinstance(fa, datetime):
            fecha = fa.date()
        elif isinstance(fa, date):
            fecha = fa
        else:
            try:
                fecha = datetime.fromisoformat(str(fa)).date()
            except ValueError:
                continue

        turno = normalize_turn(row.get("Turno"))
        if turno is None:
            continue

        day_index = (fecha - week_monday).days
        if day_index < 0 or day_index > 6:
            continue

        out.append({
            "id": int(row.get("Id") or 0),
            "fecha": fecha,
            "day_index": day_index,
            "turno": turno,
            "atador_key": resolve_atador_key(row),
            "atado": resolve_atado_label(row.get("Tipo")),
            "telar": str(row.get("NoTelarId") or "").strip(),
            "hora_inicio": normalize_time(row.get("HrInicio")),
            "hora_fin":    normalize_time(row.get("HoraArranque")),
            "calif": normalize_number(row.get("Calidad")),
            "five_s": normalize_number(row.get("Limpieza")),
            "merma": normalize_number(row.get("MergaKg")),
        })
    return out


def group_records_by_week(raw_rows: list[dict]) -> dict[str, list[dict]]:
    """Group raw rows by ISO Monday date string."""
    groups: dict[str, list[dict]] = {}
    for row in raw_rows:
        fa = row.get("FechaArranque")
        if fa is None:
            continue
        if isinstance(fa, datetime):
            fecha = fa.date()
        elif isinstance(fa, date):
            fecha = fa
        else:
            try:
                fecha = datetime.fromisoformat(str(fa)).date()
            except ValueError:
                continue
        monday = str(_monday_of(fecha))
        groups.setdefault(monday, []).append(row)
    return groups


# ---------------------------------------------------------------------------
# Layout builder (mirrors PHP buildLayout)
# ---------------------------------------------------------------------------

def build_layout(records: list[dict], section_top_row: int = 1) -> dict:
    """
    Build section layout mirroring PHP buildLayout().
    section_top_row: 1-indexed absolute row where this section starts in the sheet.
    Returns dict with turns, capacitacion, footer_row, week_number, detail_start_row, last_detail_row.
    """
    def offset(row: int) -> int:
        return section_top_row + row - 1

    # Group by turn → atador_key
    by_turn: dict[int, dict[str, list[dict]]] = {}
    for rec in records:
        by_turn.setdefault(rec["turno"], {}).setdefault(rec["atador_key"], []).append(rec)

    turns = {}
    current_row = offset(DETAIL_START_ROW)

    for turno, tdef in TURN_DEFINITIONS.items():
        atador_map = by_turn.get(turno, {})
        sorted_keys = sorted(atador_map.keys(), key=str.casefold)

        blocks = []
        for atador_key in sorted_keys:
            items = atador_map[atador_key]
            items_by_day: list[list[dict]] = [[] for _ in range(7)]
            for item in items:
                items_by_day[item["day_index"]].append(item)
            max_daily = max((len(d) for d in items_by_day), default=0)
            blocks.append({
                "atador_key": atador_key,
                "items_by_day": items_by_day,
                "height": max(DEFAULT_BLOCK_HEIGHT, max_daily),
            })

        while len(blocks) < MIN_BLOCKS_PER_TURN:
            blocks.append({
                "atador_key": "",
                "items_by_day": [[] for _ in range(7)],
                "height": DEFAULT_BLOCK_HEIGHT,
            })

        turn_start = current_row
        for i, block in enumerate(blocks):
            proto = tdef["first_prototype"] if i == 0 else tdef["next_prototype"]
            block["row_start"] = current_row
            block["row_end"]   = current_row + block["height"] - 1
            block["prototype"] = [offset(proto[0]), offset(proto[1])]
            current_row = block["row_end"] + 1

        turns[turno] = {
            "label": tdef["label"],
            "row_start": turn_start,
            "row_end": current_row - 1,
            "blocks": blocks,
        }

    cap_start = current_row
    cap_end   = cap_start + CAPACITACION_HEIGHT - 1
    footer_row = cap_end + 1

    return {
        "turns": turns,
        "capacitacion": {
            "label": "CAPACITACION",
            "row_start": cap_start,
            "row_end":   cap_end,
            "prototype": [offset(40), offset(45)],
        },
        "detail_start_row": offset(DETAIL_START_ROW),
        "last_detail_row":  cap_end,
        "footer_row":       footer_row,
    }


# ---------------------------------------------------------------------------
# Section parsing (mirrors OeeAtadoresFileService::parseDetalleSections)
# ---------------------------------------------------------------------------

def normalize_label(value) -> str:
    if value is None:
        return ""
    return " ".join(str(value).upper().split())


def _merged_cell_top_left(ws, row: int, col: int) -> tuple[int, int]:
    """If (row,col) is inside a merged range, return top-left of that range."""
    for m in ws.merged_cells.ranges:
        if m.min_row <= row <= m.max_row and m.min_col <= col <= m.max_col:
            return m.min_row, m.min_col
    return row, col


def get_cell_effective_value(ws, row: int, col: int):
    """Value for display/parse; resolves merged cells (openpyxl only stores value on top-left)."""
    r, c = _merged_cell_top_left(ws, row, col)
    return ws.cell(r, c).value


def parse_detalle_sections(ws) -> dict:
    """
    Scan DETALLE worksheet for section markers.
    Returns {"sections": [...], "map": {week_num: section}}.
    Each section: {top, start, footer, rows, week} — top = max(1, start_row - 1) per PHP.
    """
    from openpyxl.utils import column_index_from_string as col_idx  # noqa: PLC0415

    COL_A = 1
    COL_B = 2
    COL_CJ = col_idx("CJ")

    max_row = ws.max_row or 1
    starts: list[int] = []
    footers: list[int] = []

    for row in range(1, max_row + 1):
        val_a = normalize_label(get_cell_effective_value(ws, row, COL_A))
        val_b = normalize_label(get_cell_effective_value(ws, row, COL_B))
        val_cj = normalize_label(get_cell_effective_value(ws, row, COL_CJ))

        # One marker per merged region (openpyxl only stores value on top-left; we resolve merges)
        if val_a == "SEMANA":
            tl_r, tl_c = _merged_cell_top_left(ws, row, COL_A)
            if tl_r == row and tl_c == COL_A:
                starts.append(row)

        if val_b == "SEMANA" and val_cj == "ATADOS":
            tl_r, tl_c = _merged_cell_top_left(ws, row, COL_B)
            if tl_r == row and tl_c == COL_B:
                footers.append(row)

    sections: list[dict[str, Any]] = []
    section_map: dict[int, dict] = {}
    footer_index = 0

    for start_row in starts:
        while footer_index < len(footers) and footers[footer_index] <= start_row:
            footer_index += 1

        if footer_index >= len(footers):
            break

        footer_row = footers[footer_index]
        footer_index += 1

        top_row = max(1, start_row - 1)
        week_num = _resolve_week_number(ws, footer_row)

        section = {
            "top": top_row,
            "start": start_row,
            "footer": footer_row,
            "rows": footer_row - top_row + 1,
            "week": week_num,
        }
        sections.append(section)

        if week_num is not None and week_num not in section_map:
            section_map[week_num] = section

    sections.sort(key=lambda s: s["top"])

    return {"sections": sections, "map": section_map}


def _resolve_week_formula_value(ws, value: Any, depth: int = 0) -> int | None:
    """Mirror PHP resolveWeekFormulaValue for =COLrow+/-N."""
    if depth > 8 or not isinstance(value, str):
        return None
    formula = value.strip()
    if not formula.startswith("="):
        return None
    m = re.match(r"^=([A-Z]+)(\d+)([+-]\d+)$", formula.upper())
    if not m:
        return None
    col_l, row_s, delta_s = m.group(1), m.group(2), m.group(3)
    from openpyxl.utils import column_index_from_string as col_idx  # noqa: PLC0415

    r = int(row_s)
    c = col_idx(col_l)
    ref_val = get_cell_effective_value(ws, r, c)
    if isinstance(ref_val, (int, float)) and not isinstance(ref_val, bool):
        base_week = int(ref_val)
    else:
        base_week = _resolve_week_formula_value(ws, ref_val, depth + 1)
    if base_week is None:
        return None
    week = base_week + int(delta_s)
    return week if 1 <= week <= 53 else None


def _resolve_week_number(ws, footer_row: int) -> int | None:
    COL_C = 3
    value = get_cell_effective_value(ws, footer_row, COL_C)
    if value is None:
        return None
    if isinstance(value, (int, float)) and not isinstance(value, bool):
        v = int(value)
        return v if 1 <= v <= 53 else None
    raw = str(value).strip()
    if raw.lstrip("-").isdigit():
        v = int(raw)
        return v if 1 <= v <= 53 else None
    return _resolve_week_formula_value(ws, value)


def resolve_insert_top(sections: list[dict], week_num: int) -> int:
    """Mirror OeeAtadoresFileService::resolveInsertTop."""
    for sec in sections:
        w = sec.get("week")
        if w is not None and w > week_num:
            return int(sec["top"])
    if not sections:
        return 1
    last = sections[-1]
    return int(last["footer"]) + 1


def assert_single_iso_year(weeks: list[date]) -> int:
    years = {d.isocalendar()[0] for d in weeks}
    if len(years) != 1:
        raise RuntimeError(
            "El rango debe pertenecer al mismo año ISO para actualizar el archivo anual OEE."
        )
    return years.pop()


def resolve_workbook_year(wb) -> int | None:
    import re  # noqa: PLC0415

    for name in wb.sheetnames:
        if re.match(r"^ATADORES\s+\d{4}$", name.strip()):
            m = re.search(r"(\d{4})$", name.strip())
            if m:
                return int(m.group(1))
    return None


def normalize_detalle_footer_weeks(ws, sections: list[dict]) -> None:
    """Mirror normalizeDetalleFooterWeeks — force numeric week in column C at each footer."""
    for sec in sections:
        w = sec.get("week")
        if w is None:
            continue
        ws.cell(sec["footer"], 3).value = int(w)


def resolve_detalle_visual_week_anchor(ws, section: dict) -> int | None:
    """Mirror resolveDetalleVisualWeekAnchor."""
    top = int(section.get("top", 0))
    footer = int(section.get("footer", 0))
    if top < 1 or footer < top:
        return None

    candidates: list[int] = []
    for m in list(ws.merged_cells.ranges):
        if m.min_col != 1 or m.max_col != 1:
            continue
        row_start, row_end = m.min_row, m.max_row
        if row_start < top or row_end > footer:
            continue
        val = normalize_label(get_cell_effective_value(ws, row_start, 1))
        if val == "" or val == "SEMANA":
            continue
        candidates.append(row_start)

    if candidates:
        return max(candidates)

    for row in range(footer, top - 1, -1):
        val = normalize_label(get_cell_effective_value(ws, row, 1))
        if val == "" or val == "SEMANA":
            continue
        return row

    return None


def normalize_detalle_visual_weeks(ws, sections: list[dict]) -> None:
    """Mirror normalizeDetalleVisualWeeks."""
    for sec in sections:
        w = sec.get("week")
        if w is None:
            continue
        anchor = resolve_detalle_visual_week_anchor(ws, sec)
        if anchor is not None:
            ws.cell(anchor, 1).value = int(w)


# ---------------------------------------------------------------------------
# Cell manipulation helpers
# ---------------------------------------------------------------------------

def to_excel_time(time_str: str) -> float:
    """Convert HH:MM:SS to Excel fractional day."""
    parts = time_str.split(":")
    h, m, s = int(parts[0]), int(parts[1]), int(parts[2]) if len(parts) > 2 else 0
    return (h * 3600 + m * 60 + s) / 86400


def to_excel_date(d: date) -> int:
    """Convert Python date to Excel serial date integer."""
    return (d - date(1899, 12, 30)).days


# ---------------------------------------------------------------------------
# Merges (mirror OeeAtadoresFileService::unmergeRowsInRange)
# ---------------------------------------------------------------------------

def _safe_unmerge(ws, merge_range) -> None:
    """Unmerge a range, tolerating openpyxl's KeyError on orphan MergedCell objects."""
    try:
        ws.unmerge_cells(str(merge_range))
    except KeyError:
        pass


def _cleanup_orphan_merged_cells(ws, start_row: int, end_row: int) -> None:
    """
    After unmerging, openpyxl may leave orphan MergedCell objects in _cells
    because the cleanup loop fails with KeyError for cells never accessed.
    These orphans get shifted by insert_rows into neighboring sections,
    corrupting their structure. Remove them explicitly.
    """
    from openpyxl.cell.cell import MergedCell  # noqa: PLC0415

    orphans = [
        (r, c) for (r, c), cell in ws._cells.items()
        if start_row <= r <= end_row and isinstance(cell, MergedCell)
    ]
    for key in orphans:
        del ws._cells[key]


def unmerge_rows_in_range(ws, start_row: int, end_row: int) -> None:
    """
    Remove every merged range that intersects [start_row, end_row],
    then clean up orphan MergedCell objects left in _cells.
    """
    if start_row > end_row:
        return
    to_remove = [
        m for m in list(ws.merged_cells.ranges)
        if m.min_row <= end_row and m.max_row >= start_row
    ]
    for m in to_remove:
        _safe_unmerge(ws, m)
    _cleanup_orphan_merged_cells(ws, start_row, end_row)


# ---------------------------------------------------------------------------
# Section clearing (values only, preserve styles)
# ---------------------------------------------------------------------------

def clear_section_values(ws, section: dict) -> None:
    """
    Clear values in the section. Unmerge first (like PHP unmergeDynamicRanges)
    so we don't write over inconsistent merged cells.
    """
    top = section["top"]
    footer = section["footer"]
    unmerge_rows_in_range(ws, top, footer)
    for row in range(top, footer + 1):
        for col in range(1, 100):
            cell = ws.cell(row, col)
            if cell.value is not None:
                cell.value = None


# ---------------------------------------------------------------------------
# Section resize
# ---------------------------------------------------------------------------

def resize_section(ws, section: dict, desired_rows: int) -> dict:
    """
    Insert or delete rows so section has exactly desired_rows.
    Mirror of PHP resizeDetalleSection: unmerge the section range first,
    then insert/delete. The orphan MergedCell cleanup in unmerge_rows_in_range
    prevents contamination of sections below.
    """
    current_rows = section["rows"]
    delta = desired_rows - current_rows
    if delta == 0:
        return section

    top = section["top"]
    footer = section["footer"]

    unmerge_rows_in_range(ws, top, footer)

    if delta > 0:
        ws.insert_rows(footer, delta)
    else:
        delete_start = footer + delta
        ws.delete_rows(delete_start, -delta)

    new_footer = footer + delta
    return {
        "top": section["top"],
        "footer": new_footer,
        "rows": desired_rows,
        "week": section["week"],
    }


# ---------------------------------------------------------------------------
# Style snapshot / restore (mirrors PHP copySectionRange + duplicateStyle)
# ---------------------------------------------------------------------------

def snapshot_section_styles(ws, section: dict) -> dict:
    """
    Capture a snapshot of every cell's style + row heights within a section.
    Returns a dict keyed by relative row offset (0-based from section top).
    Each entry: {"height": float|None, "cells": {col_idx: style_dict}}.
    style_dict stores openpyxl style objects that can be re-assigned directly.
    """
    top = section["top"]
    footer = section["footer"]
    snapshot: dict[int, dict] = {}

    for row in range(top, footer + 1):
        rel = row - top
        rd = ws.row_dimensions.get(row)
        height = rd.height if rd else None

        cells: dict[int, dict] = {}
        for col in range(1, MAX_COLUMN_INDEX + 12):  # up to CU (col 99)
            cell = ws.cell(row, col)
            cells[col] = {
                "font": copy(cell.font),
                "fill": copy(cell.fill),
                "border": copy(cell.border),
                "alignment": copy(cell.alignment),
                "protection": copy(cell.protection),
                "number_format": cell.number_format,
            }

        snapshot[rel] = {"height": height, "cells": cells}

    return snapshot


def _apply_row_style(ws, row: int, style_row: dict) -> None:
    """Apply a captured style_row dict to an absolute worksheet row."""
    h = style_row.get("height")
    if h is not None:
        ws.row_dimensions[row].height = h

    for col, st in style_row.get("cells", {}).items():
        cell = ws.cell(row, col)
        cell.font = st["font"]
        cell.fill = st["fill"]
        cell.border = st["border"]
        cell.alignment = st["alignment"]
        cell.protection = st["protection"]
        cell.number_format = st["number_format"]


def apply_section_styles(ws, section: dict, snapshot: dict, layout: dict) -> None:
    """
    Re-apply captured styles to a section that may have been resized.
    - Rows that existed before resize: re-apply from their original relative offset.
    - New rows (inserted): derive style from prototype rows within the snapshot.
    - Footer row: always takes style from the last row of the original snapshot.
    """
    top = section["top"]
    footer = section["footer"]
    new_total = footer - top + 1
    snap_total = len(snapshot)

    if snap_total == 0:
        return

    # Footer is always last row in original snapshot
    footer_snap_rel = snap_total - 1

    # Build a map: absolute row → relative snapshot row to use as style source
    row_style_map: dict[int, int] = {}

    # Header rows (rows before detail_start_row): map 1:1
    detail_start = layout.get("detail_start_row", top + 3)
    header_count = detail_start - top
    for i in range(min(header_count, snap_total - 1)):
        row_style_map[top + i] = i

    # Footer row: always from original footer
    row_style_map[footer] = footer_snap_rel

    # Capacitacion block (just before footer)
    cap = layout.get("capacitacion", {})
    cap_start = cap.get("row_start", footer - CAPACITACION_HEIGHT)
    cap_end = cap.get("row_end", footer - 1)
    # Original cap started at relative offset snap_total - 1 - CAPACITACION_HEIGHT
    orig_cap_rel_start = footer_snap_rel - CAPACITACION_HEIGHT
    for row in range(cap_start, min(cap_end + 1, footer)):
        cap_offset = row - cap_start
        snap_rel = orig_cap_rel_start + cap_offset
        if 0 <= snap_rel < snap_total:
            row_style_map[row] = snap_rel

    # Turn blocks: use prototype rows from snapshot
    for turn in layout.get("turns", {}).values():
        for block in turn.get("blocks", []):
            proto = block.get("prototype", [])
            if len(proto) < 2:
                continue
            # proto = [abs_proto_start, abs_proto_end] computed against section_top
            proto_start_rel = proto[0] - top
            proto_end_rel = proto[1] - top

            rs = block["row_start"]
            re_ = block["row_end"]
            proto_height = proto_end_rel - proto_start_rel + 1

            for row in range(rs, re_ + 1):
                if row in row_style_map:
                    continue
                block_offset = row - rs
                # Cycle through prototype rows for blocks larger than prototype
                snap_rel = proto_start_rel + (block_offset % proto_height)
                if 0 <= snap_rel < snap_total:
                    row_style_map[row] = snap_rel

    # Apply styles
    for row in range(top, footer + 1):
        snap_rel = row_style_map.get(row)
        if snap_rel is not None and snap_rel in snapshot:
            _apply_row_style(ws, row, snapshot[snap_rel])
        else:
            # Fallback: use nearest known row from snapshot (middle detail row)
            mid = min(snap_total - 2, max(0, header_count))
            if mid in snapshot:
                _apply_row_style(ws, row, snapshot[mid])


# ---------------------------------------------------------------------------
# Section data writer
# ---------------------------------------------------------------------------

def write_section_data(ws, section: dict, layout: dict, week_monday: date, name_map: dict) -> None:
    """Write all cell values and formulas into the section."""
    _write_day_headers(ws, section["top"], week_monday)
    _write_col_a(ws, layout, section["top"], layout.get("week_number", week_monday.isocalendar()[1]))
    _write_turn_labels(ws, layout)
    _write_atador_blocks(ws, layout)
    _write_footer(ws, layout)
    _write_ck_cu_formulas(ws, layout, section["top"], name_map)


def _write_day_headers(ws, section_top: int, week_monday: date) -> None:
    """Row section_top+1: Excel serial dates for 7 days."""
    # header cells are like "H2" meaning col H, relative row 2 from section top
    from openpyxl.utils import column_index_from_string as ci  # noqa: PLC0415

    for day_idx, day in DAY_DEFINITIONS.items():
        hdr = day["header"]
        # Parse column letter and relative row from header like "H2", "AF2"
        m = re.match(r"^([A-Z]+)(\d+)$", hdr)
        if not m:
            continue
        col = ci(m.group(1))
        rel_row = int(m.group(2))
        abs_row = section_top + rel_row - 1
        d = week_monday + timedelta(days=day_idx)
        ws.cell(abs_row, col).value = to_excel_date(d)


def _write_col_a(ws, layout: dict, section_top: int, week_number: int) -> None:
    """Col A: 'SEMANA' merged over most rows, week_number merged over last Turn3 block."""
    from openpyxl.utils import get_column_letter  # noqa: PLC0415

    turns = layout["turns"]
    turn3_blocks = turns[3]["blocks"]
    last_t3_block = turn3_blocks[-1]

    header_label_row = section_top + 2 - 1  # relative row 2

    # Unmerge existing A merges in the section range first
    footer_row = layout["footer_row"]
    _unmerge_col_range(ws, 1, 1, section_top, footer_row)

    label_end_row = last_t3_block["row_start"] - 1
    if label_end_row >= header_label_row:
        ws.merge_cells(
            start_row=header_label_row, start_column=1,
            end_row=label_end_row,      end_column=1,
        )
        ws.cell(header_label_row, 1).value = "SEMANA"

    ws.merge_cells(
        start_row=last_t3_block["row_start"], start_column=1,
        end_row=last_t3_block["row_end"],     end_column=1,
    )
    ws.cell(last_t3_block["row_start"], 1).value = week_number


def _unmerge_col_range(ws, col_start: int, col_end: int, row_start: int, row_end: int) -> None:
    """Remove any existing merge ranges that overlap the given area."""
    to_remove = [
        m for m in list(ws.merged_cells.ranges)
        if (m.min_row <= row_end and m.max_row >= row_start
            and m.min_col <= col_end and m.max_col >= col_start)
    ]
    for m in to_remove:
        _safe_unmerge(ws, m)
    _cleanup_orphan_merged_cells(ws, row_start, row_end)


def _write_turn_labels(ws, layout: dict) -> None:
    """Col B: turn labels merged over full turn rows."""
    for turno, turn in layout["turns"].items():
        # Unmerge first
        _unmerge_col_range(ws, 2, 2, turn["row_start"], turn["row_end"])
        ws.merge_cells(
            start_row=turn["row_start"], start_column=2,
            end_row=turn["row_end"],     end_column=2,
        )
        ws.cell(turn["row_start"], 2).value = turn["label"]

    cap = layout["capacitacion"]
    _unmerge_col_range(ws, 2, 3, cap["row_start"], cap["row_end"])
    ws.merge_cells(
        start_row=cap["row_start"], start_column=2,
        end_row=cap["row_end"],     end_column=2,
    )
    ws.merge_cells(
        start_row=cap["row_start"], start_column=3,
        end_row=cap["row_end"],     end_column=3,
    )
    ws.cell(cap["row_start"], 2).value = cap["label"]


def _write_atador_blocks(ws, layout: dict) -> None:
    """Write per-block data: col C key, day values, formulas, merges."""
    from openpyxl.utils import column_index_from_string as ci  # noqa: PLC0415

    for turn in layout["turns"].values():
        for block in turn["blocks"]:
            rs = block["row_start"]
            re_ = block["row_end"]
            items_by_day = block["items_by_day"]
            atador_key = block["atador_key"]

            # Col C: atador key
            _unmerge_col_range(ws, 3, 3, rs, re_)
            ws.merge_cells(start_row=rs, start_column=3, end_row=re_, end_column=3)
            ws.cell(rs, 3).value = atador_key

            # Write records row by row
            rows_data: list[dict[int, dict]] = [{} for _ in range(re_ - rs + 1)]
            for day_idx, day_items in enumerate(items_by_day):
                for row_offset, item in enumerate(day_items):
                    rows_data[row_offset][day_idx] = item

            for row_offset, day_map in enumerate(rows_data):
                row = rs + row_offset
                for day_idx, item in day_map.items():
                    d = DAY_DEFINITIONS[day_idx]
                    c_start = ci(d["start"])
                    c_end   = ci(d["end"])
                    c_atado = ci(d["atado"])
                    c_telar = ci(d["telar"])
                    c_calif = ci(d["calif"])
                    c_5s    = ci(d["five_s"])
                    c_merma = ci(d["merma"])

                    if item["hora_inicio"]:
                        ws.cell(row, c_start).value = to_excel_time(item["hora_inicio"])
                    if item["hora_fin"]:
                        ws.cell(row, c_end).value = to_excel_time(item["hora_fin"])
                    ws.cell(row, c_atado).value = item["atado"]
                    ws.cell(row, c_telar).value = item["telar"]
                    if item["calif"] is not None:
                        ws.cell(row, c_calif).value = item["calif"]
                    if item["five_s"] is not None:
                        ws.cell(row, c_5s).value = item["five_s"]
                    if item["merma"] is not None:
                        ws.cell(row, c_merma).value = item["merma"]

            # Duration + avg formulas
            _write_block_formulas(ws, block)

            # Merge CJ column over block
            cj = ci("CJ")
            _unmerge_col_range(ws, cj, cj, rs, re_)
            ws.merge_cells(start_row=rs, start_column=cj, end_row=re_, end_column=cj)

            # Merge avg columns
            for day in DAY_DEFINITIONS.values():
                for col_letter in [day["avg_time"], day["avg_calif"], day["avg_merma"]]:
                    c = ci(col_letter)
                    _unmerge_col_range(ws, c, c, rs, re_)
                    ws.merge_cells(start_row=rs, start_column=c, end_row=re_, end_column=c)


def _write_block_formulas(ws, block: dict) -> None:
    """Duration per row + avg per block + CJ count."""
    from openpyxl.utils import column_index_from_string as ci  # noqa: PLC0415

    rs = block["row_start"]
    re_ = block["row_end"]

    for day in DAY_DEFINITIONS.values():
        sc = day["start"]
        ec = day["end"]
        dc = day["duration"]
        tc = day["telar"]
        avgt = day["avg_time"]
        ac = day["avg_calif"]
        fs = day["five_s"]
        avc = day["avg_calif"]
        mc = day["merma"]
        avm = day["avg_merma"]

        for row in range(rs, re_ + 1):
            ws.cell(row, ci(dc)).value = (
                f"=IF(OR({ec}{row}=\"\",{sc}{row}=\"\"),\"\",{ec}{row}-{sc}{row})"
            )

        ws.cell(rs, ci(avgt)).value = (
            f"=IF(COUNT({tc}{rs}:{tc}{re_})=0,\"\","
            f"SUM({dc}{rs}:{dc}{re_})/COUNT({tc}{rs}:{tc}{re_}))"
        )
        ws.cell(rs, ci(avc)).value = (
            f"=IF(COUNTA({ac}{rs}:{fs}{re_})=0,\"\","
            f"AVERAGE({ac}{rs}:{fs}{re_}))"
        )
        ws.cell(rs, ci(avm)).value = (
            f"=IF(COUNT({mc}{rs}:{mc}{re_})=0,\"\","
            f"AVERAGE({mc}{rs}:{mc}{re_}))"
        )

    # CJ: total count across all days
    count_parts = ",".join(
        f"{d['footer_count']}{rs}:{d['footer_count']}{re_}"
        for d in DAY_DEFINITIONS.values()
    )
    ws.cell(rs, 88).value = f"=COUNT({count_parts})"

    # CI min row (row_start + 1)
    if rs + 1 <= re_:
        min_parts = ",".join(
            d["avg_time"] + str(rs) for d in DAY_DEFINITIONS.values()
        )
        ws.cell(rs + 1, 87).value = f"=MIN({min_parts})"  # CI=87


def _write_footer(ws, layout: dict) -> None:
    """Write footer row: B=SEMANA, C=week_num, CJ=ATADOS, count formulas."""
    from openpyxl.utils import column_index_from_string as ci  # noqa: PLC0415

    fr = layout["footer_row"]
    ds = layout["detail_start_row"]
    ld = layout["last_detail_row"]
    wn = layout.get("week_number", "")

    ws.cell(fr, 2).value = "SEMANA"
    ws.cell(fr, 3).value = wn
    ws.cell(fr, ci("CJ")).value = "ATADOS"

    footer_cols = []
    for day in DAY_DEFINITIONS.values():
        fc = day["footer_count"]
        formula = f"=COUNT({fc}{ds}:{fc}{ld})"
        ws.cell(fr, ci(fc)).value = formula
        footer_cols.append(f"{fc}{fr}")

    ws.cell(fr, 87).value = "=" + "+".join(footer_cols)  # CI=87


def _write_ck_cu_formulas(ws, layout: dict, section_top: int, name_map: dict) -> None:
    """
    Mirror of PHP writeCkCuFormulas.
    Summary rows start at section_top + 3 (1-indexed = row 4 relative to section start).
    """
    summary_row = section_top + 3
    actual_summary_rows = []

    for turn in layout["turns"].values():
        for block in turn["blocks"]:
            row = summary_row
            summary_row += 1
            key = str(block["atador_key"] or "").strip()
            bs = block["row_start"]

            if not key:
                # Clear CK..CU for this row
                for col in range(89, 100):  # CK=89..CU=99
                    ws.cell(row, col).value = None
                continue

            actual_summary_rows.append(row)
            name = name_map.get(key, key)

            ws.cell(row, 89).value = f"=C{bs}"                          # CK
            ws.cell(row, 90).value = name                                # CL
            ws.cell(row, 91).value = "=IFERROR(AVERAGE(" + ",".join(f"{c}{bs}" for c in AVG_CALIF_COLS) + "),\"\")"  # CM
            ws.cell(row, 92).value = "=IFERROR(AVERAGE(" + ",".join(f"{c}{bs}" for c in AVG_TIME_COLS)  + "),\"\")"  # CN
            ws.cell(row, 97).value = f"=IFERROR(CM{row}*100/10,\"\")"   # CS
            ws.cell(row, 98).value = "=IFERROR(AVERAGE(" + ",".join(f"{c}{bs}" for c in AVG_MERMA_COLS) + "),\"\")"  # CT
            ws.cell(row, 99).value = f"=CL{row}"                        # CU

    if not actual_summary_rows:
        return

    cn_refs = ",".join(f"CN{r}" for r in actual_summary_rows)
    for row in actual_summary_rows:
        ws.cell(row, 93).value = f"=IFERROR(MIN({cn_refs}),\"\")"       # CO
        ws.cell(row, 94).value = f"=IFERROR(CN{row}-CO{row},\"\")"      # CP
        ws.cell(row, 95).value = f"=IFERROR(CO{row}-CP{row},\"\")"      # CQ
        ws.cell(row, 96).value = f"=IFERROR(CQ{row}*100/CO{row},\"\")"  # CR


# ---------------------------------------------------------------------------
# Main export orchestrator
# ---------------------------------------------------------------------------

def get_weeks_in_range(week_start: date, week_end: date) -> list[date]:
    """Return list of Monday dates from week_start to week_end inclusive."""
    weeks = []
    d = week_start
    while d <= week_end:
        weeks.append(d)
        d += timedelta(days=7)
    return weeks


def run(args: argparse.Namespace) -> None:
    from openpyxl import load_workbook  # noqa: PLC0415

    _set_run_phase("inicio")

    t0 = time.time()
    status_file = args.status_file
    file_path   = args.file_path

    write_status(status_file, "procesando", "Iniciando...")

    week_start_date = date.fromisoformat(args.week_start)
    week_end_date   = date.fromisoformat(args.week_end)
    weeks = get_weeks_in_range(week_start_date, week_end_date)
    iso_year = assert_single_iso_year(weeks)

    # 1. Copy to local temp
    _set_run_phase("copiar_entrada")
    tmp_dir = tempfile.gettempdir()
    local_temp = os.path.join(tmp_dir, f"oee_py_{args.token}.xlsx")
    shutil.copy2(file_path, local_temp)
    write_status(status_file, "procesando", "Archivo copiado localmente", time.time() - t0)

    # 2. Query DB
    _set_run_phase("conectar_bd")
    conn = get_db_connection(args)
    _set_run_phase("consultar_bd")
    raw_rows = query_records(conn, args.week_start, args.week_end)
    conn.close()

    grouped = group_records_by_week(raw_rows)

    write_status(status_file, "procesando", "Datos cargados, actualizando Excel...", time.time() - t0)

    # 3. Load workbook
    _set_run_phase("cargar_excel")
    wb = load_workbook(local_temp, keep_vba=False, data_only=False)
    ws = wb["DETALLE"]

    wby = resolve_workbook_year(wb)
    if wby is not None and wby != iso_year:
        raise RuntimeError(
            f"El archivo OEE corresponde al año {wby}; no se pueden mezclar semanas del año ISO {iso_year}."
        )

    parsed0 = parse_detalle_sections(ws)
    normalize_detalle_footer_weeks(ws, parsed0["sections"])

    # Capture a style template from the first existing section (used for brand-new inserts)
    _template_snap: dict | None = None
    if parsed0["sections"]:
        _template_snap = snapshot_section_styles(ws, parsed0["sections"][0])

    # 4. Process each week
    _set_run_phase("procesar_semanas")
    for week_monday in weeks:
        week_key = str(week_monday)
        week_num = week_monday.isocalendar()[1]

        week_raw = grouped.get(week_key, [])
        week_records = prepare_records(week_raw, week_monday)

        # Build name_map
        name_map: dict[str, str] = {}
        for rec in week_records:
            k = rec["atador_key"]
            if k and k not in name_map:
                for r in week_raw:
                    if resolve_atador_key(r) == k:
                        nm = str(r.get("NomTejedor") or "").strip()
                        if nm:
                            name_map[k] = nm
                        break

        parsed = parse_detalle_sections(ws)
        existing = parsed["map"].get(week_num)

        if existing:
            snap = snapshot_section_styles(ws, existing)

            layout = build_layout(week_records, section_top_row=existing["top"])
            layout["week_number"] = week_num
            desired_total = layout["footer_row"] - existing["top"] + 1
            existing = resize_section(ws, existing, desired_total)
            layout = build_layout(week_records, section_top_row=existing["top"])
            layout["week_number"] = week_num

            clear_section_values(ws, existing)
            apply_section_styles(ws, existing, snap, layout)
            write_section_data(ws, existing, layout, week_monday, name_map)
        else:
            parsed2 = parse_detalle_sections(ws)
            insert_top = resolve_insert_top(parsed2["sections"], week_num)

            layout_tmp = build_layout(week_records, section_top_row=insert_top)
            layout_tmp["week_number"] = week_num
            section_rows = layout_tmp["footer_row"] - insert_top + 1

            ws.insert_rows(insert_top, section_rows)

            new_section = {
                "top": insert_top,
                "footer": insert_top + section_rows - 1,
                "rows": section_rows,
                "week": week_num,
            }
            layout = build_layout(week_records, section_top_row=insert_top)
            layout["week_number"] = week_num

            if _template_snap:
                apply_section_styles(ws, new_section, _template_snap, layout)

            write_section_data(ws, new_section, layout, week_monday, name_map)

        write_status(
            status_file,
            "procesando",
            f"Semana {week_num} actualizada ({round(time.time() - t0)}s)",
            time.time() - t0,
        )

    parsed_final = parse_detalle_sections(ws)
    normalize_detalle_footer_weeks(ws, parsed_final["sections"])
    normalize_detalle_visual_weeks(ws, parsed_final["sections"])

    # 5. Save to temp output
    _set_run_phase("guardar_excel")
    out_temp = os.path.join(tmp_dir, f"oee_py_out_{args.token}.xlsx")
    write_status(status_file, "procesando", "Guardando archivo...", time.time() - t0)
    wb.save(out_temp)
    wb.close()

    # 6. Copy back to network (retry on file-lock errors common on shares)
    _set_run_phase("copiar_salida")
    max_copy_retries = 5
    for attempt in range(1, max_copy_retries + 1):
        try:
            shutil.copy2(out_temp, file_path)
            break
        except PermissionError:
            if attempt == max_copy_retries:
                raise
            time.sleep(2 * attempt)

    # Cleanup temps
    for f in [local_temp, out_temp]:
        try:
            os.remove(f)
        except OSError:
            pass

    elapsed = round(time.time() - t0, 1)
    write_status(status_file, "completado", f"Completado en {elapsed}s", elapsed)


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main() -> None:
    args = parse_args()

    # Optional log file
    if getattr(args, "log_file", None):
        import logging  # noqa: PLC0415
        logging.basicConfig(
            filename=args.log_file,
            level=logging.INFO,
            format="%(asctime)s %(levelname)s %(message)s",
        )

    t0 = time.time()
    try:
        run(args)
    except BaseException as exc:  # noqa: BLE001
        elapsed = round(time.time() - t0, 1)
        tb = traceback.format_exc()
        print(tb, file=sys.stderr, end="")
        msg = (
            f"fase={_RUN_PHASE} | {_format_exception_detail(exc)}"
        )
        if len(msg) < 100 and tb:
            msg = f"{msg}\n{tb}"[:4000]
        msg = msg[:4000]
        write_status(args.status_file, "error", msg, elapsed)
        print(f"ERROR: {msg}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
