@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', $vm['pageTitle'] ?? 'Nuevo Requerimiento')

@section('navbar-right')
    @if(!empty($vm['listaTelares']))
    <div class="relative">
        <!-- Dropdown de Telares -->
        <button type="button" id="btnDropdownTelares"
                class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
            <span class="font-medium">Telares</span>
            <svg class="w-4 h-4 transition-transform" id="iconDropdown" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        <!-- Menu Dropdown -->
        <div id="menuDropdownTelares" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 max-h-96 overflow-y-auto z-50">
            <div class="py-2">
                <button type="button" onclick="irATelar('')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">
                    <span class="font-medium">Todos los telares</span>
                </button>
                <div class="border-t border-gray-200 my-1"></div>
                    @foreach($vm['listaTelares'] as $t)
                    <button type="button" onclick="irATelar('{{ $t }}')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">
                        Telar <span class="font-semibold">{{ $t }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
    @endif
@endsection

@section('content')
    <div class="container mx-auto">
    @php
        $nrVmData = [
            'hasQueryFolio' => request()->has('folio'),
            'enProcesoExists' => $vm['enProcesoExists'] ?? false,
            'consultaUrl' => route('tejido.inventario.trama.consultar.requerimiento'),
        ];
    @endphp
        <script>
        window.ACTUALIZAR_CANTIDAD_URL = @json($vm['actualizarCantidadUrl'] ?? url('/modulo-nuevo-requerimiento/actualizar-cantidad'));
        window.NR_VM = @json($nrVmData);
        </script>

        <div class="space-y-6">
        @forelse(($vm['telares'] ?? []) as $telar)
            @php $d = $telar['telarData'] ?? []; @endphp
            <div id="telar-{{ $telar['numero'] }}" class="telar-section relative bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
                 data-telar="{{ $telar['numero'] }}"
                 data-salon="{{ $telar['salon'] }}"
                 data-orden="{{ $d['Orden_Prod'] ?? '' }}"
                 data-producto="{{ $d['Nombre_Producto'] ?? '' }}">

                <div class="{{ $telar['tipo'] === 'itema' ? 'bg-gradient-to-b from-gray-400 to-gray-500' : 'bg-gradient-to-b from-blue-600 to-blue-700' }} absolute left-0 top-0 bottom-0 w-[110px] sm:w-[92px] md:w-[110px] flex flex-col items-center justify-between py-2 px-1.5 border-r border-gray-200">
                    <div class="text-center w-full mt-1">
                        <h2 class="text-[2.5rem] font-extrabold text-white leading-none py-3 text-center drop-shadow-sm">{{ $telar['numero'] }}</h2>
                    </div>
                    @if(!empty($telar['ordenSig']))
                        <div class="absolute left-0 right-0 text-white text-sm tracking-wider text-center pointer-events-none opacity-95 font-bold top-1/2 -translate-y-1/2">
                        </div>
                    @endif
                    <div class="w-full mt-auto">
                        <button type="button" onclick="agregarNuevoRequerimiento(this); return false;"
                                 class="w-full flex flex-col items-center justify-center gap-0.5 px-2 py-2 bg-white/95 text-blue-700 hover:bg-white shadow-sm rounded-md transition-colors">

                            <span class="text-[10px] leading-3 font-semibold">Nuevo</span>
                            <span class="text-[10px] leading-3 font-semibold">Requerimiento</span>
                        </button>
                    </div>
                </div>

                <div class="p-6 ml-[112px] sm:ml-[96px] md:ml-[112px]">
                    <div class="grid grid-cols-3 lg:grid-cols-3 gap-6 mb-6">
                        <div class="space-y-3">
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Folio:</span>
                                <span class="text-sm font-semibold text-gray-900 folio-actual">{{ $vm['folio'] ?? '-' }}</span>
                    </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Fecha:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $vm['fecha'] ?? '' }}</span>
                    </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Turno:</span>
                                <span class="text-sm font-semibold text-gray-900 turno-actual">{{ $vm['turnoDesc'] ?? '' }}</span>
                    </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Orden:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $d['Orden_Prod'] ?? '-' }}</span>
                    </div>
                </div>

                        <div class="space-y-3">
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">No Flog:</span>
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ trim(($d['Id_Flog'] ?? '') . ' / ' . ($d['Calidad'] ?? '')) ?: '-' }}
                                </span>
                            </div>
                            {{-- <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Calidad:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $d['Calidad'] ?? '-' }}</span>
                            </div> --}}
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Cliente:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $d['Cliente'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Tamaño:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $d['InventSizeId'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Artículo:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ ($d['ItemId'] ?? '-') . ' ' . ($d['Nombre_Producto'] ?? '-') }}</span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Pedido:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $d['Saldos'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Producción:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $d['Produccion'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Inicio:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $d['Inicio_Tejido'] ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Fin:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $d['Fin_Tejido'] ?? '-' }}</span>
                        </div>
                        </div>
                        </div>

                    <div class="w-full h-px bg-gray-300 my-4" aria-hidden="true"></div>

                    <div class="rounded-lg overflow-hidden relative z-10">
                        <table class="w-full mt-2.5">
                            <thead class="relative z-10">
                                <tr class="bg-gray-100">
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Artículo</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fibra</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Cod Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach(($telar['rows'] ?? []) as $row)
                                <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }} hover:bg-blue-50" @if(!empty($row['id'])) data-consumo-id="{{ $row['id'] }}" @endif>
                                        <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $row['calibre'] !== null ? number_format((float)$row['calibre'], 2) : '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $row['fibra'] ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $row['cod_color'] ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $row['color'] ?? '-' }}</td>
                                            <td class="px-4 py-1">
                                                <div class="flex items-center justify-center relative">
                                                    <button class="edit-quantity-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center gap-2" onclick="toggleQuantityEdit(this)">
                                                <span class="quantity-display text-md font-semibold">{{ (int)($row['cantidad'] ?? 0) }}</span>
                                                    </button>
                                                    <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                                <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                                            <div class="flex space-x-1 min-w-max">
                                                                @for($i = 0; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ (int)($row['cantidad'] ?? 0) == $i ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                                @endfor
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
                    <i class="fas fa-trash text-2xl mb-2"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay requerimientos disponibles</h3>
                    <p class="text-gray-500">No se encontraron requerimientos que coincidan con los filtros seleccionados</p>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <!-- Modal para Agregar Nuevo Requerimiento -->
    <div id="modal-nuevo-requerimiento" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="bg-blue-500 px-6 py-4 rounded-t-lg sticky top-0 z-10">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-white">Agregar Nuevo Requerimiento</h3>
                    <button onclick="cerrarModal()" class="text-white hover:text-gray-200 transition-colors ml-4">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="form-nuevo-requerimiento">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Artículo</label>
                            <input type="number" step="0.01" id="modal-articulo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: 10.5" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Fibra</label>
                            <input type="text" id="modal-fibra" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: ALGODÓN" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cod Color</label>
                            <input type="text" id="modal-cod-color" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: A8" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre Color</label>
                            <input type="text" id="modal-nombre-color" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: BLANCO" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad</label>
                            <input type="number" min="0" max="100" id="modal-cantidad" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="0" value="0" required>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="cerrarModal()" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">Cancelar</button>
                        <button type="button" onclick="agregarCampo()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Agregar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


<script>
    // --- Scroll to telar & URL sync (función debe estar antes de DOMContentLoaded)
    (function(){
        function getScrollable(node){
            let n = node ? node.parentElement : null;
            while (n && n !== document.body) {
                const cs = getComputedStyle(n);
                const oy = cs.overflowY;
                if ((oy === 'auto' || oy === 'scroll') && n.scrollHeight > n.clientHeight) return n;
                n = n.parentElement;
            }
            return document.scrollingElement || document.documentElement;
        }
        window.irATelar = function(noTelar){
            const menu = document.getElementById('menuDropdownTelares');
            const icon = document.getElementById('iconDropdown');
            if (menu) menu.classList.add('hidden');
            if (icon) icon.style.transform = 'rotate(0deg)';
            document.querySelectorAll('[id^="telar-"]').forEach(el => el.classList.remove('hidden'));
            if (!noTelar) {
                const u0 = new URL(location.href); u0.search = ''; u0.hash = ''; history.replaceState(null,'',u0.toString());
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }
            const el = document.getElementById('telar-'+noTelar);
            if (!el) return;
            const sticky = document.querySelector('nav.sticky, nav.fixed, .sticky.top-0, .fixed.top-16');
            const stickyH = sticky ? sticky.getBoundingClientRect().height : 0;
            const extra = -50;
            const scroller = getScrollable(el);
            const scRect = scroller.getBoundingClientRect ? scroller.getBoundingClientRect() : { top: 0 };
            const tRect = el.getBoundingClientRect();
            const current = scroller.scrollTop || window.pageYOffset || document.documentElement.scrollTop || 0;
            const targetTop = tRect.top - scRect.top + current - stickyH - extra;
            if (scroller.scrollTo) scroller.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
            else window.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
            const url = new URL(location.href); url.search = ''; url.hash = 'telar-'+noTelar; history.replaceState(null,'',url.toString());
        }
    })();

    // --- Quantity editor helpers
        function toggleQuantityEdit(element) {
            const row = element.closest('tr');
            const editContainer = row.querySelector('.quantity-edit-container');
            const editBtn = row.querySelector('.edit-quantity-btn');
                closeAllQuantityEditors();
        editContainer.classList.toggle('hidden');
        if (editBtn) editBtn.classList.toggle('hidden');
    }
        function closeAllQuantityEditors() {
            document.querySelectorAll('.quantity-edit-container').forEach(container => {
                if (!container.classList.contains('hidden')) {
                    const row = container.closest('tr');
                    const editBtn = row.querySelector('.edit-quantity-btn');
                    container.classList.add('hidden');
                if (editBtn) editBtn.classList.remove('hidden');
                }
            });
        }

    // --- App boot
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar Dropdown de Telares
        const btnDropdown = document.getElementById('btnDropdownTelares');
        const menuDropdown = document.getElementById('menuDropdownTelares');
        const iconDropdown = document.getElementById('iconDropdown');

        if (btnDropdown && menuDropdown) {
            btnDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                const isHidden = menuDropdown.classList.contains('hidden');
                menuDropdown.classList.toggle('hidden');
                if (iconDropdown) iconDropdown.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
            });

            document.addEventListener('click', function(e) {
                if (!btnDropdown.contains(e.target) && !menuDropdown.contains(e.target)) {
                    menuDropdown.classList.add('hidden');
                    if (iconDropdown) iconDropdown.style.transform = 'rotate(0deg)';
                }
            });
        }

        // Auto-enfocar si viene ?telar=### o hash #telar-###
        const url = new URL(location.href);
        let t = url.searchParams.get('telar');
        if(!t && location.hash.startsWith('#telar-')){
            t = location.hash.replace('#telar-', '');
        }
        if(t){ setTimeout(() => irATelar(t), 300); }

        // Redirigir si hay En Proceso y no estamos en edición
        // Pero NO redirigir si el usuario viene de "consultar" (para evitar bucles)
        try {
            const vieneDeConsultar = sessionStorage.getItem('vieneDeConsultar') === 'true';
            const timestamp = sessionStorage.getItem('vieneDeConsultarTimestamp');
            const tiempoTranscurrido = timestamp ? Date.now() - parseInt(timestamp) : Infinity;

            // Limpiar la marca si pasó más de 5 segundos (ya no es relevante)
            if (tiempoTranscurrido > 5000) {
                sessionStorage.removeItem('vieneDeConsultar');
                sessionStorage.removeItem('vieneDeConsultarTimestamp');
            }

            if (!window.NR_VM.hasQueryFolio && window.NR_VM.enProcesoExists && !vieneDeConsultar) {
                Swal.fire({
                    icon: 'info',
                    title: 'Orden en Proceso',
                    text: 'Aún sigue en proceso esta orden. Será redirigido a Consultar Requerimiento.',
                    timer: 8000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    // Usar replaceState para evitar que el botón "atrás" vuelva aquí
                    history.replaceState(null, '', window.NR_VM.consultaUrl);
                    window.location.href = window.NR_VM.consultaUrl;
                });
            } else if (vieneDeConsultar) {
                // Limpiar la marca ya que ya estamos en nuevo
                sessionStorage.removeItem('vieneDeConsultar');
                sessionStorage.removeItem('vieneDeConsultarTimestamp');
            }
        } catch (e) {}

        // Cerrar editores al hacer click fuera
        document.addEventListener('click', function(event) {
            const isInsideEditor = event.target.closest('.quantity-edit-container');
            const isEditButton = event.target.closest('.edit-quantity-btn');
            if (!isInsideEditor && !isEditButton) closeAllQuantityEditors();
        });

        // Captura delegada para números (mejor rendimiento)
        document.addEventListener('click', function(e){
            const opt = e.target.closest('.number-option');
            if(!opt) return;
            e.preventDefault(); e.stopPropagation();
            const container = opt.closest('.number-scroll-container');
            const allOptions = container.querySelectorAll('.number-option');
            const row = opt.closest('tr');
            const quantityDisplay = row?.querySelector('.quantity-display');
            const selectedValue = opt.getAttribute('data-value');
            allOptions.forEach(o=>{ o.classList.remove('bg-blue-500','text-white'); o.classList.add('bg-gray-100','text-gray-700'); });
            opt.classList.remove('bg-gray-100','text-gray-700'); opt.classList.add('bg-blue-500','text-white');
            if (quantityDisplay) quantityDisplay.textContent = selectedValue;
            const consumoId = row?.getAttribute('data-consumo-id');
            if (consumoId) {
                actualizarCantidadEnBD(consumoId, selectedValue);
            } else {
                scheduleGuardarRequerimientos();
                showToast(`Cantidad actualizada a ${selectedValue} conos`);
            }
            const editContainer = row?.querySelector('.quantity-edit-container');
            const editBtn = row?.querySelector('.edit-quantity-btn');
            if (editContainer) editContainer.classList.add('hidden');
            if (editBtn) editBtn.classList.remove('hidden');
        }, true);

        // Guardado automático al inicio si estamos en modo nuevo y hay datos
        const params = new URLSearchParams(window.location.search);
        const folioQuery = params.get('folio') || '';
        if (!folioQuery) {
            // Modo nuevo: esperar un momento para que la página cargue completamente
            setTimeout(() => {
                const consumos = buildConsumosPayload();
                // Solo guardar si hay consumos con calibres (filas con datos)
                const tieneConsumos = consumos.some(c => c.calibre !== null && c.calibre !== undefined);
                if (tieneConsumos) {
                    // Mostrar alerta de que se está guardando y luego guardar con alerta
                    Swal.fire({
                        icon: 'info',
                        title: 'Guardando requerimientos',
                        text: 'Se están guardando los requerimientos automáticamente...',
                        timer: 1000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        autoGuardarRequerimientos(true); // true = mostrar alerta al finalizar
                    });
                }
            }, 1000);
        }
    });

    // --- Toast
    function showToast(message, type = 'success') {
        const map = { success: 'success', error: 'error', warning: 'warning', info: 'info' };
        Swal.fire({ toast: true, position: 'top-end', icon: map[type] || 'success', title: message, showConfirmButton: false, timer: 2000, timerProgressBar: true });
    }

    // --- Modal helpers
    let __telarTarget = null;
    function agregarNuevoRequerimiento(btn) {
        try { __telarTarget = btn ? btn.closest('.telar-section') : null; } catch(_) { __telarTarget = null; }
                    const modal = document.getElementById('modal-nuevo-requerimiento');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('form-nuevo-requerimiento')?.reset();
                    document.getElementById('modal-cantidad').value = 0;
    }
        function cerrarModal() {
            const modal = document.getElementById('modal-nuevo-requerimiento');
        modal.classList.add('hidden'); modal.classList.remove('flex');
        }

        function agregarCampo(){
            const articuloEl = document.getElementById('modal-articulo');
            const fibraEl = document.getElementById('modal-fibra');
            const codColorEl = document.getElementById('modal-cod-color');
            const nombreColorEl = document.getElementById('modal-nombre-color');
            const cantidadEl = document.getElementById('modal-cantidad');
        if (!articuloEl || !fibraEl || !codColorEl || !nombreColorEl || !cantidadEl) { showToast('Faltan campos del modal', 'error'); return; }
        const articulo = (articuloEl.value||'').trim();
        const fibra = (fibraEl.value||'').trim();
        const codColor = (codColorEl.value||'').trim();
        const nombreColor = (nombreColorEl.value||'').trim();
            const cantidad = parseInt(cantidadEl.value ?? '0', 10) || 0;
        if (articulo === '' || isNaN(parseFloat(articulo))) { showToast('Ingrese un artículo válido (número)', 'warning'); return; }
        if (fibra === '' || codColor === '' || nombreColor === '') { showToast('Complete fibra, código y color', 'warning'); return; }
        agregarFilaATabla({ articulo, fibra, codColor, nombreColor, cantidad });
        cerrarModal(); showToast('Nuevo requerimiento agregado', 'success'); autoGuardarRequerimientos();
    }

        function agregarFilaATabla(datos) {
        let telarActivo = __telarTarget || document.querySelector('.telar-section');
            if (!telarActivo) return;
        const tbody = telarActivo.querySelector('tbody'); if (!tbody) return;
            const nuevaFila = document.createElement('tr');
        const newIndex = tbody.querySelectorAll('tr').length;
        nuevaFila.className = `${newIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50'} hover:bg-blue-50`;
            nuevaFila.innerHTML = `
                <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">${datos.articulo}</td>
                <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">${datos.fibra}</td>
                <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">${datos.codColor}</td>
                <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">${datos.nombreColor}</td>
                <td class="px-4 py-1">
                    <div class="flex items-center justify-center relative">
                        <button class="edit-quantity-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center gap-2" onclick="toggleQuantityEdit(this)">
                            <span class="quantity-display text-md font-semibold">${datos.cantidad}</span>
                        </button>
                        <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                        <div class="number-scroll-container overflow-x-auto w-48 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                <div class="flex space-x-1 min-w-max">
                                ${Array.from({length: 101}, (_, i) => `<span class=\"number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors ${i == datos.cantidad ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'}\" data-value=\"${i}\">${i}</span>`).join('')}
                                </div>
                            </div>
                        </div>
                    </div>
            </td>`;
            tbody.appendChild(nuevaFila);
            __telarTarget = null;
        }

    // --- Guardado
    function buildConsumosPayload() {
            const consumos = [];
        document.querySelectorAll('.telar-section').forEach(section => {
            const telarId = section.getAttribute('data-telar');
            const salon = section.getAttribute('data-salon');
            const orden = section.getAttribute('data-orden') || '';
            const producto = section.getAttribute('data-producto') || '';
            const filas = section.querySelectorAll('tbody tr');
            filas.forEach(row => {
                const q = row.querySelector('.quantity-display');
                if (!q) return;
                const cantidad = parseInt(q.textContent) || 0;
                        const celdas = row.querySelectorAll('td');
                if (celdas.length < 4) return;
                            const articuloTexto = celdas[0].textContent.trim();
                const calibre = parseFloat(articuloTexto.replace(',', '.'));
                const calibreValor = isNaN(calibre) ? null : calibre;
                const fibraTxt = celdas[1].textContent.trim();
                const codTxt = celdas[2].textContent.trim();
                const colorTxt = celdas[3].textContent.trim();
                const fibraNorm = (!fibraTxt || fibraTxt === '-') ? null : fibraTxt;
                const codNorm = (!codTxt || codTxt === '-') ? null : codTxt;
                const colorNorm = (!colorTxt || colorTxt === '-') ? null : colorTxt;
                consumos.push({ telar: telarId, salon, orden, calibre: calibreValor, producto, fibra: fibraNorm, cod_color: codNorm, color: colorNorm, cantidad });
            });
        });
        return consumos;
    }

    function autoGuardarRequerimientos(mostrarAlerta = false){
            const params = new URLSearchParams(window.location.search);
            const folioQuery = params.get('folio') || '';
        const consumos = buildConsumosPayload();
                fetch('/modulo-nuevo-requerimiento/guardar', {
                    method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: JSON.stringify({ consumos, numero_empleado: '', nombre_empleado: '', folio: folioQuery })
        })
        .then(res => res.json())
                .then(data => {
                    if (data.success) {
                if (data.folio) document.querySelectorAll('.folio-actual').forEach(el => el.textContent = data.folio);
                if (Array.isArray(data.consumos)) actualizarIdsEnFilas(data.consumos);
                // Marcar que se guardó exitosamente para que al regresar se recargue
                if (folioQuery) {
                    sessionStorage.setItem('folioGuardado', folioQuery);
                    sessionStorage.setItem('folioGuardadoTimestamp', Date.now().toString());
                }
                // Si es guardado automático al inicio, mostrar alerta más visible
                if (mostrarAlerta) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado exitoso!',
                        text: `Folio creado: ${data.folio}`,
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                } else {
                    showToast(folioQuery ? `Cambios editados. Folio: ${data.folio}` : `Folio creado: ${data.folio}`, 'success');
                }
                    } else {
                if (mostrarAlerta) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al guardar',
                        text: data.message || 'No se pudo crear el folio',
                        confirmButtonText: 'Aceptar'
                    });
                } else {
                    showToast(data.message || 'No se pudo crear folio', data.message ? 'warning' : 'error');
                }
            }
        })
        .catch(() => {
            if (mostrarAlerta) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar',
                    text: 'No se pudo crear el folio',
                    confirmButtonText: 'Aceptar'
                });
            } else {
                showToast('No se pudo crear folio', 'error');
            }
        });
    }

        let _guardarTimeout = null;
    function scheduleGuardarRequerimientos(){
            if (_guardarTimeout) clearTimeout(_guardarTimeout);
        _guardarTimeout = setTimeout(() => guardarRequerimientos(), 400);
    }

    function guardarRequerimientos(){
        const consumos = buildConsumosPayload();
        if (!consumos.length) { showToast('No hay requerimientos para guardar', 'warning'); return; }
        const params = new URLSearchParams(window.location.search);
        const folioQuery = params.get('folio') || '';
        fetch('/modulo-nuevo-requerimiento/guardar', {
                method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: JSON.stringify({ consumos, numero_empleado: '', nombre_empleado: '', folio: folioQuery })
        })
        .then(r => r.json())
            .then(data => {
                if (data.success) {
                if (data.folio) document.querySelectorAll('.folio-actual').forEach(el => el.textContent = data.folio);
                if (Array.isArray(data.consumos)) actualizarIdsEnFilas(data.consumos);
                // Marcar que se guardó exitosamente para que al regresar se recargue
                if (folioQuery) {
                    sessionStorage.setItem('folioGuardado', folioQuery);
                    sessionStorage.setItem('folioGuardadoTimestamp', Date.now().toString());
                }
                showToast(folioQuery ? `Cambios editados. Folio: ${data.folio}` : `Requerimientos guardados. Folio: ${data.folio}`, 'success');
            } else { showToast(data.message || 'No se pudo guardar', 'warning'); }
        })
        .catch(() => showToast('Error al guardar requerimientos', 'error'));
    }

    function actualizarIdsEnFilas(consumos){
        document.querySelectorAll('.telar-section').forEach(section => {
            const telarId = section.getAttribute('data-telar');
            const filas = section.querySelectorAll('tbody tr');
            filas.forEach(row => {
                const celdas = row.querySelectorAll('td'); if (celdas.length < 4) return;
                const articuloTexto = celdas[0].textContent.trim();
                const calibre = articuloTexto === '-' ? null : parseFloat(articuloTexto.replace(',', '.'));
                const fibraTxt = celdas[1].textContent.trim();
                const codTxt = celdas[2].textContent.trim();
                const colorTxt = celdas[3].textContent.trim();
                const fibra = (!fibraTxt || fibraTxt === '-') ? null : fibraTxt;
                const codColor = (!codTxt || codTxt === '-') ? null : codTxt;
                const color = (!colorTxt || colorTxt === '-') ? null : colorTxt;
                const match = (c) => {
                    const calibreMatch = (calibre === null && c.calibre === null) || (calibre !== null && c.calibre !== null && Math.abs(calibre - c.calibre) < 0.01);
                    const fibraMatch = (fibra === null && c.fibra === null) || (fibra === c.fibra);
                    const codMatch = (codColor === null && c.cod_color === null) || (codColor === c.cod_color);
                    const colorMatch = (color === null && c.color === null) || (color === c.color);
                    const telarMatch = String(telarId) === String(c.telar);
                    return calibreMatch && fibraMatch && codMatch && colorMatch && telarMatch;
                };
                const consumoMatch = consumos.find(match);
                if (consumoMatch && consumoMatch.id) row.setAttribute('data-consumo-id', consumoMatch.id);
            });
        });
    }


        // Manejador delegado (captura) para asegurar selección y cierre del editor
        document.addEventListener('click', function(e){
            const opt = e.target.closest('.number-option');
            if(!opt) return;
            e.preventDefault();
            e.stopPropagation();
            const container = opt.closest('.number-scroll-container');
            if(!container) return;
            const allOptions = container.querySelectorAll('.number-option');
            const row = opt.closest('tr');
            const quantityDisplay = row?.querySelector('.quantity-display');
            const selectedValue = opt.getAttribute('data-value');
            allOptions.forEach(o=>{ o.classList.remove('bg-blue-500','text-white'); o.classList.add('bg-gray-100','text-gray-700'); });
            opt.classList.remove('bg-gray-100','text-gray-700');
            opt.classList.add('bg-blue-500','text-white');
            if (quantityDisplay) quantityDisplay.textContent = selectedValue === '0' ? '-' : selectedValue;
            const consumoId = row?.getAttribute('data-consumo-id');
            if (consumoId) {
                actualizarCantidadEnBD(consumoId, selectedValue);
            } else {
                // Si no hay ID y los registros no han sido creados, crearlos primero
                if (!window.registrosCreados) {
                    showToast('Creando registros iniciales...', 'info');
                    // Ejecutar autoguardado para crear todos los registros
                    autoGuardarRequerimientos();
                    return;
                }

                // Si los registros ya fueron creados pero esta fila no tiene ID, intentar obtenerlos de nuevo
                if (window.registrosCreados && !row.getAttribute('data-consumo-id')) {
                    showToast('Obteniendo ID del registro...', 'info');
                    obtenerIdsRegistros();
                    return;
                }
            }
            const editContainer = row?.querySelector('.quantity-edit-container');
            const editBtn = row?.querySelector('.edit-quantity-btn');
            const display = row?.querySelector('.quantity-display');
            if (editContainer) editContainer.classList.add('hidden');
            if (editBtn) editBtn.classList.remove('hidden');
            if (display) display.classList.remove('hidden');
        }, true);



    function actualizarCantidadEnBD(consumoId, cantidad){
        fetch(window.ACTUALIZAR_CANTIDAD_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: JSON.stringify({ id: parseInt(consumoId), cantidad: parseFloat(cantidad) })
        })
        .then(r => r.json())
        .then(data => { if (data.success) showToast(`Cantidad actualizada: ${data.cantidad}`, 'success'); else showToast(data.message || 'Error al actualizar cantidad', 'error'); })
        .catch(() => showToast('Error al actualizar cantidad', 'error'));
    }

    </script>
@endsection
