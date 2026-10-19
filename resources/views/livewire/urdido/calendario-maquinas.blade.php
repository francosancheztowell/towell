@php
    // Un color por turno (cuadrilla); se repite en la leyenda y en las casillas.
    $colores = [
        1 => 'border-sky-300 bg-sky-100 text-sky-900',
        2 => 'border-emerald-300 bg-emerald-100 text-emerald-900',
        3 => 'border-amber-300 bg-amber-100 text-amber-900',
        4 => 'border-violet-300 bg-violet-100 text-violet-900',
    ];
    $diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
@endphp

<div class="flex min-h-0 flex-1 flex-col gap-3">
    @if ($puede['crear'])
        @teleport('#tabla-navbar-acciones')
            <div class="flex items-center gap-2">
                <flux:button variant="primary" color="blue" icon="plus" class="min-h-touch" wire:click="abrirAlta">Nuevo</flux:button>
                <flux:button icon="calendar-days" class="min-h-touch" wire:click="abrirGenerar">Generar mes</flux:button>
            </div>
        @endteleport
    @endif

    {{-- Máquina, mes y resumen --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div role="tablist" aria-label="Máquina" class="inline-flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1">
            @foreach ($this->maquinas as $id => $nombre)
                @php $activa = (string) $id === $maquinaFiltro; @endphp
                <button type="button" role="tab" aria-selected="{{ $activa ? 'true' : 'false' }}"
                        wire:click="verMaquina('{{ $id }}')"
                        class="min-h-touch rounded-md px-4 text-sm font-semibold transition-colors {{ $activa ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:bg-white/60 hover:text-slate-900' }}">
                    {{ $nombre }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-1">
            <flux:button icon="chevron-left" variant="ghost" class="min-h-touch min-w-touch" wire:click="moverMes(-1)" aria-label="Mes anterior" />
            <h2 class="min-w-40 text-center text-lg font-semibold text-slate-800">{{ $tituloMes }}</h2>
            <flux:button icon="chevron-right" variant="ghost" class="min-h-touch min-w-touch" wire:click="moverMes(1)" aria-label="Mes siguiente" />
            <flux:input type="month" wire:model.live="mes" size="sm" aria-label="Ir al mes" class="ms-2 w-40" />
        </div>

        <p class="text-sm text-slate-600">
            <span class="font-semibold text-slate-800">{{ number_format($resumen['turnos']) }}</span> turnos ·
            <span class="font-semibold text-slate-800">{{ number_format($resumen['minutos']) }}</span> min programados
        </p>
    </div>

    <ul class="flex flex-wrap items-center gap-2 text-caption text-slate-600" aria-label="Turnos">
        @foreach ($colores as $turno => $clases)
            <li class="rounded-md border px-2 py-0.5 font-semibold {{ $clases }}">T{{ $turno }}</li>
        @endforeach
        <li class="rounded-md border border-dashed border-slate-300 px-2 py-0.5 text-slate-500">+ sin programar</li>
        <li class="text-slate-500">Los minutos salen solo si no son 480.</li>
    </ul>

    {{-- Mes: semanas de lunes a domingo; cada día con sus 3 horarios. --}}
    <div class="min-h-0 flex-1 overflow-auto rounded-xl border border-slate-200 bg-white shadow-sm"
         wire:loading.class="opacity-60" wire:target="verMaquina,moverMes,mes">
        <div class="grid min-w-[56rem] grid-cols-7">
            @foreach ($diasSemana as $nombreDia)
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $nombreDia }}</div>
            @endforeach

            @foreach ($semanas as $semana)
                @foreach ($semana as $dia)
                    <div wire:key="dia-{{ $dia['fecha']->format('Y-m-d') }}"
                         class="min-h-36 border-b border-r border-slate-100 p-1.5 {{ $dia['enMes'] ? '' : 'bg-slate-50/70' }}">
                        <div class="mb-1 flex items-center px-0.5">
                            <span @class([
                                'inline-flex size-6 items-center justify-center rounded-full text-sm font-semibold',
                                'bg-blue-600 text-white' => $dia['hoy'],
                                'text-slate-800' => ! $dia['hoy'] && $dia['enMes'],
                                'text-slate-400' => ! $dia['enMes'],
                            ])>{{ $dia['fecha']->day }}</span>
                        </div>

                        @if ($dia['enMes'])
                            <div class="space-y-1">
                                @foreach ($dia['casillas'] as $casilla)
                                    @php $t = $casilla['turno']; @endphp
                                    @if ($t)
                                        <button type="button" wire:click="abrirEdicion('{{ $t->Id }}')" @disabled(! $puede['modificar'])
                                                aria-label="Turno {{ $t->Turno }}, {{ $casilla['hora'] }}, {{ $t->Minutos }} minutos{{ $puede['modificar'] ? '. Editar' : '' }}"
                                                class="flex min-h-9 w-full items-center justify-between rounded-md border px-2 text-xs font-semibold transition hover:brightness-95 {{ $colores[$t->Turno] ?? '' }}">
                                            <span class="tabular-nums">{{ $casilla['hora'] }}</span>
                                            <span>T{{ $t->Turno }}@if ($t->Minutos !== 480)<span class="ms-1 font-normal">· {{ $t->Minutos }} min</span>@endif</span>
                                        </button>
                                    @elseif ($puede['crear'])
                                        <button type="button" wire:click="abrirAltaEn('{{ $casilla['inicio'] }}')"
                                                aria-label="Agregar turno {{ $casilla['sugerido'] }} a las {{ $casilla['hora'] }}"
                                                class="flex min-h-9 w-full items-center justify-between rounded-md border border-dashed border-slate-300 px-2 text-xs text-slate-400 transition hover:border-blue-400 hover:text-blue-600">
                                            <span class="tabular-nums">{{ $casilla['hora'] }}</span>
                                            <span>+ T{{ $casilla['sugerido'] }}</span>
                                        </button>
                                    @else
                                        <div class="flex min-h-9 items-center justify-between rounded-md border border-dashed border-slate-200 px-2 text-xs text-slate-300">
                                            <span class="tabular-nums">{{ $casilla['hora'] }}</span><span>—</span>
                                        </div>
                                    @endif
                                @endforeach

                                {{-- Turnos que empiezan a otra hora (capturados a mano). --}}
                                @foreach ($dia['otros'] as $t)
                                    <button type="button" wire:click="abrirEdicion('{{ $t->Id }}')" @disabled(! $puede['modificar'])
                                            aria-label="Turno {{ $t->Turno }}, {{ $t->FechaInicio->format('H:i') }}, {{ $t->Minutos }} minutos"
                                            class="flex min-h-9 w-full items-center justify-between rounded-md border px-2 text-xs font-semibold ring-1 ring-inset ring-slate-400/40 {{ $colores[$t->Turno] ?? '' }}">
                                        <span class="tabular-nums">{{ $t->FechaInicio->format('H:i') }}</span>
                                        <span>T{{ $t->Turno }} · {{ $t->Minutos }} min</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- Alta / edición de un turno. <dialog> nativo como en los demás catálogos. --}}
    @if ($editando !== null)
        <dialog wire:key="turno-{{ $editando }}" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="turno-titulo" class="ui-dialogo ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="turno-titulo" size="xl">{{ $editando === '' ? 'Nuevo turno' : 'Editar turno' }}</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:select wire:model="form.Maquina" label="Máquina" required>
                        <flux:select.option value="">Selecciona…</flux:select.option>
                        @foreach ($this->maquinas as $id => $nombre)
                            <flux:select.option :value="$id">{{ $nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="form.Turno" label="Turno" required>
                        @foreach (\App\Livewire\Urdido\CalendarioMaquinas::TURNOS as $turno)
                            <flux:select.option :value="$turno">Turno {{ $turno }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    {{-- .live: a las 06:30 / 14:30 / 22:30 el turno se llena con la rotación. --}}
                    <flux:input type="datetime-local" wire:model.live="form.FechaInicio" label="Inicio" required />
                    <flux:input type="number" wire:model="form.Minutos" label="Minutos" min="1" max="1440" required />
                </div>

                @if ($editando !== '' && $puede['eliminar'])
                    <div>
                        <flux:button variant="danger" icon="trash" size="sm" class="min-h-touch"
                                     x-on:click="notify.confirm({ title: '¿Eliminar el turno?', text: 'Se borra del calendario. No se puede deshacer.', confirmText: 'Sí, eliminar', confirmColor: '#dc2626' }).then(ok => ok && $wire.eliminar())">
                            Eliminar turno
                        </flux:button>
                    </div>
                @endif

                <x-dialogo-botones />
            </form>
        </dialog>
    @endif

    {{-- Generar mes: por día marcado, un turno por horario activo; el número de turno sale de la rotación.
         Lo que se encime con lo existente se omite. --}}
    @if ($generar !== null)
        <dialog wire:key="generar-mes" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrarGenerar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="generar-titulo" class="ui-dialogo ui-dialogo--formulario">
            <form wire:submit="generarMes" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="generar-titulo" size="xl">Generar mes</flux:heading>
                <flux:text>Los turnos que se encimen con los que ya están en el calendario no se agregan.</flux:text>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:select wire:model="generar.Maquina" label="Máquina" required>
                        <flux:select.option value="">Selecciona…</flux:select.option>
                        @foreach ($this->maquinas as $id => $nombre)
                            <flux:select.option :value="$id">{{ $nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input type="month" wire:model="generar.mes" label="Mes" required />
                </div>

                <flux:checkbox.group wire:model="generar.dias" label="Días" variant="buttons" class="flex flex-wrap gap-1">
                    @foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $dia => $nombre)
                        <flux:checkbox :value="(string) $dia" :label="$nombre" />
                    @endforeach
                </flux:checkbox.group>

                <fieldset class="space-y-2">
                    <legend class="mb-1 text-sm font-medium text-zinc-800">Horarios</legend>
                    @foreach ($generar['ventanas'] as $ventana => $v)
                        <div wire:key="generar-ventana-{{ $ventana }}" class="grid grid-cols-3 items-end gap-3">
                            <flux:checkbox wire:model="generar.ventanas.{{ $ventana }}.activo" :label="\App\Livewire\Urdido\CalendarioMaquinas::NOMBRES_VENTANA[$ventana]" class="min-h-touch" />
                            <flux:input type="time" wire:model="generar.ventanas.{{ $ventana }}.hora" label="Inicio" />
                            <flux:input type="number" wire:model="generar.ventanas.{{ $ventana }}.minutos" label="Minutos" min="1" max="1440" />
                        </div>
                    @endforeach
                    <flux:error name="generar.ventanas" />
                </fieldset>

                {{-- Rotación de solo lectura: qué turno cubre cada horario según el día (semana de martes a lunes). --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-center text-xs">
                        <caption class="mb-1 text-start text-sm font-medium text-zinc-800">Rotación de turnos</caption>
                        <thead>
                            <tr class="bg-slate-100">
                                <th class="p-1 text-start">Horario</th>
                                @foreach ([2 => 'Ma', 3 => 'Mi', 4 => 'Ju', 5 => 'Vi', 6 => 'Sá', 7 => 'Do', 1 => 'Lu'] as $nombre)
                                    <th class="p-1">{{ $nombre }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (\App\Models\UrdEngomado\UrdEngCalMaquina::VENTANAS as $ventana => $hora)
                                <tr class="border-t border-slate-200">
                                    <th class="p-1 text-start font-normal">{{ $hora }}</th>
                                    @foreach ([2, 3, 4, 5, 6, 7, 1] as $dia)
                                        @php $turno = \App\Models\UrdEngomado\UrdEngCalMaquina::ROTACION_URDIDO[$dia][$ventana]; @endphp
                                        <td class="p-1 {{ $turno === 4 ? 'font-semibold text-blue-700' : '' }}">T{{ $turno }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-dialogo-botones accion="generarMes" texto="Generar" />
            </form>
        </dialog>
    @endif
</div>
