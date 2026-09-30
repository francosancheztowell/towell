@extends('layouts.app')

@php
    $esDuplicado = $esDuplicado ?? false;
    $esEdicion = isset($codificacion) && ! empty($codificacion->Id);
    $tituloPagina = $esDuplicado ? 'Duplicar modelo' : ($esEdicion ? 'Editar modelo' : 'Nuevo modelo');
    $textoSubmit = $esEdicion ? 'Actualizar' : 'Crear';
@endphp

@section('page-title', $tituloPagina)

@section('navbar-right')
<div class="flex items-center gap-2">
    <span id="cod-dirty" hidden class="inline-flex items-center px-2 py-1 rounded text-xs font-medium text-amber-800 bg-amber-100">Sin guardar</span>
    <button type="button" id="cod-traer-similar"
            class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 font-semibold text-sm shadow-sm">
        <i class="fas fa-file-import" aria-hidden="true"></i>
        Traer modelo
    </button>
    <button type="button" id="cod-cancelar"
       class="cursor-pointer inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm">
        Cancelar
    </button>
    <button type="submit" form="codificacion-form" id="cod-submit"
            class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm disabled:opacity-60 disabled:cursor-not-allowed">
        <i id="cod-submit-spin" class="fas fa-spinner fa-spin" hidden aria-hidden="true"></i>
        <span id="submit-text">{{ $textoSubmit }}</span>
    </button>
</div>
@endsection

@section('content')
@php
    $esDuplicado = $esDuplicado ?? false;
    $esEdicion = isset($codificacion) && ! empty($codificacion->Id);

    $valorCampo = function (string $name, array $opts = []) use ($codificacion) {
        $value = old($name, null);
        if ($value === null && isset($codificacion)) {
            $attributes = $codificacion->getAttributes();
            if (array_key_exists($name, $attributes)) {
                $value = $attributes[$name];
            }
        }
        if ($value === null || $value === '') {
            $value = $opts['value'] ?? '';
        }

        $type = $opts['type'] ?? 'text';
        if ($type === 'date' && ! empty($value) && $value !== '0000-00-00') {
            try {
                $value = \Carbon\Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e) {
                $value = '';
            }
        }
        if ($type === 'number' && is_numeric($value) && str_contains((string) $value, '.')) {
            $value = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        }
        if (! empty($opts['ceroEsVacio']) && in_array(trim((string) $value), ['0', '0.0', '0.00'], true)) {
            $value = '';
        }

        return $value;
    };

    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $render = function ($name, $label, $opts = []) use ($valorCampo, $errorBag) {
        $type = $opts['type'] ?? 'text';
        $step = $opts['step'] ?? null;
        $required = $opts['required'] ?? false;
        $placeholder = $opts['placeholder'] ?? '';
        $isSelect = $opts['select'] ?? false;
        $selectId = $opts['selectId'] ?? '';
        $role = $opts['role'] ?? 'normal';
        $hint = $opts['hint'] ?? '';
        $bare = $opts['bare'] ?? false;
        $dup = $opts['dup'] ?? false;
        $oculto = $opts['hidden'] ?? false;
        $value = $valorCampo($name, $opts);
        $id = $opts['id'] ?? ('f-'.$name);
        $error = $errorBag->first($name) ?: '';
        if ($oculto) {
            echo '<input type="hidden" id="'.e($id).'" name="'.e($name).'" value="'.e((string) $value).'" />';

            return;
        }

        $req = $required ? 'required' : '';
        $classes = 'cod-input tab-navigation';
        if ($role === 'primary') {
            $classes .= ' cod-input--primary';
        }
        if ($role === 'derived') {
            $classes .= ' cod-input--derived';
        }
        if ($error) {
            $classes .= ' cod-input--error';
        }
        if ($dup) {
            $classes .= ' cod-input--dup';
        }

        $safeName = e($name);
        $safeId = e($id);
        $safeValue = e((string) $value);
        $safePlaceholder = e($placeholder);
        $stepAttr = $step ? ' step="'.e($step).'"' : '';
        $roleAttr = ' data-role="'.e($role).'"';
        $readonlyAttr = $role === 'derived' ? ' readonly' : '';
        $ariaLabel = $bare ? ' aria-label="'.e($label).'"' : '';
        $inputmodeAttr = ! empty($opts['inputmode']) ? ' inputmode="'.e($opts['inputmode']).'"' : '';
        $patternAttr = ! empty($opts['pattern']) ? ' pattern="'.e($opts['pattern']).'"' : '';
        $hintId = $hint ? $safeId.'-hint' : '';
        $described = $hint ? ' aria-describedby="'.$hintId.'"' : '';

        if ($isSelect) {
            $selectAttr = $selectId ? ' id="'.e($selectId).'"' : ' id="'.$safeId.'"';
            $control = "<select {$req} {$selectAttr} name=\"{$safeName}\" class=\"{$classes}\"{$roleAttr}{$described}{$ariaLabel}>"
                .'<option value="">Seleccionar...</option>';
            $opciones = $opts['options'] ?? [];
            foreach ($opciones as $opcion) {
                $sel = (string) $opcion === (string) $value ? ' selected' : '';
                $control .= '<option value="'.e($opcion).'"'.$sel.'>'.e($opcion).'</option>';
            }
            // Un valor guardado fuera de la lista (captura vieja: 'NAC-1', '0') se conserva.
            if ($value !== '' && $value !== null && ! in_array((string) $value, array_map('strval', $opciones), true)) {
                $control .= '<option value="'.$safeValue.'" selected>'.$safeValue.'</option>';
            }
            $control .= '</select>';
        } else {
            $control = "<input {$req}{$stepAttr}{$readonlyAttr}{$inputmodeAttr}{$patternAttr} type=\"".e($type)."\" id=\"{$safeId}\" name=\"{$safeName}\" value=\"{$safeValue}\" placeholder=\"{$safePlaceholder}\" class=\"{$classes}\"{$roleAttr}{$described}{$ariaLabel} />";
        }

        if ($bare) {
            echo $control;
            if ($error) {
                echo '<p class="cod-error" data-error-for="'.$safeName.'">'.e($error).'</p>';
            }

            return;
        }

        echo '<div class="cod-field" data-field="'.$safeName.'">';
        echo '<label class="cod-label" for="'.($selectId ? e($selectId) : $safeId).'">'.e($label);
        if ($required) {
            echo ' <span class="cod-req" title="Obligatorio">*</span>';
        }
        echo '</label>';
        echo $control;
        if ($hint) {
            echo '<p id="'.$hintId.'" class="cod-hint">'.e($hint).'</p>';
        }
        echo '<p class="cod-error" data-error-for="'.$safeName.'"'.($error ? '' : ' hidden').'>'.e($error).'</p>';
        echo '</div>';
    };

    $salonActual = old('SalonTejidoId', $codificacion?->SalonTejidoId ?? '');
    $telarActual = old('NoTelarId', $codificacion?->NoTelarId ?? '');
    $esKarlMayer = \App\Support\Planeacion\TelarSalonResolver::esKarlMayer($salonActual, $telarActual);
    $salonElegido = trim((string) $salonActual) !== '' || trim((string) $telarActual) !== '';

    $nombreMostrar = old('Nombre', $codificacion?->Nombre ?? '');
    $claveMostrar = old('TamanoClave', $codificacion?->TamanoClave ?? '');
    $flogMostrar = old('FlogsId', $codificacion?->FlogsId ?? '');
    $itemMostrar = old('ItemId', $codificacion?->ItemId ?? '');
    $sizeMostrar = old('InventSizeId', $codificacion?->InventSizeId ?? '');
    $idMostrar = $esEdicion ? (string) $codificacion->Id : '';

    $barrasVacias = $esKarlMayer && collect([1, 2, 3, 4])->every(function ($n) use ($valorCampo) {
        return trim((string) $valorCampo("CuentaBarra{$n}")) === ''
            && trim((string) $valorCampo("CalibreBarra{$n}")) === ''
            && trim((string) $valorCampo("FibraBarra{$n}")) === '';
    });
    $rizoSobrante = $esKarlMayer && $barrasVacias && (
        trim((string) $valorCampo('CuentaRizo')) !== ''
        || trim((string) $valorCampo('CuentaPie')) !== ''
    );

    $salonChip = match (true) {
        $esKarlMayer => ['KARL MAYER', 'cod-chip--km'],
        $salonElegido && str_contains(strtoupper((string) $salonActual), 'JAC') => [$salonActual ?: 'JACQUARD', 'cod-chip--jac'],
        $salonElegido => [$salonActual ?: 'SMIT', 'cod-chip--smit'],
        default => ['Sin salón', 'cod-chip--none'],
    };
