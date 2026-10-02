<div class="flex min-h-0 flex-1 flex-col">
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             :al-editar="$puede['modificar'] ? 'abrirEdicion' : null"
             :filtro-arriba="true"
             :mostrar-filtros="false"
             vacio="No hay julios registrados"
             vacio-icono="fa-circle-dot">
        <x-slot:acciones>
            <x-tabla-acciones-crud :puede="$puede" :seleccionado="$seleccionado" confirmar="¿Eliminar el julio?" />
        </x-slot:acciones>
    </x-tabla>

    {{-- Alta / edición. <dialog> nativo; wire:ignore.self evita que un refresco le quite `open`. --}}
    @if ($editando !== null)
        <dialog wire:key="julio-{{ $editando }}" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="julio-titulo" class="ui-dialogo ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="julio-titulo" size="xl">{{ $editando === '' ? 'Nuevo julio' : 'Editar julio' }} · {{ $departamento }}</flux:heading>

                <div class="grid grid-cols-1 gap-4">
                    <flux:input wire:model="form.NoJulio" label="No. Julio" maxlength="50" required autofocus autocomplete="off" />
                    <flux:input wire:model="form.Tara" label="Tara" inputmode="decimal" data-solo="decimal" data-decimales="2"
                                class:input="text-right tabular-nums" autocomplete="off" description:trailing="Vacía = 0." />
                </div>

                <x-dialogo-botones />
            </form>
        </dialog>
    @endif
</div>
