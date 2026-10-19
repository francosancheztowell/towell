<div class="flex min-h-0 flex-1 flex-col gap-2">
    {{-- Filtros propios y no el slot de x-tabla: ese trae un buscador que aquí no busca nada. --}}
    <div class="flex flex-wrap items-end gap-2">
        <flux:select wire:model.live="maquinaFiltro" label="Máquina" class="sm:w-48">
            <flux:select.option value="">Todas</flux:select.option>
            @foreach ($this->maquinas as $id => $nombre)
                <flux:select.option :value="$id">{{ $nombre }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input type="month" wire:model.live="mes" label="Mes" class="sm:w-48" />
    </div>

    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             :al-editar="$puede['modificar'] ? 'abrirEdicion' : null"
             :mostrar-filtros="false"
             objetivos-extra="maquinaFiltro,mes"
             vacio="No hay turnos en el calendario. Usa «Generar mes» para llenarlo."
             vacio-icono="fa-calendar-days">
        <x-slot:acciones>
            <x-tabla-acciones-crud :puede="$puede" :seleccionado="$seleccionado" confirmar="¿Eliminar el turno?">
                @if ($puede['crear'])
                    <flux:button icon="calendar-days" class="min-h-touch" wire:click="abrirGenerar">Generar mes</flux:button>
                @endif
            </x-tabla-acciones-crud>
        </x-slot:acciones>
    </x-tabla>

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
