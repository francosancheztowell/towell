@extends('layouts.app')

@section('page-title', 'Órdenes de trabajo mecánicas')

@section('content')
<div class="w-full p-3 sm:p-4 md:p-6 lg:p-8">
    <div class="mx-auto max-w-[96rem] space-y-4">
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 md:text-2xl">Órdenes de trabajo diarias</h1>
                    <p class="mt-1 text-sm text-gray-600">Registra las intervenciones de mecánicos por telar y falla.</p>
                </div>

                <button id="btn-nueva-orden" type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black sm:w-auto">
                    <i class="fas fa-plus"></i>
                    Nueva orden
                </button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="filtro-fecha" class="mb-1 block text-xs font-medium text-gray-700">Fecha</label>
                    <input id="filtro-fecha" type="date" value="{{ $fechaInicial }}"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>

                <div>
                    <label for="filtro-estatus" class="mb-1 block text-xs font-medium text-gray-700">Estatus</label>
                    <select id="filtro-estatus"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                        <option value="">Todos</option>
                        <option value="Activo">Activo</option>
                        <option value="Terminado">Terminado</option>
                        <option value="Cancelado">Cancelado</option>
                    </select>
                </div>

                <div class="sm:col-span-2 xl:col-span-2">
                    <label for="filtro-buscar" class="mb-1 block text-xs font-medium text-gray-700">Buscar</label>
                    <div class="flex gap-2">
                        <input id="filtro-buscar" type="search" placeholder="Folio, telar, fallo, orden o folio de paro"
                            class="min-w-0 flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                        <button id="btn-limpiar-filtros" type="button" title="Limpiar filtros"
                            class="rounded-md border border-gray-300 px-3 text-sm text-gray-700 transition hover:bg-gray-50">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-4 py-2 text-xs text-gray-500 lg:hidden">
                <i class="fas fa-arrows-alt-h mr-1"></i> Desliza horizontalmente para consultar todas las columnas.
            </div>
            <div class="max-w-full overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Tabla de órdenes de trabajo; desplázate horizontalmente para ver todas las columnas">
                <table id="tabla-ordenes" class="min-w-[1100px] divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3">Folio</th>
                            <th class="whitespace-nowrap px-4 py-3">Fecha</th>
                            <th class="whitespace-nowrap px-4 py-3">Telar</th>
                            <th class="min-w-56 px-4 py-3">Falla</th>
                            <th class="whitespace-nowrap px-4 py-3">Folio paro</th>
                            <th class="whitespace-nowrap px-4 py-3"># Orden</th>
                            <th class="whitespace-nowrap px-4 py-3 text-center">Turno</th>
                            <th class="whitespace-nowrap px-4 py-3 text-center">Mecánicos</th>
                            <th class="whitespace-nowrap px-4 py-3">Estatus</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="ordenes-body" class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">
                                Cargando órdenes de trabajo…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
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
            <div class="mb-4 rounded-md border border-blue-100 bg-blue-50 p-3">
                <label for="paro-activo" class="mb-1 block text-xs font-semibold text-blue-900">Tomar datos de un paro activo <span class="font-normal">(opcional)</span></label>
                <select id="paro-activo" class="w-full rounded-md border border-blue-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    <option value="">Cargando paros activos…</option>
                </select>
                <p class="mt-1 text-xs text-blue-800">Al seleccionarlo se precargan telar, falla, fechas, turno y orden.</p>
            </div>

            <input id="cabecera-folio" type="hidden">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="cabecera-fecha" class="mb-1 block text-xs font-medium text-gray-700">Fecha de orden <span class="text-red-600">*</span></label>
                    <input id="cabecera-fecha" name="Fecha" type="date" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="cabecera-telar" class="mb-1 block text-xs font-medium text-gray-700">Telar <span class="text-red-600">*</span></label>
                    <input id="cabecera-telar" name="TelarId" maxlength="10" required placeholder="Ej. 201"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="cabecera-folio-paro" class="mb-1 block text-xs font-medium text-gray-700">Folio de paro</label>
                    <input id="cabecera-folio-paro" name="FolioParo" maxlength="30"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="cabecera-estatus" class="mb-1 block text-xs font-medium text-gray-700">Estatus</label>
                    <select id="cabecera-estatus" name="Estatus"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                        <option value="Activo">Activo</option>
                        <option value="Terminado">Terminado</option>
                        <option value="Cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="cabecera-falla" class="mb-1 block text-xs font-medium text-gray-700">Descripción de falla</label>
                    <input id="cabecera-falla" name="Falla" maxlength="150"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
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

