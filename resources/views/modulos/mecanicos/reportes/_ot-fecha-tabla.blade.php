@php
    use App\Services\Mecanicos\ReporteOtFechaService;
@endphp
<table class="min-w-full text-sm border-collapse">
    <thead>
        <tr class="bg-gray-800 text-white text-left">
            <th scope="col" class="px-3 py-2 whitespace-nowrap">No. Empleado</th>
            <th scope="col" class="px-3 py-2 whitespace-nowrap">Nombre</th>
            <th scope="col" class="px-3 py-2 whitespace-nowrap">Fecha</th>
            <th scope="col" class="px-3 py-2 whitespace-nowrap">Folio</th>
            <th scope="col" class="px-3 py-2 whitespace-nowrap">Telar</th>
            <th scope="col" class="px-3 py-2">Falla</th>
            <th scope="col" class="px-3 py-2">Trabajo realizado</th>
            <th scope="col" class="px-3 py-2 whitespace-nowrap">Inicio</th>
            <th scope="col" class="px-3 py-2 whitespace-nowrap">Fin</th>
            <th scope="col" class="px-3 py-2 whitespace-nowrap text-right">Duración</th>
            <th scope="col" class="px-3 py-2 whitespace-nowrap">Estatus</th>
            <th scope="col" class="px-3 py-2 min-w-48">Comentarios</th>
        </tr>
    </thead>
    @foreach ($reporte['mecanicos'] as $grupo)
        <tbody>
            <tr class="bg-blue-50 border-t-2 border-blue-200">
                <th scope="colgroup" colspan="9" class="px-3 py-2 text-left font-semibold text-gray-900">
                    {{ $grupo['nombre'] }} <span class="font-normal text-gray-500">({{ $grupo['cve'] }})</span>
                    <span class="font-normal text-gray-600">· {{ $grupo['total_ots'] }} OT · {{ $grupo['total_trabajos'] }} trabajos</span>
                </th>
                <td class="px-3 py-2 text-right font-semibold text-gray-900 whitespace-nowrap">
                    {{ ReporteOtFechaService::duracion($grupo['total_minutos']) }}
                </td>
                <td colspan="2"></td>
            </tr>
            @foreach ($grupo['renglones'] as $fila)
                <tr class="border-t border-gray-200 align-top">
                    <td class="px-3 py-2 whitespace-nowrap">{{ $grupo['cve'] }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $grupo['nombre'] }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</td>
                    <td class="px-3 py-2 whitespace-nowrap font-medium">{{ $fila['folio'] }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $fila['telar'] }}</td>
                    <td class="px-3 py-2">{{ $fila['falla'] }}</td>
                    <td class="px-3 py-2">{{ $fila['trabajo'] }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $fila['hora_inicio'] }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ $fila['hora_fin'] }}
                        @if ($fila['cruza_dia'])
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-caption" title="Terminó al día siguiente; cuenta en el día en que empezó">+1 día</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-right">{{ ReporteOtFechaService::duracion($fila['minutos']) }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $fila['estatus'] }}</td>
                    <td class="px-3 py-2 text-gray-600">{{ $fila['comentarios'] }}</td>
                </tr>
            @endforeach
        </tbody>
    @endforeach
    <tfoot>
        <tr class="bg-gray-100 border-t-2 border-gray-400 font-semibold">
            <th scope="row" colspan="9" class="px-3 py-2 text-left">
                Total · {{ count($reporte['mecanicos']) }} mecánicos · {{ $reporte['total_ots'] }} OT · {{ $reporte['total_trabajos'] }} trabajos
            </th>
            <td class="px-3 py-2 text-right whitespace-nowrap">{{ ReporteOtFechaService::duracion($reporte['total_minutos']) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>
