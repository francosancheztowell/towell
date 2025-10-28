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
                                    <td class="px-2 py-1 font-medium text-gray-800"></td>
                                    <td class="px-2 py-1"></td>
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

<script>
let contadorFilasTelar = 0;

// Variable global para almacenar los telares disponibles
let telaresDisponibles = [];

// Cargar telares por salón
async function cargarTelaresPorSalon(salonTejidoId) {
    try {
        if (!salonTejidoId) {
            telaresDisponibles = [];
            return;
        }

        const response = await fetch(`/programa-tejido/telares-by-salon?salon_tejido_id=${salonTejidoId}`);
        const telares = await response.json();

        telaresDisponibles = telares;
        console.log('Telares cargados para salón', salonTejidoId, ':', telares);

        // Actualizar las filas existentes de telares
        actualizarFilasTelaresExistentes();
    } catch (error) {
        console.error('Error al cargar telares:', error);
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
        console.error('Error al obtener última fecha final:', error);
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

    // Formatear para input datetime-local (YYYY-MM-DDTHH:MM)
    const year = fechaObj.getFullYear();
    const month = String(fechaObj.getMonth() + 1).padStart(2, '0');
    const day = String(fechaObj.getDate()).padStart(2, '0');
    const hours = String(fechaObj.getHours()).padStart(2, '0');
    const minutes = String(fechaObj.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
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
            <input type="number" placeholder="0" value="" min="0" oninput="this.value = this.value.replace(/^-/, '')"
                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
        </td>
        <td class="px-3 py-2 border border-gray-300">
            <input type="datetime-local" id="fecha-inicio-${contadorFilasTelar}"
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

            console.log(`Fecha inicio llenada para telar ${noTelarId}:`, fechaFormateada);
        } else {
            console.log(`No se encontró fecha final para telar ${noTelarId}`);
            fechaInicioInput.value = '';
        }
    } else if (fechaInicioInput) {
        // Limpiar fecha si no hay telar seleccionado
        fechaInicioInput.value = '';
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
function guardar() {
    Swal.fire({
        title: '¿Guardar cambios?',
        text: 'Se guardarán los datos del programa de tejido',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280'
    }).then((result) => {
        if (result.isConfirmed) {
            // Aquí iría la lógica para guardar
            Swal.fire('¡Guardado!', 'Los datos se guardaron correctamente', 'success');
        }
    });
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

    if (btnAgregar) {
        btnAgregar.disabled = true;
        btnAgregar.className = 'px-3 py-2 bg-gray-400 text-white rounded cursor-not-allowed flex items-center gap-2 text-sm';
    }

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

        // Campos de medidas
        'ancho-toalla', 'largo-toalla', 'peso-crudo', 'luchaje', 'peine',
        'no-tiras', 'repeticiones', 'medida-plano', 'rasurado',

        // Selects
        'idflog-select', 'calendario-select', 'aplicacion-select'
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

// Cargar opciones de SalonTejidoId
// Cargar opciones de SalonTejidoId (Salon) - con cache
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

// Función auxiliar para llenar el select de salón
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

// Cargar opciones de TamanoClave (Clave Modelo)
// Variables globales para autocompletado
let salonSeleccionado = '';
let sugerenciasClaveModelo = [];
let cacheSalones = new Map(); // Cache para opciones de salón
let cacheClaveModelo = new Map(); // Cache para búsquedas de Clave Modelo

// Cargar opciones de TamanoClave (Clave Modelo) - ahora con filtro por salón y cache
async function cargarOpcionesTamanoClave(salonTejidoId = '', search = '') {
    try {
        console.log('Buscando Clave Modelo:', { salonTejidoId, search });

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

// Mostrar sugerencias de Clave Modelo
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

// Configurar autocompletado para Clave Modelo
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

// Cargar opciones de FlogsId (IdFlog)
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

// Cargar opciones de CalendarioId (Calendario)
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

// Cargar opciones de AplicacionId (Aplicación)
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

// Cargar datos relacionados cuando se seleccionan salon y clave modelo
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

// Función para calcular las 9 fórmulas
function calcularFormulas(datos) {
    console.log('🧮 CALCULANDO FÓRMULAS...');
    console.log('Datos disponibles:', datos);

    try {
        // Fórmula 1: (PesoCrudo * 1000) / (LargoToalla * AnchoToalla)
        const formula1 = (datos.PesoCrudo * 1000) / (datos.LargoToalla * datos.AnchoToalla);
        console.log('📊 FÓRMULA 1 - Peso por área:', formula1);
        console.log('   Cálculo: (' + datos.PesoCrudo + ' * 1000) / (' + datos.LargoToalla + ' * ' + datos.AnchoToalla + ') = ' + formula1);

        // Fórmula 2: FechaFinal - FechaInicio (en horas)
        // Nota: Como no tenemos FechaFinal y FechaInicio de ReqProgramaTejido, usaremos valores por defecto
        const fechaInicio = new Date();
        const fechaFinal = new Date(fechaInicio.getTime() + (24 * 60 * 60 * 1000)); // +1 día
        const formula2 = (fechaFinal - fechaInicio) / (1000 * 60 * 60); // Convertir a horas
        console.log('📊 FÓRMULA 2 - Diferencia de fechas (horas):', formula2);
        console.log('   Nota: Usando fechas por defecto ya que FechaFinal y FechaInicio no están disponibles en ReqModelosCodificados');

        // Fórmula 3: (StdDia * PesoCrudo) / 1000
        // Nota: StdDia no está disponible en ReqModelosCodificados, usando valor por defecto
        const stdDia = 1; // Valor por defecto
        const formula3 = (stdDia * datos.PesoCrudo) / 1000;
        console.log('📊 FÓRMULA 3 - StdDia * PesoCrudo / 1000:', formula3);
        console.log('   Nota: StdDia no disponible en ReqModelosCodificados, usando valor por defecto: ' + stdDia);

        // Fórmula 4: ((NoTiras * 60) / (((Total / 1) + (((Luchaje * 0.5) / 0.0254) / Repeticiones)) / VelocidadSTD)) * EficienciaSTD) * 24
        // Nota: VelocidadSTD y EficienciaSTD no están disponibles en ReqModelosCodificados
        const velocidadSTD = 100; // Valor por defecto
        const eficienciaSTD = 0.8; // Valor por defecto
        const parte1 = datos.Total / 1;
        const parte2 = (datos.Luchaje * 0.5) / 0.0254;
        const parte3 = parte2 / datos.Repeticiones;
        const parte4 = (parte1 + parte3) / velocidadSTD;
        const parte5 = (datos.NoTiras * 60) / parte4;
        const formula4 = (parte5 * eficienciaSTD) * 24;
        console.log('📊 FÓRMULA 4 - Cálculo complejo con eficiencia:', formula4);
        console.log('   Nota: VelocidadSTD y EficienciaSTD no disponibles, usando valores por defecto');

        // Fórmula 5: ((PesoCrudo * StdHrsEfect) * 24) / 1000
        // Nota: StdHrsEfect no está disponible en ReqModelosCodificados
        const stdHrsEfect = 8; // Valor por defecto
        const formula5 = ((datos.PesoCrudo * stdHrsEfect) * 24) / 1000;
        console.log('📊 FÓRMULA 5 - PesoCrudo * StdHrsEfect * 24 / 1000:', formula5);
        console.log('   Nota: StdHrsEfect no disponible, usando valor por defecto: ' + stdHrsEfect);

        // Fórmula 6: (NoTiras * 60) / (((Total / 1) + (((Luchaje * 0.5) / 0.0254) / Repeticiones)) / VelocidadSTD)
        const formula6 = (datos.NoTiras * 60) / parte4;
        console.log('📊 FÓRMULA 6 - Cálculo sin eficiencia:', formula6);

        // Fórmula 7: VelocidadSTD / 24
        const formula7 = velocidadSTD / 24;
        console.log('📊 FÓRMULA 7 - VelocidadSTD / 24:', formula7);

        // Fórmula 8: TotalPedido / (StdToaHra * EficienciaSTD)
        // Nota: TotalPedido y StdToaHra no están disponibles en ReqModelosCodificados
        const totalPedido = 1000; // Valor por defecto
        const stdToaHra = 10; // Valor por defecto
        const formula8 = totalPedido / (stdToaHra * eficienciaSTD);
        console.log('📊 FÓRMULA 8 - TotalPedido / (StdToaHra * EficienciaSTD):', formula8);
        console.log('   Nota: TotalPedido y StdToaHra no disponibles, usando valores por defecto');

        // Fórmula 9: (TotalPedido / (FechaFinal - FechaInicio)) / 24
        const formula9 = (totalPedido / formula2) / 24;
        console.log('📊 FÓRMULA 9 - TotalPedido / diferencia_fechas / 24:', formula9);

        // Resumen de todas las fórmulas
        console.log('🎯 RESUMEN DE FÓRMULAS:');
        console.log('   F1 (Peso por área):', formula1.toFixed(4));
        console.log('   F2 (Diferencia fechas):', formula2.toFixed(2), 'horas');
        console.log('   F3 (StdDia * Peso):', formula3.toFixed(4));
        console.log('   F4 (Cálculo complejo):', formula4.toFixed(4));
        console.log('   F5 (Peso * StdHrs):', formula5.toFixed(4));
        console.log('   F6 (Sin eficiencia):', formula6.toFixed(4));
        console.log('   F7 (Velocidad/24):', formula7.toFixed(4));
        console.log('   F8 (TotalPedido/Std):', formula8.toFixed(4));
        console.log('   F9 (TotalPedido/fechas):', formula9.toFixed(4));

    } catch (error) {
        console.error('❌ Error al calcular fórmulas:', error);
    }
}

// Función para llenar los campos con los datos obtenidos
function llenarCamposConDatos(datos) {
    console.log('Iniciando llenado de campos con datos:', datos);

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
        'Rasurado': 'rasurado',

        // Campos de Trama (primera columna - debajo de Calibre Trama)
        'CodColorTrama': 'cod-color-1',
        'ColorTrama': 'nombre-color-1',

        // Colores C1 (segunda columna - debajo de Calibre C2) - intercambiado con C2
        'CodColorC2': 'cod-color-2',
        'NomColorC2': 'nombre-color-2',

        // Colores C2 (tercera columna - debajo de Calibre C4) - intercambiado con C4
        'CodColorC4': 'cod-color-3',
        'NomColorC4': 'nombre-color-3',

        // Colores C3 (primera columna - segunda fila) - intercambiado con C1
        'CodColorC1': 'cod-color-4',
        'NomColorC1': 'nombre-color-4',

        // Colores C4 (segunda columna - segunda fila) - intercambiado con C1
        'CodColorC3': 'cod-color-5',
        'NomColorC3': 'nombre-color-5',

        // Colores C5 (tercera columna - segunda fila)
        'CodColorC5': 'cod-color-6',
        'NomColorC5': 'nombre-color-6',

        // Trama - campos correctos de la tabla
        'CalibreTrama': 'calibre-trama',
        'CalibreTrama2': 'calibre-trama', // Campo alternativo
        'FibraId': 'hilo-trama', // Este es el campo correcto para Hilo Trama

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

        // Medidas y especificaciones (nombres exactos de la tabla)
        'AnchoToalla': 'ancho-toalla',
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
        'MedidaPlano': 'medida-plano'
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
            // Agregar ring azul a los campos llenados
            elemento.classList.add('ring-2', 'ring-blue-500');
            camposLlenados++;

            // Si es un select, disparar el evento change
            if (elemento.tagName === 'SELECT') {
                elemento.dispatchEvent(new Event('change'));
            }

            console.log(` Campo llenado: ${campoDB} -> ${campoInput} = ${valor} (original: ${valorOriginal})`);
        } else if (elemento) {
            console.log(` Campo NO llenado: ${campoDB} -> ${campoInput} (valor: ${valorOriginal}, elemento existe: ${!!elemento})`);
        } else {
            console.log(` Elemento no encontrado: ${campoInput} para campo ${campoDB}`);
        }
    });

    console.log(`Campos llenados automáticamente: ${camposLlenados} campos`);

    // Calcular y mostrar las fórmulas
    calcularFormulas(datos);

    // Habilitar botones de telar cuando se cargan los datos
    habilitarBotonesTelar();

    // Los campos de salón y clave modelo NUNCA se deshabilitan
    console.log('Campos principales (salón y clave modelo) permanecen habilitados');
}

// Función para cargar datos en modo edición
function cargarDatosEdicion() {
    @if(isset($modoEdicion) && $modoEdicion && isset($registro))
        const registro = @json($registro);

        console.log('Cargando datos de edición:', registro);

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

// Inicializar cuando se carga la página
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
    cargarOpcionesFlogsId();
    cargarOpcionesCalendarioId();
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
            console.log('TamanoClave seleccionado:', tamanoClave);

            // Cargar datos relacionados SOLO si ambos campos están seleccionados
            if (salonSeleccionado && tamanoClave) {
                console.log('Ambos campos seleccionados, cargando datos...');
                cargarDatosRelacionados(salonSeleccionado, tamanoClave);
            } else {
                console.log('Falta seleccionar salón o clave modelo');
            }
        } else {
            // Quitar ring azul si está vacío
            this.classList.remove('ring-2', 'ring-blue-500');
        }
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
    });

    // Event listener para cuando se selecciona una aplicación
    document.getElementById('aplicacion-select').addEventListener('change', function() {
        const aplicacionId = this.value;
        if (aplicacionId) {
            console.log('AplicacionId seleccionado:', aplicacionId);
            // Aquí puedes agregar lógica adicional si necesitas cargar datos relacionados por AplicacionId
        }
    });
});
</script>
@endsection
