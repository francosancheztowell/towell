@extends('layouts.app')

@section('page-title', 'Órdenes de trabajo')

@section('navbar-right')
    <div class="relative flex items-center gap-2">
        <x-navbar.button-report
            id="btn-filtrar-ordenes-trabajo"
            title="Filtrar órdenes"
            text="Filtrar"
            icon="fa-filter"
            bg="bg-green-600"
            iconColor="text-white"
            :checkPermission="false"
        />
        @if ($puedeCrear)
            <x-navbar.button-create
                module="Ordenes de Trabajo"
                id="btn-nueva-orden"
                title="Nueva orden de trabajo"
                text="Nuevo"
            />
        @endif
    </div>
@endsection

@section('content')
@php
    $th = 'group/th relative border border-gray-300 bg-gray-100 px-0.5 py-1 text-center font-semibold text-gray-700 sm:px-1 sm:py-1.5 md:px-1.5 md:py-2 lg:px-2';
@endphp
<div class="flex h-[calc(100vh-64px)] w-full flex-col overflow-hidden p-1 sm:p-2 md:p-3">
    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div
            class="min-h-0 flex-1 overflow-auto overscroll-contain select-text"
            tabindex="0"
            aria-label="Tabla de órdenes de trabajo"
        >
            <table id="tabla-ordenes" class="w-full table-fixed border-collapse border border-gray-300 text-[10px] leading-tight sm:text-xs md:text-sm">
                <colgroup>
                    <col class="w-[11%]">
                    <col class="w-[11%]">
                    <col class="w-[9%]">
                    <col class="w-[14%]">
                    <col class="w-[6%]">
                    <col class="w-[10%]">
                    <col class="w-[7%]">
                    <col class="w-[8%]">
                    <col class="w-[11%]">
                    <col class="w-[13%]">
                </colgroup>
                <thead class="sticky top-0 z-10 bg-gray-100 text-gray-700 shadow-sm">
                    <tr>
                        <th class="{{ $th }}" data-col="folio">Folio <i class="fas fa-filter ml-0.5 text-[8px] text-gray-400 sm:text-[10px]"></i></th>
                        <th class="{{ $th }}" data-col="estatus">Status <i class="fas fa-filter ml-0.5 text-[8px] text-gray-400 sm:text-[10px]"></i></th>
                        <th class="{{ $th }}" data-col="fecha">Fecha <i class="fas fa-filter ml-0.5 text-[8px] text-gray-400 sm:text-[10px]"></i></th>
                        <th class="{{ $th }}" data-col="mecanico">Mecánico <i class="fas fa-filter ml-0.5 text-[8px] text-gray-400 sm:text-[10px]"></i></th>
                        <th class="{{ $th }}" data-col="turno">Turno <i class="fas fa-filter ml-0.5 text-[8px] text-gray-400 sm:text-[10px]"></i></th>
                        <th class="{{ $th }}" data-col="folio_paro">Folio paro <i class="fas fa-filter ml-0.5 text-[8px] text-gray-400 sm:text-[10px]"></i></th>
                        <th class="{{ $th }}" data-col="telar">Telar <i class="fas fa-filter ml-0.5 text-[8px] text-gray-400 sm:text-[10px]"></i></th>
                        <th class="{{ $th }}" data-col="orden">Orden <i class="fas fa-filter ml-0.5 text-[8px] text-gray-400 sm:text-[10px]"></i></th>
                        <th class="{{ $th }}" data-col="falla">Falla <i class="fas fa-filter ml-0.5 text-[8px] text-gray-400 sm:text-[10px]"></i></th>
                        <th class="{{ $th }}">Acciones</th>
                    </tr>
                </thead>
                <tbody id="ordenes-body" class="bg-white">
                    <tr>
                        <td colspan="10" class="px-3 py-10 text-center text-xs text-gray-500 sm:text-sm">
                            Cargando órdenes de trabajo…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div id="panel-filtros" class="fixed right-2 z-40 mt-1 hidden w-[min(calc(100%-1rem),24rem)] rounded-lg border border-gray-200 bg-white p-3 shadow-xl sm:right-4 sm:p-4" style="top: 64px;" role="dialog" aria-labelledby="titulo-panel-filtros">
    <div class="mb-3 flex items-center justify-between">
        <h2 id="titulo-panel-filtros" class="text-sm font-bold text-gray-900 sm:text-base">Filtrar órdenes</h2>
        <button type="button" id="btn-cerrar-filtros" class="flex h-8 w-8 items-center justify-center rounded-full text-xl leading-none text-gray-500 hover:bg-gray-100" aria-label="Cerrar">&times;</button>
    </div>
    <div class="space-y-3">
        <div>
            <label for="filtro-buscar" class="mb-1 block text-xs font-medium text-gray-700">Buscar</label>
            <input id="filtro-buscar" type="search" maxlength="100" placeholder="Folio, telar, paro, falla, orden o mecánico…"
                class="min-h-10 w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
        </div>
        <div>
            <label for="filtro-fecha" class="mb-1 block text-xs font-medium text-gray-700">Fecha</label>
            <input id="filtro-fecha" type="date"
                class="min-h-10 w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
            <p class="mt-1 text-xs text-gray-500">Vacío = todos los registros.</p>
        </div>
        <div>
            <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">Estatus</p>
            <div class="grid grid-cols-2 gap-2" id="filtro-estatus-opciones" role="group" aria-label="Estatus">
                <button type="button" data-estatus="" class="filtro-estatus-btn">Todos</button>
                <button type="button" data-estatus="Activo" class="filtro-estatus-btn">Activo</button>
                <button type="button" data-estatus="Terminado" class="filtro-estatus-btn">Finalizado</button>
                <button type="button" data-estatus="Calificado" class="filtro-estatus-btn">Calificado</button>
                <button type="button" data-estatus="Autorizado" class="filtro-estatus-btn">Autorizado</button>
                <button type="button" data-estatus="Cancelado" class="filtro-estatus-btn">Cancelado</button>
            </div>
        </div>
        <div id="chips-filtros" class="flex flex-wrap items-center gap-1.5 border-t border-gray-100 pt-2"></div>
    </div>
