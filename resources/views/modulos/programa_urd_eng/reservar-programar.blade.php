@extends('layouts.app')

@section('page-title', 'Reservar y Programar ')

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create
        id="btnReservar"
        type="button"
        title="Reservar"
        icon="fa-save"
        iconColor="text-white"
        hoverBg="hover:bg-gray-600"
        bg="bg-gray-500"
        text="Reservar"
        disabled
        />

    <x-navbar.button-create
        id="btnLiberarTelar"
        type="button"
        title="Liberar telar"
        icon="fa-unlock"
        iconColor="text-white"
        hoverBg="hover:bg-red-600"
        bg="bg-red-500"
        text="Liberar telar"
        />

    <x-navbar.button-create
        id="btnProgramar"
        type="button"
        title="Programar"
        icon="fa-calendar-check"
        iconColor="text-white"
        hoverBg="hover:bg-purple-600"
        bg="bg-purple-500"
        text="Programar"
        />


        <button id="btnResetFiltros" type="button"
        class="px-3 py-2 text-gray-500 hover:text-gray-600 rounded-lg transition-colors flex items-center justify-center"
        title="Restablecer filtros">
        <i class="fa-solid fa-arrows-rotate w-5 h-5"></i>
    </button>

    <button id="btnOpenFilters" type="button"
        class="relative px-3 py-2 text-blue-500 hover:text-blue-600 rounded-lg transition-colors flex items-center justify-center"
        title="Aplicar filtros">
        <i class="fa-solid fa-filter w-5 h-5"></i>
    </button>
</div>
@endsection

@section('content')
@php
    $esSupervisor = strtolower(auth()->user()->puesto ?? '') === 'supervisor';
@endphp
<div class="w-full">

    {{-- =================== Tabla: Programación (telares) =================== --}}
    <div class="bg-white overflow-hidden w-full">
        <div class="relative w-full">
            <div id="loaderTelares"
                 class="hidden absolute inset-0  backdrop-blur-sm z-10 flex items-center justify-center">
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
                    <div class="overflow-y-auto max-h-[300px] w-full">
                        <table id="telaresTable" class="w-full table-auto divide-y divide-gray-200">
                            <thead class="bg-white text-gray-900 sticky top-0 z-20">
                                <tr>
                                    @foreach($headers as $h)
                                        <th class="px-3 py-2 text-center text-xs font-medium tracking-wider whitespace-nowrap sortable"
                                            data-column="{{ $h['key'] }}">
                                            <button type="button"
                                                    class="w-full flex items-center justify-center gap-2 cursor-pointer">
                                                <span>{{ $h['label'] }}</span>
                                                <i class="fa-solid fa-sort text-gray-400 sort-icon"></i>
                                            </button>
                                        </th>
                                    @endforeach
                                    <th class="px-3 py-2 text-center text-xs font-medium tracking-wider whitespace-nowrap">
                                        Seleccionar
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($inventarioTelares as $i => $t)
                                    @php
                                        $tipo     = strtoupper($t['tipo'] ?? '-');
                                        $tipoCls  = $tipo === 'RIZO'
                                            ? 'bg-rose-100 text-rose-700'
                                            : ($tipo === 'PIE'
                                                ? 'bg-teal-100 text-teal-700'
                                                : 'bg-gray-100 text-gray-700');

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
                                        $tieneNoOrden = $noOrdenTrim !== '';

                                        $finalBg      = $hasBoth ? 'bg-blue-100' : $baseBg;
                                        $blueBorder   = $hasBoth ? 'border-l-4 border-blue-400' : '';

                                        $checkboxDisabled = ($isReservado || $tieneNoOrden) ? 'disabled' : '';
                                        $checkboxCursor   = ($isReservado || $tieneNoOrden)
                                            ? 'cursor-not-allowed opacity-50'
                                            : 'cursor-pointer';

                                        $checkboxTitle = $isReservado
                                            ? 'Telar reservado - no se puede seleccionar'
                                            : ($tieneNoOrden
                                                ? 'Telar con orden - no se puede seleccionar'
                                                : 'Selección múltiple (misma cuenta/atributos)');
                                    @endphp
                                    <tr class="selectable-row hover:bg-blue-50 cursor-pointer {{ $finalBg }} {{ $blueBorder }}"
                                        data-base-bg="{{ $baseBg }}"
                                        data-id="{{ $t['id'] ?? '' }}"
                                        data-telar="{{ $t['no_telar'] ?? '' }}"
                                        data-tipo="{{ strtoupper(trim($t['tipo'] ?? '')) }}"
                                        data-cuenta="{{ $t['cuenta'] ?? '' }}"
                                        data-calibre="{{ $t['calibre'] ?? '' }}"
                                        data-hilo="{{ trim($t['hilo'] ?? '') }}"
                                        data-salon="{{ $salon }}"
                                        data-no-julio="{{ $noJulioTrim }}"
                                        data-no-orden="{{ $noOrdenTrim }}"
                                        data-metros="{{ $t['metros'] ?? '' }}"
                                        data-fecha="{{ !empty($t['fecha']) ? \Carbon\Carbon::parse($t['fecha'])->format('Y-m-d') : '' }}"
                                        data-turno="{{ $t['turno'] ?? '' }}"
                                        data-has-both="{{ $hasBoth ? 'true' : 'false' }}"
                                        data-is-reservado="{{ $isReservado ? 'true' : 'false' }}">
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center font-bold">
                                            {{ $t['no_telar'] ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $tipoCls }}">
                                                {{ $t['tipo'] ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            {{ $t['cuenta'] ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            {{ number_format((float)($t['calibre'] ?? 0), 2) }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            {{ !empty($t['fecha']) ? \Carbon\Carbon::parse($t['fecha'])->format('d-M-Y') : '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            {{ $t['turno'] ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            {{ $t['hilo'] ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            {{ number_format((float)($t['metros'] ?? 0), 0) }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            {{ $t['no_julio'] ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            {{ $t['no_orden'] ?? '' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            @php $tipoAtado = $t['tipo_atado'] ?? 'Normal'; @endphp
                                            @if($esSupervisor)
                                                <select
                                                    class="tipo-atado-select w-full bg-white px-2 py-1 text-xs border border-gray-300 rounded-md text-gray-900 focus:ring-2 focus:ring-blue-500"
                                                    data-telar="{{ $t['no_telar'] ?? '' }}"
                                                    data-tipo="{{ strtoupper(trim($t['tipo'] ?? '')) }}"
                                                >
                                                    <option value="Normal" {{ $tipoAtado === 'Normal' ? 'selected' : '' }}>Normal</option>
                                                    <option value="Especial" {{ $tipoAtado === 'Especial' ? 'selected' : '' }}>Especial</option>
                                                </select>
                                            @else
                                                <span class="text-gray-800 text-xs font-medium">{{ $tipoAtado }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $salonCls }}">
                                                {{ $salon }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                                            <input type="checkbox"
                                                   class="telar-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 {{ $checkboxCursor }}"
                                                   data-telar="{{ $t['no_telar'] ?? '' }}"
                                                   data-tipo="{{ strtoupper(trim($t['tipo'] ?? '')) }}"
                                                   {{ $checkboxDisabled }}
                                                   title="{{ $checkboxTitle }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <i class="fa-solid fa-box-open w-12 h-12 text-gray-400"></i>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No hay inventario disponible</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        No se han registrado telares en el inventario.
                    </p>
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
    <div class="bg-white overflow-hidden">
        <div class="bg-blue-500 px-4 flex justify-between items-center">
            <h2 class="text-lg font-bold text-white text-center flex-1">Inventario Disponible</h2>
            <button id="btnQuitarFiltroInventario" type="button"
                    class="hidden px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors flex items-center gap-2 text-sm font-medium"
                    title="Quitar filtro y mostrar todos los registros">
                <i class="fa-solid fa-filter-circle-xmark"></i>
                <span>Quitar Filtro</span>
            </button>
        </div>

        <div class="relative">
            <div id="loaderInventario"
                 class="hidden absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500"></div>
            </div>

            <div class="overflow-x-auto w-full">
                <div class="overflow-y-auto max-h-[260px] w-full" style="max-height: 260px;">
                    <table id="inventarioTable" class="w-full table-auto divide-y divide-gray-200">
                        <thead class="bg-gray-100 text-gray-900 sticky top-0 z-20">
                            <tr>
                                @foreach(['Artículo','Tipo','Fibra','Cuenta','Cod Color','Lote','Localidad','No. Julio','Fecha','Metros','Kilos','Telar'] as $head)
                                    <th class="px-3 py-2 text-center text-xs font-medium tracking-wider whitespace-nowrap">
                                        {{ $head }}
                                    </th>
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
    .bg-white.overflow-hidden.w-full:first-of-type {
        width:100% !important;
        max-width:100% !important;
        margin:0 !important;
        padding:0 !important;
    }
    .overflow-x-auto { width:100% !important; max-width:100vw !important; }
    #telaresTable tbody tr.bg-blue-100{ background-color:#dbeafe !important; }
    #telaresTable tbody tr.bg-blue-100.border-l-4{
        border-left-color:#60a5fa !important;
        border-left-width:4px;
    }
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

const ES_SUPERVISOR = @json($esSupervisor);
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* ---------- Helpers DOM ---------- */
const $  = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
const show    = el => el && el.classList.remove('hidden');
const hide    = el => el && el.classList.add('hidden');
const disable = (el, v = true) => { if (el) el.disabled = !!v; return el; };

/* ---------- Helpers genéricos ---------- */
const toast = (icon, title, text = '', timer = 2000) => {
    if (typeof Swal === 'undefined') return;
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title,
        text,
        showConfirmButton: false,
        timer
    });
};

const http = {
    async request(url, options = {}) {
        const res = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json'
            },
            ...options
        });

        const json = await res.json().catch(() => ({ success: false }));

        if (!res.ok || json.success === false) {
            throw new Error(json.message || res.statusText);
        }

        return json;
    },
    get:  url      => http.request(url, { method: 'GET' }),
    post: (url, b) => http.request(url, { method: 'POST', body: JSON.stringify(b || {}) })
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
        return map[String(s || 'Jacquard').trim()] || 'bg-indigo-100 text-indigo-700';
    },
    tipoBadge(t) {
        const u = String(t || '-').toUpperCase().trim();
        if (u === 'RIZO') return 'bg-rose-100 text-rose-700';
        if (u === 'PIE')  return 'bg-teal-100 text-teal-700';
        return 'bg-gray-100 text-gray-700';
    },
    num(n, d = 2) {
        if (n === null || n === undefined || n === '') return '';
        const val = Number(n);
        return Number.isNaN(val) ? '' : val.toFixed(d);
    },
    date(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return Number.isNaN(d.getTime())
            ? ''
            : d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
    }
};

