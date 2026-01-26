@extends('layouts.app')
@section('navbar-right')
<button type="button" id="btn-nuevo-paro" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors whitespace-nowrap">
    Nuevo
</button>
<button type="button" id="btn-terminar-paro" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors whitespace-nowrap">
    Terminar
</button>
@endsection
@section('page-title', 'Reporte de Fallos y Paros')
@section('content')
<div class="w-full">
    <div class="bg-white">
        <!-- Contenedor principal con tabla y botones -->
        <div class="flex gap-4">
            <!-- Tabla -->
            <div class="flex-1 overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300 text-xs md:text-sm">
                    <!-- Encabezados -->
                    <thead>
                        <tr class="bg-blue-500 text-white text-center ">
                            <th class="px-2 py-2  font-semibold ">Folio</th>
                            <th class="px-2 py-2  font-semibold  ">Estatus</th>
                            <th class="px-2 py-2  font-semibold  ">Fecha</th>
                            <th class="px-2 py-2  font-semibold ">Hora</th>
                            <th class="px-2 py-2  font-semibold ">Depto</th>
                            <th class="px-2 py-2  font-semibold  ">Maquina</th>
                            <th class="px-2 py-2  font-semibold  ">Tipo Falla</th>
                            <th class="px-2 py-2  font-semibold  ">Falla</th>
                            <th class="px-2 py-2  font-semibold  ">Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-paros">
                        <!-- Los datos se cargarán dinámicamente aquí -->
                        <tr>
                            <td colspan="9" class="border border-gray-300 px-2 py-2 text-center text-gray-500">
                                Cargando datos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>


        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbodyParos = document.getElementById('tbody-paros');

    // Ocultar botón de "Paro" en la barra de navegación
    const navLinks = document.querySelectorAll('nav a, header a, .nav a, [role="navigation"] a');
    navLinks.forEach(link => {
        const href = link.getAttribute('href') || '';
        if (href.includes('/mantenimiento/nuevo-paro') || href.includes('mantenimiento') && (link.textContent.includes('Paro') || link.textContent.includes('paro'))) {
            link.style.display = 'none';
        }
    });

    // Cargar paros/fallas activos
    async function cargarParos() {
        try {
            const response = await fetch('{{ route('api.mantenimiento.paros.index') }}');
            const result = await response.json();

            if (result.success && Array.isArray(result.data)) {
                // Limpiar tbody
                tbodyParos.innerHTML = '';

                if (result.data.length === 0) {
                    tbodyParos.innerHTML = `
                        <tr>
                            <td colspan="9" class="border border-gray-300 px-2 py-2 text-center text-gray-500">
                                No hay paros/fallas activos
                            </td>
                        </tr>
                    `;
                    return;
                }

                // Agregar filas con los datos
                result.data.forEach(paro => {
                    const row = document.createElement('tr');
                    row.dataset.paroId = paro.Id || '';
                    row.className = 'cursor-pointer hover:bg-gray-100 transition-colors';

                    // Formatear fecha (YYYY-MM-DD -> DD/MM/YYYY)
                    const fecha = paro.Fecha ? new Date(paro.Fecha).toLocaleDateString('es-MX', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit'
                    }) : '';

                    row.innerHTML = `
                        <td class="px-2 py-2 text-gray-900 text-center">${paro.Folio || ''}</td>
                        <td class="px-2 py-2 text-gray-900 text-center">${paro.Estatus || ''}</td>
                        <td class="px-2 py-2 text-gray-900 text-center">${fecha}</td>
                        <td class="px-2 py-2 text-gray-900 text-center">${paro.Hora || ''}</td>
                        <td class="px-2 py-2 text-gray-900 text-center">${paro.Depto || ''}</td>
                        <td class="px-2 py-2 text-gray-900 text-center">${paro.MaquinaId || ''}</td>
                        <td class="px-2 py-2 text-gray-900 text-center">${paro.TipoFallaId || ''}</td>
                        <td class="px-2 py-2 text-gray-900 text-center">${paro.Falla || ''}</td>
                        <td class="px-2 py-2 text-gray-900 text-center">${paro.NomEmpl || ''}</td>
                    `;

                    // Event listener para seleccionar fila
                    row.addEventListener('click', function() {
                        // Remover selección de otras filas
                        document.querySelectorAll('#tbody-paros tr').forEach(r => {
                            r.classList.remove('bg-blue-500');
                            r.classList.add('hover:bg-gray-100');
                            r.querySelectorAll('td').forEach(td => {
                                td.classList.remove('text-white');
                                td.classList.add('text-gray-900');
                            });
                        });

                        // Aplicar selección a la fila clickeada
                        this.classList.remove('hover:bg-gray-100');
                        this.classList.add('bg-blue-500');
                        this.querySelectorAll('td').forEach(td => {
                            td.classList.remove('text-gray-900');
                            td.classList.add('text-white');
                        });

                        // Guardar ID del paro seleccionado (usar variable global)
                        window.paroSeleccionadoId = this.dataset.paroId || '';
                    });

                    tbodyParos.appendChild(row);
                });
            } else {
                tbodyParos.innerHTML = `
                    <tr>
                        <td colspan="9" class="border border-gray-300 px-2 py-2 text-center text-red-500">
                            Error al cargar los datos: ${result.error || 'Error desconocido'}
                        </td>
                    </tr>
                `;
            }
        } catch (error) {
            console.error('Error al cargar paros:', error);
            tbodyParos.innerHTML = `
                <tr>
                    <td colspan="9" class="border border-gray-300 px-2 py-2 text-center text-red-500">
                        Error de conexión. Por favor, recarga la página.
                    </td>
                </tr>
            `;
        }
    }

    // Variable global para almacenar el ID del paro seleccionado
    window.paroSeleccionadoId = null;

    // Cargar datos al iniciar
    cargarParos();

    // Botón Nuevo: redirigir a nuevo-paro
    const btnNuevo = document.getElementById('btn-nuevo-paro');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = '{{ route('mantenimiento.nuevo-paro') }}';
        });
    }

    // Botón Terminar: redirigir a finalizar-paro con el paro seleccionado
    const btnTerminar = document.getElementById('btn-terminar-paro');
    if (btnTerminar) {
        btnTerminar.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.paroSeleccionadoId) {
                // Guardar ID en localStorage para que la vista de finalizar lo recupere
                localStorage.setItem('selectedParoId', window.paroSeleccionadoId);
                window.location.href = '{{ route('mantenimiento.finalizar-paro') }}';
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Seleccione un paro',
                        text: 'Por favor, seleccione un paro de la tabla para finalizar.'
                    });
                } else {
                    alert('Por favor, seleccione un paro de la tabla para finalizar.');
                }
            }
        });
    }
});
</script>
@endsection

