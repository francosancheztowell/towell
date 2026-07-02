@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="Trazabilidad" />
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button type="button" id="btn-exportar"
                class="flex items-center gap-2 px-4 py-3 text-md font-bold text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
            <i class="fas fa-file-excel"></i>
            Exportar a Excel
        </button>
        <button type="button" id="btn-restablecer"
                class="flex items-center gap-2 px-4 py-3 text-md font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
            <i class="fas fa-rotate-left"></i>
            Restablecer
        </button>
    </div>
@endsection

@section('content')
    {{-- Forzar fondo gris claro en esta página (el layout aplica un gradiente azul a main/body) --}}
    <style>
        body,
        main.app-main {
            background: #f1f5f9 !important;
        }

        /* === Estilo de los selects (select2) en Trazabilidad === */
        /* Caja del select: redondeada, borde gris, altura cómoda */
        #form-filtros .select2-container--default .select2-selection--single {
            height: 34px;
            display: flex;
            align-items: center;
            border: 1px solid #cbd5e1;            /* slate-300 */
            border-radius: 0.6rem;                /* redondeado */
            background-color: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        #form-filtros .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 32px;
            padding-left: 0.7rem;
            padding-right: 1.6rem;
            color: #334155;                       /* slate-700 */
            font-size: 0.8125rem;
        }
        #form-filtros .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8;                       /* slate-400 */
        }
        #form-filtros .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 32px;
            right: 8px;
        }
        /* Botón "x" (limpiar): separarlo del borde, hacia la izquierda */
        #form-filtros .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 26px;
            padding: 0 4px;
            color: #94a3b8;       /* slate-400 */
            font-weight: 700;
            cursor: pointer;
        }
        #form-filtros .select2-container--default .select2-selection--single .select2-selection__clear:hover {
            color: #ef4444;       /* red-500 al pasar el mouse */
        }
        /* Foco / abierto: borde azul + anillo */
        #form-filtros .select2-container--default.select2-container--focus .select2-selection--single,
        #form-filtros .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #3b82f6;                /* blue-500 */
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .25);
            outline: none;
        }

        /* Dropdown (se inyecta en <body>; se le pone una clase propia) */
        .traza-select2-dd.select2-dropdown {
            border: 1px solid #3b82f6;
            border-radius: 0.6rem;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .12);
            margin-top: 4px;
        }
        .traza-select2-dd .select2-search__field {
            border: 1px solid #cbd5e1;
            border-radius: 0.45rem;
            padding: 0.35rem 0.5rem;
        }
        .traza-select2-dd .select2-results__option--highlighted[aria-selected] {
            background-color: #3b82f6;            /* opción resaltada azul */
        }
        .traza-select2-dd .select2-results__option[aria-selected="true"] {
            background-color: #dbeafe;            /* opción seleccionada azul pastel */
            color: #1e40af;
        }

        /* === Áreas expandibles (dropdown por artículo/color) === */
        /* Fila de área ABIERTA: resaltar la celda de etiqueta para que se note cuál
           está desplegada. Solo la primera columna (sticky), para no pisar el heatmap. */
        #resultado tr.area-fila.area-abierta > td:first-child {
            background-color: #bfdbfe !important;  /* blue-200 */
        }
        #resultado tr.area-fila.area-abierta:hover > td:first-child {
            background-color: #93c5fd !important;  /* blue-300 al pasar el mouse */
        }
        /* Indicar que la fila de área es clickeable */
        #resultado tr.area-fila > td:first-child { cursor: pointer; }

        /* === Tarjetas pestaña Producción === */
        .prod-card-v2 {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            overflow: hidden;
            min-height: 100%;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .prod-card-v2.prod-card--alerta {
            border-color: #fbbf24;
            box-shadow: 0 0 0 1px rgba(251, 191, 36, 0.35);
        }
        .prod-card-grupo {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0.35rem;
            border-radius: 1rem;
            border: 1px solid #fcd34d;
            background: rgba(255, 251, 235, 0.65);
            min-width: 0;
        }
        @media (min-width: 640px) {
            .prod-card-grupo {
                flex-direction: row;
                align-items: stretch;
            }
            .prod-card-grupo .prod-card-v2 {
                flex: 1 1 0;
                min-width: 0;
            }
        }
        .prod-card-grupo .prod-card--otro-telar {
            border-color: #fde68a;
        }
        .prod-card-v2__accent {
            width: 4px;
            flex-shrink: 0;
        }
        .prod-card-v2__accent--activo { background: #22c55e; }
        .prod-card-v2__accent--terminado { background: #94a3b8; }
        .prod-card-v2__accent--alerta { background: #f59e0b; }
        .prod-card-v2__accent--rollos { background: #3b82f6; }
        .prod-stat-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.6rem 0.7rem;
            min-width: 0;
        }
        .prod-stat-label {
            font-size: 0.6875rem;
            color: #94a3b8;
            font-weight: 500;
            line-height: 1.2;
        }
        .prod-stat-value {
            font-size: 1.125rem;
            font-weight: 700;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
            margin-top: 0.2rem;
        }
        .prod-badge {
            display: inline-flex;
            align-items: center;
            font-size: 0.625rem;
            font-weight: 600;
            border-radius: 0.375rem;
            padding: 0.2rem 0.45rem;
            line-height: 1.35;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .prod-badge--compartida {
            color: #6d28d9;
            background: #ede9fe;
            border-color: #c4b5fd;
        }
        .prod-badge--mes {
            color: #4338ca;
            background: #e0e7ff;
            border-color: #a5b4fc;
        }
        .prod-badge--fecha {
            color: #0f766e;
            background: #ccfbf1;
            border-color: #5eead4;
        }
        .prod-badge--articulo {
            color: #1e40af;
            background: #dbeafe;
            border-color: #93c5fd;
        }
        .prod-badge--color {
            color: #5b21b6;
            background: #ede9fe;
            border-color: #c4b5fd;
        }
        .prod-stat-value--sm {
            font-size: 0.9375rem;
            margin-top: 0.35rem;
        }
        .prod-stat-value--sm:first-of-type {
            margin-top: 0.2rem;
        }
        .prod-segment {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.2rem;
            gap: 0.125rem;
        }
        .prod-segment__btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.85rem;
            border-radius: 0.55rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, box-shadow 0.15s;
            white-space: nowrap;
        }
        .prod-segment__btn:hover {
            color: #334155;
            background: rgba(255, 255, 255, 0.55);
        }
        .prod-segment__btn.is-active {
            background: #ffffff;
            color: #1d4ed8;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }
        .prod-segment__count {
            font-size: 0.625rem;
            font-weight: 700;
            line-height: 1;
            background: #e2e8f0;
            color: #475569;
            padding: 0.15rem 0.4rem;
            border-radius: 9999px;
            min-width: 1.15rem;
            text-align: center;
        }
        .prod-segment__btn.is-active .prod-segment__count {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .prod-segment__btn--activo.is-active { color: #047857; }
        .prod-segment__btn--activo.is-active .prod-segment__count {
            background: #d1fae5;
            color: #047857;
        }
        .prod-segment__btn--terminado.is-active { color: #475569; }
        .prod-segment__btn--terminado.is-active .prod-segment__count {
            background: #e2e8f0;
            color: #334155;
        }

        .prod-rollos-maquina-card:hover {
            border-color: #93c5fd;
        }

        /* === Pestaña Flogs === */
        .flog-wrap {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .flog-grid-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 1024px) {
            .flog-grid-2 {
                grid-template-columns: 1fr 1fr;
            }
            .flog-grid-2--empaque {
                grid-template-columns: minmax(280px, 0.9fr) minmax(0, 1.1fr);
            }
        }
        .flog-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.875rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .flog-card__head {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.75rem 1.1rem;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        }
        .flog-card__icon {
            width: 2rem;
            height: 2rem;
            border-radius: 0.5rem;
            background: #dbeafe;
            color: #1d4ed8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .flog-card__title {
            font-size: 0.8125rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #1e3a8a;
            line-height: 1.25;
        }
        .flog-card__body {
            padding: 1rem 1.1rem 1.1rem;
        }
        .flog-fields {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.85rem 1.25rem;
        }
        @media (min-width: 1280px) {
            .flog-fields {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        .flog-campo {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            min-width: 0;
        }
        .flog-campo__label {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            line-height: 1.3;
        }
        .flog-campo__valor {
            font-size: 0.9375rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
            word-break: break-word;
        }
        .flog-campo__valor--accent {
            color: #1d4ed8;
        }
        .flog-campo__valor--badge {
            display: inline-block;
            align-self: flex-start;
            padding: 0.2rem 0.55rem;
            border-radius: 0.375rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #334155;
        }
        .flog-campo--wide {
            grid-column: 1 / -1;
        }
        @media (min-width: 768px) {
            .flog-campo--half {
                grid-column: span 2;
            }
        }
        .flog-etiq-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            align-items: stretch;
            width: 100%;
        }
        .flog-visual-layout {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        @media (min-width: 768px) {
            .flog-visual-layout {
                flex-direction: row;
                align-items: stretch;
                min-height: 480px;
            }
        }
        .flog-visual-meta {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            min-width: 0;
        }
        @media (min-width: 768px) {
            .flog-visual-meta {
                width: 240px;
                max-width: 28%;
                flex-shrink: 0;
            }
        }
        .flog-visual-meta .flog-campo__valor {
            font-size: 0.875rem;
        }
        .flog-meta-nota {
            margin-top: 0.15rem;
            padding: 0.6rem 0.7rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            line-height: 1.45;
            white-space: pre-line;
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
        }
        .flog-meta-nota__titulo {
            display: block;
            font-size: 0.625rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #b45309;
            margin-bottom: 0.35rem;
        }
        .flog-visual-gallery {
            flex: 1 1 0;
            min-width: 0;
            align-self: stretch;
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            align-items: stretch;
        }
        .flog-visual-gallery--solo {
            flex: 1 1 auto;
            width: 100%;
            min-height: min(520px, 62vh);
        }
        .flog-visual-gallery--solo .flog-visual-frame {
            flex: 1 1 100%;
            width: 100%;
            min-height: min(520px, 62vh);
        }
        .flog-visual-frame {
            flex: 1 1 calc(50% - 0.5rem);
            min-width: min(100%, 280px);
            min-height: 360px;
            display: flex;
            flex-direction: column;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            overflow: hidden;
            background: #fff;
            cursor: zoom-in;
            position: relative;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .flog-visual-frame:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.15);
        }
        .flog-visual-frame__img-wrap {
            flex: 1 1 auto;
            position: relative;
            width: 100%;
            min-height: 340px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        }
        .flog-visual-gallery--solo .flog-visual-frame__img-wrap {
            min-height: min(480px, 58vh);
        }
        .flog-visual-frame__img-wrap img {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            bottom: 0.75rem;
            left: 0.75rem;
            width: calc(100% - 1.5rem);
            height: calc(100% - 1.5rem);
            object-fit: contain;
            object-position: center center;
        }
        .flog-visual-frame__caption {
            flex-shrink: 0;
            padding: 0.6rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.35;
            border-top: 1px solid #e2e8f0;
            background: #fff;
        }
        .flog-visual-frame__zoom-hint {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 0.65rem;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease;
            background: linear-gradient(180deg, transparent 55%, rgba(15, 23, 42, 0.45) 100%);
        }
        .flog-visual-frame:hover .flog-visual-frame__zoom-hint {
            opacity: 1;
        }
        .flog-visual-frame__zoom-hint span {
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
            background: rgba(15, 23, 42, 0.75);
            padding: 0.3rem 0.65rem;
            border-radius: 9999px;
        }
        .flog-nota {
            border-radius: 0.625rem;
            padding: 0.85rem 1rem;
            font-size: 0.875rem;
            line-height: 1.5;
            white-space: pre-line;
        }
        .flog-nota__titulo {
            font-size: 0.6875rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.45rem;
            display: block;
        }
        .flog-nota--amber {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
        }
        .flog-nota--amber .flog-nota__titulo {
            color: #b45309;
        }
        .flog-nota--slate {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
        }
        .flog-nota--slate .flog-nota__titulo {
            color: #475569;
        }
        .flog-empty-img {
            font-size: 0.875rem;
            color: #94a3b8;
            text-align: center;
            padding: 2rem 1rem;
        }

        /* Modal visor imagen Flog — pantalla completa + zoom */
        #modal-flog-imagen {
            z-index: 9999;
        }
        #modal-flog-imagen .modal-flog-imagen__backdrop {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(4px);
        }
        #modal-flog-imagen .modal-flog-imagen__shell {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            width: 100vw;
            height: 100vh;
            pointer-events: auto;
        }
        #modal-flog-imagen .modal-flog-imagen__toolbar {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.65rem 1rem;
            background: rgba(15, 23, 42, 0.85);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        #modal-flog-imagen .modal-flog-imagen__titulo {
            font-size: 0.875rem;
            font-weight: 600;
            color: #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            flex: 1;
        }
        #modal-flog-imagen .modal-flog-imagen__tools {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-shrink: 0;
        }
        #modal-flog-imagen .modal-flog-imagen__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            min-width: 2.25rem;
            height: 2.25rem;
            padding: 0 0.55rem;
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            border: 1px solid rgba(255, 255, 255, 0.18);
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        #modal-flog-imagen .modal-flog-imagen__btn:hover {
            background: rgba(255, 255, 255, 0.22);
        }
        #modal-flog-imagen .modal-flog-imagen__btn--primary {
            background: rgba(37, 99, 235, 0.55);
            border-color: rgba(147, 197, 253, 0.45);
        }
        #modal-flog-imagen .modal-flog-imagen__btn--primary:hover {
            background: rgba(37, 99, 235, 0.75);
        }
        #modal-flog-imagen .modal-flog-imagen__zoom-label {
            min-width: 3.25rem;
            text-align: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #cbd5e1;
            font-variant-numeric: tabular-nums;
        }
        #modal-flog-imagen .modal-flog-imagen__stage {
            flex: 1 1 auto;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: grab;
            touch-action: none;
        }
        #modal-flog-imagen .modal-flog-imagen__stage.is-dragging {
            cursor: grabbing;
        }
        #modal-flog-imagen .modal-flog-imagen__viewport {
            transform-origin: center center;
            will-change: transform;
        }
        #modal-flog-imagen .modal-flog-imagen__viewport img {
            display: block;
            max-width: none;
            max-height: none;
            width: auto;
            height: auto;
            border-radius: 0.25rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
            user-select: none;
            -webkit-user-drag: none;
        }
        #modal-flog-imagen .modal-flog-imagen__hint {
            position: absolute;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            color: rgba(226, 232, 240, 0.85);
            background: rgba(15, 23, 42, 0.65);
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            pointer-events: none;
        }

        /* Modal rollos teñido: por encima del navbar y fondo oscuro */
        #modal-rollos-maquina {
            z-index: 9999;
        }
        #modal-rollos-maquina .modal-rollos-maquina__backdrop {
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(2px);
        }
        #modal-rollos-maquina .modal-rollos-maquina__panel {
            z-index: 1;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
        }
    </style>

    <div class="w-full min-h-full px-1.5 md:px-2 py-3" style="background:#f1f5f9;">

        {{-- Línea de filtros --}}
        <form method="GET" action="{{ route('trazabilidad.index') }}" id="form-filtros"
              class="bg-white border border-slate-200 rounded-2xl shadow-sm p-2.5 md:p-3 mb-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-3">
                <div>
                    <label for="filtro-flog" class="block text-xs font-semibold text-slate-500 mb-0.5">Flog</label>
                    <select name="flog" id="filtro-flog" class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos</option>
                        @foreach ($opcionesFlog as $opt)
                            <option value="{{ $opt }}" @selected(($filtros['flog'] ?? '') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtro-articulo" class="block text-xs font-semibold text-slate-500 mb-0.5">Artículo</label>
                    <select name="articulo" id="filtro-articulo" class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos</option>
                        @foreach ($opcionesArticulo as $opt)
                            <option value="{{ $opt['codigo'] }}" @selected(($filtros['articulo'] ?? '') === $opt['codigo'])>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtro-tamano" class="block text-xs font-semibold text-slate-500 mb-0.5">Tamaño</label>
                    <select name="tamano" id="filtro-tamano" class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos</option>
                        @foreach ($opcionesTamano as $opt)
                            <option value="{{ $opt }}" @selected(($filtros['tamano'] ?? '') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtro-color" class="block text-xs font-semibold text-slate-500 mb-0.5">
                        Color
                        <span class="font-normal text-slate-400">· solo rollos teñido</span>
                    </label>
                    <select name="color" id="filtro-color" class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos</option>
                        @foreach ($opcionesColor as $opt)
                            <option value="{{ $opt['codigo'] }}" @selected(($filtros['color'] ?? '') === $opt['codigo'])>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Switch de métrica + meses (misma fila) --}}
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-2">
                {{-- Grupo: Mostrar + switch --}}
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-slate-400">Mostrar:</span>
                    <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden">
                        <button type="button" data-metrica="cantidad"
                                class="btn-metrica px-4 py-1.5 text-xs font-bold transition-colors
                                       {{ ($metrica ?? 'cantidad') === 'cantidad' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                            <i class="fa-solid fa-boxes-stacked mr-1"></i> Cantidad
                        </button>
                        <button type="button" data-metrica="peso"
                                class="btn-metrica px-4 py-1.5 text-xs font-bold transition-colors border-l border-slate-200
                                       {{ ($metrica ?? 'cantidad') === 'peso' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                            <i class="fa-solid fa-weight-hanging mr-1"></i> Kilos
                        </button>
                    </div>
                </div>

                {{-- Resumen de conteos (artículo/tamaño/color; lo llena el JS) --}}
                <div id="resumen-conteos" class="flex flex-wrap items-center gap-2 {{ $hayFlog ? '' : 'hidden' }}"></div>

                {{-- Grupo: Meses --}}
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-slate-400">Meses:</span>
                    <span id="meses-badges" class="flex flex-wrap items-center gap-2"></span>
                </div>
            </div>

            <input type="hidden" name="mes" id="filtro-mes" value="{{ $filtros['mes'] ?? '' }}">
            {{-- Métrica activa (la controlan los botones de arriba) --}}
            <input type="hidden" name="metrica" id="filtro-metrica" value="{{ $metrica ?? 'cantidad' }}">
        </form>

        {{-- Contenedor que se actualiza vía AJAX (badges + matriz), sin recargar --}}
        <div id="resultado">
            @include('modulos.trazabilidad._resultado')
        </div>

        {{-- Modal detalle rollos por máquina (se mueve a body vía JS) --}}
        <div id="modal-rollos-maquina" class="hidden fixed inset-0 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="modal-rollos-maquina-titulo">
            <div class="modal-rollos-maquina__backdrop absolute inset-0" data-modal-rollos-close></div>
            <div class="modal-rollos-maquina__panel relative bg-white rounded-2xl w-full max-w-4xl max-h-[85vh] flex flex-col overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200 shrink-0">
                    <h4 id="modal-rollos-maquina-titulo" class="text-lg font-bold text-slate-800 truncate"></h4>
                    <button type="button" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg transition-colors" data-modal-rollos-close aria-label="Cerrar">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
                <div class="overflow-auto flex-1 p-4">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                <th class="px-3 py-2 border-b border-slate-200">Orden</th>
                                <th class="px-3 py-2 border-b border-slate-200">Artículo</th>
                                <th class="px-3 py-2 border-b border-slate-200">Color</th>
                                <th class="px-3 py-2 border-b border-slate-200 text-right">Pzas</th>
                                <th class="px-3 py-2 border-b border-slate-200 text-right">Kg</th>
                            </tr>
                        </thead>
                        <tbody id="modal-rollos-maquina-body" class="text-slate-700"></tbody>
                        <tfoot>
                            <tr class="bg-blue-50 font-bold text-slate-800">
                                <td colspan="3" class="px-3 py-2 border-t border-slate-200">Total</td>
                                <td id="modal-rollos-total-pzas" class="px-3 py-2 border-t border-slate-200 text-right tabular-nums"></td>
                                <td id="modal-rollos-total-kg" class="px-3 py-2 border-t border-slate-200 text-right tabular-nums"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Modal visor imagen Flog: pantalla completa, zoom y descarga --}}
        <div id="modal-flog-imagen" class="hidden fixed inset-0" role="dialog" aria-modal="true" aria-labelledby="modal-flog-imagen-titulo">
            <div class="modal-flog-imagen__backdrop absolute inset-0" data-modal-flog-close></div>
            <div class="modal-flog-imagen__shell">
                <header class="modal-flog-imagen__toolbar">
                    <p id="modal-flog-imagen-titulo" class="modal-flog-imagen__titulo"></p>
                    <div class="modal-flog-imagen__tools">
                        <button type="button" class="modal-flog-imagen__btn" data-flog-zoom-out title="Alejar" aria-label="Alejar">
                            <i class="fa-solid fa-magnifying-glass-minus"></i>
                        </button>
                        <span class="modal-flog-imagen__zoom-label" data-flog-zoom-label>100%</span>
                        <button type="button" class="modal-flog-imagen__btn" data-flog-zoom-in title="Acercar" aria-label="Acercar">
                            <i class="fa-solid fa-magnifying-glass-plus"></i>
                        </button>
                        <button type="button" class="modal-flog-imagen__btn" data-flog-zoom-reset title="Ajustar a pantalla" aria-label="Ajustar a pantalla">
                            <i class="fa-solid fa-compress"></i>
                        </button>
                        <button type="button" class="modal-flog-imagen__btn modal-flog-imagen__btn--primary" data-flog-download title="Descargar imagen" aria-label="Descargar imagen">
                            <i class="fa-solid fa-download"></i>
                            <span class="hidden sm:inline">Descargar</span>
                        </button>
                        <button type="button" class="modal-flog-imagen__btn" data-modal-flog-close title="Cerrar" aria-label="Cerrar">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </header>
                <div class="modal-flog-imagen__stage" data-flog-stage>
                    <div class="modal-flog-imagen__viewport" data-flog-viewport>
                        <img src="" alt="" data-modal-flog-img draggable="false">
                    </div>
                    <p class="modal-flog-imagen__hint">Rueda del mouse o botones para zoom · Arrastra para mover</p>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
<script>
    // jQuery se carga vía Vite (módulo diferido); esperar a DOMContentLoaded para
    // garantizar que window.$ ya está definido.
    document.addEventListener('DOMContentLoaded', function () {
        const RUTA = @json(route('trazabilidad.index'));
        const $resultado = $('#resultado');

        const $modalRollos = $('#modal-rollos-maquina');
        if ($modalRollos.length) {
            $modalRollos.appendTo(document.body);
        }

        const $modalFlogImg = $('#modal-flog-imagen');
        if ($modalFlogImg.length) {
            $modalFlogImg.appendTo(document.body);
        }

        const $scrollMain = $('main.app-main');
        let scrollLockCount = 0;
        let sincronizandoSelects = false;

        function bloquearScrollPagina() {
            scrollLockCount++;
            if (scrollLockCount === 1 && $scrollMain.length) {
                $scrollMain.data('traza-scroll-top', $scrollMain.scrollTop());
                $scrollMain.css('overflow-y', 'hidden');
            }
        }

        function desbloquearScrollPagina() {
            scrollLockCount = Math.max(0, scrollLockCount - 1);
            if (scrollLockCount === 0 && $scrollMain.length) {
                $scrollMain.css('overflow-y', '');
                const top = $scrollMain.data('traza-scroll-top');
                if (typeof top === 'number') {
                    $scrollMain.scrollTop(top);
                }
            }
        }

        function preservarScrollEnMain(callback) {
            const top = $scrollMain.length ? $scrollMain.scrollTop() : 0;
            callback();
            requestAnimationFrame(function () {
                if ($scrollMain.length) $scrollMain.scrollTop(top);
            });
        }

        function escaparHtml(texto) {
            return String(texto ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function leerValorSelect($el) {
            const val = $el.val();
            if (val === null || val === undefined) return '';
            if (Array.isArray(val)) return val[0] || '';
            return String(val);
        }

        const flogVisor = {
            src: '',
            titulo: '',
            scale: 1,
            baseScale: 1,
            panX: 0,
            panY: 0,
            dragging: false,
            dragStartX: 0,
            dragStartY: 0,
            panStartX: 0,
            panStartY: 0,
        };

        const $flogStage = () => $modalFlogImg.find('[data-flog-stage]');
        const $flogViewport = () => $modalFlogImg.find('[data-flog-viewport]');
        const $flogImg = () => $modalFlogImg.find('[data-modal-flog-img]');

        function actualizarLabelZoomFlog() {
            const pct = flogVisor.baseScale > 0
                ? Math.round((flogVisor.scale / flogVisor.baseScale) * 100)
                : 100;
            $modalFlogImg.find('[data-flog-zoom-label]').text(pct + '%');
        }

        function aplicarTransformFlog() {
            $flogViewport().css(
                'transform',
                'translate(' + flogVisor.panX + 'px, ' + flogVisor.panY + 'px) scale(' + flogVisor.scale + ')'
            );
            actualizarLabelZoomFlog();
        }

        function ajustarImagenFlogAPantalla() {
            const img = $flogImg()[0];
            const stage = $flogStage()[0];
            if (!img || !stage || !img.naturalWidth || !img.naturalHeight) return;

            const pad = 48;
            const sw = Math.max(stage.clientWidth - pad, 200);
            const sh = Math.max(stage.clientHeight - pad, 200);
            flogVisor.baseScale = Math.min(sw / img.naturalWidth, sh / img.naturalHeight);
            flogVisor.scale = flogVisor.baseScale;
            flogVisor.panX = 0;
            flogVisor.panY = 0;

            $flogImg().css({
                width: img.naturalWidth + 'px',
                height: img.naturalHeight + 'px',
            });
            aplicarTransformFlog();
        }

        function cambiarZoomFlog(factor) {
            const min = flogVisor.baseScale * 0.35;
            const max = flogVisor.baseScale * 12;
            flogVisor.scale = Math.min(max, Math.max(min, flogVisor.scale * factor));
            aplicarTransformFlog();
        }

        function nombreArchivoFlog(src, titulo) {
            try {
                const url = new URL(src, window.location.origin);
                const file = url.searchParams.get('file');
                if (file) return file;
            } catch (e) { /* ignore */ }
            const base = (titulo || 'imagen-flog')
                .replace(/[^\w\s\-\.áéíóúñÁÉÍÓÚÑ]/g, '')
                .trim()
                .replace(/\s+/g, '_')
                .slice(0, 80) || 'imagen-flog';
            return base + '.jpg';
        }

        async function descargarImagenFlog() {
            if (!flogVisor.src) return;
            const nombre = nombreArchivoFlog(flogVisor.src, flogVisor.titulo);
            try {
                const res = await fetch(flogVisor.src, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('No se pudo obtener la imagen');
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = nombre;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                window.notify?.success('Descarga iniciada');
            } catch (err) {
                window.notify?.error(err.message || 'No se pudo descargar la imagen');
            }
        }

        function abrirModalFlogImagen(src, titulo) {
            if (!src || !$modalFlogImg.length) return;
            flogVisor.src = src;
            flogVisor.titulo = titulo || 'Imagen';
            flogVisor.scale = 1;
            flogVisor.baseScale = 1;
            flogVisor.panX = 0;
            flogVisor.panY = 0;

            const $img = $flogImg();
            $img.off('load.flogVisor').on('load.flogVisor', function () {
                ajustarImagenFlogAPantalla();
            });
            $img.attr({ src: src, alt: titulo || 'Imagen' });
            if ($img[0]?.complete && $img[0].naturalWidth) {
                ajustarImagenFlogAPantalla();
            }

            $('#modal-flog-imagen-titulo').text(titulo || '');
            $modalFlogImg.removeClass('hidden');
            bloquearScrollPagina();
        }

        function cerrarModalFlogImagen() {
            if (!$modalFlogImg.length) return;
            $modalFlogImg.addClass('hidden');
            $flogImg().off('load.flogVisor').attr('src', '');
            $flogViewport().css('transform', '');
            flogVisor.src = '';
            flogVisor.dragging = false;
            $flogStage().removeClass('is-dragging');
            desbloquearScrollPagina();
        }

        $resultado.on('click', '.flog-visual-frame[data-flog-zoom]', function () {
            const src = $(this).data('flog-zoom') || $(this).find('img').attr('src');
            const titulo = $(this).find('.flog-visual-frame__caption').text().trim() || 'Imagen';
            abrirModalFlogImagen(src, titulo);
        });

        $resultado.on('keydown', '.flog-visual-frame[data-flog-zoom]', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });

        $modalFlogImg.on('click', '[data-modal-flog-close]', cerrarModalFlogImagen);
        $modalFlogImg.on('click', '[data-flog-zoom-in]', function () { cambiarZoomFlog(1.2); });
        $modalFlogImg.on('click', '[data-flog-zoom-out]', function () { cambiarZoomFlog(1 / 1.2); });
        $modalFlogImg.on('click', '[data-flog-zoom-reset]', ajustarImagenFlogAPantalla);
        $modalFlogImg.on('click', '[data-flog-download]', descargarImagenFlog);

        $modalFlogImg.on('click', function (e) {
            if ($(e.target).is('.modal-flog-imagen__backdrop')) {
                cerrarModalFlogImagen();
            }
        });

        $modalFlogImg.on('wheel', '[data-flog-stage]', function (e) {
            if ($modalFlogImg.hasClass('hidden')) return;
            e.preventDefault();
            const factor = e.originalEvent.deltaY < 0 ? 1.12 : 1 / 1.12;
            cambiarZoomFlog(factor);
        });

        $modalFlogImg.on('mousedown', '[data-flog-stage]', function (e) {
            if (e.button !== 0) return;
            flogVisor.dragging = true;
            flogVisor.dragStartX = e.clientX;
            flogVisor.dragStartY = e.clientY;
            flogVisor.panStartX = flogVisor.panX;
            flogVisor.panStartY = flogVisor.panY;
            $flogStage().addClass('is-dragging');
        });

        $(document).on('mousemove.flogVisor', function (e) {
            if (!flogVisor.dragging || $modalFlogImg.hasClass('hidden')) return;
            flogVisor.panX = flogVisor.panStartX + (e.clientX - flogVisor.dragStartX);
            flogVisor.panY = flogVisor.panStartY + (e.clientY - flogVisor.dragStartY);
            aplicarTransformFlog();
        });

        $(document).on('mouseup.flogVisor', function () {
            if (!flogVisor.dragging) return;
            flogVisor.dragging = false;
            $flogStage().removeClass('is-dragging');
        });

        $(window).on('resize.flogVisor', function () {
            if (!$modalFlogImg.hasClass('hidden')) {
                ajustarImagenFlogAPantalla();
            }
        });

        $('.filtro-select').select2({
            width: '100%',
            placeholder: 'Todos',
            allowClear: true,
            dropdownCssClass: 'traza-select2-dd'
        });

        // ===== Pestañas Trazabilidad / Producción / Flogs =====
        // Las pestañas viven dentro de #resultado (se re-renderiza en cada AJAX), así
        // que el handler es delegado y la pestaña activa se reaplica tras cada carga.
        let tabActivo = 'trazabilidad';
        function aplicarTab(tab) {
            tabActivo = tab;
            const $r = $('#resultado');
            $r.find('[data-pane]').addClass('hidden');
            $r.find('[data-pane="' + tab + '"]').removeClass('hidden');
            $r.find('.traza-tab').each(function () {
                const activo = $(this).data('tab') === tab;
                $(this).toggleClass('text-blue-600 border-blue-600', activo)
                       .toggleClass('text-slate-400 border-transparent hover:text-slate-600', !activo);
            });
        }
        $resultado.on('click', '.traza-tab', function () {
            aplicarTab($(this).data('tab'));
        });

        // Reconstruye un select  (Flog/Tamaño) preservando el valor.
        function rebuildSelect(id, opciones, seleccionado) {
            const $el = $(id);
            const val = seleccionado == null ? '' : String(seleccionado);
            let html = '<option value=""></option>';
            const valores = new Set();
            (opciones || []).forEach(function (v) {
                const s = String(v);
                valores.add(s);
                const sel = s === val ? ' selected' : '';
                html += '<option value="' + escaparHtml(s) + '"' + sel + '>' + escaparHtml(s) + '</option>';
            });
            if (val && !valores.has(val)) {
                html += '<option value="' + escaparHtml(val) + '" selected>' + escaparHtml(val) + '</option>';
            }
            sincronizandoSelects = true;
            $el.html(html);
            $el.val(val || null).trigger('change');
            sincronizandoSelects = false;
        }

        // Reconstruye un select combinado "código / nombre": [{codigo, label}].
        function rebuildCombo(id, opciones, seleccionado) {
            const $el = $(id);
            const val = seleccionado == null ? '' : String(seleccionado);
            let html = '<option value=""></option>';
            let labelSel = val;
            const codigos = new Set();
            (opciones || []).forEach(function (o) {
                const codigo = String(o.codigo);
                codigos.add(codigo);
                const sel = codigo === val ? ' selected' : '';
                if (sel) labelSel = o.label || codigo;
                html += '<option value="' + escaparHtml(codigo) + '"' + sel + '>'
                    + escaparHtml(o.label) + '</option>';
            });
            if (val && !codigos.has(val)) {
                const fallback = $el.data('traza-last-label') || val;
                labelSel = fallback;
                html += '<option value="' + escaparHtml(val) + '" selected>' + escaparHtml(fallback) + '</option>';
            }
            sincronizandoSelects = true;
            $el.html(html);
            $el.val(val || null).trigger('change');
            if (val) $el.data('traza-last-label', labelSel);
            sincronizandoSelects = false;
        }

        // Resumen de conteos arriba de los selects (solo si hay Flog).
        function rebuildResumen(counts, hayFlog) {
            const $c = $('#resumen-conteos');
            if (!hayFlog) { $c.addClass('hidden').html(''); return; }
            // n === 1 → singular, si no → plural.
            const item = (n, singular, plural, icon) =>
                '<span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1">'
                + '<i class="fa-solid ' + icon + '"></i>' + n + ' ' + (Number(n) === 1 ? singular : plural) + '</span>';
            $c.removeClass('hidden').html(
                item(counts.articulo, 'artículo', 'artículos', 'fa-box') +
                item(counts.tamano, 'tamaño', 'tamaños', 'fa-ruler') +
                item(counts.color, 'color', 'colores', 'fa-palette')
            );
        }

        // Meses seleccionados (multi) desde el input oculto CSV.
        function mesesSeleccionados() {
            return ($('#filtro-mes').val() || '').split(',').filter(Boolean);
        }

        function hayFiltroPrincipal(f) {
            return !!(f.flog || f.articulo || f.tamano || f.color || f.mes);
        }

        function formatNum(n, dec) {
            return Number(n || 0).toLocaleString('es-MX', {
                minimumFractionDigits: dec,
                maximumFractionDigits: dec,
            });
        }

        function abrirModalRollosMaquina(maquina, filas) {
            const $modal = $('#modal-rollos-maquina');
            if (!$modal.length) return;

            let totalPzas = 0;
            let totalKg = 0;
            let rowsHtml = '';

            (filas || []).forEach(function (f) {
                const pzas = Number(f.cantidad || 0);
                const kg = Number(f.peso || 0);
                totalPzas += pzas;
                totalKg += kg;
                const articulo = [f.articulo, f.nombreArticulo].filter(Boolean).join(' · ');
                const color = [f.color, f.nombreColor].filter(Boolean).join(' · ');
                rowsHtml += '<tr class="border-b border-slate-100 hover:bg-slate-50/80">'
                    + '<td class="px-3 py-2 font-mono font-semibold">' + (f.orden || '—') + '</td>'
                    + '<td class="px-3 py-2">' + (articulo || '—') + '</td>'
                    + '<td class="px-3 py-2">' + (color || '—') + '</td>'
                    + '<td class="px-3 py-2 text-right tabular-nums">' + formatNum(pzas, 0) + '</td>'
                    + '<td class="px-3 py-2 text-right tabular-nums">' + formatNum(kg, 2) + '</td>'
                    + '</tr>';
            });

            $('#modal-rollos-maquina-titulo').text(maquina || 'Detalle máquina');
            $('#modal-rollos-maquina-body').html(rowsHtml);
            $('#modal-rollos-total-pzas').text(formatNum(totalPzas, 0));
            $('#modal-rollos-total-kg').text(formatNum(totalKg, 2));
            $modal.removeClass('hidden').css('display', 'flex');
            bloquearScrollPagina();
        }

        function cerrarModalRollosMaquina() {
            const $modal = $('#modal-rollos-maquina');
            if (!$modal.length) return;
            $modal.addClass('hidden').css('display', '');
            desbloquearScrollPagina();
        }

        $resultado.on('click', '.prod-rollos-maquina-card', function () {
            const maquina = $(this).data('maquina');
            let filas = $(this).data('filas');
            if (typeof filas === 'string') {
                try { filas = JSON.parse(filas); } catch (e) { filas = []; }
            }
            abrirModalRollosMaquina(maquina, filas);
        });

        $resultado.on('click', '[data-modal-rollos-close]', cerrarModalRollosMaquina);

        $('#modal-rollos-maquina').on('click', '[data-modal-rollos-close]', cerrarModalRollosMaquina);
        $('#modal-rollos-maquina').on('click', function (e) {
            if (e.target === this) cerrarModalRollosMaquina();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && !$('#modal-rollos-maquina').hasClass('hidden')) {
                cerrarModalRollosMaquina();
            }
            if (e.key === 'Escape' && !$modalFlogImg.hasClass('hidden')) {
                cerrarModalFlogImagen();
            }
            if (!$modalFlogImg.hasClass('hidden')) {
                if (e.key === '+' || e.key === '=') {
                    e.preventDefault();
                    cambiarZoomFlog(1.2);
                } else if (e.key === '-') {
                    e.preventDefault();
                    cambiarZoomFlog(1 / 1.2);
                } else if (e.key === '0') {
                    e.preventDefault();
                    ajustarImagenFlogAPantalla();
                }
            }
        });

        // Renderiza los badges de meses [{mes, nombre}] en la barra de filtros (multi-select).
        function rebuildMeses(meses) {
            const activos = mesesSeleccionados();
            let html = '';
            (meses || []).forEach(function (m) {
                const esActivo = activos.includes(String(m.mes));
                const cls = esActivo
                    ? 'bg-blue-600 border-blue-600 text-white'
                    : 'bg-white border-slate-200 text-slate-600 hover:border-blue-400 hover:text-blue-600';
                html += '<a href="#" data-mes="' + m.mes + '" '
                      + 'class="badge-mes inline-flex items-center rounded-full text-xs font-semibold px-3 py-1 border transition-colors ' + cls + '">'
                      + m.nombre + '</a>';
            });
            if (!html) {
                html = '<span class="text-xs text-slate-400 italic">Sin meses para los filtros actuales</span>';
            }
            $('#meses-badges').html(html);
        }

        function valoresActuales() {
            return {
                flog:     leerValorSelect($('#filtro-flog')),
                articulo: leerValorSelect($('#filtro-articulo')),
                tamano:   leerValorSelect($('#filtro-tamano')),
                color:    leerValorSelect($('#filtro-color')),
                mes:      $('#filtro-mes').val() || '',
                metrica:  $('#filtro-metrica').val() || 'cantidad',
            };
        }

        // Secuencia de peticiones: matriz primero, producción y flogs después. Solo la
        // respuesta más reciente se aplica (evita condiciones de carrera).
        let reqSeq = 0;
        let prodSeq = 0;
        let flogsSeq = 0;

        let prodFiltroActivo = 'todos';

        function aplicarFiltroProduccion(filter) {
            prodFiltroActivo = filter || 'todos';
            const $crudo = $('#produccion-contenido .prod-area--crudo');
            if (!$crudo.length) return;

            $crudo.find('.prod-filter-btn').each(function () {
                $(this).toggleClass('is-active', $(this).data('filter') === prodFiltroActivo);
            });

            let visibles = 0;

            $crudo.find('.prod-card-grupo').each(function () {
                const visible = prodFiltroActivo === 'todos'
                    || $(this).data('estado') === prodFiltroActivo;
                $(this).toggle(visible);
                if (visible) visibles++;
            });

            $crudo.find('.prod-cards-grid > .prod-card').each(function () {
                const visible = prodFiltroActivo === 'todos'
                    || $(this).data('estado') === prodFiltroActivo;
                $(this).toggle(visible);
                if (visible) visibles++;
            });

            const totalItems = $crudo.find('.prod-card-grupo').length
                + $crudo.find('.prod-cards-grid > .prod-card').length;
            $crudo.find('.prod-sin-resultados').toggle(visibles === 0 && totalItems > 0);
        }

        $resultado.on('click', '.prod-filter-btn', function () {
            aplicarFiltroProduccion($(this).data('filter'));
        });

        function actualizarBadgeProduccion(cantidad) {
            const $tab = $('#resultado .traza-tab[data-tab="produccion"]');
            $tab.find('.prod-alert-badge').remove();
            if (cantidad > 0) {
                $tab.append(
                    '<span class="prod-alert-badge inline-flex items-center justify-center rounded-full bg-amber-500 text-white text-[10px] font-bold min-w-4 h-4 px-1"'
                    + ' title="' + cantidad + ' orden(es) con producción en otro telar">' + cantidad + '</span>'
                );
            }
        }

        async function cargarFlogs(params, seqMatriz) {
            const seq = ++flogsSeq;
            try {
                const data = await window.http.get(RUTA, { params: { ...params, part: 'flogs' } });
                if (seq !== flogsSeq || seqMatriz !== reqSeq) return;
                const $cont = $('#flogs-contenido');
                if ($cont.length) {
                    preservarScrollEnMain(function () {
                        $cont.html(data.flogsHtml);
                    });
                }
            } catch (err) {
                if (seq === flogsSeq && seqMatriz === reqSeq) {
                    const $cont = $('#flogs-contenido');
                    if ($cont.length) {
                        $cont.html(
                            '<div class="bg-white border border-red-200 rounded-2xl p-8 text-center">'
                            + '<p class="text-red-600 font-semibold">No se pudo cargar la información del Flog.</p>'
                            + '<p class="text-slate-400 text-sm mt-1">' + (err.message || '') + '</p></div>'
                        );
                    }
                }
            }
        }

        async function cargarProduccion(params, seqMatriz) {
            const seq = ++prodSeq;
            try {
                const data = await window.http.get(RUTA, { params: { ...params, part: 'produccion' } });
                if (seq !== prodSeq || seqMatriz !== reqSeq) return;
                const $cont = $('#produccion-contenido');
                if ($cont.length) {
                    preservarScrollEnMain(function () {
                        $cont.html(data.produccionHtml);
                        aplicarFiltroProduccion(prodFiltroActivo);
                    });
                }
                actualizarBadgeProduccion(data.prodAlertas || 0);
            } catch (err) {
                if (seq === prodSeq && seqMatriz === reqSeq) {
                    const $cont = $('#produccion-contenido');
                    if ($cont.length) {
                        $cont.html(
                            '<div class="bg-white border border-red-200 rounded-2xl p-8 text-center">'
                            + '<p class="text-red-600 font-semibold">No se pudo cargar la producción.</p>'
                            + '<p class="text-slate-400 text-sm mt-1">' + (err.message || '') + '</p></div>'
                        );
                    }
                }
            }
        }

        async function aplicar(params) {
            const seq = ++reqSeq;
            prodSeq++;
            flogsSeq++;
            const scrollAntes = $scrollMain.length ? $scrollMain.scrollTop() : 0;
            $resultado.css('opacity', 0.5);
            try {
                const data = await window.http.get(RUTA, { params: { ...params, part: 'matriz' } });
                if (seq !== reqSeq) return;
                $resultado.html(data.resultado);
                aplicarTab(tabActivo);

                sincronizandoSelects = true;
                rebuildSelect('#filtro-flog', data.opciones.flog, data.filtros.flog);
                rebuildCombo('#filtro-articulo', data.opciones.articulo, data.filtros.articulo);
                rebuildSelect('#filtro-tamano', data.opciones.tamano, data.filtros.tamano);
                rebuildCombo('#filtro-color', data.opciones.color, data.filtros.color);
                sincronizandoSelects = false;

                $('#filtro-mes').val(data.filtros.mes || '');
                rebuildMeses(data.opciones.mes);
                rebuildResumen({
                    articulo: (data.opciones.articulo || []).length,
                    tamano:   (data.opciones.tamano || []).length,
                    color:    (data.opciones.color || []).length,
                }, !!data.filtros.flog);
                window.history.replaceState(null, '', RUTA);

                requestAnimationFrame(function () {
                    if ($scrollMain.length) $scrollMain.scrollTop(scrollAntes);
                });

                const hayFiltro = hayFiltroPrincipal(data.filtros);
                if (hayFiltro) {
                    cargarProduccion(params, seq);
                } else {
                    actualizarBadgeProduccion(0);
                }
                if (data.filtros.flog) {
                    cargarFlogs(params, seq);
                }
            } catch (err) {
                if (seq === reqSeq) window.notify?.error(err.message || 'Error al cargar la trazabilidad');
            } finally {
                if (seq === reqSeq) $resultado.css('opacity', '');
            }
        }

        function programarAplicarFiltros() {
            clearTimeout(debounceFiltro);
            debounceFiltro = setTimeout(function () {
                if (sincronizandoSelects) return;
                aplicar(valoresActuales());
            }, 100);
        }

        // select2:clear + change pueden dispararse juntos; sincronizandoSelects evita
        // peticiones durante rebuild AJAX.
        let debounceFiltro = null;
        $('.filtro-select').on('change select2:select select2:clear', function (e) {
            if (sincronizandoSelects) return;
            if (e.type === 'select2:select' && e.params && e.params.data) {
                $(this).data('traza-last-label', e.params.data.text || '');
            }
            programarAplicarFiltros();
        });

        // Áreas expandibles (Flog con +2 artículos): al hacer click en la fila del área
        // se muestran/ocultan sus sub-filas de desglose por artículo/color. Se delega en
        // #resultado porque su contenido se reemplaza completo en cada respuesta AJAX.
        $resultado.on('click', '.area-fila', function () {
            const key = $(this).data('area-key');
            const abierto = $(this).hasClass('area-abierta');
            $(this).toggleClass('area-abierta');
            $(this).find('.area-caret').toggleClass('rotate-90', !abierto);
            $resultado.find('tr.detalle-fila[data-area-key="' + key + '"]').toggleClass('hidden', abierto);
        });

        // Click en un badge de mes (multi-select: agrega/quita ese mes).
        $('#meses-badges').on('click', '.badge-mes', function (e) {
            e.preventDefault();
            const m = String($(this).data('mes'));
            let sel = mesesSeleccionados();
            sel = sel.includes(m) ? sel.filter(function (x) { return x !== m; }) : sel.concat(m);
            $('#filtro-mes').val(sel.join(','));
            aplicar(valoresActuales());
        });

        // Render inicial de los badges de meses (desde los datos del servidor).
        rebuildMeses(@json($mesesDisponibles));

        // Estilo inicial de la pestaña activa (las pestañas existen si hay filtro).
        aplicarTab(tabActivo);

        // Carga diferida de producción en la primera pintura (matriz ya renderizada).
        @if ($hayFiltro && ($produccionCargando ?? false))
            cargarProduccion(valoresActuales(), 0);
        @endif

        @if ($hayFlog && ($flogsCargando ?? false))
            cargarFlogs(valoresActuales(), 0);
        @endif

        // Render inicial del resumen de conteos.
        @php $conteosIniciales = [
            'articulo' => $opcionesArticulo->count(),
            'tamano'   => $opcionesTamano->count(),
            'color'    => $opcionesColor->count(),
        ]; @endphp
        rebuildResumen(@json($conteosIniciales), @json($hayFlog));

        // Switch Material / Kilos: cambia la métrica de la matriz (sin recargar).
        $('.btn-metrica').on('click', function () {
            const metrica = $(this).data('metrica');
            $('#filtro-metrica').val(metrica);
            // Estado visual del segmentado (incluye clases de hover correctas).
            $('.btn-metrica').removeClass('bg-blue-600 text-white hover:bg-blue-700')
                             .addClass('bg-white text-slate-600 hover:bg-slate-50');
            $(this).removeClass('bg-white text-slate-600 hover:bg-slate-50')
                   .addClass('bg-blue-600 text-white hover:bg-blue-700');
            aplicar(valoresActuales());
        });

        // Botón Exportar a Excel: descarga la matriz con los filtros activos.
        const RUTA_EXPORT = @json(route('trazabilidad.exportar'));
        $('#btn-exportar').on('click', function () {
            const v = valoresActuales();
            const hayFiltro = hayFiltroPrincipal(v);
            if (!hayFiltro) {
                window.notify?.warning('Selecciona al menos un filtro antes de exportar.');
                return;
            }
            const qs = new URLSearchParams(v).toString();
            window.location.href = RUTA_EXPORT + '?' + qs;
        });

        // Botón Restablecer del navbar: limpia todos los filtros (sin recargar).
        // La métrica (Material/Kilos) NO se resetea, es una preferencia de visualización.
        $('#btn-restablecer').on('click', function () {
            sincronizandoSelects = true;
            $('.filtro-select').val(null).trigger('change');
            sincronizandoSelects = false;
            $('#filtro-mes').val('');
            aplicar({
                flog: '', articulo: '', tamano: '', color: '', mes: '',
                metrica: $('#filtro-metrica').val() || 'cantidad',
            });
        });

        // Por si algún modal cerró sin liberar el scroll (p. ej. navegación rápida).
        scrollLockCount = 0;
        if ($scrollMain.length) $scrollMain.css('overflow-y', '');
    });
</script>
@endpush
