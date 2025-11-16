@extends('layouts.app')

@section('page-title', 'Reportar Paro')

@section('content')
<div class="w-full p-2 md:p-4 lg:p-6">
    <div class="bg-white rounded-lg shadow-lg -mt-4 border border-gray-200 p-3 md:p-4 lg:p-6 max-w-4xl mx-auto">

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

                    <!-- Falla -->
                    <div>
                        <label for="falla" class="block text-xs md:text-sm font-medium text-gray-700">Falla</label>
                        <input
                            type="text"
                            id="falla"
                            name="falla"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        >
                    </div>

                    <!-- Descrip -->
                    <div>
                        <label for="descrip" class="block text-xs md:text-sm font-medium text-gray-700">Descripción</label>
                        <textarea
                            id="descrip"
                            name="descrip"
                            rows="2"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none outline-none"
                        ></textarea>
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
            <div class="grid grid-cols-2 gap-3 md:gap-4 mt-2 md:mt-3 pt-2 md:pt-3 border-t border-gray-200">
                <button
                    type="button"
                    id="btn-cancelar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm md:text-base font-medium rounded-md transition-colors"
                    onclick="window.location.href='{{ url('/produccionProceso') }}'"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    id="btn-aceptar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm md:text-base font-medium rounded-md transition-colors"
                >
                    Aceptar
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

    // Event listener para cambio de departamento
    selectDepto.addEventListener('change', function() {
        const departamentoSeleccionado = this.value;
        cargarMaquinas(departamentoSeleccionado);
    });

    // Cargar datos al iniciar
    cargarTiposFalla();
    cargarDepartamentos();

    // Submit del formulario
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        // Agregar el checkbox
        data.notificar_supervisor = document.getElementById('notificar_supervisor').checked;

        try {
            // Aquí puedes agregar la lógica para enviar los datos al servidor
            console.log('Datos del formulario:', data);

            // Mostrar mensaje de éxito
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Paro reportado',
                    text: 'El paro de máquina ha sido reportado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '{{ url('/produccionProceso') }}';
                });
            } else {
                alert('Paro reportado correctamente');
                window.location.href = '{{ url('/produccionProceso') }}';
            }
        } catch (error) {
            console.error('Error al reportar paro:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al reportar el paro. Por favor, intenta nuevamente.'
                });
            } else {
                alert('Error al reportar el paro');
            }
        }
    });
});
</script>
@endsection

