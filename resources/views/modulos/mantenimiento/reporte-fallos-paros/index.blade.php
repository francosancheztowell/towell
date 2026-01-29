@extends('layouts.app')
@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-report
    type="button"
    id="btn-open-filters"
    title="Filtros"
    icon="fa-filter"
    bg="bg-green-600"
    iconColor="text-white"
    text="Filtrar"
    class="text-white"
    module="Solicitudes"
    />
    <x-navbar.button-create
    type="button"
    id="btn-nuevo-paro"
    title="Nuevo Paro"
    module="Solicitudes"
    />
    <x-navbar.button-delete
    type="button"
    id="btn-terminar-paro"
    title="Terminar Paro"
    module="Solicitudes"
    />
</div>
@endsection
@section('page-title', 'Reporte de Fallos y Paros')
@section('content')
<div class="w-full">
    <div class="bg-white">
        <div class="flex gap-4">
            <!-- Tabla -->
            <div class="flex-1 overflow-auto max-h-[70vh] rounded-lg border border-gray-300">
                <table class="w-full border-collapse text-sm min-w-full">
                    <thead>
                        <tr class="text-white text-center">
                            <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Folio</th>
                            <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Status</th>
                            <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Fecha</th>
                            <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Hora</th>
                            <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Area</th>
                            <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Maquina</th>
                            <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Tipo Falla</th>
                            <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Falla</th>
                            <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-paros">
                        <!-- Los datos se cargarán dinámicamente aquí -->
                        <tr>
                            <td colspan="9" class="border border-gray-300 px-2 py-2 text-center text-gray-500">
                                Cargando datos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>


        </div>
    </div>
</div>

