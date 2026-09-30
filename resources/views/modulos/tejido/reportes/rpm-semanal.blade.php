@extends('layouts.app')

@section('page-title', 'Reporte RPM semanal')

@section('navbar-right')
    <button type="button" data-ui-modal-open="modalRangoTejido"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search" aria-hidden="true"></i> Elegir semana
    </button>
    @if (!empty($lunes) && !empty($domingo))
        <a href="{{ route('tejido.reportes.inv-trama.excel', ['semana' => $lunes]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4">
        @if (session('error'))
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">Reporte RPM semanal</h2>
                @if (!empty($lunes) && !empty($domingo))
                    <span class="text-white text-sm">
                        Semana: {{ \Carbon\Carbon::parse($lunes)->locale('es')->translatedFormat('D j M') }}
                        al {{ \Carbon\Carbon::parse($domingo)->locale('es')->translatedFormat('D j M Y') }}
                    </span>
                @endif
            </div>



            <div class="p-6 overflow-x-auto">
                @if (empty($lunes) || ! isset($filasOrdenTelar) || count($filasOrdenTelar) === 0)
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-week text-6xl text-gray-300 mb-4"></i>
                        <button type="button" data-ui-modal-open="modalRangoTejido"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar semana
                        </button>
                    </div>
                @else

                    <div class="rounded-lg border border-slate-300 overflow-hidden bg-white">
                        <table class="w-full min-w-[640px] border-collapse text-sm tabular-nums">
                            <caption class="sr-only">
                                Reporte RPM semana del {{ \Carbon\Carbon::parse($lunes)->format('Y-m-d') }} al {{ \Carbon\Carbon::parse($domingo)->format('Y-m-d') }}.
                            </caption>
                            <thead>
                                <tr class="bg-slate-200 text-slate-900">
                                    <th scope="col" class="border border-slate-400 px-3 py-2.5 text-left text-xs font-bold tracking-wide">{{ \App\Exports\ReporteRpmSemanalExport::COL_GRUPO }}</th>
                                    <th scope="col" class="border border-slate-400 px-3 py-2.5 text-center text-xs font-bold tracking-wide w-24">{{ \App\Exports\ReporteRpmSemanalExport::COL_TELAR }}</th>
                                    <th scope="col" class="border border-slate-400 px-3 py-2.5 text-right text-xs font-bold tracking-wide">{{ \App\Exports\ReporteRpmSemanalExport::COL_RPM_REAL }}</th>
                                    <th scope="col" class="border border-slate-400 px-3 py-2.5 text-right text-xs font-bold tracking-wide">{{ \App\Exports\ReporteRpmSemanalExport::COL_RPM_IDEAL }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                @php $rowNum = 0; @endphp
                                @foreach ($filasOrdenTelar as $fila)
                                    @php $rowNum++; @endphp
                                    <tr class="hover:bg-blue-50/40 {{ $rowNum % 2 === 0 ? 'bg-slate-50/60' : 'bg-white' }}">
                                        <td class="border border-slate-300 px-3 py-2 text-slate-800">{{ $fila['grupo'] }}</td>
                                        <td class="border border-slate-300 px-3 py-2 text-center font-mono font-medium text-slate-900">{{ $fila['no_telar'] }}</td>
                                        <td class="border border-slate-300 px-3 py-2 text-right">
                                            @if ($fila['rpm_real'] !== null)
                                                {{ number_format($fila['rpm_real'], 0, '.', ',') }}
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="border border-slate-300 px-3 py-2 text-right font-medium text-slate-900">
                                            {{ number_format($fila['rpm_ideal'], 0, '.', ',') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @if (isset($totalGeneral))
                                <tfoot>
                                    <tr class="bg-slate-200 font-bold text-slate-900 border-t-2 border-slate-500">
                                        <td class="border border-slate-400 px-3 py-2.5 text-left">{{ $totalGeneral['grupo'] }}</td>
                                        <td class="border border-slate-400 px-3 py-2.5 text-center text-slate-500">—</td>
                                        <td class="border border-slate-400 px-3 py-2.5 text-right tabular-nums">
                                            @if ($totalGeneral['rpm_real'] !== null)
                                                {{ number_format($totalGeneral['rpm_real'], 0, '.', ',') }}
                                            @else
                                                <span class="text-slate-500 font-normal">—</span>
                                            @endif
                                        </td>
                                        <td class="border border-slate-400 px-3 py-2.5 text-right tabular-nums">
                                            {{ number_format($totalGeneral['rpm_ideal'], 0, '.', ',') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @include('modulos.tejido.reportes.partials.rango-fechas', [
        'modo' => 'semana',
        'ruta' => route('tejido.reportes.inv-trama'),
        'titulo' => 'Semana a consultar',
        'descripcion' => 'Indique una fecha de la semana (se toma de lunes a domingo de esa semana, zona horaria del sistema).',
        'semana' => $semanaParam ?? null,
        'abrirAlCargar' => empty($lunes) || ! isset($filasOrdenTelar) || count($filasOrdenTelar) === 0,
    ])
@endsection

@push('scripts')
    @vite('resources/js/modulos/tejido/reportes/rpm-semanal/index.ts')
@endpush
