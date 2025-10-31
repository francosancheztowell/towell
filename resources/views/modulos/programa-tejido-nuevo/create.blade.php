@extends('layouts.app')

@section('page-title', isset($modoEdicion) && $modoEdicion ? 'Editar Programa de Tejido' : 'Nuevo Programa de Tejido')

@section('navbar-right')
<!-- Botón Guardar en la barra de navegación -->
<button onclick="guardar()" class="bg-blue-600 hover:bg-blue-700 flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
    <i class="fas fa-save"></i>
    Guardar
</button>
@endsection

@section('content')
<div class="w-full">
    <!-- Panel principal blanco - pegado arriba del navbar -->
    <div class="bg-white shadow-xl overflow-hidden rounded-2xl mt-1">

            <div class="p-8">
                <!-- Sección DATOS GENERALES - Estructura de cuadrícula exacta -->
                <div class="mb-8">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <tbody>
                                <!-- Fila 1 -->
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
                                    <td class="px-2 py-1"><input type="text" id="calibre-c2" placeholder="Ingrese calibre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre C4</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-c4" placeholder="Ingrese calibre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                </tr>

                                <!-- Fila 2 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Clave Modelo</td>
                                    <td class="px-2 py-1 relative">
                                        <input type="text" id="clave-modelo-input" placeholder="Escriba para buscar..." class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs">
                                        <div id="clave-modelo-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-b shadow-lg hidden max-h-40 overflow-y-auto"></div>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cuenta Rizo</td>
                                    <td class="px-2 py-1"><input type="text" id="cuenta-rizo" placeholder="Ingrese cuenta" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo Trama</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-trama" placeholder="Ingrese hilo" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C2</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c2" placeholder="Ingrese hilo" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C4</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c4" placeholder="Ingrese hilo" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                </tr>

                                <!-- Fila 3 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Modelo</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-modelo" placeholder="Ingrese nombre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre Rizo</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-rizo" placeholder="Ingrese calibre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-1" placeholder="Ingrese código" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-2" placeholder="Ingrese código" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-3" placeholder="Ingrese código" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                </tr>

                                <!-- Fila 4 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Tamaño</td>
                                    <td class="px-2 py-1"><input type="text" id="tamano" placeholder="Ingrese tamaño" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo Rizo</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-rizo" placeholder="Ingrese hilo" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-1" placeholder="Ingrese nombre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-2" placeholder="Ingrese nombre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-3" placeholder="Ingrese nombre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                </tr>

                                <!-- Fila 5 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo</td>
                                    <td class="px-2 py-1">
                                        <select id="hilo-select" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
                                            <option value="">Seleccione hilo...</option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cuenta Pie</td>
                                    <td class="px-2 py-1"><input type="text" id="cuenta-pie" placeholder="Ingrese cuenta" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre C1</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-c1" placeholder="Ingrese calibre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre C3</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-c3" placeholder="Ingrese calibre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre C5</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-c5" placeholder="Ingrese calibre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                </tr>

                                <!-- Fila 6 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">IdFlog</td>
                                    <td class="px-2 py-1">
                                        <select id="idflog-select" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
                                            <option value="">Seleccione IdFlog...</option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre Pie</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-pie" placeholder="Ingrese calibre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C1</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c1" placeholder="Ingrese hilo" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C3</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c3" placeholder="Ingrese hilo" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C5</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c5" placeholder="Ingrese hilo" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                </tr>

                                <!-- Campos ocultos para cálculos (Ancho, Eficiencia STD, Velocidad STD, Máquina) -->
                                <div class="hidden">
                                    <input type="number" id="ancho" placeholder="Ingrese ancho" step="0.01" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled>
                                    <input type="number" id="eficiencia-std" placeholder="Ingrese eficiencia" step="0.01" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled>
                                    <input type="number" id="velocidad-std" placeholder="Ingrese velocidad" step="0.01" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled>
                                    <input type="text" id="maquina" placeholder="Auto" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" readonly>
                                </div>

                                <!-- Fila 7 - Descripción con columnas completas -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Descripción</td>
                                    <td class="px-2 py-1"><textarea id="descripcion" rows="1" placeholder="Ingrese descripción" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs resize-none" disabled></textarea></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo Pie</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-pie" placeholder="Ingrese hilo" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-4" placeholder="Ingrese código" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-5" placeholder="Ingrese código" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-6" placeholder="Ingrese código" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                </tr>

                                <!-- Fila 8 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calendario</td>
                                    <td class="px-2 py-1">
                                        <select id="calendario-select" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
                                            <option value="">Seleccione calendario...</option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Rasurado</td>
                                    <td class="px-2 py-1"><input type="text" id="rasurado" placeholder="Ingrese rasurado" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-4" placeholder="Ingrese nombre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-5" placeholder="Ingrese nombre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-6" placeholder="Ingrese nombre" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled></td>
                                </tr>


                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Sección DATOS DEL TELAR -->
                <div class="-mt-2">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-semibold text-gray-800">Datos del telar</h3>
                        <div class="flex gap-2">
                            <button
                            title="Agregar fila"
                            id="btn-agregar-telar"
                            onclick="agregarFilaTelar()"
                            disabled
                            class="px-3 py-2 bg-gray-400 text-white rounded cursor-not-allowed flex items-center gap-2 text-sm">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button
                            title="Eliminar fila"
                            id="btn-eliminar-telar"
                            onclick="eliminarFilaTelar()"
                            disabled
                            class="px-3 py-2 bg-gray-400 text-white rounded cursor-not-allowed flex items-center gap-2 text-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>

                <!-- Campos adicionales ocultos por solicitud -->

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
                                <!-- Mensaje cuando la tabla está vacía -->
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
        </div>
    </div>

    <!-- 🆕 SECCIÓN: Tabla de Líneas Diarias (solo visible después de crear) -->
    <div id="contenedor-lineas-diarias" style="display:none;" class="mt-6">
        @include('components.req-programa-tejido-line-table')
    </div>

<script>
// ============================================
// VARIABLES GLOBALES
// ============================================
let contadorFilasTelar = 0;
let telaresDisponibles = [];
let salonSeleccionado = '';
let sugerenciasClaveModelo = [];
let cacheSalones = new Map();
let cacheClaveModelo = new Map();

// 🔴 NUEVA: Variable para almacenar datos de ReqModelosCodificados
let datosModeloActual = {};

// ============================================
// FUNCIONES DE CARGA DE TELARES
// ============================================

/**
 * Cargar telares disponibles por salón
 * @param {string} salonTejidoId - ID del salón seleccionado
 */
async function cargarTelaresPorSalon(salonTejidoId) {
    try {
        if (!salonTejidoId) {
            telaresDisponibles = [];
            return;
        }

        const response = await fetch(`/programa-tejido/telares-by-salon?salon_tejido_id=${salonTejidoId}`);
        const telares = await response.json();

        telaresDisponibles = telares;

        // Actualizar las filas existentes de telares
        actualizarFilasTelaresExistentes();
    } catch (error) {
        telaresDisponibles = [];
    }
}

// Actualizar las filas existentes de telares con las nuevas opciones
function actualizarFilasTelaresExistentes() {
    const filas = document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)');

    filas.forEach(fila => {
        const selectTelar = fila.querySelector('select');
        if (selectTelar) {
            const valorActual = selectTelar.value;

            // Crear nuevas opciones
            let opcionesTelares = '<option value="">Seleccione...</option>';
            telaresDisponibles.forEach(telar => {
                opcionesTelares += `<option value="${telar}">${telar}</option>`;
            });

            // Actualizar el select
            selectTelar.innerHTML = opcionesTelares;

            // Agregar event listener para manejar selección
            selectTelar.onchange = function() {
                manejarSeleccionTelar(this);
            };

            // Restaurar el valor si aún existe en las nuevas opciones
            if (valorActual && telaresDisponibles.includes(valorActual)) {
                selectTelar.value = valorActual;
            } else {
                selectTelar.value = '';
            }
        }
    });
}

// Función para obtener la última fecha final del telar
async function obtenerUltimaFechaFinalTelar(noTelarId) {
    try {
        if (!salonSeleccionado || !noTelarId) {
            return null;
        }

        const response = await fetch(`/programa-tejido/ultima-fecha-final-telar?salon_tejido_id=${salonSeleccionado}&no_telar_id=${noTelarId}`);
        const data = await response.json();

        return data.ultima_fecha_final;
    } catch (error) {
        return null;
    }
}

