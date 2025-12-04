@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Consultar Cortes de Eficiencia')

@php
    use Carbon\Carbon;
@endphp

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create
      id="btn-nuevo"
      title="Nuevo"
      module="Cortes de Eficiencia"
      :disabled="false"
      icon="fa-plus"
      iconColor="text-green-600"
      hoverBg="hover:bg-green-100" />

    <x-navbar.button-edit
      id="btn-editar"
      title="Editar"
      module="Cortes de Eficiencia"
      :disabled="true"
      icon="fa-pen-to-square"
      iconColor="text-blue-600"
      hoverBg="hover:bg-blue-100" />

    <x-navbar.button-report
      id="btn-finalizar"
      title="Finalizar"
      module="Cortes de Eficiencia"
      :disabled="false"
      icon="fa-check"
      iconColor="text-orange-600"
      hoverBg="hover:bg-orange-100"
      />

    <x-navbar.button-report
      id="btn-visualizar"
      title="Visualizar"
      module="Cortes de Eficiencia"
      :disabled="true"
      icon="fa-eye"
      iconColor="text-purple-600"
      hoverBg="hover:bg-purple-100"
      />

    <x-navbar.button-report
      id="btn-fechas"
      title="Fechas"
      module="Cortes de Eficiencia"
      :disabled="false"
      icon="fa-calendar"
      iconColor="text-indigo-600"
      hoverBg="hover:bg-indigo-100" />
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
                <thead class="bg-blue-600 text-white sticky top-0 z-10">
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
                        <tr class="hover:bg-blue-50 cursor-pointer transition-colors corte-row {{ isset($ultimoFolio) && $ultimoFolio->Folio == $corte->Folio ? 'bg-blue-100 border-l-4 border-blue-600' : '' }}"
                            id="row-{{ $corte->Folio }}"
                            data-folio="{{ $corte->Folio }}"
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
                                    <span class="px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-700">Finalizado</span>
                                @elseif($corte->Status === 'En Proceso')
                                    <span class="px-3 py-1.5 rounded-full text-sm font-semibold bg-blue-100 text-blue-700">En Proceso</span>
                                @else
                                    <span class="px-3 py-1.5 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-700">{{ $corte->Status }}</span>
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
                <label for="select-fechas" class="block text-sm font-medium text-gray-700 mb-1">Fechas de folios</label>
                <select id="select-fechas" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($fechasUnicas as $fecha)
                        <option value="{{ $fecha }}">{{ Carbon::createFromFormat('Y-m-d', $fecha)->format('d/m/Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="px-4 py-3 border-t flex justify-end gap-2">
                <button id="modal-fechas-ok" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Visualizar</button>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(() => {
    'use strict';

    const CONFIG = {
        urls: {
            detalle: '/modulo-cortes-de-eficiencia/',
            editar: '{{ url("/modulo-cortes-de-eficiencia") }}?folio=',
            finalizar: '/modulo-cortes-de-eficiencia/{folio}/finalizar'
        },
        timeout: 30000,
        ultimoFolio: @json(isset($ultimoFolio) ? $ultimoFolio->Folio : null)
    };

    class CortesManager {
        constructor() {
            this.state = {
                folio: null,
                status: null,
                abortController: null
            };

            this.dom = {
                btns: {
                    nuevo: document.getElementById('btn-nuevo'),
                    editar: document.getElementById('btn-editar'),
                    finalizar: document.getElementById('btn-finalizar'),
                    visualizar: document.getElementById('btn-visualizar'),
                    fechas: document.getElementById('btn-fechas')
                },
                modal: {
                    fechas: document.getElementById('modal-fechas'),
                    close: document.getElementById('modal-fechas-close'),
                    ok: document.getElementById('modal-fechas-ok'),
                    select: document.getElementById('select-fechas')
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
            
            // Abrir/cerrar modal de fechas
            this.dom.btns.fechas?.addEventListener('click', () => this.abrirModalFechas());
            this.dom.modal.close?.addEventListener('click', () => this.cerrarModalFechas());
            this.dom.modal.fechas?.addEventListener('click', (e) => {
                if (e.target?.dataset?.close === 'true') this.cerrarModalFechas();
            });
            // Confirmar visualización por fecha
            this.dom.modal.ok?.addEventListener('click', () => this.visualizarPorFecha());
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
            window.location.href = '/modulo-cortes-de-eficiencia/visualizar/' + this.state.folio;
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
            const sel = this.dom.modal.select;
            if (!sel || !sel.value) {
                Swal.fire('Fecha requerida', 'Selecciona una fecha para visualizar.', 'warning');
                return;
            }
            
            // Buscar el primer folio de esa fecha
            const fecha = sel.value; // formato YYYY-MM-DD
            const rows = document.querySelectorAll('tbody tr[data-folio]');
            
            for (const row of rows) {
                const fechaCell = row.children[1]?.textContent?.trim();
                if (!fechaCell) continue;
                
                // Convertir formato d/m/Y a Y-m-d para comparar
                const partes = fechaCell.split('/');
                if (partes.length === 3) {
                    const fechaRow = `${partes[2]}-${partes[1].padStart(2, '0')}-${partes[0].padStart(2, '0')}`;
                    if (fechaRow === fecha) {
                        const folio = row.dataset.folio;
                        this.cerrarModalFechas();
                        window.location.href = `/modulo-cortes-de-eficiencia/visualizar/${folio}`;
                        return;
                    }
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
    });

})();
</script>
@endsection
