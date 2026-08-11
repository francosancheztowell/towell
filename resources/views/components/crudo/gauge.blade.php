@props(['value' => 0, 'label' => '', 'tone' => 'good', 'title' => null])

@php
    $pct = max(0, min(100, (float) $value));
    // Largo del semicírculo r=26: π·26. El dasharray recorta ese arco.
    $arc = 81.68;
@endphp

<div class="crudo-gauge" data-tone="{{ $tone }}" role="img" aria-label="{{ $title ?? $label }}: {{ round($pct) }}%">
    <svg viewBox="0 0 64 40" aria-hidden="true">
        <path class="crudo-gauge-track" d="M6 34a26 26 0 0 1 52 0" />
        <path
            class="crudo-gauge-value"
            d="M6 34a26 26 0 0 1 52 0"
            stroke-dasharray="{{ round($arc * $pct / 100, 2) }} {{ $arc }}"
        />
    </svg>
    <strong>{{ round($pct) }}<em>%</em></strong>
    <span>{{ $label }}</span>
</div>