// Función para formatear fecha para input datetime-local
function formatearFechaParaInput(fecha) {
    if (!fecha) return '';

    // Si la fecha viene en formato ISO, convertirla
    const fechaObj = new Date(fecha);

    // Verificar si es una fecha válida
    if (isNaN(fechaObj.getTime())) {
        return '';
    }

    // Formatear para input datetime-local con segundos (YYYY-MM-DDTHH:MM:SS)
    const year = fechaObj.getFullYear();
    const month = String(fechaObj.getMonth() + 1).padStart(2, '0');
    const day = String(fechaObj.getDate()).padStart(2, '0');
    const hours = String(fechaObj.getHours()).padStart(2, '0');
    const minutes = String(fechaObj.getMinutes()).padStart(2, '0');
    const seconds = String(fechaObj.getSeconds()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
}

// Agregar nueva fila a la tabla de telares
function agregarFilaTelar() {
    contadorFilasTelar++;
    const tbody = document.getElementById('tbodyTelares');
    const mensajeVacio = document.getElementById('mensaje-vacio-telares');

    // Ocultar el mensaje de tabla vacía
    if (mensajeVacio) {
        mensajeVacio.classList.add('hidden');
    }

    // Crear opciones de telares dinámicamente
    let opcionesTelares = '<option value="">Seleccione...</option>';
    telaresDisponibles.forEach(telar => {
        opcionesTelares += `<option value="${telar}">${telar}</option>`;
    });

    const nuevaFila = document.createElement('tr');
    nuevaFila.id = `fila-telar-${contadorFilasTelar}`;
    nuevaFila.className = 'hover:bg-gray-50';
    nuevaFila.innerHTML = `
        <td class="px-3 py-2 border border-gray-300">
            <select class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white" onchange="manejarSeleccionTelar(this)">
                ${opcionesTelares}
            </select>
        </td>
        <td class="px-3 py-2 border border-gray-300">
            <input type="number" placeholder="0" value="" min="0" oninput="this.value = this.value.replace(/^-/, ''); calcularFechaFinalFila(this.closest('tr'));"
                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
        </td>
        <td class="px-3 py-2 border border-gray-300">
            <input type="datetime-local" id="fecha-inicio-${contadorFilasTelar}" step="1"
                    onchange="calcularFechaFinalFila(this.closest('tr'));"
                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
        </td>
        <td class="px-3 py-2 border border-gray-300">
            <input type="datetime-local" step="1"
                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
        </td>
        <td class="px-3 py-2 border border-gray-300">
            <input type="datetime-local"
                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled>
        </td>
        <td class="px-3 py-2 border border-gray-300">
            <input type="datetime-local"
                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled>
        </td>
        <td class="px-3 py-2 border border-gray-300">
            <input type="datetime-local"
                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled>
        </td>
    `;
    tbody.appendChild(nuevaFila);
}

// Función para manejar la selección de telar
async function manejarSeleccionTelar(selectElement) {
    const noTelarId = selectElement.value;
    const fila = selectElement.closest('tr');
    const fechaInicioInput = fila.querySelector('input[type="datetime-local"]');

    if (noTelarId && fechaInicioInput) {
        // Obtener la última fecha final del telar
        const ultimaFechaFinal = await obtenerUltimaFechaFinalTelar(noTelarId);

        if (ultimaFechaFinal) {
            // Formatear la fecha para el input
            const fechaFormateada = formatearFechaParaInput(ultimaFechaFinal);
            fechaInicioInput.value = fechaFormateada;

        } else {
            fechaInicioInput.value = '';
        }
    } else if (fechaInicioInput) {
        // Limpiar fecha si no hay telar seleccionado
        fechaInicioInput.value = '';
    }

    // Calcular fecha final si hay datos suficientes
    calcularFechaFinalFila(fila);
}

// Función para calcular fecha final de una fila específica (cálculo local)
function calcularFechaFinalFila(fila) {
    const selectTelar = fila.querySelector('select');
    const inputs = fila.querySelectorAll('input[type="datetime-local"], input[type="number"]');

    const noTelarId = selectTelar ? selectTelar.value : '';
    const cantidad = inputs[0] ? (inputs[0].type === 'number' ? Number(inputs[0].value || 0) : 0) : 0;
    const fechaInicio = inputs[1] ? inputs[1].value : '';

    // Obtener datos del formulario principal
    const salon = document.getElementById('salon-select').value.trim();
    const tamanoClave = document.getElementById('clave-modelo-input').value.trim();
    const hilo = document.getElementById('hilo-select').value.trim();
    const calendario = document.getElementById('calendario-select').value.trim();

    // Solo calcular si tenemos todos los datos necesarios
    if (noTelarId && cantidad > 0 && fechaInicio && salon && tamanoClave && hilo && calendario) {
        try {
            // Obtener datos del modelo desde los campos ya llenados
            const datosModelo = obtenerDatosModeloActuales();

            if (!datosModelo) {
                return;
            }

            // Obtener velocidad y eficiencia desde el formulario (valores reales cargados)
            const velocidad = parseFloat(document.getElementById('velocidad-std')?.value || 100);
            const eficiencia = parseFloat(document.getElementById('eficiencia-std')?.value || 0.80);

            // Calcular StdToaHra (Estándar Toallas/Hora al 100% de velocidad)
            // Fórmula: (NoTiras*60) / (((Total / 1) + ((Luchaje * 0.5) / 0.0254) / Repeticiones) / VelocidadSTD)
            const rep = Number(datosModelo.Repeticiones || 0);
            const velNum = Number(velocidad || 0);
            const noTirasNum = Number(datosModelo.NoTiras || 0);
            const totalNum = Number(datosModelo.Total || 0);
            const luchajeNum = Number(datosModelo.Luchaje || 0);

            if (rep <= 0 || velNum <= 0 || noTirasNum <= 0) {
                console.warn('[STD] Parámetros insuficientes para StdToaHra', { rep, velNum, noTirasNum });
                return;
            }

            const parte1 = totalNum / 1;
            const parte2 = (luchajeNum * 0.5) / 0.0254;
            const parte2_dividido = parte2 / rep;
            const parte3 = (parte1 + parte2_dividido) / velNum;
            const stdToaHra = (noTirasNum * 60) / (parte3 > 0 ? parte3 : Number.EPSILON);
            const stdToaHraR = Number(stdToaHra.toFixed(6));

            console.log('[STD] Componentes fórmula StdToaHra', {
                NoTiras: noTirasNum,
                Total: totalNum,
                Luchaje: luchajeNum,
                Repeticiones: rep,
                VelocidadSTD: velNum,
                parte1,
                parte2,
                parte2_dividido,
                numerador: (noTirasNum * 60),
                denominador: parte3,
                StdToaHra: stdToaHraR
            });

            // Calcular horas necesarias
            // HorasProd = TotalPedido / (StdToaHra * EficienciaSTD)
            const horasProd = cantidad / (stdToaHraR * eficiencia);

            // Calcular fecha final sumando horas al calendario
            const fechaFinal = sumarHorasCalendario(fechaInicio, horasProd, calendario);

            // Formatear la fecha final para el input
            const fechaFinalFormateada = formatearFechaParaInput(fechaFinal);
            const fechaFinalInput = inputs[2]; // El tercer input es fecha final

            if (fechaFinalInput) {
                fechaFinalInput.value = fechaFinalFormateada;

                // Obtener PesoCrudo del formulario
                const pesoCrudoPrograma = parseFloat(document.getElementById('peso-crudo')?.value || 0);

                // Calcular todas las fórmulas con los datos reales
                const formulas = calcularFormulas(datosModelo, velocidad, eficiencia, cantidad, fechaInicio, fechaFinal, stdToaHraR, pesoCrudoPrograma);

                console.log(`Telar ${noTelarId}: HorasProd=${horasProd.toFixed(2)}, Fecha final=${fechaFinalFormateada}`);
            }
        } catch (error) {
        }
    }
}

// Función para obtener datos del modelo actuales desde los campos del formulario
/**
 * Obtener datos del modelo actual desde la variable global
 * Estos datos vienen de ReqModelosCodificados cuando se selecciona Clave Modelo
 * Se usa para calcular fórmulas de producción
 */
function obtenerDatosModeloActuales() {
    try {
        // Usar datos globales de ReqModelosCodificados
        if (!datosModeloActual || Object.keys(datosModeloActual).length === 0) {
            console.log('No hay datos de modelo cargados');
            return null;
        }

        // Extraer todos los campos necesarios
        const noTiras = parseFloat(datosModeloActual.NoTiras || 0);
        const total = parseFloat(datosModeloActual.Total || 0);
        const luchaje = parseFloat(datosModeloActual.Luchaje || 0);
        const repeticiones = parseFloat(datosModeloActual.Repeticiones || 0);
        const pesoCrudo = parseFloat(datosModeloActual.PesoCrudo || 0);
        const largoToalla = parseFloat(datosModeloActual.LargoToalla || 0);
        const anchoToalla = parseFloat(datosModeloActual.AnchoToalla || 0);
        const peine = parseFloat(datosModeloActual.Peine || 0);

        // Verificar si tenemos al menos ALGUNOS datos
        const tieneDatos = noTiras > 0 || total > 0 || luchaje > 0 || repeticiones > 0 ||
                          pesoCrudo > 0 || largoToalla > 0 || anchoToalla > 0 || peine > 0;

        if (!tieneDatos) {
            console.log('No hay datos válidos para calcular fórmulas (todos en 0)');
            return null;
        }

        console.log('Datos del modelo obtenidos:', {
            NoTiras: noTiras,
            Total: total,
            Luchaje: luchaje,
            Repeticiones: repeticiones,
            PesoCrudo: pesoCrudo,
            LargoToalla: largoToalla,
            AnchoToalla: anchoToalla,
            Peine: peine
        });

        return {
            NoTiras: noTiras,
            Total: total,
            Luchaje: luchaje,
            Repeticiones: repeticiones,
            PesoCrudo: pesoCrudo,
            LargoToalla: largoToalla,
            AnchoToalla: anchoToalla,
            Peine: peine
        };
    } catch (error) {
        console.error('Error al obtener datos del modelo:', error);
        return null;
    }
}

function sumarHorasCalendario(fechaInicio, horas, tipoCalendario) {
    let fecha = new Date(fechaInicio);
    const diasCompletos = Math.floor(horas / 24);
    const horasRestantes = Math.floor(horas % 24);
    const minutosRestantes = Math.round((horas - Math.floor(horas)) * 60);

    console.log(' Sumando horas:', { horas, diasCompletos, horasRestantes, minutosRestantes, tipoCalendario });

    switch (tipoCalendario) {
        case 'Calendario Tej1':
            // ✅ Suma directa sin restricciones
            fecha.setDate(fecha.getDate() + diasCompletos);
            fecha.setHours(fecha.getHours() + horasRestantes);
            fecha.setMinutes(fecha.getMinutes() + minutosRestantes);
            break;

        case 'Calendario Tej2':
            // ✅ Suma solo lunes a sábado (domingo no cuenta)
            for (let i = 0; i < diasCompletos; i++) {
                fecha.setDate(fecha.getDate() + 1);
                // Si es domingo (0), sumar otro día
                if (fecha.getDay() === 0) {
                    console.log('   Domingo encontrado, saltando a lunes');
                    fecha.setDate(fecha.getDate() + 1);
                }
            }
            // Suma horas y minutos, saltando domingos
            fecha = sumarHorasSinDomingo(fecha, horasRestantes, minutosRestantes);
            break;

        case 'Calendario Tej3':
            // ✅ Lunes a viernes completos, sábado solo hasta 18:29
            fecha = sumarHorasTej3(fecha, diasCompletos, horasRestantes, minutosRestantes);
            break;

        default:
            // Por defecto, suma directo
            fecha.setDate(fecha.getDate() + diasCompletos);
            fecha.setHours(fecha.getHours() + horasRestantes);
            fecha.setMinutes(fecha.getMinutes() + minutosRestantes);
            break;
    }

    const resultado = fecha.toISOString();
    return resultado;
}

/**
 * Sumar horas y minutos, saltando domingos
 * @param {Date} fecha - Fecha inicial
 * @param {number} horas - Horas a sumar
 * @param {number} minutos - Minutos a sumar
 * @returns {Date} Fecha con tiempo sumado
 */
function sumarHorasSinDomingo(fecha, horas, minutos) {
    // Sumar horas una por una
    for (let i = 0; i < horas; i++) {
        fecha.setHours(fecha.getHours() + 1);
        // Si es domingo después de sumar, ir al lunes
        if (fecha.getDay() === 0) {
            fecha.setDate(fecha.getDate() + 1);
            fecha.setHours(0, 0, 0, 0);
        }
    }

    // Sumar minutos uno por uno
    for (let i = 0; i < minutos; i++) {
        fecha.setMinutes(fecha.getMinutes() + 1);
        // Si es domingo después de sumar, ir al lunes
        if (fecha.getDay() === 0) {
            fecha.setDate(fecha.getDate() + 1);
            fecha.setHours(0, 0, 0, 0);
        }
    }

    return fecha;
}

/**
 * Tej3: Lunes a viernes completos, sábado solo hasta 18:29
 * @param {Date} fecha - Fecha inicial
 * @param {number} dias - Días a sumar
 * @param {number} horas - Horas a sumar
 * @param {number} minutos - Minutos a sumar
 * @returns {Date} Fecha con tiempo sumado
 */
function sumarHorasTej3(fecha, dias, horas, minutos) {

    // Helper: mover a lunes 07:00
    function moverALunesSiete(fechaObj) {
        // Ir al lunes
        const dia = fechaObj.getDay();
        // 6 = sábado, 0 = domingo
        if (dia === 6) {
            fechaObj.setDate(fechaObj.getDate() + 2); // sábado -> lunes
        } else if (dia === 0) {
            fechaObj.setDate(fechaObj.getDate() + 1); // domingo -> lunes
        }
        fechaObj.setHours(7, 0, 0, 0);
    }

    // Helper: ¿pasa del límite del sábado 18:29?
    function superaLimiteSabado(fechaObj) {
        return fechaObj.getDay() === 6 && (fechaObj.getHours() > 18 || (fechaObj.getHours() === 18 && fechaObj.getMinutes() > 29));
    }

    // Ajuste inicial si arrancamos en sábado después de 18:29
    if (superaLimiteSabado(fecha)) {
        moverALunesSiete(fecha);
    }

    // Suma días, saltando domingos
    for (let i = 0; i < dias; i++) {
        fecha.setDate(fecha.getDate() + 1);

        // Si es domingo (0), saltar a lunes 07:00
        if (fecha.getDay() === 0) {
            moverALunesSiete(fecha);
        }

        // Si cae en sábado después de 18:29, saltar a lunes 07:00
        if (superaLimiteSabado(fecha)) {
            moverALunesSiete(fecha);
        }
    }

    // Sumar horas una por una respetando domingo y límite sábado
    for (let i = 0; i < horas; i++) {
        fecha.setHours(fecha.getHours() + 1);

        // Si es domingo, saltar a lunes 07:00
        if (fecha.getDay() === 0) {
            moverALunesSiete(fecha);
        }
        // Si cruza sábado 18:29, saltar a lunes 07:00
        if (superaLimiteSabado(fecha)) {
            moverALunesSiete(fecha);
        }
    }

    // Sumar minutos uno por uno respetando reglas
    for (let i = 0; i < minutos; i++) {
        fecha.setMinutes(fecha.getMinutes() + 1);

        if (fecha.getDay() === 0) {
            moverALunesSiete(fecha);
            }
        if (superaLimiteSabado(fecha)) {
            moverALunesSiete(fecha);
        }
    }

    return fecha;
}

// Función para recalcular todas las fechas finales
function recalcularTodasLasFechasFinales() {
    const filas = document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)');

    for (const fila of filas) {
        calcularFechaFinalFila(fila);
    }
}

