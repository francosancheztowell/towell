@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Consultar Cortes de Eficiencia')

@section('content')
<div class="container mx-auto px-4 py-6">

    @if($cortes->count() > 0)
        <!-- Lista de Cortes de Eficiencia -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Folio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Turno</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Usuario</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">No. Empleado</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($cortes as $corte)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 border-r border-gray-200">
                                    {{ $corte->Folio }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                    {{ \Carbon\Carbon::parse($corte->Date)->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                    Turno {{ $corte->Turno }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                    {{ $corte->nombreEmpl ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                                    {{ $corte->numero_empleado ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm border-r border-gray-200">
                                    @if($corte->Status == 'Finalizado')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Finalizado
                                        </span>
                                    @elseif($corte->Status == 'En Proceso')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            En Proceso
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ $corte->Status }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <div class="flex space-x-2">
                                        <button onclick="toggleLineasPanel('{{ $corte->Folio }}')" class="text-blue-600 hover:text-blue-800" title="Ver líneas de este corte">
                                            <i class="fas fa-eye text-2xl"></i>
                                        </button>
                                        <button onclick="editarCorte('{{ $corte->Folio }}')" class="text-green-600 hover:text-green-800" title="Editar">
                                            <i class="fas fa-edit text-2xl"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel de líneas debajo de la tabla principal -->
        <div id="lineas-panel" class="bg-white rounded-lg shadow-md overflow-hidden hidden">
            <div class="px-4 py-3 border-b">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">Líneas del corte <span id="lineas-folio" class="font-semibold"></span></div>
                    <button onclick="cerrarLineasPanel()" class="text-gray-500 hover:text-gray-700 text-sm">Cerrar</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <tbody id="lineas-tbody"></tbody>
                </table>
            </div>
        </div>
    @else
        <!-- Mensaje cuando no hay cortes -->
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay cortes de eficiencia registrados</h3>
            <p class="text-gray-500">Haz clic en "Nuevo Corte" para crear el primer corte de eficiencia</p>
            <a href="{{ route('cortes.eficiencia') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>
                Nuevo Corte
            </a>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Dataset con líneas por folio para render inmediato (inyectado desde el backend)
    const lineasPorFolio = {!! json_encode(
        $cortes->mapWithKeys(function($c) {
            return [
                $c->Folio => $c->lineas->map(function($l) {
                    return [
                        'NoTelarId' => $l->NoTelarId,
                        'RpmStd' => $l->RpmStd,
                        'EficienciaStd' => $l->EficienciaStd,
                        'RpmR1' => $l->RpmR1,
                        'EficienciaR1' => $l->EficienciaR1,
                        'RpmR2' => $l->RpmR2,
                        'EficienciaR2' => $l->EficienciaR2,
                        'RpmR3' => $l->RpmR3,
                        'EficienciaR3' => $l->EficienciaR3,
                        'ObsR1' => $l->ObsR1,
                        'ObsR2' => $l->ObsR2,
                        'ObsR3' => $l->ObsR3,
                    ];
                })->toArray()
            ];
        })->toArray()
    ) !!};

    function renderLineasTabla(folio) {
        const cont = document.getElementById('lineas-tbody');
        if (!cont) return;
        cont.innerHTML = '';
        const rows = lineasPorFolio[folio] || [];
        if (!rows.length) {
            cont.innerHTML = '<tr><td class="px-4 py-3 text-gray-500">Sin líneas capturadas.</td></tr>';
            return;
        }
        rows.forEach(l => {
            const tr = document.createElement('tr');
            tr.className = 'border-b';
            tr.innerHTML = `
                <td class="px-2 py-1 font-semibold border-r">Telar ${l.NoTelarId ?? '-'}</td>
                <td class="px-2 py-1 border-r">STD: ${l.RpmStd ?? '-'} / ${l.EficienciaStd ?? '-'}</td>
                <td class="px-2 py-1 border-r">R1: ${l.RpmR1 ?? '-'} / ${l.EficienciaR1 ?? '-'}</td>
                <td class="px-2 py-1 border-r">R2: ${l.RpmR2 ?? '-'} / ${l.EficienciaR2 ?? '-'}</td>
                <td class="px-2 py-1 border-r">R3: ${l.RpmR3 ?? '-'} / ${l.EficienciaR3 ?? '-'}</td>
                <td class="px-2 py-1">Obs: ${(l.ObsR1 || '')} ${(l.ObsR2 || '')} ${(l.ObsR3 || '')}</td>
            `;
            cont.appendChild(tr);
        });
    }

    function toggleLineasPanel(folio) {
        const panel = document.getElementById('lineas-panel');
        const folioSpan = document.getElementById('lineas-folio');
        if (!panel) return;
        folioSpan.textContent = folio;
        renderLineasTabla(folio);
        panel.classList.remove('hidden');
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function cerrarLineasPanel() {
        const panel = document.getElementById('lineas-panel');
        if (panel) panel.classList.add('hidden');
    }

    // Editar corte existente
    function editarCorte(folio) {
        Swal.fire({
            title: 'Editar Corte',
            text: '¿Deseas editar este corte de eficiencia?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, editar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirigir a la página de edición
                window.location.href = `/modulo-cortes-de-eficiencia?folio=${folio}`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        console.log('Consultar Cortes de Eficiencia cargado - {{ $cortes->count() }} registros');
    });
</script>
@endsection
