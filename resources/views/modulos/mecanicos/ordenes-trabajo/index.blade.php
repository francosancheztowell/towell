@extends('layouts.app')

@section('page-title', 'Órdenes de trabajo')

@section('navbar-right')
    <div class="flex items-center gap-2">
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
<div class="flex h-[calc(100vh-64px)] w-full flex-col overflow-hidden p-3 sm:p-4 md:p-5">
    <div class="mx-auto flex min-h-0 w-full max-w-[96rem] flex-1 flex-col">
        <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div
                class="min-h-0 flex-1 overflow-auto overscroll-contain"
                tabindex="0"
                aria-label="Tabla de órdenes de trabajo"
            >
                <table id="tabla-ordenes" class="w-full min-w-[1100px] divide-y divide-gray-200 text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 shadow-sm">
                        <tr>
                            <th class="whitespace-nowrap bg-gray-50 px-5 py-4">Folio</th>
                            <th class="whitespace-nowrap bg-gray-50 px-5 py-4">Status</th>
                            <th class="whitespace-nowrap bg-gray-50 px-5 py-4">Fecha</th>
                            <th class="whitespace-nowrap bg-gray-50 px-5 py-4">Telar</th>
                            <th class="min-w-52 bg-gray-50 px-5 py-4">Falla</th>
                            <th class="whitespace-nowrap bg-gray-50 px-5 py-4">Folio paro</th>
                            <th class="whitespace-nowrap bg-gray-50 px-5 py-4"># Orden</th>
                            <th class="whitespace-nowrap bg-gray-50 px-5 py-4 text-center">Turno</th>
                            <th class="whitespace-nowrap bg-gray-50 px-5 py-4 text-center">Mecánicos</th>
                            <th class="whitespace-nowrap bg-gray-50 px-5 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="ordenes-body" class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td colspan="10" class="px-5 py-12 text-center text-sm text-gray-500">
                                Cargando órdenes de trabajo…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div id="modal-filtros" class="fixed inset-0 z-50 hidden items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-filtros-ordenes">
    <div class="w-full max-w-md rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-5">
            <h2 id="titulo-modal-filtros-ordenes" class="text-lg font-bold text-gray-900">Filtrar órdenes</h2>
            <button type="button" data-close-modal="modal-filtros" class="flex h-10 w-10 items-center justify-center rounded-full text-2xl leading-none text-gray-500 transition hover:bg-gray-100 hover:text-gray-900" aria-label="Cerrar">&times;</button>
        </div>

        <div class="space-y-4 p-4 sm:p-5">
            <div>
                <label for="filtro-fecha" class="mb-1 block text-xs font-medium text-gray-700">Fecha</label>
                <input id="filtro-fecha" type="date"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                <p class="mt-1 text-xs text-gray-500">Dejar vacío para ver todos los registros.</p>
            </div>

            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Estatus</p>
            <div class="grid grid-cols-2 gap-3" id="filtro-estatus-opciones">
                <button type="button" data-estatus="" class="filtro-estatus-btn min-h-14 rounded-xl border border-gray-300 bg-white px-3 py-3 text-base font-bold text-gray-700 transition active:scale-[0.98]">Todos</button>
                <button type="button" data-estatus="Activo" class="filtro-estatus-btn min-h-14 rounded-xl border border-blue-200 bg-blue-50 px-3 py-3 text-base font-bold text-blue-800 transition active:scale-[0.98]">Activo</button>
                <button type="button" data-estatus="Terminado" class="filtro-estatus-btn min-h-14 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-base font-bold text-amber-800 transition active:scale-[0.98]">Finalizado</button>
                <button type="button" data-estatus="Calificado" class="filtro-estatus-btn min-h-14 rounded-xl border border-violet-200 bg-violet-50 px-3 py-3 text-base font-bold text-violet-800 transition active:scale-[0.98]">Calificado</button>
                <button type="button" data-estatus="Autorizado" class="filtro-estatus-btn min-h-14 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-3 text-base font-bold text-emerald-800 transition active:scale-[0.98]">Autorizado</button>
                <button type="button" data-estatus="Cancelado" class="filtro-estatus-btn min-h-14 rounded-xl border border-red-200 bg-red-50 px-3 py-3 text-base font-bold text-red-800 transition active:scale-[0.98]">Cancelado</button>
            </div>
        </div>
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
                        <label for="select-telar-paro" class="mb-1 block text-xs font-semibold text-blue-900">Telar <span class="font-normal">(opcional)</span></label>
                        <select id="select-telar-paro"
                            class="w-full rounded-md border border-blue-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                            <option value="">Cargando telares…</option>
                        </select>
                    </div>
                    <div>
                        <label for="select-paro-folio" class="mb-1 block text-xs font-semibold text-blue-900">Folio de paro <span class="font-normal">(opcional)</span></label>
                        <select id="select-paro-folio" disabled
                            class="w-full rounded-md border border-blue-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 disabled:cursor-not-allowed disabled:bg-gray-100">
                            <option value="">Seleccione telar primero</option>
                        </select>
                    </div>
                </div>
                <p class="mt-2 text-xs text-blue-800">Seleccione telar y folio de paro para precargar falla, fechas, turno y orden; o capture manualmente los campos de abajo.</p>
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
                        <option value="Calificado">Calificado</option>
                        <option value="Autorizado">Autorizado</option>
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
                    {{-- Calificar solo en captura, después de Finalizar --}}
                    <select id="linea-calificacion" name="Calificacion" disabled
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500">
                        <option value="">Sin calificar</option>
                        @for ($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="linea-cve-tejedor" class="mb-1 block text-xs font-medium text-gray-700">Clave tejedor</label>
                    <input id="linea-cve-tejedor" name="CveTejedor" maxlength="30" disabled
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500">
                </div>
                <div>
                    <label for="linea-nom-tejedor" class="mb-1 block text-xs font-medium text-gray-700">Firma / nombre del tejedor</label>
                    <input id="linea-nom-tejedor" name="NomTejedor" maxlength="150" disabled
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500">
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
    const fechaActual = @json($fechaInicial);
    const operadores = @json($operadores);
    const puedeCrear = @json($puedeCrear);
    const puedeEditar = @json($puedeEditar);
    const puedeEliminar = @json($puedeEliminar);
    const puedeRegistrar = @json($puedeRegistrar);
    const modoTejedor = @json($modoTejedor);
    const esTejedor = modoTejedor; // compatibilidad con lógica previa de UI
    const operadoresPorClave = new Map(operadores.map(operador => [String(operador.CveEmpl), operador]));
    const state = { ordenes: [], orden: null, paros: [], filtroEstatus: '' };

    const $ = (selector) => document.querySelector(selector);
    const ordenesBody = $('#ordenes-body');
    const lineasBody = $('#lineas-body');
    const modalCabecera = $('#modal-cabecera');
    const modalDetalle = $('#modal-detalle');
    const modalLinea = $('#modal-linea');
    const modalFiltros = $('#modal-filtros');

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

        return `<span class="inline-flex rounded-full px-3 py-1.5 text-xs font-bold ${classes}">${escapeHtml(label)}</span>`;
    }

    function turnoBadge(turno) {
        const value = String(turno ?? '').trim();
        const content = value !== '' ? escapeHtml(value) : '—';

        return `<span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-700">${content}</span>`;
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
        const fecha = $('#filtro-fecha')?.value;
        if (fecha) params.set('fecha', fecha);
        if (state.filtroEstatus) params.set('estatus', state.filtroEstatus);
        return params;
    }

    function actualizarBotonesFiltroEstatus() {
        const base = 'filtro-estatus-btn min-h-14 rounded-xl px-3 py-3 text-base font-bold transition active:scale-[0.98]';

        document.querySelectorAll('.filtro-estatus-btn').forEach(button => {
            const estatus = button.dataset.estatus ?? '';
            const activo = estatus === state.filtroEstatus;

            if (estatus === '') {
                button.className = activo
                    ? `${base} bg-gray-900 text-white shadow`
                    : `${base} border border-gray-300 bg-white text-gray-700`;
            } else if (estatus === 'Activo') {
                button.className = activo
                    ? `${base} bg-blue-600 text-white shadow`
                    : `${base} border border-blue-200 bg-blue-50 text-blue-800`;
            } else if (estatus === 'Terminado') {
                button.className = activo
                    ? `${base} bg-amber-500 text-white shadow`
                    : `${base} border border-amber-200 bg-amber-50 text-amber-800`;
            } else if (estatus === 'Calificado') {
                button.className = activo
                    ? `${base} bg-violet-600 text-white shadow`
                    : `${base} border border-violet-200 bg-violet-50 text-violet-800`;
            } else if (estatus === 'Autorizado') {
                button.className = activo
                    ? `${base} bg-emerald-600 text-white shadow`
                    : `${base} border border-emerald-200 bg-emerald-50 text-emerald-800`;
            } else if (estatus === 'Cancelado') {
                button.className = activo
                    ? `${base} bg-red-600 text-white shadow`
                    : `${base} border border-red-200 bg-red-50 text-red-800`;
            }
        });
    }

    function renderOrdenes() {
        if (! state.ordenes.length) {
            ordenesBody.innerHTML = '<tr><td colspan="10" class="px-5 py-12 text-center text-sm text-gray-500">No hay órdenes con los filtros seleccionados.</td></tr>';
            return;
        }

        ordenesBody.innerHTML = state.ordenes.map(orden => {
            const folio = escapeHtml(orden.Folio);
            const capturaUrl = `${baseUrl}/${encodeURIComponent(orden.Folio)}/captura`;
            const estatus = orden.Estatus || 'Activo';
            const bloqueadaEdicion = ['Terminado', 'Calificado', 'Autorizado'].includes(estatus);
            let accionPrincipal = '';
            if (modoTejedor) {
                accionPrincipal = estatus === 'Terminado'
                    ? `<a href="${capturaUrl}" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700" title="Calificar renglones"><i class="fas fa-star"></i> Calificar</a>`
                    : `<a href="${capturaUrl}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-100" title="Ver renglones"><i class="fas fa-eye"></i> Ver</a>`;
            } else if (puedeEditar && ! bloqueadaEdicion) {
                accionPrincipal = `<a href="${capturaUrl}" class="inline-flex items-center gap-1.5 rounded-md bg-gray-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-black" title="Editar / capturar"><i class="fas fa-pen"></i> Editar</a>`;
            } else {
                accionPrincipal = `<a href="${capturaUrl}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-100" title="Ver"><i class="fas fa-eye"></i> Ver</a>`;
            }

            return `
            <tr class="transition hover:bg-gray-50">
                <td class="whitespace-nowrap px-5 py-4">
                    <span class="inline-flex items-center rounded-md bg-gray-900 px-2.5 py-1 text-sm font-bold text-white">${folio}</span>
                </td>
                <td class="whitespace-nowrap px-5 py-4">${statusBadge(orden.Estatus)}</td>
                <td class="whitespace-nowrap px-5 py-4 text-gray-700">
                    <div class="font-semibold text-gray-900">${dateDisplay(orden.Fecha)}</div>
                </td>
                <td class="whitespace-nowrap px-5 py-4 text-gray-700">${display(orden.TelarId)}</td>
                <td class="min-w-52 px-5 py-4 font-semibold text-gray-900">${display(orden.Falla)}</td>
                <td class="whitespace-nowrap px-5 py-4 text-gray-700">${display(orden.FolioParo)}</td>
                <td class="whitespace-nowrap px-5 py-4 text-gray-700">${display(orden.Orden)}</td>
                <td class="px-5 py-4 text-center">${turnoBadge(orden.Turno)}</td>
                <td class="px-5 py-4 text-center">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-700">${orden.lineas_count ?? orden.lineas?.length ?? 0}</span>
                </td>
                <td class="whitespace-nowrap px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        ${modoTejedor ? '' : `<button type="button" data-action="ver-orden" data-folio="${folio}"
                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-100"
                            title="Ver (solo lectura)">
                            <i class="fas fa-eye"></i> Ver
                        </button>`}
                        ${accionPrincipal}
                    </div>
                </td>
            </tr>
        `;
        }).join('');
    }

    async function cargarOrdenes() {
        ordenesBody.innerHTML = '<tr><td colspan="10" class="px-5 py-12 text-center text-sm text-gray-500">Cargando órdenes de trabajo…</td></tr>';

        try {
            const result = await api(`${baseUrl}/registros?${obtenerFiltros().toString()}`);
            state.ordenes = result.data || [];
            renderOrdenes();
        } catch (error) {
            ordenesBody.innerHTML = `<tr><td colspan="10" class="px-5 py-12 text-center text-sm text-red-600">${escapeHtml(mensajeError(error))}</td></tr>`;
        }
    }

    async function cargarDetalle(folio) {
        try {
            const result = await api(`${baseUrl}/${encodeURIComponent(folio)}`);
            state.orden = result.data;
            $('#titulo-modal-detalle').textContent = `Orden ${state.orden.Folio}`;
            $('#detalle-resumen').textContent = `Telar ${state.orden.TelarId || '—'} · ${state.orden.Falla || 'Sin descripción'} · Turno ${state.orden.Turno || '—'}`;

            // Tras Finalizar/Calificar/Autorizar no se edita desde el modal.
            const bloqueadaEdicion = ['Terminado', 'Calificado', 'Autorizado'].includes(state.orden.Estatus);
            $('#btn-editar-cabecera').classList.toggle('hidden', bloqueadaEdicion || ! puedeEditar);
            $('#btn-agregar-linea').classList.toggle('hidden', bloqueadaEdicion || ! puedeCrear);
            $('#btn-eliminar-orden').classList.toggle('hidden', bloqueadaEdicion || ! puedeEliminar);

            renderLineas();
            abrirModal(modalDetalle);
        } catch (error) {
            notificar('error', mensajeError(error));
        }
    }

    function renderLineas() {
        const lineas = state.orden?.lineas || [];
        const bloqueadaEdicion = ['Terminado', 'Calificado', 'Autorizado'].includes(state.orden?.Estatus);
        const puedeEditarLinea = ! bloqueadaEdicion && puedeEditar;
        const puedeEliminarLinea = ! bloqueadaEdicion && puedeEliminar;
        if (! lineas.length) {
            lineasBody.innerHTML = '<tr><td colspan="12" class="px-4 py-8 text-center text-sm text-gray-500">No hay renglones.</td></tr>';
            return;
        }

        lineasBody.innerHTML = lineas.map(linea => {
            let acciones = '<span class="text-gray-400">—</span>';
            if (puedeEditarLinea || puedeEliminarLinea) {
                acciones = `
                    ${puedeEditarLinea ? `<button type="button" data-action="editar-linea" data-linea-id="${linea.Id}" class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100">Editar</button>` : ''}
                    ${puedeEliminarLinea && lineas.length > 1 ? `<button type="button" data-action="eliminar-linea" data-linea-id="${linea.Id}" class="ml-1 rounded border border-red-200 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Eliminar</button>` : ''}
                `;
            }

            return `
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
                <td class="whitespace-nowrap px-3 py-3 text-right">${acciones}</td>
            </tr>`;
        }).join('');
    }

    async function cargarParosActivos() {
        const selectTelar = $('#select-telar-paro');
        const selectParo = $('#select-paro-folio');
        selectTelar.innerHTML = '<option value="">Cargando telares…</option>';
        selectParo.innerHTML = '<option value="">Seleccione telar primero</option>';
        selectParo.disabled = true;

        try {
            const result = await api(`${baseUrl}/paros-activos`);
            state.paros = result.data || [];
            poblarSelectTelares();
            poblarSelectParosPorTelar('');
            limpiarCabeceraParaCapturaManual();
        } catch (error) {
            selectTelar.innerHTML = '<option value="">No fue posible cargar telares</option>';
            selectParo.innerHTML = '<option value="">Seleccione telar primero</option>';
            selectParo.disabled = true;
            limpiarCabeceraParaCapturaManual();
        }
    }

    function telaresConParosActivos() {
        const telares = [...new Set(
            state.paros
                .map(paro => String(paro.MaquinaId ?? '').trim())
                .filter(Boolean),
        )];

        return telares.sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
    }

    function poblarSelectTelares() {
        const select = $('#select-telar-paro');
        select.innerHTML = '<option value="">Seleccione telar</option>';

        telaresConParosActivos().forEach(telar => {
            const option = document.createElement('option');
            option.value = telar;
            option.textContent = `Telar ${telar}`;
            select.appendChild(option);
        });

        const manualOption = document.createElement('option');
        manualOption.value = '__manual__';
        manualOption.textContent = 'Capturar manualmente';
        select.appendChild(manualOption);
    }

    function poblarSelectParosPorTelar(telarId) {
        const select = $('#select-paro-folio');
        select.innerHTML = '<option value="">Seleccione folio de paro</option>';

        if (! telarId || telarId === '__manual__') {
            select.disabled = true;
            select.innerHTML = '<option value="">Seleccione telar primero</option>';
            return;
        }

        const parosTelar = state.paros.filter(paro => String(paro.MaquinaId ?? '') === String(telarId));

        if (! parosTelar.length) {
            select.disabled = true;
            select.innerHTML = '<option value="">Sin paros activos para este telar</option>';
            return;
        }

        select.disabled = false;
        parosTelar.forEach(paro => {
            const option = document.createElement('option');
            option.value = paro.Id;
            const falla = String(paro.Falla ?? '').trim();
            option.textContent = falla !== '' ? `${paro.Folio} · ${falla}` : String(paro.Folio);
            select.appendChild(option);
        });
    }

    function resetearSelectsParo() {
        $('#select-telar-paro').value = '';
        poblarSelectParosPorTelar('');
    }

    function habilitarSelectsParo(habilitar) {
        const bloque = $('#bloque-seleccion-paro');
        if (bloque) bloque.classList.toggle('hidden', ! habilitar);

        $('#select-telar-paro').disabled = ! habilitar;
        if (! habilitar) {
            $('#select-paro-folio').disabled = true;
        }
    }

    function resetearCabecera() {
        $('#form-cabecera').reset();
        $('#cabecera-folio').value = '';
        $('#cabecera-fecha').value = fechaActual;
        $('#cabecera-estatus').value = 'Activo';
        $('#titulo-modal-cabecera').textContent = 'Nueva orden de trabajo';
        $('#subtitulo-modal-cabecera').textContent = 'El folio se asigna al guardar.';
        $('#btn-guardar-cabecera').textContent = 'Guardar orden';
        habilitarSelectsParo(true);
        resetearSelectsParo();
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
        habilitarSelectsParo(false);
        abrirModal(modalCabecera);
    }

    function onTelarParoChange() {
        const telar = $('#select-telar-paro').value;
        $('#select-paro-folio').value = '';

        if (telar === '__manual__' || telar === '') {
            poblarSelectParosPorTelar('');
            limpiarCabeceraParaCapturaManual();
            return;
        }

        $('#cabecera-telar').value = telar;
        limpiarCamposDesdeParo();
        poblarSelectParosPorTelar(telar);
    }

    function aplicarParoSeleccionado() {
        const paro = state.paros.find(item => String(item.Id) === $('#select-paro-folio').value);
        if (! paro) {
            limpiarCamposDesdeParo();
            const telar = $('#select-telar-paro').value;
            if (telar && telar !== '__manual__') {
                $('#cabecera-telar').value = telar;
            }
            return;
        }

        $('#cabecera-fecha').value = fechaActual;
        $('#cabecera-telar').value = paro.MaquinaId || '';
        $('#cabecera-folio-paro').value = paro.Folio || '';
        $('#cabecera-falla').value = paro.Falla || '';
        $('#cabecera-fecha-paro').value = dateInputValue(paro.Fecha);
        $('#cabecera-hora-paro').value = timeInputValue(paro.Hora);
        $('#cabecera-orden').value = paro.OrdenTrabajo || '';
        $('#cabecera-turno').value = paro.Turno || '';
        $('#cabecera-estatus').value = 'Activo';
    }

    function limpiarCamposDesdeParo() {
        [
            'cabecera-folio-paro',
            'cabecera-falla',
            'cabecera-fecha-paro',
            'cabecera-hora-paro',
            'cabecera-orden',
            'cabecera-turno',
        ].forEach(id => {
            document.getElementById(id).value = '';
        });

        $('#cabecera-fecha').value = fechaActual;
        $('#cabecera-estatus').value = 'Activo';
    }

    function limpiarCabeceraParaCapturaManual() {
        [
            'cabecera-telar',
            'cabecera-folio-paro',
            'cabecera-falla',
            'cabecera-fecha-paro',
            'cabecera-hora-paro',
            'cabecera-orden',
            'cabecera-turno',
        ].forEach(id => {
            document.getElementById(id).value = '';
        });

        $('#cabecera-fecha').value = fechaActual;
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

            if (! folio) {
                window.location.assign(`${baseUrl}/${encodeURIComponent(result.data.Folio)}/captura`);
                return;
            }

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
        const verButton = event.target.closest('[data-action="ver-orden"]');
        if (verButton) {
            cargarDetalle(verButton.dataset.folio);
        }
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

    $('#btn-nueva-orden')?.addEventListener('click', abrirNuevaOrden);
    $('#btn-agregar-linea').addEventListener('click', abrirNuevaLinea);
    $('#btn-editar-cabecera').addEventListener('click', abrirEdicionCabecera);
    $('#select-telar-paro').addEventListener('change', onTelarParoChange);
    $('#select-paro-folio').addEventListener('change', aplicarParoSeleccionado);
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

    [modalCabecera, modalDetalle, modalLinea, modalFiltros].forEach(modal => {
        modal.addEventListener('click', event => {
            if (event.target === modal) cerrarModal(modal);
        });
    });

    $('#btn-filtrar-ordenes-trabajo')?.addEventListener('click', () => {
        actualizarBotonesFiltroEstatus();
        abrirModal(modalFiltros);
    });

    $('#filtro-fecha')?.addEventListener('change', () => {
        cargarOrdenes();
    });

    document.querySelectorAll('.filtro-estatus-btn').forEach(button => {
        button.addEventListener('click', () => {
            state.filtroEstatus = button.dataset.estatus ?? '';
            actualizarBotonesFiltroEstatus();
            cerrarModal(modalFiltros);
            cargarOrdenes();
        });
    });

    cargarOrdenes();
    actualizarBotonesFiltroEstatus();
});
</script>
@endpush
