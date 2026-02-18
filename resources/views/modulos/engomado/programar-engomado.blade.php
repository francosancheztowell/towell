@extends('layouts.app')

@section('page-title', 'Programar Engomado')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
            onclick="return irProduccion(event)"
            title="Cargar Información"
            icon="fa-download"
            iconColor="text-white"
            hoverBg="hover:bg-blue-600"
            text="Cargar"
            bg="bg-blue-500"
            module="Programa Engomado"
        />
        <x-navbar.button-edit
            onclick="abrirModalEditarPrioridad()"
            title="Editar Prioridad"
            icon="fa-sort-numeric-up"
            iconColor="text-white"
            hoverBg="hover:bg-purple-600"
            text="Editar Prioridad"
            bg="bg-purple-600"
            module="Programa Engomado"
        />
        <x-navbar.button-edit
            onclick="window.location.href='{{ route('engomado.reimpresion.finalizadas') }}'"
            title="Reimpresion"
            icon="fa-print"
            iconColor="text-white"
            hoverBg="hover:bg-green-600"
            text="Reimpresion"
            bg="bg-green-500"
            module="Programa Engomado"
        />
    </div>
@endsection

@section('content')
    <div class="w-full h-full">
        <div class="grid grid-cols-2 gap-2 w-full">
            @for ($i = 1; $i <= 2; $i++)
                <div class="w-full">
                    <h2 class="text-xl font-semibold text-white text-center bg-blue-500 py-1 rounded-t-xl">
                        Tabla {{ $i === 1 ? 'West Point 2' : 'West Point 3' }}
                    </h2>

                    <div class="h-[600px] border border-gray-300 border-t-0 rounded-b-xl bg-white flex flex-col overflow-hidden w-full">
                        <div class="overflow-x-auto overflow-y-auto flex-1 w-full">
                            <table class="w-full table-auto border-collapse min-w-full">
                                <thead class="sticky top-0 bg-gray-100 z-10">
                                    <tr class="bg-gray-100 h-6 leading-6">
                                        @php
                                            $thBaseClasses = 'px-2 py-0 text-center font-semibold text-sm border border-gray-300 align-middle h-6 leading-6';
                                        @endphp
                                        <th class="{{ $thBaseClasses }}">Prioridad</th>
                                        <th class="{{ $thBaseClasses }}">Folio</th>
                                        <th class="{{ $thBaseClasses }}">Tipo</th>
                                        <th class="{{ $thBaseClasses }}">Cuenta</th>
                                        <th class="{{ $thBaseClasses }}">Calibre</th>
                                        <th class="{{ $thBaseClasses }}">Metros</th>
                                        <th class="{{ $thBaseClasses }}">Formula</th>
                                        <th class="{{ $thBaseClasses }}">Estado</th>
                                        <th class="{{ $thBaseClasses }}">Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla{{ $i }}TableBody" class="bg-white">
                                    <tr>
                                        <td colspan="9" class="px-2 py-2 text-center text-gray-500 text-2xl">
                                            <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500 mx-auto"></div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endfor
        </div>
    </div>

    <!-- Modal Editar Prioridad -->
    <div id="modalEditarPrioridad" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
        <div class="relative bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 my-8">
            <!-- Header del Modal -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800">Editar Prioridad de Órdenes</h2>
                <button type="button" onclick="cerrarModalEditarPrioridad()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Body del Modal -->
            <div class="p-6">
                <div class="overflow-x-auto max-h-[600px]">
                    <table class="w-full table-auto border-collapse">
                        <thead class="sticky top-0 bg-gray-100 z-10">
                            <tr class="bg-gray-100">
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Prioridad</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Folio</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Tipo</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Cuenta</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Calibre</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Metros</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Tabla</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Status</th>
                            </tr>
                        </thead>
                        <tbody id="modalPrioridadTableBody" class="bg-white">
                            <tr>
                                <td colspan="8" class="px-3 py-4 text-center text-gray-500">
                                    <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500 mx-auto"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer del Modal -->
            <div class="flex justify-end gap-2 p-6 border-t border-gray-200">
                <button type="button" onclick="cerrarModalEditarPrioridad()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                    Cancelar
                </button>
                <button type="button" onclick="guardarPrioridades()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                    Guardar Cambios
                </button>
            </div>
        </div>
    </div>

    @php
        // Habilitar drag and drop para todos los usuarios
        $puedeEditar = true;
    @endphp

    <script>
        (() => {
            // ==========================
            // Config & Estado Global
            // ==========================
            const routes = {
                cargarOrdenes: '{{ route('engomado.programar.engomado.ordenes') }}',
                verificarEnProceso: '{{ route('engomado.programar.engomado.verificar.en.proceso') }}',
                intercambiarPrioridad: '{{ route('engomado.programar.engomado.intercambiar.prioridad') }}',
                produccion: '{{ route('engomado.modulo.produccion.engomado') }}',
                guardarObservaciones: '{{ route('engomado.programar.engomado.guardar.observaciones') }}',
                obtenerTodasOrdenes: '{{ route('engomado.programar.engomado.todas.ordenes') }}',
                actualizarPrioridades: '{{ route('engomado.programar.engomado.actualizar.prioridades') }}',
                actualizarStatus: '{{ route('engomado.programar.engomado.actualizar.status') }}',
            };

            const csrfToken = '{{ csrf_token() }}';
            const canEdit = {{ $puedeEditar ? 'true' : 'false' }};

            const state = {
                ordenes: {},            // { 1: [..], 2: [..] }
                ordenSeleccionada: null, // { id, tabla, ... }
                dragSource: null,        // { id, tabla, index }
                dragTarget: null,        // { id, tabla, index }
                todasOrdenes: []        // Todas las órdenes para el modal de prioridad
            };

            // ==========================
            // Helpers UI
            // ==========================
            const showToast = (icon, title) => {
                if (typeof Swal === 'undefined') {
                    if (title) alert(title);
                    return;
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon,
                    title,
                    showConfirmButton: false,
                    timer: 800,
                });
            };

            const showError = (message, title = 'Error') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title,
                    text: message,
                    confirmButtonColor: '#2563eb',
                });
            };

            const setButtonsEnabled = (enabled) => {
                // Los botones se manejan por la validación en irProduccion()
            };

            // Badge de tipo (Rizo / Pie / Otro)
            const renderTipoBadge = (tipo, isSelected = false) => {
                const normalized = String(tipo || '').toUpperCase().trim();

                const baseClasses =
                    'px-1 py-0.5 rounded text-[10px] font-medium leading-tight';
                const selectedBase = `${baseClasses} bg-white border`;
                const normalBase = `${baseClasses}`;

                if (normalized === 'RIZO') {
                    return isSelected
                        ? `<span class="${selectedBase} text-rose-700 border-rose-300">Rizo</span>`
                        : `<span class="${normalBase} bg-rose-100 text-rose-700">Rizo</span>`;
                }

                if (normalized === 'PIE') {
                    return isSelected
                        ? `<span class="${selectedBase} text-teal-700 border-teal-300">Pie</span>`
                        : `<span class="${normalBase} bg-teal-100 text-teal-700">Pie</span>`;
                }

                const label = tipo || '-';
                return isSelected
                    ? `<span class="${selectedBase} text-gray-800 border-gray-300">${label}</span>`
                    : `<span class="${normalBase} bg-gray-200 text-gray-800">${label}</span>`;
            };

            const renderStatusSelect = (orden, isSelected = false) => {
                const statusActual = String(orden.status || '').trim();
                let opciones;
                if (statusActual === 'En Proceso') {
                    opciones = ['Programado', 'En Proceso', 'Cancelado'];
                } else if (statusActual === 'Parcial') {
                    opciones = ['Programado', 'En Proceso', 'Parcial', 'Cancelado'];
                } else {
                    opciones = ['Programado', 'Cancelado'];
                }
                const disabledAttr = canEdit ? '' : 'disabled';
                const baseClasses = isSelected
                    ? 'w-full h-9 px-2 border-0 bg-blue-500 text-white'
                    : 'w-full h-9 px-2 border-0 bg-transparent text-gray-900';
                const disabledClasses = canEdit ? '' : 'opacity-70 cursor-not-allowed';

                const optionsHtml = opciones.map((status) => {
                    const selected = statusActual === status ? 'selected' : '';
                    return `<option value="${status}" ${selected}>${status}</option>`;
                }).join('');

                return `
                    <select
                        class="${baseClasses} ${disabledClasses}"
                        data-orden-id="${orden.id}"
                        data-current="${statusActual}"
                        onchange="actualizarStatus(event, ${orden.id})"
                        onmousedown="event.stopPropagation()"
                        onclick="event.stopPropagation()"
                        ${disabledAttr}
                    >
                        ${optionsHtml}
                    </select>
                `;
            };

            // ==========================
            // Renderizado Tablas
            // ==========================
            const renderTable = (tabla) => {
                const tbodyId = `tabla${tabla}TableBody`;
                const tbody = document.getElementById(tbodyId);
                if (!tbody) return;

                const ordenes = state.ordenes[tabla] || [];

                if (!ordenes.length) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="px-2 py-2 text-center text-gray-500 text-xl">
                                No hay órdenes pendientes
                            </td>
                        </tr>
                    `;
                    return;
                }

                const baseTd =
                    'px-2 py-0 text-sm border border-gray-300 whitespace-nowrap align-middle h-9 leading-9';

                const rowsHtml = ordenes.map((orden, index) => {
                    const isSelected = state.ordenSeleccionada?.id === orden.id;
                    const prioridad = orden.prioridad ?? (index + 1);

                    // Marcar con azul si urdido está finalizado
                    const urdidoFinalizado = orden.urdido_finalizado === true;
                    const bgColor = urdidoFinalizado ? 'bg-blue-200 ' : '';

                    const rowClasses = isSelected
                        ? `bg-blue-500 text-white h-9 transition-all duration-200 ${bgColor}`
                        : `hover:bg-gray-50 h-9 transition-all duration-200 select-none ${bgColor}`;

                    const rowCursorClass = canEdit ? 'cursor-move' : 'cursor-default';

                    const metros = orden.metros
                        ? Math.round(parseFloat(orden.metros))
                        : '';

                    const dragIcon = canEdit
                        ? '<i class="fas fa-grip-vertical text-gray-400 mr-1"></i>'
                        : '';

                    const observacionesCell = canEdit
                        ? `
                                <input
                                    type="text"
                                    class="w-full h-9 px-2 py-0 border-0 outline-none bg-transparent focus:bg-blue-50 ${isSelected ? 'text-white focus:text-gray-900' : 'text-gray-900'}"
                                    value="${(orden.observaciones || '').replace(/"/g, '&quot;')}"
                                    data-orden-id="${orden.id}"
                                    draggable="false"
                                    onmousedown="event.stopPropagation()"
                                    onclick="event.stopPropagation()"
                                    onblur="guardarObservaciones(event, ${orden.id})"
                                    onkeydown="if(event.key === 'Enter') event.target.blur()"
                                    placeholder="Escriba observaciones..."
                                    maxlength="60"
                                />
                        `
                        : `<span class="px-2 text-gray-700">${orden.observaciones || ''}</span>`;

                    return `
                        <tr
                            class="${rowClasses} ${rowCursorClass}"
                            data-orden-id="${orden.id}"
                            data-tabla="${tabla}"
                            data-index="${index}"
                            draggable="${canEdit ? 'true' : 'false'}"
                        >
                            <td class="${baseTd} text-center font-semibold">
                                ${dragIcon}${prioridad}
                            </td>
                            <td class="${baseTd}">${orden.folio || ''}</td>
                            <td class="${baseTd} text-center">${renderTipoBadge(orden.tipo, isSelected)}</td>
                            <td class="${baseTd}">${orden.cuenta || ''}</td>
                            <td class="${baseTd}">${orden.calibre || ''}</td>
                            <td class="${baseTd}">${metros}</td>
                            <td class="${baseTd}">${orden.formula || '-'}</td>
                            <td class="${baseTd} ${canEdit ? 'p-0' : ''}">
                                ${canEdit ? renderStatusSelect(orden, isSelected) : (orden.status || '')}
                            </td>
                            <td class="${baseTd} ${canEdit ? 'p-0' : ''}">
                                ${observacionesCell}
                            </td>
                        </tr>
                    `;
                }).join('');

                tbody.innerHTML = rowsHtml;

                // Configurar eventos drag and drop para esta tabla
                setupDragAndDrop(tabla);
            };

            const renderAllTables = () => {
                for (let tabla = 1; tabla <= 2; tabla++) {
                    renderTable(tabla);
                }
            };

            // ==========================
            // Selección de Orden
            // ==========================
            const handleRowClick = (row) => {
                const ordenId = Number(row.dataset.ordenId);
                const tabla = Number(row.dataset.tabla);

                const orden = (state.ordenes[tabla] || []).find(o => o.id === ordenId);
                if (!orden) return;

                state.ordenSeleccionada = orden;
                setButtonsEnabled(true);
                renderAllTables();
            };

            const setupRowClickDelegates = () => {
                for (let tabla = 1; tabla <= 2; tabla++) {
                    const tbody = document.getElementById(`tabla${tabla}TableBody`);
                    if (!tbody) continue;

                    tbody.addEventListener('click', (event) => {
                        // No seleccionar si se está haciendo drag o si es un input
                        if (state.dragSource || event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT') {
                            return;
                        }
                        const row = event.target.closest('tr[data-orden-id]');
                        if (!row) return;
                        handleRowClick(row);
                    });
                }
            };

            // ==========================
            // Drag and Drop (solo dentro de la misma tabla)
            // Prioridad única global pero drag solo en misma tabla
            // ==========================
            const setupDragAndDrop = (tabla) => {
                if (!canEdit) {
                    return;
                }

                const tbody = document.getElementById(`tabla${tabla}TableBody`);
                if (!tbody) return;

                const rows = tbody.querySelectorAll('tr[data-orden-id]');

                rows.forEach(row => {
                    row.addEventListener('dragstart', (e) => {
                        // No permitir drag si se hace clic en un input o select
                        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.closest('input') || e.target.closest('select')) {
                            e.preventDefault();
                            return false;
                        }

                        const ordenId = Number(row.dataset.ordenId);
                        const index = Number(row.dataset.index);

                        state.dragSource = {
                            id: ordenId,
                            tabla: tabla,
                            index: index,
                            element: row
                        };

                        row.classList.add('opacity-50', 'bg-gray-300');
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/html', row.outerHTML);
                    });

                    row.addEventListener('dragend', (e) => {
                        // Restaurar apariencia
                        rows.forEach(r => {
                            r.classList.remove('border-t-4', 'border-blue-500', 'bg-blue-100');
                        });

                        if (state.dragSource) {
                            state.dragSource.element.classList.remove('opacity-50', 'bg-gray-300');
                        }

                        state.dragSource = null;
                        state.dragTarget = null;
                    });

                    row.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';

                        if (!state.dragSource) return;

                        // Solo permitir drop en la misma tabla
                        if (Number(row.dataset.tabla) !== state.dragSource.tabla) {
                            e.dataTransfer.dropEffect = 'none';
                            return;
                        }

                        // Limpiar clases anteriores
                        rows.forEach(r => {
                            r.classList.remove('border-t-4', 'border-blue-500', 'bg-blue-100');
                        });

                        // Agregar indicador visual
                        row.classList.add('border-t-4', 'border-blue-500', 'bg-blue-100');
                    });

                    row.addEventListener('dragleave', (e) => {
                        // Solo limpiar si realmente se sale de la fila
                        if (!row.contains(e.relatedTarget)) {
                            row.classList.remove('border-t-4', 'border-blue-500', 'bg-blue-100');
                        }
                    });

                    row.addEventListener('drop', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        if (!state.dragSource) return;

                        const targetOrdenId = Number(row.dataset.ordenId);
                        const targetIndex = Number(row.dataset.index);
                        const sourceIndex = state.dragSource.index;

                        // Verificar que sea la misma tabla
                        if (Number(row.dataset.tabla) !== state.dragSource.tabla) {
                            rows.forEach(r => {
                                r.classList.remove('border-t-4', 'border-blue-500', 'bg-blue-100');
                            });
                            return;
                        }

                        // Si es la misma posición, no hacer nada
                        if (state.dragSource.id === targetOrdenId || sourceIndex === targetIndex) {
                            rows.forEach(r => {
                                r.classList.remove('border-t-4', 'border-blue-500', 'bg-blue-100');
                            });
                            return;
                        }

                        // Intercambiar prioridades
                        await intercambiarPrioridad(
                            state.dragSource.id,
                            targetOrdenId
                        );
                    });
                });
            };

            // ==========================
            // Fetch helpers
            // ==========================
            const fetchJson = async (url, options = {}) => {
                const defaultOptions = {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    ...options,
                };

                const response = await fetch(url, defaultOptions);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            };

            // ==========================
            // Cargar Órdenes
            // ==========================
            const cargarOrdenes = async (silent = false) => {
                try {
                    const result = await fetchJson(routes.cargarOrdenes);

                    if (!result.success) {
                        throw new Error(result.error || 'Error al cargar órdenes');
                    }

                    const ordenes = result.data || {};
                    state.ordenes = ordenes;

                    const ordenAnterior = state.ordenSeleccionada;
                    renderAllTables();

                    // Intentar restaurar selección
                    if (ordenAnterior) {
                        const tabla = ordenAnterior.tabla;
                        const ordenActualizada = (state.ordenes[tabla] || [])
                            .find(o => o.id === ordenAnterior.id);

                        if (ordenActualizada) {
                            state.ordenSeleccionada = ordenActualizada;
                            renderTable(tabla);
                            setButtonsEnabled(true);
                        } else {
                            state.ordenSeleccionada = null;
                            setButtonsEnabled(false);
                        }
                    } else {
                        setButtonsEnabled(false);
                    }

                    if (!silent) {
                        showToast('success', 'Órdenes cargadas correctamente');
                    }
                } catch (error) {
                    console.error('Error al cargar órdenes:', error);
                    showError(`Error al cargar órdenes: ${error.message}`);
                }
            };

            // ==========================
            // Intercambiar Prioridad (Drag and Drop)
            // ==========================
            const intercambiarPrioridad = async (sourceId, targetId) => {
                try {
                    const payload = JSON.stringify({
                        source_id: sourceId,
                        target_id: targetId
                    });

                    const result = await fetchJson(routes.intercambiarPrioridad, {
                        method: 'POST',
                        body: payload,
                    });

                    if (!result.success) {
                        throw new Error(result.error || 'Error al intercambiar prioridad');
                    }

                    const ordenSeleccionadaId = state.ordenSeleccionada?.id;

                    // Recargar sin duplicar notificaciones
                    await cargarOrdenes(true);

                    // Restaurar selección si sigue existiendo
                    if (ordenSeleccionadaId) {
                        for (let t = 1; t <= 2; t++) {
                            const orden = (state.ordenes[t] || []).find(o => o.id === ordenSeleccionadaId);
                            if (orden) {
                                state.ordenSeleccionada = orden;
                                renderAllTables();
                                break;
                            }
                        }
                    }

                    showToast('success', result.message || 'Prioridad actualizada correctamente');
                } catch (error) {
                    console.error('Error al intercambiar prioridad:', error);
                    showError(`Error al intercambiar prioridad: ${error.message}`);
                }
            };

            // ==========================
            // Guardar Observaciones
            // ==========================
            const guardarObservaciones = async (event, ordenId) => {
                if (!canEdit) {
                    showToast('warning', 'No autorizado');
                    return;
                }

                const input = event.target;
                const observaciones = input.value.trim();

                try {
                    const payload = JSON.stringify({
                        id: ordenId,
                        observaciones: observaciones
                    });

                    const result = await fetchJson(routes.guardarObservaciones, {
                        method: 'POST',
                        body: payload,
                    });

                    if (!result.success) {
                        throw new Error(result.error || 'Error al guardar observaciones');
                    }

                    // Actualizar el estado local
                    for (let tabla = 1; tabla <= 2; tabla++) {
                        const orden = (state.ordenes[tabla] || []).find(o => o.id === ordenId);
                        if (orden) {
                            orden.observaciones = observaciones;
                            break;
                        }
                    }

                    showToast('success', 'Observaciones guardadas correctamente');
                } catch (error) {
                    console.error('Error al guardar observaciones:', error);
                    showError(`Error al guardar observaciones: ${error.message}`);
                    // Restaurar valor anterior si falla
                    input.value = observaciones;
                }
            };

            // ==========================
            // Actualizar Status
            // ==========================
            const actualizarStatus = async (event, ordenId) => {
                if (!canEdit) {
                    showToast('warning', 'No autorizado');
                    return;
                }

                const select = event.target;
                const nuevoStatus = select.value;
                const statusAnterior = select.dataset.current || '';

                if (!nuevoStatus || nuevoStatus === statusAnterior) {
                    return;
                }

                select.disabled = true;

                try {
                    const payload = JSON.stringify({
                        id: ordenId,
                        status: nuevoStatus,
                    });

                    const result = await fetchJson(routes.actualizarStatus, {
                        method: 'POST',
                        body: payload,
                    });

                    if (!result.success) {
                        throw new Error(result.error || 'Error al actualizar status');
                    }

                    select.dataset.current = nuevoStatus;

                    for (let tabla = 1; tabla <= 2; tabla++) {
                        const orden = (state.ordenes[tabla] || []).find(o => o.id === ordenId);
                        if (orden) {
                            orden.status = nuevoStatus;
                            break;
                        }
                    }

                    showToast('success', 'Status actualizado correctamente');
                    await cargarOrdenes(true);
                } catch (error) {
                    console.error('Error al actualizar status:', error);
                    select.value = statusAnterior;
                    showError(`Error al actualizar status: ${error.message}`);
                } finally {
                    select.disabled = false;
                }
            };

            // ==========================
            // Ir a Producción
            // ==========================
            const irProduccion = async (event) => {
                // Prevenir comportamiento por defecto si hay un evento
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                if (!state.ordenSeleccionada) {
                    showToast('warning', 'Seleccione una orden');
                    return;
                }

                // Verificar que la orden tenga urdido finalizado en UrdProgramaUrdido
                if (state.ordenSeleccionada.urdido_finalizado !== true) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Urdido no finalizado',
                            html: '<p class="text-gray-700">La orden seleccionada aún no tiene el urdido finalizado en UrdProgramaUrdido.</p><p class="text-gray-600 text-sm mt-2">Solo las líneas en azul (urdido finalizado) se pueden cargar a producción.</p>',
                            confirmButtonColor: '#2563eb',
                            confirmButtonText: 'Entendido'
                        });
                    } else {
                        alert('La orden seleccionada aún no tiene el urdido finalizado. Solo las líneas en azul se pueden cargar a producción.');
                    }
                    return;
                }

                // Verificación de órdenes en proceso eliminada - se permite cualquier cantidad

                // Verificar si el usuario puede crear registros (con timeout)
                try {
                    const checkUrl = `${routes.produccion}?orden_id=${state.ordenSeleccionada.id}&check_only=true`;

                    // Crear un timeout para la petición
                    const timeoutPromise = new Promise((_, reject) =>
                        setTimeout(() => reject(new Error('Timeout')), 3000)
                    );

                    const fetchPromise = fetch(checkUrl, {
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                        }
                    });

                    const response = await Promise.race([fetchPromise, timeoutPromise]);

                    if (response && response.ok) {
                        const data = await response.json();

                        // Si no puede crear y no hay registros existentes, mostrar error
                        if (!data.puedeCrear && !data.tieneRegistros) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Acceso Denegado',
                                    html: `
                                        <p class="mb-2">No tienes permisos para crear registros en este módulo.</p>
                                        <p class="text-sm text-gray-600">Solo usuarios del área <strong>Engomado</strong> pueden crear registros.</p>
                                        <p class="text-sm text-gray-600 mt-2">Tu área actual: <strong>${data.usuarioArea || 'No definida'}</strong></p>
                                    `,
                                    confirmButtonColor: '#2563eb',
                                });
                            } else {
                                alert('No tienes permisos para crear registros. Solo usuarios del área Engomado pueden crear registros.');
                            }
                            return;
                        }
                    }
                } catch (error) {
                    console.error('Error al verificar permisos (continuando con redirección):', error);
                    // Continuar con la redirección si hay error en la verificación
                }

                // Si puede crear o hay registros existentes, redirigir
                const url = `${routes.produccion}?orden_id=${state.ordenSeleccionada.id}`;

                // Verificar que la URL sea válida
                if (!url || url.includes('undefined') || url.includes('null')) {
                    console.error('URL inválida para redirección:', url);
                    showError('Error: No se pudo construir la URL de redirección. Por favor, intente nuevamente.');
                    return false;
                }

                // Redirigir inmediatamente - FORZAR navegación de múltiples formas

                // Método 1: location.replace (más difícil de interceptar)
                window.location.replace(url);

                // Método 2: location.href como respaldo inmediato
                window.location.href = url;

                // Método 3: Si aún no funciona, usar window.open después de un delay muy corto
                setTimeout(() => {
                    if (window.location.href !== url && !window.location.href.includes('modulo-produccion-engomado')) {
                        window.open(url, '_self');
                    }
                }, 50);

                // NO retornar nada - dejar que la función termine naturalmente
                // Esto permite que la navegación se ejecute sin interferencias
            };

            // ==========================
            // Modal Editar Prioridad
            // ==========================
            const abrirModalEditarPrioridad = async () => {
                const modal = document.getElementById('modalEditarPrioridad');
                if (!modal) return;

                modal.style.display = 'flex';
                await cargarTodasOrdenes();
            };

            const cerrarModalEditarPrioridad = () => {
                const modal = document.getElementById('modalEditarPrioridad');
                if (modal) {
                    modal.style.display = 'none';
                }
            };

            const cargarTodasOrdenes = async () => {
                try {
                    const tbody = document.getElementById('modalPrioridadTableBody');
                    if (!tbody) return;

                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-gray-500">
                                <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500 mx-auto"></div>
                            </td>
                        </tr>
                    `;

                    const result = await fetchJson(routes.obtenerTodasOrdenes);

                    if (!result.success) {
                        throw new Error(result.error || 'Error al cargar órdenes');
                    }

                    const ordenes = result.data || [];
                    state.todasOrdenes = ordenes;

                    renderModalPrioridadTable();
                } catch (error) {
                    console.error('Error al cargar todas las órdenes:', error);
                    showError(`Error al cargar órdenes: ${error.message}`);
                    const tbody = document.getElementById('modalPrioridadTableBody');
                    if (tbody) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" class="px-3 py-4 text-center text-red-500">
                                    Error al cargar órdenes
                                </td>
                            </tr>
                        `;
                    }
                }
            };

            const renderModalPrioridadTable = () => {
                const tbody = document.getElementById('modalPrioridadTableBody');
                if (!tbody) return;

                const ordenes = state.todasOrdenes || [];

                if (!ordenes.length) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-gray-500">
                                No hay órdenes disponibles
                            </td>
                        </tr>
                    `;
                    return;
                }

                const baseTd = 'px-3 py-2 text-sm border border-gray-300 whitespace-nowrap align-middle';

                const rowsHtml = ordenes.map((orden, index) => {
                    const prioridad = orden.prioridad ?? (index + 1);
                    const metros = orden.metros ? Math.round(parseFloat(orden.metros)) : '';

                    return `
                        <tr
                            class="hover:bg-gray-50 cursor-move transition-all duration-200"
                            data-orden-id="${orden.id}"
                            data-index="${index}"
                            draggable="true"
                        >
                            <td class="${baseTd} text-center font-semibold">
                                <i class="fas fa-grip-vertical text-gray-400 mr-1"></i>${prioridad}
                            </td>
                            <td class="${baseTd}">${orden.folio || ''}</td>
                            <td class="${baseTd} text-center">${renderTipoBadge(orden.tipo, false)}</td>
                            <td class="${baseTd}">${orden.cuenta || ''}</td>
                            <td class="${baseTd}">${orden.calibre || ''}</td>
                            <td class="${baseTd}">${metros}</td>
                            <td class="${baseTd}">${orden.tabla || ''}</td>
                            <td class="${baseTd}">${orden.status || ''}</td>
                        </tr>
                    `;
                }).join('');

                tbody.innerHTML = rowsHtml;

                // Configurar drag and drop para el modal
                setupModalDragAndDrop();
            };

            const setupModalDragAndDrop = () => {
                if (!canEdit) {
                    return;
                }

                const tbody = document.getElementById('modalPrioridadTableBody');
                if (!tbody) return;

                const rows = tbody.querySelectorAll('tr[data-orden-id]');
                let dragSource = null;

                rows.forEach(row => {
                    row.addEventListener('dragstart', (e) => {
                        const ordenId = Number(row.dataset.ordenId);
                        const index = Number(row.dataset.index);

                        dragSource = {
                            id: ordenId,
                            index: index,
                            element: row
                        };

                        row.classList.add('opacity-50', 'bg-gray-300');
                        e.dataTransfer.effectAllowed = 'move';
                    });

                    row.addEventListener('dragend', (e) => {
                        rows.forEach(r => {
                            r.classList.remove('border-t-4', 'border-blue-500', 'bg-blue-100');
                        });

                        if (dragSource) {
                            dragSource.element.classList.remove('opacity-50', 'bg-gray-300');
                        }

                        dragSource = null;
                    });

                    row.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';

                        if (!dragSource) return;

                        rows.forEach(r => {
                            r.classList.remove('border-t-4', 'border-blue-500', 'bg-blue-100');
                        });

                        row.classList.add('border-t-4', 'border-blue-500', 'bg-blue-100');
                    });

                    row.addEventListener('dragleave', (e) => {
                        if (!row.contains(e.relatedTarget)) {
                            row.classList.remove('border-t-4', 'border-blue-500', 'bg-blue-100');
                        }
                    });

                    row.addEventListener('drop', (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        if (!dragSource) return;

                        const targetIndex = Number(row.dataset.index);
                        const sourceIndex = dragSource.index;

                        if (sourceIndex === targetIndex) {
                            rows.forEach(r => {
                                r.classList.remove('border-t-4', 'border-blue-500', 'bg-blue-100');
                            });
                            return;
                        }

                        // Reordenar en el array
                        const ordenes = [...state.todasOrdenes];
                        const [removed] = ordenes.splice(sourceIndex, 1);
                        ordenes.splice(targetIndex, 0, removed);

                        // Actualizar índices y prioridades
                        ordenes.forEach((orden, idx) => {
                            orden.prioridad = idx + 1;
                        });

                        state.todasOrdenes = ordenes;
                        renderModalPrioridadTable();
                    });
                });
            };

            const guardarPrioridades = async () => {
                try {
                    const ordenes = state.todasOrdenes || [];

                    // Preparar datos: array de {id, prioridad}
                    const prioridades = ordenes.map((orden, index) => ({
                        id: orden.id,
                        prioridad: orden.prioridad ?? (index + 1)
                    }));

                    const payload = JSON.stringify({ prioridades });

                    const result = await fetchJson(routes.actualizarPrioridades, {
                        method: 'POST',
                        body: payload,
                    });

                    if (!result.success) {
                        throw new Error(result.error || 'Error al guardar prioridades');
                    }

                    showToast('success', 'Prioridades guardadas correctamente');
                    cerrarModalEditarPrioridad();

                    // Recargar las órdenes en la vista principal
                    await cargarOrdenes(true);
                } catch (error) {
                    console.error('Error al guardar prioridades:', error);
                    showError(`Error al guardar prioridades: ${error.message}`);
                }
            };

            // ==========================
            // API pública (para onclick del Blade)
            // ==========================
            window.cargarOrdenes = cargarOrdenes;
            window.irProduccion = irProduccion;
            window.guardarObservaciones = guardarObservaciones;
            window.actualizarStatus = actualizarStatus;
            window.abrirModalEditarPrioridad = abrirModalEditarPrioridad;
            window.cerrarModalEditarPrioridad = cerrarModalEditarPrioridad;
            window.guardarPrioridades = guardarPrioridades;

            // ==========================
            // Init
            // ==========================
            document.addEventListener('DOMContentLoaded', () => {
                setButtonsEnabled(false);
                setupRowClickDelegates();
                cargarOrdenes();
            });
        })();
    </script>
@endsection
