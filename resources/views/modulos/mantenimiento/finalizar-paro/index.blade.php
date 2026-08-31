@extends('layouts.app')

@section('page-title', 'Finalizar Paro')

@section('content')
<style>
    /* Estrellas de Calidad: son <input type="radio"> reales, sólo repintados.
       Se resuelve en CSS porque hay que reaccionar al estado marcado/foco de un
       hermano, y las variantes de Tailwind no alcanzan a los hijos del <label>. */
    /* El <fieldset> trae min-inline-size: min-content y ensancharía la columna en
       pantallas angostas; se neutraliza para conservar el ancho que tenía el div. */
    #calidad-fieldset {
        min-inline-size: 0;
    }

    .calidad-estrellas {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
        align-items: center;
        padding: 0.25rem;
        border: 2px solid transparent;
        border-radius: 0.375rem;
    }

    /* El radio y el texto del label se ocultan a la vista, pero siguen siendo
       enfocables y anunciables por el lector de pantalla ("3 de 5"). */
    .calidad-estrellas input[type="radio"],
    .calidad-estrellas label > span {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0 0 0 0);
        clip-path: inset(50%);
        white-space: nowrap;
        border: 0;
    }

    /* Estrella vacía: contorno en gris oscuro (#4b5563 = 7.56:1 sobre blanco). */
    .calidad-estrellas label {
        line-height: 1;
        color: #4b5563;
        border-radius: 0.375rem;
        -webkit-user-select: none;
        user-select: none;
    }

    /* El "/ """ es el texto alternativo del contenido generado: sin él, Chrome lo
       suma al nombre accesible y el lector anuncia "estrella blanca 3 de 5". */
    .calidad-estrellas label::before {
        content: "\2606" / "";
    }

    /* Estrella llena: sólida y ámbar oscuro (#b45309 = 5.02:1 sobre blanco).
       Llena y vacía se distinguen por forma (sólida vs contorno) además de por
       color, para que se lean bajo el reflejo de la tablet. */
    .calidad-estrellas input[type="radio"]:checked ~ label,
    .calidad-estrellas label:hover,
    .calidad-estrellas label:hover ~ label {
        color: #b45309;
    }

    .calidad-estrellas input[type="radio"]:checked ~ label::before,
    .calidad-estrellas label:hover::before,
    .calidad-estrellas label:hover ~ label::before {
        content: "\2605" / "";
    }

    /* Foco de teclado sobre la estrella enfocada (#1d4ed8 = 6.70:1 sobre blanco).
       Se incluye :focus además de :focus-visible porque el foco también se mueve por
       código al fallar la validación, y ahí Chrome puede no aplicar :focus-visible:
       como el radio está oculto, el operador se quedaría sin ninguna marca visible. */
    .calidad-estrellas input[type="radio"]:focus + label,
    .calidad-estrellas input[type="radio"]:focus-visible + label {
        outline: 3px solid #1d4ed8;
        outline-offset: 2px;
    }

    /* Marca de error al intentar finalizar sin calificar (#dc2626 = 4.83:1). */
    .calidad-estrellas.calidad-invalida {
        border-color: #dc2626;
        background-color: #fef2f2;
    }
