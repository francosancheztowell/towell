@extends('layouts.app')

@section('page-title', 'Reservar y Programar Urd/Eng')

@section('navbar-right')
<div class="flex items-center gap-2">
    <button type="button" id="btnResetFilters"
            class="px-3 py-2 text-yellow-500 hover:text-yellow-600 rounded-lg transition-colors flex items-center justify-center"
            title="Restablecer filtros">
        <i class="fa-solid fa-rotate w-4 h-4"></i>
	</button>
    <button type="button" id="btnOpenFilters"
            class="relative px-3 py-2 text-blue-500 hover:text-blue-600 rounded-lg transition-colors flex items-center justify-center"
            title="Aplicar filtros">
        <i class="fa-solid fa-filter w-4 h-4"></i>
        <span id="filterCount" class="hidden absolute -right-1 -top-1 inline-flex items-center justify-center w-4 h-4 text-[10px] font-semibold rounded-full bg-rose-600 text-white">0</span>
	</button>
</div>
@endsection

@section('content')
<div class="w-full">

    {{-- ========== Tabla: Programación de requerimientos ========== --}}
    <div class="bg-white overflow-hidden w-full">
        <div class="relative w-full">
            <div id="loaderTelares" class="hidden absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500"></div>
        </div>

            @if(($inventarioTelares ?? collect())->count() > 0)
                <div class="overflow-x-auto w-full" style="width: 100%;">
                    <div class="overflow-y-auto max-h-[290px] w-full">
                        <table id="telaresTable" class="w-full divide-y divide-gray-200" style="width: 100% !important; table-layout: auto;">
                            <thead class="bg-white text-gray-900 sticky top-0 z-20">
                                <tr>
                                    @php
                                        $headers = [
                                            ['key'=>'no_telar',  'label'=>'No. Telar'],
                                            ['key'=>'tipo',      'label'=>'Tipo'],
                                            ['key'=>'cuenta',    'label'=>'Cuenta'],
                                            ['key'=>'calibre',   'label'=>'Calibre'],
                                            ['key'=>'fecha',     'label'=>'Fecha'],
                                            ['key'=>'turno',     'label'=>'Turno'],
                                            ['key'=>'hilo',      'label'=>'Hilo'],
                                            ['key'=>'metros',    'label'=>'Metros'],
                                            ['key'=>'no_julio',  'label'=>'No. Julio'],
                                            ['key'=>'no_orden',  'label'=>'No. Orden'],
                                            ['key'=>'tipo_atado','label'=>'Tipo Atado'],
                                            ['key'=>'salon',     'label'=>'Salón'],
                                        ];
                                    @endphp
                                    @foreach($headers as $h)
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap sortable"
                                            data-column="{{ $h['key'] }}">
                                            <div class="flex items-center justify-between gap-2 cursor-pointer">
                                                <span>{{ $h['label'] }}</span>
                                                <i class="fa-solid fa-sort text-gray-400 sort-icon"></i>
                                            </div>
                                    </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($inventarioTelares as $i => $t)
                                    @php
                                        $tipo = strtoupper($t['tipo'] ?? '-');
                                        $tipoClass = $tipo === 'RIZO' ? 'bg-rose-100 text-rose-700' :
                                                     ($tipo === 'PIE' ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-700');

                                        $salon = trim($t['salon'] ?? 'Jacquard');
                                        $salonMap = [
                                            'Jacquard'=>'bg-pink-100 text-pink-700','JACQUARD'=>'bg-pink-100 text-pink-700',
                                            'Itema'=>'bg-purple-100 text-purple-700','ITEMA'=>'bg-purple-100 text-purple-700',
                                            'Smith'=>'bg-cyan-100 text-cyan-700','SMITH'=>'bg-cyan-100 text-cyan-700',
                                            'Karl Mayer'=>'bg-amber-100 text-amber-700','KARL MAYER'=>'bg-amber-100 text-amber-700',
                                            'Sulzer'=>'bg-lime-100 text-lime-700','SULZER'=>'bg-lime-100 text-lime-700',
                                        ];
                                        $salonClass = $salonMap[$salon] ?? 'bg-indigo-100 text-indigo-700';
                                        $baseBg     = $i % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                            @endphp
                                    <tr class="selectable-row hover:bg-blue-50 cursor-pointer {{ $baseBg }}"
                                        data-base-bg="{{ $baseBg }}"
                                        data-telar="{{ $t['no_telar'] ?? '' }}">
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap font-bold">{{ $t['no_telar'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $tipoClass }}">{{ $t['tipo'] ?? '-' }}</span>
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">{{ $t['cuenta'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">{{ number_format((float)($t['calibre'] ?? 0), 2) }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $t['fecha'] ? \Carbon\Carbon::parse($t['fecha'])->format('d-M-Y') : '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">{{ $t['turno'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">{{ $t['hilo'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-right">{{ number_format((float)($t['metros'] ?? 0), 2) }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">{{ $t['no_julio'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">{{ $t['no_orden'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">{{ $t['tipo_atado'] ?? 'Normal' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $salonClass }}">{{ $salon }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                    <i class="fa-solid fa-box-open w-12 h-12 text-gray-400 mb-2"></i>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No hay inventario disponible</h3>
                <p class="mt-2 text-sm text-gray-500">No se han registrado telares en el inventario.</p>
                <div class="mt-6">
                        <button id="btnReloadTelares"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <i class="fa-solid fa-rotate w-4 h-4 mr-1"></i> Recargar
                    </button>
                </div>
            </div>
        @endif
        </div>
    </div>

    {{-- ========== Tabla: Inventario Disponible ========== --}}
    <div class="bg-white overflow-hidden mt-2">
        <div class="bg-blue-500 px-4  flex justify-between items-center">
            <h2 class="text-lg font-bold text-white text-center flex-1">Inventario Disponible</h2>
        </div>

        <div class="relative">
            <div id="loaderInventario" class="hidden absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500"></div>
            </div>

            <div class="overflow-x-auto w-full">
                <div class="overflow-y-auto max-h-[220px] w-full">
                    <table id="inventarioTable" class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100 text-gray-900 sticky top-0 z-20">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Artículo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Tipo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Cantidad</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Config</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Talla</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Color</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Almacén</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Lote</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Localidad</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Serial</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Metros</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Fecha</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Telar</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr>
                                <td colspan="13" class="px-4 py-8 text-center text-sm text-gray-500">
                                    <i class="fa-solid fa-box-open w-12 h-12 text-gray-400 mb-2"></i>
                                    No hay datos de inventario disponible por el momento
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Estilos ========== --}}
<style>
    /* Asegurar que la primera tabla ocupe todo el ancho de la pantalla */
    #telaresTable {
        width: 100% !important;
        min-width: 100%;
        table-layout: auto;
    }

    /* Contenedor principal sin padding superior */
    main > div:first-child {
        padding-top: 0 !important;
        margin-top: 0 !important;
    }

    /* Contenedor de la primera tabla - ancho completo */
    .bg-white.overflow-hidden.w-full:first-of-type {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Asegurar que el overflow-x funcione correctamente */
    .overflow-x-auto {
        width: 100% !important;
        max-width: 100vw !important;
    }
</style>

{{-- ========== Modal Filtros ========== --}}
<div id="filterModal" class="fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-3xl shadow-lg rounded-lg bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Filtrar Tablas</h3>
                <button id="btnCloseFilters" class="text-gray-400 hover:text-gray-600" title="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div id="filterFormContainer" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tabla a filtrar:</label>
                    <select id="tableSelector" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="telares" selected>Programación de Requerimientos</option>
                        <option value="inventario">Inventario Disponible</option>
                    </select>
                </div>

                <div id="filtersInputContainer"></div>

                <button type="button" id="btnAddFilter"
                        class="w-full px-4 py-2 border-2 border-dashed border-gray-300 text-gray-600 rounded-lg hover:border-purple-500 hover:text-purple-600 transition-colors text-sm font-medium">
                    Agregar otro filtro
                </button>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" id="btnCancelFilters"
                        class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    Cancelar
                </button>
                <button type="button" id="btnApplyFilters"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    Aplicar Filtros
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ========== Scripts ========== --}}
<script>
/* -------------------- Config -------------------- */
const API = {
    inventarioTelares: '{{ route("programa.urd.eng.inventario.telares") }}',
    inventarioDisponible: '{{ route("programa.urd.eng.inventario.disponible") }}',
    inventarioDisponibleGet: '{{ route("programa.urd.eng.inventario.disponible.get") }}',
    programarTelar: '{{ route("programa.urd.eng.programar.telar") }}',
    reservarInventario: '{{ route("programa.urd.eng.reservar.inventario") }}',
    columnOptions: '{{ route("programa.urd.eng.column.options") }}'
};
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* -------------------- Estado -------------------- */
const state = {
    filters: [],
    selectedTelar: null,
    columns: { telares: [], inventario: [] },
    sort: { column: null, direction: 'asc' },
    telaresData: @json($inventarioTelares ?? []),
    inventarioData: [],
};

