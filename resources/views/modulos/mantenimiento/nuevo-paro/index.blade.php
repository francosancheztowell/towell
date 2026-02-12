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
                            required
                        >
                            <option value="">Cargando...</option>
                        </select>
                        <input type="hidden" id="depto-hidden" name="depto" value="">
                    </div>

                    <!-- Tipo Falla -->
                    <div>
                        <label for="tipo_falla" class="block text-xs md:text-sm font-medium text-gray-700">Tipo Falla</label>
                        <select
                            id="tipo_falla"
                            name="tipo_falla"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            disabled
                        >
                            <option value="">Seleccione primero una máquina</option>
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
                            disabled
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
                            <option value="">Seleccione primero un tipo de falla</option>
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
                    checked
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
                    id="btn-ir-solicitudes"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-white hover:bg-gray-50 text-gray-700 text-base md:text-lg font-medium rounded-md transition-colors border-2 border-gray-300"
                    onclick="window.location.href='{{ route('mantenimiento.solicitudes') }}'"
                >
                    Ir a Solicitudes
                </button>
                <button
                    type="button"
                    id="btn-cancelar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-white hover:bg-gray-50 text-gray-700 text-base md:text-lg font-medium rounded-md transition-colors border-2 border-gray-300"
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
                // Limpiar opciones existentes
                selectTipoFalla.innerHTML = '<option value="">Seleccione primero una máquina</option>';

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

    // Cargar departamentos y seleccionar automáticamente el del usuario
    async function cargarDepartamentos() {
        try {
            const response = await fetch('{{ route('api.mantenimiento.departamentos') }}');
            const result = await response.json();

            if (result.success && result.data) {
                // Limpiar opciones existentes
                selectDepto.innerHTML = '<option value="">Seleccione un departamento</option>';

                // Agregar departamentos
                result.data.forEach(depto => {
                    const option = document.createElement('option');
                    option.value = depto;
                    option.textContent = depto;
                    selectDepto.appendChild(option);
                });

                // Obtener área del usuario desde el servidor
                const areaUsuario = @json($areaUsuario ?? null);

                if (areaUsuario) {
                    // Buscar departamento que coincida con el área del usuario
                    // Normalizar para comparación (mayúsculas, sin espacios extra)
                    const areaNormalizada = areaUsuario.trim().toUpperCase();
                    const deptoEncontrado = result.data.find(depto => {
                        const deptoNormalizado = depto.trim().toUpperCase();
                        return deptoNormalizado === areaNormalizada;
                    });

                    if (deptoEncontrado) {
                        selectDepto.value = deptoEncontrado;
                        // Actualizar el input hidden para compatibilidad
                        document.getElementById('depto-hidden').value = deptoEncontrado;
                        // Cargar máquinas automáticamente
                        cargarMaquinas(deptoEncontrado);
                    }
                }
            } else {
                console.error('Error al cargar departamentos:', result.error);
                selectDepto.innerHTML = '<option value="">Error al cargar departamentos</option>';
            }
        } catch (error) {
            console.error('Error al cargar departamentos:', error);
            selectDepto.innerHTML = '<option value="">Error al cargar departamentos</option>';
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
            // Para Jacquard, Itema, Karl Mayer, Smith, Tejedores, TRMA, Calidad, Desarrolladores y Supervisores, usar "Tejido" en la consulta
            const depUpper = departamento.toUpperCase().trim();
            let departamentoParaConsulta = departamento;

            if (depUpper === 'JACQUARD' || depUpper === 'ITEMA' ||
                depUpper === 'KARL MAYER' || depUpper === 'KARLMAYER' || depUpper === 'SMITH' ||
                depUpper === 'TEJEDORES' || depUpper === 'TRAMA' || depUpper === 'CALIDAD' || depUpper === 'DESARROLLADORES' || depUpper === 'SUPERVISORES') {
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
                    // Obtener valores de forma segura
                    const falla = String(item.Falla ?? item.falla ?? '').trim();
                    const descripcion = String(item.Descripcion ?? item.descripcion ?? '').trim();

                    // Solo agregar si hay una falla válida
                    if (falla) {
                        // Falla
                        const optF = document.createElement('option');
                        optF.value = falla;
                        optF.textContent = falla;
                        optF.dataset.desc = descripcion || '';
                        selectFalla.appendChild(optF);
                    }

                    // Solo agregar descripción si tiene un valor válido
                    if (descripcion && falla) {
                        const optD = document.createElement('option');
                        optD.value = descripcion;
                        optD.textContent = descripcion;
                        optD.dataset.falla = falla;
                        selectDescripcion.appendChild(optD);
                    }
                });

                // Solo habilitar si hay opciones disponibles
                if (selectFalla.options.length > 1) {
                    selectFalla.disabled = false;
                }
                if (selectDescripcion.options.length > 1) {
                    selectDescripcion.disabled = false;
                } else {
                    selectDescripcion.disabled = true;
                    selectDescripcion.innerHTML = '<option value=\"\">No hay descripciones disponibles</option>';
                }
            } else {
                console.error('Error al cargar fallas:', result.error || 'Error desconocido');
                console.error('Respuesta completa:', result);
                selectFalla.innerHTML = '<option value=\"\">Error al cargar fallas</option>';
                selectFalla.disabled = true;
                selectDescripcion.innerHTML = '<option value=\"\">Error al cargar descripciones</option>';
                selectDescripcion.disabled = true;
            }
        } catch (error) {
            console.error('Error al cargar fallas:', error);
            console.error('URL intentada:', url);
            selectFalla.innerHTML = '<option value=\"\">Error al cargar fallas</option>';
            selectFalla.disabled = true;
            selectDescripcion.innerHTML = '<option value=\"\">Error al cargar descripciones</option>';
            selectDescripcion.disabled = true;
        }
    }

    // Event listener para Tipo Falla: recargar fallas y habilitar descripción
    selectTipoFalla.addEventListener('change', function() {
        // Mantener checkbox siempre marcado
        checkboxNotificarSupervisor.checked = true;

        // Recargar fallas con el tipo de falla seleccionado
        const departamentoSeleccionado = selectDepto.value;
        if (departamentoSeleccionado && this.value) {
            // Limpiar solo selecciones de falla y descripción antes de recargar
            // NO limpiar ni deshabilitar la orden de trabajo
            selectFalla.value = '';
            selectDescripcion.value = '';
            cargarFallas(departamentoSeleccionado, this.value || null);
        } else {
            selectFalla.value = '';
            selectFalla.disabled = true;
            selectDescripcion.value = '';
            selectDescripcion.disabled = true;
            selectDescripcion.innerHTML = '<option value="">Seleccione primero un tipo de falla</option>';
            // Solo deshabilitar orden de trabajo si no hay tipo de falla seleccionado
            if (!this.value) {
                inputOrdenTrabajo.disabled = true;
            }
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

    // Sincronizar selects: elegir Descripción → selecciona su Falla y habilita orden de trabajo
    selectDescripcion.addEventListener('change', function() {
        const val = this.value;
        if (!val) {
            selectFalla.value = '';
            inputOrdenTrabajo.disabled = true;
            return;
        }
        const match = Array.from(selectFalla.options).find(o => (o.dataset?.desc ?? '') === val);
        if (match) {
            selectFalla.value = match.value;
        }
        // Habilitar orden de trabajo cuando hay descripción seleccionada
        inputOrdenTrabajo.disabled = false;
        // Solo cargar orden de trabajo sugerida si el campo está vacío
        // Si ya tiene un valor, mantenerlo
        const departamentoSeleccionado = selectDepto.value;
        const maquinaSeleccionada = selectMaquina.value;
        if (departamentoSeleccionado && maquinaSeleccionada && !inputOrdenTrabajo.value) {
            cargarOrdenTrabajo(departamentoSeleccionado, maquinaSeleccionada);
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
        // Actualizar el input hidden
        document.getElementById('depto-hidden').value = departamentoSeleccionado;

        if (departamentoSeleccionado) {
            // Habilitar máquina
            cargarMaquinas(departamentoSeleccionado);
            // Deshabilitar y limpiar campos siguientes
            selectTipoFalla.disabled = true;
            selectTipoFalla.value = '';
            selectDescripcion.disabled = true;
            selectDescripcion.value = '';
            selectDescripcion.innerHTML = '<option value="">Seleccione primero un tipo de falla</option>';
            inputOrdenTrabajo.disabled = true;
            inputOrdenTrabajo.value = '';
            selectFalla.value = '';
            selectFalla.disabled = true;
            selectFalla.innerHTML = '<option value="">Seleccione primero un departamento</option>';
        } else {
            // Si no hay departamento, deshabilitar todo
            selectMaquina.disabled = true;
            selectMaquina.innerHTML = '<option value="">Seleccione primero un departamento</option>';
            selectTipoFalla.disabled = true;
            selectDescripcion.disabled = true;
            inputOrdenTrabajo.disabled = true;
            selectFalla.disabled = true;
        }
    });

    // Event listener para cambio de máquina → habilitar tipo falla y buscar orden de trabajo sugerida
    selectMaquina.addEventListener('change', function() {
        const departamentoSeleccionado = selectDepto.value;
        const maquinaSeleccionada = this.value;

        if (maquinaSeleccionada) {
            // Habilitar tipo de falla
            selectTipoFalla.disabled = false;
            // Deshabilitar y limpiar campos siguientes
            selectTipoFalla.value = '';
            selectDescripcion.disabled = true;
            selectDescripcion.value = '';
            selectDescripcion.innerHTML = '<option value="">Seleccione primero un tipo de falla</option>';
            inputOrdenTrabajo.disabled = true;
            inputOrdenTrabajo.value = '';
            selectFalla.value = '';
            selectFalla.disabled = true;
            selectFalla.innerHTML = '<option value="">Seleccione primero un departamento</option>';
            // Cargar orden de trabajo sugerida
            cargarOrdenTrabajo(departamentoSeleccionado, maquinaSeleccionada);
        } else {
            // Si no hay máquina, deshabilitar campos siguientes
            selectTipoFalla.disabled = true;
            selectTipoFalla.value = '';
            selectDescripcion.disabled = true;
            inputOrdenTrabajo.disabled = true;
            inputOrdenTrabajo.value = '';
            selectFalla.value = '';
            selectFalla.disabled = true;
        }
    });

    // Asegurar que el checkbox siempre esté marcado
    checkboxNotificarSupervisor.checked = true;
    checkboxNotificarSupervisor.addEventListener('change', function() {
        // Si intentan desmarcarlo, volver a marcarlo
        if (!this.checked) {
            this.checked = true;
        }
    });

    // Cargar datos al iniciar
    cargarTiposFalla();
    cargarDepartamentos();

    // Submit del formulario
    let isSubmitting = false;
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Prevenir múltiples envíos
        if (isSubmitting) {
            return;
        }

        const btnAceptar = document.getElementById('btn-aceptar');
        const textoOriginal = btnAceptar.textContent;

        // Bloquear botón y cambiar texto
        isSubmitting = true;
        btnAceptar.disabled = true;
        btnAceptar.textContent = 'Enviando...';
        btnAceptar.style.cursor = 'not-allowed';
        btnAceptar.style.opacity = '0.6';

        const formData = new FormData(form);

        // Asegurar que los campos deshabilitados (fecha, hora) se incluyan
        const fechaInput = document.getElementById('fecha');
        const horaInput = document.getElementById('hora');
        if (fechaInput.value) {
            formData.set('fecha', fechaInput.value);
        }
        if (horaInput.value) {
            formData.set('hora', horaInput.value);
        }
        // Asegurar que el departamento se envíe (usar el select directamente)
        if (selectDepto.value) {
            formData.set('depto', selectDepto.value);
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
                const folio = (result.data && result.data.folio) ? result.data.folio : (result.folio || '—');
                const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                const mensajeHtml = 'Folio: <strong>' + esc(folio) + '</strong>' + (result.message ? '<br><br>' + esc(result.message) : '');
                // Mostrar mensaje de éxito con SweetAlert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reportado correctamente',
                        html: mensajeHtml,
                        timer: 6000,
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
                // Error del servidor - reabilitar botón
                isSubmitting = false;
                btnAceptar.disabled = false;
                btnAceptar.textContent = textoOriginal;
                btnAceptar.style.cursor = 'pointer';
                btnAceptar.style.opacity = '1';

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
            // Error de conexión - reabilitar botón
            isSubmitting = false;
            btnAceptar.disabled = false;
            btnAceptar.textContent = textoOriginal;
            btnAceptar.style.cursor = 'pointer';
            btnAceptar.style.opacity = '1';

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

