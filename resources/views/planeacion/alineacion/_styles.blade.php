<style>
    #table-container {
        position: relative;
        overflow-y: auto;
        overflow-x: auto;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        flex: 1;
        min-height: 0;
    }
    #table-container::-webkit-scrollbar { width: 14px; height: 14px; }
    #table-container::-webkit-scrollbar-track { background: #e5e7eb; border-radius: 7px; }
    #table-container::-webkit-scrollbar-thumb { background: #6b7280; border-radius: 7px; border: 2px solid #e5e7eb; }
    #table-container::-webkit-scrollbar-thumb:hover { background: #4b5563; }
    #table-container { scrollbar-width: auto; scrollbar-color: #6b7280 #e5e7eb; }

    #mainTable {
        position: relative;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-width: max-content;
        table-layout: auto;
    }
    #mainTable thead.alineacion-thead,
    #mainTable thead.alineacion-thead th {
        border-right: none !important;
        border-left: none !important;
    }
    /* Encabezados fijos: todo el thead como un solo bloque al hacer scroll vertical */
    #mainTable thead {
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 1000 !important;
        background-color: #2563eb !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        border-bottom: 2px solid #1d4ed8 !important;
    }
    #mainTable thead th {
        position: relative;
        background-color: #2563eb !important;
        white-space: nowrap;
    }
    #mainTable tbody td {
        border-right: 1px solid #e5e7eb;
        white-space: nowrap;
    }
    #mainTable tbody td:last-child { border-right: none; }
    .container-fluid { position: relative; height: 100%; display: flex; flex-direction: column; }
    .bg-white.rounded-lg.shadow-sm { display: flex; flex-direction: column; height: 100%; min-height: 0; }

    /* Menú contextual en encabezados */
    .alineacion-header-context th { cursor: context-menu; }

    /* Iconos en encabezados (filtro activo / columna fijada) */
    .alineacion-header-icons { flex-shrink: 0; display: inline-flex; align-items: center; gap: 2px; }
    .alineacion-header-icon {
        cursor: pointer;
        border: none;
        background: transparent;
        padding: 3px 4px;
        min-width: 22px;
        min-height: 22px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        color: inherit;
        font-size: 11px;
    }
    .alineacion-header-icon:hover { opacity: 0.9; background: rgba(255,255,255,0.25); }
    .alineacion-header-icon .fa-filter { color: #fcd34d !important; }
    .alineacion-header-icon .fa-thumbtack { color: #fff !important; }

    /* Columnas fijadas (pin) */
    #mainTable thead th.alineacion-pinned,
    #mainTable tbody td.alineacion-pinned {
        position: sticky !important;
        z-index: 5;
        background-color: #1d4ed8 !important;
        color: #fff !important;
    }
    #mainTable tbody td.alineacion-pinned { z-index: 1; }
    #mainTable thead th.alineacion-pinned { z-index: 11 !important; }

    /* Filas seleccionadas (bg-blue-500 text-white) */
    #mainTable tbody tr.alineacion-row-selected,
    #mainTable tbody tr.alineacion-row-selected td { background-color: #3b82f6 !important; color: #fff !important; }
    #mainTable tbody tr.alineacion-row-selected td.alineacion-pinned { background-color: #2563eb !important; color: #fff !important; }
    #mainTable tbody tr.alineacion-row-selected:hover,
    #mainTable tbody tr.alineacion-row-selected:hover td { background-color: #2563eb !important; }

    /* Filas con paro activo en ManFallasParos */
    #mainTable tbody tr.alineacion-row-alerta td { background-color: #fefce8 !important; color: #713f12 !important; }
    #mainTable tbody tr.alineacion-row-alerta:hover td { background-color: #fef9c3 !important; }
    #mainTable tbody tr.alineacion-row-alerta td.alineacion-pinned { background-color: #fef08a !important; }

    /* Fila con paro activo + seleccionada: amarillo más intenso */
    #mainTable tbody tr.alineacion-row-alerta-selected td { background-color: #fde047 !important; color: #713f12 !important; }
    #mainTable tbody tr.alineacion-row-alerta-selected:hover td { background-color: #facc15 !important; }
    #mainTable tbody tr.alineacion-row-alerta-selected td.alineacion-pinned { background-color: #eab308 !important; color: #713f12 !important; }

    /* Rango peso / muestra (tolerancia N): ancho mínimo para que min–max se lean al hacer scroll */
    #mainTable thead th[data-column="PesoMin"], #mainTable thead th[data-column="PesoMax"],
    #mainTable thead th[data-column="MuestraMin"], #mainTable thead th[data-column="MuestraMax"] {
        min-width: 4.25rem;
        text-align: center;
    }
    #mainTable tbody td[data-column="PesoMin"], #mainTable tbody td[data-column="PesoMax"],
    #mainTable tbody td[data-column="MuestraMin"], #mainTable tbody td[data-column="MuestraMax"] {
        min-width: 4.25rem;
        text-align: center;
    }
</style>
