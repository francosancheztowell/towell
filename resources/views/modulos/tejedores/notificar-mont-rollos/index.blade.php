@extends('layouts.app')

@section('page-title', 'Notificar Montado de Rollos')

@section('content')
<div class="container mx-auto px-4 py-4">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Notificar Montado de Rollo</h1>
    </div>
</div>

<!-- Modal de Telares -->
<div id="modalTelares" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
    <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <!-- Header del Modal -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">Telares Asignados</h2>
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-4 text-center text-sm text-gray-500">
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modalTelares');
        const closeModal = document.getElementById('closeModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const checkRizo = document.getElementById('checkRizo');
        const checkPie = document.getElementById('checkPie');

        // Mostrar modal automáticamente al cargar la página
        modal.style.display = 'flex';

        // Función para cerrar el modal
        function cerrarModal() {
            modal.style.display = 'none';
        }

        // Event listeners para cerrar el modal
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
    });
</script>
@endsection