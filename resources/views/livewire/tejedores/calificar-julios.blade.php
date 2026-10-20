{{-- Calificar Julios (Tejedores): telar + tipo → 2 últimos julios de AtaMontadoTelas → defectos → historial 7 días. --}}
@php
    $etiqueta = \App\Livewire\Tejedores\CalificarJulios::etiquetaTipo(...);
    $colorTipo = fn (string $t): string => match ($t) { 'Rizo' => 'sky', 'Pie' => 'amber', default => 'violet' };
    // Flujo del atado: En Proceso → Terminado → Calificado (atador) → Autorizado (supervisor).
    $colorEstatus = fn (?string $e): string => match ($e) { 'En Proceso' => 'yellow', 'Terminado' => 'blue', 'Calificado' => 'indigo', 'Autorizado' => 'green', default => 'zinc' };
@endphp
<div class="mx-auto flex w-full max-w-5xl flex-col gap-4">
    @if ($this->telares === [])
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.heading>Sin telares asignados</flux:callout.heading>
            <flux:callout.text>No tienes telares en Telares x Operador. Pide que te los asignen para calificar julios.</flux:callout.text>
        </flux:callout>
    @else
        {{-- Datos del telar y del julio --}}
        <flux:card class="flex flex-col gap-4">
            <div class="flex flex-wrap items-end gap-4">
                <flux:select wire:model.live="telar" label="Telar" class="w-40">
                    @foreach ($this->telares as $t)
                        <flux:select.option :value="$t">{{ $t }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:radio.group wire:key="tipos-{{ implode('-', $this->tipos) }}" wire:model.live="tipo" label="Tipo" variant="segmented">
                    @foreach ($this->tipos as $t)
                        <flux:radio :value="$t" :label="$etiqueta($t)" />
                    @endforeach
                </flux:radio.group>

                {{-- Abre el modal de calificación para el julio elegido en la tabla. --}}
                @if ($puedeCrear)
                    <flux:button wire:click="abrirCalificar" variant="primary" icon="clipboard-document-check"
                                 :disabled="$montadoId === null" class="min-h-touch px-6! text-base!"
                                 title="{{ $montadoId === null ? 'Elige un julio de la tabla' : 'Calificar el julio elegido' }}">
                        Calificar
                    </flux:button>
                @endif
            </div>

            <div>
                <flux:heading size="sm" class="mb-2">No. Julio <span class="font-normal text-zinc-500">· últimos 2 atados</span></flux:heading>

                @if ($this->julios->isEmpty())
                    <flux:text class="rounded-lg border border-dashed border-zinc-300 p-4 text-center">
                        No hay julios de {{ $etiqueta($tipo) }} atados en el telar {{ $telar }}.
                    </flux:text>
                @else
                    {{-- Clic en la fila (o en su radio) elige el julio que se califica. --}}
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column class="w-10"><span class="sr-only">Elegir</span></flux:table.column>
                            <flux:table.column>No. Julio</flux:table.column>
                            <flux:table.column>Tipo</flux:table.column>
                            <flux:table.column>Orden</flux:table.column>
                            <flux:table.column>Fecha</flux:table.column>
                            <flux:table.column class="hidden lg:table-cell">Turno</flux:table.column>
                            <flux:table.column>Atador</flux:table.column>
                            <flux:table.column>Estatus</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->julios as $j)
                                <flux:table.row wire:key="julio-{{ $j->Id }}" wire:click="$set('montadoId', {{ $j->Id }})"
                                                @class([
                                                    'cursor-pointer transition',
                                                    'bg-blue-50' => $montadoId === $j->Id,
                                                    'hover:bg-zinc-50' => $montadoId !== $j->Id,
                                                ])>
                                    <flux:table.cell class="!pl-3">
                                        <input type="radio" name="montado" value="{{ $j->Id }}" wire:model.live="montadoId"
                                               aria-label="Calificar julio {{ $j->NoJulio }}" class="size-4 accent-blue-600">
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="font-semibold text-zinc-800">{{ $j->NoJulio }}</div>
                                        <div @class(['text-caption', 'font-medium text-green-700' => $loop->first, 'text-zinc-500' => ! $loop->first])>{{ $loop->first ? 'Actual' : 'Anterior' }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$colorTipo($j->Tipo)">{{ $etiqueta($j->Tipo) }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $j->NoProduccion }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($j->Fecha)->format('d/m/Y') }}</flux:table.cell>
                                    <flux:table.cell class="hidden lg:table-cell">{{ $j->Turno }}</flux:table.cell>
                                    <flux:table.cell class="max-w-36 truncate lg:max-w-none" title="{{ $j->NomTejedor }}">{{ $j->NomTejedor }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$colorEstatus($j->Estatus)">{{ $j->Estatus ?? '—' }}</flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </flux:card>

        {{-- Calificación o defectos encontrados: mismo contenido que el modal de engomado (oficial, fecha, julio, defecto). --}}
        @if ($calificando && ($julio = $this->julios->firstWhere('Id', $montadoId)))
            <dialog wire:key="calificar-{{ $julio->Id }}" wire:ignore.self x-data x-init="$el.showModal()"
                    x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                    aria-labelledby="calificar-titulo" class="ui-dialogo ui-dialogo--lg ui-dialogo--formulario">
                <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                    <flux:heading id="calificar-titulo" size="xl" class="flex items-center gap-2">
                        <i class="fa-solid fa-clipboard-check text-purple-600" aria-hidden="true"></i>
                        Calificar julio {{ $julio->NoJulio }}
                    </flux:heading>

                    {{-- Resumen en una rejilla de 3 columnas: julio / atado / calificación. Los nombres
                         ocupan 2 celdas porque son largos. Se lee de un vistazo en tablet sin scroll. --}}
                    <dl class="grid grid-cols-3 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 text-sm">
                        @foreach ([
                            'Telar' => $telar,
                            'Tipo' => null,
                            'Orden' => $julio->NoProduccion,
                            'Atador' => $julio->NomTejedor ?: '—',
                            'Fecha del atado' => \Illuminate\Support\Carbon::parse($julio->Fecha)->format('d/m/Y'),
                            'Oficial' => auth()->user()->nombre,
                            'Fecha de calificación' => now()->format('d/m/Y H:i'),
                        ] as $dato => $valor)
                            <div @class(['min-w-0 bg-white px-3 py-2', 'col-span-2' => in_array($dato, ['Atador', 'Oficial'], true)])>
                                <dt class="text-caption text-zinc-500">{{ $dato }}</dt>
                                <dd class="truncate font-semibold text-zinc-800">
                                    @if ($dato === 'Tipo')
                                        <flux:badge size="sm" :color="$colorTipo($julio->Tipo)">{{ $etiqueta($julio->Tipo) }}</flux:badge>
                                    @else
                                        {{ $valor }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>

                    <flux:select wire:model="defectoId" label="Defecto" placeholder="Elige el defecto…" required>
                        @foreach ($this->defectos as $d)
                            <flux:select.option :value="$d->Id">{{ $d->Clave }} — {{ $d->Defecto }} ({{ rtrim(rtrim(number_format($d->Penalizacion, 2), '0'), '.') }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="montadoId" />

                    <x-dialogo-botones texto="Calificar" />
                </form>
            </dialog>
        @endif

        {{-- Historial --}}
        <flux:card class="!p-0 overflow-hidden">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-zinc-200 px-4 py-3">
                <flux:heading size="lg">Historial</flux:heading>
                <flux:text size="sm">Telar {{ $telar }} · {{ $etiqueta($tipo) }}</flux:text>
                <div class="ml-auto flex gap-2" role="group" aria-label="Periodo del historial">
                    @foreach (\App\Livewire\Tejedores\CalificarJulios::PERIODOS as $d => $texto)
                        <flux:badge as="button" wire:click="$set('dias', {{ $d }})" aria-pressed="{{ $dias === $d ? 'true' : 'false' }}"
                                    :color="$dias === $d ? 'blue' : 'zinc'" :variant="$dias === $d ? 'solid' : null"
                                    class="min-h-touch cursor-pointer px-3!">{{ $texto }}</flux:badge>
                    @endforeach
                </div>
            </div>

            @if ($this->historial->isEmpty())
                <flux:text class="p-6 text-center">Sin calificaciones en este periodo.</flux:text>
            @else
                <flux:table class="px-4">
                    <flux:table.columns>
                        <flux:table.column>Fecha</flux:table.column>
                        <flux:table.column>Julio</flux:table.column>
                        <flux:table.column>Oficial</flux:table.column>
                        <flux:table.column>Defecto</flux:table.column>
                        <flux:table.column align="end">Penalización</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->historial as $c)
                            <flux:table.row wire:key="cal-{{ $c->Id }}">
                                <flux:table.cell class="whitespace-nowrap">{{ $c->Fecha->format('d/m/Y H:i') }}</flux:table.cell>
                                <flux:table.cell>{{ $c->montado?->NoJulio }}</flux:table.cell>
                                <flux:table.cell>{{ $c->NomEmpleado ?? $c->NoEmpleado }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" color="zinc">{{ $c->defecto?->Clave }}</flux:badge>
                                    {{ $c->defecto?->Defecto }}
                                </flux:table.cell>
                                <flux:table.cell align="end" variant="strong">{{ rtrim(rtrim(number_format($c->Penalizacion, 2), '0'), '.') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($c->NoEmpleado === $miEmpleado)
                                        <flux:button size="sm" variant="ghost" icon="trash" class="min-h-touch min-w-touch text-red-600!"
                                                     wire:click="confirmarBorrar({{ $c->Id }})" aria-label="Eliminar defecto" />
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        {{-- Eliminar: mismo aspecto que notify.confirm (patrón de la baja en Catálogo de Calibres). --}}
        @if ($aBorrar = $this->calificacionABorrar())
            <dialog wire:key="borrar-{{ $aBorrar->Id }}" wire:ignore.self x-data x-init="$el.showModal()"
                    x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                    aria-labelledby="borrar-titulo" class="ui-dialogo">
                <div class="ui-dialogo__cuerpo">
                    <span class="ui-dialogo__icono ui-dialogo__icono--warning">
                        <i class="fa-solid fa-trash-can text-3xl" aria-hidden="true"></i>
                    </span>
                    <div class="ui-dialogo__textos">
                        <p id="borrar-titulo" class="ui-dialogo__titulo">¿Eliminar este defecto?</p>
                        <p class="ui-dialogo__texto">
                            Julio <strong>{{ $aBorrar->montado?->NoJulio }}</strong> ·
                            {{ $aBorrar->defecto?->Clave }} — {{ $aBorrar->defecto?->Defecto }}<br>
                            Calificado el {{ $aBorrar->Fecha->format('d/m/Y H:i') }}. No se puede deshacer.
                        </p>
                    </div>
                    <div class="ui-dialogo__botones">
                        <button type="button" class="ui-dialogo__boton ui-dialogo__boton--secundario"
                                x-on:click="$el.closest('dialog').close()">Cancelar</button>
                        <button type="button" wire:click="borrar" wire:loading.attr="data-ocupado" wire:target="borrar"
                                class="ui-dialogo__boton ui-dialogo__boton--peligro">Eliminar</button>
                    </div>
                </div>
            </dialog>
        @endif
    @endif
</div>
