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

        // Secuencia de peticiones: cada llamada incrementa el contador y solo la
        // respuesta de la ÚLTIMA petición se aplica. Así nunca se descarta el cambio
        // del usuario (problema del antiguo guard `cargando`, que tragaba la llamada
        // buena cuando select2/allowClear emitía `change` más de una vez) ni se pinta
        // una respuesta obsoleta si llegan fuera de orden.
        let reqSeq = 0;
        async function aplicar(params) {
            const seq = ++reqSeq;
            $resultado.css('opacity', 0.5);
            try {
                const data = await window.http.get(RUTA, { params });
                if (seq !== reqSeq) return; // llegó una petición más nueva; ignorar esta
                $resultado.html(data.resultado);
                rebuildSelect('#filtro-flog', data.opciones.flog, data.filtros.flog);
                rebuildCombo('#filtro-articulo', data.opciones.articulo, data.filtros.articulo);
                rebuildSelect('#filtro-tamano', data.opciones.tamano, data.filtros.tamano);
                rebuildCombo('#filtro-color', data.opciones.color, data.filtros.color);
                $('#filtro-mes').val(data.filtros.mes || ''); // sincroniza los meses activos (CSV)
                rebuildMeses(data.opciones.mes);
                rebuildResumen({
                    articulo: (data.opciones.articulo || []).length,
                    tamano:   (data.opciones.tamano || []).length,
                    color:    (data.opciones.color || []).length,
                }, !!data.filtros.flog);
                // URL limpia (siempre /trazabilidad) — sin recargar.
                window.history.replaceState(null, '', RUTA);
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
