{{--
  Checklist BPM de un folio (19-01): una vista para Urdido y Engomado. La incluyen
  modulos/urdido/Urdido-BPM-Line/index y modulos/engomado/Engomado-BPM-Line/index.
  @param string $variante  'urdido' | 'engomado'
  Datos del controller: $header, $actividades, $lineas (Actividad => Valor), $nombreMaquina, $esSupervisor.
  JS: resources/js/modulos/urdido/comun/bpm-line/index.ts
--}}
@php
    $cfgVariante = [
        'urdido' => [
            'titulo' => 'Checklist BPM Urdido',
            'modulo' => 'BPM (Buenas Practicas Manufactura) Urd',
            'rutas' => 'urd-bpm-line',
            'indice' => 'urd-bpm.index',
            'colAutoriza' => 'NombreEmplAutoriza',
        ],
        'engomado' => [
            'titulo' => 'Checklist BPM Engomado',
            'modulo' => 'BPM (Buenas Practicas Manufactura) Eng',
            'rutas' => 'eng-bpm-line',
            'indice' => 'eng-bpm.index',
            'colAutoriza' => 'NomEmplAutoriza',
        ],
    ][$variante];

    $configLinea = [
        'variante' => $variante,
        'rutas' => [
            'toggle' => route($cfgVariante['rutas'].'.toggle', $header->Folio),
            'indice' => route($cfgVariante['indice']),
        ],
    ];
    $iconos = [0 => '○', 1 => '✓', 2 => '✗'];
    $etiquetas = [0 => 'sin marcar', 1 => 'cumple', 2 => 'no cumple'];
    $clasesValor = [
        0 => 'bg-gray-50 border-gray-300 text-gray-400 hover:bg-gray-100 hover:border-gray-400',
        1 => 'bg-green-100 border-green-400 text-green-700 hover:bg-green-200',
        2 => 'bg-red-100 border-red-400 text-red-700 hover:bg-red-200',
    ];
@endphp
@extends('layouts.app')

@section('page-title', $cfgVariante['titulo'].' - '.$header->Folio)

@section('navbar-right')
    <div class="flex items-center gap-2">
        @if($header->Status === 'Creado')
            <form action="{{ route($cfgVariante['rutas'].'.terminar', $header->Folio) }}" method="POST" class="inline" id="form-terminar">
                @csrf
                @method('PATCH')
                <x-navbar.button-report
                    data-bpm-line-accion="terminar"
                    :checkPermission="false"
                    title="{{ !empty($esSupervisor) ? 'Terminar y Autorizar' : 'Terminado' }}"
                    text="{{ !empty($esSupervisor) ? 'Terminar y Autorizar' : 'Terminado' }}"
                    icon="fa-check"
                    bg="bg-green-600"
                    :module="$cfgVariante['modulo']"
                    iconColor="text-white"
                    class="text-white hover:bg-green-700"
                    />
            </form>
        @elseif($header->Status === 'Terminado')
            <form action="{{ route($cfgVariante['rutas'].'.autorizar', $header->Folio) }}" method="POST" class="inline" id="form-autorizar">
                @csrf
                @method('PATCH')
                <x-navbar.button-report
                    data-bpm-line-accion="autorizar"
                    title="Autorizar"
                    text="Autorizar"
                    :module="$cfgVariante['modulo']"
                    icon="fa-check-double"
                    bg="bg-blue-600"
                    iconColor="text-white"
                    class="text-white hover:bg-blue-700"
                    />
            </form>
            <form action="{{ route($cfgVariante['rutas'].'.rechazar', $header->Folio) }}" method="POST" class="inline" id="form-rechazar">
                @csrf
                @method('PATCH')
                <x-navbar.button-report
                    data-bpm-line-accion="rechazar"
                    title="Rechazar"
                    :module="$cfgVariante['modulo']"
                    text="Rechazar"
                    icon="fa-times"
                    bg="bg-red-600"
                    iconColor="text-white"
                    class="text-white hover:bg-red-700"
                    />
            </form>
        @endif
    </div>
