@extends('layouts.app')

@section('page-title', 'Reimpresion Urdido')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-edit
            id="btnEditarSeleccionado"
            onclick="editarOrdenSeleccionada()"
            module="Programa Urdido"
            checkPermission="true"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center gap-2"
            title="Editar orden seleccionada"
            icon="fa-edit"
            iconColor="text-white"
            hoverBg="hover:bg-blue-700"
            bg="bg-blue-600"
            text="Editar"
        />
        <button
            id="btnImprimirSeleccionado"
            onclick="imprimirOrdenSeleccionada()"
            disabled
            style="display: none;"
            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center gap-2"
            title="Imprimir PDF de orden seleccionada"
        >
            <i class="fas fa-file-pdf"></i>
            <span>Imprimir PDF</span>
        </button>
    </div>
@endsection

@section('content')
<div class="w-full">
    <div class="bg-white">


        <div class="overflow-x-auto">
            <table class="min-w-full text-sm" id="tablaOrdenes">
                <thead class="bg-blue-500 text-white">
                    <tr>
                        <th class="px-2 py-2 text-left font-semibold cursor-pointer hover:bg-blue-600 select-none" data-col="folio" data-order="">
                            Folio
                            <i class="fas fa-sort ml-1 text-xs opacity-50"></i>
                        </th>
                        <th class="px-2 py-2 text-left font-semibold cursor-pointer hover:bg-blue-600 select-none" data-col="fecha" data-order="asc">
                            Fecha
                            <i class="fas fa-sort-up ml-1 text-xs"></i>
                        </th>
                        <th class="px-2 py-2 text-left font-semibold cursor-pointer hover:bg-blue-600 select-none" data-col="cuenta" data-order="">
                            Cuenta
                            <i class="fas fa-sort ml-1 text-xs opacity-50"></i>
                        </th>
                        <th class="px-2 py-2 text-left font-semibold cursor-pointer hover:bg-blue-600 select-none" data-col="tipo" data-order="">
                            Tipo
                            <i class="fas fa-sort ml-1 text-xs opacity-50"></i>
                        </th>
                        <th class="px-2 py-2 text-left font-semibold cursor-pointer hover:bg-blue-600 select-none" data-col="maquina" data-order="">
                            Maquina
                            <i class="fas fa-sort ml-1 text-xs opacity-50"></i>
                        </th>
                        <th class="px-2 py-2 text-right font-semibold cursor-pointer hover:bg-blue-600 select-none" data-col="metros" data-order="">
                            Metros
                            <i class="fas fa-sort ml-1 text-xs opacity-50"></i>
                        </th>
                        <th class="px-2 py-2 text-center font-semibold cursor-pointer hover:bg-blue-600 select-none" data-col="status" data-order="">
                            Status
                            <i class="fas fa-sort ml-1 text-xs opacity-50"></i>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" id="tbodyOrdenes">
                    @forelse ($ordenes as $orden)
                        <tr
                            class="hover:bg-gray-50 cursor-pointer transition-colors"
                            data-orden-id="{{ $orden->Id }}"
                            data-status="{{ $orden->Status ?? '' }}"
                            onclick="seleccionarFila(this)"
                        >
                            <td class="px-2 py-2" data-value="{{ $orden->Folio ?? '' }}">{{ $orden->Folio ?? '-' }}</td>
                            <td class="px-2 py-2" data-value="{{ $orden->FechaProg ? $orden->FechaProg->format('Y-m-d') : '' }}">{{ $orden->FechaProg ? $orden->FechaProg->format('Y-m-d') : '-' }}</td>
                            <td class="px-2 py-2" data-value="{{ $orden->Cuenta ?? '' }}">{{ $orden->Cuenta ?? '-' }}</td>
                            <td class="px-2 py-2" data-value="{{ $orden->RizoPie ?? '' }}">{{ $orden->RizoPie ?? '-' }}</td>
                            <td class="px-2 py-2" data-value="{{ $orden->MaquinaId ?? '' }}">{{ $orden->MaquinaId ?? '-' }}</td>
                            <td class="px-2 py-2 text-right" data-value="{{ $orden->Metros ?? 0 }}">
                                {{ $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '-' }}
                            </td>
                            <td class="px-2 py-2 text-center">
                                @php
                                    $statusClass = match($orden->Status ?? '') {
                                        'Finalizado' => 'bg-green-100 text-green-800',
                                        'En Proceso' => 'bg-yellow-100 text-yellow-800',
                                        'Programado' => 'bg-blue-100 text-blue-800',
                                        'Cancelado' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                @endphp
                                <span class="px-2 py-1 text-xs font-medium rounded {{ $statusClass }}" data-value="{{ $orden->Status ?? '' }}">
                                    {{ $orden->Status ?? '-' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-2 py-4 text-center text-gray-500">
                                No hay ordenes con esos criterios.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
                <!-- Contador de registros -->
                <div class="px-4 py-2 bg-gray-100 border-b border-gray-300">
                    <p class="text-sm text-gray-700">
                        <span id="contadorRegistros">Encontrados <strong>{{ count($ordenes) }}</strong> registro(s)</span>
                    </p>
                </div>
    </div>
</div>

<script>
    (() => {
        let ordenSeleccionada = null;
        let ordenActual = { col: null, order: '' }; // 'asc' o 'desc'

        // Seleccionar fila
        window.seleccionarFila = function(row) {
            // Remover selección anterior
            document.querySelectorAll('#tbodyOrdenes tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-gray-50');
            });

            // Seleccionar nueva fila
            row.classList.add('bg-blue-500', 'text-white');
            row.classList.remove('hover:bg-gray-50');
            ordenSeleccionada = {
                id: row.dataset.ordenId,
                status: row.dataset.status
            };

            // Habilitar/deshabilitar botones
            const btnEditar = document.getElementById('btnEditarSeleccionado');
            const btnImprimir = document.getElementById('btnImprimirSeleccionado');

            if (btnEditar) {
                btnEditar.disabled = false;
            }

            if (btnImprimir) {
                // Ocultar completamente si no está finalizado, mostrar si está finalizado
                if (ordenSeleccionada.status === 'Finalizado') {
                    btnImprimir.style.display = 'flex';
                    btnImprimir.disabled = false;
                } else {
                    btnImprimir.style.display = 'none';
                }
            }
        };

        // Editar orden seleccionada
        window.editarOrdenSeleccionada = function() {
            if (!ordenSeleccionada || !ordenSeleccionada.id) {
                alert('Seleccione una orden para editar');
                return;
            }

            const url = '{{ route('urdido.editar.ordenes.programadas') }}?orden_id=' + ordenSeleccionada.id;
            window.location.href = url;
        };

        // Imprimir orden seleccionada
        window.imprimirOrdenSeleccionada = function() {
            if (!ordenSeleccionada || !ordenSeleccionada.id) {
                alert('Seleccione una orden para imprimir');
                return;
            }

            if (ordenSeleccionada.status !== 'Finalizado') {
                alert('Solo se pueden imprimir órdenes con status Finalizado');
                return;
            }

            const url = '{{ route('urdido.modulo.produccion.urdido.pdf') }}?orden_id=' + ordenSeleccionada.id + '&tipo=urdido&reimpresion=1';
            window.open(url, '_blank');
        };

        // Ordenamiento por columnas
        function ordenarTabla(columna, orden) {
            const tbody = document.getElementById('tbodyOrdenes');
            const filas = Array.from(tbody.querySelectorAll('tr'));

            // Guardar ID de fila seleccionada
            const ordenIdSeleccionada = ordenSeleccionada ? ordenSeleccionada.id : null;

            // Determinar índice de columna
            const headers = document.querySelectorAll('#tablaOrdenes thead th');
            let colIndex = -1;
            headers.forEach((h, idx) => {
                if (h.dataset.col === columna) {
                    colIndex = idx;
                }
            });

            if (colIndex === -1) return;

            // Ordenar filas
            filas.sort((a, b) => {
                const aCell = a.cells[colIndex];
                const bCell = b.cells[colIndex];
                const aValue = aCell.dataset.value || aCell.textContent.trim();
                const bValue = bCell.dataset.value || bCell.textContent.trim();

                // Conversión para números
                const aNum = parseFloat(aValue.replace(/,/g, ''));
                const bNum = parseFloat(bValue.replace(/,/g, ''));
                const esNumero = !isNaN(aNum) && !isNaN(bNum);

                let comparacion = 0;
                if (esNumero) {
                    comparacion = aNum - bNum;
                } else {
                    // Comparación de fechas (formato YYYY-MM-DD)
                    if (columna === 'fecha' && /^\d{4}-\d{2}-\d{2}$/.test(aValue) && /^\d{4}-\d{2}-\d{2}$/.test(bValue)) {
                        comparacion = aValue.localeCompare(bValue);
                    } else {
                        comparacion = aValue.localeCompare(bValue, 'es', { numeric: true, sensitivity: 'base' });
                    }
                }

                return orden === 'asc' ? comparacion : -comparacion;
            });

            // Limpiar tbody y agregar filas ordenadas
            tbody.innerHTML = '';
            filas.forEach(fila => {
                tbody.appendChild(fila);
                // Restaurar selección si existe
                if (ordenIdSeleccionada && fila.dataset.ordenId === ordenIdSeleccionada) {
                    fila.classList.add('bg-blue-500', 'text-white');
                    fila.classList.remove('hover:bg-gray-50');
                    // Actualizar botones según el status
                    const btnImprimir = document.getElementById('btnImprimirSeleccionado');
                    if (btnImprimir && ordenSeleccionada) {
                        if (ordenSeleccionada.status === 'Finalizado') {
                            btnImprimir.style.display = 'flex';
                            btnImprimir.disabled = false;
                        } else {
                            btnImprimir.style.display = 'none';
                        }
                    }
                }
            });

            // Actualizar iconos de ordenamiento
            headers.forEach(h => {
                const icon = h.querySelector('i');
                if (h.dataset.col === columna) {
                    h.dataset.order = orden;
                    if (icon) {
                        icon.className = orden === 'asc'
                            ? 'fas fa-sort-up ml-1 text-xs'
                            : 'fas fa-sort-down ml-1 text-xs';
                    }
                } else {
                    h.dataset.order = '';
                    if (icon) {
                        icon.className = 'fas fa-sort ml-1 text-xs opacity-50';
                    }
                }
            });

            // Actualizar contador
            actualizarContador();
        }

        // Event listeners para ordenamiento
        document.querySelectorAll('#tablaOrdenes thead th[data-col]').forEach(header => {
            header.addEventListener('click', function() {
                const columna = this.dataset.col;
                const ordenActual = this.dataset.order || '';

                // Alternar entre asc, desc y sin orden
                let nuevoOrden = 'asc';
                if (ordenActual === 'asc') {
                    nuevoOrden = 'desc';
                } else if (ordenActual === 'desc') {
                    nuevoOrden = 'asc';
                }

                ordenarTabla(columna, nuevoOrden);
            });
        });

        // Actualizar contador de registros
        function actualizarContador() {
            const filas = document.querySelectorAll('#tbodyOrdenes tr');
            const contador = document.getElementById('contadorRegistros');
            if (contador) {
                // Contar solo filas que no sean el mensaje de "no hay datos"
                const count = Array.from(filas).filter(fila =>
                    !fila.querySelector('td[colspan]')
                ).length;
                contador.innerHTML = `Encontrados <strong>${count}</strong> registro(s)`;
            }
        }

        // Inicializar contador y ordenar por fecha ascendente por defecto
        document.addEventListener('DOMContentLoaded', () => {
            actualizarContador();
            // Ordenar por fecha ascendente al cargar
            const fechaHeader = document.querySelector('#tablaOrdenes thead th[data-col="fecha"]');
            if (fechaHeader) {
                ordenarTabla('fecha', 'asc');
            }
        });
    })();
</script>
@endsection
