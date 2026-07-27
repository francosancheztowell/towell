@extends('layouts.app')

@section('page-title', 'Captura de orden de trabajo')

@section('content')
<div class="w-full p-3 sm:p-4 md:p-6 lg:p-8">
    <div class="mx-auto max-w-[110rem] space-y-4">
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <a href="{{ route('mecanicos.ordenes-trabajo.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 transition hover:text-gray-900">
                        <i class="fas fa-arrow-left"></i>
                        Volver a órdenes de trabajo
                    </a>
                    <h1 class="mt-2 text-xl font-bold text-gray-900 md:text-2xl">Captura de intervenciones</h1>
                    <p class="mt-1 text-sm text-gray-600">Agrega un renglón por cada mecánico que intervenga en esta orden.</p>
                </div>
                <span class="inline-flex w-fit rounded-md bg-gray-900 px-3 py-2 text-sm font-bold text-white">Folio {{ $orden->Folio }}</span>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 text-sm sm:grid-cols-3 xl:grid-cols-6">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Fecha</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ optional($orden->Fecha)->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Telar</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $orden->TelarId ?: '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Falla</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $orden->Falla ?: 'Sin descripción' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Turno</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $orden->Turno ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500"># Orden</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $orden->Orden ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        <section id="seccion-captura" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
            <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="titulo-formulario" class="text-lg font-bold text-gray-900">Capturar intervención</h2>
                    <p id="subtitulo-formulario" class="mt-1 text-sm text-gray-600">Registra las actividades realizadas por el mecánico.</p>
                </div>
                <button id="btn-nuevo-renglon" type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">
                    <i class="fas fa-plus"></i>
                    Nuevo renglón
                </button>
            </div>

            <form id="form-linea" class="mt-5">
                <input id="linea-id" type="hidden">

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="linea-operador" class="mb-1 block text-xs font-medium text-gray-700">Clave</label>
                        <select id="linea-operador" name="CveOperador"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                            <option value="">Seleccione</option>
                            @foreach ($operadores as $operador)
                                <option value="{{ $operador->CveEmpl }}">{{ $operador->CveEmpl }} · {{ $operador->NomEmpl }}@if ($operador->Turno) (T{{ $operador->Turno }}) @endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="linea-nom-operador" class="mb-1 block text-xs font-medium text-gray-700">Mecánico</label>
                        <input id="linea-nom-operador" name="NomOperador" maxlength="150"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                    </div>
                </div>

                <fieldset class="mt-5 rounded-md border border-gray-200 p-4">
                    <legend class="px-1 text-sm font-semibold text-gray-800">Trabajo realizado</legend>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-ajusto" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Ajustó</label>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-reparo" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Reparó</label>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-cambio" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Cambió</label>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-lubrico" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Lubricó</label>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input id="linea-falta-refacc" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900"> Falta refacc.</label>
                    </div>
                </fieldset>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                        <label for="linea-cve-tejedor" class="mb-1 block text-xs font-medium text-gray-700">Cve. tejedor</label>
                        <input id="linea-cve-tejedor" name="CveTejedor" maxlength="30"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                    </div>
                    <div>
                        <label for="linea-nom-tejedor" class="mb-1 block text-xs font-medium text-gray-700">Nombre / firma tejedor</label>
                        <input id="linea-nom-tejedor" name="NomTejedor" maxlength="150"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                    <button id="btn-limpiar-linea" type="button" class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">Limpiar</button>
                    <button id="btn-guardar-linea" type="submit" class="w-full rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">Guardar intervención</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-1 border-b border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h2 class="font-bold text-gray-900">Renglones capturados</h2>
                    <p class="mt-1 text-sm text-gray-600">Cada registro corresponde a una intervención en la orden {{ $orden->Folio }}.</p>
                </div>
                <span id="total-lineas" class="text-sm font-semibold text-gray-600"></span>
            </div>
            <div class="border-b border-gray-100 px-4 py-2 text-xs text-gray-500 lg:hidden"><i class="fas fa-arrows-alt-h mr-1"></i> Desliza horizontalmente para consultar todas las columnas.</div>
            <div class="max-w-full overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Tabla de intervenciones; desplázate horizontalmente para ver todas las columnas">
                <table class="min-w-[1660px] divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-3 text-left">Clave</th>
                            <th class="min-w-44 px-3 py-3 text-left">Mecánico</th>
                            <th class="px-3 py-3 text-center">Ajustó</th>
                            <th class="px-3 py-3 text-center">Reparó</th>
                            <th class="px-3 py-3 text-center">Cambió</th>
                            <th class="px-3 py-3 text-center">Lubricó</th>
                            <th class="px-3 py-3 text-center">Falta refacc.</th>
                            <th class="whitespace-nowrap px-3 py-3 text-center">Hora inicial</th>
                            <th class="whitespace-nowrap px-3 py-3 text-center">Hora final</th>
                            <th class="whitespace-nowrap px-3 py-3 text-center">Tiempo total</th>
                            <th class="px-3 py-3 text-center">Calificación</th>
                            <th class="whitespace-nowrap px-3 py-3 text-left">Cve. tejedor</th>
                            <th class="min-w-44 px-3 py-3 text-left">Nombre tejedor</th>
                            <th class="whitespace-nowrap px-3 py-3 text-right">Acciones</th>
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
    const iconoBooleano = (value) => value
        ? '<i class="fas fa-check text-green-600" aria-label="Sí"></i>'
        : '<span class="text-gray-300">—</span>';

    function notificar(icon, title) {
        if (window.Swal) {
            Swal.fire({ icon, title, toast: true, position: 'top-end', showConfirmButton: false, timer: 2800, timerProgressBar: true });
            return;
        }

        window.alert(title);
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
            lineasBody.innerHTML = '<tr><td colspan="14" class="px-4 py-10 text-center text-sm text-gray-500">No hay renglones capturados.</td></tr>';
            return;
        }

        lineasBody.innerHTML = lineas.map(linea => `
            <tr class="transition hover:bg-gray-50">
                <td class="whitespace-nowrap px-3 py-3 text-gray-700">${display(linea.CveOperador)}</td>
                <td class="px-3 py-3 font-medium text-gray-800">${display(linea.NomOperador)}</td>
                <td class="px-3 py-3 text-center">${iconoBooleano(linea.Ajusto)}</td>
                <td class="px-3 py-3 text-center">${iconoBooleano(linea.Reparo)}</td>
                <td class="px-3 py-3 text-center">${iconoBooleano(linea.Cambio)}</td>
                <td class="px-3 py-3 text-center">${iconoBooleano(linea.Lubrico)}</td>
                <td class="px-3 py-3 text-center">${iconoBooleano(linea.FaltaRefacc)}</td>
                <td class="whitespace-nowrap px-3 py-3 text-center text-gray-700">${display(timeInputValue(linea.HoraInicial))}</td>
                <td class="whitespace-nowrap px-3 py-3 text-center text-gray-700">${display(timeInputValue(linea.HoraFinal))}</td>
                <td class="whitespace-nowrap px-3 py-3 text-center text-gray-700">${linea.TotalMinutos == null ? '—' : `${linea.TotalMinutos} min`}</td>
                <td class="px-3 py-3 text-center text-gray-700">${display(linea.Calificacion)}</td>
                <td class="whitespace-nowrap px-3 py-3 text-gray-700">${display(linea.CveTejedor)}</td>
                <td class="px-3 py-3 text-gray-800">${display(linea.NomTejedor)}</td>
                <td class="whitespace-nowrap px-3 py-3 text-right">
                    <button type="button" data-action="editar" data-linea-id="${linea.Id}" class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-100">Editar</button>
                    ${lineas.length > 1 ? `<button type="button" data-action="eliminar" data-linea-id="${linea.Id}" class="ml-1 rounded border border-red-200 px-2 py-1 text-xs font-medium text-red-700 transition hover:bg-red-50">Eliminar</button>` : ''}
                </td>
            </tr>
        `).join('');
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

    function cargarLineaEnFormulario(linea) {
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
        $('#linea-calificacion').value = linea.Calificacion ?? '';
        $('#linea-cve-tejedor').value = linea.CveTejedor || '';
        $('#linea-nom-tejedor').value = linea.NomTejedor || '';
        $('#titulo-formulario').textContent = lineaSinCaptura(linea) ? 'Captura del primer renglón' : 'Editar intervención';
        $('#subtitulo-formulario').textContent = `Orden ${orden.Folio}`;
        $('#btn-guardar-linea').textContent = lineaSinCaptura(linea) ? 'Guardar primer renglón' : 'Guardar cambios';
        calcularMinutosEnPantalla();
    }

    function prepararNuevoRenglon() {
        $('#form-linea').reset();
        $('#linea-id').value = '';
        $('#linea-total-minutos').value = '';
        $('#titulo-formulario').textContent = 'Capturar nueva intervención';
        $('#subtitulo-formulario').textContent = `Orden ${orden.Folio}`;
        $('#btn-guardar-linea').textContent = 'Guardar intervención';
        document.getElementById('seccion-captura').scrollIntoView({ behavior: 'smooth', block: 'start' });
        $('#linea-operador').focus();
    }

    function prepararCapturaInicial() {
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

    function datosLinea() {
        const data = Object.fromEntries(new FormData($('#form-linea')).entries());
        data.Ajusto = $('#linea-ajusto').checked;
        data.Reparo = $('#linea-reparo').checked;
        data.Cambio = $('#linea-cambio').checked;
        data.Lubrico = $('#linea-lubrico').checked;
        data.FaltaRefacc = $('#linea-falta-refacc').checked;
        return data;
    }

    $('#form-linea').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = $('#btn-guardar-linea');
        const lineaId = $('#linea-id').value;
        button.disabled = true;
        button.textContent = 'Guardando…';

        try {
            const result = await api(lineaId
                ? `${baseUrl}/${encodeURIComponent(orden.Folio)}/lineas/${lineaId}`
                : `${baseUrl}/${encodeURIComponent(orden.Folio)}/lineas`, {
                method: lineaId ? 'PUT' : 'POST',
                data: datosLinea(),
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

        if (button.dataset.action === 'editar') {
            cargarLineaEnFormulario(linea);
            document.getElementById('seccion-captura').scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        if (! await confirmar('¿Eliminar renglón?', 'Esta intervención se quitará de la orden.')) return;

        try {
            const result = await api(`${baseUrl}/${encodeURIComponent(orden.Folio)}/lineas/${linea.Id}`, { method: 'DELETE' });
            await cargarOrden();
            prepararCapturaInicial();
            notificar('success', result.message);
        } catch (error) {
            notificar('error', mensajeError(error));
        }
    });

    $('#btn-nuevo-renglon').addEventListener('click', prepararNuevoRenglon);
    $('#btn-limpiar-linea').addEventListener('click', prepararCapturaInicial);
    $('#linea-operador').addEventListener('change', () => {
        const operador = operadoresPorClave.get($('#linea-operador').value);
        if (operador) $('#linea-nom-operador').value = operador.NomEmpl || '';
    });
    $('#linea-hora-inicial').addEventListener('input', calcularMinutosEnPantalla);
    $('#linea-hora-final').addEventListener('input', calcularMinutosEnPantalla);

    renderLineas();
    prepararCapturaInicial();
});
</script>
@endpush
