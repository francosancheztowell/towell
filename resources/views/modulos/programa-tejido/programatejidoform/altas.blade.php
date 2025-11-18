@extends('layouts.app')

@section('page-title', 'Programar Altas Especiales')

@section('navbar-right')
<x-navbar.button-create
    onclick="ProgramaTejidoCRUD.guardar()"
    title="Guardar"
    module="Programa Tejido"
    :disabled="false"
    icon="fa-save"
    text="Guardar"
    bg="bg-blue-600"
    iconColor="text-white"
    hoverBg="hover:bg-blue-700" />
@endsection

@section('content')
<div class="w-full">


    {{-- Formulario de datos generales --}}
    <div class="bg-white shadow-xl overflow-hidden rounded-2xl mt-1">
            <div class="p-8">
            {{-- SECCIÓN: DATOS GENERALES --}}
                <div class="mb-8">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <tbody>
                        <!-- Fila 1: Salon, Aplicación, Calibres Trama-C4 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Salon</td>
                                    <td class="px-2 py-1">
                                        <input type="text" id="salon-input" placeholder="Ingrese salon" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100">
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Aplicación</td>
                                    <td class="px-2 py-1">
                                        <select id="aplicacion-select" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
                                            <option value="">Seleccione aplicación...</option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre Trama</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-trama" placeholder="Ingrese calibre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre C2</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c2" placeholder="Ingrese calibre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre C4</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c4" placeholder="Ingrese calibre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                </tr>

                            <!-- Fila 2: Clave Modelo, Cuenta Rizo, Hilos Trama-C4 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Clave Modelo</td>
                                    <td class="px-2 py-1 relative">
                                        <input  type="text" id="clave-modelo-input" placeholder="Escriba para buscar..." class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs">
                                        <div id="clave-modelo-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-b shadow-lg hidden max-h-40 overflow-y-auto"></div>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cuenta Rizo</td>
                                <td class="px-2 py-1"><input disabled type="text" id="cuenta-rizo" placeholder="Ingrese cuenta" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo Trama</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-trama" placeholder="Ingrese hilo" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C2</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c2" placeholder="Ingrese hilo" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C4</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c4" placeholder="Ingrese hilo" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                </tr>

                            <!-- Fila 3: Nombre Modelo, Calibre Rizo, Cod/Color 1-3 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Modelo</td>
                                <td class="px-2 py-1"><input disabled type="text" id="nombre-modelo" placeholder="Ingrese nombre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre Rizo</td>
                                <td class="px-2 py-1"><input disabled type="text" id="calibre-rizo" placeholder="Ingrese calibre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-1" placeholder="Ingrese código" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-2" placeholder="Ingrese código" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-3" placeholder="Ingrese código" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                </tr>

                            <!-- Fila 4: Tamaño, Hilo Rizo, Nombre Color 1-3 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Tamaño</td>
                                <td class="px-2 py-1"><input disabled type="text" id="tamano" placeholder="Ingrese tamaño" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo Rizo</td>
                                <td class="px-2 py-1"><input disabled type="text" id="hilo-rizo" placeholder="Ingrese hilo" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-1" placeholder="Ingrese nombre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-2" placeholder="Ingrese nombre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-3" placeholder="Ingrese nombre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                </tr>

                            <!-- Fila 5: Hilo, Cuenta Pie, Calibres C1-C3-C5 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo</td>
                                    <td class="px-2 py-1">
                                        <select id="hilo-select" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
                                            <option value="">Seleccione hilo...</option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cuenta Pie</td>
                                <td class="px-2 py-1"><input disabled type="text" id="cuenta-pie" placeholder="Ingrese cuenta" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre C1</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c1" placeholder="Ingrese calibre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre C3</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c3" placeholder="Ingrese calibre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre C5</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c5" placeholder="Ingrese calibre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                </tr>

                            <!-- Fila 6: IdFlog, Calibre Pie, Hilos C1-C3-C5 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">IdFlog</td>
                                    <td class="px-2 py-1 relative">
                                        <input type="text" id="idflog-input" placeholder="Escriba para buscar..." class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
                                        <div id="idflog-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-b shadow-lg hidden max-h-40 overflow-y-auto"></div>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre Pie</td>
                                <td class="px-2 py-1"><input disabled type="text" id="calibre-pie" placeholder="Ingrese calibre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C1</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c1" placeholder="Ingrese hilo" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C3</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c3" placeholder="Ingrese hilo" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C5</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c5" placeholder="Ingrese hilo" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                </tr>

                            <!-- Fila 7: Descripción, Hilo Pie, Cod Color 4-6 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Descripción</td>
                                <td class="px-2 py-1"><textarea disabled id="descripcion" rows="1" placeholder="Ingrese descripción" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs resize-none"></textarea></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo Pie</td>
                                <td class="px-2 py-1"><input disabled type="text" id="hilo-pie" placeholder="Ingrese hilo" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-4" placeholder="Ingrese código" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-5" placeholder="Ingrese código" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-6" placeholder="Ingrese código" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                </tr>

                            <!-- Fila 8: Calendario, Rasurado, Nombre Color 4-6 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calendario</td>
                                    <td class="px-2 py-1">
                                        <select id="calendario-select" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
                                            <option value="">Seleccione calendario...</option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Rasurado</td>
                                <td class="px-2 py-1"><input disabled type="text" id="rasurado" placeholder="Ingrese rasurado" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-4" placeholder="Ingrese nombre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-5" placeholder="Ingrese nombre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-6" placeholder="Ingrese nombre" disabled class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"></td>
                                </tr>

                            <!-- Campos ocultos para cálculos -->
                            <div class="hidden">
                                <input type="number" id="ancho" step="0.01">
                                <input type="number" id="eficiencia-std" step="0.01">
                                <input type="number" id="velocidad-std" step="0.01">
                                <input type="text" id="maquina">
                                <input type="hidden" id="cantidad">
                                <input type="number" id="luchaje" step="0.01">
                                <input type="number" id="no-tiras" step="0.01">
                                <input type="number" id="repeticiones" step="0.01">
                                <input type="number" id="total" step="0.01">
                            </div>
                            </tbody>
                        </table>
                    </div>
                </div>
        </div>
    </div>

    {{-- SECCIÓN: DATOS DEL TELAR --}}
    <div class="bg-white shadow-xl overflow-hidden rounded-2xl mt-1">
        <div class="p-8">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3">
                        <h3 class="text-lg font-semibold text-gray-800">Datos del telar</h3>
                            <span id="badge-cantidad-pedido" class="hidden px-3 py-1 bg-blue-500 text-white text-sm font-medium rounded-full">
                                Cantidad pedido: <span id="cantidad-pedido-valor">0</span>
                            </span>
                        </div>
                        <div class="flex gap-2">
                    <button title="Agregar fila" id="btn-agregar-telar" onclick="TelarManager.agregarFilaTelar()" disabled class="px-3 py-2 bg-gray-400 text-white rounded cursor-not-allowed flex items-center gap-2 text-sm">
                                <i class="fas fa-plus"></i>
                            </button>
                    <button title="Eliminar fila" id="btn-eliminar-telar" onclick="TelarManager.eliminarFilaTelar()" disabled class="px-3 py-2 bg-gray-400 text-white rounded cursor-not-allowed flex items-center gap-2 text-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-300">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">TELAR</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">CANTIDAD</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">FECHA INICIO</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">FECHA FIN</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">COMPROMISO TEJIDO</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">FECHA CLIENTE</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">FECHA ENTREGA</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyTelares">
                                <tr id="mensaje-vacio-telares" class="hidden">
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500 text-sm">
                                        No hay telares agregados. Haga clic en el botón "+" para agregar una fila.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
            </div>
        </div>
    </div>

    {{-- Tabla de Líneas Diarias (visible después de crear) --}}
    <div id="contenedor-lineas-diarias" style="display:none;" class="mt-6">
        @include('components.tables.req-programa-tejido-line-table')
    </div>
    </div>

