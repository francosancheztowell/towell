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
    </style>

    <div class="w-full min-h-full px-1.5 md:px-2 py-3" style="background:#f1f5f9;" id="globalLoader">

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
                    <label for="filtro-color" class="block text-xs font-semibold text-slate-500 mb-0.5">Color</label>
                    <select name="color" id="filtro-color" class="filtro-select w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos</option>
                        @foreach ($opcionesColor as $opt)
                            <option value="{{ $opt['codigo'] }}" @selected(($filtros['color'] ?? '') === $opt['codigo'])>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Switch de métrica + meses disponibles (misma fila) --}}
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
                    {{-- Badges de meses (los renderiza el JS desde mesesDisponibles) --}}
                    <span id="meses-badges" class="flex flex-wrap items-center gap-2"></span>
                </div>
            </div>

            {{-- Mes se filtra por los badges; se guarda aquí para conservarlo entre cambios --}}
            <input type="hidden" name="mes" id="filtro-mes" value="{{ $filtros['mes'] ?? '' }}">
            {{-- Métrica activa (la controlan los botones de arriba) --}}
            <input type="hidden" name="metrica" id="filtro-metrica" value="{{ $metrica ?? 'cantidad' }}">
        </form>

        {{-- Contenedor que se actualiza vía AJAX (badges + matriz), sin recargar --}}
        <div id="resultado">
            @include('modulos.trazabilidad._resultado')
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

        $('.filtro-select').select2({
            width: '100%',
            placeholder: 'Todos',
            allowClear: true,
            dropdownCssClass: 'traza-select2-dd'
        });

        // ===== Pestañas Trazabilidad / Producción =====
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

        // Reconstruye un select simple (Flog/Tamaño/Color) preservando el valor.
        function rebuildSelect(id, opciones, seleccionado) {
            let html = '<option value="">Todos</option>';
            (opciones || []).forEach(function (v) {
                const sel = String(v) === String(seleccionado ?? '') ? ' selected' : '';
                html += '<option value="' + v + '"' + sel + '>' + v + '</option>';
            });
            $(id).html(html).trigger('change.select2'); // refresca select2 sin disparar el handler de cambio
        }

        // Reconstruye un select combinado "código / nombre": [{codigo, label}].
        function rebuildCombo(id, opciones, seleccionado) {
            let html = '<option value="">Todos</option>';
            (opciones || []).forEach(function (o) {
                const sel = String(o.codigo) === String(seleccionado ?? '') ? ' selected' : '';
                html += '<option value="' + o.codigo + '"' + sel + '>' + o.label + '</option>';
            });
            $(id).html(html).trigger('change.select2');
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
                flog:     $('#filtro-flog').val() || '',
                articulo: $('#filtro-articulo').val() || '', // código de artículo
                tamano:   $('#filtro-tamano').val() || '',
                color:    $('#filtro-color').val() || '',
                mes:      $('#filtro-mes').val() || '',      // input oculto controlado por los badges
                metrica:  $('#filtro-metrica').val() || 'cantidad', // switch Material/Kilos
            };
        }

        // Secuencia de peticiones: matriz primero, producción después. Solo la
        // respuesta más reciente se aplica (evita condiciones de carrera).
        let reqSeq = 0;
        let prodSeq = 0;

        let prodFiltroActivo = 'todos';

        function aplicarFiltroProduccion(filter) {
            prodFiltroActivo = filter || 'todos';
            const $crudo = $('#produccion-contenido .prod-area--crudo');
            if (!$crudo.length) return;

            $crudo.find('.prod-filter-btn').each(function () {
                $(this).toggleClass('is-active', $(this).data('filter') === prodFiltroActivo);
            });

            let visibles = 0;
            $crudo.find('.prod-card').each(function () {
                const visible = prodFiltroActivo === 'todos'
                    || $(this).data('estado') === prodFiltroActivo;
                $(this).toggle(visible);
                if (visible) visibles++;
            });

            $crudo.find('.prod-sin-resultados').toggle(visibles === 0 && $crudo.find('.prod-card').length > 0);
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

        async function cargarProduccion(params, seqMatriz) {
            const seq = ++prodSeq;
            try {
                const data = await window.http.get(RUTA, { params: { ...params, part: 'produccion' } });
                if (seq !== prodSeq || seqMatriz !== reqSeq) return;
                const $cont = $('#produccion-contenido');
                if ($cont.length) {
                    $cont.html(data.produccionHtml);
                    aplicarFiltroProduccion(prodFiltroActivo);
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
            prodSeq++; // invalida producción en curso al cambiar filtros
            $resultado.css('opacity', 0.5);
            try {
                const data = await window.http.get(RUTA, { params: { ...params, part: 'matriz' } });
                if (seq !== reqSeq) return;
                $resultado.html(data.resultado);
                aplicarTab(tabActivo);

                rebuildSelect('#filtro-flog', data.opciones.flog, data.filtros.flog);
                rebuildCombo('#filtro-articulo', data.opciones.articulo, data.filtros.articulo);
                rebuildSelect('#filtro-tamano', data.opciones.tamano, data.filtros.tamano);
                rebuildCombo('#filtro-color', data.opciones.color, data.filtros.color);
                $('#filtro-mes').val(data.filtros.mes || '');
                rebuildMeses(data.opciones.mes);
                rebuildResumen({
                    articulo: (data.opciones.articulo || []).length,
                    tamano:   (data.opciones.tamano || []).length,
                    color:    (data.opciones.color || []).length,
                }, !!data.filtros.flog);
                window.history.replaceState(null, '', RUTA);

                const hayFiltro = data.filtros.flog || data.filtros.articulo
                    || data.filtros.tamano || data.filtros.color || data.filtros.mes;
                if (hayFiltro) {
                    cargarProduccion(params, seq);
                } else {
                    actualizarBadgeProduccion(0);
                }
            } catch (err) {
                if (seq === reqSeq) window.notify?.error(err.message || 'Error al cargar la trazabilidad');
            } finally {
                if (seq === reqSeq) $resultado.css('opacity', '');
            }
        }

        // Cambio en cualquier filtro (incluido Flog) → AJAX con la combinación actual.
        // Se "desbota" (debounce) porque select2 con allowClear puede emitir el evento
        // `change` más de una vez al seleccionar: así se hace UNA sola petición con el
        // valor final, en lugar de disparar dos y depender de cuál gana.
        let debounceFiltro = null;
        $('.filtro-select').on('change', function () {
            clearTimeout(debounceFiltro);
            debounceFiltro = setTimeout(function () {
                aplicar(valoresActuales());
            }, 80);
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

        // Render inicial del resumen de conteos.
        @php $conteosIniciales = [
            'articulo' => $opcionesArticulo->count(),
            'tamano'   => $opcionesTamano->count(),
            'color'    => $opcionesColor->count(),
            'mes'      => $mesesDisponibles->count(),
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
            const hayFiltro = v.flog || v.articulo || v.tamano || v.color || v.mes;
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
            $('.filtro-select').val(null).trigger('change.select2');
            $('#filtro-mes').val('');
            aplicar({
                flog: '', articulo: '', tamano: '', color: '', mes: '',
                metrica: $('#filtro-metrica').val() || 'cantidad',
            });
        });
    });
</script>
@endpush
