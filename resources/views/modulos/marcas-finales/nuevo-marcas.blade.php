@extends('layouts.app')

@php
    use App\Helpers\TurnoHelper;
    $soloLectura = $soloLectura ?? false;
    $folioInicial = $folioInicial ?? request()->query('folio');
    $esModoEdicion = $soloLectura ? true : !empty($folioInicial);
    $tituloPagina = $soloLectura
        ? 'Visualizar Marcas Finales'
        : ($esModoEdicion ? 'Editar Marcas Finales' : 'Nuevas Marcas Finales');
    $turnoActual = TurnoHelper::getTurnoActual();
    $configPagina = [
        'soloLectura' => $soloLectura,
        'folioInicial' => $folioInicial,
        'turnoActual' => $turnoActual,
        'rutas' => [
            'generarFolio' => route('marcas.generar.folio'),
            'store' => route('marcas.store'),
            'std' => route('marcas.datos.std'),
            'show' => route('marcas.show', ['folio' => '__FOLIO__']),
            'editar' => route('marcas.nuevo'),
            'consultar' => route('marcas.consultar'),
        ],
    ];
@endphp

@section('page-title', $tituloPagina)

@section('navbar-right')
@php
    // Permisos del módulo
    $permisosMarcas = userPermissions('Marcas Finales') ?? userPermissions('Nuevas Marcas Finales');
    $puedeCrear     = (bool)($permisosMarcas->crear     ?? false);
    $puedeModificar = (bool)($permisosMarcas->modificar ?? false);
    $puedeEliminar  = (bool)($permisosMarcas->eliminar  ?? false);
    $tieneAcceso    = (bool)($permisosMarcas->acceso    ?? false);
    $puedeEditar    = ($puedeCrear || $puedeModificar) && !$soloLectura;

    // Columnas editables con su "type" para JS
    $colsEditables = [
        ['key' => 'efi',    'label' => '% Efi'],
        ['key' => 'marcas', 'label' => 'Marcas'],
        ['key' => 'horas',  'label' => 'Horas'],
        ['key' => 'trama',  'label' => 'Trama'],
        ['key' => 'pie',    'label' => 'Pie'],
        ['key' => 'rizo',   'label' => 'Rizo'],
        ['key' => 'otros',  'label' => 'Otros'],
    ];
@endphp

<!-- Badge de folio (se muestra cuando exista folio activo) -->
<div id="badge-folio" class="hidden md:flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg shadow-md">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709M15 10a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>
    <span class="text-sm font-semibold">Folio:</span>
    <span id="folio-text" class="text-sm font-bold ml-1">-</span>
</div>

<!-- Modal Fecha/Turno para crear folio -->
<div id="modal-fecha-turno" class="hidden fixed inset-0 z-50 flex items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="modal-fecha-turno-titulo">
    <div class="absolute inset-0 bg-black/40" data-modal-accion="cancelar"></div>
    <div class="relative w-full max-w-md rounded-lg bg-white shadow-lg">
        <div class="px-4 py-3 border-b flex items-center justify-between">
            <h3 id="modal-fecha-turno-titulo" class="text-lg font-semibold text-gray-800">Crear nuevo folio</h3>
            <button type="button" id="modal-fecha-turno-close" data-modal-accion="cancelar" class="text-gray-500 hover:text-gray-700" aria-label="Cerrar">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="p-4 space-y-3">
            <div>
                <label for="input-fecha-folio" class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                <input type="date" id="input-fecha-folio" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="{{ Carbon\Carbon::now()->format('Y-m-d') }}">
            </div>
            <div>
                <label for="select-turno-folio" class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                <select id="select-turno-folio" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="1">Turno 1</option>
                    <option value="2">Turno 2</option>
                    <option value="3">Turno 3</option>
                </select>
            </div>
        </div>
        <div class="px-4 py-3 border-t flex justify-end gap-2">
            <button type="button" id="modal-fecha-turno-cancel" data-modal-accion="cancelar" class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">Cancelar</button>
            <button type="button" id="modal-fecha-turno-ok" data-modal-accion="ok" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Continuar</button>
        </div>
    </div>
</div>
@endsection

