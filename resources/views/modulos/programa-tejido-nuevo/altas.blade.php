@extends('layouts.app')

@section('page-title', 'Programar Altas Especiales')

@section('navbar-right')
<button onclick="ProgramaTejidoCRUD.guardar()" class="bg-blue-600 hover:bg-blue-700 flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
    <i class="fas fa-save"></i>
    Guardar
</button>
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
                                        <select id="salon-select" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
                                            <option value="">Seleccione salon...</option>
                                        </select>
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
                                    <td class="px-2 py-1">
                                        <select id="idflog-select" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
                                            <option value="">Seleccione IdFlog...</option>
                                        </select>
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
                        <h3 class="text-lg font-semibold text-gray-800">Datos del telar</h3>
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
        @include('components.req-programa-tejido-line-table')
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

// Interceptar el método llenarSelect DESPUÉS de que los scripts se carguen pero ANTES de init
document.addEventListener('DOMContentLoaded', function() {
    // Esperar un momento para que los scripts se carguen
    setTimeout(() => {
        if (window.ProgramaTejidoForm && window.ProgramaTejidoForm.llenarSelect) {
            const originalLlenarSelect = window.ProgramaTejidoForm.llenarSelect.bind(window.ProgramaTejidoForm);
            window.ProgramaTejidoForm.llenarSelect = function(selectId, opciones, placeholder) {
                const result = originalLlenarSelect(selectId, opciones, placeholder);

                // Si es el select de idflog y tenemos un valor de la URL, establecerlo
                if (selectId === 'idflog-select' && urlIdflog) {
                    setTimeout(() => window.establecerValorSelect('idflog-select', urlIdflog), 100);
                }

                // Si es el select de hilo y tenemos un valor de la URL, establecerlo
                if (selectId === 'hilo-select' && urlTipohilo) {
                    setTimeout(() => window.establecerValorSelect('hilo-select', urlTipohilo), 100);
                }

                return result;
            };
        }
        // Ahora inicializar el formulario
        ProgramaTejidoForm.init(false, null);
    }, 50);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const qs = new URLSearchParams(window.location.search);
    const idflog       = qs.get('idflog')       || qs.get('IDFLOG')       || '';
    const itemid       = qs.get('itemid')       || qs.get('ITEMID')       || '';
    const inventsizeid = qs.get('inventsizeid') || qs.get('INVENTSIZEID') || '';
    const cantidad     = qs.get('cantidad')     || qs.get('CANTIDAD')     || '';
    const tipohilo     = qs.get('tipohilo')     || qs.get('TIPOHILO')     || '';

    // Función robusta para rellenar idflog
    function fillIdflog() {
        const idflogSel  = document.getElementById('idflog-select');
        if (!idflogSel || !idflog) return;

        // Buscar si la opción ya existe
        const existingOption = Array.from(idflogSel.options).find(opt => opt.value === idflog);

        if (existingOption) {
            // Si existe, seleccionarla
            idflogSel.value = idflog;
            if (window.ProgramaTejidoUtils && typeof ProgramaTejidoUtils.establecerValorCampo === 'function') {
                ProgramaTejidoUtils.establecerValorCampo('idflog-select', idflog);
            }
            idflogSel.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            // Si no existe, agregarla al inicio (después del placeholder)
            const option = document.createElement('option');
            option.value = idflog;
            option.textContent = idflog;
            // Insertar después del primer option (placeholder) si existe
            if (idflogSel.options.length > 0 && idflogSel.options[0].value === '') {
                idflogSel.insertBefore(option, idflogSel.options[1] || null);
            } else {
                idflogSel.insertBefore(option, idflogSel.firstChild);
            }

            // Asegurar que esté seleccionado
            idflogSel.value = idflog;
            if (window.ProgramaTejidoUtils && typeof ProgramaTejidoUtils.establecerValorCampo === 'function') {
                ProgramaTejidoUtils.establecerValorCampo('idflog-select', idflog);
            }
            idflogSel.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // Intentar rellenar después de que todo esté cargado
    // Esperar más tiempo para que ProgramaTejidoForm.init termine de cargar opciones
    setTimeout(fillIdflog, 100);
    setTimeout(fillIdflog, 500);
    setTimeout(fillIdflog, 1000);
    setTimeout(fillIdflog, 2000);

    // Rellenar el select de hilo (usando la función global si está disponible)
    function fillHiloSelect() {
        if (window.establecerValorSelect) {
            window.establecerValorSelect('hilo-select', tipohilo);
        } else {
            const hiloSelect = document.getElementById('hilo-select');
            if (hiloSelect && tipohilo) {
                // Buscar si ya existe la opción
                const existingOption = Array.from(hiloSelect.options).find(opt => opt.value === tipohilo);
                if (existingOption) {
                    hiloSelect.value = tipohilo;
                    if (window.ProgramaTejidoUtils && typeof ProgramaTejidoUtils.establecerValorCampo === 'function') {
                        ProgramaTejidoUtils.establecerValorCampo('hilo-select', tipohilo);
                    }
                    hiloSelect.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    // Si no existe, agregar la opción
                    const option = document.createElement('option');
                    option.value = tipohilo;
                    option.textContent = tipohilo;
                    if (hiloSelect.options.length > 0 && hiloSelect.options[0].value === '') {
                        hiloSelect.insertBefore(option, hiloSelect.options[1] || null);
                    } else {
                        hiloSelect.insertBefore(option, hiloSelect.firstChild);
                    }
                    hiloSelect.value = tipohilo;
                    if (window.ProgramaTejidoUtils && typeof ProgramaTejidoUtils.establecerValorCampo === 'function') {
                        ProgramaTejidoUtils.establecerValorCampo('hilo-select', tipohilo);
                    }
                    hiloSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }
    }

    // Intentar rellenar después de delays más largos para que el intercept funcione primero
    setTimeout(fillHiloSelect, 300);
    setTimeout(fillHiloSelect, 800);
    setTimeout(fillHiloSelect, 1500);

    // Prefills ligeros (si los usas)
    if (document.getElementById('clave-modelo-input') && itemid)       document.getElementById('clave-modelo-input').value = itemid;
    if (document.getElementById('tamano') && inventsizeid)             document.getElementById('tamano').value = inventsizeid;
    if (document.getElementById('cantidad') && cantidad)               document.getElementById('cantidad').value = cantidad;
    if (document.getElementById('hilo-trama') && tipohilo)             document.getElementById('hilo-trama').value = tipohilo;

    // ——— AQUI VIENE LO IMPORTANTE: armar "concatena" = Tamaño + Clave (p.ej. MB + 7290 => MB7290) ———
    const mkConcat = (tam, clave) => {
        const norm = (s) => (s || '').toString().toUpperCase().replace(/[\s\-_]+/g, '');
        return norm(tam) + norm(clave);
    };

    // Construye concatena con lo que haya (QS o inputs ya poblados)
    const concatena = mkConcat(
        inventsizeid || document.getElementById('tamano')?.value,
        itemid       || document.getElementById('clave-modelo-input')?.value
    );

    // Llama al endpoint mandando itemid, inventsizeid y concatena (para que el backend use el mejor match)
    if ((itemid && inventsizeid) || concatena) {
        const url = new URL('{{ route("planeacion.buscar-detalle-modelo") }}', window.location.origin);
        if (itemid)       url.searchParams.set('itemid', itemid);
        if (inventsizeid) url.searchParams.set('inventsizeid', inventsizeid);
        if (concatena)    url.searchParams.set('concatena', concatena);

        fetch(url.toString())
            .then(r => r.json())
            .then(data => {
                if (!data || data.error) return;

                // ——— Mapeos de rellenado (usa los nombres reales de tus columnas) ———
                // Función mejorada que ignora valores None, NULL, y strings vacíos
                const setVal = (id, v) => {
                    const el = document.getElementById(id);
                    if (!el) return;

                    // Normalizar el valor: convertir None, NULL, null, undefined, '' a null
                    let normalizedValue = v;
                    if (v === null || v === undefined || v === '' ||
                        (typeof v === 'string' && (v.toUpperCase() === 'NONE' || v.toUpperCase() === 'NULL'))) {
                        normalizedValue = null;
                    }

                    if (normalizedValue != null) {
                        el.value = normalizedValue;
                    }
                };

                // Campos básicos del modelo
                setVal('nombre-modelo',  data.Nombre);
                setVal('descripcion',    data.NombreProyecto || data.Prioridad);
                setVal('tamano',         data.InventSizeId);
                setVal('clave-modelo-input', data.ItemId || data.ClaveModelo);

                // Trama - Según config.js: CalibreTrama → calibre-trama, FibraId → hilo-trama
                setVal('calibre-trama',  data.CalibreTrama);
                setVal('hilo-trama',     data.FibraId);

                // Rizo - Según config.js: CalibreRizo2 tiene prioridad, luego CalibreRizo
                setVal('calibre-rizo',   (data.CalibreRizo2 && data.CalibreRizo2.toString().toUpperCase() !== 'NONE' && data.CalibreRizo2.toString().toUpperCase() !== 'NULL')
                    ? data.CalibreRizo2
                    : data.CalibreRizo);
                setVal('cuenta-rizo',    data.CuentaRizo);
                setVal('hilo-rizo',      data.FibraRizo);

                // Pie - Según config.js: CalibrePie y CalibrePie2 ambos mapean a calibre-pie
                setVal('calibre-pie',    data.CalibrePie2 || data.CalibrePie);
                setVal('cuenta-pie',     data.CuentaPie);
                setVal('hilo-pie',       data.FibraPie);

                // Combinaciones C1-C5 - Según config.js: se usan los campos "2" (CalibreComb12, CalibreComb22, etc.)
                setVal('calibre-c1',     data.CalibreComb12);
                setVal('hilo-c1',        data.FibraComb1);

                setVal('calibre-c2',     data.CalibreComb22);
                setVal('hilo-c2',        data.FibraComb2);

                setVal('calibre-c3',     data.CalibreComb32);
                setVal('hilo-c3',        data.FibraComb3);

                setVal('calibre-c4',     data.CalibreComb42);
                setVal('hilo-c4',        data.FibraComb4);

                setVal('calibre-c5',     data.CalibreComb52);
                setVal('hilo-c5',        data.FibraComb5);

                // Colores - Según config.js:
                // cod-color-1 → CodColorTrama, nombre-color-1 → ColorTrama
                // cod-color-2 → CodColorC1, nombre-color-2 → NomColorC1
                // cod-color-3 → CodColorC2, nombre-color-3 → NomColorC2
                // cod-color-4 → CodColorC3, nombre-color-4 → NomColorC3
                // cod-color-5 → CodColorC4, nombre-color-5 → NomColorC4
                // cod-color-6 → CodColorC5, nombre-color-6 → NomColorC5
                setVal('cod-color-1',    data.CodColorTrama);
                setVal('nombre-color-1', data.ColorTrama);
                setVal('cod-color-2',    data.CodColorC1);
                setVal('nombre-color-2', data.NomColorC1);
                setVal('cod-color-3',    data.CodColorC2);
                setVal('nombre-color-3', data.NomColorC2);
                setVal('cod-color-4',    data.CodColorC3);
                setVal('nombre-color-4', data.NomColorC3);
                setVal('cod-color-5',    data.CodColorC4);
                setVal('nombre-color-5', data.NomColorC4);
                setVal('cod-color-6',    data.CodColorC5);
                setVal('nombre-color-6', data.NomColorC5);

                // Otros campos
                setVal('rasurado',       data.Rasurado);

                // Campos ocultos / numéricos
                const anchoHidden = document.getElementById('ancho');
                if (anchoHidden && data.AnchoToalla) {
                    anchoHidden.value = parseFloat(data.AnchoToalla) || null;
                }

                // Campos adicionales que pueden estar en la tabla pero no en el formulario visible
                // pero que pueden ser útiles para otros cálculos
                const pesoCrudo = document.getElementById('peso-crudo');
                if (pesoCrudo && data.PesoCrudo) pesoCrudo.value = data.PesoCrudo;

                const luchaje = document.getElementById('luchaje');
                if (luchaje && data.Luchaje) luchaje.value = data.Luchaje;

                const peine = document.getElementById('peine');
                if (peine && data.Peine) peine.value = data.Peine;

                const noTiras = document.getElementById('no-tiras');
                if (noTiras && data.NoTiras) noTiras.value = data.NoTiras;

                const repeticiones = document.getElementById('repeticiones');
                if (repeticiones && data.Repeticiones) repeticiones.value = data.Repeticiones;

                const medidaPlano = document.getElementById('medida-plano');
                if (medidaPlano && data.MedidaPlano) medidaPlano.value = data.MedidaPlano;

                // Eficiencia y velocidad (si están en campos ocultos)
                const eficienciaStd = document.getElementById('eficiencia-std');
                if (eficienciaStd && data.EFIC) eficienciaStd.value = data.EFIC;

                const velocidadStd = document.getElementById('velocidad-std');
                if (velocidadStd && data.VelocidadSTD) velocidadStd.value = data.VelocidadSTD;

                // Rellenar el salón si está disponible
                if (data.SalonTejidoId) {
                    // Intentar establecer el salón con delay para asegurar que el select esté cargado
                    setTimeout(() => {
                        const salonSelect = document.getElementById('salon-select');
                        if (salonSelect) {
                            // Buscar si la opción ya existe
                            const existingOption = Array.from(salonSelect.options).find(opt => opt.value === data.SalonTejidoId || opt.textContent.includes(data.SalonTejidoId));
                            if (existingOption) {
                                salonSelect.value = existingOption.value;
                                if (window.ProgramaTejidoUtils && typeof ProgramaTejidoUtils.establecerValorCampo === 'function') {
                                    ProgramaTejidoUtils.establecerValorCampo('salon-select', existingOption.value);
                                }
                                salonSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            } else {
                                // Si no existe, intentar establecerlo directamente
                                salonSelect.value = data.SalonTejidoId;
                                if (window.ProgramaTejidoUtils && typeof ProgramaTejidoUtils.establecerValorCampo === 'function') {
                                    ProgramaTejidoUtils.establecerValorCampo('salon-select', data.SalonTejidoId);
                                }
                                salonSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                    }, 200);

                    // Intentar de nuevo después de más tiempo por si el select se carga más tarde
                    setTimeout(() => {
                        const salonSelect = document.getElementById('salon-select');
                        if (salonSelect && salonSelect.value !== data.SalonTejidoId) {
                            const existingOption = Array.from(salonSelect.options).find(opt => opt.value === data.SalonTejidoId || opt.textContent.includes(data.SalonTejidoId));
                            if (existingOption) {
                                salonSelect.value = existingOption.value;
                                if (window.ProgramaTejidoUtils && typeof ProgramaTejidoUtils.establecerValorCampo === 'function') {
                                    ProgramaTejidoUtils.establecerValorCampo('salon-select', existingOption.value);
                                }
                                salonSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                    }, 800);
                }

                // Notifica al módulo si lo ocupas
                if (window.ProgramaTejidoForm?.onModeloCargado) {
                    window.ProgramaTejidoForm.onModeloCargado(data);
                }
            })
            .catch(err => console.error('Error detalle modelo:', err));
    }
});
</script>
@endsection

