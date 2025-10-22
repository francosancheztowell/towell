@extends('layouts.app')

@section('content')
    <div class="container">
        <!-- Los mensajes de éxito/error se muestran con SweetAlert2 -->

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
                <table class="table table-bordered table-sm w-full">
                    <thead class="sticky top-0 bg-blue-500 text-white z-10">
                        <tr>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Salón</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Telar</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Tipo de Hilo</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Eficiencia</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Densidad</th>
                </tr>
            </thead>
                    <tbody id="eficiencia-body" class="bg-white text-black">
                @foreach ($eficiencia as $item)
                        @php
                            // Crear un ID único combinando telar y fibra
                            $uniqueId = $item->NoTelarId . '_' . $item->FibraId;
                            // Usar una combinación única si no hay ID
                            $recordId = $item->Id ?: $item->SalonTejidoId . '_' . $item->NoTelarId . '_' . $item->FibraId;
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this, '{{ $uniqueId }}', '{{ $recordId }}')"
                                ondblclick="deselectRow(this)"
                            data-eficiencia="{{ $uniqueId }}"
                            data-eficiencia-id="{{ $recordId }}">
                            <td class="py-1 px-4 border-b">{{ $item->SalonTejidoId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->NoTelarId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->FibraId }}</td>
                            <td class="py-1 px-4 border-b font-semibold">{{ number_format($item->Eficiencia * 100, 0) }}%</td>
                            <td class="py-1 px-4 border-b">{{ $item->Densidad ?? 'Normal' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
            </div>
        </div>
    </div>

    <!-- Los modales HTML han sido reemplazados por SweetAlert2 -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .slider-compact {
            -webkit-appearance: none;
            appearance: none;
            height: 6px;
            outline: none;
            border-radius: 3px;
            background: #e5e7eb;
        }

        .slider-compact::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            background: #3b82f6;
            cursor: pointer;
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .slider-compact::-moz-range-thumb {
            width: 18px;
            height: 18px;
            background: #3b82f6;
            cursor: pointer;
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .slider-compact::-moz-range-track {
            height: 6px;
            border-radius: 3px;
            background: #e5e7eb;
        }
    </style>
    <script>
        let selectedEficiencia = null;
        let selectedEficienciaId = null;

        // Variables para filtros
        let filtrosActuales = {
            salon: '',
            telar: '',
            fibra: '',
            densidad: '',
            eficiencia_min: '',
            eficiencia_max: ''
        };

        // Datos originales para filtrado
        let datosOriginales = @json($eficiencia);

        // Cache para optimización
        let cacheFiltros = new Map();
        let datosActuales = datosOriginales;

        // Helper para crear toasts
        function crearToast(icono, mensaje, duracion = 1500) {
            console.log('crearToast llamado con:', icono, mensaje, duracion);

            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: duracion,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: icono,
                title: mensaje
            });
        }

        function selectRow(row, uniqueId, eficienciaId) {
            console.log('selectRow llamado con uniqueId:', uniqueId, 'eficienciaId:', eficienciaId, 'tipo:', typeof eficienciaId); // Debug

            // Remover selección anterior
            document.querySelectorAll('tbody tr').forEach(r => {
                r.classList.remove('bg-blue-500', 'text-white');
                r.classList.add('hover:bg-blue-50');
            });

            // Seleccionar fila actual
            row.classList.remove('hover:bg-blue-50');
            row.classList.add('bg-blue-500', 'text-white');

            // Guardar eficiencia seleccionada
            selectedEficiencia = uniqueId;
            selectedEficienciaId = eficienciaId;
            console.log('selectedEficiencia establecido a:', selectedEficiencia, 'selectedEficienciaId:', selectedEficienciaId, 'tipo:', typeof selectedEficienciaId); // Debug

            // Habilitar botones de editar y eliminar
            enableButtons();
        }

        function deselectRow(row) {
            // Solo deseleccionar si la fila está seleccionada
            if (row.classList.contains('bg-blue-500')) {
                // Deseleccionar fila
                row.classList.remove('bg-blue-500', 'text-white');
                row.classList.add('hover:bg-blue-50');

                // Limpiar selección
                selectedEficiencia = null;
                selectedEficienciaId = null;

                // Deshabilitar botones
                disableButtons();
            }
        }

        function enableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');

            if (btnEditar) {
                btnEditar.disabled = false;
                btnEditar.className = 'inline-flex items-center px-3 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition-colors text-sm font-medium';
            }

            if (btnEliminar) {
                btnEliminar.disabled = false;
                btnEliminar.className = 'inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-sm font-medium';
            }
        }

        function disableButtons() {
            const btnEditar = document.getElementById('btn-editar');
            const btnEliminar = document.getElementById('btn-eliminar');

            if (btnEditar) {
                btnEditar.disabled = true;
                btnEditar.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
            }

            if (btnEliminar) {
                btnEliminar.disabled = true;
                btnEliminar.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg transition-colors text-sm font-medium cursor-not-allowed';
            }
        }

        // Datos de telares por salón
        const telaresPorSalon = {
            'JACQUARD': [201, 202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 213, 214, 215],
            'ITEMA': [299, 300, 301, 302, 303, 304, 305, 306, 307, 308, 309, 310, 311, 312, 313, 314, 315, 316, 317, 318, 319, 320]
        };

        function actualizarTelares() {
            const salon = document.getElementById('swal-salon').value;
            const telarSelect = document.getElementById('swal-telar');

            // Limpiar opciones
            telarSelect.innerHTML = '';

            if (salon && telaresPorSalon[salon]) {
                // Agregar opción por defecto
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Selecciona un telar';
                telarSelect.appendChild(defaultOption);

                // Agregar telares del salón seleccionado
                telaresPorSalon[salon].forEach(telar => {
                    const option = document.createElement('option');
                    option.value = telar;
                    option.textContent = telar;
                    telarSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Primero selecciona un salón';
                telarSelect.appendChild(option);
            }
        }

        function actualizarEficiencia(valor) {
            document.getElementById('eficiencia-value').textContent = valor + '%';
        }

        function actualizarEficienciaEdit(valor) {
            document.getElementById('eficiencia-value-edit').textContent = valor + '%';
        }

        // Funciones para el modal de filtrar
        function actualizarTelaresFiltro() {
            console.log('actualizarTelaresFiltro llamado');
            const salonSelect = document.getElementById('swal-salon');
            const telarSelect = document.getElementById('swal-telar');

            if (!salonSelect || !telarSelect) {
                console.log('No se encontraron los elementos del modal de filtros');
                return;
            }

            const salonSeleccionado = salonSelect.value;
            console.log('Salón seleccionado:', salonSeleccionado);

            // Limpiar opciones existentes
            telarSelect.innerHTML = '';

            if (salonSeleccionado && telaresPorSalon[salonSeleccionado]) {
                console.log('Telares disponibles para', salonSeleccionado, ':', telaresPorSalon[salonSeleccionado]);

                // Agregar opción por defecto
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Seleccionar';
                telarSelect.appendChild(defaultOption);

                // Agregar telares del salón seleccionado
                telaresPorSalon[salonSeleccionado].forEach(telar => {
                    const option = document.createElement('option');
                    option.value = telar;
                    option.textContent = telar;
                    telarSelect.appendChild(option);
                });

                console.log('Opciones agregadas al select de telares');
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Primero selecciona un salón';
                telarSelect.appendChild(option);
                console.log('No hay telares disponibles para el salón seleccionado');
            }
        }

        function actualizarEficienciaFiltro(valor, tipo) {
            if (tipo === 'min') {
                document.getElementById('eficiencia-min-value').textContent = valor + '%';
            } else if (tipo === 'max') {
                document.getElementById('eficiencia-max-value').textContent = valor + '%';
            }
        }

        function actualizarTelaresEdit() {
            const salonSelect = document.getElementById('swal-salon-edit');
            const telarSelect = document.getElementById('swal-telar-edit');
            const salonSeleccionado = salonSelect.value;

            if (!telarSelect) {
                console.error('No se encontró el elemento swal-telar-edit');
                return;
            }

            // MÉTODO FORZADO MEJORADO - Recrear completamente el select
            const parent = telarSelect.parentNode;
            const newSelect = document.createElement('select');

            // Copiar todas las clases y atributos
            newSelect.className = telarSelect.className;
            newSelect.id = telarSelect.id;
            newSelect.required = telarSelect.required;
            newSelect.style.cssText = telarSelect.style.cssText;

            // Generar opciones
            let optionsHTML = '<option value="">Seleccionar</option>';
            if (salonSeleccionado && telaresPorSalon[salonSeleccionado]) {
                telaresPorSalon[salonSeleccionado].forEach(numero => {
                    optionsHTML += `<option value="${numero}">${numero}</option>`;
                });
            } else {
                optionsHTML = '<option value="">Primero selecciona un salón</option>';
            }

            newSelect.innerHTML = optionsHTML;

            // Reemplazar completamente
            parent.replaceChild(newSelect, telarSelect);

            // FORZADO INMEDIATO - Sin timeouts para primera selección
            newSelect.style.display = 'none';
            newSelect.offsetHeight;
            newSelect.style.display = '';

            newSelect.style.visibility = 'hidden';
            newSelect.offsetHeight;
            newSelect.style.visibility = 'visible';

            newSelect.style.opacity = '0';
            newSelect.offsetHeight;
            newSelect.style.opacity = '1';

            // Forzar focus y blur para activar inmediatamente
            newSelect.focus();
            newSelect.blur();

            // Forzado adicional para asegurar funcionamiento
            requestAnimationFrame(() => {
                newSelect.style.display = 'none';
                newSelect.offsetHeight;
                newSelect.style.display = '';

                newSelect.style.visibility = 'hidden';
                newSelect.offsetHeight;
                newSelect.style.visibility = 'visible';
            });

            // Forzado final para garantizar
            setTimeout(() => {
                newSelect.style.display = 'none';
                newSelect.offsetHeight;
                newSelect.style.display = '';

                newSelect.focus();
                newSelect.blur();
            }, 5);
        }

        function agregarEficiencia() {
            Swal.fire({
                title: 'Crear Nueva Eficiencia',
                html: `
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Salón *</label>
                            <select id="swal-salon" class="w-full px-2 py-2 border border-gray-300 rounded text-center" required onchange="actualizarTelares()">
                                <option value="">Seleccionar</option>
                                <option value="JACQUARD">JACQUARD</option>
                                <option value="ITEMA">ITEMA</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Telar *</label>
                            <select id="swal-telar" class="w-full px-2 py-2 border border-gray-300 rounded text-center" required>
                                <option value="">Seleccionar</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Hilo *</label>
                            <input id="swal-fibra" type="text" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="H" maxlength="15" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Densidad</label>
                            <select id="swal-densidad" class="w-full px-2 py-2 border border-gray-300 rounded text-center">
                                <option value="Normal">Normal</option>
                                <option value="Alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Eficiencia: <span id="eficiencia-value" class="font-bold text-blue-600">78%</span></label>
                            <input id="swal-eficiencia" type="range" min="0" max="100" value="78" step="1"
                                   class="w-full h-4 bg-gray-200 rounded-lg appearance-none cursor-pointer slider-compact"
                                   oninput="actualizarEficiencia(this.value)">
                        </div>
                    </div>
                `,
                width: '400px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save me-2"></i>Crear',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#255be6',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const salon = document.getElementById('swal-salon').value;
                    const telar = document.getElementById('swal-telar').value;
                    const fibra = document.getElementById('swal-fibra').value.trim();
                    const eficienciaPorcentaje = document.getElementById('swal-eficiencia').value;
                    const densidad = document.getElementById('swal-densidad').value;

                    if (!salon || !telar || !fibra) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos');
                        return false;
                    }

                    // Convertir eficiencia de porcentaje a decimal
                    const eficiencia = parseFloat(eficienciaPorcentaje) / 100;

                    if (isNaN(eficiencia) || eficiencia < 0 || eficiencia > 1) {
                        Swal.showValidationMessage('La eficiencia debe estar entre 0% y 100%');
                        return false;
                    }

                    // Enviar el número del telar y el salón
                    return { telar: telar, salon: salon, fibra, eficiencia, densidad };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const { telar, salon, fibra, eficiencia, densidad } = result.value;

                    // Mostrar loader
                    Swal.fire({
                        title: 'Creando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Realizar petición AJAX para crear la eficiencia
                    const requestData = {
                        NoTelarId: telar,
                        salon: salon,
                        FibraId: fibra,
                        Eficiencia: eficiencia,
                        Densidad: densidad
                    };

                    console.log('Enviando datos:', requestData);

                    // Crear AbortController para timeout
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 segundos timeout

                    fetch('/planeacion/eficiencia', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(requestData),
                        signal: controller.signal
                    })
                    .then(response => {
                        clearTimeout(timeoutId);
                        console.log('Respuesta recibida:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Datos recibidos:', data);
                        if (data.success) {
                            Swal.fire({
                                title: '¡Eficiencia Creada!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al crear la eficiencia');
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        console.error('Error al crear eficiencia:', error);

                        let errorMessage = 'Error al crear la eficiencia. Verifica tu conexión y permisos.';

                        if (error.name === 'AbortError') {
                            errorMessage = 'La petición tardó demasiado. Intenta nuevamente.';
                        } else if (error.message) {
                            errorMessage = error.message;
                        }

                        Swal.fire({
                            title: 'Error',
                            text: errorMessage,
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function editarEficiencia() {
            console.log('editarEficiencia llamado, selectedEficiencia:', selectedEficiencia, 'selectedEficienciaId:', selectedEficienciaId, 'tipo:', typeof selectedEficienciaId); // Debug

            if (!selectedEficiencia || !selectedEficienciaId || selectedEficienciaId === 'null' || selectedEficienciaId === null) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una eficiencia para editar',
                    icon: 'warning'
                });
                return;
            }

            // Obtener datos de la fila seleccionada
            const selectedRow = document.querySelector(`tr[data-eficiencia="${selectedEficiencia}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos de la eficiencia seleccionada',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const salonActual = cells[0].textContent.trim();
            const telarActual = cells[1].textContent.trim();
            const fibraActual = cells[2].textContent.trim();
            const eficienciaActual = parseFloat(cells[3].textContent.trim().replace('%', '')) / 100;
            const densidadActual = cells[4].textContent.trim();

            // Obtener el telar desde los datos originales
            const eficienciaData = datosActuales.find(item => {
                const uniqueId = item.NoTelarId + '_' + item.FibraId;
                return uniqueId === selectedEficiencia;
            });

            // Usar directamente el número del telar
            const numeroTelarActual = eficienciaData ? eficienciaData.NoTelarId : telarActual;

            Swal.fire({
                title: 'Editar Eficiencia',
                html: `
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Salón *</label>
                            <select id="swal-salon-edit" class="w-full px-2 py-1 border border-gray-300 rounded text-center" required>
                                <option value="JACQUARD" ${salonActual === 'JACQUARD' ? 'selected' : ''}>JACQUARD</option>
                                <option value="ITEMA" ${salonActual === 'ITEMA' ? 'selected' : ''}>ITEMA</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Telar *</label>
                            <select id="swal-telar-edit" class="w-full px-2 py-1 border border-gray-300 rounded text-center" required onchange="actualizarTelaresEdit()">
                                <option value="">Seleccionar</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Hilo *</label>
                            <input id="swal-fibra-edit" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-center" placeholder="H" maxlength="15" required value="${fibraActual}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Densidad</label>
                            <select id="swal-densidad-edit" class="w-full px-2 py-1 border border-gray-300 rounded text-center">
                                <option value="Normal" ${densidadActual === 'Normal' ? 'selected' : ''}>Normal</option>
                                <option value="Alta" ${densidadActual === 'Alta' ? 'selected' : ''}>Alta</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Eficiencia: <span id="eficiencia-value-edit" class="font-bold text-blue-600">${Math.round(eficienciaActual * 100)}%</span></label>
                            <input id="swal-eficiencia-edit" type="range" min="0" max="100" value="${Math.round(eficienciaActual * 100)}" step="1"
                                   class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider-compact"
                                   oninput="actualizarEficienciaEdit(this.value)">
                        </div>
                    </div>
                `,
                width: '400px',
                showCancelButton: true,
                didOpen: () => {
                    // Inicializar telares basado en el salón actual
                    const salonSelect = document.getElementById('swal-salon-edit');
                    const telarSelect = document.getElementById('swal-telar-edit');
                    const salonActual = salonSelect.value;

                    // Limpiar opciones existentes
                    telarSelect.innerHTML = '<option value="">Seleccionar</option>';

                    // Cargar opciones del salón actual
                    if (salonActual && telaresPorSalon[salonActual]) {
                        telaresPorSalon[salonActual].forEach(numero => {
                            const option = document.createElement('option');
                            option.value = numero;
                            option.textContent = numero;
                            telarSelect.appendChild(option);
                        });
                    }

                    // Agregar event listener para cuando cambie el salón
                    salonSelect.addEventListener('change', function() {
                        setTimeout(() => {
                            actualizarTelaresEdit();
                        }, 50);
                    });
                },
                confirmButtonText: '<i class="fas fa-save me-2"></i>Actualizar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const salon = document.getElementById('swal-salon-edit').value;
                    const telar = document.getElementById('swal-telar-edit').value.trim();
                    const fibra = document.getElementById('swal-fibra-edit').value.trim();
                    const eficienciaPorcentaje = document.getElementById('swal-eficiencia-edit').value;
                    const densidad = document.getElementById('swal-densidad-edit').value;

                    if (!salon || !telar || !fibra || !eficienciaPorcentaje) {
                        Swal.showValidationMessage('Por favor completa los campos requeridos');
                        return false;
                    }

                    // Convertir eficiencia de porcentaje a decimal
                    const eficiencia = parseFloat(eficienciaPorcentaje) / 100;

                    if (isNaN(eficiencia) || eficiencia < 0 || eficiencia > 1) {
                        Swal.showValidationMessage('La eficiencia debe estar entre 0% y 100%');
                        return false;
                    }

                    // Enviar los datos en el formato esperado por el backend
                    return {
                        NoTelarId: telar,
                        SalonTejidoId: salon,
                        FibraId: fibra,
                        Eficiencia: eficiencia,
                        Densidad: densidad
                    };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const { NoTelarId, SalonTejidoId, FibraId, Eficiencia, Densidad } = result.value;

                    // Mostrar loader
                    Swal.fire({
                        title: 'Actualizando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Debug: Mostrar datos que se van a enviar
                    console.log('Datos a enviar:', {
                        NoTelarId: NoTelarId,
                        SalonTejidoId: SalonTejidoId,
                        FibraId: FibraId,
                        Eficiencia: Eficiencia,
                        Densidad: Densidad
                    });

                    // Realizar petición AJAX para actualizar la eficiencia
                    fetch(`/planeacion/eficiencia/${selectedEficienciaId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            NoTelarId: NoTelarId,
                            SalonTejidoId: SalonTejidoId,
                            FibraId: FibraId,
                            Eficiencia: Eficiencia,
                            Densidad: Densidad
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            // Debug: Mostrar detalles del error 422
                            console.log('Error response status:', response.status);
                            return response.text().then(text => {
                                console.log('Error response body:', text);
                                throw new Error(`HTTP error! status: ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Eficiencia Actualizada!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al actualizar la eficiencia');
                        }
                    })
                    .catch(error => {
                        console.error('Error al actualizar eficiencia:', error);
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al actualizar la eficiencia. Verifica tu conexión y permisos.',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function eliminarEficiencia() {
            console.log('eliminarEficiencia llamado, selectedEficiencia:', selectedEficiencia, 'selectedEficienciaId:', selectedEficienciaId, 'tipo:', typeof selectedEficienciaId); // Debug

            if (!selectedEficiencia || !selectedEficienciaId || selectedEficienciaId === 'null' || selectedEficienciaId === null) {
                Swal.fire({
                    title: 'Error',
                    text: 'Por favor selecciona una eficiencia para eliminar',
                    icon: 'warning'
                });
                return;
            }

            const selectedRow = document.querySelector(`tr[data-eficiencia="${selectedEficiencia}"]`);
            if (!selectedRow) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se encontraron los datos de la eficiencia seleccionada',
                    icon: 'error'
                });
                return;
            }

            const cells = selectedRow.querySelectorAll('td');
            const salon = cells[0].textContent.trim();
            const telar = cells[1].textContent.trim();
            const fibra = cells[2].textContent.trim();
            const eficiencia = cells[3].textContent.trim();

            Swal.fire({
                title: '¿Eliminar Eficiencia?',

                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loader
                    Swal.fire({
                        title: 'Eliminando...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Realizar petición AJAX para eliminar la eficiencia
                    fetch(`/planeacion/eficiencia/${selectedEficienciaId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Eficiencia Eliminada!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al eliminar la eficiencia');
                        }
                    })
                    .catch(error => {
                        console.error('Error al eliminar eficiencia:', error);
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al eliminar la eficiencia. Verifica tu conexión y permisos.',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function subirExcelEficiencia() {
            Swal.fire({
                title: 'Subir Excel - Eficiencia',
                html: `
                    <div class="text-left">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar archivo Excel</label>
                            <input id="excel-file" type="file" accept=".xlsx,.xls" class="swal2-input">
                        </div>
                        <div class="text-sm text-gray-600 bg-blue-50 p-3 rounded">
                            <i class="fas fa-info-circle mr-1"></i>
                            Formatos soportados: .xlsx, .xls (máximo 10MB)
                        </div>
                    </div>
                `,
                width: '400px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-upload me-2"></i>Subir',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const fileInput = document.getElementById('excel-file');
                    if (!fileInput.files[0]) {
                        Swal.showValidationMessage('Por favor selecciona un archivo Excel');
                        return false;
                    }
                    return fileInput.files[0];
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const file = result.value;

                    // Mostrar loader
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Subiendo y procesando archivo Excel',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Crear FormData
                    const formData = new FormData();
                    formData.append('archivo_excel', file);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                    // Enviar archivo
                    fetch('/planeacion/eficiencia/excel', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '¡Excel Procesado!',
                                text: data.message,
                                icon: 'success',
                                timer: 3000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Error al procesar el archivo');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Error al subir el archivo Excel',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        // Función global para que el botón "Subir Excel" del navbar pueda llamarla
        window.subirExcelEficiencia = function() {
            subirExcelEficiencia();
        };

        // Función global para que el botón "Filtrar" del navbar pueda llamarla
        window.filtrarEficiencia = function() {
            mostrarFiltros();
        };

        // Función global para limpiar filtros desde el navbar
        window.limpiarFiltrosEficiencia = function() {
            console.log('limpiarFiltrosEficiencia llamado desde navbar...');
            limpiarFiltros();
        };

        function mostrarFiltros() {
            Swal.fire({
                title: 'Filtrar Eficiencias',
                html: `
                    <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Salón</label>
                            <select id="swal-salon" class="w-full px-2 py-1 border border-gray-300 rounded text-center" value="${filtrosActuales.salon}" onchange="actualizarTelaresFiltro()">
                                <option value="">Seleccionar</option>
                                <option value="JACQUARD">JACQUARD</option>
                                <option value="ITEMA">ITEMA</option>
                            </select>
                            </div>
                            <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Telar</label>
                            <select id="swal-telar" class="w-full px-2 py-1 border border-gray-300 rounded text-xs" onchange="actualizarTelaresFiltro()">
                                <option value="">Seleccionar</option>
                            </select>
                            </div>
                            <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de Hilo</label>
                            <input id="swal-fibra" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-xs" placeholder="H, PAP, FIL370" value="${filtrosActuales.fibra}">
                            </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Densidad</label>
                            <select id="swal-densidad" class="w-full px-2 py-1 border border-gray-300 rounded text-xs">
                                    <option value="">Todas</option>
                                    <option value="Normal" ${filtrosActuales.densidad === 'Normal' ? 'selected' : ''}>Normal</option>
                                    <option value="Alta" ${filtrosActuales.densidad === 'Alta' ? 'selected' : ''}>Alta</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Eficiencia Mínima (%)</label>
                            <div class="flex items-center space-x-2">
                                <input id="swal-eficiencia-min" type="range" min="0" max="100" value="${filtrosActuales.eficiencia_min || 0}"
                                       class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider-compact"
                                       oninput="actualizarEficienciaFiltro(this.value, 'min')">
                                <span id="eficiencia-min-value" class="text-xs font-bold text-blue-600 w-12">${filtrosActuales.eficiencia_min || 0}%</span>
                        </div>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Eficiencia Máxima (%)</label>
                            <div class="flex items-center space-x-2">
                                <input id="swal-eficiencia-max" type="range" min="0" max="100" value="${filtrosActuales.eficiencia_max || 100}"
                                       class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider-compact"
                                       oninput="actualizarEficienciaFiltro(this.value, 'max')">
                                <span id="eficiencia-max-value" class="text-xs font-bold text-blue-600 w-12">${filtrosActuales.eficiencia_max || 100}%</span>
                            </div>
                        </div>
                        </div>
                    <div class="mt-3 text-xs text-gray-500 bg-blue-50 p-2 rounded">
                            <i class="fas fa-info-circle mr-1"></i>
                        Deja campos vacíos para no aplicar filtro
                    </div>
                `,
                width: '400px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-search mr-2"></i>Filtrar',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                didOpen: () => {
                    // Inicializar select de salón
                    const salonSelect = document.getElementById('swal-salon');
                    if (salonSelect) {
                        // Establecer valor del salón si existe
                        if (filtrosActuales.salon) {
                            salonSelect.value = filtrosActuales.salon;
                        }

                        // Actualizar telares basado en el salón seleccionado
                        setTimeout(() => {
                            actualizarTelaresFiltro();

                            // Preseleccionar telar si existe
                            setTimeout(() => {
                                const telarSelect = document.getElementById('swal-telar');
                                if (telarSelect && filtrosActuales.telar) {
                                    telarSelect.value = filtrosActuales.telar;
                                }
                            }, 100);
                        }, 100);

                    }
                },
                preConfirm: () => {
                    const salon = document.getElementById('swal-salon').value.trim();
                    const telar = document.getElementById('swal-telar').value.trim();
                    const fibra = document.getElementById('swal-fibra').value.trim();
                    const densidad = document.getElementById('swal-densidad').value;
                    const eficienciaMin = document.getElementById('swal-eficiencia-min').value;
                    const eficienciaMax = document.getElementById('swal-eficiencia-max').value;

                    // Validar rangos de eficiencia
                    if (eficienciaMin && eficienciaMax && parseFloat(eficienciaMin) > parseFloat(eficienciaMax)) {
                        Swal.showValidationMessage('La eficiencia mínima no puede ser mayor que la máxima');
                        return false;
                    }

                    return {
                        salon,
                        telar,
                        fibra,
                        densidad,
                        eficiencia_min: eficienciaMin,
                        eficiencia_max: eficienciaMax
                    };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    aplicarFiltros(result.value);
                }
            });
        }

        function aplicarFiltros(filtros) {
            // Actualizar filtros actuales
            filtrosActuales = { ...filtros };

            // Crear clave de cache
            const cacheKey = JSON.stringify(filtros);

            // Verificar cache primero
            if (cacheFiltros.has(cacheKey)) {
                let datosFiltrados = cacheFiltros.get(cacheKey);

                // Aplicar conversión SMITH → ITEMA también en el cache
                datosFiltrados = datosFiltrados.map(item => {
                    if (item.SalonTejidoId === 'SMITH') {
                        return { ...item, SalonTejidoId: 'ITEMA' };
                    }
                    return item;
                });

                actualizarTablaOptimizada(datosFiltrados);
                actualizarContador(datosFiltrados.length);

                const filtrosActivos = Object.values(filtros).filter(val => val !== '').length;
                if (filtrosActivos > 0) {
                    crearToast('success', `${datosFiltrados.length} de ${datosOriginales.length} registros mostrados`);
                }
                return;
            }

            // Filtrar datos de forma más eficiente
            let datosFiltrados = datosOriginales.filter(item => {
                // Optimización: salir temprano si no cumple un filtro
                if (filtros.salon) {
                    const salonFiltro = filtros.salon.toLowerCase();
                    const salonItem = item.SalonTejidoId.toLowerCase();

                    // Si se filtra por ITEMA, también incluir SMITH
                    if (salonFiltro === 'itema') {
                        if (salonItem !== 'itema' && salonItem !== 'smith') return false;
                    } else {
                        if (!salonItem.includes(salonFiltro)) return false;
                    }
                }
                if (filtros.telar && !item.NoTelarId.toLowerCase().includes(filtros.telar.toLowerCase())) return false;
                if (filtros.fibra && !item.FibraId.toLowerCase().includes(filtros.fibra.toLowerCase())) return false;
                if (filtros.densidad && item.Densidad !== filtros.densidad) return false;

                // Filtros numéricos solo si hay valores
                if (filtros.eficiencia_min) {
                    const eficienciaMinDecimal = parseFloat(filtros.eficiencia_min) / 100;
                    if (item.Eficiencia < eficienciaMinDecimal) return false;
                }

                if (filtros.eficiencia_max) {
                    const eficienciaMaxDecimal = parseFloat(filtros.eficiencia_max) / 100;
                    if (item.Eficiencia > eficienciaMaxDecimal) return false;
                }

                return true;
            });

            // Aplicar conversión SMITH → ITEMA a los datos filtrados antes de mostrar
            datosFiltrados = datosFiltrados.map(item => {
                if (item.SalonTejidoId === 'SMITH') {
                    return { ...item, SalonTejidoId: 'ITEMA' };
                }
                return item;
            });

            // Guardar en cache (máximo 10 entradas para evitar uso excesivo de memoria)
            if (cacheFiltros.size >= 10) {
                const firstKey = cacheFiltros.keys().next().value;
                cacheFiltros.delete(firstKey);
            }
            cacheFiltros.set(cacheKey, datosFiltrados);

            // Actualizar datos actuales
            datosActuales = datosFiltrados;

            // Actualizar tabla de forma más rápida
            actualizarTablaOptimizada(datosFiltrados);

            // Actualizar contador y estado de filtros
            actualizarContador(datosFiltrados.length);

            // Mostrar mensaje de éxito solo si hay filtros
            const filtrosActivos = Object.values(filtros).filter(val => val !== '').length;
            if (filtrosActivos > 0) {
                crearToast('success', `${datosFiltrados.length} de ${datosOriginales.length} registros mostrados`);
            }
        }

        function limpiarFiltros() {
            console.log('limpiarFiltros llamado...');

            // Limpiar filtros
            filtrosActuales = {
                salon: '',
                telar: '',
                fibra: '',
                densidad: '',
                eficiencia_min: '',
                eficiencia_max: ''
            };

            // Limpiar cache
            cacheFiltros.clear();

            // Restaurar datos actuales
            datosActuales = datosOriginales;

            // Restaurar tabla original de forma rápida
            actualizarTablaOptimizada(datosOriginales);

            // Actualizar contador
            actualizarContador(datosOriginales.length);

            // Ocultar indicador de filtros activos (solo si existe)
            const indicadorFiltros = document.getElementById('filtros-activos');
            if (indicadorFiltros) {
                indicadorFiltros.classList.add('hidden');
            }

            // Toast de confirmación
            console.log('Mostrando toast de limpiar filtros...');
            crearToast('success', `Filtros limpiados - Mostrando ${datosOriginales.length} registros`, 2000);
        }

        // Función optimizada para actualizar la tabla
        function actualizarTablaOptimizada(datos) {
            const tbody = document.getElementById('eficiencia-body');

            if (datos.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">
                            <i class="fas fa-search text-4xl mb-2"></i>
                            <br>No se encontraron resultados con los filtros aplicados
                        </td>
                    </tr>
                `;
                return;
            }

            // Usar DocumentFragment para mejor rendimiento
            const fragment = document.createDocumentFragment();

            datos.forEach(item => {
                const uniqueId = item.NoTelarId + '_' + item.FibraId;
                const eficienciaPorcentaje = Math.round(item.Eficiencia * 100);

                const row = document.createElement('tr');
                row.className = 'text-center hover:bg-blue-50 transition cursor-pointer';
                row.onclick = () => selectRow(row, uniqueId, item.Id || null);
                row.ondblclick = () => deselectRow(row);
                row.setAttribute('data-eficiencia', uniqueId);
                row.setAttribute('data-eficiencia-id', item.Id || 'null');

                // Usar directamente el número del telar
                const numeroTelar = item.NoTelarId;

                row.innerHTML = `
                    <td class="py-1 px-4 border-b">${item.SalonTejidoId}</td>
                    <td class="py-1 px-4 border-b">${numeroTelar}</td>
                    <td class="py-1 px-4 border-b">${item.FibraId}</td>
                    <td class="py-1 px-4 border-b font-semibold">${eficienciaPorcentaje}%</td>
                    <td class="py-1 px-4 border-b">${item.Densidad || 'Normal'}</td>
                `;

                fragment.appendChild(row);
            });

            // Actualizar DOM de una sola vez
            tbody.innerHTML = '';
            tbody.appendChild(fragment);
        }

        // Función original mantenida para compatibilidad
        function actualizarTabla(datos) {
            actualizarTablaOptimizada(datos);
        }

        function actualizarContador(cantidad) {
            // Actualizar contador del navbar
            const filterCount = document.getElementById('filter-count');
            const filtrosActivos = Object.values(filtrosActuales).filter(val => val !== '').length;

            if (filterCount) {
                if (filtrosActivos > 0) {
                    filterCount.textContent = filtrosActivos;
                    filterCount.classList.remove('hidden');
            } else {
                    filterCount.classList.add('hidden');
                }
            }

            // Mostrar/ocultar indicador de filtros activos (solo si existe)
            const indicadorFiltros = document.getElementById('filtros-activos');
            if (indicadorFiltros) {
                const shouldShow = filtrosActivos > 0;
                if (shouldShow && indicadorFiltros.classList.contains('hidden')) {
                    indicadorFiltros.classList.remove('hidden');
                } else if (!shouldShow && !indicadorFiltros.classList.contains('hidden')) {
                    indicadorFiltros.classList.add('hidden');
                }
            }
        }


        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            disableButtons();

            // Verificar si hay filtros activos desde la URL (para mantener estado después de recargar)
            const urlParams = new URLSearchParams(window.location.search);
            const hasFilters = urlParams.has('salon') || urlParams.has('telar') || urlParams.has('fibra') ||
                              urlParams.has('densidad') || urlParams.has('eficiencia_min') || urlParams.has('eficiencia_max');

            if (hasFilters) {
                const indicadorFiltros = document.getElementById('filtros-activos');
                if (indicadorFiltros) {
                    indicadorFiltros.classList.remove('hidden');
                }
            }
        });
    </script>

    <style>
        /* Estilos personalizados para el scrollbar */
        .scrollbar-thin {
            scrollbar-width: thin;
        }

        .scrollbar-thumb-gray-400::-webkit-scrollbar-thumb {
            background-color: #9ca3af;
            border-radius: 4px;
        }

        .scrollbar-track-gray-100::-webkit-scrollbar-track {
            background-color: #f3f4f6;
        }

        .scrollbar-thin::-webkit-scrollbar {
            width: 8px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background-color: #6b7280;
        }
    </style>
@endsection
