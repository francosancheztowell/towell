@extends('layouts.app')

@php
    $soloLectura = $soloLectura ?? false;
    $folioInicial = $folioInicial ?? request()->query('folio');
    $tituloPagina = $soloLectura ? 'Visualizar Corte de Eficiencia' : 'Cortes de Eficiencia';
    // JS: resources/js/modulos/tejido/cortes-eficiencia/captura/index.ts (19-02).
    $configPagina = [
        'soloLectura' => $soloLectura,
        'folioInicial' => $folioInicial,
        'aviso' => session('warning'),
        'rutas' => [
            'store' => route('cortes.eficiencia.store'),
            'consultar' => route('cortes.eficiencia.consultar'),
            'notificarTelegram' => route('cortes.eficiencia.visualizar.telegram'),
            'turnoInfo' => route('cortes.eficiencia.turno.info'),
            'datosPrograma' => route('cortes.eficiencia.datos.programa.tejido'),
            'datosTelares' => route('cortes.eficiencia.datos.telares'),
            'fallas' => route('cortes.eficiencia.fallas'),
            'generarFolio' => route('cortes.eficiencia.generar.folio'),
            'guardarHora' => route('cortes.eficiencia.guardar.hora'),
            'corte' => route('cortes.eficiencia.show', ['id' => '__FOLIO__']),
            'pdf' => route('cortes.eficiencia.pdf', ['id' => '__FOLIO__']),
            'finalizar' => route('cortes.eficiencia.finalizar', ['id' => '__FOLIO__']),
        ],
    ];
@endphp

@section('page-title', $tituloPagina)

{{-- Sin botones superiores ni barra de datos --}}
@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-report id="btn-capturar-imagen" title="Descargar Imagen" icon="fa-image"
            iconColor="text-indigo-600" hoverBg="hover:bg-indigo-100" class="text-sm" />

        <x-navbar.button-report id="btn-telegram-folio" title="Notificar por Telegram" icon="fa-paper-plane"
            iconColor="text-sky-600" hoverBg="hover:bg-sky-100" class="text-sm" module="Cortes de Eficiencia"
            :disabled="$soloLectura" />

        <x-navbar.button-report id="btn-finalizar-folio" title="Finalizar" icon="fa-check" iconColor="text-orange-600"
            hoverBg="hover:bg-orange-100" class="text-sm" module="Cortes de Eficiencia" :disabled="$soloLectura" />

        <!-- Badge del folio actual -->
        <div id="badge-folio" class="hidden items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg shadow-md">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709M15 10a3 3 0 11-6 0 3 3 0 016 0z">
                </path>
            </svg>
            <span class="text-sm font-semibold">Folio:</span>
            <span id="folio-text" class="text-sm font-bold">--</span>
        </div>
    </div>
@endsection

