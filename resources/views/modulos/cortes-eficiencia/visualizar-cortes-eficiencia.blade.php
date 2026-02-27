@extends('layouts.app')

@section('page-title', 'Visualizar Cortes de Eficiencia')

@section('content')

@php
    $turnoColors = [
        1 => ['bg' => 'bg-blue-600',   'text' => 'text-white'],
        2 => ['bg' => 'bg-green-600',  'text' => 'text-white'],
        3 => ['bg' => 'bg-amber-500',  'text' => 'text-white'],
    ];
    $horarioColors = [
        1 => [
            'header' => 'bg-blue-400   text-white',
            'cols'   => 'bg-blue-300   text-white',
            'cell'   => 'bg-blue-50/40',
            'icon'   => 'text-blue-500',
        ],
        2 => [
            'header' => 'bg-green-400  text-white',
            'cols'   => 'bg-green-300  text-white',
            'cell'   => 'bg-green-50/40',
            'icon'   => 'text-green-500',
        ],
        3 => [
            'header' => 'bg-yellow-400 text-white',
            'cols'   => 'bg-yellow-300 text-white',
            'cell'   => 'bg-yellow-50/40',
            'icon'   => 'text-yellow-500',
        ],
    ];
    $folioTurnos = [
        1 => $foliosPorTurno['1'] ?? null,
        2 => $foliosPorTurno['2'] ?? null,
        3 => $foliosPorTurno['3'] ?? null,
    ];
    $badgeColors = [
        1 => 'bg-blue-50   text-blue-800  border-blue-200',
        2 => 'bg-green-50  text-green-800 border-green-200',
        3 => 'bg-yellow-50 text-yellow-900 border-yellow-200',
    ];

    // Helpers reutilizables
    $val = fn($line, $campo) => $line ? ($line->$campo ?? '') : '';
    $efi = function ($line, $campo) {
        if (!$line) return '';
        $e = $line->$campo ?? null;
        if ($e === null || $e === '') return '';
        return number_format((float) $e, 2);
    };
    $obsData = fn($line, $campoStatus, $campoText) => [
        'status' => $line ? (bool) ($line->$campoStatus ?? false) : false,
        'text'   => $line ? trim($line->$campoText ?? '') : '',
    ];
@endphp