<div id="modal-detalle" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-2 sm:p-4 md:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-detalle">
    <div class="flex max-h-[calc(100vh-1rem)] w-full max-w-[96rem] flex-col overflow-hidden rounded-lg bg-white shadow-2xl sm:max-h-[calc(100vh-2rem)]">
        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-200 px-4 py-3 sm:px-5 sm:py-4">
            <div>
                <h2 id="titulo-modal-detalle" class="text-lg font-bold text-gray-900">Detalle de orden</h2>
                <p id="detalle-resumen" class="mt-1 text-sm text-gray-600"></p>
            </div>
            <button type="button" data-close-modal="modal-detalle" class="rounded p-1 text-xl leading-none text-gray-500 transition hover:bg-gray-100 hover:text-gray-900" aria-label="Cerrar">&times;</button>
        </div>

        <div class="min-h-0 overflow-y-auto overscroll-contain p-4 sm:p-5">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">Cada renglón representa la intervención de un mecánico.</p>
                <div class="flex flex-wrap gap-2">
                    <button id="btn-editar-cabecera" type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        <i class="fas fa-pen mr-1"></i> Editar cabecera
                    </button>
                    <button id="btn-agregar-linea" type="button" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-black">
                        <i class="fas fa-plus mr-1"></i> Agregar renglón
                    </button>
                </div>
            </div>

            <div class="mb-2 text-xs text-gray-500 lg:hidden">
                <i class="fas fa-arrows-alt-h mr-1"></i> Desliza horizontalmente para consultar todos los datos de cada intervención.
            </div>
            <div class="max-w-full overflow-x-auto overscroll-x-contain rounded-md border border-gray-200" tabindex="0" aria-label="Tabla de intervenciones; desplázate horizontalmente para ver todas las columnas">
                <table id="tabla-lineas" class="min-w-[1260px] divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-3 py-3 text-left">Mecánico</th>
                            <th class="px-2 py-3 text-center">Ajustó</th>
                            <th class="px-2 py-3 text-center">Reparó</th>
                            <th class="px-2 py-3 text-center">Cambió</th>
                            <th class="px-2 py-3 text-center">Lubricó</th>
                            <th class="px-2 py-3 text-center">Falta refacc.</th>
                            <th class="px-3 py-3 text-center">H. inicial</th>
                            <th class="px-3 py-3 text-center">H. final</th>
                            <th class="px-3 py-3 text-center">Minutos</th>
                            <th class="px-3 py-3 text-center">Calif.</th>
                            <th class="px-3 py-3 text-left">Tejedor</th>
                            <th class="px-3 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="lineas-body" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>

            <div class="sticky bottom-0 -mx-4 mt-5 flex justify-end border-t border-gray-200 bg-white px-4 pt-4 sm:-mx-5 sm:px-5">
                <button id="btn-eliminar-orden" type="button" class="rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50">
                    <i class="fas fa-trash mr-1"></i> Eliminar orden
                </button>
            </div>
        </div>
    </div>
</div>

