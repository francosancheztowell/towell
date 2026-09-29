@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Cortes de Eficiencia')

@php
    use Carbon\Carbon;

    // JS: resources/js/modulos/tejido/cortes-eficiencia/consultar/index.ts (19-02).
    $configPagina = [
        'ultimoFolio' => isset($ultimoFolio) ? $ultimoFolio->Folio : null,
        'esSupervisor' => (bool) ($esSupervisor ?? false),
        'rutas' => [
            'corte' => route('cortes.eficiencia.show', ['id' => '__FOLIO__']),
            'editar' => route('cortes.eficiencia').'?folio=',
            'finalizar' => route('cortes.eficiencia.finalizar', ['id' => '__FOLIO__']),
            'visualizarFolio' => route('cortes.eficiencia.visualizar.folio', ['folio' => '__FOLIO__']),
            'visualizar' => route('cortes.eficiencia.visualizar', ['folio' => '__FOLIO__']),
            'actualizarRegistro' => route('cortes.eficiencia.actualizar.registro', ['id' => '__FOLIO__']),
            'generarFolio' => route('cortes.eficiencia.generar.folio'),
        ],
    ];
@endphp

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create
      id="btn-nuevo"
      title="Nuevo"
      module="Cortes de Eficiencia" />

    <x-navbar.button-edit
      id="btn-editar"
      title="Editar"
      module="Cortes de Eficiencia"
    />
    <x-navbar.button-report
      id="btn-visualizar"
      title="Visualizar"
      icon="fa-eye"
      iconColor="text-gray-700"
      hoverBg="hover:bg-gray-100"
      class="text-sm"
      module="Cortes de Eficiencia"
    />
    <x-navbar.button-report
      id="btn-finalizar"
      title="Finalizar"
      icon="fa-check"
      iconColor="text-orange-600"
      hoverBg="hover:bg-orange-100"
      class="text-sm"
      module="Cortes de Eficiencia"
    />
    <x-navbar.button-create
      id="btn-fechas"
      title="Fechas"
      icon="fa-calendar"
      text=""
      bg=""
      iconColor="text-indigo-600"
      hoverBg="hover:bg-indigo-100"
      class="text-sm"
      module="Cortes de Eficiencia" />

    @if($esSupervisor ?? false)
    <x-navbar.button-report
      id="btn-editar-supervisor"
      title="Editar (Supervisor)"
      icon="fa-unlock"
      iconColor="text-red-600"
      hoverBg="hover:bg-red-100"
      class="text-sm"
      :disabled="true"
      module="Cortes de Eficiencia" />
    @endif
</div>
@endsection

