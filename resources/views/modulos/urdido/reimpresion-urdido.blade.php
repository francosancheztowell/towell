@extends('layouts.app')

@section('page-title', 'Reimpresion Urdido')

@section('navbar-right')
    <div class="flex items-center gap-2">
                <!-- Botón de Filtros -->
                    <button
                        id="btn-open-filters"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2"
                        title="Filtros"
                    >
                        <i class="fa-solid fa-filter"></i>
                        <span>Filtros</span>
                        <span id="filter-badge" class="hidden ml-1 bg-purple-800 text-white text-xs px-2 py-0.5 rounded-full">1</span>
                    </button>

        <button
            type="button"
            id="btnEditarSeleccionado"
            onclick="editarOrdenSeleccionada();"
            disabled
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center gap-2"
            title="Editar orden seleccionada"
        >
            <i class="fas fa-edit"></i>
            <span>Editar</span>
        </button>
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
                        <th class="px-2 py-2 text-left font-semibold cursor-pointer hover:bg-blue-600 select-none" data-col="fecha" data-order="desc">
                            Fecha
                            <i class="fas fa-sort-down ml-1 text-xs"></i>
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
                            class="table-row hover:bg-gray-50 cursor-pointer transition-colors"
                            data-orden-id="{{ $orden->Id }}"
                            data-status="{{ $orden->Status ?? '' }}"
                            data-folio="{{ $orden->Folio ?? '' }}"
                            data-maquina="{{ $orden->MaquinaId ?? '' }}"
                            data-tipo="{{ $orden->RizoPie ?? '' }}"
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
                        <tr class="no-results">
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

