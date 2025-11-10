@extends('layouts.app')

@section('page-title', 'Catálogo de Calendarios')

@section('navbar-right')
<x-buttons.catalog-actions route="calendarios" :showFilters="true" />
@endsection

@section('content')
    <div class="container overflow-y-auto h-[600px]">

        <!-- Tabla 1: ReqCalendarioTab -->
        <div class="mb-6">

            <div class="bg-white overflow-hidden">
                <div class="overflow-y-auto max-h-[250px]">
                    <table class="table table-bordered table-sm w-full">
                        <thead class="sticky top-0 bg-blue-500 text-white z-10">
                            <tr>
                                <th style="width: 20%;" class="px-4  text-center font-semibold">No Calendario</th>
                                <th style="width: 80%;" class="px-4  text-center font-semibold">Nombre</th>
                    </tr>
                </thead>
                        <tbody id="calendario-tab-body" class="bg-white text-black">
                            @foreach ($calendarioTab as $item)
                                <tr class="text-center hover:bg-blue-50 transition cursor-pointer border-b border-gray-200"
                                    onclick="selectRowTab(this, '{{ $item->CalendarioId }}')"
                                    ondblclick="deselectRowTab(this)"
                                    data-calendario="{{ $item->CalendarioId }}"
                                    data-calendario-id="{{ $item->Id }}">
                                    <td class="px-4  font-medium">{{ $item->CalendarioId }}</td>
                                    <td class="px-4 ">{{ $item->Nombre }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
                </div>
            </div>
        </div>

        <!-- Tabla 2: ReqCalendarioLine -->
        <div class="mb-6">


            <div class="bg-white  overflow-hidden">
                <div class="overflow-y-auto max-h-[300px]">
                    <table class="table table-bordered table-sm w-full">
                        <thead class="sticky top-0 bg-blue-500 text-white z-10">
                            <tr>
                                <th style="width: 15%;" class="px-4  text-center font-semibold">No Calendario</th>
                                <th style="width: 25%;" class="px-4  text-center font-semibold">Inicio (Fecha Hora)</th>
                                <th style="width: 25%;" class="px-4  text-center font-semibold">Fin (Fecha Hora)</th>
                                <th style="width: 15%;" class="px-4  text-center font-semibold">Horas</th>
                                <th style="width: 20%;" class="px-4  text-center font-semibold">Turno</th>
                            </tr>
                        </thead>
                        <tbody id="calendario-line-body" class="bg-white text-black">
                            @foreach ($calendarioLine as $item)
                                <tr class="text-center hover:bg-green-50 transition cursor-pointer border-b border-gray-200"
                                    onclick="selectRowLine(this, '{{ $item->CalendarioId }}-{{ $item->Turno }}')"
                                    ondblclick="deselectRowLine(this)"
                                    data-calendario-line="{{ $item->CalendarioId }}-{{ $item->Turno }}"
                                    data-linea-id="{{ $item->Id }}">
                                    <td class="px-4  font-medium">{{ $item->CalendarioId }}</td>
                                    <td class="px-4 ">{{ date('d/m/Y H:i', strtotime($item->FechaInicio)) }}</td>
                                    <td class="px-4 ">{{ date('d/m/Y H:i', strtotime($item->FechaFin)) }}</td>
                                    <td class="px-4  font-semibold">{{ $item->HorasTurno }}</td>
                                    <td class="px-4  font-semibold">{{ $item->Turno }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/catalog-core.js') }}"></script>
    <script>
        let selectedCalendarioTab = null;
        let selectedCalendarioLine = null;
        let activeFiltersTab = [];
        let activeFiltersLine = [];
        let originalDataTab = [];
        let originalDataLine = [];

        // Funciones de selección para Tabla 1 (ReqCalendarioTab)
        function selectRowTab(row, calendarioId) {
            // Remover selección anterior de AMBAS tablas
            document.querySelectorAll('#calendario-tab-body tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });
            document.querySelectorAll('#calendario-line-body tr').forEach(r => {
                r.classList.remove('bg-green-500', 'text-white');
                r.classList.add('hover:bg-green-50');
            });

            // Seleccionar fila actual
            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            // Limpiar selecciones de la otra tabla
            selectedCalendarioLine = null;

            // Guardar calendario seleccionado (usar ID de la base de datos)
            selectedCalendarioTab = row.getAttribute('data-calendario-id');

            // ✨ FILTRAR LA SEGUNDA TABLA - Mostrar solo líneas del calendario seleccionado
            filtrarLineasPorCalendario(calendarioId);

            // Habilitar botones de editar y eliminar
            enableButtons();
        }

        function deselectRowTab(row) {
            if (row.classList.contains('bg-blue-500')) {
                row.classList.remove('bg-blue-500', 'text-white');
                row.classList.add('hover:bg-blue-50');
                selectedCalendarioTab = null;

                // ✨ MOSTRAR TODAS LAS LÍNEAS NUEVAMENTE
                const tbody = document.getElementById('calendario-line-body');
                const rows = tbody.querySelectorAll('tr');
                rows.forEach(row => {
                    row.style.display = '';
                });

                disableButtons();
            }
        }

        // ✨ FUNCIÓN PARA FILTRAR LA SEGUNDA TABLA
        function filtrarLineasPorCalendario(calendarioId) {
            const tbody = document.getElementById('calendario-line-body');
            const rows = tbody.querySelectorAll('tr');
            let filasVisibles = 0;

            rows.forEach(row => {
                const dataCalendario = row.getAttribute('data-calendario-line').split('-')[0]; // Obtener solo el CalendarioId

                if (dataCalendario === calendarioId) {
                    row.style.display = ''; // Mostrar fila
                    filasVisibles++;
                } else {
                    row.style.display = 'none'; // Ocultar fila
                }
            });

            // Si no hay filas visibles, mostrar mensaje
            if (filasVisibles === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4 text-gray-500">
                            No hay líneas para este calendario
                        </td>
                    </tr>
                `;
            }
        }

        // Funciones de selección para Tabla 2 Calendario
        function selectRowLine(row, calendarioLineId) {
            // Remover selección anterior de AMBAS tablas
            document.querySelectorAll('#calendario-tab-body tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });
            document.querySelectorAll('#calendario-line-body tr').forEach(r => {
                r.classList.remove('bg-green-500', 'text-white');
                r.classList.add('hover:bg-green-50');
            });

            // Seleccionar fila actual
            row.classList.remove('hover:bg-green-50');
            row.classList.add('bg-green-500', 'text-white');

            // Limpiar selecciones de la otra tabla
            selectedCalendarioTab = null;

            // Guardar calendario line seleccionado (usar ID de la base de datos)
            selectedCalendarioLine = row.getAttribute('data-linea-id');

            // Habilitar botones de editar y eliminar
            enableButtons();
        }

        function deselectRowLine(row) {
            if (row.classList.contains('bg-green-500')) {
                row.classList.remove('bg-green-500', 'text-white');
                row.classList.add('hover:bg-green-50');
                selectedCalendarioLine = null;
                disableButtons();
            }
        }

        // Las funciones enableButtons() y disableButtons() están definidas en catalog-core.js

        // Función para agregar línea de calendario
        function agregarLineaCalendario() {
            Swal.fire({
                title: 'Agregar Nueva Línea de Calendario',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                            <input type="text" id="agregar-linea-calendario-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: CAL011">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Inicio (Fecha Hora)</label>
                            <input type="datetime-local" id="agregar-fecha-inicio" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fin (Fecha Hora)</label>
                            <input type="datetime-local" id="agregar-fecha-fin" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Horas</label>
                            <input type="number" step="0.1" id="agregar-horas" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="8.0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                            <select id="agregar-turno" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="1">Turno 1</option>
                                <option value="2">Turno 2</option>
                                <option value="3">Turno 3</option>
                            </select>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Agregar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                width: '500px',
                preConfirm: () => {
                    const calendarioId = document.getElementById('agregar-linea-calendario-id').value;
                    const fechaInicio = document.getElementById('agregar-fecha-inicio').value;
                    const fechaFin = document.getElementById('agregar-fecha-fin').value;
                    const horas = document.getElementById('agregar-horas').value;
                    const turno = document.getElementById('agregar-turno').value;

                    if (!calendarioId || !fechaInicio || !fechaFin || !horas || !turno) {
                        Swal.showValidationMessage('Por favor completa todos los campos');
                        return false;
                    }
                    return { calendarioId, fechaInicio, fechaFin, horas, turno };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Enviar datos al servidor
                    fetch('/planeacion/calendarios/lineas', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            CalendarioId: result.value.calendarioId,
                            FechaInicio: result.value.fechaInicio,
                            FechaFin: result.value.fechaFin,
                            HorasTurno: result.value.horas,
                            Turno: result.value.turno
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            location.reload();
                        } else {
                            showToast(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showToast('Error al crear línea de calendario', 'error');
                    });
                }
            });
        }

        // Funciones de modales SweetAlert para Calendarios
        function agregarCalendario() {
            // Si hay una línea seleccionada, agregar línea de calendario
            if (selectedCalendarioLine) {
                agregarLineaCalendario();
                return;
            }
            Swal.fire({
                title: 'Agregar Nuevo Calendario',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                            <input type="text" id="agregar-calendario-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: CAL011">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" id="agregar-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: Calendario Noviembre">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Agregar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                width: '500px',
                preConfirm: () => {
                    const calendarioId = document.getElementById('agregar-calendario-id').value;
                    const nombre = document.getElementById('agregar-nombre').value;

                    if (!calendarioId || !nombre) {
                        Swal.showValidationMessage('Por favor completa todos los campos');
                        return false;
                    }
                    return { calendarioId, nombre };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Enviar datos al servidor
                    fetch('/planeacion/calendarios', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            CalendarioId: result.value.calendarioId,
                            Nombre: result.value.nombre
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            location.reload();
                        } else {
                            showToast(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showToast('Error al crear calendario', 'error');
                    });
                }
            });
        }

        function editarCalendario() {
            console.log('editarCalendario llamado, selectedCalendarioTab:', selectedCalendarioTab, 'selectedCalendarioLine:', selectedCalendarioLine);

            if (!selectedCalendarioTab && !selectedCalendarioLine) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una fila para editar',
                    icon: 'warning'
                });
                return;
            }

            if (selectedCalendarioTab) {
                // Editar calendario de la tabla 1
                const selectedRow = document.querySelector(`tr[data-calendario-id="${selectedCalendarioTab}"]`);
                if (!selectedRow) return;

                const cells = selectedRow.querySelectorAll('td');
                const calendarioId = cells[0].textContent;
                const nombre = cells[1].textContent;

                Swal.fire({
                    title: 'Editar Calendario',
                    html: `
                        <div class="text-left space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                                <input type="text" id="editar-calendario-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${calendarioId}" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input type="text" id="editar-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${nombre}">
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    width: '500px',
                    preConfirm: () => {
                        const calendarioId = document.getElementById('editar-calendario-id').value;
                        const nombre = document.getElementById('editar-nombre').value;

                        if (!calendarioId || !nombre) {
                            Swal.showValidationMessage('Por favor completa todos los campos');
                            return false;
                        }
                        return { calendarioId, nombre };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Obtener el CalendarioId real de la fila seleccionada
                        const selectedRow = document.querySelector(`tr[data-calendario-id="${selectedCalendarioTab}"]`);
                        const calendarioId = selectedRow.querySelector('td:first-child').textContent;

                        // Enviar datos al servidor
                        fetch(`/planeacion/calendarios/${calendarioId}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                Nombre: result.value.nombre
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                location.reload();
                            } else {
                                showToast(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            showToast('Error al actualizar calendario', 'error');
                        });
                    }
                });
            } else if (selectedCalendarioLine) {
                // Editar línea de calendario de la tabla 2
                const selectedRow = document.querySelector(`tr[data-linea-id="${selectedCalendarioLine}"]`);
                if (!selectedRow) return;

                const cells = selectedRow.querySelectorAll('td');
                const calendarioId = cells[0].textContent;
                const fechaInicio = cells[1].textContent;
                const fechaFin = cells[2].textContent;
                const horas = cells[3].textContent;
                const turno = cells[4].textContent;

                // Convertir fechas al formato datetime-local
                function convertirFechaParaInput(fechaTexto) {
                    console.log('Convirtiendo fecha:', fechaTexto);

                    // Limpiar el texto de la fecha
                    let fechaLimpia = fechaTexto.trim();

                    // Si contiene "a. m." o "p. m.", convertir a formato 24h
                    if (fechaLimpia.includes('a. m.')) {
                        fechaLimpia = fechaLimpia.replace(' a. m.', '');
                    } else if (fechaLimpia.includes('p. m.')) {
                        fechaLimpia = fechaLimpia.replace(' p. m.', '');
                        // Convertir a formato 24h (sumar 12 horas si es necesario)
                        const partes = fechaLimpia.split(' ');
                        if (partes.length === 2) {
                            const [fecha, hora] = partes;
                            const [horaStr, minuto] = hora.split(':');
                            let hora24 = parseInt(horaStr);
                            if (hora24 !== 12) { // Si no es 12 PM, sumar 12
                                hora24 += 12;
                            }
                            fechaLimpia = `${fecha} ${hora24.toString().padStart(2, '0')}:${minuto}`;
                        }
                    }

                    // Intentar parsear la fecha
                    const fecha = new Date(fechaLimpia);
                    if (isNaN(fecha.getTime())) {
                        console.log('No se pudo parsear la fecha, usando fallback');
                        return "2024-01-01T06:00"; // Fallback si no se puede parsear
                    }

                    const resultado = fecha.toISOString().slice(0, 16);
                    console.log('Fecha convertida:', resultado);
                    return resultado;
                }

                const fechaInicioFormato = convertirFechaParaInput(fechaInicio);
                const fechaFinFormato = convertirFechaParaInput(fechaFin);

                Swal.fire({
                    title: 'Editar Línea de Calendario',
                    html: `
                        <div class="text-left space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                                <input type="text" id="editar-line-calendario-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${calendarioId}" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Inicio (Fecha Hora)</label>
                                <input type="datetime-local" id="editar-fecha-inicio" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${fechaInicioFormato}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fin (Fecha Hora)</label>
                                <input type="datetime-local" id="editar-fecha-fin" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${fechaFinFormato}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Horas</label>
                                <input type="number" step="0.1" id="editar-horas" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="${horas}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                                <select id="editar-turno" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="1" ${turno == '1' ? 'selected' : ''}>Turno 1</option>
                                    <option value="2" ${turno == '2' ? 'selected' : ''}>Turno 2</option>
                                    <option value="3" ${turno == '3' ? 'selected' : ''}>Turno 3</option>
                                </select>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    width: '500px',
                    preConfirm: () => {
                        const calendarioId = document.getElementById('editar-line-calendario-id').value;
                        const fechaInicio = document.getElementById('editar-fecha-inicio').value;
                        const fechaFin = document.getElementById('editar-fecha-fin').value;
                        const horas = document.getElementById('editar-horas').value;
                        const turno = document.getElementById('editar-turno').value;

                        if (!calendarioId || !fechaInicio || !fechaFin || !horas || !turno) {
                            Swal.showValidationMessage('Por favor completa todos los campos');
                            return false;
                        }
                        return { calendarioId, fechaInicio, fechaFin, horas, turno };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Obtener el ID de la línea seleccionada
                        const lineaId = selectedCalendarioLine;
                        console.log('Enviando actualización de línea con ID:', lineaId);
                        console.log('Datos a enviar:', {
                            FechaInicio: result.value.fechaInicio,
                            FechaFin: result.value.fechaFin,
                            HorasTurno: result.value.horas,
                            Turno: result.value.turno
                        });

                        // Enviar datos al servidor
                        fetch(`/planeacion/calendarios/lineas/${lineaId}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                FechaInicio: result.value.fechaInicio,
                                FechaFin: result.value.fechaFin,
                                HorasTurno: result.value.horas,
                                Turno: result.value.turno
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                location.reload();
                            } else {
                                showToast(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            showToast('Error al actualizar línea de calendario', 'error');
                        });
                    }
                });
            }
        }

        function eliminarCalendario() {
            console.log('eliminarCalendario llamado, selectedCalendarioTab:', selectedCalendarioTab, 'selectedCalendarioLine:', selectedCalendarioLine);

            if (!selectedCalendarioTab && !selectedCalendarioLine) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una fila para eliminar',
                    icon: 'warning'
                });
                return;
            }

            let title, html;

            if (selectedCalendarioTab) {
                const selectedRow = document.querySelector(`tr[data-calendario-id="${selectedCalendarioTab}"]`);
                if (!selectedRow) return;

                const cells = selectedRow.querySelectorAll('td');
                const calendarioId = cells[0].textContent;
                const nombre = cells[1].textContent;

                title = '¿Eliminar calendario?';
                html = `Vas a eliminar el calendario <b>${calendarioId}</b> - ${nombre}.`;
            } else if (selectedCalendarioLine) {
                const selectedRow = document.querySelector(`tr[data-linea-id="${selectedCalendarioLine}"]`);
                if (!selectedRow) return;

                const cells = selectedRow.querySelectorAll('td');
                const calendarioId = cells[0].textContent;
                const turno = cells[4].textContent;

                title = '¿Eliminar línea de calendario?';
                html = `Vas a eliminar la línea del calendario <b>${calendarioId}</b> turno <b>${turno}</b>.`;
            }

            Swal.fire({
                title: title,
                html: html,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (selectedCalendarioTab) {
                        // Obtener el CalendarioId real de la fila seleccionada
                        const selectedRow = document.querySelector(`tr[data-calendario-id="${selectedCalendarioTab}"]`);
                        const calendarioId = selectedRow.querySelector('td:first-child').textContent;

                        // Eliminar calendario
                        fetch(`/planeacion/calendarios/${calendarioId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                location.reload();
                            } else {
                                showToast(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            showToast('Error al eliminar calendario', 'error');
                        });
                    } else if (selectedCalendarioLine) {
                        // Eliminar línea de calendario
                        const selectedRow = document.querySelector(`tr[data-linea-id="${selectedCalendarioLine}"]`);
                        const lineaId = selectedCalendarioLine;
                        console.log('Enviando eliminación de línea con ID:', lineaId);

                        fetch(`/planeacion/calendarios/lineas/${lineaId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                location.reload();
                            } else {
                                showToast(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            showToast('Error al eliminar línea de calendario', 'error');
                        });
                    }
                    disableButtons();
                }
            });
        }

        function filtrarPorColumna() {
            // Generar lista de filtros activos
            let filtrosActivosHTML = '';
            const totalFiltros = activeFiltersTab.length + activeFiltersLine.length;

            if (totalFiltros > 0) {
                filtrosActivosHTML = `
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                        <div class="space-y-1">
                            ${[...activeFiltersTab, ...activeFiltersLine].map((filtro, index) => `
                                <div class="flex items-center justify-between bg-white p-2 rounded border">
                                    <span class="text-xs">${filtro.columna}: ${filtro.valor}</span>
                                    <button onclick="removeFilter(${index}, '${filtro.tabla}')" class="text-red-500 hover:text-red-700 text-xs">×</button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            Swal.fire({
                title: 'Filtrar por Columna',
                html: `
                    ${filtrosActivosHTML}
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tabla</label>
                            <select id="filtro-tabla" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateFilterColumns()">
                                <option value="tab">Calendarios (ReqCalendarioTab)</option>
                                <option value="line">Líneas de Calendario (ReqCalendarioLine)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                            <select id="filtro-columna" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="CalendarioId">No Calendario</option>
                                <option value="Nombre">Nombre</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor a buscar</label>
                            <input type="text" id="filtro-valor" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ingresa el valor a buscar">
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" id="btn-agregar-otro" class="flex-1 px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                                + Agregar Otro Filtro
                            </button>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Agregar Filtro',
                cancelButtonText: 'Cerrar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                width: '450px',
                preConfirm: () => {
                    const tabla = document.getElementById('filtro-tabla').value;
                    const columna = document.getElementById('filtro-columna').value;
                    const valor = document.getElementById('filtro-valor').value;

                    if (!valor) {
                        Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                        return false;
                    }

                    const filtros = tabla === 'tab' ? activeFiltersTab : activeFiltersLine;
                    const existeFiltro = filtros.some(f => f.columna === columna && f.valor === valor);
                    if (existeFiltro) {
                        Swal.showValidationMessage('Este filtro ya está activo');
                        return false;
                    }

                    return { tabla, columna, valor };
                },
                didOpen: () => {
                    updateFilterColumns();
                    document.getElementById('btn-agregar-otro').addEventListener('click', () => {
                        const tabla = document.getElementById('filtro-tabla').value;
                        const columna = document.getElementById('filtro-columna').value;
                        const valor = document.getElementById('filtro-valor').value;

                        if (!valor) {
                            Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                            return;
                        }

                        const filtros = tabla === 'tab' ? activeFiltersTab : activeFiltersLine;
                        const existeFiltro = filtros.some(f => f.columna === columna && f.valor === valor);
                        if (existeFiltro) {
                            Swal.showValidationMessage('Este filtro ya está activo');
                            return;
                        }

                        const filtro = { columna, valor, tabla };
                        if (tabla === 'tab') {
                            activeFiltersTab.push(filtro);
                        } else {
                            activeFiltersLine.push(filtro);
                        }

                        applyFilters();
                        showToast('Filtro agregado correctamente', 'success');
                        document.getElementById('filtro-valor').value = '';
                        updateFilterModal();
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const filtro = { ...result.value };
                    if (result.value.tabla === 'tab') {
                        activeFiltersTab.push(filtro);
                    } else {
                        activeFiltersLine.push(filtro);
                    }
                    applyFilters();
                    showToast('Filtro agregado correctamente', 'success');
                }
            });
        }

        function updateFilterColumns() {
            const tabla = document.getElementById('filtro-tabla').value;
            const columnaSelect = document.getElementById('filtro-columna');

            if (tabla === 'tab') {
                columnaSelect.innerHTML = `
                    <option value="CalendarioId">No Calendario</option>
                    <option value="Nombre">Nombre</option>
                `;
            } else {
                columnaSelect.innerHTML = `
                    <option value="CalendarioId">No Calendario</option>
                    <option value="FechaInicio">Inicio (Fecha Hora)</option>
                    <option value="FechaFin">Fin (Fecha Hora)</option>
                    <option value="HorasTurno">Horas</option>
                    <option value="Turno">Turno</option>
                `;
            }
        }

        function removeFilter(index, tabla) {
            if (tabla === 'tab') {
                activeFiltersTab.splice(index, 1);
            } else {
                activeFiltersLine.splice(index, 1);
            }
            applyFilters();
            showToast('Filtro eliminado', 'info');
            updateFilterModal();
        }

        function updateFilterModal() {
            // Regenerar modal con filtros actualizados
            filtrarPorColumna();
        }

        function applyFilters() {
            // Aplicar filtros a tabla 1
            if (!originalDataTab.length) {
                const rows = document.querySelectorAll('#calendario-tab-body tr');
                originalDataTab = Array.from(rows).map(row => ({
                    element: row,
                    CalendarioId: row.cells[0].textContent.trim(),
                    Nombre: row.cells[1].textContent.trim()
                }));
            }

            originalDataTab.forEach(item => {
                item.element.style.display = '';
                if (activeFiltersTab.length > 0) {
                    let matches = true;
                    activeFiltersTab.forEach(filter => {
                        const value = item[filter.columna].toLowerCase();
                        const filterValue = filter.valor.toLowerCase();
                        if (!value.includes(filterValue)) {
                            matches = false;
                        }
                    });
                    item.element.style.display = matches ? '' : 'none';
                }
            });
            // Aplicar filtros a tabla 2
            if (!originalDataLine.length) {
                const rows = document.querySelectorAll('#calendario-line-body tr');
                originalDataLine = Array.from(rows).map(row => ({
                    element: row,
                    CalendarioId: row.cells[0].textContent.trim(),
                    FechaInicio: row.cells[1].textContent.trim(),
                    FechaFin: row.cells[2].textContent.trim(),
                    HorasTurno: row.cells[3].textContent.trim(),
                    Turno: row.cells[4].textContent.trim()
                }));
            }

            originalDataLine.forEach(item => {
                item.element.style.display = '';
                if (activeFiltersLine.length > 0) {
                    let matches = true;
                    activeFiltersLine.forEach(filter => {
                        const value = item[filter.columna].toLowerCase();
                        const filterValue = filter.valor.toLowerCase();
                        if (!value.includes(filterValue)) {
                            matches = false;
                        }
                    });
                    item.element.style.display = matches ? '' : 'none';
                }
            });

            updateFilterCount();
        }

        function updateFilterCount() {
            const filterCount = document.getElementById('filter-count');
            if (filterCount) {
                const totalFiltros = activeFiltersTab.length + activeFiltersLine.length;
                if (totalFiltros > 0) {
                    filterCount.textContent = totalFiltros;
                    filterCount.classList.remove('hidden');
                } else {
                    filterCount.classList.add('hidden');
                }
            }
        }

        function restablecerFiltros() {
            activeFiltersTab = [];
            activeFiltersLine = [];

            if (originalDataTab.length > 0) {
                originalDataTab.forEach(item => {
                    item.element.style.display = '';
                });
            }

            if (originalDataLine.length > 0) {
                originalDataLine.forEach(item => {
                    item.element.style.display = '';
                });
            }

            updateFilterCount();
            showToast('Restablecido<br>Todos los filtros y configuraciones han sido eliminados', 'success');
        }

        // Funciones globales para navbar
        window.subirExcelCalendarios = function() {
            // Esta función es manejada por el componente action-buttons
        };

        window.subirExcelCalendariosMaestro = function() {
            Swal.fire({
                title: 'Subir Excel de Calendarios',
                html: `
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">Carga un archivo Excel con la información de calendarios.</p>
                        <p class="text-sm text-gray-600 font-semibold">Las columnas deben ser: <strong>No Calendario, Nombre</strong></p>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <label for="swal-file-excel-calendarios" class="cursor-pointer">
                                <span class="mt-2 block text-sm font-medium text-gray-900">Arrastra o haz click para seleccionar</span>
                                <input type="file" id="swal-file-excel-calendarios" name="file-excel" accept=".xlsx,.xls" class="sr-only">
                            </label>
                        </div>
                        <div id="swal-file-info-calendarios" class="hidden p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm font-medium text-gray-900" id="swal-file-name-calendarios"></p>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Procesar Excel',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
                width: '500px',
                preConfirm: () => {
                    const fileInput = document.getElementById('swal-file-excel-calendarios');
                    const file = fileInput.files[0];
                    if (!file) {
                        Swal.showValidationMessage('Por favor selecciona un archivo');
                        return false;
                    }
                    return file;
                },
                didOpen: () => {
                    const fileInput = document.getElementById('swal-file-excel-calendarios');
                    fileInput.addEventListener('change', function() {
                        if (this.files[0]) {
                            document.getElementById('swal-file-name-calendarios').textContent = this.files[0].name;
                            document.getElementById('swal-file-info-calendarios').classList.remove('hidden');
                        }
                    });
                }
            }).then(result => {
                if (result.isConfirmed && result.value) {
                    // Mostrar modal de procesamiento
                    Swal.fire({
                        title: 'Procesando...',
                        html: '<p class="text-gray-600">Se está procesando tu archivo de calendarios.</p><div class="mt-4"><div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-green-600 h-2 rounded-full animate-pulse" style="width: 100%"></div></div></div>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData();
                    formData.append('archivo_excel', result.value);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                    formData.append('tipo', 'calendarios');

                    fetch('/planeacion/calendarios/excel', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();

                        if (data.success) {
                            Swal.fire({
                                title: '¡Procesado Exitosamente!',
                                html: `
                                    <div class="text-left space-y-2">
                                        <p>Registros procesados: <strong>${data.data.registros_procesados}</strong></p>
                                    </div>
                                `,
                                icon: 'success',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#10b981'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error en el Procesamiento',
                                text: data.message || 'Hubo un problema al procesar el archivo',
                                icon: 'error',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        Swal.fire({
                            title: 'Error de Conexión',
                            text: 'Error al procesar: ' + error.message,
                            icon: 'error',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#ef4444'
                        });
                    });
                }
            });
        };

        window.subirExcelLineas = function() {
            Swal.fire({
                title: 'Subir Excel de Líneas de Calendarios',
                html: `
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">Carga un archivo Excel con las líneas de calendarios.</p>
                        <p class="text-sm text-gray-600 font-semibold">Las columnas deben ser: <strong>No Calendario, Inicio (Fecha Hora), Fin (Fecha Hora), Horas, Turno</strong></p>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <label for="swal-file-excel-lineas" class="cursor-pointer">
                                <span class="mt-2 block text-sm font-medium text-gray-900">Arrastra o haz click para seleccionar</span>
                                <input type="file" id="swal-file-excel-lineas" name="file-excel" accept=".xlsx,.xls" class="sr-only">
                            </label>
                        </div>
                        <div id="swal-file-info-lineas" class="hidden p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm font-medium text-gray-900" id="swal-file-name-lineas"></p>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Procesar Excel',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6',
                width: '500px',
                preConfirm: () => {
                    const fileInput = document.getElementById('swal-file-excel-lineas');
                    const file = fileInput.files[0];
                    if (!file) {
                        Swal.showValidationMessage('Por favor selecciona un archivo');
                        return false;
                    }
                    return file;
                },
                didOpen: () => {
                    const fileInput = document.getElementById('swal-file-excel-lineas');
                    fileInput.addEventListener('change', function() {
                        if (this.files[0]) {
                            document.getElementById('swal-file-name-lineas').textContent = this.files[0].name;
                            document.getElementById('swal-file-info-lineas').classList.remove('hidden');
                        }
                    });
                }
            }).then(result => {
                if (result.isConfirmed && result.value) {
                    // Mostrar modal de procesamiento
                    Swal.fire({
                        title: ' Procesando...',
                        html: '<p class="text-gray-600">Se está procesando tu archivo de líneas de calendarios.</p><div class="mt-4"><div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-blue-600 h-2 rounded-full animate-pulse" style="width: 100%"></div></div></div>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData();
                    formData.append('archivo_excel', result.value);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                    formData.append('tipo', 'lineas');

                    fetch('/planeacion/calendarios/excel', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();

                        if (data.success) {
                            Swal.fire({
                                title: '¡Procesado Exitosamente!',
                                html: `
                                    <div class="text-left space-y-2">
                                        <p>Registros procesados: <strong>${data.data.registros_procesados}</strong></p>
                                    </div>
                                `,
                                icon: 'success',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#3b82f6'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error en el Procesamiento',
                                text: data.message || 'Hubo un problema al procesar el archivo',
                                icon: 'error',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        Swal.fire({
                            title: 'Error de Conexión',
                            text: 'Error al procesar: ' + error.message,
                            icon: 'error',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#ef4444'
                        });
                    });
                }
            });
        };

        window.agregarCalendarios = function() {
            agregarCalendario();
        };

        window.agregarLineaCalendarios = function() {
            agregarLineaCalendario();
        };

        window.editarCalendarios = function() {
            editarCalendario();
        };

        window.eliminarCalendarios = function() {
            eliminarCalendario();
        };

        window.filtrarCalendarios = function() {
            filtrarPorColumna();
        };

        window.limpiarFiltrosCalendarios = function() {
            restablecerFiltros();
        };

        // Inicializar botones como deshabilitados
        document.addEventListener('DOMContentLoaded', function() {
            disableButtons();
        });
    </script>

    @include('components.ui.toast-notification')

@endsection