/* -------------------- Utils -------------------- */
const $  = (s, c=document) => c.querySelector(s);
const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));
const show = el => el?.classList.remove('hidden');
const hide = el => el?.classList.add('hidden');
const disable = (el, v=true)=> el && (el.disabled = v);

const api = {
    async request(url, opt={}) {
        const res = await fetch(url, {
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' },
            ...opt
        });
        const json = await res.json().catch(()=>({success:false}));
        if (!res.ok || json.success === false) throw new Error(json.message || res.statusText);
        return json;
    },
    get: (u)=>api.request(u,{method:'GET'}),
    post:(u,b)=>api.request(u,{method:'POST', body: JSON.stringify(b||{})}),
};

const fmt = {
    salonBadge(s) {
        const map = {
            'Jacquard':'bg-pink-100 text-pink-700','JACQUARD':'bg-pink-100 text-pink-700',
            'Itema':'bg-purple-100 text-purple-700','ITEMA':'bg-purple-100 text-purple-700',
            'Smith':'bg-cyan-100 text-cyan-700','SMITH':'bg-cyan-100 text-cyan-700',
            'Karl Mayer':'bg-amber-100 text-amber-700','KARL MAYER':'bg-amber-100 text-amber-700',
            'Sulzer':'bg-lime-100 text-lime-700','SULZER':'bg-lime-100 text-lime-700',
        };
        return map[String(s||'Jacquard').trim()] || 'bg-indigo-100 text-indigo-700';
    },
    tipoBadge(t) {
        const u = String(t||'-').toUpperCase().trim();
        return u==='RIZO' ? 'bg-rose-100 text-rose-700' : u==='PIE' ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-700';
    },
    num(n, d=2){ return (n==null || n==='') ? '' : Number(n).toFixed(d); },
    date(iso){ if(!iso) return ''; const d=new Date(iso); return isNaN(d) ? '' : d.toLocaleDateString('es-ES',{day:'2-digit',month:'short',year:'numeric'}); }
};

