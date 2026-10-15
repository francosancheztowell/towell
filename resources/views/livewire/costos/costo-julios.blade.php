@php
    $pesos = fn (?float $v, int $d = 2): string => $v === null ? '—' : ($v < 0 ? '-$' : '$').number_format(abs($v), $d);
    $num = fn (?float $v, int $d = 2): string => $v === null ? '—' : number_format($v, $d);
    $porKg = fn (?float $v, string $unidad = ''): string => $v === null ? '—' : '$'.number_format($v, 2).$unidad;
    // Clases completas por concepto: Tailwind solo ve literales en Blade.
    $colores = ['MOD' => 'bg-blue-600', 'MOI' => 'bg-indigo-400', 'GtsV' => 'bg-emerald-500', 'GtsF' => 'bg-amber-500',
        'Pf' => 'bg-rose-500', 'PV' => 'bg-cyan-500', 'Maquila' => 'bg-slate-500'];
    // Encabezado de la tabla en dos renglones, en palabras del contador (los códigos solo los entiende quien ya los sabe).
    $encabezado = ['MOD' => ['Mano obra', 'directa'], 'MOI' => ['Mano obra', 'indirecta'], 'GtsV' => ['Gastos', 'variables'],
        'GtsF' => ['Gastos', 'fijos'], 'Pf' => ['Prorrateo', 'fijo'], 'PV' => ['Prorrateo', 'variable'], 'Maquila' => ['Maquila', '']];
    $sabDe = array_flip(\App\Livewire\Costos\CostoJulios::SABANA);
    $pct = fn (float $parte, float $total): string => $total > 0 ? number_format($parte / $total * 100, 1).'%' : '—';
    // Lo que necesita atención va primero.
    $puntos = collect($veredicto ?? [])->sortBy(fn ($p) => ['fuera' => 0, 'sin' => 1, 'ok' => 2][$p['estado']])->values();
    $filtrando = $buscar !== '' || $maquina !== '';
    // Sin kg capturados (mes en curso) los % y el $/kg no son dato: el veredicto ya lo explica.
    $conKg = ($mesTotal['kg'] ?? 0) > 0;
    $todoParo = fn (array $f): bool => $f['minutos'] > 0 && $f['paro'] >= $f['minutos'];
@endphp
{{-- Raíz sin scroll: el spinner queda al centro de lo visible, no del contenido desplazado. --}}
<div class="relative flex min-h-0 flex-1 flex-col">
    <x-costos.costeo destino="#costos-navbar-costeo" />