@endsection

@section('content')
<div id="bpm-line-pagina" data-bpm-line='@json($configLinea)'>
    @if(!empty($esSupervisor) && $header->Status === 'Creado')
        <div class="max-w-6xl mx-auto mt-3 mb-2 px-4">
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                Al terminar este checklist como supervisor, el folio se autoriza automáticamente.
            </div>
        </div>
    @endif

    <!-- Información del Header (una sola línea) -->
    <div class="bg-white rounded-lg shadow-sm border p-3 md:p-4 mb-2 mt-2 max-w-6xl mx-auto overflow-x-auto">
        <div class="flex items-center gap-4 md:gap-6 justify-between divide-x divide-gray-200 whitespace-nowrap text-sm px-2">
            <div class="flex items-baseline gap-1 px-4">
                <span class="text-sm text-gray-500 font-medium uppercase">Fecha:</span>
                <span class="text-base font-semibold">{{ $header->Fecha ? $header->Fecha->format('d/m/Y H:i') : '' }}</span>
            </div>
            <div class="flex items-baseline gap-1 px-4">
                <span class="text-sm text-gray-500 font-medium uppercase">Recibe:</span>
                <span class="text-base font-semibold">{{ $header->NombreEmplRec }}</span>
                <span class="text-sm text-gray-600">(Turno {{ $header->TurnoRecibe }})</span>
            </div>
            <div class="flex items-baseline gap-1 px-4">
                <span class="text-sm text-gray-500 font-medium uppercase">Entrega:</span>
                <span class="text-base font-semibold">{{ $header->NombreEmplEnt }}</span>
                <span class="text-sm text-gray-600">(Turno {{ $header->TurnoEntrega }})</span>
            </div>
            <div class="flex items-baseline gap-1 px-4">
                <span class="text-sm text-gray-500 font-medium uppercase">Autoriza:</span>
                <span class="text-base font-semibold">{{ $header->{$cfgVariante['colAutoriza']} }}</span>
            </div>
        </div>
    </div>

    <!-- Checklist de Actividades -->
    <div class="bg-white rounded-lg shadow-sm border p-2 mx-60 mb-32">
        <h2 class="text-base font-bold text-gray-800 mb-2 border-b pb-1.5 px-2">Actividades</h2>

        <div class="overflow-y-auto" style="max-height: calc(100vh - 280px);">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-gray-100 border-b">
                    <tr>
                        <th class="text-left px-2 py-2 font-semibold text-gray-700 w-12">Orden</th>
                        <th class="text-left px-2 py-2 font-semibold text-gray-700">Actividad</th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-700 w-32">{{ $nombreMaquina }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actividades as $actividad)
                        @php
                            $valor = (int) $lineas->get($actividad->Actividad, 0);
                            $valor = in_array($valor, [1, 2], true) ? $valor : 0;
                        @endphp
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-2 py-1.5 text-center text-gray-600 font-medium">{{ $actividad->Orden }}</td>
                            <td class="px-2 py-1.5 text-base font-medium">{{ $actividad->Actividad }}</td>
                            <td class="px-2 py-1.5 text-center">
                                <button type="button"
                                    class="cell-btn inline-flex items-center justify-center w-9 h-9 rounded-lg border-2 transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-300 {{ $clasesValor[$valor] }}"
                                    data-actividad="{{ $actividad->Actividad }}"
                                    data-valor="{{ $valor }}"
                                    aria-label="{{ $actividad->Actividad }}: {{ $etiquetas[$valor] }}"
                                    @disabled($header->Status !== 'Creado')>
                                    <span class="cell-icon text-lg font-bold" aria-hidden="true">{{ $iconos[$valor] }}</span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/modulos/urdido/comun/bpm-line/index.ts')
@endpush
