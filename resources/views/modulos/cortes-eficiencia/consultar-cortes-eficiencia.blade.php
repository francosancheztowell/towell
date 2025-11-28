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
</div>
@endsection

@section('content')
<div class="w-full h-[calc(100vh-100px)] flex flex-col px-4 py-4 md:px-6 lg:px-8">
    <div class="flex flex-col flex-1 bg-white rounded-lg shadow-md overflow-hidden max-w-full">
    @if(isset($cortes) && $cortes->count() > 0)
        <!-- Header fijo fuera del scroll -->
        <div class="bg-blue-600 text-white">
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
        <div class="flex-1 overflow-y-auto">
            <table class="w-full text-sm">
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
                  onclick="CortesManager.seleccionar('{{ $corte->Folio }}', this)">
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

<!-- Se removió CSS personalizado; todo se maneja con utilidades Tailwind -->

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
                    finalizar: document.getElementById('btn-finalizar')
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
        }

        async accionNuevo() {
            // Verificar si ya existe un folio en proceso
            try {
                const response = await fetch('/modulo-cortes-de-eficiencia/generar-folio', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (response.status === 400 && data.folio_existente) {
                    // Ya existe un folio en proceso
                    Swal.fire({
                        icon: 'warning',
                        title: 'Folio en proceso',
                        text: 'Ya existe un folio en proceso: ' + data.folio_existente + '. Debe finalizarlo antes de crear uno nuevo.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                // Si no hay folio en proceso, redirigir a la página de nuevo
                window.location.href = '{{ route("cortes.eficiencia") }}';
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo verificar el estado de los folios'
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
