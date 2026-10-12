@php
    $pesos = fn (?float $v, int $d = 2): string => $v === null ? '—' : ($v < 0 ? '-$' : '$').number_format(abs($v), $d);
    $num = fn (?float $v, int $d = 2): string => $v === null ? '—' : number_format($v, $d);
    $porKg = fn (?float $v): string => $v === null ? '—' : '$'.number_format($v, 2);
    // Clases completas por concepto: Tailwind solo ve literales en Blade.
    $colores = ['MOD' => 'bg-blue-600', 'MOI' => 'bg-indigo-400', 'GtsV' => 'bg-emerald-500', 'GtsF' => 'bg-amber-500',
        'Pf' => 'bg-rose-500', 'PV' => 'bg-cyan-500', 'Maquila' => 'bg-slate-500'];
    $sabDe = array_flip(\App\Livewire\Costos\CostoJulios::SABANA);
    $pct = fn (float $parte, float $total): string => $total > 0 ? number_format($parte / $total * 100, 1).'%' : '—';
@endphp
{{-- Raíz sin scroll: el spinner queda al centro de lo visible, no del contenido desplazado. --}}
<div class="relative flex min-h-0 flex-1 flex-col">
<div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto pb-2">
    @php
        $diferencia = $cuadre === null ? null : array_sum(array_map(fn ($c) => $c['julios'] - $c['sab'], $cuadre));
        [$cuadreTono, $cuadreTexto] = match (true) {
            $cuadre === null => ['bg-slate-100 text-slate-600', 'Sin cuota real'],
            abs($diferencia) < 1 => ['bg-emerald-100 text-emerald-800', 'Cuadra con AX'],
            default => ['bg-amber-100 text-amber-800', 'Diferencia con AX: '.$pesos($diferencia, 0)],
        };
    @endphp
    {{-- Periodo y estado del mes en una sola barra. Solo los meses con costo; cada uno dice cuántos julios tiene. --}}
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
        <div class="w-24 shrink-0">
            <flux:select wire:model.live="anio" aria-label="Año">
                @foreach ($anios as $a)
                    <flux:select.option value="{{ $a }}">{{ $a }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-56 shrink-0">
            <flux:select wire:model.live="mes" aria-label="Mes">
                @forelse ($meses as $n => $info)
                    <flux:select.option value="{{ $n }}">{{ \App\Models\Costos\CosCuota::MESES[$n] }} · {{ number_format($info['julios']) }} julios</flux:select.option>
                @empty
                    <flux:select.option value="{{ $mes }}">Sin meses con costo</flux:select.option>
                @endforelse
            </flux:select>
        </div>

        @if ($mesTotal['julios'] > 0)
            <span class="text-sm text-slate-500">{{ number_format($folios->count()) }} folios</span>
            <span class="rounded-full px-2.5 py-0.5 text-sm font-semibold {{ $cuadreTono }}"
                  title="Suma del costo de los julios contra la sábana de gastos de AX">{{ $cuadreTexto }}</span>
        @endif

        <flux:button icon="arrow-down-tray" class="ms-auto min-h-touch shrink-0" wire:click="abrirExportar" :disabled="$meses === []">Exportar</flux:button>
    </div>

    @if ($meses === [] || $mesTotal['julios'] === 0)
        <div class="rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center text-slate-500">
            <i class="fa-solid fa-inbox mb-2 text-3xl text-slate-300" aria-hidden="true"></i>
            <p>{{ $meses === [] ? "No hay meses con costo en {$anio}: corre Calcular Urdido." : 'No hay julios urdidos en '.mb_strtolower($nombreMes)." de {$anio}." }}</p>
        </div>
    @else
        <div class="grid gap-3 lg:grid-cols-3">
            {{-- Resumen del mes: el costo por kg y de qué se compone. --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Resumen del mes">
                <p class="text-sm text-slate-500">Costo por kg</p>
                <p class="text-3xl font-bold tabular-nums text-blue-700">{{ $porKg($mesTotal['totalKg']) }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm tabular-nums">
                    <dt class="text-slate-500">MP</dt><dd class="text-end">{{ $porKg($mesTotal['mpKg']) }}/kg <span class="ms-1 inline-block w-12 text-slate-500">{{ $pct($mesTotal['mp'], $mesTotal['total']) }}</span></dd>
                    <dt class="text-slate-500">Conversión</dt><dd class="text-end">{{ $porKg($mesTotal['conversionKg']) }}/kg <span class="ms-1 inline-block w-12 text-slate-500">{{ $pct($mesTotal['conversion'], $mesTotal['total']) }}</span></dd>
                    <dt class="border-t border-slate-100 pt-1.5 text-slate-500">Cantidad</dt><dd class="border-t border-slate-100 pt-1.5 text-end">{{ $num($mesTotal['kg'], 0) }} kg</dd>
                    <dt class="text-slate-500">Costo total</dt><dd class="text-end font-semibold">{{ $pesos($mesTotal['total'], 0) }}</dd>
                </dl>
            </section>

            {{-- Conversión por concepto. --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="cj-conceptos">
                <h3 id="cj-conceptos" class="text-sm text-slate-500">Conversión <span class="font-semibold text-slate-800">{{ $pesos($mesTotal['conversion'], 0) }}</span></h3>
                @if ($mesTotal['conversion'] > 0)
                    <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Reparto del costo de conversión por concepto">
                        @foreach ($conceptos as $col => $nombre)
                            <div class="{{ $colores[$col] }}" style="width: {{ max(0, $mesTotal[$col] / $mesTotal['conversion'] * 100) }}%"></div>
                        @endforeach
                    </div>
                @endif
                {{-- Julios = lo cobrado a los julios; Sábana = gasto de AX del mes. En ámbar si difieren por $5 o más (menos es redondeo). --}}
                <table class="mt-3 w-full text-sm tabular-nums">
                    <thead class="text-caption text-slate-500">
                        <tr>
                            <th class="pb-1 text-start font-normal">Concepto</th>
                            <th class="pb-1 text-end font-normal">Julios</th>
                            <th class="pb-1 text-end font-normal">Sábana AX</th>
                            <th class="pb-1 text-end font-normal">%</th>
                            <th class="pb-1 text-end font-normal">$/kg</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($conceptos as $col => $nombre)
                            @php $sab = isset($sabDe[$col]) ? ($cuadre[$nombre]['sab'] ?? null) : null; @endphp
                            <tr>
                                <td class="py-1"><span class="me-1.5 inline-block size-2.5 rounded-sm {{ $colores[$col] }}" aria-hidden="true"></span>{{ $nombre }}</td>
                                <td class="py-1 text-end">{{ $pesos($mesTotal[$col], 0) }}</td>
                                <td class="py-1 text-end {{ $sab !== null && abs($sab - $mesTotal[$col]) >= 5 ? 'font-semibold text-amber-700' : 'text-slate-500' }}"
                                    @if ($sab !== null) title="Diferencia: {{ $pesos($mesTotal[$col] - $sab) }}" @endif>{{ $pesos($sab, 0) }}</td>
                                <td class="py-1 text-end">{{ $pct($mesTotal[$col], $mesTotal['conversion']) }}</td>
                                <td class="py-1 text-end text-slate-500">{{ $porKg($mesTotal['kg'] > 0 ? $mesTotal[$col] / $mesTotal['kg'] : null) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-2 text-slate-500">Sin costo de conversión en el mes.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($cuadre !== null)
                        <tfoot class="border-t border-slate-200 font-semibold">
                            <tr>
                                <td class="pt-1.5">Total</td>
                                <td class="pt-1.5 text-end">{{ $pesos($mesTotal['conversion'], 0) }}</td>
                                <td class="pt-1.5 text-end">{{ $pesos(array_sum(array_column($cuadre, 'sab')), 0) }}</td>
                                <td class="pt-1.5 text-end">100%</td>
                                <td class="pt-1.5 text-end">{{ $porKg($mesTotal['conversionKg']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </section>

            {{-- Por máquina: un clic filtra la tabla de abajo. --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="cj-maquinas">
                <h3 id="cj-maquinas" class="text-sm text-slate-500">Por máquina <span class="text-caption">· clic para filtrar</span></h3>
                <ul class="mt-2 text-sm tabular-nums">
                    @foreach ($porMaquina as $nombre => $m)
                        <li wire:key="cj-maq-{{ $nombre }}">
                            <button type="button" wire:click="filtrarMaquina(@js($nombre))" aria-pressed="{{ $maquina === $nombre ? 'true' : 'false' }}"
                                    class="flex min-h-touch w-full items-center gap-2 rounded-lg px-2 text-start transition-colors {{ $maquina === $nombre ? 'bg-blue-600 text-white' : 'hover:bg-blue-50' }}">
                                <span class="flex-1 font-semibold">{{ $nombre }}</span>
                                <span class="{{ $maquina === $nombre ? 'text-blue-100' : 'text-slate-500' }}">{{ number_format($m['julios']) }} julios</span>
                                <span class="w-14 text-end {{ $maquina === $nombre ? 'text-blue-100' : 'text-slate-500' }}" title="Parte del costo total del mes">{{ $pct($m['total'], $mesTotal['total']) }}</span>
                                <span class="w-16 text-end font-semibold">{{ $porKg($m['totalKg']) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        {{-- MP de los julios contra el consumo del kardex de AX, por máquina. Kg en ámbar fuera de 90–110 %. --}}
        @if ($kardex !== null)
            @php
                $filasMp = collect($kardex)->map(fn ($k, $m) => ['kardex' => $k, 'julios' => $porMaquina[$m] ?? ['kg' => 0.0, 'mp' => 0.0]]);
                $totalMp = ['kardex' => ['kg' => $filasMp->sum('kardex.kg'), 'imp' => $filasMp->sum('kardex.imp')], 'julios' => ['kg' => $filasMp->sum('julios.kg'), 'mp' => $filasMp->sum('julios.mp')]];
            @endphp
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="cj-kardex">
                <h3 id="cj-kardex" class="text-sm text-slate-500">MP contra kardex de AX
                    <span class="text-caption">· el kardex es el hilo que salió del almacén; 2–6 % menos en los julios es la merma</span></h3>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="text-caption text-slate-500">
                            <tr>
                                <th class="pb-1 text-start font-normal">Máquina</th>
                                <th class="pb-1 text-end font-normal">Kg julios</th>
                                <th class="pb-1 text-end font-normal">Kg kardex</th>
                                <th class="pb-1 text-end font-normal">%</th>
                                <th class="pb-1 text-end font-normal">MP julios</th>
                                <th class="pb-1 text-end font-normal">MP kardex</th>
                                <th class="pb-1 text-end font-normal">$/kg julios</th>
                                <th class="pb-1 text-end font-normal">$/kg kardex</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([...$filasMp->all(), 'Total' => $totalMp] as $nombre => $f)
                                @php
                                    $pctKg = $f['kardex']['kg'] > 0 ? $f['julios']['kg'] / $f['kardex']['kg'] * 100 : null;
                                    $fuera = $pctKg === null || $pctKg < 90 || $pctKg > 110;
                                @endphp
                                <tr class="{{ $nombre === 'Total' ? 'border-t border-slate-200 font-semibold' : '' }}">
                                    <td class="py-1">{{ $nombre }}</td>
                                    <td class="py-1 text-end">{{ $num($f['julios']['kg'], 0) }}</td>
                                    <td class="py-1 text-end">{{ $num($f['kardex']['kg'], 0) }}</td>
                                    <td class="py-1 text-end {{ $fuera ? 'font-semibold text-amber-700' : '' }}">{{ $pctKg === null ? '—' : number_format($pctKg, 1).'%' }}</td>
                                    <td class="py-1 text-end">{{ $pesos($f['julios']['mp'], 0) }}</td>
                                    <td class="py-1 text-end">{{ $pesos($f['kardex']['imp'], 0) }}</td>
                                    <td class="py-1 text-end">{{ $porKg($f['julios']['kg'] > 0 ? $f['julios']['mp'] / $f['julios']['kg'] : null) }}</td>
                                    <td class="py-1 text-end">{{ $porKg($f['kardex']['kg'] > 0 ? $f['kardex']['imp'] / $f['kardex']['kg'] : null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Detalle: folios (orden de urdido) que se despliegan en sus julios. --}}
        <section class="flex flex-col rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="cj-detalle"
                 x-data="{ abiertos: {}, todos: false }">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/60 px-3 py-2.5">
                <h3 id="cj-detalle" class="me-2 font-semibold text-slate-800">Julios del mes</h3>
                <label class="relative min-w-0 flex-1 sm:max-w-xs">
                    <span class="sr-only">Buscar folio, julio o artículo</span>
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
                    <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Folio, julio, cuenta, fibra…"
                           class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm">
                </label>
                @if ($maquina !== '')
                    <flux:badge color="blue" class="min-h-touch">
                        {{ $maquina }}
                        <flux:badge.close wire:click="filtrarMaquina(@js($maquina))" aria-label="Quitar filtro de máquina" />
                    </flux:badge>
                @endif
                <flux:button size="sm" variant="ghost" class="ms-auto min-h-touch" x-on:click="todos = !todos; abiertos = {}"
                             x-bind:aria-pressed="todos.toString()">
                    <span x-text="todos ? 'Contraer todo' : 'Desplegar todo'">Desplegar todo</span>
                </flux:button>
            </div>

            <div class="max-h-[75dvh] overflow-auto">
                <table class="w-full text-sm tabular-nums">
                    <thead class="sticky top-0 z-10 bg-blue-600 text-white">
                        <tr class="text-caption">
                            <th class="px-3 py-2 text-start font-semibold">Folio / julio</th>
                            <th class="px-2 py-2 text-start font-semibold">Fecha</th>
                            <th class="px-2 py-2 text-start font-semibold">Máquina</th>
                            <th class="px-2 py-2 text-start font-semibold">Artículo</th>
                            <th class="px-2 py-2 text-end font-semibold" title="Tiempo de producción en minutos">T. prod</th>
                            <th class="px-2 py-2 text-end font-semibold" title="Minutos de paro de la máquina durante el julio (sin tiempo muerto)">T. paro</th>
                            <th class="px-2 py-2 text-end font-semibold" title="Kg neto">Cantidad</th>
                            <th class="px-2 py-2 text-end font-semibold" title="Materia prima">MP</th>
                            @foreach ($conceptos as $col => $nombre)
                                <th class="px-2 py-2 text-end font-semibold" title="{{ $nombre }}">{{ $col }}</th>
                            @endforeach
                            <th class="px-2 py-2 text-end font-semibold">Costo total</th>
                            <th class="bg-blue-700 px-3 py-2 text-end font-semibold">$/kg</th>
                        </tr>
                    </thead>
                    @forelse ($folios as $folio => $f)
                        @php $k = \Illuminate\Support\Js::from((string) $folio); @endphp
                        <tbody wire:key="cj-folio-{{ $folio }}" class="border-b border-slate-200">
                            {{-- Renglón del folio: subtotal de sus julios; clic para desplegar. --}}
                            <tr class="cursor-pointer bg-slate-50 font-semibold hover:bg-blue-50" tabindex="0"
                                x-on:click="abiertos[{{ $k }}] = !(todos || abiertos[{{ $k }}])"
                                x-on:keydown.enter.prevent="abiertos[{{ $k }}] = !(todos || abiertos[{{ $k }}])"
                                x-bind:aria-expanded="(todos || !!abiertos[{{ $k }}]).toString()">
                                <td class="whitespace-nowrap px-3 py-2">
                                    <i class="fa-solid fa-chevron-right me-1.5 text-xs text-slate-400 transition-transform"
                                       x-bind:class="(todos || abiertos[{{ $k }}]) && 'rotate-90'" aria-hidden="true"></i>
                                    {{ $folio }}
                                    <span class="ms-1 rounded-full bg-slate-200 px-2 py-0.5 text-caption font-medium text-slate-600">{{ $f['julios']->count() }} julios</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 font-normal text-slate-600">{{ $f['fecha'] }}</td>
                                <td class="whitespace-nowrap px-2 py-2">{{ $f['maquina'] }}</td>
                                <td class="whitespace-nowrap px-2 py-2 font-normal">
                                    {{ $f['articulo'] }}
                                    @if ($f['rizoPie'])
                                        <span class="ms-1 rounded px-1.5 py-0.5 text-caption {{ match ($f['rizoPie']) { 'Rizo' => 'bg-blue-100 text-blue-800', 'Pie' => 'bg-amber-100 text-amber-800', default => 'bg-slate-100 text-slate-700' } }}">{{ $f['rizoPie'] }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-end">{{ $num($f['minutos'], 0) }}</td>
                                <td class="px-2 py-2 text-end {{ $f['paro'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">{{ $num($f['paro'], 0) }}</td>
                                <td class="px-2 py-2 text-end">{{ $num($f['kg']) }}</td>
                                <td class="px-2 py-2 text-end">{{ $pesos($f['mp']) }}</td>
                                @foreach ($conceptos as $col => $nombre)
                                    <td class="px-2 py-2 text-end">{{ $pesos($f[$col]) }}</td>
                                @endforeach
                                <td class="px-2 py-2 text-end">{{ $pesos($f['total']) }}</td>
                                <td class="bg-blue-50 px-3 py-2 text-end text-blue-800">{{ $porKg($f['totalKg']) }}</td>
                            </tr>
                            @foreach ($f['julios'] as $j)
                                @php $conv = array_sum(array_map(fn ($c) => (float) $j[$c], array_keys($conceptos))); @endphp
                                <tr x-show="todos || abiertos[{{ $k }}]" x-cloak wire:key="cj-julio-{{ $j['id'] }}" class="text-slate-700 hover:bg-slate-50">
                                    <td class="whitespace-nowrap py-1.5 pe-3 ps-10">
                                        Julio <strong>{{ $j['julio'] }}</strong>
                                        <span class="ms-1 text-caption text-slate-500">{{ $j['horario'] }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-500">{{ $j['fecha'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-500">{{ $j['hilos'] ? number_format((float) $j['hilos']).' hilos' : '' }}</td>
                                    <td></td>
                                    <td class="px-2 py-1.5 text-end">{{ $num($j['minutos'], 0) }}</td>
                                    <td class="px-2 py-1.5 text-end {{ $j['paro'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">{{ $num($j['paro'], 0) }}</td>
                                    <td class="px-2 py-1.5 text-end">{{ $num($j['kg']) }}</td>
                                    <td class="px-2 py-1.5 text-end">{{ $pesos($j['mp']) }}</td>
                                    @foreach ($conceptos as $col => $nombre)
                                        <td class="px-2 py-1.5 text-end {{ $j[$col] === null ? 'text-amber-600' : '' }}">{{ $pesos($j[$col]) }}</td>
                                    @endforeach
                                    <td class="px-2 py-1.5 text-end">{{ $pesos((float) $j['mp'] + $conv) }}</td>
                                    <td class="px-3 py-1.5 text-end">{{ $porKg($j['kg'] ? ((float) $j['mp'] + $conv) / $j['kg'] : null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="{{ 10 + count($conceptos) }}" class="py-10 text-center text-slate-500">Ningún julio coincide con la búsqueda.</td></tr>
                        </tbody>
                    @endforelse
                    <tfoot class="sticky bottom-0 z-10 bg-slate-800 font-semibold text-white">
                        <tr>
                            <td class="px-3 py-2" colspan="4">
                                Total {{ $buscar !== '' || $maquina !== '' ? 'filtrado' : 'del mes' }}
                                <span class="ms-1 font-normal text-slate-300">· {{ number_format($filtroTotal['julios']) }} julios</span>
                            </td>
                            <td class="px-2 py-2 text-end">{{ $num($filtroTotal['minutos'], 0) }}</td>
                            <td class="px-2 py-2 text-end">{{ $num($filtroTotal['paro'], 0) }}</td>
                            <td class="px-2 py-2 text-end">{{ $num($filtroTotal['kg']) }}</td>
                            <td class="px-2 py-2 text-end">{{ $pesos($filtroTotal['mp']) }}</td>
                            @foreach ($conceptos as $col => $nombre)
                                <td class="px-2 py-2 text-end">{{ $pesos($filtroTotal[$col]) }}</td>
                            @endforeach
                            <td class="px-2 py-2 text-end">{{ $pesos($filtroTotal['total']) }}</td>
                            <td class="bg-blue-700 px-3 py-2 text-end">{{ $porKg($filtroTotal['totalKg']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif
</div>

{{-- Exportar: rango de meses del año en pantalla. Mismo <dialog> que los de Cuotas. --}}
@if ($rangoExcel !== null)
    <dialog wire:key="cj-exportar" wire:ignore.self x-data x-init="$el.showModal()"
            x-on:close="$wire.set('rangoExcel', null)" x-on:click="if ($event.target === $el) $el.close()"
            aria-labelledby="cj-exportar-titulo" class="ui-dialogo ui-dialogo--formulario">
        <form wire:submit="exportar" class="ui-dialogo__cuerpo" novalidate>
            <flux:heading id="cj-exportar-titulo" size="xl">Exportar costo por julio</flux:heading>
            <flux:text>Un renglón por julio de los meses elegidos de {{ $anio }}, con todos los decimales.</flux:text>
            <div class="grid grid-cols-2 gap-4">
                @foreach (['desde' => 'Desde', 'hasta' => 'Hasta'] as $campo => $etiqueta)
                    <flux:select wire:model="rangoExcel.{{ $campo }}" :label="$etiqueta">
                        @foreach (array_keys($meses) as $n)
                            <flux:select.option value="{{ $n }}">{{ \App\Models\Costos\CosCuota::MESES[$n] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endforeach
            </div>
            <x-dialogo-botones accion="exportar" texto="Descargar" />
        </form>
    </dialog>
@endif

{{-- Spinner al centro mientras se leen los julios de otro mes, la búsqueda o la máquina. --}}
<div wire:loading.delay.flex wire:target="anio,mes,costeo,buscar,filtrarMaquina"
     class="absolute inset-0 z-30 hidden items-center justify-center rounded-xl bg-white/60">
    <x-ui.spinner size="lg" :inline="true" />
</div>
</div>