<div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto pb-2">
    {{-- Periodo. Solo los meses con costo; cada uno dice cuántos julios tiene. --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="w-24 shrink-0">
            <flux:select wire:model.live="anio" aria-label="Año" class="h-11!">
                @foreach ($anios as $a)
                    <flux:select.option value="{{ $a }}">{{ $a }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-56 shrink-0">
            <flux:select wire:model.live="mes" aria-label="Mes" class="h-11!">
                @forelse ($meses as $n => $info)
                    <flux:select.option value="{{ $n }}">{{ \App\Models\Costos\CosCuota::MESES[$n] }} · {{ number_format($info['julios']) }} julios</flux:select.option>
                @empty
                    <flux:select.option value="{{ $mes }}">Sin meses con costo</flux:select.option>
                @endforelse
            </flux:select>
        </div>

        <flux:button icon="arrow-down-tray" class="ms-auto min-h-touch shrink-0" wire:click="abrirExportar" :disabled="$meses === []">Exportar</flux:button>
    </div>

    @if ($meses === [] || $mesTotal['julios'] === 0)
        <div class="rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center text-slate-500">
            <i class="fa-solid fa-inbox mb-2 text-3xl text-slate-300" aria-hidden="true"></i>
            <p>{{ $meses === [] ? "No hay meses con costo en {$anio}: corre Calcular Urdido." : 'No hay julios urdidos en '.mb_strtolower($nombreMes)." de {$anio}." }}</p>
        </div>
    @else
        {{-- Veredicto del mes (CostoJulios::veredicto): ¿cuadra el hilo con el kardex y la conversión con la sábana? --}}
        <section class="shrink-0 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white" aria-labelledby="cj-veredicto">
            <h2 id="cj-veredicto" class="sr-only">¿Cuadra {{ mb_strtolower($nombreMes) }}?</h2>
            @foreach ($puntos as $p)
                <div class="flex items-start gap-3 px-4 py-2.5 {{ $p['estado'] === 'fuera' ? 'bg-amber-50' : '' }}">
                    @switch($p['estado'])
                        @case('fuera') <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-600" aria-hidden="true"></i> @break
                        @case('ok') <i class="fa-solid fa-circle-check mt-0.5 text-emerald-600" aria-hidden="true"></i> @break
                        @default <i class="fa-solid fa-circle-info mt-0.5 text-slate-500" aria-hidden="true"></i>
                    @endswitch
                    <p class="text-sm text-slate-600">
                        <strong class="font-semibold {{ $p['estado'] === 'fuera' ? 'text-amber-900' : 'text-slate-900' }}">{{ $p['titulo'] }}.</strong>
                        {{ $p['detalle'] }}
                    </p>
                </div>
            @endforeach
        </section>

        {{-- Indicadores del mes en una franja. MP primero después del costo: es casi todo el costo. --}}
        <section class="grid shrink-0 grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 tabular-nums md:grid-cols-5" aria-label="Resumen del mes">
            <div class="col-span-2 bg-white px-4 py-3 md:col-span-1">
                <p class="text-sm text-slate-500">Costo por kg</p>
                <p class="text-3xl font-bold {{ $conKg ? 'text-blue-700' : 'text-slate-500' }}">{{ $porKg($mesTotal['totalKg']) }}</p>
            </div>
            <div class="bg-white px-4 py-3">
                <p class="text-sm text-slate-500">MP <span class="text-caption">· {{ $kardex === null ? 'de fórmula' : 'del kardex' }}</span></p>
                <p class="text-xl font-semibold text-slate-900">{{ $porKg($mesTotal['mpKg'], '/kg') }}</p>
                @if ($conKg) <p class="text-caption text-slate-500">{{ $pct($mesTotal['mp'], $mesTotal['total']) }} del costo</p> @endif
            </div>
            <div class="bg-white px-4 py-3">
                <p class="text-sm text-slate-500">Conversión</p>
                <p class="text-xl font-semibold text-slate-900">{{ $porKg($mesTotal['conversionKg'], '/kg') }}</p>
                @if ($conKg) <p class="text-caption text-slate-500">{{ $pct($mesTotal['conversion'], $mesTotal['total']) }} del costo</p> @endif
            </div>
            <div class="bg-white px-4 py-3">
                <p class="text-sm text-slate-500">Cantidad</p>
                <p class="text-xl font-semibold text-slate-900">{{ $num($mesTotal['kg'], 0) }} kg</p>
                <p class="text-caption text-slate-500">{{ number_format($mesTotal['julios']) }} julios</p>
            </div>
            <div class="bg-white px-4 py-3">
                <p class="text-sm text-slate-500">Costo total</p>
                <p class="text-xl font-semibold text-slate-900">{{ $pesos($mesTotal['total'], 0) }}</p>
            </div>
        </section>

        <div class="grid gap-3 md:grid-cols-2">
            {{-- Conversión por concepto contra la sábana. --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="cj-conceptos">
                <h3 id="cj-conceptos" class="flex items-baseline gap-2 text-sm text-slate-500">Conversión
                    <span class="ms-auto text-base font-semibold tabular-nums text-slate-900">{{ $pesos($mesTotal['conversion'], 0) }}</span></h3>
                @if ($mesTotal['conversion'] > 0)
                    {{-- Huecos blancos entre tramos: separan colores vecinos aunque se parezcan. --}}
                    <div class="mt-3 flex h-2.5 gap-0.5 overflow-hidden rounded-full" role="img" aria-label="Reparto del costo de conversión por concepto">
                        @foreach ($conceptos as $col => $nombre)
                            <div class="{{ $colores[$col] }}" style="width: {{ max(0, $mesTotal[$col] / $mesTotal['conversion'] * 100) }}%"></div>
                        @endforeach
                    </div>
                @endif
                {{-- Julios = lo cobrado a los julios; Sábana = gasto de AX del mes. Fuera si difieren por $5 o más (menos es redondeo). --}}
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
                            @php
                                $sab = isset($sabDe[$col]) ? ($cuadre[$nombre]['sab'] ?? null) : null;
                                $difSab = $sab === null ? null : $mesTotal[$col] - $sab;
                            @endphp
                            <tr>
                                <td class="py-1"><span class="me-1.5 inline-block size-2.5 rounded-sm {{ $colores[$col] }}" aria-hidden="true"></span>{{ $nombre }}</td>
                                <td class="py-1 text-end">{{ $pesos($mesTotal[$col], 0) }}</td>
                                <td class="py-1 text-end {{ $difSab !== null && abs($difSab) >= 5 ? 'font-semibold text-amber-800' : 'text-slate-500' }}">
                                    @if ($difSab !== null && abs($difSab) >= 5)
                                        <i class="fa-solid fa-triangle-exclamation me-0.5 text-caption" aria-hidden="true"></i>
                                        <span class="sr-only">No cuadra por {{ $pesos($difSab, 0) }}:</span>
                                    @endif
                                    {{ $pesos($sab, 0) }}
                                </td>
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
                                {{-- Solo los conceptos de la sábana que este costeo toma (en directo, sin los fijos). --}}
                                <td class="pt-1.5 text-end">{{ $pesos(array_sum(array_column($cuadre, 'sab')), 0) }}</td>
                                <td class="pt-1.5 text-end">100%</td>
                                <td class="pt-1.5 text-end">{{ $porKg($mesTotal['conversionKg']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </section>

            {{-- Por máquina: un toque filtra la tabla de abajo (el mismo filtro que su barra). --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="cj-maquinas">
                <h3 id="cj-maquinas" class="text-sm text-slate-500">Por máquina <span class="text-caption">· toca para filtrar la tabla</span></h3>
                <ul class="mt-2 text-sm tabular-nums">
                    @foreach ($porMaquina as $nombre => $m)
                        <li wire:key="cj-maq-{{ $nombre }}">
                            <button type="button" wire:click="filtrarMaquina(@js($nombre))" aria-pressed="{{ $maquina === $nombre ? 'true' : 'false' }}"
                                    class="flex min-h-touch w-full items-center gap-2 rounded-lg px-2 text-start transition-colors {{ $maquina === $nombre ? 'bg-blue-600 text-white' : 'hover:bg-blue-50' }}">
                                <span class="flex-1 font-semibold">{{ $nombre }}</span>
                                <span class="{{ $maquina === $nombre ? 'text-blue-100' : 'text-slate-500' }}">{{ number_format($m['julios']) }} julios</span>
                                <span class="w-14 text-end {{ $maquina === $nombre ? 'text-blue-100' : 'text-slate-500' }}">{{ $pct($m['total'], $mesTotal['total']) }}</span>
                                <span class="w-20 text-end font-semibold">{{ $porKg($m['totalKg']) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-1 px-2 text-caption text-slate-500">% = parte del costo total del mes · $/kg de la máquina</p>
            </section>
        </div>

        {{-- Detalle: folios (orden de urdido) que se despliegan en sus julios. --}}
        <section class="flex flex-col rounded-xl border border-slate-200 bg-white" aria-labelledby="cj-detalle"
                 x-data="{ abiertos: {}, todos: false }">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-3 py-2">
                <h3 id="cj-detalle" class="me-2 font-semibold text-slate-800">Julios del mes</h3>
                <label class="relative min-w-0 flex-1 sm:max-w-xs">
                    <span class="sr-only">Buscar folio, julio o artículo</span>
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500" aria-hidden="true"></i>
                    <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Folio, julio, cuenta, fibra…"
                           class="min-h-touch w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 text-sm placeholder:text-slate-500">
                </label>
                {{-- Máquina junto a la tabla que filtra (la misma propiedad que las filas de "Por máquina"). --}}
                <flux:radio.group wire:model.live="maquina" variant="segmented" aria-label="Filtrar por máquina">
                    <flux:radio value="" label="Todas" class="min-h-touch data-checked:bg-blue-600! data-checked:text-white!" />
                    @foreach ($porMaquina as $nombre => $m)
                        <flux:radio value="{{ $nombre }}" label="{{ $nombre }}" class="min-h-touch data-checked:bg-blue-600! data-checked:text-white!" />
                    @endforeach
                </flux:radio.group>
                <flux:button size="sm" variant="ghost" class="ms-auto min-h-touch" x-on:click="todos = !todos; abiertos = {}">
                    <span x-text="todos ? 'Contraer todo' : 'Desplegar todo'">Desplegar todo</span>
                </flux:button>
                @if ($filtrando)
                    <p class="basis-full text-caption text-slate-500">Los indicadores de arriba son del mes completo; esta tabla y su total, del filtro.</p>
                @endif
            </div>

            {{-- Alto de la ventana: al llegar a la tabla, ella es lo único que se desplaza. La 1.ª columna queda fija. --}}
            <div class="max-h-[calc(100dvh-9rem)] min-h-72 overflow-auto">
                <table class="w-full text-sm tabular-nums">
                    <thead class="sticky top-0 z-20 bg-blue-600 text-white">
                        <tr class="text-caption leading-tight">
                            <th class="sticky left-0 z-10 bg-blue-600 px-3 py-2 text-start font-semibold">Folio / julio</th>
                            <th class="px-2 py-2 text-start font-semibold">Fecha</th>
                            <th class="px-2 py-2 text-start font-semibold">Máquina</th>
                            <th class="px-2 py-2 text-start font-semibold">Artículo</th>
                            <th class="px-2 py-2 text-end font-semibold">Producción<br>min</th>
                            <th class="px-2 py-2 text-end font-semibold" title="Paro de la máquina durante el julio, sin tiempo muerto">Paro<br>min</th>
                            <th class="px-2 py-2 text-end font-semibold">Kg</th>
                            <th class="px-2 py-2 text-end font-semibold">MP<br>{{ $kardex === null ? 'fórmula' : 'kardex' }}</th>
                            @foreach ($conceptos as $col => $nombre)
                                <th class="px-2 py-2 text-end font-semibold">{{ $encabezado[$col][0] }}<br>{{ $encabezado[$col][1] }}</th>
                            @endforeach
                            <th class="sticky right-24 z-[1] w-28 min-w-28 border-l border-slate-200 bg-blue-600 px-2 py-2 text-end font-semibold">Costo<br>total</th>
                            <th class="sticky right-0 z-[1] w-24 min-w-24 bg-blue-700 px-3 py-2 text-end font-semibold">$/kg</th>
                        </tr>
                    </thead>
                    @forelse ($folios as $folio => $f)
                        @php $k = \Illuminate\Support\Js::from((string) $folio); @endphp
                        <tbody wire:key="cj-folio-{{ $folio }}" class="border-b border-slate-200">
                            {{-- Renglón del folio: subtotal de sus julios. Todo el renglón despliega; el botón da teclado y lector. --}}
                            <tr class="cursor-pointer bg-slate-50 font-semibold hover:bg-blue-50"
                                x-on:click="abiertos[{{ $k }}] = !(todos || abiertos[{{ $k }}])">
                                <td class="sticky left-0 z-[1] whitespace-nowrap bg-inherit px-3">
                                    <button type="button" class="flex min-h-touch items-center gap-1.5 text-start"
                                            x-bind:aria-expanded="(todos || !!abiertos[{{ $k }}]).toString()">
                                        <i class="fa-solid fa-chevron-right text-xs text-slate-500 transition-transform"
                                           x-bind:class="(todos || abiertos[{{ $k }}]) && 'rotate-90'" aria-hidden="true"></i>
                                        {{ $folio }}
                                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-caption font-medium text-slate-700">{{ $f['julios']->count() }} julios</span>
                                        @if ($todoParo($f))
                                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-caption font-medium text-rose-800" title="El paro cubre todo el tiempo de producción">todo paro</span>
                                        @endif
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-2 font-normal text-slate-600">{{ $f['fecha'] }}</td>
                                <td class="whitespace-nowrap px-2">{{ $f['maquina'] }}</td>
                                <td class="whitespace-nowrap px-2 font-normal">
                                    {{ $f['articulo'] }}
                                    @if ($f['rizoPie'])
                                        @php $rp = match ($f['rizoPie']) { 'Rizo' => 'bg-blue-100 text-blue-800', 'Pie' => 'bg-amber-100 text-amber-800', default => 'bg-slate-100 text-slate-700' }; @endphp
                                        <span class="ms-1 rounded px-1.5 py-0.5 text-caption {{ $rp }}">{{ $f['rizoPie'] }}</span>
                                    @endif
                                </td>
                                <td class="px-2 text-end">{{ $num($f['minutos'], 0) }}</td>
                                <td class="px-2 text-end {{ $f['paro'] > 0 ? 'text-rose-700' : 'font-normal text-slate-500' }}">{{ $num($f['paro'], 0) }}</td>
                                <td class="px-2 text-end">{{ $num($f['kg'], 0) }}</td>
                                <td class="px-2 text-end">{{ $pesos($f['mp'], 0) }}</td>
                                @foreach ($conceptos as $col => $nombre)
                                    <td class="px-2 text-end">{{ $pesos($f[$col], 0) }}</td>
                                @endforeach
                                <td class="sticky right-24 z-[1] w-28 min-w-28 border-l border-slate-200 bg-inherit px-2 text-end">{{ $pesos($f['total'], 0) }}</td>
                                <td class="sticky right-0 z-[1] w-24 min-w-24 bg-blue-50 px-3 text-end text-blue-800">{{ $porKg($f['totalKg']) }}</td>
                            </tr>
                            {{-- Composición de la LMAT (dbo.Urdbom) al desplegar: cada material con su % exacto y su costo unitario.
                                 La suma va al final; en ámbar si no da 100 %. El bloque se queda a la vista al desplazar la tabla. --}}
                            @php
                                $materiales = $lmat[(string) $folio] ?? collect();
                                $sumaPct = (float) $materiales->sum(fn ($m) => (float) $m->Porcentaje);
                            @endphp
                            <tr x-show="todos || abiertos[{{ $k }}]" x-cloak class="bg-white">
                                <td colspan="{{ 10 + count($conceptos) }}" class="py-2 ps-10 pe-3">
                                    <div class="sticky left-10 flex w-fit max-w-[calc(100vw-6rem)] flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                                        @if ($materiales->isEmpty())
                                            <span class="text-amber-800"><i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>Sin LMAT en Lista de materiales de urdido para este folio.</span>
                                        @else
                                            <span class="text-slate-500">LMAT <strong class="font-semibold text-slate-800">{{ trim((string) $materiales->first()->Lmat) }}</strong></span>
                                            @foreach ($materiales as $m)
                                                <span class="inline-flex items-baseline gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 tabular-nums">
                                                    <strong class="font-semibold text-slate-900">{{ number_format((float) $m->Porcentaje, 2) }} %</strong>
                                                    <span class="text-slate-700">{{ trim((string) $m->Calibre) }} {{ trim((string) $m->Config) }}</span>
                                                    <span class="text-caption text-slate-500">color {{ trim((string) $m->Color) }}</span>
                                                    @if ($m->cump !== null)
                                                        <span class="text-caption text-slate-500">· {{ $porKg((float) $m->cump, '/kg') }}</span>
                                                    @endif
                                                </span>
                                            @endforeach
                                            <span class="text-caption {{ abs($sumaPct - 100) < 0.01 ? 'text-slate-500' : 'font-semibold text-amber-800' }}">
                                                Σ {{ number_format($sumaPct, 2) }} %@if (abs($sumaPct - 100) >= 0.01) · no suma 100 %@endif
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @foreach ($f['julios'] as $j)
                                @php $conv = array_sum(array_map(fn ($c) => (float) $j[$c], array_keys($conceptos))); @endphp
                                <tr x-show="todos || abiertos[{{ $k }}]" x-cloak wire:key="cj-julio-{{ $j['id'] }}" class="bg-white text-slate-700 hover:bg-slate-50">
                                    <td class="sticky left-0 z-[1] whitespace-nowrap bg-inherit py-1.5 pe-3 ps-10">
                                        Julio <strong>{{ $j['julio'] }}</strong>
                                        <span class="ms-1 text-caption text-slate-500">{{ $j['horario'] }}</span>
                                        @if ($todoParo($j))
                                            <span class="ms-1 rounded-full bg-rose-100 px-2 py-0.5 text-caption font-medium text-rose-800">todo paro</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-500">{{ $j['fecha'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-500">{{ $j['hilos'] ? number_format((float) $j['hilos']).' hilos' : '' }}</td>
                                    <td></td>
                                    <td class="px-2 py-1.5 text-end">{{ $num($j['minutos'], 0) }}</td>
                                    <td class="px-2 py-1.5 text-end {{ $j['paro'] > 0 ? 'text-rose-700' : 'text-slate-500' }}">{{ $num($j['paro'], 0) }}</td>
                                    <td class="px-2 py-1.5 text-end">{{ $num($j['kg'], 0) }}</td>
                                    <td class="px-2 py-1.5 text-end">{{ $pesos($j['mp'], 0) }}</td>
                                    @foreach ($conceptos as $col => $nombre)
                                        <td class="px-2 py-1.5 text-end {{ $j[$col] === null ? 'text-amber-800' : '' }}"
                                            @if ($j[$col] === null) title="Sin costo: falta correr Calcular Urdido" @endif>{{ $pesos($j[$col], 0) }}</td>
                                    @endforeach
                                    <td class="sticky right-24 z-[1] w-28 min-w-28 border-l border-slate-200 bg-inherit px-2 py-1.5 text-end">{{ $pesos((float) $j['mp'] + $conv, 0) }}</td>
                                    <td class="sticky right-0 z-[1] w-24 min-w-24 bg-inherit px-3 py-1.5 text-end">{{ $porKg($j['kg'] ? ((float) $j['mp'] + $conv) / $j['kg'] : null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @empty
                        <tbody>
                            <tr>
                                <td colspan="{{ 10 + count($conceptos) }}" class="py-10 text-center text-slate-500">
                                    <p>Ningún julio{{ $maquina !== '' ? " de {$maquina}" : '' }}{{ $buscar !== '' ? ' coincide con “'.$buscar.'”' : ' en el mes' }}.</p>
                                    <flux:button size="sm" class="mt-2 min-h-touch" wire:click="limpiarFiltros">Limpiar filtros</flux:button>
                                </td>
                            </tr>
                        </tbody>
                    @endforelse
                    <tfoot class="sticky bottom-0 z-20 bg-slate-800 font-semibold text-white">
                        <tr>
                            <td class="sticky left-0 z-10 bg-slate-800 px-3 py-2" colspan="4">
                                Total {{ $filtrando ? 'filtrado' : 'del mes' }}
                                <span class="ms-1 font-normal text-slate-300">· {{ number_format($filtroTotal['julios']) }} julios</span>
                            </td>
                            <td class="px-2 py-2 text-end">{{ $num($filtroTotal['minutos'], 0) }}</td>
                            <td class="px-2 py-2 text-end">{{ $num($filtroTotal['paro'], 0) }}</td>
                            <td class="px-2 py-2 text-end">{{ $num($filtroTotal['kg'], 0) }}</td>
                            <td class="px-2 py-2 text-end">{{ $pesos($filtroTotal['mp'], 0) }}</td>
                            @foreach ($conceptos as $col => $nombre)
                                <td class="px-2 py-2 text-end">{{ $pesos($filtroTotal[$col], 0) }}</td>
                            @endforeach
                            <td class="sticky right-24 z-[1] w-28 min-w-28 border-l border-slate-600 bg-slate-800 px-2 py-2 text-end">{{ $pesos($filtroTotal['total'], 0) }}</td>
                            <td class="sticky right-0 z-[1] w-24 min-w-24 bg-blue-700 px-3 py-2 text-end">{{ $porKg($filtroTotal['totalKg']) }}</td>
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
            <flux:text>Un renglón por julio de los meses elegidos de {{ $anio }}, con todos los decimales. Incluye todas las máquinas y no aplica la búsqueda.</flux:text>
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
<div wire:loading.delay.flex wire:target="anio,mes,maquina,costeo,buscar,filtrarMaquina,limpiarFiltros"
     class="absolute inset-0 z-30 hidden items-center justify-center rounded-xl bg-white/60">
    <x-ui.spinner size="lg" :inline="true" />
</div>
</div>
