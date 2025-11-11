@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Consultar Cortes de Eficiencia')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button id="btn-editar-folio" onclick="editarFolioSeleccionado()" disabled
        title="Editar Folio"
            class="px-3 py-2 text-blue-600 rounded-md hover:text-blue-700 disabled:text-gray-300 disabled:cursor-not-allowed text-sm">
            <i class="fas fa-edit mr-2"></i>
        </button>
        <button id="btn-terminar-folio" onclick="terminarFolioSeleccionado()" disabled
        title="Terminar Corte"
            class="px-3 py-2 text-emerald-600 rounded-md hover:text-emerald-700 disabled:text-gray-300 disabled:cursor-not-allowed text-sm">
            <i class="fas fa-check mr-2"></i>
        </button>
    </div>
@endsection

@section('content')
<div class="w-full">

    @if($cortes->count() > 0)
        {{-- Contenedor en grid para que quepan ambas tablas en pantalla de tablet --}}
        <div class="grid gap-3 md:gap-4 h-[calc(100vh-7rem)] md:h-[calc(100vh-8rem)] grid-rows-[46%_54%] md:grid-rows-[42%_58%]">
            {{-- Tabla principal --}}
            <div class="bg-white rounded-md border overflow-hidden flex flex-col">
                <div class="flex-1 overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-blue-500 text-white sticky top-0 z-10">
                            <tr>
                                <th class="px-3 md:px-4 py-2 text-left">Folio</th>
                                <th class="px-3 md:px-4 py-2 text-left">Fecha</th>
                                <th class="px-3 md:px-4 py-2 text-left">Turno</th>
                                <th class="px-3 md:px-4 py-2 text-left">Usuario</th>
                                <th class="px-3 md:px-4 py-2 text-left">No. Empleado</th>
                                <th class="px-3 md:px-4 py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($cortes as $corte)
                                <tr class="hover:bg-blue-50 cursor-pointer"
                                    data-folio="{{ $corte->Folio }}"
                                    onclick="toggleLineasPanel('{{ $corte->Folio }}')">
                                    <td class="px-3 md:px-4 py-2 font-semibold text-gray-900">
                                        {{ $corte->Folio }}
                                    </td>
                                    <td class="px-3 md:px-4 py-2 text-gray-900">
                                        {{ \Carbon\Carbon::parse($corte->Date)->format('d/m/Y') }}
                                    </td>
                                    <td class="px-3 md:px-4 py-2 text-gray-900">
                                        Turno {{ $corte->Turno }}
                                    </td>
                                    <td class="px-3 md:px-4 py-2 text-gray-900">
                                        {{ $corte->nombreEmpl ?? 'N/A' }}
                                    </td>
                                    <td class="px-3 md:px-4 py-2 text-gray-900">
                                        {{ $corte->numero_empleado ?? 'N/A' }}
                                    </td>
                                    <td class="px-3 md:px-4 py-2">
                                        @if($corte->Status == 'Finalizado')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Finalizado
                                            </span>
                                        @elseif($corte->Status == 'En Proceso')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                En Proceso
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                {{ $corte->Status }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Panel de líneas (detalle) --}}
            <div id="lineas-panel" class="bg-white rounded-md border overflow-hidden hidden flex flex-col">
                <div class="flex-1 overflow-auto">
                    <table class="w-full text-xs md:text-sm">
                        <thead class="bg-blue-500 text-white sticky top-0 z-10">
                            <tr>
                                <th class="px-3 md:px-4 py-2 text-left">Telar</th>
                                <th class="px-3 md:px-4 py-2 text-center">RPM STD</th>
                                <th class="px-3 md:px-4 py-2 text-center">Efic. STD</th>
                                <th class="px-3 md:px-4 py-2 text-center">Horario 1</th>
                                <th class="px-3 md:px-4 py-2 text-center">Horario 2</th>
                                <th class="px-3 md:px-4 py-2 text-center">Horario 3</th>
                                <th class="px-3 md:px-4 py-2 text-left">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="lineas-tbody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        {{-- Vacío --}}
        <div class="bg-white rounded-md border p-8 text-center w-full">
            <h3 class="text-base md:text-lg font-semibold text-gray-700 mb-2">No hay cortes de eficiencia</h3>
            <p class="text-gray-500">Crea el primero con “Nuevo Corte”.</p>
            <a href="{{ route('cortes.eficiencia') }}"
               class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i> Nuevo Corte
            </a>
        </div>
    @endif
</div>

