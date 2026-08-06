@php
    $salonOrder = ['Karl Mayer', 'Jacquard', 'Smith', 'Sin clasificar'];
    $salonLabels = ['Karl Mayer' => 'KM'];
@endphp

<div class="crudo-salons-grid" wire:ignore data-crudo-machine-grid>
    @foreach ($salonOrder as $salon)
        @php($salonLayout = $floorLayouts[$salon] ?? null)
        @continue($salonLayout === null || $salonLayout['count'] === 0)

        <section class="crudo-salon crudo-salon-{{ str($salon)->slug() }}">
            <header>
                <h2>{{ $salonLabels[$salon] ?? $salon }}</h2>
                <span data-crudo-salon-count="{{ $salon }}">{{ $salonLayout['count'] }} máquinas</span>
            </header>

            @if ($salonLayout['physical'])
                <div class="crudo-machine-grid crudo-machine-grid-physical">
                    @foreach ($salonLayout['columns'] as $columnIndex => $machineColumn)
                        <div class="crudo-machine-column" data-column="{{ $columnIndex + 1 }}">
                            @foreach ($machineColumn as $machine)
                                <x-crudo.machine-card :machine="$machine" />
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @else
                <div class="crudo-machine-grid">
                    @foreach ($salonLayout['columns'][0] as $machine)
                        <x-crudo.machine-card :machine="$machine" />
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
</div>
