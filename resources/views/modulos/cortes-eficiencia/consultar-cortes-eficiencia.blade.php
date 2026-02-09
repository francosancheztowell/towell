@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Cortes de Eficiencia')

@php
    use Carbon\Carbon;
@endphp

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create
      id="btn-nuevo"
      title="Nuevo"
      module="Cortes de Eficiencia" />

    <x-navbar.button-edit
      id="btn-editar"
      title="Editar"
      module="Cortes de Eficiencia"
    />
    <x-navbar.button-report
      id="btn-visualizar"
      title="Visualizar"
      icon="fa-eye"
      iconColor="text-gray-700"
      hoverBg="hover:bg-gray-100"
      class="text-sm"
      module="Cortes de Eficiencia"
    />
    <x-navbar.button-report
      id="btn-finalizar"
      title="Finalizar"
      icon="fa-check"
      iconColor="text-orange-600"
      hoverBg="hover:bg-orange-100"
      class="text-sm"
      module="Cortes de Eficiencia"
    />
    <x-navbar.button-report
      id="btn-fechas"
      title="Fechas"
      icon="fa-calendar"
      iconColor="text-indigo-600"
      hoverBg="hover:bg-indigo-100"
      class="text-sm"
      module="Cortes de Eficiencia" />

    @if($esSupervisor ?? false)
    <x-navbar.button-report
      id="btn-editar-supervisor"
      title="Editar (Supervisor)"
      icon="fa-unlock"
      iconColor="text-red-600"
      hoverBg="hover:bg-red-100"
      class="text-sm"
      :disabled="true"
      module="Cortes de Eficiencia" />
    @endif
</div>
@endsection