<div class="w-screen h-full overflow-hidden flex flex-col px-4 py-4 md:px-6 lg:px-8">

    {{-- ── Título y botones ── --}}
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-800">
            Cortes de Eficiencia &mdash; {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
        </h2>
        <div class="flex gap-2">
            <button onclick="exportarCortesExcel('{{ $fecha }}')"
                    class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md transition-colors">
                <i class="fa fa-file-excel mr-2"></i> Exportar Excel
            </button>
            <button onclick="descargarCortesPDF('{{ $fecha }}')"
                    class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md transition-colors">
                <i class="fa fa-file-pdf mr-2"></i> Descargar PDF
            </button>
        </div>
    </div>

    {{-- ── Badges de folio por turno ── --}}
    <div class="flex flex-wrap gap-3 text-sm text-gray-700 mb-4">
        @foreach($folioTurnos as $turno => $folio)
            <span class="px-3 py-1 rounded-full border {{ $badgeColors[$turno] }}">
                <span class="font-semibold">Turno {{ $turno }}:</span>
                <span>{{ $folio ?? 'Sin folio registrado' }}</span>
            </span>
        @endforeach
    </div>

    {{-- ── Tabla ── --}}
    <div class="flex-1 bg-white rounded-lg shadow-md overflow-hidden flex flex-col">
        <div class="flex-1 overflow-auto">
            <table class="min-w-full text-sm" style="border-collapse:separate;border-spacing:0;">
                <thead>

                    {{-- ── Fila 1: cabeceras fijas + Turno N ── --}}
                    <tr>
                        <th rowspan="3"
                            class="px-4 py-3 border border-gray-300 min-w-[80px]
                                   sticky left-0 top-0 bg-gray-100 z-50 shadow-md text-gray-800">
                            Fecha
                        </th>
                        <th rowspan="3"
                            class="px-4 py-3 border border-gray-300 min-w-[80px]
                                   sticky top-0 z-30 bg-gray-100 font-bold text-gray-800">
                            Telar
                        </th>
                        <th rowspan="3"
                            class="px-3 py-2 border border-gray-300 min-w-[80px]
                                   sticky top-0 z-30 bg-gray-100 font-bold text-gray-800">
                            STD
                        </th>
                        <th rowspan="3"
                            class="px-3 py-2 border border-gray-300 min-w-[90px]
                                   sticky top-0 z-30 bg-gray-100 font-bold text-gray-800">
                            % EF Std
                        </th>

                        @for ($turno = 1; $turno <= 3; $turno++)
                            {{-- 7 cols: H1(3) + H2(2) + H3(2) --}}
                            <th colspan="7"
                                class="px-4 py-2 text-center border border-gray-300
                                       sticky top-0 z-30
                                       {{ $turnoColors[$turno]['bg'] }} {{ $turnoColors[$turno]['text'] }}
                                       font-bold tracking-wide">
                                Turno {{ $turno }}
                            </th>
                        @endfor
                    </tr>

                    {{-- ── Fila 2: Horario 1 / 2 / 3 (dentro de cada turno) ── --}}
                    <tr>
                        @for ($turno = 1; $turno <= 3; $turno++)
                            <th colspan="3"
                                class="px-3 py-1 text-center border border-gray-300
                                       sticky top-0 z-20
                                       {{ $horarioColors[1]['header'] }} text-xs font-semibold">
                                Horario 1
                            </th>
                            <th colspan="2"
                                class="px-3 py-1 text-center border border-gray-300
                                       sticky top-0 z-20
                                       {{ $horarioColors[2]['header'] }} text-xs font-semibold">
                                Horario 2
                            </th>
                            <th colspan="2"
                                class="px-3 py-1 text-center border border-gray-300
                                       sticky top-0 z-20
                                       {{ $horarioColors[3]['header'] }} text-xs font-semibold">
                                Horario 3
                            </th>
                        @endfor
                    </tr>

                    {{-- ── Fila 3: nombres de columna ── --}}
                    <tr>
                        @for ($turno = 1; $turno <= 3; $turno++)
                            {{-- Horario 1: RPM · % EF · Obs --}}
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[1]['cols'] }} min-w-[70px]">RPM</th>
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[1]['cols'] }} min-w-[70px]">% EF</th>
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[1]['cols'] }} min-w-[120px]">Obs</th>
                            {{-- Horario 2: % EF · Obs --}}
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[2]['cols'] }} min-w-[70px]">% EF</th>
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[2]['cols'] }} min-w-[120px]">Obs</th>
                            {{-- Horario 3: % EF · Obs --}}
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[3]['cols'] }} min-w-[70px]">% EF</th>
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[3]['cols'] }} min-w-[120px]">Obs</th>
                        @endfor
                    </tr>

                </thead>

                <tbody class="divide-y divide-gray-200">
                    @forelse ($datos as $index => $row)
                        @php
                            $t1 = $row['t1'];
                            $t2 = $row['t2'];
                            $t3 = $row['t3'];
                        @endphp
                        <tr class="hover:bg-gray-50">

                            {{-- Fecha (rowspan, sticky) --}}
                            @if ($index === 0)
                                <td rowspan="{{ count($datos) }}"
                                    class="px-4 py-3 text-center border border-gray-300
                                           font-bold text-gray-900 sticky left-0 bg-white z-10">
                                    {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                                </td>
                            @endif

                            {{-- Telar --}}
                            <td class="px-4 py-3 font-bold text-gray-900 border border-gray-300 bg-gray-50 whitespace-nowrap">
                                {{ $row['telar'] }}
                            </td>

                            {{-- STD (tomado del turno 1) --}}
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700">
                                {{ $val($t1, 'RpmStd') }}
                            </td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium">
                                {{ $efi($t1, 'EficienciaSTD') }}
                            </td>

                            {{-- ── 3 turnos ── --}}
                            @foreach ([1 => $t1, 2 => $t2, 3 => $t3] as $tNum => $tx)
                                @php
                                    $o1 = $obsData($tx, 'StatusOB1', 'ObsR1');
                                    $o2 = $obsData($tx, 'StatusOB2', 'ObsR2');
                                    $o3 = $obsData($tx, 'StatusOB3', 'ObsR3');
                                @endphp

                                {{-- ── Horario 1: RPM · % EF · Obs ── --}}
                                <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 {{ $horarioColors[1]['cell'] }}">
                                    {{ $val($tx, 'RpmR1') }}
                                </td>
                                <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium {{ $horarioColors[1]['cell'] }}">
                                    {{ $efi($tx, 'EficienciaR1') }}
                                </td>
                                <td class="px-2 py-2 border border-gray-300 {{ $horarioColors[1]['cell'] }}">
                                    @if ($o1['status'] || $o1['text'] !== '')
                                        <span class="inline-flex items-center gap-1 text-xs text-gray-700"
                                              title="{{ $o1['text'] }}">
                                            <svg class="w-3 h-3 flex-shrink-0 {{ $horarioColors[1]['icon'] }}"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            @if ($o1['text'] !== '')
                                                <span class="truncate max-w-[100px]">{{ $o1['text'] }}</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>

                                {{-- ── Horario 2: % EF · Obs ── --}}
                                <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium {{ $horarioColors[2]['cell'] }}">
                                    {{ $efi($tx, 'EficienciaR2') }}
                                </td>
                                <td class="px-2 py-2 border border-gray-300 {{ $horarioColors[2]['cell'] }}">
                                    @if ($o2['status'] || $o2['text'] !== '')
                                        <span class="inline-flex items-center gap-1 text-xs text-gray-700"
                                              title="{{ $o2['text'] }}">
                                            <svg class="w-3 h-3 flex-shrink-0 {{ $horarioColors[2]['icon'] }}"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            @if ($o2['text'] !== '')
                                                <span class="truncate max-w-[100px]">{{ $o2['text'] }}</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>

                                {{-- ── Horario 3: % EF · Obs ── --}}
                                <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium {{ $horarioColors[3]['cell'] }}">
                                    {{ $efi($tx, 'EficienciaR3') }}
                                </td>
                                <td class="px-2 py-2 border border-gray-300 {{ $horarioColors[3]['cell'] }}">
                                    @if ($o3['status'] || $o3['text'] !== '')
                                        <span class="inline-flex items-center gap-1 text-xs text-gray-700"
                                              title="{{ $o3['text'] }}">
                                            <svg class="w-3 h-3 flex-shrink-0 {{ $horarioColors[3]['icon'] }}"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            @if ($o3['text'] !== '')
                                                <span class="truncate max-w-[100px]">{{ $o3['text'] }}</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                            @endforeach

                        </tr>
                    @empty
                        <tr>
                            <td colspan="25"
                                class="px-6 py-8 text-center text-gray-500 text-base border border-gray-300">
                                Sin datos para la fecha seleccionada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function exportarCortesExcel(fecha) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("cortes.eficiencia.visualizar.excel") }}';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';

        const fechaInput = document.createElement('input');
        fechaInput.type  = 'hidden';
        fechaInput.name  = 'fecha';
        fechaInput.value = fecha;

        form.appendChild(csrf);
        form.appendChild(fechaInput);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    async function descargarCortesPDF(fecha) {
        try {
            const response = await fetch('{{ route("cortes.eficiencia.visualizar.pdf") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/pdf'
                },
                body: new URLSearchParams({ fecha })
            });

            if (!response.ok) {
                const text = await response.text();
                console.error('Error al descargar PDF:', response.status, text);
                alert('No se pudo generar el PDF.');
                return;
            }

            const blob    = await response.blob();
            const blobUrl = window.URL.createObjectURL(blob);
            const enlace  = document.createElement('a');
            enlace.href     = blobUrl;
            enlace.download = `cortes_eficiencia_${fecha}.pdf`;
            document.body.appendChild(enlace);
            enlace.click();
            enlace.remove();
            window.URL.revokeObjectURL(blobUrl);
        } catch (error) {
            console.error('Excepción al descargar PDF:', error);
            alert('Ocurrió un error al intentar descargar el PDF.');
        }
    }
</script>
@endpush

@endsection
