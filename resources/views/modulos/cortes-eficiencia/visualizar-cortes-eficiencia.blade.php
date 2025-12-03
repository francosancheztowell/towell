@extends('layouts.app')

@section('page-title', 'Visualizar Cortes de Eficiencia')

@section('content')
<div class="w-screen h-full overflow-hidden flex flex-col px-4 py-4 md:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Cortes de Eficiencia de Turno</h2>
            <p class="text-sm text-gray-600 mt-0.5">Folio: <span class="font-medium">{{ $folio }}</span> · Fecha: <span class="font-medium">{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</span></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('cortes.eficiencia.consultar') }}" class="px-3 py-2 rounded-md text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 transition">Regresar</a>
        </div>
    </div>

    <div class="flex-1 bg-white rounded-lg shadow-md overflow-hidden flex flex-col">
        <div class="flex-1 overflow-auto">
            <table class="min-w-full text-sm border-collapse">
                <thead class="bg-blue-600 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 border-r border-blue-500 min-w-[80px] sticky left-0 bg-blue-600 z-20">Telar</th>
                        <th class="px-4 py-3 border-r border-blue-500 text-center" colspan="8">Turno 1</th>
                        <th class="px-4 py-3 border-r border-blue-500 text-center" colspan="8">Turno 2</th>
                        <th class="px-4 py-3 text-center" colspan="8">Turno 3</th>
                    </tr>
                    <tr class="bg-blue-700/90">
                        <th class="px-4 py-2 border-r border-blue-500 sticky left-0 bg-blue-700/90 z-20"></th>
                        @for ($i=0;$i<3;$i++)
                            <th class="px-3 py-2 text-center font-semibold min-w-[85px]">RPM Std</th>
                            <th class="px-3 py-2 text-center font-semibold min-w-[90px]">Efi Std</th>
                            <th class="px-3 py-2 text-center font-semibold min-w-[75px]">RPM R1</th>
                            <th class="px-3 py-2 text-center font-semibold min-w-[85px]">Efi R1</th>
                            <th class="px-3 py-2 text-center font-semibold min-w-[75px]">RPM R2</th>
                            <th class="px-3 py-2 text-center font-semibold min-w-[85px]">Efi R2</th>
                            <th class="px-3 py-2 text-center font-semibold min-w-[75px]">RPM R3</th>
                            <th class="px-3 py-2 text-center font-semibold min-w-[85px]{{ $i < 2 ? ' border-r border-blue-500' : '' }}">Efi R3</th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($datos as $row)
                        @php
                            $t1 = $row['t1'];
                            $t2 = $row['t2'];
                            $t3 = $row['t3'];
                            $val = function($line,$campo){ return $line ? ($line->$campo ?? '') : ''; };
                            $efi = function($line, $campo){ 
                                if(!$line) return ''; 
                                $e=$line->$campo; 
                                if($e===null) return ''; 
                                return number_format($e, 2).'%'; 
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-bold text-gray-900 border-r border-gray-200 sticky left-0 bg-white hover:bg-gray-50 z-10">{{ $row['telar'] }}</td>
                            <!-- Turno 1 -->
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t1,'RpmStd') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t1,'EficienciaSTD') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t1,'RpmR1') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t1,'EficienciaR1') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t1,'RpmR2') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t1,'EficienciaR2') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t1,'RpmR3') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium border-r border-gray-200">{{ $efi($t1,'EficienciaR3') }}</td>
                            <!-- Turno 2 -->
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t2,'RpmStd') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t2,'EficienciaSTD') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t2,'RpmR1') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t2,'EficienciaR1') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t2,'RpmR2') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t2,'EficienciaR2') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t2,'RpmR3') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium border-r border-gray-200">{{ $efi($t2,'EficienciaR3') }}</td>
                            <!-- Turno 3 -->
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t3,'RpmStd') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t3,'EficienciaSTD') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t3,'RpmR1') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t3,'EficienciaR1') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t3,'RpmR2') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t3,'EficienciaR2') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700">{{ $val($t3,'RpmR3') }}</td>
                            <td class="px-3 py-3 text-center text-gray-700 font-medium">{{ $efi($t3,'EficienciaR3') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="25" class="px-6 py-8 text-center text-gray-500 text-base">Sin datos para la fecha seleccionada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
