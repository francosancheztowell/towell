@extends('layouts.app')

@section('page-title', 'Captura de orden de trabajo')

@section('content')
@php
    $fallaTexto = $orden->Falla ?: 'Sin descripción';
    $estatusActual = $orden->Estatus ?: 'Activo';
    $badgeClases = match ($estatusActual) {
        'Autorizado' => 'bg-emerald-100 text-emerald-800',
        'Calificado' => 'bg-violet-100 text-violet-800',
        'Terminado' => 'bg-amber-100 text-amber-800',
        'Cancelado' => 'bg-red-100 text-red-800',
        'Activo' => 'bg-blue-100 text-blue-800',
        default => 'bg-gray-100 text-gray-700',
    };
    $badgeLabel = match ($estatusActual) {
        'Terminado' => 'Finalizado',
        default => $estatusActual,
    };
    $modoTejedor = $modoTejedor ?? ($esTejedor && ! ($puedeEditar ?? false));
    $puedeRegistrar = $puedeRegistrar ?? $esSupervisor;
    $puedeCrear = $puedeCrear ?? false;
    $puedeEditar = $puedeEditar ?? false;
    $puedeEliminar = $puedeEliminar ?? false;
    $puedeFinalizar = $puedeFinalizar ?? false;
    $puedeCalificar = $puedeCalificar ?? false;
    $puedeAutorizar = $puedeAutorizar ?? false;
    $bloqueadaEdicion = $bloqueadaEdicion ?? in_array($estatusActual, ['Terminado', 'Calificado', 'Autorizado'], true);
    $turnoSugerido = (int) ($turnoSugerido ?? \App\Helpers\TurnoHelper::getTurnoActual());
    $fechaSugerida = $fechaSugerida ?? now('America/Mexico_City')->toDateString();
