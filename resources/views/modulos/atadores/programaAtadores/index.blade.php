@extends('layouts.app')

@section('page-title', 'Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button id="btnIniciarAtado" onclick="iniciarAtado()" disabled
            class="px-2 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors duration-200 opacity-50 cursor-not-allowed">
            <i class="fas fa-play mr-1"></i> Iniciar Atado
        </button>
        {{-- <button onclick="calificaTejedor()" 
            class="px-2 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors duration-200">
            <i class="fas fa-user-check mr-1"></i> Califica Tejedor
        </button>
        <button onclick="calificaSupervisor()" 
            class="px-2 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-colors duration-200">
            <i class="fas fa-user-tie mr-1"></i> Califica Supervisor
        </button> --}}
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 h-[calc(100vh-100px)]">
    {{-- <div class=" rounded-lg shadow-md h-full flex flex-col"> --}}
        <div class="overflow-x-auto overflow-y-auto flex-1 mt-8 rounded-lg shadow-md">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-500 sticky top-0 z-10">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Fecha Req
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Turno Req
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Telar
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Tipo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            No. Julio
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Ubicación
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Metros
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Orden
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Tipo Atado
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Cuenta
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Calibre
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Hilo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Lote de Proveedor
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            No. Proveedor
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Hr. Paro
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($inventarioTelares as $item)
                        <tr class="hover:bg-blue-400 cursor-pointer transition-colors duration-150" 
                            onclick="selectRow(this, {{ $item->id }})" 
                            data-id="{{ $item->id }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->fecha ? $item->fecha->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm" data-status="{{ $item->status_proceso }}">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    @if($item->status_proceso === 'Activo') bg-gray-200 text-gray-800
                                    @elseif($item->status_proceso === 'En Proceso') bg-blue-200 text-blue-800
                                    @elseif($item->status_proceso === 'Terminado') bg-purple-200 text-purple-800
                                    @elseif($item->status_proceso === 'Calificado') bg-yellow-200 text-yellow-800
                                    @elseif($item->status_proceso === 'Autorizado') bg-green-200 text-green-800
                                    @endif">
                                    {{ $item->status_proceso }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->turno ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->no_telar ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->tipo ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->no_julio ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->localidad ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->metros ? number_format($item->metros, 2) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->no_orden ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->tipo_atado ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->cuenta ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->calibre ? number_format($item->calibre, 2) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->hilo ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->LoteProveedor ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->NoProveedor ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->horaParo ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="16" class="px-6 py-4 text-center text-sm text-gray-500">
                                No hay datos disponibles en el inventario de telares
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</div>

<!-- Modal Calificar Tejedor -->
<div id="calificarTejedor" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="flex justify-between items-center border-b p-4">
            <h2 class="text-xl font-bold text-gray-800">Calificar Tejedor</h2>
            <button onclick="closeCalificarTejedor()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <form id="formCalificarTejedor" onsubmit="submitCalificarTejedor(event)">
            <div class="p-6">
                <div class="grid grid-cols-1 gap-4">
                    <!-- Calidad de Atado -->
                    <div>
                        <label for="calidadAtado" class="block text-sm font-medium text-gray-700 mb-2">
                            Calidad de Atado <span class="text-red-500">*</span>
                        </label>
                        <select id="calidadAtado" name="calidadAtado" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 hover:border-blue-400400 hover:shadow-sm transition-all duration-200">
                            <option value="">Seleccione una calificación</option>
                            <option value="1">1 - Muy Deficiente</option>
                            <option value="2">2 - Deficiente</option>
                            <option value="3">3 - Insuficiente</option>
                            <option value="4">4 - Regular Bajo</option>
                            <option value="5">5 - Regular</option>
                            <option value="6">6 - Aceptable</option>
                            <option value="7">7 - Bueno</option>
                            <option value="8">8 - Muy Bueno</option>
                            <option value="9">9 - Excelente</option>
                            <option value="10">10 - Sobresaliente</option>
                        </select>
                    </div>
                    
                    <!-- Orden y Limpieza -->
                    <div>
                        <label for="ordenLimpieza" class="block text-sm font-medium text-gray-700 mb-2">
                            Orden y Limpieza <span class="text-red-500">*</span>
                        </label>
                        <select id="ordenLimpieza" name="ordenLimpieza" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 hover:border-blue-400400 hover:shadow-sm transition-all duration-200">
                            <option value="">Seleccione una calificación</option>
                            <option value="1">1 - Muy Deficiente</option>
                            <option value="2">2 - Deficiente</option>
                            <option value="3">3 - Regular</option>
                            <option value="4">4 - Bueno</option>
                            <option value="5">5 - Excelente</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="border-t p-4 flex justify-end gap-2">
                <button type="button" onclick="closeCalificarTejedor()" 
                    class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                    Cancelar
                </button>
                <button type="submit" 
                    class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg">
                    <i class="fas fa-save mr-1"></i> Guardar Calificación
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
let selectedRowId = null;
let selectedRow = null;

// Auto-refresh status every 5 seconds to show real-time changes
setInterval(refreshStatus, 5000);

async function refreshStatus() {
    try {
        const response = await fetch('{{ route("atadores.programa") }}');
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Update each row's status
        const currentRows = document.querySelectorAll('tbody tr[data-id]');
        currentRows.forEach(row => {
            const id = row.getAttribute('data-id');
            const newRow = doc.querySelector(`tr[data-id="${id}"]`);
            if (newRow) {
                const currentStatusCell = row.querySelector('td[data-status]');
                const newStatusCell = newRow.querySelector('td[data-status]');
                if (currentStatusCell && newStatusCell && 
                    currentStatusCell.getAttribute('data-status') !== newStatusCell.getAttribute('data-status')) {
                    currentStatusCell.innerHTML = newStatusCell.innerHTML;
                    currentStatusCell.setAttribute('data-status', newStatusCell.getAttribute('data-status'));
                }
            }
        });
    } catch (error) {
        console.error('Error refreshing status:', error);
    }
}

function selectRow(row, id) {
    // Remover selección previa
    if (selectedRow) {
        selectedRow.classList.remove('bg-blue-100', 'border-l-4', 'border-blue-500');
    }
    
    // Si se hace clic en la misma fila, deseleccionar
    if (selectedRow === row) {
        selectedRow = null;
        selectedRowId = null;
        disableIniciarButton();
        return;
    }
    
    // Seleccionar nueva fila
    selectedRow = row;
    selectedRowId = id;
    row.classList.add('bg-blue-100', 'border-l-4', 'border-blue-500');
    
    // Habilitar botón
    enableIniciarButton();
}

function enableIniciarButton() {
    const btn = document.getElementById('btnIniciarAtado');
    if (btn) {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
        btn.classList.add('cursor-pointer');
    }
}

function disableIniciarButton() {
    const btn = document.getElementById('btnIniciarAtado');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
        btn.classList.remove('cursor-pointer');
    }
}

function iniciarAtado(){
    if (!selectedRowId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Debe seleccionar un registro primero'
        });
        return;
    }
    
    // Enviar el ID seleccionado
    window.location.href = `{{ route("atadores.iniciar") }}?id=${selectedRowId}`;
}

