@extends('layouts.app')

@section('page-title', 'Programar Urdido')

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
            module="Programa Urdido"
        />
        <x-navbar.button-edit
            onclick="abrirModalEditarPrioridad()"
            title="Editar Prioridad"
            icon="fa-sort-numeric-up"
            iconColor="text-white"
            hoverBg="hover:bg-purple-600"
            text="Editar Prioridad"
            bg="bg-purple-600"
            module="Programa Urdido"
        />
        <x-navbar.button-edit
            onclick="window.location.href='{{ $programaRoutes['reimpresion'] }}'"
            title="Reimpresion"
            icon="fa-print"
            iconColor="text-white"
            hoverBg="hover:bg-green-600"
            text="Reimpresion"
            bg="bg-green-500"
            module="Programa Urdido"
        />
        <x-navbar.button-report
            id="btnCalidad"
            onclick="abrirModalCalidad()"
            title="Evaluación de Calidad"
            icon="fa-clipboard-check"
            text="Calidad"
            bg="bg-amber-500"
            iconColor="text-white"
            hoverBg="hover:bg-amber-600"
            module="Programa Urdido"
        />
    </div>
@endsection

@section('content')
    <div class="w-full">
        <div class="grid grid-cols-2 gap-2">
            @for ($i = 1; $i <= 4; $i++)
                <div>
                    <h2 class="text-xl font-semibold text-white text-center bg-blue-500 py-1 rounded-t-xl">
                        @if($i == 4)
                            Karl Mayer
                        @else
                            MC Coy {{ $i }}
                        @endif
                    </h2>

                    <div class="h-[256px] border border-gray-300 border-t-0 rounded-b-xl bg-white flex flex-col overflow-hidden">
                        <div class="overflow-x-auto overflow-y-auto flex-1">
                            <table class="w-full table-auto border-collapse">
                                <thead class="sticky top-0 bg-gray-100 z-10">
                                    <tr class="bg-gray-100 h-6 leading-6">
                                        @php
                                            $thBaseClasses = 'px-2 py-0 text-center font-semibold text-sm border border-gray-300 align-middle h-6 leading-6';
                                        @endphp
                                        <th class="{{ $thBaseClasses }}">Prioridad</th>
                                        <th class="{{ $thBaseClasses }}">Folio</th>
                                        <th class="{{ $thBaseClasses }}">@if($i == 4)Barras @else Tipo @endif</th>
                                        <th class="{{ $thBaseClasses }}">Cuenta/Calibre</th>
                                        <th class="{{ $thBaseClasses }}">Configuración</th>
                                        <th class="{{ $thBaseClasses }}">Metros</th>
                                        <th class="{{ $thBaseClasses }}">Status</th>
                                        <th class="{{ $thBaseClasses }}">Observaciones</th>
                                        <th class="{{ $thBaseClasses }}">Calidad</th>
                                    </tr>
                                </thead>
                                <tbody id="mcCoy{{ $i }}TableBody" class="bg-white">
                                    <tr>
                                        <td colspan="10" class="px-2 py-2 text-center text-gray-500 text-2xl">
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
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Cuenta/Calibre</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Configuración</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Metros</th>
                                <th class="px-3 py-2 text-center font-semibold text-sm border border-gray-300">Máquina</th>
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
                <button type="button" id="btnGuardarPrioridades" onclick="guardarPrioridades()"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-2">
                    <span id="btnGuardarPrioridadesSpinner" class="hidden animate-spin rounded-full h-4 w-4 border-2 border-white/40 border-t-white"></span>
                    <span id="btnGuardarPrioridadesText">Guardar Cambios</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Detalle de Calidad (EDICIÓN) -->
    <div id="modalCalidad" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
        <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 my-8">
            <!-- Header: el folio vive aquí, no en el cuerpo -->
            <div class="flex items-start justify-between gap-4 p-5 border-b border-gray-200">
                <div class="min-w-0">
                    <h2 class="text-xl font-bold text-gray-800">Evaluación de Calidad</h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Folio <span id="modalCalidadFolio" class="font-semibold text-gray-700">—</span>
                    </p>
                </div>
                <button type="button" onclick="cerrarModalCalidad()" class="shrink-0 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="p-5 space-y-4">
                <!-- Checklist: un botón por punto, clic alterna bueno/malo. El estado se deriva de los 4. -->
                <div id="calidadChecklist" class="space-y-2">
                    @foreach ($calidadPuntos as $campo => $etiqueta)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2">
                            <span class="text-sm text-gray-700">{{ $etiqueta }}</span>
                            <button type="button" data-punto="{{ $campo }}" data-valor=""
                                onclick="alternarPuntoCalidad(this)" title="Clic para alternar bueno / malo"
                                class="h-11 w-11 shrink-0 rounded-lg border-2 border-gray-300 text-lg font-bold text-gray-400 transition-colors">—</button>
                        </div>
                    @endforeach
                </div>

                <!-- Observaciones textarea -->
                <div>
                    <label for="calidadcomentario" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Observaciones</label>
                    <textarea id="calidadcomentario" rows="2" maxlength="{{ $calidadComentarioMaxLength }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                        placeholder="Opcional, máx. {{ $calidadComentarioMaxLength }} caracteres"></textarea>
                </div>

            </div>

            <!-- Footer -->
            <div class="flex gap-2 p-5 pt-0">
                <button type="button" onclick="cerrarModalCalidad()"
                    class="flex-1 px-8 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                    Cancelar
                </button>
                <button type="button" id="btnGuardarCalidad" onclick="guardarCalidad()"
                    class="flex-1 px-8 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="btnGuardarCalidadText">Guardar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Ver Calidad (SOLO LECTURA) -->
    <div id="modalVerCalidad" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" style="display: none;">
        <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 my-8">
            <!-- Header: título, folio y el estado como distintivo -->
            <div class="flex items-start justify-between gap-4 p-6 border-b border-gray-200">
                <div class="min-w-0">
                    <h2 class="text-xl font-bold text-gray-800">Detalle de Calidad</h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Folio <span id="modalVerCalidadFolio" class="font-semibold text-gray-700">—</span>
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span id="modalVerCalidadEstado"
                        class="rounded-full border px-3 py-1 text-sm font-semibold border-gray-200 bg-gray-100 text-gray-600">Sin evaluar</span>
                    <button type="button" onclick="cerrarModalVerCalidad()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-5">
                <!-- Quién autorizó y cuándo, lo primero que se busca al abrir -->
                <dl class="grid grid-cols-2 gap-4 rounded-lg bg-gray-50 px-4 py-3">
                    <div class="min-w-0">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Autorizó</dt>
                        <dd id="modalVerCalidadAutoriza" class="mt-0.5 break-words text-sm font-medium text-gray-800">—</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha y hora</dt>
                        <dd id="modalVerCalidadFecha" class="mt-0.5 text-sm font-medium text-gray-800">—</dd>
                    </div>
                </dl>

                <!-- Checklist (solo lectura) -->
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Puntos revisados</h3>
                    <ul id="modalVerCalidadChecklist" class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200"></ul>
                </div>

                <!-- Observaciones: texto plano, y sólo si las hay -->
                <div id="modalVerCalidadObsBloque" hidden>
                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Observaciones</h3>
                    <p id="modalVerCalidadComentario" class="whitespace-pre-line text-sm text-gray-700"></p>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 pt-0">
                <button type="button" onclick="cerrarModalVerCalidad()"
                    class="w-full px-8 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            // ==========================
            // Config & Estado Global
            // ==========================
            const routes = @json($programaRoutes);

            const csrfToken = '{{ csrf_token() }}';
            // Solo usuarios del área Supervisores pueden editar (cambiar status, observaciones)
            let canEdit = {{ json_encode($canEdit ?? false) }};
            // Cambiar prioridad: habilitado para todos con acceso al módulo
            const canChangePrioridad = true;

            const state = {
                ordenes: {},            // { 1: [..], 2: [..], 3: [..], 4: [..] }
                ordenSeleccionada: null, // { id, mccoy, ... }
                dragSource: null,        // { id, mccoy, index }
                dragTarget: null,        // { id, mccoy, index }
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
                const btnProduccion = document.getElementById('btnIrProduccion');
                const btnCalidad = document.getElementById('btnCalidad');

                if (btnProduccion) btnProduccion.disabled = !enabled;
                if (btnCalidad) btnCalidad.disabled = !enabled;
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
                const bloqueadoPorAx = Boolean(orden.bloqueado_por_ax);
                const bloqueadosPorAx = ['Cancelado', 'Programado', 'En Proceso'];
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
                const tituloAx = 'Este folio ya tiene producción en AX (AX = 1). No se puede poner Cancelado, Programado ni En Proceso.';

                const optionsHtml = opciones.map((status) => {
                    const selected = statusActual === status ? 'selected' : '';
                    const opcionBloqueada = bloqueadoPorAx
                        && bloqueadosPorAx.includes(status)
                        && status !== statusActual;
                    const disabledOpt = opcionBloqueada ? 'disabled' : '';
                    const titleOpt = opcionBloqueada ? ` title="${tituloAx}"` : '';
                    return `<option value="${status}" ${selected} ${disabledOpt}${titleOpt}>${status}</option>`;
                }).join('');

                return `
                    <select
                        class="${baseClasses} ${disabledClasses}"
                        data-orden-id="${orden.id}"
                        data-current="${statusActual}"
                        data-bloqueado-por-ax="${bloqueadoPorAx ? '1' : '0'}"
                        title="${bloqueadoPorAx ? tituloAx : ''}"
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
            const renderTable = (mccoy) => {
                const tbodyId = `mcCoy${mccoy}TableBody`;
                const tbody = document.getElementById(tbodyId);
                if (!tbody) return;

                const ordenes = state.ordenes[mccoy] || [];

                if (!ordenes.length) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="10" class="px-2 py-2 text-center text-gray-500 text-xl">
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

                    // Incorrecto = 1 -> fila en rojo (la selección azul sigue teniendo prioridad)
                    const rowClasses = isSelected
                        ? 'bg-blue-500 text-white h-9 transition-all duration-200'
                        : Number(orden.incorrecto) === 1
                            ? 'bg-red-100 text-red-700 font-semibold hover:bg-red-200 h-9 transition-all duration-200 select-none'
                            : 'hover:bg-gray-50 h-9 transition-all duration-200 select-none';

                    const rowCursorClass = canChangePrioridad ? 'cursor-move' : 'cursor-default';

                    const metros = orden.metros
                        ? Math.round(parseFloat(orden.metros))
                        : '';

                    const dragIcon = canChangePrioridad
                        ? '<i class="fas fa-grip-vertical text-gray-400 mr-1"></i>'
                        : '';

                    const observacionesCell = canEdit
                        ? `
                                <input
                                    type="text"
                                    class="w-full h-9 px-2 py-0 border-0 outline-none bg-transparent focus:bg-blue-50 ${isSelected ? 'text-white focus:text-gray-900' : 'text-gray-900'}"
                                    value="${orden.observaciones || ''}"
                                    data-orden-id="${orden.id}"
                                    maxlength="{{ $observacionesMaxLength }}"
                                    draggable="false"
                                    onmousedown="event.stopPropagation()"
                                    onclick="event.stopPropagation()"
                                    onblur="guardarObservaciones(event, ${orden.id})"
                                    onkeydown="if(event.key === 'Enter') event.target.blur()"
                                    placeholder="Escriba observaciones..."
                                />
                        `
                        : `<span class="px-2 text-gray-700">${orden.observaciones || ''}</span>`;

                    const calidadVisual = visualCalidad(orden.calidad);
                    const calidadCell = `<span class="${calidadVisual.iconClass} font-bold text-lg">${calidadVisual.icono}</span>`;

                    return `
                        <tr
                            class="${rowClasses} ${rowCursorClass}"
                            data-orden-id="${orden.id}"
                            data-mccoy="${mccoy}"
                            data-index="${index}"
                            draggable="${canChangePrioridad ? 'true' : 'false'}"
                        >
                            <td class="${baseTd} text-center font-semibold">
                                ${dragIcon}${prioridad}
                            </td>
                            <td class="${baseTd}">${orden.folio || ''}</td>
                            <td class="${baseTd} text-center">${renderTipoBadge(orden.tipo, isSelected)}</td>
                            <td class="${baseTd}">${orden.cuenta_calibre || ''}</td>
                            <td class="${baseTd}">${orden.configuracion || ''}</td>
                            <td class="${baseTd}">${metros}</td>
                            <td class="${baseTd} ${canEdit ? 'p-0' : ''}">
                                ${canEdit ? renderStatusSelect(orden, isSelected) : (orden.status || '')}
                            </td>
                            <td class="${baseTd} ${canEdit ? 'p-0' : ''}">
                                ${observacionesCell}
                            </td>
                            <td class="${baseTd} text-center p-0">
                                <button
                                    type="button"
                                    class="w-full h-9 flex items-center justify-center cursor-pointer"
                                    onclick="abrirModalCalidadPorOrden(${orden.id}, ${mccoy}); event.stopPropagation();"
                                    onmousedown="event.stopPropagation()"
                                >
                                    ${calidadCell}
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');

                tbody.innerHTML = rowsHtml;

                // Configurar eventos drag and drop para esta tabla
                setupDragAndDrop(mccoy);
            };

            const renderAllTables = () => {
                for (let mccoy = 1; mccoy <= 4; mccoy++) {
                    renderTable(mccoy);
                }
            };

            // ==========================
            // Selección de Orden
            // ==========================
            const handleRowClick = (row) => {
                const ordenId = Number(row.dataset.ordenId);
                const mccoy = Number(row.dataset.mccoy);

                const orden = (state.ordenes[mccoy] || []).find(o => o.id === ordenId);
                if (!orden) return;

                // Asegurar que la orden tenga maquina_id
                if (!orden.maquina_id) {
                    // Construir maquina_id basado en mccoy
                    if (mccoy === 4) {
                        orden.maquina_id = 'Karl Mayer';
                    } else {
                        orden.maquina_id = `Mc Coy ${mccoy}`;
                    }
                }

                state.ordenSeleccionada = orden;
                setButtonsEnabled(true);
                renderAllTables();
            };

            const setupRowClickDelegates = () => {
                for (let mccoy = 1; mccoy <= 4; mccoy++) {
                    const tbody = document.getElementById(`mcCoy${mccoy}TableBody`);
                    if (!tbody) continue;

                    tbody.addEventListener('click', (event) => {
                        // No seleccionar si se está haciendo drag o si es un input
                        if (state.dragSource || event.target.tagName === 'INPUT') {
                            return;
                        }
                        const row = event.target.closest('tr[data-orden-id]');
                        if (!row) return;
                        handleRowClick(row);
                    });
                }
            };

            // ==========================
            // Drag and Drop (solo dentro de la misma máquina)
            // Prioridad única global pero drag solo en misma MC Coy
            // ==========================
            const setupDragAndDrop = (mccoy) => {
                if (!canChangePrioridad) {
                    return;
                }

                const tbody = document.getElementById(`mcCoy${mccoy}TableBody`);
                if (!tbody) return;

                const rows = tbody.querySelectorAll('tr[data-orden-id]');

                rows.forEach(row => {
                    row.addEventListener('dragstart', (e) => {
                        // No permitir drag si se hace clic en un input
                        if (e.target.tagName === 'INPUT' || e.target.closest('input')) {
                            e.preventDefault();
                            return false;
                        }

                        const ordenId = Number(row.dataset.ordenId);
                        const index = Number(row.dataset.index);

                        state.dragSource = {
                            id: ordenId,
                            mccoy: mccoy,
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

                        // Solo permitir drop en la misma tabla (mismo MC Coy)
                        if (Number(row.dataset.mccoy) !== state.dragSource.mccoy) {
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

                        // Verificar que sea la misma MC Coy
                        if (Number(row.dataset.mccoy) !== state.dragSource.mccoy) {
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

                        // Intercambiar prioridades (prioridad única global pero drag solo en misma MC Coy)
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
                const data = await response.json().catch(() => null);

                if (!response.ok) {
                    throw new Error(data?.error || `HTTP ${response.status}`);
                }

                return data;
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
                        const mccoy = ordenAnterior.mccoy;
                        const ordenActualizada = (state.ordenes[mccoy] || [])
                            .find(o => o.id === ordenAnterior.id);

                        if (ordenActualizada) {
                            state.ordenSeleccionada = ordenActualizada;
                            renderTable(mccoy);
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
            // Prioridad única global, pero drag solo dentro de misma MC Coy
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
                        for (let m = 1; m <= 4; m++) {
                            const orden = (state.ordenes[m] || []).find(o => o.id === ordenSeleccionadaId);
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
                    for (let mccoy = 1; mccoy <= 4; mccoy++) {
                        const orden = (state.ordenes[mccoy] || []).find(o => o.id === ordenId);
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
                const bloqueadosPorAx = ['Cancelado', 'Programado', 'En Proceso'];

                if (!nuevoStatus || nuevoStatus === statusAnterior) {
                    return;
                }

                if (select.dataset.bloqueadoPorAx === '1' && bloqueadosPorAx.includes(nuevoStatus)) {
                    select.value = statusAnterior;
                    showError('No se puede poner Cancelado, Programado ni En Proceso: este folio ya tiene producción en AX (AX = 1).');
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

                    for (let mccoy = 1; mccoy <= 4; mccoy++) {
                        const orden = (state.ordenes[mccoy] || []).find(o => o.id === ordenId);
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
            let cargandoProduccion = false;

            const irProduccion = async (event) => {
                // Prevenir comportamiento por defecto si hay un evento
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                if (cargandoProduccion) return;

                if (!state.ordenSeleccionada) {
                    showToast('warning', 'Seleccione una orden');
                    return;
                }

                // ponytail: el boton se libera solo si abortamos; si redirige, la navegacion lo descarta
                const btnProduccion = event?.currentTarget instanceof HTMLElement ? event.currentTarget : null;
                cargandoProduccion = true;
                if (btnProduccion) btnProduccion.classList.add('opacity-60', 'pointer-events-none');
                const liberarBoton = () => {
                    cargandoProduccion = false;
                    if (btnProduccion) btnProduccion.classList.remove('opacity-60', 'pointer-events-none');
                };


                // Verificar si ya hay 2 órdenes con status "En Proceso" en la misma máquina
                try {
                    // Obtener el MaquinaId de la orden seleccionada
                    let maquinaId = state.ordenSeleccionada.maquina_id || null;

                    // Si no está en la orden seleccionada, buscarla en el estado
                    if (!maquinaId) {
                        for (let mccoy = 1; mccoy <= 4; mccoy++) {
                            const orden = (state.ordenes[mccoy] || []).find(o => o.id === state.ordenSeleccionada.id);
                            if (orden) {
                                if (orden.maquina_id) {
                                    maquinaId = orden.maquina_id;
                                } else {
                                    // Construir maquina_id basado en mccoy si no existe
                                    if (mccoy === 4) {
                                        maquinaId = 'Karl Mayer';
                                    } else {
                                        maquinaId = `Mc Coy ${mccoy}`;
                                    }
                                }
                                break;
                            }
                        }
                    }

                    // Si aún no tenemos maquina_id, intentar obtenerlo del mccoy de la orden seleccionada
                    if (!maquinaId && state.ordenSeleccionada.mccoy) {
                        const mccoy = state.ordenSeleccionada.mccoy;
                        if (mccoy === 4) {
                            maquinaId = 'Karl Mayer';
                        } else {
                            maquinaId = `Mc Coy ${mccoy}`;
                        }
                    }

                    const verificarUrl = `${routes.verificarEnProceso}?excluir_id=${state.ordenSeleccionada.id}${maquinaId ? `&maquina_id=${encodeURIComponent(maquinaId)}` : ''}`;
                    const verificarResponse = await fetchJson(verificarUrl);

                    if (verificarResponse.success && verificarResponse.tieneOrdenEnProceso) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'No se puede cargar la orden',
                                html: `
                                    <p class="mb-2">${verificarResponse.mensaje || 'Ya existen 2 órdenes con status "En Proceso" en esta máquina.'}</p>
                                    <p class="text-sm text-gray-600">Por favor, finaliza alguna de las órdenes en proceso en esta máquina antes de cargar una nueva.</p>
                                    <p class="text-sm text-gray-500 mt-2">Cantidad actual: ${verificarResponse.cantidad || 0} / ${verificarResponse.limite || 2}</p>
                                `,
                                confirmButtonColor: '#2563eb',
                            });
                        } else {
                            alert(verificarResponse.mensaje || 'Ya existen 2 órdenes con status "En Proceso" en esta máquina. No se puede cargar otra orden.');
                        }
                        liberarBoton();
                        return;
                    }
                } catch (error) {
                    console.error('Error al verificar órdenes en proceso:', error);
                    showError('Error al verificar órdenes en proceso. Por favor, intente nuevamente.');
                    liberarBoton();
                    return;
                }

                // ponytail: eliminada la precomprobación check_only. Devolvía puedeCrear=true
                // siempre y en Urdido ni existía: renderizaba la página completa de producción
                // solo para descartarla (timeouts de 3s). Los permisos se validan en el servidor.

                // Karl Mayer (MC Coy 4): confirmar cuenta/calibre antes de cargar
                if (Number(state.ordenSeleccionada.mccoy) === 4) {
                    const orden = (state.ordenes[4] || []).find(o => o.id === state.ordenSeleccionada.id);

                    // Se pregunta en cada carga, aunque ya esté marcada como incorrecta
                    if (typeof Swal !== 'undefined') {
                        const yaIncorrecta = Number(orden?.incorrecto) === 1;

                        const respuesta = await Swal.fire({
                            icon: yaIncorrecta ? 'warning' : 'question',
                            title: '¿La cuenta/calibre es correcta?',
                            html: `<p class="text-sm text-gray-600">Cuenta/Calibre: <strong>${orden?.cuenta_calibre || '—'}</strong></p>`
                                + (yaIncorrecta ? '<p class="mt-2 text-sm text-red-600">Esta orden está marcada como incorrecta. Solo un supervisor puede liberarla.</p>' : ''),
                            showDenyButton: true,
                            confirmButtonText: 'Sí, es correcta',
                            denyButtonText: yaIncorrecta ? 'Sigue incorrecta' : 'No',
                            confirmButtonColor: '#2563eb',
                            denyButtonColor: '#dc2626',
                            allowOutsideClick: false,
                        });

                        if (respuesta.isDismissed) { liberarBoton(); return; }

                        // "Sí" solo cambia algo si estaba marcada: intenta liberarla (el servidor exige supervisor)
                        const nuevoValor = respuesta.isDenied;

                        if (nuevoValor !== yaIncorrecta) {
                            try {
                                await fetchJson(routes.marcarIncorrecto, {
                                    method: 'POST',
                                    body: JSON.stringify({ id: state.ordenSeleccionada.id, incorrecto: nuevoValor }),
                                });
                                if (orden) orden.incorrecto = nuevoValor ? 1 : 0;
                                renderTable(4);
                            } catch (error) {
                                showError(error.message);
                                liberarBoton();
                                return;
                            }
                        }
                    }
                }

                // Si puede crear o hay registros existentes, redirigir
                const url = `${routes.produccion}?orden_id=${state.ordenSeleccionada.id}`;

                // Verificar que la URL sea válida
                if (!url || url.includes('undefined') || url.includes('null')) {
                    console.error('URL inválida para redirección:', url);
                    showError('Error: No se pudo construir la URL de redirección. Por favor, intente nuevamente.');
                    liberarBoton();
                    return false;
                }


                // Método 1: location.replace (más difícil de interceptar)
                window.location.replace(url);

                // Método 2: location.href como respaldo inmediato
                window.location.href = url;

                // Método 3: Si aún no funciona, usar window.open después de un delay muy corto
                setTimeout(() => {
                    if (window.location.href !== url && !window.location.href.includes('modulo-produccion-urdido')) {
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
                            <td class="${baseTd}">${orden.cuenta_calibre || ''}</td>
                            <td class="${baseTd}">${orden.configuracion || ''}</td>
                            <td class="${baseTd}">${metros}</td>
                            <td class="${baseTd}">${orden.maquina || ''}</td>
                            <td class="${baseTd}">${orden.status || ''}</td>
                        </tr>
                    `;
                }).join('');

                tbody.innerHTML = rowsHtml;

                // Configurar drag and drop para el modal
                setupModalDragAndDrop();
            };

            const setupModalDragAndDrop = () => {
                if (!canChangePrioridad) {
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

            let guardandoPrioridades = false;

            const guardarPrioridades = async () => {
                if (guardandoPrioridades) return;

                const btn = document.getElementById('btnGuardarPrioridades');
                const btnText = document.getElementById('btnGuardarPrioridadesText');
                const btnSpinner = document.getElementById('btnGuardarPrioridadesSpinner');

                guardandoPrioridades = true;
                if (btn) btn.disabled = true;
                if (btnSpinner) btnSpinner.classList.remove('hidden');
                if (btnText) btnText.textContent = 'Guardando...';

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
                } finally {
                    guardandoPrioridades = false;
                    if (btn) btn.disabled = false;
                    if (btnSpinner) btnSpinner.classList.add('hidden');
                    if (btnText) btnText.textContent = 'Guardar Cambios';
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

            let ordenCalidadId = null;

            const CALIDAD_PUNTOS = @json($calidadPuntos);

            // 'A'/'R' son evaluaciones previas al checklist; '1'/'0' las derivadas de los 4 puntos.
            const CALIDAD_VISUAL = {
                bueno: { icono: '✓', iconClass: 'text-green-600', texto: 'Aprobado', badge: 'border-green-200 bg-green-100 text-green-700' },
                malo: { icono: '✗', iconClass: 'text-red-600', texto: 'Rechazado', badge: 'border-red-200 bg-red-100 text-red-700' },
                sin: { icono: '—', iconClass: 'text-gray-400', texto: 'Sin evaluar', badge: 'border-gray-200 bg-gray-100 text-gray-600' },
            };

            function visualCalidad(valor) {
                if (valor === '1' || valor === 'A') return CALIDAD_VISUAL.bueno;
                if (valor === '0' || valor === 'R') return CALIDAD_VISUAL.malo;
                return CALIDAD_VISUAL.sin;
            }

            // 'Y-m-d H:i:s' -> 'dd/mm/aaaa hh:mm'. Sin librería: el formato de origen es fijo.
            function formatearFechaCalidad(valor) {
                if (!valor) return '—';
                const m = String(valor).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
                return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : valor;
            }

            // data-valor: '' sin contestar, '1' bien, '0' mal.
            function pintarPuntoCalidad(btn, valor) {
                btn.dataset.valor = valor ?? '';
                const bueno = valor === '1';
                const malo = valor === '0';
                btn.textContent = bueno ? '✓' : malo ? '✗' : '—';
                btn.className = 'h-11 w-11 shrink-0 rounded-lg border-2 text-lg font-bold transition-colors '
                    + (bueno ? 'border-green-500 bg-green-50 text-green-600'
                        : malo ? 'border-red-500 bg-red-50 text-red-600'
                            : 'border-gray-300 text-gray-400');
            }

            // El primer clic pone bueno; de ahí en adelante alterna. No se vuelve a "sin contestar".
            function alternarPuntoCalidad(btn) {
                pintarPuntoCalidad(btn, btn.dataset.valor === '1' ? '0' : '1');
                actualizarDisplayCalidad();
            }

            // null = sin contestar, true = bien, false = mal.
            function leerPuntosCalidad() {
                const puntos = {};
                document.querySelectorAll('#calidadChecklist [data-punto]').forEach(btn => {
                    puntos[btn.dataset.punto] = btn.dataset.valor === '' ? null : btn.dataset.valor === '1';
                });
                return puntos;
            }

            function calidadDerivada(puntos) {
                const valores = Object.keys(CALIDAD_PUNTOS).map(campo => puntos?.[campo] ?? null);
                if (valores.some(v => v === null)) return null;
                return valores.includes(false) ? '0' : '1';
            }

            function obtenerOrdenCalidadVisual(ordenId = null, mccoy = null) {
                if (ordenId !== null && mccoy !== null) {
                    return (state.ordenes[mccoy] || []).find(o => o.id === Number(ordenId)) || null;
                }

                return state.ordenSeleccionada || null;
            }

            function abrirModalCalidad(ordenId = null, mccoy = null) {
                const orden = obtenerOrdenCalidadVisual(ordenId, mccoy);
                if (!orden) {
                    alert('Seleccione un registro');
                    return;
                }

                ordenCalidadId = orden.id;
                document.getElementById('modalCalidadFolio').textContent = orden.folio || '';
                document.getElementById('calidadcomentario').value = orden.calidadcomentario || '';

                const puntos = orden.calidad_puntos || {};
                document.querySelectorAll('#calidadChecklist [data-punto]').forEach(btn => {
                    const valor = puntos[btn.dataset.punto];
                    pintarPuntoCalidad(btn, valor === null || valor === undefined ? '' : (valor ? '1' : '0'));
                });

                actualizarDisplayCalidad();

                document.getElementById('modalCalidad').style.display = 'flex';
            }

            function abrirModalCalidadPorOrden(ordenId, mccoy) {
                abrirModalVerCalidad(ordenId, mccoy);
            }

            function abrirModalVerCalidad(ordenId, mccoy) {
                const orden = (state.ordenes[mccoy] || []).find(o => o.id === Number(ordenId)) || null;
                if (!orden) {
                    return;
                }

                document.getElementById('modalVerCalidadFolio').textContent = orden.folio || '—';
                document.getElementById('modalVerCalidadAutoriza').textContent = orden.autoriza_calidad || '—';
                document.getElementById('modalVerCalidadFecha').textContent = formatearFechaCalidad(orden.fecha_calidad);

                const visual = visualCalidad(orden.calidad);
                const estado = document.getElementById('modalVerCalidadEstado');
                estado.textContent = visual.texto;
                estado.className = `rounded-full border px-3 py-1 text-sm font-semibold ${visual.badge}`;

                const puntos = orden.calidad_puntos || {};
                document.getElementById('modalVerCalidadChecklist').innerHTML =
                    Object.entries(CALIDAD_PUNTOS).map(([campo, etiqueta]) => {
                        const valor = puntos[campo] ?? null;
                        const p = valor === null ? CALIDAD_VISUAL.sin : (valor ? CALIDAD_VISUAL.bueno : CALIDAD_VISUAL.malo);
                        return `
                            <li class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="text-sm text-gray-700">${etiqueta}</span>
                                <span class="text-lg font-bold ${p.iconClass}">${p.icono}</span>
                            </li>
                        `;
                    }).join('');

                const comentario = (orden.calidadcomentario || '').trim();
                document.getElementById('modalVerCalidadComentario').textContent = comentario;
                document.getElementById('modalVerCalidadObsBloque').hidden = comentario === '';

                document.getElementById('modalVerCalidad').style.display = 'flex';
            }

            function cerrarModalVerCalidad() {
                document.getElementById('modalVerCalidad').style.display = 'none';
            }

            // El estado no se muestra al capturar: sale de los 4 puntos y se ve en el tablero.
            function actualizarDisplayCalidad() {
                document.getElementById('btnGuardarCalidad').disabled =
                    calidadDerivada(leerPuntosCalidad()) === null;
            }

            function cerrarModalCalidad() {
                document.getElementById('modalCalidad').style.display = 'none';
                ordenCalidadId = null;
            }

            async function guardarCalidad() {
                const btn = document.getElementById('btnGuardarCalidad');
                const btnText = document.getElementById('btnGuardarCalidadText');
                const puntos = leerPuntosCalidad();
                const calidadcomentario = document.getElementById('calidadcomentario').value;

                if (calidadDerivada(puntos) === null) {
                    Swal.fire({ icon: 'warning', title: 'Conteste los 4 puntos', timer: 1500, showConfirmButton: false });
                    return;
                }

                btn.disabled = true;
                btnText.textContent = 'Guardando...';

                try {
                    const data = await fetchJson(routes.actualizarCalidad, {
                        method: 'POST',
                        body: JSON.stringify({ id: ordenCalidadId, calidadcomentario, ...puntos }),
                    });

                    if (!data?.success) {
                        throw new Error(data?.error || 'Error al guardar calidad');
                    }

                    for (let mccoy = 1; mccoy <= 4; mccoy++) {
                        const ordenIdx = (state.ordenes[mccoy] || []).findIndex(o => o.id === ordenCalidadId);
                        if (ordenIdx !== -1) {
                            Object.assign(state.ordenes[mccoy][ordenIdx], {
                                calidad: data.calidad,
                                calidadcomentario: data.calidadcomentario,
                                autoriza_calidad: data.autoriza_calidad,
                                fecha_calidad: data.fecha_calidad,
                                calidad_puntos: data.calidad_puntos,
                            });
                            break;
                        }
                    }

                    cerrarModalCalidad();
                    renderAllTables();
                    const calidadTexto = visualCalidad(data.calidad).texto;
                    const msg = data.calidadcomentario
                        ? `${calidadTexto}: ${data.calidadcomentario}`
                        : calidadTexto;
                    Swal.fire({ icon: 'success', title: '¡Guardado!', text: msg, timer: 2000, showConfirmButton: false });
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: err.message, timer: 3000, showConfirmButton: false });
                } finally {
                    btn.disabled = false;
                    btnText.textContent = 'Guardar';
                }
            }

            window.abrirModalCalidad = abrirModalCalidad;
            window.abrirModalCalidadPorOrden = abrirModalCalidadPorOrden;
            window.cerrarModalCalidad = cerrarModalCalidad;
            window.guardarCalidad = guardarCalidad;
            window.alternarPuntoCalidad = alternarPuntoCalidad;
            window.abrirModalVerCalidad = abrirModalVerCalidad;
            window.cerrarModalVerCalidad = cerrarModalVerCalidad;

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
