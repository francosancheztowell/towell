/**
 * Manejador principal del formulario de Programa de Tejido
 */
window.ProgramaTejidoForm = {
    // Estado global
    state: {
        salonSeleccionado: '',
        telaresDisponibles: [],
        datosModeloActual: {},
        contadorFilasTelar: 0,
        sugerenciasClaveModelo: [],
        cacheSalones: new Map(),
        cacheClaveModelo: new Map(),
        modoEdicion: false,
        registroId: null
    },

    /**
     * Inicializar el formulario
     * @param {boolean} modoEdicion - Si es modo edición o creación
     * @param {Object} registro - Registro a editar (opcional)
     */
    async init(modoEdicion = false, registro = null) {
        console.log(' Inicializando formulario de Programa de Tejido...');

        this.state.modoEdicion = modoEdicion;
        if (registro) {
            this.state.registroId = registro.Id || registro.id;
            this.state.datosModeloActual = registro.modeloCodificado || {};
        }

        // Inicializar UI
        this.inicializarUI();

        // Cargar opciones de selects
        await this.cargarOpcionesIniciales();

        // Configurar event listeners
        this.configurarEventListeners();

        // Si es edición, cargar datos
        if (modoEdicion && registro) {
            setTimeout(() => this.cargarDatosEdicion(registro), 500);
        }

        console.log(' Formulario inicializado correctamente');
    },

    /**
     * Inicializar elementos de UI
     */
    inicializarUI() {
        // Mostrar mensaje de tabla vacía
        const mensajeVacio = document.getElementById('mensaje-vacio-telares');
        if (mensajeVacio && !this.state.modoEdicion) {
            mensajeVacio.classList.remove('hidden');
        }

        // Deshabilitar botones de telar inicialmente
        this.actualizarBotonesTelar(false);
    },

    /**
     * Cargar todas las opciones iniciales
     */
    async cargarOpcionesIniciales() {
        const cargas = [
            this.cargarOpcionesSalon(),
            this.cargarOpcionesHilos(),
            this.cargarOpcionesFlogsId(),
            this.cargarOpcionesCalendarioId(),
            this.cargarOpcionesAplicacionId()
        ];

        await Promise.all(cargas);
    },

    /**
     * Cargar opciones de salón
     */
    async cargarOpcionesSalon() {
        try {
            // Verificar cache
            if (this.state.cacheSalones.has('salones')) {
                const opciones = this.state.cacheSalones.get('salones');
                this.llenarSelect('salon-select', opciones, 'Seleccione salon...');
                return;
            }

            const opciones = await ProgramaTejidoUtils.fetchConCSRF(ProgramaTejidoConfig.api.salon, {
                method: 'GET'
            });

            this.state.cacheSalones.set('salones', opciones);
            this.llenarSelect('salon-select', opciones, 'Seleccione salon...');
        } catch (error) {
            console.error('Error al cargar salones:', error);
        }
    },

    /**
     * Cargar opciones de hilos
     */
    async cargarOpcionesHilos() {
        try {
            const opciones = await ProgramaTejidoUtils.fetchConCSRF(ProgramaTejidoConfig.api.hilos, {
                method: 'GET'
            });
            this.llenarSelect('hilo-select', opciones, 'Seleccione hilo...');
        } catch (error) {
            console.error('Error al cargar hilos:', error);
        }
    },

    /**
     * Cargar opciones de FlogsId
     */
    async cargarOpcionesFlogsId() {
        try {
            const opciones = await ProgramaTejidoUtils.fetchConCSRF(ProgramaTejidoConfig.api.flogsId, {
                method: 'GET'
            });
            this.llenarSelect('idflog-select', opciones, 'Seleccione IdFlog...');
        } catch (error) {
            console.error('Error al cargar FlogsId:', error);
        }
    },

    /**
     * Cargar opciones de CalendarioId
     */
    async cargarOpcionesCalendarioId() {
        try {
            const opciones = await ProgramaTejidoUtils.fetchConCSRF(ProgramaTejidoConfig.api.calendarioId, {
                method: 'GET'
            });
            this.llenarSelect('calendario-select', opciones, 'Seleccione calendario...');
        } catch (error) {
            console.error('Error al cargar calendarios:', error);
        }
    },

    /**
     * Cargar opciones de AplicacionId
     */
    async cargarOpcionesAplicacionId() {
        try {
            const opciones = await ProgramaTejidoUtils.fetchConCSRF(ProgramaTejidoConfig.api.aplicacionId, {
                method: 'GET'
            });
            this.llenarSelect('aplicacion-select', opciones, 'Seleccione aplicación...');
        } catch (error) {
            console.error('Error al cargar aplicaciones:', error);
        }
    },

    /**
     * Llenar un select con opciones
     */
    llenarSelect(selectId, opciones, placeholder = 'Seleccione...') {
        const select = document.getElementById(selectId);
        if (!select) return;

        select.innerHTML = `<option value="">${placeholder}</option>`;
        opciones.forEach(opcion => {
            const option = document.createElement('option');
            option.value = opcion;
            option.textContent = opcion;
            select.appendChild(option);
        });
    },

    /**
     * Configurar todos los event listeners
     */
    configurarEventListeners() {
        // Salón
        const salonSelect = document.getElementById('salon-select');
        if (salonSelect) {
            salonSelect.addEventListener('change', (e) => this.onSalonChange(e.target.value));
        }

        // Clave Modelo (con autocompletado)
        this.configurarAutocompletadoClaveModelo();

        // Hilo
        const hiloSelect = document.getElementById('hilo-select');
        if (hiloSelect) {
            hiloSelect.addEventListener('change', () => this.onHiloChange());
        }

        // Calendario
        const calendarioSelect = document.getElementById('calendario-select');
        if (calendarioSelect) {
            calendarioSelect.addEventListener('change', () => this.recalcularTodasLasFechas());
        }

        // Telares en tabla (delegado para filas dinámicas)
        const tbodyTelares = document.getElementById('tbodyTelares');
        if (tbodyTelares) {
            tbodyTelares.addEventListener('change', (e) => {
                if (e.target.tagName === 'SELECT') {
                    this.verificarYCargarEficienciaVelocidad();
                }
            });
        }

        // Campos para recalcular fechas
        ['no-tiras', 'total-marbetes', 'luchaje', 'repeticiones'].forEach(campoId => {
            const elemento = document.getElementById(campoId);
            if (elemento) {
                elemento.addEventListener('input', () => this.recalcularTodasLasFechas());
            }
        });
    },

    /**
     * Configurar autocompletado para Clave Modelo
     */
    configurarAutocompletadoClaveModelo() {
        const input = document.getElementById('clave-modelo-input');
        const container = document.getElementById('clave-modelo-suggestions');
        if (!input || !container) return;

        // Input con debounce
        const buscarConDebounce = ProgramaTejidoUtils.debounce((valor) => {
            if (valor.length >= ProgramaTejidoConfig.ui.minimoCaracteresAutocompletado) {
                this.cargarOpcionesTamanoClave(this.state.salonSeleccionado, valor);
            } else {
                container.classList.add('hidden');
            }
        }, ProgramaTejidoConfig.ui.autocompletadoDelay);

        input.addEventListener('input', (e) => buscarConDebounce(e.target.value));

        // Focus - mostrar sugerencias si existen
        input.addEventListener('focus', () => {
            if (this.state.sugerenciasClaveModelo.length > 0) {
                this.mostrarSugerenciasClaveModelo(this.state.sugerenciasClaveModelo);
            }
        });

        // Enter - validar selección
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.validarSeleccionClaveModelo(input.value.trim());
            }
        });

        // Click fuera - ocultar sugerencias
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !container.contains(e.target)) {
                container.classList.add('hidden');
            }
        });

        // Change event
        input.addEventListener('change', () => {
            const valor = input.value.trim();
            if (valor) {
                input.classList.add(...ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' '));
                if (this.state.salonSeleccionado && valor) {
                    this.cargarDatosRelacionados(this.state.salonSeleccionado, valor);
                }
            } else {
                input.classList.remove(...ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' '));
            }
        });
    },

    /**
     * Cargar opciones de TamanoClave con filtro
     */
    async cargarOpcionesTamanoClave(salonTejidoId = '', search = '') {
        try {
            const cacheKey = `${salonTejidoId}-${search}`;

            // Verificar cache
            if (this.state.cacheClaveModelo.has(cacheKey)) {
                const opciones = this.state.cacheClaveModelo.get(cacheKey);
                this.state.sugerenciasClaveModelo = opciones;
                this.mostrarSugerenciasClaveModelo(opciones);
                return;
            }

            const params = new URLSearchParams();
            if (salonTejidoId) params.append('salon_tejido_id', salonTejidoId);
            if (search) params.append('search', search);

            const opciones = await ProgramaTejidoUtils.fetchConCSRF(
                `${ProgramaTejidoConfig.api.tamanoClaveBySalon}?${params}`,
                { method: 'GET' }
            );

            this.state.cacheClaveModelo.set(cacheKey, opciones);
            this.state.sugerenciasClaveModelo = opciones;
            this.mostrarSugerenciasClaveModelo(opciones);
        } catch (error) {
            console.error('Error al cargar TamanoClave:', error);
        }
    },

    /**
     * Mostrar sugerencias de Clave Modelo
     */
    mostrarSugerenciasClaveModelo(sugerencias) {
        const container = document.getElementById('clave-modelo-suggestions');
        if (!container) return;

        container.innerHTML = '';

        if (sugerencias.length === 0) {
            const div = document.createElement('div');
            div.className = 'px-2 py-1 text-gray-500 text-xs italic';
            div.textContent = 'No se encontraron coincidencias';
            container.appendChild(div);
            container.classList.remove('hidden');
            return;
        }

        sugerencias.forEach(sugerencia => {
            const div = document.createElement('div');
            div.className = 'px-2 py-1 hover:bg-blue-100 cursor-pointer text-xs';
            div.textContent = sugerencia;
            div.addEventListener('click', () => {
                const input = document.getElementById('clave-modelo-input');
                input.value = sugerencia;
                input.classList.add(...ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' '));
                container.classList.add('hidden');

                if (this.state.salonSeleccionado && sugerencia) {
                    this.cargarDatosRelacionados(this.state.salonSeleccionado, sugerencia);
                }
            });
            container.appendChild(div);
        });

        container.classList.remove('hidden');
    },

    /**
     * Validar selección de Clave Modelo
     */
    validarSeleccionClaveModelo(valor) {
        if (!valor) return;

        const coincidencia = this.state.sugerenciasClaveModelo.some(
            sugerencia => sugerencia.toLowerCase() === valor.toLowerCase()
        );

        if (!coincidencia) {
            ProgramaTejidoUtils.mostrarAlerta('warning', 'Selección inválida',
                'Seleccione una opción válida de la lista');
            return false;
        }

        const container = document.getElementById('clave-modelo-suggestions');
        if (container) container.classList.add('hidden');
        return true;
    },

    /**
     * Handler para cambio de salón
     */
    async onSalonChange(salonTejidoId) {
        this.state.salonSeleccionado = salonTejidoId;

        const salonSelect = document.getElementById('salon-select');
        if (salonSelect && salonTejidoId) {
            salonSelect.classList.add(...ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' '));
        } else if (salonSelect) {
            salonSelect.classList.remove(...ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' '));
        }

        // Limpiar formulario
        this.limpiarFormulario();

        // Cargar telares del salón
        if (salonTejidoId) {
            await this.cargarTelaresPorSalon(salonTejidoId);
        } else {
            this.state.telaresDisponibles = [];
        }
    },

    /**
     * Handler para cambio de hilo
     */
    onHiloChange() {
        const hiloSelect = document.getElementById('hilo-select');
        if (hiloSelect && hiloSelect.value) {
            hiloSelect.classList.add(...ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' '));
        } else if (hiloSelect) {
            hiloSelect.classList.remove(...ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' '));
        }

        this.recalcularTodasLasFechas();
        this.verificarYCargarEficienciaVelocidad();
    },

    /**
     * Cargar telares por salón
     */
    async cargarTelaresPorSalon(salonTejidoId) {
        try {
            if (!salonTejidoId) {
                this.state.telaresDisponibles = [];
                return;
            }

            const telares = await ProgramaTejidoUtils.fetchConCSRF(
                `${ProgramaTejidoConfig.api.telaresBySalon}?salon_tejido_id=${salonTejidoId}`,
                { method: 'GET' }
            );

            this.state.telaresDisponibles = telares;
            this.actualizarFilasTelaresExistentes();
        } catch (error) {
            console.error('Error al cargar telares:', error);
            this.state.telaresDisponibles = [];
        }
    },

    /**
     * Actualizar filas de telares existentes con nuevas opciones
     */
    actualizarFilasTelaresExistentes() {
        const filas = document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)');

        filas.forEach(fila => {
            const selectTelar = fila.querySelector('select');
            if (!selectTelar) return;

            const valorActual = selectTelar.value;

            // Crear nuevas opciones
            let opcionesTelares = '<option value="">Seleccione...</option>';
            this.state.telaresDisponibles.forEach(telar => {
                opcionesTelares += `<option value="${telar}">${telar}</option>`;
            });

            selectTelar.innerHTML = opcionesTelares;
            selectTelar.onchange = () => this.manejarSeleccionTelar(selectTelar);

            // Restaurar valor si existe
            if (valorActual && this.state.telaresDisponibles.includes(valorActual)) {
                selectTelar.value = valorActual;
            }
        });
    },

    /**
     * Cargar datos relacionados del modelo
     */
    async cargarDatosRelacionados(salonTejidoId, tamanoClave) {
        if (!salonTejidoId || !tamanoClave) return;

        console.log('📥 Cargando datos relacionados...', { salonTejidoId, tamanoClave });

        try {
            const datos = await ProgramaTejidoUtils.fetchConCSRF(
                ProgramaTejidoConfig.api.datosRelacionados,
                {
                    method: 'POST',
                    body: JSON.stringify({
                        salon_tejido_id: salonTejidoId,
                        tamano_clave: tamanoClave
                    })
                }
            );

            if (datos.datos) {
                this.llenarCamposConDatos(datos.datos);
            }
        } catch (error) {
            console.error('Error al cargar datos relacionados:', error);
        }
    },

    /**
     * Llenar campos del formulario con datos
     */
    llenarCamposConDatos(datos) {
        console.log('📝 Llenando campos con datos:', datos);

        // Guardar datos del modelo
        this.state.datosModeloActual = { ...datos };

        // Llenar campos según mapeo
        Object.entries(ProgramaTejidoConfig.fieldMappings).forEach(([campoDB, campoInput]) => {
            const valor = datos[campoDB];
            if (valor !== undefined && valor !== null && valor !== '') {
                ProgramaTejidoUtils.establecerValorCampo(campoInput, valor, true);

                // Si es calibre-trama, cargar eficiencia y velocidad después
                if (campoInput === 'calibre-trama') {
                    setTimeout(() => this.verificarYCargarEficienciaVelocidad(), 200);
                }
            }
        });

        // Habilitar botones de telar
        this.actualizarBotonesTelar(true);
    },

    /**
     * Verificar y cargar eficiencia y velocidad
     */
    async verificarYCargarEficienciaVelocidad() {
        const hilo = ProgramaTejidoUtils.obtenerValorCampo('hilo-select');
        const calibreTrama = ProgramaTejidoUtils.obtenerValorCampo('calibre-trama');
        const primerTelarSelect = document.querySelector('#tbodyTelares tr:not(#mensaje-vacio-telares) select');
        const telar = primerTelarSelect?.value;

        if (hilo && telar && calibreTrama) {
            await this.cargarEficienciaYVelocidad();
        }
    },

    /**
     * Cargar eficiencia y velocidad estándar
     */
    async cargarEficienciaYVelocidad() {
        const fibraId = ProgramaTejidoUtils.obtenerValorCampo('hilo-trama');
        const hiloSeleccionado = ProgramaTejidoUtils.obtenerValorCampo('hilo-select');
        const calibreTrama = ProgramaTejidoUtils.obtenerValorCampo('calibre-trama');
        const primerTelarSelect = document.querySelector('#tbodyTelares tr:not(#mensaje-vacio-telares) select');
        const noTelarId = primerTelarSelect?.value;

        const fibraIdToUse = hiloSeleccionado || fibraId;

        if (!fibraIdToUse || !noTelarId || !calibreTrama) {
            return;
        }

        const calibreTramaNum = parseFloat(calibreTrama);
        if (isNaN(calibreTramaNum)) {
            return;
        }

        try {
            // Cargar eficiencia
            const eficienciaData = await ProgramaTejidoUtils.fetchConCSRF(
                `${ProgramaTejidoConfig.api.eficienciaStd}?fibra_id=${fibraIdToUse}&no_telar_id=${noTelarId}&calibre_trama=${calibreTramaNum}`,
                { method: 'GET' }
            );

            if (eficienciaData.eficiencia !== null) {
                const eficiencia = parseFloat(eficienciaData.eficiencia).toFixed(2);
                ProgramaTejidoUtils.establecerValorCampo('eficiencia-std', eficiencia);
            }

            // Cargar velocidad
            const velocidadData = await ProgramaTejidoUtils.fetchConCSRF(
                `${ProgramaTejidoConfig.api.velocidadStd}?fibra_id=${fibraIdToUse}&no_telar_id=${noTelarId}&calibre_trama=${calibreTramaNum}`,
                { method: 'GET' }
            );

            if (velocidadData.velocidad !== null) {
                ProgramaTejidoUtils.establecerValorCampo('velocidad-std', velocidadData.velocidad);
            }

            // Recalcular fechas con los nuevos valores
            this.recalcularTodasLasFechas();

        } catch (error) {
            console.error('Error al cargar eficiencia y velocidad:', error);
        }
    },

    /**
     * Limpiar todo el formulario
     */
    limpiarFormulario() {
        // Lista de campos a limpiar
        const campos = [
            'cuenta-rizo', 'calibre-rizo', 'hilo-rizo', 'nombre-modelo', 'tamano',
            'descripcion', 'calibre-trama', 'hilo-trama', 'calibre-pie', 'cuenta-pie',
            'hilo-pie', 'ancho', 'eficiencia-std', 'velocidad-std', 'maquina',
            'cod-color-1', 'nombre-color-1', 'cod-color-2', 'nombre-color-2',
            'cod-color-3', 'nombre-color-3', 'cod-color-4', 'nombre-color-4',
            'cod-color-5', 'nombre-color-5', 'cod-color-6', 'nombre-color-6',
            'calibre-c1', 'calibre-c2', 'calibre-c3', 'calibre-c4', 'calibre-c5',
            'hilo-c1', 'hilo-c2', 'hilo-c3', 'hilo-c4', 'hilo-c5',
            'hilo-select', 'idflog-select', 'calendario-select', 'aplicacion-select',
            'clave-modelo-input', 'rasurado', 'ancho-toalla', 'largo-toalla', 'peso-crudo',
            'luchaje', 'peine', 'no-tiras', 'repeticiones', 'medida-plano'
        ];

        campos.forEach(campo => ProgramaTejidoUtils.limpiarCampo(campo));

        // Reiniciar clave modelo
        const claveModeloInput = document.getElementById('clave-modelo-input');
        if (claveModeloInput) {
            claveModeloInput.value = '';
            claveModeloInput.classList.remove(...ProgramaTejidoConfig.ui.clasesInputSeleccionado.split(' '));
        }

        // Ocultar sugerencias
        const container = document.getElementById('clave-modelo-suggestions');
        if (container) container.classList.add('hidden');

        // Limpiar cache y estado
        this.state.sugerenciasClaveModelo = [];
        this.state.cacheClaveModelo.clear();
        this.state.datosModeloActual = {};

        // Deshabilitar botones
        this.actualizarBotonesTelar(false);
    },

    /**
     * Actualizar estado de botones de telar
     */
    actualizarBotonesTelar(habilitar) {
        const btnAgregar = document.getElementById('btn-agregar-telar');
        const btnEliminar = document.getElementById('btn-eliminar-telar');

        if (btnAgregar) {
            btnAgregar.disabled = !habilitar;
            btnAgregar.className = habilitar ?
                ProgramaTejidoConfig.ui.clasesBotonHabilitado :
                ProgramaTejidoConfig.ui.clasesBotonDeshabilitado;
        }

        if (btnEliminar) {
            btnEliminar.disabled = !habilitar;
            btnEliminar.className = habilitar ?
                ProgramaTejidoConfig.ui.clasesBotonEliminarHabilitado :
                ProgramaTejidoConfig.ui.clasesBotonDeshabilitado;
        }
    },

    /**
     * Recalcular todas las fechas finales
     */
    recalcularTodasLasFechas() {
        const filas = document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)');
        filas.forEach(fila => this.calcularFechaFinalFila(fila));
    },

    /**
     * Calcular fecha final de una fila
     */
    calcularFechaFinalFila(fila) {
        // Implementación específica según sea create o edit
        if (this.state.modoEdicion) {
            // Lógica de edición
            window.calcularFechaFinalFila?.(fila);
        } else {
            // Lógica de creación
            this.calcularFechaFinalCreacion(fila);
        }
    },

    /**
     * Calcular fecha final para modo creación
     */
    calcularFechaFinalCreacion(fila) {
        const selectTelar = fila.querySelector('select');
        const inputs = fila.querySelectorAll('input[type="datetime-local"], input[type="number"]');

        const noTelarId = selectTelar?.value || '';
        const cantidad = Number(inputs[0]?.value || 0);
        const fechaInicio = inputs[1]?.value || '';

        const salon = this.state.salonSeleccionado;
        const tamanoClave = ProgramaTejidoUtils.obtenerValorCampo('clave-modelo-input');
        const hilo = ProgramaTejidoUtils.obtenerValorCampo('hilo-select');
        const calendario = ProgramaTejidoUtils.obtenerValorCampo('calendario-select');

        if (!noTelarId || cantidad <= 0 || !fechaInicio || !salon || !tamanoClave || !hilo || !calendario) {
            return;
        }

        try {
            const datosModelo = this.state.datosModeloActual;
            if (!datosModelo || Object.keys(datosModelo).length === 0) return;

            let velocidad = parseFloat(ProgramaTejidoUtils.obtenerValorCampo('velocidad-std'));
            let eficiencia = parseFloat(ProgramaTejidoUtils.obtenerValorCampo('eficiencia-std'));

            // Normalizar eficiencia si viene en porcentaje
            if (eficiencia > 1) eficiencia = eficiencia / 100;

            // Calcular StdToaHra
            const noTiras = Number(datosModelo.NoTiras || 0);
            const total = Number(datosModelo.Total || 0);
            const luchaje = Number(datosModelo.Luchaje || 0);
            const repeticiones = Number(datosModelo.Repeticiones || 0);

            if (noTiras <= 0 || total <= 0 || luchaje <= 0 || repeticiones <= 0 || velocidad <= 0) {
                return;
            }

            // Calcular StdToaHra según fórmula oficial de la imagen
            const parte1 = total / 1;
            const parte2 = ((luchaje * 0.5) / 0.0254) / repeticiones;
            const denominador = (parte1 + parte2) / velocidad;
            const stdToaHra = (noTiras * 60) / denominador;

            console.log(' Cálculo fecha final:', { cantidad, stdToaHra, eficiencia, velocidad });

            // Calcular horas necesarias
            const horasProd = cantidad / (stdToaHra * eficiencia);

            console.log(' Horas necesarias:', horasProd);

            // Calcular fecha final sumando horas al calendario
            const fechaFinal = CalendarioManager.sumarHorasCalendario(fechaInicio, horasProd, calendario);
            const fechaFinalFormateada = ProgramaTejidoUtils.formatearFechaParaInput(fechaFinal);

            const fechaFinalInput = inputs[2];
            if (fechaFinalInput) {
                fechaFinalInput.value = fechaFinalFormateada;
            }

        } catch (error) {
            console.error(' Error al calcular fecha final:', error);
        }
    },

    /**
     * Cargar datos para edición
     */
    cargarDatosEdicion(registro) {
        if (!registro) return;

        // Llenar selects principales
        if (registro.SalonTejidoId) {
            ProgramaTejidoUtils.establecerValorCampo('salon-select', registro.SalonTejidoId);
        }
        if (registro.TamanoClave) {
            ProgramaTejidoUtils.establecerValorCampo('clave-modelo-input', registro.TamanoClave);
        }
        if (registro.FlogsId) {
            ProgramaTejidoUtils.establecerValorCampo('idflog-select', registro.FlogsId);
        }
        if (registro.CalendarioId) {
            ProgramaTejidoUtils.establecerValorCampo('calendario-select', registro.CalendarioId);
        }
        if (registro.AplicacionId) {
            ProgramaTejidoUtils.establecerValorCampo('aplicacion-select', registro.AplicacionId);
        }

        // Estado
        this.state.salonSeleccionado = registro.SalonTejidoId || '';
    }
};
