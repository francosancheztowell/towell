@php
    $general = $flogs['general'] ?? [];
    $etiquetas = $flogs['etiquetas'] ?? [];
    $empaques = $flogs['empaques'] ?? [];
    $encontrado = (bool) ($flogs['encontrado'] ?? false);
    $hayFlogFiltro = filled($filtros['flog'] ?? null);

    $campo = function (string $label, ?string $valor, bool $destacar = false) {
        $texto = filled($valor) ? $valor : '—';

        return '<div class="flog-campo border-b border-dashed border-slate-200 pb-3">'
            . '<dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">'
            . e($label)
            . '</dt>'
            . '<dd class="text-sm font-semibold text-slate-800 break-words '
            . ($destacar ? 'text-blue-700' : '')
            . '">'
            . e($texto)
            . '</dd></div>';
    };
@endphp

@if (! $hayFlogFiltro)
    <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-10 md:p-14 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center mb-4">
            <i class="fa-solid fa-list-ol text-blue-500 text-lg"></i>
        </div>
        <p class="text-slate-700 font-semibold">Selecciona un Flog para ver la información del proyecto</p>
        <p class="text-slate-400 text-sm mt-1">Usa el filtro <strong>Flog</strong> en la barra superior.</p>
    </div>
@elseif (! $encontrado)
    <div class="bg-white border border-amber-200 rounded-2xl p-10 md:p-14 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mb-4">
            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg"></i>
        </div>
        <p class="text-slate-700 font-semibold">No se encontró información del Flog en TI</p>
        <p class="text-slate-400 text-sm mt-1 font-mono">{{ $filtros['flog'] ?? '' }}</p>
    </div>
