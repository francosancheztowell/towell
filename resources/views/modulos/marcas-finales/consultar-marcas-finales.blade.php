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
                  onclick="MarcasManager.seleccionar('{{ $marca->Folio }}', this)">
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
            const isFinalizado = this.state.status === 'Finalizado';
            const hayFolioSeleccionado = this.state.folio !== null;

            if (this.dom.btns.nuevo) this.dom.btns.nuevo.disabled = false;
            if (this.dom.btns.editar) this.dom.btns.editar.disabled = !hayFolioSeleccionado || isFinalizado;
            if (this.dom.btns.finalizar) this.dom.btns.finalizar.disabled = !hayFolioSeleccionado || isFinalizado;
        }

        async accionNuevo() {
            // Verificar si ya existe un folio en proceso
            try {
                const response = await fetch('/modulo-marcas/generar-folio', {
                    method: 'POST',
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
                window.location.href = '{{ route("marcas.nuevo") }}';
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

                // Solo validar el campo Marcas
                let lineasConMarcasInvalidas = 0;
                for (const l of lineas) {
                    if (esVacioOCero(l.Marcas)) lineasConMarcasInvalidas++;
                }

                if (lineasConMarcasInvalidas > 0) {
                    await Swal.fire({
                        icon: 'warning',
                        title: 'No se puede finalizar',
                        text: `Hay ${lineasConMarcasInvalidas} línea(s) con el campo Marcas vacío o en 0.`,
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }

                return true;
            } catch (err) {
                Swal.close();
                throw err;
            }
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
