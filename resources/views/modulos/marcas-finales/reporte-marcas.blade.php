@extends('layouts.app')

@section('page-title', 'Reporte Marcas Finales')

@section('content')
@php
    use Carbon\Carbon;
    // Indexar por turno para acceso directo
    $porTurno = collect($tablas)->keyBy('turno');
    // Unificar lista de telares (de la secuencia cargada en cada tabla)
    $telares = collect([]);
    foreach ($tablas as $t) { $telares = $telares->merge($t['telares']); }
    $telares = $telares->unique()->sort()->values();
    $fmtEfi = function($linea){
        if(!$linea) return '';
        $e = $linea->Eficiencia ?? $linea->EficienciaSTD ?? $linea->EficienciaStd ?? null;
        if($e === null || $e === '') return '';
        if(is_numeric($e) && $e <= 1) $e = $e * 100; // fracción a %
        return intval(round($e)).'%';
    };
    $get = function($turno, $telar) use ($porTurno){
        return optional(optional($porTurno->get($turno))['lineas'])->get($telar);
    };
@endphp

<div class="w-screen h-full overflow-hidden flex flex-col px-4 py-4 md:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Marcas Finales de Turno</h2>
            <p class="text-sm text-gray-600 mt-0.5">Fecha: <span class="font-medium">{{ Carbon::parse($fecha)->format('d/m/Y') }}</span></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('marcas.consultar') }}" class="px-3 py-2 rounded-md text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 transition">Regresar</a>
        </div>
    </div>

    <div class="flex-1 bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-auto h-full">
            <table class="w-full text-xs border-collapse">
                <thead class="bg-blue-600 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2 border-r border-blue-500 text-center align-middle" rowspan="2">Telar</th>
                        <th class="px-3 py-2 border-r border-blue-500 text-center align-middle" colspan="6">Turno 1</th>
                        <th class="px-3 py-2 border-r border-blue-500 text-center align-middle" colspan="6">Turno 2</th>
                        <th class="px-3 py-2 text-center align-middle" colspan="6">Turno 3</th>
                    </tr>
                    <tr class="bg-blue-700/90">
                        @for ($i=0;$i<3;$i++)
                            <th class="px-2 py-1 text-center align-middle font-semibold">% Ef</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold">Marcas</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold">TRAMA</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold">PIE</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold">RIZO</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold {{ $i < 2 ? 'border-r border-blue-500' : '' }}">OTROS</th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($telares as $telar)
                        @php
                            $t1 = $get(1, $telar);
                            $t2 = $get(2, $telar);
                            $t3 = $get(3, $telar);
                            $val = function($l,$c){ return $l ? ($l->$c ?? '') : ''; };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-semibold text-gray-800 border-r border-gray-200 text-center align-middle">{{ $telar }}</td>
                            <!-- Turno 1 -->
                            <td class="px-2 py-2 text-center align-middle">{{ $fmtEfi($t1) }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t1,'Marcas') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t1,'Trama') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t1,'Pie') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t1,'Rizo') }}</td>
                            <td class="px-2 py-2 text-center align-middle border-r border-gray-200">{{ $val($t1,'Otros') }}</td>
                            <!-- Turno 2 -->
                            <td class="px-2 py-2 text-center align-middle">{{ $fmtEfi($t2) }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t2,'Marcas') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t2,'Trama') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t2,'Pie') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t2,'Rizo') }}</td>
                            <td class="px-2 py-2 text-center align-middle border-r border-gray-200">{{ $val($t2,'Otros') }}</td>
                            <!-- Turno 3 -->
                            <td class="px-2 py-2 text-center align-middle">{{ $fmtEfi($t3) }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Marcas') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Trama') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Pie') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Rizo') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Otros') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="19" class="px-4 py-6 text-center text-gray-500 text-sm">Sin datos para la fecha seleccionada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 bg-gray-50 border-t text-xs text-gray-500">
            <span>Folio Turno 1: {{ optional($porTurno->get(1))['folio'] ?? '—' }}</span>
            <span class="mx-3">Folio Turno 2: {{ optional($porTurno->get(2))['folio'] ?? '—' }}</span>
            <span>Folio Turno 3: {{ optional($porTurno->get(3))['folio'] ?? '—' }}</span>
        </div>
    </div>
</div>
@endsection