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
</div>
@endsection

@section('content')

@php
    $esSupervisor = (bool)($esSupervisor ?? false);
@endphp

<div class="w-full">

    {{-- =================== Tabla: ProgramaciÃ³n (telares) =================== --}}
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
                    ['key'=>'no_julio',  'label'=>'Julio'],
                    ['key'=>'no_orden',  'label'=>'Orden'],
                    ['key'=>'estado',    'label'=>'Estado'],
                    ['key'=>'tipo_atado','label'=>'Atado'],
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
                                        $isReservado  = (bool)($t['reservado'] ?? false);
                                        $isProgramado = (bool)($t['programado'] ?? false);
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
                                                : 'Selección multiple (misma cuenta/atributos)');
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
                                        data-fecha="{{ (!empty($t['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}/', trim($t['fecha']))) ? substr(trim($t['fecha']), 0, 10) : '' }}"
                                        data-turno="{{ $t['turno'] ?? '' }}"
                                        data-has-both="{{ $hasBoth ? 'true' : 'false' }}"
                                        data-is-reservado="{{ $isReservado ? 'true' : 'false' }}"
                                        data-is-programado="{{ $isProgramado ? 'true' : 'false' }}">
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
                                            @php
                                                $fechaVal = $t['fecha'] ?? '';
                                                if (!empty($fechaVal) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($fechaVal), $mFecha)) {
                                                    $fechaDisplay = \Carbon\Carbon::createFromFormat('Y-m-d', $mFecha[1].'-'.$mFecha[2].'-'.$mFecha[3])->format('d-M-Y');
                                                } else {
                                                    $fechaDisplay = $fechaVal ? \Carbon\Carbon::parse($fechaVal)->format('d-M-Y') : '';
                                                }
                                            @endphp
                                            {{ $fechaDisplay }}
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
                                            @if($isReservado)
                                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">Reservado</span>
                                            @elseif($isProgramado)
                                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-700">Programado</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700">Libre</span>
                                            @endif
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
        <div class="bg-blue-500 px-4 flex justify-between items-center gap-2">
            <h2 class="text-lg font-bold text-white text-center flex-1">Inventario Disponible</h2>
            <button type="button"
                    id="btnQuitarFiltroInventario"
                    class="hidden flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 text-white text-sm font-medium transition-colors"
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
                                @php
                                    $inventarioHeaders = [
                                        ['key' => 'ItemId', 'label' => 'ArtÃ­culo'],
                                        ['key' => 'Tipo', 'label' => 'Tipo'],
                                        ['key' => 'ConfigId', 'label' => 'Fibra'],
                                        ['key' => 'InventSizeId', 'label' => 'Cuenta'],
                                        ['key' => 'InventColorId', 'label' => 'Cod Color'],
                                        ['key' => 'InventBatchId', 'label' => 'Lote'],
                                        ['key' => 'WMSLocationId', 'label' => 'Localidad'],
                                        ['key' => 'InventSerialId', 'label' => 'No. Julio'],
                                        ['key' => 'ProdDate', 'label' => 'Fecha'],
                                        ['key' => 'Metros', 'label' => 'Metros'],
                                        ['key' => 'InventQty', 'label' => 'Kilos'],
                                        ['key' => 'NoTelarId', 'label' => 'Telar'],
                                    ];
                                @endphp
                                @foreach($inventarioHeaders as $h)
                                    <th class="px-3 py-2 text-center text-xs font-medium tracking-wider whitespace-nowrap sortable-inventario"
                                        data-column="{{ $h['key'] }}">
                                        <button type="button"
                                                class="w-full flex items-center justify-center gap-2 cursor-pointer">
                                            <span>{{ $h['label'] }}</span>
                                            <i class="fa-solid fa-sort text-gray-400 sort-icon-inventario"></i>
                                        </button>
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

<div id="tableContextMenu"
     class="hidden fixed z-50 min-w-[220px] bg-white border border-gray-200 rounded-lg shadow-lg p-1">
    <button type="button" data-action="filter-column" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md">
        Filtrar columna
    </button>
    <button type="button" data-action="clear-column-filter" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md">
        Quitar filtro de columna
    </button>
    <button type="button" data-action="clear-table-filters" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md">
        Quitar filtros de tabla
    </button>
