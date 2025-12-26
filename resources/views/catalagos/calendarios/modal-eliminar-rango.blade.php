{{-- Modal para Eliminar Líneas de Calendario por Rango --}}
<script>
    function eliminarLineasPorRango() {
        // Obtener el calendario seleccionado
        const selectedCalendarioTab = document.querySelector('#calendario-tab-body tr.bg-blue-500');
        if (!selectedCalendarioTab) {
            Swal.fire({
                title: 'Selección requerida',
                text: 'Por favor selecciona un calendario de la tabla superior para eliminar sus líneas por rango',
                icon: 'info',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        const calendarioId = selectedCalendarioTab.dataset.calendario;
        const calendarioNombre = selectedCalendarioTab.cells[1].textContent.trim();

        Swal.fire({
            title: 'Eliminar Líneas por Rango',
            html: `
                <div class="text-left space-y-4">
                    <div class="mb-4 p-3 bg-blue-50 border-l-4 border-blue-400 rounded">
                        <p class="text-sm font-semibold text-blue-800"> ${calendarioNombre}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                        <input type="date" id="eliminar-rango-fecha-inicio"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                        <input type="date" id="eliminar-rango-fecha-fin"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Turnos a Eliminar</label>
                        <div class="space-y-2 border border-gray-300 rounded-md p-3 bg-gray-50">
                            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-100 p-2 rounded">
                                <input type="checkbox" id="eliminar-turno-1" checked
                                    class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                                <span class="text-sm font-medium text-gray-700">Turno 1</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-100 p-2 rounded">
                                <input type="checkbox" id="eliminar-turno-2" checked
                                    class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                                <span class="text-sm font-medium text-gray-700">Turno 2</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-100 p-2 rounded">
                                <input type="checkbox" id="eliminar-turno-3" checked
                                    class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                                <span class="text-sm font-medium text-gray-700">Turno 3</span>
                            </label>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            width: '550px',
            preConfirm: () => {
                const fechaInicio = document.getElementById('eliminar-rango-fecha-inicio').value;
                const fechaFin = document.getElementById('eliminar-rango-fecha-fin').value;
                const turno1 = document.getElementById('eliminar-turno-1').checked;
                const turno2 = document.getElementById('eliminar-turno-2').checked;
                const turno3 = document.getElementById('eliminar-turno-3').checked;

                // Validar fechas
                if (!fechaInicio || !fechaFin) {
                    Swal.showValidationMessage('Por favor completa ambas fechas');
                    return false;
                }

                // Validar que la fecha fin sea posterior a la fecha inicio
                if (new Date(fechaFin) <= new Date(fechaInicio)) {
                    Swal.showValidationMessage('La fecha de fin debe ser posterior a la fecha de inicio');
                    return false;
                }

                // Validar que al menos un turno esté seleccionado
                if (!turno1 && !turno2 && !turno3) {
                    Swal.showValidationMessage('Por favor selecciona al menos un turno para eliminar');
                    return false;
                }

                // Recopilar turnos seleccionados
                const turnosSeleccionados = [];
                if (turno1) turnosSeleccionados.push(1);
                if (turno2) turnosSeleccionados.push(2);
                if (turno3) turnosSeleccionados.push(3);

                return {
                    calendarioId: calendarioId,
                    fechaInicio: fechaInicio,
                    fechaFin: fechaFin,
                    turnos: turnosSeleccionados
                };
            }
        }).then(result => {
            if (!result.isConfirmed) return;

            // Mostrar confirmación final antes de eliminar
            Swal.fire({
                title: '¿Confirmar eliminación?',
                html: `
                    <div class="text-left space-y-2">
                        <p><strong>Calendario:</strong> ${result.value.calendarioId}</p>
                        <p><strong>Fecha Inicio:</strong> ${result.value.fechaInicio}</p>
                        <p><strong>Fecha Fin:</strong> ${result.value.fechaFin}</p>
                        <p><strong>Turnos:</strong> ${result.value.turnos.join(', ')}</p>

                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280'
            }).then(confirmResult => {
                if (!confirmResult.isConfirmed) return;

                // Mostrar loading
                Swal.fire({
                    title: 'Eliminando...',
                    html: 'Por favor espera mientras se eliminan las líneas',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                // Hacer la petición al backend
                fetch(`/planeacion/calendarios/${encodeURIComponent(result.value.calendarioId)}/lineas/rango`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({
                        fechaInicio: result.value.fechaInicio,
                        fechaFin: result.value.fechaFin,
                        turnos: result.value.turnos
                    })
                })
                .then(r => r.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({
                            title: '¡Eliminación Exitosa!',
                            html: `
                                <div class="text-left space-y-2">
                                    <p><strong>Líneas eliminadas:</strong> ${data.eliminadas || 0}</p>
                                </div>
                            `,
                            icon: 'success',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#10b981'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Error al eliminar las líneas',
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
                        text: 'Error al procesar la solicitud: ' + error.message,
                        icon: 'error',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#ef4444'
                    });
                });
            });
        });
    }
</script>