@section('content')
{{-- JS: resources/js/modulos/tejido/marcas-finales/nuevo/index.ts --}}
<div id="pagina-marcas-nuevo" class="w-full h-full overflow-hidden flex flex-col px-4 py-4 md:px-8 lg:px-12 xl:px-16 mb-32" data-pagina='@json($configPagina)'>
    <!-- Tabla principal -->
    <div id="segunda-tabla" class="flex flex-col flex-1 bg-white rounded-lg shadow-md overflow-hidden mx-auto w-full max-w-7xl mb-24 md:mb-10">
        <!-- Header fijo (sticky) dentro del contenedor -->
        <div class="bg-blue-600 text-white sticky top-0 z-10">
            <table class="w-full text-sm">
                <colgroup>
                    <col style="width: 9%">
                    <col style="width: 9%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-center uppercase text-xs md:text-sm font-semibold">Telar</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-center uppercase text-xs md:text-sm font-semibold">Salón</th>
                        @foreach($colsEditables as $col)
                            <th class="px-2 md:px-4 py-2 md:py-3 text-center uppercase text-xs md:text-sm font-semibold">
                                {{ $col['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            </table>
        </div>
        <!-- Solo el contenido con scroll -->
        <div class="flex-1 overflow-auto">
            <table class="w-full text-sm mb-64 md:mb-32">
                <colgroup>
                    <col style="width: 9%">
                    <col style="width: 9%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                    <col style="width: 11.71%">
                </colgroup>
                <tbody id="telares-body" class="bg-white divide-y divide-gray-200">
                @foreach(($telares ?? []) as $telar)
                    <tr class="even:bg-gray-50 hover:bg-gray-100 transition-colors duration-150" data-telar="{{ $telar->NoTelarId }}">
                        <!-- Telar -->
                        <td class="px-2 md:px-4 py-2 md:py-3 text-xs md:text-sm font-medium text-gray-900 text-center border-r border-gray-200">
                            {{ $telar->NoTelarId }}
                        </td>

                        <!-- Salón (badge) -->
                        <td class="px-2 md:px-4 py-2 md:py-3 text-center border-r border-gray-200">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                  data-telar="{{ $telar->NoTelarId }}" data-field="salon">
                                {{ $telar->SalonId ?? '-' }}
                            </span>
                        </td>

                        <!-- Celdas editables (DRY) -->
                        @foreach($colsEditables as $col)
                            <td class="px-3 py-3 text-center {{ !$loop->last ? 'border-r border-gray-200' : '' }}">
                                @php
                                    $maxVal = $col['key'] === 'marcas' ? 250 : ($col['key'] === 'horas' ? 9999 : 100);
                                    $isEfi = $col['key'] === 'efi';
                                    $isHoras = $col['key'] === 'horas';
                                @endphp
                                <input type="number" 
                                    class="valor-input w-full max-w-[80px] mx-auto px-2 py-1.5 border border-gray-300 rounded text-sm text-gray-900 text-center focus:ring-2 focus:ring-blue-400 focus:border-blue-400 {{ !$puedeEditar ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white' }}" 
                                    data-telar="{{ $telar->NoTelarId }}"
                                    data-type="{{ $col['key'] }}"
                                    aria-label="{{ $col['label'] }} telar {{ $telar->NoTelarId }}"
                                    value="0"
                                    min="0"
                                    max="{{ $maxVal }}"
                                    step="{{ $isHoras ? 'any' : '1' }}"
                                    placeholder="{{ $isEfi ? '0%' : '0' }}"
                                    {{ !$puedeEditar ? 'readonly' : '' }}>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Estilos mínimos que no se pueden hacer con Tailwind */
table {
    border-collapse: separate;
    border-spacing: 0;
}

thead th {
    position: sticky;
    top: 0;
    z-index: 20;
}

/* Inputs de valor */
.valor-input {
    transition: border-color .2s ease, background-color .2s ease;
    -moz-appearance: textfield; /* Firefox - ocultar spinners */
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

/* Scrollbar personalizado */
.scrollbar-thin {
    scrollbar-width: thin;
}

.scrollbar-thumb-gray-300::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 6px;
}

.scrollbar-track-gray-100::-webkit-scrollbar-track {
    background-color: #f3f4f6;
}

.scrollbar-thin::-webkit-scrollbar {
    height: 6px;
}
</style>

@push('scripts')
    @vite('resources/js/modulos/tejido/marcas-finales/nuevo/index.ts')
@endpush
@endsection
