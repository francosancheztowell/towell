@extends('layouts.app')

@php
    // TEMPORAL: helpers de presentación para los datos simulados.
    $chipEstatusOd = [
        'En preparación' => 'border-blue-200 bg-blue-50 text-blue-700',
        'Programada' => 'border-slate-200 bg-slate-100 text-slate-600',
        'Detenida' => 'border-amber-200 bg-amber-50 text-amber-700',
    ];

    // Etiqueta corta para que el chip entre en las columnas angostas de tablet.
    // El texto completo queda en el atributo title.
    $estatusCorto = [
        'En preparación' => 'Preparando',
        'Programada' => 'Programada',
        'Detenida' => 'Detenida',
    ];

    $chipTipoOd = [
        'Nacional' => 'border-sky-200 bg-sky-50 text-sky-700',
        'Exportación' => 'border-violet-200 bg-violet-50 text-violet-700',
        'Traspaso' => 'border-slate-200 bg-slate-100 text-slate-600',
    ];

    $formatoDuracion = static fn (int $minutos): string => intdiv($minutos, 60) . 'h ' . str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT) . 'm';

    // Densidad compartida: la tipografía y el padding escalan con el ancho de
    // pantalla, así el mismo layout sirve en tablet, laptop y TV.
    $claseTabla = 'w-full table-fixed border-collapse text-xs xl:text-sm 2xl:text-base';
    $claseThead = 'sticky top-0 z-10 bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500 shadow-sm xl:text-xs';
    $claseTh = 'bg-slate-50 px-2 py-2 xl:px-3 xl:py-2.5';
    $claseTd = 'px-2 py-2 xl:px-3 xl:py-2.5';
    $claseSub = 'truncate text-[10px] text-slate-500 xl:text-xs';
    $claseChip = 'inline-flex max-w-full truncate rounded-full border px-1.5 py-px text-[10px] font-semibold xl:px-2 xl:text-xs';
    $claseTituloPanel = 'truncate text-xs font-bold uppercase tracking-wide text-slate-700 xl:text-sm';
    $claseHeaderPanel = 'flex shrink-0 items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-2.5 py-2 xl:px-4 xl:py-2.5';
    $claseSeccion = 'flex min-h-0 min-w-0 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm';
    $claseScroll = 'min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain';
    $claseConteo = 'shrink-0 rounded-full px-1.5 py-px text-[10px] font-bold xl:px-2 xl:text-xs';
@endphp

@section('page-title')
    <x-layout.page-title title="Tiempos Preparación" />

@endsection

@section('navbar-right')
    <x-navbar.button-create
      id="btn-crear-orden"
      title="Crear Orden"
      module="Tiempos Preparación" />
@endsection


