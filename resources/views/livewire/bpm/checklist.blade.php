<div class="flex min-h-0 flex-1 flex-col gap-2">
    @teleport('#tabla-navbar-acciones')
        <div class="flex items-center gap-2">
            <flux:button icon="arrow-left" class="min-h-touch" :href="route($area->value.'.bpm.folios')">Folios</flux:button>
            @if ($editable)
                <flux:button variant="primary" color="green" icon="check" class="min-h-touch" wire:click="terminar">
                    {{ $esSupervisor ? 'Terminar y autorizar' : 'Terminar' }}
                </flux:button>
            @endif
            @if ($supervisa)
                <flux:button variant="primary" color="green" icon="check-badge" class="min-h-touch" wire:click="autorizar">Autorizar</flux:button>
                <flux:button variant="danger" icon="arrow-uturn-left" class="min-h-touch"
                             x-on:click="notify.confirm({ title: '¿Rechazar el folio?', text: 'Regresa a Creado para que lo corrijan.', confirmText: 'Sí, rechazar' }).then(ok => ok && $wire.rechazar())">
                    Rechazar
                </flux:button>
            @endif
        </div>
    @endteleport

    <div class="grid shrink-0 grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
        <div><flux:text size="sm">Folio</flux:text><flux:heading size="lg">{{ $encabezado->Folio }}</flux:heading></div>
        <div><flux:text size="sm">Fecha</flux:text><div class="font-medium">{{ $encabezado->Fecha?->format('d/m/Y H:i') }}</div></div>
        <div><flux:text size="sm">Recibe</flux:text><div class="font-medium">{{ $encabezado->NombreEmplRec }} <span class="text-slate-500">· T{{ $encabezado->TurnoRecibe }}</span></div></div>
        <div><flux:text size="sm">Entrega</flux:text><div class="font-medium">{{ $encabezado->NombreEmplEnt }} <span class="text-slate-500">· T{{ $encabezado->TurnoEntrega }}</span></div></div>
        <div>
            <flux:text size="sm">{{ $maquina !== null ? 'Máquina' : 'Autoriza' }}</flux:text>
            <div class="font-medium">{{ $maquina ?? ($encabezado->{$area->columnaAutoriza()} ?: '—') }}</div>
        </div>
        <div><flux:text size="sm">Status</flux:text>@include('livewire.bpm.estatus', ['status' => $encabezado->Status])</div>
    </div>

    <div class="flex shrink-0 flex-wrap gap-3 px-1 text-caption text-slate-600" aria-label="Leyenda">
        @foreach ($area->marcas() as [$icono, $color, $etiqueta])
            <span class="inline-flex items-center gap-1"><flux:badge size="sm" :color="$color">{{ $icono }}</flux:badge>{{ $etiqueta }}</span>
        @endforeach
        <span class="text-slate-400">· toca una casilla para cambiarla</span>
    </div>

    {{-- Actividades × columnas (telares en Tejedores, la máquina en Urdido / Engomado). --}}
    <div class="tabla-pantalla overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <flux:table class="tabla-cebra">
            <flux:table.columns sticky class="bg-white">
                <flux:table.column sticky class="min-w-48 bg-white">Actividad</flux:table.column>
                @foreach ($columnas as $col)
                    <flux:table.column align="center" class="min-w-16">{{ $area->porTelar() ? $col : 'Marca' }}</flux:table.column>
                @endforeach
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($actividades as $actividad)
                    <flux:table.row wire:key="act-{{ $actividad->Orden }}">
                        <flux:table.cell sticky variant="strong" class="bg-inherit whitespace-normal">
                            <span class="me-1 text-zinc-400">{{ $loop->iteration }}</span>{{ $actividad->Actividad }}
                        </flux:table.cell>
                        @foreach ($columnas as $col)
                            @php
                                $linea = $celdas[$actividad->Orden][$col] ?? null;
                                [$icono, $color, $etiqueta] = $area->marcas()[(string) $linea?->Valor] ?? $area->marcas()[array_key_first($area->marcas())];
                            @endphp
                            <flux:table.cell align="center" class="py-1!">
                                @if ($linea)
                                    <button type="button" wire:click="marcar({{ $linea->Id }})" @disabled(! $editable)
                                            wire:loading.attr="data-ocupado" wire:target="marcar({{ $linea->Id }})"
                                            aria-label="{{ $actividad->Actividad }}{{ $area->porTelar() ? ' · telar '.$col : '' }}: {{ $etiqueta }}"
                                            class="bpm-marca bpm-marca--{{ $color }} size-touch">{{ $icono }}</button>
                                @endif
                            </flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2" class="py-6 text-center">
                            {{ $area->porTelar() ? 'El folio no tiene telares: revisa Telares x Operador de quien recibe.' : 'No hay actividades configuradas para esta máquina.' }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($area->porTelar())
        <flux:textarea wire:model.blur="comentarios" rows="2" maxlength="150" label="Comentarios" :disabled="! $editable"
                       placeholder="Observaciones del turno (máx. 150)" class="shrink-0" />
    @endif
</div>
