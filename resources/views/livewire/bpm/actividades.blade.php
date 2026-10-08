<div class="flex min-h-0 flex-1 flex-col">
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             :al-editar="$puede['modificar'] ? 'abrirEdicion' : null"
             :filtro-arriba="true"
             vacio="No hay actividades registradas"
             vacio-icono="fa-list-check"
             buscar-placeholder="Buscar actividad…">
        <x-slot:acciones>
            <x-tabla-acciones-crud :puede="$puede" :seleccionado="$seleccionado" confirmar="¿Eliminar la actividad?" />
        </x-slot:acciones>
    </x-tabla>

    @if ($editando !== null)
        <dialog wire:key="actividad-{{ $editando }}" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="actividad-titulo" class="ui-dialogo ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="actividad-titulo" size="xl">{{ $editando === '' ? 'Nueva actividad' : 'Editar actividad' }}</flux:heading>

                <div class="grid grid-cols-1 gap-4">
                    <flux:textarea wire:model="form.Actividad" label="Actividad" rows="2" maxlength="100" required autofocus />
                    @if (array_key_exists('Orden', $form))
                        <flux:input wire:model="form.Orden" type="number" min="1" label="Orden" description="Posición en el checklist." />
                    @endif
                    @if (array_key_exists('Maquina', $form))
                        <flux:select wire:model="form.Maquina" label="Máquina" placeholder="Selecciona…" required>
                            @foreach ($maquinas as $clave => $nombre)
                                <flux:select.option :value="$clave">{{ $nombre }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>

                <x-dialogo-botones />
            </form>
        </dialog>
    @endif
</div>
