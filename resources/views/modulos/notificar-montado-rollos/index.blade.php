@extends('layouts.app')

@section('page-title', 'Notificar Montado de Rollos')

@section('content')
<div class="container mx-auto px-4 py-4">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Notificar Montado de Rollos</h1>
    </div>
</div>

<!-- Modal de Telares -->
<div id="modalTelares" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
    <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <!-- Header del Modal -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">Telares Asignados - Rollos</h2>
            <button type="button" id="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Body del Modal -->
        <div class="p-6">
            <!-- Select de Telar del Usuario -->
            <div class="mb-6">
                <label for="selectTelarOperador" class="block text-sm font-medium text-gray-700 mb-2">
                    Seleccionar Telar
                </label>
                <select id="selectTelarOperador" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">-- Seleccione un telar --</option>
                    @foreach($telaresUsuario as $telar)
                        <option value="{{ $telar->NoTelarId }}">
                            Telar {{ $telar->NoTelarId }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tabla de Datos de Producción -->
            <div id="tablaProduccionContainer" class="mb-6" style="display: none;">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Datos de Producción</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-300 rounded-lg">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Marbete</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Artículo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Tamaño</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Orden</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Telar</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Piezas</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Salón</th>
                            </tr>
                        </thead>
                        <tbody id="tablaProduccionBody" class="divide-y divide-gray-200">
                            <!-- Los datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mensaje de carga o error -->
            <div id="mensajeEstado" class="text-center text-gray-500 mb-4" style="display: none;"></div>

            <!-- Filtros de Tipo (ocultos por ahora) -->
            <div class="mb-6 flex gap-4" style="display: none;">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="checkRizo" class="form-checkbox h-5 w-5 text-blue-600 rounded" 
                        {{ $tipo === 'rizo' ? 'checked' : '' }}>
                    <span class="ml-2 text-gray-700 font-medium">Rizo</span>
                </label>
                
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="checkPie" class="form-checkbox h-5 w-5 text-blue-600 rounded"
                        {{ $tipo === 'pie' ? 'checked' : '' }}>
                    <span class="ml-2 text-gray-700 font-medium">Pie</span>
                </label>
            </div>

        </div>

        <!-- Footer del Modal -->
        <div class="flex justify-end gap-2 p-6 border-t border-gray-200">
            <button type="button" id="closeModalBtn" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                Cerrar
            </button>
            <button type="button" id="btnNotificarRollos" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors" style="display: none;">
                Notificar
            </button>
        </div>
    </div>
</div>

<!-- Modal de Detalle del Telar -->
<div id="modalDetalleTelar" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-[60] flex items-center justify-center" style="display: none;">
    <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">Detalle del Telar</h2>
            <button type="button" id="closeModalDetalle" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6" id="detalleTelarContent">
            <div class="text-center text-gray-500">Cargando...</div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-2 p-6 border-t border-gray-200">
            <button type="button" id="closeModalDetalleBtn" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                Cancelar
            </button>
            <button type="button" id="btnNotificar" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                Notificar
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modalTelares');
        const closeModal = document.getElementById('closeModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const selectTelarOperador = document.getElementById('selectTelarOperador');
        const tablaProduccionContainer = document.getElementById('tablaProduccionContainer');
        const tablaProduccionBody = document.getElementById('tablaProduccionBody');
        const mensajeEstado = document.getElementById('mensajeEstado');
        const btnNotificarRollos = document.getElementById('btnNotificarRollos');

        let ordenActual = null;
        let datosProduccion = [];

        // Mostrar modal automáticamente al cargar la página
        modal.style.display = 'flex';

        // Función para cerrar el modal principal
        function cerrarModal() {
            modal.style.display = 'none';
        }

        // Event listeners para cerrar el modal principal
        closeModal.addEventListener('click', cerrarModal);
        closeModalBtn.addEventListener('click', cerrarModal);

        // Cerrar modal al hacer clic fuera de él
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                cerrarModal();
            }
        });

        // Event listener para cambio de telar
        selectTelarOperador.addEventListener('change', async function() {
            const noTelar = this.value;
            
            if (!noTelar) {
                tablaProduccionContainer.style.display = 'none';
                btnNotificarRollos.style.display = 'none';
                return;
            }

            mostrarMensaje('Buscando orden de producción...', 'info');

            try {
                // 1. Obtener orden de producción activa
                const responseOrden = await fetch(`{{ route('notificar.mont.rollos.orden.produccion') }}?no_telar=${encodeURIComponent(noTelar)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const dataOrden = await responseOrden.json();

                if (!dataOrden.success) {
                    mostrarMensaje(dataOrden.error || 'No se encontró orden activa', 'error');
                    return;
                }

                ordenActual = dataOrden.orden;
                mostrarMensaje('Cargando datos de producción...', 'info');

                // 2. Obtener datos de producción desde TOW_PRO
                const responseDatos = await fetch(`{{ route('notificar.mont.rollos.datos.produccion') }}?no_produccion=${encodeURIComponent(ordenActual.NoProduccion)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const dataDatos = await responseDatos.json();

                if (!dataDatos.success || dataDatos.datos.length === 0) {
                    mostrarMensaje(dataDatos.error || 'No se encontraron datos de producción', 'error');
                    return;
                }

                datosProduccion = dataDatos.datos;
                
                // 3. Renderizar tabla
                renderizarTablaProduccion(datosProduccion);
                
                mensajeEstado.style.display = 'none';
                tablaProduccionContainer.style.display = 'block';
                btnNotificarRollos.style.display = 'inline-block';

            } catch (error) {
                console.error('Error:', error);
                mostrarMensaje('Error al cargar los datos: ' + error.message, 'error');
            }
        });

        function mostrarMensaje(mensaje, tipo) {
            mensajeEstado.textContent = mensaje;
            mensajeEstado.className = `text-center mb-4 ${tipo === 'error' ? 'text-red-600' : tipo === 'info' ? 'text-blue-600' : 'text-gray-500'}`;
            mensajeEstado.style.display = 'block';
            tablaProduccionContainer.style.display = 'none';
            btnNotificarRollos.style.display = 'none';
        }

        function renderizarTablaProduccion(datos) {
            tablaProduccionBody.innerHTML = '';
            
            datos.forEach(dato => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';
                row.innerHTML = `
                    <td class="px-4 py-2 text-sm text-gray-900">${dato.Marbete || 'N/A'}</td>
                    <td class="px-4 py-2 text-sm text-gray-900">${dato.Articulo || 'N/A'}</td>
                    <td class="px-4 py-2 text-sm text-gray-900">${dato.Tamaño || 'N/A'}</td>
                    <td class="px-4 py-2 text-sm text-gray-900">${dato.Orden || 'N/A'}</td>
                    <td class="px-4 py-2 text-sm text-gray-900">${dato.Telar || 'N/A'}</td>
                    <td class="px-4 py-2 text-sm text-gray-900">${dato.Piezas || 'N/A'}</td>
                    <td class="px-4 py-2 text-sm text-gray-900">${dato.Salon || 'N/A'}</td>
                `;
                tablaProduccionBody.appendChild(row);
            });
        }

        // Notificar montado de rollos
        btnNotificarRollos.addEventListener('click', async function() {
            if (!ordenActual || datosProduccion.length === 0) {
                alert('No hay datos para notificar');
                return;
            }

            if (!confirm('¿Está seguro de notificar el montado de estos rollos?')) {
                return;
            }

            try {
                // Aquí puedes agregar lógica adicional para guardar en TejInventarioTelares
                const horaActual = new Date().toLocaleTimeString('es-MX', { hour12: false });
                
                alert(`Notificación registrada correctamente a las ${horaActual}`);
                
                // Limpiar formulario
                selectTelarOperador.value = '';
                tablaProduccionContainer.style.display = 'none';
                btnNotificarRollos.style.display = 'none';
                ordenActual = null;
                datosProduccion = [];
                
            } catch (error) {
                console.error('Error:', error);
                alert('Error al notificar: ' + error.message);
            }
        });
    });
</script>
@endsection