{{-- Scripts modulares --}}
<script src="{{ asset('js/programa-tejido/config.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/utils.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/calendario-manager.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/telar-manager.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/form-manager.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/crud-manager.js') }}?v={{ time() }}"></script>

<script>
// Leer parámetros de la URL ANTES de cualquier inicialización
const urlParams = new URLSearchParams(window.location.search);
const urlIdflog = urlParams.get('idflog') || urlParams.get('IDFLOG');
const urlTipohilo = urlParams.get('tipohilo') || urlParams.get('TIPOHILO');
const urlSalon = urlParams.get('salon') || urlParams.get('SALON');
const urlClaveModelo = urlParams.get('clavemodelo') || urlParams.get('CLAVEMODELO');
const urlItemid = urlParams.get('itemid') || urlParams.get('ITEMID');
const urlInventsizeid = urlParams.get('inventsizeid') || urlParams.get('INVENTSIZEID');

function agregarFilaTelar() { TelarManager.agregarFilaTelar(); }
function eliminarFilaTelar() { TelarManager.eliminarFilaTelar(); }
window.calcularFechaFinalFila = function(fila) { ProgramaTejidoForm.calcularFechaFinalFila(fila); };

// Función global para establecer valor en un select
window.establecerValorSelect = function(selectId, valor) {
    const select = document.getElementById(selectId);
    if (!select || !valor) return;

    // Buscar si existe la opción
    const exists = Array.from(select.options).find(opt => opt.value === valor);
    if (exists) {
        select.value = valor;
        if (window.ProgramaTejidoUtils && typeof ProgramaTejidoUtils.establecerValorCampo === 'function') {
            ProgramaTejidoUtils.establecerValorCampo(selectId, valor);
        }
        select.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
        // Agregar la opción si no existe
        const option = document.createElement('option');
        option.value = valor;
        option.textContent = valor;
        if (select.options.length > 0 && select.options[0].value === '') {
            select.insertBefore(option, select.options[1] || null);
        } else {
            select.insertBefore(option, select.firstChild);
        }
        select.value = valor;
        if (window.ProgramaTejidoUtils && typeof ProgramaTejidoUtils.establecerValorCampo === 'function') {
            ProgramaTejidoUtils.establecerValorCampo(selectId, valor);
        }
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
};

// Variable global para evitar múltiples inicializaciones
window.altasFormInicializado = false;

// Funciones de compatibilidad (igual que create.blade.php)
function agregarFilaTelar() { TelarManager.agregarFilaTelar(); }
function eliminarFilaTelar() { TelarManager.eliminarFilaTelar(); }

window.calcularFechaFinalFila = function(fila) {
    ProgramaTejidoForm.calcularFechaFinalFila(fila);
};

// Inicialización base (igual que create.blade.php)
document.addEventListener('DOMContentLoaded', function() {
    if (window.altasFormInicializado) {
        return;
    }

    // Inicializar el formulario base primero (igual que create.blade.php)
    if (window.ProgramaTejidoForm && typeof window.ProgramaTejidoForm.init === 'function') {
        window.altasFormInicializado = true;
        ProgramaTejidoForm.init(false, null);
    }
});
</script>

<script>
(function () {
    const qs = new URLSearchParams(location.search);

    // Valores de la URL (ya vienen decodificados)
    const Q = {
        idflog: qs.get('idflog') || qs.get('IDFLOG') || qs.get('flog') || qs.get('FLOG') || '',
        itemid: qs.get('itemid') || qs.get('ITEMID') || '',
        inventsizeid: qs.get('inventsizeid') || qs.get('INVENTSIZEID') || '',
        cantidad: qs.get('cantidad') || qs.get('CANTIDAD') || '',
        tipohilo: qs.get('tipohilo') || qs.get('TIPOHILO') || '',
        salon: qs.get('salon') || qs.get('SALON') || '',
        clavemodelo: qs.get('clavemodelo') || qs.get('CLAVEMODELO') || '',
        custname: qs.get('custname') || qs.get('CUSTNAME') || '',
        estado: qs.get('estado') || qs.get('ESTADO') || '',
        nombreproyecto: qs.get('nombreproyecto') || qs.get('NOMBREPROYECTO') || '',
        categoriacalidad: qs.get('categoriacalidad') || qs.get('CATEGORIACALIDAD') || ''
    };

    console.log('📋 Parámetros de URL cargados:', Q);

    // Pequeño util - con protección contra reinicios
    const setVal = (id, val, fire = true, force = false) => {
        if (!val) return;
        const el = document.getElementById(id);
        if (!el) return;

        // Si el campo ya fue establecido y tiene un valor, no sobrescribir a menos que sea forzado
        if (!force && camposYaEstablecidos.has(id) && el.value && el.value !== '') {
            return;
        }

        const wasDisabled = el.disabled;
        if (wasDisabled) el.disabled = false;
        el.value = val;
        camposYaEstablecidos.add(id); // Marcar como establecido
        if (fire) {
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (wasDisabled) el.disabled = true;
    };

    // Espera a que ProgramaTejidoForm cargue los catálogos y luego asigna
    const ready = () =>
        window.ProgramaTejidoForm && typeof window.ProgramaTejidoForm.init === 'function' &&
        window.ProgramaTejidoUtils;

    const ensureOption = (selectId, value, force = false, skipEvent = false) => {
        if (!value) return;
        const sel = document.getElementById(selectId);
        if (!sel) return;

        // Si el campo ya fue establecido y tiene un valor, no sobrescribir a menos que sea forzado
        if (!force && camposYaEstablecidos.has(selectId) && sel.value && sel.value !== '') {
            return;
        }

        // Marcar como modo prefill para evitar que los listeners limpien el formulario
        sel._prefillMode = true;

        if (![...sel.options].some(o => o.value === value)) {
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = value;
            sel.add(opt, sel.options.length ? 1 : null);
        }
        sel.value = value;
        camposYaEstablecidos.add(selectId); // Marcar como establecido

        // Solo disparar evento si no se especifica skipEvent
        if (!skipEvent) {
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Quitar el flag después de un momento
        setTimeout(() => {
            sel._prefillMode = false;
        }, 500);
    };

    // Bandera para evitar búsquedas múltiples
    let busquedaEnProgreso = false;
    let modeloYaCargado = false;
    let camposYaEstablecidos = new Set(); // Para evitar sobrescribir campos ya establecidos

    // Función para rellenar campos con datos del modelo encontrado
    // Usa la misma lógica que create.blade.php para asegurar consistencia
    const rellenarCamposConModelo = (data) => {
        if (!data || data.error) return;

        // Usar el método oficial de ProgramaTejidoForm (igual que create.blade.php)
        if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.llenarCamposConDatos) {
            window.ProgramaTejidoForm.llenarCamposConDatos(data);
            console.log('✅ Modelo cargado usando llenarCamposConDatos (igual que create)');

            // Verificar que los datos necesarios para cálculos estén presentes
            const datosModelo = window.ProgramaTejidoForm.state.datosModeloActual;
            console.log('📊 Datos del modelo guardados:', {
                NoTiras: datosModelo.NoTiras,
                Total: datosModelo.Total,
                Luchaje: datosModelo.Luchaje,
                Repeticiones: datosModelo.Repeticiones
            });
        } else {
            // Fallback si no está disponible (no debería pasar)
            console.warn('⚠️ ProgramaTejidoForm.llenarCamposConDatos no disponible');
            return;
        }

        // Cargar eficiencia y velocidad después de un delay para asegurar que los campos estén establecidos
        // Esperar a que se establezcan hilo, calibre-trama y telar
        setTimeout(() => {
            if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad) {
                window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad();
            }
        }, 800);

        // También intentar cargar después de que se seleccione un telar
        setTimeout(() => {
            if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad) {
                window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad();
            }
        }, 1500);

        // Establecer descripción si viene en los datos (forzar para asegurar que se establezca)
        // Solo establecer si no es "None" o "null"
        if (data.NombreProyecto || data.Prioridad) {
            const descripcion = data.NombreProyecto || data.Prioridad;
            // Evitar establecer valores "None" o "null" como strings
            if (descripcion && descripcion !== 'None' && descripcion !== 'null' && descripcion !== '') {
                const descripcionEl = document.getElementById('descripcion');
                if (descripcionEl) {
                    // Habilitar temporalmente si está deshabilitado
                    const wasDisabled = descripcionEl.disabled;
                    if (wasDisabled) descripcionEl.disabled = false;
                    setVal('descripcion', descripcion, true, true); // fire=true, force=true para asegurar que se establezca
                    if (wasDisabled) descripcionEl.disabled = true;
                    console.log('✅ Descripción establecida desde datos del modelo en rellenarCamposConModelo:', descripcion);
                }
            }
        }

        // Establecer salón si viene de la URL o de los datos del modelo
        if (data.SalonTejidoId || Q.salon) {
            const salonValue = data.SalonTejidoId || Q.salon;
            // Solo establecer si no estaba ya establecido o si viene del modelo encontrado
            if (!camposYaEstablecidos.has('salon-input') || data.SalonTejidoId) {
                setVal('salon-input', salonValue, false, true);
            }

            // Habilitar botones de telar cuando se establece el salón
            if (salonValue && window.ProgramaTejidoForm && window.ProgramaTejidoForm.actualizarBotonesTelar) {
                setTimeout(() => {
                    window.ProgramaTejidoForm.actualizarBotonesTelar(true);
                }, 200);
            }
        }
    };

    // Búsqueda optimizada del modelo
    const buscarModeloOptimizado = async () => {
        if (busquedaEnProgreso || modeloYaCargado) return;

        busquedaEnProgreso = true;

        // PRIORIDAD 1: clave modelo + salón
        if (Q.clavemodelo && Q.salon) {
            try {
                const url = new URL('{{ route("planeacion.buscar-detalle-modelo") }}', window.location.origin);
                url.searchParams.set('concatena', Q.clavemodelo.trim());
                url.searchParams.set('salon_tejido_id', Q.salon.trim());
                if (Q.itemid) url.searchParams.set('itemid', Q.itemid.trim());
                if (Q.inventsizeid) url.searchParams.set('inventsizeid', Q.inventsizeid.trim());

                const response = await fetch(url.toString());
                if (response.ok) {
                    const data = await response.json();
                    if (!data || data.error) {
                        // Continuar con siguiente prioridad
        } else {
                        modeloYaCargado = true;
                        rellenarCamposConModelo(data);
                        busquedaEnProgreso = false;
                        return;
                    }
                }
            } catch (err) {
                console.error('Error al buscar modelo:', err);
            }
        }

        // PRIORIDAD 2: itemid + inventsizeid + salón
        if (Q.itemid && Q.inventsizeid && Q.salon) {
            try {
                const url = new URL('{{ route("planeacion.buscar-detalle-modelo") }}', window.location.origin);
                url.searchParams.set('itemid', Q.itemid.trim());
                url.searchParams.set('inventsizeid', Q.inventsizeid.trim());
                url.searchParams.set('salon_tejido_id', Q.salon.trim());
                const concatena = (Q.inventsizeid + Q.itemid).toUpperCase().replace(/[\s\-_]+/g, '');
                if (concatena) url.searchParams.set('concatena', concatena);

                const response = await fetch(url.toString());
                if (response.ok) {
                    const data = await response.json();
                    if (!data || data.error) {
                        // Continuar con siguiente prioridad
                    } else {
                        modeloYaCargado = true;
                        rellenarCamposConModelo(data);
                        busquedaEnProgreso = false;
                        return;
                    }
                }
            } catch (err) {
                console.error('Error al buscar modelo:', err);
            }
        }

        // PRIORIDAD 3: solo clave modelo
        if (Q.clavemodelo && !Q.salon) {
            try {
                const url = new URL('{{ route("planeacion.buscar-detalle-modelo") }}', window.location.origin);
                url.searchParams.set('concatena', Q.clavemodelo.trim());
                if (Q.itemid) url.searchParams.set('itemid', Q.itemid.trim());
                if (Q.inventsizeid) url.searchParams.set('inventsizeid', Q.inventsizeid.trim());

                const response = await fetch(url.toString());
                if (response.ok) {
                    const data = await response.json();
                    if (!data || data.error) return;
                    modeloYaCargado = true;
                    rellenarCamposConModelo(data);
                }
            } catch (err) {
                console.error('Error al buscar modelo:', err);
            }
        }

        busquedaEnProgreso = false;
    };

    const prefill = () => {
        console.log('🔄 Iniciando prefill con valores:', Q);

        // Marcar que estamos en modo prefill para evitar limpiezas
        window._prefillMode = true;

        // Primero establecer los campos que dependen de catálogo (SIN disparar eventos)
        // Hacerlo en orden: idflog, hilo, salon (salon al final porque puede disparar eventos)
        if (Q.idflog) {
            console.log('📝 Estableciendo idflog-input con valor:', Q.idflog);
            // Intentar múltiples veces para asegurar que el input esté listo
            const establecerIdflog = () => {
                const input = document.getElementById('idflog-input');
                if (!input) {
                    console.warn('⚠️ idflog-input no encontrado, reintentando...');
                    setTimeout(establecerIdflog, 100);
                    return;
                }

                console.log('✅ idflog-input encontrado, estableciendo valor:', Q.idflog);
                setVal('idflog-input', Q.idflog, false); // No disparar eventos aún
                // Deshabilitar el input si viene desde la URL
                input.disabled = true;
                input.classList.add('bg-gray-100');
                input.classList.remove('bg-white');
            };

            // Intentar inmediatamente y con delays
            establecerIdflog();
            setTimeout(establecerIdflog, 200);
            setTimeout(establecerIdflog, 500);
            setTimeout(establecerIdflog, 1000);
        }

        if (Q.tipohilo) {
            ensureOption('hilo-select', Q.tipohilo, true, true); // skipEvent = true
            // Intentar cargar eficiencia/velocidad después de establecer el hilo
                    setTimeout(() => {
                if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad) {
                    window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad();
                }
            }, 600);
        }

        // Establecer inputs directos ANTES del salón para evitar conflictos
        if (Q.inventsizeid) setVal('tamano', Q.inventsizeid, false); // No disparar eventos aún
        if (Q.cantidad) {
            setVal('cantidad', Q.cantidad, false);
            // Mostrar badge de cantidad pedido
            const badge = document.getElementById('badge-cantidad-pedido');
            const valorBadge = document.getElementById('cantidad-pedido-valor');
            if (badge && valorBadge) {
                // Formatear como entero (redondear .50 y arriba)
                const cantidadNum = parseFloat(Q.cantidad);
                const cantidadEntera = !isNaN(cantidadNum) ? Math.round(cantidadNum) : Q.cantidad;
                valorBadge.textContent = cantidadEntera;
                badge.classList.remove('hidden');
            }
        }

        // Establecer clave modelo ANTES del salón para que no se borre
        if (Q.clavemodelo) {
            setVal('clave-modelo-input', Q.clavemodelo, false); // No disparar eventos aún
        }

        // Establecer descripción desde URL si está disponible
        if (Q.nombreproyecto || Q.descripcion) {
            const descripcion = Q.nombreproyecto || Q.descripcion;
            const descripcionEl = document.getElementById('descripcion');
            if (descripcionEl) {
                // Habilitar temporalmente si está deshabilitado
                const wasDisabled = descripcionEl.disabled;
                if (wasDisabled) descripcionEl.disabled = false;
                setVal('descripcion', descripcion, false);
                if (wasDisabled) descripcionEl.disabled = true;
            }
        }

        // Establecer salón al final (puede disparar eventos que afecten otros campos)
        // Pero NO disparar el evento change para evitar que limpie el formulario
        if (Q.salon) {
            // Asegurar que el modo prefill esté activo antes de establecer el salón
            window._prefillMode = true;
            setVal('salon-input', Q.salon, false); // No disparar eventos aún
            // Actualizar el state del salón para que esté disponible para las búsquedas
            if (window.ProgramaTejidoForm) {
                window.ProgramaTejidoForm.state.salonSeleccionado = Q.salon;
                // Cargar telares del salón sin limpiar el formulario
                if (window.ProgramaTejidoForm.cargarTelaresPorSalon) {
                    window.ProgramaTejidoForm.cargarTelaresPorSalon(Q.salon);
                }
            }
        }

        // Buscar y rellenar modelo si tenemos los datos necesarios
        // Esperar un momento para que los selects se establezcan completamente
        setTimeout(() => {
            // Mantener el modo prefill activo más tiempo para evitar limpiezas
            // window._prefillMode = false; // Quitar el flag después de establecer todo

            // Verificar que idflog se estableció correctamente (con múltiples reintentos)
            const idflogInput = document.getElementById('idflog-input');
            if (Q.idflog) {
                if (idflogInput && idflogInput.value === Q.idflog) {
                    console.log('✅ idflog establecido correctamente:', Q.idflog);
                    // Deshabilitar el input si viene desde la URL
                    idflogInput.disabled = true;
                    idflogInput.classList.add('bg-gray-100');
                    idflogInput.classList.remove('bg-white');
                    // Cargar descripción desde idflog si está disponible
                    if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.cargarDescripcionPorIdFlog) {
                        window.ProgramaTejidoForm.cargarDescripcionPorIdFlog(Q.idflog);
                    }
                } else {
                    console.warn('⚠️ idflog-input no se estableció correctamente, reintentando...');
                    setVal('idflog-input', Q.idflog, false, true); // force=true
                    // Deshabilitar el input si viene desde la URL
                    if (idflogInput) {
                        idflogInput.disabled = true;
                        idflogInput.classList.add('bg-gray-100');
                        idflogInput.classList.remove('bg-white');
                    }
                    // Reintentar después de un delay adicional
                    setTimeout(() => {
                        const idflogInput2 = document.getElementById('idflog-input');
                        if (idflogInput2 && idflogInput2.value !== Q.idflog) {
                            setVal('idflog-input', Q.idflog, false, true); // force=true
                            // Deshabilitar el input si viene desde la URL
                            idflogInput2.disabled = true;
                            idflogInput2.classList.add('bg-gray-100');
                            idflogInput2.classList.remove('bg-white');
                        } else if (idflogInput2 && idflogInput2.value === Q.idflog) {
                            console.log('✅ idflog establecido correctamente en reintento:', Q.idflog);
                            // Deshabilitar el input si viene desde la URL
                            idflogInput2.disabled = true;
                            idflogInput2.classList.add('bg-gray-100');
                            idflogInput2.classList.remove('bg-white');
                            // Cargar descripción desde idflog si está disponible
                            if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.cargarDescripcionPorIdFlog) {
                                window.ProgramaTejidoForm.cargarDescripcionPorIdFlog(Q.idflog);
                            }
                        }
                    }, 500);
                }
            }

            // Verificar que la descripción se estableció correctamente
            const descripcionEl = document.getElementById('descripcion');
            if (descripcionEl) {
                // Habilitar temporalmente si está deshabilitado
                const wasDisabled = descripcionEl.disabled;
                if (wasDisabled) descripcionEl.disabled = false;

                if (!descripcionEl.value || descripcionEl.value === '') {
                    // Si no hay descripción, intentar obtenerla desde los datos del modelo si están disponibles
                    const datosModelo = window.ProgramaTejidoForm?.state?.datosModeloActual;
                    if (datosModelo && (datosModelo.NombreProyecto || datosModelo.Prioridad)) {
                        const descripcion = datosModelo.NombreProyecto || datosModelo.Prioridad;
                        setVal('descripcion', descripcion, false, true); // force=true
                        console.log('✅ Descripción establecida desde datos del modelo:', descripcion);
                    } else if (Q.nombreproyecto || Q.descripcion) {
                        // Si no hay datos del modelo, usar los de la URL
                        const descripcion = Q.nombreproyecto || Q.descripcion;
                        setVal('descripcion', descripcion, false, true); // force=true
                        console.log('✅ Descripción establecida desde URL:', descripcion);
                    } else if (Q.idflog) {
                        // Si hay idflog pero no hay descripción aún, intentar cargarla desde el idflog
                        setTimeout(() => {
                            if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.cargarDescripcionPorIdFlog) {
                                window.ProgramaTejidoForm.cargarDescripcionPorIdFlog(Q.idflog);
                            }
                        }, 300);
                    }
                } else {
                    console.log('✅ Descripción ya establecida:', descripcionEl.value);
                }

                // Restaurar estado disabled si estaba deshabilitado
                if (wasDisabled) descripcionEl.disabled = true;
            }

            // Verificar que el salón se estableció correctamente
            const salonInput = document.getElementById('salon-input');
            if (Q.salon && salonInput && salonInput.value !== Q.salon) {
                console.warn('⚠️ salon-input no se estableció correctamente, reintentando...');
                setVal('salon-input', Q.salon, false, true); // force=true
                if (window.ProgramaTejidoForm) {
                    window.ProgramaTejidoForm.state.salonSeleccionado = Q.salon;
                    // Cargar telares del salón
                    if (window.ProgramaTejidoForm.cargarTelaresPorSalon) {
                        window.ProgramaTejidoForm.cargarTelaresPorSalon(Q.salon);
                    }
                }
            } else if (Q.salon && salonInput && salonInput.value === Q.salon) {
                // Asegurar que los telares se carguen si el salón ya está establecido
                if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.cargarTelaresPorSalon) {
                    window.ProgramaTejidoForm.cargarTelaresPorSalon(Q.salon);
                }
            }

            // Habilitar botones de telar si tenemos salón seleccionado
            const habilitarBotonesTelar = () => {
        const salonInput = document.getElementById('salon-input');
        if (salonInput && salonInput.value && salonInput.value !== '') {
            if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.actualizarBotonesTelar) {
                window.ProgramaTejidoForm.actualizarBotonesTelar(true);
                        console.log('Botones de telar habilitados');
                    }
                }
            };

            // Intentar habilitar inmediatamente y con delays
            habilitarBotonesTelar();
            setTimeout(habilitarBotonesTelar, 300);
            setTimeout(habilitarBotonesTelar, 600);

            // Buscar modelo usando el método de create.blade.php (cargarDatosRelacionados)
            if (Q.clavemodelo && Q.salon) {
                console.log('🔍 Buscando modelo con clave y salón desde URL...');
                if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.cargarDatosRelacionados) {
                    window.ProgramaTejidoForm.cargarDatosRelacionados(Q.salon, Q.clavemodelo);
                } else {
                    buscarModeloOptimizado();
                }
            } else if (Q.itemid && Q.inventsizeid && Q.salon) {
                console.log('🔍 Buscando modelo con itemid, inventsizeid y salón desde URL...');
                buscarModeloOptimizado();
            } else if (Q.clavemodelo && !Q.salon) {
                console.log('🔍 Buscando modelo solo con clave desde URL...');
                buscarModeloOptimizado();
            }

            // Quitar el flag de prefill después de un delay adicional para permitir que todo se establezca
            setTimeout(() => {
                window._prefillMode = false;
                console.log('✅ Modo prefill desactivado después de establecer todos los campos');
            }, 2000);
        }, 500);
    };

    document.addEventListener('DOMContentLoaded', function () {
        // Interceptar limpiarFormulario y onSalonChange DESPUÉS de que se cargue ProgramaTejidoForm
        const interceptarLimpiarFormulario = () => {
            if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.limpiarFormulario && !window._limpiarFormularioInterceptado) {
                window._limpiarFormularioInterceptado = true;
                const originalLimpiarFormulario = window.ProgramaTejidoForm.limpiarFormulario.bind(window.ProgramaTejidoForm);
                window.ProgramaTejidoForm.limpiarFormulario = function() {
                    // Si estamos en modo prefill, no limpiar
                    if (window._prefillMode) {
                        console.log('🛡️ Modo prefill activo, evitando limpieza');
                        return;
                    }
                    // Si los campos ya fueron establecidos desde URL, no limpiarlos
                    const camposProtegidos = ['clave-modelo-input', 'tamano', 'idflog-input', 'salon-input', 'hilo-select', 'descripcion'];
                    const hayCamposProtegidos = camposProtegidos.some(id => {
                        const el = document.getElementById(id);
                        return el && camposYaEstablecidos.has(id) && el.value && el.value !== '' && el.value !== 'None' && el.value !== 'null';
                    });

                    if (hayCamposProtegidos) {
                        console.log('🛡️ Campos protegidos detectados, evitando limpieza completa');
                        // Solo limpiar campos que no están protegidos
                        const campos = [
                            'cuenta-rizo', 'calibre-rizo', 'hilo-rizo', 'nombre-modelo',
                            'calibre-trama', 'hilo-trama', 'calibre-pie', 'cuenta-pie',
                            'hilo-pie', 'ancho', 'eficiencia-std', 'velocidad-std', 'maquina',
                            'cod-color-1', 'nombre-color-1', 'cod-color-2', 'nombre-color-2',
                            'cod-color-3', 'nombre-color-3', 'cod-color-4', 'nombre-color-4',
                            'cod-color-5', 'nombre-color-5', 'cod-color-6', 'nombre-color-6',
                            'calibre-c1', 'calibre-c2', 'calibre-c3', 'calibre-c4', 'calibre-c5',
                            'hilo-c1', 'hilo-c2', 'hilo-c3', 'hilo-c4', 'hilo-c5',
                            'calendario-select', 'aplicacion-select',
                            'rasurado', 'ancho-toalla', 'largo-toalla', 'peso-crudo',
                            'luchaje', 'peine', 'no-tiras', 'repeticiones', 'medida-plano'
                        ];
                        campos.forEach(campo => {
                            if (!camposYaEstablecidos.has(campo)) {
                                const el = document.getElementById(campo);
                                if (el) el.value = '';
                            }
                        });
                        return;
                    }

                    // Si no hay campos protegidos, ejecutar limpieza normal
                    originalLimpiarFormulario();
                };
            }

            // Interceptar onSalonChange para evitar que limpie cuando estamos en modo prefill
            if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.onSalonChange && !window._onSalonChangeInterceptado) {
                window._onSalonChangeInterceptado = true;
                const originalOnSalonChange = window.ProgramaTejidoForm.onSalonChange.bind(window.ProgramaTejidoForm);
                window.ProgramaTejidoForm.onSalonChange = async function(salonTejidoId) {
                    // Si estamos en modo prefill, solo cargar telares sin limpiar
                    if (window._prefillMode) {
                        console.log('🛡️ Modo prefill activo, cargando telares sin limpiar formulario');
                        this.state.salonSeleccionado = salonTejidoId;
                        const salonInput = document.getElementById('salon-input');
                        if (salonInput && salonTejidoId) {
                            salonInput.classList.add(...ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' '));
                        }
                        if (salonTejidoId) {
                            await this.cargarTelaresPorSalon(salonTejidoId);
                        } else {
                            this.state.telaresDisponibles = [];
                        }
                        return;
                    }
                    // Si no estamos en modo prefill, ejecutar comportamiento normal
                    await originalOnSalonChange(salonTejidoId);
                };
            }
        };

        // La inicialización ya se hizo en el script anterior
        // Solo interceptar limpiarFormulario y hacer prefill

            // Intentar interceptar limpiarFormulario inmediatamente y en intervalos
            interceptarLimpiarFormulario();

            // Agregar listener al salón para habilitar botones cuando cambie manualmente
    const salonInput = document.getElementById('salon-input');
    if (salonInput) {
        salonInput.addEventListener('input', () => {
                    setTimeout(() => {
                        if (salonInput.value && salonInput.value !== '') {
                            if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.actualizarBotonesTelar) {
                                window.ProgramaTejidoForm.actualizarBotonesTelar(true);
                                console.log('✅ Botones de telar habilitados (cambio manual de salón)');
                            }
                        } else {
                            if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.actualizarBotonesTelar) {
                                window.ProgramaTejidoForm.actualizarBotonesTelar(false);
                            }
                        }
                    }, 300);
                });
            }

            // Agregar listener al hilo para cargar eficiencia/velocidad cuando cambie manualmente
            const hiloSelect = document.getElementById('hilo-select');
            if (hiloSelect) {
                hiloSelect.addEventListener('change', () => {
                    setTimeout(() => {
                        if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad) {
                            window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad();
                        }
                    }, 300);
                });
            }

            // Observer para detectar cuando se agrega una fila de telar y cargar eficiencia/velocidad
            const tbodyTelares = document.getElementById('tbodyTelares');
            if (tbodyTelares) {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.addedNodes.length > 0) {
                            // Se agregó una nueva fila, intentar cargar eficiencia/velocidad después de un delay
                            setTimeout(() => {
                                if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad) {
                                    window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad();
                                }
                            }, 500);
                        }
                    });
                });
                observer.observe(tbodyTelares, { childList: true });
            }

            // espera a que se llenen catálogos
            let tries = 0;
            const iv = setInterval(() => {
                tries++;

                // Intentar interceptar en cada intento
                interceptarLimpiarFormulario();

                if (tries > 20 || (ready() && document.getElementById('idflog-input') && document.getElementById('salon-input'))) {
                    clearInterval(iv);
                    prefill();
                }
            }, 150);
        });
})();
</script>
@endsection

