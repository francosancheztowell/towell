@php
    $C = \App\Services\Mecanicos\ReporteOtDiariasService::class;
    $verde = '#'.$C::COLOR_VERDE;
    $oro = '#'.$C::COLOR_ORO;
    $teal = '#'.$C::COLOR_TEAL;
    $magenta = '#'.$C::COLOR_MAGENTA;
    $salmon = '#'.$C::COLOR_SALMON;
    $editable = $editable ?? false;
@endphp
<table class="ot-matriz border-collapse text-[10px] tabular-nums">
    <thead>
        <tr>
            <th class="border border-gray-500 px-2 py-1 font-bold text-white" style="background-color: {{ $verde }}">{{ $reporte['etiqueta_semana'] }}</th>
            @foreach ($reporte['dias'] as $dia)
                <th colspan="3" class="border border-gray-500 px-2 py-1 font-bold whitespace-nowrap" style="background-color: {{ $oro }}">{{ $dia['etiqueta'] }}</th>
            @endforeach
            <th colspan="2" class="border border-gray-500 px-2 py-1 font-bold whitespace-nowrap" style="background-color: {{ $oro }}">TOTAL SEMANA EN TURNO</th>
            <th colspan="2" class="border border-gray-500 px-2 py-1 font-bold text-white whitespace-nowrap" style="background-color: {{ $teal }}">DE TRAMA</th>
            <th colspan="2" class="border border-gray-500 px-2 py-1 font-bold whitespace-nowrap" style="background-color: {{ $oro }}">GENERAL</th>
            <th class="border border-gray-500 px-2 py-1 font-bold text-white whitespace-nowrap" style="background-color: {{ $magenta }}">MIN. SEMANA</th>
            <th class="border border-gray-500 px-2 py-1 font-bold text-white" style="background-color: {{ $magenta }}">%</th>
            <th class="border border-gray-500 px-2 py-1 font-bold whitespace-nowrap" style="background-color: {{ $salmon }}">OCUPACIÓN %</th>
            <th class="border border-gray-500 px-2 py-1 font-bold whitespace-nowrap" style="background-color: {{ $salmon }}">% CUMPLIMIENTO</th>
            <th class="border border-gray-500 px-2 py-1 font-bold whitespace-nowrap" style="background-color: {{ $salmon }}">% OT FINAL</th>
        </tr>
        <tr>
            <th class="border border-gray-500 px-2 py-1 font-bold text-white sticky left-0 z-10" style="background-color: {{ $verde }}">MECÁNICOS</th>
            @foreach ($reporte['dias'] as $dia)
                <th class="border border-gray-500 px-1 py-1 font-bold text-white whitespace-nowrap" style="background-color: {{ $verde }}">REALIZADAS</th>
                <th class="border border-gray-500 px-1 py-1 font-bold text-white whitespace-nowrap" style="background-color: {{ $verde }}">FIRMADAS</th>
                <th class="border border-gray-500 px-1 py-1 font-bold text-white whitespace-nowrap" style="background-color: {{ $verde }}">OCUPACIÓN MIN.</th>
            @endforeach
            <th class="border border-gray-500 px-1 py-1 font-bold whitespace-nowrap" style="background-color: {{ $oro }}">REALIZADAS</th>
            <th class="border border-gray-500 px-1 py-1 font-bold whitespace-nowrap" style="background-color: {{ $oro }}">FIRMADAS</th>
            <th class="border border-gray-500 px-1 py-1 font-bold text-white whitespace-nowrap" style="background-color: {{ $teal }}">OT TRAMA</th>
            <th class="border border-gray-500 px-1 py-1 font-bold text-white whitespace-nowrap" style="background-color: {{ $teal }}">CUMPLIDAS TRAMA</th>
            <th class="border border-gray-500 px-1 py-1 font-bold whitespace-nowrap" style="background-color: {{ $oro }}">TOTAL REALIZADAS</th>
            <th class="border border-gray-500 px-1 py-1 font-bold whitespace-nowrap" style="background-color: {{ $oro }}">TOTAL CUMPLIDAS</th>
            <th class="border border-gray-500 px-1 py-1 font-bold text-white" style="background-color: {{ $magenta }}">&nbsp;</th>
            <th class="border border-gray-500 px-1 py-1 font-bold text-white" style="background-color: {{ $magenta }}">&nbsp;</th>
            <th class="border border-gray-500 px-1 py-1" style="background-color: {{ $salmon }}">&nbsp;</th>
            <th class="border border-gray-500 px-1 py-1" style="background-color: {{ $salmon }}">&nbsp;</th>
            <th class="border border-gray-500 px-1 py-1" style="background-color: {{ $salmon }}">&nbsp;</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($reporte['mecanicos'] as $mecanico)
            <tr>
                <td class="border border-gray-400 bg-white px-2 py-1 text-left font-bold whitespace-nowrap sticky left-0 z-10">{{ $mecanico['nombre'] }}</td>
                @foreach ($reporte['dias'] as $dia)
                    @php $celda = $mecanico['dias'][$dia['fecha']]; @endphp
                    <td class="border border-gray-400 bg-white px-1 py-1 text-center">{{ number_format($celda['realizadas'], 1, '.', ',') }}</td>
                    <td class="border border-gray-400 bg-white px-1 py-1 text-center">{{ number_format($celda['firmadas'], 1, '.', ',') }}</td>
                    <td class="border border-gray-400 bg-white px-1 py-1 text-center">{{ number_format($celda['ocupacion'], 1, '.', ',') }}</td>
                @endforeach
                <td class="border border-gray-400 bg-white px-1 py-1 text-center">{{ number_format($mecanico['realizadas_semana'], 1, '.', ',') }}</td>
                <td class="border border-gray-400 bg-white px-1 py-1 text-center">{{ number_format($mecanico['firmadas_semana'], 1, '.', ',') }}</td>
                <td class="border border-gray-400 bg-white px-1 py-0.5 text-center">
                    @if ($editable)
                        <input type="number" step="0.1" min="0"
                               class="js-ot-input w-16 border border-gray-300 rounded text-center text-[10px] py-0.5"
                               data-cve="{{ $mecanico['cve'] }}" data-campo="ot_trama"
                               value="{{ number_format($mecanico['ot_trama'], 1, '.', '') }}">
                    @else
                        {{ number_format($mecanico['ot_trama'], 1, '.', ',') }}
                    @endif
                </td>
                <td class="border border-gray-400 bg-white px-1 py-0.5 text-center">
                    @if ($editable)
                        <input type="number" step="0.1" min="0"
                               class="js-ot-input w-16 border border-gray-300 rounded text-center text-[10px] py-0.5"
                               data-cve="{{ $mecanico['cve'] }}" data-campo="cumplidas_trama"
                               value="{{ number_format($mecanico['cumplidas_trama'], 1, '.', '') }}">
                    @else
                        {{ number_format($mecanico['cumplidas_trama'], 1, '.', ',') }}
                    @endif
                </td>
                <td class="border border-gray-400 bg-white px-1 py-1 text-center js-total-realizadas" data-cve="{{ $mecanico['cve'] }}">{{ number_format($mecanico['total_realizadas'], 1, '.', ',') }}</td>
                <td class="border border-gray-400 bg-white px-1 py-1 text-center js-total-cumplidas" data-cve="{{ $mecanico['cve'] }}">{{ number_format($mecanico['total_cumplidas'], 1, '.', ',') }}</td>
                <td class="border border-gray-400 bg-white px-1 py-1 text-center">{{ number_format($mecanico['min_semana'], 1, '.', ',') }}</td>
                <td class="border border-gray-400 bg-white px-1 py-1 text-center">{{ number_format($mecanico['pct_capacidad'], 2, '.', ',') }}</td>
                <td class="border border-gray-400 bg-white px-1 py-0.5 text-center">
                    @if ($editable)
                        <input type="number" step="0.1" min="0"
                               class="js-ot-input w-16 border border-gray-300 rounded text-center text-[10px] py-0.5"
                               data-cve="{{ $mecanico['cve'] }}" data-campo="ocupacion_pct"
                               value="{{ number_format($mecanico['ocupacion_pct'], 1, '.', '') }}">
                    @else
                        {{ number_format($mecanico['ocupacion_pct'], 1, '.', ',') }}
                    @endif
                </td>
                <td class="border border-gray-400 bg-white px-1 py-1 text-center js-pct-cumplimiento" data-cve="{{ $mecanico['cve'] }}">{{ number_format($mecanico['pct_cumplimiento'], 1, '.', ',') }}</td>
                <td class="border border-gray-400 bg-white px-1 py-1 text-center js-pct-ot-final" data-cve="{{ $mecanico['cve'] }}">{{ number_format($mecanico['pct_ot_final'], 1, '.', ',') }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="border border-gray-400 bg-gray-50 px-2 py-1 sticky left-0 z-10"></td>
            @foreach ($reporte['dias'] as $dia)
                <td class="border border-gray-400 bg-gray-50"></td>
                <td class="border border-gray-400 bg-gray-50"></td>
                <td class="border border-gray-400 bg-gray-50"></td>
            @endforeach
            <td class="border border-gray-400 bg-gray-50"></td>
            <td class="border border-gray-400 bg-gray-50"></td>
            <td class="border border-gray-400 bg-gray-50 px-1 py-1 text-center font-bold js-pie-ot-trama">{{ number_format($reporte['pie']['ot_trama'], 1, '.', ',') }}</td>
            <td class="border border-gray-400 bg-gray-50 px-1 py-1 text-center font-bold js-pie-cumplidas-trama">{{ number_format($reporte['pie']['cumplidas_trama'], 1, '.', ',') }}</td>
            <td class="border border-gray-400 bg-gray-50"></td>
            <td class="border border-gray-400 bg-gray-50"></td>
            <td class="border border-gray-400 bg-gray-50"></td>
            <td class="border border-gray-400 bg-gray-50"></td>
            <td class="border border-gray-400 bg-gray-50"></td>
            <td class="border border-gray-400 bg-gray-50"></td>
            <td class="border border-gray-400 bg-gray-50 px-1 py-1 text-center font-bold js-pie-ot-final">{{ number_format($reporte['pie']['pct_ot_final'], 1, '.', ',') }}</td>
        </tr>
    </tbody>
</table>
