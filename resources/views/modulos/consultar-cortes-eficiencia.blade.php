@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Consultar Cortes de Eficiencia')

@section('content')
<div class="container mx-auto px-4 py-6">

    @if($cortes->count() > 0)
        <!-- Botones de Acción -->
        <div class="mb-6 flex justify-start space-x-3">
            <button id="btn-editar-folio" onclick="editarFolioSeleccionado()" disabled class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-all duration-200">
                <i class="fas fa-edit mr-2"></i>
                Editar Folio
            </button>
            <button id="btn-terminar-folio" onclick="terminarFolioSeleccionado()" disabled class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-all duration-200">
                <i class="fas fa-check mr-2"></i>
                Terminar Corte
            </button>
        </div>

        <!-- Lista de Cortes de Eficiencia -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Folio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Turno</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Horarios</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Usuario</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">No. Empleado</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($cortes as $corte)
                            <tr class="hover:bg-blue-50 cursor-pointer transition-colors duration-200" data-folio="{{ $corte->Folio }}" onclick="toggleLineasPanel('{{ $corte->Folio }}')">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 border-r border-gray-200">
                                    {{ $corte->Folio }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                    {{ \Carbon\Carbon::parse($corte->Date)->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                    Turno {{ $corte->Turno }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                    <div class="flex flex-col space-y-1">
                                        @if($corte->Horario1)
                                            <div class="flex items-center space-x-1">
                                                <span class="inline-block w-2 h-2 bg-blue-500 rounded-full"></span>
                                                <span class="text-xs">H1: {{ explode('.', $corte->Horario1)[0] }}</span>
                                            </div>
                                        @endif
                                        @if($corte->Horario2)
                                            <div class="flex items-center space-x-1">
                                                <span class="inline-block w-2 h-2 bg-green-500 rounded-full"></span>
                                                <span class="text-xs">H2: {{ explode('.', $corte->Horario2)[0] }}</span>
                                            </div>
                                        @endif
                                        @if($corte->Horario3)
                                            <div class="flex items-center space-x-1">
                                                <span class="inline-block w-2 h-2 bg-yellow-500 rounded-full"></span>
                                                <span class="text-xs">H3: {{ explode('.', $corte->Horario3)[0] }}</span>
                                            </div>
                                        @endif
                                        @if(!$corte->Horario1 && !$corte->Horario2 && !$corte->Horario3)
                                            <span class="text-xs text-gray-400">Sin horarios</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                    {{ $corte->nombreEmpl ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                    {{ $corte->numero_empleado ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($corte->Status == 'Finalizado')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Finalizado
                                        </span>
                                    @elseif($corte->Status == 'En Proceso')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            En Proceso
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ $corte->Status }}
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
        <div id="lineas-panel" class="bg-white rounded-lg shadow-lg overflow-hidden hidden">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-blue-50 to-blue-100">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="bg-blue-500 text-white rounded-full p-2">
                            <i class="fas fa-table text-sm"></i>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-800">Detalles del Corte</div>
                            <div class="text-sm text-gray-600">Folio: <span id="lineas-folio" class="font-semibold text-blue-600"></span></div>
                        </div>
                    </div>
                    <button onclick="cerrarLineasPanel()" class="close-btn text-gray-500 hover:text-red-500 transition-all duration-200 p-2 rounded-full hover:bg-red-50">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telar</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">RPM STD</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Efic. STD</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-blue-500 uppercase tracking-wider">Horario 1</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-green-500 uppercase tracking-wider">Horario 2</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-yellow-500 uppercase tracking-wider">Horario 3</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody id="lineas-tbody" class="bg-white divide-y divide-gray-200"></tbody>
                </table>
            </div>
        </div>
    @else
        <!-- Mensaje cuando no hay cortes -->
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay cortes de eficiencia registrados</h3>
            <p class="text-gray-500">Haz clic en "Nuevo Corte" para crear el primer corte de eficiencia</p>
            <a href="{{ route('cortes.eficiencia') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>
                Nuevo Corte
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
    
    /* Animaciones suaves */
    .transition-colors {
        transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    /* Estilos para la tabla de líneas */
    #lineas-panel {
        border-left: 4px solid #3b82f6;
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
        background-color: #dbeafe !important;
        border-left: 4px solid #3b82f6;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
        transform: translateX(2px);
    }
    
    /* Estilos para los puntos de horarios */
    .horario-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
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
    
    /* Scroll suave para el panel */
    html {
        scroll-behavior: smooth;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Dataset con líneas por folio para render inmediato (inyectado desde el backend)
    const lineasPorFolio = {!! json_encode(
        $cortes->mapWithKeys(function($c) {
            return [
                $c->Folio => $c->lineas->map(function($l) {
                    return [
                        'NoTelarId' => $l->NoTelarId,
                        'RpmStd' => $l->RpmStd,
                        'EficienciaStd' => $l->EficienciaSTD,
                        'RpmR1' => $l->RpmR1,
                        'EficienciaR1' => $l->EficienciaR1,
                        'RpmR2' => $l->RpmR2,
                        'EficienciaR2' => $l->EficienciaR2,
                        'RpmR3' => $l->RpmR3,
                        'EficienciaR3' => $l->EficienciaR3,
                        'ObsR1' => $l->ObsR1,
                        'ObsR2' => $l->ObsR2,
                        'ObsR3' => $l->ObsR3,
                    ];
                })->toArray()
            ];
        })->toArray()
    ) !!};

    // Debug: Mostrar datos STD en consola
    console.log('=== DEBUG: Datos STD en consulta ===');
    Object.keys(lineasPorFolio).forEach(folio => {
        console.log(`Folio ${folio}:`);
        lineasPorFolio[folio].forEach(linea => {
            if (linea.RpmStd || linea.EficienciaStd) {
                console.log(`  Telar ${linea.NoTelarId}: RPM STD=${linea.RpmStd}, Efic STD=${linea.EficienciaStd}`);
            }
        });
    });

    // Variables para manejo de selección
    let folioSeleccionado = null;
    let statusFolioSeleccionado = null;

    // Función para actualizar el estado de los botones
    function actualizarEstadoBotones() {
        const btnEditar = document.getElementById('btn-editar-folio');
        const btnTerminar = document.getElementById('btn-terminar-folio');
        
        // Verificar que los elementos existan
        if (!btnEditar || !btnTerminar) {
            console.log('Botones no encontrados aún');
            return;
        }
        
        if (!folioSeleccionado) {
            // Sin selección
            btnEditar.disabled = true;
            btnTerminar.disabled = true;
            btnEditar.title = 'Selecciona un folio para editar';
            btnTerminar.title = 'Selecciona un folio para terminar';
            console.log('Botones deshabilitados - sin selección');
        } else if (statusFolioSeleccionado === 'Finalizado') {
            // Folio finalizado - no se puede editar ni terminar
            btnEditar.disabled = true;
            btnTerminar.disabled = true;
            btnEditar.title = 'No se puede editar un corte finalizado';
            btnTerminar.title = 'Este corte ya está finalizado';
            console.log('Botones deshabilitados - corte finalizado');
        } else {
            // Folio seleccionado y no finalizado
            btnEditar.disabled = false;
            btnTerminar.disabled = false;
            btnEditar.title = `Editar folio ${folioSeleccionado}`;
            btnTerminar.title = `Terminar corte ${folioSeleccionado}`;
            console.log(`Botones habilitados para folio: ${folioSeleccionado}`);
        }
    }

    function renderLineasTabla(folio) {
        const cont = document.getElementById('lineas-tbody');
        if (!cont) return;
        cont.innerHTML = '';
        const rows = lineasPorFolio[folio] || [];
        if (!rows.length) {
            cont.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-inbox text-3xl mb-2 block"></i>Sin líneas capturadas para este corte</td></tr>';
            return;
        }
        
        rows.forEach((l, index) => {
            // Debug: Mostrar datos de cada línea
            if (l.RpmStd || l.EficienciaStd) {
                console.log(`Renderizando telar ${l.NoTelarId}: RPM STD=${l.RpmStd}, Efic STD=${l.EficienciaStd}`);
            }
            
            const tr = document.createElement('tr');
            tr.className = index % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100';
            
            // Formatear valores para mostrar
            const formatValue = (value, suffix = '') => {
                if (!value || value === '0' || value === 0) return '<span class="text-gray-400">-</span>';
                return `<span class="font-medium">${value}${suffix}</span>`;
            };
            
            // Preparar observaciones
            const observaciones = [];
            if (l.ObsR1) observaciones.push(`<span class="text-blue-600">H1: ${l.ObsR1}</span>`);
            if (l.ObsR2) observaciones.push(`<span class="text-green-600">H2: ${l.ObsR2}</span>`);
            if (l.ObsR3) observaciones.push(`<span class="text-yellow-600">H3: ${l.ObsR3}</span>`);
            const obsText = observaciones.length ? observaciones.join('<br>') : '<span class="text-gray-400">Sin observaciones</span>';
            
            tr.innerHTML = `
                <td class="px-4 py-3 font-semibold text-gray-900">
                    <div class="flex items-center">
                        <div class="bg-blue-100 text-blue-800 rounded-full px-2 py-1 text-xs font-medium">
                            ${l.NoTelarId ?? '-'}
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-center">${formatValue(l.RpmStd)}</td>
                <td class="px-4 py-3 text-center">${formatValue(l.EficienciaStd, '%')}</td>
                <td class="px-4 py-3 text-center">
                    <div class="space-y-1">
                        <div>RPM: ${formatValue(l.RpmR1)}</div>
                        <div>Efic: ${formatValue(l.EficienciaR1, '%')}</div>
                    </div>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="space-y-1">
                        <div>RPM: ${formatValue(l.RpmR2)}</div>
                        <div>Efic: ${formatValue(l.EficienciaR2, '%')}</div>
                    </div>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="space-y-1">
                        <div>RPM: ${formatValue(l.RpmR3)}</div>
                        <div>Efic: ${formatValue(l.EficienciaR3, '%')}</div>
                    </div>
                </td>
                <td class="px-4 py-3 text-sm">${obsText}</td>
            `;
            cont.appendChild(tr);
        });
    }



    function toggleLineasPanel(folio) {
        // Seleccionar el folio clickeado
        seleccionarFolio(folio);
        
        const panel = document.getElementById('lineas-panel');
        const folioSpan = document.getElementById('lineas-folio');
        if (!panel) return;
        folioSpan.textContent = folio;
        renderLineasTabla(folio);
        panel.classList.remove('hidden');
        // Removido el scrollIntoView para evitar que se mueva la pantalla
        resaltarFilaSeleccionada(folio);
    }

    function cerrarLineasPanel() {
        const panel = document.getElementById('lineas-panel');
        if (panel) panel.classList.add('hidden');
        // No limpiar la selección al cerrar el panel, mantener el folio seleccionado
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
        
        console.log(`Folio seleccionado: ${folio}, Status detectado: "${status}"`);
        
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
                text: 'No se puede editar un corte que ya está finalizado',
                icon: 'error'
            });
            return;
        }
        
        editarCorte(folioSeleccionado);
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
                text: 'Este corte ya está finalizado',
                icon: 'info'
            });
            return;
        }
        
        finalizarCorte(folioSeleccionado);
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
        console.log('Selección limpiada');
    }

    // Editar corte existente
    function editarCorte(folio) {
        Swal.fire({
            title: 'Editar Corte',
            text: '¿Deseas editar este corte de eficiencia?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, editar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirigir a la página de edición
                window.location.href = `/modulo-cortes-de-eficiencia?folio=${folio}`;
            }
        });
    }

    // Finalizar corte existente
    function finalizarCorte(folio) {
        Swal.fire({
            title: 'Finalizar Corte',
            text: `¿Estás seguro de finalizar el corte ${folio}? Esta acción cambiará su estado a "Finalizado".`,
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
                fetch(`/modulo-cortes-de-eficiencia/${folio}/finalizar`, {
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
                            text: `El corte ${folio} ha sido finalizado exitosamente`,
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
                            text: data.message || 'No se pudo finalizar el corte',
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
        console.log('Consultar Cortes de Eficiencia cargado - {{ $cortes->count() }} registros');
        
        // Inicializar estado de botones
        actualizarEstadoBotones();
        
        // Agregar event listeners a las filas de la tabla
        document.querySelectorAll('tr[data-folio]').forEach(fila => {
            fila.addEventListener('click', function() {
                const folio = this.getAttribute('data-folio');
                console.log(`Click en fila de folio: ${folio}`);
                toggleLineasPanel(folio);
            });
        });
        
        console.log('Event listeners agregados a las filas');
        
        // Test de botones después de un segundo
        setTimeout(() => {
            console.log('=== TEST DE BOTONES ===');
            const btnEditar = document.getElementById('btn-editar-folio');
            const btnTerminar = document.getElementById('btn-terminar-folio');
            console.log('Botón editar encontrado:', !!btnEditar);
            console.log('Botón terminar encontrado:', !!btnTerminar);
            if (btnEditar) console.log('Botón editar deshabilitado:', btnEditar.disabled);
            if (btnTerminar) console.log('Botón terminar deshabilitado:', btnTerminar.disabled);
        }, 1000);
    });
    
    // Función de debug global para probar selección
    window.testSeleccion = function(folio) {
        console.log(`=== TEST MANUAL: Seleccionando folio ${folio} ===`);
        seleccionarFolio(folio);
    };
</script>
@endsection
