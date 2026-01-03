@extends('layouts.app')

@section('page-title', 'Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button id="btnIniciarAtado" onclick="iniciarAtado()" disabled
            class="px-2 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors duration-200 opacity-50 cursor-not-allowed">
            <i class="fas fa-play mr-1"></i> Iniciar Atado
        </button>
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-4">
    <div class="overflow-x-auto overflow-y-auto rounded-lg shadow-md bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-blue-500 sticky top-0 z-10">
                    <tr>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Fecha Req
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Estatus
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Turno Req
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Telar
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Tipo
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            No. Julio
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Ubicación
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Metros
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Orden
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Tipo Atado
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Cuenta
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Calibre
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Hilo
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Lote Prov.
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            No. Prov.
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Hr. Paro
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($inventarioTelares as $item)
                        <tr class="hover:bg-blue-100 cursor-pointer transition-colors duration-150"
                            onclick="selectRow(this, {{ $item->id }})"
                            data-id="{{ $item->id }}"
                            data-no-julio="{{ $item->no_julio }}"
                            data-no-orden="{{ $item->no_orden }}">
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->fecha ? $item->fecha->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs" data-status="{{ $item->status_proceso }}">
                                <span class="px-1.5 py-0.5 rounded-full text-xs font-semibold
                                    @if($item->status_proceso === 'Activo') bg-gray-200 text-gray-800
                                    @elseif($item->status_proceso === 'En Proceso') bg-blue-200 text-blue-800
                                    @elseif($item->status_proceso === 'Terminado') bg-purple-200 text-purple-800
                                    @elseif($item->status_proceso === 'Calificado') bg-yellow-200 text-yellow-800
                                    @elseif($item->status_proceso === 'Autorizado') bg-green-200 text-green-800
                                    @endif">
                                    {{ $item->status_proceso }}
                                </span>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->turno ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->no_telar ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->tipo ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->no_julio ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->localidad ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->metros ? number_format($item->metros, 2) : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->no_orden ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->tipo_atado ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->cuenta ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->calibre ? number_format($item->calibre, 2) : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->hilo ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->LoteProveedor ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                {{ $item->NoProveedor ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
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

setInterval(refreshStatus, 5000);

async function refreshStatus() {
    try {
        // Preservar la selección actual antes de actualizar
        const currentSelectedId = selectedRowId;

        const response = await fetch('{{ route("atadores.programa") }}');
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        document.querySelectorAll('tbody tr[data-id]').forEach(row => {
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