/** Coincidencia de cuenta: telar "3156" => InventSizeId que empiece por "3156" */
const matchCuenta = (cuentaTelar, inventSizeId) => {
    const a = String(cuentaTelar || '').replace(/\s+/g, '').toUpperCase();
    const b = String(inventSizeId || '').replace(/\s+/g, '').toUpperCase();
    if (!a) return false;
    return b.startsWith(a);
};

const eq = {
    str: (a, b) => String(a || '').trim().toUpperCase() === String(b || '').trim().toUpperCase(),
    num: (a, b) => {
        const x = Number(a);
        const y = Number(b);
        if (Number.isNaN(x) || Number.isNaN(y)) return String(a) === String(b);
        return Math.abs(x - y) < 1e-6;
    }
};

const sameGroup = (a, b) =>
    eq.str(a.tipo, b.tipo) &&
    eq.num(a.calibre, b.calibre) &&
    eq.str(a.hilo, b.hilo) &&
    eq.str(a.salon, b.salon);

const normalizeTipo = t => {
    const u = String(t || '').toUpperCase().trim();
    if (u === 'RIZO') return 'Rizo';
    if (u === 'PIE')  return 'Pie';
    return u || '-';
};

const hasNoOrden    = telar => String(telar?.no_orden || '').trim() !== '';
const isReservado   = telar => telar?.is_reservado === true;

/* ---------- Estado ---------- */
const state = {
    filters: { telares: [], inventario: [] },
    selectedTelar: null,       // selección individual
    selectedTelares: [],       // selección múltiple (checkboxes)
    selectedInventario: null,
    columns: { telares: [], inventario: [] },
    sort: { column: 'no_telar', direction: 'asc' },
    telaresData: @json($inventarioTelares ?? []),
    telaresDataOriginal: @json($inventarioTelares ?? []),
    inventarioData: [],
    inventarioDataOriginal: [],
    mostrarTodoInventario: false
};

