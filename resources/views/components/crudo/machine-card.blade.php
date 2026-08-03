@props(['machine'])

<button
    type="button"
    class="crudo-machine-card group"
    data-crudo-machine
    data-telar="{{ $machine['telar'] }}"
    data-state="{{ $machine['state'] }}"
    data-signature="{{ $machine['state'] }}:{{ $machine['pieces'] }}:{{ $machine['seconds'] }}:{{ $machine['kilos'] }}"
    aria-label="Abrir detalle del telar {{ $machine['telar'] }}, estado {{ $machine['stateLabel'] }}"
>
    <div>
        <svg class="crudo-loom" viewBox="0 0 200 176" role="img" aria-hidden="true" data-crudo-loom-svg>
            <ellipse class="crudo-loom-floor" cx="100" cy="169" rx="87" ry="5" />

            <g class="crudo-loom-body" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 44h18v59H20zM162 44h18v59h-18z" />
                <path d="M31 35h138v13H31zM38 48h124v47H38z" />
                <path d="M29 91h20v13H29zM151 91h20v13h-20z" />
                <path d="M11 102h178v14H11z" />
                <path d="M20 116h160v45H20z" />
                <path d="M7 162h186v9H7z" />

                <rect x="43" y="14" width="29" height="19" rx="5" />
                <rect x="128" y="14" width="29" height="19" rx="5" />
                <rect x="49" y="8" width="17" height="7" rx="3" />
                <rect x="134" y="8" width="17" height="7" rx="3" />
                <rect x="72" y="17" width="5" height="15" rx="2" />
                <rect x="123" y="17" width="5" height="15" rx="2" />

                <rect x="47" y="78" width="106" height="13" rx="2" />
                <rect x="38" y="123" width="124" height="31" rx="5" />
                <rect x="50" y="142" width="100" height="15" rx="8" />
                <rect x="29" y="128" width="20" height="12" rx="2" />
                <rect x="151" y="128" width="20" height="12" rx="2" />
            </g>

            <g class="crudo-loom-detail" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 44h18v59H20zM162 44h18v59h-18zM31 35h138v13H31zM38 48h124v47H38z" />
                <path d="M29 91h20v13H29zM151 91h20v13h-20zM11 102h178v14H11z" />
                <path d="M20 116h160v45H20zM7 162h186v9H7z" />
                <path d="M43 14h29v19H43zM128 14h29v19h-29zM49 8h17v6H49zM134 8h17v6h-17z" />
                <path d="M47 78h106v13H47zM38 123h124v31H38zM50 142h100v15H50z" />
                <path d="M29 128h20v12H29zM151 128h20v12h-20z" />
                <path d="M43 123l7-8h100l7 8M43 154h-8M165 154h-8" />
            </g>

            <g class="crudo-loom-reed" fill="none" stroke-linecap="round">
                <path d="M54 51v25M62 51v25M70 51v25M78 51v25M86 51v25M94 51v25M102 51v25M110 51v25M118 51v25M126 51v25M134 51v25M142 51v25" />
                <path d="M53 127v25M61 127v25M69 127v25M77 127v25M85 127v25M93 127v25M101 127v25M109 127v25M117 127v25M125 127v25M133 127v25M141 127v25" />
                <path d="M39 134h123M39 141h123M39 148h123" />
            </g>

            <path class="crudo-loom-accent" d="M45 97h110M34 119h132M48 158h104" />

            <g class="crudo-loom-number">
                <rect class="crudo-loom-number-badge" x="60" y="72" width="80" height="37" rx="8" />
                <text class="crudo-loom-number-text" x="100" y="100" text-anchor="middle">
                    {{ $machine['telar'] }}
                </text>
            </g>
        </svg>
    </div>

    <span class="crudo-machine-quality" data-crudo-quality>{{ number_format((float) $machine['qualityPercent']) }}%</span>
    <span class="crudo-machine-metric" data-crudo-kilos>{{ number_format((float) $machine['kilos']) }} kg</span>

    <span class="crudo-machine-tooltip" role="tooltip">
        <strong data-crudo-name>{{ $machine['name'] }}</strong>
        <span data-crudo-tooltip-metrics>{{ number_format((float) $machine['pieces']) }} piezas · {{ number_format((float) $machine['seconds']) }} segundas</span>
    </span>
</button>