</div>

<div id="menu-columna" class="fixed z-[70] hidden min-w-44 rounded-md border border-gray-200 bg-white py-1 shadow-lg" role="menu">
    <button type="button" data-menu="filtrar" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
        <i class="fas fa-filter text-amber-500"></i> Filtrar esta columna
    </button>
    <button type="button" data-menu="quitar" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
        <i class="fas fa-times text-gray-400"></i> Quitar filtro
    </button>
</div>

<div id="popover-columna" class="fixed z-[71] hidden w-64 rounded-md border border-gray-200 bg-white p-3 shadow-xl">
    <p id="popover-columna-titulo" class="mb-2 text-sm font-semibold text-gray-800"></p>
    <input id="popover-columna-valor" class="min-h-10 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
    <div class="mt-2 flex justify-end gap-2">
        <button type="button" id="popover-columna-quitar" class="rounded-md px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100">Quitar</button>
        <button type="button" id="popover-columna-aplicar" class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-black">Aplicar</button>
    </div>
</div>

<div id="modal-cabecera" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-2 sm:p-4 md:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-cabecera">
    <div class="flex max-h-[calc(100vh-1rem)] w-full max-w-3xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl sm:max-h-[calc(100vh-2rem)] md:max-w-4xl">
        <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-5 sm:py-4">
            <div>
                <h2 id="titulo-modal-cabecera" class="text-lg font-bold text-gray-900">Nueva orden de trabajo</h2>
                <p id="subtitulo-modal-cabecera" class="mt-0.5 text-xs text-gray-500">El folio se asigna al guardar.</p>
            </div>
            <button type="button" data-close-modal="modal-cabecera" class="rounded p-1 text-xl leading-none text-gray-500 transition hover:bg-gray-100 hover:text-gray-900" aria-label="Cerrar">&times;</button>
        </div>

        <form id="form-cabecera" class="min-h-0 overflow-y-auto overscroll-contain p-4 sm:p-5">
            <div id="bloque-seleccion-paro" class="mb-4 rounded-md border border-blue-100 bg-blue-50 p-3">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label for="select-telar-paro" class="mb-1 block text-xs font-semibold text-blue-900">Máquina <span class="font-normal">(opcional)</span></label>
                        <select id="select-telar-paro"
                            class="w-full rounded-md border border-blue-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                            <option value="">Cargando máquinas…</option>
                        </select>
                    </div>
                    <div>
                        <label for="select-paro-folio" class="mb-1 block text-xs font-semibold text-blue-900">Folio de paro <span class="font-normal">(opcional)</span></label>
                        <select id="select-paro-folio" disabled
                            class="w-full rounded-md border border-blue-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 disabled:cursor-not-allowed disabled:bg-gray-100">
                            <option value="">Seleccione máquina primero</option>
                        </select>
                    </div>
                </div>
                <label class="mt-3 flex items-center gap-2 text-sm font-medium text-blue-900">
                    <input id="check-captura-manual" name="CapturaManual" type="checkbox" value="1" disabled
                        class="size-4 rounded border-blue-300 text-blue-600 focus:ring-blue-600">
                    Captura manual
                </label>
                <p id="ayuda-captura-manual" class="mt-2 text-xs text-blue-800">Seleccione una máquina para consultar los paros de las últimas 12 horas.</p>
            </div>

            <input id="cabecera-folio" type="hidden">
            <input id="cabecera-folio-paro-valor" name="FolioParo" type="hidden">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="cabecera-telar" class="mb-1 block text-xs font-medium text-gray-700">Máquina <span class="text-red-600">*</span></label>
                    <input id="cabecera-telar" name="TelarId" maxlength="50" required placeholder="Ej. 201"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="cabecera-folio-paro" class="mb-1 block text-xs font-medium text-gray-700">Folio de paro</label>
                    <input id="cabecera-folio-paro" maxlength="30" readonly
                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label for="cabecera-falla" class="mb-1 block text-xs font-medium text-gray-700">Descripción de falla <span class="text-red-600">*</span></label>
                    <input id="cabecera-falla" name="Falla" maxlength="150" required
                        placeholder="Código y descripción de la falla"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div class="md:col-span-2">
                    <label for="cabecera-comentarios" class="mb-1 block text-xs font-medium text-gray-700">Comentarios</label>
                    <textarea id="cabecera-comentarios" name="Comentarios" maxlength="500" rows="3"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900"></textarea>
                </div>
                <div>
                    <label for="cabecera-fecha-paro" class="mb-1 block text-xs font-medium text-gray-700">Fecha de paro</label>
                    <input id="cabecera-fecha-paro" name="FechaParo" type="date"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="cabecera-hora-paro" class="mb-1 block text-xs font-medium text-gray-700">Hora de paro</label>
                    <input id="cabecera-hora-paro" name="HoraParo" type="time"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="cabecera-orden" class="mb-1 block text-xs font-medium text-gray-700"># Orden</label>
                    <input id="cabecera-orden" name="Orden" maxlength="20"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="cabecera-turno" class="mb-1 block text-xs font-medium text-gray-700">Turno</label>
                    <select id="cabecera-turno" name="Turno"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                        <option value="">Seleccione</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </select>
                </div>
            </div>

            <div class="sticky bottom-0 -mx-4 mt-6 flex justify-end border-t border-gray-200 bg-white px-4 pt-4 sm:-mx-5 sm:px-5">
                <button id="btn-guardar-cabecera" type="submit" class="w-full rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-black sm:w-auto">
                    Guardar orden
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const baseUrl = @json(url('/mecanicos/ordenes-trabajo'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
    const turnoSugerido = @json((string) $turnoSugerido);
    const fechaSugerida = @json($fechaSugerida);
    const horaSugerida = @json($horaSugerida);
    const telaresCatalogo = @json($telares);
    const puedeEditar = @json($puedeEditar);
    const modoTejedor = @json($modoTejedor);
    const columnas = {
        folio: { param: 'folio', label: 'Folio' },
        estatus: { param: 'estatus', label: 'Status' },
        fecha: { param: 'fecha', label: 'Fecha', tipo: 'date' },
        mecanico: { param: 'mecanico', label: 'Mecánico' },
        turno: { param: 'turno', label: 'Turno' },
        folio_paro: { param: 'folio_paro', label: 'Folio de paro' },
        telar: { param: 'telar', label: 'Telar' },
        orden: { param: 'orden', label: 'Orden' },
        falla: { param: 'falla', label: 'Falla' },
    };
    const state = {
        ordenes: [],
        parosTelar: [],
        filtros: { estatus: '', fecha: '', buscar: '', folio: '', telar: '', folio_paro: '', orden: '', falla: '', turno: '', mecanico: '' },
        columnaMenu: null,
    };
    const cell = 'truncate border border-gray-200 px-0.5 py-1 text-center sm:px-1 sm:py-1.5 md:px-1.5 md:py-2 lg:px-2';

    const $ = (selector) => document.querySelector(selector);
    const ordenesBody = $('#ordenes-body');
    const modalCabecera = $('#modal-cabecera');
    const menuColumna = $('#menu-columna');
    const popoverColumna = $('#popover-columna');

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const display = (value) => {
        const text = String(value ?? '').trim();
        return text !== '' ? escapeHtml(text) : '<span class="text-gray-400">—</span>';
    };

    const dateInputValue = (value) => value ? String(value).slice(0, 10) : '';
    const timeInputValue = (value) => value ? String(value).slice(0, 5) : '';
    const dateDisplay = (value) => {
        const date = dateInputValue(value);
        if (! date) return '—';
        const [year, month, day] = date.split('-');
        return `${day}/${month}/${year}`;
    };

    function statusBadge(estatus) {
        const value = String(estatus || 'Activo').trim() || 'Activo';
        const label = value === 'Terminado' ? 'Finalizado' : value;
        const classes = value === 'Autorizado'
            ? 'bg-emerald-100 text-emerald-800'
            : value === 'Calificado'
                ? 'bg-violet-100 text-violet-800'
            : value === 'Terminado'
                ? 'bg-amber-100 text-amber-800'
                : value === 'Cancelado'
                    ? 'bg-red-100 text-red-800'
                    : value === 'Activo'
                        ? 'bg-blue-100 text-blue-800'
                        : 'bg-gray-100 text-gray-700';

        return `<span class="inline-flex max-w-full truncate rounded-full px-1.5 py-0.5 text-[9px] font-bold sm:px-2 sm:py-0.5 sm:text-[10px] md:px-2.5 md:py-1 md:text-xs ${classes}">${escapeHtml(label)}</span>`;
    }

    function turnoBadge(turno) {
        const value = String(turno ?? '').trim();
        const content = value !== '' ? escapeHtml(value) : '—';

        return `<span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-700 sm:h-6 sm:w-6 sm:text-xs md:h-7 md:w-7">${content}</span>`;
    }

    function abrirModal(modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModal(modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function notificar(icon, title) {
        if (window.notify?.[icon]) {
            window.notify[icon](title);
            return;
        }
        if (window.Swal) {
            Swal.fire({ icon, title, toast: true, position: 'top-end', showConfirmButton: false, timer: 2800, timerProgressBar: true });
            return;
        }
        window.alert(title);
    }

    function mensajeError(error) {
        const errors = error?.payload?.errors || error?.errors || {};
        const validationMessages = Object.values(errors).flat().filter(Boolean);
        return validationMessages[0] || error?.payload?.error || error?.message || 'Ocurrió un error inesperado.';
    }

    async function api(url, options = {}) {
        if (window.http) {
            const method = (options.method || 'GET').toLowerCase();
            try {
                const payload = method === 'get'
                    ? await window.http.get(url)
                    : await window.http[method](url, options.data);
                if (payload?.success === false) {
                    const error = new Error(payload.error || 'No se pudo completar la solicitud.');
                    error.payload = payload;
                    throw error;
                }
                return payload;
            } catch (error) {
                error.payload = error.payload || error.data || {};
                throw error;
            }
        }

        const headers = { Accept: 'application/json', ...(options.headers || {}) };
        if (options.method && options.method !== 'GET') headers['X-CSRF-TOKEN'] = csrfToken;
        if (options.data !== undefined) headers['Content-Type'] = 'application/json';

        const response = await fetch(url, {
            method: options.method || 'GET',
            headers,
            body: options.data !== undefined ? JSON.stringify(options.data) : undefined,
        });

        const payload = await response.json().catch(() => ({}));
        if (! response.ok || payload.success === false) {
            const error = new Error(payload.error || 'No se pudo completar la solicitud.');
            error.payload = payload;
            throw error;
        }

        return payload;
    }

    function obtenerFiltros() {
        const params = new URLSearchParams();
        Object.entries(state.filtros).forEach(([key, value]) => {
            const texto = String(value ?? '').trim();
            if (texto) params.set(key, texto);
        });
        return params;
    }

    function actualizarBotonesFiltroEstatus() {
        const base = 'filtro-estatus-btn min-h-8 rounded-md px-2 py-1 text-[10px] font-bold transition active:scale-[0.98] sm:min-h-9 sm:px-2.5 sm:text-xs md:min-h-10 md:px-3 md:text-sm';

        document.querySelectorAll('.filtro-estatus-btn').forEach(button => {
            const estatus = button.dataset.estatus ?? '';
            const activo = estatus === state.filtros.estatus;
            button.setAttribute('aria-pressed', activo ? 'true' : 'false');

            if (estatus === '') {
                button.className = activo ? `${base} bg-gray-900 text-white shadow` : `${base} border border-gray-300 bg-white text-gray-700`;
            } else if (estatus === 'Activo') {
                button.className = activo ? `${base} bg-blue-600 text-white shadow` : `${base} border border-blue-200 bg-blue-50 text-blue-800`;
            } else if (estatus === 'Terminado') {
                button.className = activo ? `${base} bg-amber-500 text-white shadow` : `${base} border border-amber-200 bg-amber-50 text-amber-800`;
            } else if (estatus === 'Calificado') {
                button.className = activo ? `${base} bg-violet-600 text-white shadow` : `${base} border border-violet-200 bg-violet-50 text-violet-800`;
            } else if (estatus === 'Autorizado') {
                button.className = activo ? `${base} bg-emerald-600 text-white shadow` : `${base} border border-emerald-200 bg-emerald-50 text-emerald-800`;
            } else if (estatus === 'Cancelado') {
                button.className = activo ? `${base} bg-red-600 text-white shadow` : `${base} border border-red-200 bg-red-50 text-red-800`;
            }
        });
    }

    function renderChipsFiltros() {
        const chips = $('#chips-filtros');
        const activos = Object.entries(state.filtros).filter(([key, value]) => {
            if (key === 'buscar' || key === 'estatus' || key === 'fecha') return false;
            return String(value ?? '').trim() !== '';
        });

        const extra = [];
        if (state.filtros.buscar) extra.push(['buscar', 'Buscar', state.filtros.buscar]);
        if (state.filtros.fecha) extra.push(['fecha', 'Fecha', dateDisplay(state.filtros.fecha)]);
        if (state.filtros.estatus) extra.push(['estatus', 'Status', state.filtros.estatus === 'Terminado' ? 'Finalizado' : state.filtros.estatus]);

        const items = [
            ...extra,
            ...activos.map(([key, value]) => [key, columnas[key]?.label || key, value]),
        ];

        if (! items.length) {
            chips.innerHTML = '<p class="text-xs text-gray-400">Sin filtros de columna. Click derecho en un encabezado para filtrar.</p>';
            return;
        }

        chips.innerHTML = items.map(([key, label, value]) => `
            <button type="button" data-chip="${escapeHtml(key)}" class="inline-flex max-w-full items-center gap-1 rounded-full bg-gray-900 px-2 py-0.5 text-[10px] font-medium text-white sm:text-xs">
                <span class="truncate">${escapeHtml(label)}: ${escapeHtml(value)}</span>
                <span aria-hidden="true">×</span>
            </button>
        `).join('') + `<button type="button" data-chip="__all" class="text-[10px] font-semibold text-gray-500 underline sm:text-xs">Limpiar</button>`;
    }

    function renderOrdenes() {
        if (! state.ordenes.length) {
            ordenesBody.innerHTML = '<tr><td colspan="10" class="px-3 py-10 text-center text-xs text-gray-500 sm:text-sm">No hay órdenes con los filtros seleccionados.</td></tr>';
            return;
        }

        ordenesBody.innerHTML = state.ordenes.map(orden => {
            const folio = escapeHtml(orden.Folio);
            const capturaUrl = `${baseUrl}/${encodeURIComponent(orden.Folio)}/captura`;
            const estatus = orden.Estatus || 'Activo';
            const folioCerrado = ['Terminado', 'Calificado', 'Autorizado', 'Cancelado'].includes(estatus);
            let accionPrincipal = '';
            const btnBase = 'inline-flex min-h-9 min-w-20 items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold sm:min-h-10 sm:min-w-24 sm:px-3 sm:py-2 sm:text-sm md:min-h-11 md:px-4';
            if (modoTejedor) {
                accionPrincipal = estatus === 'Terminado'
                    ? `<a href="${capturaUrl}" class="${btnBase} bg-indigo-600 text-white hover:bg-indigo-700" title="Calificar renglones"><i class="fas fa-star"></i> Calificar</a>`
                    : `<a href="${capturaUrl}" class="${btnBase} border border-gray-300 text-gray-700 hover:bg-gray-100" title="Ver renglones"><i class="fas fa-eye"></i> Ver</a>`;
            } else if (puedeEditar && ! folioCerrado) {
                accionPrincipal = `<a href="${capturaUrl}" class="${btnBase} bg-gray-900 text-white hover:bg-black" title="Editar / capturar"><i class="fas fa-pen"></i> Editar</a>`;
            } else {
                accionPrincipal = `<a href="${capturaUrl}" class="${btnBase} border border-gray-300 text-gray-700 hover:bg-gray-100" title="Ver"><i class="fas fa-eye"></i> Ver</a>`;
            }

            const mecanico = String(orden.NomMecanico || '').trim();
            const falla = String(orden.Falla || '').trim();

            return `
            <tr class="odd:bg-white even:bg-slate-50 hover:bg-blue-50 odd:hover:bg-blue-50 even:hover:bg-blue-50">
                <td class="${cell}"><span class="inline-block max-w-full truncate rounded bg-gray-900 px-1 py-0.5 font-bold text-white sm:px-1.5">${folio}</span></td>
                <td class="${cell}">${statusBadge(orden.Estatus)}</td>
                <td class="${cell} text-gray-700">${dateDisplay(orden.Fecha)}</td>
                <td class="${cell} font-semibold text-gray-900" title="${escapeHtml(mecanico)}">${display(mecanico)}</td>
                <td class="${cell}">${turnoBadge(orden.Turno)}</td>
                <td class="${cell} text-gray-700">${display(orden.FolioParo)}</td>
                <td class="${cell} text-gray-700">${display(orden.TelarId)}</td>
                <td class="${cell} text-gray-700">${display(orden.Orden)}</td>
                <td class="${cell} font-semibold text-gray-900" title="${escapeHtml(falla)}">${display(falla)}</td>
                <td class="${cell}">${accionPrincipal}</td>
            </tr>
        `;
        }).join('');
    }

    async function cargarOrdenes() {
        ordenesBody.innerHTML = '<tr><td colspan="10" class="px-3 py-10 text-center text-xs text-gray-500 sm:text-sm">Cargando órdenes de trabajo…</td></tr>';

        try {
            const result = await api(`${baseUrl}/registros?${obtenerFiltros().toString()}`);
            state.ordenes = result.data || [];
            renderOrdenes();
            renderChipsFiltros();
        } catch (error) {
            ordenesBody.innerHTML = `<tr><td colspan="10" class="px-3 py-10 text-center text-xs text-red-600 sm:text-sm">${escapeHtml(mensajeError(error))}</td></tr>`;
        }
    }

    function cerrarMenus() {
        menuColumna.classList.add('hidden');
        popoverColumna.classList.add('hidden');
        state.columnaMenu = null;
    }

    function posicionar(el, x, y) {
        const width = el.offsetWidth || 220;
        const height = el.offsetHeight || 120;
        const left = Math.min(x, window.innerWidth - width - 8);
        const top = Math.min(y, window.innerHeight - height - 8);
        el.style.left = `${Math.max(8, left)}px`;
        el.style.top = `${Math.max(8, top)}px`;
    }

    function abrirMenuColumna(event, col) {
        if (! columnas[col]) return;
        event.preventDefault();
        state.columnaMenu = col;
        popoverColumna.classList.add('hidden');
        menuColumna.classList.remove('hidden');
        posicionar(menuColumna, event.clientX, event.clientY);
    }

    function abrirPopoverColumna(col, x, y) {
        if (! columnas[col]) return;
        state.columnaMenu = col;
        const meta = columnas[col];
        $('#popover-columna-titulo').textContent = `Filtrar ${meta.label}`;
        const input = $('#popover-columna-valor');
        input.type = meta.tipo === 'date' ? 'date' : 'text';
        input.value = state.filtros[meta.param] || '';
        input.placeholder = meta.tipo === 'date' ? '' : `Valor de ${meta.label}`;
        menuColumna.classList.add('hidden');
        popoverColumna.classList.remove('hidden');
        posicionar(popoverColumna, x, y);
        input.focus();
        input.select();
    }

    function aplicarFiltroColumna(quitar = false) {
        const col = state.columnaMenu;
        if (! col || ! columnas[col]) return;
        const param = columnas[col].param;
        state.filtros[param] = quitar ? '' : ($('#popover-columna-valor').value || '').trim();
        if (param === 'fecha') $('#filtro-fecha').value = state.filtros.fecha;
        if (param === 'estatus') actualizarBotonesFiltroEstatus();
        cerrarMenus();
        cargarOrdenes();
    }

    function camposCapturaManual() {
        return ['cabecera-falla', 'cabecera-comentarios'];
    }

    function camposDatosParo() {
        return ['cabecera-folio-paro', 'cabecera-fecha-paro', 'cabecera-hora-paro'];
    }

    function camposParoEditablesEnManual() {
        return ['cabecera-fecha-paro', 'cabecera-hora-paro'];
    }

    function establecerCamposCreacion({ manual = false, hayParos = false, telarSeleccionado = false } = {}) {
        camposCapturaManual().forEach(id => {
            const campo = document.getElementById(id);
            campo.readOnly = !manual;
            campo.classList.toggle('bg-gray-50', !manual);
            campo.classList.toggle('text-gray-600', !manual);
        });

        const telar = $('#cabecera-telar');
        telar.readOnly = true;
        telar.classList.add('bg-gray-50', 'text-gray-600');

        camposDatosParo().forEach(id => {
            const editable = manual && camposParoEditablesEnManual().includes(id);
            const campo = document.getElementById(id);
            campo.disabled = ! editable;
            campo.classList.toggle('bg-gray-50', ! editable);
            campo.classList.toggle('text-gray-600', ! editable);
        });

        const turno = $('#cabecera-turno');
        turno.disabled = !manual;
        turno.classList.toggle('bg-gray-50', !manual);
        turno.classList.toggle('text-gray-600', !manual);
        if (manual) {
            if (! turno.value) turno.value = turnoSugerido;
            if (! $('#cabecera-fecha-paro').value) $('#cabecera-fecha-paro').value = fechaSugerida;
            if (! $('#cabecera-hora-paro').value) $('#cabecera-hora-paro').value = horaSugerida;
        }

        const check = $('#check-captura-manual');
        if (! telarSeleccionado) {
            check.disabled = true;
            check.checked = false;
            $('#ayuda-captura-manual').textContent = 'Seleccione una máquina para consultar los paros de las últimas 12 horas.';
            return;
        }

        check.disabled = false;
        check.checked = manual;
        $('#ayuda-captura-manual').textContent = hayParos
            ? 'Selecciona un folio de paro de las últimas 12 horas, o habilita la captura manual para editar falla, turno, fecha y hora.'
            : 'No hay paros de las últimas 12 horas para esta máquina. La captura manual está habilitada.';
    }

    function resetearOrigenCreacion() {
        const selectParo = $('#select-paro-folio');
        selectParo.innerHTML = '<option value="">Seleccione máquina primero</option>';
        selectParo.disabled = true;
        state.parosTelar = [];
        poblarSelectTelares();
        limpiarCabeceraParaSeleccionTelar();
        establecerCamposCreacion();
    }

    function poblarSelectTelares() {
        const select = $('#select-telar-paro');
        select.innerHTML = '<option value="">Seleccione máquina</option>';
        telaresCatalogo.forEach(({ id, label }) => {
            const option = document.createElement('option');
            option.value = id;
            option.textContent = label;
            select.appendChild(option);
        });
    }

    function poblarSelectParosPorTelar(telarId) {
        const select = $('#select-paro-folio');
        select.innerHTML = '<option value="">Seleccione folio de paro</option>';

        if (! telarId) {
            select.disabled = true;
            select.innerHTML = '<option value="">Seleccione máquina primero</option>';
            return;
        }

        if (! state.parosTelar.length) {
            select.disabled = true;
            select.innerHTML = '<option value="">Sin paros disponibles para esta máquina</option>';
            return;
        }

        select.disabled = false;
        state.parosTelar.forEach(paro => {
            const option = document.createElement('option');
            option.value = paro.Folio;
            const falla = String(paro.FallaTexto || paro.Falla || '').trim();
            option.textContent = falla !== '' ? `${paro.Folio} · ${falla}` : String(paro.Folio);
            select.appendChild(option);
        });
    }

    function resetearCabecera() {
        $('#form-cabecera').reset();
        $('#cabecera-folio').value = '';
        $('#titulo-modal-cabecera').textContent = 'Nueva orden de trabajo';
        $('#subtitulo-modal-cabecera').textContent = 'El folio se asigna al guardar.';
        $('#btn-guardar-cabecera').textContent = 'Guardar orden';
        resetearOrigenCreacion();
    }

    async function abrirNuevaOrden() {
        resetearCabecera();
        abrirModal(modalCabecera);
    }

    async function onTelarParoChange() {
        const telar = $('#select-telar-paro').value;
        $('#select-paro-folio').value = '';
        state.parosTelar = [];
        limpiarCabeceraParaSeleccionTelar();

        if (! telar) {
            poblarSelectParosPorTelar('');
            establecerCamposCreacion();
            return;
        }

        $('#cabecera-telar').value = telar;
        $('#select-paro-folio').innerHTML = '<option value="">Consultando paros…</option>';
        $('#select-paro-folio').disabled = true;

        try {
            const result = await api(`${baseUrl}/paros-historial?${new URLSearchParams({ TelarId: telar }).toString()}`);
            state.parosTelar = result.data || [];
            poblarSelectParosPorTelar(telar);
            establecerCamposCreacion({
                manual: state.parosTelar.length === 0,
                hayParos: state.parosTelar.length > 0,
                telarSeleccionado: true,
            });
            if (state.parosTelar.length === 0) {
                $('#cabecera-folio-paro').value = 'Sin Folio de Paro.';
                $('#cabecera-turno').value = turnoSugerido;
            }
        } catch (error) {
            $('#select-paro-folio').innerHTML = '<option value="">No fue posible consultar paros</option>';
            $('#select-paro-folio').disabled = true;
            establecerCamposCreacion();
            notificar('error', mensajeError(error));
        }
    }

    function aplicarParoSeleccionado() {
        const paro = state.parosTelar.find(item => String(item.Folio) === $('#select-paro-folio').value);
        if (! paro) {
            limpiarCabeceraParaSeleccionTelar();
            const telar = $('#select-telar-paro').value;
            if (telar) $('#cabecera-telar').value = telar;
            establecerCamposCreacion({
                manual: false,
                hayParos: state.parosTelar.length > 0,
                telarSeleccionado: true,
            });
            return;
        }

        $('#cabecera-telar').value = paro.MaquinaId || '';
        $('#cabecera-folio-paro').value = paro.Folio || '';
        $('#cabecera-folio-paro-valor').value = paro.Folio || '';
        $('#cabecera-falla').value = paro.FallaTexto || paro.Falla || '';
        $('#cabecera-comentarios').value = paro.ComentariosTexto || '';
        $('#cabecera-fecha-paro').value = dateInputValue(paro.Fecha);
        $('#cabecera-hora-paro').value = timeInputValue(paro.Hora);
        $('#cabecera-orden').value = paro.OrdenTrabajo || '';
        $('#cabecera-turno').value = paro.Turno || '';
        establecerCamposCreacion({ hayParos: true, telarSeleccionado: true });
    }

    function limpiarCamposDesdeParo() {
        [
            'cabecera-folio-paro',
            'cabecera-folio-paro-valor',
            'cabecera-falla',
            'cabecera-comentarios',
            'cabecera-fecha-paro',
            'cabecera-hora-paro',
            'cabecera-orden',
            'cabecera-turno',
        ].forEach(id => {
            document.getElementById(id).value = '';
        });
    }

    function limpiarCabeceraParaSeleccionTelar() {
        limpiarCamposDesdeParo();
        $('#cabecera-telar').value = '';
    }

    $('#form-cabecera').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = $('#btn-guardar-cabecera');
        button.disabled = true;
        button.textContent = 'Guardando…';

        try {
            const result = await api(baseUrl, {
                method: 'POST',
                data: Object.fromEntries(new FormData($('#form-cabecera')).entries()),
            });
            window.location.assign(`${baseUrl}/${encodeURIComponent(result.data.Folio)}/captura`);
        } catch (error) {
            notificar('error', mensajeError(error));
            button.disabled = false;
            button.textContent = 'Guardar orden';
        }
    });

    const panelFiltros = $('#panel-filtros');
    const btnFiltrar = $('#btn-filtrar-ordenes-trabajo');

    function panelFiltrosAbierto() {
        return ! panelFiltros.classList.contains('hidden');
    }

    function abrirPanelFiltros() {
        actualizarBotonesFiltroEstatus();
        renderChipsFiltros();
        panelFiltros.classList.remove('hidden');
        btnFiltrar?.setAttribute('aria-expanded', 'true');
        $('#filtro-buscar')?.focus();
    }

    function cerrarPanelFiltros() {
        panelFiltros.classList.add('hidden');
        btnFiltrar?.setAttribute('aria-expanded', 'false');
    }

    function togglePanelFiltros() {
        if (panelFiltrosAbierto()) cerrarPanelFiltros();
        else abrirPanelFiltros();
    }

    btnFiltrar?.addEventListener('click', (event) => {
        event.stopPropagation();
        togglePanelFiltros();
    });
    $('#btn-cerrar-filtros')?.addEventListener('click', cerrarPanelFiltros);

    $('#btn-nueva-orden')?.addEventListener('click', abrirNuevaOrden);
    $('#select-telar-paro').addEventListener('change', onTelarParoChange);
    $('#select-paro-folio').addEventListener('change', aplicarParoSeleccionado);
    $('#check-captura-manual').addEventListener('change', event => {
        const telar = $('#select-telar-paro').value;
        if (event.target.checked) {
            $('#select-paro-folio').value = '';
            limpiarCamposDesdeParo();
            $('#cabecera-telar').value = telar;
            $('#cabecera-folio-paro').value = 'Sin Folio de Paro.';
            $('#cabecera-turno').value = turnoSugerido;
        } else {
            limpiarCamposDesdeParo();
            $('#cabecera-telar').value = telar;
            poblarSelectParosPorTelar(telar);
        }
        establecerCamposCreacion({
            manual: event.target.checked,
            hayParos: state.parosTelar.length > 0,
            telarSeleccionado: Boolean($('#select-telar-paro').value),
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click', () => cerrarModal(document.getElementById(button.dataset.closeModal)));
    });
    modalCabecera.addEventListener('click', event => {
        if (event.target === modalCabecera) cerrarModal(modalCabecera);
    });

    document.querySelectorAll('.filtro-estatus-btn').forEach(button => {
        button.addEventListener('click', () => {
            state.filtros.estatus = button.dataset.estatus ?? '';
            actualizarBotonesFiltroEstatus();
            cargarOrdenes();
        });
    });

    $('#filtro-fecha').addEventListener('change', () => {
        state.filtros.fecha = $('#filtro-fecha').value;
        cargarOrdenes();
    });

    let buscarTimer = null;
    $('#filtro-buscar').addEventListener('input', () => {
        clearTimeout(buscarTimer);
        buscarTimer = setTimeout(() => {
            state.filtros.buscar = $('#filtro-buscar').value.trim();
            cargarOrdenes();
        }, 300);
    });

    $('#chips-filtros').addEventListener('click', (event) => {
        const chip = event.target.closest('[data-chip]');
        if (! chip) return;
        if (chip.dataset.chip === '__all') {
            Object.keys(state.filtros).forEach(key => { state.filtros[key] = ''; });
            $('#filtro-buscar').value = '';
            $('#filtro-fecha').value = '';
        } else {
            state.filtros[chip.dataset.chip] = '';
            if (chip.dataset.chip === 'buscar') $('#filtro-buscar').value = '';
            if (chip.dataset.chip === 'fecha') $('#filtro-fecha').value = '';
        }
        actualizarBotonesFiltroEstatus();
        cargarOrdenes();
    });

    const thead = $('#tabla-ordenes thead');
    let ignorarClickTrasMenu = false;
    thead.addEventListener('contextmenu', (event) => {
        const th = event.target.closest('th[data-col]');
        if (! th) return;
        ignorarClickTrasMenu = true;
        abrirMenuColumna(event, th.dataset.col);
    });
    thead.addEventListener('click', (event) => {
        if (ignorarClickTrasMenu) {
            ignorarClickTrasMenu = false;
            return;
        }
        if (! event.target.closest('i.fa-filter')) return;
        const th = event.target.closest('th[data-col]');
        if (! th) return;
        const rect = th.getBoundingClientRect();
        abrirPopoverColumna(th.dataset.col, rect.left, rect.bottom + 4);
    });

    let pressTimer = null;
    thead.addEventListener('touchstart', (event) => {
        const th = event.target.closest('th[data-col]');
        if (! th) return;
        const touch = event.changedTouches[0];
        pressTimer = setTimeout(() => abrirMenuColumna({
            preventDefault() {},
            clientX: touch.clientX,
            clientY: touch.clientY,
        }, th.dataset.col), 500);
    }, { passive: true });
    ['touchend', 'touchmove', 'touchcancel'].forEach(tipo => {
        thead.addEventListener(tipo, () => clearTimeout(pressTimer));
    });

    menuColumna.addEventListener('click', (event) => {
        const action = event.target.closest('[data-menu]')?.dataset.menu;
        if (action === 'filtrar') {
            abrirPopoverColumna(state.columnaMenu, event.clientX, event.clientY);
            return;
        }
        if (action === 'quitar') {
            aplicarFiltroColumna(true);
        }
    });
    $('#popover-columna-aplicar').addEventListener('click', () => aplicarFiltroColumna(false));
    $('#popover-columna-quitar').addEventListener('click', () => aplicarFiltroColumna(true));
    $('#popover-columna-valor').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') aplicarFiltroColumna(false);
    });
    document.addEventListener('click', (event) => {
        if (! event.target.closest('#menu-columna, #popover-columna, th[data-col]')) {
            cerrarMenus();
        }
        if (! event.target.closest('#panel-filtros, #btn-filtrar-ordenes-trabajo')) {
            cerrarPanelFiltros();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            cerrarMenus();
            cerrarPanelFiltros();
            if (! modalCabecera.classList.contains('hidden')) cerrarModal(modalCabecera);
        }
    });

    cargarOrdenes();
    actualizarBotonesFiltroEstatus();
    renderChipsFiltros();
});
</script>
@endpush