/* ---------- Render ---------- */
const render = {
    sorted(data, col, dir) {
        return [...data].sort((a, b) => {
            const av = a[col], bv = b[col];
            const emptyA = (av === null || av === undefined || av === '');
            const emptyB = (bv === null || bv === undefined || bv === '');

            if (emptyA && emptyB) return 0;
            if (emptyA) return 1;
            if (emptyB) return -1;

            let cmp = 0;

            if (col === 'fecha') {
                cmp = new Date(av) - new Date(bv);
            } else if (['no_telar','no_julio','no_orden'].includes(col)) {
                cmp = (parseInt(av) || 0) - (parseInt(bv) || 0);
            } else if (['calibre','metros'].includes(col)) {
                cmp = (parseFloat(av) || 0) - (parseFloat(bv) || 0);
            } else {
                const as = String(av).toLowerCase();
                const bs = String(bv).toLowerCase();
                cmp = as < bs ? -1 : (as > bs ? 1 : 0);
            }

            return dir === 'asc' ? cmp : -cmp;
        });
    },

    updateSortIcons() {
        $$('#telaresTable .sortable .sort-icon')
            .forEach(i => i.className = 'fa-solid fa-sort text-gray-400 sort-icon');

        if (!state.sort.column) return;

        const th = $(`#telaresTable .sortable[data-column="${state.sort.column}"]`);
        if (!th) return;

        th.querySelector('.sort-icon').className =
            state.sort.direction === 'asc'
                ? 'fa-solid fa-sort-up text-blue-600 sort-icon'
                : 'fa-solid fa-sort-down text-blue-600 sort-icon';
    },

    telares(rows) {
        const tbody = $('#telaresTable tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!rows || !rows.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="13"
                        class="px-4 py-8 text-center text-sm text-gray-500">
                        No hay datos disponibles
                    </td>
                </tr>`;
            state.selectedTelar = null;
            disable($('#btnProgramar'));
            this.updateSortIcons();
            return;
        }

        state.telaresData = rows.slice();

        const data = state.sort.column
            ? this.sorted(rows, state.sort.column, state.sort.direction)
            : rows;

        const frag = document.createDocumentFragment();

        data.forEach((r, idx) => {
            const metrosF     = parseFloat(r.metros || 0);
            const noJulio     = String(r.no_julio || '').trim();
            const noOrden     = String(r.no_orden || '').trim();
            const hasBoth     = metrosF > 0 && noJulio !== '';
            const reservado   = (noJulio !== '' && noOrden !== '');
            const tieneNoOrd  = noOrden !== '';

            const telarNo     = r.no_telar || '';
            const tipoUpper   = String(r.tipo || '').toUpperCase().trim();

            const isInMultiple = Array.isArray(state.selectedTelares)
                && state.selectedTelares.some(t =>
                    t.no_telar === telarNo &&
                    String(t.tipo || '').toUpperCase().trim() === tipoUpper
                );

            let baseBg = hasBoth ? 'bg-blue-100' : (idx % 2 === 0 ? 'bg-white' : 'bg-gray-50');
            let border = hasBoth ? 'border-l-4 border-blue-400' : '';

            if (isInMultiple) {
                baseBg = 'bg-yellow-50';
                border = 'border-l-[3px] border-yellow-500';
            }

            const tr = document.createElement('tr');
            tr.className = `selectable-row hover:bg-blue-50 cursor-pointer ${baseBg} ${border}`;
            tr.dataset.id          = r.id || ''; // ID del registro específico en tej_inventario_telares
            tr.dataset.baseBg      = baseBg;
            tr.dataset.telar       = telarNo;
            tr.dataset.tipo        = tipoUpper;
            tr.dataset.cuenta      = r.cuenta || '';
            tr.dataset.calibre     = r.calibre || '';
            tr.dataset.hilo        = String(r.hilo || '').trim();
            tr.dataset.salon       = r.salon || '';
            tr.dataset.noJulio     = noJulio;
            tr.dataset.noOrden     = noOrden;
            tr.dataset.metros      = r.metros || '';
            tr.dataset.hasBoth     = hasBoth ? 'true' : 'false';
            tr.dataset.isReservado = reservado ? 'true' : 'false';
            tr.dataset.tipoAtado   = r.tipo_atado || 'Normal';
            // Agregar fecha y turno si están disponibles
            if (r.fecha) {
                try {
                    const fechaObj = new Date(r.fecha);
                    if (!isNaN(fechaObj.getTime())) {
                        tr.dataset.fecha = fechaObj.toISOString().split('T')[0]; // Formato YYYY-MM-DD
                    }
                } catch (e) {}
            }
            if (r.turno) {
                tr.dataset.turno = String(r.turno);
            }

            const tipoAtado = r.tipo_atado || 'Normal';
            const tipoAtadoCell = ES_SUPERVISOR
                ? `<select
                        class="tipo-atado-select w-full bg-white px-2 py-1 text-xs border border-gray-300 rounded-md text-gray-900 focus:ring-2 focus:ring-blue-500"
                        data-telar="${telarNo}"
                        data-tipo="${tipoUpper}"
                    >
                        <option value="Normal" ${tipoAtado === 'Normal' ? 'selected' : ''}>Normal</option>
                        <option value="Especial" ${tipoAtado === 'Especial' ? 'selected' : ''}>Especial</option>
                   </select>`
                : `<span class="text-gray-800 text-xs font-medium">${tipoAtado}</span>`;

            const checkboxChecked  = isInMultiple ? ' checked' : '';
            const checkboxDisabled = (reservado || tieneNoOrd) ? ' disabled' : '';
            const checkboxCursor   = (reservado || tieneNoOrd)
                ? 'cursor-not-allowed opacity-50'
                : 'cursor-pointer';

            tr.innerHTML = `
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center font-bold">
                    ${telarNo}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.tipo)}">
                        ${r.tipo || '-'}
                    </span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${r.cuenta || ''}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${fmt.num(r.calibre)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${fmt.date(r.fecha)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${r.turno || ''}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${r.hilo || ''}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${fmt.num(r.metros, 0)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${r.no_julio || ''}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${r.no_orden || ''}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${tipoAtadoCell}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.salonBadge(r.salon)}">
                        ${r.salon || 'Jacquard'}
                    </span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <input type="checkbox"
                           class="telar-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 ${checkboxCursor}"
                           data-telar="${telarNo}"
                           data-tipo="${tipoUpper}"
                           ${checkboxDisabled}${checkboxChecked}>
                </td>
            `;

            frag.appendChild(tr);
        });

        tbody.appendChild(frag);
        this.updateSortIcons();
    },

    inventario(rows) {
        const tbody = $('#inventarioTable tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!state.inventarioDataOriginal.length && rows?.length) {
            state.inventarioDataOriginal = JSON.parse(JSON.stringify(rows));
        }

        const tel = state.selectedTelar;
        let data  = rows || [];

        if (state.mostrarTodoInventario) {
            data = state.inventarioDataOriginal.length
                ? state.inventarioDataOriginal
                : data;
        } else if (tel) {
            const telCuenta  = String(tel.cuenta || '').trim();
            const telTipo    = String(tel.tipo || '').toUpperCase().trim();
            const telNo      = tel.no_telar;
            const telJulio   = tel.no_julio;
            const telNoOrden = String(tel.no_orden || '').trim();
                const telTipoAtado = tel.tipo_atado || 'Normal';

            data = data.filter(r => {
                const hasTelar      = !!(r.NoTelarId && r.NoTelarId !== '');
                const invTipo       = String(r.Tipo || '').toUpperCase().trim();
                const inventBatchId = String(r.InventBatchId || '').trim();

                // Si el telar tiene no_orden, el lote debe coincidir
                if (telNoOrden && inventBatchId !== telNoOrden) return false;

                // Misma cuenta (InventSizeId inicia con cuenta del telar)
                if (telCuenta && !matchCuenta(telCuenta, r.InventSizeId)) return false;

                // Si el telar ya tiene No. Julio, mostrar sólo esa pieza
                if (telJulio) return (r.InventSerialId || '') === telJulio;

                // Ocultar piezas asignadas a otro telar
                if (!tel.is_reservado && hasTelar && r.NoTelarId !== telNo) return false;

                // Coincidencia por Tipo (Rizo/Pie)
                if (telTipo && invTipo && invTipo !== telTipo) return false;

                return true;
            });
        }

        state.inventarioData = data;

        if (!data.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="12"
                        class="px-4 py-8 text-center text-sm text-gray-500">
                        <i class="fa-solid fa-box-open w-12 h-12 text-gray-400 mb-2"></i>
                        No hay datos de inventario disponible por el momento
                    </td>
                </tr>`;
            return;
        }

        const frag = document.createDocumentFragment();

        data.forEach(r => {
            const hasTelar = !!(r.NoTelarId && r.NoTelarId !== '');
            const tr       = document.createElement('tr');

            tr.className = hasTelar
                ? 'bg-green-100 selectable-row-inventario cursor-not-allowed opacity-75'
                : 'hover:bg-orange-50 selectable-row-inventario cursor-pointer';

            tr.dataset.disabled        = hasTelar ? 'true' : 'false';
            tr.dataset.tipo            = r.Tipo || '';
            tr.dataset.itemId          = r.ItemId || '';
            tr.dataset.configId        = r.ConfigId || '';
            tr.dataset.inventSizeId    = r.InventSizeId || '';
            tr.dataset.inventColorId   = r.InventColorId || '';
            tr.dataset.inventLocationId= r.InventLocationId || '';
            tr.dataset.inventBatchId   = r.InventBatchId || '';
            tr.dataset.wmsLocationId   = r.WMSLocationId || '';
            tr.dataset.inventSerialId  = r.InventSerialId || '';
            tr.dataset.noTelarId       = r.NoTelarId || '';
            tr.dataset.metros          = r.Metros || '';
            tr.dataset.numJulio        = r.InventSerialId || '';

            const kilos  = fmt.num(r.InventQty, 0);
            const metros = fmt.num(r.Metros, 0);

            tr.innerHTML = `
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.ItemId || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.Tipo)}">
                        ${r.Tipo || ''}
                    </span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.ConfigId || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.InventSizeId || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.InventColorId || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.InventBatchId || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.WMSLocationId || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${r.InventSerialId || ''}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${fmt.date(r.ProdDate)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${metros}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${kilos}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center font-medium">${r.NoTelarId || ''}</td>
            `;

            frag.appendChild(tr);
        });

        tbody.appendChild(frag);
        selection.updateFiltroButton();
    }
};

