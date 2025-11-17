@extends('layouts.app')

@section('page-title', 'Finalizar Paro')

@section('content')
<div class="w-full p-3 md:p-6 lg:p-8">
    <div class="bg-white rounded-lg shadow-lg border border-gray-200 p-4 md:p-6 lg:p-8 -mt-3 max-w-5xl mx-auto">

        <!-- Formulario -->
        <form id="form-finalizar-paro">
            @csrf
            <input type="hidden" id="paro_id" name="paro_id">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-3">
                <!-- Fecha Fin (Columna 1) -->
                <div>
                    <label for="fecha" class="block text-xs md:text-sm font-medium text-gray-700">
                        Fecha Cierre
                    </label>
                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-blue-50 text-blue-700 cursor-not-allowed"
                        value="{{ date('Y-m-d') }}"
                        disabled
                    >
                </div>

                <!-- Hora (Columna 2) -->
                <div>
                    <label for="hora" class="block text-xs md:text-sm font-medium text-gray-700">
                        Hora Cierre
                    </label>
                    <input
                        type="time"
                        id="hora"
                        name="hora"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-blue-50 text-blue-700 cursor-not-allowed"
                        value="{{ date('H:i') }}"
                        disabled
                    >
                </div>

                <!-- Depto (Columna 3) -->
                <div>
                    <label for="depto" class="block text-xs md:text-sm font-medium text-gray-700">
                        Departamento
                    </label>
                    <input
                        type="text"
                        id="depto"
                        name="depto"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        disabled
                    >
                </div>

                <!-- Maquina (Columna 1) -->
                <div>
                    <label for="maquina" class="block text-xs md:text-sm font-medium text-gray-700">
                        Máquina
                    </label>
                    <input
                        type="text"
                        id="maquina"
                        name="maquina"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        disabled
                    >
                </div>

                <!-- Tipo Falla (Columna 2) -->
                <div>
                    <label for="tipo_falla" class="block text-xs md:text-sm font-medium text-gray-700">
                        Tipo Falla
                    </label>
                    <input
                        type="text"
                        id="tipo_falla"
                        name="tipo_falla"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        disabled
                    >
                </div>

                <!-- Falla (Columna 3) -->
                <div>
                    <label for="falla" class="block text-xs md:text-sm font-medium text-gray-700">
                        Falla
                    </label>
                    <input
                        type="text"
                        id="falla"
                        name="falla"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        disabled
                    >
                </div>

                <!-- Descripcion (Ocupa 2 columnas: 1 y 2) -->
                <div class="md:col-span-2">
                    <label for="descrip" class="block text-xs md:text-sm font-medium text-gray-700">
                        Descripción
                    </label>
                    <input
                        type="text"
                        id="descrip"
                        name="descrip"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        disabled
                    >
                </div>

                <!-- Orden de Trabajo (Columna 3) -->
                <div>
                    <label for="orden_trabajo" class="block text-xs md:text-sm font-medium text-gray-700">
                        Orden de Trabajo
                    </label>
                    <input
                        type="text"
                        id="orden_trabajo"
                        name="orden_trabajo"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        disabled
                    >
                </div>

                <!-- Atendio (Columna 1) -->
                <div>
                    <label for="atendio" class="block text-xs md:text-sm font-medium text-gray-700">Atendio</label>
                    <select
                        id="atendio"
                        name="atendio"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 mt-1 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none mb-1"
                    >
                        <option value="">Seleccione un operador</option>
                        <!-- Los operadores se cargarán dinámicamente -->
                    </select>
                </div>

                <!-- Calidad (Ocupa 2 columnas: 2 y 3, misma fila que Atendio) -->
                <div class="md:col-span-2">
                    <label class="block text-sm md:text-md font-medium text-gray-700 mb-2">Calidad (1-10)</label>
                    <div class="flex items-center justify-start gap-5 md:gap-7 w-full ml-4" id="calidad-stars">
                        <!-- Las estrellas se generarán dinámicamente -->
                    </div>
                    <input
                        type="hidden"
                        id="calidad"
                        name="calidad"
                        value=""
                    >
                    <span id="calidad-value" class="text-xs text-gray-500 ml-2">0/10</span>
                </div>

                <!-- Turno (Oculto - se guardará pero no se mostrará) -->
                <input
                    type="hidden"
                    id="turno"
                    name="turno"
                    value=""
                >
            </div>

            <!-- Obs - ObsCierre - Ancho completo -->
            <div >
                <label for="obs_cierre" class="block text-xs md:text-sm font-medium text-gray-700">Observaciones</label>
                <textarea
                    id="obs_cierre"
                    name="obs_cierre"
                    rows="3"
                    class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none outline-none"
                    placeholder="Observaciones de cierre"
                ></textarea>
            </div>

            <!-- Botones -->
            <div class="grid grid-cols-2 gap-3 md:gap-4 mt-4 md:mt-6 pt-3 md:pt-4 border-t border-gray-200">
                <button
                    type="button"
                    id="btn-cancelar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm md:text-base font-medium rounded-md transition-colors"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    id="btn-aceptar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm md:text-base font-medium rounded-md transition-colors"
                >
                    Finalizar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-finalizar-paro');
    const paroIdInput = document.getElementById('paro_id');

    // Ocultar botón de "Paro" en la barra de navegación
    try {
        const paroLinks = document.querySelectorAll('a[href*="/mantenimiento/nuevo-paro"]');
        paroLinks.forEach(el => {
            el.style.display = 'none';
        });
    } catch (e) {
        console.warn('No se pudo ocultar el botón de Paro en la barra de navegación:', e);
    }

    // Obtener ID del paro desde la URL o parámetro
    const urlParams = new URLSearchParams(window.location.search);
    const paroId = urlParams.get('id');

    if (paroId) {
        paroIdInput.value = paroId;
        // Cargar datos del paro
        cargarDatosParo(paroId);
    } else {
        // Si no hay ID, obtenerlo del localStorage (si viene de la tabla de reportes)
        const selectedParoId = localStorage.getItem('selectedParoId');
        if (selectedParoId) {
            paroIdInput.value = selectedParoId;
            cargarDatosParo(selectedParoId);
            localStorage.removeItem('selectedParoId');
        } else {
            // Redirigir sin mostrar mensaje si no hay paro seleccionado
            window.location.href = '{{ route('mantenimiento.reporte-fallos-paros') }}';
        }
    }

    // Cargar operadores de mantenimiento
    async function cargarOperadores() {
        try {
            const response = await fetch('{{ route('api.mantenimiento.operadores') }}');
            const result = await response.json();

            if (result.success && Array.isArray(result.data)) {
                const selectAtendio = document.getElementById('atendio');

                // Limpiar opciones excepto la primera
                selectAtendio.innerHTML = '<option value="">Seleccione un operador</option>';

                // Agregar operadores al select
                result.data.forEach(operador => {
                    const option = document.createElement('option');
                    option.value = operador.NomEmpl || ''; // Usar NomEmpl como valor
                    option.textContent = operador.NomEmpl || '';
                    // Almacenar el turno en un atributo data para usarlo después
                    if (operador.Turno) {
                        option.dataset.turno = operador.Turno;
                    }
                    selectAtendio.appendChild(option);
                });

                // Event listener para rellenar automáticamente el turno cuando se selecciona un operador
                selectAtendio.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const turnoInput = document.getElementById('turno');

                    if (selectedOption && selectedOption.dataset.turno) {
                        turnoInput.value = selectedOption.dataset.turno;
                    } else {
                        turnoInput.value = '';
                    }
                });
            }
        } catch (error) {
            console.error('Error al cargar operadores:', error);
        }
    }

    // Inicializar sistema de estrellas para Calidad
    let calidadSeleccionadaGlobal = 0;

    function inicializarEstrellas() {
        const starsContainer = document.getElementById('calidad-stars');
        const calidadInput = document.getElementById('calidad');
        const calidadValueSpan = document.getElementById('calidad-value');

        function actualizarEstrellas(valor = calidadSeleccionadaGlobal) {
            const stars = starsContainer.querySelectorAll('span');
            stars.forEach((star, index) => {
                const starValue = index + 1;
                if (starValue <= valor) {
                    star.classList.remove('text-gray-300');
                    star.classList.add('text-yellow-400');
                } else {
                    star.classList.remove('text-yellow-400');
                    star.classList.add('text-gray-300');
                }
            });
        }

        // Crear 10 estrellas
        for (let i = 1; i <= 10; i++) {
            const star = document.createElement('span');
            star.className = 'text-3xl md:text-4xl cursor-pointer text-gray-300 hover:text-yellow-400 transition-colors flex-shrink-0';
            star.innerHTML = '★';
            star.dataset.value = i;

            star.addEventListener('click', function() {
                calidadSeleccionadaGlobal = parseInt(this.dataset.value);
                calidadInput.value = calidadSeleccionadaGlobal;
                actualizarEstrellas();
                calidadValueSpan.textContent = `${calidadSeleccionadaGlobal}/10`;
            });

            star.addEventListener('mouseenter', function() {
                const hoverValue = parseInt(this.dataset.value);
                actualizarEstrellas(hoverValue);
            });

            starsContainer.appendChild(star);
        }

        starsContainer.addEventListener('mouseleave', function() {
            actualizarEstrellas(calidadSeleccionadaGlobal);
        });
    }

    // Cargar datos del paro
    async function cargarDatosParo(paroId) {
        try {
            const response = await fetch(`{{ url('/api/mantenimiento/paros') }}/${paroId}`);
            const result = await response.json();

            if (result.success && result.data) {
                const paro = result.data;

                // Fecha Fin y Hora son automáticas (fecha y hora actuales)
                // No se cargan desde los datos del paro
                const ahora = new Date();
                const fechaActual = ahora.toISOString().split('T')[0];
                const horaActual = ahora.toTimeString().slice(0, 5);
                document.getElementById('fecha').value = fechaActual;
                document.getElementById('hora').value = horaActual;

                // Rellenar otros campos automáticos (deshabilitados) desde los datos del paro
                document.getElementById('depto').value = paro.Depto || '';
                document.getElementById('maquina').value = paro.MaquinaId || '';
                document.getElementById('tipo_falla').value = paro.TipoFallaId || '';
                document.getElementById('falla').value = paro.Falla || '';
                document.getElementById('descrip').value = paro.Descripcion || '';
                document.getElementById('orden_trabajo').value = paro.OrdenTrabajo || '';

                // Si ya tiene datos de finalización, prellenar
                if (paro.NomAtendio) {
                    document.getElementById('atendio').value = paro.NomAtendio;
                }
                if (paro.TurnoAtendio) {
                    document.getElementById('turno').value = paro.TurnoAtendio;
                }
                if (paro.Calidad !== null && paro.Calidad !== undefined) {
                    const calidad = parseInt(paro.Calidad);
                    calidadSeleccionadaGlobal = calidad;
                    document.getElementById('calidad').value = calidad;
                    // Actualizar estrellas visualmente
                    const stars = document.querySelectorAll('#calidad-stars span');
                    stars.forEach((star, index) => {
                        const starValue = index + 1;
                        if (starValue <= calidad) {
                            star.classList.remove('text-gray-300');
                            star.classList.add('text-yellow-400');
                        } else {
                            star.classList.remove('text-yellow-400');
                            star.classList.add('text-gray-300');
                        }
                    });
                    document.getElementById('calidad-value').textContent = `${calidad}/10`;
                }
                if (paro.ObsCierre) {
                    document.getElementById('obs_cierre').value = paro.ObsCierre;
                }
            } else {
                alert('Error al cargar los datos del paro');
                window.location.href = '{{ route('mantenimiento.reporte-fallos-paros') }}';
            }
        } catch (error) {
            console.error('Error al cargar datos del paro:', error);
            alert('Error de conexión. Por favor, intenta nuevamente.');
        }
    }

    // Cargar operadores al iniciar
    cargarOperadores();

    // Inicializar estrellas al cargar
    inicializarEstrellas();

    // Botón Cancelar
    document.getElementById('btn-cancelar').addEventListener('click', function() {
        window.location.href = '{{ route('mantenimiento.reporte-fallos-paros') }}';
    });

    // Submit del formulario
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);

        try {
            const response = await fetch(`{{ url('/api/mantenimiento/paros') }}/${paroIdInput.value}/finalizar`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    atendio: formData.get('atendio'),
                    turno: formData.get('turno'),
                    calidad: formData.get('calidad') ? parseInt(formData.get('calidad')) : null,
                    obs_cierre: formData.get('obs_cierre'),
                })
            });

            const result = await response.json();

            if (result.success) {
                // Mostrar mensaje de éxito con SweetAlert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Paro finalizado',
                        text: result.message || 'El paro ha sido finalizado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '{{ route('mantenimiento.reporte-fallos-paros') }}';
                    });
                } else {
                    alert(result.message || 'Paro finalizado correctamente');
                    window.location.href = '{{ route('mantenimiento.reporte-fallos-paros') }}';
                }
            } else {
                const errorMsg = result.error || 'Error al finalizar el paro. Por favor, intenta nuevamente.';
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
            console.error('Error al finalizar paro:', error);
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

