/**
 * Manejador de telares para el formulario de Programa de Tejido
 */
window.TelarManager = {

    /**
     * Agregar nueva fila a la tabla de telares
     */
    agregarFilaTelar() {
        console.log('➕ Agregando fila de telar...');
        console.log('📊 Estado actual de telares:', ProgramaTejidoForm.state.telaresDisponibles);

        const contadorFilas = ++ProgramaTejidoForm.state.contadorFilasTelar;
        const tbody = document.getElementById('tbodyTelares');
        const mensajeVacio = document.getElementById('mensaje-vacio-telares');

        // Ocultar mensaje de tabla vacía
        if (mensajeVacio) {
            mensajeVacio.classList.add('hidden');
        }

        // Crear opciones de telares
        let opcionesTelares = '<option value="">Seleccione...</option>';
        if (ProgramaTejidoForm.state.telaresDisponibles && Array.isArray(ProgramaTejidoForm.state.telaresDisponibles)) {
            ProgramaTejidoForm.state.telaresDisponibles.forEach(telar => {
                opcionesTelares += `<option value="${telar}">${telar}</option>`;
            });
        } else {
            console.error('❌ No hay telares disponibles o el formato es incorrecto');
        }

        const nuevaFila = document.createElement('tr');
        nuevaFila.id = `fila-telar-${contadorFilas}`;
        nuevaFila.className = 'hover:bg-gray-50';
        nuevaFila.innerHTML = this._getFilaHTML(contadorFilas, opcionesTelares);

        tbody.appendChild(nuevaFila);

        console.log('✅ Fila de telar agregada:', contadorFilas);
    },

    /**
     * Obtener HTML de una fila de telar
     * @private
     */
    _getFilaHTML(contadorFilas, opcionesTelares) {
        return `
            <td class="px-3 py-2 border border-gray-300">
                <select class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white"
                        onchange="TelarManager.manejarSeleccionTelar(this)">
                    ${opcionesTelares}
                </select>
            </td>
            <td class="px-3 py-2 border border-gray-300">
                <input type="number" placeholder="0" value="" min="0"
                       oninput="this.value = this.value.replace(/^-/, ''); ProgramaTejidoForm.calcularFechaFinalFila(this.closest('tr'));"
                       class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
            </td>
            <td class="px-3 py-2 border border-gray-300">
                <input type="datetime-local" id="fecha-inicio-${contadorFilas}" step="1"
                       onchange="ProgramaTejidoForm.calcularFechaFinalFila(this.closest('tr'));"
                       class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
            </td>
            <td class="px-3 py-2 border border-gray-300">
                <input type="datetime-local" step="1"
                       class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-white">
            </td>
            <td class="px-3 py-2 border border-gray-300">
                <input type="datetime-local"
                       class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled>
            </td>
            <td class="px-3 py-2 border border-gray-300">
                <input type="datetime-local"
                       class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled>
            </td>
            <td class="px-3 py-2 border border-gray-300">
                <input type="datetime-local"
                       class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled>
            </td>
            <input type="hidden" data-hilo-anterior="" class="hilo-anterior-input">
        `;
    },

    /**
     * Eliminar última fila de la tabla de telares
     */
    eliminarFilaTelar() {
        const filas = document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)');
        const mensajeVacio = document.getElementById('mensaje-vacio-telares');

        if (filas.length > 0) {
            filas[filas.length - 1].remove();
            ProgramaTejidoForm.state.contadorFilasTelar--;

            // Si no quedan filas, mostrar mensaje
            const filasRestantes = document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)');
            if (filasRestantes.length === 0 && mensajeVacio) {
                mensajeVacio.classList.remove('hidden');
            }

            console.log('🗑️ Fila de telar eliminada');
        }
    },

    /**
     * Manejar selección de telar
     */
    async manejarSeleccionTelar(selectElement) {
        const noTelarId = selectElement.value;
        const fila = selectElement.closest('tr');
        const fechaInicioInput = fila.querySelector('input[type="datetime-local"]');
        const hiloAnteriorInput = fila.querySelector('.hilo-anterior-input');

        if (noTelarId && fechaInicioInput) {
            // Obtener la última fecha final, hilo y maquina del telar
            const dataUltima = await this.obtenerUltimaFechaFinalTelar(noTelarId);

            if (dataUltima && dataUltima.ultima_fecha_final) {
                const fechaFormateada = ProgramaTejidoUtils.formatearFechaParaInput(dataUltima.ultima_fecha_final);
                fechaInicioInput.value = fechaFormateada;
                console.log(`📅 Fecha inicial establecida para telar ${noTelarId}:`, fechaFormateada);

                // Guardar el hilo anterior si existe
                if (dataUltima.hilo && hiloAnteriorInput) {
                    hiloAnteriorInput.setAttribute('data-hilo-anterior', dataUltima.hilo);
                    console.log(`🧵 Hilo anterior guardado: ${dataUltima.hilo}`);
                }

                // Establecer maquina si existe
                if (dataUltima.maquina) {
                    ProgramaTejidoUtils.establecerValorCampo('maquina', dataUltima.maquina, true);
                    console.log(`🏭 Máquina establecida: ${dataUltima.maquina}`);
                }

                // Establecer ancho si existe
                if (dataUltima.ancho) {
                    ProgramaTejidoUtils.establecerValorCampo('ancho', dataUltima.ancho, true);
                    console.log(`📏 Ancho establecido: ${dataUltima.ancho}`);
                }
            } else {
                fechaInicioInput.value = '';
            }
        } else if (fechaInicioInput) {
            fechaInicioInput.value = '';
        }

        // Cargar eficiencia y velocidad cuando se selecciona un telar
        if (noTelarId && window.ProgramaTejidoForm && window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad) {
            await window.ProgramaTejidoForm.verificarYCargarEficienciaVelocidad();
        }

        // Recalcular fecha final
        ProgramaTejidoForm.calcularFechaFinalFila(fila);
    },

    /**
     * Obtener última fecha final de un telar
     */
    async obtenerUltimaFechaFinalTelar(noTelarId) {
        try {
            const salonSeleccionado = ProgramaTejidoForm.state.salonSeleccionado;
            if (!salonSeleccionado || !noTelarId) return null;

            const params = new URLSearchParams({
                salon_tejido_id: salonSeleccionado,
                no_telar_id: noTelarId
            });

            const data = await ProgramaTejidoUtils.fetchConCSRF(
                `${ProgramaTejidoConfig.api.ultimaFechaFinalTelar}?${params}`,
                { method: 'GET' }
            );

            // Retornar objeto con fecha y hilo
            return {
                ultima_fecha_final: data.ultima_fecha_final,
                hilo: data.hilo || null
            };
        } catch (error) {
            console.error('Error al obtener última fecha final del telar:', error);
            return null;
        }
    },

    /**
     * Obtener datos de todos los telares de la tabla
     */
    obtenerDatosTelares() {
        const filas = Array.from(document.querySelectorAll('#tbodyTelares tr:not(#mensaje-vacio-telares)'));
        const hiloActual = ProgramaTejidoUtils.obtenerValorCampo('hilo-select');

        const datosTelares = filas.map((fila) => {
            const selectTelar = fila.querySelector('select');
            const inputs = fila.querySelectorAll('input[type="datetime-local"], input[type="number"]');
            const hiloAnteriorInput = fila.querySelector('.hilo-anterior-input');
            const hiloAnterior = hiloAnteriorInput ? hiloAnteriorInput.getAttribute('data-hilo-anterior') : '';

            // Calcular CambioHilo: 1 si el hilo cambió, 0 si no
            const cambioHilo = (hiloAnterior && hiloActual && hiloAnterior !== hiloActual) ? 1 : 0;

            console.log('🧵 Comparación de hilos:', { hiloAnterior, hiloActual, cambioHilo });

            return {
                no_telar_id: selectTelar ? selectTelar.value : '',
                cantidad: inputs[0] ? (inputs[0].type === 'number' ? Number(inputs[0].value || 0) : 0) : 0,
                fecha_inicio: inputs[1] ? inputs[1].value || null : null,
                fecha_final: inputs[2] ? inputs[2].value || null : null,
                compromiso_tejido: inputs[3] ? inputs[3].value || null : null,
                fecha_cliente: inputs[4] ? inputs[4].value || null : null,
                fecha_entrega: inputs[5] ? inputs[5].value || null : null,
                cambio_hilo: cambioHilo
            };
        }).filter(t => t.no_telar_id);

        console.log('📋 Datos telares procesados:', datosTelares);
        return datosTelares;
    },

    /**
     * Validar que haya al menos un telar
     */
    validarTelares() {
        const telares = this.obtenerDatosTelares();

        if (telares.length === 0) {
            ProgramaTejidoUtils.mostrarAlerta('warning', 'Tabla vacía', 'Agrega al menos un telar.');
            return false;
        }

        // Validar que todos los telares tengan datos mínimos
        for (const telar of telares) {
            if (!telar.no_telar_id) {
                ProgramaTejidoUtils.mostrarAlerta('warning', 'Datos incompletos',
                    'Todos los telares deben tener un número asignado.');
                return false;
            }
            if (telar.cantidad <= 0) {
                ProgramaTejidoUtils.mostrarAlerta('warning', 'Datos incompletos',
                    'Todos los telares deben tener una cantidad mayor a 0.');
                return false;
            }
        }

        return true;
    },

    /**
     * Actualizar máquina basado en el telar seleccionado
     */
    actualizarMaquina() {
        const primerTelarSelect = document.querySelector('#tbodyTelares tr:not(#mensaje-vacio-telares) select');
        const maquinaInput = document.getElementById('maquina');

        if (!primerTelarSelect || !maquinaInput) return;

        const telarSeleccionado = primerTelarSelect.value;
        if (!telarSeleccionado) {
            maquinaInput.value = '';
            return;
        }

        const salon = ProgramaTejidoForm.state.salonSeleccionado;
        let prefijoMaquina = '';

        if (salon === 'JACQUARD') {
            prefijoMaquina = 'JAC';
        } else {
            prefijoMaquina = 'SMI';
        }

        maquinaInput.value = `${prefijoMaquina} ${telarSeleccionado}`;
        console.log('⚙️ Máquina actualizada:', maquinaInput.value);
    }
};