@endphp

<div class="cod-page">
    <div class="cod-identity" id="cod-identity">
        <div class="cod-identity__main">
            <div class="cod-identity__title">
                <p class="cod-identity__nombre" id="cod-id-nombre">{{ $nombreMostrar !== '' ? $nombreMostrar : 'Sin nombre' }}</p>
                <p class="cod-identity__meta">
                    <span id="cod-id-clave">{{ $claveMostrar !== '' ? $claveMostrar : '—' }}</span>
                    @if($idMostrar !== '')
                        <span aria-hidden="true">·</span>
                        <span class="text-gray-400">#{{ $idMostrar }}</span>
                    @endif
                    <span id="cod-id-item-wrap" @if(trim($itemMostrar.' '.$sizeMostrar) === '') hidden @endif>
                        <span aria-hidden="true">·</span>
                        <span id="cod-id-item">{{ trim($itemMostrar.' '.$sizeMostrar) }}</span>
                    </span>
                    <span aria-hidden="true">·</span>
                    <span id="cod-id-flog">{{ $flogMostrar !== '' ? $flogMostrar : 'Sin flog' }}</span>
                </p>
            </div>
            <div class="cod-identity__tags">
                <span class="cod-chip {{ $salonChip[1] }}" id="cod-id-salon">{{ $salonChip[0] }}</span>
                <span class="cod-chip {{ ($esKarlMayer && trim((string) $telarActual) === '') ? 'cod-chip--warn' : '' }}" id="cod-id-telar">
                    Telar {{ $telarActual !== '' && $telarActual !== null ? $telarActual : '—' }}
                </span>
            </div>
        </div>
    </div>

    @if($esDuplicado)
        <div class="cod-banner" role="status">
            Duplicado de un modelo existente. Cambia <strong>Clave AX</strong>, <strong>Tamaño</strong>, <strong>Flog</strong>, <strong>Nombre</strong> y <strong>Pedido</strong> — el resto ya viene copiado.
        </div>
    @endif

    @if($esKarlMayer && trim((string) $telarActual) === '')
        <div class="cod-banner cod-banner--warn" role="status">
            Este modelo Karl Mayer no tiene telar. Los telares de este salón son <strong>401</strong> y <strong>402</strong>.
        </div>
    @endif

    @if($rizoSobrante)
        <div class="cod-banner cod-banner--warn" role="status">
            Las barras están vacías, pero este registro todavía tiene cuenta en rizo
            ({{ $valorCampo('CuentaRizo') ?: '—' }}) y pie ({{ $valorCampo('CuentaPie') ?: '—' }}).
            Eso era la captura vieja: cópiala a las cuatro barras.
        </div>
    @endif

    <form id="codificacion-form" class="cod-form" autocomplete="off" novalidate>
        @csrf
        <input type="hidden" id="codificacion-id" name="Id" value="{{ $esEdicion ? $codificacion->Id : '' }}">

        @if(isset($codificacion))
            <script>
                window.codificacionData = @json($codificacion->getAttributes() ?? []);
            </script>
        @endif

        <section class="cod-section" id="sec-identificacion">
            <div class="cod-grid cod-grid--id">
                @php
                    $render('SalonTejidoId', 'Salón', ['select' => true, 'selectId' => 'salon-tejido-select', 'required' => true, 'role' => 'primary']);
                    $render('NoTelarId', 'No. Telar', ['select' => true, 'selectId' => 'no-telar-select', 'role' => 'primary']);
                @endphp
                <div class="cod-id-resto" data-need-salon @unless($salonElegido) hidden @endunless>
                    @php
                        $render('ItemId', 'Clave AX', ['required' => true, 'role' => 'primary']);
                        $render('InventSizeId', 'Tamaño', ['required' => true, 'role' => 'primary']);
                        $render('TamanoClave', 'Tamaño Clave', ['role' => 'derived', 'dup' => $esDuplicado, 'hint' => 'Tamaño + Clave AX']);
                        $render('ClaveModelo', 'Clave Modelo', ['role' => 'derived', 'hint' => 'Tamaño + Clave AX']);
                        $render('Nombre', 'Nombre', ['dup' => $esDuplicado]);
                        $render('CodigoDibujo', 'Código Dibujo');
                        $render('FlogsId', 'Flogs ID', ['dup' => $esDuplicado, 'hint' => 'Si hay Clave AX y Tamaño, se busca solo']);
                        $render('NombreProyecto', 'Nombre Proyecto');
                        $render('Clave', 'Clave');
                    @endphp
                </div>
            </div>
        </section>

        <section class="cod-section" id="sec-fechas" data-need-salon @unless($salonElegido) hidden @endunless>
            <header class="cod-section__head">
                <h2>Comercial</h2>
            </header>
            <div class="cod-grid">
                @php
                    $render('Prioridad', 'Prioridad');
                    $render('Tolerancia', 'Tolerancia');
                    $render('Vendedor', 'Vendedor');
                    $render('CatCalidad', 'Cat. Calidad', ['select' => true, 'options' => ['NAC - 1', 'NAC - 2', 'NAC - 3']]);
                    $render('Obs5', 'Observaciones comerciales', ['ceroEsVacio' => true]);
                @endphp
            </div>
        </section>

        <section class="cod-section" id="sec-medidas" data-need-salon @unless($salonElegido) hidden @endunless>
            <header class="cod-section__head">
                <h2>Medidas y producción</h2>
            </header>
            <div class="cod-grid">
                @php
                    $render('Pedido', 'Pedido', ['type' => 'number', 'step' => '0.0001', 'dup' => $esDuplicado]);
                    $render('Peine', 'Peine', ['type' => 'number']);
                    $render('AnchoToalla', 'Ancho Toalla', ['type' => 'number']);
                    $render('LargoToalla', 'Largo Toalla', ['type' => 'number']);
                    $render('PesoCrudo', 'Peso Crudo', ['type' => 'number']);
                    $render('Luchaje', 'Luchaje', ['type' => 'number']);
                    $render('NoTiras', 'No. Tiras', ['type' => 'number']);
                    $render('MedidaPlano', 'Medida plano', ['type' => 'number']);
                    $render('DobladilloId', 'Tipo plano');
                    $render('Rasurado', 'Rasurada');
                    $render('CambioRepaso', 'Cambio de repaso');
                    $render('VelocidadSTD', 'Velocidad STD', ['type' => 'number', 'role' => 'derived', 'hint' => 'Del catálogo de velocidades (telar + fibra)']);
                @endphp
            </div>
        </section>

        <section class="cod-section" id="sec-trama" data-solo-salon="std" @unless($salonElegido && ! $esKarlMayer) hidden @endunless>
            <header class="cod-section__head">
                <h2>Trama</h2>
                <p>Solo Jacquard y Smit. Karl Mayer no usa trama ni combinaciones.</p>
            </header>
            <div class="cod-grid">
                @php
                    $render('CalibreTrama', 'Calibre Trama', ['type' => 'number', 'step' => '0.0001']);
                    $render('CalibreTrama2', 'Calibre Trama 2', ['type' => 'number', 'step' => '0.0001']);
                    $render('CodColorTrama', 'Cód. color trama');
                    $render('ColorTrama', 'Color trama');
                    $render('FibraId', 'Fibra ID');
                    $render('AnchoPeineTrama', 'Ancho peine trama', ['type' => 'number']);
                    $render('LogLuchaTotal', 'Log. de lucha total', ['type' => 'number']);
                @endphp
            </div>
        </section>

        <div id="sec-construccion">
            <section class="cod-section cod-section--km" data-solo-salon="km" @unless($salonElegido && $esKarlMayer) hidden @endunless>
                <header class="cod-section__head">
                    <h2>Construcción · cuatro barras</h2>
                </header>
                <div class="cod-table-wrap">
                    <table class="cod-table">
                        <thead>
                            <tr>
                                <th scope="col">Barra</th>
                                <th scope="col">Cuenta</th>
                                <th scope="col">Calibre</th>
                                <th scope="col">Cód. color</th>
                                <th scope="col">Color</th>
                                <th scope="col">Fibra</th>
                                <th scope="col">Pasadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([1, 2, 3, 4] as $n)
                                @php
                                    $barraVacia = collect(["CuentaBarra{$n}", "CalibreBarra{$n}", "CodColorBarra{$n}", "ColorBarra{$n}", "FibraBarra{$n}", "PasadasBarra{$n}"])
                                        ->every(fn ($campo) => trim((string) $valorCampo($campo, $campo === "PasadasBarra{$n}" ? ['type' => 'number'] : [])) === '');
                                @endphp
                                <tr class="{{ $barraVacia ? 'cod-table__empty' : '' }}">
                                    <th scope="row">
                                        <span class="cod-bar-num">{{ $n }}</span>
                                        @if($barraVacia)
                                            <span class="cod-bar-empty">sin capturar</span>
                                        @endif
                                    </th>
                                    <td>@php $render("CuentaBarra{$n}", 'Cuenta', ['bare' => true]); @endphp</td>
                                    <td>@php $render("CalibreBarra{$n}", 'Calibre', ['bare' => true]); @endphp</td>
                                    <td>@php $render("CodColorBarra{$n}", 'Cód. color', ['bare' => true]); @endphp</td>
                                    <td>@php $render("ColorBarra{$n}", 'Color', ['bare' => true]); @endphp</td>
                                    <td>@php $render("FibraBarra{$n}", 'Fibra', ['bare' => true]); @endphp</td>
                                    <td>@php $render("PasadasBarra{$n}", 'Pasadas', ['bare' => true, 'type' => 'number']); @endphp</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="cod-section" data-solo-salon="std" @unless($salonElegido && ! $esKarlMayer) hidden @endunless>
                <header class="cod-section__head">
                    <h2>Construcción · rizo, pie y cenefa</h2>
                    <p>Jacquard y Smit no usan barras. Las combinaciones vacías no se usan.</p>
                </header>
                <div class="cod-const-std">
                    <div class="cod-card">
                        <h3>Rizo</h3>
                        <div class="cod-grid cod-grid--2">
                            @php
                                $render('TipoRizo', 'Tipo de rizo');
                                $render('AlturaRizo', 'Altura de rizo');
                                $render('CuentaRizo', 'Cuenta');
                                $render('CalibreRizo', 'Calibre', ['type' => 'number', 'step' => '0.0001']);
                                $render('CalibreRizo2', 'Calibre 2', ['type' => 'number', 'step' => '0.0001']);
                                $render('FibraRizo', 'Fibra');
                            @endphp
                        </div>
                    </div>
                    <div class="cod-card">
                        <h3>Pie</h3>
                        <div class="cod-grid cod-grid--2">
                            @php
                                $render('CuentaPie', 'Cuenta');
                                $render('CalibrePie', 'Calibre', ['type' => 'number', 'step' => '0.0001']);
                                $render('CalibrePie2', 'Calibre 2', ['type' => 'number', 'step' => '0.0001']);
                                $render('FibraPie', 'Fibra');
                            @endphp
                        </div>
                    </div>
                </div>
                <div class="cod-grid mt-3">
                    @php
                        $render('MedidaCenefa', 'Med. de cenefa');
                        $render('MedIniRizoCenefa', 'Med. inicio rizo a cenefa');
                    @endphp
                </div>

                <h3 class="cod-sub">Combinaciones C1–C5</h3>
                <p class="cod-section__note">Comb vacío = esa combinación no se usa. C5 no tiene bandera Comb.</p>
                <div class="cod-table-wrap">
                    <table class="cod-table">
                        <thead>
                            <tr>
                                <th scope="col">C</th>
                                <th scope="col">Comb</th>
                                <th scope="col">Obs</th>
                                <th scope="col">Calibre</th>
                                <th scope="col">Hilo</th>
                                <th scope="col">Fibra</th>
                                <th scope="col">Cód. color</th>
                                <th scope="col">Nombre color</th>
                                <th scope="col">Pasadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([1, 2, 3, 4, 5] as $n)
                                <tr>
                                    <th scope="row"><span class="cod-bar-num">{{ $n }}</span></th>
                                    <td>
                                        @if($n < 5)
                                            @php $render("Comb{$n}", "C{$n}", ['bare' => true, 'ceroEsVacio' => true]); @endphp
                                        @else
                                            <span class="cod-na">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($n < 5)
                                            @php $render("Obs{$n}", "Obs C{$n}", ['bare' => true, 'ceroEsVacio' => true]); @endphp
                                        @else
                                            <span class="cod-na">—</span>
                                        @endif
                                    </td>
                                    <td>@php $render("CalibreComb{$n}", "C{$n}", ['bare' => true, 'type' => 'number', 'step' => '0.0001']); @endphp</td>
                                    <td>@php $render("CalibreComb{$n}2", "Hilo C{$n}", ['bare' => true, 'type' => 'number', 'step' => '0.0001']); @endphp</td>
                                    <td>@php $render("FibraComb{$n}", "OBS C{$n}", ['bare' => true]); @endphp</td>
                                    <td>@php $render("CodColorC{$n}", "Cod Color C{$n}", ['bare' => true]); @endphp</td>
                                    <td>@php $render("NomColorC{$n}", "Nombre Color C{$n}", ['bare' => true]); @endphp</td>
                                    <td>@php $render("PasadasComb{$n}", "Pasadas C{$n}", ['bare' => true, 'type' => 'number']); @endphp</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3 class="cod-sub">Trama de fondo C1</h3>
                <div class="cod-grid">
                    @php
                        $render('CalTramaFondoC1', 'C1 trama de fondo', ['type' => 'number', 'step' => '0.0001']);
                        $render('CalTramaFondoC12', 'Hilo fondo C1', ['type' => 'number', 'step' => '0.0001']);
                        $render('FibraTramaFondoC1', 'OBS fondo C1');
                        $render('PasadasTramaFondoC1', 'Pasadas fondo C1', ['type' => 'number']);
                    @endphp
                </div>
            </section>
        </div>

        <section class="cod-section" id="sec-obs" data-need-salon @unless($salonElegido) hidden @endunless>
            <header class="cod-section__head">
                <h2>Observaciones</h2>
            </header>
            <div class="cod-grid cod-grid--full">
                @php $render('Obs', 'Observaciones', ['ceroEsVacio' => true]); @endphp
            </div>
        </section>

        <section class="cod-section" id="sec-metricas" data-need-salon @unless($salonElegido) hidden @endunless>
            <details class="cod-details">
                <summary>
                    <span>Métricas y cálculo</span>
                </summary>
                <div class="cod-grid mt-3">
                    @php
                        $hintCalc = 'Se recalcula en el programa de tejido';
                        $render('Repeticiones', 'Repeticiones', ['type' => 'number', 'role' => 'derived', 'hint' => $hintCalc]);
                        $render('TotalMarbetes', 'Total Marbetes', ['type' => 'number', 'role' => 'derived', 'hint' => $hintCalc]);
                        $render('Total', 'Total', ['type' => 'number', 'step' => '0.0001', 'role' => 'derived', 'hint' => $hintCalc]);
                        $render('Densidad', 'Densidad', ['type' => 'number', 'step' => '0.0001', 'role' => 'derived', 'hint' => $hintCalc]);
                        $render('KGDia', 'KG/Día', ['type' => 'number', 'step' => '0.0001', 'role' => 'derived', 'hint' => $hintCalc]);
                        $render('PzasDiaPasadas', 'Pzas/Día/pasadas', ['type' => 'number', 'step' => '0.0001', 'role' => 'derived', 'hint' => $hintCalc]);
                        $render('PzasDiaFormula', 'Pzas/Día/fórmula', ['type' => 'number', 'step' => '0.0001', 'role' => 'derived', 'hint' => $hintCalc]);
                        $render('TIRAS', 'TIRAS', ['type' => 'number', 'step' => '0.0001', 'role' => 'derived']);
                        $render('PASADAS', 'PASADAS', ['type' => 'number', 'step' => '0.0001', 'role' => 'derived']);
                        $render('Contraccion', 'Contracción');
                        $render('TramasCMTejido', 'Tramas cm/Tejido');
                        $render('ContracRizo', 'Contrac. Rizo');
                        $render('ClasificacionKG', 'Clasificación (KG)');
                        $render('DIF', 'DIF', ['type' => 'number', 'step' => '0.0001']);
                        $render('EFIC', 'EFIC', ['type' => 'number', 'step' => '0.0001']);
                        $render('Rev', 'Rev', ['type' => 'number', 'step' => '0.0001']);
                    @endphp
                </div>
            </details>
        </section>
    </form>
