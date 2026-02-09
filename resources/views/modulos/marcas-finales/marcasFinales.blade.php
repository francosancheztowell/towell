@extends('layouts.app')

@section('page-title', 'Marcas Finales')

@php
    use Carbon\Carbon;
@endphp

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
            id="btn-nuevo"
            title="Nuevo"
            module="Marcas Finales"

         />

        <x-navbar.button-edit
            id="btn-editar"
            title="Editar"
            module="Marcas Finales"

         />
        <x-navbar.button-report
            id="btn-visualizar"
            title="Visualizar"
            module="Marcas Finales"

            icon="fa-eye"
            iconColor="text-gray-700"
            hoverBg="hover:bg-gray-100" />

        <x-navbar.button-report
            id="btn-finalizar"
            title="Finalizar"
            module="Marcas Finales"

            icon="fa-check"
            iconColor="text-orange-600"
            hoverBg="hover:bg-orange-100" />

        <x-navbar.button-report
            id="btn-fechas"
            title="Fechas"
            module="Marcas Finales"

            icon="fa-calendar"
            iconColor="text-indigo-600"
            hoverBg="hover:bg-indigo-100" />

        @if($esSupervisor ?? false)
        <x-navbar.button-report
            id="btn-editar-supervisor"
            title="Editar (Supervisor)"
            module="Marcas Finales"
            :disabled="true"
            icon="fa-unlock"
            iconColor="text-red-600"
            hoverBg="hover:bg-red-100" />
        @endif
    </div>
@endsection