</div>

{{-- =================== Estilos mÃ­nimos =================== --}}
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
    .sort-priority {
        min-width: 16px;
        height: 16px;
        border-radius: 9999px;
        font-size: 10px;
        line-height: 16px;
        text-align: center;
        background: #dbeafe;
        color: #1d4ed8;
        font-weight: 700;
    }
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
    liberarTelar: '{{ route("programa.urd.eng.liberar.telar") }}'
};

/* Columnas para filtros (telares / inventario), enviadas con la vista para no hacer peticiÃ³n extra */
const COLUMN_OPTIONS = @json($columnOptions ?? ['telares' => [], 'inventario' => []]);

const ES_SUPERVISOR = @json($esSupervisor);
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/* ---------- Helpers DOM ---------- */
const $  = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
const show    = el => el && el.classList.remove('hidden');
const hide    = el => el && el.classList.add('hidden');
const disable = (el, v = true) => { if (el) el.disabled = !!v; return el; };

/* ---------- Helpers genÃ©ricos ---------- */
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
        const token = getCsrfToken();
        const res = await fetch(url, {
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            ...options
        });

        const json = await res.json().catch(() => ({ success: false, message: res.statusText }));

        if (res.status === 419) {
            throw new Error('La sesiÃ³n expirÃ³ o el token de seguridad no es vÃ¡lido. Por favor recarga la pÃ¡gina (F5) e intenta de nuevo.');
        }

        if (!res.ok || json.success === false) {
            throw new Error(json.message || json.error || res.statusText);
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
        const s = String(iso).trim();
        const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            const y = parseInt(m[1], 10), mon = parseInt(m[2], 10) - 1, day = parseInt(m[3], 10);
            const d = new Date(y, mon, day);
            if (!Number.isNaN(d.getTime()) && d.getFullYear() === y && d.getMonth() === mon && d.getDate() === day) {
                return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
            }
        }
        const d = new Date(iso);
        return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
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
const isReservado   = telar => {
    if (typeof telar?.reservado === 'boolean') return telar.reservado;
    if (typeof telar?.is_reservado === 'boolean') return telar.is_reservado;
    return false;
};
const isProgramado  = telar => {
    if (typeof telar?.programado === 'boolean') return telar.programado;
    if (typeof telar?.is_programado === 'boolean') return telar.is_programado;
    return false;
};

/* ---------- Estado ---------- */
const state = {
    filters: { telares: {}, inventario: {} },
    selectedTelar: null,       // selecciÃ³n individual
    selectedTelares: [],       // selecciÃ³n mÃºltiple (checkboxes)
    selectedInventario: null,
    columns: { telares: COLUMN_OPTIONS.telares || [], inventario: COLUMN_OPTIONS.inventario || [] },
    sort: {
        telares: [{ column: 'no_telar', direction: 'asc' }],
        inventario: []
    },
    telaresData: @json($inventarioTelares ?? []),
    telaresDataOriginal: @json($inventarioTelares ?? []),
    inventarioData: [],
    inventarioDataOriginal: [],
    mostrarTodoInventario: false
};

