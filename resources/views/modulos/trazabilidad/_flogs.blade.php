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

        {{-- Empaque + etiquetas: meta compacta a la izquierda, galería grande a la derecha --}}
        @php
            $imagenesFlog = [];
            if ($empaque && ! empty($empaque['imagenUrl'])) {
                $imagenesFlog[] = [
                    'url' => $empaque['imagenUrl'],
                    'caption' => 'Empaque — '.($empaque['idEmpaque'] ?? 'Imagen'),
                ];
            }
            foreach ($etiquetas as $etiq) {
                if (! empty($etiq['imagenUrl'])) {
                    $imagenesFlog[] = [
                        'url' => $etiq['imagenUrl'],
                        'caption' => $etiq['comentarios'] ?: ($etiq['name'] ?: 'Etiqueta'),
                    ];
                }
            }
            $soloUnaImagen = count($imagenesFlog) === 1;
        @endphp

        <section class="flog-card" aria-labelledby="flog-titulo-visual">
            <header class="flog-card__head">
                <span class="flog-card__icon"><i class="fa-solid fa-box-open"></i></span>
                <h2 id="flog-titulo-visual" class="flog-card__title">Empaque y etiquetado</h2>
            </header>
            <div class="flog-card__body flog-visual-layout">
                <aside class="flog-visual-meta">
                    <div class="flog-campo">
                        <span class="flog-campo__label">Id empaque</span>
                        <span class="flog-campo__valor">{{ $v($empaque['idEmpaque'] ?? null) }}</span>
                    </div>
                    <div class="flog-campo">
                        <span class="flog-campo__label">Etiquetas registradas</span>
                        <span class="flog-campo__valor">{{ count($etiquetas) }}</span>
                    </div>
                    @if ($empaque && filled($empaque['otroEmpaque'] ?? null))
                        <div class="flog-campo">
                            <span class="flog-campo__label">Otro empaque</span>
                            <span class="flog-campo__valor whitespace-pre-line">{{ $empaque['otroEmpaque'] }}</span>
                        </div>
                    @endif
                    @if (filled($general['infoImportante'] ?? null))
                        <div class="flog-meta-nota">
                            <span class="flog-meta-nota__titulo">Información importante</span>
                            {{ $general['infoImportante'] }}
                        </div>
                    @endif
                    @if (filled($general['avisoEspecialTxt'] ?? null))
                        <div class="flog-meta-nota flog-meta-nota--verde">
                            <span class="flog-meta-nota__titulo">Aviso especial</span>
                            {{ $general['avisoEspecialTxt'] }}
                        </div>
                    @endif
                </aside>

                @if (! empty($imagenesFlog))
                    <div class="flog-visual-gallery {{ $soloUnaImagen ? 'flog-visual-gallery--solo' : '' }}">
                        @foreach ($imagenesFlog as $img)
                            <figure class="flog-visual-frame" data-flog-zoom="{{ $img['url'] }}" role="button" tabindex="0" aria-label="{{ $img['caption'] }}">
                                <div class="flog-visual-frame__img-wrap">
                                    <img src="{{ $img['url'] }}" alt="{{ $img['caption'] }}" loading="lazy">
                                </div>
                                <figcaption class="flog-visual-frame__caption">{{ $img['caption'] }}</figcaption>
                                <div class="flog-visual-frame__zoom-hint" aria-hidden="true">
                                    <span><i class="fa-solid fa-magnifying-glass-plus mr-1"></i> Clic para tamaño completo</span>
                                </div>
                            </figure>
                        @endforeach
                    </div>
                @else
                    <div class="flog-visual-placeholder" role="presentation" aria-hidden="true">
                        <div class="flog-visual-placeholder__frame">
                            <span class="flog-visual-placeholder__icon">
                                <i class="fa-regular fa-image"></i>
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endif
