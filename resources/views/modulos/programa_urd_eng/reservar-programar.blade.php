@extends('layouts.app')

@section('page-title', 'Reservar y Programar Urd/Eng')

@section('navbar-right')
<div class="flex items-center gap-2">
    <button id="btnProgramar" type="button"
      class="px-3 py-1 text-green-500 hover:text-green-600 rounded-lg transition-colors flex items-center justify-center disabled:text-gray-400 disabled:cursor-not-allowed"
      title="Programar" disabled>
      <i class="fa-solid fa-calendar-check w-5 h-5"></i>
    </button>

    <button id="btnLiberarTelar" type="button"
      class="px-3 py-2 text-red-500 hover:text-red-600 rounded-lg transition-colors flex items-center justify-center disabled:text-gray-400 disabled:cursor-not-allowed"
      title="Liberar telar" disabled>
      <i class="fa-solid fa-unlock w-5 h-5"></i>
    </button>

    <button id="btnReservar" type="button"
      class="px-3 py-1 text-yellow-500 hover:text-yellow-600 rounded-lg transition-colors flex items-center justify-center disabled:text-gray-400 disabled:cursor-not-allowed"
      title="Reservar" disabled>
      <i class="fa-solid fa-save w-5 h-5"></i>
    </button>

    <button id="btnResetFiltros" type="button"
      class="px-3 py-2 text-gray-500 hover:text-gray-600 rounded-lg transition-colors flex items-center justify-center"
      title="Restablecer filtros">
      <i class="fa-solid fa-arrows-rotate w-5 h-5"></i>
    </button>

    <button id="btnOpenFilters" type="button"
      class="relative px-3 py-2 text-blue-500 hover:text-blue-600 rounded-lg transition-colors flex items-center justify-center"
      title="Aplicar filtros">
      <i class="fa-solid fa-filter w-5 h-5"></i>
      <span id="filterCount"
        class="hidden absolute -right-1 -top-1 inline-flex items-center justify-center w-4 h-4 text-[10px] font-semibold rounded-full bg-rose-600 text-white">0</span>
    </button>
  </div>

@endsection

