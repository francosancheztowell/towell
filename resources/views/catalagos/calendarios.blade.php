@extends('layouts.app')

@section('page-title', 'Catálogo de Calendarios')

@section('navbar-right')
    <x-buttons.catalog-actions route="calendarios" :showFilters="true" />
@endsection

@section('content')
    <div class="w-full space-y-6">

        <!-- Tabla 1: ReqCalendarioTab -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="relative max-h-[300px] overflow-y-auto">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-blue-500 text-white">
                        <tr>
                            <th class="px-4 py-2 text-center font-semibold">No Calendario</th>
                            <th class="px-4 py-2 text-center font-semibold">Nombre</th>
                        </tr>
                    </thead>
                    <tbody id="calendario-tab-body" class="bg-white text-black">
                        @foreach ($calendarioTab as $item)
                            <tr class="text-center hover:bg-blue-50 transition cursor-pointer border-b border-gray-200"
                                onclick="selectRowTab(this, '{{ $item->CalendarioId }}')"
                                ondblclick="deselectRowTab(this)"
                                data-calendario="{{ $item->CalendarioId }}"
                                data-calendario-id="{{ $item->Id }}">
                                <td class="px-4 py-1 font-medium">{{ $item->CalendarioId }}</td>
                                <td class="px-4 py-1">{{ $item->Nombre }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla 2: ReqCalendarioLine -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="relative h-[600px] overflow-y-auto" id="contenedor-scroll-line">
                <table class="min-w-full text-sm" id="tabla-calendario-line">
                    <thead class="sticky top-0 z-10 bg-blue-500 text-white">
                        <tr>
                            <th class="px-4 py-2 text-center font-semibold w-[15%]">No Calendario</th>
                            <th class="px-4 py-2 text-center font-semibold w-[25%]">Inicio (Fecha Hora)</th>
                            <th class="px-4 py-2 text-center font-semibold w-[25%]">Fin (Fecha Hora)</th>
                            <th class="px-4 py-2 text-center font-semibold w-[15%]">Horas</th>
                            <th class="px-4 py-2 text-center font-semibold w-[20%]">Turno</th>
                        </tr>
                    </thead>
                    <tbody id="calendario-line-body" class="bg-white text-black">
                        @foreach ($calendarioLine as $item)
                            <tr class="text-center hover:bg-green-50 transition cursor-pointer border-b border-gray-200"
                                onclick="selectRowLine(this, '{{ $item->CalendarioId }}-{{ $item->Turno }}')"
                                ondblclick="deselectRowLine(this)"
                                data-calendario-line="{{ $item->CalendarioId }}-{{ $item->Turno }}"
                                data-linea-id="{{ $item->Id }}">
                                <td class="px-4 py-1 font-medium">{{ $item->CalendarioId }}</td>
                                <td class="px-4 py-1">{{ date('d/m/Y H:i', strtotime($item->FechaInicio)) }}</td>
                                <td class="px-4 py-1">{{ date('d/m/Y H:i', strtotime($item->FechaFin)) }}</td>
                                <td class="px-4 py-1 font-semibold">{{ $item->HorasTurno }}</td>
                                <td class="px-4 py-1 font-semibold">{{ $item->Turno }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/catalog-core.js') }}"></script>

    <script>
        // ------------------ Estado global simple ------------------
        let selectedCalendarioTab = null;   // PK ReqCalendarioTab
        let selectedCalendarioLine = null;  // PK ReqCalendarioLine

        let activeFiltersTab = [];
        let activeFiltersLine = [];

        let originalDataTab = [];
        let originalDataLine = [];

        const TAB_BODY_SELECTOR = '#calendario-tab-body';
        const LINE_BODY_SELECTOR = '#calendario-line-body';

        const getCsrfToken = () =>
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        // Limpia estilos de selección en ambas tablas
        function resetRowStyles() {
            document.querySelectorAll(`${TAB_BODY_SELECTOR} tr[data-calendario-id]`).forEach(row => {
                row.classList.remove('bg-blue-500', 'text-white');
                row.classList.add('hover:bg-blue-50');
            });

            document.querySelectorAll(`${LINE_BODY_SELECTOR} tr[data-linea-id]`).forEach(row => {
                row.classList.remove('bg-green-500', 'text-white');
                row.classList.add('hover:bg-green-50');
            });
        }

        // =========================================================
        //   SELECCIÓN TABLA 1 - ReqCalendarioTab
        // =========================================================
        function selectRowTab(row, calendarioId) {
            resetRowStyles();

            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            selectedCalendarioTab = row.dataset.calendarioId;
            selectedCalendarioLine = null;

            filtrarLineasPorCalendario(calendarioId);

            if (typeof enableButtons === 'function') {
                enableButtons();
            }
        }

        function deselectRowTab(row) {
            if (!row.classList.contains('bg-blue-500')) return;

            row.classList.remove('bg-blue-500', 'text-white');
            row.classList.add('hover:bg-blue-50');
            selectedCalendarioTab = null;

            // Mostrar todas las líneas
            document
                .querySelectorAll(`${LINE_BODY_SELECTOR} tr[data-linea-id]`)
                .forEach(tr => tr.style.display = '');

            const emptyRow = document.getElementById('calendario-line-empty');
            if (emptyRow) emptyRow.style.display = 'none';

            if (typeof disableButtons === 'function') {
                disableButtons();
            }
        }

        // =========================================================
        //   SELECCIÓN TABLA 2 - ReqCalendarioLine
        // =========================================================
        function selectRowLine(row, calendarioLineKey) {
            resetRowStyles();

            row.classList.remove('hover:bg-green-50');
            row.classList.add('bg-green-500', 'text-white');

            selectedCalendarioTab = null;
            selectedCalendarioLine = row.dataset.lineaId;

            if (typeof enableButtons === 'function') {
                enableButtons();
            }
        }

        function deselectRowLine(row) {
            if (!row.classList.contains('bg-green-500')) return;

            row.classList.remove('bg-green-500', 'text-white');
            row.classList.add('hover:bg-green-50');
            selectedCalendarioLine = null;

            if (typeof disableButtons === 'function') {
                disableButtons();
            }
        }

        // =========================================================
        //   FILTRAR LÍNEAS POR CALENDARIO (click en tabla 1)
        // =========================================================
        function filtrarLineasPorCalendario(calendarioId) {
            const tbody = document.querySelector(LINE_BODY_SELECTOR);
            const rows = tbody.querySelectorAll('tr[data-linea-id]');
            let visibles = 0;

            rows.forEach(row => {
                const dataCalendario = (row.dataset.calendarioLine || '').split('-')[0];
                const mostrar = dataCalendario === calendarioId;

                row.style.display = mostrar ? '' : 'none';
                if (mostrar) visibles++;
            });

            let emptyRow = document.getElementById('calendario-line-empty');

            if (visibles === 0) {
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.id = 'calendario-line-empty';
                    emptyRow.innerHTML = `
                        <td colspan="5" class="text-center py-4 text-gray-500">
                            No hay líneas para este calendario
                        </td>
                    `;
                    tbody.appendChild(emptyRow);
                }
                emptyRow.style.display = '';
            } else if (emptyRow) {
                emptyRow.style.display = 'none';
            }
        }

        // =========================================================
        //   CRUD LÍNEAS DE CALENDARIO
        // =========================================================
        function agregarLineaCalendario() {
            Swal.fire({
                title: 'Agregar Nueva Línea de Calendario',
                html: `
                    <div class="text-left space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                            <input type="text" id="agregar-linea-calendario-id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: CAL011">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Inicio (Fecha Hora)</label>
                            <input type="datetime-local" id="agregar-fecha-inicio"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fin (Fecha Hora)</label>
                            <input type="datetime-local" id="agregar-fecha-fin"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Horas</label>
                            <input type="number" step="0.1" id="agregar-horas"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="8.0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                            <select id="agregar-turno"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                    const calendarioId = document.getElementById('agregar-linea-calendario-id').value.trim();
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
                if (!result.isConfirmed) return;

                fetch('/planeacion/calendarios/lineas', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({
                        CalendarioId: result.value.calendarioId,
                        FechaInicio: result.value.fechaInicio,
                        FechaFin: result.value.fechaFin,
                        HorasTurno: result.value.horas,
                        Turno: result.value.turno
                    })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            location.reload();
                        } else {
                            showToast(data.message || 'Error al crear línea de calendario', 'error');
                        }
                    })
                    .catch(() => showToast('Error al crear línea de calendario', 'error'));
            });
        }

        // =========================================================
        //   CRUD CALENDARIOS (TABLA 1)
        // =========================================================
        function agregarCalendario() {
            // Si hay línea seleccionada, se entiende que quiere agregar línea
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
                            <input type="text" id="agregar-calendario-id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: CAL011">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" id="agregar-nombre"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: Calendario Noviembre">
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
                    const calendarioId = document.getElementById('agregar-calendario-id').value.trim();
                    const nombre = document.getElementById('agregar-nombre').value.trim();

                    if (!calendarioId || !nombre) {
                        Swal.showValidationMessage('Por favor completa todos los campos');
                        return false;
                    }

                    return { calendarioId, nombre };
                }
            }).then((result) => {
                if (!result.isConfirmed) return;

                fetch('/planeacion/calendarios', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({
                        CalendarioId: result.value.calendarioId,
                        Nombre: result.value.nombre
                    })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            location.reload();
                        } else {
                            showToast(data.message || 'Error al crear calendario', 'error');
                        }
                    })
                    .catch(() => showToast('Error al crear calendario', 'error'));
            });
        }

        function editarCalendario() {
            if (!selectedCalendarioTab && !selectedCalendarioLine) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una fila para editar',
                    icon: 'warning'
                });
                return;
            }

            // Editar maestro (tabla 1)
            if (selectedCalendarioTab) {
                const selectedRow = document.querySelector(
                    `${TAB_BODY_SELECTOR} tr[data-calendario-id="${selectedCalendarioTab}"]`
                );
                if (!selectedRow) return;

                const calendarioId = selectedRow.cells[0].textContent.trim();
                const nombre = selectedRow.cells[1].textContent.trim();

                Swal.fire({
                    title: 'Editar Calendario',
                    html: `
                        <div class="text-left space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                                <input type="text" id="editar-calendario-id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="${calendarioId}" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input type="text" id="editar-nombre"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="${nombre}">
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
                        const nuevoNombre = document.getElementById('editar-nombre').value.trim();
                        if (!nuevoNombre) {
                            Swal.showValidationMessage('Por favor completa todos los campos');
                            return false;
                        }
                        return { nombre: nuevoNombre };
                    }
                }).then(result => {
                    if (!result.isConfirmed) return;

                    fetch(`/planeacion/calendarios/${calendarioId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        },
                        body: JSON.stringify({ Nombre: result.value.nombre })
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                location.reload();
                            } else {
                                showToast(data.message || 'Error al actualizar calendario', 'error');
                            }
                        })
                        .catch(() => showToast('Error al actualizar calendario', 'error'));
                });

                return;
            }

            // Editar línea (tabla 2)
            if (selectedCalendarioLine) {
                const selectedRow = document.querySelector(
                    `${LINE_BODY_SELECTOR} tr[data-linea-id="${selectedCalendarioLine}"]`
                );
                if (!selectedRow) return;

                const calendarioId = selectedRow.cells[0].textContent.trim();
                const fechaInicio = selectedRow.cells[1].textContent.trim();
                const fechaFin = selectedRow.cells[2].textContent.trim();
                const horas = selectedRow.cells[3].textContent.trim();
                const turno = selectedRow.cells[4].textContent.trim();

                const fechaInicioFormato = convertirFechaParaInput(fechaInicio);
                const fechaFinFormato = convertirFechaParaInput(fechaFin);

                Swal.fire({
                    title: 'Editar Línea de Calendario',
                    html: `
                        <div class="text-left space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                                <input type="text" id="editar-line-calendario-id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="${calendarioId}" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Inicio (Fecha Hora)</label>
                                <input type="datetime-local" id="editar-fecha-inicio"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="${fechaInicioFormato}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fin (Fecha Hora)</label>
                                <input type="datetime-local" id="editar-fecha-fin"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="${fechaFinFormato}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Horas</label>
                                <input type="number" step="0.1" id="editar-horas"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="${horas}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                                <select id="editar-turno"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="1" ${turno === '1' ? 'selected' : ''}>Turno 1</option>
                                    <option value="2" ${turno === '2' ? 'selected' : ''}>Turno 2</option>
                                    <option value="3" ${turno === '3' ? 'selected' : ''}>Turno 3</option>
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
                        const fechaInicioVal = document.getElementById('editar-fecha-inicio').value;
                        const fechaFinVal = document.getElementById('editar-fecha-fin').value;
                        const horasVal = document.getElementById('editar-horas').value;
                        const turnoVal = document.getElementById('editar-turno').value;

                        if (!fechaInicioVal || !fechaFinVal || !horasVal || !turnoVal) {
                            Swal.showValidationMessage('Por favor completa todos los campos');
                            return false;
                        }

                        return {
                            fechaInicio: fechaInicioVal,
                            fechaFin: fechaFinVal,
                            horas: horasVal,
                            turno: turnoVal
                        };
                    }
                }).then(result => {
                    if (!result.isConfirmed) return;

                    const lineaId = selectedCalendarioLine;

                    fetch(`/planeacion/calendarios/lineas/${lineaId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        },
                        body: JSON.stringify({
                            FechaInicio: result.value.fechaInicio,
                            FechaFin: result.value.fechaFin,
                            HorasTurno: result.value.horas,
                            Turno: result.value.turno
                        })
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                location.reload();
                            } else {
                                showToast(data.message || 'Error al actualizar línea de calendario', 'error');
                            }
                        })
                        .catch(() => showToast('Error al actualizar línea de calendario', 'error'));
                });
            }
        }

        // Convierte "dd/mm/yyyy HH:mm" (o con a. m./p. m.) a valor para datetime-local
        function convertirFechaParaInput(fechaTexto) {
            let fechaLimpia = (fechaTexto || '').trim();
            if (!fechaLimpia) return '';

            if (fechaLimpia.includes('a. m.')) {
                fechaLimpia = fechaLimpia.replace(' a. m.', '');
            } else if (fechaLimpia.includes('p. m.')) {
                fechaLimpia = fechaLimpia.replace(' p. m.', '');
                const partes = fechaLimpia.split(' ');
                if (partes.length === 2) {
                    const [fecha, hora] = partes;
                    const [horaStr, minuto] = hora.split(':');
                    let h = parseInt(horaStr, 10);
                    if (h !== 12) h += 12;
                    fechaLimpia = `${fecha} ${String(h).padStart(2, '0')}:${minuto}`;
                }
            }

            const [dia, mes, resto] = fechaLimpia.split('/');
            if (!resto) return '';

            const [anio, horaMin] = resto.split(' ');
            const fechaISO = `${anio}-${mes.padStart(2, '0')}-${dia.padStart(2, '0')}T${horaMin}`;

            return fechaISO;
        }

        function eliminarCalendario() {
            if (!selectedCalendarioTab && !selectedCalendarioLine) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una fila para eliminar',
                    icon: 'warning'
                });
                return;
            }

            let title, html, request;

            if (selectedCalendarioTab) {
                const selectedRow = document.querySelector(
                    `${TAB_BODY_SELECTOR} tr[data-calendario-id="${selectedCalendarioTab}"]`
                );
                if (!selectedRow) return;

                const calendarioId = selectedRow.cells[0].textContent.trim();
                const nombre = selectedRow.cells[1].textContent.trim();

                title = '¿Eliminar calendario?';
                html = `Vas a eliminar el calendario <b>${calendarioId}</b> - ${nombre}.`;

                request = () => fetch(`/planeacion/calendarios/${calendarioId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                });
            } else {
                const selectedRow = document.querySelector(
                    `${LINE_BODY_SELECTOR} tr[data-linea-id="${selectedCalendarioLine}"]`
                );
                if (!selectedRow) return;

                const calendarioId = selectedRow.cells[0].textContent.trim();
                const turno = selectedRow.cells[4].textContent.trim();

                title = '¿Eliminar línea de calendario?';
                html = `Vas a eliminar la línea del calendario <b>${calendarioId}</b> turno <b>${turno}</b>.`;

                const lineaId = selectedCalendarioLine;
                request = () => fetch(`/planeacion/calendarios/lineas/${lineaId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                });
            }

            Swal.fire({
                title,
                html,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (!result.isConfirmed) return;

                request()
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            location.reload();
                        } else {
                            showToast(data.message || 'Error al eliminar', 'error');
                        }
                    })
                    .catch(() => showToast('Error al eliminar', 'error'))
                    .finally(() => {
                        if (typeof disableButtons === 'function') {
                            disableButtons();
                        }
                    });
            });
        }

        // =========================================================
        //   FILTROS POR COLUMNA (TAB & LINE)
        // =========================================================
        function filtrarPorColumna() {
            let filtrosActivosHTML = '';
            const totalFiltros = activeFiltersTab.length + activeFiltersLine.length;

            if (totalFiltros > 0) {
                const todos = [...activeFiltersTab, ...activeFiltersLine];

                filtrosActivosHTML = `
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                        <div class="space-y-1">
                            ${todos.map((filtro, index) => `
                                <div class="flex items-center justify-between bg-white p-2 rounded border">
                                    <span class="text-xs">${filtro.columna}: ${filtro.valor}</span>
                                    <button type="button"
                                        onclick="removeFilter(${index}, '${filtro.tabla}')"
                                        class="text-red-500 hover:text-red-700 text-xs">×</button>
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
                            <select id="filtro-tabla"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                onchange="updateFilterColumns()">
                                <option value="tab">Calendarios (ReqCalendarioTab)</option>
                                <option value="line">Líneas de Calendario (ReqCalendarioLine)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                            <select id="filtro-columna"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="CalendarioId">No Calendario</option>
                                <option value="Nombre">Nombre</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor a buscar</label>
                            <input type="text" id="filtro-valor"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ingresa el valor a buscar">
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" id="btn-agregar-otro"
                                class="flex-1 px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
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
                    const valor = document.getElementById('filtro-valor').value.trim();

                    if (!valor) {
                        Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                        return false;
                    }

                    const filtros = tabla === 'tab' ? activeFiltersTab : activeFiltersLine;
                    const existe = filtros.some(f => f.columna === columna && f.valor === valor);

                    if (existe) {
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
                        const valor = document.getElementById('filtro-valor').value.trim();

                        if (!valor) {
                            Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                            return;
                        }

                        const filtros = tabla === 'tab' ? activeFiltersTab : activeFiltersLine;
                        const existe = filtros.some(f => f.columna === columna && f.valor === valor);

                        if (existe) {
                            Swal.showValidationMessage('Este filtro ya está activo');
                            return;
                        }

                        const filtro = { tabla, columna, valor };

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
            }).then(result => {
                if (!result.isConfirmed) return;

                const filtro = { ...result.value };

                if (filtro.tabla === 'tab') {
                    activeFiltersTab.push(filtro);
                } else {
                    activeFiltersLine.push(filtro);
                }

                applyFilters();
                showToast('Filtro agregado correctamente', 'success');
            });
        }

        function updateFilterColumns() {
            const tabla = document.getElementById('filtro-tabla').value;
            const select = document.getElementById('filtro-columna');

            if (tabla === 'tab') {
                select.innerHTML = `
                    <option value="CalendarioId">No Calendario</option>
                    <option value="Nombre">Nombre</option>
                `;
            } else {
                select.innerHTML = `
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
            filtrarPorColumna();
        }

        function applyFilters() {
            if (!originalDataTab.length) {
                originalDataTab = Array.from(
                    document.querySelectorAll(`${TAB_BODY_SELECTOR} tr[data-calendario-id]`)
                ).map(row => ({
                    element: row,
                    CalendarioId: row.cells[0].textContent.trim(),
                    Nombre: row.cells[1].textContent.trim()
                }));
            }

            if (!originalDataLine.length) {
                originalDataLine = Array.from(
                    document.querySelectorAll(`${LINE_BODY_SELECTOR} tr[data-linea-id]`)
                ).map(row => ({
                    element: row,
                    CalendarioId: row.cells[0].textContent.trim(),
                    FechaInicio: row.cells[1].textContent.trim(),
                    FechaFin: row.cells[2].textContent.trim(),
                    HorasTurno: row.cells[3].textContent.trim(),
                    Turno: row.cells[4].textContent.trim()
                }));
            }

            // Tabla 1
            originalDataTab.forEach(item => {
                item.element.style.display = '';
                if (activeFiltersTab.length) {
                    const matches = activeFiltersTab.every(filter => {
                        const value = (item[filter.columna] ?? '').toString().toLowerCase();
                        const filterValue = filter.valor.toLowerCase();
                        return value.includes(filterValue);
                    });
                    item.element.style.display = matches ? '' : 'none';
                }
            });

            // Tabla 2
            originalDataLine.forEach(item => {
                item.element.style.display = '';
                if (activeFiltersLine.length) {
                    const matches = activeFiltersLine.every(filter => {
                        const value = (item[filter.columna] ?? '').toString().toLowerCase();
                        const filterValue = filter.valor.toLowerCase();
                        return value.includes(filterValue);
                    });
                    item.element.style.display = matches ? '' : 'none';
                }
            });

            updateFilterCount();
        }

        function updateFilterCount() {
            const filterCount = document.getElementById('filter-count');
            if (!filterCount) return;

            const total = activeFiltersTab.length + activeFiltersLine.length;
            if (total > 0) {
                filterCount.textContent = total;
                filterCount.classList.remove('hidden');
            } else {
                filterCount.classList.add('hidden');
            }
        }

        function restablecerFiltros() {
            activeFiltersTab = [];
            activeFiltersLine = [];

            if (originalDataTab.length) {
                originalDataTab.forEach(item => item.element.style.display = '');
            }
            if (originalDataLine.length) {
                originalDataLine.forEach(item => item.element.style.display = '');
            }

            updateFilterCount();
            showToast('Restablecido<br>Todos los filtros y configuraciones han sido eliminados', 'success');
        }

        // =========================================================
        //   SUBIDA DE EXCEL
        // =========================================================
        window.subirExcelCalendarios = function () {
            // Si el componente de botones maneja esta acción, puede sobreescribirla.
        };

        window.subirExcelCalendariosMaestro = function () {
            Swal.fire({
                title: 'Subir Excel de Calendarios',
                html: `
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">Carga un archivo Excel con la información de calendarios.</p>
                        <p class="text-sm text-gray-600 font-semibold">
                            Las columnas deben ser: <strong>No Calendario, Nombre</strong>
                        </p>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <label for="swal-file-excel-calendarios" class="cursor-pointer">
                                <span class="mt-2 block text-sm font-medium text-gray-900">
                                    Arrastra o haz click para seleccionar
                                </span>
                                <input type="file" id="swal-file-excel-calendarios" name="file-excel"
                                    accept=".xlsx,.xls" class="sr-only">
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
                    fileInput.addEventListener('change', function () {
                        if (this.files[0]) {
                            document.getElementById('swal-file-name-calendarios').textContent = this.files[0].name;
                            document.getElementById('swal-file-info-calendarios').classList.remove('hidden');
                        }
                    });
                }
            }).then(result => {
                if (!result.isConfirmed || !result.value) return;

                Swal.fire({
                    title: 'Procesando...',
                    html: `
                        <p class="text-gray-600">Se está procesando tu archivo de calendarios.</p>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                            </div>
                        </div>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                const formData = new FormData();
                formData.append('archivo_excel', result.value);
                formData.append('_token', getCsrfToken());
                formData.append('tipo', 'calendarios');

                fetch('/planeacion/calendarios/excel', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        Swal.close();

                        if (data.success) {
                            Swal.fire({
                                title: '¡Procesado Exitosamente!',
                                html: `
                                    <div class="text-left space-y-2">
                                        <p>Registros procesados:
                                            <strong>${data.data?.registros_procesados ?? 0}</strong>
                                        </p>
                                    </div>
                                `,
                                icon: 'success',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#10b981'
                            }).then(() => location.reload());
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
            });
        };

        window.subirExcelLineas = function () {
            Swal.fire({
                title: 'Subir Excel de Líneas de Calendarios',
                html: `
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">Carga un archivo Excel con las líneas de calendarios.</p>
                        <p class="text-sm text-gray-600 font-semibold">
                            Las columnas deben ser:
                            <strong>No Calendario, Inicio (Fecha Hora), Fin (Fecha Hora), Horas, Turno</strong>
                        </p>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <label for="swal-file-excel-lineas" class="cursor-pointer">
                                <span class="mt-2 block text-sm font-medium text-gray-900">
                                    Arrastra o haz click para seleccionar
                                </span>
                                <input type="file" id="swal-file-excel-lineas" name="file-excel"
                                    accept=".xlsx,.xls" class="sr-only">
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
                    fileInput.addEventListener('change', function () {
                        if (this.files[0]) {
                            document.getElementById('swal-file-name-lineas').textContent = this.files[0].name;
                            document.getElementById('swal-file-info-lineas').classList.remove('hidden');
                        }
                    });
                }
            }).then(result => {
                if (!result.isConfirmed || !result.value) return;

                Swal.fire({
                    title: 'Procesando...',
                    html: `
                        <p class="text-gray-600">
                            Se está procesando tu archivo de líneas de calendarios.
                        </p>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                            </div>
                        </div>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                const formData = new FormData();
                formData.append('archivo_excel', result.value);
                formData.append('_token', getCsrfToken());
                formData.append('tipo', 'lineas');

                fetch('/planeacion/calendarios/excel', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        Swal.close();

                        if (data.success) {
                            Swal.fire({
                                title: '¡Procesado Exitosamente!',
                                html: `
                                    <div class="text-left space-y-2">
                                        <p>Registros procesados:
                                            <strong>${data.data?.registros_procesados ?? 0}</strong>
                                        </p>
                                    </div>
                                `,
                                icon: 'success',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#3b82f6'
                            }).then(() => location.reload());
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
            });
        };

        // =========================================================
        //   EXPONER FUNCIONES A BOTONES DEL NAVBAR
        // =========================================================
        window.agregarCalendarios = agregarCalendario;
        window.agregarLineaCalendarios = agregarLineaCalendario;
        window.editarCalendarios = editarCalendario;
        window.eliminarCalendarios = eliminarCalendario;
        window.filtrarCalendarios = filtrarPorColumna;
        window.limpiarFiltrosCalendarios = restablecerFiltros;

        document.addEventListener('DOMContentLoaded', () => {
            if (typeof disableButtons === 'function') {
                disableButtons();
            }
        });
    </script>

    @include('components.ui.toast-notification')
@endsection