</div>

<div id="cod-modal-similar" class="cod-modal" hidden>
    <div class="cod-modal__box" role="dialog" aria-modal="true" aria-labelledby="cod-modal-similar-title">
        <div class="cod-modal__head">
            <h2 id="cod-modal-similar-title">Traer modelo</h2>
            <button type="button" id="cod-modal-cerrar" class="cod-modal__x" aria-label="Cerrar">&times;</button>
        </div>
        <fieldset class="cod-modal__set">
            <legend>De dónde</legend>
            <div class="cod-modal__ops">
                <label class="cod-choice">
                    <input type="radio" name="cod-origen" value="req" checked>
                    <span>ModelosCodificados</span>
                </label>
                <label class="cod-choice">
                    <input type="radio" name="cod-origen" value="cat">
                    <span>Codificacion</span>
                </label>
            </div>
        </fieldset>
        <fieldset class="cod-modal__set">
            <legend>Cómo</legend>
            <div class="cod-modal__ops">
                <label class="cod-choice">
                    <input type="radio" name="cod-por" value="orden" checked>
                    <span>Orden tejido</span>
                </label>
                <label class="cod-choice">
                    <input type="radio" name="cod-por" value="clave">
                    <span>Clave modelo</span>
                </label>
            </div>
        </fieldset>
        <div class="cod-field">
            <label class="cod-label" for="cod-similar-valor" id="cod-similar-valor-label">Orden tejido</label>
            <input id="cod-similar-valor" class="cod-input" type="text" inputmode="numeric" autocomplete="off">
        </div>
        <button type="button" id="cod-modal-traer" class="cod-modal__go">Traer</button>
    </div>