/* ---------- Render ---------- */
const render = {
    sorted(data, sorts = []) {
        if (!Array.isArray(sorts) || !sorts.length) return [...data];

        return [...data].sort((a, b) => {
            for (const rule of sorts) {
                const col = rule.column;
                const dir = rule.direction;
                const av = a[col], bv = b[col];
                const emptyA = (av === null || av === undefined || av === '');
                const emptyB = (bv === null || bv === undefined || bv === '');

                if (emptyA && emptyB) continue;
                if (emptyA) return 1;
                if (emptyB) return -1;

                let cmp = 0;

                if (['fecha', 'ProdDate'].includes(col)) {
                    cmp = new Date(av) - new Date(bv);
                } else if (['no_telar','no_julio','no_orden','calibre','metros','Metros','InventQty'].includes(col)) {
                    cmp = (parseFloat(av) || 0) - (parseFloat(bv) || 0);
                } else if (['reservado', 'programado'].includes(col)) {
                    cmp = (av ? 1 : 0) - (bv ? 1 : 0);
                } else if (col === 'estado') {
                    const statusVal = row => {
                        const reserved = isReservado(row) || (String(row?.no_julio || '').trim() !== '' && String(row?.no_orden || '').trim() !== '');
                        const programmed = isProgramado(row);
                        if (reserved) return 2;
                        if (programmed) return 1;
                        return 0;
                    };
                    cmp = statusVal(a) - statusVal(b);
                } else {
                    const as = String(av).toLowerCase();
                    const bs = String(bv).toLowerCase();
                    cmp = as < bs ? -1 : (as > bs ? 1 : 0);
                }

                if (cmp !== 0) {
                    return dir === 'asc' ? cmp : -cmp;
                }
            }
            return 0;
        });
    },

    updateSortIcons() {
        $$('#telaresTable .sortable .sort-icon')
            .forEach(i => i.className = 'fa-solid fa-sort text-gray-400 sort-icon');
        $$('#inventarioTable .sortable-inventario .sort-icon-inventario')
            .forEach(i => i.className = 'fa-solid fa-sort text-gray-400 sort-icon-inventario');
        $$('.sort-priority').forEach(el => el.remove());

        const applyIcon = (selector, sorts, iconClass) => {
            (sorts || []).forEach((rule, idx) => {
                const th = $(`${selector}[data-column="${rule.column}"]`);
                if (!th) return;

                const icon = th.querySelector(`.${iconClass}`);
                if (icon) {
                    icon.className = rule.direction === 'asc'
                        ? `fa-solid fa-sort-up text-blue-600 ${iconClass}`
                        : `fa-solid fa-sort-down text-blue-600 ${iconClass}`;
                }

                const btn = th.querySelector('button');
                if (btn) {
                    const marker = document.createElement('span');
                    marker.className = 'sort-priority';
                    marker.textContent = String(idx + 1);
                    btn.appendChild(marker);
                }
            });
        };

        applyIcon('#telaresTable .sortable', state.sort.telares, 'sort-icon');
        applyIcon('#inventarioTable .sortable-inventario', state.sort.inventario, 'sort-icon-inventario');
    },

    telares(rows) {
        const tbody = $('#telaresTable tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!rows || !rows.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="14"
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

        const telarFiltros = Object.entries(state.filters.telares || {})
            .map(([column, value]) => ({ column, value }))
            .filter(f => String(f.value || '').trim() !== '');

        const filteredRows = telarFiltros.length
            ? filters.filterLocal(
                rows,
                telarFiltros.map(f => ({ columna: f.column, valor: f.value }))
            )
            : rows;

        const data = this.sorted(filteredRows, state.sort.telares);

        const frag = document.createDocumentFragment();

        data.forEach((r, idx) => {
            const metrosF     = parseFloat(r.metros || 0);
            const noJulio     = String(r.no_julio || '').trim();
            const noOrden     = String(r.no_orden || '').trim();
            const hasBoth     = metrosF > 0 && noJulio !== '';
            const reservado   = isReservado(r) || (noJulio !== '' && noOrden !== '');
            const programado  = isProgramado(r);
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
            tr.dataset.id          = r.id || ''; // ID del registro especÃ­fico en tej_inventario_telares
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
            tr.dataset.isProgramado = programado ? 'true' : 'false';
            tr.dataset.tipoAtado   = r.tipo_atado || 'Normal';
            if (r.fecha) {
                const s = String(r.fecha).trim();
                const match = s.match(/^(\d{4}-\d{2}-\d{2})/);
                tr.dataset.fecha = match ? match[1] : s;
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
            const checkboxDisabled = (reservado || programado || tieneNoOrd) ? ' disabled' : '';
            const checkboxCursor   = (reservado || programado || tieneNoOrd)
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
                    ${
                        reservado
                            ? '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">Reservado</span>'
                            : (programado
                                ? '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-700">Programado</span>'
                                : '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700">Libre</span>')
                    }
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

                // Si el telar ya tiene No. Julio, mostrar sÃ³lo esa pieza
                if (telJulio) return (r.InventSerialId || '') === telJulio;

                // Ocultar piezas asignadas a otro telar
                if (!tel.is_reservado && hasTelar && r.NoTelarId !== telNo) return false;

                // Coincidencia por Tipo (Rizo/Pie)
                if (telTipo && invTipo && invTipo !== telTipo) return false;

                return true;
            });
        }

        const inventarioFiltros = Object.entries(state.filters.inventario || {})
            .map(([column, value]) => ({ column, value }))
            .filter(f => String(f.value || '').trim() !== '');

        if (inventarioFiltros.length) {
            data = filters.filterLocal(
                data,
                inventarioFiltros.map(f => ({ columna: f.column, valor: f.value }))
            );
        }

        data = this.sorted(data, state.sort.inventario);

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

/* ---------- SelecciÃ³n ---------- */
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
                btn.title = 'Aplicar filtro y mostrar solo registros del telar seleccionado';
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
            reservado: row.dataset.isReservado === 'true',
            programado: row.dataset.isProgramado === 'true',
            is_reservado: row.dataset.isReservado === 'true',
            is_programado: row.dataset.isProgramado === 'true'
        };

        if ((item.is_reservado || item.is_programado) && checked) {
            toast('info', 'Telar no disponible', 'No se puede usar en selecciÃ³n mÃºltiple');
            if (cb) cb.checked = false;
            return;
        }

        if (hasNoOrden(item) && checked) {
            toast('info', 'Telar con orden', 'No se puede usar en selecciÃ³n mÃºltiple');
            if (cb) cb.checked = false;
            return;
        }

        if (!Array.isArray(state.selectedTelares)) {
            state.selectedTelares = [];
        }

        if (checked) {
            if (state.selectedTelares.length) {
                const ref = state.selectedTelares[0];

                if (isReservado(ref) || isProgramado(ref)) {
                    toast('info', 'Telar no disponible en selecciÃ³n',
                          'No se puede agregar mÃ¡s telares a una selecciÃ³n con telares reservados o programados',
                          3000);
                    if (cb) cb.checked = false;
                    return;
                }

                if (hasNoOrden(ref)) {
                    toast('info', 'Telar con orden en selecciÃ³n',
                          'No se puede agregar mÃ¡s telares a una selecciÃ³n que contiene telares con orden',
                          3000);
                    if (cb) cb.checked = false;
                    return;
                }

                if (!sameGroup(item, ref)) {
                    toast('warning', 'SelecciÃ³n incompatible',
                          'SÃ³lo puedes seleccionar telares con el mismo Tipo, Calibre, Hilo y SalÃ³n. La cuenta puede variar.',
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
            id:         row.dataset.id || null, // ID del registro especÃ­fico en tej_inventario_telares
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
            reservado: row.dataset.isReservado === 'true',
            programado: row.dataset.isProgramado === 'true',
            is_reservado: row.dataset.isReservado === 'true',
            is_programado: row.dataset.isProgramado === 'true'
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

        // Liberar telar: sÃ³lo telar individual y reservado
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

        // Programar (prioridad selecciÃ³n mÃºltiple)
        if (hasMultiple) {
            const hasReserved = state.selectedTelares.some(isReservado);
            const hasProgramado = state.selectedTelares.some(isProgramado);
            const hasOrden    = state.selectedTelares.some(hasNoOrden);
            disable(btnProgramar, hasReserved || hasProgramado || hasOrden);
            return;
        }

        if (hasIndividual && state.selectedTelar) {
            const tel = state.selectedTelar;
            disable(btnProgramar, isReservado(tel) || isProgramado(tel) || hasNoOrden(tel));
            return;
        }

        disable(btnProgramar, true);
    }
};

/* ---------- Filtros ---------- */
const filters = {
    updateBadge() {
        const total = Object.keys(state.filters.telares || {}).length + Object.keys(state.filters.inventario || {}).length;
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

            if (col === 'estado') {
                const estado = isReservado(item)
                    ? 'reservado'
                    : (isProgramado(item) ? 'programado' : 'libre');
                return estado.includes(val);
            }

            if (['reservado', 'programado'].includes(col)) {
                const normalized = ['1','true','si','sÃ­','yes','activo','reservado','programado'].includes(val);
                return Boolean(itemVal) === normalized;
            }

            if (col === 'NoTelarId') {
                if (['null','vacÃ­o','vacio','disponible',''].includes(val)) {
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
                        <option value="telares" selected>ProgramaciÃ³n de Requerimientos</option>
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
                    Swal.showValidationMessage('Agrega al menos un filtro vÃ¡lido');
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

    applyColumnFilter(table, column, value) {
        const next = String(value ?? '').trim();
        if (!next) {
            delete state.filters[table][column];
        } else {
            state.filters[table][column] = next;
        }

        if (table === 'telares') render.telares(state.telaresDataOriginal);
        if (table === 'inventario') render.inventario(state.inventarioDataOriginal);
    },

    clearColumnFilter(table, column) {
        delete state.filters[table][column];
        if (table === 'telares') render.telares(state.telaresDataOriginal);
        if (table === 'inventario') render.inventario(state.inventarioDataOriginal);
    },

    clearTable(table) {
        state.filters[table] = {};
        if (table === 'telares') render.telares(state.telaresDataOriginal);
        if (table === 'inventario') render.inventario(state.inventarioDataOriginal);
    },

    reset() {
        if (state.telaresDataOriginal.length) {
            render.telares(state.telaresDataOriginal);
        }

        if (state.inventarioDataOriginal.length) {
            render.inventario(state.inventarioDataOriginal);
        }

        state.filters = { telares: {}, inventario: {} };
        filters.updateBadge();

        toast('success', 'Filtros restablecidos', '', 1500);
    }
};

/* ---------- Sorting ---------- */
const sorting = {
    toggle(table, col, additive = false) {
        const current = Array.isArray(state.sort[table]) ? [...state.sort[table]] : [];
        const idx = current.findIndex(s => s.column === col);

        if (!additive) {
            if (idx === 0) {
                if (current[0].direction === 'asc') {
                    state.sort[table] = [{ column: col, direction: 'desc' }];
                } else {
                    state.sort[table] = [];
                }
            } else {
                state.sort[table] = [{ column: col, direction: 'asc' }];
            }
        } else if (idx === -1) {
            current.push({ column: col, direction: 'asc' });
            state.sort[table] = current;
        } else if (current[idx].direction === 'asc') {
            current[idx].direction = 'desc';
            state.sort[table] = current;
        } else {
            current.splice(idx, 1);
            state.sort[table] = current;
        }

        if (table === 'telares') render.telares(state.telaresDataOriginal);
        if (table === 'inventario') render.inventario(state.inventarioDataOriginal);
    },
    bind() {
        $('#telaresTable thead')?.addEventListener('click', e => {
            const th = e.target.closest('.sortable');
            if (!th) return;

            const col = th.dataset.column;
            if (!col) return;

            sorting.toggle('telares', col, e.shiftKey || e.ctrlKey || e.metaKey);
        });

        $('#inventarioTable thead')?.addEventListener('click', e => {
            const th = e.target.closest('.sortable-inventario');
            if (!th) return;

            const col = th.dataset.column;
            if (!col) return;

            sorting.toggle('inventario', col, e.shiftKey || e.ctrlKey || e.metaKey);
        });
    }
};

/* ---------- Acciones ---------- */
const actions = {
    async programar() {
        const multiple = Array.isArray(state.selectedTelares) && state.selectedTelares.length > 0;
        const tel      = state.selectedTelar;

        // SelecciÃ³n mÃºltiple => programar requerimientos
        if (multiple) {
            const hasProgramado = state.selectedTelares.some(isProgramado);
            const hasOrden = state.selectedTelares.some(hasNoOrden);
            if (hasProgramado) {
                toast('info', 'Telar programado', 'No se puede programar un telar que ya está programado');
                return;
            }
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

        // SelecciÃ³n individual
        if (tel && tel.no_telar && !isReservado(tel) && !isProgramado(tel)) {
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

        if (isProgramado(tel)) {
            toast('info', 'Telar programado', 'No se puede volver a programar un telar ya programado');
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
            confirmButtonText: 'Si, liberar',
            confirmButtonColor: '#dc2626'
        }).then(r => r.isConfirmed);

        if (!ok) return;

        show($('#loaderInventario'));
        show($('#loaderTelares'));

        try {
            const resp = await http.post(API.liberarTelar, {
                id:       tel.id || null,
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

        // Validar que el telar tenga ID (requerido para identificar el registro especÃ­fico)
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
            confirmButtonText: 'Si, reservar'
        }).then(r => r.isConfirmed);

        if (!ok) return;

        show($('#loaderInventario'));
        show($('#loaderTelares'));

        try {
            const lote      = state.selectedInventario.inventBatchId ||
                              state.selectedInventario.data?.InventBatchId || '';
            const localidad = state.selectedInventario.wmsLocationId ||
                              state.selectedInventario.data?.WMSLocationId || '';

            // Actualizar telar - SOLO el registro especÃ­fico que se estÃ¡ reservando
            await http.post(API.actualizarTelar, {
                id:        tel.id, // ID del registro especÃ­fico (REQUERIDO para actualizar solo ese registro)
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
                tej_inventario_telares_id: parseInt(tel.id, 10) // ID del registro especÃ­fico (REQUERIDO - convertir a entero)
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

    let contextTarget = { table: null, column: null };
    const contextMenu = $('#tableContextMenu');
    const closeContextMenu = () => contextMenu?.classList.add('hidden');

    const openContextMenu = (e, table, column) => {
        if (!contextMenu || !column) return;
        e.preventDefault();
        contextTarget = { table, column };
        contextMenu.classList.remove('hidden');
        contextMenu.style.left = `${e.pageX}px`;
        contextMenu.style.top = `${e.pageY}px`;
    };

    $('#telaresTable thead')?.addEventListener('contextmenu', e => {
        const th = e.target.closest('.sortable');
        if (!th) return;
        openContextMenu(e, 'telares', th.dataset.column);
    });

    $('#inventarioTable thead')?.addEventListener('contextmenu', e => {
        const th = e.target.closest('.sortable-inventario');
        if (!th) return;
        openContextMenu(e, 'inventario', th.dataset.column);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('#tableContextMenu')) closeContextMenu();
    });

    contextMenu?.addEventListener('click', async e => {
        const action = e.target.closest('[data-action]')?.dataset.action;
        if (!action) return;

        const { table, column } = contextTarget;
        if (!table || !column) return;

        if (action === 'filter-column') {
            const current = state.filters[table]?.[column] || '';
            const isBooleanFilter = ['reservado', 'programado'].includes(column);

            if (isBooleanFilter) {
                const result = await Swal.fire({
                    title: 'Filtrar columna',
                    input: 'select',
                    inputOptions: { '1': 'SÃ­', '0': 'No' },
                    inputValue: current || '1',
                    showCancelButton: true,
                    confirmButtonText: 'Aplicar'
                });
                if (result.isConfirmed) {
                    filters.applyColumnFilter(table, column, result.value);
                }
            } else {
                const result = await Swal.fire({
                    title: 'Filtrar columna',
                    input: 'text',
                    inputValue: current,
                    inputPlaceholder: 'Valor de filtro',
                    showCancelButton: true,
                    confirmButtonText: 'Aplicar'
                });
                if (result.isConfirmed) {
                    filters.applyColumnFilter(table, column, result.value);
                }
            }
        }

        if (action === 'clear-column-filter') {
            filters.clearColumnFilter(table, column);
        }

        if (action === 'clear-table-filters') {
            filters.clearTable(table);
        }

        closeContextMenu();
    });

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

    // Checkboxes: selecciÃ³n mÃºltiple
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

    // Click filas telares: selecciÃ³n individual
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

    $('#btnReloadTelares')?.addEventListener('click', filters.reset);

    // Botón quitar/aplicar filtro inventario (segunda tabla)
    $('#btnQuitarFiltroInventario')?.addEventListener('click', () => {
        if (!state.selectedTelar) return;
        state.mostrarTodoInventario = !state.mostrarTodoInventario;
        render.inventario(state.inventarioDataOriginal);
        selection.updateFiltroButton();
    });

    // Acciones
    $('#btnProgramar')?.addEventListener('click', actions.programar);
    $('#btnReservar')?.addEventListener('click', actions.reservar);
    $('#btnLiberarTelar')?.addEventListener('click', actions.liberarTelar);
});
</script>
@endsection

