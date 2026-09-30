{{--
    Vista Karl Mayer - Programación de Requerimientos
    Diseño similar a Editar Orden Urdido (sin tabla de producción)
    JS: resources/js/modulos/programa-urd-eng/karl-mayer/index.ts (19-05)
--}}

@extends('layouts.app')

@section('page-title', 'Programación Karl Mayer')

@section('navbar-right')
<x-navbar.button-create
    id="btnCrearOrden"
    type="submit"
    form="form-karl-mayer"
    title="Crear Orden"
    icon="fa-save"
    iconColor="text-white"
    hoverBg="hover:bg-blue-600"
    bg="bg-blue-500"
    text="Crear Orden"
/>
@endsection

@section('content')
<style>
    .sort-icon { opacity: 0.5; transition: opacity 0.2s; }
    .sortable:hover .sort-icon { opacity: 1; }
    .sortable.sort-asc .sort-icon::before { content: "\f0de"; }
    .sortable.sort-desc .sort-icon::before { content: "\f0dd"; }
    .sortable.sort-asc .sort-icon, .sortable.sort-desc .sort-icon { opacity: 1; }
</style>
@php
    $configKarlMayer = [
        'rutas' => [
            'buscarBomUrdido' => route('programa.urd.eng.buscar.bom.urdido'),
            'materialesCompleto' => route('programa.urd.eng.materiales.urdido.completo'),
            // Karl Mayer usa el catálogo de JULIO-URDIDO
            'hilos' => route('programa.urd.eng.hilos', ['tipo' => 'Urdido']),
            'tamanos' => route('programa.urd.eng.tamanos', ['tipo' => 'Urdido']),
            'crearOrden' => route('programa.urd.eng.crear.orden.karl.mayer'),
            'index' => route('programa.urd.eng.index'),
        ],
    ];
@endphp
<form id="form-karl-mayer" method="post" action="{{ route('programa.urd.eng.crear.orden.karl.mayer') }}"
      data-pagina='@json($configKarlMayer)'>
@php
    $inputBaseClass = 'w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500';
    $inputTablaClass = 'w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
    $filasJulios = [
        ['obs_placeholder' => ''],
        ['obs_placeholder' => ''],
        ['obs_placeholder' => ''],
        ['obs_placeholder' => ''],
    ];
