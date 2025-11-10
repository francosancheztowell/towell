@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-4">
        <div class="bg-blue-500 px-5 py-3 border-t-4 border-orange-400">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-white">Marcas Finales</h1>
                <div class="flex space-x-2">
                    @php
                        $user = Auth::user();
                        $permisosMarcas = null;
                        if ($user) {
                            $permisosMarcas = DB::table('SYSUsuariosRoles')
                                ->join('SYSRoles', 'SYSUsuariosRoles.idrol', '=', 'SYSRoles.idrol')
                                ->where('SYSUsuariosRoles.idusuario', $user->idusuario)
                                ->where('SYSUsuariosRoles.acceso', true)
                                ->where(function($query) {
                                    $query->where('SYSRoles.modulo', 'LIKE', '%Marcas Finales%')
                                          ->orWhere('SYSRoles.modulo', 'LIKE', '%Nuevas Marcas Finales%')
                                          ->orWhere('SYSRoles.modulo', 'LIKE', '%marcas finales%')
                                          ->orWhere('SYSRoles.modulo', 'LIKE', '%nuevas marcas finales%');
                                })
                                ->select('SYSUsuariosRoles.*', 'SYSRoles.modulo')
                                ->first();
                        }
                        $puedeCrear = $permisosMarcas && $permisosMarcas->crear;
                        $puedeModificar = $permisosMarcas && $permisosMarcas->modificar;
                    @endphp

                    @if($puedeCrear)
                        <a href="{{ route('marcas.nuevo') }}" id="btn-nuevo" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 text-sm rounded-lg transition-colors inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i>Nuevo
                        </a>
                    @else
                        <button id="btn-nuevo" disabled class="bg-gray-200 text-gray-500 px-3 py-1.5 text-sm rounded-lg inline-flex items-center cursor-not-allowed">
                            <i class="fas fa-lock mr-2"></i>Nuevo
                        </button>
                    @endif
                    <button id="btn-editar-global" onclick="editarMarcaSeleccionada()" disabled class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1.5 text-sm rounded-lg transition-colors inline-flex items-center {{ $puedeModificar ? '' : 'opacity-50 cursor-not-allowed' }}" {{ $puedeModificar ? '' : 'disabled' }}>
                        <i class="fas fa-edit mr-2"></i>Editar
                    </button>
                    <button id="btn-finalizar-global" onclick="finalizarMarcaSeleccionada()" disabled class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 text-sm rounded-lg transition-colors inline-flex items-center {{ ($puedeModificar || ($permisosMarcas && $permisosMarcas->eliminar)) ? '' : 'opacity-50 cursor-not-allowed' }}" {{ ($puedeModificar || ($permisosMarcas && $permisosMarcas->eliminar)) ? '' : 'disabled' }}>
                        <i class="fas fa-check mr-2"></i>Finalizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if(isset($marcas) && $marcas->count() > 0)
        <!-- Tabla de folios (con scroll interno) -->
        <div class="bg-white rounded-lg shadow-md mb-4">
            <div class="tabla-scroll">
                <table class="w-full table-compact text-xs">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Folio</th>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Fecha</th>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Turno</th>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Usuario</th>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($marcas as $marca)
                        <tr class="hover:bg-gray-50 cursor-pointer {{ isset($ultimoFolio) && $ultimoFolio->Folio == $marca->Folio ? 'fila-seleccionada' : '' }}" onclick="seleccionarMarca('{{ $marca->Folio }}', this)" data-folio="{{ $marca->Folio }}">
                            <td class="px-2 py-2 text-xs font-semibold text-gray-900 border-r border-gray-200">{{ $marca->Folio }}</td>
                            <td class="px-2 py-2 text-xs text-gray-900 border-r border-gray-200">{{ \Carbon\Carbon::parse($marca->Date)->format('d/m/Y') }}</td>
                            <td class="px-2 py-2 text-xs text-gray-900 border-r border-gray-200">Turno {{ $marca->Turno }}</td>
                            <td class="px-2 py-2 text-xs text-gray-900 border-r border-gray-200">{{ $marca->numero_empleado ?? 'N/A' }}</td>
                            <td class="px-2 py-2 text-xs">
                                @if($marca->Status == 'Finalizado')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-800">Finalizado</span>
                                @elseif($marca->Status == 'En Proceso')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-800">En Proceso</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-800">{{ $marca->Status }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Preview / detalle del folio seleccionado -->
        <div id="preview-panel" class="bg-white rounded-lg shadow-md hidden">
            <div class="bg-gray-50 px-5 py-2.5 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 id="preview-folio" class="text-lg font-semibold text-gray-800">Folio: -</h3>
                        <p id="preview-meta" class="text-sm text-gray-600">Fecha: - · Turno: - · Empleado: -</p>
                    </div>
                    <div>
                        <span id="preview-status" class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">-</span>
                    </div>
                </div>
            </div>

            <div class="tabla-scroll-preview">
                <table class="min-w-full text-xs table-compact">
                    <thead class="bg-gray-50 sticky top-0 z-20">
                        <tr>
                            <th class="px-2 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Telar</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Efic. STD</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Marcas</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Trama</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Pie</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rizo</th>
                            <th class="px-2 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Otros</th>
                        </tr>
                    </thead>
                    <tbody id="preview-lineas" class="bg-white divide-y divide-gray-200"></tbody>
                </table>
            </div>
        </div>

    @else
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay marcas registradas</h3>
            <p class="text-gray-500">Haz clic en "Nueva Marca" para crear el primer registro</p>
            <a href="{{ route('marcas.nuevo') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>
                Nueva Marca
            </a>
        </div>
    @endif

</div>

<style>
    .tabla-scroll { max-height: 420px; overflow-y: auto; overflow-x: auto; }
    .tabla-scroll-preview { max-height: 360px; overflow-y: auto; overflow-x: auto; }
    .tabla-scroll::-webkit-scrollbar, .tabla-scroll-preview::-webkit-scrollbar { width:8px; height:8px }
    .tabla-scroll::-webkit-scrollbar-thumb, .tabla-scroll-preview::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:4px }
    .fila-seleccionada { background-color: #e6f0ff !important; border-left: 4px solid #3b82f6 }

    /* Tabla compacta */
    .table-compact th,
    .table-compact td { padding: 0.35rem 0.5rem; line-height: 1.1; }
    .table-compact { font-size: 0.80rem; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let marcaSeleccionada = null;
    let statusSeleccionado = null;

    function seleccionarMarca(folio, row) {
        // Remover selección anterior
        document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('fila-seleccionada'));
        row.classList.add('fila-seleccionada');

        marcaSeleccionada = folio;

        // Cargar detalles con timeout para evitar bloqueos
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 segundos timeout

        fetch(`/modulo-marcas/${folio}`, {
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        })
        .then(r => {
            clearTimeout(timeoutId);
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.json();
        })
        .then(data => {
            if (!data.success) {
                console.warn('La API no devolvió success:', data);
                return;
            }

            // Guardar el status del folio seleccionado
            statusSeleccionado = data.marca.Status;

            // Configurar botones según el status
            configurarBotonesSegunStatus(statusSeleccionado);

            mostrarDetalles(data.marca, data.lineas || data.marca.marcas_line || []);
        })
        .catch(err => {
            if (err.name === 'AbortError') {
                console.error('La solicitud fue cancelada por timeout');
            } else {
                console.error('Error al cargar detalles del folio:', err);
            }
            // Mostrar mensaje de error pero no bloquear la UI
            Swal.fire({
                icon: 'warning',
                title: 'Error al cargar detalles',
                text: 'No se pudieron cargar los detalles del folio, pero puedes continuar navegando.',
                confirmButtonText: 'Entendido'
            });
        });
    }

    function configurarBotonesSegunStatus(status) {
        const btnNuevo = document.getElementById('btn-nuevo');
        const btnEditar = document.getElementById('btn-editar-global');
        const btnFinalizar = document.getElementById('btn-finalizar-global');

        if (status === 'Finalizado') {
            // Folio finalizado: deshabilitar Nuevo y Editar
            btnNuevo.disabled = true;
            btnNuevo.classList.add('opacity-50', 'cursor-not-allowed');
            btnEditar.disabled = true;
            btnEditar.classList.add('opacity-50', 'cursor-not-allowed');
            btnFinalizar.disabled = true;
            btnFinalizar.classList.add('opacity-50', 'cursor-not-allowed');
        } else if (status === 'En Proceso') {
            // Folio en proceso: deshabilitar solo Nuevo
            btnNuevo.disabled = true;
            btnNuevo.classList.add('opacity-50', 'cursor-not-allowed');
            btnEditar.disabled = false;
            btnEditar.classList.remove('opacity-50', 'cursor-not-allowed');
            btnFinalizar.disabled = false;
            btnFinalizar.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            // Otro status: habilitar todos
            btnNuevo.disabled = false;
            btnNuevo.classList.remove('opacity-50', 'cursor-not-allowed');
            btnEditar.disabled = false;
            btnEditar.classList.remove('opacity-50', 'cursor-not-allowed');
            btnFinalizar.disabled = false;
            btnFinalizar.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    function limpiarSeleccion() {
        marcaSeleccionada = null;
        statusSeleccionado = null;

        // Remover selección visual
        document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('fila-seleccionada'));

        // Resetear botones
        const btnNuevo = document.getElementById('btn-nuevo');
        const btnEditar = document.getElementById('btn-editar-global');
        const btnFinalizar = document.getElementById('btn-finalizar-global');

        btnNuevo.disabled = false;
        btnNuevo.classList.remove('opacity-50', 'cursor-not-allowed');
        btnEditar.disabled = true;
        btnEditar.classList.add('opacity-50', 'cursor-not-allowed');
        btnFinalizar.disabled = true;
        btnFinalizar.classList.add('opacity-50', 'cursor-not-allowed');

        // Ocultar panel de detalles
        document.getElementById('preview-panel').classList.add('hidden');
    }

    function mostrarDetalles(marca, lineas) {
        document.getElementById('preview-panel').classList.remove('hidden');
        document.getElementById('preview-folio').textContent = `Folio: ${marca.Folio}`;
        document.getElementById('preview-meta').textContent = `Fecha: ${marca.Date ? new Date(marca.Date).toLocaleDateString() : '-'} · Turno: ${marca.Turno || '-'} · Empleado: ${marca.numero_empleado || '-'} `;
        document.getElementById('preview-status').textContent = marca.Status || '';

        const tbody = document.getElementById('preview-lineas');
        tbody.innerHTML = '';

        if (!lineas || lineas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Sin líneas capturadas para este folio</td></tr>';
            return;
        }

        lineas.forEach((l, idx) => {
            const tr = document.createElement('tr');
            tr.className = idx % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100';
            const efVal = l.Eficiencia ?? l.EficienciaSTD ?? l.EficienciaStd ?? null;
            const ef = efVal !== null ? (isNaN(efVal) ? efVal : (Number(efVal) * 100).toFixed(0) + '%') : '-';
            tr.innerHTML = `\
                <td class="px-4 py-3 font-semibold text-gray-900">${l.NoTelarId ?? '-'}</td>\
                <td class="px-4 py-3 text-center">${ef}</td>\
                <td class="px-4 py-3 text-center">${l.Marcas ?? '-'}</td>\
                <td class="px-4 py-3 text-center">${l.Trama ?? '-'}</td>\
                <td class="px-4 py-3 text-center">${l.Pie ?? '-'}</td>\
                <td class="px-4 py-3 text-center">${l.Rizo ?? '-'}</td>\
                <td class="px-4 py-3 text-center">${l.Otros ?? '-'}</td>`;
            tbody.appendChild(tr);
        });
    }

    function editarMarcaSeleccionada() {
        if (!marcaSeleccionada) return Swal.fire('Sin selección', 'Selecciona un folio para editar', 'warning');
        window.location.href = `{{ url('/modulo-marcas') }}?folio=${marcaSeleccionada}`;
    }

    function finalizarMarcaSeleccionada() {
        if (!marcaSeleccionada) return Swal.fire('Sin selección', 'Selecciona un folio para finalizar', 'warning');

        Swal.fire({
            title: 'Finalizar Marca',
            text: `¿Deseas finalizar el folio ${marcaSeleccionada}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            Swal.fire({ title: 'Finalizando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
            fetch(`/modulo-marcas/${marcaSeleccionada}/finalizar`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    Swal.fire('Finalizado', data.message || 'Marca finalizada', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'No se pudo finalizar', 'error');
                }
            }).catch(err => Swal.fire('Error', err.message || 'Error de conexión', 'error'));
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Inicializar estado de botones (sin selección)
        limpiarSeleccion();

        // Si hay un último folio, seleccionarlo automáticamente después de que la página cargue completamente
        @if(isset($ultimoFolio))
        window.addEventListener('load', () => {
            setTimeout(() => {
                const ultimoFolio = '{{ $ultimoFolio->Folio }}';
                const filaUltimo = document.querySelector(`tr[data-folio="${ultimoFolio}"]`);
                if (filaUltimo) {
                    try {
                        seleccionarMarca(ultimoFolio, filaUltimo);
                    } catch (error) {
                        console.error('Error al seleccionar folio automáticamente:', error);
                    }
                }
            }, 200);
        });
        @endif
    });
</script>

@endsection
