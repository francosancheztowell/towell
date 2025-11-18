/**
 * Manejador de operaciones CRUD para Programa de Tejido
 */
window.ProgramaTejidoCRUD = {

    /**
     * Guardar nuevo programa (CREATE)
     */
    async guardar() {
        console.log('Info: Iniciando proceso de guardado...');

        // Validar formulario
        if (!this.validarFormulario()) {
            return;
        }

        // Recopilar datos
        const payload = this.construirPayload();

        // Mostrar loading
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

            // Obtener ID del programa creado
            const programaId = data.data?.[0]?.Id;

            // Mostrar tabla de líneas diarias si existe
            if (programaId) {
                this.mostrarLineasDiarias(programaId);
            }

            ProgramaTejidoUtils.mostrarAlerta(
                'success',
                '¡Guardado!',
                'Programa de tejido creado exitosamente.',
                { timer: 2000, showConfirmButton: false }
            );

            // Redireccionar después de 2 segundos
            setTimeout(() => {
                const isSimulacion = window.location.pathname.includes('/simulacion');
                const redirectPath = isSimulacion ? '/simulacion' : '/planeacion/programa-tejido';
                window.location.href = redirectPath;
            }, 2000);

        } catch (error) {
            console.error(' Error al guardar:', error);
            ProgramaTejidoUtils.mostrarAlerta('error', 'Error', error.message || 'Error al guardar');
        } finally {
            ProgramaTejidoUtils.cerrarLoading();
        }
    },

    /**
     * Actualizar programa existente (UPDATE)
     */
    async actualizar() {
        console.log('Info: Iniciando proceso de actualización...');

        const registroId = ProgramaTejidoForm.state.registroId;
        if (!registroId) {
            ProgramaTejidoUtils.mostrarAlerta('error', 'Error', 'No se encontró el ID del registro');
            return;
        }

        // Recopilar datos para actualización
        const payload = this.construirPayloadActualizacion();

        // Mostrar loading
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

            // Actualizar campos en UI si hay datos de respuesta
            if (data.data) {
                this.actualizarCamposUI(data.data);
            }

            // Mostrar resultado con detalles si hay cascada
            await this.mostrarResultadoOperacion(data, 'Programa actualizado');

            // Recalcular métricas
            if (window.calcularFechaFinalFila) {
                const tr = document.getElementById('cantidad-input')?.closest('tr');
                if (tr) window.calcularFechaFinalFila(tr);
            }

            // Redireccionar después de mostrar el mensaje
            setTimeout(() => {
                const isSimulacion = window.location.pathname.includes('/simulacion');
                const redirectPath = isSimulacion ? '/simulacion' : '/planeacion/programa-tejido';
                window.location.href = redirectPath;
            }, 2000);

        } catch (error) {
            console.error(' Error al actualizar:', error);
            ProgramaTejidoUtils.mostrarAlerta('error', 'Error', error.message || 'Error al actualizar');
        } finally {
            ProgramaTejidoUtils.cerrarLoading();
        }
    },

    /**
     * Validar formulario antes de guardar
     */
    validarFormulario() {
        const salon = ProgramaTejidoUtils.obtenerValorCampo('salon-select') ||
                      ProgramaTejidoUtils.obtenerValorCampo('salon-input');
        const telares = TelarManager.obtenerDatosTelares();

        if (!salon) {
            ProgramaTejidoUtils.mostrarAlerta('warning', 'Campos requeridos', 'Selecciona un salón.');
            return false;
        }

        if (!TelarManager.validarTelares()) {
            return false;
        }

        return true;
    },

    /**
     * Construir payload para guardar
     */
    construirPayload() {
        // Buscar salón en select o input (para modo pronósticos)
        const salon = ProgramaTejidoUtils.obtenerValorCampo('salon-select') ||
                      ProgramaTejidoUtils.obtenerValorCampo('salon-input');
        console.log('Info: Salón obtenido:', salon, {
            'salon-select': ProgramaTejidoUtils.obtenerValorCampo('salon-select'),
            'salon-input': ProgramaTejidoUtils.obtenerValorCampo('salon-input')
        });
        const tamanoClave = ProgramaTejidoUtils.obtenerValorCampo('clave-modelo-input');
        const hilo = ProgramaTejidoUtils.obtenerValorCampo('hilo-select');
        // Buscar IdFlog en select o input (para modo edición)
        const idflog = ProgramaTejidoUtils.obtenerValorCampo('idflog-select') ||
                       ProgramaTejidoUtils.obtenerValorCampo('idflog-input');
        console.log('Info: Campos principales:', { salon, tamanoClave, hilo, idflog });
        const calendarioId = ProgramaTejidoUtils.obtenerValorCampo('calendario-select');
        const aplicacionId = ProgramaTejidoUtils.obtenerValorCampo('aplicacion-select');

        const telares = TelarManager.obtenerDatosTelares();
        console.log('Info: Datos de telares:', telares);
        const datosFormulario = this.obtenerDatosFormulario();
        const datosModelo = ProgramaTejidoForm.state.datosModeloActual || {};

        // Calcular totales
        const totalPedido = telares.reduce((sum, t) => sum + t.cantidad, 0);
        const primerTelar = telares[0];
        const ultimoTelar = telares[telares.length - 1];

        // Generar Maquina basada en salón y telar
        let maquina = null;
        if (salon && primerTelar?.no_telar_id) {
            const salonUpper = salon.toUpperCase();
            if (salonUpper.includes('SMIT') || salonUpper.includes('SMI')) {
                maquina = `SMI ${primerTelar.no_telar_id}`;
            } else if (salonUpper.includes('JACQUARD') || salonUpper.includes('JAC')) {
                maquina = `JAC ${primerTelar.no_telar_id}`;
            } else {
                // Fallback: usar el salón + telar
                maquina = `${salon} ${primerTelar.no_telar_id}`;
            }

            // Actualizar el campo maquina en el formulario
            ProgramaTejidoUtils.establecerValorCampo('maquina', maquina, true);
            console.log(`Info: Máquina generada: ${maquina}`);
        }

        // Verificar si hay cambio de hilo en alguno de los telares
        const tieneCambioHilo = telares.some(t => t.cambio_hilo === 1) ? 1 : 0;

        // Campos base (rosas) que DEBEN guardarse desde ReqModelosCodificados
        // Estos son los campos que no tienen input visible pero se guardan en la misma columna
        const camposBase = {
            CalibreRizo: datosModelo?.CalibreRizo ?? null,
            CalibrePie: datosModelo?.CalibrePie ?? null,
            CalibreTrama: datosModelo?.CalibreTrama ?? null,
            CalibreComb1: datosModelo?.CalibreComb1 ?? null,
            CalibreComb2: datosModelo?.CalibreComb2 ?? null,
            CalibreComb3: datosModelo?.CalibreComb3 ?? null,
            CalibreComb4: datosModelo?.CalibreComb4 ?? null,
            CalibreComb5: datosModelo?.CalibreComb5 ?? null
        };

        // Obtener descripción (NombreProyecto) desde el campo descripcion
        const descripcion = ProgramaTejidoUtils.obtenerValorCampo('descripcion') || null;

        // Obtener ancho explícitamente del campo oculto o del modelo
        const ancho = ProgramaTejidoUtils.obtenerValorCampo('ancho') ||
                      datosModelo?.AnchoToalla ||
                      datosFormulario?.AnchoToalla ||
                      null;
        console.log('Info: Ancho obtenido:', ancho, {
            'campo-ancho': ProgramaTejidoUtils.obtenerValorCampo('ancho'),
            'modelo-AnchoToalla': datosModelo?.AnchoToalla,
            'formulario-AnchoToalla': datosFormulario?.AnchoToalla
        });

        // Verificar cambio de hilo con más detalle
        console.log('Info: Verificando cambio de hilo:', {
            telares: telares.map(t => ({ no_telar_id: t.no_telar_id, cambio_hilo: t.cambio_hilo })),
            tieneCambioHilo
        });

        // Construir payload completo
        const payload = {
            salon_tejido_id: salon,
            tamano_clave: tamanoClave || null,
            hilo: hilo || null,
            idflog: idflog || null,
            calendario_id: calendarioId || null,
            aplicacion_id: aplicacionId || null,
            telares,
            ...datosModelo,
            ...datosFormulario,
            // Asegurar que los campos base (rosas) se guarden explícitamente
            ...camposBase,
            TotalPedido: totalPedido,
            SaldoPedido: totalPedido,
            FechaInicio: primerTelar?.fecha_inicio || null,
            FechaFinal: ultimoTelar?.fecha_final || null,
            // Generar Maquina automáticamente
            Maquina: maquina,
            // Mapeos especiales
            NombreProducto: datosModelo?.Nombre || datosFormulario?.Nombre || null,
            NombreProyecto: descripcion || datosFormulario?.NombreProyecto || null,
            PasadasTrama: datosModelo?.Total || null,
            Observaciones: datosModelo?.Obs ?? null
        };

        // Agregar campos críticos al final para asegurar que tengan prioridad
        // CambioHilo al nivel principal - asegurar que sea 0 o 1
        payload.CambioHilo = tieneCambioHilo;
        // Ancho explícitamente incluido (convertir a número si existe)
        payload.AnchoToalla = ancho ? Number(ancho) : null;

        // Calcular fórmulas si es posible
        const formulas = this.calcularFormulas(datosModelo, totalPedido, primerTelar, ultimoTelar);
        if (formulas) {
            Object.assign(payload, formulas);
        }

        console.log('Info: Payload construido:', payload);
        return payload;
    },

    /**
     * Construir payload para actualización
     */
    construirPayloadActualizacion() {
        const cantidadEl = document.getElementById('cantidad-input');
        const finEl = document.getElementById('fecha-fin-input');
        const tr = cantidadEl?.closest('tr');

        // Calcular fórmulas actuales
        const formulas = window.calcularFormulasActuales?.(tr) || {};

        const payload = {
            fecha_fin: finEl?.value || null,
            // Incluir IdFlog y descripción siempre
            idflog: ProgramaTejidoUtils.obtenerValorCampo('idflog-input') || null,
            nombre_proyecto: ProgramaTejidoUtils.obtenerValorCampo('descripcion') || null
        };

        // Agregar campos habilitados
        const camposActualizables = [
            { id: 'cantidad-input', field: 'cantidad', converter: v => Number(v || 0) },
            { id: 'idflog-input', field: 'idflog', converter: v => v || null },
            { id: 'descripcion', field: 'nombre_proyecto', converter: v => v || null },
            { id: 'calibre-trama', field: 'calibre_trama', converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c1', field: 'calibre_c1', converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c2', field: 'calibre_c2', converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c3', field: 'calibre_c3', converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c4', field: 'calibre_c4', converter: v => v !== '' ? Number(v) : null },
            { id: 'calibre-c5', field: 'calibre_c5', converter: v => v !== '' ? Number(v) : null },
            { id: 'hilo-trama', field: 'fibra_trama', converter: v => v || null },
            { id: 'hilo-c1', field: 'fibra_c1', converter: v => v || null },
            { id: 'hilo-c2', field: 'fibra_c2', converter: v => v || null },
            { id: 'hilo-c3', field: 'fibra_c3', converter: v => v || null },
            { id: 'hilo-c4', field: 'fibra_c4', converter: v => v || null },
            { id: 'hilo-c5', field: 'fibra_c5', converter: v => v || null },
            { id: 'nombre-color-1', field: 'nombre_color_1', converter: v => v || null },
            { id: 'nombre-color-2', field: 'nombre_color_2', converter: v => v || null },
            { id: 'nombre-color-3', field: 'nombre_color_3', converter: v => v || null },
            { id: 'nombre-color-6', field: 'nombre_color_6', converter: v => v || null },
            { id: 'cod-color-1', field: 'cod_color_1', converter: v => v || null },
            { id: 'cod-color-2', field: 'cod_color_2', converter: v => v || null },
            { id: 'cod-color-3', field: 'cod_color_3', converter: v => v || null },
            { id: 'cod-color-4', field: 'cod_color_4', converter: v => v || null },
            { id: 'cod-color-5', field: 'cod_color_5', converter: v => v || null },
            { id: 'cod-color-6', field: 'cod_color_6', converter: v => v || null }
        ];

        // Solo agregar campos habilitados al payload
        camposActualizables.forEach(({ id, field, converter }) => {
            // IdFlog y descripción ya están en el payload, saltarlos aquí
            if (id === 'idflog-input' || id === 'descripcion') {
                return; // Ya están incluidos arriba
            }

            if (ProgramaTejidoUtils.esCampoHabilitado(id)) {
                const valor = ProgramaTejidoUtils.obtenerValorCampo(id);
                payload[field] = converter(valor);
            }
        });

        // Log para depuración
        console.log('Info: Payload de actualización:', payload);

        // Agregar fórmulas calculadas
        if (formulas) {
            Object.entries(formulas).forEach(([key, value]) => {
                const fieldMap = {
                    'dias_eficiencia': 'dias_eficiencia',
                    'prod_kg_dia': 'prod_kg_dia',
                    'std_dia': 'std_dia',
                    'prod_kg_dia2': 'prod_kg_dia2',
                    'std_toa_hra': 'std_toa_hra',
                    'dias_jornada': 'dias_jornada',
                    'horas_prod': 'horas_prod',
                    'std_hrs_efect': 'std_hrs_efect'
                };

                if (fieldMap[key] && Number.isFinite(value)) {
                    payload[fieldMap[key]] = Number(value.toFixed(4));
                }
            });
        }

        console.log('Info: Payload de actualización:', payload);
        return payload;
    },

    /**
     * Obtener datos del formulario
     */
    obtenerDatosFormulario() {
        const datos = {};

        // Lista de campos del formulario
        const campos = [
            'cuenta-rizo', 'calibre-rizo', 'hilo-rizo', 'tamano', 'nombre-proyecto',
            'cod-color-1', 'nombre-color-1', 'cod-color-2', 'nombre-color-2',
            'cod-color-3', 'nombre-color-3', 'cod-color-4', 'nombre-color-4',
            'cod-color-5', 'nombre-color-5', 'cod-color-6', 'nombre-color-6',
            'calibre-trama', 'hilo-trama', 'calibre-c1', 'hilo-c1',
            'calibre-c2', 'hilo-c2', 'calibre-c3', 'hilo-c3',
            'calibre-c4', 'hilo-c4', 'calibre-c5', 'hilo-c5',
            'calibre-pie', 'cuenta-pie', 'hilo-pie', 'ancho',
            'eficiencia-std', 'velocidad-std', 'maquina', 'rasurado'
        ];

        // Mapeo inverso para obtener nombres de DB
        const mapeoInverso = {};
        Object.entries(ProgramaTejidoConfig.fieldMappings).forEach(([db, ui]) => {
            mapeoInverso[ui] = db;
        });

        // Recopilar valores
        campos.forEach(campoId => {
            const elemento = document.getElementById(campoId);
            // Para campos numéricos como 'ancho', incluir incluso si está vacío (puede venir del modelo)
            if (elemento) {
                const valor = elemento.value;
                // Incluir el valor si no está vacío, o si es un campo numérico que puede venir del modelo
                if (valor !== '' || campoId === 'ancho') {
                    const nombreDB = mapeoInverso[campoId];
                    if (nombreDB) {
                        // Convertir a número si es un campo numérico
                        if (campoId === 'ancho' && valor !== '') {
                            datos[nombreDB] = Number(valor) || null;
                        } else if (valor !== '') {
                            datos[nombreDB] = valor;
                        }
                    }
                }
            }
        });

        // Mapeo especial para descripcion -> NombreProyecto (modo edición)
        const descripcionEl = document.getElementById('descripcion');
        if (descripcionEl && descripcionEl.value !== '') {
            datos['NombreProyecto'] = descripcionEl.value;
        }

        console.log('Info: Datos recopilados del formulario:', datos);
        return datos;
    },

    /**
     * Calcular fórmulas de producción
     */
    calcularFormulas(datosModelo, totalPedido, primerTelar, ultimoTelar) {
        if (!datosModelo || !primerTelar || !ultimoTelar) return null;

        let velocidad = parseFloat(ProgramaTejidoUtils.obtenerValorCampo('velocidad-std', 100));
        let eficiencia = parseFloat(ProgramaTejidoUtils.obtenerValorCampo('eficiencia-std', 0.8));
        // Normalizar eficiencia si viene en porcentaje
        if (eficiencia > 1) eficiencia = eficiencia / 100;
        const calendario = ProgramaTejidoUtils.obtenerValorCampo('calendario-select', 'Calendario Tej1');

        // Calcular StdToaHra
        const noTiras = Number(datosModelo.NoTiras || 0);
        const total = Number(datosModelo.Total || 0);
        const luchaje = Number(datosModelo.Luchaje || 0);
        const repeticiones = Number(datosModelo.Repeticiones || 0);

        if (noTiras <= 0 || total <= 0 || luchaje <= 0 || repeticiones <= 0 || velocidad <= 0) {
            return null;
        }

        // Calcular StdToaHra según fórmula oficial de la imagen
        const parte1 = total / 1;
        const parte2 = ((luchaje * 0.5) / 0.0254) / repeticiones;
        const denominador = (parte1 + parte2) / velocidad;
        const stdToaHra = (noTiras * 60) / denominador;

        // Calcular días eficiencia (diferencia directa sin calendario laboral)
        const fechaInicio = ProgramaTejidoUtils.parseDateFlexible(primerTelar.fecha_inicio);
        const fechaFinal = ProgramaTejidoUtils.parseDateFlexible(ultimoTelar.fecha_final);
        const diasEficiencia = (fechaFinal - fechaInicio) / (1000 * 60 * 60 * 24);

        // Calcular horas reales para otros cálculos
        const horasReales = CalendarioManager.calcularHorasReales(
            primerTelar.fecha_inicio,
            ultimoTelar.fecha_final,
            calendario
        );

        const stdDia = stdToaHra * eficiencia * 24;
        // StdHrsEfect: (TotalPedido / DiasEficiencia) / 24
        const stdHrsEfect = diasEficiencia > 0 ? (totalPedido / diasEficiencia) / 24 : 0;
        const pesoCrudo = Number(datosModelo.PesoCrudo || 0);
        // ProdKgDia: (StdDia * PesoCrudo) / 1000 según imagen
        const prodKgDia = (stdDia * pesoCrudo) / 1000;
        // ProdKgDia2: ((PesoCrudo * StdHrsEfect) * 24) / 1000
        const prodKgDia2 = ((pesoCrudo * stdHrsEfect) * 24) / 1000;
        const diasJornada = velocidad / 24;
        const horasProd = (stdToaHra > 0 && eficiencia > 0) ? totalPedido / (stdToaHra * eficiencia) : 0;

        return {
            PesoGRM2: pesoCrudo ? Math.round(pesoCrudo) : null,
            DiasEficiencia: diasEficiencia || null,
            ProdKgDia: prodKgDia || null,
            StdDia: stdDia || null,
            ProdKgDia2: prodKgDia2 || null,
            StdToaHra: stdToaHra || null,
            DiasJornada: diasJornada || null,
            HorasProd: horasProd || null,
            StdHrsEfect: stdHrsEfect || null
        };
    },

    /**
     * Actualizar campos en UI después de actualización
     */
    actualizarCamposUI(data) {
        const campos = {
            'cantidad-input': 'SaldoPedido',
            'calibre-trama': 'CalibreTrama',
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
            'nombre-color-6': 'NombreCC5',
            'cod-color-1': 'CodColorTrama',
            'cod-color-2': 'CodColorComb2',
            'cod-color-3': 'CodColorComb4',
            'cod-color-4': 'CodColorComb1',
            'cod-color-5': 'CodColorComb3',
            'cod-color-6': 'CodColorComb5'
        };

        Object.entries(campos).forEach(([elementId, dataKey]) => {
            if (ProgramaTejidoUtils.esCampoHabilitado(elementId) && dataKey in data) {
                ProgramaTejidoUtils.establecerValorCampo(elementId, data[dataKey] ?? '');
            }
        });

        // Actualizar data-original de fecha final si existe
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
            console.log(tituloBase, data);
            return;
        }

        const detalles = data?.detalles || [];
        const cascaded = data?.cascaded_records || detalles.length;

        let html = '<p>Operación completada correctamente.</p>';


        await Swal.fire({
            icon: 'success',
            title: tituloBase,
            html,
            timer: 2000,
            showConfirmButton: true
        });
    },

    /**
     * Mostrar tabla de líneas diarias después de crear
     */
    mostrarLineasDiarias(programaId) {
        // No mostrar tabla de líneas diarias en simulación
        if (window.location.pathname.includes('/simulacion')) {
            return;
        }

        const contenedorLineas = document.getElementById('contenedor-lineas-diarias');
        if (!contenedorLineas || !programaId) return;

        contenedorLineas.style.display = 'block';

        // Mostrar el wrapper de la tabla
        const wrapper = document.getElementById('reqpt-line-wrapper');
        if (wrapper) {
            wrapper.classList.remove('hidden');
        }

        // Cargar las líneas diarias si existe la función
        if (window.loadReqProgramaTejidoLines) {
            window.loadReqProgramaTejidoLines({ programa_id: programaId });
        }
    }
};
