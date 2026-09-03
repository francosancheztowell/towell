@props(['machine'])

@php
    // El nombre de salón ya viene normalizado por CrudoDashboardService::normalizeSalon.
    $loomImages = [
        'Karl Mayer' => 'karlmayer',
        'Jacquard' => 'jacquard',
        'Smith' => 'smith',
    ];
    $loomImage = $loomImages[$machine['salon'] ?? ''] ?? 'jacquard';
    $fueraDeOperacion = in_array((string) $machine['telar'], config('crudo.telares_fuera', []), true);
    $saldoPedidoValue = $machine['programa']['saldoPedido'] ?? null;
    // Saldo negativo: se produjo/asignó de más contra el pedido. Aviso aparte
    // del color de estado del telar (paro/segundas/bajos kg/operando).
    $saldoEsNegativo = ! $fueraDeOperacion
        && is_numeric($saldoPedidoValue)
        && (float) $saldoPedidoValue < 0;
@endphp

<button
    type="button"
    class="crudo-machine-card group{{ $fueraDeOperacion ? ' crudo-machine-card-fuera' : '' }}"
    data-crudo-machine
    data-telar="{{ $machine['telar'] }}"
    data-state="{{ $machine['state'] }}"
    data-signature="{{ $machine['state'] }}:{{ $machine['pieces'] }}:{{ $machine['seconds'] }}:{{ $machine['kilos'] }}:{{ $machine['efficiencyPercent'] ?? 0 }}:{{ $saldoEsNegativo ? '1' : '0' }}"
    @if ($saldoEsNegativo) data-saldo-negativo @endif
    @if ($fueraDeOperacion)
        disabled
        tabindex="-1"
        aria-hidden="true"
    @else
        aria-label="Abrir detalle del telar {{ $machine['telar'] }}, estado {{ $machine['stateLabel'] }}{{ $saldoEsNegativo ? ', saldo negativo' : '' }}"
    @endif
>
    @unless ($fueraDeOperacion)
        <span class="crudo-loom-number">{{ $machine['telar'] }}</span>
        <span class="crudo-machine-state-dot" aria-hidden="true">
            <i class="fa-solid {{ $machine['stateIcon'] ?? 'fa-circle-question' }}"></i>
        </span>
    @endunless

    {{--
        Una sola foto WebP recortada por salón. El tinte de estado va en el
        ::after, enmascarado con la misma imagen, para que coloree la máquina y
        no el rectángulo. Las tres imágenes se comparten entre los 39 telares.
    --}}
    @unless ($fueraDeOperacion)
        <span
            class="crudo-loom"
            style="--loom-image: url('{{ asset("images/crudo/{$loomImage}.webp") }}')"
            aria-hidden="true"
            data-crudo-loom
        ></span>

        <span class="crudo-machine-quality" data-crudo-efficiency>{{ number_format(round((float) ($machine['efficiencyPercent'] ?? 0))) }}%</span>
        <span class="crudo-machine-metric" data-crudo-kilos>{{ number_format(round((float) $machine['kilos'])) }} kg</span>
    @endunless

    @unless ($fueraDeOperacion)
        <span class="crudo-machine-tooltip" role="tooltip">
            <strong data-crudo-name>{{ $machine['name'] }}</strong>
            <span data-crudo-tooltip-metrics>{{ number_format((float) $machine['pieces']) }} piezas · {{ number_format((float) $machine['seconds']) }} segundas</span>
            <span class="crudo-machine-tooltip-saldo" data-crudo-tooltip-saldo @unless ($saldoEsNegativo) hidden @endunless>
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span data-crudo-tooltip-saldo-value>Saldo {{ number_format((float) $saldoPedidoValue) }}</span>
            </span>
        </span>
    @endunless
</button>
