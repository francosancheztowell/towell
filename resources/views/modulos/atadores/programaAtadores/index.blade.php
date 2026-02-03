@extends('layouts.app')

@section('page-title', 'Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        {{-- Botón de Filtros --}}
            <x-navbar.button-report
            id="btn-open-filters"
            title="Filtros"
            icon="fa-filter"
            text="Filtrar"
            module="Programa Atadores"
            iconColor="text-white"
            hoverBg="hover:bg-green-600"
            class="text-white"
            bg="bg-green-600" />
        <x-navbar.button-create
        id="btnIniciarAtado"
        onclick="iniciarAtado()"
        disabled
        module="Programa Atadores"
        title="Iniciar Atado"
        text="Iniciar Atado"
        />
    </div>
@endsection

@section('content')
{{-- Modal de Filtros --}}
<div id="modalFiltros" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white max-w-2xl w-full rounded-xl shadow-xl p-4 m-4">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm text-slate-500">Puedes elegir uno o varios filtros. Clic de nuevo para quitar.</p>
            <button type="button" onclick="cerrarModalFiltros()" class="text-slate-500 hover:text-slate-700 text-5xl leading-none">&times;</button>
        </div>

        <div class="grid grid-cols-3 gap-3">
            {{-- Fila 1: Ver Todos, Ver Creados, Activo --}}
            <button type="button" id="btn-filter-todos" onclick="aplicarFiltro('todos')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-list text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Ver Todos</div>
            </button>
            <button type="button" id="btn-filter-creados" onclick="aplicarFiltro('creados')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-plus-circle text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Ver Creados</div>
            </button>
            <button type="button" id="btn-filter-activo" onclick="aplicarFiltro('activo')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-circle-dot text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Activo</div>
            </button>

            {{-- Fila 2: En Proceso, Calificados, Terminados --}}
            <button type="button" id="btn-filter-en-proceso" onclick="aplicarFiltro('en-proceso')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-play-circle text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">En Proceso</div>
            </button>
            <button type="button" id="btn-filter-calificados" onclick="aplicarFiltro('calificados')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-star text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Calificados</div>
            </button>
            <button type="button" id="btn-filter-terminados" onclick="aplicarFiltro('terminados')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-check-circle text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Terminados</div>
            </button>
            <button type="button" id="btn-filter-autorizados" onclick="aplicarFiltro('autorizados')"
                    class="filter-btn p-4 rounded-lg border-2 transition-all text-center bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-thumbs-up text-2xl mb-2 block"></i>
                <div class="font-semibold text-sm">Autorizado</div>
            </button>
        </div>
    </div>
</div>

<div class="container mx-auto px-4 py-4">

    <div class="overflow-x-auto overflow-y-auto rounded-lg shadow-md bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-md">
                <thead class="bg-blue-500 sticky top-0 z-10">
                    <tr>
                        <th data-sort="fecha" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Fecha <span class="sort-icon ml-1 opacity-80">▲</span> </th>
                        <th data-sort="estatus" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Estatus <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="turno" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Turno <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="telar" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Telar <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="tipo" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Tipo <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="julio" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Julio <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="ubicacion" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Ubicación <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="metros" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Metros <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="orden" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Orden <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="tipo-atado" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Tipo <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="cuenta" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Cuenta <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="calibre" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Calibre <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="hilo" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Hilo <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="lote" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Lote <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="no-prov" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> No. Prov. <span class="sort-icon ml-1 opacity-80"></span> </th>
                        <th data-sort="hr-paro" class="th-sortable px-2 py-2 text-left text-md font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500 cursor-pointer hover:bg-blue-600 select-none" role="button" title="Clic para ordenar"> Hr. Paro <span class="sort-icon ml-1 opacity-80"></span> </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="tb-body">
                    @forelse($inventarioTelares as $item)
                        <tr class="table-row hover:bg-blue-100 cursor-pointer transition-colors duration-150"
                            onclick="selectRow(this, {{ $item->id }})"
                            data-id="{{ $item->id }}"
                            data-fecha="{{ $item->fecha ? $item->fecha->format('Y-m-d') : '9999-99-99' }}"
                            data-estatus="{{ $item->status_proceso ?? 'Activo' }}"
                            data-turno="{{ $item->turno ?? '' }}"
                            data-telar="{{ $item->no_telar ?? '' }}"
                            data-tipo="{{ $item->tipo ?? '' }}"
                            data-no-julio="{{ $item->no_julio ?? '' }}"
                            data-ubicacion="{{ $item->localidad ?? '' }}"
                            data-metros="{{ $item->metros !== null && $item->metros !== '' ? $item->metros : '-999999' }}"
                            data-no-orden="{{ $item->no_orden ?? '' }}"
                            data-config-id="{{ $item->ConfigId ?? '' }}"
                            data-invent-size-id="{{ $item->InventSizeId ?? '' }}"
                            data-invent-color-id="{{ $item->InventColorId ?? '' }}"
                            data-tipo-atado="{{ $item->tipo_atado ?? '' }}"
                            data-cuenta="{{ $item->cuenta ?? '' }}"
                            data-calibre="{{ $item->calibre !== null && $item->calibre !== '' ? $item->calibre : '-999999' }}"
                            data-hilo="{{ $item->hilo ?? '' }}"
                            data-lote="{{ $item->LoteProveedor ?? '' }}"
                            data-no-prov="{{ $item->NoProveedor ?? '' }}"
                            data-hora-paro="{{ $item->horaParo ?? '' }}"
                            data-status="{{ $item->status_proceso ?? 'Activo' }}">
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->fecha ? $item->fecha->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md" data-status="{{ $item->status_proceso }}">
                                <span class="px-1.5 py-0.5 rounded-full text-md font-semibold
                                    @if($item->status_proceso === 'Activo') bg-gray-200 text-gray-800
                                    @elseif($item->status_proceso === 'En Proceso') bg-blue-200 text-blue-800
                                    @elseif($item->status_proceso === 'Terminado') bg-purple-200 text-purple-800
                                    @elseif($item->status_proceso === 'Calificado') bg-yellow-200 text-yellow-800
                                    @elseif($item->status_proceso === 'Autorizado') bg-green-200 text-green-800
                                    @endif">
                                    {{ $item->status_proceso }}
                                </span>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->turno ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->no_telar ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->tipo ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->no_julio ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->localidad ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->metros ? number_format($item->metros, 2) : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->no_orden ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->tipo_atado ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">de J
                                {{ $item->cuenta ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->calibre ? number_format($item->calibre, 2) : '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->hilo ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->LoteProveedor ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
                                {{ $item->NoProveedor ?? '-' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-md">
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

// Estado de filtros - Array de filtros seleccionados (vacíos = ver todos)
let filterState = {
    filtros: @json($filtroAplicado === 'todos' ? [] : [$filtroAplicado]),
    telaresUsuario: @json($telaresUsuario ?? []),
    esTejedor: {{ $esTejedor ?? false ? 'true' : 'false' }}
};

// Orden por columnas: column = clave de columna, dir = 'asc' | 'desc'
let sortState = { column: 'fecha', dir: 'asc' };
const SORT_NUMERIC_COLUMNS = ['metros', 'calibre', 'julio', 'orden'];

function getDataKey(col) {
    if (col === 'julio') return 'data-no-julio';
    if (col === 'orden') return 'data-no-orden';
    if (col === 'hr-paro') return 'data-hora-paro';
    return 'data-' + col;
}

function updateSortIcons() {
    document.querySelectorAll('.th-sortable .sort-icon').forEach((span, idx) => {
        const th = span.closest('th');
        const col = th?.getAttribute('data-sort');
        if (col === sortState.column) {
            span.textContent = sortState.dir === 'asc' ? '▲' : '▼';
        } else {
            span.textContent = '';
        }
    });
}

// Funciones para el modal de filtros
function mostrarModalFiltros() {
    const modal = document.getElementById('modalFiltros');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function cerrarModalFiltros() {
    const modal = document.getElementById('modalFiltros');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Toggle de un filtro (permite elegir 2 o más). Ver Todos limpia el resto.
// "Autorizado" hace GET al servidor con ?filtro=autorizados para traer solo esos registros de AtaMontadoTelas.
function aplicarFiltro(tipo) {
    const baseUrl = @json(route('atadores.programa'));

    if (tipo === 'autorizados') {
        const idx = filterState.filtros.indexOf('autorizados');
        if (idx >= 0) {
            filterState.filtros.splice(idx, 1);
            cerrarModalFiltros();
            if (filterState.filtros.length === 0) {
                window.location.href = baseUrl;
            } else {
                updateFilterButtons();
                applyFilters();
            }
        } else {
            cerrarModalFiltros();
            window.location.href = baseUrl + '?filtro=autorizados';
        }
        return;
    }

    if (tipo === 'todos') {
        filterState.filtros = [];
    } else {
        const idx = filterState.filtros.indexOf(tipo);
        if (idx >= 0) {
            filterState.filtros.splice(idx, 1);
        } else {
            filterState.filtros.push(tipo);
        }
    }
    updateFilterButtons();
    applyFilters();
}

// Qué status(es) incluye cada clave de filtro
function statusMatchesFilter(status, noTelar, filterKey) {
    switch (filterKey) {
        case 'creados':
            return status === 'Activo';
        case 'activo':
        case 'activo-proceso':
            // El área de atadores puede ver tanto Activo como En Proceso
            return status === 'Activo' || status === 'En Proceso';
        case 'en-proceso':
            return status === 'En Proceso';
        case 'calificados':
            return status === 'Calificado';
        case 'terminados':
            let ok = status === 'Terminado';
            if (filterState.esTejedor && filterState.telaresUsuario.length > 0) {
                const noTelarStr = String(noTelar || '');
                ok = ok && filterState.telaresUsuario.some(t => String(t) === noTelarStr);
            }
            return ok;
        case 'autorizados':
            return status === 'Autorizado';
        default:
            return false;
    }
}

// Función para aplicar filtros a las filas (varios filtros = unión: se muestra si coincide con alguno)
// NOTA: El backend ya filtra por rol/área, así que estos filtros son adicionales del usuario
function applyFilters() {
    const rows = document.querySelectorAll('.table-row');
    const tbody = document.getElementById('tb-body');
    let visibleCount = 0;
    const filtros = filterState.filtros || [];

    rows.forEach(row => {
        const status = row.getAttribute('data-status') || 'Activo';
        const noTelar = row.getAttribute('data-telar') || '';
        let show = true;

        // Solo aplicar filtros si el usuario los ha seleccionado explícitamente
        // Si filtros está vacío, mostrar todos los registros que el backend ya filtró
        if (filtros.length > 0) {
            show = filtros.some(f => statusMatchesFilter(status, noTelar, f));
        }
        // Si no hay filtros seleccionados, mostrar todas las filas (el backend ya filtró por rol)

        if (show) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const filterBadge = document.getElementById('filter-badge');
    if (filtros.length > 0 && filterBadge) {
        filterBadge.textContent = filtros.length;
        filterBadge.classList.remove('hidden');
    } else if (filterBadge) {
        filterBadge.classList.add('hidden');
    }

    // Mostrar mensaje si no hay resultados
    let emptyRow = tbody?.querySelector('tr.no-results');
    if (visibleCount === 0) {
        if (!emptyRow) {
            const tr = document.createElement('tr');
            tr.className = 'no-results';
            tr.innerHTML = `<td colspan="16" class="px-6 py-4 text-center text-sm text-gray-500">
                <div class="flex flex-col items-center gap-2">
                    <i class="fa-solid fa-inbox text-4xl text-gray-300"></i>
                    <span class="text-base font-medium">Sin resultados con los filtros aplicados</span>
                </div>
            </td>`;
            tbody?.appendChild(tr);
        }
    } else {
        emptyRow?.remove();
    }
}

// Actualizar estado visual de botones (varios pueden estar activos)
function updateFilterButtons() {
    const btns = [
        document.getElementById('btn-filter-todos'),
        document.getElementById('btn-filter-creados'),
        document.getElementById('btn-filter-activo'),
        document.getElementById('btn-filter-en-proceso'),
        document.getElementById('btn-filter-calificados'),
        document.getElementById('btn-filter-terminados'),
        document.getElementById('btn-filter-autorizados')
    ];

    const keyToBtn = {
        'todos': [btns[0], 'bg-green-100', 'border-green-400', 'text-green-800'],
        'creados': [btns[1], 'bg-blue-100', 'border-blue-400', 'text-blue-800'],
        'activo': [btns[2], 'bg-teal-100', 'border-teal-400', 'text-teal-800'],
        'activo-proceso': [btns[2], 'bg-teal-100', 'border-teal-400', 'text-teal-800'],
        'en-proceso': [btns[3], 'bg-yellow-100', 'border-yellow-400', 'text-yellow-800'],
        'calificados': [btns[4], 'bg-amber-100', 'border-amber-400', 'text-amber-800'],
        'terminados': [btns[5], 'bg-purple-100', 'border-purple-400', 'text-purple-800'],
        'autorizados': [btns[6], 'bg-emerald-100', 'border-emerald-400', 'text-emerald-800']
    };

    const activeClasses = [
        'bg-green-100', 'border-green-400', 'text-green-800',
        'bg-blue-100', 'border-blue-400', 'text-blue-800',
        'bg-yellow-100', 'border-yellow-400', 'text-yellow-800',
        'bg-purple-100', 'border-purple-400', 'text-purple-800',
        'bg-amber-100', 'border-amber-400', 'text-amber-800',
        'bg-teal-100', 'border-teal-400', 'text-teal-800',
        'bg-emerald-100', 'border-emerald-400', 'text-emerald-800'
    ];

    btns.forEach(btn => {
        if (btn) {
            btn.classList.remove(...activeClasses);
            btn.classList.add('bg-gray-50', 'border-gray-300', 'text-gray-700');
        }
    });

    const filtros = filterState.filtros || [];
    // Ver Todos activo cuando no hay ningún otro filtro
    if (filtros.length === 0 && keyToBtn['todos']) {
        const [btn, ...classes] = keyToBtn['todos'];
        if (btn) {
            btn.classList.remove('bg-gray-50', 'border-gray-300', 'text-gray-700');
            btn.classList.add(...classes);
        }
    }
    filtros.forEach(tipo => {
        const entry = keyToBtn[tipo];
        if (entry) {
            const [btn, ...classes] = entry;
            if (btn) {
                btn.classList.remove('bg-gray-50', 'border-gray-300', 'text-gray-700');
                btn.classList.add(...classes);
            }
        }
    });
}

// Ordenar tabla por la columna actual (sortState.column / sortState.dir)
function sortTable() {
    const tbody = document.getElementById('tb-body');
    if (!tbody) return;

    const noResults = tbody.querySelector('tr.no-results');
    if (noResults) noResults.remove();

    const key = getDataKey(sortState.column);
    const isNumeric = SORT_NUMERIC_COLUMNS.includes(sortState.column);

    const rows = Array.from(tbody.querySelectorAll('tr.table-row'));
    rows.sort((a, b) => {
        let va = a.getAttribute(key) ?? '';
        let vb = b.getAttribute(key) ?? '';
        let cmp;
        if (isNumeric) {
            const na = parseFloat(va) || -999999;
            const nb = parseFloat(vb) || -999999;
            cmp = na - nb;
        } else {
            cmp = String(va).localeCompare(String(vb), undefined, { numeric: true });
        }
        return sortState.dir === 'asc' ? cmp : -cmp;
    });

    rows.forEach(tr => tbody.appendChild(tr));
    if (noResults) tbody.appendChild(noResults);
    updateSortIcons();
}

// Clic en cualquier columna ordenable
document.querySelectorAll('.th-sortable').forEach(th => {
    th.addEventListener('click', function(e) {
        e.stopPropagation();
        const col = this.getAttribute('data-sort');
        if (!col) return;
        if (sortState.column === col) {
            sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
        } else {
            sortState.column = col;
            sortState.dir = 'asc';
        }
        sortTable();
    });
});

// Cerrar modal al hacer clic fuera de él
document.getElementById('modalFiltros')?.addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalFiltros();
    }
});

// Abrir modal con el botón
document.getElementById('btn-open-filters')?.addEventListener('click', mostrarModalFiltros);

// Inicializar filtros y orden al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    updateFilterButtons();
    // El backend ya filtró los datos por rol, así que solo aplicar filtros si el usuario los cambió
    // Si filtroAplicado es 'todos', mostrar todos los registros que el backend trajo
    if (filterState.filtros.length > 0) {
        applyFilters();
    }
    // Si no hay filtros, todas las filas ya están visibles (el backend ya filtró por rol)
    updateSortIcons();
});

setInterval(refreshStatus, 5000);

async function refreshStatus() {
    try {
        // Preservar la selección actual antes de actualizar
        const currentSelectedId = selectedRowId;

        // Obtener todos los registros sin filtro
        const url = '{{ route("atadores.programa") }}';

        const response = await fetch(url);
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        document.querySelectorAll('tbody tr[data-id]').forEach(row => {
            const id = row.getAttribute('data-id');
            const newRow = doc.querySelector(`tr[data-id="${id}"]`);
            if (newRow) {
                // Actualizar status
                const currentStatusCell = row.querySelector('td[data-status]');
                const newStatusCell = newRow.querySelector('td[data-status]');
                if (currentStatusCell && newStatusCell &&
                    currentStatusCell.getAttribute('data-status') !== newStatusCell.getAttribute('data-status')) {
                    currentStatusCell.innerHTML = newStatusCell.innerHTML;
                    currentStatusCell.setAttribute('data-status', newStatusCell.getAttribute('data-status'));

                    // Actualizar el atributo data-status y data-estatus de la fila
                    const newStatus = newStatusCell.getAttribute('data-status');
                    row.setAttribute('data-status', newStatus || 'Activo');
                    row.setAttribute('data-estatus', newStatus || 'Activo');
                }

                // Actualizar telar si cambió
                const newTelar = newRow.getAttribute('data-telar');
                if (newTelar) {
                    row.setAttribute('data-telar', newTelar);
                }
            }
        });

        // Reaplicar filtros y orden después de actualizar
        applyFilters();
        sortTable();

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
    const horaParo = row.getAttribute('data-hora-paro') || '';
    if (!horaParo.trim()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'El telar debe registrar la hora de paro antes de iniciar el atado'
        });
        disableIniciarButton();
        return;
    }

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
    const horaParo = selectedRowElement.getAttribute('data-hora-paro') || '';
    if (!horaParo.trim()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Este telar aún no registra la hora de paro. Detén el telar antes de iniciar el atado'
        });
        return;
    }

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