@section('content')
<div class="w-screen h-full overflow-hidden flex flex-col px-4 py-4 md:px-6 lg:px-8">
    <div class="flex flex-col flex-1 bg-white rounded-lg shadow-md overflow-hidden max-w-full">
    @if(isset($marcas) && $marcas->count() > 0)
        <!-- Header fijo (sticky) dentro del contenedor -->
        <div class="bg-blue-600 text-white sticky top-0 z-10">
            <table class="w-full text-sm">
                <colgroup>
                    <col style="width: 20%">
                    <col style="width: 20%">
                    <col style="width: 15%">
                    <col style="width: 25%">
                    <col style="width: 20%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Folio</th>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Fecha</th>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Turno</th>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Empleado</th>
                        <th class="px-4 py-3 text-left uppercase text-sm font-semibold">Status</th>
                    </tr>
                </thead>
            </table>
        </div>
        <!-- Solo el contenido con scroll -->
        <div class="flex-1 overflow-auto">
            <table class="w-full text-sm">
                <colgroup>
                    <col style="width: 20%">
                    <col style="width: 20%">
                    <col style="width: 15%">
                    <col style="width: 25%">
                    <col style="width: 20%">
                </colgroup>
                <tbody class="divide-y divide-gray-100">
              @foreach($marcas as $marca)
                            <tr class="hover:bg-blue-500 hover:text-white cursor-pointer transition-colors marca-row {{ isset($ultimoFolio) && $ultimoFolio->Folio == $marca->Folio ? 'bg-blue-100 border-l-4 border-blue-600' : '' }}"
                  id="row-{{ $marca->Folio }}"
                  data-folio="{{ $marca->Folio }}"
                  onclick="window.MarcasManager?.seleccionar('{{ $marca->Folio }}', this)">
                <td class="px-4 py-3 font-semibold text-gray-900 text-base truncate hover:text-white">{{ $marca->Folio }}</td>
                                <td class="px-4 py-3 text-gray-900 text-base truncate hover:text-white">
                                    @if($marca->Date)
                                        {{ Carbon::parse($marca->Date)->format('d/m/Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-900 text-base truncate hover:text-white">{{ $marca->Turno }}</td>
                <td class="px-4 py-3 text-gray-900 text-base truncate hover:text-white">{{ $marca->numero_empleado ?? 'N/A' }}</td>
                <td class="px-4 py-3">
                  @if($marca->Status === 'Finalizado')
                    <span class="px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-700">Finalizado</span>
                  @elseif($marca->Status === 'En Proceso')
                    <span class="px-3 py-1.5 rounded-full text-sm font-semibold bg-blue-100 text-blue-700">En Proceso</span>
                  @else
                    <span class="px-3 py-1.5 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-700">{{ $marca->Status }}</span>
                  @endif
                </td>
              </tr>
              @endforeach
                </tbody>
            </table>
        </div>
    @else
            <!-- Sin Registros -->
        <div class="flex flex-col items-center justify-center flex-1 p-8 text-center">
            <h3 class="text-2xl font-semibold text-gray-700 mb-3">No hay marcas registradas</h3>
            <p class="text-gray-500 text-lg mb-6">Toca "Nueva Marca" para crear el primer registro.</p>
        <x-navbar.button-create
                  id="btn-nuevo-empty"
          title="Nuevo"
          module="Marcas Finales"
                    :checkPermission="false"
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
        $fechasUnicas = (isset($marcas) && $marcas->count() > 0)
            ? $marcas->pluck('Date')
                ->filter()
                ->map(function ($d) { try { return Carbon::parse($d)->format('Y-m-d'); } catch (\Exception $e) { return null; } })
                ->filter()
                ->unique()
                ->sort()
            : collect();
    @endphp

    <!-- Modal Fechas -->
    <div id="modal-fechas" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" data-close="true"></div>
        <div class="relative w-full max-w-md rounded-lg bg-white shadow-lg">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Selecciona una fecha</h3>
                <button id="modal-fechas-close" class="text-gray-500 hover:text-gray-700" aria-label="Cerrar">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <label for="select-fechas" class="block text-sm font-medium text-gray-700 mb-1">Fechas de folios</label>
                <select id="select-fechas" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500" data-action="reporte-fecha">
                    @foreach($fechasUnicas as $fecha)
                        <option value="{{ $fecha }}">{{ Carbon::createFromFormat('Y-m-d', $fecha)->format('d/m/Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="px-4 py-3 border-t flex justify-end gap-2">
                <button id="modal-fechas-ok" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700" data-action="confirm-fecha">Generar Reporte</button>
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


<script>
(() => {
    'use strict';

    const CONFIG = {
        urls: {
            detalle: '/modulo-marcas/',
            editar: '{{ url("/modulo-marcas") }}?folio=',
            finalizar: '/modulo-marcas/{folio}/finalizar',
            reabrir: '/modulo-marcas/{folio}/reabrir',
            actualizarRegistro: '/modulo-marcas/{folio}/actualizar-registro'
        },
        timeout: 30000,
        ultimoFolio: @json(isset($ultimoFolio) ? $ultimoFolio->Folio : null),
        esSupervisor: @json($esSupervisor ?? false)
    };

    class MarcasManager {
        constructor() {
            this.state = {
                folio: null,
                status: null,
                abortController: null
            };

            this.dom = {
                panel: document.getElementById('preview-panel'),
                loader: document.getElementById('preview-loader'),
                body: document.getElementById('preview-body'),
                header: {
                    folio: document.getElementById('prev-folio'),
                    meta: document.getElementById('prev-meta'),
                    status: document.getElementById('prev-status-container')
                },
                btns: {
                    visualizar: document.getElementById('btn-visualizar'),
                    nuevo: document.getElementById('btn-nuevo'),
                    editar: document.getElementById('btn-editar'),
                    finalizar: document.getElementById('btn-finalizar'),
                    fechas: document.getElementById('btn-fechas'),
                    editarSupervisor: document.getElementById('btn-editar-supervisor')
                },
                modal: {
                    fechas: document.getElementById('modal-fechas'),
                    close: document.getElementById('modal-fechas-close'),
                    ok: document.getElementById('modal-fechas-ok'),
                    select: document.getElementById('select-fechas')
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
            this.dom.btns.editarSupervisor?.addEventListener('click', () => this.accionEditarSupervisor());
            // Abrir/cerrar modal de fechas
            this.dom.btns.fechas?.addEventListener('click', () => this.abrirModalFechas());
            this.dom.modal.close?.addEventListener('click', () => this.cerrarModalFechas());
            this.dom.modal.ok?.addEventListener('click', () => this.cerrarModalFechas());
            this.dom.modal.fechas?.addEventListener('click', (e) => {
                if (e.target?.dataset?.close === 'true') this.cerrarModalFechas();
            });
            // Confirmar reporte (redirigir por fecha)
            this.dom.modal.ok?.addEventListener('click', () => this.generarReporteFecha());
            // Atajo: cambiar select y generar inmediatamente (opcional)
            this.dom.modal.select?.addEventListener('change', () => {
                // Puedes quitar este auto-submit si solo quieres botón
                // this.generarReporteFecha();
            });

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
                tr.classList.remove('bg-blue-100', 'border-l-4', 'border-blue-600');
            });
            if (row) {
                row.classList.add('bg-blue-100', 'border-l-4', 'border-blue-600');
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

                this.state.status = data.marca.Status;
                this.actualizarBotones();

            } catch (err) {
                if (err.name === 'AbortError') return;
                console.error('Error al cargar detalles:', err.message);
            }
        }

        activarPanel(activo) {
            if (!this.dom.panel) return;
            if (activo) {
                this.dom.panel.classList.remove('hidden');
            } else {
                this.dom.panel.classList.add('hidden');
            }
        }

        renderizarDetalle(marca, lineas) {
            if (this.dom.header.folio) {
                this.dom.header.folio.textContent = marca?.Folio ?? '-';
            }

            if (this.dom.header.meta) {
                const fecha = marca?.Date ? this.formatFecha(marca.Date) : '-';
                const turno = marca?.Turno ?? '-';
                const empleado = marca?.numero_empleado ?? 'N/A';
                this.dom.header.meta.textContent = `${fecha} · T${turno} · ${empleado}`;
            }

            if (this.dom.header.status) {
                const status = marca?.Status ?? '-';
                this.dom.header.status.textContent = status;
                this.dom.header.status.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-white/30 whitespace-nowrap';

                if (status === 'Finalizado') {
                    this.dom.header.status.classList.add('bg-green-500/30', 'text-white');
                } else if (status === 'En Proceso') {
                    this.dom.header.status.classList.add('bg-yellow-500/30', 'text-white');
                } else {
                    this.dom.header.status.classList.add('bg-white/20', 'text-white');
                }
            }

            if (!lineas || !lineas.length) {
                this.dom.body.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Sin líneas capturadas</td></tr>';
                return;
            }

            const html = lineas.map((linea, index) => {
                const eficiencia = this.formatEficiencia(linea);
                return `
                    <tr class="${index % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100'}">
                        <td class="px-2 py-2 font-semibold text-gray-900 truncate">${linea.NoTelarId ?? '-'}</td>
                        <td class="px-2 py-2 text-center">${eficiencia}</td>
                        <td class="px-2 py-2 text-center">${linea.Marcas ?? '-'}</td>
                        <td class="px-2 py-2 text-center">${linea.Trama ?? '-'}</td>
                        <td class="px-2 py-2 text-center">${linea.Pie ?? '-'}</td>
                        <td class="px-2 py-2 text-center">${linea.Rizo ?? '-'}</td>
                        <td class="px-2 py-2 text-center">${linea.Otros ?? '-'}</td>
                    </tr>
                `;
            }).join('');

            this.dom.body.innerHTML = html;
        }

        formatFecha(dateString) {
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return '-';
                return date.toLocaleDateString('es-MX', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            } catch (e) {
                return '-';
            }
        }

        formatEficiencia(linea) {
            const eVal = linea.Eficiencia ?? linea.EficienciaSTD ?? linea.EficienciaStd ?? null;
            if (eVal === null) return '-';
            if (isNaN(eVal)) return eVal;
            return `${(Number(eVal) * 100).toFixed(0)}%`;
        }

        actualizarBotones() {
            const hayFolioSeleccionado = this.state.folio !== null;
            const isEnProceso = this.state.status === 'En Proceso';
            const isFinalizado = this.state.status === 'Finalizado';

            if (this.dom.btns.nuevo) this.dom.btns.nuevo.disabled = false;
            // Editar y Finalizar solo cuando está "En Proceso"
            if (this.dom.btns.editar) this.dom.btns.editar.disabled = !hayFolioSeleccionado || !isEnProceso;
            if (this.dom.btns.finalizar) this.dom.btns.finalizar.disabled = !hayFolioSeleccionado || !isEnProceso;
            if (this.dom.btns.visualizar) this.dom.btns.visualizar.disabled = !hayFolioSeleccionado;
            // Botón Editar Supervisor: habilitado cuando hay folio seleccionado (cualquier status)
            if (this.dom.btns.editarSupervisor) {
                this.dom.btns.editarSupervisor.disabled = !hayFolioSeleccionado;
            }
        }

        async accionNuevo() {
            window.location.href = '{{ route("marcas.nuevo") }}';
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
            if (this.state.status !== 'En Proceso') {
                Swal.fire({
                    icon: 'info',
                    title: 'Edición no disponible',
                    text: 'Solo puedes editar folios con estado "En Proceso".'
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
            window.location.href = `/modulo-marcas/visualizar/${this.state.folio}`;
        }

        async accionEditarSupervisor() {
            if (!this.state.folio) {
                Swal.fire({ icon: 'warning', title: 'Sin selección', text: 'Selecciona un folio para editar' });
                return;
            }

            // Cargar datos del folio y abrir modal
            try {
                Swal.fire({ title: 'Cargando datos...', didOpen: () => Swal.showLoading() });

                const res = await fetch(`${CONFIG.urls.detalle}${this.state.folio}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
                const data = await res.json();
                Swal.close();

                if (!data.success) throw new Error(data.message || 'No se pudo obtener los datos');

                const marca = data.marca;
                this.abrirModalEditar(marca);

            } catch (err) {
                Swal.close();
                Swal.fire('Error', err.message || 'No se pudieron cargar los datos del folio', 'error');
            }
        }

        abrirModalEditar(marca) {
            if (!this.dom.modalEditar.container) return;

            // Rellenar campos con los datos actuales
            if (this.dom.modalEditar.folioTitle) this.dom.modalEditar.folioTitle.textContent = ` - ${marca.Folio}`;
            if (this.dom.modalEditar.fecha) {
                const fecha = marca.Date ? new Date(marca.Date).toISOString().split('T')[0] : '';
                this.dom.modalEditar.fecha.value = fecha;
            }
            if (this.dom.modalEditar.turno) this.dom.modalEditar.turno.value = marca.Turno || '1';
            if (this.dom.modalEditar.empleado) this.dom.modalEditar.empleado.value = marca.numero_empleado || '';
            if (this.dom.modalEditar.nombre) this.dom.modalEditar.nombre.value = marca.nombreEmpl || '';
            if (this.dom.modalEditar.status) this.dom.modalEditar.status.value = marca.Status || 'En Proceso';

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

        accionFinalizar() {
            if (!this.state.folio) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin selección',
                    text: 'Selecciona un folio para finalizar'
                });
                return;
            }
            if (this.state.status !== 'En Proceso') {
                Swal.fire({
                    icon: 'info',
                    title: 'No se puede finalizar',
                    text: 'Solo puedes finalizar folios con estado "En Proceso".'
                });
                return;
            }
            // Primero validar que no haya campos vacíos o en cero
            this.validarParaFinalizar()
                .then((valido) => {
                    if (!valido) return; // Se mostró alerta con detalles

                    Swal.fire({
                        title: '¿Finalizar Marca?',
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
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'No se pudo finalizar');
                }

            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        }

        mostrarError(msg) {
            this.dom.body.innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">${msg}</td></tr>`;
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

        const lineas = Array.isArray(data.lineas) ? data.lineas : [];
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

        // Validar todos los campos críticos: Eficiencia, Marcas, Trama, Pie, Rizo, Otros
        let lineasInvalidas = [];

        for (let i = 0; i < lineas.length; i++) {
            const l = lineas[i];
            const camposVacios = [];

            if (esVacioOCero(l.Eficiencia)) camposVacios.push('% Efi');
            if (esVacioOCero(l.Marcas)) camposVacios.push('Marcas');
            if (esVacioOCero(l.Trama)) camposVacios.push('Trama');
            if (esVacioOCero(l.Pie)) camposVacios.push('Pie');
            if (esVacioOCero(l.Rizo)) camposVacios.push('Rizo');
            if (esVacioOCero(l.Otros)) camposVacios.push('Otros');

            if (camposVacios.length > 0) {
                lineasInvalidas.push({
                    telar: l.NoTelarId || `Línea ${i + 1}`,
                    campos: camposVacios
                });
            }
        }

        // Mostrar confirmación si hay campos vacíos
        if (lineasInvalidas.length > 0) {
            const totalCamposVacios = lineasInvalidas.reduce((acc, item) => acc + (item.campos?.length || 0), 0);
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Hay campos sin valor',
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
        }

        cerrarModalFechas() {
            if (!this.dom.modal.fechas) return;
            this.dom.modal.fechas.classList.add('hidden');
        }

        generarReporteFecha() {
            const sel = this.dom.modal.select;
            if (!sel || !sel.value) {
                Swal.fire('Fecha requerida', 'Selecciona una fecha para generar el reporte.', 'warning');
                return;
            }
            // Redirige a ruta de reporte por fecha (controlador debe existir)
            const fecha = sel.value; // formato YYYY-MM-DD
            this.cerrarModalFechas();
            window.location.href = `/modulo-marcas/reporte?fecha=${encodeURIComponent(fecha)}`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.MarcasManager = new MarcasManager();

        // Agregar listener al botón "Nuevo" cuando no hay registros
        const btnNuevoEmpty = document.getElementById('btn-nuevo-empty');
        if (btnNuevoEmpty) {
            btnNuevoEmpty.addEventListener('click', () => {
                window.location.href = '/modulo-marcas';
            });
        }
    });

})();
</script>
@endsection
