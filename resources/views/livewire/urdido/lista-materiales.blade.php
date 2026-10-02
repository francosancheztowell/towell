<div class="flex min-h-0 flex-1 flex-col">
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             grupo-campo="Folio"
             :hijos="$hijos"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             :al-editar="$puede['modificar'] ? 'abrirEdicion' : null"
             :filtro-arriba="true"
             :mostrar-filtros="false"
             vacio="Aún no hay materiales"
             vacio-icono="fa-list-check">

        <x-slot:acciones>
            <x-tabla-acciones-crud :puede="$puede" :seleccionado="$seleccionado" confirmar="¿Eliminar el material?">
                @if ($puede['crear'])
                    <flux:button icon="arrow-down-tray" class="min-h-touch" wire:click="abrirImportar">Crear desde urdido</flux:button>
                @endif
            </x-tabla-acciones-crud>
        </x-slot:acciones>
    </x-tabla>

    {{-- Alta / edición. <dialog> nativo y no flux:modal: el layout no carga Alpine por su cuenta
         (ver navbar). wire:ignore.self evita que un refresco le quite `open`. --}}
    @if ($editando !== null)
        <dialog wire:key="urdbom-{{ $editando }}"
                wire:ignore.self
                x-data
                x-init="$el.showModal()"
                x-on:close="$wire.cerrar()"
                x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="urdbom-titulo"
                class="ui-dialogo ui-dialogo--xl ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="urdbom-titulo" size="xl">
                    {{ $editando === '' ? 'Nuevo material' : 'Editar material' }}
                </flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.Folio" label="Folio" maxlength="20" required autofocus autocomplete="off"
                                description:trailing="Se guarda como texto: conserva los ceros a la izquierda." />
                    <flux:input wire:model="form.Lmat" label="No. Lmat" maxlength="100" required autocomplete="off" />
                    <flux:input wire:model="form.Calibre" label="Calibre" maxlength="50" autocomplete="off" />
                    <flux:input wire:model="form.Config" label="Config" maxlength="50" autocomplete="off" />
                    <flux:input wire:model="form.Color" label="Color" maxlength="50" autocomplete="off" />
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="form.Cantidad" label="Cantidad" inputmode="decimal" data-solo="decimal" data-decimales="2"
                                    class:input="text-right tabular-nums" autocomplete="off" />
                        <flux:input wire:model="form.Porcentaje" label="%" inputmode="decimal" data-solo="decimal" data-decimales="2"
                                    class:input="text-right tabular-nums" autocomplete="off" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="form.cump" label="Cump" inputmode="decimal" data-solo="decimal" data-decimales="4"
                                    class:input="text-right tabular-nums" autocomplete="off" />
                        <flux:input wire:model="form.importe" label="Importe" inputmode="decimal" data-solo="decimal" data-decimales="4"
                                    class:input="text-right tabular-nums" autocomplete="off" />
                    </div>
                </div>

                <x-dialogo-botones />
            </form>
        </dialog>
    @endif

    {{-- Crear desde urdido: órdenes de UrdProgramaUrdido → líneas de AX BOM por BomId. --}}
    @if ($importando)
        <dialog wire:key="urdbom-importar"
                wire:ignore.self
                x-data
                x-init="$el.showModal()"
                x-on:close="$wire.set('importando', false)"
                x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="urdbom-importar-titulo"
                class="ui-dialogo ui-dialogo--formulario">
            <form wire:submit="importarDesdeUrdido" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="urdbom-importar-titulo" size="xl">Crear desde urdido</flux:heading>
                <flux:text>Toma las órdenes del programa de urdido y copia el BOM de AX de cada una. Sin filtros toma todas; los folios que ya están en la lista se saltan.</flux:text>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input type="date" wire:model="importar.desde" label="Fecha prog. desde" />
                    <flux:input type="date" wire:model="importar.hasta" label="Fecha prog. hasta" />
                    <div class="sm:col-span-2">
                        <flux:input wire:model="importar.folio" label="Folio" maxlength="1000" autocomplete="off"
                                    placeholder="1-20, 35, 001*"
                                    description:trailing="Como en AX: coma = varios, guion = rango (1-20), * = comodín, ! = excluir. Sin ceros: 5 = 00005." />
                    </div>
                </div>

                <x-dialogo-botones accion="importarDesdeUrdido" texto="Crear" />
            </form>
        </dialog>
    @endif
</div>