/* ---------- Selección ---------- */
const selection = {
    clearVisualRow(row) {
        if (!row) return;

        row.classList.remove(
            'is-selected',
            'bg-blue-500',
            'text-white',
            'bg-green-500',
            'bg-yellow-50'
        );

        row.style.removeProperty('background-color');
        row.style.removeProperty('color');
        row.style.removeProperty('border-left');

        row.querySelectorAll('td').forEach(td => {
            td.classList.remove('text-white');
            td.style.removeProperty('color');
        });

        const hasBoth = row.dataset.hasBoth === 'true';
        const base    = row.dataset.baseBg ||
                        (row.className.includes('bg-gray-50') ? 'bg-gray-50' : 'bg-white');

        row.className = `selectable-row hover:bg-blue-50 cursor-pointer ${
            hasBoth ? 'bg-blue-100 border-l-4 border-blue-400' : base
        }`;
    },

    clearVisualInventario(row) {
        if (!row) return;

        row.classList.remove('is-selected', 'bg-green-500', 'text-white');
        row.style.removeProperty('background-color');
        row.style.removeProperty('color');

        row.querySelectorAll('td').forEach(td => {
            td.classList.remove('text-white');
            td.style.removeProperty('color');
        });

        row.className = row.dataset.disabled === 'true'
            ? 'bg-green-100 selectable-row-inventario cursor-not-allowed opacity-75'
            : 'hover:bg-orange-50 selectable-row-inventario cursor-pointer';
    },

    clear() {
        this.clearVisualRow($('#telaresTable .selectable-row.is-selected'));
        this.clearVisualInventario($('#inventarioTable .selectable-row-inventario.is-selected'));

        state.selectedTelar      = null;
        state.selectedInventario = null;
        state.selectedTelares    = [];
        state.mostrarTodoInventario = false;

        disable($('#btnProgramar'));
        disable($('#btnReservar'));
        disable($('#btnLiberarTelar'));

        this.updateFiltroButton();

        if (state.inventarioDataOriginal.length) {
            render.inventario(state.inventarioDataOriginal);
        }
    },

    updateFiltroButton() {
        const btn = $('#btnQuitarFiltroInventario');
        if (!btn) return;

        if (state.selectedTelar) {
            btn.classList.remove('hidden');

            const icon = btn.querySelector('i');
            const text = btn.querySelector('span');

            if (state.mostrarTodoInventario) {
                if (icon) icon.className = 'fa-solid fa-filter';
                if (text) text.textContent = 'Aplicar Filtro';
                btn.title = 'Aplicar filtro y mostrar sólo registros del telar seleccionado';
            } else {
                if (icon) icon.className = 'fa-solid fa-filter-circle-xmark';
                if (text) text.textContent = 'Quitar Filtro';
                btn.title = 'Quitar filtro y mostrar todos los registros';
            }
        } else {
            btn.classList.add('hidden');
        }
    },

    toggleTelarCheckbox(row, checked) {
        const cb        = row.querySelector('.telar-checkbox');
        const tipoUpper = String(row.dataset.tipo || '').toUpperCase().trim();

        const item = {
            no_telar: row.dataset.telar || '',
            tipo:     normalizeTipo(tipoUpper),
            calibre:  row.dataset.calibre,
            hilo:     row.dataset.hilo,
            salon:    row.dataset.salon,
            cuenta:   row.dataset.cuenta || '',
            no_julio: row.dataset.noJulio || '',
            no_orden: row.dataset.noOrden || '',
            is_reservado: row.dataset.isReservado === 'true'
        };

        if (item.is_reservado && checked) {
            toast('info', 'Telar reservado', 'No se puede usar en selección múltiple');
            if (cb) cb.checked = false;
            return;
        }

        if (hasNoOrden(item) && checked) {
            toast('info', 'Telar con orden', 'No se puede usar en selección múltiple');
            if (cb) cb.checked = false;
            return;
        }

        if (!Array.isArray(state.selectedTelares)) {
            state.selectedTelares = [];
        }

        if (checked) {
            if (state.selectedTelares.length) {
                const ref = state.selectedTelares[0];

                if (isReservado(ref)) {
                    toast('info', 'Telar reservado en selección',
                          'No se puede agregar más telares a una selección que contiene telares reservados',
                          3000);
                    if (cb) cb.checked = false;
                    return;
                }

                if (hasNoOrden(ref)) {
                    toast('info', 'Telar con orden en selección',
                          'No se puede agregar más telares a una selección que contiene telares con orden',
                          3000);
                    if (cb) cb.checked = false;
                    return;
                }

                if (!sameGroup(item, ref)) {
                    toast('warning', 'Selección incompatible',
                          'Sólo puedes seleccionar telares con el mismo Tipo, Calibre, Hilo y Salón. La cuenta puede variar.',
                          3000);
                    if (cb) cb.checked = false;
                    return;
                }
            }

            const exists = state.selectedTelares.some(
                t => t.no_telar === item.no_telar && eq.str(t.tipo, item.tipo)
            );

            if (!exists) state.selectedTelares.push(item);

            row.classList.add('bg-yellow-50');
            row.style.setProperty('border-left', '3px solid #eab308', 'important');
        } else {
            state.selectedTelares = state.selectedTelares.filter(
                t => !(t.no_telar === item.no_telar && eq.str(t.tipo, item.tipo))
            );
            row.classList.remove('bg-yellow-50');
            row.style.removeProperty('border-left');
        }

        this.validateButtons();
    },

    applyTelar(row) {
        const prev = $('#telaresTable .selectable-row.is-selected');
        if (prev && prev !== row) this.clearVisualRow(prev);

        const hasBoth = row.dataset.hasBoth === 'true';

        row.className = `selectable-row is-selected cursor-pointer ${
            hasBoth ? 'border-l-4 border-blue-300' : ''
        }`;

        row.classList.add('bg-blue-500', 'text-white');
        row.style.setProperty('background-color', '#3b82f6', 'important');
        row.style.setProperty('color', '#fff', 'important');

        row.querySelectorAll('td').forEach(td => {
            td.classList.add('text-white');
            td.style.setProperty('color', '#fff', 'important');
        });

        const tipoOk = normalizeTipo(row.dataset.tipo);

        state.selectedTelar = {
            id:         row.dataset.id || null, // ID del registro específico en tej_inventario_telares
            no_telar:   row.dataset.telar || null,
            tipo:       tipoOk,
            cuenta:     row.dataset.cuenta || '',
            salon:      row.dataset.salon || '',
            calibre:    row.dataset.calibre || '',
            hilo:       row.dataset.hilo || '',
            no_julio:   row.dataset.noJulio || '',
            no_orden:   row.dataset.noOrden || '',
            fecha:      row.dataset.fecha || '',
            turno:      row.dataset.turno || '',
            tipo_atado: row.querySelector('.tipo-atado-select')?.value || 'Normal',
            is_reservado: row.dataset.isReservado === 'true'
        };

        this.validateButtons();
        this.updateFiltroButton();

        if (state.inventarioDataOriginal.length) {
            state.mostrarTodoInventario = false;
            render.inventario(state.inventarioDataOriginal);

            if (state.selectedTelar.no_julio) {
                setTimeout(() => {
                    const match = $$('#inventarioTable .selectable-row-inventario')
                        .find(r => (r.dataset.inventSerialId || '') === state.selectedTelar.no_julio);
                    if (match) this.applyInventario(match);
                }, 100);
            }
        }
    },

    applyInventario(row) {
        if (state.selectedTelar?.tipo) {
            const telTipo = String(state.selectedTelar.tipo || '').toUpperCase().trim();
            const invTipo = String(row.dataset.tipo || '').toUpperCase().trim();
            if (telTipo && invTipo && telTipo !== invTipo) {
                toast('warning', 'Tipo distinto', 'El tipo de la pieza no coincide con el telar', 1800);
                return;
            }
        }

        const prev = $('#inventarioTable .selectable-row-inventario.is-selected');
        if (prev && prev !== row) this.clearVisualInventario(prev);

        row.classList.add('is-selected', 'bg-green-500', 'text-white');
        row.style.setProperty('background-color', '#10b981', 'important');
        row.style.setProperty('color', '#fff', 'important');

        row.querySelectorAll('td').forEach(td => {
            td.classList.add('text-white');
            td.style.setProperty('color', '#fff', 'important');
        });

        state.selectedInventario = {
            itemId:          row.dataset.itemId || '',
            configId:        row.dataset.configId || '',
            inventSizeId:    row.dataset.inventSizeId || '',
            inventColorId:   row.dataset.inventColorId || '',
            inventLocationId:row.dataset.inventLocationId || '',
            inventBatchId:   row.dataset.inventBatchId || '',
            wmsLocationId:   row.dataset.wmsLocationId || '',
            inventSerialId:  row.dataset.inventSerialId || '',
            metros:          parseFloat(row.dataset.metros || 0),
            numJulio:        row.dataset.numJulio || '',
            tipo:            row.dataset.tipo || '',
            data: state.inventarioData.find(
                i => i.ItemId === row.dataset.itemId &&
                     i.InventSerialId === row.dataset.inventSerialId
            )
        };

        this.validateButtons();
    },

    validateButtons() {
        const btnProgramar     = $('#btnProgramar');
        const btnReservar      = $('#btnReservar');
        const btnLiberarTelar  = $('#btnLiberarTelar');

        // Liberar telar: sólo telar individual y reservado
        if (state.selectedTelar && isReservado(state.selectedTelar)) {
            disable(btnReservar, true);
            disable(btnProgramar, true);
            disable(btnLiberarTelar, false);
            return;
        }

        disable(btnLiberarTelar, true);

        // Reservar: telar + inventario con misma cuenta
        const tiposMatch = state.selectedTelar && state.selectedInventario
            ? eq.str(state.selectedTelar.tipo, state.selectedInventario.tipo || state.selectedInventario.data?.Tipo)
            : false;

        const canReservar = !!(state.selectedTelar && state.selectedInventario && tiposMatch);
        disable(btnReservar, !canReservar);

        const hasMultiple   = Array.isArray(state.selectedTelares) && state.selectedTelares.length > 0;
        const hasIndividual = !!(state.selectedTelar && state.selectedTelar.no_telar);

        // Programar (prioridad selección múltiple)
        if (hasMultiple) {
            const hasReserved = state.selectedTelares.some(isReservado);
            const hasOrden    = state.selectedTelares.some(hasNoOrden);
            disable(btnProgramar, hasReserved || hasOrden);
            return;
        }

        if (hasIndividual && state.selectedTelar) {
            const tel = state.selectedTelar;
            disable(btnProgramar, isReservado(tel) || hasNoOrden(tel));
            return;
        }

        disable(btnProgramar, true);
    }
};

