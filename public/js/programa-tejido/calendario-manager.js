/**
 * Manejador de calendarios para el módulo de Programa de Tejido
 * Gestiona los cálculos de fechas según diferentes calendarios laborales
 */
window.CalendarioManager = {

    /**
     * Sumar horas a una fecha según el tipo de calendario
     * @param {string|Date} fechaInicio - Fecha de inicio
     * @param {number} horas - Horas a sumar
     * @param {string} tipoCalendario - Tipo de calendario (Calendario Tej1, Tej2, Tej3)
     * @returns {Date} Nueva fecha con las horas sumadas
     */
    sumarHorasCalendario(fechaInicio, horas, tipoCalendario = 'Calendario Tej1') {
        const fecha = fechaInicio instanceof Date ? new Date(fechaInicio) : new Date(fechaInicio);

        if (isNaN(fecha.getTime())) {
            console.error('Fecha inválida:', fechaInicio);
            return new Date();
        }

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
     * @private
     */
    _sumarHorasTej2(fecha, horas) {
        const nuevaFecha = new Date(fecha);
        let horasRestantes = horas;
        const msPerHour = 3600000;

        while (horasRestantes > 0.0001) {
            const dia = nuevaFecha.getDay(); // 0=Domingo

            // Si es domingo, saltar a lunes
            if (dia === 0) {
                nuevaFecha.setDate(nuevaFecha.getDate() + 1);
                nuevaFecha.setHours(0, 0, 0, 0);
                continue;
            }

            // Calcular horas disponibles hasta fin del día
            const finDelDia = new Date(nuevaFecha);
            finDelDia.setDate(finDelDia.getDate() + 1);
            finDelDia.setHours(0, 0, 0, 0);

            const horasDisponibles = (finDelDia - nuevaFecha) / msPerHour;

            if (horasRestantes <= horasDisponibles) {
                nuevaFecha.setTime(nuevaFecha.getTime() + horasRestantes * msPerHour);
                horasRestantes = 0;
            } else {
                horasRestantes -= horasDisponibles;
                nuevaFecha.setTime(finDelDia.getTime());
            }
        }

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




























