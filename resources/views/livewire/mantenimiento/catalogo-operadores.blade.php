<div class="flex min-h-0 flex-1 flex-col">
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             al-editar="abrirEdicion"
             vacio="No se encontraron operadores"
             vacio-icono="fa-inbox"
             buscar-placeholder="Buscar clave, nombre, departamento o teléfono…">

        {{-- Acciones: se teletransportan al navbar. Permisos por idrol 53 (Mantenimiento). --}}
        <x-slot:acciones>
            <x-navbar.button-create wire:click="abrirAlta" :module-id="53" title="Nuevo operador" />

            <x-navbar.button-edit wire:click="abrirEdicion" :module-id="53"
                                  :disabled="$seleccionado === null"
                                  title="{{ $seleccionado === null ? 'Selecciona una fila para editar' : 'Editar operador' }}" />

            <x-navbar.button-delete :module-id="53"
                                    x-on:click="notify.confirm({ title: '¿Eliminar este operador?', text: 'La acción no se puede deshacer.', confirmText: 'Sí, eliminar', confirmColor: '#dc2626' }).then(ok => ok && $wire.eliminar())"
                                    :disabled="$seleccionado === null"
                                    title="{{ $seleccionado === null ? 'Selecciona una fila para eliminar' : 'Eliminar operador' }}" />
        </x-slot:acciones>

        <x-slot:filtros>
            <flux:select wire:model.live="turnoFiltro" size="sm" aria-label="Filtrar por turno" class="sm:w-44">
                <flux:select.option value="">Turno: todos</flux:select.option>
                @foreach (\App\Livewire\Mantenimiento\CatalogoOperadores::TURNOS as $turno)
                    <flux:select.option :value="$turno">Turno {{ $turno }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="deptoFiltro" size="sm" aria-label="Filtrar por departamento" class="sm:w-44">
                <flux:select.option value="">Depto: todos</flux:select.option>
                @foreach ($departamentos as $departamento)
                    <flux:select.option :value="$departamento">{{ $departamento }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($turnoFiltro !== '' || $deptoFiltro !== '' || $buscar !== '')
                <flux:button size="sm" icon="x-mark" class="min-h-touch" wire:click="limpiarFiltros" aria-label="Limpiar filtros" title="Limpiar filtros">
                    <span class="hidden sm:inline">Limpiar</span>
                </flux:button>
            @endif
        </x-slot:filtros>
    </x-tabla>

    {{-- Alta / edición. <dialog> nativo como en los demás catálogos; wire:ignore.self evita que un refresco le quite `open`. --}}
    @if ($editando !== null)
        <dialog wire:key="operador-{{ $editando }}" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="titulo-operador" class="ui-dialogo ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="titulo-operador" size="xl">{{ $editando === '' ? 'Nuevo operador' : 'Editar operador' }}</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.CveEmpl" label="Clave de empleado" required autofocus autocomplete="off" />
                    <flux:input wire:model="form.NomEmpl" label="Nombre" required autocomplete="off" />
                    <flux:input wire:model="form.Depto" label="Departamento" required autocomplete="off" />
                    <flux:input wire:model="form.Telefono" type="tel" label="Teléfono" autocomplete="off" />
                    <flux:select wire:model="form.Turno" label="Turno" placeholder="Seleccione un turno" required>
                        @foreach (\App\Livewire\Mantenimiento\CatalogoOperadores::TURNOS as $turno)
                            <flux:select.option :value="$turno">Turno {{ $turno }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <x-dialogo-botones />
            </form>
        </dialog>
    @endif
</div>
