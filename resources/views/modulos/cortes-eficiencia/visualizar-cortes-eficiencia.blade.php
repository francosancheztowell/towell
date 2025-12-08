@extends('layouts.app')

@section('page-title', 'Visualizar Cortes de Eficiencia')

@section('content')
<div class="w-screen h-full overflow-hidden flex flex-col px-4 py-4 md:px-6 lg:px-8">


    <div class="flex-1 bg-white rounded-lg shadow-md overflow-hidden flex flex-col">
        <div class="flex-1 overflow-auto">
            <table class="min-w-full text-sm border-separate border-spacing-0">
                <thead class="bg-gray-100 sticky top-0 z-10">
                    <!-- Fila principal: Fecha y headers de Turnos -->
                    <tr class="border-b-2 border-gray-300">
                        <th rowspan="3" class="px-4 py-3 border border-gray-300 min-w-[80px] sticky left-0 top-0 bg-gray-100 z-50 shadow-md">Fecha</th>
                        <th rowspan="3" class="px-4 py-3 border border-gray-300 min-w-[100px] font-bold">Telar</th>
                        <th rowspan="2" class="px-3 py-2 border border-gray-300 min-w-[90px] font-bold">RPM Std</th>
                        <th rowspan="2" class="px-3 py-2 border border-gray-300 min-w-[90px] font-bold">% EF Std</th>
                        <th colspan="6" class="px-4 py-2 text-center border border-gray-300 bg-blue-50 font-bold">Turno 1</th>
                        <th colspan="6" class="px-4 py-2 text-center border border-gray-300 bg-green-50 font-bold">Turno 2</th>
                        <th colspan="6" class="px-4 py-2 text-center border border-gray-300 bg-yellow-50 font-bold">Turno 3</th>
                    </tr>

                    <!-- Fila de horarios -->
                    <tr class="border-b border-gray-300">
                        @for ($turno = 1; $turno <= 3; $turno++)
                            @php
                                $bgColor = $turno === 1 ? 'bg-blue-50' : ($turno === 2 ? 'bg-green-50' : 'bg-yellow-50');
                            @endphp
                            @for ($horario = 1; $horario <= 3; $horario++)
                                <th colspan="2" class="px-3 py-1 text-center border border-gray-300 {{ $bgColor }} text-xs font-semibold">Horario {{ $horario }}</th>
                            @endfor
                        @endfor
                    </tr>

                    <!-- Fila de columnas RPM / %EF -->
                    <tr class="bg-gray-200 border-b-2 border-gray-300">
                        @for ($turno = 1; $turno <= 3; $turno++)
                            @for ($horario = 1; $horario <= 3; $horario++)
                                <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold min-w-[70px]">RPM</th>
                                <th class="px-2 py-1 text-center border border-gray-300 text-xs font-semibold min-w-[70px]">% EF</th>
                            @endfor
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($datos as $index => $row)
                        @php
                            $t1 = $row['t1'];
                            $t2 = $row['t2'];
                            $t3 = $row['t3'];
                            $val = function($line,$campo){
                                return $line ? ($line->$campo ?? '') : '';
                            };
                            $efi = function($line, $campo){
                                if(!$line) return '';
                                $e=$line->$campo;
                                if($e===null) return '';
                                return number_format($e, 2);
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            @if($index === 0)
                                <td rowspan="{{ count($datos) }}" class="px-4 py-3 text-center border border-gray-300 font-bold text-gray-900 sticky left-0 bg-white z-10">
                                    {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                                </td>
                            @endif
                            <td class="px-4 py-3 font-bold text-gray-900 border border-gray-300 bg-gray-50">{{ $row['telar'] }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700">{{ $val($t1,'RpmStd') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium">{{ $efi($t1,'EficienciaSTD') }}</td>

                            <!-- Turno 1 - 3 Horarios -->
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 bg-blue-50/30">{{ $val($t1,'RpmR1') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium bg-blue-50/30">{{ $efi($t1,'EficienciaR1') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 bg-blue-50/30">{{ $val($t1,'RpmR2') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium bg-blue-50/30">{{ $efi($t1,'EficienciaR2') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 bg-blue-50/30">{{ $val($t1,'RpmR3') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium bg-blue-50/30">{{ $efi($t1,'EficienciaR3') }}</td>

                            <!-- Turno 2 - 3 Horarios -->
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 bg-green-50/30">{{ $val($t2,'RpmR1') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium bg-green-50/30">{{ $efi($t2,'EficienciaR1') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 bg-green-50/30">{{ $val($t2,'RpmR2') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium bg-green-50/30">{{ $efi($t2,'EficienciaR2') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 bg-green-50/30">{{ $val($t2,'RpmR3') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium bg-green-50/30">{{ $efi($t2,'EficienciaR3') }}</td>

                            <!-- Turno 3 - 3 Horarios -->
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 bg-yellow-50/30">{{ $val($t3,'RpmR1') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium bg-yellow-50/30">{{ $efi($t3,'EficienciaR1') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 bg-yellow-50/30">{{ $val($t3,'RpmR2') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium bg-yellow-50/30">{{ $efi($t3,'EficienciaR2') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 bg-yellow-50/30">{{ $val($t3,'RpmR3') }}</td>
                            <td class="px-3 py-2 text-center border border-gray-300 text-gray-700 font-medium bg-yellow-50/30">{{ $efi($t3,'EficienciaR3') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="22" class="px-6 py-8 text-center text-gray-500 text-base border border-gray-300">Sin datos para la fecha seleccionada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
