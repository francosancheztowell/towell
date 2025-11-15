@extends('layouts.app')

@section('page-title', 'Reportar Paro de Maquina')

@section('content')
<div class="w-full p-2 md:p-4 lg:p-6">
    <div class="bg-white rounded-lg shadow-lg border border-gray-200 p-3 md:p-4 lg:p-6 max-w-4xl mx-auto">

        <!-- Formulario -->
        <form id="form-paro">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4 lg:gap-6">
                <!-- Columna Izquierda -->
                <div class="space-y-2 md:space-y-3">
                    <!-- Fecha -->
                    <div>
                        <label for="fecha" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Fecha</label>
                        <input
                            type="date"
                            id="fecha"
                            name="fecha"
                            class="w-full px-3 py-2 md:px-4 md:py-2.5 text-sm md:text-base border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            value="{{ date('Y-m-d') }}"
                            required
                        >
                    </div>

                    <!-- Depto -->
                    <div>
                        <label for="depto" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Depto</label>
                        <input
                            type="text"
                            id="depto"
                            name="depto"
                            class="w-full px-3 py-2 md:px-4 md:py-2.5 text-sm md:text-base border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>

                    <!-- Tipo Falla -->
                    <div>
                        <label for="tipo_falla" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Tipo Falla</label>
                        <input
                            type="text"
                            id="tipo_falla"
                            name="tipo_falla"
                            class="w-full px-3 py-2 md:px-4 md:py-2.5 text-sm md:text-base border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>

                    <!-- Descrip -->
                    <div>
                        <label for="descrip" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Descrip</label>
                        <textarea
                            id="descrip"
                            name="descrip"
                            rows="2"
                            class="w-full px-3 py-2 md:px-4 md:py-2.5 text-sm md:text-base border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                        ></textarea>
                    </div>

                    <!-- Hora Inicio -->
                    <div>
                        <label for="hora_inicio" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Hora Inicio</label>
                        <input
                            type="time"
                            id="hora_inicio"
                            name="hora_inicio"
                            class="w-full px-3 py-2 md:px-4 md:py-2.5 text-sm md:text-base border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>

                    <!-- Obs -->
                    <div>
                        <label for="obs" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Obs</label>
                        <textarea
                            id="obs"
                            name="obs"
                            rows="2"
                            class="w-full px-3 py-2 md:px-4 md:py-2.5 text-sm md:text-base border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                        ></textarea>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="space-y-2 md:space-y-3">
                    <!-- Hora -->
                    <div>
                        <label for="hora" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Hora</label>
                        <input
                            type="time"
                            id="hora"
                            name="hora"
                            class="w-full px-3 py-2 md:px-4 md:py-2.5 text-sm md:text-base border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>

                    <!-- Maquina -->
                    <div>
                        <label for="maquina" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Maquina</label>
                        <input
                            type="text"
                            id="maquina"
                            name="maquina"
                            class="w-full px-3 py-2 md:px-4 md:py-2.5 text-sm md:text-base border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>

                    <!-- Falla -->
                    <div>
                        <label for="falla" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Falla</label>
                        <input
                            type="text"
                            id="falla"
                            name="falla"
                            class="w-full px-3 py-2 md:px-4 md:py-2.5 text-sm md:text-base border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>
                </div>
            </div>

            <!-- Notificar a Supervisor -->
            <div class="flex items-center gap-2 mt-4 md:mt-5 lg:mt-6">
                <input
                    type="checkbox"
                    id="notificar_supervisor"
                    name="notificar_supervisor"
                    class="w-4 h-4 md:w-5 md:h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                >
                <label for="notificar_supervisor" class="text-sm md:text-base font-medium text-gray-700">
                    Notificar a Supervisor
                </label>
                <span class="text-sm md:text-base text-gray-500">(Mensaje Telegram)</span>
            </div>

            <!-- Botones -->
            <div class="grid grid-cols-2 gap-3 md:gap-4 mt-4 md:mt-5 lg:mt-6 pt-3 md:pt-4 border-t border-gray-200">
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

