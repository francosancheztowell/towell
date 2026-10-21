@extends('layouts.app')

@section('title', 'OEE Urdido')
@section('page-title', 'OEE Urdido')

@php
    $num = fn (float $v, int $d = 0) => number_format($v, $d);
    $opcionesMes = collect(range(0, 17))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
@endphp

@section('navbar-right')
    @if ($reporte)
        <flux:button href="{{ route('urdido.reportes.urdido.oee.excel', request()->query()) }}" icon="arrow-down-tray" class="min-h-touch">
            Excel
        </flux:button>
    @endif
@endsection

@section('content')
    <div class="pantalla-completa bg-white">
        <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-auto p-2 pb-6 md:px-4">
            {{-- Consulta: meses (varios). Siempre solo julios finalizados; cuentan todos los paros de Urdido. --}}
            <form id="oee-consulta" method="GET" action="{{ route('urdido.reportes.urdido.oee') }}" class="flex shrink-0 flex-wrap items-end gap-x-6 gap-y-3">
                <div class="w-full sm:w-80">
                    <flux:label for="oee-meses" class="mb-1.5">Meses</flux:label>
                    <select id="oee-meses" name="meses[]" multiple>
                        @foreach ($opcionesMes as $m)
                            <option value="{{ $m->format('Y-m') }}" @selected(in_array($m->format('Y-m'), $meses, true))>
                                {{ ucfirst($m->locale('es')->translatedFormat('F Y')) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <flux:button type="submit" variant="primary" icon="magnifying-glass" class="min-h-touch">Consultar</flux:button>
            </form>

            @if (! $reporte)
                <div class="rounded-xl border border-dashed border-slate-300 py-16 text-center text-slate-500">
                    <i class="fa-solid fa-calendar-days mb-2 text-3xl text-slate-300" aria-hidden="true"></i>
                    <p>Elige uno o más meses. El reporte toma solo julios finalizados.</p>
                </div>
            @else
                {{-- Resumen del periodo. Sin Calidad ni OEE por ahora (decisión del owner): solo lo medido, para analizarlo. --}}
                <section class="grid shrink-0 grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 tabular-nums md:grid-cols-5" aria-label="Resumen del periodo">
                    <div class="bg-white px-4 py-3">
                        <p class="text-sm text-slate-500">Julios</p>
                        <p class="text-2xl font-semibold text-slate-900">{{ $num($reporte['tot']['julios']) }}</p>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <p class="text-sm text-slate-500">Kg</p>
                        <p class="text-2xl font-semibold text-slate-900">{{ $num($reporte['tot']['kg'], 1) }}</p>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <p class="text-sm text-slate-500">Min. producción</p>
                        <p class="text-2xl font-semibold text-slate-900">{{ $num($reporte['tot']['minutos']) }}</p>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <p class="text-sm text-slate-500">Min. paro necesario</p>
                        <p class="text-2xl font-semibold text-slate-900">{{ $num($reporte['tot']['paro_nec']) }}</p>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <p class="text-sm text-slate-500">Min. oportunidad</p>
                        <p class="text-2xl font-semibold text-slate-900">{{ $num($reporte['tot']['paro_oport']) }}</p>
                    </div>
                </section>

                @foreach ($reporte['meses'] as $mes)
                    <section class="shrink-0" aria-label="{{ $mes['etiqueta'] }}">
                        <div class="mb-2 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                            <flux:heading size="lg" level="2">{{ $mes['etiqueta'] }}</flux:heading>
                            <p class="text-sm text-slate-500 tabular-nums">
                                {{ $num($mes['tot']['julios']) }} julios · {{ $num($mes['tot']['kg'], 1) }} kg
                            </p>
                        </div>

                        {{-- Sin overflow-hidden: anularía el sticky de los renglones de máquina. --}}
                        <div class="divide-y divide-slate-200 rounded-xl border border-slate-200 [&>details:first-child>summary]:rounded-t-xl [&>details:last-child:not([open])>summary]:rounded-b-xl">
                            @forelse ($mes['maquinas'] as $maq)
                                <details class="group">
                                    {{-- Una máquina por renglón. Anchos fijos: los números de todas las máquinas quedan en columna.
                                         Abierta se pinta de azul para separarla de las demás. --}}
                                    <summary class="sticky top-0 z-20 grid min-h-touch sm:h-16 cursor-pointer list-none grid-cols-2 bg-white items-center gap-x-6 gap-y-2 px-4 py-2.5 hover:bg-slate-50 group-open:bg-blue-600 group-open:text-white group-open:hover:bg-blue-600 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue-500 sm:grid-cols-[minmax(10rem,1fr)_repeat(5,8rem)] [&::-webkit-details-marker]:hidden">
                                        <span class="col-span-2 flex items-center gap-3 sm:col-span-1">
                                            <i class="fa-solid fa-chevron-right w-3 text-xs text-slate-400 transition-transform group-open:rotate-90 group-open:text-blue-100" aria-hidden="true"></i>
                                            <i class="fa-solid fa-gears text-blue-600 group-open:text-blue-100" aria-hidden="true"></i>
                                            <span class="text-base font-semibold text-slate-900 group-open:text-white">{{ $maq['nombre'] }}</span>
                                        </span>
                                        @foreach ([['Julios', $num($maq['tot']['julios'])], ['Kg', $num($maq['tot']['kg'], 1)],
                                            ['Min prod.', $num($maq['tot']['minutos'])], ['Paro nec.', $num($maq['tot']['paro_nec'])],
                                            ['Oportunidad', $num($maq['tot']['paro_oport'])]] as [$etq, $valor])
                                            <span class="tabular-nums sm:text-right">
                                                <span class="block text-caption text-slate-500 group-open:text-blue-100">{{ $etq }}</span>
                                                <span class="block font-semibold text-slate-900 group-open:text-white">{{ $valor }}</span>
                                            </span>
                                        @endforeach
                                    </summary>

                                    @if ($maq['julios'] === [])
                                        <p class="border-t border-slate-200 px-4 py-3 text-sm text-slate-500">Sin julios finalizados en el mes: solo paro.</p>
                                    @else
                                        {{-- Tabla HTML simple (no flux:table.cell): con ~800 julios por mes, 15 componentes por renglón
                                             tardaban 7 s y 7.5 MB por mes. Los estilos de celda viven en la <table> ([&_td]) para no
                                             repetirse en cada celda. .n = número (derecha); .g = inicia bloque (medidas | calidad). --}}
                                        {{-- Sin overflow propio: un solo scroll (el de la página) para que el encabezado se pegue bajo el renglón
                                             de la máquina (sm:h-16 → top-16). Dos scrolls anidados lo escondían. --}}
                                        <div>
                                            <table class="w-full border-collapse whitespace-nowrap text-sm tabular-nums text-slate-700
                                                [&_td]:border [&_td]:border-slate-200 [&_td]:px-3 [&_td]:py-1.5
                                                [&_th]:border [&_th]:px-3 [&_.n]:text-right [&_.g]:border-l-2 [&_.g]:border-l-slate-300">
                                                <thead class="sticky top-[7.5rem] z-10 bg-slate-100 sm:top-16 text-left text-sm font-semibold text-slate-700 shadow-[0_2px_0_var(--color-slate-300)]">
                                                    <tr class="[&>th]:border-slate-200 [&>th]:border-b-2 [&>th]:border-b-slate-300 [&>th]:py-2">
                                                        @foreach (['Orden', 'Julio', 'Config.', 'Tamaño'] as $col)
                                                            <th>{{ $col }}</th>
                                                        @endforeach
                                                        <th class="n g">Kg</th>
                                                        <th class="n">Mts</th>
                                                        <th>H. inicio</th>
                                                        <th>H. final</th>
                                                        <th class="n">Min prod.</th>
                                                        <th class="n">Paro nec.</th>
                                                        <th class="n">Oport.</th>
                                                        <th class="g">Defecto</th>
                                                        <th class="n">Pen.</th>
                                                        <th>Oficial</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach (collect($maq['julios'])->groupBy('dia') as $dia => $delDia)
                                                        {{-- Fila del día arriba de sus julios (en vez de repetirlo en cada renglón). --}}
                                                        <tr class="bg-slate-200/70 text-slate-800 [&>td]:border-slate-300">
                                                            <td colspan="14" class="py-1! font-semibold">
                                                                <i class="fa-regular fa-calendar me-1.5 text-slate-500" aria-hidden="true"></i>{{ $dia }}
                                                                <span class="ms-2 font-normal text-slate-600">· {{ $delDia->count() }} julios · {{ $num($delDia->sum('kg'), 1) }} kg</span>
                                                            </td>
                                                        </tr>
                                                        @foreach ($delDia as $j)
                                                            <tr class="even:bg-slate-50 hover:bg-blue-50">
                                                                <td>{{ $j['orden'] }}</td>
                                                                <td>{{ $j['julio'] }}</td>
                                                                <td>{{ $j['config'] }}</td>
                                                                <td>{{ $j['tamano'] }}</td>
                                                                <td class="n g">{{ $num($j['kg'], 1) }}</td>
                                                                <td class="n">{{ $num($j['metros']) }}</td>
                                                                <td>{{ $j['hora'] }}</td>
                                                                <td>{{ $j['hora_fin'] }}</td>
                                                                <td class="n">{{ $num($j['minutos']) }}</td>
                                                                <td class="n">{{ $num($j['paro_nec'], 1) }}</td>
                                                                <td class="n">{{ $num($j['paro_oport'], 1) }}</td>
                                                                <td class="g">
                                                                    @if ($j['defecto'])
                                                                        <span title="{{ $j['defecto_nombre'] }}" class="cursor-help rounded-md bg-red-400/15 px-1.5 py-0.5 text-xs font-medium text-red-700">{{ $j['defecto'] }}</span>
                                                                    @endif
                                                                </td>
                                                                <td class="n">{{ $j['penalizacion'] }}</td>
                                                                <td>{{ $j['oficiales'] }}</td>
                                                            </tr>
                                                        @endforeach
                                                    @endforeach
                                                    {{-- El paro del total puede pasar la suma de los julios: incluye semanas sin julios. --}}
                                                    <tr class="bg-slate-100 font-semibold text-slate-900 [&>td]:border-t-2 [&>td]:border-t-slate-300">
                                                        <td colspan="4">Total {{ $maq['nombre'] }}</td>
                                                        <td class="n g">{{ $num($maq['tot']['kg'], 1) }}</td>
                                                        <td class="n">{{ $num($maq['tot']['metros']) }}</td>
                                                        <td colspan="2"></td>
                                                        <td class="n">{{ $num($maq['tot']['minutos']) }}</td>
                                                        <td class="n">{{ $num($maq['tot']['paro_nec'], 1) }}</td>
                                                        <td class="n">{{ $num($maq['tot']['paro_oport'], 1) }}</td>
                                                        <td colspan="2" class="g"></td>
                                                        <td></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </details>
                            @empty
                                <p class="px-4 py-3 text-sm text-slate-500">Sin julios finalizados ni paros en el mes.</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/modulos/urdido/reportes-oee/index.ts')
@endpush