</style>
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
                        readonly
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
                        readonly
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
                        readonly
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
                        readonly
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
                        readonly
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
                        readonly
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
                        readonly
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
                        readonly
                    >
                </div>

                <!-- Atendio (Columna 1) -->
                <div>
                    <label for="atendio" class="block text-xs md:text-sm font-medium text-gray-700">
                        Atendio <span class="text-red-600">*</span>
                    </label>
                    <select
                        id="atendio"
                        name="atendio"
                        required
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 mt-1 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none mb-1"
                    >
                        <option value="">Seleccione un operador</option>
                        <!-- Los operadores se cargarán dinámicamente -->
                    </select>
                </div>

                <!-- Calidad (Ocupa 2 columnas: 2 y 3, misma fila que Atendio) -->
                <div class="md:col-span-2">
                    <fieldset id="calidad-fieldset" aria-describedby="calidad-ayuda">
                        <legend class="block text-sm md:text-md font-medium text-gray-700">
                            Calidad (1-5) <span class="text-red-600">*</span>
                        </legend>
                        <p id="calidad-ayuda" class="text-xs md:text-sm text-gray-700">
                            Califique la atención que le dio el personal de mantenimiento que resolvió el paro.
                        </p>
                        <!-- Radios reales para que el campo sea operable con teclado (WCAG 2.1.1).
                             Van en orden inverso (5 → 1) porque el CSS los voltea con row-reverse:
                             así se ven de 1 a 5 y el combinador de hermanos (~) puede pintar la
                             estrella marcada junto con todas las menores. -->
                        <div id="calidad-stars" class="calidad-estrellas gap-8 md:gap-12 w-full ml-2 md:ml-4">
                            <input type="radio" id="calidad-5" name="calidad" value="5">
                            <label for="calidad-5" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>5 de 5</span></label>

                            <input type="radio" id="calidad-4" name="calidad" value="4">
                            <label for="calidad-4" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>4 de 5</span></label>

                            <input type="radio" id="calidad-3" name="calidad" value="3">
                            <label for="calidad-3" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>3 de 5</span></label>

                            <input type="radio" id="calidad-2" name="calidad" value="2">
                            <label for="calidad-2" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>2 de 5</span></label>

                            <input type="radio" id="calidad-1" name="calidad" value="1">
                            <label for="calidad-1" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>1 de 5</span></label>
                        </div>
                        <!-- Sólo apoyo visual: el lector de pantalla ya anuncia el radio marcado. -->
                        <span id="calidad-value" class="text-xs text-gray-500 ml-2" aria-hidden="true">0/5</span>
                    </fieldset>
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
            <p class="mt-3 md:mt-4 text-sm md:text-base text-gray-700">
                <i class="fa-solid fa-paper-plane mr-1" aria-hidden="true"></i>
                Al finalizar se enviará la notificación a Telegram.
            </p>
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

    // El id viaja en la URL para que la pantalla sobreviva a un F5. Antes venía por
    // localStorage y se borraba al leerlo, así que recargar echaba al operador fuera.
    const paroId = new URLSearchParams(window.location.search).get('id');

    if (paroId) {
        paroIdInput.value = paroId;
        cargarDatosParo(paroId);
    } else {
        // Redirigir sin mostrar mensaje si no hay paro seleccionado
        window.location.href = '{{ route('mantenimiento.solicitudes') }}';
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

    // Calidad: el pintado de las estrellas lo resuelve el CSS a partir del radio
    // marcado. Aquí sólo queda leer el valor, prellenarlo y marcar el error.
    const maxCalidad = 5;
    const grupoCalidad = document.getElementById('calidad-stars');
    const fieldsetCalidad = document.getElementById('calidad-fieldset');
    const calidadValueSpan = document.getElementById('calidad-value');
    const radiosCalidad = Array.from(document.querySelectorAll('input[name="calidad"]'));

    function calidadSeleccionada() {
        const marcado = radiosCalidad.find(radio => radio.checked);

        return marcado ? parseInt(marcado.value, 10) : 0;
    }

    function actualizarContadorCalidad() {
        calidadValueSpan.textContent = `${calidadSeleccionada()}/${maxCalidad}`;
    }

    function marcarCalidadInvalida(invalida) {
        grupoCalidad.classList.toggle('calidad-invalida', invalida);

        if (invalida) {
            fieldsetCalidad.setAttribute('aria-invalid', 'true');
        } else {
            fieldsetCalidad.removeAttribute('aria-invalid');
        }
    }

    // Al calificar se limpia la marca de error y se actualiza el contador visible.
    radiosCalidad.forEach(radio => {
        radio.addEventListener('change', function() {
            actualizarContadorCalidad();
            marcarCalidadInvalida(false);
        });
    });

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

                // Rellenar otros campos informativos (readonly) desde los datos del paro
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
                    // Marcar el radio correspondiente: el CSS pinta las estrellas solo.
                    const calidad = Math.min(Math.max(parseInt(paro.Calidad, 10) || 0, 0), maxCalidad);
                    const radioCalidad = radiosCalidad.find(radio => parseInt(radio.value, 10) === calidad);

                    if (radioCalidad) {
                        radioCalidad.checked = true;
                    }

                    actualizarContadorCalidad();
                }
                if (paro.ObsCierre) {
                    document.getElementById('obs_cierre').value = paro.ObsCierre;
                }
            } else {
                alert('Error al cargar los datos del paro');
                window.location.href = '{{ route('mantenimiento.solicitudes') }}';
            }
        } catch (error) {
            console.error('Error al cargar datos del paro:', error);
            alert('Error de conexión. Por favor, intenta nuevamente.');
        }
    }

    // Cargar operadores al iniciar
    cargarOperadores();

    // Botón Cancelar
    document.getElementById('btn-cancelar').addEventListener('click', function() {
        window.location.href = '{{ route('mantenimiento.solicitudes') }}';
    });

    // Submit del formulario
    let isSubmitting = false;
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Prevenir múltiples envíos: dos PUT seguidos disparan dos notificaciones
        // de Telegram, porque el aviso sale en un defer() del servidor.
        if (isSubmitting) {
            return;
        }

        const atendioValue = document.getElementById('atendio').value.trim();
        const calidadValue = calidadSeleccionada();

        if (!atendioValue) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo requerido',
                    text: 'Debe seleccionar quién atendió el paro.'
                });
            } else {
                alert('Debe seleccionar quién atendió el paro.');
            }
            document.getElementById('atendio').focus();
            return;
        }

        if (calidadValue < 1 || calidadValue > maxCalidad) {
            // Marcar el grupo como inválido y llevar el foco a la primera estrella,
            // igual que se hace con "Atendio".
            marcarCalidadInvalida(true);
            const primeraEstrella = document.getElementById('calidad-1');
            const mensajeCalidad = 'Debe seleccionar una calificación entre 1 y ' + maxCalidad + '.';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo requerido',
                    text: mensajeCalidad
                }).then(() => {
                    primeraEstrella.focus();
                });
            } else {
                alert(mensajeCalidad);
                primeraEstrella.focus();
            }
            return;
        }

        const formData = new FormData(form);

        // Bloquear el botón para que un doble toque no mande dos veces el cierre.
        const btnAceptar = document.getElementById('btn-aceptar');
        const textoOriginal = btnAceptar.textContent;

        function restaurarBotonFinalizar() {
            isSubmitting = false;
            btnAceptar.disabled = false;
            btnAceptar.textContent = textoOriginal;
            btnAceptar.style.cursor = 'pointer';
            btnAceptar.style.opacity = '1';
        }

        isSubmitting = true;
        btnAceptar.disabled = true;
        btnAceptar.textContent = 'Enviando...';
        btnAceptar.style.cursor = 'not-allowed';
        btnAceptar.style.opacity = '0.6';

        try {
            const response = await fetch(`{{ url('/api/mantenimiento/paros') }}/${paroIdInput.value}/finalizar`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                // El payload se arma campo por campo: los informativos (fecha, hora,
                // depto, máquina, falla, etc.) siguen sin viajar al servidor aunque
                // ahora sean readonly y aparezcan en el FormData.
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
                        // Volver a solicitudes, igual que Cancelar: quien cierra varios
                        // paros seguidos se queda en la misma lista.
                        window.location.href = '{{ route('mantenimiento.solicitudes') }}';
                    });
                } else {
                    alert(result.message || 'Paro finalizado correctamente');
                    window.location.href = '{{ route('mantenimiento.solicitudes') }}';
                }
            } else {
                // Error del servidor - reabilitar botón
                restaurarBotonFinalizar();

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
            // Error de conexión - reabilitar botón
            restaurarBotonFinalizar();

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

