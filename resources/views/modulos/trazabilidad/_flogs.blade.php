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
    <div class="flog-card p-10 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center mb-3">
            <i class="fa-solid fa-list-ol text-blue-500 text-lg"></i>
        </div>
        <p class="text-slate-800 text-base font-semibold">Selecciona un Flog para ver la información</p>
        <p class="text-slate-500 text-sm mt-1">Usa el filtro <strong>Flog</strong> en la barra superior.</p>
    </div>
@elseif (! $encontrado)
    <div class="flog-card p-10 text-center border-amber-300">
        <div class="mx-auto w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mb-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg"></i>
        </div>
        <p class="text-slate-800 text-base font-semibold">No se encontró el Flog en TI</p>
        <p class="text-slate-500 text-sm mt-1 font-mono">{{ $filtros['flog'] ?? '' }}</p>
    </div>
@else
    <div class="flog-wrap">
        {{-- Información general — una tarjeta, grid denso label arriba / valor abajo --}}
        <section class="flog-card" aria-labelledby="flog-titulo-general">
            <header class="flog-card__head">
                <span class="flog-card__icon"><i class="fa-solid fa-clipboard-list"></i></span>
                <h2 id="flog-titulo-general" class="flog-card__title">Información general del proyecto</h2>
            </header>
            <div class="flog-card__body">
                <div class="flog-fields">
                    <div class="flog-campo">
                        <span class="flog-campo__label">Folio compra especial</span>
                        <span class="flog-campo__valor flog-campo__valor--accent">{{ $v($general['idFlog'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Cliente</span>
                        <span class="flog-campo__valor flog-campo__valor--accent">{{ $v($general['cliente'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Agente</span>
                        <span class="flog-campo__valor">{{ $v($general['agente'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Tipo de pedido</span>
                        <span class="flog-campo__valor">{{ $v($general['tipoPedido'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Tipo de cliente</span>
                        <span class="flog-campo__valor">{{ $v($general['tipoClienteId'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Categoría calidad</span>
                        <span class="flog-campo__valor">{{ $v($general['categoriaCalidad'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Pruebas laboratorio</span>
                        @if (filled($general['pruebasLab'] ?? null) && ($general['pruebasLab'] ?? '—') !== '—')
                            <span class="flog-campo__valor flog-campo__valor--badge">{{ $general['pruebasLab'] }}</span>
                        @else
                            <span class="flog-campo__valor">—</span>
                        @endif
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Proceso 100% Cadmex</span>
                        <span class="flog-campo__valor">{{ $v($general['procesoCatMex'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo flog-campo--half">
                        <span class="flog-campo__label">Proyecto</span>
                        <span class="flog-campo__valor">{{ $v($general['nameProyect'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Núm. proveedor</span>
                        <span class="flog-campo__valor">{{ $v($general['numProveedor'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Suavizante exportación</span>
                        <span class="flog-campo__valor">{{ $v($general['twSuavizante'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Fecha creación Flog</span>
                        <span class="flog-campo__valor">{{ $v($general['transDate'] ?? null) }}</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Empaque + etiquetas con imágenes grandes --}}
        <div class="flog-grid-2 flog-grid-2--empaque">
            <section class="flog-card" aria-labelledby="flog-titulo-empaque">
                <header class="flog-card__head">
                    <span class="flog-card__icon"><i class="fa-solid fa-box-open"></i></span>
                    <h2 id="flog-titulo-empaque" class="flog-card__title">Empaque</h2>
                </header>
                <div class="flog-card__body">
                    <div class="flog-fields" style="grid-template-columns: 1fr 1fr;">
                        <div class="flog-campo">
                            <span class="flog-campo__label">Id empaque</span>
                            <span class="flog-campo__valor">{{ $v($empaque['idEmpaque'] ?? null) }}</span>
                        </div>
                        <div class="flog-campo">
                            <span class="flog-campo__label">Etiquetas registradas</span>
                            <span class="flog-campo__valor">{{ count($etiquetas) }}</span>
                        </div>
                    </div>
                    @if ($empaque && filled($empaque['otroEmpaque'] ?? null))
                        <div class="flog-campo mt-3">
                            <span class="flog-campo__label">Otro empaque</span>
                            <span class="flog-campo__valor whitespace-pre-line font-semibold">{{ $empaque['otroEmpaque'] }}</span>
                        </div>
                    @endif
                    @if ($empaque && ! empty($empaque['imagenUrl']))
                        <div class="flog-empaque-img" data-flog-zoom="{{ $empaque['imagenUrl'] }}" title="Clic para ampliar">
                            <img src="{{ $empaque['imagenUrl'] }}" alt="Empaque" loading="lazy">
                        </div>
                    @elseif (! $empaque)
                        <p class="flog-empty-img">Sin datos de empaque.</p>
                    @endif
                </div>
            </section>

            <section class="flog-card" aria-labelledby="flog-titulo-etiq">
                <header class="flog-card__head">
                    <span class="flog-card__icon"><i class="fa-solid fa-tags"></i></span>
                    <h2 id="flog-titulo-etiq" class="flog-card__title">Etiquetas</h2>
                </header>
                <div class="flog-card__body">
                    @if (empty($etiquetas))
                        <p class="flog-empty-img">Sin líneas de etiquetado.</p>
                    @else
                        <div class="flog-etiq-grid">
                            @foreach ($etiquetas as $etiq)
                                <article class="flog-etiq-item">
                                    @if (! empty($etiq['imagenUrl']))
                                        <div class="flog-etiq-item__img" data-flog-zoom="{{ $etiq['imagenUrl'] }}" title="Clic para ampliar">
                                            <img src="{{ $etiq['imagenUrl'] }}"
                                                 alt="{{ $etiq['comentarios'] ?: 'Etiqueta' }}"
                                                 loading="lazy">
                                        </div>
                                    @else
                                        <div class="flog-etiq-item__img flog-empty-img">Sin imagen</div>
                                    @endif
                                    <p class="flog-etiq-item__txt">{{ $etiq['comentarios'] ?: ($etiq['name'] ?: '—') }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

        @if (filled($general['avisoEspecialTxt'] ?? null) || filled($general['infoImportante'] ?? null))
            <div class="flog-grid-2">
                @if (filled($general['avisoEspecialTxt'] ?? null))
                    <div class="flog-nota flog-nota--amber">
                        <span class="flog-nota__titulo">Aviso especial</span>
                        {{ $general['avisoEspecialTxt'] }}
                    </div>
                @endif
                @if (filled($general['infoImportante'] ?? null))
                    <div class="flog-nota flog-nota--slate {{ filled($general['avisoEspecialTxt'] ?? null) ? '' : 'lg:col-span-2' }}">
                        <span class="flog-nota__titulo">Información importante</span>
                        {{ $general['infoImportante'] }}
                    </div>
                @endif
            </div>
        @endif
    </div>
@endif
