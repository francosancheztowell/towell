@extends('layouts.app')

@section('title', 'Operadores de Mantenimiento')
@section('page-title')
Operadores de Mantenimiento
@endsection

@section('navbar-right')
    <div class="flex items-center gap-2 flex-wrap">
        <!-- Botón Filtro -->
        <button
            onclick="openFilterModal()"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors flex items-center gap-2"
            title="Filtrar">
            <i class="fa-solid fa-filter"></i>
            <span>Filtrar</span>
            @if($turnoFilter || $deptoFilter)
                <span class="bg-white text-blue-600 rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                    !
                </span>
            @endif
        </button>

        <div class="flex items-center gap-2">
            <x-navbar.button-create
                onclick="openCreateModal()"
                id="btn-nuevo"/>

            <!-- Botón Editar -->
            <x-navbar.button-edit
                onclick="handleTopEdit()"
                id="btn-top-edit"
                :disabled="true"/>

            <!-- Botón Eliminar -->
            <x-navbar.button-delete
                onclick="handleTopDelete()"
                id="btn-top-delete"
                :disabled="true"/>
        </div>
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    @if($errors->any())
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                html: '<ul class="text-left list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
                confirmButtonText: 'Aceptar'
            });
        </script>
    @endif
    @if(session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: '{{ session('success') }}',
                confirmButtonText: 'Aceptar',
                timer: 3000
            });
        </script>
    @endif
    @if(session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '{{ session('error') }}',
                confirmButtonText: 'Aceptar'
            });
        </script>
    @endif

    <!-- Barra de búsqueda -->
    <div class="mb-4">
        <form method="GET" action="{{ route('mantenimiento.operadores-mantenimiento.index') }}" class="flex gap-2">
            <input
                type="text"
                name="q"
                value="{{ $q }}"
                placeholder="Buscar por clave, nombre, departamento o teléfono..."
                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <button
                type="submit"
                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fa-solid fa-search mr-2"></i>Buscar
            </button>
            @if($q)
                <a
                    href="{{ route('mantenimiento.operadores-mantenimiento.index') }}"
                    class="px-6 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                    <i class="fa-solid fa-times mr-2"></i>Limpiar
                </a>
            @endif
        </form>
    </div>

    <!-- Tabla de Operadores -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-blue-600 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Clave</th>
                        <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                        <th class="px-4 py-3 text-left font-semibold">Turno</th>
                        <th class="px-4 py-3 text-left font-semibold">Departamento</th>
                        <th class="px-4 py-3 text-left font-semibold">Teléfono</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                            data-key="{{ $item->Id }}"
                            data-cve-empl="{{ $item->CveEmpl }}"
                            data-nom-empl="{{ $item->NomEmpl }}"
                            data-turno="{{ $item->Turno }}"
                            data-depto="{{ $item->Depto }}"
                            data-telefono="{{ $item->Telefono ?? '' }}"
                            onclick="selectRow(this)"
                            aria-selected="false">
                            <td class="px-4 py-3">{{ $item->CveEmpl }}</td>
                            <td class="px-4 py-3 font-medium">{{ $item->NomEmpl }}</td>
                            <td class="px-4 py-3">{{ $item->Turno }}</td>
                            <td class="px-4 py-3">{{ $item->Depto }}</td>
                            <td class="px-4 py-3">{{ $item->Telefono ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                <i class="fa-solid fa-inbox text-4xl mb-2 block"></i>
                                No se encontraron operadores
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($items->count() > 0)
        <div class="mt-4 text-sm text-gray-600">
            Mostrando <strong>{{ $items->count() }}</strong> operador(es)
        </div>
    @endif
</div>

<script>
    const storeUrl = '{{ route('mantenimiento.operadores-mantenimiento.store') }}';
    const updateUrl = '{{ route('mantenimiento.operadores-mantenimiento.update', 'PLACEHOLDER') }}';
    const deleteUrl = '{{ route('mantenimiento.operadores-mantenimiento.destroy', 'PLACEHOLDER') }}';
    
    const turnos = @json($turnos);
    const departamentos = @json($departamentos);

    let selectedRow = null;
    let selectedKey = null;

    // Actualizar estado de botones superiores
    function updateTopButtonsState() {
        const editBtn = document.getElementById('btn-top-edit');
        const deleteBtn = document.getElementById('btn-top-delete');
        
        if (editBtn && deleteBtn) {
            const disabled = !selectedKey;
            editBtn.disabled = disabled;
            deleteBtn.disabled = disabled;
        }
    }

    // Limpiar selección
    function clearSelection() {
        if (selectedRow) {
            selectedRow.setAttribute('aria-selected', 'false');
        }
        selectedRow = null;
        selectedKey = null;
        updateTopButtonsState();
    }

    // Seleccionar fila
    function selectRow(row) {
        if (selectedRow === row) {
            clearSelection();
            return;
        }

        if (selectedRow) {
            selectedRow.setAttribute('aria-selected', 'false');
        }

        selectedRow = row;
        selectedKey = row.dataset.key || null;
        row.setAttribute('aria-selected', 'true');
        updateTopButtonsState();
    }

    // Abrir modal de creación con SweetAlert
    function openCreateModal() {
        let turnoOptions = '<option value="">Seleccione un turno</option>';
        for (let i = 1; i <= 3; i++) {
            turnoOptions += `<option value="${i}">Turno ${i}</option>`;
        }

        let deptoOptions = '<option value="">Seleccione un departamento</option>';
        departamentos.forEach(depto => {
            deptoOptions += `<option value="${depto}">${depto}</option>`;
        });

        Swal.fire({
            title: '<i class="fa-solid fa-plus-circle text-green-600"></i> Nuevo Operador',
            html: `
                <form id="createForm" class="text-left space-y-4 mt-4" style="min-width: 500px;">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Clave de Empleado <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="swal-cve-empl" name="CveEmpl" required maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="Ej: 0386">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Nombre del Empleado <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="swal-nom-empl" name="NomEmpl" required maxlength="255"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="Ej: Victor Hugo Tovar Elioza">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Turno <span class="text-red-500">*</span>
                            </label>
                            <select id="swal-turno" name="Turno" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                ${turnoOptions}
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Departamento <span class="text-red-500">*</span>
                            </label>
                            <select id="swal-depto" name="Depto" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                ${deptoOptions}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Teléfono
                        </label>
                        <input type="text" id="swal-telefono" name="Telefono" maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="Ej: 1234567890">
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> Guardar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            width: '600px',
            customClass: {
                confirmButton: 'px-4 py-2 rounded-lg font-medium',
                cancelButton: 'px-4 py-2 rounded-lg font-medium',
                popup: 'text-left'
            },
            didOpen: () => {
                document.getElementById('swal-cve-empl').focus();
            },
            preConfirm: () => {
                const cveEmpl = document.getElementById('swal-cve-empl').value.trim();
                const nomEmpl = document.getElementById('swal-nom-empl').value.trim();
                const turno = document.getElementById('swal-turno').value;
                const depto = document.getElementById('swal-depto').value;
                const telefono = document.getElementById('swal-telefono').value.trim();

                if (!cveEmpl || !nomEmpl || !turno || !depto) {
                    Swal.showValidationMessage('Por favor complete todos los campos requeridos');
                    return false;
                }

                return {
                    CveEmpl: cveEmpl,
                    NomEmpl: nomEmpl,
                    Turno: parseInt(turno),
                    Depto: depto,
                    Telefono: telefono || null
                };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                submitCreateForm(result.value);
            }
        });
    }

    // Enviar formulario de creación
    function submitCreateForm(data) {
        const formData = new FormData();
        Object.keys(data).forEach(key => {
            if (data[key] !== null) {
                formData.append(key, data[key]);
            }
        });

        fetch(storeUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: result.message || 'Operador creado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                throw new Error(result.message || 'Error al crear el operador');
            }
        })
        .catch(error => {
            let errorMessage = 'Error al crear el operador';
            if (error.message) {
                errorMessage = error.message;
            } else if (error.errors) {
                errorMessage = Object.values(error.errors).flat().join(', ');
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage,
                confirmButtonText: 'Aceptar'
            });
        });
    }

    // Editar desde botón superior
    function handleTopEdit() {
        if (!selectedRow || !selectedKey) return;
        const cveEmpl = selectedRow.dataset.cveEmpl || '';
        const nomEmpl = selectedRow.dataset.nomEmpl || '';
        const turno = selectedRow.dataset.turno || '';
        const depto = selectedRow.dataset.depto || '';
        const telefono = selectedRow.dataset.telefono || '';
        openEditModal(selectedKey, cveEmpl, nomEmpl, turno, depto, telefono);
    }

    // Abrir modal de edición con SweetAlert
    function openEditModal(key, cveEmpl, nomEmpl, turno, depto, telefono) {
        let turnoOptions = '<option value="">Seleccione un turno</option>';
        for (let i = 1; i <= 3; i++) {
            const selected = turno == i ? 'selected' : '';
            turnoOptions += `<option value="${i}" ${selected}>Turno ${i}</option>`;
        }

        let deptoOptions = '<option value="">Seleccione un departamento</option>';
        departamentos.forEach(dept => {
            const selected = depto === dept ? 'selected' : '';
            deptoOptions += `<option value="${dept}" ${selected}>${dept}</option>`;
        });

        Swal.fire({
            title: '<i class="fa-solid fa-edit text-yellow-600"></i> Editar Operador',
            html: `
                <form id="editForm" class="text-left space-y-4 mt-4" style="min-width: 500px;">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Clave de Empleado <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="swal-edit-cve-empl" name="CveEmpl" required maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500"
                            value="${(cveEmpl || '').replace(/"/g, '&quot;')}">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Nombre del Empleado <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="swal-edit-nom-empl" name="NomEmpl" required maxlength="255"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500"
                            value="${(nomEmpl || '').replace(/"/g, '&quot;')}">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Turno <span class="text-red-500">*</span>
                            </label>
                            <select id="swal-edit-turno" name="Turno" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                ${turnoOptions}
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Departamento <span class="text-red-500">*</span>
                            </label>
                            <select id="swal-edit-depto" name="Depto" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                ${deptoOptions}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Teléfono
                        </label>
                        <input type="text" id="swal-edit-telefono" name="Telefono" maxlength="50"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500"
                            value="${(telefono || '').replace(/"/g, '&quot;')}">
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-save mr-1"></i> Actualizar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            confirmButtonColor: '#ca8a04',
            cancelButtonColor: '#6b7280',
            width: '600px',
            customClass: {
                confirmButton: 'px-4 py-2 rounded-lg font-medium',
                cancelButton: 'px-4 py-2 rounded-lg font-medium',
                popup: 'text-left'
            },
            didOpen: () => {
                document.getElementById('swal-edit-cve-empl').focus();
            },
            preConfirm: () => {
                const cveEmpl = document.getElementById('swal-edit-cve-empl').value.trim();
                const nomEmpl = document.getElementById('swal-edit-nom-empl').value.trim();
                const turno = document.getElementById('swal-edit-turno').value;
                const depto = document.getElementById('swal-edit-depto').value;
                const telefono = document.getElementById('swal-edit-telefono').value.trim();

                if (!cveEmpl || !nomEmpl || !turno || !depto) {
                    Swal.showValidationMessage('Por favor complete todos los campos requeridos');
                    return false;
                }

                return {
                    CveEmpl: cveEmpl,
                    NomEmpl: nomEmpl,
                    Turno: parseInt(turno),
                    Depto: depto,
                    Telefono: telefono || null
                };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                submitUpdateForm(key, result.value);
            }
        });
    }

    // Enviar formulario de actualización
    function submitUpdateForm(key, data) {
        const url = updateUrl.replace('PLACEHOLDER', encodeURIComponent(key));
        const formData = new FormData();
        formData.append('_method', 'PUT');
        Object.keys(data).forEach(key => {
            if (data[key] !== null) {
                formData.append(key, data[key]);
            }
        });

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: result.message || 'Operador actualizado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                throw new Error(result.message || 'Error al actualizar el operador');
            }
        })
        .catch(error => {
            let errorMessage = 'Error al actualizar el operador';
            if (error.message) {
                errorMessage = error.message;
            } else if (error.errors) {
                errorMessage = Object.values(error.errors).flat().join(', ');
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage,
                confirmButtonText: 'Aceptar'
            });
        });
    }

    // Eliminar desde botón superior
    function handleTopDelete() {
        if (!selectedKey) return;
        deleteOperador(selectedKey);
    }

    // Eliminar operador
    function deleteOperador(key) {
        const nomEmpl = selectedRow ? selectedRow.dataset.nomEmpl : '';
        
        Swal.fire({
            title: '¿Estás seguro?',
            html: `¿Deseas eliminar al operador <strong>${nomEmpl}</strong>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-trash mr-1"></i> Sí, eliminar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            customClass: {
                confirmButton: 'px-4 py-2 rounded-lg font-medium',
                cancelButton: 'px-4 py-2 rounded-lg font-medium'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const url = deleteUrl.replace('PLACEHOLDER', encodeURIComponent(key));
                const formData = new FormData();
                formData.append('_method', 'DELETE');
                
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => Promise.reject(err));
                    }
                    return response.json();
                })
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: result.message || 'Operador eliminado correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        throw new Error(result.message || 'Error al eliminar el operador');
                    }
                })
                .catch(error => {
                    let errorMessage = 'Error al eliminar el operador';
                    if (error.message) {
                        errorMessage = error.message;
                    } else if (error.errors) {
                        errorMessage = Object.values(error.errors).flat().join(', ');
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMessage,
                        confirmButtonText: 'Aceptar'
                    });
                });
            }
        });
    }

    // Abrir modal de filtros
    window.openFilterModal = function() {
        const turnoActual = '{{ $turnoFilter ?? '' }}';
        const deptoActual = '{{ $deptoFilter ?? '' }}';

        let turnoOptions = '<option value="">Todos</option>';
        for (let i = 1; i <= 3; i++) {
            const selected = turnoActual == i ? 'selected' : '';
            turnoOptions += `<option value="${i}" ${selected}>Turno ${i}</option>`;
        }

        let deptoOptions = '<option value="">Todos</option>';
        departamentos.forEach(dept => {
            const selected = deptoActual === dept ? 'selected' : '';
            deptoOptions += `<option value="${dept}" ${selected}>${dept}</option>`;
        });

        Swal.fire({
            title: '<i class="fa-solid fa-filter text-blue-600"></i> Filtrar Operadores',
            html: `
                <div class="text-left space-y-4 mt-4" style="min-width: 300px;">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Turno</label>
                        <select id="swal-filter-turno" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            ${turnoOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Departamento</label>
                        <select id="swal-filter-depto" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            ${deptoOptions}
                        </select>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-filter mr-1"></i> Filtrar',
            cancelButtonText: '<i class="fa-solid fa-times mr-1"></i> Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            width: '400px',
            customClass: {
                confirmButton: 'px-4 py-2 rounded-lg font-medium',
                cancelButton: 'px-4 py-2 rounded-lg font-medium',
                popup: 'text-left'
            },
            preConfirm: () => {
                const turno = document.getElementById('swal-filter-turno').value;
                const depto = document.getElementById('swal-filter-depto').value;
                
                const params = new URLSearchParams();
                if (turno) params.append('turno', turno);
                if (depto) params.append('depto', depto);
                
                const query = '{{ $q }}';
                if (query) params.append('q', query);
                
                return params.toString();
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const url = '{{ route('mantenimiento.operadores-mantenimiento.index') }}' + '?' + result.value;
                window.location.href = url;
            }
        });
    };

    // Inicializar estado de botones
    updateTopButtonsState();
</script>
@endsection
