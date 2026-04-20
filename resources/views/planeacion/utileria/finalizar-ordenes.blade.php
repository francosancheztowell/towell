{{--
    @file finalizar-ordenes.blade.php
    @description Modal para finalizar órdenes de producción en telares.
    @relatedFiles index.blade.php, FinalizarOrdenesController.php

    ! REPORTE DE FUNCIONALIDAD - Modal Finalizar Órdenes
    * -----------------------------------------------
    * 1. Al abrir el modal, se cargan los telares con órdenes en proceso vía AJAX
    * 2. El usuario selecciona un telar del dropdown
    * 3. Se cargan las órdenes en proceso del telar seleccionado en una tabla
    * 4. Columnas de la tabla: No. Orden, Fecha Cambio, Tamaño Clave, Modelo, Seleccionar (check)
    * 5. El usuario selecciona las órdenes a finalizar mediante checkboxes
    * 6. Al confirmar, se envían los IDs al backend para actualizar EnProceso=0 y FechaFinaliza=now()
    * 7. Se muestra confirmación con SweetAlert2 y se refresca la tabla
    * -----------------------------------------------
--}}

{{-- * Modal Finalizar Órdenes --}}
<div id="modalFinalizar" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">

        {{-- ? Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-white">
            <div class="flex items-center gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">
                        Finalizar Órdenes
                        <span id="finalizarContador" class="text-sm font-semibold text-blue-600"></span>
                    </h2>
                </div>
            </div>
            <button type="button" onclick="cerrarModalFinalizar()" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        {{-- ? Body --}}
        <div class="flex-1 overflow-y-auto px-6 py-5">

            {{-- Select de Telar --}}
            <div class="mb-5">
                <label for="finalizarSelectTelar" class="block text-sm font-semibold text-gray-700 mb-2">
                    Seleccionar Telar
                </label>
                <select
                    id="finalizarSelectTelar"
                    onchange="cargarOrdenesFinalizar()"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition-colors bg-white"
                >
                    <option value="">Seleccione un telar</option>
                </select>
            </div>

            {{-- Loader --}}
            <div id="finalizarLoader" class="hidden py-12 text-center">
                <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                <p class="text-sm text-gray-500 mt-2">Cargando órdenes...</p>
            </div>

            {{-- Tabla de órdenes --}}
            <div id="finalizarTablaContainer" class="hidden">


                <div class="border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="w-full min-w-full text-sm">
                        <thead class="bg-blue-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-white w-10"></th>
                                <th class="px-3 py-2 text-left font-semibold text-white whitespace-nowrap min-w-[100px]">No. Orden</th>
                                <th class="px-3 py-2 text-left font-semibold text-white whitespace-nowrap">Fecha Cambio</th>
                                <th class="px-3 py-2 text-left font-semibold text-white whitespace-nowrap">Clave</th>
                                <th class="px-3 py-2 text-left font-semibold text-white">Modelo</th>
                                <th class="px-3 py-2 text-right font-semibold text-white whitespace-nowrap">Pedido</th>
                                <th class="px-3 py-2 text-right font-semibold text-white whitespace-nowrap">Producción</th>
                                <th class="px-3 py-2 text-right font-semibold text-white whitespace-nowrap">Saldos</th>
                            </tr>
                        </thead>
                        <tbody id="finalizarTbody" class="divide-y divide-gray-100">
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Estado vacío --}}
            <div id="finalizarEmpty" class="hidden py-12 text-center">
                <i class="fas fa-inbox text-gray-300 text-4xl mb-3"></i>
                <p class="text-sm text-gray-500">No hay órdenes para este telar</p>
            </div>
        </div>

        {{-- ? Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50">
            <span id="finalizarSeleccionados" class="text-sm text-gray-500"></span>
            <div class="flex gap-3">
                <button type="button" id="btnFinalizarConfirm" onclick="confirmarFinalizar()" disabled class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    Finalizar Ordenes
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    // * Estado del modal de finalizar
    const finalizarState = {
        telares: [],
        ordenes: [],
        selectedIds: new Set(),
    };

    const ROUTES = {
        telares: @json(route('planeacion.utileria.finalizar.telares')),
        ordenes: @json(route('planeacion.utileria.finalizar.ordenes')),
        procesar: @json(route('planeacion.utileria.finalizar.procesar')),
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // * Abrir modal y cargar telares
    window.abrirModalFinalizar = async function () {
        document.getElementById('modalFinalizar').style.display = 'flex';
        resetFinalizarState();
        await cargarTelaresFinalizar();
    };

    window.cerrarModalFinalizar = function () {
        document.getElementById('modalFinalizar').style.display = 'none';
        resetFinalizarState();
    };

    function resetFinalizarState() {
        finalizarState.telares = [];
        finalizarState.ordenes = [];
        finalizarState.selectedIds.clear();

        const select = document.getElementById('finalizarSelectTelar');
        select.innerHTML = '<option value="">Selecciona un telar</option>';

        document.getElementById('finalizarTablaContainer').classList.add('hidden');
        document.getElementById('finalizarEmpty').classList.add('hidden');
        document.getElementById('finalizarLoader').classList.add('hidden');
        document.getElementById('finalizarTbody').innerHTML = '';
        document.getElementById('finalizarSeleccionados').textContent = '';
        var contadorReset = document.getElementById('finalizarContador');
        if (contadorReset) contadorReset.textContent = '';
        document.getElementById('btnFinalizarConfirm').disabled = true;
    }

    async function cargarTelaresFinalizar() {
        try {
            const resp = await fetch(ROUTES.telares, { headers: { 'Accept': 'application/json' } });
            const data = await resp.json();

            if (data.success && Array.isArray(data.telares)) {
                finalizarState.telares = data.telares;
                const select = document.getElementById('finalizarSelectTelar');

                data.telares.forEach(function (t, i) {
                    const opt = document.createElement('option');
                    opt.value = i;
                    opt.textContent = t.telar;
                    select.appendChild(opt);
                });
            }
        } catch (e) {
            console.error('Error cargando telares para finalizar:', e);
        }
    }

    // * Cargar órdenes al seleccionar telar
    window.cargarOrdenesFinalizar = async function () {
        const select = document.getElementById('finalizarSelectTelar');
        const idx = select.value;

        finalizarState.ordenes = [];
        finalizarState.selectedIds.clear();

        if (idx === '') {
            document.getElementById('finalizarTablaContainer').classList.add('hidden');
            document.getElementById('finalizarEmpty').classList.add('hidden');
            updateFinalizarFooter();
            return;
        }

        const telar = finalizarState.telares[parseInt(idx, 10)];
        if (!telar) return;

        document.getElementById('finalizarLoader').classList.remove('hidden');
        document.getElementById('finalizarTablaContainer').classList.add('hidden');
        document.getElementById('finalizarEmpty').classList.add('hidden');

        try {
            const url = ROUTES.ordenes + '?salon=' + encodeURIComponent(telar.salon) + '&telar=' + encodeURIComponent(telar.telar);
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await resp.json();

            document.getElementById('finalizarLoader').classList.add('hidden');

            if (data.success && Array.isArray(data.ordenes) && data.ordenes.length > 0) {
                finalizarState.ordenes = data.ordenes;
                renderTablaFinalizar();
                document.getElementById('finalizarTablaContainer').classList.remove('hidden');
                var contador = document.getElementById('finalizarContador');
                if (contador) contador.textContent = '(' + data.ordenes.length + ')';
            } else {
                document.getElementById('finalizarEmpty').classList.remove('hidden');
                var contadorEmpty = document.getElementById('finalizarContador');
                if (contadorEmpty) contadorEmpty.textContent = '';
            }
        } catch (e) {
            document.getElementById('finalizarLoader').classList.add('hidden');
            console.error('Error cargando órdenes:', e);
        }

        updateFinalizarFooter();
    };

    function renderTablaFinalizar() {
        const tbody = document.getElementById('finalizarTbody');
        tbody.innerHTML = finalizarState.ordenes.map(function (o, i) {
            const idKey = typeof o.id === 'number' ? o.id : parseInt(String(o.id), 10);
            const isChecked = Number.isNaN(idKey) ? finalizarState.selectedIds.has(o.id) : finalizarState.selectedIds.has(idKey);
            const checkedAttr = isChecked ? ' checked' : '';
            const rowBg = o.enProceso ? 'bg-amber-50' : (i % 2 === 0 ? 'bg-white' : 'bg-gray-50');
            const rowBorder = o.enProceso ? ' border-l-4 border-l-amber-400' : '';
            const enProcesoBadge = o.enProceso ? ' <span class="text-xs bg-amber-500 text-white px-1.5 py-0.5 rounded font-medium whitespace-nowrap inline-block ml-1">En proceso</span>' : '';
            const esRepaso1 = (o.modelo || '').toUpperCase().indexOf('REPASO1') !== -1;
            const repaso1Badge = esRepaso1 ? ' <span class="text-xs bg-red-500 text-white px-1.5 py-0.5 rounded font-medium whitespace-nowrap inline-block ml-1">Repaso</span>' : '';
            var saldoVal = o.saldoPedido != null ? Number(o.saldoPedido) : null;
            var saldoDisplay = saldoVal === null ? '-' : saldoVal.toLocaleString('es-MX');
            var saldoHtml = saldoVal !== null && saldoVal < 0
                ? '<span class="text-xs bg-red-500 text-white px-1.5 py-0.5 rounded font-medium">' + saldoDisplay + '</span>'
                : saldoDisplay;
            var produccion = o.produccion != null ? Number(o.produccion).toLocaleString('es-MX') : '-';
            var total = o.totalPedido != null ? Number(o.totalPedido).toLocaleString('es-MX') : '-';
            return '<tr class="' + rowBg + rowBorder + ' hover:bg-gray-100 transition-colors cursor-pointer" onclick="toggleFinalizarRow(' + o.id + ', this)">'
                + '<td class="px-3 py-2"><input type="checkbox" class="finalizar-check w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-400" data-id="' + o.id + '"' + checkedAttr + ' onclick="event.stopPropagation(); toggleFinalizarCheck(' + o.id + ')"></td>'
                + '<td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">' + escHtml(o.noOrden) + enProcesoBadge + repaso1Badge + '</td>'
                + '<td class="px-3 py-2 text-gray-600 whitespace-nowrap">' + escHtml(o.fechaCambio) + '</td>'
                + '<td class="px-3 py-2 text-gray-600">' + escHtml(o.tamanoClave) + '</td>'
                + '<td class="px-3 py-2 text-gray-600">' + escHtml(o.modelo) + '</td>'
                + '<td class="px-3 py-2 text-right text-gray-600 tabular-nums">' + total + '</td>'
                + '<td class="px-3 py-2 text-right text-gray-600 tabular-nums">' + produccion + '</td>'
                + '<td class="px-3 py-2 text-right tabular-nums">' + (saldoVal !== null && saldoVal < 0 ? saldoHtml : '<span class="text-gray-600">' + saldoHtml + '</span>') + '</td>'
                + '</tr>';
        }).join('');
    }

    // * Toggle selección por fila
    window.toggleFinalizarRow = function (id, tr) {
        toggleFinalizarCheck(id);
        const cb = tr.querySelector('.finalizar-check');
        if (cb) {
            const idNum = parseInt(cb.getAttribute('data-id'), 10);
            cb.checked = Number.isNaN(idNum) ? finalizarState.selectedIds.has(String(cb.getAttribute('data-id'))) : finalizarState.selectedIds.has(idNum);
        }
    };

    window.toggleFinalizarCheck = function (id) {
        const idNorm = typeof id === 'number' ? id : parseInt(String(id), 10);
        const key = Number.isNaN(idNorm) ? id : idNorm;
        if (finalizarState.selectedIds.has(key)) {
            finalizarState.selectedIds.delete(key);
        } else {
            finalizarState.selectedIds.add(key);
        }
        syncCheckboxesFinalizar();
        updateFinalizarFooter();
    };

    function syncCheckboxesFinalizar() {
        document.querySelectorAll('.finalizar-check').forEach(function (cb) {
            const idNum = parseInt(cb.getAttribute('data-id'), 10);
            cb.checked = Number.isNaN(idNum) ? finalizarState.selectedIds.has(cb.getAttribute('data-id')) : finalizarState.selectedIds.has(idNum);
        });
    }

    function tieneSeleccionSinProduccion() {
        for (const id of finalizarState.selectedIds) {
            const idNum = typeof id === 'number' ? id : parseInt(String(id), 10);
            const o = finalizarState.ordenes.find(function (x) {
                return Number(x.id) === idNum || String(x.id) === String(id);
            });
            if (!o || o.produccion == null || Number(o.produccion) === 0) return true;
        }
        return false;
    }

    function updateFinalizarFooter() {
        const count = finalizarState.selectedIds.size;
        const sinProd = count > 0 && tieneSeleccionSinProduccion();
        const spanSel = document.getElementById('finalizarSeleccionados');
        if (count === 0) {
            spanSel.textContent = '';
        } else if (sinProd) {
            spanSel.textContent = count + ' orden(es) seleccionada(s). No se puede finalizar si falta producción o la producción es cero.';
        } else {
            spanSel.textContent = count + ' orden(es) seleccionada(s)';
        }
        document.getElementById('btnFinalizarConfirm').disabled = count === 0 || sinProd;
    }

    // * Confirmar finalización con SweetAlert2
    window.confirmarFinalizar = function () {
        const count = finalizarState.selectedIds.size;
        if (count === 0) return;
        if (tieneSeleccionSinProduccion()) return;

        Swal.fire({
            title: '¿Finalizar órdenes?',
            html: 'Se finalizarán <strong>' + count + '</strong> orden(es) de producción.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
        }).then(function (result) {
            if (result.isConfirmed) {
                ejecutarFinalizar();
            }
        });
    };

    async function ejecutarFinalizar() {
        const ids = Array.from(finalizarState.selectedIds);

        Swal.fire({
            title: 'Procesando...',
            text: 'Finalizando órdenes seleccionadas',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function () { Swal.showLoading(); },
        });

        try {
            const resp = await fetch(ROUTES.procesar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ ids: ids }),
            });
            const data = await resp.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Órdenes finalizadas',
                    text: data.message,
                    confirmButtonColor: '#16a34a',
                    timer: 2500,
                });
                // ? Recargar órdenes del telar actual
                cargarOrdenesFinalizar();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudieron finalizar las órdenes',
                    confirmButtonColor: '#ef4444',
                });
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo comunicar con el servidor',
                confirmButtonColor: '#ef4444',
            });
        }
    }

    function escHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // * Cerrar modal al hacer clic fuera
    document.getElementById('modalFinalizar')?.addEventListener('click', function (e) {
        if (e.target === this) cerrarModalFinalizar();
    });
})();
</script>
@endpush
