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
    data-signature="{{ $machine['state'] }}:{{ $machine['pieces'] }}:{{ $machine['seconds'] }}:{{ $machine['kilos'] }}:{{ $machine['efficiencyPercent'] ?? 0 }}:{{ $machine['rpm'] ?? 0 }}:{{ $saldoPedidoValue ?? 0 }}:{{ $saldoEsNegativo ? '1' : '0' }}"
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
        {{-- 1ª línea: número de telar a la izquierda, silueta de máquina a la derecha más pequeña --}}
        <div class="crudo-machine-header-row">
            <span class="crudo-loom-number">{{ $machine['telar'] }}</span>
            <span
                class="crudo-loom"
                style="--loom-image: url('{{ asset("images/crudo/{$loomImage}.webp") }}')"
                aria-hidden="true"
                data-crudo-loom
            ></span>
        </div>

        {{-- 2ª línea (Ef y RPM) y 3ª línea (Kg y Saldo) en cuadrícula: label arriba, dato abajo --}}
        <div class="crudo-machine-grid-metrics">
            <div class="crudo-machine-metric-cell">
                <span class="crudo-machine-label">Ef</span>
                <span class="crudo-machine-quality" data-crudo-efficiency>{{ number_format(round((float) ($machine['efficiencyPercent'] ?? 0))) }}%</span>
            </div>
            <div class="crudo-machine-metric-cell">
                <span class="crudo-machine-label">RPM</span>
                <span class="crudo-machine-rpm" data-crudo-rpm>{{ isset($machine['rpm']) && $machine['rpm'] !== null ? number_format(round((float) $machine['rpm'])) : '--' }}</span>
            </div>
            <div class="crudo-machine-metric-cell">
                <span class="crudo-machine-label">Kg</span>
                <span class="crudo-machine-metric" data-crudo-kilos>{{ number_format(round((float) $machine['kilos'])) }} kg</span>
            </div>
            <div class="crudo-machine-metric-cell">
                <span class="crudo-machine-label">Saldo</span>
                <span class="crudo-machine-saldo{{ $saldoEsNegativo ? ' crudo-saldo-negativo' : '' }}" data-crudo-saldo>{{ is_numeric($saldoPedidoValue) ? number_format(round((float) $saldoPedidoValue)) : '--' }}</span>
            </div>
        </div>
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
