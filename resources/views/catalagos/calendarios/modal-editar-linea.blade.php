{{-- Modal para Editar Línea de Calendario --}}
<script>
    function editarLineaCalendario() {
        if (!selectedCalendarioLine) {
            Swal.fire({
                title: 'Error',
                text: 'Por favor selecciona una línea para editar',
                icon: 'warning'
            });
            return;
        }

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

                // Validar que las horas sean un número válido y positivo
                const horasNum = parseFloat(horasVal);
                if (isNaN(horasNum) || horasNum < 0) {
                    Swal.showValidationMessage('Las horas deben ser un número válido mayor o igual a 0');
                    return false;
                }

                // Validar que la fecha fin sea posterior a la fecha inicio
                if (new Date(fechaFinVal) <= new Date(fechaInicioVal)) {
                    Swal.showValidationMessage('La fecha de fin debe ser posterior a la fecha de inicio');
                    return false;
                }

                return {
                    fechaInicio: fechaInicioVal,
                    fechaFin: fechaFinVal,
                    horas: horasNum,
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
</script>

