@extends('layouts.app')

@section('page-title', 'Creación de Órdenes')

@section('navbar-right')
<div class="flex items-center gap-1">
   <x-navbar.button-create
   onclick="crearOrdenes()"
   title="Crear Órdenes"
   icon="fa-save"
   iconColor="text-white"

   bg="bg-purple-500"
   text="Crear Órdenes"
   />
</div>
@endsection

@section('content')
<style>
    .sort-icon {
        opacity: 0.5;
        transition: opacity 0.2s;
    }
    .sortable:hover .sort-icon {
        opacity: 1;
    }
    .sortable.sort-asc .sort-icon::before {
        content: "\f0de"; /* fa-sort-up */
    }
    .sortable.sort-desc .sort-icon::before {
        content: "\f0dd"; /* fa-sort-down */
    }
    .sortable.sort-asc .sort-icon,
    .sortable.sort-desc .sort-icon {
        opacity: 1;
    }
</style>
<div class="w-full">
    {{-- =================== Tabla de requerimientos agrupados =================== --}}
    <div class="bg-white overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table id="tablaOrdenes" class="w-full">
                <thead>
                    <tr class="bg-blue-500 text-white">
                        <th class="px-2 py-2 text-center text-sm font-semibold w-24">Telar</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-28">Fec Req</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-20">Cuenta</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-20">Calibre</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-24">Hilo</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-24">Tamaño</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-28">Urdido</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-20">Tipo</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-28">Destino</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-24">Metros</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-24">Kilos</th>
                        <th class="px-2 py-2 text-center text-sm font-semibold w-32">L.Mat Urdido</th>
                    </tr>
                </thead>
                <tbody id="tbodyOrdenes" class="bg-white divide-y">
                    {{-- filas dinámicas --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- =================== Tablas 2 y 3: Materiales Urdido y Engomado (mismo nivel) =================== --}}
    <div class="flex gap-4 mb-4">
        {{-- =================== Tabla 2: Materiales Urdido =================== --}}
        <div class="w-1/5 bg-white overflow-hidden rounded-2xl flex flex-col" style="max-height: 250px;">
            <div class="overflow-x-auto overflow-y-auto flex-1">
                <table id="tablaMaterialesUrdido" class="w-full">
                    <thead class="sticky top-0 bg-slate-100 z-10">
                        <tr class="bg-blue-500 text-white">
                            <th class="px-2 py-2 text-center text-sm font-semibold w-32">Articulo</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-32">Config</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-28">Consumo</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-20">Kilos</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMaterialesUrdido" class="bg-white divide-y">
                        {{-- filas dinámicas --}}
                    </tbody>
                </table>
            </div>
        </div>

        {{-- =================== Tabla 3: Materiales Engomado =================== --}}
        <div class="flex-1 bg-white overflow-hidden rounded-2xl flex flex-col" style="max-height: 250px;">
            <div class="overflow-x-auto overflow-y-auto flex-1">
                <table id="tablaMaterialesEngomado" class="w-full">
                    <thead class="sticky top-0 bg-slate-100 z-10">
                        <tr class="bg-blue-500 text-white">
                            <th class="px-2 py-2 text-center text-sm font-semibold w-24 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="itemId">
                                Articulo <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-24 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="configId">
                                Config <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-20 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="inventSizeId">
                                Tamaño <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-20 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="inventColorId">
                                Color <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-24 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="inventLocationId">
                                Almacen <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-20 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="inventBatchId">
                                Lote <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-24 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="wmsLocationId">
                                Localidad <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-20 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="inventSerialId">
                                Serie <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-28 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="loteProv">
                                Lote Proveedor <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-24 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="noProv">
                                No Proveedor <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-28 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="prodDate">
                                Fecha <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-20 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="conos">
                                Conos <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-20 sortable cursor-pointer hover:bg-blue-600 transition-colors" data-sort="kilos">
                                Kilos <i class="fa-solid fa-sort sort-icon ml-1"></i>
                            </th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-20">Seleccionar</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMaterialesEngomado" class="bg-white">
                        {{-- filas dinámicas --}}
                    </tbody>
                    <tfoot class="bg-gray-100 border-t-2 border-gray-300 sticky bottom-0">
                        <tr class="font-semibold">
                            <td colspan="11" class="px-2 py-2 text-sm text-right text-gray-700">
                                Total registros seleccionados: <span id="totalRegistros" class="font-bold text-blue-600">0</span>
                            </td>
                            <td id="totalConos" class="px-2 py-2 text-sm text-center font-bold text-blue-600">0</td>
                            <td id="totalKilos" class="px-2 py-2 text-sm text-center font-bold text-blue-600">0.00</td>
                            <td class="px-2 py-2 text-sm text-center"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- =================== Tablas 4 y 5: Construcción Urdido y Datos de Engomado (mismo nivel) =================== --}}
    <div class="flex gap-4 mb-4">
        {{-- =================== Tabla 4: Construcción Urdido =================== --}}
        <div class="w-1/6 bg-white overflow-hidden rounded-2xl flex flex-col border border-blue-200" style="max-height: 220px;">
            <div class="overflow-x-auto overflow-y-auto flex-1">
                <table id="tablaConstruccionUrdido" class="w-full">
                    <thead class="sticky top-0 bg-slate-100 z-10">
                        <tr class="bg-blue-500 text-white">
                            <th class="px-2 py-1 text-center text-sm font-semibold">No. Julios</th>
                            <th class="px-2 py-1 text-center text-sm font-semibold">Hilos</th>
                            <th class="px-2 py-1 text-center text-sm font-semibold">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyConstruccionUrdido" class="bg-white ">
                        <tr>
                            <td class="px-2 py-0.5">
                                <input type="number" step="1" min="0" max="15" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="number" step="1" min="0" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-2 py-0.5">
                                <input type="number" step="1" min="0" max="15" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="number" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-2 py-0.5">
                                <input type="number" step="1" min="0" max="15" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="number" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-2 py-0.5">
                                <input type="number" step="1" min="0" max="15" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="number" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- =================== Tabla 5: Datos de Engomado =================== --}}
        <div class="flex-1 bg-white overflow-hidden rounded-2xl flex flex-col border border-blue-200" style="max-height: 400px;">
            <div class="overflow-x-auto overflow-y-auto flex-1">
                <table id="tablaDatosEngomado" class="w-full">
                    <thead class="sticky top-0 bg-slate-100 z-10">
                        <tr class="bg-blue-500 text-white">
                            <th class="px-2 py-2 text-center text-sm font-semibold w-24">Núcleo</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-28">No. de Telas</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-28">Ancho Balonas</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-28">Metraje de Telas</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-32">Cuendeados Mín. por Tela</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-32">Máquina Engomado</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-32">L Mat Engomado</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-32">Bom Formula</th>
                            <th class="px-2 py-2 text-center text-sm font-semibold w-40">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyDatosEngomado" class="bg-white divide-y">
                        <tr>
                            <td class="px-2 py-2">
                                <select id="inputNucleo" class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="">Seleccione</option>
                                    <!-- Los núcleos se cargarán dinámicamente desde el catálogo -->
                                </select>
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" id="inputNoTelas" class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="" value="2" required>
                            </td>
                            <td class="px-2 py-2">
                                <select id="inputAnchoBalonas" class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="">Seleccione</option>
                                </select>
                            </td>
                            <td class="px-2 py-2">
                                <input type="text" id="inputMetrajeTelas" class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" id="inputCuendeadosMin" class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="" value="2" required>
                            </td>
                            <td class="px-2 py-2">
                                <select id="inputMaquinaEngomado" class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="">Seleccione</option>
                                </select>
                            </td>
                            <td class="px-2 py-2">
                                <input type="text" id="inputLMatEngomado" class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Buscar lista..." data-bom-engomado-input="true" autocomplete="off" required>
                            </td>
                            <td class="px-2 py-2">
                                <span id="inputBomFormula" class="block w-full px-2 py-1.5 bg-gray-100 border border-gray-200 rounded-md text-xs text-gray-600" readonly aria-readonly="true">—</span>
                            </td>
                            <td class="px-2 py-2">
                                <textarea id="inputObservaciones" rows="2" class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder=""></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/modulos/programa_urd_eng/creacion-ordenes.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Restricción en tiempo real: No. Julios máximo 15
    document.getElementById('tbodyConstruccionUrdido')?.addEventListener('input', function(e) {
        const input = e.target;
        if (input.tagName !== 'INPUT' || input.type !== 'number') return;
        // Solo aplica a la primera columna (No. Julios) de cada fila
        const td = input.closest('td');
        if (!td || td !== td.parentElement.children[0]) return;
        const val = parseInt(input.value, 10);
        if (val > 15) {
            input.value = 15;
            Swal.fire({ icon: 'warning', title: 'Máximo 15 julios', text: 'El número de julios no puede ser mayor a 15.', confirmButtonColor: '#2563eb', toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
        }
    });

    if (typeof window.initCreacionOrdenes === 'function') {
        window.initCreacionOrdenes({
            telaresData: @json($telaresSeleccionados ?? []),
            routes: {
                buscarBomUrdido: '{{ route("programa.urd.eng.buscar.bom.urdido") }}',
                buscarBomEngomado: '{{ route("programa.urd.eng.buscar.bom.engomado") }}',
                materialesUrdido: '{{ route("programa.urd.eng.materiales.urdido") }}',
                materialesEngomado: '{{ route("programa.urd.eng.materiales.engomado") }}',
                anchosBalona: '{{ route("programa.urd.eng.anchos.balona") }}',
                maquinasEngomado: '{{ route("programa.urd.eng.maquinas.engomado") }}',
                nucleos: '{{ route("programa.urd.eng.nucleos") }}',
                bomFormula: '{{ route("programa.urd.eng.bom.formula") }}',
                crearOrdenes: '{{ route("programa.urd.eng.crear.ordenes") }}'
            }
        });
    }
});
</script>
@endsection
