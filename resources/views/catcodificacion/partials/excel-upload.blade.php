<script>
    (() => {
        const importFlowState = {
            cancelled: false,
            uploadController: null,
            pollController: null,
            pollTimeoutId: null,
            importId: null,
            cancelUrl: null,
            queued: false,
        };

        const getCsrfToken = () =>
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const clearPollTimeout = () => {
            if (importFlowState.pollTimeoutId !== null) {
                window.clearTimeout(importFlowState.pollTimeoutId);
                importFlowState.pollTimeoutId = null;
            }
        };

        const clearControllers = () => {
            importFlowState.uploadController = null;
            importFlowState.pollController = null;
        };

        const abortActiveRequests = () => {
            clearPollTimeout();

            if (importFlowState.uploadController) {
                importFlowState.uploadController.abort();
            }

            if (importFlowState.pollController) {
                importFlowState.pollController.abort();
            }

            clearControllers();
        };

        const resetImportFlow = () => {
            abortActiveRequests();
            importFlowState.cancelled = false;
            importFlowState.importId = null;
            importFlowState.cancelUrl = null;
            importFlowState.queued = false;
        };

        const finishImportFlow = () => {
            clearPollTimeout();
            clearControllers();
            importFlowState.cancelled = false;
            importFlowState.importId = null;
            importFlowState.cancelUrl = null;
            importFlowState.queued = false;
        };

        const scheduleNextPoll = (url, attempts) => {
            if (importFlowState.cancelled) {
                return;
            }

            importFlowState.pollTimeoutId = window.setTimeout(() => {
                importFlowState.pollTimeoutId = null;
                window.pollImportProgress(url, attempts);
            }, 1000);
        };

        const notify = (message, type = 'info') => {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type);
                return;
            }

            Swal.fire({
                icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'info'),
                text: message,
                confirmButtonColor: '#2563eb',
            });
        };

        const cancelImportFlow = async () => {
            if (importFlowState.cancelled) {
                return;
            }

            importFlowState.cancelled = true;
            abortActiveRequests();

            const shouldCancelBackend = importFlowState.queued
                && typeof importFlowState.cancelUrl === 'string'
                && importFlowState.cancelUrl !== '';

            if (shouldCancelBackend) {
                try {
                    await fetch(importFlowState.cancelUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'Accept': 'application/json',
                        },
                    });
                } catch (_) {
                }
            }

            if (Swal.isVisible()) {
                Swal.close();
            }

            notify(
                shouldCancelBackend
                    ? 'La importacion fue cancelada en el servidor.'
                    : 'Se cancelo el seguimiento del archivo.',
                'warning'
            );
        };

        const renderHeaderErrors = (errors) => {
            if (!Array.isArray(errors) || errors.length === 0) {
                return '<p class="text-sm text-gray-600">La plantilla no coincide con la esperada.</p>';
            }

            const rows = errors.slice(0, 12).map((error) => {
                const actual = error.actual && String(error.actual).trim() !== ''
                    ? String(error.actual)
                    : '(vacio)';

                return `
                    <tr class="border-b border-gray-100">
                        <td class="px-3 py-2 font-semibold text-gray-700">${error.column}</td>
                        <td class="px-3 py-2 font-semibold text-blue-700">${error.column_letter ?? ''}</td>
                        <td class="px-3 py-2 text-gray-700">${error.expected}</td>
                        <td class="px-3 py-2 text-red-600">${actual}</td>
                    </tr>
                `;
            }).join('');

            return `
                <div class="text-left">
                    <p class="text-sm text-gray-600 mb-3">Corrige los encabezados marcados antes de importar.</p>
                    <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 text-left">Col</th>
                                    <th class="px-3 py-2 text-left">Letra</th>
                                    <th class="px-3 py-2 text-left">Esperado</th>
                                    <th class="px-3 py-2 text-left">Actual</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                </div>
            `;
        };

        const renderImportSummary = (created, updated, errors) => {
            const errorList = Array.isArray(errors) ? errors : [];
            const errorHtml = errorList.length > 0
                ? `
                    <div class="mt-4 text-left">
                        <p class="text-sm font-semibold text-red-600 mb-2">Errores detectados</p>
                        <div class="max-h-56 overflow-y-auto border border-red-100 rounded-lg">
                            <table class="w-full text-sm">
                                <thead class="bg-red-50 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Fila</th>
                                        <th class="px-3 py-2 text-left">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${errorList.map((error) => `
                                        <tr class="border-b border-red-50">
                                            <td class="px-3 py-2 font-semibold text-red-700">${error.fila ?? 'N/A'}</td>
                                            <td class="px-3 py-2 text-red-700">${error.error ?? 'Error desconocido'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `
                : '';

            return `
                <div class="text-sm text-gray-700 text-left">
                    <p>Registros creados: <strong>${created}</strong></p>
                    <p>Registros actualizados: <strong>${updated}</strong></p>
                    ${errorHtml}
                </div>
            `;
        };

        const showImportFinished = (summary = {}) => {
            const created = summary.created ?? 0;
            const updated = summary.updated ?? 0;
            const errorCount = summary.error_count ?? 0;
            const errors = summary.errors ?? [];

            Swal.fire({
                title: 'Importacion completa',
                icon: errorCount > 0 ? 'warning' : 'success',
                width: errorCount > 0 ? '820px' : '500px',
                html: renderImportSummary(created, updated, errors),
                confirmButtonColor: '#2563eb',
            }).then(async () => {
                if (typeof window.loadData === 'function') {
                    try {
                        await window.loadData(true);
                        return;
                    } catch (_) {
                    }
                }

                window.location.reload();
            });
        };

        window.subirExcelCatCodificacion = function () {
            Swal.fire({
                title: 'Subir Excel de CatCodificados',
                html: `
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">Selecciona la plantilla base de CatCodificados para importarla en segundo plano.</p>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <label for="swal-file-excel-catcodificados" class="cursor-pointer block">
                                <i class="fas fa-file-excel text-green-600 text-5xl mb-3"></i>
                                <span class="block text-sm font-medium text-gray-900">Haz click para seleccionar tu archivo</span>
                                <span class="block text-xs text-gray-500 mt-2">Formatos soportados: .xlsx, .xls (max. 10 MB)</span>
                                <input type="file" id="swal-file-excel-catcodificados" accept=".xlsx,.xls" class="sr-only">
                            </label>
                        </div>
                        <div id="swal-file-info-catcodificados" class="hidden rounded-lg bg-gray-50 px-4 py-3 text-left">
                            <p id="swal-file-name-catcodificados" class="text-sm font-semibold text-gray-800"></p>
                            <p id="swal-file-size-catcodificados" class="text-xs text-gray-500"></p>
                        </div>
                    </div>
                `,
                width: '520px',
                showCancelButton: true,
                confirmButtonText: 'Procesar Excel',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#059669',
                preConfirm: () => {
                    const input = document.getElementById('swal-file-excel-catcodificados');
                    const file = input?.files?.[0];

                    if (!file) {
                        Swal.showValidationMessage('Selecciona un archivo Excel.');
                        return false;
                    }

                    if (file.size > (10 * 1024 * 1024)) {
                        Swal.showValidationMessage('El archivo no puede exceder 10 MB.');
                        return false;
                    }

                    return file;
                },
                didOpen: () => {
                    const input = document.getElementById('swal-file-excel-catcodificados');

                    input?.addEventListener('change', (event) => {
                        const file = event.target?.files?.[0];
                        if (!file) {
                            return;
                        }

                        document.getElementById('swal-file-name-catcodificados').textContent = file.name;
                        document.getElementById('swal-file-size-catcodificados').textContent =
                            `${(file.size / 1024 / 1024).toFixed(2)} MB`;
                        document.getElementById('swal-file-info-catcodificados').classList.remove('hidden');
                    });
                },
            }).then((result) => {
                if (!result.isConfirmed || !result.value) {
                    return;
                }

                window.procesarExcel(result.value);
            });
        };

        window.procesarExcel = function (file) {
            resetImportFlow();

            const formData = new FormData();
            formData.append('archivo_excel', file);
            importFlowState.uploadController = new AbortController();

            Swal.fire({
                title: 'Procesando...',
                html: '<p class="text-sm text-gray-600">Se esta validando y encolando tu archivo.</p>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showCancelButton: true,
                cancelButtonText: 'Cancelar seguimiento',
                cancelButtonColor: '#6b7280',
                didOpen: () => Swal.showLoading(),
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) {
                    cancelImportFlow();
                }
            });

            fetch(@json(route('planeacion.codificacion.excel')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: formData,
                signal: importFlowState.uploadController.signal,
            })
                .then(async (response) => {
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw data;
                    }

                    return data;
                })
                .then((data) => {
                    importFlowState.uploadController = null;

                    if (importFlowState.cancelled) {
                        return;
                    }

                    importFlowState.importId = data.data?.import_id ?? null;
                    importFlowState.cancelUrl = data.data?.cancel_url ?? null;
                    importFlowState.queued = data.data?.queued === true;

                    if (data.success && data.data?.completed) {
                        finishImportFlow();
                        Swal.close();
                        showImportFinished(data.data.summary ?? {});
                        return;
                    }

                    if (data.success && data.data?.poll_url) {
                        window.pollImportProgress(data.data.poll_url);
                        return;
                    }

                    throw data;
                })
                .catch((error) => {
                    importFlowState.uploadController = null;

                    if (error?.name === 'AbortError' || importFlowState.cancelled) {
                        return;
                    }

                    finishImportFlow();
                    Swal.close();

                    const headerErrors = error?.errors?.headers ?? [];
                    if (headerErrors.length > 0) {
                        Swal.fire({
                            title: 'Plantilla invalida',
                            icon: 'error',
                            width: '780px',
                            html: renderHeaderErrors(headerErrors),
                            confirmButtonColor: '#dc2626',
                        });
                        return;
                    }

                    notify(error?.message || 'No fue posible procesar el archivo.', 'error');
                });
        };

        window.pollImportProgress = function (url, attempts = 0) {
            if (importFlowState.cancelled) {
                return;
            }

            if (attempts > 600) {
                finishImportFlow();
                Swal.close();
                notify('Tiempo de espera agotado al procesar el archivo.', 'warning');
                return;
            }

            importFlowState.pollController = new AbortController();

            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                },
                signal: importFlowState.pollController.signal,
            })
                .then(async (response) => ({
                    ok: response.ok,
                    result: await response.json().catch(() => ({})),
                }))
                .then(({ ok, result }) => {
                    importFlowState.pollController = null;

                    if (importFlowState.cancelled) {
                        return;
                    }

                    if (!ok || !result.success || !result.data) {
                        scheduleNextPoll(url, attempts + 1);
                        return;
                    }

                    const data = result.data;
                    const processed = data.processed_rows ?? 0;
                    const total = data.total_rows ?? '?';
                    const created = data.created ?? 0;
                    const updated = data.updated ?? 0;
                    const percent = result.percent ?? 0;
                    const errorCount = data.error_count ?? 0;

                    Swal.update({
                        html: `
                            <div class="text-sm text-gray-700 text-left">
                                <p class="mb-2">Procesando archivo...</p>
                                <p class="font-semibold mb-2">${processed}/${total} filas (${percent}%)</p>
                                <p class="text-sm text-gray-600 mb-1">Creados: ${created} - Actualizados: ${updated}</p>
                                <p class="text-sm text-gray-600">Errores: ${errorCount}</p>
                            </div>
                        `,
                    });

                    if (data.status === 'done') {
                        finishImportFlow();
                        Swal.close();
                        showImportFinished({
                            created,
                            updated,
                            error_count: errorCount,
                            errors: result.errors ?? [],
                        });

                        return;
                    }

                    if (data.status === 'cancelled') {
                        finishImportFlow();
                        Swal.close();
                        notify('La importacion fue cancelada.', 'warning');
                        return;
                    }

                    scheduleNextPoll(url, attempts + 1);
                })
                .catch((error) => {
                    importFlowState.pollController = null;

                    if (error?.name === 'AbortError' || importFlowState.cancelled) {
                        return;
                    }

                    scheduleNextPoll(url, attempts + 1);
                });
        };
    })();
</script>