// Eliminar última fila de la tabla de telares
function eliminarFilaTelar() {
    const filas = document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)');
    const mensajeVacio = document.getElementById('mensaje-vacio-telares');

    if (filas.length > 0) {
        filas[filas.length - 1].remove();

        // Si ya no hay filas (excepto el mensaje), mostrar el mensaje
        const filasRestantes = document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)');
        if (filasRestantes.length === 0 && mensajeVacio) {
            mensajeVacio.classList.remove('hidden');
        }
    }
}

// Guardar datos
async function guardar() {
    const salon = document.getElementById('salon-select').value.trim();
    const tamanoClave = document.getElementById('clave-modelo-input').value.trim();
    const hilo = document.getElementById('hilo-select').value.trim();
    const idflog = document.getElementById('idflog-select').value.trim();
    const calendarioId = document.getElementById('calendario-select').value.trim();
    const aplicacionId = document.getElementById('aplicacion-select').value.trim();

    // Construir filas de telares desde la tabla
    const filas = Array.from(document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)'));
    const telares = filas.map((fila) => {
        const selectTelar = fila.querySelector('select');
        const inputs = fila.querySelectorAll('input[type="datetime-local"], input[type="number"]');
        return {
            no_telar_id: selectTelar ? selectTelar.value : '',
            cantidad: inputs[0] ? (inputs[0].type === 'number' ? Number(inputs[0].value || 0) : 0) : 0,
            fecha_inicio: inputs[1] ? inputs[1].value || null : null,
            fecha_final: inputs[2] ? inputs[2].value || null : null,
            compromiso_tejido: inputs[3] ? inputs[3].value || null : null,
            fecha_cliente: inputs[4] ? inputs[4].value || null : null,
            fecha_entrega: inputs[5] ? inputs[5].value || null : null,
        };
    }).filter(t => t.no_telar_id);

    if (!salon) {
        Swal.fire('Campos requeridos', 'Selecciona un salón.', 'warning');
        return;
    }
    if (telares.length === 0) {
        Swal.fire('Tabla vacía', 'Agrega al menos un telar.', 'warning');
        return;
    }

    // Recopilar todos los datos del formulario
    const datosFormulario = {};

    // Lista de todos los campos que pueden tener datos (solo los que existen en la tabla)
    const camposFormulario = [
        'cuenta-rizo', 'calibre-rizo', 'hilo-rizo', 'tamano', 'nombre-proyecto',
        'cod-color-1', 'nombre-color-1', 'cod-color-2', 'nombre-color-2', 'cod-color-3', 'nombre-color-3',
        'cod-color-4', 'nombre-color-4', 'cod-color-5', 'nombre-color-5', 'cod-color-6', 'nombre-color-6',
        'calibre-trama', 'hilo-trama', 'calibre-c1', 'hilo-c1', 'calibre-c2', 'hilo-c2', 'calibre-c3', 'hilo-c3',
        'calibre-c4', 'hilo-c4', 'calibre-c5', 'hilo-c5', 'calibre-pie', 'cuenta-pie', 'hilo-pie',
        'ancho', 'eficiencia-std', 'velocidad-std', 'maquina', 'ancho-toalla', 'peso-crudo', 'luchaje', 'peine', 'no-tiras', 'medida-plano',
        'largo-toalla', 'repeticiones', 'total-marbetes', 'cambio-repaso', 'vendedor', 'cat-calidad', 'obs5',
        'ancho-peine-trama', 'log-lucha-total', 'dobladillo-id', 'tipo-rizo', 'altura-rizo', 'obs', 'tolerancia',
        'codigo-dibujo', 'fecha-compromiso', 'clave', 'pedido', 'item-id', 'clave-modelo', 'nombre'
    ];

    // Recopilar datos de cada campo
    camposFormulario.forEach(campoId => {
        const elemento = document.getElementById(campoId);
        if (elemento && elemento.value && elemento.value.trim() !== '') {
            // Mapear el ID del campo al nombre de la columna en la base de datos
            const mapeoCampos = {
                'cuenta-rizo': 'CuentaRizo',
                'calibre-rizo': 'CalibreRizo',
                'hilo-rizo': 'FibraRizo',
                'tamano': 'InventSizeId',
                'nombre-proyecto': 'NombreProyecto',
                'nombre': 'Nombre',
                'clave-modelo': 'ClaveModelo',
                'item-id': 'ItemId',
                'tolerancia': 'Tolerancia',
                'codigo-dibujo': 'CodigoDibujo',
                'fecha-compromiso': 'FechaCompromiso',
                'clave': 'Clave',
                'pedido': 'Pedido',
                'tipo-rizo': 'TipoRizo',
                'altura-rizo': 'AlturaRizo',
                'obs': 'Obs',
                'cod-color-1': 'CodColorTrama',
                'nombre-color-1': 'ColorTrama',
                'cod-color-2': 'CodColorComb1',
                'nombre-color-2': 'NombreCC1',
                'cod-color-3': 'CodColorComb2',
                'nombre-color-3': 'NombreCC2',
                'cod-color-4': 'CodColorComb3',
                'nombre-color-4': 'NombreCC3',
                'cod-color-5': 'CodColorComb4',
                'nombre-color-5': 'NombreCC4',
                'cod-color-6': 'CodColorComb5',
                'nombre-color-6': 'NombreCC5',
                'calibre-trama': 'CalibreTrama',
                'hilo-trama': 'FibraTrama',
                'calibre-c1': 'CalibreComb12',
                'hilo-c1': 'FibraComb1',
                'calibre-c2': 'CalibreComb22',
                'hilo-c2': 'FibraComb2',
                'calibre-c3': 'CalibreComb32',
                'hilo-c3': 'FibraComb3',
                'calibre-c4': 'CalibreComb42',
                'hilo-c4': 'FibraComb4',
                'calibre-c5': 'CalibreComb52',
                'hilo-c5': 'FibraComb5',
                'calibre-pie': 'CalibrePie',
                'cuenta-pie': 'CuentaPie',
                'hilo-pie': 'FibraPie',
                'ancho': 'Ancho',
                'eficiencia-std': 'EficienciaSTD',
                'velocidad-std': 'VelocidadSTD',
                'maquina': 'Maquina',
                'ancho-toalla': 'AnchoToalla',
                'peso-crudo': 'PesoCrudo',
                'luchaje': 'Luchaje',
                'peine': 'Peine',
                'no-tiras': 'NoTiras',
                'medida-plano': 'MedidaPlano',
                'largo-toalla': 'LargoToalla',
                'repeticiones': 'Repeticiones',
                'total-marbetes': 'TotalMarbetes',
                'cambio-repaso': 'CambioRepaso',
                'vendedor': 'Vendedor',
                'cat-calidad': 'CatCalidad',
                'obs5': 'Obs5',
                'ancho-peine-trama': 'AnchoPeineTrama',
                'log-lucha-total': 'LogLuchaTotal',
                'dobladillo-id': 'DobladilloId'
            };

            const nombreColumna = mapeoCampos[campoId];
            if (nombreColumna) {
                datosFormulario[nombreColumna] = elemento.value.trim();
            }
        }
    });

    // Calcular las fórmulas antes de guardar
    const velocidad = parseFloat(document.getElementById('velocidad-std')?.value || 100);
    const eficiencia = parseFloat(document.getElementById('eficiencia-std')?.value || 0.8);
    const pesoCrudoFormulario = parseFloat(document.getElementById('peso-crudo')?.value || 0);

    // Obtener datos del modelo para las fórmulas
    const datosModelo = obtenerDatosModeloActuales();

    // Usar el primer telar para las fechas (primero en la lista)
    const primerTelar = telares[0];
    const ultimoTelar = telares[telares.length - 1];

    // Calcular TotalPedido (suma de todas las cantidades)
    const totalPedido = telares.reduce((sum, t) => sum + t.cantidad, 0);

    let formulas = {};

    if (datosModelo && primerTelar && primerTelar.fecha_inicio && ultimoTelar && ultimoTelar.fecha_final) {
        // Calcular StdToaHra primero
        const parte1 = datosModelo.Total / 1;
        const parte2 = (datosModelo.Luchaje * 0.5) / 0.0254;
        const parte2_dividido = parte2 / datosModelo.Repeticiones;
        const parte3 = (parte1 + parte2_dividido) / velocidad;
        const stdToaHra = (datosModelo.NoTiras * 60) / parte3;

        // Calcular todas las fórmulas con los datos de los telares (inicio del primero, fin del último)
        // Pasar pesoCrudoFormulario para PesoGRM2 y ProdKgDia
        formulas = calcularFormulas(datosModelo, velocidad, eficiencia, totalPedido, primerTelar.fecha_inicio, ultimoTelar.fecha_final, stdToaHra, pesoCrudoFormulario);
    }

    const payload = {
        salon_tejido_id: salon,
        tamano_clave: tamanoClave || null,
        hilo: hilo || null,
        idflog: idflog || null,
        calendario_id: calendarioId || null,
        aplicacion_id: aplicacionId || null,
        telares,
        // Incluir TODOS los campos que vienen de ReqModelosCodificados (aunque no sean visibles en el form)
        ...(datosModelo || {}),
        ...datosFormulario, // Incluir todos los datos del formulario (estos pueden sobreescribir si aplica)
        // Agregar TotalPedido y SaldoPedido
        TotalPedido: totalPedido || null,
        SaldoPedido: totalPedido || null, // Inicialmente igual a TotalPedido
        // Agregar datos del modelo (todos los campos de ReqModelosCodificados)
        NoTiras: datosModelo?.NoTiras || null,
        Peine: datosModelo?.Peine || null,
        Luchaje: datosModelo?.Luchaje || null,
        PesoCrudo: datosModelo?.PesoCrudo || null,
        AnchoToalla: datosModelo?.AnchoToalla || null,
        LargoToalla: datosModelo?.LargoToalla || null,
        Repeticiones: datosModelo?.Repeticiones || null,
        TotalMarbetes: datosModelo?.TotalMarbetes || null,
        CambioRepaso: datosModelo?.CambioRepaso || null,
        Vendedor: datosModelo?.Vendedor || null,
        CatCalidad: datosModelo?.CatCalidad || null,
        Obs5: datosModelo?.Obs5 || null,
        AnchoPeineTrama: datosModelo?.AnchoPeineTrama || null,
        LogLuchaTotal: datosModelo?.LogLuchaTotal || null,
        DobladilloId: datosModelo?.DobladilloId || null,
        TipoRizo: datosModelo?.TipoRizo || null,
        AlturaRizo: datosModelo?.AlturaRizo || null,
        Obs: datosModelo?.Obs || null,
        // NUEVO: Observaciones general al campo Observaciones
        Observaciones: (datosModelo?.Obs ?? null),
        Tolerancia: datosModelo?.Tolerancia || null,
        CodigoDibujo: datosModelo?.CodigoDibujo || null,
        FechaCompromiso: datosModelo?.FechaCompromiso || null,
        Clave: datosModelo?.Clave || null,
        Pedido: datosModelo?.Pedido || null,
        ItemId: datosModelo?.ItemId || null,
        ClaveModelo: datosModelo?.ClaveModelo || null,
        Nombre: datosModelo?.Nombre || null,
        // Extra: NombreProducto (para compatibilidad con reportes/frontend)
        NombreProducto: datosModelo?.Nombre || datosFormulario?.Nombre || null,
        // Campos de Total (Pasadas)
        Total: datosModelo?.Total || null,
        // NUEVO: PasadasTrama = Total
        PasadasTrama: datosModelo?.Total || null,
        // NUEVO: Color Pie → NombreCPie (si existe en codificados)
        NombreCPie: datosModelo?.NombreCPie || datosFormulario?.NombreCPie || null,
        // NUEVO: También enviar CodColorCtaPie si existe en codificados
        CodColorCtaPie: datosModelo?.CodColorCtaPie || null,
        // NUEVO: Plano → MedidaPlano (si existe en codificados o formulario)
        MedidaPlano: (datosModelo?.MedidaPlano ?? datosFormulario?.MedidaPlano ?? null),
        // NUEVO: FibraC2 → COLORC2 (usaremos CodColorC2)
        CodColorC2: datosModelo?.FibraComb2 || datosFormulario?.CodColorC2 || null,
        // Overrides explícitos para asegurar guardado
        NoTiras: (datosModelo?.NoTiras ?? datosFormulario?.NoTiras ?? null),
        Luchaje: (datosModelo?.Luchaje ?? datosFormulario?.Luchaje ?? null),
        ColorTrama: (datosFormulario?.ColorTrama ?? datosModelo?.ColorTrama ?? null),
        NombreCC1: (datosFormulario?.NombreCC1 ?? datosModelo?.NomColorC1 ?? datosModelo?.NombreCC1 ?? null),
        NombreCC2: (datosFormulario?.NombreCC2 ?? datosModelo?.NomColorC2 ?? datosModelo?.NombreCC2 ?? null),
        // Agregar las fórmulas calculadas (con conversión de tipos)
        PesoGRM2: formulas.PesoGRM2 ? Math.round(formulas.PesoGRM2) : null, // Redondear a entero
        DiasEficiencia: formulas.DiasEficiencia || null,
        ProdKgDia: formulas.ProdKgDia || null,
        StdDia: formulas.StdDia || null,
        ProdKgDia2: formulas.ProdKgDia2 || null,
        StdToaHra: formulas.StdToaHra || null,
        DiasJornada: formulas.DiasJornada || null,
        HorasProd: formulas.HorasProd || null,
        StdHrsEfect: formulas.StdHrsEfect || null,
        // Usar FechaInicio del primer telar y FechaFinal del último telar
        FechaInicio: primerTelar?.fecha_inicio || null,
        FechaFinal: ultimoTelar?.fecha_final || null
    };    // Log detallado de lo que se va a guardar
    console.log('Payload a guardar (programa-tejido):', payload);
    try {
        Swal.fire({ title:'Guardando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        const resp = await fetch('/planeacion/programa-tejido', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(payload)
        });
        const data = await (resp.ok ? resp.json() : resp.text().then(t => { throw new Error(t || `HTTP ${resp.status}`); }));
        if (!data.success) throw new Error(data.message || 'No se pudo guardar');

        // ✅ DESPUÉS DE CREAR EXITOSAMENTE:
        // 1. Obtener el ID del primer programa creado
        const programaId = data.data && data.data[0] && data.data[0].Id;

        // 2. Mostrar tabla de líneas diarias generadas
        const contenedorLineas = document.getElementById('contenedor-lineas-diarias');
        if (contenedorLineas && programaId) {
            contenedorLineas.style.display = 'block';

            // Mostrar el wrapper de la tabla (el componente lo tiene en hidden)
            const wrapper = document.getElementById('reqpt-line-wrapper');
            if (wrapper) {
                wrapper.classList.remove('hidden');
            }

            // Cargar las líneas diarias
            if (window.loadReqProgramaTejidoLines) {
                window.loadReqProgramaTejidoLines({ programa_id: programaId });
            }
        }

        Swal.fire({ icon:'success', title:'¡Guardado!', text:'Programa de tejido creado. Líneas diarias generadas.', timer:2000, showConfirmButton:false })
            .then(() => window.location.href = '/planeacion/programa-tejido');
    } catch (err) {
        Swal.fire('Error', err.message || 'Error al guardar', 'error');
    }
}

