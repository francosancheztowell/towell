/**
 * Manejador de operaciones CRUD para Programa de Tejido
 */
window.ProgramaTejidoCRUD = {

    /* =======================================================================
     * PÚBLICOS
     * ======================================================================= */

    /**
     * Guardar nuevo programa (CREATE)
     */
    async guardar() {
        if (!this.validarFormulario()) return;

        const payload = this.construirPayload();

        ProgramaTejidoUtils.mostrarLoading('Guardando programa...');

        try {
            const data = await ProgramaTejidoUtils.fetchConCSRF(
                ProgramaTejidoConfig.api.guardar,
                {
                    method: 'POST',
                    body: JSON.stringify(payload)
                }
            );

            if (!data.success) {
                throw new Error(data.message || 'No se pudo guardar');
            }

            const programaId = data.data?.[0]?.Id;

            if (programaId) {
                this.mostrarLineasDiarias(programaId);
            }

            ProgramaTejidoUtils.mostrarAlerta(
                'success',
                '¡Guardado!',
                'Programa de tejido creado exitosamente.',
                { timer: 2000, showConfirmButton: false }
            );

            this.redireccionarDespuesDeOperacion();

        } catch (error) {
            console.error('Error al guardar:', error);
            ProgramaTejidoUtils.mostrarAlerta('error', 'Error', error.message || 'Error al guardar');
        } finally {
            ProgramaTejidoUtils.cerrarLoading();
        }
    },

    /**
     * Actualizar programa existente (UPDATE)
     */
    async actualizar() {
        const registroId = ProgramaTejidoForm.state.registroId;
        if (!registroId) {
            ProgramaTejidoUtils.mostrarAlerta('error', 'Error', 'No se encontró el ID del registro');
            return;
        }

        const payload = this.construirPayloadActualizacion();

        ProgramaTejidoUtils.mostrarLoading('Actualizando programa...');

        try {
            const data = await ProgramaTejidoUtils.fetchConCSRF(
                `${ProgramaTejidoConfig.api.actualizar}${registroId}`,
                {
                    method: 'PUT',
                    body: JSON.stringify(payload)
                }
            );

            if (!data.success) {
                throw new Error(data.message || 'No se pudo actualizar');
            }

            if (data.data) {
                this.actualizarCamposUI(data.data);
            }

            await this.mostrarResultadoOperacion(data, 'Programa actualizado');

            if (window.calcularFechaFinalFila) {
                const tr = document.getElementById('cantidad-input')?.closest('tr');
                if (tr) window.calcularFechaFinalFila(tr);
            }

            this.redireccionarDespuesDeOperacion();

        } catch (error) {
            console.error('Error al actualizar:', error);
            ProgramaTejidoUtils.mostrarAlerta('error', 'Error', error.message || 'Error al actualizar');
        } finally {
            ProgramaTejidoUtils.cerrarLoading();
        }
    },

    /**
     * Validar formulario antes de guardar/actualizar
     */
    validarFormulario() {
        const salon = this.obtenerSalonSeleccionado();

        if (!salon) {
            ProgramaTejidoUtils.mostrarAlerta('warning', 'Campos requeridos', 'Selecciona un salón.');
            return false;
        }

        if (!TelarManager.validarTelares()) {
            return false;
        }

        return true;
    },

    /* =======================================================================
     * CONSTRUCCIÓN DE PAYLOAD (CREATE)
     * ======================================================================= */

    /**
     * Construir payload para guardar
     */
    construirPayload() {
        const salon = this.obtenerSalonSeleccionado();
        const tamanoClave = ProgramaTejidoUtils.obtenerValorCampo('clave-modelo-input');
        const hilo = ProgramaTejidoUtils.obtenerValorCampo('hilo-select');

        const idflog = ProgramaTejidoUtils.obtenerValorCampo('idflog-select') ||
                       ProgramaTejidoUtils.obtenerValorCampo('idflog-input');

        const calendarioId = ProgramaTejidoUtils.obtenerValorCampo('calendario-select');
        const aplicacionId = ProgramaTejidoUtils.obtenerValorCampo('aplicacion-select');

        const telares = TelarManager.obtenerDatosTelares();
        const datosFormulario = this.obtenerDatosFormulario();
        const datosModelo = ProgramaTejidoForm.state.datosModeloActual || {};

        const totalPedido = telares.reduce((sum, t) => sum + t.cantidad, 0);
        const primerTelar = telares[0];
        const ultimoTelar = telares[telares.length - 1];

        const maquina = this.generarMaquinaDesdeSalonYTelar(salon, primerTelar);
        const tieneCambioHilo = telares.some(t => t.cambio_hilo === 1) ? 1 : 0;

        const camposBase = this.obtenerCamposBaseDesdeModelo(datosModelo);
        const descripcion = ProgramaTejidoUtils.obtenerValorCampo('descripcion') || null;
        const anchoToallaCalculado = this.calcularAnchoToalla(datosModelo);
        const anchoBase = this.obtenerAnchoBaseDesdeModelo(datosModelo, datosFormulario);

        const { datosModeloSinCalibreRizo2, datosFormularioSinCalibreRizo2 } =
            this.excluirCalibreRizo2(datosModelo, datosFormulario);

        // ⭐ CRÍTICO: Excluir AnchoToalla del spread de datosModelo
        // AnchoToalla se calcula usando la fórmula, NO debe venir del modelo
        const datosModeloSinAnchoToalla = { ...datosModeloSinCalibreRizo2 };
        delete datosModeloSinAnchoToalla.AnchoToalla;

        const payload = {
            // Campos de control
            salon_tejido_id: salon,
            tamano_clave: tamanoClave || null,
            hilo: hilo || null,
            idflog: idflog || null,
            calendario_id: calendarioId || null,
            aplicacion_id: aplicacionId || null,
            telares,

            // 1) Datos de modelo (base) sin CalibreRizo2 y sin AnchoToalla
            ...datosModeloSinAnchoToalla,

            // 2) Datos de formulario (inputs) con prioridad, sin CalibreRizo2
            ...datosFormularioSinCalibreRizo2,

            // 3) Campos base desde modelo, solo si no están en el formulario
            ...this.filtrarCamposBase(camposBase, datosFormulario),

            // Campos calculados y especiales
            TotalPedido: totalPedido,
            SaldoPedido: totalPedido,
            FechaInicio: primerTelar?.fecha_inicio || null,
            FechaFinal: ultimoTelar?.fecha_final || null,
            Maquina: maquina,
            CambioHilo: tieneCambioHilo,
            // ⭐ CRÍTICO: Ancho = valor base del modelo; AnchoToalla se calcula con la fórmula
            Ancho: anchoBase !== null ? Number(anchoBase) : null,
            AnchoToalla: anchoToallaCalculado !== null ? Number(anchoToallaCalculado) : null,

            // Mapeos especiales
            NombreProducto: datosFormulario?.Nombre || datosModelo?.Nombre || null,
            NombreProyecto: descripcion || datosFormulario?.NombreProyecto || datosModelo?.NombreProyecto || null,
            PasadasTrama: datosModelo?.PasadasTramaFondoC1 || null,
            Observaciones: datosModelo?.Obs ?? null
        };

        if (datosModelo?.ItemId) {
            payload.ItemId = datosModelo.ItemId;
        }

        this.aplicarCalibresRizo(payload, datosModelo, datosFormulario);
        this.aplicarCalibresPie(payload, datosModelo);
        this.aplicarCalibresTrama(payload, datosModelo);
        this.aplicarCamposTrama(payload, datosModelo);
        this.aplicarColoresCombinados(payload, datosModelo);

        const formulas = this.calcularFormulas(datosModelo, totalPedido, primerTelar, ultimoTelar);
        if (formulas) Object.assign(payload, formulas);

        //  VERIFICACIÓN FINAL: Asegurar que AnchoToalla tenga el valor correcto
        console.log(' Payload final - AnchoToalla:', {
            valor: payload.AnchoToalla,
            calculado_desde: anchoToallaCalculado !== null ? 'fórmula' : 'null',
            NoTiras: datosModelo?.NoTiras,
            AnchoPeineTrama: datosModelo?.AnchoPeineTrama,
            AnchoToalla_del_modelo: datosModelo?.AnchoToalla,  //  Solo referencia, no se usa para AnchoToalla
            Ancho_base_enviado: payload.Ancho
        });

        return payload;
    },

    /**
     * Construir payload para actualización
     */
    construirPayloadActualizacion() {
        const cantidadEl = document.getElementById('cantidad-input');
        const finEl = document.getElementById('fecha-fin-input');
        const tr = cantidadEl?.closest('tr');

        const formulas = window.calcularFormulasActuales?.(tr) || {};

        const payload = {
            fecha_fin: finEl?.value || null,
            idflog: ProgramaTejidoUtils.obtenerValorCampo('idflog-input') || null,
            nombre_proyecto: ProgramaTejidoUtils.obtenerValorCampo('descripcion') || null
        };

        const camposActualizables = this.obtenerCamposActualizables();

        camposActualizables.forEach(({ id, field, converter }) => {
            if (id === 'idflog-input' || id === 'descripcion') return;

            if (ProgramaTejidoUtils.esCampoHabilitado(id)) {
                const valor = ProgramaTejidoUtils.obtenerValorCampo(id);
                payload[field] = converter(valor);
            }
        });

        this.aplicarFormulasActuales(payload, formulas);

        return payload;
    },

    /* =======================================================================
     * FORMULARIO -> MAPEO DIRECTO
     * ======================================================================= */

    /**
     * Obtener datos del formulario
     * Mapeo directo y explícito de cada input a su campo de DB
     */
    obtenerDatosFormulario() {
        const datos = {};

        const mapeoDirecto = this.obtenerMapeoFormularioCamposDB();
        const camposNumericos = this.obtenerCamposNumericosFormulario();

        Object.entries(mapeoDirecto).forEach(([inputId, campoDB]) => {
            const elemento = document.getElementById(inputId);
            if (!elemento) return;

            const valor = elemento.value.trim();

            if (camposNumericos.includes(inputId)) {
                if (valor !== '' || inputId === 'ancho') {
                    datos[campoDB] = valor !== '' ? Number(valor) : null;
                }
            } else {
                if (valor !== '') {
                    datos[campoDB] = valor;
                }
            }
        });

        const descripcionEl = document.getElementById('descripcion');
        if (descripcionEl && descripcionEl.value.trim() !== '') {
            datos['NombreProyecto'] = descripcionEl.value.trim();
        }

        return datos;
    },

    /* =======================================================================
     * CÁLCULOS
     * ======================================================================= */

    /**
     * Calcular fórmulas de producción
     */
    calcularFormulas(datosModelo, totalPedido, primerTelar, ultimoTelar) {
        if (!datosModelo || !primerTelar || !ultimoTelar) return null;

        let velocidad = parseFloat(ProgramaTejidoUtils.obtenerValorCampo('velocidad-std'));
        let eficiencia = parseFloat(ProgramaTejidoUtils.obtenerValorCampo('eficiencia-std'));

        if (eficiencia > 1) eficiencia = eficiencia / 100;

        const noTiras = Number(datosModelo.NoTiras || 0);
        const total = Number(datosModelo.Total || 0);
        const luchaje = Number(datosModelo.Luchaje || 0);
        const repeticiones = Number(datosModelo.Repeticiones || 0);

        if (noTiras <= 0 || total <= 0 || luchaje <= 0 || repeticiones <= 0 || velocidad <= 0) {
            return null;
        }

        const parte1 = total / 1;
        const parte2 = ((luchaje * 0.5) / 0.0254) / repeticiones;
        const denominador = (parte1 + parte2) / velocidad;
        const stdToaHra = (noTiras * 60) / denominador;

        const fechaInicio = ProgramaTejidoUtils.parseDateFlexible(primerTelar.fecha_inicio);
        const fechaFinal = ProgramaTejidoUtils.parseDateFlexible(ultimoTelar.fecha_final);
        const diasEficiencia = (fechaFinal - fechaInicio) / (1000 * 60 * 60 * 24);

        const stdDia = stdToaHra * eficiencia * 24;

        //  PesoCrudo viene de ReqProgramaTejido (pero en alta coincide con el modelo)
        const pesoCrudo = Number(datosModelo.PesoCrudo || 0);
        const stdHrsEfect = diasEficiencia > 0 ? (totalPedido / diasEficiencia) / 24 : 0;

        const prodKgDia = (stdDia * pesoCrudo) / 1000;
        const prodKgDia2 = ((pesoCrudo * stdHrsEfect) * 24) / 1000;
        const horasProd = (stdToaHra > 0 && eficiencia > 0) ? totalPedido / (stdToaHra * eficiencia) : 0;
        const diasJornada = horasProd / 24;

        //  PesoGRM2 = (PesoCrudo * 1000) / (LargoToalla_codificado * AnchoToalla_codificado)
        // LargoToalla y AnchoToalla se leen de ReqModelosCodificados
        const largoToallaModelo = Number(datosModelo.LargoToalla || 0);
        const anchoToallaModelo = Number(datosModelo.AnchoToalla || 0);
        let pesoGrm2 = null;

        if (pesoCrudo > 0 && largoToallaModelo > 0 && anchoToallaModelo > 0) {
            const pesoGrm2Raw = (pesoCrudo * 10000) / (largoToallaModelo * anchoToallaModelo);
            //  La columna PesoGRM2 en BD ahora es FLOAT: conservamos decimales (6 cifras)
            pesoGrm2 = Number(pesoGrm2Raw.toFixed(6));
            console.log(' PesoGRM2 calculado (frontend):', {
                formula: '(PesoCrudo * 1000) / (LargoToalla_cod * AnchoToalla_cod)',
                PesoCrudo: pesoCrudo,
                LargoToalla_cod: largoToallaModelo,
                AnchoToalla_cod: anchoToallaModelo,
                resultado_raw: pesoGrm2Raw,
                resultado_redondeado_6_decimales: pesoGrm2
            });
        } else {
            console.warn(' No se pudo calcular PesoGRM2 (frontend). Valores:', {
                PesoCrudo: pesoCrudo,
                LargoToalla_cod: largoToallaModelo,
                AnchoToalla_cod: anchoToallaModelo
            });
        }

        const snake = {
            peso_grm2: pesoGrm2,
            dias_eficiencia: diasEficiencia || null,
            prod_kg_dia: prodKgDia || null,
            std_dia: stdDia || null,
            prod_kg_dia2: prodKgDia2 || null,
            std_toa_hra: stdToaHra || null,
            dias_jornada: diasJornada || null,
            horas_prod: horasProd || null,
            std_hrs_efect: stdHrsEfect || null
        };

        const camel = {
            PesoGRM2: snake.peso_grm2,
            DiasEficiencia: snake.dias_eficiencia,
            ProdKgDia: snake.prod_kg_dia,
            StdDia: snake.std_dia,
            ProdKgDia2: snake.prod_kg_dia2,
            StdToaHra: snake.std_toa_hra,
            DiasJornada: snake.dias_jornada,
            HorasProd: snake.horas_prod,
            StdHrsEfect: snake.std_hrs_efect
        };

        return {
            ...snake,
            ...camel
        };
    },

    /* =======================================================================
     * UI / RESULTADOS
     * ======================================================================= */

    /**
     * Actualizar campos en UI después de actualización
     */
    actualizarCamposUI(data) {
        const campos = {
            'cantidad-input': 'SaldoPedido',
            'calibre-trama': 'CalibreTrama2',
            'calibre-c1': 'CalibreComb12',
            'calibre-c2': 'CalibreComb22',
            'calibre-c3': 'CalibreComb32',
            'calibre-c4': 'CalibreComb42',
            'calibre-c5': 'CalibreComb52',
            'hilo-trama': 'FibraTrama',
            'hilo-c1': 'FibraComb1',
            'hilo-c2': 'FibraComb2',
            'hilo-c3': 'FibraComb3',
            'hilo-c4': 'FibraComb4',
            'hilo-c5': 'FibraComb5',
            'nombre-color-1': 'NombreCC1',
            'nombre-color-2': 'NombreCC2',
            'nombre-color-3': 'NombreCC3',
            'nombre-color-4': 'NombreCC4',
            'nombre-color-5': 'NombreCC5',
            'cod-color-1': 'CodColorC1',
            'cod-color-2': 'CodColorC2',
            'cod-color-3': 'CodColorC3',
            'cod-color-4': 'CodColorC4',
            'cod-color-5': 'CodColorC5',
        };

        Object.entries(campos).forEach(([elementId, dataKey]) => {
            if (ProgramaTejidoUtils.esCampoHabilitado(elementId) && dataKey in data) {
                ProgramaTejidoUtils.establecerValorCampo(elementId, data[dataKey] ?? '');
            }
        });

        const finEl = document.getElementById('fecha-fin-input');
        if (finEl && finEl.value) {
            finEl.setAttribute('data-original', finEl.value);
        }
    },

    /**
     * Mostrar resultado de operación con detalles
     */
    async mostrarResultadoOperacion(data, tituloBase = 'Operación completada') {
        if (!window.Swal) {
            console.error('SweetAlert no está disponible para mostrar el resultado de la operación.', {
                titulo: tituloBase,
                data
            });
            return;
        }

        await Swal.fire({
            icon: 'success',
            title: tituloBase,
            html: '<p>Operación completada correctamente.</p>',
            timer: 2000,
            showConfirmButton: true
        });
    },

    /**
     * Mostrar tabla de líneas diarias después de crear
     */
    mostrarLineasDiarias(programaId) {
        if (window.location.pathname.includes('/simulacion')) return;
        if (!programaId) return;

        const contenedorLineas = document.getElementById('contenedor-lineas-diarias');
        if (!contenedorLineas) return;

        contenedorLineas.style.display = 'block';

        const wrapper = document.getElementById('reqpt-line-wrapper');
        if (wrapper) wrapper.classList.remove('hidden');

        if (window.loadReqProgramaTejidoLines) {
            window.loadReqProgramaTejidoLines({ programa_id: programaId });
        }
    },

    /* =======================================================================
     * HELPERS INTERNOS
     * ======================================================================= */

    esSimulacion() {
        return window.location.pathname.includes('/simulacion');
    },

    redireccionarDespuesDeOperacion() {
        setTimeout(() => {
            const redirectPath = this.esSimulacion()
                ? '/simulacion'
                : '/planeacion/programa-tejido';
            window.location.href = redirectPath;
        }, 2000);
    },

    obtenerSalonSeleccionado() {
        return ProgramaTejidoUtils.obtenerValorCampo('salon-select') ||
               ProgramaTejidoUtils.obtenerValorCampo('salon-input');
    },

    generarMaquinaDesdeSalonYTelar(salon, primerTelar) {
        if (!salon || !primerTelar?.no_telar_id) return null;

        const salonUpper = salon.toUpperCase();
        let maquina;

        if (salonUpper.includes('SMIT') || salonUpper.includes('SMI')) {
            maquina = `SMI ${primerTelar.no_telar_id}`;
        } else if (salonUpper.includes('JACQUARD') || salonUpper.includes('JAC')) {
            maquina = `JAC ${primerTelar.no_telar_id}`;
        } else {
            maquina = `${salon} ${primerTelar.no_telar_id}`;
        }

        ProgramaTejidoUtils.establecerValorCampo('maquina', maquina, true);

        return maquina;
    },

    obtenerCamposBaseDesdeModelo(datosModelo) {
        return {
            CalibreRizo: datosModelo?.CalibreRizo ?? null,
            CalibreRizo2: datosModelo?.CalibreRizo2 ?? null,
            CalibrePie: datosModelo?.CalibrePie ?? null,
            CalibrePie2: datosModelo?.CalibrePie2 ?? null,
            CalibreTrama: datosModelo?.CalibreTrama ?? null,
            CalibreTrama2: datosModelo?.CalibreTrama2 ?? null,
            CalibreComb1: datosModelo?.CalibreComb1 ?? null,
            CalibreComb2: datosModelo?.CalibreComb2 ?? null,
            CalibreComb3: datosModelo?.CalibreComb3 ?? null,
            CalibreComb4: datosModelo?.CalibreComb4 ?? null,
            CalibreComb5: datosModelo?.CalibreComb5 ?? null,
            LargoCrudo: datosModelo?.LargoToalla ?? null
        };
    },

    calcularAnchoToalla(datosModelo) {
        //  IMPORTANTE: AnchoToalla NO debe jalar directamente de codificados
        // Se debe calcular usando la fórmula: (AnchoPeineTrama / NoTiras) * 1.001
        // Donde NoTiras y AnchoPeineTrama vienen de ReqModelosCodificados
        // Este valor se guarda en el campo AnchoToalla de ReqProgramaTejido

        const noTiras = datosModelo?.NoTiras;
        const anchoPeineTrama = datosModelo?.AnchoPeineTrama;

        console.log('🔍 Debug calcularAnchoToalla:', {
            NoTiras: noTiras,
            AnchoPeineTrama: anchoPeineTrama,
            AnchoToalla_del_modelo: datosModelo?.AnchoToalla
        });

        const noTirasNum = Number(noTiras);
        const anchoPeineNum = Number(anchoPeineTrama);

        if (noTirasNum > 0 && anchoPeineNum > 0) {
            const anchoCalculado = (anchoPeineNum / noTirasNum) * 1.001;
            console.log('✅ AnchoToalla calculado usando fórmula:', {
                formula: '(AnchoPeineTrama / NoTiras) * 1.001',
                NoTiras: noTiras,
                AnchoPeineTrama: anchoPeineTrama,
                resultado: anchoCalculado
            });
            return anchoCalculado;
        }

        // Si no se puede calcular, retornar null
        console.warn('⚠️ No se pudo calcular AnchoToalla. NoTiras:', noTiras, 'AnchoPeineTrama:', anchoPeineTrama);
        return null;
    },

    obtenerAnchoBaseDesdeModelo(datosModelo, datosFormulario) {
        // ⭐ Ancho (sin Toalla) debe jalar del modelo (AnchoToalla)
        // Este valor se guarda en el campo Ancho de ReqProgramaTejido
        if (datosFormulario?.Ancho !== undefined && datosFormulario?.Ancho !== null) {
            return datosFormulario.Ancho;
        }
        if (datosModelo?.AnchoToalla !== undefined && datosModelo?.AnchoToalla !== null) {
            return Number(datosModelo.AnchoToalla);
        }
        return null;
    },

    excluirCalibreRizo2(datosModelo, datosFormulario) {
        const datosModeloSinCalibreRizo2 = { ...datosModelo };
        delete datosModeloSinCalibreRizo2.CalibreRizo2;

        const datosFormularioSinCalibreRizo2 = { ...datosFormulario };
        delete datosFormularioSinCalibreRizo2.CalibreRizo2;

        return { datosModeloSinCalibreRizo2, datosFormularioSinCalibreRizo2 };
    },

    filtrarCamposBase(camposBase, datosFormulario) {
        return Object.fromEntries(
            Object.entries(camposBase).filter(([key]) => !(key in datosFormulario) && key !== 'CalibreRizo2')
        );
    },

    aplicarCalibresRizo(payload, datosModelo, datosFormulario) {
        if (datosModelo?.CalibreRizo !== undefined && datosModelo?.CalibreRizo !== null) {
            payload.CalibreRizo = datosModelo.CalibreRizo;
        }

        const calibreRizo2Input = ProgramaTejidoUtils.obtenerValorCampo('calibre-rizo');

        if (calibreRizo2Input && calibreRizo2Input.trim() !== '') {
            payload.CalibreRizo2 = Number(calibreRizo2Input) || null;
        } else if (datosModelo?.CalibreRizo2 !== undefined && datosModelo?.CalibreRizo2 !== null) {
            payload.CalibreRizo2 = datosModelo.CalibreRizo2;
        } else {
            payload.CalibreRizo2 = null;
        }

        if (payload.CalibreRizo2 === payload.CalibreRizo && payload.CalibreRizo !== null) {
            if (datosModelo?.CalibreRizo2 !== undefined && datosModelo?.CalibreRizo2 !== null) {
                payload.CalibreRizo2 = datosModelo.CalibreRizo2;
            }
        }
    },

    aplicarCalibresPie(payload, datosModelo) {
        if (datosModelo?.CalibrePie !== undefined && datosModelo?.CalibrePie !== null) {
            payload.CalibrePie = datosModelo.CalibrePie;
        }

        const calibrePie2Input = ProgramaTejidoUtils.obtenerValorCampo('calibre-pie');
        if (calibrePie2Input) {
            payload.CalibrePie2 = Number(calibrePie2Input) || null;
        } else if (datosModelo?.CalibrePie2 !== undefined && datosModelo?.CalibrePie2 !== null) {
            payload.CalibrePie2 = datosModelo.CalibrePie2;
        }
    },

    aplicarCalibresTrama(payload, datosModelo) {
        if (datosModelo?.CalibreTrama !== undefined && datosModelo?.CalibreTrama !== null) {
            payload.CalibreTrama = datosModelo.CalibreTrama;
        }

        const calibreTrama2Input = ProgramaTejidoUtils.obtenerValorCampo('calibre-trama');
        if (calibreTrama2Input) {
            payload.CalibreTrama2 = Number(calibreTrama2Input) || null;
        } else if (datosModelo?.CalibreTrama2 !== undefined && datosModelo?.CalibreTrama2 !== null) {
            payload.CalibreTrama2 = datosModelo.CalibreTrama2;
        }
    },

    aplicarCamposTrama(payload, datosModelo) {
        const codColorTramaInput = ProgramaTejidoUtils.obtenerValorCampo('cod-color');
        const colorTramaInput = ProgramaTejidoUtils.obtenerValorCampo('nombre-color');
        const fibraTramaInput = ProgramaTejidoUtils.obtenerValorCampo('hilo-trama');

        if (codColorTramaInput) payload.CodColorTrama = codColorTramaInput;
        if (colorTramaInput) payload.ColorTrama = colorTramaInput;

        if (fibraTramaInput) {
            payload.FibraTrama = fibraTramaInput;
        } else if (datosModelo?.FibraTramaFondoC1) {
            payload.FibraTrama = datosModelo.FibraTramaFondoC1;
        }
    },

    aplicarColoresCombinados(payload, datosModelo) {
        // CodColorComb1-5
        for (let i = 1; i <= 5; i++) {
            const codInput = ProgramaTejidoUtils.obtenerValorCampo(`cod-color-${i}`);
            const combField = `CodColorComb${i}`;
            const modeloField = `CodColorC${i}`;

            if (codInput) {
                payload[combField] = codInput;
            } else if (datosModelo?.[modeloField]) {
                payload[combField] = datosModelo[modeloField];
            }
        }

        // NombreCC1-5
        for (let i = 1; i <= 5; i++) {
            const nombreInput = ProgramaTejidoUtils.obtenerValorCampo(`nombre-color-${i}`);
            const nombreField = `NombreCC${i}`;
            const modeloField = `NomColorC${i}`;

            if (nombreInput) {
                payload[nombreField] = nombreInput;
            } else if (datosModelo?.[modeloField]) {
                payload[nombreField] = datosModelo[modeloField];
            }
        }
    },

    obtenerCamposActualizables() {
        return [
            { id: 'cantidad-input', field: 'cantidad',       converter: v => Number(v || 0) },
            { id: 'idflog-input',  field: 'idflog',         converter: v => v || null },
            { id: 'descripcion',   field: 'nombre_proyecto',converter: v => v || null },
            { id: 'calibre-trama', field: 'calibre_trama',  converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c1',    field: 'calibre_c1',     converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c2',    field: 'calibre_c2',     converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c3',    field: 'calibre_c3',     converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c4',    field: 'calibre_c4',     converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c5',    field: 'calibre_c5',     converter: v => v !== '' ? Number(v) : null },
            { id: 'hilo-trama',    field: 'fibra_trama',    converter: v => v || null },
            { id: 'hilo-c1',       field: 'fibra_c1',       converter: v => v || null },
            { id: 'hilo-c2',       field: 'fibra_c2',       converter: v => v || null },
            { id: 'hilo-c3',       field: 'fibra_c3',       converter: v => v || null },
            { id: 'hilo-c4',       field: 'fibra_c4',       converter: v => v || null },
            { id: 'hilo-c5',       field: 'fibra_c5',       converter: v => v || null },
            { id: 'nombre-color-1',field: 'nombre_color_1', converter: v => v || null },
            { id: 'nombre-color-2',field: 'nombre_color_2', converter: v => v || null },
            { id: 'nombre-color-3',field: 'nombre_color_3', converter: v => v || null },
            { id: 'nombre-color-4',field: 'nombre_color_4', converter: v => v || null },
            { id: 'nombre-color-5',field: 'nombre_color_5', converter: v => v || null },
            { id: 'cod-color-1',   field: 'cod_color_1',    converter: v => v || null },
            { id: 'cod-color-2',   field: 'cod_color_2',    converter: v => v || null },
            { id: 'cod-color-3',   field: 'cod_color_3',    converter: v => v || null },
            { id: 'cod-color-4',   field: 'cod_color_4',    converter: v => v || null },
            { id: 'cod-color-5',   field: 'cod_color_5',    converter: v => v || null },
        ];
    },

    aplicarFormulasActuales(payload, formulas) {
        if (!formulas) return;

        const fieldMap = {
            dias_eficiencia: 'dias_eficiencia',
            prod_kg_dia: 'prod_kg_dia',
            std_dia: 'std_dia',
            prod_kg_dia2: 'prod_kg_dia2',
            std_toa_hra: 'std_toa_hra',
            dias_jornada: 'dias_jornada',
            horas_prod: 'horas_prod',
            std_hrs_efect: 'std_hrs_efect'
        };

        Object.entries(formulas).forEach(([key, value]) => {
            if (fieldMap[key] && Number.isFinite(value)) {
                payload[fieldMap[key]] = Number(value.toFixed(4));
            }
        });
    },

    obtenerMapeoFormularioCamposDB() {
        return {
            // Campos básicos
            'cuenta-rizo': 'CuentaRizo',
            'calibre-rizo': 'CalibreRizo2',
            'hilo-rizo': 'FibraRizo',
            'tamano': 'InventSizeId',
            'nombre-proyecto': 'NombreProyecto',
            'rasurado': 'Rasurado',

            // Trama
            'calibre-trama': 'CalibreTrama2',
            'hilo-trama': 'FibraTrama',
            'cod-color': 'CodColorTrama',
            'nombre-color': 'ColorTrama',

            // Pie
            'calibre-pie': 'CalibrePie2',
            'cuenta-pie': 'CuentaPie',
            'hilo-pie': 'FibraPie',

            // Colores C1-C5
            'cod-color-1': 'CodColorComb1',
            'nombre-color-1': 'NombreCC1',
            'cod-color-2': 'CodColorComb2',
            'nombre-color-2': 'NombreCC2',
            'cod-color-3': 'CodColorComb3',
            'nombre-color-3': 'NombreCC3',
            'cod-color-4': 'CodColorComb4',
            'nombre-color-4': 'NombreCC4',
            'cod-color-5': 'CodColorComb5',
            'nombre-color-5': 'NombreCC5',

            // Calibres C1-C5
            'calibre-c1': 'CalibreComb12',
            'calibre-c2': 'CalibreComb22',
            'calibre-c3': 'CalibreComb32',
            'calibre-c4': 'CalibreComb42',
            'calibre-c5': 'CalibreComb52',

            // Fibras C1-C5
            'hilo-c1': 'FibraComb1',
            'hilo-c2': 'FibraComb2',
            'hilo-c3': 'FibraComb3',
            'hilo-c4': 'FibraComb4',
            'hilo-c5': 'FibraComb5',

            // Adicionales
            'ancho': 'Ancho',
            'eficiencia-std': 'EficienciaSTD',
            'velocidad-std': 'VelocidadSTD',
            'maquina': 'Maquina'
        };
    },

    obtenerCamposNumericosFormulario() {
        return [
            'calibre-rizo', 'calibre-trama', 'calibre-pie',
            'calibre-c1', 'calibre-c2', 'calibre-c3', 'calibre-c4', 'calibre-c5',
            'ancho', 'eficiencia-std', 'velocidad-std',
            'cuenta-rizo', 'cuenta-pie'
        ];
    }
};
