{{-- Modal para Subir Excel de Líneas de Calendarios --}}
<script>
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
</script>

