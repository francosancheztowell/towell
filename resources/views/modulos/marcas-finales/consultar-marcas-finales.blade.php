@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Consultar Marcas Finales')

@php
    use Carbon\Carbon;
@endphp

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create
      id="btn-nuevo"
      title="Nuevo"
      module="Marcas Finales"
      :disabled="false"
      icon="fa-plus"
      iconColor="text-green-600"
      hoverBg="hover:bg-green-100" />

    <x-navbar.button-edit
      id="btn-editar"
      title="Editar"
      module="Marcas Finales"
      :disabled="true"
      icon="fa-pen-to-square"
      iconColor="text-blue-600"
      hoverBg="hover:bg-blue-100" />

    <x-navbar.button-report
      id="btn-finalizar"
      title="Finalizar"
      module="Marcas Finales"
      :disabled="false"
      icon="fa-check"
      iconColor="text-orange-600"
      hoverBg="hover:bg-orange-100"
      />
</div>
@endsection

@section('content')
<div class="w-full">
    <div class="flex flex-col gap-3 w-full max-h-[calc(100vh-140px)]">
    @if(isset($marcas) && $marcas->count() > 0)
            <!-- Panel Superior: Lista de Folios -->
      <div class="bg-white rounded-md shadow-sm overflow-hidden w-full flex-shrink-0">
                <div class="overflow-auto max-h-[calc((100vh-200px)/2)]">
                    <table class="w-full table-fixed text-xs border-separate border-spacing-0">
            <thead class="bg-blue-600 text-white sticky top-0 z-10">
              <tr>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-24">Folio</th>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-28">Fecha</th>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-24">Turno</th>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-32">Empleado</th>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-28">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @foreach($marcas as $marca)
                            <tr class="hover:bg-blue-50 cursor-pointer transition-colors marca-row {{ isset($ultimoFolio) && $ultimoFolio->Folio == $marca->Folio ? 'bg-blue-100 border-l-4 border-blue-600' : '' }}"
                  id="row-{{ $marca->Folio }}"
                  data-folio="{{ $marca->Folio }}"
                  onclick="MarcasManager.seleccionar('{{ $marca->Folio }}', this)">
                <td class="px-2 py-2 font-semibold text-gray-900 truncate">{{ $marca->Folio }}</td>
                                <td class="px-2 py-2 text-gray-900 truncate">
                                    @if($marca->Date)
                                        {{ Carbon::parse($marca->Date)->format('d/m/Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-gray-900 truncate">{{ $marca->Turno }}</td>
                <td class="px-2 py-2 text-gray-900 truncate">{{ $marca->numero_empleado ?? 'N/A' }}</td>
                <td class="px-2 py-2">
                  @if($marca->Status === 'Finalizado')
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-700">Finalizado</span>
                  @elseif($marca->Status === 'En Proceso')
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-700">En Proceso</span>
                  @else
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-700">{{ $marca->Status }}</span>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

            <!-- Panel Inferior: Preview / Detalle -->
      {{-- <div id="preview-panel" class="bg-white rounded-md shadow-sm overflow-hidden w-full hidden flex-shrink-0">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-1.5 border-b border-blue-700 flex-shrink-0">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0 flex-1">
              <div class="flex items-center gap-2 min-w-0">
                <i class="fas fa-file-alt text-white text-xs"></i>
                <span id="prev-folio" class="text-xs font-bold text-white truncate">-</span>
              </div>
              <span class="text-white/80 text-[10px] hidden sm:inline">·</span>
              <span id="prev-meta" class="text-[10px] text-white/90 truncate hidden sm:inline">-</span>
            </div>
            <span id="prev-status-container" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-white/20 text-white border border-white/30 whitespace-nowrap">-</span>
          </div>
        </div>

                <div class="overflow-auto max-h-[calc((100vh-200px)/2)]">
                    <table class="w-full table-fixed text-xs border-separate border-spacing-0">
            <thead class="bg-blue-600 text-white sticky top-0 z-10">
              <tr>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-20">Telar</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-24">Efic. STD</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Marcas</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Trama</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Pie</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Rizo</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Otros</th>
              </tr>
            </thead>
            <tbody id="preview-body" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-gray-500">Haga clic en un registro de la lista superior para ver los detalles.</td>
                        </tr>
                    </tbody>
          </table>
        </div>
      </div> --}}

    @else
            <!-- Sin Registros -->
      <div class="bg-white rounded-md shadow-sm p-8 text-center w-full">
        <h3 class="text-lg font-semibold text-gray-700 mb-2">No hay marcas registradas</h3>
                <p class="text-gray-500 mb-4">Toca "Nueva Marca" para crear el primer registro.</p>
        <x-navbar.button-create
                  id="btn-nuevo-empty"
          title="Nuevo"
          module="Marcas Finales"
          :disabled="false"
          icon="fa-plus"
          iconColor="text-green-600"
          hoverBg="hover:bg-green-100" />
      </div>
    @endif
  </div>
</div>

<style>
section.content { width: 100% !important; max-width: 100% !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(() => {
    'use strict';

    const CONFIG = {
        urls: {
            detalle: '/modulo-marcas/',
            editar: '{{ url("/modulo-marcas") }}?folio=',
            finalizar: '/modulo-marcas/{folio}/finalizar'
        },
        timeout: 30000, // 30 segundos (suficiente para cualquier petición)
        ultimoFolio: @json(isset($ultimoFolio) ? $ultimoFolio->Folio : null)
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
            this.activarPanel(true);

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
                this.renderizarDetalle(data.marca, data.lineas || []);
                this.actualizarBotones();

            } catch (err) {
                if (err.name === 'AbortError') return;
                this.mostrarError(err.message);
            }
        }

        activarPanel(activo) {
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
            const isFinalizado = this.state.status === 'Finalizado';

            if (this.dom.btns.nuevo) this.dom.btns.nuevo.disabled = false;
            if (this.dom.btns.editar) this.dom.btns.editar.disabled = isFinalizado;
            if (this.dom.btns.finalizar) this.dom.btns.finalizar.disabled = isFinalizado;
        }

        accionNuevo() {
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
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.MarcasManager = new MarcasManager();

        // Agregar listener al botón "Nuevo" cuando no hay registros
        const btnNuevoEmpty = document.getElementById('btn-nuevo-empty');
        if (btnNuevoEmpty) {
            btnNuevoEmpty.addEventListener('click', () => {
                window.location.href = '{{ route("marcas.nuevo") }}';
            });
        }
    });

})();
</script>
@endsection