/* ---------- Filtros ---------- */
const filters = {
    updateBadge() {
        const total = state.filters.telares.length + state.filters.inventario.length;
        const badge = $('#filterCount');

        if (!badge) return;

        badge.textContent = total;
        badge.classList.toggle('hidden', total === 0);
    },

    filterLocal(data, list) {
        if (!list?.length || !data?.length) return data;

        return data.filter(item => list.every(f => {
            const col = f.columna || f.column;
            const val = String(f.valor ?? f.value ?? '').toLowerCase().trim();

            if (!col || !val) return true;

            const itemVal = item[col];

            if (col === 'NoTelarId') {
                if (['null','vacío','vacio','disponible',''].includes(val)) {
                    return !itemVal;
                }
                return String(itemVal || '').toLowerCase().includes(val);
            }

            if (itemVal == null || itemVal === '') return false;

            if (col === 'InventSizeId') {
                return String(itemVal).toLowerCase().startsWith(val);
            }

            if (['fecha','ProdDate'].includes(col)) {
                try {
                    const dItem = new Date(itemVal);
                    const dFil  = new Date(val);
                    if (!Number.isNaN(dItem) && !Number.isNaN(dFil)) {
                        return dItem.toDateString() === dFil.toDateString();
                    }
                } catch (e) {}
            }

            if (['calibre','metros','InventQty','Metros'].includes(col)) {
                const a = parseFloat(itemVal);
                const b = parseFloat(val);
                if (!Number.isNaN(a) && !Number.isNaN(b)) {
                    return Math.abs(a - b) < 0.001 ||
                        String(itemVal).toLowerCase().includes(val);
                }
            }

            return String(itemVal).toLowerCase().includes(val);
        }));
    },

    async openModal() {
        if (!state.columns.telares.length) {
            try {
                const resp = await http.get(`${API.columnOptions}?table_type=telares`);
                state.columns.telares = resp.columns || [];
            } catch (e) {}
        }

        if (!state.columns.inventario.length) {
            try {
                const resp = await http.get(`${API.columnOptions}?table_type=inventario`);
                state.columns.inventario = resp.columns || [];
            } catch (e) {}
        }

        const options = type =>
            (state.columns[type] || [])
                .map(c => `<option value="${c.field}">${c.label}</option>`)
                .join('');

        const row = (type, idx, col = '', val = '') => `
            <div class="filter-row" data-idx="${idx}">
                <div class="grid grid-cols-2 gap-3 p-3 rounded-md bg-gray-50 border border-gray-200 mb-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Columna</label>
                        <select class="filter-col w-full px-2 py-2 border border-gray-300 rounded-md">
                            ${options(type)}
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Valor</label>
                        <input type="text"
                               class="filter-val w-full px-2 py-2 border border-gray-300 rounded-md"
                               value="${val}">
                    </div>
                    <div class="col-span-2 flex justify-end">
                        <button type="button"
                                class="btn-rm px-3 py-1.5 bg-red-500 text-white rounded-md">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>`;

        const renderRows = (type, list) =>
            list.map(f => row(type, f.idx, f.column, f.value)).join('');

        const html = `
            <div id="swalFilterContainer" class="text-left">
                <div class="mb-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tabla</label>
                    <select id="swalTableSel"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="telares" selected>Programación de Requerimientos</option>
                        <option value="inventario">Inventario Disponible</option>
                    </select>
                </div>
                <div id="swalFilters" class="max-h-[420px] overflow-y-auto">
                    ${
                        renderRows(
                            'telares',
                            state.filters.telares.length
                                ? state.filters.telares
                                : [{ table: 'telares', column: '', value: '', idx: Date.now() }]
                        )
                    }
                </div>
                <button type="button" id="swalAdd"
                        class="w-full mt-3 px-3 py-2 border-2 border-dashed border-gray-300 rounded-md text-gray-500">
                    <i class="fa-solid fa-plus mr-1"></i> Agregar filtro
                </button>
            </div>`;

        const result = await Swal.fire({
            title: 'Filtrar Tablas',
            html,
            width: '700px',
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#9333ea',
            cancelButtonColor: '#6b7280',
            didOpen: () => {
                const container   = $('#swalFilterContainer');
                const addBtn      = $('#swalAdd');
                const selectTable = $('#swalTableSel');

                const bindRemove = () => {
                    container.querySelectorAll('.btn-rm').forEach(btn => {
                        btn.onclick = e =>
                            e.currentTarget.closest('.filter-row')?.remove();
                    });
                };

                bindRemove();

                addBtn.onclick = () => {
                    $('#swalFilters')
                        .insertAdjacentHTML('beforeend', row(selectTable.value, Date.now()));
                    bindRemove();
                };

                selectTable.onchange = e => {
                    const type = e.target.value;

                    const list = (state.filters[type] && state.filters[type].length)
                        ? state.filters[type]
                        : [{ table: type, column: '', value: '', idx: Date.now() }];

                    $('#swalFilters').innerHTML = renderRows(type, list);
                    bindRemove();
                };
            },
            preConfirm: () => {
                const type = $('#swalTableSel').value;
                const list = $$('#swalFilters .filter-row').map(div => {
                    const col = div.querySelector('.filter-col')?.value?.trim() || '';
                    const val = div.querySelector('.filter-val')?.value?.trim() || '';
                    return (col && val) ? { columna: col, valor: val } : null;
                }).filter(Boolean);

                if (!list.length) {
                    Swal.showValidationMessage('Agrega al menos un filtro válido');
                    return false;
                }

                return { type, filters: list };
            }
        });

        if (!result.isConfirmed) return;

        const { type, filters: list } = result.value;

        const original = (type === 'telares')
            ? (state.telaresDataOriginal.length
                ? state.telaresDataOriginal
                : state.telaresData)
            : (state.inventarioDataOriginal.length
                ? state.inventarioDataOriginal
                : state.inventarioData);

        const filtered = filters.filterLocal(original, list);
        (type === 'telares' ? render.telares : render.inventario)(filtered);

        state.filters[type] = list.map((f, i) => ({
            table:  type,
            column: f.columna,
            value:  f.valor,
            idx:    Date.now() + i
        }));

        filters.updateBadge();
        toast('success', `${list.length} filtro(s) aplicados`, '', 2000);
    },

    reset() {
        if (state.telaresDataOriginal.length) {
            render.telares(state.telaresDataOriginal);
        }

        if (state.inventarioDataOriginal.length) {
            render.inventario(state.inventarioDataOriginal);
        }

        state.filters = { telares: [], inventario: [] };
        filters.updateBadge();

        toast('success', 'Filtros restablecidos', '', 1500);
    }
};

