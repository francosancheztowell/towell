@extends('layouts.app')

@section('page-title', 'Catálogo de Calendarios')

@section('navbar-right')
    <x-buttons.catalog-actions route="calendarios" :showFilters="true" />
    <button>

    </button>
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
                            <th class="px-4 py-2 text-center font-semibold w-[25%]">Fecha Inicio</th>
                            <th class="px-4 py-2 text-center font-semibold w-[25%]">Fecha Fin</th>
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

    <script src="{{ asset('js/catalog-core.js') }}"></script>

    @include('catalagos.calendarios.modal-calendario')
    @include('catalagos.calendarios.modal-agregar-linea')
    @include('catalagos.calendarios.modal-editar-linea')
    @include('catalagos.calendarios.modal-eliminar')
    @include('catalagos.calendarios.modal-eliminar-rango')
    @include('catalagos.calendarios.modal-filtrar')
    @include('catalagos.calendarios.modal-excel-calendarios')
    @include('catalagos.calendarios.modal-excel-lineas')
    @include('catalagos.calendarios.modal-recalcular')

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
        // La función agregarLineaCalendario está en modal-agregar-linea.blade.php

        // =========================================================
        //   CRUD CALENDARIOS (TABLA 1)
        // =========================================================
        function agregarCalendario() {
            // Si hay línea seleccionada, se entiende que quiere agregar línea
            if (selectedCalendarioLine) {
                agregarLineaCalendario();
                return;
            }

            if (typeof window.abrirModalCalendario === 'function') {
                window.abrirModalCalendario('agregar');
            } else {
                console.error('abrirModalCalendario no está disponible');
            }
        }

        // Hacer disponible globalmente inmediatamente
        window.agregarCalendarios = agregarCalendario;

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
                Swal.fire({
                    title: 'Cargando...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch(`/planeacion/calendarios/${encodeURIComponent(calendarioId)}/detalle`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                })
                    .then(r => r.json())
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            abrirModalCalendario('editar', data.data);
                        } else {
                            showToast(data.message || 'Error al cargar calendario', 'error');
                        }
                    })
                    .catch(() => {
                        Swal.close();
                        showToast('Error al cargar calendario', 'error');
                    });

                return;
            }


            // Editar línea (tabla 2) - La función está en modal-editar-linea.blade.php
            if (selectedCalendarioLine) {
                editarLineaCalendario();
            }
        }

        // Convierte "dd/mm/yyyy HH:mm" (o con a. m./p. m.) a valor para datetime-local
        function convertirFechaParaInput(fechaTexto) {
            try {
                let fechaLimpia = (fechaTexto || '').trim();
                if (!fechaLimpia) return '';

                // Manejar formato con a. m./p. m.
                if (fechaLimpia.includes('a. m.')) {
                    fechaLimpia = fechaLimpia.replace(' a. m.', '');
                } else if (fechaLimpia.includes('p. m.')) {
                    fechaLimpia = fechaLimpia.replace(' p. m.', '');
                    const partes = fechaLimpia.split(' ');
                    if (partes.length === 2) {
                        const [fecha, hora] = partes;
                        const [horaStr, minuto] = hora.split(':');
                        let h = parseInt(horaStr, 10);
                        if (!isNaN(h) && h !== 12) h += 12;
                        fechaLimpia = `${fecha} ${String(h).padStart(2, '0')}:${minuto || '00'}`;
                    }
                }

                // Parsear formato dd/mm/yyyy HH:mm
                const partesFecha = fechaLimpia.split('/');
                if (partesFecha.length !== 3) return '';

                const dia = partesFecha[0].trim();
                const mes = partesFecha[1].trim();
                const resto = partesFecha[2].trim();

                if (!resto) return '';

                const partesResto = resto.split(' ');
                if (partesResto.length !== 2) return '';

                const anio = partesResto[0].trim();
                const horaMin = partesResto[1].trim();

                // Validar que sean números válidos
                if (isNaN(dia) || isNaN(mes) || isNaN(anio)) return '';

                const fechaISO = `${anio}-${mes.padStart(2, '0')}-${dia.padStart(2, '0')}T${horaMin}`;

                // Validar que la fecha sea válida
                const fechaObj = new Date(fechaISO);
                if (isNaN(fechaObj.getTime())) return '';

                return fechaISO;
            } catch (e) {
                console.error('Error al convertir fecha:', e);
                return '';
            }
        }

        // La función eliminarCalendario está en modal-eliminar.blade.php

        // =========================================================
        //   FILTROS POR COLUMNA (TAB & LINE)
        // =========================================================
        // La función filtrarPorColumna está en modal-filtrar.blade.php

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
        // Las funciones de Excel están en modal-excel-calendarios.blade.php y modal-excel-lineas.blade.php
        window.subirExcelCalendarios = function () {
            // Si el componente de botones maneja esta acción, puede sobreescribirla.
        };

        // =========================================================
        //   RECALCULAR PROGRAMAS POR CALENDARIO
        // =========================================================
        // Las funciones de recalcular están en modal-recalcular.blade.php

        // =========================================================
        //   FUNCIONES PARA HABILITAR/DESHABILITAR BOTONES
        // =========================================================
        function enableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');

            if (btnEditar) {
                btnEditar.disabled = false;
                btnEditar.classList.remove('text-gray-400', 'cursor-not-allowed');
                btnEditar.classList.add('text-blue-600', 'hover:text-blue-800');
                btnEditar.onclick = () => editarCalendario();
            }

            if (btnEliminar) {
                btnEliminar.disabled = false;
                btnEliminar.classList.remove('text-red-400', 'cursor-not-allowed');
                btnEliminar.classList.add('text-red-600', 'hover:text-red-800');
                btnEliminar.onclick = () => eliminarCalendario();
            }
        }

        function disableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');

            if (btnEditar) {
                btnEditar.disabled = true;
                btnEditar.classList.add('text-gray-400', 'cursor-not-allowed');
                btnEditar.classList.remove('text-blue-600', 'hover:text-blue-800');
                btnEditar.onclick = null;
            }

            if (btnEliminar) {
                btnEliminar.disabled = true;
                btnEliminar.classList.add('text-red-400', 'cursor-not-allowed');
                btnEliminar.classList.remove('text-red-600', 'hover:text-red-800');
                btnEliminar.onclick = null;
            }
        }

        // =========================================================
        //   EXPONER FUNCIONES A BOTONES DEL NAVBAR
        // =========================================================
        // agregarCalendarios ya está asignado arriba
        window.agregarLineaCalendarios = agregarLineaCalendario;
        window.editarCalendarios = editarCalendario;
        window.eliminarCalendarios = eliminarCalendario;
        window.eliminarCalendariosPorRango = eliminarLineasPorRango;
        window.filtrarCalendarios = filtrarPorColumna;
        window.limpiarFiltrosCalendarios = restablecerFiltros;
        window.recalcularProgramasCalendarioNavbar = recalcularProgramasCalendarioNavbar;

        document.addEventListener('DOMContentLoaded', () => {
            disableButtons();
        });
    </script>

    @include('components.ui.toast-notification')
@endsection

