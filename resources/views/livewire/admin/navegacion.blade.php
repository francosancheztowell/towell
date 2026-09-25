{{-- Panel /admin · Navegación por dispositivo o usuario (MON-24). --}}
<div class="space-y-3">
    <div class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <label class="flex min-w-0 flex-1 flex-col text-xs font-semibold text-slate-500 sm:flex-none">
            Dispositivo
            <select wire:model.live="dispositivo" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:max-w-[16rem] sm:flex-none">
                <option value="">— Elegir —</option>
                @foreach ($dispositivos as $d)
                    <option value="{{ $d->Id }}">{{ $d->Nombre ?: trim(($d->Modelo ?: '').' '.$d->Tipo) }} · {{ $d->UltimaIp }}</option>
                @endforeach
            </select>
        </label>
        <span class="pb-2 text-xs font-semibold text-slate-400">o</span>
        <label class="flex flex-col text-xs font-semibold text-slate-500">
            Número de empleado
            <input type="search" wire:model.live.debounce.400ms="usuario" placeholder="Ej. 1234" @disabled($dispositivo !== '')
                   class="w-36 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:bg-slate-100">
        </label>
        <label class="flex flex-col text-xs font-semibold text-slate-500">
            Día
            <input type="date" wire:model.live="fecha"
                   class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
        </label>
        @if ($usuarioEncontrado === false)
            <span class="pb-2 text-sm text-red-600">No existe ese número de empleado.</span>
        @endif
    </div>

    @if ($filas === null)
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-14 text-center">
            <i class="fa-solid fa-route text-3xl text-slate-300"></i>
            <p class="mt-3 font-semibold text-slate-600">Elige un dispositivo o un número de empleado para ver su recorrido del día.</p>
        </div>
    @else
        @if ($resumen->isNotEmpty())
            @php $max = max(1, (int) $resumen->max('ms')); @endphp
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-2 text-sm font-bold text-slate-700">Tiempo visible por página</h3>
                <ul class="space-y-1.5">
                    @foreach ($resumen as $p)
                        <li class="grid grid-cols-[minmax(0,14rem)_1fr_auto] items-center gap-2 text-sm">
                            <span class="truncate text-slate-700" title="{{ $p->Ruta }}">{{ $p->Ruta }}</span>
                            <span class="h-2.5 rounded-full bg-slate-100">
                                <span class="block h-2.5 rounded-full bg-blue-500" style="width: {{ round(100 * (int) $p->ms / $max) }}%"></span>
                            </span>
                            <span class="whitespace-nowrap text-xs text-slate-500">
                                {{ \App\Services\Monitoreo\PanelConsultas::duracion(intdiv((int) $p->ms, 1000)) ?: '0 s' }} · {{ $p->n }} {{ (int) $p->n === 1 ? 'vista' : 'vistas' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
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