/* -------------------- Render -------------------- */
const render = {
    telares(rows) {
        const tbody = $('#telaresTable tbody'); if(!tbody) return;
        tbody.innerHTML = '';

        if (!rows?.length) {
            tbody.innerHTML = `<tr><td colspan="12" class="px-4 py-8 text-center text-sm text-gray-500">No hay datos disponibles</td></tr>`;
            state.selectedTelar = null; disable($('#btnProgramar'), true);
            this.updateSortIcons(); return;
        }

        // recordar datos (para ordenación re-render sin perder dataset)
        state.telaresData = rows.map((r,i)=>({...r,_index:r._index ?? i}));

        // ordenar si procede
        const data = state.sort.column ? this.sorted(state.telaresData, state.sort.column, state.sort.direction) : state.telaresData;

        const frag = document.createDocumentFragment();
        data.forEach((r, i) => {
            const baseBg = i % 2 === 0 ? 'bg-white' : 'bg-gray-50';
            const tr = document.createElement('tr');
            tr.className = `selectable-row hover:bg-blue-50 cursor-pointer ${baseBg}`;
            tr.dataset.baseBg = baseBg;
            tr.dataset.telar  = r.no_telar ?? '';

            tr.innerHTML = `
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap font-bold">${r.no_telar ?? ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.tipo)}">${r.tipo || '-'}</span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.cuenta || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${fmt.num(r.calibre)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${fmt.date(r.fecha)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.turno || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.hilo || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-right">${fmt.num(r.metros)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.no_julio || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.no_orden || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.tipo_atado || 'Normal'}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.salonBadge(r.salon)}">${r.salon || 'Jacquard'}</span>
                </td>
            `;
            frag.appendChild(tr);
        });
        tbody.appendChild(frag);
        this.updateSortIcons();
    },

    sorted(data, col, dir) {
        return [...data].sort((a,b)=>{
            const av=a[col], bv=b[col];
            const emptyA = (av==null || av===''), emptyB = (bv==null || bv==='');
            if (emptyA && emptyB) return a._index - b._index;
            if (emptyA) return 1; if (emptyB) return -1;

            let cmp = 0;
            if (col==='fecha') { cmp = (new Date(av)-new Date(bv)); }
            else if (['no_telar','no_julio','no_orden'].includes(col)) { cmp = (parseInt(av)||0) - (parseInt(bv)||0); }
            else if (['calibre','metros'].includes(col)) { cmp = (parseFloat(av)||0) - (parseFloat(bv)||0); }
            else { const as=String(av).toLowerCase(), bs=String(bv).toLowerCase(); cmp = as<bs?-1:as>bs?1:0; }
            if (cmp===0) cmp = a._index - b._index;
            return dir==='asc' ? cmp : -cmp;
        });
    },

    updateSortIcons() {
        $$('#telaresTable .sortable .sort-icon').forEach(i => i.className = 'fa-solid fa-sort text-gray-400 sort-icon');
        if (!state.sort.column) return;
        const th = $(`#telaresTable .sortable[data-column="${state.sort.column}"]`);
        if (!th) return;
        const icon = th.querySelector('.sort-icon');
        if (!icon) return;
        icon.className = state.sort.direction==='asc'
            ? 'fa-solid fa-sort-up text-blue-600 sort-icon'
            : 'fa-solid fa-sort-down text-blue-600 sort-icon';
    },

    inventario(rows) {
        const tbody = $('#inventarioTable tbody'); if(!tbody) return;
        tbody.innerHTML = '';

        // Guardar datos para recargar después de reservar
        state.inventarioData = rows || [];

        if (!rows?.length) {
            tbody.innerHTML = `<tr><td colspan="13" class="px-4 py-8 text-center text-sm text-gray-500">
                <i class="fa-solid fa-box-open w-12 h-12 text-gray-400 mb-2"></i> No hay datos de inventario disponible por el momento
            </td></tr>`;
        }
        const frag = document.createDocumentFragment();
        rows.forEach(r=>{
            const tr = document.createElement('tr');
            tr.className='hover:bg-orange-50';
            tr.dataset.itemId = r.ItemId || '';
            tr.dataset.configId = r.ConfigId || '';
            tr.dataset.inventSizeId = r.InventSizeId || '';
            tr.dataset.inventColorId = r.InventColorId || '';
            tr.dataset.inventLocationId = r.InventLocationId || '';
            tr.dataset.inventBatchId = r.InventBatchId || '';
            tr.dataset.wmsLocationId = r.WMSLocationId || '';
            tr.dataset.inventSerialId = r.InventSerialId || '';
            tr.dataset.noTelarId = r.NoTelarId || '';
            tr.innerHTML = `
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.ItemId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.Tipo)}">${r.Tipo||''}</span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-right">${fmt.num(r.InventQty, 2)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.ConfigId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.InventSizeId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.InventColorId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.InventLocationId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.InventBatchId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.WMSLocationId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${r.InventSerialId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-right">${fmt.num(r.Metros, 2)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap">${fmt.date(r.ProdDate)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap font-medium">${r.NoTelarId || ''}</td>`;
            frag.appendChild(tr);
        });
        tbody.appendChild(frag);
    }
};