<style>
/* Encabezados pegajosos sencillos */
thead.sticky, thead.bg-blue-600 { position: sticky; top: 0; }
/* Resaltado de selección */
.fila-seleccionada { background-color: #dbeafe !important; }
/* Quitar ornamentos extra para un look limpio en tablet */
table { border-collapse: separate; border-spacing: 0; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ==== Dataset inyectado desde backend (igual que tu versión) ====
    const lineasPorFolio = {!! json_encode(
        $cortes->mapWithKeys(function($c) {
            return [
                $c->Folio => $c->lineas->map(function($l) {
                    return [
                        'NoTelarId'    => $l->NoTelarId,
                        'RpmStd'       => $l->RpmStd,
                        'EficienciaStd'=> $l->EficienciaSTD,
                        'RpmR1'        => $l->RpmR1,
                        'EficienciaR1' => $l->EficienciaR1,
                        'RpmR2'        => $l->RpmR2,
                        'EficienciaR2' => $l->EficienciaR2,
                        'RpmR3'        => $l->RpmR3,
                        'EficienciaR3' => $l->EficienciaR3,
                        'ObsR1'        => $l->ObsR1,
                        'ObsR2'        => $l->ObsR2,
                        'ObsR3'        => $l->ObsR3,
                    ];
                })->toArray()
            ];
        })->toArray()
    ) !!};

    let folioSeleccionado = null;
    let statusFolioSeleccionado = null;

    function actualizarEstadoBotones() {
        const btnEditar = document.getElementById('btn-editar-folio');
        const btnTerminar = document.getElementById('btn-terminar-folio');
        if (!btnEditar || !btnTerminar) return;

        if (!folioSeleccionado) {
            btnEditar.disabled = true; btnTerminar.disabled = true;
        } else if (statusFolioSeleccionado === 'Finalizado') {
            btnEditar.disabled = true; btnTerminar.disabled = true;
        } else {
            btnEditar.disabled = false; btnTerminar.disabled = false;
        }
    }

    function renderLineasTabla(folio) {
        const cont = document.getElementById('lineas-tbody');
        if (!cont) return;
        cont.innerHTML = '';
        const rows = lineasPorFolio[folio] || [];
        if (!rows.length) {
            cont.innerHTML = `<tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Sin líneas capturadas para este corte</td></tr>`;
            return;
        }

        const fmt = (v, suf = '') => (!v || v === '0' || v === 0) ? '-' : `${v}${suf}`;

        rows.forEach((l, i) => {
            const tr = document.createElement('tr');
            tr.className = i % 2 ? 'bg-gray-50' : 'bg-white';
            tr.innerHTML = `
                <td class="px-3 md:px-4 py-2 font-medium text-gray-900">${l.NoTelarId ?? '-'}</td>
                <td class="px-3 md:px-4 py-2 text-center">${fmt(l.RpmStd)}</td>
                <td class="px-3 md:px-4 py-2 text-center">${fmt(l.EficienciaStd, '%')}</td>
                <td class="px-3 md:px-4 py-2 text-center">
                    <div>RPM: ${fmt(l.RpmR1)}</div>
                    <div>Efic: ${fmt(l.EficienciaR1, '%')}</div>
                </td>
                <td class="px-3 md:px-4 py-2 text-center">
                    <div>RPM: ${fmt(l.RpmR2)}</div>
                    <div>Efic: ${fmt(l.EficienciaR2, '%')}</div>
                </td>
                <td class="px-3 md:px-4 py-2 text-center">
                    <div>RPM: ${fmt(l.RpmR3)}</div>
                    <div>Efic: ${fmt(l.EficienciaR3, '%')}</div>
                </td>
                <td class="px-3 md:px-4 py-2">
                    ${[l.ObsR1, l.ObsR2, l.ObsR3].filter(Boolean).join('<br>') || 'Sin observaciones'}
                </td>
            `;
            cont.appendChild(tr);
        });
    }

    function toggleLineasPanel(folio) {
        // seleccionar
        seleccionarFolio(folio);

        const panel = document.getElementById('lineas-panel');
        if (!panel) return;

        renderLineasTabla(folio);
        panel.classList.remove('hidden');
        resaltarFilaSeleccionada(folio);
    }

    function seleccionarFolio(folio) {
        const fila = document.querySelector(`tr[data-folio="${folio}"]`);
        const statusEl = fila?.querySelector('.bg-green-100, .bg-blue-100, .bg-yellow-100');
        const status = statusEl?.textContent?.trim() || '';
        folioSeleccionado = folio;
        statusFolioSeleccionado = status;
        actualizarEstadoBotones();
    }

    function editarFolioSeleccionado() {
        if (!folioSeleccionado || statusFolioSeleccionado === 'Finalizado') return;
        window.location.href = `/modulo-cortes-de-eficiencia?folio=${folioSeleccionado}`;
    }

    function terminarFolioSeleccionado() {
        if (!folioSeleccionado || statusFolioSeleccionado === 'Finalizado') return;
        finalizarCorte(folioSeleccionado);
    }

    function finalizarCorte(folio) {
        Swal.fire({
            title: 'Finalizar Corte',
            text: `¿Finalizar el corte ${folio}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar'
        }).then((r) => {
            if (!r.isConfirmed) return;
            Swal.showLoading();
            fetch(`/modulo-cortes-de-eficiencia/${folio}/finalizar`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) location.reload();
                else Swal.fire({ icon:'error', title:'Error', text: d.message || 'No se pudo finalizar' });
            })
            .catch(e => Swal.fire({ icon:'error', title:'Error de conexión', text: e.message }));
        });
    }

    function resaltarFilaSeleccionada(folio) {
        document.querySelectorAll('.fila-seleccionada').forEach(f => f.classList.remove('fila-seleccionada'));
        document.querySelector(`tr[data-folio="${folio}"]`)?.classList.add('fila-seleccionada');
    }

    document.addEventListener('DOMContentLoaded', () => {
        actualizarEstadoBotones();
        document.querySelectorAll('tr[data-folio]').forEach(fila => {
            fila.addEventListener('click', function () {
                const folio = this.getAttribute('data-folio');
                toggleLineasPanel(folio);
            });
        });
    });
</script>
@endsection
