@php
    $general = $flogs['general'] ?? [];
    $etiquetas = $flogs['etiquetas'] ?? [];
    $empaques = $flogs['empaques'] ?? [];
    $encontrado = (bool) ($flogs['encontrado'] ?? false);
    $hayFlogFiltro = filled($filtros['flog'] ?? null);
    $empaque = $empaques[0] ?? null;

    $v = fn (?string $valor): string => filled($valor) ? $valor : '—';
@endphp

@if (! $hayFlogFiltro)
    <div class="flog-card p-8 text-center">
        <div class="mx-auto w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center mb-3">
            <i class="fa-solid fa-list-ol text-blue-500 text-sm"></i>
        </div>
        <p class="text-slate-700 text-sm font-semibold">Selecciona un Flog para ver la información</p>
        <p class="text-slate-400 text-xs mt-1">Usa el filtro <strong>Flog</strong> arriba.</p>
    </div>
@elseif (! $encontrado)
    <div class="flog-card p-8 text-center border-amber-200">
        <div class="mx-auto w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center mb-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-sm"></i>
        </div>
        <p class="text-slate-700 text-sm font-semibold">No se encontró el Flog en TI</p>
        <p class="text-slate-400 text-xs mt-1 font-mono">{{ $filtros['flog'] ?? '' }}</p>
    </div>
@else
    <div class="space-y-3">
        {{-- Dos tarjetas principales lado a lado --}}
        <div class="flog-grid-top">
            {{-- Información general --}}
            <section class="flog-card" aria-labelledby="flog-titulo-general">
                <header class="flog-card__head">
                    <span class="flog-card__icon"><i class="fa-solid fa-clipboard-list"></i></span>
                    <h2 id="flog-titulo-general" class="flog-card__title">Información general del proyecto</h2>
                </header>
                <div class="flog-card__body flog-card__body--2col">
                    <div class="flog-col">
                        <div class="flog-fila">
                            <span class="flog-fila__label">Folio compra especial</span>
                            <span class="flog-fila__valor flog-fila__valor--accent">{{ $v($general['idFlog'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Núm. proveedor</span>
                            <span class="flog-fila__valor">{{ $v($general['numProveedor'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Tipo de pedido</span>
                            <span class="flog-fila__valor">{{ $v($general['tipoPedido'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Categoría calidad</span>
                            <span class="flog-fila__valor">{{ $v($general['categoriaCalidad'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Pruebas laboratorio</span>
                            <span class="flog-fila__valor">
                                @if (filled($general['pruebasLab'] ?? null) && ($general['pruebasLab'] ?? '—') !== '—')
                                    <span class="flog-fila__valor--badge">{{ $general['pruebasLab'] }}</span>
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Proceso 100% Cadmex</span>
                            <span class="flog-fila__valor">{{ $v($general['procesoCatMex'] ?? null) }}</span>
                        </div>
                    </div>
                    <div class="flog-col">
                        <div class="flog-fila">
                            <span class="flog-fila__label">Cliente</span>
                            <span class="flog-fila__valor flog-fila__valor--accent">{{ $v($general['cliente'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Agente</span>
                            <span class="flog-fila__valor">{{ $v($general['agente'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Tipo de cliente</span>
                            <span class="flog-fila__valor">{{ $v($general['tipoClienteId'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Proyecto</span>
                            <span class="flog-fila__valor">{{ $v($general['nameProyect'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Suavizante exportación</span>
                            <span class="flog-fila__valor">{{ $v($general['twSuavizante'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Fecha creación Flog</span>
                            <span class="flog-fila__valor">{{ $v($general['transDate'] ?? null) }}</span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Empaque y etiquetado --}}
            <section class="flog-card" aria-labelledby="flog-titulo-empaque">
                <header class="flog-card__head">
                    <span class="flog-card__icon"><i class="fa-solid fa-box-open"></i></span>
                    <h2 id="flog-titulo-empaque" class="flog-card__title">Empaque y etiquetado</h2>
                </header>
                <div class="flog-card__body flog-card__body--2col">
                    <div class="flog-col">
                        <div class="flog-fila">
                            <span class="flog-fila__label">Id empaque</span>
                            <span class="flog-fila__valor">{{ $v($empaque['idEmpaque'] ?? null) }}</span>
                        </div>
                        <div class="flog-fila">
                            <span class="flog-fila__label">Etiquetas</span>
                            <span class="flog-fila__valor">{{ count($etiquetas) }}</span>
                        </div>
                        @if ($empaque && filled($empaque['otroEmpaque'] ?? null))
                            <div class="flog-fila" style="grid-template-columns: 1fr;">
                                <span class="flog-fila__label">Otro empaque</span>
                                <span class="flog-fila__valor text-left mt-0.5 whitespace-pre-line">{{ $empaque['otroEmpaque'] }}</span>
                            </div>
                        @endif
                        @if ($empaque && ! empty($empaque['imagenUrl']))
                            <div class="flog-empaque-img">
                                <img src="{{ $empaque['imagenUrl'] }}" alt="Empaque" loading="lazy">
                            </div>
                        @endif
                    </div>
                    <div class="flog-col">
                        @forelse ($etiquetas as $etiq)
                            <div class="flog-fila" style="grid-template-columns: 1fr;">
                                <span class="flog-fila__label">{{ $etiq['comentarios'] ?: ($etiq['name'] ?: 'Etiqueta') }}</span>
                                @if (filled($etiq['itemId'] ?? null) || filled($etiq['name'] ?? null))
                                    <span class="flog-fila__valor text-left mt-0.5">
                                        {{ trim(($etiq['itemId'] ?? '').' '.($etiq['name'] ?? '')) ?: '—' }}
                                    </span>
                                @endif
                            </div>
                        @empty
                            <div class="flog-fila">
                                <span class="flog-fila__label">Etiquetas</span>
                                <span class="flog-fila__valor text-slate-400">Sin registros</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        @if (! empty($etiquetas))
            <section class="flog-card" aria-labelledby="flog-titulo-etiq-img">
                <header class="flog-card__head">
                    <span class="flog-card__icon"><i class="fa-solid fa-tags"></i></span>
                    <h2 id="flog-titulo-etiq-img" class="flog-card__title">Imágenes de etiquetas</h2>
                </header>
                <div class="p-2">
                    <div class="flog-etiq-grid">
                        @foreach ($etiquetas as $etiq)
                            <article class="flog-etiq-item">
                                @if (! empty($etiq['imagenUrl']))
                                    <div class="flog-etiq-item__img">
                                        <img src="{{ $etiq['imagenUrl'] }}"
                                             alt="{{ $etiq['comentarios'] ?: 'Etiqueta' }}"
                                             loading="lazy">
                                    </div>
                                @endif
                                <p class="flog-etiq-item__txt">{{ $etiq['comentarios'] ?: ($etiq['name'] ?: '—') }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (filled($general['avisoEspecialTxt'] ?? null))
            <div class="flog-nota flog-nota--amber">
                <span class="text-[0.5625rem] font-bold uppercase tracking-wide text-amber-700 block mb-1">Aviso especial</span>
                {{ $general['avisoEspecialTxt'] }}
            </div>
        @endif

        @if (filled($general['infoImportante'] ?? null))
            <div class="flog-nota flog-nota--slate">
                <span class="text-[0.5625rem] font-bold uppercase tracking-wide text-slate-600 block mb-1">Información importante</span>
                {{ $general['infoImportante'] }}
            </div>
        @endif
    </div>
@endif