/* -------------------- Selección (solo Tailwind) -------------------- */
const selection = {
    clear() {
        const prev = $('#telaresTable .selectable-row.is-selected');
        if (!prev) return;
        prev.classList.remove('is-selected','bg-blue-500','text-white');
        // Limpiar estilos inline
        prev.style.removeProperty('background-color');
        prev.style.removeProperty('color');
        const base = prev.dataset.baseBg || 'bg-white';
        // quitar cualquier bg-* previa y aplicar base
        prev.className = prev.className
            .split(' ')
            .filter(c => !c.startsWith('bg-') || c==='bg-blue-50') // conservamos hover:bg-blue-50
            .join(' ');
        prev.classList.add(base, 'hover:bg-blue-50', 'cursor-pointer');
        prev.querySelectorAll('td').forEach(td => {
            td.classList.remove('text-white');
            td.style.removeProperty('color');
        });
        // Restaurar badges
        prev.querySelectorAll('.badge').forEach(badge => {
            badge.classList.remove('text-white', 'bg-white/20');
            badge.style.removeProperty('color');
            badge.style.removeProperty('background-color');
        });
        state.selectedTelar = null;
        disable($('#btnProgramar'), true);
        disable($('#btnReservar'), true);
    },
    apply(row) {
        if (!row) return;
        this.clear();
        // Guardar baseBg si no existe
        if (!row.dataset.baseBg) {
            const base = row.className.match(/bg-(white|gray-50)/)?.[0] || 'bg-white';
            row.dataset.baseBg = base;
        }
        // quitar bg-* base y poner selección
        row.className = row.className
            .split(' ')
            .filter(c => !c.startsWith('bg-') || c==='bg-blue-50')
            .join(' ');
        row.classList.add('bg-blue-500','text-white','is-selected','cursor-pointer');
        // Forzar estilos inline para asegurar que se apliquen
        row.style.setProperty('background-color', '#3b82f6', 'important');
        row.style.setProperty('color', '#ffffff', 'important');
        row.querySelectorAll('td').forEach(td => {
            td.classList.add('text-white');
            td.style.setProperty('color', '#ffffff', 'important');
        });
        // Actualizar badges
        row.querySelectorAll('.badge').forEach(badge => {
            badge.classList.add('text-white', 'bg-white/20');
            badge.style.setProperty('color', '#ffffff', 'important');
            badge.style.setProperty('background-color', 'rgba(255,255,255,0.2)', 'important');
        });
        state.selectedTelar = { no_telar: row.dataset.telar || null };
        disable($('#btnProgramar'), !state.selectedTelar.no_telar);
        disable($('#btnReservar'), !state.selectedTelar.no_telar || !state.inventarioData.length);
    },
    toggle(row) {
        if (row.classList.contains('is-selected')) { this.clear(); return; }
        this.apply(row);
    }
};