/* ---------- Sorting ---------- */
const sorting = {
    toggle(col) {
        state.sort = (state.sort.column === col)
            ? { column: col, direction: state.sort.direction === 'asc' ? 'desc' : 'asc' }
            : { column: col, direction: 'asc' };

        render.telares(state.telaresData);
    },
    bind() {
        $('#telaresTable thead')?.addEventListener('click', e => {
            const th = e.target.closest('.sortable');
            if (!th) return;

            const col = th.dataset.column;
            if (!col) return;

            sorting.toggle(col);
        });
    }
};

/* ---------- Acciones ---------- */
const actions = {
    async programar() {
        const multiple = Array.isArray(state.selectedTelares) && state.selectedTelares.length > 0;
        const tel      = state.selectedTelar;

        // Selección múltiple => programar requerimientos
        if (multiple) {
            const hasOrden = state.selectedTelares.some(hasNoOrden);
            if (hasOrden) {
                toast('info', 'Telar con orden', 'No se puede programar un telar que ya tiene No. Orden');
                return;
            }

            const telaresJson = encodeURIComponent(JSON.stringify(state.selectedTelares));
            const url = '{{ route("programa.urd.eng.programacion.requerimientos") }}?telares=' + telaresJson;

            sessionStorage.setItem('selectedTelares', JSON.stringify(state.selectedTelares));
            window.location.href = url;
            return;
        }

        // Selección individual
        if (tel && tel.no_telar && !isReservado(tel)) {
            if (hasNoOrden(tel)) {
                toast('info', 'Telar con orden', 'No se puede programar un telar que ya tiene No. Orden');
                return;
            }

            const base = state.telaresDataOriginal.length
                ? state.telaresDataOriginal
                : state.telaresData;

            const telarCompleto = base.find(t => {
                const telarMatch = String(t.no_telar || '') === String(tel.no_telar || '');
                const tipoMatch  = String(t.tipo || '').toUpperCase().trim() ===
                                   String(tel.tipo || '').toUpperCase().trim();
                return telarMatch && tipoMatch;
            });

            const telarArray = [{
                no_telar: tel.no_telar,
                tipo:     tel.tipo,
                cuenta:   tel.cuenta || (telarCompleto ? (telarCompleto.cuenta || '') : ''),
                salon:    tel.salon  || (telarCompleto ? (telarCompleto.salon  || '') : ''),
                calibre:  tel.calibre|| (telarCompleto ? (telarCompleto.calibre|| '') : ''),
                hilo:     tel.hilo   || (telarCompleto ? (telarCompleto.hilo   || '') : ''),
                tipo_atado: tel.tipo_atado || (telarCompleto ? (telarCompleto.tipo_atado || 'Normal') : 'Normal')
            }];

            const telaresJson = encodeURIComponent(JSON.stringify(telarArray));
            const url = '{{ route("programa.urd.eng.programacion.requerimientos") }}?telares=' + telaresJson;

            sessionStorage.setItem('selectedTelares', JSON.stringify(telarArray));
            window.location.href = url;
            return;
        }

        if (!tel?.no_telar) {
            toast('warning', 'Selecciona un telar');
            return;
        }

        if (isReservado(tel)) {
            toast('info', 'Telar reservado', 'No se puede programar un telar reservado');
            return;
        }

        if (hasNoOrden(tel)) {
            toast('info', 'Telar con orden', 'No se puede programar un telar que ya tiene No. Orden');
        }
    },

    async liberarTelar() {
        const tel = state.selectedTelar;

        if (!tel?.no_telar) {
            Swal.fire('Aviso', 'Selecciona un telar primero', 'warning');
            return;
        }

        if (!isReservado(tel)) {
            Swal.fire('Aviso', 'Este telar no está reservado', 'warning');
            return;
        }

        const ok = await Swal.fire({
            title: '¿Liberar telar?',
            icon:  'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, liberar',
            confirmButtonColor: '#dc2626'
        }).then(r => r.isConfirmed);

        if (!ok) return;

        show($('#loaderInventario'));
        show($('#loaderTelares'));

        try {
            const resp = await http.post(API.liberarTelar, {
                no_telar: tel.no_telar,
                tipo:     tel.tipo
            });

            if (resp.success) {
                const [inv, telrs] = await Promise.all([
                    http.get(API.inventarioDisponibleGet),
                    http.get(API.inventarioTelares)
                ]);

                if (inv?.data) {
                    state.inventarioDataOriginal = JSON.parse(JSON.stringify(inv.data));
                    render.inventario(inv.data);
                    selection.updateFiltroButton();
                }

                if (telrs?.data) {
                    state.telaresDataOriginal = JSON.parse(JSON.stringify(telrs.data));
                    state.telaresData         = telrs.data;
                    render.telares(telrs.data);
                }

                toast('success', resp.message || 'Telar liberado', '', 3000);
                selection.clear();
            }
        } catch (e) {
            Swal.fire('Error', e.message || 'Error al liberar', 'error');
        } finally {
            hide($('#loaderInventario'));
            hide($('#loaderTelares'));
        }
    },

    async reservar() {
        const tel = state.selectedTelar;

        if (!tel?.no_telar) {
            Swal.fire('Aviso', 'Selecciona un telar', 'warning');
            return;
        }

        // Validar que el telar tenga ID (requerido para identificar el registro específico)
        if (!tel?.id) {
            console.error('Error: El telar seleccionado no tiene ID', tel);
            Swal.fire('Error', 'No se pudo identificar el registro del telar. Por favor, selecciona el telar nuevamente.', 'error');
            return;
        }

        if (isReservado(tel)) {
            Swal.fire('Aviso', 'Este telar ya está reservado', 'warning');
            return;
        }

        if (!state.selectedInventario?.data) {
            Swal.fire('Aviso', 'Selecciona una fila de inventario', 'warning');
            return;
        }

        // Validar que el tipo coincida (Rizo/Pie)
        const invTipo = String(state.selectedInventario.data?.Tipo || state.selectedInventario.tipo || '').trim();
        const telTipo = String(tel.tipo || '').trim();
        if (invTipo && telTipo && !eq.str(invTipo, telTipo)) {
            Swal.fire('Advertencia', 'El tipo de la pieza no coincide con el telar.', 'warning');
            return;
        }

        if (state.selectedInventario.data.NoTelarId) {
            Swal.fire('Aviso', 'Esa pieza ya tiene telar asignado', 'warning');
            return;
        }

        const ok = await Swal.fire({
            title: '¿Reservar pieza?',
            text:  `Reservar para telar ${tel.no_telar} (${tel.tipo || 'N/A'})`,
            icon:  'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, reservar'
        }).then(r => r.isConfirmed);

        if (!ok) return;

        show($('#loaderInventario'));
        show($('#loaderTelares'));

        try {
            const lote      = state.selectedInventario.inventBatchId ||
                              state.selectedInventario.data?.InventBatchId || '';
            const localidad = state.selectedInventario.wmsLocationId ||
                              state.selectedInventario.data?.WMSLocationId || '';

            // Actualizar telar - SOLO el registro específico que se está reservando
            await http.post(API.actualizarTelar, {
                id:        tel.id, // ID del registro específico (REQUERIDO para actualizar solo ese registro)
                no_telar:  tel.no_telar,
                tipo:      tel.tipo,
                metros:    state.selectedInventario.metros || 0,
                no_julio:  state.selectedInventario.numJulio || '',
                no_orden:  lote,
                localidad: localidad
            });

            const tTipo = normalizeTipo(tel.tipo).toUpperCase();

            const ix = state.telaresData.findIndex(x =>
                x.no_telar === tel.no_telar &&
                String(x.tipo || '').toUpperCase().trim() === tTipo
            );

            if (ix > -1) {
                state.telaresData[ix].metros   = state.selectedInventario.metros || 0;
                state.telaresData[ix].no_julio = state.selectedInventario.numJulio || '';
                state.telaresData[ix].no_orden = lote;

                const jx = state.telaresDataOriginal.findIndex(x =>
                    x.no_telar === tel.no_telar &&
                    String(x.tipo || '').toUpperCase().trim() === tTipo
                );

                if (jx > -1) {
                    Object.assign(state.telaresDataOriginal[jx], {
                        metros:   state.telaresData[ix].metros,
                        no_julio: state.telaresData[ix].no_julio,
                        no_orden: lote
                    });
                }

                render.telares(state.telaresData);
            }

            // Reservar inventario
            const it = state.selectedInventario.data;

            // Validar que el ID esté presente antes de construir el payload
            if (!tel.id) {
                console.error('Error: tel.id no está presente', tel);
                Swal.fire('Error', 'No se pudo identificar el registro del telar. Por favor, selecciona el telar nuevamente.', 'error');
                return;
            }

            const payload = {
                NoTelarId:       tel.no_telar,
                SalonTejidoId:   tel.salon || null,
                ItemId:          it.ItemId,
                ConfigId:        it.ConfigId || null,
                InventSizeId:    it.InventSizeId || null,
                InventColorId:   it.InventColorId || null,
                InventLocationId:it.InventLocationId || null,
                InventBatchId:   it.InventBatchId || null,
                WMSLocationId:   it.WMSLocationId || null,
                InventSerialId:  it.InventSerialId || null,
                Tipo:            normalizeTipo(tel.tipo),
                Metros:          it.Metros || null,
                InventQty:       it.InventQty || null,
                ProdDate:        it.ProdDate || null,
                fecha:           tel.fecha || null,
                turno:           tel.turno || null,
                tej_inventario_telares_id: parseInt(tel.id, 10) // ID del registro específico (REQUERIDO - convertir a entero)
            };

            await http.post(API.reservarInventario, payload);

            const [inv, telrs] = await Promise.all([
                http.get(API.inventarioDisponibleGet),
                http.get(API.inventarioTelares)
            ]);

            if (inv?.data) {
                state.inventarioDataOriginal = JSON.parse(JSON.stringify(inv.data));
                render.inventario(inv.data);
                selection.updateFiltroButton();
            }

            if (telrs?.data) {
                state.telaresDataOriginal = JSON.parse(JSON.stringify(telrs.data));
                state.telaresData         = telrs.data;
                render.telares(telrs.data);

                setTimeout(() => {
                    const row = $$('#telaresTable .selectable-row')
                        .find(r =>
                            r.dataset.telar === tel.no_telar &&
                            String(r.dataset.tipo || '').toUpperCase().trim() === tTipo
                        );
                    if (row) selection.applyTelar(row);
                }, 100);
            }

            toast('success', 'Pieza reservada', '', 2500);
        } catch (e) {
            Swal.fire('Error', e.message || 'Error al reservar', 'error');
        } finally {
            hide($('#loaderInventario'));
            hide($('#loaderTelares'));
        }
    }
};