// Cancelar y volver
function cancelar() {
    window.location.href = '/planeacion/programa-tejido';
}

// Función para habilitar los botones de telar
function habilitarBotonesTelar() {
    const btnAgregar = document.getElementById('btn-agregar-telar');
    const btnEliminar = document.getElementById('btn-eliminar-telar');

    if (btnAgregar) {
        btnAgregar.disabled = false;
        btnAgregar.className = 'px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-2 text-sm';
    }

    if (btnEliminar) {
        btnEliminar.disabled = false;
        btnEliminar.className = 'px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 flex items-center gap-2 text-sm';
    }
}

// Función para deshabilitar los botones de telar
function deshabilitarBotonesTelar() {
    const btnAgregar = document.getElementById('btn-agregar-telar');
    const btnEliminar = document.getElementById('btn-eliminar-telar');

    // Para el botón agregar, deshabilitar inicialmente
    if (btnAgregar) {
        btnAgregar.disabled = true;
        btnAgregar.className = 'px-3 py-2 bg-gray-400 text-white rounded cursor-not-allowed flex items-center gap-2 text-sm';
    }

    // Para el botón eliminar, siempre deshabilitar inicialmente
    if (btnEliminar) {
        btnEliminar.disabled = true;
        btnEliminar.className = 'px-3 py-2 bg-gray-400 text-white rounded cursor-not-allowed flex items-center gap-2 text-sm';
    }
}

// Nota: Las funciones deshabilitarCamposPrincipales y habilitarCamposPrincipales
// han sido eliminadas porque los campos de salón y clave modelo NUNCA deben deshabilitarse

// Función para limpiar todos los campos del formulario
function limpiarTodosLosCampos() {
    // Lista de todos los IDs de campos que deben limpiarse
    const camposParaLimpiar = [
        // Campos básicos
        'cuenta-rizo', 'calibre-rizo', 'hilo-rizo', 'nombre-modelo', 'tamano', 'descripcion',

        // Campos de trama
        'calibre-trama', 'hilo-trama',

        // Campos de colores C1-C5
        'cod-color-1', 'nombre-color-1', 'cod-color-2', 'nombre-color-2',
        'cod-color-3', 'nombre-color-3', 'cod-color-4', 'nombre-color-4',
        'cod-color-5', 'nombre-color-5', 'cod-color-6', 'nombre-color-6',

        // Campos de calibres C1-C5
        'calibre-c1', 'calibre-c2', 'calibre-c3', 'calibre-c4', 'calibre-c5',

        // Campos de hilos C1-C5
        'hilo-c1', 'hilo-c2', 'hilo-c3', 'hilo-c4', 'hilo-c5',

        // Campos de pie
        'calibre-pie', 'cuenta-pie', 'hilo-pie',

        // Campos adicionales
        'ancho', 'eficiencia-std', 'velocidad-std', 'maquina',

        // Campos de colores
        'cod-color-1', 'nombre-color-1', 'cod-color-2', 'nombre-color-2', 'cod-color-3', 'nombre-color-3',
        'cod-color-4', 'nombre-color-4', 'cod-color-5', 'nombre-color-5', 'cod-color-6', 'nombre-color-6',

        // Campos de medidas
        'ancho-toalla', 'largo-toalla', 'peso-crudo', 'luchaje', 'peine',
        'no-tiras', 'repeticiones', 'medida-plano', 'rasurado',

        // Selects
        'hilo-select', 'idflog-select', 'calendario-select', 'aplicacion-select'
    ];

    // Limpiar cada campo
    camposParaLimpiar.forEach(campoId => {
        const elemento = document.getElementById(campoId);
        if (elemento) {
            elemento.value = '';
            elemento.classList.remove('ring-2', 'ring-blue-500');
        }
    });

    // Deshabilitar botones de telar
    deshabilitarBotonesTelar();

    // Los campos principales (salón y clave modelo) siempre permanecen habilitados
    console.log('Todos los campos han sido limpiados, botones de telar deshabilitados y campos principales permanecen habilitados');
}

