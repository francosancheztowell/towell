@extends('layouts.app')

@section('page-title', 'Editar Orden Programada')



@section('content')
    <div class="w-full">
        @if(!$puedeEditar)
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="font-bold text-yellow-800">Sin permisos de edición</p>
                        <p class="text-yellow-700 mt-1">No tienes permisos para editar órdenes. Solo supervisores del área Urdido pueden editar.</p>
                    </div>
                </div>
            </div>
        @endif

        @if($orden->Status === 'En Proceso')
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="font-bold text-red-800">Orden en proceso</p>
                        <p class="text-red-700 mt-1">No se pueden editar órdenes con status "En Proceso". Solo se pueden editar órdenes con status "Programado".</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Información de la Orden -->
        <div class="bg-white  p-3 mb-4">

            <div class="grid gap-1.5" style="display: grid; grid-template-columns: 0.7fr 1.6fr 0.7fr 0.7fr 0.7fr 0.7fr;">
                <!-- Folio (solo lectura - NO EDITABLE) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Folio <span class="text-red-500">*</span></label>
                    <input
                        type="text"
                        value="{{ $orden->Folio }}"
                        readonly
                        disabled
                        class="w-full px-1.5 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed"
                        title="El folio no se puede editar"
                    >
                </div>

                <!-- Folio Consumo -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Folio Consumo</label>
                    <input
                        type="text"
                        id="campo_FolioConsumo"
                        data-campo="FolioConsumo"
                        value="{{ $orden->FolioConsumo ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'readonly' : '' }}
                    >
                </div>

                <!-- Rizo/Pie -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tipo (Rizo/Pie)</label>
                    <select
                        id="campo_RizoPie"
                        data-campo="RizoPie"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'disabled' : '' }}
                    >
                        <option value="">Seleccionar...</option>
                        <option value="Rizo" {{ $orden->RizoPie === 'Rizo' ? 'selected' : '' }}>Rizo</option>
                        <option value="Pie" {{ $orden->RizoPie === 'Pie' ? 'selected' : '' }}>Pie</option>
                    </select>
                </div>

                <!-- Cuenta -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Cuenta</label>
                    <input
                        type="text"
                        id="campo_Cuenta"
                        data-campo="Cuenta"
                        value="{{ $orden->Cuenta ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'readonly' : '' }}
                    >
                </div>

                <!-- Calibre -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Calibre</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_Calibre"
                        data-campo="Calibre"
                        value="{{ $orden->Calibre ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'readonly' : '' }}
                    >
                </div>

                <!-- Metros -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Metros</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_Metros"
                        data-campo="Metros"
                        value="{{ $orden->Metros ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'readonly' : '' }}
                    >
                </div>
            </div>

            <div class="mt-1.5 grid gap-1.5" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));">

                <!-- Kilos -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Kilos</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_Kilos"
                        data-campo="Kilos"
                        value="{{ $orden->Kilos ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'readonly' : '' }}
                    >
                </div>

                <!-- Fibra -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fibra</label>
                    <select
                        id="campo_Fibra"
                        data-campo="Fibra"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'disabled' : '' }}
                    >
                        <option value="">Seleccionar...</option>
                        @foreach($fibras as $item)
                            @php
                                $fibraValor = trim($orden->Fibra ?? '');
                                $hiloValor = trim($item->Hilo ?? '');
                                $fibraNombre = trim($item->Fibra ?? '');

                                // Comparar con Hilo (identificador principal) o con Fibra si coincide
                                $esSeleccionada = ($fibraValor === $hiloValor) ||
                                                  ($fibraValor === $fibraNombre && !empty($fibraNombre));

                                // Usar Hilo como valor a guardar (es el identificador principal)
                                $valorGuardar = $hiloValor;

                                // Mostrar Hilo - Fibra si existe Fibra, o solo Hilo
                                if (!empty($fibraNombre) && $fibraNombre !== $hiloValor) {
                                    $valorMostrar = $hiloValor . ' - ' . $fibraNombre;
                                } else {
                                    $valorMostrar = $hiloValor;
                                }
                            @endphp
                            <option value="{{ $valorGuardar }}"
                                    {{ $esSeleccionada ? 'selected' : '' }}>
                                {{ $valorMostrar }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Salón de Tejido -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Salón de Tejido</label>
                    <select
                        id="campo_SalonTejidoId"
                        data-campo="SalonTejidoId"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'disabled' : '' }}
                    >
                        <option value="">Seleccionar...</option>
                        <option value="JACQUARD" {{ ($orden->SalonTejidoId ?? '') === 'JACQUARD' ? 'selected' : '' }}>JACQUARD</option>
                        <option value="SMIT" {{ ($orden->SalonTejidoId ?? '') === 'SMIT' ? 'selected' : '' }}>SMIT</option>
                    </select>
                </div>

                <!-- Máquina -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Máquina</label>
                    <select
                        id="campo_MaquinaId"
                        data-campo="MaquinaId"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'disabled' : '' }}
                    >
                        <option value="">Seleccionar...</option>
                        @foreach($maquinas as $maquina)
                            @php
                                $maquinaValor = $orden->MaquinaId ?? '';
                                $esSeleccionada = ($maquinaValor === $maquina->MaquinaId) ||
                                                  ($maquinaValor === ($maquina->Nombre ?? ''));
                            @endphp
                            <option value="{{ $maquina->MaquinaId }}"
                                    {{ $esSeleccionada ? 'selected' : '' }}>
                                {{ $maquina->Nombre ?? $maquina->MaquinaId }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Fecha Programada -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fecha Programada</label>
                    <input
                        type="date"
                        id="campo_FechaProg"
                        data-campo="FechaProg"
                        value="{{ $orden->FechaProg ? $orden->FechaProg->format('Y-m-d') : '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'readonly' : '' }}
                    >
                </div>

                <!-- Tipo Atado -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tipo Atado</label>
                    <select
                        id="campo_TipoAtado"
                        data-campo="TipoAtado"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'disabled' : '' }}
                    >
                        <option value="">Seleccionar...</option>
                        @php
                            $tipoAtadoValor = strtolower(trim($orden->TipoAtado ?? ''));
                        @endphp
                        <option value="Normal" {{ $tipoAtadoValor === 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="Especial" {{ $tipoAtadoValor === 'especial' ? 'selected' : '' }}>Especial</option>
                    </select>
                </div>

                <!-- Lote Proveedor -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Lote Proveedor</label>
                    <input
                        type="text"
                        id="campo_LoteProveedor"
                        data-campo="LoteProveedor"
                        value="{{ $orden->LoteProveedor ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        {{ !$puedeEditar ? 'readonly' : '' }}
                    >
                </div>
            </div>

            <div class="mt-2 grid gap-2" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));">
                <!-- Julios y Hilos -->
                <div>
                    <div class="overflow-x-auto border border-gray-200 rounded">
                        <table class="min-w-full text-sm">
                            <thead class="bg-blue-500 text-white">
                                <tr>
                                    <th class="px-2 py-1.5 text-center font-semibold">No. Julio</th>
                                    <th class="px-2 py-1.5 text-center font-semibold">Hilos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @php $juliosRows = $julios->values(); @endphp
                                @for ($i = 0; $i < 4; $i++)
                                    @php
                                        $row = $juliosRows[$i] ?? null;
                                    @endphp
                                    <tr data-julio-row="{{ $i }}" data-julio-id="{{ $row->Id ?? '' }}">
                                        <td class="px-2 py-1.5 text-center">
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                data-field="no_julio"
                                                value="{{ $row->Julios ?? '' }}"
                                                class="campo-julio w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                {{ !$puedeEditar ? 'readonly' : '' }}
                                            >
                                        </td>
                                        <td class="px-2 py-1.5 text-center">
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                data-field="hilos"
                                                value="{{ $row->Hilos ?? '' }}"
                                                class="campo-julio w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                {{ !$puedeEditar ? 'readonly' : '' }}
                                            >
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Observaciones -->
                <div style="height: 100%; display: flex; flex-direction: column;">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Observaciones</label>
                    <textarea
                        id="campo_Observaciones"
                        data-campo="Observaciones"
                        rows="3"
                        class="campo-editable w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        style="flex: 1 1 auto; resize: none;"
                        {{ !$puedeEditar ? 'readonly' : '' }}
                    >{{ $orden->Observaciones ?? '' }}</textarea>
                </div>
            </div>
        </div>


    </div>

    <script>
        (() => {
            const ordenId = {{ $orden->Id }};
            const puedeEditar = {{ $puedeEditar ? 'true' : 'false' }};
            const csrfToken = '{{ csrf_token() }}';
            const routeActualizar = '{{ route('urdido.editar.ordenes.programadas.actualizar') }}';
            const routeActualizarJulios = '{{ route('urdido.editar.ordenes.programadas.actualizar.julios') }}';

            const cambiosPendientes = new Map();
            let timeoutGuardado = null;

            const showToast = (icon, title) => {
                if (typeof Swal === 'undefined') {
                    alert(title);
                    return;
                }
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon,
                    title,
                    showConfirmButton: false,
                    timer: 2000,
                });
            };

            const showError = (message, title = 'Error') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }
                Swal.fire({
                    icon: 'error',
                    title,
                    html: `<p class="text-gray-700">${message}</p>`,
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Aceptar',
                    width: '500px',
                });
            };

            const showWarning = (message, title = 'Advertencia') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }
                Swal.fire({
                    icon: 'warning',
                    title,
                    html: `<p class="text-gray-700">${message}</p>`,
                    confirmButtonColor: '#f59e0b',
                    confirmButtonText: 'Aceptar',
                    width: '500px',
                });
            };

            const showSuccess = (message, title = 'Exito') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }
                Swal.fire({
                    icon: 'success',
                    title,
                    html: `<p class="text-gray-700">${message}</p>`,
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'Aceptar',
                    timer: 2000,
                    timerProgressBar: true,
                    width: '500px',
                });
            };

            const actualizarCampo = async (campo, valor) => {
                if (!puedeEditar) {
                    showWarning('No tienes permisos para editar ordenes. Solo supervisores del area Urdido pueden editar.', 'Sin Permisos');
                    return;
                }

                if (campo === 'Folio') {
                    showError('El folio no se puede editar. Es un campo de solo lectura.', 'Campo No Editable');
                    return;
                }

                try {
                    const response = await fetch(routeActualizar, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            orden_id: ordenId,
                            campo: campo,
                            valor: valor,
                        }),
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.error || 'Error al actualizar campo');
                    }

                    cambiosPendientes.delete(campo);
                    showToast('success', `${campo} actualizado correctamente`);
                } catch (error) {
                    console.error('Error al actualizar campo:', error);
                    showError(`Error al actualizar ${campo}: ${error.message}`, 'Error al Guardar');
                }
            };

            const actualizarJulioRow = async (row) => {
                if (!puedeEditar) {
                    showWarning('No tienes permisos para editar ordenes. Solo supervisores del area Urdido pueden editar.', 'Sin Permisos');
                    return;
                }

                const rowId = row.dataset.julioId || null;
                const noJulio = row.querySelector('[data-field="no_julio"]').value.trim();
                const hilos = row.querySelector('[data-field="hilos"]').value.trim();
                const noJulioVacio = noJulio === '';
                const hilosVacio = hilos === '';

                if ((noJulioVacio || hilosVacio) && !(noJulioVacio && hilosVacio && rowId)) {
                    return;
                }

                try {
                    const response = await fetch(routeActualizarJulios, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            orden_id: ordenId,
                            id: rowId || null,
                            no_julio: noJulio !== '' ? noJulio : null,
                            hilos: hilos !== '' ? hilos : null,
                        }),
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.error || 'Error al actualizar julio');
                    }

                    if (result.data?.deleted) {
                        row.dataset.julioId = '';
                    } else if (result.data?.id) {
                        row.dataset.julioId = String(result.data.id);
                    }

                    showToast('success', result.message || 'Julio actualizado correctamente');
                } catch (error) {
                    console.error('Error al actualizar julio:', error);
                    showError(`Error al actualizar julio: ${error.message}`, 'Error al Guardar');
                }
            };

            document.addEventListener('DOMContentLoaded', () => {
                const camposEditables = document.querySelectorAll('.campo-editable');
                const juliosRows = document.querySelectorAll('[data-julio-row]');
                const juliosTimeouts = new Map();

                camposEditables.forEach(campo => {
                    const campoNombre = campo.dataset.campo;
                    let valorAnterior = campo.value;

                    if (campo.tagName === 'INPUT' || campo.tagName === 'TEXTAREA') {
                        campo.addEventListener('change', () => {
                            if (campo.value !== valorAnterior) {
                                cambiosPendientes.set(campoNombre, campo.value);
                                valorAnterior = campo.value;

                                if (timeoutGuardado) {
                                    clearTimeout(timeoutGuardado);
                                }
                                timeoutGuardado = setTimeout(() => {
                                    actualizarCampo(campoNombre, campo.value);
                                }, 1000);
                            }
                        });

                        campo.addEventListener('blur', () => {
                            if (cambiosPendientes.has(campoNombre)) {
                                actualizarCampo(campoNombre, campo.value);
                            }
                        });
                    }

                    if (campo.tagName === 'SELECT') {
                        campo.addEventListener('change', () => {
                            if (campo.value !== valorAnterior) {
                                valorAnterior = campo.value;
                                actualizarCampo(campoNombre, campo.value);
                            }
                        });
                    }
                });

                juliosRows.forEach(row => {
                    const inputs = row.querySelectorAll('.campo-julio');
                    const rowKey = row.dataset.julioRow || '';

                    inputs.forEach(input => {
                        let valorAnterior = input.value;

                        const scheduleUpdate = () => {
                            if (juliosTimeouts.has(rowKey)) {
                                clearTimeout(juliosTimeouts.get(rowKey));
                            }
                            juliosTimeouts.set(rowKey, setTimeout(() => {
                                actualizarJulioRow(row);
                            }, 1000));
                        };

                        input.addEventListener('change', () => {
                            if (input.value !== valorAnterior) {
                                valorAnterior = input.value;
                                scheduleUpdate();
                            }
                        });

                        input.addEventListener('blur', () => {
                            if (juliosTimeouts.has(rowKey)) {
                                clearTimeout(juliosTimeouts.get(rowKey));
                                juliosTimeouts.delete(rowKey);
                            }
                            actualizarJulioRow(row);
                        });
                    });
                });
            });

            window.guardarCambios = async () => {
                if (!puedeEditar) {
                    showWarning('No tienes permisos para editar ordenes. Solo supervisores del area Urdido pueden editar.', 'Sin Permisos');
                    return;
                }

                if (cambiosPendientes.size === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin Cambios',
                        html: '<p class="text-gray-700">No hay cambios pendientes para guardar.</p>',
                        confirmButtonColor: '#2563eb',
                        confirmButtonText: 'Aceptar',
                        width: '400px',
                    });
                    return;
                }

                const resultado = await Swal.fire({
                    icon: 'question',
                    title: 'Guardar Cambios',
                    html: `<p class="text-gray-700">Deseas guardar ${cambiosPendientes.size} cambio(s) pendiente(s)?</p>`,
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Si, Guardar',
                    cancelButtonText: 'Cancelar',
                    width: '500px',
                });

                if (!resultado.isConfirmed) {
                    return;
                }

                try {
                    const promesas = Array.from(cambiosPendientes.entries()).map(([campo, valor]) => {
                        return actualizarCampo(campo, valor);
                    });

                    await Promise.all(promesas);
                    cambiosPendientes.clear();
                    showSuccess('Todos los cambios se guardaron correctamente.', 'Cambios Guardados');
                } catch (error) {
                    console.error('Error al guardar cambios:', error);
                    showError('Ocurrio un error al guardar algunos cambios. Por favor, intenta nuevamente.', 'Error al Guardar');
                }
            };
        })();
    </script>
@endsection