function calificaTejedor(){
    // Abrir modal de calificación de tejedor
    document.getElementById('calificarTejedor').classList.remove('hidden');
    document.getElementById('calificarTejedor').classList.add('flex');
}

function closeCalificarTejedor(){
    document.getElementById('calificarTejedor').classList.add('hidden');
    document.getElementById('calificarTejedor').classList.remove('flex');
    document.getElementById('formCalificarTejedor').reset();
}

function submitCalificarTejedor(event){
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const calidadAtado = formData.get('calidadAtado');
    const ordenLimpieza = formData.get('ordenLimpieza');
    
    console.log('Calificación de Tejedor:', {
        calidadAtado: calidadAtado,
        ordenLimpieza: ordenLimpieza
    });
    
    // Aquí irá la conexión con la DB
    Swal.fire({
        icon: 'success',
        title: 'Calificación Guardada',
        text: `Calidad de Atado: ${calidadAtado}, Orden y Limpieza: ${ordenLimpieza}`,
        showConfirmButton: false,
        timer: 2000
    });
    
    closeCalificarTejedor();
}

function calificaSupervisor(){
    console.log("Califica Supervisor");
    // Aquí irá la lógica para calificar al supervisor
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modalTejedor = document.getElementById('calificarTejedor');
    
    if (event.target === modalTejedor) {
        closeCalificarTejedor();
    }
}
</script>
@endpush