// ============================================
// FUNCIONES DE CARGA DE OPCIONES
// ============================================

/**
 * Cargar opciones de salón desde el servidor
 */
async function cargarOpcionesSalon() {
    try {
        // Verificar cache primero
        if (cacheSalones.has('salones')) {
            const opciones = cacheSalones.get('salones');
            llenarSelectSalon(opciones);
            return;
        }

        const response = await fetch('/programa-tejido/salon-options');
        const opciones = await response.json();

        // Guardar en cache
        cacheSalones.set('salones', opciones);

        llenarSelectSalon(opciones);
    } catch (error) {
        console.error('Error al cargar opciones de salon:', error);
    }
}

/**
 * Llenar el select de salón con opciones
 * @param {Array} opciones - Array de opciones de salón
 */
function llenarSelectSalon(opciones) {
    const select = document.getElementById('salon-select');
    select.innerHTML = '<option value="">Seleccione salon...</option>';

    opciones.forEach(opcion => {
        const option = document.createElement('option');
        option.value = opcion;
        option.textContent = opcion;
        select.appendChild(option);
    });
}

// ============================================
// VARIABLES GLOBALES PARA CLAVE MODELO
// ============================================

// Cargar opciones de TamanoClave (Clave Modelo) - con búsqueda y cache
/**
 * Cargar opciones de TamanoClave con filtro por salón y búsqueda
 * @param {string} salonTejidoId - ID del salón (opcional)
 * @param {string} search - Término de búsqueda (opcional)
 */
async function cargarOpcionesTamanoClave(salonTejidoId = '', search = '') {
    try {

        // Crear clave única para el cache
        const cacheKey = `${salonTejidoId}-${search}`;

        // Verificar cache primero
        if (cacheClaveModelo.has(cacheKey)) {
            const opciones = cacheClaveModelo.get(cacheKey);
            console.log('Resultado desde cache:', opciones);
            sugerenciasClaveModelo = opciones;
            mostrarSugerenciasClaveModelo(opciones);
            return;
        }

        const params = new URLSearchParams();
        if (salonTejidoId) params.append('salon_tejido_id', salonTejidoId);
        if (search) params.append('search', search);

        console.log('URL de búsqueda:', `/programa-tejido/tamano-clave-by-salon?${params}`);

        const response = await fetch(`/programa-tejido/tamano-clave-by-salon?${params}`);
        const opciones = await response.json();

        console.log('Resultado del servidor:', opciones);

        // Guardar en cache
        cacheClaveModelo.set(cacheKey, opciones);

        sugerenciasClaveModelo = opciones;
        mostrarSugerenciasClaveModelo(opciones);
    } catch (error) {
        console.error('Error al cargar opciones de TamanoClave:', error);
    }
}

/**
 * Mostrar sugerencias de Clave Modelo
 * @param {Array} sugerencias - Array de sugerencias
 */
function mostrarSugerenciasClaveModelo(sugerencias) {
    const container = document.getElementById('clave-modelo-suggestions');
    container.innerHTML = '';

    if (sugerencias.length === 0) {
        // Mostrar mensaje de "No se encontraron coincidencias"
        const div = document.createElement('div');
        div.className = 'px-2 py-1 text-gray-500 text-xs italic';
        div.textContent = 'No se encontraron coincidencias';
        container.appendChild(div);
        container.classList.remove('hidden');
        return;
    }

    sugerencias.forEach(sugerencia => {
        const div = document.createElement('div');
        div.className = 'px-2 py-1 hover:bg-blue-100 cursor-pointer text-xs';
        div.textContent = sugerencia;
        div.addEventListener('click', () => {
            const input = document.getElementById('clave-modelo-input');
            input.value = sugerencia;
            input.classList.add('ring-2', 'ring-blue-500'); // Agregar ring azul
            container.classList.add('hidden');

            // Cargar datos relacionados SOLO si ambos campos están seleccionados
            if (salonSeleccionado && sugerencia) {
                console.log('Clave modelo seleccionada desde sugerencias, cargando datos...');
                cargarDatosRelacionados(salonSeleccionado, sugerencia);
            }
        });
        container.appendChild(div);
    });

    container.classList.remove('hidden');
}

/**
 * Configurar autocompletado para el campo de Clave Modelo
 */
function configurarAutocompletadoClaveModelo() {
    const input = document.getElementById('clave-modelo-input');
    const container = document.getElementById('clave-modelo-suggestions');

    let timeoutId;
    let ultimasSugerencias = [];

    input.addEventListener('input', (e) => {
        clearTimeout(timeoutId);
        const valor = e.target.value;

        if (valor.length >= 1) { // Buscar desde la primera letra
            timeoutId = setTimeout(() => {
                cargarOpcionesTamanoClave(salonSeleccionado, valor);
            }, 150); // Reducido a 150ms para mayor velocidad
        } else {
            container.classList.add('hidden');
            ultimasSugerencias = [];
        }
    });

    input.addEventListener('focus', () => {
        if (sugerenciasClaveModelo.length > 0) {
            mostrarSugerenciasClaveModelo(sugerenciasClaveModelo);
        }
    });

    // Prevenir Enter si no hay coincidencias válidas
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();

            const valor = input.value.trim();
            if (valor === '') {
                return;
            }

            // Verificar si el valor actual coincide con alguna sugerencia
            const coincidenciaExacta = ultimasSugerencias.some(sugerencia =>
                sugerencia.toLowerCase() === valor.toLowerCase()
            );

            if (!coincidenciaExacta) {
                // Mostrar mensaje de error temporal
                const mensajeError = document.createElement('div');
                mensajeError.className = 'absolute top-full left-0 mt-1 px-2 py-1 bg-red-100 text-red-600 text-xs rounded border border-red-200 z-20';
                mensajeError.textContent = 'Seleccione una opción válida de la lista';

                // Insertar el mensaje después del input
                input.parentNode.appendChild(mensajeError);

                // Remover el mensaje después de 3 segundos
                setTimeout(() => {
                    if (mensajeError.parentNode) {
                        mensajeError.parentNode.removeChild(mensajeError);
                    }
                }, 3000);

                return;
            }

            // Si hay coincidencia, ocultar sugerencias
            container.classList.add('hidden');
        }
    });

    // Actualizar las sugerencias cuando se cargan
    const originalCargarOpciones = cargarOpcionesTamanoClave;
    cargarOpcionesTamanoClave = async function(salonTejidoId = '', search = '') {
        await originalCargarOpciones(salonTejidoId, search);
        ultimasSugerencias = sugerenciasClaveModelo;
    };

    // Ocultar sugerencias al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !container.contains(e.target)) {
            container.classList.add('hidden');
        }
    });
}

// ============================================
// FUNCIONES DE CARGA ADICIONALES DE OPCIONES
// ============================================

/**
 * Cargar opciones de Hilos desde ReqMatrizHilos
 */