@section('content')
<div id="pagina-consultar" class="w-full max-w-7xl mx-auto h-full overflow-hidden flex flex-col px-4 md:px-8 lg:px-12 xl:px-16 py-4" data-pagina='@json($configPagina)'>
    <div class="flex flex-col flex-1 bg-white rounded-lg shadow-md overflow-hidden">
    @if(isset($cortes) && $cortes->count() > 0)
        <!-- Tabla con header fijo -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <table class="w-full text-sm border-collapse">
                <colgroup>
                    <col style="width: 20%">
                    <col style="width: 20%">
                    <col style="width: 15%">
                    <col style="width: 25%">
                    <col style="width: 20%">
                </colgroup>
                <thead class="bg-blue-500 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Folio</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Fecha</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Turno</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Empleado</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-left uppercase text-xs md:text-sm font-semibold">Status</th>
                    </tr>
                </thead>
            </table>
            <div class="flex-1 overflow-auto">
                <table class="w-full text-sm border-collapse">
                    <colgroup>
                        <col style="width: 20%">
                        <col style="width: 20%">
                        <col style="width: 15%">
                        <col style="width: 25%">
                        <col style="width: 20%">
                    </colgroup>
                    <tbody id="cortes-body" class="divide-y divide-gray-100">
                        @foreach($cortes as $corte)
                        <tr class="hover:bg-blue-600 hover:text-white cursor-pointer transition-colors corte-row {{ isset($ultimoFolio) && $ultimoFolio->Folio == $corte->Folio ? 'bg-blue-100 border-l-4 border-blue-600' : '' }}"
                            id="row-{{ $corte->Folio }}"
                            data-folio="{{ $corte->Folio }}"
                            data-fecha="{{ $corte->Date ? Carbon::parse($corte->Date)->format('Y-m-d') : '' }}">
                            <td class="px-2 md:px-4 py-2 md:py-3 font-semibold text-gray-900 text-sm md:text-base truncate">{{ $corte->Folio }}</td>
                            <td class="px-2 md:px-4 py-2 md:py-3 text-gray-900 text-sm md:text-base truncate">
                                @if($corte->Date)
                                    {{ Carbon::parse($corte->Date)->format('d/m/Y') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-2 md:px-4 py-2 md:py-3 text-gray-900 text-sm md:text-base truncate">{{ $corte->Turno }}</td>
                            <td class="px-2 md:px-4 py-2 md:py-3 text-gray-900 text-sm md:text-base truncate">{{ $corte->numero_empleado ?? 'N/A' }}</td>
                            <td class="px-2 md:px-4 py-2 md:py-3">
                                @if($corte->Status === 'Finalizado')
                                    <span class="status-badge-finalizado px-2 md:px-3 py-1 md:py-1.5 rounded-full text-xs md:text-sm font-semibold bg-green-100 text-green-700">Finalizado</span>
                                @elseif($corte->Status === 'En Proceso')
                                    <span class="status-badge-proceso px-2 md:px-3 py-1 md:py-1.5 rounded-full text-xs md:text-sm font-semibold bg-blue-100 text-blue-700">En Proceso</span>
                                @else
                                    <span class="status-badge-otro px-2 md:px-3 py-1 md:py-1.5 rounded-full text-xs md:text-sm font-semibold bg-yellow-100 text-yellow-700">{{ $corte->Status }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
            <!-- Sin Registros -->
        <div class="flex flex-col items-center justify-center flex-1 p-8 text-center">
            <h2 class="text-2xl font-semibold text-gray-700 mb-3">No hay cortes de eficiencia registrados</h2>
            <p class="text-gray-500 text-lg mb-6">Toca "Nuevo" para crear el primer registro.</p>
        <x-navbar.button-create
                id="btn-nuevo-empty"
                title="Nuevo"
                module="Cortes de Eficiencia"
                :disabled="false"
                icon="fa-plus"
                iconColor="text-green-600"
                hoverBg="hover:bg-green-100" />
        </div>
    @endif
    </div>
</div>

    @php
        // Preparar fechas únicas de los folios para el modal
        $fechasUnicas = (isset($cortes) && $cortes->count() > 0)
            ? $cortes->pluck('Date')
                ->filter()
                ->map(function ($d) { try { return Carbon::parse($d)->format('Y-m-d'); } catch (\Exception $e) { return null; } })
                ->filter()
                ->unique()
                ->sort()
            : collect();
    @endphp

    {{-- Modal Fechas --}}
    <x-ui.modal-base id="modal-fechas" title="Seleccionar Fecha" size="md" :close-on-backdrop="true">
        <div class="space-y-4">
            <div>
                <label for="input-fecha-inicio" class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                <input
                    id="input-fecha-inicio"
                    type="date"
                    class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    value="{{ Carbon::now()->format('Y-m-d') }}"
                />
            </div>
        </div>
        <x-slot:footer>
            <button type="button" id="modal-fechas-ok" class="min-h-touch px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Visualizar</button>
        </x-slot:footer>
    </x-ui.modal-base>

    {{-- Modal Editar Registro (Supervisor) --}}
    @if($esSupervisor ?? false)
    <x-ui.modal-base id="modal-editar-registro" title="Editar Registro" size="md" tone="danger" :close-on-backdrop="true">
        <div class="space-y-4">
            <div>
                <label for="edit-fecha" class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                <input type="date" id="edit-fecha" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="edit-turno" class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                <select id="edit-turno" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="1">Turno 1</option>
                    <option value="2">Turno 2</option>
                    <option value="3">Turno 3</option>
                </select>
            </div>
            <div>
                <label for="edit-empleado" class="block text-sm font-medium text-gray-700 mb-1">No. Empleado</label>
                <input type="text" id="edit-empleado" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="edit-nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre Empleado</label>
                <input type="text" id="edit-nombre" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="edit-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="edit-status" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="En Proceso">En Proceso</option>
                    <option value="Finalizado">Finalizado</option>
                </select>
            </div>
        </div>
        <x-slot:footer>
            <button type="button" data-ui-modal-close-target="modal-editar-registro" class="min-h-touch px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" id="modal-editar-save" class="min-h-touch px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">
                <i class="fa-solid fa-save mr-1" aria-hidden="true"></i>Guardar Cambios
            </button>
        </x-slot:footer>
    </x-ui.modal-base>
    @endif

    {{-- Modal: Nuevo Corte de Eficiencia --}}
    <x-ui.modal-base id="modal-nuevo-corte" title="Nuevo Corte de Eficiencia" size="sm" :close-on-backdrop="true">
        <div class="mb-4">
            <label for="input-fecha-nuevo-corte" class="block text-sm font-semibold text-gray-700 mb-1">Fecha</label>
            <input type="date" id="input-fecha-nuevo-corte"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="mb-2">
            <label for="select-turno-nuevo-corte" class="block text-sm font-semibold text-gray-700 mb-1">Turno</label>
            <select id="select-turno-nuevo-corte"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">-- Seleccione un turno --</option>
                <option value="1">Turno 1</option>
                <option value="2">Turno 2</option>
                <option value="3">Turno 3</option>
            </select>
        </div>
        <x-slot:footer>
            <button type="button" data-ui-modal-close-target="modal-nuevo-corte"
                class="min-h-touch px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium border border-gray-300 transition-colors">
                Cancelar
            </button>
            <button type="button" id="btn-confirmar-nuevo-corte"
                class="min-h-touch px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                Crear Corte
            </button>
        </x-slot:footer>
    </x-ui.modal-base>

<style>
    .selected-row td, .selected-row span {
        color: #fff !important;
    }
    .selected-row .status-badge-finalizado { color: #15803d !important; }
    .selected-row .status-badge-proceso { color: #1d4ed8 !important; }
    .selected-row .status-badge-otro { color: #854d0e !important; }
</style>
@endsection

@push('scripts')
    @vite('resources/js/modulos/tejido/cortes-eficiencia/consultar/index.ts')
@endpush