@endphp
<div class="w-full p-3 sm:p-4 lg:p-5">
    <div class="mx-auto max-w-7xl space-y-3 lg:max-w-[100rem] lg:space-y-4">
        {{-- Resumen de la orden --}}
        <section class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm sm:p-4">
            <div class="flex flex-col gap-3 sm:gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-md bg-gray-900 px-3 py-1.5 text-sm font-bold text-white sm:text-base">
                            Folio {{ $orden->Folio }}
                        </span>
                        <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-bold {{ $badgeClases }}">{{ $badgeLabel }}</span>
                        @if ($puedeCalificar)
                            <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">
                                {{ ($esSupervisor ?? false) && ! ($modoTejedor ?? false) ? 'Calificar intervenciones' : 'Modo tejedor · calificar' }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($bloqueada)
                            <span class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                                <i class="fas fa-lock"></i> Autorizada · solo lectura
                            </span>
                        @elseif ($puedeAutorizar)
                            <button id="btn-autorizar-orden" type="button"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 sm:text-base">
                                <i class="fas fa-circle-check"></i>
                                Autorizar
                            </button>
                        @elseif ($puedeFinalizar)
                            <button id="btn-finalizar-orden" type="button"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-600 sm:text-base">
                                <i class="fas fa-flag-checkered"></i>
                                Finalizar
                            </button>
                        @endif
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-gray-100 pt-3 sm:grid-cols-3 md:grid-cols-5 md:gap-x-6">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 sm:text-sm">Fecha de orden</dt>
                        <dd class="mt-0.5 text-base font-bold text-gray-900 sm:text-lg">{{ optional($orden->Fecha)->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 sm:text-sm">Telar</dt>
                        <dd class="mt-0.5 text-base font-bold text-gray-900 sm:text-lg">{{ $orden->TelarId ?: '—' }}</dd>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 sm:text-sm">Falla</dt>
                        <dd class="mt-0.5 line-clamp-2 text-base font-bold text-gray-900 sm:text-lg" title="{{ $fallaTexto }}">{{ $fallaTexto }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 sm:text-sm">Turno</dt>
                        <dd class="mt-0.5 text-base font-bold text-gray-900 sm:text-lg">{{ $orden->Turno ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 sm:text-sm"># Orden</dt>
                        <dd class="mt-0.5 text-base font-bold text-gray-900 sm:text-lg">{{ $orden->Orden ?: '—' }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        @if (! $bloqueadaEdicion && ! ($modoTejedor ?? false) && ($puedeEditar || $puedeCrear))
        {{-- Formulario de captura (mecánico / supervisor) --}}
        <section id="seccion-captura" class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm sm:p-4 lg:p-5">
            <div class="flex flex-col gap-2 border-b border-gray-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 id="titulo-formulario" class="text-lg font-bold text-gray-900 sm:text-xl">Capturar intervención</h2>
                    <p id="subtitulo-formulario" class="mt-0.5 text-sm text-gray-600 sm:text-base">Orden {{ $orden->Folio }}</p>
                </div>
                <button id="btn-nuevo-renglon" type="button"
                    @class([
                        'inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto sm:text-base',
                        'hidden' => ! ($puedeCrear ?? false),
                    ])>
                    <i class="fas fa-plus"></i>
                    Nuevo renglón
                </button>
            </div>

            <form id="form-linea" class="mt-4 space-y-4">
                <input id="linea-id" type="hidden">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12 lg:gap-4">
                    <div class="lg:col-span-6">
                        <label for="linea-operador" class="mb-1 block text-sm font-medium text-gray-700">Mecánico <span class="font-normal text-gray-500">(capturando)</span></label>
                        <select id="linea-operador" name="CveOperador"
                            class="min-h-11 w-full rounded-md border border-gray-300 px-3 py-2 text-base outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                            <option value="">Seleccione</option>
                            @foreach ($operadores as $operador)
                                <option value="{{ $operador->CveEmpl }}">{{ $operador->CveEmpl }} · {{ $operador->NomEmpl }}@if ($operador->Turno) (T{{ $operador->Turno }}) @endif</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Se precarga con el usuario en sesión. Para guardar completa trabajo realizado y horas.</p>
                        {{-- El nombre viaja junto con la clave: el select ya muestra ambos. --}}
                        <input id="linea-nom-operador" name="NomOperador" type="hidden" maxlength="150">
                    </div>
                    <div class="lg:col-span-3">
                        <label for="linea-turno" class="mb-1 block text-sm font-medium text-gray-700">Turno <span class="font-normal text-gray-500">(registro)</span></label>
                        <select id="linea-turno" name="Turno"
                            class="min-h-11 w-full rounded-md border border-gray-300 px-3 py-2 text-base outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                            @foreach ([1, 2, 3, 4] as $turno)
                                <option value="{{ $turno }}" @selected($turno === $turnoSugerido)>Turno {{ $turno }}@if ($turno === 4) @endif</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Sugerido: Turno {{ $turnoSugerido }}.</p>
                    </div>
                    <div class="lg:col-span-3">
                        <label for="linea-fecha" class="mb-1 block text-sm font-medium text-gray-700">Fecha <span class="font-normal text-gray-500">(registro)</span></label>
                        <input id="linea-fecha" name="Fecha" type="date" value="{{ $fechaSugerida }}"
                            class="min-h-11 w-full rounded-md border border-gray-300 px-3 py-2 text-base outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                        <p class="mt-1 text-xs text-gray-500">Sugerida: hoy.</p>
                    </div>
                </div>

                <fieldset class="rounded-md border border-gray-200 px-3 py-2.5 sm:px-4">
                    <legend class="px-1 text-sm font-semibold text-gray-800 sm:text-base">Trabajo realizado</legend>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-5 md:gap-3">
                        <label class="flex min-h-10 items-center gap-2 text-sm text-gray-700 sm:text-base"><input id="linea-ajusto" type="checkbox" class="size-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Ajustó</label>
                        <label class="flex min-h-10 items-center gap-2 text-sm text-gray-700 sm:text-base"><input id="linea-reparo" type="checkbox" class="size-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Reparó</label>
                        <label class="flex min-h-10 items-center gap-2 text-sm text-gray-700 sm:text-base"><input id="linea-cambio" type="checkbox" class="size-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Cambió</label>
                        <label class="flex min-h-10 items-center gap-2 text-sm text-gray-700 sm:text-base"><input id="linea-lubrico" type="checkbox" class="size-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Lubricó</label>
                        <label class="col-span-2 flex min-h-10 items-center gap-2 text-sm text-gray-700 sm:col-span-1 sm:text-base"><input id="linea-falta-refacc" type="checkbox" class="size-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Falta refacc.</label>
                    </div>
                </fieldset>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                    <div>
                        <label for="linea-hora-inicial" class="mb-1 block text-sm font-medium text-gray-700">Hora inicial</label>
                        <input id="linea-hora-inicial" name="HoraInicial" type="time"
                            class="min-h-11 w-full rounded-md border border-gray-300 px-3 py-2 text-base outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                    </div>
                    <div>
                        <label for="linea-hora-final" class="mb-1 block text-sm font-medium text-gray-700">Hora final</label>
                        <input id="linea-hora-final" name="HoraFinal" type="time"
                            class="min-h-11 w-full rounded-md border border-gray-300 px-3 py-2 text-base outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                    </div>
                    <div>
                        <label for="linea-total-minutos" class="mb-1 block text-sm font-medium text-gray-700">Tiempo total</label>
                        <input id="linea-total-minutos" type="text" readonly placeholder="—"
                            class="min-h-11 w-full cursor-not-allowed rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-base text-gray-600">
                    </div>
                </div>

                <div>
                    <label for="linea-comentarios" class="mb-1 block text-sm font-medium text-gray-700">Comentarios <span class="font-normal text-gray-500">(opcional)</span></label>
                    <textarea id="linea-comentarios" name="comentarios" rows="3" maxlength="500"
                        placeholder="Detalle de la intervención, refacciones pendientes, observaciones para el siguiente turno…"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900"></textarea>
                    <p class="mt-1 text-xs text-gray-500"><span id="linea-comentarios-contador">0</span>/500 caracteres.</p>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-3 sm:flex-row sm:justify-end">
                    <button id="btn-limpiar-linea" type="button"
                        class="min-h-11 w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto sm:text-base">
                        Limpiar
                    </button>
                    <button id="btn-guardar-linea" type="submit"
                        class="min-h-11 w-full rounded-md bg-gray-900 px-5 py-2 text-sm font-semibold text-white transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:text-base">
                        Guardar intervención
                    </button>
                </div>
            </form>
        </section>
        @endif

        {{-- Tabla de renglones --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-1 border-b border-gray-100 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-4 sm:py-3.5">
                <div class="min-w-0">
                    <h2 class="text-base font-bold text-gray-900 sm:text-lg">Renglones capturados</h2>
                    <p class="mt-0.5 text-sm text-gray-600">Intervenciones de la orden {{ $orden->Folio }}.</p>
                </div>
                <span id="total-lineas" class="shrink-0 text-sm font-semibold text-gray-600"></span>
            </div>
            <div class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500 xl:hidden">
                <i class="fas fa-arrows-alt-h mr-1"></i> Desliza horizontalmente para ver todas las columnas.
            </div>
            <div class="max-w-full overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Tabla de intervenciones; desplázate horizontalmente para ver todas las columnas">
                <table class="min-w-[1280px] w-full divide-y divide-gray-200 text-sm md:min-w-[1460px]">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-2.5 text-left">Clave</th>
                            <th class="min-w-40 px-3 py-2.5 text-left">Mecánico</th>
                            <th class="px-2 py-2.5 text-center">Turno</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-center">Fecha</th>
                            <th class="px-2 py-2.5 text-center">Ajustó</th>
                            <th class="px-2 py-2.5 text-center">Reparó</th>
                            <th class="px-2 py-2.5 text-center">Cambió</th>
                            <th class="px-2 py-2.5 text-center">Lubricó</th>
                            <th class="whitespace-nowrap px-2 py-2.5 text-center">Falta ref.</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-center">H. inicial</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-center">H. final</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-center">Tiempo</th>
                            <th class="min-w-48 px-3 py-2.5 text-left">Comentarios</th>
                            <th class="px-2 py-2.5 text-center">Calif.</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-left">Cve. tej.</th>
                            <th class="min-w-40 px-3 py-2.5 text-left">Nombre tejedor</th>
                            <th class="sticky right-0 whitespace-nowrap bg-gray-50 px-3 py-2.5 text-right shadow-[-4px_0_8px_-4px_rgba(0,0,0,0.08)]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="lineas-body" class="divide-y divide-gray-100 bg-white"></tbody>
                </table>
            </div>
        </section>
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
    let orden = @json($orden);
    const puedeCrear = @json($puedeCrear ?? false);
    const puedeEditar = @json($puedeEditar ?? false);
    const puedeEliminar = @json($puedeEliminar ?? false);
    const puedeCalificar = @json($puedeCalificar ?? false);
    const puedeFinalizar = @json($puedeFinalizar ?? false);
    const puedeAutorizar = @json($puedeAutorizar ?? false);
    const bloqueada = @json($bloqueada);
    const bloqueadaEdicion = @json($bloqueadaEdicion ?? false);
    const tejedorCve = @json($tejedorCve);
    const tejedorNombre = @json($tejedorNombre);
    const capturaCve = @json($usuarioCapturaCve ?? '');
    const capturaNombre = @json($usuarioCapturaNombre ?? '');
    const turnoSugerido = @json($turnoSugerido);
    const fechaSugerida = @json($fechaSugerida);

    const $ = (selector) => document.querySelector(selector);
    const lineasBody = $('#lineas-body');
    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const display = (value) => {
        const text = String(value ?? '').trim();
        return text ? escapeHtml(text) : '<span class="text-gray-400">—</span>';
    };
    const timeInputValue = (value) => value ? String(value).slice(0, 5) : '';
    const dateInputValue = (value) => value ? String(value).slice(0, 10) : '';
    const dateDisplay = (value) => {
        const fecha = dateInputValue(value);
        if (! fecha) return '<span class="text-gray-400">—</span>';
        const [anio, mes, dia] = fecha.split('-');
        return `${dia}/${mes}/${anio}`;
    };
    const iconoBooleano = (value) => value
        ? '<i class="fas fa-check text-green-600" aria-label="Sí"></i>'
        : '<span class="text-gray-300">—</span>';

    function opcionesCalificacion(seleccionada) {
        const actual = seleccionada === null || seleccionada === undefined || seleccionada === ''
            ? ''
            : String(seleccionada);
        let html = '<option value="">—</option>';
        // Escala 1-5: la misma de las estrellas con que se cierra el paro.
        for (let i = 1; i <= 5; i++) {
            html += `<option value="${i}" ${actual === String(i) ? 'selected' : ''}>${i}</option>`;
        }
        return html;
    }

    function notificar(icon, title, text = '', options = {}) {
        if (window.Swal) {
            Swal.fire({
                icon,
                title,
                text: text || undefined,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                showCloseButton: true,
                timer: options.timer ?? 2800,
                timerProgressBar: true,
            });
            return;
        }
        window.alert(text ? `${title}\n${text}` : title);
    }

    async function confirmar(title, text) {
        if (! window.Swal) return window.confirm(`${title}\n${text}`);
        const result = await Swal.fire({
            icon: 'warning',
            title,
            text,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
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

    function lineaSinCaptura(linea) {
        return ! linea.CveOperador
            && ! linea.NomOperador
            && ! linea.Ajusto
            && ! linea.Reparo
            && ! linea.Cambio
            && ! linea.Lubrico
            && ! linea.FaltaRefacc
            && ! linea.HoraInicial
            && ! linea.HoraFinal
            && (linea.Calificacion === null || linea.Calificacion === '')
            && ! linea.CveTejedor
            && ! linea.NomTejedor;
    }

    function renderLineas() {
        const lineas = orden.lineas || [];
        $('#total-lineas').textContent = `${lineas.length} ${lineas.length === 1 ? 'renglón' : 'renglones'}`;

        if (! lineas.length) {
            lineasBody.innerHTML = '<tr><td colspan="17" class="px-4 py-10 text-center text-sm text-gray-500">No hay renglones capturados.</td></tr>';
            return;
        }

        lineasBody.innerHTML = lineas.map(linea => {
            const califCell = puedeCalificar
                ? `<select data-calificacion-linea="${linea.Id}" class="min-h-9 w-16 rounded border border-gray-300 px-1 py-1 text-sm outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600">${opcionesCalificacion(linea.Calificacion)}</select>`
                : display(linea.Calificacion);

            const cveCell = puedeCalificar
                ? display(linea.CveTejedor || tejedorCve)
                : display(linea.CveTejedor);

            const nomCell = puedeCalificar
                ? display(linea.NomTejedor || tejedorNombre)
                : display(linea.NomTejedor);

            let acciones = '<span class="text-gray-400">—</span>';
            if (puedeCalificar) {
                acciones = `<button type="button" data-action="guardar-calificacion" data-linea-id="${linea.Id}" class="rounded border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-800 transition hover:bg-indigo-100">Guardar</button>`;
            } else if (! bloqueadaEdicion && (puedeEditar || puedeEliminar)) {
                acciones = `
                    ${puedeEditar ? `<button type="button" data-action="editar" data-linea-id="${linea.Id}" class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-100">Editar</button>` : ''}
                    ${puedeEliminar && lineas.length > 1 ? `<button type="button" data-action="eliminar" data-linea-id="${linea.Id}" class="ml-1 rounded border border-red-200 px-2 py-1 text-xs font-medium text-red-700 transition hover:bg-red-50">Eliminar</button>` : ''}`;
            }

            return `
            <tr class="group transition hover:bg-gray-50">
                <td class="whitespace-nowrap px-3 py-2.5 text-gray-700">${display(linea.CveOperador)}</td>
                <td class="px-3 py-2.5 font-medium text-gray-800">${display(linea.NomOperador)}</td>
                <td class="px-2 py-2.5 text-center text-gray-700">${display(linea.Turno)}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-center text-gray-700">${dateDisplay(linea.Fecha)}</td>
                <td class="px-2 py-2.5 text-center">${iconoBooleano(linea.Ajusto)}</td>
                <td class="px-2 py-2.5 text-center">${iconoBooleano(linea.Reparo)}</td>
                <td class="px-2 py-2.5 text-center">${iconoBooleano(linea.Cambio)}</td>
                <td class="px-2 py-2.5 text-center">${iconoBooleano(linea.Lubrico)}</td>
                <td class="px-2 py-2.5 text-center">${iconoBooleano(linea.FaltaRefacc)}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-center text-gray-700">${display(timeInputValue(linea.HoraInicial))}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-center text-gray-700">${display(timeInputValue(linea.HoraFinal))}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-center text-gray-700">${linea.TotalMinutos == null ? '—' : `${linea.TotalMinutos} min`}</td>
                <td class="px-3 py-2.5 text-gray-700"><span class="line-clamp-2" title="${escapeHtml(linea.comentarios ?? '')}">${display(linea.comentarios)}</span></td>
                <td class="px-2 py-2.5 text-center text-gray-700">${califCell}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-gray-700">${cveCell}</td>
                <td class="px-3 py-2.5 text-gray-800">${nomCell}</td>
                <td class="sticky right-0 whitespace-nowrap bg-white px-3 py-2.5 text-right shadow-[-4px_0_8px_-4px_rgba(0,0,0,0.08)] group-hover:bg-gray-50">${acciones}</td>
            </tr>`;
        }).join('');
    }

    function calcularMinutosEnPantalla() {
        const inicio = $('#linea-hora-inicial')?.value;
        const fin = $('#linea-hora-final')?.value;
        if (! $('#linea-total-minutos')) return;
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

    function cargarLineaEnFormulario(linea) {
        if (! $('#form-linea')) return;
        $('#form-linea').reset();
        $('#linea-id').value = linea.Id || '';
        $('#linea-operador').value = linea.CveOperador || '';
        $('#linea-nom-operador').value = linea.NomOperador || '';
        $('#linea-ajusto').checked = Boolean(linea.Ajusto);
        $('#linea-reparo').checked = Boolean(linea.Reparo);
        $('#linea-cambio').checked = Boolean(linea.Cambio);
        $('#linea-lubrico').checked = Boolean(linea.Lubrico);
        $('#linea-falta-refacc').checked = Boolean(linea.FaltaRefacc);
        $('#linea-hora-inicial').value = timeInputValue(linea.HoraInicial);
        $('#linea-hora-final').value = timeInputValue(linea.HoraFinal);
        aplicarTurno(linea.Turno);
        aplicarFecha(linea.Fecha);
        $('#linea-comentarios').value = linea.comentarios || '';
        if ($('#linea-calificacion')) $('#linea-calificacion').value = linea.Calificacion ?? '';
        if ($('#linea-cve-tejedor')) $('#linea-cve-tejedor').value = linea.CveTejedor || '';
        if ($('#linea-nom-tejedor')) $('#linea-nom-tejedor').value = linea.NomTejedor || '';
        $('#titulo-formulario').textContent = lineaSinCaptura(linea) ? 'Captura del primer renglón' : 'Editar intervención';
        $('#subtitulo-formulario').textContent = `Orden ${orden.Folio}`;
        $('#btn-guardar-linea').textContent = lineaSinCaptura(linea) ? 'Guardar primer renglón' : 'Guardar cambios';
        if (lineaSinCaptura(linea)) {
            aplicarUsuarioCaptura();
        }
        actualizarContadorComentarios();
        calcularMinutosEnPantalla();
    }

    function prepararNuevoRenglon() {
        if (! $('#form-linea')) return;
        $('#form-linea').reset();
        $('#linea-id').value = '';
        if ($('#linea-total-minutos')) $('#linea-total-minutos').value = '';
        $('#titulo-formulario').textContent = 'Capturar nueva intervención';
        $('#subtitulo-formulario').textContent = `Orden ${orden.Folio}`;
        $('#btn-guardar-linea').textContent = 'Guardar intervención';
        aplicarUsuarioCaptura();
        aplicarTurno(null);
        aplicarFecha(null);
        actualizarContadorComentarios();
        document.getElementById('seccion-captura')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        $('#linea-operador')?.focus();
    }

    /**
     * Turno del renglón: el capturado si existe, si no el sugerido por la hora del sistema.
     */
    function aplicarTurno(valor) {
        const select = $('#linea-turno');
        if (! select) return;
        const turno = [1, 2, 3, 4].includes(Number(valor)) ? Number(valor) : Number(turnoSugerido);
        select.value = String(turno);
    }

    /**
     * Fecha del renglón: la capturada si existe, si no la de hoy en planta.
     */
    function aplicarFecha(valor) {
        const input = $('#linea-fecha');
        if (! input) return;
        input.value = dateInputValue(valor) || fechaSugerida;
    }

    function actualizarContadorComentarios() {
        const contador = $('#linea-comentarios-contador');
        if (contador) contador.textContent = String(($('#linea-comentarios')?.value || '').length);
    }

    function prepararCapturaInicial() {
        if (bloqueadaEdicion || puedeCalificar) return;
        const pendiente = (orden.lineas || []).find(lineaSinCaptura);
        if (pendiente) {
            cargarLineaEnFormulario(pendiente);
            return;
        }
        prepararNuevoRenglon();
    }

    async function cargarOrden() {
        const result = await api(`${baseUrl}/${encodeURIComponent(orden.Folio)}`);
        orden = result.data;
        renderLineas();
    }


    function aplicarUsuarioCaptura() {
        if (! $('#linea-operador') || ! $('#linea-nom-operador')) return;

        const cve = String(capturaCve || '').trim();
        const nombre = String(capturaNombre || '').trim();
        if (! cve && ! nombre) return;

        if (cve && operadoresPorClave.has(cve)) {
            $('#linea-operador').value = cve;
            const op = operadoresPorClave.get(cve);
            $('#linea-nom-operador').value = op?.NomEmpl || nombre;
            return;
        }

        if (cve) {
            let option = Array.from($('#linea-operador').options).find(opt => opt.value === cve);
            if (! option) {
                option = document.createElement('option');
                option.value = cve;
                option.textContent = nombre ? `${cve} · ${nombre}` : cve;
                $('#linea-operador').appendChild(option);
            }
            $('#linea-operador').value = cve;
        }

        if (nombre) {
            $('#linea-nom-operador').value = nombre;
        }
    }

    function validarLineaCompleta(data) {
        const cve = String(data.CveOperador || '').trim();
        const nom = String(data.NomOperador || '').trim();
        const horaIni = String(data.HoraInicial || '').trim();
        const horaFin = String(data.HoraFinal || '').trim();
        const tieneTrabajo = Boolean(data.Ajusto || data.Reparo || data.Cambio || data.Lubrico || data.FaltaRefacc);

        if (! cve || ! nom) {
            return 'Selecciona el mecánico que está capturando.';
        }
        if (! [1, 2, 3, 4].includes(Number(data.Turno))) {
            return 'Selecciona el turno en el que se está registrando la intervención.';
        }
        if (! /^\d{4}-\d{2}-\d{2}$/.test(String(data.Fecha || ''))) {
            return 'Captura la fecha en la que se está registrando la intervención.';
        }
        if (! tieneTrabajo) {
            return 'Marca al menos un trabajo realizado (Ajustó, Reparó, Cambió, Lubricó o Falta refacc.).';
        }
        if (! horaIni || ! horaFin) {
            return 'Captura hora inicial y hora final para guardar el renglón.';
        }
        return null;
    }

    function datosLinea() {
        const data = Object.fromEntries(new FormData($('#form-linea')).entries());
        data.Ajusto = $('#linea-ajusto').checked;
        data.Reparo = $('#linea-reparo').checked;
        data.Cambio = $('#linea-cambio').checked;
        data.Lubrico = $('#linea-lubrico').checked;
        data.FaltaRefacc = $('#linea-falta-refacc').checked;
        return data;
    }

    async function finalizarOrden() {
        const confirmacion = await Swal.fire({
            icon: 'question',
            title: '¿Finalizar orden?',
            html: `La orden <b>${escapeHtml(orden.Folio)}</b> pasará a <b>Finalizado</b> y ya no se podrá editar.<br>Después el tejedor podrá calificarla.`,
            showCancelButton: true,
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d97706',
        });
        if (! confirmacion.isConfirmed) return;

        try {
            const result = await api(`${baseUrl}/${encodeURIComponent(orden.Folio)}/finalizar`, { method: 'POST' });
            notificar('success', result.message || 'Orden finalizada.');
            setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            notificar('error', mensajeError(error));
        }
    }

    async function autorizarOrden() {
        const confirmacion = await Swal.fire({
            icon: 'question',
            title: '¿Autorizar orden?',
            html: `La orden <b>${escapeHtml(orden.Folio)}</b> quedará <b>autorizada</b> y en solo lectura.`,
            showCancelButton: true,
            confirmButtonText: 'Sí, autorizar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#16a34a',
        });
        if (! confirmacion.isConfirmed) return;

        try {
            const result = await api(`${baseUrl}/${encodeURIComponent(orden.Folio)}/autorizar`, { method: 'POST' });
            notificar('success', result.message || 'Orden autorizada.');
            setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            notificar('error', mensajeError(error));
        }
    }

    async function guardarCalificacionTejedor(lineaId, button) {
        const select = lineasBody.querySelector(`select[data-calificacion-linea="${lineaId}"]`);
        const calificacion = select?.value;
        if (! calificacion) {
            notificar('warning', 'Selecciona una calificación del 1 al 10.');
            return;
        }

        button.disabled = true;
        button.textContent = 'Guardando…';
        try {
            const result = await api(`${baseUrl}/${encodeURIComponent(orden.Folio)}/lineas/${lineaId}`, {
                method: 'PUT',
                data: { Calificacion: Number(calificacion) },
            });
            notificar('success', result.message || 'Calificación guardada.');
            if (result.orden?.Estatus === 'Calificado') {
                setTimeout(() => window.location.reload(), 900);
                return;
            }
            await cargarOrden();
        } catch (error) {
            notificar('error', mensajeError(error));
            button.disabled = false;
            button.textContent = 'Guardar';
        }
    }

    $('#form-linea')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = $('#btn-guardar-linea');
        const lineaId = $('#linea-id').value;
        const payload = datosLinea();
        const errorValidacion = validarLineaCompleta(payload);
        if (errorValidacion) {
            notificar('warning', errorValidacion);
            return;
        }

        button.disabled = true;
        button.textContent = 'Guardando…';

        try {
            const result = await api(lineaId
                ? `${baseUrl}/${encodeURIComponent(orden.Folio)}/lineas/${lineaId}`
                : `${baseUrl}/${encodeURIComponent(orden.Folio)}/lineas`, {
                method: lineaId ? 'PUT' : 'POST',
                data: payload,
            });

            await cargarOrden();
            prepararNuevoRenglon();
            notificar('success', result.message || 'Intervención guardada correctamente.');
        } catch (error) {
            notificar('error', mensajeError(error));
        } finally {
            button.disabled = false;
            if ($('#linea-id').value) $('#btn-guardar-linea').textContent = 'Guardar cambios';
        }
    });

    lineasBody.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (! button) return;

        const linea = (orden.lineas || []).find(item => Number(item.Id) === Number(button.dataset.lineaId));
        if (! linea) return;

        if (button.dataset.action === 'guardar-calificacion') {
            await guardarCalificacionTejedor(linea.Id, button);
            return;
        }

        if (button.dataset.action === 'editar') {
            cargarLineaEnFormulario(linea);
            document.getElementById('seccion-captura')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        if (button.dataset.action === 'eliminar') {
            if (! await confirmar('¿Eliminar renglón?', 'Esta intervención se quitará de la orden.')) return;
            try {
                const result = await api(`${baseUrl}/${encodeURIComponent(orden.Folio)}/lineas/${linea.Id}`, { method: 'DELETE' });
                await cargarOrden();
                prepararCapturaInicial();
                notificar('success', result.message);
            } catch (error) {
                notificar('error', mensajeError(error));
            }
        }
    });

    $('#btn-nuevo-renglon')?.addEventListener('click', prepararNuevoRenglon);
    $('#btn-limpiar-linea')?.addEventListener('click', prepararCapturaInicial);
    $('#linea-operador')?.addEventListener('change', () => {
        const clave = $('#linea-operador').value;
        if (! clave) {
            $('#linea-nom-operador').value = '';
            return;
        }
        const operador = operadoresPorClave.get(clave);
        // Sin catálogo (usuario en sesión inyectado a mano) se conserva el nombre precargado.
        if (operador) $('#linea-nom-operador').value = operador.NomEmpl || '';
    });
    $('#linea-comentarios')?.addEventListener('input', actualizarContadorComentarios);
    $('#linea-hora-inicial')?.addEventListener('input', calcularMinutosEnPantalla);
    $('#linea-hora-final')?.addEventListener('input', calcularMinutosEnPantalla);
    document.getElementById('btn-finalizar-orden')?.addEventListener('click', finalizarOrden);
    document.getElementById('btn-autorizar-orden')?.addEventListener('click', autorizarOrden);

    renderLineas();
    if (! bloqueadaEdicion && ! puedeCalificar && puedeEditar) prepararCapturaInicial();

    if (bloqueada) {
        notificar('success', 'Orden autorizada', 'Esta orden quedó en solo lectura.', { timer: 4000 });
    } else if (puedeCalificar) {
        notificar('info', 'Calificar intervenciones', 'Elige 1–10 en cada renglón y guarda. Cuando todos estén calificados, la orden pasará a Calificado.', { timer: 5000 });
    } else if (orden.Estatus === 'Terminado') {
        notificar('info', 'Orden finalizada', 'Pendiente de calificación del tejedor.', { timer: 4000 });
    } else if (orden.Estatus === 'Calificado') {
        notificar('info', 'Orden calificada', 'El supervisor puede autorizarla.', { timer: 4000 });
    }
});
</script>
@endpush