/* -------------------- Filtros -------------------- */
const filters = {
    async addRow() {
        const type = $('#tableSelector').value;
        if (!state.columns[type]?.length) {
            const data = await api.get(`${API.columnOptions}?table_type=${type}`);
            state.columns[type] = data.columns || [];
        }
        const wrap = document.createElement('div');
        wrap.className = 'filter-row p-4 border border-gray-200 rounded-lg bg-gray-50';
        const idx = Date.now();
        wrap.dataset.filterIndex = idx;
        wrap.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Columna</label>
                <select class="filter-column w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Selecciona una columna...</option>
                ${state.columns[type].map(c=>`<option value="${c.field}">${c.label}</option>`).join('')}
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Valor</label>
                <input type="text" class="filter-value w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Escribe el valor...">
            </div>
            <div class="pt-6">
              <button type="button" class="btn-remove-filter px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                <i class="fa-solid fa-times w-4 h-4"></i>
                </button>
            </div>
          </div>`;
        wrap.querySelector('.btn-remove-filter').addEventListener('click', ()=>{
            wrap.remove();
            state.filters = state.filters.filter(f=>f.index!==idx);
            filters.updateBadge();
        });
        $('#filtersInputContainer').appendChild(wrap);
        state.filters.push({ table:type, column:'', value:'', index:idx });
        this.updateBadge();
    },

    updateBadge() {
        const badge = $('#filterCount'); if(!badge) return;
        const n = $$('.filter-row').length;
        badge.textContent = n; badge.classList.toggle('hidden', n===0);
    },

    async apply() {
        const type = $('#tableSelector').value;
        const list = $$('.filter-row').map(r=>{
            const c = r.querySelector('.filter-column')?.value?.trim() || '';
            const v = r.querySelector('.filter-value')?.value?.trim() || '';
            return c && v ? { columna:c, valor:v } : null;
        }).filter(Boolean);

        if (!list.length) { Swal.fire('Aviso','Agrega al menos un filtro','warning'); return; }

        const loader = type==='telares' ? '#loaderTelares' : '#loaderInventario';
        show($(loader));
        try {
            const url = type==='telares' ? API.inventarioTelares : API.inventarioDisponible;
            const { data } = await api.post(url, { filtros:list });
            if (type==='telares') {
                render.telares(data);
            } else {
                render.inventario(data);
            }
            // Guardar filtros aplicados
            state.filters = list.map((f, i) => ({ table: type, column: f.columna, value: f.valor, index: i }));
            filters.updateBadge();
            $('#filterModal').classList.add('hidden');
            Swal.fire({toast:true,position:'top-end',icon:'success',title:`${list.length} filtro(s) aplicados`,showConfirmButton:false,timer:2000});
        } catch(e) {
            Swal.fire('Error', e.message||'Error al aplicar filtros','error');
        } finally {
            hide($(loader));
        }
    },

    async reset() {
        show($('#loaderTelares'));
        try {
            const { data } = await api.post(API.inventarioTelares, { filtros: [] });
            render.telares(data);
            $('#filtersInputContainer').innerHTML = '';
            this.updateBadge();
            Swal.fire({toast:true,position:'top-end',icon:'success',title:'Filtros restablecidos',showConfirmButton:false,timer:1500});
        } catch(e) {
            Swal.fire('Error', e.message||'Error al restablecer filtros','error');
        } finally { hide($('#loaderTelares')); }
    }
};

/* -------------------- Sorting -------------------- */
const sorting = {
    toggle(col){
        if (state.sort.column === col) {
            state.sort.direction = state.sort.direction==='asc' ? 'desc' : 'asc';
    } else {
            state.sort.column = col; state.sort.direction='asc';
        }
        render.telares(state.telaresData);
    },
    bind(){
        // Event delegation para evitar que un icono afecte otra columna
        $('#telaresTable thead')?.addEventListener('click', (e)=>{
            const th = e.target.closest('.sortable');
            if (!th) return;
            const col = th.dataset.column; if (!col) return;
            sorting.toggle(col);
        });
    }
};

/* -------------------- Acciones -------------------- */
const actions = {
    async programar(){
        if (!state.selectedTelar?.no_telar) { Swal.fire('Aviso','Selecciona un telar','warning'); return; }
        const ok = await Swal.fire({
            title:'¿Programar telar?',
            text:`¿Deseas programar el telar ${state.selectedTelar.no_telar}?`,
            icon:'question', showCancelButton:true, confirmButtonText:'Sí, programar', cancelButtonText:'Cancelar'
        }).then(r=>r.isConfirmed);
        if (!ok) return;
        try {
            const { message } = await api.post(API.programarTelar,{ no_telar: state.selectedTelar.no_telar });
            selection.clear(); Swal.fire({toast:true,position:'top-end',icon:'success',title:message||'Telar programado',showConfirmButton:false,timer:1500});
        } catch(e){ Swal.fire('Error', e.message||'Error al programar','error'); }
    },
    async reservar(){
        if (!state.selectedTelar?.no_telar) {
            Swal.fire('Aviso','Selecciona un telar primero','warning');
            return;
        }

        // Obtener todas las piezas disponibles (sin telar asignado) de la tabla de inventario
        const piezasDisponibles = state.inventarioData.filter(item => !item.NoTelarId || item.NoTelarId === '');

        if (piezasDisponibles.length === 0) {
            Swal.fire('Info','No hay piezas disponibles para reservar (todas ya están reservadas)','info');
            return;
        }

        const ok = await Swal.fire({
            title:'¿Reservar piezas?',
            text:`¿Deseas reservar ${piezasDisponibles.length} pieza(s) disponible(s) para el telar ${state.selectedTelar.no_telar}?`,
            icon:'question',
            showCancelButton:true,
            confirmButtonText:'Sí, reservar',
            cancelButtonText:'Cancelar'
        }).then(r=>r.isConfirmed);

        if (!ok) return;

        // Reservar todas las piezas disponibles
        let successCount = 0;
        let errorCount = 0;
        const errors = [];

        show($('#loaderInventario'));
        try {
            for (const item of piezasDisponibles) {
                try {
                    const payload = {
                        NoTelarId: state.selectedTelar.no_telar,
                        ItemId: item.ItemId,
                        ConfigId: item.ConfigId || null,
                        InventSizeId: item.InventSizeId || null,
                        InventColorId: item.InventColorId || null,
                        InventLocationId: item.InventLocationId || null,
                        InventBatchId: item.InventBatchId || null,
                        WMSLocationId: item.WMSLocationId || null,
                        InventSerialId: item.InventSerialId || null,
                        Tipo: item.Tipo || null,
                        Metros: item.Metros || null,
                        InventQty: item.InventQty || null,
                        ProdDate: item.ProdDate || null,
                    };
                    const { success } = await api.post(API.reservarInventario, payload);
                    if (success) successCount++;
                } catch(e) {
                    errorCount++;
                    errors.push(item.ItemId || 'Desconocido');
                    console.error('Error reservando pieza:', e);
                }
            }

            const message = successCount > 0
                ? `${successCount} pieza(s) reservada(s)${errorCount > 0 ? `. ${errorCount} error(es)` : ''}`
                : `Error: ${errorCount} pieza(s) no pudieron ser reservadas`;

            Swal.fire({
                toast:true,
                position:'top-end',
                icon: successCount > 0 ? 'success' : 'error',
                title: message,
                showConfirmButton:false,
                timer:3000
            });

            // Recargar inventario para mostrar los telares asignados
            const inventarioFilters = state.filters.filter(f=>f.table==='inventario').map(f=>({columna:f.column, valor:f.value}));
            if (inventarioFilters.length > 0) {
                const { data } = await api.post(API.inventarioDisponible, { filtros: inventarioFilters });
                render.inventario(data);
            } else {
                const { data } = await api.get(API.inventarioDisponibleGet);
                render.inventario(data);
            }
        } finally {
            hide($('#loaderInventario'));
        }
    }
};

/* -------------------- Init -------------------- */
document.addEventListener('DOMContentLoaded', ()=>{
    // datos ya vienen normalizados del servidor en state.telaresData
    sorting.bind();

    // selección de filas (delegation) - evitar que botones/links interfieran
    $('#telaresTable tbody')?.addEventListener('click', (e)=>{
        // Si el click es en un botón o link, no hacer nada
        if (e.target.closest('button') || e.target.closest('a')) return;
        const row = e.target.closest('.selectable-row');
        if (row) {
            e.preventDefault();
            e.stopPropagation();
            selection.toggle(row);
        }
    });

    // botones top bar
    $('#btnOpenFilters')?.addEventListener('click', async ()=>{
        if (!state.columns.telares.length) { const d=await api.get(`${API.columnOptions}?table_type=telares`); state.columns.telares = d.columns || []; }
        if (!state.columns.inventario.length) { const d=await api.get(`${API.columnOptions}?table_type=inventario`); state.columns.inventario = d.columns || []; }
        if (!$('#filtersInputContainer').children.length) await filters.addRow();
        show($('#filterModal'));
    });
    $('#btnResetFilters')?.addEventListener('click', filters.reset);
    $('#btnAddFilter')?.addEventListener('click', filters.addRow);
    $('#btnApplyFilters')?.addEventListener('click', filters.apply);
    $('#btnCancelFilters')?.addEventListener('click', ()=>hide($('#filterModal')));
    $('#btnCloseFilters')?.addEventListener('click', ()=>hide($('#filterModal')));
    $('#filterModal')?.addEventListener('click', (e)=>{ if(e.target===e.currentTarget) hide($('#filterModal')); });

    // acciones
    $('#btnProgramar')?.addEventListener('click', actions.programar);
    $('#btnReservar')?.addEventListener('click', actions.reservar);
    $('#btnReloadTelares')?.addEventListener('click', filters.reset);

    // primer render (por si el usuario reordena de inmediato)
    render.telares(state.telaresData);

    // Cargar inventario disponible al inicio
    (async ()=>{
        show($('#loaderInventario'));
        try {
            const { data } = await api.get(API.inventarioDisponibleGet);
            render.inventario(data || []);
            // Habilitar/deshabilitar botón según haya telar seleccionado
            disable($('#btnReservar'), !state.selectedTelar?.no_telar);
        } catch(e) {
            console.error('Error cargando inventario:', e);
            render.inventario([]);
            disable($('#btnReservar'), true);
        } finally {
            hide($('#loaderInventario'));
        }
    })();
});
</script>
@endsection