/* ---------- Init ---------- */
document.addEventListener('DOMContentLoaded', async () => {
    sorting.bind();
    render.telares(state.telaresData);

    // Cargar inventario inicial
    show($('#loaderInventario'));
    try {
        const { data } = await http.get(API.inventarioDisponibleGet);
        state.inventarioDataOriginal = JSON.parse(JSON.stringify(data || []));
        render.inventario(data || []);
        selection.validateButtons();
        selection.updateFiltroButton();
    } catch (e) {
        render.inventario([]);
        disable($('#btnReservar'));
    } finally {
        hide($('#loaderInventario'));
    }

    // Checkboxes: selección múltiple
    $('#telaresTable tbody')?.addEventListener('change', e => {
        const target = e.target;
        if (target.classList.contains('tipo-atado-select')) {
            if (!ES_SUPERVISOR) return;
            const row = target.closest('.selectable-row');
            const telar = row?.dataset.telar || '';
            const tipo  = row?.dataset.tipo || '';
            const nuevo = target.value || 'Normal';

            if (!telar) return;

            // Actualizar visualmente datos locales
            if (row && row.classList.contains('is-selected')) {
                state.selectedTelar = {
                    ...(state.selectedTelar || {}),
                    tipo_atado: nuevo
                };
            }

            // Enviar al backend
            http.post(API.actualizarTelar, {
                no_telar: telar,
                tipo:     normalizeTipo(tipo),
                tipo_atado: nuevo
            }).catch(err => {
                toast('error', 'No se pudo actualizar tipo de atado', err.message || '');
                // Revertir select si falla
                target.value = (row?.dataset.tipoAtadoPrev || 'Normal');
            }).then(() => {
                toast('success', 'Tipo de atado actualizado');
            });

            row.dataset.tipoAtadoPrev = nuevo;
            return;
        }

        if (!target.classList.contains('telar-checkbox')) return;

        e.stopPropagation();

        if (target.disabled) {
            target.checked = false;
            return;
        }

        const row = target.closest('.selectable-row');
        if (!row) return;

        selection.toggleTelarCheckbox(row, target.checked);
    });

    // Click filas telares: selección individual
    $('#telaresTable tbody')?.addEventListener('click', e => {
        if (e.target.closest('button,a') ||
            e.target.type === 'checkbox' ||
            e.target.closest('.telar-checkbox')) {
            return;
        }

        const row = e.target.closest('.selectable-row');
        if (!row) return;

        e.preventDefault();
        e.stopPropagation();

        if (row.classList.contains('is-selected')) {
            selection.clear();
        } else {
            selection.applyTelar(row);
        }
    });

    // Click filas inventario
    $('#inventarioTable tbody')?.addEventListener('click', e => {
        if (e.target.closest('button,a')) return;

        const row = e.target.closest('.selectable-row-inventario');
        if (!row) return;

        if (row.dataset.disabled === 'true') {
            toast('info', 'Pieza ya reservada');
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        selection.applyInventario(row);
    });

    // Topbar
    $('#btnOpenFilters')?.addEventListener('click', filters.openModal);
    $('#btnResetFiltros')?.addEventListener('click', filters.reset);
    $('#btnReloadTelares')?.addEventListener('click', filters.reset);

    // Botón quitar/aplicar filtro inventario
    $('#btnQuitarFiltroInventario')?.addEventListener('click', () => {
        if (state.mostrarTodoInventario) {
            // Estaba mostrando todo => volver a filtrar por telar
            state.mostrarTodoInventario = false;
            if (state.inventarioDataOriginal.length && state.selectedTelar) {
                render.inventario(state.inventarioDataOriginal);
            }
        } else {
            // Mostrar todos los registros
            state.mostrarTodoInventario = true;
            if (state.inventarioDataOriginal.length) {
                render.inventario(state.inventarioDataOriginal);
            }

            // Limpiar selección de inventario
            selection.clearVisualInventario(
                $('#inventarioTable .selectable-row-inventario.is-selected')
            );
            state.selectedInventario = null;
            selection.validateButtons();
        }

        selection.updateFiltroButton();

        toast(
            'info',
            state.mostrarTodoInventario
                ? 'Mostrando todos los registros'
                : 'Filtro aplicado',
            '',
            2000
        );
    });

    // Acciones
    $('#btnProgramar')?.addEventListener('click', actions.programar);
    $('#btnReservar')?.addEventListener('click', actions.reservar);
    $('#btnLiberarTelar')?.addEventListener('click', actions.liberarTelar);
});
</script>
@endsection
