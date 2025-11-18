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
      :disabled="false"
      icon="fa-pen-to-square"
      iconColor="text-blue-600"
      hoverBg="hover:bg-blue-100" />

    <x-navbar.button-report
      id="btn-finalizar"
      title="Finalizar"
      :moduleId="25"
      :disabled="false"
      icon="fa-check"
      iconColor="text-orange-600"
      hoverBg="hover:bg-orange-100" />
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
                            <tr class="hover:bg-blue-50 cursor-pointer transition-colors {{ isset($ultimoFolio) && $ultimoFolio->Folio == $marca->Folio ? 'bg-blue-100 border-l-4 border-blue-600' : '' }}"
                  data-folio="{{ $marca->Folio }}"
                                onclick="MarcasConsultar.seleccionar('{{ $marca->Folio }}', this)">
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
      <div id="preview-panel" class="bg-white rounded-md shadow-sm overflow-hidden w-full hidden flex-shrink-0">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-1.5 border-b border-blue-700 flex-shrink-0">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0 flex-1">
              <div class="flex items-center gap-2 min-w-0">
                <i class="fas fa-file-alt text-white text-xs"></i>
                <span id="preview-folio" class="text-xs font-bold text-white truncate">-</span>
              </div>
              <span class="text-white/80 text-[10px] hidden sm:inline">·</span>
              <span id="preview-meta" class="text-[10px] text-white/90 truncate hidden sm:inline">-</span>
            </div>
            <span id="preview-status" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-white/20 text-white border border-white/30 whitespace-nowrap">-</span>
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
            <tbody id="preview-lineas" class="divide-y divide-gray-100"></tbody>
          </table>
        </div>
      </div>
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

    const MarcasConsultar = {
        marcaSeleccionada: null,
        statusSeleccionado: null,
        timeoutId: null,
        abortController: null,

        init() {
            this.setupEventListeners();
            this.setupInitialState();
            this.autoSelectLastFolio();
        },

        setupEventListeners() {
            const btnEditar = document.getElementById('btn-editar');
            const btnFinalizar = document.getElementById('btn-finalizar');

            if (btnEditar) {
                btnEditar.addEventListener('click', () => this.editarMarca());
            }

            if (btnFinalizar) {
                btnFinalizar.addEventListener('click', () => this.finalizarMarca());
            }
        },

        setupInitialState() {
            const btnEditar = document.getElementById('btn-editar');
            const btnFinalizar = document.getElementById('btn-finalizar');

            if (btnEditar) btnEditar.disabled = true;
            if (btnFinalizar) btnFinalizar.disabled = true;
        },

        autoSelectLastFolio() {
            @if(isset($ultimoFolio))
            window.addEventListener('load', () => {
                const folio = '{{ $ultimoFolio->Folio }}';
                const tr = document.querySelector(`tr[data-folio="${folio}"]`);
                if (tr) {
                    try {
                        this.seleccionar(folio, tr);
                    } catch (e) {
                        console.error('Error al auto-seleccionar:', e);
                    }
                }
            });
            @endif
        },

        seleccionar(folio, row) {
            this.marcaSeleccionada = folio;
            this.highlightRow(row);

            if (this.abortController) {
                this.abortController.abort();
            }

            this.abortController = new AbortController();
            this.timeoutId = setTimeout(() => this.abortController.abort(), 10000);

            fetch(`/modulo-marcas/${folio}`, {
                headers: { 'Accept': 'application/json' },
                signal: this.abortController.signal
            })
            .then(response => {
                clearTimeout(this.timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
    .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Error al cargar los detalles');
                }
                this.statusSeleccionado = data.marca?.Status;
                this.configurarBotones(this.statusSeleccionado);
                this.mostrarDetalles(data.marca, data.lineas || []);
    })
            .catch(error => {
                if (error.name === 'AbortError') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tiempo agotado',
                        text: 'La solicitud tardó demasiado. Intenta nuevamente.'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'No se pudieron cargar los detalles.'
                    });
                }
                console.error('Error al cargar marca:', error);
    });
        },

        highlightRow(row) {
            document.querySelectorAll('tbody tr').forEach(tr => {
                tr.classList.remove('bg-blue-100', 'border-l-4', 'border-blue-600');
            });
            row.classList.add('bg-blue-100', 'border-l-4', 'border-blue-600');
        },

        configurarBotones(status) {
  const btnNuevo = document.getElementById('btn-nuevo');
            const btnEditar = document.getElementById('btn-editar');
            const btnFinalizar = document.getElementById('btn-finalizar');

            if (!btnEditar || !btnFinalizar) return;

            const isFinalizado = status === 'Finalizado';
            const isEnProceso = status === 'En Proceso';

            if (btnNuevo) {
                btnNuevo.disabled = isFinalizado || isEnProceso;
            }

            btnEditar.disabled = isFinalizado;
            btnFinalizar.disabled = isFinalizado;
        },

        mostrarDetalles(marca, lineas) {
   const panel = document.getElementById('preview-panel');
            if (!panel) return;

   panel.classList.remove('hidden');
            this.updateHeader(marca);
            this.updateTable(lineas);
        },

        updateHeader(marca) {
            const folioEl = document.getElementById('preview-folio');
            const metaEl = document.getElementById('preview-meta');
   const statusEl = document.getElementById('preview-status');

            if (folioEl) {
                folioEl.textContent = marca?.Folio ?? '-';
            }

            if (metaEl) {
                const fecha = marca?.Date ? this.formatFecha(marca.Date) : '-';
                const turno = marca?.Turno ?? '-';
                const empleado = marca?.numero_empleado ?? 'N/A';
                metaEl.textContent = `${fecha} · T${turno} · ${empleado}`;
            }

            if (statusEl) {
                const status = marca?.Status ?? '-';
   statusEl.textContent = status;
   statusEl.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-white/30 whitespace-nowrap';

                if (status === 'Finalizado') {
     statusEl.classList.add('bg-green-500/30', 'text-white');
                } else if (status === 'En Proceso') {
     statusEl.classList.add('bg-yellow-500/30', 'text-white');
   } else {
     statusEl.classList.add('bg-white/20', 'text-white');
   }
            }
        },

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
        },

        updateTable(lineas) {
  const tbody = document.getElementById('preview-lineas');
            if (!tbody) return;

  tbody.innerHTML = '';

            if (!lineas || !lineas.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Sin líneas capturadas</td></tr>';
    return;
  }

            lineas.forEach((linea, index) => {
    const tr = document.createElement('tr');
                tr.className = index % 2 === 0
                    ? 'bg-white hover:bg-gray-50'
                    : 'bg-gray-50 hover:bg-gray-100';

                const eficiencia = this.formatEficiencia(linea);

    tr.innerHTML = `
                    <td class="px-2 py-2 font-semibold text-gray-900 truncate">${linea.NoTelarId ?? '-'}</td>
                    <td class="px-2 py-2 text-center">${eficiencia}</td>
                    <td class="px-2 py-2 text-center">${linea.Marcas ?? '-'}</td>
                    <td class="px-2 py-2 text-center">${linea.Trama ?? '-'}</td>
                    <td class="px-2 py-2 text-center">${linea.Pie ?? '-'}</td>
                    <td class="px-2 py-2 text-center">${linea.Rizo ?? '-'}</td>
                    <td class="px-2 py-2 text-center">${linea.Otros ?? '-'}</td>
                `;
    tbody.appendChild(tr);
  });
        },

        formatEficiencia(linea) {
            const eVal = linea.Eficiencia ?? linea.EficienciaSTD ?? linea.EficienciaStd ?? null;
            if (eVal === null) return '-';
            if (isNaN(eVal)) return eVal;
            return `${(Number(eVal) * 100).toFixed(0)}%`;
        },

        editarMarca() {
            if (!this.marcaSeleccionada) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin selección',
                    text: 'Selecciona un folio para editar'
                });
                return;
            }
            window.location.href = `{{ url('/modulo-marcas') }}?folio=${this.marcaSeleccionada}`;
        },

        finalizarMarca() {
            if (!this.marcaSeleccionada) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin selección',
                    text: 'Selecciona un folio para finalizar'
                });
                return;
            }

            Swal.fire({
                title: 'Finalizar Marca',
                text: `¿Deseas finalizar el folio ${this.marcaSeleccionada}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (!result.isConfirmed) return;

  Swal.fire({
                    title: 'Finalizando...',
                    didOpen: () => Swal.showLoading(),
                    allowOutsideClick: false
                });

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                fetch(`/modulo-marcas/${this.marcaSeleccionada}/finalizar`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
      }
    })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Finalizado',
                            text: data.message || 'Marca finalizada correctamente'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'No se pudo finalizar'
                        });
                    }
    })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Error de conexión'
                    });
                });
  });
}
    };

    document.addEventListener('DOMContentLoaded', () => MarcasConsultar.init());
    window.MarcasConsultar = MarcasConsultar;
})();
</script>
@endsection
