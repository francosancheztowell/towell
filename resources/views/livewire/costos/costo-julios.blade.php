@php
    $pesos = fn (?float $v, int $d = 2): string => $v === null ? '—' : ($v < 0 ? '-$' : '$').number_format(abs($v), $d);
    $num = fn (?float $v, int $d = 2): string => $v === null ? '—' : number_format($v, $d);
    $porKg = fn (?float $v): string => $v === null ? '—' : '$'.number_format($v, 2);
    // Clases completas por concepto: Tailwind solo ve literales en Blade.
    $colores = ['MOD' => 'bg-blue-600', 'MOI' => 'bg-indigo-400', 'GtsV' => 'bg-emerald-500', 'GtsF' => 'bg-amber-500',
        'Pf' => 'bg-rose-500', 'PV' => 'bg-cyan-500', 'Maquila' => 'bg-slate-500'];
    $sabDe = array_flip(\App\Livewire\Costos\CostoJulios::SABANA);
    $mesCorto = fn (int $n): string => mb_substr(\App\Models\Costos\CosCuota::MESES[$n], 0, 3);
    $diferencia = $cuadre === null ? null : array_sum(array_map(fn ($c) => $c['julios'] - $c['sab'], $cuadre));
@endphp
{{-- Raíz sin scroll: el spinner queda al centro de lo visible, no del contenido desplazado. --}}
<div class="relative flex min-h-0 flex-1 flex-col">
<div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto pb-2">
    {{-- Periodo: año + los doce meses con cuántos julios tiene cada uno. Punto ámbar = julios sin costo. --}}
    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
        <div class="w-28 shrink-0">
        <flux:select wire:model.live="anio" aria-label="Año">
            @foreach ($anios as $a)
                <flux:select.option value="{{ $a }}">{{ $a }}</flux:select.option>
            @endforeach
        </flux:select>
        </div>

        <nav aria-label="Mes" class="flex min-w-0 flex-1 gap-1 overflow-x-auto [scrollbar-width:thin]">
            @foreach (\App\Models\Costos\CosCuota::MESES as $n => $nombre)
                @php $info = $meses[$n] ?? null; @endphp
                <button type="button" wire:click="$set('mes', {{ $n }})" @disabled($info === null)
                        aria-pressed="{{ $mes === $n ? 'true' : 'false' }}" title="{{ $nombre }}: {{ $info['julios'] ?? 0 }} julios{{ ($info['sinCosto'] ?? 0) > 0 ? ' · '.$info['sinCosto'].' sin costo' : '' }}"
                        class="relative flex min-h-touch min-w-16 shrink-0 flex-col items-center justify-center rounded-lg px-2 text-sm transition-colors
                               {{ $mes === $n ? 'bg-blue-600 text-white shadow' : 'text-slate-700 hover:bg-blue-50 disabled:text-slate-300 disabled:hover:bg-transparent' }}">
                    <span class="font-semibold">{{ $mesCorto($n) }}</span>
                    <span class="text-caption tabular-nums {{ $mes === $n ? 'text-blue-100' : 'text-slate-400' }}">{{ $info ? number_format($info['julios']) : '—' }}</span>
                    @if (($info['sinCosto'] ?? 0) > 0)
                        <span class="absolute right-1 top-1 size-2 rounded-full bg-amber-500" aria-hidden="true"></span>
                    @endif
                </button>
            @endforeach
        </nav>

        <flux:button icon="arrow-down-tray" class="min-h-touch shrink-0" wire:click="exportar" :disabled="$mesTotal['julios'] === 0">Excel</flux:button>
    </div>

    @if ($mesTotal['julios'] === 0)
        <div class="rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center text-slate-500">
            <i class="fa-solid fa-inbox mb-2 text-3xl text-slate-300" aria-hidden="true"></i>
            <p>No hay julios urdidos en {{ mb_strtolower($nombreMes) }} de {{ $anio }}.</p>
        </div>
    @else
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-1">
            <h2 class="text-xl font-bold text-slate-900">Costo de Urdido · {{ $nombreMes }} {{ $anio }}</h2>
            <p class="text-sm text-slate-500">
                {{ number_format($mesTotal['julios']) }} julios en {{ number_format($folios->count()) }} folios
                · costeo {{ $costeo === 'directo' ? 'directo (sin fijos)' : 'absorbente' }}
            </p>
        </div>

        @if ($avisos['sinCosto'] + $avisos['sinMp'] + $avisos['sinKg'] > 0)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Hay julios con datos incompletos</flux:callout.heading>
                <flux:callout.text>
                    @if ($avisos['sinCosto'])
                        <strong>{{ $avisos['sinCosto'] }}</strong> sin costo de conversión: corre <em>Calcular Urdido</em> para {{ mb_strtolower($nombreMes) }}.
                    @endif
                    @if ($avisos['sinMp'])
                        <strong>{{ $avisos['sinMp'] }}</strong> sin materia prima o en $0 (kardex).
                    @endif
                    @if ($avisos['sinKg'])
                        <strong>{{ $avisos['sinKg'] }}</strong> sin kg neto: no entran al $/kg.
                    @endif
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- Indicadores del mes completo (no cambian con la búsqueda). --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach ([
                ['Kg neto', $num($mesTotal['kg'], 0), 'kg urdidos', 'text-slate-900'],
                ['Costo total', $pesos($mesTotal['total'], 0), 'MP + conversión', 'text-slate-900'],
                ['Costo por kg', $porKg($mesTotal['totalKg']), 'promedio ponderado', 'text-blue-700'],
                ['Materia prima', $porKg($mesTotal['mpKg']).'/kg', $mesTotal['total'] > 0 ? number_format($mesTotal['mp'] / $mesTotal['total'] * 100, 1).'% del costo' : '', 'text-slate-900'],
                ['Conversión', $porKg($mesTotal['conversionKg']).'/kg', $pesos($mesTotal['conversion'], 0).' en el mes', 'text-slate-900'],
            ] as [$titulo, $valor, $nota, $color])
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-caption font-semibold uppercase tracking-wide text-slate-500">{{ $titulo }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums {{ $color }}">{{ $valor }}</p>
                    <p class="text-caption text-slate-500">{{ $nota }}</p>
                </div>
            @endforeach
            @php
                [$cuadreTono, $cuadreValor, $cuadreNota] = match (true) {
                    $cuadre === null => ['border-slate-200 bg-white text-slate-500', 'Sin cuota', 'no hay cuota real del mes'],
                    abs($diferencia) < 1 => ['border-emerald-200 bg-emerald-50 text-emerald-700', 'Cuadra', 'Σ julios = sábana de AX'],
                    default => ['border-amber-200 bg-amber-50 text-amber-700', $pesos($diferencia, 0), 'diferencia contra la sábana'],
                };
            @endphp
            <div class="rounded-xl border p-3 shadow-sm {{ $cuadreTono }}">
                <p class="text-caption font-semibold uppercase tracking-wide opacity-80">Cuadre</p>
                <p class="mt-1 flex items-center gap-1.5 text-2xl font-bold tabular-nums">
                    @if ($cuadre !== null && abs($diferencia) < 1)
                        <i class="fa-solid fa-circle-check text-xl" aria-hidden="true"></i>
                    @endif
                    {{ $cuadreValor }}
                </p>
                <p class="text-caption opacity-80">{{ $cuadreNota }}</p>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-5">
            {{-- Conversión por concepto, contra la sábana de la cuota real. --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2" aria-labelledby="cj-conceptos">
                <h3 id="cj-conceptos" class="font-semibold text-slate-800">Costo de conversión por concepto</h3>
                @if ($mesTotal['conversion'] > 0)
                    <div class="mt-3 flex h-3 overflow-hidden rounded-full bg-slate-100" role="img"
                         aria-label="Reparto del costo de conversión por concepto">
                        @foreach ($conceptos as $col => $nombre)
                            <div class="{{ $colores[$col] }}" style="width: {{ max(0, $mesTotal[$col] / $mesTotal['conversion'] * 100) }}%"></div>
                        @endforeach
                    </div>
                @endif
                <table class="mt-3 w-full text-sm">
                    <thead class="text-caption uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="pb-1 text-start font-semibold">Concepto</th>
                            <th class="pb-1 text-end font-semibold">Julios</th>
                            <th class="pb-1 text-end font-semibold">Sábana AX</th>
                            <th class="pb-1 text-end font-semibold">%</th>
                            <th class="pb-1 text-end font-semibold">$/kg</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 tabular-nums">
                        @forelse ($conceptos as $col => $nombre)
                            @php $sab = isset($sabDe[$col]) ? ($cuadre[$nombre]['sab'] ?? null) : null; @endphp
                            <tr>
                                <td class="py-1.5"><span class="me-2 inline-block size-2.5 rounded-sm {{ $colores[$col] }}" aria-hidden="true"></span>{{ $nombre }}</td>
                                <td class="py-1.5 text-end">{{ $pesos($mesTotal[$col], 0) }}</td>
                                <td class="py-1.5 text-end {{ $sab !== null && abs($sab - $mesTotal[$col]) >= 1 ? 'font-semibold text-amber-700' : 'text-slate-500' }}">{{ $pesos($sab, 0) }}</td>
                                <td class="py-1.5 text-end text-slate-500">{{ $mesTotal['conversion'] > 0 ? number_format($mesTotal[$col] / $mesTotal['conversion'] * 100, 1) : '—' }}</td>
                                <td class="py-1.5 text-end">{{ $porKg($mesTotal['kg'] > 0 ? $mesTotal[$col] / $mesTotal['kg'] : null) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-3 text-center text-slate-500">Sin costo de conversión en el mes.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 font-semibold tabular-nums">
                        <tr>
                            <td class="pt-1.5">Total conversión</td>
                            <td class="pt-1.5 text-end">{{ $pesos($mesTotal['conversion'], 0) }}</td>
                            <td class="pt-1.5 text-end text-slate-500">{{ $cuadre === null ? '—' : $pesos(array_sum(array_column($cuadre, 'sab')), 0) }}</td>
                            <td class="pt-1.5 text-end">100</td>
                            <td class="pt-1.5 text-end">{{ $porKg($mesTotal['conversionKg']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            {{-- Por máquina: un clic filtra la tabla de abajo. --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-3" aria-labelledby="cj-maquinas">
                <div class="flex items-baseline justify-between gap-2">
                    <h3 id="cj-maquinas" class="font-semibold text-slate-800">Por máquina</h3>
                    <span class="text-caption text-slate-500">Clic para ver solo sus julios</span>
                </div>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-caption uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="pb-1 text-start font-semibold">Máquina</th>
                                <th class="pb-1 text-end font-semibold">Julios</th>
                                <th class="pb-1 text-end font-semibold">Kg neto</th>
                                <th class="pb-1 text-end font-semibold">MP $/kg</th>
                                <th class="pb-1 text-end font-semibold">Conv. $/kg</th>
                                <th class="pb-1 text-end font-semibold">Total $/kg</th>
                                <th class="pb-1 text-end font-semibold">Costo total</th>
                            </tr>
                        </thead>
                        <tbody class="tabular-nums">
                            @foreach ($porMaquina as $nombre => $m)
                                <tr wire:click="filtrarMaquina(@js($nombre))" wire:key="cj-maq-{{ $nombre }}"
                                    class="cursor-pointer border-t border-slate-100 transition-colors {{ $maquina === $nombre ? 'bg-blue-600 text-white' : 'hover:bg-blue-50' }}"
                                    aria-selected="{{ $maquina === $nombre ? 'true' : 'false' }}">
                                    <td class="py-2 ps-1 font-semibold">{{ $nombre }}</td>
                                    <td class="py-2 text-end">{{ number_format($m['julios']) }}</td>
                                    <td class="py-2 text-end">{{ $num($m['kg'], 0) }}</td>
                                    <td class="py-2 text-end">{{ $porKg($m['mpKg']) }}</td>
                                    <td class="py-2 text-end">{{ $porKg($m['conversionKg']) }}</td>
                                    <td class="py-2 text-end font-semibold">{{ $porKg($m['totalKg']) }}</td>
                                    <td class="py-2 pe-1 text-end">{{ $pesos($m['total'], 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

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
                        <tr class="text-caption uppercase tracking-wide">
                            <th class="px-3 py-2 text-start font-semibold">Folio / julio</th>
                            <th class="px-2 py-2 text-start font-semibold">Fecha</th>
                            <th class="px-2 py-2 text-start font-semibold">Máquina</th>
                            <th class="px-2 py-2 text-start font-semibold">Artículo</th>
                            <th class="px-2 py-2 text-end font-semibold">Kg neto</th>
                            <th class="px-2 py-2 text-end font-semibold">Materia prima</th>
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
                                        <span class="ms-1 text-caption text-slate-500">{{ $j['horario'] }}{{ $j['minutos'] !== null ? ' · '.number_format($j['minutos']).' min' : '' }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-500">{{ $j['fecha'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-500">{{ $j['hilos'] ? number_format((float) $j['hilos']).' hilos' : '' }}</td>
                                    <td></td>
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
                            <tr><td colspan="{{ 8 + count($conceptos) }}" class="py-10 text-center text-slate-500">Ningún julio coincide con la búsqueda.</td></tr>
                        </tbody>
                    @endforelse
                    <tfoot class="sticky bottom-0 z-10 bg-slate-800 font-semibold text-white">
                        <tr>
                            <td class="px-3 py-2" colspan="4">
                                Total {{ $buscar !== '' || $maquina !== '' ? 'filtrado' : 'del mes' }}
                                <span class="ms-1 font-normal text-slate-300">· {{ number_format($filtroTotal['julios']) }} julios</span>
                            </td>
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

{{-- Spinner al centro mientras se leen los julios de otro mes, la búsqueda o la máquina. --}}
<div wire:loading.delay.flex wire:target="anio,mes,costeo,buscar,filtrarMaquina"
     class="absolute inset-0 z-30 hidden items-center justify-center rounded-xl bg-white/60">
    <x-ui.spinner size="lg" :inline="true" />
</div>
</div>