{{-- Modal Filtros (estilo BPM) --}}
<div id="modal-filters" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">
                <i class="fa-solid fa-filter text-purple-600 mr-2"></i>Filtros
            </h2>
            <button type="button" id="btn-close-modal-filters" class="text-slate-500 hover:text-slate-700 text-3xl leading-none">&times;</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                <label class="block text-xs text-gray-600 mb-2">
                    <i class="fa-solid fa-door-open mr-1"></i>Área
                </label>
                <select id="filter-depto" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                <label class="block text-xs text-gray-600 mb-2">
                    <i class="fa-solid fa-circle-info mr-1"></i>Status
                </label>
                <select id="filter-status" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="p-4 rounded-lg border-2 border-gray-300 bg-gray-50">
                <label class="block text-xs text-gray-600 mb-2">
                    <i class="fa-solid fa-gear mr-1"></i>Máquina
                </label>
                <select id="filter-maquina" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="button" id="btn-clear-filter" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 bg-blue-500 text-white hover:bg-blue-600 transition text-sm">
                <i class="fa-solid fa-eraser mr-1"></i>Limpiar
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbodyParos = document.getElementById('tbody-paros');

    // Ocultar botón de "Paro" en la barra de navegación
    const navLinks = document.querySelectorAll('nav a, header a, .nav a, [role="navigation"] a');
    navLinks.forEach(link => {
        const href = link.getAttribute('href') || '';
        if (href.includes('/mantenimiento/nuevo-paro') || href.includes('mantenimiento') && (link.textContent.includes('Paro') || link.textContent.includes('paro'))) {
            link.style.display = 'none';
        }
    });

    let allParos = [];

    function applyFilters() {
        const depto = (document.getElementById('filter-depto')?.value || '').trim();
        const status = (document.getElementById('filter-status')?.value || '').trim();
        const maquina = (document.getElementById('filter-maquina')?.value || '').trim();
        const rows = document.querySelectorAll('#tbody-paros tr.row-paro');
        const noResults = document.getElementById('filter-no-results');

        let visible = 0;
        rows.forEach(tr => {
            const d = (tr.dataset.depto || '').toString().trim();
            const s = (tr.dataset.status || '').toString().trim();
            const m = (tr.dataset.maquina || '').toString().trim();
            const matchDepto = !depto || d === depto;
            const matchStatus = !status || s === status;
            const matchMaquina = !maquina || m === maquina;
            const show = matchDepto && matchStatus && matchMaquina;
            tr.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const anyFilter = depto || status || maquina;
        if (noResults) {
            noResults.style.display = anyFilter && visible === 0 ? '' : 'none';
        }
    }

    // Cargar todos los paros (sin filtro backend)
    async function cargarParos() {
        try {
            const response = await fetch('{{ route('api.mantenimiento.paros.index') }}');
            const result = await response.json();

            if (result.success && Array.isArray(result.data)) {
                allParos = result.data || [];
                tbodyParos.innerHTML = '';

                if (allParos.length === 0) {
                    tbodyParos.innerHTML = `
                        <tr>
                            <td colspan="9" class="border border-gray-300 px-2 py-2 text-center text-gray-500">
                                No hay paros/fallas
                            </td>
                        </tr>
                    `;
                    ['filter-depto', 'filter-status', 'filter-maquina'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.innerHTML = '<option value="">Todos</option>';
                    });
                    return;
                }

                const esc = (s) => (s ?? '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                const deptos = [...new Set(allParos.map(p => (p.Depto || '').toString().trim()).filter(Boolean))].sort();
                const statuses = [...new Set(allParos.map(p => (p.Estatus || '').toString().trim()).filter(Boolean))].sort();
                const maquinas = [...new Set(allParos.map(p => (p.MaquinaId || '').toString().trim()).filter(Boolean))].sort();

                const filterDepto = document.getElementById('filter-depto');
                const filterStatus = document.getElementById('filter-status');
                const filterMaquina = document.getElementById('filter-maquina');
                filterDepto.innerHTML = '<option value="">Todos</option>' + deptos.map(d => `<option value="${esc(d)}">${esc(d)}</option>`).join('');
                filterStatus.innerHTML = '<option value="">Todos</option>' + statuses.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
                filterMaquina.innerHTML = '<option value="">Todos</option>' + maquinas.map(m => `<option value="${esc(m)}">${esc(m)}</option>`).join('');

                allParos.forEach(paro => {
                    const row = document.createElement('tr');
                    row.dataset.paroId = paro.Id || '';
                    row.dataset.depto = (paro.Depto || '').toString().trim();
                    row.dataset.status = (paro.Estatus || '').toString().trim();
                    row.dataset.maquina = (paro.MaquinaId || '').toString().trim();
                    row.className = 'row-paro cursor-pointer hover:bg-gray-100 transition-colors';

                    const fecha = paro.Fecha ? new Date(paro.Fecha).toLocaleDateString('es-MX', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit'
                    }) : '';

                    const est = (paro.Estatus || '').toString().trim();
                    const badgeActivo = 'inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium';
                    const badgeEstatus = est.toLowerCase() === 'activo'
                        ? `<span class="${badgeActivo} bg-blue-100 text-blue-800">${esc(est || '—')}</span>`
                        : `<span class="${badgeActivo} bg-gray-100 text-gray-800">${esc(est || '—')}</span>`;

                    row.innerHTML = `
                        <td class="px-2 py-2 text-gray-900 text-lg text-center">${esc(paro.Folio)}</td>
                        <td class="px-2 py-2 text-lg text-center">${badgeEstatus}</td>
                        <td class="px-2 py-2 text-gray-900 text-lg text-center">${esc(fecha)}</td>
                        <td class="px-2 py-2 text-gray-900 text-lg text-center">${esc(paro.Hora)}</td>
                        <td class="px-2 py-2 text-gray-900 text-lg text-center">${esc(paro.Depto)}</td>
                        <td class="px-2 py-2 text-gray-900 text-lg text-center">${esc(paro.MaquinaId)}</td>
                        <td class="px-2 py-2 text-gray-900 text-lg text-center">${esc(paro.TipoFallaId)}</td>
                        <td class="px-2 py-2 text-gray-900 text-lg text-center">${esc(paro.Falla)}</td>
                        <td class="px-2 py-2 text-gray-900 text-lg text-center">${esc(paro.NomEmpl)}</td>
                    `;

                    row.addEventListener('click', function() {
                        document.querySelectorAll('#tbody-paros tr.row-paro').forEach(r => {
                            r.classList.remove('bg-blue-500');
                            r.classList.add('hover:bg-gray-100');
                            r.querySelectorAll('td').forEach(td => {
                                td.classList.remove('text-white');
                                td.classList.add('text-gray-900');
                            });
                        });
                        this.classList.remove('hover:bg-gray-100');
                        this.classList.add('bg-blue-500');
                        this.querySelectorAll('td').forEach(td => {
                            td.classList.remove('text-gray-900');
                            td.classList.add('text-white');
                        });
                        window.paroSeleccionadoId = this.dataset.paroId || '';
                    });

                    tbodyParos.appendChild(row);
                });

                const noRow = document.createElement('tr');
                noRow.id = 'filter-no-results';
                noRow.style.display = 'none';
                noRow.innerHTML = '<td colspan="9" class="border border-gray-300 px-2 py-2 text-center text-gray-500">No hay paros con el filtro aplicado</td>';
                tbodyParos.appendChild(noRow);

                applyFilters();
            } else {
                tbodyParos.innerHTML = `
                    <tr>
                        <td colspan="9" class="border border-gray-300 px-2 py-2 text-center text-red-500">
                            Error al cargar los datos: ${result.error || 'Error desconocido'}
                        </td>
                    </tr>
                `;
            }
        } catch (error) {
            console.error('Error al cargar paros:', error);
            tbodyParos.innerHTML = `
                <tr>
                    <td colspan="9" class="border border-gray-300 px-2 py-2 text-center text-red-500">
                        Error de conexión. Por favor, recarga la página.
                    </td>
                </tr>
            `;
        }
    }

    // Variable global para almacenar el ID del paro seleccionado
    window.paroSeleccionadoId = null;

    cargarParos();

    const modalFilters = document.getElementById('modal-filters');
    function openFiltersModal() {
        if (!modalFilters) return;
        modalFilters.classList.remove('hidden');
        modalFilters.classList.add('flex');
    }
    function closeFiltersModal() {
        if (!modalFilters) return;
        modalFilters.classList.add('hidden');
        modalFilters.classList.remove('flex');
    }

    document.getElementById('btn-open-filters')?.addEventListener('click', openFiltersModal);
    document.getElementById('btn-close-modal-filters')?.addEventListener('click', closeFiltersModal);
    modalFilters?.addEventListener('click', function(e) {
        if (e.target === modalFilters) closeFiltersModal();
    });

    document.getElementById('filter-depto')?.addEventListener('change', applyFilters);
    document.getElementById('filter-status')?.addEventListener('change', applyFilters);
    document.getElementById('filter-maquina')?.addEventListener('change', applyFilters);
    document.getElementById('btn-clear-filter')?.addEventListener('click', function() {
        ['filter-depto', 'filter-status', 'filter-maquina'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        applyFilters();
        closeFiltersModal();
    });

    // Botón Nuevo: redirigir a nuevo-paro
    const btnNuevo = document.getElementById('btn-nuevo-paro');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = '{{ route('mantenimiento.nuevo-paro') }}';
        });
    }

    // Botón Terminar: redirigir a finalizar-paro con el paro seleccionado
    const btnTerminar = document.getElementById('btn-terminar-paro');
    if (btnTerminar) {
        btnTerminar.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.paroSeleccionadoId) {
                // Guardar ID en localStorage para que la vista de finalizar lo recupere
                localStorage.setItem('selectedParoId', window.paroSeleccionadoId);
                window.location.href = '{{ route('mantenimiento.finalizar-paro') }}';
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Seleccione un paro',
                        text: 'Por favor, seleccione un paro de la tabla para finalizar.'
                    });
                } else {
                    alert('Por favor, seleccione un paro de la tabla para finalizar.');
                }
            }
        });
    }
});
</script>
@endsection

