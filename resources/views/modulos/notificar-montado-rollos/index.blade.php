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
            <!-- Filtros de Tipo -->
            <div class="mb-6 flex gap-4">
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

            <!-- Tabla de Telares -->
            <div class="overflow-x-auto max-h-96">
                <table class="min-w-full bg-white border border-gray-300 rounded-lg">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider border-b">
                                Telar
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider border-b">
                                Tipo
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider border-b">
                                Acción
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($telares as $telar)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $telar->no_telar }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $telar->tipo === 'rizo' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                        {{ ucfirst($telar->tipo) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button type="button" 
                                        onclick="verDetalleTelar('{{ $telar->no_telar }}', '{{ $telar->tipo }}')"
                                        class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-xs">
                                        Ver Detalle
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No hay telares asignados o no coinciden con el filtro seleccionado
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer del Modal -->
        <div class="flex justify-end p-6 border-t border-gray-200">
            <button type="button" id="closeModalBtn" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                Cerrar
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
        const checkRizo = document.getElementById('checkRizo');
        const checkPie = document.getElementById('checkPie');

        const modalDetalle = document.getElementById('modalDetalleTelar');
        const closeModalDetalle = document.getElementById('closeModalDetalle');
        const closeModalDetalleBtn = document.getElementById('closeModalDetalleBtn');
        const btnNotificar = document.getElementById('btnNotificar');

        let registroActual = null;

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

        // Función para aplicar filtro
        function aplicarFiltro(tipo) {
            const url = new URL(window.location.href);
            
            if (tipo) {
                url.searchParams.set('tipo', tipo);
            } else {
                url.searchParams.delete('tipo');
            }
            
            window.location.href = url.toString();
        }

        // Event listeners para los checkboxes
        checkRizo.addEventListener('change', function() {
            if (this.checked) {
                checkPie.checked = false;
                aplicarFiltro('rizo');
            } else {
                aplicarFiltro(null);
            }
        });

        checkPie.addEventListener('change', function() {
            if (this.checked) {
                checkRizo.checked = false;
                aplicarFiltro('pie');
            } else {
                aplicarFiltro(null);
            }
        });

        // Función para ver detalle del telar
        window.verDetalleTelar = async function(noTelar, tipo) {
            try {
                const response = await fetch(`{{ route('notificar.mont.rollos') }}?no_telar=${encodeURIComponent(noTelar)}&tipo=${encodeURIComponent(tipo)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.detalles) {
                    registroActual = data.detalles;
                    
                    const content = document.getElementById('detalleTelarContent');
                    content.innerHTML = `
                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b">
                                <span class="font-medium text-gray-700">Telar:</span>
                                <span class="text-gray-900">${data.detalles.no_telar}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="font-medium text-gray-700">Tipo:</span>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    ${data.detalles.tipo === 'rizo' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                                    ${data.detalles.tipo.charAt(0).toUpperCase() + data.detalles.tipo.slice(1)}
                                </span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="font-medium text-gray-700">Cuenta:</span>
                                <span class="text-gray-900">${data.detalles.cuenta || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="font-medium text-gray-700">Calibre:</span>
                                <span class="text-gray-900">${data.detalles.calibre || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="font-medium text-gray-700">Tipo Atado:</span>
                                <span class="text-gray-900">${data.detalles.tipo_atado || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="font-medium text-gray-700">No. Orden:</span>
                                <span class="text-gray-900">${data.detalles.no_orden || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="font-medium text-gray-700">No. Rollo:</span>
                                <span class="text-gray-900">${data.detalles.no_rollo || 'N/A'}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="font-medium text-gray-700">Metros:</span>
                                <span class="text-gray-900">${data.detalles.metros || 'N/A'}</span>
                            </div>
                            ${data.detalles.horaParo ? `
                            <div class="flex justify-between py-2 border-b">
                                <span class="font-medium text-gray-700">Hora Paro:</span>
                                <span class="text-red-600 font-semibold">${data.detalles.horaParo}</span>
                            </div>
                            ` : ''}
                        </div>
                    `;

                    modalDetalle.style.display = 'flex';
                } else {
                    alert('No se encontraron detalles para este telar');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al cargar los detalles del telar');
            }
        };

        // Cerrar modal de detalle
        function cerrarModalDetalle() {
            modalDetalle.style.display = 'none';
            registroActual = null;
        }

        closeModalDetalle.addEventListener('click', cerrarModalDetalle);
        closeModalDetalleBtn.addEventListener('click', cerrarModalDetalle);

        modalDetalle.addEventListener('click', function(event) {
            if (event.target === modalDetalle) {
                cerrarModalDetalle();
            }
        });

        // Notificar
        btnNotificar.addEventListener('click', async function() {
            if (!registroActual) {
                alert('No hay registro seleccionado');
                return;
            }

            if (!confirm('¿Está seguro de notificar el montado de este rollo?')) {
                return;
            }

            try {
                const response = await fetch('{{ route('notificar.mont.rollos.notificar') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ id: registroActual.id })
                });

                const data = await response.json();

                if (data.success) {
                    alert(`Notificado correctamente a las ${data.horaParo}`);
                    cerrarModalDetalle();
                    cerrarModal();
                    // Recargar la página para actualizar los datos
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Error desconocido'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al notificar el montado del rollo');
            }
        });
    });
</script>
@endsection
