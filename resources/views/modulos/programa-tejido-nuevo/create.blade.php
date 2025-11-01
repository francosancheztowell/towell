@extends('layouts.app')

@section('page-title', 'Nuevo Programa de Tejido')

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
// Funciones de compatibilidad
function agregarFilaTelar() { TelarManager.agregarFilaTelar(); }
function eliminarFilaTelar() { TelarManager.eliminarFilaTelar(); }

window.calcularFechaFinalFila = function(fila) {
    ProgramaTejidoForm.calcularFechaFinalFila(fila);
};

document.addEventListener('DOMContentLoaded', function() {
    ProgramaTejidoForm.init(false, null);
});
</script>
@endsection
