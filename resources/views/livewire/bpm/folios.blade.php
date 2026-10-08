<div class="flex min-h-0 flex-1 flex-col">
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             al-editar="abrirChecklist"
             :filtro-arriba="true"
             objetivos-extra="alcance,misFolios"
             vacio="No hay folios con estos filtros"
             vacio-icono="fa-clipboard-check"
             buscar-placeholder="Buscar folio o nombre…">
        <x-slot:acciones>
            <flux:button variant="primary" color="blue" icon="plus" class="min-h-touch" wire:click="abrirAlta">Nuevo</flux:button>
            <flux:button icon="clipboard-document-check" class="min-h-touch" wire:click="abrirChecklist" :disabled="$seleccionado === null">Checklist</flux:button>
            @if ($puedeEliminar)
                <flux:button variant="danger" icon="trash" class="min-h-touch" :disabled="$seleccionado === null"
                             x-on:click="notify.confirm({ title: '¿Eliminar el folio?', text: 'Solo se eliminan folios Creados, con su checklist. No se puede deshacer.', confirmText: 'Sí, eliminar', confirmColor: '#dc2626' }).then(ok => ok && $wire.eliminar())">
                    Eliminar
                </flux:button>
            @endif
        </x-slot:acciones>

        <x-slot:filtros>
            <flux:select wire:model.live="alcance" size="sm" aria-label="Status" class="sm:w-44">
                @foreach (\App\Livewire\Bpm\Folios::ALCANCES as $valor => $texto)
                    <flux:select.option :value="$valor">{{ $texto }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button size="sm" icon="user" wire:click="$toggle('misFolios')" :variant="$misFolios ? 'primary' : 'outline'"
                         aria-pressed="{{ $misFolios ? 'true' : 'false' }}">Mis folios</flux:button>
        </x-slot:filtros>
    </x-tabla>

    {{-- Alta: <dialog> nativo como en los catálogos (el layout no carga Alpine para flux:modal). --}}
    @if ($creando)
        <dialog wire:key="bpm-alta" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="bpm-alta-titulo" class="ui-dialogo ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="bpm-alta-titulo" size="xl">Nuevo folio {{ $area->titulo() }}</flux:heading>

                <flux:fieldset>
                    <flux:legend>Recibe</flux:legend>
                    <flux:text>{{ auth()->user()->numero_empleado }} — {{ auth()->user()->nombre }}</flux:text>
                </flux:fieldset>

                <flux:select wire:model="form.entrega" label="Entrega" placeholder="Selecciona quién entrega…" required>
                    @foreach ($entregadores as $persona)
                        <flux:select.option :value="$persona['numero']">{{ $persona['nombre'] }} · {{ $persona['numero'] }}{{ filled($persona['turno']) ? ' · T'.$persona['turno'] : '' }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($entregadores->isEmpty())
                    <flux:text class="text-amber-700">
                        {{ $area->porTelar() ? 'No hay operadores en tus telares (Telares x Operador).' : 'No hay personal de '.ucfirst($area->value).' para elegir.' }}
                    </flux:text>
                @endif

                @unless ($area->porTelar())
                    <flux:select wire:model="form.maquina" label="Máquina" placeholder="Selecciona la máquina…" required>
                        @foreach ($maquinas as $id => $nombre)
                            <flux:select.option :value="$id">{{ $nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endunless

                <x-dialogo-botones texto="Crear y abrir checklist" />
            </form>
        </dialog>
    @endif
</div>
