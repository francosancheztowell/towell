@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <div class="container mx-auto">
        <!-- Lista de Requerimientos en Proceso -->
        @if($requerimientos && $requerimientos->count() > 0)
            @php $primer = $requerimientos->first(); @endphp
            <div class="bg-white overflow-hidden">

                <!-- Header azul -->
                <div class="bg-blue-500 px-3 py-3 border-t-4 border-orange-400">
                    <div class="flex items-center">
                        <button onclick="history.back()" class="flex items-center justify-center w-10 h-10 text-white hover:bg-blue-600 rounded-lg transition-colors mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <h2 class="text-2xl font-bold text-white flex-1 text-center">
                            Consultar Requerimiento
                    </h2>
                    </div>
                </div>

                <!-- Contenido: Tabla 1 (folios) y Tabla 2 (detalles seleccionados) -->
                <div class="p-4 sm:p-6 lg:p-8">
                    <div class="mb-6 flex flex-col lg:flex-row gap-3 sm:gap-4 lg:gap-6">
                        <!-- Tabla 1: Folios -->
                        <div class="flex-1 border border-gray-300 rounded-lg overflow-hidden min-w-0">
                            <div class="overflow-y-auto h-32 md:h-24 lg:h-48">
                        <table class="w-full">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                            <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Folio</th>
                                            <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fecha</th>
                                            <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Status</th>
                                            <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Turno</th>
                                            <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Operador</th>
                                </tr>
                            </thead>
                                    <tbody class="bg-white divide-y divide-gray-200" id="tbody-folios">
                                        @foreach($requerimientos as $req)
                                        @php
                                            $statusColors = [
                                                'En Proceso' => 'bg-blue-100 text-blue-800',
                                                'Solicitado' => 'bg-yellow-100 text-yellow-800',
                                                'Surtido' => 'bg-green-100 text-green-800',
                                                'Cancelado' => 'bg-red-100 text-red-800',
                                                'Creado' => 'bg-gray-100 text-gray-800',
                                            ];
                                            $statusClass = $statusColors[$req->Status] ?? 'bg-gray-100 text-gray-800';
                                            $turnoDesc = ['1' => 'Turno 1', '2' => 'Turno 2', '3' => 'Turno 3'];
                                        @endphp
                                        <tr class="hover:bg-gray-50 cursor-pointer" data-folio="{{ $req->Folio }}" onclick="selectFolio('{{ $req->Folio }}', this)">
                                            <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">{{ $req->Folio }}</td>
                                            <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">{{ \Carbon\Carbon::parse($req->Fecha)->format('d/m/Y') }}</td>
                                            <td class="px-4 py-1 border-r border-gray-200">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">{{ $req->Status }}</span>
                                            </td>
                                            <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">{{ $turnoDesc[$req->Turno] ?? $req->Turno }}</td>
                                            <td class="px-4 py-1 text-sm font-semibold text-gray-900">{{ $req->numero_empleado ?? '-' }}</td>
                                </tr>
                                        @endforeach
                            </tbody>
                        </table>
                            </div>
                        </div>

                        <!-- Acciones dinámicas para folio seleccionado -->
                        <div class="flex flex-col space-y-2 lg:min-w-48" id="acciones-contenedor">
                            <button id="btn-solicitar" onclick="accionStatus('Solicitado')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                <i class="fas fa-list mr-2"></i>Solicitar consumo
                            </button>
                            <button id="btn-editar" onclick="editarFolioSeleccionado()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                <i class="fas fa-edit mr-2"></i>Editar
                            </button>
                            <button id="btn-cancelar" onclick="accionStatus('Cancelado')" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button id="btn-resumen" onclick="verResumenSeleccionado()" class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition-colors">
                                <i class="fas fa-eye mr-2"></i>Resumen de articulo
                            </button>
                            <input type="hidden" id="folio-seleccionado" value="{{ $primer->Folio }}">
                            <input type="hidden" id="status-seleccionado" value="{{ $primer->Status }}">
                        </div>
                    </div>

                    <!-- Tabla 2: Detalles del folio seleccionado -->
                    <div class="border border-gray-300 rounded-lg overflow-hidden">
                        <div class="overflow-y-auto h-32 md:h-20 xl:h-48">
                        <table class="w-full">
                                <thead class="sticky top-0 z-10">
                                    <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Folio</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Telar</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Articulo</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fibra</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Cod Color</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre Color</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Cantidad</th>
                                </tr>
                            </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="detalles-tbody">
                                    @if($primer && $primer->consumos && $primer->consumos->count() > 0)
                                        @foreach($primer->consumos as $consumo)
                                <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $primer->Folio }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->NoTelarId }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->CalibreTrama ? number_format($consumo->CalibreTrama, 2) : '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->NombreProducto ?? '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->FibraTrama ?? '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->CodColorTrama ?? '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->ColorTrama ?? '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ $consumo->Cantidad ? number_format($consumo->Cantidad, 0) : '0' }}</td>
                                </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="8" class="px-4 py-4 text-center text-gray-500">
                                                <i class="fas fa-inbox text-2xl mb-2"></i>
                                                <p>No hay consumos registrados</p>
                                            </td>
                                </tr>
                                    @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @else
        <!-- Mensaje cuando no hay requerimientos -->
            <div id="no-requerimientos">
            <div class="text-center py-12">
                <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay requerimientos disponibles</h3>
                        <p class="text-gray-500">No se encontraron requerimientos guardados en el sistema</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Estado seleccionado en memoria
        document.addEventListener('DOMContentLoaded', function() {
            // Autoseleccionar folio si viene por query
            try {
                const params = new URLSearchParams(window.location.search);
                const folio = params.get('folio');
                if (folio) {
                    const row = document.querySelector(`#tbody-folios tr[data-folio="${folio}"]`);
                    if (row) {
                        selectFolio(folio, row);
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        // Si no está en la lista por filtros, cargar detalles directos
                        fetch(`/modulo-consultar-requerimiento/${folio}`)
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('folio-seleccionado').value = folio;
                                    document.getElementById('status-seleccionado').value = data.requerimiento.Status;
                                    actualizarBotonesPorEstado();
                                    const tbody = document.getElementById('detalles-tbody');
                                    tbody.innerHTML = '';
                                    (data.consumos || []).forEach(c => {
                                        const tr = document.createElement('tr');
                                        tr.className = 'hover:bg-gray-50';
                                        tr.innerHTML = `
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${folio}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.NoTelarId ?? '-'}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.CalibreTrama ? Number(c.CalibreTrama).toFixed(2) : '-'}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.NombreProducto ?? '-'}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.FibraTrama ?? '-'}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.CodColorTrama ?? '-'}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.ColorTrama ?? '-'}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">${c.Cantidad ? Number(c.Cantidad).toFixed(0) : '0'}</td>
                                        `;
                                        tbody.appendChild(tr);
                                    });
                                }
                            })
                            .catch(() => {});
                    }
                }
            } catch (e) {}
        });
        function selectFolio(folio, rowEl) {
            // Marcar fila activa
            document.querySelectorAll('#tbody-folios tr').forEach(tr => tr.classList.remove('bg-blue-100'));
            if (rowEl) rowEl.classList.add('bg-blue-100');

            // Guardar folio actual
            document.getElementById('folio-seleccionado').value = folio;

            // Cargar detalles por AJAX
            fetch(`/modulo-consultar-requerimiento/${folio}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;

                    // Actualizar estado seleccionado para control de botones
                    document.getElementById('status-seleccionado').value = data.requerimiento.Status;
                    actualizarBotonesPorEstado();

                    const tbody = document.getElementById('detalles-tbody');
                    tbody.innerHTML = '';
                    if (Array.isArray(data.consumos) && data.consumos.length) {
                        data.consumos.forEach(c => {
                            const tr = document.createElement('tr');
                            tr.className = 'hover:bg-gray-50';
                            tr.innerHTML = `
                                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${folio}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.NoTelarId ?? '-'}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.CalibreTrama ? Number(c.CalibreTrama).toFixed(2) : '-'}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.NombreProducto ?? '-'}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.FibraTrama ?? '-'}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.CodColorTrama ?? '-'}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.ColorTrama ?? '-'}</td>
                                <td class="px-4 py-2 text-sm text-gray-900">${c.Cantidad ? Number(c.Cantidad).toFixed(0) : '0'}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" class="px-4 py-4 text-center text-gray-500">
                                    <i class="fas fa-inbox text-2xl mb-2"></i>
                                    <p>No hay consumos registrados</p>
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(() => {});
        }

        function actualizarBotonesPorEstado() {
            const status = document.getElementById('status-seleccionado').value;
            const btnSolicitar = document.getElementById('btn-solicitar');
            const btnEditar = document.getElementById('btn-editar');
            const btnCancelar = document.getElementById('btn-cancelar');

            // Reset: mostrar todo y luego ocultar según reglas
            btnSolicitar.classList.remove('hidden');
            btnEditar.classList.remove('hidden');
            btnCancelar.classList.remove('hidden');

            // Reglas de permisos
            // En Proceso: puede todo
            if (status === 'En Proceso') return;

            // Solicitado: no puede solicitar ni editar; puede cancelar (según reglas anteriores permitimos cancelar)
            if (status === 'Solicitado') {
                btnSolicitar.classList.add('hidden');
                btnEditar.classList.add('hidden');
                return;
            }

            // Surtido: no puede solicitar, editar ni cancelar
            if (status === 'Surtido') {
                btnSolicitar.classList.add('hidden');
                btnEditar.classList.add('hidden');
                btnCancelar.classList.add('hidden');
                return;
            }

            // Cancelado: no puede hacer nada
            if (status === 'Cancelado') {
                btnSolicitar.classList.add('hidden');
                btnEditar.classList.add('hidden');
                btnCancelar.classList.add('hidden');
                return;
            }
        }

        function accionStatus(nuevoStatus) {
            const folio = document.getElementById('folio-seleccionado').value;
            if (!folio) return;
            cambiarStatus(folio, nuevoStatus);
        }

        function verResumenSeleccionado() {
            const folio = document.getElementById('folio-seleccionado').value;
            if (!folio) return;
            verResumen(folio);
        }
        // Cambiar status de un requerimiento
        function cambiarStatus(folio, nuevoStatus) {
            Swal.fire({
                title: 'Confirmación',
                text: `¿Está seguro de cambiar el status a "${nuevoStatus}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'No, cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;

                fetch(`/modulo-consultar-requerimiento/${folio}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status: nuevoStatus })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Actualizado', text: data.message || 'Status actualizado correctamente' })
                            .then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo actualizar el status' });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al actualizar el status' });
                });
            });
        }

        // Ver resumen de un folio
        function verResumen(folio) {
            // Abrir resumen en nueva ventana
            const url = `/modulo-consultar-requerimiento/${folio}/resumen`;
            window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        }

        function editarFolioSeleccionado() {
            const folio = document.getElementById('folio-seleccionado')?.value;
            if (!folio) return;
            // Redirigir a Nuevo Requerimiento con el folio en la query
            window.location.href = `{{ route('tejido.inventario.trama.nuevo.requerimiento') }}?folio=${encodeURIComponent(folio)}`;
        }
    </script>
@endsection