async function cargarOpcionesHilos() {
    try {
        const response = await fetch('/programa-tejido/hilos-options');
        const opciones = await response.json();

        const select = document.getElementById('hilo-select');
        select.innerHTML = '<option value="">Seleccione hilo...</option>';

        opciones.forEach(opcion => {
            const option = document.createElement('option');
            option.value = opcion;
            option.textContent = opcion;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Error al cargar opciones de Hilos:', error);
    }
}

/**
 * Cargar opciones de FlogsId (IdFlog)
 */
async function cargarOpcionesFlogsId() {
    try {
        const response = await fetch('/programa-tejido/flogs-id-options');
        const opciones = await response.json();

        const select = document.getElementById('idflog-select');
        select.innerHTML = '<option value="">Seleccione IdFlog...</option>';

        opciones.forEach(opcion => {
            const option = document.createElement('option');
            option.value = opcion;
            option.textContent = opcion;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Error al cargar opciones de FlogsId:', error);
    }
}

/**
 * Cargar opciones de CalendarioId (Calendario)
 */
async function cargarOpcionesCalendarioId() {
    try {
        const response = await fetch('/programa-tejido/calendario-id-options');
        const opciones = await response.json();

        const select = document.getElementById('calendario-select');
        select.innerHTML = '<option value="">Seleccione calendario...</option>';

        opciones.forEach(opcion => {
            const option = document.createElement('option');
            option.value = opcion;
            option.textContent = opcion;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Error al cargar opciones de CalendarioId:', error);
    }
}

/**
 * Cargar opciones de AplicacionId (Aplicación)
 */
async function cargarOpcionesAplicacionId() {
    try {
        const response = await fetch('/programa-tejido/aplicacion-id-options');
        const opciones = await response.json();

        const select = document.getElementById('aplicacion-select');
        select.innerHTML = '<option value="">Seleccione aplicación...</option>';

        opciones.forEach(opcion => {
            const option = document.createElement('option');
            option.value = opcion;
            option.textContent = opcion;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Error al cargar opciones de AplicacionId:', error);
    }
}

// ============================================
// FUNCIONES DE DATOS RELACIONADOS
// ============================================

/**
 * Cargar datos relacionados cuando se seleccionan salon y clave modelo
 * @param {string} salonTejidoId - ID del salón
 * @param {string} tamanoClave - Clave del tamaño/modelo
 */
async function cargarDatosRelacionados(salonTejidoId, tamanoClave = '') {
    // Solo cargar datos si AMBOS campos están seleccionados
    if (!salonTejidoId || !tamanoClave) {
        console.log('No se cargan datos: faltan salón o clave modelo');
        return;
    }

    console.log('Cargando datos relacionados para:', { salonTejidoId, tamanoClave });

    try {
        const response = await fetch('/programa-tejido/datos-relacionados', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                salon_tejido_id: salonTejidoId,
                tamano_clave: tamanoClave
            })
        });

        const data = await response.json();
        console.log('Datos relacionados recibidos:', data);

        if (data.datos) {
            llenarCamposConDatos(data.datos);
        } else {
            console.log('No se encontraron datos relacionados');
        }

    } catch (error) {
        console.error('Error al cargar datos relacionados:', error);
    }
}

// ============================================
// FUNCIONES DE CÁLCULO DE FÓRMULAS
// ============================================

/**
 * Calcular las fórmulas de producción necesarias
 * Se llama desde calcularFechaFinalFila() después de calcular la fecha final
 * @param {Object} datosModelo - Datos de ReqModelosCodificados
 * @param {number} velocidadSTD - Velocidad estándar
 * @param {number} eficienciaSTD - Eficiencia estándar
 * @param {number} totalPedido - Cantidad total a producir (TotalPedido)
 * @param {Date} fechaInicio - Fecha de inicio
 * @param {Date} fechaFinal - Fecha de finalización
 */
/**
 * Calcular las fórmulas de producción necesarias
 * Se llama DESPUÉS de calcular la fecha final
 * @param {Object} datosModelo - Datos de ReqModelosCodificados
 * @param {number} velocidadSTD - Velocidad estándar
 * @param {number} eficienciaSTD - Eficiencia estándar
 * @param {number} totalPedido - Cantidad total a producir (TotalPedido)
 * @param {Date} fechaInicio - Fecha de inicio
 * @param {Date} fechaFinal - Fecha de finalización
 * @param {number} stdToaHra - StdToaHra ya calculado
 * @param {number} pesoCrudoPrograma - PesoCrudo desde ReqProgramaTejido (del formulario)
 */
function calcularFormulas(datosModelo, velocidadSTD, eficienciaSTD, totalPedido, fechaInicio, fechaFinal, stdToaHra, pesoCrudoPrograma) {
    try {
        const vel = parseFloat(velocidadSTD) || 100;
        const efic = parseFloat(eficienciaSTD) || 0.8;
        const cantidad = parseFloat(totalPedido) || 0;
        // Usar PesoCrudo de ReqModelosCodificados como fuente principal; si no viene, usar el del formulario
        const pesoCrudoModelo = parseFloat(datosModelo?.PesoCrudo);
        const pesoCrudo = (!isNaN(pesoCrudoModelo) && pesoCrudoModelo > 0)
            ? pesoCrudoModelo
            : (parseFloat(pesoCrudoPrograma) || 0);

        const fechaInicioDate = new Date(fechaInicio);
        const fechaFinalDate = new Date(fechaFinal);

        // Calcular diferencia en horas
        const diffMs = fechaFinalDate - fechaInicioDate;
        const diffHoras = isNaN(diffMs) ? 0 : Math.max(0, diffMs / (1000 * 60 * 60));

        const formulas = {};

        // StdToaHra ya viene calculado desde calcularFechaFinalFila()
        formulas.StdToaHra = stdToaHra;

        // 1. PesoGRM2 = (ReqProgramaTejido[PesoCrudo] * 1000) / (ReqModelosCodificados[LargoToalla] * ReqModelosCodificados[AnchoToalla])
        if (pesoCrudo > 0 && datosModelo?.LargoToalla > 0 && datosModelo?.AnchoToalla > 0) {
            formulas.PesoGRM2 = (pesoCrudo * 1000) / (datosModelo.LargoToalla * datosModelo.AnchoToalla);
        }

        // 2. DiasEficiencia en formato d.HH (días.horas) y horas crudas
        const diasEnteros = Math.floor(diffHoras / 24);
        const horasRestantes = diffHoras % 24; // horas decimales (no enteras)
        const horasEnteras = Math.floor(horasRestantes);
        const minutosRestantes = Math.round((horasRestantes - horasEnteras) * 60);

        // Formato d.HH: días.horas (sin decimales dentro de las horas)
        const diasEficienciaDH = diffHoras > 0
            ? parseFloat(`${diasEnteros}.${String(horasEnteras).padStart(2,'0')}`)
            : null;

        formulas.DiasEficiencia = diasEficienciaDH; // formato d.HH: 15.10
        formulas.DiasEficienciaHoras = diffHoras > 0 ? diffHoras : null; // horas crudas para cálculos internos

        // 3. ProdKgDia = (ReqProgramaTejido[StdDia] * ReqProgramaTejido[PesoCrudo]) / 1000
        // Primero calcular StdDia
        let stdDia = 0;
        if (stdToaHra > 0) {
            // StdToaHra ya está a 100% de velocidad → StdDia: toallas por día a 100%
            stdDia = (stdToaHra) * 24;
            formulas.StdDia = stdDia;

            console.log('[STD] Resumen StdDia', {
                NoTiras: datosModelo?.NoTiras,
                Total: datosModelo?.Total,
                Luchaje: datosModelo?.Luchaje,
                Repeticiones: datosModelo?.Repeticiones,
                VelocidadSTD: vel,
                StdToaHra: stdToaHra,
                StdDia: stdDia,
                EficienciaSTD: efic,
                StdDiaEfectivoSugerido: stdDia * efic
            });
        }

        if (stdDia > 0 && pesoCrudo > 0) {
            formulas.ProdKgDia = (stdDia * pesoCrudo) / 1000;
        }

        // 4. ProdKgDia2 = ((ReqModelosCodificados[PesoCrudo] * ReqProgramaTejido[StdHrsEfect]) * 24) / 1000
        // Primero calcular StdHrsEfect usando HORAS crudas (diffHoras)
        let stdHrsEfect = 0;
        if (diffHoras > 0) {
            // StdHrsEfect: piezas por hora efectiva (NO dividir entre 24)
            stdHrsEfect = (cantidad / diffHoras);
            formulas.StdHrsEfect = stdHrsEfect;
        }

        if (datosModelo.PesoCrudo > 0 && stdHrsEfect > 0) {
            // StdHrsEfect es por hora → multiplicar por 24 para llevar a día
            formulas.ProdKgDia2 = ((datosModelo.PesoCrudo * stdHrsEfect) * 24) / 1000;
        }

        // 5. DiasJornada = ReqProgramaTejido[VelocitadadSTD] / 24
        formulas.DiasJornada = vel / 24;

        // 6. HorasProd = ReqProgramaTejido[TotalPedido] / (ReqProgramaTejido[StdToaHra] * ReqProgramaTejido[EficienciaSTD])
        if (stdToaHra > 0) {
            formulas.HorasProd = cantidad / (stdToaHra * efic);
        }

        // Logs detallados de todas las fórmulas e insumos
        try {
            const round = (v, d=6) => (typeof v === 'number' && isFinite(v)) ? Number(v.toFixed(d)) : v;
            console.groupCollapsed('[STD] Detalle fórmulas');
            console.log('Insumos', {
                NoTiras: datosModelo?.NoTiras,
                Total: datosModelo?.Total,
                Luchaje: datosModelo?.Luchaje,
                Repeticiones: datosModelo?.Repeticiones,
                LargoToalla: datosModelo?.LargoToalla,
                AnchoToalla: datosModelo?.AnchoToalla,
                PesoCrudoModelo: datosModelo?.PesoCrudo,
                PesoCrudoFormulario: pesoCrudoPrograma,
                PesoCrudoUsado: pesoCrudo,
                VelocidadSTD: vel,
                EficienciaSTD: efic,
                TotalPedido: cantidad,
                FechaInicio: fechaInicio,
                FechaFinal: fechaFinal
            });
            console.log('Resultados crudos', {
                StdToaHra: stdToaHra,
                StdDia: stdDia,
                StdDiaEfectivo: stdDia > 0 ? stdDia * efic : null,
                ProdKgDia: formulas.ProdKgDia ?? null,
                StdHrsEfect: formulas.StdHrsEfect ?? null,
                StdHrsEfectDH: formulas.StdHrsEfectDH ?? null,
                ProdKgDia2: formulas.ProdKgDia2 ?? null,
                PesoGRM2: formulas.PesoGRM2 ?? null,
                DiasEficienciaHoras: formulas.DiasEficienciaHoras ?? null,
                DiasEficiencia_dHH: formulas.DiasEficiencia ?? null,
                DiasJornada: formulas.DiasJornada ?? null,
                HorasProd: formulas.HorasProd ?? null
            });
            console.log('Resultados redondeados', {
                StdToaHra: round(stdToaHra),
                StdDia: round(stdDia),
                StdDiaEfectivo: stdDia > 0 ? round(stdDia * efic) : null,
                ProdKgDia: formulas.ProdKgDia ? round(formulas.ProdKgDia) : null,
                StdHrsEfect: formulas.StdHrsEfect ? round(formulas.StdHrsEfect) : null,
                StdHrsEfectDH: formulas.StdHrsEfectDH ? round(formulas.StdHrsEfectDH) : null,
                ProdKgDia2: formulas.ProdKgDia2 ? round(formulas.ProdKgDia2) : null,
                PesoGRM2: formulas.PesoGRM2 ? round(formulas.PesoGRM2) : null,
                DiasEficienciaHoras: formulas.DiasEficienciaHoras ? round(formulas.DiasEficienciaHoras) : null,
                DiasEficiencia_dHH: formulas.DiasEficiencia ? round(formulas.DiasEficiencia, 2) : null,
                DiasJornada: formulas.DiasJornada ? round(formulas.DiasJornada) : null,
                HorasProd: formulas.HorasProd ? round(formulas.HorasProd) : null
            });
            console.groupEnd();
        } catch(e) {}

        console.log('Fórmulas calculadas:', formulas);
        return formulas;

    } catch (error) {
        console.error('Error al calcular fórmulas:', error);
        return {};
    }
}

// ============================================
// FUNCIONES DE EFICIENCIA Y VELOCIDAD
// ============================================

/**
 * Cargar eficiencia y velocidad estándar desde el servidor
 * Obtiene valores basados en: fibra, telar y calibre de trama
 */
async function cargarEficienciaYVelocidad() {
    console.log('🔍 Iniciando cargarEficienciaYVelocidad...');

    // Obtener referencias de elementos
    const fibraIdElement = document.getElementById('hilo-trama');
    const calibreTramaElement = document.getElementById('calibre-trama');
    const hiloSeleccionadoElement = document.getElementById('hilo-select');
    const primerTelarSelect = document.querySelector('#tbodyTelares tr:not(#mensaje-vacio-telares) select');

    // Validar que todos los elementos existan
    if (!fibraIdElement || !primerTelarSelect || !calibreTramaElement || !hiloSeleccionadoElement) {
        console.log('❌ Elementos no encontrados para cargar eficiencia y velocidad');
        return;
    }

    // Obtener valores
    const fibraId = fibraIdElement.value;
    const noTelarId = primerTelarSelect.value;
    const calibreTrama = calibreTramaElement.value;
    const hiloSeleccionado = hiloSeleccionadoElement.value;
    const fibraIdToUse = hiloSeleccionado || fibraId;

    // Validar datos requeridos
    if (!fibraIdToUse || !noTelarId || !calibreTrama) {
        console.log('❌ Faltan datos requeridos:', { fibraIdToUse, noTelarId, calibreTrama });
        return;
    }

    // Validar que calibreTrama sea un número
    const calibreTramaNum = parseFloat(calibreTrama);
    if (isNaN(calibreTramaNum)) {
        console.log('❌ Calibre de trama no es un número válido:', calibreTrama);
        return;
    }

    try {
        console.log('🔄 Parámetros para consulta:', { fibraIdToUse, noTelarId, calibreTramaNum });

        let eficienciaRedondeada = null;
        let velocidadValue = null;

        // Cargar eficiencia
        console.log('📊 Consultando eficiencia...');
        const eficienciaResponse = await fetch(
            `/programa-tejido/eficiencia-std?fibra_id=${fibraIdToUse}&no_telar_id=${noTelarId}&calibre_trama=${calibreTramaNum}`
        );

        if (!eficienciaResponse.ok) {
            throw new Error(`Error HTTP ${eficienciaResponse.status} al cargar eficiencia`);
        }

        const eficienciaData = await eficienciaResponse.json();
        if (eficienciaData.eficiencia !== null && eficienciaData.eficiencia !== undefined) {
            eficienciaRedondeada = parseFloat(eficienciaData.eficiencia).toFixed(2);
            document.getElementById('eficiencia-std').value = eficienciaRedondeada;
            document.getElementById('eficiencia-std').disabled = false;
            console.log('✅ Eficiencia cargada:', eficienciaRedondeada);
        } else {
            console.log('⚠️ No se encontró eficiencia para los parámetros dados');
            document.getElementById('eficiencia-std').value = '';
        }

        // Cargar velocidad
        console.log('🚀 Consultando velocidad...');
        const velocidadResponse = await fetch(
            `/programa-tejido/velocidad-std?fibra_id=${fibraIdToUse}&no_telar_id=${noTelarId}&calibre_trama=${calibreTramaNum}`
        );

        if (!velocidadResponse.ok) {
            throw new Error(`Error HTTP ${velocidadResponse.status} al cargar velocidad`);
        }

        const velocidadData = await velocidadResponse.json();
        if (velocidadData.velocidad !== null && velocidadData.velocidad !== undefined) {
            velocidadValue = velocidadData.velocidad;
            document.getElementById('velocidad-std').value = velocidadValue;
            document.getElementById('velocidad-std').disabled = false;
            console.log('✅ Velocidad cargada:', velocidadValue);
        } else {
            console.log('⚠️ No se encontró velocidad para los parámetros dados');
            document.getElementById('velocidad-std').value = '';
        }

        // ============================================
        // DESPUÉS DE CARGAR AMBOS VALORES
        // ============================================

        // Recalcular fechas finales después de cargar los datos
        console.log('🧮 Recalculando fechas después de cargar eficiencia y velocidad...');
        recalcularTodasLasFechasFinales();

        // Ahora calcular las fórmulas con los valores reales cargados
        console.log('🧮 Ejecutando fórmulas con valores reales...');
        const datosActuales = obtenerDatosModeloActuales();
        if (datosActuales) {
            // Pasar eficiencia y velocidad reales a la función
            calcularFormulas(datosActuales, velocidadValue, eficienciaRedondeada);
        } else {
            console.log('⚠️ No se pudieron obtener datos del modelo para calcular fórmulas');
        }

    } catch (error) {
        console.error('❌ Error al cargar eficiencia y velocidad:', error);
        Swal.fire('Error', `No se pudo cargar eficiencia y velocidad: ${error.message}`, 'error');
    }
}

/**
 * Llenar los campos del formulario con los datos obtenidos
 * @param {Object} datos - Objeto con los datos a llenar
 */
function llenarCamposConDatos(datos) {
    console.log('Iniciando llenado de campos con datos:', datos);

    // 🔴 NUEVO: Guardar datos de ReqModelosCodificados en variable global
    datosModeloActual = { ...datos };
    console.log('💾 Datos del modelo guardados en variable global:', datosModeloActual);

    // Mapear los campos de la base de datos a los inputs del formulario
    const mapeoCampos = {
        // Campos principales básicos
        'CuentaRizo': 'cuenta-rizo',
        'CalibreRizo2': 'calibre-rizo',
        'CalibreRizo': 'calibre-rizo', // Campo alternativo
        'FibraRizo': 'hilo-rizo',
        'FlogsId': 'idflog-select',
        'InventSizeId': 'tamano',
        'Nombre': 'nombre-modelo',
        'NombreProyecto': 'nombre-proyecto',
        'Nombre': 'nombre',
        'Rasurado': 'rasurado',

        // Campos de Trama (primera columna - debajo de Calibre Trama)
        'CodColorTrama': 'cod-color-1',
        'ColorTrama': 'nombre-color-1',

        // Colores C1-C5
        'CodColorC1': 'cod-color-2',
        'NomColorC1': 'nombre-color-2',
        'CodColorC2': 'cod-color-3',
        'NomColorC2': 'nombre-color-3',
        'CodColorC3': 'cod-color-4',
        'NomColorC3': 'nombre-color-4',
        'CodColorC4': 'cod-color-5',
        'NomColorC4': 'nombre-color-5',
        'CodColorC5': 'cod-color-6',
        'NomColorC5': 'nombre-color-6',

        // Trama - campos correctos de la tabla
        'CalibreTrama': 'calibre-trama',
        'FibraId': 'hilo-trama', // FibraId de ReqModelosCodificados se mapea a hilo-trama

        // Combinaciones C1-C5 - Calibres (nombres exactos de la tabla)
        'CalibreComb12': 'calibre-c1', // Calibre C1
        'CalibreComb22': 'calibre-c2', // Calibre C2
        'CalibreComb32': 'calibre-c3', // Calibre C3
        'CalibreComb42': 'calibre-c4', // Calibre C4
        'CalibreComb52': 'calibre-c5', // Calibre C5

        // Combinaciones C1-C5 - Hilos/Fibras (nombres exactos de la tabla)
        'FibraComb1': 'hilo-c1', // Hilo C1
        'FibraComb2': 'hilo-c2', // Hilo C2
        'FibraComb3': 'hilo-c3', // Hilo C3
        'FibraComb4': 'hilo-c4', // Hilo C4
        'FibraComb5': 'hilo-c5', // Hilo C5

        // Pie
        'CalibrePie': 'calibre-pie',
        'CalibrePie2': 'calibre-pie', // Campo alternativo
        'CuentaPie': 'cuenta-pie',
        'FibraPie': 'hilo-pie',

        // Campos adicionales
        'AnchoToalla': 'ancho', // AnchoToalla de ReqModelosCodificados se mapea a ancho
        'EficienciaSTD': 'eficiencia-std',
        'VelocidadSTD': 'velocidad-std',
        'Maquina': 'maquina',

        // Medidas y especificaciones (nombres exactos de la tabla)
        'LargoToalla': 'largo-toalla',
        'PesoCrudo': 'peso-crudo',
        'Luchaje': 'luchaje',
        'Peine': 'peine',
        'NoTiras': 'no-tiras',
        'Repeticiones': 'repeticiones',
        'TotalMarbetes': 'total-marbetes',
        'CambioRepaso': 'cambio-repaso',
        'Vendedor': 'vendedor',
        'CatCalidad': 'cat-calidad',
        'AnchoPeineTrama': 'ancho-peine-trama',
        'LogLuchaTotal': 'log-lucha-total',
        'MedidaPlano': 'medida-plano',

        // Campos adicionales solicitados
        'OrdenTejido': 'orden-tejido',
        'FechaTejido': 'fecha-tejido',
        'FechaCumplimiento': 'fecha-cumplimiento',
        'Prioridad': 'prioridad',
        'ClaveModelo': 'clave-modelo',
        'ItemId': 'item-id',
        'Tolerancia': 'tolerancia',
        'CodigoDibujo': 'codigo-dibujo',
        'FechaCompromiso': 'fecha-compromiso',
        'Clave': 'clave',
        'Pedido': 'pedido',
        'TipoRizo': 'tipo-rizo',
        'AlturaRizo': 'altura-rizo',
        'Obs': 'obs',
        'Repeticiones': 'repeticiones',
        'TotalMarbetes': 'total-marbetes',
        'CambioRepaso': 'cambio-repaso',
        'Vendedor': 'vendedor',
        'CatCalidad': 'cat-calidad',
        'AnchoPeineTrama': 'ancho-peine-trama',
        'LogLuchaTotal': 'log-lucha-total',
        'DobladilloId': 'dobladillo-id'
    };

    let camposLlenados = 0;

    // Función para formatear números decimales
    function formatearDecimal(valor) {
        if (valor === null || valor === undefined || valor === '') return valor;

        // Convertir a número si es string
        const numero = parseFloat(valor);

        // Si no es un número válido, devolver el valor original
        if (isNaN(numero)) return valor;

        // Formatear a máximo 2 decimales, eliminando ceros innecesarios
        return parseFloat(numero.toFixed(2)).toString();
    }

    // Llenar cada campo si existe el dato
    Object.entries(mapeoCampos).forEach(([campoDB, campoInput]) => {
        const elemento = document.getElementById(campoInput);
        const valorOriginal = datos[campoDB];


        if (elemento && valorOriginal !== undefined && valorOriginal !== null && valorOriginal !== '') {
            let valor = valorOriginal;

            // Formatear decimales para campos numéricos
            if (campoDB.includes('Calibre') || campoDB.includes('Peso') || campoDB.includes('Ancho') ||
                campoDB.includes('Largo') || campoDB.includes('Eficiencia') || campoDB.includes('Velocidad')) {
                valor = formatearDecimal(valor);
            }

            elemento.value = valor;
            // Habilitar el campo si está deshabilitado
            elemento.disabled = false;
            // Agregar ring azul a los campos llenados
            elemento.classList.add('ring-2', 'ring-blue-500');
            camposLlenados++;

            // Si es un select, disparar el evento change
            if (elemento.tagName === 'SELECT') {
                elemento.dispatchEvent(new Event('change'));
            }

    // Si se llenó calibre-trama, cargar eficiencia y velocidad después de un delay
    if (campoInput === 'calibre-trama') {
        setTimeout(() => {
            // Verificar que todos los campos necesarios estén llenos antes de cargar
            const hiloSeleccionado = document.getElementById('hilo-select')?.value;
            // Buscar el primer telar seleccionado en la tabla (dinámico)
            const primerTelarSelect = document.querySelector('#tbodyTelares tr:not(#mensaje-vacio-telares) select');
            const telarSeleccionado = primerTelarSelect?.value;
            const calibreTrama = document.getElementById('calibre-trama')?.value;

            if (hiloSeleccionado && telarSeleccionado && calibreTrama) {
                cargarEficienciaYVelocidad();
            }
        }, 200);
    }

        } else if (elemento) {
        } else {
        }
    });


    // Nota: Las fórmulas se calcularán DESPUÉS de cargar eficiencia y velocidad
    // Ver: función cargarEficienciaYVelocidad()

    // Habilitar botones de telar cuando se cargan los datos
    habilitarBotonesTelar();

    // Los campos de salón y clave modelo NUNCA se deshabilitan
    console.log('Campos principales (salón y clave modelo) permanecen habilitados');
}

function cargarDatosEdicion() {
    @if(isset($modoEdicion) && $modoEdicion && isset($registro))
        const registro = @json($registro);

        // Llenar los campos del formulario
        if (registro.SalonTejidoId) document.getElementById('salon-select').value = registro.SalonTejidoId;
        if (registro.TamanoClave) document.getElementById('clave-modelo-input').value = registro.TamanoClave;
        if (registro.FlogsId) document.getElementById('idflog-select').value = registro.FlogsId;
        if (registro.CalendarioId) document.getElementById('calendario-select').value = registro.CalendarioId;
        if (registro.AplicacionId) document.getElementById('aplicacion-select').value = registro.AplicacionId;

        // Llenar campos de texto
        if (registro.CuentaRizo) document.getElementById('cuenta-rizo').value = registro.CuentaRizo;
        if (registro.CalibreRizo) document.getElementById('calibre-rizo').value = registro.CalibreRizo;
        if (registro.CalibreTrama) document.getElementById('calibre-trama').value = registro.CalibreTrama;

        // Agregar ring azul a los campos llenados
        document.getElementById('salon-select').classList.add('ring-2', 'ring-blue-500');
        if (registro.TamanoClave) document.getElementById('clave-modelo-input').classList.add('ring-2', 'ring-blue-500');

        salonSeleccionado = registro.SalonTejidoId;
    @endif
}

// ============================================
// INICIALIZACIÓN DEL FORMULARIO
// ============================================

/**
 * Inicializar el formulario cuando se carga la página
 */
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar mensaje de tabla vacía por defecto
    const mensajeVacio = document.getElementById('mensaje-vacio-telares');
    if (mensajeVacio) {
        mensajeVacio.classList.remove('hidden');
    }

    // Deshabilitar botones de telar al cargar la página
    deshabilitarBotonesTelar();

    // Cargar todas las opciones
    cargarOpcionesSalon();
    cargarOpcionesHilos();
    cargarOpcionesFlogsId();
    cargarOpcionesCalendarioId();

    // Validar estado inicial de botones después de cargar todo
    setTimeout(() => {
        validarHabilitacionBotones();
    }, 2000);
    cargarOpcionesAplicacionId();

    // Cargar datos en modo edición después de un pequeño delay para asegurar que los selects estén cargados
    setTimeout(() => {
        cargarDatosEdicion();
    }, 500);

    // Configurar autocompletado para Clave Modelo
    configurarAutocompletadoClaveModelo();

    // Event listener para cuando se selecciona un salon
    document.getElementById('salon-select').addEventListener('change', function() {
        const salonTejidoId = this.value;
        salonSeleccionado = salonTejidoId;

        // Agregar ring azul al select de salón
        if (salonTejidoId) {
            this.classList.add('ring-2', 'ring-blue-500');
        } else {
            this.classList.remove('ring-2', 'ring-blue-500');
        }

        // Limpiar TODOS los campos del formulario
        limpiarTodosLosCampos();

        // Reiniciar el input de Clave Modelo
        const claveModeloInput = document.getElementById('clave-modelo-input');
        claveModeloInput.value = '';
        claveModeloInput.classList.remove('ring-2', 'ring-blue-500');

        // Ocultar sugerencias
        const container = document.getElementById('clave-modelo-suggestions');
        container.classList.add('hidden');

        // Limpiar sugerencias previas y cache
        sugerenciasClaveModelo = [];
        cacheClaveModelo.clear();

        // Cargar telares para el salón seleccionado
        if (salonTejidoId) {
            cargarTelaresPorSalon(salonTejidoId);
            // Actualizar máquina cuando se cambia el salón
            actualizarMaquina();
        } else {
            telaresDisponibles = [];
        }

        // NO cargar datos automáticamente solo con el salón
        // Los datos se cargarán solo cuando ambos campos estén seleccionados
    });

    // Event listener para cuando se selecciona una clave modelo (ahora es input)
    document.getElementById('clave-modelo-input').addEventListener('change', function() {
        const tamanoClave = this.value;
        if (tamanoClave) {
            // Agregar ring azul al input de clave modelo
            this.classList.add('ring-2', 'ring-blue-500');

            // Cargar datos relacionados SOLO si ambos campos están seleccionados
            if (salonSeleccionado && tamanoClave) {
                cargarDatosRelacionados(salonSeleccionado, tamanoClave);
            } else {
            }
        } else {
            // Quitar ring azul si está vacío
            this.classList.remove('ring-2', 'ring-blue-500');
        }
    });

    // Event listener para cuando se selecciona un hilo
    document.getElementById('hilo-select').addEventListener('change', function() {
        const hilo = this.value;
        if (hilo) {
            // Agregar ring azul al select de hilo
            this.classList.add('ring-2', 'ring-blue-500');
        } else {
            // Quitar ring azul si está vacío
            this.classList.remove('ring-2', 'ring-blue-500');
        }

        // Recalcular fechas finales de todas las filas
        recalcularTodasLasFechasFinales();
    });

    // Event listener para cuando se selecciona un IdFlog
    document.getElementById('idflog-select').addEventListener('change', function() {
        const flogsId = this.value;
        if (flogsId) {
            console.log('FlogsId seleccionado:', flogsId);
            // Aquí puedes agregar lógica adicional si necesitas cargar datos relacionados por FlogsId
        }
    });

    // Event listener para cuando se selecciona un calendario
    document.getElementById('calendario-select').addEventListener('change', function() {
        const calendarioId = this.value;
        if (calendarioId) {
            console.log('CalendarioId seleccionado:', calendarioId);
            // Aquí puedes agregar lógica adicional si necesitas cargar datos relacionados por CalendarioId
        }

        // Recalcular fechas finales de todas las filas
        recalcularTodasLasFechasFinales();
    });

    // Event listener para cuando se selecciona una aplicación
    document.getElementById('aplicacion-select').addEventListener('change', function() {
        const aplicacionId = this.value;
        if (aplicacionId) {
            // Aquí puedes agregar lógica adicional si necesitas cargar datos relacionados por AplicacionId
        }
    });

    // Función para actualizar el campo Máquina basado en el telar seleccionado
    function actualizarMaquina() {
        // Buscar el primer telar seleccionado en la tabla
        const primerTelarSelect = document.querySelector('#tbodyTelares tr:not(#mensaje-vacio-telares) select');
        const maquinaInput = document.getElementById('maquina');

        if (primerTelarSelect && maquinaInput) {
            const telarSeleccionado = primerTelarSelect.value;
            if (telarSeleccionado) {
                // Obtener el salón seleccionado
                const salonSelect = document.getElementById('salon-select');
                const salon = salonSelect ? salonSelect.value : '';

                // Generar máquina basada en salón y número de telar
                let prefijoMaquina = '';
                if (salon === 'JACQUARD') {
                    prefijoMaquina = 'JAC';
                } else {
                    prefijoMaquina = 'SMI';
                }

                // Formato: JAC 201 o SMI 300
                maquinaInput.value = `${prefijoMaquina} ${telarSeleccionado}`;

                console.log('Máquina actualizada:', maquinaInput.value, 'para telar:', telarSeleccionado, 'salón:', salon);
            } else {
                maquinaInput.value = '';
            }
        }
    }

    // Función para validar si se pueden habilitar los botones
    function validarHabilitacionBotones() {
        // Los telares se agregan dinámicamente en la tabla, no en un select separado
        // Por eso verificamos Hilo + Calendario (el telar se selecciona al agregar filas)
        const hiloElement = document.getElementById('hilo-select');
        const calendarioElement = document.getElementById('calendario-select');
        const btnAgregar = document.getElementById('btn-agregar-telar');

        // Si algún elemento no existe, no hacer nada
        if (!hiloElement || !calendarioElement || !btnAgregar) {
            console.log('⚠️ Elementos no disponibles para validación de botones:', {
                hiloElement: !!hiloElement,
                calendarioElement: !!calendarioElement,
                btnAgregar: !!btnAgregar
            });
            return;
        }

        const hiloSeleccionado = hiloElement.value;
        const calendarioSeleccionado = calendarioElement.value;

        // Verificar si los campos requeridos están llenos
        // Nota: El telar se selecciona dinámicamente en la tabla
        const camposCompletos = hiloSeleccionado && calendarioSeleccionado;

        if (btnAgregar) {
            if (camposCompletos) {
                btnAgregar.disabled = false;
                btnAgregar.className = 'px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-2 text-sm';
            } else {
                btnAgregar.disabled = true;
                btnAgregar.className = 'px-3 py-2 bg-gray-400 text-white rounded cursor-not-allowed flex items-center gap-2 text-sm';
            }
        }
    }

    // Event listeners para validar habilitación de botones (se agregan a los existentes)
    const hiloSelectValidation = document.getElementById('hilo-select');
    if (hiloSelectValidation) {
        hiloSelectValidation.addEventListener('change', function() {
            setTimeout(() => {
                validarHabilitacionBotones();
            }, 100);
        });
    }

    // Nota: Los telares se seleccionan dinámicamente en la tabla, no en un select separado

    const calendarioSelectValidation = document.getElementById('calendario-select');
    if (calendarioSelectValidation) {
        calendarioSelectValidation.addEventListener('change', function() {
            setTimeout(() => {
                validarHabilitacionBotones();
            }, 100);
        });
    }

    // Event listeners para cargar eficiencia y velocidad cuando cambien los campos relevantes
    // (Se ejecuta cuando se selecciona hilo, telar o se llena calibre-trama)

    // Función para verificar si se pueden cargar eficiencia y velocidad
    function verificarYCargarEficienciaVelocidad() {
        const hilo = document.getElementById('hilo-select')?.value;
        const calibreTrama = document.getElementById('calibre-trama')?.value;

        // Buscar el primer telar seleccionado en la tabla
        const primerTelarSelect = document.querySelector('#tbodyTelares tr:not(#mensaje-vacio-telares) select');
        const telar = primerTelarSelect?.value;

        if (hilo && telar && calibreTrama) {
            cargarEficienciaYVelocidad();
        } else {
        }
    }

    // Event listener para hilo-select (agregar a los existentes)
    const hiloSelectEfficiency = document.getElementById('hilo-select');
    if (hiloSelectEfficiency) {
        hiloSelectEfficiency.addEventListener('change', function() {
            setTimeout(() => {
                verificarYCargarEficienciaVelocidad();
            }, 200);
        });
    }

    // Event listener para telares en la tabla (delegado para filas dinámicas)
    const tbodyTelares = document.getElementById('tbodyTelares');
    if (tbodyTelares) {
        tbodyTelares.addEventListener('change', function(e) {
            if (e.target.tagName === 'SELECT') {
                // Actualizar máquina cuando se selecciona un telar
                actualizarMaquina();
                setTimeout(() => {
                    verificarYCargarEficienciaVelocidad();
                }, 200);
            }
        });
    }

    // Event listener para calibre-trama
    const calibreTrama = document.getElementById('calibre-trama');
    if (calibreTrama) {
        calibreTrama.addEventListener('input', function() {
            setTimeout(() => {
                verificarYCargarEficienciaVelocidad();
            }, 200);
        });
    }

    // Event listeners para campos de cálculo de fecha final
    ['no-tiras', 'total-marbetes', 'luchaje', 'repeticiones'].forEach(campoId => {
        const elemento = document.getElementById(campoId);
        if (elemento) {
            elemento.addEventListener('input', function() {
                // Recalcular fechas finales cuando cambien estos campos
                recalcularTodasLasFechasFinales();
            });
        }
    });
});
</script>
@endsection
