{{-- Modal para Eliminar Calendario o Línea --}}
<script>
    async function eliminarCalendario() {
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

            request = () => http.delete(`/planeacion/calendarios/${calendarioId}`);
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
            request = () => http.delete(`/planeacion/calendarios/lineas/${lineaId}`);
        }

        const confirmado = await notify.confirm({
            title,
            html,
            icon: 'warning',
            confirmText: 'Sí, eliminar',
            confirmColor: '#dc2626',
        });
        if (!confirmado) return;

        try {
            const data = await request();
            if (data.success) {
                showToast(data.message, 'success');
                location.reload();
            } else {
                showToast(data.message || 'Error al eliminar', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Error al eliminar', 'error');
        } finally {
            if (typeof disableButtons === 'function') {
                disableButtons();
            }
        }
    }
</script>

