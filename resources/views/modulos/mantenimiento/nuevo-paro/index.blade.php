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
                    <!-- Fecha (informativa: el servidor la estampa al guardar) -->
                    <div>
                        <label for="fecha" class="block text-xs md:text-sm font-medium text-gray-700">Fecha</label>
                        <input
                            type="date"
                            id="fecha"
                            name="fecha"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                            value="{{ date('Y-m-d') }}"
                            readonly
                            aria-describedby="ayuda-fecha-hora"
                        >
                        <p id="ayuda-fecha-hora" class="text-xs md:text-sm text-gray-700">
                            La fecha y la hora las registra el sistema al guardar.
                        </p>
                    </div>

                    <!-- Depto -->
                    <div>
                        <label for="depto" class="block text-xs md:text-sm font-medium text-gray-700">Departamento</label>
                        <select
                            id="depto"
                            name="depto"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            aria-describedby="error-depto"
                            required
                        >
                            <option value="">Cargando...</option>
                        </select>
                        <p id="error-depto" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
                    </div>

                    <!-- Tipo Falla -->
                    <div>
                        <label for="tipo_falla" class="block text-xs md:text-sm font-medium text-gray-700">Tipo Falla</label>
                        <p id="ayuda-tipo-falla" class="text-xs md:text-sm text-gray-700">
                            Seleccione primero una máquina para habilitar este campo.
                        </p>
                        <select
                            id="tipo_falla"
                            name="tipo_falla"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            aria-describedby="ayuda-tipo-falla error-tipo-falla"
                            disabled
                        >
                            <option value="">Seleccione primero una máquina</option>
                        </select>
                        <p id="error-tipo-falla" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
                    </div>

                    <!-- Orden de Trabajo -->
                    <div>
                        <label for="orden_trabajo" class="block text-xs md:text-sm font-medium text-gray-700">Orden de Trabajo</label>
                        <p id="ayuda-orden-trabajo" class="text-xs md:text-sm text-gray-700">
                            Se sugiere sola al elegir la máquina. Puede escribirla o corregirla a mano.
                        </p>
                        <input
                            type="text"
                            id="orden_trabajo"
                            name="orden_trabajo"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            aria-describedby="ayuda-orden-trabajo"
                        >
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="space-y-2 md:space-y-2">
                    <!-- Hora (informativa: el servidor la estampa al guardar) -->
                    <div>
                        <label for="hora" class="block text-xs md:text-sm font-medium text-gray-700">Hora</label>
                        <input
                            type="time"
                            id="hora"
                            name="hora"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                            value="{{ date('H:i') }}"
                            readonly
                            aria-describedby="ayuda-fecha-hora"
                        >
                    </div>

                    <!-- Maquina -->
                    <div>
                        <label for="maquina" class="block text-xs md:text-sm font-medium text-gray-700">Maquina</label>
                        <p id="ayuda-maquina" class="text-xs md:text-sm text-gray-700">
                            Seleccione primero un departamento para habilitar este campo.
                        </p>
                        <select
                            id="maquina"
                            name="maquina"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            aria-describedby="ayuda-maquina error-maquina"
                            disabled
                        >
                            <option value="">Seleccione primero un departamento</option>
                        </select>
                        <p id="error-maquina" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label for="descripcion" class="block text-xs md:text-sm font-medium text-gray-700">Descripción</label>
                        <p id="ayuda-descripcion" class="text-xs md:text-sm text-gray-700">
                            Seleccione primero un tipo de falla para habilitar este campo.
                        </p>
                        <select
                            id="descripcion"
                            name="descrip"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            aria-describedby="ayuda-descripcion error-descripcion"
                            disabled
                        >
                            <option value="">Seleccione primero un tipo de falla</option>
                        </select>
                        <p id="error-descripcion" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
                    </div>

                    <!-- Falla -->
                    <div>
                        <label for="falla" class="block text-xs md:text-sm font-medium text-gray-700">Falla</label>
                        <p id="ayuda-falla" class="text-xs md:text-sm text-gray-700">
                            Seleccione primero un tipo de falla para habilitar este campo.
                        </p>
                        <select
                            id="falla"
                            name="falla"
                            class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            aria-describedby="ayuda-falla error-falla"
                            disabled
                        >
                            <option value="">Seleccione primero un tipo de falla</option>
                        </select>
                        <p id="error-falla" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
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

            <!-- Botones -->
            <div class="grid grid-cols-2 gap-3 md:gap-4 mt-2 md:mt-3 pt-2 md:pt-3 border-t border-gray-200">
                <button
                    type="button"
                    id="btn-cancelar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-white hover:bg-gray-50 text-gray-700 text-sm md:text-base font-medium rounded-md transition-colors border-2 border-gray-300"
                    onclick="window.location.href='{{ route('mantenimiento.solicitudes') }}'"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    id="btn-aceptar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-blue-600 hover:bg-blue-700 text-white text-base md:text-lg font-semibold rounded-md transition-colors"
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
    const inputFecha = document.getElementById('fecha');
    const inputHora = document.getElementById('hora');

    // Regiones aria-live: los errores de carga se anuncian y se ven bajo el campo,
    // en vez de esconderse dentro de un <option> que sólo aparece al abrir el combo.
    const regionesError = {
        depto: document.getElementById('error-depto'),
        maquina: document.getElementById('error-maquina'),
        tipo_falla: document.getElementById('error-tipo-falla'),
        falla: document.getElementById('error-falla'),
        descripcion: document.getElementById('error-descripcion'),
    };

    // Textos de ayuda visibles: dicen qué falta para habilitar cada campo bloqueado.
    // Se ocultan en cuanto el campo se habilita, mientras carga o si hay un error.
    const camposCascada = [
        { campo: 'maquina', control: selectMaquina, ayuda: document.getElementById('ayuda-maquina') },
        { campo: 'tipo_falla', control: selectTipoFalla, ayuda: document.getElementById('ayuda-tipo-falla') },
        { campo: 'falla', control: selectFalla, ayuda: document.getElementById('ayuda-falla') },
        { campo: 'descripcion', control: selectDescripcion, ayuda: document.getElementById('ayuda-descripcion') },
        // orden_trabajo no entra: nunca se bloquea, así que su ayuda es fija.
    ];

    // La orden de trabajo se puede capturar a mano en cualquier momento. En cuanto el
    // operador escribe algo, la sugerencia automática deja de pisarle el valor; si la
    // borra, vuelve a sugerirse sola.
    let ordenTrabajoManual = false;
    inputOrdenTrabajo.addEventListener('input', function () {
        ordenTrabajoManual = this.value.trim() !== '';
    });

    /** Borra la orden sólo si la había puesto la sugerencia automática. */
    function limpiarOrdenTrabajoSugerida() {
        if (!ordenTrabajoManual) {
            inputOrdenTrabajo.value = '';
        }
    }

    function mostrarErrorCarga(campo, mensaje) {
        const region = regionesError[campo];
        if (region) {
            region.textContent = mensaje;
        }
    }

    function limpiarErrorCarga(campo) {
        const region = regionesError[campo];
        if (region) {
            region.textContent = '';
        }
    }

    function refrescarAyudas() {
        camposCascada.forEach(({ campo, control, ayuda }) => {
            if (!control || !ayuda) {
                return;
            }

            const region = regionesError[campo];
            const hayError = !!(region && region.textContent.trim() !== '');
            const estaCargando = control.dataset.cargando === '1';

            ayuda.classList.toggle('hidden', !control.disabled || hayError || estaCargando);
        });
    }

    // Indicador de carga en los combos de la cascada: mientras la petición está en
    // vuelo el select queda deshabilitado con "Cargando..." (mismo patrón que el
    // combo de Departamento). Antes se quedaba con el texto anterior y en la red de
    // la planta el operador creía que el combo estaba vacío.
    function marcarCargando(select) {
        select.dataset.cargando = '1';
        select.disabled = true;
        select.innerHTML = '<option value="">Cargando...</option>';
    }

    function terminarCarga(select) {
        delete select.dataset.cargando;
    }

    // Cualquier cambio en la cascada puede habilitar/bloquear campos: el listener a
    // nivel de formulario corre después del handler del propio campo.
    form.addEventListener('change', refrescarAyudas);

    // Fecha y hora son sólo informativas: el servidor las estampa al guardar.
    // La pantalla puede quedar abierta horas, así que el reloj se mantiene al día
    // para que lo que se ve coincida con lo que se va a guardar.
    function actualizarRelojLocal() {
        const ahora = new Date();
        const dosDigitos = n => String(n).padStart(2, '0');
        inputFecha.value = `${ahora.getFullYear()}-${dosDigitos(ahora.getMonth() + 1)}-${dosDigitos(ahora.getDate())}`;
        inputHora.value = `${dosDigitos(ahora.getHours())}:${dosDigitos(ahora.getMinutes())}`;
    }
    actualizarRelojLocal();
    setInterval(actualizarRelojLocal, 30000);

    // Ocultar botón/enlace de "Paro" en la barra de navegación solo en esta pantalla
    try {
        const paroLinks = document.querySelectorAll('a[href*="/mantenimiento/nuevo-paro"]');
        paroLinks.forEach(el => {
            el.style.display = 'none';
        });
    } catch (e) {
        console.warn('No se pudo ocultar el botón de Paro en la barra de navegación:', e);
    }

    // Cargar tipos de falla del departamento elegido: los tipos sin fallas en su
    // catálogo no deben ofrecerse (dejarían el combo de Falla vacío).
    async function cargarTiposFalla(departamento) {
        limpiarErrorCarga('tipo_falla');
        selectTipoFalla.innerHTML = '<option value="">Seleccione primero una máquina</option>';

        if (!departamento) {
            refrescarAyudas();
            return;
        }

        marcarCargando(selectTipoFalla);

        try {
            const url = `{{ url('/api/mantenimiento/tipos-falla') }}/${encodeURIComponent(departamento)}`;
            const response = await fetch(url);
            const result = await response.json();

            selectTipoFalla.innerHTML = '<option value="">Seleccione primero una máquina</option>';

            if (result.success && Array.isArray(result.data)) {
                result.data.forEach(tipoFalla => {
                    const option = document.createElement('option');
                    option.value = tipoFalla;
                    option.textContent = tipoFalla;
                    selectTipoFalla.appendChild(option);
                });
                // El tipo de falla sólo se abre cuando ya hay una máquina elegida.
                selectTipoFalla.disabled = !selectMaquina.value;
            } else {
                console.error('Error al cargar tipos de falla:', result.error);
                selectTipoFalla.disabled = true;
                mostrarErrorCarga('tipo_falla', 'No se pudieron cargar los tipos de falla de este departamento.');
            }
        } catch (error) {
            console.error('Error al cargar tipos de falla:', error);
            selectTipoFalla.innerHTML = '<option value="">Seleccione primero una máquina</option>';
            selectTipoFalla.disabled = true;
            mostrarErrorCarga('tipo_falla', 'No se pudieron cargar los tipos de falla. Revise la conexión e intente de nuevo.');
        }

        terminarCarga(selectTipoFalla);
        refrescarAyudas();
    }

    // Cargar departamentos y seleccionar automáticamente el del usuario
    async function cargarDepartamentos() {
        limpiarErrorCarga('depto');

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
                        // Cargar máquinas y tipos de falla automáticamente
                        cargarMaquinas(deptoEncontrado);
                        cargarTiposFalla(deptoEncontrado);
                    }
                }
            } else {
                console.error('Error al cargar departamentos:', result.error);
                selectDepto.innerHTML = '<option value="">Error al cargar departamentos</option>';
                mostrarErrorCarga('depto', 'No se pudieron cargar los departamentos.');
            }
        } catch (error) {
            console.error('Error al cargar departamentos:', error);
            selectDepto.innerHTML = '<option value="">Error al cargar departamentos</option>';
            mostrarErrorCarga('depto', 'No se pudieron cargar los departamentos. Revise la conexión e intente de nuevo.');
        }

        refrescarAyudas();
    }

    // Cargar fallas/descripcion por departamento y tipo de falla (CatParosFallas)
    async function cargarFallas(departamento, tipoFallaId = null) {
        limpiarErrorCarga('falla');
        limpiarErrorCarga('descripcion');

        // Reset si no hay depto
        if (!departamento) {
            while (selectFalla.options.length > 1) {
                selectFalla.remove(1);
            }
            selectFalla.value = '';
            selectFalla.disabled = true;
            selectFalla.innerHTML = '<option value=\"\">Seleccione primero un tipo de falla</option>';
            while (selectDescripcion.options.length > 1) {
                selectDescripcion.remove(1);
            }
            selectDescripcion.value = '';
            selectDescripcion.disabled = true;
            selectDescripcion.innerHTML = '<option value=\"\">Seleccione primero un tipo de falla</option>';
            refrescarAyudas();
            return;
        }

        let url = '';

        marcarCargando(selectFalla);
        marcarCargando(selectDescripcion);

        try {
            // El backend resuelve cuándo un departamento comparte catálogo con Tejido.
            url = `{{ url('/api/mantenimiento/fallas') }}/${encodeURIComponent(departamento)}`;
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

                // Ambos selects se valúan con el Id de CatParosFallas: así Falla y
                // Descripción siempre apuntan a la MISMA fila del catálogo. Antes se
                // guardaban como texto suelto y se emparejaban por coincidencia, lo que
                // permitía grabar un par que no existe en el catálogo.
                result.data.forEach(item => {
                    const id = String(item.Id ?? '').trim();
                    const falla = String(item.Falla ?? item.falla ?? '').trim();
                    const descripcion = String(item.Descripcion ?? item.descripcion ?? '').trim();

                    if (!id || !falla) {
                        return;
                    }

                    const optF = document.createElement('option');
                    optF.value = id;
                    optF.textContent = falla;
                    selectFalla.appendChild(optF);

                    if (descripcion) {
                        const optD = document.createElement('option');
                        optD.value = id;
                        optD.textContent = descripcion;
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
                mostrarErrorCarga('falla', 'No se pudieron cargar las fallas de este tipo.');
                mostrarErrorCarga('descripcion', 'No se pudieron cargar las descripciones de este tipo.');
            }
        } catch (error) {
            console.error('Error al cargar fallas:', error);
            console.error('URL intentada:', url);
            selectFalla.innerHTML = '<option value=\"\">Error al cargar fallas</option>';
            selectFalla.disabled = true;
            selectDescripcion.innerHTML = '<option value=\"\">Error al cargar descripciones</option>';
            selectDescripcion.disabled = true;
            mostrarErrorCarga('falla', 'No se pudieron cargar las fallas. Revise la conexión e intente de nuevo.');
            mostrarErrorCarga('descripcion', 'No se pudieron cargar las descripciones. Revise la conexión e intente de nuevo.');
        }

        terminarCarga(selectFalla);
        terminarCarga(selectDescripcion);
        refrescarAyudas();
    }

    // Event listener para Tipo Falla: recargar fallas y habilitar descripción
    selectTipoFalla.addEventListener('change', function() {
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
        }
    });

    // Falla y Descripción comparten valor (el Id del catálogo), así que sincronizarlos
    // es copiar el valor. Si la falla no tiene descripción, el combo de Descripción
    // simplemente queda sin selección.
    function sincronizarFallaDescripcion(origen, destino) {
        const fallaId = origen.value;
        destino.value = fallaId;

        if (!fallaId) {
            return;
        }

        // Solo cargar orden de trabajo sugerida si el campo está vacío: si ya
        // tiene un valor capturado a mano, se respeta.
        const departamentoSeleccionado = selectDepto.value;
        const maquinaSeleccionada = selectMaquina.value;
        if (departamentoSeleccionado && maquinaSeleccionada && !inputOrdenTrabajo.value) {
            cargarOrdenTrabajo(
                departamentoParaOrdenTrabajo(departamentoSeleccionado),
                maquinaSeleccionada
            );
        }
    }

    selectFalla.addEventListener('change', () => sincronizarFallaDescripcion(selectFalla, selectDescripcion));
    selectDescripcion.addEventListener('change', () => sincronizarFallaDescripcion(selectDescripcion, selectFalla));

    // Cargar máquinas por departamento
    async function cargarMaquinas(departamento) {
        limpiarErrorCarga('maquina');

        if (!departamento) {
            // Limpiar máquinas y deshabilitar select
            while (selectMaquina.options.length > 1) {
                selectMaquina.remove(1);
            }
            selectMaquina.value = '';
            selectMaquina.disabled = true;
            selectMaquina.innerHTML = '<option value="">Seleccione primero un departamento</option>';
            refrescarAyudas();
            return;
        }

        marcarCargando(selectMaquina);

        try {
            const response = await fetch(`{{ url('/api/mantenimiento/maquinas') }}/${encodeURIComponent(departamento)}`);
            const result = await response.json();

            if (result.success && result.data) {
                // Limpiar opciones existentes
                selectMaquina.innerHTML = '<option value="">Seleccione una máquina</option>';

                const crearOpcion = maquina => {
                    const option = document.createElement('option');
                    option.value = maquina.MaquinaId;
                    option.textContent = maquina.MaquinaId;
                    option.dataset.departamentoOrigen = maquina.DepartamentoOrigen || departamento;

                    return option;
                };

                const esCalidad = departamento.trim().toUpperCase() === 'CALIDAD';
                const grupos = ['Tejido', 'Urdido', 'Engomado'];

                if (esCalidad && result.data.some(maquina => maquina.DepartamentoOrigen)) {
                    grupos.forEach(grupo => {
                        const maquinasGrupo = result.data.filter(
                            maquina => maquina.DepartamentoOrigen === grupo
                        );
                        if (maquinasGrupo.length === 0) return;

                        const optgroup = document.createElement('optgroup');
                        optgroup.label = grupo;
                        maquinasGrupo.forEach(maquina => optgroup.appendChild(crearOpcion(maquina)));
                        selectMaquina.appendChild(optgroup);
                    });
                } else {
                    result.data.forEach(maquina => selectMaquina.appendChild(crearOpcion(maquina)));
                }

                // Habilitar select de máquinas
                selectMaquina.disabled = false;
            } else {
                console.error('Error al cargar máquinas:', result.error);
                selectMaquina.innerHTML = '<option value="">Error al cargar máquinas</option>';
                selectMaquina.disabled = true;
                mostrarErrorCarga('maquina', 'No se pudieron cargar las máquinas de este departamento.');
            }
        } catch (error) {
            console.error('Error al cargar máquinas:', error);
            selectMaquina.innerHTML = '<option value="">Error al cargar máquinas</option>';
            selectMaquina.disabled = true;
            mostrarErrorCarga('maquina', 'No se pudieron cargar las máquinas. Revise la conexión e intente de nuevo.');
        }

        terminarCarga(selectMaquina);
        refrescarAyudas();
    }

    function departamentoParaOrdenTrabajo(departamentoSeleccionado) {
        if (departamentoSeleccionado.trim().toUpperCase() !== 'CALIDAD') {
            return departamentoSeleccionado;
        }

        const origen = selectMaquina.selectedOptions[0]?.dataset.departamentoOrigen || '';

        return origen === 'Urdido' || origen === 'Engomado'
            ? origen
            : departamentoSeleccionado;
    }

    // Cargar orden de trabajo sugerida por depto + máquina (ReqProgramaTejido en proceso).
    // Nunca pisa lo que el operador haya escrito a mano.
    async function cargarOrdenTrabajo(departamento, maquina) {
        if (ordenTrabajoManual) {
            return;
        }

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

        if (departamentoSeleccionado) {
            // Habilitar máquina y recargar los tipos de falla de este departamento
            cargarMaquinas(departamentoSeleccionado);
            cargarTiposFalla(departamentoSeleccionado);
            // Deshabilitar y limpiar campos siguientes
            selectTipoFalla.disabled = true;
            selectTipoFalla.value = '';
            selectDescripcion.disabled = true;
            selectDescripcion.value = '';
            selectDescripcion.innerHTML = '<option value="">Seleccione primero un tipo de falla</option>';
            limpiarOrdenTrabajoSugerida();
            selectFalla.value = '';
            selectFalla.disabled = true;
            selectFalla.innerHTML = '<option value="">Seleccione primero un tipo de falla</option>';
        } else {
            // Si no hay departamento, deshabilitar todo
            selectMaquina.disabled = true;
            selectMaquina.innerHTML = '<option value="">Seleccione primero un departamento</option>';
            selectTipoFalla.disabled = true;
            selectDescripcion.disabled = true;
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
            limpiarOrdenTrabajoSugerida();
            selectFalla.value = '';
            selectFalla.disabled = true;
            selectFalla.innerHTML = '<option value="">Seleccione primero un tipo de falla</option>';
            // Cargar orden de trabajo sugerida
            cargarOrdenTrabajo(
                departamentoParaOrdenTrabajo(departamentoSeleccionado),
                maquinaSeleccionada
            );
        } else {
            // Si no hay máquina, deshabilitar campos siguientes
            selectTipoFalla.disabled = true;
            selectTipoFalla.value = '';
            selectDescripcion.disabled = true;
            limpiarOrdenTrabajoSugerida();
            selectFalla.value = '';
            selectFalla.disabled = true;
        }
    });

    // Cargar datos al iniciar (los tipos de falla dependen del departamento y los
    // carga cargarDepartamentos al autoseleccionar el área del usuario)
    refrescarAyudas();
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

        function restaurarBotonReportar() {
            isSubmitting = false;
            btnAceptar.disabled = false;
            btnAceptar.textContent = textoOriginal;
            btnAceptar.style.cursor = 'pointer';
            btnAceptar.style.opacity = '1';
        }

        // Bloquear botón y cambiar texto
        isSubmitting = true;
        btnAceptar.disabled = true;
        btnAceptar.textContent = 'Enviando...';
        btnAceptar.style.cursor = 'not-allowed';
        btnAceptar.style.opacity = '0.6';

        // Fecha y hora no se envían: las estampa el servidor al guardar.
        // La falla viaja como Id de catálogo; el servidor deriva Falla y Descripción.
        const payload = {
            depto: selectDepto.value,
            maquina: selectMaquina.value,
            falla_id: selectFalla.value,
            orden_trabajo: inputOrdenTrabajo.value || null,
            obs: document.getElementById('obs').value || null,
        };

        try {
            // El duplicado lo resuelve el propio store dentro de su transacción y
            // responde 422; una comprobación previa aparte sólo añadía un viaje.
            const response = await fetch('{{ route('api.mantenimiento.paros.store') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                const folio = (result.data && result.data.folio) ? result.data.folio : (result.folio || '—');
                const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                const mensajeHtml = 'Folio: <strong>' + esc(folio) + '</strong>' + (result.message ? '<br><br>' + esc(result.message) : '');

                // Siempre a Solicitudes: con document.referrer el operador acababa en
                // cualquier parte si llegó por un enlace externo o abrió la pestaña directa.
                const irASolicitudes = () => {
                    window.location.href = '{{ route('mantenimiento.solicitudes') }}';
                };

                // Mostrar mensaje de éxito con SweetAlert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reportado correctamente',
                        html: mensajeHtml,
                        timer: 6000,
                        showConfirmButton: false
                    }).then(irASolicitudes);
                } else {
                    alert(result.message || 'Paro reportado correctamente');
                    irASolicitudes();
                }
            } else {
                // Error del servidor - reabilitar botón
                restaurarBotonReportar();

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
            restaurarBotonReportar();

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
