@extends('layouts.app')

@section('page-title', 'Creación de Órdenes')

@section('navbar-right')
<div class="flex items-center gap-1">
   <x-navbar.button-create onclick="crearOrdenes()" title="Crear Órdenes" />
</div>
@endsection

@section('content')
<div class="w-full">
    {{-- =================== Tabla de requerimientos agrupados =================== --}}
    <div class="bg-white overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table id="tablaOrdenes" class="w-full">
                <thead>
                    <tr class="bg-slate-100 border-b">
                        <th class="px-2 py-2 text-center text-xs font-semibold w-24">Telar</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-28">Fec Req</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-20">Cuenta</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-20">Calibre</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-24">Hilo</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-28">Urdido</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-20">Tipo</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-28">Destino</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-24">Metros</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-24">Kilos</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-32">L.Mat Urdido</th>
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
                        <tr class="bg-slate-100 border-b">
                            <th class="px-2 py-2 text-center text-xs font-semibold w-32">Articulo</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-32">Config</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-28">Consumo</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Kilos</th>
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
                        <tr class="bg-slate-100 border-b">
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">Articulo</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">Config</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Tamaño</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Color</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">Almacen</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Lote</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">Localidad</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Serie</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Kilos</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Conos</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-28">Lote Proveedor</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">No Proveedor</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-28">Fecha</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Seleccionar</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMaterialesEngomado" class="bg-white divide-y">
                        {{-- filas dinámicas --}}
                    </tbody>
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
                        <tr class="bg-slate-100 border-b">
                            <th class="px-2 py-1 text-center text-xs font-semibold">No. Julios</th>
                            <th class="px-2 py-1 text-center text-xs font-semibold">Hilos</th>
                            <th class="px-2 py-1 text-center text-xs font-semibold">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyConstruccionUrdido" class="bg-white divide-y">
                        <tr>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
                            </td>
                            <td class="px-2 py-0.5">
                                <input type="text" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="" required>
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
                        <tr class="bg-slate-100 border-b">
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">Núcleo</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-28">No. de Telas</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-28">Ancho Balonas</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-28">Metraje de Telas</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-32">Cuendeados Mín. por Tela</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-32">Máquina Engomado</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-32">L Mat Engomado</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-40">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyDatosEngomado" class="bg-white divide-y">
                        <tr>
                            <td class="px-2 py-2">
                                <select id="inputNucleo" class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="">Seleccione</option>
                                    <option value="Jacquard">Jacquard</option>
                                    <option value="Itema">Itema</option>
                                    <option value="Smith">Smith</option>
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
                crearOrdenes: '{{ route("programa.urd.eng.crear.ordenes") }}'
            }
        });
    }
});
</script>
@endsection
