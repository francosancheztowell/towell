/**
 * Manejador de calendarios para el módulo de Programa de Tejido
 * Gestiona los cálculos de fechas según diferentes calendarios laborales
 */
window.CalendarioManager = {

    /**
     * Cache de líneas de calendario para evitar múltiples peticiones
     */
    _lineasCache: {},

    /**
     * Sumar horas a una fecha según el tipo de calendario
     * @param {string|Date} fechaInicio - Fecha de inicio
     * @param {number} horas - Horas a sumar
     * @param {string} tipoCalendario - Tipo de calendario (Calendario Tej1, Tej2, Tej3)
     * @returns {Promise<Date>} Nueva fecha con las horas sumadas
     */
    async sumarHorasCalendario(fechaInicio, horas, tipoCalendario = 'Calendario Tej1') {
        const fecha = fechaInicio instanceof Date ? new Date(fechaInicio) : new Date(fechaInicio);

        if (isNaN(fecha.getTime())) {
            console.error('Fecha inválida:', fechaInicio);
            return new Date();
        }

        // ⭐ INTENTAR USAR LÍNEAS REALES DEL CALENDARIO PRIMERO
        try {
            const fechaFinal = await this._sumarHorasConLineasReales(fecha, horas, tipoCalendario);
            if (fechaFinal) {
                console.log(`✅ Fecha final calculada usando líneas reales del calendario: ${tipoCalendario}`);
                return fechaFinal;
            }
        } catch (error) {
            console.warn(`⚠️ No se pudieron usar líneas reales del calendario, usando método genérico:`, error);
        }

        // Fallback a método genérico
        const config = ProgramaTejidoConfig.calendarios[tipoCalendario];
        if (!config) {
            console.warn(`Calendario desconocido: ${tipoCalendario}, usando Tej1 por defecto`);
            return this._sumarHorasDirecto(fecha, horas);
        }

        switch(tipoCalendario) {
            case 'Calendario Tej1':
                return this._sumarHorasDirecto(fecha, horas);
            case 'Calendario Tej2':
                return this._sumarHorasTej2(fecha, horas);
            case 'Calendario Tej3':
                return this._sumarHorasTej3(fecha, horas);
            default:
                return this._sumarHorasDirecto(fecha, horas);
        }
    },

    /**
     * Sumar horas usando las líneas reales del calendario importado
     * @private
     */
    async _sumarHorasConLineasReales(fechaInicio, horasNecesarias, calendarioId) {
        // Obtener líneas del calendario (con cache)
        let lineas = this._lineasCache[calendarioId];
        if (!lineas) {
            try {
                const url = `${ProgramaTejidoConfig.api.calendarioLineas}/${encodeURIComponent(calendarioId)}`;
                const response = await fetch(url);
                if (!response.ok) {
                    console.warn(`No se pudieron obtener líneas del calendario ${calendarioId}`);
                    return null;
                }
                const data = await response.json();
                if (!data.success || !data.lineas || data.lineas.length === 0) {
                    console.warn(`Calendario ${calendarioId} no tiene líneas importadas`);
                    return null;
                }
                lineas = data.lineas;
                this._lineasCache[calendarioId] = lineas; // Cachear
            } catch (error) {
                console.error(`Error al obtener líneas del calendario:`, error);
                return null;
            }
        }

        // Convertir fechaInicio a Date si es string
        let fechaActual = fechaInicio instanceof Date ? new Date(fechaInicio) : new Date(fechaInicio);
        let horasRestantes = Number(horasNecesarias) || 0;

        const primeraLinea = lineas[0];
        const ultimaLinea = lineas[lineas.length - 1];
        const inicioPrimera = new Date(primeraLinea.FechaInicio);
        const finUltima = new Date(ultimaLinea.FechaFin);

        // Si la fecha de inicio está fuera del rango cubierto por las líneas, usar fallback directo
        if (fechaActual < inicioPrimera || fechaActual > finUltima) {
            console.warn('⚠️ Fecha de inicio fuera del rango de líneas importadas. Usando fallback genérico.', {
                calendarioId,
                fechaInicioSolicitada: fechaActual.toISOString(),
                inicioPrimera: inicioPrimera.toISOString(),
                finUltima: finUltima.toISOString()
            });
            return null;
        }

        // Buscar la línea que contiene la fecha de inicio
        let indiceInicio = -1;
        for (let i = 0; i < lineas.length; i++) {
            const linea = lineas[i];
            const inicioLinea = new Date(linea.FechaInicio);
            const finLinea = new Date(linea.FechaFin);

            // Si la fecha está dentro de este período
            if (fechaActual >= inicioLinea && fechaActual <= finLinea) {
                indiceInicio = i;
                break;
            }
            // Si la fecha es anterior a esta línea, empezar desde aquí
            if (fechaActual < inicioLinea) {
                indiceInicio = i;
                fechaActual = new Date(inicioLinea); // Ajustar a inicio de la línea
                break;
            }
        }

        // Si no encontramos línea, empezar desde la primera
        if (indiceInicio === -1) {
            console.warn('⚠️ No se encontró una línea que contenga la fecha de inicio. Usando fallback genérico.', {
                calendarioId,
                fechaInicioSolicitada: fechaActual.toISOString()
            });
            return null;
        }

        // Recorrer las líneas sumando horas solo en períodos de trabajo
        for (let i = indiceInicio; i < lineas.length && horasRestantes > 0.0001; i++) {
            const linea = lineas[i];
            const inicioLinea = new Date(linea.FechaInicio);
            const finLinea = new Date(linea.FechaFin);
            const horasTurno = Number(linea.HorasTurno) || 0;

            // Si la fecha actual está antes del inicio de esta línea, avanzar al inicio
            if (fechaActual < inicioLinea) {
                fechaActual = new Date(inicioLinea);
            }

            // Si la fecha actual está después del fin de esta línea, saltar a la siguiente
            if (fechaActual > finLinea) {
                continue;
            }

            //  USAR LAS HORAS DEL TURNO: Si HorasTurno está definido, usar ese valor
            // Si no, calcular las horas desde fechaActual hasta finLinea
            let horasDisponiblesEnEstePeriodo;
            if (horasTurno > 0) {
                // Calcular qué porcentaje del turno queda desde fechaActual hasta finLinea
                const duracionTotalTurno = (finLinea - inicioLinea) / (1000 * 60 * 60);
                const horasDesdeInicio = (fechaActual - inicioLinea) / (1000 * 60 * 60);
                const porcentajeConsumido = horasDesdeInicio / duracionTotalTurno;
                horasDisponiblesEnEstePeriodo = horasTurno * (1 - porcentajeConsumido);
            } else {
                // Si no hay HorasTurno definido, usar la diferencia de tiempo real
                horasDisponiblesEnEstePeriodo = (finLinea - fechaActual) / (1000 * 60 * 60);
            }

            if (horasRestantes <= horasDisponiblesEnEstePeriodo) {
                // Las horas caben en este período
                // Calcular la fecha final proporcionalmente
                const porcentajeUsado = horasRestantes / horasDisponiblesEnEstePeriodo;
                const tiempoUsado = (finLinea - fechaActual) * porcentajeUsado;
                fechaActual = new Date(fechaActual.getTime() + tiempoUsado);
                horasRestantes = 0;
            } else {
                // Consumir todo este período y pasar al siguiente
                horasRestantes -= horasDisponiblesEnEstePeriodo;
                fechaActual = new Date(finLinea);
            }
        }

        // Si aún quedan horas, usar el método genérico para el resto
        if (horasRestantes > 0.0001) {
            console.warn(`⚠️ Quedan ${horasRestantes.toFixed(2)} horas después de recorrer todas las líneas del calendario`);
            fechaActual = this._sumarHorasDirecto(fechaActual, horasRestantes);
        }

        return fechaActual;
    },

    /**
     * Calcular horas reales entre dos fechas según el calendario
     * @param {string|Date} fechaInicio - Fecha de inicio
     * @param {string|Date} fechaFinal - Fecha final
     * @param {string} tipoCalendario - Tipo de calendario
     * @returns {number} Horas reales de trabajo
     */
    calcularHorasReales(fechaInicio, fechaFinal, tipoCalendario = 'Calendario Tej1') {
        const dInicio = ProgramaTejidoUtils.parseDateFlexible(fechaInicio);
        const dFinal = ProgramaTejidoUtils.parseDateFlexible(fechaFinal);

        if (!dInicio || !dFinal) return 0;

        switch(tipoCalendario) {
            case 'Calendario Tej1':
                return (dFinal - dInicio) / (1000 * 60 * 60);
            case 'Calendario Tej2':
                return this._calcularHorasTej2(dInicio, dFinal);
            case 'Calendario Tej3':
                return this._calcularHorasTej3(dInicio, dFinal);
            default:
                return (dFinal - dInicio) / (1000 * 60 * 60);
        }
    },

    /**
     * Suma directa de horas sin restricciones (Tej1)
     * @private
     */
    _sumarHorasDirecto(fecha, horas) {
        const nuevaFecha = new Date(fecha);
        const diasCompletos = Math.floor(horas / 24);
        const horasRestantes = Math.floor(horas % 24);
        const minutosRestantes = Math.round((horas - Math.floor(horas)) * 60);

        nuevaFecha.setDate(nuevaFecha.getDate() + diasCompletos);
        nuevaFecha.setHours(nuevaFecha.getHours() + horasRestantes);
        nuevaFecha.setMinutes(nuevaFecha.getMinutes() + minutosRestantes);

        return nuevaFecha;
    },

    /**
     * Suma de horas para Tej2 (Lunes a Sábado, sin domingos)
     * Replica la lógica del código PHP viejo: suma días calendario completos (24h)
     * saltando domingos, luego suma horas/minutos restantes también saltando domingos
     * @private
     */
    _sumarHorasTej2(fecha, horas) {
        const fechaOriginal = new Date(fecha);
        const nuevaFecha = new Date(fecha);
        const msPerDay = 86400000; // 24h en ms
        const msPerHour = 3600000;
        const msPerMinute = 60000;

        // Separar días completos, horas y minutos
        const diasCompletos = Math.floor(horas / 24);
        const horasRestantes = Math.floor(horas % 24);
        const minutosRestantes = Math.round((horas - Math.floor(horas)) * 60);

        console.log('🔍 _sumarHorasTej2 DEBUG:', {
            fechaInicio: fechaOriginal.toISOString(),
            horasTotales: horas,
            diasCompletos: diasCompletos,
            horasRestantes: horasRestantes,
            minutosRestantes: minutosRestantes
        });

        // 1. Sumar días completos (24h cada uno), saltando domingos
        let domingosEvitados = 0;
        for (let i = 0; i < diasCompletos; i++) {
            nuevaFecha.setTime(nuevaFecha.getTime() + msPerDay);
            // Si es domingo, sumar 1 día más para llegar a lunes
            if (nuevaFecha.getDay() === 0) {
                nuevaFecha.setTime(nuevaFecha.getTime() + msPerDay);
                domingosEvitados++;
            }
        }

        console.log('  → Después de sumar días:', nuevaFecha.toISOString(), `(evitó ${domingosEvitados} domingos)`);

        // 2. Sumar horas restantes, saltando domingos si caemos en uno
        let domingosPorHoras = 0;
        for (let i = 0; i < horasRestantes; i++) {
            nuevaFecha.setTime(nuevaFecha.getTime() + msPerHour);
            if (nuevaFecha.getDay() === 0) {
                // Si caemos en domingo, ir a lunes 00:00
                nuevaFecha.setDate(nuevaFecha.getDate() + 1);
                nuevaFecha.setHours(0, 0, 0, 0);
                domingosPorHoras++;
            }
        }

        if (horasRestantes > 0) {
            console.log('  → Después de sumar horas:', nuevaFecha.toISOString(), `(evitó ${domingosPorHoras} domingos)`);
        }

        // 3. Sumar minutos restantes, saltando domingos si caemos en uno
        let domingosPorMinutos = 0;
        for (let i = 0; i < minutosRestantes; i++) {
            nuevaFecha.setTime(nuevaFecha.getTime() + msPerMinute);
            if (nuevaFecha.getDay() === 0) {
                // Si caemos en domingo, ir a lunes 00:00
                nuevaFecha.setDate(nuevaFecha.getDate() + 1);
                nuevaFecha.setHours(0, 0, 0, 0);
                domingosPorMinutos++;
            }
        }

        if (minutosRestantes > 0) {
            console.log('  → Después de sumar minutos:', nuevaFecha.toISOString(), `(evitó ${domingosPorMinutos} domingos)`);
        }

        console.log('✅ Fecha final Tej2:', nuevaFecha.toISOString());

        return nuevaFecha;
    },

    /**
     * Suma de horas para Tej3 (Lunes-Viernes completo, Sábado hasta 18:29, sin domingos)
     * @private
     */
    _sumarHorasTej3(startDate, horas) {
        if (!(startDate instanceof Date) || isNaN(startDate)) return startDate;

        let cur = new Date(startDate.getTime());
        let remaining = Number(horas) || 0;
        const msPerHour = 3600000;

        // Helper para avanzar a lunes 07:00
        const toMonday0700 = (d) => {
            const day = d.getDay();
            const diffToMon = (8 - day) % 7;
            d = new Date(d.getTime());
            d.setDate(d.getDate() + diffToMon);
            d.setHours(7, 0, 0, 0);
            return d;
        };

        while (remaining > 0.0001) {
            const day = cur.getDay(); // 0=Dom, 6=Sab

            // Si es domingo, saltar a lunes 07:00
            if (day === 0) {
                cur = toMonday0700(cur);
                continue;
            }

            // Si es sábado y ya pasó 18:29, saltar a lunes 07:00
            if (day === 6) {
                const sabEnd = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate(), 18, 29, 0, 0);
                if (cur.getTime() >= sabEnd.getTime()) {
                    cur = toMonday0700(cur);
                    continue;
                }

                // Ventana final de hoy
                const availableHours = (sabEnd.getTime() - cur.getTime()) / msPerHour;
                if (remaining <= availableHours) {
                    cur = new Date(cur.getTime() + remaining * msPerHour);
                    remaining = 0;
                } else {
                    remaining -= availableHours;
                    cur = toMonday0700(cur);
                }
                continue;
            }

            // Lunes a Viernes: ventana hasta fin del día
            const endOfDay = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate() + 1, 0, 0, 0, 0);
            let nextStart = new Date(endOfDay.getTime());

            // Si mañana es domingo, saltar a lunes 07:00
            if (nextStart.getDay() === 0) nextStart = toMonday0700(nextStart);

            const availableHours = (endOfDay.getTime() - cur.getTime()) / msPerHour;

            if (remaining <= availableHours) {
                cur = new Date(cur.getTime() + remaining * msPerHour);
                remaining = 0;
            } else {
                remaining -= availableHours;
                cur = nextStart;
            }
        }

        return cur;
    },

    /**
     * Calcular horas Tej2 (Lunes a Sábado)
     * @private
     */
    _calcularHorasTej2(dInicio, dFin) {
        if (!(dInicio instanceof Date) || !(dFin instanceof Date)) return 0;

        let cur = new Date(dInicio.getTime());
        let totalHoras = 0;
        const msPerHour = 3600000;

        while (cur.getTime() < dFin.getTime()) {
            const dia = cur.getDay(); // 0=Dom

            // Si es domingo, saltar al lunes 00:00
            if (dia === 0) {
                cur = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate() + 1, 0, 0, 0, 0);
                if (cur.getTime() >= dFin.getTime()) break;
                continue;
            }

            // Lunes a sábado: contar horas hasta fin del día o dFin
            const finDelDia = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate() + 1, 0, 0, 0, 0);
            const efectoEnd = Math.min(finDelDia.getTime(), dFin.getTime());
            totalHoras += (efectoEnd - cur.getTime()) / msPerHour;
            cur.setTime(efectoEnd);
        }

        return Math.max(0, totalHoras);
    },

    /**
     * Calcular horas Tej3 (Lunes-Viernes completo, Sábado hasta 18:29)
     * @private
     */
    _calcularHorasTej3(dInicio, dFin) {
        if (!(dInicio instanceof Date) || !(dFin instanceof Date)) return 0;

        let cur = new Date(dInicio.getTime());
        let totalHoras = 0;
        const msPerHour = 3600000;

        // Helper para avanzar a lunes 07:00
        const toMonday0700 = (d) => {
            const day = d.getDay();
            const diffToMon = (8 - day) % 7;
            d = new Date(d.getTime());
            d.setDate(d.getDate() + diffToMon);
            d.setHours(7, 0, 0, 0);
            return d;
        };

        while (cur.getTime() < dFin.getTime()) {
            const day = cur.getDay(); // 0=Dom, 6=Sab

            // Si es domingo, saltar a lunes 07:00
            if (day === 0) {
                cur = toMonday0700(cur);
                if (cur.getTime() >= dFin.getTime()) break;
                continue;
            }

            // Si es sábado
            if (day === 6) {
                const sabEnd = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate(), 18, 29, 0, 0);

                // Si ya estamos después de 18:29, saltar a lunes 07:00
                if (cur.getTime() >= sabEnd.getTime()) {
                    cur = toMonday0700(cur);
                    if (cur.getTime() >= dFin.getTime()) break;
                    continue;
                }

                // Calcular horas hasta 18:29 o hasta dFin
                const efectoEnd = Math.min(sabEnd.getTime(), dFin.getTime());
                totalHoras += (efectoEnd - cur.getTime()) / msPerHour;
                cur.setTime(efectoEnd);

                // Si llegamos a 18:29, saltar a lunes 07:00
                if (cur.getTime() >= sabEnd.getTime()) {
                    cur = toMonday0700(cur);
                }
                continue;
            }

            // Lunes a viernes: contar horas hasta fin del día o dFin
            const endOfDay = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate() + 1, 0, 0, 0, 0);
            const efectoEnd = Math.min(endOfDay.getTime(), dFin.getTime());
            totalHoras += (efectoEnd - cur.getTime()) / msPerHour;
            cur.setTime(efectoEnd);

            // Si mañana es domingo, saltar a lunes 07:00
            if (cur.getDay() === 0 && cur.getTime() < dFin.getTime()) {
                cur = toMonday0700(cur);
            }
        }

        return Math.max(0, totalHoras);
    }
};





































