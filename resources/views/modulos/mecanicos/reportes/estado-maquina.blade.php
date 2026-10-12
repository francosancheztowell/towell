@extends('layouts.app')

@section('page-title', 'Reporte Estado de Máquina')

@section('navbar-right')
    @if ($reporte)
        <button type="button" data-em-descargar="excel"
            class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Excel
        </button>
        <button type="button" data-em-descargar="pdf"
            class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
        <button type="button" data-em-imagen
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-image"></i> Imagen
        </button>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reporte-estado-maquina"
         data-semanas-url="{{ route('mecanicos.reportes.estado-maquina.semanas') }}"
         data-excel-url="{{ route('mecanicos.reportes.estado-maquina.excel') }}"
         data-pdf-url="{{ route('mecanicos.reportes.estado-maquina.pdf') }}"
         data-imagen-url="{{ route('mecanicos.reportes.estado-maquina.telegram-imagen') }}"
         data-mes="{{ $mes }}"
         data-semana="{{ $semana }}"
         data-nombre-imagen="{{ $reporte ? 'hoja-verificacion-estado-maquina_'.$reporte['desde'].'_'.$reporte['hasta'].'.png' : 'hoja-verificacion-estado-maquina.png' }}">
        @if ($error)
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">{{ $error }}</div>
        @endif

        <form method="GET" action="{{ route('mecanicos.reportes.estado-maquina') }}"
              class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-4">
            <div>
                <label for="mes" class="block text-sm font-semibold text-gray-700 mb-1">Mes</label>
                <input type="month" id="mes" name="mes" value="{{ $mes }}" required
                       class="px-3 py-2 border border-gray-300 rounded-md text-sm">
            </div>
            <div>
                <label for="semana" class="block text-sm font-semibold text-gray-700 mb-1">Semana</label>
                <select id="semana" name="semana" required {{ $mes === '' ? 'disabled' : '' }}
                        class="px-3 py-2 border border-gray-300 rounded-md text-sm min-w-[220px]">
                    <option value="">Seleccione una semana</option>
                    @foreach ($semanas as $opcion)
                        <option value="{{ $opcion['lunes'] }}" @selected($semana === $opcion['lunes'])>
                            {{ $opcion['etiqueta'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" id="btn-consultar"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    {{ $mes === '' || $semana === '' ? 'disabled' : '' }}>
                <i class="fas fa-search mr-1"></i> Consultar
            </button>
        </form>

        @if (! $reporte)
            <div class="bg-white rounded-xl shadow border border-gray-200 px-6 py-16 text-center">
                <i class="fa-solid fa-calendar-week text-gray-300 text-4xl mb-3"></i>
                <p class="text-gray-600 font-medium">Selecciona mes y semana para crear el reporte</p>
            </div>
        @else
            <form id="form-export-estado-maquina" method="POST" class="hidden">
                @csrf
                <input type="hidden" name="mes" value="{{ $reporte['mes'] }}">
                <input type="hidden" name="semana" value="{{ $reporte['lunes'] }}">
                <div id="prioridades-export"></div>
            </form>

            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div id="hoja-verificacion" class="bg-white p-5">
                    <div class="flex flex-col items-start gap-1 mb-5">
                        <img src="{{ asset('images/fondosTowell/logo.png') }}" alt="Towell" class="h-14 mb-1">
                        <h1 class="text-lg font-bold text-gray-900 tracking-wide">HOJA DE VERIFICACIÓN ESTADO MÁQUINA</h1>
                        <p class="text-sm text-gray-600">
                            Periodo: {{ \Carbon\Carbon::parse($reporte['desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($reporte['hasta'])->format('d/m/Y') }}
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="border-collapse text-xs tabular-nums">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="border border-gray-400 bg-gray-100 px-3 py-2 text-center font-bold sticky left-0 z-20 min-w-[180px]">Control</th>
                                    <th rowspan="2" class="border border-gray-400 bg-gray-100 px-2 py-2 text-center font-bold sticky left-[180px] z-20 min-w-[84px]">Prioridad</th>
                                    @foreach ($reporte['salones'] as $salon)
                                        <th colspan="{{ count($salon['telares']) }}"
                                            class="border border-gray-400 px-2 py-2 text-center font-bold whitespace-nowrap"
                                            style="background-color: #{{ $salon['color'] }}">
                                            {{ $salon['nombre'] }}
                                        </th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach ($reporte['salones'] as $salon)
                                        @foreach ($salon['telares'] as $telar)
                                            <th class="border border-gray-400 px-0 py-1 align-bottom"
                                                style="background-color: #{{ $salon['color'] }}"
                                                title="{{ $telar['nombre'] }}">
                                                <div class="em-telar-stack">
                                                    <span class="em-telar-label">Telar</span>
                                                    <span class="em-telar-num">{{ $telar['id'] }}</span>
                                                    <span class="em-calificacion">calificación</span>
                                                </div>
                                            </th>
                                        @endforeach
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reporte['actividades'] as $actividad)
                                    <tr>
                                        <td class="border border-gray-300 bg-white px-3 py-1.5 sticky left-0 z-10 whitespace-nowrap">{{ $actividad['nombre'] }}</td>
                                        <td class="border border-gray-300 bg-white px-1 py-1.5 sticky left-[180px] z-10 text-center">
                                            <input type="number" min="1" max="3" step="1"
                                                   name="prioridad-{{ $actividad['id'] }}"
                                                   data-actividad-id="{{ $actividad['id'] }}"
                                                   value="{{ $actividad['prioridad'] }}"
                                                   class="js-prioridad w-14 mx-auto block border border-gray-300 rounded text-center text-xs py-0.5">
                                        </td>
                                        @foreach ($reporte['salones'] as $salon)
                                            @foreach ($salon['telares'] as $telar)
                                                @php $valor = $actividad['valores'][$telar['id']] ?? 0; @endphp
                                                <td class="border border-gray-300 px-2 py-1.5 text-center font-semibold min-w-[72px]"
                                                    style="{{ $valor === 0 ? 'color:#808080' : 'background-color:#'.(\App\Services\Mecanicos\ReporteEstadoMaquinaService::COLOR_CELDA[$valor] ?? 'FFFFFF') }}">
                                                    {{ $valor }}
                                                </td>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <style>
                .em-telar-stack {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 2px;
                    min-width: 72px;
                    min-height: 132px;
                    padding: 4px 0 6px;
                }
                .em-telar-label {
                    font-weight: 700;
                    line-height: 1.1;
                    text-align: center;
                }
                .em-telar-num {
                    font-weight: 700;
                    font-size: 13px;
                    line-height: 1.1;
                    text-align: center;
                }
                .em-calificacion {
                    writing-mode: vertical-rl;
                    transform: rotate(180deg);
                    font-size: 10px;
                    font-weight: 500;
                    letter-spacing: 0.04em;
                    white-space: nowrap;
                    line-height: 1;
                    margin-top: 4px;
                }
            </style>
        @endif
    </div>

    @vite('resources/js/modulos/mecanicos/reportes/estado-maquina/index.ts')
@endsection