<div id="modal-linea" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 p-2 sm:p-4 md:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-linea">
    <div class="flex max-h-[calc(100vh-1rem)] w-full max-w-3xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl sm:max-h-[calc(100vh-2rem)] md:max-w-4xl">
        <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-5 sm:py-4">
            <div>
                <h2 id="titulo-modal-linea" class="text-lg font-bold text-gray-900">Capturar intervención</h2>
                <p id="subtitulo-modal-linea" class="mt-0.5 text-xs text-gray-500"></p>
            </div>
            <button type="button" data-close-modal="modal-linea" class="rounded p-1 text-xl leading-none text-gray-500 transition hover:bg-gray-100 hover:text-gray-900" aria-label="Cerrar">&times;</button>
        </div>

        <form id="form-linea" class="min-h-0 overflow-y-auto overscroll-contain p-4 sm:p-5">
            <input id="linea-id" type="hidden">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="linea-operador" class="mb-1 block text-xs font-medium text-gray-700">Mecánico</label>
                    <select id="linea-operador" name="CveOperador"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                        <option value="">Seleccione</option>
                    </select>
                </div>
                <div>
                    <label for="linea-nom-operador" class="mb-1 block text-xs font-medium text-gray-700">Nombre mecánico</label>
                    <input id="linea-nom-operador" name="NomOperador" maxlength="150"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
            </div>

            <fieldset class="mt-5 rounded-md border border-gray-200 p-4">
                <legend class="px-1 text-sm font-semibold text-gray-800">Trabajo realizado</legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-ajusto" name="Ajusto" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Ajustó</label>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-reparo" name="Reparo" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Reparó</label>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-cambio" name="Cambio" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Cambió</label>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-lubrico" name="Lubrico" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Lubricó</label>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-falta-refacc" name="FaltaRefacc" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Falta refacc.</label>
                </div>
            </fieldset>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label for="linea-hora-inicial" class="mb-1 block text-xs font-medium text-gray-700">Hora inicial</label>
                    <input id="linea-hora-inicial" name="HoraInicial" type="time"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="linea-hora-final" class="mb-1 block text-xs font-medium text-gray-700">Hora final</label>
                    <input id="linea-hora-final" name="HoraFinal" type="time"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="linea-total-minutos" class="mb-1 block text-xs font-medium text-gray-700">Tiempo total</label>
                    <input id="linea-total-minutos" type="text" readonly placeholder="—"
                        class="w-full cursor-not-allowed rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                </div>
                <div>
                    <label for="linea-calificacion" class="mb-1 block text-xs font-medium text-gray-700">Calificación</label>
                    <input id="linea-calificacion" name="Calificacion" type="number" min="0" step="1"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="linea-cve-tejedor" class="mb-1 block text-xs font-medium text-gray-700">Clave tejedor</label>
                    <input id="linea-cve-tejedor" name="CveTejedor" maxlength="30"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
                <div>
                    <label for="linea-nom-tejedor" class="mb-1 block text-xs font-medium text-gray-700">Firma / nombre del tejedor</label>
                    <input id="linea-nom-tejedor" name="NomTejedor" maxlength="150"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                </div>
            </div>

            <div class="sticky bottom-0 -mx-4 mt-6 flex justify-end border-t border-gray-200 bg-white px-4 pt-4 sm:-mx-5 sm:px-5">
                <button id="btn-guardar-linea" type="submit" class="w-full rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-black sm:w-auto">
                    Guardar intervención
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
    const operadores = @json($operadores);
    const operadoresPorClave = new Map(operadores.map(operador => [String(operador.CveEmpl), operador]));
    const state = { ordenes: [], orden: null, paros: [] };

    const $ = (selector) => document.querySelector(selector);
    const ordenesBody = $('#ordenes-body');
    const lineasBody = $('#lineas-body');
    const modalCabecera = $('#modal-cabecera');
    const modalDetalle = $('#modal-detalle');
    const modalLinea = $('#modal-linea');

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
        const value = String(estatus || '').trim();
        const classes = value === 'Terminado'
            ? 'bg-green-100 text-green-800'
            : value === 'Cancelado'
                ? 'bg-red-100 text-red-800'
                : 'bg-blue-100 text-blue-800';

        return `<span class="inline-flex whitespace-nowrap rounded-full px-2 py-1 text-xs font-semibold ${classes}">${display(value)}</span>`;
    }

    function iconoBooleano(value) {
        return value
            ? '<i class="fas fa-check text-green-600" aria-label="Sí"></i>'
            : '<span class="text-gray-300">—</span>';
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
        if (window.Swal) {
            Swal.fire({ icon, title, toast: true, position: 'top-end', showConfirmButton: false, timer: 2800, timerProgressBar: true });
            return;
        }

        window.alert(title);
    }

    async function confirmar(title, text, confirmText = 'Sí, continuar') {
        if (! window.Swal) return window.confirm(`${title}\n${text}`);

        const result = await Swal.fire({
            icon: 'warning',
            title,
            text,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#b91c1c',
        });

        return result.isConfirmed;
    }

    function mensajeError(error) {
        const errors = error?.payload?.errors || {};
        const validationMessages = Object.values(errors).flat().filter(Boolean);
        return validationMessages[0] || error?.payload?.error || 'Ocurrió un error inesperado.';
    }

    async function api(url, options = {}) {
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
        if ($('#filtro-fecha').value) params.set('fecha', $('#filtro-fecha').value);
        if ($('#filtro-estatus').value) params.set('estatus', $('#filtro-estatus').value);
        if ($('#filtro-buscar').value.trim()) params.set('buscar', $('#filtro-buscar').value.trim());
        return params;
    }

    function renderOrdenes() {
        if (! state.ordenes.length) {
            ordenesBody.innerHTML = '<tr><td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">No hay órdenes con los filtros seleccionados.</td></tr>';
            return;
        }

        ordenesBody.innerHTML = state.ordenes.map(orden => `
            <tr class="transition hover:bg-gray-50">
                <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">${display(orden.Folio)}</td>
                <td class="whitespace-nowrap px-4 py-3 text-gray-700">${dateDisplay(orden.Fecha)}</td>
                <td class="whitespace-nowrap px-4 py-3 text-gray-700">${display(orden.TelarId)}</td>
                <td class="max-w-xs px-4 py-3 text-gray-700">${display(orden.Falla)}</td>
                <td class="whitespace-nowrap px-4 py-3 text-gray-700">${display(orden.FolioParo)}</td>
                <td class="whitespace-nowrap px-4 py-3 text-gray-700">${display(orden.Orden)}</td>
                <td class="px-4 py-3 text-center text-gray-700">${display(orden.Turno)}</td>
                <td class="px-4 py-3 text-center text-gray-700">${orden.lineas_count ?? orden.lineas?.length ?? 0}</td>
                <td class="px-4 py-3">${statusBadge(orden.Estatus)}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <button type="button" data-action="detalle-orden" data-folio="${escapeHtml(orden.Folio)}" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-100">Capturar</button>
                </td>
            </tr>
        `).join('');
    }

    async function cargarOrdenes() {
        ordenesBody.innerHTML = '<tr><td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">Cargando órdenes de trabajo…</td></tr>';

        try {
            const result = await api(`${baseUrl}/registros?${obtenerFiltros().toString()}`);
            state.ordenes = result.data || [];
            renderOrdenes();
        } catch (error) {
            ordenesBody.innerHTML = `<tr><td colspan="10" class="px-4 py-10 text-center text-sm text-red-600">${escapeHtml(mensajeError(error))}</td></tr>`;
        }
    }

    async function cargarDetalle(folio) {
        try {
            const result = await api(`${baseUrl}/${encodeURIComponent(folio)}`);
            state.orden = result.data;
            $('#titulo-modal-detalle').textContent = `Orden ${state.orden.Folio}`;
            $('#detalle-resumen').textContent = `Telar ${state.orden.TelarId || '—'} · ${state.orden.Falla || 'Sin descripción'} · Turno ${state.orden.Turno || '—'}`;
            renderLineas();
            abrirModal(modalDetalle);
        } catch (error) {
            notificar('error', mensajeError(error));
        }
    }

    function renderLineas() {
        const lineas = state.orden?.lineas || [];
        if (! lineas.length) {
            lineasBody.innerHTML = '<tr><td colspan="12" class="px-4 py-8 text-center text-sm text-gray-500">No hay renglones.</td></tr>';
            return;
        }

        lineasBody.innerHTML = lineas.map(linea => `
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-3 text-gray-800"><span class="font-medium">${display(linea.NomOperador)}</span><br><span class="text-gray-500">${display(linea.CveOperador)}</span></td>
                <td class="px-2 py-3 text-center">${iconoBooleano(linea.Ajusto)}</td>
                <td class="px-2 py-3 text-center">${iconoBooleano(linea.Reparo)}</td>
                <td class="px-2 py-3 text-center">${iconoBooleano(linea.Cambio)}</td>
                <td class="px-2 py-3 text-center">${iconoBooleano(linea.Lubrico)}</td>
                <td class="px-2 py-3 text-center">${iconoBooleano(linea.FaltaRefacc)}</td>
                <td class="whitespace-nowrap px-3 py-3 text-center text-gray-700">${display(timeInputValue(linea.HoraInicial))}</td>
                <td class="whitespace-nowrap px-3 py-3 text-center text-gray-700">${display(timeInputValue(linea.HoraFinal))}</td>
                <td class="px-3 py-3 text-center text-gray-700">${display(linea.TotalMinutos)}</td>
                <td class="px-3 py-3 text-center text-gray-700">${display(linea.Calificacion)}</td>
                <td class="px-3 py-3 text-gray-800"><span class="font-medium">${display(linea.NomTejedor)}</span><br><span class="text-gray-500">${display(linea.CveTejedor)}</span></td>
                <td class="whitespace-nowrap px-3 py-3 text-right">
                    <button type="button" data-action="editar-linea" data-linea-id="${linea.Id}" class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100">Editar</button>
                    ${lineas.length > 1 ? `<button type="button" data-action="eliminar-linea" data-linea-id="${linea.Id}" class="ml-1 rounded border border-red-200 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Eliminar</button>` : ''}
                </td>
            </tr>
        `).join('');
    }

    async function cargarParosActivos() {
        const select = $('#paro-activo');
        select.innerHTML = '<option value="">Cargando paros activos…</option>';

        try {
            const result = await api(`${baseUrl}/paros-activos`);
            state.paros = result.data || [];
            select.innerHTML = '<option value="">Capturar manualmente</option>';

            state.paros.forEach(paro => {
                const option = document.createElement('option');
                option.value = paro.Id;
                option.textContent = `${paro.Folio} · Telar ${paro.MaquinaId || '—'} · ${paro.Falla || 'Sin falla'}`;
                select.appendChild(option);
            });
        } catch (error) {
            select.innerHTML = '<option value="">No fue posible cargar paros activos</option>';
        }
    }

    function resetearCabecera() {
        $('#form-cabecera').reset();
        $('#cabecera-folio').value = '';
        $('#cabecera-fecha').value = $('#filtro-fecha').value || @json($fechaInicial);
        $('#cabecera-estatus').value = 'Activo';
        $('#titulo-modal-cabecera').textContent = 'Nueva orden de trabajo';
        $('#subtitulo-modal-cabecera').textContent = 'El folio se asigna al guardar.';
        $('#btn-guardar-cabecera').textContent = 'Guardar orden';
        $('#paro-activo').disabled = false;
    }

    async function abrirNuevaOrden() {
        resetearCabecera();
        abrirModal(modalCabecera);
        await cargarParosActivos();
    }

    async function abrirEdicionCabecera() {
        if (! state.orden) return;

        cerrarModal(modalDetalle);
        resetearCabecera();
        $('#cabecera-folio').value = state.orden.Folio;
        $('#cabecera-fecha').value = dateInputValue(state.orden.Fecha);
        $('#cabecera-telar').value = state.orden.TelarId || '';
        $('#cabecera-folio-paro').value = state.orden.FolioParo || '';
        $('#cabecera-falla').value = state.orden.Falla || '';
        $('#cabecera-fecha-paro').value = dateInputValue(state.orden.FechaParo);
        $('#cabecera-hora-paro').value = timeInputValue(state.orden.HoraParo);
        $('#cabecera-estatus').value = state.orden.Estatus || 'Activo';
        $('#cabecera-orden').value = state.orden.Orden || '';
        $('#cabecera-turno').value = state.orden.Turno || '';
        $('#titulo-modal-cabecera').textContent = `Editar orden ${state.orden.Folio}`;
        $('#subtitulo-modal-cabecera').textContent = 'Actualiza los datos de la cabecera.';
        $('#btn-guardar-cabecera').textContent = 'Guardar cambios';
        $('#paro-activo').disabled = true;
        abrirModal(modalCabecera);
    }

    function aplicarParoSeleccionado() {
        const paro = state.paros.find(item => String(item.Id) === $('#paro-activo').value);
        if (! paro) return;

        $('#cabecera-telar').value = paro.MaquinaId || '';
        $('#cabecera-folio-paro').value = paro.Folio || '';
        $('#cabecera-falla').value = paro.Falla || '';
        $('#cabecera-fecha-paro').value = dateInputValue(paro.Fecha);
        $('#cabecera-hora-paro').value = timeInputValue(paro.Hora);
        $('#cabecera-orden').value = paro.OrdenTrabajo || '';
        $('#cabecera-turno').value = paro.Turno || '';
        $('#cabecera-estatus').value = 'Activo';
    }

    function llenarSelectOperadores() {
        const select = $('#linea-operador');
        select.innerHTML = '<option value="">Seleccione</option>';
        operadores.forEach(operador => {
            const option = document.createElement('option');
            option.value = operador.CveEmpl;
            option.textContent = `${operador.CveEmpl} · ${operador.NomEmpl}${operador.Turno ? ` (T${operador.Turno})` : ''}`;
            select.appendChild(option);
        });
    }

    function resetearLinea() {
        $('#form-linea').reset();
        $('#linea-id').value = '';
        $('#linea-total-minutos').value = '';
        $('#titulo-modal-linea').textContent = 'Capturar intervención';
        $('#subtitulo-modal-linea').textContent = state.orden ? `Orden ${state.orden.Folio}` : '';
        $('#btn-guardar-linea').textContent = 'Guardar intervención';
        llenarSelectOperadores();
    }

    function abrirNuevaLinea() {
        if (! state.orden) return;
        resetearLinea();
        abrirModal(modalLinea);
    }

    function abrirEdicionLinea(id) {
        const linea = state.orden?.lineas?.find(item => Number(item.Id) === Number(id));
        if (! linea) return;

        resetearLinea();
        $('#linea-id').value = linea.Id;
        $('#linea-operador').value = linea.CveOperador || '';
        $('#linea-nom-operador').value = linea.NomOperador || '';
        $('#linea-ajusto').checked = Boolean(linea.Ajusto);
        $('#linea-reparo').checked = Boolean(linea.Reparo);
        $('#linea-cambio').checked = Boolean(linea.Cambio);
        $('#linea-lubrico').checked = Boolean(linea.Lubrico);
        $('#linea-falta-refacc').checked = Boolean(linea.FaltaRefacc);
        $('#linea-hora-inicial').value = timeInputValue(linea.HoraInicial);
        $('#linea-hora-final').value = timeInputValue(linea.HoraFinal);
        $('#linea-calificacion').value = linea.Calificacion ?? '';
        $('#linea-cve-tejedor').value = linea.CveTejedor || '';
        $('#linea-nom-tejedor').value = linea.NomTejedor || '';
        $('#titulo-modal-linea').textContent = 'Editar intervención';
        $('#btn-guardar-linea').textContent = 'Guardar cambios';
        calcularMinutosEnPantalla();
        abrirModal(modalLinea);
    }

    function calcularMinutosEnPantalla() {
        const inicio = $('#linea-hora-inicial').value;
        const fin = $('#linea-hora-final').value;
        if (! inicio || ! fin) {
            $('#linea-total-minutos').value = '';
            return;
        }

        const [inicioHora, inicioMinutos] = inicio.split(':').map(Number);
        const [finHora, finMinutos] = fin.split(':').map(Number);
        let total = ((finHora * 60) + finMinutos) - ((inicioHora * 60) + inicioMinutos);
        if (total < 0) total += 24 * 60;
        $('#linea-total-minutos').value = `${total} min`;
    }

    function datosFormulario(form) {
        return Object.fromEntries(new FormData(form).entries());
    }

    function datosLinea() {
        const data = datosFormulario($('#form-linea'));
        ['Ajusto', 'Reparo', 'Cambio', 'Lubrico', 'FaltaRefacc'].forEach(campo => {
            data[campo] = $(`#linea-${campo.replace(/[A-Z]/g, match => `-${match.toLowerCase()}`)}`).checked;
        });
        return data;
    }

    $('#form-cabecera').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = $('#btn-guardar-cabecera');
        const folio = $('#cabecera-folio').value;
        button.disabled = true;
        button.textContent = 'Guardando…';

        try {
            const result = await api(folio ? `${baseUrl}/${encodeURIComponent(folio)}` : baseUrl, {
                method: folio ? 'PUT' : 'POST',
                data: datosFormulario($('#form-cabecera')),
            });

            cerrarModal(modalCabecera);
            await cargarOrdenes();
            notificar('success', result.message || 'Orden guardada correctamente.');
            await cargarDetalle(result.data.Folio);
        } catch (error) {
            notificar('error', mensajeError(error));
        } finally {
            button.disabled = false;
            button.textContent = folio ? 'Guardar cambios' : 'Guardar orden';
        }
    });

    $('#form-linea').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (! state.orden) return;

        const button = $('#btn-guardar-linea');
        const id = $('#linea-id').value;
        button.disabled = true;
        button.textContent = 'Guardando…';

        try {
            const result = await api(id
                ? `${baseUrl}/${encodeURIComponent(state.orden.Folio)}/lineas/${id}`
                : `${baseUrl}/${encodeURIComponent(state.orden.Folio)}/lineas`, {
                method: id ? 'PUT' : 'POST',
                data: datosLinea(),
            });

            cerrarModal(modalLinea);
            await cargarDetalle(state.orden.Folio);
            await cargarOrdenes();
            notificar('success', result.message || 'Intervención guardada correctamente.');
        } catch (error) {
            notificar('error', mensajeError(error));
        } finally {
            button.disabled = false;
            button.textContent = id ? 'Guardar cambios' : 'Guardar intervención';
        }
    });

    $('#tabla-ordenes').addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="detalle-orden"]');
        if (button) cargarDetalle(button.dataset.folio);
    });

    $('#tabla-lineas').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (! button || ! state.orden) return;

        const id = button.dataset.lineaId;
        if (button.dataset.action === 'editar-linea') {
            abrirEdicionLinea(id);
            return;
        }

        if (button.dataset.action === 'eliminar-linea') {
            const confirmed = await confirmar('¿Eliminar renglón?', 'Esta intervención se quitará de la orden.', 'Sí, eliminar');
            if (! confirmed) return;

            try {
                const result = await api(`${baseUrl}/${encodeURIComponent(state.orden.Folio)}/lineas/${id}`, { method: 'DELETE' });
                await cargarDetalle(state.orden.Folio);
                await cargarOrdenes();
                notificar('success', result.message);
            } catch (error) {
                notificar('error', mensajeError(error));
            }
        }
    });

    $('#btn-nueva-orden').addEventListener('click', abrirNuevaOrden);
    $('#btn-agregar-linea').addEventListener('click', abrirNuevaLinea);
    $('#btn-editar-cabecera').addEventListener('click', abrirEdicionCabecera);
    $('#paro-activo').addEventListener('change', aplicarParoSeleccionado);
    $('#linea-operador').addEventListener('change', () => {
        const operador = operadoresPorClave.get($('#linea-operador').value);
        if (operador) $('#linea-nom-operador').value = operador.NomEmpl || '';
    });
    $('#linea-hora-inicial').addEventListener('input', calcularMinutosEnPantalla);
    $('#linea-hora-final').addEventListener('input', calcularMinutosEnPantalla);

    $('#btn-eliminar-orden').addEventListener('click', async () => {
        if (! state.orden) return;
        const confirmed = await confirmar('¿Eliminar orden?', `Se eliminará la orden ${state.orden.Folio} y todas sus intervenciones.`, 'Sí, eliminar orden');
        if (! confirmed) return;

        try {
            const result = await api(`${baseUrl}/${encodeURIComponent(state.orden.Folio)}`, { method: 'DELETE' });
            cerrarModal(modalDetalle);
            await cargarOrdenes();
            notificar('success', result.message);
        } catch (error) {
            notificar('error', mensajeError(error));
        }
    });

    document.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click', () => cerrarModal(document.getElementById(button.dataset.closeModal)));
    });

    [modalCabecera, modalDetalle, modalLinea].forEach(modal => {
        modal.addEventListener('click', event => {
            if (event.target === modal) cerrarModal(modal);
        });
    });

    let searchTimer;
    $('#filtro-fecha').addEventListener('change', cargarOrdenes);
    $('#filtro-estatus').addEventListener('change', cargarOrdenes);
    $('#filtro-buscar').addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(cargarOrdenes, 300);
    });
    $('#btn-limpiar-filtros').addEventListener('click', () => {
        $('#filtro-fecha').value = @json($fechaInicial);
        $('#filtro-estatus').value = '';
        $('#filtro-buscar').value = '';
        cargarOrdenes();
    });

    cargarOrdenes();
});
</script>
@endpush
