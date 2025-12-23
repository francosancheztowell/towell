{{-- Modal para Agregar Línea de Calendario --}}
<script>
    function agregarLineaCalendario() {
        // Prellenar calendario si hay uno seleccionado en la tabla 1
        let calendarioPrellenado = '';
        if (selectedCalendarioTab) {
            const selectedRow = document.querySelector(
                `${TAB_BODY_SELECTOR} tr[data-calendario-id="${selectedCalendarioTab}"]`
            );
            if (selectedRow) {
                calendarioPrellenado = selectedRow.cells[0].textContent.trim();
            }
        }

        Swal.fire({
            title: 'Agregar Nueva Línea de Calendario',
            html: `
                <div class="text-left space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">No Calendario</label>
                        <input type="text" id="agregar-linea-calendario-id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Ej: CAL011"
                            value="${calendarioPrellenado}">
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
            width: '100%',
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

                // Validar que las horas sean un número válido y positivo
                const horasNum = parseFloat(horas);
                if (isNaN(horasNum) || horasNum < 0) {
                    Swal.showValidationMessage('Las horas deben ser un número válido mayor o igual a 0');
                    return false;
                }

                // Validar que la fecha fin sea posterior a la fecha inicio
                if (new Date(fechaFin) <= new Date(fechaInicio)) {
                    Swal.showValidationMessage('La fecha de fin debe ser posterior a la fecha de inicio');
                    return false;
                }

                return { calendarioId, fechaInicio, fechaFin, horas: horasNum, turno };
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
</script>

