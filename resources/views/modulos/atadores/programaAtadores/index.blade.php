@extends('layouts.app')

@section('page-title', 'Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        {{-- Botón de Filtros --}}
        <button id="btn-open-filters" title="Filtros"
                class="p-2 rounded-lg transition hover:bg-purple-100 relative">
            <i class="fa-solid fa-filter text-purple-600 text-lg"></i>
            @if($filtroAplicado !== 'todos')
                <span id="filter-badge"
                      class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">!</span>
            @endif
        </button>
        
        <button id="btnIniciarAtado" onclick="iniciarAtado()" disabled
            class="px-2 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors duration-200 opacity-50 cursor-not-allowed">
            <i class="fas fa-play mr-1"></i> Iniciar Atado
        </button>
    </div>
@endsection

@section('content')
{{-- Modal de Filtros --}}
<div id="modalFiltros" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fa-solid fa-filter text-purple-600 mr-2"></i>Filtros
            </h2>
            <button type="button" onclick="cerrarModalFiltros()" class="text-slate-500 hover:text-slate-700 text-5xl leading-none">&times;</button>
        </div>

        <div class="grid grid-cols-2 gap-3">
            {{-- Ver Todos --}}
            <button type="button" id="btn-filter-todos" onclick="aplicarFiltro('todos')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-list text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Ver Todos</div>
            </button>

            {{-- Ver Creados (Activo) --}}
            <button type="button" id="btn-filter-creados" onclick="aplicarFiltro('creados')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-plus-circle text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Ver Creados</div>
            </button>

            {{-- Ver Activo y En Proceso --}}
            <button type="button" id="btn-filter-activo-proceso" onclick="aplicarFiltro('activo-proceso')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-play-circle text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Activo y En Proceso</div>
            </button>

            {{-- Ver Terminados --}}
            <button type="button" id="btn-filter-terminados" onclick="aplicarFiltro('terminados')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-check-circle text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Ver Terminados</div>
            </button>
        </div>
    </div>
</div>

<div class="container mx-auto px-4 py-4">
    
    <div class="overflow-x-auto overflow-y-auto rounded-lg shadow-md bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-md">
                <thead class="bg-blue-500 sticky top-0 z-10">
                    <tr>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Fecha
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Estatus
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Turno
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Telar
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Tipo
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            No. Julio
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Ubicación
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Metros
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Orden
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Tipo Atado
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Cuenta
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Calibre
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Hilo
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Lote Prov.
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            No. Prov.
                        </th>
                        <th class="px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Hr. Paro
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="tb-body">
                    @forelse($inventarioTelares as $item)
                        <tr class="table-row hover:bg-blue-100 cursor-pointer transition-colors duration-150"
                            onclick="selectRow(this, {{ $item->id }})"
                            data-id="{{ $item->id }}"
                            data-no-julio="{{ $item->no_julio }}"
                            data-no-orden="{{ $item->no_orden }}"
                            data-hora-paro="{{ $item->horaParo ?? '' }}"
                            data-status="{{ $item->status_proceso ?? 'Activo' }}"
                            data-telar="{{ $item->no_telar ?? '' }}">
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->fecha ? $item->fecha->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md" data-status="{{ $item->status_proceso }}">
                                <span class="px-1.5 py-0.5 rounded-full text-md font-semibold
                                    @if($item->status_proceso === 'Activo') bg-gray-200 text-gray-800
                                    @elseif($item->status_proceso === 'En Proceso') bg-blue-200 text-blue-800
                                    @elseif($item->status_proceso === 'Terminado') bg-purple-200 text-purple-800
                                    @elseif($item->status_proceso === 'Calificado') bg-yellow-200 text-yellow-800
                                    @elseif($item->status_proceso === 'Autorizado') bg-green-200 text-green-800
                                    @endif">
                                    {{ $item->status_proceso }}
                                </span>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->turno ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->no_telar ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->tipo ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->no_julio ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->localidad ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->metros ? number_format($item->metros, 2) : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->no_orden ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->tipo_atado ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">de J
                                {{ $item->cuenta ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->calibre ? number_format($item->calibre, 2) : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->hilo ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->LoteProveedor ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->NoProveedor ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
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

@endsection

@push('scripts')
<script>
let selectedRowId = null;
let selectedRow = null;

// Estado de filtros - Inicializar con el filtro por defecto según área/puesto
let filterState = {
    tipo: '{{ $filtroAplicado }}', // Inicializar con el filtro del servidor
    telaresUsuario: @json($telaresUsuario ?? []), // Telares del usuario si es tejedor
    esTejedor: {{ $esTejedor ?? false ? 'true' : 'false' }} // Si el usuario es tejedor
};

// Funciones para el modal de filtros
function mostrarModalFiltros() {
    const modal = document.getElementById('modalFiltros');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function cerrarModalFiltros() {
    const modal = document.getElementById('modalFiltros');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Función para aplicar filtros sin recargar
function aplicarFiltro(tipo) {
    filterState.tipo = tipo;
    updateFilterButtons();
    applyFilters();
    cerrarModalFiltros();
}

// Función para aplicar filtros a las filas
function applyFilters() {
    const rows = document.querySelectorAll('.table-row');
    const tbody = document.getElementById('tb-body');
    let visibleCount = 0;

    rows.forEach(row => {
        const status = row.getAttribute('data-status') || 'Activo';
        const noTelar = row.getAttribute('data-telar') || ''; // Obtener telar del atributo data
        let show = true;

        switch (filterState.tipo) {
            case 'creados':
                // Solo registros con status Activo (sin registro en AtaMontadoTelas)
                show = status === 'Activo';
                break;
                
            case 'activo-proceso':
                // Activo y En Proceso
                show = status === 'Activo' || status === 'En Proceso';
                break;
                
            case 'terminados':
                // Solo Terminados
                show = status === 'Terminado';
                // Si es tejedor, también filtrar por sus telares
                if (filterState.esTejedor && filterState.telaresUsuario.length > 0) {
                    show = show && filterState.telaresUsuario.includes(noTelar);
                }
                break;
                
            case 'todos':
            default:
                // Mostrar todos
                show = true;
                break;
        }

        // Mostrar/ocultar fila
        if (show) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Actualizar badge
    const filterBadge = document.getElementById('filter-badge');
    if (filterState.tipo !== 'todos') {
        filterBadge?.classList.remove('hidden');
    } else {
        filterBadge?.classList.add('hidden');
    }

    // Mostrar mensaje si no hay resultados
    let emptyRow = tbody?.querySelector('tr.no-results');
    if (visibleCount === 0) {
        if (!emptyRow) {
            const tr = document.createElement('tr');
            tr.className = 'no-results';
            tr.innerHTML = `<td colspan="16" class="px-6 py-4 text-center text-sm text-gray-500">
                <div class="flex flex-col items-center gap-2">
                    <i class="fa-solid fa-inbox text-4xl text-gray-300"></i>
                    <span class="text-base font-medium">Sin resultados con los filtros aplicados</span>
                </div>
            </td>`;
            tbody?.appendChild(tr);
        }
    } else {
        emptyRow?.remove();
    }
}

// Actualizar estado visual de botones
function updateFilterButtons() {
    const btnTodos = document.getElementById('btn-filter-todos');
    const btnCreados = document.getElementById('btn-filter-creados');
    const btnActivoProceso = document.getElementById('btn-filter-activo-proceso');
    const btnTerminados = document.getElementById('btn-filter-terminados');

    // Resetear todos los botones
    [btnTodos, btnCreados, btnActivoProceso, btnTerminados].forEach(btn => {
        if (btn) {
            btn.classList.remove('bg-green-100', 'border-green-400', 'text-green-800',
                               'bg-blue-100', 'border-blue-400', 'text-blue-800',
                               'bg-yellow-100', 'border-yellow-400', 'text-yellow-800',
                               'bg-purple-100', 'border-purple-400', 'text-purple-800');
            btn.classList.add('bg-gray-50', 'border-gray-300', 'text-gray-700');
        }
    });

    // Aplicar estilos según el filtro activo
    switch (filterState.tipo) {
        case 'todos':
            if (btnTodos) {
                btnTodos.classList.remove('bg-gray-50', 'border-gray-300', 'text-gray-700');
                btnTodos.classList.add('bg-green-100', 'border-green-400', 'text-green-800');
            }
            break;
        case 'creados':
            if (btnCreados) {
                btnCreados.classList.remove('bg-gray-50', 'border-gray-300', 'text-gray-700');
                btnCreados.classList.add('bg-blue-100', 'border-blue-400', 'text-blue-800');
            }
            break;
        case 'activo-proceso':
            if (btnActivoProceso) {
                btnActivoProceso.classList.remove('bg-gray-50', 'border-gray-300', 'text-gray-700');
                btnActivoProceso.classList.add('bg-yellow-100', 'border-yellow-400', 'text-yellow-800');
            }
            break;
        case 'terminados':
            if (btnTerminados) {
                btnTerminados.classList.remove('bg-gray-50', 'border-gray-300', 'text-gray-700');
                btnTerminados.classList.add('bg-purple-100', 'border-purple-400', 'text-purple-800');
            }
            break;
    }
}

// Cerrar modal al hacer clic fuera de él
document.getElementById('modalFiltros')?.addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalFiltros();
    }
});

// Abrir modal con el botón
document.getElementById('btn-open-filters')?.addEventListener('click', mostrarModalFiltros);

// Inicializar filtros al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    updateFilterButtons();
    applyFilters();
});

setInterval(refreshStatus, 5000);

async function refreshStatus() {
    try {
        // Preservar la selección actual antes de actualizar
        const currentSelectedId = selectedRowId;

        // Obtener todos los registros sin filtro
        const url = '{{ route("atadores.programa") }}';
        
        const response = await fetch(url);
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        document.querySelectorAll('tbody tr[data-id]').forEach(row => {
            const id = row.getAttribute('data-id');
            const newRow = doc.querySelector(`tr[data-id="${id}"]`);
            if (newRow) {
                // Actualizar status
                const currentStatusCell = row.querySelector('td[data-status]');
                const newStatusCell = newRow.querySelector('td[data-status]');
                if (currentStatusCell && newStatusCell &&
                    currentStatusCell.getAttribute('data-status') !== newStatusCell.getAttribute('data-status')) {
                    currentStatusCell.innerHTML = newStatusCell.innerHTML;
                    currentStatusCell.setAttribute('data-status', newStatusCell.getAttribute('data-status'));
                    
                    // Actualizar el atributo data-status de la fila
                    const newStatus = newStatusCell.getAttribute('data-status');
                    row.setAttribute('data-status', newStatus || 'Activo');
                }
                
                // Actualizar telar si cambió
                const newTelar = newRow.getAttribute('data-telar');
                if (newTelar) {
                    row.setAttribute('data-telar', newTelar);
                }
            }
        });
        
        // Reaplicar filtros después de actualizar
        applyFilters();

        // Restaurar la selección después de actualizar solo si todavía existe
        if (currentSelectedId) {
            const restoredRow = document.querySelector(`tr[data-id="${currentSelectedId}"]`);
            if (restoredRow) {
                // Limpiar todas las selecciones primero para evitar duplicados
                document.querySelectorAll('tbody tr').forEach(tr => {
                    tr.classList.remove('bg-blue-500', 'text-white', 'hover:bg-blue-700');
                    tr.querySelectorAll('td').forEach(td => {
                        td.classList.remove('text-white');
                    });
                });

                // Aplicar selección solo a la fila correcta
                selectedRow = restoredRow;
                selectedRowId = currentSelectedId;
                restoredRow.classList.add('bg-blue-500', 'text-white', 'hover:bg-blue-700');
                restoredRow.querySelectorAll('td').forEach(td => {
                    td.classList.add('text-white');
                });
                enableIniciarButton();
            } else {
                // Si la fila ya no existe, limpiar la selección
                selectedRow = null;
                selectedRowId = null;
                disableIniciarButton();
            }
        }
    } catch (error) {
        console.error('Error refreshing status:', error);
    }
}

function selectRow(row, id) {
    // Validar que el ID y la fila sean válidos
    if (!id || !row) {
        console.error('Error: ID o fila inválidos');
        return;
    }

    // Obtener datos de la fila para validación
    const noJulio = row.getAttribute('data-no-julio');
    const noOrden = row.getAttribute('data-no-orden');
    const horaParo = row.getAttribute('data-hora-paro') || '';
    if (!horaParo.trim()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'El telar debe registrar la hora de paro antes de iniciar el atado'
        });
        disableIniciarButton();
        return;
    }

    // Validar que la fila tenga los datos necesarios
    if (!noJulio || !noOrden) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'El registro seleccionado no tiene los datos necesarios (No. Julio o No. Orden)'
        });
        return;
    }

    // Si se hace clic en la misma fila, deseleccionar
    if (selectedRow === row && selectedRowId === id) {
        // Limpiar todas las selecciones
        document.querySelectorAll('tbody tr').forEach(tr => {
            tr.classList.remove('bg-blue-500', 'text-white', 'hover:bg-blue-700');
            tr.querySelectorAll('td').forEach(td => {
                td.classList.remove('text-white');
            });
        });
        selectedRow = null;
        selectedRowId = null;
        disableIniciarButton();
        return;
    }

    // Limpiar TODAS las selecciones primero para evitar duplicados
    document.querySelectorAll('tbody tr').forEach(tr => {
        tr.classList.remove('bg-blue-500', 'text-white', 'hover:bg-blue-700');
        tr.querySelectorAll('td').forEach(td => {
            td.classList.remove('text-white');
        });
    });

    // Seleccionar nueva fila
    selectedRow = row;
    selectedRowId = id;
    row.classList.add('bg-blue-500', 'text-white', 'hover:bg-blue-700');
    row.querySelectorAll('td').forEach(td => {
        td.classList.add('text-white');
    });

    enableIniciarButton();
}

