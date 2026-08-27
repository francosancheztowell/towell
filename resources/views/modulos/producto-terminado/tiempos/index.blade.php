@extends('layouts.app')

@php
    // TEMPORAL: helpers de presentación para los datos simulados.
    $chipEstatusOd = [
        'En preparación' => 'border-blue-200 bg-blue-50 text-blue-700',
        'Programada' => 'border-slate-200 bg-slate-100 text-slate-600',
        'Detenida' => 'border-amber-200 bg-amber-50 text-amber-700',
    ];

    $formatoDuracion = static fn (int $minutos): string => intdiv($minutos, 60) . 'h ' . str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT) . 'm';
@endphp

@section('page-title')
    <x-layout.page-title title="Tiempos Preparación" />
@endsection

@section('content')
<div class="flex flex-col gap-3 p-3 md:h-[calc(100vh-64px)] md:overflow-hidden md:p-4">

    {{-- Resumen superior: se mantiene compacto para no robarle altura a los paneles. --}}
    <div class="grid shrink-0 grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
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
    </div>

    {{--
        Grid de los 3 paneles:
        · Móvil  (<768px)  → 1 columna, cada panel con altura propia y scroll de página.
        · Tablet (≥768px)  → 2 columnas: distribución y compras arriba, cerradas abajo a lo ancho.
        · Desktop(≥1280px) → 3 columnas lado a lado sobre 12 unidades (4 / 5 / 3).
    --}}
    <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 md:grid-cols-2 md:grid-rows-[minmax(0,3fr)_minmax(0,2fr)] xl:grid-cols-12 xl:grid-rows-1">

        {{-- ============ 1. Órdenes de distribución ============ --}}
        <section class="flex min-h-[20rem] min-w-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:min-h-0 xl:col-span-4">
            <header class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex min-w-0 items-center gap-2">
                    <i class="fa-solid fa-truck-ramp-box text-blue-600"></i>
                    <h2 class="truncate text-sm font-bold uppercase tracking-wide text-slate-700">Órdenes de distribución</h2>
                </div>
                <span class="shrink-0 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-700">{{ count($ordenesDistribucion) }}</span>
            </header>

            <div class="min-h-0 flex-1 overflow-auto overscroll-contain" tabindex="0" aria-label="Listado de órdenes de distribución">
                <table class="w-full min-w-[34rem] border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 shadow-sm">
                        <tr>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5">Folio</th>
                            <th class="bg-slate-50 px-4 py-2.5">Cliente / destino</th>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5 text-right">Piezas</th>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5">Estatus</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-distribucion" class="divide-y divide-slate-100" role="listbox" aria-label="Órdenes de distribución">
                        @foreach ($ordenesDistribucion as $indice => $orden)
                            <tr class="fila-distribucion cursor-pointer transition hover:bg-blue-50/60 focus:bg-blue-50 focus:outline-none aria-selected:bg-blue-50 aria-selected:ring-1 aria-selected:ring-inset aria-selected:ring-blue-400"
                                data-indice="{{ $indice }}"
                                role="option"
                                aria-selected="false"
                                tabindex="0">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">{{ $orden['folio'] }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $orden['cliente'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $orden['destino'] }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700">{{ number_format($orden['piezas']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $chipEstatusOd[$orden['estatus']] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">
                                        {{ $orden['estatus'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ============ 2. Órdenes de compra de la distribución seleccionada ============ --}}
        <section class="flex min-h-[20rem] min-w-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:min-h-0 xl:col-span-5">
            <header class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex min-w-0 items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-indigo-600"></i>
                    <div class="min-w-0">
                        <h2 class="truncate text-sm font-bold uppercase tracking-wide text-slate-700">Órdenes de compra</h2>
                        <p id="compras-subtitulo" class="truncate text-xs text-slate-500">Seleccione una distribución</p>
                    </div>
                </div>
                <span id="compras-conteo" class="shrink-0 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-bold text-indigo-700">0</span>
            </header>

            <div class="min-h-0 flex-1 overflow-auto overscroll-contain" tabindex="0" aria-label="Órdenes de compra de la distribución seleccionada">
                <table class="w-full min-w-[38rem] border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 shadow-sm">
                        <tr>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5">OC</th>
                            <th class="bg-slate-50 px-4 py-2.5">Artículo</th>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5 text-right">Surtido</th>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5">Compromiso</th>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5">Estatus</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-compras" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </section>

        {{-- ============ 3. Órdenes de distribución cerradas ============ --}}
        <section class="flex min-h-[20rem] min-w-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:col-span-2 md:min-h-0 xl:col-span-3">
            <header class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex min-w-0 items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                    <h2 class="truncate text-sm font-bold uppercase tracking-wide text-slate-700">Distribuciones cerradas</h2>
                </div>
                <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">{{ count($ordenesCerradas) }}</span>
            </header>

            <div class="min-h-0 flex-1 overflow-auto overscroll-contain" tabindex="0" aria-label="Distribuciones cerradas">
                <table class="w-full min-w-[30rem] border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 shadow-sm">
                        <tr>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5">Folio</th>
                            <th class="bg-slate-50 px-4 py-2.5">Cliente</th>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5 text-right">Piezas</th>
                            <th class="whitespace-nowrap bg-slate-50 px-4 py-2.5 text-right">Preparación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($ordenesCerradas as $cerrada)
                            <tr class="transition hover:bg-emerald-50/50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $cerrada['folio'] }}</p>
                                    <p class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($cerrada['cierre'])->format('d/m/Y H:i') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-800">{{ $cerrada['cliente'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $cerrada['compras'] }} OC</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700">{{ number_format($cerrada['piezas']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <span class="font-semibold tabular-nums text-emerald-700">{{ $formatoDuracion($cerrada['minutos']) }}</span>
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
            return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : escapar(iso);
        }

        function filaVacia(mensaje) {
            return '<tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">' + escapar(mensaje) + '</td></tr>';
        }

        function pintarCompras(orden) {
            var cuerpo = document.getElementById('tabla-compras');
            var subtitulo = document.getElementById('compras-subtitulo');
            var conteo = document.getElementById('compras-conteo');
            if (!cuerpo) return;

            if (!orden) {
                cuerpo.innerHTML = filaVacia('Seleccione una orden de distribución para ver sus órdenes de compra.');
                if (subtitulo) subtitulo.textContent = 'Seleccione una distribución';
                if (conteo) conteo.textContent = '0';
                return;
            }

            if (subtitulo) subtitulo.textContent = orden.folio + ' · ' + orden.cliente;
            if (conteo) conteo.textContent = String(orden.compras.length);

            if (orden.compras.length === 0) {
                cuerpo.innerHTML = filaVacia('Esta distribución no tiene órdenes de compra registradas.');
                return;
            }

            cuerpo.innerHTML = orden.compras.map(function (compra) {
                var avance = compra.cantidad > 0 ? Math.round((compra.surtido / compra.cantidad) * 100) : 0;
                var chip = chipsCompra[compra.estatus] || chipsCompra['Pendiente'];

                return '' +
                    '<tr class="transition hover:bg-indigo-50/50">' +
                        '<td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">' + escapar(compra.folio) + '</td>' +
                        '<td class="px-4 py-3">' +
                            '<p class="text-slate-800">' + escapar(compra.articulo) + '</p>' +
                            '<p class="text-xs text-slate-500">' + escapar(compra.modelo) + '</p>' +
                        '</td>' +
                        '<td class="whitespace-nowrap px-4 py-3 text-right">' +
                            '<p class="tabular-nums text-slate-800">' + formatearNumero(compra.surtido) + ' / ' + formatearNumero(compra.cantidad) + '</p>' +
                            '<div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">' +
                                '<div class="h-full rounded-full bg-indigo-500" style="width: ' + avance + '%"></div>' +
                            '</div>' +
                        '</td>' +
                        '<td class="whitespace-nowrap px-4 py-3 text-slate-700">' + formatearFechaCorta(compra.compromiso) + '</td>' +
                        '<td class="whitespace-nowrap px-4 py-3">' +
                            '<span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold ' + chip + '">' + escapar(compra.estatus) + '</span>' +
                        '</td>' +
                    '</tr>';
            }).join('');
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
            if (!cuerpo || cuerpo.dataset.listenersBound === '1') return;
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