@else
    {{-- Información general --}}
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-6" aria-labelledby="flog-titulo-general">
        <header class="flex items-center gap-2 px-4 py-3 bg-blue-50 border-b border-blue-200">
            <i class="fa-solid fa-clipboard-list text-blue-600"></i>
            <h2 id="flog-titulo-general" class="text-sm font-bold uppercase tracking-wide text-blue-800">
                Información general del proyecto
            </h2>
        </header>

        <dl class="p-4 md:p-5 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
            {!! $campo('Folio compra especial', $general['idFlog'] ?? null, true) !!}
            {!! $campo('Cliente', $general['cliente'] ?? null, true) !!}
            {!! $campo('Tipo de pedido', $general['tipoPedido'] ?? null, true) !!}
            {!! $campo('Agente', $general['agente'] ?? null, true) !!}
            {!! $campo('Categoría calidad', $general['categoriaCalidad'] ?? null, true) !!}
            {!! $campo('Tipo de cliente', $general['tipoClienteId'] ?? null, true) !!}
            {!! $campo('Pruebas laboratorio', $general['pruebasLab'] ?? null, true) !!}
            {!! $campo('Proyecto', $general['nameProyect'] ?? null, true) !!}
            {!! $campo('Núm. proveedor', $general['numProveedor'] ?? null) !!}
            {!! $campo('Proceso 100% Cadmex', $general['procesoCatMex'] ?? null) !!}
            {!! $campo('Suavizante exportación', $general['twSuavizante'] ?? null) !!}
            {!! $campo('Empresa', $general['empresa'] ?? null) !!}
            {!! $campo('Fecha creación Flog', $general['transDate'] ?? null, true) !!}
        </dl>
    </section>

    @if (filled($general['avisoEspecialTxt'] ?? null))
        <section class="bg-amber-50 border border-amber-200 rounded-2xl p-4 md:p-5 mb-6">
            <h3 class="text-xs font-bold uppercase tracking-wide text-amber-800 mb-2">Aviso especial</h3>
            <p class="text-sm text-amber-900 whitespace-pre-line">{{ $general['avisoEspecialTxt'] }}</p>
        </section>
    @endif

    @if (filled($general['infoImportante'] ?? null))
        <section class="bg-slate-50 border border-slate-200 rounded-2xl p-4 md:p-5 mb-6">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-700 mb-2">Información importante</h3>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $general['infoImportante'] }}</p>
        </section>
    @endif

    {{-- Etiquetas --}}
    <section class="mb-6" aria-labelledby="flog-titulo-etiquetas">
        <h3 id="flog-titulo-etiquetas" class="text-sm font-bold text-slate-700 mb-3">
            <i class="fa-solid fa-tags text-slate-400 mr-1"></i> Etiquetas
        </h3>

        @if (empty($etiquetas))
            <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-8 text-center text-slate-400 text-sm">
                Sin líneas de etiquetado registradas.
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($etiquetas as $etiq)
                    <article class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                        @if (! empty($etiq['imagenUrl']))
                            <div class="bg-slate-100 border-b border-slate-200 p-3 flex items-center justify-center min-h-[140px]">
                                <img src="{{ $etiq['imagenUrl'] }}"
                                     alt="{{ $etiq['comentarios'] ?: 'Etiqueta' }}"
                                     class="max-h-40 max-w-full object-contain rounded"
                                     loading="lazy">
                            </div>
                        @endif
                        <div class="p-4 space-y-2 text-sm">
                            @if (filled($etiq['itemId'] ?? null))
                                <p><span class="text-slate-500 font-semibold text-xs uppercase">Item</span><br>{{ $etiq['itemId'] }}</p>
                            @endif
                            @if (filled($etiq['name'] ?? null))
                                <p><span class="text-slate-500 font-semibold text-xs uppercase">Nombre</span><br>{{ $etiq['name'] }}</p>
                            @endif
                            @if (filled($etiq['comentarios'] ?? null))
                                <p><span class="text-slate-500 font-semibold text-xs uppercase">Comentarios</span><br>{{ $etiq['comentarios'] }}</p>
                            @endif
                            @if (empty($etiq['imagenUrl']) && filled($etiq['imagenPath'] ?? null))
                                <p class="text-xs text-slate-400 break-all">{{ $etiq['imagenPath'] }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Empaque --}}
    <section aria-labelledby="flog-titulo-empaque">
        <h3 id="flog-titulo-empaque" class="text-sm font-bold text-slate-700 mb-3">
            <i class="fa-solid fa-box-open text-slate-400 mr-1"></i> Empaque
        </h3>

        @if (empty($empaques))
            <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-8 text-center text-slate-400 text-sm">
                Sin datos de empaque registrados.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($empaques as $emp)
                    <article class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                        <div class="grid grid-cols-1 lg:grid-cols-[1fr_minmax(200px,280px)]">
                            <div class="p-4 md:p-5 space-y-3 text-sm">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Id empaque</p>
                                    <p class="font-semibold text-slate-800">{{ filled($emp['idEmpaque'] ?? null) ? $emp['idEmpaque'] : '—' }}</p>
                                </div>
                                @if (filled($emp['otroEmpaque'] ?? null))
                                    <div>
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Otro empaque</p>
                                        <p class="text-slate-700 whitespace-pre-line">{{ $emp['otroEmpaque'] }}</p>
                                    </div>
                                @endif
                            </div>
                            @if (! empty($emp['imagenUrl']))
                                <div class="bg-slate-100 border-t lg:border-t-0 lg:border-l border-slate-200 p-3 flex items-center justify-center min-h-[160px]">
                                    <img src="{{ $emp['imagenUrl'] }}"
                                         alt="Empaque {{ $emp['idEmpaque'] ?? '' }}"
                                         class="max-h-48 max-w-full object-contain rounded"
                                         loading="lazy">
                                </div>
                            @elseif (filled($emp['imagenPath'] ?? null))
                                <div class="p-4 text-xs text-slate-400 break-all border-t lg:border-t-0 lg:border-l border-slate-200">
                                    {{ $emp['imagenPath'] }}
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endif