@section('content')
<div class="flex h-[calc(100vh-64px)] flex-col gap-2 overflow-hidden p-2 xl:gap-3 xl:p-3">

    {{-- Resumen superior: se mantiene compacto para no robarle altura a los paneles. --}}
    {{-- <div class="grid shrink-0 grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Distribuciones abiertas</p>
            <p class="mt-0.5 text-xl font-bold text-slate-900 lg:text-2xl">{{ count($ordenesDistribucion) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Órdenes de compra</p>
            <p class="mt-0.5 text-xl font-bold text-slate-900 lg:text-2xl">{{ collect($ordenesDistribucion)->sum(fn ($orden) => count($orden['compras'])) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Piezas por surtir</p>
            <p class="mt-0.5 text-xl font-bold text-slate-900 lg:text-2xl">{{ number_format(collect($ordenesDistribucion)->sum('piezas')) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Prom. preparación</p>
            <p class="mt-0.5 text-xl font-bold text-emerald-700 lg:text-2xl">
                {{ $formatoDuracion((int) round(collect($ordenesCerradas)->avg('minutos'))) }}
            </p>
        </div>
    </div> --}}

    {{--
        Distribución en L, idéntica en todas las pantallas:

            ┌───────────┬───────────┐
            │           │  Compras  │
            │ Distrib.  ├───────────┤
            │           │  Cerradas │
            └───────────┴───────────┘

        Dos columnas por dos filas; el panel de distribución abarca las dos
        filas. Solo escalan tipografía y densidad vía xl: / 2xl:. Ninguna tabla
        lleva min-width —todas son table-fixed con anchos porcentuales—, así que
        nunca aparece scroll lateral.
    --}}
    <div class="grid min-h-0 flex-1 grid-cols-2 grid-rows-2 gap-2 xl:gap-3">

        {{-- ============ 1. Órdenes de distribución ============ --}}
        <section class="{{ $claseSeccion }} row-span-2">
            <header class="{{ $claseHeaderPanel }}">
                <div class="flex min-w-0 items-center gap-1.5">
                    <i class="fa-solid fa-truck-ramp-box text-xs text-blue-600 xl:text-sm"></i>
                    <h2 class="{{ $claseTituloPanel }}">Órdenes de distribución</h2>
                </div>
                <span class="{{ $claseConteo }} bg-blue-100 text-blue-700">{{ count($ordenesDistribucion) }}</span>
            </header>

            <div class="{{ $claseScroll }}" tabindex="0" aria-label="Listado de órdenes de distribución">
                <table class="{{ $claseTabla }}">
                    <thead class="{{ $claseThead }}">
                        <tr>
                            <th class="{{ $claseTh }} w-[25%]">Folio / Orden</th>
                            <th class="{{ $claseTh }} w-[31%]">Cliente / Tipo</th>
                            <th class="{{ $claseTh }} w-[21%] text-right">Piezas / Kg</th>
                            <th class="{{ $claseTh }} w-[23%] text-right">Estatus / Prep.</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-distribucion" class="divide-y divide-slate-100" role="listbox" aria-label="Órdenes de distribución">
                        @foreach ($ordenesDistribucion as $indice => $orden)
                            <tr class="fila-distribucion cursor-pointer align-top transition hover:bg-blue-50/60 focus:bg-blue-50 focus:outline-none aria-selected:bg-blue-50 aria-selected:ring-1 aria-selected:ring-inset aria-selected:ring-blue-400"
                                data-indice="{{ $indice }}"
                                role="option"
                                aria-selected="false"
                                tabindex="0">
                                <td class="{{ $claseTd }}">
                                    <p class="truncate font-semibold text-slate-900">{{ $orden['folio'] }}</p>
                                    <p class="{{ $claseSub }}">{{ $orden['orden'] }}</p>
                                </td>
                                <td class="{{ $claseTd }}">
                                    <p class="truncate font-medium text-slate-800" title="{{ $orden['cliente'] }} — {{ $orden['destino'] }}">{{ $orden['cliente'] }}</p>
                                    <span class="{{ $claseChip }} mt-0.5 {{ $chipTipoOd[$orden['tipo']] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">{{ $orden['tipo'] }}</span>
                                </td>
                                <td class="{{ $claseTd }} text-right">
                                    <p class="tabular-nums text-slate-800">{{ number_format($orden['piezas']) }}</p>
                                    <p class="{{ $claseSub }} tabular-nums" title="{{ number_format($orden['kg'], 2) }} kg">{{ number_format($orden['kg']) }} kg</p>
                                </td>
                                <td class="{{ $claseTd }} text-right">
                                    <span class="{{ $claseChip }} {{ $chipEstatusOd[$orden['estatus']] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}" title="{{ $orden['estatus'] }}">
                                        {{ $estatusCorto[$orden['estatus']] ?? $orden['estatus'] }}
                                    </span>
                                    @if ($orden['inicio'])
                                        {{-- El texto lo escribe el cronómetro; el valor inicial evita el parpadeo. --}}
                                        <p class="contador-preparacion mt-0.5 font-bold tabular-nums text-slate-700"
                                           data-inicio="{{ $orden['inicio'] }}"
                                           title="Inicio de preparación: {{ \Illuminate\Support\Carbon::parse($orden['inicio'])->format('d/m/Y H:i') }}">--:--:--</p>
                                    @else
                                        <p class="mt-0.5 text-slate-400" title="Aún no inicia la preparación">—</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ============ 2. Órdenes de compra de la distribución seleccionada ============ --}}
        <section class="{{ $claseSeccion }}">
            <header class="{{ $claseHeaderPanel }}">
                <div class="flex min-w-0 items-center gap-1.5">
                    <i class="fa-solid fa-file-invoice text-xs text-indigo-600 xl:text-sm"></i>
                    <div class="min-w-0">
                        <h2 class="{{ $claseTituloPanel }}">Órdenes de compra</h2>
                        <p id="compras-subtitulo" class="{{ $claseSub }}">Seleccione una distribución</p>
                    </div>
                </div>
                <span id="compras-conteo" class="{{ $claseConteo }} bg-indigo-100 text-indigo-700">0</span>
            </header>

            <div class="{{ $claseScroll }}" tabindex="0" aria-label="Órdenes de compra de la distribución seleccionada">
                <table class="{{ $claseTabla }}">
                    <thead class="{{ $claseThead }}">
                        <tr>
                            <th class="{{ $claseTh }} w-[26%]">OC / Compr.</th>
                            <th class="{{ $claseTh }} w-[38%]">Artículo</th>
                            <th class="{{ $claseTh }} w-[36%] text-right">Surtido / Estatus</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-compras" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </section>

        {{-- ============ 3. Órdenes de distribución cerradas ============ --}}
        <section class="{{ $claseSeccion }}">
            <header class="{{ $claseHeaderPanel }}">
                <div class="flex min-w-0 items-center gap-1.5">
                    <i class="fa-solid fa-circle-check text-xs text-emerald-600 xl:text-sm"></i>
                    <h2 class="{{ $claseTituloPanel }}">Distribuciones cerradas</h2>
                </div>
                <span class="{{ $claseConteo }} bg-emerald-100 text-emerald-700">{{ count($ordenesCerradas) }}</span>
            </header>

            <div class="{{ $claseScroll }}" tabindex="0" aria-label="Distribuciones cerradas">
                <table class="{{ $claseTabla }}">
                    <thead class="{{ $claseThead }}">
                        <tr>
                            <th class="{{ $claseTh }} w-[40%]">Folio / Cliente</th>
                            <th class="{{ $claseTh }} w-[26%] text-right">Piezas / Kg</th>
                            <th class="{{ $claseTh }} w-[34%] text-right">Preparación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($ordenesCerradas as $cerrada)
                            <tr class="align-top transition hover:bg-emerald-50/50">
                                <td class="{{ $claseTd }}">
                                    <p class="truncate font-semibold text-slate-900">{{ $cerrada['folio'] }}</p>
                                    <p class="{{ $claseSub }}" title="{{ $cerrada['cliente'] }}">{{ $cerrada['cliente'] }}</p>
                                </td>
                                <td class="{{ $claseTd }} text-right">
                                    <p class="tabular-nums text-slate-800">{{ number_format($cerrada['piezas']) }}</p>
                                    <p class="{{ $claseSub }} tabular-nums" title="{{ number_format($cerrada['kg'], 2) }} kg">{{ number_format($cerrada['kg']) }} kg</p>
                                </td>
                                <td class="{{ $claseTd }} text-right">
                                    <p class="font-bold tabular-nums text-emerald-700">{{ $formatoDuracion($cerrada['minutos']) }}</p>
                                    <p class="{{ $claseSub }} tabular-nums">{{ \Illuminate\Support\Carbon::parse($cerrada['cierre'])->format('d/m H:i') }}</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</div>
@endsection

@push('scripts')
<script>
    // IIFE: el layout re-evalúa los scripts al navegar con wire:navigate y las
    // declaraciones `const` en el ámbito superior lanzarían "already declared".
    (function () {
        var ordenes = @json($ordenesDistribucion);

        var chipsCompra = {
            'Completa': 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'Parcial': 'border-amber-200 bg-amber-50 text-amber-700',
            'Pendiente': 'border-slate-200 bg-slate-100 text-slate-600',
        };

        // Se reutilizan las mismas variables de densidad del Blade: así las filas
        // dibujadas por JS escalan igual que las renderizadas en servidor y no
        // hay dos listas de clases que mantener sincronizadas a mano.
        var claseTd = @json($claseTd);
        var claseSub = @json($claseSub);
        var claseChip = @json($claseChip);

        function escapar(valor) {
            return String(valor ?? '').replace(/[&<>"']/g, function (caracter) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[caracter];
            });
        }

        function formatearNumero(valor) {
            return Number(valor).toLocaleString('es-MX');
        }

        function formatearFechaCorta(iso) {
            var partes = String(iso).split('-');
            return partes.length === 3 ? partes[2] + '/' + partes[1] : escapar(iso);
        }

        function filaVacia(mensaje) {
            return '<tr><td colspan="3" class="px-3 py-10 text-center text-[11px] text-slate-500">' + escapar(mensaje) + '</td></tr>';
        }

        function pintarCompras(orden) {
            var cuerpo = document.getElementById('tabla-compras');
            var subtitulo = document.getElementById('compras-subtitulo');
            var conteo = document.getElementById('compras-conteo');
            if (!cuerpo) return;

            if (!orden) {
                cuerpo.innerHTML = filaVacia('Seleccione una orden de distribución.');
                if (subtitulo) subtitulo.textContent = 'Seleccione una distribución';
                if (conteo) conteo.textContent = '0';
                return;
            }

            if (subtitulo) subtitulo.textContent = orden.folio + ' · ' + orden.cliente;
            if (conteo) conteo.textContent = String(orden.compras.length);

            if (orden.compras.length === 0) {
                cuerpo.innerHTML = filaVacia('Sin órdenes de compra registradas.');
                return;
            }

            cuerpo.innerHTML = orden.compras.map(function (compra) {
                var avance = compra.cantidad > 0 ? Math.round((compra.surtido / compra.cantidad) * 100) : 0;
                var chip = chipsCompra[compra.estatus] || chipsCompra['Pendiente'];

                return '' +
                    '<tr class="align-top transition hover:bg-indigo-50/50">' +
                        '<td class="' + claseTd + '">' +
                            '<p class="truncate font-semibold text-slate-900">' + escapar(compra.folio) + '</p>' +
                            '<p class="' + claseSub + ' tabular-nums">' + formatearFechaCorta(compra.compromiso) + '</p>' +
                        '</td>' +
                        '<td class="' + claseTd + '">' +
                            '<p class="truncate text-slate-800" title="' + escapar(compra.articulo) + '">' + escapar(compra.articulo) + '</p>' +
                            '<p class="' + claseSub + '">' + escapar(compra.modelo) + '</p>' +
                        '</td>' +
                        '<td class="' + claseTd + ' text-right">' +
                            '<p class="tabular-nums text-slate-800">' + formatearNumero(compra.surtido) + ' / ' + formatearNumero(compra.cantidad) + '</p>' +
                            '<div class="mt-1 h-1 w-full overflow-hidden rounded-full bg-slate-200">' +
                                '<div class="h-full rounded-full bg-indigo-500" style="width: ' + avance + '%"></div>' +
                            '</div>' +
                            '<span class="' + claseChip + ' mt-1 ' + chip + '">' + escapar(compra.estatus) + '</span>' +
                        '</td>' +
                    '</tr>';
            }).join('');
        }

        // ---- Cronómetro de preparación --------------------------------------
        // Umbrales provisionales: ámbar a las 6 h, rojo a las 10 h. Ajustar al
        // estándar real de preparación cuando el negocio lo defina.
        var umbralAmbar = 6;
        var umbralRojo = 10;
        var clasesContador = ['text-slate-700', 'text-amber-600', 'text-red-600'];

        function formatearCronometro(milisegundos) {
            var totalSegundos = Math.max(0, Math.floor(milisegundos / 1000));
            return [
                Math.floor(totalSegundos / 3600),
                Math.floor((totalSegundos % 3600) / 60),
                totalSegundos % 60,
            ].map(function (parte) {
                return String(parte).padStart(2, '0');
            }).join(':');
        }

        function actualizarContadores() {
            var ahora = Date.now();

            document.querySelectorAll('.contador-preparacion').forEach(function (elemento) {
                var inicio = Date.parse(elemento.dataset.inicio);
                if (Number.isNaN(inicio)) {
                    elemento.textContent = '—';
                    return;
                }

                var transcurrido = ahora - inicio;
                var horas = transcurrido / 3600000;

                elemento.textContent = formatearCronometro(transcurrido);
                elemento.classList.remove.apply(elemento.classList, clasesContador);
                elemento.classList.add(horas >= umbralRojo ? clasesContador[2] : (horas >= umbralAmbar ? clasesContador[1] : clasesContador[0]));
            });
        }

        function arrancarContadores() {
            // El intervalo vive en window: al navegar con wire:navigate esta IIFE
            // se re-ejecuta en un ámbito nuevo y el intervalo anterior quedaría
            // corriendo sobre nodos ya descartados.
            if (window.__contadorTiemposPrep) {
                clearInterval(window.__contadorTiemposPrep);
            }

            actualizarContadores();
            window.__contadorTiemposPrep = setInterval(actualizarContadores, 1000);
        }

        function seleccionar(fila) {
            var filas = document.querySelectorAll('#tabla-distribucion .fila-distribucion');
            filas.forEach(function (item) {
                item.setAttribute('aria-selected', String(item === fila));
            });
            pintarCompras(ordenes[Number(fila.dataset.indice)]);
        }

        function inicializar() {
            var cuerpo = document.getElementById('tabla-distribucion');
            if (!cuerpo) return;

            // Los cronómetros se reinician siempre; los listeners solo una vez
            // por instancia del DOM.
            arrancarContadores();

            if (cuerpo.dataset.listenersBound === '1') return;
            cuerpo.dataset.listenersBound = '1';

            cuerpo.addEventListener('click', function (evento) {
                var fila = evento.target.closest('.fila-distribucion');
                if (fila) seleccionar(fila);
            });

            cuerpo.addEventListener('keydown', function (evento) {
                if (evento.key !== 'Enter' && evento.key !== ' ') return;
                var fila = evento.target.closest('.fila-distribucion');
                if (!fila) return;
                evento.preventDefault();
                seleccionar(fila);
            });

            var primera = cuerpo.querySelector('.fila-distribucion');
            primera ? seleccionar(primera) : pintarCompras(null);
        }

        document.addEventListener('DOMContentLoaded', inicializar);
        document.addEventListener('livewire:navigated', inicializar);
        if (document.readyState !== 'loading') inicializar();
    })();
</script>
@endpush
