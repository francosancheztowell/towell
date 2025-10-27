@extends('layouts.app')

@section('content')
<div class=" py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header con botones -->
        <div class="bg-white shadow-sm rounded-lg mb-4 p-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Nuevo Programa de Tejido</h1>
                    <p class="text-sm text-gray-600 mt-1">Completa los datos del registro</p>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="cancelar()"
                            class="flex items-center justify-center gap-2 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-base font-medium">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancelar
                    </button>
                    <button type="button" onclick="guardar()"
                            class="flex items-center justify-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-base font-medium shadow-md">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Guardar
                    </button>
                </div>
            </div>
        </div>

        <!-- Estructura tipo hoja de cálculo - Parte superior -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-4">
            <div class="p-4">
                <!-- Tabla principal con estructura de cuadrícula -->
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <tbody>
                            <!-- Fila 1: Salon, Clave Modelo, Nombre Modelo, Tamaño, Hilo, IdFlog, Descripción, Calendario -->
                            <tr>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-sm font-medium text-gray-800 w-32">Salon</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-sm font-medium text-gray-800 w-32">Clave Modelo</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-sm font-medium text-gray-800 w-32">Nombre Modelo</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-sm font-medium text-gray-800 w-32">Tamaño</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                            </tr>

                            <!-- Fila 2: Hilo, IdFlog, Descripción, Calendario -->
                            <tr>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-sm font-medium text-gray-800">Hilo</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-sm font-medium text-gray-800">IdFlog</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-sm font-medium text-gray-800">Descripción</td>
                                <td class="px-3 py-2 border border-gray-300" colspan="3"><textarea rows="2" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm resize-none"></textarea></td>
                            </tr>

                            <!-- Fila 3: Calendario -->
                            <tr>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-sm font-medium text-gray-800">Calendario</td>
                                <td class="px-3 py-2 border border-gray-300"><select class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm bg-white">
                                    <option value="">Seleccione...</option>
                                    <option value="1">Jornada 1</option>
                                    <option value="2">Jornada 2</option>
                                </select></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                            </tr>

                            <!-- Fila 4: Trama - Calibre, Hilo, Cod Color, Nombre Color -->
                            <tr>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-sm font-medium text-gray-800">Trama</td>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-xs font-medium text-gray-800 text-center">Calibre</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-xs font-medium text-gray-800 text-center">Hilo</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-xs font-medium text-gray-800 text-center">Cod Color</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-xs font-medium text-gray-800 text-center">Nombre Color</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-sm"></td>
                            </tr>

                            <!-- Combinaciones C1-C5 -->
                            @for($i = 1; $i <= 5; $i++)
                            <tr>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-xs font-medium text-gray-800">C{{ $i }}</td>
                                <td class="px-3 py-2 border border-gray-300 text-xs text-gray-600 text-center">Cal</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-xs"></td>
                                <td class="px-3 py-2 border border-gray-300 text-xs text-gray-600 text-center">Hilo</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-xs"></td>
                                <td class="px-3 py-2 border border-gray-300 text-xs text-gray-600 text-center">Cod</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-xs"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                            </tr>
                            @endfor

                            <!-- Rizo -->
                            <tr>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-xs font-medium text-gray-800">Rizo</td>
                                <td class="px-3 py-2 border border-gray-300 text-xs text-gray-600 text-center">Cuenta</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-xs"></td>
                                <td class="px-3 py-2 border border-gray-300 text-xs text-gray-600 text-center">Calibre</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-xs"></td>
                                <td class="px-3 py-2 border border-gray-300 text-xs text-gray-600 text-center">Hilo</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-xs"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                            </tr>

                            <!-- Pie -->
                            <tr>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-xs font-medium text-gray-800">Pie</td>
                                <td class="px-3 py-2 border border-gray-300 text-xs text-gray-600 text-center">Cuenta</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-xs"></td>
                                <td class="px-3 py-2 border border-gray-300 text-xs text-gray-600 text-center">Calibre</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-xs"></td>
                                <td class="px-3 py-2 border border-gray-300 text-xs text-gray-600 text-center">Hilo</td>
                                <td class="px-3 py-2 border border-gray-300"><input type="text" class="w-full px-2 py-1 border-0 focus:ring-2 focus:ring-blue-500 text-xs"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                            </tr>

                            <!-- Rasurado -->
                            <tr>
                                <td class="bg-green-100 px-3 py-2 border border-gray-300 text-xs font-medium text-gray-800">Rasurado</td>
                                <td class="px-3 py-2 border border-gray-300 text-center"><input type="checkbox" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                                <td class="px-3 py-2 border border-gray-300"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Panel inferior - Datos del telar -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="bg-gray-100 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">DATOS DEL TELAR</h2>
                <div class="flex gap-2">
                    <button type="button" onclick="agregarFila()"
                            class="flex items-center justify-center w-10 h-10 bg-blue-600 text-white rounded-full hover:bg-blue-700 transition-colors shadow-md">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                    <button type="button" onclick="eliminarFila()"
                            class="flex items-center justify-center w-10 h-10 bg-red-600 text-white rounded-full hover:bg-red-700 transition-colors shadow-md">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table id="tablaTelares" class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-32">TELAR</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-32">CANTIDAD</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-48">FECHA INICIO</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-48">FECHA FIN</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-48">COMPROMISO TEJIDO</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-48">FECHA CLIENTE</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-48">FECHA ENTREGA</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyTelares" class="bg-white divide-y divide-gray-200">
                            <!-- Se agregarán filas dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let contadorFilas = 0;

// Agregar nueva fila a la tabla
function agregarFila() {
    contadorFilas++;
    const tbody = document.getElementById('tbodyTelares');
    const nuevaFila = document.createElement('tr');
    nuevaFila.id = `fila-${contadorFilas}`;
    nuevaFila.className = 'hover:bg-gray-50';
    nuevaFila.innerHTML = `
        <td>
            <select class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm bg-white">
                <option value="">Seleccione...</option>
                <option value="201">201</option>
                <option value="202">202</option>
                <option value="207">207</option>
                <option value="208">208</option>
            </select>
        </td>
        <td>
            <input type="number" value="2500"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
        </td>
        <td>
            <input type="datetime-local"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
        </td>
        <td>
            <input type="datetime-local"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
        </td>
        <td>
            <input type="datetime-local"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
        </td>
        <td>
            <input type="datetime-local"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
        </td>
        <td>
            <input type="datetime-local"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
        </td>
    `;
    tbody.appendChild(nuevaFila);
}

// Eliminar fila seleccionada
function eliminarFila() {
    const filas = document.querySelectorAll('#tbodyTelares tr');
    if (filas.length > 0) {
        filas[filas.length - 1].remove();
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

// Agregar una fila inicial al cargar
document.addEventListener('DOMContentLoaded', function() {
    agregarFila();
});
</script>
@endsection
