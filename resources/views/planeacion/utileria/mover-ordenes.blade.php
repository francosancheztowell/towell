{{--
    @file mover-ordenes.blade.php
    @description Modal para mover órdenes entre telares con interfaz drag-and-drop y ordenamiento.
    @relatedFiles index.blade.php, MoverOrdenesController.php

    ! REPORTE DE FUNCIONALIDAD - Modal Mover Órdenes
    * -----------------------------------------------
    * 1. Al abrir el modal, se cargan todos los telares disponibles vía AJAX.
    * 2. El usuario selecciona TELAR ORIGEN y/o TELAR DESTINO y se cargan sus registros.
    * 3. Las órdenes en proceso ahora también se pueden mover y reordenar.
    * 4. Interfaz drag-and-drop permite reordenar registros dentro del mismo telar o moverlos a otro.
    * 5. Al confirmar, se envían todos los IDs en el nuevo orden al backend.
    * 6. El backend actualiza NoTelarId, SalonTejidoId y Posicion para cada registro.
    * -----------------------------------------------
--}}

<div id="modalMover" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] max-h-[90vh] flex flex-col overflow-hidden" style="min-width: 1200px;">

        {{-- ? Header --}}
        <div class="flex items-center justify-between px-6 py-4 ">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-bold text-gray-800">Mover Órdenes</h2>
            </div>
            <button type="button" onclick="cerrarModalMover()" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        {{-- ? Body: Paneles --}}
        <div class="flex-1 overflow-hidden px-6 py-5 bg-gray-50/30">
            <div class="flex gap-6 h-full" style="min-height: 450px;">

                {{-- Panel ORIGEN --}}
                <div class="flex-1 flex flex-col border border-gray-100 rounded-xl overflow-hidden bg-white transition-all duration-200"
                     ondragover="handleDragOverContainer(event, 'origen')"
                     ondragleave="handleDragLeaveContainer(event, 'origen')"
                     ondrop="handleDropContainer(event, 'origen')"
                     id="panelOrigenContainer">
                    <div class="px-4 py-3 bg-amber-100 border-b border-gray-200">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <label class="text-sm font-semibold text-amber-800">Telar Origen</label>
                            <span id="moverOrigenTipo" class="text-xs font-bold text-amber-800 bg-amber-300 px-2 py-0.5 rounded hidden shadow-sm"></span>
                        </div>
                        <select id="moverSelectOrigen" onchange="cargarRegistrosOrigen()" class="w-full px-3 py-2 border border-amber-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-400 bg-white">
                            <option value="">Seleccione telar origen</option>
                        </select>
                    </div>
                    <div id="moverOrigenList" class="flex-1 overflow-y-auto relative">
                        <div id="moverOrigenEmpty" class="py-12 text-center text-gray-400 text-sm pointer-events-none">
                            <i class="fas fa-inbox text-3xl mb-2 text-amber-200"></i><p>Seleccione un telar origen</p>
                        </div>
                        <div id="moverOrigenItems" class="hidden h-full">
                            <table class="w-full text-sm border-collapse">
                                <thead class="bg-blue-500 text-white sticky top-0 z-10 shadow-sm rounded-t-lg">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold w-8 rounded-tl-lg"></th>
                                        <th class="px-3 py-2 text-left font-semibold">Orden</th>
                                        <th class="px-3 py-2 text-left font-semibold">Tamaño</th>
                                        <th class="px-3 py-2 text-left font-semibold">Modelo</th>
                                        <th class="px-3 py-2 text-right font-semibold rounded-tr-lg">Producción</th>
                                    </tr>
                                </thead>
                                <tbody id="moverOrigenTbody" class="pb-12"></tbody>
                            </table>
                            <div id="moverOrigenDropZone" class="hidden absolute inset-0 bg-amber-300/80 backdrop-blur-[1px] flex items-center justify-center border-2 border-dashed border-amber-400 rounded-b-lg pointer-events-none z-20">
                                <div class="bg-white px-4 py-2 rounded-lg shadow-sm text-amber-600 font-semibold flex items-center gap-2">
                                    <i class="fas fa-download"></i><span>Mover aquí</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Panel DESTINO --}}
                <div class="flex-1 flex flex-col border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm transition-all duration-200"
                     ondragover="handleDragOverContainer(event, 'destino')"
                     ondragleave="handleDragLeaveContainer(event, 'destino')"
                     ondrop="handleDropContainer(event, 'destino')"
                     id="panelDestinoContainer">
                    <div class="px-4 py-3 bg-blue-100 border-b border-gray-200">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <label class="text-sm font-semibold text-blue-800">Telar Destino</label>
                            <span id="moverDestinoTipo" class="text-xs font-bold text-blue-800 bg-blue-200 px-2 py-0.5 rounded hidden shadow-sm"></span>
                        </div>
                        <select id="moverSelectDestino" onchange="cargarRegistrosDestino()" class="w-full px-3 py-2 border border-blue-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 bg-white">
                            <option value="">Seleccione telar destino</option>
                        </select>
                    </div>
                    <div id="moverDestinoList" class="flex-1 overflow-y-auto relative">
                        <div id="moverDestinoEmpty" class="py-12 text-center text-gray-400 text-sm pointer-events-none">
                            <i class="fas fa-inbox text-3xl mb-2 text-blue-200"></i><p>Seleccione un telar destino</p>
                        </div>
                        <div id="moverDestinoItems" class="hidden h-full">
                            <table class="w-full text-sm border-collapse">
                                <thead class="bg-blue-500 text-white sticky top-0 z-10 shadow-sm rounded-t-lg">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold w-8 rounded-tl-lg"></th>
                                        <th class="px-3 py-2 text-left font-semibold">Orden</th>
                                        <th class="px-3 py-2 text-left font-semibold">Tamaño</th>
                                        <th class="px-3 py-2 text-left font-semibold">Modelo</th>
                                        <th class="px-3 py-2 text-right font-semibold rounded-tr-lg">Producción</th>
                                    </tr>
                                </thead>
                                <tbody id="moverDestinoTbody" class="pb-12"></tbody>
                            </table>
                            <div id="moverDestinoDropZone" class="hidden absolute inset-0 bg-blue-50/80 backdrop-blur-[1px] flex items-center justify-center border-2 border-dashed border-blue-400 rounded-b-lg pointer-events-none z-20">
                                <div class="bg-white px-4 py-2 rounded-lg shadow-sm text-blue-600 font-semibold flex items-center gap-2">
                                    <i class="fas fa-download"></i><span>Mover aquí</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ? Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-white">
            <div id="moverResumen" class="text-sm font-medium"></div>
            <div class="flex gap-3">
                <button type="button" id="btnMoverRevertir" onclick="revertirCambios()" class="hidden px-5 py-2.5 text-md font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors shadow-sm">
                    <i class="fas fa-undo mr-1"></i> Revertir
                </button>
                <button type="button" id="btnMoverConfirm" onclick="confirmarMover()" disabled class="px-5 py-2.5 text-md font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm flex items-center gap-2">
                    Guardar
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    const moverState = {
        telares: [],
        origenTelar: null,
        destinoTelar: null,
        origenRegistros: [],
        destinoRegistros: [],
        originalOrigenIds: [],
        originalDestinoIds: [],
        hasChanges: false,
        draggedItemInfo: null // { sourcePanel: 'origen'|'destino', id: number }
    };

    const MOVER_ROUTES = {
        telares: @json(route('planeacion.utileria.mover.telares')),
        registros: @json(route('planeacion.utileria.mover.registros')),
        procesar: @json(route('planeacion.utileria.mover.procesar')),
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function tipoSalonDisplay(salon) {
        if (!salon || salon === '') return '';
        var s = String(salon).toUpperCase().trim();
        if (s === 'JACQUARD' || s === 'JAC' || s === 'JACQ') return 'JACQUARD';
        if (s === 'SMIT' || s === 'SMITH' || s === 'ITEMA') return 'SMIT';
        if (s === 'KARL MAYER' || s === 'KARLMAYER' || s === 'KM') return 'KARL MAYER';
        return salon;
    }

    function mismoTelar(a, b) {
        if (!a || !b) return false;
        return (a.salon || '') === (b.salon || '') && (a.telar || '') === (b.telar || '');
    }

    function syncTelarSelects() {
        var selOrigen = document.getElementById('moverSelectOrigen');
        var selDestino = document.getElementById('moverSelectDestino');
        var idxOrigen = selOrigen.value;
        var idxDestino = selDestino.value;
        var telarOrigen = idxOrigen !== '' ? moverState.telares[parseInt(idxOrigen, 10)] : null;
        var telarDestino = idxDestino !== '' ? moverState.telares[parseInt(idxDestino, 10)] : null;

        for (var i = 0; i < selOrigen.options.length; i++) {
            var opt = selOrigen.options[i];
            if (opt.value === '') continue;
            var t = moverState.telares[parseInt(opt.value, 10)];
            opt.disabled = telarDestino && mismoTelar(t, telarDestino);
        }
        for (var j = 0; j < selDestino.options.length; j++) {
            var opt2 = selDestino.options[j];
            if (opt2.value === '') continue;
            var t2 = moverState.telares[parseInt(opt2.value, 10)];
            opt2.disabled = telarOrigen && mismoTelar(t2, telarOrigen);
        }
    }

    function escHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    window.abrirModalMover = async function () {
        document.getElementById('modalMover').style.display = 'flex';
        resetMoverState();
        await cargarTelaresMover();
    };

    window.cerrarModalMover = function () {
        if (moverState.hasChanges) {
            Swal.fire({
                title: '¿Cerrar sin guardar?',
                text: "Hay cambios sin guardar. Si cierra el modal se perderán.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, cerrar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('modalMover').style.display = 'none';
                    resetMoverState();
                }
            });
        } else {
            document.getElementById('modalMover').style.display = 'none';
            resetMoverState();
        }
    };

    window.revertirCambios = async function () {
        if (!moverState.hasChanges) return;
        moverState.hasChanges = false;
        moverState.origenRegistros = [];
        moverState.destinoRegistros = [];
        if (moverState.origenTelar) await fetchRegistros('origen');
        if (moverState.destinoTelar) await fetchRegistros('destino');
    };

    function resetMoverState() {
        moverState.telares = [];
        moverState.origenTelar = null;
        moverState.destinoTelar = null;
        moverState.origenRegistros = [];
        moverState.destinoRegistros = [];
        moverState.originalOrigenIds = [];
        moverState.originalDestinoIds = [];
        moverState.hasChanges = false;
        moverState.draggedItemInfo = null;

        ['moverSelectOrigen', 'moverSelectDestino'].forEach(function (id) {
            var el = document.getElementById(id);
            el.innerHTML = '<option value="">Seleccione un telar</option>';
        });

        document.getElementById('moverOrigenTbody').innerHTML = '';
        document.getElementById('moverDestinoTbody').innerHTML = '';
        document.getElementById('moverOrigenItems').classList.add('hidden');
        document.getElementById('moverDestinoItems').classList.add('hidden');
        document.getElementById('moverOrigenEmpty').style.display = '';
        document.getElementById('moverDestinoEmpty').style.display = '';
        document.getElementById('moverResumen').innerHTML = '';
        document.getElementById('moverOrigenTipo').classList.add('hidden');
        document.getElementById('moverDestinoTipo').classList.add('hidden');
        document.getElementById('btnMoverConfirm').disabled = true;
        document.getElementById('btnMoverRevertir').classList.add('hidden');

        document.getElementById('panelDestinoContainer').classList.remove('ring-2', 'ring-blue-300');
        document.getElementById('panelOrigenContainer').classList.remove('ring-2', 'ring-amber-300');
    }

    async function cargarTelaresMover() {
        try {
            var resp = await fetch(MOVER_ROUTES.telares, { headers: { 'Accept': 'application/json' } });
            var data = await resp.json();

            if (data.success && Array.isArray(data.telares)) {
                moverState.telares = data.telares;

                ['moverSelectOrigen', 'moverSelectDestino'].forEach(function (id) {
                    var select = document.getElementById(id);
                    data.telares.forEach(function (t, i) {
                        var opt = document.createElement('option');
                        opt.value = i;
                        opt.textContent = t.telar;
                        select.appendChild(opt);
                    });
                });
                syncTelarSelects();
            }
        } catch (e) {
            console.error('Error cargando telares para mover:', e);
        }
    }

    function checkIfChanged() {
        var hasChanged = false;

        if (moverState.origenRegistros.length !== moverState.originalOrigenIds.length) {
            hasChanged = true;
        } else {
            for (var i = 0; i < moverState.origenRegistros.length; i++) {
                if (moverState.origenRegistros[i].id !== moverState.originalOrigenIds[i]) {
                    hasChanged = true;
                    break;
                }
            }
        }

        if (!hasChanged) {
            if (moverState.destinoRegistros.length !== moverState.originalDestinoIds.length) {
                hasChanged = true;
            } else {
                for (var j = 0; j < moverState.destinoRegistros.length; j++) {
                    if (moverState.destinoRegistros[j].id !== moverState.originalDestinoIds[j]) {
                        hasChanged = true;
                        break;
                    }
                }
            }
        }

        moverState.hasChanges = hasChanged;

        if (!hasChanged) {
            moverState.origenRegistros.forEach(function(r) { r.isMoved = false; });
            moverState.destinoRegistros.forEach(function(r) { r.isMoved = false; });
        }

        updateMoverButtons();
    }

    window.cargarRegistrosOrigen = async function () {
        if (moverState.hasChanges) {
            Swal.fire({
                icon: 'warning',
                title: 'Cambios sin guardar',
                text: 'Guarde sus cambios antes de cambiar de telar.'
            });
            syncTelarSelects();
            return;
        }

        var select = document.getElementById('moverSelectOrigen');
        var idx = select.value;
        moverState.origenRegistros = [];
        moverState.originalOrigenIds = [];

        if (idx === '') {
            moverState.origenTelar = null;
            document.getElementById('moverOrigenTbody').innerHTML = '';
            document.getElementById('moverOrigenItems').classList.add('hidden');
            document.getElementById('moverOrigenEmpty').style.display = '';
            document.getElementById('moverOrigenTipo').classList.add('hidden');
            syncTelarSelects();
            updateMoverButtons();
            return;
        }

        moverState.origenTelar = moverState.telares[parseInt(idx, 10)];
        var tipoEl = document.getElementById('moverOrigenTipo');
        tipoEl.textContent = tipoSalonDisplay(moverState.origenTelar.salon);
        tipoEl.classList.remove('hidden');
        syncTelarSelects();
        await fetchRegistros('origen');
    };

    window.cargarRegistrosDestino = async function () {
        if (moverState.hasChanges) {
            Swal.fire({
                icon: 'warning',
                title: 'Cambios sin guardar',
                text: 'Guarde sus cambios antes de cambiar de telar.'
            });
            return;
        }

        var select = document.getElementById('moverSelectDestino');
        var idx = select.value;
        moverState.destinoRegistros = [];
        moverState.originalDestinoIds = [];

        if (idx === '') {
            moverState.destinoTelar = null;
            document.getElementById('moverDestinoTbody').innerHTML = '';
            document.getElementById('moverDestinoItems').classList.add('hidden');
            document.getElementById('moverDestinoEmpty').style.display = '';
            document.getElementById('moverDestinoTipo').classList.add('hidden');
            syncTelarSelects();
            updateMoverButtons();
            return;
        }

        moverState.destinoTelar = moverState.telares[parseInt(idx, 10)];
        var tipoEl = document.getElementById('moverDestinoTipo');
        tipoEl.textContent = tipoSalonDisplay(moverState.destinoTelar.salon);
        tipoEl.classList.remove('hidden');
        syncTelarSelects();
        await fetchRegistros('destino');
    };

    async function fetchRegistros(panel) {
        var isOrigen = panel === 'origen';
        var telar = isOrigen ? moverState.origenTelar : moverState.destinoTelar;
        var emptyId = isOrigen ? 'moverOrigenEmpty' : 'moverDestinoEmpty';
        var itemsId = isOrigen ? 'moverOrigenItems' : 'moverDestinoItems';
        var tbodyId = isOrigen ? 'moverOrigenTbody' : 'moverDestinoTbody';

        if (!telar) return;

        document.getElementById(emptyId).style.display = 'none';
        document.getElementById(itemsId).classList.remove('hidden');
        document.getElementById(tbodyId).innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin text-lg"></i><p class="text-sm mt-1">Cargando...</p></td></tr>';

        try {
            var url = MOVER_ROUTES.registros + '?salon=' + encodeURIComponent(telar.salon) + '&telar=' + encodeURIComponent(telar.telar);
            var resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            var data = await resp.json();

            if (data.success && Array.isArray(data.registros)) {
                var regsWithState = data.registros.map(function(r) {
                    r.isMoved = false;
                    return r;
                });

                if (isOrigen) {
                    moverState.origenRegistros = regsWithState;
                    moverState.originalOrigenIds = regsWithState.map(function(r){ return r.id; });
                } else {
                    moverState.destinoRegistros = regsWithState;
                    moverState.originalDestinoIds = regsWithState.map(function(r){ return r.id; });
                }
                checkIfChanged();
                renderPanel(panel);
            } else {
                document.getElementById(tbodyId).innerHTML = '';
                renderPanel(panel);
            }
        } catch (e) {
            document.getElementById(tbodyId).innerHTML = '';
            console.error('Error cargando registros ' + panel + ':', e);
        }
    }

    function renderPanel(panel) {
        var isOrigen = panel === 'origen';
        var registros = isOrigen ? moverState.origenRegistros : moverState.destinoRegistros;
        var tbodyId = isOrigen ? 'moverOrigenTbody' : 'moverDestinoTbody';
        var itemsId = isOrigen ? 'moverOrigenItems' : 'moverDestinoItems';
        var emptyId = isOrigen ? 'moverOrigenEmpty' : 'moverDestinoEmpty';
        var telarAsignado = isOrigen ? moverState.origenTelar : moverState.destinoTelar;

        if (registros.length > 0 || telarAsignado) {
            document.getElementById(itemsId).classList.remove('hidden');
            document.getElementById(emptyId).style.display = 'none';
        } else {
            document.getElementById(itemsId).classList.add('hidden');
            document.getElementById(emptyId).style.display = '';
        }

        var tbody = document.getElementById(tbodyId);

        if (registros.length === 0 && telarAsignado) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-gray-400 bg-white border-2 border-dashed border-gray-200 m-2 rounded-lg block italic">Arrastre órdenes aquí</td></tr>';
            return;
        }

        tbody.innerHTML = registros.map(function (r, i) {
            var isEnProceso = r.enProceso;

            var isNativeOrigen = false;
            if (moverState.origenTelar) {
                isNativeOrigen = r.telar === moverState.origenTelar.telar;
            }

            var isPendingMoveToDestino = !isOrigen && isNativeOrigen;
            var isPendingReorder = isOrigen && r.isMoved;
            var isPending = isPendingMoveToDestino || isPendingReorder;
            var isDraggable = isOrigen || isPendingMoveToDestino;

            var rowClasses = 'transition-all duration-200 border-b border-gray-100 ';
            if (isPending) {
                rowClasses += 'bg-green-50 hover:bg-green-100';
            } else {
                rowClasses += 'hover:bg-gray-50 bg-white';
            }

            var badges = '';
            var hasPendingBadge = false;

            if (isPendingMoveToDestino) {
                badges += '<span class="text-sm bg-green-200 text-green-800 px-2 py-0.5 rounded-full font-bold ml-2 shadow-sm border border-green-300"><i class="fas fa-arrow-right mr-1"></i>A mover</span>';
                hasPendingBadge = true;
            } else if (isPendingReorder) {
                badges += '<span class="text-sm bg-green-200 text-green-800 px-2 py-0.5 rounded-full font-bold ml-2 shadow-sm border border-green-300"><i class="fas fa-arrows-alt-v mr-1"></i>Modificado</span>';
                hasPendingBadge = true;
            }

            if (isEnProceso && !hasPendingBadge) {
                badges += '<span class="text-sm bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-bold ml-2 shadow-sm border border-amber-200">En proceso</span>';
            }
            if (r.esRepaso1) {
                badges += '<span class="text-sm bg-red-100 text-red-800 px-2 py-0.5 rounded-full font-bold ml-2 shadow-sm border border-red-200">Repaso</span>';
            }

            if (isDraggable) {
                rowClasses += ' cursor-grab active:cursor-grabbing hover:shadow-md';
            } else {
                rowClasses += ' opacity-80';
            }

            var produccionFormatted = new Intl.NumberFormat('en-US').format(r.produccion);

            var dragAttr = isDraggable ? 'draggable="true" ondragstart="handleDragStart(event, \'' + panel + '\', ' + r.id + ')" ondragend="handleDragEnd(event)"' : '';

            var dropAttr = ' ondragover="handleRowDragOver(event)" ondragleave="handleRowDragLeave(event)" ondrop="handleRowDrop(event, \'' + panel + '\', ' + i + ')"';

            var posIndicator = '<div class="w-5 h-5 rounded-full bg-gray-200 text-gray-600 text-xs flex items-center justify-center font-bold">' + (i + 1) + '</div>';

            return '<tr class="' + rowClasses + ' group" data-id="' + r.id + '" ' + dragAttr + dropAttr + '>'
                + '<td class="px-2 py-3 align-middle">' + posIndicator + '</td>'
                + '<td class="px-3 py-3 font-medium text-gray-800 flex items-center gap-1">' + escHtml(r.noOrden) + badges + '</td>'
                + '<td class="px-3 py-3 text-gray-600 align-middle">' + escHtml(r.tamanoClave) + '</td>'
                + '<td class="px-3 py-3 text-gray-600 align-middle truncate max-w-[150px]" title="' + escHtml(r.modelo) + '">' + escHtml(r.modelo) + '</td>'
                + '<td class="px-3 py-3 text-gray-800 font-semibold align-middle text-right">' + produccionFormatted + '</td>'
                + '</tr>';
        }).join('');
    }

    window.handleDragStart = function(event, panel, id) {
        moverState.draggedItemInfo = { sourcePanel: panel, id: id };
        event.dataTransfer.setData('application/json', JSON.stringify({ panel: panel, id: id }));
        event.dataTransfer.effectAllowed = 'move';

        setTimeout(function() {
            event.target.classList.add('opacity-40', 'scale-[0.99]', 'bg-gray-100');
        }, 10);
    };

    window.handleDragEnd = function(event) {
        event.target.classList.remove('opacity-40', 'scale-[0.99]', 'bg-gray-100');
        document.getElementById('panelDestinoContainer').classList.remove('ring-2', 'ring-blue-300');
        document.getElementById('panelOrigenContainer').classList.remove('ring-2', 'ring-amber-300');
        document.querySelectorAll('.border-t-2').forEach(el => el.classList.remove('border-t-2', 'border-blue-500', 'border-amber-500'));
        moverState.draggedItemInfo = null;
    };

    window.handleRowDragOver = function(event) {
        event.preventDefault();
        event.stopPropagation();
        event.dataTransfer.dropEffect = 'move';

        let tr = event.currentTarget;
        let isDestino = tr.closest('#moverDestinoTbody');
        let borderColor = isDestino ? 'border-blue-500' : 'border-amber-500';

        document.querySelectorAll('.border-t-2').forEach(el => el.classList.remove('border-t-2', 'border-blue-500', 'border-amber-500'));
        tr.classList.add('border-t-2', borderColor);
    };

    window.handleRowDragLeave = function(event) {
        let tr = event.currentTarget;
        tr.classList.remove('border-t-2', 'border-blue-500', 'border-amber-500');
    };

    window.handleRowDrop = function(event, targetPanel, targetIndex) {
        event.preventDefault();
        event.stopPropagation();

        if (!moverState.draggedItemInfo) return;

        let sourcePanel = moverState.draggedItemInfo.sourcePanel;
        let id = moverState.draggedItemInfo.id;

        let sourceArray = sourcePanel === 'origen' ? moverState.origenRegistros : moverState.destinoRegistros;
        let targetArray = targetPanel === 'origen' ? moverState.origenRegistros : moverState.destinoRegistros;

        let itemIndex = sourceArray.findIndex(r => r.id === id);
        if (itemIndex === -1) return;

        let item = sourceArray[itemIndex];

        if (targetPanel === 'destino' && !moverState.destinoTelar) return;
        if (targetPanel === 'origen' && !moverState.origenTelar) return;

        sourceArray.splice(itemIndex, 1);

        if (sourcePanel === targetPanel && itemIndex < targetIndex) {
            targetIndex--;
        }

        item.isMoved = true;
        targetArray.splice(targetIndex, 0, item);

        checkIfChanged();
        renderPanel('origen');
        renderPanel('destino');
    };

    window.handleDragOverContainer = function(event, targetPanel) {
        event.preventDefault();
        if (targetPanel === 'destino' && moverState.destinoTelar) {
            document.getElementById('panelDestinoContainer').classList.add('ring-2', 'ring-blue-300');
        } else if (targetPanel === 'origen' && moverState.origenTelar) {
            document.getElementById('panelOrigenContainer').classList.add('ring-2', 'ring-amber-300');
        }
    };

    window.handleDragLeaveContainer = function(event, targetPanel) {
        if (!event.currentTarget.contains(event.relatedTarget)) {
            if (targetPanel === 'destino') {
                document.getElementById('panelDestinoContainer').classList.remove('ring-2', 'ring-blue-300');
            } else {
                document.getElementById('panelOrigenContainer').classList.remove('ring-2', 'ring-amber-300');
            }
        }
    };

    window.handleDropContainer = function(event, targetPanel) {
        event.preventDefault();
        document.getElementById('panelDestinoContainer').classList.remove('ring-2', 'ring-blue-300');
        document.getElementById('panelOrigenContainer').classList.remove('ring-2', 'ring-amber-300');

        if (!moverState.draggedItemInfo) return;

        let sourcePanel = moverState.draggedItemInfo.sourcePanel;
        let id = moverState.draggedItemInfo.id;

        let sourceArray = sourcePanel === 'origen' ? moverState.origenRegistros : moverState.destinoRegistros;
        let targetArray = targetPanel === 'origen' ? moverState.origenRegistros : moverState.destinoRegistros;

        if (targetPanel === 'destino' && !moverState.destinoTelar) return;
        if (targetPanel === 'origen' && !moverState.origenTelar) return;

        let itemIndex = sourceArray.findIndex(r => r.id === id);
        if (itemIndex === -1) return;

        let item = sourceArray.splice(itemIndex, 1)[0];
        item.isMoved = true;
        targetArray.push(item);

        checkIfChanged();
        renderPanel('origen');
        renderPanel('destino');
    };

    function updateMoverButtons() {
        document.getElementById('btnMoverConfirm').disabled = !moverState.hasChanges;

        var btnRevertir = document.getElementById('btnMoverRevertir');
        if (moverState.hasChanges) {
            btnRevertir.classList.remove('hidden');
        } else {
            btnRevertir.classList.add('hidden');
        }

        var resumen = document.getElementById('moverResumen');
        if (moverState.hasChanges) {
            resumen.innerHTML = '<span class="text-blue-700 bg-blue-100 px-3 py-1.5 rounded-lg shadow-sm inline-block"><i class="fas fa-exclamation-triangle mr-3"></i>Hay cambios en el orden o asignación que deben ser guardados</span>';
        } else {
            resumen.innerHTML = '';
        }
    }

    window.confirmarMover = function () {
        if (!moverState.hasChanges) return;

        Swal.fire({
            title: '¿Guardar Cambios?',
            html: 'Se actualizarán las posiciones y asignaciones en base al orden mostrado en pantalla.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
        }).then(function (result) {
            if (result.isConfirmed) {
                ejecutarMover();
            }
        });
    };

    async function ejecutarMover() {
        Swal.fire({
            title: 'Procesando...',
            text: 'Guardando el nuevo orden y asignaciones',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function () { Swal.showLoading(); },
        });

        try {
            var payload = {
                ordenes_origen: moverState.origenRegistros.map(r => r.id),
                origen_salon: moverState.origenTelar ? moverState.origenTelar.salon : null,
                origen_telar: moverState.origenTelar ? moverState.origenTelar.telar : null,
                ordenes_destino: moverState.destinoRegistros.map(r => r.id),
                destino_salon: moverState.destinoTelar ? moverState.destinoTelar.salon : null,
                destino_telar: moverState.destinoTelar ? moverState.destinoTelar.telar : null,
            };

            var resp = await fetch(MOVER_ROUTES.procesar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            var data = await resp.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cambios Guardados',
                    text: data.message,
                    confirmButtonColor: '#2563eb',
                    timer: 2500,
                });

                moverState.hasChanges = false;
                if (moverState.origenTelar) await fetchRegistros('origen');
                if (moverState.destinoTelar) await fetchRegistros('destino');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudieron guardar los cambios',
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

    document.getElementById('modalMover')?.addEventListener('click', function (e) {
        if (e.target === this) cerrarModalMover();
    });
})();
</script>
@endpush