@section('content')
<div class="w-screen h-full overflow-hidden flex flex-col px-4 py-4 md:px-6 lg:px-8">
    <div class="flex flex-col-1 bg-white rounded-lg shadow-md max-w-full overflow-hidden">
    @if(isset($cortes) && $cortes->count() > 0)
        <!-- Tabla con header fijo -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <table class="w-full text-sm border-collapse">
                <colgroup>
                    <col style="width: 20%">
                    <col style="width: 20%">
                    <col style="width: 15%">
                    <col style="width: 25%">
                    <col style="width: 20%">
                </colgroup>
                <thead class="bg-blue-500 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Folio</th>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Fecha</th>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Turno</th>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Empleado</th>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Status</th>
                    </tr>
                </thead>
            </table>
            <div class="flex-1 overflow-auto">
                <table class="w-full text-sm border-collapse">
                    <colgroup>
                        <col style="width: 20%">
                        <col style="width: 20%">
                        <col style="width: 15%">
                        <col style="width: 25%">
                        <col style="width: 20%">
                    </colgroup>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($cortes as $corte)
                        <tr class="hover:bg-blue-600 hover:text-white cursor-pointer transition-colors corte-row {{ isset($ultimoFolio) && $ultimoFolio->Folio == $corte->Folio ? 'bg-blue-100 border-l-4 border-blue-600' : '' }}"
                            id="row-{{ $corte->Folio }}"
                            data-folio="{{ $corte->Folio }}"
                            data-fecha="{{ $corte->Date ? Carbon::parse($corte->Date)->format('Y-m-d') : '' }}"
                            onclick="CortesManager.seleccionar('{{ $corte->Folio }}', this)"
                            ondblclick="CortesManager.accionVisualizar()">
                            <td class="px-4 py-3 font-semibold text-gray-900 text-base truncate">{{ $corte->Folio }}</td>
                            <td class="px-4 py-3 text-gray-900 text-base truncate">
                                @if($corte->Date)
                                    {{ Carbon::parse($corte->Date)->format('d/m/Y') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-900 text-base truncate">{{ $corte->Turno }}</td>
                            <td class="px-4 py-3 text-gray-900 text-base truncate">{{ $corte->numero_empleado ?? 'N/A' }}</td>
                            <td class="px-4 py-3">
                                @if($corte->Status === 'Finalizado')
                                    <span class="status-badge-finalizado px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-700">Finalizado</span>
                                @elseif($corte->Status === 'En Proceso')
                                    <span class="status-badge-proceso px-3 py-1.5 rounded-full text-sm font-semibold bg-blue-100 text-blue-700">En Proceso</span>
                                @else
                                    <span class="status-badge-otro px-3 py-1.5 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-700">{{ $corte->Status }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
            <!-- Sin Registros -->
        <div class="flex flex-col items-center justify-center flex-1 p-8 text-center">
            <h3 class="text-2xl font-semibold text-gray-700 mb-3">No hay cortes de eficiencia registrados</h3>
            <p class="text-gray-500 text-lg mb-6">Toca "Nuevo" para crear el primer registro.</p>
        <x-navbar.button-create
                id="btn-nuevo-empty"
                title="Nuevo"
                module="Cortes de Eficiencia"
                :disabled="false"
                icon="fa-plus"
                iconColor="text-green-600"
                hoverBg="hover:bg-green-100" />
        </div>
    @endif
    </div>
</div>

    @php
        // Preparar fechas únicas de los folios para el modal
        $fechasUnicas = (isset($cortes) && $cortes->count() > 0)
            ? $cortes->pluck('Date')
                ->filter()
                ->map(function ($d) { try { return Carbon::parse($d)->format('Y-m-d'); } catch (\Exception $e) { return null; } })
                ->filter()
                ->unique()
                ->sort()
            : collect();
    @endphp

    <!-- Modal Fechas -->
    <div id="modal-fechas" class="hidden fixed inset-0 z-50 items-center justify-center">
        <div class="absolute inset-0 bg-black/40" data-close="true"></div>
        <div class="relative mx-auto mt-24 w-full max-w-md rounded-lg bg-white shadow-lg">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Selecciona una fecha</h3>
                <button id="modal-fechas-close" class="text-gray-500 hover:text-gray-700" aria-label="Cerrar">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <label for="input-fecha" class="block text-sm font-medium text-gray-700 mb-1">Fecha de folios</label>
                <input
                    id="input-fecha"
                    type="date"
                    class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    value="{{ Carbon::now()->format('Y-m-d') }}"
                    @if($fechasUnicas->isNotEmpty())
                        min="{{ $fechasUnicas->first() }}"
                        max="{{ $fechasUnicas->last() }}"
                    @endif
                />
            </div>
            <div class="px-4 py-3 border-t flex justify-end gap-2">
                <button id="modal-fechas-ok" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Visualizar</button>
            </div>
        </div>
    </div>

    {{-- Modal Editar Registro (Supervisor) --}}
    @if($esSupervisor ?? false)
    <div id="modal-editar-registro" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" data-close-edit="true"></div>
        <div class="relative w-full max-w-lg rounded-lg bg-white shadow-lg">
            <div class="px-4 py-3 border-b flex items-center justify-between bg-red-50">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fa-solid fa-unlock text-red-600 mr-2"></i>Editar Registro
                    <span id="edit-folio-title" class="text-red-600 font-bold"></span>
                </h3>
                <button id="modal-editar-close" class="text-gray-500 hover:text-gray-700" aria-label="Cerrar">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label for="edit-fecha" class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                    <input type="date" id="edit-fecha" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="edit-turno" class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                    <select id="edit-turno" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="1">Turno 1</option>
                        <option value="2">Turno 2</option>
                        <option value="3">Turno 3</option>
                    </select>
                </div>
                <div>
                    <label for="edit-empleado" class="block text-sm font-medium text-gray-700 mb-1">No. Empleado</label>
                    <input type="text" id="edit-empleado" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="edit-nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre Empleado</label>
                    <input type="text" id="edit-nombre" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="edit-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="edit-status" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="En Proceso">En Proceso</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                </div>
            </div>
            <div class="px-4 py-3 border-t flex justify-end gap-2">
                <button id="modal-editar-cancel" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button id="modal-editar-save" class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">
                    <i class="fa-solid fa-save mr-1"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
    @endif

<style>
    .selected-row td, .selected-row span {
        color: #fff !important;
    }
    .selected-row .status-badge-finalizado { color: #15803d !important; }
    .selected-row .status-badge-proceso { color: #1d4ed8 !important; }
    .selected-row .status-badge-otro { color: #854d0e !important; }
</style>
<script>
(() => {
    'use strict';

    const CONFIG = {
        urls: {
            detalle: '/modulo-cortes-de-eficiencia/',
            editar: '{{ url("/modulo-cortes-de-eficiencia") }}?folio=',
            finalizar: '/modulo-cortes-de-eficiencia/{folio}/finalizar',
            visualizarFolio: '/modulo-cortes-de-eficiencia/visualizar-folio/',
            actualizarRegistro: '/modulo-cortes-de-eficiencia/{folio}/actualizar-registro'
        },
        timeout: 30000,
        ultimoFolio: @json(isset($ultimoFolio) ? $ultimoFolio->Folio : null),
        esSupervisor: @json($esSupervisor ?? false)
    };

    class CortesManager {
        constructor() {
            this.state = {
                folio: null,
                status: null,
                abortController: null,
                fechaSeleccionada: null
            };

            this.dom = {
                btns: {
                    nuevo: document.getElementById('btn-nuevo'),
                    editar: document.getElementById('btn-editar'),
                    finalizar: document.getElementById('btn-finalizar'),
                    visualizar: document.getElementById('btn-visualizar'),
                    fechas: document.getElementById('btn-fechas'),
                    pdf: document.getElementById('btn-pdf'),
                    editarSupervisor: document.getElementById('btn-editar-supervisor')
                },
                modal: {
                    fechas: document.getElementById('modal-fechas'),
                    close: document.getElementById('modal-fechas-close'),
                    ok: document.getElementById('modal-fechas-ok'),
                    fechaInput: document.getElementById('input-fecha')
                },
                modalEditar: {
                    container: document.getElementById('modal-editar-registro'),
                    close: document.getElementById('modal-editar-close'),
                    cancel: document.getElementById('modal-editar-cancel'),
                    save: document.getElementById('modal-editar-save'),
                    folioTitle: document.getElementById('edit-folio-title'),
                    fecha: document.getElementById('edit-fecha'),
                    turno: document.getElementById('edit-turno'),
                    empleado: document.getElementById('edit-empleado'),
                    nombre: document.getElementById('edit-nombre'),
                    status: document.getElementById('edit-status')
                }
            };

            this.init();
        }

        init() {
            this.bindEvents();
            if (CONFIG.ultimoFolio) {
                setTimeout(() => {
                    const tr = document.querySelector(`tr[data-folio="${CONFIG.ultimoFolio}"]`);
                    if (tr) {
                        this.seleccionar(CONFIG.ultimoFolio, tr);
                    }
                }, 100);
            }
        }

        bindEvents() {
            this.dom.btns.nuevo?.addEventListener('click', () => this.accionNuevo());
            this.dom.btns.editar?.addEventListener('click', () => this.accionEditar());
            this.dom.btns.finalizar?.addEventListener('click', () => this.accionFinalizar());
            this.dom.btns.visualizar?.addEventListener('click', () => this.accionVisualizar());
            this.dom.btns.pdf?.addEventListener('click', () => this.accionPdf());
            this.dom.btns.editarSupervisor?.addEventListener('click', () => this.accionEditarSupervisor());
            document.getElementById('btn-exportar-excel')?.addEventListener('click', () => this.exportar('excel'));
            document.getElementById('btn-descargar-pdf')?.addEventListener('click', () => this.exportar('pdf'));

            // Abrir/cerrar modal de fechas
            this.dom.btns.fechas?.addEventListener('click', () => this.abrirModalFechas());
            this.dom.modal.close?.addEventListener('click', () => this.cerrarModalFechas());
            this.dom.modal.fechas?.addEventListener('click', (e) => {
                if (e.target?.dataset?.close === 'true') this.cerrarModalFechas();
            });
            // Confirmar visualización por fecha
            this.dom.modal.ok?.addEventListener('click', () => this.visualizarPorFecha());

            // Modal editar registro (supervisor)
            this.dom.modalEditar.close?.addEventListener('click', () => this.cerrarModalEditar());
            this.dom.modalEditar.cancel?.addEventListener('click', () => this.cerrarModalEditar());
            this.dom.modalEditar.save?.addEventListener('click', () => this.guardarRegistro());
            this.dom.modalEditar.container?.addEventListener('click', (e) => {
                if (e.target?.dataset?.closeEdit === 'true') this.cerrarModalEditar();
            });
        }

        seleccionar(folio, row) {
            if (this.state.folio === folio) return;

            this.state.folio = folio;
            this.highlightRow(row);
            this.cargarDetalles(folio);
        }

        highlightRow(row) {
            document.querySelectorAll('tbody tr').forEach(tr => {
                tr.classList.remove('bg-blue-500', 'text-white', 'selected-row');
            });
            if (row) {
                row.classList.add('bg-blue-500', 'text-white', 'selected-row');
                this.state.fechaSeleccionada = row.dataset.fecha || null;
            }
        }

        async cargarDetalles(folio) {
            if (this.state.abortController) {
                this.state.abortController.abort();
            }

            this.state.abortController = new AbortController();

            try {
                const res = await fetch(`${CONFIG.urls.detalle}${folio}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: this.state.abortController.signal
                });

                if (!res.ok) {
                    throw new Error(`Error HTTP: ${res.status}`);
                }

                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.message || 'Error al cargar los detalles');
                }

                this.state.status = data.data.status;
                this.actualizarBotones();

            } catch (err) {
                if (err.name === 'AbortError') return;
                console.error('Error al cargar detalles:', err.message);
            }
        }

        actualizarBotones() {
            const isFinalizado = this.state.status === 'Finalizado';
            const hayFolioSeleccionado = this.state.folio !== null;

            if (this.dom.btns.nuevo) this.dom.btns.nuevo.disabled = false;
            if (this.dom.btns.editar) this.dom.btns.editar.disabled = !hayFolioSeleccionado || isFinalizado;
            if (this.dom.btns.finalizar) this.dom.btns.finalizar.disabled = !hayFolioSeleccionado || isFinalizado;
            if (this.dom.btns.visualizar) this.dom.btns.visualizar.disabled = !hayFolioSeleccionado;
            if (this.dom.btns.pdf) this.dom.btns.pdf.disabled = !hayFolioSeleccionado;
            if (this.dom.btns.editarSupervisor) this.dom.btns.editarSupervisor.disabled = !hayFolioSeleccionado;
            const btnExcel = document.getElementById('btn-exportar-excel');
            const btnPdf = document.getElementById('btn-descargar-pdf');
            if (btnExcel) btnExcel.disabled = !hayFolioSeleccionado;
            if (btnPdf) btnPdf.disabled = !hayFolioSeleccionado;
        }

        exportar(tipo) {
            if (!this.state.fechaSeleccionada) {
                Swal.fire('Sin fecha', 'Selecciona un folio para exportar.', 'warning');
                return;
            }

            if (tipo === 'excel') {
                exportarExcelVisualizacion(this.state.fechaSeleccionada);
            } else {
                descargarPDFVisualizacion(this.state.fechaSeleccionada);
            }
        }

        async accionNuevo() {
            // Generar nuevo folio y redirigir a la página de edición
            try {
                const response = await fetch('/modulo-cortes-de-eficiencia/generar-folio', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (response.status === 400 && data.folio_existente) {
                    // Ya existe un folio en proceso, preguntar si quiere editarlo
                    const result = await Swal.fire({
                        icon: 'warning',
                        title: 'Folio en proceso',
                        text: 'Ya existe un folio en proceso: ' + data.folio_existente + '. ¿Desea continuar editándolo?',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, editar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#3085d6'
                    });

                    if (result.isConfirmed) {
                        // Redirigir al folio existente para editarlo
                        window.location.href = '{{ route("cortes.eficiencia") }}?folio=' + data.folio_existente;
                    }
                    return;
                }

                if (!data.success) {
                    throw new Error(data.message || 'No se pudo generar el folio');
                }

                // Folio generado exitosamente, redirigir a la página de edición con el nuevo folio
                window.location.href = '{{ route("cortes.eficiencia") }}?folio=' + data.folio;

            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo generar el folio: ' + error.message
                });
            }
        }

        accionEditar() {
            if (!this.state.folio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin selección',
                    text: 'Selecciona un folio para editar'
                });
                return;
            }
            window.location.href = CONFIG.urls.editar + this.state.folio;
        }

        accionVisualizar() {
            if (!this.state.folio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin selección',
                    text: 'Selecciona un folio para visualizar'
                });
                return;
            }
            window.location.href = `${CONFIG.urls.visualizarFolio}${this.state.folio}`;
        }

        async accionEditarSupervisor() {
            if (!this.state.folio) {
                Swal.fire({ icon: 'warning', title: 'Sin selección', text: 'Selecciona un folio para editar' });
                return;
            }

            try {
                Swal.fire({ title: 'Cargando datos...', didOpen: () => Swal.showLoading() });

                const res = await fetch(`${CONFIG.urls.detalle}${this.state.folio}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
                const data = await res.json();
                Swal.close();

                if (!data.success) throw new Error(data.message || 'No se pudo obtener los datos');

                this.abrirModalEditar(data.data);

            } catch (err) {
                Swal.close();
                Swal.fire('Error', err.message || 'No se pudieron cargar los datos del folio', 'error');
            }
        }

        abrirModalEditar(corte) {
            if (!this.dom.modalEditar.container) return;

            if (this.dom.modalEditar.folioTitle) this.dom.modalEditar.folioTitle.textContent = ` - ${corte.folio || this.state.folio}`;
            if (this.dom.modalEditar.fecha) {
                const fecha = corte.fecha ? new Date(corte.fecha).toISOString().split('T')[0] : '';
                this.dom.modalEditar.fecha.value = fecha;
            }
            if (this.dom.modalEditar.turno) this.dom.modalEditar.turno.value = corte.turno || '1';
            if (this.dom.modalEditar.empleado) this.dom.modalEditar.empleado.value = corte.numero_empleado || corte.noEmpleado || '';
            if (this.dom.modalEditar.nombre) this.dom.modalEditar.nombre.value = corte.nombreEmpl || corte.usuario || '';
            if (this.dom.modalEditar.status) this.dom.modalEditar.status.value = corte.status || 'En Proceso';

            this.dom.modalEditar.container.classList.remove('hidden');
        }

        cerrarModalEditar() {
            if (!this.dom.modalEditar.container) return;
            this.dom.modalEditar.container.classList.add('hidden');
        }

        async guardarRegistro() {
            const folio = this.state.folio;
            if (!folio) return;

            const datos = {};
            if (this.dom.modalEditar.fecha) datos.Date = this.dom.modalEditar.fecha.value;
            if (this.dom.modalEditar.turno) datos.Turno = this.dom.modalEditar.turno.value;
            if (this.dom.modalEditar.empleado) datos.numero_empleado = this.dom.modalEditar.empleado.value;
            if (this.dom.modalEditar.nombre) datos.nombreEmpl = this.dom.modalEditar.nombre.value;
            if (this.dom.modalEditar.status) datos.Status = this.dom.modalEditar.status.value;

            if (!datos.Date || !datos.Turno) {
                Swal.fire('Campos requeridos', 'Fecha y Turno son obligatorios.', 'warning');
                return;
            }

            Swal.fire({ title: 'Guardando cambios...', didOpen: () => Swal.showLoading() });

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = CONFIG.urls.actualizarRegistro.replace('{folio}', folio);

                const res = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(datos)
                });

                const data = await res.json();

                if (data.success) {
                    this.cerrarModalEditar();
                    await Swal.fire('¡Actualizado!', data.message || 'Registro actualizado correctamente.', 'success');
                    window.location.reload();
                } else {
                    Swal.fire('Error', data.message || 'No se pudo actualizar el registro', 'error');
                }
            } catch (err) {
                Swal.fire('Error', err.message || 'Error al guardar los cambios', 'error');
            }
        }

        accionPdf() {
            if (!this.state.folio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin selección',
                    text: 'Selecciona un folio para descargar PDF'
                });
                return;
            }
            window.open(`/modulo-cortes-de-eficiencia/${this.state.folio}/pdf`, '_blank');
        }

        accionFinalizar() {
            if (!this.state.folio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin selección',
                    text: 'Selecciona un folio para finalizar'
                });
                return;
            }
            // Validar campos vacíos antes de finalizar
            this.validarParaFinalizar()
                .then((valido) => {
                    if (!valido) return;

                    Swal.fire({
                        title: '¿Finalizar Corte de Eficiencia?',
                        text: `El folio ${this.state.folio} quedará cerrado y no podrá editarse.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ea580c',
                        confirmButtonText: 'Sí, finalizar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.procesarFinalizado();
                        }
                    });
                })
                .catch((err) => {
                    Swal.fire('Error', err?.message || 'No se pudo validar el folio', 'error');
                });
        }

        async procesarFinalizado() {
            Swal.fire({ title: 'Finalizando...', didOpen: () => Swal.showLoading() });

            try {
                const url = CONFIG.urls.finalizar.replace('{folio}', this.state.folio);
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    }
                });

                const data = await res.json();

                if (data.success) {
                    await Swal.fire('¡Finalizado!', 'El registro se ha cerrado correctamente.', 'success');
                    this.state.status = 'Finalizado';
                    this.actualizarBotones();
                    this.actualizarFilaFinalizada();
                } else {
                    throw new Error(data.message || 'No se pudo finalizar');
                }

            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        }

        actualizarFilaFinalizada() {
            const row = document.querySelector(`tr[data-folio="${this.state.folio}"]`);
            if (!row) return;
            const statusCell = row.querySelector('td:last-child');
            if (statusCell) {
                statusCell.innerHTML = '<span class="status-badge-finalizado px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-700">Finalizado</span>';
            }
        }

        async validarParaFinalizar() {
            try {
                Swal.fire({ title: 'Validando...', didOpen: () => Swal.showLoading() });

                const res = await fetch(`${CONFIG.urls.detalle}${this.state.folio}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
                const data = await res.json();
                Swal.close();

                if (!data.success) throw new Error(data.message || 'No se pudo obtener el detalle');

                const lineas = Array.isArray(data.data?.datos_telares) ? data.data.datos_telares : [];
                if (lineas.length === 0) {
                    await Swal.fire({
                        icon: 'warning',
                        title: 'No hay líneas',
                        text: 'No puedes finalizar un folio sin líneas capturadas.'
                    });
                    return false;
                }

                const esVacioOCero = (v) => {
                    if (v === null || v === undefined) return true;
                    if (typeof v === 'string' && v.trim() === '') return true;
                    const n = Number(v);
                    if (Number.isNaN(n)) return true;
                    return n <= 0;
                };

                let lineasInvalidas = [];

                for (let i = 0; i < lineas.length; i++) {
                    const l = lineas[i];
                    const camposVacios = [];

                    if (esVacioOCero(l.RpmR1)) camposVacios.push('RPM H1');
                    if (esVacioOCero(l.EficienciaR1)) camposVacios.push('% Efi H1');
                    if (esVacioOCero(l.RpmR2)) camposVacios.push('RPM H2');
                    if (esVacioOCero(l.EficienciaR2)) camposVacios.push('% Efi H2');
                    if (esVacioOCero(l.RpmR3)) camposVacios.push('RPM H3');
                    if (esVacioOCero(l.EficienciaR3)) camposVacios.push('% Efi H3');

                    if (camposVacios.length > 0) {
                        lineasInvalidas.push({
                            telar: l.NoTelar || `Línea ${i + 1}`,
                            campos: camposVacios
                        });
                    }
                }

                if (lineasInvalidas.length > 0) {
                    const totalCamposVacios = lineasInvalidas.reduce((acc, item) => acc + (item.campos?.length || 0), 0);
                    const result = await Swal.fire({
                        icon: 'warning',
                        title: 'Hay campos sin llenar',
                        text: `Hay ${totalCamposVacios} campo(s) vacío(s) o en cero en ${lineasInvalidas.length} telar(es). ¿Deseas continuar?`,
                        showCancelButton: true,
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'No, revisar'
                    });
                    return !!result.isConfirmed;
                }

                return true;
            } catch (err) {
                Swal.close();
                throw err;
            }
        }

        abrirModalFechas() {
            if (!this.dom.modal.fechas) return;
            this.dom.modal.fechas.classList.remove('hidden');
            this.dom.modal.fechas.classList.add('flex');
        }

        cerrarModalFechas() {
            if (!this.dom.modal.fechas) return;
            this.dom.modal.fechas.classList.add('hidden');
            this.dom.modal.fechas.classList.remove('flex');
        }

        visualizarPorFecha() {
            const input = this.dom.modal.fechaInput;
            if (!input || !input.value) {
                Swal.fire('Fecha requerida', 'Selecciona una fecha para visualizar.', 'warning');
                return;
            }

            // Buscar el primer folio de esa fecha (el reporte incluye turnos 1, 2 y 3)
            const fecha = input.value; // formato YYYY-MM-DD
            const rows = document.querySelectorAll('tbody tr[data-folio]');

            for (const row of rows) {
                const fechaRow = row.dataset.fecha;
                if (fechaRow === fecha) {
                    const folio = row.dataset.folio;
                    this.cerrarModalFechas();
                    window.location.href = `/modulo-cortes-de-eficiencia/visualizar/${folio}`;
                    return;
                }
            }

            Swal.fire('Sin folios', 'No se encontraron folios para la fecha seleccionada.', 'info');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.CortesManager = new CortesManager();

        // Agregar listener al botón "Nuevo" cuando no hay registros
        const btnNuevoEmpty = document.getElementById('btn-nuevo-empty');
        if (btnNuevoEmpty) {
            btnNuevoEmpty.addEventListener('click', () => {
                window.location.href = '{{ route("cortes.eficiencia") }}';
            });
        }

        document.getElementById('btn-exportar-excel')?.addEventListener('click', () => {
            if (!window.CortesManager?.state?.fechaSeleccionada) {
                Swal.fire('Sin selección', 'Selecciona un folio para exportar.', 'warning');
                return;
            }
            exportarExcelVisualizacion(window.CortesManager.state.fechaSeleccionada);
        });

        document.getElementById('btn-descargar-pdf')?.addEventListener('click', () => {
            if (!window.CortesManager?.state?.fechaSeleccionada) {
                Swal.fire('Sin selección', 'Selecciona un folio para exportar.', 'warning');
                return;
            }
            descargarPDFVisualizacion(window.CortesManager.state.fechaSeleccionada);
        });
    });

})();

function exportarExcelVisualizacion(fecha) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("cortes.eficiencia.visualizar.excel") }}';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = '{{ csrf_token() }}';

    const fechaInput = document.createElement('input');
    fechaInput.type = 'hidden';
    fechaInput.name = 'fecha';
    fechaInput.value = fecha;

    form.appendChild(token);
    form.appendChild(fechaInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

async function descargarPDFVisualizacion(fecha) {
    try {
        const response = await fetch('{{ route("cortes.eficiencia.visualizar.pdf") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/pdf'
            },
            body: new URLSearchParams({ fecha })
        });

        if (!response.ok) {
            const text = await response.text();
            console.error('Error al generar PDF:', response.status, text);
            Swal.fire('Error', 'No se pudo generar el PDF.', 'error');
            return;
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `cortes_eficiencia_${fecha}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error al descargar PDF:', error);
        Swal.fire('Error', 'Ocurrió un error al generar el PDF.', 'error');
    }
}
</script>
@endsection
