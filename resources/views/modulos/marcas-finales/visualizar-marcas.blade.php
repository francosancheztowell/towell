@extends('layouts.app')

@section('page-title', 'Visualizar Marcas Finales')

@section('content')
<div class="w-screen h-full overflow-hidden flex flex-col px-4 py-4 md:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Marcas Finales de Turno</h2>
            <p class="text-sm text-gray-600 mt-0.5">Folio: <span class="font-medium">{{ $folio }}</span> · Fecha: <span class="font-medium">{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</span></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('marcas.consultar') }}" class="px-3 py-2 rounded-md text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 transition">Regresar</a>
        </div>
    </div>

    <div class="flex-1 bg-white rounded-lg shadow-md overflow-hidden flex flex-col">
        <div class="bg-blue-600 text-white sticky top-0 z-10">
            <table class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-3 py-2 border-r border-blue-500 w-16">Telar</th>
                        <th class="px-3 py-2 border-r border-blue-500 text-center" colspan="6">Turno 1</th>
                        <th class="px-3 py-2 border-r border-blue-500 text-center" colspan="6">Turno 2</th>
                        <th class="px-3 py-2 text-center" colspan="6">Turno 3</th>
                    </tr>
                    <tr class="bg-blue-700/90">
                        <th class="px-3 py-1 border-r border-blue-500"></th>
                        @for ($i=0;$i<3;$i++)
                            <th class="px-2 py-1 text-center font-semibold">% Ef</th>
                            <th class="px-2 py-1 text-center font-semibold">Marcas</th>
                            <th class="px-2 py-1 text-center font-semibold">TRAMA</th>
                            <th class="px-2 py-1 text-center font-semibold">PIE</th>
                            <th class="px-2 py-1 text-center font-semibold">RIZO</th>
                            <th class="px-2 py-1 text-center font-semibold border-r border-blue-500">OTROS</th>
                        @endfor
                    </tr>
                </thead>
            </table>
        </div>
        <div class="flex-1 overflow-auto">
            <table class="w-full text-xs">
                <tbody class="divide-y divide-gray-100">
                    @forelse ($datos as $row)
                        @php
                            $t1 = $row['t1'];
                            $t2 = $row['t2'];
                            $t3 = $row['t3'];
                            $val = function($line,$campo){ return $line ? ($line->$campo ?? '') : ''; };
                            $efi = function($line){ if(!$line) return ''; $e=$line->Eficiencia; if($e===null) return ''; return intval($e).'%'; };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-semibold text-gray-800 border-r border-gray-200">{{ $row['telar'] }}</td>
                            <!-- Turno 1 -->
                            <td class="px-2 py-1 text-center">{{ $efi($t1) }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t1,'Marcas') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t1,'Trama') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t1,'Pie') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t1,'Rizo') }}</td>
                            <td class="px-2 py-1 text-center border-r border-gray-200">{{ $val($t1,'Otros') }}</td>
                            <!-- Turno 2 -->
                            <td class="px-2 py-1 text-center">{{ $efi($t2) }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t2,'Marcas') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t2,'Trama') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t2,'Pie') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t2,'Rizo') }}</td>
                            <td class="px-2 py-1 text-center border-r border-gray-200">{{ $val($t2,'Otros') }}</td>
                            <!-- Turno 3 -->
                            <td class="px-2 py-1 text-center">{{ $efi($t3) }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t3,'Marcas') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t3,'Trama') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t3,'Pie') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t3,'Rizo') }}</td>
                            <td class="px-2 py-1 text-center">{{ $val($t3,'Otros') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="19" class="px-4 py-6 text-center text-gray-500 text-sm">Sin datos para la fecha seleccionada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
