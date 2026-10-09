@php
    $cifras = [
        ['Programado', number_format((float) ($resumenCrudo['totalProgramado'] ?? 0))],
        ['Producido', number_format((float) ($resumenCrudo['totalProducido'] ?? 0))],
        ['Kg', number_format((float) ($resumenCrudo['totalKg'] ?? 0))],
        ['Avance', number_format((float) ($resumenCrudo['avanceGlobal'] ?? 0)).'%'],
        ['Telares activos', (int) ($resumenCrudo['telaresActivos'] ?? 0).' / '.(int) ($resumenCrudo['telaresTotal'] ?? 0)],
    ];
@endphp

{{-- Resumen de Crudo: en azul para que destaque sobre las tarjetas de cada orden. --}}
<section class="overflow-hidden rounded-xl bg-blue-600 text-white shadow-sm" aria-label="Resumen de crudo" data-prod-summary-card>
    <dl class="grid grid-cols-2 divide-blue-500 sm:grid-cols-5 sm:divide-x">
        @foreach ($cifras as [$etiqueta, $valor])
            <div class="px-4 py-3">
                <dt class="text-xs font-medium text-blue-100">{{ $etiqueta }}</dt>
                <dd class="mt-0.5 text-2xl font-semibold tabular-nums">{{ $valor }}</dd>
            </div>
        @endforeach
    </dl>
</section>