<!-- Modal FILTROS -->
<div id="modal-filters" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4">
        <div class="flex items-center justify-end">

            <button data-close="#modal-filters" class="text-slate-500 hover:text-slate-700 text-4xl leading-none">&times;</button>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-4">
            <!-- Folio -->
            <div class="p-4 rounded-lg border-2 border-blue-300 bg-blue-50">
                <label class="block text-md font-semibold text-blue-800 mb-2 text-center">
                    Folio
                </label>
                <input type="text" id="filter-folio" class="w-full rounded border border-blue-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 bg-white" placeholder="Buscar folio...">
            </div>
            <!-- Máquina -->
            <div class="p-4 rounded-lg border-2 border-green-300 bg-green-50">
                <label class="block text-md font-semibold text-green-800 mb-2 text-center">
                    Máquina
                </label>
                <select id="filter-maquina" class="w-full rounded border border-green-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-green-500 bg-white">
                    <option value="">Todas</option>
                    @php
                        $maquinas = $ordenes->pluck('MaquinaId')->filter()->unique()->sort()->values();
                    @endphp
                    @foreach($maquinas as $maq)
                        <option value="{{ $maq }}" {{ request('maquina') == $maq ? 'selected' : '' }}>{{ $maq }}</option>
                    @endforeach
                </select>
            </div>
            <!-- Tipo -->
            <div class="p-4 rounded-lg border-2 border-cyan-300 bg-cyan-50">
                <label class="block text-md font-semibold text-cyan-800 mb-2 text-center">
                    Tipo
                </label>
                <select id="filter-tipo" class="w-full rounded border border-cyan-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-cyan-500 bg-white">
                    <option value="">Todos</option>
                    @php
                        $tipos = $ordenes->pluck('RizoPie')->filter()->unique()->sort()->values();
                    @endphp
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo }}" {{ request('tipo') == $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                    @endforeach
                </select>
            </div>
            <!-- Status -->
            <div class="p-4 rounded-lg border-2 border-amber-300 bg-amber-50">
                <label class="block text-md font-semibold text-amber-800 mb-2 text-center">
                    Status
                </label>
                <select id="filter-status" class="w-full rounded border border-amber-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white">
                    <option value="">Todos</option>
                    <option value="Finalizado" {{ request('status') == 'Finalizado' ? 'selected' : '' }}>Finalizado</option>
                    <option value="En Proceso" {{ request('status') == 'En Proceso' ? 'selected' : '' }}>En Proceso</option>
                    <option value="Programado" {{ request('status') == 'Programado' ? 'selected' : '' }}>Programado</option>
                    <option value="Cancelado" {{ request('status') == 'Cancelado' ? 'selected' : '' }}>Cancelado</option>
                </select>
            </div>
        </div>
            <div class="flex items-center gap-2">
            <button type="button" id="btn-clear-filters" class="w-full px-3 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition text-sm">
                <i class="fa-solid fa-eraser mr-1"></i>Limpiar
            </button>
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
                return false;
            }

            // Construir URL usando route de Laravel
            const baseUrl = '{{ route('urdido.editar.ordenes.programadas') }}';
            const ordenId = String(ordenSeleccionada.id).trim();
            const url = baseUrl + '?orden_id=' + encodeURIComponent(ordenId) + '&from=reimpresion';

            console.log('=== INICIANDO NAVEGACIÓN ===');
            console.log('URL completa:', url);
            console.log('Orden seleccionada:', ordenSeleccionada);
            console.log('Orden ID (raw):', ordenSeleccionada.id);
            console.log('Orden ID (string):', ordenId);
            console.log('Base URL:', baseUrl);

            // Navegación directa sin try-catch para ver errores reales
            window.location.href = url;

            return false;
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

        // Inicializar contador y ordenar por fecha descendente por defecto (más recientes primero)
        document.addEventListener('DOMContentLoaded', () => {
            actualizarContador();
            // Ordenar por fecha descendente al cargar (más recientes primero)
            const fechaHeader = document.querySelector('#tablaOrdenes thead th[data-col="fecha"]');
            if (fechaHeader) {
                ordenarTabla('fecha', 'desc');
            }
            initFilters();
        });

        // ========== FILTROS ==========
        function initFilters() {
            const btnOpenFilters = document.getElementById('btn-open-filters');
            const btnCloseFilters = document.querySelector('[data-close="#modal-filters"]');
            const modalFilters = document.getElementById('modal-filters');
            const btnClearFilters = document.getElementById('btn-clear-filters');
            const filterBadge = document.getElementById('filter-badge');
            const filterFolio = document.getElementById('filter-folio');
            const filterMaquina = document.getElementById('filter-maquina');
            const filterTipo = document.getElementById('filter-tipo');
            const filterStatus = document.getElementById('filter-status');
            const tbody = document.getElementById('tbodyOrdenes');

            let filterState = {
                folio: '',
                maquina: '',
                tipo: '',
                status: ''
            };

            // Función para aplicar filtros
            function applyFilters() {
                const rows = tbody?.querySelectorAll('.table-row') || [];
                let visibleCount = 0;

                rows.forEach(row => {
                    const folio = (row.dataset.folio || '').toLowerCase();
                    const maquina = row.dataset.maquina || '';
                    const tipo = row.dataset.tipo || '';
                    const status = row.dataset.status || '';

                    let show = true;

                    // Filtro por folio
                    if (filterState.folio) {
                        if (!folio.includes(filterState.folio.toLowerCase())) {
                            show = false;
                        }
                    }

                    // Filtro por máquina
                    if (show && filterState.maquina) {
                        if (maquina !== filterState.maquina) {
                            show = false;
                        }
                    }

                    // Filtro por tipo
                    if (show && filterState.tipo) {
                        if (tipo !== filterState.tipo) {
                            show = false;
                        }
                    }

                    // Filtro por status
                    if (show && filterState.status) {
                        if (status !== filterState.status) {
                            show = false;
                        }
                    }

                    // Mostrar/ocultar fila
                    if (show) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Mostrar mensaje si no hay resultados
                let noResultsRow = tbody?.querySelector('tr.no-results');
                if (visibleCount === 0) {
                    if (!noResultsRow) {
                        const tr = document.createElement('tr');
                        tr.className = 'no-results';
                        tr.innerHTML = `<td colspan="7" class="px-2 py-4 text-center text-gray-500">
                            <div class="flex flex-col items-center gap-2">
                                <i class="fa-solid fa-inbox text-4xl text-gray-300"></i>
                                <span class="text-base font-medium">No hay órdenes con los filtros aplicados</span>
                            </div>
                        </td>`;
                        tbody?.appendChild(tr);
                    }
                } else {
                    noResultsRow?.remove();
                }

                // Actualizar contador
                actualizarContador();

                // Actualizar badge
                const activeCount = Object.values(filterState).filter(v => v !== '').length;
                if (activeCount > 0 && filterBadge) {
                    filterBadge.textContent = activeCount;
                    filterBadge.classList.remove('hidden');
                } else if (filterBadge) {
                    filterBadge.classList.add('hidden');
                }
            }

            // Abrir modal
            btnOpenFilters?.addEventListener('click', () => {
                modalFilters?.classList.remove('hidden');
                modalFilters?.classList.add('flex');
            });

            // Cerrar modal
            btnCloseFilters?.addEventListener('click', () => {
                modalFilters?.classList.add('hidden');
                modalFilters?.classList.remove('flex');
            });

            // Cerrar al hacer click fuera
            modalFilters?.addEventListener('click', (e) => {
                if (e.target === modalFilters) {
                    modalFilters.classList.add('hidden');
                    modalFilters.classList.remove('flex');
                }
            });

            // Event listeners para filtros
            filterFolio?.addEventListener('input', (e) => {
                filterState.folio = e.target.value.trim();
                applyFilters();
            });

            filterMaquina?.addEventListener('change', (e) => {
                filterState.maquina = e.target.value;
                applyFilters();
            });

            filterTipo?.addEventListener('change', (e) => {
                filterState.tipo = e.target.value;
                applyFilters();
            });

            filterStatus?.addEventListener('change', (e) => {
                filterState.status = e.target.value;
                applyFilters();
            });

            // Limpiar filtros
            btnClearFilters?.addEventListener('click', () => {
                filterFolio.value = '';
                filterMaquina.value = '';
                filterTipo.value = '';
                filterStatus.value = '';
                filterState = { folio: '', maquina: '', tipo: '', status: '' };
                applyFilters();
            });

            // Aplicar filtros iniciales si hay valores en los campos
            applyFilters();
        }
    })();
</script>
@endsection
