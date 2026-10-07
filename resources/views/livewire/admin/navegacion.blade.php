{{-- Panel /admin · Navegación por dispositivo o usuario (MON-24). --}}
<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="dispositivo" size="sm" label="Dispositivo" class="sm:w-72">
            <flux:select.option value="">Elegir dispositivo</flux:select.option>
            @foreach ($dispositivos as $d)
                <flux:select.option :value="$d->Id">{{ $d->Nombre ?: trim(($d->Modelo ?: '').' '.$d->Tipo) }} · {{ $d->UltimaIp }}</flux:select.option>
            @endforeach
        </flux:select>
        <span class="pb-2 text-caption text-(--adm-ink-3)">o</span>
        <div class="w-40">
            <flux:input wire:model.live.debounce.400ms="usuario" type="search" size="sm" label="Número de empleado" placeholder="Ej. 1234" :disabled="$dispositivo !== ''" />
        </div>
        <div class="w-44">
            <flux:input wire:model.live="fecha" type="date" size="sm" label="Día" />
        </div>
        @if ($usuarioEncontrado === false)
            <span class="pb-2 text-sm text-(--adm-err)">No existe ese número de empleado.</span>
        @endif
    </div>

    @if ($filas === null)
        <div class="rounded-xl border border-dashed border-(--adm-line-strong) px-4 py-14 text-center">
            <flux:icon.map class="mx-auto size-6 text-(--adm-ink-3)" />
            <p class="mt-2 text-sm text-(--adm-ink-2)">Elige un dispositivo o un número de empleado para ver su recorrido del día.</p>
        </div>
    @else
        @if ($resumen->isNotEmpty())
            @php $max = max(1, (int) $resumen->max('ms')); @endphp
            <section class="rounded-xl border border-(--adm-line) bg-(--adm-panel)" aria-labelledby="titulo-tiempo">
                <h3 id="titulo-tiempo" class="border-b border-(--adm-line) px-4 py-3 text-sm font-semibold">Tiempo visible por página</h3>
                <ul class="space-y-2 px-4 py-3">
                    @foreach ($resumen as $p)
                        <li class="grid grid-cols-[minmax(0,14rem)_1fr_auto] items-center gap-3 text-sm">
                            <span class="adm-mono truncate text-(--adm-ink)" title="{{ $p->Ruta }}">{{ $p->Ruta }}</span>
                            <span class="h-1.5 rounded-full bg-(--adm-hover)">
                                <span class="block h-1.5 rounded-full bg-(--adm-accent-solid)" style="width: {{ round(100 * (int) $p->ms / $max) }}%"></span>
                            </span>
                            <span class="whitespace-nowrap text-caption tabular-nums text-(--adm-ink-3)">
                                {{ \App\Services\Monitoreo\PanelConsultas::duracion(intdiv((int) $p->ms, 1000)) ?: '0 s' }} · {{ $p->n }} {{ (int) $p->n === 1 ? 'vista' : 'vistas' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <x-tabla :columnas="$this->columnas()"
                 :filas="$filas"
                 :seleccionado="$seleccionado"
                 :orden-por="$ordenPor"
                 :orden-dir="$ordenDir"
                 vacio="Sin páginas registradas ese día"
                 vacio-icono="fa-route"
                 buscar-placeholder="Buscar página o URL…" />
    @endif
</div>
