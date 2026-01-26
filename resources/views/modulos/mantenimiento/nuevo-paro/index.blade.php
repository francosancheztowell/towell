@extends('layouts.app')

@section('page-title', 'Reportar Paro')

@section('content')
<div class="w-full p-3 md:p-6 lg:p-8">
    <div class="bg-white rounded-lg shadow-lg  border border-gray-200 p-4 md:p-6 lg:p-8 max-w-5xl mx-auto">

        <!-- Formulario -->
        <form id="form-paro">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-3">
                <!-- Columna Izquierda -->
                <div class="space-y-2 md:space-y-2">
                    <!-- Fecha -->
                    <div>
                        <label for="fecha" class="block text-xs md:text-sm font-medium text-gray-700">Fecha</label>
                        <input
                            type="date"
                            id="fecha"
                            name="fecha"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                            value="{{ date('Y-m-d') }}"
                            disabled
                            required
                        >
                    </div>

                    <!-- Depto -->
                    <div>
                        <label for="depto" class="block text-xs md:text-sm font-medium text-gray-700">Departamento</label>
                        <select
                            id="depto"
                            name="depto"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        >
                            <option value="">Seleccione un departamento</option>
                        </select>
                    </div>

                    <!-- Tipo Falla -->
                    <div>
                        <label for="tipo_falla" class="block text-xs md:text-sm font-medium text-gray-700">Tipo Falla</label>
                        <select
                            id="tipo_falla"
                            name="tipo_falla"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        >
                            <option value="">Seleccione un tipo de falla</option>
                        </select>
                    </div>

                    <!-- Orden de Trabajo -->
                    <div>
                        <label for="orden_trabajo" class="block text-xs md:text-sm font-medium text-gray-700">Orden de Trabajo</label>
                        <input
                            type="text"
                            id="orden_trabajo"
                            name="orden_trabajo"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        >
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="space-y-2 md:space-y-2">
                    <!-- Hora -->
                    <div>
                        <label for="hora" class="block text-xs md:text-sm font-medium text-gray-700">Hora</label>
                        <input
                            type="time"
                            id="hora"
                            name="hora"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                            value="{{ date('H:i') }}"
                            disabled
                        >
                    </div>

                    <!-- Maquina -->
                    <div>
                        <label for="maquina" class="block text-xs md:text-sm font-medium text-gray-700">Maquina</label>
                        <select
                            id="maquina"
                            name="maquina"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            disabled
                        >
                            <option value="">Seleccione primero un departamento</option>
                        </select>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label for="descripcion" class="block text-xs md:text-sm font-medium text-gray-700">Descripción</label>
                        <select
                            id="descripcion"
                            name="descrip"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            disabled
                        >
                            <option value="">Seleccione primero un departamento</option>
                        </select>
                    </div>

                    <!-- Falla -->
                    <div>
                        <label for="falla" class="block text-xs md:text-sm font-medium text-gray-700">Falla</label>
                        <select
                            id="falla"
                            name="falla"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            disabled
                        >
                            <option value="">Seleccione primero un departamento</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Obs - Ancho completo -->
            <div class="mt-3 md:mt-4">
                <label for="obs" class="block text-xs md:text-sm font-medium text-gray-700">Observaciones</label>
                <textarea
                    id="obs"
                    name="obs"
                    rows="2"
                    class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none outline-none"
                ></textarea>
            </div>

            <!-- Notificar a Supervisor -->
            <div class="flex items-center gap-2 mt-2 md:mt-3">
                <input
                    type="checkbox"
                    id="notificar_supervisor"
                    name="notificar_supervisor"
                    class="w-4 h-4 md:w-5 md:h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                >
                <label for="notificar_supervisor" class="text-xs md:text-sm font-medium text-gray-700">
                    Notificar a Supervisor
                </label>
                <span class="text-xs md:text-sm text-gray-500">(Mensaje Telegram)</span>
            </div>

            <!-- Botones -->
            <div class="grid grid-cols-3 gap-3 md:gap-4 mt-2 md:mt-3 pt-2 md:pt-3 border-t border-gray-200">
                <button
                    type="button"
                    id="btn-cancelar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm md:text-base font-medium rounded-md transition-colors"
                    onclick="window.location.href='{{ route('mantenimiento.solicitudes') }}'"
                >
                    Ir a Solicitudes
                </button>
                <button
                    type="button"
                    id="btn-cancelar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm md:text-base font-medium rounded-md transition-colors"
                    onclick="window.location.href='{{ route('mantenimiento.solicitudes') }}'"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    id="btn-aceptar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm md:text-base font-medium rounded-md transition-colors"
                >
                    Reportar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-paro');
    const selectDepto = document.getElementById('depto');
    const selectMaquina = document.getElementById('maquina');
    const selectTipoFalla = document.getElementById('tipo_falla');
    const selectFalla = document.getElementById('falla');
    const selectDescripcion = document.getElementById('descripcion');
    const inputOrdenTrabajo = document.getElementById('orden_trabajo');
    const checkboxNotificarSupervisor = document.getElementById('notificar_supervisor');

    // Ocultar botón/enlace de "Paro" en la barra de navegación solo en esta pantalla
    try {
        const paroLinks = document.querySelectorAll('a[href*="/mantenimiento/nuevo-paro"]');
        paroLinks.forEach(el => {
            el.style.display = 'none';
        });
    } catch (e) {
        console.warn('No se pudo ocultar el botón de Paro en la barra de navegación:', e);
    }

    // Cargar tipos de falla
    async function cargarTiposFalla() {
        try {
            const response = await fetch('{{ route('api.mantenimiento.tipos-falla') }}');
            const result = await response.json();

            if (result.success && result.data) {
                // Limpiar opciones existentes excepto la primera
                while (selectTipoFalla.options.length > 1) {
                    selectTipoFalla.remove(1);
                }

                // Agregar tipos de falla
                result.data.forEach(tipoFalla => {
                    const option = document.createElement('option');
                    option.value = tipoFalla;
                    option.textContent = tipoFalla;
                    selectTipoFalla.appendChild(option);
                });
            } else {
                console.error('Error al cargar tipos de falla:', result.error);
            }
        } catch (error) {
            console.error('Error al cargar tipos de falla:', error);
        }
    }

    // Cargar departamentos
    async function cargarDepartamentos() {
        try {
            const response = await fetch('{{ route('api.mantenimiento.departamentos') }}');
            const result = await response.json();

            if (result.success && result.data) {
                // Limpiar opciones existentes excepto la primera
                while (selectDepto.options.length > 1) {
                    selectDepto.remove(1);
                }

                // Agregar departamentos
                result.data.forEach(depto => {
                    const option = document.createElement('option');
                    option.value = depto;
                    option.textContent = depto;
                    selectDepto.appendChild(option);
                });
            } else {
                console.error('Error al cargar departamentos:', result.error);
            }
        } catch (error) {
            console.error('Error al cargar departamentos:', error);
        }
    }

    // Cargar fallas/descripcion por departamento y tipo de falla (CatParosFallas)
    async function cargarFallas(departamento, tipoFallaId = null) {
        // Reset si no hay depto
        if (!departamento) {
            while (selectFalla.options.length > 1) {
                selectFalla.remove(1);
            }
            selectFalla.value = '';
            selectFalla.disabled = true;
            selectFalla.innerHTML = '<option value=\"\">Seleccione primero un departamento</option>';
            while (selectDescripcion.options.length > 1) {
                selectDescripcion.remove(1);
            }
            selectDescripcion.value = '';
            selectDescripcion.disabled = true;
            selectDescripcion.innerHTML = '<option value=\"\">Seleccione primero un departamento</option>';
            return;
        }

        try {
            // Para Jacquard, Itema, Karl Mayer y Smith, usar "Tejido" en la consulta
            const depUpper = departamento.toUpperCase().trim();
            let departamentoParaConsulta = departamento;

            if (depUpper === 'JACQUARD' || depUpper === 'ITEMA' ||
                depUpper === 'KARL MAYER' || depUpper === 'KARLMAYER' || depUpper === 'SMITH') {
                departamentoParaConsulta = 'Tejido';
            }

            // Construir URL con tipo de falla si está seleccionado
            let url = `{{ url('/api/mantenimiento/fallas') }}/${encodeURIComponent(departamentoParaConsulta)}`;
            if (tipoFallaId) {
                url += `/${encodeURIComponent(tipoFallaId)}`;
            }

            const response = await fetch(url);
            const result = await response.json();

            if (result.success && Array.isArray(result.data)) {
                // Limpiar y cargar Falla
                selectFalla.innerHTML = '<option value=\"\">Seleccione una falla</option>';
                // Limpiar y cargar Descripción
                selectDescripcion.innerHTML = '<option value=\"\">Seleccione una descripción</option>';
                result.data.forEach(item => {
                    // item puede venir como string o objeto {Id, Falla, Descripcion}
                    const value = item.Falla ?? item.falla ?? item.Id ?? '';
                    const label = item.Descripcion ?? item.descripcion ?? item.Falla ?? item.falla ?? value;
                    // Falla
                    const optF = document.createElement('option');
                    optF.value = (item.Falla ?? item.falla ?? value);
                    optF.textContent = (item.Falla ?? item.falla ?? value);
                    optF.dataset.desc = (item.Descripcion ?? item.descripcion ?? '');
                    selectFalla.appendChild(optF);
                    // Descripción
                    const optD = document.createElement('option');
                    optD.value = (item.Descripcion ?? item.descripcion ?? label);
                    optD.textContent = (item.Descripcion ?? item.descripcion ?? label);
                    optD.dataset.falla = (item.Falla ?? item.falla ?? value);
                    selectDescripcion.appendChild(optD);
                });
                selectFalla.disabled = false;
                selectDescripcion.disabled = false;
            } else {
                console.error('Error al cargar fallas:', result.error);
                selectFalla.innerHTML = '<option value=\"\">Error al cargar fallas</option>';
                selectFalla.disabled = true;
                selectDescripcion.innerHTML = '<option value=\"\">Error al cargar descripciones</option>';
                selectDescripcion.disabled = true;
            }
        } catch (error) {
            console.error('Error al cargar fallas:', error);
            selectFalla.innerHTML = '<option value=\"\">Error al cargar fallas</option>';
            selectFalla.disabled = true;
            selectDescripcion.innerHTML = '<option value=\"\">Error al cargar descripciones</option>';
            selectDescripcion.disabled = true;
        }
    }

    // Event listener para Tipo Falla: auto-marcar/desmarcar "Notificar a Supervisor" y recargar fallas
    selectTipoFalla.addEventListener('change', function() {
        const tipoFallaSeleccionado = this.value.toUpperCase().trim();
        // Si es "Electrico" o "Mecanico", marcar automáticamente
        if (tipoFallaSeleccionado === 'ELECTRICO' || tipoFallaSeleccionado === 'MECANICO') {
            checkboxNotificarSupervisor.checked = true;
        } else {
            // Para cualquier otro tipo, desmarcar
            checkboxNotificarSupervisor.checked = false;
        }

        // Recargar fallas con el tipo de falla seleccionado
        const departamentoSeleccionado = selectDepto.value;
        if (departamentoSeleccionado) {
            // Limpiar selecciones de falla y descripción antes de recargar
            selectFalla.value = '';
            selectDescripcion.value = '';
            cargarFallas(departamentoSeleccionado, this.value || null);
        }
    });

    // Sincronizar selects: elegir Falla → selecciona su Descripción
    selectFalla.addEventListener('change', function() {
        const val = this.value;
        if (!val) {
            selectDescripcion.value = '';
            return;
        }
        // Buscar opción en Descripción con data-falla coincidente
        const match = Array.from(selectDescripcion.options).find(o => (o.dataset?.falla ?? '') === val);
        if (match) {
            selectDescripcion.value = match.value;
        }
    });

    // Sincronizar selects: elegir Descripción → selecciona su Falla
    selectDescripcion.addEventListener('change', function() {
        const val = this.value;
        if (!val) {
            selectFalla.value = '';
            return;
        }
        const match = Array.from(selectFalla.options).find(o => (o.dataset?.desc ?? '') === val);
        if (match) {
            selectFalla.value = match.value;
        }
    });

    // Cargar máquinas por departamento
    async function cargarMaquinas(departamento) {
        if (!departamento) {
            // Limpiar máquinas y deshabilitar select
            while (selectMaquina.options.length > 1) {
                selectMaquina.remove(1);
            }
            selectMaquina.value = '';
            selectMaquina.disabled = true;
            selectMaquina.innerHTML = '<option value="">Seleccione primero un departamento</option>';
            return;
        }

        try {
            const response = await fetch(`{{ url('/api/mantenimiento/maquinas') }}/${encodeURIComponent(departamento)}`);
            const result = await response.json();

            if (result.success && result.data) {
                // Limpiar opciones existentes
                selectMaquina.innerHTML = '<option value="">Seleccione una máquina</option>';

                // Agregar máquinas
                result.data.forEach(maquina => {
                    const option = document.createElement('option');
                    option.value = maquina.MaquinaId;
                    option.textContent = maquina.MaquinaId;
                    selectMaquina.appendChild(option);
                });

                // Habilitar select de máquinas
                selectMaquina.disabled = false;
            } else {
                console.error('Error al cargar máquinas:', result.error);
                selectMaquina.innerHTML = '<option value="">Error al cargar máquinas</option>';
            }
        } catch (error) {
            console.error('Error al cargar máquinas:', error);
            selectMaquina.innerHTML = '<option value="">Error al cargar máquinas</option>';
        }
    }

    // Cargar orden de trabajo sugerida por depto + máquina (ReqProgramaTejido en proceso)
    async function cargarOrdenTrabajo(departamento, maquina) {
        // Si falta alguno, limpiar y salir
        if (!departamento || !maquina) {
            // Si cambia a valor vacío, limpiamos el input
            inputOrdenTrabajo.value = '';
            return;
        }

        try {
            const baseUrl = `{{ url('/api/mantenimiento/orden-trabajo') }}`;
            const url = `${baseUrl}/${encodeURIComponent(departamento)}/${encodeURIComponent(maquina)}`;
            const response = await fetch(url);
            const result = await response.json();

            if (result.success && Array.isArray(result.data) && result.data.length > 0) {
                const primera = result.data[0];
                // Siempre refrescar el valor sugerido al cambiar depto/maquina
                inputOrdenTrabajo.value = primera.Orden_Prod || '';
            } else {
                // Si no hay registros en proceso, limpiamos para no dejar valores viejos
                inputOrdenTrabajo.value = '';
            }
        } catch (error) {
            console.error('Error al cargar orden de trabajo sugerida:', error);
            // En caso de error también limpiamos para evitar datos obsoletos
            inputOrdenTrabajo.value = '';
        }
    }

    // Event listener para cambio de departamento
    selectDepto.addEventListener('change', function() {
        const departamentoSeleccionado = this.value;
        // Al cambiar de departamento, limpiamos orden de trabajo (para evitar valores de otro depto)
        inputOrdenTrabajo.value = '';
        cargarMaquinas(departamentoSeleccionado);
        // Cargar fallas con el tipo de falla seleccionado (si hay uno)
        const tipoFallaSeleccionado = selectTipoFalla.value || null;
        cargarFallas(departamentoSeleccionado, tipoFallaSeleccionado);
    });

    // Event listener para cambio de máquina → buscar orden de trabajo sugerida
    selectMaquina.addEventListener('change', function() {
        const departamentoSeleccionado = selectDepto.value;
        const maquinaSeleccionada = this.value;
        cargarOrdenTrabajo(departamentoSeleccionado, maquinaSeleccionada);
    });

    // Cargar datos al iniciar
    cargarTiposFalla();
    cargarDepartamentos();

    // Submit del formulario
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);

        // Asegurar que los campos deshabilitados (fecha y hora) se incluyan
        const fechaInput = document.getElementById('fecha');
        const horaInput = document.getElementById('hora');
        if (fechaInput.value) {
            formData.set('fecha', fechaInput.value);
        }
        if (horaInput.value) {
            formData.set('hora', horaInput.value);
        }

        // Agregar el checkbox
        formData.append('notificar_supervisor', checkboxNotificarSupervisor.checked ? '1' : '0');

        try {
            // Enviar datos al servidor
            const response = await fetch('{{ route('api.mantenimiento.paros.store') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Mostrar mensaje de éxito con SweetAlert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reportado correctamente',
                        text: result.message || 'El paro ha sido reportado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // Volver a la página anterior
                        if (document.referrer && document.referrer !== window.location.href) {
                            window.location.href = document.referrer;
                        } else {
                            // Si no hay referrer, ir a solicitudes
                            window.location.href = '{{ route('mantenimiento.solicitudes') }}';
                        }
                    });
                } else {
                    alert(result.message || 'Paro reportado correctamente');
                    // Volver a la página anterior
                    if (document.referrer && document.referrer !== window.location.href) {
                        window.location.href = document.referrer;
                    } else {
                        // Si no hay referrer, ir a solicitudes
                        window.location.href = '{{ route('mantenimiento.solicitudes') }}';
                    }
                }
            } else {
                // Error del servidor
                const errorMsg = result.error || 'Error al reportar el paro. Por favor, intenta nuevamente.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMsg
                    });
                } else {
                    alert(errorMsg);
                }
            }
        } catch (error) {
            console.error('Error al reportar paro:', error);
            const errorMsg = 'Error de conexión. Por favor, verifica tu conexión e intenta nuevamente.';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg
                });
            } else {
                alert(errorMsg);
            }
        }
    });
});
</script>
@endsection