</div>
@endsection

@push('styles')
<style>
    .cod-page {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 0 0.75rem 2rem;
        -webkit-user-select: text;
        user-select: text;
    }
    .cod-identity {
        position: sticky;
        top: 0;
        z-index: 30;
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid #d1d5db;
        border-top: none;
        border-radius: 0 0 0.5rem 0.5rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        margin: 0 0 0.75rem;
        padding: 0.65rem 0.85rem 0.45rem;
        backdrop-filter: blur(6px);
    }
    .cod-identity__main {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .cod-identity__nombre {
        font-size: 1.05rem;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
        margin: 0;
    }
    .cod-identity__meta {
        margin: 0.15rem 0 0;
        font-size: 0.75rem;
        color: #4b5563;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    .cod-identity__tags {
        display: flex;
        gap: 0.4rem;
        flex-wrap: wrap;
        align-items: center;
    }
    .cod-chip {
        display: inline-flex;
        align-items: center;
        min-height: 1.75rem;
        padding: 0.15rem 0.55rem;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .cod-chip--km { background: #fff7ed; color: #9a3412; border-color: #fdba74; }
    .cod-chip--jac { background: #eef2ff; color: #3730a3; border-color: #a5b4fc; }
    .cod-chip--smit { background: #eff6ff; color: #1e40af; border-color: #93c5fd; }
    .cod-chip--none { background: #f3f4f6; color: #4b5563; border-color: #d1d5db; }
    .cod-chip--warn { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
    .cod-banner {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e3a8a;
        font-size: 0.8rem;
        padding: 0.55rem 0.75rem;
        border-radius: 0.4rem;
        margin-bottom: 0.75rem;
    }
    .cod-banner--warn {
        background: #fffbeb;
        border-color: #fcd34d;
        color: #78350f;
    }
    .cod-form {
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .cod-section {
        padding: 0.9rem 0.9rem 1rem;
        border-top: 1px solid #e5e7eb;
        scroll-margin-top: 6.5rem;
    }
    .cod-field { scroll-margin-top: 6.5rem; }
    .cod-section:first-child { border-top: 0; }
    .cod-section--km { background: #fffbf5; }
    .cod-section__head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.5rem 1rem;
        margin-bottom: 0.7rem;
    }
    .cod-section__head h2 {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 700;
        color: #111827;
    }
    .cod-section__head p,
    .cod-section__note {
        margin: 0;
        font-size: 0.72rem;
        color: #6b7280;
    }
    .cod-sub {
        margin: 1rem 0 0.35rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: #1f2937;
    }
    .cod-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.65rem 0.75rem;
    }
    .cod-grid--id .cod-input--primary { font-weight: 600; }
    .cod-grid--2 { grid-template-columns: 1fr; }
    @media (min-width: 640px) {
        .cod-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cod-grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 768px) {
        .cod-grid--id { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    @media (min-width: 1024px) {
        .cod-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .cod-grid--id { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    @media (min-width: 1440px) {
        .cod-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        .cod-grid--id { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    .cod-grid--full { grid-template-columns: 1fr; }
    .cod-field { min-width: 0; }
    .cod-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.2rem;
        line-height: 1.2;
    }
    .cod-req { color: #dc2626; }
    .cod-hint {
        margin: 0.15rem 0 0;
        font-size: 0.68rem;
        line-height: 1.2;
        color: #4b5563;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .cod-error { margin: 0.2rem 0 0; font-size: 0.68rem; color: #b91c1c; }
    .cod-input {
        width: 100%;
        min-height: 2.5rem;
        padding: 0.4rem 0.55rem;
        font-size: 0.8125rem;
        line-height: 1.25;
        color: #111827;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
    }
    .cod-input:hover { border-color: #9ca3af; }
    .cod-input:focus {
        outline: 2px solid #2563eb;
        outline-offset: 1px;
        border-color: #2563eb;
    }
    .cod-input--primary { border-color: #93c5fd; background: #f8fbff; }
    .cod-input[required] { border-left: 3px solid #dc2626; }
    .cod-input[required]:focus { border-left-color: #2563eb; }
    .cod-input--derived {
        background: #f8fafc;
        border-style: dashed;
        color: #334155;
        cursor: default;
    }
    .cod-input--dup { box-shadow: inset 0 0 0 1px #f59e0b; }
    /* Claves armadas solas (Tamaño + Clave AX): se leen de un vistazo, no se teclean. */
    [data-field="TamanoClave"] .cod-input,
    [data-field="ClaveModelo"] .cod-input {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: #1e3a8a;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
    }
    .cod-input--error { border-color: #dc2626; background: #fef2f2; }
    .cod-empty {
        margin: 0;
        padding: 1rem;
        font-size: 0.85rem;
        color: #374151;
        background: #f9fafb;
        border: 1px dashed #d1d5db;
        border-radius: 0.4rem;
    }
    .cod-const-std {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    @media (min-width: 768px) {
        .cod-const-std { grid-template-columns: 1fr 1fr; }
    }
    .cod-card {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 0.4rem;
        padding: 0.7rem;
    }
    .cod-card h3 {
        margin: 0 0 0.5rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: #0f172a;
    }
    .cod-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .cod-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
        min-width: 720px;
    }
    .cod-table th, .cod-table td {
        border: 1px solid #d1d5db;
        padding: 0.35rem;
        vertical-align: middle;
        text-align: left;
    }
    .cod-table thead th {
        background: #f3f4f6;
        font-weight: 600;
        color: #111827;
        white-space: nowrap;
    }
    .cod-table tbody tr:nth-child(odd) { background: #fff; }
    .cod-table tbody tr:nth-child(even) { background: #f8fafc; }
    .cod-table tbody tr:hover { background: #eff6ff; }
    .cod-table__empty { background: #fffbeb !important; }
    .cod-bar-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 999px;
        background: #9a3412;
        color: #fff;
        font-weight: 700;
        font-size: 0.7rem;
    }
    [data-solo-salon="std"] .cod-bar-num { background: #1e40af; }
    .cod-bar-empty {
        display: block;
        margin-top: 0.15rem;
        font-size: 0.7rem;
        font-weight: 600;
        color: #b45309;
        white-space: nowrap;
    }
    .cod-table .cod-input { min-height: 2.25rem; font-size: 0.75rem; }
    .cod-na { color: #9ca3af; }
    .cod-input[type='number']::-webkit-outer-spin-button,
    .cod-input[type='number']::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .cod-input[type='number'] { -moz-appearance: textfield; appearance: textfield; }
    .cod-details summary {
        cursor: pointer;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.75rem;
        align-items: baseline;
        font-size: 0.9rem;
        font-weight: 700;
        color: #111827;
        list-style: none;
    }
    .cod-details summary::-webkit-details-marker { display: none; }
    .cod-id-resto { display: contents; }
    .cod-id-resto[hidden] { display: none !important; }
    .cod-modal {
        position: fixed;
        inset: 0;
        z-index: 80;
        background: rgba(15, 23, 42, 0.45);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .cod-modal[hidden] { display: none !important; }
    .cod-modal__box {
        width: 100%;
        max-width: 26rem;
        background: #fff;
        border-radius: 0.5rem;
        padding: 0.9rem 1rem 1rem;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
    }
    .cod-modal__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .cod-modal__head h2 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
    }
    .cod-modal__x {
        border: 0;
        background: transparent;
        font-size: 1.5rem;
        line-height: 1;
        color: #6b7280;
        cursor: pointer;
        padding: 0.15rem 0.35rem;
    }
    .cod-modal__set {
        border: 0;
        margin: 0 0 0.7rem;
        padding: 0;
    }
    .cod-modal__set legend {
        font-size: 0.75rem;
        font-weight: 700;
        color: #374151;
        margin-bottom: 0.35rem;
    }
    .cod-modal__ops {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem;
    }
    .cod-choice {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        min-height: 2.5rem;
        padding: 0.35rem 0.6rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        font-size: 0.8125rem;
        cursor: pointer;
    }
    .cod-choice:has(input:checked) {
        border-color: #059669;
        background: #ecfdf5;
        font-weight: 600;
    }
    .cod-modal__go {
        width: 100%;
        margin-top: 0.85rem;
        min-height: 2.5rem;
        border: 0;
        border-radius: 0.375rem;
        background: #059669;
        color: #fff;
        font-weight: 700;
        font-size: 0.875rem;
        cursor: pointer;
    }
    .cod-modal__go:disabled { opacity: 0.6; cursor: not-allowed; }
    #cod-submit-spin[hidden] { display: none !important; }
    @media (prefers-reduced-motion: reduce) {
        .cod-input, #cod-cancelar, #cod-submit, #cod-traer-similar { transition: none !important; }
        html { scroll-behavior: auto; }
        #cod-submit-spin { animation: none !important; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const ALIAS_KM = ['KM', 'KARL MAYER', 'KARLMAYER'];
    const TELARES_KM = ['401', '402'];
    const CERO_ES_VACIO = ['Comb1', 'Comb2', 'Comb3', 'Comb4', 'Obs', 'Obs1', 'Obs2', 'Obs3', 'Obs4', 'Obs5'];
    const REQUIRED = ['SalonTejidoId', 'ItemId', 'InventSizeId'];

    const form = document.getElementById('codificacion-form');
    const idEl = document.getElementById('codificacion-id');
    const isEdit = !!(idEl && idEl.value !== '');
    const submitBtn = document.getElementById('cod-submit');
    const submitSpin = document.getElementById('cod-submit-spin');
    const dirtyEl = document.getElementById('cod-dirty');
    const cancelBtn = document.getElementById('cod-cancelar');

    let dirty = false;
    let snapshot = '';
    let saving = false;
    let salonesData = null;

    function notify() { return window.notify || null; }
    function http() { return window.http || null; }

    function limpiarValor(value, fieldName) {
        const s = String(value ?? '').trim();
        if (CERO_ES_VACIO.includes(fieldName) && (s === '0' || s === '0.0' || s === '0.00')) return '';
        if (/^-?\d+\.\d{6,}$/.test(s)) {
            return String(parseFloat(parseFloat(s).toFixed(4)));
        }
        return value;
    }

    function esKarlMayer() {
        const salon = (document.getElementById('salon-tejido-select')?.value || '').trim().toUpperCase();
        if (ALIAS_KM.includes(salon)) return true;
        if (salon !== '') return false;
        const telar = (document.getElementById('no-telar-select')?.value || '').trim();
        return TELARES_KM.includes(telar);
    }

    function setBloqueVisible(el, visible) {
        el.hidden = !visible;
        el.querySelectorAll('input, select, textarea, button').forEach(function (ctrl) {
            ctrl.disabled = !visible;
        });
    }

    function visibleSegunSalon(el, elegido, km) {
        const solo = el.dataset.soloSalon;
        const need = el.hasAttribute('data-need-salon');
        if (solo === 'elige') return !elegido;
        if (solo === 'km') return elegido && km;
        if (solo === 'std') return elegido && !km;
        if (need) return elegido;
        return true;
    }

    function aplicarSalon() {
        const salon = (document.getElementById('salon-tejido-select')?.value || '').trim();
        const telar = (document.getElementById('no-telar-select')?.value || '').trim();
        const elegido = salon !== '' || telar !== '';
        const km = esKarlMayer();
        document.querySelectorAll('[data-solo-salon], [data-need-salon]').forEach(function (el) {
            if (el.tagName === 'A') {
                el.hidden = !visibleSegunSalon(el, elegido, km);
                return;
            }
            setBloqueVisible(el, visibleSegunSalon(el, elegido, km));
        });
        pintarIdentidad();
    }

    function textoO(valor, vacio) {
        const s = String(valor || '').trim();
        return s === '' ? vacio : s;
    }

    function syncClaves(force) {
        const item = (form.querySelector('[name="ItemId"]')?.value || '').trim();
        const size = (form.querySelector('[name="InventSizeId"]')?.value || '').trim();
        // Tamaño primero y luego la clave AX: FEL + 7897 = FEL7897.
        const concat = size + item;
        const tamano = form.querySelector('[name="TamanoClave"]');
        const claveMod = form.querySelector('[name="ClaveModelo"]');
        const actual = tamano ? String(tamano.value || '').trim() : '';
        const conservar = !force && isEdit && actual !== '' && actual !== concat && tamano.dataset.auto !== '1';
        if (conservar) {
            return actual;
        }
        if (tamano) {
            tamano.value = concat;
            tamano.dataset.auto = '1';
        }
        if (claveMod && (force || !isEdit || String(claveMod.value || '').trim() === '' || claveMod.dataset.auto === '1')) {
            claveMod.value = concat;
            claveMod.dataset.auto = '1';
        }
        return concat;
    }

    function pintarIdentidad() {
        const salon = document.getElementById('salon-tejido-select')?.value || '';
        const telar = document.getElementById('no-telar-select')?.value || '';
        const chipSalon = document.getElementById('cod-id-salon');
        const chipTelar = document.getElementById('cod-id-telar');
        const km = esKarlMayer();
        if (chipSalon) {
            chipSalon.textContent = salon || 'Sin salón';
            chipSalon.className = 'cod-chip ' + (km ? 'cod-chip--km' : (salon === '' ? 'cod-chip--none' : (salon.toUpperCase().includes('JAC') ? 'cod-chip--jac' : 'cod-chip--smit')));
        }
        if (chipTelar) {
            chipTelar.textContent = 'Telar ' + (telar || '—');
            chipTelar.className = 'cod-chip' + (km && telar === '' ? ' cod-chip--warn' : '');
        }
        const set = (id, val, vacio) => {
            const el = document.getElementById(id);
            if (el) el.textContent = textoO(val, vacio);
        };
        set('cod-id-nombre', form.querySelector('[name="Nombre"]')?.value, 'Sin nombre');
        set('cod-id-clave', form.querySelector('[name="TamanoClave"]')?.value || syncClaves(), '—');
        set('cod-id-flog', form.querySelector('[name="FlogsId"]')?.value, 'Sin flog');
        const item = (form.querySelector('[name="ItemId"]')?.value || '').trim();
        const size = (form.querySelector('[name="InventSizeId"]')?.value || '').trim();
        const itemWrap = document.getElementById('cod-id-item-wrap');
        const itemEl = document.getElementById('cod-id-item');
        const itemSize = (item + ' ' + size).trim();
        if (itemEl) itemEl.textContent = itemSize;
        if (itemWrap) itemWrap.hidden = itemSize === '';
    }

    function serializar() {
        return Array.from(form.querySelectorAll('[name]')).map(function (el) {
            return el.name + '=' + String(el.value || '');
        }).join('&');
    }

    function marcarDirty(on) {
        dirty = on;
        if (dirtyEl) dirtyEl.hidden = !on;
    }

    function clearFieldError(name) {
        const wrap = form.querySelector('[data-field="' + name + '"]');
        const input = form.querySelector('[name="' + name + '"]');
        const err = form.querySelector('[data-error-for="' + name + '"]');
        if (input) {
            input.classList.remove('cod-input--error');
            input.removeAttribute('aria-invalid');
        }
        if (err) { err.hidden = true; err.textContent = ''; }
        wrap?.classList.remove('cod-field--error');
    }

    function showFieldError(name, message) {
        const input = form.querySelector('[name="' + name + '"]');
        const err = form.querySelector('[data-error-for="' + name + '"]');
        if (input) {
            input.classList.add('cod-input--error');
            input.setAttribute('aria-invalid', 'true');
        }
        if (err) {
            err.hidden = false;
            err.textContent = message;
        } else if (input && input.closest('td')) {
            let p = input.parentElement.querySelector('.cod-error');
            if (!p) {
                p = document.createElement('p');
                p.className = 'cod-error';
                input.parentElement.appendChild(p);
            }
            p.textContent = message;
        }
    }

    function validarCliente() {
        let first = null;
        REQUIRED.forEach(function (name) {
            clearFieldError(name);
            const el = form.querySelector('[name="' + name + '"]');
            if (!el || String(el.value || '').trim() === '') {
                showFieldError(name, 'Obligatorio');
                if (!first) first = el;
            }
        });
        if (first) {
            first.scrollIntoView({ block: 'center' });
            first.focus();
        }
        return !first;
    }

    function aplicarErrores(errors) {
        Object.keys(errors || {}).forEach(function (name) {
            const msgs = errors[name];
            showFieldError(name, Array.isArray(msgs) ? msgs[0] : String(msgs));
        });
        const firstName = Object.keys(errors || {})[0];
        const first = firstName ? form.querySelector('[name="' + firstName + '"]') : null;
        first?.focus();
    }

    function updateTelaresSelect(selectedTelar, salonForzado) {
        const salonSelect = document.getElementById('salon-tejido-select');
        const telarSelect = document.getElementById('no-telar-select');
        if (!salonSelect || !telarSelect || !salonesData) return;

        const salonSeleccionado = salonForzado != null ? salonForzado : salonSelect.value;
        const telarASeleccionar = selectedTelar != null ? selectedTelar : telarSelect.value;
        telarSelect.innerHTML = '<option value="">Seleccionar...</option>';

        let telares = null;
        if (salonSeleccionado && salonesData.telaresPorSalon[salonSeleccionado]) {
            telares = salonesData.telaresPorSalon[salonSeleccionado];
        } else if (salonSeleccionado === 'ITEMA' && salonesData.telaresPorSalon['SMIT']) {
            telares = salonesData.telaresPorSalon['SMIT'];
        } else if (salonSeleccionado === 'SMIT' && salonesData.telaresPorSalon['ITEMA']) {
            telares = salonesData.telaresPorSalon['ITEMA'];
        }

        if (telares && Array.isArray(telares)) {
            telares.forEach(function (telar) {
                const option = document.createElement('option');
                option.value = String(telar);
                option.textContent = String(telar);
                if (telarASeleccionar && String(telar) === String(telarASeleccionar)) option.selected = true;
                telarSelect.appendChild(option);
            });
        }
        if (!salonSeleccionado) telarSelect.value = '';
    }

    function asegurarKarlMayer(data) {
        const salones = Array.isArray(data.salones) ? data.salones.slice() : [];
        const telares = Object.assign({}, data.telaresPorSalon || {});
        const kmTelares = (telares['KARL MAYER'] || telares.KM || telares.KARLMAYER || []).map(String);
        ['401', '402'].forEach(function (t) {
            if (kmTelares.indexOf(t) === -1) kmTelares.push(t);
        });
        telares['KARL MAYER'] = kmTelares;
        delete telares.KM;
        delete telares.KARLMAYER;
        const filtrados = salones.filter(function (s) {
            const u = String(s).toUpperCase().replace(/\s+/g, '');
            return u !== 'KM' && u !== 'KARLMAYER';
        });
        if (filtrados.indexOf('KARL MAYER') === -1) filtrados.push('KARL MAYER');
        filtrados.sort();
        return { salones: filtrados, telaresPorSalon: telares };
    }

    async function loadSalonesYTelares() {
        const salonSelect = document.getElementById('salon-tejido-select');
        const telarSelect = document.getElementById('no-telar-select');
        try {
            const client = http();
            const result = client
                ? await client.get('/planeacion/catalogos/codificacion-modelos/salones-telares')
                : await fetch('/planeacion/catalogos/codificacion-modelos/salones-telares').then(function (r) { return r.json(); });
            if (!result || !result.success || !result.data) return;
            salonesData = asegurarKarlMayer(result.data);

            let currentSalon = salonSelect ? salonSelect.value : '';
            let currentTelar = telarSelect ? telarSelect.value : '';
            if (window.codificacionData) {
                if (window.codificacionData.SalonTejidoId) {
                    currentSalon = window.codificacionData.SalonTejidoId;
                    if (currentSalon === 'SMIT' && salonesData.salones && salonesData.salones.includes('ITEMA')) {
                        currentSalon = 'ITEMA';
                    }
                }
                if (window.codificacionData.NoTelarId) currentTelar = window.codificacionData.NoTelarId;
            }

            if (salonSelect) {
                salonSelect.innerHTML = '<option value="">Seleccionar...</option>';
                salonesData.salones.forEach(function (salon) {
                    const option = document.createElement('option');
                    option.value = salon;
                    option.textContent = salon;
                    if (String(salon) === String(currentSalon)) option.selected = true;
                    salonSelect.appendChild(option);
                });
                if (currentSalon) {
                    salonSelect.value = currentSalon;
                    updateTelaresSelect(currentTelar, currentSalon);
                }
            }
            aplicarSalon();
        } catch (err) {
            salonesData = asegurarKarlMayer({ salones: [], telaresPorSalon: {} });
            if (salonSelect && salonSelect.options.length <= 1) {
                salonSelect.innerHTML = '<option value="">Seleccionar...</option>';
                salonesData.salones.forEach(function (salon) {
                    const option = document.createElement('option');
                    option.value = salon;
                    option.textContent = salon;
                    salonSelect.appendChild(option);
                });
            }
            notify()?.error?.('No se pudieron cargar salones y telares');
            aplicarSalon();
        }
    }

    function aplicarCamposSimilar(campos) {
        const salonActual = (document.getElementById('salon-tejido-select')?.value || '').trim();
        const telarActual = (document.getElementById('no-telar-select')?.value || '').trim();
        Object.keys(campos || {}).forEach(function (name) {
            if (salonActual && (name === 'SalonTejidoId' || name === 'NoTelarId')) return;
            const el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            const val = campos[name] == null ? '' : String(campos[name]);
            if (el.tagName === 'SELECT' && val !== '') {
                const existe = Array.from(el.options).some(function (o) { return o.value === val; });
                if (!existe) {
                    const opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val;
                    el.appendChild(opt);
                }
            }
            el.value = val;
        });
        if (salonActual) {
            const salonEl = document.getElementById('salon-tejido-select');
            const telarEl = document.getElementById('no-telar-select');
            if (salonEl) salonEl.value = salonActual;
            if (telarEl) telarEl.value = telarActual;
        }
        syncClaves(true);
        aplicarSalon();
        pintarIdentidad();
        traerVelocidad();
    }

    function modalSimilar() { return document.getElementById('cod-modal-similar'); }
    function origenElegido() {
        return document.querySelector('input[name="cod-origen"]:checked')?.value || 'req';
    }
    function porElegido() {
        return document.querySelector('input[name="cod-por"]:checked')?.value || 'orden';
    }

    function syncModalPor() {
        const por = porElegido();
        const label = document.getElementById('cod-similar-valor-label');
        const input = document.getElementById('cod-similar-valor');
        if (label) label.textContent = por === 'clave' ? 'Clave modelo' : 'Orden tejido';
        if (!input) return;
        if (por === 'orden') {
            input.inputMode = 'numeric';
            const orden = String(window.codificacionData?.OrdenTejido || '').trim();
            if (orden) input.value = orden;
            else input.value = String(input.value || '').replace(/\D+/g, '');
        } else {
            input.inputMode = 'text';
            const clave = (form.querySelector('[name="ClaveModelo"]')?.value
                || form.querySelector('[name="TamanoClave"]')?.value || '').trim();
            if (clave) input.value = clave;
        }
        input.focus();
        input.select();
    }

    function abrirModalSimilar() {
        const modal = modalSimilar();
        if (!modal) return;
        modal.hidden = false;
        syncModalPor();
    }

    function cerrarModalSimilar() {
        const modal = modalSimilar();
        if (modal) modal.hidden = true;
    }

    async function traerModeloSimilar() {
        const origen = origenElegido();
        const por = porElegido();
        const input = document.getElementById('cod-similar-valor');
        let valor = (input?.value || '').trim();
        if (por === 'orden') valor = valor.replace(/\D+/g, '');
        if (input) input.value = valor;
        if (valor === '') {
            notify()?.warning?.(por === 'orden' ? 'Escribe la orden de tejido' : 'Escribe la clave modelo');
            input?.focus();
            return;
        }
        const go = document.getElementById('cod-modal-traer');
        const btn = document.getElementById('cod-traer-similar');
        if (go) go.disabled = true;
        if (btn) btn.disabled = true;
        notify()?.loading?.('Buscando modelo…');
        try {
            const params = new URLSearchParams({ origen: origen, por: por, valor: valor });
            if (idEl && idEl.value) params.set('excluir_id', idEl.value);
            const salon = (document.getElementById('salon-tejido-select')?.value || '').trim();
            if (salon) params.set('salon', salon);
            const client = http();
            const url = '/planeacion/catalogos/codificacion-modelos/modelo-similar?' + params.toString();
            const result = client
                ? await client.get(url)
                : await fetch(url).then(function (r) { return r.json(); });
            notify()?.close?.();
            if (!result || !result.success || !result.data) {
                notify()?.error?.(result && result.message ? result.message : 'No se encontró el modelo');
                return;
            }
            aplicarCamposSimilar(result.data.campos || {});
            cerrarModalSimilar();
            const de = origen === 'req' ? 'ModelosCodificados' : 'Codificacion';
            notify()?.success?.('Datos copiados de ' + de + ': ' + (result.data.nombre || result.data.clave_mod || valor));
            marcarDirty(true);
        } catch (err) {
            notify()?.close?.();
            notify()?.error?.(err && err.message ? err.message : 'No se encontró el modelo');
        } finally {
            if (go) go.disabled = false;
            if (btn) btn.disabled = false;
        }
    }

    async function buscarFlog() {
        const item = (form.querySelector('[name="ItemId"]')?.value || '').trim();
        const size = (form.querySelector('[name="InventSizeId"]')?.value || '').trim();
        const flog = form.querySelector('[name="FlogsId"]');
        const proy = form.querySelector('[name="NombreProyecto"]');
        if (!item || !size || !flog || flog.dataset.tocado === '1') return;
        try {
            const client = http();
            const url = '/planeacion/catalogos/codificacion-modelos/flogs-data?item_id='
                + encodeURIComponent(item) + '&invent_size_id=' + encodeURIComponent(size);
            const result = client
                ? await client.get(url)
                : await fetch(url).then(function (r) { return r.json(); });
            if (!result || !result.success || !result.data || !result.data.idflog) return;
            flog.value = result.data.idflog;
            if (proy && proy.dataset.tocado !== '1' && result.data.nombre) {
                proy.value = result.data.nombre;
            }
            pintarIdentidad();
        } catch (err) {
            // TI puede no estar; el flog se sigue tecleando a mano.
        }
    }

    // Velocidad STD sale de ReqVelocidadStd (telar + fibra + densidad), igual que en Programa Tejido.
    // Jacquard/Smit buscan por Fibra de rizo; Karl Mayer por la fibra de sus barras.
    const CAMPOS_VELOCIDAD = ['NoTelarId', 'FibraRizo', 'CalibreTrama', 'CalibreTrama2', 'FibraBarra1', 'FibraBarra2', 'FibraBarra3', 'FibraBarra4'];

    async function traerVelocidad() {
        const client = http();
        const vel = form.querySelector('[name="VelocidadSTD"]');
        const telar = (document.getElementById('no-telar-select')?.value || '').trim();
        if (!client || !vel || telar === '') return;
        const valor = function (name) { return (form.querySelector('[name="' + name + '"]')?.value || '').trim(); };
        const fibras = esKarlMayer()
            ? ['FibraBarra1', 'FibraBarra2', 'FibraBarra3', 'FibraBarra4'].map(valor)
            : [valor('FibraRizo')];
        const calibre = valor('CalibreTrama2') || valor('CalibreTrama') || '0';
        for (const fibra of fibras.filter(Boolean)) {
            try {
                const params = new URLSearchParams({ fibra_id: fibra, no_telar_id: telar, calibre_trama: calibre });
                const std = await client.get('/programa-tejido/eficiencia-velocidad-std?' + params.toString());
                if (std && std.velocidad != null) {
                    vel.value = String(parseFloat(std.velocidad));
                    marcarDirty(serializar() !== snapshot);
                    return;
                }
            } catch (err) {
                // Sin catálogo para esa fibra: se prueba la siguiente y si no, se deja el valor actual.
            }
        }
    }

    document.addEventListener('change', function (e) {
        if (CAMPOS_VELOCIDAD.includes(e.target.name)) traerVelocidad();
        if (e.target.id === 'salon-tejido-select' || e.target.id === 'no-telar-select') aplicarSalon();
        if (e.target.id === 'salon-tejido-select') {
            const telarEl = document.getElementById('no-telar-select');
            if (telarEl) telarEl.value = '';
            updateTelaresSelect();
            aplicarSalon();
            if (esKarlMayer()) {
                document.getElementById('no-telar-select')?.focus();
            }
        }
        if (e.target.name === 'ItemId' || e.target.name === 'InventSizeId') {
            syncClaves(true);
            buscarFlog();
        }
        if (e.target.name === 'FlogsId' && e.isTrusted) e.target.dataset.tocado = '1';
        if (e.target.name === 'NombreProyecto' && e.isTrusted) e.target.dataset.tocado = '1';
    });

    document.addEventListener('input', function (e) {
        if (e.target.name === 'OrdenTejido') {
            const digits = String(e.target.value || '').replace(/\D+/g, '');
            if (e.target.value !== digits) e.target.value = digits;
        }
        if (e.target.name === 'ItemId' || e.target.name === 'InventSizeId') {
            syncClaves(true);
        }
        pintarIdentidad();
        if (form.contains(e.target) && snapshot && serializar() !== snapshot) marcarDirty(true);
        else if (snapshot && serializar() === snapshot) marcarDirty(false);
        if (e.target.name) clearFieldError(e.target.name);
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            if (modalSimilar() && !modalSimilar().hidden) return;
            form.requestSubmit();
            return;
        }
        if (e.key !== 'Enter' || !e.target.classList.contains('tab-navigation')) return;
        if (e.target.tagName === 'TEXTAREA') return;
        e.preventDefault();
        const inputs = Array.from(form.querySelectorAll('.tab-navigation')).filter(function (el) {
            return !el.disabled && el.offsetParent !== null;
        });
        const currentIndex = inputs.indexOf(e.target);
        if (currentIndex !== -1 && currentIndex < inputs.length - 1) {
            inputs[currentIndex + 1].focus();
            if (inputs[currentIndex + 1].select) inputs[currentIndex + 1].select();
        } else {
            submitBtn?.focus();
        }
    });

    cancelBtn?.addEventListener('click', async function () {
        if (dirty) {
            const n = notify();
            const ok = n
                ? await n.confirm({
                    title: '¿Salir sin guardar?',
                    text: 'Hay cambios sin guardar.',
                    confirmText: 'Salir',
                    cancelText: 'Seguir editando',
                    confirmColor: '#dc2626',
                })
                : window.confirm('Hay cambios sin guardar. ¿Salir?');
            if (!ok) return;
        }
        history.back();
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirty && !saving) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (saving) return;
        syncClaves(!isEdit);
        if (!validarCliente()) {
            notify()?.warning?.('Completa salón, Clave AX y Tamaño');
            return;
        }

        const n = notify();
        const data = Object.fromEntries(new FormData(form).entries());
        if (data.SalonTejidoId === 'ITEMA') data.SalonTejidoId = 'SMIT';

        const url = isEdit
            ? '/planeacion/catalogos/codificacion-modelos/' + idEl.value
            : '/planeacion/catalogos/codificacion-modelos';

        saving = true;
        submitBtn.disabled = true;
        if (submitSpin) submitSpin.hidden = false;
        n?.loading?.(isEdit ? 'Actualizando…' : 'Guardando…');

        try {
            const client = http();
            let json;
            if (client) {
                json = isEdit ? await client.put(url, data) : await client.post(url, data);
            } else {
                const res = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });
                json = await res.json();
                if (!res.ok || json.success === false) {
                    const err = new Error(json.message || 'No fue posible guardar');
                    err.status = res.status;
                    err.errors = json.errors;
                    throw err;
                }
            }
            if (json && json.success === false) {
                const err = new Error(json.message || 'No fue posible guardar');
                err.errors = json.errors;
                throw err;
            }
            dirty = false;
            n?.close?.();
            n?.success?.(isEdit ? 'Modelo actualizado' : 'Modelo creado');
            window.location.href = '/planeacion/catalogos/codificacion-modelos';
        } catch (err) {
            n?.close?.();
            if (err && err.errors) {
                aplicarErrores(err.errors);
                n?.validation?.(err.errors);
            } else {
                n?.error?.(err.message || 'Ocurrió un error al guardar');
            }
            saving = false;
            submitBtn.disabled = false;
            if (submitSpin) submitSpin.hidden = true;
        }
    });

    document.addEventListener('DOMContentLoaded', async function () {
        if (submitSpin) submitSpin.hidden = true;
        document.getElementById('cod-traer-similar')?.addEventListener('click', abrirModalSimilar);
        document.getElementById('cod-modal-cerrar')?.addEventListener('click', cerrarModalSimilar);
        document.getElementById('cod-modal-traer')?.addEventListener('click', traerModeloSimilar);
        modalSimilar()?.addEventListener('click', function (e) {
            if (e.target === modalSimilar()) cerrarModalSimilar();
        });
        document.querySelectorAll('input[name="cod-por"]').forEach(function (el) {
            el.addEventListener('change', syncModalPor);
        });
        document.getElementById('cod-similar-valor')?.addEventListener('input', function (e) {
            if (porElegido() !== 'orden') return;
            const digits = String(e.target.value || '').replace(/\D+/g, '');
            if (e.target.value !== digits) e.target.value = digits;
        });
        document.getElementById('cod-similar-valor')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                traerModeloSimilar();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modalSimilar() && !modalSimilar().hidden) {
                e.preventDefault();
                cerrarModalSimilar();
            }
        });
        await loadSalonesYTelares();
        aplicarSalon();
        pintarIdentidad();
        snapshot = serializar();
        marcarDirty(false);
        syncClaves();
        if (!isEdit) {
            document.getElementById('salon-tejido-select')?.focus();
        }
    });
})();
</script>
@endpush
