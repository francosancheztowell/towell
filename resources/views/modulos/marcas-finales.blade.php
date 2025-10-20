@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
        <div class="bg-blue-500 px-6 py-4 border-t-4 border-orange-400">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-white">Marcas Finales</h1>
                <div class="flex space-x-2">
                    <button id="btn-nuevo" onclick="nuevaMarca()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nuevo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Marcas -->
    @if($marcas && $marcas->count() > 0)
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Folio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Turno</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Usuario</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($marcas as $marca)
                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="seleccionarMarca('{{ $marca->Folio }}', this)">
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 border-r border-gray-200">{{ $marca->Folio }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">{{ \Carbon\Carbon::parse($marca->Date)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">Turno {{ $marca->Turno }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">{{ $marca->numero_empleado ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm border-r border-gray-200">
                                @php
                                    $statusColors = [
                                        'En Proceso' => 'bg-blue-100 text-blue-800',
                                        'Finalizado' => 'bg-green-100 text-green-800',
                                        'Cancelado' => 'bg-red-100 text-red-800',
                                    ];
                                    $statusClass = $statusColors[$marca->Status] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">{{ $marca->Status }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="flex space-x-2">
                                    <button onclick="editarMarca('{{ $marca->Folio }}')" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    @if($marca->Status === 'En Proceso')
                                    <button onclick="finalizarMarca('{{ $marca->Folio }}')" class="text-green-600 hover:text-green-800">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay marcas registradas</h3>
            <p class="text-gray-500">Haz clic en "Nuevo" para crear la primera marca</p>
        </div>
    @endif

    <!-- Detalles de la marca seleccionada -->
    <div id="detalles-marca" class="bg-white rounded-lg shadow-md overflow-hidden" style="display: none;">
        <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Detalles de la Marca</h2>
                <div class="flex space-x-2">
                    <button id="btn-editar" onclick="editarMarcaSeleccionada()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-edit mr-2"></i>Editar
                    </button>
                    <button id="btn-finalizar" onclick="finalizarMarcaSeleccionada()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-check mr-2"></i>Finalizar
                    </button>
                </div>
            </div>
        </div>

        <!-- Información del folio -->
        <div class="p-6 border-b border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="text-center">
                    <div class="text-sm text-gray-500 uppercase tracking-wide">Folio</div>
                    <div class="text-lg font-bold text-gray-900" id="detalle-folio">-</div>
                </div>
                <div class="text-center">
                    <div class="text-sm text-gray-500 uppercase tracking-wide">Fecha</div>
                    <div class="text-lg font-semibold text-gray-900" id="detalle-fecha">-</div>
                </div>
                <div class="text-center">
                    <div class="text-sm text-gray-500 uppercase tracking-wide">Turno</div>
                    <div class="text-lg font-semibold text-gray-900" id="detalle-turno">-</div>
                </div>
                <div class="text-center">
                    <div class="text-sm text-gray-500 uppercase tracking-wide">Usuario</div>
                    <div class="text-lg font-semibold text-gray-900" id="detalle-usuario">-</div>
                </div>
                <div class="text-center">
                    <div class="text-sm text-gray-500 uppercase tracking-wide">Status</div>
                    <div class="text-lg font-semibold text-gray-900" id="detalle-status">-</div>
                </div>
            </div>
        </div>

        <!-- Tabla de telares -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">1°</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Telar</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">% Efi</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Marcas #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Trama #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Pie #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Rizo #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Otros #</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="tabla-telares">
                    <!-- Se llena dinámicamente -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para nueva marca -->
<div id="modal-nueva-marca" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Nueva Marca</h3>
            </div>
            <form id="form-nueva-marca">
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Número de Empleado</label>
                        <input type="text" name="numero_empleado" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre del Empleado</label>
                        <input type="text" name="nombre_empleado" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3">
                    <button type="button" onclick="cerrarModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let marcaSeleccionada = null;

    // Nueva marca
    function nuevaMarca() {
        document.getElementById('modal-nueva-marca').classList.remove('hidden');
    }

    function cerrarModal() {
        document.getElementById('modal-nueva-marca').classList.add('hidden');
        document.getElementById('form-nueva-marca').reset();
    }

    // Formulario nueva marca
    document.getElementById('form-nueva-marca').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('/modulo-marcas-finales', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                numero_empleado: formData.get('numero_empleado'),
                nombre_empleado: formData.get('nombre_empleado')
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: data.message
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al crear marca'
            });
        });
    });

    // Seleccionar marca
    function seleccionarMarca(folio, row) {
        // Remover selección anterior
        document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('bg-blue-100'));
        row.classList.add('bg-blue-100');

        marcaSeleccionada = folio;

        // Cargar detalles
        fetch(`/modulo-marcas-finales/${folio}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarDetalles(data.marca);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    // Mostrar detalles
    function mostrarDetalles(marca) {
        document.getElementById('detalle-folio').textContent = marca.Folio;
        document.getElementById('detalle-fecha').textContent = new Date(marca.Date).toLocaleDateString();
        document.getElementById('detalle-turno').textContent = `Turno ${marca.Turno}`;
        document.getElementById('detalle-usuario').textContent = marca.numero_empleado || '-';
        document.getElementById('detalle-status').textContent = marca.Status;

        // Mostrar tabla de telares
        const tbody = document.getElementById('tabla-telares');
        tbody.innerHTML = '';

        marca.marcas_line.forEach((linea, index) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            tr.innerHTML = `
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${index + 1}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${linea.NoTelarId}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${linea.Eficiencia || 0}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${linea.Marcas || 0}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${linea.Trama || 0}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${linea.Pie || 0}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">${linea.Rizo || 0}</td>
                <td class="px-4 py-3 text-sm text-gray-900">${linea.Otros || 0}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('detalles-marca').style.display = 'block';

        // Mostrar/ocultar botones según status
        const btnFinalizar = document.getElementById('btn-finalizar');
        if (marca.Status === 'En Proceso') {
            btnFinalizar.style.display = 'inline-block';
        } else {
            btnFinalizar.style.display = 'none';
        }
    }

    // Editar marca
    function editarMarca(folio) {
        // Implementar edición
        console.log('Editar marca:', folio);
    }

    function editarMarcaSeleccionada() {
        if (marcaSeleccionada) {
            editarMarca(marcaSeleccionada);
        }
    }

    // Finalizar marca
    function finalizarMarca(folio) {
        Swal.fire({
            title: 'Confirmación',
            text: '¿Está seguro de finalizar esta marca?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/modulo-marcas-finales/${folio}/finalizar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: data.message
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al finalizar marca'
                    });
                });
            }
        });
    }

    function finalizarMarcaSeleccionada() {
        if (marcaSeleccionada) {
            finalizarMarca(marcaSeleccionada);
        }
    }
</script>
@endsection
