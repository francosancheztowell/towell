{{-- Modal para Eliminar Calendario o Línea --}}
<script>
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
</script>

