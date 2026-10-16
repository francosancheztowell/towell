<div class="flex min-h-0 flex-1 flex-col">
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             al-editar="abrirEdicion"
             vacio="No se encontraron fallas"
             vacio-icono="fa-triangle-exclamation"
             buscar-placeholder="Buscar falla, descripción o abreviado…">

        {{-- Acciones: se teletransportan al navbar. --}}
        <x-slot:acciones>
            <x-navbar.button-create wire:click="abrirAlta" module="Catalogo de Fallas" title="Nueva falla" />

            <x-navbar.button-edit wire:click="abrirEdicion" module="Catalogo de Fallas"
                                  :disabled="$seleccionado === null"
                                  title="{{ $seleccionado === null ? 'Selecciona una fila para editar' : 'Editar falla' }}" />

            <x-navbar.button-delete module="Catalogo de Fallas"
                                    x-on:click="notify.confirm({ title: '¿Eliminar esta falla?', text: 'La acción no se puede deshacer.', confirmText: 'Sí, eliminar', confirmColor: '#dc2626' }).then(ok => ok && $wire.eliminar())"
                                    :disabled="$seleccionado === null"
                                    title="{{ $seleccionado === null ? 'Selecciona una fila para eliminar' : 'Eliminar falla' }}" />
        </x-slot:acciones>

        {{-- Filtros: junto al buscador, dentro de la barra de la tabla. --}}
        <x-slot:filtros>
            <flux:select wire:model.live="tipoFallaFiltro" size="sm" aria-label="Filtrar por tipo de falla" class="sm:w-44">
                <flux:select.option value="">Tipo: todos</flux:select.option>
                @foreach ($tiposFalla as $tipo)
                    <flux:select.option :value="$tipo">{{ $tipo }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="departamentoFiltro" size="sm" aria-label="Filtrar por departamento" class="sm:w-44">
                <flux:select.option value="">Depto: todos</flux:select.option>
                @foreach ($departamentos as $departamento)
                    <flux:select.option :value="$departamento">{{ $departamento }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($tipoFallaFiltro !== '' || $departamentoFiltro !== '' || $buscar !== '')
                <flux:button size="sm" icon="x-mark" class="min-h-touch" wire:click="limpiarFiltros" aria-label="Limpiar filtros" title="Limpiar filtros">
                    <span class="hidden sm:inline">Limpiar</span>
                </flux:button>
            @endif
        </x-slot:filtros>
    </x-tabla>

    {{-- Alta / edición. <dialog> nativo como en los demás catálogos; wire:ignore.self evita que un refresco le quite `open`. --}}
    @if ($editando !== null)
        <dialog wire:key="falla-{{ $editando }}" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="titulo-falla" class="ui-dialogo ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="titulo-falla" size="xl">{{ $editando === '' ? 'Nueva falla' : 'Editar falla' }}</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.TipoFallaId" label="Tipo de falla" required autofocus autocomplete="off" />
                    <flux:select wire:model="form.Departamento" label="Departamento" required>
                        <flux:select.option value="">Selecciona…</flux:select.option>
                        @foreach ($departamentos as $departamento)
                            <flux:select.option :value="$departamento">{{ $departamento }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="form.Falla" label="Falla" required autocomplete="off" />
                    <flux:input wire:model="form.Abreviado" label="Abreviado" autocomplete="off" />
                    <flux:input wire:model="form.Seccion" label="Sección" autocomplete="off" />
                    <div class="sm:col-span-2">
                        <flux:textarea wire:model="form.Descripcion" label="Descripción" rows="2" />
                    </div>
                </div>

                <x-dialogo-botones />
            </form>
        </dialog>
    @endif
</div>
