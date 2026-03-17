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
        ],
        2 => [
            'header' => 'bg-green-400  text-white',
            'cols'   => 'bg-green-300  text-white',
            'cell'   => 'bg-green-50/40',
        ],
        3 => [
            'header' => 'bg-yellow-400 text-white',
            'cols'   => 'bg-yellow-300 text-white',
            'cell'   => 'bg-yellow-50/40',  
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
    $horariosTurno = [];
    foreach ([1, 2, 3] as $turno) {
        $horariosTurno[$turno] = [
            1 => $horariosPorTurno[(string) $turno][1] ?? '--:--',
            2 => $horariosPorTurno[(string) $turno][2] ?? '--:--',
            3 => $horariosPorTurno[(string) $turno][3] ?? '--:--',
        ];
    }

    // Helpers reutilizables
    $val = fn($line, $campo) => $line ? ($line->$campo ?? '') : '';
    $efi = function ($line, $campo) {
        if (!$line) return '';
        $e = $line->$campo ?? null;
        if ($e === null || $e === '') return '';
        return (string) round((float) $e);
    };
    $obsData = fn($line, $campoStatus, $campoText) => [
        'status' => $line ? (bool) ($line->$campoStatus ?? false) : false,
        'text'   => $line ? trim($line->$campoText ?? '') : '',
    ];

    // Última RPM real (no-cero) de un turno específico: Horario 3>2>1
    $lastRpmTurno = function ($line) {
        if (!$line) return '';
        foreach (['RpmR3', 'RpmR2', 'RpmR1'] as $campo) {
            $v = $line->$campo ?? null;
            if ($v !== null && $v !== '' && (float) $v != 0) return (int) $v;
        }
        return '';
    };
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
                        <button onclick="notificarTelegram('{{ $fecha }}')" id="btn-telegram"
                    class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-100 text-sky-600 text-sm font-medium rounded-md transition-colors border border-sky-200">
                <i class="fa-brands fa-telegram mr-2 text-blue-600 text-lg"></i> Notificar Telegram
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
                            class="px-4 py-3 border border-gray-300 min-w-[90px]
                                   sticky top-0 z-30 bg-gray-100 font-bold text-gray-800">
                            Telar
                        </th>

                        @for ($turno = 1; $turno <= 3; $turno++)
                            <th colspan="4"
                                class="px-4 py-2 text-center border border-gray-300
                                       sticky top-0 z-30
                                       {{ $turnoColors[$turno]['bg'] }} {{ $turnoColors[$turno]['text'] }}
                                       font-bold tracking-wide">
                                Turno {{ $turno }}
                            </th>
                        @endfor
                    </tr>

                    {{-- ── Fila 2: RPM + Horarios (HR reales del folio por turno) ── --}}
                    <tr>
                        @for ($turno = 1; $turno <= 3; $turno++)
                            <th rowspan="2"
                                class="px-2 py-1 text-center border border-gray-300
                                       sticky top-0 z-20
                                       {{ $horarioColors[1]['header'] }} text-xs font-semibold min-w-[52px]">
                                RPM
                            </th>
                            <th
                                class="px-3 py-1 text-center border border-gray-300
                                       sticky top-0 z-20
                                       {{ $horarioColors[1]['header'] }} text-xs font-semibold">
                                Horario: {{ $horariosTurno[$turno][1] }}
                            </th>
                            <th
                                class="px-3 py-1 text-center border border-gray-300
                                       sticky top-0 z-20
                                       {{ $horarioColors[2]['header'] }} text-xs font-semibold">
                                Horario: {{ $horariosTurno[$turno][2] }}
                            </th>
                            <th
                                class="px-3 py-1 text-center border border-gray-300
                                       sticky top-0 z-20
                                       {{ $horarioColors[3]['header'] }} text-xs font-semibold">
                                Horario: {{ $horariosTurno[$turno][3] }}
                            </th>
                        @endfor
                    </tr>

                    {{-- ── Fila 3: nombres de columna (EF x3) ── --}}
                    <tr>
                        @for ($turno = 1; $turno <= 3; $turno++)
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[1]['cols'] }} min-w-[56px]">EF</th>
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[2]['cols'] }} min-w-[56px]">EF</th>
                            <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold
                                       sticky top-0 z-10 {{ $horarioColors[3]['cols'] }} min-w-[56px]">EF</th>
                        @endfor
                    </tr>

                </thead>

                <tbody class="divide-y divide-gray-200">
                    @forelse ($datos as $row)
                        @php
                            $t1 = $row['t1'];
                            $t2 = $row['t2'];
                            $t3 = $row['t3'];
                        @endphp
                        <tr class="hover:bg-gray-50">

                            {{-- Telar --}}
                            <td class="px-4 py-3 font-bold text-gray-900 border border-gray-300 bg-gray-50 whitespace-nowrap">
                                {{ $row['telar'] }}
                            </td>

                            {{-- ── 3 turnos ── --}}
                            @foreach ([1 => $t1, 2 => $t2, 3 => $t3] as $tx)
                                @php
                                    $o1 = $obsData($tx, 'StatusOB1', 'ObsR1');
                                    $o2 = $obsData($tx, 'StatusOB2', 'ObsR2');
                                    $o3 = $obsData($tx, 'StatusOB3', 'ObsR3');
                                @endphp

                                {{-- ── RPM (misma lógica actual: última no-cero de H3>H2>H1) ── --}}
                                <td class="px-2 py-1.5 text-center align-middle border border-gray-300 text-gray-700 {{ $horarioColors[1]['cell'] }}">
                                    {{ $lastRpmTurno($tx) }}
                                </td>

                                {{-- ── EF por horario (1,2,3) ── --}}
                                <td class="px-2 py-1.5 text-center align-middle border border-gray-300 text-gray-700 font-medium {{ $horarioColors[1]['cell'] }}">
                                    <div class="flex flex-col items-center justify-center leading-tight text-center">
                                        <div class="text-base font-semibold">{{ $efi($tx, 'EficienciaR1') }}</div>
                                        @if ($o1['status'] || $o1['text'] !== '')
                                            <div class="mt-0.5 text-[10px] leading-tight text-gray-700 text-center whitespace-normal break-words max-w-[64px]">
                                                {{ $o1['text'] }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-2 py-1.5 text-center align-middle border border-gray-300 text-gray-700 font-medium {{ $horarioColors[2]['cell'] }}">
                                    <div class="flex flex-col items-center justify-center leading-tight text-center">
                                        <div class="text-base font-semibold">{{ $efi($tx, 'EficienciaR2') }}</div>
                                        @if ($o2['status'] || $o2['text'] !== '')
                                            <div class="mt-0.5 text-[10px] leading-tight text-gray-700 text-center whitespace-normal break-words max-w-[64px]">
                                                {{ $o2['text'] }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-2 py-1.5 text-center align-middle border border-gray-300 text-gray-700 font-medium {{ $horarioColors[3]['cell'] }}">
                                    <div class="flex flex-col items-center justify-center leading-tight text-center">
                                        <div class="text-base font-semibold">{{ $efi($tx, 'EficienciaR3') }}</div>
                                        @if ($o3['status'] || $o3['text'] !== '')
                                            <div class="mt-0.5 text-[10px] leading-tight text-gray-700 text-center whitespace-normal break-words max-w-[64px]">
                                                {{ $o3['text'] }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            @endforeach

                        </tr>
                    @empty
                        <tr>
                            <td colspan="13"
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

    async function notificarTelegram(fecha) {
        const btn = document.getElementById('btn-telegram');
        const originalHtml = btn.innerHTML;
        try {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Enviando...';

            const response = await fetch('{{ route("cortes.eficiencia.visualizar.telegram") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams({ fecha })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                console.error('Error al notificar por Telegram:', data);
                alert(data.message || 'No se pudo enviar la notificación por Telegram.');
                return;
            }

            alert('Reporte enviado por Telegram exitosamente.');
        } catch (error) {
            console.error('Excepción al notificar por Telegram:', error);
            alert('Ocurrió un error al intentar enviar la notificación por Telegram.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
</script>
@endpush

@endsection