function enableIniciarButton() {
    const btn = document.getElementById('btnIniciarAtado');
    if (btn) {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

function disableIniciarButton() {
    const btn = document.getElementById('btnIniciarAtado');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

function iniciarAtado() {
    if (!selectedRowId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Debe seleccionar un registro primero'
        });
        return;
    }

    // Obtener datos adicionales del registro seleccionado para validación
    const selectedRowElement = document.querySelector(`tr[data-id="${selectedRowId}"]`);
    if (!selectedRowElement) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo encontrar el registro seleccionado'
        });
        return;
    }

    const noJulio = selectedRowElement.getAttribute('data-no-julio');
    const noOrden = selectedRowElement.getAttribute('data-no-orden');
    const horaParo = selectedRowElement.getAttribute('data-hora-paro') || '';
    if (!horaParo.trim()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Este telar aún no registra la hora de paro. Detén el telar antes de iniciar el atado'
        });
        return;
    }

    // Validar que los datos estén presentes
    if (!noJulio || !noOrden) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'El registro seleccionado no tiene los datos necesarios (No. Julio o No. Orden)'
        });
        return;
    }

    // Enviar con datos adicionales para validación en el servidor
    window.location.href = `{{ route("atadores.iniciar") }}?id=${selectedRowId}&no_julio=${noJulio}&no_orden=${noOrden}`;
}
</script>
@endpush
