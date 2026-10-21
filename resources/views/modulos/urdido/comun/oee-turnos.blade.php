{{-- Tabla por turno de reloj del OEE Urdido (OeeUrdidoTurnos): los tres turnos suman $total.
     Recibe $turnos (1..3 => sumas), $total y los formateadores $num / $sinReg de la vista. --}}
<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="w-full whitespace-nowrap text-sm tabular-nums text-slate-700 [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2 [&_.n]:text-right">
        <thead class="bg-slate-100 text-left font-semibold text-slate-700">
            <tr>
                <th>Turno</th>
                <th class="n">Programado</th>
                <th class="n">Producción</th>
                <th class="n">% prod.</th>
                <th class="n">Paro nec.</th>
                <th class="n">Oportunidad</th>
                <th class="n">Sin registrar</th>
                <th class="n">Julios</th>
                <th class="n">Kg</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            @foreach ([...array_map(null, App\Services\Urdido\OeeUrdidoTurnos::NOMBRES, $turnos), ['Total', $total]] as [$nombre, $t])
                <tr @class(['bg-slate-50 font-semibold text-slate-900' => $loop->last])>
                    <td>{{ $nombre }}</td>
                    <td class="n">{{ $num($t['programado']) }}</td>
                    <td class="n">{{ $num($t['minutos']) }}</td>
                    <td class="n">{{ $t['programado'] > 0 ? $num(100 * $t['minutos'] / $t['programado'], 1).' %' : '—' }}</td>
                    <td class="n">{{ $num($t['paro_nec']) }}</td>
                    <td class="n">{{ $num($t['paro_oport']) }}</td>
                    <td class="n">{{ $sinReg($t) }}</td>
                    <td class="n">{{ $num($t['julios']) }}</td>
                    <td class="n">{{ $num($t['kg'], 1) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
