{{-- Tabla por turno de reloj del OEE Urdido (OeeUrdidoTurnos): los tres turnos suman $total.
     Recibe $turnos (1..3 => sumas), $total, los formateadores $num / $sinReg y la flecha $chev de la vista.
     Mismo orden de columnas que el resumen, los renglones de máquina y los julios. Producción = min. producción + paro
     necesario; .desglose (las dos partes) solo se ve con el botón Desglose. --}}
<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="w-full whitespace-nowrap text-sm tabular-nums text-slate-700 [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2 [&_.n]:text-right">
        <thead class="bg-slate-100 text-left font-semibold text-slate-700">
            <tr>
                <th>Turno</th>
                <th class="n">Kg</th>
                <th class="n">Julios</th>
                <th class="n">Programado</th>
                <th class="n cursor-pointer" data-desglose-toggle title="Min. producción + paro necesario">{!! $chev !!}Producción</th>
                <th class="n desglose">Min prod.</th>
                <th class="n desglose">Paro nec.</th>
                <th class="n">% prod.</th>
                <th class="n">Paros</th>
                <th class="n">Sin registrar</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            @foreach ([...array_map(null, App\Services\Urdido\OeeUrdidoTurnos::NOMBRES, $turnos), ['Total', $total]] as [$nombre, $t])
                <tr @class(['bg-slate-50 font-semibold text-slate-900' => $loop->last])>
                    <td>{{ $nombre }}</td>
                    <td class="n">{{ $num($t['kg'], 1) }}</td>
                    <td class="n">{{ $num($t['julios']) }}</td>
                    <td class="n">{{ $num($t['programado']) }}</td>
                    <td class="n">{{ $num($t['minutos'] + $t['paro_nec']) }}</td>
                    <td class="n desglose">{{ $num($t['minutos']) }}</td>
                    <td class="n desglose">{{ $num($t['paro_nec']) }}</td>
                    <td class="n">{{ $t['programado'] > 0 ? $num(100 * ($t['minutos'] + $t['paro_nec']) / $t['programado'], 1).' %' : '—' }}</td>
                    <td class="n">{{ $num($t['paro_oport']) }}</td>
                    <td class="n">{{ $sinReg($t) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