@section('content')
    @php
        // Paleta por horario (evita duplicación de clases)
        $horarios = [
            1 => ['title' => 'HORARIO 1', 'shade' => 'bg-blue-400', 'hover' => 'hover:bg-blue-500', 'check' => 'text-blue-600', 'cellHover' => 'hover:bg-blue-100'],
            2 => ['title' => 'HORARIO 2', 'shade' => 'bg-green-400', 'hover' => 'hover:bg-green-500', 'check' => 'text-green-600', 'cellHover' => 'hover:bg-green-100'],
            3 => ['title' => 'HORARIO 3', 'shade' => 'bg-yellow-400', 'hover' => 'hover:bg-yellow-500', 'check' => 'text-yellow-600', 'cellHover' => 'hover:bg-yellow-100'],
        ];
    @endphp

    @if(session('warning'))
        <div class="container mx-auto px-4 pt-4">
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded" role="alert">
                <p class="font-bold">Atención</p>
                <p>{{ session('warning') }}</p>
            </div>
        </div>
    @endif

    <div id="pagina-cortes" class="container mx-auto px-4 py-6 mb-32" data-pagina='@json($configPagina)'>
        <!-- Tabla principal, sin encabezado de folio/turno -->
        <div id="tabla-cortes" class="bg-white shadow overflow-hidden">
            <div class="overflow-x-auto">
                <div class="overflow-y-auto max-h-[80vh]">
                    <table class="min-w-full divide-y divide-gray-200 mb-24">
                        <thead>
                            <tr class="text-white">
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider sticky top-0 z-30 bg-blue-500 min-w-[80px]">
                                    TELAR</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider sticky top-0 z-30 bg-blue-500 min-w-[100px]">
                                    STD</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider sticky top-0 z-30 bg-blue-500 min-w-[120px]">
                                    % EF STD</th>
                                @foreach($horarios as $h => $c)
                                    <th colspan="3"
                                        class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider sticky top-0 z-30 {{ $c['shade'] }}">
                                        <div class="flex items-center justify-center gap-2">
                                            <span>{{ $c['title'] }}</span>
                                            <button type="button"
                                                class="p-1 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-white {{ $c['hover'] }} text-white"
                                                data-accion="tomar-hora" data-horario="{{ $h }}"
                                                title="Tomar hora Horario {{ $h }}" aria-label="Tomar hora del horario {{ $h }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                            <span id="hora-horario-{{ $h }}" class="text-xs opacity-75">--:--</span>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                            <tr>
                                <th class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 bg-blue-500"></th>
                                <th class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 bg-blue-500"></th>
                                <th class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 bg-blue-500"></th>
                                @foreach($horarios as $h => $c)
                                    <th
                                        class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 {{ $c['shade'] }} min-w-[100px]">
                                        RPM</th>
                                    <th
                                        class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 {{ $c['shade'] }} min-w-[100px]">
                                        % EF</th>
                                    <th
                                        class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 {{ $c['shade'] }} min-w-[80px]">
                                        Obs</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody id="telares-body" class="bg-white divide-y divide-gray-100">
                            @foreach(($telares ?? []) as $telar)
                                <tr class="hover:bg-blue-50">
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap">{{ $telar }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                        <input type="text"
                                            class="w-full px-2 py-1 border border-gray-200 rounded text-sm bg-gray-100 text-gray-600 text-center cursor-not-allowed"
                                            placeholder="Cargando..." data-telar="{{ $telar }}" data-field="rpm_std" readonly
                                            aria-label="RPM estándar telar {{ $telar }}">
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                        <input type="text"
                                            class="w-full px-2 py-1 border border-gray-200 rounded text-sm bg-gray-100 text-gray-600 text-center cursor-not-allowed"
                                            placeholder="Cargando..." data-telar="{{ $telar }}" data-field="eficiencia_std"
                                            readonly aria-label="% eficiencia estándar telar {{ $telar }}">
                                    </td>
                                    @foreach($horarios as $h => $c)
                                        <!-- RPM -->
                                        <td class="border border-gray-300 px-1 py-2">
                                            <input type="number"
                                                class="valor-input rpm-input w-full px-2 py-1 border border-gray-200 rounded text-sm text-gray-900 text-center focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
                                                data-telar="{{ $telar }}" data-horario="{{ $h }}" data-type="rpm" value="0" min="0"
                                                max="{{ in_array($telar, [401, 402]) ? 650 : 500 }}" placeholder="0"
                                                aria-label="RPM telar {{ $telar }} horario {{ $h }}">
                                        </td>
                                        <!-- EF -->
                                        <td class="border border-gray-300 px-1 py-2">
                                            <input type="number"
                                                class="valor-input ef-input w-full px-2 py-1 border border-gray-200 rounded text-sm text-gray-900 text-center focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
                                                data-telar="{{ $telar }}" data-horario="{{ $h }}" data-type="eficiencia" value="0"
                                                min="0" max="100" placeholder="0%"
                                                aria-label="% eficiencia telar {{ $telar }} horario {{ $h }}">
                                        </td>
                                        <!-- Obs -->
                                        <td class="border border-gray-300 px-0 py-2 w-10 text-center">
                                            <input type="checkbox"
                                                class="obs-checkbox w-3 h-3 {{ $c['check'] }} bg-gray-100 border-gray-300 rounded focus:ring-offset-0 focus:ring-0"
                                                data-telar="{{ $telar }}" data-horario="{{ $h }}"
                                                aria-label="Observación telar {{ $telar }} horario {{ $h }}">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    <x-ui.modal-base id="modal-observaciones" title="Observaciones" size="md">
        <div class="text-left mb-4">
            <p class="text-sm text-gray-600 mb-2">Telar: <strong id="obs-telar"></strong> | Horario: <strong id="obs-horario"></strong></p>
            <div id="obs-falla-campo">
                <label for="obs-falla" class="block text-xs text-gray-700 mb-1">Falla (Clave)</label>
                <select id="obs-falla" class="w-full p-2 border border-gray-300 rounded mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Seleccione una clave --</option>
                </select>
            </div>
        </div>
        <label for="obs-texto" class="sr-only">Observaciones</label>
        <textarea id="obs-texto" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
            rows="4" maxlength="100" placeholder="Escriba sus observaciones aquí..."></textarea>
        <p id="obs-contador" class="text-xs text-gray-500 text-right mt-1" aria-live="polite"></p>
        <x-slot:footer>
            <button type="button" data-ui-modal-close-target="modal-observaciones" id="obs-cancelar"
                class="min-h-touch px-4 py-2 rounded-md bg-gray-500 text-white hover:bg-gray-600">Cancelar</button>
            <button type="button" id="obs-guardar"
                class="min-h-touch px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Guardar</button>
        </x-slot:footer>
    </x-ui.modal-base>

    {{-- Aviso flotante del autoguardado (antes lo creaba el JS con innerHTML). --}}
    <div id="notificacion-guardado" role="status" aria-live="polite"
        class="fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 transition-opacity duration-300 z-50 opacity-0 pointer-events-none">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <span data-texto></span>
    </div>
    <div id="notificacion-error-guardado" role="alert"
        class="fixed bottom-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 transition-opacity duration-300 z-50 opacity-0 pointer-events-none">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        <span data-texto></span>
    </div>

    <style>
        /* Tabla y sticky headers */
        table {
            border-collapse: separate;
            border-spacing: 0;
        }

        thead th {
            position: sticky;
            top: 0;
            z-index: 30;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .08);
        }

        tbody tr:hover {
            background-color: #eff6ff !important;
        }

        /* Inputs STD y valor */
        tbody input {
            transition: border-color .2s ease, background-color .2s ease;
        }

        tbody input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px #3b82f6;
            outline: none;
        }

        /* Inputs de valor (RPM y Eficiencia) */
        .valor-input {
            min-width: 60px;
            max-width: 80px;
            -moz-appearance: textfield;
            /* Firefox - ocultar spinners */
        }

        .valor-input::-webkit-outer-spin-button,
        .valor-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .valor-input:focus {
            background-color: #eff6ff;
        }

        .valor-input.bg-green-100 {
            background-color: #dcfce7 !important;
        }

        /* Scrollbars */
        .overflow-x-auto::-webkit-scrollbar,
        .overflow-y-auto::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .overflow-x-auto::-webkit-scrollbar-track,
        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .overflow-x-auto::-webkit-scrollbar-thumb,
        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .overflow-x-auto::-webkit-scrollbar-thumb:hover,
        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>

@endsection

@push('scripts')
    @vite('resources/js/modulos/tejido/cortes-eficiencia/captura/index.ts')
@endpush
