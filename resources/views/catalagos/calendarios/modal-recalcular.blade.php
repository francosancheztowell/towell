{{-- Modal para Recalcular Programas por Calendario --}}
<script>
    function recalcularProgramasCalendarioNavbar() {
        // Obtener el calendario seleccionado
        const selectedCalendarioTab = document.querySelector('#calendario-tab-body tr.bg-blue-500');
        if (!selectedCalendarioTab) {
            Swal.fire({
                title: 'Selección requerida',
                text: 'Por favor selecciona un calendario de la tabla superior para recalcular sus programas',
                icon: 'info',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        const calendarioId = selectedCalendarioTab.dataset.calendario; // CalendarioId (ej: "Calendario Tej2")
        const calendarioNombre = selectedCalendarioTab.cells[1].textContent.trim();

        console.log('Calendario seleccionado:', {
            calendarioId: calendarioId,
            calendarioNombre: calendarioNombre,
            dataset: selectedCalendarioTab.dataset
        });

        recalcularProgramasCalendario(calendarioId, calendarioNombre);
    }

    function recalcularProgramasCalendario(calendarioId, calendarioNombre) {
        Swal.fire({
            title: 'Recalcular Programas de Tejido',
            html: `
                <div class="text-center">
                    <div class="mb-4">
                        <p class="text-lg font-semibold text-gray-800">Calendario: ${calendarioNombre}</p>
                        <p class="text-sm text-gray-600">ID: ${calendarioId}</p>
                    </div>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    Esta acción recalculará las fechas de inicio y fin de todos los programas de tejido que usan este calendario, y actualizará sus líneas diarias.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="text-left text-sm text-gray-600">
                        <p><strong>¿Qué se recalculará?</strong></p>
                        <ul class="list-disc list-inside mt-2 space-y-1">
                            <li>Fechas de inicio y fin de los programas</li>
                            <li>Fórmulas de eficiencia (HorasProd, DiasJornada, etc.)</li>
                            <li>Líneas diarias del calendario (ReqProgramaTejidoLine)</li>
                        </ul>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Recalcular Programas',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6b7280',
            width: '600px',
            preConfirm: () => {
                return new Promise((resolve) => {
                    // Mostrar progreso mientras se procesa
                    Swal.fire({
                        title: 'Procesando...',
                        html: `
                            <div class="text-center">
                                <p class="text-gray-600 mb-4">Recalculando programas para el ${calendarioId}</p>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Esta operación puede tomar algunos segundos...</p>
                            </div>
                        `,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => Swal.showLoading()
                    });

                    // Hacer la llamada al backend
                    const url = `/planeacion/calendarios/${encodeURIComponent(calendarioId)}/recalcular-programas`;
                    console.log('URL de petición:', url);
                    console.log('CalendarioId original:', calendarioId);
                    console.log('CalendarioId encoded:', encodeURIComponent(calendarioId));

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        }
                    })
                    .then(r => {
                        if (!r.ok) {
                            return r.text().then(text => {
                                throw new Error(`HTTP ${r.status}: ${text || r.statusText}`);
                            });
                        }
                        return r.json();
                    })
                    .then(data => {
                        resolve(data);
                    })
                    .catch(error => {
                        console.error('Error en fetch:', error);
                        resolve({ success: false, message: 'Error de conexión: ' + error.message });
                    });
                });
            }
        }).then(result => {
            if (!result.isConfirmed) return;

            const data = result.value;

            if (data.success) {
                Swal.fire({
                    title: '¡Recálculo Completado!',
                    html: `
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-green-500 text-4xl"></i>
                            </div>
                        </div>
                    `,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#10b981',
                    width: '500px'
                });
            } else {
                Swal.fire({
                    title: 'Error en el Recálculo',
                    text: data.message || 'Hubo un problema al recalcular los programas',
                    icon: 'error',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
    }
</script>