@endphp
<div class="w-full">
    <div class="bg-white p-3 mb-4 shadow-sm border border-gray-200">
        {{-- Primera fila: No. Telar, Barras, Fibra, Tamaño, Cuenta, Calibre, Metros --}}
        <div class="grid gap-1.5 mb-1.5" style="display: grid; grid-template-columns: 0.7fr 1.2fr 1fr 0.9fr 0.7fr 0.7fr 0.7fr;">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">No. Telar</label>
                <select required name="no_telar" class="{{ $inputBaseClass }}">
                    <option value="">Seleccionar...</option>
                    <option value="401">401</option>
                    <option value="402">402</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Barras</label>
                <select required name="barras" class="{{ $inputBaseClass }}">
                    <option value="">Seleccionar...</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fibra</label>
                <select required id="input-fibra" name="fibra" class="{{ $inputBaseClass }}">
                    <option value="">Seleccionar...</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tamaño</label>
                <div class="relative">
                    <input type="text" required id="input-tamano" name="tamano"
                        autocomplete="off" placeholder="Buscar..."
                        class="{{ $inputBaseClass }}">
                    <div id="tamano-dropdown"
                        class="hidden fixed z-[9999] bg-white border border-gray-300 rounded shadow-lg overflow-y-auto text-sm"
                        style="max-height:200px;">
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Cuenta</label>
                <input type="text" required id="input-cuenta" name="cuenta" placeholder="" readonly
                    class="{{ $inputBaseClass }} bg-gray-100" title="Se completa al elegir Fibra y Tamaño">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Calibre</label>
                <input type="text" required id="input-calibre" name="calibre" placeholder="" readonly
                    class="{{ $inputBaseClass }} bg-gray-100" title="Se completa al elegir Fibra y Tamaño">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Metros</label>
                <input type="number" step="0.01" required name="metros" value="" placeholder=""
                    class="{{ $inputBaseClass }}">
            </div>
        </div>

        {{-- Segunda fila: Fecha Programada, Tipo Atado, Bom Urdido, Lote Proveedor --}}
        <div class="grid gap-1.5" style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fecha Programada</label>
                <input type="date" required name="fecha_programada" value="{{ date('Y-m-d') }}" placeholder=""
                    class="{{ $inputBaseClass }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tipo Atado</label>
                <select required name="tipo_atado" class="{{ $inputBaseClass }}">
                    <option value="">Seleccionar...</option>
                    <option value="Normal" selected>Normal</option>
                    <option value="Especial">Especial</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Bom Urdido</label>
                <input type="text" required id="input-lmat" name="bom_id" placeholder=""
                    class="{{ $inputBaseClass }}"
                    autocomplete="off"
                    list="input-lmat-options">
                <datalist id="input-lmat-options"></datalist>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Lote Proveedor</label>
                <input type="text" readonly id="input-lote-proveedor" name="lote_proveedor" placeholder=""
                    class="{{ $inputBaseClass }} bg-gray-100"
                    title="Se completa al seleccionar un registro del inventario">
            </div>
        </div>

        {{-- Tablas: Resumen materiales (izq) + Inventario detalle (der) - más ancho a la derecha --}}
        <div class="mt-2 grid gap-2" style="display: grid; grid-template-columns: minmax(160px, 0.22fr) minmax(0, 1fr);">
            {{-- Tabla izquierda: Resumen (Articulo, Config, Consumo, Kilos) --}}
            <div class="border border-gray-400 rounded overflow-hidden">
                <div class="overflow-auto" style="height: 220px; max-height: 220px;">
                    <table id="tabla-resumen-lmat" class="min-w-full text-sm">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th class="px-2 py-1.5 text-center font-semibold">Articulo</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Config</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Consumo</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Kilos</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-resumen-lmat-body" class="divide-y divide-gray-200 bg-white">
                            <tr>
                                <td colspan="4" class="px-2 py-3 text-center text-gray-500 text-sm">Ingrese Bom Urdido (lmat)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            {{-- Tabla derecha: Detalle inventario con selección --}}
            <div class="border border-gray-400 rounded overflow-hidden flex flex-col" style="height: 260px;">
                <div class="overflow-auto flex-1 min-h-0">
                    <table id="tabla-detalle-lmat" class="min-w-full text-sm">
                        <thead class="text-white sticky top-0 z-10 whitespace-nowrap">
                            <tr>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="itemId">Articulo <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="configId">Config <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="inventSizeId">Tamaño <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="inventColorId">Color <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="inventLocationId">Almacen <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="inventBatchId">Lote <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="wmsLocationId">Localidad <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="inventSerialId">Serie <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="noProv">No Prov. <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="loteProv">Lote Prov. <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="prodDate">Fecha <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 sortable cursor-pointer hover:bg-blue-600" data-sort="conos">Conos <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-500 sortable cursor-pointer hover:bg-blue-600" data-sort="kilos">Kilos <i class="fa-solid fa-sort sort-icon ml-1" aria-hidden="true"></i></th>
                                <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 w-10">Seleccionar</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-detalle-lmat-body" class="divide-y divide-gray-200 bg-white">
                            <tr>
                                <td colspan="14" class="px-2 py-3 text-center text-gray-500 text-sm">Ingrese Bom Urdido (lmat)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="tabla-detalle-lmat-footer" class="shrink-0 border-t border-gray-200 text-sm py-1.5 overflow-x-hidden">
                    <table class="min-w-full text-sm" style="table-layout: fixed; width: 100%;">
                        <tr>
                            <td class="px-1.5 py-0 font-medium bg-blue-50"><span id="txt-total-registros">Total: 0</span></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td class="px-1.5 py-0 bg-white"></td>
                            <td class="px-1.5 py-0 bg-blue-50"></td>
                            <td id="txt-total-conos" class="px-1.5 py-0 text-center font-semibold bg-white whitespace-nowrap" style="min-width: 50px;"></td>
                            <td id="txt-total-kilos" class="px-1.5 py-0 text-center font-semibold bg-blue-50 whitespace-nowrap" style="min-width: 70px;"></td>
                            <td class="px-1.5 py-0 w-10 bg-white"></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Tercera sección: No. Julio / Hilos + Observaciones --}}
        <div class="mt-2 grid gap-2" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div>
                <div class="overflow-x-auto border border-gray-200 rounded">
                    <table class="min-w-full text-sm">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th class="px-2 py-1.5 text-center font-semibold">No. Julio</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Hilos</th>
                                <th class="px-2 py-1.5 text-center font-semibold">Obs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($filasJulios as $filaJulio)
                            <tr>
                                <td class="px-2 py-1.5 text-center">
                                    <input type="number" min="1" name="julios[]"
                                        value="{{ $loop->first ? 4 : '' }}"
                                        class="{{ $inputTablaClass }}">
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <input type="number" min="1" name="hilos[]"
                                        class="{{ $inputTablaClass }}">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="text" placeholder="{{ $filaJulio['obs_placeholder'] }}" name="obs[]"
                                        class="{{ $inputTablaClass }}">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Observaciones del Programa</label>
                <textarea rows="3" name="observaciones" placeholder=""
                    class="{{ $inputTablaClass }} resize-none"></textarea>
            </div>
        </div>
    </div>
</div>
</form>

@include('modulos.programa_urd_eng.comun.modal-fecha-requerimiento', ['id' => 'modal-fecha-req-km'])

@push('scripts')
    @vite('resources/js/modulos/programa-urd-eng/karl-mayer/index.ts')
@endpush
@endsection
