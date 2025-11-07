@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Consultar Marcas')

@section('content')
<div class="container mx-auto px-4 py-6">

    @if($marcas->count() > 0)
        <!-- Botones de Acción -->
        <div class="mb-2 flex justify-start space-x-2">
            <button id="btn-editar-folio" onclick="editarFolioSeleccionado()" disabled class="px-3 py-1.5 text-sm bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-all duration-200">
                <i class="fas fa-edit mr-2"></i>
                Editar Folio
            </button>
            <button id="btn-terminar-folio" onclick="terminarFolioSeleccionado()" disabled class="px-3 py-1.5 text-sm bg-green-500 text-white rounded-lg shadow hover:bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-all duration-200">
                <i class="fas fa-check mr-2"></i>
                Terminar Marca
            </button>
        </div>

        <!-- Lista de Marcas -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-3">
            <div class="overflow-x-auto scroll-area" style="max-height: 24vh; overflow-y: auto;">
                <table class="w-full text-xs table-compact-y">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Folio</th>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Fecha</th>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Turno</th>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Usuario</th>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($marcas as $marca)
                            <tr class="hover:bg-blue-50 cursor-pointer transition-colors duration-200" data-folio="{{ $marca->Folio }}" onclick="toggleLineasPanel('{{ $marca->Folio }}')">
                                <td class="px-2 py-2 text-xs font-semibold text-gray-900 border-r border-gray-200">
                                    {{ $marca->Folio }}
                                </td>
                                <td class="px-2 py-2 text-xs text-gray-900 border-r border-gray-200">
                                    {{ \Carbon\Carbon::parse($marca->Date)->format('d/m/Y') }}
                                </td>
                                <td class="px-2 py-2 text-xs text-gray-900 border-r border-gray-200">
                                    Turno {{ $marca->Turno }}
                                </td>
                                <td class="px-2 py-2 text-xs text-gray-900 border-r border-gray-200">
                                    {{ $marca->nombreEmpl ?? 'N/A' }}
                                </td>
                                <td class="px-2 py-2 text-xs">
                                    @if($marca->Status == 'Finalizado')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-800">
                                            Finalizado
                                        </span>
                                    @elseif($marca->Status == 'En Proceso')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-800">
                                            En Proceso
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-800">
                                            {{ $marca->Status }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel de líneas debajo de la tabla principal -->
        <div id="lineas-panel" class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto overflow-y-auto scroll-area-lines" style="max-height: 36vh;">
                <table class="min-w-full text-xs table-compact whitespace-nowrap">
                    <thead class="bg-gray-50 sticky top-0 z-20">
                        <tr>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Telar</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">%Efi</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-purple-500 uppercase tracking-wider">Marcas</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-blue-500 uppercase tracking-wider">Trama</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-green-500 uppercase tracking-wider">Pie</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-yellow-500 uppercase tracking-wider">Rizo</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-red-500 uppercase tracking-wider">Otros</th>
                        </tr>
                    </thead>
                    <tbody id="lineas-tbody" class="bg-white divide-y divide-gray-200"></tbody>
                </table>
            </div>
        </div>
    @else
        <!-- Mensaje cuando no hay marcas -->
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay marcas registradas</h3>
            <p class="text-gray-500">Haz clic en "Nueva Marca" para crear el primer registro de marcas</p>
            <a href="{{ route('marcas.nuevo') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>
                Nueva Marca
            </a>
        </div>
    @endif
</div>

<style>
    /* Estilos para las filas clickeables */
    .cursor-pointer:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* Tabla compacta */
    .table-compact th,
    .table-compact td { padding: 0.35rem 0.5rem; line-height: 1.1; }
    .table-compact { font-size: 0.80rem; }

    /* Compacta alto sin estrechar (solo para la principal) */
    .table-compact-y th,
    .table-compact-y td {
        padding-top: 0.20rem;
        padding-bottom: 0.20rem;
        padding-left: 0.9rem;  /* mantener ancho visual */
        padding-right: 0.9rem; /* mantener ancho visual */
        line-height: 1.15;
    }

    /* Evitar scroll chaining entre áreas */
    .scroll-area,
    .scroll-area-lines {
        overscroll-behavior: contain;
        overscroll-behavior-y: contain;
    }
    
    /* Animaciones suaves */
    .transition-colors {
        transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    /* Estilos para scroll de la tabla principal */
    .overflow-y-auto::-webkit-scrollbar {
        width: 8px;
    }
    
    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    
    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    
    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    /* Header sticky para la tabla con scroll */
    thead.sticky {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    /* Header sticky para tabla de detalles */
    #lineas-panel thead.sticky {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        background-color: #f9fafb;
    }
    
    /* Scroll personalizado para tabla de detalles */
    #lineas-panel .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }
    
    #lineas-panel .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
    }
    
    #lineas-panel .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    
    #lineas-panel .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    /* Estilos para la tabla de líneas */
    #lineas-panel {
        border-left: 4px solid #9333ea;
        animation: slideDown 0.3s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Resaltado para fila seleccionada */
    .fila-seleccionada {
        background-color: #f3e8ff !important;
        border-left: 4px solid #9333ea;
        box-shadow: 0 2px 4px rgba(147, 51, 234, 0.2);
        transform: translateX(2px);
    }
    
    /* Mejora del botón de cerrar */
    .close-btn:hover {
        color: #dc2626;
        transform: scale(1.1);
    }
    
    /* Estilos para botones de acción */
    .btn-editar:hover {
        background-color: #2563eb !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
    }
    
    .btn-finalizar:hover {
        background-color: #059669 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(5, 150, 105, 0.3);
    }
    
    .btn-finalizado {
        cursor: not-allowed !important;
        opacity: 0.6;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Dataset con líneas por folio para render inmediato (inyectado desde el backend)
    const lineasPorFolio = {!! json_encode(
        $marcas->mapWithKeys(function($m) {
            return [
                $m->Folio => $m->lineas->map(function($l) {
                    return [
                        'NoTelarId' => $l->NoTelarId,
                        'PorcentajeEfi' => $l->PorcentajeEfi,
                        'Marcas' => $l->Marcas,
                        'Trama' => $l->Trama,
                        'Pie' => $l->Pie,
                        'Rizo' => $l->Rizo,
                        'Otros' => $l->Otros,
                    ];
                })->toArray()
            ];
        })->toArray()
    ) !!};

    // Variables para manejo de selección
    let folioSeleccionado = null;
    let statusFolioSeleccionado = null;

    // Función para actualizar el estado de los botones
    function actualizarEstadoBotones() {
        const btnEditar = document.getElementById('btn-editar-folio');
        const btnTerminar = document.getElementById('btn-terminar-folio');
        
        // Verificar que los elementos existan
        if (!btnEditar || !btnTerminar) {
            return;
        }
        
        if (!folioSeleccionado) {
            // Sin selección
            btnEditar.disabled = true;
            btnTerminar.disabled = true;
            btnEditar.title = 'Selecciona un folio para editar';
            btnTerminar.title = 'Selecciona un folio para terminar';
        } else if (statusFolioSeleccionado === 'Finalizado') {
            // Folio finalizado - no se puede editar ni terminar
            btnEditar.disabled = true;
            btnTerminar.disabled = true;
            btnEditar.title = 'No se puede editar una marca finalizada';
            btnTerminar.title = 'Esta marca ya está finalizada';
        } else {
            // Folio seleccionado y no finalizado
            btnEditar.disabled = false;
            btnTerminar.disabled = false;
            btnEditar.title = `Editar folio ${folioSeleccionado}`;
            btnTerminar.title = `Terminar marca ${folioSeleccionado}`;
        }
    }

    function renderLineasTabla(folio) {
        const cont = document.getElementById('lineas-tbody');
        if (!cont) return;
        cont.innerHTML = '';
        const rows = lineasPorFolio[folio] || [];
        if (!rows.length) {
            cont.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-inbox text-3xl mb-2 block"></i>Sin líneas capturadas para esta marca</td></tr>';
            return;
        }
        
        rows.forEach((l, index) => {
            const tr = document.createElement('tr');
            tr.className = index % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100';
            
            // Formatear valores para mostrar
            const formatValue = (value, suffix = '') => {
                if (!value || value === '0' || value === 0) return '<span class="text-gray-400">-</span>';
                return `<span class="font-medium">${value}${suffix}</span>`;
            };
            
            tr.innerHTML = `
                <td class="px-2 py-1 font-semibold text-gray-900">
                    <div class="flex items-center">
                        <div class="bg-purple-100 text-purple-800 rounded-full px-2 py-1 text-xs font-medium">
                            ${l.NoTelarId ?? '-'}
                        </div>
                    </div>
                </td>
                <td class="px-2 py-1 text-center">${formatValue(l.PorcentajeEfi, '%')}</td>
                <td class="px-2 py-1 text-center">
                    <span class="text-purple-600 font-bold text-sm">${formatValue(l.Marcas)}</span>
                </td>
                <td class="px-2 py-1 text-center text-blue-600">${formatValue(l.Trama)}</td>
                <td class="px-2 py-1 text-center text-green-600">${formatValue(l.Pie)}</td>
                <td class="px-2 py-1 text-center text-yellow-600">${formatValue(l.Rizo)}</td>
                <td class="px-2 py-1 text-center text-red-600">${formatValue(l.Otros)}</td>
            `;
            cont.appendChild(tr);
        });
    }

    function toggleLineasPanel(folio) {
        // Seleccionar el folio clickeado
        seleccionarFolio(folio);
        
        const panel = document.getElementById('lineas-panel');
        if (!panel) return;
        
        renderLineasTabla(folio);
        panel.classList.remove('hidden');
        resaltarFilaSeleccionada(folio);
    }

    function cerrarLineasPanel() {
        const panel = document.getElementById('lineas-panel');
        if (panel) panel.classList.add('hidden');
        limpiarResaltadoSeleccion();
    }

    // Función para seleccionar un folio
    function seleccionarFolio(folio) {
        // Obtener el status del folio desde la fila
        const fila = document.querySelector(`tr[data-folio="${folio}"]`);
        const statusElement = fila?.querySelector('.bg-green-100, .bg-blue-100, .bg-yellow-100');
        const status = statusElement?.textContent?.strip?.() || statusElement?.textContent?.trim() || '';
        
        folioSeleccionado = folio;
        statusFolioSeleccionado = status;
        
        // Actualizar estado de los botones con un pequeño delay para asegurar que el DOM esté listo
        setTimeout(() => {
            actualizarEstadoBotones();
        }, 100);
    }

    // Función para editar el folio seleccionado
    function editarFolioSeleccionado() {
        if (!folioSeleccionado) {
            Swal.fire({
                title: 'Sin selección',
                text: 'Selecciona un folio de la tabla para editarlo',
                icon: 'warning'
            });
            return;
        }
        
        if (statusFolioSeleccionado === 'Finalizado') {
            Swal.fire({
                title: 'Acción no permitida',
                text: 'No se puede editar una marca que ya está finalizada',
                icon: 'error'
            });
            return;
        }
        
        editarMarca(folioSeleccionado);
    }

    // Función para terminar el folio seleccionado
    function terminarFolioSeleccionado() {
        if (!folioSeleccionado) {
            Swal.fire({
                title: 'Sin selección',
                text: 'Selecciona un folio de la tabla para terminarlo',
                icon: 'warning'
            });
            return;
        }
        
        if (statusFolioSeleccionado === 'Finalizado') {
            Swal.fire({
                title: 'Acción no permitida',
                text: 'Esta marca ya está finalizada',
                icon: 'info'
            });
            return;
        }
        
        finalizarMarca(folioSeleccionado);
    }
    
    // Función para resaltar la fila seleccionada
    function resaltarFilaSeleccionada(folio) {
        // Limpiar resaltado previo
        limpiarResaltadoSeleccion();
        
        // Resaltar fila actual
        const fila = document.querySelector(`tr[data-folio="${folio}"]`);
        if (fila) {
            fila.classList.add('fila-seleccionada');
        }
    }
    
    // Función para limpiar el resaltado
    function limpiarResaltadoSeleccion() {
        document.querySelectorAll('.fila-seleccionada').forEach(fila => {
            fila.classList.remove('fila-seleccionada');
        });
    }
    
    // Función para limpiar completamente la selección
    function limpiarSeleccion() {
        folioSeleccionado = null;
        statusFolioSeleccionado = null;
        limpiarResaltadoSeleccion();
        actualizarEstadoBotones();
    }

    // Editar marca existente
    function editarMarca(folio) {
        // Redirigir directamente a la página de edición
        window.location.href = `/modulo-marcas?folio=${folio}`;
    }

    // Finalizar marca existente
    function finalizarMarca(folio) {
        Swal.fire({
            title: 'Finalizar Marca',
            text: `¿Estás seguro de finalizar la marca ${folio}? Esta acción cambiará su estado a "Finalizado".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading
                Swal.fire({
                    title: 'Finalizando...',
                    text: 'Por favor espera',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Enviar petición para finalizar
                fetch(`/modulo-marcas/${folio}/finalizar`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Finalizado',
                            text: `La marca ${folio} ha sido finalizada exitosamente`,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Recargar la página para actualizar la tabla
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'No se pudo finalizar la marca',
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: 'Error de conexión: ' + error.message,
                        icon: 'error'
                    });
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar estado de botones
        actualizarEstadoBotones();
        
        // Agregar event listeners a las filas de la tabla
        document.querySelectorAll('tr[data-folio]').forEach(fila => {
            fila.addEventListener('click', function() {
                const folio = this.getAttribute('data-folio');
                toggleLineasPanel(folio);
            });
        });

        // Seleccionar automáticamente el primer folio para mostrar ambas tablas sin necesidad de scroll adicional
        const firstRow = document.querySelector('tr[data-folio]');
        if (firstRow) {
            const folio = firstRow.getAttribute('data-folio');
            toggleLineasPanel(folio);
            // Asegurar foco visual
            firstRow.classList.add('fila-seleccionada');
        }
    });
</script>
@endsection
