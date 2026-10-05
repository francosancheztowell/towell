<div class="flex min-h-0 flex-1 flex-col">
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             :al-editar="$puede['modificar'] ? 'abrirEdicion' : null"
             :filtro-arriba="true"
             :mostrar-filtros="false"
             vacio="No hay máquinas registradas"
             vacio-icono="fa-gears">
        <x-slot:acciones>
            <x-tabla-acciones-crud :puede="$puede" :seleccionado="$seleccionado" confirmar="¿Eliminar la máquina?" />
        </x-slot:acciones>
    </x-tabla>

    {{-- Alta / edición. <dialog> nativo y no flux:modal: el layout no carga Alpine por su cuenta
         (ver navbar). wire:ignore.self evita que un refresco le quite `open`. --}}
    @if ($editando !== null)
        <dialog wire:key="maquina-{{ $editando }}" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="maquina-titulo" class="ui-dialogo ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="maquina-titulo" size="xl">{{ $editando === '' ? 'Nueva máquina' : 'Editar máquina' }}</flux:heading>

                <div class="grid grid-cols-1 gap-4">
                    <flux:input wire:model="form.MaquinaId" label="Máquina ID" maxlength="50" required autofocus autocomplete="off" placeholder="Ej: MC Coy 1" />
                    <flux:input wire:model="form.Nombre" label="Nombre" maxlength="100" autocomplete="off" placeholder="Ej: MC Coy 1" />
                    <flux:input wire:model="form.Departamento" label="Departamento" maxlength="50" autocomplete="off" placeholder="Ej: Urdido" />
                    <flux:input wire:model="form.Codificacion" label="Codificación" maxlength="45" autocomplete="off" placeholder="Ej: TOW-MACC1-URDI" />
                </div>

                <x-dialogo-botones />
            </form>
        </dialog>
    @endif
</div>