@section('content')
<div class="w-full">

    {{-- =================== Tabla: Programación (telares) =================== --}}
    <div class="bg-white overflow-hidden w-full">
        <div class="relative w-full">
            <div id="loaderTelares"
                 class="hidden absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500"></div>
        </div>

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

            @if(($inventarioTelares ?? collect())->count())
                <div class="overflow-x-auto w-full">
                    <div class="overflow-y-auto max-h-[290px] w-full">
                        <table id="telaresTable" class="w-full table-auto divide-y divide-gray-200">
                            <thead class="bg-white text-gray-900 sticky top-0 z-20">
                                <tr>
                                    @foreach($headers as $h)
                                        <th class="px-3 py-2 text-center text-xs font-medium tracking-wider whitespace-nowrap sortable"
                                            data-column="{{ $h['key'] }}">
                                            <button type="button" class="w-full flex items-center justify-center gap-2 cursor-pointer">
                                                <span>{{ $h['label'] }}</span>
                                                <i class="fa-solid fa-sort text-gray-400 sort-icon"></i>
                                            </button>
                                    </th>
                                    @endforeach
                                    <th class="px-3 py-2 text-center text-xs font-medium tracking-wider whitespace-nowrap">Seleccionar</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($inventarioTelares as $i => $t)
                                    @php
                                        $tipo     = strtoupper($t['tipo'] ?? '-');
                                        $tipoCls  = $tipo === 'RIZO' ? 'bg-rose-100 text-rose-700' : ($tipo === 'PIE' ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-700');
                                        $salon    = trim($t['salon'] ?? 'Jacquard');
                                        $salonMap = [
                                            'Jacquard'=>'bg-pink-100 text-pink-700','JACQUARD'=>'bg-pink-100 text-pink-700',
                                            'Itema'=>'bg-purple-100 text-purple-700','ITEMA'=>'bg-purple-100 text-purple-700',
                                            'Smith'=>'bg-cyan-100 text-cyan-700','SMITH'=>'bg-cyan-100 text-cyan-700',
                                            'Karl Mayer'=>'bg-amber-100 text-amber-700','KARL MAYER'=>'bg-amber-100 text-amber-700',
                                            'Sulzer'=>'bg-lime-100 text-lime-700','SULZER'=>'bg-lime-100 text-lime-700',
                                        ];
                                        $salonCls     = $salonMap[$salon] ?? 'bg-indigo-100 text-indigo-700';
                                        $baseBg       = $i % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                        $metrosFloat  = isset($t['metros']) ? (float)$t['metros'] : 0;
                                        $noJulioTrim  = trim($t['no_julio'] ?? '');
                                        $noOrdenTrim  = trim($t['no_orden'] ?? '');
                                        $hasBoth      = $metrosFloat > 0 && $noJulioTrim !== '';
                                        $isReservado  = $noJulioTrim !== '' && $noOrdenTrim !== '';
                                        $finalBg      = $hasBoth ? 'bg-blue-100' : $baseBg;
                                        $blueBorder   = $hasBoth ? 'border-l-4 border-blue-400' : '';
                                            @endphp
                                    <tr class="selectable-row hover:bg-blue-50 cursor-pointer {{ $finalBg }} {{ $blueBorder }}"
                                        data-base-bg="{{ $baseBg }}"
                                        data-telar="{{ $t['no_telar'] ?? '' }}"
                                        data-tipo="{{ strtoupper(trim($t['tipo'] ?? '')) }}"
                                        data-cuenta="{{ $t['cuenta'] ?? '' }}"
                                        data-calibre="{{ $t['calibre'] ?? '' }}"
                                        data-hilo="{{ trim($t['hilo'] ?? '') }}"
                                        data-salon="{{ $salon }}"
                                        data-no-julio="{{ $noJulioTrim }}"
                                        data-no-orden="{{ $noOrdenTrim }}"
                                        data-metros="{{ $t['metros'] ?? '' }}"
                                        data-has-both="{{ $hasBoth ? 'true' : 'false' }}"
                                        data-is-reservado="{{ $isReservado ? 'true' : 'false' }}">
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center font-bold">{{ $t['no_telar'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $tipoCls }}">{{ $t['tipo'] ?? '-' }}</span>
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">{{ $t['cuenta'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">{{ number_format((float)($t['calibre'] ?? 0), 2) }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            {{ !empty($t['fecha']) ? \Carbon\Carbon::parse($t['fecha'])->format('d-M-Y') : '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">{{ $t['turno'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">{{ $t['hilo'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">{{ number_format((float)($t['metros'] ?? 0), 0) }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">{{ $t['no_julio'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">{{ $t['no_orden'] ?? '' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">{{ $t['tipo_atado'] ?? 'Normal' }}</td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $salonCls }}">{{ $salon }}</span>
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            <input type="checkbox"
                                                   class="telar-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 {{ $isReservado ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}"
                                                   data-telar="{{ $t['no_telar'] ?? '' }}"
                                                   data-tipo="{{ strtoupper(trim($t['tipo'] ?? '')) }}"
                                                   {{ $isReservado ? 'disabled' : '' }}
                                                   title="{{ $isReservado ? 'Telar reservado - no se puede seleccionar' : 'Selección múltiple (misma cuenta/atributos)' }}">
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

    {{-- =================== Tabla: Inventario disponible =================== --}}
    <div class="bg-white overflow-hidden mt-2">
        <div class="bg-blue-500 px-4 flex justify-between items-center">
            <h2 class="text-lg font-bold text-white text-center flex-1">Inventario Disponible</h2>
        </div>

        <div class="relative">
            <div id="loaderInventario"
                 class="hidden absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500"></div>
            </div>

            <div class="overflow-x-auto w-full">
                <div class="overflow-y-auto max-h-[220px] w-full">
                    <table id="inventarioTable" class="w-full table-auto divide-y divide-gray-200">
                        <thead class="bg-gray-100 text-gray-900 sticky top-0 z-20">
                            <tr>
                                @foreach(['Artículo','Tipo','Fibra','Cuenta','Cod Color','Lote','Localidad','No. Julio','Fecha','Metros','Kilos','Telar'] as $head)
                                    <th class="px-3 py-2 text-center text-xs font-medium tracking-wider whitespace-nowrap">{{ $head }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr>
                                <td colspan="12" class="px-4 py-8 text-center text-sm text-gray-500">
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

{{-- =================== Estilos mínimos =================== --}}
<style>
    #telaresTable { width:100% !important; min-width:100%; }
    main > div:first-child { padding-top:0 !important; margin-top:0 !important; }
    .bg-white.overflow-hidden.w-full:first-of-type { width:100% !important; max-width:100% !important; margin:0 !important; padding:0 !important; }
    .overflow-x-auto { width:100% !important; max-width:100vw !important; }
    #telaresTable tbody tr.bg-blue-100{ background-color:#dbeafe !important; }
    #telaresTable tbody tr.bg-blue-100.border-l-4{ border-left-color:#60a5fa !important; border-left-width:4px; }
    #telaresTable tbody tr.bg-blue-100:hover{ background-color:#bfdbfe !important; }
</style>

{{-- =================== Scripts =================== --}}
<script>
/* ---------- Constantes ---------- */
const API = {
    inventarioTelares: '{{ route("programa.urd.eng.inventario.telares") }}',
    inventarioDisponible: '{{ route("programa.urd.eng.inventario.disponible") }}',
    inventarioDisponibleGet: '{{ route("programa.urd.eng.inventario.disponible.get") }}',
    programarTelar: '{{ route("programa.urd.eng.programar.telar") }}',
    actualizarTelar: '{{ route("programa.urd.eng.actualizar.telar") }}',
    reservarInventario: '{{ route("programa.urd.eng.reservar.inventario") }}',
    liberarTelar: '{{ route("programa.urd.eng.liberar.telar") }}',
    columnOptions: '{{ route("programa.urd.eng.column.options") }}'
};
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* ---------- Helpers ---------- */
const $  = (s, c=document) => c.querySelector(s);
const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));
const show = el => el?.classList.remove('hidden');
const hide = el => el?.classList.add('hidden');
const disable = function(el, v){
    v = v === undefined ? true : v;
    if (!el) return;
    el.disabled = v === true;
    return el;
};

const http = {
    async request(url, opt={}) {
        const res = await fetch(url, { headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, ...opt });
        const json = await res.json().catch(()=>({success:false}));
        if (!res.ok || json.success === false) throw new Error(json.message || res.statusText);
        return json;
    },
    get:(u)=>http.request(u,{method:'GET'}),
    post:(u,b)=>http.request(u,{method:'POST', body: JSON.stringify(b||{})})
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
    tipoBadge(t){ const u=String(t||'-').toUpperCase().trim(); return u==='RIZO'?'bg-rose-100 text-rose-700':(u==='PIE'?'bg-teal-100 text-teal-700':'bg-gray-100 text-gray-700'); },
    num(n,d=2){ return (n==null||n==='')?'':Number(n).toFixed(d); },
    date(iso){ if(!iso) return ''; const d=new Date(iso); return isNaN(d)?'':d.toLocaleDateString('es-ES',{day:'2-digit',month:'short',year:'numeric'}); }
};

/* Coincidencia de cuenta: telar "3156" => InventSizeId que empiece por "3156" */
const matchCuenta = (cuentaTelar, inventSizeId) => {
    const a = String(cuentaTelar||'').replace(/\s+/g,'').toUpperCase();
    const b = String(inventSizeId||'').replace(/\s+/g,'').toUpperCase();
    if (!a) return false;
    return b.startsWith(a);
};

/* Igualdad para validar grupo en multi-selección */
const eq = {
    str:(a,b)=>String(a||'').trim().toUpperCase() === String(b||'').trim().toUpperCase(),
    num:(a,b)=>{ const x=Number(a), y=Number(b); if (Number.isNaN(x)||Number.isNaN(y)) return String(a)===String(b); return Math.abs(x-y) < 1e-6; }
};
const sameGroup = (a,b) => eq.str(a.tipo,b.tipo) && eq.num(a.calibre,b.calibre) && eq.str(a.hilo,b.hilo) && eq.str(a.salon,b.salon);

/* ---------- Estado ---------- */
const state = {
    filters: { telares: [], inventario: [] },
    selectedTelar: null,          // selección individual (click fila)
    selectedTelares: [],          // selección múltiple (checkboxes)
    selectedInventario: null,
    columns: { telares: [], inventario: [] },
    sort: { column: 'no_telar', direction: 'asc' },
    telaresData: @json($inventarioTelares ?? []),
    telaresDataOriginal: @json($inventarioTelares ?? []),
    inventarioData: [],
    inventarioDataOriginal: [],
};

/* ---------- Render ---------- */
const render = {
    sorted(data, col, dir) {
        return [...data].sort((a,b)=>{
            const av=a[col], bv=b[col];
            const emptyA=(av==null||av===''), emptyB=(bv==null||bv==='');
            if (emptyA && emptyB) return 0;
            if (emptyA) return 1; if (emptyB) return -1;

            let cmp=0;
            if (col==='fecha') cmp=(new Date(av)-new Date(bv));
            else if (['no_telar','no_julio','no_orden'].includes(col)) cmp=(parseInt(av)||0)-(parseInt(bv)||0);
            else if (['calibre','metros'].includes(col)) cmp=(parseFloat(av)||0)-(parseFloat(bv)||0);
            else { const as=String(av).toLowerCase(), bs=String(bv).toLowerCase(); cmp = as<bs?-1:as>bs?1:0; }
            return dir==='asc'?cmp:-cmp;
        });
    },
    updateSortIcons(){
        $$('#telaresTable .sortable .sort-icon').forEach(i=>i.className='fa-solid fa-sort text-gray-400 sort-icon');
        if (!state.sort.column) return;
        const th = $(`#telaresTable .sortable[data-column="${state.sort.column}"]`);
        if (!th) return;
        th.querySelector('.sort-icon').className = state.sort.direction==='asc'
            ? 'fa-solid fa-sort-up text-blue-600 sort-icon'
            : 'fa-solid fa-sort-down text-blue-600 sort-icon';
    },
    telares(rows){
        const tbody = $('#telaresTable tbody'); if(!tbody) return;
        tbody.innerHTML='';

        if (!rows?.length){
            tbody.innerHTML = `<tr><td colspan="13" class="px-4 py-8 text-center text-sm text-gray-500">No hay datos disponibles</td></tr>`;
            state.selectedTelar=null; disable($('#btnProgramar'),true); this.updateSortIcons(); return;
        }

        state.telaresData = rows.map((r,i)=>({...r,_i:i}));

        const isRes = r => (parseFloat(r.metros||0) > 0) && String(r.no_julio||'').trim() !== '';
        const notRes = rows.filter(r=>!isRes(r));
        const yesRes = rows.filter(isRes);
        const base = state.sort.column ? this.sorted(notRes, state.sort.column, state.sort.direction) : notRes;
        const data = [...base, ...yesRes];

        const frag = document.createDocumentFragment();
        data.forEach((r, idx) => {
            const metrosF = parseFloat(r.metros||0);
            const noJulio = String(r.no_julio||'').trim();
            const noOrden = String(r.no_orden||'').trim();
            const hasBoth = metrosF>0 && noJulio!=='';
            const isReservado = noJulio!=='' && noOrden!=='';

            // Verificar si está en selección múltiple
            const telarNo = r.no_telar || '';
            const tipoUpper = (r.tipo || '').toString().toUpperCase().trim();
            const isInMultiple = state.selectedTelares && state.selectedTelares.some(t =>
                t.no_telar === telarNo &&
                (t.tipo || '').toString().toUpperCase().trim() === tipoUpper
            );

            // Determinar fondo: prioridad: múltiple > reservado > alternado
            let baseBg = hasBoth ? 'bg-blue-100' : (idx%2===0?'bg-white':'bg-gray-50');
            let border = hasBoth ? 'border-l-4 border-blue-400' : '';
            if (isInMultiple) {
                baseBg = 'bg-yellow-50';
                border = 'border-l-[3px] border-yellow-500';
            }

            const tr = document.createElement('tr');
            tr.className = `selectable-row hover:bg-blue-50 cursor-pointer ${baseBg} ${border}`;
            tr.dataset.baseBg = baseBg;
            tr.dataset.telar   = r.no_telar ?? '';
            tr.dataset.tipo    = (r.tipo ?? '').toString().toUpperCase().trim();
            tr.dataset.cuenta  = r.cuenta ?? '';
            tr.dataset.calibre = r.calibre ?? '';
            tr.dataset.hilo    = (r.hilo ?? '').toString().trim();
            tr.dataset.salon   = r.salon ?? '';
            tr.dataset.noJulio = noJulio;
            tr.dataset.noOrden = noOrden;
            tr.dataset.metros  = r.metros ?? '';
            tr.dataset.hasBoth = hasBoth ? 'true' : 'false';
            tr.dataset.isReservado = isReservado ? 'true' : 'false';

            // Construir atributos del checkbox
            const checkboxChecked = isInMultiple ? ' checked' : '';
            const checkboxDisabled = isReservado ? ' disabled' : '';
            const checkboxCursor = isReservado ? 'cursor-not-allowed opacity-50' : 'cursor-pointer';

            tr.innerHTML = `
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center font-bold">${r.no_telar ?? ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.tipo)}">${r.tipo||'-'}</span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.cuenta||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${fmt.num(r.calibre)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${fmt.date(r.fecha)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.turno||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.hilo||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${fmt.num(r.metros,0)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.no_julio||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.no_orden||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.tipo_atado||'Normal'}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.salonBadge(r.salon)}">${r.salon||'Jacquard'}</span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <input type="checkbox"
                           class="telar-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 ${checkboxCursor}"
                           data-telar="${r.no_telar ?? ''}" data-tipo="${(r.tipo ?? '').toString().toUpperCase().trim()}"
                           ${checkboxDisabled}${checkboxChecked}>
                </td>`;
            frag.appendChild(tr);
        });
        tbody.appendChild(frag);
        this.updateSortIcons();
    },

    inventario(rows){
        const tbody = $('#inventarioTable tbody'); if(!tbody) return;
        tbody.innerHTML='';

        if (state.inventarioDataOriginal.length===0 && rows?.length){
            state.inventarioDataOriginal = JSON.parse(JSON.stringify(rows));
        }

        const tel = state.selectedTelar;
        let data = rows || [];

        if (tel){
            const telCuenta = (tel.cuenta||'').toString().trim();
            const telTipo   = (tel.tipo||'').toString().toUpperCase().trim();
            const telNo     = tel.no_telar;
            const telJulio  = tel.no_julio;

            data = data.filter(r=>{
                const hasTelar = !!(r.NoTelarId && r.NoTelarId!=='');
                const invTipo  = (r.Tipo||'').toString().toUpperCase().trim();

                // Obligatorio: misma cuenta (InventSizeId inicia con cuenta del telar)
                if (telCuenta && !matchCuenta(telCuenta, r.InventSizeId)) return false;

                // Si el telar ya tiene No. Julio, mostrar solo esa pieza
                if (telJulio) return (r.InventSerialId||'') === telJulio;

                // Ocultar piezas asignadas a otro telar
                if (!tel.is_reservado && hasTelar && r.NoTelarId !== telNo) return false;

                // Coincidencia por Tipo (Rizo/Pie)
                if (telTipo && invTipo && invTipo !== telTipo) return false;

                return true;
            });
        }

        state.inventarioData = data;

        if (!data.length){
            tbody.innerHTML = `<tr><td colspan="12" class="px-4 py-8 text-center text-sm text-gray-500">
                <i class="fa-solid fa-box-open w-12 h-12 text-gray-400 mb-2"></i> No hay datos de inventario disponible por el momento
            </td></tr>`;
        return;
    }

        const frag = document.createDocumentFragment();
        data.forEach(r=>{
            const hasTelar = r.NoTelarId && r.NoTelarId!=='';
            const tr = document.createElement('tr');
            tr.className = hasTelar ? 'bg-green-100 selectable-row-inventario cursor-not-allowed opacity-75'
                                    : 'hover:bg-orange-50 selectable-row-inventario cursor-pointer';
            tr.dataset.disabled = hasTelar ? 'true':'false';
            Object.assign(tr.dataset, {
                itemId:r.ItemId||'', configId:r.ConfigId||'', inventSizeId:r.InventSizeId||'',
                inventColorId:r.InventColorId||'', inventLocationId:r.InventLocationId||'',
                inventBatchId:r.InventBatchId||'', wmsLocationId:r.WMSLocationId||'',
                inventSerialId:r.InventSerialId||'', noTelarId:r.NoTelarId||'',
                metros:r.Metros||'', numJulio:r.InventSerialId||''
            });

            const kilos  = fmt.num(r.InventQty,0);
            const metros = fmt.num(r.Metros,0);

            tr.innerHTML = `
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.ItemId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.Tipo)}">${r.Tipo||''}</span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.ConfigId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.InventSizeId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.InventColorId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.InventBatchId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.WMSLocationId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.InventSerialId||''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${fmt.date(r.ProdDate)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${metros}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${kilos}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center font-medium">${r.NoTelarId||''}</td>`;
            frag.appendChild(tr);
        });
        tbody.appendChild(frag);
    }
};

/* ---------- Selección ---------- */
const selection = {
    clearVisualRow(row){
        if (!row) return;
        row.classList.remove('is-selected','bg-blue-500','text-white','bg-green-500','bg-yellow-50');
        row.style.removeProperty('background-color'); row.style.removeProperty('color');
        row.style.removeProperty('border-left');
        row.querySelectorAll('td').forEach(td=>{ td.classList.remove('text-white'); td.style.removeProperty('color'); });

        const hasBoth = row.dataset.hasBoth === 'true';
        const base = row.dataset.baseBg || (row.className.includes('bg-gray-50') ? 'bg-gray-50' : 'bg-white');
        row.className = `selectable-row hover:bg-blue-50 cursor-pointer ${hasBoth?'bg-blue-100 border-l-4 border-blue-400':base}`;
        // no tocamos el estado del checkbox (el usuario decide)
    },
    clearVisualInventario(row){
        if (!row) return;
        row.classList.remove('is-selected','bg-green-500','text-white');
        row.style.removeProperty('background-color'); row.style.removeProperty('color');
        row.querySelectorAll('td').forEach(td=>{ td.classList.remove('text-white'); td.style.removeProperty('color'); });
        row.className = row.dataset.disabled==='true'
            ? 'bg-green-100 selectable-row-inventario cursor-not-allowed opacity-75'
            : 'hover:bg-orange-50 selectable-row-inventario cursor-pointer';
    },
    clear(){
        this.clearVisualRow($('#telaresTable .selectable-row.is-selected'));
        this.clearVisualInventario($('#inventarioTable .selectable-row-inventario.is-selected'));
        state.selectedTelar=null; state.selectedInventario=null;
        disable($('#btnProgramar'),true); disable($('#btnReservar'),true); disable($('#btnLiberarTelar'),true);
        if (state.inventarioDataOriginal.length>0) render.inventario(state.inventarioDataOriginal);
    },
    toggleTelarCheckbox(row, checked){
        const cb = row.querySelector('.telar-checkbox');
        const tipoUpper = (row.dataset.tipo || '').toUpperCase().trim();
        const item = {
            no_telar: row.dataset.telar || '',
            tipo: (tipoUpper==='RIZO'?'Rizo':(tipoUpper==='PIE'?'Pie':tipoUpper)),
            calibre: row.dataset.calibre,
            hilo: row.dataset.hilo,
            salon: row.dataset.salon,
            cuenta: row.dataset.cuenta || '',
            no_julio: row.dataset.noJulio || '',
            no_orden: row.dataset.noOrden || '',
            is_reservado: row.dataset.isReservado === 'true'
        };

        if (item.is_reservado && checked){
            Swal.fire({toast:true,position:'top-end',icon:'info',title:'Telar reservado',text:'No se puede usar en selección múltiple',showConfirmButton:false,timer:2000});
            if (cb) cb.checked = false; return;
        }

        // Asegurar que state.selectedTelares sea un array
        if (!Array.isArray(state.selectedTelares)) {
            state.selectedTelares = [];
        }

        if (checked){
            // Verificar que el telar no esté reservado antes de agregarlo a la selección múltiple
            if (item.is_reservado) {
                Swal.fire({toast:true,position:'top-end',icon:'info',title:'Telar reservado',text:'No se puede usar en selección múltiple',showConfirmButton:false,timer:2000});
                if (cb) cb.checked = false; return;
            }

            if (state.selectedTelares.length > 0){
                const ref = state.selectedTelares[0];
                // Verificar que el telar de referencia no esté reservado
                if (ref.is_reservado) {
                    Swal.fire({toast:true,position:'top-end',icon:'info',title:'Telar reservado en selección',text:'No se puede agregar más telares a una selección que contiene telares reservados',showConfirmButton:false,timer:3000});
                    if (cb) cb.checked = false; return;
                }
                if (!sameGroup(item, ref)){
                    Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Selección incompatible',text:'Solo puedes seleccionar telares con el mismo Tipo, Calibre, Hilo y Salón. La cuenta puede variar.',showConfirmButton:false,timer:3000});
                    if (cb) cb.checked = false; return;
                }
            }
            const exists = state.selectedTelares.some(t => t.no_telar===item.no_telar && eq.str(t.tipo,item.tipo));
            if (!exists) {
                state.selectedTelares.push(item);
            }
            row.classList.add('bg-yellow-50');
            row.style.setProperty('border-left','3px solid #eab308','important');
        }else{
            state.selectedTelares = state.selectedTelares.filter(t => !(t.no_telar===item.no_telar && eq.str(t.tipo,item.tipo)));
            row.classList.remove('bg-yellow-50');
            row.style.removeProperty('border-left');
        }

        // Actualizar estado de botones después de cambiar selección múltiple
        this.validateButtons();
    },
    applyTelar(row){
        const prev = $('#telaresTable .selectable-row.is-selected'); if (prev && prev!==row) this.clearVisualRow(prev);

        const hasBoth = row.dataset.hasBoth === 'true';
        row.className = `selectable-row is-selected cursor-pointer ${hasBoth?'border-l-4 border-blue-300':''}`;
        row.classList.add('bg-blue-500','text-white');
        row.style.setProperty('background-color','#3b82f6','important');
        row.style.setProperty('color','#fff','important');
        row.querySelectorAll('td').forEach(td=>{ td.classList.add('text-white'); td.style.setProperty('color','#fff','important'); });

        const tipoUpper = (row.dataset.tipo||'').toUpperCase().trim();
        const tipoOk = tipoUpper==='RIZO'?'Rizo':(tipoUpper==='PIE'?'Pie':tipoUpper);

        state.selectedTelar = {
            no_telar: row.dataset.telar || null,
            tipo: tipoOk,
            cuenta: row.dataset.cuenta || '',
            salon: row.dataset.salon || '',
            calibre: row.dataset.calibre || '',
            hilo: row.dataset.hilo || '',
            no_julio: row.dataset.noJulio || '',
            no_orden: row.dataset.noOrden || '',
            is_reservado: row.dataset.isReservado === 'true'
        };

        // NO limpiar selección múltiple cuando se selecciona individualmente
        // La selección múltiple y la individual pueden coexistir
        this.validateButtons();

        if (state.inventarioDataOriginal.length>0) {
                render.inventario(state.inventarioDataOriginal);
            if (state.selectedTelar.no_julio){
                setTimeout(()=>{
                    const match = [...$$('#inventarioTable .selectable-row-inventario')]
                        .find(r => (r.dataset.inventSerialId||'') === state.selectedTelar.no_julio);
                    if (match) this.applyInventario(match);
                },100);
            }
        }
    },
    applyInventario(row){
        if (state.selectedTelar?.cuenta){
            const okCuenta = matchCuenta(state.selectedTelar.cuenta, row.dataset.inventSizeId);
            if (!okCuenta){
                Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Cuenta distinta',text:'La pieza no coincide con la cuenta del telar',showConfirmButton:false,timer:1800});
            return;
        }
        }
        const prev = $('#inventarioTable .selectable-row-inventario.is-selected'); if (prev && prev!==row) this.clearVisualInventario(prev);

        row.classList.add('is-selected','bg-green-500','text-white');
        row.style.setProperty('background-color','#10b981','important');
        row.style.setProperty('color','#fff','important');
        row.querySelectorAll('td').forEach(td=>{ td.classList.add('text-white'); td.style.setProperty('color','#fff','important'); });

        state.selectedInventario = {
            itemId: row.dataset.itemId || '',
            configId: row.dataset.configId || '',
            inventSizeId: row.dataset.inventSizeId || '',
            inventColorId: row.dataset.inventColorId || '',
            inventLocationId: row.dataset.inventLocationId || '',
            inventBatchId: row.dataset.inventBatchId || '',
            wmsLocationId: row.dataset.wmsLocationId || '',
            inventSerialId: row.dataset.inventSerialId || '',
            metros: parseFloat(row.dataset.metros || 0),
            numJulio: row.dataset.numJulio || '',
            data: state.inventarioData.find(i => i.ItemId===row.dataset.itemId && i.InventSerialId===row.dataset.inventSerialId)
        };

        this.validateButtons();
    },
    validateButtons(){
        const btnProgramar = $('#btnProgramar');
        const btnReservar = $('#btnReservar');
        const btnLiberarTelar = $('#btnLiberarTelar');



        // Validar botón Liberar (solo para telar individual reservado)
        if (state.selectedTelar && state.selectedTelar.is_reservado){
            disable(btnReservar, true);
            disable(btnLiberarTelar, false);
            disable(btnProgramar, true);
            return;
        }
        disable(btnLiberarTelar, true);

        // Validar botón Reservar (telar individual + inventario)
        const sameCuenta = !!(state.selectedTelar && state.selectedInventario
                           && matchCuenta(state.selectedTelar.cuenta, state.selectedInventario.inventSizeId));
        disable(btnReservar, !sameCuenta);

        // Validar botón Programar: PRIORIDAD a selección múltiple
        const hasMultipleSelection = Array.isArray(state.selectedTelares) && state.selectedTelares.length > 0;
        const hasIndividualSelection = state.selectedTelar && state.selectedTelar.no_telar;

        // PRIORIDAD: Si hay selección múltiple, verificar que NINGUNO esté reservado
        if (hasMultipleSelection) {
            // Verificar si algún telar en la selección múltiple está reservado
            const hasReservedTelar = state.selectedTelares.some(t => t.is_reservado === true);
            if (hasReservedTelar) {
                // Si hay algún telar reservado, NO permitir programar
                btnProgramar.disabled = true;
            } else {
                // Si ningún telar está reservado, permitir programar
                btnProgramar.disabled = false;
            }
        } else if (hasIndividualSelection && state.selectedTelar && !state.selectedTelar.is_reservado) {
            // Si hay selección individual y no está reservado, también habilitar
            btnProgramar.disabled = false;
        } else {
            // En cualquier otro caso, deshabilitar
            btnProgramar.disabled = true;
        }
    }
};

/* ---------- Filtros ---------- */
const filters = {
    updateBadge(){
            const total = state.filters.telares.length + state.filters.inventario.length;
        const badge = $('#filterCount');
        if (!badge) return;
            badge.textContent = total;
        badge.classList.toggle('hidden', total===0);
    },
    filterLocal(data, list){
        if (!list?.length || !data?.length) return data;
        return data.filter(item => list.every(f=>{
                const col = f.columna || f.column;
            const val = String(f.valor ?? f.value ?? '').toLowerCase().trim();
                if (!col || !val) return true;
                const itemVal = item[col];

            if (col==='NoTelarId'){
                if (['null','vacío','vacio','disponible',''].includes(val)) return !itemVal;
                return String(itemVal||'').toLowerCase().includes(val);
            }
            if (itemVal==null || itemVal==='') return false;

            if (col==='InventSizeId'){ return String(itemVal).toLowerCase().startsWith(val); }
            if (['fecha','ProdDate'].includes(col)){
                try{
                    const dItem = new Date(itemVal), dFil = new Date(val);
                    if (!isNaN(dItem) && !isNaN(dFil)) return dItem.toDateString()===dFil.toDateString();
                }catch{}
            }
            if (['calibre','metros','InventQty','Metros'].includes(col)){
                const a=parseFloat(itemVal), b=parseFloat(val);
                if (!isNaN(a)&&!isNaN(b)) return Math.abs(a-b)<0.001 || String(itemVal).toLowerCase().includes(val);
            }
            return String(itemVal).toLowerCase().includes(val);
        }));
    },
    async openModal(){
        if (!state.columns.telares.length){
            try{ state.columns.telares = (await http.get(`${API.columnOptions}?table_type=telares`)).columns || []; }catch{}
        }
        if (!state.columns.inventario.length){
            try{ state.columns.inventario = (await http.get(`${API.columnOptions}?table_type=inventario`)).columns || []; }catch{}
        }

        const options = type => (state.columns[type]||[]).map(c=>`<option value="${c.field}">${c.label}</option>`).join('');
        const row = (type, idx, col='', val='') => `
            <div class="filter-row" data-idx="${idx}">
                <div class="grid grid-cols-2 gap-3 p-3 rounded-md bg-gray-50 border border-gray-200 mb-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Columna</label>
                        <select class="filter-col w-full px-2 py-2 border border-gray-300 rounded-md">${options(type)}</select>
                </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Valor</label>
                        <input type="text" class="filter-val w-full px-2 py-2 border border-gray-300 rounded-md" value="${val}">
                </div>
                    <div class="col-span-2 flex justify-end">
                        <button type="button" class="btn-rm px-3 py-1.5 bg-red-500 text-white rounded-md"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>
            </div>`;

        const renderRows = (type, list) => list.map(f=>row(type, f.idx, f.column, f.value)).join('');

        const html = `
            <div id="swalFilterContainer" class="text-left">
                <div class="mb-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tabla</label>
                    <select id="swalTableSel" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="telares" selected>Programación de Requerimientos</option>
                        <option value="inventario">Inventario Disponible</option>
                    </select>
                </div>
                <div id="swalFilters" class="max-h-[420px] overflow-y-auto">
                    ${renderRows('telares', state.filters.telares.length ? state.filters.telares : [{table:'telares',column:'',value:'',idx:Date.now()}])}
                </div>
                <button type="button" id="swalAdd"
                        class="w-full mt-3 px-3 py-2 border-2 border-dashed border-gray-300 rounded-md text-gray-500">
                    <i class="fa-solid fa-plus mr-1"></i> Agregar filtro
                </button>
            </div>`;

        const result = await Swal.fire({
            title:'Filtrar Tablas', html, width:'700px',
            showCancelButton:true, confirmButtonText:'Aplicar',
            cancelButtonText:'Cancelar', confirmButtonColor:'#9333ea', cancelButtonColor:'#6b7280',
            didOpen: ()=>{
                const container = $('#swalFilterContainer');
                const add = $('#swalAdd');
                const selectTable = $('#swalTableSel');

                const bindRemove = () => {
                    container.querySelectorAll('.btn-rm').forEach(btn=>{
                        btn.onclick = e => e.currentTarget.closest('.filter-row')?.remove();
                    });
                };
                bindRemove();

                add.onclick = ()=>{
                    $('#swalFilters').insertAdjacentHTML('beforeend', row(selectTable.value, Date.now()));
                    bindRemove();
                };

                selectTable.onchange = e=>{
                    const type = e.target.value;
                    const list = (state.filters[type] && state.filters[type].length) ? state.filters[type] : [{table:type,column:'',value:'',idx:Date.now()}];
                    $('#swalFilters').innerHTML = renderRows(type, list);
                    bindRemove();
                };
            },
            preConfirm: ()=>{
                const type = $('#swalTableSel').value;
                const list = [...$$('#swalFilters .filter-row')].map(div=>{
                    const col = div.querySelector('.filter-col')?.value?.trim() || '';
                    const val = div.querySelector('.filter-val')?.value?.trim() || '';
                    return (col && val) ? { columna: col, valor: val } : null;
                }).filter(Boolean);
                if (!list.length) { Swal.showValidationMessage('Agrega al menos un filtro válido'); return false; }
                return { type, filters:list };
            }
        });

        if (!result.isConfirmed) return;

        const { type, filters:list } = result.value;
        const original = type==='telares'
            ? (state.telaresDataOriginal.length?state.telaresDataOriginal:state.telaresData)
            : (state.inventarioDataOriginal.length?state.inventarioDataOriginal:state.inventarioData);

            const filtered = filters.filterLocal(original, list);
        (type==='telares' ? render.telares : render.inventario)(filtered);

        state.filters[type] = list.map((f,i)=>({table:type,column:f.columna,value:f.valor,idx:Date.now()+i}));
        filters.updateBadge();
        Swal.fire({toast:true,position:'top-end',icon:'success',title:`${list.length} filtro(s) aplicados`,showConfirmButton:false,timer:2000});
    },
    reset(){
        if (state.telaresDataOriginal.length)  render.telares(state.telaresDataOriginal);
        if (state.inventarioDataOriginal.length) render.inventario(state.inventarioDataOriginal);
        state.filters = { telares:[], inventario:[] };
        filters.updateBadge();
        Swal.fire({toast:true,position:'top-end',icon:'success',title:'Filtros restablecidos',showConfirmButton:false,timer:1500});
    }
};

/* ---------- Sorting ---------- */
const sorting = {
    toggle(col){
        state.sort = (state.sort.column===col)
            ? { column:col, direction: state.sort.direction==='asc'?'desc':'asc' }
            : { column:col, direction:'asc' };
        render.telares(state.telaresData);
    },
    bind(){
        $('#telaresTable thead')?.addEventListener('click', e=>{
            const th = e.target.closest('.sortable'); if (!th) return;
            const col = th.dataset.column; if (!col) return;
            sorting.toggle(col);
        });
    }
};

 /* ---------- Acciones ---------- */
const actions = {
    async programar(){
         // Si hay selección múltiple, redirigir a programación de requerimientos
         if (state.selectedTelares && state.selectedTelares.length > 0) {
             const telaresJson = encodeURIComponent(JSON.stringify(state.selectedTelares));
             const url = '{{ route("programa.urd.eng.programacion.requerimientos") }}?telares=' + telaresJson;

             // Guardar en sessionStorage por si acaso
             sessionStorage.setItem('selectedTelares', JSON.stringify(state.selectedTelares));

             // Redirigir
             window.location.href = url;
        return;
    }

         // Si hay selección individual, también redirigir a programación de requerimientos (sin modal)
         if (state.selectedTelar && state.selectedTelar.no_telar && !state.selectedTelar.is_reservado) {
             // Buscar el telar completo en los datos originales (que tienen todos los campos)
             const telarCompleto = (state.telaresDataOriginal.length > 0 ? state.telaresDataOriginal : state.telaresData).find(t => {
                 const telarMatch = String(t.no_telar || '') === String(state.selectedTelar.no_telar || '');
                 const tipoMatch = String(t.tipo || '').toUpperCase().trim() === String(state.selectedTelar.tipo || '').toUpperCase().trim();
                 return telarMatch && tipoMatch;
             });

             // Convertir selección individual a array para mantener consistencia
             // Usar datos del selectedTelar primero, luego completar con telarCompleto si existe
             const telarArray = [{
                 no_telar: state.selectedTelar.no_telar,
                 tipo: state.selectedTelar.tipo,
                 cuenta: state.selectedTelar.cuenta || (telarCompleto ? (telarCompleto.cuenta || '') : ''),
                 salon: state.selectedTelar.salon || (telarCompleto ? (telarCompleto.salon || '') : ''),
                 calibre: state.selectedTelar.calibre || (telarCompleto ? (telarCompleto.calibre || '') : ''),
                 hilo: state.selectedTelar.hilo || (telarCompleto ? (telarCompleto.hilo || '') : '')
             }];

             const telaresJson = encodeURIComponent(JSON.stringify(telarArray));
             const url = '{{ route("programa.urd.eng.programacion.requerimientos") }}?telares=' + telaresJson;

             // Guardar en sessionStorage por si acaso
             sessionStorage.setItem('selectedTelares', JSON.stringify(telarArray));

             // Redirigir directamente sin modal
             window.location.href = url;
             return;
         }

         // Si no hay selección válida
         if (!state.selectedTelar?.no_telar) {
             return Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Selecciona un telar',showConfirmButton:false,timer:2000});
         }

         // Si el telar está reservado, no se puede programar
         if (state.selectedTelar.is_reservado) {
             return Swal.fire({toast:true,position:'top-end',icon:'info',title:'Telar reservado',text:'No se puede programar un telar reservado',showConfirmButton:false,timer:2000});
         }
     },
    async liberarTelar(){
        const tel = state.selectedTelar;
        if (!tel?.no_telar) return Swal.fire('Aviso','Selecciona un telar primero','warning');
        if (!tel.is_reservado) return Swal.fire('Aviso','Este telar no está reservado','warning');

        const ok = await Swal.fire({
            title:'¿Liberar telar?',
            icon:'warning', showCancelButton:true, confirmButtonText:'Sí, liberar', confirmButtonColor:'#dc2626'
        }).then(r=>r.isConfirmed);
        if (!ok) return;

        show($('#loaderInventario')); show($('#loaderTelares'));
        try{
            const resp = await http.post(API.liberarTelar,{ no_telar: tel.no_telar, tipo: tel.tipo });
            if (resp.success){
                const [inv, telrs] = await Promise.all([ http.get(API.inventarioDisponibleGet), http.get(API.inventarioTelares) ]);
                if (inv?.data){ state.inventarioDataOriginal = JSON.parse(JSON.stringify(inv.data)); render.inventario(inv.data); }
                if (telrs?.data){ state.telaresDataOriginal = JSON.parse(JSON.stringify(telrs.data)); state.telaresData = telrs.data; render.telares(telrs.data); }
                Swal.fire({toast:true,position:'top-end',icon:'success',title: resp.message || 'Telar liberado',showConfirmButton:false,timer:3000});
                selection.clear();
            }
        }catch(e){ Swal.fire('Error', e.message||'Error al liberar','error'); }
        finally{ hide($('#loaderInventario')); hide($('#loaderTelares')); }
    },
    async reservar(){
        const tel = state.selectedTelar;
        if (!tel?.no_telar) return Swal.fire('Aviso','Selecciona un telar','warning');
        if (tel.is_reservado) return Swal.fire('Aviso','Este telar ya está reservado','warning');
        if (!state.selectedInventario?.data) return Swal.fire('Aviso','Selecciona una fila de inventario','warning');

        if (!matchCuenta(tel.cuenta, state.selectedInventario.inventSizeId)){
            return Swal.fire({icon:'warning',title:'Cuenta distinta',text:'La InventSizeId no coincide con la cuenta del telar.'});
        }
        if (state.selectedInventario.data.NoTelarId) return Swal.fire('Aviso','Esa pieza ya tiene telar asignado','warning');

        const ok = await Swal.fire({
            title:'¿Reservar pieza?',
            text:`Reservar para telar ${tel.no_telar} (${tel.tipo||'N/A'})`,
            icon:'question', showCancelButton:true, confirmButtonText:'Sí, reservar'
        }).then(r=>r.isConfirmed);
        if (!ok) return;

        show($('#loaderInventario')); show($('#loaderTelares'));
        try{
            // Obtener el lote (InventBatchId) del inventario seleccionado
            const lote = state.selectedInventario.inventBatchId || state.selectedInventario.data?.InventBatchId || '';

            // Actualizar telar (metros / no_julio / no_orden)
            await http.post(API.actualizarTelar,{
                no_telar: tel.no_telar, tipo: tel.tipo,
                        metros: state.selectedInventario.metros || 0,
                        no_julio: state.selectedInventario.numJulio || '',
                        no_orden: lote
                    });

            // Refrescar UI local del telar
            const tTipo = (tel.tipo||'').toUpperCase().trim();
            const ix = state.telaresData.findIndex(x => x.no_telar===tel.no_telar && (String(x.tipo||'').toUpperCase().trim()===tTipo));
            if (ix>-1){
                state.telaresData[ix].metros   = state.selectedInventario.metros || 0;
                state.telaresData[ix].no_julio = state.selectedInventario.numJulio || '';
                state.telaresData[ix].no_orden = lote;
                const jx = state.telaresDataOriginal.findIndex(x => x.no_telar===tel.no_telar && (String(x.tipo||'').toUpperCase().trim()===tTipo));
                if (jx>-1){
                    state.telaresDataOriginal[jx].metros = state.telaresData[ix].metros;
                    state.telaresDataOriginal[jx].no_julio = state.telaresData[ix].no_julio;
                    state.telaresDataOriginal[jx].no_orden = lote;
                }
                        render.telares(state.telaresData);
            }

            // Reservar
            const it = state.selectedInventario.data;
                    const payload = {
                NoTelarId: tel.no_telar,
                SalonTejidoId: tel.salon || null,
                ItemId: it.ItemId, ConfigId: it.ConfigId||null, InventSizeId: it.InventSizeId||null,
                InventColorId: it.InventColorId||null, InventLocationId: it.InventLocationId||null,
                InventBatchId: it.InventBatchId||null, WMSLocationId: it.WMSLocationId||null,
                InventSerialId: it.InventSerialId||null,
                Tipo: (String(tel.tipo||'').toUpperCase()==='RIZO'?'Rizo':String(tel.tipo||'').toUpperCase()==='PIE'?'Pie':(tel.tipo||null)),
                Metros: it.Metros||null, InventQty: it.InventQty||null, ProdDate: it.ProdDate||null
            };
            await http.post(API.reservarInventario, payload);

            // Recargar tablas
            const [inv, telrs] = await Promise.all([ http.get(API.inventarioDisponibleGet), http.get(API.inventarioTelares) ]);
            if (inv?.data){ state.inventarioDataOriginal = JSON.parse(JSON.stringify(inv.data)); render.inventario(inv.data); }
            if (telrs?.data){
                state.telaresDataOriginal = JSON.parse(JSON.stringify(telrs.data));
                state.telaresData = telrs.data; render.telares(telrs.data);
                const tTipo = (tel.tipo||'').toUpperCase().trim();
                setTimeout(()=>{
                    const row = [...$$('#telaresTable .selectable-row')]
                        .find(r => r.dataset.telar===tel.no_telar && (r.dataset.tipo||'').toUpperCase().trim()===tTipo);
                    if (row) selection.applyTelar(row);
                },100);
            }

            Swal.fire({toast:true,position:'top-end',icon:'success',title:'Pieza reservada',showConfirmButton:false,timer:2500});
        }catch(e){
            Swal.fire('Error', e.message||'Error al reservar','error');
        }finally{
            hide($('#loaderInventario')); hide($('#loaderTelares'));
        }
    }
};

/* ---------- Init ---------- */
document.addEventListener('DOMContentLoaded', async ()=>{
    sorting.bind();
    render.telares(state.telaresData);

    // Cargar inventario al inicio
    show($('#loaderInventario'));
    try{
        const { data } = await http.get(API.inventarioDisponibleGet);
        state.inventarioDataOriginal = JSON.parse(JSON.stringify(data||[]));
        render.inventario(data||[]);
        selection.validateButtons?.();
    }catch(e){
        render.inventario([]); disable($('#btnReservar'),true);
    }finally{ hide($('#loaderInventario')); }

    // Checkboxes: selección múltiple (con validación de grupo)
    $('#telaresTable tbody')?.addEventListener('change', e=>{
        if (!e.target.classList.contains('telar-checkbox')) return;
            e.stopPropagation();
        const cb = e.target;
        if (cb.disabled){ cb.checked=false; return; }
        const row = cb.closest('.selectable-row'); if (!row) return;
        selection.toggleTelarCheckbox(row, cb.checked);
    });

    // Click filas telares: selección individual (no afecta checkbox)
    $('#telaresTable tbody')?.addEventListener('click', e=>{
        if (e.target.closest('button,a') || e.target.type==='checkbox' || e.target.closest('.telar-checkbox')) return;
        const row = e.target.closest('.selectable-row'); if (!row) return;
        e.preventDefault(); e.stopPropagation();
        if (row.classList.contains('is-selected')) selection.clear(); else selection.applyTelar(row);
    });

    // Click filas inventario
    $('#inventarioTable tbody')?.addEventListener('click', e=>{
        if (e.target.closest('button,a')) return;
        const row = e.target.closest('.selectable-row-inventario'); if (!row) return;
        if (row.dataset.disabled==='true'){
            Swal.fire({toast:true,position:'top-end',icon:'info',title:'Pieza ya reservada',showConfirmButton:false,timer:1800});
                return;
            }
        e.preventDefault(); e.stopPropagation();
        selection.applyInventario(row);
    });

    // Topbar
    $('#btnOpenFilters')?.addEventListener('click', filters.openModal);
    $('#btnResetFiltros')?.addEventListener('click', filters.reset);
    $('#btnReloadTelares')?.addEventListener('click', filters.reset);

    // Acciones
    $('#btnProgramar')?.addEventListener('click', actions.programar);
    $('#btnReservar')?.addEventListener('click', actions.reservar);
    $('#btnLiberarTelar')?.addEventListener('click', actions.liberarTelar);
});
</script>
@endsection
