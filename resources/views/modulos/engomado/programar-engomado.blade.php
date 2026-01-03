@extends('layouts.app')

@section('page-title', 'Programar Engomado')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-edit
            id="btnSubirPrioridad"
            onclick="subirPrioridad()"
            title="Subir Prioridad"
            icon="fa-arrow-up"
            iconColor="text-green-500"
            hoverBg="hover:bg-green-100"
        />
        <x-navbar.button-edit
            id="btnBajarPrioridad"
            onclick="bajarPrioridad()"
            title="Bajar Prioridad"
            icon="fa-arrow-down"
            iconColor="text-red-500"
            hoverBg="hover:bg-red-100"
        />
        <x-navbar.button-create
            onclick="irProduccion()"
            title="Cargar Información"
            icon="fa-download"
            iconColor="text-white"
            hoverBg="hover:bg-blue-600"
            text="Cargar"
            bg="bg-blue-500"
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
                                    </tr>
                                </thead>
                                <tbody id="tabla{{ $i }}TableBody" class="bg-white">
                                    <tr>
                                        <td colspan="8" class="px-2 py-2 text-center text-gray-500 text-2xl">
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

    <script>
        (() => {
            // ==========================
            // Config & Estado Global
            // ==========================
            const routes = {
                cargarOrdenes: '{{ route('engomado.programar.engomado.ordenes') }}',
                verificarEnProceso: '{{ route('engomado.programar.engomado.verificar.en.proceso') }}',
                subirPrioridad: '{{ route('engomado.programar.engomado.subir.prioridad') }}',
                bajarPrioridad: '{{ route('engomado.programar.engomado.bajar.prioridad') }}',
                produccion: '{{ route('engomado.modulo.produccion.engomado') }}',
            };

            const csrfToken = '{{ csrf_token() }}';

            const state = {
                ordenes: {},            // { 1: [..], 2: [..] }
                ordenSeleccionada: null // { id, tabla, ... }
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
                    timer: 2000,
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
                const btnSubir = document.getElementById('btnSubirPrioridad');
                const btnBajar = document.getElementById('btnBajarPrioridad');

                if (btnSubir) btnSubir.disabled = !enabled;
                if (btnBajar) btnBajar.disabled = !enabled;
                // El botón de producción se manejará por la validación en irProduccion()
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
                            <td colspan="8" class="px-2 py-2 text-center text-gray-500 text-xl">
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

                    const rowClasses = isSelected
                        ? 'bg-blue-500 text-white cursor-pointer h-9 transition-all duration-200'
                        : 'hover:bg-gray-50 cursor-pointer h-9 transition-all duration-200';

                    const metros = orden.metros
                        ? Math.round(parseFloat(orden.metros))
                        : '';

                    return `
                        <tr class="${rowClasses}" data-orden-id="${orden.id}" data-tabla="${tabla}">
                            <td class="${baseTd} text-center font-semibold">${prioridad}</td>
                            <td class="${baseTd}">${orden.folio || ''}</td>
                            <td class="${baseTd} text-center">${renderTipoBadge(orden.tipo, isSelected)}</td>
                            <td class="${baseTd}">${orden.cuenta || ''}</td>
                            <td class="${baseTd}">${orden.calibre || ''}</td>
                            <td class="${baseTd}">${metros}</td>
                            <td class="${baseTd}">${orden.formula || '-'}</td>
                            <td class="${baseTd}">${orden.status || ''}</td>
                        </tr>
                    `;
                }).join('');

                tbody.innerHTML = rowsHtml;
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
                        const row = event.target.closest('tr[data-orden-id]');
                        if (!row) return;
                        handleRowClick(row);
                    });
                }
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
            // Actualizar Prioridad
            // ==========================
            const actualizarPrioridad = async (endpoint) => {
                if (!state.ordenSeleccionada) {
                    showToast('warning', 'Seleccione una orden');
                    return;
                }

                try {
                    const payload = JSON.stringify({ id: state.ordenSeleccionada.id });
                    const result = await fetchJson(endpoint, {
                        method: 'POST',
                        body: payload,
                    });

                    if (!result.success) {
                        throw new Error(result.error || 'Error al actualizar prioridad');
                    }

                    const ordenId = state.ordenSeleccionada.id;

                    // Recargar sin duplicar notificaciones
                    await cargarOrdenes(true);

                    // Restaurar selección si sigue existiendo
                    for (let tablaIndex = 1; tablaIndex <= 2; tablaIndex++) {
                        const orden = (state.ordenes[tablaIndex] || []).find(o => o.id === ordenId);
                        if (orden) {
                            state.ordenSeleccionada = orden;
                            renderTable(tablaIndex);
                            break;
                        }
                    }

                    showToast('success', result.message || 'Prioridad actualizada correctamente');
                } catch (error) {
                    console.error('Error al actualizar prioridad:', error);
                    showError(`Error al actualizar prioridad: ${error.message}`);
                }
            };

            // ==========================
            // Ir a Producción
            // ==========================
            const irProduccion = async () => {
                if (!state.ordenSeleccionada) {
                    showToast('warning', 'Seleccione una orden');
                    return;
                }

                // Verificar si ya hay una orden con status "En Proceso"
                try {
                    const verificarUrl = `${routes.verificarEnProceso}?excluir_id=${state.ordenSeleccionada.id}`;
                    const verificarResponse = await fetchJson(verificarUrl);

                    if (verificarResponse.success && verificarResponse.tieneOrdenEnProceso) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'No se puede cargar la orden',
                                html: `
                                    <p class="mb-2">${verificarResponse.mensaje || 'Ya existe una orden con status "En Proceso".'}</p>
                                    <p class="text-sm text-gray-600">Por favor, finaliza la orden en proceso antes de cargar una nueva.</p>
                                `,
                                confirmButtonColor: '#2563eb',
                            });
                        } else {
                            alert(verificarResponse.mensaje || 'Ya existe una orden con status "En Proceso". No se puede cargar otra orden.');
                        }
                        return;
                    }
                } catch (error) {
                    console.error('Error al verificar órdenes en proceso:', error);
                    showError('Error al verificar órdenes en proceso. Por favor, intente nuevamente.');
                    return;
                }

                // Verificar si el usuario puede crear registros
                try {
                    const checkUrl = `${routes.produccion}?orden_id=${state.ordenSeleccionada.id}&check_only=true`;
                    const response = await fetch(checkUrl, {
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                        }
                    });

                    if (response.ok) {
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
                    console.error('Error al verificar permisos:', error);
                    // Continuar con la redirección si hay error en la verificación
                }

                // Si puede crear o hay registros existentes, redirigir
                const url = `${routes.produccion}?orden_id=${state.ordenSeleccionada.id}`;
                window.location.href = url;
            };

            // ==========================
            // API pública (para onclick del Blade)
            // ==========================
            const subirPrioridad = () => actualizarPrioridad(routes.subirPrioridad);
            const bajarPrioridad = () => actualizarPrioridad(routes.bajarPrioridad);

            window.cargarOrdenes = cargarOrdenes;
            window.subirPrioridad = subirPrioridad;
            window.bajarPrioridad = bajarPrioridad;
            window.irProduccion = irProduccion;

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

